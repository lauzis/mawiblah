<?php

use Mawiblah\Tests;
use Mawiblah\Settings;

$scenarios = Tests::scenarios();
$selected  = [];
$ran       = false;

if ( ! empty( $_POST['run'] ) && is_array( $_POST['run'] ) ) {
    check_admin_referer( 'mawiblah-run-tests' );
    $selected = array_intersect( array_map( 'sanitize_key', $_POST['run'] ), array_keys( $scenarios ) );
    $ran      = true;
}

?>
<div class="wrap">
    <h1 class="wp-heading-inline">Mawiblah &mdash; Self Tests</h1>
    <hr class="wp-header-end">

    <div class="metabox-holder">

    <!-- Settings (collapsed) -->
    <div class="postbox" id="mawiblah-settings-box">
        <div class="postbox-header">
            <h2 class="hndle" style="cursor:pointer;" onclick="
                var b = document.getElementById('mawiblah-settings-body');
                b.hidden = !b.hidden;
                this.closest('.postbox').classList.toggle('closed', b.hidden);
            "><span>Current Settings</span></h2>
            <div class="handle-actions hide-if-no-js">
                <button type="button" class="handlediv" onclick="
                    var b = document.getElementById('mawiblah-settings-body');
                    b.hidden = !b.hidden;
                    this.closest('.postbox').classList.toggle('closed', b.hidden);
                " aria-expanded="false"><span class="toggle-indicator" aria-hidden="true"></span></button>
            </div>
        </div>
        <div class="inside" id="mawiblah-settings-body" hidden>
            <?php $sections = $ran ? [] : Settings::get_sections(); ?>
            <?php if ( empty( $sections ) ) : ?>
                <p class="description">Submit the form on a GET request to view current settings.</p>
            <?php endif; ?>
            <?php foreach ( $sections as $value ) : ?>
                <h3><?= esc_html( $value['title'] ); ?></h3>
                <?php if ( ! empty( $value['description'] ) ) : ?>
                    <p class="description"><?= esc_html( $value['description'] ); ?></p>
                <?php endif; ?>
                <table class="wp-list-table widefat fixed striped" style="margin-bottom:20px;">
                    <thead>
                        <tr>
                            <th style="width:25%">Field</th>
                            <th style="width:25%">ID</th>
                            <th style="width:25%">Value</th>
                            <th style="width:25%">Options</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $value['fields'] as $field ) : ?>
                            <tr>
                                <td><?= esc_html( $field['title'] ); ?></td>
                                <td><code><?= esc_html( $field['id'] ); ?></code></td>
                                <td><?php
                                    $v = $field['value'];
                                    echo ( $v === '' || $v === null || $v === false )
                                        ? '<em style="color:#999;">—</em>'
                                        : '<code>' . esc_html( $v ) . '</code>';
                                ?></td>
                                <td><?php if ( ! empty( $field['options'] ) ) : ?>
                                    <ul style="margin:0;padding-left:16px;">
                                        <?php foreach ( $field['options'] as $opt ) : ?>
                                            <li><?= esc_html( $opt['title'] ); ?>: <code><?= esc_html( $opt['value'] ); ?></code></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else : ?>
                                    <em style="color:#999;">—</em>
                                <?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Test Scenarios -->
    <div class="postbox">
        <div class="postbox-header">
            <h2 class="hndle"><span>Test Scenarios</span></h2>
        </div>
        <div class="inside">
            <p class="description">Select the scenarios to run, then click <strong>Run Tests</strong>. Each scenario creates its own test data, asserts, and cleans up after itself.</p>

            <form method="post" style="margin-top:16px;">
                <?php wp_nonce_field( 'mawiblah-run-tests' ); ?>

                <table class="wp-list-table widefat fixed" style="margin-bottom:16px;">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="mawiblah-check-all" checked
                                       onchange="document.querySelectorAll('.mawiblah-scenario-cb').forEach(function(cb){ cb.checked = this.checked; }.bind(this))">
                            </td>
                            <th class="manage-column">Scenario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $scenarios as $key => $label ) : ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox"
                                           class="mawiblah-scenario-cb"
                                           name="run[]"
                                           value="<?= esc_attr( $key ); ?>"
                                           checked>
                                </th>
                                <td><?= esc_html( $label ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <button type="submit" class="button button-primary button-large">
                    &#9654; Run Tests
                </button>
            </form>
        </div>
    </div>

    <!-- Results -->
    <?php if ( $ran && ! empty( $selected ) ) : ?>
        <div class="postbox">
            <div class="postbox-header">
                <h2 class="hndle"><span>Results</span></h2>
            </div>
            <div class="inside">
                <?php foreach ( $selected as $key ) : ?>
                    <div style="margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid #dcdcde;">
                        <?php Tests::run( $key ); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php elseif ( $ran && empty( $selected ) ) : ?>
        <div class="notice notice-warning">
            <p>No scenarios were selected.</p>
        </div>
    <?php endif; ?>

    </div><!-- /.metabox-holder -->
</div>
