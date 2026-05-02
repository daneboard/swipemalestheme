<?php
/**
 * Plugin Name:       TikSwipe Embed
 * Plugin URI:        https://swipemales.com/
 * Description:       Static rich embed cards for video posts. Renders poster + play button + metadata in third-party sites; click anywhere navigates back to the original post to play.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            TikSwipe
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tikswipe-embed
 *
 * @package TikSwipe_Embed
 */

defined( 'ABSPATH' ) || exit;

define( 'TSE_VERSION', '1.0.0' );
define( 'TSE_PLUGIN_FILE', __FILE__ );
define( 'TSE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TSE_PLUGIN_DIR . 'includes/tse-helpers.php';
require_once TSE_PLUGIN_DIR . 'includes/class-tse-template.php';
require_once TSE_PLUGIN_DIR . 'includes/class-tse-oembed.php';
require_once TSE_PLUGIN_DIR . 'includes/class-tse-admin.php';
require_once TSE_PLUGIN_DIR . 'includes/class-tse-shortcode.php';

/**
 * Boot the plugin.
 */
function tse_init() {
	TSE_Template::init();
	TSE_Oembed::init();
	TSE_Admin::init();
	TSE_Shortcode::init();
}
add_action( 'plugins_loaded', 'tse_init' );

/**
 * Activation: flush rewrites so /embed/ endpoint resolves cleanly.
 */
function tse_activate() {
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'tse_activate' );

/**
 * Deactivation cleanup.
 */
function tse_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'tse_deactivate' );
