<?php
/**
 * REST endpoints under the aifirst/v1 namespace.
 *
 * @package AIFirst
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_REST.
 *
 * @since 0.2.0
 */
class AIFirst_REST {

	const NAMESPACE_V1 = 'aifirst/v1';

	/**
	 * Hook routes.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register /tool.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/tool',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_post_tool' ),
					'permission_callback' => array( $this, 'permission_manage_options' ),
				),
			)
		);
	}

	/**
	 * Only admins can write tools through the API.
	 *
	 * @since 0.2.0
	 * @return bool
	 */
	public function permission_manage_options() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * POST /aifirst/v1/tool.
	 *
	 * @since 0.2.0
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_post_tool( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$validated = AIFirst_Record::validate( $params );
		if ( null === $validated['record'] ) {
			return new WP_REST_Response(
				array(
					'ok'     => false,
					'errors' => $validated['errors'],
				),
				400
			);
		}

		$intake = new AIFirst_Intake();
		$result = $intake->save_record( $validated['record'] );

		$status = 'created' === $result['created_or_updated'] ? 201 : ( 'updated' === $result['created_or_updated'] ? 200 : 500 );

		if ( ! empty( $result['errors'] ) && 0 === $result['post_id'] ) {
			$status = 500;
		}

		return new WP_REST_Response(
			array(
				'ok'     => 0 !== $result['post_id'],
				'result' => $result,
			),
			$status
		);
	}
}
