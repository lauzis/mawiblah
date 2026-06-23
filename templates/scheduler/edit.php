<?php

use Mawiblah\Campaigns;
use Mawiblah\Helpers;

defined('ABSPATH') || exit;

$isEdit = isset($scheduler) && $scheduler->id;
?>
<div class="wrap mawiblah">

    <h1 class="wp-heading-inline">
        <?php echo $isEdit ? esc_html__('Edit Schedule', 'mawiblah') : esc_html__('Create Schedule', 'mawiblah'); ?>
    </h1>
    <hr class="wp-header-end">

    <a href="<?php echo esc_url(admin_url('admin.php?page=mawiblah-scheduler')); ?>" class="btn btn-secondary" style="margin-bottom:16px;display:inline-block;">
        &larr; <?php esc_html_e('Back to Schedules', 'mawiblah'); ?>
    </a>

    <form method="POST" action="<?php echo esc_url(Helpers::generatePluginUrl(['action' => 'save-scheduler'])); ?>">
        <?php wp_nonce_field('save-scheduler'); ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="schedulerId" value="<?php echo (int) $scheduler->id; ?>">
        <?php endif; ?>

        <table class="form-table">
            <tbody>

                <tr>
                    <th scope="row">
                        <label for="scheduler-name"><?php esc_html_e('Name', 'mawiblah'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="scheduler-name" name="name" class="regular-text"
                               value="<?php echo $isEdit ? esc_attr($scheduler->post_title) : ''; ?>"
                               required>
                        <p class="description"><?php esc_html_e('A descriptive name for this schedule (e.g. "Monthly Newsletter").', 'mawiblah'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="scheduler-campaign"><?php esc_html_e('Campaign', 'mawiblah'); ?></label>
                    </th>
                    <td>
                        <?php
                        $campaigns = Campaigns::getCampaigns();
                        $approvedCampaigns = array_filter($campaigns, fn($c) => !empty($c->testApproved));
                        ?>
                        <select id="scheduler-campaign" name="campaign_id" required>
                            <option value=""><?php esc_html_e('— Select a campaign —', 'mawiblah'); ?></option>
                            <?php foreach ($approvedCampaigns as $c): ?>
                                <option value="<?php echo (int) $c->id; ?>"
                                    <?php selected($isEdit && $scheduler->campaign_id === $c->id); ?>>
                                    <?php echo esc_html($c->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($approvedCampaigns)): ?>
                            <p class="description" style="color:#d63638;">
                                <?php esc_html_e('No approved campaigns found. A campaign must pass the test phase before it can be scheduled.', 'mawiblah'); ?>
                            </p>
                        <?php else: ?>
                            <p class="description"><?php esc_html_e('Only test-approved campaigns are listed.', 'mawiblah'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="scheduler-type"><?php esc_html_e('Schedule Type', 'mawiblah'); ?></label>
                    </th>
                    <td>
                        <select id="scheduler-type" name="schedule_type">
                            <option value="once"    <?php selected($isEdit && $scheduler->schedule_type === 'once');    ?>>
                                <?php esc_html_e('Once (send on a specific date)', 'mawiblah'); ?>
                            </option>
                            <option value="weekly"  <?php selected($isEdit && $scheduler->schedule_type === 'weekly');  ?>>
                                <?php esc_html_e('Weekly (same day each week)', 'mawiblah'); ?>
                            </option>
                            <option value="monthly" <?php selected($isEdit && $scheduler->schedule_type === 'monthly'); ?>>
                                <?php esc_html_e('Monthly (same day each month)', 'mawiblah'); ?>
                            </option>
                        </select>
                    </td>
                </tr>

                <tr id="row-send-date">
                    <th scope="row">
                        <label for="scheduler-send-date"><?php esc_html_e('Send Date', 'mawiblah'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="scheduler-send-date" name="send_date"
                               value="<?php echo $isEdit ? esc_attr($scheduler->send_date) : ''; ?>">
                        <p class="description"><?php esc_html_e('Date on which to send (for "Once" type only).', 'mawiblah'); ?></p>
                    </td>
                </tr>

                <tr id="row-send-day-weekly">
                    <th scope="row">
                        <label for="scheduler-day-week"><?php esc_html_e('Day of Week', 'mawiblah'); ?></label>
                    </th>
                    <td>
                        <select id="scheduler-day-week" name="send_day_weekly">
                            <?php
                            $weekDays = [
                                0 => __('Sunday', 'mawiblah'),
                                1 => __('Monday', 'mawiblah'),
                                2 => __('Tuesday', 'mawiblah'),
                                3 => __('Wednesday', 'mawiblah'),
                                4 => __('Thursday', 'mawiblah'),
                                5 => __('Friday', 'mawiblah'),
                                6 => __('Saturday', 'mawiblah'),
                            ];
                            $currentWeekDay = ($isEdit && $scheduler->schedule_type === 'weekly') ? $scheduler->send_day : 1;
                            foreach ($weekDays as $val => $label):
                            ?>
                                <option value="<?php echo (int) $val; ?>" <?php selected($currentWeekDay === $val); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr id="row-send-day-monthly">
                    <th scope="row">
                        <label for="scheduler-day-month"><?php esc_html_e('Day of Month', 'mawiblah'); ?></label>
                    </th>
                    <td>
                        <select id="scheduler-day-month" name="send_day_monthly">
                            <?php
                            $currentMonthDay = ($isEdit && $scheduler->schedule_type === 'monthly') ? $scheduler->send_day : 1;
                            for ($d = 1; $d <= 31; $d++):
                            ?>
                                <option value="<?php echo $d; ?>" <?php selected($currentMonthDay === $d); ?>>
                                    <?php echo $d; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <p class="description"><?php esc_html_e('If the month is shorter (e.g. February), the last available day is used.', 'mawiblah'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="scheduler-send-time"><?php esc_html_e('Send Time', 'mawiblah'); ?></label>
                    </th>
                    <td>
                        <input type="time" id="scheduler-send-time" name="send_time"
                               value="<?php echo $isEdit ? esc_attr($scheduler->send_time) : '09:00'; ?>">
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: timezone name */
                                esc_html__('Site timezone: %s', 'mawiblah'),
                                esc_html(wp_timezone_string())
                            );
                            ?>
                        </p>
                    </td>
                </tr>

                <tr id="row-end-date">
                    <th scope="row">
                        <label for="scheduler-end-date"><?php esc_html_e('End Date', 'mawiblah'); ?></label>
                    </th>
                    <td>
                        <input type="date" id="scheduler-end-date" name="end_date"
                               value="<?php echo $isEdit ? esc_attr($scheduler->end_date) : ''; ?>">
                        <p class="description"><?php esc_html_e('Optional. Leave empty to repeat forever. For recurring schedules only.', 'mawiblah'); ?></p>
                    </td>
                </tr>

            </tbody>
        </table>

        <p class="submit">
            <input type="submit" class="button button-primary"
                   value="<?php echo $isEdit ? esc_attr__('Update Schedule', 'mawiblah') : esc_attr__('Create Schedule', 'mawiblah'); ?>">
        </p>
    </form>

</div>

<script>
(function () {
    var typeSelect   = document.getElementById('scheduler-type');
    var rowDate      = document.getElementById('row-send-date');
    var rowWeekly    = document.getElementById('row-send-day-weekly');
    var rowMonthly   = document.getElementById('row-send-day-monthly');
    var rowEndDate   = document.getElementById('row-end-date');

    function toggleRows() {
        var type = typeSelect.value;
        rowDate.style.display    = (type === 'once')    ? '' : 'none';
        rowWeekly.style.display  = (type === 'weekly')  ? '' : 'none';
        rowMonthly.style.display = (type === 'monthly') ? '' : 'none';
        rowEndDate.style.display = (type !== 'once')    ? '' : 'none';
    }

    typeSelect.addEventListener('change', toggleRows);
    toggleRows();
}());
</script>
