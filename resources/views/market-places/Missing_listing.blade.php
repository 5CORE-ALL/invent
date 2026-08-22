@extends('layouts.vertical', ['title' => 'Missing Listing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        .ml-channel-logo {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 4px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 1px;
            display: inline-block;
        }
        .ml-channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            color: #adb5bd;
            font-size: 12px;
        }
        .ml-channel-listing-link {
            color: inherit;
            font-weight: 600;
            text-decoration: none;
        }
        .ml-channel-listing-link:hover {
            color: #0d6efd;
            text-decoration: underline;
        }
        #stat-missing-listing.badge,
        .badge-ml-stat {
            font-size: 1.35rem !important;
            line-height: 1.35;
            padding: 0.75rem 1.25rem !important;
            border-radius: 0.35rem !important; /* rectangular, not pill */
            font-weight: 700;
        }
        .badge-ml-chart { cursor: pointer; font-weight: bold; }
        .ml-seller-portal-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .ml-seller-portal-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e7f1ff;
            color: #0d6efd;
            text-decoration: none;
            transition: background-color 0.15s ease, color 0.15s ease;
        }
        .ml-seller-portal-link:hover {
            background: #0d6efd;
            color: #fff;
        }
        .ml-seller-portal-empty {
            color: #adb5bd;
            font-style: italic;
            font-size: 0.75rem;
        }
        .ml-from-sheet {
            color: #6c757d;
            font-weight: 600;
            font-style: italic;
        }
        .ml-source-api {
            color: #198754;
            font-weight: 700;
        }
        .ml-source-sheet {
            color: #6c757d;
            font-weight: 700;
        }
        .tabulator .tabulator-cell.tabulator-editing { padding: 2px 4px; }

        /* Metric history modal — same full-width layout as Active Channel */
        #mlMetricChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #mlMetricChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #mlMetricChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Missing Listing',
        'sub_title'  => '',
    ])

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="badge bg-danger badge-ml-stat badge-ml-chart" id="stat-missing-listing" data-metric="missing_l" title="Missing L total from API channels only (Sheet channels excluded)" style="background-color:#a71d2a !important;">
                        Missing L: <span id="total-missing-listing">{{ number_format(\App\Support\Marketplace\ListingChannelCounts::totalMissingL(true)) }}</span>
                    </span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom">
                    <input type="text" id="missing-listing-search" class="form-control form-control-sm" placeholder="Search by Channel...">
                </div>
                <div id="missing-listing-table" style="height: calc(100vh - 280px);"></div>
            </div>
        </div>
    </div>

    <div class="modal fade p-0" id="mlMetricChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="mlChartModalTitle">Missing Listing - Rolling window (California)</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="mlChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30">30 Days</option>
                            <option value="31">31 Days</option>
                            <option value="32" selected>32 Days</option>
                            <option value="35">35 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="mlChartContainer" style="height: 20vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="mlMetricChart"></canvas>
                        </div>
                        <div id="mlChartRefPanel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="mlChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="mlChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="mlChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="mlChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="mlChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">Daily history is not available yet.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    let table = null;
    let mlMetricChartInstance = null;
    let mlChartAjax = null;
    let mlCurrentChartChannel = 'all';
    let mlCurrentChartDisplayChannel = 'All';
    let mlCurrentMetricKey = 'missing_l';
    let mlCurrentChartDays = 32;
    let mlCurrentBadgeValue = null;

    function updateStats(rows, totalMissingL) {
        if (totalMissingL !== undefined && totalMissingL !== null && !isNaN(Number(totalMissingL))) {
            $('#total-missing-listing').text(Number(totalMissingL).toLocaleString('en-US'));
            return;
        }
        const total = (rows || []).reduce((sum, r) => {
            if (String(r.data_source || '').toUpperCase() === 'SHEET') return sum;
            return sum + Number(r.missing_listing || 0);
        }, 0);
        $('#total-missing-listing').text(total.toLocaleString('en-US'));
    }

    function isSheetRow(rowData) {
        return String((rowData && rowData.data_source) || '').toUpperCase() === 'SHEET';
    }

    function fromSheetCell() {
        return '<span class="ml-from-sheet" title="Listing counts come from Sheet — not calculated here">From Sheet</span>';
    }

    function mlChartRangeLabel(days) {
        return days === 0 ? 'Lifetime' : ('L' + days);
    }

    function mlFmtVal(v) {
        return Math.round(Number(v || 0)).toLocaleString('en-US');
    }

    function showMlMetricChart(channel, cellValue) {
        mlCurrentChartDisplayChannel = String(channel || 'All');
        mlCurrentChartChannel = mlCurrentChartDisplayChannel.toLowerCase().replace(/[^a-z0-9]/g, '');
        mlCurrentMetricKey = 'missing_l';
        mlCurrentChartDays = 32;
        mlCurrentBadgeValue = (mlCurrentChartDisplayChannel === 'All' && cellValue !== undefined && cellValue !== null && !isNaN(cellValue))
            ? cellValue
            : null;

        $('#mlChartRangeSelect').val('32');
        const label = mlCurrentChartDisplayChannel === 'All'
            ? 'Missing Listing'
            : (mlCurrentChartDisplayChannel + ' - Missing Listing');
        $('#mlChartModalTitle').text(`${label} (Rolling ${mlChartRangeLabel(mlCurrentChartDays)}, California)`);

        const modal = new bootstrap.Modal(document.getElementById('mlMetricChartModal'));
        modal.show();
        loadMlMetricChart();
    }

    function loadMlMetricChart() {
        if (mlChartAjax) mlChartAjax.abort();

        $('#mlChartNoData').hide();
        $('#mlChartContainer').hide();
        $('#mlChartLoading').show();

        const params = {
            channel: mlCurrentChartChannel,
            metric: mlCurrentMetricKey,
            days: mlCurrentChartDays,
        };
        if (mlCurrentBadgeValue !== null) {
            params.badge_value = mlCurrentBadgeValue;
        }

        mlChartAjax = $.ajax({
            url: "{{ route('missing.listing.chart.data') }}",
            method: 'GET',
            data: params,
        }).done(function(response) {
            mlChartAjax = null;
            $('#mlChartLoading').hide();

            if (response.success !== false && response.data && response.data.length > 0) {
                $('#mlChartContainer').show();
                renderMlMetricChart(response.data);
            } else {
                $('#mlChartNoData').show();
            }
        }).fail(function(_xhr, status) {
            mlChartAjax = null;
            if (status === 'abort') return;
            $('#mlChartLoading').hide();
            $('#mlChartNoData').show();
        });
    }

    function renderMlMetricChart(data) {
        const ctx = document.getElementById('mlMetricChart').getContext('2d');
        if (mlMetricChartInstance) mlMetricChartInstance.destroy();

        const labels = data.map(d => d.date);
        const values = data.map(d => Number(d.value || 0));
        const dataMin = Math.min(...values);
        const dataMax = Math.max(...values);
        const sorted = [...values].sort((a, b) => a - b);
        const mid = Math.floor(sorted.length / 2);
        const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
        const range = dataMax - dataMin || 1;
        const yMin = Math.max(0, dataMin - range * 0.1);
        const yMax = dataMax + range * 0.1;

        const refRed = '#dc3545';
        const refGray = '#6c757d';
        const refGreen = '#198754';
        const highestEl = document.getElementById('mlChartHighest');
        const medianEl = document.getElementById('mlChartMedian');
        const lowestEl = document.getElementById('mlChartLowest');
        highestEl.textContent = mlFmtVal(dataMax);
        highestEl.style.color = dataMax === 0 ? refGreen : dataMax > 0 ? refRed : refGray;
        medianEl.textContent = mlFmtVal(median);
        medianEl.style.color = median === 0 ? refGreen : median > 0 ? refRed : refGray;
        lowestEl.textContent = mlFmtVal(dataMin);
        lowestEl.style.color = dataMin === 0 ? refGreen : dataMin > 0 ? refRed : refGray;

        const dotColors = values.map((v, i) => {
            if (i === 0) return refGray;
            return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? refRed : refGray;
        });
        const labelColors = values.map(v => v === 0 ? refGreen : v > 0 ? refRed : refGray);

        const medianLinePlugin = {
            id: 'mlMedianLine',
            afterDraw(chart) {
                const yScale = chart.scales.y;
                const xScale = chart.scales.x;
                const c = chart.ctx;
                const yPixel = yScale.getPixelForValue(median);
                c.save();
                c.setLineDash([6, 4]);
                c.strokeStyle = refGray;
                c.lineWidth = 1.2;
                c.beginPath();
                c.moveTo(xScale.left, yPixel);
                c.lineTo(xScale.right, yPixel);
                c.stroke();
                c.restore();
            }
        };

        const valueLabelsPlugin = {
            id: 'mlValueLabels',
            afterDatasetsDraw(chart) {
                const dataset = chart.data.datasets[0];
                const meta = chart.getDatasetMeta(0);
                const c = chart.ctx;
                c.save();
                c.font = 'bold 11px Inter, system-ui, sans-serif';
                c.textAlign = 'center';
                c.textBaseline = 'bottom';
                meta.data.forEach((point, i) => {
                    const val = dataset.data[i];
                    c.fillStyle = labelColors[i];
                    c.fillText(mlFmtVal(val), point.x, point.y + ((i % 2 === 0) ? -10 : -20));
                });
                c.restore();
            }
        };

        mlMetricChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Missing Listing',
                    data: values,
                    backgroundColor: 'rgba(108,117,125,0.08)',
                    borderColor: '#adb5bd',
                    borderWidth: 1.5,
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: dotColors,
                    pointBorderColor: dotColors,
                    pointBorderWidth: 1.5,
                }],
            },
            plugins: [medianLinePlugin, valueLabelsPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 26, left: 2, right: 2, bottom: 2 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        titleFont: { size: 10 },
                        bodyFont: { size: 10 },
                        padding: 6,
                        callbacks: {
                            label: function(context) {
                                const idx = context.dataIndex;
                                const parts = ['Value: ' + mlFmtVal(context.raw)];
                                if (idx > 0) {
                                    const diff = context.raw - values[idx - 1];
                                    const arrow = diff < 0 ? '▼' : diff > 0 ? '▲' : '▬';
                                    parts.push('vs Yesterday: ' + arrow + ' ' + mlFmtVal(Math.abs(diff)));
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
                            font: { size: 9 },
                            callback: function(value) { return mlFmtVal(value); }
                        }
                    },
                    x: {
                        ticks: { maxRotation: 45, minRotation: 45, font: { size: 9 } }
                    }
                }
            }
        });
    }

    function showToast(message, type) {
        type = type || 'info';
        const container = document.querySelector('.toast-container');
        if (!container) return;
        const bg = type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info';
        const el = document.createElement('div');
        el.className = `toast align-items-center text-white bg-${bg} border-0 mb-2`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>`;
        container.appendChild(el);
        new bootstrap.Toast(el, { delay: 4000 }).show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // channel_master.logo is stored as a relative path under storage/
    // (e.g. channel_logos/amazon.png) — same as all-marketplace-master Img column.
    function mlLogoSrc(logo) {
        const v = String(logo || '').trim();
        if (!v) return '';
        if (/^https?:\/\//i.test(v) || v.startsWith('/')) return v;
        return '/storage/' + v.replace(/^\/+/, '');
    }

    function saveSellerPortal(cell) {
        const row = cell.getRow();
        const data = row.getData();
        const newValue = (cell.getValue() || '').trim();
        const oldValue = (cell.getOldValue() || '').trim();

        if (newValue === oldValue) return;

        if (newValue !== '') {
            try {
                new URL(newValue);
            } catch (_) {
                showToast('Please enter a valid URL (including https://).', 'error');
                cell.setValue(oldValue, true);
                return;
            }
        }

        $.ajax({
            url: "{{ route('missing.listing.seller.portal.save') }}",
            method: 'POST',
            data: { id: data.id, seller_portal: newValue },
            dataType: 'json',
        }).done(function(res) {
            if (res && res.success) {
                showToast(res.message || 'Seller Portal updated.', 'success');
            } else {
                showToast((res && res.message) || 'Update failed.', 'error');
                cell.setValue(oldValue, true);
            }
        }).fail(function(xhr) {
            const msg = (xhr.responseJSON && xhr.responseJSON.message)
                ? xhr.responseJSON.message
                : (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.seller_portal)
                    ? xhr.responseJSON.errors.seller_portal[0]
                    : 'Update failed.';
            showToast(msg, 'error');
            cell.setValue(oldValue, true);
        });
    }

    $(document).ready(function() {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

        $('#stat-missing-listing').on('click', function() {
            const badgeText = $('#total-missing-listing').text().replace(/[,$%]/g, '').trim();
            const badgeValue = parseFloat(badgeText) || null;
            showMlMetricChart('All', badgeValue);
        });

        $('#mlChartRangeSelect').on('change', function() {
            const days = parseInt($(this).val(), 10);
            if (days === mlCurrentChartDays) return;
            mlCurrentChartDays = days;
            const label = mlCurrentChartDisplayChannel === 'All'
                ? 'Missing Listing'
                : (mlCurrentChartDisplayChannel + ' - Missing Listing');
            $('#mlChartModalTitle').text(`${label} (Rolling ${mlChartRangeLabel(days)}, California)`);
            loadMlMetricChart();
        });

        table = new Tabulator("#missing-listing-table", {
            ajaxURL: "{{ route('missing.listing.data') }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                updateStats(data, response && response.total_missing_l);
                return data;
            },
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: "channel", dir: "asc" }],
            placeholder: "No channels found.",
            columns: [
                {
                    title: "Image",
                    field: "image",
                    headerSort: false,
                    width: 90,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const logo = cell.getValue();
                        const channel = (cell.getRow().getData().channel || '').trim();
                        if (!logo) {
                            return '<span class="ml-channel-logo-placeholder" title="No logo"><i class="fas fa-image"></i></span>';
                        }
                        const src = mlLogoSrc(logo);
                        const safeSrc = escapeHtml(src);
                        const safeAlt = escapeHtml(channel);
                        return `<img src="${safeSrc}" alt="${safeAlt}" class="ml-channel-logo" onerror="this.style.display='none'">`;
                    }
                },
                {
                    title: "Channel",
                    field: "channel",
                    minWidth: 220,
                    formatter: function(cell) {
                        const name = (cell.getValue() || '').trim();
                        const url = (cell.getRow().getData().listing_url || '').trim();
                        if (!name) return '';
                        const safeName = escapeHtml(name);
                        if (!url) return safeName;
                        const safeUrl = escapeHtml(url);
                        return `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer" class="ml-channel-listing-link" title="Open listing page">${safeName}</a>`;
                    },
                },
                {
                    title: "Data Source",
                    field: "data_source",
                    width: 120,
                    hozAlign: "center",
                    headerTooltip: "API = live listing-page counts; Sheet = From Sheet (no numbers)",
                    formatter: function(cell) {
                        const v = String(cell.getValue() || '').trim();
                        if (v.toUpperCase() === 'API') {
                            return '<span class="ml-source-api">API</span>';
                        }
                        if (v.toUpperCase() === 'SHEET') {
                            return '<span class="ml-source-sheet">Sheet</span>';
                        }
                        return escapeHtml(v || '-');
                    },
                },
                {
                    title: "SKU",
                    field: "sku",
                    width: 90,
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "Total non-PARENT SKUs from CP Master",
                    formatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        return `<span style="color:#0d6efd;font-weight:600;">${v.toLocaleString('en-US')}</span>`;
                    },
                },
                {
                    title: "0 Inv",
                    field: "zero_inv",
                    width: 90,
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "SKUs with 0 / missing Shopify INV from CP Master",
                    formatter: function(cell) {
                        const v = Number(cell.getValue() || 0);
                        return `<span style="color:#dc3545;font-weight:600;">${v.toLocaleString('en-US')}</span>`;
                    },
                },
                {
                    title: "REQ",
                    field: "req",
                    width: 100,
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "REQ count from the channel listing page (API only)",
                    formatter: function(cell) {
                        if (isSheetRow(cell.getRow().getData())) return fromSheetCell();
                        const v = Number(cell.getValue() || 0);
                        return `<span style="color:#198754;font-weight:600;">${v.toLocaleString('en-US')}</span>`;
                    },
                    bottomCalc: function(values, data) {
                        return (data || []).reduce((sum, row) => {
                            if (isSheetRow(row)) return sum;
                            return sum + Number(row.req || 0);
                        }, 0);
                    },
                    bottomCalcFormatter: function(cell) {
                        return Number(cell.getValue() || 0).toLocaleString('en-US');
                    },
                },
                {
                    title: "NRL",
                    field: "nrl",
                    width: 100,
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "NRL count from the channel listing page (API only)",
                    formatter: function(cell) {
                        if (isSheetRow(cell.getRow().getData())) return fromSheetCell();
                        const v = Number(cell.getValue() || 0);
                        return `<span style="color:#dc3545;font-weight:600;">${v.toLocaleString('en-US')}</span>`;
                    },
                    bottomCalc: function(values, data) {
                        return (data || []).reduce((sum, row) => {
                            if (isSheetRow(row)) return sum;
                            return sum + Number(row.nrl || 0);
                        }, 0);
                    },
                    bottomCalcFormatter: function(cell) {
                        return Number(cell.getValue() || 0).toLocaleString('en-US');
                    },
                },
                {
                    title: "Listed",
                    field: "listed",
                    width: 110,
                    hozAlign: "center",
                    sorter: "number",
                    headerTooltip: "Listed count from the channel listing page (API only)",
                    formatter: function(cell) {
                        if (isSheetRow(cell.getRow().getData())) return fromSheetCell();
                        const v = Number(cell.getValue() || 0);
                        return `<span style="color:#0d6efd;font-weight:600;">${v.toLocaleString('en-US')}</span>`;
                    },
                    bottomCalc: function(values, data) {
                        return (data || []).reduce((sum, row) => {
                            if (isSheetRow(row)) return sum;
                            return sum + Number(row.listed || 0);
                        }, 0);
                    },
                    bottomCalcFormatter: function(cell) {
                        return Number(cell.getValue() || 0).toLocaleString('en-US');
                    },
                },
                {
                    title: "Missing Listing",
                    field: "missing_listing",
                    width: 180,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        if (isSheetRow(cell.getRow().getData())) return fromSheetCell();
                        const v = Number(cell.getValue() || 0);
                        const row = cell.getRow().getData();
                        const channel = (row.channel || '').trim();
                        const listingUrl = String(row.listing_url || '').trim();
                        const color = v === 0 ? '#198754' : '#dc3545';
                        const dotColor = v === 0 ? '#198754' : (v > 0 ? '#dc3545' : '#6c757d');
                        const chartIcon = `<i class="fas fa-circle ml-metric-chart-icon ms-1" data-channel="${escapeHtml(channel)}" style="cursor:pointer;color:${dotColor};font-size:8px;" title="View Chart"></i>`;
                        const countHtml = listingUrl
                            ? `<a href="${escapeHtml(listingUrl)}?missing=1" class="ml-channel-listing-link" style="color:${color};font-weight:600;" title="Open Missing L SKUs">${v.toLocaleString('en-US')}</a>`
                            : `<span style="color:${color};font-weight:600;">${v.toLocaleString('en-US')}</span>`;
                        return `${countHtml}${chartIcon}`;
                    },
                    cellClick: function(e, cell) {
                        if (e.target.classList.contains('ml-metric-chart-icon')) {
                            e.stopPropagation();
                            if (isSheetRow(cell.getRow().getData())) return;
                            const channel = $(e.target).data('channel');
                            const value = Number(cell.getValue() || 0);
                            showMlMetricChart(channel, value);
                        }
                    },
                    bottomCalc: function(values, data) {
                        return (data || []).reduce((sum, row) => {
                            if (isSheetRow(row)) return sum;
                            return sum + Number(row.missing_listing || 0);
                        }, 0);
                    },
                    bottomCalcFormatter: function(cell) {
                        return Number(cell.getValue() || 0).toLocaleString('en-US');
                    },
                },
                {
                    title: "Seller Portal",
                    field: "seller_portal",
                    width: 90,
                    maxWidth: 90,
                    hozAlign: "center",
                    editor: "input",
                    headerSort: false,
                    headerTooltip: "Double-click cell to edit",
                    cellDblClick: function(_e, cell) {
                        cell.edit();
                    },
                    formatter: function(cell) {
                        const v = (cell.getValue() || '').trim();
                        if (!v) {
                            return '<div class="ml-seller-portal-cell"><span class="ml-seller-portal-empty" title="Double-click to add">Add</span></div>';
                        }
                        const safe = escapeHtml(v);
                        return `<div class="ml-seller-portal-cell">
                                    <a href="${safe}" target="_blank" rel="noopener noreferrer" class="ml-seller-portal-link" title="${safe}" onclick="event.stopPropagation();">
                                        <i class="fa fa-link"></i>
                                    </a>
                                </div>`;
                    },
                    cellEdited: function(cell) {
                        saveSellerPortal(cell);
                    },
                },
            ],
        });

        $('#missing-listing-search').on('input', function() {
            const q = $(this).val().trim().toLowerCase();
            if (!q) {
                table.clearFilter(true);
                return;
            }
            table.setFilter(function(row) {
                return String(row.channel || '').toLowerCase().includes(q);
            });
        });
    });
</script>
@endsection
