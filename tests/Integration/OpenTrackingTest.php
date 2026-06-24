<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Campaigns;
use Mawiblah\Subscribers;
use WP_UnitTestCase;

/**
 * Integration tests for the email open-tracking feature introduced in PR #25.
 *
 * Covers:
 *  - Campaigns::STAT_EMAILS_OPENED constant
 *  - Campaigns::recordOpen()
 *  - Campaigns::getOpenTimesByHourOfDay()
 *  - Campaigns::getOpenTimesByDayOfWeek()
 *  - Campaigns::getStatsForCampaign() – emailsOpened key
 *  - Campaigns::getConversionStatsForCampaign() – emailsOpened key + percentage
 */
class OpenTrackingTest extends WP_UnitTestCase
{
    private int    $campaignId;
    private object $sub1;
    private object $sub2;

    public function setUp(): void
    {
        parent::setUp();
        $this->campaignId = Campaigns::addCampaign('Open Tracking Test', 'Subject', 'Title', 'Content', [], 'test-template');
        $this->sub1       = Subscribers::addSubscriber('opentrack1@mawiblah.test');
        $this->sub2       = Subscribers::addSubscriber('opentrack2@mawiblah.test');
    }

    public function tearDown(): void
    {
        Campaigns::deleteCampaign($this->campaignId);
        wp_delete_post($this->sub1->id, true);
        wp_delete_post($this->sub2->id, true);
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // STAT_EMAILS_OPENED constant
    // -------------------------------------------------------------------------

    public function test_stat_emails_opened_constant_value(): void
    {
        $this->assertSame('emailsOpened', Campaigns::STAT_EMAILS_OPENED);
    }

    // -------------------------------------------------------------------------
    // recordOpen()
    // -------------------------------------------------------------------------

    public function test_record_open_increments_emails_opened_counter(): void
    {
        $before = (int) get_post_meta($this->campaignId, 'emailsOpened', true);
        $this->assertSame(0, $before);

        Campaigns::recordOpen($this->sub1->id, $this->campaignId);

        $after = (int) get_post_meta($this->campaignId, 'emailsOpened', true);
        $this->assertSame(1, $after);
    }

    public function test_record_open_is_idempotent_for_same_subscriber(): void
    {
        Campaigns::recordOpen($this->sub1->id, $this->campaignId);
        Campaigns::recordOpen($this->sub1->id, $this->campaignId);

        $count = (int) get_post_meta($this->campaignId, 'emailsOpened', true);
        $this->assertSame(1, $count);
    }

    public function test_record_open_counts_different_subscribers_separately(): void
    {
        Campaigns::recordOpen($this->sub1->id, $this->campaignId);
        Campaigns::recordOpen($this->sub2->id, $this->campaignId);

        $count = (int) get_post_meta($this->campaignId, 'emailsOpened', true);
        $this->assertSame(2, $count);
    }

    public function test_record_open_stores_timestamp_on_subscriber(): void
    {
        $before = time();
        Campaigns::recordOpen($this->sub1->id, $this->campaignId);
        $after = time();

        $ts = (int) get_post_meta($this->sub1->id, 'opened_' . $this->campaignId, true);
        $this->assertGreaterThanOrEqual($before, $ts);
        $this->assertLessThanOrEqual($after, $ts);
    }

    public function test_record_open_stores_open_time_meta_on_campaign(): void
    {
        $before = time();
        Campaigns::recordOpen($this->sub1->id, $this->campaignId);
        $after = time();

        $openTimes = get_post_meta($this->campaignId, 'open_time', false);
        $this->assertCount(1, $openTimes);
        $ts = (int) $openTimes[0];
        $this->assertGreaterThanOrEqual($before, $ts);
        $this->assertLessThanOrEqual($after, $ts);
    }

    public function test_record_open_adds_one_open_time_entry_per_unique_subscriber(): void
    {
        Campaigns::recordOpen($this->sub1->id, $this->campaignId);
        Campaigns::recordOpen($this->sub2->id, $this->campaignId);

        $openTimes = get_post_meta($this->campaignId, 'open_time', false);
        $this->assertCount(2, $openTimes);
    }

    public function test_record_open_duplicate_does_not_add_open_time_entry(): void
    {
        Campaigns::recordOpen($this->sub1->id, $this->campaignId);
        Campaigns::recordOpen($this->sub1->id, $this->campaignId); // duplicate

        $openTimes = get_post_meta($this->campaignId, 'open_time', false);
        $this->assertCount(1, $openTimes);
    }

    public function test_record_open_open_time_entries_are_independent_per_campaign(): void
    {
        $campaign2Id = Campaigns::addCampaign('Open Tracking Test 2', 'Subject2', 'Title2', 'Content2', [], 'test-template');

        Campaigns::recordOpen($this->sub1->id, $this->campaignId);
        Campaigns::recordOpen($this->sub1->id, $campaign2Id);

        $openTimes1 = get_post_meta($this->campaignId, 'open_time', false);
        $openTimes2 = get_post_meta($campaign2Id, 'open_time', false);

        $this->assertCount(1, $openTimes1);
        $this->assertCount(1, $openTimes2);

        Campaigns::deleteCampaign($campaign2Id);
    }

    // -------------------------------------------------------------------------
    // getOpenTimesByHourOfDay()
    // -------------------------------------------------------------------------

    public function test_get_open_times_by_hour_returns_24_hour_keys(): void
    {
        $result = Campaigns::getOpenTimesByHourOfDay($this->campaignId);

        $this->assertCount(24, $result);
        for ($h = 0; $h < 24; $h++) {
            $this->assertArrayHasKey($h, $result);
        }
    }

    public function test_get_open_times_by_hour_all_zeros_when_no_opens(): void
    {
        $result = Campaigns::getOpenTimesByHourOfDay($this->campaignId);

        foreach ($result as $count) {
            $this->assertSame(0, $count);
        }
    }

    public function test_get_open_times_by_hour_buckets_timestamp_correctly(): void
    {
        // Build a timestamp that falls in a predictable hour using mktime.
        // mktime(hour, minute, second, month, day, year)
        $targetHour = 14; // 2 PM
        $ts = mktime($targetHour, 30, 0, 6, 15, 2024); // Saturday 2024-06-15 14:30:00
        add_post_meta($this->campaignId, 'open_time', $ts, false);

        $result = Campaigns::getOpenTimesByHourOfDay($this->campaignId);

        $this->assertSame(1, $result[$targetHour]);
        // All other hours should be zero.
        for ($h = 0; $h < 24; $h++) {
            if ($h !== $targetHour) {
                $this->assertSame(0, $result[$h], "Hour $h should be 0");
            }
        }
    }

    public function test_get_open_times_by_hour_accumulates_multiple_opens_in_same_hour(): void
    {
        $ts1 = mktime(9, 0, 0, 1, 10, 2024);
        $ts2 = mktime(9, 45, 0, 1, 10, 2024);
        add_post_meta($this->campaignId, 'open_time', $ts1, false);
        add_post_meta($this->campaignId, 'open_time', $ts2, false);

        $result = Campaigns::getOpenTimesByHourOfDay($this->campaignId);

        $this->assertSame(2, $result[9]);
    }

    public function test_get_open_times_by_hour_keys_are_integers(): void
    {
        $result = Campaigns::getOpenTimesByHourOfDay($this->campaignId);

        foreach (array_keys($result) as $key) {
            $this->assertIsInt($key);
        }
    }

    // -------------------------------------------------------------------------
    // getOpenTimesByDayOfWeek()
    // -------------------------------------------------------------------------

    public function test_get_open_times_by_day_returns_seven_named_keys(): void
    {
        $result = Campaigns::getOpenTimesByDayOfWeek($this->campaignId);

        $this->assertCount(7, $result);
        $expectedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach ($expectedDays as $day) {
            $this->assertArrayHasKey($day, $result);
        }
    }

    public function test_get_open_times_by_day_all_zeros_when_no_opens(): void
    {
        $result = Campaigns::getOpenTimesByDayOfWeek($this->campaignId);

        foreach ($result as $count) {
            $this->assertSame(0, $count);
        }
    }

    public function test_get_open_times_by_day_buckets_timestamp_correctly(): void
    {
        // 2024-06-17 is a Monday.
        $ts = mktime(10, 0, 0, 6, 17, 2024); // Monday
        add_post_meta($this->campaignId, 'open_time', $ts, false);

        $result = Campaigns::getOpenTimesByDayOfWeek($this->campaignId);

        $this->assertSame(1, $result['Monday']);
        $otherDays = ['Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach ($otherDays as $day) {
            $this->assertSame(0, $result[$day], "$day should be 0");
        }
    }

    public function test_get_open_times_by_day_accumulates_multiple_opens_on_same_day(): void
    {
        // 2024-06-19 is a Wednesday.
        $ts1 = mktime(8, 0, 0, 6, 19, 2024);
        $ts2 = mktime(20, 0, 0, 6, 19, 2024);
        add_post_meta($this->campaignId, 'open_time', $ts1, false);
        add_post_meta($this->campaignId, 'open_time', $ts2, false);

        $result = Campaigns::getOpenTimesByDayOfWeek($this->campaignId);

        $this->assertSame(2, $result['Wednesday']);
    }

    public function test_get_open_times_by_day_counts_each_weekday_independently(): void
    {
        // Plant one timestamp per each weekday (Mon–Sun of 2024-06-17 to 2024-06-23).
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach ($days as $i => $day) {
            $ts = mktime(12, 0, 0, 6, 17 + $i, 2024);
            add_post_meta($this->campaignId, 'open_time', $ts, false);
        }

        $result = Campaigns::getOpenTimesByDayOfWeek($this->campaignId);

        foreach ($days as $day) {
            $this->assertSame(1, $result[$day], "$day should have 1 open");
        }
    }

    // -------------------------------------------------------------------------
    // getStatsForCampaign() – emailsOpened included in return value
    // -------------------------------------------------------------------------

    public function test_get_stats_for_campaign_includes_emails_opened_key(): void
    {
        $campaign = Campaigns::getCampaignById($this->campaignId);
        $stats    = Campaigns::getStatsForCampaign($campaign);

        $this->assertArrayHasKey(Campaigns::STAT_EMAILS_OPENED, $stats);
    }

    public function test_get_stats_for_campaign_emails_opened_is_zero_initially(): void
    {
        $campaign = Campaigns::getCampaignById($this->campaignId);
        $stats    = Campaigns::getStatsForCampaign($campaign);

        $this->assertSame([0], $stats[Campaigns::STAT_EMAILS_OPENED]);
    }

    public function test_get_stats_for_campaign_emails_opened_reflects_recorded_opens(): void
    {
        Campaigns::recordOpen($this->sub1->id, $this->campaignId);
        Campaigns::recordOpen($this->sub2->id, $this->campaignId);

        $campaign = Campaigns::getCampaignById($this->campaignId);
        $stats    = Campaigns::getStatsForCampaign($campaign);

        $this->assertSame([2], $stats[Campaigns::STAT_EMAILS_OPENED]);
    }

    // -------------------------------------------------------------------------
    // getConversionStatsForCampaign() – emailsOpened as percentage
    // -------------------------------------------------------------------------

    public function test_get_conversion_stats_for_campaign_includes_emails_opened_key(): void
    {
        // Set a non-zero sent count so the percentage calculation does not divide by zero.
        $campaign = Campaigns::getCampaignById($this->campaignId);
        Campaigns::updateCounters($campaign, 10, 0, 0, 0);

        $campaign = Campaigns::getCampaignById($this->campaignId);
        $stats    = Campaigns::getConversionStatsForCampaign($campaign);

        $this->assertArrayHasKey(Campaigns::STAT_EMAILS_OPENED, $stats);
    }

    public function test_get_conversion_stats_emails_opened_percentage_is_correct(): void
    {
        $campaign = Campaigns::getCampaignById($this->campaignId);
        Campaigns::updateCounters($campaign, 100, 0, 0, 0); // 100 sent

        // Record 25 opens (via direct meta for precision, bypassing subscriber uniqueness).
        update_post_meta($this->campaignId, 'emailsOpened', 25);

        $campaign = Campaigns::getCampaignById($this->campaignId);
        $stats    = Campaigns::getConversionStatsForCampaign($campaign);

        // Expected: round(25/100*100, 2) = 25.0
        $this->assertSame([25.0], $stats[Campaigns::STAT_EMAILS_OPENED]);
    }

    public function test_get_conversion_stats_emails_opened_percentage_rounds_to_two_decimals(): void
    {
        $campaign = Campaigns::getCampaignById($this->campaignId);
        Campaigns::updateCounters($campaign, 3, 0, 0, 0); // 3 sent

        update_post_meta($this->campaignId, 'emailsOpened', 1); // 1 of 3 = 33.333…

        $campaign = Campaigns::getCampaignById($this->campaignId);
        $stats    = Campaigns::getConversionStatsForCampaign($campaign);

        // round(1/3*100, 2) = 33.33
        $this->assertSame([33.33], $stats[Campaigns::STAT_EMAILS_OPENED]);
    }

    public function test_get_conversion_stats_emails_opened_zero_opens_yields_zero_percent(): void
    {
        $campaign = Campaigns::getCampaignById($this->campaignId);
        Campaigns::updateCounters($campaign, 50, 0, 0, 0);

        $campaign = Campaigns::getCampaignById($this->campaignId);
        $stats    = Campaigns::getConversionStatsForCampaign($campaign);

        // 0 opened / 50 sent = 0.0%
        $this->assertSame([0.0], $stats[Campaigns::STAT_EMAILS_OPENED]);
    }

    /**
     * Regression / boundary: when emailsSent is zero the open-rate calculation
     * performs integer division by zero.  PHP 8 raises a DivisionByZeroError
     * in that case.  This test documents that behaviour so a future zero-guard
     * fix is verifiable.
     */
    public function test_get_conversion_stats_emails_opened_division_by_zero_when_sent_is_zero(): void
    {
        // Ensure emailsSend stays at 0 (default) and emailsOpened is also 0.
        $campaign = Campaigns::getCampaignById($this->campaignId);

        $this->expectException(\DivisionByZeroError::class);
        Campaigns::getConversionStatsForCampaign($campaign);
    }
}