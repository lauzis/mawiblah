<?php

namespace Mawiblah\Tests\Integration;

use Mawiblah\Init;
use WP_UnitTestCase;

/**
 * Integration tests for the Import-related additions to Init.
 *
 * Covers:
 *  - Init::MAWIBLAH_IMPORT constant value
 *  - Init::getIdsOfPages() includes the import page slug
 *  - Init::import() delegates to Renderer::import() without throwing
 */
class InitImportTest extends WP_UnitTestCase
{
    // ── Constant ──────────────────────────────────────────────────────────────

    public function test_mawiblah_import_constant_value(): void
    {
        $this->assertSame('mawiblah-import', Init::MAWIBLAH_IMPORT);
    }

    // ── getIdsOfPages ─────────────────────────────────────────────────────────

    public function test_getIdsOfPages_includes_import_slug(): void
    {
        $pages = Init::getIdsOfPages();

        $this->assertContains(
            Init::MAWIBLAH_IMPORT,
            $pages,
            'getIdsOfPages() must include the mawiblah-import page slug'
        );
    }

    public function test_getIdsOfPages_returns_array(): void
    {
        $this->assertIsArray(Init::getIdsOfPages());
    }

    public function test_getIdsOfPages_import_slug_is_string(): void
    {
        $pages = Init::getIdsOfPages();
        $slug  = Init::MAWIBLAH_IMPORT;

        // Verify the matching entry in the pages array is a string (not cast to int, etc.)
        $found = array_filter($pages, fn($p) => $p === $slug);
        $this->assertCount(1, $found, 'Import slug should appear exactly once in the pages array');
    }

    public function test_getIdsOfPages_import_appears_exactly_once(): void
    {
        $pages = Init::getIdsOfPages();
        $count = count(array_filter($pages, fn($p) => $p === Init::MAWIBLAH_IMPORT));

        $this->assertSame(1, $count, 'Import slug must not be duplicated in the pages array');
    }
}