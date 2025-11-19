<?php

use Mawiblah\Campaigns;
use Mawiblah\Templates;

?>
<style>
    .graph-wrap {
        background-color: #FFF;
        padding: 20px;
        grid-template-columns: 1fr;
        display: grid;
    }

    .wrap.mawiblah section h2 {
        font-size: 24px;
        margin-top: 48px;
        line-height: normal;
    }

    @media (min-width: 968px) {
        .wrap.mawiblah {

            display: grid;
            gap:20px;
            grid-template-columns: 1fr 1fr;
        }
        .wrap.mawiblah section {

        }
    }

</style>
<h1>Mawiblah</h1>


    <div class="wrap mawiblah">
    <section>
        <h2><?= __('Campaigns raw numbers', 'mawiblah'); ?></h2>
        <div class="graph-wrap">
            <?php
            $data = Campaigns::getDataForDashBoard(12);
            $dataForDisplay = [
                    __('Sent emails', 'mawiblah') => $data['sent'],
                    __('Sending failed', 'mawiblah') => $data['failed'],
                    __('Links clicked', 'mawiblah') => $data['linksClicked'],
            ];
            Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
            ?>
        </div>
    </section>

    <section>
        <h2><?= __('Campaigns conversion rates', 'mawiblah'); ?></h2>
        <div class="graph-wrap">
            <?php
            $data = Campaigns::getDataForDashBoardConversionRate(12);
            $dataForDisplay = [
                    __('Sent emails', 'mawiblah') => $data['sent'],
                    __('Sending failed', 'mawiblah') => $data['failed']
            ];
            Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
            ?>
        </div>
    </section>

    <?php
    $campaings = Campaigns::getLastCampaigns(1);
    $campaignTitle = false;
    $lastCampaing = false;
    if (is_array($campaings)) {
        $lastCampaing = $campaings[0];
        $campaignTitle = $lastCampaing->post_title ?? false;
    }

    if (!$lastCampaing) {
        return;
    }

    ?>
    <section>
        <h2><?= $campaignTitle ?> - <?= __('Latest campaign raw', 'mawiblah'); ?></h2>
        <div class="graph-wrap">
            <?php
            $data = Campaigns::getDataForDashBoard(1);

            $dataForDisplay = [
                    'Email sending failed' => $data['failed'],
                    'Email sending skipped' => $data['skipped'],
                    'Unsubscribed' => $data['unsubscribed'],
                    'Newly unsubscribed' => $data['newlyUnsubscribed'],
                    'Sent emails' => $data['sent'],
                    'User opened' => $data['uniqueUsers'],
                    'Links clicked' => $data['linksClicked']
            ];
            Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
            ?>
        </div>
    </section>

    <section>
        <?php if ($campaignTitle): ?>
            <h2><?= $campaignTitle ?> - <?= __('Latest campaign conversion rate', 'mawiblah'); ?></h2>
            <div class="graph-wrap">
                <?php

                $data = Campaigns::getDataForDashBoardConversionRate(1);

                $dataForDisplay = [
                        'Email sending failed' => $data['failed'],
                        'Email sending skipped' => $data['skipped'],
                        'Unsubscribed' => $data['unsubscribed'],
                        'Newly unsubscribed' => $data['newlyUnsubscribed'],
                        'Sent emails' => $data['sent'],
                        'User opened' => $data['uniqueUsers'],
                        'Links clicked' => $data['linksClicked']
                ];

                Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
                ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($campaignTitle): ?>
        <section>
            <h2><?= $campaignTitle ?> - <?= __('Links clicked', 'mawiblah'); ?></h2>
            <?php
            $headers = [__('Links'), __('Click count')];
            $data = [];

            asort($lastCampaing->links);
            $lastCampaing->links  = array_reverse($lastCampaing->links) ;
            foreach ($lastCampaing->links as $link => $clickCount) {
                $data[] = [$link, $clickCount];
            }

            Templates::renderTable($headers, $data);
            ?>
        </section>
    <?php endif; ?>

    <?php if ($campaignTitle): ?>
        <section>
            <h2><?= $campaignTitle ?> - <?= __('Active days', 'mawiblah'); ?></h2>
            <?php
                $activeDays = Campaigns::getClickTimesByDayOfWeek($lastCampaing->id);
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


</div>


