<?php
	global $post;
	$author_id       = $post->post_author;
	$author_username = get_the_author_meta( 'user_login', $author_id );
	$the_content     = get_the_content();
	$upload_dir      = wp_upload_dir();
	$upload_dir_url  = $upload_dir['baseurl'];

	$user_fav_posts = array();
if ( is_user_logged_in() ) {
	$user_fav_posts = (array) get_user_meta( get_current_user_id(), 'wpst_favorite_posts', true );
} elseif ( isset( $_COOKIE['msfav'] ) && ! empty( $_COOKIE['msfav'] ) ) {
		$user_fav_posts = unserialize( $_COOKIE['msfav'] );
}

	/** PROFILE AVATAR */
	$profile_avatar_basename = esc_html( get_user_meta( $author_id, '_author_profile_avatar_basename', true ) );
	$profile_avatar          = '';
if ( isset( $profile_avatar_basename ) && ! empty( $profile_avatar_basename ) ) {
	$profile_avatar = '<img src="' . $upload_dir_url . $profile_avatar_basename . '" width="36" height="36">';
} else {
	$profile_avatar = '<svg width="36" height="36" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd"><path fill="#ffffff" d="M12 0c6.623 0 12 5.377 12 12s-5.377 12-12 12-12-5.377-12-12 5.377-12 12-12zm8.127 19.41c-.282-.401-.772-.654-1.624-.85-3.848-.906-4.097-1.501-4.352-2.059-.259-.565-.19-1.23.205-1.977 1.726-3.257 2.09-6.024 1.027-7.79-.674-1.119-1.875-1.734-3.383-1.734-1.521 0-2.732.626-3.409 1.763-1.066 1.789-.693 4.544 1.049 7.757.402.742.476 1.406.22 1.974-.265.586-.611 1.19-4.365 2.066-.852.196-1.342.449-1.623.848 2.012 2.207 4.91 3.592 8.128 3.592s6.115-1.385 8.127-3.59zm.65-.782c1.395-1.844 2.223-4.14 2.223-6.628 0-6.071-4.929-11-11-11s-11 4.929-11 11c0 2.487.827 4.783 2.222 6.626.409-.452 1.049-.81 2.049-1.041 2.025-.462 3.376-.836 3.678-1.502.122-.272.061-.628-.188-1.087-1.917-3.535-2.282-6.641-1.03-8.745.853-1.431 2.408-2.251 4.269-2.251 1.845 0 3.391.808 4.24 2.218 1.251 2.079.896 5.195-1 8.774-.245.463-.304.821-.179 1.094.305.668 1.644 1.038 3.667 1.499 1 .23 1.64.59 2.049 1.043z"/></svg>';
}

	// Collect all tags (categories + tags) for the +N logic
	$postcats        = get_the_category();
	$posttags_all    = get_the_tags();
	$all_terms       = array();
	$current_term    = get_queried_object();
	$current_term_id = '';
if ( null !== $current_term && property_exists( $current_term, 'term_id' ) ) {
	$current_term_id = $current_term->term_id;
}
if ( $postcats ) {
	foreach ( $postcats as $cat ) {
		$all_terms[] = array(
			'term_id' => $cat->term_id,
			'name'    => $cat->name,
			'count'   => $cat->count,
			'url'     => get_category_link( $cat->term_id ),
		);
	}
}
if ( $posttags_all ) {
	foreach ( $posttags_all as $tag ) {
		$all_terms[] = array(
			'term_id' => $tag->term_id,
			'name'    => $tag->name,
			'count'   => $tag->count,
			'url'     => get_tag_link( $tag->term_id ),
		);
	}
}

$is_fav = in_array( $post->ID, $user_fav_posts );
?>

