<?php
defined('ABSPATH') || exit;

$cleared = false;

if (
    isset($_POST['action'], $_POST['mawiblah_clear_logs_nonce'])
    && $_POST['action'] === 'clear_logs'
    && wp_verify_nonce($_POST['mawiblah_clear_logs_nonce'], 'mawiblah_clear_logs')
) {
    \Mawiblah\Logs::clearLogs();
    $cleared = true;
}

$logFiles      = \Mawiblah\Logs::getLogFiles(); // newest first
$selectedFile  = null;
$selectedDate  = null;
$logLines      = [];

if (!empty($logFiles)) {
    // Use query param or default to the newest file
    $requestedDate = isset($_GET['log_date']) ? sanitize_text_field($_GET['log_date']) : null;
    foreach ($logFiles as $f) {
        if ($requestedDate && $f['date'] === $requestedDate) {
            $selectedFile = $f['file'];
            $selectedDate = $f['date'];
            break;
        }
    }
    if (!$selectedFile) {
        $selectedFile = $logFiles[0]['file'];
        $selectedDate = $logFiles[0]['date'];
    }
    if (file_exists($selectedFile)) {
        $logLines = file($selectedFile, FILE_IGNORE_NEW_LINES) ?: [];
    }
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Mawiblah — Logs', 'mawiblah'); ?></h1>
    <hr class="wp-header-end">

    <?php if ($cleared) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('All log files deleted.', 'mawiblah'); ?></p></div>
    <?php endif; ?>

    <?php if (!\Mawiblah\Logs::enabled()) : ?>
        <div class="notice notice-warning inline" style="margin-top:20px;">
            <p>
                <?php esc_html_e('File logging is currently disabled. Enable it under', 'mawiblah'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=mawiblah-settings')); ?>"><?php esc_html_e('Settings → Debug mode', 'mawiblah'); ?></a>.
            </p>
        </div>
    <?php endif; ?>

    <div class="metabox-holder">

    <!-- ── Toolbar ───────────────────────────────────────────────────────── -->
    <div class="postbox" style="margin-top:4px;">
        <div class="inside" style="padding-top:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">

            <?php if (!empty($logFiles)) : ?>
                <form method="get" style="display:flex;align-items:center;gap:8px;margin:0;">
                    <input type="hidden" name="page" value="mawiblah-logs">
                    <label for="mawiblah-log-select" style="font-weight:600;">
                        <?php esc_html_e('Log file:', 'mawiblah'); ?>
                    </label>
                    <select id="mawiblah-log-select" name="log_date" onchange="this.form.submit()">
                        <?php foreach ($logFiles as $f) : ?>
                            <option value="<?php echo esc_attr($f['date']); ?>"
                                <?php selected($f['date'], $selectedDate); ?>>
                                <?php echo esc_html($f['date']); ?>
                                (<?php echo esc_html($f['count']); ?> <?php esc_html_e('entries', 'mawiblah'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php else : ?>
                <span class="description"><?php esc_html_e('No log files found.', 'mawiblah'); ?></span>
            <?php endif; ?>

            <!-- Clear All button pushed to the right -->
            <form method="post" style="margin:0 0 0 auto;"
                  onsubmit="return confirm('<?php echo esc_js(__('Delete all log files? This cannot be undone.', 'mawiblah')); ?>')">
                <?php wp_nonce_field('mawiblah_clear_logs', 'mawiblah_clear_logs_nonce'); ?>
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="button button-secondary" <?php echo empty($logFiles) ? 'disabled' : ''; ?>>
                    <?php esc_html_e('Clear all logs', 'mawiblah'); ?>
                </button>
            </form>

        </div>
    </div>

    <!-- ── Log output ────────────────────────────────────────────────────── -->
    <?php if (!empty($logLines)) : ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle">
                    <span><?php echo esc_html($selectedDate); ?></span>
                </h2>
            </div>
            <div class="inside" style="padding:0;">
                <pre style="
                    margin:0;
                    padding:12px 16px;
                    background:#1d2327;
                    color:#e0e0e0;
                    font-size:12px;
                    line-height:1.7;
                    overflow-x:auto;
                    max-height:70vh;
                    overflow-y:auto;
                    border-radius:0 0 3px 3px;
                "><?php
                    foreach (array_reverse($logLines) as $line) {
                        echo esc_html($line) . "\n";
                    }
                ?></pre>
            </div>
        </div>
    <?php elseif (!empty($logFiles)) : ?>
        <div class="notice notice-info inline" style="margin-top:0;">
            <p><?php esc_html_e('This log file is empty.', 'mawiblah'); ?></p>
        </div>
    <?php endif; ?>

    </div><!-- /.metabox-holder -->
</div>
