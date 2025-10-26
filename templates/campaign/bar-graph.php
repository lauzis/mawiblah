<?php
$maxLinksClicked = max($data['linksClicked']) ?: 1;
$maxUniqueUsers = max($data['uniqueUsers']) ?: 1;
$maxSentEmails = max($data['sent']) ?: 1;
?>
<div class="stats-box-email-container">
    <div class="stats-box-email-sent">
        <?php foreach ($data['linksClicked'] as $index => $clicks): ?>
            <?php
            $uniqueUsers = $data['uniqueUsers'][$index];
            $sentEmails = $data['sent'][$index];
            ?>
            <div class="stats-box-bar-group">
                <div class="mawiblah-stats-bar green" style="height:<?= floor($sentEmails / $maxSentEmails * 100); ?>%">
                    <div><?= $sentEmails ?></div>
                </div>
                <div class="mawiblah-stats-bar yellow" style="height:<?= floor($uniqueUsers / $maxUniqueUsers * 100); ?>%">
                    <div><?= $uniqueUsers ?></div>
                </div>
                <div class="mawiblah-stats-bar" style="height:<?= floor($clicks / $maxLinksClicked * 100); ?>%">
                    <div><?= $clicks ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
    .stats-box-email-labels {
        padding: 20px 0;
    }

    .stats-box-email-container {
        position: relative;
    }

    .stats-box-email-sent {
        position: relative;
        bottom: 0;
        height: 200px;
        width: 100%;
        background-color: #F0F0F0;
        display: flex;
        flex-direction: row;
        align-items: flex-end;
        border-radius: 6px 6px 0 0;
        border-width: 1px;
        border-color: #0003;
        border-style: solid;
        padding: 20px 5px 0;
        box-sizing: border-box;

    }

    .stats-box-bar-group {
        padding: 0 5px;
        position: relative;
        bottom: 0;
        left: 0;
        flex-grow: 1;
        display: flex;
        flex-direction: row;
        height: 100%;
        align-items: flex-end;
    }

    .mawiblah-stats-bar {
        background-color: #bfe5f6;
        border-width: 2px;
        border-style: solid;
        border-color: #0096dd;
        margin: 0 2px;
        flex-grow: 1;
        text-align: center;
        border-radius: 6px 6px 0 0;
        position: relative;
        border-bottom-width: 0;
    }

    .mawiblah-stats-bar.green {
        background-color: #bff6e0;
        border-color: #00b871;
    }

    .mawiblah-stats-bar.yellow {
        background-color: #fff7bf;
        border-color: #ddc200;
    }

    .mawiblah-stats-bar div {
        position: absolute;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
    }

    .stats-line {
        position: absolute;
        bottom: 0;
        width: 100%;
        overflow: hidden;
        border-width: 0;
        border-top-width: 2px;
        border-style: dashed;
        border-color: black;
        max-height: 100%;
    }

    .stats-month-avg-line {
        border-color: green;
        text-aling: left;
        font-weight: bold;
        text-shadow: 0 0 4px white;
        padding-left: 5px;
        box-sizing: border-box;
    }

    .stats-year-avg-line {
        border-color: red;
        text-align: right;
        font-weight: bold;
        text-shadow: 0 0 4px white;
        padding-right: 5px;
        box-sizing: border-box;
    }
</style>