<div class="wpst-fullscreen-controls">
	<button class="wpst-zoom-toggle"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="#ffffff" d="M15 3l2.3 2.3-2.89 2.87 1.42 1.42L18.7 6.7 21 9V3h-6zM3 9l2.3-2.3 2.87 2.89 1.42-1.42L6.7 5.3 9 3H3v6zm6 12l-2.3-2.3 2.89-2.87-1.42-1.42L5.3 17.3 3 15v6h6zm12-6l-2.3 2.3-2.87-2.89-1.42 1.42 2.89 2.87L15 21h6v-6z"/></svg></button>
	<button class="close-fullscreen"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="#ffffff" d="m12 10.93 5.719-5.72c.146-.146.339-.219.531-.219.404 0 .75.324.75.749 0 .193-.073.385-.219.532l-5.72 5.719 5.719 5.719c.147.147.22.339.22.531 0 .427-.349.75-.75.75-.192 0-.385-.073-.531-.219l-5.719-5.719-5.719 5.719c-.146.146-.339.219-.531.219-.401 0-.75-.323-.75-.75 0-.192.073-.384.22-.531l5.719-5.719-5.72-5.719c-.146-.147-.219-.339-.219-.532 0-.425.346-.749.75-.749.192 0 .385.073.531.219z"/></svg></button>
</div>

<div class="single-content-infos">
	<?php if ( ! empty( wpst_get_video_duration() ) ) : ?>
		<div class="post-datas">
			<div class="post-duration"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" style="position: relative; top: 1px;"><path fill="#ffffff" d="M3 22v-20l18 10-18 10z"/></svg> <?php echo wpst_get_video_duration(); ?></div>
		</div>
	<?php endif; ?>
	<?php if ( is_single() ) : ?>
		<h1><?php the_title(); ?></h1>
	<?php else : ?>
		<h2><?php the_title(); ?></h2>
	<?php endif; ?>
	<?php
	// Description kept in DOM but expand button hidden via CSS
	if ( ! empty( $the_content ) ) {
		echo '<div class="post-desc"><p>' . $the_content . '</p><a class="see-desc" href="#!"><svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 24 24" width="30" height="30" xmlns="http://www.w3.org/2000/svg"><path fill="#ffffff" d="m15 17.75c0-.414-.336-.75-.75-.75h-11.5c-.414 0-.75.336-.75.75s.336.75.75.75h11.5c.414 0 .75-.336.75-.75zm7-4c0-.414-.336-.75-.75-.75h-18.5c-.414 0-.75.336-.75.75s.336.75.75.75h18.5c.414 0 .75-.336.75-.75zm0-4c0-.414-.336-.75-.75-.75h-18.5c-.414 0-.75.336-.75.75s.336.75.75.75h18.5c.414 0 .75-.336.75-.75zm0-4c0-.414-.336-.75-.75-.75h-18.5c-.414 0-.75.336-.75.75s.336.75.75.75h18.5c.414 0 .75-.336.75-.75z" fill-rule="nonzero"/></svg></a></div>';
	}
	?>

	<?php if ( ! empty( $all_terms ) ) : ?>
		<div class="tags-list">
			<?php
			$total = count( $all_terms );
			foreach ( $all_terms as $i => $term ) :
				$is_active   = ( $term['term_id'] === $current_term_id );
				$href        = $is_active ? esc_url( home_url( '/' ) ) : esc_url( $term['url'] );
				$hidden      = ( $i >= 3 ) ? ' style="display:none;"' : '';
				$extra_class = $is_active ? 'active' : '';
				if ( $i >= 3 ) {
					$extra_class .= ( $extra_class ? ' ' : '' ) . 'wpst-tag-hidden';
				}
				?>
				<a<?php echo $extra_class ? ' class="' . $extra_class . '"' : ''; ?> href="<?php echo $href; ?>" title="<?php echo esc_attr( $term['name'] ); ?>"<?php echo $hidden; ?>><?php echo esc_html( $term['name'] ); ?><small><?php echo $term['count']; ?></small></a>
			<?php endforeach; ?>
			<?php if ( $total > 3 ) : ?>
				<a class="wpst-tags-more" href="#!">+<?php echo $total - 3; ?></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

