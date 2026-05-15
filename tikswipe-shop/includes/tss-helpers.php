<?php
/**
 * Shared helpers.
 *
 * @package TikSwipe_Shop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Curated list of Dashicons to pick from. Slug => human label.
 * Slug is what's stored / rendered as `dashicons-<slug>`.
 *
 * @return array<string,string>
 */
function tss_dashicon_choices() {
	return array(
		'cart'              => 'Cart',
		'store'             => 'Store',
		'tag'               => 'Tag',
		'tickets-alt'       => 'Ticket',
		'money-alt'         => 'Money',
		'products'          => 'Products',
		'star-filled'       => 'Star',
		'awards'            => 'Award',
		'heart'             => 'Heart',
		'thumbs-up'         => 'Thumbs up',
		'yes-alt'           => 'Check',
		'clock'             => 'Clock',
		'info'              => 'Info',
		'warning'           => 'Warning',
		'megaphone'         => 'Megaphone',
		'lightbulb'         => 'Lightbulb',
		'arrow-up-alt'      => 'Arrow up',
		'arrow-down-alt'    => 'Arrow down',
		'flag'              => 'Flag',
		'cover-image'       => 'Image',
		'admin-customizer'  => 'Sparkle',
		'controls-volumeon' => 'Sound',
		'video-alt3'        => 'Video',
		'visibility'        => 'Eye',
	);
}

/**
 * Resolve product image URL: uploaded attachment wins over URL field.
 *
 * @param int $item_id Shop item ID.
 * @return string
 */
function tss_get_image_url( $item_id ) {
	$attachment_id = (int) get_post_meta( $item_id, '_tss_image_id', true );
	if ( $attachment_id ) {
		$url = wp_get_attachment_image_url( $attachment_id, 'medium' );
		if ( $url ) {
			return $url;
		}
	}
	return (string) get_post_meta( $item_id, '_tss_image_url', true );
}

/**
 * Resolve the URL of the tag's custom icon image (if any).
 *
 * @param int $item_id Shop item ID.
 * @return string
 */
function tss_get_tag_icon_url( $item_id ) {
	$attachment_id = (int) get_post_meta( $item_id, '_tss_tag_icon_id', true );
	if ( $attachment_id ) {
		$url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		if ( $url ) {
			return $url;
		}
	}
	return (string) get_post_meta( $item_id, '_tss_tag_icon_url', true );
}

/**
 * Inline SVG markup for each curated Dashicon. Used by the frontend so the
 * tag icon renders even when the dashicons font isn't loaded (optimizers
 * often strip it from non-admin pages, leaving a missing-glyph square).
 *
 * @param string $slug Dashicon slug.
 * @param int    $size Pixels.
 * @return string SVG element or empty string if no match.
 */
