<?php
/**
 * ROM extension whitelist and header signatures.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates ROM files against the console registry.
 */
class Youem_Validator {

	/**
	 * Whether the extension is allowed for the console.
	 *
	 * @param string $console System code.
	 * @param string $name    File name.
	 */
	public static function extension_allowed( string $console, string $name ): bool {
		$def = Youem_Consoles::get( $console );
		if ( ! $def ) {
			return false;
		}
		$ext = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		return in_array( $ext, $def['extensions'], true );
	}

	/**
	 * Checks the file signature for formats that have one.
	 *
	 * @param string $path File path.
	 * @param string $name Original file name (for the extension).
	 * @return true|WP_Error
	 */
	public static function check_header( string $path, string $name ) {
		$ext = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		$ok  = self::header_matches( $path, $ext );
		/**
		 * Filters the ROM signature check result.
		 *
		 * @param bool   $ok   Whether the header matches.
		 * @param string $path File path.
		 * @param string $ext  Lower-case extension.
		 */
		$ok = (bool) apply_filters( 'youem_validate_rom', $ok, $path, $ext );
		if ( ! $ok ) {
			/* translators: %s: file extension. */
			return new WP_Error( 'youem_bad_header', sprintf( __( 'The file does not look like a valid .%s image (signature check failed).', 'you-emulator' ), $ext ), array( 'status' => 422 ) );
		}
		return true;
	}

	/**
	 * Signature rules.
	 *
	 * @param string $path File path.
	 * @param string $ext  Lower-case extension.
	 */
	public static function header_matches( string $path, string $ext ): bool {
		$size = (int) filesize( $path );
		switch ( $ext ) {
			case 'nes':
				return "NES\x1A" === self::read( $path, 0, 4 );
			case 'fds':
				$head = self::read( $path, 0, 16 );
				return str_starts_with( $head, "FDS\x1A" ) || str_starts_with( $head, "\x01*NINTENDO-HVC*" );
			case 'unf':
			case 'unif':
				return 'UNIF' === self::read( $path, 0, 4 );
			case 'smc':
			case 'sfc':
			case 'fig':
			case 'swc':
				return self::is_snes( $path, $size );
			case 'md':
			case 'gen':
			case 'mdx':
				return self::is_genesis( $path ) || self::is_smd( $path );
			case 'smd':
				return self::is_smd( $path ) || self::is_genesis( $path );
			case 'gba':
			case 'agb':
				return $size >= 192 && "\x96" === self::read( $path, 0xB2, 1 );
			case 'z64':
				return "\x80\x37\x12\x40" === self::read( $path, 0, 4 );
			case 'v64':
				return "\x37\x80\x40\x12" === self::read( $path, 0, 4 );
			case 'n64':
				return in_array( self::read( $path, 0, 4 ), array( "\x40\x12\x37\x80", "\x80\x37\x12\x40", "\x37\x80\x40\x12" ), true );
			case 'iso':
				return 'CD001' === self::read( $path, 0x8001, 5 ) || 'CD001' === self::read( $path, 0x9319, 5 );
			case 'exe':
				return 'PS-X EXE' === self::read( $path, 0, 8 );
			case 'cso':
				return 'CISO' === self::read( $path, 0, 4 );
			case 'pbp':
				return "\0PBP" === self::read( $path, 0, 4 );
			case 'chd':
				return 'MComprHD' === self::read( $path, 0, 8 );
			case 'zip':
				return "PK\x03\x04" === self::read( $path, 0, 4 );
			case '7z':
				return "7z\xBC\xAF\x27\x1C" === self::read( $path, 0, 6 );
			case 'cue':
				return (bool) preg_match( '/^\s*FILE\s+/mi', self::read( $path, 0, 65536 ) );
			default:
				return true;
		}
	}

	/**
	 * File names referenced by a cue sheet.
	 *
	 * @param string $cue Cue sheet contents.
	 * @return string[]
	 */
	public static function cue_files( string $cue ): array {
		preg_match_all( '/^\s*FILE\s+(?:"([^"]+)"|(\S+))/mi', $cue, $m, PREG_SET_ORDER );
		$out = array();
		foreach ( $m as $row ) {
			$out[] = wp_basename( str_replace( '\\', '/', '' !== $row[1] ? $row[1] : $row[2] ) );
		}
		return $out;
	}

	/**
	 * SNES: valid internal header (checksum pair or map mode) at LoROM/HiROM offset, with or without a 512-byte copier header.
	 *
	 * @param string $path File path.
	 * @param int    $size File size.
	 */
	private static function is_snes( string $path, int $size ): bool {
		if ( $size < 0x8000 ) {
			return false;
		}
		$copier = ( 512 === $size % 1024 ) ? 512 : 0;
		foreach ( array( 0x7FC0, 0xFFC0, 0x40FFC0 ) as $base ) {
			$hdr = self::read( $path, $copier + $base, 32 );
			if ( strlen( $hdr ) < 32 ) {
				continue;
			}
			$map  = ord( $hdr[21] );
			$comp = ord( $hdr[28] ) | ( ord( $hdr[29] ) << 8 );
			$sum  = ord( $hdr[30] ) | ( ord( $hdr[31] ) << 8 );
			if ( 0xFFFF === ( $comp ^ $sum ) || in_array( $map, array( 0x20, 0x21, 0x22, 0x23, 0x25, 0x30, 0x31, 0x32, 0x35, 0x3A ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Genesis: "SEGA" in the system type field at 0x100.
	 *
	 * @param string $path File path.
	 */
	private static function is_genesis( string $path ): bool {
		return str_contains( self::read( $path, 0x100, 16 ), 'SEGA' );
	}

	/**
	 * Interleaved SMD dump: 512-byte header with 0xAA 0xBB marker.
	 *
	 * @param string $path File path.
	 */
	private static function is_smd( string $path ): bool {
		return "\xAA\xBB" === self::read( $path, 8, 2 );
	}

	/**
	 * Reads bytes at an offset.
	 *
	 * @param string $path   File path.
	 * @param int    $offset Offset.
	 * @param int    $len    Length.
	 */
	private static function read( string $path, int $offset, int $len ): string {
		// phpcs:disable WordPress.WP.AlternativeFunctions
		$fh = fopen( $path, 'rb' );
		if ( ! $fh ) {
			return '';
		}
		fseek( $fh, $offset );
		$data = fread( $fh, $len );
		fclose( $fh );
		// phpcs:enable
		return false === $data ? '' : $data;
	}
}
