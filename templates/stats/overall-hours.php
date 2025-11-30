<?php
use Mawiblah\Campaigns;
use Mawiblah\Templates;
?>
        <section>
            <h2><?= __('Overall active hours (last 12 campaigns)', 'mawiblah'); ?></h2>
            <?php
            $activeHours = Campaigns::getClickTimesByHourOfDayForLastCampaigns(12);
            $dataForBarGraph = [];
            $dataForBarGraph[__('Hours', 'mawiblah')] = [];
            $headers=[__('Hour', 'mawiblah'), __('Count', 'mawiblah')];
            $tableData = [];

            foreach($activeHours as $hour=>$count) {
                $dataForBarGraph[__('Hours', 'mawiblah')][] = $count;
                $tableData[] = [$hour . ':00', $count];
            }
            ?>

            <div class="graph-wrap">
                <?php Templates::loadTemplate('campaign/bar-graph.php', $dataForBarGraph); ?>
            </div>
            <div class="graph-wrap">
                <?php
                Templates::renderTable($headers, $tableData);
                ?>
            </div>
        </section>