function tss_inline_icon_svg( $slug, $size = 14 ) {
	$paths = array(
		'cart'              => '<path d="M3 4a1 1 0 0 1 1-1h2.2a1.5 1.5 0 0 1 1.46 1.16L8 6h12.5a1 1 0 0 1 .97 1.26l-2 7A1 1 0 0 1 18.5 15H9.4l-.34 1.5H19a1 1 0 0 1 0 2H8a1 1 0 0 1-.98-1.22L8.5 11 6.7 5H4a1 1 0 0 1-1-1Zm6 17.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm9 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z"/>',
		'store'             => '<path d="M4.4 4h15.2l1.3 4.05a2.7 2.7 0 0 1-5 2.05 2.7 2.7 0 0 1-4.95 0 2.7 2.7 0 0 1-4.95 0A2.7 2.7 0 0 1 1 8.05L2.3 4ZM4 11.86c.6.3 1.3.45 2 .43.95 0 1.85-.28 2.5-.78.65.5 1.55.78 2.5.78s1.85-.28 2.5-.78c.65.5 1.55.78 2.5.78.7.02 1.4-.13 2-.43V20H4v-8.14Zm3 1.64h6v4H7v-4Z"/>',
		'tag'               => '<path d="M11.6 2.4 21 11.8a2 2 0 0 1 0 2.8L13.6 22a2 2 0 0 1-2.8 0L1.4 12.6A2 2 0 0 1 .8 11l.4-7a2 2 0 0 1 2-2l7-.4a2 2 0 0 1 1.4.6ZM6.5 8a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/>',
		'tickets-alt'       => '<path d="M2 7a1 1 0 0 1 1-1h18a1 1 0 0 1 1 1v3a2 2 0 0 0 0 4v3a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-3a2 2 0 0 0 0-4V7Zm7 1.5a.5.5 0 0 0-.5.5v6a.5.5 0 0 0 1 0V9a.5.5 0 0 0-.5-.5Z"/>',
		'money-alt'         => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm.9 14.7v1.1a.9.9 0 1 1-1.8 0v-1.1a4 4 0 0 1-3-2.6.9.9 0 0 1 1.7-.55c.4 1 1.3 1.65 2.4 1.65 1.3 0 2.1-.6 2.1-1.5 0-.9-.7-1.3-2.4-1.7-2-.5-3.6-1.2-3.6-3.2 0-1.5 1.05-2.7 2.8-3v-1.1a.9.9 0 1 1 1.8 0v1.1c1.4.2 2.4 1 2.8 2.2a.9.9 0 0 1-1.7.55c-.3-.8-1-1.25-2-1.25-1.2 0-1.9.55-1.9 1.35 0 .85.7 1.2 2.3 1.6 2.1.55 3.7 1.25 3.7 3.3 0 1.65-1.1 2.85-3.2 3.15Z"/>',
		'products'          => '<path d="M3 6h18v2H3V6Zm1 4h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V10Zm5-7h6a1 1 0 0 1 1 1v1H8V4a1 1 0 0 1 1-1Zm.5 9a1 1 0 0 0 0 2h5a1 1 0 1 0 0-2h-5Z"/>',
		'star-filled'       => '<path d="M12 2.5a.8.8 0 0 1 .72.46l2.7 5.66 6.16.88a.8.8 0 0 1 .45 1.37l-4.5 4.4 1.07 6.16a.8.8 0 0 1-1.18.85L12 19.4l-5.42 2.88a.8.8 0 0 1-1.18-.85l1.07-6.15-4.5-4.4a.8.8 0 0 1 .45-1.38l6.16-.88 2.7-5.66A.8.8 0 0 1 12 2.5Z"/>',
		'awards'            => '<path d="M5 3h14v3a5 5 0 0 1-3.5 4.77A4.5 4.5 0 0 1 13 13.92V17h2.5a.5.5 0 0 1 .5.5V19h2v2H6v-2h2v-1.5a.5.5 0 0 1 .5-.5H11v-3.08a4.5 4.5 0 0 1-2.5-3.15A5 5 0 0 1 5 6V3Zm0 2v1a3 3 0 0 0 1.5 2.6V5H5Zm12.5 0v3.6A3 3 0 0 0 19 6V5h-1.5Z"/>',
		'heart'             => '<path d="M12 21s-7.5-4.5-9.5-9A5.5 5.5 0 0 1 12 6.5 5.5 5.5 0 0 1 21.5 12c-2 4.5-9.5 9-9.5 9Z"/>',
		'thumbs-up'         => '<path d="M2 11a2 2 0 0 1 2-2h2v12H4a2 2 0 0 1-2-2v-8Zm6-2 4-7c1.5 0 3 1 3 3v3h5a2 2 0 0 1 2 2.2L20.7 19a2 2 0 0 1-2 1.8H8V9Z"/>',
		'yes-alt'           => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm5 7.3-6.3 7a.9.9 0 0 1-1.35.05L6.5 13.4a.9.9 0 0 1 1.3-1.25l2.15 2.2 5.65-6.3A.9.9 0 0 1 17 9.3Z"/>',
		'clock'             => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm.9 5.5a.9.9 0 0 0-1.8 0v5l.05.3.15.25 3 3a.9.9 0 0 0 1.3-1.3l-2.7-2.7v-4.55Z"/>',
		'info'              => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 3.5a1.3 1.3 0 1 1 0 2.6 1.3 1.3 0 0 1 0-2.6Zm1.5 11.5h-3a.7.7 0 0 1 0-1.4h.8v-3.6h-.6a.7.7 0 0 1 0-1.4h1.5a.7.7 0 0 1 .7.7v4.3h.6a.7.7 0 0 1 0 1.4Z"/>',
		'warning'           => '<path d="M11.13 3.05a1 1 0 0 1 1.74 0l9.8 17a1 1 0 0 1-.87 1.5H2.2a1 1 0 0 1-.87-1.5l9.8-17ZM12 9a.9.9 0 0 0-.9.9v4.3a.9.9 0 0 0 1.8 0V9.9A.9.9 0 0 0 12 9Zm0 8.6a1.1 1.1 0 1 0 0-2.2 1.1 1.1 0 0 0 0 2.2Z"/>',
		'megaphone'         => '<path d="M3 9a3 3 0 0 1 3-3h2l9-3v18l-9-3H6a3 3 0 0 1-3-3V9Zm5 6.5v3a1.5 1.5 0 0 0 3 0V16l-3-.5Z"/>',
		'lightbulb'         => '<path d="M12 2a7 7 0 0 0-4 12.7V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.3A7 7 0 0 0 12 2ZM9 20a1 1 0 0 1 1-1h4a1 1 0 0 1 0 2v.5A1.5 1.5 0 0 1 12.5 23h-1A1.5 1.5 0 0 1 10 21.5V21a1 1 0 0 1-1-1Z"/>',
		'arrow-up-alt'      => '<path d="M12 3a1 1 0 0 1 .7.3l7 7a1 1 0 0 1-1.4 1.4L13 6.4V20a1 1 0 0 1-2 0V6.4l-5.3 5.3a1 1 0 0 1-1.4-1.4l7-7A1 1 0 0 1 12 3Z"/>',
		'arrow-down-alt'    => '<path d="M12 21a1 1 0 0 1-.7-.3l-7-7a1 1 0 0 1 1.4-1.4l5.3 5.3V4a1 1 0 0 1 2 0v13.6l5.3-5.3a1 1 0 0 1 1.4 1.4l-7 7A1 1 0 0 1 12 21Z"/>',
		'flag'              => '<path d="M5 3a1 1 0 0 1 1 1v16a1 1 0 0 1-2 0V4a1 1 0 0 1 1-1Zm3 1h11l-2 4 2 4H8V4Z"/>',
		'cover-image'       => '<path d="M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5Zm12 3.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3ZM5 18l4-5 3 3 4-4 3 3v3H5Z"/>',
		'admin-customizer'  => '<path d="M13.6 3.4a2 2 0 0 1 2.8 0l4.2 4.2a2 2 0 0 1 0 2.8L10.4 21.6a2 2 0 0 1-1.4.6H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 .6-1.4L13.6 3.4Zm-1.4 4.2 4.2 4.2 2.5-2.5-4.2-4.2-2.5 2.5Z"/>',
		'controls-volumeon' => '<path d="M3 9v6h4l5 5V4L7 9H3Zm12 3a4 4 0 0 0-2-3.5v7A4 4 0 0 0 15 12Zm-2-7v2a5 5 0 0 1 0 10v2a7 7 0 0 0 0-14Z"/>',
		'video-alt3'        => '<path d="M3 4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4Zm6 4v8l7-4-7-4Z"/>',
		'visibility'        => '<path d="M12 5C6.5 5 2.5 9 1 12c1.5 3 5.5 7 11 7s9.5-4 11-7c-1.5-3-5.5-7-11-7Zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-6a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/>',
	);

	if ( ! isset( $paths[ $slug ] ) ) {
		return '';
	}
	return sprintf(
		'<svg class="tss-svg-icon" viewBox="0 0 24 24" width="%1$d" height="%1$d" fill="currentColor" aria-hidden="true">%2$s</svg>',
		(int) $size,
		$paths[ $slug ]
	);
}

