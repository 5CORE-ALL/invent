{{--
    Standard rolling-history dots + history graph for analytics header badges.

    Usage (once per page, near the badges):
      @include('partials.channel-badge-history', [
          'bhLabel' => 'Wayfair',
          'bhChartUrl' => url('/wayfair/badge-chart-data'),
          'bhPrevUrl' => url('/wayfair/badge-prev-day'),
          'bhSnapshotUrl' => url('/wayfair/badge-snapshot'),   // optional (browser-computed metrics)
      ])
      ChBadgeHistory.register([{ sel: '#id', metric: 'total_sales', format: 'money', invert: false, label: 'Sales', post: false }]);
      ChBadgeHistory.setLive('total_sales', 1234);   // paints the dot (green up / red down / grey same)
      ChBadgeHistory.setText('#id', 'Sales: $1,234'); // sets badge text and keeps the dot
    The dot opens the graph; badges without their own click action open it on badge click too.
--}}
@once
@include('partials.lazy-chart-js')
<style>
    .ch-bh-badge { display: inline-flex !important; align-items: center; }
    #chBadgeHistModal.modal {
        --tz-modal-width: 100%;
        --tz-modal-margin: 0.5rem 0;
        padding-left: 0 !important;
        padding-right: 0 !important;
        z-index: 10800;
    }
    #chBadgeHistModal .modal-dialog { width: 100% !important; max-width: none !important; margin: 0.5rem 0 0 0 !important; }
    #chBadgeHistModal .modal-content { border-radius: 0; width: 100%; max-width: 100%; }
