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
 * Find the first published shop item targeting the given post.
 * Explicit post IDs win over category matches.
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

	return $by_post ? $by_post : $by_category;
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

	$title       = get_the_title( $item_id );
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
