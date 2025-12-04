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
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';

        switch ($action) {
            case 'test':
                $testMode = true;
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/email-list.php";
                break;
            case 'campaign-test-reset':
                if (isset($_GET['campaignPostId'])) {
                    $campaignPostId = intval($_GET['campaignPostId']);

                    if ($campaignPostId > 0) {
                        if (!current_user_can('edit_post', $campaignPostId)) {
                            wp_die(__('You are not allowed to do this.', 'mawiblah'), 403);
                        }
                        check_admin_referer('campaign-test-reset_' . $campaignPostId);

                        Campaigns::testReset($campaignPostId);
                    }
                }
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
                break;
            case 'campaign-test-approve':
                if (isset($_GET['campaignPostId'])) {
                    $campaignPostId = intval($_GET['campaignPostId'] ?? 0);

                    if ($campaignPostId > 0 && !current_user_can('edit_post', $campaignPostId)) {
                        wp_die(__('You are not allowed to do this.', 'mawiblah'), 403);
                    }
                    check_admin_referer('campaign-test-approve_' . $campaignPostId);

                    Campaigns::testApprove($campaignPostId);
                }
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
                break;

            case 'campaign-send':
                if (isset($_GET['campaignPostId'])) {
                    $campaignPostId = intval($_GET['campaignPostId'] ?? 0);

                    if ($campaignPostId > 0 && !current_user_can('edit_post', $campaignPostId)) {
                        wp_die(__('You are not allowed to do this.', 'mawiblah'), 403);
                    }
                    check_admin_referer('campaign-send_' . $campaignPostId);

                    $result = Campaigns::campaignStart($campaignPostId);

                    require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/email-list.php";
                }
                break;

            case 'save-campaign':
                $debug = [];
                $debug['post'] = $_POST;
                $debug['valid'] = false;
                $debug['unique'] = false;
                $debug['campaignPostId'] = null;
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

                        $campaignPostId = isset($_POST['campaignPostId']) ? intval($_POST['campaignPostId']) : null;
                        $campaignWithTitle = Campaigns::getCampaign($title);
                        $isUnique = !$campaignWithTitle || ($campaignPostId && $campaignWithTitle->id == $campaignPostId);

                        if ($isUnique) {
                            $debug['unique'] = true;
                            if ($campaignPostId) {
                                Campaigns::updateCampaign($campaignPostId, $title, $subject, $contentTitle, $content, $audiences, $template);
                            } else {
                                $campaignPostId = Campaigns::addCampaign(title: $title, subject: $subject, contentTitle: $contentTitle, content: $content, audiences: $audiences, template: $template);
                            }

                            $debug['campaignPostId'] = $campaignPostId;
                            if ($campaignPostId) {
                                Renderer::campaign_created($debug);
                            } else {
                                Renderer::campaign_could_not_create($debug);
                            }
                        } else {
                            $debug['existingCampaign'] = $campaignWithTitle;
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
                if (isset($_GET['campaignPostId'])) {
                    $campaignPostId = intval($_GET['campaignPostId']);
                    $campaign = Campaigns::getCampaignById($campaignPostId);

                    require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/add-campaign.php";
                }
                break;

            case 'campaign-delete':
                if (isset($_GET['campaignPostId'])) {
                    $campaignPostId = intval($_GET['campaignPostId']);

                    if ($campaignPostId > 0) {
                        if (!current_user_can('edit_post', $campaignPostId)) {
                            wp_die(__('You are not allowed to do this.', 'mawiblah'), 403);
                        }
                        check_admin_referer('campaign-delete_' . $campaignPostId);

                        Campaigns::deleteCampaign($campaignPostId);
                    }
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
