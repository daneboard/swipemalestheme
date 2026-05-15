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
 * Build the HTML for the tag's icon. Custom image > Dashicon > empty.
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
			return '<span class="dashicons dashicons-' . esc_attr( $dashicon ) . '" aria-hidden="true"></span>';
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
