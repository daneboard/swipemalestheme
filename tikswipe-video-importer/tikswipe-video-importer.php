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

define( 'TSVI_VERSION', '1.9.0' );
define( 'TSVI_PATH', plugin_dir_path( __FILE__ ) );
define( 'TSVI_URL', plugin_dir_url( __FILE__ ) );

require_once TSVI_PATH . 'includes/class-tsvi-ai.php';
require_once TSVI_PATH . 'includes/class-tsvi-bunny.php';
require_once TSVI_PATH . 'includes/class-tsvi-scraper.php';
require_once TSVI_PATH . 'includes/class-tsvi-importer.php';
require_once TSVI_PATH . 'includes/class-tsvi-admin.php';
require_once TSVI_PATH . 'includes/class-tsvi-log.php';

add_action( 'plugins_loaded', function () {
	if ( is_admin() ) {
		TSVI_Admin::init();
	}
} );

// Register background cron for Bunny uploads.
TSVI_Bunny::init_cron();

// Add database indexes on activation for faster queue queries.
register_activation_hook( __FILE__, 'tsvi_activate' );
function tsvi_activate() {
	global $wpdb;
	// Index on meta_key + meta_value for queue lookups.
	$index_exists = $wpdb->get_var(
		"SHOW INDEX FROM {$wpdb->postmeta} WHERE Key_name = 'tsvi_bunny_pending'"
	);
	if ( ! $index_exists ) {
		$wpdb->query(
			"ALTER TABLE {$wpdb->postmeta}
			 ADD INDEX tsvi_bunny_pending (meta_key(40), meta_value(40))"
		);
	}
	update_option( 'tsvi_db_version', '1.0' );
}

// Run activation on upgrade if indexes missing.
add_action( 'admin_init', function () {
	if ( get_option( 'tsvi_db_version' ) !== '1.0' ) {
		tsvi_activate();
	}
} );
