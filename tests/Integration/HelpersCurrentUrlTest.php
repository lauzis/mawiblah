<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Helpers;
use WP_UnitTestCase;

/**
 * Tests for Helpers::getCurrentUrl() covering the PR changes.
 *
 * The PR replaced direct $_SERVER['HTTP_HOST'] / $_SERVER['REQUEST_URI'] access
 * with sanitize_text_field(wp_unslash(...)) and sanitize_url(wp_unslash(...))
 * respectively, and added null-coalescing fallbacks (?? '') for both keys.
 */
class HelpersCurrentUrlTest extends WP_UnitTestCase
{
    /** Original $_SERVER values restored in tearDown. */
    private array $originalServer = [];

    public function setUp(): void
    {
        parent::setUp();
        // Snapshot keys we may mutate so we can restore them exactly
        $this->originalServer = [
            'HTTP_HOST'    => $_SERVER['HTTP_HOST']    ?? null,
            'REQUEST_URI'  => $_SERVER['REQUEST_URI']  ?? null,
            'HTTPS'        => $_SERVER['HTTPS']        ?? null,
            'SERVER_PORT'  => $_SERVER['SERVER_PORT']  ?? null,
        ];
    }

    public function tearDown(): void
    {
        foreach ($this->originalServer as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Protocol detection
    // -----------------------------------------------------------------------

    public function test_returns_http_url_when_https_is_not_set(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTP_HOST']   = 'example.com';
        $_SERVER['REQUEST_URI'] = '/path';

        $url = Helpers::getCurrentUrl();

        $this->assertStringStartsWith('http://', $url);
    }

    public function test_returns_https_url_when_https_server_var_is_on(): void
    {
        $_SERVER['HTTPS']       = 'on';
        $_SERVER['SERVER_PORT'] = '443';
        $_SERVER['HTTP_HOST']   = 'example.com';
        $_SERVER['REQUEST_URI'] = '/path';

        $url = Helpers::getCurrentUrl();

        $this->assertStringStartsWith('https://', $url);
    }

    public function test_returns_https_url_when_server_port_is_443(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = 443;
        $_SERVER['HTTP_HOST']   = 'example.com';
        $_SERVER['REQUEST_URI'] = '/path';

        $url = Helpers::getCurrentUrl();

        $this->assertStringStartsWith('https://', $url);
    }

    public function test_returns_http_when_https_is_off(): void
    {
        $_SERVER['HTTPS']       = 'off';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTP_HOST']   = 'example.com';
        $_SERVER['REQUEST_URI'] = '/path';

        $url = Helpers::getCurrentUrl();

        $this->assertStringStartsWith('http://', $url);
    }

    // -----------------------------------------------------------------------
    // Host and URI assembly
    // -----------------------------------------------------------------------

    public function test_constructs_full_url_from_server_vars(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTP_HOST']   = 'www.example.com';
        $_SERVER['REQUEST_URI'] = '/foo/bar?baz=1';

        $url = Helpers::getCurrentUrl();

        $this->assertSame('http://www.example.com/foo/bar?baz=1', $url);
    }

    public function test_constructs_https_url_correctly(): void
    {
        $_SERVER['HTTPS']       = 'on';
        $_SERVER['SERVER_PORT'] = '443';
        $_SERVER['HTTP_HOST']   = 'secure.example.com';
        $_SERVER['REQUEST_URI'] = '/admin/page';

        $url = Helpers::getCurrentUrl();

        $this->assertSame('https://secure.example.com/admin/page', $url);
    }

    // -----------------------------------------------------------------------
    // Null-coalescence — missing keys must not produce a PHP notice or error
    // -----------------------------------------------------------------------

    public function test_does_not_throw_when_http_host_is_missing(): void
    {
        unset($_SERVER['HTTP_HOST']);
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['REQUEST_URI'] = '/path';

        // The PR adds ?? '' so this must not trigger an undefined-index notice
        $url = Helpers::getCurrentUrl();

        $this->assertIsString($url);
        $this->assertStringStartsWith('http://', $url);
    }

    public function test_does_not_throw_when_request_uri_is_missing(): void
    {
        unset($_SERVER['REQUEST_URI']);
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTP_HOST']   = 'example.com';

        $url = Helpers::getCurrentUrl();

        $this->assertIsString($url);
        $this->assertStringStartsWith('http://', $url);
    }

    public function test_returns_string_when_both_host_and_uri_are_missing(): void
    {
        unset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'], $_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = '80';

        $url = Helpers::getCurrentUrl();

        $this->assertIsString($url);
    }

    // -----------------------------------------------------------------------
    // Sanitization — backslashes in HTTP_HOST are stripped by wp_unslash
    // -----------------------------------------------------------------------

    public function test_host_with_backslash_is_sanitized(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = '80';
        // wp_unslash removes backslashes; the host portion should be clean
        $_SERVER['HTTP_HOST']   = 'example\\com';
        $_SERVER['REQUEST_URI'] = '/';

        $url = Helpers::getCurrentUrl();

        $this->assertStringNotContainsString('\\', $url);
    }

    // -----------------------------------------------------------------------
    // Boundary / regression
    // -----------------------------------------------------------------------

    public function test_url_with_query_string_is_preserved(): void
    {
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTP_HOST']   = 'example.com';
        $_SERVER['REQUEST_URI'] = '/page?a=1&b=2';

        $url = Helpers::getCurrentUrl();

        $this->assertStringContainsString('a=1', $url);
        $this->assertStringContainsString('b=2', $url);
    }
}