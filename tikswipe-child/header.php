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
				// Header CTA slot (same place the old "Fav this app" PWA
				// button used). Three mutually exclusive states:
				//   1. Logged out  → Login button (opens the modal)
				//   2. Logged in + free, on home → Remove Ads button
				//   3. Logged in + premium → nothing (clean header)
				// All guarded by class_exists so the theme stays functional
				// without the plugin.
				$tsar_logged_in  = is_user_logged_in();
				$tsar_is_premium = $tsar_logged_in && class_exists( 'TSAR_Membership' ) && TSAR_Membership::is_premium();
				$tsar_on_home    = is_home() || is_front_page();

				if ( ! $tsar_logged_in ) :
					// The .wpst-login class is what main.js binds to for the
					// login/register modal trigger.
					?>
					<a class="wpst-pwa-btn tsar-header-cta tsar-login-cta wpst-login" href="#wpst-login">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
							<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
						<span><?php esc_html_e( 'Login', 'tikswipe-child' ); ?></span>
					</a>
				<?php elseif ( $tsar_on_home && ! $tsar_is_premium && function_exists( 'tsar_subscription_url' ) ) : ?>
					<a class="wpst-pwa-btn tsar-header-cta tsar-remove-ads-btn" href="<?php echo esc_url( tsar_subscription_url() ); ?>">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
							<path d="M12 2L4 5v6c0 5 3.5 9.5 8 11 4.5-1.5 8-6 8-11V5l-8-3z" fill="currentColor"/>
							<path d="M8.5 12l2.5 2.5L16 9.5" stroke="#000" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
						</svg>
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
