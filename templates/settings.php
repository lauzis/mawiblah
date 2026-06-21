<?php

use Mawiblah\Settings;

?>
<div class="<?= esc_attr(MAWIBLAH_PLUGIN_DIRECTORY_NAME) ?>">

    <?php Settings::settings_page_visited(); ?>
    <?php $sections = Settings::get_sections(); ?>
    <h1><?= esc_html(Settings::get_translation('MAWIBLAH settings')); ?>
        - <?php print esc_html(MAWIBLAH_PLUGIN_NAME . " " . MAWIBLAH_VERSION); ?></h1>
    <?php Settings::print_all_messages(); ?>

    <?php
        $enabled_values = [
            1,
            "tag-manager",
            "analytics",
            "enable-php-log",
            "enable-console-log",
            "enable-show-on-front",
            "enable-file-log",
        ];

        $disabled_values = [
            0,
            "added-tag-manager",
            "added-tag-analytics",
            "added-idk",
            "disabled"
        ]

    ?>

    <form method="post" action="<?= Settings::get_settings_page_url() ?>" class="<?= esc_attr(MAWIBLAH_PLUGIN_DIRECTORY_NAME) ?>" autocomplete="off">
        <?php settings_fields('gae-settings-group'); ?>
        <?php $count_of_sections = count($sections); ?>
        <?php $counter=0; ?>

        <?php foreach ($sections as $section): ?>
            <?php $counter++; ?>
            <?php $enabled=""; ?>

            <?php
                if ($counter===$count_of_sections){
                    $last=" ".MAWIBLAH_PLUGIN_DIRECTORY_NAME."-section-last";
                } else {
                    $last="";
                }
            ?>

            <?php foreach($section["fields"] as $field){
                if (in_array($field["value"],$enabled_values) && ($field["type"]=="switch" || $field["type"]=="select")){
                    $enabled=" section-enabled";
                    $onOff="true";
                    break;
                } elseif (in_array($field["value"],$disabled_values) && ($field["type"]=="switch" || $field["type"]=="select")) {
                    $enabled=" section-disabled";
                    $onOff="false";
                }

            } ?>

            <div id="<?= esc_attr($section["id"]) ?>" class="postbox-container <?= esc_attr(MAWIBLAH_PLUGIN_DIRECTORY_NAME) ?>-section<?= esc_attr($enabled) ?><?= esc_attr($last) ?>">

                <div class="meta-box-sortables closed">
                    <div id="<?= esc_attr($section["id"]) ?>" class="postbox <?= esc_attr($section["id"]) ?> ">

                        <button type="button" class="handlediv section-title" aria-expanded="false">
                            <span class="screen-reader-text"><?= esc_html(Settings::get_translation("Toggle panel")); ?>: <?= esc_html(Settings::get_translation($section["title"])); ?></span>
                            <span class="toggle-indicator" aria-hidden="true"></span>
                        </button>

                        <h2 id="section-<?= esc_attr($section["id"]) ?>" class="section-title">
                            <span>
                                <?= esc_html(Settings::get_translation($section["title"])); ?>
                            </span>
                        </h2>

                        <div class="inside">

                            <p class="<?= esc_attr(MAWIBLAH_PLUGIN_DIRECTORY_NAME) ?>-description">
                                <?= wp_kses_post(Settings::get_translation($section["description"])); ?>
                            </p>

                            <?php if (!empty($section["example"])): ?>
                                <code class="<?= esc_attr(MAWIBLAH_PLUGIN_DIRECTORY_NAME) ?>-code">
                                    <?= esc_html(Settings::get_translation($section["example"])); ?>
                                </code>
                            <?php endif; ?>

                            <ul id="section-<?= esc_attr($section["id"]) ?>-content" class="<?= esc_attr(MAWIBLAH_PLUGIN_DIRECTORY_NAME) ?>-content">
                                <?php foreach ($section["fields"] as $field): ?>
                                    <?php $title = Settings::get_translation($field["title"]) ?>
                                    <?php $id = $field["id"] ?>
                                    <?php $value = $field["value"] ?>
                                    <?php $default_value = $field["default_value"] ?>
                                    <?php $placeholder = !empty($field["placeholder"]) ? Settings::get_translation($field["placeholder"]) : "" ?>
                                    <?php $options = !empty($field["options"]) ? $field["options"] : [] ?>
                                    <?php $description = !empty($field["description"]) ? Settings::get_translation($field["description"]) : "" ?>
                                    <?php if ($id === "gea-debug-ip") {
                                        $description .= Settings::get_translation("<br/>Your current IP address is: ") . esc_html($_SERVER["REMOTE_ADDR"]);
                                    } ?>
                                    <li><?php
                                        $allowed_types = ['text', 'textarea', 'select', 'checkbox', 'switch']; // Add all valid field types
                                        $type = in_array($field["type"], $allowed_types) ? $field["type"] : 'text';
                                        $field_path = MAWIBLAH_TEMPLATES_PATH . "/settings/fields/" . $type . ".php";
                                        if (file_exists($field_path)) {
                                            require($field_path);
                                        } else {
                                            echo "Field type not found: " . htmlspecialchars($type)." file: ".$field_path;
                                        }
                                    ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                    </div>
                </div>

            </div>
        <?php endforeach; ?>
        <section class="<?= MAWIBLAH_PLUGIN_DIRECTORY_NAME ?>-submit">
            <input type="submit" class="button-primary" value="<?= Settings::get_translation('Save Changes') ?>"/>
            <?php if (MAWIBLAH_DEVELOPER): ?>
                <a href="<?= esc_url(Settings::get_settings_page_url() . '&generate-pot-file') ?>" class="button-secondary">
                    <?= Settings::get_translation('Generate Translation Template') ?> <?= sprintf(Settings::get_translation("(Collected %s items)"),Settings::get_translation_count()); ?></a>

            <?php endif ?>
        </section>

    </form>

</div>
