<?php
/**
 * WP_List_Table for wp_aifirst_reports.
 *
 * @package AIFirst
 * @since   0.5.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * AIFirst_Reports_List_Table.
 *
 * @since 0.5.0
 */
class AIFirst_Reports_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'report',
				'plural'   => 'reports',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Column definitions.
	 *
	 * @since 0.5.0
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'tool_id'    => __( 'Tool', 'aifirstsaas' ),
			'kind'       => __( 'Kind', 'aifirstsaas' ),
			'body'       => __( 'Report', 'aifirstsaas' ),
			'email'      => __( 'Email', 'aifirstsaas' ),
			'status'     => __( 'Status', 'aifirstsaas' ),
			'created_at' => __( 'Submitted', 'aifirstsaas' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @since 0.5.0
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
			'status'     => array( 'status', false ),
			'tool_id'    => array( 'tool_id', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @since 0.5.0
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'reviewed' => __( 'Mark reviewed', 'aifirstsaas' ),
			'resolved' => __( 'Mark resolved', 'aifirstsaas' ),
			'rejected' => __( 'Reject', 'aifirstsaas' ),
			'delete'   => __( 'Delete', 'aifirstsaas' ),
		);
	}

	/**
	 * Status/Tool/Kind filter dropdowns + new-row count summary.
	 *
	 * @since 0.5.0
	 * @param string $which top | bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$status = isset( $_GET['status_f'] ) ? sanitize_key( wp_unslash( $_GET['status_f'] ) ) : '';
		$tool   = isset( $_GET['tool_f'] ) ? sanitize_title( wp_unslash( $_GET['tool_f'] ) ) : '';
		$kind   = isset( $_GET['kind_f'] ) ? sanitize_key( wp_unslash( $_GET['kind_f'] ) ) : '';
		?>
		<div class="alignleft actions">
			<select name="status_f">
				<option value=""><?php esc_html_e( 'Any status', 'aifirstsaas' ); ?></option>
				<?php foreach ( AIFirst_Reports_Admin::ALLOWED_STATUSES as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $status, $s ); ?>><?php echo esc_html( $s ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" name="tool_f" value="<?php echo esc_attr( $tool ); ?>" placeholder="<?php esc_attr_e( 'Tool slug…', 'aifirstsaas' ); ?>" />
			<select name="kind_f">
				<option value=""><?php esc_html_e( 'Any kind', 'aifirstsaas' ); ?></option>
				<?php foreach ( AIFirst_Reports::ALLOWED_KINDS as $k ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $kind, $k ); ?>><?php echo esc_html( $k ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'aifirstsaas' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Checkbox column.
	 *
	 * @since 0.5.0
	 * @param object $item Row.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item->report_id );
	}

	/**
	 * Tool column — link to tool edit screen + per-row actions.
	 *
	 * @since 0.5.0
	 * @param object $item Row.
	 * @return string
	 */
	public function column_tool_id( $item ) {
		$meta = AIFirst_Query::tool_meta_by_id( (string) $item->tool_id );
		$name = $meta ? get_the_title( (int) $meta->post_id ) : $item->tool_id;
		$link = $meta ? get_edit_post_link( (int) $meta->post_id ) : '';
		$out  = $link
			? sprintf( '<strong><a href="%s">%s</a></strong>', esc_url( $link ), esc_html( $name ) )
			: '<strong>' . esc_html( $name ) . '</strong>';

		$actions = array();
		$base    = add_query_arg( array( 'page' => AIFirst_Reports_Admin::PAGE_SLUG, 'post_type' => 'aifirst_tool' ), admin_url( 'edit.php' ) );
		foreach ( array( 'reviewed', 'resolved', 'rejected', 'delete' ) as $a ) {
			$url            = wp_nonce_url(
				add_query_arg(
					array(
						'aifirst_action' => $a,
						'report_id'      => (int) $item->report_id,
					),
					$base
				),
				AIFirst_Reports_Admin::NONCE
			);
			$actions[ $a ] = sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( ucfirst( $a ) ) );
		}
		return $out . $this->row_actions( $actions );
	}

	/**
	 * Default column renderer.
	 *
	 * @since 0.5.0
	 * @param object $item Row.
	 * @param string $col Column key.
	 * @return string
	 */
	public function column_default( $item, $col ) {
		switch ( $col ) {
			case 'kind':
			case 'email':
			case 'status':
				return esc_html( (string) $item->$col );
			case 'body':
				$body = (string) $item->body;
				return esc_html( mb_strlen( $body ) > 60 ? mb_substr( $body, 0, 60 ) . '…' : $body );
			case 'created_at':
				return esc_html( mysql2date( 'M j, Y H:i', $item->created_at ) );
		}
		return '';
	}

	/**
	 * Prepare table items (filters + pagination).
	 *
	 * @since 0.5.0
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_reports';

		$per_page = 20;
		$page     = max( 1, (int) $this->get_pagenum() );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		$status = isset( $_GET['status_f'] ) ? sanitize_key( wp_unslash( $_GET['status_f'] ) ) : '';
		$tool   = isset( $_GET['tool_f'] ) ? sanitize_title( wp_unslash( $_GET['tool_f'] ) ) : '';
		$kind   = isset( $_GET['kind_f'] ) ? sanitize_key( wp_unslash( $_GET['kind_f'] ) ) : '';
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		if ( '' !== $status && in_array( $status, AIFirst_Reports_Admin::ALLOWED_STATUSES, true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		if ( '' !== $tool ) {
			$where[]  = 'tool_id = %s';
			$params[] = $tool;
		}
		if ( '' !== $kind ) {
			$where[]  = 'kind = %s';
			$params[] = $kind;
		}
		if ( '' !== $search ) {
			$where[]  = '(body LIKE %s OR email LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$orderby_safe = 'created_at';
		if ( isset( $_GET['orderby'] ) ) {
			$orderby = sanitize_key( wp_unslash( $_GET['orderby'] ) );
			if ( in_array( $orderby, array( 'created_at', 'status', 'tool_id' ), true ) ) {
				$orderby_safe = $orderby;
			}
		}
		$order_safe = ( isset( $_GET['order'] ) && 'asc' === strtolower( (string) wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC';

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = empty( $params )
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			? (int) $wpdb->get_var( $count_sql )
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

		$data_params = array_merge( $params, array( $per_page, $offset ) );
		$data_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby_safe} {$order_safe} LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->items = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ) );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}
}
