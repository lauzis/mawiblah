<?php
use Mawiblah\Campaigns;
use Mawiblah\Templates;

$campaignTitle = $data['title'];
$lastCampaign = $data['campaign'];
?>
    <?php if ($campaignTitle): ?>
        <section>
            <h2><?= esc_html($campaignTitle) ?> - <?= __('Links clicked', 'mawiblah'); ?></h2>
            <?php
            $headers = [__('Links', 'mawiblah'), __('Click count', 'mawiblah')];
            $tableData = [];

            if (isset($lastCampaign->links) && is_array($lastCampaign->links)) {
                asort($lastCampaign->links);
                $lastCampaign->links  = array_reverse($lastCampaign->links) ;
                foreach ($lastCampaign->links as $link => $clickCount) {
                    $tableData[] = [$link, $clickCount];
                }
            }

            Templates::renderTable($headers, $tableData);
            ?>
        </section>
    <?php endif; ?>