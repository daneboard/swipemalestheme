<?php
/**
 * Scraping engine — fetches URLs, parses HTML, extracts video data.
 */

defined( 'ABSPATH' ) || exit;

class TSVI_Scraper {

	/**
	 * Discover video page links from a listing/search page.
	 *
	 * @param string $url   Listing page URL.
	 * @param int    $limit Max links to return.
	 * @return array|WP_Error Array of absolute URLs.
	 */
	public static function discover_links( $url, $limit = 20 ) {
		$html = self::fetch( $url );
		if ( is_wp_error( $html ) ) {
			return $html;
		}

		$base_url = self::base_url( $url );
		$links    = array();

		libxml_use_internal_errors( true );
		$doc = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();

		$anchors = $doc->getElementsByTagName( 'a' );

		foreach ( $anchors as $a ) {
			$href  = $a->getAttribute( 'href' );
			$title = trim( $a->getAttribute( 'title' ) ?: $a->textContent );

			if ( empty( $href ) || $href === '#' || strpos( $href, 'javascript:' ) === 0 ) {
				continue;
			}

			// Make absolute.
			$abs = self::absolute_url( $href, $base_url );

			// Filter: likely video page links (heuristic).
			if ( self::looks_like_video_link( $abs, $a ) ) {
				$thumb = self::find_nearby_thumbnail( $a );
				$links[ $abs ] = array(
					'url'       => $abs,
					'title'     => sanitize_text_field( mb_substr( $title, 0, 200 ) ),
					'thumbnail' => $thumb ? self::absolute_url( $thumb, $base_url ) : '',
				);
			}

			if ( count( $links ) >= $limit ) {
				break;
			}
		}

		return array_values( $links );
	}

	/**
	 * Extract video data from a single video page.
	 *
	 * @param string $url Video page URL.
	 * @return array|WP_Error Video data array.
	 */
	public static function extract_video( $url ) {
		$html = self::fetch( $url );
		if ( is_wp_error( $html ) ) {
			return $html;
		}

		$base_url = self::base_url( $url );
		$video    = array(
			'source_url'  => $url,
			'title'       => '',
			'description' => '',
			'video_url'   => '',
			'thumbnail'   => '',
			'duration'    => 0,
			'width'       => 0,
			'height'      => 0,
			'source_tags' => array(),
			'embed'       => '',
		);

		libxml_use_internal_errors( true );
		$doc = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
		libxml_clear_errors();

		$xpath = new DOMXPath( $doc );

		// 1. Title — og:title, <title>, h1.
		$video['title'] = self::extract_meta( $xpath, 'og:title' )
			?: self::extract_tag_content( $doc, 'title' )
			?: self::extract_first( $xpath, '//h1' );

		// 2. Description — og:description, meta description.
		$video['description'] = self::extract_meta( $xpath, 'og:description' )
			?: self::extract_meta_name( $xpath, 'description' );

		// 3. Thumbnail — og:image.
		$thumb = self::extract_meta( $xpath, 'og:image' );
		if ( $thumb ) {
			$video['thumbnail'] = self::absolute_url( $thumb, $base_url );
		}

		// 4. Video URL — try multiple strategies.
		$video['video_url'] = self::extract_video_url( $doc, $xpath, $html, $base_url );

		// 5. Duration — og:video:duration, JSON-LD, or itemprop.
		$dur = self::extract_meta( $xpath, 'video:duration' )
			?: self::extract_meta_name( $xpath, 'duration' );
		if ( $dur ) {
			$video['duration'] = intval( $dur );
		}

		// 6. Dimensions.
		$w = self::extract_meta( $xpath, 'og:video:width' );
		$h = self::extract_meta( $xpath, 'og:video:height' );
		if ( $w && $h ) {
			$video['width']  = intval( $w );
			$video['height'] = intval( $h );
		}

		// 7. Tags — keywords meta, tag links.
		$keywords = self::extract_meta_name( $xpath, 'keywords' );
		if ( $keywords ) {
			$video['source_tags'] = array_map( 'trim', explode( ',', $keywords ) );
		}
		if ( empty( $video['source_tags'] ) ) {
			$video['source_tags'] = self::extract_tag_links( $xpath );
		}

		// 8. JSON-LD fallback for all fields.
		self::parse_json_ld( $html, $video, $base_url );

		// 9. Embed fallback — og:video:url or og:video.
		if ( empty( $video['video_url'] ) ) {
			$embed_url = self::extract_meta( $xpath, 'og:video:url' )
				?: self::extract_meta( $xpath, 'og:video' );
			if ( $embed_url ) {
				$video['embed'] = '<iframe src="' . esc_url( $embed_url ) . '" width="720" height="1280" frameborder="0" allowfullscreen></iframe>';
			}
		}

		$video['title'] = sanitize_text_field( $video['title'] );

		return $video;
	}

