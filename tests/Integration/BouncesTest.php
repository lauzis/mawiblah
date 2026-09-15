<?php

namespace Mawiblah\Tests\Integration;

use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Mawiblah\Bounces;
use Mawiblah\Secrets;
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
        update_option('mawiblah_db_version', '1.1.0');

        $this->inbox = new FakeFolder('INBOX', messages: [
            new FakeMessage(1, contents: "From: reader@example.org\r\nSubject: Thanks!\r\n\r\nLoved the newsletter."),
            new FakeMessage(2, contents: self::fixture('microsoft-recipient-not-found.eml')),
            new FakeMessage(3, contents: self::fixture('mailbox-full-delayed.eml')),
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
        $this->assertFalse($this->inFailingEmail($this->hard->id), 'Nothing happens to a subscriber before approval.');
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

    public function test_approving_a_hard_bounce_moves_the_subscriber_to_failing_email_and_deletes_the_report(): void
    {
        Bounces::check(10);
        $row = $this->pendingFor('aivars.lauzis@awave.com');

        $this->assertSame((int) $this->hard->id, (int) $row->subscriber_id);

        $this->assertSame(['handled' => true, 'error' => ''], Bounces::approve((int) $row->id));

        $this->assertTrue($this->inFailingEmail($this->hard->id));
        $this->assertSame('1', get_post_meta($this->hard->id, 'bounce_hard_count', true));
        $this->assertSame('5.1.10', get_post_meta($this->hard->id, 'bounce_last_status', true));
        $this->assertSame([1, 3], $this->uids(), 'Only the approved report is gone.');

        $handled = Bounces::get((int) $row->id);
        $this->assertSame(Bounces::STATE_RESOLVED, $handled->state);
        $this->assertSame(1, (int) $handled->message_deleted);
    }

    public function test_approving_a_soft_bounce_records_it_and_keeps_the_subscriber_receiving(): void
    {
        Bounces::check(10);
        $row = $this->pendingFor('lauzis@inbox.lv');

        Bounces::approve((int) $row->id);

        $this->assertFalse($this->inFailingEmail($this->soft->id));
        $this->assertSame('1', get_post_meta($this->soft->id, 'bounce_soft_count', true));
        $this->assertSame([1, 2], $this->uids());
    }

    public function test_dismissing_deletes_the_report_and_leaves_the_subscriber_alone(): void
    {
        Bounces::check(10);
        $row = $this->pendingFor('aivars.lauzis@awave.com');

        $this->assertTrue(Bounces::dismiss((int) $row->id)['handled']);

        $this->assertFalse($this->inFailingEmail($this->hard->id));
        $this->assertSame('', get_post_meta($this->hard->id, 'bounce_hard_count', true));
        $this->assertSame([1, 3], $this->uids());
        $this->assertSame(Bounces::STATE_DISMISSED, Bounces::get((int) $row->id)->state);
    }

    public function test_a_handled_bounce_cannot_be_handled_again(): void
    {
        Bounces::check(10);
        $row = $this->pendingFor('aivars.lauzis@awave.com');

        Bounces::approve((int) $row->id);
        $again = Bounces::dismiss((int) $row->id);

        $this->assertFalse($again['handled']);
        $this->assertSame(Bounces::STATE_RESOLVED, Bounces::get((int) $row->id)->state);
    }

    /** A UID only means the same e-mail in the mailbox it was read from. */
    public function test_a_report_is_left_in_place_once_the_mailbox_settings_have_changed(): void
    {
        Bounces::check(10);
        $row = $this->pendingFor('aivars.lauzis@awave.com');

        $this->username = 'someone-else@mawiblah.test';
        $result = Bounces::approve((int) $row->id);

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

    private function inFailingEmail(int $subscriberId): bool
    {
        $audience = Subscribers::failingEmailAudience();

        return $audience && has_term($audience->term_id, Subscribers::postType() . '_category', $subscriberId);
    }
}
