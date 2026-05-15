<?php
/**
 * Admin Dashboard page — date-filtered analytics with period comparison.
 *
 * Reads the events table (wp_tss_events) for date-window queries and falls
 * back to the lifetime post-meta counters for an "all time" panel. Renders
 * KPI tiles with delta-vs-previous-period, a daily time-series line chart,
 * a clicks/closes/ignored donut, top products + top stores bar charts, and
 * a per-product table.
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

	/**
	 * Aggregate counters from the events table within a date window.
	 *
	 * @param string $from  Y-m-d.
	 * @param string $to    Y-m-d.
	 * @return array<int,array{views:int,clicks:int,closes:int}> Keyed by item_id.
	 */
	private static function totals_by_item( $from, $to, $item_id = 0 ) {
		global $wpdb;
		$table   = TSS_Tracking::table_name();
		$item_id = (int) $item_id;
		if ( $item_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT item_id, event_type, COUNT(*) AS c
					FROM {$table}
					WHERE created_at >= %s AND created_at < %s AND item_id = %d
					GROUP BY item_id, event_type",
					$from . ' 00:00:00',
					date( 'Y-m-d', strtotime( $to . ' +1 day' ) ) . ' 00:00:00',
					$item_id
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT item_id, event_type, COUNT(*) AS c
					FROM {$table}
					WHERE created_at >= %s AND created_at < %s
					GROUP BY item_id, event_type",
					$from . ' 00:00:00',
					date( 'Y-m-d', strtotime( $to . ' +1 day' ) ) . ' 00:00:00'
				),
				ARRAY_A
			);
		}
		$out = array();
		foreach ( (array) $rows as $r ) {
			$id = (int) $r['item_id'];
			if ( ! isset( $out[ $id ] ) ) {
				$out[ $id ] = array( 'views' => 0, 'clicks' => 0, 'closes' => 0 );
			}
			$bucket = TSS_Tracking::EVENTS;
			if ( 'view'  === $r['event_type'] ) { $out[ $id ]['views']  = (int) $r['c']; }
			if ( 'click' === $r['event_type'] ) { $out[ $id ]['clicks'] = (int) $r['c']; }
			if ( 'close' === $r['event_type'] ) { $out[ $id ]['closes'] = (int) $r['c']; }
		}
		return $out;
	}

	/**
	 * Per-day counts of each event type inside the window.
	 *
	 * @return array<string,array{views:int,clicks:int,closes:int}> Keyed by Y-m-d.
	 */
	private static function per_day( $from, $to, $item_id = 0 ) {
		global $wpdb;
		$table   = TSS_Tracking::table_name();
		$item_id = (int) $item_id;
		if ( $item_id > 0 ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(created_at) AS day, event_type, COUNT(*) AS c
					FROM {$table}
					WHERE created_at >= %s AND created_at < %s AND item_id = %d
					GROUP BY day, event_type
					ORDER BY day",
					$from . ' 00:00:00',
					date( 'Y-m-d', strtotime( $to . ' +1 day' ) ) . ' 00:00:00',
					$item_id
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DATE(created_at) AS day, event_type, COUNT(*) AS c
					FROM {$table}
					WHERE created_at >= %s AND created_at < %s
					GROUP BY day, event_type
					ORDER BY day",
					$from . ' 00:00:00',
					date( 'Y-m-d', strtotime( $to . ' +1 day' ) ) . ' 00:00:00'
				),
				ARRAY_A
			);
		}
		$out = array();
		// Pre-fill every day in range with zeros for a continuous chart.
		$cursor = strtotime( $from );
		$end    = strtotime( $to );
		while ( $cursor <= $end ) {
			$out[ date( 'Y-m-d', $cursor ) ] = array( 'views' => 0, 'clicks' => 0, 'closes' => 0 );
			$cursor = strtotime( '+1 day', $cursor );
		}
		foreach ( (array) $rows as $r ) {
			$day = $r['day'];
			if ( ! isset( $out[ $day ] ) ) {
				$out[ $day ] = array( 'views' => 0, 'clicks' => 0, 'closes' => 0 );
			}
			if ( 'view'  === $r['event_type'] ) { $out[ $day ]['views']  = (int) $r['c']; }
			if ( 'click' === $r['event_type'] ) { $out[ $day ]['clicks'] = (int) $r['c']; }
			if ( 'close' === $r['event_type'] ) { $out[ $day ]['closes'] = (int) $r['c']; }
		}
		return $out;
	}

	/**
	 * Resolve a preset / custom range from GET params. Returns
	 * [from, to, label, preset_slug].
	 */
	private static function resolve_range() {
		$preset = isset( $_GET['range'] ) ? sanitize_key( $_GET['range'] ) : 'last_30';
		$tz     = wp_timezone();
		$now    = new DateTime( 'now', $tz );
		$today  = $now->format( 'Y-m-d' );

		$ranges = array(
			'today'      => array( $today, $today, __( 'Today', 'tikswipe-shop' ) ),
			'yesterday'  => array(
				( new DateTime( 'yesterday', $tz ) )->format( 'Y-m-d' ),
				( new DateTime( 'yesterday', $tz ) )->format( 'Y-m-d' ),
				__( 'Yesterday', 'tikswipe-shop' ),
			),
			'last_7'     => array(
				( new DateTime( '-6 days', $tz ) )->format( 'Y-m-d' ),
				$today,
				__( 'Last 7 days', 'tikswipe-shop' ),
			),
			'last_30'    => array(
				( new DateTime( '-29 days', $tz ) )->format( 'Y-m-d' ),
				$today,
				__( 'Last 30 days', 'tikswipe-shop' ),
			),
			'last_90'    => array(
				( new DateTime( '-89 days', $tz ) )->format( 'Y-m-d' ),
				$today,
				__( 'Last 90 days', 'tikswipe-shop' ),
			),
			'mtd'        => array(
				$now->format( 'Y-m-01' ),
				$today,
				__( 'Month to date', 'tikswipe-shop' ),
			),
			'last_month' => array(
				( new DateTime( 'first day of last month', $tz ) )->format( 'Y-m-d' ),
				( new DateTime( 'last day of last month', $tz ) )->format( 'Y-m-d' ),
				__( 'Last month', 'tikswipe-shop' ),
			),
		);

		if ( 'custom' === $preset ) {
			$from = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : $today;
			$to   = isset( $_GET['to'] )   ? sanitize_text_field( wp_unslash( $_GET['to'] ) )   : $today;
			// Validate / normalise.
			$from = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ? $from : $today;
			$to   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to )   ? $to   : $today;
			if ( strtotime( $from ) > strtotime( $to ) ) {
				$tmp = $from; $from = $to; $to = $tmp;
			}
			return array( $from, $to, __( 'Custom range', 'tikswipe-shop' ), 'custom' );
		}

		$r = isset( $ranges[ $preset ] ) ? $ranges[ $preset ] : $ranges['last_30'];
		return array( $r[0], $r[1], $r[2], $preset );
	}

	/**
	 * Previous comparable window (same length, ending immediately before $from).
	 */
	private static function previous_range( $from, $to ) {
		$days  = (int) ( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS ) + 1;
		$pTo   = date( 'Y-m-d', strtotime( $from . ' -1 day' ) );
		$pFrom = date( 'Y-m-d', strtotime( $pTo . ' -' . ( $days - 1 ) . ' days' ) );
		return array( $pFrom, $pTo );
	}

	private static function summarise( $by_item ) {
		$views = 0; $clicks = 0; $closes = 0;
		foreach ( $by_item as $row ) {
			$views  += $row['views'];
			$clicks += $row['clicks'];
			$closes += $row['closes'];
		}
		$ignored = max( 0, $views - $clicks );
		return compact( 'views', 'clicks', 'closes', 'ignored' );
	}

	private static function delta( $current, $previous ) {
		if ( $previous <= 0 ) {
			return $current > 0 ? 100.0 : 0.0;
		}
		return round( ( ( $current - $previous ) / $previous ) * 100, 1 );
	}

	public static function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'tikswipe-shop' ) );
		}

		$item_id = isset( $_GET['item_id'] ) ? (int) $_GET['item_id'] : 0;
		if ( $item_id > 0 && get_post_type( $item_id ) === TSS_CPT ) {
			self::render_solo_page( $item_id );
			return;
		}

		list( $from, $to, $range_label, $preset ) = self::resolve_range();
		$compare = ! empty( $_GET['compare'] );

		$by_item   = self::totals_by_item( $from, $to );
		$current   = self::summarise( $by_item );
		$per_day   = self::per_day( $from, $to );

		$prev_summary = array( 'views' => 0, 'clicks' => 0, 'closes' => 0, 'ignored' => 0 );
		if ( $compare ) {
			list( $pFrom, $pTo ) = self::previous_range( $from, $to );
			$prev_by_item        = self::totals_by_item( $pFrom, $pTo );
			$prev_summary        = self::summarise( $prev_by_item );
		}

		// Pull product metadata once.
		$query = new WP_Query(
			array(
				'post_type'      => TSS_CPT,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$items   = array();
		$stores  = array();
		$life    = array( 'views' => 0, 'clicks' => 0, 'closes' => 0, 'ignored' => 0 );

		foreach ( $query->posts as $id ) {
			$life_views  = (int) get_post_meta( $id, '_tss_views', true );
			$life_clicks = (int) get_post_meta( $id, '_tss_clicks', true );
			$life_closes = (int) get_post_meta( $id, '_tss_closes', true );
			$life['views']   += $life_views;
			$life['clicks']  += $life_clicks;
			$life['closes']  += $life_closes;

			$views  = isset( $by_item[ $id ] ) ? $by_item[ $id ]['views']  : 0;
			$clicks = isset( $by_item[ $id ] ) ? $by_item[ $id ]['clicks'] : 0;
			$closes = isset( $by_item[ $id ] ) ? $by_item[ $id ]['closes'] : 0;
			$ignored= max( 0, $views - $clicks );

			$store_raw = (string) get_post_meta( $id, '_tss_store', true );
			$store     = '' === $store_raw ? __( '(no store)', 'tikswipe-shop' ) : $store_raw;
			if ( ! isset( $stores[ $store ] ) ) {
				$stores[ $store ] = array( 'name' => $store, 'views' => 0, 'clicks' => 0, 'closes' => 0 );
			}
			$stores[ $store ]['views']  += $views;
			$stores[ $store ]['clicks'] += $clicks;
			$stores[ $store ]['closes'] += $closes;

			$items[] = array(
				'id'           => $id,
				'title'        => html_entity_decode( get_the_title( $id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'store'        => $store_raw,
				'views'        => $views,
				'clicks'       => $clicks,
				'closes'       => $closes,
				'ignored'      => $ignored,
				'ctr'          => $views > 0 ? round( ( $clicks / $views ) * 100, 1 ) : 0,
				'life_views'   => $life_views,
				'life_clicks'  => $life_clicks,
				'life_closes'  => $life_closes,
				'solo_url'     => add_query_arg(
					array(
						'post_type' => TSS_CPT,
						'page'      => self::HOOK,
						'item_id'   => $id,
						'range'     => $preset,
						'from'      => $from,
						'to'        => $to,
						'compare'   => $compare ? 1 : null,
					),
					admin_url( 'edit.php' )
				),
			);
		}
		$life['ignored'] = max( 0, $life['views'] - $life['clicks'] );

		// Sort and build top-N lists.
		usort( $items, function ( $a, $b ) { return $b['views']  <=> $a['views']; } );
		$top_views = array_slice( $items, 0, 10 );
		$top_clicks = $items;
		usort( $top_clicks, function ( $a, $b ) { return $b['clicks'] <=> $a['clicks']; } );
		$top_clicks = array_slice( $top_clicks, 0, 10 );
		$store_list = array_values( $stores );
		usort( $store_list, function ( $a, $b ) { return $b['clicks'] <=> $a['clicks']; } );

		$payload = array(
			'current'   => $current,
			'previous'  => $prev_summary,
			'compare'   => $compare,
			'perDay'    => $per_day,
			'topViews'  => $top_views,
			'topClicks' => $top_clicks,
			'stores'    => array_slice( $store_list, 0, 10 ),
			'life'      => $life,
		);

		$pct = function ( $part, $whole ) {
			return $whole > 0 ? round( ( $part / $whole ) * 100, 1 ) : 0;
		};

		$base_url = admin_url( 'edit.php?post_type=' . TSS_CPT . '&page=' . self::HOOK );
		?>
		<div class="wrap tss-dashboard">
			<h1><?php esc_html_e( 'TikSwipe Shop — Dashboard', 'tikswipe-shop' ); ?></h1>

			<form method="get" class="tss-filter-bar">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( TSS_CPT ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::HOOK ); ?>">
				<label class="tss-filter__field">
					<span><?php esc_html_e( 'Range', 'tikswipe-shop' ); ?></span>
					<select name="range" class="tss-range-select">
						<?php
						$opts = array(
							'today'      => __( 'Today', 'tikswipe-shop' ),
							'yesterday'  => __( 'Yesterday', 'tikswipe-shop' ),
							'last_7'     => __( 'Last 7 days', 'tikswipe-shop' ),
							'last_30'    => __( 'Last 30 days', 'tikswipe-shop' ),
							'last_90'    => __( 'Last 90 days', 'tikswipe-shop' ),
							'mtd'        => __( 'Month to date', 'tikswipe-shop' ),
							'last_month' => __( 'Last month', 'tikswipe-shop' ),
							'custom'     => __( 'Custom…', 'tikswipe-shop' ),
						);
						foreach ( $opts as $v => $label ) :
							?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $preset, $v ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="tss-filter__field tss-filter__custom">
					<span><?php esc_html_e( 'From', 'tikswipe-shop' ); ?></span>
					<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>">
				</label>
				<label class="tss-filter__field tss-filter__custom">
					<span><?php esc_html_e( 'To', 'tikswipe-shop' ); ?></span>
					<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>">
				</label>
				<label class="tss-filter__field tss-filter__compare">
					<input type="checkbox" name="compare" value="1" <?php checked( $compare ); ?>>
					<span><?php esc_html_e( 'Compare to previous period', 'tikswipe-shop' ); ?></span>
				</label>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'tikswipe-shop' ); ?></button>
				<span class="tss-filter__summary">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: range label, 2: from, 3: to */
							__( '%1$s — %2$s → %3$s', 'tikswipe-shop' ),
							$range_label,
							$from,
							$to
						)
					);
					?>
				</span>
			</form>

			<div class="tss-kpis">
				<?php
				$kpis = array(
					array( 'label' => __( 'Views', 'tikswipe-shop' ), 'value' => $current['views'], 'prev' => $prev_summary['views'], 'good' => true, 'mod' => '' ),
					array( 'label' => __( 'Clicks (wanted)', 'tikswipe-shop' ), 'value' => $current['clicks'], 'prev' => $prev_summary['clicks'], 'good' => true, 'mod' => 'good', 'pct' => $pct( $current['clicks'], $current['views'] ) ),
					array( 'label' => __( 'Manual closes', 'tikswipe-shop' ), 'value' => $current['closes'], 'prev' => $prev_summary['closes'], 'good' => false, 'mod' => 'bad', 'pct' => $pct( $current['closes'], $current['views'] ) ),
					array( 'label' => __( 'Ignored', 'tikswipe-shop' ), 'value' => $current['ignored'], 'prev' => $prev_summary['ignored'], 'good' => false, 'mod' => 'muted', 'pct' => $pct( $current['ignored'], $current['views'] ) ),
				);
				foreach ( $kpis as $k ) :
					$delta_pct = self::delta( $k['value'], $k['prev'] );
					$delta_up  = $delta_pct >= 0;
					// For "good" metrics up is good (green); for "bad" metrics up is bad (red).
					$delta_color_good = ( $k['good'] && $delta_up ) || ( ! $k['good'] && ! $delta_up );
					?>
					<div class="tss-kpi tss-kpi--<?php echo esc_attr( $k['mod'] ); ?>">
						<span class="tss-kpi__label"><?php echo esc_html( $k['label'] ); ?></span>
						<span class="tss-kpi__value"><?php echo esc_html( number_format_i18n( $k['value'] ) ); ?></span>
						<?php if ( isset( $k['pct'] ) ) : ?>
							<span class="tss-kpi__pct"><?php echo esc_html( $k['pct'] ); ?>% <?php esc_html_e( 'of views', 'tikswipe-shop' ); ?></span>
						<?php endif; ?>
						<?php if ( $compare ) : ?>
							<span class="tss-kpi__delta <?php echo $delta_color_good ? 'is-good' : 'is-bad'; ?>">
								<?php echo $delta_up ? '▲' : '▼'; ?>
								<?php echo esc_html( ( $delta_up ? '+' : '' ) . $delta_pct ); ?>%
								<small><?php esc_html_e( 'vs prev', 'tikswipe-shop' ); ?></small>
							</span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="tss-card">
				<h2><?php esc_html_e( 'Daily activity', 'tikswipe-shop' ); ?></h2>
				<canvas id="tss-timeseries" height="220"></canvas>
			</div>

			<div class="tss-grid">
				<div class="tss-card">
					<h2><?php esc_html_e( 'Engagement split', 'tikswipe-shop' ); ?></h2>
					<canvas id="tss-donut" height="220"></canvas>
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

			<div class="tss-card tss-lifetime">
				<h2><?php esc_html_e( 'All-time totals (since plugin install)', 'tikswipe-shop' ); ?></h2>
				<div class="tss-life-grid">
					<div><strong><?php echo esc_html( number_format_i18n( $life['views'] ) ); ?></strong> <span><?php esc_html_e( 'Views', 'tikswipe-shop' ); ?></span></div>
					<div><strong><?php echo esc_html( number_format_i18n( $life['clicks'] ) ); ?></strong> <span><?php esc_html_e( 'Clicks', 'tikswipe-shop' ); ?></span></div>
					<div><strong><?php echo esc_html( number_format_i18n( $life['closes'] ) ); ?></strong> <span><?php esc_html_e( 'Closes', 'tikswipe-shop' ); ?></span></div>
					<div><strong><?php echo esc_html( number_format_i18n( $life['ignored'] ) ); ?></strong> <span><?php esc_html_e( 'Ignored', 'tikswipe-shop' ); ?></span></div>
				</div>
			</div>

			<div class="tss-card">
				<h2><?php esc_html_e( 'Per-product breakdown (selected range)', 'tikswipe-shop' ); ?></h2>
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
							<th><?php esc_html_e( 'All-time views', 'tikswipe-shop' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $items as $it ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $it['solo_url'] ); ?>" class="row-title" title="<?php esc_attr_e( 'Open product analytics', 'tikswipe-shop' ); ?>"><strong><?php echo esc_html( $it['title'] ); ?></strong></a></td>
								<td><?php echo esc_html( $it['store'] !== '' ? $it['store'] : '—' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $it['views'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $it['clicks'] ) ); ?></td>
								<td><?php echo esc_html( $it['ctr'] ); ?>%</td>
								<td><?php echo esc_html( number_format_i18n( $it['closes'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $it['ignored'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $it['life_views'] ) ); ?></td>
								<td><a href="<?php echo esc_url( get_edit_post_link( $it['id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'tikswipe-shop' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
						<?php if ( ! $items ) : ?>
							<tr><td colspan="9"><?php esc_html_e( 'No shop items yet.', 'tikswipe-shop' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<script>window.tssDash = <?php echo wp_json_encode( $payload ); ?>;</script>
		<?php
	}

	public static function render_solo_page( $item_id ) {
		list( $from, $to, $range_label, $preset ) = self::resolve_range();
		$compare = ! empty( $_GET['compare'] );

		$by_item = self::totals_by_item( $from, $to, $item_id );
		$current = isset( $by_item[ $item_id ] )
			? array_merge( $by_item[ $item_id ], array( 'ignored' => max( 0, $by_item[ $item_id ]['views'] - $by_item[ $item_id ]['clicks'] ) ) )
			: array( 'views' => 0, 'clicks' => 0, 'closes' => 0, 'ignored' => 0 );
		$per_day = self::per_day( $from, $to, $item_id );

		$prev_summary = array( 'views' => 0, 'clicks' => 0, 'closes' => 0, 'ignored' => 0 );
		if ( $compare ) {
			list( $pFrom, $pTo ) = self::previous_range( $from, $to );
			$prev_by_item        = self::totals_by_item( $pFrom, $pTo, $item_id );
			if ( isset( $prev_by_item[ $item_id ] ) ) {
				$prev_summary           = $prev_by_item[ $item_id ];
				$prev_summary['ignored'] = max( 0, $prev_summary['views'] - $prev_summary['clicks'] );
			}
		}

		$title       = html_entity_decode( get_the_title( $item_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$store       = (string) get_post_meta( $item_id, '_tss_store', true );
		$price       = (string) get_post_meta( $item_id, '_tss_price', true );
		$image_url   = tss_get_image_url( $item_id );
		$button_url  = (string) get_post_meta( $item_id, '_tss_button_url', true );
		$affiliate   = (string) get_post_meta( $item_id, '_tss_affiliate_url', true );
		$final_url   = $button_url ? $button_url : $affiliate;
		$life_views  = (int) get_post_meta( $item_id, '_tss_views', true );
		$life_clicks = (int) get_post_meta( $item_id, '_tss_clicks', true );
		$life_closes = (int) get_post_meta( $item_id, '_tss_closes', true );
		$life_ignored = max( 0, $life_views - $life_clicks );

		$target_post_ids = array_filter( array_map( 'intval', (array) get_post_meta( $item_id, '_tss_target_post_ids', true ) ) );
		$target_cats     = array_filter( array_map( 'intval', (array) get_post_meta( $item_id, '_tss_target_categories', true ) ) );
		$cat_names       = array();
		foreach ( $target_cats as $cid ) {
			$t = get_term( $cid, 'category' );
			if ( $t && ! is_wp_error( $t ) ) {
				$cat_names[] = $t->name;
			}
		}

		$payload = array(
			'current'  => $current,
			'previous' => $prev_summary,
			'compare'  => $compare,
			'perDay'   => $per_day,
			'solo'     => true,
		);

		$pct = function ( $part, $whole ) {
			return $whole > 0 ? round( ( $part / $whole ) * 100, 1 ) : 0;
		};

		$dash_url = admin_url( 'edit.php?post_type=' . TSS_CPT . '&page=' . self::HOOK );
		?>
		<div class="wrap tss-dashboard tss-dashboard--solo">
			<h1>
				<a href="<?php echo esc_url( $dash_url ); ?>" class="tss-back">&larr; <?php esc_html_e( 'Dashboard', 'tikswipe-shop' ); ?></a>
				<?php echo esc_html( $title ); ?>
			</h1>

			<div class="tss-solo-header">
				<?php if ( $image_url ) : ?>
					<div class="tss-solo-thumb"><img src="<?php echo esc_url( $image_url ); ?>" alt=""></div>
				<?php endif; ?>
				<div class="tss-solo-meta">
					<div class="tss-solo-row">
						<span class="tss-solo-label"><?php esc_html_e( 'Store', 'tikswipe-shop' ); ?></span>
						<span class="tss-solo-value"><?php echo esc_html( $store !== '' ? $store : '—' ); ?></span>
					</div>
					<div class="tss-solo-row">
						<span class="tss-solo-label"><?php esc_html_e( 'Price', 'tikswipe-shop' ); ?></span>
						<span class="tss-solo-value"><?php echo esc_html( $price !== '' ? $price : '—' ); ?></span>
					</div>
					<div class="tss-solo-row">
						<span class="tss-solo-label"><?php esc_html_e( 'Targets', 'tikswipe-shop' ); ?></span>
						<span class="tss-solo-value">
							<?php
							$bits = array();
							if ( $target_post_ids ) {
								$bits[] = sprintf( _n( '%d post', '%d posts', count( $target_post_ids ), 'tikswipe-shop' ), count( $target_post_ids ) );
							}
							if ( $cat_names ) {
								$bits[] = sprintf(
									_n( '%d category (%s)', '%d categories (%s)', count( $cat_names ), 'tikswipe-shop' ),
									count( $cat_names ),
									implode( ', ', $cat_names )
								);
							}
							echo esc_html( $bits ? implode( ' · ', $bits ) : '—' );
							?>
						</span>
					</div>
					<div class="tss-solo-actions">
						<a class="button" href="<?php echo esc_url( get_edit_post_link( $item_id ) ); ?>"><?php esc_html_e( 'Edit product', 'tikswipe-shop' ); ?></a>
						<?php if ( $final_url ) : ?>
							<a class="button" href="<?php echo esc_url( $final_url ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Open Buy URL', 'tikswipe-shop' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<form method="get" class="tss-filter-bar">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( TSS_CPT ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::HOOK ); ?>">
				<input type="hidden" name="item_id" value="<?php echo esc_attr( $item_id ); ?>">
				<label class="tss-filter__field">
					<span><?php esc_html_e( 'Range', 'tikswipe-shop' ); ?></span>
					<select name="range" class="tss-range-select">
						<?php
						$opts = array(
							'today'      => __( 'Today', 'tikswipe-shop' ),
							'yesterday'  => __( 'Yesterday', 'tikswipe-shop' ),
							'last_7'     => __( 'Last 7 days', 'tikswipe-shop' ),
							'last_30'    => __( 'Last 30 days', 'tikswipe-shop' ),
							'last_90'    => __( 'Last 90 days', 'tikswipe-shop' ),
							'mtd'        => __( 'Month to date', 'tikswipe-shop' ),
							'last_month' => __( 'Last month', 'tikswipe-shop' ),
							'custom'     => __( 'Custom…', 'tikswipe-shop' ),
						);
						foreach ( $opts as $v => $label ) :
							?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $preset, $v ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="tss-filter__field tss-filter__custom">
					<span><?php esc_html_e( 'From', 'tikswipe-shop' ); ?></span>
					<input type="date" name="from" value="<?php echo esc_attr( $from ); ?>">
				</label>
				<label class="tss-filter__field tss-filter__custom">
					<span><?php esc_html_e( 'To', 'tikswipe-shop' ); ?></span>
					<input type="date" name="to" value="<?php echo esc_attr( $to ); ?>">
				</label>
				<label class="tss-filter__field tss-filter__compare">
					<input type="checkbox" name="compare" value="1" <?php checked( $compare ); ?>>
					<span><?php esc_html_e( 'Compare to previous period', 'tikswipe-shop' ); ?></span>
				</label>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'tikswipe-shop' ); ?></button>
				<span class="tss-filter__summary"><?php echo esc_html( $range_label . ' — ' . $from . ' → ' . $to ); ?></span>
			</form>

			<div class="tss-kpis">
				<?php
				$kpis = array(
					array( 'label' => __( 'Views', 'tikswipe-shop' ),         'value' => $current['views'],   'prev' => $prev_summary['views'],   'good' => true,  'mod' => '' ),
					array( 'label' => __( 'Clicks (wanted)', 'tikswipe-shop' ), 'value' => $current['clicks'],  'prev' => $prev_summary['clicks'],  'good' => true,  'mod' => 'good', 'pct' => $pct( $current['clicks'], $current['views'] ) ),
					array( 'label' => __( 'Manual closes', 'tikswipe-shop' ),   'value' => $current['closes'],  'prev' => $prev_summary['closes'],  'good' => false, 'mod' => 'bad',  'pct' => $pct( $current['closes'], $current['views'] ) ),
					array( 'label' => __( 'Ignored', 'tikswipe-shop' ),         'value' => $current['ignored'], 'prev' => $prev_summary['ignored'], 'good' => false, 'mod' => 'muted','pct' => $pct( $current['ignored'], $current['views'] ) ),
				);
				foreach ( $kpis as $k ) :
					$delta_pct = self::delta( $k['value'], $k['prev'] );
					$delta_up  = $delta_pct >= 0;
					$delta_color_good = ( $k['good'] && $delta_up ) || ( ! $k['good'] && ! $delta_up );
					?>
					<div class="tss-kpi tss-kpi--<?php echo esc_attr( $k['mod'] ); ?>">
						<span class="tss-kpi__label"><?php echo esc_html( $k['label'] ); ?></span>
						<span class="tss-kpi__value"><?php echo esc_html( number_format_i18n( $k['value'] ) ); ?></span>
						<?php if ( isset( $k['pct'] ) ) : ?>
							<span class="tss-kpi__pct"><?php echo esc_html( $k['pct'] ); ?>% <?php esc_html_e( 'of views', 'tikswipe-shop' ); ?></span>
						<?php endif; ?>
						<?php if ( $compare ) : ?>
							<span class="tss-kpi__delta <?php echo $delta_color_good ? 'is-good' : 'is-bad'; ?>">
								<?php echo $delta_up ? '▲' : '▼'; ?>
								<?php echo esc_html( ( $delta_up ? '+' : '' ) . $delta_pct ); ?>%
								<small><?php esc_html_e( 'vs prev', 'tikswipe-shop' ); ?></small>
							</span>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="tss-card">
				<h2><?php esc_html_e( 'Daily activity', 'tikswipe-shop' ); ?></h2>
				<canvas id="tss-timeseries" height="220"></canvas>
			</div>

			<div class="tss-grid">
				<div class="tss-card">
					<h2><?php esc_html_e( 'Engagement split', 'tikswipe-shop' ); ?></h2>
					<canvas id="tss-donut" height="220"></canvas>
				</div>
				<div class="tss-card tss-lifetime">
					<h2><?php esc_html_e( 'All-time totals for this product', 'tikswipe-shop' ); ?></h2>
					<div class="tss-life-grid">
						<div><strong><?php echo esc_html( number_format_i18n( $life_views ) ); ?></strong> <span><?php esc_html_e( 'Views', 'tikswipe-shop' ); ?></span></div>
						<div><strong><?php echo esc_html( number_format_i18n( $life_clicks ) ); ?></strong> <span><?php esc_html_e( 'Clicks', 'tikswipe-shop' ); ?></span></div>
						<div><strong><?php echo esc_html( number_format_i18n( $life_closes ) ); ?></strong> <span><?php esc_html_e( 'Closes', 'tikswipe-shop' ); ?></span></div>
						<div><strong><?php echo esc_html( number_format_i18n( $life_ignored ) ); ?></strong> <span><?php esc_html_e( 'Ignored', 'tikswipe-shop' ); ?></span></div>
					</div>
				</div>
			</div>
		</div>

		<script>window.tssDash = <?php echo wp_json_encode( $payload ); ?>;</script>
		<?php
	}

	public static function enqueue_dashboard_assets( $hook ) {
		if ( strpos( (string) $hook, self::HOOK ) === false ) {
			return;
		}
		wp_enqueue_style(
			'tss-dashboard',
			TSS_PLUGIN_URL . 'assets/css/tikswipe-shop-dashboard.css',
			array(),
			tss_asset_ver( 'assets/css/tikswipe-shop-dashboard.css' )
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
			tss_asset_ver( 'assets/js/tikswipe-shop-dashboard.js' ),
			true
		);
	}
}

add_action( 'admin_enqueue_scripts', array( 'TSS_Dashboard', 'enqueue_dashboard_assets' ) );
