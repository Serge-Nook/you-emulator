<?php
/**
 * Activation, upgrades and cron.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates the saves table, storage, caps and default terms.
 */
class Youem_Activator {

	const CRON = 'youem_daily_cleanup';

	/**
	 * Activation hook.
	 */
	public static function activate(): void {
		self::install();
		Youem_Game::register();
		Youem_Game::create_default_terms();
		flush_rewrite_rules();
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
	}

	/**
	 * Deactivation hook.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON );
		flush_rewrite_rules();
	}

	/**
	 * Runs install when the DB version changes.
	 */
	public static function maybe_upgrade(): void {
		add_action( self::CRON, array( 'Youem_Storage', 'cleanup_tmp' ) );
		if ( get_option( 'youem_db_version' ) !== YOUEM_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Table, storage and capabilities.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = Youem_Saves::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  game_id BIGINT UNSIGNED NOT NULL,
  slot TINYINT UNSIGNED NOT NULL DEFAULT 0,
  state LONGBLOB NOT NULL,
  screenshot LONGBLOB NULL,
  file_path VARCHAR(255) NULL,
  state_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  screenshot_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY idx_user_game (user_id,game_id,slot),
  KEY idx_game (game_id)
) {$charset};"
		);
		Youem_Storage::ensure();
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( Youem_Access::MANAGE );
		}
		if ( false === get_option( Youem_Settings::OPTION ) ) {
			add_option( Youem_Settings::OPTION, Youem_Settings::defaults() );
		}
		update_option( 'youem_db_version', YOUEM_DB_VERSION );
	}
}