/**
 * Map of dashicon slug => raw SVG inner paths. Used by the admin preview
 * JS so the preview matches what the frontend renders.
 *
 * @return array<string,string>
 */
function tss_dashicon_svg_map() {
	$map = array();
	foreach ( array_keys( tss_dashicon_choices() ) as $slug ) {
		$svg = tss_inline_icon_svg( $slug, 14 );
		if ( '' !== $svg ) {
			$map[ $slug ] = $svg;
		}
	}
	return $map;
}

/**
 * Build the HTML for the tag's icon. Custom image > inline SVG (from
 * Dashicon slug) > empty.
 *
 * @param int $item_id Shop item ID.
 * @return string Safe HTML.
 */
function tss_render_tag_icon( $item_id ) {
	$custom = tss_get_tag_icon_url( $item_id );
	if ( $custom ) {
		return '<img class="tss-tag-icon-img" src="' . esc_url( $custom ) . '" alt="" />';
	}
	$dashicon = (string) get_post_meta( $item_id, '_tss_tag_dashicon', true );
	if ( $dashicon ) {
		$choices = tss_dashicon_choices();
		if ( isset( $choices[ $dashicon ] ) ) {
			return tss_inline_icon_svg( $dashicon, 14 );
		}
	}
	return '';
}

/**
 * Find a published shop item that targets the given post and pick a winner.
 *
 * Two tiers, in priority order:
 *  1. Items whose "Target posts" explicitly include this post id.
 *  2. Items whose "Target categories" overlap the post's categories.
 *
 * If a tier has multiple matches, tss_pick_winner() chooses one using the
 * configured selection strategy (Thompson sampling by default). The
 * lower-priority tier is only consulted if the higher tier is empty.
 *
 * @param int $post_id Post being viewed.
 * @return int|null Shop item ID or null.
 */
