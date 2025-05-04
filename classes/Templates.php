<?php

namespace Mawiblah;
class Templates
{

    public static function getTemplateByNameViaRest($templateName): string | bool
    {
        $cookies = [];
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'wordpress_logged_in_') !== false || strpos($name, 'wp-settings-') !== false) {
                $cookies[] = $name . '=' . $value;
            }
        }
        $cookieHeader = implode('; ', $cookies);

        $url = "/wp-json/mawiblah/v1/get-html-template";
        $postData = (object) ['template'=> $templateName];

         $response = wp_remote_post(site_url() . $url, [
             'body' => json_encode($postData),
             'headers' => [
                 'Content-Type' => 'application/json',
                 'X-WP-Nonce' => wp_create_nonce('wp_rest'),
                 'cookie' => $cookieHeader
             ],
             'timeout'=> 15,
         ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        return $data->template ?? false;
    }

    public static function getEmailTemplateByName($templateName)
    {
        $dir = MAWIBLAH_PLUGIN_DIR . '/email_templates';
        $files = scandir($dir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_FILENAME) === $templateName) {
                return file_get_contents($dir . '/' . $file);
            }
        }
        return false;
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

    public static function validateEmailTemplate(string $emailTemplate): bool
    {
        $dir = MAWIBLAH_PLUGIN_DIR . '/email_templates';
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === $emailTemplate) {
                return true;
            }
        }
        return false;
    }

    public static function copyTemplate(int $campaignId, bool $testMode): string|bool
    {
        $templateName = get_post_meta($campaignId, 'template', true);
        $templateArchived = get_post_meta($campaignId, 'email_template_copied', true);

        $dir = MAWIBLAH_PLUGIN_DIR . '/email_templates/archieved';
        $template = self::getTemplateByNameViaRest($templateName);

        if ($template=== false) {
            return false;
        }

        if (!$templateArchived || $testMode) {
            $filename = $campaignId . '_' . $templateName . '.html';
            file_put_contents($dir . '/' . $filename, $template);
            update_post_meta($campaignId, 'email_template_copied', true);
        }

        return $template;
    }
}
