<?php

use Mawiblah\Campaigns;
use Mawiblah\Templates;

?>
<style>
    .graph-wrap{
        background-color: #FFF;
        padding:20px;
    }
    .wrap.mawiblah section h2 {
        font-size:24px;
        margin-top:48px;
    }
</style>
<div class="wrap mawiblah">

    <h1>Mawiblah</h1>

    <section>
        <h2>Campaigns raw numbers</h2>
        <div class="graph-wrap">
            <?php
            $data = Campaigns::getDataForDashBoard(12);
            $dataForDisplay['Sent emails'] = $data['sent'];
            $dataForDisplay['Sending failed'] = $data['failed'];
            $dataForDisplay['Links clicked'] = $data['linksClicked'];


            Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
            ?>
        </div>
    </section>

    <section>
        <h2>Campaigns conversion rates</h2>
        <div class="graph-wrap">
            <?php
            $data = Campaigns::getDataForDashBoardConversionRate(12);
            $dataForDisplay['Sent emails'] = $data['sent'];
            $dataForDisplay['Sending failed'] = $data['failed'];
            Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
            ?>
        </div>
    </section>

    <?php
    $campaing = Campaigns::getLastCampaigns(1);
    $campaignTitle = false;
    if (is_array($campaing)) {
        $campaignTitle = $campaing[0]->post_title ?? false;
    }
    ?>
    <section>
        <h2><?= $campaignTitle ?> - Latest campaign raw</h2>
        <div class="graph-wrap">
            <?php
            $data = Campaigns::getDataForDashBoard(1);

            $dataForDisplay = [
                    'Email sending failed'=> $data['failed'],
                    'Email sending skipped'=> $data['skipped'],
                    'Unsubscribed'=> $data['unsubscribed'],
                    'Newly unsubscribed'=> $data['newlyUnsubscribed'],
                    'Sent emails'=> $data['sent'],
                    'User opened'=> $data['uniqueUsers'],
                    'Links clicked'=> $data['linksClicked']
            ];
            Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
            ?>
        </div>
    </section>

    <section>


        <?php if ($campaignTitle): ?>
        <h2><?=$campaignTitle ?> - latest campaign conversion rate</h2>
        <div class="graph-wrap">
            <?php

            $data = Campaigns::getDataForDashBoardConversionRate(1);

            $dataForDisplay = [
                    'Email sending failed'=> $data['failed'],
                    'Email sending skipped'=> $data['skipped'],
                    'Unsubscribed'=> $data['unsubscribed'],
                    'Newly unsubscribed'=> $data['newlyUnsubscribed'],
                    'Sent emails'=> $data['sent'],
                    'User opened'=> $data['uniqueUsers'],
                    'Links clicked'=> $data['linksClicked']
            ];

            Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
            ?>
        </div>
        <?php endif; ?>
    </section>


</div>


