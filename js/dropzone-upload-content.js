Dropzone.autoDiscover = false; // Disable auto discover to prevent Dropzone being attached twice

jQuery(document).ready(function() {    
	
	jQuery('#dropzone-form-content').validate();

	var dropzone = new Dropzone('#dropzone-content', {
		url: wpst_dropform_content.upload_content,
		method: 'post',
		// autoQueue: false,
		autoProcessQueue: false,
		addRemoveLinks: true,
		params: {
			'wpst-nonce': jQuery('#wpst-nonce').val()
		},
		// paramName: "wpst-file", // name of file field
		// paramName: 'file',
		acceptedFiles: '.jpg, .jpeg, .png, .mp4, .mov',
		maxFilesize: wpst_dropform_content.upload_max_file_size, // MB
		timeout: 0,
		maxFiles: 1,
		dictRemoveFile: '<svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 24 24" width="30" height="30" xmlns="http://www.w3.org/2000/svg"><path fill="#FFFFFF" d="m12 10.93 5.719-5.72c.146-.146.339-.219.531-.219.404 0 .75.324.75.749 0 .193-.073.385-.219.532l-5.72 5.719 5.719 5.719c.147.147.22.339.22.531 0 .427-.349.75-.75.75-.192 0-.385-.073-.531-.219l-5.719-5.719-5.719 5.719c-.146.146-.339.219-.531.219-.401 0-.75-.323-.75-.75 0-.192.073-.384.22-.531l5.719-5.719-5.72-5.719c-.146-.147-.219-.339-.219-.532 0-.425.346-.749.75-.749.192 0 .385.073.531.219z"/></svg>',
		dictCancelUpload: '<svg clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="2" viewBox="0 0 24 24" width="30" height="30" xmlns="http://www.w3.org/2000/svg"><path fill="#FFFFFF" d="m12 10.93 5.719-5.72c.146-.146.339-.219.531-.219.404 0 .75.324.75.749 0 .193-.073.385-.219.532l-5.72 5.719 5.719 5.719c.147.147.22.339.22.531 0 .427-.349.75-.75.75-.192 0-.385-.073-.531-.219l-5.719-5.719-5.719 5.719c-.146.146-.339.219-.531.219-.401 0-.75-.323-.75-.75 0-.192.073-.384.22-.531l5.719-5.719-5.72-5.719c-.146-.147-.219-.339-.219-.532 0-.425.346-.749.75-.749.192 0 .385.073.531.219z"/></svg>',
		thumbnailWidth: 230,
		thumbnailHeight: 230,
		// accept: function (file, done) {
		//     if ('video/mp4' === file.type) {
		//         done();
		//     } else {
		//         done(file.type + ' is not supported. Please use only .mp4 files.');
		//     }
		// },
		init: function() {
			this.on('addedfile', function (file) {			
				jQuery('.dropzone .dz-preview.dz-error').hide();
				jQuery('#submit-dropzone').prop('disabled', false).removeClass('button-disabled');
			});
			this.on('removedfile', function(file){
				jQuery('#submit-dropzone').prop('disabled', true).addClass('button-disabled');
			});

			this.on('sending', function(file, xhr, formData) {				
				formData.append('mimeType', file.type);					
				formData.append('postTitle', jQuery('#postTitle').val());
				formData.append('postContent', jQuery('#postContent').val());
				formData.append('postCategory', jQuery('#postCategory').val());
				formData.append('postTags', jQuery('#postTags').val());
				formData.append('postStatus', jQuery('.post-status:checked').val());
			});

			this.on('uploadprogress', function(file, progress, bytesSent) {					
				if( progress < 100 ) {
					jQuery('.dz-progress').html(
						'<div class="progression" style="width: ' + Math.floor(progress) + '%;" aria-valuenow="' + Math.floor(progress) + '" aria-valuemin="0" aria-valuemax="100"><span class="processing">' + wpst_dropform_content.uploading + '...</span></div>'
					);
				} else {
					setTimeout( function() {
						jQuery('.dz-progress').html(
							'<div class="progression" style="width: 100%;"><span class="processing">' + wpst_dropform_content.export + '...</span></div>'
						);
					}, 1000 );
				}
			});

			this.on('success', function(file) {
				jQuery('.dz-remove').hide();
				window.location.href = wpst_dropform_content.home_url;
			});

			jQuery('#submit-dropzone').on('click', function(e){					
				e.preventDefault();
				e.stopPropagation();				
				if (jQuery('#dropzone-form-content').valid()) {			
					jQuery(this).prop('disabled', true).addClass('button-disabled').html('<svg class="spinner" width="20" height="20" viewBox="0 0 50 50" style="width: 20px; height: 20px; position: relative; top: 3px; margin-right: 2px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg> ' + wpst_dropform_content.sending + '...');
					dropzone.processQueue();
				}
			});

		}

	});	
	
});