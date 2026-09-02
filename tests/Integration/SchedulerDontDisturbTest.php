<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Campaigns;
use Mawiblah\CronSend;
use Mawiblah\RestRoutes;
use Mawiblah\Scheduler;
use Mawiblah\SchedulerCron;
use Mawiblah\ShortCodes;
use Mawiblah\Subscribers;
use WP_UnitTestCase;

/**
 * Covers the per-schedule do-not-disturb override: a schedule may contact subscribers on
 * its own threshold rather than the site-wide one, and only for the run it started.
 *
 * SchedulerCron writes the resolved threshold to the campaign as
 * CronSend::DND_OVERRIDE_META, CronSend reads it in place of the global setting, and the
 * meta is dropped when the send finishes — these tests assert all three.
 */
class SchedulerDontDisturbTest extends WP_UnitTestCase
{
    private const TEMPLATE       = 'mawiblah-newsletter-template';
    private const GLOBAL_SECONDS = 3600;
    private const SCHEDULE_SECONDS = 60;

    private int $campaignId  = 0;
    private int $audienceId  = 0;
    private int $schedulerId = 0;
    private array $subscriberIds = [];
    private $loopbackStub = null;

    public function setUp(): void
    {
        parent::setUp();

        ShortCodes::register();
        $this->loopbackStub = self::addTemplateLoopbackStub();

        update_option('_mawiblah-dont-send-emails', 'send-emails');
        update_option('_mawiblah-dont-disturb-threshold', (string) self::GLOBAL_SECONDS);

        $audience = wp_insert_term('PHPUnit DND Audience', Subscribers::postType() . '_category');
        $this->audienceId = (int) $audience['term_id'];

        $this->campaignId = Campaigns::addCampaign(
            'PHPUnit Do Not Disturb Campaign',
            'Scheduled digest',
            'Scheduled digest',
            'Scheduled digest body',
            [$this->audienceId],
            self::TEMPLATE
        );

        Campaigns::testApprove($this->campaignId);
    }

    public function tearDown(): void
    {
        if ($this->loopbackStub) {
            remove_filter('pre_http_request', $this->loopbackStub, 10);
            $this->loopbackStub = null;
        }

        CronSend::unschedule($this->campaignId);
        $this->removeArchive();

        if ($this->schedulerId) {
            Scheduler::delete($this->schedulerId);
            $this->schedulerId = 0;
        }

        foreach ($this->subscriberIds as $subscriberId) {
            wp_delete_post($subscriberId, true);
        }
        $this->subscriberIds = [];

        if ($this->campaignId) {
            Campaigns::deleteCampaign($this->campaignId);
            $this->campaignId = 0;
        }

        if ($this->audienceId) {
            wp_delete_term($this->audienceId, Subscribers::postType() . '_category');
            $this->audienceId = 0;
        }

        parent::tearDown();
    }

    public function test_a_schedule_without_an_override_uses_the_global_threshold(): void
    {
        // Contacted 10 minutes ago: inside the global hour, outside the schedule's minute.
        $recent = $this->addSubscriber('dnd-global@mawiblah.test', time() - 600);

        $this->runSchedule(false, 0);

        $this->assertFalse(
            metadata_exists('post', $this->campaignId, CronSend::DND_OVERRIDE_META),
            'A schedule with the override off must not leave a threshold on the campaign.'
        );
        $this->assertSame(
            self::GLOBAL_SECONDS,
            CronSend::doNotDisturbThreshold($this->campaignId),
            'Without an override the global setting decides.'
        );

        $sent = $this->processBatch();

        $this->assertSame([], $sent, 'The global threshold should have held this subscriber back.');
        $this->assertSame('sent', get_post_meta($recent, 'sent_' . $this->campaignId, true));
    }

    public function test_an_override_replaces_the_global_threshold_for_that_send(): void
    {
        // Outside the schedule's 60s window, but well inside the global hour.
        $outside = $this->addSubscriber('dnd-outside@mawiblah.test', time() - 600);
        // Inside the schedule's 60s window too — skipped either way.
        $inside  = $this->addSubscriber('dnd-inside@mawiblah.test', time() - 5);

        $this->runSchedule(true, self::SCHEDULE_SECONDS);

        $this->assertSame(
            self::SCHEDULE_SECONDS,
            (int) get_post_meta($this->campaignId, CronSend::DND_OVERRIDE_META, true)
        );
        $this->assertSame(self::SCHEDULE_SECONDS, CronSend::doNotDisturbThreshold($this->campaignId));

        $sent = $this->processBatch();

        $this->assertSame(
            ['dnd-outside@mawiblah.test'],
            $sent,
            'The schedule threshold should have released the subscriber the global one blocked.'
        );
        $this->assertSame('sent', get_post_meta($inside, 'sent_' . $this->campaignId, true));
        $this->assertSame('sent', get_post_meta($outside, 'sent_' . $this->campaignId, true));
    }

