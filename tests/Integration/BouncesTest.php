<?php

namespace Mawiblah\Tests\Integration;

use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Mawiblah\BounceParser;
use Mawiblah\Bounces;
use Mawiblah\Secrets;
use Mawiblah\Settings;
use Mawiblah\Subscribers;
use WP_UnitTestCase;

/**
 * Drives Bounces against imapengine's fake mailbox: the real parser, the real
 * table and the real subscriber changes, with only the IMAP server faked.
 */
class BouncesTest extends WP_UnitTestCase
{
    private FakeFolder $inbox;
    private object $hard;
    private object $soft;
    private string $username = 'info@mawiblah.test';

    public function setUp(): void
    {
        parent::setUp();

        Bounces::install();
        update_option('mawiblah_db_version', '1.1.1');

        $this->inbox = new FakeFolder('INBOX', messages: [
            new FakeMessage(1, contents: "From: reader@example.org\r\nSubject: Thanks!\r\n\r\nLoved the newsletter."),
            new FakeMessage(2, contents: self::fixture('microsoft-recipient-not-found.eml')),
            new FakeMessage(3, contents: self::fixture('server-unavailable-delayed.eml')),
        ]);

        new FakeMailbox([], [$this->inbox], ['UIDPLUS']);

        add_filter('mawiblah_bounce_folder', [$this, 'fakeFolder']);
        add_filter('mawiblah_bounce_mailbox', [$this, 'fakeConfig']);

        $this->hard = Subscribers::addSubscriber('aivars.lauzis@awave.com');
        $this->soft = Subscribers::addSubscriber('lauzis@inbox.lv');
    }

    public function tearDown(): void
    {
        remove_filter('mawiblah_bounce_folder', [$this, 'fakeFolder']);
        remove_filter('mawiblah_bounce_mailbox', [$this, 'fakeConfig']);
        delete_transient('mawiblah_bounce_check_lock');

        parent::tearDown();
    }

    public function fakeFolder(): FakeFolder
    {
        return $this->inbox;
    }

    public function fakeConfig(): array
    {
        return [
            'host'       => 'imap.mawiblah.test',
            'port'       => 993,
            'encryption' => 'ssl',
            'username'   => $this->username,
            'password'   => 'secret',
            'folder'     => 'INBOX',
        ];
    }

    public function test_a_check_records_each_bounce_and_leaves_the_mailbox_untouched(): void
    {
        $summary = Bounces::check(10);

        $this->assertSame('', $summary['error']);
        $this->assertSame(3, $summary['scanned']);
        $this->assertSame(2, $summary['bounces'], 'The reader\'s thank-you is not a bounce.');
        $this->assertSame(2, $summary['recorded']);
        $this->assertSame(2, Bounces::counts()[Bounces::STATE_PENDING]);
        $this->assertSame([1, 2, 3], $this->uids(), 'A check deletes nothing.');
        $this->assertFalse($this->inFailingEmail($this->hard->id), 'Nothing happens to a subscriber before a decision.');
        $this->assertSame(0, $this->failures($this->hard->id));
    }

    public function test_a_second_check_reads_only_what_arrived_since(): void
    {
        Bounces::check(10);

        $this->inbox->addMessage(new FakeMessage(4, contents: self::fixture('two-recipients-one-delivered.eml')));

        $summary = Bounces::check(10);

        $this->assertSame(1, $summary['scanned']);
        $this->assertSame(1, $summary['recorded']);
        $this->assertSame(0, Bounces::check(10)['scanned']);
        $this->assertSame(3, Bounces::counts()[Bounces::STATE_PENDING]);
    }

    public function test_a_batch_leaves_the_rest_for_the_next_run(): void
    {
        $first = Bounces::check(2);

        $this->assertSame(2, $first['scanned']);
        $this->assertTrue($first['hasMore']);

        $second = Bounces::check(2);

        $this->assertSame(1, $second['scanned']);
        $this->assertFalse($second['hasMore']);
    }

    public function test_counting_a_failure_below_the_threshold_keeps_the_subscriber_receiving(): void
    {
        Bounces::check(10);
        $row = $this->pendingFor('aivars.lauzis@awave.com');

        $this->assertSame((int) $this->hard->id, (int) $row->subscriber_id);
        $this->assertSame(['handled' => true, 'error' => '', 'moved' => false], Bounces::countAsFailure((int) $row->id));

        $this->assertSame(1, $this->failures($this->hard->id));
        $this->assertFalse($this->inFailingEmail($this->hard->id));
        $this->assertSame('1', get_post_meta($this->hard->id, 'bounce_hard_count', true));
        $this->assertSame([1, 3], $this->uids(), 'Only that report is gone.');

        $handled = Bounces::get((int) $row->id);
        $this->assertSame(Bounces::STATE_RESOLVED, $handled->state);
        $this->assertSame(Bounces::RESOLUTION_COUNTED, $handled->resolution);
        $this->assertSame(1, (int) $handled->message_deleted);
    }

