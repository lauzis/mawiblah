<?php

namespace Mawiblah;

/**
 * Background send via WP Cron.
 *
 * Registers the cron hook and processes subscriber batches server-side so
 * campaigns can be delivered without keeping a browser tab open.
 */
class CronSend
{
    const HOOK = 'mawiblah_background_send';

    /**
     * Campaign meta holding the do-not-disturb threshold a schedule asked for.
     *
     * Written by SchedulerCron just before the send starts and removed when the send
     * finishes, so it only ever applies to the run it was written for. Absent means
     * "use the global setting"; an explicit 0 means "no do-not-disturb check this run".
     */
    const DND_OVERRIDE_META = 'dnd_threshold_override';

    /**
     * Campaign meta holding the last moment a batch of this send did any work.
     *
     * A batch hands over to the next one through a single WP-Cron event, and WP-Cron
     * saves its whole queue as one option: a concurrent cron run that read the queue
     * a moment earlier writes it back without that event, and the send stops for good
     * with nothing left to wake it. resumeStalled() puts the event back -- this stamp
     * is how it tells a lost hand-off from a batch still busy sending, which has no
     * event queued either.
     */
    const LAST_ACTIVITY_META = 'backgroundLastActivity';

    /** Campaign meta set once a send has been reported as too old to resume. */
    const STALL_REPORTED_META = 'backgroundStallReported';

    /** Seconds without batch activity, and with no batch queued, before a send counts as stalled. */
    const STALL_AFTER = 600;

    /**
     * Seconds without batch activity after which a stalled send is no longer resumed.
     *
     * Past a day the letter is old news; picking it up again, say for a send that
     * died long before this check existed, is a decision for a person, not for cron.
     */
    const RESUME_WITHIN = 86400;

    /** How often, at most, a busy batch refreshes LAST_ACTIVITY_META. */
    const ACTIVITY_INTERVAL = 30;

    /** Registers the cron action hook. Call from plugin init. */
    public static function init(): void
    {
        add_action(self::HOOK, [self::class, 'processBatch'], 10, 1);
    }

    /**
     * Schedules the first cron batch for a campaign.
     *
     * @param int $campaignPostId Campaign post ID.
     */
    public static function schedule(int $campaignPostId): void
    {
        if (!wp_next_scheduled(self::HOOK, [$campaignPostId])) {
            wp_schedule_single_event(time(), self::HOOK, [$campaignPostId]);
        }
    }

