<?php
/**
 * Imports scraped video data into WordPress posts compatible with TikSwipe theme.
 */

defined( 'ABSPATH' ) || exit;

class TSVI_Importer {

	/**
	 * Import a single video as a WordPress post.
	 *
	 * @param array $video Video data from scraper/AI.
	 * @return int|WP_Error Post ID or error.
	 */
	public static function import( $video ) {
		// Deduplication: check if video_url already exists.
		if ( ! empty( $video['video_url'] ) ) {
			$existing = self::find_by_video_url( $video['video_url'] );
			if ( $existing ) {
				return new WP_Error( 'duplicate', 'Video already imported as post #' . $existing );
			}
		}

		$status   = get_option( 'tsvi_default_status', 'draft' );
		$cat_id   = self::resolve_category( $video['category'] ?? '' );
		$tag_ids  = self::resolve_tags( $video['tags'] ?? array() );

		$post_data = array(
			'post_title'   => $video['title'] ?: 'Untitled Video',
			'post_content' => $video['description'] ?? '',
			'post_status'  => $status,
			'post_type'    => 'post',
			'post_author'  => get_current_user_id(),
		);

		if ( $cat_id ) {
			$post_data['post_category'] = array( $cat_id );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set post format to video.
		set_post_format( $post_id, 'video' );

		// Set tags.
		if ( ! empty( $tag_ids ) ) {
			wp_set_post_tags( $post_id, $tag_ids );
		}

		// Set TikSwipe meta fields.
		if ( ! empty( $video['video_url'] ) ) {
			update_post_meta( $post_id, 'video_url', esc_url_raw( $video['video_url'] ) );
		}
		if ( ! empty( $video['embed'] ) ) {
			update_post_meta( $post_id, 'embed', $video['embed'] );
		}
		if ( ! empty( $video['duration'] ) ) {
			update_post_meta( $post_id, 'duration', intval( $video['duration'] ) );
		}

		$width  = intval( $video['width'] ?? 0 );
		$height = intval( $video['height'] ?? 0 );
		if ( $width && $height ) {
			update_post_meta( $post_id, '_video_width', $width );
			update_post_meta( $post_id, '_video_height', $height );
		}

		// Detect portrait.
		if ( $height > $width && $width > 0 ) {
			update_post_meta( $post_id, '_file_format', 'portrait' );
		}

		// Video extension.
		if ( ! empty( $video['video_url'] ) ) {
			$ext = self::get_extension( $video['video_url'] );
			if ( $ext ) {
				update_post_meta( $post_id, '_video_extension', $ext );
			}
		}

		// Initialize views.
		update_post_meta( $post_id, 'post_views_count', '0' );

		// Download and set thumbnail.
		if ( ! empty( $video['thumbnail'] ) ) {
			$thumb_id = self::sideload_image( $video['thumbnail'], $post_id, $video['title'] );
			if ( $thumb_id && ! is_wp_error( $thumb_id ) ) {
				set_post_thumbnail( $post_id, $thumb_id );
			}
		}

		return $post_id;
	}

	/**
	 * Check if a video_url already exists in any post.
	 */
	private static function find_by_video_url( $url ) {
		global $wpdb;
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'video_url' AND meta_value = %s LIMIT 1",
				$url
			)
		);
	}

	/**
	 * Resolve category name to ID, create if needed.
	 */
	private static function resolve_category( $name ) {
		if ( empty( $name ) ) {
			return intval( get_option( 'tsvi_default_category', 0 ) );
		}

		$term = get_term_by( 'name', $name, 'category' );
		if ( $term ) {
			return $term->term_id;
		}

		// Try case-insensitive match.
		$all_cats = get_categories( array( 'hide_empty' => false ) );
		foreach ( $all_cats as $cat ) {
			if ( strtolower( $cat->name ) === strtolower( $name ) ) {
				return $cat->term_id;
			}
		}

		// Create new category.
		$new = wp_insert_term( $name, 'category' );
		if ( ! is_wp_error( $new ) ) {
			return $new['term_id'];
		}

		return intval( get_option( 'tsvi_default_category', 0 ) );
	}

	/**
	 * Resolve tag names to tag name strings for wp_set_post_tags.
	 */
	private static function resolve_tags( $tags ) {
		return array_filter( array_map( 'sanitize_text_field', $tags ) );
	}

	/**
	 * Download external image and attach to post.
	 */
	private static function sideload_image( $url, $post_id, $desc = '' ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 10 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$file_array = array(
			'name'     => sanitize_file_name( basename( wp_parse_url( $url, PHP_URL_PATH ) ) ),
			'tmp_name' => $tmp,
		);

		$attach_id = media_handle_sideload( $file_array, $post_id, $desc );

		if ( is_wp_error( $attach_id ) ) {
			@unlink( $tmp );
		}

		return $attach_id;
	}

	/**
	 * Extract file extension from URL.
	 */
	private static function get_extension( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$ext  = pathinfo( $path, PATHINFO_EXTENSION );
		return strtolower( $ext ) ?: 'mp4';
	}
}
