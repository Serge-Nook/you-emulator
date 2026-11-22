<?php
/**
 * PHPUnit bootstrap (WP_Mock).
 *
 * @package YouEmulator
 */

$youem_autoload = getenv( 'YOUEM_AUTOLOAD' ) ? getenv( 'YOUEM_AUTOLOAD' ) : dirname( __DIR__ ) . '/tools/vendor/autoload.php';
require_once $youem_autoload;

define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'KB_IN_BYTES', 1024 );
define( 'MB_IN_BYTES', 1024 * KB_IN_BYTES );
define( 'YOUEM_DIR', dirname( __DIR__ ) . '/' );

WP_Mock::bootstrap();

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error.
	 */
	class WP_Error {
		/**
		 * Constructor.
		 *
		 * @param string $code    Code.
		 * @param string $message Message.
		 * @param mixed  $data    Data.
		 */
		public function __construct( public string $code = '', public string $message = '', public $data = null ) {}

		/**
		 * Error code.
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * Error data.
		 */
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal WP_Post.
	 */
	class WP_Post {
		/**
		 * ID.
		 *
		 * @var int
		 */
		public $ID = 0;
		/**
		 * Type.
		 *
		 * @var string
		 */
		public $post_type = 'post';
		/**
		 * Status.
		 *
		 * @var string
		 */
		public $post_status = 'publish';
		/**
		 * Password.
		 *
		 * @var string
		 */
		public $post_password = '';
	}
}

foreach ( array( 'consoles', 'game', 'storage', 'zip', 'validator', 'settings', 'access', 'stream', 'upload' ) as $youem_class ) {
	require_once YOUEM_DIR . 'includes/class-youem-' . $youem_class . '.php';
}
