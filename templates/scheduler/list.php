<?php

use Mawiblah\Helpers;
use Mawiblah\Scheduler;
use Mawiblah\SchedulerCron;
use Mawiblah\Campaigns;

defined('ABSPATH') || exit;

?>
<div class="wrap mawiblah">

    <h1 class="wp-heading-inline"><?php esc_html_e('Scheduler', 'mawiblah'); ?></h1>
    <hr class="wp-header-end">

    <?php
    $cronNext = wp_next_scheduled(SchedulerCron::HOOK);
    $tz       = wp_timezone();
    if ($cronNext) {
        $cronNextStr = wp_date('Y-m-d H:i:s', $cronNext, $tz);
        $diffMin     = round(($cronNext - time()) / 60, 1);
        echo '<div class="notice notice-info inline" style="margin:8px 0 16px;"><p>';
        printf(
            /* translators: 1: next fire datetime, 2: minutes until next fire */
            esc_html__('Scheduler cron check next fires at %1$s (in %2$s min).', 'mawiblah'),
            '<strong>' . esc_html($cronNextStr) . '</strong>',
            esc_html($diffMin)
        );
        echo '</p></div>';
    } else {
        echo '<div class="notice notice-warning inline" style="margin:8px 0 16px;"><p>';
        esc_html_e('Scheduler cron check is NOT scheduled in WP Cron. The scheduler will not fire automatically.', 'mawiblah');
        echo '</p></div>';
    }
    ?>

    <div class="btn-row">
        <a class="btn" href="<?php echo esc_url(Helpers::generatePluginUrl(['action' => 'create-scheduler'])); ?>">
            <?php esc_html_e('Create new schedule', 'mawiblah'); ?>
        </a>
    </div>

    <?php $schedulers = Scheduler::getAll(); ?>

    <?php if (empty($schedulers)): ?>
        <p><?php esc_html_e('No schedules yet. Create one to automate campaign sends.', 'mawiblah'); ?></p>
    <?php else: ?>
    <table class="wp-list-table widefat striped table-view-list">
        <thead>
            <tr>
                <th><?php esc_html_e('Name', 'mawiblah'); ?></th>
                <th><?php esc_html_e('Campaign', 'mawiblah'); ?></th>
                <th><?php esc_html_e('Type', 'mawiblah'); ?></th>
                <th><?php esc_html_e('Schedule', 'mawiblah'); ?></th>
                <th><?php esc_html_e('Next Send', 'mawiblah'); ?></th>
                <th><?php esc_html_e('Last Sent', 'mawiblah'); ?></th>
                <th><?php esc_html_e('End Date', 'mawiblah'); ?></th>
                <th><?php esc_html_e('Status', 'mawiblah'); ?></th>
                <th colspan="3"><?php esc_html_e('Actions', 'mawiblah'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($schedulers as $scheduler): ?>
            <?php
            $campaign = $scheduler->campaign_id ? Campaigns::getCampaignById($scheduler->campaign_id) : null;
            $campaignName = $campaign ? esc_html($campaign->post_title) : '—';

            $typeLabels = [
                'once'    => __('Once', 'mawiblah'),
                'weekly'  => __('Weekly', 'mawiblah'),
                'monthly' => __('Monthly', 'mawiblah'),
            ];
            $typeLabel = $typeLabels[$scheduler->schedule_type] ?? $scheduler->schedule_type;

            $dayNames = [
                0 => __('Sunday', 'mawiblah'),
                1 => __('Monday', 'mawiblah'),
                2 => __('Tuesday', 'mawiblah'),
                3 => __('Wednesday', 'mawiblah'),
                4 => __('Thursday', 'mawiblah'),
                5 => __('Friday', 'mawiblah'),
                6 => __('Saturday', 'mawiblah'),
            ];

            if ($scheduler->schedule_type === 'once') {
                $scheduleDesc = esc_html($scheduler->send_date) . ' ' . esc_html($scheduler->send_time);
            } elseif ($scheduler->schedule_type === 'weekly') {
                $dayName     = $dayNames[$scheduler->send_day] ?? $scheduler->send_day;
                $scheduleDesc = sprintf(
                    /* translators: 1: day name, 2: time */
                    __('Every %1$s at %2$s', 'mawiblah'),
                    esc_html($dayName),
                    esc_html($scheduler->send_time)
                );
            } else {
                $scheduleDesc = sprintf(
                    /* translators: 1: day number, 2: time */
                    __('Day %1$d of each month at %2$s', 'mawiblah'),
                    $scheduler->send_day,
                    esc_html($scheduler->send_time)
                );
            }

            $tz          = wp_timezone();
            $nextSendStr = $scheduler->next_send && $scheduler->status === 'active'
                ? wp_date('Y-m-d H:i', $scheduler->next_send, $tz)
                : '—';
            $lastSentStr = $scheduler->last_sent
                ? wp_date('Y-m-d H:i', $scheduler->last_sent, $tz)
                : '—';

            $statusClass = [
                'active'    => 'notice-success',
                'paused'    => 'notice-warning',
                'completed' => '',
            ][$scheduler->status] ?? '';
            ?>
            <tr>
                <td><strong><?php echo esc_html($scheduler->post_title); ?></strong></td>
                <td><?php echo $campaignName; ?></td>
                <td><?php echo esc_html($typeLabel); ?></td>
                <td><?php echo esc_html($scheduleDesc); ?></td>
                <td><?php echo esc_html($nextSendStr); ?></td>
                <td><?php echo esc_html($lastSentStr); ?></td>
                <td><?php echo $scheduler->end_date ? esc_html($scheduler->end_date) : '—'; ?></td>
                <td>
                    <span style="font-weight:600;">
                        <?php echo esc_html(ucfirst($scheduler->status)); ?>
                    </span>
                </td>
                <td>
                    <a class="btn" href="<?php echo esc_url(Helpers::generatePluginUrl(['action' => 'scheduler-edit', 'schedulerId' => $scheduler->id])); ?>">
                        <?php esc_html_e('Edit', 'mawiblah'); ?>
                    </a>
                </td>
                <td>
                    <?php if ($scheduler->status === 'active'): ?>
                        <a class="btn btn-secondary" href="<?php echo esc_url(Helpers::generatePluginUrl(['action' => 'scheduler-pause', 'schedulerId' => $scheduler->id], 'schedulerId')); ?>">
                            <?php esc_html_e('Pause', 'mawiblah'); ?>
                        </a>
                    <?php elseif ($scheduler->status === 'paused'): ?>
                        <a class="btn btn-secondary" href="<?php echo esc_url(Helpers::generatePluginUrl(['action' => 'scheduler-activate', 'schedulerId' => $scheduler->id], 'schedulerId')); ?>">
                            <?php esc_html_e('Activate', 'mawiblah'); ?>
                        </a>
                    <?php else: ?>
                        <span class="btn btn-secondary disabled"><?php esc_html_e('Completed', 'mawiblah'); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <a class="btn btn-warning link-delete" href="<?php echo esc_url(Helpers::generatePluginUrl(['action' => 'scheduler-delete', 'schedulerId' => $scheduler->id], 'schedulerId')); ?>"
                       onclick="return confirm('<?php echo esc_js(__('Delete this schedule?', 'mawiblah')); ?>')">
                        <?php esc_html_e('Delete', 'mawiblah'); ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

</div>
