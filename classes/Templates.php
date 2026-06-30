<?php

namespace Mawiblah;
class Templates
{

    /**
     * Fetches a processed email template via an internal REST request.
     *
     * Used during campaign sending to retrieve templates with WPML-aware shortcode evaluation,
     * because the REST call ensures WPML is fully initialised before shortcodes run.
     *
     * @param string $templateName Template filename without extension.
     * @return string|bool Processed HTML string, or false on failure.
     */
    public static function getTemplateByNameViaRest($templateName): string | bool
    {
        // When running under WP Cron there is no logged-in user, so an HTTP
        // loopback to the authenticated REST endpoint would always 401. Call
        // the template layer directly instead — identical result.
        if (defined('DOING_CRON') && DOING_CRON) {
            $content = self::getEmailTemplateByName($templateName);
            if ($content === false) {
                Logs::addError('template', "Template not found (cron direct load)", ['template' => $templateName]);
                return false;
            }
            Logs::addLog('template', "Template loaded directly (cron)", ['template' => $templateName]);
            return do_shortcode($content);
        }

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
            Logs::addError('template', "REST loopback failed (wp_error)", ['template' => $templateName, 'error' => $response->get_error_message()]);
            return false;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if ($statusCode !== 200 || !is_object($data) || empty($data->template)) {
            Logs::addError('template', "REST loopback returned unexpected response", ['template' => $templateName, 'status' => $statusCode, 'body' => substr($body, 0, 300)]);
            return false;
        }

        return $data->template;
    }

    /**
     * Returns the ordered list of directories to search for email templates.
     *
     * Search order: child theme → parent theme → plugin. This allows themes to override
     * plugin-bundled templates by placing HTML files in their mawiblah/email_templates/ directory.
     *
     * @return array<string, string> Map of label to directory path.
     */
    private static function getEmailTemplateDirectories(): array
    {
        $dirs = [];
        $child = trailingslashit(get_stylesheet_directory()) . 'mawiblah/email_templates';
        $parent = trailingslashit(get_template_directory()) . 'mawiblah/email_templates';
        $plugin = MAWIBLAH_PLUGIN_DIR . '/email_templates';

        if ($child === $parent) {
            $dirs['Theme'] = $child;
        } else {
            $dirs['Child Theme'] = $child;
            $dirs['Parent Theme'] = $parent;
        }
        $dirs['Plugin'] = $plugin;

        return $dirs;
    }

