<?php
/**
 * Test suite for lauzis/wp-plugin-packages.
 *
 * Dependency-free, like the package itself: pulling in PHPUnit would push that
 * resolution onto every consuming plugin. The WordPress functions the library
 * touches are stubbed below.
 */

use Lauzis\WpPackages\Notices\Notice;

define( 'WP_PLUGIN_DIR', '/srv/wp/wp-content/plugins' );
define( 'WP_CONTENT_DIR', '/srv/wp/wp-content' );

$base = sys_get_temp_dir() . '/wp-packages-tests-' . getmypid();
if ( is_dir( $base ) ) {
	exec( 'rm -rf ' . escapeshellarg( $base ) );
}

$GLOBALS['test_base'] = $base;
$GLOBALS['options']   = array();
$GLOBALS['user_meta'] = array();
$GLOBALS['hooks']     = array();
$GLOBALS['enqueued']  = array();
$GLOBALS['localized'] = array();
$GLOBALS['user_id']   = 1;
$GLOBALS['caps']      = true;

function wp_mkdir_p( $dir ) { return is_dir( $dir ) || mkdir( $dir, 0777, true ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_upload_dir() { return array( 'basedir' => $GLOBALS['test_base'] . '/uploads' ); }
function plugins_url() { return 'https://example.test/wp-content/plugins'; }
function content_url() { return 'https://example.test/wp-content'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function did_action( $hook ) { return 0; }
function get_option( $k, $d = false ) { return $GLOBALS['options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
function get_user_meta( $u, $k, $single = false ) { return $GLOBALS['user_meta'][ $u ][ $k ] ?? ( $single ? '' : array() ); }
function update_user_meta( $u, $k, $v ) { $GLOBALS['user_meta'][ $u ][ $k ] = $v; return true; }
function get_current_user_id() { return $GLOBALS['user_id']; }
function current_user_can( $c ) { return $GLOBALS['caps']; }
function esc_attr( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $s, $d = 'default' ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function __( $s, $d = 'default' ) { $GLOBALS['translated'][] = array( $s, $d ); return $s; }
// Approximates wp_kses_post()'s allow-list: block and inline markup through, scripts out.
function wp_kses_post( $s ) { return strip_tags( $s, '<a><strong><em><code><br><p><ul><ol><li><span><h2><h3>' ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $k ) ); }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function wp_create_nonce( $a ) { return 'nonce-' . $a; }
function wp_enqueue_style( $h, $src = '', $d = array(), $v = null ) { $GLOBALS['enqueued'][ $h ] = $src; }
function wp_enqueue_script( $h, $src = '', $d = array(), $v = null, $f = false ) { $GLOBALS['enqueued'][ $h ] = $src; }
function wp_localize_script( $h, $name, $data ) { $GLOBALS['localized'][ $name ] = $data; }
function check_ajax_referer( $action, $field = false ) { return true; }

/** Models wp_send_json_*(), which terminate the request. */
class WpJsonHalt extends Exception {}
function wp_send_json_error( $message = '', $code = null ) { throw new WpJsonHalt( is_string( $message ) ? $message : 'error' ); }
function wp_send_json_success( $data = null ) { throw new WpJsonHalt( 'success' ); }

require __DIR__ . '/fake-carbon-fields.php';
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

function render( $notices ) {
	ob_start();
	$notices->render();

	return ob_get_clean();
}

/**
 * Invokes the dismissal handler the way admin-ajax would, returning whatever
 * the handler responded with instead of terminating.
 */
function dismiss( $notices, $id ) {
	$_POST['notification_id'] = $id;

	try {
		$notices->handle_dismiss();
	} catch ( WpJsonHalt $e ) {
		return $e->getMessage();
	}

	return null;
}

$today = gmdate( 'Y-m-d' );

// =========================================================== Logs component ==
$enabled = false;
$log     = WpPackages_Registry::logger( 'demo', array( 'enabled' => function () use ( &$enabled ) { return $enabled; } ) );

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
$custom = WpPackages_Registry::logger( 'custom', array( 'dir' => $base . '/elsewhere', 'enabled' => true ) );
check( 'a trailing slash is added to dir', substr( $custom->dir(), -1 ), '/' );
$custom->add( 'a', 'b' );
check( 'writes to the configured dir', file_exists( $base . '/elsewhere/custom-' . $today . '.log' ), true );

$defaulted = WpPackages_Registry::logger( 'defaulted', array( 'enabled' => true ) );
check( 'dir defaults to uploads/{slug}-logs/', $defaulted->dir(), $base . '/uploads/defaulted-logs/' );


// ======================================================== Notices component ==
$always = function () { return true; };
$n      = WpPackages_Registry::notices( 'splecheh', array( 'screen' => $always ) );

echo "render\n";
$n->add( new Notice( 'missing-lib', 'The spell-check library is <strong>missing</strong>.', 'error', Notice::ONCE ) );
$html = render( $n );
check( 'renders a WordPress notice', (bool) strpos( $html, 'class="notice notice-error wp-notices-notice"' ), true );

$html = render( $n );
check( 'renders a WordPress notice', (bool) strpos( $html, 'class="notice notice-error wp-notices-notice"' ), true );
check( 'carries the id',   (bool) strpos( $html, 'data-wp-notices-id="missing-lib"' ), true );
check( 'carries the mode', (bool) strpos( $html, 'data-wp-notices-mode="once"' ), true );
check( 'keeps safe markup', (bool) strpos( $html, '<strong>missing</strong>' ), true );
check( 'renders a dismiss button', (bool) strpos( $html, 'notice-dismiss' ), true );

$evil = WpPackages_Registry::notices( 'evil', array( 'screen' => $always ) );
$evil->add( new Notice( 'xss', 'ok <script>alert(1)</script>', 'info' ) );
check( 'strips script tags from the message', false === strpos( render( $evil ), '<script>' ), true );

$bad = new Notice( 'x', 'y', 'not-a-type', 'not-a-mode' );
check( 'unknown type falls back to info', $bad->type, 'info' );
check( 'unknown mode falls back to once', $bad->mode, 'once' );

// ------------------------------------------------------------------ scoping --
echo "screen scoping\n";
$scoped = WpPackages_Registry::notices( 'scoped', array( 'screen' => function () { return false; } ) );
$scoped->add( new Notice( 'hidden', 'should not render' ) );
check( 'renders nothing off-screen', render( $scoped ), '' );

$GLOBALS['get'] = array();
$dflt = WpPackages_Registry::notices( 'mawiblah' );
$dflt->add( new Notice( 'setup', 'setup needed' ) );
check( 'default scoping hides notices with no page param', render( $dflt ), '' );
$_GET['page'] = 'mawiblah-settings';
check( 'default scoping shows on the plugin page', (bool) strpos( render( $dflt ), 'setup needed' ), true );
$_GET['page'] = 'some-other-plugin';
check( 'default scoping hides on other pages', render( $dflt ), '' );
unset( $_GET['page'] );

// ----------------------------------------------------------- dismissal: once --
echo "dismissal — option store, once\n";
check( 'dismissal succeeds', dismiss( $n, 'missing-lib' ), 'success' );
check( 'dismissal saved to an option', isset( $GLOBALS['options']['splecheh_dismissed_notices']['missing-lib'] ), true );
check( 'not saved to user meta', isset( $GLOBALS['user_meta'][1]['splecheh_dismissed_notices'] ), false );
check( 'dismissed notice no longer renders', render( $n ), '' );

$n->reset();
check( 'reset() brings it back', (bool) strpos( render( $n ), 'missing-lib' ), true );

// -------------------------------------------------------- dismissal: version --
echo "dismissal — user store, per version\n";
$v = WpPackages_Registry::notices( 'mawiblah_v', array( 'store' => 'user', 'version' => '1.0.28', 'screen' => $always ) );
$v->add( new Notice( 'setup', 'setup needed', 'warning', Notice::VERSION ) );
check( 'renders before dismissal', (bool) strpos( render( $v ), 'setup needed' ), true );

check( 'dismissal succeeds', dismiss( $v, 'setup' ), 'success' );
check( 'dismissal saved to user meta', $GLOBALS['user_meta'][1]['mawiblah_v_dismissed_notices']['setup'], '1.0.28' );
check( 'not saved to an option', isset( $GLOBALS['options']['mawiblah_v_dismissed_notices'] ), false );
check( 'hidden for the dismissed version', render( $v ), '' );

$v2 = new \Lauzis\WpPackages\Notices\Notices( 'mawiblah_v', array( 'store' => 'user', 'version' => '1.0.29', 'screen' => $always ) );
$v2->add( new Notice( 'setup', 'setup needed', 'warning', Notice::VERSION ) );
check( 'shows again after a version bump', (bool) strpos( render( $v2 ), 'setup needed' ), true );

$GLOBALS['user_id'] = 2;
check( 'user-store dismissal does not leak to another user', (bool) strpos( render( $v ), 'setup needed' ), true );
$GLOBALS['user_id'] = 1;

// -------------------------------------------------------- dismissal: session --
echo "dismissal — session\n";
$s = WpPackages_Registry::notices( 'sessiony', array( 'screen' => $always ) );
$s->add( new Notice( 'transient', 'just this once', 'info', Notice::SESSION ) );
$GLOBALS['options']['sessiony_dismissed_notices'] = array( 'transient' => true );
check( 'session notices ignore stored dismissals', (bool) strpos( render( $s ), 'just this once' ), true );

// ------------------------------------------------------------------ security --
echo "security\n";
$GLOBALS['caps'] = false;
check( 'dismissal requires the capability', dismiss( $n, 'missing-lib' ), 'Insufficient permissions' );
$GLOBALS['caps'] = true;

check( 'empty notification id is rejected', dismiss( $n, '' ), 'Invalid notification ID' );

// -------------------------------------------------------------------- assets --
echo "assets\n";
$a = new \Lauzis\WpPackages\Notices\Assets( '/srv/wp/wp-content/plugins/splecheh/vendor/lauzis/wp-plugin-packages' );
check(
	'vendor path maps onto a plugin URL',
	$a->url( 'notices.css' ),
	'https://example.test/wp-content/plugins/splecheh/vendor/lauzis/wp-plugin-packages/assets/notices.css'
);

$mu = new \Lauzis\WpPackages\Notices\Assets( '/srv/wp/wp-content/mu-plugins/thing/vendor/lauzis/wp-plugin-packages' );
check(
	'paths outside the plugin dir fall back to content_url',
	$mu->url( 'toasts.css' ),
	'https://example.test/wp-content/mu-plugins/thing/vendor/lauzis/wp-plugin-packages/assets/toasts.css'
);

$override = new \Lauzis\WpPackages\Notices\Assets( '/anywhere', 'https://cdn.test/a' );
check( 'explicit assets_url wins', $override->url( 'notices.css' ), 'https://cdn.test/a/notices.css' );

$n->enqueue();
check( 'enqueues the stylesheet', isset( $GLOBALS['enqueued']['wp-notices'] ), true );
check( 'localises per-plugin config', $GLOBALS['localized']['wpNoticessplecheh']['action'], 'splecheh_dismiss_notice' );
check( 'nonce matches the action', $GLOBALS['localized']['wpNoticessplecheh']['nonce'], 'nonce-splecheh_dismiss_notice' );

WpPackages_Registry::toasts( 'rest-in-sync', array( 'timeout' => 3000 ) )->enqueue();
check( 'toast assets enqueued', isset( $GLOBALS['enqueued']['wp-notices-toasts'] ), true );
check( 'toast timeout is configurable', $GLOBALS['localized']['wpNoticesToastConfig']['timeout'], 3000 );

// ---------------------------------------------------------------------- boot --
echo "boot\n";
$b = WpPackages_Registry::notices( 'booty', array( 'screen' => $always ) );
$b->boot();
$b->boot();
check( 'admin_notices hooked once', count( $GLOBALS['hooks']['admin_notices'] ), 1 );
check( 'ajax handler hooked', isset( $GLOBALS['hooks']['wp_ajax_booty_dismiss_notice'] ), true );
check( 'slug dashes become underscores in hook names', ( WpPackages_Registry::notices( 'rest-in-sync' ) )->action(), 'rest_in_sync_dismiss_notice' );


// ======================================================= Settings component ==
echo "settings — schema loading\n";

use Lauzis\WpPackages\Settings\Schema;

$fixture = __DIR__ . '/fixtures/plugin.json';

$plugin = Schema::load( $fixture, array( 'prefix' => 'demo_', 'domain' => 'demo' ) );
check( 'tabs become sections', count( $plugin ), 2 );
check( 'section id is prefixed', $plugin[0]['id'], 'demo_general' );
check( 'section keeps its domain', $plugin[0]['domain'], 'demo' );
check( 'field id is prefixed', $plugin[0]['fields'][0]['id'], 'demo_post_types' );
check( 'bare id is retained', $plugin[0]['fields'][0]['bare'], 'post_types' );

$advanced = $plugin[1]['fields'];
$command  = $advanced[1];
check( 'conditional logic field ref is prefixed', $command['conditional_logic'][0]['field'], 'demo_mode' );
check( 'conditional logic gets a default compare', $command['conditional_logic'][0]['compare'], '=' );

$keywords = $advanced[3];
check( 'complex sub-fields are NOT prefixed', $keywords['fields'][0]['id'], 'keyword' );
check( 'nested complex recurses', $keywords['fields'][1]['fields'][0]['id'], 'variation' );

// map is applied before the prefix, so a legacy key survives adoption.
$mapped = Schema::load( $fixture, array( 'prefix' => 'maw-', 'map' => array( 'post_types' => 'legacy-types' ) ) );
check( 'map replaces the id before prefixing', $mapped[0]['fields'][0]['id'], 'maw-legacy-types' );
check( 'unmapped ids are untouched', $mapped[0]['fields'][1]['id'], 'maw-language' );

$bad = false;
try { Schema::load( __DIR__ . '/fixtures/nope.json' ); } catch ( \RuntimeException $e ) { $bad = true; }
check( 'a missing schema throws', $bad, true );

echo "settings — string walking\n";
$found = array();
Schema::walk_strings( $plugin, function ( $text, $domain ) use ( &$found ) { $found[ $text ] = $domain; } );
check( 'section titles collected', isset( $found['General'] ), true );
check( 'section descriptions collected', isset( $found['Core behaviour.'] ), true );
check( 'field titles collected', isset( $found['Batch Size'] ), true );
check( 'help text collected', isset( $found['Which post types to process.'] ), true );
check( 'option labels collected', isset( $found['Hosted API'] ), true );
check( 'html collected', isset( $found['<p>Careful.</p>'] ), true );
check( 'nested field titles collected', isset( $found['Variation'] ), true );
check( 'default values NOT collected', isset( $found['50'] ), false );
check( 'callback refs NOT collected', isset( $found['@callback:default_language'] ), false );
check( 'strings carry the fragment domain', $found['General'], 'demo' );

echo "settings — composition and rendering\n";
$s = WpPackages_Registry::settings( 'demo', array( 'title' => 'Demo Settings', 'page_parent' => 'demo-root' ) );
$s->callback( 'public_post_types', function () { return array( 'post' => 'Posts', 'page' => 'Pages' ); } );
$s->callback( 'default_language', function () { return 'lv'; } );
$s->register( $fixture, array( 'prefix' => 'demo_', 'domain' => 'demo' ) );
$s->register( dirname( __DIR__ ) . '/settings/logs.json', array( 'prefix' => 'demo_', 'domain' => 'wp-plugin-packages' ) );

check( 'fragments merge in order', count( $s->sections() ), 3 );
check( 'component section is last', $s->sections()[2]['id'], 'demo_logging' );
check( 'component section keeps the package domain', $s->sections()[2]['domain'], 'wp-plugin-packages' );
check( 'component field lands on the plugin key', $s->key( 'logs_enabled' ), 'demo_logs_enabled' );

$s->render();
$c = \Carbon_Fields\Container::$last;

check( 'container is theme_options', $c->type, 'theme_options' );
check( 'container title', $c->title, 'Demo Settings' );
check( 'page parent passed through', $c->page_parent, 'demo-root' );
check( 'one tab per section', count( $c->tabs ), 3 );
check( 'tab titles translated', $c->tabs[0]['title'], 'General' );

check( 'set field built', $c->find( 'demo_post_types' )->type, 'set' );
check( 'callback options resolved', $c->find( 'demo_post_types' )->options, array( 'post' => 'Posts', 'page' => 'Pages' ) );
check( 'help text applied', $c->find( 'demo_post_types' )->help_text, 'Which post types to process.' );
check( 'callback default resolved', $c->find( 'demo_language' )->default_value, 'lv' );
check( 'literal default kept', $c->find( 'demo_batch_size' )->default_value, '50' );
check( 'attributes applied', $c->find( 'demo_batch_size' )->attributes, array( 'type' => 'number', 'min' => '1' ) );
check( 'static options kept', $c->find( 'demo_mode' )->options, array( 'commandline' => 'Commandline', 'api' => 'Hosted API' ) );
check( 'conditional logic applied', $c->find( 'demo_command' )->conditional_logic[0]['field'], 'demo_mode' );
check( 'html field carries markup', $c->find( 'demo_notice' )->html, '<p>Careful.</p>' );
check( 'complex nests one level', count( $c->find( 'demo_keywords' )->children ), 2 );
check( 'complex nests two levels', $c->find( 'variation' )->type, 'text' );
check( 'section description becomes an html field', $c->find( 'demo_general_description' )->html, '<p>Core behaviour.</p>' );
check( 'component field rendered', $c->find( 'demo_logs_enabled' )->type, 'checkbox' );

echo "settings — flat mode\n";
$flat = new \Lauzis\WpPackages\Settings\Settings( 'flatty', array( 'title' => 'Flat', 'mode' => 'flat' ) );
$flat->register( dirname( __DIR__ ) . '/settings/logs.json', array( 'prefix' => 'flatty_', 'domain' => 'wp-plugin-packages' ) );
$flat->render();
$fc = \Carbon_Fields\Container::$last;
check( 'flat mode uses no tabs', count( $fc->tabs ), 0 );
check( 'flat mode emits a separator per section', $fc->fields[0]->type, 'separator' );
check( 'separator carries the section title', $fc->fields[0]->label, 'Logging' );

echo "settings — reading values\n";
$GLOBALS['options']['_demo_logs_enabled'] = '1';
check( 'get() resolves prefix and reads storage', $s->get( 'logs_enabled' ), '1' );
check( 'get() falls back to the schema default first', $s->get( 'batch_size' ), '50' );
check( 'default_for() exposes the schema default', $s->default_for( 'batch_size' ), '50' );
check( 'then to the caller default when the schema has none', $s->get( 'language', 'fallback' ), 'lv' );
check( 'and to the caller default when neither exists', $s->get( 'command', 'none' ), 'none' );
check( 'get() returns default for unknown ids', $s->get( 'no_such_field', 'x' ), 'x' );
check( 'key() returns null for unknown ids', $s->key( 'no_such_field' ), null );

echo "settings — callback safety\n";
$orphan = new \Lauzis\WpPackages\Settings\Settings( 'orphan' );
check( 'unregistered callback resolves to null, not a fatal', $orphan->resolve( '@callback:missing' ), null );
check( 'literals pass through resolve()', $orphan->resolve( 'plain' ), 'plain' );
check( 'render() is idempotent', ( $s->render() === $s ), true );


echo "settings — defaults, sprintf args, html callbacks\n";
$ov = new \Lauzis\WpPackages\Settings\Settings( 'ov', array( 'title' => 'Ov' ) );
$ov->register( dirname( __DIR__ ) . '/settings/logs.json', array(
    'prefix'   => 'ov_',
    'domain'   => 'wp-plugin-packages',
    'defaults' => array( 'logs_enabled' => true ),
) );
$ov->render();
$oc = \Carbon_Fields\Container::$last;
check( 'defaults override the schema value', $oc->find( 'ov_logs_enabled' )->default_value, true );

$plain = new \Lauzis\WpPackages\Settings\Settings( 'plain', array( 'title' => 'Plain' ) );
$plain->register( dirname( __DIR__ ) . '/settings/logs.json', array( 'prefix' => 'plain_' ) );
$plain->render();
check( 'without an override the schema default stands', \Carbon_Fields\Container::$last->find( 'plain_logs_enabled' )->default_value, null );

$sp = new \Lauzis\WpPackages\Settings\Settings( 'sp', array( 'title' => 'Sp' ) );
$sp->callback( 'locale', function () { return 'lv'; } );
$sp->callback( 'test_field', function () { return '<p>rendered late</p>'; } );
$sp->register( __DIR__ . '/fixtures/dynamic.json', array( 'prefix' => 'sp_', 'domain' => 'sp' ) );
$sp->render();
$sc = \Carbon_Fields\Container::$last;
check( 'sprintf args fill the translated string', $sc->find( 'sp_language' )->help_text, 'Defaults to the site language (lv).' );
check( 'html callback is passed as a callable, not its result', is_callable( $sc->find( 'sp_widget' )->html ), true );
check( 'and it renders when invoked', call_user_func( $sc->find( 'sp_widget' )->html ), '<p>rendered late</p>' );
check( 'a missing html callback leaves the field empty', $sc->find( 'sp_orphan' )->html, null );


echo "logs — reading its own setting\n";
$auto = WpPackages_Registry::logger( 'autolog' );
check( 'no setting registered means off', $auto->isEnabled(), false );

WpPackages_Registry::settings( 'autolog' )->register(
    dirname( __DIR__ ) . '/settings/logs.json',
    array( 'prefix' => 'autolog_', 'domain' => 'wp-plugin-packages' )
);
$GLOBALS['options']['_autolog_logs_enabled'] = '1';
check( 'reads logs_enabled from its own schema', $auto->isEnabled(), true );
$GLOBALS['options']['_autolog_logs_enabled'] = '';
check( 'and follows it being switched off', $auto->isEnabled(), false );

// A plugin that mapped the field onto a legacy key still resolves by bare id.
WpPackages_Registry::settings( 'mapped' )->register(
    dirname( __DIR__ ) . '/settings/logs.json',
    array( 'prefix' => 'ris_', 'domain' => 'wp-plugin-packages', 'map' => array( 'logs_enabled' => 'enable_logging' ) )
);
$GLOBALS['options']['_ris_enable_logging'] = '1';
check( 'a mapped field is still found by its bare id', WpPackages_Registry::logger( 'mapped' )->isEnabled(), true );

check( 'an explicit enabled config still wins', WpPackages_Registry::logger( 'explicit', array( 'enabled' => true ) )->isEnabled(), true );

// Logging can happen before carbon_fields_register_fields has run, so a plugin
// whose logging defaults to on must not report off in that window.
check( 'enabled_default applies before the schema is registered', WpPackages_Registry::logger( 'early', array( 'enabled_default' => true ) )->isEnabled(), true );
WpPackages_Registry::settings( 'early' )->register(
    dirname( __DIR__ ) . '/settings/logs.json',
    array( 'prefix' => 'early_', 'domain' => 'wp-plugin-packages' )
);
// An unchecked checkbox stores an empty string; that is a real answer and must
// not be overridden by the default.
$GLOBALS['options']['_early_logs_enabled'] = '';
check( 'a stored empty value beats the default', WpPackages_Registry::logger( 'early' )->isEnabled(), false );
unset( $GLOBALS['options']['_early_logs_enabled'] );
check( 'an absent option still falls back', WpPackages_Registry::logger( 'early' )->isEnabled(), true );

// ============================================================ version gate ==
echo "version gate\n";
check( 'components share one registry', WpPackages_Registry::logger( 'demo' ) === $log, true );
check( 'a slug gets one logger', WpPackages_Registry::logger( 'demo' ) === WpPackages_Registry::logger( 'demo' ), true );
check( 'a slug gets one notice manager', WpPackages_Registry::notices( 'splecheh' ) === $n, true );
check( 'distinct slugs get distinct instances', WpPackages_Registry::logger( 'other' ) !== $log, true );
check( 'logger and notices are separate buckets', WpPackages_Registry::notices( 'demo' ) !== WpPackages_Registry::logger( 'demo' ), true );

// Registering other copies must not disturb anything already resolved, so
// these assertions come last.
$boot_version = WpPackages_Registry::active_version();
check( 'active root is this copy', WpPackages_Registry::active_root(), dirname( __DIR__ ) );

// The version registered in bootstrap.php and the asset cache-buster must move
// together, or a newer template could load an older stylesheet.
preg_match( "/register\\(\\s*'([^']+)'/", file_get_contents( dirname( __DIR__ ) . '/bootstrap.php' ), $m );
check( 'bootstrap registers its own declared version', $boot_version, $m[1] );
check( 'Assets::VERSION is in step with it', \Lauzis\WpPackages\Notices\Assets::VERSION, $boot_version );

WpPackages_Registry::register( '0.9.0', '/nonexistent/older.php', '/nonexistent' );
check( 'an older copy does not win', WpPackages_Registry::active_version(), $boot_version );

WpPackages_Registry::register( '1.10.0', dirname( __DIR__ ) . '/src/load.php', '/newer' );
check( 'version compare is semantic, not lexical', WpPackages_Registry::active_version(), '1.10.0' );
check( 'assets follow the winning copy', WpPackages_Registry::active_root(), '/newer' );

exec( 'rm -rf ' . escapeshellarg( $base ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
