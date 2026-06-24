<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Campaigns;
use Mawiblah\Subscribers;
use WP_UnitTestCase;

/**
 * Tests for the open-tracking features introduced in this PR:
 *
 *  - Campaigns::STAT_EMAILS_OPENED constant
 *  - Campaigns::recordOpen()              — now also stores open_time
 *  - Campaigns::getOpenTimesByHourOfDay() — new method
 *  - Campaigns::getOpenTimesByDayOfWeek() — new method
 *  - Campaigns::getStatsForCampaign()     — now includes STAT_EMAILS_OPENED
 *  - Campaigns::getConversionStatsForCampaign() — now includes STAT_EMAILS_OPENED
 *  - Subscribers::renderMetaData()        — now renders an Opens section
 */
class OpenTrackingTest extends WP_UnitTestCase
{
    private int    $campaignId;
    private object $subscriber;

    public function setUp(): void
    {
        parent::setUp();
        $this->campaignId = Campaigns::addCampaign(
            'Open Tracking Test Campaign',
            'Test Subject',
            'Test Title',
            'Content',
            [],
            'test-template'
        );
        $this->subscriber = Subscribers::addSubscriber('opentrack@mawiblah.test');
    }

    public function tearDown(): void
    {
        Campaigns::deleteCampaign($this->campaignId);
        wp_delete_post($this->subscriber->id, true);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // STAT_EMAILS_OPENED constant
    // -----------------------------------------------------------------------

    public function test_stat_emails_opened_constant_has_correct_value(): void
    {
        $this->assertSame('emailsOpened', Campaigns::STAT_EMAILS_OPENED);
    }

    // -----------------------------------------------------------------------
    // Campaigns::recordOpen()
    // -----------------------------------------------------------------------

    public function test_record_open_increments_emails_opened_counter(): void
    {
        $before = (int) get_post_meta($this->campaignId, 'emailsOpened', true);
        Campaigns::recordOpen($this->subscriber->id, $this->campaignId);
        $after = (int) get_post_meta($this->campaignId, 'emailsOpened', true);

        $this->assertSame($before + 1, $after);
    }

    public function test_record_open_stores_subscriber_open_timestamp(): void
    {
        $before = time();
        Campaigns::recordOpen($this->subscriber->id, $this->campaignId);
        $after = time();

        $timestamp = (int) get_post_meta($this->subscriber->id, 'opened_' . $this->campaignId, true);

        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }

    public function test_record_open_stores_open_time_on_campaign_for_analytics(): void
    {
        Campaigns::recordOpen($this->subscriber->id, $this->campaignId);

        $openTimes = get_post_meta($this->campaignId, 'open_time', false);

        $this->assertCount(1, $openTimes, 'Expected exactly one open_time entry after one open');
        $this->assertGreaterThan(0, (int) $openTimes[0]);
    }

    public function test_record_open_is_idempotent_for_same_subscriber(): void
    {
        Campaigns::recordOpen($this->subscriber->id, $this->campaignId);
        Campaigns::recordOpen($this->subscriber->id, $this->campaignId);

        $count = (int) get_post_meta($this->campaignId, 'emailsOpened', true);
        $this->assertSame(1, $count, 'emailsOpened must not increment on duplicate open');
    }

    public function test_record_open_does_not_add_duplicate_open_time_for_same_subscriber(): void
    {
        Campaigns::recordOpen($this->subscriber->id, $this->campaignId);
        Campaigns::recordOpen($this->subscriber->id, $this->campaignId);

        $openTimes = get_post_meta($this->campaignId, 'open_time', false);
        $this->assertCount(1, $openTimes, 'open_time must not be added on a duplicate open');
    }

    public function test_record_open_counts_each_unique_subscriber(): void
    {
        $sub2 = Subscribers::addSubscriber('opentrack2@mawiblah.test');

        Campaigns::recordOpen($this->subscriber->id, $this->campaignId);
        Campaigns::recordOpen($sub2->id, $this->campaignId);

        $count     = (int) get_post_meta($this->campaignId, 'emailsOpened', true);
        $openTimes = get_post_meta($this->campaignId, 'open_time', false);

        $this->assertSame(2, $count);
        $this->assertCount(2, $openTimes);

        wp_delete_post($sub2->id, true);
    }

    // -----------------------------------------------------------------------
    // Campaigns::getOpenTimesByHourOfDay()
    // -----------------------------------------------------------------------

    public function test_get_open_times_by_hour_of_day_returns_all_24_hours(): void
    {
        $result = Campaigns::getOpenTimesByHourOfDay($this->campaignId);

        $this->assertCount(24, $result);
        for ($h = 0; $h < 24; $h++) {
            $this->assertArrayHasKey($h, $result);
        }
    }

    public function test_get_open_times_by_hour_of_day_all_zeros_for_new_campaign(): void
    {
        $result = Campaigns::getOpenTimesByHourOfDay($this->campaignId);

        foreach ($result as $count) {
            $this->assertSame(0, $count);
        }
    }

    public function test_get_open_times_by_hour_of_day_counts_correctly(): void
    {
        // Build a known timestamp at 14:30:00 on a fixed date.
        $ts14 = mktime(14, 30, 0, 6, 1, 2025); // hour 14
        $ts9  = mktime(9, 0, 0, 6, 2, 2025);   // hour 9

        add_post_meta($this->campaignId, 'open_time', $ts14, false);
        add_post_meta($this->campaignId, 'open_time', $ts14, false);
        add_post_meta($this->campaignId, 'open_time', $ts9, false);

        $result = Campaigns::getOpenTimesByHourOfDay($this->campaignId);

        $this->assertSame(2, $result[14]);
        $this->assertSame(1, $result[9]);
        // All other hours should remain 0
        for ($h = 0; $h < 24; $h++) {
            if ($h !== 14 && $h !== 9) {
                $this->assertSame(0, $result[$h], "Hour $h should be 0");
            }
        }
    }

    public function test_get_open_times_by_hour_of_day_covers_boundary_hours(): void
    {
        $tsMidnight = mktime(0, 0, 0, 1, 15, 2025);  // hour 0
        $ts23       = mktime(23, 59, 59, 1, 15, 2025); // hour 23

        add_post_meta($this->campaignId, 'open_time', $tsMidnight, false);
        add_post_meta($this->campaignId, 'open_time', $ts23, false);

        $result = Campaigns::getOpenTimesByHourOfDay($this->campaignId);

        $this->assertSame(1, $result[0],  'Midnight (hour 0) open should be counted');
        $this->assertSame(1, $result[23], 'Hour 23 open should be counted');
    }

    // -----------------------------------------------------------------------
    // Campaigns::getOpenTimesByDayOfWeek()
    // -----------------------------------------------------------------------

    public function test_get_open_times_by_day_of_week_returns_all_seven_days(): void
    {
        $result = Campaigns::getOpenTimesByDayOfWeek($this->campaignId);

        $expectedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $this->assertCount(7, $result);
        foreach ($expectedDays as $day) {
            $this->assertArrayHasKey($day, $result);
        }
    }

    public function test_get_open_times_by_day_of_week_all_zeros_for_new_campaign(): void
    {
        $result = Campaigns::getOpenTimesByDayOfWeek($this->campaignId);

        foreach ($result as $count) {
            $this->assertSame(0, $count);
        }
    }

    public function test_get_open_times_by_day_of_week_counts_correctly(): void
    {
        // 2025-06-02 was a Monday; 2025-06-07 was a Saturday.
        $tsMonday   = mktime(10, 0, 0, 6, 2, 2025);
        $tsMonday2  = mktime(12, 0, 0, 6, 2, 2025);
        $tsSaturday = mktime(9, 0, 0, 6, 7, 2025);

        add_post_meta($this->campaignId, 'open_time', $tsMonday, false);
        add_post_meta($this->campaignId, 'open_time', $tsMonday2, false);
        add_post_meta($this->campaignId, 'open_time', $tsSaturday, false);

        $result = Campaigns::getOpenTimesByDayOfWeek($this->campaignId);

        $this->assertSame(2, $result['Monday'],   'Two Monday opens should be counted');
        $this->assertSame(1, $result['Saturday'], 'One Saturday open should be counted');
        // All other days should remain 0
        $zeroDays = ['Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Sunday'];
        foreach ($zeroDays as $day) {
            $this->assertSame(0, $result[$day], "$day should be 0");
        }
    }

    public function test_get_open_times_by_day_of_week_preserves_order(): void
    {
        $result = Campaigns::getOpenTimesByDayOfWeek($this->campaignId);
        $keys   = array_keys($result);

        $this->assertSame('Monday',    $keys[0]);
        $this->assertSame('Tuesday',   $keys[1]);
        $this->assertSame('Wednesday', $keys[2]);
        $this->assertSame('Thursday',  $keys[3]);
        $this->assertSame('Friday',    $keys[4]);
        $this->assertSame('Saturday',  $keys[5]);
        $this->assertSame('Sunday',    $keys[6]);
    }

    // -----------------------------------------------------------------------
    // Campaigns::getStatsForCampaign() — STAT_EMAILS_OPENED
    // -----------------------------------------------------------------------

    public function test_get_stats_for_campaign_includes_emails_opened_key(): void
    {
        $campaign = Campaigns::getCampaignById($this->campaignId);
        $stats    = Campaigns::getStatsForCampaign($campaign);

        $this->assertArrayHasKey(Campaigns::STAT_EMAILS_OPENED, $stats);
    }

    public function test_get_stats_for_campaign_emails_opened_reflects_opens(): void
    {
        $sub2 = Subscribers::addSubscriber('statopen1@mawiblah.test');
        $sub3 = Subscribers::addSubscriber('statopen2@mawiblah.test');

        Campaigns::recordOpen($sub2->id, $this->campaignId);
        Campaigns::recordOpen($sub3->id, $this->campaignId);

        $campaign = Campaigns::getCampaignById($this->campaignId);
        $stats    = Campaigns::getStatsForCampaign($campaign);
        $opened   = $stats[Campaigns::STAT_EMAILS_OPENED];

        $this->assertIsArray($opened);
        $this->assertCount(1, $opened);
        $this->assertSame(2, (int) $opened[0]);

        wp_delete_post($sub2->id, true);
        wp_delete_post($sub3->id, true);
    }

    public function test_get_stats_for_campaign_emails_opened_zero_when_no_opens(): void
    {
        $campaign = Campaigns::getCampaignById($this->campaignId);
        $stats    = Campaigns::getStatsForCampaign($campaign);
        $opened   = $stats[Campaigns::STAT_EMAILS_OPENED];

        $this->assertIsArray($opened);
        $this->assertCount(1, $opened);
        // Value may be 0 (int) or '' (empty string from get_post_meta); cast to int.
        $this->assertSame(0, (int) $opened[0]);
    }

    // -----------------------------------------------------------------------
    // Campaigns::getConversionStatsForCampaign() — STAT_EMAILS_OPENED
    // -----------------------------------------------------------------------

    public function test_get_conversion_stats_for_campaign_includes_emails_opened_key(): void
    {
        $campaign = Campaigns::getCampaignById($this->campaignId);
        Campaigns::updateCounters($campaign, 100, 0, 0, 0);
        $campaign = Campaigns::getCampaignById($this->campaignId);
        $stats    = Campaigns::getConversionStatsForCampaign($campaign);

        $this->assertArrayHasKey(Campaigns::STAT_EMAILS_OPENED, $stats);
    }

    public function test_get_conversion_stats_for_campaign_emails_opened_is_percentage(): void
    {
        $campaign = Campaigns::getCampaignById($this->campaignId);
        // Send 100 emails, record 25 opens → expect 25.00 %
        Campaigns::updateCounters($campaign, 100, 0, 0, 0);

        for ($i = 0; $i < 25; $i++) {
            $sub = Subscribers::addSubscriber("convopen{$i}@mawiblah.test");
            Campaigns::recordOpen($sub->id, $this->campaignId);
            wp_delete_post($sub->id, true);
        }

        $campaign = Campaigns::getCampaignById($this->campaignId);
        $stats    = Campaigns::getConversionStatsForCampaign($campaign);
        $opened   = $stats[Campaigns::STAT_EMAILS_OPENED];

        $this->assertIsArray($opened);
        $this->assertCount(1, $opened);
        $this->assertSame(25.0, (float) $opened[0]);
    }

    public function test_get_conversion_stats_for_campaign_emails_opened_zero_sends_does_not_fatal(): void
    {
        // sentCount = 0: the code performs $emailsOpenedCount / $sentCount.
        // This is a known division-by-zero edge case; at minimum verify the call does not throw.
        $campaign = Campaigns::getCampaignById($this->campaignId);
        // Do NOT call updateCounters, so emailsSend stays empty/0.

        try {
            $stats = Campaigns::getConversionStatsForCampaign($campaign);
            // If PHP emits a warning/notice it may still return a result – that is acceptable;
            // a fatal error / exception is not.
            $this->assertArrayHasKey(Campaigns::STAT_EMAILS_OPENED, $stats);
        } catch (\Throwable $e) {
            $this->fail('getConversionStatsForCampaign threw an exception with zero sent count: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------------
    // Subscribers::renderMetaData() — Opens section
    // -----------------------------------------------------------------------

    public function test_render_meta_data_shows_opens_heading(): void
    {
        // Simulate a WP_Post-like object for the subscriber.
        $post     = get_post($this->subscriber->id);
        ob_start();
        Subscribers::renderMetaData($post);
        $output = ob_get_clean();

        $this->assertStringContainsString('Opens', $output);
    }

    public function test_render_meta_data_shows_zero_opens_when_none_recorded(): void
    {
        $post = get_post($this->subscriber->id);
        ob_start();
        Subscribers::renderMetaData($post);
        $output = ob_get_clean();

        // Total opens count should be 0 in the output.
        $this->assertMatchesRegularExpression('/Total opens.*0/s', $output);
    }

    public function test_render_meta_data_shows_open_entry_after_record_open(): void
    {
        Campaigns::recordOpen($this->subscriber->id, $this->campaignId);

        $post = get_post($this->subscriber->id);
        ob_start();
        Subscribers::renderMetaData($post);
        $output = ob_get_clean();

        // There should be exactly one campaign open entry.
        $this->assertStringContainsString('<table>', $output, 'A table should be rendered when opens exist');
        $this->assertStringContainsString((string) $this->campaignId, $output, 'Campaign ID should appear in the opens table');
    }

    public function test_render_meta_data_total_opens_counts_unique_campaigns(): void
    {
        $campaignId2 = Campaigns::addCampaign('Open Track Campaign 2', 'Subj', 'Title', 'Content', [], 'test');

        Campaigns::recordOpen($this->subscriber->id, $this->campaignId);
        Campaigns::recordOpen($this->subscriber->id, $campaignId2);

        $post = get_post($this->subscriber->id);
        ob_start();
        Subscribers::renderMetaData($post);
        $output = ob_get_clean();

        // The total opens count should reflect 2 unique campaign opens.
        $this->assertMatchesRegularExpression('/Total opens.*2/s', $output);

        Campaigns::deleteCampaign($campaignId2);
    }

    public function test_render_meta_data_does_not_show_table_when_no_opens(): void
    {
        $post = get_post($this->subscriber->id);
        ob_start();
        Subscribers::renderMetaData($post);
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<table>', $output, 'No table should be rendered when there are no opens');
    }
}