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
				<?php
				// Header CTA slot (same place as the original "Fav this app"
				// PWA button). Three mutually exclusive states, only on the
				// home / front page:
				//   1. logged in + premium  → "Fav this app" PWA install
				//      button. The child's PWA JS (in functions.php) only
				//      reveals it when beforeinstallprompt fires (Android)
				//      or on iOS Safari outside standalone — so it stays
				//      hidden when the site is already opened from the
				//      installed PWA.
				//   2. free user                → "Remove Ads" CTA pointing
				//      at /subscription.
				//   3. anything else (not home) → empty slot.
				// Guarded by class_exists so the theme works without the plugin.
				$tsar_is_premium = class_exists( 'TSAR_Membership' ) && TSAR_Membership::is_premium();
				$tsar_on_home    = is_home() || is_front_page();
				if ( $tsar_on_home && $tsar_is_premium ) :
					?>
					<button id="wpst-pwa-install" class="wpst-pwa-btn" style="display:none;"><?php esc_html_e( 'Fav this app', 'tikswipe-child' ); ?></button>
				<?php elseif ( $tsar_on_home && function_exists( 'tsar_subscription_url' ) ) : ?>
					<a class="wpst-pwa-btn tsar-remove-ads-btn" href="<?php echo esc_url( tsar_subscription_url() ); ?>">
						<span><?php esc_html_e( 'Remove Ads', 'tikswipe-child' ); ?></span>
					</a>
				<?php endif; ?>
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
