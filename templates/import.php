<?php

use Mawiblah\Import;
use Mawiblah\Subscribers;

defined('ABSPATH') || exit;

$step     = 'upload'; // upload | preview | done
$errors   = [];
$result   = null;
$rows     = [];
$allAuds  = Subscribers::getAllAudiences();

// ── Step 2: process confirmed import ────────────────────────────────────────
if (!empty($_POST['mawiblah_import_confirm'])) {
    check_admin_referer('mawiblah_import_confirm');

    $transientKey  = sanitize_key($_POST['import_key'] ?? '');
    $emailColumn   = max(0, (int) ($_POST['email_column'] ?? 0));
    $hasHeaders    = !empty($_POST['has_headers']);
    $audienceIds   = array_map('intval', (array) ($_POST['audiences'] ?? []));
    $duplicateMode = sanitize_key($_POST['duplicate_mode'] ?? 'skip');
    if (!in_array($duplicateMode, ['skip', 'overwrite', 'merge'], true)) {
        $duplicateMode = 'skip';
    }

    $result = Import::processImport($transientKey, $emailColumn, $hasHeaders, $audienceIds, $duplicateMode);
    $step   = 'done';
}

// ── Step 1b: file uploaded — show preview ───────────────────────────────────
elseif (!empty($_FILES['csv_file']['tmp_name'])) {
    check_admin_referer('mawiblah_import_upload');

    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = __('File upload failed. Please try again.', 'mawiblah');
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            $errors[] = __('Only CSV / TXT files are accepted.', 'mawiblah');
        } else {
            $allRows      = Import::parseFile($file['tmp_name']);
            $previewRows  = array_slice($allRows, 0, 5);
            $transientKey = Import::storeRows($allRows);
            $columnCount  = empty($allRows) ? 0 : max(array_map('count', $allRows));
            $step         = 'preview';
        }
    }
}

?>
<div class="wrap mawiblah">
    <h1 class="wp-heading-inline"><?php esc_html_e('Import Subscribers', 'mawiblah'); ?></h1>
    <hr class="wp-header-end">

<?php if (!empty($errors)): ?>
    <div class="notice notice-error"><p><?php echo esc_html(implode('<br>', $errors)); ?></p></div>
<?php endif; ?>

