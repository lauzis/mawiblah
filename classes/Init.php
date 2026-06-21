<?php

namespace Mawiblah;

use Mawiblah\Campaigns;
use Mawiblah\Subscribers;

class Init
{
    const MAWIBLAH = 'mawiblah';
    const MAWIBLAH_CAMPAIGNS = 'mawiblah-campaigns';
    const MAWIBLAH_EMAIL_TEMPLATES = 'mawiblah-email-templates';
    const MAWIBLAH_TESTS = 'mawiblah-tests';

    const MAWIBLAH_SETTINGS = 'mawiblah-settings';

    const MAWIBLAH_ACTIONS = 'mawiblah-actions';
    const MAWIBLAH_LOGS    = 'mawiblah-logs';
    const MAWIBLAH_HELP    = 'mawiblah-help';
    /** Bootstraps the plugin: runs migrations, registers admin menu, hooks, REST routes, and blocks. */
    public function init(): void
    {
        Migrations::run();
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_menu_links']);
        }
        $this->setup_hooks();
        $this->setup_api_routes();
        $this->registerBlocks();
        add_filter('block_categories_all', [$this, 'registerBlockCategories'], 10, 2);
    }

    /** Returns the array of all plugin admin page slugs used to conditionally enqueue assets. */
    public static function getIdsOfPages(){
        return [
            self::MAWIBLAH,
            self::MAWIBLAH_CAMPAIGNS,
            self::MAWIBLAH_EMAIL_TEMPLATES,
            self::MAWIBLAH_TESTS,
            self::MAWIBLAH_SETTINGS,
            self::MAWIBLAH_ACTIONS,
            self::MAWIBLAH_LOGS,
            self::MAWIBLAH_HELP,
        ];
    }

    /**
     * Appends a Settings link to the plugin entry in the WP plugin list table.
     *
     * @param array $links Existing action links.
     * @return array Modified links array.
     */
    public static function add_settings_link_to_plugin_list($links)
    {
        $links[] = '<a href="' . self::get_settings_page_url() . '">Settings</a>';
        return $links;
    }

    /** Returns the full URL to the plugin settings page in the WordPress admin. */
    public static function get_settings_page_url()
    {
        return esc_url(get_admin_url(null, 'admin.php?page=' . self::MAWIBLAH_SETTINGS));
    }


    /** Registers all plugin REST API routes on the rest_api_init hook. */
    public function setup_api_routes(): void
    {

        add_action('rest_api_init', function () {
            register_rest_route('mawiblah/v1', '/get-html-template', array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => 'Mawiblah\RestRoutes::getHtmlTemplate',
                'permission_callback' => function () {
                    return current_user_can('edit_others_posts');
                },
            ));

            register_rest_route('mawiblah/v1', '/test', array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => 'Mawiblah\RestRoutes::test',
                'permission_callback' => function () {
                    return current_user_can('edit_others_posts');
                },
            ));

            register_rest_route('mawiblah/v1', '/send-email', array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => 'Mawiblah\RestRoutes::sendEmail',
                'permission_callback' => function () {
                    return current_user_can('edit_others_posts');
                },
                'args'                => [
                    'campaignPostId' => [
                        'type'     => 'integer',
                        'required' => true,
                    ],
                    'subscriberId'   => [
                        'type'     => 'integer',
                        'required' => true,
                    ],
                    'email'          => [
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_email',
                    ],
                    'lastItem'       => [
                        'type'    => 'boolean',
                        'default' => false,
                    ],
                ],
            ));

            register_rest_route('mawiblah/v1', '/subscribe', array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => 'Mawiblah\SubscriptionForm::subscribe',
                'permission_callback' => '__return_true',
                'args'                => [
                    'email'          => [
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_email',
                    ],
                    'audienceHashes' => [
                        'type'    => 'array',
                        'default' => [],
                        'items'   => ['type' => 'string'],
                    ],
                    'honeypot'       => [
                        'type'    => 'string',
                        'default' => '',
                    ],
                    'recaptchaToken' => [
                        'type'              => 'string',
                        'default'           => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ));

            register_rest_route('mawiblah/v1', '/unsubscribe', array(
                'methods'             => [ \WP_REST_Server::READABLE, \WP_REST_Server::CREATABLE ],
                'callback'            => 'Mawiblah\Unsubscribe::oneClickEndpoint',
                'permission_callback' => '__return_true',
            ));

        });
    }

    /**
     * Prepends a Settings link in the plugin action links (admin bar variant).
     *
     * @param array $links Existing action links.
     * @return array Modified links array.
     */
    public function add_settings_link($links)
    {
        $settings_link = '<a href="' . esc_url(get_admin_url(null, 'admin.php?page=' . self::MAWIBLAH_SETTINGS)) . '">' . __('Settings', 'mawiblah') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }


    /** Enqueues admin scripts and styles when the current page is a plugin admin page. */
    private function setup_hooks(): void
    {
        $pageIds = $this->getIdsOfPages();

        if (isset($_GET['page']) && in_array($_GET['page'], $pageIds)) {
            add_action('admin_enqueue_scripts', [$this, 'my_custom_rest_api_nonce']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_plugin_styles']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_plugin_scripts']);
        }
    }

    /** Registers the subscriber custom post type and taxonomy (called during plugin bootstrap). */
    public function registerPostsTypesAndTaxamonies()
    {
        Subscribers::registerPostType();
    }

    /**
     * Registers the Gutenberg subscription-form block, its editor script, and render callback.
     *
     * Localises available audiences for the block editor via enqueue_block_editor_assets,
     * filtering out system audiences (Unsubed, Testers).
     */
    public function registerBlocks(): void
    {
        // Register the script early (before register_block_type references it)
        wp_register_script(
            'mawiblah-subscription-form-block-js',
            MAWIBLAH_PLUGIN_URL . '/assets/js/block/subscription-form.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
            MAWIBLAH_VERSION
        );

        // Localize audiences on enqueue_block_editor_assets so the taxonomy
        // is already registered by Subscribers::init() when we query it.
        add_action('enqueue_block_editor_assets', function () {
            $systemAudiences = ['Unsubed', 'Testers'];
            $audiences = array_values(array_map(function ($a) {
                return ['hash' => $a->audienceHash, 'name' => $a->name];
            }, array_filter(Subscribers::getAllAudiences(), function ($a) use ($systemAudiences) {
                return !in_array($a->name, $systemAudiences, true);
            })));

            wp_localize_script('mawiblah-subscription-form-block-js', 'mawiblahSubscriptionBlock', [
                'audiences' => $audiences,
            ]);
        });

        register_block_type('mawiblah/subscription-form', [
            'attributes'      => [
                'audienceHashes' => ['type' => 'array',  'default' => []],
                'label'          => ['type' => 'string', 'default' => ''],
                'placeholder'    => ['type' => 'string', 'default' => ''],
                'buttonText'     => ['type' => 'string', 'default' => ''],
                'successMessage' => ['type' => 'string', 'default' => ''],
                'errorMessage'   => ['type' => 'string', 'default' => ''],
            ],
            'render_callback' => function (array $attrs): string {
                $hashes  = array_map('sanitize_text_field', $attrs['audienceHashes'] ?? []);
                $options = array_filter([
                    'label'          => sanitize_text_field($attrs['label'] ?? ''),
                    'placeholder'    => sanitize_text_field($attrs['placeholder'] ?? ''),
                    'buttonText'     => sanitize_text_field($attrs['buttonText'] ?? ''),
                    'successMessage' => sanitize_text_field($attrs['successMessage'] ?? ''),
                    'errorMessage'   => sanitize_text_field($attrs['errorMessage'] ?? ''),
                ]);

                wp_enqueue_style('mawiblah-subscription-form-css', MAWIBLAH_PLUGIN_URL . '/assets/css/subscription-form.css', [], MAWIBLAH_VERSION);
                wp_enqueue_script('mawiblah-subscription-form-js', MAWIBLAH_PLUGIN_URL . '/assets/js/subscription-form.js', [], MAWIBLAH_VERSION, true);
                wp_localize_script('mawiblah-subscription-form-js', 'mawiblahSubscribeFormData', [
                    'restUrl'      => rest_url('mawiblah/v1/subscribe'),
                    'errorMessage' => __('Something went wrong. Please try again.', 'mawiblah'),
                ]);

                return SubscriptionForm::renderForm($hashes, $options);
            },
            'editor_script'   => 'mawiblah-subscription-form-block-js',
        ]);
    }

    /**
     * Prepends the 'mawiblah' block category to the Gutenberg block inserter.
     *
     * @param array $categories Existing block categories.
     * @return array Modified categories array.
     */
    public function registerBlockCategories(array $categories): array
    {
        array_unshift($categories, [
            'slug'  => 'mawiblah',
            'title' => 'Mawiblah',
            'icon'  => 'email-alt2',
        ]);
        return $categories;
    }

    /** Injects the WP REST nonce into the page as a JS global variable (mawiblahNonce) for admin AJAX calls. */
    public function my_custom_rest_api_nonce()
    {

        $nonce = wp_create_nonce('wp_rest');
        $inline_script = '
        var mawiblahNonce = ' . json_encode(['mawiblahNonce' => $nonce]) . ';';

        wp_register_script( 'mawiblah-js', '',);
        wp_enqueue_script( 'mawiblah-js' );
        wp_add_inline_script( 'mawiblah-js', $inline_script);
    }

    /** Enqueues the plugin's main admin stylesheet on plugin pages. */
    public function enqueue_plugin_styles(): void
    {
        wp_enqueue_style('mawiblah-css', MAWIBLAH_PLUGIN_URL . '/assets/css/mawiblah.css', [], MAWIBLAH_VERSION);
        //wp_enqueue_style('mawiblah-lib-table-styles', MAWIBLAH_PLUGIN_URL . '/assets/lib/jquery.dataTables.css', [], MAWIBLAH_VERSION);
    }

    /** Enqueues the plugin's main admin JavaScript on plugin pages. */
    public function enqueue_plugin_scripts(): void
    {
        //wp_enqueue_script('mawiblah-lib-table-js', MAWIBLAH_PLUGIN_URL . '/assets/lib/jquery.dataTables.js.js', array('jquery'), MAWIBLAH_VERSION, true);
        wp_enqueue_script('mawiblah-main-js', MAWIBLAH_PLUGIN_URL . '/assets/js/mawiblah.js', ['mawiblah-js'], MAWIBLAH_VERSION);
    }

    /** Registers the Mawiblah top-level menu and all submenus in the WordPress admin sidebar. */
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
            'Campaigns',
            '<span class="dashicons dashicons-megaphone" style="font-size:16px;line-height:1.4;margin-right:6px;vertical-align:middle;"></span>Campaigns',
            'manage_options',
            self::MAWIBLAH_CAMPAIGNS,
            [$this, 'campaigns']
        );

        add_submenu_page(
            'mawiblah',
            'All Campaigns',
            '— All Campaigns',
            'manage_options',
            'edit.php?post_type=' . Campaigns::postType()
        );

        add_submenu_page(
            'mawiblah',
            'Add New Campaign',
            '— Add New',
            'manage_options',
            'post-new.php?post_type=' . Campaigns::postType()
        );

        add_submenu_page(
            'mawiblah',
            'Subscribers',
            '<span class="dashicons dashicons-groups" style="font-size:16px;line-height:1.4;margin-right:6px;vertical-align:middle;"></span>Subscribers',
            'manage_options',
            'edit.php?post_type=' . Subscribers::postType()
        );

        add_submenu_page(
            'mawiblah',
            'Add New Subscriber',
            '— Add New',
            'manage_options',
            'post-new.php?post_type=' . Subscribers::postType()
        );

        add_submenu_page(
            'mawiblah',
            'Audiences',
            '— Audiences',
            'manage_options',
            'edit-tags.php?taxonomy=' . Subscribers::postType() . '_category&post_type=' . Subscribers::postType()
        );

        add_submenu_page(
            'mawiblah',
            'Email Templates',
            '<span class="dashicons dashicons-email-alt" style="font-size:16px;line-height:1.4;margin-right:6px;vertical-align:middle;"></span>Email Templates',
            'manage_options',
            self::MAWIBLAH_EMAIL_TEMPLATES,
            [$this, 'emailTemplates']
        );

        add_submenu_page(
            'mawiblah',
            'Tests',
            '<span class="dashicons dashicons-yes-alt" style="font-size:16px;line-height:1.4;margin-right:6px;vertical-align:middle;"></span>Tests',
            'manage_options',
            self::MAWIBLAH_TESTS,
            [$this, 'tests']
        );

        add_submenu_page(
            'mawiblah',
            'Actions',
            '<span class="dashicons dashicons-admin-tools" style="font-size:16px;line-height:1.4;margin-right:6px;vertical-align:middle;"></span>Actions',
            'manage_options',
            self::MAWIBLAH_ACTIONS,
            [$this, 'actions']
        );

        add_submenu_page(
            'mawiblah',
            'Logs',
            '<span class="dashicons dashicons-list-view" style="font-size:16px;line-height:1.4;margin-right:6px;vertical-align:middle;"></span>Logs',
            'manage_options',
            self::MAWIBLAH_LOGS,
            [$this, 'logs']
        );

        add_submenu_page(
            'mawiblah',
            'Settings',
            '<span class="dashicons dashicons-admin-settings" style="font-size:16px;line-height:1.4;margin-right:6px;vertical-align:middle;"></span>Settings',
            'manage_options',
            self::MAWIBLAH_SETTINGS,
            [$this, 'settings']
        );

        add_submenu_page(
            'mawiblah',
            'Help',
            '<span class="dashicons dashicons-editor-help" style="font-size:16px;line-height:1.4;margin-right:6px;vertical-align:middle;"></span>Help',
            'manage_options',
            self::MAWIBLAH_HELP,
            [$this, 'help']
        );
    }

    /** Admin page callback: renders the email templates list. */
    public function emailTemplates() {
        Renderer::emailTemplates();
    }

    /** Admin page callback: renders the main plugin dashboard. */
    public function mawiblah()
    {
        Renderer::start();
    }

    /** Admin page callback: renders the campaigns list and action pages. */
    public function campaigns() {
        Renderer::campaigns();
    }

    /** Admin page callback: renders the test scenarios page. */
    public function tests() {
        Renderer::tests();
    }

    /** Admin page callback: renders the plugin settings page. */
    public function settings() {
        Renderer::settings();
    }

    /** Admin page callback: renders the actions/tools page. */
    public function actions() {
        Renderer::actions();
    }

    /** Admin page callback: renders the log viewer page. */
    public function logs() {
        Renderer::logs();
    }

    /** Admin page callback: renders the in-plugin help page. */
    public function help() {
        Renderer::help();
    }
}
