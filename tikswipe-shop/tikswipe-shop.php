<?php
/**
 * Plugin Name:       TikSwipe Shop
 * Plugin URI:        https://swipemales.com/
 * Description:       TikTok-Shop style affiliate product blocks. Configure title, description, image, affiliate/button URL, target posts/categories and a custom badge. The block appears on the matching swiper slide after 10s of video playback, replacing the post info; a close button appears after 5s and restores the original info.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            TikSwipe
 * License:           GPL-2.0-or-later
 * Text Domain:       tikswipe-shop
 *
 * @package TikSwipe_Shop
 */

defined( 'ABSPATH' ) || exit;

define( 'TSS_VERSION', '1.0.0' );
define( 'TSS_PLUGIN_FILE', __FILE__ );
define( 'TSS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TSS_CPT', 'tss_shop_item' );

require_once TSS_PLUGIN_DIR . 'includes/tss-helpers.php';
require_once TSS_PLUGIN_DIR . 'includes/class-tss-cpt.php';
require_once TSS_PLUGIN_DIR . 'includes/class-tss-admin.php';
require_once TSS_PLUGIN_DIR . 'includes/class-tss-rest.php';
require_once TSS_PLUGIN_DIR . 'includes/class-tss-tracking.php';
require_once TSS_PLUGIN_DIR . 'includes/class-tss-dashboard.php';
require_once TSS_PLUGIN_DIR . 'includes/class-tss-frontend.php';

function tss_init() {
	TSS_CPT::init();
	TSS_Admin::init();
	TSS_Rest::init();
	TSS_Tracking::init();
	TSS_Dashboard::init();
	TSS_Frontend::init();
}
add_action( 'plugins_loaded', 'tss_init' );

function tss_activate() {
	TSS_CPT::register_cpt();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'tss_activate' );

function tss_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'tss_deactivate' );