function tss_find_item_for_post( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id ) {
		return null;
	}

	$query = new WP_Query(
		array(
			'post_type'      => TSS_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	if ( empty( $query->posts ) ) {
		return null;
	}

	$post_categories = wp_get_post_categories( $post_id );

	$by_post = array();
	$by_cat  = array();

	foreach ( $query->posts as $item_id ) {
		$target_post_ids = (array) get_post_meta( $item_id, '_tss_target_post_ids', true );
		$target_post_ids = array_filter( array_map( 'intval', $target_post_ids ) );

		if ( $target_post_ids && in_array( $post_id, $target_post_ids, true ) ) {
			$by_post[] = (int) $item_id;
			continue;
		}

		if ( $post_categories ) {
			$target_categories = (array) get_post_meta( $item_id, '_tss_target_categories', true );
			$target_categories = array_filter( array_map( 'intval', $target_categories ) );
			if ( $target_categories && array_intersect( $post_categories, $target_categories ) ) {
				$by_cat[] = (int) $item_id;
			}
		}
	}

	$strategy     = get_option( 'tss_strategy', 'thompson' );
	$window_days  = (int) get_option( 'tss_window_days', 30 );
	$close_weight = (float) get_option( 'tss_close_weight', 1.5 );

	if ( $by_post ) {
		return tss_pick_winner( $by_post, $strategy, $window_days, $close_weight );
	}
	if ( $by_cat ) {
		return tss_pick_winner( $by_cat, $strategy, $window_days, $close_weight );
	}
	return null;
}

/**
 * Pick one item id out of a list of candidates using the requested strategy.
 *
 * @param int[]  $candidate_ids
 * @param string $strategy     thompson | weighted_ctr | random | recent
 * @param int    $window_days  Days of event history to consider (0 = lifetime).
 * @param float  $close_weight Multiplier applied to closes as a negative signal.
 * @return int|null
 */
function tss_pick_winner( $candidate_ids, $strategy = 'thompson', $window_days = 30, $close_weight = 1.5 ) {
	$candidate_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $candidate_ids ) ) ) );
	if ( ! $candidate_ids ) {
		return null;
	}
	if ( 1 === count( $candidate_ids ) ) {
		return $candidate_ids[0];
	}

	if ( 'random' === $strategy ) {
		return $candidate_ids[ array_rand( $candidate_ids ) ];
	}

	if ( 'recent' === $strategy ) {
		$ids = get_posts(
			array(
				'post_type'      => TSS_CPT,
				'post_status'    => 'publish',
				'post__in'       => $candidate_ids,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		return $ids ? (int) $ids[0] : $candidate_ids[0];
	}

	$stats = tss_get_window_stats( $candidate_ids, $window_days );

	if ( 'weighted_ctr' === $strategy ) {
		$weights = array();
		$total   = 0.0;
		foreach ( $candidate_ids as $id ) {
			$s            = $stats[ $id ];
			$non          = max( 0, $s['views'] - $s['clicks'] ) + ( $close_weight * $s['closes'] );
			// Smoothed CTR with Beta(2, 8) prior (≈ 0.2 baseline).
			$w            = ( $s['clicks'] + 2 ) / ( $s['clicks'] + $non + 10 );
			$weights[ $id ] = $w;
			$total       += $w;
		}
		if ( $total <= 0 ) {
			return $candidate_ids[ array_rand( $candidate_ids ) ];
		}
		$r   = ( mt_rand() / mt_getrandmax() ) * $total;
		$acc = 0.0;
		foreach ( $weights as $id => $w ) {
			$acc += $w;
			if ( $r <= $acc ) {
				return (int) $id;
			}
		}
		return $candidate_ids[0];
	}

	// Default: Thompson sampling.
	$best_id    = $candidate_ids[0];
	$best_score = -INF;
	foreach ( $candidate_ids as $id ) {
		$s      = $stats[ $id ];
		$clicks = max( 0, $s['clicks'] );
		$non    = max( 0, $s['views'] - $s['clicks'] ) + ( $close_weight * $s['closes'] );
		$score  = tss_random_beta( $clicks + 1, $non + 1 );
		if ( $score > $best_score ) {
			$best_score = $score;
			$best_id    = $id;
		}
	}
	return (int) $best_id;
}

/**
 * Fetch (views, clicks, closes) per candidate within the time window,
 * falling back to lifetime post-meta counters for items with no events
 * inside the window.
 *
 * @param int[] $item_ids
 * @param int   $window_days 0 = use lifetime only.
 * @return array<int,array{views:int,clicks:int,closes:int}>
 */
function tss_get_window_stats( $item_ids, $window_days ) {
	$item_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $item_ids ) ) ) );
	$out      = array();
	foreach ( $item_ids as $id ) {
		$out[ $id ] = array( 'views' => 0, 'clicks' => 0, 'closes' => 0 );
	}
	if ( ! $item_ids ) {
		return $out;
	}

	if ( $window_days > 0 && class_exists( 'TSS_Tracking' ) ) {
		global $wpdb;
		$table        = TSS_Tracking::table_name();
		$placeholders = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );
		$since        = date( 'Y-m-d H:i:s', strtotime( '-' . (int) $window_days . ' days' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT item_id, event_type, COUNT(*) AS c
			FROM {$table}
			WHERE item_id IN ( $placeholders ) AND created_at >= %s
			GROUP BY item_id, event_type";
		$params   = array_merge( $item_ids, array( $since ) );
		$prepared = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $params ) );
		$rows     = $wpdb->get_results( $prepared, ARRAY_A );
		foreach ( (array) $rows as $r ) {
			$id = (int) $r['item_id'];
			if ( ! isset( $out[ $id ] ) ) {
				continue;
			}
			if ( 'view'  === $r['event_type'] ) { $out[ $id ]['views']  = (int) $r['c']; }
			if ( 'click' === $r['event_type'] ) { $out[ $id ]['clicks'] = (int) $r['c']; }
			if ( 'close' === $r['event_type'] ) { $out[ $id ]['closes'] = (int) $r['c']; }
		}
	}

	// Items with zero data in the window fall back to lifetime totals so a
	// new candidate that simply hasn't received traffic recently still has
	// a baseline to compete with.
	foreach ( $out as $id => $row ) {
		if ( 0 === ( $row['views'] + $row['clicks'] + $row['closes'] ) ) {
			$out[ $id ]['views']  = (int) get_post_meta( $id, '_tss_views', true );
			$out[ $id ]['clicks'] = (int) get_post_meta( $id, '_tss_clicks', true );
			$out[ $id ]['closes'] = (int) get_post_meta( $id, '_tss_closes', true );
		}
	}

	return $out;
}

