<?php

namespace Mawiblah;

class RestRoutes
{

    /**
     * Returns a processed email template with all shortcodes evaluated.
     *
     * Requires editor capabilities. Used by the campaign editor to preview template output.
     *
     * @param \WP_REST_Request $request JSON body containing 'template' (template name string).
     * @return \WP_REST_Response Template HTML content and template name.
     */
    public static function getHtmlTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        $template        = sanitize_text_field($request->get_param('template') ?? '');
        $templateContent = Templates::getEmailTemplateByName($template);
        $templateContent = do_shortcode($templateContent);

        return new \WP_REST_Response([
            'status'       => 'ok',
            'template'     => $templateContent,
            'templateName' => $template,
        ], 200);
    }

    /** Connectivity smoke-test endpoint. Returns {"status":"ok"} with HTTP 200. */
    public static function test(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(['status' => 'ok'], 200);
    }

    /**
     * Sends one campaign email to one subscriber and updates campaign counters.
     *
     * This is the callback for the JS-driven per-subscriber send loop. It delegates
     * to processSendEmail() and wraps the result in a WP_REST_Response.
     *
     * @param \WP_REST_Request $request JSON body: campaignPostId, subscriberId, email, lastItem.
     * @return \WP_REST_Response Result payload with status, message, and emailSendingStats.
     */
    public static function sendEmail(\WP_REST_Request $request): \WP_REST_Response
    {
        return rest_ensure_response(self::processSendEmail($request));
    }

    /**
     * Core send-email logic: validates inputs, applies all skip rules, sends via wp_mail(),
     * and updates campaign counters.
     *
     * Skip rules (in order): unsubscribed, already sent, do-not-disturb threshold,
     * email sending disabled in settings, not a tester in test mode, template unavailable.
     *
     * @param \WP_REST_Request $request
     * @return array Result array consumed by sendEmail().
     */
    private static function processSendEmail(\WP_REST_Request $request): array
    {
        $campaignPostId = absint($request->get_param('campaignPostId'));
        $subscriberId   = absint($request->get_param('subscriberId'));
        $email          = sanitize_email($request->get_param('email') ?? '');
        $lastItem       = $request->get_param('lastItem');

        // Initialize or retrieve current counters
        $currentCounters = Campaigns::getCounters((object)['id' => $campaignPostId]);
        $emailsSent = (int)($currentCounters->emailsSend ?? 0);
        $emailsFailed = (int)($currentCounters->emailsFailed ?? 0);
        $emailsSkipped = (int)($currentCounters->emailsSkipped ?? 0);
        $emailsUnsubed = (int)($currentCounters->emailsUnsubed ?? 0);

        if (!$campaignPostId || !$subscriberId) {
            return [
                'stats'   => Helpers::emailSendingStats(skipped: 1),
                'status'  => 'error',
                'message' => 'Campaign or subscriber missing',
            ];
        }

        $campaign = Campaigns::getCampaignById($campaignPostId);
        $subscriber = Subscribers::getSubscriberById($subscriberId);

        if (!$campaign) {
            return [
                'stats' => Helpers::emailSendingStats(skipped:1),
                'data' => [
                    'campaignPostId' => $campaignPostId,
                    'subscriberId' => $subscriberId,
                    'email' => $email
                ],
                'status' => 'error',
                'message' => 'Campaign not found'
            ];
        }

        if (!$subscriber) {
            return [
                'stats' => Helpers::emailSendingStats(skipped:1),
                'data' => [
                    'campaignPostId' => $campaignPostId,
                    'subscriberId' => $subscriberId,
                    'email' => $email
                ],
                'status' => 'error',
                'message' => 'Subscriber not found'
            ];
        }

        $testMode = false;
        //-----------------------------------------------------
        // --------------- Test mode detection ----------------
        //----------------------------------------------------
        if (!$campaign->testApproved && $campaign->testStarted) {
            $testMode = true;
        }

        if ($lastItem) {
            if ($testMode && $campaign->testStarted) {
                Campaigns::testFinish($campaignPostId);
            }
            if(!$testMode && $campaign->campaignStarted) {
                Campaigns::campaignFinish($campaignPostId);
            }
        }

        //-----------------------------------------------------
        // --------------- Unsubed user -----------------
        //----------------------------------------------------
        $unsubscribed = $subscriber->unsubed;
        if ($unsubscribed) {
            $emailsUnsubed++;
            Campaigns::updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed);
            
            return [
                'stats' => Helpers::emailSendingStats(unsubscribed:1),
                'data' => [
                    'campaignPostId' => $campaignPostId,
                    'subscriberId' => $subscriberId,
                    'email' => $email
                ],
                'status' => 'ok',
                'message' => 'Skip, subscriber is unsubscribed'
            ];
        }
        //-----------------------------------------------------
        // --------------- Failing email address --------------
        //----------------------------------------------------
        $failingEmailAudience = Subscribers::failingEmailAudience();
        $isFailingEmail = $failingEmailAudience
            && has_term($failingEmailAudience->term_id, Subscribers::postType() . '_category', $subscriberId);

        if ($isFailingEmail) {
            $emailsSkipped++;
            Campaigns::updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed);
            return [
                'stats'   => Helpers::emailSendingStats(skipped: 1),
                'data'    => [
                    'campaignPostId'  => $campaignPostId,
                    'subscriberId'    => $subscriberId,
                    'email'           => $email,
                    'emailFailCount'  => $subscriber->emailFailCount,
                ],
                'status'  => 'ok',
                'message' => 'Skip, subscriber is in Failing Email audience',
            ];
        }

        //-----------------------------------------------------
        // --------------- Already sent email -----------------
        //----------------------------------------------------
        $alreadySent = Subscribers::isEmailSent($subscriberId, $campaignPostId, $testMode);

        if ($alreadySent) {
            return [
                'stats' => Helpers::emailSendingStats(alreadySent:1),
                'data' => [
                    'campaignPostId' => $campaignPostId,
                    'subscriberId' => $subscriberId,
                    'email' => $email,
                    'testMode' => $testMode,
                    'already' => $alreadySent,
                ],
                'status' => 'ok',
                'message' => 'Skip, email already sent'
            ];
        }


        $isTester = Subscribers::isTester($subscriber);
        $sendEmails = Settings::sendEmails();

        //-----------------------------------------------------
        // --------------- Do not disturb threshold ------------
        //----------------------------------------------------
        $doNotDisturbThreshold = Settings::dontDisturbThreshold();
        $lastInteraction = $subscriber->lastInteraction;
        $currentTime = time();
        $timeDiff = $currentTime - (int) $lastInteraction;
        $subscriberDontDisturb = $timeDiff < $doNotDisturbThreshold;
        $timeLeftInSeconds = $doNotDisturbThreshold - $timeDiff;
        $days = floor($timeLeftInSeconds / (60 * 60 * 24));
        $timeLeftInSeconds = $timeLeftInSeconds - ($days * 60 * 60 * 24);
        $daysHoursSecondsLeft = $days."d ".gmdate("H:i:s", $timeLeftInSeconds);

        if ($subscriberDontDisturb){
            $emailsSkipped++;
            Campaigns::updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed);
            
            return [
                'stats' => Helpers::emailSendingStats(doNotDisturb:1),
                'data' => [
                    'campaignPostId' => $campaignPostId,
                    'subscriberId' => $subscriberId,
                    'email' => $email,
                    'testMode' => $testMode,
                    'isTester' => $isTester,
                    'subscriberDontDisturb' => $subscriberDontDisturb,
                    'campaign' => $campaign,
                    'timeDiff' => $timeDiff,
                    'doNotDisturbThreshold' => $doNotDisturbThreshold,
                    'alreadySent' => $alreadySent,
                    'lastItem' => $lastItem,
                ],
                'status' => 'ok',
                'message' => "Skip, subscriber is in do not disturb mode. Threshold between emails is not reached! Left time: {$daysHoursSecondsLeft}"
            ];
        }

        if (!$sendEmails) {

            $message = false;

            if ($testMode && $isTester) {
                $message = "Email sending is off: Would send, Tester in test mode, but email sending is off in settings";
            }

            if (!$message && !$isTester && $testMode) {
                $message = 'Email sending is off: Test mode is on. Skip subscriber is not a tester';
            }

            if (!$message) {
                $message = 'Email sending is off: Skipping sending emails, setting for email sending is off';
            }

            return [
                'status' => 'ok',
                'message' => $message,
                'stats' => Helpers::emailSendingStats(emailsDisabled:1),
                'data' => [
                    'campaignPostId' => $campaignPostId,
                    'subscriberId' => $subscriberId,
                    'email' => $email,
                    'testMode' => $testMode,
                    'isTester' => $isTester,
                    'subscriberDontDisturb' => $subscriberDontDisturb,
                    'campaign' => $campaign,
                    'timeDiff' => $timeDiff,
                    'doNotDisturbThreshold' => $doNotDisturbThreshold,
                    'alreadySent' => $alreadySent,
                    'lastItem' => $lastItem
                ]
            ];
        }

        if (!$isTester && $testMode) {
            return [
                'stats' => Helpers::emailSendingStats(notTester:1),
                'data' => [
                    'testMode' => $testMode,
                    'isTester' => $isTester,
                    'campaignPostId' => $campaignPostId,
                    'subscriberId' => $subscriberId,
                    'email' => $email,
                    'campaign' => $campaign,
                    'subscriber' => $subscriber,
                    'timeDiff' => $timeDiff,
                    'doNotDisturbThreshold' => $doNotDisturbThreshold,
                    'alreadySent' => $alreadySent,
                    'lastItem' => $lastItem
                ],
                'status' => 'ok',
                'message' => 'Test mode: Skip subscriber is not a tester'
            ];
        }

        $template = Campaigns::lockTemplate($campaign, $testMode);
        if ($template===false){

            Logs::addLog("Email sending failed. Template could not be retrieved.", "Email sending failed. Template could not be retrieved.", [
                'campaign' => $campaign,
                'subscriber' => $subscriber,
                'testMode' => $testMode,
                'isTester' => $isTester,
                'campaignPostId' => $campaignPostId,
                'subscriberId' => $subscriberId,
                'email' => $email,
                'timeDiff' => $timeDiff,
                'doNotDisturbThreshold' => $doNotDisturbThreshold,
                'alreadySent' => $alreadySent,
                'lastItem' => $lastItem
            ]);

            return [
                'stats' => Helpers::emailSendingStats(failed:1),
                'data' => [
                    'testMode' => $testMode,
                    'isTester' => $isTester,
                    'campaignPostId' => $campaignPostId,
                    'subscriberId' => $subscriberId,
                    'email' => $email,
                    'campaign' => $campaign,
                    'subscriber' => $subscriber,
                    'timeDiff' => $timeDiff,
                    'doNotDisturbThreshold' => $doNotDisturbThreshold,
                    'alreadySent' => $alreadySent,
                    'lastItem' => $lastItem
                ],
                'status' => 'error',
                'message' => "Problem with recieving template! Template is not locked."
            ];
        }
        $emailBody = Campaigns::fillTemplate($template, $campaign, $subscriber);

        if (!$testMode && Settings::openTrackingEnabled()) {
            $pixelUrl = add_query_arg([
                'subscriber' => $subscriber->subscriberHash,
                'campaign'   => $campaign->campaignHash,
            ], rest_url('mawiblah/v1/open'));
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

        $mailerError = '';
        $enableExceptions = function (\PHPMailer\PHPMailer\PHPMailer $phpmailer) {
            $phpmailer->SMTPDebug  = 0;
            $phpmailer->Debugoutput = 'error_log';
            $phpmailer->exceptions = true;
        };
        add_action('phpmailer_init', $enableExceptions);

        try {
            $emailSendingResult = wp_mail($email, $campaign->subject, $emailBody, $emailHeaders);
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $emailSendingResult = false;
            $mailerError        = $e->getMessage();
        } finally {
            remove_action('phpmailer_init', $enableExceptions);
        }

        if ($emailSendingResult) {

            Subscribers::sentEmail($subscriber->id, $campaign->id, $testMode);
            $emailsSent++;
            Campaigns::updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed);

            Logs::addLog("Email sent to {$email} successfully!", "Email sent to {$email} successfully!", [
                'campaign' => $campaign,
                'subscriber' => $subscriber,
                'testMode' => $testMode,
                'emailSendingResult' => $emailSendingResult,
                'isTester' => $isTester,
                'campaignPostId' => $campaignPostId,
                'subscriberId' => $subscriberId,
                'email' => $email,
                'timeDiff' => $timeDiff,
                'doNotDisturbThreshold' => $doNotDisturbThreshold,
                'alreadySent' => $alreadySent,
                'lastItem' => $lastItem
            ]);

            return [
                'stats' =>Helpers::emailSendingStats(sent: 1),
                'data' => [
                    'testMode' => $testMode,
                    'isTester' => $isTester,
                    'campaignPostId' => $campaignPostId,
                    'subscriberId' => $subscriberId,
                    'email' => $email,
                    'campaign' => $campaign,
                    'subscriber' => $subscriber,
                    'timeDiff' => $timeDiff,
                    'doNotDisturbThreshold' => $doNotDisturbThreshold,
                    'alreadySent' => $alreadySent,
                    'lastItem' => $lastItem
                ],
                'status' => 'ok',
                'message' => "Email sent to {$email} successfully!"
            ];
        }

        Subscribers::sentEmailFailed($subscriber->id, $campaign->id, $mailerError);
        $emailsFailed++;
        Campaigns::updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed);

        $failMessage = $mailerError
            ? "Email sending to {$email} failed: {$mailerError}"
            : "Email sending to {$email} failed!";

        Logs::addLog($failMessage, $failMessage, [
            'campaign' => $campaign,
            'subscriber' => $subscriber,
            'testMode' => $testMode,
            'emailSendingResult' => $emailSendingResult,
            'mailerError' => $mailerError,
            'isTester' => $isTester,
            'campaignPostId' => $campaignPostId,
            'subscriberId' => $subscriberId,
            'email' => $email,
            'timeDiff' => $timeDiff,
            'doNotDisturbThreshold' => $doNotDisturbThreshold,
            'alreadySent' => $alreadySent,
            'lastItem' => $lastItem
        ]);

        return [
            'stats' => Helpers::emailSendingStats(failed: 1),
            'data' => [
                'testMode' => $testMode,
                'isTester' => $isTester,
                'campaignPostId' => $campaignPostId,
                'subscriberId' => $subscriberId,
                'email' => $email,
                'campaign' => $campaign,
                'subscriber' => $subscriber,
                'timeDiff' => $timeDiff,
                'doNotDisturbThreshold' => $doNotDisturbThreshold,
                'alreadySent' => $alreadySent,
                'lastItem' => $lastItem,
                'mailerError' => $mailerError,
            ],
            'status' => 'error',
            'message' => $failMessage,
        ];
    }

    /**
     * Returns live send counters for a background send in progress.
     *
     * @param \WP_REST_Request $request REST request with 'campaignPostId'.
     * @return array{sent:int, failed:int, skipped:int, unsubed:int, total:int, running:bool}
     */
    public static function backgroundProgress(\WP_REST_Request $request): array
    {
        $campaignPostId = (int) $request->get_param('campaignPostId');
        $campaign       = Campaigns::getCampaignById($campaignPostId);

        if (!$campaign) {
            return ['error' => 'Not found'];
        }

        $counters = Campaigns::getCounters($campaign);
        $sent     = (int) ($counters->emailsSend ?? 0);
        $failed   = (int) ($counters->emailsFailed ?? 0);
        $skipped  = (int) ($counters->emailsSkipped ?? 0);
        $unsubed  = (int) ($counters->emailsUnsubed ?? 0);

        return [
            'sent'              => $sent,
            'failed'            => $failed,
            'skipped'           => $skipped,
            'unsubed'           => $unsubed,
            'total'             => $sent + $failed + $skipped + $unsubed,
            'total_subscribers' => (int) ($campaign->totalSubscribers ?? 0),
            'running'           => !empty($campaign->backgroundStarted) && empty($campaign->campaignFinished),
        ];
    }

    /**
     * Tracking pixel endpoint — records a unique email open and returns a 1×1 transparent GIF.
     * Publicly accessible; no authentication required (the pixel is embedded in emails).
     *
     * @param \WP_REST_Request $request REST request with 'subscriber' and 'campaign' query params.
     */
    public static function trackOpen(\WP_REST_Request $request): void
    {
        $subscriberHash = sanitize_text_field($request->get_param('subscriber') ?? '');
        $campaignHash   = sanitize_text_field($request->get_param('campaign') ?? '');

        if (Settings::openTrackingEnabled() && $subscriberHash && $campaignHash) {
            $subscriber = Subscribers::getSubscriberBySubscriberHash($subscriberHash);
            $campaign   = Campaigns::getCampaignByHash($campaignHash);

            if ($subscriber && $campaign) {
                Campaigns::recordOpen($subscriber->id, $campaign->id);
            }
        }

        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        header('Content-Type: image/gif');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $gif;
        exit;
    }
}
