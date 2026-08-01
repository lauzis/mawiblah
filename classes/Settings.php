<?php

namespace Mawiblah;

class Settings
{

    private static $messages = [];
    private static $permissionFailure = false;

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

    /** Registers the plugin options page and adds the Settings link in the plugin list. */
    public static function create_menu()
    {

        // or create options menu page
        add_options_page(
            __('Google Analytics Events', 'mawiblah'),
            __('Google Analytics Events', 'mawiblah'),
            "manage_options",
            self::get_settings_page_relative_path()

        );
        // or create sub menu page
        $parent_slug = "index.php";    # For Dashboard
        #$parent_slug="edit.php";		# For Posts
        // more examples at http://codex.wordpress.org/Administration_Menus
        //add_submenu_page( $parent_slug, __("HTML Title 4", EMU2_I18N_DOMAIN), __("Menu title 4", EMU2_I18N_DOMAIN), 9, MAWIBLAH_PLUGIN_DIR.'/mawiblah_settings_page.php');
        add_filter('plugin_action_links_' . plugin_basename(MAWIBLAH_PLUGIN_FILE), 'Settings::add_settings_link_to_plugin_list');

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

    /** Reads all section field values from the database and then deletes those options. Called during uninstall. */
    private static function remove_sections_options()
    {
        $sections = json_decode(file_get_contents(MAWIBLAH_CONFIG_PATH . "/sections.json"), true);

        foreach ($sections as $sk => $s) {

            foreach ($s["fields"] as $fk => $f) {
                $sections[$sk]["fields"][$fk]["value"] = get_option($f["id"]);
                delete_option($f["id"]);
            }
        }
    }

    /**
     * Returns all settings sections with current values loaded from the database.
     *
     * On POST (settings form submission), validates the nonce, sanitizes each value
     * by field type, persists it via update_option(), and refreshes the asset version.
     *
     * @return array Settings sections with fields populated with their current values.
     */
    public static function get_sections()
    {
        $options_updated = false;
        $sectionsFile = MAWIBLAH_CONFIG_PATH . "/sections.json";
        $sections = json_decode(file_get_contents($sectionsFile), true);

        $is_post = !empty($_POST) && isset($_GET['page']) && $_GET['page'] === MAWIBLAH_SETTINGS_PAGE;
        if ($is_post) {
            check_admin_referer('gae-settings-group-options');
        }

        foreach ($sections as $sk => $s) {

            foreach ($s["fields"] as $fk => $f) {

                if ($is_post && isset($_POST[$f["id"]])) {
                    $raw   = wp_unslash($_POST[$f["id"]]);
                    $value = $f['type'] === 'textarea'
                        ? sanitize_textarea_field($raw)
                        : sanitize_text_field($raw);
                    update_option($f["id"], $value);
                    $sections[$sk]["fields"][$fk]["value"] = $value;
                    $options_updated = true;
                } else {
                    $sections[$sk]["fields"][$fk]["value"] = get_option($f["id"]);
                }
            }
        }
        if ($options_updated) {
            update_option("gae-assets-version", time());
        }
        return $sections;
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
     * Returns a plugin option value, falling back to the field's default_value from sections.json.
     *
     * @param string $optionId WordPress option key matching a field id in sections.json.
     * @return mixed Stored option value, or the field's default_value if not yet saved.
     */
    public static function getOption($optionId)
    {
        // TODO: return default value if option not set
        $value = get_option($optionId);

        if (!$value) {
            $sections = self::get_sections();
            foreach ($sections as $section) {
                foreach ($section['fields'] as $field) {
                    if ($field['id'] === $optionId) {
                        if ($field['type']!=='boolean'){
                            return $field['default_value'];
                        }
                        break;
                    }
                }
            }
        }

        return $value;
    }
}
