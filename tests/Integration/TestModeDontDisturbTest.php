<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Campaigns;
use Mawiblah\RestRoutes;
use Mawiblah\ShortCodes;
use Mawiblah\Subscribers;
use WP_UnitTestCase;

/**
 * Covers the do-not-disturb threshold during a campaign's test send.
 *
 * A tester in test mode gets the test e-mail even inside the threshold -- the test is how
 * a campaign gets approved, and the tester is the one meant to read it -- and the send says
 * so. Everyone else keeps the rule: a non-tester during the test, and a tester in the real
 * send, are still skipped.
 */
class TestModeDontDisturbTest extends WP_UnitTestCase
{
    private const TEMPLATE  = 'mawiblah-newsletter-template';
    private const THRESHOLD = HOUR_IN_SECONDS;

    private int $campaignId = 0;
    private ?object $subscriber = null;
    private array $captured = [];
    private $loopbackStub = null;
    private $mailCapture = null;

    public function setUp(): void
    {
        parent::setUp();

        ShortCodes::register();
        $this->loopbackStub = self::addTemplateLoopbackStub();

        update_option('_mawiblah-dont-send-emails', 'send-emails');
        update_option('_mawiblah-dont-disturb-threshold', (string) self::THRESHOLD);

        $this->campaignId = Campaigns::addCampaign('PHPUnit Test Mode DND', 'Test subject', 'Title', 'Body', [], self::TEMPLATE);

        $this->subscriber = Subscribers::addSubscriber('tester-dnd@mawiblah.test');
        // Contacted a minute ago: well inside the hour's threshold.
        update_post_meta($this->subscriber->id, 'lastInteraction', time() - MINUTE_IN_SECONDS);

        $this->mailCapture = function ($shortCircuit, $atts) {
            $this->captured = $atts;
            return true;
        };
        add_filter('pre_wp_mail', $this->mailCapture, 10, 2);
    }

    public function tearDown(): void
    {
        remove_filter('pre_wp_mail', $this->mailCapture, 10);

        if ($this->loopbackStub) {
            remove_filter('pre_http_request', $this->loopbackStub, 10);
            $this->loopbackStub = null;
        }

        self::removeArchivedTemplate($this->campaignId, self::TEMPLATE);

        if ($this->subscriber) {
            wp_delete_post($this->subscriber->id, true);
            $this->subscriber = null;
        }

        if ($this->campaignId) {
            Campaigns::deleteCampaign($this->campaignId);
            $this->campaignId = 0;
        }

        parent::tearDown();
    }

    public function test_tester_in_test_mode_gets_the_email_inside_the_threshold(): void
    {
        update_post_meta($this->subscriber->id, 'tester', 1);
        Campaigns::testStart($this->campaignId);

        $data = $this->send();

        $this->assertSame('ok', $data['status'], $data['message']);
        $this->assertNotEmpty($this->captured, 'The tester was skipped: wp_mail() was never reached.');
        $this->assertSame($this->subscriber->email, $this->captured['to']);
        $this->assertTrue($data['data']['testerIgnoresDontDisturb']);
        $this->assertStringContainsString('sent anyway', $data['message']);
        $this->assertSame(1, $data['stats']['emailsSent']);
    }

    public function test_non_tester_in_test_mode_is_still_skipped_inside_the_threshold(): void
    {
        Campaigns::testStart($this->campaignId);

        $data = $this->send();

        $this->assertEmpty($this->captured, 'A non-tester was sent the test e-mail inside the threshold.');
        $this->assertSame(1, $data['stats']['doNotDisturb']);
    }

    public function test_tester_in_a_real_send_is_still_skipped_inside_the_threshold(): void
    {
        update_post_meta($this->subscriber->id, 'tester', 1);
        Campaigns::testApprove($this->campaignId);
        Campaigns::campaignStart($this->campaignId);

        $data = $this->send();

        $this->assertEmpty($this->captured, 'A tester was sent the real campaign inside the threshold.');
        $this->assertSame(1, $data['stats']['doNotDisturb']);
    }

    private function send(): array
    {
        $request = new \WP_REST_Request('POST', '/mawiblah/v1/send-email');
        $request->set_param('campaignPostId', $this->campaignId);
        $request->set_param('subscriberId', $this->subscriber->id);
        $request->set_param('email', $this->subscriber->email);
        $request->set_param('lastItem', false);

        return RestRoutes::sendEmail($request)->get_data();
    }

    /**
     * Answers the template loopback in-process. Templates::getTemplateByNameViaRest() posts to
     * the site's own REST route, which has no HTTP server behind it under PHPUnit; this serves
     * the same controller directly so the real lockTemplate() path can be exercised.
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

    /** Removes the snapshot Templates::copyTemplate() writes, so a run leaves no files behind. */
    private static function removeArchivedTemplate(int $campaignId, string $templateName): void
    {
        if (!$campaignId) {
            return;
        }

        $dir  = MAWIBLAH_PLUGIN_DIR . '/email_templates/archived';
        $file = $dir . '/' . $campaignId . '_' . $templateName . '.html';

        if (file_exists($file)) {
            unlink($file);
        }
        if (is_dir($dir) && count((array) scandir($dir)) === 2) {
            rmdir($dir);
        }
    }
}
