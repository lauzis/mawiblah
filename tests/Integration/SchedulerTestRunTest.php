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
 * Covers a schedule's test run: test_run_days before each occurrence the campaign is
 * sent in test mode to its testers, once, and recorded in the schedule's history --
 * without touching the counters, send state or do-not-disturb window the real send
 * depends on.
 */
class SchedulerTestRunTest extends WP_UnitTestCase
{
    private const TEMPLATE = 'mawiblah-newsletter-template';

    private int $campaignId  = 0;
    private int $audienceId  = 0;
    private int $schedulerId = 0;
    private int $testerId    = 0;
    private int $readerId    = 0;
    private array $sent = [];
    private $loopbackStub = null;
    private $mailCapture = null;

    public function setUp(): void
    {
        parent::setUp();

        ShortCodes::register();
        $this->loopbackStub = self::addTemplateLoopbackStub();

        update_option('_mawiblah-dont-send-emails', 'send-emails');
        update_option('_mawiblah-dont-disturb-threshold', (string) WEEK_IN_SECONDS);

        $audience = wp_insert_term('PHPUnit Test Run Audience', Subscribers::postType() . '_category');
        $this->audienceId = (int) $audience['term_id'];

        $this->campaignId = Campaigns::addCampaign('PHPUnit Test Run Campaign', 'Weekly digest', 'Title', 'Body', [$this->audienceId], self::TEMPLATE);
        Campaigns::testApprove($this->campaignId);
        update_post_meta($this->campaignId, 'emailsSend', 42);
        update_post_meta($this->campaignId, 'campaignFinished', time() - WEEK_IN_SECONDS);

        $this->testerId = $this->addSubscriber('tester-run@mawiblah.test');
        update_post_meta($this->testerId, 'tester', 1);
        $this->readerId = $this->addSubscriber('reader-run@mawiblah.test');

        $this->mailCapture = function ($shortCircuit, $atts) {
            $this->sent[] = $atts;
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

        CronSend::unschedule($this->campaignId);

        $archive = MAWIBLAH_PLUGIN_DIR . '/email_templates/archived/' . $this->campaignId . '_' . self::TEMPLATE . '.html';
        if (file_exists($archive)) {
            unlink($archive);
        }

        if ($this->schedulerId) {
            Scheduler::delete($this->schedulerId);
        }
        wp_delete_post($this->testerId, true);
        wp_delete_post($this->readerId, true);
        Campaigns::deleteCampaign($this->campaignId);
        wp_delete_term($this->audienceId, Subscribers::postType() . '_category');

        parent::tearDown();
    }

    public function testADueTestRunSendsToTestersOnlyAndLeavesTheCampaignAlone(): void
    {
        $nextSend = $this->schedule(true, 1, HOUR_IN_SECONDS);

        SchedulerCron::check();

        $this->assertCount(1, $this->sent, 'The test run should reach the tester and no one else.');
        $this->assertSame('tester-run@mawiblah.test', $this->sent[0]['to']);
        $this->assertStringStartsWith('[Test] ', $this->sent[0]['subject']);

        $this->assertTrue(Subscribers::isEmailSent($this->testerId, $this->campaignId, true));
        $this->assertFalse(Subscribers::isEmailSent($this->testerId, $this->campaignId, false), 'The test run marked the tester as sent for the real send.');
        $this->assertEmpty(get_post_meta($this->testerId, 'lastInteraction', true), 'The test run started the do-not-disturb window.');

        $campaign = Campaigns::getCampaignById($this->campaignId);
        $this->assertSame(42, (int) $campaign->emailsSend, 'The test run touched the campaign counters.');
        $this->assertEmpty($campaign->backgroundStarted);
        $this->assertNotEmpty($campaign->campaignFinished);

        $runs = Scheduler::getRuns($this->schedulerId);
        $this->assertCount(1, $runs);
        $this->assertTrue($runs[0]['test']);
        $this->assertSame($nextSend, $runs[0]['test_for']);
        $this->assertSame(1, $runs[0]['sent']);
        $this->assertArrayNotHasKey('skipped_reason', $runs[0]);
    }

    public function testEachOccurrenceIsTestedOnce(): void
    {
        $this->schedule(true, 1, HOUR_IN_SECONDS);

        SchedulerCron::check();
        SchedulerCron::check();

        $this->assertCount(1, $this->sent);
        $this->assertCount(1, Scheduler::getRuns($this->schedulerId));
    }

    public function testATestRunIsNotSentBeforeItsDay(): void
    {
        $this->schedule(true, 1, 2 * DAY_IN_SECONDS);

        SchedulerCron::check();

        $this->assertSame([], $this->sent);
        $this->assertSame([], Scheduler::getRuns($this->schedulerId));
    }

    public function testTheLeadTimeIsHonoured(): void
    {
        $this->schedule(true, 3, 2 * DAY_IN_SECONDS);

        SchedulerCron::check();

        $this->assertCount(1, $this->sent);
    }

    public function testASwitchedOffTestRunSendsNothing(): void
    {
        $this->schedule(false, 1, HOUR_IN_SECONDS);

        SchedulerCron::check();

        $this->assertSame([], $this->sent);
    }

    public function testAScheduleSavedBeforeTheSettingHasItOn(): void
    {
        $this->schedule(true, 1, HOUR_IN_SECONDS);
        delete_post_meta($this->schedulerId, 'test_run');
        delete_post_meta($this->schedulerId, 'test_run_days');

        $scheduler = Scheduler::getById($this->schedulerId);

        $this->assertTrue($scheduler->test_run);
        $this->assertSame(Scheduler::TEST_RUN_DAYS_DEFAULT, $scheduler->test_run_days);
    }

    public function testNoTestersIsRecordedWithTheReason(): void
    {
        delete_post_meta($this->testerId, 'tester');
        $this->schedule(true, 1, HOUR_IN_SECONDS);

        SchedulerCron::check();

        $runs = Scheduler::getRuns($this->schedulerId);
        $this->assertSame([], $this->sent);
        $this->assertTrue($runs[0]['test']);
        $this->assertStringContainsString('no testers', $runs[0]['skipped_reason']);
    }

    public function testAFinishedSendClosesItsRunUnderATestRun(): void
    {
        $this->schedule(true, 1, HOUR_IN_SECONDS);
        Scheduler::startRun($this->schedulerId, $this->campaignId);
        Scheduler::recordTestRun($this->schedulerId, $this->campaignId, time() + DAY_IN_SECONDS, time(), ['sent' => 1]);

        Scheduler::finishRun($this->campaignId);

        $runs = Scheduler::getRuns($this->schedulerId);
        $this->assertTrue($runs[0]['test']);
        $this->assertNotEmpty($runs[1]['finished'], 'The real run was left open behind the test run.');
        $this->assertSame(42, $runs[1]['sent']);
    }

    /** A weekly schedule whose next occurrence is $sendIn seconds away. */
    private function schedule(bool $testRun, int $days, int $sendIn): int
    {
        $this->schedulerId = (int) Scheduler::add('PHPUnit Test Run Schedule', $this->campaignId, 'weekly', '09:00', 1, '', '', false, 0, $testRun, $days);
        $nextSend = time() + $sendIn;
        Scheduler::updateMeta($this->schedulerId, ['next_send' => $nextSend]);

        return $nextSend;
    }

    private function addSubscriber(string $email): int
    {
        $subscriber = Subscribers::addSubscriber($email);
        Subscribers::addSubscriberToAudience((int) $subscriber->id, $this->audienceId);

        return (int) $subscriber->id;
    }

    /** Answers the template loopback in-process; see TestModeDontDisturbTest. */
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
