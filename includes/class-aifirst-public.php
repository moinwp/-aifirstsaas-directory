<?php
/**
 * Frontend dispatcher — rewrite rules, query vars, template_include,
 * conditional asset enqueue.
 *
 * Plugin templates live in templates/public/. Themes override by placing a
 * file under their own aifirst/ subfolder (e.g.
 * theme-name/aifirst/single-aifirst_tool.php).
 *
 * @package AIFirst
 * @since   0.4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Public.
 *
 * @since 0.4.0
 */
class AIFirst_Public {

	const QV_PAGE  = 'aifirst_page';
	const QV_CMP_A = 'aifirst_cmp_a';
	const QV_CMP_B = 'aifirst_cmp_b';
	const QV_CMP_C = 'aifirst_cmp_c';
	const QV_STACK = 'my_stack_tools';

	/**
	 * Register rewrites, query vars, template_include, assets.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'add_rewrites' ), 20 );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_filter( 'template_include', array( $this, 'template_include' ), 50 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );
		add_action( 'wp_footer', array( $this, 'render_stack_badge' ) );
		// @since 0.7.0 — FOUC-prevention + theme toggle.
		add_action( 'wp_head', array( $this, 'print_fouc_script' ), 1 );
		add_action( 'wp_footer', array( $this, 'render_theme_toggle' ) );
	}

	/**
	 * Tiny inline script in wp_head that reads localStorage before any
	 * stylesheet downloads and sets [data-theme] on <html> so the correct
	 * palette paints on first frame.
	 *
	 * @since 0.7.0
	 * @return void
	 */
	public function print_fouc_script() {
		if ( ! $this->is_plugin_screen() ) {
			return;
		}
		?>
<script id="aifirst-fouc">(function(){try{var t=localStorage.getItem('aifirst_theme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();</script>
		<?php
	}

	/**
	 * Inject the theme toggle alongside the stack badge.
	 *
	 * @since 0.7.0
	 * @return void
	 */
	public function render_theme_toggle() {
		if ( ! $this->is_plugin_screen() ) {
			return;
		}
		AIFirst_Components::dark_mode_toggle();
	}

	/**
	 * Inject the floating "My Stack (N)" badge on plugin-owned pages.
	 * Works on any theme without needing a header hook.
	 *
	 * @since 0.6.0
	 * @return void
	 */
	public function render_stack_badge() {
		if ( ! $this->is_plugin_screen() ) {
			return;
		}
		AIFirst_Components::stack_header_badge();
	}

	/**
	 * Register the /changelog/, /submit/, /compare/, /features/ rewrites.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function add_rewrites() {
		add_rewrite_rule( '^changelog/?$', 'index.php?' . self::QV_PAGE . '=changelog', 'top' );
		add_rewrite_rule( '^submit/?$',    'index.php?' . self::QV_PAGE . '=submit',    'top' );
		add_rewrite_rule( '^features/?$',  'index.php?' . self::QV_PAGE . '=features-index', 'top' );

		// My Stack: cookie-driven /stack/ + URL-driven /stack/slug+slug+…/.
		// @since 0.6.0
		add_rewrite_rule( '^stack/?$', 'index.php?' . self::QV_PAGE . '=stack', 'top' );
		add_rewrite_rule(
			'^stack/([^/]+)/?$',
			'index.php?' . self::QV_PAGE . '=stack&' . self::QV_STACK . '=$matches[1]',
			'top'
		);

		add_rewrite_rule(
			'^compare(?:/([^/]+))?(?:/([^/]+))?(?:/([^/]+))?/?$',
			'index.php?' . self::QV_PAGE . '=compare&' . self::QV_CMP_A . '=$matches[1]&' . self::QV_CMP_B . '=$matches[2]&' . self::QV_CMP_C . '=$matches[3]',
			'top'
		);
	}

	/**
	 * Register public query vars.
	 *
	 * @since 0.4.0
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::QV_PAGE;
		$vars[] = self::QV_CMP_A;
		$vars[] = self::QV_CMP_B;
		$vars[] = self::QV_CMP_C;
		$vars[] = self::QV_STACK;
		return $vars;
	}

	/**
	 * One-shot rewrite flush scheduled by the upgrader when new rules land.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function maybe_flush_rewrites() {
		if ( get_option( 'aifirst_needs_rewrite_flush' ) ) {
			flush_rewrite_rules( false );
			delete_option( 'aifirst_needs_rewrite_flush' );
		}
	}

	/**
	 * Route the current request to a plugin template, or return $template.
	 *
	 * @since 0.4.0
	 * @param string $template Current WP template path.
	 * @return string
	 */
	public function template_include( $template ) {
		$candidate = $this->resolve_template();
		if ( ! $candidate ) {
			return $template;
		}

		// Let themes override by placing files under <theme>/aifirst/<name>.
		$override = locate_template( array( 'aifirst/' . $candidate ) );
		if ( $override ) {
			return $override;
		}

		$plugin_file = AIFIRST_TEMPLATES_DIR . 'public/' . $candidate;
		if ( file_exists( $plugin_file ) ) {
			return $plugin_file;
		}

		return $template;
	}

	/**
	 * Decide which plugin template this request wants.
	 *
	 * @since 0.4.0
	 * @return string Basename or '' when the request is not ours.
	 */
	protected function resolve_template() {
		$page = get_query_var( self::QV_PAGE );
		if ( $page ) {
			$map = array(
				'changelog'       => 'changelog.php',
				'submit'          => 'submit.php',
				'features-index'  => 'features-index.php',
				'compare'         => 'compare.php',
				'stack'           => 'stack.php',
			);
			return isset( $map[ $page ] ) ? $map[ $page ] : '';
		}

		if ( is_singular( 'aifirst_tool' ) ) {
			return 'single-aifirst_tool.php';
		}
		if ( is_singular( 'aifirst_comparison' ) ) {
			// @since 0.5.0
			return 'single-aifirst_comparison.php';
		}
		if ( is_tax( 'aifirst_category' ) ) {
			return 'taxonomy-aifirst_category.php';
		}
		if ( is_tax( 'aifirst_feature' ) ) {
			return 'taxonomy-aifirst_feature.php';
		}
		if ( is_post_type_archive( 'aifirst_tool' ) ) {
			return 'archive-aifirst_tool.php';
		}
		if ( is_front_page() ) {
			// Only intercept the home page when the owner has opted in
			// explicitly — we don't want to hijack every site's home.
			if ( get_option( 'aifirst_own_home' ) ) {
				return 'front-page.php';
			}
		}
		return '';
	}

	/**
	 * Enqueue the public CSS + JS + Alpine CDN, only on plugin-owned screens.
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_plugin_screen() ) {
			return;
		}

		// @since 0.7.0 — pull Inter so the plugin's typography matches the
		// design regardless of theme. Google Fonts; preconnect omitted
		// to keep this enqueue single-stop.
		wp_enqueue_style(
			'aifirst-inter',
			'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'aifirst-public',
			AIFIRST_PLUGIN_URL . 'assets/css/public.css',
			array( 'aifirst-inter' ),
			AIFIRST_VERSION
		);

		// Alpine v3 (CDN build; `defer` is required by Alpine).
		wp_enqueue_script(
			'alpinejs',
			'https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js',
			array(),
			'3.14.1',
			true
		);
		// Mark alpine script with defer.
		add_filter(
			'script_loader_tag',
			static function ( $tag, $handle ) {
				if ( 'alpinejs' === $handle && false === strpos( $tag, ' defer' ) ) {
					$tag = str_replace( ' src=', ' defer src=', $tag );
				}
				return $tag;
			},
			10,
			2
		);

		wp_enqueue_script(
			'aifirst-public',
			AIFIRST_PLUGIN_URL . 'assets/js/public.js',
			array(),
			AIFIRST_VERSION,
			true
		);

		wp_localize_script(
			'aifirst-public',
			'AIFirstPublic',
			array(
				'homeUrl'       => esc_url_raw( home_url( '/' ) ),
				'toolsBaseUrl'  => esc_url_raw( home_url( '/tools/' ) ),
				'compareBaseUrl' => esc_url_raw( home_url( '/compare/' ) ),
			)
		);
	}

	/**
	 * Whether the current request is a plugin-owned screen.
	 *
	 * @since 0.4.0
	 * @return bool
	 */
	protected function is_plugin_screen() {
		return (
			'' !== $this->resolve_template()
			|| is_singular( array( 'aifirst_tool', 'aifirst_comparison' ) )
			|| is_post_type_archive( 'aifirst_tool' )
			|| is_tax( array( 'aifirst_category', 'aifirst_feature' ) )
		);
	}
}
