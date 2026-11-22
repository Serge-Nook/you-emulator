<?php
/**
 * Visual themes (CSS variables + animation presets).
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme registry and per-user choice.
 */
class Youem_Themes {

	const META = 'youem_theme';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'profile_field' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_field' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile' ) );
	}

	/**
	 * Registered themes keyed by slug.
	 *
	 * @return array<string, array{name:string,css:string,preview:string}>
	 */
	public static function all(): array {
		$themes = array();
		$names  = array(
			'neon'        => __( 'Neon', 'you-emulator' ),
			'retro-cream' => __( 'Retro cream', 'you-emulator' ),
			'dark'        => __( 'Dark', 'you-emulator' ),
			'crt'         => 'CRT',
		);
		foreach ( $names as $slug => $name ) {
			$themes[ $slug ] = array(
				'name'    => $name,
				'css'     => YOUEM_URL . 'public/themes/' . $slug . '/theme.css',
				'preview' => YOUEM_URL . 'public/themes/' . $slug . '/preview.png',
			);
		}
		/**
		 * Filters available themes. Each item: name, css (URL), preview (URL).
		 *
		 * @param array $themes Themes keyed by slug.
		 */
		return (array) apply_filters( 'youem_themes', $themes );
	}

	/**
	 * Theme of a user (falls back to the default).
	 *
	 * @param int $user_id User ID (0 = guest).
	 */
	public static function current( int $user_id = 0 ): string {
		$themes = self::all();
		if ( $user_id ) {
			$slug = (string) get_user_meta( $user_id, self::META, true );
			if ( isset( $themes[ $slug ] ) ) {
				return $slug;
			}
		}
		$default = (string) Youem_Settings::get( 'default_theme' );
		return isset( $themes[ $default ] ) ? $default : (string) array_key_first( $themes );
	}

	/**
	 * Stores a user's theme.
	 *
	 * @param int    $user_id User ID.
	 * @param string $slug    Theme slug or '' for default.
	 */
	public static function set( int $user_id, string $slug ): bool {
		if ( '' === $slug ) {
			return delete_user_meta( $user_id, self::META );
		}
		if ( ! isset( self::all()[ $slug ] ) ) {
			return false;
		}
		update_user_meta( $user_id, self::META, $slug );
		return true;
	}

	/**
	 * Adds the theme class to <body>.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public static function body_class( array $classes ): array {
		if ( Youem_Public::page_uses_plugin() ) {
			$classes[] = 'youem-theme-' . sanitize_html_class( self::current( get_current_user_id() ) );
		}
		return $classes;
	}

	/**
	 * Profile select.
	 *
	 * @param WP_User $user User.
	 */
	public static function profile_field( WP_User $user ): void {
		$current = (string) get_user_meta( $user->ID, self::META, true );
		wp_nonce_field( 'youem_profile_theme', 'youem_profile_theme_nonce' );
		?>
		<h2>You Emulator</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="youem_theme"><?php esc_html_e( 'Theme', 'you-emulator' ); ?></label></th>
				<td>
					<select name="youem_theme" id="youem_theme">
						<option value=""><?php esc_html_e( 'Site default', 'you-emulator' ); ?></option>
						<?php foreach ( self::all() as $slug => $theme ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>><?php echo esc_html( $theme['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Saves the profile select.
	 *
	 * @param int $user_id User ID.
	 */
	public static function save_profile( int $user_id ): void {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['youem_profile_theme_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['youem_profile_theme_nonce'] ) ), 'youem_profile_theme' ) ) {
			return;
		}
		self::set( $user_id, isset( $_POST['youem_theme'] ) ? sanitize_key( wp_unslash( $_POST['youem_theme'] ) ) : '' );
	}
}
