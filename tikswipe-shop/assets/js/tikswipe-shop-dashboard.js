/* global Chart, tssDash */
(function () {
	'use strict';
	if (typeof Chart === 'undefined' || !window.tssDash) { return; }

	var data = window.tssDash;

	function trunc(s, n) {
		s = String(s == null ? '' : s);
		return s.length > n ? s.slice(0, n - 1) + '…' : s;
	}

	// Show/hide custom date inputs based on the preset.
	(function rangeUx() {
		var sel = document.querySelector('.tss-range-select');
		var customs = document.querySelectorAll('.tss-filter__custom');
		if (!sel || !customs.length) { return; }
		function sync() {
			var show = sel.value === 'custom';
			customs.forEach(function (n) { n.style.display = show ? '' : 'none'; });
		}
		sync();
		sel.addEventListener('change', sync);
	})();

	// Daily activity line chart.
	var ts = document.getElementById('tss-timeseries');
	if (ts && data.perDay) {
		var labels = Object.keys(data.perDay);
		var views  = labels.map(function (d) { return data.perDay[d].views; });
		var clicks = labels.map(function (d) { return data.perDay[d].clicks; });
		var closes = labels.map(function (d) { return data.perDay[d].closes; });
		new Chart(ts, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{ label: 'Views',  data: views,  borderColor: '#2271b1', backgroundColor: 'rgba(34,113,177,.12)', tension: .25, fill: true },
					{ label: 'Clicks', data: clicks, borderColor: '#16a55a', backgroundColor: 'rgba(22,165,90,.12)',  tension: .25, fill: true },
					{ label: 'Closes', data: closes, borderColor: '#d63638', backgroundColor: 'rgba(214,54,56,.10)',  tension: .25, fill: true },
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: { legend: { position: 'bottom' } },
				scales: {
					y: { beginAtZero: true, ticks: { precision: 0 } },
					x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
				},
			},
		});
	}

	// Engagement split donut.
	var donut = document.getElementById('tss-donut');
	if (donut) {
		var c = data.current || { clicks: 0, closes: 0, ignored: 0 };
		new Chart(donut, {
			type: 'doughnut',
			data: {
				labels: ['Clicks', 'Closes', 'Ignored'],
				datasets: [{
					data: [c.clicks || 0, c.closes || 0, c.ignored || 0],
					backgroundColor: ['#16a55a', '#d63638', '#c3c4c7'],
					borderWidth: 0,
				}],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutout: '60%',
				plugins: {
					legend: { position: 'bottom' },
					tooltip: {
						callbacks: {
							label: function (ctx) {
								var total = (c.clicks || 0) + (c.closes || 0) + (c.ignored || 0);
								var pct   = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
								return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
							},
						},
					},
				},
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
				scales: { x: { beginAtZero: true, ticks: { precision: 0 } } },
			},
		});
	}

	horizontalBar('tss-top-views',  data.topViews  || [], 'views',  '#2271b1', 'Views');
	horizontalBar('tss-top-clicks', data.topClicks || [], 'clicks', '#16a55a', 'Clicks');
	horizontalBar('tss-stores',     data.stores    || [], 'clicks', '#F63A61', 'Clicks');
})();
