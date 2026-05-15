<?php

add_filter( 'wps_paywall_premium_badge', 'pwll_premium_badge', 10, 2 );
/**
 * Display premium badge or empty string if post is not premium.
 *
 * @param string $content The default content to be displayed.
 * @param int    $post_id Post ID.
 * @param array  $params Array of params to apply to the badge (style).
 *
 * @return string The premium badge or empty string if post is not premium.
 */
function pwll_premium_badge( $content, $post_id = null, $params = array() ) {

	unset( $content );

	// return empty string when post ID is not an integer.
	if ( ! is_int( $post_id ) ) {
		return '';
	}

	// Return empty string when post ID is less than or equal to 0.
	if ( $post_id <= 0 ) {
		return '';
	}

	// Return empty string when post is not premium.
	$premium_post = get_post_meta( $post_id, 'pwll_post_status', true );
	if ( 'premium' !== $premium_post ) {
		return '';
	}

	// Return buffer when user has premium access.
	$has_premium_access = get_user_meta( get_current_user_id(), '_has_premium_access', true );
	if ( 'on' === $has_premium_access ) {
		return '';
	}

	$badge_icon     = xbox_get_field_value( 'pwll-options', 'pwll-badge-icon', 'lock' );
	$badge_icon_svg = '';
	switch ( $badge_icon ) {
		case 'lock':
			$badge_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"><path fill="#ffffff" d="M18 10v-4c0-3.313-2.687-6-6-6s-6 2.687-6 6v4h-3v14h18v-14h-3zm-10 0v-4c0-2.206 1.794-4 4-4s4 1.794 4 4v4h-8z"/></svg>';
			break;
		case 'star':
			$badge_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"><path fill="#ffffff" d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z"/></svg>';
			break;
	}
	$badge_text = xbox_get_field_value( 'pwll-options', 'pwll-badge-text', 'Premium' );

	$style = isset( $params['style'] ) ? $params['style'] : '';

	return '<div class="pwll-badge" style="' . esc_attr( $style ) . '"><span class="badge-icon">' . $badge_icon_svg . '</span><span class="badge-text">' . $badge_text . '</span></div>';
}

add_filter( 'wps_paywall_media_content', 'pwll_media_content_locker', 10, 2 );
/**
 * Display default content or media content locker.
 *
 * @param string $content The default content to be displayed.
 * @param int    $post_id Post ID.
 *
 * @return string Default content when post is not premium or when the user has premium access, otherwise the media content locker.
 */
function pwll_media_content_locker( $content, $post_id ) {

	// Return default content when content is not a string.
	if ( ! is_string( $content ) ) {
		return $content;
	}

	// Return default content when post ID is not an integer.
	if ( ! is_int( $post_id ) ) {
		return $content;
	}

	// Return default content when post ID is less than or equal to 0.
	if ( $post_id <= 0 ) {
		return $content;
	}

	// Return default content when post is not premium.
	$premium_post = get_post_meta( $post_id, 'pwll_post_status', true );
	if ( 'premium' !== $premium_post ) {
		return $content;
	}

	// Return default content when user has premium access.
	$has_premium_access = get_user_meta( get_current_user_id(), '_has_premium_access', true );
	if ( 'on' === $has_premium_access ) {
		return $content;
	}

	// Return default content when admin navigates as premium member.
	$pwll_navigate_as_premium_member = xbox_get_field_value( 'pwll-options', 'pwll-navigate-as-premium-member', 'off' );
	if ( 'on' === $pwll_navigate_as_premium_member && current_user_can( 'manage_options' ) ) {
		return $content;
	}

	$unlock_button_text = xbox_get_field_value( 'pwll-options', 'pwll-locked-content-area-text', 'Unlock Video' );
	$lock_icon_svg      = '<svg viewBox="0 0 100 100"><path y="50"class="lock-top" d="M64,50V18.7C64,12,58.9,6.6,52.6,6.6h-3.5c-6.3,0-11.3,5.4-11.3,12.1v25.9"/><circle class="lock-outline" cx="50.9" cy="65.4" r="27" /><path class="lock-body" d="M50.9,41.4c-13.2,0-24,10.7-24,24c0,13.2,10.7,24,24,24c13.2,0,24-10.7,24-24C74.9,52.2,64.1,41.4,50.9,41.4z M56.2,61.9 c-1.1,1.5-1.3,3-1.3,4.8c0.1,3,0.1,6.1,0,9.1c-0.1,2.8-1.6,4.4-4,4.5c-2.5,0.1-4.3-1.6-4.5-4.4c-0.1-1.9,0-3.9,0-5.8c0,0,0,0,0,0 c0-1.4,0.1-2.8,0-4.2c-0.2-1.3-0.5-2.7-1.2-3.8c-1.5-2.7-1.1-6.3,1.1-8.3c2.4-2.2,6-2.3,8.6-0.2C57.3,55.5,58,59.2,56.2,61.9z"/><path class="lock-spinner" d="M73.3,65.7c0,12.2-9.9,22.1-22.1,22.1"><animateTransform attributeType="xml" attributeName="transform" type="rotate" from="0 50.9 65.4" to="360 50.9 65.4" dur="0.8s" repeatCount="indefinite"/></path></svg>';

	$pwll_thumb_url = '';
	if ( has_post_thumbnail() && wp_get_attachment_url( get_post_thumbnail_id(), 'wpst_thumb_large' ) ) {
		$pwll_thumb_url = get_the_post_thumbnail_url( get_the_ID() );
	} elseif ( '' !== get_post_meta( get_the_ID(), 'thumb', true ) ) {
		$pwll_thumb_url = get_post_meta( get_the_ID(), 'thumb', true );
	}

	if ( ! empty( $pwll_thumb_url ) ) {
		$post_bg_img = 'style="background-image: url(' . $pwll_thumb_url . ');"';
	}

	return '<div class="premium-video-bg" ' . $post_bg_img . '>
        <button class="open-pwll-box locked-button locked-button-large">' . $lock_icon_svg . $unlock_button_text . '</button>
    </div>';
}
