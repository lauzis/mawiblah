<?php
/**
 * Entry point for every bundled copy of wp-logs.
 *
 * Loaded via Composer's "files" autoloader, so this runs once per plugin that
 * ships the package. It does not define the library itself — it only announces
 * this copy's version to the registry. The highest version registered across
 * all active plugins is the one that actually gets loaded.
 *
 * Deliberately no ABSPATH guard: Composer's autoloader may run before
 * WordPress is bootstrapped (plugins that require vendor/autoload.php early,
 * WP-CLI, and PHPUnit suites all do this), and bailing out there would leave
 * the registry undefined and logging silently disabled. Nothing here touches
 * WordPress — registration is inert until a logger is actually requested.
 */

require_once __DIR__ . '/src/Registry.php';

WpLogs_Registry::register( '1.0.0', __DIR__ . '/src/load.php' );
