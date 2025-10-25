<?php

namespace Mawiblah;

class ShortCodes
{

    public static function register()
    {
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
    }

    public static function title()
    {
        $title = get_the_title();
        if ($title) {
            return $title;
        }
        return sprintf(__('Summary for the %s', 'mawiblah'), date("F"));
    }

    public static function content()
    {
        $content = get_the_content();
        if ($content) {
            return $content;
        }
        return __('This is our montly report to you, hoepfully you will find something usefull', 'mawiblah');
    }

    public static function websiteUrl()
    {
        return get_site_url();
    }

    public static function logoAlt()
    {
        return get_bloginfo('name');
    }

    public static function logoSrc()
    {
        return get_custom_logo();
    }

    public static function socialProfiles()
    {
        return '<a href="https://www.facebook.com/">Facebook</a>';
    }

    public static function newestArticlesTitle()
    {
        return __('Check Out Our Latest Articles!', 'mawiblah');
    }

    public static function newestArticlesParagraph()
    {
        return __('Discover newest articles and printables for parents and kids.', 'mawiblah');
    }

    public static function newestArticles()
    {

        $args = [
            'post_type' => 'post',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $posts = get_posts($args);
        $output = '';
        foreach ($posts as $post) {
            $output .= '<li><p><a href="' . get_the_permalink($post->ID) . '?utm_source=email&utm_medium=email&utm_campaign=monthly-email" target="_blank">' . $post->post_title . '</a></p></li>';
        }
        return $output;
    }

    public static function endTitle()
    {
        return __('Thank you for reading!', 'mawiblah');
    }

    public static function endContent()
    {
        return __('We hope you enjoyed our monthly newsletter. If you have any questions or suggestions, feel free to contact us.', 'mawiblah');
    }

    public static function unsubscribe()
    {

        $url = get_site_url() . Helpers::trackingParams([
            'unsubscribe' => '{email}',
            'campaignId' => '{campaignId}'
        ]);
        $linkText = __('Unsubscribe', 'mawiblah');

        return sprintf('<a href="%s">%s</a>', $url, $linkText);
    }


}
