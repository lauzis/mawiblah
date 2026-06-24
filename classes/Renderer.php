<?php

namespace Mawiblah;
class Renderer
{
    /**
     * Renders the main plugin dashboard page and exits.
     *
     * @param array|null $debug Optional debug data passed to the template.
     */
    public static function start(array|null $debug = null)
    {
        include(MAWIBLAH_PLUGIN_DIR . "/templates/start.php");
        exit;
    }

    /**
     * Renders the "campaign already exists" notice partial.
     *
     * @param array|null $debug Optional debug data passed to the template.
     */
    public static function campaign_already_exists(array|null $debug = null)
    {
        include_once MAWIBLAH_PLUGIN_DIR . "/templates/campaign/already-exists.php";
    }

    /**
     * Renders the "campaign validation failed" notice partial.
     *
     * @param array|null $debug Optional debug data passed to the template.
     */
    public static function campaign_invalid(array|null $debug = null)
    {
        include_once MAWIBLAH_PLUGIN_DIR . "/templates/campaign/could-not-create.php";
    }

    /**
     * Renders the "campaign created successfully" notice partial.
     *
     * @param array|null $debug Optional debug data passed to the template.
     */
    public static function campaign_created(array|null $debug = null)
    {
        include_once MAWIBLAH_PLUGIN_DIR . "/templates/campaign/created.php";
    }

    /**
     * Renders the "campaign could not be created" error partial.
     *
     * @param array|null $debug Optional debug data passed to the template.
     */
    public static function campaign_could_not_create(array|null $debug = null)
    {
        include_once MAWIBLAH_PLUGIN_DIR . "/templates/campaign/could-not-create.php";
    }

    /**
     * Renders the campaigns admin page, dispatching to the correct sub-template based on the
     * 'action' query parameter (list, create, edit, save, delete, test, approve, send, reset).
     *
     * Nonce and capability checks are applied to all write actions.
     *
     * @param array|null $debug Optional debug data passed to the template.
     */
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

                    $campaign = Campaigns::getCampaignById($campaignPostId);
                    if (!$campaign || !$campaign->testApproved) {
                        wp_die(__('Campaign must be approved before sending.', 'mawiblah'), 403);
                    }

                    $result = Campaigns::campaignStart($campaignPostId);

