<?php

namespace Mawiblah;

/**
 * Bounced e-mails: read from a mailbox, held for approval, then applied.
 *
 * A check only reads. It walks the mailbox from where the previous check
 * stopped, parses anything that is a delivery report, and records one pending
 * row per failed recipient. Nothing happens to a subscriber or to the mailbox
 * until somebody decides on the Bounced Emails page -- and whichever way they
 * decide, the report is then deleted from the mailbox:
 *
 * - Count as failure: one more on the subscriber's email_fail_count, the
 *   counter a send that fails on the spot also adds to. At the Failing Email
 *   threshold they are moved into that audience, by the same rule.
 * - Move to Failing Email: straight away, whatever the count -- for an address
 *   that plainly does not exist.
 * - Dismiss: the subscriber is left alone.
 *
 * Which of the first two a bounce deserves is the reader's call; the hard (5.x.x)
 * and soft (4.x.x) label on the page is there to inform it, not to make it.
 */
class Bounces
{
    public const STATE_PENDING   = 'pending';
    public const STATE_RESOLVED  = 'resolved';
    public const STATE_DISMISSED = 'dismissed';

    /** How a resolved bounce was applied to its subscriber. */
    public const RESOLUTION_MOVED         = 'moved';
    public const RESOLUTION_COUNTED       = 'counted';
    public const RESOLUTION_COUNTED_MOVED = 'counted_moved';
    public const RESOLUTION_NO_SUBSCRIBER = 'no_subscriber';

    public const CRON_HOOK     = 'mawiblah_bounce_check';
    public const CONTINUE_HOOK = 'mawiblah_bounce_check_continue';
    public const ADMIN_ACTION  = 'mawiblah_bounces';

    private const CURSOR_OPTION     = 'mawiblah_bounce_cursor';
    private const LAST_CHECK_OPTION = 'mawiblah_bounce_last_check';
    private const LOCK_TRANSIENT    = 'mawiblah_bounce_check_lock';
    private const NOTICE_TRANSIENT  = 'mawiblah_bounce_notices_';

