<?php
/**
 * Plugin Name:       TikSwipe Ad Removal
 * Plugin URI:        https://swipemales.com/
 * Description:       Lets logged-in users pay (manual PayPal) to remove ExoClick / VAST ads delivered by the TikSwipe theme. Ships with a payment-claim shortcode, an admin dashboard for approving requests, manual membership management, and configurable plans / disclaimer.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            TikSwipe
 * License:           GPL-2.0-or-later
 * Text Domain:       tikswipe-ad-removal
 *
 * @package TikSwipe_Ad_Removal
 */

defined( 'ABSPATH' ) || exit;

define( 'TSAR_VERSION', '1.0.0' );
define( 'TSAR_PLUGIN_FILE', __FILE__ );
define( 'TSAR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSAR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TSAR_OPTION_KEY', 'tsar_settings' );
define( 'TSAR_TABLE', 'tsar_requests' );
define( 'TSAR_USER_META', '_tsar_premium_expires' );

require_once TSAR_PLUGIN_DIR . 'includes/tsar-helpers.php';
require_once TSAR_PLUGIN_DIR . 'includes/class-tsar-install.php';
require_once TSAR_PLUGIN_DIR . 'includes/class-tsar-membership.php';
require_once TSAR_PLUGIN_DIR . 'includes/class-tsar-ad-blocker.php';
require_once TSAR_PLUGIN_DIR . 'includes/class-tsar-frontend.php';
require_once TSAR_PLUGIN_DIR . 'includes/class-tsar-ajax.php';

register_activation_hook( __FILE__, array( 'TSAR_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TSAR_Install', 'deactivate' ) );

add_action( 'plugins_loaded', 'tsar_bootstrap' );

function tsar_bootstrap() {
	load_plugin_textdomain( 'tikswipe-ad-removal', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	TSAR_Install::maybe_upgrade();
	TSAR_Membership::init();
	TSAR_Ad_Blocker::init();
	TSAR_Frontend::init();
	TSAR_Ajax::init();

	if ( is_admin() ) {
		require_once TSAR_PLUGIN_DIR . 'includes/class-tsar-admin.php';
		TSAR_Admin::init();
	}
}

add_action( 'tsar_daily_expire', array( 'TSAR_Membership', 'cleanup_expired' ) );
