<?php
/**
 * Imports scraped video data into WordPress posts compatible with TikSwipe theme.
 *
 * Handles: Bunny.net upload, title cleaning, smart tag/category mapping,
 * deduplication, and triggering save_post hooks for third-party plugins.
 */

defined( 'ABSPATH' ) || exit;

class TSVI_Importer {

	/**
	 * Sites and patterns to strip from titles.
	 */
	private static $strip_sites = array(
		'xgroovy', 'xgaytube', 'gay4', 'pornhub', 'xvideos', 'xhamster',
		'redtube', 'tube8', 'xtube', 'gaytube', 'xnxx', 'youporn',
		'spankbang', 'eporner', 'tnaflix', 'drtuber', 'hclips', 'txxx',
		'voyeurhit', 'nuvid', 'tubedupe', 'sleazyneasy', 'anyporn',
	);

	/**
	 * Import a single video as a WordPress post.
	 *
	 * @param array $video Video data from scraper/AI.
	 * @return int|WP_Error Post ID or error.
	 */
	public static function import( $video ) {
		// Clean the title first.
		$video['title'] = self::clean_title( $video['title'] ?? '' );

		// Deduplication: check video_url AND source page URL.
		if ( ! empty( $video['video_url'] ) ) {
			$existing = self::find_by_video_url( $video['video_url'] );
			if ( $existing ) {
				return new WP_Error( 'duplicate', 'Video already imported as post #' . $existing );
			}
		}
		if ( ! empty( $video['source_url'] ) ) {
			$existing = self::find_by_video_url( $video['source_url'] );
			if ( $existing ) {
				return new WP_Error( 'duplicate', 'Video page already imported as post #' . $existing );
			}
		}

		// Smart tag/category mapping.
		$taxonomy   = self::map_tags_to_categories( $video['tags'] ?? array() );
		$cat_ids    = $taxonomy['category_ids'];
		$extra_tags = $taxonomy['tags'];

		// Add AI/manual category if provided.
		$ai_cat = self::resolve_category( $video['category'] ?? '' );
		if ( $ai_cat && ! in_array( $ai_cat, $cat_ids, true ) ) {
			$cat_ids[] = $ai_cat;
		}

		// Ensure at least one category.
		if ( empty( $cat_ids ) ) {
			$default = intval( get_option( 'tsvi_default_category', 0 ) );
			if ( $default ) {
				$cat_ids[] = $default;
			}
		}

		// If Bunny is enabled, always start as draft → publish after upload.
		// Otherwise use the configured default status.
		$status = TSVI_Bunny::is_enabled() ? 'draft' : get_option( 'tsvi_default_status', 'draft' );

		$post_data = array(
			'post_title'    => $video['title'] ?: 'Untitled Video',
			'post_content'  => sanitize_text_field( $video['description'] ?? '' ),
			'post_status'   => $status,
			'post_type'     => 'post',
			'post_author'   => get_current_user_id(),
			'post_category' => $cat_ids,
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set post format to video.
		set_post_format( $post_id, 'video' );

		// Set tags (max 3 extra that don't match categories).
		if ( ! empty( $extra_tags ) ) {
			wp_set_post_tags( $post_id, $extra_tags );
		}

		// --- Video URL: save external URL now, queue Bunny upload for background ---
		$final_video_url = $video['video_url'] ?? '';
		$bunny_status    = 'disabled';

		if ( ! empty( $final_video_url ) ) {
			update_post_meta( $post_id, 'video_url', esc_url_raw( $final_video_url ) );
		}

		if ( ! empty( $final_video_url ) && TSVI_Bunny::is_enabled() ) {
			// Queue for background upload instead of blocking here.
			update_post_meta( $post_id, '_tsvi_bunny_pending', $final_video_url );
			TSVI_Bunny::schedule_upload( $post_id );
			$bunny_status = 'queued';
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
		if ( ! empty( $final_video_url ) ) {
			$ext = self::get_extension( $final_video_url );
			if ( $ext ) {
				update_post_meta( $post_id, '_video_extension', $ext );
			}
		}

		// Initialize views.
		update_post_meta( $post_id, 'post_views_count', '0' );

		// Store original source URL for reference.
		if ( ! empty( $video['source_url'] ) ) {
			update_post_meta( $post_id, '_tsvi_source_url', esc_url_raw( $video['source_url'] ) );
		}

		// NO thumbnail download — user has a plugin that generates it on save.

		// Trigger save_post hooks so third-party plugins (thumbnail generator, etc.) fire.
		wp_update_post( array( 'ID' => $post_id ) );

		return array(
			'post_id'      => $post_id,
			'bunny_status' => $bunny_status,
			'video_url'    => $final_video_url,
		);
	}

	/* ------------------------------------------------------------------
	   Title cleaning
	   ------------------------------------------------------------------ */

	/**
	 * Clean a scraped title: remove sites, URLs, hyphens as separators, junk.
	 */
	public static function clean_title( $title ) {
		if ( empty( $title ) ) {
			return '';
		}

		// Remove URLs.
		$title = preg_replace( '#https?://[^\s<>"\']+#i', '', $title );

		// Remove site names (case insensitive, word boundaries).
		foreach ( self::$strip_sites as $site ) {
			$title = preg_replace( '/\b' . preg_quote( $site, '/' ) . '(?:\.com|\.net|\.org)?\b/i', '', $title );
		}

		// Remove common suffixes: "- SiteName", "| SiteName", "— SiteName".
		$title = preg_replace( '/\s*[\-–—\|]\s*$/u', '', $title );

		// Remove ".com", ".net" etc. leftovers.
		$title = preg_replace( '/\.(com|net|org|xxx|tv)\b/i', '', $title );

		// Replace hyphens between words with spaces (but keep hyphens in numbers).
		$title = preg_replace( '/(?<=[a-zA-Z])-(?=[a-zA-Z])/', ' ', $title );

		// Clean up multiple spaces.
		$title = preg_replace( '/\s+/', ' ', $title );

		// Trim junk characters from edges.
		$title = trim( $title, " \t\n\r\0\x0B-–—|:,." );

		return $title;
	}

	/* ------------------------------------------------------------------
	   Smart tag/category mapping
	   ------------------------------------------------------------------ */

	/**
	 * Map scraped tags to existing WP categories.
	 * Tags that match a category → assign that category.
	 * Remaining tags → keep max 3 as actual tags.
	 *
	 * @param array $tags Source tags from scraper/AI.
	 * @return array { category_ids: int[], tags: string[] }
	 */
	private static function map_tags_to_categories( $tags ) {
		$max_extra_tags = 3;

		$all_cats   = get_categories( array( 'hide_empty' => false ) );
		$cat_lookup = array();
		foreach ( $all_cats as $cat ) {
			$cat_lookup[ mb_strtolower( $cat->name ) ] = $cat->term_id;
			// Also index by slug.
			$cat_lookup[ $cat->slug ] = $cat->term_id;
		}

		$matched_cat_ids = array();
		$remaining_tags  = array();

		foreach ( $tags as $tag ) {
			$tag_clean = sanitize_text_field( $tag );
			$lower     = mb_strtolower( $tag_clean );

			if ( isset( $cat_lookup[ $lower ] ) ) {
				$cat_id = $cat_lookup[ $lower ];
				if ( ! in_array( $cat_id, $matched_cat_ids, true ) ) {
					$matched_cat_ids[] = $cat_id;
				}
			} else {
				if ( count( $remaining_tags ) < $max_extra_tags && mb_strlen( $tag_clean ) > 1 ) {
					$remaining_tags[] = $tag_clean;
				}
			}
		}

		return array(
			'category_ids' => $matched_cat_ids,
			'tags'         => $remaining_tags,
		);
	}

	/* ------------------------------------------------------------------
	   Helpers
	   ------------------------------------------------------------------ */

	/**
	 * Check if a video URL already exists in any post (checks video_url,
	 * pending Bunny queue, and original source URL).
	 */
	private static function find_by_video_url( $url ) {
		global $wpdb;
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key IN ('video_url', '_tsvi_bunny_pending', '_tsvi_source_url')
				 AND meta_value = %s
				 LIMIT 1",
				$url
			)
		);
	}

	/**
	 * Resolve category name to ID (case-insensitive match).
	 */
	private static function resolve_category( $name ) {
		if ( empty( $name ) ) {
			return 0;
		}

		$all_cats = get_categories( array( 'hide_empty' => false ) );
		foreach ( $all_cats as $cat ) {
			if ( mb_strtolower( $cat->name ) === mb_strtolower( $name ) ) {
				return $cat->term_id;
			}
		}

		return 0;
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
