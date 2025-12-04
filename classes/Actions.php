<?php

namespace Mawiblah;

use Mawiblah\Campaigns;
use Mawiblah\Templates;

class Actions
{

    public static function init()
    {
        add_action('wp_dashboard_setup', [self::class, 'registerDashboardWidget']);
    }

    public static function registerDashboardWidget()
    {
        wp_add_dashboard_widget(
            'mawiblah_dashboard_widget',
            esc_html__('Mawiblah - Campaign stats', 'mawiblah'),
            [self::class, 'renderDashboardWidget']
        );

        wp_add_dashboard_widget(
            'mawiblah_activity_rating_widget',
            esc_html__('Mawiblah - Activity Rating', 'mawiblah'),
            [self::class, 'renderActivityRatingWidget']
        );
    }

    public static function renderDashboardWidget()
    {
        $data = Campaigns::getDataForDashBoard(3);

        $dataForDisplay = [
            __('Emails sent', 'mawiblah') => $data[Campaigns::STAT_SENT],
            __('Unique visitors', 'mawiblah') => $data[Campaigns::STAT_UNIQUE_USERS],
            __('Links opened', 'mawiblah') => $data[Campaigns::STAT_LINKS_CLICKED],
        ];
        Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
    }

    public static function renderActivityRatingWidget()
    {
        $activeDays = Campaigns::getClickTimesByDayOfWeekForLastCampaigns(12);
        $startDays = Campaigns::getCampaignStartTimesByDayOfWeek(12);
        $dataForBarGraph = [];
        $dataForBarGraph[__('Weekdays', 'mawiblah')] = [];

        foreach ($activeDays as $day => $count) {
            $startCount = $startDays[$day] ?? 0;
            $rating = $startCount > 0 ? round($count / $startCount, 2) : 0;

            $dataForBarGraph[__('Weekdays', 'mawiblah')][] = $rating;
        }

        Templates::loadTemplate('campaign/bar-graph.php', $dataForBarGraph);
    }
}
