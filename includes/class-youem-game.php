<?php
/**
 * Game post type, taxonomies and meta.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers youem_game, youem_console and youem_genre.
 */
class Youem_Game {

	const POST_TYPE = 'youem_game';
	const TAX_CON   = 'youem_console';
	const TAX_GENRE = 'youem_genre';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'cyrillic_slug' ), 99, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ) );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'hide_from_sitemaps' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( __CLASS__, 'hide_tax_from_sitemaps' ) );
	}

	/**
	 * Registers the CPT, taxonomies and meta.
	 */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Games', 'you-emulator' ),
					'singular_name'      => __( 'Game', 'you-emulator' ),
					'menu_name'          => 'You Emulator',
					'all_items'          => __( 'Games', 'you-emulator' ),
					'add_new'            => __( 'Add game', 'you-emulator' ),
					'add_new_item'       => __( 'Add game', 'you-emulator' ),
					'edit_item'          => __( 'Edit game', 'you-emulator' ),
					'new_item'           => __( 'New game', 'you-emulator' ),
					'view_item'          => __( 'View game', 'you-emulator' ),
					'search_items'       => __( 'Search games', 'you-emulator' ),
					'not_found'          => __( 'No games found', 'you-emulator' ),
					'not_found_in_trash' => __( 'No games found in Trash', 'you-emulator' ),
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'show_in_nav_menus'   => false,
				'menu_icon'           => 'dashicons-games',
				'menu_position'       => 26,
				'supports'            => array( 'title', 'editor', 'excerpt' ),
				'has_archive'         => false,
				'rewrite'             => array(
					'slug'       => 'game',
					'with_front' => false,
				),
				'capability_type'     => 'post',
				'capabilities'        => array(
					'edit_post'              => 'youem_manage_games',
					'read_post'              => 'read',
					'delete_post'            => 'youem_manage_games',
					'edit_posts'             => 'youem_manage_games',
					'edit_others_posts'      => 'youem_manage_games',
					'delete_posts'           => 'youem_manage_games',
					'publish_posts'          => 'youem_manage_games',
					'read_private_posts'     => 'youem_manage_games',
					'delete_private_posts'   => 'youem_manage_games',
					'delete_published_posts' => 'youem_manage_games',
					'delete_others_posts'    => 'youem_manage_games',
					'edit_private_posts'     => 'youem_manage_games',
					'edit_published_posts'   => 'youem_manage_games',
					'create_posts'           => 'youem_manage_games',
				),
				'map_meta_cap'        => false,
			)
		);

		$tax_caps = array(
			'manage_terms' => 'youem_manage_games',
			'edit_terms'   => 'youem_manage_games',
			'delete_terms' => 'youem_manage_games',
			'assign_terms' => 'youem_manage_games',
		);

		register_taxonomy(
			self::TAX_CON,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Consoles', 'you-emulator' ),
					'singular_name' => __( 'Console', 'you-emulator' ),
					'menu_name'     => __( 'Consoles', 'you-emulator' ),
				),
				'hierarchical'      => true,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'show_admin_column' => false,
				'show_in_rest'      => false,
				'meta_box_cb'       => false,
				'rewrite'           => false,
				'capabilities'      => $tax_caps,
			)
		);

		register_taxonomy(
			self::TAX_GENRE,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Genres', 'you-emulator' ),
					'singular_name' => __( 'Genre', 'you-emulator' ),
					'menu_name'     => __( 'Genres', 'you-emulator' ),
					'add_new_item'  => __( 'Add genre', 'you-emulator' ),
				),
				'hierarchical'      => false,
				'public'            => false,
				'show_ui'           => true,
				'show_in_menu'      => true,
				'show_admin_column' => false,
				'show_in_rest'      => false,
				'rewrite'           => false,
				'capabilities'      => $tax_caps,
			)
		);

		$meta = array(
			'_youem_rom_path'     => 'string',
			'_youem_rom_name'     => 'string',
			'_youem_rom_size'     => 'integer',
			'_youem_console'      => 'string',
			'_youem_core'         => 'string',
			'_youem_cover_id'     => 'integer',
			'_youem_cover_source' => 'string',
			'_youem_year'         => 'string',
			'_youem_developer'    => 'string',
			'_youem_players'      => 'string',
			'_youem_visibility'   => 'string',
		);
		foreach ( $meta as $key => $type ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => static fn() => current_user_can( 'youem_manage_games' ),
				)
			);
		}
		foreach ( array(
			'_youem_allowed_users' => 'integer',
			'_youem_allowed_roles' => 'string',
		) as $key => $type ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'          => $type,
					'single'        => false,
					'show_in_rest'  => false,
					'auth_callback' => static fn() => current_user_can( 'youem_manage_games' ),
				)
			);
		}
	}

	/**
	 * Creates the console terms and a default genre set.
	 */
	public static function create_default_terms(): void {
		foreach ( Youem_Consoles::all() as $code => $console ) {
			$slug = Youem_Consoles::term_slug( $code );
			if ( ! term_exists( $slug, self::TAX_CON ) ) {
				wp_insert_term( $console['label'], self::TAX_CON, array( 'slug' => $slug ) );
			}
		}
		if ( ! get_terms(
			array(
				'taxonomy'   => self::TAX_GENRE,
				'hide_empty' => false,
				'number'     => 1,
			)
		) ) {
			$genres = array( __( 'Action', 'you-emulator' ), 'RPG', __( 'Racing', 'you-emulator' ), __( 'Platformer', 'you-emulator' ), __( 'Fighting', 'you-emulator' ), __( 'Shooter', 'you-emulator' ), __( 'Puzzle', 'you-emulator' ), __( 'Sports', 'you-emulator' ) );
			foreach ( $genres as $genre ) {
				wp_insert_term( $genre, self::TAX_GENRE, array( 'slug' => self::unicode_slug( $genre ) ) );
			}
		}
	}

	/**
	 * Lower-case percent-encoded slug that keeps Cyrillic letters.
	 *
	 * @param string $title Title.
	 */
	public static function unicode_slug( string $title ): string {
		$title = wp_strip_all_tags( $title );
		$title = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );
		return sanitize_title_with_dashes( $title, '', 'save' );
	}

	/**
	 * Builds game slugs from the title in Cyrillic, ignoring transliteration plugins.
	 *
	 * @param array<string, mixed> $data    Post data.
	 * @param array<string, mixed> $postarr Raw input.
	 * @return array<string, mixed>
	 */
	public static function cyrillic_slug( array $data, array $postarr ): array {
		if ( self::POST_TYPE !== $data['post_type'] || in_array( $data['post_status'], array( 'auto-draft', 'trash' ), true ) || '' === trim( (string) $data['post_title'] ) ) {
			return $data;
		}
		$title     = (string) wp_unslash( $data['post_title'] );
		$requested = isset( $postarr['post_name'] ) ? (string) $postarr['post_name'] : '';
		$auto      = sanitize_title( $title );
		if ( '' !== $requested && urldecode( $requested ) !== urldecode( $auto ) && ! str_starts_with( $requested, 'auto-draft' ) ) {
			return $data;
		}
		$slug = self::unicode_slug( $title );
		if ( '' === $slug ) {
			return $data;
		}
		$data['post_name'] = wp_unique_post_slug( $slug, (int) ( $postarr['ID'] ?? 0 ), $data['post_status'], self::POST_TYPE, (int) $data['post_parent'] );
		return $data;
	}

	/**
	 * Deletes ROM file and saves of a removed game.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_delete( int $post_id ): void {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		self::delete_rom_file( $post_id );
		Youem_Saves::delete_for_game( $post_id );
	}

	/**
	 * Removes the ROM file of a game.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function delete_rom_file( int $post_id ): void {
		$rel = (string) get_post_meta( $post_id, '_youem_rom_path', true );
		if ( '' === $rel ) {
			return;
		}
		$path = Youem_Storage::resolve( 'roms', $rel );
		if ( $path ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Console system code of a game.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function console( int $post_id ): string {
		$code = (string) get_post_meta( $post_id, '_youem_console', true );
		return Youem_Consoles::exists( $code ) ? $code : '';
	}

	/**
	 * Core used for a game.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function core( int $post_id ): string {
		$console = self::console( $post_id );
		$core    = (string) get_post_meta( $post_id, '_youem_core', true );
		$def     = Youem_Consoles::get( $console );
		if ( $def && in_array( $core, $def['cores'], true ) ) {
			return $core;
		}
		return Youem_Consoles::default_core( $console );
	}

	/**
	 * Cover image data.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $size    Image size.
	 * @return array{url:string,srcset:string,width:int,height:int}|null
	 */
	public static function cover( int $post_id, string $size = 'medium_large' ): ?array {
		$id = (int) get_post_meta( $post_id, '_youem_cover_id', true );
		if ( ! $id ) {
			return null;
		}
		$img = wp_get_attachment_image_src( $id, $size );
		if ( ! $img ) {
			return null;
		}
		return array(
			'url'    => (string) $img[0],
			'srcset' => (string) wp_get_attachment_image_srcset( $id, $size ),
			'width'  => (int) $img[1],
			'height' => (int) $img[2],
		);
	}

	/**
	 * Genre names.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array{id:int,name:string,slug:string}>
	 */
	public static function genres( int $post_id ): array {
		$terms = get_the_terms( $post_id, self::TAX_GENRE );
		if ( ! is_array( $terms ) ) {
			return array();
		}
		return array_map(
			static fn( WP_Term $t ) => array(
				'id'   => $t->term_id,
				'name' => $t->name,
				'slug' => $t->slug,
			),
			$terms
		);
	}

	/**
	 * Public representation used by REST and templates.
	 *
	 * @param WP_Post $post Post.
	 * @param bool    $full Include the description.
	 * @return array<string, mixed>
	 */
	public static function to_array( WP_Post $post, bool $full = false ): array {
		$console = self::console( $post->ID );
		$def     = Youem_Consoles::get( $console );
		$data    = array(
			'id'           => $post->ID,
			'title'        => get_the_title( $post ),
			'slug'         => $post->post_name,
			'link'         => get_permalink( $post ),
			'console'      => $console,
			'consoleLabel' => $def ? $def['label'] : '',
			'genres'       => self::genres( $post->ID ),
			'year'         => (string) get_post_meta( $post->ID, '_youem_year', true ),
			'developer'    => (string) get_post_meta( $post->ID, '_youem_developer', true ),
			'players'      => (string) get_post_meta( $post->ID, '_youem_players', true ),
			'cover'        => self::cover( $post->ID ),
			'heavy'        => $def ? (bool) $def['heavy'] : false,
		);
		if ( $full ) {
			$data['excerpt']     = wp_strip_all_tags( get_the_excerpt( $post ) );
			$data['description'] = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$data['romSize']     = (int) get_post_meta( $post->ID, '_youem_rom_size', true );
			$data['core']        = self::core( $post->ID );
		}
		return $data;
	}

	/**
	 * Hides games from core sitemaps.
	 *
	 * @param array<string, WP_Post_Type> $types Post types.
	 * @return array<string, WP_Post_Type>
	 */
	public static function hide_from_sitemaps( array $types ): array {
		unset( $types[ self::POST_TYPE ] );
		return $types;
	}

	/**
	 * Hides taxonomies from core sitemaps.
	 *
	 * @param array<string, WP_Taxonomy> $taxes Taxonomies.
	 * @return array<string, WP_Taxonomy>
	 */
	public static function hide_tax_from_sitemaps( array $taxes ): array {
		unset( $taxes[ self::TAX_CON ], $taxes[ self::TAX_GENRE ] );
		return $taxes;
	}
}
