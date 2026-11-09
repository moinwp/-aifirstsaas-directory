<?php
/**
 * Uninstall routine — drops custom tables and removes plugin options.
 *
 * Runs only when the plugin is deleted via the WP admin UI (not on deactivate).
 *
 * @package AIFirst
 * @since   0.1.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$aifirst_tables = array(
	$wpdb->prefix . 'aifirst_tool_meta',
	$wpdb->prefix . 'aifirst_plans',
	$wpdb->prefix . 'aifirst_faq',
	$wpdb->prefix . 'aifirst_radar',
	$wpdb->prefix . 'aifirst_cannot_do',
	$wpdb->prefix . 'aifirst_relationships',
	$wpdb->prefix . 'aifirst_tool_features',
	$wpdb->prefix . 'aifirst_maturity_breakdown',
	$wpdb->prefix . 'aifirst_industries',
	$wpdb->prefix . 'aifirst_sentiment',
	$wpdb->prefix . 'aifirst_quotes',
	$wpdb->prefix . 'aifirst_category_meta',
	$wpdb->prefix . 'aifirst_feature_meta',
	$wpdb->prefix . 'aifirst_submissions',
	$wpdb->prefix . 'aifirst_reports',
	$wpdb->prefix . 'aifirst_subscribers',
);

foreach ( $aifirst_tables as $aifirst_table ) {
	// Table names cannot be parameterized via $wpdb->prepare(); each name
	// is built from a trusted prefix and a hardcoded suffix above.
	$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $aifirst_table ) . '`' );
}

$aifirst_options = array(
	'aifirst_db_version',
	'aifirst_show_activation_notice',
);

foreach ( $aifirst_options as $aifirst_option ) {
	delete_option( $aifirst_option );
	delete_site_option( $aifirst_option );
}
