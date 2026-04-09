<?php
/**
 * Grok (xAI) API integration for auto-generating titles, descriptions, tags.
 */

defined( 'ABSPATH' ) || exit;

class TSVI_AI {

	/**
	 * Call Grok API to enrich video metadata.
	 *
	 * @param array $video {title, source_url, source_tags, thumbnail}
	 * @return array|WP_Error {title, description, tags[], category}
	 */
	public static function enrich( $video ) {
		$api_key = get_option( 'tsvi_grok_api_key', '' );
		$model   = get_option( 'tsvi_grok_model', 'grok-3-mini-fast' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', 'Grok API key not configured.' );
		}

		$categories = self::get_category_names();

		$prompt = sprintf(
			"You are a video metadata assistant for a short-form video website.\n\n" .
			"Given this scraped video info:\n" .
			"- Original title: %s\n" .
			"- Source tags: %s\n" .
			"- Source URL: %s\n\n" .
			"Available categories on the site: %s\n\n" .
			"RULES:\n" .
			"- title: Clean, natural, NO site names, NO URLs, NO hyphens as separators. Max 80 chars.\n" .
			"- description: Short, max 160 chars.\n" .
			"- tags: ONLY 3 tags that are NOT in the categories list above. Do NOT repeat category names as tags.\n" .
			"- category: Pick the BEST matching category from the list above.\n\n" .
			"Return ONLY valid JSON (no markdown, no explanation):\n" .
			"{\n" .
			"  \"title\": \"clean title here\",\n" .
			"  \"description\": \"short description\",\n" .
			"  \"tags\": [\"tag1\", \"tag2\", \"tag3\"],\n" .
			"  \"category\": \"best matching category\"\n" .
			"}",
			$video['title'] ?? '',
			implode( ', ', (array) ( $video['source_tags'] ?? array() ) ),
			$video['source_url'] ?? '',
			implode( ', ', $categories )
		);

		$response = wp_remote_post(
			'https://api.x.ai/v1/chat/completions',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $model,
						'messages'    => array(
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
						'temperature' => 0.3,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code !== 200 ) {
			return new WP_Error( 'api_error', 'Grok API returned HTTP ' . $code . ': ' . $body );
		}

		$data = json_decode( $body, true );

		if ( empty( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'empty_response', 'Grok returned empty response.' );
		}

		$content = $data['choices'][0]['message']['content'];
		// Strip possible markdown code fences.
		$content = preg_replace( '/^```(?:json)?\s*/m', '', $content );
		$content = preg_replace( '/\s*```$/m', '', $content );

		$parsed = json_decode( trim( $content ), true );

		if ( ! $parsed || ! isset( $parsed['title'] ) ) {
			return new WP_Error( 'parse_error', 'Could not parse Grok response: ' . $content );
		}

		return array(
			'title'       => sanitize_text_field( $parsed['title'] ?? '' ),
			'description' => sanitize_text_field( $parsed['description'] ?? '' ),
			'tags'        => array_map( 'sanitize_text_field', (array) ( $parsed['tags'] ?? array() ) ),
			'category'    => sanitize_text_field( $parsed['category'] ?? '' ),
		);
	}

	/**
	 * Get all existing category names.
	 */
	private static function get_category_names() {
		$cats  = get_categories( array( 'hide_empty' => false ) );
		$names = array();
		foreach ( $cats as $cat ) {
			if ( $cat->slug !== 'uncategorized' ) {
				$names[] = $cat->name;
			}
		}
		return $names;
	}
}
