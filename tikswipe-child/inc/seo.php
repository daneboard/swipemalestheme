<?php
/**
 * SEO Improvements for TikSwipe Child Theme.
 *
 * Adds meta descriptions, canonical URLs, Open Graph, Twitter Cards,
 * JSON-LD structured data, Video XML Sitemap, and more.
 *
 * @package TikSwipe Child
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Don't run if a dedicated SEO plugin is active.
if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) ) {
	return;
}

/* ==========================================================================
   HELPERS
   ========================================================================== */

/**
 * Get a trimmed plain-text excerpt from post content.
 */
function tikswipe_seo_get_description( $post = null, $words = 30 ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$text = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	$text = str_replace( array( "\r", "\n", "\t" ), ' ', $text );
	$text = preg_replace( '/\s+/', ' ', $text );
	return $text ? wp_trim_words( $text, $words, '' ) : get_the_title( $post );
}

/**
 * Get the primary thumbnail URL for a post.
 */
function tikswipe_seo_get_thumb( $post_id, $size = 'ms-large' ) {
	if ( has_post_thumbnail( $post_id ) ) {
		return get_the_post_thumbnail_url( $post_id, $size );
	}
	$thumb = get_post_meta( $post_id, 'thumb', true );
	return $thumb ?: '';
}

/**
 * XML-safe escaping (fallback for WP < 5.5).
 */
