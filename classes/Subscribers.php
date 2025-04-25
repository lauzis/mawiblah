<?php

namespace Mawiblah;

class Subscribers
{

    // exampel http://gudlenieks.test/?utm_source=email&utm_medium=email&utm_campaign=monthly-email&mawiblahId=%7BmawiblahId%7D&unsubscribe=%7Bemail%7D
    public static function init()
    {
        self::registerPostType();
        add_action('add_meta_boxes', [self::class, 'addMetaBoxes']);
    }

    public static function addMetaBoxes()
    {

        add_meta_box(
            'mawiblahMetadata', // Unique ID for the meta box
            'Metadata',               // Title of the meta box
            [self::class, 'renderMetaData'], // Callback function to render the content
            self::postType(),         // Post type where the meta box will appear
            'normal',                 // Context (normal, side, advanced)
            'default'                 // Priority
        );
    }

    public static function renderMetaData($post)
    {
        // Add your custom output here
        $metadata = self::getMetaData($post->ID);
        echo '<div>';
        echo '<h3>Meta data</h3>';
        foreach ($metadata as $key => $value) {
            echo '<p><strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '</p>';
        }
        echo '</div>';
    }

    public static function postType()
    {
        return MAWIBLAH_POST_TYPE_PREFIX . 'subscriber';
    }

