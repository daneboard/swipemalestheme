<?php
if ( is_author() ) {
	$author    = get_queried_object();
	$author_id = $author->ID;
} else {
	$author_id = 1;
}
$vids_args     = array(
	'post_type'   => 'post',
	'author'      => $author_id,
	'post_status' => 'publish',
	'orderby'     => 'ID',
	'order'       => 'DESC',
	'tax_query'   => array(
		array(
			'taxonomy' => 'post_format',
			'field'    => 'slug',
			'terms'    => array(
				'post-format-video',
			),
			'operator' => 'IN',
		),
	),
);
$wp_query_vids = new WP_Query( $vids_args );
$vids_count    = $wp_query_vids->found_posts;
set_query_var( 'vids_count', $vids_count );
