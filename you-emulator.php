<?php
/**
 * Plugin Name:       You Emulator
 * Plugin URI:        https://github.com/Serge-Nook/you-emulator
 * Description:       Retro game library for WordPress powered by EmulatorJS: NES, SNES, Sega Genesis, GBA, PlayStation, PSP and N64 right in the browser, with server-side save states, hotkeys and gamepad combos.
 * Version:           1.0.4
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Горшков Сергей Владимирович
 * Author URI:        https://nookbat.ru
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       you-emulator
 * Domain Path:       /languages
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;

define( 'YOUEM_VERSION', '1.0.4' );
define( 'YOUEM_DB_VERSION', '1' );
define( 'YOUEM_EJS_VERSION', '4.2.3' );
define( 'YOUEM_FILE', __FILE__ );
define( 'YOUEM_DIR', plugin_dir_path( __FILE__ ) );
define( 'YOUEM_URL', plugin_dir_url( __FILE__ ) );

require_once YOUEM_DIR . 'includes/class-youem-consoles.php';
require_once YOUEM_DIR . 'includes/class-youem-storage.php';
require_once YOUEM_DIR . 'includes/class-youem-zip.php';
require_once YOUEM_DIR . 'includes/class-youem-validator.php';
require_once YOUEM_DIR . 'includes/class-youem-settings.php';
require_once YOUEM_DIR . 'includes/class-youem-controls.php';
require_once YOUEM_DIR . 'includes/class-youem-game.php';
require_once YOUEM_DIR . 'includes/class-youem-access.php';
require_once YOUEM_DIR . 'includes/class-youem-saves.php';
require_once YOUEM_DIR . 'includes/class-youem-upload.php';
require_once YOUEM_DIR . 'includes/class-youem-bios.php';
require_once YOUEM_DIR . 'includes/class-youem-covers.php';
require_once YOUEM_DIR . 'includes/class-youem-themes.php';
require_once YOUEM_DIR . 'includes/class-youem-stream.php';
require_once YOUEM_DIR . 'includes/class-youem-rest.php';
require_once YOUEM_DIR . 'includes/class-youem-public.php';
require_once YOUEM_DIR . 'includes/class-youem-admin.php';
require_once YOUEM_DIR . 'includes/class-youem-activator.php';

register_activation_hook( __FILE__, array( 'Youem_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Youem_Activator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'you-emulator', false, dirname( plugin_basename( YOUEM_FILE ) ) . '/languages' );
		Youem_Activator::maybe_upgrade();
		Youem_Game::init();
		Youem_Access::init();
		Youem_Saves::init();
		Youem_Upload::init();
		Youem_Themes::init();
		Youem_Rest::init();
		Youem_Public::init();
		if ( is_admin() ) {
			Youem_Admin::init();
		}
	}
);
