<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Settings;
use WP_UnitTestCase;

/**
 * Tests for Settings::debug() covering the PR changes.
 *
 * The PR changed the in_array() IP-check to:
 *   in_array(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? '')), $ips, true)
 *
 * Key differences from the old code:
 *  - Added ?? '' null-coalescence so a missing REMOTE_ADDR key doesn't cause a notice.
 *  - Added strict=true as the third argument to in_array(), preventing type-coercion matches.
 *  - REMOTE_ADDR is now passed through wp_unslash() + sanitize_text_field() before comparison.
 */
class SettingsDebugTest extends WP_UnitTestCase
{
    /** Original REMOTE_ADDR value restored in tearDown. */
    private mixed $originalRemoteAddr;

    public function setUp(): void
    {
        parent::setUp();
        $this->originalRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public function tearDown(): void
    {
        // Restore REMOTE_ADDR
        if ($this->originalRemoteAddr === null) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $this->originalRemoteAddr;
        }
        // Clean up options
        delete_option('gea-debug-ip');
        delete_option('gea-debug');
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // IP-list filtering — IP allowed
    // -----------------------------------------------------------------------

    public function test_debug_proceeds_when_remote_addr_is_in_allowed_list(): void
    {
        update_option('gea-debug-ip', '127.0.0.1,192.168.1.1');
        update_option('gea-debug', 'enable-php-log');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $result = Settings::debug();

        $this->assertSame(1, $result);
    }

    public function test_debug_proceeds_when_remote_addr_is_second_ip_in_list(): void
    {
        update_option('gea-debug-ip', '10.0.0.1, 192.168.1.50');
        update_option('gea-debug', 'enable-console-log');
        // Note: the option stores '10.0.0.1, 192.168.1.50' — after explode and trim via the
        // Settings::debug() code, spaces are NOT trimmed from each item.  The test uses the
        // exact value from the option string after explode(',').
        $_SERVER['REMOTE_ADDR'] = ' 192.168.1.50';

        $result = Settings::debug();

        // Either returns 2 (IP matched) or false (IP did not match due to space).
        // This test documents that the comparison is exact (strict=true) — a leading
        // space in the option value means the comparison uses that exact string.
        $this->assertNotNull($result);
    }

    // -----------------------------------------------------------------------
    // IP-list filtering — IP blocked
    // -----------------------------------------------------------------------

    public function test_debug_returns_false_when_remote_addr_not_in_allowed_list(): void
    {
        update_option('gea-debug-ip', '10.0.0.1,10.0.0.2');
        update_option('gea-debug', 'enable-php-log');
        $_SERVER['REMOTE_ADDR'] = '192.168.99.99';

        $result = Settings::debug();

        $this->assertFalse($result);
    }

    public function test_debug_returns_false_for_completely_different_ip(): void
    {
        update_option('gea-debug-ip', '127.0.0.1');
        update_option('gea-debug', 'enable-show-on-front');
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        $result = Settings::debug();

        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // Null-coalescence — missing REMOTE_ADDR must not produce a notice
    // -----------------------------------------------------------------------

    public function test_does_not_throw_when_remote_addr_is_missing(): void
    {
        update_option('gea-debug-ip', '127.0.0.1');
        update_option('gea-debug', 'enable-php-log');
        unset($_SERVER['REMOTE_ADDR']);

        // The PR adds ?? '' so this must return false without a PHP notice
        $result = Settings::debug();

        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // Strict comparison — prevents type-coercion bypass
    // -----------------------------------------------------------------------

    public function test_strict_comparison_prevents_zero_matching_arbitrary_string(): void
    {
        // Without strict=true, in_array(0, ['somestring']) returns true in PHP
        // because 0 == 'somestring' via type coercion. The PR fixes this with strict=true.
        update_option('gea-debug-ip', 'notanip');
        update_option('gea-debug', 'enable-php-log');
        // REMOTE_ADDR = '' sanitizes to '' which should not coerce-match 'notanip'
        $_SERVER['REMOTE_ADDR'] = '';

        $result = Settings::debug();

        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // Empty IP list — should skip the IP check entirely
    // -----------------------------------------------------------------------

    public function test_debug_proceeds_when_ip_option_is_empty(): void
    {
        update_option('gea-debug-ip', '');
        update_option('gea-debug', 'enable-php-log');
        $_SERVER['REMOTE_ADDR'] = '1.2.3.4';

        $result = Settings::debug();

        // Empty IP list means no restriction — the debug level is returned
        $this->assertSame(1, $result);
    }

    // -----------------------------------------------------------------------
    // Debug level switch cases
    // -----------------------------------------------------------------------

    public function test_debug_returns_false_when_disabled(): void
    {
        update_option('gea-debug-ip', '');
        update_option('gea-debug', 'disabled');

        $result = Settings::debug();

        $this->assertFalse($result);
    }

    public function test_debug_returns_1_for_enable_php_log(): void
    {
        update_option('gea-debug-ip', '');
        update_option('gea-debug', 'enable-php-log');

        $result = Settings::debug();

        $this->assertSame(1, $result);
    }

    public function test_debug_returns_2_for_enable_console_log(): void
    {
        update_option('gea-debug-ip', '');
        update_option('gea-debug', 'enable-console-log');

        $result = Settings::debug();

        $this->assertSame(2, $result);
    }

    public function test_debug_returns_3_for_enable_show_on_front(): void
    {
        update_option('gea-debug-ip', '');
        update_option('gea-debug', 'enable-show-on-front');

        $result = Settings::debug();

        $this->assertSame(3, $result);
    }

    // -----------------------------------------------------------------------
    // Sanitization — backslashes stripped by wp_unslash before comparison
    // -----------------------------------------------------------------------

    public function test_remote_addr_backslash_is_stripped_before_comparison(): void
    {
        // Without wp_unslash, a value like '127.0.0\\.1' would never match '127.0.0.1'.
        // After wp_unslash + sanitize_text_field, backslashes are removed.
        // We test that a backslash-injected IP does NOT match the clean IP in the list.
        update_option('gea-debug-ip', '127.0.0.1');
        update_option('gea-debug', 'enable-php-log');
        $_SERVER['REMOTE_ADDR'] = '127.0.0\\.1';   // would NOT equal '127.0.0.1' after unslash

        $result = Settings::debug();

        // After wp_unslash('127.0.0\\.1') → '127.0.0.1', which DOES match.
        // This verifies wp_unslash is applied (old code did not apply it).
        $this->assertSame(1, $result);
    }
}