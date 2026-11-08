<?php
/**
 * Canonical tool record shape + validator/sanitizer.
 *
 * Every intake path (admin UI, CSV, REST, AI client) produces or consumes a
 * record in this exact shape. Keep the canonical definition in one place.
 *
 * 0.3.0 additions:
 *  - tool_features[]            : per-tool feature detail (plan/status/native/since)
 *  - maturity_breakdown[]       : per-criterion maturity rows
 *  - industries[]               : name + percentage
 *  - sentiment[]                : review source + score + count
 *  - quotes[]                   : user quotes with role
 *  - category_meta[]            : long-form content per category term
 *  - feature_meta[]             : long-form content per feature term
 *  - meta.company_group_id      : links tools made by the same company
 *  - meta.company_size_*        : now percentages (0-100), not booleans
 *  - cannot_do[].workaround     : new text field
 *  - radar alias enum normalized: "New Feature"/"Plan Change"/"Removed" → canonical
 *
 * @package AIFirst
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Record.
 *
 * @since 0.2.0
 */
class AIFirst_Record {

	/**
	 * Allowed enum values, keyed by field.
	 *
	 * @since 0.2.0
	 * @var array<string, string[]>
	 */
	protected static $enums = array(
		'ai_type'           => array( 'native', 'third-party', 'both', '' ),
		'pricing_model'     => array( 'free', 'freemium', 'paid-only', 'subscription', 'one-time', 'bundle', 'discontinued', '' ),
		'billing_cycle'     => array( 'monthly', 'annual', 'one-time', 'free', '' ),
		'change_type'       => array( 'launch', 'new-feature', 'upgraded', 'deprecated', 'pricing-change', '' ),
		'confidence'        => array( 'low', 'medium', 'high' ),
		'row_status'        => array( 'draft', 'verified', 'published' ),
		'relationship_type' => array( 'alternative', 'complement', 'competitor', 'integration' ),
		'post_status'       => array( 'draft', 'publish' ),
		'feature_status'    => array( 'stable', 'beta', 'alpha', 'deprecated', 'coming-soon', '' ),
		'feature_plan'      => array( 'Free', 'Starter', 'Pro', 'Enterprise', 'Add-on', '' ),
		'feature_type'      => array( 'universal', 'category-specific', '' ),
	);

	/**
	 * Display-label aliases for the radar change_type enum. Design copy uses
	 * "New Feature"/"Plan Change"/"Removed"; storage keeps the canonical
	 * kebab-case values.
	 *
	 * @since 0.3.0
	 * @var array<string, string>
	 */
	protected static $change_type_aliases = array(
		'new feature'  => 'new-feature',
		'plan change'  => 'pricing-change',
		'removed'      => 'deprecated',
		'pricing'      => 'pricing-change',
		'launch'       => 'launch',
		'upgraded'     => 'upgraded',
		'deprecated'   => 'deprecated',
	);

	/**
	 * Return an empty record skeleton — handy for the admin review form.
	 *
	 * @since 0.2.0
	 * @return array
	 */
	public static function skeleton() {
		return array(
			'tool'               => array(
				'tool_id'     => '',
				'title'       => '',
				'content'     => '',
				'excerpt'     => '',
				'categories'  => array(),
				'ai_features' => array(),
				'status'      => 'draft',
			),
			'meta'               => array(
				'company_name'        => '',
				'website_url'         => '',
				'pricing_url'         => '',
				'initials'            => '',
				'avatar_color'        => '',
				'logo_url'            => '',
				'wp_org_slug'         => '',
				'subcategory'         => '',
				'founded_year'        => null,
				'headquarters'        => '',
				'pricing_model'       => '',
				'has_free_plan'       => 0,
				'starting_price_usd'  => null,
				'pricing_note'        => '',
				'ai_maturity_score'   => null,
				'ai_since'            => null,
				'ai_type'             => '',
				'ai_on_free_plan'     => 0,
				'total_ai_features'   => 0,
				'native_ai_count'     => 0,
				'thirdparty_ai_count' => 0,
				'last_ai_update'      => '',
				'last_verified'       => '',
				'company_size_smb'    => 0,
				'company_size_mid'    => 0,
				'company_size_ent'    => 0,
				'company_group_id'    => '',
				'notable_customers'   => '',
				'best_for'            => '',
				'expert_take'         => '',
				'meta_title'          => '',
				'meta_description'    => '',
				'h1'                  => '',
			),
			'plans'              => array(),
			'faq'                => array(),
			'radar'              => array(),
			'cannot_do'          => array(),
			'relationships'      => array(),
			'tool_features'      => array(),
			'maturity_breakdown' => array(),
			'industries'         => array(),
			'sentiment'          => array(),
			'quotes'             => array(),
			'category_meta'      => array(),
			'feature_meta'       => array(),
		);
	}

