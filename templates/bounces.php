<?php
defined('ABSPATH') || exit;

use Mawiblah\BounceMailbox;
use Mawiblah\BounceParser;
use Mawiblah\Bounces;
use Mawiblah\Init;
use Mawiblah\Settings;
use Mawiblah\Subscribers;

$states = [
    Bounces::STATE_PENDING   => __('Waiting for approval', 'mawiblah'),
    Bounces::STATE_RESOLVED  => __('Approved', 'mawiblah'),
    Bounces::STATE_DISMISSED => __('Dismissed', 'mawiblah'),
];

$state = isset($_GET['state']) ? sanitize_key(wp_unslash($_GET['state'])) : Bounces::STATE_PENDING;
$state = isset($states[$state]) ? $state : Bounces::STATE_PENDING;

// Each kind's badge colour and meaning, shared by the rows and the type filter.
$kinds = [
    BounceParser::KIND_HARD => [
        'label' => __('Hard', 'mawiblah'),
        'color' => '#d63638',
        'title' => __('Permanent failure (5.x.x): the address does not exist or refuses mail.', 'mawiblah'),
    ],
    BounceParser::KIND_SOFT => [
        'label' => __('Soft', 'mawiblah'),
        'color' => '#dba617',
        'title' => __('Temporary failure (4.x.x): mailbox full or server unavailable.', 'mawiblah'),
    ],
    BounceParser::KIND_SPAM => [
        'label' => __('Spam', 'mawiblah'),
        'color' => '#8c8f94',
        'title' => __('Refused by a spam or policy filter (5.7.x): the address works, this e-mail was not accepted.', 'mawiblah'),
    ],
    BounceParser::KIND_QUOTA => [
        'label' => __('Over quota', 'mawiblah'),
        'color' => '#2271b1',
        'title' => __('Mailbox full (x.2.2): the address works but has run out of space; mail gets through again once space is freed.', 'mawiblah'),
    ],
];

$kind = isset($_GET['kind']) ? sanitize_key(wp_unslash($_GET['kind'])) : '';
$kind = isset($kinds[$kind]) ? $kind : '';

$perPage    = 50;
$paged      = max(1, (int) ($_GET['paged'] ?? 1));
$counts     = Bounces::counts();
$kindCounts = Bounces::kindCounts($state);
$total      = $kind !== '' ? $kindCounts[$kind] : ($counts[$state] ?? 0);
$pages      = max(1, (int) ceil($total / $perPage));
$rows       = Bounces::rows($state, min($paged, $pages), $perPage, $kind);
$notices    = Bounces::takeNotices();
$lastCheck  = Bounces::lastCheck();
$configured = BounceMailbox::configured();
$enabled    = Settings::bouncesEnabled();
$nextRun    = wp_next_scheduled(Bounces::CRON_HOOK);
$failing    = Subscribers::failingEmailAudience();
$threshold  = Settings::failingEmailThreshold();
$pageUrl    = admin_url('admin.php?page=' . Init::MAWIBLAH_BOUNCES);
$settingsUrl = admin_url('admin.php?page=' . MAWIBLAH_SETTINGS_PAGE);
$dateFormat = get_option('date_format') . ' ' . get_option('time_format');

