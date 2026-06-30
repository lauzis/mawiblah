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
            Logs::addLog('cron-send', "Campaign already finished, aborting batch", ['campaignPostId' => $campaignPostId]);
            return;
        }

        Logs::addLog('cron-send', "Batch started", ['campaignPostId' => $campaignPostId]);

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

        $doNotDisturbThreshold = (int) Settings::dontDisturbThreshold();
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

                // Unsubscribed
                if ($subscriber->unsubed) {
                    $emailsUnsubed++;
                    Subscribers::sentEmail($subscriber->id, $campaignPostId, false);
                    Campaigns::updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed);
                    $batchCount++;
                    continue;
                }

                // Failing email address
                if ($failingEmailAudience
                    && has_term($failingEmailAudience->term_id, Subscribers::postType() . '_category', $subscriber->id)) {
                    $emailsSkipped++;
                    Subscribers::sentEmail($subscriber->id, $campaignPostId, false);
                    Campaigns::updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed);
                    $batchCount++;
                    continue;
                }

                // Do-not-disturb threshold
                if ($doNotDisturbThreshold > 0 && $subscriber->lastInteraction) {
                    $timeDiff = time() - (int) $subscriber->lastInteraction;
                    if ($timeDiff < $doNotDisturbThreshold) {
                        $emailsSkipped++;
                        Subscribers::sentEmail($subscriber->id, $campaignPostId, false);
                        Campaigns::updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed);
                        $batchCount++;
                        continue;
                    }
                }

                // Email sending disabled in settings — mark as processed so counters advance
                if (!$sendEmails) {
                    $emailsSkipped++;
                    Subscribers::sentEmail($subscriber->id, $campaignPostId, false);
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

                $emailHeaders = [
                    'Content-Type: text/html; charset=UTF-8',
                    'List-Unsubscribe: <' . $unsubUrl . '>',
                    'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
                ];

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

        Logs::addLog('cron-send', "Batch complete", [
            'campaignPostId' => $campaignPostId,
            'batchCount'     => $batchCount,
            'sent'           => $emailsSent,
            'failed'         => $emailsFailed,
            'skipped'        => $emailsSkipped,
            'unsub'          => $emailsUnsubed,
            'hasMore'        => $hasMore,
        ]);

        if ($hasMore) {
            wp_schedule_single_event(time() + 60, self::HOOK, [$campaignPostId]);
        } else {
            Campaigns::campaignFinish($campaignPostId);
            Campaigns::backgroundSendStop($campaignPostId);
            Logs::addLog('cron-send', "Campaign finished", ['campaignPostId' => $campaignPostId]);
        }
    }
}
