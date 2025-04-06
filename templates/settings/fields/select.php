<?php
use Mawiblah\Settings;

/**
 * @var string $id
 * @var string $title
 * @var string $description
 * @var string $value
 * @var string $default_value
 * @var array $options
 */
?>
<div class="mawiblah-form-field mawiblah-form-field-select" id="mawiblah-form-field-<?= esc_attr($id) ?>">

  <label for="<?= esc_attr($id) ?>">
    <?= esc_html($title) ?>
  </label>

  <div class="mawiblah-form-field-select-wrap">
      <select name="<?= esc_attr($id) ?>" id="<?= esc_attr($id) ?>">
          <?php foreach($options as $o): ?>
              <option
                  <?php if ((strlen($value)>0 && $value===$o["value"]) || (empty($value) && $default_value===$o["value"])): ?>
                      selected="selected"
                  <?php endif; ?>
                      value="<?= esc_attr($o["value"]) ?>"><?= esc_html(Settings::get_translation($o["title"])) ?></option>
          <?php endforeach; ?>
      </select>
  </div>

  <?php if ($description): ?>
    <p class="mawiblah-form-field-description"><?= esc_html($description) ?></p>
  <?php endif; ?>

</div>
