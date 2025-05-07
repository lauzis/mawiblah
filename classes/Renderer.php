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
    }

    public static function campaign_invalid(array|null $debug = null)
    {
        include_once MAWIBLAH_PLUGIN_DIR . "/templates/campaign/could-not-create.php";
    }

    public static function campaign_created(array|null $debug = null)
    {
        include_once MAWIBLAH_PLUGIN_DIR . "/templates/campaign/created.php";
    }

    public static function campaign_could_not_create(array|null $debug = null)
    {
        include_once MAWIBLAH_PLUGIN_DIR . "/templates/campaign/could-not-create.php";
    }

    public static function campaigns(array|null $debug = null)
    {

        $testMode = false;
        $action = $_GET['action'] ?? 'list';

        switch ($action) {
            case 'test':
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/email-list.php";
                break;
            case 'campaign-test-reset':
                if (isset($_GET['campaignId'])) {
                    $campaignId = $_GET['campaignId'];
                    $result = Campaigns::testReset($campaignId);
                }
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
                break;
            case 'campaign-test-approve':
                if (isset($_GET['campaignId'])) {
                    $campaignId = intval($_GET['campaignId'] ?? 0);

                    if ($campaignId > 0 && !current_user_can('edit_post', $campaignId)) {
                        wp_die(__('You are not allowed to do this.', 'mawiblah'), 403);
                    }
                    check_admin_referer('campaign-test-approve_' . $campaignId);

                    $result = Campaigns::testApprove($campaignId);
                }
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
                break;

            case 'campaign-send':
                if (isset($_GET['campaignId'])) {
                    $campaignId = intval($_GET['campaignId'] ?? 0);

                    if ($campaignId > 0 && !current_user_can('edit_post', $campaignId)) {
                        wp_die(__('You are not allowed to do this.', 'mawiblah'), 403);
                    }
                    check_admin_referer('campaign-send_' . $campaignId);

                    $result = Campaigns::campaignStart($campaignId);

                    require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/email-list.php";
                }
                break;

            case 'save-campaign':
                $debug = [];
                $debug['post'] = $_POST;
                $debug['valid'] = false;
                $debug['unique'] = false;
                $debug['campaignId'] = null;
                $debug['existingCampaign'] = null;

                if (Helpers::canEdit()) {
                    check_admin_referer('save-campaign');

                    $title = $_POST['title'] ?? "";
                    $subject = $_POST['subject'] ?? "";
                    $contentTitle = $_POST['contentTitle'] ?? "";
                    $content = $_POST['content'] ?? "";
                    $audiences = $_POST['audiences'] ?? [];
                    $template = $_POST['template'] ?? "";

                    if (Campaigns::validateCampaign($title, $subject, $audiences, $template)) {
                        $debug['valid'] = true;
                        if (Campaigns::isUnique($title)) {
                            $debug['unique'] = true;
                            $campaignId = Campaigns::addCampaign(title: $title, subject: $subject, contentTitle: $contentTitle, content: $content, audiences: $audiences, template: $template);

                            $debug['campaignId'] = $campaignId;
                            if ($campaignId) {
                                Renderer::campaign_created($debug);
                            } else {
                                Renderer::campaign_could_not_create($debug);
                            }
                        } else {
                            $debug['existingCampaign'] = Campaigns::getCampaign($title);
                            Renderer::campaign_already_exists($debug);
                        }
                    } else {
                        Renderer::campaign_invalid($debug);
                    }
                }
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
                break;

            case 'create-campaign':
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/add-campaign.php";
                break;

            case 'campaign-edit':
                if (isset($_GET['campaignId'])) {
                    $campaignId = $_GET['campaignId'];
                    $campaign = Campaigns::getCampaignById($campaignId);

                    require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/add-campaign.php";
                }
                break;

            case 'campaign-delete':
                if (isset($_GET['campaignId'])) {
                    $campaignId = $_GET['campaignId'];
                    $result = Campaigns::deleteCampaign($campaignId);
                }
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
                break;

            default:
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
        }
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
