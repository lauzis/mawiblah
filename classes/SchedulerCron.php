<?php

namespace Mawiblah;

/**
 * WP Cron handler for the Campaign Scheduler.
 *
 * Registers an hourly recurring event (mawiblah_scheduler_check) that iterates
 * all active Scheduler records and fires a background campaign send for any
 * whose next_send time has passed.
 *
 * After firing:
 *   - once    — status is set to 'completed'.
 *   - weekly / monthly — next_send is advanced to the next occurrence.
 *
 * Campaigns are reset before each scheduled send so all subscribers receive
 * the email regardless of prior sends for the same campaign.
 */
class SchedulerCron
{
    const HOOK = 'mawiblah_scheduler_check';

    /** Registers the cron action and schedules the recurring event if not already scheduled. */
    public static function init(): void
    {
        add_action(self::HOOK, [self::class, 'check']);

        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time(), 'hourly', self::HOOK);
        }
    }

    /**
     * Checks all active schedulers and triggers campaign sends for overdue ones.
     * Called by WP Cron on the mawiblah_scheduler_check hook.
     */
    public static function check(): void
    {
        $schedulers = Scheduler::getAll();
        $now        = time();

        foreach ($schedulers as $scheduler) {
            if ($scheduler->status !== 'active') {
                continue;
            }

            if ($scheduler->next_send <= 0 || $scheduler->next_send > $now) {
                continue;
            }

            // Honour end_date for recurring schedules
            if (!empty($scheduler->end_date)) {
                try {
                    $tz      = wp_timezone();
                    $endDate = new \DateTimeImmutable($scheduler->end_date . ' 23:59:59', $tz);
                    if ($now > $endDate->getTimestamp()) {
                        Scheduler::updateMeta($scheduler->id, ['status' => 'completed']);
                        Logs::addLog('scheduler', "Scheduler #{$scheduler->id} expired, marked completed");
                        continue;
                    }
                } catch (\Throwable $e) {
                    // Malformed end_date — skip the check and proceed
                }
            }

            $campaignPostId = $scheduler->campaign_id;
            if (!$campaignPostId) {
                continue;
            }

            $campaign = Campaigns::getCampaignById($campaignPostId);
            if (!$campaign || !$campaign->testApproved) {
                Logs::addLog('scheduler', "Scheduler #{$scheduler->id}: campaign #{$campaignPostId} not approved, skipping");
                continue;
            }

            // Reset campaign so every subscriber is treated as unsent
            Scheduler::resetCampaignForResend($campaignPostId);

            // Trigger a background (cron-driven) send
            Campaigns::backgroundSendStart($campaignPostId);
            CronSend::schedule($campaignPostId);

            Logs::addLog('scheduler', "Scheduler #{$scheduler->id} triggered campaign #{$campaignPostId}");

            if ($scheduler->schedule_type === 'once') {
                Scheduler::updateMeta($scheduler->id, [
                    'status'    => 'completed',
                    'last_sent' => $now,
                ]);
            } else {
                $nextSend = Scheduler::computeNextSend(
                    $scheduler->schedule_type,
                    $scheduler->send_time,
                    $scheduler->send_day
                );
                Scheduler::updateMeta($scheduler->id, [
                    'next_send' => $nextSend,
                    'last_sent' => $now,
                ]);
            }
        }
    }
}
