<?php

namespace Mawiblah;

class Campaigns
{
    const STAT_SENT = 'sent';
    const STAT_FAILED = 'failed';
    const STAT_SKIPPED = 'skipped';
    const STAT_UNSUBSCRIBED = 'unsubscribed';
    const STAT_NEWLY_UNSUBSCRIBED = 'newlyUnsubscribed';
    const STAT_UNIQUE_USERS = 'uniqueUsers';
    const STAT_LINKS_CLICKED = 'linksClicked';

    // exampel http://gudlenieks.test/?utm_source=email&utm_medium=email&utm_campaign=monthly-email&mawiblahId=%7BmawiblahId%7D&unsubscribe=%7Bemail%7D
    /** Registers the campaign post type and hooks campaign meta boxes and save handlers. */
    public static function init()
    {
        self::registerPostType();
        add_action('add_meta_boxes', [self::class, 'addMetaBoxes']);
        add_action('save_post', [self::class, 'saveMetaBoxData']);
    }

    /** Registers the Campaign Details and Campaign Statistics meta boxes on the campaign edit screen. */
    public static function addMetaBoxes()
    {
        add_meta_box(
            'mawiblah_campaign_details',
            __('Campaign Details', 'mawiblah'),
            [self::class, 'renderDetailsMetaBox'],
            self::postType(),
            'normal',
            'high'
        );

        add_meta_box(
            'mawiblah_campaign_stats',
            __('Campaign Statistics', 'mawiblah'),
            [self::class, 'renderStatsMetaBox'],
            self::postType(),
            'normal',
            'high'
        );
    }

    /**
     * Renders the Campaign Details meta box (audiences, template, subject).
     *
     * @param \WP_Post $post The campaign post being edited.
     */
    public static function renderDetailsMetaBox($post)
    {
        $campaign = self::appendMeta($post);
        $data = ['campaign' => $campaign];
        \Mawiblah\Templates::loadTemplate('campaign/edit-fields.php', $data);
    }

