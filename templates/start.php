<?php

use Mawiblah\Campaigns;
use Mawiblah\Templates;

Templates::loadTemplate('stats/styles.php', []);
?>
<h1>Mawiblah</h1>

<h2>Overall statistics</h2>
    <div class="wrap mawiblah">
    <?php Templates::loadTemplate('stats/subscriber-growth.php', []); ?>
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


