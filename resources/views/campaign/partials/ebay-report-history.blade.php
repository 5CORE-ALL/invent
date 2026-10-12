{{-- Daily clicks / spend / sales history. Pass $historyUrl. --}}
<div class="modal fade" id="ecaReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white py-2">
                <h6 class="modal-title mb-0" id="ecaReportTitle">History</h6>
                <div class="d-flex align-items-center gap-2">
                    <select id="eca-report-days" class="form-select form-select-sm" style="width:110px;">
                        <option value="7">7 Days</option>
                        <option value="14">14 Days</option>
                        <option value="30" selected>30 Days</option>
                        <option value="60">60 Days</option>
                        <option value="90">90 Days</option>
                        <option value="0">Lifetime</option>
                    </select>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="ecaReportLoading" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary"></div>
                    <p class="mt-2 text-muted small mb-0">Loading history…</p>
                </div>
                <div id="ecaReportEmpty" class="text-center text-muted py-4 d-none">No daily report yet.</div>
                <div id="ecaReportBody" class="d-none">
                    <div class="d-flex justify-content-end gap-3 small fw-bold mb-2">
                        <span>High <span id="ecaReportHigh" class="text-danger">—</span></span>
                        <span>Med <span id="ecaReportMed" class="text-secondary">—</span></span>
                        <span>Low <span id="ecaReportLow" class="text-success">—</span></span>
                    </div>
                    <div id="ecaReportChart" style="height:340px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
const ECA_REPORT_HISTORY_URL = @json($historyUrl);
let ecaReportRow = null;
let ecaReportMetric = 'rpt_clicks';
let ecaReportChart = null;

function ebayRptEsc(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;');
}

function ebayRptCellHtml(row, field, prevField, dateField, metric, money) {
    const raw = row ? row[field] : null;
    const has = raw !== null && raw !== undefined && raw !== '' && isFinite(parseFloat(raw));
    if (!has) return '<span class="text-muted">—</span>';
    const amount = parseFloat(raw);
    const prevRaw = row[prevField];
    const hasPrev = prevRaw !== null && prevRaw !== undefined && prevRaw !== '' && isFinite(parseFloat(prevRaw));
    const prev = hasPrev ? parseFloat(prevRaw) : null;
    const prevWhen = row[dateField] ? String(row[dateField]) : 'the prior 7 days';
    const fmt = function(n) {
        return money ? ('$' + Number(n).toFixed(2)) : Math.round(Number(n)).toLocaleString('en-US');
    };
    let dotColor = '#6c757d';
    let dotTip = hasPrev ? ('Same as ' + prevWhen + ' (' + fmt(prev) + ')') : 'No prior 7 days yet';
    if (hasPrev && amount > prev) {
        dotColor = '#28a745';
        dotTip = 'Up vs ' + prevWhen + ' (' + fmt(prev) + ')';
    } else if (hasPrev && amount < prev) {
        dotColor = '#a00211';
        dotTip = 'Down vs ' + prevWhen + ' (' + fmt(prev) + ')';
    }
    const label = metric === 'rpt_spend' ? 'Spend' : (metric === 'rpt_sales' ? 'Sales' : 'Clicks');
    const range = row.rpt_range ? ('Last 7 days ' + row.rpt_range) : 'Last 7 days';
    return '<span class="eca-rpt-hist" data-metric="' + metric + '" style="white-space:nowrap;display:inline-flex;align-items:center;gap:4px;cursor:pointer;" title="' + ebayRptEsc(range + '. ' + dotTip + ' — click for ' + label + ' history') + '">'
        + '<span style="font-weight:600;">' + fmt(amount) + '</span>'
        + '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' + dotColor + ';"></span></span>';
}

