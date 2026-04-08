<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

get_header();
?>

<main>
	<div class="swiper">
		<div class="swiper-wrapper">
			<?php
				$current_cat              = get_queried_object();
				$current_cat_id           = (int) $current_cat->term_id;
				$paged                    = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
				$ads_displaying_frequency = get_theme_mod( 'wpst_ads_displaying_frequency', 5 );
				$args                     = array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'orderby'        => 'RAND(' . get_random_seed() . ')',
					'order'          => 'DESC',
					'category__and'  => $current_cat_id,
					'posts_per_page' => $ads_displaying_frequency,
					'tax_query'      => array(
						array(
							'taxonomy' => 'post_format',
							'field'    => 'slug',
							'terms'    => array(
								'post-format-image',
								'post-format-video',
							),
							'operator' => 'IN',
						),
					),
					'paged'          => $paged,
				);
				$wp_query                 = new WP_Query( $args );

				if ( $wp_query->have_posts() ) :
					while ( $wp_query->have_posts() ) :
						$wp_query->the_post();
						eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'category_eval_1' ) );
					endwhile;
				endif;
				wp_reset_postdata();
				eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'category_eval_2' ) );
				get_template_part( 'templates/slide', 'happy' );
				?>
		</div>
		<div class="swiper-button-prev"></div>
		<div class="swiper-button-next"></div>
	</div>
</main>

<?php
	get_footer();
