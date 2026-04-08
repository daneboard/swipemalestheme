<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>

<!DOCTYPE html>
<?php require get_template_directory() . '/inc/init.php'; ?>

<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<!-- PWA -->
	<link rel="manifest" href="<?php echo get_stylesheet_directory_uri(); ?>/manifest.json">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<meta name="apple-mobile-web-app-title" content="Swipe Males">
	<meta name="application-name" content="Swipe Males">
	<meta name="theme-color" content="#000000">
	<?php wp_head(); ?>
</head>

<body
<?php
if ( ( is_home() && ! isset( $_GET['view'] ) ) || ( is_front_page() && ! isset( $_GET['view'] ) ) || is_page_template( array( 'template-vids.php', 'template-pics.php' ) ) || ( is_single() && has_post_format( array( 'video', 'image' ) ) ) || is_category() || is_tag() ) :
	?>
	<?php body_class( 'media-body' ); ?>
	<?php
elseif ( isset( $_GET['view'] ) && $_GET['view'] === 'grid' ) :
	?>
	<?php body_class( 'grid' ); ?>
	<?php
elseif ( isset( $_GET['view'] ) && $_GET['view'] === 'profile' ) :
	?>
	<?php body_class( 'profile' ); ?>
	<?php
else :
	?>
	<?php body_class(); ?><?php endif; ?>>

<?php wp_body_open(); ?>

<div id="content" class="content
<?php
if ( wp_is_mobile() ) :
	?>
	content-mobile<?php endif; ?>">
	<div class="dark-bg"></div>
	<?php if ( is_author() || ( isset( $_GET['view'] ) && $_GET['view'] === 'profile' ) ) : ?>
	<?php else : ?>
		<header>
			<div class="logo">
				<?php get_template_part( 'templates/content', 'logo' ); ?>
			</div>
			<div class="menu">
				<button id="wpst-pwa-install" class="wpst-pwa-btn" style="display:none;">Fav this app</button>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'wpst-header-menu',
						'menu_class'     => '',
						'container'      => false,
					)
				);
				?>
			</div>
		</header>
		<?php
	endif;