	/**
	 * Validate and sanitize a raw record.
	 *
	 * Returns [ 'record' => array|null, 'errors' => string[] ]. If errors is
	 * non-empty the record is unsafe to write.
	 *
	 * @since 0.2.0
	 * @param array $raw User-supplied record payload.
	 * @return array{record: ?array, errors: string[]}
	 */
	public static function validate( $raw ) {
		$errors = array();
		$out    = self::skeleton();

		if ( ! is_array( $raw ) ) {
			return array(
				'record' => null,
				'errors' => array( __( 'Record must be an object.', 'aifirstsaas' ) ),
			);
		}

		// Tool block.
		$tool_in = isset( $raw['tool'] ) && is_array( $raw['tool'] ) ? $raw['tool'] : array();

		$title = isset( $tool_in['title'] ) ? sanitize_text_field( (string) $tool_in['title'] ) : '';
		if ( '' === $title ) {
			$errors[] = __( 'tool.title is required.', 'aifirstsaas' );
		}

		$tool_id = isset( $tool_in['tool_id'] ) ? sanitize_title( (string) $tool_in['tool_id'] ) : '';
		if ( '' === $tool_id && '' !== $title ) {
			$tool_id = sanitize_title( $title );
		}
		if ( '' === $tool_id ) {
			$errors[] = __( 'tool.tool_id could not be derived.', 'aifirstsaas' );
		}

		$status = isset( $tool_in['status'] ) ? (string) $tool_in['status'] : 'draft';
		if ( ! in_array( $status, self::$enums['post_status'], true ) ) {
			$errors[] = sprintf( /* translators: %s: value */ __( 'tool.status "%s" is not allowed.', 'aifirstsaas' ), $status );
			$status   = 'draft';
		}

		$out['tool']['tool_id']     = $tool_id;
		$out['tool']['title']       = $title;
		$out['tool']['content']     = isset( $tool_in['content'] ) ? wp_kses_post( (string) $tool_in['content'] ) : '';
		$out['tool']['excerpt']     = isset( $tool_in['excerpt'] ) ? sanitize_textarea_field( (string) $tool_in['excerpt'] ) : '';
		$out['tool']['categories']  = self::sanitize_term_list( isset( $tool_in['categories'] ) ? $tool_in['categories'] : array() );
		$out['tool']['ai_features'] = self::sanitize_term_list( isset( $tool_in['ai_features'] ) ? $tool_in['ai_features'] : array() );
		$out['tool']['status']      = $status;

		// Meta block.
		$meta_in = isset( $raw['meta'] ) && is_array( $raw['meta'] ) ? $raw['meta'] : array();
		$m       = &$out['meta'];

		$m['company_name']        = isset( $meta_in['company_name'] ) ? sanitize_text_field( (string) $meta_in['company_name'] ) : '';
		$m['website_url']         = isset( $meta_in['website_url'] ) ? esc_url_raw( (string) $meta_in['website_url'] ) : '';
		$m['pricing_url']         = isset( $meta_in['pricing_url'] ) ? esc_url_raw( (string) $meta_in['pricing_url'] ) : '';
		$m['initials']            = isset( $meta_in['initials'] ) ? substr( sanitize_text_field( (string) $meta_in['initials'] ), 0, 8 ) : '';
		$m['avatar_color']        = self::sanitize_hex_color( isset( $meta_in['avatar_color'] ) ? (string) $meta_in['avatar_color'] : '' );
		$m['logo_url']            = isset( $meta_in['logo_url'] ) ? esc_url_raw( (string) $meta_in['logo_url'] ) : '';
		$m['wp_org_slug']         = isset( $meta_in['wp_org_slug'] ) ? sanitize_key( (string) $meta_in['wp_org_slug'] ) : '';
		$m['subcategory']         = isset( $meta_in['subcategory'] ) ? sanitize_text_field( (string) $meta_in['subcategory'] ) : '';
		$m['founded_year']        = self::sanitize_year( isset( $meta_in['founded_year'] ) ? $meta_in['founded_year'] : null );
		$m['headquarters']        = isset( $meta_in['headquarters'] ) ? sanitize_text_field( (string) $meta_in['headquarters'] ) : '';
		$m['pricing_model']       = self::sanitize_enum( isset( $meta_in['pricing_model'] ) ? (string) $meta_in['pricing_model'] : '', 'pricing_model', $errors, 'meta.pricing_model' );
		$m['has_free_plan']       = self::sanitize_tristate( isset( $meta_in['has_free_plan'] ) ? $meta_in['has_free_plan'] : 0 );
		$m['starting_price_usd']  = self::sanitize_decimal( isset( $meta_in['starting_price_usd'] ) ? $meta_in['starting_price_usd'] : null );
		$m['pricing_note']        = isset( $meta_in['pricing_note'] ) ? sanitize_textarea_field( (string) $meta_in['pricing_note'] ) : '';
		$m['ai_maturity_score']   = self::sanitize_score( isset( $meta_in['ai_maturity_score'] ) ? $meta_in['ai_maturity_score'] : null );
		$m['ai_since']            = self::sanitize_year( isset( $meta_in['ai_since'] ) ? $meta_in['ai_since'] : null );
		$m['ai_type']             = self::sanitize_enum( isset( $meta_in['ai_type'] ) ? (string) $meta_in['ai_type'] : '', 'ai_type', $errors, 'meta.ai_type' );
		$m['ai_on_free_plan']     = self::sanitize_bool( isset( $meta_in['ai_on_free_plan'] ) ? $meta_in['ai_on_free_plan'] : false );
		$m['total_ai_features']   = absint( isset( $meta_in['total_ai_features'] ) ? $meta_in['total_ai_features'] : 0 );
		$m['native_ai_count']     = absint( isset( $meta_in['native_ai_count'] ) ? $meta_in['native_ai_count'] : 0 );
		$m['thirdparty_ai_count'] = absint( isset( $meta_in['thirdparty_ai_count'] ) ? $meta_in['thirdparty_ai_count'] : 0 );
		$m['last_ai_update']      = self::sanitize_date( isset( $meta_in['last_ai_update'] ) ? (string) $meta_in['last_ai_update'] : '' );
		$m['last_verified']       = self::sanitize_date( isset( $meta_in['last_verified'] ) ? (string) $meta_in['last_verified'] : '' );
		$m['company_size_smb']    = self::sanitize_percent( isset( $meta_in['company_size_smb'] ) ? $meta_in['company_size_smb'] : 0 );
		$m['company_size_mid']    = self::sanitize_percent( isset( $meta_in['company_size_mid'] ) ? $meta_in['company_size_mid'] : 0 );
		$m['company_size_ent']    = self::sanitize_percent( isset( $meta_in['company_size_ent'] ) ? $meta_in['company_size_ent'] : 0 );
		$m['company_group_id']    = isset( $meta_in['company_group_id'] ) ? sanitize_title( (string) $meta_in['company_group_id'] ) : '';
		$m['notable_customers']   = isset( $meta_in['notable_customers'] ) ? sanitize_textarea_field( (string) $meta_in['notable_customers'] ) : '';
		$m['best_for']            = isset( $meta_in['best_for'] ) ? sanitize_textarea_field( (string) $meta_in['best_for'] ) : '';
		$m['expert_take']         = isset( $meta_in['expert_take'] ) ? wp_kses_post( (string) $meta_in['expert_take'] ) : '';
		$m['meta_title']          = isset( $meta_in['meta_title'] ) ? sanitize_text_field( (string) $meta_in['meta_title'] ) : '';
		$m['meta_description']    = isset( $meta_in['meta_description'] ) ? sanitize_text_field( (string) $meta_in['meta_description'] ) : '';
		$m['h1']                  = isset( $meta_in['h1'] ) ? sanitize_text_field( (string) $meta_in['h1'] ) : '';

		// Children — existing.
		$out['plans']         = self::sanitize_plans( isset( $raw['plans'] ) ? $raw['plans'] : array(), $errors );
		$out['faq']           = self::sanitize_faq( isset( $raw['faq'] ) ? $raw['faq'] : array(), $errors );
		$out['radar']         = self::sanitize_radar( isset( $raw['radar'] ) ? $raw['radar'] : array(), $errors );
		$out['cannot_do']     = self::sanitize_cannot_do( isset( $raw['cannot_do'] ) ? $raw['cannot_do'] : array() );
		$out['relationships'] = self::sanitize_relationships( isset( $raw['relationships'] ) ? $raw['relationships'] : array(), $errors );

		// Children — new in 0.3.0.
		$out['tool_features']      = self::sanitize_tool_features( isset( $raw['tool_features'] ) ? $raw['tool_features'] : array(), $errors, $out );
		$out['maturity_breakdown'] = self::sanitize_maturity_breakdown( isset( $raw['maturity_breakdown'] ) ? $raw['maturity_breakdown'] : array() );
		$out['industries']         = self::sanitize_industries( isset( $raw['industries'] ) ? $raw['industries'] : array() );
		$out['sentiment']          = self::sanitize_sentiment( isset( $raw['sentiment'] ) ? $raw['sentiment'] : array() );
		$out['quotes']             = self::sanitize_quotes( isset( $raw['quotes'] ) ? $raw['quotes'] : array() );
		$out['category_meta']      = self::sanitize_term_meta_list( isset( $raw['category_meta'] ) ? $raw['category_meta'] : array(), 'category' );
		$out['feature_meta']       = self::sanitize_term_meta_list( isset( $raw['feature_meta'] ) ? $raw['feature_meta'] : array(), 'feature', $errors );

		// If tool_features supplied, back-fill tool.ai_features so the term
		// attachment still happens even when the caller only sent the
		// structured join.
		if ( ! empty( $out['tool_features'] ) ) {
			$names = array();
			foreach ( $out['tool_features'] as $tf ) {
				if ( '' !== $tf['feature_name'] ) {
					$names[] = $tf['feature_name'];
				}
			}
			$out['tool']['ai_features'] = array_values(
				array_unique( array_merge( $out['tool']['ai_features'], $names ) )
			);
		}

		return array(
			'record' => empty( $errors ) ? $out : null,
			'errors' => $errors,
		);
	}

