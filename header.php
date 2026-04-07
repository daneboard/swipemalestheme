<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>

<!DOCTYPE html>
<?php require get_template_directory() . '/inc/init.php'; ?>

<html <?php language_attributes(); ?>>
<head>
<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<meta http-equiv="ScreenOrientation" content="autoRotate:disabled">
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
				<?php if ( is_home() || is_front_page() ) : ?>
					<?php if ( isset( $_GET['view'] ) && $_GET['view'] === 'grid' ) : ?>
						<a class="display-switcher" id="switch-to-swipe" href="<?php echo esc_url( home_url( '/' ) ); ?>"><svg xmlns="http://www.w3.org/2000/svg" width="35" height="35" viewBox="0 0 24 24"><path fill="#ffffff" d="M13 6h4l-5-6-5 6h4v12h-4l5 6 5-6h-4z"/></svg></a>
					<?php else : ?>
						<a class="display-switcher" id="switch-to-grid" href="<?php echo esc_url( home_url( '/?view=grid' ) ); ?>"><svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" width="35" height="35" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill="#ffffff" d="m8 16h-5v4c0 .621.52 1 1 1h4zm6.6 5v-5h-5.2v5zm6.4-5h-5v5h4c.478 0 1-.379 1-1zm0-1.4v-5.2h-5v5.2zm-18-5.2v5.2h5v-5.2zm11.6 0h-5.2v5.2h5.2zm1.4-6.4v5h5v-4c0-.478-.379-1-1-1zm-8 5v-5h-4c-.62 0-1 .519-1 1v4zm6.6-5h-5.2v5h5.2z" fill-rule="nonzero"/></svg></a>
					<?php endif; ?>
				<?php endif; ?>

				<?php
				/*
				echo wpst_get_filter_title(); ?>
				<?php get_template_part( 'templates/content', 'filters' ); */
				?>
				
				<a id="menu-mobile-icon" href="#!"><svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" width="45" height="45" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill="#333333" d="m11 16.745c0-.414.336-.75.75-.75h9.5c.414 0 .75.336.75.75s-.336.75-.75.75h-9.5c-.414 0-.75-.336-.75-.75zm-9-5c0-.414.336-.75.75-.75h18.5c.414 0 .75.336.75.75s-.336.75-.75.75h-18.5c-.414 0-.75-.336-.75-.75zm4-5c0-.414.336-.75.75-.75h14.5c.414 0 .75.336.75.75s-.336.75-.75.75h-14.5c-.414 0-.75-.336-.75-.75z" fill-rule="nonzero"/></svg></a>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'wpst-header-menu',
						'menu_class'     => '',
						'container'      => false,
					)
				);
				?>
				<a id="close-mobile-nav" href="#!"><svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" width="40" height="40" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill="#FFFFFF" d="m12 10.93 5.719-5.72c.146-.146.339-.219.531-.219.404 0 .75.324.75.749 0 .193-.073.385-.219.532l-5.72 5.719 5.719 5.719c.147.147.22.339.22.531 0 .427-.349.75-.75.75-.192 0-.385-.073-.531-.219l-5.719-5.719-5.719 5.719c-.146.146-.339.219-.531.219-.401 0-.75-.323-.75-.75 0-.192.073-.384.22-.531l5.719-5.719-5.72-5.719c-.146-.147-.219-.339-.219-.532 0-.425.346-.749.75-.749.192 0 .385.073.531.219z"/></svg></a>
			</div>
		</header>
		<?php
	endif;
