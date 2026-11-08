<?php
/**
 * Intake service — the single write path for canonical records.
 *
 * Both the admin UI and the REST endpoint call AIFirst_Intake::save_record().
 * No other code writes to the aifirst_tool CPT or the 6 custom tables.
 *
 * @package AIFirst
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Intake.
 *
 * @since 0.2.0
 */
class AIFirst_Intake {

	const TOOL_ID_META_KEY = '_aifirst_tool_id';

	/**
	 * Save a VALIDATED canonical record (idempotent upsert on tool_id).
	 *
	 * @since 0.2.0
	 * @param array $record Canonical record produced by AIFirst_Record::validate().
	 * @return array{tool_id:string, post_id:int, created_or_updated:string, counts:array, errors:string[]}
	 */
	public function save_record( array $record ) {
		global $wpdb;

		$errors  = array();
		$tool_id = isset( $record['tool']['tool_id'] ) ? (string) $record['tool']['tool_id'] : '';
		if ( '' === $tool_id ) {
			return array(
				'tool_id'            => '',
				'post_id'            => 0,
				'created_or_updated' => 'none',
				'counts'             => array(),
				'errors'             => array( __( 'Missing tool_id.', 'aifirstsaas' ) ),
			);
		}

		$existing_post_id = $this->find_post_id_by_tool_id( $tool_id );
		$is_update        = $existing_post_id > 0;

		$post_data = array(
			'post_type'    => 'aifirst_tool',
			'post_title'   => $record['tool']['title'],
			'post_content' => $record['tool']['content'],
			'post_excerpt' => $record['tool']['excerpt'],
			'post_status'  => $record['tool']['status'],
			'post_name'    => $tool_id,
		);

		if ( $is_update ) {
			$post_data['ID'] = $existing_post_id;
			$post_id         = wp_update_post( wp_slash( $post_data ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $post_data ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			return array(
				'tool_id'            => $tool_id,
				'post_id'            => 0,
				'created_or_updated' => 'none',
				'counts'             => array(),
				'errors'             => array( $post_id->get_error_message() ),
			);
		}

		update_post_meta( (int) $post_id, self::TOOL_ID_META_KEY, $tool_id );

		// Taxonomies.
		$this->assign_terms( (int) $post_id, 'aifirst_category', $record['tool']['categories'] );
		$this->assign_terms( (int) $post_id, 'aifirst_feature', $record['tool']['ai_features'] );

		// Meta table.
		$meta_res = $this->upsert_tool_meta( $tool_id, (int) $post_id, $record['meta'] );
		if ( is_wp_error( $meta_res ) ) {
			$errors[] = $meta_res->get_error_message();
		}

		// Child tables — replace strategy keyed on tool_id.
		$counts = array(
			'plans'              => $this->replace_plans( $tool_id, $record['plans'], $errors ),
			'faq'                => $this->replace_faq( $tool_id, $record['faq'], $errors ),
			'radar'              => $this->replace_radar( $tool_id, $record['radar'], $errors ),
			'cannot_do'          => $this->replace_cannot_do( $tool_id, $record['cannot_do'], $errors ),
			'relationships'      => $this->replace_relationships( $tool_id, $record['relationships'], $errors ),
			'tool_features'      => $this->replace_tool_features( $tool_id, isset( $record['tool_features'] ) ? $record['tool_features'] : array(), $errors ),
			'maturity_breakdown' => $this->replace_maturity_breakdown( $tool_id, isset( $record['maturity_breakdown'] ) ? $record['maturity_breakdown'] : array(), $errors ),
			'industries'         => $this->replace_industries( $tool_id, isset( $record['industries'] ) ? $record['industries'] : array(), $errors ),
			'sentiment'          => $this->replace_sentiment( $tool_id, isset( $record['sentiment'] ) ? $record['sentiment'] : array(), $errors ),
			'quotes'             => $this->replace_quotes( $tool_id, isset( $record['quotes'] ) ? $record['quotes'] : array(), $errors ),
		);

		// Term-level long-form content.
		if ( ! empty( $record['category_meta'] ) ) {
			$this->upsert_term_meta( 'aifirst_category', $record['category_meta'], $errors );
		}
		if ( ! empty( $record['feature_meta'] ) ) {
			$this->upsert_term_meta( 'aifirst_feature', $record['feature_meta'], $errors );
		}

		return array(
			'tool_id'            => $tool_id,
			'post_id'            => (int) $post_id,
			'created_or_updated' => $is_update ? 'updated' : 'created',
			'counts'             => $counts,
			'errors'             => $errors,
		);
	}

	/**
	 * Find an existing aifirst_tool post by its tool_id.
	 *
	 * @since 0.2.0
	 * @param string $tool_id Slug.
	 * @return int 0 if not found.
	 */
	protected function find_post_id_by_tool_id( $tool_id ) {
		global $wpdb;

		$post_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND pm.meta_key = %s
				AND pm.meta_value = %s
				LIMIT 1",
				'aifirst_tool',
				self::TOOL_ID_META_KEY,
				$tool_id
			)
		);

		if ( $post_id > 0 ) {
			return $post_id;
		}

		// Fallback: match by post_name.
		$post_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s LIMIT 1",
				'aifirst_tool',
				$tool_id
			)
		);

		return $post_id;
	}

	/**
	 * Assign terms to a post, creating by name when needed.
	 *
	 * @since 0.2.0
	 * @param int      $post_id Post ID.
	 * @param string   $taxonomy Taxonomy slug.
	 * @param string[] $names    Term names.
	 * @return void
	 */
	protected function assign_terms( $post_id, $taxonomy, $names ) {
		if ( empty( $names ) ) {
			wp_set_object_terms( $post_id, array(), $taxonomy, false );
			return;
		}

		$term_ids = array();
		foreach ( $names as $name ) {
			$term = term_exists( $name, $taxonomy );
			if ( ! $term ) {
				$term = term_exists( sanitize_title( $name ), $taxonomy );
			}
			if ( ! $term ) {
				$inserted = wp_insert_term( $name, $taxonomy );
				if ( ! is_wp_error( $inserted ) ) {
					$term_ids[] = (int) $inserted['term_id'];
				}
			} elseif ( is_array( $term ) ) {
				$term_ids[] = (int) $term['term_id'];
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
		}
	}

	/**
	 * Upsert a row in {prefix}aifirst_tool_meta.
	 *
	 * @since 0.2.0
	 * @param string $tool_id Slug.
	 * @param int    $post_id Post ID.
	 * @param array  $meta    Meta fields.
	 * @return true|WP_Error
	 */
	protected function upsert_tool_meta( $tool_id, $post_id, array $meta ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_tool_meta';

		$data = array(
			'tool_id'             => $tool_id,
			'post_id'             => $post_id,
			'company_name'        => (string) $meta['company_name'],
			'website_url'         => (string) $meta['website_url'],
			'pricing_url'         => (string) $meta['pricing_url'],
			'initials'            => (string) $meta['initials'],
			'avatar_color'        => (string) $meta['avatar_color'],
			'logo_url'            => (string) $meta['logo_url'],
			'wp_org_slug'         => (string) $meta['wp_org_slug'],
			'subcategory'         => (string) $meta['subcategory'],
			'founded_year'        => $meta['founded_year'],
			'headquarters'        => (string) $meta['headquarters'],
			'pricing_model'       => (string) $meta['pricing_model'],
			'has_free_plan'       => (int) $meta['has_free_plan'],
			'starting_price_usd'  => $meta['starting_price_usd'],
			'pricing_note'        => (string) $meta['pricing_note'],
			'ai_maturity_score'   => $meta['ai_maturity_score'],
			'ai_since'            => $meta['ai_since'],
			'ai_type'             => (string) $meta['ai_type'],
			'ai_on_free_plan'     => (int) $meta['ai_on_free_plan'],
			'total_ai_features'   => (int) $meta['total_ai_features'],
			'native_ai_count'     => (int) $meta['native_ai_count'],
			'thirdparty_ai_count' => (int) $meta['thirdparty_ai_count'],
			'last_ai_update'      => '' !== $meta['last_ai_update'] ? $meta['last_ai_update'] : null,
			'last_verified'       => '' !== $meta['last_verified'] ? $meta['last_verified'] : null,
			'company_size_smb'    => (int) $meta['company_size_smb'],
			'company_size_mid'    => (int) $meta['company_size_mid'],
			'company_size_ent'    => (int) $meta['company_size_ent'],
			'company_group_id'    => (string) $meta['company_group_id'],
			'notable_customers'   => (string) $meta['notable_customers'],
			'best_for'            => (string) $meta['best_for'],
			'expert_take'         => (string) $meta['expert_take'],
			'meta_title'          => (string) $meta['meta_title'],
			'meta_description'    => (string) $meta['meta_description'],
			'h1'                  => (string) $meta['h1'],
			'updated_at'          => current_time( 'mysql' ),
		);

		$existing_id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT meta_id FROM {$table} WHERE tool_id = %s LIMIT 1", $tool_id )
		);

		if ( $existing_id > 0 ) {
			$result = $wpdb->update(
				$table,
				$data,
				array( 'meta_id' => $existing_id ),
				$this->format_specs_for_meta(),
				array( '%d' )
			);
		} else {
			$data['created_at'] = current_time( 'mysql' );
			$result             = $wpdb->insert( $table, $data, $this->format_specs_for_meta( true ) );
		}

		if ( false === $result ) {
			return new WP_Error( 'aifirst_db_error', $wpdb->last_error ? $wpdb->last_error : __( 'Unknown DB error on tool_meta.', 'aifirstsaas' ) );
		}
		return true;
	}

	/**
	 * Format specifiers for tool_meta columns, in the same order as upsert_tool_meta().
	 *
	 * @since 0.2.0
	 * @param bool $with_created Whether created_at is appended.
	 * @return string[]
	 */
	protected function format_specs_for_meta( $with_created = false ) {
		$specs = array(
			'%s', // tool_id
			'%d', // post_id
			'%s', // company_name
			'%s', // website_url
			'%s', // pricing_url
			'%s', // initials
			'%s', // avatar_color
			'%s', // logo_url
			'%s', // wp_org_slug
			'%s', // subcategory
			'%d', // founded_year (nullable; %d forces int cast when not null)
			'%s', // headquarters
			'%s', // pricing_model
			'%d', // has_free_plan
			'%f', // starting_price_usd
			'%s', // pricing_note
			'%d', // ai_maturity_score
			'%d', // ai_since
			'%s', // ai_type
			'%d', // ai_on_free_plan
			'%d', // total_ai_features
			'%d', // native_ai_count
			'%d', // thirdparty_ai_count
			'%s', // last_ai_update
			'%s', // last_verified
			'%d', // company_size_smb
			'%d', // company_size_mid
			'%d', // company_size_ent
			'%s', // company_group_id
			'%s', // notable_customers
			'%s', // best_for
			'%s', // expert_take
			'%s', // meta_title
			'%s', // meta_description
			'%s', // h1
			'%s', // updated_at
		);
		if ( $with_created ) {
			$specs[] = '%s'; // created_at
		}
		return $specs;
	}

	/**
	 * Delete-and-insert plans for a tool.
	 *
	 * @since 0.2.0
	 * @param string   $tool_id Slug.
	 * @param array    $rows    Validated rows.
	 * @param string[] $errors  By-ref.
	 * @return int Inserted row count.
	 */
	protected function replace_plans( $tool_id, array $rows, array &$errors ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_plans';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE tool_id = %s", $tool_id ) );

		$inserted = 0;
		foreach ( $rows as $row ) {
			$ok = $wpdb->insert(
				$table,
				array(
					'tool_id'           => $tool_id,
					'plan_name'         => (string) $row['plan_name'],
					'tier_order'        => (int) $row['tier_order'],
					'price_usd_monthly' => $row['price_usd_monthly'],
					'price_usd_annual'  => $row['price_usd_annual'],
					'billing_cycle'     => (string) $row['billing_cycle'],
					'user_limit'        => (string) $row['user_limit'],
					'feature_list'      => (string) $row['feature_list'],
					'notes'             => (string) $row['notes'],
					'is_free'           => (int) $row['is_free'],
					'is_most_popular'   => (int) $row['is_most_popular'],
					'created_at'        => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
			);
			if ( false === $ok ) {
				$errors[] = sprintf( /* translators: %s: db error */ __( 'Plan insert failed: %s', 'aifirstsaas' ), $wpdb->last_error );
			} else {
				++$inserted;
			}
		}
		return $inserted;
	}

	/**
	 * Delete-and-insert FAQ for a tool.
	 *
	 * @since 0.2.0
	 * @param string   $tool_id Slug.
	 * @param array    $rows    Validated rows.
	 * @param string[] $errors  By-ref.
	 * @return int Inserted row count.
	 */
	protected function replace_faq( $tool_id, array $rows, array &$errors ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_faq';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE tool_id = %s", $tool_id ) );

		$inserted = 0;
		foreach ( $rows as $row ) {
			$ok = $wpdb->insert(
				$table,
				array(
					'tool_id'     => $tool_id,
					'category_id' => (string) $row['category_id'],
					'faq_order'   => (int) $row['faq_order'],
					'question'    => (string) $row['question'],
					'answer'      => (string) $row['answer'],
					'created_at'  => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%d', '%s', '%s', '%s' )
			);
			if ( false === $ok ) {
				$errors[] = sprintf( /* translators: %s: db error */ __( 'FAQ insert failed: %s', 'aifirstsaas' ), $wpdb->last_error );
			} else {
				++$inserted;
			}
		}
		return $inserted;
	}

	/**
	 * Delete-and-insert radar rows for a tool.
	 *
	 * @since 0.2.0
	 * @param string   $tool_id Slug.
	 * @param array    $rows    Validated rows.
	 * @param string[] $errors  By-ref.
	 * @return int Inserted row count.
	 */
	protected function replace_radar( $tool_id, array $rows, array &$errors ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_radar';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE tool_id = %s", $tool_id ) );

		$inserted = 0;
		foreach ( $rows as $row ) {
			$ok = $wpdb->insert(
				$table,
				array(
					'tool_id'        => $tool_id,
					'change_date'    => '' !== $row['change_date'] ? $row['change_date'] : null,
					'change_type'    => (string) $row['change_type'],
					'what_changed'   => (string) $row['what_changed'],
					'why_it_matters' => (string) $row['why_it_matters'],
					'source_url'     => (string) $row['source_url'],
					'version_label'  => (string) $row['version_label'],
					'confidence'     => (string) $row['confidence'],
					'row_status'     => (string) $row['row_status'],
					'created_at'     => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			if ( false === $ok ) {
				$errors[] = sprintf( /* translators: %s: db error */ __( 'Radar insert failed: %s', 'aifirstsaas' ), $wpdb->last_error );
			} else {
				++$inserted;
			}
		}
		return $inserted;
	}

	/**
	 * Delete-and-insert cannot_do rows for a tool.
	 *
	 * @since 0.2.0
	 * @param string   $tool_id Slug.
	 * @param array    $rows    Validated rows.
	 * @param string[] $errors  By-ref.
	 * @return int Inserted row count.
	 */
	protected function replace_cannot_do( $tool_id, array $rows, array &$errors ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_cannot_do';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE tool_id = %s", $tool_id ) );

		$inserted = 0;
		foreach ( $rows as $row ) {
			$ok = $wpdb->insert(
				$table,
				array(
					'tool_id'         => $tool_id,
					'order_index'     => (int) $row['order_index'],
					'limitation_text' => (string) $row['limitation_text'],
					'workaround'      => (string) ( isset( $row['workaround'] ) ? $row['workaround'] : '' ),
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%s', '%s', '%s' )
			);
			if ( false === $ok ) {
				$errors[] = sprintf( /* translators: %s: db error */ __( 'Cannot-do insert failed: %s', 'aifirstsaas' ), $wpdb->last_error );
			} else {
				++$inserted;
			}
		}
		return $inserted;
	}

	/**
	 * Delete-and-insert relationships for a tool (as source).
	 *
	 * @since 0.2.0
	 * @param string   $tool_id Slug.
	 * @param array    $rows    Validated rows.
	 * @param string[] $errors  By-ref.
	 * @return int Inserted row count.
	 */
	protected function replace_relationships( $tool_id, array $rows, array &$errors ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_relationships';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE source_tool_id = %s", $tool_id ) );

		$inserted = 0;
		foreach ( $rows as $row ) {
			$ok = $wpdb->insert(
				$table,
				array(
					'source_tool_id'    => $tool_id,
					'target_tool_id'    => (string) $row['target_tool_id'],
					'relationship_type' => (string) $row['relationship_type'],
					'notes'             => (string) $row['notes'],
					'created_at'        => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%s' )
			);
			if ( false === $ok ) {
				$errors[] = sprintf( /* translators: %s: db error */ __( 'Relationship insert failed: %s', 'aifirstsaas' ), $wpdb->last_error );
			} else {
				++$inserted;
			}
		}
		return $inserted;
	}

	/**
	 * Delete-and-insert per-tool feature join rows. Also ensures the
	 * aifirst_feature term exists for each feature and attaches it to the
	 * post.
	 *
	 * @since 0.3.0
	 * @param string   $tool_id Slug.
	 * @param array    $rows    Validated rows.
	 * @param string[] $errors  By-ref.
	 * @return int Inserted row count.
	 */
	protected function replace_tool_features( $tool_id, array $rows, array &$errors ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_tool_features';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE tool_id = %s", $tool_id ) );

		$inserted = 0;
		foreach ( $rows as $row ) {
			$ok = $wpdb->insert(
				$table,
				array(
					'tool_id'      => $tool_id,
					'feature_slug' => (string) $row['feature_slug'],
					'feature_name' => (string) $row['feature_name'],
					'plan'         => (string) $row['plan'],
					'status'       => (string) $row['status'],
					'is_native'    => (int) $row['is_native'],
					'since'        => (string) $row['since'],
					'feature_note' => (string) $row['feature_note'],
					'created_at'   => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
			);
			if ( false === $ok ) {
				$errors[] = sprintf( /* translators: %s: db error */ __( 'tool_features insert failed: %s', 'aifirstsaas' ), $wpdb->last_error );
			} else {
				++$inserted;
			}
		}
		return $inserted;
	}

	/**
	 * Delete-and-insert maturity_breakdown rows for a tool.
	 *
	 * @since 0.3.0
	 * @param string   $tool_id Slug.
	 * @param array    $rows    Validated rows.
	 * @param string[] $errors  By-ref.
	 * @return int Inserted row count.
	 */
	protected function replace_maturity_breakdown( $tool_id, array $rows, array &$errors ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_maturity_breakdown';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE tool_id = %s", $tool_id ) );

		$inserted = 0;
		foreach ( $rows as $row ) {
			$ok = $wpdb->insert(
				$table,
				array(
					'tool_id'       => $tool_id,
					'order_index'   => (int) $row['order_index'],
					'criterion'     => (string) $row['criterion'],
					'score_display' => (string) $row['score_display'],
					'score_numeric' => $row['score_numeric'],
					'score_color'   => (string) $row['score_color'],
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%s', '%s', '%f', '%s', '%s' )
			);
			if ( false === $ok ) {
				$errors[] = sprintf( /* translators: %s: db error */ __( 'maturity_breakdown insert failed: %s', 'aifirstsaas' ), $wpdb->last_error );
			} else {
				++$inserted;
			}
		}
		return $inserted;
	}

	/**
	 * Delete-and-insert industries rows for a tool.
	 *
	 * @since 0.3.0
	 * @param string   $tool_id Slug.
	 * @param array    $rows    Validated rows.
	 * @param string[] $errors  By-ref.
	 * @return int Inserted row count.
	 */
	protected function replace_industries( $tool_id, array $rows, array &$errors ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_industries';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE tool_id = %s", $tool_id ) );

		$inserted = 0;
		foreach ( $rows as $row ) {
			$ok = $wpdb->insert(
				$table,
				array(
					'tool_id'     => $tool_id,
					'order_index' => (int) $row['order_index'],
					'name'        => (string) $row['name'],
					'pct'         => (int) $row['pct'],
					'created_at'  => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%s', '%d', '%s' )
			);
			if ( false === $ok ) {
				$errors[] = sprintf( /* translators: %s: db error */ __( 'industries insert failed: %s', 'aifirstsaas' ), $wpdb->last_error );
			} else {
				++$inserted;
			}
		}
		return $inserted;
	}

	/**
	 * Delete-and-insert sentiment rows for a tool.
	 *
	 * @since 0.3.0
	 * @param string   $tool_id Slug.
	 * @param array    $rows    Validated rows.
	 * @param string[] $errors  By-ref.
	 * @return int Inserted row count.
	 */
	protected function replace_sentiment( $tool_id, array $rows, array &$errors ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_sentiment';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE tool_id = %s", $tool_id ) );

		$inserted = 0;
		foreach ( $rows as $row ) {
			$ok = $wpdb->insert(
				$table,
				array(
					'tool_id'      => $tool_id,
					'source'       => (string) $row['source'],
					'score'        => $row['score'],
					'review_count' => (int) $row['review_count'],
					'source_url'   => (string) $row['source_url'],
					'created_at'   => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%f', '%d', '%s', '%s' )
			);
			if ( false === $ok ) {
				$errors[] = sprintf( /* translators: %s: db error */ __( 'sentiment insert failed: %s', 'aifirstsaas' ), $wpdb->last_error );
			} else {
				++$inserted;
			}
		}
		return $inserted;
	}

	/**
	 * Delete-and-insert quotes rows for a tool.
	 *
	 * @since 0.3.0
	 * @param string   $tool_id Slug.
	 * @param array    $rows    Validated rows.
	 * @param string[] $errors  By-ref.
	 * @return int Inserted row count.
	 */
	protected function replace_quotes( $tool_id, array $rows, array &$errors ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_quotes';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE tool_id = %s", $tool_id ) );

		$inserted = 0;
		foreach ( $rows as $row ) {
			$ok = $wpdb->insert(
				$table,
				array(
					'tool_id'     => $tool_id,
					'order_index' => (int) $row['order_index'],
					'quote_text'  => (string) $row['quote_text'],
					'role'        => (string) $row['role'],
					'created_at'  => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%s', '%s', '%s' )
			);
			if ( false === $ok ) {
				$errors[] = sprintf( /* translators: %s: db error */ __( 'quotes insert failed: %s', 'aifirstsaas' ), $wpdb->last_error );
			} else {
				++$inserted;
			}
		}
		return $inserted;
	}

	/**
	 * Upsert a list of term-meta rows for a taxonomy.
	 *
	 * Creates the term if it doesn't exist. Writes to {prefix}aifirst_category_meta
	 * or {prefix}aifirst_feature_meta depending on the taxonomy.
	 *
	 * @since 0.3.0
	 * @param string   $taxonomy aifirst_category | aifirst_feature.
	 * @param array[]  $rows     Validated term-meta rows.
	 * @param string[] $errors   By-ref.
	 * @return void
	 */
	protected function upsert_term_meta( $taxonomy, array $rows, array &$errors ) {
		global $wpdb;

		$is_category = ( 'aifirst_category' === $taxonomy );
		$table       = $wpdb->prefix . ( $is_category ? 'aifirst_category_meta' : 'aifirst_feature_meta' );

		foreach ( $rows as $row ) {
			$term_id = $this->resolve_term_id( $row['term_name'], $row['term_slug'], $taxonomy );
			if ( 0 === $term_id ) {
				$errors[] = sprintf(
					/* translators: 1: taxonomy 2: term name */
					__( 'Could not resolve %1$s term for term_meta "%2$s".', 'aifirstsaas' ),
					$taxonomy,
					$row['term_name']
				);
				continue;
			}

			if ( $is_category ) {
				$data    = array(
					'term_id'            => $term_id,
					'icon'               => (string) $row['icon'],
					'intro'              => (string) $row['intro'],
					'what_ai_does_well'  => (string) $row['what_ai_does_well'],
					'whats_immature'     => (string) $row['whats_immature'],
					'honest_observation' => (string) $row['honest_observation'],
					'most_common_ai'     => (string) $row['most_common_ai'],
					'fastest_growing'    => (string) $row['fastest_growing'],
					'buyers_checklist'   => (string) $row['buyers_checklist'],
				);
				$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
			} else {
				$data    = array(
					'term_id'               => $term_id,
					'color'                 => (string) $row['color'],
					'feature_type'          => (string) $row['feature_type'],
					'description'           => (string) $row['description'],
					'why_matters'           => (string) $row['why_matters'],
					'evaluate'              => (string) $row['evaluate'],
					'related_features'      => (string) $row['related_features'],
					'how_it_works_surface'  => (string) $row['how_it_works_surface'],
					'how_it_works_deeper'   => (string) $row['how_it_works_deeper'],
					'vendors_dont_tell_you' => (string) $row['vendors_dont_tell_you'],
					'buyers_checklist'      => (string) $row['buyers_checklist'],
				);
				$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
			}

			$existing = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT " . ( $is_category ? 'cat_meta_id' : 'feat_meta_id' ) . " FROM {$table} WHERE term_id = %d LIMIT 1", $term_id )
			);

			if ( $existing > 0 ) {
				$result = $wpdb->update(
					$table,
					$data,
					array( ( $is_category ? 'cat_meta_id' : 'feat_meta_id' ) => $existing ),
					$formats,
					array( '%d' )
				);
			} else {
				$result = $wpdb->insert( $table, $data, $formats );
			}

			if ( false === $result ) {
				$errors[] = sprintf(
					/* translators: 1: taxonomy 2: term name */
					__( '%1$s term_meta write failed for "%2$s".', 'aifirstsaas' ),
					$taxonomy,
					$row['term_name']
				);
			}
		}
	}

	/**
	 * Find or create a term by name/slug within a taxonomy.
	 *
	 * @since 0.3.0
	 * @param string $name     Human name.
	 * @param string $slug     Slug.
	 * @param string $taxonomy Taxonomy slug.
	 * @return int term_id or 0 on failure.
	 */
	protected function resolve_term_id( $name, $slug, $taxonomy ) {
		if ( '' === $name && '' === $slug ) {
			return 0;
		}
		$lookup = '' !== $name ? $name : $slug;
		$term   = term_exists( $lookup, $taxonomy );
		if ( ! $term && '' !== $slug ) {
			$term = term_exists( $slug, $taxonomy );
		}
		if ( ! $term ) {
			$inserted = wp_insert_term( '' !== $name ? $name : $slug, $taxonomy, '' !== $slug ? array( 'slug' => $slug ) : array() );
			if ( is_wp_error( $inserted ) ) {
				return 0;
			}
			return (int) $inserted['term_id'];
		}
		if ( is_array( $term ) ) {
			return (int) $term['term_id'];
		}
		return 0;
	}
}
