/* TikSwipe Reddit Poster — admin modal + column actions */
(function ($) {
	'use strict';

	function api(action, data) {
		return $.post(TSRP.ajax, Object.assign({ action: action, nonce: TSRP.nonce }, data || {}));
	}

	function esc(str) {
		return $('<div/>').text(str == null ? '' : String(str)).html();
	}

	function formatBadge(stats) {
		if (!stats) { return ''; }
		var parts = [];
		parts.push('▲ ' + (stats.ups || 0));
		parts.push('💬 ' + (stats.num_comments || 0));
		if (stats.view_count != null) { parts.push('👁 ' + stats.view_count); }
		if (stats.upvote_ratio) { parts.push(Math.round(stats.upvote_ratio * 100) + '%'); }
		return parts.join(' · ');
	}

	/* ------------------------------------------------------------------
	   Modal skeleton
	   ------------------------------------------------------------------ */

	function openModal(postId) {
		if (!TSRP.configured) {
			alert('Reddit API is not configured. Ask the site admin to set client_id/secret.');
			return;
		}
		if (!TSRP.connected) {
			if (confirm(TSRP.i18n.connect + '\n\nOpen the connection page?')) {
				window.location = TSRP.connectUrl;
			}
			return;
		}
		closeModal();
		var $m = $(
			'<div class="tsrp-modal-overlay">' +
				'<div class="tsrp-modal" role="dialog" aria-modal="true">' +
					'<div class="tsrp-modal-header">' +
						'<h2>' + esc(TSRP.i18n.post) + '</h2>' +
						'<button type="button" class="tsrp-close" aria-label="Close">×</button>' +
					'</div>' +
					'<div class="tsrp-modal-body"><p>' + esc(TSRP.i18n.loading) + '</p></div>' +
					'<div class="tsrp-modal-footer">' +
						'<button type="button" class="button tsrp-cancel">Cancel</button>' +
						'<button type="button" class="button button-primary tsrp-submit" disabled>' + esc(TSRP.i18n.submit) + '</button>' +
					'</div>' +
				'</div>' +
			'</div>'
		);
		$('body').append($m);
		$m.on('click', function (e) { if (e.target === $m[0]) { closeModal(); } });
		$m.find('.tsrp-close, .tsrp-cancel').on('click', closeModal);

		api('tsrp_get_post_data', { post_id: postId }).then(function (res) {
			if (!res.success) { renderError($m, res.data && res.data.message); return; }
			renderForm($m, res.data);
		}, function (xhr) {
			renderError($m, (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Network error');
		});
	}

	function closeModal() {
		$('.tsrp-modal-overlay').remove();
	}

	function renderError($m, msg) {
		$m.find('.tsrp-modal-body').html('<div class="notice notice-error"><p>' + esc(msg || 'Error') + '</p></div>');
	}

	/* ------------------------------------------------------------------
	   Form
	   ------------------------------------------------------------------ */

	function renderForm($m, post) {
		var $body = $m.find('.tsrp-modal-body');
		$body.empty().html(
			'<div class="tsrp-form">' +
				'<div class="tsrp-row"><label>Subreddit</label>' +
					'<input type="text" class="tsrp-subreddit" placeholder="name (without r/)" autocomplete="off" />' +
					'<div class="tsrp-sub-suggestions"></div>' +
					'<div class="tsrp-sub-meta"></div>' +
				'</div>' +
				'<div class="tsrp-row"><label>Format</label>' +
					'<div class="tsrp-kinds">' +
						'<label><input type="radio" name="tsrp-kind" value="self" checked /> Text</label> ' +
						'<label><input type="radio" name="tsrp-kind" value="link" /> Link</label> ' +
						'<label><input type="radio" name="tsrp-kind" value="image" /> Image</label>' +
					'</div>' +
				'</div>' +
				'<div class="tsrp-row"><label>Title</label>' +
					'<input type="text" class="tsrp-title" maxlength="300" />' +
					'<div class="tsrp-title-counter description"></div>' +
				'</div>' +
				'<div class="tsrp-row tsrp-kind-self"><label>Body</label>' +
					'<textarea class="tsrp-text" rows="6"></textarea>' +
				'</div>' +
				'<div class="tsrp-row tsrp-kind-link" style="display:none;"><label>URL</label>' +
					'<input type="url" class="tsrp-url" />' +
				'</div>' +
				'<div class="tsrp-row tsrp-kind-image" style="display:none;"><label>Image URL</label>' +
					'<input type="url" class="tsrp-image-url" />' +
					'<div class="tsrp-preview"></div>' +
				'</div>' +
				'<div class="tsrp-row tsrp-flair-row" style="display:none;"><label>Flair</label>' +
					'<select class="tsrp-flair"><option value="">— none —</option></select>' +
				'</div>' +
				'<div class="tsrp-row">' +
					'<label><input type="checkbox" class="tsrp-nsfw" /> NSFW</label> ' +
					'<label><input type="checkbox" class="tsrp-spoiler" /> Spoiler</label>' +
				'</div>' +
				'<div class="tsrp-errors"></div>' +
			'</div>'
		);

		// Pre-fill.
		$body.find('.tsrp-title').val(post.title);
		$body.find('.tsrp-text').val(post.excerpt + '\n\n' + post.permalink);
		$body.find('.tsrp-url').val(post.permalink);
		$body.find('.tsrp-image-url').val(post.featured_url || '');
		if (post.featured_url) {
			$body.find('.tsrp-preview').html('<img src="' + esc(post.featured_url) + '" alt="" />');
		}
		updateTitleCounter($m);

		// Kind switching.
		$m.on('change', 'input[name=tsrp-kind]', function () {
			var k = $(this).val();
			$m.find('.tsrp-kind-self, .tsrp-kind-link, .tsrp-kind-image').hide();
			$m.find('.tsrp-kind-' + k).show();
			validate($m);
		});

		// Subreddit autocomplete + info.
		var searchTimer = null;
		$m.on('input', '.tsrp-subreddit', function () {
			clearTimeout(searchTimer);
			var q = $(this).val();
			searchTimer = setTimeout(function () { suggestSubs($m, q); }, 200);
		});
		$m.on('blur', '.tsrp-subreddit', function () {
			setTimeout(function () { $m.find('.tsrp-sub-suggestions').hide(); }, 150);
		});
		$m.on('focus', '.tsrp-subreddit', function () {
			$m.find('.tsrp-sub-suggestions').show();
		});
		$m.on('click', '.tsrp-sub-suggest', function () {
			var name = $(this).data('name');
			$m.find('.tsrp-subreddit').val(name);
			$m.find('.tsrp-sub-suggestions').hide();
			loadSubInfo($m, name);
		});
		$m.on('change', '.tsrp-subreddit', function () {
			loadSubInfo($m, $(this).val());
		});
		$m.on('input', '.tsrp-title', function () { updateTitleCounter($m); validate($m); });
		$m.on('input change', '.tsrp-url, .tsrp-image-url, .tsrp-text, .tsrp-flair', function () { validate($m); });

		$m.data('post', post);
		$m.find('.tsrp-submit').on('click', function () { doSubmit($m); });
	}

	function updateTitleCounter($m) {
		var max = $m.data('subInfo') ? $m.data('subInfo').title_max : 300;
		var len = ($m.find('.tsrp-title').val() || '').length;
		$m.find('.tsrp-title-counter').text(len + ' / ' + max);
	}

	function suggestSubs($m, q) {
		api('tsrp_search_subs', { q: q }).then(function (res) {
			if (!res.success) { return; }
			var $sug = $m.find('.tsrp-sub-suggestions').empty().show();
			res.data.slice(0, 20).forEach(function (s) {
				$sug.append(
					'<div class="tsrp-sub-suggest" data-name="' + esc(s.name) + '">' +
						'<strong>r/' + esc(s.name) + '</strong>' +
						(s.over18 ? ' <span class="tsrp-tag">NSFW</span>' : '') +
						' <span class="description">' + esc(s.title || '') + '</span>' +
					'</div>'
				);
			});
			if (!res.data.length) { $sug.append('<div class="description" style="padding:4px 8px;">No matches.</div>'); }
		});
	}

	function loadSubInfo($m, sub) {
		if (!sub) { return; }
		var $meta = $m.find('.tsrp-sub-meta').html('<em>' + esc(TSRP.i18n.loading) + '</em>');
		api('tsrp_get_sub_info', { subreddit: sub }).then(function (res) {
			if (!res.success) {
				$meta.html('<span class="tsrp-error">' + esc(res.data && res.data.message) + '</span>');
				return;
			}
			var info = res.data;
			$m.data('subInfo', info);

			// Enable / disable formats.
			$m.find('.tsrp-kinds input[value=self]').prop('disabled', info.link_type === 'link');
			$m.find('.tsrp-kinds input[value=link]').prop('disabled', info.link_type === 'self');
			$m.find('.tsrp-kinds input[value=image]').prop('disabled', !info.allow_images);
			// If current kind is disabled, switch to first enabled.
			var $checked = $m.find('.tsrp-kinds input:checked');
			if ($checked.is(':disabled')) {
				$m.find('.tsrp-kinds input:not(:disabled)').first().prop('checked', true).trigger('change');
			}

			// Flairs.
			var $flairRow = $m.find('.tsrp-flair-row');
			var $flair    = $m.find('.tsrp-flair').empty().append('<option value="">— none —</option>');
			if (info.flairs && info.flairs.length) {
				info.flairs.forEach(function (f) {
					$flair.append('<option value="' + esc(f.id) + '">' + esc(f.text) + '</option>');
				});
				$flairRow.show();
			} else {
				$flairRow.toggle(!!info.flair_required);
			}

			// Meta summary.
			var bits = [];
			bits.push('Title ' + info.title_min + '-' + info.title_max);
			bits.push('Type: ' + info.link_type);
			if (info.allow_images) bits.push('images ok');
			if (info.allow_videos) bits.push('videos ok');
			if (info.flair_required) bits.push('<strong>flair required</strong>');
			if (info.over18) bits.push('<strong>NSFW sub</strong>');
			$meta.html(bits.join(' · '));

			if (info.over18) { $m.find('.tsrp-nsfw').prop('checked', true); }

			updateTitleCounter($m);
			validate($m);
		});
	}

	function validate($m) {
		var info  = $m.data('subInfo');
		var kind  = $m.find('input[name=tsrp-kind]:checked').val();
		var title = $m.find('.tsrp-title').val() || '';
		var errs  = [];

		if (!$m.find('.tsrp-subreddit').val()) { errs.push('Pick a subreddit.'); }
		if (info) {
			if (title.length < info.title_min) { errs.push(TSRP.i18n.titleTooShort); }
			if (title.length > info.title_max) { errs.push(TSRP.i18n.titleTooLong); }
			if (info.flair_required && !$m.find('.tsrp-flair').val()) { errs.push(TSRP.i18n.flairReq); }
		}
		if (kind === 'link' && !$m.find('.tsrp-url').val()) { errs.push('Link URL required.'); }
		if (kind === 'image' && !$m.find('.tsrp-image-url').val()) { errs.push('Image URL required.'); }

		$m.find('.tsrp-errors').html(errs.length ? '<div class="notice notice-warning"><p>' + errs.map(esc).join('<br>') + '</p></div>' : '');
		$m.find('.tsrp-submit').prop('disabled', errs.length > 0);
	}

	function doSubmit($m) {
		var post = $m.data('post');
		var kind = $m.find('input[name=tsrp-kind]:checked').val();
		var data = {
			post_id:    post.id,
			subreddit:  $m.find('.tsrp-subreddit').val(),
			kind:       kind,
			title:      $m.find('.tsrp-title').val(),
			text:       $m.find('.tsrp-text').val(),
			url:        $m.find('.tsrp-url').val(),
			image_url:  $m.find('.tsrp-image-url').val(),
			flair_id:   $m.find('.tsrp-flair').val(),
			nsfw:       $m.find('.tsrp-nsfw').is(':checked') ? 1 : 0,
			spoiler:    $m.find('.tsrp-spoiler').is(':checked') ? 1 : 0
		};
		var $btn = $m.find('.tsrp-submit').prop('disabled', true).text(TSRP.i18n.submitting);
		$m.find('.tsrp-errors').empty();

		api('tsrp_submit', data).then(function (res) {
			if (!res.success) {
				var msg = (res.data && res.data.message) || 'Submit failed';
				$m.find('.tsrp-errors').html('<div class="notice notice-error"><p><strong>Reddit said:</strong> ' + esc(msg) + '</p></div>');
				$btn.prop('disabled', false).text(TSRP.i18n.submit);
				return;
			}
			var sub = res.data;
			$m.find('.tsrp-modal-body').html(
				'<div class="notice notice-success"><p>Posted: <a href="' + esc(sub.reddit_url) + '" target="_blank" rel="noopener">' + esc(sub.reddit_url) + '</a></p>' +
				'<p class="description">A follow-up comment will be posted in 3 minutes.</p></div>'
			);
			$m.find('.tsrp-submit').hide();
			$m.find('.tsrp-cancel').text('Close');
			// Update the column/metabox in-place.
			refreshPostRow(sub.post_id);
		}, function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || 'Network error';
			$m.find('.tsrp-errors').html('<div class="notice notice-error"><p>' + esc(msg) + '</p></div>');
			$btn.prop('disabled', false).text(TSRP.i18n.submit);
		});
	}

	/* ------------------------------------------------------------------
	   Column + analytics
	   ------------------------------------------------------------------ */

	function refreshPostRow(postId) {
		api('tsrp_get_submissions', { post_id: postId }).then(function (res) {
			if (!res.success) { return; }
			var $cols = $('.tsrp-col[data-post=' + postId + '], .tsrp-metabox[data-post=' + postId + '] .tsrp-submissions-list');
			$cols.each(function () {
				var $c      = $(this);
				var isList  = $c.hasClass('tsrp-submissions-list');
				$c.empty();
				if (!res.data.length) {
					if (!isList) {
						$c.append('<button type="button" class="button tsrp-post-btn" data-post="' + postId + '">' + esc(TSRP.i18n.post) + '</button>');
					} else {
						$c.append('<p class="description">Not posted to Reddit yet.</p>');
					}
					return;
				}
				res.data.forEach(function (s) { $c.append(renderRow(s)); });
				if (!isList) {
					$c.append('<button type="button" class="button button-small tsrp-post-btn" data-post="' + postId + '">+ ' + esc(TSRP.i18n.add) + '</button>');
				}
			});
			// Refresh analytics for this post.
			api('tsrp_refresh_stats', { 'post_ids[]': postId, force: 1 });
		});
	}

	function renderRow(s) {
		var stats = s.last_stats ? JSON.parse(s.last_stats) : null;
		var html =
			'<div class="tsrp-sub-row" data-sub-id="' + s.id + '" data-fullname="' + esc(s.reddit_fullname) + '">' +
				'<a class="tsrp-link" href="' + esc(s.reddit_url) + '" target="_blank" rel="noopener">r/' + esc(s.subreddit) + '</a>' +
				' <span class="tsrp-stats">' + esc(formatBadge(stats)) + '</span>';
		if (s.last_error) { html += ' <span class="tsrp-err" title="' + esc(s.last_error) + '">⚠</span>'; }
		if (s.comment_status) {
			var cls = s.comment_status === 'posted' ? 'ok' : (s.comment_status === 'failed' ? 'err' : 'pending');
			html += ' <span class="tsrp-cstat tsrp-' + cls + '">✎ ' + esc(s.comment_status) + '</span>';
		}
		html += '</div>';
		return html;
	}

	function refreshAllStatsOnList() {
		var ids = $('.tsrp-col[data-post]').map(function () { return $(this).data('post'); }).get();
		if (!ids.length) { return; }
		api('tsrp_refresh_stats', { post_ids: ids }).then(function (res) {
			if (!res.success) { return; }
			var byPost = res.data.by_post || {};
			Object.keys(byPost).forEach(function (pid) {
				var $col = $('.tsrp-col[data-post=' + pid + ']');
				if (!$col.length) { return; }
				$col.empty();
				byPost[pid].forEach(function (s) { $col.append(renderRow(s)); });
				$col.append('<button type="button" class="button button-small tsrp-post-btn" data-post="' + pid + '">+ ' + esc(TSRP.i18n.add) + '</button>');
			});
		});
	}

	/* ------------------------------------------------------------------
	   Bootstrap
	   ------------------------------------------------------------------ */

	$(document).on('click', '.tsrp-post-btn', function () {
		openModal(parseInt($(this).data('post'), 10));
	});
	$(document).on('keydown', function (e) { if (e.key === 'Escape') { closeModal(); } });
	$(function () { refreshAllStatsOnList(); });
})(jQuery);
