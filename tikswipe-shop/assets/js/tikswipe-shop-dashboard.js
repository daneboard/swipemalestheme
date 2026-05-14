/* global Chart, tssDash */
(function () {
	'use strict';
	if (typeof Chart === 'undefined' || !window.tssDash) { return; }

	var data = window.tssDash;

	function trunc(s, n) {
		s = String(s == null ? '' : s);
		return s.length > n ? s.slice(0, n - 1) + '…' : s;
	}

	// Engagement split donut: clicks vs closes vs ignored.
	var donut = document.getElementById('tss-donut');
	if (donut) {
		new Chart(donut, {
			type: 'doughnut',
			data: {
				labels: ['Clicks (wanted)', 'Manual closes', 'Ignored'],
				datasets: [{
					data: [
						data.totals.clicks || 0,
						data.totals.closes || 0,
						data.totals.ignored || 0,
					],
					backgroundColor: ['#16a55a', '#d63638', '#c3c4c7'],
					borderWidth: 0,
				}],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { position: 'bottom' },
					tooltip: {
						callbacks: {
							label: function (ctx) {
								var total = (data.totals.clicks || 0) + (data.totals.closes || 0) + (data.totals.ignored || 0);
								var pct   = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
								return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
							},
						},
					},
				},
				cutout: '60%',
			},
		});
	}

	function horizontalBar(canvasId, items, valueKey, color, label) {
		var el = document.getElementById(canvasId);
		if (!el) { return; }
		new Chart(el, {
			type: 'bar',
			data: {
				labels: items.map(function (i) { return trunc(i.title || i.name, 32); }),
				datasets: [{
					label: label,
					data: items.map(function (i) { return i[valueKey] || 0; }),
					backgroundColor: color,
					borderRadius: 4,
				}],
			},
			options: {
				indexAxis: 'y',
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: {
					x: { beginAtZero: true, ticks: { precision: 0 } },
				},
			},
		});
	}

	horizontalBar('tss-top-views',  data.topViews  || [], 'views',  '#2271b1', 'Views');
	horizontalBar('tss-top-clicks', data.topClicks || [], 'clicks', '#16a55a', 'Clicks');
	horizontalBar('tss-stores',     data.stores    || [], 'clicks', '#F63A61', 'Clicks');
})();
