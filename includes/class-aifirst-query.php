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
	 * Parse the stack-slug URL segment or the aifirst_stack cookie into a
	 * clean, deduped array of tool_id slugs.
	 *
	 * Precedence: URL slug segment wins; otherwise fall back to the cookie.
	 *
	 * @since 0.6.0
	 * @return array{slugs:string[], source:string}
	 */
	public static function stack_slugs_from_request() {
		$raw = get_query_var( AIFirst_Public::QV_STACK );
		if ( ! empty( $raw ) ) {
			$parts  = explode( '+', (string) $raw );
			$source = 'url';
		} elseif ( isset( $_COOKIE['aifirst_stack'] ) ) {
			$parts  = explode( ',', (string) wp_unslash( $_COOKIE['aifirst_stack'] ) );
			$source = 'cookie';
		} else {
			return array( 'slugs' => array(), 'source' => 'empty' );
		}

		$clean = array();
		foreach ( $parts as $p ) {
			$slug = sanitize_title( (string) $p );
			if ( '' === $slug || in_array( $slug, $clean, true ) ) {
				continue;
			}
			$clean[] = $slug;
			if ( count( $clean ) >= 50 ) {
				break;
			}
		}
		return array( 'slugs' => $clean, 'source' => $source );
	}

	/**
	 * Resolve slugs to [ post_id => (object) tool_meta row ] for published
	 * tools only. Order of result follows order of input slugs.
	 *
	 * @since 0.6.0
	 * @param string[] $slugs tool_id slugs.
	 * @return array<int, object>
	 */
	public static function stack_resolve_tool_ids( array $slugs ) {
		$slugs = array_values( array_filter( array_map( 'sanitize_title', $slugs ) ) );
		if ( empty( $slugs ) ) {
			return array();
		}
		global $wpdb;
		$meta  = $wpdb->prefix . 'aifirst_tool_meta';
		$posts = $wpdb->posts;

		$placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
		$sql          = "SELECT m.*, p.post_title FROM {$meta} m
			INNER JOIN {$posts} p ON p.ID = m.post_id
			WHERE m.tool_id IN ({$placeholders})
			AND p.post_type = 'aifirst_tool'
			AND p.post_status = 'publish'";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $slugs ) );

		// Re-order to match input order.
		$by_slug = array();
		foreach ( (array) $rows as $r ) {
			$by_slug[ $r->tool_id ] = $r;
		}
		$out = array();
		foreach ( $slugs as $s ) {
			if ( isset( $by_slug[ $s ] ) ) {
				$out[ (int) $by_slug[ $s ]->post_id ] = $by_slug[ $s ];
			}
		}
		return $out;
	}

	/**
	 * Aggregate unique features across a list of tools.
	 *
	 * @since 0.6.0
	 * @param int[]|string[] $post_ids_or_slugs post IDs or tool slugs.
	 * @return array{
	 *     features: array<string,object>, native: int, third_party: int,
	 *     total_unique: int, tools_with_free_ai: int, starting_price_sum: float
	 * }
	 */
	public static function stack_aggregate_features( array $post_ids_or_slugs ) {
		$resolved = self::stack_input_to_rows( $post_ids_or_slugs );
		$out      = array(
			'features'           => array(),
			'native'             => 0,
			'third_party'        => 0,
			'total_unique'       => 0,
			'tools_with_free_ai' => 0,
			'starting_price_sum' => 0.0,
		);
		if ( empty( $resolved ) ) {
			return $out;
		}

		global $wpdb;
		$tf = $wpdb->prefix . 'aifirst_tool_features';

		$tool_ids = array();
		foreach ( $resolved as $r ) {
			$tool_ids[] = $r->tool_id;
			if ( 1 === (int) $r->ai_on_free_plan ) {
				$out['tools_with_free_ai']++;
			}
			if ( null !== $r->starting_price_usd ) {
				$out['starting_price_sum'] += (float) $r->starting_price_usd;
			}
		}

		$placeholders = implode( ',', array_fill( 0, count( $tool_ids ), '%s' ) );
		$sql          = "SELECT feature_slug, feature_name, is_native, COUNT(*) AS tool_count
			FROM {$tf} WHERE tool_id IN ({$placeholders})
			GROUP BY feature_slug, feature_name, is_native
			ORDER BY tool_count DESC, feature_name ASC";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $tool_ids ) );

		foreach ( (array) $rows as $r ) {
			$slug = (string) $r->feature_slug;
			if ( ! isset( $out['features'][ $slug ] ) ) {
				$out['features'][ $slug ] = (object) array(
					'feature_slug' => $slug,
					'feature_name' => (string) $r->feature_name,
					'is_native'    => (int) $r->is_native,
					'tool_count'   => 0,
				);
			}
			$out['features'][ $slug ]->tool_count += (int) $r->tool_count;
			if ( (int) $r->is_native ) {
				$out['native'] += (int) $r->tool_count;
			} else {
				$out['third_party'] += (int) $r->tool_count;
			}
		}
		$out['total_unique'] = count( $out['features'] );
		return $out;
	}

	/**
	 * Features that are present in the same categories as the stack, but
	 * not present in any tool in the stack. Ranked by how many tools in
	 * those categories offer the feature (frequency = priority).
	 *
	 * @since 0.6.0
	 * @param int[]|string[] $post_ids_or_slugs Current stack.
	 * @param int            $limit             Max rows.
	 * @return object[] Each: feature_slug, feature_name, tool_count, top_tools[]
	 */
	public static function stack_missing_features( array $post_ids_or_slugs, $limit = 10 ) {
		$resolved = self::stack_input_to_rows( $post_ids_or_slugs );
		if ( empty( $resolved ) ) {
			return array();
		}

		// Categories currently represented.
		$post_ids_in_stack = array_keys( $resolved );
		$tool_ids_in_stack = array_map( static function ( $r ) { return $r->tool_id; }, array_values( $resolved ) );

		$category_ids = array();
		foreach ( $post_ids_in_stack as $pid ) {
			$terms = get_the_terms( (int) $pid, 'aifirst_category' );
			if ( ! $terms || is_wp_error( $terms ) ) { continue; }
			foreach ( $terms as $t ) {
				$category_ids[ (int) $t->term_id ] = true;
			}
		}
		if ( empty( $category_ids ) ) {
			return array();
		}

		// All published tools in those categories.
		$candidate_posts = get_posts(
			array(
				'post_type'      => 'aifirst_tool',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => 'aifirst_category',
						'field'    => 'term_id',
						'terms'    => array_keys( $category_ids ),
					),
				),
			)
		);
		$candidate_posts = array_diff( $candidate_posts, $post_ids_in_stack );
		if ( empty( $candidate_posts ) ) {
			return array();
		}

		global $wpdb;
		$meta = $wpdb->prefix . 'aifirst_tool_meta';
		$tf   = $wpdb->prefix . 'aifirst_tool_features';

		// Map candidate post_ids → tool_ids.
		$pid_placeholders = implode( ',', array_fill( 0, count( $candidate_posts ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$cand_slugs = $wpdb->get_col(
			$wpdb->prepare( "SELECT tool_id FROM {$meta} WHERE post_id IN ({$pid_placeholders})", $candidate_posts )
		);
		if ( empty( $cand_slugs ) ) {
			return array();
		}

		$slug_placeholders = implode( ',', array_fill( 0, count( $cand_slugs ), '%s' ) );
		$stack_placeholders = implode( ',', array_fill( 0, count( $tool_ids_in_stack ), '%s' ) );
		$sql = "SELECT feature_slug, feature_name, COUNT(DISTINCT tool_id) AS tool_count
			FROM {$tf}
			WHERE tool_id IN ({$slug_placeholders})
			AND feature_slug NOT IN (
				SELECT feature_slug FROM {$tf} WHERE tool_id IN ({$stack_placeholders})
			)
			GROUP BY feature_slug, feature_name
			ORDER BY tool_count DESC, feature_name ASC
			LIMIT %d";

		$params = array_merge( $cand_slugs, $tool_ids_in_stack, array( (int) $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		// For each missing feature, surface up to 3 top tools that have it.
		foreach ( (array) $rows as &$r ) {
			$top_slugs = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT f.tool_id FROM {$tf} f
					INNER JOIN {$meta} m ON m.tool_id = f.tool_id
					WHERE f.feature_slug = %s
					AND f.tool_id IN ({$slug_placeholders})
					ORDER BY COALESCE(m.ai_maturity_score, 0) DESC
					LIMIT 3",
					array_merge( array( $r->feature_slug ), $cand_slugs )
				)
			);
			$r->top_tools = array();
			foreach ( (array) $top_slugs as $slug ) {
				$row = self::tool_meta_by_id( $slug );
				if ( $row ) {
					$r->top_tools[] = (object) array(
						'tool_id'   => $slug,
						'name'      => get_the_title( (int) $row->post_id ),
						'permalink' => get_permalink( (int) $row->post_id ),
					);
				}
			}
		}
		unset( $r );

		return (array) $rows;
	}

	/**
	 * Categories NOT represented in the current stack, ranked by the
	 * category's tool count (biggest gaps first).
	 *
	 * @since 0.6.0
	 * @param int[]|string[] $post_ids_or_slugs Current stack.
	 * @param int            $limit             Max rows.
	 * @return object[] Each: term, tool_count, top_tools[]
	 */
	public static function stack_category_gaps( array $post_ids_or_slugs, $limit = 10 ) {
		$resolved = self::stack_input_to_rows( $post_ids_or_slugs );

		$have = array();
		foreach ( array_keys( $resolved ) as $pid ) {
			$terms = get_the_terms( (int) $pid, 'aifirst_category' );
			if ( ! $terms || is_wp_error( $terms ) ) { continue; }
			foreach ( $terms as $t ) {
				$have[ (int) $t->term_id ] = true;
			}
		}

		$all = get_terms(
			array(
				'taxonomy'   => 'aifirst_category',
				'hide_empty' => true,
			)
		);
		if ( is_wp_error( $all ) || empty( $all ) ) {
			return array();
		}

		$gaps = array();
		foreach ( $all as $t ) {
			if ( isset( $have[ (int) $t->term_id ] ) ) {
				continue;
			}
			$gaps[] = $t;
		}
		if ( empty( $gaps ) ) {
			return array();
		}

		usort( $gaps, static function ( $a, $b ) { return (int) $b->count - (int) $a->count; } );
		$gaps = array_slice( $gaps, 0, (int) $limit );

		$out = array();
		foreach ( $gaps as $t ) {
			$top = self::leaderboard( array( 'category_id' => (int) $t->term_id, 'limit' => 3 ) );
			$out[] = (object) array(
				'term'       => $t,
				'tool_count' => (int) $t->count,
				'top_tools'  => $top,
			);
		}
		return $out;
	}

	/**
	 * Accept either [post_id => row] arrays or a list of slugs; resolve
	 * to [post_id => tool_meta row].
	 *
	 * @since 0.6.0
	 * @param mixed[] $input Mixed slugs and/or post ids.
	 * @return array<int, object>
	 */
	protected static function stack_input_to_rows( array $input ) {
		if ( empty( $input ) ) {
			return array();
		}
		// If first element looks numeric treat as post_ids.
		$first = reset( $input );
		if ( is_object( $first ) && isset( $first->tool_id ) ) {
			$out = array();
			foreach ( $input as $row ) {
				$out[ (int) $row->post_id ] = $row;
			}
			return $out;
		}
		if ( is_numeric( $first ) && ! is_string( $first ) ) {
			global $wpdb;
			$meta         = $wpdb->prefix . 'aifirst_tool_meta';
			$placeholders = implode( ',', array_fill( 0, count( $input ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$meta} WHERE post_id IN ({$placeholders})", $input ) );
			$out  = array();
			foreach ( (array) $rows as $r ) {
				$out[ (int) $r->post_id ] = $r;
			}
			return $out;
		}
		return self::stack_resolve_tool_ids( array_map( 'strval', $input ) );
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
