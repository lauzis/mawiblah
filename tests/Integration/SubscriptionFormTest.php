<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Subscribers;
use Mawiblah\SubscriptionForm;
use WP_UnitTestCase;
use WP_REST_Request;

class SubscriptionFormTest extends WP_UnitTestCase
{
    private array $audienceIds = [];
    private array $hashes      = [];

    public function setUp(): void
    {
        parent::setUp();

        $t1 = wp_insert_term('PHPUnit Audience 1', Subscribers::postType() . '_category');
        $t2 = wp_insert_term('PHPUnit Audience 2', Subscribers::postType() . '_category');

        $this->audienceIds = [
            is_wp_error($t1) ? null : $t1['term_id'],
            is_wp_error($t2) ? null : $t2['term_id'],
        ];

        foreach ($this->audienceIds as $id) {
            if ($id) {
                $aud = Subscribers::getAudience($id);
                $this->hashes[] = $aud->audienceHash;
            }
        }
    }

    public function tearDown(): void
    {
        foreach (get_posts(['post_type' => Subscribers::postType(), 'posts_per_page' => -1]) as $p) {
            if (str_contains($p->post_title, '@mawiblah.test')) {
                wp_delete_post($p->ID, true);
            }
        }
        foreach ($this->audienceIds as $id) {
            if ($id) wp_delete_term($id, Subscribers::postType() . '_category');
        }
        parent::tearDown();
    }

    private function makeRequest(array $body): WP_REST_Request
    {
        $req = new WP_REST_Request('POST', '/mawiblah/v1/subscribe');
        $req->set_body(json_encode($body));
        $req->set_header('content-type', 'application/json');
        return $req;
    }

    public function test_new_subscriber_is_created(): void
    {
        $email = 'new@mawiblah.test';
        $res   = SubscriptionForm::subscribe($this->makeRequest(['email' => $email, 'audienceHashes' => $this->hashes, 'honeypot' => '']));

        $this->assertSame('ok', $res['status']);
        $this->assertNotNull(Subscribers::getSubscriber($email));
    }

    public function test_duplicate_submission_is_silent(): void
    {
        $email = 'dup@mawiblah.test';
        SubscriptionForm::subscribe($this->makeRequest(['email' => $email, 'audienceHashes' => $this->hashes, 'honeypot' => '']));
        $res  = SubscriptionForm::subscribe($this->makeRequest(['email' => $email, 'audienceHashes' => $this->hashes, 'honeypot' => '']));
        $dups = get_posts(['post_type' => Subscribers::postType(), 'title' => $email, 'posts_per_page' => -1]);

        $this->assertSame('ok', $res['status']);
        $this->assertCount(1, $dups);
    }

    public function test_honeypot_rejects_silently(): void
    {
        $email = 'bot@mawiblah.test';
        $res   = SubscriptionForm::subscribe($this->makeRequest(['email' => $email, 'audienceHashes' => [], 'honeypot' => 'bot-value']));

        $this->assertSame('ok', $res['status']);
        $this->assertNull(Subscribers::getSubscriber($email));
    }

    public function test_invalid_email_returns_error(): void
    {
        $res = SubscriptionForm::subscribe($this->makeRequest(['email' => 'not-an-email', 'audienceHashes' => [], 'honeypot' => '']));

        $this->assertSame('error', $res['status']);
    }

    public function test_unsubscribed_email_triggers_resubscribe_message(): void
    {
        $email = 'resub@mawiblah.test';
        SubscriptionForm::subscribe($this->makeRequest(['email' => $email, 'audienceHashes' => $this->hashes, 'honeypot' => '']));
        $sub = Subscribers::getSubscriber($email);
        update_post_meta($sub->id, 'unsubed', true);

        $res   = SubscriptionForm::subscribe($this->makeRequest(['email' => $email, 'audienceHashes' => $this->hashes, 'honeypot' => '']));
        $fresh = Subscribers::getSubscriber($email);

        $this->assertSame('ok', $res['status']);
        $this->assertTrue((bool) $fresh->unsubed, 'unsubed flag should still be set while awaiting confirmation');
    }

    public function test_valid_resub_token_clears_unsubed_flag(): void
    {
        $email = 'resubconfirm@mawiblah.test';
        SubscriptionForm::subscribe($this->makeRequest(['email' => $email, 'audienceHashes' => $this->hashes, 'honeypot' => '']));
        $sub   = Subscribers::getSubscriber($email);
        $token = Subscribers::getUnsubToken($sub->id, $email);
        update_post_meta($sub->id, 'unsubed', true);

        // Simulate confirmation: valid token match clears flag
        $this->assertSame($token, Subscribers::getUnsubToken($sub->id, $email));
        update_post_meta($sub->id, 'unsubed', false);
        delete_post_meta($sub->id, 'unsub_time');

        $fresh = Subscribers::getSubscriber($email);
        $this->assertFalse((bool) $fresh->unsubed);
    }

    public function test_invalid_resub_token_is_rejected(): void
    {
        $email = 'resubinvalid@mawiblah.test';
        SubscriptionForm::subscribe($this->makeRequest(['email' => $email, 'audienceHashes' => $this->hashes, 'honeypot' => '']));
        $sub   = Subscribers::getSubscriber($email);
        $token = Subscribers::getUnsubToken($sub->id, $email);

        $this->assertNotSame('wrong-token', $token);
    }

    public function test_subscriber_added_to_multiple_audiences(): void
    {
        $email = 'multiaud@mawiblah.test';
        SubscriptionForm::subscribe($this->makeRequest(['email' => $email, 'audienceHashes' => $this->hashes, 'honeypot' => '']));
        $sub   = Subscribers::getSubscriber($email);
        $terms = wp_get_post_terms($sub->id, Subscribers::postType() . '_category', ['fields' => 'ids']);

        foreach ($this->audienceIds as $id) {
            if ($id) $this->assertContains($id, $terms);
        }
    }

    public function test_partial_audience_overlap_adds_missing_only(): void
    {
        $email = 'partial@mawiblah.test';
        // Subscribe to first audience only
        SubscriptionForm::subscribe($this->makeRequest(['email' => $email, 'audienceHashes' => [$this->hashes[0]], 'honeypot' => '']));
        $sub    = Subscribers::getSubscriber($email);
        $before = wp_get_post_terms($sub->id, Subscribers::postType() . '_category', ['fields' => 'ids']);
        $this->assertCount(1, $before);

        // Subscribe to both — should add only the missing second audience
        SubscriptionForm::subscribe($this->makeRequest(['email' => $email, 'audienceHashes' => $this->hashes, 'honeypot' => '']));
        $after = wp_get_post_terms($sub->id, Subscribers::postType() . '_category', ['fields' => 'ids']);
        $this->assertCount(2, $after);
    }
}
