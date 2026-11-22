<?php
/**
 * Plugin settings storage.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and sanitizes the youem_settings option.
 */
class Youem_Settings {

	const OPTION = 'youem_settings';

	/**
	 * Cached settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Default values.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'shader'             => 'disabled',
			'volume'             => 0.5,
			'per_page'           => 24,
			'player_mode'        => 'page',
			'default_theme'      => 'neon',
			'guest_banner_title' => __( 'Retro games are waiting for you', 'you-emulator' ),
			'guest_banner_html'  => __( 'The game library is available to registered users only. Sign up or log in to play NES, SNES, Sega Genesis, GBA, PlayStation, PSP and N64 games right in your browser, with saves synced to your profile.', 'you-emulator' ) . "\n\n[youem_button text=\"" . __( 'Get access', 'you-emulator' ) . '" url="/wp-login.php?action=register"] [youem_button text="' . __( 'Log in', 'you-emulator' ) . '" url="/wp-login.php" style="outline"]',
			'combos'             => array(
				'save'       => array( 4, 5, 2 ),
				'load'       => array( 4, 5, 1 ),
				'fullscreen' => array( 4, 5, 3 ),
			),
			'play_roles'         => array(),
			'rom_sessions'       => 3,
			'save_quota_mb'      => 512,
			'save_interval'      => 5,
			'chunk_mb'           => 8,
			'coep'               => 'require-corp',
			'cover_provider'     => 'none',
			'tgdb_key'           => '',
			'ss_devid'           => '',
			'ss_devpassword'     => '',
			'ss_user'            => '',
			'ss_password'        => '',
			'cdn_cores'          => false,
			'uninstall_purge'    => false,
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Drops the in-memory cache.
	 */
	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * Shader presets offered in settings (EmulatorJS shader ids).
	 *
	 * @return array<string, string>
	 */
	public static function shaders(): array {
		return array(
			'disabled'           => __( 'Off', 'you-emulator' ),
			'crt-easymode.glslp' => 'CRT Easymode',
			'crt-aperture.glslp' => 'CRT Aperture',
			'crt-geom.glslp'     => 'CRT Geom',
			'crt-mattias.glslp'  => 'CRT Mattias',
			'2xScaleHQ.glslp'    => '2xScaleHQ',
			'4xScaleHQ.glslp'    => '4xScaleHQ',
		);
	}

	/**
	 * Parses a combo definition into a list of button indexes (last one is the trigger).
	 *
	 * @param mixed $value Array or comma-separated string.
	 * @return int[]|null
	 */
	public static function parse_combo( $value ): ?array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,+]+/', trim( $value ) );
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$buttons = array();
		foreach ( $value as $b ) {
			if ( '' === $b || ! is_numeric( $b ) ) {
				continue;
			}
			$b = (int) $b;
			if ( $b < 0 || $b > 31 ) {
				return null;
			}
			$buttons[] = $b;
		}
		$buttons = array_values( array_unique( $buttons ) );
		return count( $buttons ) >= 2 ? $buttons : null;
	}

	/**
	 * Sanitizes the option on save.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$d   = self::defaults();
		$in  = is_array( $input ) ? $input : array();
		$out = array();

		$out['shader']             = array_key_exists( (string) ( $in['shader'] ?? '' ), self::shaders() ) ? (string) $in['shader'] : $d['shader'];
		$out['volume']             = max( 0.0, min( 1.0, (float) ( $in['volume'] ?? $d['volume'] ) ) );
		$out['per_page']           = max( 6, min( 120, (int) ( $in['per_page'] ?? $d['per_page'] ) ) );
		$out['player_mode']        = in_array( $in['player_mode'] ?? '', array( 'page', 'modal' ), true ) ? $in['player_mode'] : 'page';
		$themes                    = Youem_Themes::all();
		$out['default_theme']      = isset( $themes[ $in['default_theme'] ?? '' ] ) ? (string) $in['default_theme'] : $d['default_theme'];
		$out['guest_banner_title'] = sanitize_text_field( (string) ( $in['guest_banner_title'] ?? '' ) );
		$out['guest_banner_html']  = wp_kses_post( (string) ( $in['guest_banner_html'] ?? '' ) );

		$combos = array();
		foreach ( array( 'save', 'load', 'fullscreen' ) as $action ) {
			$parsed            = self::parse_combo( $in['combos'][ $action ] ?? null );
			$combos[ $action ] = $parsed ?? $d['combos'][ $action ];
		}
		$out['combos'] = $combos;

		$roles             = array_keys( wp_roles()->roles );
		$out['play_roles'] = array_values( array_intersect( (array) ( $in['play_roles'] ?? array() ), $roles ) );

		$out['rom_sessions']    = max( 1, min( 50, (int) ( $in['rom_sessions'] ?? $d['rom_sessions'] ) ) );
		$out['save_quota_mb']   = max( 1, min( 102400, (int) ( $in['save_quota_mb'] ?? $d['save_quota_mb'] ) ) );
		$out['save_interval']   = max( 0, min( 3600, (int) ( $in['save_interval'] ?? $d['save_interval'] ) ) );
		$out['chunk_mb']        = max( 1, min( 64, (int) ( $in['chunk_mb'] ?? $d['chunk_mb'] ) ) );
		$out['coep']            = in_array( $in['coep'] ?? '', array( 'require-corp', 'credentialless', 'off' ), true ) ? $in['coep'] : $d['coep'];
		$out['cover_provider']  = in_array( $in['cover_provider'] ?? '', array( 'none', 'thegamesdb', 'screenscraper' ), true ) ? $in['cover_provider'] : 'none';
		$out['tgdb_key']        = sanitize_text_field( (string) ( $in['tgdb_key'] ?? '' ) );
		$out['ss_devid']        = sanitize_text_field( (string) ( $in['ss_devid'] ?? '' ) );
		$out['ss_devpassword']  = sanitize_text_field( (string) ( $in['ss_devpassword'] ?? '' ) );
		$out['ss_user']         = sanitize_text_field( (string) ( $in['ss_user'] ?? '' ) );
		$out['ss_password']     = sanitize_text_field( (string) ( $in['ss_password'] ?? '' ) );
		$out['cdn_cores']       = ! empty( $in['cdn_cores'] );
		$out['uninstall_purge'] = ! empty( $in['uninstall_purge'] );

		self::flush();
		return $out;
	}
}
