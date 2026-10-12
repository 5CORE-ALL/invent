<div class="modal fade p-0" id="ebayCbidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog shadow-none m-0 mx-0">
        <div class="modal-content" style="overflow: hidden;">
            <div class="modal-header bg-info text-white py-1 px-3">
                <h6 class="modal-title mb-0" style="font-size: 13px;">
                    <i class="fas fa-chart-area me-1"></i>
                    <span id="ebayCbidTitle">Ad Spend</span>
                    <span>(ebay_general_reports · 7 Days)</span>
                </h6>
                <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2">
                <div id="ebayCbidChartWrap" style="height: 38vh; display: none; align-items: stretch;">
                    <div style="flex: 1; min-width: 0; position: relative;">
                        <canvas id="ebayCbidChart"></canvas>
                    </div>
                    <div style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                        <div style="text-align: center;">
                            <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">High</div>
                            <div id="ebayCbidHigh" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                        </div>
                        <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                            <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Med</div>
                            <div id="ebayCbidMed" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Low</div>
                            <div id="ebayCbidLow" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                        </div>
                    </div>
                </div>
                <div id="ebayCbidLoading" class="text-center py-3" style="display: none;">
                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                </div>
                <div id="ebayCbidEmpty" class="text-center py-3" style="display: none;">
                    <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                    <p class="text-muted small mb-0">No daily rows in ebay_general_reports for this listing.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    const CBID_HISTORY_URL = @json(url('/ebay/cbid-history'));
    let ebayCbidChart = null;

    function cbidFmt(v) {
        const n = Number(v);
        return isFinite(n) ? '$' + n.toFixed(2) : '—';
    }

    function setCbidBox(id, show) {
        const el = document.getElementById(id);
        if (!el) return;
        el.style.display = show ? (id === 'ebayCbidChartWrap' ? 'flex' : 'block') : 'none';
    }

    function ensureCbidChart(fn) {
        if (typeof Chart !== 'undefined') { fn(); return; }
        if (typeof chPromoZsWithChart === 'function') { chPromoZsWithChart(fn); return; }
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js';
        s.onload = fn;
        s.onerror = function() {
            setCbidBox('ebayCbidLoading', false);
            setCbidBox('ebayCbidEmpty', true);
        };
        document.head.appendChild(s);
    }

    function renderStandardCbidChart(points) {
        const values = (points || []).map(function(d) {
            const n = parseFloat(d && d.bid);
            return isFinite(n) ? n : null;
        });
        const labels = (points || []).map(function(d) { return (d && (d.date_formatted || d.date)) || ''; });
        const valid = values.filter(function(v) { return v !== null; });
        setCbidBox('ebayCbidLoading', false);
        if (!valid.length || typeof Chart === 'undefined') {
            setCbidBox('ebayCbidChartWrap', false);
            setCbidBox('ebayCbidEmpty', true);
            return;
        }
        const dataMin = Math.min.apply(null, valid);
        const dataMax = Math.max.apply(null, valid);
        const sorted = valid.slice().sort(function(a, b) { return a - b; });
        const mid = Math.floor(sorted.length / 2);
        const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
        const range = dataMax - dataMin;
        let yMin;
        let yMax;
        if (range < 1e-9) {
            const pad = Math.max(Math.abs(dataMax) * 0.08, 0.6);
            yMin = Math.max(0, dataMin - pad);
            yMax = dataMax + pad;
        } else {
            yMin = Math.max(0, dataMin - range * 0.12);
            yMax = dataMax + Math.max(range * 0.28, Math.abs(dataMax) * 0.08);
        }
        const highEl = document.getElementById('ebayCbidHigh');
        const medEl = document.getElementById('ebayCbidMed');
        const lowEl = document.getElementById('ebayCbidLow');
        if (highEl) highEl.textContent = cbidFmt(dataMax);
        if (medEl) medEl.textContent = cbidFmt(median);
        if (lowEl) lowEl.textContent = cbidFmt(dataMin);

        const dotColors = values.map(function(v, i) {
            if (v === null) return '#6c757d';
            const prev = values.slice(0, i).reverse().find(function(n) { return n !== null; });
            if (prev === undefined) return '#6c757d';
            if (v > prev) return '#28a745';
            if (v < prev) return '#dc3545';
            return '#6c757d';
        });

        if (ebayCbidChart) {
            ebayCbidChart.destroy();
            ebayCbidChart = null;
        }
        const canvas = document.getElementById('ebayCbidChart');
        if (!canvas) return;
        setCbidBox('ebayCbidEmpty', false);
        setCbidBox('ebayCbidChartWrap', true);

        ebayCbidChart = new Chart(canvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: 'rgba(13, 110, 253, 0.08)',
                    borderColor: '#0d6efd',
                    borderWidth: 1.5,
                    fill: true,
                    tension: 0.3,
                    spanGaps: false,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: dotColors,
                    pointBorderColor: dotColors,
                    pointBorderWidth: 1.5
                }]
            },
            plugins: [{
                id: 'ebayCbidMedianLine',
                afterDraw: function(chart) {
                    const yScale = chart.scales.y;
                    const xScale = chart.scales.x;
                    if (!yScale || !xScale) return;
                    const c = chart.ctx;
                    const yPixel = yScale.getPixelForValue(median);
                    c.save();
                    c.setLineDash([6, 4]);
                    c.strokeStyle = '#6c757d';
                    c.lineWidth = 1.2;
                    c.beginPath();
                    c.moveTo(xScale.left, yPixel);
                    c.lineTo(xScale.right, yPixel);
                    c.stroke();
                    c.restore();
                }
            }, {
                id: 'ebayCbidValueLabels',
                afterDatasetsDraw: function(chart) {
                    const dataset = chart.data.datasets[0];
                    const meta = chart.getDatasetMeta(0);
                    const c = chart.ctx;
                    if (!dataset || !meta || !meta.data) return;
                    meta.data.forEach(function(point, i) {
                        const val = dataset.data[i];
                        if (val == null || !point || point.skip) return;
                        c.save();
                        c.font = 'bold 13px Inter, system-ui, sans-serif';
                        c.fillStyle = '#000000';
                        c.strokeStyle = 'rgba(255,255,255,0.95)';
                        c.lineWidth = 3;
                        c.lineJoin = 'round';
                        c.textAlign = 'left';
                        c.textBaseline = 'middle';
                        c.translate(point.x + 2, point.y - 10);
                        c.rotate(-40 * Math.PI / 180);
                        c.strokeText(cbidFmt(val), 0, 0);
                        c.fillText(cbidFmt(val), 0, 0);
                        c.restore();
                    });
                }
            }],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                clip: false,
                layout: { padding: { top: 44, left: 8, right: 22, bottom: 28 } },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        titleFont: { size: 13 },
                        bodyFont: { size: 13 },
                        padding: 8,
                        callbacks: {
                            label: function(context) {
                                const y = context.parsed.y;
                                const row = points[context.dataIndex] || {};
                                if (y == null || !isFinite(y)) return 'No row in ebay_general_reports';
                                const parts = ['Ad Spend: ' + cbidFmt(y)];
                                if (row.impressions != null) parts.push('Impressions: ' + Number(row.impressions).toLocaleString('en-US'));
                                if (row.clicks != null) parts.push('Clicks: ' + Number(row.clicks).toLocaleString('en-US'));
                                const prev = values.slice(0, context.dataIndex).reverse().find(function(n) { return n !== null; });
                                if (prev !== undefined) {
                                    const diff = y - prev;
                                    parts.push('vs prior: ' + (diff < 0 ? '▼' : diff > 0 ? '▲' : '▬') + ' ' + cbidFmt(Math.abs(diff)));
                                }
                                return parts;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        min: yMin,
                        max: yMax,
                        ticks: {
                            font: { size: 13, weight: '600' },
                            callback: function(v) { return cbidFmt(v); }
                        }
                    },
                    x: {
                        offset: true,
                        ticks: {
                            maxRotation: 90,
                            minRotation: 90,
                            autoSkip: false,
                            font: { size: 11, weight: '600' }
                        }
                    }
                }
            }
        });
    }

    window.openStandardCbidChart = function(opts) {
        opts = opts || {};
        const titleEl = document.getElementById('ebayCbidTitle');
        if (titleEl) titleEl.textContent = 'Ad Spend — ' + (opts.title || opts.listingId || '');
        setCbidBox('ebayCbidChartWrap', false);
        setCbidBox('ebayCbidEmpty', false);
        setCbidBox('ebayCbidLoading', true);
        const modalEl = document.getElementById('ebayCbidModal');
        if (window.jQuery && jQuery.fn && jQuery.fn.modal) {
            jQuery('#ebayCbidModal').modal('show');
        } else if (modalEl && window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
        const listingId = opts.listingId || '';
        fetch(CBID_HISTORY_URL + '?listing_id=' + encodeURIComponent(listingId), { headers: { Accept: 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(rows) {
                ensureCbidChart(function() { renderStandardCbidChart(Array.isArray(rows) ? rows : []); });
            })
            .catch(function() { renderStandardCbidChart([]); });
    };
})();
</script>
