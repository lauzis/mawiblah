<?php

use Mawiblah\Campaigns;
use Mawiblah\Templates;

Templates::loadTemplate('stats/styles.php', []);
?>
<h1>Mawiblah</h1>

<div style="margin-bottom:20px;display:flex;gap:8px;flex-wrap:wrap;">
    <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=mawiblah_subscriber_category&post_type=mawiblah_subscriber')); ?>"
       class="button" style="display:inline-flex;align-items:center;gap:4px;">
        <span class="dashicons dashicons-category" style="font-size:16px;"></span>
        <?php esc_html_e('Add Audience', 'mawiblah'); ?>
    </a>
    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=mawiblah_subscriber')); ?>"
       class="button" style="display:inline-flex;align-items:center;gap:4px;">
        <span class="dashicons dashicons-groups" style="font-size:16px;"></span>
        <?php esc_html_e('Add Subscriber', 'mawiblah'); ?>
    </a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=mawiblah-campaigns&action=create-campaign')); ?>"
       class="button button-primary" style="display:inline-flex;align-items:center;gap:4px;">
        <span class="dashicons dashicons-megaphone" style="font-size:16px;"></span>
        <?php esc_html_e('New Campaign', 'mawiblah'); ?>
    </a>
</div>

<h2>Overall statistics</h2>
    <div class="wrap mawiblah">
    <?php Templates::loadTemplate('stats/subscriber-growth.php', []); ?>
    <?php Templates::loadTemplate('stats/unsubscribe-growth.php', []); ?>
    <?php Templates::loadTemplate('stats/overall-sent.php', []); ?>
    <?php Templates::loadTemplate('stats/overall-unique.php', []); ?>
    <?php Templates::loadTemplate('stats/overall-rating.php', []); ?>
    <?php Templates::loadTemplate('stats/overall-clicks.php', []); ?>
    <?php Templates::loadTemplate('stats/overall-days.php', []); ?>
    <?php Templates::loadTemplate('stats/overall-hours.php', []); ?>

    <?php
    $campaigns = Campaigns::getLastCampaigns(1);
    $campaignTitle = false;
    $lastCampaign = false;
    if (is_array($campaigns)) {
        $lastCampaign = $campaigns[0];
        $campaignTitle = $lastCampaign->post_title ?? false;
    }
?>
    </div>
<?php
    if (!$lastCampaign) {
        return;
    }

    $lastCampaignData = ['campaign' => $lastCampaign, 'title' => $campaignTitle];
    ?>

    <h2>Last campaign results</h2>
    <div class="wrap mawiblah">
    <?php Templates::loadTemplate('stats/last-raw.php', $lastCampaignData); ?>
    <?php Templates::loadTemplate('stats/last-conversion.php', $lastCampaignData); ?>
    <?php Templates::loadTemplate('stats/last-links.php', $lastCampaignData); ?>
    <?php Templates::loadTemplate('stats/last-days.php', $lastCampaignData); ?>
    <?php Templates::loadTemplate('stats/last-hours.php', $lastCampaignData); ?>
</div>


