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
			);
			return isset( $map[ $page ] ) ? $map[ $page ] : '';
		}

		if ( is_singular( 'aifirst_tool' ) ) {
			return 'single-aifirst_tool.php';
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

		wp_enqueue_style(
			'aifirst-public',
			AIFIRST_PLUGIN_URL . 'assets/css/public.css',
			array(),
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
			|| is_singular( 'aifirst_tool' )
			|| is_post_type_archive( 'aifirst_tool' )
			|| is_tax( array( 'aifirst_category', 'aifirst_feature' ) )
		);
	}
}
