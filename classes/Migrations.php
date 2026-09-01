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
        add_action('mawiblah_migration_1021_continue', [self::class, 'continueMigration1021']);

        $currentVersion = get_option('mawiblah_db_version');

        if (version_compare($currentVersion, '1.0.15', '<')) {
            self::migrateTo1015();
            update_option('mawiblah_db_version', '1.0.15');
        }

        if (version_compare($currentVersion, '1.0.16', '<')) {
            self::migrateTo1016();
            update_option('mawiblah_db_version', '1.0.16');
        }

        if (version_compare($currentVersion, '1.0.21', '<')) {
            $done = self::migrateTo1021();
            if ($done) {
                update_option('mawiblah_db_version', '1.0.21');
            }
        }

        if (version_compare($currentVersion, '1.0.31', '<')) {
            self::migrateTo1031();
            update_option('mawiblah_db_version', '1.0.31');
        }

        if (version_compare($currentVersion, '1.0.32', '<')) {
            self::migrateTo1032();
            update_option('mawiblah_db_version', '1.0.32');
        }

        if (version_compare($currentVersion, '1.0.40', '<')) {
            self::migrateTo1040();
            update_option('mawiblah_db_version', '1.0.40');
        }
    }

    /**
     * WP-Cron callback to continue the batched 1.0.21 migration.
     *
     * Processes the next batch of log posts and updates the schema version once all
     * posts have been migrated.
     */
    public static function continueMigration1021(): void
    {
        $done = self::migrateTo1021();
        if ($done) {
            update_option('mawiblah_db_version', '1.0.21');
        }
    }

    /**
     * Names the reCAPTCHA version an install had already chosen.
     *
     * The setting was Disabled/Enabled while v3 was the only version on offer.
     * It is now Disabled/v2/v3, and Carbon Fields validates a select against
     * its declared options and silently returns the default for anything else
     * -- so an install left holding 'enabled' would have read as *disabled* and
     * quietly stopped checking anything. Same trap as 1.0.32, same fix.
     *
     * @return void
     */
    private static function migrateTo1040(): void
    {
        foreach (['_mawiblah-recaptcha-enabled', 'mawiblah-recaptcha-enabled'] as $key) {
            if ('enabled' === get_option($key, null)) {
                update_option($key, 'v3');
                Logs::addLog('migration', 'Named the reCAPTCHA version as v3.', ['option' => $key]);
            }
        }
    }

    /**
     * Normalises the legacy 'enable-db-log' logging value.
     *
     * The settings page has never offered it and there has never been a
     * database log target — installs holding it were writing files, exactly as
     * 'enable-file-log' does. The old hand-rolled renderer read the option
     * directly and did not care, but Carbon Fields validates a select against
     * its declared options and silently returns the default for anything else,
     * which switched logging off on upgrade.
     *
     * @return void
     */
    private static function migrateTo1032(): void
    {
        foreach (['_mawiblah-debug', 'mawiblah-debug'] as $key) {
            if ('enable-db-log' === get_option($key, null)) {
                update_option($key, 'enable-file-log');
                Logs::addLog('migration', 'Normalised legacy logging value.', ['option' => $key]);
            }
        }
    }

    /**
     * Moves settings onto Carbon Fields' storage.
     *
     * The settings page used to write plain options; Carbon Fields stores theme
     * options under an underscore-prefixed key. Without this every setting would
     * read as unset after the upgrade — logging off, reCAPTCHA off, thresholds
     * back to their defaults.
     *
     * Existing values are copied rather than moved, so rolling back to the
     * previous version still finds its settings.
     *
     * @return void
     */
    private static function migrateTo1031(): void
    {
        $schemaFile = MAWIBLAH_CONFIG_PATH . '/settings.json';

        if (!is_readable($schemaFile)) {
            return;
        }

        $schema = json_decode(file_get_contents($schemaFile), true);
        $copied = 0;

        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] as $field) {
                $legacyKey = 'mawiblah-' . $field['id'];
                $carbonKey = '_' . $legacyKey;

                // Never clobber a value Carbon Fields has already written.
                if (null !== get_option($carbonKey, null)) {
                    continue;
                }

                $existing = get_option($legacyKey, null);

                if (null === $existing || '' === $existing) {
                    continue;
                }

                // 'enable-db-log' is not among the logging field's options, and
                // Carbon Fields returns the default for a value it does not
                // recognise. It always meant file logging anyway.
                if ('mawiblah-debug' === $legacyKey && 'enable-db-log' === $existing) {
                    $existing = 'enable-file-log';
                }

                update_option($carbonKey, $existing);
                $copied++;
            }
        }

        Logs::addLog('migration', 'Copied settings to Carbon Fields storage.', ['keys' => $copied]);
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
     * Migrates log entries from the mawiblah_log post type to daily log files (introduced in 1.0.21).
     *
     * Processes up to 200 posts per call to avoid PHP timeouts. If posts remain after the batch,
     * schedules a WP-Cron event to continue. Returns true only when all posts have been migrated.
     *
     * Each post is written as a single line to mawiblah-YYYY-MM-DD.log (based on post_date),
     * then permanently deleted from the database.
     */
    private static function migrateTo1021(): bool
    {
        $posts = get_posts([
            'post_type'      => MAWIBLAH_POST_TYPE_PREFIX . 'log',
            'posts_per_page' => 200,
            'post_status'    => 'any',
            'orderby'        => 'date',
            'order'          => 'ASC',
        ]);

        if (empty($posts)) {
            Logs::addLog('migration-1021', 'Migration to 1.0.21 complete: all log posts migrated to files.');
            return true;
        }

        Logs::addLog('migration-1021', 'Migrating log posts to files.', ['batch' => count($posts)]);

        $dir = MAWIBLAH_LOG_PATH;
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        foreach ($posts as $post) {
            $date      = gmdate('Y-m-d', strtotime($post->post_date_gmt ?: $post->post_date));
            $timestamp = gmdate('Y-m-d H:i:s', strtotime($post->post_date_gmt ?: $post->post_date));
            $action    = sanitize_text_field($post->post_title);

            // Strip HTML that addLog() injected for additional objects, collapse whitespace
            $content = wp_strip_all_tags($post->post_content ?? '');
            $content = preg_replace('/\s+/', ' ', $content);
            $content = trim($content);

            $line = "[{$timestamp}] [{$action}]";
            if ($content !== '') {
                $line .= " {$content}";
            }

            file_put_contents(
                $dir . "mawiblah-{$date}.log",
                $line . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );

            wp_delete_post($post->ID, true);
        }

        $remaining = get_posts([
            'post_type'      => MAWIBLAH_POST_TYPE_PREFIX . 'log',
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'fields'         => 'ids',
        ]);

        if (!empty($remaining)) {
            Logs::addLog('migration-1021', 'Batch complete, scheduling next batch.');
            if (!wp_next_scheduled('mawiblah_migration_1021_continue')) {
                wp_schedule_single_event(time() + 5, 'mawiblah_migration_1021_continue');
            }
            return false;
        }

        Logs::addLog('migration-1021', 'Migration to 1.0.21 complete: all log posts migrated to files.');
        return true;
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
