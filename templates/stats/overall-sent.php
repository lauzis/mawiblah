<?php
use Mawiblah\Campaigns;
use Mawiblah\Templates;
?>
    <section>
        <h2><?= __('Campaigns sent emails', 'mawiblah'); ?></h2>
        <div class="graph-wrap">
            <?php
            $statsData = Campaigns::getDataForDashBoard(12);
            $dataRate = Campaigns::getDataForDashBoardConversionRate(12);
            $dataForDisplay = [
                    __('Sent emails', 'mawiblah') => $statsData[Campaigns::STAT_SENT],
                    __('Sending failed %', 'mawiblah') => $dataRate[Campaigns::STAT_FAILED],
            ];
            Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
            ?>
        </div>
    </section>