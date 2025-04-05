<div class="gae-form-field" id="gae-form-field-<?= $id ?>">

  <label for="<?= $id ?>">
    <?= $title ?>
  </label>

  <textarea 
  name="<?= $id ?>" 
  id="<?= $id ?>" 
  placeholder="<?= $placeholder ?>" 
  ><?= $value ? $value : $default_value; ?></textarea>

  <?php if ($description): ?>
    <p class="gae-form-field-description"><?= $description ?></p>
  <?php endif; ?>

</div>