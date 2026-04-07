<?php
	global $post;
	$author_id       = $post->post_author;
	$author_username = get_the_author_meta( 'user_login', $author_id );
	$the_content     = get_the_content();
	$posttags        = get_the_tags();
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
	$profile_avatar = '<img src="' . $upload_dir_url . $profile_avatar_basename . '" width="60" height="60">';
} else {
	$profile_avatar = '<svg width="50" height="50" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd"><path fill="#ffffff" d="M12 0c6.623 0 12 5.377 12 12s-5.377 12-12 12-12-5.377-12-12 5.377-12 12-12zm8.127 19.41c-.282-.401-.772-.654-1.624-.85-3.848-.906-4.097-1.501-4.352-2.059-.259-.565-.19-1.23.205-1.977 1.726-3.257 2.09-6.024 1.027-7.79-.674-1.119-1.875-1.734-3.383-1.734-1.521 0-2.732.626-3.409 1.763-1.066 1.789-.693 4.544 1.049 7.757.402.742.476 1.406.22 1.974-.265.586-.611 1.19-4.365 2.066-.852.196-1.342.449-1.623.848 2.012 2.207 4.91 3.592 8.128 3.592s6.115-1.385 8.127-3.59zm.65-.782c1.395-1.844 2.223-4.14 2.223-6.628 0-6.071-4.929-11-11-11s-11 4.929-11 11c0 2.487.827 4.783 2.222 6.626.409-.452 1.049-.81 2.049-1.041 2.025-.462 3.376-.836 3.678-1.502.122-.272.061-.628-.188-1.087-1.917-3.535-2.282-6.641-1.03-8.745.853-1.431 2.408-2.251 4.269-2.251 1.845 0 3.391.808 4.24 2.218 1.251 2.079.896 5.195-1 8.774-.245.463-.304.821-.179 1.094.305.668 1.644 1.038 3.667 1.499 1 .23 1.64.59 2.049 1.043z"/></svg>';
}
?>

<button class="close-fullscreen"><svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 24 24" width="45" height="45" xmlns="http://www.w3.org/2000/svg"><path fill="#ffffff" d="m12 10.93 5.719-5.72c.146-.146.339-.219.531-.219.404 0 .75.324.75.749 0 .193-.073.385-.219.532l-5.72 5.719 5.719 5.719c.147.147.22.339.22.531 0 .427-.349.75-.75.75-.192 0-.385-.073-.531-.219l-5.719-5.719-5.719 5.719c-.146.146-.339.219-.531.219-.401 0-.75-.323-.75-.75 0-.192.073-.384.22-.531l5.719-5.719-5.72-5.719c-.146-.147-.219-.339-.219-.532 0-.425.346-.749.75-.749.192 0 .385.073.531.219z"/></svg></button>

