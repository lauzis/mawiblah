<?php

namespace Mawiblah;

/**
 * Campaign Scheduler — stores and manages scheduled sends.
 *
 * Each scheduler record references one campaign and defines when (and how often)
 * it should be sent via WP Cron (see SchedulerCron). Schedule types:
 *   once    — fires once at a specific date + time, then marks itself completed.
 *   weekly  — fires every week on a given day of the week.
 *   monthly — fires every month on a given day of the month.
 *
 * Recurring schedules run forever unless an end_date is set.
 */
class Scheduler
{
    const POST_TYPE = 'mawiblah_scheduler';

    /** Registers the scheduler CPT and hooks. Called from mawiblah_init(). */
    public static function init(): void
    {
        register_post_type(self::POST_TYPE, [
            'public'   => false,
            'show_ui'  => false,
            'supports' => ['title'],
            'label'    => 'Mawiblah Scheduler',
        ]);
    }

    /** Returns all scheduler records with meta attached. */
    public static function getAll(): array
    {
        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        return array_map([self::class, 'appendMeta'], $posts);
    }

    /**
     * Returns a single scheduler by ID, or null if not found.
     *
     * @param int $id Scheduler post ID.
     */
    public static function getById(int $id): ?object
    {
        $post = get_post($id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return null;
        }
        return self::appendMeta($post);
    }

    private static function appendMeta(object $post): object
    {
        $post->id            = $post->ID;
        $post->campaign_id   = (int) get_post_meta($post->ID, 'campaign_id', true);
        $post->status        = get_post_meta($post->ID, 'status', true) ?: 'active';
        $post->schedule_type = get_post_meta($post->ID, 'schedule_type', true) ?: 'once';
        $post->send_time     = get_post_meta($post->ID, 'send_time', true) ?: '09:00';
        $post->send_day      = (int) (get_post_meta($post->ID, 'send_day', true) ?: 1);
        $post->send_date     = get_post_meta($post->ID, 'send_date', true) ?: '';
        $post->next_send     = (int) get_post_meta($post->ID, 'next_send', true);
        $post->end_date      = get_post_meta($post->ID, 'end_date', true) ?: '';
        $post->last_sent     = (int) get_post_meta($post->ID, 'last_sent', true);
        return $post;
    }

    /**
     * Creates a new scheduler record.
     *
     * @param string $name         Display name.
     * @param int    $campaignId   Campaign post ID.
     * @param string $scheduleType one of: once, weekly, monthly.
     * @param string $sendTime     Time of day in H:i format (site timezone).
     * @param int    $sendDay      Day-of-week (0=Sun…6=Sat) for weekly; day-of-month (1-31) for monthly.
     * @param string $sendDate     YYYY-MM-DD for once-type schedules.
     * @param string $endDate      Optional YYYY-MM-DD cutoff for recurring schedules; empty = forever.
     * @return int|null New post ID or null on failure.
     */
    public static function add(string $name, int $campaignId, string $scheduleType, string $sendTime, int $sendDay, string $sendDate = '', string $endDate = ''): ?int
    {
        $id = wp_insert_post([
            'post_type'   => self::POST_TYPE,
            'post_title'  => sanitize_text_field($name),
            'post_status' => 'publish',
        ]);

        if (is_wp_error($id) || !$id) {
            return null;
        }

        $nextSend = self::computeNextSend($scheduleType, $sendTime, $sendDay, $sendDate);

        update_post_meta($id, 'campaign_id',   $campaignId);
        update_post_meta($id, 'status',        'active');
        update_post_meta($id, 'schedule_type', $scheduleType);
        update_post_meta($id, 'send_time',     sanitize_text_field($sendTime));
        update_post_meta($id, 'send_day',      $sendDay);
        update_post_meta($id, 'send_date',     sanitize_text_field($sendDate));
        update_post_meta($id, 'next_send',     $nextSend);
        update_post_meta($id, 'end_date',      sanitize_text_field($endDate));

        return $id;
    }

    /**
     * Updates all editable fields on an existing scheduler and recomputes next_send.
     *
     * @param int    $id           Scheduler post ID.
     * @param string $name         Display name.
     * @param int    $campaignId   Campaign post ID.
     * @param string $scheduleType one of: once, weekly, monthly.
     * @param string $sendTime     Time of day in H:i (site timezone).
     * @param int    $sendDay      Day-of-week or day-of-month.
     * @param string $sendDate     YYYY-MM-DD for once-type schedules.
     * @param string $endDate      Optional cutoff date.
     */
    public static function update(int $id, string $name, int $campaignId, string $scheduleType, string $sendTime, int $sendDay, string $sendDate = '', string $endDate = ''): void
    {
        wp_update_post(['ID' => $id, 'post_title' => sanitize_text_field($name)]);

        $nextSend = self::computeNextSend($scheduleType, $sendTime, $sendDay, $sendDate);

        update_post_meta($id, 'campaign_id',   $campaignId);
        update_post_meta($id, 'schedule_type', $scheduleType);
        update_post_meta($id, 'send_time',     sanitize_text_field($sendTime));
        update_post_meta($id, 'send_day',      $sendDay);
        update_post_meta($id, 'send_date',     sanitize_text_field($sendDate));
        update_post_meta($id, 'next_send',     $nextSend);
        update_post_meta($id, 'end_date',      sanitize_text_field($endDate));
    }

