<?php
	$logo_type  = get_theme_mod( 'wpst_logo_type', 'logo_text' );
	$logo_image = get_theme_mod( 'wpst_logo_image', '' );
if ( ( is_search() || is_page_template( array( 'template-add-content.php', 'template-edit-profile.php', 'template-favorites.php', 'template-search.php', 'template-creators.php' ) ) ) && ! empty( get_theme_mod( 'wpst_logo_image_for_white_pages', '' ) ) ) {
	$logo_image = get_theme_mod( 'wpst_logo_image_for_white_pages', '' );
}
	$site_title  = get_bloginfo( 'name' );
	$logo_word_1 = get_theme_mod( 'wpst_logo_tube_word_1', '' );
	$logo_word_2 = get_theme_mod( 'wpst_logo_tube_word_2', '' );
	$site_url    = home_url( '/' );
?>

<div class="logo-box">

	<?php if ( 'logo_image' === $logo_type && ( isset( $logo_image ) && ! empty( $logo_image ) ) ) : ?>
		
		<a class="navbar-brand-image" rel="home" href="<?php echo esc_url( $site_url ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" itemprop="url"><img src="<?php echo esc_url( $logo_image ); ?>"></a>

	<?php elseif ( 'logo_tube' === $logo_type && ( ( isset( $logo_word_1 ) && ! empty( $logo_word_1 ) ) || ( isset( $logo_word_2 ) && ! empty( $logo_word_2 ) ) ) ) : ?>

		<a class="navbar-brand" rel="home" href="<?php echo esc_url( $site_url ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" itemprop="url">
			<?php if ( isset( $logo_word_1 ) && ! empty( $logo_word_1 ) ) : ?>
				<span class="logo-word-1"><?php echo esc_attr( $logo_word_1 ); ?></span>
			<?php endif; ?>
			<?php if ( isset( $logo_word_2 ) && ! empty( $logo_word_2 ) ) : ?>
				<span class="logo-word-2"><?php echo esc_attr( $logo_word_2 ); ?></span>	
			<?php endif; ?>
		</a>

	<?php else : ?>

		<a class="navbar-brand" rel="home" href="<?php echo esc_url( $site_url ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" itemprop="url">
			<span class="logo-text"><?php echo esc_attr( $site_title ); ?></span>
		</a>

		<?php
	endif;
	?>

</div>