    /** The same threshold a send that fails on the spot is held to. */
    public function test_the_counted_failure_that_reaches_the_threshold_moves_the_subscriber(): void
    {
        $threshold = Settings::failingEmailThreshold();
        update_post_meta($this->hard->id, 'email_fail_count', $threshold - 1);

        Bounces::check(10);
        $row    = $this->pendingFor('aivars.lauzis@awave.com');
        $result = Bounces::countAsFailure((int) $row->id);

        $this->assertTrue($result['moved']);
        $this->assertSame($threshold, $this->failures($this->hard->id));
        $this->assertTrue($this->inFailingEmail($this->hard->id));
        $this->assertSame(Bounces::RESOLUTION_COUNTED_MOVED, Bounces::get((int) $row->id)->resolution);
    }

    public function test_moving_to_failing_email_happens_straight_away_whatever_the_count(): void
    {
        Bounces::check(10);
        $row = $this->pendingFor('aivars.lauzis@awave.com');

        $this->assertSame(['handled' => true, 'error' => '', 'moved' => true], Bounces::moveToFailingEmail((int) $row->id));

        $this->assertTrue($this->inFailingEmail($this->hard->id));
        $this->assertSame(0, $this->failures($this->hard->id), 'Moving does not count.');
        $this->assertSame('5.1.10', get_post_meta($this->hard->id, 'bounce_last_status', true));
        $this->assertSame([1, 3], $this->uids());
        $this->assertSame(Bounces::RESOLUTION_MOVED, Bounces::get((int) $row->id)->resolution);
    }

    public function test_a_soft_bounce_can_be_counted_too(): void
    {
        Bounces::check(10);
        $row = $this->pendingFor('lauzis@inbox.lv');

        Bounces::countAsFailure((int) $row->id);

        $this->assertSame(1, $this->failures($this->soft->id));
        $this->assertSame('1', get_post_meta($this->soft->id, 'bounce_soft_count', true));
        $this->assertSame([1, 2], $this->uids());
    }

    public function test_dismissing_deletes_the_report_and_leaves_the_subscriber_alone(): void
    {
        Bounces::check(10);
        $row = $this->pendingFor('aivars.lauzis@awave.com');

        $this->assertTrue(Bounces::dismiss((int) $row->id)['handled']);

        $this->assertFalse($this->inFailingEmail($this->hard->id));
        $this->assertSame(0, $this->failures($this->hard->id));
        $this->assertSame('', get_post_meta($this->hard->id, 'bounce_hard_count', true));
        $this->assertSame([1, 3], $this->uids());

        $dismissed = Bounces::get((int) $row->id);
        $this->assertSame(Bounces::STATE_DISMISSED, $dismissed->state);
        $this->assertSame('', $dismissed->resolution);
    }

    public function test_a_handled_bounce_cannot_be_handled_again(): void
    {
        Bounces::check(10);
        $row = $this->pendingFor('aivars.lauzis@awave.com');

        Bounces::countAsFailure((int) $row->id);
        $again = Bounces::countAsFailure((int) $row->id);

        $this->assertFalse($again['handled']);
        $this->assertSame(1, $this->failures($this->hard->id), 'The second click counts nothing.');
    }

    /** A UID only means the same e-mail in the mailbox it was read from. */
    public function test_a_report_is_left_in_place_once_the_mailbox_settings_have_changed(): void
    {
        Bounces::check(10);
        $row = $this->pendingFor('aivars.lauzis@awave.com');

        $this->username = 'someone-else@mawiblah.test';
        $result = Bounces::moveToFailingEmail((int) $row->id);

        $this->assertTrue($result['handled'], 'The subscriber side still happens.');
        $this->assertNotSame('', $result['error']);
        $this->assertSame([1, 2, 3], $this->uids());
        $this->assertSame(0, (int) Bounces::get((int) $row->id)->message_deleted);
    }

    public function test_the_mailbox_password_never_reaches_the_database_in_the_clear(): void
    {
        update_option('_mawiblah-bounce-password', 'imap-secret');
        $stored = get_option('_mawiblah-bounce-password');

        $this->assertTrue(Secrets::isEncrypted($stored));
        $this->assertSame('imap-secret', Secrets::decrypt($stored));

        // Saving the settings page untouched posts the ciphertext back.
        update_option('_mawiblah-bounce-password', $stored);
        $this->assertSame($stored, get_option('_mawiblah-bounce-password'));
    }

