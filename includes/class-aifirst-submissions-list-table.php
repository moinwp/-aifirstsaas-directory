<?php
/**
 * WP_List_Table for wp_aifirst_submissions.
 *
 * @package AIFirst
 * @since   0.5.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * AIFirst_Submissions_List_Table.
 *
 * @since 0.5.0
 */
class AIFirst_Submissions_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'submission',
				'plural'   => 'submissions',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'kind'       => __( 'Kind', 'aifirstsaas' ),
			'tool_id'    => __( 'Target tool', 'aifirstsaas' ),
			'body'       => __( 'Body', 'aifirstsaas' ),
			'email'      => __( 'Email', 'aifirstsaas' ),
			'status'     => __( 'Status', 'aifirstsaas' ),
			'created_at' => __( 'Submitted', 'aifirstsaas' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
			'status'     => array( 'status', false ),
			'kind'       => array( 'kind', false ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'approve'  => __( 'Approve', 'aifirstsaas' ),
			'rejected' => __( 'Reject', 'aifirstsaas' ),
			'delete'   => __( 'Delete', 'aifirstsaas' ),
		);
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$status = isset( $_GET['status_f'] ) ? sanitize_key( wp_unslash( $_GET['status_f'] ) ) : '';
		$kind   = isset( $_GET['kind_f'] ) ? sanitize_key( wp_unslash( $_GET['kind_f'] ) ) : '';
		?>
		<div class="alignleft actions">
			<select name="status_f">
				<option value=""><?php esc_html_e( 'Any status', 'aifirstsaas' ); ?></option>
				<?php foreach ( AIFirst_Submissions_Admin::ALLOWED_STATUSES as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $status, $s ); ?>><?php echo esc_html( $s ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="kind_f">
				<option value=""><?php esc_html_e( 'Any kind', 'aifirstsaas' ); ?></option>
				<option value="new" <?php selected( $kind, 'new' ); ?>><?php esc_html_e( 'New tool', 'aifirstsaas' ); ?></option>
				<option value="update" <?php selected( $kind, 'update' ); ?>><?php esc_html_e( 'Update', 'aifirstsaas' ); ?></option>
			</select>
			<?php submit_button( __( 'Filter', 'aifirstsaas' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', (int) $item->submission_id );
	}

	public function column_kind( $item ) {
		$label = ( 'update' === $item->kind ) ? __( 'Update', 'aifirstsaas' ) : __( 'New tool', 'aifirstsaas' );
		$base  = add_query_arg( array( 'page' => AIFirst_Submissions_Admin::PAGE_SLUG, 'post_type' => 'aifirst_tool' ), admin_url( 'edit.php' ) );
		$view  = add_query_arg( 'view', (int) $item->submission_id, $base );
		$out   = '<strong><a href="' . esc_url( $view ) . '">' . esc_html( $label ) . '</a></strong>';

		// @since 0.8.0 — "View details" row action.
		$actions = array(
			'view' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $view ),
				esc_html__( 'View submission details', 'aifirstsaas' )
			),
		);
		foreach ( array( 'approve', 'rejected', 'delete' ) as $a ) {
			$url          = wp_nonce_url(
				add_query_arg(
					array(
						'aifirst_action' => $a,
						'submission_id'  => (int) $item->submission_id,
					),
					$base
				),
				AIFirst_Submissions_Admin::NONCE
			);
			$label_action = 'approve' === $a ? __( 'Approve', 'aifirstsaas' ) : ucfirst( $a );
			$actions[ $a ] = sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $label_action ) );
		}
		return $out . $this->row_actions( $actions );
	}

	public function column_tool_id( $item ) {
		if ( '' === (string) $item->tool_id ) {
			$body = json_decode( (string) $item->body, true );
			if ( is_array( $body ) && isset( $body['tool_name'] ) && '' !== (string) $body['tool_name'] ) {
				return '<em>' . esc_html( (string) $body['tool_name'] ) . '</em> <span class="aifirst-pending-slug" style="color:#6B6B65">(' . esc_html__( 'pending slug', 'aifirstsaas' ) . ')</span>';
			}
			return '—';
		}
		$meta = AIFirst_Query::tool_meta_by_id( (string) $item->tool_id );
		if ( $meta ) {
			return sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( (int) $meta->post_id ) ), esc_html( get_the_title( (int) $meta->post_id ) ) );
		}
		return esc_html( (string) $item->tool_id );
	}

	public function column_default( $item, $col ) {
		switch ( $col ) {
			case 'email':
			case 'status':
				return esc_html( (string) $item->$col );
			case 'body':
				$body = json_decode( (string) $item->body, true );
				if ( is_array( $body ) ) {
					$parts = array();
					foreach ( array( 'tool_name', 'website', 'category', 'ai_features', 'notes' ) as $k ) {
						if ( isset( $body[ $k ] ) && '' !== (string) $body[ $k ] ) {
							$parts[] = $k . ': ' . (string) $body[ $k ];
						}
					}
					$body = implode( ' · ', $parts );
				} else {
					$body = (string) $item->body;
				}
				return esc_html( mb_strlen( $body ) > 80 ? mb_substr( $body, 0, 80 ) . '…' : $body );
			case 'created_at':
				return esc_html( mysql2date( 'M j, Y H:i', $item->created_at ) );
		}
		return '';
	}

	public function prepare_items() {
		global $wpdb;
		$table = $wpdb->prefix . 'aifirst_submissions';

		$per_page = 20;
		$page     = max( 1, (int) $this->get_pagenum() );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		$status = isset( $_GET['status_f'] ) ? sanitize_key( wp_unslash( $_GET['status_f'] ) ) : '';
		$kind   = isset( $_GET['kind_f'] ) ? sanitize_key( wp_unslash( $_GET['kind_f'] ) ) : '';
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		if ( '' !== $status && in_array( $status, AIFirst_Submissions_Admin::ALLOWED_STATUSES, true ) ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		if ( '' !== $kind && in_array( $kind, array( 'new', 'update' ), true ) ) {
			$where[]  = 'kind = %s';
			$params[] = $kind;
		}
		if ( '' !== $search ) {
			$where[]  = '(body LIKE %s OR email LIKE %s OR tool_id LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$orderby_safe = 'created_at';
		if ( isset( $_GET['orderby'] ) ) {
			$orderby = sanitize_key( wp_unslash( $_GET['orderby'] ) );
			if ( in_array( $orderby, array( 'created_at', 'status', 'kind' ), true ) ) {
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
