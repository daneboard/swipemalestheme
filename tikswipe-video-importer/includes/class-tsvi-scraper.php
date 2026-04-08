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

		$xpath = new DOMXPath( $doc );

		// Strategy 1: Items with data-video-id attribute (xgroovy-style).
		$video_items = $xpath->query( '//*[@data-video-id]' );
		foreach ( $video_items as $item ) {
			if ( count( $links ) >= $limit ) {
				break;
			}
			$a = $xpath->query( './/a[@href]', $item );
			if ( ! $a->length ) {
				continue;
			}
			$anchor = $a->item( 0 );
			$href   = $anchor->getAttribute( 'href' );
			if ( empty( $href ) || $href === '#' ) {
				continue;
			}
			$abs = self::absolute_url( $href, $base_url );
			if ( isset( $links[ $abs ] ) ) {
				continue;
			}

			$title = trim( $anchor->getAttribute( 'title' ) );
			if ( ! $title ) {
				$strong = $xpath->query( './/strong|.//h3|.//h2', $item );
				$title  = $strong->length ? trim( $strong->item( 0 )->textContent ) : '';
			}
			$thumb    = self::find_nearby_thumbnail_in( $item, $xpath );
			$duration = self::find_duration_text( $item );

			$links[ $abs ] = array(
				'url'       => $abs,
				'title'     => sanitize_text_field( mb_substr( $title, 0, 200 ) ),
				'thumbnail' => $thumb ? self::absolute_url( $thumb, $base_url ) : '',
				'duration'  => $duration,
			);
		}

		// Strategy 2: Article/div items with duration text (generic tube sites).
		if ( empty( $links ) ) {
			$containers = $xpath->query( '//article[contains(@class,"thumb")]|//div[contains(@class,"thumb")]|//div[contains(@class,"video-item")]|//div[contains(@class,"video_block")]|//li[contains(@class,"video")]' );
			foreach ( $containers as $item ) {
				if ( count( $links ) >= $limit ) {
					break;
				}
				// Must have duration text to qualify as video.
				$duration = self::find_duration_text( $item );
				if ( ! $duration ) {
					continue;
				}
				$a = $xpath->query( './/a[@href]', $item );
				if ( ! $a->length ) {
					continue;
				}
				$anchor = $a->item( 0 );
				$href   = $anchor->getAttribute( 'href' );
				if ( empty( $href ) || $href === '#' ) {
					continue;
				}
				$abs = self::absolute_url( $href, $base_url );
				if ( isset( $links[ $abs ] ) ) {
					continue;
				}

				$title = trim( $anchor->getAttribute( 'title' ) );
				if ( ! $title ) {
					$headings = $xpath->query( './/h3|.//h2|.//strong[@class]', $item );
					$title    = $headings->length ? trim( $headings->item( 0 )->textContent ) : '';
				}
				$thumb = self::find_nearby_thumbnail_in( $item, $xpath );

				$links[ $abs ] = array(
					'url'       => $abs,
					'title'     => sanitize_text_field( mb_substr( $title, 0, 200 ) ),
					'thumbnail' => $thumb ? self::absolute_url( $thumb, $base_url ) : '',
					'duration'  => $duration,
				);
			}
		}

		// Strategy 3: Fallback — <a> tags with video URL patterns + thumbnail.
		if ( empty( $links ) ) {
			$anchors = $doc->getElementsByTagName( 'a' );
			foreach ( $anchors as $a ) {
				if ( count( $links ) >= $limit ) {
					break;
				}
				$href = $a->getAttribute( 'href' );
				if ( empty( $href ) || $href === '#' || strpos( $href, 'javascript:' ) === 0 ) {
					continue;
				}
				$abs = self::absolute_url( $href, $base_url );
				if ( isset( $links[ $abs ] ) ) {
					continue;
				}
				// Must match strict video URL pattern.
				if ( ! preg_match( '#/(video|watch|view|play|clip)s?/\d+#i', $abs ) ) {
					continue;
				}
				// Must have an img child.
				$imgs = $a->getElementsByTagName( 'img' );
				if ( ! $imgs->length ) {
					continue;
				}

				$title = trim( $a->getAttribute( 'title' ) ?: $a->textContent );
				$thumb = $imgs->item( 0 )->getAttribute( 'data-src' )
					?: $imgs->item( 0 )->getAttribute( 'src' );

				$links[ $abs ] = array(
					'url'       => $abs,
					'title'     => sanitize_text_field( mb_substr( $title, 0, 200 ) ),
					'thumbnail' => $thumb ? self::absolute_url( $thumb, $base_url ) : '',
					'duration'  => '',
				);
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
		// A) Collect ALL <video src=""> and <source src=""> candidates.
		$candidates = array();

		// <video src="..."> direct attribute.
		$videos = $xpath->query( '//video[@src]' );
		foreach ( $videos as $v ) {
			$src = $v->getAttribute( 'src' );
			if ( $src && self::is_video_file( $src ) ) {
				$candidates[] = array(
					'url'     => self::absolute_url( $src, $base_url ),
					'quality' => self::detect_quality( $src ),
				);
			}
		}

		// <source src="..."> inside <video>.
		$sources = $xpath->query( '//video/source[@src]' );
		foreach ( $sources as $s ) {
			$src   = $s->getAttribute( 'src' );
			$title = $s->getAttribute( 'title' ); // e.g., "1080p", "720p".
			$type  = $s->getAttribute( 'type' );
			if ( ! $src || ( $type && strpos( $type, 'video' ) === false && $type !== '' ) ) {
				continue;
			}
			if ( self::is_video_file( $src ) ) {
				$q = $title ? self::detect_quality( $title ) : self::detect_quality( $src );
				$candidates[] = array(
					'url'     => self::absolute_url( $src, $base_url ),
					'quality' => $q,
				);
			}
		}

		// Pick highest quality from candidates.
		if ( ! empty( $candidates ) ) {
			usort( $candidates, function ( $a, $b ) {
				return $b['quality'] - $a['quality'];
			} );
			return $candidates[0]['url'];
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
		preg_match_all( '/(?:src|href|data-src|data-video-url|data-video|content)=["\']([^"\']*\.(?:mp4|m3u8|webm)[^\s"\']*)/i', $html, $all_attrs );
		if ( ! empty( $all_attrs[1] ) ) {
			// Score all matches and pick best quality.
			$best     = '';
			$best_q   = -1;
			foreach ( $all_attrs[1] as $match ) {
				$q = self::detect_quality( $match );
				if ( $q > $best_q ) {
					$best_q = $q;
					$best   = $match;
				}
			}
			if ( $best ) {
				return self::absolute_url( html_entity_decode( $best ), $base_url );
			}
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
		// Match .mp4, .m3u8, .webm followed by /, ?, or end of string.
		return (bool) preg_match( '/\.(mp4|m3u8|webm)([\/\?&#]|$)/i', $url );
	}

	/**
	 * Detect video quality score from URL or title string.
	 * Higher score = better quality.
	 */
	private static function detect_quality( $str ) {
		if ( preg_match( '/2160|4k/i', $str ) )  return 2160;
		if ( preg_match( '/1080/i', $str ) )      return 1080;
		if ( preg_match( '/720/i', $str ) )       return 720;
		if ( preg_match( '/480/i', $str ) )       return 480;
		if ( preg_match( '/360/i', $str ) )       return 360;
		if ( preg_match( '/240/i', $str ) )       return 240;
		return 0; // Unknown quality.
	}

	/**
	 * Find thumbnail inside a container element.
	 */
	private static function find_nearby_thumbnail_in( $container, $xpath ) {
		$imgs = $xpath->query( './/img', $container );
		if ( ! $imgs->length ) {
			return '';
		}
		$img = $imgs->item( 0 );
		return $img->getAttribute( 'data-src' )
			?: $img->getAttribute( 'data-jpg' )
			?: $img->getAttribute( 'src' );
	}

	/**
	 * Find duration text inside a container (e.g., "29:15", "9 min", "02:10").
	 */
	private static function find_duration_text( $container ) {
		$text = $container->textContent;
		// Match MM:SS or HH:MM:SS patterns.
		if ( preg_match( '/\b(\d{1,2}:\d{2}(?::\d{2})?)\b/', $text, $m ) ) {
			return $m[1];
		}
		// Match "X min" patterns.
		if ( preg_match( '/\b(\d+)\s*min/i', $text, $m ) ) {
			return $m[1] . ':00';
		}
		// Match "Duration:" label followed by value.
		if ( preg_match( '/duration[:\s]+(\d{1,2}:\d{2}(?::\d{2})?)/i', $text, $m ) ) {
			return $m[1];
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
