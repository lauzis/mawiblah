<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Import;
use Mawiblah\Subscribers;
use WP_UnitTestCase;

/**
 * Integration tests for Import class (CSV import feature).
 *
 * Covers: Import::parseFile(), Import::storeRows(), Import::processImport()
 */
class ImportTest extends WP_UnitTestCase
{
    /** Temporary files created during tests that must be cleaned up. */
    private array $tempFiles = [];

    /** Subscriber IDs created during tests that must be cleaned up. */
    private array $subscriberIds = [];

    /** Audience term IDs created during tests. */
    private array $audienceIds = [];

    public function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        foreach ($this->subscriberIds as $id) {
            wp_delete_post($id, true);
        }

        foreach ($this->audienceIds as $id) {
            wp_delete_term($id, Subscribers::postType() . '_category');
        }

        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function writeTempCsv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mawiblah_test_');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;
        return $path;
    }

    private function createAudience(string $name): int
    {
        $term = wp_insert_term($name, Subscribers::postType() . '_category');
        if (is_wp_error($term)) {
            $this->markTestSkipped('Could not create audience term: ' . $term->get_error_message());
        }
        $id = $term['term_id'];
        $this->audienceIds[] = $id;
        return $id;
    }

    private function createSubscriber(string $email): object
    {
        $sub = Subscribers::addSubscriber($email);
        $this->assertNotNull($sub, "Failed to create subscriber for $email");
        $this->subscriberIds[] = $sub->id;
        return $sub;
    }

    // ── Import::parseFile ─────────────────────────────────────────────────────

    public function test_parseFile_returns_empty_array_for_nonexistent_file(): void
    {
        $result = Import::parseFile('/tmp/mawiblah_no_such_file_xyz.csv');
        $this->assertSame([], $result);
    }

    public function test_parseFile_parses_comma_delimited_csv(): void
    {
        $path = $this->writeTempCsv("email,name\nfoo@example.com,Foo\nbar@example.com,Bar\n");
        $rows = Import::parseFile($path);

        $this->assertCount(3, $rows);
        $this->assertSame(['email', 'name'], $rows[0]);
        $this->assertSame(['foo@example.com', 'Foo'], $rows[1]);
        $this->assertSame(['bar@example.com', 'Bar'], $rows[2]);
    }

    public function test_parseFile_parses_semicolon_delimited_csv(): void
    {
        $path = $this->writeTempCsv("email;name;city\nfoo@example.com;Foo;London\nbar@example.com;Bar;Paris\n");
        $rows = Import::parseFile($path);

        $this->assertCount(3, $rows);
        $this->assertSame(['email', 'name', 'city'], $rows[0]);
        $this->assertSame(['foo@example.com', 'Foo', 'London'], $rows[1]);
    }

    public function test_parseFile_parses_tab_delimited_csv(): void
    {
        $path = $this->writeTempCsv("email\tname\nfoo@example.com\tFoo\nbar@example.com\tBar\n");
        $rows = Import::parseFile($path);

        $this->assertCount(3, $rows);
        $this->assertSame(['email', 'name'], $rows[0]);
        $this->assertSame(['foo@example.com', 'Foo'], $rows[1]);
    }

    public function test_parseFile_trims_whitespace_from_fields(): void
    {
        $path = $this->writeTempCsv("  email  ,  name  \n  foo@example.com  ,  Foo  \n");
        $rows = Import::parseFile($path);

        $this->assertCount(2, $rows);
        $this->assertSame('email', $rows[0][0]);
        $this->assertSame('name', $rows[0][1]);
        $this->assertSame('foo@example.com', $rows[1][0]);
        $this->assertSame('Foo', $rows[1][1]);
    }

    public function test_parseFile_respects_limit_parameter(): void
    {
        $path = $this->writeTempCsv("a@a.com\nb@b.com\nc@c.com\nd@d.com\ne@e.com\n");
        $rows = Import::parseFile($path, 3);

        $this->assertCount(3, $rows);
        $this->assertSame(['a@a.com'], $rows[0]);
        $this->assertSame(['c@c.com'], $rows[2]);
    }

    public function test_parseFile_returns_all_rows_when_limit_is_zero(): void
    {
        $path = $this->writeTempCsv("a@a.com\nb@b.com\nc@c.com\nd@d.com\ne@e.com\n");
        $rows = Import::parseFile($path, 0);

        $this->assertCount(5, $rows);
    }

    public function test_parseFile_skips_empty_lines(): void
    {
        // An empty line produces [null] which parseFile must skip
        $path = $this->writeTempCsv("a@a.com\n\nb@b.com\n");
        $rows = Import::parseFile($path);

        $this->assertCount(2, $rows);
        $this->assertSame(['a@a.com'], $rows[0]);
        $this->assertSame(['b@b.com'], $rows[1]);
    }

    public function test_parseFile_returns_empty_array_for_empty_file(): void
    {
        $path = $this->writeTempCsv('');
        $rows = Import::parseFile($path);

        $this->assertSame([], $rows);
    }

    public function test_parseFile_handles_single_row_no_newline(): void
    {
        $path = $this->writeTempCsv('only@example.com');
        $rows = Import::parseFile($path);

        $this->assertCount(1, $rows);
        $this->assertSame(['only@example.com'], $rows[0]);
    }

    // ── Import::storeRows ─────────────────────────────────────────────────────

    public function test_storeRows_returns_key_with_expected_prefix(): void
    {
        $key = Import::storeRows([['a@a.com']]);
        $this->assertStringStartsWith(Import::TRANSIENT_PREFIX, $key);
    }

    public function test_storeRows_stores_rows_in_transient(): void
    {
        $rows = [['email', 'name'], ['foo@example.com', 'Foo']];
        $key  = Import::storeRows($rows);

        $stored = get_transient($key);
        $this->assertSame($rows, $stored);

        delete_transient($key);
    }

    public function test_storeRows_each_call_produces_unique_key(): void
    {
        $key1 = Import::storeRows([['a@a.com']]);
        $key2 = Import::storeRows([['b@b.com']]);

        $this->assertNotSame($key1, $key2);

        delete_transient($key1);
        delete_transient($key2);
    }

    public function test_storeRows_key_suffix_is_alphanumeric(): void
    {
        $key    = Import::storeRows([]);
        $suffix = substr($key, strlen(Import::TRANSIENT_PREFIX));

        $this->assertMatchesRegularExpression('/^[a-z0-9]+$/i', $suffix);

        delete_transient($key);
    }

    // ── Import::processImport — expired / missing transient ──────────────────

    public function test_processImport_returns_error_when_transient_missing(): void
    {
        $result = Import::processImport('mawiblah_import_nonexistent', 0, false, [], 'skip');

        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['updated']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Session expired', $result['errors'][0]);
    }

    // ── Import::processImport — basic import ──────────────────────────────────

    public function test_processImport_imports_new_subscriber(): void
    {
        $email = 'newimport@mawiblah.test';
        $key   = Import::storeRows([[$email]]);

        $result = Import::processImport($key, 0, false, [], 'skip');

        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(0, $result['updated']);
        $this->assertEmpty($result['errors']);

        $sub = Subscribers::getSubscriber($email);
        $this->assertNotNull($sub);
        $this->subscriberIds[] = $sub->id;
    }

    public function test_processImport_skips_header_row_when_has_headers_true(): void
    {
        $key = Import::storeRows([
            ['email'],               // header row — should be skipped
            ['header@mawiblah.test'],
        ]);

        $result = Import::processImport($key, 0, true, [], 'skip');

        $this->assertSame(1, $result['imported']);

        $sub = Subscribers::getSubscriber('header@mawiblah.test');
        $this->assertNotNull($sub);
        $this->subscriberIds[] = $sub->id;

        // The header-row value 'email' must NOT have been imported as a subscriber
        $this->assertNull(Subscribers::getSubscriber('email'));
    }

    public function test_processImport_does_not_skip_first_row_when_has_headers_false(): void
    {
        $email = 'noheader@mawiblah.test';
        $key   = Import::storeRows([[$email]]);

        $result = Import::processImport($key, 0, false, [], 'skip');

        $this->assertSame(1, $result['imported']);

        $sub = Subscribers::getSubscriber($email);
        $this->assertNotNull($sub);
        $this->subscriberIds[] = $sub->id;
    }

    // ── Import::processImport — email column selection ────────────────────────

    public function test_processImport_reads_email_from_correct_column(): void
    {
        $email = 'col2@mawiblah.test';
        $key   = Import::storeRows([['John', 'Doe', $email]]);

        $result = Import::processImport($key, 2, false, [], 'skip');

        $this->assertSame(1, $result['imported']);
        $sub = Subscribers::getSubscriber($email);
        $this->assertNotNull($sub);
        $this->subscriberIds[] = $sub->id;
    }

    public function test_processImport_skips_row_when_email_column_missing(): void
    {
        // Row has only one column (index 0); requesting column index 2 should skip the row
        $key = Import::storeRows([['only@mawiblah.test']]);

        $result = Import::processImport($key, 2, false, [], 'skip');

        $this->assertSame(0, $result['imported']);
        $this->assertEmpty($result['errors']);
    }

    // ── Import::processImport — validation ───────────────────────────────────

    public function test_processImport_records_error_for_invalid_email(): void
    {
        $key = Import::storeRows([['not-an-email']]);

        $result = Import::processImport($key, 0, false, [], 'skip');

        $this->assertSame(0, $result['imported']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('invalid email', $result['errors'][0]);
    }

    public function test_processImport_continues_after_invalid_email_row(): void
    {
        $validEmail = 'valid@mawiblah.test';
        $key = Import::storeRows([
            ['not-an-email'],
            [$validEmail],
        ]);

        $result = Import::processImport($key, 0, false, [], 'skip');

        $this->assertSame(1, $result['imported']);
        $this->assertCount(1, $result['errors']);

        $sub = Subscribers::getSubscriber($validEmail);
        $this->assertNotNull($sub);
        $this->subscriberIds[] = $sub->id;
    }

    // ── Import::processImport — duplicate handling ────────────────────────────

    public function test_processImport_skip_mode_skips_existing_subscriber(): void
    {
        $email = 'dup-skip@mawiblah.test';
        $sub   = $this->createSubscriber($email);

        $key    = Import::storeRows([[$email]]);
        $result = Import::processImport($key, 0, false, [], 'skip');

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['updated']);
        $this->assertEmpty($result['errors']);
    }

    public function test_processImport_merge_mode_updates_existing_subscriber(): void
    {
        $email = 'dup-merge@mawiblah.test';
        $sub   = $this->createSubscriber($email);

        $key    = Import::storeRows([[$email]]);
        $result = Import::processImport($key, 0, false, [], 'merge');

        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(1, $result['updated']);
        $this->assertEmpty($result['errors']);
    }

    public function test_processImport_overwrite_mode_updates_existing_subscriber(): void
    {
        $email = 'dup-overwrite@mawiblah.test';
        $sub   = $this->createSubscriber($email);

        $key    = Import::storeRows([[$email]]);
        $result = Import::processImport($key, 0, false, [], 'overwrite');

        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(1, $result['updated']);
        $this->assertEmpty($result['errors']);
    }

    // ── Import::processImport — audience assignment ───────────────────────────

    public function test_processImport_assigns_new_subscriber_to_audience(): void
    {
        $audienceId = $this->createAudience('Import Test Audience A');
        $email      = 'audience-new@mawiblah.test';
        $key        = Import::storeRows([[$email]]);

        $result = Import::processImport($key, 0, false, [$audienceId], 'skip');

        $this->assertSame(1, $result['imported']);

        $sub = Subscribers::getSubscriber($email);
        $this->assertNotNull($sub);
        $this->subscriberIds[] = $sub->id;

        $this->assertTrue(
            has_term($audienceId, Subscribers::postType() . '_category', $sub->id),
            'New subscriber should be assigned to the requested audience'
        );
    }

    public function test_processImport_assigns_existing_subscriber_to_audience_on_merge(): void
    {
        $audienceId = $this->createAudience('Import Test Audience B');
        $email      = 'audience-merge@mawiblah.test';
        $sub        = $this->createSubscriber($email);

        $key    = Import::storeRows([[$email]]);
        $result = Import::processImport($key, 0, false, [$audienceId], 'merge');

        $this->assertSame(1, $result['updated']);

        $this->assertTrue(
            has_term($audienceId, Subscribers::postType() . '_category', $sub->id),
            'Existing subscriber should be added to the requested audience on merge'
        );
    }

    public function test_processImport_ignores_invalid_audience_id_zero(): void
    {
        $email = 'zero-audience@mawiblah.test';
        $key   = Import::storeRows([[$email]]);

        // Passing audience ID 0 should not cause errors or assignments
        $result = Import::processImport($key, 0, false, [0], 'skip');

        $this->assertSame(1, $result['imported']);
        $this->assertEmpty($result['errors']);

        $sub = Subscribers::getSubscriber($email);
        $this->assertNotNull($sub);
        $this->subscriberIds[] = $sub->id;
    }

    // ── Import::processImport — transient lifecycle ───────────────────────────

    public function test_processImport_deletes_transient_after_processing(): void
    {
        $key = Import::storeRows([['cleanup@mawiblah.test']]);

        // Confirm transient exists before processing
        $this->assertNotFalse(get_transient($key));

        $result = Import::processImport($key, 0, false, [], 'skip');

        // Transient must be deleted after a successful call
        $this->assertFalse(get_transient($key));

        $sub = Subscribers::getSubscriber('cleanup@mawiblah.test');
        if ($sub) {
            $this->subscriberIds[] = $sub->id;
        }
    }

    public function test_processImport_does_not_process_same_transient_twice(): void
    {
        $email = 'twice@mawiblah.test';
        $key   = Import::storeRows([[$email]]);

        $result1 = Import::processImport($key, 0, false, [], 'skip');
        $result2 = Import::processImport($key, 0, false, [], 'skip');

        $this->assertSame(1, $result1['imported']);
        // Second call should see no transient → session-expired error
        $this->assertNotEmpty($result2['errors']);
        $this->assertStringContainsString('Session expired', $result2['errors'][0]);

        $sub = Subscribers::getSubscriber($email);
        if ($sub) {
            $this->subscriberIds[] = $sub->id;
        }
    }

    // ── Import::processImport — multiple rows ─────────────────────────────────

    public function test_processImport_imports_multiple_rows(): void
    {
        $emails = [
            'multi1@mawiblah.test',
            'multi2@mawiblah.test',
            'multi3@mawiblah.test',
        ];
        $key = Import::storeRows(array_map(fn($e) => [$e], $emails));

        $result = Import::processImport($key, 0, false, [], 'skip');

        $this->assertSame(3, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertEmpty($result['errors']);

        foreach ($emails as $email) {
            $sub = Subscribers::getSubscriber($email);
            $this->assertNotNull($sub, "Subscriber $email should exist");
            $this->subscriberIds[] = $sub->id;
        }
    }

    public function test_processImport_mixed_valid_invalid_emails(): void
    {
        $key = Import::storeRows([
            ['good1@mawiblah.test'],
            ['bad-email'],
            ['good2@mawiblah.test'],
        ]);

        $result = Import::processImport($key, 0, false, [], 'skip');

        $this->assertSame(2, $result['imported']);
        $this->assertCount(1, $result['errors']);

        foreach (['good1@mawiblah.test', 'good2@mawiblah.test'] as $email) {
            $sub = Subscribers::getSubscriber($email);
            $this->assertNotNull($sub);
            $this->subscriberIds[] = $sub->id;
        }
    }
}