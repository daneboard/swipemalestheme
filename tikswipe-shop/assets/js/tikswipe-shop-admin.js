/* global jQuery, wp, tssAdmin */
jQuery(function ($) {
	'use strict';

	$('.tss-image-picker').each(function () {
		var $wrap     = $(this);
		var target    = $wrap.data('target');
		var pickLabel = target === 'icon' ? tssAdmin.i18n.pickIcon : tssAdmin.i18n.pickImage;
		var useLabel  = target === 'icon' ? tssAdmin.i18n.useIcon : tssAdmin.i18n.useImage;
		var frame;

		$wrap.on('click', '.tss-pick-media', function (e) {
			e.preventDefault();
			if (frame) { frame.open(); return; }
			frame = wp.media({
				title: pickLabel,
				button: { text: useLabel },
				library: { type: 'image' },
				multiple: false,
			});
			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				$wrap.find('.tss-image-id').val(att.id);
				$wrap.find('.tss-image-url').val('');
				$wrap.find('.tss-image-preview').html('<img src="' + att.url + '" alt="">');
			});
			frame.open();
		});

		$wrap.on('click', '.tss-clear-image', function (e) {
			e.preventDefault();
			$wrap.find('.tss-image-id').val('');
			$wrap.find('.tss-image-url').val('');
			$wrap.find('.tss-image-preview').empty();
		});
	});

	var $picker   = $('.tss-post-picker');
	var $input    = $picker.find('.tss-post-search');
	var $results  = $picker.find('.tss-post-results');
	var $selected = $picker.find('.tss-post-selected');
	var searchTimer;

	function selectedIds() {
		return $selected.find('li').map(function () { return parseInt($(this).data('id'), 10); }).get();
	}

	function addPost(id, title) {
		if (selectedIds().indexOf(id) !== -1) { return; }
		var $li = $(
			'<li data-id="' + id + '">' +
				'<span class="tss-pid">#' + id + '</span> ' +
				'<span class="tss-ptitle"></span> ' +
				'<button type="button" class="button-link tss-remove-post">&times;</button>' +
				'<input type="hidden" name="tss_target_post_ids[]" value="' + id + '">' +
			'</li>'
		);
		$li.find('.tss-ptitle').text(title);
		$selected.append($li);
	}

	$input.on('input', function () {
		clearTimeout(searchTimer);
		var q = $.trim($(this).val());
		if (q.length < 1) {
			$results.removeClass('is-open').empty();
			return;
		}
		searchTimer = setTimeout(function () {
			$.getJSON(tssAdmin.ajaxUrl, {
				action: 'tss_search_posts',
				nonce: tssAdmin.nonce,
				q: q,
			}).done(function (res) {
				$results.empty();
				if (!res.success || !res.data.length) {
					$results.append('<li class="tss-empty">' + tssAdmin.i18n.noResults + '</li>');
				} else {
					res.data.forEach(function (item) {
						$('<li>')
							.attr('data-id', item.id)
							.attr('data-title', item.title)
							.text('#' + item.id + ' — ' + item.title)
							.appendTo($results);
					});
				}
				$results.addClass('is-open');
			});
		}, 250);
	});

	$results.on('click', 'li[data-id]', function () {
		addPost(parseInt($(this).data('id'), 10), $(this).data('title') || '');
		$input.val('');
		$results.removeClass('is-open').empty();
	});

	$selected.on('click', '.tss-remove-post', function () {
		$(this).closest('li').remove();
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('.tss-post-picker').length) {
			$results.removeClass('is-open');
		}
	});

	$(document).on('change', '.tss-dashicon-grid input[type=radio]', function () {
		var $grid = $(this).closest('.tss-dashicon-grid');
		$grid.find('.tss-dashicon-item').removeClass('is-selected');
		$(this).closest('.tss-dashicon-item').addClass('is-selected');
	});
});
