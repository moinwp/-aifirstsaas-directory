<?php
/**
 * Main plugin orchestrator.
 *
 * @package AIFirst
 * @since   0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Plugin.
 *
 * @since 0.1.0
 */
class AIFirst_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since 0.1.0
	 * @var AIFirst_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Get or create the singleton instance.
	 *
	 * @since 0.1.0
	 * @return AIFirst_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor is private; use instance().
	 *
	 * @since 0.1.0
	 */
	protected function __construct() {}

	/**
	 * Wire up hooks.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function run() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_activation_notice' ) );

		// Session 2: REST + admin.
		( new AIFirst_REST() )->register();

		if ( is_admin() ) {
			( new AIFirst_Admin() )->register();
		}

		// Session 3: DB version upgrader.
		( new AIFirst_Upgrader() )->register();

		// Session 4: public frontend + public form handlers.
		( new AIFirst_Public() )->register();
		( new AIFirst_Submissions() )->register();
		( new AIFirst_Reports() )->register();

		// Session 5: comparison CPT, admin inboxes, subscribers, blog.
		( new AIFirst_Comparison() )->register();
		( new AIFirst_Subscribers() )->register();
		( new AIFirst_Blog() )->register();
		if ( is_admin() ) {
			( new AIFirst_Reports_Admin() )->register();
			( new AIFirst_Submissions_Admin() )->register();
		}
	}

	/**
	 * Load the plugin's translations.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'aifirstsaas',
			false,
			dirname( AIFIRST_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register the Tool CPT.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_post_types() {
		$post_type = new AIFirst_Post_Types();
		$post_type->register();
	}

	/**
	 * Register the Category and AI Feature taxonomies.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function register_taxonomies() {
		$taxonomies = new AIFirst_Taxonomies();
		$taxonomies->register();
	}

	/**
	 * Show a one-shot activation notice.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function maybe_show_activation_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! get_option( 'aifirst_show_activation_notice' ) ) {
			return;
		}

		delete_option( 'aifirst_show_activation_notice' );

		$message = __(
			'AI First SaaS Directory activated. Run `wp db query "SHOW TABLES LIKE \'wp_aifirst_%\'"` to verify tables.',
			'aifirstsaas'
		);

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( $message )
		);
	}
}
