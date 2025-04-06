<?php

namespace Mawiblah;

class Logs
{

    // exampel http://gudlenieks.test/?utm_source=email&utm_medium=email&utm_campaign=monthly-email&mawiblahId=%7BmawiblahId%7D&unsubscribe=%7Bemail%7D
    public static function init()
    {
        self::registerPostType();
    }

    public static function enabled()
    {
        return get_option('mawiblah-debug', false) === 'enable-db-log';
    }

    public static function postType()
    {
        return MAWIBLAH_POST_TYPE_PREFIX . 'log';
    }

    public static function registerPostType()
    {

        $labels = [
            'name' => _x('Logs', 'Post type general name', 'mawiblah'),
            'singular_name' => _x('Log', 'Post type singular name', 'mawiblah'),
            'menu_name' => _x('Logs', 'Admin Menu text', 'mawiblah'),
            'name_admin_bar' => _x('Log', 'Add New on Toolbar', 'mawiblah'),
            'add_new' => __('Add New', 'mawiblah'),
            'add_new_item' => __('Add New Log', 'mawiblah'),
            'new_item' => __('New Log', 'mawiblah'),
            'edit_item' => __('Edit Log', 'mawiblah'),
            'view_item' => __('View Log', 'mawiblah'),
            'all_items' => __('All Logs', 'mawiblah'),
            'search_items' => __('Search Logs', 'mawiblah'),
            'parent_item_colon' => __('Parent Logs:', 'mawiblah'),
            'not_found' => __('No Logs found.', 'mawiblah'),
            'not_found_in_trash' => __('No Logs found in Trash.', 'mawiblah'),
            'featured_image' => _x('Log Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'mawiblah'),
            'set_featured_image' => _x('Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'mawiblah'),
            'remove_featured_image' => _x('Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'mawiblah'),
            'use_featured_image' => _x('Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'mawiblah'),
            'archives' => _x('Log archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'mawiblah'),
            'insert_into_item' => _x('Insert into Log', 'Overrides the “Insert into post”/“Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'mawiblah'),
            'uploaded_to_this_item' => _x('Uploaded to this Log', 'Overrides the “Uploaded to this post”/“Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'mawiblah'),
            'filter_items_list' => _x('Filter Logs list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/“Filter pages list”. Added in 4.4', 'mawiblah'),
            'items_list_navigation' => _x('Logs list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/“Pages list navigation”. Added in 4.4', 'mawiblah'),
            'items_list' => _x('Logs list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/“Pages list”. Added in 4.4', 'mawiblah'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'mawiblah-log'],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => ['title', 'editor'],
        ];

        register_post_type(Logs::postType(), $args);
    }

    public static function appendMeta($post)
    {
        $post->id = $post->ID;
        $post->email = get_post_meta($post->id, 'email', true);
        $post->logId = get_post_meta($post->id, 'logId', true);
        $post->unsubToken = get_post_meta($post->id, 'unsubToken', true);
        $post->unsubed = get_post_meta($post->id, 'unsubed', true);
        $post->activity = get_post_meta($post->id, 'activity', true) ?? 0;
        $post->activityTotal = get_post_meta($post->id, 'activityTotal', true) ?? 0;

        if (!$post->logId) {
            update_post_meta($post->id, 'logId', md5($post->id));
        }

        $post->audiences = get_the_terms($post->ID, Logs::postType() . '_category');

        return $post;
    }

    public static function getLog($id): object|null
    {
        $logById = get_post($id);
        if ($logById) {
            return self::appendMeta($logById);
        }
        return null;
    }

    public static function addLog(string $action, string $message = "", $additionalObjects = []): object|false
    {
        if (!self::enabled()) {
            return false;
        }

        foreach ($additionalObjects as $key => $object) {
            $message .= "<h2>$key</h2>";
            $message .= "<pre>";
            $message .= "\n" . print_r($object, true);
            $message .= "</pre>";
        }

        $post_data = [
            'post_title' => $action,
            'post_content' => $message,
            'post_status' => 'publish',
            'post_type' => self::postType(), // Replace with your custom post type
        ];

        $post_id = wp_insert_post($post_data);

        if ($post_id) {
            return self::getLog($post_id);
        }

        return false;
    }
}
