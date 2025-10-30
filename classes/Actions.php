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
    }

    public static function renderDashboardWidget()
    {
        $data = Campaigns::getDataForDashBoard(3);
        unset($data['unsubscribed']);
        unset($data['skipped']);
        unset($data['newlyUnsubscribed']);
        unset($data['failed']);
        Templates::loadTemplate('campaign/bar-graph.php', $data);
    }
}
