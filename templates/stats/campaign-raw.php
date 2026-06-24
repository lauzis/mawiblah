<?php
use Mawiblah\Campaigns;
use Mawiblah\Settings;
use Mawiblah\Templates;

$campaignTitle = $data['title'];
$stats = $data['stats'];
?>
    <section>
        <h2><?= esc_html($campaignTitle) ?> - <?= __('Campaign raw stats', 'mawiblah'); ?></h2>
        <div class="graph-wrap">
            <?php
            $dataForDisplay = [
                    __('Email sending failed', 'mawiblah') => $stats[Campaigns::STAT_FAILED],
                    __('Email sending skipped', 'mawiblah') => $stats[Campaigns::STAT_SKIPPED],
                    __('Unsubscribed', 'mawiblah') => $stats[Campaigns::STAT_UNSUBSCRIBED],
                    __('Newly unsubscribed', 'mawiblah') => $stats[Campaigns::STAT_NEWLY_UNSUBSCRIBED],
                    __('Sent emails', 'mawiblah') => $stats[Campaigns::STAT_SENT],
                    __('Emails opened', 'mawiblah') => $stats[Campaigns::STAT_EMAILS_OPENED],
                    __('User opened', 'mawiblah') => $stats[Campaigns::STAT_UNIQUE_USERS],
                    __('Links clicked', 'mawiblah') => $stats[Campaigns::STAT_LINKS_CLICKED],
                    __('Emails opened', 'mawiblah') => $stats[Campaigns::STAT_EMAILS_OPENED]
            ];
            if (Settings::openTrackingEnabled()) {
                $dataForDisplay[__('Emails opened (pixel)', 'mawiblah')] = $stats[Campaigns::STAT_EMAILS_OPENED];
            }
            Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
            ?>
        </div>
    </section>