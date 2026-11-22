<?php
/**
 * REST API (namespace youem/v1).
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles plugin endpoints.
 */
class Youem_Rest {

	const NS             = 'youem/v1';
	const SESSION_WINDOW = 900;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Route definitions.
	 */
	public static function routes(): void {
		$id   = array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			),
		);
		$slot = array(
			'slot' => array(
				'type'    => 'integer',
				'default' => 0,
				'enum'    => Youem_Saves::SLOTS,
			),
		);

		register_rest_route(
			self::NS,
			'/games',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'games' ),
				'permission_callback' => array( __CLASS__, 'can_play' ),
				'args'                => array(
					'console'  => array(
						'type'    => 'string',
						'default' => '',
					),
					'genre'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'search'   => array(
						'type'    => 'string',
						'default' => '',
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 0,
						'minimum' => 0,
						'maximum' => 120,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/games/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'game' ),
				'permission_callback' => array( __CLASS__, 'can_play_game' ),
				'args'                => $id,
			)
		);
		register_rest_route(
			self::NS,
			'/games/(?P<id>\d+)/rom(?:/(?P<name>[^/]+))?',
			array(
				'methods'             => 'GET, HEAD',
				'callback'            => array( __CLASS__, 'rom' ),
				'permission_callback' => array( __CLASS__, 'can_play_game' ),
				'args'                => $id,
			)
		);
		register_rest_route(
			self::NS,
			'/games/(?P<id>\d+)/bios(?:/(?P<name>[^/]+))?',
			array(
				'methods'             => 'GET, HEAD',
				'callback'            => array( __CLASS__, 'bios' ),
				'permission_callback' => array( __CLASS__, 'can_play_game' ),
				'args'                => $id,
			)
		);
		register_rest_route(
			self::NS,
			'/games/(?P<id>\d+)/saves',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'saves' ),
				'permission_callback' => array( __CLASS__, 'can_play_game' ),
				'args'                => $id,
			)
		);
		register_rest_route(
			self::NS,
			'/games/(?P<id>\d+)/save',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save' ),
					'permission_callback' => array( __CLASS__, 'can_play_game' ),
					'args'                => $id + $slot,
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'delete_save' ),
					'permission_callback' => array( __CLASS__, 'can_play_game' ),
					'args'                => $id + $slot,
				),
			)
		);
		register_rest_route(
			self::NS,
			'/games/(?P<id>\d+)/load',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'load' ),
				'permission_callback' => array( __CLASS__, 'can_play_game' ),
				'args'                => $id + $slot,
			)
		);
		register_rest_route(
			self::NS,
			'/games/(?P<id>\d+)/saves/(?P<slot>\d)/screenshot',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'screenshot' ),
				'permission_callback' => array( __CLASS__, 'can_play_game' ),
				'args'                => $id + $slot,
			)
		);
		register_rest_route(
			self::NS,
			'/config',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'config' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NS,
			'/me/theme',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'set_theme' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'theme' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/admin/upload-chunk',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'upload_chunk' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
		register_rest_route(
			self::NS,
			'/admin/upload-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'upload_status' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'upload_id' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/admin/assemble',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'assemble' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'game_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'console' => array(
						'type'     => 'string',
						'required' => true,
					),
					'files'   => array(
						'type'     => 'array',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/admin/scrape',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'scrape' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'game_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'title'   => array(
						'type'     => 'string',
						'required' => true,
					),
					'console' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/admin/users',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'users' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'search' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Logged-in player permission (guests get 401).
	 */
	public static function can_play() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'youem_guest', __( 'Log in to play.', 'you-emulator' ), array( 'status' => 401 ) );
		}
		return current_user_can( Youem_Access::PLAY ) ? true : new WP_Error( 'youem_forbidden', __( 'You are not allowed to play.', 'you-emulator' ), array( 'status' => 403 ) );
	}

	/**
	 * Per-game permission; hidden games answer 404.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function can_play_game( WP_REST_Request $request ) {
		$base = self::can_play();
		if ( true !== $base ) {
			return $base;
		}
		if ( ! current_user_can( 'youem_play_game', (int) $request['id'] ) ) {
			return new WP_Error( 'youem_not_found', __( 'Game not found.', 'you-emulator' ), array( 'status' => 404 ) );
		}
		return true;
	}

	/**
	 * Manager permission.
	 */
	public static function can_manage(): bool {
		return current_user_can( Youem_Access::MANAGE );
	}

	/**
	 * GET /games.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function games( WP_REST_Request $request ): WP_REST_Response {
		$per_page = (int) $request['per_page'] ? (int) $request['per_page'] : (int) Youem_Settings::get( 'per_page' );
		$args     = array_merge(
			Youem_Access::query_args( get_current_user_id() ),
			array(
				'posts_per_page'         => $per_page,
				'paged'                  => (int) $request['page'],
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => true,
			)
		);
		$tax      = array();
		$console  = (string) $request['console'];
		if ( '' !== $console && 'all' !== $console ) {
			$tax[] = array(
				'taxonomy' => Youem_Game::TAX_CON,
				'field'    => 'slug',
				'terms'    => Youem_Consoles::term_slug( sanitize_text_field( $console ) ),
			);
		}
		$genre = (string) $request['genre'];
		if ( '' !== $genre ) {
			$tax[] = array(
				'taxonomy' => Youem_Game::TAX_GENRE,
				'field'    => is_numeric( $genre ) ? 'term_id' : 'slug',
				'terms'    => is_numeric( $genre ) ? (int) $genre : sanitize_title( $genre ),
			);
		}
		if ( $tax ) {
			$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		$search = trim( (string) $request['search'] );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		$query = new WP_Query( $args );
		$items = array_map( static fn( $p ) => Youem_Game::to_array( $p ), $query->posts );
		$res   = new WP_REST_Response(
			array(
				'items' => $items,
				'total' => (int) $query->found_posts,
				'pages' => (int) $query->max_num_pages,
				'page'  => (int) $request['page'],
			)
		);
		$res->header( 'X-WP-Total', (string) $query->found_posts );
		$res->header( 'X-WP-TotalPages', (string) $query->max_num_pages );
		$res->header( 'Cache-Control', 'private, no-store' );
		return $res;
	}

	/**
	 * GET /games/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function game( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( Youem_Game::to_array( get_post( (int) $request['id'] ), true ) );
	}

	/**
	 * Limits the number of distinct games a user streams per window.
	 *
	 * @param int $user_id User ID.
	 * @param int $game_id Game ID.
	 */
	public static function register_session( int $user_id, int $game_id ): bool {
		if ( user_can( $user_id, Youem_Access::MANAGE ) ) {
			return true;
		}
		$key      = 'youem_rom_sessions_' . $user_id;
		$now      = time();
		$sessions = array_filter( (array) get_transient( $key ), static fn( $ts ) => (int) $ts > $now - self::SESSION_WINDOW );
		if ( ! isset( $sessions[ $game_id ] ) && count( $sessions ) >= (int) Youem_Settings::get( 'rom_sessions' ) ) {
			return false;
		}
		$sessions[ $game_id ] = $now;
		set_transient( $key, $sessions, self::SESSION_WINDOW );
		return true;
	}

	/**
	 * GET|HEAD /games/{id}/rom.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error|void
	 */
	public static function rom( WP_REST_Request $request ) {
		$game_id = (int) $request['id'];
		$path    = Youem_Storage::resolve( 'roms', (string) get_post_meta( $game_id, '_youem_rom_path', true ) );
		if ( ! $path ) {
			return new WP_Error( 'youem_no_rom', __( 'ROM file is missing.', 'you-emulator' ), array( 'status' => 404 ) );
		}
		if ( 'HEAD' !== $request->get_method() && ! self::register_session( get_current_user_id(), $game_id ) ) {
			return new WP_Error( 'youem_rate_limited', __( 'Too many games opened at once. Close other games and try again in a few minutes.', 'you-emulator' ), array( 'status' => 429 ) );
		}
		/**
		 * Fires before a ROM is streamed.
		 *
		 * @param int    $game_id Game ID.
		 * @param string $path    Absolute ROM path.
		 */
		do_action( 'youem_before_rom_stream', $game_id, $path );
		Youem_Stream::send( $path, wp_basename( $path ), 'application/octet-stream', DAY_IN_SECONDS );
	}

	/**
	 * GET|HEAD /games/{id}/bios.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error|void
	 */
	public static function bios( WP_REST_Request $request ) {
		$bundle = Youem_Bios::bundle( Youem_Game::console( (int) $request['id'] ) );
		if ( ! $bundle ) {
			return new WP_Error( 'youem_no_bios', __( 'BIOS is not uploaded for this system.', 'you-emulator' ), array( 'status' => 404 ) );
		}
		Youem_Stream::send( $bundle, 'bios.zip', 'application/zip', HOUR_IN_SECONDS );
	}

	/**
	 * GET /games/{id}/saves.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function saves( WP_REST_Request $request ): WP_REST_Response {
		$user = get_current_user_id();
		return new WP_REST_Response(
			array(
				'slots' => Youem_Saves::slots( $user, (int) $request['id'] ),
				'usage' => Youem_Saves::usage( $user ),
				'quota' => (int) Youem_Settings::get( 'save_quota_mb' ) * MB_IN_BYTES,
			)
		);
	}

	/**
	 * POST /games/{id}/save (multipart: state, screenshot, slot).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		$state = null;
		if ( ! empty( $files['state']['tmp_name'] ) && UPLOAD_ERR_OK === (int) $files['state']['error'] ) {
			$state = (string) file_get_contents( $files['state']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		} elseif ( str_starts_with( (string) $request->get_content_type()['value'], 'application/octet-stream' ) ) {
			$state = (string) $request->get_body();
		}
		if ( null === $state || '' === $state ) {
			return new WP_Error( 'youem_empty_state', __( 'Empty save state.', 'you-emulator' ), array( 'status' => 400 ) );
		}
		$shot = null;
		if ( ! empty( $files['screenshot']['tmp_name'] ) && UPLOAD_ERR_OK === (int) $files['screenshot']['error'] ) {
			$data = (string) file_get_contents( $files['screenshot']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( in_array( substr( $data, 0, 4 ), array( "\x89PNG", "\xFF\xD8\xFF\xE0", "\xFF\xD8\xFF\xE1", "\xFF\xD8\xFF\xDB", 'RIFF' ), true ) ) {
				$shot = $data;
			}
		}
		$res = Youem_Saves::save( get_current_user_id(), (int) $request['id'], (int) $request['slot'], $state, $shot );
		return is_wp_error( $res ) ? $res : new WP_REST_Response( $res );
	}

	/**
	 * DELETE /games/{id}/save.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function delete_save( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( array( 'deleted' => Youem_Saves::delete( get_current_user_id(), (int) $request['id'], (int) $request['slot'] ) ) );
	}

	/**
	 * GET /games/{id}/load.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error|void
	 */
	public static function load( WP_REST_Request $request ) {
		$data = Youem_Saves::load( get_current_user_id(), (int) $request['id'], (int) $request['slot'] );
		if ( null === $data ) {
			return new WP_Error( 'youem_no_state', __( 'This slot is empty.', 'you-emulator' ), array( 'status' => 404 ) );
		}
		Youem_Stream::send_blob( $data, 'application/octet-stream', 'slot' . (int) $request['slot'] . '.state' );
	}

	/**
	 * GET /games/{id}/saves/{slot}/screenshot.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error|void
	 */
	public static function screenshot( WP_REST_Request $request ) {
		$data = Youem_Saves::load( get_current_user_id(), (int) $request['id'], (int) $request['slot'], 'screenshot' );
		if ( null === $data ) {
			return new WP_Error( 'youem_no_state', __( 'No screenshot.', 'you-emulator' ), array( 'status' => 404 ) );
		}
		$type = str_starts_with( $data, "\x89PNG" ) ? 'image/png' : ( str_starts_with( $data, 'RIFF' ) ? 'image/webp' : 'image/jpeg' );
		Youem_Stream::send_blob( $data, $type, 'slot.img' );
	}

	/**
	 * GET /config.
	 */
	public static function config(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'version'      => YOUEM_VERSION,
				'ejsVersion'   => YOUEM_EJS_VERSION,
				'combos'       => Youem_Settings::get( 'combos' ),
				'keyboard'     => array(
					'save'       => 'Shift+Digit1',
					'load'       => 'Shift+Digit2',
					'fullscreen' => 'Shift+Digit3',
				),
				'slots'        => Youem_Saves::SLOTS,
				'perPage'      => (int) Youem_Settings::get( 'per_page' ),
				'saveInterval' => (int) Youem_Settings::get( 'save_interval' ),
				'saveQuota'    => (int) Youem_Settings::get( 'save_quota_mb' ) * MB_IN_BYTES,
				'consoles'     => array_map(
					static fn( $c ) => array(
						'label' => $c['label'],
						'short' => $c['short'],
					),
					Youem_Consoles::all()
				),
				'themes'       => array_map( static fn( $t ) => $t['name'], Youem_Themes::all() ),
			)
		);
	}

	/**
	 * POST /me/theme.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function set_theme( WP_REST_Request $request ) {
		$slug = sanitize_key( (string) $request['theme'] );
		if ( ! Youem_Themes::set( get_current_user_id(), $slug ) && '' !== $slug ) {
			return new WP_Error( 'youem_bad_theme', __( 'Unknown theme.', 'you-emulator' ), array( 'status' => 400 ) );
		}
		return new WP_REST_Response( array( 'theme' => Youem_Themes::current( get_current_user_id() ) ) );
	}

	/**
	 * POST /admin/upload-chunk.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function upload_chunk( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['chunk']['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $files['chunk']['error'] ) {
			return new WP_Error( 'youem_bad_chunk', __( 'Chunk upload failed.', 'you-emulator' ), array( 'status' => 400 ) );
		}
		$res = Youem_Upload::put_chunk( (string) $request['upload_id'], (int) $request['index'], (int) $request['total'], (string) $files['chunk']['tmp_name'] );
		return is_wp_error( $res ) ? $res : new WP_REST_Response( array( 'received' => (int) $request['index'] ) );
	}

	/**
	 * GET /admin/upload-status.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function upload_status( WP_REST_Request $request ): WP_REST_Response {
		$id = (string) $request['upload_id'];
		return new WP_REST_Response( array( 'received' => Youem_Upload::valid_id( $id ) ? Youem_Upload::received( $id ) : array() ) );
	}

	/**
	 * POST /admin/assemble.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function assemble( WP_REST_Request $request ) {
		$res = Youem_Upload::assemble( (int) $request['game_id'], (string) $request['console'], (array) $request['files'] );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$res['sizeHuman'] = size_format( $res['size'], 1 );
		return new WP_REST_Response( $res );
	}

	/**
	 * POST /admin/scrape.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function scrape( WP_REST_Request $request ) {
		$game_id = (int) $request['game_id'];
		if ( Youem_Game::POST_TYPE !== get_post_type( $game_id ) ) {
			return new WP_Error( 'youem_bad_game', __( 'Unknown game.', 'you-emulator' ), array( 'status' => 404 ) );
		}
		$info = Youem_Covers::lookup( sanitize_text_field( (string) $request['title'] ), (string) $request['console'], (string) get_post_meta( $game_id, '_youem_rom_name', true ), (int) get_post_meta( $game_id, '_youem_rom_size', true ) );
		if ( is_wp_error( $info ) ) {
			$info->add_data( array( 'status' => 502 ) );
			return $info;
		}
		$applied = Youem_Covers::apply( $game_id, $info );
		$cover   = Youem_Game::cover( $game_id, 'medium' );
		return new WP_REST_Response(
			array(
				'info'    => $info,
				'applied' => $applied,
				'cover'   => $cover,
				'coverId' => (int) get_post_meta( $game_id, '_youem_cover_id', true ),
			)
		);
	}

	/**
	 * GET /admin/users.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function users( WP_REST_Request $request ): WP_REST_Response {
		$search = trim( (string) $request['search'] );
		$users  = get_users(
			array(
				'search'         => '' === $search ? '' : '*' . $search . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name', 'user_nicename' ),
				'number'         => 20,
				'fields'         => array( 'ID', 'display_name', 'user_login' ),
			)
		);
		return new WP_REST_Response(
			array_map(
				static fn( $u ) => array(
					'id'    => (int) $u->ID,
					'label' => $u->display_name . ' (' . $u->user_login . ')',
				),
				$users
			)
		);
	}
}
