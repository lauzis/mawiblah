<?php
/**
 * Plugin Name: Mawiblah
 * Plugin URI: https://github.com/lauzis/
 * Description: Fff-ine, will build my own mailchimp... with blackjack and hookers.
 * Version: 1.0.3
 * Author: Aivars Lauzis
 * Author URI: https://github.com/lauzis/
 * License: GPL3 - http://www.gnu.org/licenses/gpl.html
 */

if (!defined('MAWIBLAH_VERSION')) {
    define('MAWIBLAH_VERSION', '1.0.8.'.time());
}

if (!defined('MAWIBLAH_PLUGIN_DIR')) {
    define('MAWIBLAH_PLUGIN_DIR', untrailingslashit(plugin_dir_path(__FILE__)));
}

if (!defined('MAWIBLAH_PLUGIN_URL')) {
    define('MAWIBLAH_PLUGIN_URL', untrailingslashit(plugin_dir_url(__FILE__)));
}

if (!defined('MAWIBLAH_TEMPLATE_DIR')) {
    define( 'MAWIBLAH_TEMPLATE_DIR', MAWIBLAH_PLUGIN_DIR.'/templates');
}

if (!defined('MAWIBLAH_PLUGIN_FILE')) {
    define('MAWIBLAH_PLUGIN_FILE', plugin_basename(__FILE__));
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



function mawiblah_init(): void
{
    $mawiblah = new \Mawiblah\Init();
    $mawiblah->init();

    \Mawiblah\ShortCodes::register();
    \Mawiblah\Unsubscribe::init();
    \Mawiblah\Subscribers::registerPostType();
    \Mawiblah\Campaigns::init();
    \Mawiblah\Visits::init();
    \Mawiblah\Logs::init();
}

add_action('init', 'mawiblah_init');

function mawiblah_load_textdomain() {
    load_plugin_textdomain('mawiblah', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'mawiblah_load_textdomain');
