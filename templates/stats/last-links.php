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