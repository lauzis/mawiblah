<?php
use Mawiblah\Campaigns;
use Mawiblah\Templates;

$campaignTitle = $data['title'];
$lastCampaign = $data['campaign'];
?>
    <?php if ($campaignTitle): ?>
        <section>
            <h2><?= esc_html($campaignTitle) ?> - <?= __('Open active days', 'mawiblah'); ?></h2>
            <?php
            $activeDays = Campaigns::getOpenTimesByDayOfWeek($lastCampaign->id);
            $dataForBarGraph = [];
            $dataForBarGraph[__('Weekdays', 'mawiblah')] = [];
            $headers = [__('Day', 'mawiblah'), __('Opens', 'mawiblah')];
            $tableData = [];

            foreach ($activeDays as $day => $count) {
                $dataForBarGraph[__('Weekdays', 'mawiblah')][] = $count;
                $tableData[] = [Templates::getDayTranslation($day), $count];
            }
            ?>

            <div class="graph-wrap">
                <?php Templates::loadTemplate('campaign/bar-graph.php', $dataForBarGraph); ?>
            </div>
            <div class="graph-wrap">
                <?php Templates::renderTable($headers, $tableData); ?>
            </div>

        </section>
    <?php endif; ?>
