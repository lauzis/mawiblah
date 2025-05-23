<?php
/**
 * Plugin Name: Mawiblah
 * Plugin URI: https://github.com/lauzis/
 * Description: Fff-ine, will build my own mailchimp... with blackjack and hookers.
 * Version: 1.0.14
 * Author: Aivars Lauzis
 * Author URI: https://github.com/lauzis/
 * License: GPL3 - http://www.gnu.org/licenses/gpl.html
 */

if (!defined('MAWIBLAH_VERSION')) {
    define('MAWIBLAH_VERSION', '1.0.14.' . time());
}

define('MAWIBLAH_PLUGIN_NAME', 'Mawiblah');

if (!defined('MAWIBLAH_PLUGIN_DIR')) {
    define('MAWIBLAH_PLUGIN_DIR', untrailingslashit(plugin_dir_path(__FILE__)));
}

if (!defined('MAWIBLAH_PLUGIN_DIRECTORY_NAME')) {
    define('MAWIBLAH_PLUGIN_DIRECTORY_NAME', "mawiblah");
}

if (!defined('MAWIBLAH_PLUGIN_URL')) {
    define('MAWIBLAH_PLUGIN_URL', untrailingslashit(plugin_dir_url(__FILE__)));
}

if (!defined('MAWIBLAH_TEMPLATE_DIR')) {
    define('MAWIBLAH_TEMPLATE_DIR', MAWIBLAH_PLUGIN_DIR . '/templates');
}

if (!defined('MAWIBLAH_PLUGIN_FILE')) {
    define('MAWIBLAH_PLUGIN_FILE', plugin_basename(__FILE__));
}

if (!defined('MAWIBLAH_CONFIG_PATH')) {
    define('MAWIBLAH_CONFIG_PATH', MAWIBLAH_PLUGIN_DIR . '/config');
}

if (!defined('MAWIBLAH_UPLOAD_DIR')) {
    $uploadDir = wp_get_upload_dir();
    $baseDir = $uploadDir['basedir'] . '/mawiblah';
    if (!is_dir($baseDir) && !file_exists($baseDir)) {
        mkdir($baseDir, 0777);
    }
    define('MAWIBLAH_UPLOAD_DIR', untrailingslashit($baseDir));
}


if (!defined('MAWIBLAH_UPLOAD_URL')) {
    $uploadDir = wp_get_upload_dir();
    $baseDir = $uploadDir['baseurl'] . '/mawiblah';
    define('MAWIBLAH_UPLOAD_URL', untrailingslashit($baseDir));
}

if (!defined('MAWIBLAH_REPORT_URL')) {
    $url = MAWIBLAH_UPLOAD_URL . '/report';
    define('MAWIBLAH_REPORT_URL', $url);
}

define('MAWIBLAH_POST_TYPE_PREFIX', 'mawiblah_');
$uloads_dir = wp_upload_dir();
define('MAWIBLAH_DEVELOPER', true);
define('MAWIBLAH_GENERATE_PATH', str_replace('\\', '/', $uloads_dir["basedir"] . '/gae/'));
define('MAWIBLAH_TRANSLATION_IDS_FILE', MAWIBLAH_GENERATE_PATH . MAWIBLAH_PLUGIN_DIRECTORY_NAME . ".serialized.php");

define('MAWIBLAH_LOG_PATH', str_replace('\\', '/', $uloads_dir["basedir"] . '/gae-logs/'));
define('MAWIBLAH_TEMPLATES_PATH', MAWIBLAH_PLUGIN_DIR . "/templates");
define('MAWIBLAH_SETTINGS_PAGE', 'mawiblah-settings');


require(MAWIBLAH_PLUGIN_DIR . '/classes/Settings.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/Helpers.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/Init.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/Tests.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/RestRoutes.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/Templates.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/ShortCodes.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/Unsubscribe.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/Subscribers.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/Campaigns.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/GravityForms.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/Renderer.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/Visits.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/Logs.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/Actions.php');

function mawiblah_init(): void
{
    $mawiblah = new \Mawiblah\Init();
    $mawiblah->init();

    \Mawiblah\ShortCodes::register();
    \Mawiblah\Unsubscribe::init();
    \Mawiblah\Subscribers::init();
    \Mawiblah\Campaigns::init();
    \Mawiblah\Visits::init();
    \Mawiblah\Logs::init();
    \Mawiblah\GravityForms::init();
    \Mawiblah\Actions::init();
}

add_action('init', 'mawiblah_init');

function mawiblah_load_textdomain()
{
    load_plugin_textdomain('mawiblah', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('plugins_loaded', 'mawiblah_load_textdomain');
