<?php
	$admin_user_id = 1;
	$admin_user    = get_user_by( 'ID', $admin_user_id );

	$upload_dir = wp_upload_dir();

	/** PROFILE AVATAR */
	$profile_avatar_basename = esc_html( get_user_meta( $admin_user_id, '_author_profile_avatar_basename', true ) );
	$profile_avatar          = '';
if ( isset( $profile_avatar_basename ) && ! empty( $profile_avatar_basename ) ) {
	$profile_avatar = '<img id="avatar-img" src="' . $upload_dir['baseurl'] . '/' . $profile_avatar_basename . '" width="130" height="130">';
} else {
	$profile_avatar = '<div id="avatar-img" class="default-avatar"><svg width="130" height="130" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd"><path fill="#ffffff" d="M12 0c6.623 0 12 5.377 12 12s-5.377 12-12 12-12-5.377-12-12 5.377-12 12-12zm8.127 19.41c-.282-.401-.772-.654-1.624-.85-3.848-.906-4.097-1.501-4.352-2.059-.259-.565-.19-1.23.205-1.977 1.726-3.257 2.09-6.024 1.027-7.79-.674-1.119-1.875-1.734-3.383-1.734-1.521 0-2.732.626-3.409 1.763-1.066 1.789-.693 4.544 1.049 7.757.402.742.476 1.406.22 1.974-.265.586-.611 1.19-4.365 2.066-.852.196-1.342.449-1.623.848 2.012 2.207 4.91 3.592 8.128 3.592s6.115-1.385 8.127-3.59zm.65-.782c1.395-1.844 2.223-4.14 2.223-6.628 0-6.071-4.929-11-11-11s-11 4.929-11 11c0 2.487.827 4.783 2.222 6.626.409-.452 1.049-.81 2.049-1.041 2.025-.462 3.376-.836 3.678-1.502.122-.272.061-.628-.188-1.087-1.917-3.535-2.282-6.641-1.03-8.745.853-1.431 2.408-2.251 4.269-2.251 1.845 0 3.391.808 4.24 2.218 1.251 2.079.896 5.195-1 8.774-.245.463-.304.821-.179 1.094.305.668 1.644 1.038 3.667 1.499 1 .23 1.64.59 2.049 1.043z"/></svg></div>';
}
	/** PROFILE POSTER */
	$profile_poster_basename = esc_html( get_user_meta( $admin_user_id, '_author_profile_poster_basename', true ) );
	$profile_poster_url      = '';
if ( isset( $profile_poster_basename ) && ! empty( $profile_poster_basename ) ) {
	$profile_poster_url = $upload_dir['baseurl'] . '/' . $profile_poster_basename;
}
	$author_desc         = get_user_meta( $admin_user_id, 'description', true );
	$author_display_name = $admin_user->display_name;
	$current_user_id     = get_current_user_id();

	$ads_displaying_frequency = get_theme_mod( 'wpst_ads_displaying_frequency', 5 );

	get_header();

	$paged    = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
	$args     = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'orderby'        => 'ID',
		'order'          => 'DESC',
		'posts_per_page' => $ads_displaying_frequency,
		'tax_query'      => array(
			array(
				'taxonomy' => 'post_format',
				'field'    => 'slug',
				'terms'    => array(
					'post-format-image',
					'post-format-video',
				),
				'operator' => 'IN',
			),
		),
		'paged'          => $paged,
	);
	$wp_query = new WP_Query( $args );

	eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'index_eval_1' ) );
	eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'index_eval_2' ) );
	?>

<main>

	<?php if ( isset( $_GET['view'] ) && $_GET['view'] === 'profile' ) : ?>

		<div class="creator-header
		<?php
		if ( empty( $profile_poster_url ) ) :
			?>
			empty-header<?php endif; ?>">
			<div class="profile-poster">
				<?php if ( ! empty( $profile_poster_url ) ) : ?>
					<img src="<?php echo esc_url( $profile_poster_url ); ?>">
				<?php endif; ?>
			</div>
			<div class="profile-avatar">
				<?php if ( is_user_logged_in() && ( $current_user_id === $admin_user_id ) ) : ?>
					<a href="<?php echo esc_url( wpst_get_page_url( 'edit-profile' ) ); ?>"><?php echo $profile_avatar; ?></a>
				<?php else : ?>
					<?php echo $profile_avatar; ?>
				<?php endif; ?>
			</div>
		</div>

		<div class="creator-infos">
			<h1><?php echo esc_html( $author_display_name ); ?></h1>
			<?php if ( isset( $author_desc ) && ! empty( $author_desc ) ) : ?>
				<p class="creator-desc"><?php echo esc_html( $author_desc ); ?></p>
			<?php endif; ?>
			<?php if ( is_user_logged_in() && ( $current_user_id === $admin_user_id ) ) : ?>
				<div class="creator-author-action">
					<a class="button button-primary" href="<?php echo esc_url( wpst_get_page_url( 'edit-profile' ) ); ?>"><?php esc_html_e( 'Edit my profile', 'wpst' ); ?></a>
				</div>
			<?php endif; ?>
		</div>

		<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'index_eval_3' ) ); ?>

		<div class="content-wrapper">
			<div id="tab-vids" class="tab-content active">
				<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'index_eval_6' ) ); ?>
			</div>
			<div id="tab-pics" class="tab-content">
				<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'index_eval_7' ) ); ?>
			</div>
		</div>

	<?php elseif ( isset( $_GET['view'] ) && $_GET['view'] === 'grid' ) : ?>

		<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'index_eval_3' ) ); ?>

		<div class="content-wrapper">
			<div id="tab-vids" class="tab-content active">
				<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'index_eval_6' ) ); ?>
			</div>
			<div id="tab-pics" class="tab-content">
				<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'index_eval_7' ) ); ?>
			</div>
		</div>

	<?php else : ?>

		<div class="swiper">
			<div class="swiper-wrapper">
			<?php
			if ( $wp_query->have_posts() ) :
				?>
					<?php
					while ( $wp_query->have_posts() ) :
						$wp_query->the_post();
						?>
						<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'index_eval_4' ) ); ?>
					<?php endwhile; ?>
				<?php
				endif;
				wp_reset_query();
				eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'index_eval_5' ) );
			?>
			</div>
			<div class="swiper-button-prev"></div>
			<div class="swiper-button-next"></div>
		</div>

	<?php endif; ?>

</main>

<?php
	get_footer();

