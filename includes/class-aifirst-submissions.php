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

		$body = array(
			'tool_name'    => isset( $_POST['tool_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tool_name'] ) ) : '',
			'website'      => isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '',
			'category'     => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'ai_features'  => isset( $_POST['ai_features'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ai_features'] ) ) : '',
			'source_url'   => isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '',
			'notes'        => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
		);

		if ( 'new' === $kind && '' === $body['tool_name'] ) {
			$this->redirect_with( 'error', __( 'Tool name is required for a new listing.', 'aifirstsaas' ) );
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
				'email'      => $email,
				'status'     => 'new',
				'source_ip'  => $ip,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			$this->redirect_with( 'error', __( 'Could not save the submission. Please try again.', 'aifirstsaas' ) );
		}

		$this->mark_rate( $ip );
		$this->redirect_with( 'ok', __( 'Thanks — submission received. Our team verifies new listings within 3-5 business days.', 'aifirstsaas' ) );
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
