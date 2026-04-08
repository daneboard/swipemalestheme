/**
 * TikSwipe Video Importer — Admin JS.
 *
 * Handles the scrape → review → AI enrich → import flow via AJAX.
 */
(function ($) {
	'use strict';

	var results = []; // Scraped video data.

	/* ==========================================================
	   Scrape button
	   ========================================================== */

	$('#tsvi-btn-scrape').on('click', function () {
		var url   = $('#tsvi-url').val().trim();
		var mode  = $('input[name="tsvi-mode"]:checked').val();
		var limit = parseInt($('#tsvi-limit').val(), 10) || 10;
		var delay = parseInt($('#tsvi-delay').val(), 10) || 2;

		if (!url) { alert('Enter a URL.'); return; }

		results = [];
		$('#tsvi-results').addClass('tsvi-hidden');
		$('#tsvi-progress').removeClass('tsvi-hidden');
		setProgress('tsvi-progress-fill', 0);
		$('#tsvi-progress-text').text('Fetching source page...');

		if (mode === 'direct') {
			// Single video page.
			extractOne(url, function (video) {
				if (video) results.push(video);
				scrapeDone();
			});
		} else {
			// Listing page: discover links first, then extract each.
			$.post(tsvi.ajax_url, {
				action: 'tsvi_discover',
				nonce:  tsvi.nonce,
				url:    url,
				limit:  limit
			}, function (resp) {
				if (!resp.success) {
					$('#tsvi-progress-text').text('Error: ' + resp.data);
					return;
				}

				var links = resp.data;
				if (!links.length) {
					$('#tsvi-progress-text').text('No video links found on this page.');
					return;
				}

				$('#tsvi-progress-text').text('Found ' + links.length + ' links. Extracting videos...');

				// Process links sequentially with delay.
				var idx = 0;
				function next() {
					if (idx >= links.length || idx >= limit) {
						scrapeDone();
						return;
					}

					var link = links[idx];
					var pct  = Math.round(((idx + 1) / Math.min(links.length, limit)) * 100);
					setProgress('tsvi-progress-fill', pct);
					$('#tsvi-progress-text').text('Extracting ' + (idx + 1) + '/' + Math.min(links.length, limit) + ': ' + link.title.substring(0, 60));

					extractOne(link.url, function (video) {
						if (video) {
							// Use listing thumbnail as fallback.
							if (!video.thumbnail && link.thumbnail) {
								video.thumbnail = link.thumbnail;
							}
							if (!video.title && link.title) {
								video.title = link.title;
							}
							results.push(video);
						}
						idx++;
						setTimeout(next, delay * 1000);
					});
				}
				next();
			});
		}
	});

	/* ==========================================================
	   Extract a single video page
	   ========================================================== */

	function extractOne(url, callback) {
		$.post(tsvi.ajax_url, {
			action: 'tsvi_extract',
			nonce:  tsvi.nonce,
			url:    url
		}, function (resp) {
			callback(resp.success ? resp.data : null);
		}).fail(function () {
			callback(null);
		});
	}

	/* ==========================================================
	   After scraping is done — render results table
	   ========================================================== */

	function scrapeDone() {
		$('#tsvi-progress').addClass('tsvi-hidden');

		if (!results.length) {
			alert('No videos could be extracted.');
			return;
		}

		renderTable();
		$('#tsvi-results').removeClass('tsvi-hidden');
	}

	function renderTable() {
		var $tbody = $('#tsvi-results-table tbody');
		$tbody.empty();

		$.each(results, function (i, v) {
			var thumb = v.thumbnail
				? '<img src="' + escHtml(v.thumbnail) + '" style="width:80px;height:auto;">'
				: '<span class="dashicons dashicons-format-video"></span>';

			var videoInfo = v.video_url
				? '<span class="tsvi-ok">MP4</span>'
				: (v.embed ? '<span class="tsvi-warn">Embed only</span>' : '<span class="tsvi-err">None</span>');

			var tags = (v.source_tags || []).join(', ');

			$tbody.append(
				'<tr data-idx="' + i + '">' +
					'<td class="check-column"><input type="checkbox" class="tsvi-check" data-idx="' + i + '"></td>' +
					'<td>' + thumb + '</td>' +
					'<td><input type="text" class="tsvi-title large-text" data-idx="' + i + '" value="' + escAttr(v.title) + '"></td>' +
					'<td>' + formatDuration(v.duration) + '</td>' +
					'<td>' + videoInfo + '</td>' +
					'<td class="tsvi-tags-cell" data-idx="' + i + '">' + escHtml(tags) + '</td>' +
					'<td class="tsvi-status-cell" data-idx="' + i + '">—</td>' +
				'</tr>'
			);
		});
	}

	/* ==========================================================
	   Select all checkboxes
	   ========================================================== */

	$(document).on('change', '#tsvi-select-all, #tsvi-select-all-head', function () {
		$('.tsvi-check').prop('checked', $(this).prop('checked'));
	});

	/* ==========================================================
	   Title inline edit — sync back to results array
	   ========================================================== */

	$(document).on('change', '.tsvi-title', function () {
		var idx = $(this).data('idx');
		results[idx].title = $(this).val();
	});

	/* ==========================================================
	   AI Enrich (Grok) — process selected videos
	   ========================================================== */

	$('#tsvi-btn-ai').on('click', function () {
		var selected = getSelected();
		if (!selected.length) { alert('Select at least one video.'); return; }

		var btn = $(this);
		btn.prop('disabled', true).text('Processing AI...');

		var queue = selected.slice();
		var total = queue.length;

		function processNext() {
			if (!queue.length) {
				btn.prop('disabled', false).text('Generate AI Tags (Grok)');
				return;
			}

			var idx = queue.shift();
			var v   = results[idx];
			var $status = $('.tsvi-status-cell[data-idx="' + idx + '"]');
			$status.html('<span class="tsvi-loading">AI...</span>');

			$.post(tsvi.ajax_url, {
				action:      'tsvi_enrich',
				nonce:       tsvi.nonce,
				title:       v.title,
				source_url:  v.source_url,
				source_tags: v.source_tags || []
			}, function (resp) {
				if (resp.success) {
					var ai = resp.data;
					// Update results array.
					results[idx].title       = ai.title || v.title;
					results[idx].description = ai.description || '';
					results[idx].tags        = ai.tags || [];
					results[idx].category    = ai.category || '';

					// Update UI.
					$('.tsvi-title[data-idx="' + idx + '"]').val(results[idx].title);
					$('.tsvi-tags-cell[data-idx="' + idx + '"]').text((ai.tags || []).join(', '));
					$status.html('<span class="tsvi-ok">AI done</span>');
				} else {
					$status.html('<span class="tsvi-err">AI error</span>');
				}
				processNext();
			}).fail(function () {
				$status.html('<span class="tsvi-err">AI failed</span>');
				processNext();
			});
		}

		processNext();
	});

	/* ==========================================================
	   Import selected videos
	   ========================================================== */

	$('#tsvi-btn-import').on('click', function () {
		var selected = getSelected();
		if (!selected.length) { alert('Select at least one video.'); return; }

		var overrideCat = $('#tsvi-import-cat').val();
		var btn = $(this);
		btn.prop('disabled', true);

		$('#tsvi-import-progress').removeClass('tsvi-hidden');
		setProgress('tsvi-import-fill', 0);

		var queue = selected.slice();
		var total = queue.length;
		var done  = 0;
		var imported = 0;

		function importNext() {
			if (!queue.length) {
				$('#tsvi-import-text').text('Done! ' + imported + '/' + total + ' videos imported.');
				btn.prop('disabled', false);
				return;
			}

			var idx = queue.shift();
			var v   = results[idx];
			done++;

			var pct = Math.round((done / total) * 100);
			setProgress('tsvi-import-fill', pct);
			$('#tsvi-import-text').text('Importing ' + done + '/' + total + ': ' + v.title.substring(0, 50));

			var $status = $('.tsvi-status-cell[data-idx="' + idx + '"]');
			$status.html('<span class="tsvi-loading">Importing...</span>');

			$.post(tsvi.ajax_url, {
				action:            'tsvi_import',
				nonce:             tsvi.nonce,
				title:             v.title,
				description:       v.description || '',
				video_url:         v.video_url || '',
				embed:             v.embed || '',
				thumbnail:         v.thumbnail || '',
				duration:          v.duration || 0,
				width:             v.width || 0,
				height:            v.height || 0,
				tags:              v.tags || v.source_tags || [],
				category:          v.category || '',
				override_category: overrideCat || ''
			}, function (resp) {
				if (resp.success) {
					imported++;
					$status.html('<span class="tsvi-ok">Imported #' + resp.data.post_id + '</span>');
					// Uncheck imported item.
					$('.tsvi-check[data-idx="' + idx + '"]').prop('checked', false);
				} else {
					$status.html('<span class="tsvi-err">' + escHtml(resp.data) + '</span>');
				}

				// Small delay before next to avoid hammering the server.
				setTimeout(importNext, 500);
			}).fail(function () {
				$status.html('<span class="tsvi-err">Request failed</span>');
				setTimeout(importNext, 500);
			});
		}

		importNext();
	});

	/* ==========================================================
	   Helpers
	   ========================================================== */

	function getSelected() {
		var sel = [];
		$('.tsvi-check:checked').each(function () {
			sel.push($(this).data('idx'));
		});
		return sel;
	}

	function setProgress(id, pct) {
		$('#' + id).css('width', pct + '%');
	}

	function formatDuration(sec) {
		if (!sec) return '—';
		var m = Math.floor(sec / 60);
		var s = sec % 60;
		return m + ':' + (s < 10 ? '0' : '') + s;
	}

	function escHtml(str) {
		if (!str) return '';
		return $('<span>').text(str).html();
	}

	function escAttr(str) {
		if (!str) return '';
		return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

})(jQuery);
