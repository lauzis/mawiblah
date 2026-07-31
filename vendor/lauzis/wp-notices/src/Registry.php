<?php
/**
 * Version gate for wp-notices.
 *
 * Every plugin that bundles this package ships its own copy in vendor/.
 * Without arbitration PHP would use whichever copy autoloaded first, so a
 * plugin shipping a newer version could silently run an older one — and
 * calling a method that version does not have is a fatal.
 *
 * This class is deliberately global (not namespaced) and guarded by
 * class_exists(): the first copy to load defines it, and every later copy
 * registers against that same instance. Because an OLD copy may be the one
 * that wins the race, this API must stay backwards compatible essentially
 * forever. Keep it small, and put new behaviour in the component classes.
 */

if ( class_exists( 'WpNotices_Registry', false ) ) {
	return;
}

class WpNotices_Registry {

	/** @var array<string, string> version => loader path */
	private static $copies = array();

	/** @var array<string, string> version => package root directory */
	private static $roots = array();

	/** @var bool */
	private static $booted = false;

	/** @var array<string, mixed> */
	private static $notices = array();

	/** @var array<string, mixed> */
	private static $toasts = array();

	/**
	 * Announces a bundled copy of the library.
	 *
	 * @param string $version Semantic version of this copy.
	 * @param string $path    Absolute path to that copy's src/load.php.
	 * @param string $root    Absolute path to that copy's package root, used to
	 *                        locate the bundled CSS/JS at runtime.
	 */
	public static function register( $version, $path, $root ) {
		self::$copies[ $version ] = $path;
		self::$roots[ $version ]  = $root;
	}

	/** Loads the highest registered version. Idempotent. */
	public static function boot() {
		if ( self::$booted || empty( self::$copies ) ) {
			return;
		}

		self::$booted = true;

		require_once self::$copies[ self::active_version() ];
	}

	/**
	 * Returns the admin-notice manager for a plugin, creating it on first use.
	 *
	 * @param string $slug   Plugin slug — namespaces the stored dismissals, the
	 *                       AJAX action and the nonce.
	 * @param array  $config See Lauzis\WpNotices\Notices::__construct().
	 * @return \Lauzis\WpNotices\Notices
	 */
	public static function notices( $slug, array $config = array() ) {
		self::boot();

		if ( ! isset( self::$notices[ $slug ] ) ) {
			$config['package_root']       = self::active_root();
			self::$notices[ $slug ]       = new \Lauzis\WpNotices\Notices( $slug, $config );
		}

		return self::$notices[ $slug ];
	}

	/**
	 * Returns the toast component for a plugin, creating it on first use.
	 *
	 * @param string $slug   Plugin slug.
	 * @param array  $config See Lauzis\WpNotices\Toasts::__construct().
	 * @return \Lauzis\WpNotices\Toasts
	 */
	public static function toasts( $slug, array $config = array() ) {
		self::boot();

		if ( ! isset( self::$toasts[ $slug ] ) ) {
			$config['package_root'] = self::active_root();
			self::$toasts[ $slug ]  = new \Lauzis\WpNotices\Toasts( $slug, $config );
		}

		return self::$toasts[ $slug ];
	}

	/**
	 * The version actually in use.
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

	/**
	 * Package root of the copy actually in use — the assets must come from the
	 * same copy as the code, or a newer template would load older CSS.
	 *
	 * @return string|null
	 */
	public static function active_root() {
		$version = self::active_version();

		return null === $version ? null : self::$roots[ $version ];
	}
}
