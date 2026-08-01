<?php

namespace Mawiblah;

class Settings
{

    /** Bare schema ids are the option keys minus this. */
    private const PREFIX = 'mawiblah-';

    private static $messages = [];
    private static $permissionFailure = false;

    /**
     * Returns the shared settings page, or null when the package is absent.
     *
     * @return \Lauzis\WpPackages\Settings\Settings|null
     */
    public static function page()
    {
        if (!class_exists('WpPackages_Registry')) {
            return null;
        }

        return \WpPackages_Registry::settings('mawiblah', [
            'title'       => __('Settings', 'mawiblah'),
            'mode'        => 'flat',
            'page_parent' => 'mawiblah',
            'page_file'   => MAWIBLAH_SETTINGS_PAGE,
        ]);
    }

    /** Declares the settings fields. Hooked on carbon_fields_register_fields. */
    public static function registerFields(): void
    {
        $page = self::page();

        if (!$page) {
            return;
        }

        $page->register(MAWIBLAH_CONFIG_PATH . '/settings.json', [
            'prefix' => self::PREFIX,
            'domain' => 'mawiblah',
        ]);

        $page->render();
    }

    /**
     * Reads every option declared in the schema, then deletes it.
     *
     * @return void
     */
    private static function remove_sections_options()
    {
        $schema = json_decode(file_get_contents(MAWIBLAH_CONFIG_PATH . '/settings.json'), true);

        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] as $field) {
                $key = self::PREFIX . $field['id'];
                // Carbon Fields stores theme options under an underscore-prefixed
                // key; the un-prefixed name is the pre-1.0.31 location.
                delete_option('_' . $key);
                delete_option($key);
            }
        }
    }

    /** Removes all plugin options and deletes generated files and directories on uninstall. */
    public static function uninstall()
    {
        // removing options
        self::remove_sections_options();

        // MAWIBLAH_GENERATE_PATH is legacy: it only ever held the translation
        // string cache and the generated .pot, both of which are gone. It is
        // still cleaned up here so upgraded installs do not leave the directory
        // behind, and guarded because new installs never create it.
        foreach ([MAWIBLAH_GENERATE_PATH, MAWIBLAH_LOG_PATH] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            array_map('unlink', glob($dir . "*.*"));
            rmdir($dir);
        }
    }

    /** Plugin activation hook handler (currently a stub). */
    public static function activate()
    {
        //TODO
        // would be nice to show message with link to settings page
    }

    /** Plugin deactivation hook handler. Resets the settings-page-visited flag. */
    public static function deactivate()
    {
        update_option("gae-settings-page-visited", 0);
    }

    /** Settings init hook (currently a stub, registration is handled by get_sections). */
    public static function init()
    {
        //register settings
        //gea_register_scripts();
        //self::add_scripts();
    }

    /** Returns the full URL to the plugin settings page. */
    public static function get_settings_page_url()
    {
        return esc_url(get_admin_url(null, 'admin.php?page=' . self::get_settings_page_relative_path()));
    }

    /** Returns the settings page slug constant. */
    public static function get_settings_page_relative_path()
    {
        return MAWIBLAH_SETTINGS_PAGE;
    }

    /**
     * Returns the current debug level (0/1/2/3) based on the stored option value.
     *
     * Only applies if the current IP is in the allowed debug-IP list (when set).
     * Returns false when debugging is disabled.
     *
     * @return int|false Debug level, or false if disabled.
     */
    public static function debug()
    {
        # only run debug on localhost
        $ips = trim(get_option("gea-debug-ip"));
        if (!empty($ips)) {
            $ips = explode(",", $ips);
            $ips = array_unique($ips);
            if (count($ips) > 0) {
                if (!in_array(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')), $ips, true)) {
                    return false;
                }
            }
        }

        $debug_level = get_option("gae-debug");
        switch ($debug_level) {
            case "disabled":
                return false;
                break;
            case "enable-php-log":
                return 1;
                break;
            case "enable-console-log":
                return 2;
                break;
            case "enable-show-on-front":
                return 3;
                break;

            default:
                return $debug_level;
                break;
        }
        return false;
    }

    /**
     * Returns the debug level for the current admin user based on their stored option value.
     *
     * @return int|false Debug level, or false if the user is not an administrator.
     */
    public static function debug_admin()
    {
        $debug_level = 0;
        $current_user = wp_get_current_user();
        if ($current_user) {
            if (user_can($current_user, 'administrator')) {
                $debug_level = get_option("gae-debug-when-admin");
            }
        }

        switch ($debug_level) {
            case "disabled":
                return false;
                break;
            case "enable-php-log":
                return 1;
                break;
            case "enable-console-log":
                return 2;
                break;
            case "enable-show-on-front":
                return 3;
                break;
            case "enable-use-test-ga-id":
                return 4;
                break;
            default:
                return $debug_level;
                break;
        }
        return false;
    }
    /**
     * Queues an admin notice message to be displayed on the settings page.
     *
     * @param string $text Message text.
     * @param string $type Notice type: 'success' or 'error'.
     */
    public static function add_message($text, $type = "success")
    {
        array_push(self::$messages, ["type" => $type, "message" => "MAWIBLAH: " . $text]);
    }

    /**
     * Outputs a single dismissible WordPress admin notice.
     *
     * @param int|string $id      Unique notice ID used in the element's HTML id attribute.
     * @param string     $message Notice text.
     * @param string     $type    Notice class suffix: 'success', 'error', 'warning', etc.
     */
    public static function print_message($id, $message, $type)
    {
        ?>
        <div id="message-<?= $id; ?>" class="gae-message notice notice-<?= $type; ?> is-dismissible">
            <p>
                <?= $message; ?>
            </p>
            <button type="button" class="notice-dismiss">
                <span class="screen-reader-text"><?php echo esc_html__('Dismiss this notice.', 'mawiblah'); ?></span>
            </button>
        </div>
        <?php
    }

    /** Outputs all queued admin notices added via add_message(). */
    public static function print_all_messages()
    {
        foreach (self::$messages as $id => $message) {
            self::print_message($id, $message["message"], $message["type"]);
        }
    }

    /** Records that the settings page has been visited (used to suppress first-run notices). */
    public static function settings_page_visited()
    {
        update_option("gae-settings-page-visited", 1);
    }

    /** Returns truthy if the settings page has been visited at least once, falsy otherwise. */
    public static function is_settings_page_visited()
    {
        return get_option("gae-settings-page-visited");
    }

    /** Returns true when the "Send emails" setting is enabled (email sending is not suppressed). */
    public static function sendEmails():bool
    {
        return self::getOption("mawiblah-dont-send-emails") === 'send-emails';
    }

    /** Returns the do-not-disturb threshold in seconds (minimum time between emails to the same subscriber). */
    public static function dontDisturbThreshold(){
        return self::getOption('mawiblah-dont-disturb-threshold');
    }

    /** Returns the failing-email failure threshold (default 3). */
    public static function failingEmailThreshold(): int
    {
        return max(1, (int) self::getOption('mawiblah-failing-email-threshold'));
    }

    /** Returns true when reCAPTCHA v3 is set to "enabled" in settings (keys may still be missing). */
    public static function recaptchaEnabled(): bool
    {
        return self::getOption('mawiblah-recaptcha-enabled') === 'enabled';
    }

    /** Returns the reCAPTCHA v3 site key (public, used in the browser). Empty string if not configured. */
    public static function recaptchaSiteKey(): string
    {
        return (string) self::getOption('mawiblah-recaptcha-site-key');
    }

    /** Returns the reCAPTCHA v3 secret key (private, used server-side). Empty string if not configured. */
    public static function recaptchaSecretKey(): string
    {
        return (string) self::getOption('mawiblah-recaptcha-secret-key');
    }

    /** Returns the background send batch size (subscribers per cron run). Defaults to 100. */
    public static function backgroundBatchSize(): int
    {
        return max(1, (int) (self::getOption('mawiblah-background-batch-size') ?: 100));
    }

    /** Returns the scheduler check interval in seconds (default 3600). */
    public static function schedulerInterval(): int
    {
        return max(60, (int) (self::getOption('mawiblah-scheduler-interval') ?: 3600));
    }

    /** Returns true when email open tracking is set to "enabled" in settings. */
    public static function openTrackingEnabled(): bool
    {
        return self::getOption('mawiblah-open-tracking-enabled') === 'enabled';
    }

    /** Returns true only when reCAPTCHA is enabled AND both site key and secret key are non-empty. */
    public static function recaptchaReady(): bool
    {
        return self::recaptchaEnabled()
            && self::recaptchaSiteKey() !== ''
            && self::recaptchaSecretKey() !== '';
    }

    /**
     * Returns a plugin option value, falling back to the schema default.
     *
     * @param string $optionId Full option key, e.g. "mawiblah-debug".
     * @return mixed Stored option value, or the field's default_value if not yet saved.
     */
    public static function getOption($optionId)
    {
        $page = self::page();

        if (!$page) {
            return null;
        }

        // Callers pass the full option key; the schema knows it by its bare id.
        $bare = str_starts_with($optionId, self::PREFIX)
            ? substr($optionId, strlen(self::PREFIX))
            : $optionId;

        return $page->get($bare);
    }
}
