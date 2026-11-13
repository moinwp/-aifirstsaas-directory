<?php
/**
 * Activation handler — creates/migrates custom tables via dbDelta().
 *
 * create_tables() is idempotent and runs on both activation and version-bump
 * upgrades (see AIFirst_Upgrader). Non-dbDelta migrations (column type
 * changes) live in migrate_post_dbdelta().
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
		self::migrate_post_dbdelta();

		update_option( 'aifirst_db_version', AIFIRST_DB_VERSION );
		update_option( 'aifirst_show_activation_notice', 1 );

		require_once AIFIRST_INCLUDES_DIR . 'class-aifirst-post-types.php';
		require_once AIFIRST_INCLUDES_DIR . 'class-aifirst-taxonomies.php';
		require_once AIFIRST_INCLUDES_DIR . 'class-aifirst-public.php';
		require_once AIFIRST_INCLUDES_DIR . 'class-aifirst-comparison.php';
		( new AIFirst_Post_Types() )->register();
		( new AIFirst_Taxonomies() )->register();
		( new AIFirst_Public() )->add_rewrites();
		( new AIFirst_Comparison() )->register_post_type();

		flush_rewrite_rules();
	}

	/**
	 * Create (or upgrade) every custom table. Idempotent.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function create_tables() {
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
			company_size_smb TINYINT UNSIGNED NOT NULL DEFAULT 0,
			company_size_mid TINYINT UNSIGNED NOT NULL DEFAULT 0,
			company_size_ent TINYINT UNSIGNED NOT NULL DEFAULT 0,
			company_group_id VARCHAR(191) NOT NULL DEFAULT '',
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
			KEY idx_last_verified (last_verified),
			KEY idx_company_group (company_group_id)
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
			workaround TEXT NULL,
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

		// --- 0.3.0 new tables ---

		$sql[] = "CREATE TABLE {$prefix}tool_features (
			feature_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tool_id VARCHAR(191) NOT NULL,
			feature_slug VARCHAR(191) NOT NULL,
			feature_name VARCHAR(255) NOT NULL DEFAULT '',
			plan VARCHAR(50) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT '',
			is_native TINYINT(1) NOT NULL DEFAULT 1,
			since VARCHAR(20) NOT NULL DEFAULT '',
			feature_note VARCHAR(500) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (feature_id),
			UNIQUE KEY uniq_tool_feature (tool_id, feature_slug),
			KEY idx_feature (feature_slug),
			KEY idx_status (status),
			KEY idx_plan (plan)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}maturity_breakdown (
			breakdown_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tool_id VARCHAR(191) NOT NULL,
			order_index TINYINT UNSIGNED NOT NULL DEFAULT 1,
			criterion VARCHAR(100) NOT NULL,
			score_display VARCHAR(20) NOT NULL DEFAULT '',
			score_numeric DECIMAL(5,2) NULL,
			score_color VARCHAR(20) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (breakdown_id),
			KEY idx_tool (tool_id, order_index)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}industries (
			industry_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tool_id VARCHAR(191) NOT NULL,
			order_index TINYINT UNSIGNED NOT NULL DEFAULT 1,
			name VARCHAR(100) NOT NULL,
			pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (industry_id),
			KEY idx_tool (tool_id, order_index)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}sentiment (
			sentiment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tool_id VARCHAR(191) NOT NULL,
			source VARCHAR(50) NOT NULL,
			score DECIMAL(3,2) NULL,
			review_count INT UNSIGNED NOT NULL DEFAULT 0,
			source_url VARCHAR(500) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (sentiment_id),
			UNIQUE KEY uniq_tool_source (tool_id, source),
			KEY idx_tool (tool_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}quotes (
			quote_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tool_id VARCHAR(191) NOT NULL,
			order_index TINYINT UNSIGNED NOT NULL DEFAULT 1,
			quote_text TEXT NOT NULL,
			role VARCHAR(100) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (quote_id),
			KEY idx_tool (tool_id, order_index)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}category_meta (
			cat_meta_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			term_id BIGINT UNSIGNED NOT NULL,
			icon VARCHAR(20) NOT NULL DEFAULT '',
			intro TEXT NULL,
			what_ai_does_well TEXT NULL,
			whats_immature TEXT NULL,
			honest_observation TEXT NULL,
			most_common_ai VARCHAR(255) NOT NULL DEFAULT '',
			fastest_growing VARCHAR(255) NOT NULL DEFAULT '',
			buyers_checklist TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (cat_meta_id),
			UNIQUE KEY uniq_term (term_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}submissions (
			submission_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			kind VARCHAR(10) NOT NULL DEFAULT 'new',
			tool_id VARCHAR(191) NOT NULL DEFAULT '',
			body LONGTEXT NULL,
			payload LONGTEXT NULL,
			email VARCHAR(190) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			source_ip VARCHAR(45) NOT NULL DEFAULT '',
			reviewed_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (submission_id),
			KEY idx_status (status, created_at),
			KEY idx_tool (tool_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}reports (
			report_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tool_id VARCHAR(191) NOT NULL,
			kind VARCHAR(60) NOT NULL DEFAULT '',
			body TEXT NOT NULL,
			email VARCHAR(190) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			source_ip VARCHAR(45) NOT NULL DEFAULT '',
			reviewed_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (report_id),
			KEY idx_tool (tool_id, created_at),
			KEY idx_status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}subscribers (
			sub_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			email VARCHAR(190) NOT NULL,
			scope VARCHAR(32) NOT NULL DEFAULT 'all',
			scope_target VARCHAR(191) NOT NULL DEFAULT '',
			source VARCHAR(64) NOT NULL DEFAULT '',
			confirmed TINYINT(1) NOT NULL DEFAULT 0,
			confirmed_at DATETIME NULL,
			source_ip VARCHAR(45) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (sub_id),
			UNIQUE KEY uniq_email (email),
			KEY idx_scope (scope, scope_target),
			KEY idx_confirmed (confirmed)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}feature_meta (
			feat_meta_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			term_id BIGINT UNSIGNED NOT NULL,
			color VARCHAR(7) NOT NULL DEFAULT '',
			feature_type VARCHAR(30) NOT NULL DEFAULT '',
			description TEXT NULL,
			why_matters TEXT NULL,
			evaluate TEXT NULL,
			related_features TEXT NULL,
			how_it_works_surface TEXT NULL,
			how_it_works_deeper TEXT NULL,
			vendors_dont_tell_you TEXT NULL,
			buyers_checklist TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (feat_meta_id),
			UNIQUE KEY uniq_term (term_id),
			KEY idx_type (feature_type)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Follow-up migrations that dbDelta can't reliably do on its own.
	 *
	 * @since 0.3.0
	 * @return void
	 */
	public static function migrate_post_dbdelta() {
		global $wpdb;
		$tool_meta = $wpdb->prefix . 'aifirst_tool_meta';

		// company_size_* flipped from boolean (TINYINT(1)) to percentage
		// (TINYINT UNSIGNED 0-100). dbDelta usually catches the type change,
		// but we also widen explicitly in case an older schema snapshot kept
		// a tight display width. This is idempotent.
		$cols = array( 'company_size_smb', 'company_size_mid', 'company_size_ent' );
		foreach ( $cols as $col ) {
			$col_safe = preg_replace( '/[^a-z_0-9]/i', '', $col );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$tool_meta} MODIFY COLUMN `{$col_safe}` TINYINT UNSIGNED NOT NULL DEFAULT 0" );
		}

		// Session 5: existing reports / submissions rows used the old
		// default 'pending'. Session 5 contract is 'new' / reviewed /
		// resolved / rejected (reports) and 'new' / approved / rejected
		// (submissions). Backfill in place — idempotent.
		// @since 0.5.0
		$reports     = $wpdb->prefix . 'aifirst_reports';
		$submissions = $wpdb->prefix . 'aifirst_submissions';
		$wpdb->query( $wpdb->prepare( "UPDATE {$reports} SET status = %s WHERE status = %s", 'new', 'pending' ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$submissions} SET status = %s WHERE status = %s", 'new', 'pending' ) );

		// Session 7.2: add `payload` LONGTEXT to submissions on existing
		// installs. dbDelta usually catches it, but belt-and-braces in
		// case the host's MySQL grammar silently ignored the ADD.
		// @since 0.8.0
		$has_payload = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
					WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
				DB_NAME,
				$submissions,
				'payload'
			)
		);
		if ( 0 === (int) $has_payload ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "ALTER TABLE {$submissions} ADD COLUMN payload LONGTEXT NULL AFTER body" );
		}
	}
}
