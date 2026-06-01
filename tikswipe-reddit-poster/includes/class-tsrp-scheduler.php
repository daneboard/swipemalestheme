<?php
/**
 * Schedules the +3 minute follow-up comment, with Action Scheduler when
 * available and wp_cron as fallback.
 */

defined( 'ABSPATH' ) || exit;

class TSRP_Scheduler {

	const HOOK  = 'tsrp_post_followup_comment';
	const DELAY = 180; // seconds

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ), 10, 1 );
	}

	public static function schedule( $submission_id ) {
		$submission_id = (int) $submission_id;
		$when          = time() + self::DELAY;

		TSRP_DB::update_submission( $submission_id, array(
			'comment_status'        => 'scheduled',
			'comment_scheduled_for' => gmdate( 'Y-m-d H:i:s', $when ),
		) );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $when, self::HOOK, array( $submission_id ), 'tsrp' );
			return 'action_scheduler';
		}
		wp_schedule_single_event( $when, self::HOOK, array( $submission_id ) );
		return 'wp_cron';
	}

	public static function run( $submission_id ) {
		$submission_id = (int) $submission_id;
		$submission    = TSRP_DB::get_submission( $submission_id );
		if ( ! $submission ) {
			return;
		}
		if ( ! empty( $submission['comment_id'] ) ) {
			return; // already commented
		}
		if ( empty( $submission['reddit_fullname'] ) ) {
			TSRP_DB::update_submission( $submission_id, array(
				'comment_status' => 'failed',
				'last_error'     => 'No reddit_fullname to comment on',
			) );
			return;
		}

		$template = (string) get_option( 'tsrp_comment_template', 'The full video: {url}' );
		$text     = self::render_template( $template, $submission );

		$result = TSRP_Submitter::comment( $submission['reddit_fullname'], $text, (int) $submission['user_id'] );
		if ( is_wp_error( $result ) ) {
			TSRP_DB::update_submission( $submission_id, array(
				'comment_status' => 'failed',
				'last_error'     => $result->get_error_message(),
			) );
			TSRP_Log::error( 'comment_failed', $result->get_error_message(), array(), $submission['post_id'], $submission_id );
			return;
		}

		TSRP_DB::update_submission( $submission_id, array(
			'comment_id'        => $result['id'],
			'comment_status'    => 'posted',
			'comment_posted_at' => current_time( 'mysql' ),
		) );
		TSRP_Log::info( 'comment_posted', $result['url'], array(), $submission['post_id'], $submission_id );
	}

	protected static function render_template( $template, array $submission ) {
		$post  = get_post( $submission['post_id'] );
		$url   = get_permalink( $submission['post_id'] );
		$title = $post ? $post->post_title : '';
		return strtr( $template, array(
			'{url}'       => $url,
			'{title}'     => $title,
			'{subreddit}' => 'r/' . $submission['subreddit'],
		) );
	}
}
