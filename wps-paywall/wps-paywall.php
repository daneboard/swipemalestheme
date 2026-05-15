<?php
/**
 * Plugin Name: WPS Paywall
 * Version: 1.3.5
 * Plugin URI: https://www.wp-script.com/adult-wordpress-plugins/paywall/
 * Description: Restrict and Sell Access to Premium Content.
 * Author: WP-Script
 * Author URI: https://www.wp-script.com/
 * Text Domain: pwll_lang
 * Domain Path: /languages
 * Requires PHP: 7.2
 *
 * @package pwll\main
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

define( 'PWLL_VERSION', '1.3.5' );
define( 'PWLL_DIR', plugin_dir_path( __FILE__ ) );
define( 'PWLL_URL', plugin_dir_url( __FILE__ ) );
define( 'PWLL_FILE', __FILE__ );

require_once PWLL_DIR . 'tgmpa/class-tgm-plugin-activation.php';
require_once PWLL_DIR . 'tgmpa/config.php';
require_once PWLL_DIR . 'vendor/autoload.php';

/**
 * Create the plugin instance in a function and call it.
 */
if ( ! function_exists( 'pwll' ) ) {
	/**
	 * Run the plugin.
	 *
	 * @return PWLL The plugin instance or null if the plugin is not connected.
	 */
	function pwll() {
		return PWLL::instance();
	}
}

add_action(
	'woocommerce_init',
	function () {
		if ( function_exists( 'WPSCORE' ) && 'connected' === WPSCORE()->get_product_status( 'PWLL' ) ) {
			pwll();
		}
	}
);
