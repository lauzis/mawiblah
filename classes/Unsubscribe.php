<?php

namespace Mawiblah;
class Unsubscribe
{

    /** Intercepts unsubscribe URLs on WordPress init and routes to the appropriate handler. */
    public static function init()
    {
        if (isset($_GET['subscriber']) && isset($_GET['unsubscribe'])) {
            $subscriberHash = sanitize_text_field($_GET['subscriber']);
            $email = sanitize_email($_GET['unsubscribe']);
            $campaignHash = isset($_GET['campaign']) ? sanitize_text_field($_GET['campaign']) : null;

            if (!isset($_GET['unsubToken'])) {
                self::unsubscribe($email, $subscriberHash, $campaignHash);
            } else {
                $unsubToken = sanitize_text_field($_GET['unsubToken']);
                self::unsubscribeAprooved($subscriberHash, $email, $unsubToken, $campaignHash);
            }
        }
    }

    /**
     * Initiates the unsubscribe flow for a subscriber: generates a token and shows the confirmation page.
     *
     * If the email is not in the subscribers list but exists in Gravity Forms, a subscriber record
     * is created first. Exits after rendering the template.
     *
     * @param string      $email          Subscriber email address.
     * @param string      $subscriberHash Subscriber hash from the URL.
     * @param string|null $campaignHash   Campaign hash for counter attribution (optional).
     * @return array Debug data (returned before exit for testing purposes).
     */
    public static function unsubscribe(string $email, string $subscriberHash, ?string $campaignHash = null): array
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

            $formUrl = Helpers::getCurrentUrlPath() . self::unsubscribeConfirmLink($subscriberHash, $email, $unsubToken, $campaignHash);

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

    /**
     * REST endpoint for List-Unsubscribe header.
     * GET  → redirect to the human-readable confirmation page.
     * POST → RFC 8058 one-click: immediately unsubscribe, no UI.
     */
    public static function oneClickEndpoint(\WP_REST_Request $request): \WP_REST_Response
    {
        $subscriberHash = sanitize_text_field($request->get_param('subscriber') ?? '');
        $unsubToken     = sanitize_text_field($request->get_param('token') ?? '');
        $campaignHash   = sanitize_text_field($request->get_param('campaign') ?? '');

        $subscriber = Subscribers::getSubscriberBySubscriberHash($subscriberHash);

        if (!$subscriber) {
            return new \WP_REST_Response(['status' => 'not_found'], 404);
        }

        $method = $request->get_method();

        if ($method === 'GET') {
            $redirectUrl = add_query_arg([
                'subscriber'  => $subscriberHash,
                'unsubscribe' => $subscriber->email,
                'unsubToken'  => $unsubToken,
                'campaign'    => $campaignHash ?: null,
            ], get_site_url());
            wp_redirect(esc_url_raw($redirectUrl));
            exit;
        }

        if ($method !== 'POST') {
            return new \WP_REST_Response(['status' => 'method_not_allowed'], 405);
        }

        // POST — one-click unsubscribe (RFC 8058)
        if ($subscriber->unsubToken !== $unsubToken) {
            return new \WP_REST_Response(['status' => 'invalid_token'], 403);
        }

        if (!$subscriber->unsubed) {
            update_post_meta($subscriber->id, 'unsubed', true);
            update_post_meta($subscriber->id, 'unsub_time', time());

            $audience = Subscribers::unsubedAudience();
            if ($audience) {
                Subscribers::addSubscriberToAudience($subscriber->id, $audience->term_id);
            }

            if ($campaignHash) {
                $campaign = Campaigns::getCampaignByHash($campaignHash);
                if ($campaign) {
                    Campaigns::incrementNewlyUnsubed($campaign->id);
                }
            }
        }

        return new \WP_REST_Response(['status' => 'ok'], 200);
    }

    /**
     * Builds the initial unsubscribe query string (without a token) for embedding in email bodies.
     *
     * @param string $subscriberHash Subscriber identifier hash.
     * @param string $email          Subscriber email address.
     * @return string Query string starting with '?'.
     */
    public static function unsubscribeLink(string $subscriberHash, string $email)
    {
        return Helpers::trackingParams([
            'subscriber' => $subscriberHash,
            'unsubscribe' => $email
        ]);
    }

    /**
     * Builds the token-bearing confirmation query string shown on the "Are you sure?" page.
     *
     * @param string      $subscriberHash Subscriber identifier hash.
     * @param string      $email          Subscriber email address.
     * @param string      $unsubToken     One-time unsubscribe verification token.
     * @param string|null $campaignHash   Campaign hash for counter attribution (optional).
     * @return string Query string starting with '?'.
     */
    public static function unsubscribeConfirmLink(string $subscriberHash, string $email, string $unsubToken, ?string $campaignHash = null)
    {
        $params = [
            'subscriber' => $subscriberHash,
            'unsubscribe' => $email,
            'unsubToken' => $unsubToken
        ];

        if ($campaignHash) {
            $params['campaign'] = $campaignHash;
        }

        return Helpers::trackingParams($params);
    }

    /**
     * Completes the unsubscribe flow after the subscriber confirms via the token URL.
     *
     * Validates the token, marks the subscriber as unsubed, adds them to the Unsubed audience,
     * stores optional feedback, and increments the campaign's newly-unsubscribed counter.
     * Exits after rendering the result template.
     *
     * @param string      $subscriberHash Subscriber identifier hash.
     * @param string      $email          Subscriber email address.
     * @param string      $unsubToken     Token from the confirmation URL.
     * @param string|null $campaignHash   Campaign hash for counter attribution (optional).
     */
    public static function unsubscribeAprooved(string $subscriberHash, string $email, string $unsubToken, ?string $campaignHash = null)
    {
        $debug = [
            'subscriberHash' => $subscriberHash,
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