    public static function registerPostType()
    {

        $labels = [
            'name' => _x('Subscribers', 'Post type general name', 'mawiblah'),
            'singular_name' => _x('Subscriber', 'Post type singular name', 'mawiblah'),
            'menu_name' => _x('Subscribers', 'Admin Menu text', 'mawiblah'),
            'name_admin_bar' => _x('Subscriber', 'Add New on Toolbar', 'mawiblah'),
            'add_new' => __('Add New', 'mawiblah'),
            'add_new_item' => __('Add New Subscriber', 'mawiblah'),
            'new_item' => __('New Subscriber', 'mawiblah'),
            'edit_item' => __('Edit Subscriber', 'mawiblah'),
            'view_item' => __('View Subscriber', 'mawiblah'),
            'all_items' => __('All Subscribers', 'mawiblah'),
            'search_items' => __('Search Subscribers', 'mawiblah'),
            'parent_item_colon' => __('Parent Subscribers:', 'mawiblah'),
            'not_found' => __('No subscribers found.', 'mawiblah'),
            'not_found_in_trash' => __('No subscribers found in Trash.', 'mawiblah'),
            'featured_image' => _x('Subscriber Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'mawiblah'),
            'set_featured_image' => _x('Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'mawiblah'),
            'remove_featured_image' => _x('Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'mawiblah'),
            'use_featured_image' => _x('Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'mawiblah'),
            'archives' => _x('Subscriber archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'mawiblah'),
            'insert_into_item' => _x('Insert into subscriber', 'Overrides the “Insert into post”/“Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'mawiblah'),
            'uploaded_to_this_item' => _x('Uploaded to this subscriber', 'Overrides the “Uploaded to this post”/“Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'mawiblah'),
            'filter_items_list' => _x('Filter subscribers list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/“Filter pages list”. Added in 4.4', 'mawiblah'),
            'items_list_navigation' => _x('Subscribers list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/“Pages list navigation”. Added in 4.4', 'mawiblah'),
            'items_list' => _x('Subscribers list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/“Pages list”. Added in 4.4', 'mawiblah'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'mawiblah-subscriber'],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => ['title'],
        ];

        register_post_type(Subscribers::postType(), $args);

        // Register custom taxonomy
        $taxonomy_labels = [
            'name' => _x('Subscriber Audiences', 'taxonomy general name', 'mawiblah'),
            'singular_name' => _x('Subscriber Audience', 'taxonomy singular name', 'mawiblah'),
            'search_items' => __('Search Subscriber Audiences', 'mawiblah'),
            'all_items' => __('All Subscriber Audiences', 'mawiblah'),
            'parent_item' => __('Parent Subscriber Audience', 'mawiblah'),
            'parent_item_colon' => __('Parent Subscriber Audience:', 'mawiblah'),
            'edit_item' => __('Edit Subscriber Audience', 'mawiblah'),
            'update_item' => __('Update Subscriber Audience', 'mawiblah'),
            'add_new_item' => __('Add New Subscriber Audience', 'mawiblah'),
            'new_item_name' => __('New Subscriber Category Name', 'mawiblah'),
            'menu_name' => __('Subscriber Audiences', 'mawiblah'),
        ];

        $taxonomy_args = [
            'hierarchical' => true,
            'labels' => $taxonomy_labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'subscriber-category'],
        ];

        register_taxonomy(Subscribers::postType() . '_category', [Subscribers::postType()], $taxonomy_args);
    }

    public static function getMetaKeys()
    {
        return [
            'email' => [
                'key' => 'email',
                'default_value' => ''
            ],
            'subscriberId' => [
                'key' => 'subscriberId',
                'default_value' => ''
            ],
            'unsubToken' => [
                'key' => 'unsubToken',
                'default_value' => ''
            ],
            'unsubed' => [
                'key' => 'unsubed',
                'default_value' => ''
            ],
            'activity' => [
                'key' => 'activity',
                'default_value' => ''
            ],
            'activityTotal' => [
                'key' => 'activityTotal',
                'default_value' => ''
            ],
            'lastInteraction' => [
                'key' => 'lastInteraction',
                'default_value' => ''
            ]
        ];
    }

    public static function appendMeta($post)
    {

        $post->id = $post->ID;
        $post->email = get_post_meta($post->id, 'email', true);
        $post->subscriberId = get_post_meta($post->id, 'subscriberId', true);
        $post->unsubToken = get_post_meta($post->id, 'unsubToken', true);
        $post->unsubed = get_post_meta($post->id, 'unsubed', true);
        $post->activity = get_post_meta($post->id, 'activity', true) ?? 0;
        $post->activityTotal = get_post_meta($post->id, 'activityTotal', true) ?? 0;
        $post->lastInteraction = get_post_meta($post->id, 'lastInteraction', true) ?? date("Y-m-d H:i:s", 0);

        if (!$post->subscriberId) {
            update_post_meta($post->id, 'subscriberId', md5($post->id));
        }

        $post->audiences = get_the_terms($post->ID, Subscribers::postType() . '_category');

        return $post;
    }

    public static function getMetaData($postId)
    {
        $metaKeys = self::getMetaKeys();
        $metaData = [];
        foreach ($metaKeys as $key => $value) {
            $metaData[$key] = get_post_meta($postId, $key, true);
        }
        return $metaData;
    }

    public static function getSubscriber($email): object|null
    {
        $subscriber = get_posts([
            'post_type' => self::postType(),
            'meta_query' => [
                [
                    'key' => 'email',
                    'value' => $email,
                    'compare' => '='
                ]
            ]
        ]);;

        if (count($subscriber) > 0) {
            return self::appendMeta($subscriber[0]);
        }

        return null;
    }

    public static function addSubscriber(string $email, string $subscriberId = ""): object
    {

        // Prepare the post data
        $post_data = [
            'post_title' => $email,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => self::postType(), // Replace with your custom post type
        ];

        // Insert the post into the database
        $post_id = wp_insert_post($post_data);

        if (!is_wp_error($post_id)) {
            // Save the email as a meta field
            update_post_meta($post_id, 'email', $email);
            if (!empty($subscriberId)) {
                update_post_meta($post_id, 'subscriberId', $subscriberId);
            }
        }

        return self::getSubscriber($email);
    }

    public static function getUnsubToken(string|int $subId, string $subEmail = "")
    {
        $id = $subId;
        if (!$id) {
            $sub = self::getSubscriber($subEmail);
            if ($sub) {
                $id = $sub['ID'];
            }
        }
        $unsubToken = get_post_meta($id, 'unsubToken', true);
        if (!$unsubToken) {
            $unsubToken = md5($subId . $subEmail);
            add_post_meta($subId, 'unsubToken', $unsubToken);
        }
        return $unsubToken;

    }

    public static function unsub($email, $unsubToken, $feedback)
    {
        $subscriber = self::getSubscriber($email);
        if ($subscriber->unsubToken === $unsubToken) {
            update_post_meta($subscriber->id, 'unsubed', true);
            update_post_meta($subscriber->id, 'unsubed_feedback', $feedback);
            return true;
        } else {
            return false;
        }
    }

    public static function validateAudiences(array $audiences): bool
    {
        $gravityForms = GravityForms::getArrayOfGravityForms();
        $validAudiences = [];

        foreach ($audiences as $key => $audience) {

            if (substr_count($audience, "GF__")) {
                $audience = str_replace("GF__", "", $audience);
                foreach ($gravityForms as $gravityForm) {
                    $gravityFormId = $gravityForm['id'];

                    if (intval($audience) === $gravityFormId) {
                        $validAudiences[] = $audience;
                    }
                }
            }
        }

        if (count($validAudiences) === count($audiences)) {
            return true;
        }

        return false;
    }

    public static function getGFAudience($gravityFormId, $title = "", $description = ""): object|null
    {
        $listOfTaxanomies = get_terms([
            'taxonomy' => Subscribers::postType() . '_category',
            'hide_empty' => false,
        ]);

        if (!empty($listOfTaxanomies)) {
            foreach ($listOfTaxanomies as $taxanomy) {
                $gfId = get_term_meta($taxanomy->term_id, 'gravityFormsId', true);
                if ((int)$gfId === (int)$gravityFormId) {
                    return (object)$taxanomy;
                }
            }
        }

        if (!empty($title)) {

            return (object)self::createAudience($title, $description, $gravityFormId);
        }

        return null;
    }

    // to do check this return types...
    public static function createAudience($title, $description, $gravityFormsId): array|object|null
    {
        $term = wp_insert_term($title, Subscribers::postType() . '_category', ['description' => $description]);
        if (!is_wp_error($term)) {
            add_term_meta($term['term_id'], 'gravityFormsId', $gravityFormsId);
        }
        return $term;
    }

    public static function addSubscriberToAudience($subscriberId, $audienceId)
    {
        wp_set_post_terms($subscriberId, $audienceId, Subscribers::postType() . '_category', true);
    }

    public static function checkMailchimpUnsubedAudience($email): bool
    {
        // load file
        $mailchimpUnsubFile = MAWIBLAH_PLUGIN_DIR . '/mailchimp_audience/unsubed.csv';

        if (!file_exists($mailchimpUnsubFile)) {
            return false;
        }

        $file = fopen($mailchimpUnsubFile, "r");

        while (($data = fgetcsv($file)) !== FALSE) {
            if ($data[0] === $email) {
                return true;
            }
        }
        return false;
    }

    public static function unsubedAudience()
    {
        $term = get_term_by('name', 'Unsubed', Subscribers::postType() . '_category');
        if (!$term) {
            $term = wp_insert_term('Unsubed', Subscribers::postType() . '_category');
        }
        return $term;
    }

    public static function isEmailSent(int $subscriberId, int $campaignId): bool
    {
        $sent = get_post_meta($subscriberId, 'sent_' . $campaignId, true);

        return (bool)$sent;
    }

    public static function isTester($subscriber): bool
    {
        $testerFlag = get_post_meta($subscriber->id, 'tester', true);
        $testerCategory = self::testerAudience();
        $audiences = $subscriber->audiences;

        $inTesterAudience = false;

        foreach ($audiences as $audience) {
            if ($audience->term_id === $testerCategory->term_id) {
                $inTesterAudience = true;
            }
        }
        return $testerFlag || $inTesterAudience;
    }

    public static function testerAudience()
    {
        $term = get_term_by('name', 'Testers', Subscribers::postType() . '_category');
        if (!$term) {
            $term = wp_insert_term('Testers', Subscribers::postType() . '_category');
        }
        return $term;
    }

    public static function updateLastInteraction(int $subscriberId, string|null $interactionDate = null): void
    {
        $interactionDate = $interactionDate ?? date("Y-m-d H:i:s");
        update_post_meta($subscriberId, 'lastInteraction', $interactionDate);
    }

    public static function sendingEmail(int $subscriberId, int $campaignId): void
    {
        update_post_meta($subscriberId, 'sent_' . $campaignId, 'sending');
    }

    public static function sentEmail(int $subscriberId, int $campaignId): void
    {
        update_post_meta($subscriberId, 'sent_' . $campaignId, 'sent');
        self::updateLastInteraction($subscriberId);
    }

    public static function sentEmailFailed(int $subscriberId, int $campaignId): void
    {
        update_post_meta($subscriberId, 'sent_' . $campaignId, 'failed');
    }

    public static function getSubscriberBySubscriberId($subscriberId)
    {

        $postsByMeta = get_posts([
            'post_type' => self::postType(),
            'meta_query' => [
                [
                    'key' => 'subscriberId',
                    'value' => $subscriberId,
                    'compare' => '='
                ]
            ]
        ]);

        return self::appendMeta($postsByMeta[0]);
    }

    public static function linksClicked($subscriberId)
    {
        $subscriber = self::getSubscriberBySubscriberId($subscriberId);
        $subscriber->activityTotal++;
        update_post_meta($subscriber->id, 'activityTotal', $subscriber->activityTotal);

        // lets check if visit is in session already
        // check if session started
        if (!session_id()) {
            session_start();
        }
        if (isset($_SESSION['campaignId']) && isset($_SESSION['subscriberId'])) {
            return $subscriber->activity;
        }

        $subscriber->activity++;
        update_post_meta($subscriber->id, 'activity', $subscriber->activity);
        return $subscriber->activity;
    }

    public static function getLastSyncDate($audienceId, $date=null)
    {
        $lastSyncDate = get_term_meta($audienceId, 'lastSyncDate', true);
        if (!$lastSyncDate && $date) {
            $lastSyncDate = date("Y-m-d H:i:s");
            add_term_meta($audienceId, 'lastSyncDate', $lastSyncDate);
        }
        return $lastSyncDate;
    }

    public static function updateLastSyncDate($audienceId, $date)
    {
        update_term_meta($audienceId, 'lastSyncDate', $date);
    }
}
