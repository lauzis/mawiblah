<?php
use Mawiblah\Campaigns;
use Mawiblah\Templates;

$campaignTitle = $data['title'];
$lastCampaign = $data['campaign'];
?>
    <?php if ($campaignTitle): ?>
        <section>
            <h2><?= esc_html($campaignTitle) ?> - <?= __('Active days', 'mawiblah'); ?></h2>
            <?php
                $activeDays = Campaigns::getClickTimesByDayOfWeek($lastCampaign->id);
                $dataForBarGraph = [];
                $dataForBarGraph[__('Weekdays')] = [];
                $headers=['Day','Count'];
                $data = [];

                foreach($activeDays as $day=>$count) {
                    $dataForBarGraph[__('Weekdays')][] = $count;
                    $data[] = [Templates::getDayTranslation($day), $count];
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