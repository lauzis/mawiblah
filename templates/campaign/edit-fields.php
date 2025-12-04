<?php
$campaign = $data['campaign'];
?>
<div class="mawiblah-meta-box">
    <p>
        <label for="subject"><strong><?= __('Subject', 'mawiblah') ?></strong></label><br>
        <input type="text" name="subject" id="subject" value="<?= esc_attr($campaign->subject) ?>" class="widefat">
    </p>

    <p>
        <label for="contentTitle"><strong><?= __('Content Title', 'mawiblah') ?></strong></label><br>
        <input type="text" name="contentTitle" id="contentTitle" value="<?= esc_attr($campaign->contentTitle) ?>" class="widefat">
    </p>

    <p>
        <label for="template"><strong><?= __('Template', 'mawiblah') ?></strong></label><br>
        <select name="template" id="template" class="widefat">
            <?php $templates = \Mawiblah\Templates::getArrayOfEmailTemplates(); ?>
            <?php foreach ($templates as $template): ?>
                <option value="<?= esc_attr($template) ?>" <?= selected($campaign->template, $template, false) ?>>
                    <?= esc_html($template) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label for="audiences"><strong><?= __('Audiences', 'mawiblah') ?></strong></label><br>
        <?php $audiences = \Mawiblah\Subscribers::getAllAudiences(); ?>
        <select name="audiences[]" id="audiences" multiple class="widefat" style="height: auto;">
            <?php foreach ($audiences as $audience): ?>
                <?php 
                $selected = (isset($campaign->audiences) && is_array($campaign->audiences) && in_array($audience->term_id, $campaign->audiences));
                ?>
                <option value="<?= esc_attr($audience->term_id) ?>" <?= selected($selected, true, false) ?>>
                    <?= esc_html($audience->name) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php wp_nonce_field('mawiblah_save_campaign_details', 'mawiblah_campaign_details_nonce'); ?>
</div>
