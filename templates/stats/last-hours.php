<?php
use Mawiblah\Campaigns;
use Mawiblah\Templates;

$campaignTitle = $data['title'];
$lastCampaign = $data['campaign'];
?>
    <?php if ($campaignTitle): ?>
        <section>
            <h2><?= esc_html($campaignTitle) ?> - <?= __('Active hours', 'mawiblah'); ?></h2>
            <?php
            $activeHours = Campaigns::getClickTimesByHourOfDay($lastCampaign->id);
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
    <?php endif; ?>