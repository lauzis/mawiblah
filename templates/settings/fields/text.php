<div class="mawiblah-form-field" id="mawiblah-form-field-<?= esc_attr($id) ?>">

  <label for="<?= esc_attr($id) ?>">
    <?= esc_html($title) ?>
  </label>

  <input name="<?= esc_attr($id) ?>" id="<?= esc_attr($id) ?>" placeholder="<?= esc_attr($placeholder) ?>" value="<?= esc_attr($value ?: $default_value) ?>" />

  <?php if ($description): ?>
    <p class="mawiblah-form-field-description"><?= wp_kses_post($description) ?></p>
  <?php endif; ?>

</div>
