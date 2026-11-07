<?php
/**
 * CSV importer — deterministic, no AI.
 *
 * Reads a CSV with a documented header, builds one canonical record per row,
 * calls AIFirst_Intake::save_record() per row, and returns per-row results.
 *
 * Expected header (see NOTES.md for full docs):
 *   title, tool_id, status, excerpt, content,
 *   categories, ai_features,
 *   company_name, website_url, pricing_url, initials, avatar_color, logo_url,
 *   wp_org_slug, subcategory, founded_year, headquarters,
 *   pricing_model, has_free_plan, starting_price_usd, pricing_note,
 *   ai_maturity_score, ai_since, ai_type, ai_on_free_plan,
 *   total_ai_features, native_ai_count, thirdparty_ai_count,
 *   last_ai_update, last_verified,
 *   company_size_smb, company_size_mid, company_size_ent,
 *   notable_customers, best_for, expert_take,
 *   meta_title, meta_description, h1,
 *   plans, faq, radar, cannot_do, relationships
 *
 * Sub-rows: multiple rows inside one cell use `||`; fields within a sub-row
 * use `~`; key=value pairs use `=`. Example for a single plan:
 *   plan_name=Creator~tier_order=1~price_usd_monthly=39~billing_cycle=monthly
 * Two plans:
 *   plan_name=Free~tier_order=1~price_usd_monthly=0~billing_cycle=free~is_free=1||plan_name=Pro~tier_order=2~price_usd_monthly=29~billing_cycle=monthly
 *
 * @package AIFirst
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_CSV_Importer.
 *
 * @since 0.2.0
 */
class AIFirst_CSV_Importer {

	const ROW_SEP   = '||';
	const FIELD_SEP = '~';
	const KV_SEP    = '=';

	/**
	 * Import a CSV file from disk.
	 *
	 * @since 0.2.0
	 * @param string $path Absolute path to a CSV file.
	 * @return array{results: array, errors: string[]}
	 */
	public function import_file( $path ) {
		if ( ! is_readable( $path ) ) {
			return array(
				'results' => array(),
				'errors'  => array( __( 'CSV file is not readable.', 'aifirstsaas' ) ),
			);
		}

		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return array(
				'results' => array(),
				'errors'  => array( __( 'Could not open CSV.', 'aifirstsaas' ) ),
			);
		}

