@extends('layouts.vertical', ['title' => 'LQS Amz', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tabulator { border: 1px solid #dee2e6; border-radius: 8px; font-size: 12px; }
        .tabulator .tabulator-header { background: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl; text-orientation: mixed; transform: rotate(180deg);
            white-space: nowrap; height: 78px; display: flex; align-items: center;
            justify-content: center; font-size: 11px; font-weight: 600;
        }
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        .tabulator .tabulator-row { min-height: 50px; }

        /* ── Parent row ── */
        .tabulator-row.amz-lqs-parent-row,
        .tabulator-row.amz-lqs-parent-row .tabulator-cell {
            background-color: #fff3cd !important;
            font-weight: 700 !important;
            min-height: 48px !important;
        }
        .tabulator-row.amz-lqs-parent-row .tabulator-cell {
            min-height: 48px !important; height: 48px !important;
            padding-top: 8px !important; padding-bottom: 8px !important;
            overflow: visible !important; vertical-align: middle !important;
            color: #664d03;
        }
        .tabulator-row.amz-lqs-parent-row:hover,
        .tabulator-row.amz-lqs-parent-row:hover .tabulator-cell {
            background-color: #ffe69c !important;
        }

        /* ── Modern pagination ── */
        .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important;
            min-width: 36px !important; height: 36px !important; line-height: 36px !important;
            padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important;
            color: #475569 !important; cursor: pointer; transition: all 0.15s ease !important;
            text-align: center !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #ff9900 !important; border-color: #ff9900 !important;
            color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(255,153,0,0.35) !important;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }

        /* ── DIL dropdown ── */
        .amz-lqs-dropdown { position: relative; display: inline-block; }
        .amz-lqs-dropdown .dropdown-menu {
            position: absolute; top: 100%; left: 0; z-index: 1050;
            display: none; min-width: 200px; padding: .5rem 0; margin: 0;
            background: #fff; border: 1px solid #dee2e6; border-radius: .375rem;
            box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
        }
        .amz-lqs-dropdown.show .dropdown-menu { display: block; }
        .amz-dil-item {
            display: block; width: 100%; padding: .5rem 1rem; clear: both;
            font-weight: 400; color: #212529; text-decoration: none;
            background: transparent; border: 0; cursor: pointer; white-space: nowrap;
        }
        .amz-dil-item:hover { background: #e9ecef; }

        /* ── Status circles ── */
        .amz-sc { display:inline-block; width:12px; height:12px; border-radius:50%; margin-right:6px; border:1px solid #ddd; }
        .amz-sc.def    { background:#6c757d; }
        .amz-sc.red    { background:#dc3545; }
        .amz-sc.yellow { background:#ffc107; }
        .amz-sc.green  { background:#28a745; }
        .amz-sc.pink   { background:#e83e8c; }

        /* Summary badges */
        #amz-summary-stats .amz-badge-row {
            display: flex; flex-wrap: nowrap; align-items: stretch;
            gap: clamp(0.2rem, 0.5vw, 0.45rem); width: 100%;
            overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: thin;
        }
        #amz-summary-stats .amz-badge-row > .badge {
            flex: 1 1 0; min-width: 0;
            font-size: clamp(0.62rem, 0.35rem + 0.85vw, 1.05rem);
            padding: clamp(0.28rem, 0.4vw, 0.5rem) clamp(0.2rem, 0.5vw, 0.5rem);
            font-weight: bold; box-sizing: border-box;
            display: inline-flex; align-items: center; justify-content: center;
            text-align: center; white-space: nowrap;
        }

        /* Amazon orange accent */
        .btn-amz-orange { background: #ff9900; border-color: #e88e00; color: #fff; }
        .btn-amz-orange:hover { background: #e88e00; color: #fff; }

        /* ── Action dot ── */
        .amz-action-dot {
            display: inline-block; width: 14px; height: 14px; border-radius: 50%;
            cursor: pointer; border: 2px solid rgba(0,0,0,0.15);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .amz-action-dot:hover { transform: scale(1.3); box-shadow: 0 0 6px rgba(0,0,0,0.25); }
        .amz-action-dot.no-action  { background: #dc3545; }
        .amz-action-dot.has-action { background: #28a745; }

        /* ── History cell ── */
        .amz-history-cell {
            font-size: 10px; line-height: 1.35; max-width: 160px;
            overflow: hidden; cursor: pointer;
        }
        .amz-history-cell .amz-hist-user { font-weight: 700; color: #495057; }
        .amz-history-cell .amz-hist-date { color: #6c757d; }
        .amz-history-cell .amz-hist-text { color: #212529; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px; display: block; }
        .amz-history-cell .amz-hist-more { color: #ff9900; font-weight: 600; font-size: 9px; }

        /* ── History modal entries ── */
        .amz-hist-entry {
            border-left: 3px solid #ff9900; padding: 6px 10px;
            margin-bottom: 8px; background: #fffdf8; border-radius: 0 4px 4px 0;
        }
        .amz-hist-entry:last-child { margin-bottom: 0; }
        .amz-hist-entry .amz-he-meta { font-size: 10px; color: #6c757d; margin-bottom: 2px; }
        .amz-hist-entry .amz-he-text { font-size: 12px; color: #212529; font-weight: 500; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'LQS Amz',
        'sub_title'  => 'Amazon Listing Quality Score – Parent SKU, ASIN, Sessions, Units & LQS metrics',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    {{-- ── Filter bar ── --}}
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">

                        {{-- Row type filter --}}
                        <select id="amz-row-type-filter" class="form-select form-select-sm" style="width:120px;">
                            <option value="all" selected>All Rows</option>
                            <option value="parents">Parents</option>
                            <option value="skus">SKUs</option>
                        </select>

                        {{-- Inventory filter --}}
                        <select id="amz-inv-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">All Inventory</option>
                            <option value="zero">0 Inventory</option>
                            <option value="more" selected>More than 0</option>
                        </select>

                        {{-- LQS filter --}}
                        <select id="amz-score-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">All LQS</option>
                            <option value="8-10">High (8-10)</option>
                            <option value="6-7">Good (6-7)</option>
                            <option value="4-5">Medium (4-5)</option>
                            <option value="1-3">Low (1-3)</option>
                            <option value="missing">No LQS</option>
                        </select>

                        {{-- DIL% dropdown --}}
                        <div class="amz-lqs-dropdown">
                            <button class="btn btn-light btn-sm amz-dil-toggle" type="button" id="amz-dil-btn">
                                <span class="amz-sc def"></span>DIL%
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="amz-dil-item active" href="#" data-color="all">
                                    <span class="amz-sc def"></span>All DIL</a></li>
                                <li><a class="amz-dil-item" href="#" data-color="red">
                                    <span class="amz-sc red"></span>Red (&lt;16.7%)</a></li>
                                <li><a class="amz-dil-item" href="#" data-color="yellow">
                                    <span class="amz-sc yellow"></span>Yellow (16.7–25%)</a></li>
                                <li><a class="amz-dil-item" href="#" data-color="green">
                                    <span class="amz-sc green"></span>Green (25–50%)</a></li>
                                <li><a class="amz-dil-item" href="#" data-color="pink">
                                    <span class="amz-sc pink"></span>Pink (50%+)</a></li>
                            </ul>
                        </div>

                        {{-- SKU search --}}
                        <input type="text" id="amz-sku-search" class="form-control form-control-sm"
                            style="max-width:220px;" placeholder="Search SKU / ASIN...">

                        <button type="button" id="amz-refresh-btn" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                        <button type="button" id="amz-export-btn" class="btn btn-sm btn-success">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </button>
                    </div>

                    {{-- ── Summary badges ── --}}
                    <div id="amz-summary-stats" class="mt-2 p-3 bg-light rounded mb-3">
                        <div class="d-flex flex-wrap gap-2 amz-badge-row" role="group" aria-label="Summary metrics">
                            <span class="badge bg-info fs-6 p-2 amz-badge-chart" id="amz-total-sess-badge"
                                  data-metric="total_sessions" style="font-weight:700;color:#111;cursor:pointer;" title="Click for trend">Sessions L30: 0</span>
                            <span class="badge bg-warning fs-6 p-2 amz-badge-chart" id="amz-avg-dil-badge"
                                  data-metric="avg_dil" style="font-weight:700;color:#111;cursor:pointer;"
                                  title="Dilution % · Click for trend">DIL: 0%</span>
                            <span class="badge bg-success fs-6 p-2 amz-badge-chart" id="amz-avg-lqs-badge"
                                  data-metric="avg_lqs" style="font-weight:700;cursor:pointer;" title="Click for trend">LQS: –</span>
                            <span class="badge bg-secondary fs-6 p-2 amz-badge-chart" id="amz-avg-rating-badge"
                                  data-metric="avg_rating" style="font-weight:700;cursor:pointer;" title="Click for trend">Rating: –</span>
                            <span class="badge fs-6 p-2 amz-badge-chart" id="amz-lqs-below9-badge"
                                  data-metric="lqs_below_9_count"
                                  style="font-weight:700;cursor:pointer;background:#dc3545;color:#fff;"
                                  title="SKUs with LQS score below 9 · Click for trend">&lt; 9: –</span>
                            <span class="badge fs-6 p-2" id="amz-cvr-badge"
                                  style="font-weight:700;background:#146eb4;color:#fff;cursor:pointer;" title="CVR = Total Sold ÷ Total Sessions × 100 · Click for trend">CVR: –</span>
                            <span class="badge fs-6 p-2" id="amz-js-refresh-badge"
                                  style="font-weight:700;background:#198754;color:#fff;cursor:pointer;user-select:none;"
                                  title="Pull latest LQS data from Jungle Scout API">
                                <i class="fas fa-sync-alt me-1" id="amz-js-refresh-icon"></i>Refresh
                            </span>
                        </div>
                    </div>

                    <div id="amz-lqs-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CVR History Chart Modal (matches all-marketplace-master design) ── --}}
    <div class="modal fade p-0" id="amzCvrChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0" style="max-width:100%;">
            <div class="modal-content" style="overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="amzCvrChartTitle">LQS Amz – CVR (Rolling)</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="amzCvrChartRange" class="form-select form-select-sm bg-white"
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
                    <div id="amzCvrChartContainer" style="height:20vh;display:flex;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;">
                            <canvas id="amzCvrChart"></canvas>
                        </div>
                        <div id="amzCvrRefPanel" style="width:100px;display:flex;flex-direction:column;justify-content:center;
                                gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;border-radius:0 4px 4px 0;">
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#dc3545;margin-bottom:1px;">Highest</div>
                                <div id="amzCvrHighest" style="font-size:13px;font-weight:700;color:#dc3545;">–</div>
                            </div>
                            <div style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;margin-bottom:1px;">Median</div>
                                <div id="amzCvrMedian" style="font-size:13px;font-weight:700;color:#6c757d;">–</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#198754;margin-bottom:1px;">Lowest</div>
                                <div id="amzCvrLowest" style="font-size:13px;font-weight:700;color:#198754;">–</div>
                            </div>
                        </div>
                    </div>
                    <div id="amzCvrLoading" class="text-center py-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data…</p>
                    </div>
                    <div id="amzCvrNoData" class="text-center py-3" style="display:none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No CVR history yet. Data is saved each time the page loads.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Action Taken Modal ── --}}
    <div class="modal fade" id="amzActionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm" style="max-width:400px;">
            <div class="modal-content" style="border-radius:10px;overflow:hidden;">
                <div class="modal-header py-2 px-3" style="background:#ff9900;">
                    <h6 class="modal-title mb-0 text-white" style="font-size:13px;">
                        <i class="fas fa-edit me-1"></i> Action Taken
                    </h6>
                    <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="text-muted mb-1" style="font-size:11px;">
                        SKU: <strong id="amzActionSkuLabel" class="text-dark"></strong>
                    </p>
                    <textarea id="amzActionText" class="form-control form-control-sm" rows="3"
                        maxlength="100" placeholder="Describe the action taken… (max 100 chars)"
                        style="resize:none;font-size:12px;"></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small class="text-muted"><span id="amzActionCharCount">0</span>/100</small>
                        <small id="amzActionErr" class="text-danger" style="display:none;font-size:11px;"></small>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="amzActionSaveBtn" class="btn btn-sm" style="background:#ff9900;color:#fff;border-color:#e88e00;">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── History Modal ── --}}
    <div class="modal fade" id="amzHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" style="max-width:480px;">
            <div class="modal-content" style="border-radius:10px;overflow:hidden;">
                <div class="modal-header py-2 px-3" style="background:#495057;">
                    <h6 class="modal-title mb-0 text-white" style="font-size:13px;">
                        <i class="fas fa-history me-1"></i> Action History –
                        <span id="amzHistorySkuLabel" style="font-size:11px;opacity:.85;"></span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3" style="max-height:55vh;overflow-y:auto;">
                    <div id="amzHistoryLoading" class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-secondary"></div>
                        <p class="mt-1 small text-muted mb-0">Loading history…</p>
                    </div>
                    <div id="amzHistoryList" style="display:none;"></div>
                    <div id="amzHistoryEmpty" class="text-center py-3" style="display:none;">
                        <i class="fas fa-inbox text-muted fa-2x mb-2"></i>
                        <p class="small text-muted mb-0">No actions recorded yet.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Badge Trend Chart Modal – matches all-marketplace-master design --}}
    <div class="modal fade p-0" id="amzBadgeChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0" style="max-width:100%;">
            <div class="modal-content" style="overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="amzBadgeChartTitle">LQS Amz – Trend</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="amzBadgeChartRange" class="form-select form-select-sm bg-white"
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
                    <div id="amzBadgeChartContainer" style="height:20vh;display:flex;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;">
                            <canvas id="amzBadgeChart"></canvas>
                        </div>
                        <div id="amzBadgeRefPanel" style="width:100px;display:flex;flex-direction:column;justify-content:center;
                                gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;border-radius:0 4px 4px 0;">
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#dc3545;margin-bottom:1px;">Highest</div>
                                <div id="amzBadgeHighest" style="font-size:13px;font-weight:700;color:#dc3545;">–</div>
                            </div>
                            <div style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;margin-bottom:1px;">Median</div>
                                <div id="amzBadgeMedian" style="font-size:13px;font-weight:700;color:#6c757d;">–</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#198754;margin-bottom:1px;">Lowest</div>
                                <div id="amzBadgeLowest" style="font-size:13px;font-weight:700;color:#198754;">–</div>
                            </div>
                        </div>
                    </div>
                    <div id="amzBadgeLoading" class="text-center py-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data…</p>
                    </div>
                    <div id="amzBadgeNoData" class="text-center py-3" style="display:none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No trend data yet. Data is saved each time the page loads.</p>
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
        let amzTable = null;
        let amzSummaryCache = [];

        function amzNotify(msg, type) {
            if (window.toastr) {
                if (type === 'warning') toastr.warning(msg);
                else if (type === 'error') toastr.error(msg);
                else toastr.success(msg);
            } else {
                alert(msg);
            }
        }

        // ── applyFilters ────────────────────────────────────────────────
        function amzApplyFilters() {
            if (!amzTable) return;
            amzTable.clearFilter();

            const skuSearch = ($('#amz-sku-search').val() || '').toLowerCase().trim();
            const rowType   = $('#amz-row-type-filter').val();
            const invFilter = $('#amz-inv-filter').val();
            const lqsFilter = $('#amz-score-filter').val();
            const dilColor  = $('.amz-dil-item.active').data('color') || 'all';

            if (skuSearch) {
                amzTable.addFilter(d =>
                    (d.sku || '').toLowerCase().includes(skuSearch) ||
                    (d.asin || '').toLowerCase().includes(skuSearch)
                );
            }

            if (rowType === 'parents') {
                amzTable.addFilter(d => d.is_parent === true);
            } else if (rowType === 'skus') {
                amzTable.addFilter(d => !d.is_parent);
            }

            if (invFilter === 'zero') {
                amzTable.addFilter(d => (parseInt(d.inv, 10) || 0) === 0);
            } else if (invFilter === 'more') {
                amzTable.addFilter(d => (parseInt(d.inv, 10) || 0) > 0);
            }

            if (lqsFilter !== 'all') {
                amzTable.addFilter(function(d) {
                    if (d.is_parent) return true;
                    const lqs = parseInt(d.lqs, 10);
                    if (lqsFilter === 'missing') return !lqs || isNaN(lqs);
                    if (lqsFilter === '8-10') return lqs >= 8 && lqs <= 10;
                    if (lqsFilter === '6-7')  return lqs >= 6 && lqs < 8;
                    if (lqsFilter === '4-5')  return lqs >= 4 && lqs < 6;
                    if (lqsFilter === '1-3')  return lqs >= 1 && lqs < 4;
                    return true;
                });
            }

            if (dilColor !== 'all') {
                amzTable.addFilter(function(d) {
                    const inv   = parseFloat(d.inv)  || 0;
                    const l30   = parseFloat(d.l30)  || 0;
                    const dil   = inv === 0 ? 0 : (l30 / inv) * 100;
                    if (dilColor === 'red')    return dil < 16.66;
                    if (dilColor === 'yellow') return dil >= 16.66 && dil < 25;
                    if (dilColor === 'green')  return dil >= 25    && dil < 50;
                    if (dilColor === 'pink')   return dil >= 50;
                    return true;
                });
            }
        }

        function amzNormalizeRows(input) {
            if (Array.isArray(input)) {
                return input.map(r => (r && typeof r.getData === 'function') ? r.getData() : (r || {}));
            }
            if (input && typeof input === 'object') {
                return Object.values(input).map(r => (r && typeof r.getData === 'function') ? r.getData() : (r || {}));
            }
            return [];
        }

        function amzUpdateSummary(input = null) {
            let rows = amzNormalizeRows(input);
            if (!rows.length && amzTable && typeof amzTable.getData === 'function') {
                const active = amzNormalizeRows(amzTable.getData('active'));
                const all    = amzNormalizeRows(amzTable.getData());
                rows = active.length ? active : all;
            }
            if (!rows.length) rows = amzNormalizeRows(amzSummaryCache);

            let totalInv = 0, totalL30 = 0, totalSess = 0, totalSold = 0, totalSessions = 0;
            let dilSum = 0, dilCount = 0;
            let lqsSum = 0, lqsCount = 0;
            let ratingSum = 0, ratingCount = 0;
            let lqsBelow9Count = 0;

            rows.forEach(function(row) {
                if (row.is_parent) return;
                const inv    = parseFloat(row.inv)      || 0;
                const l30    = parseFloat(row.l30)      || 0;
                const sess   = parseFloat(row.sessions) || 0;
                const lqs    = parseInt(row.lqs, 10);
                const rating = parseFloat(row.rating)   || 0;

                totalInv      += inv;
                totalL30      += l30;
                totalSess     += sess;
                totalSold     += l30;
                totalSessions += sess;

                if (inv > 0) { dilSum += (l30 / inv) * 100; dilCount++; }
                if (lqs && !isNaN(lqs)) { lqsSum += lqs; lqsCount++; }
                if (rating > 0) { ratingSum += rating; ratingCount++; }
                if (lqs && !isNaN(lqs) && lqs > 0 && lqs < 9) { lqsBelow9Count++; }
            });

            const avgDil    = dilCount    > 0 ? dilSum    / dilCount    : 0;
            const avgLqs    = lqsCount    > 0 ? lqsSum    / lqsCount    : 0;
            const avgRating = ratingCount > 0 ? ratingSum / ratingCount : 0;
            const cvr       = totalSessions > 0 ? (totalSold / totalSessions) * 100 : null;

            $('#amz-total-inv-badge').text('Total INV: ' + Math.round(totalInv));
            $('#amz-total-l30-badge').text('Total L30: ' + Math.round(totalL30));
            $('#amz-total-sess-badge').text('Sessions L30: ' + totalSess.toLocaleString());
            $('#amz-avg-dil-badge').text('DIL: ' + Math.round(avgDil) + '%');
            $('#amz-avg-lqs-badge').text('LQS: ' + (avgLqs > 0 ? avgLqs.toFixed(1) : '–'));
            $('#amz-avg-rating-badge').text('Rating: ' + (avgRating > 0 ? avgRating.toFixed(1) : '–'));
            $('#amz-cvr-badge').text('CVR: ' + (cvr !== null ? cvr.toFixed(1) + '%' : '–'));
            $('#amz-lqs-below9-badge').text('< 9: ' + lqsBelow9Count);
        }

        $(document).ready(function() {
            amzTable = new Tabulator('#amz-lqs-table', {
                ajaxURL: '{{ route("lqs.amz.data") }}',
                ajaxResponse: function(url, params, response) {
                    amzSummaryCache = amzNormalizeRows(response);
                    amzUpdateSummary(amzSummaryCache);
                    return response;
                },
                layout: 'fitDataStretch',
                pagination: true,
                paginationSize: 100,
                initialSort: [],
                rowFormatter: function(row) {
                    if (row.getData().is_parent === true) {
                        row.getElement().classList.add('amz-lqs-parent-row');
                    }
                },
                columns: [
                    {
                        title: 'Parent',
                        field: 'parent',
                        width: 120,
                        frozen: true,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const v = cell.getValue() || '';
                            if (!v) return '<span style="color:#adb5bd;">–</span>';
                            return `<span style="color:#0d6efd;font-size:11px;font-weight:600;">${v}</span>`;
                        }
                    },
                    {
                        title: 'Image',
                        field: 'image',
                        width: 60,
                        headerSort: false,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            const src = cell.getValue();
                            if (d.is_parent) return '';
                            const asin = d.asin || '';
                            const imgTag = src
                                ? `<img src="${src}" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:4px;" onerror="this.style.display='none'">`
                                : `<span style="color:#adb5bd;font-size:10px;">No img</span>`;
                            return asin
                                ? `<a href="https://www.amazon.com/dp/${asin}" target="_blank" title="View on Amazon">${imgTag}</a>`
                                : imgTag;
                        }
                    },
                    {
                        title: 'SKU',
                        field: 'sku',
                        minWidth: 180,
                        frozen: true,
                        headerFilter: 'input',
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            const val = cell.getValue() || '';
                            if (d.is_parent) {
                                return `<span style="color:#664d03;font-size:13px;font-weight:700;">${val}</span>`;
                            }
                            const esc = val.replace(/&/g,'&amp;').replace(/</g,'&lt;');
                            return `<span style="font-weight:600;">${esc}</span>`;
                        }
                    },
                    {
                        title: 'ASIN',
                        field: 'asin',
                        width: 110,
                        formatter: function(cell) {
                            const d    = cell.getRow().getData();
                            const asin = cell.getValue() || '';
                            if (d.is_parent || !asin) return '<span style="color:#adb5bd;">–</span>';
                            return `<a href="https://www.amazon.com/dp/${asin}" target="_blank"
                                style="color:#ff9900;font-weight:600;font-size:11px;">${asin}</a>`;
                        }
                    },
                    {
                        title: 'INV',
                        field: 'inv',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 55,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (d.is_parent) return `<span style="font-weight:700;">${val}</span>`;
                            if (val === 0) return `<span style="color:#dc3545;font-weight:600;">0</span>`;
                            return `<span style="font-weight:600;">${val}</span>`;
                        }
                    },
                    {
                        title: 'L30',
                        field: 'l30',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 55,
                        formatter: function(cell) {
                            const val = parseInt(cell.getValue(), 10) || 0;
                            return `<span style="font-weight:600;">${val}</span>`;
                        }
                    },
                    {
                        title: 'Sessions L30',
                        field: 'sessions',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 70,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (val === 0) return '<span style="color:#adb5bd;">–</span>';
                            return `<span style="font-weight:600;">${val.toLocaleString()}</span>`;
                        }
                    },
                    {
                        title: 'DIL%',
                        field: 'dil_percent',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 55,
                        formatter: function(cell) {
                            const row   = cell.getRow().getData();
                            const inv   = parseFloat(row.inv) || 0;
                            const l30   = parseFloat(row.l30) || 0;
                            if (inv === 0) return `<span style="color:#6c757d;">0%</span>`;
                            const dil   = (l30 / inv) * 100;
                            const color = dil < 16.66 ? '#a00211' : dil < 25 ? '#ffc107' : dil < 50 ? '#28a745' : '#e83e8c';
                            return `<span style="color:${color};font-weight:600;">${Math.round(dil)}%</span>`;
                        }
                    },
                    {
                        title: 'Price',
                        field: 'price',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 65,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const val = parseFloat(cell.getValue()) || 0;
                            if (val === 0) return '<span style="color:#adb5bd;">–</span>';
                            return `<span style="font-weight:600;">$${val.toFixed(2)}</span>`;
                        }
                    },
                    {
                        title: 'Rating',
                        field: 'rating',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 60,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const val = parseFloat(cell.getValue()) || 0;
                            if (!val) return '<span style="color:#adb5bd;">–</span>';
                            const color = val >= 4.5 ? '#28a745' : val >= 4.0 ? '#3591dc' : val >= 3.5 ? '#ffc107' : '#dc3545';
                            return `<span style="color:${color};font-weight:600;">★ ${val.toFixed(1)}</span>`;
                        }
                    },
                    {
                        title: 'Reviews',
                        field: 'reviews',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 65,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (!val) return '<span style="color:#adb5bd;">–</span>';
                            return `<span style="font-weight:600;">${val.toLocaleString()}</span>`;
                        }
                    },
                    {
                        title: 'Avg CVR',
                        field: 'cvr',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 65,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            const val = parseFloat(cell.getValue());
                            if (val === null || val === undefined || isNaN(val)) {
                                return '<span style="color:#adb5bd;">–</span>';
                            }
                            let color = '#6c757d';
                            if (val >= 15)      color = '#28a745';
                            else if (val >= 10) color = '#3591dc';
                            else if (val >= 5)  color = '#ffc107';
                            else                color = '#dc3545';
                            return `<span style="color:${color};font-weight:700;">${val.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: 'LQS',
                        field: 'lqs',
                        sorter: 'number',
                        hozAlign: 'center',
                        width: 55,
                        formatter: function(cell) {
                            const d   = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const lqs = parseInt(cell.getValue(), 10);
                            if (!lqs || isNaN(lqs)) return '<span style="color:#adb5bd;">–</span>';
                            let color = '#6c757d';
                            if (lqs >= 8)      color = '#28a745';
                            else if (lqs >= 6) color = '#3591dc';
                            else if (lqs >= 4) color = '#ffc107';
                            else               color = '#dc3545';
                            return `<span style="color:${color};font-weight:700;font-size:13px;">${lqs}</span>`;
                        }
                    },
                    {
                        title: 'Action Taken',
                        field: 'has_action',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 72,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const active = d.has_action;
                            const cls    = active ? 'has-action' : 'no-action';
                            const tip    = active
                                ? `Last: ${d.latest_action_user} – ${d.latest_action_date}`
                                : 'No action recorded. Click to add.';
                            return `<span class="amz-action-dot ${cls}" data-sku="${d.sku}" title="${tip}"></span>`;
                        },
                        cellClick: function(e, cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return;
                            amzOpenActionModal(d.sku, cell);
                        }
                    },
                    {
                        title: 'History',
                        field: 'latest_action_text',
                        headerSort: false,
                        width: 160,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || !d.has_action) {
                                return '<span style="color:#adb5bd;font-size:10px;">–</span>';
                            }
                            return `<div class="amz-history-cell" data-sku="${d.sku}" title="Click to see full history">
                                <span class="amz-hist-user">${d.latest_action_user || ''}</span>
                                <span class="amz-hist-date"> · ${d.latest_action_date || ''}</span><br>
                                <span class="amz-hist-text">${d.latest_action_text || ''}</span>
                                <span class="amz-hist-more">▶ Full history</span>
                            </div>`;
                        },
                        cellClick: function(e, cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent || !d.has_action) return;
                            amzOpenHistoryModal(d.sku);
                        }
                    }
                ],
                dataLoaded:    function(data)        { amzUpdateSummary(data); },
                dataFiltered:  function(filters, rows) { amzUpdateSummary(rows); },
                dataProcessed: function()             { amzUpdateSummary(); },
                renderComplete: function()            { amzUpdateSummary(); }
            });

            // ── Filter events ──────────────────────────────────────────
            $('#amz-sku-search').on('input', function()      { amzApplyFilters(); });
            $('#amz-row-type-filter').on('change', function() { amzApplyFilters(); });
            $('#amz-inv-filter').on('change',    function()  { amzApplyFilters(); });
            $('#amz-score-filter').on('change',  function()  { amzApplyFilters(); });

            // DIL dropdown
            $(document).on('click', '.amz-dil-toggle', function(e) {
                e.stopPropagation();
                $(this).closest('.amz-lqs-dropdown').toggleClass('show');
            });
            $(document).on('click', '.amz-dil-item', function(e) {
                e.preventDefault(); e.stopPropagation();
                $('.amz-dil-item').removeClass('active');
                $(this).addClass('active');
                const circle = $(this).find('.amz-sc').clone();
                $('#amz-dil-btn').html('').append(circle).append('DIL%');
                $(this).closest('.amz-lqs-dropdown').removeClass('show');
                amzApplyFilters();
            });
            $(document).on('click', function() {
                $('.amz-lqs-dropdown').removeClass('show');
            });

            $('#amz-refresh-btn').on('click', function() {
                amzTable.setData('{{ route("lqs.amz.data") }}');
            });
            $('#amz-export-btn').on('click', function() {
                amzTable.download('csv', 'lqs_amz_data.csv');
            });

            // ── CVR Badge History Chart ───────────────────────────────
            let amzCvrChartInstance = null;
            let amzCvrChartDays     = 32;
            let amzCvrAjax          = null;

            function amzCvrFmt(v) {
                return (Number(v) || 0).toFixed(1) + '%';
            }

            function amzCvrRangeLabel(d) {
                return d === 0 ? 'Lifetime' : ('L' + d);
            }

            function amzRenderCvrChart(data) {
                const labels = data.map(d => d.date);
                const values = data.map(d => Number(d.value) || 0);

                const dataMin = Math.min(...values);
                const dataMax = Math.max(...values);
                const sorted  = [...values].sort((a, b) => a - b);
                const mid     = Math.floor(sorted.length / 2);
                const median  = sorted.length % 2 !== 0
                    ? sorted[mid]
                    : (sorted[mid - 1] + sorted[mid]) / 2;

                const range = dataMax - dataMin || 1;
                const yMin  = Math.max(0, dataMin - range * 0.1);
                const yMax  = dataMax + range * 0.1;

                // Right panel
                document.getElementById('amzCvrHighest').textContent = amzCvrFmt(dataMax);
                document.getElementById('amzCvrMedian').textContent  = amzCvrFmt(median);
                document.getElementById('amzCvrLowest').textContent  = amzCvrFmt(dataMin);

                // Dot colors: green=up, red=down vs previous day
                const dotColors = values.map((v, i) => {
                    if (i === 0) return '#6c757d';
                    return v > values[i - 1] ? '#28a745' : v < values[i - 1] ? '#dc3545' : '#6c757d';
                });

                const labelColors = dotColors;

                // Median dashed line plugin (matches all-marketplace-master)
                const medianLinePlugin = {
                    id: 'amzCvrMedianLine',
                    afterDraw(chart) {
                        const yScale = chart.scales.y;
                        const xScale = chart.scales.x;
                        const c      = chart.ctx;
                        const yPx    = yScale.getPixelForValue(median);
                        c.save();
                        c.setLineDash([6, 4]);
                        c.strokeStyle = '#6c757d';
                        c.lineWidth   = 1.2;
                        c.beginPath();
                        c.moveTo(xScale.left, yPx);
                        c.lineTo(xScale.right, yPx);
                        c.stroke();
                        c.restore();
                    }
                };

                // Value labels plugin (alternating -10 / -20 offsets)
                const valueLabelsPlugin = {
                    id: 'amzCvrValueLabels',
                    afterDatasetsDraw(chart) {
                        const dataset = chart.data.datasets[0];
                        const meta    = chart.getDatasetMeta(0);
                        const c       = chart.ctx;
                        c.save();
                        c.font          = 'bold 11px Inter, system-ui, sans-serif';
                        c.textAlign     = 'center';
                        c.textBaseline  = 'bottom';
                        meta.data.forEach((point, i) => {
                            const offsetY = (i % 2 === 0) ? -10 : -20;
                            c.fillStyle   = labelColors[i];
                            c.fillText(amzCvrFmt(dataset.data[i]), point.x, point.y + offsetY);
                        });
                        c.restore();
                    }
                };

                const ctx = document.getElementById('amzCvrChart').getContext('2d');
                if (amzCvrChartInstance) amzCvrChartInstance.destroy();

                amzCvrChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: 'CVR',
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
                                bodyFont:  { size: 10 },
                                padding:   6,
                                callbacks: {
                                    label: function(ctx) {
                                        const i = ctx.dataIndex;
                                        const parts = ['Value: ' + amzCvrFmt(ctx.raw)];
                                        if (i > 0) {
                                            const diff  = ctx.raw - values[i - 1];
                                            const arrow = diff > 0 ? '▲' : diff < 0 ? '▼' : '▬';
                                            parts.push('vs Yesterday: ' + arrow + ' ' + amzCvrFmt(Math.abs(diff)));
                                        }
                                        if (i >= 7) {
                                            const diff7  = ctx.raw - values[i - 7];
                                            const arrow7 = diff7 > 0 ? '▲' : diff7 < 0 ? '▼' : '▬';
                                            parts.push('vs 7d Ago: ' + arrow7 + ' ' + amzCvrFmt(Math.abs(diff7)));
                                        }
                                        return parts;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                min: yMin, max: yMax,
                                ticks: { font: { size: 9 }, callback: v => amzCvrFmt(v) }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45, minRotation: 45,
                                    autoSkip: labels.length > 14,
                                    maxTicksLimit: labels.length > 14 ? 14 : labels.length,
                                    font: { size: 8 }
                                }
                            }
                        }
                    }
                });

                $('#amzCvrChartContainer').show();
            }

            function amzLoadCvrChart() {
                if (amzCvrAjax) amzCvrAjax.abort();
                $('#amzCvrChartContainer,#amzCvrNoData').hide();
                $('#amzCvrLoading').show();

                amzCvrAjax = $.ajax({
                    url: '{{ route("lqs.amz.cvr.history") }}',
                    method: 'GET',
                    data: { days: amzCvrChartDays },
                    success: function(res) {
                        amzCvrAjax = null;
                        $('#amzCvrLoading').hide();
                        if (res.success && res.data && res.data.length) {
                            amzRenderCvrChart(res.data);
                        } else {
                            $('#amzCvrNoData').show();
                        }
                    },
                    error: function() {
                        amzCvrAjax = null;
                        $('#amzCvrLoading').hide();
                        $('#amzCvrNoData').show();
                    }
                });
            }

            $('#amz-cvr-badge').on('click', function() {
                amzCvrChartDays = 32;
                $('#amzCvrChartRange').val('32');
                $('#amzCvrChartTitle').text('LQS Amz – CVR (Rolling L32)');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('amzCvrChartModal')).show();
                amzLoadCvrChart();
            });

            // ── Jungle Scout Refresh badge ─────────────────────────────
            $('#amz-js-refresh-badge').on('click', function() {
                const $badge = $(this);
                const $icon  = $('#amz-js-refresh-icon');

                if ($badge.data('running')) return;
                $badge.data('running', true)
                      .css({ opacity: '0.7', cursor: 'not-allowed' });
                $icon.addClass('fa-spin');

                amzNotify('Jungle Scout refresh started. LQS data will update in the background.', 'success');

                $.ajax({
                    url: '{{ route("lqs.amz.refresh.js") }}',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(res) {
                        if (res.success) {
                            // Auto-reload the table after a short delay to pick up new data
                            setTimeout(function() {
                                amzTable.setData('{{ route("lqs.amz.data") }}');
                                amzNotify('LQS data refreshed from Jungle Scout!', 'success');
                            }, 3000);
                        } else {
                            amzNotify(res.message || 'Refresh failed.', 'error');
                        }
                    },
                    error: function() {
                        amzNotify('Failed to trigger refresh. Please try again.', 'error');
                    },
                    complete: function() {
                        setTimeout(function() {
                            $icon.removeClass('fa-spin');
                            $badge.data('running', false)
                                  .css({ opacity: '1', cursor: 'pointer' });
                        }, 4000);
                    }
                });
            });

            $(document).on('change', '#amzCvrChartRange', function() {
                const d = parseInt($(this).val(), 10);
                if (d === amzCvrChartDays) return;
                amzCvrChartDays = d;
                $('#amzCvrChartTitle').text('LQS Amz – CVR (Rolling ' + amzCvrRangeLabel(d) + ')');
                amzLoadCvrChart();
            });

            // ── Action Taken modal ────────────────────────────────────
            let amzActionCurrentSku  = null;
            let amzActionCurrentCell = null;

            window.amzOpenActionModal = function(sku, cell) {
                amzActionCurrentSku  = sku;
                amzActionCurrentCell = cell;
                $('#amzActionSkuLabel').text(sku);
                $('#amzActionText').val('').trigger('input');
                $('#amzActionErr').hide();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('amzActionModal')).show();
                setTimeout(() => $('#amzActionText').focus(), 350);
            };

            $('#amzActionText').on('input', function() {
                $('#amzActionCharCount').text($(this).val().length);
            });

            $('#amzActionSaveBtn').on('click', function() {
                const text = $('#amzActionText').val().trim();
                if (!text) {
                    $('#amzActionErr').text('Please enter an action.').show();
                    return;
                }
                const $btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving…');

                $.ajax({
                    url: '{{ route("lqs.amz.action.save") }}',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    data: { sku: amzActionCurrentSku, action: text },
                    success: function(res) {
                        if (res.success && amzActionCurrentCell) {
                            // Update the row data in Tabulator
                            const row = amzActionCurrentCell.getRow();
                            row.update({
                                has_action:         true,
                                latest_action_text: res.action,
                                latest_action_user: res.user_name,
                                latest_action_date: res.created_at,
                            });
                        }
                        bootstrap.Modal.getInstance(document.getElementById('amzActionModal')).hide();
                        amzNotify('Action saved!', 'success');
                    },
                    error: function() {
                        $('#amzActionErr').text('Failed to save. Please try again.').show();
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save');
                    }
                });
            });

            // ── History modal ─────────────────────────────────────────
            window.amzOpenHistoryModal = function(sku) {
                $('#amzHistorySkuLabel').text(sku);
                $('#amzHistoryList').hide().empty();
                $('#amzHistoryEmpty').hide();
                $('#amzHistoryLoading').show();
                bootstrap.Modal.getOrCreateInstance(document.getElementById('amzHistoryModal')).show();

                $.ajax({
                    url: '/lqs/amz/action-history/' + encodeURIComponent(sku),
                    method: 'GET',
                    success: function(res) {
                        $('#amzHistoryLoading').hide();
                        if (!res.success || !res.data || !res.data.length) {
                            $('#amzHistoryEmpty').show();
                            return;
                        }
                        const html = res.data.map(e => `
                            <div class="amz-hist-entry">
                                <div class="amz-he-meta">
                                    <i class="fas fa-user-circle me-1"></i>${e.user_name}
                                    &nbsp;·&nbsp;
                                    <i class="fas fa-clock me-1"></i>${e.created_at}
                                </div>
                                <div class="amz-he-text">${e.action}</div>
                            </div>`).join('');
                        $('#amzHistoryList').html(html).show();
                    },
                    error: function() {
                        $('#amzHistoryLoading').hide();
                        $('#amzHistoryEmpty').show();
                    }
                });
            };

            // ── Badge Trend Chart (all-marketplace-master design) ─────
            let amzBadgeChartInstance = null;
            let amzMetric    = '';
            let amzChartDays = 32;
            let amzChartAjax = null;

            const amzBadgeLabels = {
                total_inv:          'Total INV',
                total_l30:          'Total L30',
                total_sessions:     'Sessions L30',
                avg_dil:            'DIL%',
                avg_lqs:            'LQS',
                avg_rating:         'Rating',
                lqs_below_9_count:  'LQS < 9'
            };

            function amzBadgeFmt(v) {
                const n = Number(v) || 0;
                if (amzMetric === 'avg_lqs')           return n.toFixed(1);
                if (amzMetric === 'avg_dil')           return Math.round(n) + '%';
                if (amzMetric === 'avg_rating')        return n.toFixed(1) + ' ★';
                if (amzMetric === 'lqs_below_9_count') return Math.round(n) + ' SKUs';
                return Math.round(n).toLocaleString('en-US');
            }

            function amzBadgeRangeLabel(d) {
                return d === 0 ? 'Lifetime' : ('L' + d);
            }

            function amzRenderBadgeChart(data) {
                const labels  = data.map(d => d.date);
                const values  = data.map(d => Number(d.value) || 0);
                const dataMin = Math.min(...values);
                const dataMax = Math.max(...values);
                const sorted  = [...values].sort((a, b) => a - b);
                const mid     = Math.floor(sorted.length / 2);
                const median  = sorted.length % 2 !== 0
                    ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;

                const range = dataMax - dataMin || 1;
                const yMin  = Math.max(0, dataMin - range * 0.1);
                const yMax  = dataMax + range * 0.1;

                // Right reference panel
                document.getElementById('amzBadgeHighest').textContent = amzBadgeFmt(dataMax);
                document.getElementById('amzBadgeMedian').textContent  = amzBadgeFmt(median);
                document.getElementById('amzBadgeLowest').textContent  = amzBadgeFmt(dataMin);

                // Dot colors: green=up, red=down vs previous day
                const dotColors = values.map((v, i) =>
                    i === 0 ? '#6c757d' : v > values[i-1] ? '#28a745' : v < values[i-1] ? '#dc3545' : '#6c757d'
                );

                // Median dashed line plugin
                const medianLinePlugin = {
                    id: 'amzBadgeMedianLine',
                    afterDraw(chart) {
                        const yScale = chart.scales.y, xScale = chart.scales.x, c = chart.ctx;
                        const yPx = yScale.getPixelForValue(median);
                        c.save();
                        c.setLineDash([6, 4]); c.strokeStyle = '#6c757d'; c.lineWidth = 1.2;
                        c.beginPath(); c.moveTo(xScale.left, yPx); c.lineTo(xScale.right, yPx); c.stroke();
                        c.restore();
                    }
                };

                // Value labels plugin (alternating -10 / -20 offsets)
                const valueLabelsPlugin = {
                    id: 'amzBadgeValueLabels',
                    afterDatasetsDraw(chart) {
                        const dataset = chart.data.datasets[0];
                        const meta = chart.getDatasetMeta(0);
                        const c = chart.ctx;
                        c.save();
                        c.font = 'bold 11px Inter, system-ui, sans-serif';
                        c.textAlign = 'center'; c.textBaseline = 'bottom';
                        meta.data.forEach((point, i) => {
                            const offsetY = (i % 2 === 0) ? -10 : -20;
                            c.fillStyle = dotColors[i];
                            c.fillText(amzBadgeFmt(dataset.data[i]), point.x, point.y + offsetY);
                        });
                        c.restore();
                    }
                };

                const ctx = document.getElementById('amzBadgeChart').getContext('2d');
                if (amzBadgeChartInstance) amzBadgeChartInstance.destroy();

                amzBadgeChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: amzBadgeLabels[amzMetric] || amzMetric,
                            data: values,
                            backgroundColor: 'rgba(108,117,125,0.08)',
                            borderColor: '#adb5bd',
                            borderWidth: 1.5,
                            fill: true, tension: 0.3,
                            pointRadius: 3, pointHoverRadius: 5,
                            pointBackgroundColor: dotColors,
                            pointBorderColor: dotColors,
                            pointBorderWidth: 1.5
                        }]
                    },
                    plugins: [medianLinePlugin, valueLabelsPlugin],
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 26, left: 2, right: 2, bottom: 2 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                titleFont: { size: 10 }, bodyFont: { size: 10 }, padding: 6,
                                callbacks: {
                                    label: function(ctx) {
                                        const i = ctx.dataIndex;
                                        const parts = ['Value: ' + amzBadgeFmt(ctx.raw)];
                                        if (i > 0) {
                                            const diff = ctx.raw - values[i - 1];
                                            parts.push('vs Yesterday: ' + (diff > 0 ? '▲' : diff < 0 ? '▼' : '▬') + ' ' + amzBadgeFmt(Math.abs(diff)));
                                        }
                                        if (i >= 7) {
                                            const diff7 = ctx.raw - values[i - 7];
                                            parts.push('vs 7d Ago: ' + (diff7 > 0 ? '▲' : diff7 < 0 ? '▼' : '▬') + ' ' + amzBadgeFmt(Math.abs(diff7)));
                                        }
                                        return parts;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                min: yMin, max: yMax,
                                ticks: { font: { size: 9 }, callback: v => amzBadgeFmt(v) }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45, minRotation: 45,
                                    autoSkip: labels.length > 14,
                                    maxTicksLimit: labels.length > 14 ? 14 : labels.length,
                                    font: { size: 8 }
                                }
                            }
                        }
                    }
                });

                $('#amzBadgeChartContainer').show();
            }

            function amzLoadBadgeChart() {
                if (!amzMetric) return;
                if (amzChartAjax) amzChartAjax.abort();
                $('#amzBadgeChartContainer,#amzBadgeNoData').hide();
                $('#amzBadgeLoading').show();

                amzChartAjax = $.ajax({
                    url: '{{ route("lqs.amz.badge.chart") }}',
                    method: 'GET',
                    data: { metric: amzMetric, days: amzChartDays },
                    success: function(res) {
                        amzChartAjax = null;
                        $('#amzBadgeLoading').hide();
                        const pts = (res && res.success && Array.isArray(res.data)) ? res.data : [];
                        if (pts.length) {
                            amzRenderBadgeChart(pts);
                        } else {
                            $('#amzBadgeNoData').show();
                        }
                    },
                    error: function() {
                        amzChartAjax = null;
                        $('#amzBadgeLoading').hide();
                        $('#amzBadgeNoData').show();
                    }
                });
            }

            $(document).on('click', '.amz-badge-chart', function() {
                amzMetric    = $(this).data('metric');
                amzChartDays = 32;
                $('#amzBadgeChartRange').val('32');
                $('#amzBadgeChartTitle').text('LQS Amz – ' + (amzBadgeLabels[amzMetric] || amzMetric) + ' (Rolling L32)');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('amzBadgeChartModal')).show();
                amzLoadBadgeChart();
            });

            $(document).on('change', '#amzBadgeChartRange', function() {
                const d = parseInt($(this).val(), 10);
                if (d === amzChartDays) return;
                amzChartDays = d;
                $('#amzBadgeChartTitle').text(
                    'LQS Amz – ' + (amzBadgeLabels[amzMetric] || amzMetric) + ' (Rolling ' + amzBadgeRangeLabel(d) + ')'
                );
                amzLoadBadgeChart();
            });
        });
    </script>
@endsection
