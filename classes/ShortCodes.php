<?php

namespace Mawiblah;

class ShortCodes
{

    /** Registers all Mawiblah shortcodes with WordPress. */
    public static function register()
    {
        add_shortcode('mawiblah_subscribe_form', [ShortCodes::class, 'subscribeForm']);
        add_shortcode('mawiblah_title', [ShortCodes::class, 'title']);
        add_shortcode('mawiblah_content', [ShortCodes::class, 'content']);

        add_shortcode('mawiblah_website_url', [ShortCodes::class, 'websiteUrl']);
        add_shortcode('mawiblah_logo_alt', [ShortCodes::class, 'logoAlt']);
        add_shortcode('mawiblah_logo_src', [ShortCodes::class, 'logoSrc']);

        add_shortcode('mawiblah_social_profiles', [ShortCodes::class, 'socialProfiles']);

        add_shortcode('mawiblah_newest_articles_title', [ShortCodes::class, 'newestArticlesTitle']);
        add_shortcode('mawiblah_newest_articles_paragraph', [ShortCodes::class, 'newestArticlesParagraph']);
        add_shortcode('mawiblah_newest_articles', [ShortCodes::class, 'newestArticles']);


        add_shortcode('mawiblah_end_title', [ShortCodes::class, 'endTitle']);
        add_shortcode('mawiblah_end_content', [ShortCodes::class, 'endContent']);


        add_shortcode('mawiblah_unsubscribe', [ShortCodes::class, 'unsubscribe']);

        add_shortcode('mawiblah_new_posts_since_last_sent', [ShortCodes::class, 'weHaveNewPostsSinceLastSentOut']);
    }

    /** Returns the current post title, falling back to a month-based default. */
    public static function title()
    {
        $title = get_the_title();
        if ($title) {
            return $title;
        }
        return sprintf(__('Summary for the %s', 'mawiblah'), date("F"));
    }

    /** Returns the current post content, falling back to a default monthly newsletter message. */
    public static function content()
    {
        $content = get_the_content();
        if ($content) {
            return $content;
        }
        return __('This is our montly report to you, hoepfully you will find something usefull', 'mawiblah');
    }

    /** Returns the site URL for use in email templates. */
    public static function websiteUrl()
    {
        return get_site_url();
    }

    /** Returns the site name for use as the logo alt text in email templates. */
    public static function logoAlt()
    {
        return get_bloginfo('name');
    }

    /** Returns the custom logo HTML for use in email templates. */
    public static function logoSrc()
    {
        return get_custom_logo();
    }

    /** Returns social profile links HTML for use in email templates. */
    public static function socialProfiles()
    {
        return '<a href="https://www.facebook.com/">Facebook</a>';
    }

    /** Returns the default heading for the newest articles section in email templates. */
    public static function newestArticlesTitle()
    {
        return __('Check Out Our Latest Articles!', 'mawiblah');
    }

    /** Returns the default introductory paragraph for the newest articles section. */
    public static function newestArticlesParagraph()
    {
        return __('Discover newest articles and printables for parents and kids.', 'mawiblah');
    }