/**
 * Standard-normal random sample via Box-Muller.
 */
function tss_random_normal() {
	$u1 = max( 1e-12, mt_rand() / mt_getrandmax() );
	$u2 = mt_rand() / mt_getrandmax();
	return sqrt( -2 * log( $u1 ) ) * cos( 2 * M_PI * $u2 );
}

/**
 * Gamma-distributed sample (Marsaglia-Tsang for shape >= 1; boost trick
 * for shape < 1).
 */
function tss_random_gamma( $shape ) {
	if ( $shape <= 0 ) {
		return 0.0;
	}
	if ( $shape < 1 ) {
		$u = max( 1e-12, mt_rand() / mt_getrandmax() );
		return tss_random_gamma( $shape + 1 ) * pow( $u, 1 / $shape );
	}
	$d = $shape - 1.0 / 3.0;
	$c = 1.0 / sqrt( 9.0 * $d );
	for ( $i = 0; $i < 200; $i++ ) {
		$x = tss_random_normal();
		$v = pow( 1 + $c * $x, 3 );
		if ( $v <= 0 ) {
			continue;
		}
		$u = max( 1e-12, mt_rand() / mt_getrandmax() );
		if ( $u < 1 - 0.0331 * pow( $x, 4 ) ) {
			return $d * $v;
		}
		if ( log( $u ) < 0.5 * $x * $x + $d * ( 1 - $v + log( $v ) ) ) {
			return $d * $v;
		}
	}
	return $d;
}

