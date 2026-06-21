<?php

namespace Mawiblah;

class Migrations
{
    /**
     * Runs all pending database migrations in version order.
     *
     * Reads the stored schema version from options and applies only the migrations
     * that have not yet run. Safe to call on every request.
     */
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

    /**
     * Migrates campaign meta key from campaignId to campaignHash (introduced in 1.0.15).
     *
     * Copies existing campaignId values to campaignHash and removes the old key.
     * Generates a new hash for campaigns that have neither key set.
     */
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

    /**
     * Migrates subscriber meta key from subscriberId to subscriberHash (introduced in 1.0.16).
     *
     * Copies existing subscriberId values to subscriberHash and removes the old key.
     * Generates a new hash for subscribers that have neither key set.
     */
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