function tikswipe_seo_esc_xml( $string ) {
	if ( function_exists( 'esc_xml' ) ) {
		return esc_xml( $string );
	}
	return htmlspecialchars( $string, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

/* ==========================================================================
   1. META DESCRIPTION
   ========================================================================== */

function tikswipe_seo_meta_description() {
	$desc = '';

	if ( is_single() || is_page() ) {
		$desc = tikswipe_seo_get_description( get_post(), 25 );
	} elseif ( is_category() || is_tag() ) {
		$term = get_queried_object();
		$desc = $term->description ? wp_strip_all_tags( $term->description ) : $term->name . ' - ' . get_bloginfo( 'name' );
	} elseif ( is_home() || is_front_page() ) {
		$desc = get_bloginfo( 'description' );
	} elseif ( is_author() ) {
		$author = get_queried_object();
		$desc   = $author->display_name . ' - ' . get_bloginfo( 'name' );
	} elseif ( is_search() ) {
		$desc = sprintf( 'Search results for "%s" - %s', get_search_query(), get_bloginfo( 'name' ) );
	}

	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'tikswipe_seo_meta_description', 1 );

/* ==========================================================================
   2. CANONICAL URLs
   ========================================================================== */

function tikswipe_seo_canonical_init() {
	remove_action( 'wp_head', 'rel_canonical' );
}
add_action( 'init', 'tikswipe_seo_canonical_init' );

function tikswipe_seo_canonical() {
	// Don't output canonical on noindex pages.
	if ( is_search() || is_page_template( 'template-favorites.php' ) || is_page_template( 'template-search.php' ) ) {
		return;
	}
	if ( isset( $_GET['view'] ) ) {
		return;
	}

	$url = '';
	if ( is_front_page() || is_home() ) {
		$url = home_url( '/' );
	} elseif ( is_single() || is_page() ) {
		$url = get_permalink();
	} elseif ( is_category() || is_tag() ) {
		$url = get_term_link( get_queried_object() );
	} elseif ( is_author() ) {
		$url = get_author_posts_url( get_queried_object_id() );
	}

	if ( $url && ! is_wp_error( $url ) ) {
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'tikswipe_seo_canonical', 1 );

/* ==========================================================================
   3. ROBOTS META (noindex for non-content pages)
   ========================================================================== */

function tikswipe_seo_robots_meta() {
	$noindex = false;

	if ( is_search() ) {
		$noindex = true;
	} elseif ( is_page_template( 'template-favorites.php' ) ) {
		$noindex = true;
	} elseif ( is_page_template( 'template-search.php' ) ) {
		$noindex = true;
	} elseif ( is_page_template( 'template-edit-profile.php' ) ) {
		$noindex = true;
	} elseif ( is_page_template( 'template-add-content.php' ) ) {
		$noindex = true;
	} elseif ( isset( $_GET['view'] ) ) {
		$noindex = true;
	}

	if ( $noindex ) {
		echo '<meta name="robots" content="noindex, follow">' . "\n";
	}
}
add_action( 'wp_head', 'tikswipe_seo_robots_meta', 1 );

/* ==========================================================================
   4. OPEN GRAPH
   ========================================================================== */

function tikswipe_seo_open_graph() {
	$og        = array();
	$site_name = get_bloginfo( 'name' );

	if ( is_single() ) {
		$post = get_post();
		$og['og:type']        = has_post_format( 'video' ) ? 'video.other' : 'article';
		$og['og:title']       = get_the_title();
		$og['og:url']         = get_permalink();
		$og['og:description'] = tikswipe_seo_get_description( $post, 25 );

		$thumb = tikswipe_seo_get_thumb( $post->ID );
		if ( $thumb ) {
			$og['og:image'] = $thumb;
		}

		if ( has_post_format( 'video' ) ) {
			$video_url = get_post_meta( $post->ID, 'video_url', true );
			$embed     = get_post_meta( $post->ID, 'embed', true );
			if ( $video_url ) {
				$og['og:video']      = $video_url;
				$og['og:video:type'] = 'video/mp4';
			} elseif ( $embed ) {
				preg_match( '/src=["\']([^"\']+)["\']/', $embed, $match );
				if ( ! empty( $match[1] ) ) {
					$og['og:video'] = $match[1];
				}
			}
			$width  = get_post_meta( $post->ID, '_video_width', true );
			$height = get_post_meta( $post->ID, '_video_height', true );
			if ( $width && $height ) {
				$og['og:video:width']  = $width;
				$og['og:video:height'] = $height;
			}
		}
	} elseif ( is_category() || is_tag() ) {
		$term                 = get_queried_object();
		$og['og:type']        = 'website';
		$og['og:title']       = $term->name . ' - ' . $site_name;
		$og['og:url']         = get_term_link( $term );
		$og['og:description'] = $term->description ?: $term->name . ' - ' . $site_name;
	} elseif ( is_home() || is_front_page() ) {
		$og['og:type']        = 'website';
		$og['og:title']       = $site_name;
		$og['og:url']         = home_url( '/' );
		$og['og:description'] = get_bloginfo( 'description' );
	} elseif ( is_author() ) {
		$author               = get_queried_object();
		$og['og:type']        = 'profile';
		$og['og:title']       = $author->display_name . ' - ' . $site_name;
		$og['og:url']         = get_author_posts_url( $author->ID );
		$og['og:description'] = $author->display_name . ' - ' . $site_name;
	} else {
		return;
	}

	$og['og:site_name'] = $site_name;
	$og['og:locale']    = get_locale();

	foreach ( $og as $prop => $val ) {
		if ( $val ) {
			echo '<meta property="' . esc_attr( $prop ) . '" content="' . esc_attr( $val ) . '">' . "\n";
		}
	}
}
add_action( 'wp_head', 'tikswipe_seo_open_graph', 2 );

/* ==========================================================================
   5. TWITTER CARDS
   ========================================================================== */

function tikswipe_seo_twitter_cards() {
	if ( ! is_single() ) {
		return;
	}

	$post = get_post();

	if ( has_post_format( 'video' ) ) {
		echo '<meta name="twitter:card" content="player">' . "\n";
		$video_url = get_post_meta( $post->ID, 'video_url', true );
		if ( $video_url ) {
			echo '<meta name="twitter:player" content="' . esc_attr( get_permalink() ) . '">' . "\n";
			$w = get_post_meta( $post->ID, '_video_width', true ) ?: '720';
			$h = get_post_meta( $post->ID, '_video_height', true ) ?: '1280';
			echo '<meta name="twitter:player:width" content="' . esc_attr( $w ) . '">' . "\n";
			echo '<meta name="twitter:player:height" content="' . esc_attr( $h ) . '">' . "\n";
		}
	} else {
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	}

	echo '<meta name="twitter:title" content="' . esc_attr( get_the_title() ) . '">' . "\n";
	echo '<meta name="twitter:description" content="' . esc_attr( tikswipe_seo_get_description( $post, 25 ) ) . '">' . "\n";

	$thumb = tikswipe_seo_get_thumb( $post->ID );
	if ( $thumb ) {
		echo '<meta name="twitter:image" content="' . esc_attr( $thumb ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'tikswipe_seo_twitter_cards', 2 );

/* ==========================================================================
   6. JSON-LD STRUCTURED DATA
   ========================================================================== */

function tikswipe_seo_jsonld() {
	$schemas = array();

	// --- WebSite + SearchAction (all pages) ---
	$schemas[] = array(
		'@type'           => 'WebSite',
		'name'            => get_bloginfo( 'name' ),
		'url'             => home_url( '/' ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => home_url( '/?s={search_term_string}' ),
			'query-input' => 'required name=search_term_string',
		),
	);

	// --- Organization (home only) ---
	if ( is_home() || is_front_page() ) {
		$org = array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);
		$logo_image = get_theme_mod( 'wpst_logo_image', '' );
		if ( $logo_image ) {
			$org['logo'] = esc_url( $logo_image );
		}
		$schemas[] = $org;
	}

	// --- BreadcrumbList (single, category, tag) ---
	if ( is_single() || is_category() || is_tag() ) {
		$crumbs = array();
		$pos    = 1;

		$crumbs[] = array(
			'@type'    => 'ListItem',
			'position' => $pos++,
			'name'     => get_bloginfo( 'name' ),
			'item'     => home_url( '/' ),
		);

		if ( is_single() ) {
			$cats = get_the_category();
			if ( $cats ) {
				$cat       = $cats[0];
				$crumbs[] = array(
					'@type'    => 'ListItem',
					'position' => $pos++,
					'name'     => $cat->name,
					'item'     => get_category_link( $cat->term_id ),
				);
			}
			$crumbs[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => get_the_title(),
			);
		} elseif ( is_category() || is_tag() ) {
			$term     = get_queried_object();
			$crumbs[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => $term->name,
			);
		}

		$schemas[] = array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $crumbs,
		);
	}

	// --- VideoObject (single video posts - enhanced) ---
	if ( is_single() && has_post_format( 'video' ) ) {
		$post      = get_post();
		$video_url = get_post_meta( $post->ID, 'video_url', true );
		$embed     = get_post_meta( $post->ID, 'embed', true );
		$duration  = (int) get_post_meta( $post->ID, 'duration', true );
		$thumb     = tikswipe_seo_get_thumb( $post->ID );
		$desc      = tikswipe_seo_get_description( $post, 50 );
		$views     = (int) get_post_meta( $post->ID, 'post_views_count', true );
		$width     = get_post_meta( $post->ID, '_video_width', true );
		$height    = get_post_meta( $post->ID, '_video_height', true );

		$content_url = '';
		$embed_url   = '';
		if ( $video_url ) {
			$content_url = $video_url;
		} elseif ( $embed ) {
			preg_match( '/src=["\']([^"\']+)["\']/', $embed, $match );
			if ( ! empty( $match[1] ) ) {
				$embed_url = $match[1];
			}
		}

		$video_schema = array(
			'@type'        => 'VideoObject',
			'name'         => get_the_title(),
			'description'  => $desc ?: get_the_title(),
			'thumbnailUrl' => $thumb,
			'uploadDate'   => get_the_date( 'c' ),
			'author'       => array(
				'@type' => 'Person',
				'name'  => get_the_author(),
			),
		);

		if ( $duration > 0 ) {
			$video_schema['duration'] = wpst_iso8601_duration( $duration );
		}
		if ( $content_url ) {
			$video_schema['contentUrl'] = $content_url;
		}
		if ( $embed_url ) {
			$video_schema['embedUrl'] = $embed_url;
		}
		if ( $width && $height ) {
			$video_schema['width']  = (int) $width;
			$video_schema['height'] = (int) $height;
		}
		if ( $views > 0 ) {
			$video_schema['interactionStatistic'] = array(
				'@type'                => 'InteractionCounter',
				'interactionType'      => array( '@type' => 'WatchAction' ),
				'userInteractionCount' => $views,
			);
		}
		$video_schema['potentialAction'] = array(
			'@type'  => 'WatchAction',
			'target' => get_permalink(),
		);

		$schemas[] = $video_schema;
	}

	// --- ImageObject (single image posts) ---
	if ( is_single() && has_post_format( 'image' ) ) {
		$post  = get_post();
		$thumb = tikswipe_seo_get_thumb( $post->ID );
		$desc  = tikswipe_seo_get_description( $post, 50 );

		if ( $thumb ) {
			$image_schema = array(
				'@type'        => 'ImageObject',
				'name'         => get_the_title(),
				'description'  => $desc ?: get_the_title(),
				'contentUrl'   => $thumb,
				'thumbnailUrl' => tikswipe_seo_get_thumb( $post->ID, 'ms-thumb' ),
				'uploadDate'   => get_the_date( 'c' ),
				'author'       => array(
					'@type' => 'Person',
					'name'  => get_the_author(),
				),
			);

			$schemas[] = $image_schema;
		}
	}

	// Output all schemas in a single script tag.
	if ( ! empty( $schemas ) ) {
		$output = array(
			'@context' => 'https://schema.org',
		);

		if ( count( $schemas ) === 1 ) {
			$output = array_merge( $output, $schemas[0] );
		} else {
			$output['@graph'] = $schemas;
		}

		echo '<script type="application/ld+json">' . wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
	}
}
add_action( 'wp_head', 'tikswipe_seo_jsonld', 3 );

/* ==========================================================================
   7. VIDEO XML SITEMAP
   ========================================================================== */

/**
 * Intercept requests for /video-sitemap.xml and output the video sitemap.
 */
function tikswipe_seo_video_sitemap( $do_parse ) {
	$path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
	if ( $path !== 'video-sitemap.xml' ) {
		return $do_parse;
	}

	status_header( 200 );
	header( 'Content-Type: application/xml; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );

	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
	echo '        xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

	$args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 1000,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'tax_query'      => array(
			array(
				'taxonomy' => 'post_format',
				'field'    => 'slug',
				'terms'    => 'post-format-video',
				'operator' => 'IN',
			),
		),
	);

	$query = new WP_Query( $args );

	while ( $query->have_posts() ) {
		$query->the_post();
		$post_id   = get_the_ID();
		$video_url = get_post_meta( $post_id, 'video_url', true );
		$embed     = get_post_meta( $post_id, 'embed', true );
		$duration  = (int) get_post_meta( $post_id, 'duration', true );
		$thumb     = tikswipe_seo_get_thumb( $post_id, 'ms-large' );
		$title     = get_the_title();
		$desc      = tikswipe_seo_get_description( get_post(), 50 );
		if ( ! $desc ) {
			$desc = $title;
		}

		$content_loc = '';
		if ( $video_url ) {
			$content_loc = $video_url;
		} elseif ( $embed ) {
			preg_match( '/src=["\']([^"\']+)["\']/', $embed, $match );
			if ( ! empty( $match[1] ) ) {
				$content_loc = $match[1];
			}
		}

		echo "  <url>\n";
		echo '    <loc>' . esc_url( get_permalink() ) . "</loc>\n";
		echo "    <video:video>\n";
		if ( $thumb ) {
			echo '      <video:thumbnail_loc>' . esc_url( $thumb ) . "</video:thumbnail_loc>\n";
		}
		echo '      <video:title>' . tikswipe_seo_esc_xml( $title ) . "</video:title>\n";
		echo '      <video:description>' . tikswipe_seo_esc_xml( $desc ) . "</video:description>\n";
		if ( $content_loc ) {
			echo '      <video:content_loc>' . esc_url( $content_loc ) . "</video:content_loc>\n";
		}
		if ( $duration > 0 ) {
			echo '      <video:duration>' . $duration . "</video:duration>\n";
		}
		echo '      <video:publication_date>' . get_the_date( 'c' ) . "</video:publication_date>\n";
		echo "    </video:video>\n";
		echo "  </url>\n";
	}

	wp_reset_postdata();

	echo "</urlset>\n";
	exit;
}
add_filter( 'do_parse_request', 'tikswipe_seo_video_sitemap', 1 );

/* ==========================================================================
   8. ROBOTS.TXT CUSTOMIZATION
   ========================================================================== */

function tikswipe_seo_robots_txt( $output, $public ) {
	if ( '1' === (string) $public ) {
		$output .= "\n# Block filtered/duplicate views from crawling\n";
		$output .= "Disallow: /*?view=\n";
		$output .= "Disallow: /*?s=\n";
		$output .= "\n# Sitemaps\n";
		$output .= 'Sitemap: ' . home_url( '/wp-sitemap.xml' ) . "\n";
		$output .= 'Sitemap: ' . home_url( '/video-sitemap.xml' ) . "\n";
	}
	return $output;
}
add_filter( 'robots_txt', 'tikswipe_seo_robots_txt', 10, 2 );

/* ==========================================================================
   9. ALT TEXT FOR IMAGES (dynamic fallback)
   ========================================================================== */

function tikswipe_seo_dynamic_alt( $attr, $attachment, $size ) {
	if ( empty( $attr['alt'] ) ) {
		// Try attachment title first.
		$attr['alt'] = get_the_title( $attachment->ID );
		// Fallback to current post title.
		if ( empty( $attr['alt'] ) ) {
			$attr['alt'] = get_the_title();
		}
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'tikswipe_seo_dynamic_alt', 5, 3 );

/* ==========================================================================
   10. IMAGE DIMENSIONS (prevent CLS)
   ========================================================================== */

function tikswipe_seo_image_dimensions( $attr, $attachment, $size ) {
	// Only add if not already present and we can determine size.
	if ( empty( $attr['width'] ) || empty( $attr['height'] ) ) {
		$img_data = wp_get_attachment_image_src( $attachment->ID, $size );
		if ( $img_data ) {
			$attr['width']  = $img_data[1];
			$attr['height'] = $img_data[2];
		}
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'tikswipe_seo_image_dimensions', 6, 3 );

/* ==========================================================================
   11. RSS FEED THUMBNAILS
   ========================================================================== */

function tikswipe_seo_rss_thumbnail( $content ) {
	global $post;
	if ( ! $post ) {
		return $content;
	}
	if ( has_post_thumbnail( $post->ID ) ) {
		$thumb   = get_the_post_thumbnail_url( $post->ID, 'ms-thumb' );
		$content = '<p><img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( get_the_title() ) . '" /></p>' . $content;
	}
	return $content;
}
add_filter( 'the_excerpt_rss', 'tikswipe_seo_rss_thumbnail' );
add_filter( 'the_content_feed', 'tikswipe_seo_rss_thumbnail' );

/* ==========================================================================
   12. HIDDEN H1 HEADINGS (visually hidden, SEO-only)
   ========================================================================== */

function tikswipe_seo_hidden_headings() {
	$heading = '';

	if ( ( is_home() || is_front_page() ) && ( ! isset( $_GET['view'] ) || $_GET['view'] !== 'profile' ) ) {
		$desc = get_bloginfo( 'description' );
		$heading = get_bloginfo( 'name' ) . ( $desc ? ' - ' . $desc : '' );
	} elseif ( is_category() || is_tag() ) {
		$term    = get_queried_object();
		$heading = $term->name;
	} elseif ( is_search() ) {
		$heading = sprintf( 'Search results for: %s', get_search_query() );
	}

	if ( $heading ) {
		echo '<h1 class="tikswipe-sr-only">' . esc_html( $heading ) . '</h1>' . "\n";
	}
}
add_action( 'wp_body_open', 'tikswipe_seo_hidden_headings', 1 );

/* ==========================================================================
   13. NOSCRIPT PAGINATION FALLBACK
   ========================================================================== */

function tikswipe_seo_noscript_pagination() {
	if ( ! is_home() && ! is_front_page() && ! is_single() && ! is_category() && ! is_tag() ) {
		return;
	}
	$next = get_pagenum_link( max( 1, get_query_var( 'paged', 1 ) ) + 1 );
	echo '<noscript><p style="text-align:center;padding:20px;"><a href="' . esc_url( $next ) . '">' . esc_html__( 'Next Page', 'tikswipe-child' ) . '</a></p></noscript>' . "\n";
}
add_action( 'wp_footer', 'tikswipe_seo_noscript_pagination', 1 );

/* ==========================================================================
   14. PRECONNECT / DNS PREFETCH
   ========================================================================== */

function tikswipe_seo_resource_hints( $urls, $relation_type ) {
	if ( 'dns-prefetch' === $relation_type ) {
		$urls[] = '//cdn.jsdelivr.net';
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'tikswipe_seo_resource_hints', 10, 2 );

/* ==========================================================================
   15. INLINE CSS FOR SR-ONLY CLASS
   ========================================================================== */

function tikswipe_seo_inline_css() {
	echo '<style>.tikswipe-sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}</style>' . "\n";
}
add_action( 'wp_head', 'tikswipe_seo_inline_css', 99 );
