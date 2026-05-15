<?php
/**
 * Activation, deactivation and schema upgrades.
 *
 * @package TikSwipe_Ad_Removal
 */

defined( 'ABSPATH' ) || exit;

class TSAR_Install {

	const DB_VERSION_OPTION = 'tsar_db_version';
	const DB_VERSION        = '1.0.0';

	public static function activate() {
		self::create_table();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		if ( ! wp_next_scheduled( 'tsar_daily_expire' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'tsar_daily_expire' );
		}
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'tsar_daily_expire' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tsar_daily_expire' );
		}
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::create_table();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}
	}

	private static function create_table() {
		global $wpdb;

		$table   = tsar_table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			plan VARCHAR(32) NOT NULL DEFAULT '',
			amount DECIMAL(10,2) NOT NULL DEFAULT 0,
			currency VARCHAR(8) NOT NULL DEFAULT 'USD',
			paypal_email_used VARCHAR(190) NOT NULL DEFAULT '',
			txn_note TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			days_granted INT NOT NULL DEFAULT 0,
			expires_at BIGINT(20) UNSIGNED NULL,
			created_at BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			processed_at BIGINT(20) UNSIGNED NULL,
			processed_by BIGINT(20) UNSIGNED NULL,
			admin_notes TEXT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}
