<?php

namespace Mawiblah;

const KEYWORD_FOUND_PHRASE = 2;
const KEYWORD_FOUND_EXACT_MATCH = 1;

class Helpers
{

    /** Returns true if the current user has editor or administrator capabilities. */
    public static function canEdit()
    {
        return current_user_can('editor') || current_user_can('administrator');
    }

    /**
     * Returns all available email templates as a name-keyed array.
     *
     * @return array Map of template filename (without extension) to display label.
     */
    public static function getArrayOfEmailTemplates(): array
    {
        return Templates::getArrayOfEmailTemplates();
    }


    /**
     * Creates and persists a new campaign post with the given name, audience, and template.
     *
     * @param string $name          Campaign title.
     * @param array  $audience      Array of audience term IDs.
     * @param string $emailTemplate Template filename.
     * @return int|false New post ID on success, false if validation fails.
     */
    public static function saveCampaign(string $name, array $audience, string $emailTemplate)
    {
        if (!Templates::validateEmailTemplate($emailTemplate)) {
            return false;
        }

        if (!self::validateAudiences($audience)) {
            return false;
        }

        $post_data = [
            'post_title' => $name,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'campaigns',
        ];

        // Insert the post into the database
        $post_id = wp_insert_post($post_data);

        if (!is_wp_error($post_id)) {
            // Save custom fields
            update_post_meta($post_id, 'audience', $audience);
            update_post_meta($post_id, 'email_template', $emailTemplate);
        }

        return $post_id;
    }

    /**
     * Builds a URL query string with standard UTM and subscriber tracking parameters.
     *
     * Merges utm_source, utm_medium, utm_campaign, and subscriber hash with any
     * additional params provided by the caller.
     *
     * @param array $additionalParams Extra key-value pairs to merge into the query string.
     * @return string Query string starting with '?'.
     */
    public static function trackingParams(array $additionalParams = [])
    {
        $params = array_merge(
            [
                'utm_source' => 'email',
                'utm_medium' => 'email',
                'utm_campaign' => 'monthly-email',
                'subscriber' => '{subscriberHash}',
            ],
            $additionalParams
        );
        $queryString = http_build_query($params);

        return '?' . $queryString;
    }


    /** Returns the current request URL path without the query string. */
    public static function getCurrentUrlPath()
    {

        $uriWithoutQuery = strtok(self::getCurrentUrl(), '?'); // Remove the query string

        return $uriWithoutQuery;
    }


    /** Returns the full current request URL including scheme, host, and query string. */
    public static function getCurrentUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? ''));
        $uri = sanitize_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));

        return $protocol . $host . $uri;
    }

    /**
     * Generates an admin action URL appended to the current URL, with a WP nonce included.
     *
     * @param array|null $params       Query parameters; must include 'action'.
     * @param string     $nonceIdField Optional param key whose value is appended to the nonce action for per-item nonces.
     * @return string Full URL with nonce query parameter.
     */
    public static function generatePluginUrl(array|null $params, string $nonceIdField=""): string
    {
        if (!$params){
            $params = [];
        }
        $nonceChangingPart ="";
        if ($nonceIdField && isset($params[$nonceIdField])) {
            $nonceChangingPart = "_".$params[$nonceIdField];
        }

        $params['_wpnonce'] = wp_create_nonce($params['action'] ? $params['action'].$nonceChangingPart : 'mawiblah_action');

        return self::getCurrentUrl() . '&' . http_build_query($params);
    }

    /**
     * Generates a nonce-protected URL that resets the test state for a campaign.
     *
     * @param int $campaignPostId Campaign post ID.
     * @return string Admin action URL.
     */
    public static function campaignTestResetUrl(int $campaignPostId): string
    {
        return self::generatePluginUrl(['action' => 'campaign-test-reset', 'campaignPostId' => $campaignPostId], 'campaignPostId');
    }

    /**
     * Generates a nonce-protected URL that approves the test phase for a campaign.
     *
     * @param int $campaignPostId Campaign post ID.
     * @return string Admin action URL.
     */
    public static function campaignTestApproveUrl(int $campaignPostId): string
    {
        return self::generatePluginUrl(['action' => 'campaign-test-approve', 'campaignPostId' => $campaignPostId], 'campaignPostId');
    }

    /**
     * Builds a normalised email-sending stats array for a single send attempt.
     *
     * Any non-zero skip reason (alreadySent, doNotDisturb, unsubscribed, emailsDisabled, notTester)
     * automatically sets emailsSkipped to 1 so callers don't need to track this manually.
     *
     * @param int $sent           1 if email was sent successfully.
     * @param int $skipped        1 if skipped for an unspecified reason.
     * @param int $failed         1 if wp_mail() returned false.
     * @param int $unsubscribed   1 if subscriber was already unsubscribed.
     * @param int $alreadySent    1 if email was previously sent to this subscriber for this campaign.
     * @param int $doNotDisturb   1 if the do-not-disturb threshold was not reached.
     * @param int $emailsDisabled 1 if email sending is disabled in settings.
     * @param int $notTester      1 if subscriber is not a tester in test mode.
     * @return array Stats array with emailsSent, emailsFailed, emailsSkipped, and reason flags.
     */
    public static function emailSendingStats(int $sent=0,int $skipped=0,int $failed=0,int $unsubscribed=0, int $alreadySent=-0, int $doNotDisturb=0, int $emailsDisabled=0, int $notTester=0 ): array{
        if ($alreadySent || $doNotDisturb || $unsubscribed || $emailsDisabled || $notTester) {
            $skipped = 1;
        }

        return [
            'emailsSent' => $sent,
            'emailsFailed' => $failed,
            'emailsSkipped' => $skipped,
            'emailsUnsubscribed' => $unsubscribed,
            'alreadySent' => $alreadySent,
            'doNotDisturb' => $doNotDisturb,
            'emailsDisabled' => $emailsDisabled,
            'notTesterInTestMode' => $notTester,
        ];
    }

    /**
     * Generates a stable subscriber hash from its post ID.
     *
     * @param int $id Subscriber post ID.
     * @return string MD5 hash used as the public subscriber identifier.
     */
    public static function generateSubscriberHash(int $id): string{
        return md5($id);
    }

    /**
     * Generates a campaign hash from its post ID and current timestamp.
     *
     * Includes time so that re-running a campaign produces a fresh hash for tracking purposes.
     *
     * @param int $id Campaign post ID.
     * @return string MD5 hash used as the public campaign identifier.
     */
    public static function generateCampaignHash(int $id): string{
        return md5($id . time());
    }
}