function openEcaReportChart(row, metric) {
    ecaReportRow = row || null;
    ecaReportMetric = metric || 'rpt_clicks';
    const sku = (row && (row.resolved_sku || row.sku || row['(Child) sku'])) || '';
    const labels = { rpt_clicks: 'Clicks', rpt_spend: 'Spend', rpt_sales: 'Sales' };
    const titleEl = document.getElementById('ecaReportTitle');
    if (titleEl) titleEl.textContent = (labels[ecaReportMetric] || 'History') + ' — ' + (sku || (row && row.listing_id) || '');
    const modalEl = document.getElementById('ecaReportModal');
    if (modalEl && window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
    loadEcaReportChart();
}

function loadEcaReportChart() {
    const row = ecaReportRow;
    if (!row) return;
    const daysEl = document.getElementById('eca-report-days');
    const days = daysEl ? daysEl.value : '30';
    const loading = document.getElementById('ecaReportLoading');
    const empty = document.getElementById('ecaReportEmpty');
    const body = document.getElementById('ecaReportBody');
    if (loading) loading.classList.remove('d-none');
    if (empty) empty.classList.add('d-none');
    if (body) body.classList.add('d-none');
    const params = new URLSearchParams({
        days: String(days),
        listing_id: row.listing_id || row.eBay_item_id || ''
    });
    fetch(ECA_REPORT_HISTORY_URL + '?' + params.toString(), { headers: { Accept: 'application/json' } })
        .then(function(r) { return r.json(); })
        .then(function(rows) { renderEcaReportChart(Array.isArray(rows) ? rows : []); })
        .catch(function() { renderEcaReportChart([]); });
}

function renderEcaReportChart(rows) {
    const loading = document.getElementById('ecaReportLoading');
    const empty = document.getElementById('ecaReportEmpty');
    const body = document.getElementById('ecaReportBody');
    if (loading) loading.classList.add('d-none');
    const field = ecaReportMetric === 'rpt_spend' ? 'spend' : (ecaReportMetric === 'rpt_sales' ? 'sales' : 'clicks');
    const money = field === 'spend';
    const points = (rows || []).filter(function(d) {
        return d && d.date && d[field] !== null && d[field] !== undefined && isFinite(parseFloat(d[field]));
    });
    if (!points.length || typeof Highcharts === 'undefined') {
        if (body) body.classList.add('d-none');
        if (empty) empty.classList.remove('d-none');
        return;
    }
    if (empty) empty.classList.add('d-none');
    if (body) body.classList.remove('d-none');
    const values = points.map(function(d) { return parseFloat(d[field]); });
    const sorted = values.slice().sort(function(a, b) { return a - b; });
    const mid = Math.floor(sorted.length / 2);
    const median = sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
    const fmt = function(n) {
        return money ? ('$' + Number(n).toFixed(2)) : Math.round(Number(n)).toLocaleString('en-US');
    };
    const highEl = document.getElementById('ecaReportHigh');
    const medEl = document.getElementById('ecaReportMed');
    const lowEl = document.getElementById('ecaReportLow');
    if (highEl) highEl.textContent = fmt(Math.max.apply(null, values));
    if (medEl) medEl.textContent = fmt(median);
    if (lowEl) lowEl.textContent = fmt(Math.min.apply(null, values));
    if (ecaReportChart) {
        ecaReportChart.destroy();
        ecaReportChart = null;
    }
    const name = field === 'spend' ? 'Spend' : (field === 'sales' ? 'Sales' : 'Clicks');
    ecaReportChart = Highcharts.chart('ecaReportChart', {
        chart: { type: 'line', height: 340 },
        title: { text: null },
        credits: { enabled: false },
        legend: { enabled: false },
        xAxis: {
            categories: points.map(function(d) { return d.date_formatted || d.date; }),
            labels: { rotation: -90, style: { fontSize: '10px', fontWeight: '600' } }
        },
        yAxis: {
            title: { text: null },
            labels: { formatter: function() { return money ? ('$' + Highcharts.numberFormat(this.value, 2)) : Highcharts.numberFormat(this.value, 0); } }
        },
        tooltip: { pointFormatter: function() { return '<b>' + fmt(this.y) + '</b>'; } },
        plotOptions: { series: { marker: { radius: 4, lineWidth: 1, lineColor: '#fff' } } },
        series: [{
            name: name,
            color: '#64748b',
            data: values.map(function(v, i) {
                let color = '#6c757d';
                if (i > 0 && v > values[i - 1]) color = '#28a745';
                else if (i > 0 && v < values[i - 1]) color = '#a00211';
                return { y: v, color: color };
            })
        }]
    });
}

document.getElementById('eca-report-days')?.addEventListener('change', loadEcaReportChart);
</script>
