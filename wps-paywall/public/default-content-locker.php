<?php
add_filter( 'get_the_excerpt', 'pwll_premium_excerpt' );
function pwll_premium_excerpt( $excerpt ) {
	if ( ! is_archive() && ! is_home() && ! is_front_page() ) {
		return;
	}
	global $post;
	$premium_post                    = get_post_meta( $post->ID, 'pwll_post_status', true );
	$unlock_button_text              = xbox_get_field_value( 'pwll-options', 'pwll-locked-content-area-text', 'Unlock Video' );
	$current_user_id                 = get_current_user_id();
	$has_premium_access              = 'on' === get_user_meta( $current_user_id, '_has_premium_access', true );
	$pwll_navigate_as_premium_member = xbox_get_field_value( 'pwll-options', 'pwll-navigate-as-premium-member', 'off' );
	if ( $pwll_navigate_as_premium_member && current_user_can( 'administrator' ) ) {
		$has_premium_access = true;
	}
	if ( ! $premium_post || $has_premium_access ) {
		return $excerpt;
	}
	$lock_icon     = xbox_get_field_value( 'pwll-options', 'pwll-badge-icon', 'lock' );
	$lock_icon_svg = '';
	switch ( $lock_icon ) {
		case 'lock':
			$lock_icon_svg = /*'<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" style="position:relative;top:-1px;"><path fill="inherit" d="M18 10v-4c0-3.313-2.687-6-6-6s-6 2.687-6 6v4h-3v14h18v-14h-3zm-10 0v-4c0-2.206 1.794-4 4-4s4 1.794 4 4v4h-8z"/></svg>'*/'<svg viewBox="0 0 100 100"><path y="50"class="lock-top" d="M64,50V18.7C64,12,58.9,6.6,52.6,6.6h-3.5c-6.3,0-11.3,5.4-11.3,12.1v25.9"/>
			<circle class="lock-outline" cx="50.9" cy="65.4" r="27" />
			<path class="lock-body" d="M50.9,41.4c-13.2,0-24,10.7-24,24c0,13.2,10.7,24,24,24c13.2,0,24-10.7,24-24C74.9,52.2,64.1,41.4,50.9,41.4z M56.2,61.9
			  c-1.1,1.5-1.3,3-1.3,4.8c0.1,3,0.1,6.1,0,9.1c-0.1,2.8-1.6,4.4-4,4.5c-2.5,0.1-4.3-1.6-4.5-4.4c-0.1-1.9,0-3.9,0-5.8c0,0,0,0,0,0
			  c0-1.4,0.1-2.8,0-4.2c-0.2-1.3-0.5-2.7-1.2-3.8c-1.5-2.7-1.1-6.3,1.1-8.3c2.4-2.2,6-2.3,8.6-0.2C57.3,55.5,58,59.2,56.2,61.9z"/>
			 <path class="lock-spinner" d="M73.3,65.7c0,12.2-9.9,22.1-22.1,22.1">
			   <animateTransform attributeType="xml"
								 attributeName="transform"
								 type="rotate"
								 from="0 50.9 65.4"
								 to="360 50.9 65.4"
								 dur="0.8s"
								 repeatCount="indefinite"/>
		   </path>
		  </svg>';
			break;
		case 'star':
			$lock_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" style="position:relative;top:-1px;"><path fill="inherit" d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279-7.416-3.967-7.417 3.967 1.481-8.279-6.064-5.828 8.332-1.151z"/></svg>';
			break;
	}
	return '';
}

add_filter( 'post_thumbnail_html', 'pwll_premium_featured_image', 10, 3 );
function pwll_premium_featured_image( $html, $post_id, $post_image_id ) {
	$premium_post                    = get_post_meta( $post_id, 'pwll_post_status', true );
	$current_user_id                 = get_current_user_id();
	$has_premium_access              = 'on' === get_user_meta( $current_user_id, '_has_premium_access', true );
	$pwll_navigate_as_premium_member = xbox_get_field_value( 'pwll-options', 'pwll-navigate-as-premium-member', 'off' );
	if ( $pwll_navigate_as_premium_member === 'on' && current_user_can( 'administrator' ) ) {
		$has_premium_access = true;
	}
	if ( ! $premium_post || $has_premium_access ) {
		return $html;
	}
	if ( ! is_single() ) {
		return str_replace( 'wp-post-image', 'wp-post-image premium-badge', $html );
	}
	return $html;
}

add_filter( 'the_content', 'pwll_premium_content', -1 );
function pwll_premium_content( $content ) {
	global $post;
	$premium_post                    = get_post_meta( $post->ID, 'pwll_post_status', true );
	$current_user_id                 = get_current_user_id();
	$has_premium_access              = 'on' === get_user_meta( $current_user_id, '_has_premium_access', true );
	$pwll_navigate_as_premium_member = xbox_get_field_value( 'pwll-options', 'pwll-navigate-as-premium-member', 'off' );
	if ( $pwll_navigate_as_premium_member === 'on' && current_user_can( 'administrator' ) ) {
		$has_premium_access = true;
	}
	if ( ! $premium_post || $has_premium_access ) {
		return $content;
	}
	$unlock_button_text = xbox_get_field_value( 'pwll-options', 'pwll-locked-content-area-text', 'Unlock Video' );
	$lock_icon_svg      = '<svg viewBox="0 0 100 100"><path y="50"class="lock-top" d="M64,50V18.7C64,12,58.9,6.6,52.6,6.6h-3.5c-6.3,0-11.3,5.4-11.3,12.1v25.9"/><circle class="lock-outline" cx="50.9" cy="65.4" r="27" /><path class="lock-body" d="M50.9,41.4c-13.2,0-24,10.7-24,24c0,13.2,10.7,24,24,24c13.2,0,24-10.7,24-24C74.9,52.2,64.1,41.4,50.9,41.4z M56.2,61.9 c-1.1,1.5-1.3,3-1.3,4.8c0.1,3,0.1,6.1,0,9.1c-0.1,2.8-1.6,4.4-4,4.5c-2.5,0.1-4.3-1.6-4.5-4.4c-0.1-1.9,0-3.9,0-5.8c0,0,0,0,0,0 c0-1.4,0.1-2.8,0-4.2c-0.2-1.3-0.5-2.7-1.2-3.8c-1.5-2.7-1.1-6.3,1.1-8.3c2.4-2.2,6-2.3,8.6-0.2C57.3,55.5,58,59.2,56.2,61.9z"/><path class="lock-spinner" d="M73.3,65.7c0,12.2-9.9,22.1-22.1,22.1"><animateTransform attributeType="xml" attributeName="transform" type="rotate" from="0 50.9 65.4" to="360 50.9 65.4"	 dur="0.8s" repeatCount="indefinite"/></path></svg>';
	if ( has_post_thumbnail() && wp_get_attachment_url( get_post_thumbnail_id() ) ) {
		$thumb_url = get_the_post_thumbnail_url( get_the_id() );
		return '<div class="premium-video-bg" style="background-image: url(' . $thumb_url . ');"><button class="open-pwll-box locked-button locked-button-large">' . $lock_icon_svg . $unlock_button_text . '</button></div>';
	} else {
		return '<button class="open-pwll-box locked-button locked-button-large">' . $lock_icon_svg . $unlock_button_text . '</button>';
	}
}
