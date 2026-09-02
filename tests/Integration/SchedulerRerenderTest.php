<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Campaigns;
use Mawiblah\RestRoutes;
use Mawiblah\Scheduler;
use Mawiblah\ShortCodes;
use WP_UnitTestCase;

/**
 * Covers the promise a recurring schedule makes: the next occurrence goes out with the
 * newest content, not with the snapshot the first occurrence froze.
 *
 * Scheduler::resetCampaignForResend() expresses that by dropping the email_template_copied
 * flag, which is what lets Templates::copyTemplate() overwrite the archived snapshot on the
 * next lockTemplate(). These tests assert both halves — the flag and the snapshot on disk.
 */
class SchedulerRerenderTest extends WP_UnitTestCase
{
    private const TEMPLATE = 'mawiblah-newsletter-template';

    private int $campaignId = 0;
    private array $postIds = [];
    private $loopbackStub = null;

    public function setUp(): void
    {
        parent::setUp();

        ShortCodes::register();
        $this->loopbackStub = self::addTemplateLoopbackStub();

        $this->campaignId = Campaigns::addCampaign(
            'PHPUnit Recurring Campaign',
            'Weekly digest',
            'Weekly digest',
            'Weekly digest body',
            [],
            self::TEMPLATE
        );
    }

    public function tearDown(): void
    {
        if ($this->loopbackStub) {
            remove_filter('pre_http_request', $this->loopbackStub, 10);
            $this->loopbackStub = null;
        }

        $this->removeArchive();

        foreach ($this->postIds as $postId) {
            wp_delete_post($postId, true);
        }
        $this->postIds = [];

        if ($this->campaignId) {
            Campaigns::deleteCampaign($this->campaignId);
            $this->campaignId = 0;
        }

        parent::tearDown();
    }

    public function test_weekly_resend_rerenders_the_template_with_the_newest_content(): void
    {
        $firstRender = $this->lockTemplate();
        $newTitle    = 'Published After The First Send';

        $this->assertStringNotContainsString($newTitle, $firstRender);
        $this->assertNotEmpty(get_post_meta($this->campaignId, 'email_template_copied', true));
        $this->assertFileExists($this->archivePath());
        $this->assertStringNotContainsString($newTitle, (string) file_get_contents($this->archivePath()));

        $this->publishPost($newTitle);

        Scheduler::resetCampaignForResend($this->campaignId, 'weekly');

        $this->assertEmpty(
            get_post_meta($this->campaignId, 'email_template_copied', true),
            'The locked template copy was not released for the next weekly send.'
        );

        $secondRender = $this->lockTemplate();

        $this->assertStringContainsString(
            $newTitle,
            $secondRender,
            'The re-rendered letter does not carry the post published since the last send.'
        );
        $this->assertStringContainsString(
            $newTitle,
            (string) file_get_contents($this->archivePath()),
            'The archived snapshot was not rewritten with the fresh render.'
        );
    }

    public function test_monthly_resend_also_releases_the_locked_template(): void
    {
        $this->lockTemplate();
        $this->assertNotEmpty(get_post_meta($this->campaignId, 'email_template_copied', true));

        Scheduler::resetCampaignForResend($this->campaignId, 'monthly');

        $this->assertEmpty(get_post_meta($this->campaignId, 'email_template_copied', true));
    }

    public function test_one_off_schedule_keeps_the_locked_template(): void
    {
        $this->lockTemplate();
        $newTitle = 'Published After A One Off Send';

        Scheduler::resetCampaignForResend($this->campaignId, 'once');

        $this->assertNotEmpty(
            get_post_meta($this->campaignId, 'email_template_copied', true),
            'A one-off schedule must not release the locked template copy.'
        );

        $this->publishPost($newTitle);
        $this->lockTemplate();

        $this->assertStringNotContainsString(
            $newTitle,
            (string) file_get_contents($this->archivePath()),
            'The archived snapshot was rewritten even though the schedule is one-off.'
        );
    }

    public function test_recurring_schedule_keeps_the_locked_template_when_rerender_is_disabled(): void
    {
        update_post_meta($this->campaignId, 'rerender_on_recurring', '0');

        $this->lockTemplate();
        $newTitle = 'Published While Rerender Is Off';

        Scheduler::resetCampaignForResend($this->campaignId, 'weekly');

        $this->assertNotEmpty(
            get_post_meta($this->campaignId, 'email_template_copied', true),
            'rerender_on_recurring is off, so the locked template copy must survive.'
        );

        $this->publishPost($newTitle);
        $this->lockTemplate();

        $this->assertStringNotContainsString(
            $newTitle,
            (string) file_get_contents($this->archivePath()),
            'The archived snapshot was rewritten even though rerender_on_recurring is off.'
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function lockTemplate(): string
    {
        $campaign = Campaigns::getCampaignById($this->campaignId);
        $rendered = Campaigns::lockTemplate($campaign, false);

        $this->assertIsString($rendered, 'lockTemplate() could not render the newsletter template.');

        return $rendered;
    }

    private function publishPost(string $title): void
    {
        $this->postIds[] = self::factory()->post->create([
            'post_title'  => $title,
            'post_status' => 'publish',
        ]);
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
