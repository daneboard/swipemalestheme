<?php
/**
 * View / click / close tracking for shop items.
 *
 *  - REST: POST /tikswipe-shop/v1/track {item_id, event=view|click|close}
 *  - Per-item counters stored as post meta (_tss_views/_tss_clicks/_tss_closes).
 *  - "Stats" meta box on the edit screen + dedicated columns on the list.
 *
 * @package TikSwipe_Shop
 */

defined( 'ABSPATH' ) || exit;

class TSS_Tracking {

	const EVENTS = array(
		'view'  => '_tss_views',
		'click' => '_tss_clicks',
		'close' => '_tss_closes',
	);

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_stats_box' ) );
		add_filter( 'manage_' . TSS_CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . TSS_CPT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . TSS_CPT . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'maybe_sort_query' ) );
	}

	public static function register_routes() {
		register_rest_route(
			'tikswipe-shop/v1',
			'/track',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => '__return_true',
				'args'                => array(
					'item_id' => array( 'required' => true, 'type' => 'integer' ),
					'event'   => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array_keys( self::EVENTS ),
					),
				),
				'callback'            => array( __CLASS__, 'track' ),
			)
		);
	}

	public static function track( $request ) {
		$item_id = (int) $request->get_param( 'item_id' );
		$event   = (string) $request->get_param( 'event' );

		if ( ! isset( self::EVENTS[ $event ] ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'bad_event' ), 400 );
		}
		if ( get_post_type( $item_id ) !== TSS_CPT ) {
			return new WP_REST_Response( array( 'ok' => false, 'reason' => 'bad_item' ), 404 );
		}

		$key     = self::EVENTS[ $event ];
		$current = (int) get_post_meta( $item_id, $key, true );
		update_post_meta( $item_id, $key, $current + 1 );

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public static function register_stats_box() {
		add_meta_box(
			'tss_stats',
			__( 'Stats', 'tikswipe-shop' ),
			array( __CLASS__, 'render_stats' ),
			TSS_CPT,
			'side',
			'high'
		);
	}

	private static function compute_stats( $item_id ) {
		$views  = (int) get_post_meta( $item_id, '_tss_views', true );
		$clicks = (int) get_post_meta( $item_id, '_tss_clicks', true );
		$closes = (int) get_post_meta( $item_id, '_tss_closes', true );

		$ignored   = max( 0, $views - $clicks );
		$pct       = function ( $part, $whole ) {
			if ( $whole <= 0 ) {
				return 0;
			}
			return round( ( $part / $whole ) * 100, 1 );
		};
		return array(
			'views'        => $views,
			'clicks'       => $clicks,
			'closes'       => $closes,
			'ignored'      => $ignored,
			'click_pct'    => $pct( $clicks, $views ),
			'close_pct'    => $pct( $closes, $views ),
			'ignored_pct'  => $pct( $ignored, $views ),
		);
	}

	public static function render_stats( $post ) {
		$s = self::compute_stats( $post->ID );
		?>
		<table class="tss-stats-table" style="width:100%;border-collapse:collapse;">
			<tr>
				<th style="text-align:left;padding:6px 0;color:#646970;font-weight:500;">
					<?php esc_html_e( 'Views', 'tikswipe-shop' ); ?>
				</th>
				<td style="text-align:right;padding:6px 0;font-weight:600;">
					<?php echo esc_html( number_format_i18n( $s['views'] ) ); ?>
				</td>
			</tr>
			<tr>
				<th style="text-align:left;padding:6px 0;color:#646970;font-weight:500;">
					<?php esc_html_e( 'Buy clicks (wanted)', 'tikswipe-shop' ); ?>
				</th>
				<td style="text-align:right;padding:6px 0;">
					<strong><?php echo esc_html( number_format_i18n( $s['clicks'] ) ); ?></strong>
					<span style="color:#16a55a;"> (<?php echo esc_html( $s['click_pct'] ); ?>%)</span>
				</td>
			</tr>
			<tr>
				<th style="text-align:left;padding:6px 0;color:#646970;font-weight:500;">
					<?php esc_html_e( 'Manual closes', 'tikswipe-shop' ); ?>
				</th>
				<td style="text-align:right;padding:6px 0;">
					<strong><?php echo esc_html( number_format_i18n( $s['closes'] ) ); ?></strong>
					<span style="color:#d63638;"> (<?php echo esc_html( $s['close_pct'] ); ?>%)</span>
				</td>
			</tr>
			<tr>
				<th style="text-align:left;padding:6px 0;color:#646970;font-weight:500;">
					<?php esc_html_e( 'Ignored (no click)', 'tikswipe-shop' ); ?>
				</th>
				<td style="text-align:right;padding:6px 0;">
					<strong><?php echo esc_html( number_format_i18n( $s['ignored'] ) ); ?></strong>
					<span style="color:#8c8f94;"> (<?php echo esc_html( $s['ignored_pct'] ); ?>%)</span>
				</td>
			</tr>
		</table>
		<p style="margin-top:10px;color:#646970;font-size:12px;">
			<?php esc_html_e( 'A view is counted when the card actually appears (after 10s of playback). "Wanted" is clicks on the Buy button or anywhere on the card content. "Ignored" = views − clicks.', 'tikswipe-shop' ); ?>
		</p>
		<?php
	}

	public static function columns( $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'title' === $k ) {
				$out['tss_targets'] = __( 'Targets', 'tikswipe-shop' );
				$out['tss_price']   = __( 'Price', 'tikswipe-shop' );
				$out['tss_views']   = __( 'Views', 'tikswipe-shop' );
				$out['tss_clicks']  = __( 'Clicks', 'tikswipe-shop' );
				$out['tss_closes']  = __( 'Closes', 'tikswipe-shop' );
				$out['tss_ignored'] = __( 'Ignored', 'tikswipe-shop' );
			}
		}
		return $out;
	}

	public static function sortable_columns( $cols ) {
		$cols['tss_views']  = '_tss_views';
		$cols['tss_clicks'] = '_tss_clicks';
		$cols['tss_closes'] = '_tss_closes';
		return $cols;
	}

	public static function maybe_sort_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->get( 'post_type' ) !== TSS_CPT ) {
			return;
		}
		$orderby = $query->get( 'orderby' );
		if ( in_array( $orderby, array( '_tss_views', '_tss_clicks', '_tss_closes' ), true ) ) {
			$query->set( 'meta_key', $orderby );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	public static function column_content( $col, $post_id ) {
		if ( 'tss_price' === $col ) {
			echo esc_html( get_post_meta( $post_id, '_tss_price', true ) );
			return;
		}
		if ( 'tss_targets' === $col ) {
			$pids = (array) get_post_meta( $post_id, '_tss_target_post_ids', true );
			$cids = (array) get_post_meta( $post_id, '_tss_target_categories', true );
			$bits = array();
			if ( $pids ) {
				$bits[] = sprintf( _n( '%d post', '%d posts', count( $pids ), 'tikswipe-shop' ), count( $pids ) );
			}
			if ( $cids ) {
				$bits[] = sprintf( _n( '%d category', '%d categories', count( $cids ), 'tikswipe-shop' ), count( $cids ) );
			}
			echo $bits ? esc_html( implode( ' · ', $bits ) ) : '—';
			return;
		}
		if ( in_array( $col, array( 'tss_views', 'tss_clicks', 'tss_closes', 'tss_ignored' ), true ) ) {
			$s = self::compute_stats( $post_id );
			switch ( $col ) {
				case 'tss_views':
					echo esc_html( number_format_i18n( $s['views'] ) );
					break;
				case 'tss_clicks':
					echo esc_html( number_format_i18n( $s['clicks'] ) );
					if ( $s['views'] > 0 ) {
						echo ' <span style="color:#16a55a;">(' . esc_html( $s['click_pct'] ) . '%)</span>';
					}
					break;
				case 'tss_closes':
					echo esc_html( number_format_i18n( $s['closes'] ) );
					if ( $s['views'] > 0 ) {
						echo ' <span style="color:#d63638;">(' . esc_html( $s['close_pct'] ) . '%)</span>';
					}
					break;
				case 'tss_ignored':
					echo esc_html( number_format_i18n( $s['ignored'] ) );
					if ( $s['views'] > 0 ) {
						echo ' <span style="color:#8c8f94;">(' . esc_html( $s['ignored_pct'] ) . '%)</span>';
					}
					break;
			}
		}
	}
}