    public function test_spam_rejections_are_their_own_kind_and_can_be_filtered(): void
    {
        $this->inbox->addMessage(new FakeMessage(4, contents: self::fixture('spam-rejected.eml')));

        Bounces::check(10);

        $this->assertSame(
            [BounceParser::KIND_HARD => 1, BounceParser::KIND_SOFT => 1, BounceParser::KIND_SPAM => 1, BounceParser::KIND_QUOTA => 0],
            Bounces::kindCounts(Bounces::STATE_PENDING)
        );

        $spam = Bounces::rows(Bounces::STATE_PENDING, 1, 50, BounceParser::KIND_SPAM);

        $this->assertCount(1, $spam);
        $this->assertSame('reader@example.lv', $spam[0]->recipient);
        $this->assertCount(3, Bounces::rows(Bounces::STATE_PENDING), 'No kind means every kind.');
    }

    public function test_a_counted_spam_rejection_is_recorded_as_spam(): void
    {
        $reader = Subscribers::addSubscriber('reader@example.lv');
        $this->inbox->addMessage(new FakeMessage(4, contents: self::fixture('spam-rejected.eml')));

        Bounces::check(10);
        Bounces::countAsFailure((int) $this->pendingFor('reader@example.lv')->id);

        $this->assertSame('1', get_post_meta($reader->id, 'bounce_spam_count', true));
        $this->assertSame('', get_post_meta($reader->id, 'bounce_hard_count', true));
    }

    /** Bounces read before their kind existed were recorded as hard. */
    public function test_the_upgrade_relabels_spam_and_over_quota_bounces_recorded_before(): void
    {
        global $wpdb;

        Bounces::check(10);

        $wpdb->insert(Bounces::table(), [
            'mailbox'     => 'info@mawiblah.test@imap.mawiblah.test/INBOX',
            'uid'         => 99,
            'bounced_at'  => gmdate('Y-m-d H:i:s'),
            'recipient'   => 'old@example.org',
            'kind'        => BounceParser::KIND_HARD,
            'status_code' => '5.7.0',
            'action'      => 'failed',
            'reason'      => '554 5.7.0 Reject, id=09876-39 - spam',
            'state'       => Bounces::STATE_PENDING,
            'created_at'  => gmdate('Y-m-d H:i:s'),
        ]);

        $wpdb->insert(Bounces::table(), [
            'mailbox'     => 'info@mawiblah.test@imap.mawiblah.test/INBOX',
            'uid'         => 100,
            'bounced_at'  => gmdate('Y-m-d H:i:s'),
            'recipient'   => 'full@example.com',
            'kind'        => BounceParser::KIND_HARD,
            'status_code' => '5.2.2',
            'action'      => 'failed',
            'reason'      => '552 5.2.2 <full@example.com>: user is over quota',
            'state'       => Bounces::STATE_PENDING,
            'created_at'  => gmdate('Y-m-d H:i:s'),
        ]);

        $this->assertSame(2, Bounces::reclassify());
        $this->assertSame(BounceParser::KIND_SPAM, $this->pendingFor('old@example.org')->kind);
        $this->assertSame(BounceParser::KIND_QUOTA, $this->pendingFor('full@example.com')->kind);
        $this->assertSame(BounceParser::KIND_SOFT, $this->pendingFor('lauzis@inbox.lv')->kind, 'A server that is down stays soft.');
        $this->assertSame(BounceParser::KIND_HARD, $this->pendingFor('aivars.lauzis@awave.com')->kind, 'A real "not found" stays hard.');
    }

    public function test_an_over_quota_bounce_is_its_own_kind_and_counted_as_quota(): void
    {
        $full = Subscribers::addSubscriber('over-quota@example.com');
        $this->inbox->addMessage(new FakeMessage(4, contents: self::fixture('over-quota.eml')));

        Bounces::check(10);

        $this->assertSame(1, Bounces::kindCounts(Bounces::STATE_PENDING)[BounceParser::KIND_QUOTA]);
        $this->assertCount(1, Bounces::rows(Bounces::STATE_PENDING, 1, 50, BounceParser::KIND_QUOTA));

        Bounces::countAsFailure((int) $this->pendingFor('over-quota@example.com')->id);

        $this->assertSame('1', get_post_meta($full->id, 'bounce_quota_count', true));
        $this->assertSame(1, $this->failures($full->id));
    }

    private static function fixture(string $name): string
    {
        return file_get_contents(dirname(__DIR__) . '/fixtures/bounces/' . $name);
    }

    /** @return int[] */
    private function uids(): array
    {
        $uids = array_map(fn ($message) => $message->uid(), $this->inbox->getMessages());
        sort($uids);

        return $uids;
    }

    private function pendingFor(string $email): object
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . Bounces::table() . ' WHERE recipient = %s AND state = %s',
            $email,
            Bounces::STATE_PENDING
        ));
    }

    private function failures(int $subscriberId): int
    {
        return (int) get_post_meta($subscriberId, 'email_fail_count', true);
    }

    private function inFailingEmail(int $subscriberId): bool
    {
        $audience = Subscribers::failingEmailAudience();

        return $audience && has_term($audience->term_id, Subscribers::postType() . '_category', $subscriberId);
    }
}
