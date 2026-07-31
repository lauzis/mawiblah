<?php
/**
 * Test suite for lauzis/wp-logs.
 *
 * Deliberately dependency-free: the package has no Composer requirements, and
 * adding PHPUnit just for this would mean every consuming plugin resolves it.
 * Run with `composer test`, or `php tests/run.php`.
 *
 * The handful of WordPress functions the library touches are stubbed below.
 */

$base = sys_get_temp_dir() . '/wp-logs-tests-' . getmypid();
if ( is_dir( $base ) ) {
	exec( 'rm -rf ' . escapeshellarg( $base ) );
}

function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['wp_logs_test_base'] . '/uploads' ); }
function add_action( $hook, $cb, $priority = 10 ) {}
function did_action( $hook ) { return 0; }

$GLOBALS['wp_logs_test_base'] = $base;

require dirname( __DIR__ ) . '/bootstrap.php';

$pass = 0;
$fail = 0;

function check( $label, $got, $want ) {
	global $pass, $fail;

	if ( $got === $want ) {
		$pass++;
		echo "  ok   $label\n";
		return;
	}

	$fail++;
	echo "  FAIL $label\n";
	echo "         expected: " . var_export( $want, true ) . "\n";
	echo "         actual:   " . var_export( $got, true ) . "\n";
}

$today = gmdate( 'Y-m-d' );

// ---------------------------------------------------------------- registry --
echo "registry\n";
check( 'bootstrap registers this copy', WpLogs_Registry::active_version(), '1.0.0' );

WpLogs_Registry::register( '0.9.0', '/nonexistent/older.php' );
check( 'an older copy does not win', WpLogs_Registry::active_version(), '1.0.0' );

WpLogs_Registry::register( '1.10.0', dirname( __DIR__ ) . '/src/load.php' );
check( 'version compare is semantic, not lexical', WpLogs_Registry::active_version(), '1.10.0' );

$enabled = false;
$log = WpLogs_Registry::logger( 'demo', array( 'enabled' => function () use ( &$enabled ) { return $enabled; } ) );
check( 'logger() caches per slug', WpLogs_Registry::logger( 'demo' ) === $log, true );
check( 'distinct slugs get distinct loggers', WpLogs_Registry::logger( 'other' ) !== $log, true );

// ------------------------------------------------------------ enable gating --
echo "enable gating\n";
check( 'add() is a no-op while disabled', $log->add( 'boot', 'nope' ), false );
check( 'no directory created while disabled', is_dir( $log->dir() ), false );

$enabled = true;
check( 'enabling takes effect without rebuilding the logger', $log->add( 'cron', 'Batch finished.', array( 'processed' => 12 ) ), true );

// ------------------------------------------------------------------ format --
echo "format\n";
$file = $log->dir() . 'demo-' . $today . '.log';
check( 'file is {channel}-{date}.log', file_exists( $file ), true );
check(
	'line is [ts] [action] message | {json}',
	(bool) preg_match( '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] \[cron\] Batch finished\. \| \{"processed":12\}$/', trim( file_get_contents( $file ) ) ),
	true
);

$log->add( '', 'no action label', array(), 'audit' );
$audit = file( $log->dir() . 'audit-' . $today . '.log', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
check(
	'empty action omits the action segment',
	(bool) preg_match( '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] no action label$/', end( $audit ) ),
	true
);

$log->add( 'x', 'no context' );
$lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
check( 'empty context omits the pipe', strpos( end( $lines ), '|' ), false );

// --------------------------------------------------------------- hardening --
echo "hardening\n";
check( 'index.php dropped in the log dir', file_exists( $log->dir() . 'index.php' ), true );
check( '.htaccess dropped in the log dir', file_exists( $log->dir() . '.htaccess' ), true );

$log->add( 'x', 'traversal', array(), '../../escaped' );
check( 'traversal channel is confined to the log dir', file_exists( $log->dir() . 'escaped-' . $today . '.log' ), true );
check( 'nothing was written above the log dir', glob( dirname( rtrim( $log->dir(), '/' ) ) . '/escaped-*.log' ), array() );

// ------------------------------------------------------ counting and listing --
echo "counting and listing\n";
check( 'count() counts entries, not files', $log->count(), 2 );
check( 'count() is per channel', $log->count( 'audit' ), 1 );

$meta = $log->files();
check( 'files() returns one file for the default channel', count( $meta ), 1 );
check( 'files() keys', array_keys( $meta[0] ), array( 'file', 'name', 'date', 'count' ) );
check( 'files() reports the date', $meta[0]['date'], $today );
check( 'files() reports the entry count', $meta[0]['count'], 2 );
check( 'files("*") spans every channel', count( $log->files( '*' ) ), 3 );

check( 'read() returns newest entry first', (bool) preg_match( '/no context$/', $log->read()[0] ), true );
check( 'read() honours the limit', count( $log->read( null, 1 ) ), 1 );

// ------------------------------------------------------------------- error --
echo "error()\n";
$enabled = false;
$log->error( 'send', 'unconditional' );
check( 'error() does not write to file while disabled', substr_count( file_get_contents( $file ), 'unconditional' ), 0 );

$enabled = true;
$log->error( 'send', 'unconditional' );
check( 'error() writes to file while enabled', substr_count( file_get_contents( $file ), 'unconditional' ), 1 );

// ------------------------------------------------------------------- clear --
echo "clear()\n";
$log->clear();
check( 'clear() empties the default channel', file_exists( $file ), false );
check( 'clear() leaves other channels alone', file_exists( $log->dir() . 'audit-' . $today . '.log' ), true );

$log->clear( '*' );
check( 'clear("*") empties every channel', count( $log->files( '*' ) ), 0 );

// ------------------------------------------------------------------ config --
echo "config\n";
$custom = WpLogs_Registry::logger( 'custom', array( 'dir' => $base . '/elsewhere', 'enabled' => true ) );
check( 'a trailing slash is added to dir', substr( $custom->dir(), -1 ), '/' );
$custom->add( 'a', 'b' );
check( 'writes to the configured dir', file_exists( $base . '/elsewhere/custom-' . $today . '.log' ), true );

$defaulted = WpLogs_Registry::logger( 'defaulted', array( 'enabled' => true ) );
check( 'dir defaults to uploads/{slug}-logs/', $defaulted->dir(), $base . '/uploads/defaulted-logs/' );

exec( 'rm -rf ' . escapeshellarg( $base ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
