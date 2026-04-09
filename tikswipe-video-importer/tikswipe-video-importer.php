<?php
/**
 * Plugin Name: TikSwipe Video Importer
 * Plugin URI:  https://github.com/daneboard/swipemalestheme
 * Description: Scrape and import videos from external URLs into TikSwipe theme posts.
 * Version:     1.0.0
 * Author:      TikSwipe
 * Text Domain: tikswipe-video-importer
 * Requires at least: 5.5
 * Requires PHP: 7.4
 * Update URI:  false
 */

defined( 'ABSPATH' ) || exit;

define( 'TSVI_VERSION', '1.5.0' );
define( 'TSVI_PATH', plugin_dir_path( __FILE__ ) );
define( 'TSVI_URL', plugin_dir_url( __FILE__ ) );

require_once TSVI_PATH . 'includes/class-tsvi-ai.php';
require_once TSVI_PATH . 'includes/class-tsvi-bunny.php';
require_once TSVI_PATH . 'includes/class-tsvi-scraper.php';
require_once TSVI_PATH . 'includes/class-tsvi-importer.php';
require_once TSVI_PATH . 'includes/class-tsvi-admin.php';

add_action( 'plugins_loaded', function () {
	if ( is_admin() ) {
		TSVI_Admin::init();
	}
} );

// Register background cron for Bunny uploads.
TSVI_Bunny::init_cron();
