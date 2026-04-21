<?php
/**
 * Live Reddit analytics. Batches up to 100 fullnames per /api/info call and
 * caches the result in a 5-minute transient. Updates the submissions table with
 * the latest stats snapshot on each refresh.
 */

defined( 'ABSPATH' ) || exit;

class TSRP_Analytics {

	const CACHE_TTL = 300; // 5 min
	const BATCH     = 100;

	/**
	 * Return stats keyed by reddit fullname.
	 *
	 * @param array $fullnames Reddit fullnames like "t3_xxx".
	 * @param int|null $user_id WP user owning the Reddit account used to read.
	 * @param bool $force Skip cache.
	 */
	public static function get_stats( array $fullnames, $user_id = null, $force = false ) {
		$fullnames = array_values( array_unique( array_filter( array_map( 'strval', $fullnames ) ) ) );
		$out       = array();
		$missing   = array();

		foreach ( $fullnames as $fn ) {
			$cached = $force ? false : get_transient( 'tsrp_stats_' . $fn );
			if ( is_array( $cached ) ) {
				$out[ $fn ] = $cached;
			} else {
				$missing[] = $fn;
			}
		}
		if ( empty( $missing ) ) {
			return $out;
		}

		$chunks = array_chunk( $missing, self::BATCH );
		foreach ( $chunks as $chunk ) {
			$resp = TSRP_API::get( 'api/info', array( 'id' => implode( ',', $chunk ) ), $user_id );
			if ( is_wp_error( $resp ) ) {
				TSRP_Log::error( 'analytics_fetch', $resp->get_error_message() );
				continue;
			}
			$children = isset( $resp['data']['children'] ) ? $resp['data']['children'] : array();
			foreach ( $children as $c ) {
				$d = isset( $c['data'] ) ? $c['data'] : array();
				if ( empty( $d['name'] ) ) {
					continue;
				}
				$stats = array(
					'score'         => isset( $d['score'] ) ? (int) $d['score'] : 0,
					'ups'           => isset( $d['ups'] ) ? (int) $d['ups'] : 0,
					'upvote_ratio'  => isset( $d['upvote_ratio'] ) ? (float) $d['upvote_ratio'] : 0.0,
					'num_comments'  => isset( $d['num_comments'] ) ? (int) $d['num_comments'] : 0,
					'num_crossposts'=> isset( $d['num_crossposts'] ) ? (int) $d['num_crossposts'] : 0,
					'view_count'    => isset( $d['view_count'] ) ? (int) $d['view_count'] : null,
					'awards'        => isset( $d['total_awards_received'] ) ? (int) $d['total_awards_received'] : 0,
					'over_18'       => ! empty( $d['over_18'] ),
					'archived'      => ! empty( $d['archived'] ),
					'removed'       => isset( $d['removed_by_category'] ) ? $d['removed_by_category'] : null,
					'permalink'     => isset( $d['permalink'] ) ? ( 'https://www.reddit.com' . $d['permalink'] ) : '',
					'fetched_at'    => time(),
				);
				set_transient( 'tsrp_stats_' . $d['name'], $stats, self::CACHE_TTL );
				$out[ $d['name'] ] = $stats;
				self::persist_snapshot( $d['name'], $stats );
			}
		}
		return $out;
	}

	protected static function persist_snapshot( $fullname, array $stats ) {
		global $wpdb;
		$wpdb->update(
			TSRP_DB::submissions_table(),
			array(
				'last_stats'    => wp_json_encode( $stats ),
				'last_stats_at' => current_time( 'mysql' ),
			),
			array( 'reddit_fullname' => $fullname )
		);
	}

	public static function format_badge( $stats ) {
		if ( ! is_array( $stats ) ) {
			return '';
		}
		$parts = array();
		$parts[] = '▲ ' . number_format_i18n( $stats['ups'] );
		$parts[] = '💬 ' . number_format_i18n( $stats['num_comments'] );
		if ( isset( $stats['view_count'] ) && $stats['view_count'] !== null ) {
			$parts[] = '👁 ' . number_format_i18n( $stats['view_count'] );
		}
		if ( ! empty( $stats['upvote_ratio'] ) ) {
			$parts[] = round( $stats['upvote_ratio'] * 100 ) . '%';
		}
		return implode( ' · ', $parts );
	}
}
