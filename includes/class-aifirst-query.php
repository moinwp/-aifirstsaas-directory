<?php
/**
 * Read-side query helpers for frontend templates.
 *
 * Every public query goes through here instead of inline SQL in templates,
 * so prepared statements stay in one place and template files stay readable.
 *
 * @package AIFirst
 * @since   0.4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Query.
 *
 * @since 0.4.0
 */
class AIFirst_Query {

	/**
	 * Return the {prefix}aifirst_tool_meta row for a post.
	 *
	 * @since 0.4.0
	 * @param int $post_id Post ID.
	 * @return object|null
	 */
	public static function tool_meta( $post_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_tool_meta';
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d LIMIT 1", (int) $post_id )
		);
	}

	/**
	 * Return the {prefix}aifirst_tool_meta row for a tool_id slug.
	 *
	 * @since 0.4.0
	 * @param string $tool_id Slug.
	 * @return object|null
	 */
	public static function tool_meta_by_id( $tool_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_tool_meta';
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE tool_id = %s LIMIT 1", (string) $tool_id )
		);
	}

	/**
	 * Return all tool_features for a tool.
	 *
	 * @since 0.4.0
	 * @param string $tool_id Slug.
	 * @return array
	 */
	public static function tool_features( $tool_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_tool_features';
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE tool_id = %s ORDER BY feature_name ASC", (string) $tool_id )
		);
	}

	/**
	 * Pricing plans for a tool, ordered by tier.
	 *
	 * @since 0.4.0
	 * @param string $tool_id Slug.
	 * @return array
	 */
	public static function plans( $tool_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_plans';
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE tool_id = %s ORDER BY tier_order ASC", (string) $tool_id )
		);
	}

	/**
	 * AI Maturity breakdown rows for a tool.
	 *
	 * @since 0.4.0
	 * @param string $tool_id Slug.
	 * @return array
	 */
	public static function maturity_breakdown( $tool_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_maturity_breakdown';
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE tool_id = %s ORDER BY order_index ASC", (string) $tool_id )
		);
	}

	/**
	 * Cannot-do rows for a tool.
	 *
	 * @since 0.4.0
	 * @param string $tool_id Slug.
	 * @return array
	 */
	public static function cannot_do( $tool_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_cannot_do';
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE tool_id = %s ORDER BY order_index ASC", (string) $tool_id )
		);
	}

	/**
	 * Industry percentages for a tool.
	 *
	 * @since 0.4.0
	 * @param string $tool_id Slug.
	 * @return array
	 */
	public static function industries( $tool_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_industries';
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE tool_id = %s ORDER BY order_index ASC", (string) $tool_id )
		);
	}

	/**
	 * Review-source sentiment rows for a tool.
	 *
	 * @since 0.4.0
	 * @param string $tool_id Slug.
	 * @return array
	 */
	public static function sentiment( $tool_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_sentiment';
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE tool_id = %s ORDER BY source ASC", (string) $tool_id )
		);
	}

	/**
	 * User quotes for a tool.
	 *
	 * @since 0.4.0
	 * @param string $tool_id Slug.
	 * @return array
	 */
	public static function quotes( $tool_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_quotes';
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE tool_id = %s ORDER BY order_index ASC", (string) $tool_id )
		);
	}

	/**
	 * Radar entries for a tool, optionally limited.
	 *
	 * @since 0.4.0
	 * @param string $tool_id Slug.
	 * @param int    $limit   Max rows.
	 * @return array
	 */
	public static function radar_for_tool( $tool_id, $limit = 20 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_radar';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE tool_id = %s AND row_status IN ('verified','published')
				ORDER BY change_date DESC LIMIT %d",
				(string) $tool_id,
				(int) $limit
			)
		);
	}

	/**
	 * Recent published radar entries, optionally filtered.
	 *
	 * @since 0.4.0
	 * @param array $args {tool_id, change_type, days, limit}
	 * @return array
	 */
	public static function radar_recent( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_radar';

		$where  = array( "row_status IN ('verified','published')" );
		$params = array();

		if ( ! empty( $args['tool_id'] ) ) {
			$where[]  = 'tool_id = %s';
			$params[] = $args['tool_id'];
		}
		if ( ! empty( $args['tool_ids'] ) && is_array( $args['tool_ids'] ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $args['tool_ids'] ), '%s' ) );
			$where[]      = 'tool_id IN (' . $placeholders . ')';
			$params       = array_merge( $params, $args['tool_ids'] );
		}
		if ( ! empty( $args['change_type'] ) ) {
			$where[]  = 'change_type = %s';
			$params[] = $args['change_type'];
		}
		if ( ! empty( $args['days'] ) ) {
			$where[] = 'change_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)';
			$params[] = (int) $args['days'];
		}

		$limit    = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 20;
		$params[] = $limit;

		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where )
			. ' ORDER BY change_date DESC LIMIT %d';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Return a tool's siblings (same company_group_id), excluding itself.
	 *
	 * @since 0.4.0
	 * @param string $tool_id Slug.
	 * @param string $group   company_group_id.
	 * @return array
	 */
	public static function same_company( $tool_id, $group ) {
		global $wpdb;
		if ( '' === $group ) {
			return array();
		}
		$table = $wpdb->prefix . 'aifirst_tool_meta';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tool_id, post_id FROM {$table} WHERE company_group_id = %s AND tool_id <> %s ORDER BY tool_id ASC",
				(string) $group,
				(string) $tool_id
			)
		);
	}

	/**
	 * Outbound relationships for a tool.
	 *
	 * @since 0.4.0
	 * @param string $tool_id Slug.
	 * @param string $type    One of alternative|complement|competitor|integration or '' for all.
	 * @return array
	 */
	public static function relationships( $tool_id, $type = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_relationships';
		if ( '' !== $type ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE source_tool_id = %s AND relationship_type = %s", (string) $tool_id, $type )
			);
		}
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE source_tool_id = %s", (string) $tool_id )
		);
	}

	/**
	 * FAQ entries for a tool OR a category.
	 *
	 * @since 0.4.0
	 * @param array $args {tool_id, category_id, limit}
	 * @return array
	 */
	public static function faq( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_faq';

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['tool_id'] ) ) {
			$where[]  = 'tool_id = %s';
			$params[] = $args['tool_id'];
		}
		if ( ! empty( $args['category_id'] ) ) {
			$where[]  = 'category_id = %s';
			$params[] = $args['category_id'];
		}
		if ( empty( $args['tool_id'] ) && empty( $args['category_id'] ) ) {
			// Site-wide FAQ: entries with no tool or category attached.
			$where[] = "tool_id = '' AND category_id = ''";
		}

		$limit    = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 10;
		$params[] = $limit;

		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where )
			. ' ORDER BY faq_order ASC LIMIT %d';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Category meta row.
	 *
	 * @since 0.4.0
	 * @param int $term_id Term ID.
	 * @return object|null
	 */
	public static function category_meta( $term_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_category_meta';
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE term_id = %d LIMIT 1", (int) $term_id )
		);
	}

	/**
	 * Feature meta row.
	 *
	 * @since 0.4.0
	 * @param int $term_id Term ID.
	 * @return object|null
	 */
	public static function feature_meta( $term_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_feature_meta';
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE term_id = %d LIMIT 1", (int) $term_id )
		);
	}

	/**
	 * Count of tools that have a given feature.
	 *
	 * @since 0.4.0
	 * @param string $feature_slug Slug.
	 * @return int
	 */
	public static function count_tools_with_feature( $feature_slug ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_tool_features';
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(DISTINCT tool_id) FROM {$table} WHERE feature_slug = %s", (string) $feature_slug )
		);
	}

	/**
	 * Count of tools in a category (post → term relationship).
	 *
	 * @since 0.4.0
	 * @param int $term_id Term ID.
	 * @return int
	 */
	public static function count_tools_in_category( $term_id ) {
		$count = wp_count_terms(
			array(
				'taxonomy' => 'aifirst_category',
				'include'  => array( (int) $term_id ),
			)
		);
		if ( is_wp_error( $count ) ) {
			return 0;
		}
		$term = get_term( $term_id, 'aifirst_category' );
		if ( ! $term || is_wp_error( $term ) ) {
			return 0;
		}
		return (int) $term->count;
	}

	/**
	 * Category stat strip aggregates (avg maturity, free-AI %).
	 *
	 * @since 0.4.0
	 * @param int $term_id Term ID.
	 * @return array{tool_count:int, avg_maturity:float, free_pct:float}
	 */
	public static function category_stats( $term_id ) {
		global $wpdb;
		$meta = $wpdb->prefix . 'aifirst_tool_meta';

		$posts = get_posts(
			array(
				'post_type'      => 'aifirst_tool',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => 'aifirst_category',
						'field'    => 'term_id',
						'terms'    => (int) $term_id,
					),
				),
			)
		);

		if ( empty( $posts ) ) {
			return array(
				'tool_count'   => 0,
				'avg_maturity' => 0.0,
				'free_pct'     => 0.0,
			);
		}

		$placeholders = implode( ',', array_fill( 0, count( $posts ), '%d' ) );
		$sql          = "SELECT COUNT(*) AS c,
			AVG(COALESCE(ai_maturity_score,0)) AS avg_m,
			SUM(CASE WHEN ai_on_free_plan = 1 THEN 1 ELSE 0 END) AS free_count
			FROM {$meta} WHERE post_id IN ({$placeholders})";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $posts ) );
		if ( ! $row || 0 === (int) $row->c ) {
			return array(
				'tool_count'   => count( $posts ),
				'avg_maturity' => 0.0,
				'free_pct'     => 0.0,
			);
		}
		return array(
			'tool_count'   => (int) $row->c,
			'avg_maturity' => round( (float) $row->avg_m, 1 ),
			'free_pct'     => round( (float) $row->free_count * 100.0 / (float) $row->c, 1 ),
		);
	}

	/**
	 * AI Maturity leaderboard — top N tools by score, optionally filtered by
	 * category term_id.
	 *
	 * @since 0.4.0
	 * @param array $args {limit, category_id}
	 * @return array Rows carrying post_id, tool_id, title, ai_maturity_score, meta…
	 */
	public static function leaderboard( $args = array() ) {
		global $wpdb;
		$meta  = $wpdb->prefix . 'aifirst_tool_meta';
		$posts = $wpdb->posts;

		$limit = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 10;

		$where  = array( "p.post_status = 'publish'", "p.post_type = 'aifirst_tool'" );
		$params = array();

		if ( ! empty( $args['category_id'] ) ) {
			$ids = get_posts(
				array(
					'post_type'      => 'aifirst_tool',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'tax_query'      => array(
						array(
							'taxonomy' => 'aifirst_category',
							'field'    => 'term_id',
							'terms'    => (int) $args['category_id'],
						),
					),
				)
			);
			if ( empty( $ids ) ) {
				return array();
			}
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$where[]      = 'p.ID IN (' . $placeholders . ')';
			$params       = array_merge( $params, $ids );
		}

		$params[] = $limit;

		$sql = "SELECT p.ID AS post_id, p.post_title, m.*
			FROM {$posts} p
			INNER JOIN {$meta} m ON m.post_id = p.ID
			WHERE " . implode( ' AND ', $where ) . "
			ORDER BY COALESCE(m.ai_maturity_score, 0) DESC, p.post_title ASC
			LIMIT %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Tools that have a given feature, with their plan/status for that feature.
	 *
	 * @since 0.4.0
	 * @param string $feature_slug Slug.
	 * @return array
	 */
	public static function tools_with_feature( $feature_slug ) {
		global $wpdb;
		$meta  = $wpdb->prefix . 'aifirst_tool_meta';
		$tf    = $wpdb->prefix . 'aifirst_tool_features';
		$posts = $wpdb->posts;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS post_id, p.post_title, m.*, f.plan AS feat_plan, f.status AS feat_status, f.is_native AS feat_is_native
				FROM {$tf} f
				INNER JOIN {$meta} m ON m.tool_id = f.tool_id
				INNER JOIN {$posts} p ON p.ID = m.post_id
				WHERE f.feature_slug = %s AND p.post_status = 'publish'
				ORDER BY COALESCE(m.ai_maturity_score, 0) DESC",
				$feature_slug
			)
		);
	}

	/**
	 * Tools that do NOT have a given feature.
	 *
	 * @since 0.4.0
	 * @param string $feature_slug Slug.
	 * @param int    $limit        Max rows.
	 * @return array
	 */
	public static function tools_without_feature( $feature_slug, $limit = 20 ) {
		global $wpdb;
		$meta  = $wpdb->prefix . 'aifirst_tool_meta';
		$tf    = $wpdb->prefix . 'aifirst_tool_features';
		$posts = $wpdb->posts;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS post_id, p.post_title, m.tool_id
				FROM {$posts} p
				INNER JOIN {$meta} m ON m.post_id = p.ID
				WHERE p.post_type = 'aifirst_tool' AND p.post_status = 'publish'
				AND m.tool_id NOT IN (SELECT tool_id FROM {$tf} WHERE feature_slug = %s)
				ORDER BY COALESCE(m.ai_maturity_score, 0) DESC
				LIMIT %d",
				$feature_slug,
				$limit
			)
		);
	}

	/**
	 * Site-wide counters for the home / footer strip.
	 *
	 * @since 0.4.0
	 * @return array{tools:int, features:int, updated:string}
	 */
	public static function counters() {
		global $wpdb;
		$meta  = $wpdb->prefix . 'aifirst_tool_meta';
		$tf    = $wpdb->prefix . 'aifirst_tool_features';

		$tools    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$meta}" );
		$features = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tf}" );
		$last     = $wpdb->get_var( "SELECT MAX(updated_at) FROM {$meta}" );

		return array(
			'tools'    => $tools,
			'features' => $features,
			'updated'  => $last ? mysql2date( get_option( 'date_format', 'M j, Y' ), $last ) : '',
		);
	}

	/**
	 * Return up to $limit published aifirst_comparison posts that include
	 * the given tool_id in their _aifirst_vs_tool_ids meta.
	 *
	 * @since 0.5.0
	 * @param string $tool_id Tool slug.
	 * @param int    $limit   Max rows.
	 * @return WP_Post[]
	 */
	public static function comparisons_for_tool( $tool_id, $limit = 5 ) {
		$tool_id = sanitize_title( (string) $tool_id );
		if ( '' === $tool_id ) {
			return array();
		}
		global $wpdb;
		// JSON stored as a slug array like ["jasper","grammarly"] — a slug
		// is URL-safe so double-quote wrapping is safe to match on.
		$needle = '%"' . $wpdb->esc_like( $tool_id ) . '"%';
		$ids    = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
					AND p.post_status = 'publish'
					AND pm.meta_key = %s
					AND pm.meta_value LIKE %s
				ORDER BY p.post_date DESC
				LIMIT %d",
				AIFirst_Comparison::POST_TYPE,
				AIFirst_Comparison::META_TOOLS,
				$needle,
				(int) $limit
			)
		);
		if ( empty( $ids ) ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'   => AIFirst_Comparison::POST_TYPE,
				'post_status' => 'publish',
				'include'     => array_map( 'intval', $ids ),
				'orderby'     => 'post__in',
			)
		);
	}

	/**
	 * Find the single published comparison whose tool_ids set equals the
	 * given set (order-independent).
	 *
	 * @since 0.5.0
	 * @param string[] $tool_ids 2 or 3 slugs.
	 * @return WP_Post|null
	 */
	public static function comparison_for_tool_pair( array $tool_ids ) {
		$slugs = array();
		foreach ( $tool_ids as $s ) {
			$s = sanitize_title( (string) $s );
			if ( '' !== $s && ! in_array( $s, $slugs, true ) ) {
				$slugs[] = $s;
			}
		}
		if ( count( $slugs ) < 2 ) {
			return null;
		}
		sort( $slugs );
		$wanted = count( $slugs );

		global $wpdb;
		// Candidates: every published comparison that contains the first
		// slug. Then filter in PHP by set equality (small result set).
		$needle = '%"' . $wpdb->esc_like( $slugs[0] ) . '"%';
		$ids    = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
					AND p.post_status = 'publish'
					AND pm.meta_key = %s
					AND pm.meta_value LIKE %s
				ORDER BY p.post_date DESC
				LIMIT 50",
				AIFirst_Comparison::POST_TYPE,
				AIFirst_Comparison::META_TOOLS,
				$needle
			)
		);
		foreach ( (array) $ids as $post_id ) {
			$row = AIFirst_Comparison::get_tool_ids( (int) $post_id );
			if ( count( $row ) !== $wanted ) {
				continue;
			}
			$row_sorted = $row;
			sort( $row_sorted );
			if ( $row_sorted === $slugs ) {
				return get_post( (int) $post_id );
			}
		}
		return null;
	}

	/**
	 * Most-recent published comparisons for the home "Trending" rail.
	 *
	 * @since 0.5.0
	 * @param int $limit Max rows.
	 * @return WP_Post[]
	 */
	public static function trending_comparisons( $limit = 6 ) {
		return get_posts(
			array(
				'post_type'      => AIFirst_Comparison::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => (int) $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Split a JSON-encoded list back into a PHP array (safe).
	 *
	 * @since 0.4.0
	 * @param string $json Raw JSON array string.
	 * @return array
	 */
	public static function json_list( $json ) {
		$json = (string) $json;
		if ( '' === $json ) {
			return array();
		}
		$arr = json_decode( $json, true );
		return is_array( $arr ) ? $arr : array();
	}
}
