<?php
/**
 * Log writer backed by the wp_tsrp_log table.
 */

defined( 'ABSPATH' ) || exit;

class TSRP_Log {

	public static function info( $event, $message = '', array $context = array(), $post_id = null, $submission_id = null ) {
		self::write( 'info', $event, $message, $context, $post_id, $submission_id );
	}

	public static function error( $event, $message = '', array $context = array(), $post_id = null, $submission_id = null ) {
		self::write( 'error', $event, $message, $context, $post_id, $submission_id );
	}

	protected static function write( $level, $event, $message, array $context, $post_id, $submission_id ) {
		global $wpdb;
		$wpdb->insert(
			TSRP_DB::log_table(),
			array(
				'created_at'    => current_time( 'mysql' ),
				'user_id'       => get_current_user_id(),
				'post_id'       => $post_id ? (int) $post_id : null,
				'submission_id' => $submission_id ? (int) $submission_id : null,
				'level'         => $level,
				'event'         => substr( (string) $event, 0, 64 ),
				'message'       => (string) $message,
				'context'       => $context ? wp_json_encode( $context ) : null,
			)
		);
	}

	public static function tail( $limit = 200 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . TSRP_DB::log_table() . ' ORDER BY id DESC LIMIT %d',
				(int) $limit
			),
			ARRAY_A
		);
	}

	public static function purge_older_than( $days = 30 ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . TSRP_DB::log_table() . ' WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)',
				(int) $days
			)
		);
	}
}