	/* ------------------------------------------------------------------
	   Video URL extraction strategies
	   ------------------------------------------------------------------ */

	private static function extract_video_url( $doc, $xpath, $html, $base_url ) {
		// A) <video> / <source> elements.
		$sources = $xpath->query( '//video/source[@src]|//video[@src]' );
		foreach ( $sources as $el ) {
			$src  = $el->getAttribute( 'src' );
			$type = $el->getAttribute( 'type' );
			if ( $src && self::is_video_file( $src ) ) {
				return self::absolute_url( $src, $base_url );
			}
		}

		// B) og:video meta with direct file URL.
		$og_video = self::extract_meta( $xpath, 'og:video' );
		if ( $og_video && self::is_video_file( $og_video ) ) {
			return self::absolute_url( $og_video, $base_url );
		}
		$og_video_url = self::extract_meta( $xpath, 'og:video:url' );
		if ( $og_video_url && self::is_video_file( $og_video_url ) ) {
			return self::absolute_url( $og_video_url, $base_url );
		}

		// C) Scan inline scripts for video URLs.
		$mp4 = self::scan_scripts_for_video( $html );
		if ( $mp4 ) {
			return $mp4;
		}

		// D) Scan all href/src attributes for direct video files.
		$all_attrs = array();
		preg_match_all( '/(?:src|href|data-src|data-video-url|data-video|content)=["\']([^"\']*\.(?:mp4|m3u8|webm)[^"\']*)/i', $html, $all_attrs );
		if ( ! empty( $all_attrs[1] ) ) {
			return self::absolute_url( html_entity_decode( $all_attrs[1][0] ), $base_url );
		}

		return '';
	}

