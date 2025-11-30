<?php if (!isset($data) || !is_array($data) || count($data) === 0): ?>
    <div class="stats-box-email-container">
        <?php _e('No data to display.', 'mawiblah'); ?>
    </div>
<?php return; endif; ?>

<?php

$colors = [
        'green',
        'yellow',
        'blue',
        'red',
        'purple',
        'cyan',
        'magenta',
];
$keys = array_keys($data);

$maxArray = [];
$avgArray = [];

$arrayItemCount = 0;
$maxOfMax = 0;
foreach ($keys as $key) {
    $arrayItemCount = max($arrayItemCount, count($data[$key]));
    $maxArray[$key] = (count($data[$key]) > 0) ? (max($data[$key]) ?: 1) : 1;
    $avgArray[$key] = array_sum($data[$key]) / (count($data[$key]) ?: 1);
}
$maxOfMax = max($maxArray);
$oneItem = $arrayItemCount === 1;
?>
<div class="stats-box-email-container">
    <div class="stats-box-email-sent">
        <?php foreach ($data[$keys[0]] as $index => $clicks): ?>
            <div class="stats-box-bar-group">
                <?php foreach ($keys as $colorIndex => $k2): ?>
                    <?php
                    $count = $data[$k2][$index];
                    $max = $oneItem ? $maxOfMax : $maxArray[$k2];
                    $color = $colors[$colorIndex];
                    ?>
                    <div class="mawiblah-stats-bar <?= $color; ?>" style="height:<?= floor($count / $max * 100); ?>%">
                        <div><?= $count ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="stats-box-email-sent-labels">
        <table>
            <thead>
            <tr>
                <th></th>
                <th><?= __('Max', 'mawiblah'); ?></th>
                <th><?= __('Avg', 'mawiblah'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($keys as $colorIndex => $k2): ?>
                <tr>
                    <td>
                        <div>
                            <?php $color = $colors[$colorIndex]; ?>
                            <span class="mawiblah-stats-square <?= $color ?>"></span><?= $k2; ?>
                        </div>
                    </td>
                    <td align="right">
                        <?= $maxArray[$k2] ?>
                    </td>
                    <td align="right">
                        <?= round($avgArray[$k2], 2) ?>
                    </td>
                </tr>

            <?php endforeach; ?>
            </tbody>
        </table>

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
        height: 300px;
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

    .mawiblah-stats-bar.red {
        background-color: #ff9999; /* A medium-light red */
        border-color: #990000; /* A deep, dark red */
    }

    .mawiblah-stats-bar.purple {
        background-color: #c990ff; /* A medium-light purple */
        border-color: #4d0099; /* A very deep purple */
    }

    .mawiblah-stats-bar.cyan {
        background-color: #80ffff; /* A bright, light cyan */
        border-color: #00bfff; /* A deep sky blue/azure that complements the cyan */
    }

    .mawiblah-stats-bar.magenta {
        background-color: #ff99ff; /* A medium-light magenta/fuchsia */
        border-color: #990099; /* A deep, slightly darker purple-magenta */
    }

    .mawiblah-stats-square {
        background-color: #bfe5f6;
        border-width: 2px;
        border-style: solid;
        border-color: #0096dd;
        border-radius: 6px;
        display: inline-block;
        width: 20px;
        height: 20px;
        overflow: hidden;
        margin-right: 5px;
    }

    .mawiblah-stats-square.green {
        background-color: #bff6e0;
        border-color: #00b871;
    }

    .mawiblah-stats-square.yellow {
        background-color: #fff7bf;
        border-color: #ddc200;
    }

    .mawiblah-stats-square.red {
        background-color: #ff9999; /* A medium-light red */
        border-color: #990000; /* A deep, dark red */
    }

    .mawiblah-stats-square.purple {
        background-color: #c990ff; /* A medium-light purple */
        border-color: #4d0099; /* A very deep purple */
    }

    .mawiblah-stats-square.cyan {
        background-color: #80ffff; /* A bright, light cyan */
        border-color: #00bfff; /* A deep sky blue/azure that complements the cyan */
    }

    .mawiblah-stats-square.magenta {
        background-color: #ff99ff; /* A medium-light magenta/fuchsia */
        border-color: #990099; /* A deep, slightly darker purple-magenta */
    }


    .stats-box-email-sent-labels {
        margin-top: 20px;
    }

    .stats-box-email-sent-labels table {
        width: 100%;
    }

    .stats-box-email-sent-labels table,
    .stats-box-email-sent-labels table tr td {
        border-width: 1px;
        border-style: solid;
        border-color: #0003;
        border-collapse: collapse;
    }

    .stats-box-email-sent-labels table tr th {
        padding: 20px;
        font-size: 18px;
    }

    .stats-box-email-sent-labels table tr td {
        padding: 5px 20px;
    }

    .stats-box-email-sent-labels table tr:nth-child(2n) td {
        background-color:#F6F7F7;
    }

    .stats-box-email-sent-labels div {
        padding: 10px 0;
        line-height: 20px;
        height: 20px;
        display: flex;
        flex-direction: row;
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
        text-align: left;
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