/**
 * Beta-distributed sample via two gamma deviates.
 */
function tss_random_beta( $a, $b ) {
	$x = tss_random_gamma( $a );
	$y = tss_random_gamma( $b );
	if ( ( $x + $y ) <= 0 ) {
		return 0.5;
	}
	return $x / ( $x + $y );
}

/**
 * Build the public payload for a shop item.
 *
 * @param int $item_id Shop item ID.
 * @return array|null
 */
function tss_get_item_payload( $item_id ) {
	$item_id = (int) $item_id;
	if ( ! $item_id || get_post_type( $item_id ) !== TSS_CPT ) {
		return null;
	}

	// Decode entities so smart quotes / dashes from wp_texturize render as
	// real UTF-8 in the card (the JS will safely re-escape).
	$title       = html_entity_decode( get_the_title( $item_id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$description = (string) get_post_meta( $item_id, '_tss_description', true );
	$button_url  = (string) get_post_meta( $item_id, '_tss_button_url', true );
	$affiliate   = (string) get_post_meta( $item_id, '_tss_affiliate_url', true );
	$price       = (string) get_post_meta( $item_id, '_tss_price', true );
	$position    = (string) get_post_meta( $item_id, '_tss_position_label', true );
	$image_url   = tss_get_image_url( $item_id );

	$tag_label = (string) get_post_meta( $item_id, '_tss_tag_label', true );
	$tag_icon  = tss_render_tag_icon( $item_id );

	$final_link = $button_url ? $button_url : $affiliate;

	return array(
		'id'         => $item_id,
		'title'      => $title,
		'description'=> $description,
		'button_url' => esc_url_raw( $final_link ),
		'price'      => $price,
		'position'   => $position ? $position : '01',
		'image_url'  => esc_url_raw( $image_url ),
		'tag'        => array(
			'label'    => $tag_label,
			'icon_html'=> $tag_icon,
		),
	);
}