    /**
     * Returns an HTML list of the most recent posts with campaign tracking links appended.
     *
     * @param array $atts Shortcode attributes; supports 'count' (default 5).
     * @return string HTML <li> items for each recent post.
     */
    public static function newestArticles($atts)
    {
        $atts = shortcode_atts(
            array(
                'count' => 5,
            ), $atts, 'mawiblah_newest_articles'
        );

        $args = [
            'post_type' => 'post',
            'posts_per_page' => $atts['count'],
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $posts = get_posts($args);
        $output = '';
        foreach ($posts as $post) {
            $trackingParams = Helpers::trackingParams(['campaign' => '{campaignHash}']);
            $output .= '<li><p><a href="' . get_the_permalink($post->ID) . $trackingParams . '" target="_blank">' . $post->post_title . '</a></p></li>';
        }
        return $output;
    }

    /** Returns the default sign-off heading for email templates. */
    public static function endTitle()
    {
        return __('Thank you for reading!', 'mawiblah');
    }

    /** Returns the default sign-off paragraph for email templates. */
    public static function endContent()
    {
        return __('We hope you enjoyed our monthly newsletter. If you have any questions or suggestions, feel free to contact us.', 'mawiblah');
    }

    /**
     * Renders the subscription form HTML for the [mawiblah_subscribe_form] shortcode.
     *
     * Enqueues the form CSS and JS, then delegates rendering to SubscriptionForm::renderForm().
     *
     * @param array $atts Shortcode attributes: audiences, label, placeholder, button, success, error.
     * @return string Rendered form HTML.
     */
    public static function subscribeForm($atts): string
    {
        $atts = shortcode_atts([
            'audiences'   => '',
            'label'       => '',
            'placeholder' => '',
            'button'      => '',
            'success'     => '',
            'error'       => '',
        ], $atts, 'mawiblah_subscribe_form');

        $hashes = array_values(array_filter(array_map('sanitize_text_field', array_map('trim', explode(',', $atts['audiences'])))));

        $options = array_filter([
            'label'          => sanitize_text_field($atts['label']),
            'placeholder'    => sanitize_text_field($atts['placeholder']),
            'buttonText'     => sanitize_text_field($atts['button']),
            'successMessage' => sanitize_text_field($atts['success']),
            'errorMessage'   => sanitize_text_field($atts['error']),
        ]);

        wp_enqueue_style('mawiblah-subscription-form-css', MAWIBLAH_PLUGIN_URL . '/assets/css/subscription-form.css', [], MAWIBLAH_VERSION);
        wp_enqueue_script('mawiblah-subscription-form-js', MAWIBLAH_PLUGIN_URL . '/assets/js/subscription-form.js', [], MAWIBLAH_VERSION, true);
        wp_localize_script('mawiblah-subscription-form-js', 'mawiblahSubscribeFormData', [
            'restUrl'      => rest_url('mawiblah/v1/subscribe'),
            'errorMessage' => __('Something went wrong. Please try again.', 'mawiblah'),
        ]);

        return SubscriptionForm::renderForm($hashes, $options);
    }

    /**
     * Send-condition shortcode: returns a non-empty string if any posts were published
     * after the campaign's last send, or empty string if none (→ skip the scheduled send).
     *
     * Usage: [mawiblah_new_posts_since_last_sent campaign_id="123"]
     *
     * @param array $atts Shortcode attributes; requires 'campaign_id'.
     * @return string Non-empty string when new posts exist, empty string otherwise.
     */
    public static function weHaveNewPostsSinceLastSentOut($atts): string
    {
        $atts = shortcode_atts(['campaign_id' => 0], $atts, 'mawiblah_new_posts_since_last_sent');

        $campaignId = (int) $atts['campaign_id'];
        if (!$campaignId) {
            return '';
        }

        $campaign = Campaigns::getCampaignById($campaignId);
        if (!$campaign || empty($campaign->campaignFinished)) {
            // No previous send recorded — allow the send so the first occurrence always goes out.
            return 'yes';
        }

        $since = date('Y-m-d H:i:s', (int) $campaign->campaignFinished);

        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'date_query'     => [['after' => $since, 'inclusive' => false]],
        ]);

        return !empty($posts) ? 'yes' : '';
    }

    /** Returns an unsubscribe anchor tag with subscriber and campaign tracking parameters pre-filled as template placeholders. */
    public static function unsubscribe()
    {

        $url = get_site_url() . Helpers::trackingParams([
            'unsubscribe' => '{email}',
            'campaign' => '{campaignHash}',
        ]);
        $linkText = __('Unsubscribe', 'mawiblah');

        return sprintf('<a href="%s">%s</a>', $url, $linkText);
    }


}
