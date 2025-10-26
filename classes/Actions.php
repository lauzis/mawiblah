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
            'gudlnenieks_stats_sent_out_emails_widget',
            esc_html__('Mawiblah - Campaign stats', 'mawiblah'),
            [self::class, 'renderDashboardWidget']
        );
    }

    public static function renderDashboardWidget()
    {
        $data = Campaigns::getDataForDashBoard(5);
        Templates::loadTemplate('campaign/bar-graph.php', $data);
    }
}
