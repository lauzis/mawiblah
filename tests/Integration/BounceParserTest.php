<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\BounceParser;
use WP_UnitTestCase;

/**
 * The fixtures are synthetic, written to the formats Microsoft 365 and Postfix
 * produce; no real report is committed.
 */
class BounceParserTest extends WP_UnitTestCase
{
    private static function parse(string $fixture): ?array
    {
        return BounceParser::parse(file_get_contents(dirname(__DIR__) . '/fixtures/bounces/' . $fixture));
    }

    public function test_a_microsoft_365_recipient_not_found_is_a_hard_bounce_naming_its_campaign(): void
    {
        $bounce = self::parse('microsoft-recipient-not-found.eml');

        $this->assertNotNull($bounce);
        $this->assertCount(1, $bounce['recipients']);
        $this->assertSame('aivars.lauzis@awave.com', $bounce['recipients'][0]['email']);
        $this->assertSame('5.1.10', $bounce['recipients'][0]['status']);
        $this->assertSame(BounceParser::KIND_HARD, $bounce['recipients'][0]['kind']);
        $this->assertStringContainsString('RecipientNotFound', $bounce['recipients'][0]['reason']);
        $this->assertSame('237639df16d283272a4919f0a517ebdb', $bounce['campaignHash']);
        $this->assertSame('d2949eb6aaa490c835219e945ff37dad', $bounce['subscriberHash']);
        $this->assertSame('🏫🤓 Bezmaksas drukājami darbi bērniem', $bounce['originalSubject']);
    }

    /** Postfix quotes the whole original message, not just its headers. */
    public function test_a_postfix_bounce_reads_its_headers_from_the_attached_original(): void
    {
        $bounce = self::parse('postfix-user-unknown.eml');

        $this->assertNotNull($bounce);
        $this->assertSame('ai.vrslauzis@gmail.com', $bounce['recipients'][0]['email'], 'Original-Recipient wins over the rewritten Final-Recipient.');
        $this->assertSame('5.1.1', $bounce['recipients'][0]['status']);
        $this->assertSame('0f1e2d3c4b5a69788796a5b4c3d2e1f0', $bounce['campaignHash']);
        $this->assertSame('mail.inbox.eu', $bounce['reportingMta']);
    }

    public function test_a_delayed_delivery_is_a_soft_bounce(): void
    {
        $bounce = self::parse('mailbox-full-delayed.eml');

        $this->assertNotNull($bounce);
        $this->assertSame(BounceParser::KIND_SOFT, $bounce['recipients'][0]['kind']);
        $this->assertSame('4.2.2', $bounce['recipients'][0]['status']);
        $this->assertSame('', $bounce['campaignHash'], 'A report without the header names no campaign.');
    }

    public function test_a_recipient_the_report_says_was_delivered_is_left_out(): void
    {
        $bounce = self::parse('two-recipients-one-delivered.eml');

        $this->assertNotNull($bounce);
        $this->assertSame(['gone@example.org'], array_column($bounce['recipients'], 'email'));
    }

    public function test_an_out_of_office_reply_is_not_a_bounce(): void
    {
        $this->assertNull(self::parse('out-of-office.eml'));
    }

    public function test_a_read_receipt_is_not_a_bounce(): void
    {
        $this->assertNull(self::parse('read-receipt.eml'));
    }

    /** The address works; only this e-mail was refused. */
    public function test_a_spam_filter_rejection_is_spam_not_hard(): void
    {
        $bounce = self::parse('spam-rejected.eml');

        $this->assertNotNull($bounce);
        $this->assertSame('reader@example.lv', $bounce['recipients'][0]['email']);
        $this->assertSame('5.7.0', $bounce['recipients'][0]['status']);
        $this->assertSame(BounceParser::KIND_SPAM, $bounce['recipients'][0]['kind']);
        $this->assertSame('554 5.7.0 Reject, id=09876-39 - spam', $bounce['recipients'][0]['reason']);
    }

    public function test_classify_tells_hard_soft_and_spam_apart(): void
    {
        $this->assertSame(BounceParser::KIND_HARD, BounceParser::classify('failed', '5.1.1', '550 5.1.1 <gone@example.org>: User unknown'));
        $this->assertSame(BounceParser::KIND_SPAM, BounceParser::classify('failed', '5.7.1', '550 5.7.1 Access denied'));
        $this->assertSame(BounceParser::KIND_SPAM, BounceParser::classify('failed', '5.0.0', '550 Message rejected: sender listed on Spamhaus'), 'A diagnostic can say spam without a 5.7 status.');
        $this->assertSame(BounceParser::KIND_HARD, BounceParser::classify('failed', '', ''));
        $this->assertSame(BounceParser::KIND_SOFT, BounceParser::classify('delayed', '4.7.1', 'Greylisted, try again later'), 'A temporary policy refusal is still soft.');
        $this->assertNull(BounceParser::classify('delivered', '2.0.0'));
    }

    public function test_tracking_headers_carry_only_well_formed_hashes(): void
    {
        $this->assertSame(
            ['X-Mawiblah-Campaign: 237639df16d283272a4919f0a517ebdb', 'X-Mawiblah-Subscriber: d2949eb6aaa490c835219e945ff37dad'],
            BounceParser::trackingHeaders('237639df16d283272a4919f0a517ebdb', 'd2949eb6aaa490c835219e945ff37dad')
        );
        $this->assertSame([], BounceParser::trackingHeaders('', "abc\r\nBcc: someone@example.org"));
    }
}
