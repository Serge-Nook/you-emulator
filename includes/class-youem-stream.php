<?php
/**
 * Binary file delivery with HTTP Range support.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Streams private files.
 */
class Youem_Stream {

	/**
	 * Parses a single Range header.
	 *
	 * @param string $header Range header value.
	 * @param int    $size   File size.
	 * @return array{0:int,1:int}|false|null Range, false if unsatisfiable, null if absent/ignored.
	 */
	public static function parse_range( string $header, int $size ) {
		if ( '' === $header || ! preg_match( '/^bytes=(\d*)-(\d*)$/', trim( $header ), $m ) ) {
			return null;
		}
		if ( '' === $m[1] && '' === $m[2] ) {
			return null;
		}
		if ( '' === $m[1] ) {
			$len = (int) $m[2];
			if ( 0 === $len ) {
				return false;
			}
			return array( max( 0, $size - $len ), $size - 1 );
		}
		$start = (int) $m[1];
		$end   = '' === $m[2] ? $size - 1 : min( (int) $m[2], $size - 1 );
		if ( $start >= $size || $start > $end ) {
			return false;
		}
		return array( $start, $end );
	}

	/**
	 * Sends a file and exits.
	 *
	 * @param string $path    Absolute path.
	 * @param string $name    Download file name.
	 * @param string $type    MIME type.
	 * @param int    $max_age Private cache lifetime.
	 */
	public static function send( string $path, string $name, string $type = 'application/octet-stream', int $max_age = 0 ): void {
		$size = (int) filesize( $path );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		$range  = self::parse_range( isset( $_SERVER['HTTP_RANGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) ) : '', $size );

		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_apache_setenv, Generic.PHP.NoSilencedErrors.Discouraged
		}
		@ini_set( 'zlib.output_compression', 'Off' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header_remove( 'Expires' );
		header_remove( 'Pragma' );
		header( 'Cache-Control: ' . ( $max_age > 0 ? 'private, max-age=' . $max_age : 'private, no-store' ) );
		header( 'Content-Type: ' . $type );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cross-Origin-Resource-Policy: same-origin' );
		header( 'Accept-Ranges: bytes' );
		header( 'Content-Disposition: inline; filename="' . str_replace( '"', '', $name ) . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', (int) filemtime( $path ) ) . ' GMT' );

		if ( false === $range ) {
			status_header( 416 );
			header( 'Content-Range: bytes */' . $size );
			exit;
		}
		$start = 0;
		$end   = $size - 1;
		if ( is_array( $range ) ) {
			[ $start, $end ] = $range;
			status_header( 206 );
			header( sprintf( 'Content-Range: bytes %d-%d/%d', $start, $end, $size ) );
		} else {
			status_header( 200 );
		}
		header( 'Content-Length: ' . ( $size > 0 ? $end - $start + 1 : 0 ) );
		if ( 'HEAD' === $method || 0 === $size ) {
			exit;
		}
		// phpcs:disable WordPress.WP.AlternativeFunctions
		$fh = fopen( $path, 'rb' );
		if ( ! $fh ) {
			exit;
		}
		fseek( $fh, $start );
		$left = $end - $start + 1;
		set_time_limit( 0 );
		while ( $left > 0 && ! feof( $fh ) && ! connection_aborted() ) {
			$buf = fread( $fh, (int) min( 1048576, $left ) );
			if ( false === $buf ) {
				break;
			}
			echo $buf; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$left -= strlen( $buf );
			flush();
		}
		fclose( $fh );
		// phpcs:enable
		exit;
	}

	/**
	 * Sends an in-memory blob and exits.
	 *
	 * @param string $data Data.
	 * @param string $type MIME type.
	 * @param string $name File name.
	 */
	public static function send_blob( string $data, string $type, string $name ): void {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: ' . $type );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: inline; filename="' . str_replace( '"', '', $name ) . '"' );
		header( 'Content-Length: ' . strlen( $data ) );
		echo $data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
