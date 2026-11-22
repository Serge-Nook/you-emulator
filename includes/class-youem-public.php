<?php
/**
 * Front end: library shortcode/block, guest banner, game player page.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the public side of the plugin.
 */
class Youem_Public {

	/**
	 * Whether the current request renders plugin UI.
	 *
	 * @var bool|null
	 */
	private static ?bool $uses = null;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_shortcode( 'youem_library', array( __CLASS__, 'shortcode_library' ) );
		add_shortcode( 'youem_guest_banner', array( __CLASS__, 'shortcode_banner' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'init', array( __CLASS__, 'register_assets' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ), 1 );
		add_filter( 'the_content', array( __CLASS__, 'game_content' ), 20 );
		add_filter( 'template_include', array( __CLASS__, 'embed_template' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'restrict_queries' ) );
	}

	/**
	 * Applies game access rules to front-end listings and feeds of games.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function restrict_queries( WP_Query $query ): void {
		if ( is_admin() || $query->is_singular() || ( ! $query->is_main_query() && ! $query->is_feed() ) ) {
			return;
		}
		$types = (array) $query->get( 'post_type' );
		if ( ! in_array( Youem_Game::POST_TYPE, $types, true ) ) {
			return;
		}
		if ( count( $types ) > 1 ) {
			$query->set( 'post_type', array_values( array_diff( $types, array( Youem_Game::POST_TYPE ) ) ) );
			return;
		}
		foreach ( Youem_Access::query_args( get_current_user_id() ) as $key => $value ) {
			$query->set( $key, $value );
		}
	}

	/**
	 * Whether this page renders the library or a game.
	 */
	public static function page_uses_plugin(): bool {
		if ( null !== self::$uses ) {
			return self::$uses;
		}
		if ( is_singular( Youem_Game::POST_TYPE ) ) {
			self::$uses = true;
			return true;
		}
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			self::$uses = has_shortcode( $post->post_content, 'youem_library' ) || has_block( 'you-emulator/library', $post );
			return self::$uses;
		}
		return false;
	}

	/**
	 * Registers the server-rendered Gutenberg block.
	 */
	public static function register_block(): void {
		wp_register_script( 'youem-block', YOUEM_URL . 'public/js/block.js', array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ), YOUEM_VERSION, true );
		wp_set_script_translations( 'youem-block', 'you-emulator', YOUEM_DIR . 'languages' );
		register_block_type(
			'you-emulator/library',
			array(
				'api_version'     => 3,
				'title'           => __( 'Retro game library', 'you-emulator' ),
				'category'        => 'widgets',
				'icon'            => 'games',
				'editor_script'   => 'youem-block',
				'attributes'      => array(
					'console' => array(
						'type'    => 'string',
						'default' => '',
					),
					'perPage' => array(
						'type'    => 'number',
						'default' => 0,
					),
				),
				'render_callback' => static fn( $atts ) => self::shortcode_library(
					array(
						'console'  => $atts['console'] ?? '',
						'per_page' => $atts['perPage'] ?? 0,
					)
				),
			)
		);
	}

	/**
	 * Registers styles and scripts.
	 */
	public static function register_assets(): void {
		$v = YOUEM_VERSION;
		wp_register_style( 'youem-base', YOUEM_URL . 'public/css/base.css', array(), $v );
		foreach ( Youem_Themes::all() as $slug => $theme ) {
			wp_register_style( 'youem-theme-' . $slug, $theme['css'], array( 'youem-base' ), $v );
		}
		wp_register_style( 'youem-library', YOUEM_URL . 'public/css/library.css', array( 'youem-base' ), $v );
		wp_register_style( 'youem-player', YOUEM_URL . 'public/css/player.css', array( 'youem-base' ), $v );
		wp_register_script( 'youem-common', YOUEM_URL . 'public/js/common.js', array( 'wp-i18n' ), $v, true );
		wp_register_script( 'youem-library', YOUEM_URL . 'public/js/library.js', array( 'youem-common' ), $v, true );
		wp_register_script( 'youem-hotkeys', YOUEM_URL . 'public/js/hotkeys.js', array(), $v, true );
		wp_register_script( 'youem-gamepad', YOUEM_URL . 'public/js/gamepad.js', array(), $v, true );
		wp_register_script( 'youem-player', YOUEM_URL . 'public/js/player.js', array( 'youem-common', 'youem-hotkeys', 'youem-gamepad' ), $v, true );
		foreach ( array( 'youem-common', 'youem-library', 'youem-player' ) as $handle ) {
			wp_set_script_translations( $handle, 'you-emulator', YOUEM_DIR . 'languages' );
		}
	}

	/**
	 * Enqueues theme styles and the shared client config.
	 */
	private static function enqueue_common(): void {
		wp_enqueue_style( 'youem-base' );
		foreach ( array_keys( Youem_Themes::all() ) as $slug ) {
			wp_enqueue_style( 'youem-theme-' . $slug );
		}
		wp_enqueue_script( 'youem-common' );
		static $done = false;
		if ( $done ) {
			return;
		}
		$done    = true;
		$user_id = get_current_user_id();
		$config  = array(
			'rest'     => esc_url_raw( rest_url( Youem_Rest::NS . '/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'theme'    => Youem_Themes::current( $user_id ),
			'themes'   => array_map( static fn( $t ) => $t['name'], Youem_Themes::all() ),
			'loggedIn' => (bool) $user_id,
		);
		wp_add_inline_script( 'youem-common', 'window.youemConfig = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/**
	 * Guest banner HTML.
	 */
	public static function guest_banner(): string {
		$s        = Youem_Settings::all();
		$redirect = is_singular() ? get_permalink() : home_url( add_query_arg( array() ) );
		$buttons  = '<a class="youem-btn youem-btn--primary" href="' . esc_url( wp_login_url( $redirect ) ) . '">' . esc_html__( 'Log in', 'you-emulator' ) . '</a>';
		if ( get_option( 'users_can_register' ) ) {
			$buttons .= '<a class="youem-btn" href="' . esc_url( wp_registration_url() ) . '">' . esc_html__( 'Sign up', 'you-emulator' ) . '</a>';
		}
		$html  = '<section class="youem youem-guest youem-theme-' . esc_attr( $s['default_theme'] ) . '" role="region" aria-label="' . esc_attr( $s['guest_banner_title'] ) . '">';
		$html .= '<div class="youem-guest__glow" aria-hidden="true"></div>';
		$html .= '<h2 class="youem-guest__title">' . esc_html( $s['guest_banner_title'] ) . '</h2>';
		$html .= '<div class="youem-guest__text">' . wp_kses_post( do_shortcode( $s['guest_banner_html'] ) ) . '</div>';
		$html .= '<div class="youem-guest__actions">' . $buttons . '</div>';
		$html .= '</section>';
		/**
		 * Filters the guest banner markup.
		 *
		 * @param string $html Markup.
		 */
		return (string) apply_filters( 'youem_guest_banner', $html );
	}

	/**
	 * [youem_guest_banner].
	 */
	public static function shortcode_banner(): string {
		if ( is_user_logged_in() ) {
			return '';
		}
		self::enqueue_common();
		return self::guest_banner();
	}

	/**
	 * [youem_library console="" genre="" per_page=""].
	 *
	 * @param array<string, mixed>|string $atts Attributes.
	 */
	public static function shortcode_library( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'console'  => '',
				'genre'    => '',
				'per_page' => 0,
			),
			is_array( $atts ) ? $atts : array(),
			'youem_library'
		);
		self::enqueue_common();
		if ( ! is_user_logged_in() ) {
			return self::guest_banner();
		}
		if ( ! current_user_can( Youem_Access::PLAY ) ) {
			return '<div class="youem youem-notice">' . esc_html__( 'Your account is not allowed to play games.', 'you-emulator' ) . '</div>';
		}
		wp_enqueue_style( 'youem-library' );
		wp_enqueue_script( 'youem-library' );

		$ids     = self::accessible_ids();
		$counts  = self::console_counts( $ids );
		$genres  = $ids ? get_terms(
			array(
				'taxonomy'   => Youem_Game::TAX_GENRE,
				'object_ids' => $ids,
			)
		) : array();
		$data    = array(
			'console'    => self::console_code( (string) $atts['console'] ),
			'genre'      => sanitize_title( (string) $atts['genre'] ),
			'perPage'    => (int) $atts['per_page'] ? (int) $atts['per_page'] : (int) Youem_Settings::get( 'per_page' ),
			'playerMode' => Youem_Settings::get( 'player_mode' ),
		);
		$current = Youem_Themes::current( get_current_user_id() );

		ob_start();
		?>
		<div class="youem youem-library" data-youem-library="<?php echo esc_attr( wp_json_encode( $data ) ); ?>">
			<div class="youem-library__bar">
				<nav class="youem-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Consoles', 'you-emulator' ); ?>">
					<button type="button" role="tab" class="youem-tab" data-console="" aria-selected="<?php echo '' === $data['console'] ? 'true' : 'false'; ?>">
						<?php esc_html_e( 'All', 'you-emulator' ); ?> <span class="youem-tab__count"><?php echo (int) array_sum( $counts ); ?></span>
					</button>
					<?php foreach ( Youem_Consoles::all() as $code => $def ) : ?>
						<?php
						if ( empty( $counts[ $code ] ) ) {
							continue;
						}
						?>
						<button type="button" role="tab" class="youem-tab" data-console="<?php echo esc_attr( $code ); ?>" aria-selected="<?php echo $data['console'] === $code ? 'true' : 'false'; ?>" title="<?php echo esc_attr( $def['label'] ); ?>">
							<?php echo esc_html( $def['short'] ); ?> <span class="youem-tab__count"><?php echo (int) $counts[ $code ]; ?></span>
						</button>
					<?php endforeach; ?>
				</nav>
				<div class="youem-library__filters">
					<label class="youem-sr" for="youem-search"><?php esc_html_e( 'Search games', 'you-emulator' ); ?></label>
					<input type="search" id="youem-search" class="youem-input youem-search" placeholder="<?php esc_attr_e( 'Search games…', 'you-emulator' ); ?>" autocomplete="off">
					<?php if ( is_array( $genres ) && $genres ) : ?>
						<label class="youem-sr" for="youem-genre"><?php esc_html_e( 'Genre', 'you-emulator' ); ?></label>
						<select id="youem-genre" class="youem-input youem-genre">
							<option value=""><?php esc_html_e( 'All genres', 'you-emulator' ); ?></option>
							<?php foreach ( $genres as $genre ) : ?>
								<option value="<?php echo esc_attr( $genre->slug ); ?>" <?php selected( $data['genre'], $genre->slug ); ?>><?php echo esc_html( $genre->name ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
					<?php self::theme_select( $current ); ?>
				</div>
			</div>
			<div class="youem-grid" aria-live="polite" aria-busy="true"></div>
			<p class="youem-empty" hidden><?php esc_html_e( 'No games found.', 'you-emulator' ); ?></p>
			<div class="youem-more-wrap"><button type="button" class="youem-btn youem-more" hidden><?php esc_html_e( 'Load more', 'you-emulator' ); ?></button></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Resolves a console code case-insensitively.
	 *
	 * @param string $value Raw value.
	 */
	private static function console_code( string $value ): string {
		foreach ( array_keys( Youem_Consoles::all() ) as $code ) {
			if ( strtolower( $code ) === strtolower( trim( $value ) ) ) {
				return $code;
			}
		}
		return '';
	}

	/**
	 * Theme picker.
	 *
	 * @param string $current Current theme.
	 */
	private static function theme_select( string $current ): void {
		?>
		<label class="youem-sr" for="youem-theme-<?php echo esc_attr( wp_unique_id() ); ?>"><?php esc_html_e( 'Theme', 'you-emulator' ); ?></label>
		<select class="youem-input youem-theme-select" aria-label="<?php esc_attr_e( 'Theme', 'you-emulator' ); ?>">
			<?php foreach ( Youem_Themes::all() as $slug => $theme ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>><?php echo esc_html( $theme['name'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * IDs of the games the current user can play.
	 *
	 * @return int[]
	 */
	private static function accessible_ids(): array {
		$args = array_merge(
			Youem_Access::query_args( get_current_user_id() ),
			array(
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		return array_values( array_filter( array_map( 'intval', ( new WP_Query( $args ) )->posts ) ) );
	}

	/**
	 * Number of games per console.
	 *
	 * @param int[] $ids Game IDs.
	 * @return array<string, int>
	 */
	private static function console_counts( array $ids ): array {
		$counts = array();
		foreach ( $ids as $id ) {
			$c = Youem_Game::console( $id );
			if ( '' !== $c ) {
				$counts[ $c ] = ( $counts[ $c ] ?? 0 ) + 1;
			}
		}
		return $counts;
	}

	/**
	 * Access control, embed mode and isolation headers for game pages.
	 */
	public static function template_redirect(): void {
		if ( ! is_singular( Youem_Game::POST_TYPE ) ) {
			return;
		}
		$id = (int) get_queried_object_id();
		if ( ! is_user_logged_in() ) {
			nocache_headers();
			$library = self::library_url();
			if ( '' !== $library && ! isset( $_GET['youem_embed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_safe_redirect( $library );
				exit;
			}
			add_filter( 'pre_get_document_title', static fn() => __( 'Retro game library', 'you-emulator' ) );
			add_filter( 'the_title', static fn( $title, $post_id = 0 ) => Youem_Game::POST_TYPE === get_post_type( $post_id ) ? __( 'Retro game library', 'you-emulator' ) : $title, 10, 2 );
			return;
		}
		if ( ! current_user_can( 'youem_play_game', $id ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}
		nocache_headers();
		if ( is_user_logged_in() && self::needs_isolation( $id ) && ! headers_sent() ) {
			header( 'Cross-Origin-Opener-Policy: same-origin' );
			header( 'Cross-Origin-Embedder-Policy: ' . Youem_Settings::get( 'coep' ) );
		}
	}

	/**
	 * Whether the game needs SharedArrayBuffer (threaded core).
	 *
	 * @param int $id Game ID.
	 */
	public static function needs_isolation( int $id ): bool {
		if ( 'off' === Youem_Settings::get( 'coep' ) ) {
			return false;
		}
		$def = Youem_Consoles::get( Youem_Game::console( $id ) );
		return $def && ! empty( $def['threads'] );
	}

	/**
	 * Minimal template for the modal player iframe.
	 *
	 * @param string $template Template path.
	 */
	public static function embed_template( string $template ): string {
		if ( is_singular( Youem_Game::POST_TYPE ) && isset( $_GET['youem_embed'] ) && is_user_logged_in() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return YOUEM_DIR . 'templates/embed.php';
		}
		return $template;
	}

	/**
	 * Replaces game post content with the player.
	 *
	 * @param string $content Content.
	 */
	public static function game_content( string $content ): string {
		if ( ! is_singular( Youem_Game::POST_TYPE ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() ) {
			return $content;
		}
		return self::player( (int) get_the_ID(), $content );
	}

	/**
	 * Player markup and script config.
	 *
	 * @param int    $id      Game ID.
	 * @param string $content Description HTML.
	 */
	public static function player( int $id, string $content = '' ): string {
		self::enqueue_common();
		if ( ! is_user_logged_in() ) {
			return self::guest_banner();
		}
		if ( ! current_user_can( 'youem_play_game', $id ) ) {
			return '';
		}
		wp_enqueue_style( 'youem-player' );
		wp_enqueue_script( 'youem-player' );

		$console = Youem_Game::console( $id );
		$def     = Youem_Consoles::get( $console );
		$rom     = (string) get_post_meta( $id, '_youem_rom_path', true );
		$nonce   = wp_create_nonce( 'wp_rest' );
		$base    = rest_url( Youem_Rest::NS . '/games/' . $id );
		$s       = Youem_Settings::all();
		$bios    = $def && $def['bios'] && Youem_Bios::available( $console );
		$locale  = substr( determine_locale(), 0, 2 );
		$lang    = array(
			'ru' => 'ru-RU',
			'zh' => 'zh-CN',
			'de' => 'de-GER',
			'es' => 'es-ES',
			'it' => 'it-IT',
			'ja' => 'ja-JA',
			'ko' => 'ko-KO',
			'pt' => 'pt-BR',
		);
		$player  = array(
			'gameId'     => $id,
			'title'      => get_the_title( $id ),
			'console'    => $console,
			'core'       => Youem_Game::core( $id ),
			'threads'    => $def && ! empty( $def['threads'] ),
			'romUrl'     => $rom ? add_query_arg( '_wpnonce', $nonce, $base . '/rom/' . rawurlencode( wp_basename( $rom ) ) ) : '',
			'biosUrl'    => $bios ? add_query_arg( '_wpnonce', $nonce, $base . '/bios/bios.zip' ) : '',
			'biosNeeded' => $def && 'required' === $def['bios'] && ! in_array( Youem_Game::core( $id ), $def['hle_cores'] ?? array(), true ),
			'dataPath'   => $s['cdn_cores'] ? 'https://cdn.emulatorjs.org/' . YOUEM_EJS_VERSION . '/data/' : YOUEM_URL . 'vendor/emulatorjs/data/',
			'language'   => $lang[ $locale ] ?? 'en-US',
			'shader'     => $s['shader'],
			'volume'     => (float) $s['volume'],
			'combos'     => $s['combos'],
			'slots'      => Youem_Saves::slots( get_current_user_id(), $id ),
			'interval'   => (int) $s['save_interval'],
			'library'    => self::library_url(),
			'embed'      => isset( $_GET['youem_embed'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
		wp_add_inline_script( 'youem-player', 'window.youemPlayer = ' . wp_json_encode( $player ) . ';', 'before' );

		$meta = array_filter(
			array(
				$def ? $def['label'] : '',
				(string) get_post_meta( $id, '_youem_year', true ),
				(string) get_post_meta( $id, '_youem_developer', true ),
			)
		);
		ob_start();
		?>
		<div class="youem youem-game" data-game="<?php echo (int) $id; ?>">
			<div class="youem-stage">
				<div class="youem-stage__top">
					<?php if ( ! $player['embed'] && $player['library'] ) : ?>
						<a class="youem-icon-btn youem-back" href="<?php echo esc_url( $player['library'] ); ?>" aria-label="<?php esc_attr_e( 'Back to library', 'you-emulator' ); ?>">&larr;</a>
					<?php endif; ?>
					<span class="youem-stage__title"><?php echo esc_html( get_the_title( $id ) ); ?></span>
					<button type="button" class="youem-icon-btn youem-saves-toggle" aria-expanded="false" aria-controls="youem-saves-<?php echo (int) $id; ?>" title="<?php esc_attr_e( 'Save slots', 'you-emulator' ); ?>">&#9636;</button>
					<button type="button" class="youem-icon-btn youem-fullscreen" title="<?php esc_attr_e( 'Fullscreen (Shift+3)', 'you-emulator' ); ?>" aria-label="<?php esc_attr_e( 'Fullscreen', 'you-emulator' ); ?>">
						<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M4 9V4h5v2H6v3H4zm10-5h6v5h-2V6h-4V4zM4 15h2v3h3v2H4v-5zm14 3v-3h2v5h-6v-2h4z"/></svg>
					</button>
				</div>
				<div class="youem-screen">
					<div id="youem-ejs-<?php echo (int) $id; ?>" class="youem-ejs"></div>
					<?php if ( ! $rom ) : ?>
						<div class="youem-screen__msg"><?php esc_html_e( 'ROM file is missing.', 'you-emulator' ); ?></div>
					<?php elseif ( $player['biosNeeded'] && ! $bios ) : ?>
						<div class="youem-screen__msg"><?php esc_html_e( 'This system needs a BIOS. Ask the site administrator to upload it.', 'you-emulator' ); ?></div>
					<?php endif; ?>
				</div>
				<aside class="youem-saves" id="youem-saves-<?php echo (int) $id; ?>" hidden>
					<h3 class="youem-saves__title"><?php esc_html_e( 'Save slots', 'you-emulator' ); ?></h3>
					<ol class="youem-saves__list"></ol>
				</aside>
				<div class="youem-toast" role="status" aria-live="polite"></div>
			</div>
			<div class="youem-game__info">
				<div class="youem-hint">
					<span><kbd>Shift</kbd>+<kbd>1</kbd> <?php esc_html_e( 'quick save', 'you-emulator' ); ?></span>
					<span><kbd>Shift</kbd>+<kbd>2</kbd> <?php esc_html_e( 'quick load', 'you-emulator' ); ?></span>
					<span><kbd>Shift</kbd>+<kbd>3</kbd> <?php esc_html_e( 'fullscreen', 'you-emulator' ); ?></span>
					<span class="youem-hint__pad"><?php esc_html_e( 'Gamepad: LB+RB+X save, LB+RB+B load, LB+RB+Y fullscreen', 'you-emulator' ); ?></span>
				</div>
				<?php if ( $meta ) : ?>
					<p class="youem-game__meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></p>
				<?php endif; ?>
				<?php if ( ! $player['embed'] ) : ?>
					<div class="youem-game__desc"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content output. ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * URL of the first page containing the library.
	 */
	public static function library_url(): string {
		$url = (string) get_transient( 'youem_library_url' );
		if ( '' !== $url ) {
			return $url;
		}
		global $wpdb;
		$id  = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page','post') AND (post_content LIKE '%[youem_library%' OR post_content LIKE '%wp:you-emulator/library%') ORDER BY ID ASC LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$url = $id ? (string) get_permalink( $id ) : '';
		set_transient( 'youem_library_url', $url, HOUR_IN_SECONDS );
		return $url;
	}
}
