<?php
function wpst_load_post_comments() {
	if ( ! wp_verify_nonce( $_POST['nonce'], 'ajax-nonce' ) ) {
		die( 'Busted!' );
	}
	$post_id = $_POST['post_id'];
	?>

<div class="comment-dark-bg"></div>
<div id="comment-box-<?php echo $post_id; ?>" class="slideup-box comment-box">
	<div class="comment-box-head">
		<div class="comment-post-title">
			<?php $post_thumb = get_the_post_thumbnail_url( $post_id, 'ms-thumb' ); ?>
			<div class="comment-media-box">
				<img src="<?php echo esc_url( $post_thumb ); ?>">
			</div>
			<div>
				<span class="title"><?php echo get_the_title( $post_id ); ?></span>
				<span class="comment-number"><span><?php echo get_comments_number( $post_id ); ?></span> <?php esc_html_e( 'comment', 'wpst' ); ?>
																<?php
																if ( get_comments_number( $post_id ) > 0 ) :
																	?>
					s<?php endif; ?></span>
			</div>
		</div>
		<a class="close-comment-box" href="#!"><svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 24 24" width="30" height="30" xmlns="http://www.w3.org/2000/svg" style="position: relative; top: 2px;"><path fill="#333333" d="m12 10.93 5.719-5.72c.146-.146.339-.219.531-.219.404 0 .75.324.75.749 0 .193-.073.385-.219.532l-5.72 5.719 5.719 5.719c.147.147.22.339.22.531 0 .427-.349.75-.75.75-.192 0-.385-.073-.531-.219l-5.719-5.719-5.719 5.719c-.146.146-.339.219-.531.219-.401 0-.75-.323-.75-.75 0-.192.073-.384.22-.531l5.719-5.719-5.72-5.719c-.146-.147-.219-.339-.219-.532 0-.425.346-.749.75-.749.192 0 .385.073.531.219z"/></svg></a>
	</div>
	<?php if ( comments_open( $post_id ) ) : ?>
		<?php
		$comments = get_comments(
			array(
				'post_id' => $post_id,
			)
		);
		?>

		<div class="comments-list">
			<ol class="commentlist">
				<?php
				wp_list_comments(
					array(
						'short_ping'  => true,
						'avatar_size' => 40,
						'callback'    => 'wpst_custom_comment_list',
					),
					$comments
				);
				?>
			</ol>
		</div>
		<?php
		$comments_args = array(
			'title_reply'          => '<span class="button button-color">Leave a comment <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" style="position: relative; top: 2px; margin-left: 3px;"><path fill="#ffffff" d="M0 16.67l2.829 2.83 9.175-9.339 9.167 9.339 2.829-2.83-11.996-12.17z"/></svg></span>',
			'fields'               => array(
				'author' => '<div class="comment-fields"><div class="comment-form-author"><input id="author-' . $post_id . '" name="author" class="required" type="text" value="" placeholder="Your name" required></div>',
				'email'  => '<div class="comment-form-email"><input id="email-' . $post_id . '" name="email" class="required email" type="email" value="" placeholder="Your email" required></div></div>',
			),
			'comment_field'        => '<textarea id="comment-' . $post_id . '" name="comment" placeholder="' . esc_html__( 'Your comment', 'wpst' ) . '" required></textarea>',
			'comment_notes_before' => '',
			'submit_button'        => '<button class="button button-color">' . esc_html__( 'Send comment', 'wpst' ) . '</button>',
		);
		comment_form( $comments_args, $post_id );
		?>
	<?php else : ?>
		<p><?php esc_html_e( 'Comments are closed.', 'wpst' ); ?></p>
	<?php endif; ?>
</div>

	<?php
	die();
}

eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_comments_eval_1' ) );
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_comments_eval_2' ) );


function wpst_ajaxify_comments( $comment_ID, $comment_status ) {
	if ( ! empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) == 'xmlhttprequest' ) {
		// If AJAX Request Then
		switch ( $comment_status ) {
			case '0':
				// notify moderator of unapproved comment
				wp_notify_moderator( $comment_ID );
			case '1': // Approved comment
				$commentdata = &get_comment( $comment_ID, ARRAY_A );

				eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_comments_eval_3' ) );

				$comment_author_first_letter = strtoupper( substr( $comment_author, 0, 1 ) );
				$comment_author_email        = get_comment_author_email( $comment_ID );

				eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_comments_eval_4' ) );

				$profile_avatar_basename = '';
				if ( $comment_user_role ) {
					$profile_avatar_basename = esc_html( get_user_meta( $comment_user_role->ID, '_author_profile_avatar_basename', true ) );
				}
				if ( ! empty( $profile_avatar_basename ) ) {
					$upload_dir   = wp_upload_dir();
					$gravatar_url = $upload_dir['baseurl'] . '/' . $profile_avatar_basename;
					$gravatar_img = '<img src="' . $gravatar_url . '" width="40" height="40" />';
				} else {
					$gravatar_img = '<span class="author-first-letter">' . $comment_author_first_letter . '</span>';
				}
				$permaurl = get_permalink( $post->ID );
				$url      = str_replace( 'http://', '/', $permaurl );
				$output   = '<li class="comment even thread-even depth-1 ms-comment-awaiting-moderation" id="li-comment-' . $commentdata['comment_ID'] . '">
					<div id="div-comment-' . $commentdata['comment_ID'] . '" class="comment-body">
						<div class="avatar">' . $gravatar_img . '</div>
						<div class="comment-author">
							<div class="comment-bubble">
								<strong>' . $commentdata['comment_author'] . '</strong>
								<p>' . $commentdata['comment_content'] . '</p>
							</div>
						</div>
					</div>
					<div class="comment-footer">
						<p class="comment-awaiting-moderation">Your comment is awaiting moderation.</p>
					</div>
				</li>';
				echo $output;

				$post = &get_post( $commentdata['comment_post_ID'] );
				wp_notify_postauthor( $comment_ID );
				break;
			default:
				echo 'error';
		}
		exit;
	}
}
eval( WPSCORE()->eval_product_data( WPSCORE()->get_installed_theme( 'sku' ), 'ajax_comments_eval_5' ) );
