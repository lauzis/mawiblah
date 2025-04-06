<div class="mawiblah-form-field" id="mawiblah-form-field-<?= $id ?>">

  <label for="<?= $id ?>">
    <?= $title ?>
  </label>

  <input name="<?= $id ?>" id="<?= $id ?>" placeholder="<?= $placeholder ?>" value="<?= $value ? $value : $default_value; ?>" />

  <?php if ($description): ?>
    <p class="mawiblah-form-field-description"><?= $description ?></p>
  <?php endif; ?>

</div>
