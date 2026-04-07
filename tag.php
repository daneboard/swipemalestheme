<?php

	get_header();

?>

<main>
	<!-- Slider main container -->
	<div class="swiper">
		<!-- Additional required wrapper -->
		<div class="swiper-wrapper">
			<?php
				$current_tag              = get_queried_object();
				$current_tag_id           = (int) $current_tag->term_id;
				$paged                    = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
				$ads_displaying_frequency = get_theme_mod( 'wpst_ads_displaying_frequency', 5 );
				$args                     = array(
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'orderby'        => 'ID',
					'order'          => 'DESC',
					'tag__and'       => $current_tag_id,
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
					?>
					<?php
					while ( $wp_query->have_posts() ) :
						$wp_query->the_post();
						?>
						<?php eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'tag_eval_1' ) ); ?>
					<?php endwhile; ?>
					<?php
				endif;
				wp_reset_postdata();
				eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'tag_eval_2' ) );
				?>
		</div>
		<!-- If we need navigation buttons -->
		<div class="swiper-button-prev"></div>
		<div class="swiper-button-next"></div>
	</div>
</main>

<?php
	get_footer();

