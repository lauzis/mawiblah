<?php

namespace Mawiblah;
use Gae_Logger;

class Settings
{

    public static $folder_that_should_be_writable = [mawiblah_GENERATE_PATH, mawiblah_LOG_PATH];
    private static $messages = [];
    private static $permissionFailure = false;

    public static function uninstall()
    {
        // removing options
        self::remove_sections_options();
        // removing files
        array_map('unlink', glob(mawiblah_GENERATE_PATH . "*.*"));
        array_map('unlink', glob(mawiblah_LOG_PATH . "*.*"));
        //removing dirs
        rmdir(mawiblah_GENERATE_PATH);
        rmdir(mawiblah_LOG_PATH);
    }

    public static function activate()
    {
        //TODO
        // would be nice to show message with link to settings page
    }

    public static function deactivate()
    {
        update_option("gae-settings-page-visited", 0);
    }

    public static function init()
    {
        //register settings
        //gea_register_scripts();
        //self::add_scripts();

    }


    public static function get_settings_page_url()
    {
        return esc_url(get_admin_url(null, 'options-general.php?page=' . self::get_settings_page_relative_path()));
    }

    public static function get_settings_page_relative_path()
    {
        return MAWIBLAH_PLUGIN_DIR . '/mawiblah.php';
    }

    public static function create_menu()
    {

        // or create options menu page
        add_options_page(
            self::get_translation('Google Analytics Events'), //'My Options',
            self::get_translation("Google Analytics Events"), //'My Plugin',
            "manage_options",
            self::get_settings_page_relative_path()

        );
        // or create sub menu page
        $parent_slug = "index.php";    # For Dashboard
        #$parent_slug="edit.php";		# For Posts
        // more examples at http://codex.wordpress.org/Administration_Menus
        //add_submenu_page( $parent_slug, __("HTML Title 4", EMU2_I18N_DOMAIN), __("Menu title 4", EMU2_I18N_DOMAIN), 9, mawiblah_PLUGIN_DIRECTORY.'/mawiblah_settings_page.php');
        add_filter('plugin_action_links_' . plugin_basename(mawiblah_PLUGIN_FILE), 'Settings::add_settings_link_to_plugin_list');

    }

