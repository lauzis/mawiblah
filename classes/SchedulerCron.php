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
 *   - daily / weekly / monthly — next_send is advanced to the next occurrence.
 *
 * Campaigns are reset before each scheduled send so all subscribers receive
 * the email regardless of prior sends for the same campaign.
 *
 * A schedule can also carry its own do-not-disturb threshold; the resolved value is
 * written to the campaign as CronSend::DND_OVERRIDE_META for the duration of that run.
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

        // A run is closed by whoever finishes the campaign, which is the cron
        // batch rather than this class.
        add_action('mawiblah_campaign_finished', [Scheduler::class, 'finishRun']);

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
        $started    = microtime(true);
        $triggered  = 0;

        Logs::addLog('scheduler', 'Scheduled check started', [
            'now'        => gmdate('Y-m-d H:i:s', $now),
            'schedulers' => count($schedulers),
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
                if ($campaign) {
                    Scheduler::recordSkippedRun((int) $scheduler->id, $campaignPostId, __('the campaign has not been test-approved', 'mawiblah'));
                }
                continue;
            }

            // Custom send-condition shortcode: skip the send if the shortcode returns empty output.
            $conditionShortcode = $campaign->send_condition_shortcode ?? '';
            if (!empty($conditionShortcode)) {
                $output = trim(do_shortcode("[{$conditionShortcode} campaign_id='{$campaignPostId}']"));
                if ($output === '') {
                    Logs::addLog('scheduler', "Scheduler #{$scheduler->id}: send skipped — custom rule returned empty", [
                        'campaignPostId'       => $campaignPostId,
                        'send_condition_shortcode' => $conditionShortcode,
                    ]);
                    Scheduler::recordSkippedRun(
                        (int) $scheduler->id,
                        $campaignPostId,
                        sprintf(
                            /* translators: %s: shortcode name */
                            __('the send condition [%s] returned nothing', 'mawiblah'),
                            $conditionShortcode
                        )
                    );
                    if ($scheduler->schedule_type !== 'once') {
                        Scheduler::updateMeta($scheduler->id, [
                            'next_send' => Scheduler::computeNextSend(
                                $scheduler->schedule_type,
                                $scheduler->send_time,
                                $scheduler->send_day
                            ),
                        ]);
                    }
                    continue;
                }
            }

            // If the previous scheduled send is still in progress, skip this occurrence
            // to avoid resetting the campaign mid-send.
            //
            // In progress means started *and not finished*, which is what the
            // status endpoint has always reported. Reading `backgroundStarted`
            // alone made a flag that outlived its send -- and one does, if a
            // batch aborts after the campaign finished -- silently retire the
            // schedule: every occurrence after it was skipped, for ever.
            if (!empty($campaign->backgroundStarted) && empty($campaign->campaignFinished)) {
                Logs::addLog('scheduler', "Scheduler #{$scheduler->id}: previous send still in progress, skipping this occurrence", ['campaignPostId' => $campaignPostId]);
                Scheduler::recordSkippedRun((int) $scheduler->id, $campaignPostId, __('the previous send was still running', 'mawiblah'));
                if ($scheduler->schedule_type !== 'once') {
                    Scheduler::updateMeta($scheduler->id, [
                        'next_send' => Scheduler::computeNextSend(
                            $scheduler->schedule_type,
                            $scheduler->send_time,
                            $scheduler->send_day
                        ),
                    ]);
                }
                continue;
            }

            // Hand this run its do-not-disturb threshold. The override belongs to the
            // schedule, not to the campaign, so it is written per run and dropped again
            // when the send finishes -- a schedule whose override was switched off falls
            // straight back to the global setting on its next occurrence.
            if ($scheduler->override_dnd) {
                $dndThreshold = (int) $scheduler->dnd_threshold;
                $dndSource    = 'schedule';
                update_post_meta($campaignPostId, CronSend::DND_OVERRIDE_META, $dndThreshold);
            } else {
                $dndThreshold = (int) Settings::dontDisturbThreshold();
                $dndSource    = 'global';
                CronSend::clearDoNotDisturbOverride($campaignPostId);
            }

            Scheduler::startRun((int) $scheduler->id, $campaignPostId);

            // Reset campaign so every subscriber is treated as unsent
            Scheduler::resetCampaignForResend($campaignPostId, $scheduler->schedule_type);

            // Trigger a background (cron-driven) send
            Campaigns::backgroundSendStart($campaignPostId);
            CronSend::schedule($campaignPostId);

            $triggered++;

            Logs::addLog('scheduler', "Scheduled campaign started: {$campaign->post_title}", [
                'schedulerId'    => $scheduler->id,
                'campaignPostId' => $campaignPostId,
                'campaign'       => $campaign->post_title,
                'scheduleType'   => $scheduler->schedule_type,
                'dndThreshold'   => $dndThreshold,
                'dndSource'      => $dndSource,
            ]);

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

        // Logged even when nothing was due: without it, a check that died
        // halfway through looks exactly like one that found no work.
        Logs::addLog('scheduler', 'Scheduled check finished', [
            'schedulers' => count($schedulers),
            'triggered'  => $triggered,
            'elapsedMs'  => (int) round((microtime(true) - $started) * 1000),
        ]);
    }
}
