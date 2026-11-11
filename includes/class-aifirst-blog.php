<?php
/**
 * Blog integration — attach related AI tools to core WP posts.
 *
 * Adds a metabox on the standard post edit screen that stores tool
 * slugs in post meta `_aifirst_related_tools` (JSON array), then
 * appends an AI First "Related AI tools" block at the bottom of the
 * post via `the_content`. The block ships the plugin's own assets,
 * so it works on any theme.
 *
 * @package AIFirst
 * @since   0.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Blog.
 *
 * @since 0.5.0
 */
class AIFirst_Blog {

	const META_KEY = '_aifirst_related_tools';
	const NONCE    = 'aifirst_blog_related';

	/**
	 * Register hooks.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'ensure_post_type_support' ), 20 );
		add_action( 'add_meta_boxes_post', array( $this, 'add_metabox' ) );
		add_action( 'save_post_post', array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'maybe_append_related' ), 50 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_public_assets' ) );
	}

	/**
	 * Make sure core posts declare custom-fields support — some Gutenberg
	 * skins hide third-party metaboxes that sit beside a post type with
	 * no custom-fields declaration. Core adds it by default; defensive.
	 *
	 * @since 0.5.1
	 * @return void
	 */
	public function ensure_post_type_support() {
		if ( ! post_type_supports( 'post', 'custom-fields' ) ) {
			add_post_type_support( 'post', 'custom-fields' );
		}
	}

	/**
	 * Add the metabox to the core post editor.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function add_metabox() {
		// Gutenberg renders 'side' metaboxes in a collapsed drawer that
		// sometimes won't expand; 'normal' puts us below the main editor
		// as an always-expanded section.
		// @since 0.5.1
		add_meta_box(
			'aifirst-related-tools',
			__( 'Related AI tools', 'aifirstsaas' ),
			array( $this, 'render_metabox' ),
			'post',
			'normal',
			'default'
		);
	}

	/**
	 * Metabox UI — simple textarea of comma/newline-separated tool slugs.
	 *
	 * @since 0.5.0
	 * @param WP_Post $post Post.
	 * @return void
	 */
	public function render_metabox( $post ) {
		wp_nonce_field( self::NONCE, 'aifirst_blog_related_nonce' );
		$slugs = self::get_related( $post->ID );
		$value = implode( ', ', $slugs );
		?>
		<p>
			<label for="aifirst-related-input" style="font-weight:600;">
				<?php esc_html_e( 'Tool slugs', 'aifirstsaas' ); ?>
			</label><br />
			<textarea id="aifirst-related-input" name="aifirst_related_tools" rows="4" style="width:100%;" placeholder="jasper, grammarly"><?php echo esc_textarea( $value ); ?></textarea>
		</p>
		<p class="description"><?php esc_html_e( 'Comma- or newline-separated tool_id slugs. Slugs that don\'t match a published tool are ignored at render time.', 'aifirstsaas' ); ?></p>
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
		if ( ! isset( $_POST['aifirst_blog_related_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['aifirst_blog_related_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw   = isset( $_POST['aifirst_related_tools'] ) ? (string) wp_unslash( $_POST['aifirst_related_tools'] ) : '';
		$parts = preg_split( '/[\n,]+/', $raw );
		$clean = array();
		foreach ( (array) $parts as $p ) {
			$slug = sanitize_title( trim( (string) $p ) );
			if ( '' === $slug || in_array( $slug, $clean, true ) ) {
				continue;
			}
			$clean[] = $slug;
			if ( count( $clean ) >= 10 ) {
				break;
			}
		}

		if ( empty( $clean ) ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, wp_json_encode( $clean ) );
		}
	}

	/**
	 * Read the related tools slug array.
	 *
	 * @since 0.5.0
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function get_related( $post_id ) {
		$raw = get_post_meta( (int) $post_id, self::META_KEY, true );
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
	 * Append the related-tools block to the main content on single posts.
	 *
	 * @since 0.5.0
	 * @param string $content Post content.
	 * @return string
	 */
	public function maybe_append_related( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$slugs = self::get_related( get_the_ID() );
		if ( empty( $slugs ) ) {
			return $content;
		}
		ob_start();
		AIFirst_Components::related_tools_block( $slugs );
		return $content . ob_get_clean();
	}

	/**
	 * Enqueue the plugin's public CSS on single posts that carry related
	 * tools, so the block renders correctly on any theme.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function maybe_enqueue_public_assets() {
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		$slugs = self::get_related( get_queried_object_id() );
		if ( empty( $slugs ) ) {
			return;
		}
		wp_enqueue_style(
			'aifirst-public',
			AIFIRST_PLUGIN_URL . 'assets/css/public.css',
			array(),
			AIFIRST_VERSION
		);
	}
}
