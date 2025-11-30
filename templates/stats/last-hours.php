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
            $dataForBarGraph[__('Hours')] = [];
            $headers=['Hour','Count'];
            $data = [];

            foreach($activeHours as $hour=>$count) {
                $dataForBarGraph[__('Hours')][] = $count;
                $data[] = [$hour . ':00', $count];
            }
            ?>

            <div class="graph-wrap">
                <?php Templates::loadTemplate('campaign/bar-graph.php', $dataForBarGraph); ?>
            </div>
            <div class="graph-wrap">
                <?php
                Templates::renderTable($headers, $data);
                ?>
            </div>

        </section>
    <?php endif; ?>