    public static function debug()
    {
        # only run debug on localhost
        $ips = trim(get_option("gea-debug-ip"));
        if (!empty($ips)) {
            $ips = explode(",", $ips);
            $ips = array_unique($ips);
            if (count($ips) > 0) {
                if (!in_array($_SERVER["REMOTE_ADDR"], $ips)) {
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
    public static function add_message($text, $type = "success")
    {
        array_push(self::$messages, ["type" => $type, "message" => "GAE: " . $text]);
    }

    private static function remove_sections_options()
    {
        $sections = json_decode(file_get_contents(mawiblah_INCLUDES_PATH . "/sections.json"), true);

        foreach ($sections as $sk => $s) {

            foreach ($s["fields"] as $fk => $f) {
                $sections[$sk]["fields"][$fk]["value"] = get_option($f["id"]);
                delete_option($f["id"]);
            }
        }
    }

    public static function get_sections()
    {
        $options_updated = false;
        $sections = json_decode(file_get_contents(MAWIBLAH_CONFIG_PATH . "/sections.json"), true);

        foreach ($sections as $sk => $s) {

            foreach ($s["fields"] as $fk => $f) {

                if (isset($_POST[$f["id"]])) {
                    update_option($f["id"], $_POST[$f["id"]]);
                    $sections[$sk]["fields"][$fk]["value"] = $_POST[$f["id"]];
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

    public static function is_settings_page()
    {
        //// TODO: check if we are on settings page to load addtional css
        return true;
    }

    public static function add_css()
    {
        echo '<link id="' . mawiblah_PLUGIN_DIRECTORY . '" rel="stylesheet" href="' . mawiblah_CSS_URL . '/gae-admin.css' . '" type="text/css" media="all" />';
    }

    public static function add_scripts()
    {
        if (is_admin()) {
            if (self::is_settings_page()) {
                add_action('admin_head', 'Settings::add_css');
            }
        }
        wp_enqueue_script('Settings_script', mawiblah_JS_URL . '/gae-admin.js');
    }

    public static function add_settings_link_to_plugin_list($links)
    {
        if (ģae_DONATION_SHOW_LINKS) {
            $links[] = '<a target="_blank" href="' . mawiblah_DONATION_URL . '">Donate</a>';
        }
        $links[] = '<a href="' . self::get_settings_page_url() . '">Settings</a>';
        return $links;
    }

    public static function show_donation_block()
    {
        //todo check the last interaction ignore for a while
        return true;
    }


    public static function print_message($id, $message, $type)
    {
        ?>
        <div id="message-<?= $id; ?>" class="gae-message notice notice-<?= $type; ?> is-dismissible">
            <p>
                <?= $message; ?>
            </p>
            <button type="button" class="notice-dismiss">
                <span class="screen-reader-text"><?= Settings::get_translation("Dismiss this notice."); ?></span>
            </button>
        </div>
        <?php
    }

    public static function print_all_messages()
    {
        foreach (self::$messages as $id => $message) {
            self::print_message($id, $message["message"], $message["type"]);
        }
    }

    public static function settings_page_visited()
    {
        update_option("gae-settings-page-visited", 1);
    }

    public static function is_settings_page_visited()
    {
        return get_option("gae-settings-page-visited");
    }

    public static function get_translation_count()
    {
        $translationIdsFile = mawiblah_TRANSLATION_IDS_FILE;
        $translationIds = [];
        if (file_exists($translationIdsFile)) {
            $translationIds = unserialize(file_get_contents($translationIdsFile));
        }
        return count($translationIds);
    }

    public static function get_translation($text, $params = [])
    {

        if (MAWIBLAH_DEVELOPER) {

            $text_id = strip_tags($text);
            $translationIdsFile = mawiblah_TRANSLATION_IDS_FILE;
            $translationIds = [];
            $changed = false;
            if (file_exists($translationIdsFile)) {
                $translationIds = unserialize(file_get_contents($translationIdsFile));
            } else {
                if (@touch($translationIdsFile)) {
                    chmod($translationIdsFile, 0777);
                }
            }

            if (!isset($translationIds[$text])) {
                $translationIds[$text] = $text;
                $changed = true;
            }

            if (is_array($params)) {
                foreach ($params as $item) {
                    if (!isset($translationIds[$item])) {
                        $translationIds[$item] = $item;
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                if (is_writable($translationIdsFile)) {
                    file_put_contents($translationIdsFile, serialize($translationIds));
                }
            }
        }

        $text = __($text, EMU2_I18N_DOMAIN);

        if (is_array($params) && count($params) > 0) {
            $text = vsprintf($text, $params);
        } elseif (!empty($params)) {
            $text = sprintf($text, $params);
        }
        //
        return $text;
    }

    public static function generate_pot_file()
    {
        if (MAWIBLAH_DEVELOPER) {

            $pot_header = '
msgid ""
msgstr ""
"Project-Id-Version:Google Analytics Events\n"
"POT-Creation-Date: ' . date("Y-m-d H:i:s") . '\n"
"PO-Revision-Date: ' . date("Y-m-d H:i:s") . '\n"
"Last-Translator: Aivars Lauzis\n"
"Language-Team: \n"
"Language: en\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"X-Generator: Poedit 2.0.3\n"
"X-Poedit-Basepath: ..\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\n"
"X-Poedit-KeywordsList: ;__;_e\n"
"X-Poedit-SearchPath-0: .\n"
"X-Poedit-SearchPathExcluded-0: assets/css\n"
"X-Poedit-SearchPathExcluded-1: assets/inc/chosen\n"
"X-Poedit-SearchPathExcluded-2: assets/js\n"
"X-Poedit-SearchPathExcluded-3: lang\n"

';
            $translationIdsFile = mawiblah_TRANSLATION_IDS_FILE;
            $potFile = mawiblah_GENERATE_PATH . MAWIBLAH_PLUGIN_DIRECTORY_NAME . ".pot";
            $potFileUrl = mawiblah_GENERATE_URL . MAWIBLAH_PLUGIN_DIRECTORY_NAME . ".pot";

            $dir_potFile = dirname($potFile);

            if (!is_writable($dir_potFile)) {
                self::add_message("Directory ($dir_potFile) not writable. Could not generate pot file.", "error");
                return false;
            }

            if (!file_exists($potFile)) {
                if (!@touch($potFile) || !@chmod($potFile, 0777)) {
                    self::add_message("Could not create ($potFile). Could not generate pot file.", "error");
                }
            }

            if (!is_writable($potFile)) {
                self::add_message("File ($potFile) not writable. Could not generate pot file.", "error");
                return false;
            }

            if (!file_exists($translationIdsFile)) {
                self::add_message("Could not generate POT file no translations collected, cant find file $translationIdsFile", "error");
                return false;
            }

            if (file_exists($translationIdsFile)) {
                $translationIds = unserialize(file_get_contents($translationIdsFile));
            }
            file_put_contents($potFile, $pot_header);
            foreach ($translationIds as $k => $value) {
                $potText = '
                
msgid "' . htmlspecialchars(str_replace(array("\r\n", "\r", "\n"), "", $translationIds[$k])) . '"
msgstr ""
';
                file_put_contents($potFile, $potText, FILE_APPEND);
            }
            self::add_message("Pot file generated. You will find it here $potFile");
        }
    }
}