</style>
<div class="modal fade p-0" id="chBadgeHistModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog shadow-none m-0 mx-0">
        <div class="modal-content" style="overflow:hidden;">
            <div class="modal-header py-1 px-3" style="background:#20c997;color:#fff;">
                <h6 class="modal-title mb-0" style="font-size:13px;">
                    <i class="fas fa-chart-area me-1"></i>
                    <span id="chBadgeHistTitle">{{ $bhLabel ?? 'Channel' }} — Rolling History</span>
                </h6>
                <div class="d-flex align-items-center gap-2">
                    <select id="chBadgeHistRange" class="form-select form-select-sm bg-white" style="width:110px;height:26px;font-size:11px;padding:1px 8px;">
                        <option value="7">7 Days</option>
                        <option value="30" selected>30 Days</option>
                        <option value="60">60 Days</option>
                        <option value="90">90 Days</option>
                        <option value="0">Lifetime</option>
                    </select>
                    <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-2">
                <div id="chBadgeHistWrap" style="height:38vh;display:none;align-items:stretch;">
                    <div style="flex:1;min-width:0;position:relative;"><canvas id="chBadgeHistCanvas"></canvas></div>
                    <div style="width:100px;display:flex;flex-direction:column;justify-content:center;gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;">
                        <div style="text-align:center;">
                            <div style="font-size:8px;font-weight:700;text-transform:uppercase;color:#dc3545;">Highest</div>
                            <div id="chBadgeHistHighest" style="font-size:13px;font-weight:700;color:#dc3545;">-</div>
                        </div>
                        <div style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                            <div style="font-size:8px;font-weight:700;text-transform:uppercase;color:#6c757d;">Median</div>
                            <div id="chBadgeHistMedian" style="font-size:13px;font-weight:700;color:#6c757d;">-</div>
                        </div>
                        <div style="text-align:center;">
                            <div style="font-size:8px;font-weight:700;text-transform:uppercase;color:#198754;">Lowest</div>
                            <div id="chBadgeHistLowest" style="font-size:13px;font-weight:700;color:#198754;">-</div>
                        </div>
                    </div>
                </div>
                <div id="chBadgeHistLoading" class="text-center py-3" style="display:none;">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <p class="mt-1 text-muted small mb-0">Loading history…</p>
                </div>
                <div id="chBadgeHistNoData" class="text-center py-3" style="display:none;">
                    <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                    <p class="text-muted small mb-0">No daily snapshots yet. Keep this page open — today’s value is saved automatically and history builds day by day.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function (w) {
    if (w.ChBadgeHistory) return;

    var cfg = { label: 'Channel', chartUrl: '', prevUrl: '', snapshotUrl: '' };
    var metrics = {};          // metric -> { sel, format, invert, label, post, live }
    var prev = null;           // metric -> previous-day value
    var prevLoaded = false;
    var prevLoading = false;
    var chartInst = null;
    var chartAjax = null;
    var chartDays = 30;
    var curMetric = null;
    var postTimer = null;
    var lastPostSig = '';

    function $$() { return w.jQuery; }

    function ymd(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function todayPt() {
        try { return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date()); }
        catch (e) { return ymd(new Date()); }
    }
    function labelFor(day) {
        var d = new Date(day + 'T12:00:00');
        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', timeZone: 'UTC' });
    }
    function fmt(v, metric) {
        var n = Number(v);
        if (!isFinite(n)) return '—';
        var m = metrics[metric] || {};
        if (m.format === 'money') return '$' + Math.round(n).toLocaleString('en-US');
        if (m.format === 'pct') return n.toFixed(1) + '%';
        return Math.round(n).toLocaleString('en-US');
    }
    function trendClass(curr, previous, invert) {
        if (!isFinite(curr) || previous == null || !isFinite(previous)) return 'none';
        var diff = curr - previous;
        var cls = 'flat';
        if (diff > 0.05) cls = 'up';
        else if (diff < -0.05) cls = 'down';
        if (invert && cls === 'up') cls = 'down';
        else if (invert && cls === 'down') cls = 'up';
        return cls;
    }
    function badgeEl(m) {
        return m && m.sel ? document.querySelector(m.sel) : null;
    }
    function ensureDot(el, metric) {
        if (!el) return null;
        var dot = el.querySelector('.summary-trend-dot');
        if (!dot) {
            dot = document.createElement('span');
            dot.className = 'summary-trend-dot none';
            dot.title = 'Rolling history';
            el.insertBefore(dot, el.firstChild);
        }
        dot.setAttribute('data-metric', metric);
        el.setAttribute('data-ch-bh', metric);
        if (!el.classList.contains('ch-bh-badge')) el.classList.add('ch-bh-badge');
        return dot;
    }

    function paintDot(metric) {
        var m = metrics[metric];
        var el = badgeEl(m);
        if (!el) return;
        var dot = ensureDot(el, metric);
        var live = m.live;
        var p = prev ? prev[metric] : null;
        if (!isFinite(live) || p == null || !isFinite(p)) {
            dot.className = 'summary-trend-dot none';
            dot.title = 'Click for rolling history (no prior day yet)';
            return;
        }
        var cls = trendClass(live, p, m.invert);
        var word = cls === 'up' ? 'Up' : (cls === 'down' ? 'Down' : 'Same');
        dot.className = 'summary-trend-dot ' + cls;
        dot.title = word + ' vs prior day (' + fmt(p, metric) + ' → ' + fmt(live, metric) + '). Click for rolling history.';
    }

    function setText(sel, text) {
        var el = typeof sel === 'string' ? document.querySelector(sel) : sel;
        if (!el) return;
        var dot = el.querySelector('.summary-trend-dot');
        if (dot) dot.parentNode.removeChild(dot);
        el.textContent = text;
        if (dot) el.insertBefore(dot, el.firstChild);
    }

    function scheduleSnapshotPost() {
        if (!cfg.snapshotUrl || !w.fetch) return;
        if (postTimer) clearTimeout(postTimer);
        postTimer = setTimeout(function () {
            postTimer = null;
            var body = {};
            Object.keys(metrics).forEach(function (k) {
                if (metrics[k].post && isFinite(metrics[k].live)) body[k] = Math.round(metrics[k].live);
            });
            if (!Object.keys(body).length) return;
            var sig = todayPt() + '|' + JSON.stringify(body);
            if (sig === lastPostSig) return;
            lastPostSig = sig;
            try {
                fetch(cfg.snapshotUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(body),
                    credentials: 'same-origin'
                }).then(function (r) { if (!r.ok) lastPostSig = ''; }).catch(function () { lastPostSig = ''; });
            } catch (e) { lastPostSig = ''; }
        }, 3000);
    }

    function setLive(metric, value) {
        var m = metrics[metric];
        if (!m) return;
        var n = Number(value);
        m.live = isFinite(n) ? n : NaN;
        var el = badgeEl(m);
        if (el) el.setAttribute('data-live-value', isFinite(n) ? String(n) : '');
        paintDot(metric);
        if (m.post) scheduleSnapshotPost();
    }

    function loadPrev() {
        if (prevLoaded || prevLoading || !cfg.prevUrl || !$$()) return;
        prevLoading = true;
        $$().ajax({
            url: cfg.prevUrl,
            method: 'GET',
            success: function (resp) {
                prevLoaded = true; prevLoading = false;
                prev = (resp && resp.success && resp.metrics) ? resp.metrics : null;
                Object.keys(metrics).forEach(paintDot);
            },
            error: function () {
                prevLoaded = true; prevLoading = false;
                Object.keys(metrics).forEach(paintDot);
            }
        });
    }

    function register(list) {
        (list || []).forEach(function (m) {
            if (!m || !m.sel || !m.metric) return;
            metrics[m.metric] = {
                sel: m.sel,
                format: m.format || 'number',
                invert: !!m.invert,
                label: m.label || m.metric,
                post: !!m.post,
                live: NaN,
                clickBadge: m.clickBadge !== false
            };
            ensureDot(document.querySelector(m.sel), m.metric);
        });
        loadPrev();
    }

    function overlayLive(rows, metric) {
        var list = Array.isArray(rows) ? rows.slice() : [];
        var m = metrics[metric] || {};
        if (!isFinite(m.live)) return list;
        var today = todayPt();
        var last = list.length ? list[list.length - 1] : null;
        if (last && (last.full_date === today || last.date === labelFor(today))) {
            last.value = m.live;
        } else {
            list.push({ date: labelFor(today), full_date: today, value: m.live });
        }
        return list;
    }

    function renderChart(data, metric) {
        var run = function () {
            var canvas = document.getElementById('chBadgeHistCanvas');
            if (!canvas || typeof Chart === 'undefined') return;
            if (chartInst) chartInst.destroy();
            var m = metrics[metric] || {};
            var seen = {};
            data = (data || []).filter(function (d) {
                var k = d.full_date || d.date || '';
                if (!k || seen[k]) return false;
                seen[k] = true;
                return isFinite(Number(d.value));
            });
            var labels = data.map(function (d) { return d.date; });
            var values = data.map(function (d) { return Number(d.value); });
            if (!values.length) return;
            var dataMin = Math.min.apply(null, values);
            var dataMax = Math.max.apply(null, values);
            var sorted = values.slice().sort(function (a, b) { return a - b; });
            var mid = Math.floor(sorted.length / 2);
            var median = sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
            document.getElementById('chBadgeHistHighest').textContent = fmt(dataMax, metric);
            document.getElementById('chBadgeHistMedian').textContent = fmt(median, metric);
            document.getElementById('chBadgeHistLowest').textContent = fmt(dataMin, metric);
            var range = dataMax - dataMin;
            var yMin, yMax;
            if (range < 1e-9) {
                var pad = Math.max(Math.abs(dataMax) * 0.2, 0.5);
                yMin = Math.max(0, dataMin - pad);
                yMax = dataMax + pad;
            } else {
                yMin = Math.max(0, dataMin - range * 0.12);
                yMax = dataMax + Math.max(range * 0.28, Math.abs(dataMax) * 0.08, range * 0.1);
            }
            // Point colours follow the badge dot rule: up green, down red, same grey (inverted for lower-is-better).
            var colors = values.map(function (v, i) {
                if (i === 0) return '#9ca3af';
                var cls = trendClass(v, values[i - 1], m.invert);
                return cls === 'up' ? '#22c55e' : (cls === 'down' ? '#ef4444' : '#9ca3af');
            });
            var medianLine = {
                id: 'chBhMedian',
                afterDraw: function (chart) {
                    var y = chart.scales.y.getPixelForValue(median);
                    var x = chart.scales.x;
                    var c = chart.ctx;
                    c.save();
                    c.setLineDash([6, 4]);
                    c.strokeStyle = '#6c757d';
                    c.lineWidth = 1.2;
                    c.beginPath(); c.moveTo(x.left, y); c.lineTo(x.right, y); c.stroke();
                    c.restore();
                }
            };
            var valueLabels = {
                id: 'chBhLabels',
                afterDraw: function (chart) {
                    var ds = chart.data.datasets[0];
                    var meta = chart.getDatasetMeta(0);
                    var c = chart.ctx;
                    if (!ds || !meta || !meta.data) return;
                    meta.data.forEach(function (pt, i) {
                        if (!pt || pt.skip) return;
                        var offsetY = (i % 2 === 0) ? -14 : -32;
                        c.save();
                        c.font = 'bold 13px Inter, system-ui, sans-serif';
                        c.textAlign = 'left';
                        c.textBaseline = 'middle';
                        c.lineJoin = 'round';
                        c.lineWidth = 3;
                        c.strokeStyle = 'rgba(255,255,255,0.95)';
                        c.fillStyle = colors[i];
                        c.translate(pt.x + 3, pt.y + offsetY);
                        c.rotate(-35 * Math.PI / 180);
                        var txt = fmt(ds.data[i], metric);
                        c.strokeText(txt, 0, 0);
                        c.fillText(txt, 0, 0);
                        c.restore();
                    });
                }
            };
            chartInst = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: m.label || metric,
                        data: values,
                        borderColor: '#20c997',
                        backgroundColor: 'rgba(32,201,151,0.08)',
                        borderWidth: 1.5,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: colors,
                        pointBorderColor: colors,
                        pointBorderWidth: 1.5
                    }]
                },
                plugins: [medianLine, valueLabels],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    clip: false,
                    layout: { padding: { top: 44, left: 8, right: 22, bottom: 28 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: { label: function (ctx) { return ' ' + (m.label || metric) + ': ' + fmt(ctx.parsed.y, metric); } }
                        }
                    },
                    scales: {
                        x: { ticks: { maxRotation: 90, minRotation: 90, autoSkip: false, font: { size: labels.length > 45 ? 9 : 10, weight: '600' } } },
                        y: { min: yMin, max: yMax, ticks: { callback: function (v) { return fmt(v, metric); } } }
                    }
                }
            });
        };
        if (typeof Chart === 'undefined' && typeof w.loadChartJs === 'function') {
            w.loadChartJs().then(run);
        } else {
            run();
        }
    }

    function showSeries(rows, metric) {
        var $ = $$();
        $('#chBadgeHistLoading').hide();
        var series = overlayLive(rows, metric);
        if (!series.length) {
            $('#chBadgeHistWrap').hide();
            $('#chBadgeHistNoData').show();
            return;
        }
        $('#chBadgeHistNoData').hide();
        $('#chBadgeHistWrap').css({ display: 'flex', alignItems: 'stretch' }).show();
        renderChart(series, metric);
    }

    function loadChart() {
        var $ = $$();
        if (!$ || !curMetric) return;
        var metric = curMetric;
        $('#chBadgeHistWrap').hide();
        $('#chBadgeHistNoData').hide();
        $('#chBadgeHistLoading').show();
        if (chartAjax) chartAjax.abort();
        chartAjax = $.ajax({
            url: cfg.chartUrl,
            method: 'GET',
            data: { metric: metric, days: chartDays },
            success: function (resp) {
                chartAjax = null;
                var rows = (resp && resp.success && Array.isArray(resp.data)) ? resp.data : [];
                showSeries(rows, metric);
            },
            error: function (xhr, status) {
                chartAjax = null;
                if (status === 'abort') return;
                showSeries([], metric);
            }
        });
    }

    function open(metric) {
        var $ = $$();
        if (!$ || !metrics[metric] || !cfg.chartUrl) return;
        curMetric = metric;
        chartDays = 30;
        $('#chBadgeHistRange').val('30');
        $('#chBadgeHistTitle').text(cfg.label + ' — ' + metrics[metric].label + ' Rolling History');
        var el = document.getElementById('chBadgeHistModal');
        if (el.parentElement !== document.body) document.body.appendChild(el);
        if (w.bootstrap && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(el).show();
        else $(el).modal('show');
        loadChart();
    }

    // Capture phase: the dot (and non-filter badges) open the graph before any badge filter handler runs.
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        var badge = t.closest('[data-ch-bh]');
        if (!badge) return;
        var metric = badge.getAttribute('data-ch-bh');
        var m = metrics[metric];
        if (!m) return;
        var onDot = !!t.closest('.summary-trend-dot');
        if (!onDot && !m.clickBadge) return;
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        open(metric);
    }, true);

    document.addEventListener('change', function (e) {
        if (!e.target || e.target.id !== 'chBadgeHistRange') return;
        chartDays = parseInt(e.target.value, 10) || 0;
        loadChart();
    });

    w.ChBadgeHistory = {
        init: function (opts) {
            cfg = Object.assign(cfg, opts || {});
            loadPrev();
        },
        register: register,
        setLive: setLive,
        setText: setText,
        open: open
    };
})(window);
</script>
@endonce
