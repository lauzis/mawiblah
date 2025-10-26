<?php

namespace Mawiblah;

class Campaigns
{

    // exampel http://gudlenieks.test/?utm_source=email&utm_medium=email&utm_campaign=monthly-email&mawiblahId=%7BmawiblahId%7D&unsubscribe=%7Bemail%7D
    public static function init()
    {
        self::registerPostType();
    }

    public static function deleteCampaign($campaignId)
    {
        return wp_delete_post($campaignId);
    }

    public static function isUnique($title): bool
    {
        return self::getCampaign($title) == null;
    }

    public static function validateCampaign(string $title, string $subject, array $audiences, string $template): bool
    {
        if (empty($title)) {
            print("Empty title");
            return false;
        }

        if (empty($audiences)) {
            print("Empty audiences");
            return false;
        }

        if (empty($template)) {
            print("Empty template");
            return false;
        }

        if (empty($subject)) {
            print("Empty subject");
            return false;
        }

        if (!self::validateEmailTemplate($template)) {
            print("Could nog get template $template");
            return false;
        }

        if (!Subscribers::validateAudiences($audiences)) {
            print("Audiences not found audience");
            return false;
        }

        return true;
    }

    public static function validateEmailTemplate(string $emailTemplate): bool
    {
        $template = Templates::getEmailTemplateByName($emailTemplate);
        if ($template) {
            return true;
        }

        return false;
    }

    public static function postType()
    {
        return MAWIBLAH_POST_TYPE_PREFIX . 'campaigns';
    }

