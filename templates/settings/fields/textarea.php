<div class="gae-form-field" id="gae-form-field-<?= esc_attr($id) ?>">

  <label for="<?= $id ?>">
    <?= $title ?>
  </label>

  <textarea 
  name="<?= esc_attr($id) ?>" 
  id="<?= esc_attr($id) ?>" 
  placeholder="<?= esc_attr($placeholder) ?>" 
  ><?= esc_textarea($value ? $value : $default_value); ?></textarea>

  <?php if ($description): ?>
-    <p class="gae-form-field-description"><?= $description ?></p>
+    <p class="gae-form-field-description"><?= esc_html($description) ?></p>
  <?php endif; ?>

</div>