@extends('layouts.vertical', ['title' => 'LQS Master', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        html, body, .wrapper {
            height: auto !important;
            max-height: none !important;
            overflow-x: auto !important;
            overflow-y: auto !important;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa !important;
        }

        .channel-logo-thumb {
            width: 28px;
            height: 28px;
            object-fit: contain;
            border-radius: 4px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 1px;
            display: inline-block;
        }
        .channel-logo-link {
            display: inline-block;
            line-height: 0;
            text-decoration: none;
            cursor: pointer;
        }
        .channel-logo-link:hover .channel-logo-thumb {
            border-color: #0d6efd;
            box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.15);
        }
        .channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 4px;
            background: #f1f3f5;
            border: 1px dashed #ced4da;
            color: #adb5bd;
            font-size: 12px;
        }

        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .content-page {
            min-height: calc(100vh - var(--tz-topbar-height, 70px)) !important;
            height: auto !important;
            max-height: none !important;
            overflow: visible !important;
        }

        #lqs-master-table,
        #lqs-master-table.tabulator {
            height: auto !important;
            min-height: 0 !important;
            width: 100% !important;
            overflow: visible !important;
        }
        #lqs-master-table.tabulator .tabulator-header {
            position: sticky !important;
            top: var(--tz-topbar-height, 70px) !important;
            z-index: 24 !important;
            background-color: #dbeafe !important;
        }
        #lqs-master-table.tabulator .tabulator-header .tabulator-header-contents,
        #lqs-master-table.tabulator .tabulator-header .tabulator-col {
            background-color: #dbeafe !important;
        }
        #lqs-master-table.tabulator .tabulator-tableholder {
            overflow: visible !important;
            height: auto !important;
            max-height: none !important;
        }
        #lqs-master-table.tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed;
            transform: none !important;
            white-space: nowrap;
            height: auto !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 8px;
        }
        #lqs-master-table.tabulator .tabulator-header .tabulator-col {
            height: auto !important;
            min-height: 36px;
        }
        #lqs-master-table.tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 8px !important;
        }

        #lqs-master-avg-badge {
            font-weight: 700;
            font-size: 0.95rem;
            padding: 0.4rem 0.7rem;
        }
        .lqs-master-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
        }
        .lqs-master-chart-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex: 0 0 auto;
            cursor: pointer;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.12);
        }
        .lqs-master-chart-dot:hover {
            transform: scale(1.35);
            box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.18);
        }
        #lqsMasterChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #lqsMasterChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #lqsMasterChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'LQS Master',
        'sub_title'  => 'Active Channel image and channel list',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <input type="text" id="lqs-master-channel-search" class="form-control form-control-sm"
                            placeholder="Search Channel..." style="width: 180px;">
                        <button type="button" id="refresh-lqs-master-table" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                    </div>
                    <div class="mb-3">
                        <span class="badge bg-success" id="lqs-master-avg-badge" title="Average of filled LQS scores">Avg Score: –</span>
                    </div>
                    <div id="lqs-master-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade p-0" id="lqsMasterChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="lqsMasterChartTitle">LQS – Trend</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="lqsMasterChartRange" class="form-select form-select-sm bg-white"
                            style="width:110px;height:26px;font-size:11px;padding:1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30">30 Days</option>
                            <option value="32" selected>32 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="lqsMasterChartContainer" style="height:20vh;display:flex;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;">
                            <canvas id="lqsMasterChart"></canvas>
                        </div>
                        <div id="lqsMasterRefPanel" style="width:100px;display:flex;flex-direction:column;justify-content:center;
                                gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;border-radius:0 4px 4px 0;">
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#dc3545;margin-bottom:1px;">Highest</div>
                                <div id="lqsMasterHighest" style="font-size:13px;font-weight:700;color:#dc3545;">–</div>
                            </div>
                            <div style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;margin-bottom:1px;">Median</div>
                                <div id="lqsMasterMedian" style="font-size:13px;font-weight:700;color:#6c757d;">–</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#198754;margin-bottom:1px;">Lowest</div>
                                <div id="lqsMasterLowest" style="font-size:13px;font-weight:700;color:#198754;">–</div>
                            </div>
                        </div>
                    </div>
                    <div id="lqsMasterChartLoading" class="text-center py-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data…</p>
                    </div>
                    <div id="lqsMasterChartNoData" class="text-center py-3" style="display:none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No trend data yet. Open that channel’s LQS page to start saving daily badge scores.</p>
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
        function lqsMasterEscape(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function lqsMasterFormatScore(value) {
            const lqs = parseFloat(value);
            if (value === null || value === undefined || value === '' || isNaN(lqs)) {
                return null;
            }
            return Number.isInteger(lqs) ? String(lqs) : lqs.toFixed(1);
        }

        function lqsMasterScoreColor(value) {
            const lqs = parseFloat(value);
            if (isNaN(lqs)) return '#6c757d';
            if (lqs >= 8) return '#28a745';
            if (lqs >= 6) return '#3591dc';
            if (lqs >= 4) return '#ffc107';
            return '#dc3545';
        }

        $(document).ready(function() {
            let table = null;
            let lqsMasterChartInstance = null;
            let lqsMasterChartDays = 32;
            let lqsMasterChartAjax = null;
            let lqsMasterChartChannel = '';
            let lqsMasterChartLabel = '';

            function updateLqsMasterAvgBadge(rows) {
                const source = Array.isArray(rows)
                    ? rows
                    : (table && typeof table.getData === 'function' ? table.getData('active') : []);
                let sum = 0;
                let count = 0;
                source.forEach(function(row) {
                    const value = parseFloat(row && row.lqs);
                    if (!isNaN(value)) {
                        sum += value;
                        count += 1;
                    }
                });
                const $badge = $('#lqs-master-avg-badge');
                if (!count) {
                    $badge.text('Avg Score: –')
                        .removeClass('bg-success bg-info bg-warning bg-danger bg-secondary')
                        .addClass('bg-secondary');
                    return;
                }
                const avg = sum / count;
                let tone = 'bg-danger';
                if (avg >= 8) tone = 'bg-success';
                else if (avg >= 6) tone = 'bg-info';
                else if (avg >= 4) tone = 'bg-warning';
                $badge.text('Avg Score: ' + avg.toFixed(1))
                    .removeClass('bg-success bg-info bg-warning bg-danger bg-secondary')
                    .addClass(tone);
            }

            table = new Tabulator('#lqs-master-table', {
                ajaxURL: '{{ route("lqs.master.data") }}',
                ajaxResponse: function(url, params, response) {
                    if (response && response.status === 200 && Array.isArray(response.data)) {
                        updateLqsMasterAvgBadge(response.data);
                        return response.data;
                    }
                    if (window.toastr) {
                        toastr.error((response && response.message) || 'Failed to load LQS Master channels');
                    }
                    updateLqsMasterAvgBadge([]);
                    return [];
                },
                ajaxRequestError: function() {
                    if (window.toastr) {
                        toastr.error('Failed to load LQS Master channels');
                    }
                    updateLqsMasterAvgBadge([]);
                },
                dataLoaded: function() {
                    updateLqsMasterAvgBadge();
                },
                dataFiltered: function() {
                    updateLqsMasterAvgBadge();
                },
                layout: 'fitDataStretch',
                height: false,
                pagination: false,
                sortMode: 'local',
                filterMode: 'local',
                headerSort: true,
                columns: [
                    {
                        title: 'Image',
                        field: 'logo',
                        frozen: true,
                        width: 60,
                        hozAlign: 'center',
                        headerSort: false,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const logo = cell.getValue();
                            const channel = lqsMasterEscape((row.channel || row['Channel '] || '').trim());
                            const sellerLink = (row.seller_link || '').trim();
                            const imgHtml = logo
                                ? `<img src="/storage/${lqsMasterEscape(logo)}" alt="${channel}" class="channel-logo-thumb" onerror="this.style.display='none'"/>`
                                : `<span class="channel-logo-placeholder" title="No logo"><i class="fas fa-image text-muted"></i></span>`;

                            if (sellerLink) {
                                return `<a href="${lqsMasterEscape(sellerLink)}" target="_blank" rel="noopener noreferrer" title="Open seller page" class="channel-logo-link">${imgHtml}</a>`;
                            }
                            return imgHtml;
                        }
                    },
                    {
                        title: 'Channel',
                        field: 'channel',
                        frozen: true,
                        minWidth: 140,
                        headerSort: true,
                        formatter: function(cell) {
                            const channel = lqsMasterEscape(cell.getValue() || '');
                            const missingLink = (cell.getRow().getData().missing_link || '').trim();
                            if (missingLink) {
                                return `<a href="${lqsMasterEscape(missingLink)}" target="_blank" rel="noopener noreferrer" style="color:inherit;font-weight:inherit;text-decoration:none;">${channel}</a>`;
                            }
                            return `<span>${channel}</span>`;
                        }
                    },
                    {
                        title: 'LQS',
                        field: 'lqs',
                        frozen: true,
                        hozAlign: 'center',
                        width: 90,
                        sorter: 'number',
                        headerSort: true,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const formatted = lqsMasterFormatScore(cell.getValue());
                            const color = formatted ? lqsMasterScoreColor(cell.getValue()) : '#adb5bd';
                            const scoreHtml = formatted
                                ? `<span style="color:${color};font-weight:700;">${formatted}</span>`
                                : '<span style="color:#adb5bd;">–</span>';
                            return `<span class="lqs-master-cell">
                                ${scoreHtml}
                                <span class="lqs-master-chart-dot" data-channel="${lqsMasterEscape(row.channel_key || row.channel || '')}" data-label="${lqsMasterEscape(row.channel || '')}" style="background:${color};" title="LQS history graph"></span>
                            </span>`;
                        }
                    }
                ]
            });

            $('#lqs-master-channel-search').on('keyup', function() {
                const query = ($(this).val() || '').toLowerCase().trim();
                table.clearFilter();
                if (query) {
                    table.addFilter(function(data) {
                        return String(data.channel || data['Channel '] || '').toLowerCase().includes(query);
                    });
                }
                updateLqsMasterAvgBadge();
            });

            $('#refresh-lqs-master-table').on('click', function() {
                table.replaceData();
            });

            function lqsMasterRangeLabel(days) {
                return days === 0 ? 'Lifetime' : ('L' + days);
            }

            function lqsMasterFmt(value) {
                return (Number(value) || 0).toFixed(1);
            }

            function lqsMasterRenderChart(data) {
                const labels = data.map(function(d) { return d.date; });
                const values = data.map(function(d) { return Number(d.value) || 0; });
                const dataMin = Math.min.apply(null, values);
                const dataMax = Math.max.apply(null, values);
                const sorted = values.slice().sort(function(a, b) { return a - b; });
                const mid = Math.floor(sorted.length / 2);
                const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
                const range = dataMax - dataMin || 1;
                const yMin = Math.max(0, dataMin - range * 0.1);
                const yMax = dataMax + range * 0.1;

                document.getElementById('lqsMasterHighest').textContent = lqsMasterFmt(dataMax);
                document.getElementById('lqsMasterMedian').textContent = lqsMasterFmt(median);
                document.getElementById('lqsMasterLowest').textContent = lqsMasterFmt(dataMin);

                const dotColors = values.map(function(v, i) {
                    if (i === 0) return '#6c757d';
                    return v > values[i - 1] ? '#28a745' : (v < values[i - 1] ? '#dc3545' : '#6c757d');
                });
                const labelColors = values.map(function(v) {
                    return v === 0 ? '#198754' : (v > 0 ? '#dc3545' : '#6c757d');
                });

                const medianLinePlugin = {
                    id: 'lqsMasterMedianLine',
                    afterDraw: function(chart) {
                        const yScale = chart.scales.y;
                        const xScale = chart.scales.x;
                        const c = chart.ctx;
                        const yPx = yScale.getPixelForValue(median);
                        c.save();
                        c.setLineDash([6, 4]);
                        c.strokeStyle = '#6c757d';
                        c.lineWidth = 1.2;
                        c.beginPath();
                        c.moveTo(xScale.left, yPx);
                        c.lineTo(xScale.right, yPx);
                        c.stroke();
                        c.restore();
                    }
                };
                const valueLabelsPlugin = {
                    id: 'lqsMasterValueLabels',
                    afterDatasetsDraw: function(chart) {
                        const dataset = chart.data.datasets[0];
                        const meta = chart.getDatasetMeta(0);
                        const c = chart.ctx;
                        c.save();
                        c.font = 'bold 11px Inter, system-ui, sans-serif';
                        c.textAlign = 'center';
                        c.textBaseline = 'bottom';
                        meta.data.forEach(function(point, i) {
                            c.fillStyle = labelColors[i];
                            c.fillText(lqsMasterFmt(dataset.data[i]), point.x, point.y + ((i % 2 === 0) ? -10 : -20));
                        });
                        c.restore();
                    }
                };

                const ctx = document.getElementById('lqsMasterChart').getContext('2d');
                if (lqsMasterChartInstance) {
                    lqsMasterChartInstance.destroy();
                }
                lqsMasterChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'LQS',
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
                            pointBorderWidth: 1.5
                        }]
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
                                    label: function(ctx) {
                                        const i = ctx.dataIndex;
                                        const parts = ['Value: ' + lqsMasterFmt(ctx.raw)];
                                        if (i > 0) {
                                            const diff = ctx.raw - values[i - 1];
                                            parts.push('vs Yesterday: ' + (diff > 0 ? '▲' : (diff < 0 ? '▼' : '▬')) + ' ' + lqsMasterFmt(Math.abs(diff)));
                                        }
                                        if (i >= 7) {
                                            const diff7 = ctx.raw - values[i - 7];
                                            parts.push('vs 7d Ago: ' + (diff7 > 0 ? '▲' : (diff7 < 0 ? '▼' : '▬')) + ' ' + lqsMasterFmt(Math.abs(diff7)));
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
                                ticks: { font: { size: 9 }, callback: function(v) { return lqsMasterFmt(v); } }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    autoSkip: labels.length > 14,
                                    maxTicksLimit: labels.length > 14 ? 14 : labels.length,
                                    font: { size: 8 }
                                }
                            }
                        }
                    }
                });
                $('#lqsMasterChartContainer').show();
            }

            function lqsMasterLoadChart() {
                if (!lqsMasterChartChannel) {
                    return;
                }
                if (lqsMasterChartAjax) {
                    lqsMasterChartAjax.abort();
                }
                $('#lqsMasterChartContainer,#lqsMasterChartNoData').hide();
                $('#lqsMasterChartLoading').show();
                lqsMasterChartAjax = $.ajax({
                    url: '{{ url("/lqs-master/lqs-chart") }}/' + encodeURIComponent(lqsMasterChartChannel),
                    method: 'GET',
                    data: { days: lqsMasterChartDays },
                    success: function(res) {
                        lqsMasterChartAjax = null;
                        $('#lqsMasterChartLoading').hide();
                        const pts = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                        if (pts.length) {
                            lqsMasterRenderChart(pts);
                        } else {
                            $('#lqsMasterChartNoData').show();
                        }
                    },
                    error: function() {
                        lqsMasterChartAjax = null;
                        $('#lqsMasterChartLoading').hide();
                        $('#lqsMasterChartNoData').show();
                    }
                });
            }

            function openLqsMasterChart(channel, label) {
                if (!channel) {
                    return;
                }
                lqsMasterChartChannel = channel;
                lqsMasterChartLabel = label || channel;
                lqsMasterChartDays = 32;
                $('#lqsMasterChartRange').val('32');
                $('#lqsMasterChartTitle').text(lqsMasterChartLabel + ' – LQS (Rolling L32)');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('lqsMasterChartModal')).show();
                lqsMasterLoadChart();
            }

            $(document).on('click', '.lqs-master-chart-dot', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openLqsMasterChart($(this).data('channel'), $(this).data('label'));
            });

            $(document).on('change', '#lqsMasterChartRange', function() {
                const days = parseInt($(this).val(), 10);
                if (days === lqsMasterChartDays) {
                    return;
                }
                lqsMasterChartDays = days;
                $('#lqsMasterChartTitle').text(lqsMasterChartLabel + ' – LQS (Rolling ' + lqsMasterRangeLabel(days) + ')');
                lqsMasterLoadChart();
            });
        });
    </script>
@endsection
