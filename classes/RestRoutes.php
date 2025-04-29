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
        $sleepBeforeJob = Settings::getOption('mawiblah-time-between-emails');
        if ($sleepBeforeJob > 0) {
            sleep(Settings::getOption('mawiblah-time-between-emails'));
        }

        $post = $request->get_json_params();

        $campaignId = $post['campaignId'];
        $subscriberId = $post['subscriberId'];
        $email = $post['email'];
        $lastItem = $post['lastItem'] ?? false;

        if (is_nan($campaignId) || is_nan($subscriberId)) {
            return [
                'stats' => [
                    'emailsSend' => 0,
                    'emailsFailed' => 0,
                    'emailsSkipped' => 1,
                    'emailsUnsubscribed' => 0,
                ],
                'status' => 'error',
                'message' => 'Campaign or subscriber missing'
            ];
        }

        $campaign = Campaigns::getCampaignById($campaignId);
        $subscriber = Subscribers::getSubscriberById($subscriberId);

        if (!$campaign) {
            return [
                'stats' => [
                    'emailsSend' => 0,
                    'emailsFailed' => 0,
                    'emailsSkipped' => 1,
                    'emailsUnsubscribed' => 0,
                ],
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
                'stats' => [
                    'emailsSend' => 0,
                    'emailsFailed' => 0,
                    'emailsSkipped' => 1,
                    'emailsUnsubscribed' => 0,
                ],
                'data' => [
                    'campaignId' => $campaignId,
                    'subscriberId' => $subscriberId,
                    'email' => $email
                ],
                'status' => 'error',
                'message' => 'Subscriber not found'
            ];
        }

        //-----------------------------------------------------
        // --------------- Test mode detection ----------------
        //----------------------------------------------------
        if (!$campaign->testApproved && $campaign->testStarted) {
            $testMode = true;
        }

        //-----------------------------------------------------
        // --------------- Unsubed user -----------------
        //----------------------------------------------------
        $unsubscribed = $subscriber->unsubed;
        if ($unsubscribed) {
            return [
                'stats' => [
                    'emailsSend' => 0,
                    'emailsFailed' => 0,
                    'emailsSkipped' => 1,
                    'emailsUnsubscribed' => 1,
                ],
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
        $doNotDisturbThreshold = Settings::dontDisturbThreshold();
        $lastInteraction = $subscriber->lastInteraction;
        $currentTime = time();
        $timeDiff = $currentTime - $lastInteraction;
        $subscriberDontDisturb = $timeDiff < $doNotDisturbThreshold;

        if (!$sendEmails) {

            $message = false;

            if ($testMode && $isTester) {
                $message = "Email sending is off: Would send, Tester in test mode, but email sending is off in settings";
            }

            if (!$message && $subscriberDontDisturb) {
                $message = "Email sending is off: Would not send, Skipping sending email, subscriber is in do not disturb mode. Threshold is not reached!";
            }

            if (!$message && $unsubscribed) {
                $message = "Email sending is off: Would not send, Skipping sending email, subscriber is unsubscribed";
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

        if ($lastItem) {
            Campaigns::testFinish($campaignId);;
        }

        if (!$isTester && $testMode) {
            return [
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

        return [
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
            'message' => 'We should not be here!',
            'status' => 'ok'
        ];
    }
}
