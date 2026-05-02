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
			$containers = $xpath->query(
				'//article[contains(@class,"thumb")]' .
				'|//div[contains(@class,"thumb")]' .
				'|//div[contains(@class,"video-item")]' .
				'|//div[contains(@class,"video_block")]' .
				'|//div[contains(@class,"video-card")]' .
				'|//div[contains(@class,"mozaique")]//div[contains(@class,"thumb")]' .  // xvideos
				'|//li[contains(@class,"video")]' .
				'|//li[contains(@class,"pcVideoListItem")]' .                            // pornhub
				'|//div[contains(@class,"phimage")]' .                                   // pornhub
				'|//div[contains(@class,"nf-videos")]//div[contains(@class,"content")]' . // xnxx
				'|//div[contains(@class,"gallery")]//div[contains(@class,"item")]'        // generic gallery
			);
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
				// Must match video URL pattern.
				if ( ! preg_match( '#/(video|watch|view|play|clip|embed|scene)s?[/_-](\d+|[a-z0-9-]{8,})#i', $abs ) ) {
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

		// GFF Shorts / Feaner: derive direct CDN URL from thumbnail to avoid
		// the page's signed /api/stream URLs which expire.
		if ( self::is_gffshorts_url( $url ) ) {
			$result = self::gffshorts_extract( $url );
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}
			// Fall through to normal scraping on failure.
		}

		// Known hoster (doodstream, streamtape, mixdrop, etc): try native extractor then yt-dlp.
		if ( self::is_hoster_url( $url ) ) {
			$result = self::resolve_hoster_url( $url );
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}
			// Fall through to normal scraping on failure.
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

		// 4. Video URL — check for embedded hoster iframe first (Doodstream, Streamtape, etc).
		// Many aggregator sites embed players from hosters that yt-dlp can resolve.
		$hoster_embed = self::find_hoster_embed_url( $html );
		if ( $hoster_embed ) {
			TSVI_Log::write( 'scrape', 'Found hoster embed: ' . mb_substr( $hoster_embed, 0, 120 ) );
			$resolved = self::resolve_hoster_url( $hoster_embed );
			if ( is_wp_error( $resolved ) ) {
				TSVI_Log::write( 'scrape', 'Hoster resolve failed: ' . $resolved->get_error_message() );
			} elseif ( ! empty( $resolved['video_url'] ) ) {
				TSVI_Log::write( 'scrape', 'Hoster resolved: ' . mb_substr( $resolved['video_url'], 0, 120 ) );
				$video['video_url'] = $resolved['video_url'];
				if ( ! empty( $resolved['duration'] ) ) {
					$video['duration'] = $resolved['duration'];
				}
				if ( ! empty( $resolved['width'] ) ) {
					$video['width'] = $resolved['width'];
				}
				if ( ! empty( $resolved['height'] ) ) {
					$video['height'] = $resolved['height'];
				}
				if ( empty( $video['thumbnail'] ) && ! empty( $resolved['thumbnail'] ) ) {
					$video['thumbnail'] = $resolved['thumbnail'];
				}
			}
		} else {
			TSVI_Log::write( 'scrape', 'No hoster embed found in HTML for: ' . mb_substr( $url, 0, 120 ) . ' (HTML size: ' . strlen( $html ) . ')' );
		}

		// 4a. Fallback to normal video URL extraction strategies.
		if ( empty( $video['video_url'] ) ) {
			$video['video_url'] = self::extract_video_url( $doc, $xpath, $html, $base_url );
		}

		// 4b. Pre-resolve redirects so the stored URL is the final direct one.
		// This avoids re-scraping later when the original redirect link expires.
		if ( ! empty( $video['video_url'] ) && preg_match( '/\.(mp4|m3u8|webm)([\/\?&#]|$)/i', $video['video_url'] ) ) {
			$resolved = TSVI_Bunny::resolve_redirect( $video['video_url'] );
			if ( $resolved && $resolved !== $video['video_url'] ) {
				$video['video_url_original'] = $video['video_url'];
				$video['video_url']          = $resolved;
			}
		}

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

		// 9. yt-dlp fallback — if no direct URL was found, try yt-dlp.
		// Covers any site yt-dlp supports (1000+ sites) without us having to
		// maintain per-site scraping logic.
		if ( empty( $video['video_url'] ) ) {
			$ytdlp = self::ytdlp_extract( $url );
			if ( ! is_wp_error( $ytdlp ) && ! empty( $ytdlp['video_url'] ) ) {
				// Merge: prefer yt-dlp's video_url, dimensions, duration.
				// Keep title/description/thumb from scraping if yt-dlp didn't provide them.
				foreach ( array( 'video_url', 'duration', 'width', 'height' ) as $k ) {
					if ( ! empty( $ytdlp[ $k ] ) ) {
						$video[ $k ] = $ytdlp[ $k ];
					}
				}
				if ( empty( $video['title'] ) && ! empty( $ytdlp['title'] ) ) {
					$video['title'] = $ytdlp['title'];
				}
				if ( empty( $video['thumbnail'] ) && ! empty( $ytdlp['thumbnail'] ) ) {
					$video['thumbnail'] = $ytdlp['thumbnail'];
				}
				if ( empty( $video['source_tags'] ) && ! empty( $ytdlp['source_tags'] ) ) {
					$video['source_tags'] = $ytdlp['source_tags'];
				}
			}
		}

		// 10. Embed fallback — og:video:url or og:video.
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

	/* ------------------------------------------------------------------
	   GFF Shorts / Feaner support
	   The page renders /api/stream/<id>?token=<expiring> URLs that stop
	   working once the token expires. The CDN exposes a stable direct
	   path next to the thumbnail, so we derive that instead.
	   ------------------------------------------------------------------ */

	private static function is_gffshorts_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}
		return (bool) preg_match( '#(?:^|\.)(gffshorts\.com|feaner\.com|gff\.network)$#i', $host );
	}

	/**
	 * Extract video data from a GFF Shorts / Feaner page.
	 * Builds the direct CDN URL (e.g. .../720p.mp4) from the thumbnail
	 * path so the stored URL doesn't depend on a signed token.
	 */
	private static function gffshorts_extract( $url ) {
		TSVI_Log::write( 'scrape', 'gffshorts_extract: start ' . mb_substr( $url, 0, 120 ) );
		$html = self::fetch( $url );
		if ( is_wp_error( $html ) ) {
			TSVI_Log::write( 'scrape', 'gffshorts_extract: fetch failed ' . $html->get_error_message() );
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

		$video['title']       = self::extract_meta( $xpath, 'og:title' )
			?: self::extract_tag_content( $doc, 'title' );
		$video['description'] = self::extract_meta( $xpath, 'og:description' );

		$thumb = self::extract_meta( $xpath, 'og:image' );
		if ( $thumb ) {
			$video['thumbnail'] = self::absolute_url( $thumb, $base_url );
		}

		// JSON-LD VideoObject — most reliable source on this site for
		// title/description/duration/dims/thumb. Discard whatever it says
		// about contentUrl since that points to an expiring /api/stream URL.
		self::parse_json_ld( $html, $video, $base_url );
		$video['video_url'] = '';

		// Derive direct MP4 URL from the thumbnail path:
		//   .../videos/<YYYY>/W<nn>/<UUID>/thumbnail.jpg -> .../<UUID>/720p.mp4
		if ( ! empty( $video['thumbnail'] )
			&& preg_match( '#^(https?://[^/]+/videos/\d{4}/W\d{1,2}/[a-f0-9-]{36})/#i', $video['thumbnail'], $m )
		) {
			$video['video_url'] = $m[1] . '/720p.mp4';
		}

		// Fallback: pull the UUID from the JSON-LD/og:video stream URL and pair
		// with thumbnail host. The thumbnail path includes year/week, but if it
		// is missing for any reason, leave video_url empty so the generic
		// scraper can take over.
		if ( empty( $video['video_url'] ) ) {
			$og_video = self::extract_meta( $xpath, 'og:video' )
				?: self::extract_meta( $xpath, 'og:video:secure_url' );
			if ( $og_video
				&& preg_match( '#/api/stream/([a-f0-9-]{36})#i', $og_video, $idm )
				&& ! empty( $video['thumbnail'] )
				&& preg_match( '#^(https?://[^/]+/videos/\d{4}/W\d{1,2})/#i', $video['thumbnail'], $hm )
			) {
				$video['video_url'] = $hm[1] . '/' . $idm[1] . '/720p.mp4';
			}
		}

		if ( empty( $video['video_url'] ) ) {
			TSVI_Log::write( 'scrape', 'gffshorts_extract: no video_url derived. thumb=' . mb_substr( $video['thumbnail'], 0, 160 ) );
			return new WP_Error( 'gffshorts_extract', 'Could not derive direct CDN URL.' );
		}

		TSVI_Log::write( 'scrape', 'gffshorts_extract: ok ' . mb_substr( $video['video_url'], 0, 160 ) );

		$video['title'] = sanitize_text_field( $video['title'] );

		return $video;
	}

	/* ------------------------------------------------------------------
	   yt-dlp support — resolves direct URLs for 1000+ hosters
	   (Doodstream, Streamtape, Mixdrop, Fembed, Upstream, etc)
	   ------------------------------------------------------------------ */

	/**
	 * Combined hoster regex (used by both is_hoster_url and find_hoster_embed_url).
	 */
	private static function hoster_regex() {
		return '(?:'
			// Doodstream
			. 'doodstream\.com|d000d\.com|dood\.(?:ws|so|to|re|watch|com|la|pm|sh|wf|email|video|one|stream|cx|li|yt)|doods\.pro|ds2play\.com|d0o0d\.com|do0od\.com'
			// Streamtape
			. '|streamtape\.(?:com|net|site|xyz|to)|streamta\.pe|strtape\.(?:cloud|tech)|tapewithadblock\.org'
			// Mixdrop
			. '|mixdrop\.(?:co|to|sx|club|ag|bz|ch|is|ps|gl|nu)'
			// StreamSB
			. '|streamsb\.net|sbfast\.com|sbrapid\.com|sblona\.com|sbflix\.xyz|sbanh\.com|sblanh\.com|sbchill\.com|vidcloud\.co'
			// Upstream
			. '|upstream\.to'
			// MP4Upload
			. '|mp4upload\.com'
			// Fembed
			. '|fembed\.com|feurl\.com|anime789\.com|fembad\.org|femoload\.xyz|diasfem\.com|sharinglink\.club'
			// Other common
			. '|ok\.ru|odnoklassniki\.ru'
			. '|filemoon\.(?:sx|to|in|nl|la|link|wf|pro|art)'
			. '|vidoza\.(?:net|org|co)'
			. '|voe\.sx|voe-network\.net|voe-un\.blocked\.page'
			. ')';
	}

	/**
	 * Detect known embed hosters that need yt-dlp to resolve the direct URL.
	 */
	private static function is_hoster_url( $url ) {
		return (bool) preg_match( '#' . self::hoster_regex() . '#i', $url );
	}

	/**
	 * Scan HTML for an embedded hoster URL (iframe src, JS variables, data attrs).
	 * Returns the first match or empty string.
	 */
	private static function find_hoster_embed_url( $html ) {
		// Match any URL in the HTML pointing to a known hoster.
		$pattern = '#https?://(?:[a-z0-9-]+\.)*' . self::hoster_regex() . '/[^\s"\'<>\\\\]+#i';
		if ( preg_match( $pattern, $html, $m ) ) {
			return html_entity_decode( $m[0] );
		}
		return '';
	}

	/**
	 * Get the yt-dlp binary path (cached per request).
	 * Returns empty string if yt-dlp is not installed.
	 */
	private static function ytdlp_binary() {
		static $cached = null;
		if ( $cached !== null ) {
			return $cached;
		}

		if ( ! function_exists( 'shell_exec' ) ) {
			$cached = '';
			return $cached;
		}

		// 1. Check admin-configured custom path first.
		$custom = trim( get_option( 'tsvi_ytdlp_path', '' ) );
		if ( $custom && is_executable( $custom ) ) {
			$cached = $custom;
			return $cached;
		}

		// 2. Check common install paths (including snap, pip, manual).
		$paths = array(
			'/snap/bin/yt-dlp',                  // snap install
			'/usr/local/bin/yt-dlp',             // manual install
			'/usr/bin/yt-dlp',                   // apt install
			'/opt/yt-dlp/yt-dlp',                // /opt
			'/root/.local/bin/yt-dlp',           // pip --user (root)
			'/home/ubuntu/.local/bin/yt-dlp',    // pip --user (ubuntu)
			'/var/www/.local/bin/yt-dlp',        // pip --user (www-data)
			'/usr/local/sbin/yt-dlp',
		);

		foreach ( $paths as $p ) {
			if ( is_executable( $p ) ) {
				$cached = $p;
				return $cached;
			}
		}

		// 3. Try resolving via which/command (PHP's PATH is often limited).
		$result = @shell_exec( 'PATH=/usr/local/bin:/usr/bin:/snap/bin:/usr/local/sbin:/usr/sbin:/sbin:/bin command -v yt-dlp 2>/dev/null' );
		if ( ! empty( $result ) ) {
			$cached = trim( $result );
			return $cached;
		}

		$cached = '';
		return $cached;
	}

	/**
	 * Check if a URL is a Doodstream/playmogo variant.
	 */
	private static function is_doodstream_url( $url ) {
		return (bool) preg_match( '/doodstream\.com|playmogo\.com|d000d\.com|dood\.(?:ws|so|to|re|watch|com|la|pm|sh|wf|email|video|one|stream|cx|li|yt)|doods\.pro|ds2play\.com|d0o0d\.com|do0od\.com/i', $url );
	}

	/**
	 * Try to resolve a hoster URL to a direct video URL.
	 * Tries native Doodstream extractor first (handles Cloudflare via curl_cffi),
	 * then falls back to yt-dlp.
	 *
	 * @return array|WP_Error Standard video data array or error.
	 */
	public static function resolve_hoster_url( $url ) {
		// Doodstream / playmogo: use native Python extractor (yt-dlp removed support).
		if ( self::is_doodstream_url( $url ) ) {
			$native = self::doodstream_native_extract( $url );
			if ( ! is_wp_error( $native ) ) {
				return $native;
			}
			TSVI_Log::write( 'scrape', 'Doodstream native failed: ' . $native->get_error_message() );
			// Fall through to yt-dlp.
		}

		// All other hosters: use yt-dlp.
		return self::ytdlp_extract( $url );
	}

	/**
	 * Native Doodstream/playmogo extractor via Python script (uses curl_cffi).
	 * yt-dlp removed the Doodstream extractor, so we ship our own.
	 *
	 * @return array|WP_Error Standard video data array or error.
	 */
	public static function doodstream_native_extract( $url ) {
		if ( ! function_exists( 'shell_exec' ) ) {
			return new WP_Error( 'no_shell', 'shell_exec is disabled.' );
		}

		$script = TSVI_PATH . 'bin/doodstream.py';
		if ( ! file_exists( $script ) ) {
			return new WP_Error( 'no_script', 'Doodstream helper script not found at ' . $script );
		}

		$python = self::python_binary();
		if ( ! $python ) {
			return new WP_Error( 'no_python', 'python3 not available on server.' );
		}

		$cmd = escapeshellcmd( $python ) . ' ' . escapeshellarg( $script ) . ' ' . escapeshellarg( $url ) . ' 2>&1';
		$output = @shell_exec( $cmd );

		if ( empty( $output ) ) {
			return new WP_Error( 'empty', 'Doodstream script returned no output.' );
		}

		// The script prints JSON on success, or an ERROR: line on failure.
		$output = trim( $output );

		// Find JSON object in output (skip any warnings).
		$json_start = strpos( $output, '{' );
		if ( $json_start === false ) {
			return new WP_Error( 'no_json', 'Doodstream script: ' . mb_substr( $output, 0, 300 ) );
		}

		$json = substr( $output, $json_start );
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) || empty( $data['video_url'] ) ) {
			return new WP_Error( 'parse', 'Doodstream script invalid JSON: ' . mb_substr( $output, 0, 300 ) );
		}

		return array(
			'source_url'  => $url,
			'title'       => sanitize_text_field( $data['title'] ?? '' ),
			'description' => '',
			'video_url'   => esc_url_raw( $data['video_url'] ),
			'thumbnail'   => esc_url_raw( $data['thumbnail'] ?? '' ),
			'duration'    => intval( $data['duration'] ?? 0 ),
			'width'       => 0,
			'height'      => 0,
			'source_tags' => array(),
			'embed'       => '',
		);
	}

	/**
	 * Find python3 binary (needed for Doodstream native extractor).
	 */
	private static function python_binary() {
		static $cached = null;
		if ( $cached !== null ) {
			return $cached;
		}

		$paths = array(
			'/usr/bin/python3',
			'/usr/local/bin/python3',
			'/usr/bin/python',
		);
		foreach ( $paths as $p ) {
			if ( is_executable( $p ) ) {
				$cached = $p;
				return $cached;
			}
		}

		$cached = '';
		return $cached;
	}

	/**
	 * Check if the native Doodstream extractor is available.
	 */
	public static function doodstream_native_available() {
		return self::python_binary() && file_exists( TSVI_PATH . 'bin/doodstream.py' );
	}

	/**
	 * Check if yt-dlp is available (cached).
	 */
	public static function ytdlp_available() {
		return ! empty( self::ytdlp_binary() );
	}

	/**
	 * Public accessor for the detected yt-dlp binary path.
	 */
	public static function ytdlp_binary_path() {
		return self::ytdlp_binary();
	}

	/**
	 * Get yt-dlp version for admin display.
	 */
	public static function ytdlp_version() {
		$binary = self::ytdlp_binary();
		if ( ! $binary ) {
			return '';
		}
		$out = @shell_exec( escapeshellcmd( $binary ) . ' --version 2>/dev/null' );
		return trim( $out ?? '' );
	}

	/**
	 * Extract video data using yt-dlp.
	 * Runs `yt-dlp --dump-json` and parses the metadata.
	 *
	 * @return array|WP_Error Standard video data array or error.
	 */
	public static function ytdlp_extract( $url ) {
		$binary = self::ytdlp_binary();
		if ( ! $binary ) {
			return new WP_Error( 'ytdlp_missing', 'yt-dlp is not installed on the server.' );
		}

		// Build command: prefer MP4, no playlist, suppress warnings, JSON output.
		// Try with --impersonate chrome to bypass Cloudflare anti-bot (Doodstream etc).
		// Falls back to a plain call if impersonation isn't available.
		$base_args = ' --dump-json --no-warnings --no-playlist --no-check-certificate'
			. ' --format "best[ext=mp4]/best[protocol^=http]/best"'
			. ' --socket-timeout 30';

		// First attempt: with chrome impersonation (needs curl-cffi).
		$cmd_impersonate = escapeshellcmd( $binary )
			. $base_args
			. ' --impersonate chrome'
			. ' ' . escapeshellarg( $url )
			. ' 2>&1';

		$output = @shell_exec( $cmd_impersonate );

		// If impersonate failed (curl-cffi not installed), retry without it.
		if ( empty( $output ) || stripos( $output, 'impersonate' ) !== false && stripos( $output, 'not' ) !== false ) {
			$cmd_plain = escapeshellcmd( $binary )
				. $base_args
				. ' ' . escapeshellarg( $url )
				. ' 2>&1';
			$output = @shell_exec( $cmd_plain );
		}

		if ( empty( $output ) ) {
			return new WP_Error( 'ytdlp_empty', 'yt-dlp returned no output for ' . mb_substr( $url, 0, 80 ) );
		}

		// Find the JSON line (yt-dlp may print warnings before it).
		$lines     = explode( "\n", trim( $output ) );
		$json_line = '';
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( strlen( $line ) > 10 && $line[0] === '{' ) {
				$json_line = $line;
				break;
			}
		}

		if ( empty( $json_line ) ) {
			return new WP_Error( 'ytdlp_failed', 'yt-dlp failed: ' . mb_substr( $output, 0, 300 ) );
		}

		$data = json_decode( $json_line, true );
		if ( ! is_array( $data ) || empty( $data['url'] ) ) {
			return new WP_Error( 'ytdlp_parse', 'yt-dlp returned invalid JSON.' );
		}

		// Build tags list from yt-dlp data.
		$tags = array();
		if ( ! empty( $data['tags'] ) && is_array( $data['tags'] ) ) {
			$tags = array_map( 'sanitize_text_field', $data['tags'] );
		} elseif ( ! empty( $data['categories'] ) && is_array( $data['categories'] ) ) {
			$tags = array_map( 'sanitize_text_field', $data['categories'] );
		}

		// Pick thumbnail — prefer explicit field, fallback to first of thumbnails array.
		$thumbnail = $data['thumbnail'] ?? '';
		if ( empty( $thumbnail ) && ! empty( $data['thumbnails'] ) && is_array( $data['thumbnails'] ) ) {
			$last      = end( $data['thumbnails'] );
			$thumbnail = $last['url'] ?? '';
		}

		return array(
			'source_url'  => $url,
			'title'       => sanitize_text_field( $data['title'] ?? '' ),
			'description' => sanitize_text_field( mb_substr( $data['description'] ?? '', 0, 500 ) ),
			'video_url'   => esc_url_raw( $data['url'] ),
			'thumbnail'   => esc_url_raw( $thumbnail ),
			'duration'    => intval( $data['duration'] ?? 0 ),
			'width'       => intval( $data['width'] ?? 0 ),
			'height'      => intval( $data['height'] ?? 0 ),
			'source_tags' => $tags,
			'embed'       => '',
		);
	}
}
