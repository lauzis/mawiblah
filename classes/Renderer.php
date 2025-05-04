<?php

namespace Mawiblah;
class Renderer
{
    public static function start(array|null $debug = null)
    {
        include(MAWIBLAH_PLUGIN_DIR . "/templates/start.php");
        exit;
    }

    public static function campaign_already_exists(array|null $debug = null)
    {
        include_once MAWIBLAH_PLUGIN_DIR . "/templates/campaign/already-exists.php";
        exit;
    }

    public static function campaign_invalid(array|null $debug = null)
    {
        include_once MAWIBLAH_PLUGIN_DIR . "/templates/campaign/could-not-create.php";
        exit;
    }

    public static function campaign_created(array|null $debug = null)
    {
        include_once MAWIBLAH_PLUGIN_DIR . "/templates/campaign/created.php";
        exit;
    }

    public static function campaign_could_not_create(array|null $debug = null)
    {
        include_once MAWIBLAH_PLUGIN_DIR . "/templates/campaign/could-not-create.php";
        exit;
    }

    public static function campaigns(array|null $debug = null)
    {

        $testMode = false;
        $action = $_GET['action'] ?? 'list';

        switch ($action) {
            case 'test':
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/email-list.php";
                exit;
            case 'campaign-test-reset':
                if (isset($_GET['campaignId'])) {
                    $campaignId = $_GET['campaignId'];
                    $result = Campaigns::testReset($campaignId);
                }
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
                exit;
            case 'campaign-test-approve':
                if (isset($_GET['campaignId'])) {
                    $campaignId = intval( $_GET['campaignId'] ?? 0 );

                    if ($campaignId > 0 && ! current_user_can( 'edit_post', $campaignId ) ) {
                        wp_die( __( 'You are not allowed to do this.', 'mawiblah' ), 403 );
                    }
                    check_admin_referer( 'mawiblah_campaign_action_' . $campaignId );

                    $result = Campaigns::testApprove($campaignId);
                }
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
                exit;

            case 'campaign-send':
                if (isset($_GET['campaignId'])) {
                    $campaignId = intval( $_GET['campaignId'] ?? 0 );

                    if ($campaignId > 0 && ! current_user_can( 'edit_post', $campaignId ) ) {
                        wp_die( __( 'You are not allowed to do this.', 'mawiblah' ), 403 );
                    }
                    check_admin_referer( 'mawiblah_campaign_action_' . $campaignId );

                    $result = Campaigns::campaignStart($campaignId);

                    require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/email-list.php";
                }
                exit;

            case 'create':
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/add-campaign.php";
                break;

            case 'edit':
                if (isset($_GET['campaignId'])) {
                    $campaignId = $_GET['campaignId'];
                    $campaign = Campaigns::getCampaignById($campaignId);

                    require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/add-campaign.php";
                }
                break;

            case 'delete':
                if (isset($_GET['campaignId'])) {
                    $campaignId = $_GET['campaignId'];
                    $result = Campaigns::deleteCampaign($campaignId);
                }
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
                exit;
            default:
        }
        require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
        exit;
    }

    public static function tests()
    {
        require MAWIBLAH_PLUGIN_DIR . "/templates/tests.php";
        exit;
    }

    public static function settings()
    {
        require MAWIBLAH_PLUGIN_DIR . "/templates/settings.php";
        exit;
    }

    public static function actions()
    {
        require MAWIBLAH_PLUGIN_DIR . "/templates/actions.php";
        exit;
    }
}
