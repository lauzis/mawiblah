<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Campaigns;
use Mawiblah\CronSend;
use Mawiblah\Scheduler;
use Mawiblah\SchedulerCron;
use Mawiblah\ShortCodes;
use WP_UnitTestCase;

/**
 * Covers the Send Condition Shortcode field: it holds a shortcode *name*, and the scheduler
 * builds `[name campaign_id="N"]` from it before each scheduled send.
 *
 * A campaign whose field held the whole shortcode, the way the Help page showed it, had it
 * wrapped a second time. The shortcode still ran and answered nothing, but the stray text
 * around it made the result non-empty, and a digest meant to wait for new articles went out
 * every day.
 */
class SendConditionTest extends WP_UnitTestCase
{
    private int $campaignId  = 0;
    private int $schedulerId = 0;

    public function setUp(): void
    {
        parent::setUp();

        ShortCodes::register();

        // The test install's own "Hello world!" is dated at install time, moments ago,
        // which would count as new content for every case below.
        foreach (get_posts(['post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']) as $postId) {
            wp_delete_post($postId, true);
        }

        $this->campaignId = Campaigns::addCampaign('PHPUnit Send Condition Campaign', 'Subject', 'Title', 'Content', [], 'test-template');
        Campaigns::testApprove($this->campaignId);

        $this->schedulerId = (int) Scheduler::add('PHPUnit Send Condition Schedule', $this->campaignId, 'daily', '09:00', 1);
        Scheduler::updateMeta($this->schedulerId, ['next_send' => time() - 60]);
    }

    public function tearDown(): void
    {
        CronSend::unschedule($this->campaignId);
        Scheduler::delete($this->schedulerId);
        Campaigns::deleteCampaign($this->campaignId);
        delete_transient(Campaigns::SEND_CONDITION_NOTICE . get_current_user_id());
        $_POST = [];

        parent::tearDown();
    }

    public function fieldValues(): array
    {
        return [
            'a name'                              => ['mawiblah_new_posts_since_last_sent', 'mawiblah_new_posts_since_last_sent'],
            'the whole shortcode, as Help showed' => ['[mawiblah_new_posts_since_last_sent campaign_id="45712"]', 'mawiblah_new_posts_since_last_sent'],
            'brackets and no attributes'          => ['[mawiblah_new_posts_since_last_sent]', 'mawiblah_new_posts_since_last_sent'],
            'self-closing'                        => ['[my-condition /]', 'my-condition'],
            'surrounding space'                   => ['  my_condition  ', 'my_condition'],
            'blank'                               => ['   ', ''],
            'an attribute and no name'            => ['campaign_id="5"', null],
            'markup'                              => ['<b>my_condition</b>', null],
            'a quoted name'                       => ['"my_condition"', null],
        ];
    }

    /**
     * @dataProvider fieldValues
     */
    public function testFieldValueIsReducedToTheShortcodeName(string $value, ?string $expected): void
    {
        $this->assertSame($expected, Campaigns::sendConditionShortcodeName($value));
    }

    public function testAWholeShortcodeInTheFieldStillBlocksASendWhenNothingIsNew(): void
    {
        $this->publishPostAgo(DAY_IN_SECONDS);
        update_post_meta($this->campaignId, 'campaignFinished', time() - HOUR_IN_SECONDS);
        update_post_meta($this->campaignId, 'send_condition_shortcode', '[mawiblah_new_posts_since_last_sent campaign_id="' . $this->campaignId . '"]');

        SchedulerCron::check();

        $this->assertEmpty(get_post_meta($this->campaignId, 'backgroundStarted', true), 'The send started with no new post since the last one.');
        $this->assertStringContainsString('mawiblah_new_posts_since_last_sent', $this->lastSkipReason());
        $this->assertStringNotContainsString('campaign_id', $this->lastSkipReason());
    }

