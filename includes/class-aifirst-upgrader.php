<?php
/**
 * Plugin version → schema upgrader.
 *
 * Fires on plugins_loaded. If the stored aifirst_db_version is lower than
 * AIFIRST_DB_VERSION, re-runs AIFirst_Activator::create_tables() and any
 * version-specific follow-ups, then bumps the stored version.
 *
 * @package AIFirst
 * @since   0.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Upgrader.
 *
 * @since 0.3.0
 */
class AIFirst_Upgrader {

	const OPTION = 'aifirst_db_version';

	/**
	 * Wire the check.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function register() {
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ), 5 );
	}

	/**
	 * Run upgrades if the stored version is behind the plugin constant.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public function maybe_upgrade() {
		$stored = (string) get_option( self::OPTION, '0.0.0' );
		if ( version_compare( $stored, AIFIRST_DB_VERSION, '>=' ) ) {
			return;
		}

		require_once AIFIRST_INCLUDES_DIR . 'class-aifirst-activator.php';
		AIFirst_Activator::create_tables();
		AIFirst_Activator::migrate_post_dbdelta();

		update_option( self::OPTION, AIFIRST_DB_VERSION );
	}
}
