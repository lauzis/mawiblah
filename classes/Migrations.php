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
