jQuery(document).ready(function () {

	jQuery(document).on('click', '.enlight-content', function (e) {
		e.preventDefault();
		jQuery('.embed-thumbnail').hide();
		jQuery('.embed-play-button').hide();
		jQuery('.embed-content').css('opacity', '1');
		jQuery('.close-fullscreen').show();
		// Add fullscreen class to hide gradient
		jQuery(this).parents('.swiper-slide').addClass('wpst-fullscreen');
		jQuery('header, .slide-bg, .swiper-button-next, .swiper-button-prev, .single-content-infos, .swiper-side, .wpst-progress-bar, footer').addClass('hidden');
		jQuery(this).parents('.swiper-slide').find('.vjs-big-play-button').click();
		jQuery(this).parents('.swiper-slide').find('.vjs-control-bar').addClass('show-control-bar');
	});

	jQuery(document).on('click', '.close-fullscreen', function (e) {
		e.preventDefault();
		jQuery('.close-fullscreen').hide();
		jQuery('.embed-thumbnail').show();
		jQuery('.embed-play-button').show();
		jQuery('.embed-content').css('opacity', '0');
		// Remove fullscreen class to show gradient again
		jQuery(this).parents('.swiper-slide').removeClass('wpst-fullscreen');
		var iframe = jQuery(this).parents('.swiper-slide').find('iframe').get(0);
		if (iframe) {
			var iframeSrc = iframe.src;
			if (iframeSrc.indexOf("?") == -1) {
				var iframeSrc = iframeSrc + "?autoplay=false";
			} else {
				var iframeSrc = iframeSrc + "&autoplay=false";
			}
			jQuery(iframe).attr('src', iframeSrc);
		}
		jQuery('header, .slide-bg, .swiper-button-next, .swiper-button-prev, .single-content-infos, .swiper-side, .wpst-progress-bar, footer').removeClass('hidden');
		jQuery('.playvideo').show();
		jQuery(this).parents('.swiper-slide').find('.vjs-control-bar').removeClass('show-control-bar');
		jQuery('.vjs-control-bar').hide();
	});

    jQuery(document).on('click', '.slide-bg.video', function () {
        var videoPlayerID = jQuery(this).parents('.swiper-slide').find('video-js').attr('id');
        var player = videojs.getPlayer(videoPlayerID);

        if (!player) {
            return;
        }

        player.ready(function () {
            if (this.paused()) {
                this.play();
            } else {
                this.pause();
            }
        });
	});

	jQuery(document).on('click', '.playvideo', function (e) {
		e.preventDefault();
		jQuery(this).parents('.swiper-slide').find('.enlight-content').trigger('click');
		jQuery(this).hide();
	});

	jQuery(document).on('click', '.see-desc', function (e) {
		e.preventDefault();
		jQuery(this).parents('.post-desc').find('p').slideToggle('fast');
	});

	// Like pop animation helper
	function triggerPop(el) {
		el.addClass('wpst-pop');
		setTimeout(function () { el.removeClass('wpst-pop'); }, 350);
	}

	jQuery(document).on('click', '.add-fav', function (e) {
		e.preventDefault();
		var this_field = jQuery(this);
		var postId = this_field.parents('.swiper-slide').data('id');
		var userId = wpst_ajax_var.logged_in_user_id;
		if (postId) {
			if (this_field.hasClass('fav-added')) {
				jQuery.ajax({
					url: wpst_ajax_var.url,
					type: "POST",
					data: {
						action: 'wpst_remove_from_favorites',
						nonce: wpst_ajax_var.nonce,
						post_id: postId,
						user_id: userId
					},
					success: function (response) {
						this_field.removeClass('active').html('<svg width="28" height="28"><use href="#wpst-icon-heart-outline"/></svg>');
						triggerPop(this_field);
					}
				});
			} else {
				jQuery.ajax({
					url: wpst_ajax_var.url,
					type: "POST",
					data: {
						action: 'wpst_add_to_favorites',
						nonce: wpst_ajax_var.nonce,
						post_id: postId,
						user_id: userId
					},
					beforeSend: function () {
						this_field.html('<svg class="spinner" viewBox="0 0 50 50"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>');
					},
					success: function () {
						this_field.removeClass('add-fav').addClass('fav-added').html('<svg width="28" height="28"><use href="#wpst-icon-heart-filled"/></svg>');
						triggerPop(this_field);
					}
				});
			}
		}
	});

	jQuery(document).on('click', '.fav-added', function (e) {
		e.preventDefault();
		var this_field = jQuery(this);
		var postId = this_field.parents('.swiper-slide').data('id');
		var userId = wpst_ajax_var.logged_in_user_id;
		if (postId) {
			jQuery.ajax({
				url: wpst_ajax_var.url,
				type: "POST",
				data: {
					action: 'wpst_remove_from_favorites',
					nonce: wpst_ajax_var.nonce,
					post_id: postId,
					user_id: userId
				},
				success: function (response) {
					this_field.removeClass('active').html('<svg width="28" height="28"><use href="#wpst-icon-heart-outline"/></svg>');
					triggerPop(this_field);
				}
			});
		}
	});

	// Copy link to clipboard
	new ClipboardJS('.copy-link');
	jQuery(document).on('click', '.copy-link', function (e) {
		e.preventDefault();
		var copyLink = jQuery(this);
		copyLink.html('<svg width="28" height="28"><use href="#wpst-icon-share"/></svg><small>Copied</small>');
		setTimeout(function () {
			copyLink.html('<svg width="28" height="28"><use href="#wpst-icon-share"/></svg>');
		}, 2000);
	});

	// Post views with ajax request for cache compatibility
	(function () {
		var postId = 0;
		postId = jQuery('.swiper-slide').data('id');
		if (postId) {
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
		}
		return;
	}());

});
