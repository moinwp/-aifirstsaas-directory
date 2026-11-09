<?php
/**
 * Comparison CPT + admin metabox + save handler.
 *
 * Registers `aifirst_comparison` so editorial can hand-write a full
 * "X vs Y" page that layers over the runtime /compare/ route.
 *
 * @package AIFirst
 * @since   0.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Comparison.
 *
 * @since 0.5.0
 */
class AIFirst_Comparison {

	const POST_TYPE   = 'aifirst_comparison';
	const META_TOOLS  = '_aifirst_vs_tool_ids';
	const META_V_BUDG = '_aifirst_vs_verdict_budget';
	const META_V_ENT  = '_aifirst_vs_verdict_enterprise';
	const META_V_A    = '_aifirst_vs_verdict_use_case_a';
	const META_V_B    = '_aifirst_vs_verdict_use_case_b';
	const NONCE       = 'aifirst_comparison_save';

	/**
	 * Hook registration.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
	}

	/**
	 * Register the aifirst_comparison CPT.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Comparisons', 'post type general name', 'aifirstsaas' ),
			'singular_name'      => _x( 'Comparison', 'post type singular name', 'aifirstsaas' ),
			'menu_name'          => __( 'Comparisons', 'aifirstsaas' ),
			'add_new'            => __( 'Add New', 'aifirstsaas' ),
			'add_new_item'       => __( 'Add New Comparison', 'aifirstsaas' ),
			'edit_item'          => __( 'Edit Comparison', 'aifirstsaas' ),
			'new_item'           => __( 'New Comparison', 'aifirstsaas' ),
			'view_item'          => __( 'View Comparison', 'aifirstsaas' ),
			'search_items'       => __( 'Search Comparisons', 'aifirstsaas' ),
			'not_found'          => __( 'No comparisons found.', 'aifirstsaas' ),
			'not_found_in_trash' => __( 'No comparisons in Trash.', 'aifirstsaas' ),
			'all_items'          => __( 'All Comparisons', 'aifirstsaas' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => $labels,
				'public'       => true,
				'show_in_rest' => true,
				'show_in_menu' => 'edit.php?post_type=aifirst_tool',
				'has_archive'  => false,
				'menu_icon'    => 'dashicons-columns',
				'rewrite'      => array(
					'slug'       => 'vs',
					'with_front' => false,
				),
				'supports'     => array( 'title', 'editor', 'revisions', 'excerpt' ),
			)
		);
	}

	/**
	 * Register the admin metaboxes.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function add_metaboxes() {
		add_meta_box(
			'aifirst-comparison-tools',
			__( 'Tools in this comparison', 'aifirstsaas' ),
			array( $this, 'render_tools_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'aifirst-comparison-verdicts',
			__( 'Verdicts', 'aifirstsaas' ),
			array( $this, 'render_verdicts_box' ),
			self::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Tools metabox renderer.
	 *
	 * @since 0.5.0
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_tools_box( $post ) {
		wp_nonce_field( self::NONCE, 'aifirst_comparison_nonce' );
		$ids = (array) self::get_tool_ids( $post->ID );
		for ( $i = 0; $i < 3; $i++ ) {
			$val = isset( $ids[ $i ] ) ? (string) $ids[ $i ] : '';
			printf(
				'<p><label><strong>%1$s %2$d</strong><br/><input type="text" name="aifirst_vs_tools[]" value="%3$s" class="regular-text" placeholder="e.g. jasper" /></label></p>',
				esc_html__( 'Tool slug', 'aifirstsaas' ),
				(int) ( $i + 1 ),
				esc_attr( $val )
			);
		}
		echo '<p class="description">' . esc_html__( 'Enter 2 or 3 tool slugs (tool_id). Order is cosmetic — matches are order-independent at render time.', 'aifirstsaas' ) . '</p>';
	}

	/**
	 * Verdicts metabox renderer.
	 *
	 * @since 0.5.0
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_verdicts_box( $post ) {
		$budget = get_post_meta( $post->ID, self::META_V_BUDG, true );
		$ent    = get_post_meta( $post->ID, self::META_V_ENT, true );
		$uc_a   = get_post_meta( $post->ID, self::META_V_A, true );
		$uc_b   = get_post_meta( $post->ID, self::META_V_B, true );
		?>
		<p><label><strong><?php esc_html_e( 'For AI on a budget', 'aifirstsaas' ); ?></strong><br/>
			<input type="text" name="aifirst_vs_v_budget" value="<?php echo esc_attr( (string) $budget ); ?>" class="large-text" />
		</label></p>
		<p><label><strong><?php esc_html_e( 'For enterprise', 'aifirstsaas' ); ?></strong><br/>
			<input type="text" name="aifirst_vs_v_ent" value="<?php echo esc_attr( (string) $ent ); ?>" class="large-text" />
		</label></p>
		<p><label><strong><?php esc_html_e( 'Use-case verdict (leans tool A)', 'aifirstsaas' ); ?></strong><br/>
			<input type="text" name="aifirst_vs_v_a" value="<?php echo esc_attr( (string) $uc_a ); ?>" class="large-text" />
		</label></p>
		<p><label><strong><?php esc_html_e( 'Use-case verdict (leans tool B)', 'aifirstsaas' ); ?></strong><br/>
			<input type="text" name="aifirst_vs_v_b" value="<?php echo esc_attr( (string) $uc_b ); ?>" class="large-text" />
		</label></p>
		<?php
	}

	/**
	 * Save handler.
	 *
	 * @since 0.5.0
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public function save_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['aifirst_comparison_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['aifirst_comparison_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Tool IDs: sanitize, drop empties, dedupe, validate against
		// wp_aifirst_tool_meta so we never store bogus slugs.
		$raw_ids = isset( $_POST['aifirst_vs_tools'] ) && is_array( $_POST['aifirst_vs_tools'] )
			? wp_unslash( $_POST['aifirst_vs_tools'] )
			: array();

		$clean = array();
		foreach ( $raw_ids as $slug ) {
			$slug = sanitize_title( (string) $slug );
			if ( '' === $slug || in_array( $slug, $clean, true ) ) {
				continue;
			}
			if ( self::tool_exists( $slug ) ) {
				$clean[] = $slug;
			}
			if ( count( $clean ) >= 3 ) {
				break;
			}
		}
		update_post_meta( $post_id, self::META_TOOLS, wp_json_encode( $clean ) );

		update_post_meta(
			$post_id,
			self::META_V_BUDG,
			isset( $_POST['aifirst_vs_v_budget'] ) ? sanitize_text_field( wp_unslash( $_POST['aifirst_vs_v_budget'] ) ) : ''
		);
		update_post_meta(
			$post_id,
			self::META_V_ENT,
			isset( $_POST['aifirst_vs_v_ent'] ) ? sanitize_text_field( wp_unslash( $_POST['aifirst_vs_v_ent'] ) ) : ''
		);
		update_post_meta(
			$post_id,
			self::META_V_A,
			isset( $_POST['aifirst_vs_v_a'] ) ? sanitize_text_field( wp_unslash( $_POST['aifirst_vs_v_a'] ) ) : ''
		);
		update_post_meta(
			$post_id,
			self::META_V_B,
			isset( $_POST['aifirst_vs_v_b'] ) ? sanitize_text_field( wp_unslash( $_POST['aifirst_vs_v_b'] ) ) : ''
		);
	}

	/**
	 * Decode the JSON tool_ids meta into an array of slugs.
	 *
	 * @since 0.5.0
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function get_tool_ids( $post_id ) {
		$raw = get_post_meta( (int) $post_id, self::META_TOOLS, true );
		if ( empty( $raw ) ) {
			return array();
		}
		$arr = json_decode( (string) $raw, true );
		if ( ! is_array( $arr ) ) {
			return array();
		}
		return array_values(
			array_filter(
				array_map(
					static function ( $v ) { return sanitize_title( (string) $v ); },
					$arr
				),
				static function ( $v ) { return '' !== $v; }
			)
		);
	}

	/**
	 * Return the four verdict strings for a comparison.
	 *
	 * @since 0.5.0
	 * @param int $post_id Post ID.
	 * @return array{budget:string,enterprise:string,use_case_a:string,use_case_b:string}
	 */
	public static function get_verdicts( $post_id ) {
		return array(
			'budget'     => (string) get_post_meta( (int) $post_id, self::META_V_BUDG, true ),
			'enterprise' => (string) get_post_meta( (int) $post_id, self::META_V_ENT, true ),
			'use_case_a' => (string) get_post_meta( (int) $post_id, self::META_V_A, true ),
			'use_case_b' => (string) get_post_meta( (int) $post_id, self::META_V_B, true ),
		);
	}

	/**
	 * Does a tool_id exist in wp_aifirst_tool_meta?
	 *
	 * @since 0.5.0
	 * @param string $slug tool_id.
	 * @return bool
	 */
	public static function tool_exists( $slug ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_tool_meta';
		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT 1 FROM {$table} WHERE tool_id = %s LIMIT 1", (string) $slug )
		);
	}
}
