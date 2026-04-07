<?php
if ( is_author() ) {
	$author    = get_queried_object();
	$author_id = $author->ID;
} else {
	$author_id = 1;
}
$pics_args     = array(
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
				'post-format-image',
			),
			'operator' => 'IN',
		),
	),
);
$wp_query_pics = new WP_Query( $pics_args );
$pics_count    = $wp_query_pics->found_posts;
set_query_var( 'pics_count', $pics_count );
