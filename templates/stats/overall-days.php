<?php
use Mawiblah\Campaigns;
use Mawiblah\Templates;
?>
        <section>
            <h2><?= __('Overall active days & Campaign start days (last 12 campaigns)', 'mawiblah'); ?></h2>
            <?php
            $activeDays = Campaigns::getClickTimesByDayOfWeekForLastCampaigns(12);
            $startDays = Campaigns::getCampaignStartTimesByDayOfWeek(12);

            $dataForBarGraph = [];
            $dataForBarGraph[__('Active Days', 'mawiblah')] = [];
            $dataForBarGraph[__('Start Days', 'mawiblah')] = [];

            $headers=[__('Day', 'mawiblah'), __('Active Count', 'mawiblah'), __('Start Count', 'mawiblah')];
            $tableData = [];

            foreach($activeDays as $day=>$count) {
                $startCount = $startDays[$day] ?? 0;

                $dataForBarGraph[__('Active Days', 'mawiblah')][] = $count;
                $dataForBarGraph[__('Start Days', 'mawiblah')][] = $startCount;

                $tableData[] = [Templates::getDayTranslation($day), $count, $startCount];
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