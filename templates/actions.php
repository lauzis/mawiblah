<div class="<?= esc_attr(MAWIBLAH_PLUGIN_DIRECTORY_NAME) ?>">
<h1>
    <?php esc_html_e('Actions', 'mawiblah'); ?>
</h1>

    <h2>
        <?php esc_html_e('Clear logs', 'mawiblah'); ?>
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
                echo '<p>' . esc_html__('Logs cleared successfully', 'mawiblah') . '</p>';
            } else {
                echo '<p>' . esc_html__('Failed to clear logs', 'mawiblah') . '</p>';
            }
        }
    ?>
    <p>
        <?php esc_html_e('Clear all logs, not reversible changes', 'mawiblah'); ?>
        <?php echo esc_html(sprintf(__('Currently there is %s log entries', 'mawiblah'), \Mawiblah\Logs::getLogCount())); ?>
    </p>

    <form method="post" action="" class="<?= esc_attr(MAWIBLAH_PLUGIN_DIRECTORY_NAME) ?>" autocomplete="off">
        <?php wp_nonce_field('mawiblah_clear_logs', 'mawiblah_clear_logs_nonce'); ?>
        <input type="hidden" name="action" value="clear_logs">
        <button type="submit" class="button button-primary">
            <?= esc_html__('Clear logs', 'mawiblah') ?>
        </button>
    </form>


    <?php if (\Mawiblah\GravityForms::isGravityPluginActive()): ?>

        <h2>
            <?php esc_html_e('Gravity forms', 'mawiblah'); ?>
        </h2>
        <p>
            <?php esc_html_e('Synchronize gravityforms with audiences', 'mawiblah'); ?>
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
                if (isset($_POST['force']) && $_POST['force'] === 'force'){
                    $syncStats = \Mawiblah\GravityForms::syncWithAudiencePostType(force:true);
                } else{
                    $syncStats = \Mawiblah\GravityForms::syncWithAudiencePostType();
                }

                if($syncStats['checked'] > 0){
                    echo '<p>' . esc_html(sprintf(__('Synchronized %s audiences', 'mawiblah'), $syncStats['checked'])) . '</p>';
                    if($syncStats['skipped'] > 0){
                        echo '<p>' . esc_html(sprintf(__('Skipped %s audiences', 'mawiblah'), $syncStats['skipped'])) . '</p>';
                    }
                } else {
                    echo '<p>' . esc_html__('No audiences to sync', 'mawiblah') . '</p>';
                }
            }
        ?>

        <form method="post" action="" class="<?= esc_attr(MAWIBLAH_PLUGIN_DIRECTORY_NAME) ?>" autocomplete="off">
            <input type="hidden" name="action" value="gravity_forms_sync">
            <?php wp_nonce_field('mawiblah_gravity_sync', 'mawiblah_gravity_sync_nonce'); ?>
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Gravity forms audience sync', 'mawiblah'); ?>
            </button>
        </form>
        <br/>
        <form method="post" action="" class="<?= esc_attr(MAWIBLAH_PLUGIN_DIRECTORY_NAME) ?>" autocomplete="off">
            <input type="hidden" name="action" value="gravity_forms_sync">
            <input type="hidden" name="force" value="force">
            <?php wp_nonce_field('mawiblah_gravity_sync', 'mawiblah_gravity_sync_nonce'); ?>
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Gravity forms audience sync (force)', 'mawiblah'); ?>
            </button>
        </form>

    <?php endif; ?>

</div>