	/**
	 * Validate an enum, logging unknown values.
	 *
	 * @since 0.2.0
	 * @param string   $value Raw value.
	 * @param string   $key   Enum key in self::$enums.
	 * @param string[] $errors By-reference error list.
	 * @param string   $path  Field path for the error message.
	 * @return string
	 */
	protected static function sanitize_enum( $value, $key, &$errors, $path ) {
		$value = sanitize_text_field( $value );
		if ( ! in_array( $value, self::$enums[ $key ], true ) ) {
			$errors[] = sprintf(
				/* translators: 1: field path, 2: value */
				__( '%1$s "%2$s" is not an allowed value.', 'aifirstsaas' ),
				$path,
				$value
			);
			return '';
		}
		return $value;
	}

	/**
	 * Resolve a radar change_type value, accepting design-label aliases.
	 *
	 * @since 0.3.0
	 * @param string   $value Raw.
	 * @param string[] $errors By-ref.
	 * @param string   $path  Field path for errors.
	 * @return string
	 */
	protected static function sanitize_change_type( $value, &$errors, $path ) {
		$value = trim( strtolower( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		if ( isset( self::$change_type_aliases[ $value ] ) ) {
			return self::$change_type_aliases[ $value ];
		}
		if ( in_array( $value, self::$enums['change_type'], true ) ) {
			return $value;
		}
		$errors[] = sprintf(
			/* translators: 1: field path, 2: value */
			__( '%1$s "%2$s" is not an allowed change type.', 'aifirstsaas' ),
			$path,
			$value
		);
		return '';
	}

	/**
	 * 0/1 boolean (tinyint).
	 *
	 * @since 0.2.0
	 * @param mixed $value Raw.
	 * @return int
	 */
	protected static function sanitize_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 1 : 0;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value ? 1 : 0;
		}
		$lower = strtolower( (string) $value );
		return in_array( $lower, array( 'yes', 'true', 'y', '1', 'on' ), true ) ? 1 : 0;
	}

