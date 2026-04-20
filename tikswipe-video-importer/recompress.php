#!/usr/bin/env php
<?php
/**
 * TikSwipe Video Importer — Recompress existing Bunny CDN videos.
 *
 * Downloads each video from Bunny CDN, transcodes to 720p via FFmpeg,
 * re-uploads to the same path (overwrites), and marks the post as done.
 *
 * Usage:
 *   php recompress.php              — process next batch (3 videos)
 *   php recompress.php --all        — process ALL pending videos (no batch limit)
 *   php recompress.php --status     — show recompress stats
 *   php recompress.php --reset      — reset all recompress marks (re-do everything)
 *
 * Designed to run via crontab alongside worker.php:
 *   * * * * * php /path/to/recompress.php >> /dev/null 2>&1
 *
 * Or run once manually: php recompress.php --all
 */

if ( php_sapi_name() !== 'cli' ) {
	die( 'CLI only.' );
}

// Find wp-load.php.
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

define( 'DOING_CRON', true );
require_once $dir . '/wp-load.php';

$arg = $argv[1] ?? '';

// --status
if ( $arg === '--status' ) {
	$stats = tsvi_recompress_stats();
	echo "=== Recompress Status ===\n";
	echo "Total uploaded:     {$stats['total']}\n";
	echo "Already compressed: {$stats['done']}\n";
	echo "Skipped (small):    {$stats['skipped']}\n";
	echo "Errors:             {$stats['errors']}\n";
	echo "Pending:            {$stats['pending']}\n";
	exit( 0 );
}

// --reset (clears EVERYTHING)
if ( $arg === '--reset' ) {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_tsvi_recompressed', '_tsvi_recompress_error', '_tsvi_recompress_skipped')" );
	delete_option( 'tsvi_recompress_stats' );
	echo "Reset done. All videos can be reprocessed.\n";
	exit( 0 );
}

// --reset-errors (clears only error markers so they can retry)
if ( $arg === '--reset-errors' ) {
	global $wpdb;
	$count = $wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_recompress_error'" );
	echo "Cleared " . intval( $count ) . " error markers. They will be retried on next run.\n";
	exit( 0 );
}

// Prevent concurrent runs.
$lock_key = 'tsvi_recompress_lock';
if ( get_transient( $lock_key ) ) {
	echo "Already running (lock active). Exiting.\n";
	exit( 0 );
}
set_transient( $lock_key, getmypid(), 3600 );
register_shutdown_function( function () use ( $lock_key ) {
	delete_transient( $lock_key );
} );

$batch_size = ( $arg === '--all' ) ? 9999 : 3;

rclog( 'Recompress started (PID ' . getmypid() . ', batch=' . $batch_size . ')' );

// Check FFmpeg.
if ( ! TSVI_Bunny::ffmpeg_available() ) {
	rclog( 'ERROR: FFmpeg not installed. Aborting.' );
	exit( 1 );
}

global $wpdb;

// Find uploaded videos that haven't been recompressed yet.
$post_ids = $wpdb->get_col( $wpdb->prepare(
	"SELECT pm.post_id FROM {$wpdb->postmeta} pm
	 WHERE pm.meta_key = '_tsvi_bunny_status' AND pm.meta_value = 'uploaded'
	 AND pm.post_id NOT IN (
	   SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_recompressed'
	 )
	 AND pm.post_id NOT IN (
	   SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_recompress_skipped'
	 )
	 AND pm.post_id NOT IN (
	   SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_recompress_error'
	 )
	 LIMIT %d",
	$batch_size
) );

if ( empty( $post_ids ) ) {
	rclog( 'Nothing to recompress.' );
	tsvi_save_recompress_stats();
	exit( 0 );
}

$total     = count( $post_ids );
$api_key   = get_option( 'tsvi_bunny_api_key', '' );
$zone      = get_option( 'tsvi_bunny_storage_zone', '' );
$region    = get_option( 'tsvi_bunny_storage_region', '' );
$hostname  = get_option( 'tsvi_bunny_cdn_hostname', '' );

rclog( "Found {$total} videos to recompress." );

