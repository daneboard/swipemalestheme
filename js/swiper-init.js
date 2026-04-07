jQuery(document).ready(function () {
	const swiper = new Swiper('.swiper', {
		// Optional parameters
		direction: 'vertical',
		loop: false,
        threshold: 2,
		navigation: {
			nextEl: '.swiper-button-next',
			prevEl: '.swiper-button-prev',
		},
		on: {
            transitionStart: function () {
				// Post views
				var postId = 0;
				postId = jQuery('.swiper-slide-active').data('id');
				jQuery.ajax({
					type: 'post',
					url: wpst_ajax_var.url,
					dataType: 'json',
					data: {
						action: 'get-post-data',
						nonce: wpst_ajax_var.nonce,
						post_id: postId,
					},
				});
			},
		}
    });

    swiper.on('reachEnd', function () {
		var data = {
			'action': 'loadmore_swipe',
			'nonce': loadmore_ajax_var.nonce,
			'query': loadmore_ajax_var.posts,
            'page': loadmore_ajax_var.current_page
		};
		// console.log(loadmore_ajax_var.max_page);
		// if( jQuery(document).scrollTop() > ( jQuery(document).height() - bottomOffset ) && canBeLoaded == true ){
		jQuery.ajax({
			url: loadmore_ajax_var.ajaxurl,
			data: data,
			type: 'POST',
			beforeSend: function (xhr) {
				// you can also add your own preloader here
				// you see, the AJAX call is in process, we shouldn't run it again until complete
				canBeLoaded = false;
			},
			success: function (data) {
				if (data) {
					jQuery('.swiper-wrapper').find('.swiper-slide-active').after(data);
					// canBeLoaded = true; // the ajax is completed, now we can run it again
					loadmore_ajax_var.current_page++;
					swiper.update();
				}/*else{
						jQuery('#loadmore').hide();
					}*/
			}
		});
		// }
	});

	jQuery(document).on('click', '.enlight-content', function (e) {
		swiper.disable();
	});

	jQuery(document).on('click', '.close-fullscreen', function (e) {
		swiper.enable();
	});

	// Comments
	jQuery(document).on('click', '.comment-icon.comment-closed', function (e) {
		jQuery(this).addClass('comment-opened').removeClass('comment-closed');
		jQuery('#nav-menu').addClass('comment-opened');
		jQuery('#reply-title').addClass('.button .button-color');
		e.preventDefault();
		// var this_field = jQuery(this);
		var postID = jQuery(this).parents('.swiper-slide').data('id');
		var data = {
			'action': 'load_post_comments',
			'nonce': loadmore_ajax_var.nonce,
			'post_id': postID
		};
		jQuery.ajax({
			url: loadmore_ajax_var.ajaxurl,
			data: data,
			type: 'POST',
			beforeSend: function () {
			},
			success: function (data) {
				swiper.disable();
				jQuery('.dark-bg').show();
				var videos = document.querySelectorAll('video');
				Array.prototype.forEach.call(videos, function (video) {
					video.pause();
				});
				jQuery('main').prepend(data);
				var commentRespondHeight = jQuery('.comment-respond').outerHeight();
				jQuery('.comments-list').css('height', 'calc(100% - ' + commentRespondHeight + 'px - 70px)');
				jQuery('.author-first-letter').each(function () {
					jQuery(this).css('background-color', 'hsla(' + Math.floor(Math.random() * (360)) + ', 75%, 58%, 1)');
				});
				var commentform = jQuery('#commentform'); // find the comment form
				commentform.prepend('<div id="comment-status" ></div>'); // add info panel before the form to provide feedback or errors
				var statusdiv = jQuery('#comment-status'); // define the info panel
				// var list ;
				jQuery('a.comment-reply-link').click(function (e) {
					e.preventDefault();
				});

				commentform.submit(function () {
					//serialize and store form data in a variable
					var formdata = commentform.serialize();
					//Add a status message
					statusdiv.html('<p>Processing...</p>');
					//Extract action URL from commentform
					var formurl = commentform.attr('action');
					//Post Form with data					
					jQuery.ajax({
						type: 'post',
						url: formurl,
						data: formdata,
						error: function (XMLHttpRequest, textStatus, errorThrown) {
							statusdiv.html('<p class="alert alert-error">You might have left one of the fields blank, or be posting too quickly</p>');
						},
						success: function (data, textStatus) {
							if (data == "success" || textStatus == "success") {
								jQuery('#reply-title').hide();
								jQuery('#commentform .comment-fields').hide();
								jQuery('#commentform textarea').hide();
								jQuery('#commentform .form-submit').hide();
								statusdiv.html('<p class="alert alert-success" >Thank you. Your comment is awaiting moderation.</p>');
								if (jQuery(".comment-box").has("ol.commentlist").length > 0) {
									jQuery('ol.commentlist').prepend(data);
									jQuery('.author-first-letter').each(function () {
										jQuery(this).css('background-color', 'hsla(' + Math.floor(Math.random() * (360)) + ', 75%, 58%, 1)');
									});
								} else {
									jQuery('ol.commentlist').html(data);
								}
							} else {
								statusdiv.html('<p class="alert alert-error">Please wait a while before posting your next comment</p>');
								commentform.find('textarea[name=comment]').val();
							}
						}
					});
					return false;
				});
			}
		});
	});

	jQuery(document).on('click', '.dark-bg', function () {
		swiper.enable();
		jQuery('.comment-box').remove();
		jQuery('.comment-icon.comment-opened').addClass('comment-closed').removeClass('comment-opened');
	});

	jQuery(document).on('click', '#respond #reply-title', function () {
		var replySpan = jQuery(this).find('span');
		replySpan.toggleClass('button button-color');
		replySpan.html('Leave a comment <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" style="position: relative; top: 2px; margin-left: 3px;"><path fill="#333333" d="M0 7.33l2.829-2.83 9.175 9.339 9.167-9.339 2.829 2.83-11.996 12.17z"/></svg>');
		if (replySpan.hasClass('button')) {
			replySpan.html('Leave a comment <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" style="position: relative; top: 2px; margin-left: 3px;"><path fill="#ffffff" d="M0 16.67l2.829 2.83 9.175-9.339 9.167 9.339 2.829-2.83-11.996-12.17z"/></svg>');
		}
		jQuery(this).parents('#respond').find('#commentform').toggle();
	});

	jQuery(document).on('click', '.close-comment-box', function (e) {
		e.preventDefault();
		swiper.enable();
		jQuery('.dark-bg').hide();
		jQuery('.comment-box').remove();
		jQuery('.comment-icon.comment-opened').addClass('comment-closed').removeClass('comment-opened');
	});

});