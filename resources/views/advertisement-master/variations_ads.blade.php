@php
    $pageTitle = 'Variations Ads';
    $pageSubtitle = 'Advertisement Master';
@endphp

@extends('layouts.vertical', ['title' => $pageTitle, 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .va-stat-badge {
            display: inline-block;
            flex-shrink: 0;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 6px;
            white-space: nowrap;
            line-height: 1.2;
        }
        .va-stat-badge--parents { background: #1971c2; }

        /* Per-channel checked / unchecked count badge. */
        .va-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: #fff;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
            cursor: pointer;
        }
        .va-count-badge:hover { background: #f8fafc; border-color: #cbd5e1; }
        .va-count-badge .va-count-badge-label { color: #334155; }
        .va-count-badge .va-count-chk,
        .va-count-badge .va-count-unchk {
            display: inline-flex;
            align-items: center;
            min-width: 20px;
            justify-content: center;
            padding: 1px 6px;
            border-radius: 10px;
            color: #fff;
            font-weight: 700;
        }
        .va-count-badge .va-count-chk { background: #16a34a; }
        .va-count-badge .va-count-unchk { background: #dc2626; }

        #variations-ads-wrap {
            overflow-x: auto;
            overflow-y: visible;
        }
        #variations-ads-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 12px;
        }
        #variations-ads-wrap .tabulator .tabulator-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        #variations-ads-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            font-size: 12px;
            font-weight: 600;
            white-space: normal;
        }
        #variations-ads-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 4px 6px !important;
        }

        /* Data-tree expand/collapse control (+ / −), same look as /shopify-ads-master. */
        #variations-ads-wrap .tabulator .tabulator-data-tree-control {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            margin-right: 6px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            color: #334155;
            cursor: pointer;
            vertical-align: middle;
            flex-shrink: 0;
        }
        #variations-ads-wrap .tabulator .tabulator-data-tree-control:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }
        #variations-ads-wrap .tabulator .tabulator-data-tree-control-expand::after {
            content: '+';
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
        }
        #variations-ads-wrap .tabulator .tabulator-data-tree-control-collapse::after {
            content: '−';
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
        }
        #variations-ads-wrap .tabulator-row.va-child-row .tabulator-cell {
            background: #f8fafc;
            color: #475569;
        }
        #variations-ads-wrap .tabulator-row.va-child-row:hover .tabulator-cell {
            background: #f1f5f9;
        }

        /* Channel toggle: green check when on, red cross when off (no blank state). */
        #variations-ads-wrap .va-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            line-height: 1;
            user-select: none;
        }
        #variations-ads-wrap .va-toggle-on { background: #16a34a; }
        #variations-ads-wrap .va-toggle-off { background: #dc2626; }
        #variations-ads-wrap .va-toggle:hover { filter: brightness(1.08); }

        /* Clickable channel headers open that column's trend graph. */
        #variations-ads-wrap .tabulator .tabulator-col.va-graph-col {
            cursor: pointer;
        }
        #variations-ads-wrap .tabulator .tabulator-col.va-graph-col .va-col-graph {
            margin-left: 4px;
            color: #16a34a;
        }

        /* Full-width trend modal pinned to the top, same look as /shopify-ads-master. */
        #vaTrendsModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #vaTrendsModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #vaTrendsModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $pageTitle,
        'sub_title' => $pageSubtitle,
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 flex-grow-1 py-1" style="min-width:0;">
                            <span class="va-stat-badge va-stat-badge--parents" title="Number of parent rows in the current view">PARENTS: <span id="va-badge-parents">0</span></span>
                            <span class="va-count-badge" data-col="amz_kw" title="Amz KW — checked vs unchecked (click for trend)">
                                <span class="va-count-badge-label">Amz KW</span>
                                <span class="va-count-chk" id="va-chk-amz_kw">0</span>
                                <span class="va-count-unchk" id="va-unchk-amz_kw">0</span>
                            </span>
                            <span class="va-count-badge" data-col="amz_pt" title="Amz PT — checked vs unchecked (click for trend)">
                                <span class="va-count-badge-label">Amz PT</span>
                                <span class="va-count-chk" id="va-chk-amz_pt">0</span>
                                <span class="va-count-unchk" id="va-unchk-amz_pt">0</span>
                            </span>
                            <span class="va-count-badge" data-col="ebay2" title="Ebay 2 — checked vs unchecked (click for trend)">
                                <span class="va-count-badge-label">Ebay 2</span>
                                <span class="va-count-chk" id="va-chk-ebay2">0</span>
                                <span class="va-count-unchk" id="va-unchk-ebay2">0</span>
                            </span>
                            <span class="va-count-badge" data-col="google_shop" title="Google Shop — checked vs unchecked (click for trend)">
                                <span class="va-count-badge-label">Google Shop</span>
                                <span class="va-count-chk" id="va-chk-google_shop">0</span>
                                <span class="va-count-unchk" id="va-unchk-google_shop">0</span>
                            </span>
                        </div>
                        <input type="text" id="va-search" class="form-control form-control-sm"
                            placeholder="Search parent / SKU…" style="width:200px; flex-shrink:0;">
                    </div>

                    <div id="variations-ads-wrap">
                        <div id="variations-ads-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Per-column date-wise green-count trend graph (opened from a column header) --}}
    <div class="modal fade p-0" id="vaTrendsModal" tabindex="-1" aria-labelledby="vaTrendsLabel" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content">
                <div class="modal-header py-2" style="background:#0d6efd;color:#fff;">
                    <h6 class="modal-title fw-bold" id="vaTrendsLabel">
                        <i class="fa fa-chart-line me-1"></i>
                        <span id="va-trend-title">Trend</span>
                    </h6>
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <select id="va-trend-days" class="form-select form-select-sm" style="width:110px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-0">
                    <div class="d-flex">
                        <div style="flex:1; min-height:340px; padding:8px;">
                            <canvas id="va-trend-canvas"></canvas>
                            <p class="text-center text-muted small mb-0 d-none" id="va-trend-empty">No history yet for this channel. Toggle a checkbox to start recording.</p>
                        </div>
                        <div style="width:130px; border-left:1px solid #dee2e6; padding:14px 10px; text-align:center;">
                            <div class="small text-uppercase fw-bold" style="color:#dc3545;">High</div>
                            <div class="fs-5 fw-bold" id="va-trend-highest">—</div>
                            <hr class="my-2">
                            <div class="small text-uppercase fw-bold" style="color:#6c757d;">Med</div>
                            <div class="fs-5 fw-bold" id="va-trend-median">—</div>
                            <hr class="my-2">
                            <div class="small text-uppercase fw-bold" style="color:#198754;">Low</div>
                            <div class="fs-5 fw-bold" id="va-trend-lowest">—</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var dataUrl = "{{ route('advertisement.variations.ads.data') }}";
            var saveUrl = "{{ route('advertisement.variations.ads.save') }}";
            var historyUrl = "{{ route('advertisement.variations.ads.history') }}";
            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

            // Channel columns: key => label + colour (for the graph).
            var CHANNELS = [
                { key: 'amz_kw', label: 'Amz KW', color: '#4c7ed8' },
                { key: 'amz_pt', label: 'Amz PT', color: '#f59e0b' },
                { key: 'ebay2', label: 'Ebay 2', color: '#db2777' },
                { key: 'google_shop', label: 'Google Shop', color: '#16a34a' }
            ];

            function esc(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function nameFormatter(cell) {
                var isChild = !!cell.getRow().getTreeParent();
                var weight = isChild ? 'font-weight:500;' : 'font-weight:700;';
                return '<span style="' + weight + '">' + esc(cell.getValue()) + '</span>';
            }

            // Toggle cell: green check (on) or red cross (off). Default = on/green.
            function checkFormatter(colKey) {
                return function (cell) {
                    var checked = cell.getValue() !== false; // default true / green
                    var cls = checked ? 'va-toggle-on' : 'va-toggle-off';
                    var sym = checked ? '\u2713' : '\u2717'; // ✓ / ✗
                    var title = checked ? 'On — click to cross' : 'Off — click to check';
                    return '<span class="va-toggle ' + cls + '" data-col="' + colKey + '" role="button" title="' + title + '">' + sym + '</span>';
                };
            }

            function saveFlag(sku, colKey, checked) {
                return fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ sku: sku, col_key: colKey, checked: checked ? 1 : 0 })
                });
            }

            // Persist on toggle and keep the row's data in sync.
            function checkCellClick(colKey) {
                return function (e, cell) {
                    var el = e.target && e.target.closest ? e.target.closest('.va-toggle') : null;
                    if (!el) { return; }
                    var data = cell.getRow().getData();
                    var current = cell.getValue() !== false;
                    var checked = !current;
                    cell.setValue(checked, true); // re-renders the toggle (check <-> cross)
                    if (vaFlatBySku[data.sku]) { vaFlatBySku[data.sku][colKey] = checked; }
                    updateBadges();
                    saveFlag(data.sku, colKey, checked).catch(function () {
                        cell.setValue(current, true);
                        if (vaFlatBySku[data.sku]) { vaFlatBySku[data.sku][colKey] = current; }
                        updateBadges();
                    });
                };
            }

            function checkColumn(ch) {
                return {
                    title: ch.label, field: ch.key, hozAlign: 'center', headerHozAlign: 'center', width: 110,
                    headerSort: false, cssClass: 'va-graph-col',
                    titleFormatter: function () {
                        return esc(ch.label) + ' <i class="fa fa-chart-line va-col-graph" title="Show ' + esc(ch.label) + ' trend"></i>';
                    },
                    headerClick: function (e, column) { openColumnTrend(ch); },
                    formatter: checkFormatter(ch.key), cellClick: checkCellClick(ch.key)
                };
            }

            // Flat map of every row (parent + child) by sku, for live checked/unchecked counts.
            var vaFlatBySku = {};

            function flatten(rows) {
                vaFlatBySku = {};
                (function walk(list) {
                    (list || []).forEach(function (r) {
                        if (!r) { return; }
                        vaFlatBySku[r.sku] = r;
                        if (Array.isArray(r._children)) { walk(r._children); }
                    });
                })(rows);
            }

            function updateBadges() {
                var parents = table.getData('active').filter(function (r) { return r && r.is_parent; });
                document.getElementById('va-badge-parents').textContent = Number(parents.length).toLocaleString('en-US');

                // Checked / unchecked per channel across all rows (default = checked/green).
                CHANNELS.forEach(function (ch) {
                    var chk = 0, unchk = 0;
                    Object.keys(vaFlatBySku).forEach(function (sku) {
                        if (vaFlatBySku[sku][ch.key] === false) { unchk++; } else { chk++; }
                    });
                    var c = document.getElementById('va-chk-' + ch.key);
                    var u = document.getElementById('va-unchk-' + ch.key);
                    if (c) { c.textContent = Number(chk).toLocaleString('en-US'); }
                    if (u) { u.textContent = Number(unchk).toLocaleString('en-US'); }
                });
            }

            var columns = [
                { title: 'Parent / SKU', field: 'name', minWidth: 240, widthGrow: 3, headerSort: true, tooltip: true, formatter: nameFormatter }
            ];
            CHANNELS.forEach(function (ch) { columns.push(checkColumn(ch)); });

            var table = new Tabulator('#variations-ads-table', {
                ajaxURL: dataUrl,
                ajaxResponse: function (url, params, response) {
                    var rows = (response && Array.isArray(response.data)) ? response.data : (response || []);
                    flatten(rows);
                    return rows;
                },
                index: 'sku',
                layout: 'fitColumns',
                height: 'calc(100vh - 220px)',
                headerSort: true,
                dataTree: true,
                dataTreeStartExpanded: false,
                dataTreeChildField: '_children',
                dataTreeFilter: true,
                placeholder: 'No data',
                rowFormatter: function (row) {
                    if (row.getTreeParent()) {
                        row.getElement().classList.add('va-child-row');
                    }
                },
                columns: columns
            });

            table.on('dataProcessed', updateBadges);
            table.on('dataFiltered', updateBadges);

            document.getElementById('va-search').addEventListener('input', function () {
                var q = this.value.trim();
                if (q === '') {
                    table.clearFilter();
                } else {
                    table.setFilter('name', 'like', q);
                }
            });

            // Clicking a count badge opens that channel's trend graph.
            document.querySelectorAll('.va-count-badge').forEach(function (badge) {
                badge.addEventListener('click', function () {
                    var key = badge.getAttribute('data-col');
                    var ch = CHANNELS.filter(function (c) { return c.key === key; })[0];
                    if (ch) { openColumnTrend(ch); }
                });
            });

            // ── Per-column trend graph (opened by clicking a channel header) ──
            var vaTrendChart = null;
            var vaTrendChannel = null;

            function setTrendTitle() {
                var days = parseInt(document.getElementById('va-trend-days').value || '30', 10);
                var titleEl = document.getElementById('va-trend-title');
                if (titleEl && vaTrendChannel) { titleEl.textContent = vaTrendChannel.label + ' - Green (Rolling L' + days + ')'; }
            }

            function openColumnTrend(ch) {
                vaTrendChannel = ch;
                setTrendTitle();
                var modalEl = document.getElementById('vaTrendsModal');
                if (window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                loadTrend();
            }

            function loadTrend() {
                if (!vaTrendChannel) { return; }
                setTrendTitle();
                var days = parseInt(document.getElementById('va-trend-days').value || '30', 10);
                fetch(historyUrl + '?days=' + encodeURIComponent(days), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (payload) { renderTrend(payload); })
                    .catch(function () { renderTrend({ labels: [], series: {} }); });
            }

            function renderTrend(payload) {
                var canvas = document.getElementById('va-trend-canvas');
                var emptyEl = document.getElementById('va-trend-empty');
                var labels = (payload && payload.labels) || [];
                var series = (payload && payload.series) || {};
                var seriesUnchk = (payload && payload.series_unchecked) || {};
                var rawChecked = (vaTrendChannel && series[vaTrendChannel.key]) || [];
                var rawUnchecked = (vaTrendChannel && seriesUnchk[vaTrendChannel.key]) || [];
                // Keep missing days as null (don't force to 0) so the line only spans real snapshots.
                var values = rawChecked.map(function (v) { return (v === null || v === undefined) ? null : Number(v); });
                var uncheckedVals = rawUnchecked.map(function (v) { return (v === null || v === undefined) ? null : Number(v); });
                var checkedNums = values.filter(function (v) { return v !== null; });

                if (vaTrendChart) { vaTrendChart.destroy(); vaTrendChart = null; }
                ['va-trend-highest', 'va-trend-median', 'va-trend-lowest'].forEach(function (id) {
                    var el = document.getElementById(id); if (el) { el.textContent = '—'; }
                });

                var hasData = labels.length && rawChecked.some(function (v) { return v !== null && v !== undefined; });
                if (!hasData || typeof Chart === 'undefined') {
                    canvas.style.display = 'none';
                    emptyEl.classList.remove('d-none');
                    return;
                }
                canvas.style.display = '';
                emptyEl.classList.add('d-none');

                var refRed = '#dc3545', refGray = '#6c757d', refGreen = '#198754';

                var dataMin = checkedNums.length ? Math.min.apply(null, checkedNums) : 0;
                var dataMax = checkedNums.length ? Math.max.apply(null, checkedNums) : 0;
                var sorted = checkedNums.slice().sort(function (a, b) { return a - b; });
                var mid = Math.floor(sorted.length / 2);
                var median = sorted.length === 0 ? 0 : (sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2);

                // Side panel reflects the Checked series: high red, low green, median grey.
                var setStat = function (id, v) {
                    var el = document.getElementById(id); if (!el) { return; }
                    el.textContent = Math.round(v).toLocaleString('en-US');
                    el.style.color = (v === 0) ? refGreen : (v > 0 ? refRed : refGray);
                };
                setStat('va-trend-highest', dataMax);
                setStat('va-trend-median', median);
                setStat('va-trend-lowest', dataMin);

                var medianLinePlugin = {
                    id: 'medianLine',
                    afterDraw: function (chart) {
                        var yScale = chart.scales.y, xScale = chart.scales.x, ctx = chart.ctx;
                        var yPixel = yScale.getPixelForValue(median);
                        ctx.save();
                        ctx.setLineDash([6, 4]);
                        ctx.strokeStyle = '#6c757d';
                        ctx.lineWidth = 1.2;
                        ctx.beginPath();
                        ctx.moveTo(xScale.left, yPixel);
                        ctx.lineTo(xScale.right, yPixel);
                        ctx.stroke();
                        ctx.restore();
                    }
                };
                // Value labels: Checked above its points (green), Unchecked below its points (red).
                var valueLabelsPlugin = {
                    id: 'valueLabels',
                    afterDatasetsDraw: function (chart) {
                        var ctx = chart.ctx;
                        ctx.save();
                        ctx.font = 'bold 11px Inter, system-ui, sans-serif';
                        ctx.textAlign = 'center';
                        var chkMeta = chart.getDatasetMeta(0);
                        ctx.textBaseline = 'bottom';
                        ctx.fillStyle = '#16a34a';
                        chkMeta.data.forEach(function (point, i) {
                            if (values[i] === null || values[i] === undefined) { return; }
                            var offY = (i % 2 === 0) ? -8 : -18;
                            ctx.fillText(Math.round(values[i]).toLocaleString('en-US'), point.x, point.y + offY);
                        });
                        var unMeta = chart.getDatasetMeta(1);
                        ctx.textBaseline = 'top';
                        ctx.fillStyle = '#dc2626';
                        unMeta.data.forEach(function (point, i) {
                            if (uncheckedVals[i] === null || uncheckedVals[i] === undefined) { return; }
                            var offY = (i % 2 === 0) ? 8 : 18;
                            ctx.fillText(Math.round(uncheckedVals[i]).toLocaleString('en-US'), point.x, point.y + offY);
                        });
                        ctx.restore();
                    }
                };

                vaTrendChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Checked',
                                data: values,
                                yAxisID: 'y',
                                backgroundColor: 'rgba(22,163,74,0.08)',
                                borderColor: '#16a34a',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3,
                                spanGaps: true,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                pointBackgroundColor: '#16a34a',
                                pointBorderColor: '#16a34a'
                            },
                            {
                                label: 'Unchecked',
                                data: uncheckedVals,
                                yAxisID: 'y1',
                                backgroundColor: 'rgba(220,38,38,0.05)',
                                borderColor: '#dc2626',
                                borderWidth: 2,
                                fill: false,
                                tension: 0.3,
                                spanGaps: true,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                pointBackgroundColor: '#dc2626',
                                pointBorderColor: '#dc2626'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 24, right: 16, bottom: 12, left: 16 } },
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: true, position: 'bottom' },
                            tooltip: { callbacks: { label: function (ctx) { return ctx.dataset.label + ': ' + Math.round(ctx.parsed.y).toLocaleString('en-US'); } } }
                        },
                        scales: {
                            y: {
                                beginAtZero: true, position: 'left',
                                title: { display: true, text: 'Checked', color: '#16a34a' },
                                ticks: { callback: function (v) { return Math.round(v).toLocaleString('en-US'); } }
                            },
                            y1: {
                                beginAtZero: true, position: 'right',
                                title: { display: true, text: 'Unchecked', color: '#dc2626' },
                                grid: { drawOnChartArea: false },
                                ticks: { callback: function (v) { return Math.round(v).toLocaleString('en-US'); } }
                            },
                            x: { ticks: { autoSkip: false, maxRotation: 60, minRotation: 45 } }
                        }
                    },
                    plugins: [medianLinePlugin, valueLabelsPlugin]
                });
            }

            document.getElementById('va-trend-days').addEventListener('change', loadTrend);
            document.getElementById('vaTrendsModal').addEventListener('shown.bs.modal', function () {
                if (vaTrendChart) { vaTrendChart.resize(); }
            });
        });
    </script>
@endsection