foreach ( $post_ids as $i => $post_id ) {
	$post_id = intval( $post_id );
	$title   = get_the_title( $post_id );
	$n       = $i + 1;

	// Get current video URL (strip token params).
	$video_url = get_post_meta( $post_id, 'video_url', true );
	if ( empty( $video_url ) ) {
		rclog( "  [{$n}/{$total}] #{$post_id}: no video_url — skipping." );
		update_post_meta( $post_id, '_tsvi_recompress_skipped', 'no_url' );
		continue;
	}

	// Strip token/signature params to get the clean CDN URL.
	$clean_url = TSVI_Bunny::strip_token_params_public( $video_url );

	// Check if this is actually a Bunny CDN URL.
	if ( $hostname && strpos( $clean_url, $hostname ) === false ) {
		rclog( "  [{$n}/{$total}] #{$post_id}: not a Bunny URL — skipping." );
		update_post_meta( $post_id, '_tsvi_recompress_skipped', 'not_bunny' );
		continue;
	}

	// Extract the storage path from the CDN URL.
	// CDN:     https://myzone.b-cdn.net/videos/123_slug.mp4
	// Storage: https://storage.bunnycdn.com/my-zone/videos/123_slug.mp4
	$cdn_path_only = wp_parse_url( $clean_url, PHP_URL_PATH );

	rclog( "  [{$n}/{$total}] #{$post_id}: " . mb_substr( $title, 0, 50 ) );

	// Step 1: Download from Bunny STORAGE API (bypasses CDN hotlink/token protection).
	$host = 'storage.bunnycdn.com';
	if ( $region && $region !== 'default' ) {
		$host = $region . '.' . $host;
	}
	$storage_download_url = 'https://' . $host . '/' . $zone . $cdn_path_only;

	$tmp = wp_tempnam( 'tsvi_rc_' );
	$download = tsvi_rc_download_storage( $storage_download_url, $tmp, $api_key );

	if ( is_wp_error( $download ) ) {
		// Fallback: try CDN URL directly (works if no hotlink/token protection).
		rclog( "  [{$n}/{$total}] Storage download failed, trying CDN direct..." );
		$download = tsvi_rc_download( $clean_url, $tmp );
	}

	if ( is_wp_error( $download ) ) {
		rclog( "  [{$n}/{$total}] DOWNLOAD FAILED: " . $download->get_error_message() );
		update_post_meta( $post_id, '_tsvi_recompress_error', 'download: ' . $download->get_error_message() );
		@unlink( $tmp );
		continue;
	}

	$original_size = filesize( $tmp );
	$original_mb   = round( $original_size / 1048576, 1 );

	// Skip if already small (< 20 MB).
	if ( $original_size < 20000000 ) {
		rclog( "  [{$n}/{$total}] SKIPPED: already {$original_mb}MB (< 20MB threshold)." );
		update_post_meta( $post_id, '_tsvi_recompress_skipped', 'small_' . $original_mb . 'MB' );
		@unlink( $tmp );
		continue;
	}

	rclog( "  [{$n}/{$total}] Downloaded {$original_mb}MB — transcoding..." );

	// Step 2: Transcode via FFmpeg.
	$output = $tmp . '_720p.mp4';
	$ffmpeg = TSVI_Bunny::ffmpeg_binary_path();

	$cmd = escapeshellcmd( $ffmpeg )
		. ' -y -i ' . escapeshellarg( $tmp )
		. ' -vf "scale=-2:720"'
		. ' -c:v libx264 -crf 23 -preset medium'
		. ' -c:a aac -b:a 128k'
		. ' -movflags +faststart'
		. ' -threads 0'
		. ' ' . escapeshellarg( $output )
		. ' 2>&1';

	$ffmpeg_output = @shell_exec( $cmd );
	$success = file_exists( $output ) && filesize( $output ) > 10000;

	if ( ! $success ) {
		rclog( "  [{$n}/{$total}] TRANSCODE FAILED: " . mb_substr( $ffmpeg_output ?? '', -200 ) );
		update_post_meta( $post_id, '_tsvi_recompress_error', 'transcode: ' . mb_substr( $ffmpeg_output ?? '', -150 ) );
		@unlink( $tmp );
		@unlink( $output );
		continue;
	}

	$new_size = filesize( $output );
	$new_mb   = round( $new_size / 1048576, 1 );
	$saved    = round( ( 1 - $new_size / $original_size ) * 100 );

	// If the transcoded file is larger or barely smaller, skip.
	if ( $new_size >= $original_size * 0.9 ) {
		rclog( "  [{$n}/{$total}] SKIPPED: transcoded {$new_mb}MB >= 90% of original {$original_mb}MB — not worth it." );
		update_post_meta( $post_id, '_tsvi_recompress_skipped', 'no_gain_' . $original_mb . 'MB->' . $new_mb . 'MB' );
		@unlink( $tmp );
		@unlink( $output );
		continue;
	}

	rclog( "  [{$n}/{$total}] Transcoded: {$original_mb}MB → {$new_mb}MB (saved {$saved}%) — uploading..." );

	// Step 3: Re-upload to Bunny (same path = overwrites).
	$cdn_path = wp_parse_url( $clean_url, PHP_URL_PATH );
	// Ensure .mp4 extension.
	$cdn_path = preg_replace( '/\.[^.]+$/', '.mp4', $cdn_path );

	$host = 'storage.bunnycdn.com';
	if ( $region && $region !== 'default' ) {
		$host = $region . '.' . $host;
	}
	$storage_url = 'https://' . $host . '/' . $zone . $cdn_path;

	$upload_result = tsvi_rc_upload( $storage_url, $output, $api_key );

	@unlink( $tmp );
	@unlink( $output );

	if ( is_wp_error( $upload_result ) ) {
		rclog( "  [{$n}/{$total}] UPLOAD FAILED: " . $upload_result->get_error_message() );
		update_post_meta( $post_id, '_tsvi_recompress_error', 'upload: ' . $upload_result->get_error_message() );
		continue;
	}

	// Step 4: Update post meta if extension changed.
	$new_cdn_url = TSVI_Bunny::get_cdn_url( ltrim( $cdn_path, '/' ) );
	$old_clean   = $clean_url;
	if ( $new_cdn_url !== $old_clean ) {
		// Remove old filter to update raw URL.
		remove_filter( 'get_post_metadata', array( 'TSVI_Bunny', 'filter_video_url' ), 10 );
		update_post_meta( $post_id, 'video_url', esc_url_raw( $new_cdn_url ) );
		add_filter( 'get_post_metadata', array( 'TSVI_Bunny', 'filter_video_url' ), 10, 4 );
	}

	// Mark as recompressed.
	update_post_meta( $post_id, '_tsvi_recompressed', current_time( 'Y-m-d H:i' ) . '|' . $original_mb . 'MB->' . $new_mb . 'MB' );

	TSVI_Log::write( 'upload', 'Recompressed #' . $post_id . ': ' . $original_mb . 'MB → ' . $new_mb . 'MB (saved ' . $saved . '%)' );

	rclog( "  [{$n}/{$total}] OK — saved {$saved}%." );
}