		$header = fgetcsv( $handle );
		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return array(
				'results' => array(),
				'errors'  => array( __( 'CSV has no header row.', 'aifirstsaas' ) ),
			);
		}

		$header = array_map(
			static function ( $h ) {
				return strtolower( trim( (string) $h ) );
			},
			$header
		);

		$intake  = new AIFirst_Intake();
		$results = array();
		$errors  = array();
		$row_num = 1;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
			++$row_num;

			if ( count( array_filter( $row, static function ( $v ) { return '' !== trim( (string) $v ); } ) ) === 0 ) {
				continue;
			}

			$assoc = array();
			foreach ( $header as $i => $col ) {
				$assoc[ $col ] = isset( $row[ $i ] ) ? (string) $row[ $i ] : '';
			}

			$record = $this->row_to_record( $assoc );

			$validated = AIFirst_Record::validate( $record );
			if ( null === $validated['record'] ) {
				$results[ $row_num ] = array(
					'tool_id'  => isset( $assoc['tool_id'] ) ? $assoc['tool_id'] : '',
					'post_id'  => 0,
					'status'   => 'validation-error',
					'messages' => $validated['errors'],
				);
				continue;
			}

			$save = $intake->save_record( $validated['record'] );
			$results[ $row_num ] = array(
				'tool_id'  => (string) $save['tool_id'],
				'post_id'  => (int) $save['post_id'],
				'status'   => (string) $save['created_or_updated'],
				'messages' => (array) $save['errors'],
			);
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return array(
			'results' => $results,
			'errors'  => $errors,
		);
	}

	/**
	 * Convert one associative CSV row to a canonical record.
	 *
	 * @since 0.2.0
	 * @param array $r Associative row keyed by lowercased header.
	 * @return array
	 */
	protected function row_to_record( array $r ) {
		$g = function ( $key, $default = '' ) use ( $r ) {
			return isset( $r[ $key ] ) ? trim( (string) $r[ $key ] ) : $default;
		};

		$record = AIFirst_Record::skeleton();

		$record['tool']['title']       = $g( 'title' );
		$record['tool']['tool_id']     = $g( 'tool_id' );
		$record['tool']['excerpt']     = $g( 'excerpt' );
		$record['tool']['content']     = $g( 'content' );
		$record['tool']['status']      = '' !== $g( 'status' ) ? $g( 'status' ) : 'draft';
		$record['tool']['categories']  = $this->split_list( $g( 'categories' ) );
		$record['tool']['ai_features'] = $this->split_list( $g( 'ai_features' ) );

		$meta_keys = array(
			'company_name', 'website_url', 'pricing_url', 'initials', 'avatar_color',
			'logo_url', 'wp_org_slug', 'subcategory', 'founded_year', 'headquarters',
			'pricing_model', 'has_free_plan', 'starting_price_usd', 'pricing_note',
			'ai_maturity_score', 'ai_since', 'ai_type', 'ai_on_free_plan',
			'total_ai_features', 'native_ai_count', 'thirdparty_ai_count',
			'last_ai_update', 'last_verified',
			'company_size_smb', 'company_size_mid', 'company_size_ent',
			'notable_customers', 'best_for', 'expert_take',
			'meta_title', 'meta_description', 'h1',
		);
		foreach ( $meta_keys as $k ) {
			if ( isset( $r[ $k ] ) ) {
				$record['meta'][ $k ] = $g( $k );
			}
		}

		$record['plans']         = $this->parse_subrows( $g( 'plans' ) );
		$record['faq']           = $this->parse_subrows( $g( 'faq' ) );
		$record['radar']         = $this->parse_subrows( $g( 'radar' ) );
		$record['cannot_do']     = $this->parse_subrows( $g( 'cannot_do' ) );
		$record['relationships'] = $this->parse_subrows( $g( 'relationships' ) );

		return $record;
	}

	/**
	 * Split a comma/pipe list into an array of trimmed strings.
	 *
	 * @since 0.2.0
	 * @param string $value Raw.
	 * @return string[]
	 */
	protected function split_list( $value ) {
		$value = (string) $value;
		if ( '' === trim( $value ) ) {
			return array();
		}
		$parts = preg_split( '/[,|\n]+/', $value );
		return array_values(
			array_filter(
				array_map(
					static function ( $v ) {
						return trim( (string) $v );
					},
					$parts
				),
				static function ( $v ) {
					return '' !== $v;
				}
			)
		);
	}

	/**
	 * Parse a "||"-separated list of "~"-separated "key=value" sub-rows.
	 *
	 * @since 0.2.0
	 * @param string $value Raw cell.
	 * @return array[]
	 */
	protected function parse_subrows( $value ) {
		$value = (string) $value;
		if ( '' === trim( $value ) ) {
			return array();
		}
		$rows = explode( self::ROW_SEP, $value );
		$out  = array();
		foreach ( $rows as $raw_row ) {
			$raw_row = trim( $raw_row );
			if ( '' === $raw_row ) {
				continue;
			}
			$pairs = explode( self::FIELD_SEP, $raw_row );
			$assoc = array();
			foreach ( $pairs as $pair ) {
				$pair = trim( $pair );
				if ( '' === $pair ) {
					continue;
				}
				if ( false === strpos( $pair, self::KV_SEP ) ) {
					continue;
				}
				list( $k, $v ) = explode( self::KV_SEP, $pair, 2 );
				$k             = trim( (string) $k );
				$v             = trim( (string) $v );
				if ( '' === $k ) {
					continue;
				}
				$assoc[ $k ] = $v;
			}
			if ( ! empty( $assoc ) ) {
				$out[] = $assoc;
			}
		}
		return $out;
	}
}