    public static function registerPostType()
    {

        $labels = [
            'name' => _x('Campaigns', 'Post type general name', 'mawiblah'),
            'singular_name' => _x('Campaign', 'Post type singular name', 'mawiblah'),
            'menu_name' => _x('Campaigns', 'Admin Menu text', 'mawiblah'),
            'name_admin_bar' => _x('Campaign', 'Add New on Toolbar', 'mawiblah'),
            'add_new' => __('Add New', 'mawiblah'),
            'add_new_item' => __('Add New Campaign', 'mawiblah'),
            'new_item' => __('New Campaign', 'mawiblah'),
            'edit_item' => __('Edit Campaign', 'mawiblah'),
            'view_item' => __('View Campaign', 'mawiblah'),
            'all_items' => __('All Campaigns', 'mawiblah'),
            'search_items' => __('Search Campaigns', 'mawiblah'),
            'parent_item_colon' => __('Parent Campaigns:', 'mawiblah'),
            'not_found' => __('No campaigns found.', 'mawiblah'),
            'not_found_in_trash' => __('No campaigns found in Trash.', 'mawiblah'),
            'featured_image' => _x('Campaign Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'mawiblah'),
            'set_featured_image' => _x('Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'mawiblah'),
            'remove_featured_image' => _x('Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'mawiblah'),
            'use_featured_image' => _x('Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'mawiblah'),
            'archives' => _x('Campaign archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'mawiblah'),
            'insert_into_item' => _x('Insert into campaign', 'Overrides the “Insert into post”/“Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'mawiblah'),
            'uploaded_to_this_item' => _x('Uploaded to this campaign', 'Overrides the “Uploaded to this post”/“Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'mawiblah'),
            'filter_items_list' => _x('Filter campaigns list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/“Filter pages list”. Added in 4.4', 'mawiblah'),
            'items_list_navigation' => _x('Campaigns list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/“Pages list navigation”. Added in 4.4', 'mawiblah'),
            'items_list' => _x('Campaigns list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/“Pages list”. Added in 4.4', 'mawiblah'),
        ];

        $args = [
            'labels' => $labels,
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'mawiblah-campaigns'],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => null,
            'supports' => ['title', 'editor'],
        ];

        register_post_type(Campaigns::postType(), $args);
    }

    public static function appendMeta($post)
    {
        $post->id = $post->ID ?? $post->id;
        $post->contentTitle = get_post_meta($post->id, 'contentTitle', true);
        $post->subject = get_post_meta($post->id, 'subject', true);
        $post->audiences = get_post_meta($post->id, 'audiences', true);
        $post->template = get_post_meta($post->id, 'template', true);
        $post->status = get_post_meta($post->id, 'status', true);
        $post->linksClicked = get_post_meta($post->id, 'linksClicked', true) ?? 0;
        $post->linksClickedTotal = get_post_meta($post->id, 'linksClickedTotal', true) ?? 0;
        $post->uniqueUserClicks = get_post_meta($post->id, 'uniqueUserClicks', true) ?? 0;
        $post->links = json_decode(get_post_meta($post->id, 'links', true) ?? '[]', true);
        $post->campaignId = get_post_meta($post->id, 'campaignId', true);

        $post->emailsSend = get_post_meta($post->id, 'emailsSend', true);
        $post->emailsFailed = get_post_meta($post->id, 'emailsFailed', true);
        $post->emailsSkipped = get_post_meta($post->id, 'emailsSkipped', true);
        $post->emailsUnsubed = get_post_meta($post->id, 'emailsUnsubed', true);
        $post->emailsNewlyUnsubed = get_post_meta($post->id, 'emailsNewlyUnsubed', true) ?? 0;

        $post->testStarted = get_post_meta($post->id, 'testStarted', true) ?? false;
        $post->testFinished = get_post_meta($post->id, 'testFinished', true) ?? false;
        $post->testApproved = get_post_meta($post->id, 'testApproved', true) ?? false;

        $post->campaignStarted = get_post_meta($post->id, 'campaignStarted', true) ?? false;
        $post->campaignFinished = get_post_meta($post->id, 'campaignFinished', true) ?? false;

        if (!$post->campaignId) {
            $post->campaignId = md5($post->id);
            update_post_meta($post->id, 'campaignId', $post->campaignId);
        }

        return $post;
    }

    public static function getCampaignById($id): object|null
    {
        $wpPost = get_post($id);

        if (!$wpPost) {
            return null;
        }

        $campaign = (object)[
            'id' => $wpPost->ID,
            'post_title' => $wpPost->post_title,
            'post_content' => $wpPost->post_content,
            'post_status' => $wpPost->post_status,
        ];

        return self::appendMeta($campaign);
    }

    public static function getCampaignByCampaignId($campaignId): object|null
    {
        $postByMeta = get_posts([
            'post_type' => self::postType(),
            'meta_key' => 'campaignId',
            'meta_value' => $campaignId,
            'posts_per_page' => 1,
        ]);

        return self::appendMeta((object)$postByMeta[0]);
    }

    public static function getCampaign($title): object|null
    {
        $wpQuery = new \WP_Query([
            'post_type' => self::postType(),
            'posts_per_page' => 1,
            'title' => $title,
        ]);

        $campaigns = [];
        if ($wpQuery->have_posts()) {
            while ($wpQuery->have_posts()) {
                $wpQuery->the_post();
                $campaigns[] = (object)[
                    'id' => get_the_ID(),
                    'post_title' => get_the_title(),
                    'post_content' => get_the_content(),
                    'post_status' => get_post_status(),
                ];
            }
            wp_reset_postdata();
        }
        if (count($campaigns) > 0) {
            return self::appendMeta($campaigns[0]);
        }

        return null;
    }

    public static function getCampaigns(): array
    {
        $wpQuery = new \WP_Query([
            'post_type' => self::postType(),
            'posts_per_page' => -1,
        ]);

        $campaigns = [];
        if ($wpQuery->have_posts()) {
            while ($wpQuery->have_posts()) {
                $wpQuery->the_post();

                $campaign = (object)[
                    'id' => get_the_ID(),
                    'post_title' => get_the_title(),
                    'post_content' => get_the_content(),
                    'post_status' => get_post_status(),
                ];
                $campaign = self::appendMeta($campaign);
                $campaigns[] = $campaign;
            }
            wp_reset_postdata();
        }

        return $campaigns;
    }

    public static function getLastCampaigns(int $limit = 5): array
    {
        $wpQuery = new \WP_Query([
            'post_type' => self::postType(),
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        $campaigns = [];
        if ($wpQuery->have_posts()) {
            while ($wpQuery->have_posts()) {
                $wpQuery->the_post();

                $campaign = (object)[
                    'id' => get_the_ID(),
                    'post_title' => get_the_title(),
                    'post_content' => get_the_content(),
                    'post_status' => get_post_status(),
                ];
                $campaign = self::appendMeta($campaign);
                $campaigns[] = $campaign;
            }
            wp_reset_postdata();
        }

        return $campaigns;
    }

    public static function addCampaign(string $title, string $subject, string $contentTitle, string $content, array $audiences, string $template): int
    {

        // Prepare the post data
        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => self::postType(),
        ];

        // Insert the post into the database
        $post_id = wp_insert_post($post_data);

        if (!is_wp_error($post_id)) {
            // Save the email as a meta field
            update_post_meta($post_id, 'template', $template);
            update_post_meta($post_id, 'audiences', $audiences);
            update_post_meta($post_id, 'subject', $subject);
            update_post_meta($post_id, 'contentTitle', $contentTitle);
            update_post_meta($post_id, 'emailsNewlyUnsubed', 0);

        }

        return $post_id;
    }


    public static function getArrayOfCampaigns(): array
    {
        $args = [
            'post_type' => 'campaigns',
            'posts_per_page' => -1,
        ];

        $query = new \WP_Query($args);
        $campaigns = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $campaigns[] = [
                    'ID' => get_the_ID(),
                    'title' => get_the_title(),
                    'content' => get_the_content(),
                    'status' => get_post_meta(get_the_ID(), 'status', true),
                ];
            }
            wp_reset_postdata();
        }

        return $campaigns;
    }

    public static function getArrayOfCampaignsByName(string $name): array
    {
        $args = [
            'post_type' => 'campaigns',
            'posts_per_page' => -1,
            'post_title' => $name,
        ];

        $query = new \WP_Query($args);
        $campaigns = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $campaigns[] = [
                    'ID' => get_the_ID(),
                    'title' => get_the_title(),
                    'content' => get_the_content(),
                ];
            }
            wp_reset_postdata();
        }

        return $campaigns;
    }

    public static function deleteCampaignByName(string $name)
    {

        $args = [
            'post_type' => 'campaigns',
            'posts_per_page' => -1,
            'post_title' => $name,
        ];

        $query = new \WP_Query($args);
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                wp_delete_post(get_the_ID());
            }
            wp_reset_postdata();
        }
    }

    public static function lockTemplate($campaign, bool $testMode): string|bool
    {

        $campaignId = $campaign->id;
        update_post_meta($campaignId, 'status', 'sending-in-progress');
        // copy template

        $templateHTML = Templates::copyTemplate($campaignId, $testMode);

        if ($templateHTML=== false) {
            return false;
        }

        $templateHTML = do_shortcode($templateHTML);
        $templateHTML = str_replace('[gdlnks_newsletter_title]', $campaign->contentTitle ?? $campaign->post_title, $templateHTML);
        $templateHTML = str_replace('[gdlnks_newsletter_content]', $campaign->post_content, $templateHTML);
        $templateHTML = str_replace('{campaignId}', $campaign->campaignId, $templateHTML);

        return $templateHTML;
    }


    public static function fillTemplate(string $template, object $campaign, object $subscriber): string
    {
        $email = $subscriber->email;
        $campaignId = $campaign->id;
        $templateHTML = do_shortcode($template);
        $templateHTML = str_replace('[gdlnks_newsletter_title]', $campaign->post_title, $templateHTML);
        $templateHTML = str_replace('[gdlnks_newsletter_content]', get_the_content($campaign->id), $templateHTML);

        $templateHTML = str_replace('{campaignId}', $campaign->campaignId, $templateHTML);
        $templateHTML = str_replace('{subscriberId}', $subscriber->subscriberId, $templateHTML);
        $templateHTML = str_replace('{email}', $email, $templateHTML);

        $templateHTML = str_replace('%7BcampaignId%7D', $campaign->campaignId, $templateHTML);
        $templateHTML = str_replace('%7BsubscriberId%7D', $subscriber->subscriberId, $templateHTML);
        $templateHTML = str_replace('%7Bemail%7D', $email, $templateHTML);

        return $templateHTML;
    }

    public static function updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed)
    {

        $campaignId = $campaign->id;
        update_post_meta($campaignId, 'emailsSend', $emailsSent);
        update_post_meta($campaignId, 'emailsFailed', $emailsFailed);
        update_post_meta($campaignId, 'emailsSkipped', $emailsSkipped);
        update_post_meta($campaignId, 'emailsUnsubed', $emailsUnsubed);
    }

