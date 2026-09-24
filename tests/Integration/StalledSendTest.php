<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Campaigns;
use Mawiblah\CronSend;
use Mawiblah\RestRoutes;
use Mawiblah\SchedulerCron;
use Mawiblah\ShortCodes;
use Mawiblah\Subscribers;
use WP_UnitTestCase;

/**
 * Covers resuming a background send whose next batch went missing.
 *
 * A batch hands over to the next through one WP-Cron event, and a concurrent cron
 * run writing back an older copy of the queue can erase it. On gudlenieks.lv a
 * monthly letter stopped at 1300 of ~3356 subscribers that way. CronSend stamps
 * its activity and resumeStalled() queues the next batch again once a send has
 * been idle with nothing queued -- but never for a batch that is still busy, never
 * twice, never for a finished send, and never for one that died long ago.
 */
class StalledSendTest extends WP_UnitTestCase
{
    private const TEMPLATE = 'mawiblah-newsletter-template';

    private int $campaignId = 0;
    private int $audienceId = 0;
    private array $subscriberIds = [];
    private $loopbackStub = null;

    public function setUp(): void
    {
        parent::setUp();

        ShortCodes::register();
        $this->loopbackStub = self::addTemplateLoopbackStub();

        update_option('_mawiblah-dont-send-emails', 'send-emails');
        update_option('_mawiblah-dont-disturb-threshold', '0');
        update_option('_mawiblah-background-batch-size', '2');

        $audience = wp_insert_term('PHPUnit Stalled Send Audience', Subscribers::postType() . '_category');
        $this->audienceId = (int) $audience['term_id'];

        $this->campaignId = Campaigns::addCampaign(
            'PHPUnit Stalled Send Campaign',
            'Stalled send',
            'Stalled send',
            'Stalled send body',
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

        delete_option('_mawiblah-background-batch-size');

        parent::tearDown();
    }

    public function test_a_lost_hand_off_is_queued_again_and_the_send_finishes(): void
    {
        foreach (['one', 'two', 'three', 'four'] as $name) {
            $this->addSubscriber("stalled-{$name}@mawiblah.test");
        }

        Campaigns::backgroundSendStart($this->campaignId);

        $first = $this->processBatch();
        $this->assertCount(2, $first, 'The first batch should have sent a full batch of two.');
        $this->assertNotFalse($this->queued(), 'A batch with more to send queues the next one.');
        $this->assertNotEmpty(get_post_meta($this->campaignId, CronSend::LAST_ACTIVITY_META, true));

        // The hand-off is lost, as a concurrent write of the cron option does it,
        // and the send sits idle past the threshold.
        CronSend::unschedule($this->campaignId);
        $this->idleFor(CronSend::STALL_AFTER + 60);

        $this->assertSame(1, CronSend::resumeStalled(), 'The stalled send was not resumed.');
        $this->assertNotFalse($this->queued(), 'Resuming must queue the next batch.');

        $second = $this->processBatch();

        $this->assertCount(2, $second);
        $this->assertSame([], array_intersect($first, $second), 'Nobody may get the letter twice.');
        $this->assertNotEmpty(get_post_meta($this->campaignId, 'campaignFinished', true));
        $this->assertFalse(
            metadata_exists('post', $this->campaignId, CronSend::LAST_ACTIVITY_META),
            'The activity stamp outlived the send.'
        );
    }

    public function test_a_send_that_was_busy_recently_is_left_alone(): void
    {
        Campaigns::backgroundSendStart($this->campaignId);
        $this->idleFor(CronSend::STALL_AFTER - 60);

        $this->assertSame(0, CronSend::resumeStalled());
        $this->assertFalse($this->queued(), 'A batch still sending has nothing queued, and must not get a second one.');
    }

    public function test_a_send_with_its_next_batch_queued_is_left_alone(): void
    {
        Campaigns::backgroundSendStart($this->campaignId);
        CronSend::schedule($this->campaignId);
        $this->idleFor(CronSend::STALL_AFTER + 60);

        $this->assertSame(0, CronSend::resumeStalled());
    }

    public function test_a_finished_send_is_not_resumed(): void
    {
        Campaigns::backgroundSendStart($this->campaignId);
        Campaigns::campaignFinish($this->campaignId);
        $this->idleFor(CronSend::STALL_AFTER + 60);

        $this->assertSame(0, CronSend::resumeStalled());
        $this->assertFalse($this->queued());
    }

    public function test_a_send_stalled_for_longer_than_a_day_is_reported_not_resumed(): void
    {
        Campaigns::backgroundSendStart($this->campaignId);
        $this->idleFor(CronSend::RESUME_WITHIN + 3600);

        $this->assertSame(0, CronSend::resumeStalled());
        $this->assertFalse($this->queued(), 'A send idle for over a day must be left for a person to decide on.');
        $this->assertNotEmpty(get_post_meta($this->campaignId, CronSend::STALL_REPORTED_META, true));
    }

    public function test_the_scheduler_check_resumes_a_stalled_send(): void
    {
        Campaigns::backgroundSendStart($this->campaignId);
        $this->idleFor(CronSend::STALL_AFTER + 60);

        SchedulerCron::check();

        $this->assertNotFalse($this->queued(), 'SchedulerCron::check() did not look for stalled sends.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return int|false Timestamp of the queued batch, or false. */
    private function queued()
    {
        return wp_next_scheduled(CronSend::HOOK, [$this->campaignId]);
    }

    /** Moves the send's start and last activity $seconds into the past. */
    private function idleFor(int $seconds): void
    {
        update_post_meta($this->campaignId, 'backgroundStarted', time() - $seconds);
        update_post_meta($this->campaignId, CronSend::LAST_ACTIVITY_META, time() - $seconds);
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

    private function addSubscriber(string $email): int
    {
        $subscriber = Subscribers::addSubscriber($email);
        $this->subscriberIds[] = (int) $subscriber->id;

        Subscribers::addSubscriberToAudience((int) $subscriber->id, $this->audienceId);

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
