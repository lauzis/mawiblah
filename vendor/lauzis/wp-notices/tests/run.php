<?php
/**
 * Test suite for lauzis/wp-notices.
 *
 * Dependency-free, like the package itself: pulling in PHPUnit would push that
 * resolution onto every consuming plugin. The WordPress functions the library
 * touches are stubbed below.
 */

define( 'WP_PLUGIN_DIR', '/srv/wp/wp-content/plugins' );
define( 'WP_CONTENT_DIR', '/srv/wp/wp-content' );

$GLOBALS['options']   = array();
$GLOBALS['user_meta'] = array();
$GLOBALS['hooks']     = array();
$GLOBALS['enqueued']  = array();
$GLOBALS['localized'] = array();
$GLOBALS['user_id']   = 1;
$GLOBALS['caps']      = true;
$GLOBALS['get']       = array();

function plugins_url() { return 'https://example.test/wp-content/plugins'; }
function content_url() { return 'https://example.test/wp-content'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function get_option( $k, $d = false ) { return $GLOBALS['options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
function get_user_meta( $u, $k, $single = false ) { return $GLOBALS['user_meta'][ $u ][ $k ] ?? ( $single ? '' : array() ); }
function update_user_meta( $u, $k, $v ) { $GLOBALS['user_meta'][ $u ][ $k ] = $v; return true; }
function get_current_user_id() { return $GLOBALS['user_id']; }
function current_user_can( $c ) { return $GLOBALS['caps']; }
function esc_attr( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $s, $d = 'default' ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function wp_kses_post( $s ) { return strip_tags( $s, '<a><strong><em><code><br>' ); }
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

use Lauzis\WpNotices\Notice;

// ------------------------------------------------------------------ registry --
echo "registry\n";
check( 'bootstrap registers this copy', WpNotices_Registry::active_version(), '1.0.0' );
WpNotices_Registry::register( '0.9.0', '/nonexistent.php', '/nonexistent' );
check( 'an older copy does not win', WpNotices_Registry::active_version(), '1.0.0' );

$always = function () { return true; };

$n = WpNotices_Registry::notices( 'splecheh', array( 'screen' => $always ) );
check( 'notices() caches per slug', WpNotices_Registry::notices( 'splecheh' ) === $n, true );
check( 'toasts() is a separate component', WpNotices_Registry::toasts( 'splecheh' ) instanceof \Lauzis\WpNotices\Toasts, true );

// -------------------------------------------------------------------- render --
echo "render\n";
$n->add( new Notice( 'missing-lib', 'The spell-check library is <strong>missing</strong>.', 'error', Notice::ONCE ) );
$html = render( $n );
check( 'renders a WordPress notice', (bool) strpos( $html, 'class="notice notice-error wp-notices-notice"' ), true );
check( 'carries the id',   (bool) strpos( $html, 'data-wp-notices-id="missing-lib"' ), true );
check( 'carries the mode', (bool) strpos( $html, 'data-wp-notices-mode="once"' ), true );
check( 'keeps safe markup', (bool) strpos( $html, '<strong>missing</strong>' ), true );
check( 'renders a dismiss button', (bool) strpos( $html, 'notice-dismiss' ), true );

$evil = WpNotices_Registry::notices( 'evil', array( 'screen' => $always ) );
$evil->add( new Notice( 'xss', 'ok <script>alert(1)</script>', 'info' ) );
check( 'strips script tags from the message', false === strpos( render( $evil ), '<script>' ), true );

$bad = new Notice( 'x', 'y', 'not-a-type', 'not-a-mode' );
check( 'unknown type falls back to info', $bad->type, 'info' );
check( 'unknown mode falls back to once', $bad->mode, 'once' );

// ------------------------------------------------------------------ scoping --
echo "screen scoping\n";
$scoped = WpNotices_Registry::notices( 'scoped', array( 'screen' => function () { return false; } ) );
$scoped->add( new Notice( 'hidden', 'should not render' ) );
check( 'renders nothing off-screen', render( $scoped ), '' );

$GLOBALS['get'] = array();
$dflt = WpNotices_Registry::notices( 'mawiblah' );
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
$v = WpNotices_Registry::notices( 'mawiblah_v', array( 'store' => 'user', 'version' => '1.0.28', 'screen' => $always ) );
$v->add( new Notice( 'setup', 'setup needed', 'warning', Notice::VERSION ) );
check( 'renders before dismissal', (bool) strpos( render( $v ), 'setup needed' ), true );

check( 'dismissal succeeds', dismiss( $v, 'setup' ), 'success' );
check( 'dismissal saved to user meta', $GLOBALS['user_meta'][1]['mawiblah_v_dismissed_notices']['setup'], '1.0.28' );
check( 'not saved to an option', isset( $GLOBALS['options']['mawiblah_v_dismissed_notices'] ), false );
check( 'hidden for the dismissed version', render( $v ), '' );

$v2 = new \Lauzis\WpNotices\Notices( 'mawiblah_v', array( 'store' => 'user', 'version' => '1.0.29', 'screen' => $always ) );
$v2->add( new Notice( 'setup', 'setup needed', 'warning', Notice::VERSION ) );
check( 'shows again after a version bump', (bool) strpos( render( $v2 ), 'setup needed' ), true );

$GLOBALS['user_id'] = 2;
check( 'user-store dismissal does not leak to another user', (bool) strpos( render( $v ), 'setup needed' ), true );
$GLOBALS['user_id'] = 1;

// -------------------------------------------------------- dismissal: session --
echo "dismissal — session\n";
$s = WpNotices_Registry::notices( 'sessiony', array( 'screen' => $always ) );
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
$a = new \Lauzis\WpNotices\Assets( '/srv/wp/wp-content/plugins/splecheh/vendor/lauzis/wp-notices' );
check(
	'vendor path maps onto a plugin URL',
	$a->url( 'notices.css' ),
	'https://example.test/wp-content/plugins/splecheh/vendor/lauzis/wp-notices/assets/notices.css'
);

$mu = new \Lauzis\WpNotices\Assets( '/srv/wp/wp-content/mu-plugins/thing/vendor/lauzis/wp-notices' );
check(
	'paths outside the plugin dir fall back to content_url',
	$mu->url( 'toasts.css' ),
	'https://example.test/wp-content/mu-plugins/thing/vendor/lauzis/wp-notices/assets/toasts.css'
);

$override = new \Lauzis\WpNotices\Assets( '/anywhere', 'https://cdn.test/a' );
check( 'explicit assets_url wins', $override->url( 'notices.css' ), 'https://cdn.test/a/notices.css' );

$n->enqueue();
check( 'enqueues the stylesheet', isset( $GLOBALS['enqueued']['wp-notices'] ), true );
check( 'localises per-plugin config', $GLOBALS['localized']['wpNoticessplecheh']['action'], 'splecheh_dismiss_notice' );
check( 'nonce matches the action', $GLOBALS['localized']['wpNoticessplecheh']['nonce'], 'nonce-splecheh_dismiss_notice' );

WpNotices_Registry::toasts( 'rest-in-sync', array( 'timeout' => 3000 ) )->enqueue();
check( 'toast assets enqueued', isset( $GLOBALS['enqueued']['wp-notices-toasts'] ), true );
check( 'toast timeout is configurable', $GLOBALS['localized']['wpNoticesToastConfig']['timeout'], 3000 );

// ---------------------------------------------------------------------- boot --
echo "boot\n";
$b = WpNotices_Registry::notices( 'booty', array( 'screen' => $always ) );
$b->boot();
$b->boot();
check( 'admin_notices hooked once', count( $GLOBALS['hooks']['admin_notices'] ), 1 );
check( 'ajax handler hooked', isset( $GLOBALS['hooks']['wp_ajax_booty_dismiss_notice'] ), true );
check( 'slug dashes become underscores in hook names', ( WpNotices_Registry::notices( 'rest-in-sync' ) )->action(), 'rest_in_sync_dismiss_notice' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
