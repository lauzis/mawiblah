<?php

namespace Mawiblah;

class RestRoutes
{

    public static function getHtmlTemplate(\WP_REST_Request $request)
    {
        $post = $request->get_json_params();

        $template = $post['template'];
        $templateContent = Templates::getEmailTemplateByName($template);
        $templateContent = do_shortcode($templateContent);

        return [
            'status' => 'ok',
            'template' => $templateContent,
            'templateName' => $template
        ];
    }

    public static function test(\WP_REST_Request $request)
    {
        return [
            'status' => 'ok'
        ];
    }

    public static function sendEmail(\WP_REST_Request $request)
    {
        $post = $request->get_json_params();

        $campaignId = $post['campaignId'];
        $subscriberId = $post['subscriberId'];
        $email = $post['email'];
        $lastItem = $post['lastItem'] ?? false;

        if (!is_numeric($campaignId) || !is_numeric($subscriberId)) {
            return [
                'stats' => Helpers::emailSendingStats(skipped:1),
                'status' => 'error',
                'message' => 'Campaign or subscriber missing'
            ];
        }

        $campaign = Campaigns::getCampaignById($campaignId);
        $subscriber = Subscribers::getSubscriberById($subscriberId);

        if (!$campaign) {
            return [
                'stats' => Helpers::emailSendingStats(skipped:1),
                'data' => [
                    'campaignId' => $campaignId,
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
                    'campaignId' => $campaignId,
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
                Campaigns::testFinish($campaignId);
            }
            if(!$testMode && $campaign->campaignStarted) {
                Campaigns::campaignFinish($campaignId);
            }
        }

        //-----------------------------------------------------
        // --------------- Unsubed user -----------------
        //----------------------------------------------------
        $unsubscribed = $subscriber->unsubed;
        if ($unsubscribed) {
            return [
                'stats' => Helpers::emailSendingStats(unsubscribed:1),
                'data' => [
                    'campaignId' => $campaignId,
                    'subscriberId' => $subscriberId,
                    'email' => $email
                ],
                'status' => 'ok',
                'message' => 'Skip, subscriber is unsubscribed'
            ];
        }
        //-----------------------------------------------------
        // --------------- Already sent email -----------------
        //----------------------------------------------------
        $alreadySent = Subscribers::isEmailSent($subscriberId, $campaignId);

        if ($alreadySent) {
            return [
                'stats' => Helpers::emailSendingStats(alreadySent:1),
                'data' => [
                    'campaignId' => $campaignId,
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
        $timeDiff = $currentTime - $lastInteraction;
        $subscriberDontDisturb = $timeDiff < $doNotDisturbThreshold;
        $timeLeftInSeconds = $doNotDisturbThreshold - $timeDiff;
        $days = floor($timeLeftInSeconds / (60 * 60 * 24));
        $timeLeftInSeconds = $timeLeftInSeconds - ($days * 60 * 60 * 24);
        $daysHoursSecondsLeft = $days."d ".gmdate("H:i:s", $timeLeftInSeconds);

        if ($subscriberDontDisturb){
            return [
                'stats' => Helpers::emailSendingStats(doNotDisturb:1),
                'data' => [
                    'campaignId' => $campaignId,
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
                    'campaignId' => $campaignId,
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
                    'campaignId' => $campaignId,
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
        $emailBody = Campaigns::fillTemplate($template, $campaign, $subscriber);

        $email = 'lauzis@inbox.lv';
        $emailSendingResult = wp_mail($email, $campaign->subject, $emailBody);

        if ($emailSendingResult) {

            if(!$testMode) {
                Subscribers::sentEmail($subscriber->id, $campaign->id);
            }

            Logs::addLog("Email sent to {$email} successfully!", "Email sent to {$email} successfully!", [
                'campaign' => $campaign,
                'subscriber' => $subscriber,
                'testMode' => $testMode,
                'emailSendingResult' => $emailSendingResult,
                'isTester' => $isTester,
                'campaignId' => $campaignId,
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
                    'campaignId' => $campaignId,
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


        if (!$testMode) {
            Subscribers::sentEmailFailed($subscriber->id, $campaign->id);
        }

        Logs::addLog("Email sending to {$email} failed!", "Email sending to {$email} failed!", [
            'campaign' => $campaign,
            'subscriber' => $subscriber,
            'testMode' => $testMode,
            'emailSendingResult' => $emailSendingResult,
            'isTester' => $isTester,
            'campaignId' => $campaignId,
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
                'campaignId' => $campaignId,
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
            'message' => "Email sending to {$email} failed!"
        ];
    }
}
