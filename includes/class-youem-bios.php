<?php
/**
 * BIOS files per system.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores BIOS files under bios/<system>/ and bundles them for EmulatorJS.
 */
class Youem_Bios {

	const GENERIC_EXT = array( 'bin', 'rom', 'zip', 'prx', 'img' );

	/**
	 * Directory of a system.
	 *
	 * @param string $system System code.
	 */
	private static function dir( string $system ): string {
		$dir = Youem_Storage::dir( 'bios' ) . '/' . Youem_Consoles::term_slug( $system );
		wp_mkdir_p( $dir );
		return $dir;
	}

	/**
	 * BIOS files of a system.
	 *
	 * @param string $system System code.
	 * @return array<int, array{name:string,size:int,modified:int}>
	 */
	public static function files( string $system ): array {
		if ( ! Youem_Consoles::exists( $system ) ) {
			return array();
		}
		$out = array();
		foreach ( (array) glob( self::dir( $system ) . '/*' ) as $path ) {
			$path = (string) $path;
			$name = wp_basename( $path );
			if ( ! is_file( $path ) || str_starts_with( $name, '.' ) || 'index.php' === $name || 'web.config' === $name ) {
				continue;
			}
			$out[] = array(
				'name'     => $name,
				'size'     => (int) filesize( $path ),
				'modified' => (int) filemtime( $path ),
			);
		}
		return $out;
	}

	/**
	 * Whether a BIOS file name is accepted for a system.
	 *
	 * @param string $system System code.
	 * @param string $name   File name.
	 */
	public static function name_allowed( string $system, string $name ): bool {
		$def = Youem_Consoles::get( $system );
		if ( ! $def || ! preg_match( '/^[A-Za-z0-9._-]{1,64}$/', $name ) ) {
			return false;
		}
		if ( $def['bios_files'] ) {
			return in_array( strtolower( $name ), array_map( 'strtolower', $def['bios_files'] ), true );
		}
		return in_array( strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ), self::GENERIC_EXT, true );
	}

	/**
	 * Stores an uploaded BIOS file.
	 *
	 * @param string $system System code.
	 * @param string $tmp    Uploaded temp path.
	 * @param string $name   Original name.
	 * @return true|WP_Error
	 */
	public static function store( string $system, string $tmp, string $name ) {
		$name = wp_basename( $name );
		$def  = Youem_Consoles::get( $system );
		if ( ! $def || ! self::name_allowed( $system, $name ) ) {
			$hint = $def && $def['bios_files'] ? implode( ', ', $def['bios_files'] ) : implode( ', ', self::GENERIC_EXT );
			/* translators: 1: file name, 2: allowed names or extensions. */
			return new WP_Error( 'youem_bios_name', sprintf( __( '"%1$s" is not a known BIOS file for this system. Allowed: %2$s', 'you-emulator' ), $name, $hint ) );
		}
		if ( $def['bios_files'] ) {
			$name = $def['bios_files'][ array_search( strtolower( $name ), array_map( 'strtolower', $def['bios_files'] ), true ) ];
		}
		if ( filesize( $tmp ) > 64 * MB_IN_BYTES ) {
			return new WP_Error( 'youem_bios_size', __( 'BIOS file is too large.', 'you-emulator' ) );
		}
		$target = self::dir( $system ) . '/' . $name;
		$ok     = is_uploaded_file( $tmp ) ? move_uploaded_file( $tmp, $target ) : copy( $tmp, $target );
		if ( ! $ok ) {
			return new WP_Error( 'youem_write', __( 'Could not store the BIOS file.', 'you-emulator' ) );
		}
		self::invalidate( $system );
		return true;
	}

	/**
	 * Deletes a BIOS file.
	 *
	 * @param string $system System code.
	 * @param string $name   File name.
	 */
	public static function delete( string $system, string $name ): bool {
		$path = Youem_Storage::resolve( 'bios', Youem_Consoles::term_slug( $system ) . '/' . wp_basename( $name ) );
		if ( ! $path ) {
			return false;
		}
		wp_delete_file( $path );
		self::invalidate( $system );
		return true;
	}

	/**
	 * Whether a system has at least one BIOS file.
	 *
	 * @param string $system System code.
	 */
	public static function available( string $system ): bool {
		return (bool) self::files( $system );
	}

	/**
	 * Path of a ZIP bundle with all BIOS files of a system (EmulatorJS extracts it into the system dir).
	 *
	 * @param string $system System code.
	 */
	public static function bundle( string $system ): ?string {
		$files = self::files( $system );
		if ( ! $files ) {
			return null;
		}
		$bundle = Youem_Storage::dir( 'bios' ) . '/.' . Youem_Consoles::term_slug( $system ) . '-bundle.zip';
		$newest = max( array_column( $files, 'modified' ) );
		if ( ! is_file( $bundle ) || filemtime( $bundle ) < $newest ) {
			$map = array();
			foreach ( $files as $file ) {
				$map[ $file['name'] ] = self::dir( $system ) . '/' . $file['name'];
			}
			try {
				Youem_Zip::create( $bundle, $map );
			} catch ( RuntimeException $e ) {
				return null;
			}
		}
		return $bundle;
	}

	/**
	 * Drops the cached bundle.
	 *
	 * @param string $system System code.
	 */
	private static function invalidate( string $system ): void {
		$bundle = Youem_Storage::dir( 'bios' ) . '/.' . Youem_Consoles::term_slug( $system ) . '-bundle.zip';
		if ( is_file( $bundle ) ) {
			wp_delete_file( $bundle );
		}
	}
}
