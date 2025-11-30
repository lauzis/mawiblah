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

    h1 {
        font-size: 36px;
    }

    h2 {
        font-size: 28px;
        line-height: normal;
    }

    .wrap.mawiblah section h2 {
        font-size: 24px;
        margin-top: 24px;
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

<h2>Overall statistics</h2>
    <div class="wrap mawiblah">
    <section>
        <h2><?= __('Campaigns sent emails', 'mawiblah'); ?></h2>
        <div class="graph-wrap">
            <?php
            $data = Campaigns::getDataForDashBoard(12);
            $dataRate = Campaigns::getDataForDashBoardConversionRate(12);
            $dataForDisplay = [
                    __('Sent emails', 'mawiblah') => $data[Campaigns::STAT_SENT],
                    __('Sending failed %', 'mawiblah') => $dataRate[Campaigns::STAT_FAILED],
            ];
            Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
            ?>
        </div>
    </section>

        <section>
            <h2><?= __('Campaigns links clicked unique users', 'mawiblah'); ?></h2>
            <div class="graph-wrap">
                <?php
                $data = Campaigns::getDataForDashBoard(12);
                $dataForDisplay = [
                        __('Sent emails', 'mawiblah') => $data[Campaigns::STAT_SENT],
                        __('Links clicked', 'mawiblah') => $data[Campaigns::STAT_UNIQUE_USERS],
                ];
                Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
                ?>
            </div>
        </section>

        <section>
            <h2><?= __('Activity rating (Active days / Campaign start days)', 'mawiblah'); ?></h2>
            <?php
            $activeDays = Campaigns::getClickTimesByDayOfWeekForLastCampaigns(12);
            $startDays = Campaigns::getCampaignStartTimesByDayOfWeek(12);
            $dataForBarGraph = [];
            $dataForBarGraph[__('Weekdays')] = [];
            $headers=['Day','Rating'];
            $data = [];

            foreach($activeDays as $day=>$count) {
                $startCount = $startDays[$day] ?? 0;
                $rating = $startCount > 0 ? round($count / $startCount, 2) : 0;

                $dataForBarGraph[__('Weekdays')][] = $rating;
                $data[] = [Templates::getDayTranslation($day), $rating];
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

        <section>
            <h2><?= __('Campaigns links clicked total', 'mawiblah'); ?></h2>
            <div class="graph-wrap">
                <?php
                $data = Campaigns::getDataForDashBoard(12);
                $dataForDisplay = [
                        __('Sent emails', 'mawiblah') => $data[Campaigns::STAT_SENT],
                        __('Links clicked', 'mawiblah') => $data[Campaigns::STAT_LINKS_CLICKED],
                ];
                Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
                ?>
            </div>
        </section>

        <section>
            <h2><?= __('Overall active days & Campaign start days (last 12 campaigns)', 'mawiblah'); ?></h2>
            <?php
            $activeDays = Campaigns::getClickTimesByDayOfWeekForLastCampaigns(12);
            $startDays = Campaigns::getCampaignStartTimesByDayOfWeek(12);

            $dataForBarGraph = [];
            $dataForBarGraph[__('Active Days', 'mawiblah')] = [];
            $dataForBarGraph[__('Start Days', 'mawiblah')] = [];

            $headers=[__('Day', 'mawiblah'), __('Active Count', 'mawiblah'), __('Start Count', 'mawiblah')];
            $data = [];

            foreach($activeDays as $day=>$count) {
                $startCount = $startDays[$day] ?? 0;

                $dataForBarGraph[__('Active Days', 'mawiblah')][] = $count;
                $dataForBarGraph[__('Start Days', 'mawiblah')][] = $startCount;

                $data[] = [Templates::getDayTranslation($day), $count, $startCount];
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

        <section>
            <h2><?= __('Overall active hours (last 12 campaigns)', 'mawiblah'); ?></h2>
            <?php
            $activeHours = Campaigns::getClickTimesByHourOfDayForLastCampaigns(12);
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

    <?php
    $campaigns = Campaigns::getLastCampaigns(1);
    $campaignTitle = false;
    $lastCampaign = false;
    if (is_array($campaigns)) {
        $lastCampaign = $campaigns[0];
        $campaignTitle = $lastCampaign->post_title ?? false;
    }
?>
    </div>
<?php
    if (!$lastCampaign) {
        return;
    }

    ?>

    <h2>Last campaign results</h2>
    <div class="wrap mawiblah">
    <section>
        <h2><?= esc_html($campaignTitle) ?> - <?= __('Latest campaign raw', 'mawiblah'); ?></h2>
        <div class="graph-wrap">
            <?php
            $data = Campaigns::getDataForDashBoard(1);

            $dataForDisplay = [
                    'Email sending failed' => $data[Campaigns::STAT_FAILED],
                    'Email sending skipped' => $data[Campaigns::STAT_SKIPPED],
                    'Unsubscribed' => $data[Campaigns::STAT_UNSUBSCRIBED],
                    'Newly unsubscribed' => $data[Campaigns::STAT_NEWLY_UNSUBSCRIBED],
                    'Sent emails' => $data[Campaigns::STAT_SENT],
                    'User opened' => $data[Campaigns::STAT_UNIQUE_USERS],
                    'Links clicked' => $data[Campaigns::STAT_LINKS_CLICKED]
            ];
            Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
            ?>
        </div>
    </section>

    <section>
        <?php if ($campaignTitle): ?>
            <h2><?= esc_html($campaignTitle) ?> - <?= __('Latest campaign conversion rate', 'mawiblah'); ?></h2>
            <div class="graph-wrap">
                <?php

                $data = Campaigns::getDataForDashBoardConversionRate(1);

                $dataForDisplay = [
                        'Email sending failed' => $data[Campaigns::STAT_FAILED],
                        'Email sending skipped' => $data[Campaigns::STAT_SKIPPED],
                        'Unsubscribed' => $data[Campaigns::STAT_UNSUBSCRIBED],
                        'Newly unsubscribed' => $data[Campaigns::STAT_NEWLY_UNSUBSCRIBED],
                        'Sent emails' => $data[Campaigns::STAT_SENT],
                        'User opened' => $data[Campaigns::STAT_UNIQUE_USERS],
                        'Links clicked' => $data[Campaigns::STAT_LINKS_CLICKED]
                ];

                Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
                ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($campaignTitle): ?>
        <section>
            <h2><?= esc_html($campaignTitle) ?> - <?= __('Links clicked', 'mawiblah'); ?></h2>
            <?php
            $headers = [__('Links'), __('Click count')];
            $data = [];

            asort($lastCampaign->links);
            $lastCampaign->links  = array_reverse($lastCampaign->links) ;
            foreach ($lastCampaign->links as $link => $clickCount) {
                $data[] = [$link, $clickCount];
            }

            Templates::renderTable($headers, $data);
            ?>
        </section>
    <?php endif; ?>

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


</div>


