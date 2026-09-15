<style>
    .analytics-dil-badge { display: inline-flex !important; align-items: center; gap: 4px; }
    .analytics-dil-badge .summary-trend-dot {
        display: inline-block;
        width: 6px !important;
        height: 6px !important;
        margin: 0 2px 0 0 !important;
        border-radius: 50%;
        flex-shrink: 0;
        cursor: pointer;
        box-shadow: 0 0 0 1px rgba(255,255,255,0.85);
        vertical-align: 0.08em;
        background: #9ca3af;
    }
    .analytics-dil-badge .summary-trend-dot:hover { transform: scale(1.35); }
    .analytics-dil-badge .summary-trend-dot.up { background: #22c55e; }
    .analytics-dil-badge .summary-trend-dot.down { background: #ef4444; }
    .analytics-dil-badge .summary-trend-dot.flat,
    .analytics-dil-badge .summary-trend-dot.none { background: #9ca3af; }
    #analyticsDilChartModal.modal {
        --tz-modal-width: 100%;
        --tz-modal-margin: 0.5rem 0;
        padding-left: 0 !important;
        padding-right: 0 !important;
        z-index: 10800;
    }
    #analyticsDilChartModal .modal-dialog {
        width: 100% !important;
        max-width: none !important;
        margin: 0.5rem 0 0 0 !important;
    }
    #analyticsDilChartModal .modal-content { border-radius: 0; width: 100%; max-width: 100%; }
</style>
<div class="modal fade p-0" id="analyticsDilChartModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog shadow-none m-0 mx-0">
        <div class="modal-content" style="overflow: hidden;">
            <div class="modal-header py-1 px-3" style="background:#fd7e14;color:#fff;">
                <h6 class="modal-title mb-0" style="font-size: 13px;">
                    <i class="fas fa-chart-area me-1"></i>
                    <span id="analyticsDilChartTitle">Dil% — daily trend</span>
                </h6>
                <div class="d-flex align-items-center gap-2">
                    <select id="analyticsDilChartRange" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                        <option value="7">7 Days</option>
                        <option value="30" selected>30 Days</option>
                        <option value="60">60 Days</option>
                        <option value="90">90 Days</option>
                        <option value="0">Lifetime</option>
                    </select>
                    <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-2">
                <div id="analyticsDilChartWrap" style="height: 38vh; display: none; align-items: stretch;">
                    <div style="flex: 1; min-width: 0; position: relative;">
                        <canvas id="analyticsDilChartCanvas"></canvas>
                    </div>
                    <div style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa;">
                        <div style="text-align: center;">
                            <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; color: #dc3545;">Highest</div>
                            <div id="analyticsDilChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                        </div>
                        <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                            <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; color: #6c757d;">Median</div>
                            <div id="analyticsDilChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; color: #198754;">Lowest</div>
                            <div id="analyticsDilChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                        </div>
                    </div>
                </div>
                <div id="analyticsDilChartLoading" class="text-center py-3" style="display: none;">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <p class="mt-1 text-muted small mb-0">Loading Dil% history…</p>
                </div>
                <div id="analyticsDilChartNoData" class="text-center py-3" style="display: none;">
                    <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                    <p class="text-muted small mb-0">No daily Dil% snapshots yet. Keep this page open — today’s value is saved automatically.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    if (window.AnalyticsDilBadge) return;

    const INV_FIELDS = ['INV', 'inventory', 'inv', 'Inv', 'Shopify INV'];
    const L30_FIELDS = ['OV L30', 'ov_l30', 'OV_L30', 'ovl30', 'L30', 'quantity'];
    let prevDil = null;
    let prevLoaded = false;
    let lastPosted = '';
    let chartInst = null;
    let chartAjax = null;
    let chartDays = 30;
    let inited = false;

    function channel() {
        return String($('#analytics-dil-badge').data('dil-channel') || '').trim();
    }
    function csrf() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }
    function todayPt() {
        try { return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Los_Angeles' }).format(new Date()); }
        catch (e) {
            const d = new Date();
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }
    }
    function ymd(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function lastCompletedPt() {
        const d = new Date(todayPt() + 'T12:00:00');
        d.setDate(d.getDate() - 1);
        return ymd(d);
    }
    function labelFor(ymdStr) {
        const d = new Date((ymdStr || lastCompletedPt()) + 'T12:00:00');
        return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', timeZone: 'UTC' });
    }
    function isParent(row) {
        if (!row) return true;
        if (row.is_parent_row || row.is_parent_summary || row.is_parent) return true;
        if (typeof window.isEbay2TabulatorParentRow === 'function' && isEbay2TabulatorParentRow(row)) return true;
        const sku = String(row['(Child) sku'] || row.sku || row.SKU || '').toUpperCase();
        return sku.indexOf('PARENT') !== -1;
    }
    function num(row, fields) {
        for (let i = 0; i < fields.length; i++) {
            if (row[fields[i]] != null && row[fields[i]] !== '') {
                const n = parseFloat(row[fields[i]]);
                if (isFinite(n)) return n;
            }
        }
        return 0;
    }
    function defaultRows() {
        if (window.AnalyticsDilBadge && typeof window.AnalyticsDilBadge._getRows === 'function') {
            const rows = window.AnalyticsDilBadge._getRows();
            if (Array.isArray(rows)) return rows;
        }
        if (window.allTableData && window.allTableData.length) return window.allTableData;
        if (window.table && typeof window.table.getData === 'function') {
            try { return window.table.getData() || []; } catch (e) { return []; }
        }
        const el = document.querySelector('.tabulator');
        if (el && el.tabulator && typeof el.tabulator.getData === 'function') {
            try { return el.tabulator.getData() || []; } catch (e) { return []; }
        }
        return [];
    }
    function totalsFromRows(rows) {
        let ov = 0, inv = 0;
        (rows || []).forEach(function(row) {
            if (isParent(row)) return;
            ov += num(row, L30_FIELDS);
            inv += num(row, INV_FIELDS);
        });
        return { ovL30: ov, inv: inv, dil: inv > 0 ? (ov / inv) * 100 : 0 };
    }
    function paintDot(curr) {
        const $dot = $('#analytics-dil-badge .summary-trend-dot');
        if (!$dot.length) return;
        if (!isFinite(curr) || prevDil == null || !isFinite(prevDil)) {
            $dot.attr('class', 'summary-trend-dot none').attr('title', 'Click for daily Dil% history');
            return;
        }
        let cls = 'flat';
        const diff = curr - prevDil;
        if (diff > 0.05) cls = 'up';
        else if (diff < -0.05) cls = 'down';
        $dot.attr('class', 'summary-trend-dot ' + cls)
            .attr('title', (cls === 'up' ? 'Up' : (cls === 'down' ? 'Down' : 'Same'))
                + ' vs prior day (' + prevDil.toFixed(1) + '% → ' + curr.toFixed(1) + '%). Click for history.');
    }
    function set(dil, ovL30, inv) {
        const $b = $('#analytics-dil-badge');
        if (!$b.length) return;
        const pct = isFinite(dil) ? dil : 0;
        const ov = isFinite(ovL30) ? ovL30 : 0;
        const stock = isFinite(inv) ? inv : 0;
        $b.attr('data-live-value', pct.toFixed(2));
        const $dot = $b.find('.summary-trend-dot').detach();
        $b.text('Dil%: ' + pct.toFixed(1) + '%');
        $b.prepend($dot);
        $b.attr('title', 'Dil% = Σ OV L30 (' + Math.round(ov).toLocaleString()
            + ') ÷ Σ INV (' + Math.round(stock).toLocaleString() + ') × 100. Click the dot for daily history.');
        paintDot(pct);
        postSnapshot(pct, ov, stock);
    }
    function postSnapshot(dil, ov, inv) {
        const ch = channel();
        if (!ch) return;
        const key = ch + '|' + todayPt() + '|' + dil.toFixed(2);
        if (key === lastPosted) return;
        lastPosted = key;
        $.ajax({
            url: '/analytics/dil-badge-snapshot',
            method: 'POST',
            data: {
                _token: csrf(),
                channel: ch,
                dil_ov_percent: dil.toFixed(2),
                total_ov_l30: ov,
                total_inv: inv
            }
        });
    }
    function loadPrev() {
        const ch = channel();
        if (!ch || prevLoaded) return;
        $.ajax({
            url: '/analytics/dil-badge-prev-day',
            method: 'GET',
            data: { channel: ch },
            success: function(resp) {
                prevLoaded = true;
                const v = resp && resp.metrics ? parseFloat(resp.metrics.dil_ov_percent) : NaN;
                prevDil = isFinite(v) ? v : null;
                const live = parseFloat($('#analytics-dil-badge').attr('data-live-value'));
                paintDot(live);
            },
            error: function() { prevLoaded = true; }
        });
    }
    function overlayLive(rows) {
        const list = Array.isArray(rows) ? rows.slice() : [];
        const live = parseFloat($('#analytics-dil-badge').attr('data-live-value'));
        if (!isFinite(live)) return list;
        const asOf = lastCompletedPt();
        const asOfLabel = labelFor(asOf);
        const last = list.length ? list[list.length - 1] : null;
        if (last && (last.full_date === asOf || last.date === asOfLabel)) {
            last.value = live;
            last.full_date = asOf;
            last.date = asOfLabel;
        } else {
            list.push({ date: asOfLabel, full_date: asOf, value: live });
        }
        return list;
    }
    function renderChart(data) {
        const run = function() {
            const ctxEl = document.getElementById('analyticsDilChartCanvas');
            if (!ctxEl || typeof Chart === 'undefined') return;
            if (chartInst) chartInst.destroy();
            const seen = {};
            data = (data || []).filter(function(d) {
                const k = d.full_date || d.date || '';
                if (!k || seen[k]) return false;
                seen[k] = true;
                return true;
            });
            const labels = data.map(function(d) { return d.date; });
            const values = data.map(function(d) { return Number(d.value); });
            const dataMin = Math.min.apply(null, values);
            const dataMax = Math.max.apply(null, values);
            const sorted = values.slice().sort(function(a, b) { return a - b; });
            const mid = Math.floor(sorted.length / 2);
            const median = sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
            document.getElementById('analyticsDilChartHighest').textContent = dataMax.toFixed(1) + '%';
            document.getElementById('analyticsDilChartMedian').textContent = median.toFixed(1) + '%';
            document.getElementById('analyticsDilChartLowest').textContent = dataMin.toFixed(1) + '%';
            const range = dataMax - dataMin;
            const yMin = range < 1e-9 ? Math.max(0, dataMin - 0.5) : Math.max(0, dataMin - range * 0.12);
            const yMax = range < 1e-9 ? dataMax + 1.2 : dataMax + Math.max(range * 0.38, 0.8);
            const valueLabelsPlugin = {
                id: 'analyticsDilValueLabels',
                afterDraw: function(chart) {
                    const dataset = chart.data.datasets[0];
                    const meta = chart.getDatasetMeta(0);
                    const c = chart.ctx;
                    if (!dataset || !meta || !meta.data) return;
                    const angle = -40 * Math.PI / 180;
                    meta.data.forEach(function(point, i) {
                        const val = dataset.data[i];
                        if (val == null || !point) return;
                        const txt = Number(val).toFixed(1) + '%';
                        c.save();
                        c.font = 'bold 12px Inter, system-ui, sans-serif';
                        c.fillStyle = '#111';
                        c.strokeStyle = 'rgba(255,255,255,0.95)';
                        c.lineWidth = 3;
                        c.lineJoin = 'round';
                        c.textAlign = 'left';
                        c.textBaseline = 'middle';
                        c.translate(point.x + 4, point.y - 10);
                        c.rotate(angle);
                        c.strokeText(txt, 0, 0);
                        c.fillText(txt, 0, 0);
                        c.restore();
                    });
                }
            };
            chartInst = new Chart(ctxEl.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Dil%',
                        data: values,
                        borderColor: '#fd7e14',
                        backgroundColor: 'rgba(253,126,20,0.12)',
                        fill: true,
                        tension: 0.25,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#fd7e14'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 22, right: 36 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: true }
                    },
                    scales: {
                        x: { ticks: { maxRotation: 90, minRotation: 90, font: { size: 10, weight: '600' } } },
                        y: { min: yMin, max: yMax, ticks: { callback: function(v) { return Number(v).toFixed(1) + '%'; } } }
                    }
                },
                plugins: [valueLabelsPlugin]
            });
        };
        if (typeof Chart === 'undefined' && typeof loadChartJs === 'function') {
            loadChartJs().then(run);
        } else {
            run();
        }
    }
    function loadChart() {
        const ch = channel();
        if (!ch) return;
        $('#analyticsDilChartWrap').hide();
        $('#analyticsDilChartNoData').hide();
        $('#analyticsDilChartLoading').show();
        if (chartAjax) chartAjax.abort();
        chartAjax = $.ajax({
            url: '/analytics/dil-badge-chart-data',
            method: 'GET',
            data: { channel: ch, days: chartDays },
            success: function(resp) {
                chartAjax = null;
                $('#analyticsDilChartLoading').hide();
                const rows = overlayLive((resp && resp.success && resp.data) ? resp.data : []);
                if (!rows.length) {
                    $('#analyticsDilChartNoData').show();
                    return;
                }
                $('#analyticsDilChartWrap').css({ display: 'flex', alignItems: 'stretch' }).show();
                renderChart(rows);
            },
            error: function(xhr, status) {
                chartAjax = null;
                if (status === 'abort') return;
                $('#analyticsDilChartLoading').hide();
                $('#analyticsDilChartNoData').show();
            }
        });
    }
    function openChart() {
        const el = document.getElementById('analyticsDilChartModal');
        if (!el) return;
        $('#analyticsDilChartTitle').text('Dil% — daily trend');
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
        } else {
            $(el).modal('show');
        }
        loadChart();
    }
    function refreshFromTable() {
        const t = totalsFromRows(defaultRows());
        if (t.inv <= 0 && t.ovL30 <= 0 && !inited) return;
        set(t.dil, t.ovL30, t.inv);
    }

    window.AnalyticsDilBadge = {
        init: function(opts) {
            opts = opts || {};
            if (typeof opts.getRows === 'function') this._getRows = opts.getRows;
            inited = true;
            loadPrev();
            refreshFromTable();
        },
        set: set,
        refreshFromTable: refreshFromTable
    };

    $(function() {
        loadPrev();
        $(document).on('click', '#analytics-dil-badge .summary-trend-dot', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openChart();
        });
        $(document).on('click', '#analytics-dil-badge', function(e) {
            if ($(e.target).closest('.summary-trend-dot').length) return;
            openChart();
        });
        $('#analyticsDilChartRange').on('change', function() {
            chartDays = parseInt($(this).val(), 10) || 0;
            loadChart();
        });
        setTimeout(refreshFromTable, 1200);
        setTimeout(refreshFromTable, 4000);
        let dilPoll = 0;
        const dilPoller = setInterval(function() {
            refreshFromTable();
            if (++dilPoll >= 8) clearInterval(dilPoller);
        }, 2000);
        $(document).on('ebay2-tabulator-data-loaded', refreshFromTable);
    });
})();
</script>
