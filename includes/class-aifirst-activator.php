<?php
/**
 * Activation handler — creates custom tables via dbDelta().
 *
 * @package AIFirst
 * @since   0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Activator.
 *
 * @since 0.1.0
 */
class AIFirst_Activator {

	/**
	 * Run on plugin activation.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function activate() {
		self::create_tables();

		update_option( 'aifirst_db_version', AIFIRST_DB_VERSION );
		update_option( 'aifirst_show_activation_notice', 1 );

		// Ensure CPT + taxonomy rewrite rules are registered before flushing.
		require_once AIFIRST_INCLUDES_DIR . 'class-aifirst-post-types.php';
		require_once AIFIRST_INCLUDES_DIR . 'class-aifirst-taxonomies.php';
		( new AIFirst_Post_Types() )->register();
		( new AIFirst_Taxonomies() )->register();

		flush_rewrite_rules();
	}

	/**
	 * Create all 6 custom tables.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	protected static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix . 'aifirst_';

		$sql = array();

		$sql[] = "CREATE TABLE {$prefix}tool_meta (
			meta_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tool_id VARCHAR(191) NOT NULL,
			post_id BIGINT UNSIGNED NOT NULL,
			company_name VARCHAR(255) NOT NULL DEFAULT '',
			website_url VARCHAR(500) NOT NULL DEFAULT '',
			pricing_url VARCHAR(500) NOT NULL DEFAULT '',
			initials VARCHAR(8) NOT NULL DEFAULT '',
			avatar_color VARCHAR(7) NOT NULL DEFAULT '',
			logo_url VARCHAR(500) NOT NULL DEFAULT '',
			wp_org_slug VARCHAR(191) NOT NULL DEFAULT '',
			subcategory VARCHAR(191) NOT NULL DEFAULT '',
			founded_year SMALLINT UNSIGNED NULL,
			headquarters VARCHAR(255) NOT NULL DEFAULT '',
			pricing_model VARCHAR(50) NOT NULL DEFAULT '',
			has_free_plan TINYINT(1) NOT NULL DEFAULT 0,
			starting_price_usd DECIMAL(10,2) NULL,
			pricing_note TEXT NULL,
			ai_maturity_score TINYINT UNSIGNED NULL,
			ai_since SMALLINT UNSIGNED NULL,
			ai_type VARCHAR(20) NOT NULL DEFAULT '',
			ai_on_free_plan TINYINT(1) NOT NULL DEFAULT 0,
			total_ai_features SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			native_ai_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			thirdparty_ai_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			last_ai_update DATE NULL,
			last_verified DATE NULL,
			company_size_smb TINYINT(1) NOT NULL DEFAULT 0,
			company_size_mid TINYINT(1) NOT NULL DEFAULT 0,
			company_size_ent TINYINT(1) NOT NULL DEFAULT 0,
			notable_customers TEXT NULL,
			best_for TEXT NULL,
			expert_take TEXT NULL,
			meta_title VARCHAR(255) NOT NULL DEFAULT '',
			meta_description VARCHAR(500) NOT NULL DEFAULT '',
			h1 VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (meta_id),
			UNIQUE KEY uniq_tool_id (tool_id),
			UNIQUE KEY uniq_post_id (post_id),
			KEY idx_pricing_model (pricing_model),
			KEY idx_has_free_plan (has_free_plan),
			KEY idx_ai_maturity (ai_maturity_score),
			KEY idx_ai_type (ai_type),
			KEY idx_last_verified (last_verified)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}plans (
			plan_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tool_id VARCHAR(191) NOT NULL,
			plan_name VARCHAR(100) NOT NULL,
			tier_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
			price_usd_monthly DECIMAL(10,2) NULL,
			price_usd_annual DECIMAL(10,2) NULL,
			billing_cycle VARCHAR(20) NOT NULL DEFAULT '',
			user_limit VARCHAR(50) NOT NULL DEFAULT '',
			feature_list TEXT NULL,
			notes TEXT NULL,
			is_free TINYINT(1) NOT NULL DEFAULT 0,
			is_most_popular TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (plan_id),
			KEY idx_tool_tier (tool_id, tier_order)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}faq (
			faq_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tool_id VARCHAR(191) NOT NULL DEFAULT '',
			category_id VARCHAR(191) NOT NULL DEFAULT '',
			faq_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
			question VARCHAR(500) NOT NULL,
			answer TEXT NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (faq_id),
			KEY idx_tool (tool_id, faq_order),
			KEY idx_category (category_id, faq_order)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}radar (
			radar_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tool_id VARCHAR(191) NOT NULL,
			change_date DATE NULL,
			change_type VARCHAR(30) NOT NULL DEFAULT '',
			what_changed TEXT NOT NULL,
			why_it_matters TEXT NULL,
			source_url VARCHAR(500) NOT NULL DEFAULT '',
			version_label VARCHAR(50) NOT NULL DEFAULT '',
			confidence VARCHAR(20) NOT NULL DEFAULT 'medium',
			row_status VARCHAR(20) NOT NULL DEFAULT 'draft',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (radar_id),
			KEY idx_tool_date (tool_id, change_date),
			KEY idx_change_date (change_date),
			KEY idx_status (row_status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}cannot_do (
			cannot_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tool_id VARCHAR(191) NOT NULL,
			order_index TINYINT UNSIGNED NOT NULL DEFAULT 1,
			limitation_text TEXT NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (cannot_id),
			KEY idx_tool (tool_id, order_index)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}relationships (
			relationship_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_tool_id VARCHAR(191) NOT NULL,
			target_tool_id VARCHAR(191) NOT NULL,
			relationship_type VARCHAR(20) NOT NULL DEFAULT '',
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (relationship_id),
			UNIQUE KEY uniq_relationship (source_tool_id, target_tool_id, relationship_type),
			KEY idx_source (source_tool_id, relationship_type),
			KEY idx_target (target_tool_id, relationship_type)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}
}
