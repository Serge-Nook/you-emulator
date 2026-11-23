<?php
/**
 * Capabilities and per-game visibility.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Server-side access checks for listing, ROM delivery and saves.
 */
class Youem_Access {

	const MANAGE = 'youem_manage_games';
	const PLAY   = 'youem_play';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'user_has_cap', array( __CLASS__, 'grant_play' ), 10, 4 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 10, 4 );
	}

	/**
	 * Grants youem_play to logged-in users (optionally limited to roles from settings).
	 *
	 * @param array<string, bool> $allcaps All caps.
	 * @param string[]            $caps    Required primitive caps.
	 * @param array<int, mixed>   $args    Arguments.
	 * @param WP_User             $user    User.
	 * @return array<string, bool>
	 */
	public static function grant_play( array $allcaps, array $caps, array $args, $user ): array {
		if ( ! in_array( self::PLAY, $caps, true ) || ! ( $user instanceof WP_User ) || ! $user->exists() ) {
			return $allcaps;
		}
		if ( ! empty( $allcaps[ self::MANAGE ] ) ) {
			$allcaps[ self::PLAY ] = true;
			return $allcaps;
		}
		if ( array_key_exists( self::PLAY, $user->caps ) ) {
			return $allcaps;
		}
		$roles = (array) Youem_Settings::get( 'play_roles' );
		if ( ! $roles || array_intersect( $roles, (array) $user->roles ) ) {
			$allcaps[ self::PLAY ] = true;
		}
		return $allcaps;
	}

	/**
	 * Maps youem_play_game (meta cap with game ID) to primitive caps.
	 *
	 * @param string[]          $caps    Primitive caps.
	 * @param string            $cap     Requested cap.
	 * @param int               $user_id User ID.
	 * @param array<int, mixed> $args    Args.
	 * @return string[]
	 */
	public static function map_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
		if ( 'youem_play_game' !== $cap ) {
			return $caps;
		}
		$post_id = (int) ( $args[0] ?? 0 );
		return self::can_access( $post_id, $user_id ) ? array( self::PLAY ) : array( 'do_not_allow' );
	}

	/**
	 * Whether a user may see and play a game.
	 *
	 * @param int $post_id Game ID.
	 * @param int $user_id User ID.
	 */
	public static function can_access( int $post_id, int $user_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post || Youem_Game::POST_TYPE !== $post->post_type || ! $user_id ) {
			return false;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		if ( user_can( $user, self::MANAGE ) ) {
			return true;
		}
		if ( 'publish' !== $post->post_status || '' !== $post->post_password || ! user_can( $user, self::PLAY ) ) {
			return false;
		}
		$visibility = (string) get_post_meta( $post_id, '_youem_visibility', true );
		if ( 'private' !== $visibility ) {
			$allowed = true;
		} else {
			$users   = array_map( 'intval', (array) get_post_meta( $post_id, '_youem_allowed_users', false ) );
			$roles   = array_map( 'strval', (array) get_post_meta( $post_id, '_youem_allowed_roles', false ) );
			$allowed = in_array( $user_id, $users, true ) || (bool) array_intersect( $roles, (array) $user->roles );
		}
		/**
		 * Filters per-game access.
		 *
		 * @param bool $allowed Whether access is granted.
		 * @param int  $post_id Game ID.
		 * @param int  $user_id User ID.
		 */
		return (bool) apply_filters( 'youem_can_access_game', $allowed, $post_id, $user_id );
	}

	/**
	 * Query args restricting WP_Query to games visible to a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed>
	 */
	public static function query_args( int $user_id ): array {
		$user = $user_id ? get_userdata( $user_id ) : false;
		if ( ! $user || ! user_can( $user, self::PLAY ) ) {
			return array( 'post__in' => array( 0 ) );
		}
		$args = array(
			'post_type'    => Youem_Game::POST_TYPE,
			'post_status'  => 'publish',
			'has_password' => false,
		);
		if ( user_can( $user, self::MANAGE ) ) {
			return $args;
		}
		$private = array(
			'relation' => 'OR',
			array(
				'key'   => '_youem_allowed_users',
				'value' => (string) $user_id,
			),
		);
		if ( $user->roles ) {
			$private[] = array(
				'key'     => '_youem_allowed_roles',
				'value'   => array_values( (array) $user->roles ),
				'compare' => 'IN',
			);
		}
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'OR',
			array(
				'key'     => '_youem_visibility',
				'value'   => 'private',
				'compare' => '!=',
			),
			array(
				'key'     => '_youem_visibility',
				'compare' => 'NOT EXISTS',
			),
			array(
				'relation' => 'AND',
				array(
					'key'   => '_youem_visibility',
					'value' => 'private',
				),
				$private,
			),
		);
		return $args;
	}
}