    /**
     * Saves campaign meta box field values on post save.
     *
     * Validates the nonce, skips autosaves and insufficient-capability saves.
     * Persists subject, contentTitle, template, and audiences as post meta.
     *
     * @param int $post_id The post ID being saved.
     */
    public static function saveMetaBoxData($post_id)
    {
        if (!isset($_POST['mawiblah_campaign_details_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['mawiblah_campaign_details_nonce'], 'mawiblah_save_campaign_details')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['subject'])) {
            update_post_meta($post_id, 'subject', sanitize_text_field($_POST['subject']));
        }

        if (isset($_POST['contentTitle'])) {
            update_post_meta($post_id, 'contentTitle', sanitize_text_field($_POST['contentTitle']));
        }

        if (isset($_POST['template'])) {
            update_post_meta($post_id, 'template', sanitize_text_field($_POST['template']));
        }

        if (isset($_POST['audiences'])) {
            $audiences = array_map('sanitize_text_field', $_POST['audiences']);
            update_post_meta($post_id, 'audiences', $audiences);
        } else {
            update_post_meta($post_id, 'audiences', []);
        }
    }

    /**
     * Renders the Campaign Statistics meta box with raw, conversion, link, day, and hour charts.
     *
     * Shows a placeholder message if the campaign has not yet started.
     *
     * @param \WP_Post $post The campaign post being viewed.
     */
    public static function renderStatsMetaBox($post)
    {
        $campaign = self::appendMeta($post);

        if (!$campaign->campaignStarted) {
            echo '<p>' . __('Campaign has not started yet.', 'mawiblah') . '</p>';
            return;
        }

        \Mawiblah\Templates::loadTemplate('stats/styles.php', []);

        $rawStats = self::getStatsForCampaign($campaign);
        $conversionStats = self::getConversionStatsForCampaign($campaign);

        $campaignData = [
            'campaign' => $campaign,
            'title' => $campaign->post_title,
            'stats' => $rawStats
        ];

        $conversionData = [
            'campaign' => $campaign,
            'title' => $campaign->post_title,
            'stats' => $conversionStats
        ];

        echo '<div class="wrap mawiblah">';
        \Mawiblah\Templates::loadTemplate('stats/campaign-raw.php', $campaignData);
        \Mawiblah\Templates::loadTemplate('stats/campaign-conversion.php', $conversionData);
        \Mawiblah\Templates::loadTemplate('stats/last-links.php', $campaignData);
        \Mawiblah\Templates::loadTemplate('stats/last-days.php', $campaignData);
        \Mawiblah\Templates::loadTemplate('stats/last-hours.php', $campaignData);
        echo '</div>';
    }

    /**
     * Permanently deletes a campaign post.
     *
     * @param int $campaignPostId Campaign post ID to delete.
     * @return \WP_Post|false|null Deleted post on success, false or null on failure.
     */
    public static function deleteCampaign($campaignPostId)
    {
        return wp_delete_post($campaignPostId);
    }

    /**
     * Returns true if no campaign with the given title exists.
     *
     * @param string $title Campaign title to check.
     * @return bool
     */
    public static function isUnique($title): bool
    {
        return self::getCampaign($title) == null;
    }

    /**
     * Validates all required campaign fields.
     *
     * Checks that title, subject, audiences, and template are non-empty, that the template
     * file exists, and that all audience IDs are valid taxonomy terms. Prints an error
     * message and returns false on the first failure.
     *
     * @param string   $title     Campaign title.
     * @param string   $subject   Email subject line.
     * @param int[]    $audiences Array of audience term IDs.
     * @param string   $template  Email template filename.
     * @return bool True if valid, false otherwise.
     */
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

    /**
     * Returns true if the named email template file exists in any template directory.
     *
     * @param string $emailTemplate Template filename without extension.
     * @return bool
     */
    public static function validateEmailTemplate(string $emailTemplate): bool
    {
        $template = Templates::getEmailTemplateByName($emailTemplate);
        if ($template) {
            return true;
        }

        return false;
    }

    /** Returns the custom post type slug for campaigns. */
    public static function postType()
    {
        return MAWIBLAH_POST_TYPE_PREFIX . 'campaigns';
    }

    /** Registers the campaign custom post type with WordPress (admin-only, not public). */
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
            'labels'          => $labels,
            'public'          => false,
            'publicly_queryable' => false,
            'show_ui'         => true,
            'show_in_menu'    => false,
            'query_var'       => true,
            'rewrite'         => ['slug' => 'mawiblah-campaigns'],
            'capability_type' => 'post',
            'has_archive'     => false,
            'hierarchical'    => false,
            'menu_position'   => null,
            'menu_icon'       => 'dashicons-megaphone',
            'supports'        => ['title', 'editor'],
        ];

        register_post_type(Campaigns::postType(), $args);
    }

    /**
     * Attaches all campaign meta fields to a post or campaign object.
     *
     * Lazily generates and persists a campaignHash if one is not yet stored.
     *
     * @param object $post WP_Post or campaign object to decorate.
     * @return object The same object with all campaign meta properties attached.
     */
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
        $post->campaignHash = get_post_meta($post->id, 'campaignHash', true);

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

        if (!$post->campaignHash) {
            $post->campaignHash = Helpers::generateCampaignHash($post->id);
            update_post_meta($post->id, 'campaignHash', $post->campaignHash);
        }

