<?php
/**
 * Version gate for wp-logs.
 *
 * Every plugin that bundles this package ships its own copy of the library in
 * vendor/. Without arbitration PHP would simply use whichever copy autoloaded
 * first, so a plugin shipping a newer version could silently end up running an
 * older one — and calling a method that version does not have is a fatal.
 *
 * This class is deliberately global (not namespaced) and guarded by
 * class_exists(): the first copy to load defines it, and every later copy
 * registers against that same instance. Because an OLD copy may be the one
 * that wins the race, this API must stay backwards compatible essentially
 * forever. Keep it small, and put new behaviour in Logger instead.
 */

if ( class_exists( 'WpLogs_Registry', false ) ) {
	return;
}

class WpLogs_Registry {

	/**
	 * Absolute path to each registered copy's loader, keyed by version.
	 *
	 * @var array<string, string>
	 */
	private static $copies = array();

	/** @var bool */
	private static $booted = false;

	/**
	 * Logger instances, keyed by the caller's slug.
	 *
	 * @var array<string, mixed>
	 */
	private static $loggers = array();

	/**
	 * Announces a bundled copy of the library.
	 *
	 * @param string $version Semantic version of this copy.
	 * @param string $path    Absolute path to that copy's src/load.php.
	 */
	public static function register( $version, $path ) {
		self::$copies[ $version ] = $path;

		// Boot as early as possible so the library is available to plugins that
		// log during their own bootstrap. Late-loading plugins (those whose
		// autoloader runs after plugins_loaded) fall back to the lazy boot in
		// logger().
		if ( ! self::$booted && function_exists( 'add_action' ) && ! did_action( 'plugins_loaded' ) ) {
			add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), -9999 );
		}
	}

	/**
	 * Loads the highest registered version. Idempotent.
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}

		if ( empty( self::$copies ) ) {
			return;
		}

		$versions = array_keys( self::$copies );
		usort( $versions, 'version_compare' );
		$winner = end( $versions );

		self::$booted = true;

		require_once self::$copies[ $winner ];
	}

	/**
	 * Returns the shared logger for a plugin, creating it on first use.
	 *
	 * @param string $slug   Plugin slug — namespaces the log files and the
	 *                       error_log prefix. Also the instance cache key.
	 * @param array  $config See Lauzis\WpLogs\Logger::__construct().
	 * @return \Lauzis\WpLogs\Logger
	 */
	public static function logger( $slug, array $config = array() ) {
		self::boot();

		if ( ! isset( self::$loggers[ $slug ] ) ) {
			self::$loggers[ $slug ] = new \Lauzis\WpLogs\Logger( $slug, $config );
		}

		return self::$loggers[ $slug ];
	}

	/**
	 * The version actually in use. Useful for support/debug output.
	 *
	 * @return string|null
	 */
	public static function active_version() {
		if ( empty( self::$copies ) ) {
			return null;
		}

		$versions = array_keys( self::$copies );
		usort( $versions, 'version_compare' );

		return end( $versions );
	}
}
