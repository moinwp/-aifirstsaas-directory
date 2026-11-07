<?php
/**
 * AJAX handlers for Quick Add + Settings.
 *
 * Every handler checks the aifirst_ajax nonce and manage_options capability.
 *
 * @package AIFirst
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Ajax.
 *
 * @since 0.2.0
 */
class AIFirst_Ajax {

	/**
	 * Hook handlers.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_aifirst_parse_text', array( $this, 'handle_parse_text' ) );
		add_action( 'wp_ajax_aifirst_save_record', array( $this, 'handle_save_record' ) );
		add_action( 'wp_ajax_aifirst_test_connection', array( $this, 'handle_test_connection' ) );
	}

	/**
	 * Verify nonce + cap, or die with 403.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	protected function guard() {
		if ( ! current_user_can( AIFirst_Admin::CAP ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'aifirstsaas' ) ),
				403
			);
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, AIFirst_Admin::NONCE_AJAX ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Bad nonce.', 'aifirstsaas' ) ),
				403
			);
		}
	}

	/**
	 * Parse pasted text with AI → canonical record.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function handle_parse_text() {
		$this->guard();

		$raw    = isset( $_POST['raw_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['raw_text'] ) ) : '';
		$site   = isset( $_POST['website_url'] ) ? esc_url_raw( wp_unslash( $_POST['website_url'] ) ) : '';

		if ( '' === trim( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Paste some text first.', 'aifirstsaas' ) ), 400 );
		}

		$client = AIFirst_AI_Client::from_settings();
		$parsed = $client->parse_tool_text( $raw, $site );

		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error(
				array(
					'message' => $parsed->get_error_message(),
					'data'    => $parsed->get_error_data(),
				),
				502
			);
		}

		$validated = AIFirst_Record::validate( $parsed );

		wp_send_json_success(
			array(
				'record'  => null !== $validated['record'] ? $validated['record'] : $parsed,
				'errors'  => $validated['errors'],
				'raw_ai'  => $parsed,
				'warning' => null === $validated['record'] ? __( 'AI output had validation issues — review the form carefully.', 'aifirstsaas' ) : '',
			)
		);
	}

	/**
	 * Save the review form as a canonical record.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function handle_save_record() {
		$this->guard();

		$json = isset( $_POST['record'] ) ? wp_unslash( $_POST['record'] ) : '';
		if ( ! is_string( $json ) || '' === $json ) {
			wp_send_json_error( array( 'message' => __( 'Missing record payload.', 'aifirstsaas' ) ), 400 );
		}

		$raw = json_decode( $json, true );
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'Record payload is not valid JSON.', 'aifirstsaas' ) ), 400 );
		}

		$validated = AIFirst_Record::validate( $raw );
		if ( null === $validated['record'] ) {
			wp_send_json_error(
				array(
					'message' => __( 'Validation failed.', 'aifirstsaas' ),
					'errors'  => $validated['errors'],
				),
				400
			);
		}

		$intake = new AIFirst_Intake();
		$result = $intake->save_record( $validated['record'] );

		if ( 0 === $result['post_id'] ) {
			wp_send_json_error(
				array(
					'message' => __( 'Save failed.', 'aifirstsaas' ),
					'result'  => $result,
				),
				500
			);
		}

		$result['edit_url'] = get_edit_post_link( $result['post_id'], 'raw' );
		wp_send_json_success( $result );
	}

	/**
	 * Settings page "Test connection" button.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function handle_test_connection() {
		$this->guard();
		$client = AIFirst_AI_Client::from_settings();
		$res    = $client->test_connection();
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ), 502 );
		}
		wp_send_json_success(
			array(
				'message'  => __( 'Connection OK.', 'aifirstsaas' ),
				'provider' => $client->get_provider(),
				'model'    => $client->get_model(),
			)
		);
	}
}
