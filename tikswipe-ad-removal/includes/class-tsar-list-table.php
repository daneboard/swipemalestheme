<?php
/**
 * WP_List_Table for the Requests admin screen.
 *
 * @package TikSwipe_Ad_Removal
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class TSAR_Requests_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'tsar_request',
				'plural'   => 'tsar_requests',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'id'         => __( 'ID', 'tikswipe-ad-removal' ),
			'user'       => __( 'User', 'tikswipe-ad-removal' ),
			'plan'       => __( 'Plan', 'tikswipe-ad-removal' ),
			'amount'     => __( 'Amount', 'tikswipe-ad-removal' ),
			'paypal'     => __( 'PayPal email', 'tikswipe-ad-removal' ),
			'note'       => __( 'Note', 'tikswipe-ad-removal' ),
			'status'     => __( 'Status', 'tikswipe-ad-removal' ),
			'created_at' => __( 'Submitted', 'tikswipe-ad-removal' ),
			'actions'    => __( 'Actions', 'tikswipe-ad-removal' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'id'         => array( 'id', true ),
			'created_at' => array( 'created_at', true ),
			'status'     => array( 'status', false ),
		);
	}

	protected function get_views() {
		global $wpdb;
		$table   = tsar_table();
		$counts  = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status", ARRAY_A );
		$by      = array(
			'all'      => 0,
			'pending'  => 0,
			'approved' => 0,
			'rejected' => 0,
		);
		foreach ( $counts as $row ) {
			$by[ $row['status'] ] = (int) $row['c'];
			$by['all']           += (int) $row['c'];
		}

		$current = isset( $_REQUEST['status_filter'] ) ? sanitize_key( $_REQUEST['status_filter'] ) : 'all';
		$base    = remove_query_arg( array( 'status_filter', 'paged' ) );

		$views = array();
		foreach ( array( 'all', 'pending', 'approved', 'rejected' ) as $key ) {
			$url   = add_query_arg( 'status_filter', $key, $base );
			$class = $current === $key ? ' class="current"' : '';
			$views[ $key ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $url ),
				$class,
				esc_html( ucfirst( $key ) ),
				$by[ $key ]
			);
		}
		return $views;
	}

	public function prepare_items() {
		global $wpdb;
		$table = tsar_table();

		$per_page = 20;
		$paged    = max( 1, (int) ( $_REQUEST['paged'] ?? 1 ) );
		$offset   = ( $paged - 1 ) * $per_page;

		$status_filter = isset( $_REQUEST['status_filter'] ) ? sanitize_key( $_REQUEST['status_filter'] ) : 'all';
		$orderby       = isset( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'id';
		$order         = isset( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ? 'ASC' : 'DESC';

		$allowed_orderby = array( 'id', 'created_at', 'status' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'id';
		}

		$where  = '1=1';
		$params = array();
		if ( in_array( $status_filter, array( 'pending', 'approved', 'rejected' ), true ) ) {
			$where    = 'status = %s';
			$params[] = $status_filter;
		}

		$total = $params
			? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $params ) )
			: (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );

		$sql      = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query    = $wpdb->prepare( $sql, array_merge( $params, array( $per_page, $offset ) ) );
		$this->items = $wpdb->get_results( $query, ARRAY_A );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	protected function column_default( $item, $col ) {
		switch ( $col ) {
			case 'id':
				return '#' . (int) $item['id'];
			case 'user':
				$user = get_userdata( (int) $item['user_id'] );
				if ( ! $user ) {
					return sprintf( '<em>#%d (deleted)</em>', (int) $item['user_id'] );
				}
				return sprintf(
					'<a href="%s">%s</a><br><small>%s</small>',
					esc_url( get_edit_user_link( $user->ID ) ),
					esc_html( $user->user_login ),
					esc_html( $user->user_email )
				);
			case 'plan':
				$plans = tsar_plans();
				return isset( $plans[ $item['plan'] ] )
					? esc_html( $plans[ $item['plan'] ]['label'] )
					: esc_html( $item['plan'] );
			case 'amount':
				return esc_html( $item['currency'] . ' ' . number_format( (float) $item['amount'], 2 ) );
			case 'paypal':
				return $item['paypal_email_used'] ? esc_html( $item['paypal_email_used'] ) : '—';
			case 'note':
				return $item['txn_note'] ? '<span title="' . esc_attr( $item['txn_note'] ) . '">' . esc_html( wp_trim_words( $item['txn_note'], 8, '…' ) ) . '</span>' : '—';
			case 'status':
				return sprintf(
					'<span class="tsar-status tsar-status-%s">%s</span>',
					esc_attr( $item['status'] ),
					esc_html( tsar_status_label( $item['status'] ) )
				);
			case 'created_at':
				return esc_html( tsar_format_datetime( (int) $item['created_at'] ) );
			case 'actions':
				return $this->render_actions( $item );
		}
		return '';
	}

	private function render_actions( $item ) {
		if ( 'pending' !== $item['status'] ) {
			$processed = $item['processed_at'] ? tsar_format_datetime( (int) $item['processed_at'] ) : '';
			return $processed
				? '<small>' . esc_html__( 'Processed:', 'tikswipe-ad-removal' ) . ' ' . esc_html( $processed ) . '</small>'
				: '—';
		}

		$default_days = (int) ( $item['plan'] === 'lifetime' ? 0 : tsar_get_setting( 'days_30days', 30 ) );
		$approve_url  = wp_nonce_url(
			admin_url( 'admin-post.php?action=tsar_approve&request_id=' . (int) $item['id'] ),
			'tsar_approve_' . (int) $item['id']
		);
		$reject_url   = wp_nonce_url(
			admin_url( 'admin-post.php?action=tsar_reject&request_id=' . (int) $item['id'] ),
			'tsar_reject_' . (int) $item['id']
		);

		ob_start();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tsar-row-form">
			<input type="hidden" name="action" value="tsar_approve">
			<input type="hidden" name="request_id" value="<?php echo (int) $item['id']; ?>">
			<?php wp_nonce_field( 'tsar_approve_' . (int) $item['id'] ); ?>
			<label class="screen-reader-text"><?php esc_html_e( 'Days', 'tikswipe-ad-removal' ); ?></label>
			<input type="number" name="days" min="0" value="<?php echo esc_attr( $default_days ); ?>" class="small-text" title="<?php esc_attr_e( '0 = lifetime', 'tikswipe-ad-removal' ); ?>">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Approve', 'tikswipe-ad-removal' ); ?></button>
		</form>
		<a href="<?php echo esc_url( $reject_url ); ?>" class="button button-link-delete tsar-confirm" data-confirm="<?php esc_attr_e( 'Reject this request?', 'tikswipe-ad-removal' ); ?>"><?php esc_html_e( 'Reject', 'tikswipe-ad-removal' ); ?></a>
		<?php
		return ob_get_clean();
	}
}