tsvi_save_recompress_stats();
rclog( 'Recompress finished.' );
exit( 0 );

/* ------------------------------------------------------------------ */

function tsvi_recompress_stats() {
	global $wpdb;
	$total   = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_bunny_status' AND meta_value = 'uploaded'" ) );
	$done    = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_recompressed'" ) );
	$skipped = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_recompress_skipped'" ) );
	$errors  = intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_tsvi_recompress_error'" ) );
	$pending = $total - $done - $skipped - $errors;
	return compact( 'total', 'done', 'skipped', 'errors', 'pending' );
}

function tsvi_save_recompress_stats() {
	update_option( 'tsvi_recompress_stats', tsvi_recompress_stats(), false );
}

function tsvi_rc_download( $url, $dest ) {
	$ch = curl_init();
	$fp = fopen( $dest, 'wb' );
	if ( ! $fp ) {
		return new WP_Error( 'file', 'Cannot open temp file.' );
	}
	curl_setopt_array( $ch, array(
		CURLOPT_URL            => $url,
		CURLOPT_FILE           => $fp,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS      => 5,
		CURLOPT_TIMEOUT        => 600,
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_USERAGENT      => 'TikSwipe-Recompress/1.0',
	) );
	$result    = curl_exec( $ch );
	$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$error     = curl_error( $ch );
	curl_close( $ch );
	fclose( $fp );
	if ( ! $result || $http_code >= 400 ) {
		return new WP_Error( 'download', "HTTP {$http_code}: {$error}" );
	}
	return true;
}

/**
 * Download from Bunny Storage API (uses AccessKey, bypasses CDN protection).
 */
function tsvi_rc_download_storage( $storage_url, $dest, $api_key ) {
	if ( empty( $api_key ) ) {
		return new WP_Error( 'no_key', 'Bunny API key missing.' );
	}
	$ch = curl_init();
	$fp = fopen( $dest, 'wb' );
	if ( ! $fp ) {
		return new WP_Error( 'file', 'Cannot open temp file.' );
	}
	curl_setopt_array( $ch, array(
		CURLOPT_URL            => $storage_url,
		CURLOPT_FILE           => $fp,
		CURLOPT_TIMEOUT        => 600,
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_HTTPHEADER     => array(
			'AccessKey: ' . $api_key,
			'Accept: */*',
		),
	) );
	$result    = curl_exec( $ch );
	$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$error     = curl_error( $ch );
	curl_close( $ch );
	fclose( $fp );
	if ( ! $result || $http_code >= 400 ) {
		return new WP_Error( 'storage_download', "Storage HTTP {$http_code}: {$error}" );
	}
	return true;
}

function tsvi_rc_upload( $storage_url, $file_path, $api_key ) {
	$fp = fopen( $file_path, 'rb' );
	if ( ! $fp ) {
		return new WP_Error( 'file', 'Cannot open file for upload.' );
	}
	$ch = curl_init();
	curl_setopt_array( $ch, array(
		CURLOPT_URL            => $storage_url,
		CURLOPT_CUSTOMREQUEST  => 'PUT',
		CURLOPT_UPLOAD         => true,
		CURLOPT_INFILE         => $fp,
		CURLOPT_INFILESIZE     => filesize( $file_path ),
		CURLOPT_TIMEOUT        => 600,
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER     => array(
			'AccessKey: ' . $api_key,
			'Content-Type: application/octet-stream',
		),
	) );
	$result    = curl_exec( $ch );
	$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	$error     = curl_error( $ch );
	curl_close( $ch );
	fclose( $fp );
	if ( $http_code < 200 || $http_code >= 300 ) {
		return new WP_Error( 'upload', "HTTP {$http_code}: {$result} ({$error})" );
	}
	return true;
}

function rclog( $msg ) {
	static $log_file = null;
	if ( $log_file === null ) {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/tsvi-logs';
		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}
		$log_file = $log_dir . '/recompress.log';
	}
	$line = '[' . date( 'Y-m-d H:i:s' ) . '] ' . $msg . "\n";
	file_put_contents( $log_file, $line, FILE_APPEND | LOCK_EX );
	echo $line;
}
