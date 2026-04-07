<?php
	$post_format = get_post_format();
?>
<?php
$item       = '';
$meta_items = '';
if ( 'video' === $post_format ) {
	$item = 'itemprop="video" itemscope itemtype="https://schema.org/VideoObject"';

	$author      = get_the_author();
	$video_title = get_the_title();
	$desc        = wp_strip_all_tags( get_the_content() );
	$duration    = get_post_meta( $post->ID, 'duration', true );
	$embed_code  = get_post_meta( $post->ID, 'embed', true );
	$video_url   = get_post_meta( $post->ID, 'video_url', true );
	$embed_url   = '';
	if ( $embed_code != '' ) {
		preg_match( '/src=["\']([^"]+)["\']/', $embed_code, $match );
		if ( isset( $match[1] ) ) {
			$embed_url = $match[1];
		}
	}
	$poster = '';
	$thumb  = get_post_meta( $post->ID, 'thumb', true );
	if ( has_post_thumbnail() ) {
		$thumb_id  = get_post_thumbnail_id();
		$thumb_url = wp_get_attachment_image_src( $thumb_id, 'full', true );
		$poster    = $thumb_url[0];
	} else {
		$poster = $thumb;
	}

	$meta_items = implode(
		'',
		array(
			'author'      => '<meta itemprop="author" content="' . $author . '" />',
			'name'        => '<meta itemprop="name" content="' . $video_title . '" />',
			'description' => '' !== $desc ? '<meta itemprop="description" content="' . $desc . '" />' : '<meta itemprop="description" content="' . $video_title . '" />',
			'duration'    => '<meta itemprop="duration" content="' . wpst_iso8601_duration( $duration ) . '" />',
			'thumbnail'   => '<meta itemprop="thumbnailUrl" content="' . $poster . '" />',
			'contentURL'  => '' !== $video_url ? '<meta itemprop="contentURL" content="' . $video_url . '" />' : ( '' !== $embed_code ? '<meta itemprop="embedURL" content="' . $embed_url . '" />' : '' ),
			'uploadDate'  => '<meta itemprop="uploadDate" content="' . get_the_date( 'c' ) . '" />',
		)
	);
}
?>

<div class="swiper-slide<?php echo $post_format === 'video' ? ' swiper-video-slide' : ''; ?>" data-id="<?php echo esc_attr( get_the_id() ); ?>" <?php echo $item; ?>>

	<?php echo $meta_items; ?>

	<?php echo apply_filters( 'wps_paywall_premium_badge', '', get_the_id() ); ?>
	<?php
	ob_start(
		function ( $buffer ) {
			return apply_filters( 'wps_paywall_media_content', $buffer, get_the_id() );
		}
	);

	$current_post_id = get_the_id();
	$is_portrait     = get_post_meta( $current_post_id, '_file_format', true ) === 'portrait';
	$embed           = get_post_meta( $current_post_id, 'embed', true );
	$video_file_url  = get_post_meta( $current_post_id, '_video_file_url', true );
	$video_url       = get_post_meta( $current_post_id, 'video_url', true );
	$has_thumbnail   = has_post_thumbnail();
	$image_url       = $has_thumbnail ? get_the_post_thumbnail_url( $current_post_id, 'ms-large' ) : '';
	?>

	<div class="slide-bg <?php echo esc_attr( $post_format ); ?>"></div>

	<?php if ( 'video' === $post_format ) : ?>
		<?php if ( ! empty( $embed ) ) : ?>
			<?php if ( $has_thumbnail ) : ?>
				<img class="embed-thumbnail content-img<?php echo $is_portrait ? ' is-portrait' : ''; ?>" src="<?php echo esc_url( $image_url ); ?>">
			<?php endif; ?>
			<div class="embed-play-button">
				<a href="#!"><img src="<?php echo esc_url( get_template_directory_uri() . '/img/play.svg' ); ?>" width="50"></a>
			</div>
			<div class="embed-content"><?php echo $embed; ?></div>
			<a class="playvideo" href="#!"></a>
		<?php elseif ( ! empty( $video_file_url ) || ! empty( $video_url ) ) : ?>
			<video-js
				id="video-<?php echo esc_attr( get_query_var( 'paged', 1 ) ); ?>-<?php echo esc_attr( $current_post_id ); ?>"
				data-postid="<?php echo esc_attr( $current_post_id ); ?>"
				class="vjs-default-skin vjs-show-big-play-button-on-pause<?php echo esc_attr( $is_portrait ? ' is-portrait' : '' ); ?>">
			</video-js>
		<?php elseif ( ! empty( $embed ) ) : ?>
			<?php echo $embed; ?>
			<a class="playvideo" href="#!"></a>
		<?php endif; ?>
	<?php elseif ( 'image' === $post_format && $has_thumbnail ) : ?>
		<img class="content-img<?php echo $is_portrait ? ' is-portrait' : ''; ?>" src="<?php echo esc_url( $image_url ); ?>">
	<?php endif; ?>

	<?php ob_end_flush(); ?>

	<?php if ( 'video' === $post_format ) : ?>
		<div class="wpst-progress-bar">
			<div class="wpst-progress-played"></div>
		</div>
	<?php endif; ?>

	<?php get_template_part( 'templates/post', 'content' ); ?>
</div>
