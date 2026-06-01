<?php
/**
 * Post submission + subreddit introspection (requirements, flairs).
 */

defined( 'ABSPATH' ) || exit;

class TSRP_Submitter {

	/**
	 * Fetch subreddit capabilities and cache for 1h.
	 *
	 * @return array|WP_Error Shape: {
	 *   subreddit, title_min, title_max, allow_images, allow_videos,
	 *   allow_galleries, link_type, flair_required, subreddit_type,
	 *   over18, submit_text, submit_text_html,
	 *   flairs: [ { id, text, text_editable, background_color } ]
	 * }
	 */
	public static function get_subreddit_info( $subreddit, $user_id = null ) {
		$subreddit = self::clean_subreddit( $subreddit );
		if ( ! $subreddit ) {
			return new WP_Error( 'tsrp_bad_subreddit', __( 'Missing subreddit name.', 'tikswipe-reddit-poster' ) );
		}
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$key     = 'tsrp_sr_' . md5( $subreddit . '|' . $user_id );
		$cached  = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$about = TSRP_API::get( 'r/' . $subreddit . '/about' );
		if ( is_wp_error( $about ) ) {
			return $about;
		}
		$data = isset( $about['data'] ) ? $about['data'] : array();

		$req = TSRP_API::get( 'api/v1/' . $subreddit . '/post_requirements' );
		if ( is_wp_error( $req ) ) {
			$req = array();
		}

		$flairs = TSRP_API::get( 'r/' . $subreddit . '/api/link_flair_v2' );
		if ( is_wp_error( $flairs ) || ! is_array( $flairs ) ) {
			$flairs = array();
		}

		$info = array(
			'subreddit'        => $subreddit,
			'display_name'     => isset( $data['display_name_prefixed'] ) ? $data['display_name_prefixed'] : ( 'r/' . $subreddit ),
			'subreddit_type'   => isset( $data['subreddit_type'] ) ? $data['subreddit_type'] : 'public',
			'over18'           => ! empty( $data['over18'] ),
			'allow_images'     => ! empty( $data['allow_images'] ),
			'allow_videos'     => ! empty( $data['allow_videos'] ),
			'allow_galleries'  => ! empty( $data['allow_galleries'] ),
			'allow_polls'      => ! empty( $data['allow_polls'] ),
			'link_type'        => isset( $data['submission_type'] ) ? $data['submission_type'] : ( isset( $data['link_type'] ) ? $data['link_type'] : 'any' ),
			'title_min'        => isset( $req['title_text_min_length'] ) ? (int) $req['title_text_min_length'] : 1,
			'title_max'        => isset( $req['title_text_max_length'] ) ? (int) $req['title_text_max_length'] : 300,
			'flair_required'   => ! empty( $req['is_flair_required'] ),
			'body_policy'      => isset( $req['body_restriction_policy'] ) ? $req['body_restriction_policy'] : 'notRequired',
			'domain_whitelist' => isset( $req['domain_whitelist'] ) ? $req['domain_whitelist'] : array(),
			'domain_blacklist' => isset( $req['domain_blacklist'] ) ? $req['domain_blacklist'] : array(),
			'submit_text'      => isset( $data['submit_text'] ) ? $data['submit_text'] : '',
			'flairs'           => array_values( array_map(
				function ( $f ) {
					return array(
						'id'              => isset( $f['id'] ) ? $f['id'] : '',
						'text'            => isset( $f['text'] ) ? $f['text'] : '',
						'text_editable'   => ! empty( $f['text_editable'] ),
						'background_color'=> isset( $f['background_color'] ) ? $f['background_color'] : '',
						'text_color'      => isset( $f['text_color'] ) ? $f['text_color'] : '',
					);
				},
				is_array( $flairs ) ? $flairs : array()
			) ),
		);
		set_transient( $key, $info, HOUR_IN_SECONDS );
		return $info;
	}

