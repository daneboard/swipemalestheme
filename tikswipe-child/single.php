<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
get_header(); ?>

<?php if ( have_posts() ) : ?>
	<main>
		<div class="swiper">
			<div class="swiper-wrapper">
				<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'single_eval_1' ) ); ?>
				<?php
					$paged                    = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
					$ads_displaying_frequency = get_theme_mod( 'wpst_ads_displaying_frequency', 5 );

					// Get ALL categories from the current post for related videos.
					$post_cats = wp_get_post_categories( $post->ID, array( 'fields' => 'ids' ) );
					$post_tags = wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) );

					$args = array(
						'post_type'      => 'post',
						'post_status'    => 'publish',
						'posts_per_page' => $ads_displaying_frequency,
						'orderby'        => 'RAND(' . get_random_seed() . ')',
						'order'          => 'DESC',
						'post__not_in'   => array( $post->ID ),
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

					// Use category OR tag matching to widen the related pool.
					if ( ! empty( $post_cats ) ) {
						$args['category__in'] = $post_cats;
					}
					if ( ! empty( $post_tags ) ) {
						$args['tag__in'] = $post_tags;
					}

					$wp_query = new WP_Query( $args );
					if ( $wp_query->have_posts() ) :
						while ( $wp_query->have_posts() ) :
							$wp_query->the_post();
							eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'single_eval_1' ) );
						endwhile;
					endif;
					wp_reset_postdata();
					eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'single_eval_2' ) );
				?>
			</div>
			<!-- If we need navigation buttons -->
			<div class="swiper-button-prev"></div>
			<div class="swiper-button-next"></div>
		</div>
	</main>
<?php endif; ?>

<?php
	get_footer();