                    require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/email-list.php";
                }
                break;

            case 'campaign-send-background':
                if (isset($_GET['campaignPostId'])) {
                    $campaignPostId = intval($_GET['campaignPostId'] ?? 0);

                    if ($campaignPostId > 0 && !current_user_can('edit_post', $campaignPostId)) {
                        wp_die(__('You are not allowed to do this.', 'mawiblah'), 403);
                    }
                    check_admin_referer('campaign-send-background_' . $campaignPostId);

                    $campaign = Campaigns::getCampaignById($campaignPostId);
                    if (!$campaign || !$campaign->testApproved) {
                        wp_die(__('Campaign must be approved before sending.', 'mawiblah'), 403);
                    }

                    Campaigns::backgroundSendStart($campaignPostId);
                    CronSend::schedule($campaignPostId);

                    require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/background-progress.php";
                }
                break;

            case 'campaign-stop-background':
                if (isset($_GET['campaignPostId'])) {
                    $campaignPostId = intval($_GET['campaignPostId'] ?? 0);

                    if ($campaignPostId > 0 && !current_user_can('edit_post', $campaignPostId)) {
                        wp_die(__('You are not allowed to do this.', 'mawiblah'), 403);
                    }
                    check_admin_referer('campaign-stop-background_' . $campaignPostId);

                    CronSend::unschedule($campaignPostId);
                    Campaigns::backgroundSendStop($campaignPostId);

                    wp_safe_redirect(admin_url('admin.php?page=' . Init::MAWIBLAH_CAMPAIGNS));
                    exit;
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

            case 'campaign-duplicate':
                if (isset($_GET['campaignPostId'])) {
                    $campaignPostId = intval($_GET['campaignPostId']);

                    if ($campaignPostId > 0) {
                        if (!current_user_can('edit_posts')) {
                            wp_die(__('You are not allowed to do this.', 'mawiblah'), 403);
                        }
                        check_admin_referer('campaign-duplicate_' . $campaignPostId);

                        Campaigns::duplicateCampaign($campaignPostId);
                    }
                }
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
                break;

            default:
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
        }
    }

    /** Renders the test scenarios page and exits. */
    public static function tests()
    {
        require MAWIBLAH_PLUGIN_DIR . "/templates/tests.php";
        exit;
    }

    /** Renders the plugin settings page and exits. */
    public static function settings()
    {
        require MAWIBLAH_PLUGIN_DIR . "/templates/settings.php";
        exit;
    }

    /** Renders the actions/tools admin page and exits. */
    public static function actions()
    {
        require MAWIBLAH_PLUGIN_DIR . "/templates/actions.php";
        exit;
    }

    /** Renders the CSV import page. */
    public static function import()
    {
        require MAWIBLAH_PLUGIN_DIR . "/templates/import.php";
    }

    /**
     * Renders the campaign scheduler admin page, dispatching based on the 'action' query parameter.
     *
     * Actions: list (default), create-scheduler, scheduler-edit, save-scheduler,
     *          scheduler-delete, scheduler-pause, scheduler-activate.
     *
     * @param array|null $debug Optional debug data.
     */
    public static function scheduler(array|null $debug = null): void
    {
        $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';

        switch ($action) {
            case 'create-scheduler':
                require MAWIBLAH_PLUGIN_DIR . '/templates/scheduler/edit.php';
                break;

            case 'scheduler-edit':
                if (isset($_GET['schedulerId'])) {
                    $schedulerId = (int) $_GET['schedulerId'];
                    $scheduler   = Scheduler::getById($schedulerId);
                }
                require MAWIBLAH_PLUGIN_DIR . '/templates/scheduler/edit.php';
                break;

            case 'save-scheduler':
                if (Helpers::canEdit()) {
                    check_admin_referer('save-scheduler');

                    $schedulerId  = isset($_POST['schedulerId']) ? (int) $_POST['schedulerId'] : null;
                    $name         = sanitize_text_field($_POST['name'] ?? '');
                    $campaignId   = (int) ($_POST['campaign_id'] ?? 0);
                    $scheduleType = sanitize_key($_POST['schedule_type'] ?? 'once');
                    $sendTime     = sanitize_text_field($_POST['send_time'] ?? '09:00');
                    $sendDate     = sanitize_text_field($_POST['send_date'] ?? '');
                    $endDate      = sanitize_text_field($_POST['end_date'] ?? '');

                    // Resolve the correct send_day depending on schedule type
                    if ($scheduleType === 'weekly') {
                        $sendDay = (int) ($_POST['send_day_weekly'] ?? 1);
                    } elseif ($scheduleType === 'monthly') {
                        $sendDay = (int) ($_POST['send_day_monthly'] ?? 1);
                    } else {
                        $sendDay = 1;
                    }

                    if ($name && $campaignId) {
                        if ($schedulerId) {
                            Scheduler::update($schedulerId, $name, $campaignId, $scheduleType, $sendTime, $sendDay, $sendDate, $endDate);
                        } else {
                            Scheduler::add($name, $campaignId, $scheduleType, $sendTime, $sendDay, $sendDate, $endDate);
                        }
                    }
                }
                require MAWIBLAH_PLUGIN_DIR . '/templates/scheduler/list.php';
                break;

            case 'scheduler-delete':
                if (isset($_GET['schedulerId'])) {
                    $schedulerId = (int) $_GET['schedulerId'];
                    if ($schedulerId > 0) {
                        check_admin_referer('scheduler-delete_' . $schedulerId);
                        Scheduler::delete($schedulerId);
                    }
                }
                require MAWIBLAH_PLUGIN_DIR . '/templates/scheduler/list.php';
                break;

            case 'scheduler-pause':
                if (isset($_GET['schedulerId'])) {
                    $schedulerId = (int) $_GET['schedulerId'];
                    if ($schedulerId > 0) {
                        check_admin_referer('scheduler-pause_' . $schedulerId);
                        Scheduler::updateMeta($schedulerId, ['status' => 'paused']);
                    }
                }
                require MAWIBLAH_PLUGIN_DIR . '/templates/scheduler/list.php';
                break;

            case 'scheduler-activate':
                if (isset($_GET['schedulerId'])) {
                    $schedulerId = (int) $_GET['schedulerId'];
                    if ($schedulerId > 0) {
                        check_admin_referer('scheduler-activate_' . $schedulerId);
                        $scheduler = Scheduler::getById($schedulerId);
                        if ($scheduler) {
                            $nextSend = Scheduler::computeNextSend(
                                $scheduler->schedule_type,
                                $scheduler->send_time,
                                $scheduler->send_day,
                                $scheduler->send_date
                            );
                            Scheduler::updateMeta($schedulerId, [
                                'status'    => 'active',
                                'next_send' => $nextSend,
                            ]);
                        }
                    }
                }
                require MAWIBLAH_PLUGIN_DIR . '/templates/scheduler/list.php';
                break;

            default:
                require MAWIBLAH_PLUGIN_DIR . '/templates/scheduler/list.php';
        }
    }

    /** Renders the in-plugin help page and exits. */
    public static function help()
    {
        require MAWIBLAH_PLUGIN_DIR . "/templates/help.php";
        exit;
    }

    /** Renders the log viewer page and exits. */
    public static function logs()
    {
        require MAWIBLAH_PLUGIN_DIR . "/templates/logs.php";
        exit;
    }
}
