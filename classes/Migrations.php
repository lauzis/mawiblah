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
}
