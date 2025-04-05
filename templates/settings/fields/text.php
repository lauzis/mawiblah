<div class="gae-form-field" id="gae-form-field-<?= $id ?>">

  <label for="<?= $id ?>">
    <?= $title ?>
  </label>

  <input name="<?= $id ?>" id="<?= $id ?>" placeholder="<?= $placeholder ?>" value="<?= $value ? $value : $default_value; ?>" />

  <?php if ($description): ?>
    <p class="gae-form-field-description"><?= $description ?></p>
  <?php endif; ?>

</div>
