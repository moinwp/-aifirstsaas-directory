<?php
/**
 * Canonical tool record shape + validator/sanitizer.
 *
 * Every intake path (admin UI, CSV, REST, AI client) produces or consumes a
 * record in this exact shape. Keep the canonical definition in one place.
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
	);

	/**
	 * Return an empty record skeleton — handy for the admin review form.
	 *
	 * @since 0.2.0
	 * @return array
	 */
	public static function skeleton() {
		return array(
			'tool'          => array(
				'tool_id'     => '',
				'title'       => '',
				'content'     => '',
				'excerpt'     => '',
				'categories'  => array(),
				'ai_features' => array(),
				'status'      => 'draft',
			),
			'meta'          => array(
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
				'notable_customers'   => '',
				'best_for'            => '',
				'expert_take'         => '',
				'meta_title'          => '',
				'meta_description'    => '',
				'h1'                  => '',
			),
			'plans'         => array(),
			'faq'           => array(),
			'radar'         => array(),
			'cannot_do'     => array(),
			'relationships' => array(),
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
		$m['company_size_smb']    = self::sanitize_bool( isset( $meta_in['company_size_smb'] ) ? $meta_in['company_size_smb'] : false );
		$m['company_size_mid']    = self::sanitize_bool( isset( $meta_in['company_size_mid'] ) ? $meta_in['company_size_mid'] : false );
		$m['company_size_ent']    = self::sanitize_bool( isset( $meta_in['company_size_ent'] ) ? $meta_in['company_size_ent'] : false );
		$m['notable_customers']   = isset( $meta_in['notable_customers'] ) ? sanitize_textarea_field( (string) $meta_in['notable_customers'] ) : '';
		$m['best_for']            = isset( $meta_in['best_for'] ) ? sanitize_textarea_field( (string) $meta_in['best_for'] ) : '';
		$m['expert_take']         = isset( $meta_in['expert_take'] ) ? sanitize_textarea_field( (string) $meta_in['expert_take'] ) : '';
		$m['meta_title']          = isset( $meta_in['meta_title'] ) ? sanitize_text_field( (string) $meta_in['meta_title'] ) : '';
		$m['meta_description']    = isset( $meta_in['meta_description'] ) ? sanitize_text_field( (string) $meta_in['meta_description'] ) : '';
		$m['h1']                  = isset( $meta_in['h1'] ) ? sanitize_text_field( (string) $meta_in['h1'] ) : '';

		// Children.
		$out['plans']         = self::sanitize_plans( isset( $raw['plans'] ) ? $raw['plans'] : array(), $errors );
		$out['faq']           = self::sanitize_faq( isset( $raw['faq'] ) ? $raw['faq'] : array(), $errors );
		$out['radar']         = self::sanitize_radar( isset( $raw['radar'] ) ? $raw['radar'] : array(), $errors );
		$out['cannot_do']     = self::sanitize_cannot_do( isset( $raw['cannot_do'] ) ? $raw['cannot_do'] : array() );
		$out['relationships'] = self::sanitize_relationships( isset( $raw['relationships'] ) ? $raw['relationships'] : array(), $errors );

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
		unset( $errors ); // enum-free here.
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
				'change_type'    => self::sanitize_enum( isset( $r['change_type'] ) ? (string) $r['change_type'] : '', 'change_type', $errors, "radar[$i].change_type" ),
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
	 * Sanitize cannot_do[] array.
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
}
