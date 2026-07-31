<?php

namespace Mawiblah;

class SetupNotice
{
    const NOTICE_ID = 'setup';

    /**
     * Returns the shared notice manager, or null when the wp-notices package
     * is not installed, in which case the notice is simply not shown.
     *
     * Dismissals are per user and scoped to the plugin version, so a dismissed
     * notice returns after an upgrade — the setup requirements may have moved.
     *
     * @return \Lauzis\WpNotices\Notices|null
     */
    private static function manager()
    {
        if (!class_exists('WpNotices_Registry')) {
            return null;
        }

        return \WpNotices_Registry::notices(
            'mawiblah',
            [
                'store'      => 'user',
                'version'    => MAWIBLAH_VERSION_BASE,
                'capability' => 'manage_options',
            ]
        );
    }

    public static function init(): void
    {
        $manager = self::manager();
        if (!$manager) {
            return;
        }

        $manager->boot();

        // The checks query terms and touch the filesystem, so they run on
        // admin_notices rather than at load time.
        add_action('admin_notices', [self::class, 'maybeShowNotice'], 5);
    }

    public static function maybeShowNotice(): void
    {
        $manager = self::manager();
        if (!$manager) {
            return;
        }

        $issues = self::runChecks();
        if (empty($issues)) {
            return;
        }

        $message = '<strong>'
            . esc_html__('Mawiblah — minimal setup needed before you can send emails', 'mawiblah')
            . '</strong><ul style="list-style:disc;padding-left:20px;margin-top:4px;"><li>'
            . implode('</li><li>', $issues)
            . '</li></ul>';

        $manager->add(
            new \Lauzis\WpNotices\Notice(
                self::NOTICE_ID,
                $message,
                'warning',
                \Lauzis\WpNotices\Notice::VERSION
            )
        );
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
