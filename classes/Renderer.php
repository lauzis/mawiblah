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
                    $campaignId = $_GET['campaignId'];
                    $result = Campaigns::testApprove($campaignId);
                }
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
                exit;

            case 'campaign-send':
                if (isset($_GET['campaignId'])) {
                    $campaignId = $_GET['campaignId'];
                    $result = Campaigns::campaignStart($campaignId);
                    require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/email-list.php";
                }

                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
                exit;
            case 'send':
                print("this should be removed....");
                die();
                if (isset($_GET['campaignId'])) {
                    $startTime = time();
                    $maxTime = ini_get('max_execution_time');
                    $unsubedAudience = Subscribers::unsubedAudience();

                    $campaignId = $_GET['campaignId'];

                    $campaign = Campaigns::getCampaignById($campaignId);

                    if ($testMode) {
                        Logs::addLog("TEST MODE campaign started: {$campaign->post_title}", "Test campaign {$campaign->post_title} started", [
                                'campaign' => $campaign
                            ]
                        );
                    } else {
                        Logs::addLog("Campaign started: {$campaign->post_title}", "Campaign {$campaign->post_title} started",
                            [
                                'campaign' => $campaign
                            ]
                        );
                    }

                    $audiences = $campaign->audiences;

                    $template = Campaigns::lockTemplate($campaign, $testMode);

                    $counters = Campaigns::getCounters($campaign);
                    $emailsSent = $counters->emailsSend ?? 0;
                    $emailsFailed = $counters->emailsFailed ?? 0;
                    $emailsSkipped = $counters->emailsSkipped ?? 0;
                    $emailsUnsubed = $counters->emailsUnsubed ?? 0;

                    $skippingReasons = [
                        'unsubed' => [],
                        'notATester' => [],
                        'alreadySent' => [],
                        'notUniqueEmail' => [],
                        'skippingToPlaceWeLeftOf' => [],
                        'emailSendingIsDisabled' => [],
                        'donNotDisturb' => [],
                    ];

                    $iteration = get_post_meta($campaign->id, 'iteration', true);
                    $index = 0;
                    if (!$iteration) {
                        $iteration = 0;
                    }

                    $uniqueEmails = [];

                    foreach ($audiences as $id) {
                        if (substr_count($id, "GF__") > 0) {
                            $id = str_replace("GF__", "", $id);
                            $audienceName = GravityForms::getFormName($id) . " (Gravity Forms)";
                            $audience = Subscribers::getGFAudience($id, $audienceName);
                            $emails = GravityForms::getAllEmailsForForm($id);

                            foreach ($emails as $email => $info) {
                                $email = trim(strtolower($email));

                                if (isset($uniqueEmails[$email])) {
                                    $skippingReasons['notUniqueEmail'][] = $email;
                                    continue;
                                }
                                $uniqueEmails[$email] = true;

                                $index++;
                                //skipp to the place where we left off
                                if ($index < $iteration) {
                                    $skippingReasons['skippingToPlaceWeLeftOf'][] = $email;
                                    continue;
                                }
                                $subscriber = Subscribers::getSubscriber($email);
                                if (!$subscriber) {
                                    $subscriber = Subscribers::addSubscriber($email);
                                }

                                $currentTIme = time();
                                if ($currentTIme - $startTime > $maxTime - 2) {
                                    Logs::addLog("Campaign stopped {$campaign->post_title} as we are running out of time", "Campaign {$campaign->post_title} stopped",
                                        [
                                            'campaign' => $campaign,
                                            'audience' => $audience,
                                            'subscriber' => $subscriber,
                                            'uniqueEmails' => $uniqueEmails,
                                            'testMode' => $testMode,
                                            'skippingReasons' => $skippingReasons,
                                        ]
                                    );
                                    wp_redirect(Helpers::generatePluginUrl(['action' => 'list']));
                                    die();
                                }

                                if (is_array($subscriber->audiences) && count($subscriber->audiences) > 0) {
                                    $found = false;
                                    foreach ($subscriber->audiences as $audienceId) {
                                        if ($audienceId === $audience->term_id) {
                                            $found = true;
                                            continue;
                                        }
                                    }

                                    if (!$found) {
                                        Subscribers::addSubscriberToAudience($subscriber->ID, $audience->term_id);
                                    }
                                } else {
                                    Subscribers::addSubscriberToAudience($subscriber->ID, $audience->term_id);
                                }

                                if ($subscriber->unsubed) {
                                    $emailsUnsubed++;
                                    $skippingReasons['unsubed'][] = $email;
                                    continue;
                                }

                                if (Subscribers::checkMailchimpUnsubedAudience($email)) {
                                    Subscribers::unsub($email, false, 'In mailchimp audience was unsubed already');
                                    Subscribers::addSubscriberToAudience($subscriber->id, $unsubedAudience->term_id);
                                    $skippingReasons['unsubed'][] = $email;
                                    $emailsUnsubed++;
                                    continue;
                                }

                                $emailIsSent = Subscribers::isEmailSent($subscriber->id, $campaign->id);
                                $isTester = Subscribers::isTester($subscriber);

                                if ($testMode && !$isTester) {
                                    $skippingReasons['notATester'][] = $email;
                                    $emailsSkipped++;
                                    continue;
                                }

                                if ($emailIsSent && !$testMode && !$isTester) {
                                    $skippingReasons['alreadySent'][] = $email;
                                    $emailsSkipped++;
                                    continue;
                                }

                                $lastInteraction = $subscriber->lastInteraction;
                                $doNotDisturbThreshold = Settings::dontDisturbThreshold();
                                // current tiem did not go over the reshold
                                if (time() < strtotime($lastInteraction) + $doNotDisturbThreshold) {
                                    $skippingReasons['donNotDisturb'][] = $email;

                                    if (!$testMode && !$isTester){
                                        continue;
                                    }
                                }

                                Campaigns::updateCounters($campaign, $emailsSent, $emailsFailed, $emailsSkipped, $emailsUnsubed);

                                update_post_meta($campaign->id, 'iteration', $index);

                            }
                        }
                    }

                    if ($testMode) {
                        update_post_meta($campaign->id, 'iteration', 0);
                        Campaigns::updateCounters($campaign, 0, 0, 0, 0);
                    }

                    if (!$testMode) {
                        Campaigns::finished($campaign->id);
                    }
                    Logs::addLog("Campaign finished: {$campaign->post_title}", "Campaign {$campaign->post_title} finished", [
                        'campaign' => $campaign,
                        'audience' => $audience,
                        'subscriber' => $subscriber,
                        'uniqueEmails' => $uniqueEmails,
                        'testMode' => $testMode,
                        'skippingReasons' => $skippingReasons
                    ]);
                }
                require MAWIBLAH_PLUGIN_DIR . "/templates/campaign/list.php";
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
