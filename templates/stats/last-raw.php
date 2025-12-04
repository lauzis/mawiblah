<?php
use Mawiblah\Campaigns;
use Mawiblah\Templates;

$campaignTitle = $data['title'];
?>
    <section>
        <h2><?= esc_html($campaignTitle) ?> - <?= __('Latest campaign raw', 'mawiblah'); ?></h2>
        <div class="graph-wrap">
            <?php
            $rawStats = Campaigns::getDataForDashBoard(1);

            $dataForDisplay = [
                    __('Email sending failed', 'mawiblah') => $rawStats[Campaigns::STAT_FAILED],
                    __('Email sending skipped', 'mawiblah') => $rawStats[Campaigns::STAT_SKIPPED],
                    __('Unsubscribed', 'mawiblah') => $rawStats[Campaigns::STAT_UNSUBSCRIBED],
                    __('Newly unsubscribed', 'mawiblah') => $rawStats[Campaigns::STAT_NEWLY_UNSUBSCRIBED],
                    __('Sent emails', 'mawiblah') => $rawStats[Campaigns::STAT_SENT],
                    __('User opened', 'mawiblah') => $rawStats[Campaigns::STAT_UNIQUE_USERS],
                    __('Links clicked', 'mawiblah') => $rawStats[Campaigns::STAT_LINKS_CLICKED]
            ];
            Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
            ?>
        </div>
    </section>