    /**
     * Sets arbitrary meta keys on a scheduler record (used by SchedulerCron to update status/next_send).
     *
     * @param int   $id   Scheduler post ID.
     * @param array $data Key-value pairs to write as post meta.
     */
    public static function updateMeta(int $id, array $data): void
    {
        foreach ($data as $key => $value) {
            update_post_meta($id, $key, $value);
        }
    }

    /**
     * Permanently deletes a scheduler record.
     *
     * @param int $id Scheduler post ID.
     */
    public static function delete(int $id): void
    {
        wp_delete_post($id, true);
    }

    /**
     * Computes the Unix timestamp for the next scheduled send based on schedule settings.
     *
     * All times are interpreted in the WordPress site timezone (wp_timezone()).
     *
     * @param string $scheduleType once | weekly | monthly.
     * @param string $sendTime     H:i format.
     * @param int    $sendDay      Day-of-week (0-6) for weekly; day-of-month (1-31) for monthly.
     * @param string $sendDate     YYYY-MM-DD, required for once-type.
     * @return int Unix timestamp.
     */
    public static function computeNextSend(string $scheduleType, string $sendTime, int $sendDay, string $sendDate = ''): int
    {
        $tz     = wp_timezone();
        $now    = new \DateTimeImmutable('now', $tz);
        $parts  = explode(':', $sendTime);
        $hour   = isset($parts[0]) ? (int) $parts[0] : 9;
        $minute = isset($parts[1]) ? (int) $parts[1] : 0;

        switch ($scheduleType) {
            case 'once':
                if (!$sendDate) {
                    return $now->getTimestamp() + 86400;
                }
                try {
                    $dt = (new \DateTimeImmutable($sendDate, $tz))->setTime($hour, $minute, 0);
                } catch (\Throwable $e) {
                    return $now->getTimestamp() + 86400;
                }
                return $dt->getTimestamp();

            case 'weekly':
                // send_day uses PHP date('w'): 0=Sunday … 6=Saturday
                $dt         = $now->setTime($hour, $minute, 0);
                $currentDay = (int) $now->format('w');
                $daysAhead  = ($sendDay - $currentDay + 7) % 7;
                if ($daysAhead === 0 && $dt->getTimestamp() <= $now->getTimestamp()) {
                    $daysAhead = 7;
                }
                return $dt->modify("+{$daysAhead} days")->getTimestamp();

            case 'monthly':
                // send_day: 1-31; clamped to last day of month if the month is shorter
                $year   = (int) $now->format('Y');
                $month  = (int) $now->format('n');
                $maxDay = (int) $now->format('t');
                $day    = min($sendDay, $maxDay);

                try {
                    $dt = $now->setDate($year, $month, $day)->setTime($hour, $minute, 0);
                } catch (\Throwable $e) {
                    return $now->getTimestamp() + 86400 * 30;
                }

                if ($dt->getTimestamp() <= $now->getTimestamp()) {
                    $dt     = $dt->modify('+1 month');
                    $maxDay = (int) $dt->format('t');
                    $day    = min($sendDay, $maxDay);
                    $dt     = $dt->setDate((int) $dt->format('Y'), (int) $dt->format('n'), $day)->setTime($hour, $minute, 0);
                }
                return $dt->getTimestamp();
        }

        return $now->getTimestamp() + 86400;
    }

    /**
     * Clears a campaign's send state so SchedulerCron can trigger a fresh background send.
     *
     * Removes campaignStarted, campaignFinished, and backgroundStarted timestamps, then
     * deletes the sent_{id} meta from every subscriber across the campaign's audiences.
     * This allows CronSend::processBatch() to treat all subscribers as unsent.
     *
     * For weekly/monthly (recurring) schedules where the campaign's rerender_on_recurring flag
     * is enabled, the email_template_copied meta is also deleted so CronSend re-fetches and
     * re-renders the template fresh (picking up updated shortcode output, WP queries, etc.).
     *
     * @param int    $campaignPostId Campaign post ID.
     * @param string $scheduleType   Scheduler type: 'once', 'weekly', or 'monthly'.
     */
    public static function resetCampaignForResend(int $campaignPostId, string $scheduleType = 'once'): void
    {
        delete_post_meta($campaignPostId, 'campaignStarted');
        delete_post_meta($campaignPostId, 'campaignFinished');
        delete_post_meta($campaignPostId, 'backgroundStarted');

        $campaign = Campaigns::getCampaignById($campaignPostId);
        if (!$campaign) {
            return;
        }

        if (in_array($scheduleType, ['weekly', 'monthly'], true) && !empty($campaign->rerender_on_recurring)) {
            delete_post_meta($campaignPostId, 'email_template_copied');
        }

        foreach ((array) $campaign->audiences as $audienceId) {
            $subscribers = Subscribers::getSubscribersByAudience((int) $audienceId);
            foreach ($subscribers as $subscriber) {
                delete_post_meta($subscriber->id, 'sent_' . $campaignPostId);
            }
        }
    }
}
