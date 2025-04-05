<div class="gae-form-field gae-form-field-select" id="gae-form-field-<?= $id ?>">

    <label for="<?= $id ?>">
        <?= $title ?>
    </label>

    <div class="gae-form-field-select-wrap">
        <select name="<?= $id ?>" id="<?= $id ?>">
            <?php foreach($options as $o): ?>
                <option
                    <?php if ((strlen($value)>0 && $value===$o["value"]) || (empty($value) && $default_value===$o["value"])): ?>
                        selected="selected"
                    <?php endif; ?>
                        value="<?= $o["value"] ?>"><?= Settings::get_translation($o["title"]) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if ($description): ?>
        <p class="gae-form-field-description"><?= $description ?></p>
    <?php endif; ?>

</div>
