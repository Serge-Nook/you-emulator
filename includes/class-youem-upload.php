<?php
/**
 * Chunked ROM upload with resume.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores chunks in tmp/ and assembles them into roms/.
 */
class Youem_Upload {

	const MAX_CHUNKS = 20000;

	/**
	 * Hooks.
	 */
	public static function init(): void {}

	/**
	 * Maximum ROM size in bytes.
	 */
	public static function max_size(): int {
		/**
		 * Filters the maximum ROM size (bytes). Default 4 GB - 1.
		 *
		 * @param int $max Bytes.
		 */
		return (int) apply_filters( 'youem_max_rom_size', Youem_Zip::MAX_SIZE );
	}

	/**
	 * Effective chunk size in bytes, limited by PHP upload limits.
	 */
	public static function chunk_size(): int {
		$wanted = (int) Youem_Settings::get( 'chunk_mb' ) * MB_IN_BYTES;
		$limit  = (int) wp_max_upload_size();
		if ( $limit > 0 ) {
			$wanted = min( $wanted, max( 256 * KB_IN_BYTES, $limit - 64 * KB_IN_BYTES ) );
		}
		return max( 256 * KB_IN_BYTES, $wanted );
	}

	/**
	 * Validates an upload ID.
	 *
	 * @param string $id ID.
	 */
	public static function valid_id( string $id ): bool {
		return (bool) preg_match( '/^[a-z0-9-]{8,64}$/', $id );
	}

	/**
	 * Temp directory of an upload.
	 *
	 * @param string $id Upload ID.
	 */
	private static function dir( string $id ): string {
		return Youem_Storage::dir( 'tmp' ) . '/' . get_current_user_id() . '-' . $id;
	}

	/**
	 * Indexes of received chunks.
	 *
	 * @param string $id Upload ID.
	 * @return int[]
	 */
	public static function received( string $id ): array {
		$dir = self::dir( $id );
		if ( ! is_dir( $dir ) ) {
			return array();
		}
		$out = array();
		foreach ( (array) glob( $dir . '/*.part' ) as $file ) {
			$out[] = (int) basename( (string) $file, '.part' );
		}
		sort( $out );
		return $out;
	}