<div class="single-content-infos">
	<?php if ( ! empty( wpst_get_post_views( $post->ID ) ) || ! empty( wpst_get_video_duration() ) ) : ?>
		<div class="post-datas">
			<?php if ( ! empty( wpst_get_post_views( $post->ID ) ) ) : ?>
				<div class="post-views"><svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" style="position: relative; top: 1px; margin-right: 1px;"><path fill="#ffffff" d="M15 12c0 1.654-1.346 3-3 3s-3-1.346-3-3 1.346-3 3-3 3 1.346 3 3zm9-.449s-4.252 8.449-11.985 8.449c-7.18 0-12.015-8.449-12.015-8.449s4.446-7.551 12.015-7.551c7.694 0 11.985 7.551 11.985 7.551zm-7 .449c0-2.757-2.243-5-5-5s-5 2.243-5 5 2.243 5 5 5 5-2.243 5-5z"/></svg> <?php echo wpst_get_human_number( wpst_get_post_views( $post->ID ) ); ?></div>
			<?php endif; ?>
			<?php if ( ! empty( wpst_get_video_duration() ) ) : ?>
				<div class="post-duration"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" style="position: relative; top: 1px;"><path fill="#ffffff" d="M3 22v-20l18 10-18 10z"/></svg> <?php echo wpst_get_video_duration(); ?></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<?php if ( is_single() ) : ?>
		<h1><?php the_title(); ?></h1>
	<?php else : ?>
		<h2><?php the_title(); ?></h2>
	<?php endif; ?>
	<?php
	if ( ! empty( $the_content ) /* && is_single() */ ) {
		echo '<div class="post-desc"><p>' . $the_content . '</p><a class="see-desc" href="#!"><svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 24 24" width="30" height="30" xmlns="http://www.w3.org/2000/svg"><path fill="#ffffff" d="m15 17.75c0-.414-.336-.75-.75-.75h-11.5c-.414 0-.75.336-.75.75s.336.75.75.75h11.5c.414 0 .75-.336.75-.75zm7-4c0-.414-.336-.75-.75-.75h-18.5c-.414 0-.75.336-.75.75s.336.75.75.75h18.5c.414 0 .75-.336.75-.75zm0-4c0-.414-.336-.75-.75-.75h-18.5c-.414 0-.75.336-.75.75s.336.75.75.75h18.5c.414 0 .75-.336.75-.75zm0-4c0-.414-.336-.75-.75-.75h-18.5c-.414 0-.75.336-.75.75s.336.75.75.75h18.5c.414 0 .75-.336.75-.75z" fill-rule="nonzero"/></svg></a></div>';
	}
	// if ($posttags) {
	// echo '<div class="post-tags">';
	// foreach($posttags as $tag) {
	// echo '<a href="' . esc_attr( get_tag_link( $tag->term_id ) ) . '">#' . $tag->name . '</a> ';
	// }
	// echo '</div>';
	// }
	?>

	<?php
		$postcats        = get_the_category();
		$posttags        = get_the_tags();
		$tags_count      = 0;
		$current_term    = get_queried_object();
		$current_term_id = '';
	if ( null !== $current_term && property_exists( $current_term, 'term_id' ) ) {
		$current_term_id = $current_term->term_id;
	}
	if ( $postcats || $posttags ) :
		?>
				<div class="tags-list">
			<?php foreach ( (array) $postcats as $cat ) : ?>
				<a 
				<?php
				if ( $cat->term_id === $current_term_id ) :
					?>
					class="active"<?php endif; ?> href="
												<?php
												if ( $cat->term_id === $current_term_id ) :
													?>
													<?php echo esc_url( home_url( '/' ) ); ?>
													<?php
else :
	?>
		<?php echo get_category_link( $cat->term_id ); ?><?php endif; ?>" title="<?php echo $cat->name; ?>"><?php echo $cat->name; ?><small><?php echo $cat->count; ?></small></a>
			<?php endforeach; ?>
			<?php if ( ! empty( $posttags ) ) : ?>
				<?php foreach ( (array) $posttags as $tag ) : ?>
					<a 
					<?php
					if ( $tag->term_id === $current_term_id ) :
						?>
						class="active"<?php endif; ?> href="
													<?php
													if ( $tag->term_id === $current_term_id ) :
														?>
														<?php echo esc_url( home_url( '/' ) ); ?>
														<?php
else :
	?>
		<?php echo get_tag_link( $tag->term_id ); ?><?php endif; ?>" title="<?php echo $tag->name; ?>"><?php echo $tag->name; ?><small><?php echo $tag->count; ?></small></a>
					<?php
					if ( ++$tags_count == 3 ) {
						break;
					} endforeach;
				?>
			<?php endif; ?>
		</div>
		<?php
		endif;
	?>
</div>

<div class="swiper-side">
	<?php
	/*
	<div class="swiper-button-prev"></div>
	<div class="swiper-button-next"></div> */
	?>
	<a href="#!" class="enlight-content"><svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24"><path fill="#ffffff" d="M24 9h-2v-7h-7v-2h9v9zm-9 15v-2h7v-7h2v9h-9zm-15-9h2v7h7v2h-9v-9zm9-15v2h-7v7h-2v-9h9z"/></svg></a>
	<?php if ( get_theme_mod( 'wpst_enable_creators', '' ) === true ) : ?>
		<?php if ( $author_id == 1 ) : ?>
			<a class="avatar-img" href="<?php echo esc_url( home_url( '/?view=profile' ) ); ?>"><?php echo $profile_avatar; ?></a>
		<?php else : ?>
			<a class="avatar-img" href="<?php echo esc_url( home_url( '/' . $author_username ) ); ?>" title="@<?php echo $author_username; ?>"><?php echo $profile_avatar; ?></a>
		<?php endif; ?>
	<?php endif; ?>
	<a class="add-to-fav 
	<?php
	if ( in_array( $post->ID, $user_fav_posts ) ) :
		?>
		fav-added
		<?php
