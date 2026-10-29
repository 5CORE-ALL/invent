@extends('layouts.vertical', ['title' => 'Kpi Shipping', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .tabulator {
            border: 1px solid #dee2e6;
            font-size: 12px;
        }

        .tabulator .tabulator-header {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .tabulator .tabulator-header .tabulator-col {
            background: #f8f9fa;
            border-right: 1px solid #e9ecef;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 6px 4px;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-title {
            font-weight: 600;
            color: #212529;
            white-space: nowrap;
        }

        .tabulator .tabulator-row {
            min-height: 32px;
        }

        .tabulator .tabulator-row:nth-child(even) {
            background-color: #fcfcfd;
        }

        .tabulator .tabulator-row:hover {
            background-color: #f1f5ff;
        }

        .tabulator .tabulator-cell {
            padding: 6px 8px;
            border-right: 1px solid #f1f3f5;
            white-space: nowrap;
        }

        .tabulator .tabulator-footer {
            border-top: 1px solid #dee2e6;
            background: #f8f9fa;
        }

        .tabulator-paginator label {
            margin-right: 5px;
        }

        .incentive-bag-icon {
            filter: drop-shadow(0 3px 4px rgba(0, 0, 0, 0.25));
            animation: incentive-bag-bounce 2.2s ease-in-out infinite;
        }

        @keyframes incentive-bag-bounce {
            0%, 100% { transform: translateY(0) rotate(-3deg); }
            50% { transform: translateY(-5px) rotate(3deg); }
        }

        #incentive-badge {
            background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%) !important;
            color: #4a2c00 !important;
            border: none;
        }

        #avg-pct-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .kpi-ship-pct-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            white-space: nowrap;
        }

        .kpi-ship-hist-dot {
            width: 8px;
            height: 8px;
            min-width: 8px;
            border: 0;
            padding: 0;
            border-radius: 50%;
            background: #9ca3af;
            cursor: pointer;
            flex-shrink: 0;
        }

        .kpi-ship-hist-dot.is-up { background: #28a745; }
        .kpi-ship-hist-dot.is-down { background: #dc3545; }
        .kpi-ship-hist-dot.is-flat,
        .kpi-ship-hist-dot.is-none { background: #9ca3af; }
        .kpi-ship-hist-dot:hover { transform: scale(1.35); }

        #incentive-modal .modal-content {
            border: none;
            border-radius: 12px;
            overflow: hidden;
        }

        /* Metric history modal — full width (theme uses --tz-modal-width / --tz-modal-margin) */
        #avg-history-modal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #avg-history-modal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #avg-history-modal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Label Created & Uploaded On time.',
        'sub_title' => 'Shipping KPI overview.',
    ])

    <div class="toast-container"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Label Created & Uploaded On time.</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <span class="badge bg-primary fs-6 p-2" id="avg-pct-badge" style="color: #fff; font-weight: bold; cursor: pointer;" title="Click for On Time % history">
                        <span class="summary-trend-dot none" id="avg-history-dot" role="button" tabindex="0" title="Click for rolling history" aria-label="Avg On Time % history"></span>
                        Avg: <span id="avg-pct-value">0.00%</span>
                    </span>
                    <span class="badge bg-danger fs-6 p-2" id="critical-count-badge" style="color: #fff; font-weight: bold;" title="Channels below 99%">Critical count: <span id="critical-count-value">0</span></span>
                    <span class="badge bg-warning text-dark fs-6 p-2" id="incentive-badge" style="font-weight: bold; cursor: pointer;" title="Incentive">
                        <i class="fas fa-shopping-bag"></i> <i class="fas fa-indian-rupee-sign"></i> Incentive<span id="incentive-summary"></span>
                    </span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="kpi-shipping-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="global-search" class="form-control form-control-sm" placeholder="Search..." style="max-width: 220px;">
                    </div>
                    <div id="kpi-shipping-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="link-modal" tabindex="-1" aria-labelledby="link-modal-label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="link-modal-label">Add / Edit Link</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2 text-muted">Channel: <strong id="link-modal-channel"></strong></p>
                    <label for="link-input" class="form-label">Link URL</label>
                    <input type="url" class="form-control" id="link-input" placeholder="https://example.com/...">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="link-save-btn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="value-modal" tabindex="-1" aria-labelledby="value-modal-label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="value-modal-label">Label Created &amp; Uploaded On Time %</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2 text-muted">Channel: <strong id="value-modal-channel"></strong></p>
                    <label for="value-input" class="form-label">On Time %</label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" max="100" class="form-control" id="value-input" placeholder="e.g. 96.50">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="value-save-btn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade p-0" id="avg-history-modal" tabindex="-1" aria-labelledby="avg-history-modal-label" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;" id="avg-history-modal-label">
                        <i class="fas fa-chart-area me-1"></i>
                        Avg - On Time % (Rolling 30 Days)
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="avg-history-range" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="31">31 Days</option>
                            <option value="32">32 Days</option>
                            <option value="35">35 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="avg-history-chart-wrapper" style="height: 34vh; min-height: 220px; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="avg-history-chart"></canvas>
                        </div>
                        <div style="width: 118px; flex-shrink: 0; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="avg-history-high" style="font-size: 13px; font-weight: 700; color: #dc3545; white-space: nowrap;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="avg-history-median" style="font-size: 13px; font-weight: 700; color: #6c757d; white-space: nowrap;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="avg-history-low" style="font-size: 13px; font-weight: 700; color: #198754; white-space: nowrap;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="avg-history-empty" class="text-center text-muted py-4" style="display: none;">No history data yet.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="incentive-modal" tabindex="-1" aria-labelledby="incentive-modal-label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header border-0 text-white" style="background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%);">
                    <h5 class="modal-title d-flex align-items-center gap-3" id="incentive-modal-label">
                        <span class="incentive-bag-icon fa-stack fa-2x">
                            <i class="fas fa-sack-dollar fa-stack-2x"></i>
                            <i class="fas fa-indian-rupee-sign fa-stack-1x" style="color: #f7971e; margin-top: 2px;"></i>
                        </span>
                        <span>
                            <span class="d-block fw-bold" style="font-size: 1.4rem; text-shadow: 0 1px 2px rgba(0,0,0,0.2);">Incentive</span>
                            <small class="d-block" style="opacity: 0.9;">Reward top performers</small>
                        </span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="incentive-target" class="form-label">Target</label>
                        <input type="text" class="form-control" id="incentive-target" maxlength="100" placeholder="e.g. 99% On Time for the month">
                    </div>
                    <div class="mb-3">
                        <label for="incentive-amount" class="form-label">Amount (Rs)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-indian-rupee-sign"></i></span>
                            <input type="number" step="0.01" min="0" class="form-control" id="incentive-amount" placeholder="e.g. 5000">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="incentive-user" class="form-label">User(s)</label>
                        <select class="form-select" id="incentive-user" multiple>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label for="incentive-condition" class="form-label">Condition</label>
                        <input type="text" class="form-control" id="incentive-condition" maxlength="100" placeholder="Condition (max 100 characters)">
                        <div class="form-text text-end"><span id="incentive-condition-count">0</span>/100</div>
                    </div>
                    <div id="incentive-readonly-note" class="text-muted small" style="display: none;">
                        <i class="fas fa-lock me-1"></i> Only president@5core.com can edit the incentive.
                    </div>

                    <div id="incentive-table-wrapper" class="mt-3" style="display: none;">
                        <hr>
                        <h6 class="mb-2">Incentives by user</h6>
                        <div id="incentive-table"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="incentive-save-btn" style="display: none;">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    let table = null;
    let linkClickTimer = null;
    let linkModalCell = null;
    let valueModalRow = null;
    let incentiveData = { incentives: [], can_edit: false, users: [] };
    let incentiveTable = null;

    function escAttr(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function onTimeHistoryTip(trend, prev, current) {
        const prevMissing = prev === null || prev === undefined || prev === '';
        const nowTxt = (current === null || current === undefined || current === '') ? '—' : (Number(current).toFixed(2) + '%');
        if (trend === 'up' && prevMissing) return 'On Time % history · First day saved';
        if (trend === 'none' || trend === 'na' || prevMissing) return 'On Time % history · No previous day saved yet';
        const prevTxt = Number(prev).toFixed(2) + '%';
        if (trend === 'up') return 'Up vs previous day ' + prevTxt + ' → ' + nowTxt + '. Click for history.';
        if (trend === 'down') return 'Down vs previous day ' + prevTxt + ' → ' + nowTxt + '. Click for history.';
        return 'Same as previous day ' + prevTxt + '. Click for history.';
    }

    function setAvgHistoryDot(trend) {
        const dot = document.getElementById('avg-history-dot');
        if (!dot) return;
        const cls = (trend === 'up' || trend === 'down' || trend === 'flat') ? trend : 'none';
        dot.classList.remove('up', 'down', 'flat', 'none');
        dot.classList.add(cls);
        const labels = {
            up: 'Avg On Time % is up vs the previous day. Click for history.',
            down: 'Avg On Time % is down vs the previous day. Click for history.',
            flat: 'Avg On Time % is unchanged vs the previous day. Click for history.',
            none: 'Click for On Time % history.'
        };
        dot.title = labels[cls];
    }

    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;

        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        table = new Tabulator("#kpi-shipping-table", {
            ajaxURL: "{{ route('kpi.shipping.tabulator.data') }}",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            placeholder: "No data available",
            columnDefaults: {
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle"
            },
            ajaxResponse: function(url, params, response) {
                if (response && !Array.isArray(response)) {
                    setAvgHistoryDot(response.avg_trend);
                }
                return Array.isArray(response) ? response : (response.data || []);
            },
            columns: [
                { title: "Channel", field: "channel", width: 220 },
                {
                    title: "Label Created & Uploaded On Time %",
                    field: "on_time_pct",
                    width: 290,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const raw = cell.getValue();
                        let valueHtml = `<span style="color: #adb5bd;">&mdash;</span>`;
                        if (raw !== null && raw !== undefined && raw !== '') {
                            const value = parseFloat(raw) || 0;
                            const text = value.toFixed(2) + '%';
                            if (value >= 98 && value < 99) {
                                valueHtml = `<span style="background-color: #ffc107; color: #111; font-weight: bold; padding: 2px 6px; border-radius: 4px;">${text}</span>`;
                            } else if (value < 98) {
                                valueHtml = `<span style="background-color: #dc3545; color: #000; font-weight: bold; padding: 2px 6px; border-radius: 4px;">${text}</span>`;
                            } else {
                                valueHtml = `<span style="color: #28a745; font-weight: bold;">${text}</span>`;
                            }
                        }
                        const trend = row.on_time_trend || 'none';
                        const cls = trend === 'up' ? 'is-up' : (trend === 'down' ? 'is-down' : 'is-flat');
                        const tip = onTimeHistoryTip(trend, row.on_time_prev, raw);
                        return `<span class="kpi-ship-pct-cell"><button type="button" class="kpi-ship-hist-dot ${cls}" data-channel="${escAttr(row.channel || '')}" title="${escAttr(tip)}" aria-label="${escAttr(tip)}"></button>${valueHtml}</span>`;
                    }
                },
                {
                    title: "Link",
                    field: "link",
                    width: 90,
                    hozAlign: "center",
                    headerSort: false,
                    tooltip: "Single click: open link · Double click: add / edit link",
                    formatter: function(cell) {
                        const hasLink = !!(cell.getValue() && String(cell.getValue()).trim() !== '');
                        const color = hasLink ? '#0d6efd' : '#adb5bd';
                        return `<i class="fas fa-arrow-up-right-from-square" style="color: ${color}; cursor: pointer; font-size: 15px;"></i>`;
                    },
                    cellClick: function(e, cell) {
                        clearTimeout(linkClickTimer);
                        linkClickTimer = setTimeout(function() {
                            const link = (cell.getValue() || '').toString().trim();
                            if (link === '') {
                                showToast('No link yet. Double-click to add one.', 'info');
                                return;
                            }
                            window.open(link, '_blank', 'noopener');
                        }, 220);
                    },
                    cellDblClick: function(e, cell) {
                        clearTimeout(linkClickTimer);
                        openLinkModal(cell);
                    }
                },
                {
                    title: "Add",
                    field: "add_action",
                    width: 70,
                    hozAlign: "center",
                    headerSort: false,
                    tooltip: "Add / edit On Time % value",
                    formatter: function() {
                        return `<i class="fas fa-plus" style="color: #198754; cursor: pointer; font-size: 15px;"></i>`;
                    },
                    cellClick: function(e, cell) {
                        openValueModal(cell.getRow());
                    }
                },
                {
                    title: "Updated",
                    field: "updated_at_ts",
                    width: 170,
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow, column, dir, sorterParams) {
                        const ad = aRow.getData();
                        const bd = bRow.getData();
                        // Rank: stale (red alert) first, then fresh, then never-entered.
                        const rank = function(d) {
                            if (!d.updated_at_ts) return 2;
                            return d.is_stale ? 0 : 1;
                        };
                        const ra = rank(ad);
                        const rb = rank(bd);
                        if (ra !== rb) return ra - rb;
                        // Within the same rank, oldest first (most overdue on top).
                        return (ad.updated_at_ts || 0) - (bd.updated_at_ts || 0);
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (!d.updated_at_ts) {
                            return `<span style="color: #adb5bd;">&mdash;</span>`;
                        }
                        let html = '';
                        if (d.is_stale) {
                            html += `<i class="fas fa-triangle-exclamation" style="color: #dc3545; margin-right: 6px;" title="Not updated in over 7 days"></i>`;
                        }
                        html += `<span>${d.updated_display || ''}</span>`;
                        return html;
                    }
                },
            ],
            initialSort: [{ column: "updated_at_ts", dir: "asc" }],
        });

        function updateAvgBadge() {
            const data = table.getData("active");
            let sum = 0;
            let count = 0;
            let criticalCount = 0;
            data.forEach(function(row) {
                const value = parseFloat(row.on_time_pct);
                if (!isNaN(value)) {
                    sum += value;
                    count++;
                    if (value < 98) {
                        criticalCount++;
                    }
                }
            });
            const avg = count > 0 ? sum / count : 0;
            $('#avg-pct-value').text(avg.toFixed(2) + '%');
            $('#critical-count-value').text(criticalCount);

            const GREEN = '#28a745';
            const YELLOW = '#ffc107';
            const RED = '#dc3545';

            const criticalEl = document.getElementById('critical-count-badge');
            criticalEl.classList.remove('bg-danger', 'bg-success');
            criticalEl.style.setProperty('background-color', criticalCount === 0 ? GREEN : RED, 'important');

            let avgColor = RED;
            let avgText = '#fff';
            if (avg >= 99) {
                avgColor = GREEN;
            } else if (avg >= 98) {
                avgColor = YELLOW;
                avgText = '#111';
            }
            const avgEl = document.getElementById('avg-pct-badge');
            avgEl.classList.remove('bg-danger', 'bg-success', 'bg-primary');
            avgEl.style.setProperty('background-color', avgColor, 'important');
            avgEl.style.setProperty('color', avgText, 'important');
        }

        table.on('dataLoaded', updateAvgBadge);
        table.on('dataProcessed', updateAvgBadge);
        table.on('dataFiltered', updateAvgBadge);

        $('#global-search').on('keyup', function() {
            const value = $(this).val() || '';
            table.setFilter('channel', 'like', value);
        });

        $('#link-save-btn').on('click', function() {
            if (!linkModalCell) return;

            const row = linkModalCell.getRow();
            const channel = row.getData().channel;
            const link = ($('#link-input').val() || '').trim();
            const $btn = $(this);

            $btn.prop('disabled', true);
            $.ajax({
                url: "{{ route('kpi.shipping.link.save') }}",
                method: 'POST',
                data: { channel: channel, link: link },
                success: function(res) {
                    row.update({
                        link: res.link || null,
                        updated_by: res.updated_by,
                        updated_at_ts: res.updated_at_ts,
                        updated_display: res.updated_display,
                        is_stale: res.is_stale
                    });
                    bootstrap.Modal.getInstance(document.getElementById('link-modal')).hide();
                    showToast('Link saved.', 'success');
                },
                error: function(xhr) {
                    let msg = 'Failed to save link.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    showToast(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });

        $('#value-save-btn').on('click', function() {
            if (!valueModalRow) return;

            const channel = valueModalRow.getData().channel;
            const raw = ($('#value-input').val() || '').trim();
            const $btn = $(this);

            $btn.prop('disabled', true);
            $.ajax({
                url: "{{ route('kpi.shipping.value.save') }}",
                method: 'POST',
                data: { channel: channel, on_time_pct: raw },
                success: function(res) {
                    valueModalRow.update({
                        on_time_pct: res.on_time_pct,
                        on_time_prev: res.on_time_prev,
                        on_time_trend: res.on_time_trend,
                        updated_by: res.updated_by,
                        updated_at_ts: res.updated_at_ts,
                        updated_display: res.updated_display,
                        is_stale: res.is_stale
                    });
                    setAvgHistoryDot(res.avg_trend);
                    bootstrap.Modal.getInstance(document.getElementById('value-modal')).hide();
                    updateAvgBadge();
                    showToast('Value saved.', 'success');
                },
                error: function(xhr) {
                    let msg = 'Failed to save value.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    showToast(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });

        function openAvgHistory() {
            historyChartChannel = '';
            $('#avg-history-range').val('30');
            setHistoryTitle();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('avg-history-modal')).show();
            loadAvgHistory();
        }

        function openChannelHistory(channel) {
            historyChartChannel = channel || '';
            $('#avg-history-range').val('30');
            setHistoryTitle();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('avg-history-modal')).show();
            loadAvgHistory();
        }

        $('#avg-pct-badge').on('click', function(e) {
            if (e.target.closest && e.target.closest('.summary-trend-dot')) return;
            openAvgHistory();
        });

        $('#avg-history-dot').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openAvgHistory();
        });

        $('#avg-history-dot').on('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            e.preventDefault();
            openAvgHistory();
        });

        $(document).on('click', '.kpi-ship-hist-dot', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openChannelHistory($(this).attr('data-channel') || '');
        });

        $('#avg-history-range').on('change', function() {
            setHistoryTitle();
            loadAvgHistory();
        });

        loadIncentive();

        $('#incentive-badge').on('click', function() {
            openIncentiveModal();
        });

        document.getElementById('incentive-modal').addEventListener('shown.bs.modal', function() {
            if (incentiveTable) incentiveTable.redraw(true);
        });

        $('#incentive-condition').on('input', function() {
            $('#incentive-condition-count').text(($(this).val() || '').length);
        });

        $('#incentive-user').on('change', function() {
            const selected = $(this).val() || [];
            // Only auto-load existing values when exactly one user is selected (edit mode).
            if (selected.length !== 1) {
                return;
            }
            const existing = (incentiveData.incentives || []).find(function(it) {
                return String(it.user_id) === String(selected[0]);
            });
            if (!existing) {
                return;
            }
            $('#incentive-target').val(existing.target || '');
            $('#incentive-amount').val(existing.amount !== null && existing.amount !== undefined ? existing.amount : '');
            const cond = existing.condition || '';
            $('#incentive-condition').val(cond);
            $('#incentive-condition-count').text(cond.length);
        });

        $('#incentive-save-btn').on('click', function() {
            const userIds = $('#incentive-user').val() || [];
            if (!userIds.length) {
                showToast('Please select at least one user.', 'error');
                return;
            }
            const target = ($('#incentive-target').val() || '').trim();
            const amount = ($('#incentive-amount').val() || '').trim();
            const condition = ($('#incentive-condition').val() || '').trim();
            const $btn = $(this);

            $btn.prop('disabled', true);
            $.ajax({
                url: "{{ route('kpi.shipping.incentive.save') }}",
                method: 'POST',
                data: { user_ids: userIds, target: target, amount: amount, condition: condition },
                success: function(res) {
                    incentiveData.incentives = res.incentives || [];
                    applyIncentiveToBadge();
                    renderIncentiveTable();
                    showToast('Incentive saved.', 'success');
                },
                error: function(xhr) {
                    let msg = 'Failed to save incentive.';
                    if (xhr.status === 403) msg = 'You are not authorized to edit the incentive.';
                    else if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    showToast(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });
    });

    function openValueModal(row) {
        valueModalRow = row;
        const data = row.getData();
        $('#value-modal-channel').text(data.channel || '');
        $('#value-input').val(data.on_time_pct === null || data.on_time_pct === undefined ? '' : data.on_time_pct);
        const modal = new bootstrap.Modal(document.getElementById('value-modal'));
        modal.show();
    }

    function openLinkModal(cell) {
        linkModalCell = cell;
        const data = cell.getRow().getData();
        $('#link-modal-channel').text(data.channel || '');
        $('#link-input').val((data.link || '').toString());
        const modal = new bootstrap.Modal(document.getElementById('link-modal'));
        modal.show();
    }

    function loadIncentive() {
        $.ajax({
            url: "{{ route('kpi.shipping.incentive') }}",
            method: 'GET',
            success: function(res) {
                incentiveData = res;
                applyIncentiveToBadge();
            }
        });
    }

    function applyIncentiveToBadge() {
        const list = incentiveData.incentives || [];
        let summary = '';
        if (list.length === 1) {
            const it = list[0];
            const amt = (it.amount !== null && it.amount !== undefined) ? '₹' + Number(it.amount).toLocaleString('en-IN') : '';
            summary = ': ' + [amt, it.user_name].filter(Boolean).join(' — ');
        } else if (list.length > 1) {
            summary = ' (' + list.length + ' users)';
        }
        $('#incentive-summary').text(summary);
    }

    function renderIncentiveTable() {
        const list = incentiveData.incentives || [];
        // Only show the user-wise table when more than one user has an incentive.
        if (list.length <= 1) {
            $('#incentive-table-wrapper').hide();
            if (incentiveTable) { incentiveTable.destroy(); incentiveTable = null; }
            return;
        }

        $('#incentive-table-wrapper').show();
        const rows = list.map(function(it) {
            return {
                user: it.user_name,
                target: it.target || '',
                amount: (it.amount !== null && it.amount !== undefined) ? Number(it.amount) : null,
                condition: it.condition || ''
            };
        });

        if (incentiveTable) {
            incentiveTable.replaceData(rows);
            return;
        }

        incentiveTable = new Tabulator("#incentive-table", {
            data: rows,
            layout: "fitColumns",
            height: "220px",
            columns: [
                { title: "User", field: "user", widthGrow: 2 },
                { title: "Target", field: "target", widthGrow: 2 },
                {
                    title: "Amount (Rs)",
                    field: "amount",
                    widthGrow: 1,
                    hozAlign: "right",
                    formatter: function(cell) {
                        const v = cell.getValue();
                        return (v === null || v === undefined) ? '' : '₹' + Number(v).toLocaleString('en-IN');
                    }
                },
                { title: "Condition", field: "condition", widthGrow: 2 },
            ]
        });
    }

    function openIncentiveModal() {
        const $select = $('#incentive-user');

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
        $select.empty();
        (incentiveData.users || []).forEach(function(u) {
            $select.append(`<option value="${u.id}">${u.label}</option>`);
        });
        $select.val(null);

        $select.select2({
            theme: 'bootstrap-5',
            placeholder: 'Search and select user(s)',
            closeOnSelect: false,
            width: '100%',
            dropdownParent: $('#incentive-modal')
        });
        $select.val(null).trigger('change');

        $('#incentive-target').val('');
        $('#incentive-amount').val('');
        $('#incentive-condition').val('');
        $('#incentive-condition-count').text('0');

        const canEdit = !!incentiveData.can_edit;
        $('#incentive-target, #incentive-amount, #incentive-condition').prop('disabled', !canEdit);
        $select.prop('disabled', !canEdit);
        $('#incentive-save-btn').toggle(canEdit);
        $('#incentive-readonly-note').toggle(!canEdit);

        renderIncentiveTable();

        const modal = new bootstrap.Modal(document.getElementById('incentive-modal'));
        modal.show();
    }

    let avgHistoryChart = null;
    let historyChartChannel = '';

    function historyRangeLabel(days) {
        const n = parseInt(days, 10);
        return n ? (n + ' Days') : 'Lifetime';
    }

    function setHistoryTitle() {
        const name = historyChartChannel || 'Avg';
        const range = historyRangeLabel($('#avg-history-range').val());
        const title = document.getElementById('avg-history-modal-label');
        if (!title) return;
        title.textContent = '';
        const icon = document.createElement('i');
        icon.className = 'fas fa-chart-area me-1';
        title.appendChild(icon);
        title.appendChild(document.createTextNode(name + ' - On Time % (Rolling ' + range + ')'));
    }

    function fmtOnTimePct(value) {
        const n = Number(value);
        return (isFinite(n) ? n.toFixed(2) : '0.00') + '%';
    }

    function onTimeDotColors(values) {
        const gray = '#6c757d';
        const green = '#28a745';
        const red = '#dc3545';
        return values.map(function(v, i) {
            if (i === 0) return gray;
            if (Math.abs(v - values[i - 1]) <= 0.005) return gray;
            return v > values[i - 1] ? green : red;
        });
    }

    function loadAvgHistory() {
        const days = $('#avg-history-range').val();
        const channel = historyChartChannel;
        const data = { days: days };
        if (channel) data.channel = channel;

        $('#avg-history-chart-wrapper').hide();
        $('#avg-history-empty').hide();

        $.ajax({
            url: channel
                ? "{{ route('kpi.shipping.value.history') }}"
                : "{{ route('kpi.shipping.avg.history') }}",
            method: 'GET',
            data: data,
            success: function(response) {
                if (response.success && response.data && response.data.length > 0) {
                    $('#avg-history-chart-wrapper').show();
                    $('#avg-history-empty').hide();
                    renderAvgHistoryChart(response.data);
                } else {
                    if (avgHistoryChart) { avgHistoryChart.destroy(); avgHistoryChart = null; }
                    $('#avg-history-chart-wrapper').hide();
                    $('#avg-history-empty').show();
                }
            },
            error: function() {
                showToast('Failed to load history.', 'error');
            }
        });
    }

    function renderAvgHistoryChart(data) {
        const canvas = document.getElementById('avg-history-chart');
        const ctx = canvas.getContext('2d');
        if (avgHistoryChart) avgHistoryChart.destroy();

        const labels = data.map(function(d) { return d.date; });
        const values = data.map(function(d) { return Number(d.value); });
        const dotColors = onTimeDotColors(values);

        const sorted = values.slice().sort(function(a, b) { return a - b; });
        const mid = Math.floor(sorted.length / 2);
        const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
        const dataMin = Math.min.apply(null, values);
        const dataMax = Math.max.apply(null, values);
        const range = dataMax - dataMin || 1;
        const yPad = Math.max(range * 0.28, Math.abs(dataMax) * 0.08, range * 0.1);
        const yMin = Math.max(0, dataMin - range * 0.12);
        const yMax = dataMax + yPad;

        let maxIdx = 0;
        let minIdx = 0;
        values.forEach(function(v, i) {
            if (v >= values[maxIdx]) maxIdx = i;
            if (v <= values[minIdx]) minIdx = i;
        });
        const refGray = '#6c757d';
        const highestEl = document.getElementById('avg-history-high');
        const medianEl = document.getElementById('avg-history-median');
        const lowestEl = document.getElementById('avg-history-low');
        highestEl.textContent = fmtOnTimePct(dataMax);
        highestEl.style.color = dotColors[maxIdx] || refGray;
        if (highestEl.previousElementSibling) highestEl.previousElementSibling.style.color = highestEl.style.color;
        medianEl.textContent = fmtOnTimePct(median);
        medianEl.style.color = refGray;
        if (medianEl.previousElementSibling) medianEl.previousElementSibling.style.color = refGray;
        lowestEl.textContent = fmtOnTimePct(dataMin);
        lowestEl.style.color = dotColors[minIdx] || refGray;
        if (lowestEl.previousElementSibling) lowestEl.previousElementSibling.style.color = lowestEl.style.color;

        const medianLinePlugin = {
            id: 'medianLine',
            afterDraw: function(chart) {
                const yScale = chart.scales.y;
                const xScale = chart.scales.x;
                const draw = chart.ctx;
                const yPixel = yScale.getPixelForValue(median);
                draw.save();
                draw.setLineDash([6, 4]);
                draw.strokeStyle = '#6c757d';
                draw.lineWidth = 1.2;
                draw.beginPath();
                draw.moveTo(xScale.left, yPixel);
                draw.lineTo(xScale.right, yPixel);
                draw.stroke();
                draw.restore();
            }
        };

        const valueLabelsPlugin = {
            id: 'valueLabels',
            afterDraw: function(chart) {
                const dataset = chart.data.datasets[0];
                const meta = chart.getDatasetMeta(0);
                const draw = chart.ctx;
                const lastIdx = meta.data.length - 1;
                const anchors = [];
                const angle = Math.PI / 5;
                const sinA = Math.sin(angle);
                const cosA = Math.cos(angle);
                const minGap = 13;

                draw.save();
                draw.font = 'bold 10px Inter, system-ui, sans-serif';
                draw.textAlign = 'left';
                draw.textBaseline = 'middle';

                meta.data.forEach(function(point, i) {
                    const text = fmtOnTimePct(dataset.data[i]);
                    const textW = draw.measureText(text).width;
                    let offsetY = (i % 2 === 0) ? -12 : -26;
                    if (i === lastIdx) offsetY = (lastIdx % 2 === 0) ? -26 : -12;

                    const sx = point.x;
                    const sy = point.y + offsetY;
                    for (let k = anchors.length - 1; k >= Math.max(0, anchors.length - 3); k--) {
                        const prev = anchors[k];
                        const dx = sx - prev.x;
                        const dy = sy - prev.y;
                        const along = dx * cosA - dy * sinA;
                        if (!(along < prev.w && along + textW > 0)) continue;
                        const dist = dx * sinA + dy * cosA;
                        if (Math.abs(dist) >= minGap) continue;
                        offsetY += (dist >= 0 ? -1 : 1) * ((minGap - Math.abs(dist)) / cosA);
                        break;
                    }
                    anchors.push({ x: point.x, y: point.y + offsetY, w: textW });

                    draw.save();
                    draw.fillStyle = dotColors[i] || refGray;
                    draw.translate(point.x, point.y + offsetY);
                    draw.rotate(-angle);
                    draw.fillText(text, 2, 0);
                    draw.restore();
                });
                draw.restore();
            }
        };

        ctx.save();
        ctx.font = 'bold 10px Inter, system-ui, sans-serif';
        const tailLabelWidth = Math.max.apply(null, values.slice(-2).map(function(v) {
            return ctx.measureText(fmtOnTimePct(v)).width;
        }));
        ctx.restore();
        const chartRightPad = Math.max(22, Math.ceil(tailLabelWidth * Math.cos(Math.PI / 5)) + 12);

        avgHistoryChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'On Time %',
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
                    pointHoverBackgroundColor: dotColors,
                    pointHoverBorderColor: dotColors,
                    pointBorderWidth: 1.5
                }]
            },
            plugins: [medianLinePlugin, valueLabelsPlugin],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                clip: false,
                layout: { padding: { top: 44, left: 4, right: chartRightPad, bottom: 8 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            labelColor: function(context) {
                                const color = dotColors[context.dataIndex] || refGray;
                                return { borderColor: color, backgroundColor: color, borderWidth: 2, borderRadius: 8 };
                            },
                            label: function(context) {
                                const idx = context.dataIndex;
                                const parts = ['Value: ' + fmtOnTimePct(context.raw)];
                                if (idx > 0) {
                                    const diff = context.raw - values[idx - 1];
                                    const arrow = diff < 0 ? '▼' : diff > 0 ? '▲' : '▬';
                                    parts.push('vs Yesterday: ' + arrow + ' ' + fmtOnTimePct(Math.abs(diff)));
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
                            callback: function(value) { return fmtOnTimePct(value); }
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 60,
                            minRotation: 60,
                            autoSkip: false,
                            font: { size: 8 }
                        }
                    }
                }
            }
        });
    }
</script>
@endsection
