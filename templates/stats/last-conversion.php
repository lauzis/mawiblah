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

                $data = Campaigns::getDataForDashBoardConversionRate(1);

                $dataForDisplay = [
                        'Email sending failed' => $data[Campaigns::STAT_FAILED],
                        'Email sending skipped' => $data[Campaigns::STAT_SKIPPED],
                        'Unsubscribed' => $data[Campaigns::STAT_UNSUBSCRIBED],
                        'Newly unsubscribed' => $data[Campaigns::STAT_NEWLY_UNSUBSCRIBED],
                        'Sent emails' => $data[Campaigns::STAT_SENT],
                        'User opened' => $data[Campaigns::STAT_UNIQUE_USERS],
                        'Links clicked' => $data[Campaigns::STAT_LINKS_CLICKED]
                ];

                Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
                ?>
            </div>
        <?php endif; ?>
    </section>