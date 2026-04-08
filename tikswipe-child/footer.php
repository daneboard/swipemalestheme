<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
$current_user_id = get_current_user_id();
$upload_dir      = wp_upload_dir();

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'footer_eval_1' ) );
$profile_avatar = '';
if ( isset( $profile_avatar_basename ) && ! empty( $profile_avatar_basename ) ) {
	$profile_avatar = '<img class="rounded-circle" src="' . $upload_dir['baseurl'] . '/' . $profile_avatar_basename . '" width="28" height="28">';
} else {
	$profile_avatar = '<svg width="28" height="28" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd"><path fill="currentColor" d="M12 0c6.623 0 12 5.377 12 12s-5.377 12-12 12-12-5.377-12-12 5.377-12 12-12zm8.127 19.41c-.282-.401-.772-.654-1.624-.85-3.848-.906-4.097-1.501-4.352-2.059-.259-.565-.19-1.23.205-1.977 1.726-3.257 2.09-6.024 1.027-7.79-.674-1.119-1.875-1.734-3.383-1.734-1.521 0-2.732.626-3.409 1.763-1.066 1.789-.693 4.544 1.049 7.757.402.742.476 1.406.22 1.974-.265.586-.611 1.19-4.365 2.066-.852.196-1.342.449-1.623.848 2.012 2.207 4.91 3.592 8.128 3.592s6.115-1.385 8.127-3.59zm.65-.782c1.395-1.844 2.223-4.14 2.223-6.628 0-6.071-4.929-11-11-11s-11 4.929-11 11c0 2.487.827 4.783 2.222 6.626.409-.452 1.049-.81 2.049-1.041 2.025-.462 3.376-.836 3.678-1.502.122-.272.061-.628-.188-1.087-1.917-3.535-2.282-6.641-1.03-8.745.853-1.431 2.408-2.251 4.269-2.251 1.845 0 3.391.808 4.24 2.218 1.251 2.079.896 5.195-1 8.774-.245.463-.304.821-.179 1.094.305.668 1.644 1.038 3.667 1.499 1 .23 1.64.59 2.049 1.043z"/></svg>';
}
$current_user = wp_get_current_user();
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'footer_eval_2' ) );

$menu_items = 3;
if ( get_theme_mod( 'wpst_enable_creators', '' ) === true ) {
	$menu_items++;
}
?>

	<div id="wpst-global-progress" class="wpst-progress-bar">
		<div class="wpst-progress-played"></div>
	</div>

	<footer>
		<div class="footer-menu footer-menu-<?php echo $menu_items; ?>">
			<a <?php if ( is_front_page() || is_home() ) echo 'class="active"'; ?> href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M11.47 3.84a.75.75 0 0 1 1.06 0l8.69 8.69a.75.75 0 0 1-1.06 1.06l-.66-.66V21a.75.75 0 0 1-.75.75h-5a.75.75 0 0 1-.75-.75v-5h-2v5a.75.75 0 0 1-.75.75h-5A.75.75 0 0 1 4.5 21v-8.07l-.66.66a.75.75 0 0 1-1.06-1.06l8.69-8.69zM6 11.43V20.25h3.5v-5a.75.75 0 0 1 .75-.75h3.5a.75.75 0 0 1 .75.75v5H18V11.43l-6-6-6 6z"/></svg>
				<small><?php esc_html_e( 'Home', 'wpst' ); ?></small>
			</a>

			<a id="search-menu" <?php if ( is_search() || is_page_template( 'template-search.php' ) ) echo 'class="active"'; ?> href="<?php echo esc_url( wpst_get_page_url( 'search' ) ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M10.5 2a8.5 8.5 0 0 1 6.676 13.762l4.781 4.781a.75.75 0 0 1-1.06 1.06l-4.781-4.78A8.5 8.5 0 1 1 10.5 2zM4 10.5a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0z"/></svg>
				<small><?php esc_html_e( 'Search', 'wpst' ); ?></small>
			</a>

			<a id="fav-menu" <?php if ( is_page_template( 'template-favorites.php' ) ) echo 'class="active"'; ?> href="<?php echo esc_url( wpst_get_page_url( 'favorites' ) ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" width="24" height="24" viewBox="0 0 24 24"><path d="M16.5 3C14.76 3 13.09 3.81 12 5.09 10.91 3.81 9.24 3 7.5 3 4.42 3 2 5.42 2 8.5c0 3.78 3.4 6.86 8.55 11.54L12 21.35l1.45-1.32C18.6 15.36 22 12.28 22 8.5 22 5.42 19.58 3 16.5 3zm-4.4 15.55l-.1.1-.1-.1C7.14 14.24 4 11.39 4 8.5 4 6.5 5.5 5 7.5 5c1.54 0 3.04.99 3.57 2.36h1.87C13.46 5.99 14.96 5 16.5 5 18.5 5 20 6.5 20 8.5c0 2.89-3.14 5.74-7.9 10.05z" stroke="currentColor" stroke-width="0.5"/></svg>
				<small><?php esc_html_e( 'Favorites', 'wpst' ); ?></small>
			</a>

			<?php if ( get_theme_mod( 'wpst_enable_creators', '' ) === true ) : ?>
				<?php if ( wpst_is_admin() ) : ?>
					<a id="menu-profil" class="menu-profile" href="<?php echo esc_url( home_url( '/?view=profile' ) ); ?>"><?php echo $profile_avatar; ?><small><?php esc_html_e( 'Profile', 'wpst' ); ?></small></a>
				<?php elseif ( wpst_is_author() ) : ?>
					<a id="menu-profil" class="menu-profile" href="<?php echo esc_url( $creator_url ); ?>"><?php echo $profile_avatar; ?><small><?php esc_html_e( 'Profile', 'wpst' ); ?></small></a>
				<?php else : ?>
					<a id="menu-profil" class="menu-profile wpst-login" href="#!"><?php echo $profile_avatar; ?><small><?php esc_html_e( 'Profile', 'wpst' ); ?></small></a>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</footer>

</div>

</body>

<?php
	wp_footer();