    /**
     * Cancels any pending cron batch for a campaign.
     *
     * @param int $campaignPostId Campaign post ID.
     */
    public static function unschedule(int $campaignPostId): void
    {
        $timestamp = wp_next_scheduled(self::HOOK, [$campaignPostId]);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK, [$campaignPostId]);
        }
    }

    /**
     * Queues the next batch again for every background send that has lost it.
     *
     * A send is stalled when it is started and not finished, has no batch queued,
     * and no batch has done anything for STALL_AFTER seconds. Sends idle for longer
     * than RESUME_WITHIN are reported once and left alone. Subscribers already
     * handled are recorded per campaign, so the resumed batch skips them.
     *
     * Called from SchedulerCron::check(), which runs on its own WP-Cron event.
     *
     * @return int Number of sends resumed.
     */
    public static function resumeStalled(): int
    {
        $campaignIds = get_posts([
            'post_type'      => Campaigns::postType(),
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => 'backgroundStarted',
        ]);

        $resumed = 0;

        foreach ($campaignIds as $campaignPostId) {
            $campaignPostId = (int) $campaignPostId;

            // Another process -- the batch itself -- writes these; read them fresh.
            wp_cache_delete($campaignPostId, 'post_meta');

            $started = (int) get_post_meta($campaignPostId, 'backgroundStarted', true);
            if (!$started || get_post_meta($campaignPostId, 'campaignFinished', true)) {
                continue;
            }

            if (wp_next_scheduled(self::HOOK, [$campaignPostId])) {
                continue;
            }

            $lastActivity = max($started, (int) get_post_meta($campaignPostId, self::LAST_ACTIVITY_META, true));
            $idle         = time() - $lastActivity;

            if ($idle < self::STALL_AFTER) {
                continue;
            }

            $title = get_the_title($campaignPostId);

            if ($idle > self::RESUME_WITHIN) {
                if (!get_post_meta($campaignPostId, self::STALL_REPORTED_META, true)) {
                    update_post_meta($campaignPostId, self::STALL_REPORTED_META, time());
                    Logs::addError('cron-send', "Stalled send too old to resume, finish or restart it by hand: {$title}", [
                        'campaignPostId' => $campaignPostId,
                        'lastActivity'   => gmdate('Y-m-d H:i:s', $lastActivity),
                    ]);
                }
                continue;
            }

            $queued = wp_schedule_single_event(time(), self::HOOK, [$campaignPostId], true);

            if (is_wp_error($queued)) {
                Logs::addError('cron-send', "Stalled send could not be resumed: {$title}", [
                    'campaignPostId' => $campaignPostId,
                    'error'          => $queued->get_error_message(),
                ]);
                continue;
            }

            $resumed++;
            Logs::addLog('cron-send', "Stalled send resumed: {$title}", [
                'campaignPostId' => $campaignPostId,
                'lastActivity'   => gmdate('Y-m-d H:i:s', $lastActivity),
                'idleMin'        => round($idle / 60, 1),
            ]);
        }

        return $resumed;
    }

    /** Records that a batch of this send is doing work right now. */
    private static function touch(int $campaignPostId): void
    {
        update_post_meta($campaignPostId, self::LAST_ACTIVITY_META, time());
    }

    /**
     * Processes the next batch of subscribers for a background send.
     * Re-schedules itself if more subscribers remain; otherwise finishes the campaign.
     *
     * @param int $campaignPostId Campaign post ID.
     */
    public static function processBatch(int $campaignPostId): void
    {
        $campaign = Campaigns::getCampaignById($campaignPostId);

        if (!$campaign) {
            Logs::addError('cron-send', "Campaign not found, aborting batch", ['campaignPostId' => $campaignPostId]);
            return;
        }

        if (!$campaign->backgroundStarted) {
            Logs::addError('cron-send', "backgroundStarted not set, aborting batch", ['campaignPostId' => $campaignPostId]);
            return;
        }

        if ($campaign->campaignFinished) {
            // Clear the in-progress flag on the way out. This path is reached
            // when a batch arrives after the campaign has already finished --
            // the normal completion at the end of this method clears it, this
            // one used to return without doing so, and a scheduler that reads
            // the flag as "still sending" then skipped every occurrence for
            // ever after.
            Campaigns::backgroundSendStop($campaignPostId);
            Logs::addLog('cron-send', "Campaign already finished, aborting batch", ['campaignPostId' => $campaignPostId]);
            return;
        }

        $batchStarted = microtime(true);
        $before       = Campaigns::getCounters($campaign);

        // Named and counted: a Slack line or a log read weeks later has to say
        // which campaign this was and how far along it already is, not just
        // that some batch began.
        Logs::addLog('cron-send', "Batch started: {$campaign->post_title}", [
            'campaignPostId' => $campaignPostId,
            'campaign'       => $campaign->post_title,
            'sentSoFar'      => (int) ($before->emailsSend ?? 0),
            'failedSoFar'    => (int) ($before->emailsFailed ?? 0),
        ]);

        self::touch($campaignPostId);
        $lastTouch = time();

        // Register a shutdown handler so fatal errors inside the batch are always logged
        register_shutdown_function(function () use ($campaignPostId) {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                Logs::addError('cron-send', "Fatal error during batch", [
                    'campaignPostId' => $campaignPostId,
                    'error'          => $error['message'],
                    'file'           => $error['file'],
                    'line'           => $error['line'],
                ]);
            }
        });

        $template = Campaigns::lockTemplate($campaign, false);
        if ($template === false) {
            Logs::addError('cron-send', "lockTemplate returned false, aborting batch", ['campaignPostId' => $campaignPostId]);
            Campaigns::backgroundSendStop($campaignPostId);
            return;
        }

        $counters       = Campaigns::getCounters($campaign);
        $emailsSent     = (int) ($counters->emailsSend ?? 0);
        $emailsFailed   = (int) ($counters->emailsFailed ?? 0);
        $emailsSkipped  = (int) ($counters->emailsSkipped ?? 0);
        $emailsUnsubed  = (int) ($counters->emailsUnsubed ?? 0);

        $doNotDisturbThreshold = self::doNotDisturbThreshold($campaignPostId);
        $failingEmailAudience  = Subscribers::failingEmailAudience();
        $sendEmails            = Settings::sendEmails();

        $batchSize  = Settings::backgroundBatchSize();
        $audiences  = $campaign->audiences ?? [];
        $seenEmails = [];
        $batchCount = 0;
        $hasMore    = false;

        foreach ($audiences as $audienceId) {
            $subscribers = Subscribers::getSubscribersByAudience($audienceId);

            foreach ($subscribers as $subscriber) {
                $email = trim(strtolower($subscriber->email));

                if (isset($seenEmails[$email])) {
                    continue;
                }
                $seenEmails[$email] = true;

                // Already sent (the canonical skip signal)
                if (Subscribers::isEmailSent($subscriber->id, $campaignPostId, false)) {
                    continue;
                }

                // If batch is full, note that more work remains and stop iteration
                if ($batchCount >= $batchSize) {
                    $hasMore = true;
                    break 2;
                }

                // A batch of a hundred takes minutes; keep the stamp fresh so a
                // send that is busy is never taken for one that stalled.
                if (time() - $lastTouch >= self::ACTIVITY_INTERVAL) {
                    self::touch($campaignPostId);
                    $lastTouch = time();
                }

                // Unsubscribed
                if ($subscriber->unsubed) {
                    $emailsUnsubed++;
                    self::logSkip($campaignPostId, $subscriber, 'unsubscribed');
                    Subscribers::markCampaignProcessed($subscriber->id, $campaignPostId, false);
                    Campaigns::updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed);
                    $batchCount++;
                    continue;
                }

                // Failing email address
                if ($failingEmailAudience
                    && has_term($failingEmailAudience->term_id, Subscribers::postType() . '_category', $subscriber->id)) {
                    $emailsSkipped++;
                    self::logSkip($campaignPostId, $subscriber, 'in the failing-email audience');
                    Subscribers::markCampaignProcessed($subscriber->id, $campaignPostId, false);
                    Campaigns::updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed);
                    $batchCount++;
                    continue;
                }

                // Do-not-disturb threshold
                if ($doNotDisturbThreshold > 0 && $subscriber->lastInteraction) {
                    $timeDiff = time() - (int) $subscriber->lastInteraction;
                    if ($timeDiff < $doNotDisturbThreshold) {
                        $emailsSkipped++;
                        self::logSkip($campaignPostId, $subscriber, sprintf(
                            'do not disturb — last interaction %s ago, threshold %s',
                            human_time_diff((int) $subscriber->lastInteraction),
                            human_time_diff(0, $doNotDisturbThreshold)
                        ));
                        Subscribers::markCampaignProcessed($subscriber->id, $campaignPostId, false);
                        Campaigns::updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed);
                        $batchCount++;
                        continue;
                    }
                }

                // Email sending disabled in settings — mark as processed so counters advance
                if (!$sendEmails) {
                    $emailsSkipped++;
                    self::logSkip($campaignPostId, $subscriber, 'e-mail sending is switched off in settings');
                    Subscribers::markCampaignProcessed($subscriber->id, $campaignPostId, false);
                    Campaigns::updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed);
                    $batchCount++;
                    continue;
                }

                // Build personalised email
                $emailBody = Campaigns::fillTemplate($template, $campaign, $subscriber);

                if (Settings::openTrackingEnabled()) {
                    $pixelUrl  = add_query_arg([
                        'subscriber' => $subscriber->subscriberHash,
                        'campaign'   => $campaign->campaignHash,
                    ], rest_url('mawiblah/v1/open'));
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    $emailBody .= '<img src="' . esc_url($pixelUrl) . '" width="1" height="1" alt="" style="display:none;" />';
                }

                $unsubToken = Subscribers::getUnsubToken($subscriber->id, $subscriber->email);
                $unsubUrl   = add_query_arg([
                    'subscriber' => $subscriber->subscriberHash,
                    'token'      => $unsubToken,
                    'campaign'   => $campaign->campaignHash,
                ], rest_url('mawiblah/v1/unsubscribe'));

                $emailHeaders = array_merge([
                    'Content-Type: text/html; charset=UTF-8',
                    'List-Unsubscribe: <' . $unsubUrl . '>',
                    'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
                ], BounceParser::trackingHeaders((string) $campaign->campaignHash, (string) $subscriber->subscriberHash));

                $mailerError      = '';
                $captureMailError = static function (\WP_Error $error) use (&$mailerError): void {
                    $mailerError = $error->get_error_message();
                };
                add_action('wp_mail_failed', $captureMailError);

                $result = wp_mail($subscriber->email, $campaign->subject, $emailBody, $emailHeaders);

                remove_action('wp_mail_failed', $captureMailError);

                if ($result) {
                    $emailsSent++;
                    Subscribers::sentEmail($subscriber->id, $campaignPostId, false);
                } else {
                    $emailsFailed++;
                    Subscribers::sentEmailFailed($subscriber->id, $campaignPostId, $mailerError ?: 'wp_mail returned false');
                }

                Campaigns::updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed);
                $batchCount++;
            }
        }

        Logs::addLog('cron-send', "Batch finished: {$campaign->post_title}", [
            'campaignPostId' => $campaignPostId,
            'campaign'       => $campaign->post_title,
            'batchCount'     => $batchCount,
            'sent'           => $emailsSent,
            'failed'         => $emailsFailed,
            'skipped'        => $emailsSkipped,
            'unsub'          => $emailsUnsubed,
            'hasMore'        => $hasMore,
            'elapsedMs'      => (int) round((microtime(true) - $batchStarted) * 1000),
        ]);

        if ($hasMore) {
            self::touch($campaignPostId);

            $queued = wp_schedule_single_event(time() + 60, self::HOOK, [$campaignPostId], true);
            if (is_wp_error($queued)) {
                // Said out loud, though resumeStalled() will queue it again either way.
                Logs::addError('cron-send', "Could not queue the next batch: {$campaign->post_title}", [
                    'campaignPostId' => $campaignPostId,
                    'error'          => $queued->get_error_message(),
                ]);
            }
        } else {
            Campaigns::campaignFinish($campaignPostId);
            Campaigns::backgroundSendStop($campaignPostId);
            self::clearDoNotDisturbOverride($campaignPostId);
            Logs::addLog('cron-send', "Campaign finished: {$campaign->post_title}", [
                'campaignPostId' => $campaignPostId,
                'campaign'       => $campaign->post_title,
                'sent'           => $emailsSent,
                'failed'         => $emailsFailed,
                'skipped'        => $emailsSkipped,
                'unsub'          => $emailsUnsubed,
            ]);
        }
    }

    /**
     * Resolves the do-not-disturb threshold for this run: the schedule's override when one
     * was written for the campaign, otherwise the global setting.
     *
     * An override of 0 is a value, not an absence — it means this send ignores the
     * do-not-disturb rule entirely — so the meta's presence decides, not its truthiness.
     *
     * @param int $campaignPostId Campaign post ID.
     * @return int Threshold in seconds; 0 disables the check.
     */
    /**
     * Records why a subscriber was passed over.
     *
     * The batch summary counts skips but has never said what caused them, which
     * makes "it sent nothing" a guessing game between four different rules.
     *
     * @param int    $campaignPostId Campaign post ID.
     * @param object $subscriber     The subscriber being skipped.
     * @param string $reason         Why, in words.
     * @return void
     */
    private static function logSkip(int $campaignPostId, object $subscriber, string $reason): void
    {
        Logs::addLog('cron-send', "Skipped {$subscriber->email}: {$reason}", [
            'campaignPostId' => $campaignPostId,
            'subscriberId'   => $subscriber->id,
            'reason'         => $reason,
        ]);
    }

    public static function doNotDisturbThreshold(int $campaignPostId): int
    {
        if (metadata_exists('post', $campaignPostId, self::DND_OVERRIDE_META)) {
            return max(0, (int) get_post_meta($campaignPostId, self::DND_OVERRIDE_META, true));
        }

        return max(0, (int) Settings::dontDisturbThreshold());
    }

    /**
     * Removes the per-run do-not-disturb override from a campaign.
     *
     * @param int $campaignPostId Campaign post ID.
     */
    public static function clearDoNotDisturbOverride(int $campaignPostId): void
    {
        delete_post_meta($campaignPostId, self::DND_OVERRIDE_META);
    }
}
