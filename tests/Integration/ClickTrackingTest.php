<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Campaigns;
use Mawiblah\Subscribers;
use Mawiblah\Visits;
use WP_UnitTestCase;

class ClickTrackingTest extends WP_UnitTestCase
{
    private int    $campaignId;
    private object $sub1;
    private object $sub2;
    private string $campaignHash;

    public function setUp(): void
    {
        parent::setUp();
        $this->campaignId   = Campaigns::addCampaign('Click Test', 'Subject', 'Title', 'Content', [], 'test-template');
        $c                  = Campaigns::getCampaignById($this->campaignId);
        $this->campaignHash = $c->campaignHash;
        $this->sub1         = Subscribers::addSubscriber('click1@mawiblah.test');
        $this->sub2         = Subscribers::addSubscriber('click2@mawiblah.test');
        $_SESSION           = [];
    }

    public function tearDown(): void
    {
        Campaigns::deleteCampaign($this->campaignId);
        wp_delete_post($this->sub1->id, true);
        wp_delete_post($this->sub2->id, true);
        $_SESSION = [];
        parent::tearDown();
    }

    private function getCounters(): array
    {
        return [
            'total'  => (int) get_post_meta($this->campaignId, 'linksClickedTotal', true),
            'unique' => (int) get_post_meta($this->campaignId, 'linksClicked', true),
            'users'  => (int) get_post_meta($this->campaignId, 'uniqueUserClicks', true),
        ];
    }

    public function test_total_increments_every_click(): void
    {
        $url = 'https://example.com/a';
        Visits::visit($this->campaignHash, $this->sub1->subscriberHash, $url);
        Visits::visit($this->campaignHash, $this->sub1->subscriberHash, $url);

        $this->assertSame(2, $this->getCounters()['total']);
    }

    public function test_unique_per_session_deduplicates_same_url(): void
    {
        $url = 'https://example.com/b';
        Visits::visit($this->campaignHash, $this->sub1->subscriberHash, $url);
        Visits::visit($this->campaignHash, $this->sub1->subscriberHash, $url);

        $this->assertSame(1, $this->getCounters()['unique']);
    }

    public function test_unique_per_session_counts_different_urls(): void
    {
        Visits::visit($this->campaignHash, $this->sub1->subscriberHash, 'https://example.com/c1');
        Visits::visit($this->campaignHash, $this->sub1->subscriberHash, 'https://example.com/c2');

        $this->assertSame(2, $this->getCounters()['unique']);
    }

    public function test_unique_user_counted_once_per_subscriber(): void
    {
        Visits::visit($this->campaignHash, $this->sub1->subscriberHash, 'https://example.com/d1');
        Visits::visit($this->campaignHash, $this->sub1->subscriberHash, 'https://example.com/d2');
        Visits::visit($this->campaignHash, $this->sub1->subscriberHash, 'https://example.com/d3');

        $this->assertSame(1, $this->getCounters()['users']);
    }

    public function test_different_subscribers_each_count_as_unique_user(): void
    {
        $_SESSION = [];
        Visits::visit($this->campaignHash, $this->sub1->subscriberHash, 'https://example.com/e');
        $_SESSION = [];
        Visits::visit($this->campaignHash, $this->sub2->subscriberHash, 'https://example.com/e');

        $this->assertSame(2, $this->getCounters()['users']);
    }
}
