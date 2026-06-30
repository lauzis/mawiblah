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
            <?php foreach ($templates as $value => $label): ?>
                <option value="<?= esc_attr($value) ?>" <?= selected($campaign->template, $value, false) ?>>
                    <?= esc_html($label) ?>
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
    <p>
        <label>
            <input type="checkbox" name="rerender_on_recurring" value="1" <?= checked($campaign->rerender_on_recurring, true, false) ?>>
            <?= __('Re-render template on each recurring send', 'mawiblah') ?>
        </label>
    </p>

    <p>
        <label for="send_condition_shortcode"><strong><?= __('Send Condition Shortcode', 'mawiblah') ?></strong></label><br>
        <input type="text" name="send_condition_shortcode" id="send_condition_shortcode"
               value="<?= esc_attr($campaign->send_condition_shortcode) ?>" class="widefat"
               placeholder="<?= esc_attr__('e.g. mawiblah_we_have_new_posts_since_last_sent_out', 'mawiblah') ?>">
        <span class="description"><?= __('Optional. Shortcode name (without brackets). Before each scheduled send the shortcode is called with <code>campaign_id</code> attribute. If it returns empty output the send is skipped. Leave blank to always send.', 'mawiblah') ?></span>
    </p>
    <?php wp_nonce_field('mawiblah_save_campaign_details', 'mawiblah_campaign_details_nonce'); ?>
</div>
