<?php
/**
 * Submissions service — public form on /submit/ writes to
 * {prefix}aifirst_submissions.
 *
 * Public (unauthenticated) endpoint, protected by: WP nonce bound to the
 * form, a honeypot field, 1 submission / 60s / IP throttle, and strict input
 * sanitisation. Nothing goes into any WP post, term, or option — just the
 * submissions table for admin review.
 *
 * @package AIFirst
 * @since   0.4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Submissions.
 *
 * @since 0.4.0
 */
class AIFirst_Submissions {

	const ACTION = 'aifirst_submit_listing';
	const NONCE  = 'aifirst_submit';
	const RATE_TRANSIENT_PREFIX = 'aifirst_sub_rl_';

	/**
	 * Wire handlers.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Process a submission post and redirect back to /submit/ with a flash.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function handle() {
		$nonce = isset( $_POST['aifirst_submit_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['aifirst_submit_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			$this->redirect_with( 'error', __( 'Request expired. Reload the page and try again.', 'aifirstsaas' ) );
		}

		// Honeypot — if "hp" is filled, it's a bot.
		$hp = isset( $_POST['hp'] ) ? trim( (string) wp_unslash( $_POST['hp'] ) ) : '';
		if ( '' !== $hp ) {
			$this->redirect_with( 'ok', __( 'Thanks — submission received.', 'aifirstsaas' ) );
		}

		$ip = $this->client_ip();
		if ( $this->rate_limited( $ip ) ) {
			$this->redirect_with( 'error', __( 'Please wait a moment before submitting again.', 'aifirstsaas' ) );
		}

		$kind    = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'new';
		$kind    = in_array( $kind, array( 'new', 'update' ), true ) ? $kind : 'new';
		$tool_id = isset( $_POST['existing_tool_id'] ) ? sanitize_title( wp_unslash( $_POST['existing_tool_id'] ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( '' === $email || ! is_email( $email ) ) {
			$this->redirect_with( 'error', __( 'A valid email is required.', 'aifirstsaas' ) );
		}

		// @since 0.8.0 — richer field set + a structured `payload`.
		// `body` remains the slim Session 4 shape for backwards-compat
		// with existing admin list views; `payload` carries everything.
		$allowed_pricing      = array( 'freemium', 'subscription', 'one-time', 'free', 'usage-based' );
		$allowed_relationship = array( 'builder', 'employee', 'user', 'other' );

		$pricing_model = isset( $_POST['pricing_model'] ) ? sanitize_key( wp_unslash( $_POST['pricing_model'] ) ) : '';
		if ( ! in_array( $pricing_model, $allowed_pricing, true ) ) {
			$pricing_model = '';
		}
		$relationship = isset( $_POST['relationship'] ) ? sanitize_key( wp_unslash( $_POST['relationship'] ) ) : '';
		if ( ! in_array( $relationship, $allowed_relationship, true ) ) {
			$relationship = '';
		}

		$has_free_plan   = ! empty( $_POST['has_free_plan'] ) ? 1 : 0;
		$ai_on_free_plan = ! empty( $_POST['ai_on_free_plan'] ) ? 1 : 0;
		if ( ! $has_free_plan ) {
			$ai_on_free_plan = 0;
		}

		$starting_price = isset( $_POST['starting_price_usd'] ) ? (string) wp_unslash( $_POST['starting_price_usd'] ) : '';
		$starting_price = '' === $starting_price ? null : max( 0, min( 99999999.99, (float) $starting_price ) );

		$excerpt       = isset( $_POST['excerpt'] ) ? substr( sanitize_textarea_field( wp_unslash( $_POST['excerpt'] ) ), 0, 240 ) : '';
		$why_ai_first  = isset( $_POST['why_ai_first'] ) ? substr( sanitize_textarea_field( wp_unslash( $_POST['why_ai_first'] ) ), 0, 1500 ) : '';

		$payload = array(
			'tool_name'          => isset( $_POST['tool_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tool_name'] ) ) : '',
			'website'            => isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '',
			'pricing_url'        => isset( $_POST['pricing_url'] ) ? esc_url_raw( wp_unslash( $_POST['pricing_url'] ) ) : '',
			'logo_url'           => isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '',
			'company_name'       => isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '',
			'category'           => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'subcategory'        => isset( $_POST['subcategory'] ) ? sanitize_text_field( wp_unslash( $_POST['subcategory'] ) ) : '',
			'excerpt'            => $excerpt,
			'ai_features'        => isset( $_POST['ai_features'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_features'] ) ) : '',
			'pricing_model'      => $pricing_model,
			'has_free_plan'      => $has_free_plan,
			'ai_on_free_plan'    => $ai_on_free_plan,
			'starting_price_usd' => $starting_price,
			'why_ai_first'       => $why_ai_first,
			'submitter_name'     => isset( $_POST['submitter_name'] ) ? sanitize_text_field( wp_unslash( $_POST['submitter_name'] ) ) : '',
			'relationship'       => $relationship,
			'source_url'         => isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '',
			'notes'              => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
		);

		// Backwards-compatible slim body for the admin list column renderer.
		$body = array(
			'tool_name'    => $payload['tool_name'],
			'website'      => $payload['website'],
			'category'     => $payload['category'],
			'ai_features'  => $payload['ai_features'],
			'source_url'   => $payload['source_url'],
			'notes'        => $payload['notes'],
		);

		if ( 'new' === $kind ) {
			if ( '' === $payload['tool_name'] ) {
				$this->redirect_with( 'error', __( 'Tool name is required for a new listing.', 'aifirstsaas' ) );
			}
			if ( '' === $payload['website'] ) {
				$this->redirect_with( 'error', __( 'Website URL is required.', 'aifirstsaas' ) );
			}
			if ( '' === $payload['category'] ) {
				$this->redirect_with( 'error', __( 'Pick a category.', 'aifirstsaas' ) );
			}
			if ( '' === $excerpt ) {
				$this->redirect_with( 'error', __( 'A one-sentence excerpt is required.', 'aifirstsaas' ) );
			}
			if ( '' === $payload['ai_features'] ) {
				$this->redirect_with( 'error', __( 'List at least one AI feature.', 'aifirstsaas' ) );
			}
			if ( '' === $why_ai_first ) {
				$this->redirect_with( 'error', __( 'Tell us why this tool is AI-first.', 'aifirstsaas' ) );
			}
		}
		if ( 'update' === $kind && '' === $tool_id ) {
			$this->redirect_with( 'error', __( 'Choose the tool you want to update.', 'aifirstsaas' ) );
		}

		global $wpdb;
		$ok = $wpdb->insert(
			$wpdb->prefix . 'aifirst_submissions',
			array(
				'kind'       => $kind,
				'tool_id'    => $tool_id,
				'body'       => wp_json_encode( $body ),
				'payload'    => wp_json_encode( $payload ),
				'email'      => $email,
				'status'     => 'new',
				'source_ip'  => $ip,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			$this->redirect_with( 'error', __( 'Could not save the submission. Please try again.', 'aifirstsaas' ) );
		}

		$this->mark_rate( $ip );
		// @since 0.8.0 — explicit expectation-setting copy.
		$this->redirect_with(
			'ok',
			__( "Thanks — we've received your submission. A reviewer will look at it within a few days and either publish your tool or email you back if we need more information.", 'aifirstsaas' )
		);
	}

	/**
	 * Flash + bounce.
	 *
	 * @since 0.4.0
	 * @param string $kind "ok" or "error".
	 * @param string $msg  Message.
	 * @return void
	 */
	protected function redirect_with( $kind, $msg ) {
		$referer = wp_get_referer();
		$back    = $referer ? $referer : home_url( '/submit/' );
		$back    = add_query_arg(
			array(
				'aifirst_submit' => $kind,
				'aifirst_msg'    => rawurlencode( wp_strip_all_tags( $msg ) ),
			),
			$back
		);
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Best-effort client IP.
	 *
	 * @since 0.4.0
	 * @return string
	 */
	protected function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$ip = filter_var( $ip, FILTER_VALIDATE_IP );
		return $ip ? $ip : '0.0.0.0';
	}

	/**
	 * Rate limit: 1 submit per 60s per IP.
	 *
	 * @since 0.4.0
	 * @param string $ip IP.
	 * @return bool
	 */
	protected function rate_limited( $ip ) {
		return (bool) get_transient( self::RATE_TRANSIENT_PREFIX . md5( $ip ) );
	}

	/**
	 * Record the rate timestamp.
	 *
	 * @since 0.4.0
	 * @param string $ip IP.
	 * @return void
	 */
	protected function mark_rate( $ip ) {
		set_transient( self::RATE_TRANSIENT_PREFIX . md5( $ip ), 1, 60 );
	}
}
