<?php
/**
 * Uninstall: removes the table, options, user meta and (optionally) stored files.
 *
 * @package YouEmulator
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$youem_settings = get_option( 'youem_settings', array() );

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}youem_saves" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
delete_option( 'youem_settings' );
delete_option( 'youem_db_version' );
delete_metadata( 'user', 0, 'youem_theme', '', true );
wp_clear_scheduled_hook( 'youem_daily_cleanup' );

foreach ( wp_roles()->role_objects as $youem_role ) {
	$youem_role->remove_cap( 'youem_manage_games' );
	$youem_role->remove_cap( 'youem_play' );
}

if ( is_array( $youem_settings ) && ! empty( $youem_settings['uninstall_purge'] ) ) {
	$youem_games = get_posts(
		array(
			'post_type'   => 'youem_game',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);
	foreach ( $youem_games as $youem_game ) {
		wp_delete_post( $youem_game, true );
	}
	foreach ( array( 'youem_console', 'youem_genre' ) as $youem_tax ) {
		$youem_terms = $wpdb->get_col( $wpdb->prepare( "SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.taxonomy = %s", $youem_tax ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $youem_terms as $youem_term ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete(
				$wpdb->term_taxonomy,
				array(
					'term_id'  => (int) $youem_term,
					'taxonomy' => $youem_tax,
				)
			);
			$wpdb->delete( $wpdb->terms, array( 'term_id' => (int) $youem_term ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}
	$youem_uploads = wp_upload_dir( null, false );
	$youem_dir     = trailingslashit( $youem_uploads['basedir'] ) . 'you-emulator';
	if ( is_dir( $youem_dir ) ) {
		$youem_items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $youem_dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $youem_items as $youem_item ) {
			if ( $youem_item->isDir() ) {
				rmdir( $youem_item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			} else {
				unlink( $youem_item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
		rmdir( $youem_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
