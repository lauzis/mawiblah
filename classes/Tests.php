<?php

namespace Mawiblah;

class Tests
{
    public static function echoHeading(string $title): void
    {
        echo "<h2>$title</h2>";
    }

    public static function echoTitle(string $title): void
    {
        echo "<h3>$title</h3>";
    }

    public static function echoResult($resultMessage, $resultType, $debug=null): void
    {
        echo "<p class='mawiblah-$resultType'>$resultMessage</p>";
        if ($debug) {
            echo "<p class='mawiblah-debug'>".print_r($debug)."<p>";
        }
    }

    public static function getCampaignTitle(): string
    {
        return 'Mawiblah Test Campaing';
    }
    public static function tests(): void
    {

        $campaignTitle = self::getCampaignTitle();
        $campaignTitle2 = self::getCampaignTitle()." ---2";
        $campaignTitle3 = self::getCampaignTitle()." ---3";

        if (current_user_can('editor') || current_user_can('administrator')) {
            // test that adds comaingn to posts

            $campaignsAtTheStartOfTheTest = Campaigns::getCampaigns();

            self::echoHeading("Campaigns self tests");

            self::echoTitle('Mawiblah Test Campaing');
            $result = Campaigns::addCampaign($campaignTitle, 'Test subject',  'Test content title','Test content', ['Test audience'], 'Test template');
            $tiId = $result;
            if ($result) {
                self::echoResult('Campaign added', 'success');
            } else {
                self::echoResult('Campaign not added', 'error');
            }

            $result = Campaigns::addCampaign($campaignTitle3, 'Test subject', 'Test content title','Test content', ['Test audience'], 'Test template');
            $t3Id = $result;

            // check if newly created campaign exists
            self::echoTitle('Check if newly created campaign exists');
            $campaign = Campaigns::getCampaign($campaignTitle);
            if ($campaign && $campaign->post_title === $campaignTitle) {
                self::echoResult('Campaign exists', 'success');
            } else {
                self::echoResult('Campaign does not exist', 'error');
            }

            //check the campaign that should not exist
            self::echoTitle('Check the campaign that should not exist');
            $campaign = Campaigns::getCampaign($campaignTitle2);
            if ($campaign === null) {
                self::echoResult('Campaign does not exist', 'success');
            } else {
                self::echoResult('Campaign exists, but should not', 'error', $campaign);
            }


            // get campaings should be atleaste tow
            self::echoTitle('Get campaings');
            $campaigns = Campaigns::getCampaigns();
            if (count($campaigns) >= 2) {
                self::echoResult('Campaigns found - '.count($campaigns), 'success');
            } else {
                self::echoResult('No campaigns found, wrong count', 'error');
            }

            self::echoTitle('Exactly two campaigns where created');
            if (count($campaigns) - count($campaignsAtTheStartOfTheTest) === 2) {
                self::echoResult('Campaigns at the start - '.count($campaignsAtTheStartOfTheTest).' and campaigns before deletion '.count($campaigns), 'success');
            } else {
                self::echoResult('Wrong count, test failed. At the start - '.count($campaignsAtTheStartOfTheTest).' and campaigns before deletion '.count($campaigns), 'error');
            }


            // remove compaings
            self::echoTitle('Remove test campaings that was created');
            Campaigns::deleteCampaign($tiId);
            Campaigns::deleteCampaign($t3Id);

            $campaigns = Campaigns::getCampaigns();
            if (count($campaigns) === count($campaignsAtTheStartOfTheTest)) {
                self::echoResult('Campaigns removed', 'success');
            } else {
                self::echoResult('Campaigns not removed', 'error');
            }

        }
    }
}
