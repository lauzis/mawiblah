<div class="wrap mawiblah">
    <?php

    use Mawiblah\Campaigns;
    use Mawiblah\GravityForms;
    use Mawiblah\Helpers;
    use Mawiblah\Subscribers;
    use Mawiblah\Settings;

    if (isset($_GET['campaignId'])) {

        $sleepBeforeJob = Settings::getOption('mawiblah-time-between-emails');
        if (!is_numeric($sleepBeforeJob) || $sleepBeforeJob < 0) {
            $sleepBeforeJob = 0;
        }

        $startTime = time();
        $maxTime = ini_get('max_execution_time');
        $unsubedAudience = Subscribers::unsubedAudience();

        $campaignId = intval($_GET['campaignId'] ?? 0);
        if ($campaignId <= 0) {
            wp_die(__('Invalid campaign ID', 'mawiblah'));
        }

        $campaign = Campaigns::getCampaignById($campaignId);

        if ($campaign->testFinished && !$campaign->campaignStarted) {
            ?>
            <div class="alert alert-danger">
                <strong>Test finished</strong>
                <p>Test was already finished. You cannot test it again.</p>
                <div class="flex flex-row space-between">
                    <a class="btn btn-secondary" href="<?= Helpers::campaignTestResetUrl($campaignId) ?>"
                       class="btn btn-primary">Retest</a>
                    <?php if (!$campaign->testApproved) : ?>
                        <a class="btn btn-primary" href="<?= Helpers::campaignTestApproveUrl($campaignId) ?>"
                           class="btn btn-primary">Approve</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            exit;
        }
        if ($campaign->campaignFinished) {
            ?>
            <div class="alert alert-danger">
                <strong>Campaign finished</strong>
            </div>
            <?php
            exit;
        }

        Campaigns::testStart($campaignId);

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

        ?>
            <div class="progress-wrap">
                <div class="progress" style="width:100%" data-sleep-before-job="<?= $sleepBeforeJob ?>">
                    <div class="progress-bar"
                         style="background-color: #0a4b78; color:#FFF; text-align: center; padding:4px; text-wrap: nowrap">
                    </div>
                </div>
            </div>

        <?php

        foreach ($audiences as $id) {
            if (substr_count($id, "GF__") > 0) {
                $id = str_replace("GF__", "", $id);
                $audienceName = GravityForms::getFormName($id) . " (Gravity Forms)";
                $audience = Subscribers::getGFAudience($id, $audienceName);
                $emails = GravityForms::getAllEmailsForForm($id);

                ?>

                <table class="mawiblah-email-list wp-list-table widefat striped table-view-list">
                    <thead>
                    <tr>
                        <th colspan="4">Audience: <?= $audienceName ?></th>
                    </tr>

                    <tr>
                        <th>Email</th>
                        <th>First interaction</th>
                        <th>Last interaction</th>
                        <th>Status</th>
                    </tr>

                    <?php
                    foreach ($emails as $email => $info) {
                        $email = trim(strtolower($email));

                        if (isset($uniqueEmails[$email])) {
                            $skippingReasons['notUniqueEmail'][] = $email;
                            continue;
                        }
                        $uniqueEmails[$email] = true;

                        $index++;

                        $subscriber = Subscribers::getSubscriber($email);
                        if (!$subscriber) {
                            $subscriber = Subscribers::addSubscriber($email);
                        }

                        $subscriberId = $subscriber->id;

                        ?>
                        <tr>
                            <td><?= $subscriber->email; ?></td>
                            <td><?= esc_html( date_i18n( 'Y-m-d H:i:s', $subscriber->firstInteraction ) ); ?></td>
                            <td><?= esc_html( date_i18n( 'Y-m-d H:i:s', $subscriber->lastInteraction ) ); ?></td>
                            <td>
                                <div id="<?= $campaignId ?>-<?= $subscriberId ?>"
                                     class="mawiblah-campaign-action test"
                                     data-campaign-id="<?= $campaignId ?>"
                                     data-subscriber-id="<?= $subscriberId ?>"
                                     data-subscriber-email="<?= $email ?>">
                                    Status
                                </div>
                            </td>
                        </tr>
                        <?php

                    }
                    ?>

                    </thead>
                    <tbody>
                </table>
                <?php
            }
        }
    }
    ?>


</div>
