@extends('layouts.vertical', ['title' => 'Temu 2 Ads (API)', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #temu-ads-table .tabulator-header {
            background: #fd7e14;
            font-size: 0.8rem;
            color: #fff;
        }
        #temu-ads-table .tabulator-header .tabulator-col {
            background: #fd7e14;
            color: #fff;
            border-right: 1px solid rgba(255,255,255,0.25);
            text-align: center;
        }
        #temu-ads-table .tabulator-header .tabulator-col .tabulator-col-content,
        #temu-ads-table .tabulator-header .tabulator-col .tabulator-col-title {
            text-align: center;
            justify-content: center;
        }
        #temu-ads-table .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0;
        }
        #temu-ads-table .tabulator-col .tabulator-col-sorter,
        #temu-ads-table .tabulator-col .tabulator-col-sorter-element,
        #temu-ads-table .tabulator-col .tabulator-arrow {
            display: none !important;
        }
        #temu-ads-table .tabulator-header .tabulator-col.tabulator-sortable {
            cursor: pointer;
        }
        #temu-ads-table .tabulator-header .tabulator-col {
            padding: 0;
        }
        #temu-ads-table .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 4px 3px;
        }
        #temu-ads-table .tabulator-cell {
            font-size: 0.85rem;
            text-align: center !important;
            justify-content: center;
            align-items: center;
            padding: 2px 4px !important;
        }
        #temu-ads-table .tabulator-cell[tabulator-field="image_path"] {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1px 2px !important;
            overflow: hidden;
        }
        #temu-ads-table .tabulator-cell[tabulator-field="image_path"] .temu-ads-thumb {
            width: auto;
            height: 22px;
            max-width: 100%;
            max-height: 22px;
            object-fit: contain;
            border-radius: 2px;
            vertical-align: middle;
        }
        #temu-ads-table .temu-ads-row-cb,
        #temu-ads-table .temu-ads-select-all {
            width: 16px;
            height: 16px;
            margin: 0;
            cursor: pointer;
            accent-color: #0d6efd;
            vertical-align: middle;
        }
        .temu-pause-run-btn {
            position: relative;
            display: inline-block;
            border: 0;
            border-radius: 999px;
            width: 44px;
            height: 24px;
            padding: 0;
            cursor: pointer;
            vertical-align: middle;
        }
        .temu-pause-run-btn.is-pause { background: #dc3545; }
        .temu-pause-run-btn.is-run { background: #198754; }
        .temu-pause-run-knob {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #fff;
        }
        .temu-pause-run-btn.is-run .temu-pause-run-knob { left: auto; right: 3px; }
        .temu-pause-run-btn:disabled { opacity: 0.65; cursor: wait; }
        .temu-pause-run-ok {
            color: #198754;
            font-weight: 800;
            font-size: 1.2rem;
            line-height: 1;
        }
        .temu-pause-run-fail {
            color: #dc3545;
            font-weight: 800;
            font-size: 1.2rem;
            line-height: 1;
            cursor: help;
        }
        #temu-ads-table .tabulator-footer {
            background: #f4f7fa;
            padding: 8px;
        }
        #summary-stats .temu-ads-badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        .pricing-filter-item {
            display: inline-block;
            vertical-align: middle;
        }
        #raw-json-pre {
            max-height: 70vh;
            overflow: auto;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        #temu-ads-column-dropdown-menu.show {
            min-width: min(92vw, 720px);
            max-width: min(96vw, 780px);
            max-height: 70vh;
            overflow-y: auto;
            padding: 0.4rem 0.55rem 0.6rem;
        }
        #temu-ads-column-dropdown-menu > li.col-vis-full {
            list-style: none;
        }
        #temu-ads-column-dropdown-menu .col-vis-selections-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            margin: 0 0 8px;
            padding: 2px 4px;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            user-select: none;
        }
        #temu-ads-column-dropdown-menu .col-vis-selections-title input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            cursor: pointer;
        }
        #temu-ads-column-dropdown-menu .col-vis-groups {
            display: grid;
            grid-template-columns: repeat(3, minmax(140px, 1fr));
            gap: 8px;
        }
        #temu-ads-column-dropdown-menu .col-vis-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 6px 8px 8px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
        }
        #temu-ads-column-dropdown-menu .col-vis-group.col-vis-drop-over {
            outline: 2px dashed #0d6efd;
        }
        #temu-ads-column-dropdown-menu .col-vis-group-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            margin: 0 0 6px;
            padding: 2px 4px;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            user-select: none;
        }
        #temu-ads-column-dropdown-menu .col-vis-group-title input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            cursor: pointer;
        }
        #temu-ads-column-dropdown-menu .col-vis-group-list {
            flex: 1;
            min-height: 60px;
            display: flex;
            flex-direction: column;
            gap: 1px;
        }
        #temu-ads-column-dropdown-menu .col-vis-item {
            cursor: grab;
            border-radius: 4px;
        }
        #temu-ads-column-dropdown-menu .col-vis-item.col-vis-dragging {
            opacity: 0.4;
            cursor: grabbing;
        }
        #temu-ads-column-dropdown-menu .col-vis-item.col-vis-drop-before {
            box-shadow: inset 0 2px 0 #0d6efd;
        }
        #temu-ads-column-dropdown-menu .col-vis-item.col-vis-drop-after {
            box-shadow: inset 0 -2px 0 #0d6efd;
        }
        #temu-ads-column-dropdown-menu .col-vis-item > label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 3px 5px;
            cursor: grab;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
            font-size: 0.8rem;
            user-select: none;
        }
        #temu-ads-column-dropdown-menu .col-vis-item > label input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            width: 14px;
            height: 14px;
        }
        #temu-ads-column-dropdown-menu .col-vis-item > label:hover {
            background: rgba(0, 0, 0, 0.04);
            border-radius: 3px;
        }
        .temu-ad-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #198754;
            vertical-align: middle;
        }
        .temu-ad-dot.is-zero-inv { background: #ffc107; }
        .temu-ads-thumb {
            width: auto;
            height: 22px;
            max-width: 40px;
            max-height: 22px;
            object-fit: contain;
            border-radius: 2px;
            vertical-align: middle;
        }
        .temu-ads-chart-badge { cursor: pointer; }
        .temu-ads-history-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            margin-left: 6px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(0, 0, 0, 0.18);
            vertical-align: middle;
            cursor: pointer;
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.25);
        }
        .temu-ads-history-dot:hover { transform: scale(1.3); }
        #temuAdsBadgeChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #temuAdsBadgeChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #temuAdsBadgeChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
            overflow: hidden;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Temu 2 Ads (API)',
        'sub_title' => 'Matches Temu 2 Data Report Overall (Last 30 days includes today, US Pacific)',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <select id="period-filter" class="form-select form-select-sm pricing-filter-item" style="width: auto;">
                                <option value="" data-label="All Periods">All Periods</option>
                                <option value="L7" data-label="L7">L7</option>
                                <option value="L30" data-label="L30" selected>L30</option>
                                <option value="L60" data-label="L60">L60</option>
                            </select>
                            <input type="text" id="search-goods-id" class="form-control form-control-sm pricing-filter-item"
                                   placeholder="Search Goods ID" style="width: 170px;">
                            <input type="text" id="search-sku" class="form-control form-control-sm pricing-filter-item"
                                   placeholder="Search SKU" style="width: 150px;">
                            <select id="status-filter" class="form-select form-select-sm pricing-filter-item" style="width: auto;"
                                    title="Filter by Status">
                                <option value="" data-label="All Status">All Status</option>
                                <option value="Active" data-label="Active">Active</option>
                                <option value="Inactive" data-label="Paused">Paused</option>
                                <option value="No ad" data-label="No ad">No ad</option>
                                <option value="Deleted" data-label="Deleted">Deleted</option>
                                <option value="Not sync" data-label="Not sync">Not sync</option>
                            </select>
                            <select id="pause-run-filter" class="form-select form-select-sm pricing-filter-item" style="width: auto;"
                                    title="Filter by Pause/Run">
                                <option value="" data-label="All Pause/Run">All Pause/Run</option>
                                <option value="pause" data-label="Pause">Pause</option>
                                <option value="run" data-label="Run">Run</option>
                            </select>
                            <select id="inv-filter" class="form-select form-select-sm pricing-filter-item" style="width: auto;"
                                    title="Filter by Inv">
                                <option value="" data-label="ALL">ALL</option>
                                <option value="eq0" data-label="INV=0">INV=0</option>
                                <option value="gt0" data-label="INV&gt;0">INV&gt;0</option>
                            </select>
                            <select id="dil-filter" class="form-select form-select-sm pricing-filter-item" style="width: auto;"
                                    title="Dil% color — same as /temu-decrease (red &lt;25, green 25–50, pink 50%+)">
                                <option value="" data-label="All Dil%">All Dil%</option>
                                <option value="red" data-label="Dil% Red">Dil% Red</option>
                                <option value="green" data-label="Dil% Green">Dil% Green</option>
                                <option value="pink" data-label="Dil% Pink">Dil% Pink</option>
                            </select>
                            <select id="clicks-filter" class="form-select form-select-sm pricing-filter-item" style="width: auto;"
                                    title="Filter by Clicks 7">
                                <option value="" data-label="All Clicks">All Clicks</option>
                                <option value="0-70" data-label="0–70">0–70</option>
                                <option value="71-140" data-label="71–140">71–140</option>
                                <option value="141-210" data-label="141–210">141–210</option>
                                <option value="211-280" data-label="211–280">211–280</option>
                                <option value="281-350" data-label="281–350">281–350</option>
                                <option value="351-420" data-label="351–420">351–420</option>
                                <option value="421-490" data-label="421–490">421–490</option>
                                <option value="491-560" data-label="491–560">491–560</option>
                                <option value="561-630" data-label="561–630">561–630</option>
                                <option value="631-700" data-label="631–700">631–700</option>
                                <option value="gt700" data-label="&gt;700">&gt;700</option>
                            </select>
                            <button type="button" id="temu-ads-rules-btn" class="btn btn-sm btn-outline-dark pricing-filter-item"
                                    data-bs-toggle="modal" data-bs-target="#temuAdsRulesModal"
                                    title="Ad rules — L7 clicks Pause/Run and Auto Cron">
                                <i class="fas fa-sliders-h me-1"></i>Ad rules
                            </button>
                            <div class="dropdown d-inline-block pricing-filter-item">
                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                                    id="temuAdsColumnVisibilityDropdown" data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside" aria-expanded="false"
                                    title="Show / hide table columns">
                                    <i class="fas fa-table-columns"></i>
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="temuAdsColumnVisibilityDropdown"
                                    id="temu-ads-column-dropdown-menu"></ul>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="button" id="refresh-status-btn" class="btn btn-sm btn-outline-secondary pricing-filter-item"
                                    title="Sync Active/Inactive from Temu ad.detail.query (adsDetail.adShowStatus)">
                                <i class="fa fa-toggle-on"></i> Refresh Status
                            </button>
                            <button type="button" id="create-ad-btn" class="btn btn-sm btn-warning pricing-filter-item d-none"
                                    title="Create ads Rule — budget and target ROAS used for Create">
                                <i class="fa fa-plus"></i> Create ads Rule
                            </button>
                            <button type="button" id="pause-run-rule-btn" class="btn btn-sm btn-outline-dark pricing-filter-item"
                                    data-bs-toggle="modal" data-bs-target="#pauseRunRuleModal"
                                    title="Pause/Run slabs by L7 Clicks">
                                <i class="fas fa-sliders-h"></i> Pause/Run Rule
                            </button>
                            <button type="button" id="roas-rule-btn" class="btn btn-sm btn-outline-danger pricing-filter-item"
                                    data-bs-toggle="modal" data-bs-target="#roasRuleModal"
                                    title="Spend 1 colors and ROAS ranges">
                                <i class="fas fa-palette"></i> ROAS rule
                            </button>
                            <button type="button" id="refresh-btn" class="btn btn-sm btn-primary pricing-filter-item"
                                    title="Fetch from Temu API and store raw data">
                                <i class="fa fa-sync"></i> Fetch from API
                            </button>
                            <button type="button" id="export-btn" class="btn btn-sm btn-success pricing-filter-item" title="Export CSV">
                                <i class="fa fa-download"></i>
                            </button>
                        </div>
                    </div>

                    <div id="fetch-status" class="mb-2" style="display:none;"></div>

                    <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                        <div class="temu-ads-badge-row" role="group" aria-label="Summary metrics">
                            <span class="badge fs-6 p-2" id="roas-rule-badge"
                                style="background-color: #be185d; color: white; font-weight: bold; cursor: pointer;"
                                data-bs-toggle="modal" data-bs-target="#roasRuleModal"
                                title="Spend 1 colors and dynamic ROAS ranges">
                                ROAS rule<span id="roas-rule-summary" class="d-none"></span>
                            </span>
                            <span class="badge bg-dark fs-6 p-2 temu-ads-chart-badge" id="row-count"
                                data-metric="rows" data-label="Rows"
                                style="color: white; font-weight: bold;"
                                title="Click for history">Rows: <span class="temu-ads-badge-val">0</span><span class="temu-ads-history-dot" title="History"></span></span>
                            <span class="badge fs-6 p-2 temu-ads-chart-badge" id="impr-sum"
                                data-metric="impressions" data-label="Impr"
                                style="background-color: #0d6efd; color: white; font-weight: bold;"
                                title="Click for history">Impr: <span class="temu-ads-badge-val">0</span><span class="temu-ads-history-dot" title="History"></span></span>
                            <span class="badge fs-6 p-2 temu-ads-chart-badge" id="click-sum"
                                data-metric="clicks" data-label="Clicks"
                                style="background-color: #e83e8c; color: white; font-weight: bold;"
                                title="Click for history">Clicks: <span class="temu-ads-badge-val">0</span><span class="temu-ads-history-dot" title="History"></span></span>
                            <span class="badge fs-6 p-2 temu-ads-chart-badge" id="spend-sum"
                                data-metric="spend" data-label="Spend"
                                style="background-color: #6f42c1; color: white; font-weight: bold;"
                                title="Click for history">Spend: <span class="temu-ads-badge-val">$0</span><span class="temu-ads-history-dot" title="History"></span></span>
                            <span class="badge fs-6 p-2 temu-ads-chart-badge" id="y-spend-sum"
                                data-metric="y_spend" data-label="Y spend"
                                style="background-color: #4c1d95; color: white; font-weight: bold;"
                                title="Y spend = total of the Y Spend column (last 1 day). Click for history">Y spend: <span class="temu-ads-badge-val">$0</span><span class="temu-ads-history-dot" title="History"></span></span>
                            <span class="badge fs-6 p-2 temu-ads-chart-badge" id="roas-sum"
                                data-metric="roas" data-label="ROAS"
                                style="background-color: #7c3aed; color: white; font-weight: bold;"
                                title="ROAS = total ad sales (Ord$) ÷ total Spend from badges. Click for history">ROAS: <span class="temu-ads-badge-val">0</span><span class="temu-ads-history-dot" title="History"></span></span>
                            <span class="badge fs-6 p-2 temu-ads-chart-badge" id="acos-sum"
                                data-metric="acos" data-label="Acos%"
                                style="background-color: #e11d48; color: white; font-weight: bold;"
                                title="Acos% = total Spend ÷ total ad sales (Ord$) from badges. Click for history">Acos%: <span class="temu-ads-badge-val">0%</span><span class="temu-ads-history-dot" title="History"></span></span>
                            <span class="badge fs-6 p-2 temu-ads-chart-badge" id="tacos-sum"
                                data-metric="tacos" data-label="TAcos%"
                                style="background-color: #b45309; color: white; font-weight: bold;"
                                title="TAcos% = total Spend ÷ total all sales (Shopify L30 × price). Click for history">TAcos%: <span class="temu-ads-badge-val">0%</span><span class="temu-ads-history-dot" title="History"></span></span>
                            <span class="badge fs-6 p-2 temu-ads-chart-badge" id="ctr-avg"
                                data-metric="ctr" data-label="CTR"
                                style="background-color: #0891b2; color: white; font-weight: bold;"
                                title="CTR = Clicks ÷ Impr from badges. Click for history">CTR: <span class="temu-ads-badge-val">0.0%</span><span class="temu-ads-history-dot" title="History"></span></span>
                            <span class="badge fs-6 p-2 temu-ads-chart-badge" id="cvr-avg"
                                data-metric="cvr" data-label="Avg CVR"
                                style="background-color: #20c997; color: white; font-weight: bold;"
                                title="Avg CVR = Sold / Clicks from badges. Click for history">Avg CVR: <span class="temu-ads-badge-val">0.0%</span><span class="temu-ads-history-dot" title="History"></span></span>
                            <span class="badge fs-6 p-2" id="create-count"
                                style="background-color: #fd7e14; color: white; font-weight: bold; cursor: pointer;"
                                title="Create ads for selected No ad rows (Inv > 0). If nothing is selected, uses all visible Create rows.">Create: <span class="temu-ads-badge-val">0</span><span class="temu-ads-history-dot" data-metric="create" data-label="Create" title="History"></span></span>
                            <span class="badge fs-6 p-2" id="pause-run-count"
                                style="background-color: #212529; color: white; font-weight: bold; cursor: pointer;"
                                title="Pause and Run counts. Click for running ads budget and details.">
                                Pause <span id="pause-count-num" style="color:#ff8a80;">0</span><span class="temu-ads-history-dot" data-metric="pause" data-label="Pause" title="Pause history"></span>
                                / Run <span id="run-count-num" style="color:#81c784;">0</span><span class="temu-ads-history-dot" data-metric="run" data-label="Run" title="Run history"></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div id="temu-ads-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Shared L7 Clicks → T ROAS bidding rule --}}
    <div class="modal fade" id="temuAdsRulesModal" tabindex="-1" aria-labelledby="temuAdsRulesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="temuAdsRulesModalLabel">Ad rules</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Shared with /temu2-decrease. L7 clicks below the threshold stay in Run mode. L7 clicks at or above the threshold are Pause. Auto Cron only pushes rows whose Active/Pause status actually changes.</p>
                    <div class="d-inline-flex flex-wrap align-items-center gap-1 border rounded px-3 py-2 bg-light">
                        <label for="temu-l7-clicks-red-threshold" class="mb-0 small fw-semibold text-nowrap">L7 Clicks &lt;</label>
                        <input type="number" id="temu-l7-clicks-red-threshold" class="form-control form-control-sm"
                               min="0" max="100000" step="1" value="70" style="width: 70px;">
                        <span class="small fw-bold" style="color:#198754;">Run</span>
                        <span class="text-muted px-1">→</span>
                        <span class="small fw-semibold text-nowrap">L7 Clicks ≥ <span data-temu-l7-pause-threshold>70</span></span>
                        <span class="small fw-bold" style="color:#0d6efd;">Pause</span>
                    </div>
                    <div id="temu-ads-cron-status" class="small mt-2 text-success">Daily cron: ON — only rows whose Active/Pause status changes from the click limit, after L7 fetch and at 16:10 IST.</div>
                    <div id="temu-ads-pause-status" class="mt-3" style="display:none;"></div>
                </div>
                <div class="modal-footer flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-warning" id="temu-ads-cron-toggle-btn"
                            data-enabled="1"
                            title="Auto cron is ON. Only rows whose Active/Pause status changes from the click limit are pushed. Click to turn off.">
                        <i class="fas fa-bolt me-1"></i>Auto Cron
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="temu-ads-save-rule-btn"
                            title="Save the L7 clicks threshold">
                        <i class="fas fa-save me-1"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Pause/Run Rule slabs --}}
    <div class="modal fade" id="pauseRunRuleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pause/Run Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Slabs by <strong>Clicks 7</strong>. First matching range wins.
                        Leave <strong>To</strong> empty for that value and above.
                        Default: <strong>0–69 → Run</strong>, <strong>70+ → Pause</strong>.
                    </p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="pause-run-inv-zero" checked>
                        <label class="form-check-label" for="pause-run-inv-zero">
                            <strong>Inv = 0 → Pause</strong>
                            <span class="text-muted"> — SKUs with 0 inventory are Pause (overrides L7 clicks slabs)</span>
                        </label>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-2">
                            <thead class="table-light">
                                <tr>
                                    <th>L7 Clicks from</th>
                                    <th>L7 Clicks to</th>
                                    <th>Pause/Run</th>
                                    <th style="width:70px;"></th>
                                </tr>
                            </thead>
                            <tbody id="pause-run-slabs-tbody"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pause-run-slab-add-btn">
                        <i class="fa fa-plus"></i> Add slab
                    </button>
                    <div id="pause-run-rule-status" class="small mt-2"></div>
                </div>
                <div class="modal-footer flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-primary" id="pause-run-rule-save-btn"
                            title="Save slabs and refresh Pause/Run switches">Save</button>
                    <button type="button" class="btn btn-sm btn-primary d-none" id="pause-run-rule-apply-btn"
                            title="Save and update Pause/Run on this page">Apply</button>
                    <button type="button" class="btn btn-sm btn-success d-none" id="pause-run-rule-apply-site-btn"
                            title="Save for /temu/ads and /temu-decrease, then update this page">Apply To Site</button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ROAS Rule: Spend 1 colors + dynamic ROAS ranges --}}
    <div class="modal fade" id="roasRuleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ROAS rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        <strong>Spend 1</strong> uses the Spend 1 from/to columns.
                        Default: <strong>$0 red</strong>, <strong>$0.01–$5.99 yellow</strong>, <strong>$6–$9 green</strong>, <strong>above $9 black text on pink</strong>.
                        Add a <strong>ROAS Range</strong> on any row to color the ROAS column the same way.
                        <strong>Target ROAS</strong> fills the T ROAS column from the matching Spend 1 slab.
                        <strong>Push ROAS</strong> sends that T ROAS to Temu for existing ads via <code>temu.searchrec.ad.modify</code> (status 5).
                        Leave To empty for that value and above. First matching range wins.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-2">
                            <thead class="table-light">
                                <tr>
                                    <th>Spend 1 from</th>
                                    <th>Spend 1 to</th>
                                    <th>Color</th>
                                    <th>Target ROAS</th>
                                    <th>ROAS Range from</th>
                                    <th>ROAS Range to</th>
                                    <th style="width:70px;"></th>
                                </tr>
                            </thead>
                            <tbody id="roas-rule-slabs-tbody"></tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="roas-rule-slab-add-btn">
                            <i class="fa fa-plus"></i> Add slab
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="roas-rule-range-add-btn">
                            <i class="fa fa-plus"></i> Add ROAS Range
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="roas-rule-push-btn"
                            title="Push Target ROAS from each Spend 1 slab to Temu for existing ads">
                            <i class="fa fa-cloud-upload"></i> Push ROAS
                        </button>
                    </div>
                    <div id="roas-rule-status" class="small mt-2"></div>
                </div>
                <div class="modal-footer flex-wrap gap-1">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="roas-rule-save-btn">Save</button>
                    <button type="button" class="btn btn-sm btn-primary" id="roas-rule-apply-btn">Apply</button>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Create Ad modal --}}
    <div class="modal fade" id="createAdModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create ads Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Calls <code>temu.searchrec.ad.create</code>.</p>
                    <div class="mb-2">
                        <label class="form-label form-label-sm" for="create-goods-id">Goods ID</label>
                        <input type="text" id="create-goods-id" class="form-control form-control-sm" placeholder="602442267775049">
                    </div>
                    <div class="mb-2 d-none">
                        <label class="form-label form-label-sm" for="create-budget">Daily budget (USD)</label>
                        <input type="number" id="create-budget" class="form-control form-control-sm" min="1" step="0.01" value="10">
                    </div>
                    <div class="mb-2 d-none">
                        <label class="form-label form-label-sm" for="create-roas">Target ROAS</label>
                        <div class="input-group input-group-sm">
                            <input type="number" id="create-roas" class="form-control" min="0.1" max="12" step="0.1" value="4">
                            <button type="button" class="btn btn-outline-secondary" id="predict-roas-btn">Suggest</button>
                        </div>
                    </div>
                    <div id="create-ad-status" class="mt-2" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-warning" id="create-ad-submit">Create Ad</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Running ads (Pause/Run badge) --}}
    <div class="modal fade" id="pauseRunRunningModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Running ads</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap gap-2 mb-3" id="pause-run-running-summary"></div>
                    <div class="table-responsive" style="max-height: 55vh;">
                        <table class="table table-sm table-striped table-bordered mb-0" id="pause-run-running-table">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>SKU</th>
                                    <th>Goods ID</th>
                                    <th>Status</th>
                                    <th>Clicks 7</th>
                                    <th>Clicks 30</th>
                                    <th>Impr</th>
                                    <th>Spend</th>
                                    <th>Ord</th>
                                    <th>Ord$</th>
                                    <th>ROAS</th>
                                    <th>ACOS</th>
                                    <th>Budget</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Badge history chart — same Rolling L30 dot graph as Temu / campaign ads --}}
    <div class="modal fade p-0" id="temuAdsBadgeChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span>Temu Ads - <span id="temuAdsBadgeChartTitle">CTR</span> <span id="temuAdsBadgeChartSuffix">(Rolling L30)</span></span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="temuAdsBadgeChartRange" class="form-select form-select-sm bg-white" style="width:110px;height:26px;font-size:11px;padding:1px 8px;" aria-label="Chart date range">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="temuAdsBadgeChartContainer" style="height:20vh;display:flex;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;">
                            <canvas id="temuAdsBadgeChartCanvas"></canvas>
                            <p class="text-center text-muted small mb-0 py-4 d-none" id="temuAdsBadgeChartEmpty">
                                No history available for this metric in the selected window.
                            </p>
                        </div>
                        <div style="width:100px;display:flex;flex-direction:column;justify-content:center;gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;">
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#dc3545;margin-bottom:1px;">Highest</div>
                                <div id="temuAdsBadgeChartHighest" style="font-size:13px;font-weight:700;color:#dc3545;">-</div>
                            </div>
                            <div style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6c757d;margin-bottom:1px;">Median</div>
                                <div id="temuAdsBadgeChartMedian" style="font-size:13px;font-weight:700;color:#6c757d;">-</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#198754;margin-bottom:1px;">Lowest</div>
                                <div id="temuAdsBadgeChartLowest" style="font-size:13px;font-weight:700;color:#198754;">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Raw JSON modal --}}
    <div class="modal fade" id="rawJsonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rawJsonModalLabel">Raw API Response</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <pre id="raw-json-pre"></pre>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="{{ asset('js/temu-ads-color-rules.js') }}?v={{ @filemtime(public_path('js/temu-ads-color-rules.js')) ?: 15 }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const moneyFmt = (cell) => {
                const v = cell.getValue();
                const el = cell.getElement && cell.getElement();
                const field = (cell.getField && cell.getField()) || '';
                if (el) {
                    el.style.color = '';
                    el.style.backgroundColor = '';
                    el.style.fontWeight = '';
                }
                if (v === null || v === undefined || v === '') return '';
                if (field === 'ad_spend' && window.TemuAdsColorRules && TemuAdsColorRules.colorSpendRoasAlert) {
                    const row = cell.getRow ? cell.getRow().getData() : {};
                    TemuAdsColorRules.colorSpendRoasAlert(el, v, row.roas);
                }
                if (field === 'spend_l1' && window.TemuAdsColorRules && TemuAdsColorRules.colorSpend1) {
                    TemuAdsColorRules.colorSpend1(el, v);
                }
                return '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            };
            const numFmt = (cell) => {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '';
                return Number(v).toLocaleString('en-US');
            };
            const clicksFmt = (cell) => {
                const shown = cell.getValue();
                const el = cell.getElement();
                if (el) {
                    el.style.color = '';
                    el.style.fontWeight = '';
                }
                if (shown === null || shown === undefined || shown === '') return '';
                const n = Number(String(shown).replace(/,/g, ''));
                if (window.TemuAdsColorRules) {
                    TemuAdsColorRules.colorL7Clicks(el, n);
                }
                return n.toLocaleString('en-US');
            };
            const clicks30Fmt = (cell) => {
                const shown = cell.getValue();
                const el = cell.getElement();
                if (el) {
                    el.style.color = '';
                    el.style.fontWeight = '';
                }
                if (shown === null || shown === undefined || shown === '') return '';
                const n = Number(String(shown).replace(/,/g, ''));
                if (isFinite(n) && n < 300) {
                    if (el) {
                        el.style.color = '#a00211';
                        el.style.fontWeight = '700';
                    }
                }
                return n.toLocaleString('en-US');
            };
            let currentAvgCtr = 0;
            const pctFmt = (cell) => {
                const v = cell.getValue();
                const el = cell.getElement();
                const field = (cell.getField && cell.getField()) || '';
                if (el) {
                    el.style.color = '';
                    el.style.backgroundColor = '';
                    el.style.fontWeight = '';
                }
                if (v === null || v === undefined || v === '') return '';
                if (field === 'acos') {
                    const row = cell.getRow ? cell.getRow().getData() : {};
                    const spend = row.ad_spend;
                    if (window.TemuAdsColorRules && TemuAdsColorRules.colorSpendAcosAlert) {
                        TemuAdsColorRules.colorSpendAcosAlert(el, spend, v);
                    }
                    const shown = window.TemuAdsColorRules && TemuAdsColorRules.displayAcosPercent
                        ? TemuAdsColorRules.displayAcosPercent(v, spend)
                        : Number(v);
                    return Number(shown).toFixed(0) + '%';
                }
                if (field === 'ctr' && el) {
                    const n = Number(v);
                    if (isFinite(n) && n < currentAvgCtr) {
                        el.style.color = '#dc3545';
                        el.style.fontWeight = '700';
                    }
                }
                return Number(v).toFixed(1) + '%';
            };
            function targetRoasValue(row) {
                if (window.TemuAdsColorRules && typeof TemuAdsColorRules.targetRoasForSpend === 'function') {
                    return TemuAdsColorRules.targetRoasForSpend(row && row.spend_l1);
                }
                return 8;
            }
            const tRoasFmt = (cell) => {
                const el = cell.getElement && cell.getElement();
                if (el) el.title = 'Target ROAS';
                const row = cell.getRow ? cell.getRow().getData() : {};
                const n = Number(targetRoasValue(row));
                if (!isFinite(n)) return '';
                return n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 1 });
            };
            const decFmt = (cell) => {
                const v = cell.getValue();
                const el = cell.getElement();
                if (el) {
                    el.style.color = '';
                    el.style.backgroundColor = '';
                    el.style.fontWeight = '';
                }
                if (v === null || v === undefined || v === '') return '';
                if (window.TemuAdsColorRules && cell.getField && cell.getField() === 'roas') {
                    const row = cell.getRow ? cell.getRow().getData() : {};
                    const alerted = TemuAdsColorRules.colorSpendRoasAlert
                        && TemuAdsColorRules.colorSpendRoasAlert(el, row.ad_spend, v);
                    if (!alerted && TemuAdsColorRules.colorRoasRange) {
                        TemuAdsColorRules.colorRoasRange(el, v);
                    }
                }
                return Number(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            };
            const fetchedFmt = (cell) => {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '';
                const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})/);
                if (!m) return String(v);
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                return String(Number(m[3])) + ' ' + months[Number(m[2]) - 1];
            };

            function dataUrl() {
                const period = document.getElementById('period-filter').value;
                let url = '{{ route("temu2.ads.data") }}';
                if (period) url += '?period=' + encodeURIComponent(period);
                return url;
            }

            function rowInvValue(row) {
                const n = parseInt(String(row && row.inv != null ? row.inv : 0).replace(/,/g, ''), 10);
                return isFinite(n) ? n : 0;
            }

            function rowPauseRunAction(row) {
                if (window.TemuAdsColorRules && TemuAdsColorRules.computedPauseRunAction) {
                    return TemuAdsColorRules.computedPauseRunAction(row || {});
                }
                if (window.TemuAdsColorRules && TemuAdsColorRules.pauseRunAction) {
                    return TemuAdsColorRules.pauseRunAction(row || {});
                }
                return String((row && row.pause_run) || '');
            }

            function rowStatusValue(row) {
                const v = String((row && row.ad_status) || 'Not sync');
                return v === 'Paused' ? 'Inactive' : v;
            }

            function rowDilBand(row) {
                const dil = parseFloat(row && row.dil_percent) || 0;
                if (window.TemuAdsColorRules && typeof TemuAdsColorRules.dilBand === 'function') {
                    return TemuAdsColorRules.dilBand(dil);
                }
                if (dil < 25) return 'red';
                if (dil < 50) return 'green';
                return 'pink';
            }

            function rowInvBucket(row) {
                return rowInvValue(row) <= 0 ? 'eq0' : 'gt0';
            }

            function rowClicks7(row) {
                const n = parseInt(String(row && row.clicks_l7 != null ? row.clicks_l7 : 0).replace(/,/g, ''), 10);
                return isFinite(n) && n > 0 ? n : 0;
            }

            function rowClicksBucket(row) {
                const n = rowClicks7(row);
                if (n > 700) return 'gt700';
                if (n <= 70) return '0-70';
                const start = Math.floor((n - 1) / 70) * 70 + 1;
                return start + '-' + (start + 69);
            }

            function currentFilterQuery() {
                return {
                    goodsQ: (document.getElementById('search-goods-id').value || '').trim().toLowerCase(),
                    skuQ: (document.getElementById('search-sku').value || '').trim().toLowerCase(),
                    statusQ: (document.getElementById('status-filter').value || '').trim(),
                    pauseRunQ: (document.getElementById('pause-run-filter').value || '').trim(),
                    invQ: (document.getElementById('inv-filter').value || '').trim(),
                    dilQ: (document.getElementById('dil-filter').value || '').trim(),
                    clicksQ: (document.getElementById('clicks-filter').value || '').trim(),
                };
            }

            function rowMatchesQuery(data, q, skip) {
                q = q || currentFilterQuery();
                skip = skip || '';
                if (skip !== 'search') {
                    if (q.goodsQ && String(data.goods_id || '').toLowerCase().indexOf(q.goodsQ) === -1) return false;
                    if (q.skuQ && String(data.sku || '').toLowerCase().indexOf(q.skuQ) === -1) return false;
                }
                if (skip !== 'status' && q.statusQ && rowStatusValue(data) !== q.statusQ) return false;
                if (skip !== 'pause_run' && q.pauseRunQ && rowPauseRunAction(data) !== q.pauseRunQ) return false;
                if (skip !== 'inv' && q.invQ && rowInvBucket(data) !== q.invQ) return false;
                if (skip !== 'dil' && q.dilQ && rowDilBand(data) !== q.dilQ) return false;
                if (skip !== 'clicks' && q.clicksQ && rowClicksBucket(data) !== q.clicksQ) return false;
                return true;
            }

            function paintSelectOptionCounts(selectId, counts, total) {
                const sel = document.getElementById(selectId);
                if (!sel) return;
                Array.from(sel.options).forEach(function (opt) {
                    const label = opt.getAttribute('data-label')
                        || String(opt.textContent || '').replace(/\s*\([\d,]+\)$/, '').trim();
                    opt.setAttribute('data-label', label);
                    const n = opt.value === '' ? total : (counts[opt.value] || 0);
                    opt.textContent = label + ' (' + Number(n).toLocaleString() + ')';
                });
            }

            function updateFilterCounts(rows) {
                const all = Array.isArray(rows) ? rows : (table ? (table.getData() || []) : []);
                const q = currentFilterQuery();
                const statusCounts = {};
                const pauseCounts = {};
                const invCounts = {};
                const dilCounts = {};
                const clicksCounts = {};
                const periodCounts = {};
                let statusTotal = 0;
                let pauseTotal = 0;
                let invTotal = 0;
                let dilTotal = 0;
                let clicksTotal = 0;
                let periodTotal = 0;
                all.forEach(function (row) {
                    if (rowMatchesQuery(row, q, 'status')) {
                        statusTotal++;
                        const s = rowStatusValue(row);
                        statusCounts[s] = (statusCounts[s] || 0) + 1;
                    }
                    if (rowMatchesQuery(row, q, 'pause_run')) {
                        pauseTotal++;
                        const a = rowPauseRunAction(row) || 'pause';
                        pauseCounts[a] = (pauseCounts[a] || 0) + 1;
                    }
                    if (rowMatchesQuery(row, q, 'inv')) {
                        invTotal++;
                        const b = rowInvBucket(row);
                        invCounts[b] = (invCounts[b] || 0) + 1;
                    }
                    if (rowMatchesQuery(row, q, 'dil')) {
                        dilTotal++;
                        const d = rowDilBand(row);
                        dilCounts[d] = (dilCounts[d] || 0) + 1;
                    }
                    if (rowMatchesQuery(row, q, 'clicks')) {
                        clicksTotal++;
                        const c = rowClicksBucket(row);
                        clicksCounts[c] = (clicksCounts[c] || 0) + 1;
                    }
                    periodTotal++;
                    const p = String((row && row.period) || '').toUpperCase();
                    if (p) periodCounts[p] = (periodCounts[p] || 0) + 1;
                });
                paintSelectOptionCounts('status-filter', statusCounts, statusTotal);
                paintSelectOptionCounts('pause-run-filter', pauseCounts, pauseTotal);
                paintSelectOptionCounts('inv-filter', invCounts, invTotal);
                paintSelectOptionCounts('dil-filter', dilCounts, dilTotal);
                paintSelectOptionCounts('clicks-filter', clicksCounts, clicksTotal);
                paintSelectOptionCounts('period-filter', periodCounts, periodTotal);
            }

            function paintPauseRunBadge(rows) {
                const list = Array.isArray(rows) ? rows : [];
                let pauseN = 0;
                let runN = 0;
                list.forEach(function (r) {
                    if (rowPauseRunAction(r) === 'run') runN++;
                    else pauseN++;
                });
                const pauseEl = document.getElementById('pause-count-num');
                const runEl = document.getElementById('run-count-num');
                if (pauseEl) pauseEl.textContent = Number(pauseN).toLocaleString();
                if (runEl) runEl.textContent = Number(runN).toLocaleString();
                return { pause: pauseN, run: runN };
            }

            function setBadgeVal(id, text) {
                const el = document.getElementById(id);
                if (!el) return;
                const val = el.querySelector('.temu-ads-badge-val');
                if (val) val.textContent = text;
            }

            function canCreateAdRow(row) {
                if (!row) return false;
                if (String(row.ad_status || '') !== 'No ad') return false;
                return (parseInt(row.inv, 10) || 0) > 0;
            }

            const selectedGoodsIds = new Set();

            function rowGoodsId(row) {
                return String((row && row.goods_id) || '').trim();
            }

            function pruneSelectedGoodsIds() {
                if (!table) return;
                const live = {};
                (table.getData() || []).forEach(function (row) {
                    const id = rowGoodsId(row);
                    if (id) live[id] = true;
                });
                Array.from(selectedGoodsIds).forEach(function (id) {
                    if (!live[id]) selectedGoodsIds.delete(id);
                });
            }

            function hasRowSelection() {
                return selectedGoodsIds.size > 0;
            }

            function selectedRowData() {
                if (!table || !hasRowSelection()) return [];
                return (table.getData() || []).filter(function (row) {
                    return selectedGoodsIds.has(rowGoodsId(row));
                });
            }

            function selectCheckboxHtml(row) {
                const id = rowGoodsId(row);
                const on = !!(id && selectedGoodsIds.has(id));
                return '<input type="checkbox" class="temu-ads-row-cb" data-gid="' + id + '"' + (on ? ' checked' : '') + '>';
            }

            function refreshSelectCheckboxes() {
                if (!table) return;
                const col = table.getColumn('_select');
                if (col && typeof col.getCells === 'function') {
                    col.getCells().forEach(function (cell) {
                        const box = cell.getElement().querySelector('.temu-ads-row-cb');
                        if (!box) return;
                        const id = rowGoodsId(cell.getRow().getData());
                        box.checked = !!(id && selectedGoodsIds.has(id));
                        box.dataset.gid = id;
                    });
                }
                const allCb = document.getElementById('temu-ads-select-all');
                if (!allCb) return;
                const rows = table.getRows('active') || [];
                let selected = 0;
                rows.forEach(function (row) {
                    if (selectedGoodsIds.has(rowGoodsId(row.getData()))) selected++;
                });
                allCb.checked = rows.length > 0 && selected === rows.length;
                allCb.indeterminate = selected > 0 && selected < rows.length;
            }

            function createSourceRows() {
                if (!table) return [];
                if (hasRowSelection()) return selectedRowData();
                return table.getData(true) || [];
            }

            function paintCreateBadge() {
                const el = document.getElementById('create-count');
                const n = createBadgeCount();
                setBadgeVal('create-count', Number(n).toLocaleString());
                if (!el) return;
                el.title = hasRowSelection()
                    ? 'Create the ' + n + ' selected No ad row(s) with Inv > 0.'
                    : 'Create all ' + n + ' visible No ad rows with Inv > 0.';
            }

            function queueCreateGoodsIdsFromRows(rows) {
                const seen = {};
                const ids = [];
                (rows || []).forEach(function (row) {
                    if (!canCreateAdRow(row)) return;
                    const gid = String(row.goods_id || '').trim();
                    if (!gid || seen[gid]) return;
                    seen[gid] = true;
                    ids.push(gid);
                });
                return ids;
            }

            function createBadgeCount() {
                return queueCreateGoodsIdsFromRows(createSourceRows()).length;
            }

            function badgeCounts(rows) {
                const list = Array.isArray(rows) ? rows : [];
                let impr = 0, clicks = 0, spend = 0, ySpend = 0, sold = 0, sales = 0, allSales = 0, createN = 0, pauseN = 0, runN = 0;
                const seenSku = {};
                list.forEach(function (r) {
                    impr += parseFloat(r.impressions) || 0;
                    clicks += parseFloat(r.clicks) || 0;
                    spend += parseFloat(r.ad_spend) || 0;
                    ySpend += parseFloat(r.spend_l1) || 0;
                    sold += parseFloat(r.order_pay_cnt) || 0;
                    sales += parseFloat(r.order_pay_amt) || 0;
                    const sku = String(r.sku || '').trim().toUpperCase();
                    if (!sku || !seenSku[sku]) {
                        if (sku) seenSku[sku] = true;
                        allSales += parseFloat(r.all_sale) || 0;
                    }
                    if (canCreateAdRow(r)) createN++;
                    const action = rowPauseRunAction(r);
                    if (action === 'run') runN++;
                    else if (action === 'pause') pauseN++;
                });
                return {
                    rows: list.length,
                    impressions: impr,
                    clicks: clicks,
                    spend: spend,
                    y_spend: ySpend,
                    sold: sold,
                    sales: sales,
                    all_sales: allSales,
                    create: createN,
                    pause: pauseN,
                    run: runN,
                    ctr: impr > 0 ? (clicks / impr) * 100 : 0,
                    cvr: clicks > 0 ? (sold / clicks) * 100 : 0,
                    roas: spend > 0 ? (sales / spend) : 0,
                    acos: sales > 0 ? (spend / sales) * 100 : (spend > 0 ? 100 : 0),
                    tacos: allSales > 0 ? (spend / allSales) * 100 : (spend > 0 ? 100 : 0),
                };
            }

            function currentPeriodKey() {
                return (document.getElementById('period-filter').value || 'ALL');
            }

            function applyCtrAvgColors() {
                if (!table) return;
                const col = table.getColumn('ctr');
                if (!col || typeof col.getCells !== 'function') return;
                col.getCells().forEach(function (cell) {
                    const el = cell.getElement();
                    if (!el) return;
                    const raw = cell.getValue();
                    if (raw === null || raw === undefined || raw === '') {
                        el.style.color = '';
                        el.style.fontWeight = '';
                        return;
                    }
                    const n = Number(raw);
                    if (isFinite(n) && n < currentAvgCtr) {
                        el.style.color = '#dc3545';
                        el.style.fontWeight = '700';
                    } else {
                        el.style.color = '';
                        el.style.fontWeight = '';
                    }
                });
            }

            function paintMetricBadges(rows) {
                const m = badgeCounts(rows);
                currentAvgCtr = Number(m.ctr) || 0;
                setBadgeVal('row-count', Number(m.rows).toLocaleString());
                setBadgeVal('impr-sum', Math.round(m.impressions).toLocaleString());
                setBadgeVal('click-sum', Math.round(m.clicks).toLocaleString());
                setBadgeVal('spend-sum', '$' + Math.round(Number(m.spend) || 0).toLocaleString('en-US'));
                setBadgeVal('y-spend-sum', '$' + Math.round(Number(m.y_spend) || 0).toLocaleString('en-US'));
                setBadgeVal('roas-sum', Number(m.roas || 0).toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 }));
                setBadgeVal('acos-sum', Number(m.acos || 0).toFixed(1) + '%');
                setBadgeVal('tacos-sum', Number(m.tacos || 0).toFixed(1) + '%');
                setBadgeVal('ctr-avg', Number(m.ctr).toFixed(1) + '%');
                setBadgeVal('cvr-avg', Number(m.cvr).toFixed(1) + '%');
                paintCreateBadge();
                paintPauseRunBadge(rows);
                applyCtrAvgColors();
                return m;
            }

            let badgeSnapshotTimer = null;
            function snapshotBadgeHistory() {
                if (!table) return;
                const m = badgeCounts(table.getData() || []);
                clearTimeout(badgeSnapshotTimer);
                badgeSnapshotTimer = setTimeout(function () {
                    fetch(@json(route('temu2.ads.badge-snapshot')), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            rows: m.rows,
                            impressions: m.impressions,
                            clicks: m.clicks,
                            spend: m.spend,
                            y_spend: Math.round((m.y_spend || 0) * 100) / 100,
                            create: m.create,
                            pause: m.pause,
                            run: m.run,
                            ctr: Math.round(m.ctr * 100) / 100,
                            cvr: Math.round(m.cvr * 100) / 100,
                            roas: Math.round((m.roas || 0) * 100) / 100,
                            acos: Math.round((m.acos || 0) * 100) / 100,
                            tacos: Math.round((m.tacos || 0) * 100) / 100,
                            sold: m.sold,
                            sales: m.sales,
                            period: currentPeriodKey(),
                        }),
                    }).catch(function () {});
                }, 600);
            }

            let temuAdsBadgeChart = null;
            let activeBadgeMetric = null;
            let activeBadgeLabel = '';

            function fmtBadgeChartValue(metric, v) {
                if (v === null || v === undefined || isNaN(v)) return '—';
                if (metric === 'spend' || metric === 'y_spend') {
                    return '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
                if (metric === 'roas') {
                    return Number(v).toLocaleString('en-US', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
                }
                if (metric === 'acos' || metric === 'tacos' || metric === 'ctr' || metric === 'cvr') {
                    return Number(v).toFixed(1) + '%';
                }
                return Math.round(Number(v)).toLocaleString('en-US');
            }

            function openTemuAdsBadgeChart(metric, label) {
                if (!metric) return;
                activeBadgeMetric = metric;
                activeBadgeLabel = label || metric.toUpperCase();
                const days = parseInt(document.getElementById('temuAdsBadgeChartRange').value || '30', 10);
                document.getElementById('temuAdsBadgeChartTitle').textContent = activeBadgeLabel;
                document.getElementById('temuAdsBadgeChartSuffix').textContent = '(Rolling L' + days + ')';
                bootstrap.Modal.getOrCreateInstance(document.getElementById('temuAdsBadgeChartModal')).show();
                loadTemuAdsBadgeChart();
            }

            function loadTemuAdsBadgeChart() {
                if (!activeBadgeMetric) return;
                const days = parseInt(document.getElementById('temuAdsBadgeChartRange').value || '30', 10);
                document.getElementById('temuAdsBadgeChartTitle').textContent = activeBadgeLabel;
                document.getElementById('temuAdsBadgeChartSuffix').textContent = '(Rolling L' + days + ')';
                const params = new URLSearchParams({
                    metric: activeBadgeMetric,
                    days: String(days),
                    period: currentPeriodKey(),
                });
                fetch(@json(route('temu2.ads.badge-history')) + '?' + params.toString(), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (resp) {
                        renderTemuAdsBadgeChart(activeBadgeMetric, (resp && resp.data) || []);
                    })
                    .catch(function () {
                        renderTemuAdsBadgeChart(activeBadgeMetric, []);
                    });
            }

            function renderTemuAdsBadgeChart(metric, data) {
                const canvas = document.getElementById('temuAdsBadgeChartCanvas');
                const emptyEl = document.getElementById('temuAdsBadgeChartEmpty');
                if (!canvas) return;
                if (temuAdsBadgeChart) {
                    temuAdsBadgeChart.destroy();
                    temuAdsBadgeChart = null;
                }
                ['temuAdsBadgeChartHighest', 'temuAdsBadgeChartMedian', 'temuAdsBadgeChartLowest'].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.textContent = '-';
                });
                if (!data.length) {
                    canvas.style.display = 'none';
                    emptyEl.classList.remove('d-none');
                    return;
                }
                canvas.style.display = '';
                emptyEl.classList.add('d-none');

                const labels = data.map(function (d) { return d.date; });
                const values = data.map(function (d) { return Number(d.value) || 0; });
                const dataMin = Math.min.apply(null, values);
                const dataMax = Math.max.apply(null, values);
                const sorted = values.slice().sort(function (a, b) { return a - b; });
                const mid = Math.floor(sorted.length / 2);
                const median = sorted.length % 2 !== 0
                    ? sorted[mid]
                    : (sorted[mid - 1] + sorted[mid]) / 2;
                const range = (dataMax - dataMin) || 1;
                const yMin = Math.max(0, dataMin - range * 0.1);
                const yMax = dataMax + range * 0.1;
                const refRed = '#dc3545', refGray = '#6c757d', refGreen = '#198754';

                document.getElementById('temuAdsBadgeChartHighest').textContent = fmtBadgeChartValue(metric, dataMax);
                document.getElementById('temuAdsBadgeChartMedian').textContent = fmtBadgeChartValue(metric, median);
                document.getElementById('temuAdsBadgeChartLowest').textContent = fmtBadgeChartValue(metric, dataMin);

                const dotColors = values.map(function (v, i) {
                    if (i === 0) return refGray;
                    return v > values[i - 1] ? '#28a745' : (v < values[i - 1] ? refRed : refGray);
                });
                const labelColors = values.map(function (v) {
                    return v === 0 ? refGreen : (v > 0 ? refRed : refGray);
                });

                const medianLinePlugin = {
                    id: 'temuAdsMedianLine',
                    afterDraw: function (chart) {
                        const yScale = chart.scales.y;
                        const xScale = chart.scales.x;
                        const ctx = chart.ctx;
                        const yPixel = yScale.getPixelForValue(median);
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
                const valueLabelsPlugin = {
                    id: 'temuAdsValueLabels',
                    afterDatasetsDraw: function (chart) {
                        const meta = chart.getDatasetMeta(0);
                        const ctx = chart.ctx;
                        ctx.save();
                        ctx.font = 'bold 11px Inter, system-ui, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        meta.data.forEach(function (point, i) {
                            ctx.fillStyle = labelColors[i];
                            ctx.fillText(fmtBadgeChartValue(metric, values[i]), point.x, point.y + ((i % 2 === 0) ? -10 : -20));
                        });
                        ctx.restore();
                    }
                };

                temuAdsBadgeChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: activeBadgeLabel,
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
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: { padding: { top: 24, right: 16, bottom: 12, left: 16 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) { return fmtBadgeChartValue(metric, ctx.parsed.y); }
                                }
                            },
                        },
                        scales: {
                            y: {
                                min: yMin,
                                max: yMax,
                                ticks: { callback: function (v) { return fmtBadgeChartValue(metric, v); } }
                            },
                            x: { ticks: { autoSkip: false, maxRotation: 60, minRotation: 45 } },
                        },
                    },
                    plugins: [medianLinePlugin, valueLabelsPlugin],
                });
            }

            function runningAdsRows(rows) {
                const seen = {};
                const out = [];
                (rows || []).forEach(function (r) {
                    if (rowPauseRunAction(r) !== 'run') return;
                    const gid = String(r.goods_id || '').trim();
                    const key = gid || ('sku:' + String(r.sku || ''));
                    if (seen[key]) return;
                    seen[key] = true;
                    out.push(r);
                });
                return out;
            }

            function dailyCreateBudget() {
                const el = document.getElementById('create-budget');
                const n = parseFloat(el && el.value ? el.value : 10);
                return (isFinite(n) && n >= 1) ? n : 10;
            }

            function moneyText(n) {
                return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            }

            function fillRunningAdsModal() {
                const rows = runningAdsRows(table ? table.getData(true) : []);
                const budgetEach = dailyCreateBudget();
                const totalBudget = rows.length * budgetEach;
                let spend = 0, impr = 0, clicks7 = 0, clicks30 = 0, orders = 0, orderAmt = 0;
                rows.forEach(function (r) {
                    spend += parseFloat(r.ad_spend) || 0;
                    impr += parseFloat(r.impressions) || 0;
                    clicks7 += parseFloat(r.clicks_l7) || 0;
                    clicks30 += parseFloat(r.clicks_l30) || 0;
                    orders += parseFloat(r.order_pay_cnt) || 0;
                    orderAmt += parseFloat(r.order_pay_amt) || 0;
                });
                const roas = spend > 0 ? (orderAmt / spend) : 0;
                const summary = document.getElementById('pause-run-running-summary');
                const pills = [
                    ['Running ads', rows.length.toLocaleString()],
                    ['Daily budget each', moneyText(budgetEach)],
                    ['Total ads budget', moneyText(totalBudget)],
                    ['Spend', moneyText(spend)],
                    ['Impr', Math.round(impr).toLocaleString()],
                    ['Clicks 7', Math.round(clicks7).toLocaleString()],
                    ['Clicks 30', Math.round(clicks30).toLocaleString()],
                    ['Ord', Math.round(orders).toLocaleString()],
                    ['Ord$', moneyText(orderAmt)],
                    ['ROAS', String(Math.round(roas))],
                ];
                summary.innerHTML = pills.map(function (p) {
                    return '<span class="badge bg-light text-dark border fs-6 p-2">' + p[0] + ': <strong>' + p[1] + '</strong></span>';
                }).join('');
                const tbody = document.querySelector('#pause-run-running-table tbody');
                tbody.innerHTML = rows.length ? rows.map(function (r) {
                    return '<tr>' +
                        '<td>' + String(r.sku || '') + '</td>' +
                        '<td>' + String(r.goods_id || '') + '</td>' +
                        '<td>' + String(r.ad_status || '') + '</td>' +
                        '<td>' + Number(r.clicks_l7 || 0).toLocaleString() + '</td>' +
                        '<td>' + Number(r.clicks_l30 || 0).toLocaleString() + '</td>' +
                        '<td>' + Number(r.impressions || 0).toLocaleString() + '</td>' +
                        '<td>' + moneyText(r.ad_spend) + '</td>' +
                        '<td>' + Number(r.order_pay_cnt || 0).toLocaleString() + '</td>' +
                        '<td>' + moneyText(r.order_pay_amt) + '</td>' +
                        '<td>' + String(Math.round(Number(r.roas) || 0)) + '</td>' +
                        '<td>' + String(Math.round(Number(r.acos) || 0)) + '%</td>' +
                        '<td>' + moneyText(budgetEach) + '</td>' +
                        '</tr>';
                }).join('') : '<tr><td colspan="12" class="text-center text-muted">No running ads in the current view.</td></tr>';
            }

            function setBadges(rows) {
                paintMetricBadges(rows);
                updateFilterCounts(rows);
            }

            function updateBadgesFromTable() {
                if (!table) return;
                paintMetricBadges(table.getData(true));
                snapshotBadgeHistory();
                updateFilterCounts();
            }

            const table = new Tabulator('#temu-ads-table', {
                ajaxURL: dataUrl(),
                ajaxResponse: function (url, params, response) {
                    const rows = response.data || [];
                    setBadges(rows, response);
                    return rows;
                },
                layout: 'fitData',
                height: '70vh',
                pagination: 'local',
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 250, true],
                placeholder: 'No Temu 2 ads data in temu2_campaign_reports yet.',
                columnDefaults: {
                    hozAlign: 'center',
                    headerHozAlign: 'center',
                    headerSort: true,
                    minWidth: 48,
                    resizable: true,
                },
                columns: [
                    { title: 'Period', field: 'period', width: 70, visible: false, sorter: 'string' },
                    {
                        title: '',
                        field: '_select',
                        width: 44,
                        minWidth: 44,
                        hozAlign: 'center',
                        headerHozAlign: 'center',
                        headerSort: false,
                        resizable: false,
                        frozen: true,
                        titleFormatter: function () {
                            const cb = document.createElement('input');
                            cb.type = 'checkbox';
                            cb.id = 'temu-ads-select-all';
                            cb.className = 'temu-ads-select-all';
                            cb.title = 'Select all filtered rows';
                            cb.addEventListener('click', function (e) { e.stopPropagation(); });
                            cb.addEventListener('change', function (e) {
                                e.stopPropagation();
                                if (!table) return;
                                (table.getRows('active') || []).forEach(function (row) {
                                    const id = rowGoodsId(row.getData());
                                    if (!id) return;
                                    if (cb.checked) selectedGoodsIds.add(id);
                                    else selectedGoodsIds.delete(id);
                                });
                                refreshSelectCheckboxes();
                                paintCreateBadge();
                            });
                            return cb;
                        },
                        formatter: function (cell) {
                            return selectCheckboxHtml(cell.getRow().getData() || {});
                        },
                        cellClick: function (e, cell) {
                            e.stopPropagation();
                            const id = rowGoodsId(cell.getRow().getData());
                            const cb = cell.getElement().querySelector('.temu-ads-row-cb');
                            if (!id || !cb) return;
                            if (selectedGoodsIds.has(id)) {
                                selectedGoodsIds.delete(id);
                                cb.checked = false;
                            } else {
                                selectedGoodsIds.add(id);
                                cb.checked = true;
                            }
                            refreshSelectCheckboxes();
                            paintCreateBadge();
                        },
                    },
                    {
                        title: 'Image',
                        field: 'image_path',
                        width: 52,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerHozAlign: 'center',
                        headerVertAlign: 'middle',
                        headerSort: false,
                        headerTooltip: 'Image from Product Master (Values.image_path)',
                        formatter: function (cell) {
                            const src = String(cell.getValue() || '').trim();
                            if (!src) return '';
                            return '<img class="temu-ads-thumb" src="' + src.replace(/"/g, '&quot;') + '" alt="">';
                        },
                    },
                    { title: 'SKU', field: 'sku', width: 120, minWidth: 80, sorter: 'string' },
                    { title: 'Inv', field: 'inv', width: 52, minWidth: 48, hozAlign: 'center', formatter: numFmt, sorter: 'number',
                      headerTooltip: 'Inventory from shopify_skus.inv' },
                    {
                        title: 'Ad',
                        field: 'create_ad',
                        width: 68,
                        minWidth: 56,
                        hozAlign: 'center',
                        sorter: function (a, b, aRow, bRow) {
                            function rank(row) {
                                const data = row.getData() || {};
                                const inv = parseInt(String(data.inv != null ? data.inv : 0).replace(/,/g, ''), 10) || 0;
                                if (String(data.ad_status || '') === 'No ad' && inv > 0) return 0;
                                if (inv <= 0) return 1;
                                return 2;
                            }
                            return rank(aRow) - rank(bRow);
                        },
                        headerTooltip: 'Create is shown only when Status is No ad and Inv > 0. Yellow dot = 0 inventory. Green dot = ad already available.',
                        formatter: function (cell) {
                            const data = cell.getRow().getData() || {};
                            const status = String(data.ad_status || '');
                            const inv = parseInt(String(data.inv != null ? data.inv : 0).replace(/,/g, ''), 10) || 0;
                            if (inv <= 0) {
                                return '<span class="temu-ad-dot is-zero-inv" title="0 inventory — Create hidden"></span>';
                            }
                            if (status === 'No ad') {
                                return '<button type="button" class="btn btn-sm btn-outline-warning create-row-ad-btn" title="Create Temu ad">Create</button>';
                            }
                            return '<span class="temu-ad-dot" title="Ad available"></span>';
                        },
                        cellClick: function (e, cell) {
                            e.stopPropagation();
                            if (!e.target.closest('.create-row-ad-btn')) return;
                            const goodsId = cell.getRow().getData().goods_id || '';
                            document.getElementById('create-goods-id').value = goodsId;
                            document.getElementById('create-roas').value = String(createRoasForGoods(goodsId, createAdDefaults().roas));
                            document.getElementById('create-ad-status').style.display = 'none';
                            new bootstrap.Modal(document.getElementById('createAdModal')).show();
                        }
                    },
                    { title: 'Ovl30', field: 'ovl30', width: 62, minWidth: 54, hozAlign: 'center', formatter: numFmt, sorter: 'number',
                      headerTooltip: 'Overall L30 sold units from Shopify (same as /temu-decrease OVL30)' },
                    {
                        title: 'Dil%',
                        field: 'dil_percent',
                        width: 62,
                        minWidth: 54,
                        hozAlign: 'center',
                        sorter: 'number',
                        headerTooltip: 'Dil% = Ovl30 ÷ Inv × 100. Same color schema as /temu-decrease: red &lt;25, green 25–50, pink 50%+.',
                        formatter: function (cell) {
                            const dil = parseFloat(cell.getValue()) || 0;
                            if (window.TemuAdsColorRules && typeof TemuAdsColorRules.dilHtml === 'function') {
                                return TemuAdsColorRules.dilHtml(dil);
                            }
                            return Math.round(dil) + '%';
                        }
                    },
                    { title: 'Impressions', field: 'impressions', width: 120, visible: false, hozAlign: 'center', formatter: numFmt, sorter: 'number',
                      headerTooltip: 'Impressions (Overall) — same as Temu Data Report' },
                    { title: 'Clicks 30', field: 'clicks_l30', width: 78, minWidth: 70, hozAlign: 'center', formatter: clicks30Fmt, sorter: 'number',
                      headerTooltip: 'Last 30 days clicks (Overall). Red when below 300.' },
                    { title: 'Clicks 7', field: 'clicks_l7', width: 70, minWidth: 62, hozAlign: 'center', formatter: clicksFmt, sorter: 'number',
                      headerTooltip: 'Last 7 days clicks (Overall). Red when at or above the shared L7 Clicks rule (default 70) — pause zone.' },
                    {
                        title: 'Pause/Run',
                        field: 'pause_run',
                        width: 86,
                        minWidth: 78,
                        hozAlign: 'center',
                        sorter: function (a, b, aRow, bRow) {
                            function action(row) {
                                const data = row.getData() || {};
                                if (window.TemuAdsColorRules && TemuAdsColorRules.pauseRunAction) {
                                    return TemuAdsColorRules.pauseRunAction(data);
                                }
                                return String(data.pause_run || '');
                            }
                            return action(aRow).localeCompare(action(bRow));
                        },
                        headerTooltip: 'Pause/Run from the current L7 Clicks slabs. Changing the rule updates these switches immediately. Click a switch to push to Temu.',
                        formatter: function (cell) {
                            if (!window.TemuAdsColorRules || typeof TemuAdsColorRules.pauseRunButtonHtml !== 'function') return '';
                            return TemuAdsColorRules.pauseRunButtonHtml(cell.getRow().getData() || {});
                        },
                        cellClick: function (e, cell) {
                            e.stopPropagation();
                            const btn = e.target.closest('.temu-pause-run-btn');
                            if (!btn || !window.TemuAdsColorRules || typeof TemuAdsColorRules.pushPauseRun !== 'function') return;
                            TemuAdsColorRules.pushPauseRun(btn, cell, @json(route('temu2.ads.toggle')));
                        }
                    },
                    {
                        title: 'Status',
                        field: 'ad_status',
                        width: 88,
                        minWidth: 72,
                        hozAlign: 'center',
                        sorter: 'string',
                        headerTooltip: 'Temu ad campaign status from ad.detail.query (Active / Paused / No ad). Not sync = API not confirmed.',
                        formatter: function (cell) {
                            const v = String(cell.getValue() || 'Not sync');
                            const label = v === 'Inactive' ? 'Paused' : v;
                            let cls = 'bg-secondary';
                            if (v === 'Active') cls = 'bg-success';
                            else if (v === 'Inactive' || v === 'Paused') cls = 'bg-warning text-dark';
                            else if (v === 'Deleted') cls = 'bg-dark';
                            else if (v === 'No ad') cls = 'bg-danger';
                            else if (v === 'Not sync') cls = 'bg-secondary';
                            return '<span class="badge ' + cls + '">' + label + '</span>';
                        }
                    },
                    {
                        title: 'Success',
                        field: 'pause_run_ok',
                        width: 64,
                        minWidth: 56,
                        hozAlign: 'center',
                        sorter: function (a, b) {
                            const rank = function (v) { return v === true ? 2 : (v === false ? 1 : 0); };
                            return rank(a) - rank(b);
                        },
                        headerTooltip: 'Result of the last Pause/Run push. Hover the red cross for the reason.',
                        formatter: function (cell) {
                            if (!window.TemuAdsColorRules || typeof TemuAdsColorRules.pauseRunResultHtml !== 'function') return '';
                            return TemuAdsColorRules.pauseRunResultHtml(cell.getRow().getData() || {});
                        }
                    },
                    { title: 'CTR', field: 'ctr', width: 56, minWidth: 50, hozAlign: 'center', formatter: pctFmt, sorter: 'number',
                      headerTooltip: 'CTR (Overall)' },
                    { title: 'CVR', field: 'cvr', width: 56, minWidth: 50, hozAlign: 'center', formatter: pctFmt, sorter: 'number',
                      headerTooltip: 'CVR (Overall) = Orders ÷ Clicks' },
                    { title: 'Cart', field: 'cart_cnt', width: 56, minWidth: 50, hozAlign: 'center', formatter: numFmt, sorter: 'number' },
                    { title: 'Ord', field: 'order_pay_cnt', width: 56, minWidth: 50, hozAlign: 'center', formatter: numFmt, sorter: 'number' },
                    { title: 'Ord$', field: 'order_pay_amt', width: 70, minWidth: 62, hozAlign: 'center', formatter: moneyFmt, sorter: 'number' },
                    { title: 'Spend 30', field: 'ad_spend', width: 76, minWidth: 68, hozAlign: 'center', formatter: moneyFmt, sorter: 'number' },
                    { title: 'Y Spend', field: 'spend_l1', width: 70, minWidth: 62, hozAlign: 'center', formatter: moneyFmt, sorter: 'number',
                      headerTooltip: 'Y Spend = last 1 day ad spend. Color from ROAS Rule: $0 red, $0.01–$5.99 yellow, $6–$9 green, above $9 pink.' },
                    {
                        title: 'T ROAS',
                        field: 't_roas',
                        width: 68,
                        minWidth: 60,
                        hozAlign: 'center',
                        headerTooltip: 'Target ROAS',
                        formatter: tRoasFmt,
                        sorter: function (a, b, aRow, bRow) {
                            return Number(targetRoasValue(aRow && aRow.getData())) - Number(targetRoasValue(bRow && bRow.getData()));
                        }
                    },
                    { title: 'ROAS', field: 'roas', width: 58, minWidth: 52, hozAlign: 'center', formatter: decFmt, sorter: 'number',
                      headerTooltip: 'Actual ROAS. Color from ROAS Rule ranges when set.' },
                    { title: 'ACOS', field: 'acos', width: 58, minWidth: 52, hozAlign: 'center', formatter: pctFmt, sorter: 'number',
                      headerTooltip: 'ACOS. Color from ROAS Rule spend slabs when set.' },
                    {
                        title: 'OK',
                        field: 'success',
                        width: 60,
                        hozAlign: 'center',
                        sorter: 'boolean',
                        formatter: function (cell) {
                            return cell.getValue()
                                ? '<span class="text-success"><i class="fas fa-check"></i></span>'
                                : '<span class="text-danger" title="' + (cell.getRow().getData().error_msg || '') + '"><i class="fas fa-x"></i></span>';
                        }
                    },
                    { title: 'Fetched', field: 'fetched_at', width: 72, minWidth: 64, formatter: fetchedFmt, sorter: 'string',
                      headerTooltip: 'Date the Ads API row was last fetched' },
                    {
                        title: 'Raw',
                        field: 'raw_response',
                        width: 70,
                        hozAlign: 'center',
                        sorter: function (a, b) {
                            return (a ? String(a).length : 0) - (b ? String(b).length : 0);
                        },
                        formatter: function () {
                            return '<button type="button" class="btn btn-sm btn-outline-secondary view-raw-btn" title="View raw JSON"><i class="fas fa-code"></i></button>';
                        },
                        cellClick: function (e, cell) {
                            e.stopPropagation();
                            const raw = cell.getRow().getData().raw_response;
                            const el = document.getElementById('raw-json-pre');
                            if (!raw) {
                                el.textContent = '(empty)';
                            } else {
                                try {
                                    el.textContent = JSON.stringify(JSON.parse(raw), null, 2);
                                } catch (err) {
                                    el.textContent = String(raw);
                                }
                            }
                            const data = cell.getRow().getData();
                            document.getElementById('rawJsonModalLabel').textContent =
                                'Raw API — Goods ' + (data.goods_id || '') + ' (' + (data.period || '') + ')';
                            new bootstrap.Modal(document.getElementById('rawJsonModal')).show();
                        }
                    },
                    { title: 'Goods ID', field: 'goods_id', width: 130, minWidth: 100, sorter: 'string' },
                ],
            });

            table.on('dataFiltered', function () {
                updateBadgesFromTable();
                refreshSelectCheckboxes();
            });
            table.on('dataLoaded', function () {
                pruneSelectedGoodsIds();
                if (typeof applyPauseRunSlabsToTable === 'function') applyPauseRunSlabsToTable();
                else updateBadgesFromTable();
                refreshSelectCheckboxes();
            });

            try {
            if (window.TemuAdsColorRules) {
                    if (typeof TemuAdsColorRules.configureChannel === 'function') {
                        TemuAdsColorRules.configureChannel('temu2_ads');
                    }
                    if (typeof TemuAdsColorRules.setUrls === 'function') {
                TemuAdsColorRules.setUrls(
                    @json(route('temu2.ads.color-rules')),
                            @json(route('temu2.ads.color-rules.save')),
                            @json(route('temu2.ads.auto-pause')),
                            @json(route('temu2.ads.toggle')),
                            @json(route('temu2.ads.auto-pause-cron')),
                            @json(route('temu2.ads.push-roas'))
                );
                    }
                    if (typeof TemuAdsColorRules.bindThresholdInput === 'function') {
                TemuAdsColorRules.bindThresholdInput(document.getElementById('temu-l7-clicks-red-threshold'));
                    }
                    if (typeof TemuAdsColorRules.bindRoasRuleSummary === 'function') {
                        TemuAdsColorRules.bindRoasRuleSummary(document.getElementById('roas-rule-summary'));
                    }
                    if (typeof TemuAdsColorRules.bindCronToggleButton === 'function') {
                        TemuAdsColorRules.bindCronToggleButton(
                            document.getElementById('temu-ads-cron-toggle-btn'),
                            document.getElementById('temu-ads-cron-status')
                        );
                    }
                    if (typeof TemuAdsColorRules.bindSaveRuleButton === 'function') {
                        TemuAdsColorRules.bindSaveRuleButton(
                            document.getElementById('temu-ads-save-rule-btn'),
                            document.getElementById('temu-ads-pause-status')
                        );
                    }
                    if (typeof TemuAdsColorRules.onChange === 'function') {
                TemuAdsColorRules.onChange(function () {
                            if (!document.activeElement || !document.activeElement.closest('#pause-run-slabs-tbody, #pause-run-inv-zero')) {
                                renderPauseRunSlabs();
                                syncPauseRunInvZeroCheckbox();
                            }
                            renderRoasRuleSlabs();
                    table.redraw(true);
                            if (typeof applyPauseRunSlabsToTable === 'function') applyPauseRunSlabsToTable();
                            else if (typeof updateBadgesFromTable === 'function') updateBadgesFromTable();
                        });
                    }
                }
            } catch (err) {
                console.warn('TemuAdsColorRules init skipped', err);
            }

            function pauseRunSlabStatus(html, ok) {
                const el = document.getElementById('pause-run-rule-status');
                if (!el) return;
                el.innerHTML = html
                    ? '<div class="alert ' + (ok ? 'alert-success' : 'alert-danger') + ' py-1 px-2 mb-0">' + html + '</div>'
                    : '';
            }

            function renderPauseRunSlabs(slabs) {
                const tbody = document.getElementById('pause-run-slabs-tbody');
                if (!tbody || !window.TemuAdsColorRules) return;
                const list = Array.isArray(slabs) ? slabs : TemuAdsColorRules.getPauseRunSlabs();
                tbody.innerHTML = '';
                list.forEach(function (slab) {
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td><input type="number" class="form-control form-control-sm pr-slab-min" min="0" step="1" value="' + slab.min + '"></td>' +
                        '<td><input type="number" class="form-control form-control-sm pr-slab-max" min="0" step="1" placeholder="and above" value="' +
                            (slab.max == null ? '' : slab.max) + '"></td>' +
                        '<td><select class="form-select form-select-sm pr-slab-action">' +
                            '<option value="run"' + (slab.action === 'run' ? ' selected' : '') + '>Run</option>' +
                            '<option value="pause"' + (slab.action === 'pause' ? ' selected' : '') + '>Pause</option>' +
                        '</select></td>' +
                        '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger pr-slab-remove" title="Remove">&times;</button></td>';
                    tbody.appendChild(tr);
                });
            }

            function collectPauseRunSlabs() {
                const rows = document.querySelectorAll('#pause-run-slabs-tbody tr');
                const slabs = [];
                rows.forEach(function (tr) {
                    const minEl = tr.querySelector('.pr-slab-min');
                    const maxEl = tr.querySelector('.pr-slab-max');
                    const actEl = tr.querySelector('.pr-slab-action');
                    const min = parseInt(minEl && minEl.value, 10);
                    const maxRaw = maxEl ? String(maxEl.value || '').trim() : '';
                    const max = maxRaw === '' ? null : parseInt(maxRaw, 10);
                    slabs.push({
                        min: isFinite(min) && min >= 0 ? min : 0,
                        max: (max === null || !isFinite(max)) ? null : max,
                        action: (actEl && actEl.value) === 'run' ? 'run' : 'pause',
                    });
                });
                return window.TemuAdsColorRules
                    ? TemuAdsColorRules.normalizePauseRunSlabs(slabs)
                    : slabs;
            }

            function applyPauseRunSlabsToTable() {
                if (!table || !window.TemuAdsColorRules) return 0;
                const rows = (typeof table.getRows === 'function' ? (table.getRows('all') || table.getRows()) : []) || [];
                let n = 0;
                rows.forEach(function (row) {
                    const data = row.getData() || {};
                    const action = TemuAdsColorRules.computedPauseRunAction
                        ? TemuAdsColorRules.computedPauseRunAction(data)
                        : TemuAdsColorRules.actionFromSlabs(data.clicks_l7 != null ? data.clicks_l7 : 0);
                    if (data.pause_run !== action) {
                        row.update({ pause_run: action });
                    }
                    const cell = typeof row.getCell === 'function' ? row.getCell('pause_run') : null;
                    if (cell && typeof cell.setValue === 'function') {
                        cell.setValue(action, true);
                    }
                    n++;
                });
                table.redraw(true);
                if (typeof updateBadgesFromTable === 'function') updateBadgesFromTable();
                return n;
            }

            renderPauseRunSlabs();
            syncPauseRunInvZeroCheckbox();

            const slabTbody = document.getElementById('pause-run-slabs-tbody');
            let pauseRunAutoApplyTimer = null;
            let pauseRunTemuPushTimer = null;
            function pushPauseRunRulesToTemu() {
                if (!window.TemuAdsColorRules || typeof TemuAdsColorRules.runAutoPauseCron !== 'function') return;
                pauseRunSlabStatus('Pushing Pause/Run changes to Temu…', true);
                TemuAdsColorRules.runAutoPauseCron(function (res) {
                    const ok = !!(res && res.ok && res.data && res.data.success !== false);
                    const msg = (res && res.data && res.data.message) ? res.data.message : (ok ? 'Temu update done' : 'Temu update failed');
                    pauseRunSlabStatus(msg, ok);
                    if (typeof table !== 'undefined' && table && typeof table.setData === 'function' && typeof dataUrl === 'function') {
                        table.setData(dataUrl());
                    }
                });
            }
            function autoApplyPauseRunRules() {
                if (pauseRunAutoApplyTimer) clearTimeout(pauseRunAutoApplyTimer);
                if (pauseRunTemuPushTimer) clearTimeout(pauseRunTemuPushTimer);
                pauseRunAutoApplyTimer = setTimeout(function () {
                    pauseRunAutoApplyTimer = null;
                    savePauseRunSlabsFromModal();
                    const n = applyPauseRunSlabsToTable();
                    pauseRunSlabStatus('Rules applied to ' + n + ' Pause/Run switches. Updating Temu…', true);
                }, 200);
                pauseRunTemuPushTimer = setTimeout(function () {
                    pauseRunTemuPushTimer = null;
                    pushPauseRunRulesToTemu();
                }, 900);
            }
            if (slabTbody) {
                slabTbody.addEventListener('click', function (e) {
                    const btn = e.target.closest('.pr-slab-remove');
                    if (!btn) return;
                    const rows = slabTbody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr').remove();
                    autoApplyPauseRunRules();
                });
                slabTbody.addEventListener('change', autoApplyPauseRunRules);
            }
            const addSlabBtn = document.getElementById('pause-run-slab-add-btn');
            if (addSlabBtn) {
                addSlabBtn.addEventListener('click', function () {
                    const current = collectPauseRunSlabs();
                    const last = current[current.length - 1];
                    const nextMin = last && last.max != null ? last.max + 1 : (last ? last.min + 1 : 0);
                    current.push({ min: nextMin, max: null, action: 'pause' });
                    renderPauseRunSlabs(current);
                    autoApplyPauseRunRules();
                });
            }
            const invZeroEl = document.getElementById('pause-run-inv-zero');
            if (invZeroEl) {
                invZeroEl.addEventListener('change', autoApplyPauseRunRules);
            }
            function syncPauseRunInvZeroCheckbox() {
                const el = document.getElementById('pause-run-inv-zero');
                if (!el || !window.TemuAdsColorRules || !TemuAdsColorRules.getPauseRunInvZero) return;
                el.checked = !!TemuAdsColorRules.getPauseRunInvZero();
            }

            function savePauseRunSlabsFromModal() {
                if (!window.TemuAdsColorRules) return collectPauseRunSlabs();
                const invZeroEl = document.getElementById('pause-run-inv-zero');
                if (invZeroEl && TemuAdsColorRules.setPauseRunInvZero) {
                    TemuAdsColorRules.setPauseRunInvZero(invZeroEl.checked, false);
                }
                const slabs = collectPauseRunSlabs();
                TemuAdsColorRules.setPauseRunSlabs(slabs, true);
                return slabs;
            }
            document.getElementById('pause-run-rule-save-btn').addEventListener('click', function () {
                savePauseRunSlabsFromModal();
                const n = applyPauseRunSlabsToTable();
                pauseRunSlabStatus('Saved and applied Pause/Run to ' + n + ' switches. Updating Temu…', true);
                pushPauseRunRulesToTemu();
            });
            document.getElementById('pause-run-rule-apply-btn').addEventListener('click', function () {
                savePauseRunSlabsFromModal();
                const n = applyPauseRunSlabsToTable();
                pauseRunSlabStatus('Saved and applied Pause/Run to ' + n + ' rows on this page.', true);
            });
            document.getElementById('pause-run-rule-apply-site-btn').addEventListener('click', function () {
                savePauseRunSlabsFromModal();
                const n = applyPauseRunSlabsToTable();
                pauseRunSlabStatus('Saved for /temu/ads and /temu-decrease. Applied Pause/Run to ' + n + ' rows on this page.', true);
            });

            function roasRuleStatus(html, ok) {
                const el = document.getElementById('roas-rule-status');
                if (!el) return;
                el.innerHTML = html
                    ? '<div class="alert ' + (ok ? 'alert-success' : 'alert-danger') + ' py-1 px-2 mb-0">' + html + '</div>'
                    : '';
            }

            function roasRuleNumAttr(v) {
                return v == null || v === '' ? '' : String(v);
            }

            function renderRoasRuleSlabs(slabs) {
                const tbody = document.getElementById('roas-rule-slabs-tbody');
                if (!tbody) return;
                const list = Array.isArray(slabs)
                    ? slabs
                    : (window.TemuAdsColorRules && TemuAdsColorRules.getRoasRuleSlabs
                        ? TemuAdsColorRules.getRoasRuleSlabs()
                        : []);
                tbody.innerHTML = '';
                list.forEach(function (slab) {
                    const tr = document.createElement('tr');
                    const style = slab.style || 'red';
                    tr.innerHTML =
                        '<td><input type="number" class="form-control form-control-sm rr-spend-min" min="0" step="0.01" placeholder="from" value="' + roasRuleNumAttr(slab.spend_min) + '"></td>' +
                        '<td><input type="number" class="form-control form-control-sm rr-spend-max" min="0" step="0.01" placeholder="and above" value="' + roasRuleNumAttr(slab.spend_max) + '"></td>' +
                        '<td><select class="form-select form-select-sm rr-style">' +
                            '<option value="red"' + (style === 'red' ? ' selected' : '') + '>Red text</option>' +
                            '<option value="yellow"' + (style === 'yellow' ? ' selected' : '') + '>Yellow</option>' +
                            '<option value="green"' + (style === 'green' ? ' selected' : '') + '>Green text</option>' +
                            '<option value="pink"' + (style === 'pink' ? ' selected' : '') + '>Black + pink bg</option>' +
                        '</select></td>' +
                        '<td><input type="number" class="form-control form-control-sm rr-target-roas" step="0.1" placeholder="T ROAS" title="Target ROAS" value="' + roasRuleNumAttr(slab.target_roas != null ? slab.target_roas : 8) + '"></td>' +
                        '<td><input type="number" class="form-control form-control-sm rr-roas-min" min="0" step="0.1" placeholder="ROAS from" value="' + roasRuleNumAttr(slab.roas_min) + '"></td>' +
                        '<td><input type="number" class="form-control form-control-sm rr-roas-max" min="0" step="0.1" placeholder="ROAS to" value="' + roasRuleNumAttr(slab.roas_max) + '"></td>' +
                        '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger rr-slab-remove" title="Remove">&times;</button></td>';
                    tbody.appendChild(tr);
                });
            }

            function collectRoasRuleSlabs() {
                const rows = document.querySelectorAll('#roas-rule-slabs-tbody tr');
                const slabs = [];
                rows.forEach(function (tr) {
                    const spendMin = tr.querySelector('.rr-spend-min');
                    const spendMax = tr.querySelector('.rr-spend-max');
                    const roasMin = tr.querySelector('.rr-roas-min');
                    const roasMax = tr.querySelector('.rr-roas-max');
                    const targetRoas = tr.querySelector('.rr-target-roas');
                    const styleEl = tr.querySelector('.rr-style');
                    slabs.push({
                        spend_min: spendMin ? spendMin.value : '',
                        spend_max: spendMax ? spendMax.value : '',
                        roas_min: roasMin ? roasMin.value : '',
                        roas_max: roasMax ? roasMax.value : '',
                        target_roas: targetRoas ? targetRoas.value : '',
                        style: styleEl ? styleEl.value : 'red',
                    });
                });
                return window.TemuAdsColorRules && TemuAdsColorRules.normalizeRoasRuleSlabs
                    ? TemuAdsColorRules.normalizeRoasRuleSlabs(slabs)
                    : slabs;
            }

            function saveRoasRuleFromModal() {
                const slabs = collectRoasRuleSlabs();
                if (window.TemuAdsColorRules && TemuAdsColorRules.setRoasRuleSlabs) {
                    TemuAdsColorRules.setRoasRuleSlabs(slabs, true);
                }
                return slabs;
            }

            renderRoasRuleSlabs();

            const roasRuleTbody = document.getElementById('roas-rule-slabs-tbody');
            if (roasRuleTbody) {
                roasRuleTbody.addEventListener('click', function (e) {
                    const btn = e.target.closest('.rr-slab-remove');
                    if (!btn) return;
                    const rows = roasRuleTbody.querySelectorAll('tr');
                    if (rows.length <= 1) return;
                    btn.closest('tr').remove();
                });
            }
            const addRoasSlabBtn = document.getElementById('roas-rule-slab-add-btn');
            if (addRoasSlabBtn) {
                addRoasSlabBtn.addEventListener('click', function () {
                    const current = collectRoasRuleSlabs();
                    const last = current[current.length - 1];
                    const nextMin = last && last.spend_max != null ? Number(last.spend_max) + 0.01 : 0;
                    current.push({ spend_min: nextMin, spend_max: null, roas_min: null, roas_max: null, target_roas: 8, style: 'red' });
                    renderRoasRuleSlabs(current);
                });
            }
            const addRoasRangeBtn = document.getElementById('roas-rule-range-add-btn');
            if (addRoasRangeBtn) {
                addRoasRangeBtn.addEventListener('click', function () {
                    const current = collectRoasRuleSlabs();
                    const last = current.filter(function (s) { return s.roas_min != null || s.roas_max != null; }).pop();
                    const nextMin = last && last.roas_max != null ? Number(last.roas_max) + 0.1 : 0;
                    current.push({ spend_min: null, spend_max: null, roas_min: nextMin, roas_max: null, target_roas: 8, style: 'green' });
                    renderRoasRuleSlabs(current);
                });
            }
            document.getElementById('roas-rule-save-btn').addEventListener('click', function () {
                saveRoasRuleFromModal();
                roasRuleStatus('ROAS Rule saved.', true);
            });
            document.getElementById('roas-rule-apply-btn').addEventListener('click', function () {
                saveRoasRuleFromModal();
                if (table) table.redraw(true);
                roasRuleStatus('ROAS Rule saved and applied to Spend 1 / T ROAS / ROAS columns.', true);
            });

            function rowHasTemuAd(row) {
                const status = String((row && row.ad_status) || '');
                return status === 'Active' || status === 'Inactive';
            }

            function queuePushRoasItems() {
                const usingSelection = hasRowSelection();
                const rows = ((table && table.getData(true)) || []).filter(function (row) {
                    if (!rowHasTemuAd(row)) return false;
                    if (usingSelection && !selectedGoodsIds.has(rowGoodsId(row))) return false;
                    return !!rowGoodsId(row);
                });
                const items = [];
                const seen = {};
                rows.forEach(function (row) {
                    const gid = rowGoodsId(row);
                    if (!gid || seen[gid]) return;
                    seen[gid] = true;
                    items.push({
                        goods_id: gid,
                        roas: createRoasForGoods(gid, 8),
                    });
                });
                return { items: items, usingSelection: usingSelection };
            }

            async function runPushRoasRule() {
                const slabs = saveRoasRuleFromModal();
                if (table) table.redraw(true);
                const queued = queuePushRoasItems();
                const items = queued.items;
                if (!items.length) {
                    roasRuleStatus(queued.usingSelection
                        ? 'No selected Active/Inactive ads to push.'
                        : 'No visible Active/Inactive ads to push.', false);
                    return;
                }
                if (!confirm(
                    (queued.usingSelection
                        ? 'Push Target ROAS to Temu for the ' + items.length + ' selected ads?'
                        : 'Push Target ROAS to Temu for all ' + items.length + ' visible ads?') +
                    '\nEach ad uses T ROAS from its Spend 1 slab via temu.searchrec.ad.modify.'
                )) {
                    return;
                }
                const btn = document.getElementById('roas-rule-push-btn');
                if (btn) btn.disabled = true;
                let updated = 0;
                let failed = 0;
                const failNotes = [];
                const chunkSize = 8;
                for (let i = 0; i < items.length; i += chunkSize) {
                    const chunk = items.slice(i, i + chunkSize);
                    roasRuleStatus(
                        '<i class="fas fa-spinner fa-spin me-1"></i> Pushing ROAS ' +
                        Math.min(i + chunk.length, items.length) + '/' + items.length + '…',
                        true
                    );
                    const el = document.getElementById('roas-rule-status');
                    if (el && el.firstElementChild) {
                        el.firstElementChild.className = 'alert alert-info py-1 px-2 mb-0';
                    }
                    try {
                        const res = window.TemuAdsColorRules && TemuAdsColorRules.pushRoasRule
                            ? await TemuAdsColorRules.pushRoasRule(chunk, { slabs: slabs })
                            : { ok: false, data: { message: 'Push ROAS function is not available' } };
                        const data = res.data || {};
                        updated += (data.updated && data.updated.length) ? data.updated.length : 0;
                        if (data.failed && data.failed.length) {
                            failed += data.failed.length;
                            data.failed.slice(0, 5).forEach(function (row) {
                                failNotes.push((row.goods_id || '') + ': ' + (row.message || 'failed'));
                            });
                        } else if (!res.ok && !(data.updated && data.updated.length)) {
                            failed += chunk.length;
                            failNotes.push(data.message || 'Push failed');
                        }
                    } catch (err) {
                        failed += chunk.length;
                        failNotes.push(err && err.message ? err.message : 'network error');
                    }
                }
                const note = failNotes.length
                    ? ' — ' + failNotes.slice(0, 3).join('; ')
                    : '';
                roasRuleStatus(
                    'Pushed ROAS for ' + updated + '/' + items.length + ' ads' +
                    (failed ? ', failed ' + failed + note : ''),
                    failed === 0
                );
                if (btn) btn.disabled = false;
            }

            const pushRoasBtn = document.getElementById('roas-rule-push-btn');
            if (pushRoasBtn) {
                pushRoasBtn.addEventListener('click', function () {
                    runPushRoasRule();
                });
            }

            const TABULATOR_COLUMN_CHANNEL = 'temu2_ads';
            const TABULATOR_COLUMN_VISIBILITY_URL = @json(url('/tabulator-column-visibility'));
            const TABULATOR_COLUMN_ORDER_URL = @json(url('/tabulator-column-order'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            const csrfHeaders = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken ? csrfToken.getAttribute('content') : '',
            };
            let savedColumnVisibilityMap = {};
            let applyingColumnOrder = false;
            const COL_VIS_CATEGORY_KEYS = ['basic', 'ads', 'others'];
            const COL_VIS_CATEGORY_LABELS = {
                basic: 'Basic',
                ads: 'Ads Statistics',
                others: 'Others',
            };
            const COL_VIS_CAT_STORAGE = 'temu_ads_col_vis_cats';
            const LOCKED_HIDDEN_FIELDS = { impressions: true };
            const SKIP_COLUMN_BOX_FIELDS = { _select: true, impressions: true };

            function isLockedHiddenField(field) {
                return !!LOCKED_HIDDEN_FIELDS[String(field || '')];
            }

            function skipColumnBoxField(field) {
                return !!SKIP_COLUMN_BOX_FIELDS[String(field || '')];
            }

            function columnTitle(col) {
                const def = col.getDefinition ? col.getDefinition() : {};
                const raw = def.title || def.field || '';
                return String(raw).replace(/<[^>]*>/g, '').trim() || String(def.field || '');
            }

            function classifyTemuAdsColumn(field) {
                const f = String(field || '');
                if (/^(sku|inv|image_path|ad_status|create_ad|ovl30|dil_percent|goods_id|period)$/.test(f)) return 'basic';
                if (/^(impressions|clicks_l30|clicks_l7|pause_run|pause_run_ok|ctr|cvr|cart_cnt|order_pay_cnt|order_pay_amt|ad_spend|spend_l1|t_roas|roas|acos)$/.test(f)) {
                    return 'ads';
                }
                return 'others';
            }

            function loadCategoryOverrides() {
                try {
                    const parsed = JSON.parse(localStorage.getItem(COL_VIS_CAT_STORAGE) || '{}');
                    return parsed && typeof parsed === 'object' ? parsed : {};
                } catch (e) {
                    return {};
                }
            }

            function saveCategoryOverrides(map) {
                try { localStorage.setItem(COL_VIS_CAT_STORAGE, JSON.stringify(map || {})); } catch (e) { /* ignore */ }
            }

            function resolveCategory(field) {
                const over = loadCategoryOverrides();
                if (over[field] && COL_VIS_CATEGORY_KEYS.indexOf(over[field]) !== -1) return over[field];
                return classifyTemuAdsColumn(field);
            }

            function syncGroupHeaderCheckbox(groupEl) {
                if (!groupEl) return;
                const headerCb = groupEl.querySelector('.col-vis-group-toggle');
                const itemCbs = groupEl.querySelectorAll('.col-vis-field-toggle');
                if (!headerCb || !itemCbs.length) {
                    if (headerCb) {
                        headerCb.checked = false;
                        headerCb.indeterminate = false;
                    }
                    return;
                }
                let checked = 0;
                itemCbs.forEach(function (cb) { if (cb.checked) checked++; });
                headerCb.checked = checked === itemCbs.length;
                headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
            }

            function syncSelectionHeaderCheckbox() {
                const menu = document.getElementById('temu-ads-column-dropdown-menu');
                if (!menu) return;
                menu.querySelectorAll('.col-vis-group').forEach(syncGroupHeaderCheckbox);
                const headerCb = menu.querySelector('.col-vis-selections-toggle');
                const itemCbs = menu.querySelectorAll('.col-vis-field-toggle');
                if (!headerCb || !itemCbs.length) return;
                let checked = 0;
                itemCbs.forEach(function (cb) { if (cb.checked) checked++; });
                headerCb.checked = checked === itemCbs.length;
                headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
            }

            function saveColumnVisibilityToServer() {
                const visibility = {};
                const boxChecks = document.querySelectorAll('#temu-ads-column-dropdown-menu .col-vis-field-toggle');
                if (boxChecks.length) {
                    boxChecks.forEach(function (cb) {
                        if (cb.value) visibility[cb.value] = !!cb.checked;
                    });
                } else {
                    table.getColumns().forEach(function (col) {
                        const field = col.getField && col.getField();
                        if (field) visibility[field] = col.isVisible();
                    });
                }
                savedColumnVisibilityMap = visibility;
                fetch(TABULATOR_COLUMN_VISIBILITY_URL, {
                    method: 'POST',
                    headers: csrfHeaders,
                    body: JSON.stringify({
                        channel: TABULATOR_COLUMN_CHANNEL,
                        visibility: visibility,
                    }),
                }).catch(function (err) { console.error('Error saving column visibility:', err); });
            }

            function applyColumnVisibility(map) {
                if (!map || typeof map !== 'object') return;
                savedColumnVisibilityMap = map;
                table.getColumns().forEach(function (col) {
                    const field = col.getField && col.getField();
                    if (!field) return;
                    if (field === '_select') {
                        col.show();
                        return;
                    }
                    if (isLockedHiddenField(field)) {
                        col.hide();
                        return;
                    }
                    if (!map.hasOwnProperty(field)) return;
                    if (map[field]) col.show();
                    else col.hide();
                });
            }

            function currentColumnOrder() {
                return table.getColumns()
                    .map(function (col) { return col.getField && col.getField(); })
                    .filter(Boolean);
            }

            function applyColumnOrder(order) {
                if (!table || !Array.isArray(order) || !order.length) return;
                const existing = currentColumnOrder();
                if (!existing.length) return;
                const valid = [];
                const seen = {};
                order.forEach(function (f) {
                    if (!f || seen[f] || existing.indexOf(f) === -1) return;
                    seen[f] = true;
                    valid.push(f);
                });
                existing.forEach(function (f) {
                    if (seen[f]) return;
                    if (f === 'spend_l1' || f === 't_roas') return;
                    valid.push(f);
                    if (f === 'ad_spend' && existing.indexOf('spend_l1') !== -1) {
                        valid.push('spend_l1');
                        seen.spend_l1 = true;
                        if (existing.indexOf('t_roas') !== -1) {
                            valid.push('t_roas');
                            seen.t_roas = true;
                        }
                    }
                });
                existing.forEach(function (f) {
                    if (!seen[f]) valid.push(f);
                });
                function pinAfter(list, field, after) {
                    const i = list.indexOf(field);
                    const j = list.indexOf(after);
                    if (i === -1 || j === -1) return;
                    list.splice(i, 1);
                    list.splice(list.indexOf(after) + 1, 0, field);
                }
                pinAfter(valid, 'create_ad', 'inv');
                pinAfter(valid, 'ovl30', 'create_ad');
                pinAfter(valid, 'dil_percent', 'ovl30');
                pinAfter(valid, 'ad_status', 'pause_run');
                applyingColumnOrder = true;
                try {
                    for (let i = 0; i < valid.length; i++) {
                        const field = valid[i];
                        const cols = table.getColumns().filter(function (c) { return !!c.getField(); });
                        const currentIdx = cols.findIndex(function (c) { return c.getField() === field; });
                        if (currentIdx === i || currentIdx < 0) continue;
                        if (i === 0) {
                            const firstField = cols[0].getField();
                            if (firstField && firstField !== field) {
                                table.moveColumn(field, firstField, false);
                            }
                        } else if (valid[i - 1]) {
                            table.moveColumn(field, valid[i - 1], true);
                        }
                    }
                } catch (err) {
                    console.error('Error applying column order:', err);
                } finally {
                    applyingColumnOrder = false;
                }
            }

            function saveColumnOrderToServer() {
                if (applyingColumnOrder) return;
                const order = currentColumnOrder();
                if (!order.length) return;
                fetch(TABULATOR_COLUMN_ORDER_URL, {
                    method: 'POST',
                    headers: csrfHeaders,
                    body: JSON.stringify({
                        channel: TABULATOR_COLUMN_CHANNEL,
                        order: order,
                    }),
                }).catch(function (err) { console.error('Error saving column order:', err); });
            }

            function orderFromBox() {
                return Array.from(document.querySelectorAll('#temu-ads-column-dropdown-menu .col-vis-item'))
                    .map(function (el) { return el.dataset.field; })
                    .filter(Boolean);
            }

            function persistBoxOrderAndCategories() {
                const overrides = loadCategoryOverrides();
                document.querySelectorAll('#temu-ads-column-dropdown-menu .col-vis-group').forEach(function (group) {
                    const cat = group.dataset.category;
                    group.querySelectorAll('.col-vis-item').forEach(function (item) {
                        if (item.dataset.field && cat) overrides[item.dataset.field] = cat;
                    });
                });
                saveCategoryOverrides(overrides);
                applyColumnOrder(orderFromBox());
                saveColumnOrderToServer();
                syncSelectionHeaderCheckbox();
            }

            function bindColumnBoxDrag(root) {
                if (!root) return;
                let dragEl = null;

                function clearDropMarks() {
                    root.querySelectorAll('.col-vis-drop-before, .col-vis-drop-after, .col-vis-drop-over').forEach(function (el) {
                        el.classList.remove('col-vis-drop-before', 'col-vis-drop-after', 'col-vis-drop-over');
                    });
                }

                root.querySelectorAll('.col-vis-item').forEach(function (item) {
                    item.draggable = true;
                    const checkbox = item.querySelector('.col-vis-field-toggle');
                    if (checkbox) {
                        item.dataset.field = checkbox.value;
                        checkbox.addEventListener('mousedown', function (e) { e.stopPropagation(); });
                    }
                    item.addEventListener('dragstart', function (e) {
                        if (e.target && e.target.closest && e.target.closest('input')) {
                            e.preventDefault();
                            return;
                        }
                        dragEl = item;
                        item.classList.add('col-vis-dragging');
                        e.dataTransfer.setData('text/plain', item.dataset.field || '');
                        e.dataTransfer.effectAllowed = 'move';
                    });
                    item.addEventListener('dragend', function () {
                        item.classList.remove('col-vis-dragging');
                        clearDropMarks();
                        dragEl = null;
                    });
                    item.addEventListener('dragover', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (!dragEl || dragEl === item) return;
                        const rect = item.getBoundingClientRect();
                        const before = e.clientY < rect.top + rect.height / 2;
                        item.classList.toggle('col-vis-drop-before', before);
                        item.classList.toggle('col-vis-drop-after', !before);
                        e.dataTransfer.dropEffect = 'move';
                    });
                    item.addEventListener('dragleave', function () {
                        item.classList.remove('col-vis-drop-before', 'col-vis-drop-after');
                    });
                    item.addEventListener('drop', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const before = item.classList.contains('col-vis-drop-before');
                        clearDropMarks();
                        if (!dragEl || dragEl === item) return;
                        const list = item.parentNode;
                        if (before) list.insertBefore(dragEl, item);
                        else list.insertBefore(dragEl, item.nextSibling);
                        persistBoxOrderAndCategories();
                    });
                });

                root.querySelectorAll('.col-vis-group-list').forEach(function (list) {
                    const group = list.closest('.col-vis-group');
                    list.addEventListener('dragover', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (group) group.classList.add('col-vis-drop-over');
                        e.dataTransfer.dropEffect = 'move';
                    });
                    list.addEventListener('dragleave', function (e) {
                        if (group && !group.contains(e.relatedTarget)) {
                            group.classList.remove('col-vis-drop-over');
                        }
                    });
                    list.addEventListener('drop', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (group) group.classList.remove('col-vis-drop-over');
                        if (!dragEl) return;
                        if (e.target.closest('.col-vis-item')) return;
                        list.appendChild(dragEl);
                        persistBoxOrderAndCategories();
                    });
                });
            }

            function buildColumnDropdown(map) {
                const menu = document.getElementById('temu-ads-column-dropdown-menu');
                if (!menu) return;
                const vis = (map && typeof map === 'object') ? map : savedColumnVisibilityMap;
                menu.innerHTML = '';

                const showAllLi = document.createElement('li');
                showAllLi.className = 'col-vis-full';
                showAllLi.innerHTML = '<a class="dropdown-item py-1" href="#" id="temu-ads-show-all-columns-btn"><i class="fa fa-eye"></i> Show All</a>';
                menu.appendChild(showAllLi);

                const boxLi = document.createElement('li');
                boxLi.className = 'col-vis-full';
                const wrap = document.createElement('div');
                wrap.className = 'col-vis-selections';

                const selTitle = document.createElement('label');
                selTitle.className = 'col-vis-selections-title';
                const selCb = document.createElement('input');
                selCb.type = 'checkbox';
                selCb.className = 'col-vis-selections-toggle';
                selCb.title = 'Select / deselect all columns';
                selTitle.appendChild(selCb);
                selTitle.appendChild(document.createTextNode('Selections'));
                wrap.appendChild(selTitle);

                const groupsWrap = document.createElement('div');
                groupsWrap.className = 'col-vis-groups';
                const lists = {};
                COL_VIS_CATEGORY_KEYS.forEach(function (cat) {
                    const group = document.createElement('div');
                    group.className = 'col-vis-group';
                    group.dataset.category = cat;
                    const titleEl = document.createElement('label');
                    titleEl.className = 'col-vis-group-title';
                    const groupCb = document.createElement('input');
                    groupCb.type = 'checkbox';
                    groupCb.className = 'col-vis-group-toggle';
                    groupCb.dataset.group = cat;
                    groupCb.title = 'Select / deselect all in ' + COL_VIS_CATEGORY_LABELS[cat];
                    titleEl.appendChild(groupCb);
                    titleEl.appendChild(document.createTextNode(COL_VIS_CATEGORY_LABELS[cat]));
                    group.appendChild(titleEl);
                    const list = document.createElement('div');
                    list.className = 'col-vis-group-list';
                    group.appendChild(list);
                    groupsWrap.appendChild(group);
                    lists[cat] = list;
                });

                table.getColumns().forEach(function (col) {
                    const field = col.getField && col.getField();
                    if (!field || skipColumnBoxField(field) || isLockedHiddenField(field)) return;
                    const title = columnTitle(col);
                    const cat = resolveCategory(field);
                    const item = document.createElement('div');
                    item.className = 'col-vis-item';
                    item.dataset.field = field;
                    item.dataset.group = cat;
                    const label = document.createElement('label');
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.value = field;
                    checkbox.className = 'col-vis-field-toggle';
                    checkbox.dataset.group = cat;
                    checkbox.checked = vis.hasOwnProperty(field) ? (vis[field] !== false) : col.isVisible();
                    label.appendChild(checkbox);
                    label.appendChild(document.createTextNode(title));
                    label.title = title + ' — drag to reorder';
                    item.appendChild(label);
                    lists[cat].appendChild(item);
                });

                wrap.appendChild(groupsWrap);
                boxLi.appendChild(wrap);
                menu.appendChild(boxLi);
                syncSelectionHeaderCheckbox();
                bindColumnBoxDrag(wrap);
            }

            function loadAndApplyColumnVisibility() {
                const visReq = fetch(TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                    method: 'GET',
                    headers: csrfHeaders,
                }).then(function (r) { return r.json(); });
                const orderReq = fetch(TABULATOR_COLUMN_ORDER_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                    method: 'GET',
                    headers: csrfHeaders,
                }).then(function (r) { return r.json(); }).catch(function () { return {}; });

                Promise.all([visReq, orderReq])
                    .then(function (results) {
                        const saved = results[0];
                        const orderResp = results[1];
                        const map = (saved && typeof saved === 'object') ? saved : {};
                        applyColumnVisibility(map);
                        if (orderResp && orderResp.success && Array.isArray(orderResp.order)) {
                            applyColumnOrder(orderResp.order);
                        }
                        buildColumnDropdown(map);
                    })
                    .catch(function (err) {
                        console.error('Error loading column visibility:', err);
                        buildColumnDropdown({});
                    });
            }

            table.on('tableBuilt', loadAndApplyColumnVisibility);

            const colMenu = document.getElementById('temu-ads-column-dropdown-menu');
            if (colMenu) {
                colMenu.addEventListener('change', function (e) {
                    if (e.target.type !== 'checkbox') return;
                    if (e.target.classList.contains('col-vis-selections-toggle')) {
                        const checked = e.target.checked;
                        colMenu.querySelectorAll('.col-vis-field-toggle').forEach(function (cb) {
                            cb.checked = checked;
                            const col = table.getColumn(cb.value);
                            if (!col) return;
                            if (checked) col.show();
                            else col.hide();
                        });
                        e.target.indeterminate = false;
                        syncSelectionHeaderCheckbox();
                        saveColumnVisibilityToServer();
                        return;
                    }
                    if (e.target.classList.contains('col-vis-group-toggle')) {
                        const checked = e.target.checked;
                        const groupEl = e.target.closest('.col-vis-group');
                        const itemCbs = groupEl
                            ? groupEl.querySelectorAll('.col-vis-field-toggle')
                            : colMenu.querySelectorAll('.col-vis-field-toggle[data-group="' + e.target.dataset.group + '"]');
                        itemCbs.forEach(function (cb) {
                            cb.checked = checked;
                            const col = table.getColumn(cb.value);
                            if (!col) return;
                            if (checked) col.show();
                            else col.hide();
                        });
                        e.target.indeterminate = false;
                        syncSelectionHeaderCheckbox();
                        saveColumnVisibilityToServer();
                        return;
                    }
                    const col = table.getColumn(e.target.value);
                    if (col) {
                        if (e.target.checked) col.show();
                        else col.hide();
                    }
                    syncSelectionHeaderCheckbox();
                    saveColumnVisibilityToServer();
                });
                colMenu.addEventListener('click', function (e) {
                    const showAll = e.target.closest('#temu-ads-show-all-columns-btn');
                    if (!showAll) return;
                    e.preventDefault();
                    e.stopPropagation();
                    table.getColumns().forEach(function (col) {
                        const field = col.getField && col.getField();
                        if (isLockedHiddenField(field)) {
                            col.hide();
                            return;
                        }
                        col.show();
                    });
                    buildColumnDropdown({});
                    saveColumnVisibilityToServer();
                });
            }

            document.getElementById('period-filter').addEventListener('change', function () {
                table.setData(dataUrl());
            });

            function applySearchFilters() {
                const q = currentFilterQuery();
                if (!q.goodsQ && !q.skuQ && !q.statusQ && !q.pauseRunQ && !q.invQ && !q.dilQ && !q.clicksQ) {
                    table.clearFilter(true);
                    updateBadgesFromTable();
                    return;
                }
                table.setFilter(function (data) {
                    return rowMatchesQuery(data, q, '');
                });
                updateBadgesFromTable();
            }
            document.getElementById('search-goods-id').addEventListener('input', applySearchFilters);
            document.getElementById('search-sku').addEventListener('input', applySearchFilters);
            document.getElementById('status-filter').addEventListener('change', applySearchFilters);
            document.getElementById('pause-run-filter').addEventListener('change', applySearchFilters);
            document.getElementById('inv-filter').addEventListener('change', applySearchFilters);
            document.getElementById('dil-filter').addEventListener('change', applySearchFilters);
            document.getElementById('clicks-filter').addEventListener('change', applySearchFilters);

            document.getElementById('export-btn').addEventListener('click', function () {
                table.download('csv', 'temu-ads-api-reports.csv', {}, {
                    documentProcessing: function (doc) {
                        // strip huge raw_response from CSV export
                        return doc;
                    }
                });
            });

            function showCreateStatus(html) {
                const el = document.getElementById('create-ad-status');
                el.style.display = 'block';
                el.innerHTML = html;
            }

            function queueCreateGoodsIds() {
                return queueCreateGoodsIdsFromRows(createSourceRows());
            }

            function createAdDefaults() {
                const budgetEl = document.getElementById('create-budget');
                const roasEl = document.getElementById('create-roas');
                const budget = parseFloat(budgetEl && budgetEl.value ? budgetEl.value : 10);
                const roas = parseFloat(roasEl && roasEl.value ? roasEl.value : 4);
                return {
                    budget: (isFinite(budget) && budget >= 1) ? budget : 10,
                    roas: (isFinite(roas) && roas >= 0.1) ? roas : 4,
                };
            }

            function rowByGoodsId(goodsId) {
                const id = String(goodsId || '');
                if (!id || !table) return null;
                const rows = table.getData(true) || [];
                for (let i = 0; i < rows.length; i++) {
                    if (String(rows[i].goods_id || '') === id) return rows[i];
                }
                return null;
            }

            function createRoasForGoods(goodsId, fallbackRoas) {
                return (isFinite(Number(fallbackRoas)) && Number(fallbackRoas) >= 0.1) ? Number(fallbackRoas) : 4;
            }

            async function runBulkCreateQueue() {
                const usingSelection = hasRowSelection();
                const ids = queueCreateGoodsIds();
                const status = document.getElementById('fetch-status');
                const badge = document.getElementById('create-count');
                if (!ids.length) {
                    status.style.display = 'block';
                    status.innerHTML = usingSelection
                        ? '<div class="alert alert-warning py-2 mb-0">No selected rows can be created (need Status No ad and Inv &gt; 0).</div>'
                        : '<div class="alert alert-warning py-2 mb-0">No Create queue rows (Status No ad and Inv &gt; 0).</div>';
                    return;
                }
                const defaults = createAdDefaults();
                if (!confirm(
                    (usingSelection
                        ? 'Create Temu ads for the ' + ids.length + ' selected goods?'
                        : 'Create Temu ads for all ' + ids.length + ' required goods?') + '\n' +
                    'Daily budget: $' + defaults.budget + '\n' +
                    'Target ROAS: ' + defaults.roas + '\n' +
                    '(Same as Create ads Rule)'
                )) {
                    return;
                }
                if (badge) badge.style.pointerEvents = 'none';
                status.style.display = 'block';
                let created = 0;
                let failed = 0;
                const failNotes = [];
                const chunkSize = 5;
                for (let i = 0; i < ids.length; i += chunkSize) {
                    const chunk = ids.slice(i, i + chunkSize);
                    status.innerHTML = '<div class="alert alert-info py-2 mb-0"><i class="fas fa-spinner fa-spin me-1"></i> Creating ads ' +
                        Math.min(i + chunk.length, ids.length) + '/' + ids.length +
                        ' (budget $' + defaults.budget + ', ROAS ' + defaults.roas + ')…</div>';
                    try {
                        const res = await fetch(@json(route('temu2.ads.create-bulk')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify({
                                goods_ids: chunk,
                                budget: defaults.budget,
                                roas: defaults.roas,
                                roas_by_goods: chunk.reduce(function (map, gid) {
                                    map[gid] = createRoasForGoods(gid, defaults.roas);
                                    return map;
                                }, {}),
                            }),
                        });
                        const data = await res.json();
                        created += (data.created && data.created.length) ? data.created.length : 0;
                        if (data.failed && data.failed.length) {
                            failed += data.failed.length;
                            data.failed.slice(0, 5).forEach(function (row) {
                                failNotes.push((row.goods_id || '') + ': ' + (row.message || 'failed'));
                            });
                        }
                    } catch (err) {
                        failed += chunk.length;
                        failNotes.push('Chunk failed: ' + (err && err.message ? err.message : 'network error'));
                    }
                    if (badge) {
                        setBadgeVal('create-count', Math.max(ids.length - created - failed, 0).toLocaleString());
                    }
                }
                let cls = failed === 0 ? 'alert-success' : (created > 0 ? 'alert-warning' : 'alert-danger');
                let msg = 'Created ' + created + '/' + ids.length + ' ads (budget $' + defaults.budget + ', ROAS ' + defaults.roas + ')';
                if (failed > 0) msg += '. Failed ' + failed;
                if (failNotes.length) msg += ' — ' + failNotes.slice(0, 3).join('; ');
                status.innerHTML = '<div class="alert ' + cls + ' py-2 mb-0">' + msg + '</div>';
                if (badge) badge.style.pointerEvents = '';
                table.setData(dataUrl());
            }

            document.getElementById('create-count').addEventListener('click', function (e) {
                if (e.target.closest('.temu-ads-history-dot')) return;
                runBulkCreateQueue();
            });

            document.getElementById('pause-run-count').addEventListener('click', function (e) {
                if (e.target.closest('.temu-ads-history-dot')) return;
                fillRunningAdsModal();
                new bootstrap.Modal(document.getElementById('pauseRunRunningModal')).show();
            });

            document.querySelectorAll('.temu-ads-chart-badge').forEach(function (el) {
                el.addEventListener('click', function () {
                    openTemuAdsBadgeChart(this.dataset.metric, this.dataset.label);
                });
            });
            document.querySelectorAll('.temu-ads-history-dot').forEach(function (dot) {
                dot.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const host = this.closest('[data-metric]') || this;
                    const metric = this.dataset.metric || (host && host.dataset.metric);
                    const label = this.dataset.label || (host && host.dataset.label) || metric;
                    openTemuAdsBadgeChart(metric, label);
                });
            });
            document.getElementById('temuAdsBadgeChartRange').addEventListener('change', function () {
                if (activeBadgeMetric) loadTemuAdsBadgeChart();
            });

            function openCreateModal(goodsId) {
                document.getElementById('create-goods-id').value = goodsId || '';
                document.getElementById('create-ad-status').style.display = 'none';
                document.getElementById('create-roas').value = String(createRoasForGoods(goodsId, createAdDefaults().roas));
                new bootstrap.Modal(document.getElementById('createAdModal')).show();
            }

            document.getElementById('refresh-status-btn').addEventListener('click', function () {
                const status = document.getElementById('fetch-status');
                const btn = this;
                if (!confirm('Refresh Active/Inactive status from Temu for all goods on this page?')) {
                    return;
                }
                status.style.display = 'block';
                status.innerHTML = '<div class="alert alert-info py-2 mb-0"><i class="fas fa-spinner fa-spin me-1"></i> Refreshing ad status…</div>';
                btn.disabled = true;
                $.ajax({
                    url: '{{ route("temu2.ads.refresh-status") }}',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    timeout: 0,
                    success: function (response) {
                        status.innerHTML = '<div class="alert ' + (response.success ? 'alert-success' : 'alert-danger') + ' py-2 mb-0">' + (response.message || 'Done') + '</div>';
                        table.setData(dataUrl());
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Status refresh failed';
                        status.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + msg + '</div>';
                    },
                    complete: function () {
                        btn.disabled = false;
                    }
                });
            });

            document.getElementById('create-ad-btn').addEventListener('click', function () {
                const q = (document.getElementById('search-goods-id').value || '').trim();
                openCreateModal(/^\d+$/.test(q) ? q : '');
            });

            document.getElementById('predict-roas-btn').addEventListener('click', function () {
                const goodsId = (document.getElementById('create-goods-id').value || '').trim();
                if (!goodsId) {
                    showCreateStatus('<div class="alert alert-warning py-2 mb-0">Enter a Goods ID first.</div>');
                    return;
                }
                const btn = this;
                btn.disabled = true;
                showCreateStatus('<div class="alert alert-info py-2 mb-0"><i class="fas fa-spinner fa-spin me-1"></i> Asking Temu for suggested ROAS…</div>');
                $.ajax({
                    url: '{{ route("temu2.ads.predict-roas") }}',
                    method: 'POST',
                    data: { goods_id: goodsId, _token: '{{ csrf_token() }}' },
                    success: function (response) {
                        if (!response.success) {
                            showCreateStatus('<div class="alert alert-danger py-2 mb-0">' + (response.message || 'Predict failed') + '</div>');
                            return;
                        }
                        const raw = response.result;
                        let suggested = null;
                        if (raw && typeof raw === 'object') {
                            suggested = raw.roas ?? raw.predRoas ?? raw.predictRoas
                                ?? (raw.goodsInfoList && raw.goodsInfoList[0] && (raw.goodsInfoList[0].roas || raw.goodsInfoList[0].predRoas))
                                ?? null;
                            if (suggested && typeof suggested === 'object') suggested = suggested.val ?? suggested.roas ?? null;
                        }
                        if (suggested != null && Number(suggested) > 0) {
                            let n = Number(suggested);
                            if (n > 1000) n = n / 1000;
                            document.getElementById('create-roas').value = String(Math.round(n * 10) / 10);
                            showCreateStatus('<div class="alert alert-success py-2 mb-0">Suggested ROAS filled from Temu.</div>');
                        } else {
                            showCreateStatus('<div class="alert alert-warning py-2 mb-0">No suggested ROAS in response. Check Raw if needed.</div>');
                        }
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Predict failed';
                        showCreateStatus('<div class="alert alert-danger py-2 mb-0">' + msg + '</div>');
                    },
                    complete: function () {
                        btn.disabled = false;
                    }
                });
            });

            document.getElementById('create-ad-submit').addEventListener('click', function () {
                const goodsId = (document.getElementById('create-goods-id').value || '').trim();
                const budget = parseFloat(document.getElementById('create-budget').value);
                const roas = parseFloat(document.getElementById('create-roas').value);
                if (!goodsId || !(budget >= 1) || !(roas >= 0.1)) {
                    showCreateStatus('<div class="alert alert-warning py-2 mb-0">Enter Goods ID, budget (≥ $1), and ROAS (≥ 0.1).</div>');
                    return;
                }
                if (!confirm('Create a live Temu ad for goods ' + goodsId + '?\nDaily budget: $' + budget.toFixed(2) + '\nTarget ROAS: ' + roas)) {
                    return;
                }
                const btn = this;
                btn.disabled = true;
                showCreateStatus('<div class="alert alert-info py-2 mb-0"><i class="fas fa-spinner fa-spin me-1"></i> Creating ad…</div>');
                $.ajax({
                    url: '{{ route("temu2.ads.create") }}',
                    method: 'POST',
                    data: { goods_id: goodsId, budget: budget, roas: roas, _token: '{{ csrf_token() }}' },
                    success: function (response) {
                        if (response.success) {
                            showCreateStatus('<div class="alert alert-success py-2 mb-0">' + (response.message || 'Created') + '</div>');
                            table.setData(dataUrl());
                        } else {
                            showCreateStatus('<div class="alert alert-danger py-2 mb-0">' + (response.message || 'Failed') + '</div>');
                        }
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Create failed';
                        showCreateStatus('<div class="alert alert-danger py-2 mb-0">' + msg + '</div>');
                    },
                    complete: function () {
                        btn.disabled = false;
                    }
                });
            });

            document.getElementById('refresh-btn').addEventListener('click', function () {
                const period = document.getElementById('period-filter').value || 'L30';
                const status = document.getElementById('fetch-status');
                const btn = this;
                let fetchMsg = 'Fetch Temu ads API reports for ' + period + ' for all goods?\nThis may take several minutes.';
                if (period === 'L7') {
                    fetchMsg += '\n\nAuto Cron will push only ads whose Active/Pause status changes from the click limit.';
                }
                if (!confirm(fetchMsg)) {
                    return;
                }
                status.style.display = 'block';
                status.innerHTML = '<div class="alert alert-info py-2 mb-0"><i class="fas fa-spinner fa-spin me-1"></i> Fetching ' + period + ' from Temu API…</div>';
                btn.disabled = true;

                $.ajax({
                    url: '{{ route("temu2.ads.refresh") }}',
                    method: 'POST',
                    data: { period: period, _token: '{{ csrf_token() }}' },
                    timeout: 0,
                    success: function (response) {
                        if (response.success) {
                            status.innerHTML = '<div class="alert alert-success py-2 mb-0">' + (response.message || 'Done') + '</div>';
                            table.setData(dataUrl());
                        } else {
                            status.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + (response.message || 'Failed') + '</div>';
                        }
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Fetch failed (timeout or server error). Try: php artisan temu:fetch-ads-api-reports --period=' + period;
                        status.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + msg + '</div>';
                    },
                    complete: function () {
                        btn.disabled = false;
                    }
                });
            });
        });
    </script>
@endsection
