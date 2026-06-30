<?php

use Mawiblah\Campaigns;
use Mawiblah\Helpers;
use Mawiblah\Init;

$campaign = Campaigns::getCampaignById($campaignPostId);
if (!$campaign) {
    wp_die(esc_html__('Campaign not found.', 'mawiblah'));
}

$stopUrl = Helpers::generatePluginUrl(
    ['action' => 'campaign-stop-background', 'campaignPostId' => $campaignPostId],
    'campaignPostId'
);

$listUrl = admin_url('admin.php?page=' . Init::MAWIBLAH_CAMPAIGNS);

$counters         = Campaigns::getCounters($campaign);
$sent             = (int) ($counters->emailsSend ?? 0);
$failed           = (int) ($counters->emailsFailed ?? 0);
$skipped          = (int) ($counters->emailsSkipped ?? 0);
$unsubed          = (int) ($counters->emailsUnsubed ?? 0);
$total            = $sent + $failed + $skipped + $unsubed;
$totalSubscribers = (int) ($campaign->totalSubscribers ?? 0);
?>
<div class="wrap mawiblah">
    <h1><?php esc_html_e('Background Send in Progress', 'mawiblah'); ?></h1>
    <p><?php esc_html_e('This campaign is being sent in the background via WP Cron. You can safely close this tab.', 'mawiblah'); ?></p>

    <div id="mawiblah-bg-progress" class="mawiblah-bg-progress">
        <table class="widefat" style="max-width:480px;">
            <tbody>
                <tr>
                    <th><?php esc_html_e('Sent', 'mawiblah'); ?></th>
                    <td id="bg-sent"><?= esc_html($sent); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Failed', 'mawiblah'); ?></th>
                    <td id="bg-failed"><?= esc_html($failed); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Skipped', 'mawiblah'); ?></th>
                    <td id="bg-skipped"><?= esc_html($skipped); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Unsubscribed', 'mawiblah'); ?></th>
                    <td id="bg-unsubed"><?= esc_html($unsubed); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Total processed', 'mawiblah'); ?></th>
                    <td id="bg-total"><?= esc_html($total); ?></td>
                </tr>
                <?php if ($totalSubscribers > 0): ?>
                <tr>
                    <th><?php esc_html_e('Total subscribers', 'mawiblah'); ?></th>
                    <td id="bg-total-subscribers"><?= esc_html($totalSubscribers); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p id="bg-status">
            <?php if ($campaign->campaignFinished): ?>
                <strong><?php esc_html_e('Send complete!', 'mawiblah'); ?></strong>
                <a class="btn" href="<?= esc_url($listUrl); ?>"><?php esc_html_e('Back to campaigns', 'mawiblah'); ?></a>
            <?php elseif ($campaign->backgroundStarted): ?>
                <span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span>
                <?php esc_html_e('Running…', 'mawiblah'); ?>
                <a class="btn btn-secondary" style="margin-left:10px;" href="<?= esc_url($stopUrl); ?>"
                   onclick="return confirm('<?php esc_attr_e('Stop background send?', 'mawiblah'); ?>');">
                    <?php esc_html_e('Stop', 'mawiblah'); ?>
                </a>
            <?php else: ?>
                <?php esc_html_e('Not running.', 'mawiblah'); ?>
                <a class="btn" href="<?= esc_url($listUrl); ?>"><?php esc_html_e('Back to campaigns', 'mawiblah'); ?></a>
            <?php endif; ?>
        </p>
    </div>

    <?php if (!$campaign->campaignFinished && $campaign->backgroundStarted): ?>
    <script>
    (function () {
        var interval = setInterval(function () {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', '<?= esc_url_raw(rest_url('mawiblah/v1/background-progress?campaignPostId=' . intval($campaignPostId) . '&_wpnonce=' . wp_create_nonce('wp_rest'))); ?>');
            xhr.setRequestHeader('X-WP-Nonce', '<?= esc_js(wp_create_nonce('wp_rest')); ?>');
            xhr.onload = function () {
                if (xhr.status === 200) {
                    var d = JSON.parse(xhr.responseText);
                    document.getElementById('bg-sent').textContent    = d.sent;
                    document.getElementById('bg-failed').textContent  = d.failed;
                    document.getElementById('bg-skipped').textContent = d.skipped;
                    document.getElementById('bg-unsubed').textContent = d.unsubed;
                    document.getElementById('bg-total').textContent   = d.total;
                    var elTotal = document.getElementById('bg-total-subscribers');
                    if (elTotal && d.total_subscribers > 0) { elTotal.textContent = d.total_subscribers; }
                    if (!d.running) {
                        clearInterval(interval);
                        document.getElementById('bg-status').innerHTML =
                            '<strong><?= esc_js(__('Send complete!', 'mawiblah')); ?></strong> ' +
                            '<a class="btn" href="<?= esc_url($listUrl); ?>"><?= esc_js(__('Back to campaigns', 'mawiblah')); ?></a>';
                    }
                }
            };
            xhr.send();
        }, 5000);
    }());
    </script>
    <?php endif; ?>
</div>
