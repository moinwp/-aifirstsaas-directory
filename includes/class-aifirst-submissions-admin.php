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
		$body = json_decode( (string) $row->body, true );
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$record = AIFirst_Record::skeleton();

		$kind    = isset( $row->kind ) ? (string) $row->kind : 'new';
		$tool_id = ( 'update' === $kind && '' !== (string) $row->tool_id )
			? (string) $row->tool_id
			: sanitize_title( (string) ( isset( $body['tool_name'] ) ? $body['tool_name'] : '' ) );

		$record['tool']['tool_id'] = $tool_id;
		$record['tool']['title']   = isset( $body['tool_name'] )
			? (string) $body['tool_name']
			: ( '' !== $tool_id ? ucwords( str_replace( '-', ' ', $tool_id ) ) : '' );
		$record['tool']['status']  = 'draft';

		if ( isset( $body['category'] ) && '' !== (string) $body['category'] ) {
			$record['tool']['categories'] = array( (string) $body['category'] );
		}

		$notes_blob = array();
		if ( isset( $body['notes'] ) && '' !== (string) $body['notes'] ) {
			$notes_blob[] = (string) $body['notes'];
		}
		if ( isset( $body['source_url'] ) && '' !== (string) $body['source_url'] ) {
			$notes_blob[] = sprintf( /* translators: %s: url */ __( 'Source: %s', 'aifirstsaas' ), (string) $body['source_url'] );
		}
		$record['tool']['content'] = implode( "\n\n", $notes_blob );

		if ( isset( $body['ai_features'] ) ) {
			$features = preg_split( '/[\n,]+/', (string) $body['ai_features'] );
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
		if ( isset( $body['website'] ) && '' !== (string) $body['website'] ) {
			$record['meta']['website_url'] = (string) $body['website'];
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
}
