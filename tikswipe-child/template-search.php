<?php
/*
 * Template Name: Search
 */
defined( 'ABSPATH' ) || exit;
get_header();
$paged             = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
$vids_args         = array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'orderby'        => 'ID',
	'order'          => 'DESC',
	'posts_per_page' => 12,
	'tax_query'      => array(
		array(
			'taxonomy' => 'post_format',
			'field'    => 'slug',
			'terms'    => array( 'post-format-video', 'post-format-image' ),
			'operator' => 'IN',
		),
	),
	'paged'          => $paged,
);
$vids_search_query = new WP_Query( $vids_args );
$vids_count        = $vids_search_query->found_posts;
set_query_var( 'vids_count', $vids_count );
?>
<main>
	<div class="search-header">
		<?php get_search_form(); ?>
	</div>

	<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'template_search_eval_1' ) ); ?>

	<div class="content-wrapper">
		<div id="tab-vids" class="tab-content active">
			<?php if ( $vids_search_query->have_posts() ) : ?>
				<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'template_search_eval_2' ) ); ?>
			<?php elseif ( ! $vids_search_query->have_posts() && ! empty( $search_query ) ) : ?>
				No results found.
			<?php else : ?>
				<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'template_search_eval_3' ) ); ?>
			<?php endif; ?>
		</div>
	</div>
</main>

<?php
get_footer();
