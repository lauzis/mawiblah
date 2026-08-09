<?php
/**
 * Plugin Name: Mawiblah
 * Plugin URI: https://github.com/lauzis/
 * Description: Fff-ine, will build my own mailchimp... with blackjack and hookers.
 * Version: 1.0.33
 * Author: Aivars Lauzis
 * Author URI: https://github.com/lauzis/
 * License: GPL3 - http://www.gnu.org/licenses/gpl.html
 * Requires PHP: 8.0
 */

define('MAWIBLAH_VERSION_BASE', '1.0.33');
if (!defined('MAWIBLAH_VERSION')) {
    define('MAWIBLAH_VERSION', MAWIBLAH_VERSION_BASE);
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
        mkdir($baseDir, 0755);
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
// Legacy directory: it only ever held the runtime translation-string cache and
// the generated .pot. Nothing writes here any more; the constant remains so
// uninstall can still clear it on upgraded installs.
define('MAWIBLAH_GENERATE_PATH', str_replace('\\', '/', $uloads_dir["basedir"] . '/gae/'));

define('MAWIBLAH_LOG_PATH', str_replace('\\', '/', $uloads_dir["basedir"] . '/gae-logs/'));
define('MAWIBLAH_TEMPLATES_PATH', MAWIBLAH_PLUGIN_DIR . "/templates");
define('MAWIBLAH_SETTINGS_PAGE', 'mawiblah-settings');


// Composer dependencies (lauzis/wp-plugin-packages, Carbon Fields). Guarded so a
// build shipped without vendor/ degrades gracefully instead of fataling.
if (file_exists(MAWIBLAH_PLUGIN_DIR . '/vendor/autoload.php')) {
    require_once MAWIBLAH_PLUGIN_DIR . '/vendor/autoload.php';
    // Required explicitly: Composer's files autoload runs only one copy of this
    // package per request, so the version gate would never see the others.
    require_once MAWIBLAH_PLUGIN_DIR . '/vendor/lauzis/wp-plugin-packages/bootstrap.php';
}

// Carbon Fields renders the settings page. It self-guards against a double
// boot, so calling it here is safe even if another plugin has already done so.
add_action('after_setup_theme', static function (): void {
    if (class_exists('\\Carbon_Fields\\Carbon_Fields')) {
        \Carbon_Fields\Carbon_Fields::boot();
    }
});

add_action('carbon_fields_register_fields', ['\Mawiblah\Settings', 'registerFields']);

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
require(MAWIBLAH_PLUGIN_DIR . '/classes/Migrations.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/Actions.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/SubscriptionForm.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/SetupNotice.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/CronSend.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/Import.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/Scheduler.php');
require(MAWIBLAH_PLUGIN_DIR . '/classes/SchedulerCron.php');

function mawiblah_init(): void
{
    $mawiblah = new \Mawiblah\Init();
    $mawiblah->init();

    \Mawiblah\ShortCodes::register();
    \Mawiblah\Unsubscribe::init();
    \Mawiblah\Subscribers::init();
    \Mawiblah\Campaigns::init();
    \Mawiblah\Visits::init();
    \Mawiblah\GravityForms::init();
    \Mawiblah\SetupNotice::init();
    \Mawiblah\SubscriptionForm::init();
    \Mawiblah\CronSend::init();
    \Mawiblah\Scheduler::init();
    \Mawiblah\SchedulerCron::init();
}

add_action('init', 'mawiblah_init');

// Dashboard widget needs to be registered earlier
\Mawiblah\Actions::init();

function mawiblah_load_textdomain()
{
    load_plugin_textdomain('mawiblah', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_action('plugins_loaded', 'mawiblah_load_textdomain');

/**
 * Subscribe an email address to one or more Mawiblah audiences.
 *
 * @param string   $email          Email address to subscribe.
 * @param string[] $audienceHashes Optional array of audienceHash values.
 * @return array{status: string, message: string}
 */
function mawiblah_subscribe(string $email, array $audienceHashes = []): array
{
    return \Mawiblah\SubscriptionForm::subscribeByEmail($email, $audienceHashes);
}

// The plugin's version in the admin footer, beside WordPress's own — the first
// thing worth knowing about a page misbehaving is which version drew it.
add_action( 'admin_init', static function () {
    if ( ! class_exists( '\\Lauzis\\WpPackages\\Admin\\Footer' ) ) {
        return;
    }

    \Lauzis\WpPackages\Admin\Footer::show(
        'mawiblah',
        array(
            'name'    => 'Mawiblah',
            'version' => defined( 'MAWIBLAH_VERSION_BASE' ) ? MAWIBLAH_VERSION_BASE : '',
            'types'   => array( 'mawiblah_subscriber', 'mawiblah_campaign' ),
        )
    );
} );
