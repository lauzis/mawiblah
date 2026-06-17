<?php

namespace Mawiblah;

use Mawiblah\Campaigns;
use Mawiblah\Templates;

class Actions
{

    /** Registers WordPress dashboard widgets via the wp_dashboard_setup action. */
    public static function init()
    {
        add_action('wp_dashboard_setup', [self::class, 'registerDashboardWidget']);
    }

    /** Registers the campaign stats and activity rating widgets on the WordPress dashboard. */
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

    /** Renders the campaign stats bar chart (sent, unique visitors, links opened) for the last 3 campaigns. */
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

    /**
     * Renders the activity rating bar chart showing clicks-per-campaign-send-day ratio by weekday.
     *
     * A rating above 1 means subscribers click more on that day than campaigns are sent on it,
     * indicating an optimal send day.
     */
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
