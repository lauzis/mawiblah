<?php
    use Mawiblah\Tests;
    use Mawiblah\Settings;
?>
<div class="wrap mawiblah">
    <h1>Self Tests</h1>

    <h2> Settings </h2>
    <?php $settings = Settings::get_sections(); ?>
    <?php foreach ($settings as $value) : ?>
        <h3><?= $value['title']; ?>[<?= $value['id']; ?>]</h3>
        <?php $fields = $value['fields']; ?>
        <?php foreach ($fields as $field) : ?>
            <h4><?= $field['title']; ?> [<?= $field['id']; ?>]</h4>
            <p>[<?= $field['id']; ?>]=[<?= $field['value']; ?>]</p>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <h2> Actions </h2>
    <?php Tests::tests(); ?>
</div>


