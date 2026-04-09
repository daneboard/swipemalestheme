<?php
/**
 * Simple activity log for tracking imports, uploads, and errors.
 * Stored in a dedicated log file, viewable from admin.
 */

defined( 'ABSPATH' ) || exit;

class TSVI_Log {

	private static $log_file = null;

	private static function get_log_file() {
		if ( self::$log_file === null ) {
			$upload_dir    = wp_upload_dir();
			$log_dir       = $upload_dir['basedir'] . '/tsvi-logs';
			if ( ! is_dir( $log_dir ) ) {
				wp_mkdir_p( $log_dir );
				// Protect from web access.
				file_put_contents( $log_dir . '/.htaccess', 'Deny from all' );
				file_put_contents( $log_dir . '/index.php', '<?php // Silence.' );
			}
			self::$log_file = $log_dir . '/activity-' . date( 'Y-m' ) . '.log';
		}
		return self::$log_file;
	}

	/**
	 * Write a log entry.
	 *
	 * @param string $type    Type: import, upload, error, scrape, direct, system.
	 * @param string $message Description.
	 * @param array  $context Optional key-value data.
	 */
	public static function write( $type, $message, $context = array() ) {
		$ts   = current_time( 'Y-m-d H:i:s' );
		$line = "[{$ts}] [{$type}] {$message}";
		if ( ! empty( $context ) ) {
			$line .= ' | ' . json_encode( $context, JSON_UNESCAPED_SLASHES );
		}
		$line .= "\n";
		file_put_contents( self::get_log_file(), $line, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Shorthand methods.
	 */
	public static function import( $message, $context = array() ) {
		self::write( 'import', $message, $context );
	}

	public static function upload( $message, $context = array() ) {
		self::write( 'upload', $message, $context );
	}

	public static function error( $message, $context = array() ) {
		self::write( 'error', $message, $context );
	}

	public static function scrape( $message, $context = array() ) {
		self::write( 'scrape', $message, $context );
	}

	/**
	 * Read the last N lines of the current log.
	 */
	public static function tail( $lines = 100 ) {
		$file = self::get_log_file();
		if ( ! file_exists( $file ) ) {
			return '';
		}
		$all = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( ! $all ) {
			return '';
		}
		$tail = array_slice( $all, -$lines );
		return implode( "\n", array_reverse( $tail ) );
	}

	/**
	 * Get available log files (for month selector).
	 */
	public static function get_log_files() {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/tsvi-logs';
		if ( ! is_dir( $log_dir ) ) {
			return array();
		}
		$files = glob( $log_dir . '/activity-*.log' );
		return $files ? array_map( 'basename', $files ) : array();
	}

	/**
	 * Read a specific log file.
	 */
	public static function read_file( $filename, $lines = 200 ) {
		// Sanitize filename to prevent directory traversal.
		$filename = basename( $filename );
		if ( ! preg_match( '/^activity-\d{4}-\d{2}\.log$/', $filename ) ) {
			return '';
		}
		$upload_dir = wp_upload_dir();
		$path       = $upload_dir['basedir'] . '/tsvi-logs/' . $filename;
		if ( ! file_exists( $path ) ) {
			return '';
		}
		$all = file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( ! $all ) {
			return '';
		}
		$tail = array_slice( $all, -$lines );
		return implode( "\n", array_reverse( $tail ) );
	}
}
