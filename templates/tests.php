<?php

use Mawiblah\Tests;
use Mawiblah\Settings;

$scenario  = isset($_GET['run']) ? sanitize_key($_GET['run']) : null;
$scenarios = Tests::scenarios();

?>
<div class="wrap mawiblah">
    <h1>Self Tests</h1>

    <h2>Settings</h2>
    <?php $sections = Settings::get_sections(); ?>
    <?php foreach ($sections as $value): ?>
        <h3><?= esc_html($value['title']); ?></h3>
        <p><?= esc_html($value['description']); ?></p>
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
            <?php foreach ($value['fields'] as $field): ?>
                <tr>
                    <td><?= esc_html($field['title']); ?></td>
                    <td><?= esc_html($field['id']); ?></td>
                    <td><?= esc_html($field['value']); ?></td>
                    <td>
                        <?php if (isset($field['options'])): ?>
                            <ul>
                                <?php foreach ($field['options'] as $option): ?>
                                    <li><?= esc_html($option['title']); ?>: <?= esc_html($option['value']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            No options
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <br/>
    <?php endforeach; ?>

    <h2>Test Scenarios</h2>
    <p>Each scenario creates its own test data, asserts, and cleans up. Click a button to run a single scenario.</p>

    <div class="mawiblah-test-scenarios">
        <?php foreach ($scenarios as $key => $label): ?>
            <?php
            $url = add_query_arg([
                'page' => 'mawiblah-tests',
                'run'  => $key,
            ], admin_url('admin.php'));
            $active = $scenario === $key ? ' mawiblah-test-scenario--active' : '';
            ?>
            <a href="<?= esc_url($url) ?>" class="button mawiblah-test-scenario<?= esc_attr($active) ?>">
                <?= esc_html($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($scenario): ?>
        <hr/>
        <div class="mawiblah-test-results">
            <h2>Results: <?= esc_html($scenarios[$scenario] ?? $scenario) ?></h2>
            <?php Tests::run($scenario); ?>
        </div>
    <?php endif; ?>

</div>
