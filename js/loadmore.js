jQuery(document).ready(function () {

	jQuery(document).on('click', '.enlight-content', function (e) {
		e.preventDefault();
		jQuery('.embed-thumbnail').hide();
		jQuery('.embed-play-button').hide();
		jQuery('.embed-content').css('opacity', '1');
		jQuery('.close-fullscreen').show();
		jQuery('header, .slide-bg, .swiper-button-next, .swiper-button-prev, .single-content-infos, .swiper-side, footer').addClass('hidden');
		jQuery(this).parents('.swiper-slide').find('.vjs-big-play-button').click();
		jQuery(this).parents('.swiper-slide').find('.vjs-control-bar').addClass('show-control-bar');
	});

	jQuery(document).on('click', '.close-fullscreen', function (e) {
		e.preventDefault();
		jQuery('.close-fullscreen').hide();
		jQuery('.embed-thumbnail').show();
		jQuery('.embed-play-button').show();
		jQuery('.embed-content').css('opacity', '0');
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
		jQuery('header, .slide-bg, .swiper-button-next, .swiper-button-prev, .single-content-infos, .swiper-side, footer').removeClass('hidden');
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
					beforeSend: function (e) {
					},
					success: function (response) {
						this_field.removeClass('active').html('<svg xmlns="http://www.w3.org/2000/svg" fill="none" width="32" height="32" viewBox="3.9 4.9 17.2 16.2"><path d="M17 16C15.8 17.3235 12.5 20.5 12.5 20.5C12.5 20.5 9.2 17.3235 8 16C5.2 12.9118 4.5 11.7059 4.5 9.5C4.5 7.29412 6.1 5.5 8.5 5.5C10.5 5.5 11.7 6.82353 12.5 8.14706C13.3 6.82353 14.5 5.5 16.5 5.5C18.9 5.5 20.5 7.29412 20.5 9.5C20.5 11.7059 19.8 12.9118 17 16Z" stroke="#ffffff" stroke-width="1.2"/></svg>');
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
						this_field.removeClass('add-fav').addClass('fav-added').html('<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="3.9 4.9 17.2 16.2"><path d="M17 16C15.8 17.3235 12.5 20.5 12.5 20.5C12.5 20.5 9.2 17.3235 8 16C5.2 12.9118 4.5 11.7059 4.5 9.5C4.5 7.29412 6.1 5.5 8.5 5.5C10.5 5.5 11.7 6.82353 12.5 8.14706C13.3 6.82353 14.5 5.5 16.5 5.5C18.9 5.5 20.5 7.29412 20.5 9.5C20.5 11.7059 19.8 12.9118 17 16Z" fill="#ffffff" stroke="#ffffff" stroke-width="1.2"/></svg>');
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
				beforeSend: function (e) {
				},
				success: function (response) {
					this_field.removeClass('active').html('<svg xmlns="http://www.w3.org/2000/svg" fill="none" width="32" height="32" viewBox="3.9 4.9 17.2 16.2"><path d="M17 16C15.8 17.3235 12.5 20.5 12.5 20.5C12.5 20.5 9.2 17.3235 8 16C5.2 12.9118 4.5 11.7059 4.5 9.5C4.5 7.29412 6.1 5.5 8.5 5.5C10.5 5.5 11.7 6.82353 12.5 8.14706C13.3 6.82353 14.5 5.5 16.5 5.5C18.9 5.5 20.5 7.29412 20.5 9.5C20.5 11.7059 19.8 12.9118 17 16Z" stroke="#ffffff" stroke-width="1.2"/></svg>');
				}
			});
		}
	});

	// Copy link to clipboard
	new ClipboardJS('.copy-link');
	jQuery(document).on('click', '.copy-link', function (e) {
		e.preventDefault();
		var copyLink = jQuery(this);
		copyLink.html('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="32" height="32" viewBox="0 0 512 512" style="enable-background:new 0 0 512 512;" xml:space="preserve"><path fill="#ffffff" d="M512,253.001L295.204,36.204v144.388C132.168,180.592,0,312.76,0,475.796c59.893-109.171,178.724-165.462,295.204-151.033 v145.034L512,253.001z"/></svg><small>Copied</small>');
		setTimeout(function () {
			copyLink.html('<svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" width="32" height="32" viewBox="0 0 512 512" style="enable-background:new 0 0 512 512;" xml:space="preserve"><path fill="#ffffff" d="M512,241.7L273.643,3.343v156.152c-71.41,3.744-138.015,33.337-188.958,84.28C30.075,298.384,0,370.991,0,448.222v60.436 l29.069-52.985c45.354-82.671,132.173-134.027,226.573-134.027c5.986,0,12.004,0.212,18.001,0.632v157.779L512,241.7z M255.642,290.666c-84.543,0-163.661,36.792-217.939,98.885c26.634-114.177,129.256-199.483,251.429-199.483h15.489V78.131 l163.568,163.568L304.621,405.267V294.531l-13.585-1.683C279.347,291.401,267.439,290.666,255.642,290.666z"/></svg>');
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