$when = static function ($gmt) use ($dateFormat): string {
    return $gmt ? wp_date($dateFormat, strtotime($gmt . ' UTC')) : '—';
};
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Mawiblah — Bounced Emails', 'mawiblah'); ?></h1>
    <hr class="wp-header-end">

    <?php foreach ($notices as [$type, $message]) : ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endforeach; ?>

    <div class="metabox-holder">

    <!-- ── Mailbox status ────────────────────────────────────────────────── -->
    <div class="postbox" style="margin-top:4px;">
        <div class="inside" style="padding-top:12px;">
            <p style="max-width:860px;">
                <?php esc_html_e('Bounce reports are read from the mailbox and listed here. Nothing happens to a subscriber or to the mailbox until you decide:', 'mawiblah'); ?>
            </p>
            <ul style="list-style:disc;padding-left:1.5em;max-width:860px;">
                <li>
                    <strong><?php esc_html_e('Count as failure', 'mawiblah'); ?></strong> —
                    <?php
                    printf(
                        /* translators: 1: failure threshold, 2: audience name */
                        esc_html__('adds one to the subscriber\'s failure count, the same count a send that fails on the spot adds to. At %1$d failures they are moved into %2$s and stop receiving campaigns.', 'mawiblah'),
                        (int) $threshold,
                        '<strong>' . esc_html($failing ? $failing->name : 'Failing Email') . '</strong>'
                    );
                    ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Move to Failing Email', 'mawiblah'); ?></strong> —
                    <?php esc_html_e('moves the subscriber there straight away, whatever the count. For an address that plainly does not exist: a hard bounce such as 5.1.1 "user unknown".', 'mawiblah'); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Dismiss', 'mawiblah'); ?></strong> —
                    <?php esc_html_e('leaves the subscriber alone.', 'mawiblah'); ?>
                </li>
            </ul>
            <p style="max-width:860px;"><?php esc_html_e('Whichever you choose, the bounce report is deleted from the mailbox. Hard (red, 5.x.x) means the address does not exist; soft (yellow, 4.x.x) means something temporary, like a server that is down; spam (gray, 5.7.x) means a spam or policy filter refused the e-mail while the address itself works — usually one to dismiss; over quota (blue, x.2.2) means the mailbox is full — usually one to count.', 'mawiblah'); ?></p>

            <table class="widefat striped" style="max-width:860px;margin-top:12px;">
                <tbody>
                    <tr>
                        <th style="width:30%;"><?php esc_html_e('Mailbox', 'mawiblah'); ?></th>
                        <td>
                            <?php if ($configured) : ?>
                                <code><?php echo esc_html(BounceMailbox::key()); ?></code>
                            <?php else : ?>
                                <?php esc_html_e('Not configured.', 'mawiblah'); ?>
                                <a href="<?php echo esc_url($settingsUrl); ?>"><?php esc_html_e('Settings → Bounced Emails', 'mawiblah'); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Hourly check', 'mawiblah'); ?></th>
                        <td>
                            <?php
                            if (!$enabled) {
                                esc_html_e('Off — only "Check mailbox now" reads the mailbox.', 'mawiblah');
                            } elseif ($nextRun) {
                                printf(
                                    /* translators: %s: date and time */
                                    esc_html__('On — next run %s', 'mawiblah'),
                                    esc_html(wp_date($dateFormat, $nextRun))
                                );
                            } else {
                                esc_html_e('On, but not scheduled yet — it is scheduled on the next page load once the mailbox is configured.', 'mawiblah');
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Last check', 'mawiblah'); ?></th>
                        <td>
                            <?php if (!$lastCheck) : ?>
                                <?php esc_html_e('Never.', 'mawiblah'); ?>
                            <?php elseif ($lastCheck['error'] !== '') : ?>
                                <?php echo esc_html(wp_date($dateFormat, (int) $lastCheck['time'])); ?> —
                                <span style="color:#d63638;"><?php echo esc_html($lastCheck['error']); ?></span>
                            <?php else : ?>
                                <?php
                                printf(
                                    /* translators: 1: date and time, 2: messages read, 3: reports found, 4: new rows */
                                    esc_html__('%1$s — read %2$d messages, %3$d bounce reports, %4$d new.', 'mawiblah'),
                                    esc_html(wp_date($dateFormat, (int) $lastCheck['time'])),
                                    (int) $lastCheck['scanned'],
                                    (int) $lastCheck['bounces'],
                                    (int) $lastCheck['recorded']
                                );
                                ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                <?php wp_nonce_field(Bounces::ADMIN_ACTION); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(Bounces::ADMIN_ACTION); ?>">
                <input type="hidden" name="do" value="check">
                <input type="hidden" name="state" value="<?php echo esc_attr($state); ?>">
                <input type="hidden" name="kind" value="<?php echo esc_attr($kind); ?>">
                <button type="submit" class="button button-secondary" <?php disabled(!$configured); ?>>
                    <span class="dashicons dashicons-update" style="vertical-align:middle;margin-top:-3px;"></span>
                    <?php esc_html_e('Check mailbox now', 'mawiblah'); ?>
                </button>
            </form>
        </div>
    </div>

    <!-- ── Bounces ────────────────────────────────────────────────────────── -->
    <ul class="subsubsub">
        <?php $links = []; ?>
        <?php foreach ($states as $key => $label) : ?>
            <?php
            $links[] = sprintf(
                '<li><a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg(['state' => $key, 'kind' => $kind !== '' ? $kind : false], $pageUrl)),
                $key === $state ? ' class="current" aria-current="page"' : '',
                esc_html($label),
                (int) $counts[$key]
            );
            ?>
        <?php endforeach; ?>
        <?php echo implode(' | </li>', $links) . '</li>'; ?>
    </ul>
    <br class="clear">

    <ul class="subsubsub" style="margin-top:0;">
        <li><?php esc_html_e('Type:', 'mawiblah'); ?>&nbsp;</li>
        <?php
        $kindLinks = [sprintf(
            '<li><a href="%s"%s>%s <span class="count">(%d)</span></a>',
            esc_url(add_query_arg(['state' => $state], $pageUrl)),
            $kind === '' ? ' class="current" aria-current="page"' : '',
            esc_html__('All', 'mawiblah'),
            (int) ($counts[$state] ?? 0)
        )];

        foreach ($kinds as $key => $meta) {
            $kindLinks[] = sprintf(
                '<li><a href="%s"%s title="%s"><span style="display:inline-block;width:8px;height:8px;border-radius:50%%;background:%s;margin-right:4px;"></span>%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg(['state' => $state, 'kind' => $key], $pageUrl)),
                $key === $kind ? ' class="current" aria-current="page"' : '',
                esc_attr($meta['title']),
                esc_attr($meta['color']),
                esc_html($meta['label']),
                (int) $kindCounts[$key]
            );
        }

        echo implode(' | </li>', $kindLinks) . '</li>';
        ?>
    </ul>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field(Bounces::ADMIN_ACTION); ?>
        <input type="hidden" name="action" value="<?php echo esc_attr(Bounces::ADMIN_ACTION); ?>">
        <input type="hidden" name="do" value="bulk">
        <input type="hidden" name="state" value="<?php echo esc_attr($state); ?>">
        <input type="hidden" name="kind" value="<?php echo esc_attr($kind); ?>">

        <div class="tablenav top">
            <?php if ($state === Bounces::STATE_PENDING && $rows) : ?>
                <div class="alignleft actions bulkactions">
                    <label for="mawiblah-bounce-bulk" class="screen-reader-text"><?php esc_html_e('Bulk action', 'mawiblah'); ?></label>
                    <select name="bulk_action" id="mawiblah-bounce-bulk">
                        <option value=""><?php esc_html_e('Bulk actions', 'mawiblah'); ?></option>
                        <option value="count"><?php esc_html_e('Count as failure', 'mawiblah'); ?></option>
                        <option value="fail"><?php esc_html_e('Move to Failing Email', 'mawiblah'); ?></option>
                        <option value="dismiss"><?php esc_html_e('Dismiss', 'mawiblah'); ?></option>
                    </select>
                    <button type="submit" class="button action"
                            onclick="return document.getElementById('mawiblah-bounce-bulk').value !== '' && confirm('<?php echo esc_js(__('Apply this to every selected bounce? Reports are deleted from the mailbox.', 'mawiblah')); ?>');">
                        <?php esc_html_e('Apply', 'mawiblah'); ?>
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($pages > 1) : ?>
                <div class="tablenav-pages">
                    <span class="pagination-links">
                        <?php if ($paged > 1) : ?>
                            <a class="button" href="<?php echo esc_url(add_query_arg(['state' => $state, 'kind' => $kind !== '' ? $kind : false, 'paged' => $paged - 1], $pageUrl)); ?>">‹</a>
                        <?php endif; ?>
                        <span class="paging-input">
                            <?php printf(esc_html__('%1$d of %2$d', 'mawiblah'), (int) min($paged, $pages), (int) $pages); ?>
                        </span>
                        <?php if ($paged < $pages) : ?>
                            <a class="button" href="<?php echo esc_url(add_query_arg(['state' => $state, 'kind' => $kind !== '' ? $kind : false, 'paged' => $paged + 1], $pageUrl)); ?>">›</a>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
            <br class="clear">
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <?php if ($state === Bounces::STATE_PENDING) : ?>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" onclick="document.querySelectorAll('.mawiblah-bounce-cb').forEach(function (cb) { cb.checked = this.checked; }, this);">
                        </td>
                    <?php endif; ?>
                    <th style="width:12%;"><?php esc_html_e('Bounced', 'mawiblah'); ?></th>
                    <th style="width:18%;"><?php esc_html_e('Recipient', 'mawiblah'); ?></th>
                    <th style="width:9%;"><?php esc_html_e('Type', 'mawiblah'); ?></th>
                    <th><?php esc_html_e('Reason', 'mawiblah'); ?></th>
                    <th style="width:14%;"><?php esc_html_e('Campaign', 'mawiblah'); ?></th>
                    <th style="width:12%;"><?php esc_html_e('Subscriber now', 'mawiblah'); ?></th>
                    <th style="width:<?php echo $state === Bounces::STATE_PENDING ? '17' : '16'; ?>%;">
                        <?php $state === Bounces::STATE_PENDING ? esc_html_e('Decide', 'mawiblah') : esc_html_e('Handled', 'mawiblah'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows) : ?>
                    <tr>
                        <td colspan="8">
                            <?php $state === Bounces::STATE_PENDING
                                ? esc_html_e('Nothing waiting for approval.', 'mawiblah')
                                : esc_html_e('Nothing here yet.', 'mawiblah'); ?>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($rows as $row) : ?>
                    <?php
                    $badge        = $kinds[$row->kind] ?? $kinds[BounceParser::KIND_HARD];
                    $subscriberId = (int) $row->subscriber_id;
                    $isSubscriber = $subscriberId && get_post_type($subscriberId) === Subscribers::postType();
                    $campaignId   = (int) $row->campaign_id;
                    $reason       = (string) $row->reason;
                    ?>
                    <tr>
                        <?php if ($state === Bounces::STATE_PENDING) : ?>
                            <th scope="row" class="check-column">
                                <input type="checkbox" class="mawiblah-bounce-cb" name="ids[]" value="<?php echo (int) $row->id; ?>">
                            </th>
                        <?php endif; ?>
                        <td><?php echo esc_html($when($row->bounced_at)); ?></td>
                        <td>
                            <strong><?php echo esc_html($row->recipient); ?></strong>
                            <?php if ($row->subject !== '') : ?>
                                <br><span class="description"><?php echo esc_html($row->subject); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span title="<?php echo esc_attr($badge['title']); ?>" style="display:inline-block;padding:1px 8px;border-radius:10px;color:#fff;background:<?php echo esc_attr($badge['color']); ?>;">
                                <?php echo esc_html($badge['label']); ?>
                            </span>
                            <?php if ($row->status_code !== '') : ?>
                                <br><code><?php echo esc_html($row->status_code); ?></code>
                            <?php endif; ?>
                        </td>
                        <td title="<?php echo esc_attr($reason); ?>">
                            <?php echo esc_html(mb_strimwidth($reason, 0, 220, '…')); ?>
                        </td>
                        <td>
                            <?php if ($campaignId && get_post($campaignId)) : ?>
                                <a href="<?php echo esc_url(get_edit_post_link($campaignId)); ?>"><?php echo esc_html(get_the_title($campaignId)); ?></a>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$isSubscriber) : ?>
                                <span class="description"><?php esc_html_e('Not a subscriber', 'mawiblah'); ?></span>
                            <?php else : ?>
                                <a href="<?php echo esc_url(get_edit_post_link($subscriberId)); ?>">#<?php echo (int) $subscriberId; ?></a><br>
                                <span class="description">
                                    <?php
                                    printf(
                                        /* translators: 1: failures so far, 2: failure threshold */
                                        esc_html__('Failures: %1$d of %2$d', 'mawiblah'),
                                        (int) get_post_meta($subscriberId, 'email_fail_count', true),
                                        (int) $threshold
                                    );
                                    ?>
                                </span><br>
                                <?php
                                if ($failing && has_term($failing->term_id, Subscribers::postType() . '_category', $subscriberId)) {
                                    esc_html_e('In Failing Email', 'mawiblah');
                                } elseif (get_post_meta($subscriberId, 'unsubed', true)) {
                                    esc_html_e('Unsubscribed', 'mawiblah');
                                } else {
                                    esc_html_e('Receiving campaigns', 'mawiblah');
                                }
                                ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($state === Bounces::STATE_PENDING) : ?>
                                <?php if ($isSubscriber) : ?>
                                    <button type="submit" name="row_action" value="count:<?php echo (int) $row->id; ?>" class="button button-primary" style="margin-bottom:4px;"
                                            title="<?php echo esc_attr(sprintf(
                                                /* translators: %d: failure threshold */
                                                __('Adds one to the subscriber\'s failures, moves them to Failing Email at %d, and deletes the report from the mailbox.', 'mawiblah'),
                                                (int) $threshold
                                            )); ?>">
                                        <?php esc_html_e('Count as failure', 'mawiblah'); ?>
                                    </button>
                                    <button type="submit" name="row_action" value="fail:<?php echo (int) $row->id; ?>" class="button" style="margin-bottom:4px;"
                                            title="<?php esc_attr_e('Moves the subscriber into Failing Email straight away and deletes the report from the mailbox.', 'mawiblah'); ?>">
                                        <?php esc_html_e('Move to Failing Email', 'mawiblah'); ?>
                                    </button>
                                <?php endif; ?>
                                <button type="submit" name="row_action" value="dismiss:<?php echo (int) $row->id; ?>" class="button"
                                        title="<?php esc_attr_e('Leaves the subscriber alone and deletes the report from the mailbox.', 'mawiblah'); ?>">
                                    <?php $isSubscriber ? esc_html_e('Dismiss', 'mawiblah') : esc_html_e('Delete report', 'mawiblah'); ?>
                                </button>
                            <?php else : ?>
                                <?php
                                $user = $row->handled_by ? get_userdata((int) $row->handled_by) : null;
                                echo esc_html($when($row->handled_at));
                                if ($user) {
                                    echo '<br>' . esc_html($user->display_name);
                                }
                                ?>
                                <?php
                                $resolutions = [
                                    Bounces::RESOLUTION_MOVED         => __('Moved to Failing Email', 'mawiblah'),
                                    Bounces::RESOLUTION_COUNTED       => __('Counted as a failure', 'mawiblah'),
                                    Bounces::RESOLUTION_COUNTED_MOVED => __('Counted — threshold reached, moved to Failing Email', 'mawiblah'),
                                    Bounces::RESOLUTION_NO_SUBSCRIBER => __('No subscriber to apply it to', 'mawiblah'),
                                ];

                                if (isset($resolutions[$row->resolution ?? ''])) {
                                    echo '<br><strong>' . esc_html($resolutions[$row->resolution]) . '</strong>';
                                }
                                ?>
                                <br>
                                <?php (int) $row->message_deleted
                                    ? esc_html_e('Report deleted', 'mawiblah')
                                    : esc_html_e('Report still in the mailbox', 'mawiblah'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>

    </div><!-- /.metabox-holder -->
</div>
