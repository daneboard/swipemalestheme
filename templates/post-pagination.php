<?php
/**
 * Pagination Template.
 *
 * To be used inside the WordPress loop.
 * Pagination for Google.
 *
 * @package Aquila
 */

if ( empty( $args_author_vids['total_pages'] ) || empty( $args_author_vids['current_page'] ) ) {
	return null;
}

if ( 1 < $args_author_vids['total_pages'] ) {
	?>
		<div id="post-pagination-author-vids" class="hidden-pagination hidden" data-max-pages="<?php echo esc_attr( $args_author_vids['total_pages'] ); ?>">
		<?php
		echo paginate_links(
			array(
				'base'      => get_pagenum_link( 1 ) . '%_%',
				'format'    => 'page/%#%',
				'current'   => $args_author_vids['current_page'],
				'total'     => $args_author_vids['total_pages'],
				'prev_text' => __( '« Prev', 'aquila' ),
				'next_text' => __( 'Next »', 'aquila' ),
			)
		);
		?>
		</div>
	<?php
}
