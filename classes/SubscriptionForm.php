<?php

namespace Mawiblah;

class SubscriptionForm
{
    /** Template name a theme can override this letter with, in mawiblah/email_templates/. */
    const RESUBSCRIBE_TEMPLATE = 'resubscribe-confirm';

    const RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    const RECAPTCHA_THRESHOLD  = 0.5;

    /** Intercepts the re-subscribe confirmation URL on WordPress init and processes the token. */
    public static function init(): void
    {
        if (isset($_GET['mawiblah-resubscribe'])) {
            self::handleResubscribeConfirmation();
        }
    }

    /**
     * Renders the subscription form HTML, conditionally loading reCAPTCHA v3 if fully configured.
     *
     * @param string[] $audienceHashes Audience hashes to embed as hidden inputs.
     * @param array    $options        Optional overrides: label, placeholder, buttonText, successMessage, errorMessage.
     * @return string Rendered form HTML.
     */
    public static function renderForm(array $audienceHashes = [], array $options = []): string
    {
        $siteKey        = Settings::recaptchaSiteKey();
        $recaptcha      = Settings::recaptchaReady();
        $recaptchaVersion = $recaptcha ? Settings::recaptchaVersion() : 'disabled';
        $label          = $options['label']          ?? __('Email', 'mawiblah');
        $placeholder    = $options['placeholder']    ?? __('your@email.com', 'mawiblah');
        $buttonText     = $options['buttonText']     ?? __('Subscribe', 'mawiblah');
        $successMessage = $options['successMessage'] ?? '';
        $errorMessage   = $options['errorMessage']   ?? '';
        ob_start();
        include MAWIBLAH_TEMPLATE_DIR . '/subscription-form/form.php';
        return ob_get_clean();
    }

    /**
     * REST endpoint callback for POST /wp-json/mawiblah/v1/subscribe.
     *
     * Applies honeypot and reCAPTCHA checks before delegating to subscribeByEmail().
     * Returns HTTP 400 on validation or spam-check failure, HTTP 200 otherwise.
     *
     * @param \WP_REST_Request $request JSON body: email, audienceHashes, honeypot, recaptchaToken.
     * @return \WP_REST_Response
     */
    public static function subscribe(\WP_REST_Request $request): \WP_REST_Response
    {
        $email          = sanitize_email($request->get_param('email') ?? '');
        $audienceHashes = array_map('sanitize_text_field', (array) ($request->get_param('audienceHashes') ?? []));
        $honeypot       = $request->get_param('honeypot') ?? '';
        $recaptchaToken = sanitize_text_field($request->get_param('recaptchaToken') ?? '');

        // Honeypot — silently succeed so bots think it worked
        if (!empty($honeypot)) {
            return new \WP_REST_Response(['status' => 'ok', 'message' => __('You are now subscribed!', 'mawiblah')], 200);
        }

        // reCAPTCHA v3
        if (Settings::recaptchaReady()) {
            if (!self::verifyRecaptcha($recaptchaToken)) {
                return new \WP_REST_Response(['status' => 'error', 'message' => __('Verification failed. Please try again.', 'mawiblah')], 400);
            }
        }

        $result = self::subscribeByEmail($email, $audienceHashes);
        $status = $result['status'] === 'error' ? 400 : 200;
        return new \WP_REST_Response($result, $status);
    }

    /**
     * Subscribe an email address to one or more audiences.
     *
     * Safe to call from any PHP code (Gravity Forms callbacks, WP-CLI, etc.).
     * Returns ['status' => 'ok'|'error', 'message' => string].
     *
     * Fires the `mawiblah_subscribed` action on success:
     *   do_action('mawiblah_subscribed', string $email, array $audienceHashes, object $subscriber)
     */
    public static function subscribeByEmail(string $email, array $audienceHashes = []): array
    {
        if (!is_email($email)) {
            return ['status' => 'error', 'message' => __('Invalid email address.', 'mawiblah')];
        }

        // Resolve audienceHashes → term IDs
        $audienceIds = [];
        foreach ($audienceHashes as $hash) {
            $audience = Subscribers::getAudienceByHash($hash);
            if ($audience) {
                $audienceIds[] = (int) $audience->term_id;
            }
        }

        $subscriber = Subscribers::getSubscriber($email);

        // Unsubscribed — send re-subscribe confirmation email
        if ($subscriber && $subscriber->unsubed) {
            self::sendResubscribeEmail($subscriber, $audienceIds);
            return ['status' => 'ok', 'message' => __('Check your inbox to confirm your subscription.', 'mawiblah')];
        }

        // New subscriber
        if (!$subscriber) {
            $subscriber = Subscribers::addSubscriber($email);
        }

        // Add to audiences (only those not already assigned)
        $existingTerms = wp_get_post_terms($subscriber->id, Subscribers::postType() . '_category', ['fields' => 'ids']);
        foreach ($audienceIds as $audienceId) {
            if (!in_array($audienceId, $existingTerms)) {
                Subscribers::addSubscriberToAudience($subscriber->id, $audienceId);
            }
        }

        do_action('mawiblah_subscribed', $email, $audienceHashes, $subscriber);

        return ['status' => 'ok', 'message' => __('You are now subscribed!', 'mawiblah')];
    }

