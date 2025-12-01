<?php

namespace Mawiblah;
class Unsubscribe
{

    public static function init()
    {
        if (isset($_GET['subscriberId']) && isset($_GET['unsubscribe'])) {
            $campaignHash = isset($_GET['campaign']) ? sanitize_text_field($_GET['campaign']) : null;
            if (!isset($_GET['unsubToken'])) {
                self::unsubscribe($_GET['unsubscribe'], $_GET['subscriberId'], $campaignHash);
            } else {
                self::unsubscribeAprooved($_GET['subscriberId'],$_GET['unsubscribe'], $_GET['unsubToken'], $campaignHash);
            }
        }
    }

    public static function unsubscribe(string $email, string $subscriberId, ?string $campaignHash = null): array
    {
        $subscriber = Subscribers::getSubscriber($email);

        $gravityFormsEmails = GravityForms::findEmail($email);

        if ($subscriber || count($gravityFormsEmails) > 0) {
            // we havte to mark email as unsubscribed
            if ($subscriber) {
                $subId = $subscriber->ID;
            } else {
                //There is no email in subscribers list but there is in gravity forms
                // add and mark as unsubscribed
                $subId = Subscribers::addSubscriber($email);
            }
            $unsubToken = Subscribers::getUnsubToken($subId, $email);

            $formUrl = Helpers::getCurrentUrlPath() . self::unsubscribeConfirmLink($subscriberId, $email, $unsubToken, $campaignHash);

            include(MAWIBLAH_TEMPLATE_DIR . '/unsubscribe/are-you-sure.php');
            die();

        } else {
            /// show template
            ///
            ///
            include(MAWIBLAH_TEMPLATE_DIR . '/unsubscribe/not-found.php');

            die();
        }
    }

    public static function unsubscribeLink(string $subscriberId, string $email)
    {
        return Helpers::trackingParams([
            'subscriberId' => $subscriberId,
            'unsubscribe' => $email
        ]);
    }

    public static function unsubscribeConfirmLink(string $subscriberId, string $email, string $unsubToken, ?string $campaignHash = null)
    {
        $params = [
            'subscriberId' => $subscriberId,
            'unsubscribe' => $email,
            'unsubToken' => $unsubToken
        ];

        if ($campaignHash) {
            $params['campaign'] = $campaignHash;
        }

        return Helpers::trackingParams($params);
    }

    public static function unsubscribeAprooved(string $subscriberId, string $email, string $unsubToken, ?string $campaignHash = null)
    {
        $debug = [
            'subscriberId' => $subscriberId,
            'email' => $email,
            'unsubToken' => $unsubToken,
            'campaign' => $campaignHash
        ];
        $subscriber = Subscribers::getSubscriber($email);
        $debug['subscriber'] = $subscriber;

        $feedback = isset($_POST['feedback']) ? $_POST['feedback'] : '';
        // Sanitize the post value
        $feedback = sanitize_text_field($feedback);
        $debug['feedback'] = $feedback;

        if ($subscriber) {
            if ($subscriber->unsubed) {
                include(MAWIBLAH_TEMPLATE_DIR . '/unsubscribe/already-unsubed.php');
            } else {
                if ($subscriber->unsubToken === $unsubToken) {
                    update_post_meta($subscriber->id, 'unsubed', true);
                    update_post_meta($subscriber->id, 'unsub_time', time());
                    $audience = Subscribers::unsubedAudience();
                    if ($audience && isset($audience->term_id)) {
                        Subscribers::addSubscriberToAudience($subscriber->id, $audience->term_id);
                    }
                    if (!empty($feedback)) {
                        add_post_meta($subscriber->id, 'unsubed_feedback', $feedback, false);
                    }
                    
                    // Increment newly unsubscribed counter for the campaign
                    if ($campaignHash) {
                        $campaign = Campaigns::getCampaignByHash($campaignHash);
                        if ($campaign) {
                            Campaigns::incrementNewlyUnsubed($campaign->id);
                        }
                    }
                    
                    include(MAWIBLAH_TEMPLATE_DIR . '/unsubscribe/unsubed.php');
                    exit;
                } else {
                    include(MAWIBLAH_TEMPLATE_DIR . '/unsubscribe/not-found.php');
                    exit;
                }
                include(MAWIBLAH_TEMPLATE_DIR . '/unsubscribe/already-unsubed.php');
                exit;
            }

        } else {
            include(MAWIBLAH_TEMPLATE_DIR . '/unsubscribe/not-found.php');
        }
        exit;
    }
}
