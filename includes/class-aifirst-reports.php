<?php
/**
 * Change-report service — modal on tool pages writes to
 * {prefix}aifirst_reports.
 *
 * Same security envelope as AIFirst_Submissions: WP nonce bound to the tool
 * being reported, honeypot, 1 report / 60s / IP throttle.
 *
 * @package AIFirst
 * @since   0.4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Reports.
 *
 * @since 0.4.0
 */
class AIFirst_Reports {

	const ACTION = 'aifirst_submit_report';
	const RATE_TRANSIENT_PREFIX = 'aifirst_rep_rl_';

	const ALLOWED_KINDS = array(
		'wrong_info',
		'pricing_changed',
		'feature_added',
		'feature_removed',
		'other',
	);

	/**
	 * Wire handlers.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );

		// AJAX path for the in-page modal — returns JSON, no redirect.
		// @since 0.4.1
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle_ajax' ) );
	}

	/**
	 * AJAX endpoint — validates, writes, replies JSON.
	 *
	 * Same validation envelope as handle() (nonce bound to the tool,
	 * honeypot, 60s/IP throttle), but talks JSON instead of redirecting.
	 *
	 * @since 0.4.1
	 * @return void
	 */
	public function handle_ajax() {
		$tool_id = isset( $_POST['tool_id'] ) ? sanitize_title( wp_unslash( $_POST['tool_id'] ) ) : '';
		$nonce   = isset( $_POST['aifirst_report_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['aifirst_report_nonce'] ) ) : '';

		if ( '' === $tool_id || ! wp_verify_nonce( $nonce, 'aifirst_report_' . $tool_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Request expired. Reload and try again.', 'aifirstsaas' ) ), 403 );
		}

		$hp = isset( $_POST['hp'] ) ? trim( (string) wp_unslash( $_POST['hp'] ) ) : '';
		if ( '' !== $hp ) {
			// Silently accept to not tip off bots.
			wp_send_json_success( array( 'message' => __( 'Thanks — your report was recorded.', 'aifirstsaas' ) ) );
		}

		$ip = $this->client_ip();
		if ( $this->rate_limited( $ip ) ) {
			wp_send_json_error( array( 'message' => __( 'Please wait a moment before reporting again.', 'aifirstsaas' ) ), 429 );
		}

		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'other';
		if ( ! in_array( $kind, self::ALLOWED_KINDS, true ) ) {
			$kind = 'other';
		}

		$body  = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( '' === $body ) {
			wp_send_json_error( array( 'message' => __( 'Tell us what needs changing.', 'aifirstsaas' ) ), 400 );
		}

		global $wpdb;
		$ok = $wpdb->insert(
			$wpdb->prefix . 'aifirst_reports',
			array(
				'tool_id'    => $tool_id,
				'kind'       => $kind,
				'body'       => $body,
				'email'      => $email,
				'status'     => 'new',
				'source_ip'  => $ip,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			wp_send_json_error( array( 'message' => __( 'Could not save the report. Please try again.', 'aifirstsaas' ) ), 500 );
		}

		$this->mark_rate( $ip );
		wp_send_json_success( array( 'message' => __( 'Thanks — your report was recorded.', 'aifirstsaas' ) ) );
	}

	/**
	 * Process a report post and redirect back to the tool page with a flash.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function handle() {
		$tool_id = isset( $_POST['tool_id'] ) ? sanitize_title( wp_unslash( $_POST['tool_id'] ) ) : '';
		$nonce   = isset( $_POST['aifirst_report_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['aifirst_report_nonce'] ) ) : '';

		if ( '' === $tool_id || ! wp_verify_nonce( $nonce, 'aifirst_report_' . $tool_id ) ) {
			$this->redirect_with( 'error', __( 'Request expired. Reload the page and try again.', 'aifirstsaas' ) );
		}

		$hp = isset( $_POST['hp'] ) ? trim( (string) wp_unslash( $_POST['hp'] ) ) : '';
		if ( '' !== $hp ) {
			$this->redirect_with( 'ok', __( 'Thanks — report received.', 'aifirstsaas' ) );
		}

		$ip = $this->client_ip();
		if ( $this->rate_limited( $ip ) ) {
			$this->redirect_with( 'error', __( 'Please wait a moment before reporting again.', 'aifirstsaas' ) );
		}

		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'other';
		if ( ! in_array( $kind, self::ALLOWED_KINDS, true ) ) {
			$kind = 'other';
		}

		$body  = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( '' === $body ) {
			$this->redirect_with( 'error', __( 'Tell us what needs changing.', 'aifirstsaas' ) );
		}

		global $wpdb;
		$ok = $wpdb->insert(
			$wpdb->prefix . 'aifirst_reports',
			array(
				'tool_id'    => $tool_id,
				'kind'       => $kind,
				'body'       => $body,
				'email'      => $email,
				'status'     => 'new',
				'source_ip'  => $ip,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			$this->redirect_with( 'error', __( 'Could not save the report. Please try again.', 'aifirstsaas' ) );
		}

		$this->mark_rate( $ip );
		$this->redirect_with( 'ok', __( 'Thanks — we\'ll review this shortly.', 'aifirstsaas' ) );
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
		$back    = $referer ? $referer : home_url( '/' );
		$back    = add_query_arg(
			array(
				'aifirst_report' => $kind,
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
	 * Rate limit: 1 report / 60s / IP.
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
