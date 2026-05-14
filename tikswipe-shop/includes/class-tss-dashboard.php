<?php
/**
 * Admin Dashboard page — aggregate stats + charts.
 *
 * Registers a submenu page under the Shop Items CPT. Aggregates per-item
 * counters into KPIs, a clicks/closes/ignored donut, top products by
 * views and clicks, and top stores by clicks. Chart.js is loaded from a
 * CDN only on this page.
 *
 * @package TikSwipe_Shop
 */

defined( 'ABSPATH' ) || exit;

class TSS_Dashboard {

	const HOOK = 'tss_dashboard';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
	}

	public static function register_page() {
		add_submenu_page(
			'edit.php?post_type=' . TSS_CPT,
			__( 'Dashboard', 'tikswipe-shop' ),
			__( 'Dashboard', 'tikswipe-shop' ),
			'edit_posts',
			self::HOOK,
			array( __CLASS__, 'render_page' )
		);
	}

	private static function gather() {
		$query = new WP_Query(
			array(
				'post_type'      => TSS_CPT,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$items   = array();
		$stores  = array();
		$totals  = array(
			'views'   => 0,
			'clicks'  => 0,
			'closes'  => 0,
			'ignored' => 0,
		);

		foreach ( $query->posts as $id ) {
			$views  = (int) get_post_meta( $id, '_tss_views', true );
			$clicks = (int) get_post_meta( $id, '_tss_clicks', true );
			$closes = (int) get_post_meta( $id, '_tss_closes', true );
			$ignored= max( 0, $views - $clicks );

			$totals['views']   += $views;
			$totals['clicks']  += $clicks;
			$totals['closes']  += $closes;
			$totals['ignored'] += $ignored;

			$store = (string) get_post_meta( $id, '_tss_store', true );
			if ( '' === $store ) {
				$store = __( '(no store)', 'tikswipe-shop' );
			}
			if ( ! isset( $stores[ $store ] ) ) {
				$stores[ $store ] = array(
					'name'   => $store,
					'views'  => 0,
					'clicks' => 0,
					'closes' => 0,
				);
			}
			$stores[ $store ]['views']  += $views;
			$stores[ $store ]['clicks'] += $clicks;
			$stores[ $store ]['closes'] += $closes;

			$items[] = array(
				'id'     => $id,
				'title'  => html_entity_decode( get_the_title( $id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'store'  => (string) get_post_meta( $id, '_tss_store', true ),
				'views'  => $views,
				'clicks' => $clicks,
				'closes' => $closes,
				'ignored'=> $ignored,
				'ctr'    => $views > 0 ? round( ( $clicks / $views ) * 100, 1 ) : 0,
			);
		}

		usort(
			$items,
			function ( $a, $b ) {
				return $b['views'] <=> $a['views'];
			}
		);

		$store_list = array_values( $stores );
		usort(
			$store_list,
			function ( $a, $b ) {
				return $b['clicks'] <=> $a['clicks'];
			}
		);

		return array(
			'totals' => $totals,
			'items'  => $items,
			'stores' => $store_list,
		);
	}

	public static function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'tikswipe-shop' ) );
		}

		$data   = self::gather();
		$totals = $data['totals'];
		$items  = $data['items'];
		$stores = $data['stores'];

		$pct = function ( $part, $whole ) {
			if ( $whole <= 0 ) {
				return 0;
			}
			return round( ( $part / $whole ) * 100, 1 );
		};

		// Top 10 lists for the charts.
		$top_views  = array_slice( $items, 0, 10 );
		$top_clicks = $items;
		usort(
			$top_clicks,
			function ( $a, $b ) {
				return $b['clicks'] <=> $a['clicks'];
			}
		);
		$top_clicks = array_slice( $top_clicks, 0, 10 );

		$payload = array(
			'totals'    => $totals,
			'topViews'  => $top_views,
			'topClicks' => $top_clicks,
			'stores'    => array_slice( $stores, 0, 10 ),
		);
		?>
		<div class="wrap tss-dashboard">
			<h1><?php esc_html_e( 'TikSwipe Shop — Dashboard', 'tikswipe-shop' ); ?></h1>

			<div class="tss-kpis">
				<div class="tss-kpi">
					<span class="tss-kpi__label"><?php esc_html_e( 'Views', 'tikswipe-shop' ); ?></span>
					<span class="tss-kpi__value"><?php echo esc_html( number_format_i18n( $totals['views'] ) ); ?></span>
				</div>
				<div class="tss-kpi tss-kpi--good">
					<span class="tss-kpi__label"><?php esc_html_e( 'Clicks (wanted)', 'tikswipe-shop' ); ?></span>
					<span class="tss-kpi__value"><?php echo esc_html( number_format_i18n( $totals['clicks'] ) ); ?></span>
					<span class="tss-kpi__pct"><?php echo esc_html( $pct( $totals['clicks'], $totals['views'] ) ); ?>%</span>
				</div>
				<div class="tss-kpi tss-kpi--bad">
					<span class="tss-kpi__label"><?php esc_html_e( 'Manual closes', 'tikswipe-shop' ); ?></span>
					<span class="tss-kpi__value"><?php echo esc_html( number_format_i18n( $totals['closes'] ) ); ?></span>
					<span class="tss-kpi__pct"><?php echo esc_html( $pct( $totals['closes'], $totals['views'] ) ); ?>%</span>
				</div>
				<div class="tss-kpi tss-kpi--muted">
					<span class="tss-kpi__label"><?php esc_html_e( 'Ignored', 'tikswipe-shop' ); ?></span>
					<span class="tss-kpi__value"><?php echo esc_html( number_format_i18n( $totals['ignored'] ) ); ?></span>
					<span class="tss-kpi__pct"><?php echo esc_html( $pct( $totals['ignored'], $totals['views'] ) ); ?>%</span>
				</div>
			</div>

			<div class="tss-grid">
				<div class="tss-card">
					<h2><?php esc_html_e( 'Engagement split', 'tikswipe-shop' ); ?></h2>
					<canvas id="tss-donut" height="240"></canvas>
				</div>
				<div class="tss-card">
					<h2><?php esc_html_e( 'Top products by views', 'tikswipe-shop' ); ?></h2>
					<canvas id="tss-top-views" height="240"></canvas>
				</div>
				<div class="tss-card">
					<h2><?php esc_html_e( 'Top products by clicks', 'tikswipe-shop' ); ?></h2>
					<canvas id="tss-top-clicks" height="240"></canvas>
				</div>
				<div class="tss-card">
					<h2><?php esc_html_e( 'Top stores by clicks', 'tikswipe-shop' ); ?></h2>
					<canvas id="tss-stores" height="240"></canvas>
				</div>
			</div>

			<div class="tss-card">
				<h2><?php esc_html_e( 'All products', 'tikswipe-shop' ); ?></h2>
				<table class="widefat striped tss-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Product', 'tikswipe-shop' ); ?></th>
							<th><?php esc_html_e( 'Store', 'tikswipe-shop' ); ?></th>
							<th><?php esc_html_e( 'Views', 'tikswipe-shop' ); ?></th>
							<th><?php esc_html_e( 'Clicks', 'tikswipe-shop' ); ?></th>
							<th><?php esc_html_e( 'CTR', 'tikswipe-shop' ); ?></th>
							<th><?php esc_html_e( 'Closes', 'tikswipe-shop' ); ?></th>
							<th><?php esc_html_e( 'Ignored', 'tikswipe-shop' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $it ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( get_edit_post_link( $it['id'] ) ); ?>"><?php echo esc_html( $it['title'] ); ?></a></td>
								<td><?php echo esc_html( $it['store'] !== '' ? $it['store'] : '—' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $it['views'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $it['clicks'] ) ); ?></td>
								<td><?php echo esc_html( $it['ctr'] ); ?>%</td>
								<td><?php echo esc_html( number_format_i18n( $it['closes'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $it['ignored'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if ( ! $items ) : ?>
							<tr><td colspan="7"><?php esc_html_e( 'No shop items yet.', 'tikswipe-shop' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<script>window.tssDash = <?php echo wp_json_encode( $payload ); ?>;</script>
		<?php
	}

	public static function enqueue_dashboard_assets( $hook ) {
		// $hook is like 'tss_shop_item_page_tss_dashboard'.
		if ( strpos( (string) $hook, self::HOOK ) === false ) {
			return;
		}
		wp_enqueue_style(
			'tss-dashboard',
			TSS_PLUGIN_URL . 'assets/css/tikswipe-shop-dashboard.css',
			array(),
			TSS_VERSION
		);
		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
			array(),
			'4.4.1',
			true
		);
		wp_enqueue_script(
			'tss-dashboard',
			TSS_PLUGIN_URL . 'assets/js/tikswipe-shop-dashboard.js',
			array( 'chartjs' ),
			TSS_VERSION,
			true
		);
	}
}

add_action( 'admin_enqueue_scripts', array( 'TSS_Dashboard', 'enqueue_dashboard_assets' ) );