<div class="swiper-side">
	<a href="#!" class="enlight-content"><svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24"><path fill="#ffffff" d="M24 9h-2v-7h-7v-2h9v9zm-9 15v-2h7v-7h2v9h-9zm-15-9h2v7h7v2h-9v-9zm9-15v2h-7v7h-2v-9h9z"/></svg></a>
	<?php if ( 'video' === get_post_format() ) : ?>
		<a href="#!" class="wpst-mute-toggle" title="<?php esc_attr_e( 'Mute / Unmute', 'tikswipe' ); ?>">
			<svg class="wpst-icon-muted" xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24"><path fill="#ffffff" d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/><line x1="2" y1="2" x2="22" y2="22" stroke="#ffffff" stroke-width="2"/></svg>
			<svg class="wpst-icon-unmuted" xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" style="display:none;"><path fill="#ffffff" d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/></svg>
		</a>
	<?php endif; ?>
	<?php if ( get_theme_mod( 'wpst_enable_creators', '' ) === true ) : ?>
		<?php if ( $author_id == 1 ) : ?>
			<a class="avatar-img" href="<?php echo esc_url( home_url( '/?view=profile' ) ); ?>"><?php echo $profile_avatar; ?></a>
		<?php else : ?>
			<a class="avatar-img" href="<?php echo esc_url( home_url( '/' . $author_username ) ); ?>" title="@<?php echo $author_username; ?>"><?php echo $profile_avatar; ?></a>
		<?php endif; ?>
	<?php endif; ?>
	<a class="add-to-fav <?php echo $is_fav ? 'fav-added' : 'add-fav'; ?>" href="#!">
		<?php if ( $is_fav ) : ?>
			<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="3.9 4.9 17.2 16.2"><path d="M17 16C15.8 17.3235 12.5 20.5 12.5 20.5C12.5 20.5 9.2 17.3235 8 16C5.2 12.9118 4.5 11.7059 4.5 9.5C4.5 7.29412 6.1 5.5 8.5 5.5C10.5 5.5 11.7 6.82353 12.5 8.14706C13.3 6.82353 14.5 5.5 16.5 5.5C18.9 5.5 20.5 7.29412 20.5 9.5C20.5 11.7059 19.8 12.9118 17 16Z" fill="#ffffff" stroke="#ffffff" stroke-width="1.2"/></svg>
		<?php else : ?>
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" width="28" height="28" viewBox="3.9 4.9 17.2 16.2"><path d="M17 16C15.8 17.3235 12.5 20.5 12.5 20.5C12.5 20.5 9.2 17.3235 8 16C5.2 12.9118 4.5 11.7059 4.5 9.5C4.5 7.29412 6.1 5.5 8.5 5.5C10.5 5.5 11.7 6.82353 12.5 8.14706C13.3 6.82353 14.5 5.5 16.5 5.5C18.9 5.5 20.5 7.29412 20.5 9.5C20.5 11.7059 19.8 12.9118 17 16Z" stroke="#ffffff" stroke-width="1.2"/></svg>
		<?php endif; ?>
	</a>
	<a class="views-icon" href="#!"><svg xmlns="http://www.w3.org/2000/svg" fill="#ffffff" width="28" height="28" viewBox="0 0 24 24"><path d="M15 12c0 1.654-1.346 3-3 3s-3-1.346-3-3 1.346-3 3-3 3 1.346 3 3zm9-.449s-4.252 8.449-11.985 8.449c-7.18 0-12.015-8.449-12.015-8.449s4.446-7.551 12.015-7.551c7.694 0 11.985 7.551 11.985 7.551zm-7 .449c0-2.757-2.243-5-5-5s-5 2.243-5 5 2.243 5 5 5 5-2.243 5-5z"/></svg><span><?php echo wpst_get_human_number( wpst_get_post_views( $post->ID ) ); ?></span></a>
	<a class="copy-link" href="#!" data-clipboard-text="<?php echo get_the_permalink( get_the_id() ); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 512 512"><path fill="#ffffff" d="M512,241.7L273.643,3.343v156.152c-71.41,3.744-138.015,33.337-188.958,84.28C30.075,298.384,0,370.991,0,448.222v60.436 l29.069-52.985c45.354-82.671,132.173-134.027,226.573-134.027c5.986,0,12.004,0.212,18.001,0.632v157.779L512,241.7z M255.642,290.666c-84.543,0-163.661,36.792-217.939,98.885c26.634-114.177,129.256-199.483,251.429-199.483h15.489V78.131 l163.568,163.568L304.621,405.267V294.531l-13.585-1.683C279.347,291.401,267.439,290.666,255.642,290.666z"/></svg></a>
</div>
