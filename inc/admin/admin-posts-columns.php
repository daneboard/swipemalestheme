<?php
add_filter(
	'manage_post_posts_columns',
	function ( $columns ) {
		// return array_merge($columns, ['post_format' => __('Format', 'wpst')], ['video_thumb' => __('Thumb', 'wpst')]);
		return array_merge( $columns, array( 'post_format' => __( 'Format', 'wpst' ) ) );
	}
);

add_action(
	'manage_post_posts_custom_column',
	function ( $column_key, $post_id ) {
		if ( $column_key == 'post_format' ) {
			$post_format = get_post_format( $post_id );
			if ( $post_format === 'video' ) {
				echo _e( 'Video', 'wpst' );
			} elseif ( $post_format === 'image' ) {
				echo _e( 'Image', 'wpst' );
			}
		}
		// if( $column_key === 'video_thumb' ){
		// echo '<div style="background-image: url(' . get_the_post_thumbnail_url( get_the_id(), 'ms-thumb' ) . '); background-repeat: no-repeat; background-position: center; background-size: cover; width: 120px; height: 120px; -webkit-border-radius: 3px; -moz-border-radius: 3px; border-radius: 3px;"></div>';
		// }
	},
	10,
	2
);
