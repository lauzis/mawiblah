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

    /** Maps interval seconds to a WP Cron recurrence name. */
    public static function intervalName(int $seconds): string
    {
        return 'mawiblah_scheduler_' . $seconds;
    }

    /** Registers the cron action, custom intervals, and schedules (or reschedules) the recurring event. */
    public static function init(): void
    {
        // Register all supported custom intervals
        add_filter('cron_schedules', function (array $schedules): array {
            $intervals = [60, 300, 600, 900, 1800, 3600, 7200, 14400, 28800, 43200, 86400];
            foreach ($intervals as $s) {
                $name = self::intervalName($s);
                $schedules[$name] = [
                    'interval' => $s,
                    'display'  => "Mawiblah every {$s}s",
                ];
            }
            return $schedules;
        });

        add_action(self::HOOK, [self::class, 'check']);

        $wantedInterval  = Settings::schedulerInterval();
        $wantedName      = self::intervalName($wantedInterval);
        $existingEvent   = wp_get_scheduled_event(self::HOOK);

        if ($existingEvent) {
            // Reschedule only when the interval has changed
            if ($existingEvent->schedule !== $wantedName) {
                wp_unschedule_event($existingEvent->timestamp, self::HOOK);
                wp_schedule_event(time(), $wantedName, self::HOOK);
                Logs::addLog('scheduler', "Rescheduled cron from {$existingEvent->schedule} to {$wantedName}");
            }
        } else {
            wp_schedule_event(time(), $wantedName, self::HOOK);
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

        Logs::addLog('scheduler', 'check() fired', [
            'now'        => gmdate('Y-m-d H:i:s', $now),
            'count'      => count($schedulers),
        ]);

        foreach ($schedulers as $scheduler) {
            if ($scheduler->status !== 'active') {
                Logs::addLog('scheduler', "Scheduler #{$scheduler->id}: skipped — status is '{$scheduler->status}'");
                continue;
            }

            if ($scheduler->next_send <= 0 || $scheduler->next_send > $now) {
                Logs::addLog('scheduler', "Scheduler #{$scheduler->id}: not due yet", [
                    'next_send' => gmdate('Y-m-d H:i:s', $scheduler->next_send),
                    'now'       => gmdate('Y-m-d H:i:s', $now),
                    'diff_min'  => round(($scheduler->next_send - $now) / 60, 1),
                ]);
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

            // If the previous scheduled send is still in progress, skip this occurrence
            // to avoid resetting the campaign mid-send.
            if (!empty($campaign->backgroundStarted)) {
                Logs::addLog('scheduler', "Scheduler #{$scheduler->id}: previous send still in progress, skipping this occurrence", ['campaignPostId' => $campaignPostId]);
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
