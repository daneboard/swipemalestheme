#!/usr/bin/env php
<?php
/**
 * TikSwipe Video Importer — CLI Worker
 *
 * Processes the Bunny CDN upload queue and direct upload queue outside
 * of the web server, avoiding nginx/Apache timeouts entirely.
 *
 * Setup: Add to system crontab (runs every minute):
 *   * * * * * php /path/to/wp-content/plugins/tikswipe-video-importer/worker.php >> /dev/null 2>&1
 *
 * Can also be run manually:
 *   php worker.php          — process next batch (3 items)
 *   php worker.php --once   — process 1 item and exit
 *   php worker.php --status — show queue status
 */

// Must be CLI.
if ( php_sapi_name() !== 'cli' ) {
	die( 'CLI only.' );
}

// Find wp-load.php by walking up from plugin directory.
$dir = __DIR__;
for ( $i = 0; $i < 10; $i++ ) {
	if ( file_exists( $dir . '/wp-load.php' ) ) {
		break;
	}
	$dir = dirname( $dir );
}

if ( ! file_exists( $dir . '/wp-load.php' ) ) {
	fwrite( STDERR, "Error: Could not find wp-load.php\n" );
	exit( 1 );
}

// Bootstrap WordPress.
define( 'DOING_CRON', true );
define( 'TSVI_CLI_WORKER', true );
require_once $dir . '/wp-load.php';

// Parse args.
$arg = $argv[1] ?? '';

// --status: show queue info and exit.
if ( $arg === '--status' ) {
	global $wpdb;
	$pending = $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->postmeta}
		 WHERE meta_key = '_tsvi_bunny_pending' AND meta_value != ''"
	);
	$failed = $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->postmeta}
		 WHERE meta_key = '_tsvi_bunny_error' AND meta_value != ''"
	);
	$uploaded = $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->postmeta}
		 WHERE meta_key = '_tsvi_bunny_status' AND meta_value = 'uploaded'"
	);
	$direct_queue = count( get_option( 'tsvi_direct_upload_queue', array() ) );

	echo "=== TikSwipe CDN Queue Status ===\n";
	echo "CDN Pending:    {$pending}\n";
	echo "CDN Failed:     {$failed}\n";
	echo "CDN Uploaded:   {$uploaded}\n";
	echo "Direct Pending: {$direct_queue}\n";
	echo "Lock active:    " . ( get_transient( 'tsvi_cli_lock' ) ? 'YES' : 'no' ) . "\n";
	exit( 0 );
}

// Prevent concurrent CLI runs (separate lock from web cron).
$lock_key = 'tsvi_cli_lock';
if ( get_transient( $lock_key ) ) {
	// Already running.
	exit( 0 );
}
set_transient( $lock_key, getmypid(), 1800 );

// Register shutdown to always clean up lock.
register_shutdown_function( function () use ( $lock_key ) {
	delete_transient( $lock_key );
} );

$batch_size = ( $arg === '--once' ) ? 1 : 3;

tsvi_log( 'Worker started (PID ' . getmypid() . ', batch=' . $batch_size . ')' );

// ---- Process CDN Queue (post uploads) ----
$processed_cdn = tsvi_process_cdn_queue( $batch_size );

// ---- Process Direct Upload Queue ----
$processed_direct = tsvi_process_direct_queue( $batch_size );

if ( $processed_cdn === 0 && $processed_direct === 0 ) {
	tsvi_log( 'Nothing to process.' );
}

tsvi_log( 'Worker finished.' );
exit( 0 );

/**
 * Process the CDN upload queue (posts with _tsvi_bunny_pending).
 */
function tsvi_process_cdn_queue( $batch_size ) {
	global $wpdb;

	$post_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT pm.post_id FROM {$wpdb->postmeta} pm
		 LEFT JOIN {$wpdb->postmeta} dur ON pm.post_id = dur.post_id AND dur.meta_key = 'duration'
		 WHERE pm.meta_key = '_tsvi_bunny_pending'
		 AND pm.meta_value != ''
		 ORDER BY CAST(COALESCE(dur.meta_value, '999999') AS UNSIGNED) ASC
		 LIMIT %d",
		$batch_size
	) );

	if ( empty( $post_ids ) ) {
		return 0;
	}

	$total = count( $post_ids );
	tsvi_log( "CDN queue: {$total} items to process." );

	foreach ( $post_ids as $i => $post_id ) {
		$post_id = intval( $post_id );
		$title   = get_the_title( $post_id );
		tsvi_log( "  [" . ( $i + 1 ) . "/{$total}] Processing #{$post_id}: " . mb_substr( $title, 0, 50 ) );

		try {
			TSVI_Bunny::process_single( $post_id );

			// Check result.
			$error = get_post_meta( $post_id, '_tsvi_bunny_error', true );
			if ( $error ) {
				tsvi_log( "  [" . ( $i + 1 ) . "/{$total}] FAILED: {$error}" );
			} else {
				tsvi_log( "  [" . ( $i + 1 ) . "/{$total}] OK — uploaded to CDN." );
			}
		} catch ( \Throwable $e ) {
			update_post_meta( $post_id, '_tsvi_bunny_pending', '' );
			update_post_meta( $post_id, '_tsvi_bunny_error', 'Fatal: ' . $e->getMessage() );
			tsvi_log( "  [" . ( $i + 1 ) . "/{$total}] FATAL: " . $e->getMessage() );
		}
	}

	return $total;
}

/**
 * Process the direct upload queue (URLs from wp_options).
 */
function tsvi_process_direct_queue( $batch_size ) {
	$queue = get_option( 'tsvi_direct_upload_queue', array() );
	if ( empty( $queue ) ) {
		return 0;
	}

	$batch = array_splice( $queue, 0, $batch_size );
	update_option( 'tsvi_direct_upload_queue', $queue, false );

	$total = count( $batch );
	tsvi_log( "Direct queue: {$total} items to process." );

	foreach ( $batch as $i => $item ) {
		$url = $item['url'];
		tsvi_log( "  [" . ( $i + 1 ) . "/{$total}] Uploading: " . mb_substr( $url, 0, 80 ) );

		TSVI_Bunny::process_single_direct( $url );

		// Check history for result.
		$history = get_option( 'tsvi_direct_upload_history', array() );
		$last    = end( $history );
		if ( $last && ! empty( $last['error'] ) ) {
			tsvi_log( "  [" . ( $i + 1 ) . "/{$total}] FAILED: " . $last['error'] );
		} else {
			tsvi_log( "  [" . ( $i + 1 ) . "/{$total}] OK — " . ( $last['cdn_url'] ?? '?' ) );
		}
	}

	return $total;
}

/**
 * Log a message with timestamp.
 * Logs are written to wp-content/uploads/tsvi-logs/ (NOT inside the plugin folder,
 * so plugin updates don't fail due to permission conflicts).
 */
function tsvi_log( $msg ) {
	static $log_file = null;

	if ( $log_file === null ) {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/tsvi-logs';
		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
			file_put_contents( $log_dir . '/.htaccess', 'Deny from all' );
		}
		$log_file = $log_dir . '/worker.log';
	}

	$ts   = date( 'Y-m-d H:i:s' );
	$line = "[{$ts}] {$msg}\n";

	file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );

	// Also output to stdout (visible when running manually).
	echo $line;
}