	/**
	 * Tristate for has_free_plan: 0 = no, 1 = yes, 2 = add-on.
	 *
	 * @since 0.2.0
	 * @param mixed $value Raw.
	 * @return int
	 */
	protected static function sanitize_tristate( $value ) {
		if ( is_numeric( $value ) ) {
			$i = (int) $value;
			return ( 0 === $i || 1 === $i || 2 === $i ) ? $i : 0;
		}
		$lower = strtolower( trim( (string) $value ) );
		if ( in_array( $lower, array( 'add-on', 'addon', 'add_on' ), true ) ) {
			return 2;
		}
		return self::sanitize_bool( $value );
	}

	/**
	 * Decimal(10,2) or null.
	 *
	 * @since 0.2.0
	 * @param mixed $value Raw.
	 * @return float|null
	 */
	protected static function sanitize_decimal( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		$f = (float) $value;
		if ( $f < 0 ) {
			$f = 0.0;
		}
		if ( $f > 99999999.99 ) {
			$f = 99999999.99;
		}
		return round( $f, 2 );
	}

	/**
	 * Year as 4-digit int or null.
	 *
	 * @since 0.2.0
	 * @param mixed $value Raw.
	 * @return int|null
	 */
	protected static function sanitize_year( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$y = (int) $value;
		if ( $y < 1900 || $y > 2100 ) {
			return null;
		}
		return $y;
	}

	/**
	 * ai_maturity_score 1-10 or null.
	 *
	 * @since 0.2.0
	 * @param mixed $value Raw.
	 * @return int|null
	 */
	protected static function sanitize_score( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$n = (int) $value;
		if ( $n < 1 ) {
			return 1;
		}
		if ( $n > 10 ) {
			return 10;
		}
		return $n;
	}

	/**
	 * Percentage 0-100.
	 *
	 * @since 0.3.0
	 * @param mixed $value Raw.
	 * @return int
	 */
	protected static function sanitize_percent( $value ) {
		if ( null === $value || '' === $value ) {
			return 0;
		}
		$n = (int) $value;
		if ( $n < 0 ) {
			return 0;
		}
		if ( $n > 100 ) {
			return 100;
		}
		return $n;
	}

