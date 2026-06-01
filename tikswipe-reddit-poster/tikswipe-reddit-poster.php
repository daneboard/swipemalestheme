<?php
/**
 * Plugin Name: TikSwipe Reddit Poster
 * Plugin URI:  https://github.com/daneboard/swipemalestheme
 * Description: Publish WordPress posts to Reddit (text/link/image), schedule a follow-up comment, and pull live analytics via the official Reddit API.
 * Version:     1.0.0
 * Author:      TikSwipe
 * Text Domain: tikswipe-reddit-poster
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * Update URI:  false
 */

defined( 'ABSPATH' ) || exit;

define( 'TSRP_VERSION', '1.0.0' );
define( 'TSRP_PATH', plugin_dir_path( __FILE__ ) );
define( 'TSRP_URL', plugin_dir_url( __FILE__ ) );
define( 'TSRP_DB_VERSION', '1.0.0' );

require_once TSRP_PATH . 'includes/class-tsrp-crypto.php';
require_once TSRP_PATH . 'includes/class-tsrp-db.php';
require_once TSRP_PATH . 'includes/class-tsrp-log.php';
require_once TSRP_PATH . 'includes/class-tsrp-oauth.php';
require_once TSRP_PATH . 'includes/class-tsrp-api.php';
require_once TSRP_PATH . 'includes/class-tsrp-submitter.php';
require_once TSRP_PATH . 'includes/class-tsrp-scheduler.php';
require_once TSRP_PATH . 'includes/class-tsrp-analytics.php';
require_once TSRP_PATH . 'includes/class-tsrp-admin.php';

register_activation_hook( __FILE__, array( 'TSRP_DB', 'install' ) );

add_action( 'plugins_loaded', function () {
	TSRP_DB::maybe_upgrade();
	TSRP_OAuth::init();
	TSRP_Scheduler::init();
	if ( is_admin() ) {
		TSRP_Admin::init();
	}
} );
