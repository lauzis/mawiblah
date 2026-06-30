<?php

namespace Mawiblah;

class SetupNotice
{
    const DISMISSED_META_KEY = 'mawiblah_notice_dismissed';
    const AJAX_ACTION        = 'mawiblah_dismiss_notice';

    public static function init(): void
    {
        add_action('admin_notices', [self::class, 'maybeShowNotice']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [self::class, 'handleDismiss']);
    }

    public static function maybeShowNotice(): void
    {
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if (strpos($page, 'mawiblah') !== 0) {
            return;
        }

        $userId    = get_current_user_id();
        $dismissed = get_user_meta($userId, self::DISMISSED_META_KEY, true);
        if ($dismissed === MAWIBLAH_VERSION_BASE) {
            return;
        }

        $issues = self::runChecks();
        if (empty($issues)) {
            return;
        }

        $nonce = wp_create_nonce(self::AJAX_ACTION);
        ?>
        <div class="notice notice-warning mawiblah-setup-notice" style="padding-bottom:12px;">
            <p><strong><?php esc_html_e('Mawiblah — minimal setup needed before you can send emails', 'mawiblah'); ?></strong></p>
            <ul style="list-style:disc;padding-left:20px;margin-top:4px;">
                <?php foreach ($issues as $issue) : ?>
                    <li><?php echo wp_kses_post($issue); ?></li>
                <?php endforeach; ?>
            </ul>
            <p style="margin-top:8px;">
                <button type="button" class="button mawiblah-dismiss-notice-btn"
                        data-nonce="<?php echo esc_attr($nonce); ?>">
                    <?php esc_html_e('Dismiss for this version', 'mawiblah'); ?>
                </button>
            </p>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.querySelector('.mawiblah-dismiss-notice-btn');
            if (!btn) { return; }
            btn.addEventListener('click', function () {
                var nonce = this.getAttribute('data-nonce');
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxurl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send('action=<?php echo esc_js(self::AJAX_ACTION); ?>&_wpnonce=' + encodeURIComponent(nonce));
                this.closest('.mawiblah-setup-notice').remove();
            });
        });
        </script>
        <?php
    }

    public static function handleDismiss(): void
    {
        check_ajax_referer(self::AJAX_ACTION);
        update_user_meta(get_current_user_id(), self::DISMISSED_META_KEY, MAWIBLAH_VERSION_BASE);
        wp_send_json_success();
    }

    /** @return string[] Human-readable issues; empty array means all checks passed. */
    private static function runChecks(): array
    {
        $issues = [];

        // 1. At least one email template
        if (empty(Templates::getArrayOfEmailTemplates())) {
            $issues[] = __('No email templates found. Add a template file to your theme under <code>mawiblah/</code> or to the plugin\'s email templates directory.', 'mawiblah');
        }

        // 2. Plugin uploads directory writable (required for template locking at campaign start)
        if (!is_dir(MAWIBLAH_UPLOAD_DIR)) {
            $issues[] = sprintf(
                /* translators: %s: directory path */
                __('Plugin upload directory does not exist: <code>%s</code>. Ensure the WordPress uploads directory is writable so the plugin can create it.', 'mawiblah'),
                esc_html(MAWIBLAH_UPLOAD_DIR)
            );
        } elseif (!is_writable(MAWIBLAH_UPLOAD_DIR)) {
            $issues[] = sprintf(
                /* translators: %s: directory path */
                __('Plugin upload directory is not writable: <code>%s</code>. Fix file permissions to allow campaign template locking.', 'mawiblah'),
                esc_html(MAWIBLAH_UPLOAD_DIR)
            );
        }

        // 3. At least one non-system audience exists
        $systemNames  = ['Unsubed', 'Testers', 'Failing Email'];
        $allAudiences = get_terms(['taxonomy' => Subscribers::postType() . '_category', 'hide_empty' => false]);
        $realAudiences = array_filter(
            is_array($allAudiences) ? $allAudiences : [],
            fn($t) => !in_array($t->name, $systemNames, true)
        );
        if (empty($realAudiences)) {
            $addAudienceUrl = admin_url('edit-tags.php?taxonomy=' . Subscribers::postType() . '_category&post_type=' . Subscribers::postType());
            $issues[] = sprintf(
                /* translators: %s: URL to add audience */
                __('No subscriber audiences found. <a href="%s">Create at least one audience</a> and add subscribers before sending a campaign.', 'mawiblah'),
                esc_url($addAudienceUrl)
            );
        }

        return $issues;
    }
}