    /** The bounce table's name. */
    public static function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'mawiblah_bounces';
    }

    /** True once the 1.1.0 migration has created the table. */
    public static function installed(): bool
    {
        return version_compare((string) get_option('mawiblah_db_version'), '1.1.0', '>=');
    }

    /**
     * Creates or updates the table.
     *
     * One row per failed recipient of a report, so a report naming two addresses
     * is two rows sharing one mailbox message. The unique key is what makes a
     * check safe to repeat: reading the same report again inserts nothing.
     */
    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table   = self::table();
        $charset = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  mailbox varchar(191) NOT NULL DEFAULT '',
  uidvalidity bigint(20) unsigned NOT NULL DEFAULT 0,
  uid bigint(20) unsigned NOT NULL DEFAULT 0,
  message_id varchar(255) NOT NULL DEFAULT '',
  bounced_at datetime NOT NULL,
  recipient varchar(191) NOT NULL DEFAULT '',
  subscriber_id bigint(20) unsigned NOT NULL DEFAULT 0,
  campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
  kind varchar(10) NOT NULL DEFAULT '',
  status_code varchar(20) NOT NULL DEFAULT '',
  action varchar(20) NOT NULL DEFAULT '',
  reason text,
  subject varchar(255) NOT NULL DEFAULT '',
  state varchar(20) NOT NULL DEFAULT 'pending',
  resolution varchar(20) NOT NULL DEFAULT '',
  message_deleted tinyint(1) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  handled_at datetime DEFAULT NULL,
  handled_by bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY message_recipient (mailbox,uidvalidity,uid,recipient),
  KEY state (state),
  KEY recipient (recipient)
) {$charset};");
    }

    /** Registers the cron handlers and keeps the hourly event in line with the settings. */
    public static function init(): void
    {
        add_action(self::CRON_HOOK, [self::class, 'runScheduledCheck']);
        add_action(self::CONTINUE_HOOK, [self::class, 'runScheduledCheck']);

        $scheduled = wp_next_scheduled(self::CRON_HOOK);
        $wanted    = Settings::bouncesEnabled() && BounceMailbox::configured();

        if ($wanted && !$scheduled) {
            wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK);
        } elseif (!$wanted && $scheduled) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
            wp_clear_scheduled_hook(self::CONTINUE_HOOK);
        }
    }

    /** Registers the Bounced Emails page's form handler. */
    public static function bootAdmin(): void
    {
        add_action('admin_post_' . self::ADMIN_ACTION, [self::class, 'handleAdminPost']);
    }

    /**
     * Cron callback. A mailbox with more waiting than one batch is worked
     * through in further runs a minute apart rather than in one long request.
     */
    public static function runScheduledCheck(): void
    {
        if (!Settings::bouncesEnabled() || !BounceMailbox::configured()) {
            return;
        }

        $summary = self::check();

        if ($summary['hasMore'] && !wp_next_scheduled(self::CONTINUE_HOOK)) {
            wp_schedule_single_event(time() + MINUTE_IN_SECONDS, self::CONTINUE_HOOK);
        }
    }

    /**
     * Reads the mailbox from where the previous check stopped and records bounces.
     *
     * Only reads. Messages are fetched with BODY.PEEK, so they stay unread, and
     * nothing is flagged, moved or deleted. Only a message whose headers say it
     * is a report has its body fetched at all.
     *
     * @param int|null $limit Messages to read; the setting when null.
     * @return array{time: int, scanned: int, bounces: int, recorded: int, hasMore: bool, error: string, elapsedMs: int}
     */
    public static function check(?int $limit = null): array
    {
        $summary = [
            'time'      => time(),
            'scanned'   => 0,
            'bounces'   => 0,
            'recorded'  => 0,
            'hasMore'   => false,
            'error'     => '',
            'elapsedMs' => 0,
        ];

        // A cron run and a "Check now" click can overlap. The unique key would
        // stop duplicate rows, but two readers would fight over the cursor.
        if (get_transient(self::LOCK_TRANSIENT)) {
            $summary['error'] = __('Another check is still running.', 'mawiblah');

            return $summary;
        }

        set_transient(self::LOCK_TRANSIENT, 1, 10 * MINUTE_IN_SECONDS);
        $started = microtime(true);

        try {
            $folder      = BounceMailbox::folder();
            $mailbox     = BounceMailbox::key();
            $uidValidity = BounceMailbox::uidValidity($folder);
            $after       = self::cursor($mailbox, $uidValidity);
            $limit       = $limit ?? Settings::bounceBatchSize();

            // One more than the batch, only to learn whether anything is left.
            $heads              = BounceMailbox::headersAfter($folder, $after, $limit + 1);
            $summary['hasMore'] = count($heads) > $limit;

            foreach (array_slice($heads, 0, $limit, true) as $uid => $head) {
                if (BounceMailbox::looksLikeReport($head)) {
                    $parsed = BounceParser::parse(BounceMailbox::raw($folder, $uid));

                    if ($parsed) {
                        $summary['bounces']++;
                        $summary['recorded'] += self::record($parsed, $mailbox, $uidValidity, $uid, $head);
                    }
                }

                // Per message, not per batch: a run cut short costs nothing.
                self::saveCursor($mailbox, $uidValidity, $uid);
                $summary['scanned']++;
            }
        } catch (\Throwable $e) {
            $summary['error'] = $e->getMessage();
            Logs::addError('bounces', 'Bounce check failed: ' . $e->getMessage());
        } finally {
            BounceMailbox::disconnect();
            delete_transient(self::LOCK_TRANSIENT);
        }

        $summary['elapsedMs'] = (int) round((microtime(true) - $started) * 1000);

        update_option(self::LAST_CHECK_OPTION, $summary, false);
        Logs::addLog('bounces', 'Bounce check finished', $summary);

        return $summary;
    }

    /**
     * Records one pending row per failed recipient of a parsed report.
     *
     * @return int Rows inserted; a report read before inserts none.
     */
    private static function record(array $parsed, string $mailbox, int $uidValidity, int $uid, string $head): int
    {
        global $wpdb;

        $campaignId = 0;

        if ($parsed['campaignHash'] !== '') {
            $campaign   = Campaigns::getCampaignByHash($parsed['campaignHash']);
            $campaignId = $campaign ? (int) ($campaign->id ?? $campaign->ID ?? 0) : 0;
        }

        $bySubscriberHash = $parsed['subscriberHash'] !== ''
            ? Subscribers::getSubscriberBySubscriberHash($parsed['subscriberHash'])
            : null;

        $bouncedAt = strtotime(BounceMailbox::headerValue($head, 'Date'));
        $bouncedAt = gmdate('Y-m-d H:i:s', $bouncedAt ?: time());
        $messageId = mb_substr(BounceMailbox::headerValue($head, 'Message-ID'), 0, 255);
        $inserted  = 0;

        foreach ($parsed['recipients'] as $recipient) {
            // The quoted header names the subscriber we sent to; the address is
            // the fallback for mail sent before the header existed.
            $subscriber = $bySubscriberHash
                && (count($parsed['recipients']) === 1 || strtolower((string) $bySubscriberHash->email) === $recipient['email'])
                ? $bySubscriberHash
                : Subscribers::getSubscriber($recipient['email']);

            $inserted += (int) $wpdb->query($wpdb->prepare(
                'INSERT IGNORE INTO ' . self::table() . '
                    (mailbox, uidvalidity, uid, message_id, bounced_at, recipient, subscriber_id, campaign_id,
                     kind, status_code, action, reason, subject, state, created_at)
                 VALUES (%s, %d, %d, %s, %s, %s, %d, %d, %s, %s, %s, %s, %s, %s, %s)',
                $mailbox,
                $uidValidity,
                $uid,
                $messageId,
                $bouncedAt,
                mb_substr($recipient['email'], 0, 191),
                $subscriber ? (int) $subscriber->id : 0,
                $campaignId,
                $recipient['kind'],
                $recipient['status'],
                $recipient['action'],
                $recipient['reason'],
                $parsed['originalSubject'],
                self::STATE_PENDING,
                gmdate('Y-m-d H:i:s')
            ));
        }

        return $inserted;
    }

    /**
     * Counts a bounce as one failure on its subscriber -- moving them into
     * Failing Email if that reaches the threshold -- and deletes the report.
     *
     * @return array{handled: bool, error: string, moved: bool} Handled even when
     *   the report could not be deleted -- the subscriber side has already
     *   happened -- in which case error says why it is still in the mailbox.
     *   Moved when the subscriber ended up in Failing Email.
     */
    public static function countAsFailure(int $id, int $userId = 0): array
    {
        return self::handle($id, self::STATE_RESOLVED, self::RESOLUTION_COUNTED, $userId);
    }

    /**
     * Moves a bounce's subscriber into Failing Email straight away and deletes the report.
     *
     * @return array{handled: bool, error: string, moved: bool}
     */
    public static function moveToFailingEmail(int $id, int $userId = 0): array
    {
        return self::handle($id, self::STATE_RESOLVED, self::RESOLUTION_MOVED, $userId);
    }

    /**
     * Dismisses a bounce: the subscriber is left alone, the report deleted.
     *
     * @return array{handled: bool, error: string, moved: bool}
     */
    public static function dismiss(int $id, int $userId = 0): array
    {
        return self::handle($id, self::STATE_DISMISSED, '', $userId);
    }

    /**
     * @param string $how RESOLUTION_COUNTED or RESOLUTION_MOVED; '' for a dismissal.
     * @return array{handled: bool, error: string, moved: bool}
     */
    private static function handle(int $id, string $state, string $how, int $userId): array
    {
        global $wpdb;

        $row = self::get($id);

        if (!$row) {
            return ['handled' => false, 'error' => sprintf(__('Bounce #%d no longer exists.', 'mawiblah'), $id), 'moved' => false];
        }

        if ($row->state !== self::STATE_PENDING) {
            return ['handled' => false, 'error' => sprintf(__('Bounce #%d has already been handled.', 'mawiblah'), $id), 'moved' => false];
        }

        $resolution = $state === self::STATE_RESOLVED ? self::applyToSubscriber($row, $how) : '';
        $deleted    = self::deleteReport($row);

        $wpdb->update(
            self::table(),
            ['state' => $state, 'resolution' => $resolution, 'handled_at' => gmdate('Y-m-d H:i:s'), 'handled_by' => $userId],
            ['id' => $id]
        );

        Logs::addLog('bounces', "Bounce #{$id} {$state}" . ($resolution !== '' ? " ({$resolution})" : ''), [
            'recipient'     => $row->recipient,
            'subscriberId'  => (int) $row->subscriber_id,
            'kind'          => $row->kind,
            'status'        => $row->status_code,
            'resolution'    => $resolution,
            'reportDeleted' => $deleted === true,
            'error'         => $deleted === true ? '' : $deleted,
        ]);

        return [
            'handled' => true,
            'error'   => $deleted === true ? '' : $deleted,
            'moved'   => in_array($resolution, [self::RESOLUTION_MOVED, self::RESOLUTION_COUNTED_MOVED], true),
        ];
    }

    /**
     * Records the bounce on its subscriber, then counts it or moves them.
     *
     * @param string $how RESOLUTION_COUNTED or RESOLUTION_MOVED.
     * @return string The resolution that happened, one of the RESOLUTION_* values.
     */
    private static function applyToSubscriber(object $row, string $how): string
    {
        global $wpdb;

        $subscriberId = (int) $row->subscriber_id;

        // Matched at approval if not when read: the subscriber may be newer
        // than the bounce, or have been re-imported since.
        if (!$subscriberId || get_post_type($subscriberId) !== Subscribers::postType()) {
            $subscriber   = Subscribers::getSubscriber($row->recipient);
            $subscriberId = $subscriber ? (int) $subscriber->id : 0;

            if ($subscriberId) {
                $wpdb->update(self::table(), ['subscriber_id' => $subscriberId], ['id' => (int) $row->id]);
            }
        }

        if (!$subscriberId) {
            return self::RESOLUTION_NO_SUBSCRIBER;
        }

        $countKey = $row->kind === BounceParser::KIND_HARD ? 'bounce_hard_count' : 'bounce_soft_count';

        update_post_meta($subscriberId, $countKey, (int) get_post_meta($subscriberId, $countKey, true) + 1);
        update_post_meta($subscriberId, 'bounce_last_at', $row->bounced_at);
        update_post_meta($subscriberId, 'bounce_last_status', $row->status_code);
        update_post_meta($subscriberId, 'bounce_last_reason', (string) $row->reason);
        update_post_meta($subscriberId, 'bounce_last_campaign', (int) $row->campaign_id);

        if ($how === self::RESOLUTION_MOVED) {
            Subscribers::moveToFailingEmail($subscriberId);

            return self::RESOLUTION_MOVED;
        }

        return Subscribers::countFailure($subscriberId) ? self::RESOLUTION_COUNTED_MOVED : self::RESOLUTION_COUNTED;
    }

    /**
     * Deletes a row's report from the mailbox.
     *
     * Refuses rather than guesses: a UID only means the same message while the
     * mailbox settings and its UIDVALIDITY are the ones the report was read
     * under, and deleting some other e-mail is not a mistake to risk.
     *
     * @return true|string True, or why the report was left where it is.
     */
    private static function deleteReport(object $row)
    {
        global $wpdb;

        $where = ['mailbox' => $row->mailbox, 'uidvalidity' => (int) $row->uidvalidity, 'uid' => (int) $row->uid];

        // Another recipient of the same report may already have taken it.
        $alreadyDeleted = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT MAX(message_deleted) FROM ' . self::table() . ' WHERE mailbox = %s AND uidvalidity = %d AND uid = %d',
            $where['mailbox'],
            $where['uidvalidity'],
            $where['uid']
        ));

        if ($alreadyDeleted) {
            $wpdb->update(self::table(), ['message_deleted' => 1], ['id' => (int) $row->id]);

            return true;
        }

        try {
            if (BounceMailbox::key() !== $row->mailbox) {
                return __('The mailbox settings have changed since this bounce was read, so its report was left in the mailbox.', 'mawiblah');
            }

            $folder = BounceMailbox::folder();

            if (BounceMailbox::uidValidity($folder) !== (int) $row->uidvalidity) {
                return __('The mailbox has been rebuilt since this bounce was read (its UIDVALIDITY changed), so its report was left in place rather than risk deleting a different e-mail.', 'mawiblah');
            }

            BounceMailbox::delete($folder, (int) $row->uid);
        } catch (\Throwable $e) {
            return sprintf(__('The report could not be deleted from the mailbox: %s', 'mawiblah'), $e->getMessage());
        }

        $wpdb->update(self::table(), ['message_deleted' => 1], $where);

        return true;
    }

    /** A single row, or null. */
    public static function get(int $id): ?object
    {
        global $wpdb;

        if (!self::installed()) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id));

        return $row ?: null;
    }

    /**
     * Rows in one state, newest bounce first.
     *
     * @return object[]
     */
    public static function rows(string $state, int $page = 1, int $perPage = 50): array
    {
        global $wpdb;

        if (!self::installed()) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE state = %s ORDER BY bounced_at DESC, id DESC LIMIT %d OFFSET %d',
            $state,
            $perPage,
            max(0, ($page - 1) * $perPage)
        ));
    }

    /**
     * Rows per state.
     *
     * @return array<string, int>
     */
    public static function counts(): array
    {
        global $wpdb;

        $counts = [self::STATE_PENDING => 0, self::STATE_RESOLVED => 0, self::STATE_DISMISSED => 0];

        if (!self::installed()) {
            return $counts;
        }

        foreach ((array) $wpdb->get_results('SELECT state, COUNT(*) AS n FROM ' . self::table() . ' GROUP BY state') as $row) {
            $counts[$row->state] = (int) $row->n;
        }

        return $counts;
    }

    /** What the most recent check found, or null before the first. */
    public static function lastCheck(): ?array
    {
        $last = get_option(self::LAST_CHECK_OPTION);

        return is_array($last) ? $last : null;
    }

    /** The UID the previous check stopped at, or 0 when the mailbox is not the one it read. */
    private static function cursor(string $mailbox, int $uidValidity): int
    {
        $cursor = get_option(self::CURSOR_OPTION);

        if (
            !is_array($cursor)
            || ($cursor['mailbox'] ?? '') !== $mailbox
            || (int) ($cursor['uidvalidity'] ?? -1) !== $uidValidity
        ) {
            return 0;
        }

        return (int) ($cursor['uid'] ?? 0);
    }

    private static function saveCursor(string $mailbox, int $uidValidity, int $uid): void
    {
        update_option(self::CURSOR_OPTION, ['mailbox' => $mailbox, 'uidvalidity' => $uidValidity, 'uid' => $uid], false);
    }

    /** Answers the Bounced Emails page's forms, then redirects back to it. */
    public static function handleAdminPost(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do that.', 'mawiblah'), 403);
        }

        check_admin_referer(self::ADMIN_ACTION);

        $do  = sanitize_key(wp_unslash($_POST['do'] ?? ''));
        $ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));

        // A row's own button wins over whatever is ticked for the bulk action.
        $rowAction = sanitize_text_field(wp_unslash($_POST['row_action'] ?? ''));

        if (preg_match('/^(count|fail|dismiss):(\d+)$/', $rowAction, $match)) {
            $do  = $match[1];
            $ids = [(int) $match[2]];
        } elseif ($do === 'bulk') {
            $do = sanitize_key(wp_unslash($_POST['bulk_action'] ?? ''));
        }

        $userId  = get_current_user_id();
        $notices = [];

        switch ($do) {
            case 'check':
                $summary = self::check();

                if ($summary['error'] !== '') {
                    $notices[] = ['error', sprintf(__('The check failed: %s', 'mawiblah'), $summary['error'])];
                    break;
                }

                $notices[] = ['success', sprintf(
                    /* translators: 1: messages read, 2: bounce reports found, 3: new rows */
                    __('Read %1$d messages: %2$d bounce reports, %3$d new bounces to review.', 'mawiblah'),
                    $summary['scanned'],
                    $summary['bounces'],
                    $summary['recorded']
                )];

                if ($summary['hasMore']) {
                    $notices[] = ['info', __('More messages are waiting. Check again, or let the hourly check work through them.', 'mawiblah')];
                }
                break;

            case 'count':
            case 'fail':
            case 'dismiss':
                if (!$ids) {
                    $notices[] = ['warning', __('No bounces were selected.', 'mawiblah')];
                    break;
                }

                $handled = 0;
                $moved   = 0;

                foreach ($ids as $id) {
                    $result = match ($do) {
                        'count' => self::countAsFailure($id, $userId),
                        'fail'  => self::moveToFailingEmail($id, $userId),
                        default => self::dismiss($id, $userId),
                    };

                    $handled += $result['handled'] ? 1 : 0;
                    $moved   += $result['moved'] ? 1 : 0;

                    if ($result['error'] !== '') {
                        $notices[] = ['error', $result['error']];
                    }
                }

                BounceMailbox::disconnect();

                if ($do === 'count') {
                    $notices[] = ['success', sprintf(_n('%d bounce counted as a failure.', '%d bounces counted as failures.', $handled, 'mawiblah'), $handled)];

                    if ($moved) {
                        $notices[] = ['info', sprintf(
                            _n('%d subscriber reached the failure threshold and was moved to Failing Email.', '%d subscribers reached the failure threshold and were moved to Failing Email.', $moved, 'mawiblah'),
                            $moved
                        )];
                    }
                } elseif ($do === 'fail') {
                    $notices[] = ['success', sprintf(_n('%d subscriber moved to Failing Email.', '%d subscribers moved to Failing Email.', $moved, 'mawiblah'), $moved)];

                    if ($handled > $moved) {
                        $notices[] = ['info', sprintf(
                            _n('%d bounce had no subscriber to move; its report was deleted.', '%d bounces had no subscriber to move; their reports were deleted.', $handled - $moved, 'mawiblah'),
                            $handled - $moved
                        )];
                    }
                } else {
                    $notices[] = ['success', sprintf(_n('%d bounce dismissed.', '%d bounces dismissed.', $handled, 'mawiblah'), $handled)];
                }
                break;
        }

        set_transient(self::NOTICE_TRANSIENT . $userId, $notices, 5 * MINUTE_IN_SECONDS);

        $state = sanitize_key(wp_unslash($_POST['state'] ?? self::STATE_PENDING));

        wp_safe_redirect(add_query_arg(['page' => Init::MAWIBLAH_BOUNCES, 'state' => $state], admin_url('admin.php')));
        exit;
    }

    /**
     * The notices the last form submission left, cleared as they are read.
     *
     * @return array<int, array{0: string, 1: string}> [type, message] pairs.
     */
    public static function takeNotices(): array
    {
        $key     = self::NOTICE_TRANSIENT . get_current_user_id();
        $notices = get_transient($key);

        delete_transient($key);

        return is_array($notices) ? $notices : [];
    }

    /** Drops the table, the options and the cron events. Called on uninstall. */
    public static function uninstall(): void
    {
        global $wpdb;

        $wpdb->query('DROP TABLE IF EXISTS ' . self::table());

        delete_option(self::CURSOR_OPTION);
        delete_option(self::LAST_CHECK_OPTION);
        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::CONTINUE_HOOK);
    }
}
