<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
$current_user_id = get_current_user_id();
$upload_dir      = wp_upload_dir();

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'footer_eval_1' ) );
$profile_avatar = '';
if ( isset( $profile_avatar_basename ) && ! empty( $profile_avatar_basename ) ) {
	$profile_avatar = '<img class="rounded-circle" src="' . $upload_dir['baseurl'] . '/' . $profile_avatar_basename . '" width="32" height="32">';
} else {
	$profile_avatar = '<svg width="45" height="45" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd"><path fill="#333333" d="M12 0c6.623 0 12 5.377 12 12s-5.377 12-12 12-12-5.377-12-12 5.377-12 12-12zm8.127 19.41c-.282-.401-.772-.654-1.624-.85-3.848-.906-4.097-1.501-4.352-2.059-.259-.565-.19-1.23.205-1.977 1.726-3.257 2.09-6.024 1.027-7.79-.674-1.119-1.875-1.734-3.383-1.734-1.521 0-2.732.626-3.409 1.763-1.066 1.789-.693 4.544 1.049 7.757.402.742.476 1.406.22 1.974-.265.586-.611 1.19-4.365 2.066-.852.196-1.342.449-1.623.848 2.012 2.207 4.91 3.592 8.128 3.592s6.115-1.385 8.127-3.59zm.65-.782c1.395-1.844 2.223-4.14 2.223-6.628 0-6.071-4.929-11-11-11s-11 4.929-11 11c0 2.487.827 4.783 2.222 6.626.409-.452 1.049-.81 2.049-1.041 2.025-.462 3.376-.836 3.678-1.502.122-.272.061-.628-.188-1.087-1.917-3.535-2.282-6.641-1.03-8.745.853-1.431 2.408-2.251 4.269-2.251 1.845 0 3.391.808 4.24 2.218 1.251 2.079.896 5.195-1 8.774-.245.463-.304.821-.179 1.094.305.668 1.644 1.038 3.667 1.499 1 .23 1.64.59 2.049 1.043z"/></svg>';
}
$current_user = wp_get_current_user();
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'footer_eval_2' ) );
?>
	
	<footer>
		<div class="footer-menu 
		<?php
		if ( get_theme_mod( 'wpst_enable_creators', '' ) === true ) :
			?>
			footer-menu-5
			<?php
elseif ( wpst_is_admin() ) :
	?>
			footer-menu-4
			<?php
