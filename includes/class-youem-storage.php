<?php
/**
 * Private file storage under wp-content/uploads/you-emulator.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves and protects storage directories.
 */
class Youem_Storage {

	const AREAS = array( 'roms', 'bios', 'saves', 'tmp' );

	/**
	 * Absolute base directory without trailing slash.
	 */
	public static function base_dir(): string {
		$uploads = wp_upload_dir( null, false );
		$base    = trailingslashit( $uploads['basedir'] ) . 'you-emulator';
		/**
		 * Filters the private storage base directory.
		 *
		 * @param string $base Absolute path.
		 */
		return untrailingslashit( (string) apply_filters( 'youem_storage_dir', $base ) );
	}

	/**
	 * Absolute path of a storage area, created on demand.
	 *
	 * @param string $area One of self::AREAS.
	 */
	public static function dir( string $area ): string {
		$dir = self::base_dir() . '/' . $area;
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			self::protect( $dir );
		}
		return $dir;
	}

	/**
	 * Creates all areas and deny files.
	 */
	public static function ensure(): void {
		wp_mkdir_p( self::base_dir() );
		self::protect( self::base_dir() );
		foreach ( self::AREAS as $area ) {
			self::dir( $area );
		}
	}

	/**
	 * Writes deny-all files for Apache/IIS and an empty index.php.
	 *
	 * @param string $dir Directory.
	 */
	public static function protect( string $dir ): void {
		$files = array(
			'.htaccess'  => "# You Emulator: private storage\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\nOptions -Indexes\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'index.php'  => "<?php\n// Silence is golden.\n",
		);
		foreach ( $files as $name => $content ) {
			$path = $dir . '/' . $name;
			if ( ! file_exists( $path ) ) {
				file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
	}

	/**
	 * Resolves a path relative to an area; returns null if it escapes the area or is missing.
	 *
	 * @param string $area     Area.
	 * @param string $relative Relative path.
	 */
	public static function resolve( string $area, string $relative ): ?string {
		$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );
		if ( '' === $relative || str_contains( $relative, '..' ) ) {
			return null;
		}
		$root = realpath( self::dir( $area ) );
		$path = realpath( self::dir( $area ) . '/' . $relative );
		if ( false === $root || false === $path || ! str_starts_with( $path, $root . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) {
			return null;
		}
		return $path;
	}

	/**
	 * Makes a file name safe for storage while keeping the extension.
	 *
	 * @param string $name Original name.
	 */
	public static function safe_name( string $name ): string {
		$name = wp_basename( str_replace( '\\', '/', $name ) );
		$ext  = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		$stem = (string) pathinfo( $name, PATHINFO_FILENAME );
		$stem = preg_replace( '/[^A-Za-z0-9._-]+/', '-', remove_accents( $stem ) );
		$stem = trim( (string) $stem, '-.' );
		if ( '' === $stem ) {
			$stem = 'file';
		}
		$stem = substr( $stem, 0, 80 );
		return '' === $ext ? $stem : $stem . '.' . $ext;
	}

	/**
	 * Unique file path in a directory.
	 *
	 * @param string $dir  Directory.
	 * @param string $name Desired safe name.
	 */
	public static function unique_path( string $dir, string $name ): string {
		$name = wp_unique_filename( $dir, $name );
		return trailingslashit( $dir ) . $name;
	}

	/**
	 * Path relative to an area.
	 *
	 * @param string $area Area.
	 * @param string $path Absolute path.
	 */
	public static function relative( string $area, string $path ): string {
		$root = self::dir( $area ) . '/';
		return str_starts_with( $path, $root ) ? substr( $path, strlen( $root ) ) : wp_basename( $path );
	}

	/**
	 * Recursively removes a directory.
	 *
	 * @param string $dir Directory.
	 */
	public static function rrmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			} else {
				wp_delete_file( $item->getPathname() );
			}
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/**
	 * Removes upload temp directories older than a day.
	 */
	public static function cleanup_tmp(): void {
		$tmp = self::dir( 'tmp' );
		foreach ( (array) glob( $tmp . '/*', GLOB_ONLYDIR ) as $dir ) {
			if ( is_string( $dir ) && filemtime( $dir ) < time() - DAY_IN_SECONDS ) {
				self::rrmdir( $dir );
			}
		}
	}
}
