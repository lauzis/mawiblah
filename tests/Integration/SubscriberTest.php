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

    public function test_has_testers_returns_false_with_no_testers(): void
    {
        $term = wp_insert_term('No Testers Audience', Subscribers::postType() . '_category');
        if (is_wp_error($term)) $this->markTestSkipped('Could not create term');
        $audienceId = $term['term_id'];

        wp_set_object_terms($this->sub->id, $audienceId, Subscribers::postType() . '_category');

        $this->assertFalse(Subscribers::hasTestersInAudiences([$audienceId]));

        wp_delete_term($audienceId, Subscribers::postType() . '_category');
    }

    public function test_has_testers_returns_true_with_tester_meta(): void
    {
        $term = wp_insert_term('Meta Tester Audience', Subscribers::postType() . '_category');
        if (is_wp_error($term)) $this->markTestSkipped('Could not create term');
        $audienceId = $term['term_id'];

        wp_set_object_terms($this->sub->id, $audienceId, Subscribers::postType() . '_category');
        update_post_meta($this->sub->id, 'tester', true);

        $this->assertTrue(Subscribers::hasTestersInAudiences([$audienceId]));

        delete_post_meta($this->sub->id, 'tester');
        wp_delete_term($audienceId, Subscribers::postType() . '_category');
    }

    public function test_has_testers_returns_true_via_testers_audience(): void
    {
        $testerAudience = Subscribers::testerAudience();
        $this->assertNotNull($testerAudience);

        wp_set_object_terms($this->sub->id, $testerAudience->term_id, Subscribers::postType() . '_category');

        $this->assertTrue(Subscribers::hasTestersInAudiences([$testerAudience->term_id]));

        wp_remove_object_terms($this->sub->id, $testerAudience->term_id, Subscribers::postType() . '_category');
    }

    public function test_has_testers_returns_false_for_empty_audience_list(): void
    {
        $this->assertFalse(Subscribers::hasTestersInAudiences([]));
    }

    public function test_is_email_sent_flag(): void
    {
        $cId = Campaigns::addCampaign('Sent Flag Test', 'Subj', 'Title', 'Content', [], 'test');
        $this->assertFalse(Subscribers::isEmailSent($this->sub->id, $cId));

        Subscribers::sentEmail($this->sub->id, $cId);
        $this->assertTrue(Subscribers::isEmailSent($this->sub->id, $cId));

        Campaigns::deleteCampaign($cId);
    }

    public function test_failing_email_audience_is_created(): void
    {
        $audience = Subscribers::failingEmailAudience();
        $this->assertNotNull($audience);
        $this->assertSame('Failing Email', $audience->name);
    }

    public function test_sent_email_failed_increments_fail_count(): void
    {
        $cId = Campaigns::addCampaign('Fail Count Test', 'Subj', 'Title', 'Content', [], 'test');

        Subscribers::sentEmailFailed($this->sub->id, $cId);
        $this->assertSame(1, (int) get_post_meta($this->sub->id, 'email_fail_count', true));

        Subscribers::sentEmailFailed($this->sub->id, $cId);
        $this->assertSame(2, (int) get_post_meta($this->sub->id, 'email_fail_count', true));

        Campaigns::deleteCampaign($cId);
    }

    public function test_sent_email_failed_stores_error_reason(): void
    {
        $cId = Campaigns::addCampaign('Fail Reason Test', 'Subj', 'Title', 'Content', [], 'test');

        Subscribers::sentEmailFailed($this->sub->id, $cId, 'SMTP: Could not connect');
        $this->assertSame('SMTP: Could not connect', get_post_meta($this->sub->id, 'sent_' . $cId . '_error', true));

        Campaigns::deleteCampaign($cId);
    }

    public function test_subscriber_added_to_failing_email_audience_at_threshold(): void
    {
        $threshold = \Mawiblah\Settings::failingEmailThreshold();
        $cId = Campaigns::addCampaign('Threshold Test', 'Subj', 'Title', 'Content', [], 'test');

        for ($i = 0; $i < $threshold - 1; $i++) {
            Subscribers::sentEmailFailed($this->sub->id, $cId);
        }

        $failingAudience = Subscribers::failingEmailAudience();
        $this->assertFalse(has_term($failingAudience->term_id, Subscribers::postType() . '_category', $this->sub->id));

        Subscribers::sentEmailFailed($this->sub->id, $cId);
        $this->assertTrue(has_term($failingAudience->term_id, Subscribers::postType() . '_category', $this->sub->id));

        Campaigns::deleteCampaign($cId);
    }

    public function test_subscriber_not_added_to_failing_email_before_threshold(): void
    {
        $threshold = \Mawiblah\Settings::failingEmailThreshold();
        $cId = Campaigns::addCampaign('Below Threshold Test', 'Subj', 'Title', 'Content', [], 'test');

        for ($i = 0; $i < $threshold - 1; $i++) {
            Subscribers::sentEmailFailed($this->sub->id, $cId);
        }

        $failingAudience = Subscribers::failingEmailAudience();
        $this->assertFalse(has_term($failingAudience->term_id, Subscribers::postType() . '_category', $this->sub->id));

        Campaigns::deleteCampaign($cId);
    }
}
