<?php
use Mawiblah\Campaigns;
use Mawiblah\Templates;

$campaignTitle = $data['title'];
?>
    <section>
        <?php if ($campaignTitle): ?>
            <h2><?= esc_html($campaignTitle) ?> - <?= __('Latest campaign conversion rate', 'mawiblah'); ?></h2>
            <div class="graph-wrap">
                <?php

                $conversionStats = Campaigns::getDataForDashBoardConversionRate(1);

                $dataForDisplay = [
                        __('Email sending failed', 'mawiblah') => $conversionStats[Campaigns::STAT_FAILED],
                        __('Email sending skipped', 'mawiblah') => $conversionStats[Campaigns::STAT_SKIPPED],
                        __('Unsubscribed', 'mawiblah') => $conversionStats[Campaigns::STAT_UNSUBSCRIBED],
                        __('Newly unsubscribed', 'mawiblah') => $conversionStats[Campaigns::STAT_NEWLY_UNSUBSCRIBED],
                        __('Sent emails', 'mawiblah') => $conversionStats[Campaigns::STAT_SENT],
                        __('User opened', 'mawiblah') => $conversionStats[Campaigns::STAT_UNIQUE_USERS],
                        __('Links clicked', 'mawiblah') => $conversionStats[Campaigns::STAT_LINKS_CLICKED]
                ];

                Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
                ?>
            </div>
        <?php endif; ?>
    </section>