	/**
	 * YYYY-MM-DD date string or ''.
	 *
	 * @since 0.2.0
	 * @param string $value Raw.
	 * @return string
	 */
	protected static function sanitize_date( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$ts = strtotime( $value );
		if ( false === $ts ) {
			return '';
		}
		return gmdate( 'Y-m-d', $ts );
	}

	/**
	 * 7-character hex color or ''.
	 *
	 * @since 0.2.0
	 * @param string $value Raw.
	 * @return string
	 */
	protected static function sanitize_hex_color( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^#([A-Fa-f0-9]{6})$/', $value, $m ) ) {
			return '#' . strtoupper( $m[1] );
		}
		if ( preg_match( '/^([A-Fa-f0-9]{6})$/', $value, $m ) ) {
			return '#' . strtoupper( $m[1] );
		}
		return '';
	}

	/**
	 * Term list: accepts array or comma-separated string, returns clean names.
	 *
	 * @since 0.2.0
	 * @param mixed $value Raw.
	 * @return string[]
	 */
	protected static function sanitize_term_list( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[,|\n]+/', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $v ) {
			$name = sanitize_text_field( (string) $v );
			if ( '' !== $name ) {
				$out[] = $name;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Sanitize plans[] array.
	 *
	 * @since 0.2.0
	 * @param mixed    $plans Raw.
	 * @param string[] $errors By-ref.
	 * @return array[]
	 */
	protected static function sanitize_plans( $plans, &$errors ) {
		if ( ! is_array( $plans ) ) {
			return array();
		}
		$out = array();
		foreach ( $plans as $i => $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$plan_name = isset( $p['plan_name'] ) ? sanitize_text_field( (string) $p['plan_name'] ) : '';
			if ( '' === $plan_name ) {
				continue;
			}
			$out[] = array(
				'plan_name'         => $plan_name,
				'tier_order'        => isset( $p['tier_order'] ) ? max( 1, min( 255, absint( $p['tier_order'] ) ) ) : ( $i + 1 ),
				'price_usd_monthly' => self::sanitize_decimal( isset( $p['price_usd_monthly'] ) ? $p['price_usd_monthly'] : null ),
				'price_usd_annual'  => self::sanitize_decimal( isset( $p['price_usd_annual'] ) ? $p['price_usd_annual'] : null ),
				'billing_cycle'     => self::sanitize_enum( isset( $p['billing_cycle'] ) ? (string) $p['billing_cycle'] : '', 'billing_cycle', $errors, "plans[$i].billing_cycle" ),
				'user_limit'        => isset( $p['user_limit'] ) ? sanitize_text_field( (string) $p['user_limit'] ) : '',
				'feature_list'      => isset( $p['feature_list'] ) ? sanitize_textarea_field( (string) $p['feature_list'] ) : '',
				'notes'             => isset( $p['notes'] ) ? sanitize_textarea_field( (string) $p['notes'] ) : '',
				'is_free'           => self::sanitize_bool( isset( $p['is_free'] ) ? $p['is_free'] : false ),
				'is_most_popular'   => self::sanitize_bool( isset( $p['is_most_popular'] ) ? $p['is_most_popular'] : false ),
			);
		}
		return $out;
	}

	/**
	 * Sanitize faq[] array.
	 *
	 * @since 0.2.0
	 * @param mixed    $faq Raw.
	 * @param string[] $errors By-ref.
	 * @return array[]
	 */
	protected static function sanitize_faq( $faq, &$errors ) {
		unset( $errors );
		if ( ! is_array( $faq ) ) {
			return array();
		}
		$out = array();
		foreach ( $faq as $i => $q ) {
			if ( ! is_array( $q ) ) {
				continue;
			}
			$question = isset( $q['question'] ) ? sanitize_text_field( (string) $q['question'] ) : '';
			$answer   = isset( $q['answer'] ) ? wp_kses_post( (string) $q['answer'] ) : '';
			if ( '' === $question || '' === $answer ) {
				continue;
			}
			$out[] = array(
				'category_id' => isset( $q['category_id'] ) ? sanitize_text_field( (string) $q['category_id'] ) : '',
				'faq_order'   => isset( $q['faq_order'] ) ? max( 1, min( 255, absint( $q['faq_order'] ) ) ) : ( $i + 1 ),
				'question'    => $question,
				'answer'      => $answer,
			);
		}
		return $out;
	}

	/**
	 * Sanitize radar[] array.
	 *
	 * @since 0.2.0
	 * @param mixed    $radar Raw.
	 * @param string[] $errors By-ref.
	 * @return array[]
	 */
	protected static function sanitize_radar( $radar, &$errors ) {
		if ( ! is_array( $radar ) ) {
			return array();
		}
		$out = array();
		foreach ( $radar as $i => $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$what_changed = isset( $r['what_changed'] ) ? sanitize_textarea_field( (string) $r['what_changed'] ) : '';
			if ( '' === $what_changed ) {
				continue;
			}
			$out[] = array(
				'change_date'    => self::sanitize_date( isset( $r['change_date'] ) ? (string) $r['change_date'] : '' ),
				'change_type'    => self::sanitize_change_type( isset( $r['change_type'] ) ? (string) $r['change_type'] : '', $errors, "radar[$i].change_type" ),
				'what_changed'   => $what_changed,
				'why_it_matters' => isset( $r['why_it_matters'] ) ? sanitize_textarea_field( (string) $r['why_it_matters'] ) : '',
				'source_url'     => isset( $r['source_url'] ) ? esc_url_raw( (string) $r['source_url'] ) : '',
				'version_label'  => isset( $r['version_label'] ) ? sanitize_text_field( (string) $r['version_label'] ) : '',
				'confidence'     => self::sanitize_enum( isset( $r['confidence'] ) ? (string) $r['confidence'] : 'medium', 'confidence', $errors, "radar[$i].confidence" ),
				'row_status'     => self::sanitize_enum( isset( $r['row_status'] ) ? (string) $r['row_status'] : 'draft', 'row_status', $errors, "radar[$i].row_status" ),
			);
		}
		return $out;
	}

	/**
	 * Sanitize cannot_do[] — now includes a workaround field.
	 *
	 * @since 0.2.0
	 * @param mixed $cannot Raw.
	 * @return array[]
	 */
	protected static function sanitize_cannot_do( $cannot ) {
		if ( ! is_array( $cannot ) ) {
			return array();
		}
		$out = array();
		foreach ( $cannot as $i => $c ) {
			if ( is_string( $c ) ) {
				$c = array( 'limitation_text' => $c );
			}
			if ( ! is_array( $c ) ) {
				continue;
			}
			$text = isset( $c['limitation_text'] ) ? sanitize_textarea_field( (string) $c['limitation_text'] ) : '';
			if ( '' === $text ) {
				continue;
			}
			$out[] = array(
				'order_index'     => isset( $c['order_index'] ) ? max( 1, min( 255, absint( $c['order_index'] ) ) ) : ( $i + 1 ),
				'limitation_text' => $text,
				'workaround'      => isset( $c['workaround'] ) ? sanitize_textarea_field( (string) $c['workaround'] ) : '',
			);
		}
		return $out;
	}

	/**
	 * Sanitize relationships[] array.
	 *
	 * @since 0.2.0
	 * @param mixed    $rel Raw.
	 * @param string[] $errors By-ref.
	 * @return array[]
	 */
	protected static function sanitize_relationships( $rel, &$errors ) {
		if ( ! is_array( $rel ) ) {
			return array();
		}
		$out = array();
		foreach ( $rel as $i => $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$target = isset( $r['target_tool_id'] ) ? sanitize_title( (string) $r['target_tool_id'] ) : '';
			if ( '' === $target ) {
				continue;
			}
			$type = self::sanitize_enum( isset( $r['relationship_type'] ) ? (string) $r['relationship_type'] : '', 'relationship_type', $errors, "relationships[$i].relationship_type" );
			if ( '' === $type ) {
				continue;
			}
			$out[] = array(
				'target_tool_id'    => $target,
				'relationship_type' => $type,
				'notes'             => isset( $r['notes'] ) ? sanitize_textarea_field( (string) $r['notes'] ) : '',
			);
		}
		return $out;
	}

	/**
	 * Sanitize tool_features[] — the per-tool join of feature term + plan/
	 * status/is_native/since.
	 *
	 * @since 0.3.0
	 * @param mixed    $rows Raw.
	 * @param string[] $errors By-ref.
	 * @param array    $out    Current output record (unused but reserved).
	 * @return array[]
	 */
	protected static function sanitize_tool_features( $rows, &$errors, $out ) {
		unset( $out );
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$result = array();
		foreach ( $rows as $i => $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$name = isset( $r['feature_name'] ) ? sanitize_text_field( (string) $r['feature_name'] ) : '';
			$slug = isset( $r['feature_slug'] ) ? sanitize_title( (string) $r['feature_slug'] ) : '';
			if ( '' === $slug && '' !== $name ) {
				$slug = sanitize_title( $name );
			}
			if ( '' === $slug ) {
				continue;
			}
			if ( '' === $name ) {
				$name = $slug;
			}

			$plan_raw   = isset( $r['plan'] ) ? (string) $r['plan'] : '';
			$status_raw = isset( $r['status'] ) ? strtolower( (string) $r['status'] ) : '';

			$plan   = sanitize_text_field( $plan_raw );
			$status = in_array( $status_raw, self::$enums['feature_status'], true ) ? $status_raw : sanitize_key( $status_raw );

			$result[] = array(
				'feature_slug' => $slug,
				'feature_name' => $name,
				'plan'         => $plan,
				'status'       => $status,
				'is_native'    => self::sanitize_bool( isset( $r['is_native'] ) ? $r['is_native'] : 1 ),
				'since'        => isset( $r['since'] ) ? substr( sanitize_text_field( (string) $r['since'] ), 0, 20 ) : '',
				'feature_note' => isset( $r['feature_note'] ) ? substr( sanitize_text_field( (string) $r['feature_note'] ), 0, 500 ) : '',
			);
			unset( $i );
		}
		// Deduplicate by feature_slug (last one wins).
		$by_slug = array();
		foreach ( $result as $row ) {
			$by_slug[ $row['feature_slug'] ] = $row;
		}
		return array_values( $by_slug );
	}

	/**
	 * Sanitize maturity_breakdown[] rows.
	 *
	 * @since 0.3.0
	 * @param mixed $rows Raw.
	 * @return array[]
	 */
	protected static function sanitize_maturity_breakdown( $rows ) {
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $i => $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$criterion = isset( $r['criterion'] ) ? sanitize_text_field( (string) $r['criterion'] ) : '';
			if ( '' === $criterion ) {
				continue;
			}
			$out[] = array(
				'order_index'   => isset( $r['order_index'] ) ? max( 1, min( 255, absint( $r['order_index'] ) ) ) : ( $i + 1 ),
				'criterion'     => $criterion,
				'score_display' => isset( $r['score_display'] ) ? substr( sanitize_text_field( (string) $r['score_display'] ), 0, 20 ) : '',
				'score_numeric' => self::sanitize_decimal( isset( $r['score_numeric'] ) ? $r['score_numeric'] : null ),
				'score_color'   => isset( $r['score_color'] ) ? substr( sanitize_text_field( (string) $r['score_color'] ), 0, 20 ) : '',
			);
		}
		return $out;
	}

	/**
	 * Sanitize industries[] rows (name + percentage).
	 *
	 * @since 0.3.0
	 * @param mixed $rows Raw.
	 * @return array[]
	 */
	protected static function sanitize_industries( $rows ) {
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $i => $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$name = isset( $r['name'] ) ? sanitize_text_field( (string) $r['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$out[] = array(
				'order_index' => isset( $r['order_index'] ) ? max( 1, min( 255, absint( $r['order_index'] ) ) ) : ( $i + 1 ),
				'name'        => $name,
				'pct'         => self::sanitize_percent( isset( $r['pct'] ) ? $r['pct'] : 0 ),
			);
		}
		return $out;
	}

	/**
	 * Sanitize sentiment[] rows (source + score + review_count).
	 *
	 * @since 0.3.0
	 * @param mixed $rows Raw.
	 * @return array[]
	 */
	protected static function sanitize_sentiment( $rows ) {
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$source = isset( $r['source'] ) ? sanitize_text_field( (string) $r['source'] ) : '';
			if ( '' === $source ) {
				continue;
			}
			$score = null;
			if ( isset( $r['score'] ) && '' !== $r['score'] && is_numeric( $r['score'] ) ) {
				$score = round( max( 0, min( 5, (float) $r['score'] ) ), 2 );
			}
			$out[] = array(
				'source'       => $source,
				'score'        => $score,
				'review_count' => absint( isset( $r['review_count'] ) ? $r['review_count'] : 0 ),
				'source_url'   => isset( $r['source_url'] ) ? esc_url_raw( (string) $r['source_url'] ) : '',
			);
		}
		return $out;
	}

	/**
	 * Sanitize quotes[] rows.
	 *
	 * @since 0.3.0
	 * @param mixed $rows Raw.
	 * @return array[]
	 */
	protected static function sanitize_quotes( $rows ) {
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $i => $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$text = isset( $r['quote_text'] ) ? sanitize_textarea_field( (string) $r['quote_text'] ) : '';
			if ( '' === $text ) {
				continue;
			}
			$out[] = array(
				'order_index' => isset( $r['order_index'] ) ? max( 1, min( 255, absint( $r['order_index'] ) ) ) : ( $i + 1 ),
				'quote_text'  => $text,
				'role'        => isset( $r['role'] ) ? sanitize_text_field( (string) $r['role'] ) : '',
			);
		}
		return $out;
	}

	/**
	 * Sanitize a list of term-meta entries (categories or features).
	 *
	 * Each entry is keyed by the term name/slug (`term` field) and carries the
	 * long-form content. The intake service resolves or creates the term.
	 *
	 * @since 0.3.0
	 * @param mixed    $rows   Raw.
	 * @param string   $kind   'category' or 'feature'.
	 * @param string[] $errors By-ref, used for feature_type enum validation.
	 * @return array[]
	 */
	protected static function sanitize_term_meta_list( $rows, $kind, &$errors = null ) {
		if ( ! is_array( $rows ) ) {
			return array();
		}
		$out = array();
		foreach ( $rows as $i => $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$term_name = isset( $r['term'] ) ? sanitize_text_field( (string) $r['term'] ) : '';
			if ( '' === $term_name && isset( $r['term_name'] ) ) {
				$term_name = sanitize_text_field( (string) $r['term_name'] );
			}
			$term_slug = isset( $r['term_slug'] ) ? sanitize_title( (string) $r['term_slug'] ) : '';
			if ( '' === $term_slug && '' !== $term_name ) {
				$term_slug = sanitize_title( $term_name );
			}
			if ( '' === $term_name && '' === $term_slug ) {
				continue;
			}

			$row = array(
				'term_name'         => $term_name,
				'term_slug'         => $term_slug,
				'intro'             => isset( $r['intro'] ) ? wp_kses_post( (string) $r['intro'] ) : '',
				'buyers_checklist'  => self::encode_list( isset( $r['buyers_checklist'] ) ? $r['buyers_checklist'] : array() ),
			);

			if ( 'category' === $kind ) {
				$row['icon']               = isset( $r['icon'] ) ? substr( sanitize_text_field( (string) $r['icon'] ), 0, 20 ) : '';
				$row['what_ai_does_well']  = isset( $r['what_ai_does_well'] ) ? wp_kses_post( (string) $r['what_ai_does_well'] ) : '';
				$row['whats_immature']     = isset( $r['whats_immature'] ) ? wp_kses_post( (string) $r['whats_immature'] ) : '';
				$row['honest_observation'] = isset( $r['honest_observation'] ) ? wp_kses_post( (string) $r['honest_observation'] ) : '';
				$row['most_common_ai']     = isset( $r['most_common_ai'] ) ? sanitize_text_field( (string) $r['most_common_ai'] ) : '';
				$row['fastest_growing']    = isset( $r['fastest_growing'] ) ? sanitize_text_field( (string) $r['fastest_growing'] ) : '';
			} else {
				$feature_type_raw = isset( $r['feature_type'] ) ? strtolower( (string) $r['feature_type'] ) : '';
				$feature_type     = in_array( $feature_type_raw, self::$enums['feature_type'], true )
					? $feature_type_raw
					: '';
				if ( '' !== $feature_type_raw && '' === $feature_type && is_array( $errors ) ) {
					$errors[] = sprintf(
						/* translators: 1: index 2: value */
						__( 'feature_meta[%1$d].feature_type "%2$s" is not an allowed value.', 'aifirstsaas' ),
						$i,
						$feature_type_raw
					);
				}

				$row['color']                 = self::sanitize_hex_color( isset( $r['color'] ) ? (string) $r['color'] : '' );
				$row['feature_type']          = $feature_type;
				$row['description']           = isset( $r['description'] ) ? wp_kses_post( (string) $r['description'] ) : '';
				$row['why_matters']           = isset( $r['why_matters'] ) ? wp_kses_post( (string) $r['why_matters'] ) : '';
				$row['evaluate']              = self::encode_list( isset( $r['evaluate'] ) ? $r['evaluate'] : array() );
				$row['related_features']      = self::encode_list( isset( $r['related_features'] ) ? $r['related_features'] : array(), true );
				$row['how_it_works_surface']  = isset( $r['how_it_works_surface'] ) ? wp_kses_post( (string) $r['how_it_works_surface'] ) : '';
				$row['how_it_works_deeper']   = isset( $r['how_it_works_deeper'] ) ? wp_kses_post( (string) $r['how_it_works_deeper'] ) : '';
				$row['vendors_dont_tell_you'] = self::encode_list( isset( $r['vendors_dont_tell_you'] ) ? $r['vendors_dont_tell_you'] : array() );
			}

			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Normalise a list-ish value (array / newline string / pipe string) to a
	 * JSON array string, slugging each entry when $slug_each is true.
	 *
	 * @since 0.3.0
	 * @param mixed $value     Raw.
	 * @param bool  $slug_each Whether to sanitize_title each entry.
	 * @return string JSON array, empty string when nothing.
	 */
	protected static function encode_list( $value, $slug_each = false ) {
		if ( is_string( $value ) ) {
			$parts = preg_split( '/[\n|]+/', $value );
		} elseif ( is_array( $value ) ) {
			$parts = $value;
		} else {
			return '';
		}
		$clean = array();
		foreach ( $parts as $p ) {
			$p = trim( (string) $p );
			if ( '' === $p ) {
				continue;
			}
			$clean[] = $slug_each ? sanitize_title( $p ) : sanitize_text_field( $p );
		}
		if ( empty( $clean ) ) {
			return '';
		}
		return wp_json_encode( array_values( array_unique( $clean ) ) );
	}
}
