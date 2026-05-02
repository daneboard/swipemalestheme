<?php
/**
 * Static iframe template served at /post-slug/embed/.
 *
 * No <video> element, no player JS. Click anywhere on the card breaks out
 * of the iframe and navigates the parent window to the original post.
 *
 * @package TikSwipe_Embed
 */

defined( 'ABSPATH' ) || exit;

$post_id   = get_the_ID();
$permalink = get_permalink( $post_id );
$title     = get_the_title( $post_id );
$author_id = (int) get_post_field( 'post_author', $post_id );
$author    = $author_id ? get_user_by( 'id', $author_id ) : false;
$avatar    = $author_id ? get_avatar_url( $author_id, array( 'size' => 64 ) ) : '';

$poster = '';
if ( has_post_thumbnail( $post_id ) ) {
	$poster = get_the_post_thumbnail_url( $post_id, 'ms-large' );
}

$views    = function_exists( 'wpst_get_post_views' ) ? wpst_get_post_views( $post_id ) : 0;
$comments = (int) get_comments_number( $post_id );
$duration = tse_format_duration( get_post_meta( $post_id, 'duration', true ) );

$format = get_post_format( $post_id );

$site_name = get_bloginfo( 'name' );
$site_host = wp_parse_url( home_url(), PHP_URL_HOST );

$site_logo = '';
if ( function_exists( 'has_custom_logo' ) && has_custom_logo() ) {
	$logo_id = get_theme_mod( 'custom_logo' );
	$logo    = $logo_id ? wp_get_attachment_image_src( $logo_id, 'full' ) : false;
	if ( $logo ) {
		$site_logo = $logo[0];
	}
}

$author_label = $author ? ( $author->display_name ? $author->display_name : $author->user_login ) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $title ); ?> &mdash; <?php echo esc_html( $site_name ); ?></title>
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

		<?php if ( 'video' === $format ) : ?>
			<div class="tse-play-btn" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">
					<circle cx="32" cy="32" r="32" fill="rgba(0,0,0,0.55)"/>
					<path d="M26 20 L46 32 L26 44 Z" fill="#fff"/>
				</svg>
			</div>
			<?php if ( $duration ) : ?>
				<span class="tse-duration"><?php echo esc_html( $duration ); ?></span>
			<?php endif; ?>
		<?php else : ?>
			<div class="tse-image-icon" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" fill="#fff">
					<path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
				</svg>
			</div>
		<?php endif; ?>
	</div>

	<div class="tse-info">
		<div class="tse-author">
			<?php if ( $avatar ) : ?>
				<img class="tse-avatar" src="<?php echo esc_url( $avatar ); ?>" alt="" width="32" height="32" loading="lazy">
			<?php endif; ?>
			<?php if ( $author_label ) : ?>
				<span class="tse-author-name"><?php echo esc_html( $author_label ); ?></span>
			<?php endif; ?>
		</div>

		<h2 class="tse-title"><?php echo esc_html( $title ); ?></h2>

		<div class="tse-actions">
			<span class="tse-action tse-views" title="<?php esc_attr_e( 'Views', 'tikswipe-embed' ); ?>">
				<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M12 4.5C7 4.5 2.7 7.6 1 12c1.7 4.4 6 7.5 11 7.5s9.3-3.1 11-7.5c-1.7-4.4-6-7.5-11-7.5zm0 12.5a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/></svg>
				<span><?php echo esc_html( tse_format_count( $views ) ); ?></span>
			</span>
			<span class="tse-action tse-comments" title="<?php esc_attr_e( 'Comments', 'tikswipe-embed' ); ?>">
				<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M21 6h-2v9H6v2c0 .55.45 1 1 1h11l4 4V7c0-.55-.45-1-1-1zm-4 6V3c0-.55-.45-1-1-1H3c-.55 0-1 .45-1 1v14l4-4h10c.55 0 1-.45 1-1z"/></svg>
				<span><?php echo esc_html( tse_format_count( $comments ) ); ?></span>
			</span>
			<span class="tse-action tse-fav" title="<?php esc_attr_e( 'Like', 'tikswipe-embed' ); ?>" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
			</span>
			<span class="tse-action tse-share" title="<?php esc_attr_e( 'Share', 'tikswipe-embed' ); ?>" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92S19.61 16.08 18 16.08z"/></svg>
			</span>
		</div>

		<div class="tse-branding">
			<?php if ( $site_logo ) : ?>
				<img class="tse-logo" src="<?php echo esc_url( $site_logo ); ?>" alt="" loading="lazy">
			<?php endif; ?>
			<span class="tse-brand-name"><?php echo esc_html( $site_host ); ?></span>
		</div>
	</div>
</a>

<?php do_action( 'tse_embed_footer', $post_id ); ?>
</body>
</html>
