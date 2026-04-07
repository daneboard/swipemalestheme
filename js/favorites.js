jQuery( document ).ready(function() {
	
	// Remove post from favorites
	jQuery(document).on('click', '.remove-fav', function(e) {
		e.preventDefault();
		var this_field = jQuery(this);
		var parentLi = this_field.parents('li');
		var postId = parentLi.data('id');
		var userId = wpst_ajax_var.logged_in_user_id;
		var activeFavCount = jQuery('.tab-link.active .count').text();
		if( postId ){
			jQuery.ajax({
				url : wpst_ajax_var.url,
				type: "POST",
				data: {
					action: 'wpst_remove_from_favorites',
					nonce: wpst_ajax_var.nonce,
					post_id: postId,
					user_id: userId
				},
				beforeSend: function(e){
				},
				success: function(response) {
					parentLi.fadeOut();
					jQuery('.tab-link.active .count').text( activeFavCount - 1 );
				}
			});
		}
	});	

});