    /**
     * Sends a re-subscribe confirmation email containing a token-bearing URL.
     *
     * @param object $subscriber  Subscriber object with email, subscriberHash, and id.
     * @param int[]  $audienceIds Audience term IDs to restore on confirmation.
     */
    private static function sendResubscribeEmail(object $subscriber, array $audienceIds): void
    {
        $token        = Subscribers::getUnsubToken($subscriber->id, $subscriber->email);
        $audienceParam = implode(',', array_map('strval', $audienceIds));
        $confirmUrl   = add_query_arg([
            'mawiblah-resubscribe' => '1',
            'subscriber'           => $subscriber->subscriberHash,
            'resubToken'           => $token,
            'audiences'            => $audienceParam,
        ], get_site_url());

        $subject = sprintf(__('Confirm your subscription to %s', 'mawiblah'), get_bloginfo('name'));
        $body    = sprintf(
            __("Hi,\n\nClick the link below to confirm your re-subscription:\n\n%s\n\nIf you did not request this, please ignore this email.", 'mawiblah'),
            $confirmUrl
        );

        /*
         * A theme can dress this letter the way it dresses its own, by putting
         * `resubscribe-confirm.html` in its `mawiblah/email_templates/` folder
         * -- the same place, and the same search order, campaign templates come
         * from. Shortcodes in it are evaluated, then these placeholders are
         * filled:
         *
         *   {{confirm_url}}       the link the letter exists to carry
         *   {{site_name}}         blog name
         *   {{subscriber_email}}  who is being asked
         *
         * With no such file the plain-text letter below is sent, which is what
         * a plugin should do on a site that has expressed no opinion.
         */
        $template = Templates::getEmailTemplateByName(self::RESUBSCRIBE_TEMPLATE);

        if (is_string($template) && trim($template) !== '') {
            $html = strtr(do_shortcode($template), [
                '{{confirm_url}}'      => esc_url($confirmUrl),
                '{{site_name}}'        => esc_html(get_bloginfo('name')),
                '{{subscriber_email}}' => esc_html($subscriber->email),
            ]);

            wp_mail($subscriber->email, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);

            return;
        }

        wp_mail($subscriber->email, $subject, $body);
    }

    /** Reads resubscribe URL parameters, calls confirmResubscribe(), and renders the result template. Exits. */
    private static function handleResubscribeConfirmation(): void
    {
        $subscriberHash = sanitize_text_field($_GET['subscriber'] ?? '');
        $resubToken     = sanitize_text_field($_GET['resubToken'] ?? '');
        $audienceParam  = sanitize_text_field($_GET['audiences'] ?? '');

        $result = self::confirmResubscribe($subscriberHash, $resubToken, $audienceParam);

        if ($result) {
            include MAWIBLAH_TEMPLATE_DIR . '/subscription-form/resubscribe-confirm.php';
        } else {
            include MAWIBLAH_TEMPLATE_DIR . '/subscription-form/resubscribe-invalid.php';
        }
        exit;
    }

    /**
     * Validates a re-subscribe token and, if valid, clears the unsubed flag and restores audience memberships.
     *
     * @param string $subscriberHash Subscriber identifier hash from the confirmation URL.
     * @param string $resubToken     Token to validate against the stored unsubToken.
     * @param string $audienceParam  Comma-separated audience term IDs to restore.
     * @return bool True if resubscription succeeded, false if subscriber not found or token invalid.
     */
    public static function confirmResubscribe(string $subscriberHash, string $resubToken, string $audienceParam): bool
    {
        $subscriber = Subscribers::getSubscriberBySubscriberHash($subscriberHash);

        if (!$subscriber) {
            return false;
        }

        $expectedToken = Subscribers::getUnsubToken($subscriber->id, $subscriber->email);

        if ($resubToken !== $expectedToken) {
            return false;
        }

        // Clear unsubed flag
        update_post_meta($subscriber->id, 'unsubed', false);
        delete_post_meta($subscriber->id, 'unsub_time');

        // Remove from unsubbed audience
        $unsubedAudience = Subscribers::unsubedAudience();
        if ($unsubedAudience) {
            $terms = wp_get_post_terms($subscriber->id, Subscribers::postType() . '_category', ['fields' => 'ids']);
            $terms = array_diff($terms, [$unsubedAudience->term_id]);
            wp_set_post_terms($subscriber->id, array_values($terms), Subscribers::postType() . '_category');
        }

        // Add to requested audiences
        if (!empty($audienceParam)) {
            $audienceIds = array_filter(array_map('intval', explode(',', $audienceParam)));
            foreach ($audienceIds as $audienceId) {
                Subscribers::addSubscriberToAudience($subscriber->id, $audienceId);
            }
        }

        return true;
    }

    /**
     * Verifies a reCAPTCHA token against the Google siteverify API.
     *
     * Returns false immediately for empty tokens. A v2 answer only has to be
     * valid; a v3 one also has to score at least RECAPTCHA_THRESHOLD (0.5).
     *
     * @param string $token reCAPTCHA v3 token from the browser.
     * @return bool True if verification passed, false otherwise.
     */
    private static function verifyRecaptcha(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $version = Settings::recaptchaVersion();

        $response = wp_remote_post(self::RECAPTCHA_VERIFY_URL, [
            'body' => [
                'secret'   => Settings::recaptchaSecretKey(),
                'response' => $token,
            ],
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['success'])) {
            return false;
        }

        // v2 is answered or it is not. v3 always "succeeds" and puts the answer
        // in the score -- a reply without one means the keys are a v2 pair on a
        // v3 form, not a visitor to trust.
        if ($version === 'v2') {
            return true;
        }

        return isset($data['score']) && $data['score'] >= self::RECAPTCHA_THRESHOLD;
    }
}
