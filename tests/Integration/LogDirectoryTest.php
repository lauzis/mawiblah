<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Migrations;
use WP_UnitTestCase;

class LogDirectoryTest extends WP_UnitTestCase
{
    private string $legacyDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->legacyDir = str_replace('\\', '/', wp_upload_dir()['basedir']) . '/gae-logs/';
        wp_mkdir_p($this->legacyDir);
        wp_mkdir_p(MAWIBLAH_LOG_PATH);
    }

    public function tearDown(): void
    {
        $patterns = [
            $this->legacyDir . 'mawiblah-2020-01-0*.log',
            $this->legacyDir . 'gae-2020-01.log',
            MAWIBLAH_LOG_PATH . 'mawiblah-2020-01-0*.log',
        ];

        foreach ($patterns as $pattern) {
            array_map('unlink', glob($pattern) ?: []);
        }

        parent::tearDown();
    }

    /** The directory name carries the stored token, so a log file's URL cannot be guessed from its date. */
    public function test_logs_are_written_to_a_tokened_directory_under_mawiblah_uploads(): void
    {
        $token = get_option('mawiblah_log_token');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);
        $this->assertSame(str_replace('\\', '/', MAWIBLAH_UPLOAD_DIR) . '/logs-' . $token . '/', MAWIBLAH_LOG_PATH);
    }

    /**
     * An install upgraded from 1.0.54 keeps its logs in Google Analytics Events' log
     * directory. Mawiblah's files move; that plugin's own files stay put.
     */
    public function test_upgrade_moves_only_mawiblah_logs_out_of_gae_logs(): void
    {
        file_put_contents($this->legacyDir . 'mawiblah-2020-01-01.log', "old line\n");
        file_put_contents($this->legacyDir . 'gae-2020-01.log', "gae line\n");

        update_option('mawiblah_db_version', '1.0.54');
        Migrations::run();

        $this->assertFileDoesNotExist($this->legacyDir . 'mawiblah-2020-01-01.log');
        $this->assertSame("old line\n", file_get_contents(MAWIBLAH_LOG_PATH . 'mawiblah-2020-01-01.log'));
        $this->assertSame("gae line\n", file_get_contents($this->legacyDir . 'gae-2020-01.log'));
        $this->assertSame('1.0.55', get_option('mawiblah_db_version'));
    }

    /** A day already started in the new directory keeps both halves, oldest first. */
    public function test_upgrade_merges_a_day_already_logged_to_the_new_directory(): void
    {
        file_put_contents($this->legacyDir . 'mawiblah-2020-01-02.log', "before upgrade\n");
        file_put_contents(MAWIBLAH_LOG_PATH . 'mawiblah-2020-01-02.log', "after upgrade\n");

        update_option('mawiblah_db_version', '1.0.54');
        Migrations::run();

        $this->assertFileDoesNotExist($this->legacyDir . 'mawiblah-2020-01-02.log');
        $this->assertSame("before upgrade\nafter upgrade\n", file_get_contents(MAWIBLAH_LOG_PATH . 'mawiblah-2020-01-02.log'));
    }
}