else :
	?>
			add-fav<?php endif; ?>" href="#!">
								<?php
								if ( in_array( $post->ID, $user_fav_posts ) ) :
									?>
	<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="3.9 4.9 17.2 16.2"><path d="M17 16C15.8 17.3235 12.5 20.5 12.5 20.5C12.5 20.5 9.2 17.3235 8 16C5.2 12.9118 4.5 11.7059 4.5 9.5C4.5 7.29412 6.1 5.5 8.5 5.5C10.5 5.5 11.7 6.82353 12.5 8.14706C13.3 6.82353 14.5 5.5 16.5 5.5C18.9 5.5 20.5 7.29412 20.5 9.5C20.5 11.7059 19.8 12.9118 17 16Z" fill="#ffffff" stroke="#ffffff" stroke-width="1.2"/></svg>
									<?php
else :
	?>
		<svg xmlns="http://www.w3.org/2000/svg" fill="none" width="32" height="32" viewBox="3.9 4.9 17.2 16.2"><path d="M17 16C15.8 17.3235 12.5 20.5 12.5 20.5C12.5 20.5 9.2 17.3235 8 16C5.2 12.9118 4.5 11.7059 4.5 9.5C4.5 7.29412 6.1 5.5 8.5 5.5C10.5 5.5 11.7 6.82353 12.5 8.14706C13.3 6.82353 14.5 5.5 16.5 5.5C18.9 5.5 20.5 7.29412 20.5 9.5C20.5 11.7059 19.8 12.9118 17 16Z" stroke="#ffffff" stroke-width="1.2"/></svg><?php endif; ?></a>
	<a class="comment-icon comment-closed<?php /* if( is_single() ) : ?> comment-post<?php endif; */ ?>" href="<?php /* echo get_the_permalink( $post->ID ); ?>#comment */ ?>#!"><svg xmlns="http://www.w3.org/2000/svg" fill="#ffffff" version="1.1" width="32" height="32" viewBox="1.25 2.35 29.5 27.3"><path d="M16.5 2.353c-7.857 0-14.25 5.438-14.25 12.124 0.044 2.834 1.15 5.402 2.938 7.33l-0.006-0.007c-0.597 2.605-1.907 4.844-3.712 6.569l-0.005 0.005c-0.132 0.135-0.214 0.32-0.214 0.525 0 0.414 0.336 0.75 0.75 0.751h0c0.054-0 0.107-0.006 0.158-0.017l-0.005 0.001c3.47-0.559 6.546-1.94 9.119-3.936l-0.045 0.034c1.569 0.552 3.378 0.871 5.262 0.871 0.004 0 0.009 0 0.013 0h-0.001c7.857 0 14.25-5.439 14.25-12.125s-6.393-12.124-14.25-12.124zM16.5 25.102c-0.016 0-0.035 0-0.054 0-1.832 0-3.586-0.332-5.205-0.94l0.102 0.034c-0.058-0.018-0.126-0.029-0.195-0.030h-0.001c-0.020-0.002-0.036-0.009-0.056-0.009 0 0-0 0-0 0-0.185 0-0.354 0.068-0.485 0.18l0.001-0.001c-0.010 0.008-0.024 0.004-0.034 0.013-1.797 1.519-3.97 2.653-6.357 3.243l-0.108 0.023c1.29-1.633 2.215-3.613 2.619-5.777l0.013-0.083c0-0.006 0-0.014 0-0.021 0-0.021-0.001-0.043-0.003-0.064l0 0.003c0-0.005 0-0.010 0-0.015 0-0.019-0.001-0.037-0.002-0.055l0 0.002c-0.004-0.181-0.073-0.345-0.184-0.47l0.001 0.001-0.011-0.027c-1.704-1.697-2.767-4.038-2.791-6.626l-0-0.005c0-5.858 5.72-10.624 12.75-10.624s12.75 4.766 12.75 10.624c0 5.859-5.719 10.625-12.75 10.625z"/></svg><span><?php echo get_comments_number( $post->ID ); ?></span></a>
	<a class="copy-link" href="#!" data-clipboard-text="<?php echo get_the_permalink( get_the_id() ); ?>"><svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="32" height="32" viewBox="0 0 512 512" style="enable-background:new 0 0 512 512;" xml:space="preserve"><path fill="#ffffff" d="M512,241.7L273.643,3.343v156.152c-71.41,3.744-138.015,33.337-188.958,84.28C30.075,298.384,0,370.991,0,448.222v60.436 l29.069-52.985c45.354-82.671,132.173-134.027,226.573-134.027c5.986,0,12.004,0.212,18.001,0.632v157.779L512,241.7z M255.642,290.666c-84.543,0-163.661,36.792-217.939,98.885c26.634-114.177,129.256-199.483,251.429-199.483h15.489V78.131 l163.568,163.568L304.621,405.267V294.531l-13.585-1.683C279.347,291.401,267.439,290.666,255.642,290.666z"/></svg></a>	
</div>
