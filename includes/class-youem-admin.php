<?php
/**
 * WordPress admin: game editor, list table, BIOS, saves, settings, about.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens.
 */
class Youem_Admin {

	const PARENT = 'edit.php?post_type=youem_game';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'add_meta_boxes_' . Youem_Game::POST_TYPE, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . Youem_Game::POST_TYPE, array( __CLASS__, 'save_game' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'manage_' . Youem_Game::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Youem_Game::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'list_filter' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_list_filter' ) );
		add_action( 'admin_post_youem_bios_upload', array( __CLASS__, 'bios_upload' ) );
		add_action( 'admin_post_youem_bios_delete', array( __CLASS__, 'bios_delete' ) );
		add_action( 'admin_post_youem_save_delete', array( __CLASS__, 'save_delete' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( YOUEM_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	/**
	 * Submenus.
	 */
	public static function menu(): void {
		add_submenu_page( self::PARENT, __( 'BIOS files', 'you-emulator' ), 'BIOS', Youem_Access::MANAGE, 'youem-bios', array( __CLASS__, 'page_bios' ) );
		add_submenu_page( self::PARENT, __( 'Save states', 'you-emulator' ), __( 'Saves', 'you-emulator' ), Youem_Access::MANAGE, 'youem-saves', array( __CLASS__, 'page_saves' ) );
		add_submenu_page( self::PARENT, __( 'You Emulator settings', 'you-emulator' ), __( 'Settings', 'you-emulator' ), 'manage_options', 'youem-settings', array( __CLASS__, 'page_settings' ) );
		add_submenu_page( self::PARENT, __( 'About You Emulator', 'you-emulator' ), __( 'About', 'you-emulator' ), Youem_Access::MANAGE, 'youem-about', array( __CLASS__, 'page_about' ) );
	}

	/**
	 * Plugins list links.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( self::PARENT . '&page=youem-settings' ) ) . '">' . esc_html__( 'Settings', 'you-emulator' ) . '</a>' );
		return $links;
	}

	/**
	 * Title placeholder.
	 *
	 * @param string  $text Placeholder.
	 * @param WP_Post $post Post.
	 */
	public static function title_placeholder( string $text, WP_Post $post ): string {
		return Youem_Game::POST_TYPE === $post->post_type ? __( 'Game title', 'you-emulator' ) : $text;
	}

	/**
	 * Admin notices from redirects.
	 */
	public static function notices(): void {
		if ( empty( $_GET['youem_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$msg   = sanitize_text_field( wp_unslash( $_GET['youem_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error = ! empty( $_GET['youem_err'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $error ? 'error' : 'success', esc_html( $msg ) );
	}

	/**
	 * Admin assets.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || Youem_Game::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'youem-admin', YOUEM_URL . 'admin/css/admin.css', array(), YOUEM_VERSION );
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'youem-uploader', YOUEM_URL . 'admin/js/uploader.js', array(), YOUEM_VERSION, true );
			wp_enqueue_script( 'youem-admin', YOUEM_URL . 'admin/js/admin.js', array( 'youem-uploader', 'wp-i18n', 'jquery' ), YOUEM_VERSION, true );
			wp_set_script_translations( 'youem-admin', 'you-emulator', YOUEM_DIR . 'languages' );
			$consoles = array();
			foreach ( Youem_Consoles::all() as $code => $def ) {
				$consoles[ $code ] = array(
					'label' => $def['label'],
					'cores' => $def['cores'],
					'ext'   => $def['extensions'],
					'bios'  => $def['bios'],
				);
			}
			wp_add_inline_script(
				'youem-admin',
				'window.youemAdmin = ' . wp_json_encode(
					array(
						'rest'      => esc_url_raw( rest_url( Youem_Rest::NS . '/' ) ),
						'nonce'     => wp_create_nonce( 'wp_rest' ),
						'chunkSize' => Youem_Upload::chunk_size(),
						'maxSize'   => Youem_Upload::max_size(),
						'consoles'  => $consoles,
						'provider'  => Youem_Settings::get( 'cover_provider' ),
					)
				) . ';',
				'before'
			);
		} elseif ( 'youem_game_page_youem-settings' === $hook ) {
			wp_enqueue_script( 'youem-settings', YOUEM_URL . 'admin/js/settings.js', array(), YOUEM_VERSION, true );
		}
	}

	/**
	 * Registers game meta boxes.
	 */
	public static function meta_boxes(): void {
		$pt = Youem_Game::POST_TYPE;
		add_meta_box( 'youem-rom', __( 'ROM file', 'you-emulator' ), array( __CLASS__, 'box_rom' ), $pt, 'normal', 'high' );
		add_meta_box( 'youem-details', __( 'Details', 'you-emulator' ), array( __CLASS__, 'box_details' ), $pt, 'normal', 'default' );
		add_meta_box( 'youem-cover', __( 'Cover', 'you-emulator' ), array( __CLASS__, 'box_cover' ), $pt, 'side', 'default' );
		add_meta_box( 'youem-access', __( 'Access', 'you-emulator' ), array( __CLASS__, 'box_access' ), $pt, 'side', 'default' );
	}

	/**
	 * ROM box: console, core, chunked uploader.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function box_rom( WP_Post $post ): void {
		wp_nonce_field( 'youem_save_game', 'youem_game_nonce' );
		$console = Youem_Game::console( $post->ID );
		$core    = (string) get_post_meta( $post->ID, '_youem_core', true );
		$name    = (string) get_post_meta( $post->ID, '_youem_rom_name', true );
		$size    = (int) get_post_meta( $post->ID, '_youem_rom_size', true );
		?>
		<div class="youem-rom" data-game="<?php echo (int) $post->ID; ?>">
			<p class="youem-row">
				<label for="youem_console"><strong><?php esc_html_e( 'Console', 'you-emulator' ); ?></strong></label>
				<select name="youem_console" id="youem_console" required>
					<option value=""><?php esc_html_e( '— Select —', 'you-emulator' ); ?></option>
					<?php foreach ( Youem_Consoles::all() as $code => $def ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $console, $code ); ?>><?php echo esc_html( $def['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="youem_core"><strong><?php esc_html_e( 'Core', 'you-emulator' ); ?></strong></label>
				<select name="youem_core" id="youem_core" data-current="<?php echo esc_attr( $core ); ?>">
					<option value=""><?php esc_html_e( 'Default', 'you-emulator' ); ?></option>
					<?php foreach ( Youem_Consoles::all_cores() as $c ) : ?>
						<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $core, $c ); ?>><?php echo esc_html( $c ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="youem-rom__current">
				<?php if ( $name ) : ?>
					<?php
					/* translators: 1: file name, 2: size. */
					printf( esc_html__( 'Current file: %1$s (%2$s)', 'you-emulator' ), '<code>' . esc_html( $name ) . '</code>', esc_html( size_format( $size, 1 ) ) );
					?>
				<?php else : ?>
					<?php esc_html_e( 'No ROM uploaded yet.', 'you-emulator' ); ?>
				<?php endif; ?>
			</p>
			<div class="youem-drop" tabindex="0" role="button" aria-describedby="youem-drop-help">
				<input type="file" class="youem-drop__input" multiple hidden>
				<strong><?php esc_html_e( 'Drop ROM files here or click to choose', 'you-emulator' ); ?></strong>
				<span id="youem-drop-help" class="youem-drop__help"></span>
			</div>
			<ul class="youem-uploads"></ul>
			<p class="description"><?php esc_html_e( 'Large files are sent in resumable chunks. For PS1 select the .cue together with all its .bin tracks.', 'you-emulator' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Details box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function box_details( WP_Post $post ): void {
		$fields = array(
			'year'      => __( 'Year', 'you-emulator' ),
			'developer' => __( 'Developer', 'you-emulator' ),
			'players'   => __( 'Players', 'you-emulator' ),
		);
		echo '<div class="youem-details">';
		foreach ( $fields as $key => $label ) {
			printf(
				'<p><label for="youem_%1$s">%2$s</label><input type="text" class="widefat" id="youem_%1$s" name="youem_%1$s" value="%3$s"></p>',
				esc_attr( $key ),
				esc_html( $label ),
				esc_attr( (string) get_post_meta( $post->ID, '_youem_' . $key, true ) )
			);
		}
		echo '</div>';
	}

	/**
	 * Cover box: media library + auto fill.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function box_cover( WP_Post $post ): void {
		$id  = (int) get_post_meta( $post->ID, '_youem_cover_id', true );
		$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		?>
		<div class="youem-cover">
			<input type="hidden" name="youem_cover_id" id="youem_cover_id" value="<?php echo (int) $id; ?>">
			<input type="hidden" name="youem_cover_source" id="youem_cover_source" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, '_youem_cover_source', true ) ); ?>">
			<div class="youem-cover__preview"><?php echo $src ? '<img src="' . esc_url( $src ) . '" alt="">' : ''; ?></div>
			<p>
				<button type="button" class="button youem-cover__pick"><?php esc_html_e( 'Choose cover', 'you-emulator' ); ?></button>
				<button type="button" class="button-link-delete youem-cover__remove" <?php echo $id ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'you-emulator' ); ?></button>
			</p>
			<p>
				<button type="button" class="button youem-scrape" <?php disabled( 'none', Youem_Settings::get( 'cover_provider' ) ); ?>><?php esc_html_e( 'Auto-fill from database', 'you-emulator' ); ?></button>
				<span class="spinner"></span>
			</p>
			<p class="description youem-scrape__msg">
				<?php
				if ( 'none' === Youem_Settings::get( 'cover_provider' ) ) {
					esc_html_e( 'Set up TheGamesDB or ScreenScraper in Settings to enable auto-fill.', 'you-emulator' );
				}
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Access box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function box_access( WP_Post $post ): void {
		$vis   = 'private' === get_post_meta( $post->ID, '_youem_visibility', true ) ? 'private' : 'public';
		$users = array_map( 'intval', (array) get_post_meta( $post->ID, '_youem_allowed_users', false ) );
		$roles = array_map( 'strval', (array) get_post_meta( $post->ID, '_youem_allowed_roles', false ) );
		?>
		<div class="youem-access">
			<p>
				<label><input type="radio" name="youem_visibility" value="public" <?php checked( $vis, 'public' ); ?>> <?php esc_html_e( 'All logged-in users', 'you-emulator' ); ?></label><br>
				<label><input type="radio" name="youem_visibility" value="private" <?php checked( $vis, 'private' ); ?>> <?php esc_html_e( 'Only selected users and roles', 'you-emulator' ); ?></label>
			</p>
			<div class="youem-access__private" <?php echo 'private' === $vis ? '' : 'hidden'; ?>>
				<p><strong><?php esc_html_e( 'Roles', 'you-emulator' ); ?></strong></p>
				<?php foreach ( wp_roles()->get_names() as $role => $name ) : ?>
					<label class="youem-access__role"><input type="checkbox" name="youem_roles[]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $roles, true ) ); ?>> <?php echo esc_html( translate_user_role( $name ) ); ?></label>
				<?php endforeach; ?>
				<p><label for="youem-user-search"><strong><?php esc_html_e( 'Users', 'you-emulator' ); ?></strong></label></p>
				<input type="search" id="youem-user-search" class="widefat youem-user-search" placeholder="<?php esc_attr_e( 'Search users…', 'you-emulator' ); ?>" autocomplete="off">
				<ul class="youem-user-results"></ul>
				<ul class="youem-user-chips">
					<?php foreach ( $users as $uid ) : ?>
						<?php
						$u = get_userdata( $uid );
						if ( ! $u ) {
							continue;
						}
						?>
						<li><input type="hidden" name="youem_users[]" value="<?php echo (int) $uid; ?>"><?php echo esc_html( $u->display_name . ' (' . $u->user_login . ')' ); ?> <button type="button" class="youem-chip-remove" aria-label="<?php esc_attr_e( 'Remove', 'you-emulator' ); ?>">&times;</button></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Saves meta box fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save_game( int $post_id, WP_Post $post ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! isset( $_POST['youem_game_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['youem_game_nonce'] ) ), 'youem_save_game' ) || ! current_user_can( Youem_Access::MANAGE ) ) {
			return;
		}
		$console = isset( $_POST['youem_console'] ) ? sanitize_text_field( wp_unslash( $_POST['youem_console'] ) ) : '';
		if ( Youem_Consoles::exists( $console ) ) {
			Youem_Upload::set_console( $post_id, $console );
		}
		$core = isset( $_POST['youem_core'] ) ? sanitize_key( wp_unslash( $_POST['youem_core'] ) ) : '';
		$def  = Youem_Consoles::get( $console );
		if ( $def && in_array( $core, $def['cores'], true ) ) {
			update_post_meta( $post_id, '_youem_core', $core );
		} else {
			delete_post_meta( $post_id, '_youem_core' );
		}
		foreach ( array( 'year', 'developer', 'players' ) as $key ) {
			if ( isset( $_POST[ 'youem_' . $key ] ) ) {
				update_post_meta( $post_id, '_youem_' . $key, sanitize_text_field( wp_unslash( $_POST[ 'youem_' . $key ] ) ) );
			}
		}
		$cover = isset( $_POST['youem_cover_id'] ) ? absint( $_POST['youem_cover_id'] ) : 0;
		if ( $cover && wp_attachment_is_image( $cover ) ) {
			update_post_meta( $post_id, '_youem_cover_id', $cover );
			$source = isset( $_POST['youem_cover_source'] ) ? sanitize_key( wp_unslash( $_POST['youem_cover_source'] ) ) : 'manual';
			update_post_meta( $post_id, '_youem_cover_source', 'scraped' === $source ? 'scraped' : 'manual' );
		} else {
			delete_post_meta( $post_id, '_youem_cover_id' );
			delete_post_meta( $post_id, '_youem_cover_source' );
		}
		$vis = isset( $_POST['youem_visibility'] ) && 'private' === $_POST['youem_visibility'] ? 'private' : 'public';
		update_post_meta( $post_id, '_youem_visibility', $vis );
		delete_post_meta( $post_id, '_youem_allowed_users' );
		delete_post_meta( $post_id, '_youem_allowed_roles' );
		if ( 'private' === $vis ) {
			$users = isset( $_POST['youem_users'] ) ? array_unique( array_map( 'absint', (array) wp_unslash( $_POST['youem_users'] ) ) ) : array();
			foreach ( $users as $uid ) {
				if ( $uid && get_userdata( $uid ) ) {
					add_post_meta( $post_id, '_youem_allowed_users', $uid );
				}
			}
			$valid = array_keys( wp_roles()->roles );
			$roles = isset( $_POST['youem_roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['youem_roles'] ) ) : array();
			foreach ( array_intersect( array_unique( $roles ), $valid ) as $role ) {
				add_post_meta( $post_id, '_youem_allowed_roles', $role );
			}
		}
		delete_transient( 'youem_library_url' );
	}

	/**
	 * List columns.
	 *
	 * @param array<string, string> $cols Columns.
	 * @return array<string, string>
	 */
	public static function columns( array $cols ): array {
		$out = array();
		foreach ( $cols as $key => $label ) {
			if ( 'title' === $key ) {
				$out['youem_cover'] = '<span class="screen-reader-text">' . esc_html__( 'Cover', 'you-emulator' ) . '</span>';
			}
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['youem_console']    = __( 'Console', 'you-emulator' );
				$out['youem_genres']     = __( 'Genres', 'you-emulator' );
				$out['youem_rom']        = 'ROM';
				$out['youem_visibility'] = __( 'Access', 'you-emulator' );
			}
		}
		return $out;
	}

	/**
	 * Column values.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Post ID.
	 */
	public static function column( string $col, int $post_id ): void {
		switch ( $col ) {
			case 'youem_cover':
				$cover = Youem_Game::cover( $post_id, 'thumbnail' );
				echo $cover ? '<img class="youem-thumb" src="' . esc_url( $cover['src'] ) . '" alt="" loading="lazy">' : '<span class="youem-thumb youem-thumb--empty"></span>';
				break;
			case 'youem_console':
				$def = Youem_Consoles::get( Youem_Game::console( $post_id ) );
				echo $def ? esc_html( $def['label'] ) : '&mdash;';
				break;
			case 'youem_genres':
				echo esc_html( implode( ', ', wp_list_pluck( Youem_Game::genres( $post_id ), 'name' ) ) );
				break;
			case 'youem_rom':
				$size = (int) get_post_meta( $post_id, '_youem_rom_size', true );
				echo $size ? esc_html( size_format( $size, 1 ) ) : '<span class="youem-warn">' . esc_html__( 'missing', 'you-emulator' ) . '</span>';
				break;
			case 'youem_visibility':
				echo 'private' === get_post_meta( $post_id, '_youem_visibility', true ) ? esc_html__( 'Private', 'you-emulator' ) : esc_html__( 'All users', 'you-emulator' );
				break;
		}
	}

	/**
	 * Console filter dropdown.
	 *
	 * @param string $post_type Post type.
	 */
	public static function list_filter( string $post_type ): void {
		if ( Youem_Game::POST_TYPE !== $post_type ) {
			return;
		}
		$current = isset( $_GET['youem_console'] ) ? sanitize_text_field( wp_unslash( $_GET['youem_console'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<select name="youem_console"><option value="">' . esc_html__( 'All consoles', 'you-emulator' ) . '</option>';
		foreach ( Youem_Consoles::all() as $code => $def ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $code ), selected( $current, $code, false ), esc_html( $def['label'] ) );
		}
		echo '</select>';
	}

	/**
	 * Applies the console filter.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function apply_list_filter( WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() || Youem_Game::POST_TYPE !== $q->get( 'post_type' ) || empty( $_GET['youem_console'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$q->set(
			'tax_query',
			array(
				array(
					'taxonomy' => Youem_Game::TAX_CON,
					'field'    => 'slug',
					'terms'    => Youem_Consoles::term_slug( sanitize_text_field( wp_unslash( $_GET['youem_console'] ) ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				),
			)
		);
	}

	/**
	 * Redirects back with a notice.
	 *
	 * @param string $page  Page slug.
	 * @param string $msg   Message.
	 * @param bool   $error Error flag.
	 */
	private static function back( string $page, string $msg, bool $error = false ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => Youem_Game::POST_TYPE,
					'page'      => $page,
					'youem_msg' => rawurlencode( $msg ),
					'youem_err' => $error ? 1 : 0,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * BIOS page.
	 */
	public static function page_bios(): void {
		?>
		<div class="wrap youem-wrap">
			<h1><?php esc_html_e( 'BIOS files', 'you-emulator' ); ?></h1>
			<p><?php esc_html_e( 'BIOS files are stored in a protected folder and are sent only to logged-in players of the matching system.', 'you-emulator' ); ?></p>
			<?php foreach ( Youem_Consoles::all() as $code => $def ) : ?>
				<?php
				if ( 'none' === $def['bios'] ) {
					continue;
				}
				$files = Youem_Bios::files( $code );
				?>
				<div class="youem-card">
					<h2><?php echo esc_html( $def['label'] ); ?>
						<span class="youem-badge <?php echo $files ? 'is-ok' : ( 'required' === $def['bios'] ? 'is-bad' : '' ); ?>">
							<?php
							if ( $files ) {
								esc_html_e( 'Installed', 'you-emulator' );
							} elseif ( 'required' === $def['bios'] ) {
								esc_html_e( 'Required', 'you-emulator' );
							} else {
								esc_html_e( 'Optional', 'you-emulator' );
							}
							?>
						</span>
					</h2>
					<p class="description">
						<?php
						/* translators: %s: list of file names. */
						printf( esc_html__( 'Expected files: %s', 'you-emulator' ), '<code>' . esc_html( implode( ', ', $def['bios_files'] ) ) . '</code>' );
						?>
					</p>
					<?php if ( $files ) : ?>
						<table class="widefat striped">
							<thead><tr><th><?php esc_html_e( 'File', 'you-emulator' ); ?></th><th><?php esc_html_e( 'Size', 'you-emulator' ); ?></th><th>MD5</th><th></th></tr></thead>
							<tbody>
							<?php foreach ( $files as $f ) : ?>
								<tr>
									<td><code><?php echo esc_html( $f['name'] ); ?></code></td>
									<td><?php echo esc_html( size_format( $f['size'], 1 ) ); ?></td>
									<td><code><?php echo esc_html( $f['md5'] ?? '' ); ?></code></td>
									<td>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<?php wp_nonce_field( 'youem_bios_delete' ); ?>
											<input type="hidden" name="action" value="youem_bios_delete">
											<input type="hidden" name="system" value="<?php echo esc_attr( $code ); ?>">
											<input type="hidden" name="file" value="<?php echo esc_attr( $f['name'] ); ?>">
											<button class="button-link-delete" type="submit"><?php esc_html_e( 'Delete', 'you-emulator' ); ?></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
					<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="youem-bios-form">
						<?php wp_nonce_field( 'youem_bios_upload' ); ?>
						<input type="hidden" name="action" value="youem_bios_upload">
						<input type="hidden" name="system" value="<?php echo esc_attr( $code ); ?>">
						<input type="file" name="bios[]" multiple required>
						<button class="button button-primary" type="submit"><?php esc_html_e( 'Upload', 'you-emulator' ); ?></button>
					</form>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Handles BIOS upload.
	 */
	public static function bios_upload(): void {
		check_admin_referer( 'youem_bios_upload' );
		if ( ! current_user_can( Youem_Access::MANAGE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'you-emulator' ), 403 );
		}
		$system = isset( $_POST['system'] ) ? sanitize_key( wp_unslash( $_POST['system'] ) ) : '';
		$files  = $_FILES['bios'] ?? array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$done   = 0;
		$errors = array();
		foreach ( (array) ( $files['name'] ?? array() ) as $i => $name ) {
			if ( UPLOAD_ERR_OK !== (int) $files['error'][ $i ] ) {
				continue;
			}
			$res = Youem_Bios::store( $system, (string) $files['tmp_name'][ $i ], sanitize_file_name( (string) $name ) );
			if ( is_wp_error( $res ) ) {
				$errors[] = $res->get_error_message();
			} else {
				++$done;
			}
		}
		if ( $errors ) {
			self::back( 'youem-bios', implode( ' ', $errors ), true );
		}
		/* translators: %d: number of files. */
		self::back( 'youem-bios', sprintf( _n( '%d BIOS file uploaded.', '%d BIOS files uploaded.', $done, 'you-emulator' ), $done ), 0 === $done );
	}

	/**
	 * Handles BIOS deletion.
	 */
	public static function bios_delete(): void {
		check_admin_referer( 'youem_bios_delete' );
		if ( ! current_user_can( Youem_Access::MANAGE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'you-emulator' ), 403 );
		}
		$ok = Youem_Bios::delete( isset( $_POST['system'] ) ? sanitize_key( wp_unslash( $_POST['system'] ) ) : '', isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '' );
		self::back( 'youem-bios', $ok ? __( 'BIOS file deleted.', 'you-emulator' ) : __( 'Could not delete the file.', 'you-emulator' ), ! $ok );
	}

	/**
	 * Saves page.
	 */
	public static function page_saves(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page    = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$game_id = isset( $_GET['game'] ) ? absint( $_GET['game'] ) : 0;
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'updated_at';
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';
		// phpcs:enable
		$per  = 30;
		$data = Youem_Saves::admin_rows( $page, $per, $orderby, $order, $game_id );
		$sort = static function ( string $key, string $label ) use ( $orderby, $order ) {
			$next = ( $orderby === $key && 'desc' === strtolower( $order ) ) ? 'asc' : 'desc';
			return '<a href="' . esc_url(
				add_query_arg(
					array(
						'orderby' => $key,
						'order'   => $next,
					)
				)
			) . '">' . esc_html( $label ) . ( $orderby === $key ? ( 'asc' === strtolower( $order ) ? ' ▲' : ' ▼' ) : '' ) . '</a>';
		};
		?>
		<div class="wrap youem-wrap">
			<h1><?php esc_html_e( 'Save states', 'you-emulator' ); ?></h1>
			<?php if ( $game_id ) : ?>
				<p><?php echo esc_html( get_the_title( $game_id ) ); ?> — <a href="<?php echo esc_url( remove_query_arg( array( 'game', 'paged' ) ) ); ?>"><?php esc_html_e( 'show all', 'you-emulator' ); ?></a></p>
			<?php endif; ?>
			<table class="widefat striped youem-saves-table">
				<thead>
				<tr>
					<th><?php echo $sort( 'user_id', __( 'User', 'you-emulator' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<th><?php echo $sort( 'game_id', __( 'Game', 'you-emulator' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<th><?php echo $sort( 'slot', __( 'Slot', 'you-emulator' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<th><?php echo $sort( 'state_size', __( 'Size', 'you-emulator' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<th><?php echo $sort( 'updated_at', __( 'Updated', 'you-emulator' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
					<th></th>
				</tr>
				</thead>
				<tbody>
				<?php if ( ! $data['rows'] ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No saves yet.', 'you-emulator' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $data['rows'] as $row ) : ?>
					<?php $user = get_userdata( (int) $row['user_id'] ); ?>
					<tr>
						<td><?php echo $user ? esc_html( $user->display_name . ' (' . $user->user_login . ')' ) : '#' . (int) $row['user_id']; ?></td>
						<td><a href="<?php echo esc_url( add_query_arg( 'game', (int) $row['game_id'] ) ); ?>"><?php echo esc_html( get_the_title( (int) $row['game_id'] ) ); ?></a></td>
						<td><?php echo 0 === (int) $row['slot'] ? esc_html__( 'Quick', 'you-emulator' ) : (int) $row['slot']; ?></td>
						<td><?php echo esc_html( size_format( (int) $row['state_size'] + (int) $row['screenshot_size'], 1 ) ); ?></td>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['updated_at'] . ' UTC' ) ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'youem_save_delete' ); ?>
								<input type="hidden" name="action" value="youem_save_delete">
								<input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
								<button class="button-link-delete" type="submit"><?php esc_html_e( 'Delete', 'you-emulator' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			$links = paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $page,
					'total'   => (int) ceil( $data['total'] / $per ),
				)
			);
			if ( $links ) {
				echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Deletes a save state.
	 */
	public static function save_delete(): void {
		check_admin_referer( 'youem_save_delete' );
		if ( ! current_user_can( Youem_Access::MANAGE ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'you-emulator' ), 403 );
		}
		$ok = Youem_Saves::delete_by_id( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		self::back( 'youem-saves', $ok ? __( 'Save deleted.', 'you-emulator' ) : __( 'Could not delete the save.', 'you-emulator' ), ! $ok );
	}

	/**
	 * Settings API registration.
	 */
	public static function register_settings(): void {
		register_setting(
			'youem_settings',
			Youem_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Youem_Settings', 'sanitize' ),
				'default'           => Youem_Settings::defaults(),
			)
		);
	}

	/**
	 * Settings field name.
	 *
	 * @param string $key Key.
	 */
	private static function name( string $key ): string {
		return Youem_Settings::OPTION . '[' . $key . ']';
	}

	/**
	 * Settings page.
	 */
	public static function page_settings(): void {
		$s   = Youem_Settings::all();
		$n   = array( __CLASS__, 'name' );
		$pad = array(
			0  => 'A',
			1  => 'B',
			2  => 'X',
			3  => 'Y',
			4  => 'LB',
			5  => 'RB',
			6  => 'LT',
			7  => 'RT',
			8  => 'Select',
			9  => 'Start',
			10 => 'L3',
			11 => 'R3',
			12 => '↑',
			13 => '↓',
			14 => '←',
			15 => '→',
			16 => 'Home',
		);
		?>
		<div class="wrap youem-wrap">
			<h1><?php esc_html_e( 'You Emulator settings', 'you-emulator' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'youem_settings' ); ?>
				<h2 class="title"><?php esc_html_e( 'Library and player', 'you-emulator' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="youem-per-page"><?php esc_html_e( 'Games per page', 'you-emulator' ); ?></label></th>
						<td><input type="number" min="6" max="120" id="youem-per-page" name="<?php echo esc_attr( $n( 'per_page' ) ); ?>" value="<?php echo (int) $s['per_page']; ?>"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Open games', 'you-emulator' ); ?></th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( $n( 'player_mode' ) ); ?>" value="page" <?php checked( $s['player_mode'], 'page' ); ?>> <?php esc_html_e( 'On a separate page', 'you-emulator' ); ?></label><br>
							<label><input type="radio" name="<?php echo esc_attr( $n( 'player_mode' ) ); ?>" value="modal" <?php checked( $s['player_mode'], 'modal' ); ?>> <?php esc_html_e( 'In a popup over the library', 'you-emulator' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><label for="youem-theme"><?php esc_html_e( 'Default theme', 'you-emulator' ); ?></label></th>
						<td>
							<select id="youem-theme" name="<?php echo esc_attr( $n( 'default_theme' ) ); ?>">
								<?php foreach ( Youem_Themes::all() as $slug => $theme ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $s['default_theme'], $slug ); ?>><?php echo esc_html( $theme['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Players can pick their own theme in the library or in their profile.', 'you-emulator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="youem-shader"><?php esc_html_e( 'Video shader', 'you-emulator' ); ?></label></th>
						<td>
							<select id="youem-shader" name="<?php echo esc_attr( $n( 'shader' ) ); ?>">
								<?php foreach ( Youem_Settings::shaders() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['shader'], $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="youem-volume"><?php esc_html_e( 'Default volume', 'you-emulator' ); ?></label></th>
						<td><input type="range" min="0" max="1" step="0.05" id="youem-volume" name="<?php echo esc_attr( $n( 'volume' ) ); ?>" value="<?php echo esc_attr( (string) $s['volume'] ); ?>"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Who can play', 'you-emulator' ); ?></th>
						<td>
							<?php foreach ( wp_roles()->get_names() as $role => $label ) : ?>
								<label class="youem-inline"><input type="checkbox" name="<?php echo esc_attr( $n( 'play_roles' ) ); ?>[]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, $s['play_roles'], true ) ); ?>> <?php echo esc_html( translate_user_role( $label ) ); ?></label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Leave all unchecked to allow every logged-in user.', 'you-emulator' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Guest banner', 'you-emulator' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="youem-banner-title"><?php esc_html_e( 'Title', 'you-emulator' ); ?></label></th>
						<td><input type="text" class="regular-text" id="youem-banner-title" name="<?php echo esc_attr( $n( 'guest_banner_title' ) ); ?>" value="<?php echo esc_attr( $s['guest_banner_title'] ); ?>"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Text', 'you-emulator' ); ?></th>
						<td>
							<?php
							wp_editor(
								$s['guest_banner_html'],
								'youem_guest_banner_html',
								array(
									'textarea_name' => $n( 'guest_banner_html' ),
									'textarea_rows' => 6,
									'media_buttons' => true,
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Shown instead of the library to visitors who are not logged in. Log in and sign up buttons are added automatically.', 'you-emulator' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Gamepad shortcuts', 'you-emulator' ); ?></h2>
				<p><?php esc_html_e( 'Keyboard: Shift+1 quick save, Shift+2 quick load, Shift+3 fullscreen.', 'you-emulator' ); ?></p>
				<table class="form-table youem-combos" role="presentation">
					<?php
					$labels = array(
						'save'       => __( 'Quick save', 'you-emulator' ),
						'load'       => __( 'Quick load', 'you-emulator' ),
						'fullscreen' => __( 'Fullscreen', 'you-emulator' ),
					);
					foreach ( $labels as $action => $label ) :
						?>
						<tr>
							<th><?php echo esc_html( $label ); ?></th>
							<td>
								<?php foreach ( $pad as $btn => $btn_label ) : ?>
									<label class="youem-pad-btn"><input type="checkbox" name="<?php echo esc_attr( $n( 'combos' ) . '[' . $action . ']' ); ?>[]" value="<?php echo (int) $btn; ?>" <?php checked( in_array( $btn, $s['combos'][ $action ], true ) ); ?>> <?php echo esc_html( $btn_label ); ?></label>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<p class="description"><?php esc_html_e( 'Buttons use the standard gamepad layout (Xbox names). Pick at least two buttons per action.', 'you-emulator' ); ?></p>

				<h2 class="title"><?php esc_html_e( 'Limits', 'you-emulator' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="youem-quota"><?php esc_html_e( 'Save quota per user, MB', 'you-emulator' ); ?></label></th>
						<td><input type="number" min="1" id="youem-quota" name="<?php echo esc_attr( $n( 'save_quota_mb' ) ); ?>" value="<?php echo (int) $s['save_quota_mb']; ?>"></td>
					</tr>
					<tr>
						<th><label for="youem-interval"><?php esc_html_e( 'Minimum seconds between saves', 'you-emulator' ); ?></label></th>
						<td><input type="number" min="0" id="youem-interval" name="<?php echo esc_attr( $n( 'save_interval' ) ); ?>" value="<?php echo (int) $s['save_interval']; ?>"></td>
					</tr>
					<tr>
						<th><label for="youem-sessions"><?php esc_html_e( 'Games a user may open within 15 minutes', 'you-emulator' ); ?></label></th>
						<td><input type="number" min="1" id="youem-sessions" name="<?php echo esc_attr( $n( 'rom_sessions' ) ); ?>" value="<?php echo (int) $s['rom_sessions']; ?>"></td>
					</tr>
					<tr>
						<th><label for="youem-chunk"><?php esc_html_e( 'Upload chunk size, MB', 'you-emulator' ); ?></label></th>
						<td>
							<input type="number" min="1" max="64" id="youem-chunk" name="<?php echo esc_attr( $n( 'chunk_mb' ) ); ?>" value="<?php echo (int) $s['chunk_mb']; ?>">
							<p class="description">
								<?php
								/* translators: %s: size. */
								printf( esc_html__( 'Server upload limit: %s. The effective chunk size is never larger.', 'you-emulator' ), esc_html( size_format( wp_max_upload_size() ) ) );
								?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Metadata and covers', 'you-emulator' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="youem-provider"><?php esc_html_e( 'Provider', 'you-emulator' ); ?></label></th>
						<td>
							<select id="youem-provider" name="<?php echo esc_attr( $n( 'cover_provider' ) ); ?>">
								<option value="none" <?php selected( $s['cover_provider'], 'none' ); ?>><?php esc_html_e( 'Off (manual covers only)', 'you-emulator' ); ?></option>
								<option value="thegamesdb" <?php selected( $s['cover_provider'], 'thegamesdb' ); ?>>TheGamesDB</option>
								<option value="screenscraper" <?php selected( $s['cover_provider'], 'screenscraper' ); ?>>ScreenScraper</option>
							</select>
						</td>
					</tr>
					<tr class="youem-provider-thegamesdb">
						<th><label for="youem-tgdb"><?php esc_html_e( 'TheGamesDB API key', 'you-emulator' ); ?></label></th>
						<td><input type="password" class="regular-text" id="youem-tgdb" name="<?php echo esc_attr( $n( 'tgdb_key' ) ); ?>" value="<?php echo esc_attr( $s['tgdb_key'] ); ?>" autocomplete="off"></td>
					</tr>
					<?php
					$ss = array(
						'ss_devid'       => __( 'ScreenScraper developer ID', 'you-emulator' ),
						'ss_devpassword' => __( 'ScreenScraper developer password', 'you-emulator' ),
						'ss_user'        => __( 'ScreenScraper user (optional)', 'you-emulator' ),
						'ss_password'    => __( 'ScreenScraper password (optional)', 'you-emulator' ),
					);
					foreach ( $ss as $key => $label ) :
						?>
						<tr class="youem-provider-screenscraper">
							<th><label for="youem-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input type="<?php echo str_contains( $key, 'password' ) ? 'password' : 'text'; ?>" class="regular-text" id="youem-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $n( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" autocomplete="off"></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2 class="title"><?php esc_html_e( 'Advanced', 'you-emulator' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="youem-coep"><?php esc_html_e( 'Cross-origin isolation (PSP)', 'you-emulator' ); ?></label></th>
						<td>
							<select id="youem-coep" name="<?php echo esc_attr( $n( 'coep' ) ); ?>">
								<option value="require-corp" <?php selected( $s['coep'], 'require-corp' ); ?>>require-corp</option>
								<option value="credentialless" <?php selected( $s['coep'], 'credentialless' ); ?>>credentialless</option>
								<option value="off" <?php selected( $s['coep'], 'off' ); ?>><?php esc_html_e( 'Off', 'you-emulator' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'The PSP core needs SharedArrayBuffer, which browsers enable only on HTTPS pages sent with COOP/COEP headers.', 'you-emulator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Emulator files', 'you-emulator' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $n( 'cdn_cores' ) ); ?>" value="1" <?php checked( $s['cdn_cores'] ); ?>> <?php esc_html_e( 'Load EmulatorJS from cdn.emulatorjs.org instead of the plugin folder', 'you-emulator' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Uninstall', 'you-emulator' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $n( 'uninstall_purge' ) ); ?>" value="1" <?php checked( $s['uninstall_purge'] ); ?>> <?php esc_html_e( 'Delete all games, ROMs, BIOS and saves when the plugin is deleted', 'you-emulator' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * About page.
	 */
	public static function page_about(): void {
		$cores = array();
		$dir   = YOUEM_DIR . 'vendor/emulatorjs/data/cores/';
		foreach ( Youem_Consoles::all() as $def ) {
			foreach ( $def['cores'] as $core ) {
				$cores[ $core ] = (bool) glob( $dir . $core . '-*wasm.data' );
			}
		}
		?>
		<div class="wrap youem-wrap youem-about">
			<h1>You Emulator <?php echo esc_html( YOUEM_VERSION ); ?></h1>
			<p><?php esc_html_e( 'Retro game library for WordPress powered by EmulatorJS.', 'you-emulator' ); ?></p>
			<div class="youem-card">
				<h2><?php esc_html_e( 'Author', 'you-emulator' ); ?></h2>
				<p><?php esc_html_e( 'Sergey Gorshkov', 'you-emulator' ); ?> — <a href="https://nookbat.ru" target="_blank" rel="noopener">nookbat.ru</a></p>
				<p><a href="https://github.com/Serge-Nook/you-emulator" target="_blank" rel="noopener">github.com/Serge-Nook/you-emulator</a></p>
			</div>
			<div class="youem-card">
				<h2>EmulatorJS <?php echo esc_html( YOUEM_EJS_VERSION ); ?></h2>
				<p>
					<?php esc_html_e( 'The emulator is EmulatorJS, distributed under the GNU General Public License v3.0. Cores are libretro projects under their own licenses.', 'you-emulator' ); ?>
					<a href="https://github.com/EmulatorJS/EmulatorJS" target="_blank" rel="noopener">github.com/EmulatorJS/EmulatorJS</a>
				</p>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Core', 'you-emulator' ); ?></th><th><?php esc_html_e( 'Bundled', 'you-emulator' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $cores as $core => $ok ) : ?>
						<tr><td><code><?php echo esc_html( $core ); ?></code></td><td><?php echo $ok ? '✓' : '—'; ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<div class="youem-card">
				<h2><?php esc_html_e( 'How to use', 'you-emulator' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'Upload BIOS files for PS1 (and optionally GBA).', 'you-emulator' ); ?></li>
					<li><?php esc_html_e( 'Add games: title, console, ROM file, cover, genres and access.', 'you-emulator' ); ?></li>
					<li><?php esc_html_e( 'Put the [youem_library] shortcode or the "Retro game library" block on any page.', 'you-emulator' ); ?></li>
				</ol>
			</div>
			<p class="description"><?php esc_html_e( 'Only upload games you have the right to distribute.', 'you-emulator' ); ?></p>
		</div>
		<?php
	}
}
