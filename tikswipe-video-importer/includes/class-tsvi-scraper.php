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
		// RedGifs: extract IDs from tiles and build watch URLs.
		if ( self::is_redgifs_url( $url ) ) {
			return self::redgifs_discover( $url, $limit );
		}

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
		// RedGifs: use API to get video data directly.
		if ( self::is_redgifs_url( $url ) ) {
			$result = self::redgifs_extract( $url );
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}
			// Fallback to normal scraping if API fails.
		}

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

	/* ------------------------------------------------------------------
	   RedGifs support — API-based discovery and extraction
	   ------------------------------------------------------------------ */

	private static function is_redgifs_url( $url ) {
		return (bool) preg_match( '/redgifs\.com/i', $url );
	}

	/**
	 * Extract GIF ID from a RedGifs URL.
	 * Handles: /watch/id, /ifr/id, and bare ID in path.
	 */
	private static function redgifs_parse_id( $url ) {
		if ( preg_match( '#redgifs\.com/(?:watch|ifr)/([a-zA-Z]+)#i', $url, $m ) ) {
			return strtolower( $m[1] );
		}
		// Bare path like /gifid
		$path = trim( wp_parse_url( $url, PHP_URL_PATH ), '/' );
		if ( preg_match( '/^[a-zA-Z]{10,}$/', $path ) ) {
			return strtolower( $path );
		}
		return '';
	}

	/**
	 * Get a temporary RedGifs API token (cached for 1 hour).
	 */
	private static function redgifs_get_token() {
		$cached = get_transient( 'tsvi_redgifs_token' );
		if ( $cached ) {
			return $cached;
		}

		$response = wp_remote_get( 'https://api.redgifs.com/v2/auth/temporary', array(
			'timeout'   => 15,
			'sslverify' => false,
			'headers'   => array(
				'Accept'     => 'application/json',
				'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['token'] ) ) {
			return new WP_Error( 'redgifs_auth', 'Failed to get RedGifs API token.' );
		}

		set_transient( 'tsvi_redgifs_token', $body['token'], HOUR_IN_SECONDS );
		return $body['token'];
	}

	/**
	 * Call RedGifs API for a single GIF.
	 *
	 * @return array|WP_Error  Raw API gif object or error.
	 */
	private static function redgifs_api_gif( $gif_id ) {
		$token = self::redgifs_get_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_get( 'https://api.redgifs.com/v2/gifs/' . $gif_id, array(
			'timeout'   => 15,
			'sslverify' => false,
			'headers'   => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
				'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			// Token may have expired — clear cache and retry once.
			delete_transient( 'tsvi_redgifs_token' );
			if ( $code === 401 ) {
				$token = self::redgifs_get_token();
				if ( is_wp_error( $token ) ) {
					return $token;
				}
				$response = wp_remote_get( 'https://api.redgifs.com/v2/gifs/' . $gif_id, array(
					'timeout'   => 15,
					'sslverify' => false,
					'headers'   => array(
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer ' . $token,
						'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
					),
				) );
				if ( is_wp_error( $response ) ) {
					return $response;
				}
			} else {
				return new WP_Error( 'redgifs_api', 'RedGifs API error HTTP ' . $code );
			}
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['gif'] ) ) {
			return new WP_Error( 'redgifs_api', 'Invalid RedGifs API response.' );
		}

		return $body['gif'];
	}

	/**
	 * Discover videos from a RedGifs page.
	 * Tries API-based bulk discovery first (1 call), falls back to HTML + individual API.
	 */
	private static function redgifs_discover( $url, $limit ) {
		// Try API-based bulk discovery (user pages, search pages).
		$api_result = self::redgifs_discover_api( $url, $limit );
		if ( ! is_wp_error( $api_result ) && ! empty( $api_result ) ) {
			return $api_result;
		}

		// Fallback: extract IDs from HTML, then call API for each.
		$html = self::fetch( $url );
		if ( is_wp_error( $html ) ) {
			return $html;
		}

		if ( ! preg_match_all( '/data-feed-item-id=["\']([a-zA-Z]+)["\']/i', $html, $matches ) ) {
			return new WP_Error( 'no_links', 'No RedGifs video tiles found on this page.' );
		}

		$links = array();
		$ids   = array_unique( $matches[1] );

		foreach ( $ids as $gif_id ) {
			if ( count( $links ) >= $limit ) {
				break;
			}

			$gif_id_lower = strtolower( $gif_id );
			$gif = self::redgifs_api_gif( $gif_id_lower );

			if ( ! is_wp_error( $gif ) ) {
				$links[] = self::redgifs_gif_to_item( $gif );
			} else {
				// API failed for this one — add with minimal data.
				$links[] = array(
					'url'       => 'https://www.redgifs.com/watch/' . $gif_id_lower,
					'title'     => $gif_id_lower,
					'thumbnail' => '',
					'duration'  => '',
				);
			}
		}

		return $links;
	}

	/**
	 * Bulk API discovery: fetch all videos in 1 API call for user/search pages.
	 * Returns full video data (including video_url) so JS can skip extraction.
	 */
	private static function redgifs_discover_api( $url, $limit ) {
		$token = self::redgifs_get_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$api_url = '';
		$count   = min( $limit, 80 );

		// User page: /users/username
		if ( preg_match( '#redgifs\.com/users/([a-zA-Z0-9_.-]+)#i', $url, $m ) ) {
			$api_url = 'https://api.redgifs.com/v2/users/' . strtolower( $m[1] ) . '/search?order=new&count=' . $count . '&page=1';
		}
		// Search: ?query=term or /search?query=term
		elseif ( preg_match( '#[?&]query=([^&]+)#i', $url, $m ) ) {
			$api_url = 'https://api.redgifs.com/v2/gifs/search?search_text=' . urlencode( urldecode( $m[1] ) ) . '&order=new&count=' . $count . '&page=1';
		}
		// Tag/category browsing: /gifs/tagname, /gay/tagname, etc.
		elseif ( preg_match( '#redgifs\.com/(?:gifs|gay|straight|bi|explore)/([a-zA-Z0-9_-]+)#i', $url, $m ) ) {
			$api_url = 'https://api.redgifs.com/v2/gifs/search?search_text=' . urlencode( $m[1] ) . '&order=new&count=' . $count . '&page=1';
		}

		if ( ! $api_url ) {
			return new WP_Error( 'no_api', 'Could not determine RedGifs API URL.' );
		}

		$response = wp_remote_get( $api_url, array(
			'timeout'   => 20,
			'sslverify' => false,
			'headers'   => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
				'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 400 ) {
			return new WP_Error( 'redgifs_api', 'RedGifs API HTTP ' . $code );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$gifs = $body['gifs'] ?? array();

		if ( empty( $gifs ) ) {
			return new WP_Error( 'no_results', 'No gifs returned from RedGifs API.' );
		}

		$links = array();
		foreach ( $gifs as $gif ) {
			if ( count( $links ) >= $limit ) {
				break;
			}
			$links[] = self::redgifs_gif_to_item( $gif );
		}

		return $links;
	}

	/**
	 * Convert a RedGifs API gif object to a discover item with full video data.
	 * When video_url is present, JS can skip the extract step entirely.
	 */
	private static function redgifs_gif_to_item( $gif ) {
		$urls      = $gif['urls'] ?? array();
		$video_url = $urls['hd'] ?? $urls['sd'] ?? '';
		$gif_id    = strtolower( $gif['id'] ?? '' );

		$tags = array();
		if ( ! empty( $gif['tags'] ) && is_array( $gif['tags'] ) ) {
			$tags = array_map( 'sanitize_text_field', $gif['tags'] );
		}

		$title = ! empty( $tags ) ? implode( ', ', array_slice( $tags, 0, 5 ) ) : $gif_id;

		$thumb = $urls['poster'] ?? $urls['thumbnail'] ?? '';

		return array(
			'url'         => 'https://www.redgifs.com/watch/' . $gif_id,
			'title'       => sanitize_text_field( mb_substr( $title, 0, 200 ) ),
			'thumbnail'   => $thumb,
			'duration'    => intval( $gif['duration'] ?? 0 ),
			// Full data — JS skips extraction when these are present.
			'video_url'   => $video_url,
			'width'       => intval( $gif['width'] ?? 0 ),
			'height'      => intval( $gif['height'] ?? 0 ),
			'source_tags' => $tags,
			'description' => sanitize_text_field( $gif['description'] ?? '' ),
		);
	}

	/**
	 * Extract video data from a RedGifs page using the API.
	 * Returns direct HD mp4 URL, duration, dimensions, tags.
	 */
	private static function redgifs_extract( $url ) {
		$gif_id = self::redgifs_parse_id( $url );
		if ( ! $gif_id ) {
			return new WP_Error( 'redgifs_id', 'Could not parse RedGifs ID from URL.' );
		}

		$gif = self::redgifs_api_gif( $gif_id );
		if ( is_wp_error( $gif ) ) {
			return $gif;
		}

		$urls = $gif['urls'] ?? array();

		// Prefer HD, fallback to SD.
		$video_url = $urls['hd'] ?? $urls['sd'] ?? '';

		$tags = array();
		if ( ! empty( $gif['tags'] ) && is_array( $gif['tags'] ) ) {
			$tags = array_map( 'sanitize_text_field', $gif['tags'] );
		}

		$title = '';
		if ( ! empty( $tags ) ) {
			$title = implode( ', ', array_slice( $tags, 0, 5 ) );
		}
		if ( ! $title ) {
			$title = $gif_id;
		}

		$thumb = $urls['poster'] ?? $urls['thumbnail'] ?? '';
		if ( ! $thumb ) {
			// Build thumbnail URL from known pattern.
			$camel = ucfirst( $gif_id );
			$thumb = 'https://media.redgifs.com/' . $camel . '-mobile.jpg';
		}

		return array(
			'source_url'  => $url,
			'title'       => sanitize_text_field( mb_substr( $title, 0, 200 ) ),
			'description' => sanitize_text_field( $gif['description'] ?? '' ),
			'video_url'   => $video_url,
			'thumbnail'   => $thumb,
			'duration'    => intval( $gif['duration'] ?? 0 ),
			'width'       => intval( $gif['width'] ?? 0 ),
			'height'      => intval( $gif['height'] ?? 0 ),
			'source_tags' => $tags,
			'embed'       => '',
		);
	}
}
