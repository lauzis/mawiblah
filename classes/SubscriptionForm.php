<?php

namespace Mawiblah;

class SubscriptionForm
{
    const RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    const RECAPTCHA_THRESHOLD  = 0.5;

    public static function init(): void
    {
        if (isset($_GET['mawiblah-resubscribe'])) {
            self::handleResubscribeConfirmation();
        }
    }

    public static function renderForm(array $audienceHashes = []): string
    {
        $siteKey    = Settings::recaptchaSiteKey();
        $recaptcha  = Settings::recaptchaEnabled() && $siteKey;
        ob_start();
        include MAWIBLAH_TEMPLATE_DIR . '/subscription-form/form.php';
        return ob_get_clean();
    }

    public static function subscribe(\WP_REST_Request $request): array
    {
        $body           = $request->get_json_params();
        $email          = sanitize_email($body['email'] ?? '');
        $audienceHashes = array_map('sanitize_text_field', (array) ($body['audienceHashes'] ?? []));
        $honeypot       = $body['honeypot'] ?? '';
        $recaptchaToken = sanitize_text_field($body['recaptchaToken'] ?? '');

        // Honeypot — silently succeed so bots think it worked
        if (!empty($honeypot)) {
            return ['status' => 'ok', 'message' => __('You are now subscribed!', 'mawiblah')];
        }

        // reCAPTCHA v3
        if (Settings::recaptchaEnabled()) {
            if (!self::verifyRecaptcha($recaptchaToken)) {
                return ['status' => 'error', 'message' => __('Verification failed. Please try again.', 'mawiblah')];
            }
        }

        // Email validation
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
        foreach ($audienceIds as $audienceId) {
            $terms = wp_get_post_terms($subscriber->id, Subscribers::postType() . '_category', ['fields' => 'ids']);
            if (!in_array($audienceId, $terms)) {
                Subscribers::addSubscriberToAudience($subscriber->id, $audienceId);
            }
        }

        return ['status' => 'ok', 'message' => __('You are now subscribed!', 'mawiblah')];
    }

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

        wp_mail($subscriber->email, $subject, $body);
    }

    private static function handleResubscribeConfirmation(): void
    {
        $subscriberHash = sanitize_text_field($_GET['subscriber'] ?? '');
        $resubToken     = sanitize_text_field($_GET['resubToken'] ?? '');
        $audienceParam  = sanitize_text_field($_GET['audiences'] ?? '');

        $subscriber = Subscribers::getSubscriberBySubscriberHash($subscriberHash);

        if (!$subscriber) {
            include MAWIBLAH_TEMPLATE_DIR . '/subscription-form/resubscribe-invalid.php';
            exit;
        }

        $expectedToken = Subscribers::getUnsubToken($subscriber->id, $subscriber->email);

        if ($resubToken !== $expectedToken) {
            include MAWIBLAH_TEMPLATE_DIR . '/subscription-form/resubscribe-invalid.php';
            exit;
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

        include MAWIBLAH_TEMPLATE_DIR . '/subscription-form/resubscribe-confirm.php';
        exit;
    }

    private static function verifyRecaptcha(string $token): bool
    {
        if (empty($token)) {
            return false;
        }

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

        return isset($data['success'], $data['score'])
            && $data['success'] === true
            && $data['score'] >= self::RECAPTCHA_THRESHOLD;
    }
}
