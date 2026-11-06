<?php
/**
 * Deactivation handler.
 *
 * @package AIFirst
 * @since   0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Deactivator.
 *
 * Non-destructive: clears scheduled events and flushes rewrite rules.
 * Data and tables are preserved — those are removed only via uninstall.php.
 *
 * @since 0.1.0
 */
class AIFirst_Deactivator {

	/**
	 * Run on plugin deactivation.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'aifirst_daily_maintenance' );
		wp_clear_scheduled_hook( 'aifirst_hourly_maintenance' );

		flush_rewrite_rules();
	}
}
