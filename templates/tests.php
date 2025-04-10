<?php

use Mawiblah\Tests;
use Mawiblah\Settings;

?>
<div class="wrap mawiblah">
    <h1>Self Tests</h1>

    <h2> Settings </h2>
    <?php $settings = Settings::get_sections(); ?>
    <?php foreach ($settings as $value) : ?>
        <h3><?= $value['title']; ?></h3>
        <p><?= $value['description']; ?></p>
        <?php $fields = $value['fields']; ?>
        <table class="mawiblah-settings-table">
            <thead>
            <tr>
                <th>Field</th>
                <th>Id</th>
                <th>Value</th>
                <th>Options</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($fields as $field) : ?>
                <tr>
                    <td><?= esc_html($field['title']); ?></td>
                    <td><?= esc_html($field['id']); ?></td>
                    <td><?= esc_html($field['value']); ?></td>
                    <td>
                        <?php if (isset($field['options'])) : ?>
                            <ul>
                                <?php foreach ($field['options'] as $option) : ?>
                                    <li><?= esc_html($option['title']); ?>: <?= esc_html($option['value']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            No options
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <br/>
    <?php endforeach; ?>

    <h2> Actions </h2>
    <?php Tests::tests(); ?>
</div>


