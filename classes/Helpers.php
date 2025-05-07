<?php

namespace Mawiblah;

const KEYWORD_FOUND_PHRASE = 2;
const KEYWORD_FOUND_EXACT_MATCH = 1;

class Helpers
{

    public static function canEdit()
    {
        return current_user_can('editor') || current_user_can('administrator');
    }

    public static function getArrayOfEmailTemplates(): array
    {
        $templates = [];
        $dir = MAWIBLAH_PLUGIN_DIR . '/email_templates';
        $files = scandir($dir);

        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && !is_dir($dir . '/' . $file)) {
                $templates[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        return $templates;
    }


    public static function saveCampaign(string $name, array $audience, string $emailTemplate)
    {
        if (!self::validateEmailTemplate($emailTemplate)) {
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

    public static function trackingParams(array $additionalParams = [])
    {
        $params = array_merge(
            [
                'utm_source' => 'email',
                'utm_medium' => 'email',
                'utm_campaign' => 'monthly-email',
                'subscriberId' => '{subscriberId}',
            ],
            $additionalParams
        );
        $queryString = http_build_query($params);

        return '?' . $queryString;
    }


    public static function getCurrentUrlPath()
    {

        $uriWithoutQuery = strtok(self::getCurrentUrl(), '?'); // Remove the query string

        return $uriWithoutQuery;
    }


    public static function getCurrentUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $uri = $_SERVER['REQUEST_URI'];

        return $protocol . $host . $uri;
    }

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

    public static function campaignTestResetUrl(int $campaignId): string
    {
        return self::generatePluginUrl(['action' => 'campaign-test-reset', 'campaignId' => $campaignId], 'campaignId');
    }

    public static function campaignTestApproveUrl(int $campaignId): string
    {
        return self::generatePluginUrl(['action' => 'campaign-test-approve', 'campaignId' => $campaignId], 'campaignId');
    }

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

    public static function generateSubscriberId(int $id): string{
        return md5($id);
    }
}