    public function test_the_override_is_removed_once_the_send_finishes(): void
    {
        $this->addSubscriber('dnd-finish@mawiblah.test', time() - 600);

        $this->runSchedule(true, self::SCHEDULE_SECONDS);
        $this->assertTrue(metadata_exists('post', $this->campaignId, CronSend::DND_OVERRIDE_META));

        $this->processBatch();

        $this->assertFalse(
            metadata_exists('post', $this->campaignId, CronSend::DND_OVERRIDE_META),
            'The per-run override outlived the send it belonged to.'
        );
        $this->assertNotEmpty(get_post_meta($this->campaignId, 'campaignFinished', true));
        $this->assertSame(
            self::GLOBAL_SECONDS,
            CronSend::doNotDisturbThreshold($this->campaignId),
            'A later send must fall back to the global setting.'
        );
    }

    public function test_turning_the_override_off_falls_back_to_the_global_threshold(): void
    {
        update_post_meta($this->campaignId, CronSend::DND_OVERRIDE_META, self::SCHEDULE_SECONDS);

        $this->runSchedule(false, 0);

        $this->assertFalse(
            metadata_exists('post', $this->campaignId, CronSend::DND_OVERRIDE_META),
            'A leftover override must be cleared when the schedule no longer asks for one.'
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Creates a one-off schedule that is already due, then lets SchedulerCron pick it up. */
    private function runSchedule(bool $overrideDnd, int $threshold): void
    {
        $this->schedulerId = (int) Scheduler::add(
            'PHPUnit DND Schedule',
            $this->campaignId,
            'once',
            '09:00',
            1,
            gmdate('Y-m-d', time() + 86400),
            '',
            $overrideDnd,
            $threshold
        );

        Scheduler::updateMeta($this->schedulerId, ['next_send' => time() - 60]);

        SchedulerCron::check();

        $this->assertNotEmpty(
            get_post_meta($this->campaignId, 'backgroundStarted', true),
            'SchedulerCron did not start the scheduled send.'
        );
    }

    /**
     * Runs one background batch with wp_mail intercepted.
     *
     * @return string[] Addresses the mailer was handed, in send order.
     */
    private function processBatch(): array
    {
        $sent    = [];
        $capture = static function ($shortCircuit, $atts) use (&$sent) {
            $sent[] = $atts['to'];
            return true;
        };

        add_filter('pre_wp_mail', $capture, 10, 2);
        CronSend::processBatch($this->campaignId);
        remove_filter('pre_wp_mail', $capture, 10);

        return $sent;
    }

    /** Creates an audience subscriber last contacted at $lastInteraction (a Unix timestamp). */
    private function addSubscriber(string $email, int $lastInteraction): int
    {
        $subscriber = Subscribers::addSubscriber($email);
        $this->subscriberIds[] = (int) $subscriber->id;

        Subscribers::addSubscriberToAudience((int) $subscriber->id, $this->audienceId);
        update_post_meta($subscriber->id, 'lastInteraction', $lastInteraction);

        return (int) $subscriber->id;
    }

    private function archivePath(): string
    {
        return MAWIBLAH_PLUGIN_DIR . '/email_templates/archived/' . $this->campaignId . '_' . self::TEMPLATE . '.html';
    }

    /** Removes the snapshot Templates::copyTemplate() writes, so a run leaves no files behind. */
    private function removeArchive(): void
    {
        if (!$this->campaignId) {
            return;
        }

        if (file_exists($this->archivePath())) {
            unlink($this->archivePath());
        }

        $dir = MAWIBLAH_PLUGIN_DIR . '/email_templates/archived';
        if (is_dir($dir) && count((array) scandir($dir)) === 2) {
            rmdir($dir);
        }
    }

    /**
     * Answers the template loopback in-process — see EmailTemplateTest for why the real
     * wp_remote_post() cannot reach the site under PHPUnit.
     */
    private static function addTemplateLoopbackStub(): callable
    {
        $stub = static function ($preempt, $args, $url) {
            if (!str_contains((string) $url, 'mawiblah/v1/get-html-template')) {
                return $preempt;
            }

            $body    = json_decode((string) ($args['body'] ?? ''), true);
            $request = new \WP_REST_Request('POST', '/mawiblah/v1/get-html-template');
            $request->set_param('template', $body['template'] ?? '');

            $response = RestRoutes::getHtmlTemplate($request);

            return [
                'headers'  => [],
                'body'     => wp_json_encode($response->get_data()),
                'response' => ['code' => $response->get_status(), 'message' => ''],
                'cookies'  => [],
                'filename' => null,
            ];
        };

        add_filter('pre_http_request', $stub, 10, 3);

        return $stub;
    }
}
