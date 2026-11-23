<?php
/**
 * Minimal streaming ZIP writer (store method, no compression).
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes uncompressed ZIP archives without loading files into memory.
 */
class Youem_Zip {

	const MAX_SIZE = 4294967295;

	/**
	 * Creates a stored ZIP from files.
	 *
	 * @param string                $target Output path.
	 * @param array<string, string> $files  Entry name => absolute source path.
	 * @throws RuntimeException On I/O failure or size overflow.
	 */
	public static function create( string $target, array $files ): void {
		// phpcs:disable WordPress.WP.AlternativeFunctions
		$out = fopen( $target, 'wb' );
		if ( ! $out ) {
			throw new RuntimeException( 'Cannot open zip for writing' );
		}
		$central = '';
		$offset  = 0;
		$count   = 0;
		$time    = self::dos_time( time() );
		foreach ( $files as $name => $path ) {
			$size = filesize( $path );
			if ( false === $size || $offset + $size > self::MAX_SIZE ) {
				fclose( $out );
				throw new RuntimeException( 'Archive too large' );
			}
			$crc    = hexdec( (string) hash_file( 'crc32b', $path ) );
			$name   = str_replace( '\\', '/', (string) $name );
			$header = pack( 'VvvvVVVVvv', 0x04034b50, 20, 0x0800, 0, $time, $crc, $size, $size, strlen( $name ), 0 ) . $name;
			fwrite( $out, $header );
			$in = fopen( $path, 'rb' );
			if ( ! $in ) {
				fclose( $out );
				throw new RuntimeException( 'Cannot read ' . esc_html( $name ) );
			}
			stream_copy_to_stream( $in, $out );
			fclose( $in );
			$central .= pack( 'VvvvvVVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $time, $crc, $size, $size, strlen( $name ), 0, 0, 0, 0, 0x20, $offset ) . $name;
			$offset  += strlen( $header ) + $size;
			++$count;
		}
		fwrite( $out, $central );
		fwrite( $out, pack( 'VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen( $central ), $offset, 0 ) );
		fclose( $out );
		// phpcs:enable
	}

	/**
	 * Lists entry names of a ZIP (central directory only).
	 *
	 * @param string $path Archive path.
	 * @return string[]
	 */
	public static function list_names( string $path ): array {
		// phpcs:disable WordPress.WP.AlternativeFunctions
		$size = filesize( $path );
		$fh   = fopen( $path, 'rb' );
		if ( ! $fh || ! $size || $size < 22 ) {
			return array();
		}
		$tail_len = (int) min( $size, 65557 );
		fseek( $fh, $size - $tail_len );
		$tail = (string) fread( $fh, $tail_len );
		$pos  = strrpos( $tail, "PK\x05\x06" );
		if ( false === $pos ) {
			fclose( $fh );
			return array();
		}
		$eocd = unpack( 'vdisk/vcdisk/ventries/vtotal/Vcdsize/Vcdoffset', substr( $tail, $pos + 4, 16 ) );
		fseek( $fh, (int) $eocd['cdoffset'] );
		$cd    = (string) fread( $fh, (int) $eocd['cdsize'] );
		$names = array();
		$p     = 0;
		$len   = strlen( $cd );
		while ( $p + 46 <= $len && substr( $cd, $p, 4 ) === "PK\x01\x02" ) {
			$h       = unpack( 'vnlen/velen/vclen', substr( $cd, $p + 28, 6 ) );
			$names[] = substr( $cd, $p + 46, (int) $h['nlen'] );
			$p      += 46 + $h['nlen'] + $h['elen'] + $h['clen'];
		}
		fclose( $fh );
		// phpcs:enable
		return $names;
	}

	/**
	 * DOS date/time.
	 *
	 * @param int $ts Unix timestamp.
	 */
	private static function dos_time( int $ts ): int {
		$d = getdate( $ts );
		return ( ( $d['year'] - 1980 ) << 25 ) | ( $d['mon'] << 21 ) | ( $d['mday'] << 16 ) | ( $d['hours'] << 11 ) | ( $d['minutes'] << 5 ) | ( $d['seconds'] >> 1 );
	}
}
