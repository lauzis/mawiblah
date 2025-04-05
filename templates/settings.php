<?php

use Mawiblah\Settings;

?>
<div class="<?= MAWIBLAH_PLUGIN_DIRECTORY_NAME ?>">

    <?php Settings::settings_page_visited(); ?>
    <?php $sections = Settings::get_sections(); ?>
    <h1><?= Settings::get_translation('MAWIBLAH settings'); ?>
        - <?php print mawiblah_PLUGIN_NAME . " " . mawiblah_CURRENT_VERSION; ?></h1>
    <?php Settings::print_all_messages(); ?>
    <p>
        <?= Settings::get_translation(""); ?>
    </p>

    <?php if (Settings::show_donation_block()) : ?>
        <?php include(mawiblah_INCLUDES_PATH . "/donation.php"); ?>
    <?php endif; ?>

    <?php
        $enabled_values = [
            1,
            "tag-manager",
            "analytics",
            "enable-php-log",
            "enable-console-log",
            "enable-show-on-front"
        ];

        $disabled_values = [
            0,
            "added-tag-manager",
            "added-tag-analytics",
            "added-idk",
            "disabled"
        ]

    ?>

    <form method="post" action="<?= Settings::get_settings_page_url() ?>" class="<?= MAWIBLAH_PLUGIN_DIRECTORY_NAME ?>" autocomplete="off">
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

            <div id="<?= $section["id"] ?>" class="postbox-container <?= MAWIBLAH_PLUGIN_DIRECTORY_NAME ?>-section<?= $enabled ?><?= $last ?>">

                <div class="meta-box-sortables closed">
                    <div id="<?= $section["id"] ?>-" class="postbox <?= $section["id"] ?> ">

                        <button type="button" class="handlediv section-title" aria-expanded="false">
                            <span class="screen-reader-text"><?= Settings::get_translation("Toggle panel"); ?>: <?= Settings::get_translation($section["title"]); ?></span>
                            <span class="toggle-indicator" aria-hidden="true"></span>
                        </button>

                        <h2 id="section-<?= $section["id"] ?>" class="section-title">
                            <span>
                                <?= Settings::get_translation($section["title"]); ?>
                            </span>
                        </h2>

                        <div class="inside">

                            <p class="<?= mawiblah_PLUGIN_DIRECTORY ?>-description">
                                <?= Settings::get_translation($section["description"]); ?>
                            </p>

                            <?php if (!empty($section["example"])): ?>
                                <code class="<?= mawiblah_PLUGIN_DIRECTORY ?>-code">
                                    <?= htmlentities(Settings::get_translation($section["example"])); ?>
                                </code>
                            <?php endif; ?>

                            <ul id="section-<?= $section["id"] ?>-content" class="<?= MAWIBLAH_PLUGIN_DIRECTORY_NAME ?>-content">
                                <?php foreach ($section["fields"] as $field): ?>
                                    <?php $title = Settings::get_translation($field["title"]) ?>
                                    <?php $id = $field["id"] ?>
                                    <?php $value = $field["value"] ?>
                                    <?php $default_value = $field["default_value"] ?>
                                    <?php $placeholder = !empty($field["placeholder"]) ? Settings::get_translation($field["placeholder"]) : "" ?>
                                    <?php $options = !empty($field["options"]) ? $field["options"] : [] ?>
                                    <?php $description = !empty($field["description"]) ? Settings::get_translation($field["description"]) : "" ?>
                                    <?php if ($id === "gea-debug-ip") {
                                        $description .= Gae_Admin::get_translation("<br/>You current IP address is: ") . $_SERVER["REMOTE_ADDR"];
                                    } ?>
                                    <li><?php require(mawiblah_INCLUDES_PATH . "/fields/" . $field["type"] . ".php"); ?></li>
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
                <a href="<?= Settings::get_settings_page_url() ?>&generate-pot-file" class="button-secondary">
                    <?= Settings::get_translation('Generate Translation Template') ?> <?= sprintf(Settings::get_translation("(Collected %s items)"),Settings::get_translation_count()); ?></a>

            <?php endif ?>
        </section>

    </form>

</div>