	public static function clear_subreddit_cache( $subreddit, $user_id = null ) {
		$subreddit = self::clean_subreddit( $subreddit );
		$user_id   = $user_id ? (int) $user_id : get_current_user_id();
		delete_transient( 'tsrp_sr_' . md5( $subreddit . '|' . $user_id ) );
	}

	/**
	 * Search subreddits the user is subscribed to.
	 */
	public static function search_my_subreddits( $query, $user_id = null ) {
		$resp = TSRP_API::get(
			'subreddits/mine/subscriber',
			array( 'limit' => 100 ),
			$user_id
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$children = isset( $resp['data']['children'] ) ? $resp['data']['children'] : array();
		$out      = array();
		$query    = strtolower( trim( (string) $query ) );
		foreach ( $children as $c ) {
			$d = isset( $c['data'] ) ? $c['data'] : array();
			if ( empty( $d['display_name'] ) ) {
				continue;
			}
			if ( $query === '' || strpos( strtolower( $d['display_name'] ), $query ) !== false || strpos( strtolower( $d['title'] ?? '' ), $query ) !== false ) {
				$out[] = array(
					'name'        => $d['display_name'],
					'title'       => isset( $d['title'] ) ? $d['title'] : '',
					'subscribers' => isset( $d['subscribers'] ) ? (int) $d['subscribers'] : 0,
					'over18'      => ! empty( $d['over18'] ),
					'icon'        => isset( $d['community_icon'] ) ? $d['community_icon'] : '',
				);
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	   Submit
	   ------------------------------------------------------------------ */

	/**
	 * Submit a post. Returns array with { id, name, url } on success or WP_Error.
	 *
	 * @param array $args {
	 *   subreddit (string, required)
	 *   kind      (self|link|image)
	 *   title     (string, required)
	 *   text      (string, optional, for self)
	 *   url       (string, optional, for link)
	 *   image_url (string, optional, for image — server-reachable URL)
	 *   flair_id  (string, optional)
	 *   flair_text (string, optional)
	 *   nsfw      (bool, optional)
	 *   spoiler   (bool, optional)
	 *   send_replies (bool, optional, default true)
	 * }
	 */
	public static function submit( array $args, $user_id = null ) {
		$args = wp_parse_args( $args, array(
			'subreddit'    => '',
			'kind'         => 'self',
			'title'        => '',
			'text'         => '',
			'url'          => '',
			'image_url'    => '',
			'flair_id'     => '',
			'flair_text'   => '',
			'nsfw'         => false,
			'spoiler'      => false,
			'send_replies' => true,
		) );
		$args['subreddit'] = self::clean_subreddit( $args['subreddit'] );
		if ( ! $args['subreddit'] || ! $args['title'] ) {
			return new WP_Error( 'tsrp_missing_fields', __( 'Subreddit and title are required.', 'tikswipe-reddit-poster' ) );
		}

		$body = array(
			'sr'          => $args['subreddit'],
			'title'       => wp_strip_all_tags( $args['title'] ),
			'resubmit'    => 'true',
			'sendreplies' => $args['send_replies'] ? 'true' : 'false',
			'nsfw'        => $args['nsfw'] ? 'true' : 'false',
			'spoiler'     => $args['spoiler'] ? 'true' : 'false',
			'api_type'    => 'json',
		);
		if ( $args['flair_id'] ) {
			$body['flair_id'] = $args['flair_id'];
		}
		if ( $args['flair_text'] ) {
			$body['flair_text'] = $args['flair_text'];
		}

		switch ( $args['kind'] ) {
			case 'self':
				$body['kind'] = 'self';
				$body['text'] = (string) $args['text'];
				break;
			case 'link':
				if ( ! $args['url'] ) {
					return new WP_Error( 'tsrp_missing_url', __( 'URL is required for link posts.', 'tikswipe-reddit-poster' ) );
				}
				$body['kind'] = 'link';
				$body['url']  = esc_url_raw( $args['url'] );
				break;
			case 'image':
				if ( ! $args['image_url'] ) {
					return new WP_Error( 'tsrp_missing_image', __( 'Image URL is required for image posts.', 'tikswipe-reddit-poster' ) );
				}
				$uploaded = self::upload_media( $args['image_url'], $user_id );
				if ( is_wp_error( $uploaded ) ) {
					return $uploaded;
				}
				$body['kind'] = 'image';
				$body['url']  = $uploaded['asset_url'];
				break;
			default:
				return new WP_Error( 'tsrp_bad_kind', __( 'Unsupported post kind.', 'tikswipe-reddit-poster' ) );
		}

		$resp = TSRP_API::post( 'api/submit', $body, $user_id );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		// Normal: resp.json.data = { url, id, name, drafts_count }
		$data = isset( $resp['json']['data'] ) ? $resp['json']['data'] : array();
		if ( empty( $data['id'] ) || empty( $data['url'] ) ) {
			return new WP_Error(
				'tsrp_submit_no_id',
				__( 'Reddit did not return a post ID.', 'tikswipe-reddit-poster' ),
				array( 'body' => $resp )
			);
		}
		return array(
			'id'       => $data['id'],
			'name'     => ! empty( $data['name'] ) ? $data['name'] : ( 't3_' . $data['id'] ),
			'url'      => $data['url'],
		);
	}

	/**
	 * Upload an image to Reddit media storage and return the asset URL to use
	 * as the `url` field of an image submission.
	 *
	 * Flow:
	 *  1) POST /api/media/asset.json → presigned S3 form
	 *  2) multipart POST to S3
	 *  3) Return https://i.redd.it/<asset_id>.<ext>
	 */
	public static function upload_media( $image_url, $user_id = null ) {
		$image_url = esc_url_raw( $image_url );
		if ( ! $image_url ) {
			return new WP_Error( 'tsrp_bad_image_url', 'Invalid image URL' );
		}

		// Pull the image bytes (works for local attachments and remote URLs).
		$bytes = self::fetch_image_bytes( $image_url );
		if ( is_wp_error( $bytes ) ) {
			return $bytes;
		}
		$filename = basename( wp_parse_url( $image_url, PHP_URL_PATH ) );
		if ( ! $filename ) {
			$filename = 'upload.jpg';
		}
		$mime = wp_check_filetype( $filename );
		$mime = $mime && ! empty( $mime['type'] ) ? $mime['type'] : 'image/jpeg';
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif' ), true ) ) {
			return new WP_Error( 'tsrp_bad_mime', 'Only JPEG, PNG or GIF images are supported.' );
		}

		$lease = TSRP_API::post( 'api/media/asset.json', array(
			'filepath' => $filename,
			'mimetype' => $mime,
		), $user_id );
		if ( is_wp_error( $lease ) ) {
			return $lease;
		}

		$action_url = isset( $lease['args']['action'] ) ? $lease['args']['action'] : '';
		$fields     = isset( $lease['args']['fields'] ) ? $lease['args']['fields'] : array();
		$asset_id   = isset( $lease['asset']['asset_id'] ) ? $lease['asset']['asset_id'] : '';
		if ( strpos( $action_url, '//' ) === 0 ) {
			$action_url = 'https:' . $action_url;
		}
		if ( ! $action_url || ! $asset_id ) {
			return new WP_Error( 'tsrp_lease_invalid', 'Reddit did not return an upload lease.', array( 'body' => $lease ) );
		}

		$boundary = wp_generate_password( 24, false, false );
		$payload  = self::build_multipart( $fields, $bytes['content'], $filename, $mime, $boundary );

		$upload = wp_remote_post( $action_url, array(
			'timeout' => 90,
			'headers' => array(
				'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
				'User-Agent'   => TSRP_OAuth::user_agent(),
			),
			'body'    => $payload,
		) );
		if ( is_wp_error( $upload ) ) {
			return $upload;
		}
		$code = (int) wp_remote_retrieve_response_code( $upload );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'tsrp_s3_error', 'S3 upload failed: HTTP ' . $code, array(
				'body' => substr( (string) wp_remote_retrieve_body( $upload ), 0, 500 ),
			) );
		}

		$ext = pathinfo( $filename, PATHINFO_EXTENSION );
		if ( ! $ext ) {
			$ext = $mime === 'image/png' ? 'png' : ( $mime === 'image/gif' ? 'gif' : 'jpg' );
		}
		return array(
			'asset_id'  => $asset_id,
			'asset_url' => 'https://i.redd.it/' . $asset_id . '.' . $ext,
		);
	}

	protected static function fetch_image_bytes( $url ) {
		// Prefer direct filesystem read for local attachments.
		$local = self::url_to_local_path( $url );
		if ( $local && file_exists( $local ) ) {
			return array( 'content' => file_get_contents( $local ) );
		}
		$resp = wp_remote_get( $url, array( 'timeout' => 60 ) );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( $code !== 200 ) {
			return new WP_Error( 'tsrp_image_fetch', 'Could not download image: HTTP ' . $code );
		}
		return array( 'content' => wp_remote_retrieve_body( $resp ) );
	}

	protected static function url_to_local_path( $url ) {
		$uploads = wp_get_upload_dir();
		if ( isset( $uploads['baseurl'], $uploads['basedir'] ) && strpos( $url, $uploads['baseurl'] ) === 0 ) {
			return str_replace( $uploads['baseurl'], $uploads['basedir'], $url );
		}
		return '';
	}

	protected static function build_multipart( array $fields, $file_bytes, $filename, $mime, $boundary ) {
		$body = '';
		foreach ( $fields as $field ) {
			if ( ! isset( $field['name'] ) ) {
				continue;
			}
			$body .= "--{$boundary}\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $field['name'] . "\"\r\n\r\n";
			$body .= ( isset( $field['value'] ) ? $field['value'] : '' ) . "\r\n";
		}
		$body .= "--{$boundary}\r\n";
		$body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . "\"\r\n";
		$body .= 'Content-Type: ' . $mime . "\r\n\r\n";
		$body .= $file_bytes . "\r\n";
		$body .= "--{$boundary}--\r\n";
		return $body;
	}

	/* ------------------------------------------------------------------
	   Comments
	   ------------------------------------------------------------------ */

	public static function comment( $thing_fullname, $text, $user_id = null ) {
		$resp = TSRP_API::post( 'api/comment', array(
			'thing_id' => $thing_fullname,
			'text'     => (string) $text,
			'api_type' => 'json',
		), $user_id );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$things = isset( $resp['json']['data']['things'] ) ? $resp['json']['data']['things'] : array();
		$first  = isset( $things[0]['data'] ) ? $things[0]['data'] : array();
		if ( empty( $first['id'] ) ) {
			return new WP_Error( 'tsrp_comment_no_id', 'Reddit did not return a comment id.', array( 'body' => $resp ) );
		}
		return array(
			'id'   => $first['id'],
			'name' => isset( $first['name'] ) ? $first['name'] : ( 't1_' . $first['id'] ),
			'url'  => isset( $first['permalink'] ) ? 'https://www.reddit.com' . $first['permalink'] : '',
		);
	}

	/* ------------------------------------------------------------------
	   Helpers
	   ------------------------------------------------------------------ */

	public static function clean_subreddit( $s ) {
		$s = (string) $s;
		$s = preg_replace( '#^https?://(www\.)?reddit\.com/r/#i', '', $s );
		$s = trim( $s, " /\t\n\r\0\x0B" );
		$s = preg_replace( '#^r/#i', '', $s );
		$s = preg_replace( '/[^A-Za-z0-9_]/', '', $s );
		return $s;
	}
}