else :
	?>
	footer-menu-3<?php endif; ?>">
			<a 
			<?php
			if ( is_front_page() || is_home() ) :
				?>
				class="active"<?php endif; ?> href="<?php echo esc_url( home_url( '/' ) ); ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="fill-current text-primary cursor-pointer" height="30"><path fill="#333333" clip-rule="evenodd" fill-rule="evenodd" d="m12 1c-0.1697-3.069e-5 -0.3394 0.05602-0.4796 0.1682l-10.23 8.186c-0.331 0.2648-0.3846 0.7477-0.1199 1.079 0.2648 0.331 0.7477 0.3846 1.079 0.1199l0.7999-0.6399v12.32c0 0.4238 0.3436 0.7674 0.7674 0.7674h16.37c0.4239 0 0.7674-0.3437 0.7674-0.7674v-12.32l0.7994 0.6395c0.331 0.2648 0.8139 0.2111 1.079-0.1199 0.2648-0.3309 0.2111-0.8139-0.1199-1.079l-2.034-1.627c-4e-3 -0.0033-8e-3 -0.00652-0.01197-0.00975l-8.186-6.549c-0.1402-0.1121-0.3099-0.1682-0.4795-0.1682zm-7.7e-5 1.75 7.419 5.935v12.78h-14.84v-12.78z" style="stroke-width: 0.7674;"></path></svg><small><?php esc_html_e( 'Home', 'wpst' ); ?></small></a>

			<?php
			/*
			if( wpst_is_admin() ) : ?>
				<a <?php if( is_page_template( 'template-add-content.php') ) : ?>class="active"<?php endif; ?> href="<?php echo esc_url( wpst_get_page_url( 'add-content' ) ); ?>"><svg data-cy-id="plus-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="fill-current text-primary cursor-pointer" height="30"><path fill="#333333" data-v-743171fe="" clip-rule="evenodd" fill-rule="evenodd" d="m4.837 2.535c-1.275 0-2.302 1.028-2.302 2.302v14.33c0 1.275 1.028 2.302 2.302 2.302h14.33c1.275 0 2.302-1.028 2.302-2.302v-14.33c0-1.275-1.028-2.302-2.302-2.302zm-3.837 2.302c0-2.122 1.715-3.837 3.837-3.837h14.33c2.122 0 3.837 1.715 3.837 3.837v14.33c0 2.122-1.715 3.837-3.837 3.837h-14.33c-2.122 0-3.837-1.715-3.837-3.837zm4.093 7.163c0-0.4239 0.3436-0.7674 0.7674-0.7674h5.372v-5.372c0-0.4238 0.3436-0.7674 0.7674-0.7674s0.7674 0.3436 0.7674 0.7674v5.372h5.372c0.4239 0 0.7674 0.3436 0.7674 0.7674 0 0.4239-0.3436 0.7674-0.7674 0.7674h-5.372v5.372c0 0.4239-0.3436 0.7674-0.7674 0.7674s-0.7674-0.3436-0.7674-0.7674v-5.372h-5.372c-0.4238 0-0.7674-0.3436-0.7674-0.7674z" style="stroke-width: 0.7674;"></path></svg><small><?php esc_html_e( 'Add content', 'wpst' ); ?></small></a>
			<?php endif; */
			?>
			
			<a id="search-menu" 
			<?php
			if ( is_search() || is_page_template( 'template-search.php' ) ) :
				?>
				class="active"<?php endif; ?> href="<?php echo esc_url( wpst_get_page_url( 'search' ) ); ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="fill-current text-primary cursor-pointer" height="30"><path fill="#333333" clip-rule="evenodd" fill-rule="evenodd" d="m9.952 1c-4.944 0-8.952 4.009-8.952 8.954 0 4.945 4.008 8.954 8.952 8.954 2.012 0 3.869-0.664 5.364-1.785l5.65 5.651c0.144 0.144 0.3391 0.2248 0.5426 0.2248 0.2036 0 0.3987-0.08082 0.5426-0.2248l0.7234-0.7236c0.2997-0.2997 0.2997-0.7857 0-1.085l-5.651-5.652c1.118-1.494 1.78-3.35 1.78-5.36 0-4.945-4.008-8.954-8.952-8.954zm5.606 13.81c1.128-1.302 1.811-3 1.811-4.858 0-4.098-3.321-7.419-7.417-7.419-4.097 0-7.418 3.322-7.418 7.419 0 4.098 3.321 7.419 7.418 7.419 1.858 0 3.557-0.6835 4.858-1.813 0.0046-0.0049 0.0093-0.0097 0.01397-0.01443l0.7234-0.7236c0.0035-0.0035 0.0069-0.0068 0.01044-0.01021z" style="stroke-width: 0.7674;"></path></svg><small><?php esc_html_e( 'Search', 'wpst' ); ?></small></a>
				
			<?php if ( get_theme_mod( 'wpst_enable_creators', '' ) === true && wpst_is_author() || wpst_is_admin() ) : ?>                
				<a 
				<?php
				if ( is_page_template( 'template-add-content.php' ) ) :
					?>
					class="active"<?php endif; ?> href="<?php echo esc_url( wpst_get_page_url( 'add-content' ) ); ?>"><svg data-cy-id="plus-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="fill-current text-primary cursor-pointer" height="30"><path fill="#333333" data-v-743171fe="" clip-rule="evenodd" fill-rule="evenodd" d="m4.837 2.535c-1.275 0-2.302 1.028-2.302 2.302v14.33c0 1.275 1.028 2.302 2.302 2.302h14.33c1.275 0 2.302-1.028 2.302-2.302v-14.33c0-1.275-1.028-2.302-2.302-2.302zm-3.837 2.302c0-2.122 1.715-3.837 3.837-3.837h14.33c2.122 0 3.837 1.715 3.837 3.837v14.33c0 2.122-1.715 3.837-3.837 3.837h-14.33c-2.122 0-3.837-1.715-3.837-3.837zm4.093 7.163c0-0.4239 0.3436-0.7674 0.7674-0.7674h5.372v-5.372c0-0.4238 0.3436-0.7674 0.7674-0.7674s0.7674 0.3436 0.7674 0.7674v5.372h5.372c0.4239 0 0.7674 0.3436 0.7674 0.7674 0 0.4239-0.3436 0.7674-0.7674 0.7674h-5.372v5.372c0 0.4239-0.3436 0.7674-0.7674 0.7674s-0.7674-0.3436-0.7674-0.7674v-5.372h-5.372c-0.4238 0-0.7674-0.3436-0.7674-0.7674z" style="stroke-width: 0.7674;"></path></svg><small><?php esc_html_e( 'Add content', 'wpst' ); ?></small></a>
			<?php elseif ( get_theme_mod( 'wpst_enable_creators', '' ) === true && ! is_user_logged_in() ) : ?>
				<a class="wpst-login" href="#!"><svg data-cy-id="plus-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="fill-current text-primary cursor-pointer" height="30"><path fill="#333333" data-v-743171fe="" clip-rule="evenodd" fill-rule="evenodd" d="m4.837 2.535c-1.275 0-2.302 1.028-2.302 2.302v14.33c0 1.275 1.028 2.302 2.302 2.302h14.33c1.275 0 2.302-1.028 2.302-2.302v-14.33c0-1.275-1.028-2.302-2.302-2.302zm-3.837 2.302c0-2.122 1.715-3.837 3.837-3.837h14.33c2.122 0 3.837 1.715 3.837 3.837v14.33c0 2.122-1.715 3.837-3.837 3.837h-14.33c-2.122 0-3.837-1.715-3.837-3.837zm4.093 7.163c0-0.4239 0.3436-0.7674 0.7674-0.7674h5.372v-5.372c0-0.4238 0.3436-0.7674 0.7674-0.7674s0.7674 0.3436 0.7674 0.7674v5.372h5.372c0.4239 0 0.7674 0.3436 0.7674 0.7674 0 0.4239-0.3436 0.7674-0.7674 0.7674h-5.372v5.372c0 0.4239-0.3436 0.7674-0.7674 0.7674s-0.7674-0.3436-0.7674-0.7674v-5.372h-5.372c-0.4238 0-0.7674-0.3436-0.7674-0.7674z" style="stroke-width: 0.7674;"></path></svg><small><?php esc_html_e( 'Add content', 'wpst' ); ?></small></a>
			<?php endif; ?>            

			<a id="fav-menu" 
			<?php
			if ( is_page_template( 'template-favorites.php' ) ) :
				?>
				class="active"<?php endif; ?> href="<?php echo esc_url( wpst_get_page_url( 'favorites' ) ); ?>"><svg xmlns="http://www.w3.org/2000/svg" fill="none" width="24" height="24" viewBox="3.9 4.9 17.2 16.2"><path d="M17 16C15.8 17.3235 12.5 20.5 12.5 20.5C12.5 20.5 9.2 17.3235 8 16C5.2 12.9118 4.5 11.7059 4.5 9.5C4.5 7.29412 6.1 5.5 8.5 5.5C10.5 5.5 11.7 6.82353 12.5 8.14706C13.3 6.82353 14.5 5.5 16.5 5.5C18.9 5.5 20.5 7.29412 20.5 9.5C20.5 11.7059 19.8 12.9118 17 16Z" stroke="#333333" stroke-width="1.2"/></svg><small><?php esc_html_e( 'Favorites', 'wpst' ); ?></small></a>
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
