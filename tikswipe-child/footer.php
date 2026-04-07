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

$is_home = is_front_page() || is_home();
$is_search = is_search() || is_page_template( 'template-search.php' );
$is_fav = is_page_template( 'template-favorites.php' );
?>

	<footer>
		<div class="footer-menu footer-menu-<?php echo $menu_items; ?>">
			<a <?php if ( $is_home ) : ?>class="active"<?php endif; ?> href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php if ( $is_home ) : ?>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="24"><path fill="currentColor" d="M12.97 2.59a1.5 1.5 0 0 0-1.94 0l-7.5 6.363A1.5 1.5 0 0 0 3 10.097V19.5A1.5 1.5 0 0 0 4.5 21h4.75a.75.75 0 0 0 .75-.75v-4.5a2 2 0 0 1 4 0v4.5c0 .414.336.75.75.75h4.75a1.5 1.5 0 0 0 1.5-1.5v-9.403a1.5 1.5 0 0 0-.53-1.144l-7.5-6.363z"/></svg>
				<?php else : ?>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="24"><path fill="currentColor" d="m12 1c-0.17 0-0.34 0.056-0.48 0.168l-10.23 8.186c-0.331 0.265-0.385 0.748-0.12 1.079 0.265 0.331 0.748 0.385 1.079 0.12l0.8-0.64v12.32c0 0.424 0.344 0.767 0.767 0.767h16.37c0.424 0 0.767-0.344 0.767-0.767v-12.32l0.8 0.64c0.331 0.265 0.814 0.211 1.079-0.12 0.265-0.331 0.211-0.814-0.12-1.079l-2.034-1.627-0.012-0.01-8.186-6.549c-0.14-0.112-0.31-0.168-0.48-0.168zm0 1.75 7.419 5.935v12.78h-14.84v-12.78z"/></svg>
				<?php endif; ?>
				<small><?php esc_html_e( 'Home', 'wpst' ); ?></small>
			</a>

			<a id="search-menu" <?php if ( $is_search ) : ?>class="active"<?php endif; ?> href="<?php echo esc_url( wpst_get_page_url( 'search' ) ); ?>">
				<?php if ( $is_search ) : ?>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="24"><path fill="currentColor" d="M10.5 2a8.5 8.5 0 0 1 6.676 13.762l4.781 4.781a.75.75 0 0 1-1.06 1.06l-4.781-4.78A8.5 8.5 0 1 1 10.5 2zM4 10.5a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0z"/><circle cx="10.5" cy="10.5" r="5" fill="currentColor" opacity="0.3"/></svg>
				<?php else : ?>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" height="24"><path fill="currentColor" d="M10.5 2a8.5 8.5 0 0 1 6.676 13.762l4.781 4.781a.75.75 0 0 1-1.06 1.06l-4.781-4.78A8.5 8.5 0 1 1 10.5 2zM4 10.5a6.5 6.5 0 1 0 13 0 6.5 6.5 0 0 0-13 0z"/></svg>
				<?php endif; ?>
				<small><?php esc_html_e( 'Search', 'wpst' ); ?></small>
			</a>

			<a id="fav-menu" <?php if ( $is_fav ) : ?>class="active"<?php endif; ?> href="<?php echo esc_url( wpst_get_page_url( 'favorites' ) ); ?>">
				<?php if ( $is_fav ) : ?>
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="3.9 4.9 17.2 16.2"><path d="M17 16C15.8 17.3235 12.5 20.5 12.5 20.5C12.5 20.5 9.2 17.3235 8 16C5.2 12.9118 4.5 11.7059 4.5 9.5C4.5 7.29412 6.1 5.5 8.5 5.5C10.5 5.5 11.7 6.82353 12.5 8.14706C13.3 6.82353 14.5 5.5 16.5 5.5C18.9 5.5 20.5 7.29412 20.5 9.5C20.5 11.7059 19.8 12.9118 17 16Z" fill="currentColor" stroke="currentColor" stroke-width="1.2"/></svg>
				<?php else : ?>
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" width="24" height="24" viewBox="3.9 4.9 17.2 16.2"><path d="M17 16C15.8 17.3235 12.5 20.5 12.5 20.5C12.5 20.5 9.2 17.3235 8 16C5.2 12.9118 4.5 11.7059 4.5 9.5C4.5 7.29412 6.1 5.5 8.5 5.5C10.5 5.5 11.7 6.82353 12.5 8.14706C13.3 6.82353 14.5 5.5 16.5 5.5C18.9 5.5 20.5 7.29412 20.5 9.5C20.5 11.7059 19.8 12.9118 17 16Z" stroke="currentColor" stroke-width="1.2"/></svg>
				<?php endif; ?>
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