    public static function getCounters($capmaign): object
    {
        return (object) [
            'emailsSend' => get_post_meta($capmaign->id, 'emailsSend', true),
            'emailsFailed' => get_post_meta($capmaign->id, 'emailsFailed', true),
            'emailsSkipped' => get_post_meta($capmaign->id, 'emailsSkipped', true),
            'emailsUnsubed' => get_post_meta($capmaign->id, 'emailsUnsubed', true),
            'emailsNewlyUnsubed' => get_post_meta($capmaign->id, 'emailsNewlyUnsubed', true) ?? 0,
        ];
    }

    public static function incrementNewlyUnsubed($campaignId): void
    {
        $campaign = self::getCampaignById($campaignId);
        if ($campaign) {
            $current = (int)($campaign->emailsNewlyUnsubed ?? 0);
            update_post_meta($campaignId, 'emailsNewlyUnsubed', $current + 1);
        }
    }

    public static function linkCLicked($campaignId, $url): int
    {
        $campaign = self::getCampaignByCampaignId($campaignId);
        $currentCountTotal = ( int ) $campaign->linksClickedTotal ?? 0;
        update_post_meta($campaign->id, 'linksClickedTotal', $currentCountTotal + 1);

        // lets check if visit is in session already
        // check if session started
        if (!session_id()) {
            session_start();
        }
        
        // Track unique user clicks (once per subscriber per campaign)
        $isNewUser = !isset($_SESSION['campaignId']) || !isset($_SESSION['subscriberId']);
        if ($isNewUser) {
            $currentUniqueUsers = ( int ) $campaign->uniqueUserClicks ?? 0;
            update_post_meta($campaign->id, 'uniqueUserClicks', $currentUniqueUsers + 1);
        }
        
        if (isset($_SESSION['campaignId']) && isset($_SESSION['subscriberId']) &&  isset($_SESSION[$url])) {
            return $campaign->linksClicked;
        }

        $currentCount = ( int ) $campaign->linksClicked ?? 0;
        update_post_meta($campaign->id, 'linksClicked', $currentCount + 1);

        $links = $campaign->links;
        if (isset($links[$url])) {
            $links[$url] = (int) $links[$url] + 1;
        } else {
            $links[$url] = 1;
        }
        update_post_meta($campaign->id, 'links', json_encode($links));

        add_post_meta($campaign->id, 'click_time', time(),false);

        return (int) $campaign->linksClicked + 1;
    }

