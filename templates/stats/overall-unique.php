<?php
use Mawiblah\Campaigns;
use Mawiblah\Templates;
?>
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