    public function testAWholeShortcodeInTheFieldLetsASendThroughWhenSomethingIsNew(): void
    {
        update_post_meta($this->campaignId, 'campaignFinished', time() - 2 * DAY_IN_SECONDS);
        $this->publishPostAgo(DAY_IN_SECONDS);
        update_post_meta($this->campaignId, 'send_condition_shortcode', '[mawiblah_new_posts_since_last_sent campaign_id="' . $this->campaignId . '"]');

        SchedulerCron::check();

        $this->assertNotEmpty(get_post_meta($this->campaignId, 'backgroundStarted', true), 'A post published since the last send did not let the send through.');
    }

    public function testAConditionThatIsNotARegisteredShortcodeBlocksTheSend(): void
    {
        update_post_meta($this->campaignId, 'send_condition_shortcode', 'mawiblah_phpunit_no_such_condition');

        SchedulerCron::check();

        $this->assertEmpty(get_post_meta($this->campaignId, 'backgroundStarted', true), 'An unknown shortcode, printed back as text, let the send through.');
        $this->assertStringContainsString('not a registered shortcode', $this->lastSkipReason());
        $this->assertGreaterThan(time(), Scheduler::getById($this->schedulerId)->next_send, 'The schedule did not move on to its next occurrence.');
    }

    public function testSavingAWholeShortcodeStoresTheNameAndSaysSo(): void
    {
        $this->postCampaignDetails('[mawiblah_new_posts_since_last_sent campaign_id="' . $this->campaignId . '"]');

        Campaigns::saveMetaBoxData($this->campaignId);

        $this->assertSame('mawiblah_new_posts_since_last_sent', get_post_meta($this->campaignId, 'send_condition_shortcode', true));
        $this->assertSame(['warning'], array_column($this->notices(), 'type'));
    }

    public function testSavingAValueWithNoNameInItKeepsTheConditionItHad(): void
    {
        update_post_meta($this->campaignId, 'send_condition_shortcode', 'mawiblah_new_posts_since_last_sent');
        $this->postCampaignDetails('campaign_id="' . $this->campaignId . '"');

        Campaigns::saveMetaBoxData($this->campaignId);

        $this->assertSame('mawiblah_new_posts_since_last_sent', get_post_meta($this->campaignId, 'send_condition_shortcode', true));
        $this->assertSame(['error'], array_column($this->notices(), 'type'));
    }

    public function testSavingANameNoShortcodeIsRegisteredUnderWarns(): void
    {
        $this->postCampaignDetails('mawiblah_phpunit_no_such_condition');

        Campaigns::saveMetaBoxData($this->campaignId);

        $this->assertSame('mawiblah_phpunit_no_such_condition', get_post_meta($this->campaignId, 'send_condition_shortcode', true));
        $this->assertSame(['warning'], array_column($this->notices(), 'type'));
    }

    public function testSavingABlankFieldClearsTheCondition(): void
    {
        update_post_meta($this->campaignId, 'send_condition_shortcode', 'mawiblah_new_posts_since_last_sent');
        $this->postCampaignDetails('');

        Campaigns::saveMetaBoxData($this->campaignId);

        $this->assertSame('', get_post_meta($this->campaignId, 'send_condition_shortcode', true));
        $this->assertSame([], $this->notices());
    }

    private function publishPostAgo(int $seconds): void
    {
        self::factory()->post->create([
            'post_status' => 'publish',
            'post_date'   => gmdate('Y-m-d H:i:s', time() - $seconds),
        ]);
    }

    private function lastSkipReason(): string
    {
        $runs = Scheduler::getRuns($this->schedulerId);

        return (string) ($runs[0]['skipped_reason'] ?? '');
    }

    /** Fills $_POST the way the campaign edit form does, slashes included. */
    private function postCampaignDetails(string $sendCondition): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $_POST = wp_slash([
            'mawiblah_campaign_details_nonce' => wp_create_nonce('mawiblah_save_campaign_details'),
            'send_condition_shortcode'        => $sendCondition,
        ]);
    }

    private function notices(): array
    {
        return array_values((array) (get_transient(Campaigns::SEND_CONDITION_NOTICE . get_current_user_id()) ?: []));
    }
}
