<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Campaigns;
use Mawiblah\Subscribers;
use WP_UnitTestCase;

class CampaignTest extends WP_UnitTestCase
{
    private int $campaignId;

    public function setUp(): void
    {
        parent::setUp();
        $this->campaignId = Campaigns::addCampaign('PHPUnit Campaign', 'Subject', 'Title', 'Content', [], 'test-template');
    }

    public function tearDown(): void
    {
        Campaigns::deleteCampaign($this->campaignId);
        parent::tearDown();
    }

    public function test_campaign_workflow_state_transitions(): void
    {
        $id = $this->campaignId;
        $c  = Campaigns::getCampaignById($id);
        $this->assertFalse((bool) $c->testStarted);

        Campaigns::testStart($id);
        $this->assertNotEmpty(Campaigns::getCampaignById($id)->testStarted);

        Campaigns::testFinish($id);
        $this->assertNotEmpty(Campaigns::getCampaignById($id)->testFinished);

        Campaigns::testApprove($id);
        $this->assertNotEmpty(Campaigns::getCampaignById($id)->testApproved);

        Campaigns::testReset($id);
        $c = Campaigns::getCampaignById($id);
        $this->assertFalse((bool) $c->testStarted);
        $this->assertFalse((bool) $c->testFinished);
        $this->assertFalse((bool) $c->testApproved);

        Campaigns::campaignStart($id);
        $startedAt = Campaigns::getCampaignById($id)->campaignStarted;
        $this->assertNotEmpty($startedAt);

        // Guard: second call does not overwrite
        Campaigns::campaignStart($id);
        $this->assertSame($startedAt, Campaigns::getCampaignById($id)->campaignStarted);

        Campaigns::campaignFinish($id);
        $this->assertNotEmpty(Campaigns::getCampaignById($id)->campaignFinished);
    }

    public function test_counters_update_correctly(): void
    {
        $c        = Campaigns::getCampaignById($this->campaignId);
        $counters = Campaigns::getCounters($c);

        $this->assertSame(0, (int) $counters->emailsSend);
        $this->assertSame(0, (int) $counters->emailsFailed);
        $this->assertSame(0, (int) $counters->emailsSkipped);

        Campaigns::updateCounters($c, 10, 2, 3, 1);
        $counters = Campaigns::getCounters($c);

        $this->assertSame(10, (int) $counters->emailsSend);
        $this->assertSame(2, (int) $counters->emailsFailed);
        $this->assertSame(3, (int) $counters->emailsSkipped);
    }

    public function test_fill_template_replaces_all_placeholders(): void
    {
        $c   = Campaigns::getCampaignById($this->campaignId);
        $sub = Subscribers::addSubscriber('templateph@mawiblah.test');

        $template = '{campaignHash} {subscriberHash} {email} %7BcampaignHash%7D';
        $filled   = Campaigns::fillTemplate($template, $c, $sub);

        $this->assertStringContainsString($c->campaignHash, $filled);
        $this->assertStringContainsString($sub->subscriberHash, $filled);
        $this->assertStringContainsString($sub->email, $filled);
        $this->assertStringNotContainsString('%7BcampaignHash%7D', $filled);

        wp_delete_post($sub->id, true);
    }

    public function test_campaign_start_guard_against_double_start(): void
    {
        $id = $this->campaignId;
        Campaigns::campaignStart($id);
        $first = Campaigns::getCampaignById($id)->campaignStarted;

        Campaigns::campaignStart($id);
        $second = Campaigns::getCampaignById($id)->campaignStarted;

        $this->assertSame($first, $second);
    }
}
