<?php
/**
 * Admin coordinator — adds submenu pages and enqueues screen-scoped assets.
 *
 * @package AIFirst
 * @since   0.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Admin.
 *
 * @since 0.2.0
 */
class AIFirst_Admin {

	const QUICK_ADD_SLUG = 'aifirst-quick-add';
	const SETTINGS_SLUG  = 'aifirst-settings';

	const NONCE_AJAX     = 'aifirst_ajax';
	const CAP            = 'manage_options';

	/**
	 * Quick Add screen hook suffix (set after add_submenu_page returns).
	 *
	 * @since 0.2.0
	 * @var string
	 */
	protected $quick_add_hook = '';

	/**
	 * Settings screen hook suffix.
	 *
	 * @since 0.2.0
	 * @var string
	 */
	protected $settings_hook = '';

	/**
	 * Wire up hooks.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );

		$ajax = new AIFirst_Ajax();
		$ajax->register();
	}

	/**
	 * Add Quick Add and Settings submenu pages under the Tools CPT.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function add_menus() {
		$parent = 'edit.php?post_type=aifirst_tool';

		$quick = new AIFirst_Quick_Add();

		$this->quick_add_hook = (string) add_submenu_page(
			$parent,
			__( 'Quick Add — AI First', 'aifirstsaas' ),
			__( 'Quick Add', 'aifirstsaas' ),
			self::CAP,
			self::QUICK_ADD_SLUG,
			array( $quick, 'render' )
		);

		$settings = new AIFirst_Settings();

		$this->settings_hook = (string) add_submenu_page(
			$parent,
			__( 'AI First Settings', 'aifirstsaas' ),
			__( 'Settings', 'aifirstsaas' ),
			self::CAP,
			self::SETTINGS_SLUG,
			array( $settings, 'render' )
		);
	}

	/**
	 * Register the aifirst_settings option group.
	 *
	 * @since 0.2.0
	 * @return void
	 */
	public function register_settings() {
		$settings = new AIFirst_Settings();
		$settings->register();
	}

	/**
	 * Enqueue admin assets only on our screens.
	 *
	 * @since 0.2.0
	 * @param string $hook_suffix Current screen hook.
	 * @return void
	 */
	public function maybe_enqueue_assets( $hook_suffix ) {
		if ( $hook_suffix !== $this->quick_add_hook && $hook_suffix !== $this->settings_hook ) {
			return;
		}

		wp_enqueue_style(
			'aifirst-admin',
			AIFIRST_PLUGIN_URL . 'assets/css/admin-quick-add.css',
			array(),
			AIFIRST_VERSION
		);

		wp_enqueue_script(
			'aifirst-admin',
			AIFIRST_PLUGIN_URL . 'assets/js/admin-quick-add.js',
			array( 'jquery' ),
			AIFIRST_VERSION,
			true
		);

		wp_localize_script(
			'aifirst-admin',
			'AIFirstAdmin',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( self::NONCE_AJAX ),
				'restUrl'      => esc_url_raw( rest_url( AIFirst_REST::NAMESPACE_V1 . '/tool' ) ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'skeleton'     => AIFirst_Record::skeleton(),
				'editBaseUrl'  => esc_url_raw( admin_url( 'post.php' ) ),
				'listUrl'      => esc_url_raw( admin_url( 'edit.php?post_type=aifirst_tool' ) ),
				'i18n'         => array(
					'parsing'    => __( 'Parsing…', 'aifirstsaas' ),
					'parseFail'  => __( 'Parse failed:', 'aifirstsaas' ),
					'saving'     => __( 'Saving…', 'aifirstsaas' ),
					'saveFail'   => __( 'Save failed:', 'aifirstsaas' ),
					'noKey'      => __( 'No AI key configured. You can still fill the form by hand.', 'aifirstsaas' ),
					'addRow'     => __( 'Add row', 'aifirstsaas' ),
					'removeRow'  => __( 'Remove', 'aifirstsaas' ),
					'importing'  => __( 'Importing…', 'aifirstsaas' ),
				),
			)
		);
	}
}
