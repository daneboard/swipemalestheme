<?php
$post_thumbnail_url = '';
if ( has_post_thumbnail() ) {
	$post_thumbnail_url = get_the_post_thumbnail_url( get_the_id(), 'ms-thumb' );
} ?>
<li data-id="<?php echo get_the_id(); ?>">
	<div class="media-box media-item
	<?php
	if ( empty( $post_thumbnail_url ) ) :
		?>
		empty-thumb empty-video-thumb<?php endif; ?> relative" data-id="<?php echo get_the_id(); ?>">
		<a href="<?php the_permalink(); ?>">
			<?php echo apply_filters( 'wps_paywall_premium_badge', '', get_the_id() ); ?>
			<?php if ( ! empty( $post_thumbnail_url ) ) : ?>
				<img src="<?php echo esc_url( $post_thumbnail_url ); ?>">
			<?php endif; ?>
			<?php if ( ! empty( wpst_get_video_duration() ) ) : ?>
				<span class="video-duration"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" style="position: relative; top: 1px;"><path fill="#ffffff" d="M3 22v-20l18 10-18 10z"/></svg> <?php echo wpst_get_video_duration(); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( wpst_get_post_views( get_the_id() ) ) ) : ?>
				<div class="post-views"><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" style="position: relative; top: 1px; margin-right: 1px;"><path fill="#ffffff" d="M15 12c0 1.654-1.346 3-3 3s-3-1.346-3-3 1.346-3 3-3 3 1.346 3 3zm9-.449s-4.252 8.449-11.985 8.449c-7.18 0-12.015-8.449-12.015-8.449s4.446-7.551 12.015-7.551c7.694 0 11.985 7.551 11.985 7.551zm-7 .449c0-2.757-2.243-5-5-5s-5 2.243-5 5 2.243 5 5 5 5-2.243 5-5z"/></svg> <?php echo wpst_get_human_number( wpst_get_post_views( get_the_id() ) ); ?></div>
			<?php endif; ?>
		</a>
		<a class="remove-fav" href="#!"><svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 24 24" width="30" height="30" xmlns="http://www.w3.org/2000/svg"><path fill="#ffffff" d="m12 10.93 5.719-5.72c.146-.146.339-.219.531-.219.404 0 .75.324.75.749 0 .193-.073.385-.219.532l-5.72 5.719 5.719 5.719c.147.147.22.339.22.531 0 .427-.349.75-.75.75-.192 0-.385-.073-.531-.219l-5.719-5.719-5.719 5.719c-.146.146-.339.219-.531.219-.401 0-.75-.323-.75-.75 0-.192.073-.384.22-.531l5.719-5.719-5.72-5.719c-.146-.147-.219-.339-.219-.532 0-.425.346-.749.75-.749.192 0 .385.073.531.219z"/></svg></a>
	</div>
</li>
