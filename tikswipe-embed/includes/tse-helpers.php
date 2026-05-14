<?php
/**
 * Shared helpers used across the plugin.
 *
 * @package TikSwipe_Embed
 */

defined( 'ABSPATH' ) || exit;

/**
 * Format a number with K/M suffixes (1234 -> 1.2K).
 *
 * @param int|string $n Raw count.
 * @return string
 */
function tse_format_count( $n ) {
	if ( ! is_numeric( $n ) ) {
		return $n ? (string) $n : '0';
	}
	$n = (int) $n;
	if ( $n >= 1000000 ) {
		return rtrim( rtrim( number_format( $n / 1000000, 1, '.', '' ), '0' ), '.' ) . 'M';
	}
	if ( $n >= 1000 ) {
		return rtrim( rtrim( number_format( $n / 1000, 1, '.', '' ), '0' ), '.' ) . 'K';
	}
	return (string) $n;
}

/**
 * Convert duration meta to mm:ss string. Accepts seconds (int) or already
 * formatted strings (e.g. "1:23").
 *
 * @param mixed $duration Raw duration meta.
 * @return string
 */
function tse_format_duration( $duration ) {
	if ( empty( $duration ) ) {
		return '';
	}
	if ( is_numeric( $duration ) ) {
		$total   = (int) $duration;
		$minutes = (int) floor( $total / 60 );
		$seconds = $total % 60;
		if ( $total >= 3600 ) {
			$hours   = (int) floor( $total / 3600 );
			$minutes = (int) floor( ( $total % 3600 ) / 60 );
			return sprintf( '%d:%02d:%02d', $hours, $minutes, $seconds );
		}
		return sprintf( '%d:%02d', $minutes, $seconds );
	}
	return (string) $duration;
}

/**
 * Whether a post is eligible for our custom embed (video or image format).
 *
 * @param int|WP_Post $post Post.
 * @return bool
 */
function tse_post_supports_embed( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return false;
	}
	$format = get_post_format( $post );
	return in_array( $format, array( 'video', 'image' ), true );
}

/**
 * Get the canonical /embed/ URL for a post.
 *
 * @param int|WP_Post $post Post.
 * @return string
 */
function tse_get_embed_url( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	return trailingslashit( get_permalink( $post ) ) . 'embed/';
}
