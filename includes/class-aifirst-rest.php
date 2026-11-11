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

		// Session 7: lazy-loaded home sections.
		// @since 0.7.0
		register_rest_route(
			self::NAMESPACE_V1,
			'/home-section/(?P<section>[a-z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_home_section' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'section' => array(
							'type' => 'string',
						),
					),
				),
			)
		);
	}

	/**
	 * Render a home section as HTML. Public, read-only, transient-cached.
	 *
	 * @since 0.7.0
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_home_section( WP_REST_Request $request ) {
		$section = sanitize_key( (string) $request->get_param( 'section' ) );
		$allowed = array( 'leaderboard', 'trending' );
		if ( ! in_array( $section, $allowed, true ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'error' => 'bad_section' ), 400 );
		}

		$cache_key = AIFirst_Cache::key( 'home_sec_' . $section );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return new WP_REST_Response( array( 'ok' => true, 'html' => $cached, 'cached' => true ), 200 );
		}

		ob_start();
		if ( 'leaderboard' === $section ) {
			$leaders = AIFirst_Query::leaderboard( array( 'limit' => 8 ) );
			if ( empty( $leaders ) ) {
				echo '<p class="aifirst-lead">' . esc_html__( 'No scored tools yet.', 'aifirstsaas' ) . '</p>';
			} else {
				echo '<div>';
				foreach ( $leaders as $i => $l ) {
					printf(
						'<a href="%1$s" class="aifirst-leader-row"><span class="aifirst-leader-row__rank">%2$d</span>',
						esc_url( get_permalink( (int) $l->post_id ) ),
						(int) ( $i + 1 )
					);
					AIFirst_Components::avatar( (int) $l->post_id, $l->initials, $l->avatar_color, 28 );
					echo '<span class="aifirst-leader-row__title">' . esc_html( $l->post_title ) . '</span>';
					AIFirst_Components::maturity_bars( (int) $l->ai_maturity_score );
					echo '<span class="aifirst-leader-row__score">' . esc_html( (int) $l->ai_maturity_score ) . '</span>';
					echo '</a>';
				}
				echo '</div>';
			}
		} elseif ( 'trending' === $section ) {
			AIFirst_Components::trending_comparisons( 6 );
		}
		$html = (string) ob_get_clean();

		set_transient( $cache_key, $html, 30 * MINUTE_IN_SECONDS );
		return new WP_REST_Response( array( 'ok' => true, 'html' => $html, 'cached' => false ), 200 );
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
