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
               placeholder="<?= esc_attr__('e.g. mawiblah_new_posts_since_last_sent', 'mawiblah') ?>">
        <span class="description"><?= __('Optional. The shortcode <strong>name only</strong> — no brackets, no attributes, e.g. <code>mawiblah_new_posts_since_last_sent</code>. Before each scheduled send it is called as <code>[name campaign_id="…"]</code>, with this campaign\'s ID filled in automatically. If it returns empty output the send is skipped. Leave blank to always send.', 'mawiblah') ?></span>
    </p>
    <p id="send_condition_shortcode_trimmed" class="description" style="color:#996800;" hidden></p>
    <script>
        (function () {
            var field = document.getElementById('send_condition_shortcode');
            var note = document.getElementById('send_condition_shortcode_trimmed');
            if (!field || !note) {
                return;
            }
            // Same rule as Campaigns::sendConditionShortcodeName(), which is what the save applies.
            field.addEventListener('change', function () {
                var value = field.value.trim();
                var match = value.match(/^\[*\s*([^\s\[\]\/<>&='"]+)(?:[\s\/\]]|$)/);
                note.hidden = !match || match[1] === value;
                if (!note.hidden) {
                    field.value = match[1];
                    note.textContent = <?= wp_json_encode(__('Only the shortcode name is needed, so the brackets and attributes were removed. The campaign ID is added automatically.', 'mawiblah')) ?>;
                }
            });
        })();
    </script>
    <?php wp_nonce_field('mawiblah_save_campaign_details', 'mawiblah_campaign_details_nonce'); ?>
</div>
