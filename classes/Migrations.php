<?php

namespace Mawiblah;

class Migrations
{
    public static function run()
    {
        $currentVersion = get_option('mawiblah_db_version');

        if (version_compare($currentVersion, '1.0.15', '<')) {
            self::migrateTo1015();
            update_option('mawiblah_db_version', '1.0.15');
        }

        if (version_compare($currentVersion, '1.0.16', '<')) {
            self::migrateTo1016();
            update_option('mawiblah_db_version', '1.0.16');
        }

        if (version_compare($currentVersion, '1.0.17', '<')) {
            self::migrateTo1017();
            update_option('mawiblah_db_version', '1.0.17');
        }
    }

    private static function migrateTo1015()
    {
        // Migration: Rename campaignId meta to campaignHash
        $campaigns = get_posts([
            'post_type' => MAWIBLAH_POST_TYPE_PREFIX . 'campaigns',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);

        foreach ($campaigns as $campaign) {
            $campaignIdMeta = get_post_meta($campaign->ID, 'campaignId', true);
            $campaignHashMeta = get_post_meta($campaign->ID, 'campaignHash', true);

            if ($campaignIdMeta && !$campaignHashMeta) {
                update_post_meta($campaign->ID, 'campaignHash', $campaignIdMeta);
                delete_post_meta($campaign->ID, 'campaignId');
            } elseif (!$campaignHashMeta) {
                // Generate hash if missing entirely (should be handled by appendMeta but good for DB consistency)
                $hash = Helpers::generateCampaignHash($campaign->ID);
                update_post_meta($campaign->ID, 'campaignHash', $hash);
            }
        }
    }

    private static function migrateTo1017(): void
    {
        // Ensure both system audiences exist and have audienceHash set
        $defaults = [
            'Unsubed' => 'Audience for unsubscribed subscribers. Managed automatically.',
            'Testers' => 'Audience for test campaign recipients. Managed automatically.',
        ];

        $taxonomy = Subscribers::postType() . '_category';

        foreach ($defaults as $name => $description) {
            $term = get_term_by('name', $name, $taxonomy);

            if (!$term) {
                $result = wp_insert_term($name, $taxonomy, ['description' => $description]);
                if (is_wp_error($result)) {
                    continue;
                }
                $term = get_term($result['term_id'], $taxonomy);
            }

            if (!$term || is_wp_error($term)) {
                continue;
            }

            // Ensure audienceHash is set (same lazy logic as appendAudienceMeta)
            $hash = get_term_meta($term->term_id, 'audienceHash', true);
            if (!$hash) {
                add_term_meta($term->term_id, 'audienceHash', md5((string) $term->term_id), true);
            }
        }
    }

    private static function migrateTo1016()
    {
        // Migration: Rename subscriberId meta to subscriberHash
        $subscribers = get_posts([
            'post_type' => MAWIBLAH_POST_TYPE_PREFIX . 'subscribers',
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);

        foreach ($subscribers as $subscriber) {
            $subscriberIdMeta = get_post_meta($subscriber->ID, 'subscriberId', true);
            $subscriberHashMeta = get_post_meta($subscriber->ID, 'subscriberHash', true);

            if ($subscriberIdMeta && !$subscriberHashMeta) {
                update_post_meta($subscriber->ID, 'subscriberHash', $subscriberIdMeta);
                delete_post_meta($subscriber->ID, 'subscriberId');
            } elseif (!$subscriberHashMeta) {
                // Generate hash if missing entirely
                $hash = Helpers::generateSubscriberHash($subscriber->ID);
                update_post_meta($subscriber->ID, 'subscriberHash', $hash);
            }
        }
    }
}
