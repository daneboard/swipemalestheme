<?php
/**
 * Custom tables and schema upgrades.
 */

defined( 'ABSPATH' ) || exit;

class TSRP_DB {

	public static function submissions_table() {
		global $wpdb;
		return $wpdb->prefix . 'tsrp_submissions';
	}

	public static function log_table() {
		global $wpdb;
		return $wpdb->prefix . 'tsrp_log';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$submissions = self::submissions_table();
		$log         = self::log_table();

		$sql_submissions = "CREATE TABLE {$submissions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			subreddit varchar(100) NOT NULL,
			reddit_id varchar(40) DEFAULT NULL,
			reddit_fullname varchar(40) DEFAULT NULL,
			reddit_url text DEFAULT NULL,
			kind varchar(20) NOT NULL,
			title text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'posted',
			posted_at datetime DEFAULT NULL,
			comment_id varchar(40) DEFAULT NULL,
			comment_status varchar(20) DEFAULT NULL,
			comment_scheduled_for datetime DEFAULT NULL,
			comment_posted_at datetime DEFAULT NULL,
			last_error text DEFAULT NULL,
			last_stats longtext DEFAULT NULL,
			last_stats_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY reddit_fullname (reddit_fullname),
			KEY status (status)
		) {$charset};";

		$sql_log = "CREATE TABLE {$log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			post_id bigint(20) unsigned DEFAULT NULL,
			submission_id bigint(20) unsigned DEFAULT NULL,
			level varchar(10) NOT NULL DEFAULT 'info',
			event varchar(64) NOT NULL,
			message text DEFAULT NULL,
			context longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY submission_id (submission_id),
			KEY level (level),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql_submissions );
		dbDelta( $sql_log );

		update_option( 'tsrp_db_version', TSRP_DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'tsrp_db_version' ) !== TSRP_DB_VERSION ) {
			self::install();
		}
	}

	public static function insert_submission( array $data ) {
		global $wpdb;
		$defaults = array(
			'post_id'   => 0,
			'user_id'   => get_current_user_id(),
			'subreddit' => '',
			'kind'      => 'self',
			'status'    => 'posted',
			'posted_at' => current_time( 'mysql' ),
		);
		$data = array_merge( $defaults, $data );
		$wpdb->insert( self::submissions_table(), $data );
		return (int) $wpdb->insert_id;
	}

	public static function update_submission( $id, array $data ) {
		global $wpdb;
		return $wpdb->update( self::submissions_table(), $data, array( 'id' => (int) $id ) );
	}

	public static function get_submission( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::submissions_table() . ' WHERE id = %d', (int) $id ),
			ARRAY_A
		);
	}

	public static function get_submissions_for_post( $post_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::submissions_table() . ' WHERE post_id = %d ORDER BY posted_at DESC, id DESC',
				(int) $post_id
			),
			ARRAY_A
		);
	}

	public static function get_submissions_for_posts( array $post_ids ) {
		global $wpdb;
		$post_ids = array_map( 'intval', $post_ids );
		$post_ids = array_filter( $post_ids );
		if ( empty( $post_ids ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );
		$sql          = $wpdb->prepare(
			'SELECT * FROM ' . self::submissions_table() . " WHERE post_id IN ({$placeholders}) ORDER BY posted_at DESC, id DESC",
			$post_ids
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$by_post = array();
		foreach ( $rows as $row ) {
			$by_post[ (int) $row['post_id'] ][] = $row;
		}
		return $by_post;
	}

	public static function list_submissions( $limit = 100, $offset = 0 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::submissions_table() . ' ORDER BY id DESC LIMIT %d OFFSET %d',
				(int) $limit,
				(int) $offset
			),
			ARRAY_A
		);
	}

	public static function delete_submission( $id ) {
		global $wpdb;
		return $wpdb->delete( self::submissions_table(), array( 'id' => (int) $id ) );
	}
}