    public static function testStart(int $campaignId): void
    {
        update_post_meta($campaignId, 'testStarted', time());
        self::resetCounters($campaignId);
    }

    public static function testFinish( int $campaignId): void
    {
        update_post_meta($campaignId, 'testFinished', time());
    }

    public static function testApprove(int $campaignId): void
    {
        update_post_meta($campaignId, 'testApproved', time());
    }

    public static function testReset(int $campaignId): void
    {
        update_post_meta($campaignId, 'testStarted', false);
        update_post_meta($campaignId, 'testFinished', false);
        update_post_meta($campaignId, 'testApproved', false);
    }

    public static function campaignStart(int $campaignId): void
    {
        $campaign = self::getCampaignById($campaignId);
        if (!$campaign->campaignStarted) {
            self::resetCounters($campaignId);
        }
        update_post_meta($campaignId, 'campaignStarted', time());
    }

    public static function campaignFinish(int $campaignId): void
    {
        update_post_meta($campaignId, 'campaignFinished', time());
    }

    public static function resetCounters(int $campaignId): void
    {
        update_post_meta($campaignId, 'emailsSend', 0);
        update_post_meta($campaignId, 'emailsFailed', 0);
        update_post_meta($campaignId, 'emailsSkipped', 0);
        update_post_meta($campaignId, 'emailsUnsubed', 0);
        update_post_meta($campaignId, 'emailsNewlyUnsubed', 0);
        update_post_meta($campaignId, 'linksClicked', 0);
        update_post_meta($campaignId, 'linksClickedTotal', 0);
        update_post_meta($campaignId, 'uniqueUserClicks', 0);
    }

    public static function getDataForDashBoard($limit)
    {
        $lastCampaigns = Campaigns::getLastCampaigns($limit);

        $skipped = [];
        $sent = [];
        $unsubscribed = [];
        $newlyUnsubscribed = [];
        $failed = [];
        $uniqueUsers = [];
        $linksClicked = [];

        foreach ($lastCampaigns as $lastCampaign) {
            $skipped[] = is_numeric($lastCampaign->emailsSkipped) ? $lastCampaign->emailsSkipped : 0;
            $sent[] = is_numeric($lastCampaign->emailsSend) ? $lastCampaign->emailsSend : 0;
            $unsubscribed[] = is_numeric($lastCampaign->emailsUnsubed) ? $lastCampaign->emailsUnsubed : 0;
            $newlyUnsubscribed[] = is_numeric($lastCampaign->emailsNewlyUnsubed) ? $lastCampaign->emailsNewlyUnsubed : 0;
            $failed[] = is_numeric($lastCampaign->emailsFailed) ? $lastCampaign->emailsFailed : 0;
            $uniqueUsers[] = is_numeric($lastCampaign->uniqueUserClicks) ? $lastCampaign->uniqueUserClicks : 0;
            $linksClicked[] = is_numeric($lastCampaign->linksClicked) ? $lastCampaign->linksClicked : 0;
        }

        return [
            'skipped' => $skipped,
            'sent' => $sent,
            'unsubscribed' => $unsubscribed,
            'newlyUnsubscribed' => $newlyUnsubscribed,
            'failed' => $failed,
            'uniqueUsers' => $uniqueUsers,
            'linksClicked' => $linksClicked,
        ];
    }
}
