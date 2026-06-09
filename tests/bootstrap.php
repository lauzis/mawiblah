<?php
/**
 * PHPUnit bootstrap — loads the WordPress test environment.
 *
 * Requires WP_PHPUNIT__DIR to be set (provided by wp-phpunit/wp-phpunit),
 * and WP_TESTS_DIR / WP_TESTS_CONFIG_FILE_PATH to point to a configured
 * wp-tests-config.php.
 *
 * Run: composer test
 */

$_tests_dir = getenv('WP_PHPUNIT__DIR') ?: getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    // Try the vendor path from wp-phpunit/wp-phpunit
    $_tests_dir = dirname(__DIR__) . '/vendor/wp-phpunit/wp-phpunit';
}

if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find WordPress PHPUnit bootstrap. Set WP_PHPUNIT__DIR env var.\n";
    exit(1);
}

// Load WordPress test functions
require_once $_tests_dir . '/includes/functions.php';

// Load the plugin before WordPress is set up
tests_add_filter('muplugins_loaded', function () {
    require dirname(__DIR__) . '/mawiblah.php';
});

// Boot WordPress
require $_tests_dir . '/includes/bootstrap.php';
