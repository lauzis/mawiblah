<?php

namespace Mawiblah;

use Mawiblah\Subscribers;

class Init
{
    const MAWIBLAH = 'mawiblah';
    const MAWIBLAH_CAMPAIGNS = 'mawiblah-campaigns';
    const MAWIBLAH_EMAIL_TEMPLATES = 'mawiblah-email-templates';
    const MAWIBLAH_TESTS = 'mawiblah-tests';

    const MAWIBLAH_SETTINGS = 'mawiblah-settings';

    const MAWIBLAH_ACTIONS = 'mawiblah-actions';
    public function init(): void
    {
        Migrations::run();
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_menu_links']);
        }
        $this->setup_hooks();
        $this->setup_api_routes();

    }

    public static function getIdsOfPages(){
        return [
            self::MAWIBLAH,
            self::MAWIBLAH_CAMPAIGNS,
            self::MAWIBLAH_EMAIL_TEMPLATES,
            self::MAWIBLAH_TESTS,
            self::MAWIBLAH_SETTINGS,
            self::MAWIBLAH_ACTIONS
        ];
    }

    public static function add_settings_link_to_plugin_list($links)
    {
        $links[] = '<a href="' . self::get_settings_page_url() . '">Settings</a>';
        return $links;
    }

    public static function get_settings_page_url()
    {
        return esc_url(get_admin_url(null, 'options-general.php?page=' . self::get_settings_page_relative_path()));
    }


    public function setup_api_routes(): void
    {

        add_action('rest_api_init', function () {
            register_rest_route('mawiblah/v1', '/get-html-template', array(
                'methods' => 'POST',
                'callback' => 'Mawiblah\RestRoutes::getHtmlTemplate',
                'permission_callback' => function () {
                    return current_user_can('edit_others_posts');
                }
            ));

            register_rest_route('mawiblah/v1', '/test', array(
                'methods' => 'GET',
                'callback' => 'Mawiblah\RestRoutes::test',
                'permission_callback' => function () {
                    return current_user_can('edit_others_posts');
                }
            ));

            register_rest_route('mawiblah/v1', '/send-email', array(
                'methods' => 'POST',
                'callback' => 'Mawiblah\RestRoutes::sendEmail',
                'permission_callback' => function () {
                    return current_user_can('edit_others_posts');
                }
            ));

        });
    }

    public function add_settings_link($links)
    {
        $settings_link = '<a href="admin.php?page=mawiblah-logs">' . __('Settings', 'mawiblah') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }


    private function setup_hooks(): void
    {
        $pageIds = $this->getIdsOfPages();

        if (isset($_GET['page']) && in_array($_GET['page'], $pageIds)) {
            add_action('admin_enqueue_scripts', [$this, 'my_custom_rest_api_nonce']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_plugin_styles']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_plugin_scripts']);
        }
    }

    public function registerPostsTypesAndTaxamonies()
    {
        Subscribers::registerPostType();
    }

    public function my_custom_rest_api_nonce()
    {

        $nonce = wp_create_nonce('wp_rest');
        $inline_script = '
        var mawiblahNonce = ' . json_encode(['mawiblahNonce' => $nonce]) . ';';

        wp_register_script( 'mawiblah-js', '',);
        wp_enqueue_script( 'mawiblah-js' );
        wp_add_inline_script( 'mawiblah-js', $inline_script);
    }

    public function enqueue_plugin_styles(): void
    {
        wp_enqueue_style('mawiblah-css', MAWIBLAH_PLUGIN_URL . '/assets/css/mawiblah.css', [], MAWIBLAH_VERSION);
        //wp_enqueue_style('mawiblah-lib-table-styles', MAWIBLAH_PLUGIN_URL . '/assets/lib/jquery.dataTables.css', [], MAWIBLAH_VERSION);
    }

    public function enqueue_plugin_scripts(): void
    {
        //wp_enqueue_script('mawiblah-lib-table-js', MAWIBLAH_PLUGIN_URL . '/assets/lib/jquery.dataTables.js.js', array('jquery'), MAWIBLAH_VERSION, true);
        wp_enqueue_script('mawiblah-main-js', MAWIBLAH_PLUGIN_URL . '/assets/js/mawiblah.js', ['mawiblah-js'], MAWIBLAH_VERSION);
    }

    public function add_menu_links(): void
    {
        add_menu_page(
            'Mawiblah',
            'Mawiblah',
            'manage_options',
            self::MAWIBLAH,
            [$this, 'mawiblah'],//'Init\Init::seo_audit',
            'dashicons-email-alt2', // You can change the icon
            85 // Adjust the position as needed
        );

        add_submenu_page(
            'mawiblah',
            'Compaigns',
            'Campaigns',
            'manage_options',
            self::MAWIBLAH_CAMPAIGNS,
            [$this, 'campaigns']
        );

        add_submenu_page(
            'mawiblah',
            'Email Templates',
            'Email Templates',
            'manage_options',
            self::MAWIBLAH_EMAIL_TEMPLATES,
            [$this, 'emailTemplates']
        );

        add_submenu_page(
            'mawiblah',
            'Tests',
            'Tests',
            'manage_options',
            self::MAWIBLAH_TESTS,
            [$this, 'tests']
        );

        add_submenu_page(
            'mawiblah',
            'Actions',
            'Actions',
            'manage_options',
            self::MAWIBLAH_ACTIONS,
            [$this, 'actions']
        );

        add_submenu_page(
            'mawiblah',
            'Settings',
            'Settings',
            'manage_options',
            self::MAWIBLAH_SETTINGS,
            [$this, 'settings']
        );
    }

    public function emailTemplates() {
        Renderer::emailTemplates();
    }

    public function mawiblah()
    {
        Renderer::start();
    }

    public function campaigns() {
        Renderer::campaigns();
    }

    public function tests() {
        Renderer::tests();
    }

    public function settings() {
        Renderer::settings();
    }

    public function actions() {
        Renderer::actions();
    }
}
