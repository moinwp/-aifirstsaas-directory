<?php
/**
 * Admin inbox for public submissions (wp_aifirst_submissions).
 *
 * Approve-a-new-tool creates a draft aifirst_tool through the Session 2
 * intake pipeline; approve-an-update upserts in place. Both run the
 * payload through AIFirst_Record::validate() first.
 *
 * @package AIFirst
 * @since   0.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Submissions_Admin.
 *
 * @since 0.5.0
 */
class AIFirst_Submissions_Admin {

	const PAGE_SLUG = 'aifirst-submissions';
	const NONCE     = 'aifirst_submissions_action';

	const ALLOWED_STATUSES = array( 'new', 'approved', 'rejected' );

	/**
	 * Register hooks.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Register the Tools -> Submissions submenu with a (N) badge.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function add_menu() {
		$count = $this->count_new();
		$label = __( 'Submissions', 'aifirstsaas' );
		if ( $count > 0 ) {
			$label .= ' <span class="awaiting-mod"><span class="pending-count">' . esc_html( number_format_i18n( $count ) ) . '</span></span>';
		}

		add_submenu_page(
			'edit.php?post_type=aifirst_tool',
			__( 'Submissions — AI First', 'aifirstsaas' ),
			$label,
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Count rows where status='new'.
	 *
	 * @since 0.5.0
	 * @return int
	 */
	protected function count_new() {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_submissions';
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'new' )
		);
	}

	/**
	 * Run action handlers.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function handle_actions() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page = isset( $_REQUEST['page'] ) ? sanitize_key( wp_unslash( $_REQUEST['page'] ) ) : '';
		if ( self::PAGE_SLUG !== $page ) {
			return;
		}
		$action = isset( $_REQUEST['aifirst_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['aifirst_action'] ) ) : '';
		if ( '' === $action ) {
			return;
		}
		check_admin_referer( self::NONCE );

		$ids = array();
		if ( isset( $_REQUEST['submission_id'] ) ) {
			$ids[] = (int) $_REQUEST['submission_id'];
		}
		if ( isset( $_REQUEST['ids'] ) && is_array( $_REQUEST['ids'] ) ) {
			foreach ( $_REQUEST['ids'] as $id ) {
				$ids[] = (int) $id;
			}
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( empty( $ids ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_submissions';

		$outcome = array( 'ok' => 0, 'fail' => 0, 'errors' => array() );

		foreach ( $ids as $id ) {
			if ( 'delete' === $action ) {
				$wpdb->delete( $table, array( 'submission_id' => $id ), array( '%d' ) );
				$outcome['ok']++;
			} elseif ( 'rejected' === $action ) {
				$wpdb->update(
					$table,
					array( 'status' => 'rejected', 'reviewed_at' => current_time( 'mysql' ) ),
					array( 'submission_id' => $id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
				$outcome['ok']++;
			} elseif ( 'approve' === $action ) {
				$row = $wpdb->get_row(
					$wpdb->prepare( "SELECT * FROM {$table} WHERE submission_id = %d", $id )
				);
				if ( ! $row ) {
					$outcome['fail']++;
					$outcome['errors'][] = sprintf( /* translators: %d: id */ __( 'Submission #%d not found.', 'aifirstsaas' ), $id );
					continue;
				}
				$res = $this->approve_row( $row );
				if ( is_wp_error( $res ) ) {
					$outcome['fail']++;
					$outcome['errors'][] = $res->get_error_message();
					continue;
				}
				$wpdb->update(
					$table,
					array( 'status' => 'approved', 'reviewed_at' => current_time( 'mysql' ) ),
					array( 'submission_id' => $id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
				$outcome['ok']++;
				// @since 0.8.0 — carry the created post_id so the flash
				// can link directly to the new draft.
				if ( is_array( $res ) && ! empty( $res['post_id'] ) ) {
					$outcome['post_ids'][] = (int) $res['post_id'];
				}
			}
		}

		set_transient( 'aifirst_submissions_flash_' . get_current_user_id(), $outcome, 30 );

		wp_safe_redirect(
			add_query_arg(
				'aifirst_done',
				'1',
				remove_query_arg( array( 'aifirst_action', '_wpnonce', 'ids', 'submission_id' ) )
			)
		);
		exit;
	}

	/**
	 * Build a canonical record from a submission body and run it through
	 * AIFirst_Intake::save_record().
	 *
	 * @since 0.5.0
	 * @param object $row Submission row.
	 * @return array|WP_Error save_record() result or error.
	 */
	protected function approve_row( $row ) {
		// @since 0.8.0 — prefer the richer `payload` JSON stored in
		// Session 7.2; fall back to the slim `body` for legacy rows.
		$payload = array();
		if ( isset( $row->payload ) && '' !== (string) $row->payload ) {
			$decoded = json_decode( (string) $row->payload, true );
			if ( is_array( $decoded ) ) {
				$payload = $decoded;
			}
		}
		if ( empty( $payload ) ) {
			$body    = json_decode( (string) $row->body, true );
			$payload = is_array( $body ) ? $body : array();
		}

		$record = AIFirst_Record::skeleton();

		$kind    = isset( $row->kind ) ? (string) $row->kind : 'new';
		$tool_id = ( 'update' === $kind && '' !== (string) $row->tool_id )
			? (string) $row->tool_id
			: sanitize_title( (string) ( isset( $payload['tool_name'] ) ? $payload['tool_name'] : '' ) );

		$record['tool']['tool_id'] = $tool_id;
		$record['tool']['title']   = isset( $payload['tool_name'] )
			? (string) $payload['tool_name']
			: ( '' !== $tool_id ? ucwords( str_replace( '-', ' ', $tool_id ) ) : '' );
		$record['tool']['status']  = 'draft';
		$record['tool']['excerpt'] = isset( $payload['excerpt'] ) ? (string) $payload['excerpt'] : '';
		$record['tool']['content'] = isset( $payload['why_ai_first'] ) ? wp_kses_post( (string) $payload['why_ai_first'] ) : '';

		if ( isset( $payload['category'] ) && '' !== (string) $payload['category'] ) {
			// Try to resolve slug to category name; fall back to the raw value.
			$term = get_term_by( 'slug', sanitize_title( (string) $payload['category'] ), 'aifirst_category' );
			$record['tool']['categories'] = array( $term ? $term->name : (string) $payload['category'] );
		}

		if ( isset( $payload['ai_features'] ) ) {
			$features = preg_split( '/[\n,]+/', (string) $payload['ai_features'] );
			foreach ( (array) $features as $name ) {
				$name = trim( (string) $name );
				if ( '' !== $name ) {
					$record['tool_features'][] = array(
						'feature_name' => $name,
						'feature_slug' => sanitize_title( $name ),
						'is_native'    => 1,
					);
				}
			}
		}

		// Meta fields drawn from the rich payload.
		$meta_map = array(
			'company_name'       => 'company_name',
			'website_url'        => 'website',
			'pricing_url'        => 'pricing_url',
			'logo_url'           => 'logo_url',
			'subcategory'        => 'subcategory',
			'pricing_model'      => 'pricing_model',
		);
		foreach ( $meta_map as $meta_key => $payload_key ) {
			if ( isset( $payload[ $payload_key ] ) && '' !== (string) $payload[ $payload_key ] ) {
				$record['meta'][ $meta_key ] = (string) $payload[ $payload_key ];
			}
		}
		$record['meta']['has_free_plan']   = ! empty( $payload['has_free_plan'] ) ? 1 : 0;
		$record['meta']['ai_on_free_plan'] = ! empty( $payload['ai_on_free_plan'] ) ? 1 : 0;
		if ( isset( $payload['starting_price_usd'] ) && '' !== (string) $payload['starting_price_usd'] && null !== $payload['starting_price_usd'] ) {
			$record['meta']['starting_price_usd'] = (float) $payload['starting_price_usd'];
		} else {
			$record['meta']['starting_price_usd'] = 0;
		}

		$validated = AIFirst_Record::validate( $record );
		if ( null === $validated['record'] ) {
			return new WP_Error( 'aifirst_validation', implode( '; ', $validated['errors'] ) );
		}

		$intake = new AIFirst_Intake();
		$result = $intake->save_record( $validated['record'] );
		if ( 0 === $result['post_id'] ) {
			return new WP_Error( 'aifirst_save_failed', __( 'Intake failed to persist the record.', 'aifirstsaas' ) );
		}
		return $result;
	}

	/**
	 * Render the admin page.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'aifirstsaas' ) );
		}

		// @since 0.8.0 — single-submission detail view.
		if ( isset( $_GET['view'] ) ) {
			$this->render_detail( (int) $_GET['view'] );
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		require_once AIFIRST_INCLUDES_DIR . 'class-aifirst-submissions-list-table.php';
		$table = new AIFirst_Submissions_List_Table();
		$table->prepare_items();

		$flash = get_transient( 'aifirst_submissions_flash_' . get_current_user_id() );
		if ( $flash ) {
			delete_transient( 'aifirst_submissions_flash_' . get_current_user_id() );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Submissions', 'aifirstsaas' ); ?></h1>

			<?php if ( is_array( $flash ) ) : ?>
				<div class="notice <?php echo $flash['fail'] > 0 ? 'notice-warning' : 'notice-success'; ?> is-dismissible">
					<p><?php echo esc_html( sprintf( /* translators: 1: ok 2: fail */ __( '%1$d processed, %2$d failed.', 'aifirstsaas' ), (int) $flash['ok'], (int) $flash['fail'] ) ); ?></p>
					<?php foreach ( (array) $flash['errors'] as $err ) : ?>
						<p style="color:#b32d2e;"><?php echo esc_html( (string) $err ); ?></p>
					<?php endforeach; ?>
					<?php if ( ! empty( $flash['post_ids'] ) ) : ?>
						<p>
							<?php foreach ( (array) $flash['post_ids'] as $pid ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( (int) $pid ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: title */ __( 'Open draft: %s', 'aifirstsaas' ), get_the_title( (int) $pid ) ) ); ?></a> &nbsp;
							<?php endforeach; ?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<form method="get">
				<input type="hidden" name="post_type" value="aifirst_tool" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<?php $table->search_box( __( 'Search', 'aifirstsaas' ), 'aifirst-submissions' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the full detail view for a single submission (payload dump
	 * laid out field-by-field) with Approve / Reject / Back actions.
	 *
	 * @since 0.8.0
	 * @param int $id Submission ID.
	 * @return void
	 */
	protected function render_detail( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_submissions';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE submission_id = %d", $id ) );
		$back  = admin_url( 'edit.php?post_type=aifirst_tool&page=' . self::PAGE_SLUG );
		if ( ! $row ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Submission not found', 'aifirstsaas' ) . '</h1>';
			echo '<p><a href="' . esc_url( $back ) . '">' . esc_html__( '← Back to submissions', 'aifirstsaas' ) . '</a></p></div>';
			return;
		}
		$payload = json_decode( (string) $row->payload, true );
		if ( ! is_array( $payload ) ) {
			$payload = json_decode( (string) $row->body, true );
			$payload = is_array( $payload ) ? $payload : array();
		}

		$labels = array(
			'tool_name'          => __( 'Tool name', 'aifirstsaas' ),
			'website'            => __( 'Website', 'aifirstsaas' ),
			'pricing_url'        => __( 'Pricing URL', 'aifirstsaas' ),
			'logo_url'           => __( 'Logo URL', 'aifirstsaas' ),
			'company_name'       => __( 'Company name', 'aifirstsaas' ),
			'category'           => __( 'Category', 'aifirstsaas' ),
			'subcategory'        => __( 'Subcategory', 'aifirstsaas' ),
			'excerpt'            => __( 'Excerpt', 'aifirstsaas' ),
			'ai_features'        => __( 'AI features', 'aifirstsaas' ),
			'pricing_model'      => __( 'Pricing model', 'aifirstsaas' ),
			'has_free_plan'      => __( 'Has free plan', 'aifirstsaas' ),
			'ai_on_free_plan'    => __( 'AI on free plan', 'aifirstsaas' ),
			'starting_price_usd' => __( 'Starting price (USD)', 'aifirstsaas' ),
			'why_ai_first'       => __( 'Why is this tool AI-first?', 'aifirstsaas' ),
			'submitter_name'     => __( 'Submitter name', 'aifirstsaas' ),
			'relationship'       => __( 'Relationship', 'aifirstsaas' ),
			'source_url'         => __( 'Source URL', 'aifirstsaas' ),
			'notes'              => __( 'Notes', 'aifirstsaas' ),
		);

		$approve_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'           => self::PAGE_SLUG,
					'post_type'      => 'aifirst_tool',
					'aifirst_action' => 'approve',
					'submission_id'  => (int) $id,
				),
				admin_url( 'edit.php' )
			),
			self::NONCE
		);
		$reject_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'           => self::PAGE_SLUG,
					'post_type'      => 'aifirst_tool',
					'aifirst_action' => 'rejected',
					'submission_id'  => (int) $id,
				),
				admin_url( 'edit.php' )
			),
			self::NONCE
		);
		?>
		<div class="wrap">
			<h1>
				<?php echo esc_html( sprintf( /* translators: %d id */ __( 'Submission #%d', 'aifirstsaas' ), (int) $id ) ); ?>
				<span style="font-size:13px; font-weight:400; color:#6b6b65; margin-left:8px;">
					<?php echo esc_html( ( 'update' === $row->kind ) ? __( 'Update request', 'aifirstsaas' ) : __( 'New tool', 'aifirstsaas' ) ); ?>
					· <?php echo esc_html( $row->status ); ?>
					· <?php echo esc_html( mysql2date( 'M j, Y H:i', $row->created_at ) ); ?>
				</span>
			</h1>
			<p>
				<a href="<?php echo esc_url( $back ); ?>">← <?php esc_html_e( 'Back to submissions', 'aifirstsaas' ); ?></a>
			</p>

			<?php if ( 'new' !== $row->status || 'new' !== $row->kind && 'update' !== $row->kind ) : ?>
				<p style="color:#6b6b65;"><?php echo esc_html( sprintf( /* translators: %s: status */ __( 'Current status: %s', 'aifirstsaas' ), $row->status ) ); ?></p>
			<?php endif; ?>

			<?php if ( 'new' === $row->status ) : ?>
				<p>
					<a href="<?php echo esc_url( $approve_url ); ?>" class="button button-primary"><?php esc_html_e( 'Approve & create draft tool', 'aifirstsaas' ); ?></a>
					<a href="<?php echo esc_url( $reject_url ); ?>" class="button"><?php esc_html_e( 'Reject', 'aifirstsaas' ); ?></a>
				</p>
			<?php endif; ?>

			<table class="widefat striped" style="max-width:900px; margin-top:16px;">
				<tbody>
					<tr><td style="width:200px; font-weight:600;"><?php esc_html_e( 'Submitter email', 'aifirstsaas' ); ?></td><td><?php echo esc_html( (string) $row->email ); ?></td></tr>
					<tr><td style="font-weight:600;"><?php esc_html_e( 'Source IP', 'aifirstsaas' ); ?></td><td><code><?php echo esc_html( (string) $row->source_ip ); ?></code></td></tr>
					<?php foreach ( $labels as $k => $label ) :
						if ( ! isset( $payload[ $k ] ) ) { continue; }
						$v = $payload[ $k ];
						if ( '' === (string) $v || null === $v ) { continue; }
						?>
						<tr>
							<td style="font-weight:600; vertical-align:top;"><?php echo esc_html( $label ); ?></td>
							<td style="white-space:pre-wrap; vertical-align:top;">
								<?php
								if ( is_bool( $v ) || ( is_numeric( $v ) && in_array( $k, array( 'has_free_plan', 'ai_on_free_plan' ), true ) ) ) {
									echo (int) $v ? '✓' : '—';
								} elseif ( in_array( $k, array( 'website', 'pricing_url', 'logo_url', 'source_url' ), true ) ) {
									printf( '<a href="%1$s" target="_blank" rel="noopener">%1$s</a>', esc_url( (string) $v ) );
								} else {
									echo esc_html( (string) $v );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
