<?php

namespace Mawiblah;
class Unsubscribe
{

    public static function init()
    {
        if (isset($_GET['subscriberId']) && isset($_GET['unsubscribe'])) {
            $campaignId = isset($_GET['campaignId']) ? sanitize_text_field($_GET['campaignId']) : null;
            if (!isset($_GET['unsubToken'])) {
                self::unsubscribe($_GET['unsubscribe'], $_GET['subscriberId'], $campaignId);
            } else {
                self::unsubscribeAprooved($_GET['subscriberId'],$_GET['unsubscribe'], $_GET['unsubToken'], $campaignId);
            }
        }
    }

    public static function unsubscribe(string $email, string $subscriberId, ?string $campaignId = null): array
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

            $formUrl = Helpers::getCurrentUrlPath() . self::unsubscribeConfirmLink($subscriberId, $email, $unsubToken);

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

    public static function unsubscribeLink($subscriberId, $email)
    {
        return Helpers::trackingParams([
            'subscriberId' => $subscriberId,
            'unsubscribe' => $email
        ]);
    }

    public static function unsubscribeConfirmLink($subscriberId, $email, $unsubToken)
    {
        return Helpers::trackingParams([
            'subscriberId' => $subscriberId,
            'unsubscribe' => $email,
            'unsubToken' => $unsubToken
        ]);
    }

    public static function unsubscribeAprooved($subscriberId, $email, $unsubToken, ?string $campaignId = null)
    {
        $debug = [
            'subscriberId' => $subscriberId,
            'email' => $email,
            'unsubToken' => $unsubToken,
            'campaignId' => $campaignId
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
                    $audience = Subscribers::unsubedAudience();
                    Subscribers::addSubscriberToAudience($subscriber->id, $audience->term_id);
                    if (!empty($feedback)) {
                        add_post_meta($subscriber->id, 'unsubed_feedback', $feedback, false);
                    }
                    
                    // Increment newly unsubscribed counter for the campaign
                    if ($campaignId) {
                        $campaign = Campaigns::getCampaignByCampaignId($campaignId);
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
