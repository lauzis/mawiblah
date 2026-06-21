<?php

use Mawiblah\Helpers;

?>
<div class="wrap mawiblah">

    <h1>Campaigns</h1>
    <p>
        List of campaigns
    </p>

    <div class="btn-row">
        <a class="btn" href="<?= esc_url(Helpers::generatePluginUrl(['action' => 'create-campaign'])); ?>"><?= esc_html__('Create new campaign', 'mawiblah') ?></a>
    </div>

    <table class="mawiblah-campaign-list wp-list-table widefat striped table-view-list">
        <thead>
        <tr>
            <th>Id</th>
            <th>Name</th>
            <th>Subject</th>
            <th>Template</th>
            <th>Audience</th>
            <th>Status</th>
            <th>Emails sent</th>
            <th>Emails failed</th>
            <th>Emails skipped</th>
            <th>Emails unsubed</th>
            <th>Unique visitors</th>
            <th>Links clicked</th>
            <th colspan="5">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $campaigns = \Mawiblah\Campaigns::getCampaigns();

        foreach ($campaigns as $campaign) {
            echo "<tr>";
            echo "<td>" . esc_html($campaign->id) . "</td>";
            echo "<td>" . esc_html($campaign->post_title) . "</td>";
            echo "<td>" . esc_html($campaign->subject) . "</td>";
            echo "<td>" . esc_html($campaign->template) . "</td>";
            $audienceNames = [];
            if (is_array($campaign->audiences)) {
                foreach ($campaign->audiences as $audienceId) {
                    $audience = \Mawiblah\Subscribers::getAudience($audienceId);
                    if ($audience) {
                        $audienceNames[] = $audience->name;
                    }
                }
            }
            echo "<td>" . esc_html(implode(", ", $audienceNames)) . "</td>";
            echo "<td>" . esc_html($campaign->status) . "</td>";
            echo "<td>" . esc_html($campaign->emailsSend) . "</td>";
            echo "<td>" . esc_html($campaign->emailsFailed) . "</td>";
            echo "<td>" . esc_html($campaign->emailsSkipped) . "</td>";
            echo "<td>" . esc_html($campaign->emailsUnsubed) . "</td>";
            echo "<td>" . esc_html($campaign->uniqueUserClicks) . "</td>";
            echo "<td>" . esc_html($campaign->linksClicked) . "</td>";
            $status = $campaign->post_status;

            $campaignFinished = false;
            $editDisabled = '';
            $sendDisabled = 'disabled';
            $deleteDisabled='';

            $testButtonText = __('Test', 'mawiblah');
            if ($campaign->testFinished) {
                $testButtonText = __('Test (finished)', 'mawiblah');
            }

            if ($campaign->testApproved) {
                $testButtonText = __('Test (approved)', 'mawiblah');
                $deleteDisabled = 'disabled';
                $editDisabled= 'disabled';
                $sendDisabled = '';
            }

            if ($campaign->campaignFinished) {
                $campaignButtonText = __('Test (finished)', 'mawiblah');
                $campaignFinished = true;
            }

            if ($campaignFinished) {
                echo "<td colspan='5'>
                    Campaign is completed
                    </td>";
            } else {
                echo "<td>
                    <a class='btn link-send campaign-actions' data-type='send' data-href='" . esc_url(Helpers::generatePluginUrl(['action' => 'test', 'campaignPostId' => $campaign->id])) . "'>" . esc_html($testButtonText) . "</a>
                </td>";
                if ($sendDisabled) {
                    echo "<td><span class='btn btn-danger disabled'>" . esc_html__('Send', 'mawiblah') . "</span></td>";
                } else {
                    echo "<td>
                        <a class='btn btn-danger link-send campaign-actions' data-type='send' data-href='" . esc_url(Helpers::generatePluginUrl(['action' => 'campaign-send', 'campaignPostId' => $campaign->id], 'campaignPostId')) . "'>" . esc_html__('Send', 'mawiblah') . "</a>
                    </td>";
                }
                if ($deleteDisabled) {
                    echo "<td><span class='btn btn-warning disabled'>" . esc_html__('Delete', 'mawiblah') . "</span></td>";
                } else {
                    echo "<td>
                        <a class='btn btn-warning link-delete campaign-actions' data-type='delete' data-href='" . esc_url(Helpers::generatePluginUrl(['action' => 'campaign-delete', 'campaignPostId' => $campaign->id], 'campaignPostId')) . "'>" . esc_html__('Delete', 'mawiblah') . "</a>
                    </td>";
                }
                if ($editDisabled) {
                    echo "<td><span class='btn disabled'>" . esc_html__('Edit', 'mawiblah') . "</span></td>";
                } else {
                    echo "<td>
                        <a class='btn link-edit campaign-actions' data-type='edit' data-href='" . esc_url(Helpers::generatePluginUrl(['action' => 'campaign-edit', 'campaignPostId' => $campaign->id], 'campaignPostId')) . "'>" . esc_html__('Edit', 'mawiblah') . "</a>
                    </td>";
                }
                echo "<td>
                    <a class='btn campaign-actions' data-type='duplicate' data-href='" . esc_url(Helpers::generatePluginUrl(['action' => 'campaign-duplicate', 'campaignPostId' => $campaign->id], 'campaignPostId')) . "'>" . esc_html__('Duplicate', 'mawiblah') . "</a>
                </td>";
            }


            echo "</tr>";
        }
        ?>
    </table>

</div>
