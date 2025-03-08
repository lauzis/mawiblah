<?php
    use Mawiblah\Helpers;
?>
<div class="wrap mawiblah">

    <h1>Campaigns</h1>
    <p>
        List of campaigns
    </p>

    <a class="btn" href="<?= Helpers::generatePluginUrl(['action'=>'create']); ?>">Create new campaign</a>
    <table>
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
            echo "<td>" . $campaign->id. "</td>";
            echo "<td>" . $campaign->post_title . "</td>";
            echo "<td>" . $campaign->subject . "</td>";
            echo "<td>" . $campaign->template . "</td>";
            echo "<td>" . implode(",",$campaign->audiences) . "</td>";
            echo "<td>" . $campaign->status . "</td>";
            echo "<td>" . $campaign->emailsSend . "</td>";
            echo "<td>" . $campaign->emailsFailed . "</td>";
            echo "<td>" . $campaign->emailsSkipped . "</td>";
            echo "<td>" . $campaign->emailsUnsubed . "</td>";
            echo "<td>" . $campaign->linksClicked . "</td>";
            $status = $campaign->post_status;
            if ($status === 'sent') {
                echo "";
            } else {
                $finished = $campaign->status === 'finished' ? 'disabled' : '';
                echo "<td>
                    <a class='btn link-send campaign-actions' data-type='send' data-href='".Helpers::generatePluginUrl(['action'=>'test','campaignId'=>$campaign->id])."'>Test</a>
                </td>";
                echo "<td>
                    <a class='btn btn-danger link-send campaign-actions $finished' data-type='send' data-href='".Helpers::generatePluginUrl(['action'=>'send','campaignId'=>$campaign->id])."'>Send</a>
                </td>";

                echo "<td>
                    <a class='btn btn-warning link-delete campaign-actions $finished' data-type='delete' data-href='".Helpers::generatePluginUrl(['action'=>'delete','campaignId'=>$campaign->id])."'>Delete</a>
                </td>";

                echo "<td>
                    <a class='btn link-edit campaign-actions $finished' data-type='edit' data-href='".Helpers::generatePluginUrl(['action'=>'edit','campaignId'=>$campaign->id])."'>Edit</a>
                </td>";
            }

            echo "</tr>";
        }
        ?>
    </table>

</div>