<?php if ($step === 'upload'): ?>

    <!-- ── Help ─────────────────────────────────────────────────────────── -->
    <div class="postbox" style="max-width:800px;">
        <div class="postbox-header">
            <h2 class="hndle"><span><?php esc_html_e('Before you import', 'mawiblah'); ?></span></h2>
        </div>
        <div class="inside">
            <ul style="list-style:disc;padding-left:1.5em;">
                <li><?php esc_html_e('Accepted formats: CSV or TXT. Delimiters detected automatically (comma, semicolon, tab).', 'mawiblah'); ?></li>
                <li><?php esc_html_e('Your file must contain at least one column with email addresses. Other columns are ignored.', 'mawiblah'); ?></li>
                <li><?php esc_html_e('If the first row contains column names (e.g. "Email", "Name"), check the "First row is headers" box on the next screen.', 'mawiblah'); ?></li>
                <li><?php esc_html_e('Unsubscribed addresses: tick the "Unsubscribed" audience to import them directly into the unsubscribed list.', 'mawiblah'); ?></li>
                <li><?php esc_html_e('Prepare separate files for separate lists — one import, one audience.', 'mawiblah'); ?></li>
            </ul>
        </div>
    </div>

    <!-- ── Upload form ───────────────────────────────────────────────────── -->
    <div class="postbox" style="max-width:800px;margin-top:16px;">
        <div class="postbox-header">
            <h2 class="hndle"><span><?php esc_html_e('Upload CSV', 'mawiblah'); ?></span></h2>
        </div>
        <div class="inside">
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('mawiblah_import_upload'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="csv_file"><?php esc_html_e('CSV file', 'mawiblah'); ?></label></th>
                        <td>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv,.txt" required>
                            <p class="description"><?php esc_html_e('Max upload size: ', 'mawiblah'); ?><?php echo esc_html(size_format(wp_max_upload_size())); ?></p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Upload and preview', 'mawiblah'); ?></button>
                </p>
            </form>
        </div>
    </div>

<?php elseif ($step === 'preview'): ?>

    <!-- ── Preview ───────────────────────────────────────────────────────── -->
    <div class="postbox" style="max-width:900px;">
        <div class="postbox-header">
            <h2 class="hndle"><span><?php esc_html_e('Preview (first 5 rows)', 'mawiblah'); ?></span></h2>
        </div>
        <div class="inside">
            <?php if (empty($previewRows)): ?>
                <p><?php esc_html_e('The file appears to be empty or could not be parsed.', 'mawiblah'); ?></p>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <?php for ($c = 0; $c < $columnCount; $c++): ?>
                                <th><?php echo esc_html__('Column', 'mawiblah') . ' ' . esc_html($c); ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewRows as $row): ?>
                            <tr>
                                <?php for ($c = 0; $c < $columnCount; $c++): ?>
                                    <td><?php echo esc_html($row[$c] ?? ''); ?></td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form method="post" style="margin-top:24px;">
                <?php wp_nonce_field('mawiblah_import_confirm'); ?>
                <input type="hidden" name="mawiblah_import_confirm" value="1">
                <input type="hidden" name="import_key" value="<?php echo esc_attr($transientKey); ?>">

                <table class="form-table" role="presentation">

                    <tr>
                        <th scope="row"><label for="has_headers"><?php esc_html_e('First row is headers', 'mawiblah'); ?></label></th>
                        <td>
                            <input type="checkbox" name="has_headers" id="has_headers" value="1" checked>
                            <p class="description"><?php esc_html_e('Uncheck if your file has no header row and the first row is already a data row.', 'mawiblah'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="email_column"><?php esc_html_e('Email column', 'mawiblah'); ?></label></th>
                        <td>
                            <select name="email_column" id="email_column">
                                <?php for ($c = 0; $c < $columnCount; $c++): ?>
                                    <option value="<?php echo esc_attr($c); ?>">
                                        <?php echo esc_html__('Column', 'mawiblah') . ' ' . esc_html($c); ?>
                                        <?php if (!empty($previewRows[0][$c])): ?>
                                            (<?php echo esc_html(mb_substr($previewRows[0][$c], 0, 30)); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Select which column contains the email address.', 'mawiblah'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Add to audiences', 'mawiblah'); ?></th>
                        <td>
                            <?php if (empty($allAuds)): ?>
                                <p><?php esc_html_e('No audiences found. Create at least one audience before importing.', 'mawiblah'); ?></p>
                            <?php else: ?>
                                <?php foreach ($allAuds as $aud): ?>
                                    <label style="display:block;margin-bottom:4px;">
                                        <input type="checkbox" name="audiences[]" value="<?php echo esc_attr($aud->term_id); ?>">
                                        <?php echo esc_html($aud->name); ?>
                                    </label>
                                <?php endforeach; ?>
                                <p class="description"><?php esc_html_e('Subscribers will be added to all checked audiences. Check "Unsubscribed" to import them as unsubscribed.', 'mawiblah'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php esc_html_e('Duplicate handling', 'mawiblah'); ?></th>
                        <td>
                            <fieldset>
                                <label style="display:block;margin-bottom:4px;">
                                    <input type="radio" name="duplicate_mode" value="skip" checked>
                                    <strong><?php esc_html_e('Skip', 'mawiblah'); ?></strong>
                                    — <?php esc_html_e('leave existing subscribers exactly as they are.', 'mawiblah'); ?>
                                </label>
                                <label style="display:block;margin-bottom:4px;">
                                    <input type="radio" name="duplicate_mode" value="merge">
                                    <strong><?php esc_html_e('Merge', 'mawiblah'); ?></strong>
                                    — <?php esc_html_e('add the selected audiences to existing subscribers without removing anything.', 'mawiblah'); ?>
                                </label>
                                <label style="display:block;margin-bottom:4px;">
                                    <input type="radio" name="duplicate_mode" value="overwrite">
                                    <strong><?php esc_html_e('Overwrite', 'mawiblah'); ?></strong>
                                    — <?php esc_html_e('same as Merge for now (full overwrite reserved for a future version).', 'mawiblah'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Run import', 'mawiblah'); ?></button>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mawiblah-import')); ?>"><?php esc_html_e('Cancel', 'mawiblah'); ?></a>
                </p>
            </form>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($step === 'done' && $result !== null): ?>

    <!-- ── Results ───────────────────────────────────────────────────────── -->
    <div class="postbox" style="max-width:600px;">
        <div class="postbox-header">
            <h2 class="hndle"><span><?php esc_html_e('Import complete', 'mawiblah'); ?></span></h2>
        </div>
        <div class="inside">
            <table class="widefat" style="max-width:400px;">
                <tbody>
                    <tr><th><?php esc_html_e('Imported (new)', 'mawiblah'); ?></th><td><?php echo esc_html($result['imported']); ?></td></tr>
                    <tr><th><?php esc_html_e('Updated (existing)', 'mawiblah'); ?></th><td><?php echo esc_html($result['updated']); ?></td></tr>
                    <tr><th><?php esc_html_e('Skipped (duplicate)', 'mawiblah'); ?></th><td><?php echo esc_html($result['skipped']); ?></td></tr>
                    <tr><th><?php esc_html_e('Errors', 'mawiblah'); ?></th><td><?php echo esc_html(count($result['errors'])); ?></td></tr>
                </tbody>
            </table>

            <?php if (!empty($result['errors'])): ?>
                <details style="margin-top:12px;">
                    <summary><?php esc_html_e('Show errors', 'mawiblah'); ?></summary>
                    <ul style="list-style:disc;padding-left:1.5em;margin-top:8px;">
                        <?php foreach ($result['errors'] as $err): ?>
                            <li><?php echo esc_html($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>

            <p style="margin-top:16px;">
                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=mawiblah-import')); ?>"><?php esc_html_e('Import another file', 'mawiblah'); ?></a>
            </p>
        </div>
    </div>

<?php endif; ?>
</div>
