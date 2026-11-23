<?php
/**
 * Server-side save states (DB + file storage for big states).
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD for youem_saves.
 */
class Youem_Saves {

	const SLOTS          = array( 0, 1, 2, 3 );
	const FILE_THRESHOLD = 1048576;
	const MAX_SCREENSHOT = 1048576;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'deleted_user', array( __CLASS__, 'delete_for_user' ) );
	}

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'youem_saves';
	}

	/**
	 * Slot summaries for a user and game.
	 *
	 * @param int $user_id User ID.
	 * @param int $game_id Game ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function slots( int $user_id, int $game_id ): array {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT slot, state_size, screenshot_size, updated_at FROM {$table} WHERE user_id = %d AND game_id = %d ORDER BY slot", $user_id, $game_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array();
		foreach ( self::SLOTS as $slot ) {
			$out[ $slot ] = array(
				'slot'          => $slot,
				'used'          => false,
				'size'          => 0,
				'hasScreenshot' => false,
				'updatedAt'     => null,
			);
		}
		foreach ( (array) $rows as $row ) {
			$slot = (int) $row['slot'];
			if ( ! isset( $out[ $slot ] ) ) {
				continue;
			}
			$out[ $slot ] = array(
				'slot'          => $slot,
				'used'          => true,
				'size'          => (int) $row['state_size'],
				'hasScreenshot' => (int) $row['screenshot_size'] > 0,
				'updatedAt'     => gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $row['updated_at'] . ' UTC' ) ),
			);
		}
		return array_values( $out );
	}

	/**
	 * Total bytes stored by a user.
	 *
	 * @param int $user_id User ID.
	 */
	public static function usage( int $user_id ): int {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(state_size + screenshot_size), 0) FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Stores a state.
	 *
	 * @param int         $user_id    User ID.
	 * @param int         $game_id    Game ID.
	 * @param int         $slot       Slot 0..3.
	 * @param string      $state      Binary state.
	 * @param string|null $screenshot Binary image or null.
	 * @return array<string, mixed>|WP_Error Slot summary.
	 */
	public static function save( int $user_id, int $game_id, int $slot, string $state, ?string $screenshot = null ) {
		global $wpdb;
		if ( ! in_array( $slot, self::SLOTS, true ) ) {
			return new WP_Error( 'youem_bad_slot', __( 'Invalid save slot.', 'you-emulator' ), array( 'status' => 400 ) );
		}
		if ( '' === $state ) {
			return new WP_Error( 'youem_empty_state', __( 'Empty save state.', 'you-emulator' ), array( 'status' => 400 ) );
		}
		$interval = (int) Youem_Settings::get( 'save_interval' );
		$lock_key = 'youem_save_lock_' . $user_id;
		if ( $interval > 0 && get_transient( $lock_key ) ) {
			/* translators: %d: seconds. */
			return new WP_Error( 'youem_too_fast', sprintf( __( 'Please wait %d seconds between saves.', 'you-emulator' ), $interval ), array( 'status' => 429 ) );
		}
		if ( null !== $screenshot && strlen( $screenshot ) > self::MAX_SCREENSHOT ) {
			$screenshot = null;
		}
		$table    = self::table();
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, file_path, state_size, screenshot_size FROM {$table} WHERE user_id = %d AND game_id = %d AND slot = %d", $user_id, $game_id, $slot ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$old_size = $existing ? (int) $existing['state_size'] + (int) $existing['screenshot_size'] : 0;
		$new_size = strlen( $state ) + ( null === $screenshot ? 0 : strlen( $screenshot ) );
		$quota    = (int) Youem_Settings::get( 'save_quota_mb' ) * MB_IN_BYTES;
		if ( self::usage( $user_id ) - $old_size + $new_size > $quota ) {
			return new WP_Error( 'youem_quota', __( 'Save storage quota exceeded. Delete old saves and try again.', 'you-emulator' ), array( 'status' => 507 ) );
		}

		$file_path = null;
		$blob      = $state;
		if ( strlen( $state ) > self::FILE_THRESHOLD ) {
			$dir = Youem_Storage::dir( 'saves' ) . '/' . $user_id;
			wp_mkdir_p( $dir );
			$name = sprintf( '%d-%d.state', $game_id, $slot );
			if ( false === file_put_contents( $dir . '/' . $name . '.tmp', $state ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				return new WP_Error( 'youem_write', __( 'Could not write the save file.', 'you-emulator' ), array( 'status' => 500 ) );
			}
			rename( $dir . '/' . $name . '.tmp', $dir . '/' . $name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			$file_path = $user_id . '/' . $name;
			$blob      = '';
		} elseif ( $existing && ! empty( $existing['file_path'] ) ) {
			$old = Youem_Storage::resolve( 'saves', (string) $existing['file_path'] );
			if ( $old ) {
				wp_delete_file( $old );
			}
		}

		$now  = current_time( 'mysql', true );
		$data = array(
			'state'           => $blob,
			'screenshot'      => $screenshot,
			'file_path'       => $file_path,
			'state_size'      => strlen( $state ),
			'screenshot_size' => null === $screenshot ? 0 : strlen( $screenshot ),
			'updated_at'      => $now,
		);
		$fmt  = array( '%s', '%s', '%s', '%d', '%d', '%s' );
		if ( $existing ) {
			$ok = $wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ), $fmt, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$data += array(
				'user_id'    => $user_id,
				'game_id'    => $game_id,
				'slot'       => $slot,
				'created_at' => $now,
			);
			$ok    = $wpdb->insert( $table, $data, array_merge( $fmt, array( '%d', '%d', '%d', '%s' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		if ( false === $ok ) {
			return new WP_Error( 'youem_db', __( 'Could not store the save state.', 'you-emulator' ), array( 'status' => 500 ) );
		}
		if ( $interval > 0 ) {
			set_transient( $lock_key, 1, $interval );
		}
		/**
		 * Fires after a state is stored.
		 *
		 * @param int $user_id User ID.
		 * @param int $game_id Game ID.
		 * @param int $slot    Slot.
		 */
		do_action( 'youem_state_saved', $user_id, $game_id, $slot );
		foreach ( self::slots( $user_id, $game_id ) as $summary ) {
			if ( $summary['slot'] === $slot ) {
				return $summary;
			}
		}
		return array( 'slot' => $slot );
	}

	/**
	 * Loads a state or screenshot.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $game_id Game ID.
	 * @param int    $slot    Slot.
	 * @param string $what    'state' or 'screenshot'.
	 */
	public static function load( int $user_id, int $game_id, int $slot, string $what = 'state' ): ?string {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT state, screenshot, file_path FROM {$table} WHERE user_id = %d AND game_id = %d AND slot = %d", $user_id, $game_id, $slot ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			return null;
		}
		if ( 'screenshot' === $what ) {
			return '' === (string) $row['screenshot'] ? null : (string) $row['screenshot'];
		}
		if ( ! empty( $row['file_path'] ) ) {
			$path = Youem_Storage::resolve( 'saves', (string) $row['file_path'] );
			$data = $path ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return false === $data ? null : $data;
		}
		return (string) $row['state'];
	}

	/**
	 * Deletes one slot.
	 *
	 * @param int $user_id User ID.
	 * @param int $game_id Game ID.
	 * @param int $slot    Slot.
	 */
	public static function delete( int $user_id, int $game_id, int $slot ): bool {
		return self::delete_where( 'user_id = %d AND game_id = %d AND slot = %d', array( $user_id, $game_id, $slot ) ) > 0;
	}

	/**
	 * Deletes a save by row ID.
	 *
	 * @param int $id Row ID.
	 */
	public static function delete_by_id( int $id ): bool {
		return self::delete_where( 'id = %d', array( $id ) ) > 0;
	}

	/**
	 * Deletes all saves of a game.
	 *
	 * @param int $game_id Game ID.
	 */
	public static function delete_for_game( int $game_id ): void {
		self::delete_where( 'game_id = %d', array( $game_id ) );
	}

	/**
	 * Deletes all saves of a user.
	 *
	 * @param int $user_id User ID.
	 */
	public static function delete_for_user( int $user_id ): void {
		self::delete_where( 'user_id = %d', array( $user_id ) );
	}

	/**
	 * Deletes rows and their files.
	 *
	 * @param string            $where  SQL condition with placeholders.
	 * @param array<int, mixed> $params Values.
	 */
	private static function delete_where( string $where, array $params ): int {
		global $wpdb;
		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$files = $wpdb->get_col( $wpdb->prepare( "SELECT file_path FROM {$table} WHERE {$where} AND file_path IS NOT NULL", $params ) );
		foreach ( $files as $rel ) {
			$path = Youem_Storage::resolve( 'saves', (string) $rel );
			if ( $path ) {
				wp_delete_file( $path );
			}
		}
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$where}", $params ) );
		// phpcs:enable
		return (int) $deleted;
	}

	/**
	 * Paginated rows for the admin screen.
	 *
	 * @param int    $page     Page (1-based).
	 * @param int    $per_page Rows per page.
	 * @param string $orderby  Column.
	 * @param string $order    ASC|DESC.
	 * @param int    $game_id  Optional game filter.
	 * @return array{rows: array<int, array<string, mixed>>, total: int}
	 */
	public static function admin_rows( int $page, int $per_page, string $orderby = 'updated_at', string $order = 'DESC', int $game_id = 0 ): array {
		global $wpdb;
		$table   = self::table();
		$orderby = in_array( $orderby, array( 'updated_at', 'state_size', 'user_id', 'game_id', 'slot' ), true ) ? $orderby : 'updated_at';
		$order   = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
		$where   = $game_id ? $wpdb->prepare( 'WHERE game_id = %d', $game_id ) : '';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, user_id, game_id, slot, state_size, screenshot_size, created_at, updated_at FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", $per_page, ( $page - 1 ) * $per_page ), ARRAY_A );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
		// phpcs:enable
		return array(
			'rows'  => (array) $rows,
			'total' => $total,
		);
	}
}