    /**
     * Returns the raw HTML content of an email template by name.
     *
     * Searches child theme, parent theme, then plugin directories in order.
     *
     * @param string $templateName Template filename without extension.
     * @return string|false Raw HTML content, or false if not found.
     */
    public static function getEmailTemplateByName($templateName)
    {
        $dirs = self::getEmailTemplateDirectories();
        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $files = scandir($dir);
                if ($files === false) {
                    continue;
                }
                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_FILENAME) === $templateName) {
                        return file_get_contents($dir . '/' . $file);
                    }
                }
            }
        }
        return false;
    }

    /**
     * Returns all available email templates with their source label as the display value.
     *
     * Deduplicates by template name so child-theme overrides shadow plugin templates.
     *
     * @return array<string, string> Map of template name to "Source: name" display string.
     */
    public static function getArrayOfEmailTemplates(): array
    {
        $templates = [];
        $dirs = self::getEmailTemplateDirectories();

        foreach ($dirs as $label => $dir) {
            if (is_dir($dir)) {
                $files = scandir($dir);
                if ($files === false) {
                    continue;
                }
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && !is_dir($dir . '/' . $file)) {
                        $filename = pathinfo($file, PATHINFO_FILENAME);
                        if (!array_key_exists($filename, $templates)) {
                            $templates[$filename] = $label . ': ' . $filename;
                        }
                    }
                }
            }
        }

        return $templates;
    }

    /**
     * Returns true if the given template name exists in any of the template directories.
     *
     * @param string $emailTemplate Template filename without extension.
     * @return bool
     */
    public static function validateEmailTemplate(string $emailTemplate): bool
    {
        return array_key_exists($emailTemplate, self::getArrayOfEmailTemplates());
    }

    /**
     * Fetches a campaign's email template via REST and archives a copy to disk.
     *
     * Archives are stored in email_templates/archived/ so campaign emails can be re-sent
     * with the exact template snapshot that existed at send time. In test mode the archive
     * is always refreshed; in production mode it is written only once.
     *
     * @param int  $campaignPostId Campaign post ID.
     * @param bool $testMode       When true, always overwrites the archived copy.
     * @return string|false Processed template HTML, or false if retrieval failed.
     */
    public static function copyTemplate(int $campaignPostId, bool $testMode): string|bool
    {
        $templateName = get_post_meta($campaignPostId, 'template', true);
        $templateArchived = get_post_meta($campaignPostId, 'email_template_copied', true);

        $dir = MAWIBLAH_PLUGIN_DIR . '/email_templates/archived';
        $template = self::getTemplateByNameViaRest($templateName);

        if ($template=== false) {
            return false;
        }

        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            error_log( "Cannot create archive dir $dir" );
            return false;
        }

        if (!$templateArchived || $testMode) {
            $filename = $campaignPostId . '_' . $templateName . '.html';
            file_put_contents($dir . '/' . $filename, $template);
            update_post_meta($campaignPostId, 'email_template_copied', true);
        }

        return $template;
    }

    /**
     * Resolves a PHP template path using the child-theme → parent-theme → plugin override chain.
     *
     * @param string $templatePath Relative path (e.g. 'stats/subscriber-growth.php').
     * @return string Absolute path to the first matching file, or path to missingTemplate.php.
     */
    public static function getTemplatePath( string $templatePath ):string {
        $theme_template_paths = array(
            trailingslashit( get_stylesheet_directory() ) .'mawiblah/'. $templatePath , // Child/Active Theme
            trailingslashit( get_template_directory('mawiblah') ) .'mawiblah/'. $templatePath ,    // Parent Theme
            MAWIBLAH_TEMPLATES_PATH .'/'. $templatePath ,
        );

        foreach ($theme_template_paths as $template_path) {
            if (file_exists($template_path)) {
                return $template_path;
            }
        }

        return MAWIBLAH_TEMPLATES_PATH. '/missingTemplate.php';
    }

    /**
     * Includes a PHP template, resolving it through the theme override chain.
     *
     * @param string $templatePath Relative path to the template.
     * @param mixed  $data         Data made available to the template as $data.
     */
    public static function loadTemplate(string $templatePath, mixed $data) {
        include self::getTemplatePath($templatePath);
    }

    /**
     * Renders a styled HTML data table using the campaign/table-stats.php partial.
     *
     * @param array $headers Column header labels.
     * @param array $rows    Array of row arrays, each containing one value per column.
     */
    public static function renderTable(array $headers, array $rows): void
    {
        $data = ['headers' => $headers, 'rows' => $rows];
        self::loadTemplate('campaign/table-stats.php',$data);
    }

    /**
     * Returns the translated name of a weekday.
     *
     * @param string $day English day name (e.g. 'Monday').
     * @return string Translated day name, or the original string if unrecognised.
     */
    public static function getDayTranslation($day){
        return match ($day) {
            'Monday' => __('Monday', 'mawiblah'),
            'Tuesday' => __('Tuesday', 'mawiblah'),
            'Wednesday' => __('Wednesday', 'mawiblah'),
            'Thursday' => __('Thursday', 'mawiblah'),
            'Friday' => __('Friday', 'mawiblah'),
            'Saturday' => __('Saturday', 'mawiblah'),
            'Sunday' => __('Sunday', 'mawiblah'),
            default => $day,
        };
    }
}
