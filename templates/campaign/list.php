<?php

use Mawiblah\Helpers;

?>
<div class="wrap mawiblah">

    <h1>Campaigns</h1>
    <p>
        List of campaigns
    </p>

    <div class="btn-row">
        <a class="btn" href="<?= Helpers::generatePluginUrl(['action' => 'create-campaign']); ?>">Create new campaign</a>
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
            <th>Links clicked</th>
            <th colspan="4">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $campaigns = \Mawiblah\Campaigns::getCampaigns();

        foreach ($campaigns as $campaign) {
            echo "<tr>";
            echo "<td>" . $campaign->id . "</td>";
            echo "<td>" . $campaign->post_title . "</td>";
            echo "<td>" . $campaign->subject . "</td>";
            echo "<td>" . $campaign->template . "</td>";
            echo "<td>" . implode(",", $campaign->audiences) . "</td>";
            echo "<td>" . $campaign->status . "</td>";
            echo "<td>" . $campaign->emailsSend . "</td>";
            echo "<td>" . $campaign->emailsFailed . "</td>";
            echo "<td>" . $campaign->emailsSkipped . "</td>";
            echo "<td>" . $campaign->emailsUnsubed . "</td>";
            echo "<td>" . $campaign->linksClicked . "</td>";
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
                echo "<td colspan='4'>
                    Campaign is completed
                    </td>";
            } else {
                echo "<td>
                    <a class='btn link-send campaign-actions' data-type='send' data-href='" . Helpers::generatePluginUrl(['action' => 'test', 'campaignId' => $campaign->id]) . "'>" . $testButtonText . "</a>
                </td>";
                echo "<td>
                        <a class='btn btn-danger link-send campaign-actions $sendDisabled' data-type='send' data-href='" . Helpers::generatePluginUrl(['action' => 'campaign-send', 'campaignId' => $campaign->id], 'campaignId') . "'>Send</a>
                    </td>";
                echo "<td>
                    <a class='btn btn-warning link-delete campaign-actions $deleteDisabled' data-type='delete' data-href='" . Helpers::generatePluginUrl(['action' => 'campaign-delete', 'campaignId' => $campaign->id], 'campaignId') . "'>Delete</a>
                    </td>";
                echo "<td>
                    <a class='btn link-edit campaign-actions $editDisabled' data-type='edit' data-href='" . Helpers::generatePluginUrl(['action' => 'campaign-edit', 'campaignId' => $campaign->id], 'campaignId') . "'>Edit</a>
                </td>";
            }


            echo "</tr>";
        }
        ?>
    </table>

</div>