	/**
	 * Stores one chunk.
	 *
	 * @param string $id    Upload ID.
	 * @param int    $index Chunk index.
	 * @param int    $total Total chunks.
	 * @param string $tmp   Uploaded temp file.
	 * @return true|WP_Error
	 */
	public static function put_chunk( string $id, int $index, int $total, string $tmp ) {
		if ( ! self::valid_id( $id ) || $index < 0 || $total < 1 || $total > self::MAX_CHUNKS || $index >= $total ) {
			return new WP_Error( 'youem_bad_chunk', __( 'Invalid chunk parameters.', 'you-emulator' ), array( 'status' => 400 ) );
		}
		if ( ! is_uploaded_file( $tmp ) && ! apply_filters( 'youem_allow_non_uploaded_chunk', false, $tmp ) ) {
			return new WP_Error( 'youem_bad_chunk', __( 'Chunk upload failed.', 'you-emulator' ), array( 'status' => 400 ) );
		}
		if ( filesize( $tmp ) > self::chunk_size() + MB_IN_BYTES ) {
			return new WP_Error( 'youem_chunk_size', __( 'Chunk is too large.', 'you-emulator' ), array( 'status' => 413 ) );
		}
		$dir = self::dir( $id );
		wp_mkdir_p( $dir );
		$target = $dir . '/' . $index . '.part';
		$moved  = is_uploaded_file( $tmp ) ? move_uploaded_file( $tmp, $target ) : copy( $tmp, $target );
		if ( ! $moved ) {
			return new WP_Error( 'youem_write', __( 'Could not store the chunk.', 'you-emulator' ), array( 'status' => 500 ) );
		}
		touch( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch
		return true;
	}

	/**
	 * Concatenates chunks of an upload into one file.
	 *
	 * @param string $id    Upload ID.
	 * @param int    $total Total chunks.
	 * @return string|WP_Error Path of the assembled file.
	 */
	private static function concat( string $id, int $total ) {
		if ( ! self::valid_id( $id ) || $total < 1 || $total > self::MAX_CHUNKS ) {
			return new WP_Error( 'youem_bad_upload', __( 'Invalid upload.', 'you-emulator' ), array( 'status' => 400 ) );
		}
		$dir  = self::dir( $id );
		$size = 0;
		for ( $i = 0; $i < $total; $i++ ) {
			if ( ! is_file( $dir . '/' . $i . '.part' ) ) {
				/* translators: %d: chunk number. */
				return new WP_Error( 'youem_missing_chunk', sprintf( __( 'Chunk %d is missing, resume the upload.', 'you-emulator' ), $i + 1 ), array( 'status' => 409 ) );
			}
			$size += (int) filesize( $dir . '/' . $i . '.part' );
		}
		if ( $size > self::max_size() ) {
			return new WP_Error( 'youem_too_big', __( 'The file is too large.', 'you-emulator' ), array( 'status' => 413 ) );
		}
		$out_path = $dir . '/assembled.bin';
		// phpcs:disable WordPress.WP.AlternativeFunctions
		$out = fopen( $out_path, 'wb' );
		if ( ! $out ) {
			return new WP_Error( 'youem_write', __( 'Could not assemble the file.', 'you-emulator' ), array( 'status' => 500 ) );
		}
		for ( $i = 0; $i < $total; $i++ ) {
			$in = fopen( $dir . '/' . $i . '.part', 'rb' );
			if ( ! $in ) {
				fclose( $out );
				return new WP_Error( 'youem_read', __( 'Could not read a chunk.', 'you-emulator' ), array( 'status' => 500 ) );
			}
			stream_copy_to_stream( $in, $out );
			fclose( $in );
			wp_delete_file( $dir . '/' . $i . '.part' );
		}
		fclose( $out );
		// phpcs:enable
		return $out_path;
	}

	/**
	 * Assembles uploaded files into the game ROM.
	 *
	 * @param int                              $game_id Game ID.
	 * @param string                           $console System code.
	 * @param array<int, array<string, mixed>> $files   List of {id, name, total}.
	 * @return array<string, mixed>|WP_Error ROM info.
	 */
	public static function assemble( int $game_id, string $console, array $files ) {
		if ( Youem_Game::POST_TYPE !== get_post_type( $game_id ) ) {
			return new WP_Error( 'youem_bad_game', __( 'Unknown game.', 'you-emulator' ), array( 'status' => 404 ) );
		}
		if ( ! Youem_Consoles::exists( $console ) ) {
			return new WP_Error( 'youem_bad_console', __( 'Choose a console first.', 'you-emulator' ), array( 'status' => 400 ) );
		}
		if ( ! $files || count( $files ) > 64 ) {
			return new WP_Error( 'youem_no_files', __( 'No files to assemble.', 'you-emulator' ), array( 'status' => 400 ) );
		}
		$parts = array();
		foreach ( $files as $file ) {
			$name = wp_basename( str_replace( '\\', '/', (string) ( $file['name'] ?? '' ) ) );
			if ( '' === $name || ! Youem_Validator::extension_allowed( $console, $name ) ) {
				/* translators: %s: file name. */
				return new WP_Error( 'youem_bad_ext', sprintf( __( 'File type of "%s" is not supported for this console.', 'you-emulator' ), $name ), array( 'status' => 415 ) );
			}
			$path = self::concat( (string) ( $file['id'] ?? '' ), (int) ( $file['total'] ?? 0 ) );
			if ( is_wp_error( $path ) ) {
				return $path;
			}
			$parts[ $name ] = $path;
		}

		$dir = Youem_Storage::dir( 'roms' ) . '/' . Youem_Consoles::term_slug( $console );
		wp_mkdir_p( $dir );

		if ( count( $parts ) > 1 ) {
			$check = self::check_disc_set( $parts );
			if ( is_wp_error( $check ) ) {
				self::cleanup( $files );
				return $check;
			}
			$cue      = (string) $check;
			$original = pathinfo( $cue, PATHINFO_FILENAME ) . '.zip';
			$target   = Youem_Storage::unique_path( $dir, $game_id . '-' . Youem_Storage::safe_name( $original ) );
			try {
				Youem_Zip::create( $target, $parts );
			} catch ( RuntimeException $e ) {
				self::cleanup( $files );
				return new WP_Error( 'youem_zip', $e->getMessage(), array( 'status' => 500 ) );
			}
		} else {
			$original = (string) array_key_first( $parts );
			$source   = $parts[ $original ];
			$valid    = Youem_Validator::check_header( $source, $original );
			if ( is_wp_error( $valid ) ) {
				self::cleanup( $files );
				return $valid;
			}
			if ( 'cue' === strtolower( pathinfo( $original, PATHINFO_EXTENSION ) ) ) {
				self::cleanup( $files );
				return new WP_Error( 'youem_cue_alone', __( 'Upload the .cue together with all its .bin tracks (select them all at once) or as a .zip.', 'you-emulator' ), array( 'status' => 422 ) );
			}
			$target = Youem_Storage::unique_path( $dir, $game_id . '-' . Youem_Storage::safe_name( $original ) );
			if ( ! rename( $source, $target ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
				self::cleanup( $files );
				return new WP_Error( 'youem_write', __( 'Could not store the ROM.', 'you-emulator' ), array( 'status' => 500 ) );
			}
		}
		self::cleanup( $files );

		Youem_Game::delete_rom_file( $game_id );
		$relative = Youem_Storage::relative( 'roms', $target );
		$size     = (int) filesize( $target );
		update_post_meta( $game_id, '_youem_rom_path', $relative );
		update_post_meta( $game_id, '_youem_rom_name', sanitize_file_name( $original ) );
		update_post_meta( $game_id, '_youem_rom_size', $size );
		self::set_console( $game_id, $console );

		return array(
			'name' => $original,
			'size' => $size,
			'path' => $relative,
		);
	}

	/**
	 * Sets console meta and term.
	 *
	 * @param int    $game_id Game ID.
	 * @param string $console System code.
	 */
	public static function set_console( int $game_id, string $console ): void {
		update_post_meta( $game_id, '_youem_console', $console );
		$term = get_term_by( 'slug', Youem_Consoles::term_slug( $console ), Youem_Game::TAX_CON );
		if ( $term ) {
			wp_set_object_terms( $game_id, array( (int) $term->term_id ), Youem_Game::TAX_CON );
		}
	}

	/**
	 * Checks a multi-file disc set: exactly one cue, all referenced tracks present.
	 *
	 * @param array<string, string> $parts Name => path.
	 * @return string|WP_Error Cue file name.
	 */
	public static function check_disc_set( array $parts ) {
		$cues = array_values( array_filter( array_keys( $parts ), static fn( $n ) => 'cue' === strtolower( pathinfo( (string) $n, PATHINFO_EXTENSION ) ) ) );
		if ( 1 !== count( $cues ) ) {
			return new WP_Error( 'youem_cue', __( 'A multi-file upload must contain exactly one .cue sheet.', 'you-emulator' ), array( 'status' => 422 ) );
		}
		$cue     = $cues[0];
		$content = (string) file_get_contents( $parts[ $cue ] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$have    = array_map( 'strtolower', array_keys( $parts ) );
		$missing = array();
		foreach ( Youem_Validator::cue_files( $content ) as $ref ) {
			if ( ! in_array( strtolower( $ref ), $have, true ) ) {
				$missing[] = $ref;
			}
		}
		if ( $missing ) {
			/* translators: %s: list of file names. */
			return new WP_Error( 'youem_cue_missing', sprintf( __( 'The cue sheet references missing files: %s', 'you-emulator' ), implode( ', ', $missing ) ), array( 'status' => 422 ) );
		}
		return $cue;
	}

	/**
	 * Removes temp directories of uploads.
	 *
	 * @param array<int, array<string, mixed>> $files Files.
	 */
	private static function cleanup( array $files ): void {
		foreach ( $files as $file ) {
			$id = (string) ( $file['id'] ?? '' );
			if ( self::valid_id( $id ) ) {
				Youem_Storage::rrmdir( self::dir( $id ) );
			}
		}
	}
}