        return $post;
    }

    /**
     * Retrieves a campaign by its WordPress post ID.
     *
     * @param int $id Campaign post ID.
     * @return object|null Campaign with meta attached, or null if not found.
     */
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

    /**
     * Retrieves a campaign by its public campaignHash identifier.
     *
     * @param string $campaignHash MD5 hash stored in the campaignHash meta field.
     * @return object|null Campaign with meta, or null if not found.
     */
    public static function getCampaignByHash($campaignHash): object|null
    {
        $postByMeta = get_posts([
            'post_type' => self::postType(),
            'meta_key' => 'campaignHash',
            'meta_value' => $campaignHash,
            'posts_per_page' => 1,
        ]);

        if (empty($postByMeta)) {
            return null;
        }

        return self::appendMeta((object)$postByMeta[0]);
    }

    /**
     * Retrieves a campaign by its exact title.
     *
     * @param string $title Campaign title to search for.
     * @return object|null Campaign with meta, or null if not found.
     */
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

    /**
     * Returns all campaigns with meta attached.
     *
     * @return array Array of campaign objects.
     */
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

    /**
     * Returns the most recently created campaigns, ordered newest first.
     *
     * @param int $limit Maximum number of campaigns to return (default 5).
     * @return array Array of campaign objects with meta attached.
     */
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

    /**
     * Creates a new campaign post with all associated meta fields.
     *
     * @param string   $title        Campaign title.
     * @param string   $subject      Email subject line.
     * @param string   $contentTitle Internal content title for template placeholders.
     * @param string   $content      Campaign post content body.
     * @param int[]    $audiences    Array of audience term IDs.
     * @param string   $template     Email template filename.
     * @return int New post ID.
     */
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

    /**
     * Updates an existing campaign's post fields and meta.
     *
     * @param int      $campaignPostId Post ID of the campaign to update.
     * @param string   $title          New title.
     * @param string   $subject        New email subject.
     * @param string   $contentTitle   New content title.
     * @param string   $content        New post content.
     * @param int[]    $audiences      New array of audience term IDs.
     * @param string   $template       New email template filename.
     * @return int Updated post ID.
     */
    public static function updateCampaign(int $campaignPostId, string $title, string $subject, string $contentTitle, string $content, array $audiences, string $template): int
    {
        // Prepare the post data
        $post_data = [
            'ID' => $campaignPostId,
            'post_title' => $title,
            'post_content' => $content,
        ];

        // Update the post in the database
        $post_id = wp_update_post($post_data);

        if (!is_wp_error($post_id)) {
            // Save the email as a meta field
            update_post_meta($post_id, 'template', $template);
            update_post_meta($post_id, 'audiences', $audiences);
            update_post_meta($post_id, 'subject', $subject);
            update_post_meta($post_id, 'contentTitle', $contentTitle);
        }

        return $post_id;
    }


    /** @deprecated Use getCampaigns() instead. Returns all campaigns as plain associative arrays. */
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

    /**
     * Returns campaigns matching the given title as plain associative arrays.
     *
     * @param string $name Campaign title to search for.
     * @return array Array of campaign data arrays.
     */
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

    /**
     * Permanently deletes all campaigns matching the given title.
     *
     * @param string $name Campaign title to match.
     */
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

    /**
     * Archives the campaign template snapshot and returns the processed HTML ready for sending.
     *
     * Sets the campaign status to 'sending-in-progress', fetches and archives the template
     * via REST (for WPML compatibility), evaluates shortcodes, and fills static placeholders.
     *
     * @param object $campaign  Campaign object with meta attached.
     * @param bool   $testMode  When true, always refreshes the archived template copy.
     * @return string|false Processed HTML, or false if the template could not be retrieved.
     */
    public static function lockTemplate(object $campaign, bool $testMode): string|bool
    {

        $campaignPostId = $campaign->id;
        update_post_meta($campaignPostId, 'status', 'sending-in-progress');
        // copy template

        $templateHTML = Templates::copyTemplate($campaignPostId, $testMode);

        if ($templateHTML === false) {
            return false;
        }

        $templateHTML = do_shortcode($templateHTML);
        $templateHTML = str_replace('[gdlnks_newsletter_title]', $campaign->contentTitle ?? $campaign->post_title, $templateHTML);
        $templateHTML = str_replace('[gdlnks_newsletter_content]', $campaign->post_content, $templateHTML);
        $templateHTML = str_replace('{campaignHash}', $campaign->campaignHash, $templateHTML);

        return $templateHTML;
    }


    /**
     * Replaces per-subscriber placeholders in a template with actual values.
     *
     * Handles {campaignHash}, {subscriberHash}, {email} and their URL-encoded equivalents.
     * Called once per email just before sending.
     *
     * @param string $template   Template HTML (typically from lockTemplate()).
     * @param object $campaign   Campaign object.
     * @param object $subscriber Subscriber object.
     * @return string Personalised HTML ready to send.
     */
    public static function fillTemplate(string $template, object $campaign, object $subscriber): string
    {
        $email = $subscriber->email;
        $campaignPostId = $campaign->id;
        $templateHTML = do_shortcode($template);
        $templateHTML = str_replace('[gdlnks_newsletter_title]', $campaign->post_title, $templateHTML);

        $templateHTML = str_replace('[gdlnks_newsletter_content]', get_the_content($campaign->id), $templateHTML);

        $templateHTML = str_replace('{campaignHash}', $campaign->campaignHash, $templateHTML);
        $templateHTML = str_replace('{subscriberHash}', $subscriber->subscriberHash, $templateHTML);
        $templateHTML = str_replace('{email}', $email, $templateHTML);
        $templateHTML = str_replace('%7BcampaignHash%7D', $campaign->campaignHash, $templateHTML);
        $templateHTML = str_replace('%7BsubscriberHash%7D', $subscriber->subscriberHash, $templateHTML);
        $templateHTML = str_replace('%7Bemail%7D', $email, $templateHTML);

        return $templateHTML;
    }

    /**
     * Persists the four main email-send counters for a campaign.
     *
     * @param object $campaign       Campaign object.
     * @param int    $emailsSent     Total successfully sent emails.
     * @param int    $emailsFailed   Total failed sends.
     * @param int    $emailsSkipped  Total skipped sends.
     * @param int    $emailsUnsubed  Total sends skipped due to unsubscription.
     */
    public static function updateCounters(object $campaign, int $emailsSent, int $emailsFailed, int $emailsSkipped, int $emailsUnsubed): void
    {

        $campaignPostId = $campaign->id;
        update_post_meta($campaignPostId, 'emailsSend', $emailsSent);
        update_post_meta($campaignPostId, 'emailsFailed', $emailsFailed);
        update_post_meta($campaignPostId, 'emailsSkipped', $emailsSkipped);
        update_post_meta($campaignPostId, 'emailsUnsubed', $emailsUnsubed);
    }

    /**
     * Returns the current email-send counters for a campaign.
     *
     * @param object $campaign Campaign object with an id property.
     * @return object Object with emailsSend, emailsFailed, emailsSkipped, emailsUnsubed, emailsNewlyUnsubed.
     */
    public static function getCounters(object $campaign): object
    {
        return (object)[
            'emailsSend' => get_post_meta($campaign->id, 'emailsSend', true),
            'emailsFailed' => get_post_meta($campaign->id, 'emailsFailed', true),
            'emailsSkipped' => get_post_meta($campaign->id, 'emailsSkipped', true),
            'emailsUnsubed' => get_post_meta($campaign->id, 'emailsUnsubed', true),
            'emailsNewlyUnsubed' => get_post_meta($campaign->id, 'emailsNewlyUnsubed', true) ?? 0,
        ];
    }

    /**
     * Increments the newly-unsubscribed counter and appends the current timestamp for attribution.
     *
     * @param int $campaignPostId Campaign post ID.
     */
    public static function incrementNewlyUnsubed(int $campaignPostId): void
    {
        $campaign = self::getCampaignById($campaignPostId);
        if ($campaign) {
            $current = (int)($campaign->emailsNewlyUnsubed ?? 0);
            update_post_meta($campaignPostId, 'emailsNewlyUnsubed', $current + 1);
            add_post_meta($campaignPostId, 'unsub_time', time(), false);
        }
    }

    /**
     * Records a link click for a campaign URL, updating all three click counters.
     *
     * Always increments linksClickedTotal. Increments linksClicked and uniqueUserClicks
     * only when the session doesn't already carry them (prevents double-counting per session).
     *
     * @param string $campaignHash Campaign identifier hash.
     * @param string $url          The URL that was clicked.
     * @return int Updated per-session click count, or 0 if campaign not found.
     */
    public static function linkCLicked(string $campaignHash, string $url): int
    {
        $campaign = self::getCampaignByHash($campaignHash);

        if (!$campaign) {
            Logs::addLog('Campaign not found', 'Campaign not found', ['campaignHash' => $campaignHash, 'url' => $url]);
            return 0;
        }

        $currentCountTotal = ( int )$campaign->linksClickedTotal ?? 0;
        update_post_meta($campaign->id, 'linksClickedTotal', $currentCountTotal + 1);

        // lets check if visit is in session already
        // check if session started
        if (!session_id() && !headers_sent()) {
            session_start();
        }

        // Track unique user clicks (once per subscriber per campaign)
        $isNewUser = !isset($_SESSION['campaignHash']) || !isset($_SESSION['subscriberHash']);
        if ($isNewUser) {
            $currentUniqueUsers = ( int )$campaign->uniqueUserClicks ?? 0;
            update_post_meta($campaign->id, 'uniqueUserClicks', $currentUniqueUsers + 1);
        }

        if (isset($_SESSION['campaignHash']) && isset($_SESSION['subscriberHash']) && isset($_SESSION[$url])) {
            return (int)$campaign->linksClicked;
        }

        $currentCount = ( int )$campaign->linksClicked ?? 0;
        $newCount = $currentCount + 1;
        update_post_meta($campaign->id, 'linksClicked', $newCount);

        $links = $campaign->links;
        if (isset($links[$url])) {
            $links[$url] = (int)$links[$url] + 1;
        } else {
            $links[$url] = 1;
        }
        update_post_meta($campaign->id, 'links', json_encode($links));

        add_post_meta($campaign->id, 'click_time', time(), false);

        return $newCount;
    }

    /**
     * Returns click counts grouped by day of week for a single campaign.
     *
     * @param int $campaignPostId Campaign post ID.
     * @return array Map of weekday name to click count.
     */
    public static function getClickTimesByDayOfWeek(int $campaignPostId): array
    {
        $campaign = self::getCampaignById($campaignPostId);
        if (!$campaign) {
            return [];
        }

        $clickTimes = get_post_meta($campaign->id, 'click_time', false);

        if (empty($clickTimes)) {
            return [
                'Monday' => 0,
                'Tuesday' => 0,
                'Wednesday' => 0,
                'Thursday' => 0,
                'Friday' => 0,
                'Saturday' => 0,
                'Sunday' => 0
            ];
        }

        $dayStats = [
            'Monday' => 0,
            'Tuesday' => 0,
            'Wednesday' => 0,
            'Thursday' => 0,
            'Friday' => 0,
            'Saturday' => 0,
            'Sunday' => 0
        ];

        foreach ($clickTimes as $timestamp) {
            $dayOfWeek = date('l', (int)$timestamp);
            if (isset($dayStats[$dayOfWeek])) {
                $dayStats[$dayOfWeek]++;
            }
        }

        return $dayStats;
    }

    /**
     * Returns aggregated click counts grouped by day of week across the last N campaigns.
     *
     * @param int $limit Number of recent campaigns to include (default 12).
     * @return array Map of weekday name to total click count.
     */
    public static function getClickTimesByDayOfWeekForLastCampaigns(int $limit = 12): array
    {
        $campaigns = self::getLastCampaigns($limit);

        $dayStats = [
            'Monday' => 0,
            'Tuesday' => 0,
            'Wednesday' => 0,
            'Thursday' => 0,
            'Friday' => 0,
            'Saturday' => 0,
            'Sunday' => 0
        ];

        foreach ($campaigns as $campaign) {
            $clickTimes = get_post_meta($campaign->id, 'click_time', false);
            if (!empty($clickTimes)) {
                foreach ($clickTimes as $timestamp) {
                    $dayOfWeek = date('l', (int)$timestamp);
                    if (isset($dayStats[$dayOfWeek])) {
                        $dayStats[$dayOfWeek]++;
                    }
                }
            }
        }

        return $dayStats;
    }

    /**
     * Returns click counts grouped by hour of day (0–23) for a single campaign.
     *
     * @param int $campaignPostId Campaign post ID.
     * @return array Map of hour integer to click count.
     */
    public static function getClickTimesByHourOfDay(int $campaignPostId): array
    {
        $campaign = self::getCampaignById($campaignPostId);
        if (!$campaign) {
            return [];
        }

        $clickTimes = get_post_meta($campaign->id, 'click_time', false);


        if (empty($clickTimes)) {
            $hourStats = [];
            for ($i = 0; $i < 24; $i++) {
                $hourStats[$i] = 0;
            }
            return $hourStats;
        }

        $hourStats = [];
        for ($i = 0; $i < 24; $i++) {
            $hourStats[$i] = 0;
        }

        foreach ($clickTimes as $timestamp) {
            $hour = (int)date('G', (int)$timestamp);
            if (isset($hourStats[$hour])) {
                $hourStats[$hour]++;
            }
        }

        return $hourStats;
    }

    /**
     * Returns aggregated click counts grouped by hour of day across the last N campaigns.
     *
     * @param int $limit Number of recent campaigns to include (default 12).
     * @return array Map of hour integer (0–23) to total click count.
     */
    public static function getClickTimesByHourOfDayForLastCampaigns(int $limit = 12): array
    {
        $campaigns = self::getLastCampaigns($limit);

        $hourStats = [];
        for ($i = 0; $i < 24; $i++) {
            $hourStats[$i] = 0;
        }

        foreach ($campaigns as $campaign) {
            $clickTimes = get_post_meta($campaign->id, 'click_time', false);
            if (!empty($clickTimes)) {
                foreach ($clickTimes as $timestamp) {
                    $hour = (int)date('G', (int)$timestamp);
                    if (isset($hourStats[$hour])) {
                        $hourStats[$hour]++;
                    }
                }
            }
        }

        return $hourStats;
    }

    /**
     * Returns campaign start counts grouped by day of week for the last N campaigns.
     *
     * Used alongside getClickTimesByDayOfWeekForLastCampaigns() to compute the activity rating.
     *
     * @param int $limit Number of recent campaigns to include (default 12).
     * @return array Map of weekday name to campaign start count.
     */
    public static function getCampaignStartTimesByDayOfWeek(int $limit = 12): array
    {
        $campaigns = self::getLastCampaigns($limit);

        $dayStats = [
            'Monday' => 0,
            'Tuesday' => 0,
            'Wednesday' => 0,
            'Thursday' => 0,
            'Friday' => 0,
            'Saturday' => 0,
            'Sunday' => 0
        ];

        foreach ($campaigns as $campaign) {
            if (!empty($campaign->campaignStarted)) {
                $dayOfWeek = date('l', (int)$campaign->campaignStarted);
                if (isset($dayStats[$dayOfWeek])) {
                    $dayStats[$dayOfWeek]++;
                }
            }
        }

        return $dayStats;
    }

    /**
     * Starts the test phase: syncs unsubscribe status, records the start timestamp, and resets counters.
     *
     * @param int $campaignPostId Campaign post ID.
     */
    public static function testStart(int $campaignPostId): void
    {
        Subscribers::syncUnsubscribeStatus();
        update_post_meta($campaignPostId, 'testStarted', time());
        self::resetCounters($campaignPostId);
    }

    /**
     * Records the test phase completion timestamp.
     *
     * @param int $campaignPostId Campaign post ID.
     */
    public static function testFinish(int $campaignPostId): void
    {
        update_post_meta($campaignPostId, 'testFinished', time());
    }

    /**
     * Approves the test phase, allowing the campaign to proceed to full send.
     *
     * @param int $campaignPostId Campaign post ID.
     */
    public static function testApprove(int $campaignPostId): void
    {
        update_post_meta($campaignPostId, 'testApproved', time());
    }

    /**
     * Clears all test-phase timestamps so the campaign can be re-tested from scratch.
     *
     * @param int $campaignPostId Campaign post ID.
     */
    public static function testReset(int $campaignPostId): void
    {
        update_post_meta($campaignPostId, 'testStarted', false);
        update_post_meta($campaignPostId, 'testFinished', false);
        update_post_meta($campaignPostId, 'testApproved', false);
        Subscribers::resetTestSent($campaignPostId);
    }

    /**
     * Starts the full campaign send: resets counters and records the start timestamp.
     *
     * Guard: does nothing if campaignStarted is already set, preventing double-start.
     *
     * @param int $campaignPostId Campaign post ID.
     */
    public static function campaignStart(int $campaignPostId): void
    {
        $campaign = self::getCampaignById($campaignPostId);
        if ($campaign && !$campaign->campaignStarted) {
            self::resetCounters($campaignPostId);
            update_post_meta($campaignPostId, 'campaignStarted', time());
        }
    }

    /**
     * Records the campaign send completion timestamp.
     *
     * @param int $campaignPostId Campaign post ID.
     */
    public static function campaignFinish(int $campaignPostId): void
    {
        update_post_meta($campaignPostId, 'campaignFinished', time());
    }

    /**
     * Zeros all email-send and click counters for a campaign.
     *
     * Called at the start of each test phase and production send to ensure a clean slate.
     *
     * @param int $campaignPostId Campaign post ID.
     */
    public static function resetCounters(int $campaignPostId): void
    {
        update_post_meta($campaignPostId, 'emailsSend', 0);
        update_post_meta($campaignPostId, 'emailsFailed', 0);
        update_post_meta($campaignPostId, 'emailsSkipped', 0);
        update_post_meta($campaignPostId, 'emailsUnsubed', 0);
        update_post_meta($campaignPostId, 'emailsNewlyUnsubed', 0);
        update_post_meta($campaignPostId, 'linksClicked', 0);
        update_post_meta($campaignPostId, 'linksClickedTotal', 0);
        update_post_meta($campaignPostId, 'uniqueUserClicks', 0);
    }

    /**
     * Returns raw email-send and click stats for a campaign, formatted for bar-chart templates.
     *
     * Each stat key maps to an array of numeric values (one element per campaign in the input).
     *
     * @param object $campaign Campaign object with meta attached.
     * @return array<string, int[]> Map of STAT_* constant to single-element numeric array.
     */
    public static function getStatsForCampaign(object $campaign): array
    {
        $lastCampaigns = [$campaign];

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
            self::STAT_SKIPPED => $skipped,
            self::STAT_SENT => $sent,
            self::STAT_UNSUBSCRIBED => $unsubscribed,
            self::STAT_NEWLY_UNSUBSCRIBED => $newlyUnsubscribed,
            self::STAT_FAILED => $failed,
            self::STAT_UNIQUE_USERS => $uniqueUsers,
            self::STAT_LINKS_CLICKED => $linksClicked,
        ];
    }

    /**
     * Returns percentage-based conversion stats for a campaign, formatted for bar-chart templates.
     *
     * Normalises each stat as a percentage of total attempted sends to allow comparison across
     * campaigns with different list sizes.
     *
     * @param object $campaign Campaign object with meta attached.
     * @return array<string, float[]> Map of STAT_* constant to single-element percentage array.
     */
    public static function getConversionStatsForCampaign(object $campaign): array
    {
        $lastCampaigns = [$campaign];

        $skipped = [];
        $sent = [];
        $unsubscribed = [];
        $newlyUnsubscribed = [];
        $failed = [];
        $uniqueUsers = [];
        $linksClicked = [];

        foreach ($lastCampaigns as $lastCampaign) {

            $totalLinksCount = $lastCampaign->links ? count($lastCampaign->links) : 1;
            $skip = is_numeric($lastCampaign->emailsSkipped) ? $lastCampaign->emailsSkipped : 0;
            $sentCount = is_numeric($lastCampaign->emailsSend) ? $lastCampaign->emailsSend : 0;
            $newlyUnsubscribedCount = is_numeric($lastCampaign->emailsNewlyUnsubed) ? $lastCampaign->emailsNewlyUnsubed : 0;
            $failedCount = is_numeric($lastCampaign->emailsFailed) ? $lastCampaign->emailsFailed : 0;
            $uniqueUsersCount = is_numeric($lastCampaign->uniqueUserClicks) ? $lastCampaign->uniqueUserClicks : 0;
            $linksClickedCount = is_numeric($lastCampaign->linksClicked) ? $lastCampaign->linksClicked : 0;
            $total = $skip + $sentCount + $failedCount;
            $total = $total === 0 ? 1 : $total;
            $unsubed = is_numeric($lastCampaign->emailsUnsubed) ? $lastCampaign->emailsUnsubed : 0;
            $skipped[] = round($skip/$total*100,2);
            $sent[] = round($sentCount/$total*100,2);
            $unsubscribed[] = round($unsubed/($total+$unsubed)*100,2);
            $newlyUnsubscribed[] = round($newlyUnsubscribedCount/$total*100, 2);
            $failed[] = round($failedCount/$total*100,2);
            $uniqueUsers[] = round($uniqueUsersCount/$total*100,2);
            $linksClicked[] = round($linksClickedCount/($totalLinksCount*$total)*100,2);
        }

        return [
            self::STAT_SKIPPED => $skipped,
            self::STAT_SENT => $sent,
            self::STAT_UNSUBSCRIBED => $unsubscribed,
            self::STAT_NEWLY_UNSUBSCRIBED => $newlyUnsubscribed,
            self::STAT_FAILED => $failed,
            self::STAT_UNIQUE_USERS => $uniqueUsers,
            self::STAT_LINKS_CLICKED => $linksClicked,
        ];
    }

    /**
     * Returns raw email-send stats for the last N campaigns, formatted for dashboard bar charts.
     *
     * @param int $limit Number of recent campaigns to include.
     * @return array<string, int[]> Map of STAT_* constant to array of values (one per campaign).
     */
    public static function getDataForDashBoard(int $limit): array
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
            self::STAT_SKIPPED => $skipped,
            self::STAT_SENT => $sent,
            self::STAT_UNSUBSCRIBED => $unsubscribed,
            self::STAT_NEWLY_UNSUBSCRIBED => $newlyUnsubscribed,
            self::STAT_FAILED => $failed,
            self::STAT_UNIQUE_USERS => $uniqueUsers,
            self::STAT_LINKS_CLICKED => $linksClicked,
        ];
    }

    /**
     * Returns percentage-based conversion stats for the last N campaigns, formatted for dashboard bar charts.
     *
     * @param int $limit Number of recent campaigns to include.
     * @return array<string, float[]> Map of STAT_* constant to array of percentages (one per campaign).
     */
    public static function getDataForDashBoardConversionRate(int $limit): array
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

            $totalLinksCount = $lastCampaign->links ? count($lastCampaign->links) : 1;
            $skip = is_numeric($lastCampaign->emailsSkipped) ? $lastCampaign->emailsSkipped : 0;
            $sentCount = is_numeric($lastCampaign->emailsSend) ? $lastCampaign->emailsSend : 0;
            $newlyUnsubscribedCount = is_numeric($lastCampaign->emailsNewlyUnsubed) ? $lastCampaign->emailsNewlyUnsubed : 0;
            $failedCount = is_numeric($lastCampaign->emailsFailed) ? $lastCampaign->emailsFailed : 0;
            $uniqueUsersCount = is_numeric($lastCampaign->uniqueUserClicks) ? $lastCampaign->uniqueUserClicks : 0;
            $linksClickedCount = is_numeric($lastCampaign->linksClicked) ? $lastCampaign->linksClicked : 0;
            $total = $skip + $sentCount + $failedCount;
            $total = $total === 0 ? 1 : $total;
            $unsubed = is_numeric($lastCampaign->emailsUnsubed) ? $lastCampaign->emailsUnsubed : 0;
            $skipped[] = round($skip/$total*100,2);
            $sent[] = round($sentCount/$total*100,2);
            $unsubscribed[] = round($unsubed/($total+$unsubed)*100,2);
            $newlyUnsubscribed[] = round($newlyUnsubscribedCount/$total*100, 2);
            $failed[] = round($failedCount/$total*100,2);
            $uniqueUsers[] = round($uniqueUsersCount/$total*100,2);
            $linksClicked[] = round($linksClickedCount/($totalLinksCount*$total)*100,2);
        }

        return [
            self::STAT_SKIPPED => $skipped,
            self::STAT_SENT => $sent,
            self::STAT_UNSUBSCRIBED => $unsubscribed,
            self::STAT_NEWLY_UNSUBSCRIBED => $newlyUnsubscribed,
            self::STAT_FAILED => $failed,
            self::STAT_UNIQUE_USERS => $uniqueUsers,
            self::STAT_LINKS_CLICKED => $linksClicked,
        ];
    }

    /**
     * Returns monthly unsubscribe counts for the given number of past months.
     *
     * Counts unsubscribe events by reading unsub_time meta entries on campaign posts.
     *
     * @param int $months Number of months to look back (default 12).
     * @return array Map of "Mon YYYY" label to unsubscribe count.
     */
    public static function getUnsubscribeGrowthStats(int $months = 12): array
    {
        $stats = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = date('Y-m', strtotime("-$i months"));
            $stats[$date] = 0;
        }

        global $wpdb;
        $subscriberPostType = Subscribers::postType();

        $query = "
            SELECT 
                p.ID,
                pm_time.meta_value as unsub_time,
                pm_last.meta_value as last_interaction
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_unsub ON p.ID = pm_unsub.post_id
            LEFT JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'unsub_time'
            LEFT JOIN {$wpdb->postmeta} pm_last ON p.ID = pm_last.post_id AND pm_last.meta_key = 'lastInteraction'
            WHERE p.post_type = %s
            AND pm_unsub.meta_key = 'unsubed' 
            AND pm_unsub.meta_value = '1'
        ";

        $results = $wpdb->get_results($wpdb->prepare($query, $subscriberPostType));

        $cutoff = strtotime("-$months months");

        foreach ($results as $row) {
            $timestamp = $row->unsub_time;
            if (empty($timestamp)) {
                $timestamp = $row->last_interaction;
            }

            if (!empty($timestamp)) {
                if (!is_numeric($timestamp)) {
                    $timestamp = strtotime($timestamp);
                }

                if ($timestamp >= $cutoff) {
                    $ym = date('Y-m', (int)$timestamp);
                    if (isset($stats[$ym])) {
                        $stats[$ym]++;
                    }
                }
            }
        }

        // Format keys for display
        $formattedStats = [];
        foreach ($stats as $ym => $count) {
            $timestamp = strtotime($ym . '-01');
            $label = date_i18n('M Y', $timestamp);
            $formattedStats[$label] = $count;
        }

        return $formattedStats;
    }
}
