<?php
/**
 * Shared helpers.
 *
 * @package TikSwipe_Shop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve the image URL for a shop item, preferring uploaded attachment over
 * the raw URL field.
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
 * Same for the badge icon image.
 *
 * @param int $item_id Shop item ID.
 * @return string
 */
function tss_get_badge_icon_url( $item_id ) {
	$attachment_id = (int) get_post_meta( $item_id, '_tss_badge_icon_id', true );
	if ( $attachment_id ) {
		$url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		if ( $url ) {
			return $url;
		}
	}
	return (string) get_post_meta( $item_id, '_tss_badge_icon_url', true );
}

/**
 * Find the first published shop item targeting the given post.
 *
 * Targets are matched as: explicit post IDs first, then category IDs.
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

	$by_post     = null;
	$by_category = null;

	foreach ( $query->posts as $item_id ) {
		$target_post_ids = (array) get_post_meta( $item_id, '_tss_target_post_ids', true );
		$target_post_ids = array_filter( array_map( 'intval', $target_post_ids ) );

		if ( $target_post_ids && in_array( $post_id, $target_post_ids, true ) ) {
			$by_post = $item_id;
			break;
		}

		if ( null === $by_category && $post_categories ) {
			$target_categories = (array) get_post_meta( $item_id, '_tss_target_categories', true );
			$target_categories = array_filter( array_map( 'intval', $target_categories ) );
			if ( $target_categories && array_intersect( $post_categories, $target_categories ) ) {
				$by_category = $item_id;
			}
		}
	}

	if ( $by_post ) {
		return $by_post;
	}
	return $by_category;
}

/**
 * Build the public payload for a shop item, used by the REST endpoint.
 *
 * @param int $item_id Shop item ID.
 * @return array|null
 */
function tss_get_item_payload( $item_id ) {
	$item_id = (int) $item_id;
	if ( ! $item_id || get_post_type( $item_id ) !== TSS_CPT ) {
		return null;
	}

	$title       = get_the_title( $item_id );
	$description = (string) get_post_meta( $item_id, '_tss_description', true );
	$button_url  = (string) get_post_meta( $item_id, '_tss_button_url', true );
	$affiliate   = (string) get_post_meta( $item_id, '_tss_affiliate_url', true );
	$price       = (string) get_post_meta( $item_id, '_tss_price', true );
	$shipping    = (string) get_post_meta( $item_id, '_tss_shipping_label', true );
	$position    = (string) get_post_meta( $item_id, '_tss_position_label', true );
	$image_url   = tss_get_image_url( $item_id );

	$badge_name  = (string) get_post_meta( $item_id, '_tss_badge_name', true );
	$badge_icon  = tss_get_badge_icon_url( $item_id );
	$badge_color = (string) get_post_meta( $item_id, '_tss_badge_color', true );

	$final_link = $button_url ? $button_url : $affiliate;

	return array(
		'id'          => $item_id,
		'title'       => $title,
		'description' => $description,
		'button_url'  => esc_url_raw( $final_link ),
		'price'       => $price,
		'shipping'    => $shipping ? $shipping : __( 'Free shipping', 'tikswipe-shop' ),
		'position'    => $position ? $position : '01',
		'image_url'   => esc_url_raw( $image_url ),
		'badge'       => array(
			'name'  => $badge_name,
			'icon'  => esc_url_raw( $badge_icon ),
			'color' => $badge_color ? $badge_color : '#22c55e',
		),
	);
}
