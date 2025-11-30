<?php
use Mawiblah\Campaigns;
use Mawiblah\Templates;
?>
        <section>
            <h2><?= __('Activity rating (Active days / Campaign start days)', 'mawiblah'); ?></h2>
            <?php
            $activeDays = Campaigns::getClickTimesByDayOfWeekForLastCampaigns(12);
            $startDays = Campaigns::getCampaignStartTimesByDayOfWeek(12);
            $dataForBarGraph = [];
            $dataForBarGraph[__('Weekdays', 'mawiblah')] = [];
            $headers=[__('Day', 'mawiblah'), __('Rating', 'mawiblah')];
            $tableData = [];

            foreach($activeDays as $day=>$count) {
                $startCount = $startDays[$day] ?? 0;
                $rating = $startCount > 0 ? round($count / $startCount, 2) : 0;

                $dataForBarGraph[__('Weekdays', 'mawiblah')][] = $rating;
                $tableData[] = [Templates::getDayTranslation($day), $rating];
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