	/**
	 * Scan inline <script> blocks for video URLs.
	 */
	private static function scan_scripts_for_video( $html ) {
		// Common patterns in video players.
		$patterns = array(
			// "videoUrl": "..."
			'/["\'](?:video_?[Uu]rl|video_?[Ss]rc|file|source|mp4|hls_?url|stream_?url|content_?url)["\']:\s*["\']([^"\']+\.(?:mp4|m3u8|webm)[^"\']*)/i',
			// var video_url = "..."
			'/(?:video_?url|videoSrc|videoFile|source)\s*[:=]\s*["\']([^"\']+\.(?:mp4|m3u8|webm)[^"\']*)/i',
			// Bare MP4 URLs in scripts.
			'/https?:\/\/[^\s"\'<>]+\.mp4(?:\?[^\s"\'<>]*)?/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $html, $m ) ) {
				$url = isset( $m[1] ) ? $m[1] : $m[0];
				$url = html_entity_decode( $url );
				// Basic sanity check.
				if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
					return $url;
				}
			}
		}

		return '';
	}

	/**
	 * Parse JSON-LD VideoObject for additional data.
	 */
	private static function parse_json_ld( $html, &$video, $base_url ) {
		preg_match_all( '/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $ld_matches );
		foreach ( $ld_matches[1] as $ld_raw ) {
			$ld = json_decode( trim( $ld_raw ), true );
			if ( ! $ld ) {
				continue;
			}

			// Handle @graph arrays.
			$items = isset( $ld['@graph'] ) ? $ld['@graph'] : array( $ld );
			foreach ( $items as $item ) {
				if ( ! isset( $item['@type'] ) ) {
					continue;
				}
				if ( $item['@type'] !== 'VideoObject' ) {
					continue;
				}

				if ( empty( $video['title'] ) && ! empty( $item['name'] ) ) {
					$video['title'] = $item['name'];
				}
				if ( empty( $video['description'] ) && ! empty( $item['description'] ) ) {
					$video['description'] = $item['description'];
				}
				if ( empty( $video['thumbnail'] ) && ! empty( $item['thumbnailUrl'] ) ) {
					$t = is_array( $item['thumbnailUrl'] ) ? $item['thumbnailUrl'][0] : $item['thumbnailUrl'];
					$video['thumbnail'] = self::absolute_url( $t, $base_url );
				}
				if ( empty( $video['video_url'] ) && ! empty( $item['contentUrl'] ) ) {
					$video['video_url'] = $item['contentUrl'];
				}
				if ( empty( $video['duration'] ) && ! empty( $item['duration'] ) ) {
					$video['duration'] = self::parse_iso_duration( $item['duration'] );
				}
				if ( empty( $video['width'] ) && ! empty( $item['width'] ) ) {
					$video['width'] = intval( $item['width'] );
				}
				if ( empty( $video['height'] ) && ! empty( $item['height'] ) ) {
					$video['height'] = intval( $item['height'] );
				}
			}
		}
	}

	/* ------------------------------------------------------------------
	   Helpers
	   ------------------------------------------------------------------ */

	public static function fetch( $url ) {
		$args = array(
			'timeout'    => 15,
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			'sslverify'  => false,
			'headers'    => array(
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language' => 'en-US,en;q=0.9',
			),
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			return new WP_Error( 'http_error', 'HTTP ' . $code . ' fetching ' . $url );
		}

		return wp_remote_retrieve_body( $response );
	}

	private static function extract_meta( $xpath, $property ) {
		$nodes = $xpath->query( '//meta[@property="' . $property . '"]/@content' );
		return $nodes->length ? trim( $nodes->item( 0 )->nodeValue ) : '';
	}

	private static function extract_meta_name( $xpath, $name ) {
		$nodes = $xpath->query( '//meta[@name="' . $name . '"]/@content' );
		return $nodes->length ? trim( $nodes->item( 0 )->nodeValue ) : '';
	}

	private static function extract_tag_content( $doc, $tag ) {
		$els = $doc->getElementsByTagName( $tag );
		return $els->length ? trim( $els->item( 0 )->textContent ) : '';
	}

	private static function extract_first( $xpath, $query ) {
		$nodes = $xpath->query( $query );
		return $nodes->length ? trim( $nodes->item( 0 )->textContent ) : '';
	}

	private static function extract_tag_links( $xpath ) {
		$tags  = array();
		$nodes = $xpath->query( '//a[contains(@href,"tag") or contains(@class,"tag")]' );
		foreach ( $nodes as $n ) {
			$t = trim( $n->textContent );
			if ( $t && mb_strlen( $t ) < 50 ) {
				$tags[] = $t;
			}
			if ( count( $tags ) >= 10 ) {
				break;
			}
		}
		return $tags;
	}

	private static function is_video_file( $url ) {
		return (bool) preg_match( '/\.(mp4|m3u8|webm)(\?|$)/i', $url );
	}

	private static function looks_like_video_link( $url, $anchor ) {
		// Heuristic: URL contains /video/, /watch, /view, /embed, or has a thumbnail child.
		if ( preg_match( '#/(video|watch|view|embed|play|clip)/|/video[_-]#i', $url ) ) {
			return true;
		}
		// Has an img child (thumbnail grid).
		$imgs = $anchor->getElementsByTagName( 'img' );
		if ( $imgs->length > 0 ) {
			return true;
		}
		return false;
	}

	private static function find_nearby_thumbnail( $anchor ) {
		$imgs = $anchor->getElementsByTagName( 'img' );
		if ( $imgs->length > 0 ) {
			return $imgs->item( 0 )->getAttribute( 'data-src' )
				?: $imgs->item( 0 )->getAttribute( 'src' );
		}
		return '';
	}

	private static function base_url( $url ) {
		$parsed = wp_parse_url( $url );
		return $parsed['scheme'] . '://' . $parsed['host'];
	}

	private static function absolute_url( $href, $base ) {
		if ( strpos( $href, 'http' ) === 0 ) {
			return $href;
		}
		if ( strpos( $href, '//' ) === 0 ) {
			return 'https:' . $href;
		}
		return rtrim( $base, '/' ) . '/' . ltrim( $href, '/' );
	}

	private static function parse_iso_duration( $iso ) {
		if ( preg_match( '/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $iso, $m ) ) {
			return ( intval( $m[1] ?? 0 ) * 3600 ) + ( intval( $m[2] ?? 0 ) * 60 ) + intval( $m[3] ?? 0 );
		}
		return 0;
	}
}
