<?php
/**
 * Newsletter subscriber service.
 *
 * Public AJAX endpoint for the newsletter form, admin list table with
 * CSV export. Anonymous subscribe allowed; protected by nonce +
 * honeypot + 5-per-hour-per-IP rate limit.
 *
 * @package AIFirst
 * @since   0.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Subscribers.
 *
 * @since 0.5.0
 */
class AIFirst_Subscribers {

	const ACTION    = 'aifirst_subscribe';
	const NONCE     = 'aifirst_subscribe';
	const RATE_KEY  = 'aifirst_sub_ip_';
	const RATE_MAX  = 5;
	const RATE_TTL  = HOUR_IN_SECONDS;
	const PAGE_SLUG = 'aifirst-subscribers';

	const ALLOWED_SCOPES = array( 'all', 'category', 'tool', 'feature' );

	/**
	 * Register hooks.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle_ajax' ) );

		add_action( 'admin_menu', array( $this, 'add_menu' ), 25 );
		add_action( 'admin_init', array( $this, 'maybe_export_csv' ) );
	}

	/**
	 * Public AJAX subscribe endpoint.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function handle_ajax() {
		$nonce = isset( $_POST['aifirst_subscribe_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['aifirst_subscribe_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( array( 'message' => __( 'Form expired. Reload and try again.', 'aifirstsaas' ) ), 403 );
		}

		$hp = isset( $_POST['hp'] ) ? trim( (string) wp_unslash( $_POST['hp'] ) ) : '';
		if ( '' !== $hp ) {
			// Silently acknowledge — don't tip off bots.
			wp_send_json_success( array( 'message' => __( 'Subscribed.', 'aifirstsaas' ) ) );
		}

		$ip = $this->client_ip();
		if ( $this->rate_limited( $ip ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many sign-ups from this address. Try again later.', 'aifirstsaas' ) ), 429 );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( '' === $email || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'A valid email address is required.', 'aifirstsaas' ) ), 400 );
		}

		$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'all';
		if ( ! in_array( $scope, self::ALLOWED_SCOPES, true ) ) {
			$scope = 'all';
		}
		$scope_target = isset( $_POST['scope_target'] ) ? sanitize_title( wp_unslash( $_POST['scope_target'] ) ) : '';
		if ( 'all' === $scope ) {
			$scope_target = '';
		}
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : 'unknown';

		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_subscribers';

		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT sub_id FROM {$table} WHERE email = %s LIMIT 1", $email )
		);

		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'scope'        => $scope,
					'scope_target' => $scope_target,
					'source'       => $source,
				),
				array( 'sub_id' => (int) $existing->sub_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'email'        => $email,
					'scope'        => $scope,
					'scope_target' => $scope_target,
					'source'       => $source,
					'confirmed'    => 0,
					'source_ip'    => $ip,
					'created_at'   => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}

		$this->bump_rate( $ip );
		wp_send_json_success( array( 'message' => __( 'Subscribed — check your inbox.', 'aifirstsaas' ) ) );
	}

	/**
	 * Best-effort client IP.
	 *
	 * @since 0.5.0
	 * @return string
	 */
	protected function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		$ip = filter_var( $ip, FILTER_VALIDATE_IP );
		return $ip ? $ip : '0.0.0.0';
	}

	/**
	 * Rate-limit: max 5 submits/hour per IP.
	 *
	 * @since 0.5.0
	 * @param string $ip IP.
	 * @return bool
	 */
	protected function rate_limited( $ip ) {
		$count = (int) get_transient( self::RATE_KEY . md5( $ip ) );
		return $count >= self::RATE_MAX;
	}

	/**
	 * Record one hit against the rate window.
	 *
	 * @since 0.5.0
	 * @param string $ip IP.
	 * @return void
	 */
	protected function bump_rate( $ip ) {
		$key   = self::RATE_KEY . md5( $ip );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, self::RATE_TTL );
	}

	/**
	 * Register the Tools -> Subscribers submenu.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function add_menu() {
		add_submenu_page(
			'edit.php?post_type=aifirst_tool',
			__( 'Subscribers — AI First', 'aifirstsaas' ),
			__( 'Subscribers', 'aifirstsaas' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * CSV export of the current filtered view. Streamed as attachment.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function maybe_export_csv() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';
		if ( self::PAGE_SLUG !== $page ) {
			return;
		}
		if ( empty( $_REQUEST['aifirst_export'] ) ) {
			return;
		}
		check_admin_referer( 'aifirst_export_subscribers' );

		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_subscribers';

		$where  = array( '1=1' );
		$params = array();
		$scope  = isset( $_REQUEST['scope_f'] ) ? sanitize_key( wp_unslash( $_REQUEST['scope_f'] ) ) : '';
		if ( '' !== $scope && in_array( $scope, self::ALLOWED_SCOPES, true ) ) {
			$where[]  = 'scope = %s';
			$params[] = $scope;
		}
		if ( isset( $_REQUEST['confirmed_f'] ) && '' !== $_REQUEST['confirmed_f'] ) {
			$where[]  = 'confirmed = %d';
			$params[] = (int) $_REQUEST['confirmed_f'];
		}
		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT email, scope, scope_target, source, confirmed, confirmed_at, created_at FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC";
		$rows      = empty( $params )
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			? $wpdb->get_results( $sql )
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="aifirst-subscribers-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'email', 'scope', 'scope_target', 'source', 'confirmed', 'confirmed_at', 'created_at' ) );
		foreach ( (array) $rows as $r ) {
			fputcsv(
				$out,
				array(
					(string) $r->email,
					(string) $r->scope,
					(string) $r->scope_target,
					(string) $r->source,
					(int) $r->confirmed,
					(string) $r->confirmed_at,
					(string) $r->created_at,
				)
			);
		}
		fclose( $out );
		exit;
	}

	/**
	 * Render the admin list page.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'aifirstsaas' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_subscribers';

		$scope_f     = isset( $_GET['scope_f'] ) ? sanitize_key( wp_unslash( $_GET['scope_f'] ) ) : '';
		$confirmed_f = isset( $_GET['confirmed_f'] ) ? wp_unslash( (string) $_GET['confirmed_f'] ) : '';
		$per_page    = 25;
		$page_num    = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$offset      = ( $page_num - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();
		if ( '' !== $scope_f && in_array( $scope_f, self::ALLOWED_SCOPES, true ) ) {
			$where[]  = 'scope = %s';
			$params[] = $scope_f;
		}
		if ( '' !== $confirmed_f ) {
			$where[]  = 'confirmed = %d';
			$params[] = (int) $confirmed_f;
		}
		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = empty( $params )
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			? (int) $wpdb->get_var( $count_sql )
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

		$data_params = array_merge( $params, array( $per_page, $offset ) );
		$data_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ) );

		$base_url = admin_url( 'edit.php?post_type=aifirst_tool&page=' . self::PAGE_SLUG );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Subscribers', 'aifirstsaas' ); ?></h1>

			<form method="get" style="display:flex; gap:12px; align-items:center; margin:12px 0;">
				<input type="hidden" name="post_type" value="aifirst_tool" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<select name="scope_f">
					<option value=""><?php esc_html_e( 'Any scope', 'aifirstsaas' ); ?></option>
					<?php foreach ( self::ALLOWED_SCOPES as $s ) : ?>
						<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $scope_f, $s ); ?>><?php echo esc_html( $s ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="confirmed_f">
					<option value=""><?php esc_html_e( 'Any confirmation', 'aifirstsaas' ); ?></option>
					<option value="0" <?php selected( $confirmed_f, '0' ); ?>><?php esc_html_e( 'Unconfirmed', 'aifirstsaas' ); ?></option>
					<option value="1" <?php selected( $confirmed_f, '1' ); ?>><?php esc_html_e( 'Confirmed', 'aifirstsaas' ); ?></option>
				</select>
				<?php submit_button( __( 'Filter', 'aifirstsaas' ), '', 'filter', false ); ?>
				<a class="button" href="<?php echo esc_url( wp_nonce_url(
					add_query_arg(
						array(
							'aifirst_export' => '1',
							'scope_f'        => $scope_f,
							'confirmed_f'    => $confirmed_f,
						),
						$base_url
					),
					'aifirst_export_subscribers'
				) ); ?>">
					<?php esc_html_e( 'Export CSV', 'aifirstsaas' ); ?>
				</a>
			</form>

			<p><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d subscriber', '%d subscribers', $total, 'aifirstsaas' ), $total ) ); ?></p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Email', 'aifirstsaas' ); ?></th>
						<th><?php esc_html_e( 'Scope', 'aifirstsaas' ); ?></th>
						<th><?php esc_html_e( 'Target', 'aifirstsaas' ); ?></th>
						<th><?php esc_html_e( 'Source', 'aifirstsaas' ); ?></th>
						<th><?php esc_html_e( 'Confirmed', 'aifirstsaas' ); ?></th>
						<th><?php esc_html_e( 'Signed up', 'aifirstsaas' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6" style="padding:20px; text-align:center; color:#6b6b65;"><?php esc_html_e( 'No subscribers match.', 'aifirstsaas' ); ?></td></tr>
					<?php else : foreach ( $rows as $r ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $r->email ); ?></td>
							<td><?php echo esc_html( (string) $r->scope ); ?></td>
							<td><?php echo esc_html( (string) $r->scope_target ); ?></td>
							<td><?php echo esc_html( (string) $r->source ); ?></td>
							<td><?php echo (int) $r->confirmed ? '✓' : '—'; ?></td>
							<td><?php echo esc_html( mysql2date( 'M j, Y H:i', $r->created_at ) ); ?></td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput
							array(
								'base'    => add_query_arg( 'paged', '%#%', $base_url . ( '' !== $scope_f ? '&scope_f=' . rawurlencode( $scope_f ) : '' ) . ( '' !== $confirmed_f ? '&confirmed_f=' . rawurlencode( $confirmed_f ) : '' ) ),
								'format'  => '',
								'current' => $page_num,
								'total'   => $total_pages,
							)
						);
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
