<?php
// Required variables
$id = $id ?? '';
$title = $title ?? '';
$options = $options ?? [];
// Optional variables with defaults
$value = $value ?? '';
$default_value = $default_value ?? '';
$description = $description ?? '';

// Ensure Settings class is available
if (!class_exists('\\Mawiblah\\Settings')) {
    require_once MAWIBLAH_PLUGIN_DIR . '/classes/Settings.php';
}
use Mawiblah\Settings;
?>
<div class="gae-form-field gae-form-field-select" id="gae-form-field-<?= esc_attr($id) ?>">

    <label for="<?= esc_attr($id) ?>">
        <?= esc_html($title) ?>
    </label>

    <div class="gae-form-field-select-wrap">
        <select name="<?= esc_attr($id) ?>" id="<?= esc_attr($id) ?>">
            <?php foreach($options as $o): ?>
                <?php 
                // Validate option structure
                if (!isset($o['value']) || !isset($o['title'])) continue;
                ?>
                <option
                    <?php if ((strlen($value) > 0 && $value === $o["value"]) || (empty($value) && $default_value === $o["value"])): ?>
                        selected="selected"
                    <?php endif; ?>
                        value="<?= esc_attr($o["value"]) ?>"><?= esc_html(Settings::get_translation($o["title"])) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($description): ?>
        <p class="gae-form-field-description"><?= esc_html($description) ?></p>
    <?php endif; ?>

</div>
