<?php
/**
 * Static iframe template served at /post-slug/embed/.
 *
 * Mirrors the native swiper-slide player layout (.single-content-infos
 * + .swiper-side) but stripped of interactivity and the elements the
 * site does not want exposed in embeds (author avatar, comments).
 *
 * @package TikSwipe_Embed
 */

defined( 'ABSPATH' ) || exit;

global $post;

$post_id   = get_the_ID();
$permalink = get_permalink( $post_id );
$title     = get_the_title( $post_id );

$poster = '';
if ( has_post_thumbnail( $post_id ) ) {
	$poster = get_the_post_thumbnail_url( $post_id, 'ms-large' );
}

$views_raw = function_exists( 'wpst_get_post_views' ) ? wpst_get_post_views( $post_id ) : 0;
$views     = function_exists( 'wpst_get_human_number' )
	? wpst_get_human_number( $views_raw )
	: tse_format_count( $views_raw );

$duration_raw = (int) get_post_meta( $post_id, 'duration', true );
$duration     = '';
if ( $duration_raw > 0 ) {
	$duration = $duration_raw >= 3600 ? gmdate( 'H:i:s', $duration_raw ) : gmdate( 'i:s', $duration_raw );
}

$format = get_post_format( $post_id );

$site_host = wp_parse_url( home_url(), PHP_URL_HOST );

$postcats = get_the_category( $post_id );
$posttags = get_the_tags( $post_id );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $title ); ?> &mdash; <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
<base target="_top">
<link rel="stylesheet" href="<?php echo esc_url( TSE_PLUGIN_URL . 'assets/css/embed.css?ver=' . TSE_VERSION ); ?>">
<?php do_action( 'tse_embed_head', $post_id ); ?>
</head>
<body class="tse-body">

<a class="tse-card" href="<?php echo esc_url( $permalink ); ?>" target="_top" rel="noopener" data-post-id="<?php echo esc_attr( $post_id ); ?>">

	<div class="tse-poster"<?php echo $poster ? ' style="background-image:url(\'' . esc_url( $poster ) . '\')"' : ''; ?>>
		<?php if ( ! $poster ) : ?>
			<div class="tse-poster-fallback"></div>
		<?php endif; ?>
		<div class="tse-overlay-grad" aria-hidden="true"></div>
	</div>

	<?php if ( 'video' === $format ) : ?>
		<div class="tse-play-btn" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
				<circle cx="32" cy="32" r="32" fill="rgba(0,0,0,0.55)"/>
				<path d="M26 20 L46 32 L26 44 Z" fill="#fff"/>
			</svg>
		</div>
	<?php else : ?>
		<div class="tse-image-icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" fill="#fff">
				<path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
			</svg>
		</div>
	<?php endif; ?>

	<div class="single-content-infos">
		<?php if ( $views_raw || $duration ) : ?>
			<div class="post-datas">
				<?php if ( $views_raw ) : ?>
					<div class="post-views">
						<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" style="position:relative;top:1px;margin-right:1px;"><path fill="#ffffff" d="M15 12c0 1.654-1.346 3-3 3s-3-1.346-3-3 1.346-3 3-3 3 1.346 3 3zm9-.449s-4.252 8.449-11.985 8.449c-7.18 0-12.015-8.449-12.015-8.449s4.446-7.551 12.015-7.551c7.694 0 11.985 7.551 11.985 7.551zm-7 .449c0-2.757-2.243-5-5-5s-5 2.243-5 5 2.243 5 5 5 5-2.243 5-5z"/></svg>
						<?php echo esc_html( $views ); ?>
					</div>
				<?php endif; ?>
				<?php if ( $duration ) : ?>
					<div class="post-duration">
						<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" style="position:relative;top:1px;"><path fill="#ffffff" d="M3 22v-20l18 10-18 10z"/></svg>
						<?php echo esc_html( $duration ); ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<h1><?php echo esc_html( $title ); ?></h1>

		<?php if ( $postcats || $posttags ) : ?>
			<div class="tags-list">
				<?php
				$tags_count = 0;
				foreach ( (array) $postcats as $cat ) :
					if ( ! is_object( $cat ) ) {
						continue;
					}
					?>
					<span><?php echo esc_html( $cat->name ); ?><small><?php echo (int) $cat->count; ?></small></span>
					<?php
					if ( ++$tags_count >= 4 ) {
						break;
					}
				endforeach;
				if ( $posttags ) :
					foreach ( (array) $posttags as $tag ) :
						if ( ! is_object( $tag ) ) {
							continue;
						}
						?>
						<span><?php echo esc_html( $tag->name ); ?><small><?php echo (int) $tag->count; ?></small></span>
						<?php
						if ( ++$tags_count >= 4 ) {
							break;
						}
					endforeach;
				endif;
				?>
			</div>
		<?php endif; ?>

		<div class="tse-branding"><?php echo esc_html( $site_host ); ?></div>
	</div>

	<div class="swiper-side">
		<span class="add-to-fav" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" width="32" height="32" viewBox="3.9 4.9 17.2 16.2"><path d="M17 16C15.8 17.3235 12.5 20.5 12.5 20.5C12.5 20.5 9.2 17.3235 8 16C5.2 12.9118 4.5 11.7059 4.5 9.5C4.5 7.29412 6.1 5.5 8.5 5.5C10.5 5.5 11.7 6.82353 12.5 8.14706C13.3 6.82353 14.5 5.5 16.5 5.5C18.9 5.5 20.5 7.29412 20.5 9.5C20.5 11.7059 19.8 12.9118 17 16Z" stroke="#ffffff" stroke-width="1.2"/></svg>
		</span>
		<span class="copy-link" aria-hidden="true">
			<svg version="1.1" xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="32" height="32" viewBox="0 0 512 512"><path fill="#ffffff" d="M512,241.7L273.643,3.343v156.152c-71.41,3.744-138.015,33.337-188.958,84.28C30.075,298.384,0,370.991,0,448.222v60.436 l29.069-52.985c45.354-82.671,132.173-134.027,226.573-134.027c5.986,0,12.004,0.212,18.001,0.632v157.779L512,241.7z M255.642,290.666c-84.543,0-163.661,36.792-217.939,98.885c26.634-114.177,129.256-199.483,251.429-199.483h15.489V78.131 l163.568,163.568L304.621,405.267V294.531l-13.585-1.683C279.347,291.401,267.439,290.666,255.642,290.666z"/></svg>
		</span>
	</div>
</a>

<?php do_action( 'tse_embed_footer', $post_id ); ?>
</body>
</html>
