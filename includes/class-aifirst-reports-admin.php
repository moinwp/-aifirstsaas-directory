<?php
/**
 * Admin inbox for change reports (wp_aifirst_reports).
 *
 * Registers Tools -> Reports with a WP_List_Table, filters by Status / Tool /
 * Kind, per-row and bulk actions (reviewed / resolved / rejected / delete),
 * and a "(N)" new-count badge on the menu item.
 *
 * @package AIFirst
 * @since   0.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * AIFirst_Reports_Admin.
 *
 * @since 0.5.0
 */
class AIFirst_Reports_Admin {

	const PAGE_SLUG = 'aifirst-reports';
	const NONCE     = 'aifirst_reports_action';

	const ALLOWED_STATUSES = array( 'new', 'reviewed', 'resolved', 'rejected' );

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
	 * Register the Tools -> Reports submenu with a (N) badge.
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function add_menu() {
		$count = $this->count_new();
		$label = __( 'Reports', 'aifirstsaas' );
		if ( $count > 0 ) {
			$label .= ' <span class="awaiting-mod"><span class="pending-count">' . esc_html( number_format_i18n( $count ) ) . '</span></span>';
		}

		add_submenu_page(
			'edit.php?post_type=aifirst_tool',
			__( 'Reports — AI First', 'aifirstsaas' ),
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
		$table = $wpdb->prefix . 'aifirst_reports';
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'new' )
		);
	}

	/**
	 * Run action handlers on admin_init (nonce + capability guarded).
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
		if ( isset( $_REQUEST['report_id'] ) ) {
			$ids[] = (int) $_REQUEST['report_id'];
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
		$table = $wpdb->prefix . 'aifirst_reports';

		if ( 'delete' === $action ) {
			foreach ( $ids as $id ) {
				$wpdb->delete( $table, array( 'report_id' => $id ), array( '%d' ) );
			}
		} elseif ( in_array( $action, self::ALLOWED_STATUSES, true ) ) {
			foreach ( $ids as $id ) {
				$wpdb->update(
					$table,
					array(
						'status'      => $action,
						'reviewed_at' => current_time( 'mysql' ),
					),
					array( 'report_id' => $id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
			}
		}

		wp_safe_redirect( add_query_arg( 'aifirst_done', '1', remove_query_arg( array( 'aifirst_action', '_wpnonce', 'ids', 'report_id' ) ) ) );
		exit;
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
		require_once AIFIRST_INCLUDES_DIR . 'class-aifirst-reports-list-table.php';
		$table = new AIFirst_Reports_List_Table();
		$table->prepare_items();

		$base = admin_url( 'edit.php?post_type=aifirst_tool&page=' . self::PAGE_SLUG );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Change reports', 'aifirstsaas' ); ?></h1>
			<?php if ( isset( $_GET['aifirst_done'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Updated.', 'aifirstsaas' ); ?></p></div>
			<?php endif; ?>

			<form method="get">
				<input type="hidden" name="post_type" value="aifirst_tool" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<?php $table->search_box( __( 'Search', 'aifirstsaas' ), 'aifirst-reports' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}
}
