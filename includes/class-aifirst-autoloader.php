<?php
/**
 * Simple PSR-4-style autoloader for AIFirst_* classes.
 *
 * Maps class names like `AIFirst_Post_Types` to files like
 * `includes/class-aifirst-post-types.php` using WordPress file naming.
 *
 * @package AIFirst
 * @since   0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Autoloader.
 *
 * @since 0.1.0
 */
class AIFirst_Autoloader {

	/**
	 * Register the autoloader with SPL.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load_class' ) );
	}

	/**
	 * Resolve a class name to a file inside includes/ and require it.
	 *
	 * @since 0.1.0
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public static function load_class( $class_name ) {
		if ( 0 !== strpos( $class_name, 'AIFirst_' ) ) {
			return;
		}

		$file_slug = strtolower( str_replace( '_', '-', $class_name ) );
		$file_name = 'class-' . $file_slug . '.php';
		$file_path = AIFIRST_INCLUDES_DIR . $file_name;

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
}
