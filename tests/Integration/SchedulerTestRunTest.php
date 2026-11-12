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
 *
 * Also covers what both are sent as: each occurrence is a run campaign of its own,
 * copied from the schedule's campaign with the schedule's next-send overrides, made
 * by the test run and sent by the occurrence.
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
            foreach ($this->runCampaigns() as $runId) {
                CronSend::unschedule($runId);
                $runArchive = MAWIBLAH_PLUGIN_DIR . '/email_templates/archived/' . $runId . '_' . self::TEMPLATE . '.html';
                if (file_exists($runArchive)) {
                    unlink($runArchive);
                }
                Campaigns::deleteCampaign($runId);
            }
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

        $runs  = Scheduler::getRuns($this->schedulerId);
        $runId = (int) $runs[0]['campaign_id'];
        $this->assertNotSame($this->campaignId, $runId, 'The test run sent the schedule\'s own campaign, not the occurrence\'s.');

        $this->assertTrue(Subscribers::isEmailSent($this->testerId, $runId, true));
        $this->assertFalse(Subscribers::isEmailSent($this->testerId, $runId, false), 'The test run marked the tester as sent for the real send.');
        $this->assertEmpty(get_post_meta($this->testerId, 'lastInteraction', true), 'The test run started the do-not-disturb window.');

        $campaign = Campaigns::getCampaignById($this->campaignId);
        $this->assertSame(42, (int) $campaign->emailsSend, 'The test run touched the campaign counters.');
        $this->assertEmpty($campaign->backgroundStarted);
        $this->assertNotEmpty($campaign->campaignFinished);

        $this->assertEmpty(get_post_meta($runId, 'backgroundStarted', true), 'The test run started the real send.');

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

    public function testTheOccurrenceIsSentAsACampaignOfItsOwnWithTheOverrides(): void
    {
        $this->schedule(true, 1, HOUR_IN_SECONDS);
        Scheduler::setNextOverrides($this->schedulerId, 'Only this time', 'A special title', '<p>A special paragraph.</p>');

        SchedulerCron::check();

        $this->assertSame('[Test] Only this time', $this->sent[0]['subject'], 'The test run did not carry the next-send subject.');
        $testedId = (int) Scheduler::getRuns($this->schedulerId)[0]['campaign_id'];

        // The occurrence comes due: it sends the copy the testers were sent.
        $due = time() - 60;
        Scheduler::updateMeta($this->schedulerId, ['next_send' => $due]);
        update_post_meta($testedId, Campaigns::RUN_FOR_META, $due);
        SchedulerCron::check();

        $scheduler = Scheduler::getById($this->schedulerId);
        $runId     = $scheduler->last_run_campaign_id;
        $run       = Campaigns::getCampaignById($runId);

        $this->assertSame($testedId, $runId, 'The real send was not the campaign the testers were sent.');
        $this->assertNotEmpty($run->backgroundStarted);
        $this->assertSame('Only this time', $run->subject);
        $this->assertSame('A special title', $run->contentTitle);
        $this->assertSame('<p>A special paragraph.</p>', $run->post_content);
        $this->assertSame($this->schedulerId, $run->scheduler_id);
        $this->assertSame($this->campaignId, $run->source_campaign_id);

        $source = Campaigns::getCampaignById($this->campaignId);
        $this->assertSame('Weekly digest', $source->subject, 'The schedule\'s campaign was changed.');
        $this->assertSame('Body', $source->post_content);
        $this->assertEmpty($source->backgroundStarted, 'The schedule\'s campaign was sent.');

        $this->assertFalse(Scheduler::hasNextOverrides($scheduler), 'The overrides outlived the send they were for.');
        $this->assertSame(0, $scheduler->run_campaign_id);
        $this->assertSame($runId, (int) Scheduler::getRuns($this->schedulerId)[0]['campaign_id'], 'History does not point at the run campaign.');
    }

    public function testEachOccurrenceIsACampaignOfItsOwn(): void
    {
        $this->schedule(false, 1, HOUR_IN_SECONDS);

        Scheduler::updateMeta($this->schedulerId, ['next_send' => time() - 120]);
        SchedulerCron::check();
        $first = Scheduler::getById($this->schedulerId)->last_run_campaign_id;
        Campaigns::campaignFinish($first);
        Campaigns::backgroundSendStop($first);

        Scheduler::updateMeta($this->schedulerId, ['next_send' => time() - 60]);
        SchedulerCron::check();
        $second = Scheduler::getById($this->schedulerId)->last_run_campaign_id;

        $this->assertNotSame(0, $first);
        $this->assertNotSame(0, $second);
        $this->assertNotSame($first, $second, 'Two occurrences were counted on one campaign.');
        $this->assertSame([$second, $first], array_map('intval', array_column(Scheduler::getRuns($this->schedulerId), 'campaign_id')));
    }

    public function testASendStillRunningOnTheLastRunSkipsTheNextOccurrence(): void
    {
        $this->schedule(false, 1, HOUR_IN_SECONDS);

        Scheduler::updateMeta($this->schedulerId, ['next_send' => time() - 120]);
        SchedulerCron::check();
        $first = Scheduler::getById($this->schedulerId)->last_run_campaign_id;

        Scheduler::updateMeta($this->schedulerId, ['next_send' => time() - 60]);
        SchedulerCron::check();

        $this->assertSame($first, Scheduler::getById($this->schedulerId)->last_run_campaign_id, 'A second send started while the first was still going.');
        $this->assertStringContainsString('still running', Scheduler::getRuns($this->schedulerId)[0]['skipped_reason'] ?? '');
    }

    public function testARefusedOccurrenceDiscardsTheCopyItsTestRunMade(): void
    {
        $this->schedule(true, 1, HOUR_IN_SECONDS);
        SchedulerCron::check();
        $testedId = (int) Scheduler::getRuns($this->schedulerId)[0]['campaign_id'];

        add_shortcode('mawiblah_phpunit_refuse', fn() => '');
        update_post_meta($this->campaignId, 'send_condition_shortcode', 'mawiblah_phpunit_refuse');
        Scheduler::updateMeta($this->schedulerId, ['next_send' => time() - 60]);
        SchedulerCron::check();

        $this->assertFalse(get_post_status($testedId), 'A send that never happened was left in the campaign list.');
        $this->assertSame(0, Scheduler::getById($this->schedulerId)->run_campaign_id);
    }

    public function testAFinishedRunCampaignClosesItsRun(): void
    {
        $this->schedule(false, 1, HOUR_IN_SECONDS);
        Scheduler::updateMeta($this->schedulerId, ['next_send' => time() - 60]);
        SchedulerCron::check();
        $runId = Scheduler::getById($this->schedulerId)->last_run_campaign_id;
        update_post_meta($runId, 'emailsSend', 7);

        Scheduler::finishRun($runId);

        $runs = Scheduler::getRuns($this->schedulerId);
        $this->assertNotEmpty($runs[0]['finished']);
        $this->assertSame(7, $runs[0]['sent'], 'The run took its counts from the wrong campaign.');
    }

    public function testTheLastSendIsTheNewestRunCampaignsFinish(): void
    {
        $this->schedule(false, 1, HOUR_IN_SECONDS);
        Scheduler::updateMeta($this->schedulerId, ['next_send' => time() - 60]);
        SchedulerCron::check();
        $runId = Scheduler::getById($this->schedulerId)->last_run_campaign_id;
        $finished = time() - 60;
        update_post_meta($runId, 'campaignFinished', $finished);

        $this->assertSame($finished, Campaigns::lastFinished($this->campaignId));
    }

    /** Run campaigns this test's schedule made. */
    private function runCampaigns(): array
    {
        return get_posts([
            'post_type'      => Campaigns::postType(),
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => Campaigns::RUN_SCHEDULER_META,
            'meta_value'     => $this->schedulerId,
        ]);
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
