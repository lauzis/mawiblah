<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Subscribers;
use Mawiblah\Campaigns;
use WP_UnitTestCase;

class SubscriberTest extends WP_UnitTestCase
{
    private string $email = 'subtest@mawiblah.test';
    private ?object $sub  = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->sub = Subscribers::addSubscriber($this->email);
    }

    public function tearDown(): void
    {
        if ($this->sub) wp_delete_post($this->sub->id, true);
        parent::tearDown();
    }

    public function test_add_subscriber_no_duplicate(): void
    {
        Subscribers::addSubscriber($this->email);
        $all = get_posts(['post_type' => Subscribers::postType(), 'title' => $this->email, 'posts_per_page' => -1]);
        $this->assertCount(1, $all);
    }

    public function test_get_subscriber_by_email(): void
    {
        $found = Subscribers::getSubscriber($this->email);
        $this->assertNotNull($found);
        $this->assertSame($this->email, $found->email);
    }

    public function test_get_subscriber_by_id(): void
    {
        $found = Subscribers::getSubscriberById($this->sub->id);
        $this->assertNotNull($found);
        $this->assertSame($this->sub->id, $found->id);
    }

    public function test_get_subscriber_by_hash(): void
    {
        $found = Subscribers::getSubscriberBySubscriberHash($this->sub->subscriberHash);
        $this->assertNotNull($found);
        $this->assertSame($this->sub->id, $found->id);
    }

    public function test_audience_hash_is_generated_lazily(): void
    {
        $term = wp_insert_term('Hash Test Audience', Subscribers::postType() . '_category');
        if (is_wp_error($term)) $this->markTestSkipped('Could not create term');

        $audience = Subscribers::getAudience($term['term_id']);
        $this->assertNotEmpty($audience->audienceHash);

        wp_delete_term($term['term_id'], Subscribers::postType() . '_category');
    }

    public function test_audience_hash_is_idempotent(): void
    {
        $term = wp_insert_term('Idempotent Hash Audience', Subscribers::postType() . '_category');
        if (is_wp_error($term)) $this->markTestSkipped('Could not create term');

        $hash1 = Subscribers::getAudience($term['term_id'])->audienceHash;
        $hash2 = Subscribers::getAudience($term['term_id'])->audienceHash;
        $this->assertSame($hash1, $hash2);

        wp_delete_term($term['term_id'], Subscribers::postType() . '_category');
    }

    public function test_unsub_token_persists(): void
    {
        $token1 = Subscribers::getUnsubToken($this->sub->id, $this->email);
        $token2 = Subscribers::getUnsubToken($this->sub->id, $this->email);
        $this->assertNotEmpty($token1);
        $this->assertSame($token1, $token2);
    }

    public function test_is_email_sent_flag(): void
    {
        $cId = Campaigns::addCampaign('Sent Flag Test', 'Subj', 'Title', 'Content', [], 'test');
        $this->assertFalse(Subscribers::isEmailSent($this->sub->id, $cId));

        Subscribers::sentEmail($this->sub->id, $cId);
        $this->assertTrue(Subscribers::isEmailSent($this->sub->id, $cId));

        Campaigns::deleteCampaign($cId);
    }
}
