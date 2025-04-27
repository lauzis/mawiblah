<div class="<?= MAWIBLAH_PLUGIN_DIRECTORY_NAME ?>">
<h1>
    <?= _e('Actions', 'mawiblah') ?>
</h1>

    <h2>
        <?= _e('Clear logs', 'mawiblah') ?>
    </h2>
    <?php
        if(
                isset($_POST['action'])
                && $_POST['action'] == 'clear_logs'
                && isset($_POST['mawiblah_clear_logs_nonce'])
                && wp_verify_nonce(
                    $_POST['mawiblah_clear_logs_nonce'],
                    'mawiblah_clear_logs'
                )

        ) {
            // Call the function to clear logs
            $result = \Mawiblah\Logs::clearLogs();
            if($result) {
                echo '<p>' . _e('Logs cleared successfully', 'mawiblah') . '</p>';
            } else {
                echo '<p>' . _e('Failed to clear logs', 'mawiblah') . '</p>';
            }
        }
    ?>
    <p>
        <?= _e('Clear all logs, not reversible changes', 'mawiblah') ?>
        <?= sprintf(__('Currently there is %s log entries', 'mawiblah'), \Mawiblah\Logs::getLogCount()); ?>
    </p>

    <form method="post" action="" class="<?= MAWIBLAH_PLUGIN_DIRECTORY_NAME ?>" autocomplete="off">
        <?php wp_nonce_field('mawiblah_clear_logs', 'mawiblah_clear_logs_nonce'); ?>
        <input type="hidden" name="action" value="clear_logs">
        <button type="submit" class="button button-primary">
            <?= esc_html__('Clear logs', 'mawiblah') ?>
        </button>
    </form>


    <?php if (\Mawiblah\GravityForms::isGravityPluginActive()): ?>

        <h2>
            <?= _e('Gravity forms', 'mawiblah') ?>
        </h2>
        <p>
            <?= _e('Syncronize gravityforms with audiences', 'mawiblah') ?>
        </p>
        <?php
            if(
                    isset($_POST['action'])
                    && $_POST['action'] === 'gravity_forms_sync'
                    && isset($_POST['mawiblah_gravity_sync_nonce'])
                    && wp_verify_nonce(
                        $_POST['mawiblah_gravity_sync_nonce'],
                        'mawiblah_gravity_sync'
                    )
            ){
                $syncStats = \Mawiblah\GravityForms::syncWithAudiencePostType();
                if($syncStats['checked'] > 0){
                    echo '<p>' . sprintf(__('Syncronized %s audiences', 'mawiblah'), $syncStats['checked']) . '</p>';
                    if($syncStats['skipped'] > 0){
                        echo '<p>' . sprintf(__('Skipped %s audiences', 'mawiblah'), $syncStats['skipped']) . '</p>';
                    }
                } else {
                    echo '<p>' . _e('No audiences to sync', 'mawiblah') . '</p>';
                }
            }
        ?>

        <form method="post" action="" class="<?= MAWIBLAH_PLUGIN_DIRECTORY_NAME ?>" autocomplete="off">
            <input type="hidden" name="action" value="gravity_forms_sync">
            <?php wp_nonce_field('mawiblah_gravity_sync', 'mawiblah_gravity_sync_nonce'); ?>
            <button type="submit" class="button button-primary">
                <?= _e('Gravity forms audience sync', 'mawiblah') ?>
            </button>
        </form>

    <?php endif; ?>

</div>
