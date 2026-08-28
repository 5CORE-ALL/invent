@extends('layouts.vertical', ['title' => 'Faire - Analytics', 'sidenav' => 'condensed'])

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
        .tabulator .tabulator-tableholder { scrollbar-width: thin; scrollbar-color: #c1c1c1 transparent; }
        .tabulator .tabulator-tableholder::-webkit-scrollbar { width: 8px; height: 8px; }
        .tabulator .tabulator-tableholder::-webkit-scrollbar-track { background: transparent; }
        .tabulator .tabulator-tableholder::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
        .tabulator .tabulator-tableholder::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }
        #faire-pricing-table .tabulator-header .tabulator-col {
            background: #dbeafe !important;
            background-color: #dbeafe !important;
        }
        #faire-pricing-table .tabulator-header .tabulator-col[tabulator-field="_fr_select"] .tabulator-col-title,
        #faire-pricing-table .tabulator-header .tabulator-col[tabulator-field="_parent_expand"] .tabulator-col-title {
            writing-mode: horizontal-tb;
            transform: none;
            height: auto;
        }
        .tabulator-row.fr-parent-row,
        .tabulator-row.fr-parent-row .tabulator-cell {
            background-color: #fffef2 !important;
            font-weight: 700 !important;
            min-height: 48px !important;
            color: #1e3a5f;
        }
        .tabulator-row.fr-parent-row .tabulator-cell {
            min-height: 48px !important; height: 48px !important;
            padding-top: 8px !important; padding-bottom: 8px !important;
            overflow: visible !important; vertical-align: middle !important;
        }
        .tabulator-row.fr-parent-row:hover,
        .tabulator-row.fr-parent-row:hover .tabulator-cell {
            background-color: #93c5fd !important;
        }
        #faire-pricing-table .tabulator-calcs-top,
        #faire-pricing-table .tabulator-calcs-holder {
            display: none !important;
        }
        .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }
        /* Toolbar: compact controls (matches /ebay2-tabulator-view) */
        .fr-toolbar-row {
            row-gap: 4px;
        }
        .fr-toolbar-row > .form-select,
        .fr-toolbar-row .form-select.pricing-filter-item,
        .fr-toolbar-row > .btn,
        .fr-toolbar-row > .dropdown > .btn,
        .fr-toolbar-row > .btn-group > .btn {
            padding: 3px 10px;
            font-size: 0.8125rem;
            line-height: 1.3;
            min-height: 30px;
        }
        .fr-toolbar-row .form-select {
            padding-right: 24px;
            background-position: right 6px center;
            width: auto;
            display: inline-block;
        }
        .fr-toolbar-row .dropdown-menu {
            font-size: 0.8125rem;
        }
        #faire-pricing-table .tabulator-cell {
            white-space: nowrap;
        }
        #fr-image-hover-preview {
            pointer-events: auto;
            z-index: 10050;
        }
        /* Badges above the filter controls (matches /ebay2-tabulator-view) */
        #fr-summary-stats {
            order: -1;
            padding: 0.5rem 0.7rem !important;
            margin-top: 0 !important;
            margin-bottom: 0.5rem !important;
        }
        #fr-summary-stats .ebay2-summary-badge-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: stretch;
            gap: clamp(0.2rem, 0.5vw, 0.45rem);
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'faire'])
        #fr-summary-stats .fr-filter-badge.active-filter {
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.45);
            outline: 2px solid #0d6efd;
        }
        #fr-summary-stats .ebay2-summary-badge-row > .badge {
            flex: 1 1 0;
            min-width: 0;
            font-size: clamp(0.62rem, 0.35rem + 0.85vw, 1.05rem);
            padding: clamp(0.28rem, 0.4vw, 0.5rem) clamp(0.2rem, 0.5vw, 0.5rem);
            font-weight: bold;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            white-space: nowrap;
        }

        /* Metric history modal — full width (theme uses --tz-modal-width / --tz-modal-margin) */
        #frMetricChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #frMetricChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #frMetricChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Faire - Analytics',
        'sub_title'  => '',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2 d-flex flex-column">
                    {{-- Filter toolbar (compact UI matches /ebay2-tabulator-view) --}}
                    <div class="d-flex align-items-center flex-wrap gap-2 fr-toolbar-row">
                        <input type="text" id="fr-pricing-parent-search" class="form-control form-control-sm"
                            style="width:160px; display:inline-block;" placeholder="Search Parent..." title="Filter by Parent column">
                        <input type="text" id="fr-pricing-sku-search" class="form-control form-control-sm"
                            style="width:160px; display:inline-block;" placeholder="Search SKU...">

                        <select id="fr-row-type-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all">All Rows</option>
                            <option value="parents">Parents</option>
                            <option value="skus" selected>SKUs</option>
                        </select>
                        <select id="fr-inv-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all">INV</option>
                            <option value="zero">0 INV</option>
                            <option value="more" selected>INV &gt; 0</option>
                        </select>
                        <select id="fr-stock-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all">Faire stock</option>
                            <option value="zero">0 Faire stock</option>
                            <option value="more">Faire stock &gt; 0</option>
                        </select>
                        <select id="fr-gpft-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0–10%</option>
                            <option value="10-20">10–20%</option>
                            <option value="20-30">20–30%</option>
                            <option value="30-40">30–40%</option>
                            <option value="40-50">40–50%</option>
                            <option value="50plus">Above 50%</option>
                        </select>
                        <select id="fr-cvr-filter" class="form-select form-select-sm pricing-filter-item" title="CVR = sold (al30) ÷ OV L30">
                            <option value="all">CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-2">0-2%</option>
                            <option value="2-4">2-4%</option>
                            <option value="4-7">4-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                        <select id="fr-roi-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all">ROI%</option>
                            <option value="lt40">&lt; 40%</option>
                            <option value="40-75">40–75%</option>
                            <option value="75-125">75–125%</option>
                            <option value="gt125">125%+</option>
                        </select>
                        <select id="fr-fqty-filter" class="form-select form-select-sm pricing-filter-item" title="Units sold (Faire daily data)">
                            <option value="all">Sold</option>
                            <option value="0">0</option>
                            <option value="0-10">1–10</option>
                            <option value="10plus">10+</option>
                        </select>
                        <select id="fr-dil-filter" class="form-select form-select-sm pricing-filter-item">
                            <option value="all">DIL%</option>
                            <option value="red">Red &lt;16.7%</option>
                            <option value="yellow">Yellow 16.7–25%</option>
                            <option value="green">Green 25–50%</option>
                            <option value="pink">Pink 50%+</option>
                        </select>

                        <button type="button" id="fr-refresh-pricing" class="btn btn-sm btn-outline-primary pricing-filter-item" title="Reload table">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="fr-export-pricing" class="btn btn-sm btn-success pricing-filter-item" title="Export CSV" aria-label="Export CSV">
                            <i class="fas fa-file-csv"></i>
                        </button>
                        <div class="dropdown d-inline-block pricing-filter-item">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="frColumnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Columns" aria-label="Columns">
                                <i class="fa fa-eye"></i>
                            </button>
                            <ul class="dropdown-menu py-1" aria-labelledby="frColumnVisibilityDropdown" id="fr-column-dropdown-menu" style="max-height: 400px; overflow-y: auto; min-width: 220px;">
                            </ul>
                        </div>
                        <button id="fr-price-mode-btn" type="button" class="btn btn-sm btn-secondary pricing-filter-item"
                            title="Pricing mode: Off → Decrease → Increase → Same SPRICE (all rows)"
                            aria-label="Pricing mode">
                            <i class="fas fa-exchange-alt"></i>
                        </button>
                        <button type="button" id="fr-rule-btn" class="btn btn-sm btn-outline-dark pricing-filter-item"
                            title="Price rules: Dil %, Faire sold qty, Discount % → SPRICE = (STD × (1−Disc%)) − Ship">
                            <i class="fas fa-sliders-h"></i> Rule
                        </button>
                        @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'faire'])
                        <button type="button" id="fr-push-to-faire-btn" class="btn btn-sm btn-primary pricing-filter-item" style="display: none;"
                            title="Push SPRICE for selected SKUs to Faire (or all with SPRICE if none selected)">
                            <i class="fas fa-upload"></i> Push to Faire
                        </button>

                        {{-- Target ROI% bulk control — back-solves S PRC for selected rows so SROI = Target ROI%.
                             Faire's server-side SGPFT / SROI formula does NOT include shipping (matches FaireController::saveFaireSpriceUpdates lines 1060-1061).
                             Formula: sprice = LP × (1 + ROI%/100) / margin   (margin = per-row `_margin`, default 0.75 for Faire) --}}
                        <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                            id="fr-target-roi-controls"
                            title="Target ROI% — sets S PRC = LP × (1 + Target ROI%/100) / margin on every checked row (back-solves so SROI column equals the target)">
                            <label for="fr-target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                                <span style="font-size:1em;" aria-hidden="true">🎯</span> ROI%:
                            </label>
                            <input type="number" id="fr-target-roi-input" class="form-control form-control-sm text-end"
                                placeholder="30" step="0.1" style="width: 56px;"
                                title="Target ROI% applied to all checked rows when you click 'Apply S PRC'">
                            <button id="fr-apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                                title="Apply S PRC = LP × (1 + Target ROI%/100) / margin for every checked row"
                                aria-label="Apply S PRC">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>

                        {{-- Target GPFT% bulk control — back-solves S PRC for selected rows so SGPFT = Target GPFT%.
                             Formula: sprice = LP / (margin − GPFT%/100). Target GPFT% must be < margin*100. --}}
                        <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light pricing-filter-item"
                            id="fr-target-gpft-controls"
                            title="Target GPFT% — sets S PRC = LP / (margin − Target GPFT%/100) on every checked row (back-solves so SGPFT column equals the target)">
                            <label for="fr-target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                                <span style="font-size:1em;" aria-hidden="true">🎯</span> GPFT%:
                            </label>
                            <input type="number" id="fr-target-gpft-input" class="form-control form-control-sm text-end"
                                placeholder="30" step="0.1" style="width: 56px;"
                                title="Target GPFT% applied to all checked rows when you click 'Apply S PRC'. Must be less than the Faire take-home margin (typically < 75%).">
                            <button id="fr-apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                                title="Apply S PRC = LP / (margin − Target GPFT%/100) for every checked row"
                                aria-label="Apply S PRC">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>

                        <!-- Play / Pause parent navigation -->
                        <div class="btn-group align-items-center pricing-filter-item" role="group" aria-label="Parent navigation">
                            <button type="button" id="play-backward" class="btn btn-sm btn-light" title="Previous parent" disabled>
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button type="button" id="play-auto" class="btn btn-sm btn-primary" title="Start parent navigation">
                                <i class="fas fa-play"></i>
                            </button>
                            <button type="button" id="play-pause" class="btn btn-sm btn-warning" style="display: none;" title="Stop navigation and show all">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button type="button" id="play-forward" class="btn btn-sm btn-light" title="Next parent" disabled>
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>
                    </div>

                    <div id="fr-discount-container" class="p-2 bg-light border rounded mb-2" style="display:none;">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span id="fr-selected-skus-count" class="fw-bold text-secondary"></span>
                            <span id="fr-discount-type-wrap">
                            <select id="fr-discount-type" class="form-select form-select-sm" style="width:120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                            </span>
                            <input type="number" id="fr-discount-input" class="form-control form-control-sm" placeholder="Enter %" step="0.01" style="width:110px;">
                            <button id="fr-apply-discount-btn" type="button" class="btn btn-primary btn-sm">Apply</button>
                            <button id="fr-clear-sprice-btn" type="button" class="btn btn-danger btn-sm">
                                <i class="fas fa-eraser"></i> Clear SPRICE
                            </button>
                        </div>
                    </div>

                    <div id="fr-summary-stats" class="mt-2 p-3 bg-light rounded">
                        <div class="ebay2-summary-badge-row" role="group" aria-label="Summary metrics">
                            <button type="button" class="badge bg-warning text-dark fs-6 p-2 border-0 fr-sync-badge" id="frSyncFromApiBtn"
                                style="font-weight:700;cursor:pointer;flex:0 0 auto;"
                                title="Pulls live listings, wholesale price, and stock into faire_metric (API only)">
                                <i class="fas fa-cloud-download-alt me-1"></i>Sync API
                            </button>
                            <span id="frSyncFromApiStatus" class="small text-muted align-self-center text-nowrap" style="flex:0 0 auto;"></span>
                            <span class="badge bg-primary fs-6 p-2 fr-badge-chart fr-hover-chart" id="fr-total-sales-badge" data-metric="total_sales" style="font-weight:700;cursor:pointer;" title="Click or hover (½s) for daily trend">Sales: $0</span>
                            <span class="badge bg-warning fs-6 p-2 fr-badge-chart fr-hover-chart" id="fr-total-fqty-badge" data-metric="total_al30" style="font-weight:700;color:#111;cursor:pointer;" title="Click or hover for daily trend (units)">Sold: 0</span>
                            <span class="badge bg-success fs-6 p-2 d-none fr-badge-chart fr-hover-chart" id="fr-total-profit-badge" data-metric="total_pft" style="font-weight:700;cursor:pointer;" aria-hidden="true" title="View trend">Profit: 0</span>
                            <span class="badge bg-info fs-6 p-2 fr-badge-chart fr-hover-chart" id="fr-avg-gpft-badge" data-metric="avg_gpft" style="font-weight:700;color:#111;cursor:pointer;" title="Same as Faire Sales Data: total order-style profit ÷ total sales (0.75×wholesale revenue − LP×qty). Click or hover for trend.">PFt: 0%</span>
                            <span class="badge bg-secondary fs-6 p-2 fr-badge-chart fr-hover-chart" id="fr-avg-roi-badge" data-metric="avg_roi" style="font-weight:700;color:#111;cursor:pointer;" title="Click or hover for daily trend">ROI: 0%</span>
                            <span class="badge fs-6 p-2" id="faire-blue-triangle-badge"
                                style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;"
                                title="Blue triangle: S PRC ≠ Price. Click to show only those rows. Click again to clear.">
                                <i class="fas fa-exclamation-triangle"></i> 0</span>
                            <span class="badge fs-6 p-2 fr-hover-chart fr-filter-badge" id="fr-zero-sold-badge" data-metric="zero_sold" data-filter="zero_sold" style="font-weight:700;background:#dc3545;color:#fff;cursor:pointer;" title="Click to filter table · Hover ½s for daily trend">0 Sold: 0</span>
                            <span class="badge fs-6 p-2 fr-hover-chart fr-filter-badge" id="fr-more-sold-badge" data-metric="more_sold" data-filter="more_sold" style="font-weight:700;background:#b6e0fe;color:#0f172a;cursor:pointer;" title="Click to filter table · Hover ½s for daily trend">&gt;0 Sold: 0</span>
                        </div>
                    </div>

                    <div id="faire-pricing-table"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Faire pricing summary — daily snapshot trend (amazon_channel_summary_data channel=faire) -->
    <div class="modal fade p-0" id="frMetricChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="frChartModalTitle">Faire — Metric trend</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="frChartRangeSelect" class="form-select form-select-sm bg-white" style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
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
                    <div id="frChartContainer" style="height: 22vh; display: none; flex-direction: row; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="frMetricChart"></canvas>
                        </div>
                        <div id="frChartRefPanel" style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">Highest</div>
                                <div id="frChartHighest" style="font-size: 13px; font-weight: 700; color: #dc3545;">-</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">Median</div>
                                <div id="frChartMedian" style="font-size: 13px; font-weight: 700; color: #6c757d;">-</div>
                            </div>
                            <div style="text-align: center;">
                                <div style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">Lowest</div>
                                <div id="frChartLowest" style="font-size: 13px; font-weight: 700; color: #198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div id="frChartLoading" class="text-center py-3" style="display: none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data...</p>
                    </div>
                    <div id="frChartNoData" class="text-center py-3" style="display: none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No daily snapshots yet. Load this page on separate days to build history (saved automatically with pricing data).</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Links Modal -->
    <div class="modal fade" id="faireEditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <small class="text-muted">SKU: <span id="faireEditLinksSku" class="fw-bold"></span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seller Link (S)</label>
                        <input type="url" class="form-control" id="faireSellerLinkInput" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Link (B)</label>
                        <input type="url" class="form-control" id="faireBuyerLinkInput" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="faireSaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Price Rule: Dil % / Faire sold qty / Discount % → SPRICE from STD -->
    <div class="modal fade" id="frPriceRuleModal" tabindex="-1" aria-labelledby="frPriceRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="frPriceRuleModalLabel">
                        <i class="fas fa-sliders-h me-1"></i> Price Rule
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Match rows by <strong>Dil %</strong> and <strong>Sold qty (Faire)</strong>.
                        Apply sets <strong>SPRICE = (STD prc × (1 − Discount%/100)) − Ship</strong>.
                        Blank min/max = no limit. If SKUs are checked, only those are updated.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-2" id="fr-price-rule-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:12%">Dil % min</th>
                                    <th style="width:12%">Dil % max</th>
                                    <th style="width:14%">Sold min</th>
                                    <th style="width:14%">Sold max</th>
                                    <th style="width:14%">Discount %</th>
                                    <th style="width:8%"></th>
                                </tr>
                            </thead>
                            <tbody id="fr-price-rule-tbody"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="fr-price-rule-add-btn">
                        <i class="fas fa-plus"></i> Add rule
                    </button>
                    <div id="fr-price-rule-msg" class="small mt-2 text-danger d-none"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-outline-success" id="fr-price-rule-save-btn">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="fr-price-rule-apply-btn">
                        <i class="fas fa-check"></i> Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'faire'])
@endsection

@section('script-bottom')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'faire'])
        let table = null;
        let allTableData = [];
        let summaryDataCache = [];
        let frZeroSoldActive = false;
        let frMoreSoldActive = false;
        let blueTriangleFilterActive = false;

        function frIsParentRow(d) {
            return !!(d && (d.is_parent || d.is_parent_summary));
        }
        function frRowSpriceForAlert(data) {
            let sprice = parseFloat(data && (data.SPRICE != null ? data.SPRICE : data.sprice)) || 0;
            if (typeof chPromoLiveSprice === 'function' && !frIsParentRow(data)) {
                const calc = chPromoLiveSprice(data);
                if (calc > 0) sprice = calc;
            }
            return sprice;
        }
        function frHasBlueTriangle(data) {
            if (frIsParentRow(data)) return false;
            const sprice = frRowSpriceForAlert(data);
            const price = parseFloat(data && data.price) || 0;
            return sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100);
        }
        function syncFrTriangleBadgeState() {
            $('#faire-blue-triangle-badge').css({
                outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
                outlineOffset: blueTriangleFilterActive ? '2px' : ''
            });
        }

        let frDecreaseModeActive = false;
        let frIncreaseModeActive = false;
        let frUniformPriceModeActive = false;
        let frSelectedSkus = new Set();

        const FR_BADGE_CHART_URL = '{{ url('/faire/badge-chart-data') }}';
        const frBadgeMetricLabels = {
            total_sales: 'Sales',
            total_al30: 'Sold (units)',
            total_pft: 'Profit',
            avg_gpft: 'PFt %',
            avg_roi: 'ROI %',
            zero_sold: '0 Sold',
            more_sold: '> 0 Sold',
        };
        const frBadgeDollarMetrics = ['total_sales', 'total_pft'];
        const frBadgePctMetrics = ['avg_gpft', 'avg_roi'];
        let frChartInstance = null;
        let frChartAjax = null;
        let frChartDays = 30;
        let frChartMetricKey = '';

        function frFmtChartVal(v) {
            if (frBadgeDollarMetrics.includes(frChartMetricKey)) {
                const n = Number(v);
                if (Number.isFinite(n) && Math.abs(n % 1) > 1e-9) {
                    return '$' + n.toFixed(2);
                }
                return '$' + Math.round(n).toLocaleString('en-US');
            }
            if (frBadgePctMetrics.includes(frChartMetricKey)) {
                return Number(v).toFixed(1) + '%';
            }
            return Math.round(Number(v)).toLocaleString('en-US');
        }

        function frShowMetricChart(metricKey) {
            frChartMetricKey = metricKey;
            frChartDays = 30;
            $('#frChartRangeSelect').val('30');
            const label = frBadgeMetricLabels[metricKey] || metricKey;
            $('#frChartModalTitle').text('Faire — ' + label + ' (Daily snapshot)');
            const modalEl = document.getElementById('frMetricChartModal');
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else {
                $(modalEl).modal('show');
            }
            frLoadMetricChart();
        }

        function frLoadMetricChart() {
            if (frChartAjax) frChartAjax.abort();
            $('#frChartNoData').hide();
            $('#frChartContainer').hide();
            $('#frChartLoading').show();

            frChartAjax = $.ajax({
                url: FR_BADGE_CHART_URL,
                method: 'GET',
                data: { metric: frChartMetricKey, days: frChartDays },
                success: function(resp) {
                    frChartAjax = null;
                    $('#frChartLoading').hide();
                    if (resp.success && resp.data && resp.data.length > 0) {
                        $('#frChartContainer').css({ display: 'flex', flexDirection: 'row', alignItems: 'stretch' }).show();
                        frRenderMetricChart(resp.data);
                    } else {
                        $('#frChartNoData').show();
                    }
                },
                error: function(xhr, status) {
                    frChartAjax = null;
                    if (status === 'abort') return;
                    $('#frChartLoading').hide();
                    $('#frChartNoData').show();
                }
            });
        }

        function frRenderMetricChart(data) {
            const ctx = document.getElementById('frMetricChart').getContext('2d');
            if (frChartInstance) frChartInstance.destroy();

            const labels = data.map(function(d) { return d.date; });
            const values = data.map(function(d) { return d.value; });

            const dataMin = Math.min.apply(null, values);
            const dataMax = Math.max.apply(null, values);
            const sorted = values.slice().sort(function(a, b) { return a - b; });
            const mid = Math.floor(sorted.length / 2);
            const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
            const range = dataMax - dataMin || 1;
            const yMin = Math.max(0, dataMin - range * 0.1);
            const yMax = dataMax + range * 0.1;

            document.getElementById('frChartHighest').textContent = frFmtChartVal(dataMax);
            document.getElementById('frChartMedian').textContent = frFmtChartVal(median);
            document.getElementById('frChartLowest').textContent = frFmtChartVal(dataMin);

            const dotColors = values.map(function(v, i) {
                if (i === 0) return '#6c757d';
                return v < values[i - 1] ? '#dc3545' : (v > values[i - 1] ? '#28a745' : '#6c757d');
            });
            const labelColors = values.map(function(v) {
                return v === 0 ? '#198754' : v > 0 ? '#dc3545' : '#6c757d';
            });

            const medianLinePlugin = {
                id: 'frMedianLine',
                afterDraw: function(chart) {
                    const yScale = chart.scales.y, xScale = chart.scales.x, c = chart.ctx;
                    const yPixel = yScale.getPixelForValue(median);
                    c.save(); c.setLineDash([6, 4]); c.strokeStyle = '#6c757d'; c.lineWidth = 1.2;
                    c.beginPath(); c.moveTo(xScale.left, yPixel); c.lineTo(xScale.right, yPixel); c.stroke(); c.restore();
                }
            };

            const valueLabelsPlugin = {
                id: 'frValueLabels',
                afterDatasetsDraw: function(chart) {
                    const dataset = chart.data.datasets[0];
                    const meta = chart.getDatasetMeta(0);
                    const c = chart.ctx;
                    if (!dataset || !meta || !meta.data) return;
                    c.save();
                    c.font = 'bold 9px Inter, system-ui, sans-serif';
                    c.textAlign = 'center';
                    c.textBaseline = 'bottom';
                    meta.data.forEach(function(point, i) {
                        if (point == null || point.skip) return;
                        const txt = frFmtChartVal(dataset.data[i]);
                        const offsetY = (i % 2 === 0) ? -8 : -16;
                        const py = point.y + offsetY;
                        c.lineJoin = 'round';
                        c.lineWidth = 3;
                        c.strokeStyle = 'rgba(255,255,255,0.92)';
                        c.strokeText(txt, point.x, py);
                        c.fillStyle = labelColors[i];
                        c.fillText(txt, point.x, py);
                    });
                    c.restore();
                }
            };

            frChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: 'rgba(184, 134, 11, 0.1)',
                        borderColor: '#b8860b',
                        borderWidth: 1.5,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: dotColors,
                        pointBorderColor: dotColors,
                        pointBorderWidth: 1.5
                    }]
                },
                plugins: [medianLinePlugin, valueLabelsPlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 22, left: 2, right: 2, bottom: 2 } },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            titleFont: { size: 10 },
                            bodyFont: { size: 10 },
                            padding: 6,
                            callbacks: {
                                label: function(context) {
                                    const idx = context.dataIndex;
                                    const parts = ['Value: ' + frFmtChartVal(context.raw)];
                                    if (idx > 0) {
                                        const diff = context.raw - values[idx - 1];
                                        parts.push('vs prior: ' + (diff < 0 ? '▼' : diff > 0 ? '▲' : '▬') + ' ' + frFmtChartVal(Math.abs(diff)));
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
                            ticks: { font: { size: 9 }, callback: function(v) { return frFmtChartVal(v); } }
                        },
                        x: { ticks: { maxRotation: 45, minRotation: 45, autoSkip: true, maxTicksLimit: 30, font: { size: 8 } } }
                    }
                }
            });
        }

        let frBadgeHoverTimer = null;
        $(document).on('click', '.fr-badge-chart', function(e) {
            e.stopPropagation();
            const m = $(this).data('metric');
            if (m) frShowMetricChart(m);
        });
        $(document).on('mouseenter', '.fr-hover-chart', function() {
            const metric = $(this).data('metric');
            if (!metric) return;
            frBadgeHoverTimer = setTimeout(function() {
                frShowMetricChart(metric);
            }, 500);
        });
        $(document).on('mouseleave', '.fr-hover-chart', function() {
            if (frBadgeHoverTimer) { clearTimeout(frBadgeHoverTimer); frBadgeHoverTimer = null; }
        });
        $(document).on('mousedown', '.fr-hover-chart', function() {
            if (frBadgeHoverTimer) { clearTimeout(frBadgeHoverTimer); frBadgeHoverTimer = null; }
        });

        let frSkuColHoverBase = null;
        let frSkuColHoverActive = false;

        function frResetSkuColHoverWidth() {
            if (table && frSkuColHoverActive && frSkuColHoverBase != null) {
                try {
                    const col = table.getColumn('sku');
                    if (col) col.setWidth(frSkuColHoverBase);
                } catch (err) { /* ignore */ }
            }
            frSkuColHoverActive = false;
            frSkuColHoverBase = null;
        }

        let frImagePreviewHideTimer = null;
        let frImagePreviewEl = null;

        function frRemoveImagePreview() {
            if (frImagePreviewHideTimer) {
                clearTimeout(frImagePreviewHideTimer);
                frImagePreviewHideTimer = null;
            }
            document.querySelectorAll('#fr-image-hover-preview').forEach(function(el) {
                el.remove();
            });
            frImagePreviewEl = null;
        }

        function frCancelImagePreviewHide() {
            if (frImagePreviewHideTimer) {
                clearTimeout(frImagePreviewHideTimer);
                frImagePreviewHideTimer = null;
            }
        }

        function frScheduleImagePreviewHide() {
            frCancelImagePreviewHide();
            frImagePreviewHideTimer = setTimeout(frRemoveImagePreview, 220);
        }

        function frEnsureImagePreviewListeners(wrap) {
            if (wrap.dataset.frPreviewListeners === '1') return;
            wrap.dataset.frPreviewListeners = '1';
            wrap.addEventListener('mouseenter', frCancelImagePreviewHide);
            wrap.addEventListener('mouseleave', frScheduleImagePreviewHide);
        }

        function frClampImagePreviewPosition(wrap, clientX, clientY) {
            const pad = 12;
            let left = clientX + pad;
            let top = clientY + pad;
            wrap.style.position = 'fixed';
            wrap.style.left = left + 'px';
            wrap.style.top = top + 'px';
            const rect = wrap.getBoundingClientRect();
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const m = 8;
            if (rect.right > vw - m) left = Math.max(m, vw - rect.width - m);
            if (rect.bottom > vh - m) top = Math.max(m, vh - rect.height - m);
            if (left < m) left = m;
            if (top < m) top = m;
            wrap.style.left = left + 'px';
            wrap.style.top = top + 'px';
        }

        function frShowImagePreview(clientX, clientY, fullUrl) {
            if (!fullUrl) return;
            frCancelImagePreviewHide();
            const existing = frImagePreviewEl;
            if (existing && document.body.contains(existing)) {
                const prevImg = existing.querySelector('img');
                if (prevImg && prevImg.getAttribute('src') === fullUrl) {
                    frClampImagePreviewPosition(existing, clientX, clientY);
                    return;
                }
            }
            document.querySelectorAll('#fr-image-hover-preview').forEach(function(el) {
                el.remove();
            });
            frImagePreviewEl = null;

            const wrap = document.createElement('div');
            wrap.id = 'fr-image-hover-preview';
            wrap.style.zIndex = '10050';
            wrap.style.pointerEvents = 'auto';
            wrap.style.border = '1px solid #ccc';
            wrap.style.background = '#fff';
            wrap.style.padding = '4px';
            wrap.style.boxShadow = '0 4px 16px rgba(0,0,0,0.18)';
            wrap.style.borderRadius = '6px';
            const big = document.createElement('img');
            big.style.maxWidth = '350px';
            big.style.maxHeight = '350px';
            big.style.display = 'block';
            big.alt = '';
            big.src = fullUrl;
            wrap.appendChild(big);
            frEnsureImagePreviewListeners(wrap);
            document.body.appendChild(wrap);
            frImagePreviewEl = wrap;
            frClampImagePreviewPosition(wrap, clientX, clientY);
        }

        function money(value) {
            return '$' + (parseFloat(value) || 0).toFixed(2);
        }

        function frEscUrlAttr(url) {
            if (url == null || url === '') return '';
            return String(url).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        }

        function saveFaireSpriceUpdates(updates) {
            frUpdatePushButtonVisibility();
            $.ajax({
                url: '{{ route("faire.pricing.save.sprice") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { _token: '{{ csrf_token() }}', updates: updates },
                success: function(res) {
                    if (res.success) console.log('Faire SPRICE saved:', res.updated);
                },
                error: function(xhr) {
                    console.error('Faire SPRICE save error:', xhr.responseJSON);
                }
            });
        }

        function frSavePushStatus(sku, pushStatus, sprice) {
            return $.ajax({
                url: '{{ route("faire.pricing.save.sprice") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: {
                    _token: '{{ csrf_token() }}',
                    sku: sku,
                    sprice: sprice,
                    push_status: pushStatus
                }
            });
        }

        /** Push SPRICE to Faire price API with retries (same pattern as /doba-tabulator). */
        function frPushPriceToFaireWithRetry(sku, price, productId, maxRetries, delay) {
            maxRetries = maxRetries || 5;
            delay = delay || 5000;
            return new Promise(function(resolve, reject) {
                let attempt = 0;
                function attemptPush() {
                    attempt++;
                    $.ajax({
                        url: '{{ route("faire.pricing.push.price") }}',
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                        data: {
                            _token: '{{ csrf_token() }}',
                            sku: sku,
                            price: price,
                            product_id: productId || ''
                        },
                        success: function(response) {
                            if (response && response.success === false) {
                                const errorMsg = (response.errors && response.errors[0] && response.errors[0].message) || 'Push failed';
                                if (attempt < maxRetries) {
                                    setTimeout(attemptPush, delay);
                                } else {
                                    reject({ error: true, response: response, message: errorMsg });
                                }
                                return;
                            }
                            resolve({ success: true, response: response });
                        },
                        error: function(xhr) {
                            const errorMsg = (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors[0] && xhr.responseJSON.errors[0].message)
                                || (xhr.responseJSON && xhr.responseJSON.message)
                                || xhr.responseText
                                || 'Network error';
                            if (attempt < maxRetries) {
                                setTimeout(attemptPush, delay);
                            } else {
                                reject({ error: true, xhr: xhr, message: errorMsg });
                            }
                        }
                    });
                }
                attemptPush();
            });
        }

        /** Tabulator 6: do NOT call cell.reformat() (stub warns / throws). Rebuild Push HTML. */
        function frRefreshPushCell(row) {
            if (!row) return;
            const pushCell = row.getCell('_push');
            if (!pushCell) return;
            try {
                const col = pushCell.getColumn && pushCell.getColumn();
                const def = col && col.getDefinition ? col.getDefinition() : null;
                if (def && typeof def.formatter === 'function') {
                    const html = def.formatter(pushCell);
                    pushCell.getElement().innerHTML = (html == null ? '' : String(html));
                    return;
                }
            } catch (e) { /* ignore */ }
            try {
                const d = row.getData();
                const sprice = parseFloat(d.sprice) || 0;
                const status = d.push_status || null;
                const el = pushCell.getElement();
                if (!el) return;
                if (status === 'pushing') {
                    el.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:#ffc107;" title="Pushing to Faire..."></i>';
                } else if (status === 'pushed') {
                    el.innerHTML = '<i class="fa-solid fa-check-double" style="color:#28a745;" title="Pushed to Faire"></i>';
                } else if (status === 'error') {
                    el.innerHTML = '<button type="button" class="fr-push-single-btn" data-sku="' + String(d.sku || '').replace(/"/g, '&quot;') + '" data-price="' + sprice + '" style="border:none;background:none;color:#dc3545;cursor:pointer;padding:4px 6px;" title="Push failed — click to retry"><i class="fa-solid fa-x"></i></button>';
                } else if (sprice > 0) {
                    el.innerHTML = '<button type="button" class="fr-push-single-btn" data-sku="' + String(d.sku || '').replace(/"/g, '&quot;') + '" data-price="' + sprice + '" style="border:none;background:none;color:#0d6efd;cursor:pointer;padding:4px 6px;" title="Push SPRICE to Faire"><i class="fas fa-upload"></i></button>';
                } else {
                    el.innerHTML = '';
                }
            } catch (e2) { /* ignore */ }
        }

        function frRunPushForRow(row) {
            if (!row) return;
            const d = row.getData();
            if (d.is_parent) return;
            const sku = d.sku;
            const price = parseFloat(d.sprice) || 0;
            const productId = d.product_id || '';
            if (!sku || price <= 0) {
                if (window.toastr) toastr.warning('SPRICE must be > 0 to push');
                else alert('SPRICE must be > 0 to push');
                return;
            }

            row.update({ push_status: 'pushing' }, true);
            frRefreshPushCell(row);

            if (window.toastr) toastr.info('Pushing ' + sku + '…');
            // Fewer/faster retries so failures surface quickly in the UI
            frPushPriceToFaireWithRetry(sku, price, productId, 3, 1500)
                .then(function() {
                    row.update({ push_status: 'pushed', price: price }, true);
                    frRefreshPushCell(row);
                    frSavePushStatus(sku, 'pushed', price);
                    if (window.toastr) toastr.success('Pushed to Faire: ' + sku);
                    else alert('Pushed to Faire: ' + sku);
                })
                .catch(function(err) {
                    row.update({ push_status: 'error' }, true);
                    frRefreshPushCell(row);
                    frSavePushStatus(sku, 'error', price);
                    const msg = (err && err.message) ? err.message : 'Push failed';
                    if (window.toastr) toastr.error(msg);
                    else alert(msg);
                });
        }

        function frCollectPushableRows(selectedOnly) {
            const list = [];
            if (!table) return list;
            table.getRows().forEach(function(row) {
                const d = row.getData();
                if (d.is_parent) return;
                const sku = d.sku;
                const price = parseFloat(d.sprice) || 0;
                if (!sku || price <= 0) return;
                if (selectedOnly && !frSelectedSkus.has(sku)) return;
                list.push({
                    sku: sku,
                    price: price,
                    productId: d.product_id || '',
                    row: row
                });
            });
            return list;
        }

        function frUpdatePushButtonVisibility() {
            const $btn = $('#fr-push-to-faire-btn');
            if (!$btn.length || !table) return;
            const selectedPushable = frCollectPushableRows(true);
            const allPushable = selectedPushable.length > 0
                ? selectedPushable
                : frCollectPushableRows(false);
            const count = allPushable.length;
            if (count > 0) {
                const label = selectedPushable.length > 0
                    ? ('Push to Faire (' + count + ')')
                    : ('Push all SPRICE (' + count + ')');
                $btn.show().prop('disabled', false).html('<i class="fas fa-upload"></i> ' + label);
            } else {
                $btn.hide();
            }
        }

        function frRunBulkPush(skusWithSprice) {
            if (!skusWithSprice || !skusWithSprice.length) return;
            const $btn = $('#fr-push-to-faire-btn');
            const originalHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Pushing...');

            let currentIndex = 0;
            let successCount = 0;
            let errorCount = 0;

            function processNext() {
                if (currentIndex >= skusWithSprice.length) {
                    $btn.prop('disabled', false);
                    frUpdatePushButtonVisibility();
                    if (! $btn.is(':visible')) $btn.html(originalHtml);
                    if (successCount > 0 && errorCount === 0) {
                        if (window.toastr) toastr.success('Pushed prices for ' + successCount + ' SKU(s) to Faire');
                        else alert('Pushed prices for ' + successCount + ' SKU(s) to Faire');
                    } else if (successCount > 0 && errorCount > 0) {
                        if (window.toastr) toastr.warning('Pushed ' + successCount + ' SKU(s), ' + errorCount + ' failed');
                        else alert('Pushed ' + successCount + ' SKU(s), ' + errorCount + ' failed');
                    } else {
                        if (window.toastr) toastr.error('Failed to push prices for ' + errorCount + ' SKU(s)');
                        else alert('Failed to push prices for ' + errorCount + ' SKU(s)');
                    }
                    return;
                }

                const item = skusWithSprice[currentIndex];
                $btn.html('<i class="fas fa-spinner fa-spin"></i> ' + (currentIndex + 1) + '/' + skusWithSprice.length);

                item.row.update({ push_status: 'pushing' }, true);
                frRefreshPushCell(item.row);

                frPushPriceToFaireWithRetry(item.sku, item.price, item.productId, 3, 1500)
                    .then(function() {
                        successCount++;
                        item.row.update({ push_status: 'pushed', price: item.price }, true);
                        frRefreshPushCell(item.row);
                        frSavePushStatus(item.sku, 'pushed', item.price);
                        currentIndex++;
                        setTimeout(processNext, 1500);
                    })
                    .catch(function() {
                        errorCount++;
                        item.row.update({ push_status: 'error' }, true);
                        frRefreshPushCell(item.row);
                        frSavePushStatus(item.sku, 'error', item.price);
                        frSelectedSkus.delete(item.sku);
                        currentIndex++;
                        setTimeout(processNext, 1500);
                    });
            }

            processNext();
        }

        function frBindPushUi() {
            // Match Doba: delegate on class (clicks on the <i> icon still bubble).
            $(document).off('click.frPush', '.fr-push-single-btn').on('click.frPush', '.fr-push-single-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (!table) return;
                const $btn = $(this).closest('.fr-push-single-btn');
                const sku = $btn.attr('data-sku') || $btn.data('sku');
                if (!sku) return;
                let row = null;
                table.getRows().forEach(function(r) {
                    if (row) return;
                    const d = r.getData();
                    if (!d.is_parent && String(d.sku) === String(sku)) row = r;
                });
                if (!row && typeof table.searchRows === 'function') {
                    const found = table.searchRows('sku', '=', sku);
                    if (found && found.length) row = found[0];
                }
                if (!row) {
                    if (window.toastr) toastr.warning('Row not found for SKU ' + sku);
                    else alert('Row not found for SKU ' + sku);
                    return;
                }
                frRunPushForRow(row);
            });

            $(document).off('click.frBulkPush', '#fr-push-to-faire-btn').on('click.frBulkPush', '#fr-push-to-faire-btn', function() {
                if (!table) return;
                const selectedPushable = frCollectPushableRows(true);
                const skusWithSprice = selectedPushable.length > 0
                    ? selectedPushable
                    : frCollectPushableRows(false);

                if (!skusWithSprice.length) {
                    if (window.toastr) toastr.warning('No SKUs with SPRICE found. Set SPRICE first.');
                    else alert('No SKUs with SPRICE found. Set SPRICE first.');
                    return;
                }

                const scope = selectedPushable.length > 0 ? 'selected' : 'all';
                if (!confirm('Push SPRICE for ' + skusWithSprice.length + ' ' + scope + ' SKU(s) to Faire?')) {
                    return;
                }
                frRunBulkPush(skusWithSprice);
            });
        }

        function frRoundToRetailPrice(price) {
            if (price < 20.99) {
                return +price.toFixed(2);
            }
            return Math.ceil(price) - 0.01;
        }

        // ---- Price Rule (Dil % / Faire sold / Discount % → SPRICE from STD) ----
        const FR_PRICE_RULES_KEY = 'faire_price_rules_v1';
        let frPriceRules = [];

        function frDefaultPriceRules() {
            return [{ dil_min: null, dil_max: null, sold_min: null, sold_max: null, discount_pct: 25 }];
        }

        function frLoadPriceRules() {
            try {
                const raw = localStorage.getItem(FR_PRICE_RULES_KEY);
                if (!raw) return frDefaultPriceRules();
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) && parsed.length ? parsed : frDefaultPriceRules();
            } catch (e) {
                return frDefaultPriceRules();
            }
        }

        function frSavePriceRulesToStorage(rules) {
            localStorage.setItem(FR_PRICE_RULES_KEY, JSON.stringify(rules || []));
        }

        function frNumOrNull(v) {
            if (v === null || v === undefined || v === '') return null;
            const n = parseFloat(v);
            return isFinite(n) ? n : null;
        }

        function frInRange(val, min, max) {
            if (min !== null && min !== undefined && val < min) return false;
            if (max !== null && max !== undefined && val > max) return false;
            return true;
        }

        function frReadPriceRulesFromDom() {
            const rules = [];
            $('#fr-price-rule-tbody tr').each(function() {
                const $tr = $(this);
                rules.push({
                    dil_min: frNumOrNull($tr.find('[data-field="dil_min"]').val()),
                    dil_max: frNumOrNull($tr.find('[data-field="dil_max"]').val()),
                    sold_min: frNumOrNull($tr.find('[data-field="sold_min"]').val()),
                    sold_max: frNumOrNull($tr.find('[data-field="sold_max"]').val()),
                    discount_pct: frNumOrNull($tr.find('[data-field="discount_pct"]').val()),
                });
            });
            return rules;
        }

        function frRenderPriceRules(rules) {
            frPriceRules = Array.isArray(rules) ? rules : [];
            const $tb = $('#fr-price-rule-tbody');
            $tb.empty();
            if (!frPriceRules.length) frPriceRules = frDefaultPriceRules();
            frPriceRules.forEach(function(rule, idx) {
                const r = rule || {};
                $tb.append(
                    '<tr data-idx="' + idx + '">' +
                    '<td><input type="number" class="form-control form-control-sm" data-field="dil_min" step="0.1" placeholder="—" value="' + (r.dil_min != null ? r.dil_min : '') + '"></td>' +
                    '<td><input type="number" class="form-control form-control-sm" data-field="dil_max" step="0.1" placeholder="—" value="' + (r.dil_max != null ? r.dil_max : '') + '"></td>' +
                    '<td><input type="number" class="form-control form-control-sm" data-field="sold_min" step="1" placeholder="—" title="Faire sold qty (al30)" value="' + (r.sold_min != null ? r.sold_min : '') + '"></td>' +
                    '<td><input type="number" class="form-control form-control-sm" data-field="sold_max" step="1" placeholder="—" title="Faire sold qty (al30)" value="' + (r.sold_max != null ? r.sold_max : '') + '"></td>' +
                    '<td><input type="number" class="form-control form-control-sm" data-field="discount_pct" step="0.1" placeholder="e.g. 25" value="' + (r.discount_pct != null ? r.discount_pct : '') + '"></td>' +
                    '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger fr-price-rule-del" title="Remove"><i class="fas fa-trash"></i></button></td>' +
                    '</tr>'
                );
            });
        }

        function frRuleMatchesRow(rule, d) {
            const dil = parseFloat(d.dil_percent);
            const dilVal = isFinite(dil) ? dil : 0;
            const sold = parseFloat(d.al30);
            const soldVal = isFinite(sold) ? sold : 0;
            return frInRange(dilVal, rule.dil_min, rule.dil_max)
                && frInRange(soldVal, rule.sold_min, rule.sold_max);
        }

        function frApplyPriceRules() {
            const $msg = $('#fr-price-rule-msg');
            $msg.addClass('d-none').text('');
            if (!table) return;

            const rules = frReadPriceRulesFromDom().filter(function(r) {
                return r.discount_pct !== null && isFinite(r.discount_pct);
            });
            if (!rules.length) {
                $msg.removeClass('d-none').text('Add at least one rule with a Discount %.');
                return;
            }

            const restrictSelected = frSelectedSkus.size > 0;
            const updates = [];
            let matched = 0;
            let skippedNoStd = 0;

            table.getRows().forEach(function(row) {
                const d = row.getData();
                if (d.is_parent) return;
                if (restrictSelected && !frSelectedSkus.has(d.sku)) return;

                let hit = null;
                for (let i = 0; i < rules.length; i++) {
                    if (frRuleMatchesRow(rules[i], d)) {
                        hit = rules[i];
                        break;
                    }
                }
                if (!hit) return;
                matched++;

                const std = parseFloat(d.standard_price);
                if (!(std > 0)) {
                    skippedNoStd++;
                    return;
                }

                const factor = 1 - (parseFloat(hit.discount_pct) / 100);
                const ship = parseFloat(d.ship) || 0;
                // SPRICE = (STD × (1 − Discount%/100)) − Ship
                let newSprice = frRoundToRetailPrice(Math.max(0.99, (std * factor) - ship));
                const margin = parseFloat(d._margin) || 0.75;
                const lp = parseFloat(d.lp) || 0;
                const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - lp) / newSprice) * 100) : 0;
                const sroi = lp > 0 ? Math.round(((newSprice * margin - lp) / lp) * 100) : 0;
                row.update({ sprice: newSprice, sgpft: sgpft, sroi: sroi, push_status: null });
                updates.push({ sku: d.sku, sprice: newSprice });
            });

            if (!updates.length) {
                $msg.removeClass('d-none').text(
                    matched
                        ? ('Matched ' + matched + ' row(s) but none have STD prc > 0' + (skippedNoStd ? ' (' + skippedNoStd + ')' : '') + '.')
                        : (restrictSelected
                            ? 'No checked rows match the Dil % / Sold qty filters.'
                            : 'No rows match the Dil % / Sold qty filters.')
                );
                return;
            }

            frSavePriceRulesToStorage(frReadPriceRulesFromDom());
            saveFaireSpriceUpdates(updates);
            const tip = 'Applied SPRICE on ' + updates.length + ' SKU(s)'
                + (skippedNoStd ? ' (' + skippedNoStd + ' skipped — no STD)' : '')
                + '.';
            if (window.toastr) toastr.success(tip);
            else alert(tip);
        }

        function frBindPriceRuleUi() {
            $('#fr-rule-btn').on('click', function() {
                frRenderPriceRules(frLoadPriceRules());
                $('#fr-price-rule-msg').addClass('d-none').text('');
                const el = document.getElementById('frPriceRuleModal');
                if (el && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(el).show();
                } else {
                    $(el).modal('show');
                }
            });

            $('#fr-price-rule-add-btn').on('click', function() {
                frPriceRules = frReadPriceRulesFromDom();
                frPriceRules.push({ dil_min: null, dil_max: null, sold_min: null, sold_max: null, discount_pct: 25 });
                frRenderPriceRules(frPriceRules);
            });

            $(document).on('click', '#fr-price-rule-tbody .fr-price-rule-del', function() {
                const idx = parseInt($(this).closest('tr').attr('data-idx'), 10);
                frPriceRules = frReadPriceRulesFromDom();
                if (frPriceRules.length <= 1) {
                    frPriceRules = frDefaultPriceRules();
                } else {
                    frPriceRules.splice(idx, 1);
                }
                frRenderPriceRules(frPriceRules);
            });

            $('#fr-price-rule-save-btn').on('click', function() {
                const rules = frReadPriceRulesFromDom();
                frSavePriceRulesToStorage(rules);
                frPriceRules = rules;
                if (window.toastr) toastr.success('Price rules saved');
                else alert('Price rules saved');
            });

            $('#fr-price-rule-apply-btn').on('click', function() {
                frApplyPriceRules();
            });
        }

        function frSyncPriceModeUi() {
            const $btn = $('#fr-price-mode-btn');
            const selectCol = table ? table.getColumn('_fr_select') : null;
            $('#fr-discount-type-wrap').toggle(!frUniformPriceModeActive);
            if (frUniformPriceModeActive) {
                $btn.removeClass('btn-secondary btn-danger btn-primary').addClass('btn-warning')
                    .attr('title', 'Same SPRICE (click to turn off)')
                    .attr('aria-label', 'Same SPRICE')
                    .html('<i class="fas fa-equals"></i>');
                if (selectCol) selectCol.hide();
                frSelectedSkus.clear();
                $('#fr-discount-input').attr('placeholder', 'SPRICE $');
                frUpdateSelectedCount();
                return;
            }
            $('#fr-discount-input').attr('placeholder', $('#fr-discount-type').val() === 'percentage' ? 'Enter %' : 'Enter $');
            if (frDecreaseModeActive) {
                $btn.removeClass('btn-secondary btn-primary btn-warning').addClass('btn-danger')
                    .attr('title', 'Decrease ON — select SKUs, then use discount panel')
                    .attr('aria-label', 'Decrease ON')
                    .html('<i class="fas fa-arrow-down"></i>');
                if (selectCol) selectCol.show();
                frUpdateSelectedCount();
                return;
            }
            if (frIncreaseModeActive) {
                $btn.removeClass('btn-secondary btn-danger btn-warning').addClass('btn-primary')
                    .attr('title', 'Increase ON — select SKUs, then use discount panel')
                    .attr('aria-label', 'Increase ON')
                    .html('<i class="fas fa-arrow-up"></i>');
                if (selectCol) selectCol.show();
                frUpdateSelectedCount();
                return;
            }
            $btn.removeClass('btn-danger btn-primary btn-warning').addClass('btn-secondary')
                .attr('title', 'Pricing mode: Off → Decrease → Increase → Same SPRICE (all rows)')
                .attr('aria-label', 'Pricing mode')
                .html('<i class="fas fa-exchange-alt"></i>');
            if (selectCol) selectCol.hide();
            frSelectedSkus.clear();
            frUpdateSelectedCount();
        }

        function frUpdateSelectedCount() {
            if (frUniformPriceModeActive) {
                $('#fr-selected-skus-count').text('One SPRICE for every SKU row (not parent summaries).');
            } else {
                const cnt = frSelectedSkus.size;
                $('#fr-selected-skus-count').text(cnt + ' SKU' + (cnt !== 1 ? 's' : '') + ' selected');
            }
            const showPanel = frUniformPriceModeActive
                || (frSelectedSkus.size > 0 && (frDecreaseModeActive || frIncreaseModeActive));
            $('#fr-discount-container').toggle(showPanel);
            frUpdatePushButtonVisibility();
        }

        function frApplyDiscount() {
            const discountType = $('#fr-discount-type').val();
            const discountVal = parseFloat($('#fr-discount-input').val());

            if (frUniformPriceModeActive) {
                if (isNaN(discountVal) || discountVal <= 0 || !table) return;
                const newSprice = frRoundToRetailPrice(Math.max(0.99, discountVal));
                const updates = [];
                table.getRows().forEach(function(row) {
                    const d = row.getData();
                    if (d.is_parent) return;
                    const margin = parseFloat(d._margin) || 0.75;
                    const lp = parseFloat(d.lp) || 0;
                    const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - lp) / newSprice) * 100) : 0;
                    const sroi = lp > 0 ? Math.round(((newSprice * margin - lp) / lp) * 100) : 0;
                    row.update({ sprice: newSprice, sgpft: sgpft, sroi: sroi, push_status: null });
                    updates.push({ sku: d.sku, sprice: newSprice });
                });
                if (updates.length) saveFaireSpriceUpdates(updates);
                $('#fr-discount-input').val('');
                return;
            }

            if (isNaN(discountVal) || discountVal === 0 || frSelectedSkus.size === 0) return;

            const updates = [];
            frSelectedSkus.forEach(function(sku) {
                const rows = table.searchRows('sku', '=', sku);
                if (!rows.length) return;
                const row = rows[0];
                const rowData = row.getData();
                const currentPrice = parseFloat(rowData.price) || 0;
                if (currentPrice <= 0) return;

                let newSprice;
                if (discountType === 'percentage') {
                    newSprice = frIncreaseModeActive
                        ? currentPrice * (1 + discountVal / 100)
                        : currentPrice * (1 - discountVal / 100);
                } else {
                    newSprice = frIncreaseModeActive
                        ? currentPrice + discountVal
                        : currentPrice - discountVal;
                }
                newSprice = frRoundToRetailPrice(Math.max(0.99, newSprice));

                const margin = parseFloat(rowData._margin) || 0.75;
                const lp = parseFloat(rowData.lp) || 0;
                const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - lp) / newSprice) * 100) : 0;
                const sroi = lp > 0 ? Math.round(((newSprice * margin - lp) / lp) * 100) : 0;

                row.update({ sprice: newSprice, sgpft: sgpft, sroi: sroi, push_status: null });
                updates.push({ sku: sku, sprice: newSprice });
            });

            if (updates.length) saveFaireSpriceUpdates(updates);
            $('#fr-discount-input').val('');
        }

        function frClearSpriceForSelected() {
            if (frUniformPriceModeActive) {
                if (!confirm('Clear SPRICE for ALL SKU rows?')) return;
                const updates = [];
                table.getRows().forEach(function(row) {
                    const d = row.getData();
                    if (!d.is_parent) {
                        row.update({ sprice: 0, sgpft: 0, sroi: 0, push_status: null });
                        updates.push({ sku: d.sku, sprice: 0 });
                    }
                });
                if (updates.length) saveFaireSpriceUpdates(updates);
                return;
            }
            if (!frSelectedSkus.size) return;
            if (!confirm('Clear SPRICE for ' + frSelectedSkus.size + ' SKU(s)?')) return;
            const updates = [];
            table.getRows().forEach(function(row) {
                const d = row.getData();
                if (frSelectedSkus.has(d.sku) && !d.is_parent) {
                    row.update({ sprice: 0, sgpft: 0, sroi: 0, push_status: null });
                    updates.push({ sku: d.sku, sprice: 0 });
                }
            });
            if (updates.length) saveFaireSpriceUpdates(updates);
        }

        function normalizeRows(rowsInput) {
            if (Array.isArray(rowsInput)) {
                return rowsInput.map(row => {
                    if (row && typeof row.getData === 'function') return row.getData();
                    return row || {};
                });
            }
            if (rowsInput && typeof rowsInput === 'object') {
                return Object.values(rowsInput).map(row => {
                    if (row && typeof row.getData === 'function') return row.getData();
                    return row || {};
                });
            }
            return [];
        }

        /** Matches /faire-tabulator: keep 0.75 of wholesale dollars minus LP×qty (per-SKU aggregate). */
        const FAIRE_ORDER_KEEP = 0.75;

        function updateSummary(rowsInput = null) {
            let rows = normalizeRows(rowsInput);
            if (!rows.length && table && typeof table.getData === 'function') {
                const activeRows = normalizeRows(table.getData('active'));
                const allRows = normalizeRows(table.getData());
                rows = activeRows.length ? activeRows : allRows;
            }
            if (!rows.length) rows = normalizeRows(summaryDataCache);

            let totalSales = 0, totalFqty = 0, totalProfit = 0, totalCogs = 0;
            let zeroSold = 0, moreSold = 0;

            rows.forEach(row => {
                if (row.is_parent) return;
                const fqty = parseFloat(row.al30) || 0;
                const sales = parseFloat(row.sales) || 0;
                const lp = parseFloat(row.lp) || 0;
                const listProfitPerUnit = parseFloat(row.profit) || 0;

                totalSales += sales;
                totalFqty += fqty;
                totalCogs += lp * fqty;

                let rowOrderPft = 0;
                if (sales > 0 && fqty > 0) {
                    rowOrderPft = FAIRE_ORDER_KEEP * sales - lp * fqty;
                } else if (fqty > 0) {
                    rowOrderPft = fqty * listProfitPerUnit;
                }
                totalProfit += rowOrderPft;

                if (fqty === 0) zeroSold++; else moreSold++;
            });

            const pftPct = totalSales > 0 ? (totalProfit / totalSales) * 100 : 0;
            const roiPct = totalCogs > 0 ? (totalProfit / totalCogs) * 100 : 0;

            $('#fr-total-sales-badge').text('Sales: $' + Math.round(totalSales).toLocaleString());
            $('#fr-total-fqty-badge').text('Sold: ' + totalFqty.toLocaleString());
            $('#fr-total-profit-badge').text('Profit: ' + Math.round(totalProfit).toLocaleString());
            $('#fr-avg-gpft-badge').text('PFt: ' + Math.round(pftPct) + '%');
            $('#fr-avg-roi-badge').text('ROI: ' + Math.round(roiPct) + '%');
            $('#fr-zero-sold-badge').text('0 Sold: ' + zeroSold.toLocaleString());
            $('#fr-more-sold-badge').text('>0 Sold: ' + moreSold.toLocaleString());
            let blueTriangleCount = 0;
            (table ? table.getData() : rows).forEach(function(row) {
                if (frHasBlueTriangle(row)) blueTriangleCount++;
            });
            $('#faire-blue-triangle-badge').html(
                '<i class="fas fa-exclamation-triangle"></i> ' + blueTriangleCount.toLocaleString()
            );
            if (typeof syncFrTriangleBadgeState === 'function') syncFrTriangleBadgeState();
        }

        // Play / Pause parent navigation state
        let frUniqueParents = [];
        let isFrPlayActive = false;
        let currentFrParentIndex = -1;

        function normalizeFrParentKey(val) {
            if (val == null || val === '') return '';
            return String(val).trim().replace(/\s+/g, ' ').replace(/^PARENT\s+/i, '');
        }
        function buildFrUniqueParents() {
            if (!table) return [];
            const allRows = table.getData('all') || [];
            const seen = {};
            const list = [];
            allRows.forEach(function(r) {
                const p = normalizeFrParentKey(r.parent);
                if (p && !seen[p]) { seen[p] = true; list.push(p); }
            });
            list.sort(function(a, b) { return String(a).localeCompare(String(b)); });
            return list;
        }
        function updateFrPlayButtonStates() {
            $('#play-backward').prop('disabled', !isFrPlayActive || currentFrParentIndex <= 0);
            $('#play-forward').prop('disabled', !isFrPlayActive || currentFrParentIndex >= frUniqueParents.length - 1);
        }
        function startFrPlay() {
            frUniqueParents = buildFrUniqueParents();
            if (frUniqueParents.length === 0) return;
            isFrPlayActive = true;
            currentFrParentIndex = 0;
            $('#play-auto').hide();
            $('#play-pause').show();
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updateFrPlayButtonStates();
        }
        function stopFrPlay() {
            isFrPlayActive = false;
            currentFrParentIndex = -1;
            $('#play-pause').hide();
            $('#play-auto').show();
            applyFilters();
            updateFrPlayButtonStates();
        }
        function nextFrParent() {
            if (!isFrPlayActive || currentFrParentIndex >= frUniqueParents.length - 1) return;
            currentFrParentIndex++;
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updateFrPlayButtonStates();
        }
        function previousFrParent() {
            if (!isFrPlayActive || currentFrParentIndex <= 0) return;
            currentFrParentIndex--;
            applyFilters();
            try { table.setPage(1); } catch (e) {}
            updateFrPlayButtonStates();
        }
        $('#play-auto').on('click', startFrPlay);
        $('#play-pause').on('click', stopFrPlay);
        $('#play-forward').on('click', nextFrParent);
        $('#play-backward').on('click', previousFrParent);

        function frIsParentRow(d) {
            if (!d) return false;
            if (d.is_parent === true || d.is_parent === 1 || d.is_parent === '1' || d.is_parent === 'true') return true;
            const sku = String(d.sku || '').trim().toUpperCase();
            if (/^PARENT\b/.test(sku)) return true;
            const p = String(d.parent || '').trim().toUpperCase();
            return /^PARENT\b/.test(p);
        }

        function frCurrentRowType() {
            return $('#fr-row-type-filter').val() || 'skus';
        }

        function frRowsForRowType(rows, rowType) {
            rowType = rowType || frCurrentRowType();
            if (!Array.isArray(rows)) return [];
            if (rowType === 'parents') return rows.filter(frIsParentRow);
            if (rowType === 'skus') return rows.filter(function(d) { return !frIsParentRow(d); });
            return rows.slice();
        }

        let frSyncingRowType = false;

        function applyFilters() {
            if (frSyncingRowType) return;
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                ParentExpand.beforeFilters(function(){ applyFilters(); });
                return;
            }
            if (!table) return;

            const source = (allTableData && allTableData.length) ? allTableData : (table.getData('all') || []);

            if (isFrPlayActive && frUniqueParents.length > 0 && currentFrParentIndex >= 0) {
                const currentKey = frUniqueParents[currentFrParentIndex];
                if (currentKey) {
                    const group = source.filter(function(d) {
                        const p = normalizeFrParentKey(d.parent);
                        return p === currentKey || p === ('PARENT ' + currentKey);
                    });
                    frSyncingRowType = true;
                    table.setData(group).then(function() { frSyncingRowType = false; })
                        .catch(function() { frSyncingRowType = false; });
                }
                return;
            }

            const rowType = frCurrentRowType();
            const desired = frRowsForRowType(source, rowType);
            const currentAll = (typeof table.getData === 'function' ? table.getData('all') : []) || [];
            const needsSwap = currentAll.length !== desired.length
                || (rowType === 'skus' && currentAll.some(frIsParentRow))
                || (rowType === 'parents' && currentAll.some(function(d) { return !frIsParentRow(d); }))
                || (rowType === 'all' && currentAll.length !== source.length);

            if (needsSwap && source.length) {
                frSyncingRowType = true;
                table.setData(desired).then(function() {
                    frSyncingRowType = false;
                    applyMetricFilters();
                }).catch(function() { frSyncingRowType = false; });
                return;
            }

            applyMetricFilters();
        }

        function applyMetricFilters() {
            if (!table) return;
            table.clearFilter();

            const skuSearch = ($('#fr-pricing-sku-search').val() || '').toLowerCase().trim();
            const parentSearch = ($('#fr-pricing-parent-search').val() || '').toLowerCase().trim();
            const rowType = frCurrentRowType();
            const invFilter = $('#fr-inv-filter').val();
            const stockFilter = $('#fr-stock-filter').val();
            const gpftFilter = $('#fr-gpft-filter').val();
            const cvrFilter = $('#fr-cvr-filter').val();
            const roiFilter = $('#fr-roi-filter').val();
            const fqtyFilter = $('#fr-fqty-filter').val();
            const dilColor = $('#fr-dil-filter').val() || 'all';

            if (skuSearch) {
                table.addFilter(d => (d.sku || '').toLowerCase().includes(skuSearch));
            }
            if (parentSearch) {
                table.addFilter(function(d) {
                    const p = (d.parent || '').toLowerCase();
                    const sku = (d.sku || '').toLowerCase();
                    if (d.is_parent === true) {
                        return p.includes(parentSearch) || sku.includes(parentSearch);
                    }
                    return p.includes(parentSearch) || sku.includes(parentSearch);
                });
            }
            if (rowType === 'parents') {
                table.addFilter(function(d) { return frIsParentRow(d); });
            } else if (rowType === 'skus') {
                table.addFilter(function(d) { return !frIsParentRow(d); });
            }
            if (invFilter === 'zero') {
                table.addFilter(function(d) {
                    if (frIsParentRow(d)) return rowType !== 'skus';
                    return (parseInt(d.inv, 10) || 0) === 0;
                });
            } else if (invFilter === 'more') {
                table.addFilter(function(d) {
                    if (frIsParentRow(d)) return rowType !== 'skus';
                    return (parseInt(d.inv, 10) || 0) > 0;
                });
            }
            if (stockFilter === 'zero') {
                table.addFilter(d => (parseInt(d.ae_stock, 10) || 0) === 0);
            } else if (stockFilter === 'more') {
                table.addFilter(d => (parseInt(d.ae_stock, 10) || 0) > 0);
            }
            if (gpftFilter !== 'all') {
                table.addFilter(function(d) {
                    const gpft = parseFloat(d.gpft) || 0;
                    if (gpftFilter === 'negative') return gpft < 0;
                    if (gpftFilter === '50plus') return gpft >= 50;
                    const parts = gpftFilter.split('-').map(Number);
                    return gpft >= parts[0] && gpft < parts[1];
                });
            }
            if (cvrFilter !== 'all') {
                table.addFilter(function(d) {
                    const ov = parseFloat(d.ov_l30) || 0;
                    const sold = parseFloat(d.al30) || 0;
                    const cvrPercent = ov > 0 ? (sold / ov) * 100 : 0;
                    const cvrRounded = Math.round(cvrPercent * 100) / 100;
                    if (cvrFilter === '0-0') return cvrRounded === 0;
                    if (cvrFilter === '0-2') return cvrRounded > 0 && cvrRounded <= 2;
                    if (cvrFilter === '2-4') return cvrRounded > 2 && cvrRounded <= 4;
                    if (cvrFilter === '4-7') return cvrRounded > 4 && cvrRounded <= 7;
                    if (cvrFilter === '7-13') return cvrRounded > 7 && cvrRounded <= 13;
                    if (cvrFilter === '13plus') return cvrRounded > 13;
                    return true;
                });
            }
            if (roiFilter !== 'all') {
                table.addFilter(function(d) {
                    if (d.is_parent) return true;
                    const roi = parseFloat(d.groi) || 0;
                    if (roiFilter === 'lt40') return roi < 40;
                    if (roiFilter === '40-75') return roi >= 40 && roi < 75;
                    if (roiFilter === '75-125') return roi >= 75 && roi < 125;
                    if (roiFilter === 'gt125') return roi >= 125;
                    return true;
                });
            }
            if (fqtyFilter !== 'all') {
                table.addFilter(function(d) {
                    if ((parseInt(d.inv, 10) || 0) <= 0) return false;
                    const fqty = parseFloat(d.al30) || 0;
                    if (fqtyFilter === '0') return fqty === 0;
                    if (fqtyFilter === '0-10') return fqty > 0 && fqty <= 10;
                    if (fqtyFilter === '10plus') return fqty > 10;
                    return true;
                });
            }
            if (dilColor !== 'all') {
                table.addFilter(function(d) {
                    const inv = parseFloat(d.inv) || 0;
                    const ovL30 = parseFloat(d.ov_l30) || 0;
                    const dil = inv === 0 ? 0 : (ovL30 / inv) * 100;
                    if (dilColor === 'red') return dil < 16.66;
                    if (dilColor === 'yellow') return dil >= 16.66 && dil < 25;
                    if (dilColor === 'green') return dil >= 25 && dil < 50;
                    if (dilColor === 'pink') return dil >= 50;
                    return true;
                });
            }
            frSyncFilterBadgeActiveClasses();
            if (frZeroSoldActive) table.addFilter(d => (parseFloat(d.al30) || 0) === 0);
            if (frMoreSoldActive) table.addFilter(d => (parseFloat(d.al30) || 0) > 0);
            if (blueTriangleFilterActive) {
                table.addFilter(function(data) {
                    return frHasBlueTriangle(data);
                });
            }
        }

        function frBuildColumnDropdown() {
            if (!table) return;
            if (window.AnalyticsColVis) {
                window.AnalyticsColVis.install({
                    getTable: function() { return table; },
                    menuId: 'fr-column-dropdown-menu',
                    storageKey: 'faire_col_cats_v1',
                    skipFields: ['_fr_select', '_select'],
                    onSave: function() {
                        if (typeof frSaveColumnVisibilityToServer === 'function') frSaveColumnVisibilityToServer();
                    }
                });
                window.AnalyticsColVis.rebuild(null, 'fr-column-dropdown-menu');
                return;
            }
            const menu = document.getElementById('fr-column-dropdown-menu');
            if (!menu) return;
            let html = '';
            table.getColumns().forEach(function(col) {
                const field = col.getField();
                const def = col.getDefinition();
                const titleRaw = def.title;
                const titleStr = titleRaw != null ? String(titleRaw) : '';
                const label = titleStr.replace(/<[^>]*>/g, '').trim() || field;
                if (field && field !== '_fr_select' && label) {
                    const isVisible = col.isVisible();
                    const fEsc = String(field).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                    const lEsc = String(label).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                    html += '<li class="dropdown-item px-2 py-1">' +
                        '<label class="d-flex align-items-center gap-2 mb-0 w-100" style="cursor:pointer;">' +
                        '<input type="checkbox" class="fr-column-toggle" data-field="' + fEsc + '" ' + (isVisible ? 'checked' : '') + '>' +
                        '<span>' + lEsc + '</span>' +
                        '</label></li>';
                }
            });
            menu.innerHTML = html;
        }

        function frSaveColumnVisibilityToServer() {
            if (!table) return;
            const visibility = {};
            table.getColumns().forEach(function(col) {
                const field = col.getField();
                if (field && field !== '_fr_select') {
                    visibility[field] = col.isVisible();
                }
            });
            $.ajax({
                url: '{{ route("faire.pricing.column.set") }}',
                method: 'POST',
                data: { visibility: visibility, _token: '{{ csrf_token() }}' }
            });
        }

        function frApplyColumnVisibilityFromServer() {
            if (!table) return;
            $.ajax({
                url: '{{ route("faire.pricing.column.get") }}',
                method: 'GET',
                success: function(visibility) {
                    if (visibility && typeof visibility === 'object' && Object.keys(visibility).length > 0) {
                        Object.keys(visibility).forEach(function(field) {
                            const col = table.getColumn(field);
                            if (col) {
                                if (visibility[field]) {
                                    col.show();
                                } else {
                                    col.hide();
                                }
                            }
                        });
                        frBuildColumnDropdown();
                    }
                    if (typeof applyFilters === 'function') applyFilters();
                }
            });
        }

        // ---- Edit Links (Buyer / Seller) ----
        function frLinksNotify(msg, type) {
            if (window.toastr) {
                if (type === 'error' || type === 'danger') toastr.error(msg);
                else if (type === 'warning') toastr.warning(msg);
                else toastr.success(msg);
                return;
            }
            let c = document.getElementById('frLinksToastContainer');
            if (!c) {
                c = document.createElement('div');
                c.id = 'frLinksToastContainer';
                c.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;';
                document.body.appendChild(c);
            }
            const t = document.createElement('div');
            const bg = (type === 'error' || type === 'danger') ? '#dc3545' : (type === 'warning' ? '#fd7e14' : '#198754');
            t.style.cssText = 'min-width:220px;max-width:340px;color:#fff;background:' + bg + ';padding:12px 16px;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.18);font-size:14px;opacity:0;transition:opacity .25s ease;';
            t.textContent = msg;
            c.appendChild(t);
            requestAnimationFrame(function() { t.style.opacity = '1'; });
            setTimeout(function() { t.style.opacity = '0'; setTimeout(function() { t.remove(); }, 300); }, 2600);
        }
        let faireEditLinksRow = null;
        window.openFaireEditLinksModal = function(row) {
            faireEditLinksRow = row;
            const d = row.getData();
            $('#faireEditLinksSku').text(d.sku || '');
            $('#faireSellerLinkInput').val(d.seller_link || '');
            $('#faireBuyerLinkInput').val(d.buyer_link || '');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('faireEditLinksModal')).show();
        };
        $(document).on('click', '#faireSaveLinksBtn', function() {
            if (!faireEditLinksRow) return;
            const sku = faireEditLinksRow.getData().sku;
            const sellerLink = $('#faireSellerLinkInput').val().trim();
            const buyerLink = $('#faireBuyerLinkInput').val().trim();
            const $btn = $(this);
            $btn.prop('disabled', true).text('Saving...');
            $.ajax({
                url: '/faire/save-links',
                method: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    sku: sku,
                    seller_link: sellerLink,
                    buyer_link: buyerLink
                },
                success: function(res) {
                    if (res && res.success) {
                        faireEditLinksRow.update({
                            seller_link: res.seller_link || '',
                            buyer_link: res.buyer_link || ''
                        });
                        frLinksNotify('Links saved successfully', 'success');
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('faireEditLinksModal')).hide();
                    } else {
                        frLinksNotify((res && res.message) || 'Failed to save links', 'error');
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save links';
                    frLinksNotify(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save');
                }
            });
        });

        $(document).ready(function() {
            $('#frChartRangeSelect').on('change', function() {
                const days = parseInt($(this).val(), 10);
                if (days === frChartDays) return;
                frChartDays = days;
                const label = frBadgeMetricLabels[frChartMetricKey] || frChartMetricKey;
                $('#frChartModalTitle').text('Faire — ' + label + ' (Daily snapshot)');
                frLoadMetricChart();
            });

            table = new Tabulator('#faire-pricing-table', {
                ajaxURL: '/faire/pricing-data',
                ajaxResponse: function(url, params, response) {
                    const rows = Array.isArray(response) ? response : [];
                    rows.forEach(function(r) {
                        if (!r || typeof r !== 'object') return;
                        const sku = String(r.sku || '').trim().toUpperCase();
                        if (r.is_parent === true || r.is_parent === 1 || r.is_parent === '1' || /^PARENT\b/.test(sku)) {
                            r.is_parent = true;
                        }
                    });
                    allTableData = rows;
                    if (window.ParentExpand) ParentExpand.captureDataset(allTableData);
                    summaryDataCache = normalizeRows(rows);
                    updateSummary(summaryDataCache);
                    return frRowsForRowType(rows, 'skus');
                },
                layout: 'fitDataStretch',
                height: 'calc(100vh - 260px)',
                pagination: true,
                paginationSize: 100,
                initialSort: [],
                rowFormatter: function(row) {
                    if (frIsParentRow(row.getData())) {
                        row.getElement().classList.add('fr-parent-row');
                    }
                },
                columns: [
                    {
                        title: 'Image', field: 'image', width: 60, headerSort: false, frozen: true,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const src = cell.getValue();
                            if (d.is_parent || !src) return '';
                            const esc = String(src).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            return '<img src="' + esc + '" data-full="' + esc + '" class="fr-hover-thumb" alt="" ' +
                                'style="width:44px;height:44px;object-fit:cover;border-radius:4px;cursor:pointer;" ' +
                                'onerror="this.onerror=null;this.style.display=\'none\'">';
                        },
                        cellMouseOver: function(e, cell) {
                            if (cell.getRow().getData().is_parent) return;
                            const img = cell.getElement().querySelector('.fr-hover-thumb');
                            if (!img) return;
                            frShowImagePreview(e.clientX, e.clientY, img.getAttribute('data-full'));
                        },
                        cellMouseMove: function(e, cell) {
                            const preview = frImagePreviewEl;
                            if (!preview || !document.body.contains(preview)) return;
                            if (cell.getRow().getData().is_parent) return;
                            const img = cell.getElement().querySelector('.fr-hover-thumb');
                            const fullUrl = img ? img.getAttribute('data-full') : '';
                            const big = preview.querySelector('img');
                            if (!fullUrl || !big || big.getAttribute('src') !== fullUrl) return;
                            frClampImagePreviewPosition(preview, e.clientX, e.clientY);
                        },
                        cellMouseOut: function(e, cell) {
                            const related = e.relatedTarget;
                            if (related && typeof related.closest === 'function' && related.closest('#fr-image-hover-preview')) {
                                frCancelImagePreviewHide();
                                return;
                            }
                            frScheduleImagePreviewHide();
                        }
                    },
                    {
                        title: "<input type=\"checkbox\" id=\"fr-select-all\">",
                        field: '_fr_select',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 38,
                        minWidth: 38,
                        maxWidth: 42,
                        download: false,
                        visible: false,
                        frozen: true,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const sku = String(d.sku || '');
                            const esc = sku.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            const chk = frSelectedSkus.has(d.sku) ? 'checked' : '';
                            return '<input type="checkbox" class="fr-sku-chk" data-sku="' + esc + '" ' + chk + '>';
                        }
                    },
                    {
                        title: 'Parent', field: 'parent', width: 120, frozen: true,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const v = cell.getValue() || '';
                            if (!v) return '<span style="color:#adb5bd;">–</span>';
                            return '<span style="color:#0d6efd;font-size:11px;font-weight:600;">' + v + '</span>';
                        }
                    },
                    ParentExpand.columnDef(),
                    {
                        title: 'SKU', field: 'sku', minWidth: 200, frozen: true,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const val = cell.getValue() || '';
                            if (d.is_parent) {
                                return '<span style="color:#0f5132;font-size:13px;font-weight:700;">' + String(val).replace(/</g, '&lt;') + '</span>';
                            }
                            const raw = String(val);
                            const esc = raw.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                            return '<span class="d-inline-flex align-items-center gap-1">' +
                                '<span class="fw-bold">' + esc + '</span>' +
                                '<button type="button" class="btn btn-sm btn-link p-0 fr-copy-sku-btn" data-sku="' + esc + '" title="Copy SKU" ' +
                                'style="min-width:auto;line-height:1;color:#6c757d;vertical-align:middle;"><i class="fas fa-copy" style="font-size:12px;"></i></button>' +
                                '</span>';
                        }
                    },
                    {
                        title: 'Links',
                        field: 'buyer_link',
                        headerSort: false,
                        hozAlign: 'center',
                        width: 55,
                        download: false,
                        frozen: true,
                        tooltip: 'Double-click to add / edit links',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const b = d.buyer_link || '';
                            const s = d.seller_link || '';
                            let html = '<div style="display:flex;flex-direction:column;gap:1px;line-height:1.1;">';
                            if (s) {
                                html += '<a href="' + frEscUrlAttr(s) + '" target="_blank" rel="noopener noreferrer" class="text-info" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> S</a>';
                            }
                            if (b) {
                                html += '<a href="' + frEscUrlAttr(b) + '" target="_blank" rel="noopener noreferrer" class="text-success" style="font-size:11px;text-decoration:none;" onclick="event.stopPropagation();"><i class="fa fa-link"></i> B</a>';
                            }
                            if (!s && !b) {
                                html += '<span class="text-muted" style="font-size:12px;">-</span>';
                            }
                            html += '</div>';
                            return html;
                        },
                        cellDblClick: function(e, cell) {
                            if (cell.getRow().getData().is_parent) return;
                            openFaireEditLinksModal(cell.getRow());
                        }
                    },
                    {
                        title: 'INV', field: 'inv', sorter: 'number', hozAlign: 'center', width: 55,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="font-weight:700;">' + cell.getValue() + '</span>';
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (val === 0) return '<span style="color:#dc3545;font-weight:600;">0</span>';
                            return '<span style="font-weight:600;">' + val + '</span>';
                        }
                    },
                    {
                        title: 'Faire stock', field: 'ae_stock', sorter: 'number', hozAlign: 'center', width: 82,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="font-weight:700;">' + cell.getValue() + '</span>';
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (val === 0) return '<span style="color:#dc3545;font-weight:600;">0</span>';
                            return '<span style="font-weight:600;">' + val + '</span>';
                        }
                    },
                    {
                        title: 'OV L30', field: 'ov_l30', sorter: 'number', hozAlign: 'center', width: 60,
                        formatter: function(cell) {
                            return '<span style="font-weight:700;">' + (parseInt(cell.getValue(), 10) || 0) + '</span>';
                        }
                    },
                    {
                        title: 'Dil', field: 'dil_percent', sorter: 'number', hozAlign: 'center', width: 55,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const inv = parseFloat(row.inv) || 0;
                            const ovL30 = parseFloat(row.ov_l30) || 0;
                            if (inv === 0) return '<span style="color:#6c757d;">0%</span>';
                            const dil = (ovL30 / inv) * 100;
                            let color = dil < 16.66 ? '#a00211' : dil < 25 ? '#ffc107' : dil < 50 ? '#28a745' : '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                        }
                    },
                    {
                        title: 'Sold', field: 'al30', sorter: 'number', hozAlign: 'center', width: 55,
                        formatter: function(cell) {
                            const v = parseInt(cell.getValue(), 10) || 0;
                            return '<span style="font-weight:700;">' + v + '</span>';
                        }
                    },
                    {
                        title: 'STD prc',
                        field: 'standard_price',
                        sorter: 'number',
                        hozAlign: 'right',
                        width: 78,
                        headerTooltip: 'Standard Price (STD PRC) — same as /pricing-errors-form (amazon_data_view.STANDARD_PRICE)',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue());
                            if (!isFinite(v) || !(v > 0)) return '<span style="color:#6c757d;">–</span>';
                            return '<span style="font-weight:600;">' + money(v) + '</span>';
                        }
                    },
                    {
                        title: 'Pricing', field: 'price', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            return '<span style="font-weight:700;">' + money(cell.getValue()) + '</span>';
                        }
                    },
                    {
                        title: 'GROI', field: 'groi', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue()) || 0;
                            let color;
                            if (v < 40) color = '#a00211';
                            else if (v < 75) color = '#ffc107';
                            else if (v < 125) color = '#28a745';
                            else color = '#d63384';
                            return '<span style="color:' + color + ';font-weight:700;">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'GPFT', field: 'gpft', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v)) return '<span style="color:#6c757d;">–</span>';
                            if (v === 0 && !d.is_parent) return '0%';
                            if (v === 0 && d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            let color = v < 10 ? '#a00211' : v < 15 ? '#ffc107' : v < 20 ? '#3591dc' : v <= 40 ? '#28a745' : '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:' + (d.is_parent ? '700' : '600') + ';">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'Profit', field: 'profit', sorter: 'number', hozAlign: 'right', visible: false,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue()) || 0;
                            if (d.is_parent) {
                                if (v === 0) return '<span style="color:#6c757d;">–</span>';
                                const color = v >= 0 ? '#28a745' : '#dc3545';
                                return '<span style="color:' + color + ';font-weight:700;">' + money(v) + '</span>';
                            }
                            return money(v);
                        }
                    },
                    {
                        title: 'Sales', field: 'sales', sorter: 'number', hozAlign: 'right', visible: false,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue()) || 0;
                            if (d.is_parent) {
                                if (v === 0) return '<span style="color:#6c757d;">–</span>';
                                return '<span style="font-weight:700;">' + money(v) + '</span>';
                            }
                            return money(v);
                        }
                    },
                    {
                        title: 'LP', field: 'lp', sorter: 'number', hozAlign: 'right', visible: false,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            return money(cell.getValue());
                        }
                    },
                    ...(typeof channelPromoAnalyticsColumns === 'function' ? channelPromoAnalyticsColumns() : (typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : [])),
                    {
                        title: 'Sprice', field: 'sprice', sorter: 'number', hozAlign: 'right',
                        editor: 'number', editorParams: { min: 0, step: 0.01 },
                        headerTooltip: 'S PRC = Std × (1 − (PRMT% + cvr%)/100). Blue triangle = S PRC ≠ Price. Red text = S PRC > LMP.',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (frIsParentRow(d)) return '<span style="color:#6c757d;">–</span>';
                            let value = parseFloat(cell.getValue() || 0);
                            if (typeof chPromoLiveSprice === 'function') {
                                const calc = chPromoLiveSprice(d);
                                if (calc > 0) value = calc;
                            }
                            if (!(value > 0)) return '<span style="color:#6c757d;">–</span>';
                            const live = parseFloat(d.price) || 0;
                            const lmp = parseFloat(d.lmp_price || d.lmp || d.LMP) || 0;
                            const cap = window.SpriceLmpCap ? SpriceLmpCap.apply(d, value) : null;
                            if (cap && cap.shown > 0) value = cap.shown;
                            const overLmp = cap ? cap.alert : (lmp > 0 && value + 0.0001 >= lmp);
                            const redTri = overLmp ? (cap ? cap.triangleHtml : '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;" title="S PRC capped at LMP"></i>') : '';
                            const formatted = money(value);
                            const priceHtml = overLmp
                                ? '<span style="color:#dc3545;font-weight:600;">' + formatted + '</span>'
                                : '<span style="font-weight:600;">' + formatted + '</span>';
                            const blueTri = (live > 0 && Math.round(value * 100) !== Math.round(live * 100))
                                ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                    + value.toFixed(2) + ' ≠ Price $' + live.toFixed(2) + '"></i>'
                                : '';
                            return '<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">' + priceHtml + blueTri + '</span>';
                        }
                    },
                    {
                        title: 'SGROI', field: 'sroi', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v) || v === 0) return '<span style="font-weight:700;">0%</span>';
                            let color;
                            if (v < 40) color = '#a00211';
                            else if (v < 75) color = '#ffc107';
                            else if (v < 125) color = '#28a745';
                            else color = '#d63384';
                            return '<span style="color:' + color + ';font-weight:700;">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'SGPFT', field: 'sgpft', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v) || v === 0) return '0%';
                            let color = v < 10 ? '#a00211' : v < 15 ? '#ffc107' : v < 20 ? '#3591dc' : v <= 40 ? '#28a745' : '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'Push',
                        field: '_push',
                        width: 52,
                        hozAlign: 'center',
                        headerSort: false,
                        headerTooltip: 'Push SPRICE to Faire wholesale price API (same status UX as /doba-tabulator)',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const sprice = parseFloat(d.sprice) || 0;
                            if (sprice <= 0) return '';
                            const pushStatus = d.push_status || null;
                            const sku = String(d.sku || '');
                            const productId = String(d.product_id || '');
                            const esc = function(v) {
                                return String(v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            };
                            if (pushStatus === 'pushing') {
                                return '<i class="fas fa-spinner fa-spin" style="color:#ffc107;" title="Pushing to Faire..."></i>';
                            }
                            if (pushStatus === 'pushed') {
                                return '<i class="fa-solid fa-check-double" style="color:#28a745;" title="Pushed to Faire"></i>';
                            }
                            if (pushStatus === 'error') {
                                return '<button type="button" class="fr-push-single-btn" data-sku="' + esc(sku) + '" data-price="' + sprice + '" data-product-id="' + esc(productId) + '" style="border:none;background:none;color:#dc3545;cursor:pointer;padding:4px 6px;" title="Push failed — click to retry"><i class="fa-solid fa-x"></i></button>';
                            }
                            return '<button type="button" class="fr-push-single-btn" data-sku="' + esc(sku) + '" data-price="' + sprice + '" data-product-id="' + esc(productId) + '" style="border:none;background:none;color:#0d6efd;cursor:pointer;padding:4px 6px;" title="Push SPRICE to Faire"><i class="fas fa-upload"></i></button>';
                        },
                        // Tabulator can swallow nested button clicks — handle via cellClick too
                        cellClick: function(e, cell) {
                            const t = e.target;
                            if (!t || typeof t.closest !== 'function') return;
                            const btn = t.closest('.fr-push-single-btn');
                            if (!btn) return;
                            e.preventDefault();
                            e.stopPropagation();
                            frRunPushForRow(cell.getRow());
                        }
                    },
                ],
                dataLoaded: function(data) {
                    if (!allTableData.length && Array.isArray(data)) {
                        allTableData = data;
                        if (window.ParentExpand) ParentExpand.captureDataset(allTableData);
                    }
                    frResetSkuColHoverWidth();
                    frRemoveImagePreview();
                    updateSummary(data);
                    if (!frSyncingRowType && typeof applyFilters === 'function') applyFilters();
                    setTimeout(frUpdatePushButtonVisibility, 50);
                },
                dataFiltered: function(filters, rows) { updateSummary(rows); },
                dataProcessed: function() { updateSummary(); },
                renderComplete: function() { updateSummary(); }
            });

            if (window.ParentExpand) {
                ParentExpand.configure({
                    parentField: 'parent',
                    skuField: 'sku',
                    getTable: () => table,
                    getDataset: () => allTableData,
                    onAfterExpand: () => { if (typeof updateSummary === 'function') updateSummary(); },
                    onCollapse: () => { if (typeof applyFilters === 'function') applyFilters(); },
                });
                ParentExpand.bind();
            }

            function frSyncFilterBadgeActiveClasses() {
                $('#fr-zero-sold-badge').toggleClass('active-filter', frZeroSoldActive);
                $('#fr-more-sold-badge').toggleClass('active-filter', frMoreSoldActive);
            }

            function frApplyBadgeFilterFromUrl() {
                const badge = (new URLSearchParams(window.location.search).get('badge') || '').toLowerCase();
                if (!badge || !table) return;
                frZeroSoldActive = frMoreSoldActive = false;
                if (badge === 'zero_sold') frZeroSoldActive = true;
                else if (badge === 'more_sold') frMoreSoldActive = true;
                else return;
                frSyncFilterBadgeActiveClasses();
                applyFilters();
            }

            table.on('tableBuilt', function() {
                frBuildColumnDropdown();
                frApplyColumnVisibilityFromServer();
                frApplyBadgeFilterFromUrl();
            });

            table.on('scrollVertical', frRemoveImagePreview);
            table.on('scrollHorizontal', frRemoveImagePreview);

            $(document).on('click', '.fr-copy-sku-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const sku = $(this).attr('data-sku');
                if (!sku) return;
                const done = function() {
                    if (window.toastr) toastr.success('SKU copied');
                };
                const fail = function() {
                    if (window.toastr) toastr.error('Could not copy SKU');
                    else alert('Could not copy SKU');
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(sku).then(done).catch(fail);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = sku;
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    try {
                        if (document.execCommand('copy')) done(); else fail();
                    } catch (err) { fail(); }
                    document.body.removeChild(ta);
                }
            });

            frSyncPriceModeUi();

            $('#fr-price-mode-btn').on('click', function() {
                if (!frDecreaseModeActive && !frIncreaseModeActive && !frUniformPriceModeActive) {
                    frDecreaseModeActive = true;
                    frIncreaseModeActive = false;
                    frUniformPriceModeActive = false;
                } else if (frDecreaseModeActive) {
                    frDecreaseModeActive = false;
                    frIncreaseModeActive = true;
                    frUniformPriceModeActive = false;
                } else if (frIncreaseModeActive) {
                    frDecreaseModeActive = false;
                    frIncreaseModeActive = false;
                    frUniformPriceModeActive = true;
                } else {
                    frDecreaseModeActive = false;
                    frIncreaseModeActive = false;
                    frUniformPriceModeActive = false;
                }
                frSyncPriceModeUi();
            });

            $('#fr-discount-type').on('change', function() {
                $('#fr-discount-input').attr('placeholder', $(this).val() === 'percentage' ? 'Enter %' : 'Enter $');
            });
            $('#fr-apply-discount-btn').on('click', function() { frApplyDiscount(); });
            $('#fr-discount-input').on('keypress', function(e) { if (e.which === 13) frApplyDiscount(); });
            $('#fr-clear-sprice-btn').on('click', function() { frClearSpriceForSelected(); });

            /*
             * Target ROI% / Target GPFT% bulk apply (Faire, per-row `_margin`, default 0.75)
             * -----------------------------------------------------------------------------
             * Back-solves SPRICE so the resulting SROI / SGPFT column matches the entered
             * target. Faire's server-side SGPFT / SROI formulas (FaireController::
             * saveFaireSpriceUpdates lines 1060-1061) do NOT include shipping — they're:
             *     SGPFT% = ((sprice * margin − lp) / sprice) * 100
             *     SROI%  = ((sprice * margin − lp) / lp)     * 100
             *   → sprice = lp * (1 + ROI%/100) / margin
             *   → sprice = lp / (margin − GPFT%/100)
             * Optimistic SGPFT / SROI written client-side (matching frApplyDiscount's
             * same no-ship formula), then the existing /faire/pricing-save-sprice
             * endpoint reconciles them server-side. Plain 2-decimal rounding — no .99
             * snapping — because snapping would shift the achieved SROI / SGPFT off
             * the user-typed target.
             */
            function frApplyTargetBackSolve(computeFn, labelPrefix) {
                if (frSelectedSkus.size === 0) {
                    if (window.toastr) toastr.warning('Please check at least one SKU first (turn on Pricing mode to reveal checkboxes)');
                    return;
                }

                const updates     = [];
                let updatedCount  = 0;
                let skippedNoLp   = 0;
                const skippedHigh = [];

                frSelectedSkus.forEach(function (sku) {
                    const rows = table.searchRows('sku', '=', sku);
                    if (!rows.length) return;
                    const row     = rows[0];
                    const rowData = row.getData();
                    if (rowData.is_parent) return;

                    const lp = parseFloat(rowData.lp) || 0;
                    if (lp <= 0) { skippedNoLp++; return; }
                    const marginRaw = parseFloat(rowData._margin);
                    const margin    = (isFinite(marginRaw) && marginRaw > 0) ? marginRaw : 0.75;

                    const computed = computeFn(lp, margin);
                    if (computed == null) { skippedHigh.push(sku); return; }
                    const newSprice = +computed.toFixed(2);
                    if (!isFinite(newSprice) || newSprice <= 0) return;

                    const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - lp) / newSprice) * 100) : 0;
                    const sroi  = lp > 0       ? Math.round(((newSprice * margin - lp) / lp)     * 100) : 0;

                    row.update({ sprice: newSprice, sgpft: sgpft, sroi: sroi, push_status: null });
                    updates.push({ sku: sku, sprice: newSprice });
                    updatedCount++;
                });

                if (updates.length === 0) {
                    if (skippedHigh.length > 0) {
                        if (window.toastr) toastr.error(`${labelPrefix} too high — must be less than each row's take-home margin (typically < 75%).`);
                    } else {
                        if (window.toastr) toastr.warning('No checked rows have a usable LP > 0');
                    }
                    return;
                }

                saveFaireSpriceUpdates(updates);
                let note = '';
                if (skippedNoLp > 0)    note += ' (' + skippedNoLp + ' skipped — no LP)';
                if (skippedHigh.length) note += ' (' + skippedHigh.length + ' skipped — target ≥ margin)';
                if (window.toastr) toastr.success(labelPrefix + ' applied to ' + updatedCount + ' SKU(s)' + note);
            }

            $('#fr-apply-target-roi-btn').on('click', function () {
                const rawInput = $('#fr-target-roi-input').val();
                const targetRoiPct = parseFloat(String(rawInput).replace(',', '.'));

                if (rawInput === '' || rawInput == null) {
                    if (window.toastr) toastr.error('Please enter a Target ROI%');
                    return;
                }
                if (!isFinite(targetRoiPct)) {
                    if (window.toastr) toastr.error('Target ROI% must be a number');
                    return;
                }

                const roiMultiplier = 1 + (targetRoiPct / 100);
                frApplyTargetBackSolve(function (lp, margin) {
                    return (lp * roiMultiplier) / margin;
                }, 'Target ROI ' + targetRoiPct + '%');
            });

            $('#fr-apply-target-gpft-btn').on('click', function () {
                const rawInput = $('#fr-target-gpft-input').val();
                const targetGpftPct = parseFloat(String(rawInput).replace(',', '.'));

                if (rawInput === '' || rawInput == null) {
                    if (window.toastr) toastr.error('Please enter a Target GPFT%');
                    return;
                }
                if (!isFinite(targetGpftPct)) {
                    if (window.toastr) toastr.error('Target GPFT% must be a number');
                    return;
                }

                const targetFraction = targetGpftPct / 100;
                frApplyTargetBackSolve(function (lp, margin) {
                    const denom = margin - targetFraction;
                    if (denom <= 0) return null; // signals "target ≥ margin" skip
                    return lp / denom;
                }, 'Target GPFT ' + targetGpftPct + '%');
            });

            $('#fr-target-roi-input').on('keypress', function (e) {
                if (e.which === 13) $('#fr-apply-target-roi-btn').click();
            });
            $('#fr-target-gpft-input').on('keypress', function (e) {
                if (e.which === 13) $('#fr-apply-target-gpft-btn').click();
            });

            $(document).on('change', '#fr-select-all', function() {
                const checked = $(this).prop('checked');
                const rows = table.getData('active').filter(function(d) { return !d.is_parent; });
                rows.forEach(function(d) {
                    if (checked) frSelectedSkus.add(d.sku); else frSelectedSkus.delete(d.sku);
                });
                $('.fr-sku-chk').prop('checked', checked);
                frUpdateSelectedCount();
            });

            $(document).on('change', '.fr-sku-chk', function() {
                const sku = $(this).attr('data-sku');
                if ($(this).prop('checked')) frSelectedSkus.add(sku); else frSelectedSkus.delete(sku);
                frUpdateSelectedCount();
            });

            $('#faire-blue-triangle-badge').on('click', function() {
                blueTriangleFilterActive = !blueTriangleFilterActive;
                applyFilters();
            });

            table.on('cellEdited', function(cell) {
                if (cell.getField() === 'standard_price' || cell.getField() === 'STANDARD_PRICE') {
                    const row = cell.getRow();
                    if (typeof applyChannelSpriceFromStdChange === 'function') {
                        applyChannelSpriceFromStdChange(row);
                    }
                    return;
                }
                if (cell.getField() !== 'sprice') return;
                const d = cell.getRow().getData();
                if (d.is_parent) return;
                const sku = d.sku;
                const sprice = parseFloat(cell.getValue()) || 0;
                const margin = parseFloat(d._margin) || 0.75;
                const lp = parseFloat(d.lp) || 0;
                const sgpft = sprice > 0 ? Math.round(((sprice * margin - lp) / sprice) * 100) : 0;
                const sroi = lp > 0 ? Math.round(((sprice * margin - lp) / lp) * 100) : 0;
                cell.getRow().update({ sgpft: sgpft, sroi: sroi, push_status: null });
                frRefreshPushCell(cell.getRow());
                frUpdatePushButtonVisibility();
                saveFaireSpriceUpdates([{ sku: sku, sprice: sprice }]);
            });

            $('#fr-pricing-parent-search, #fr-pricing-sku-search').on('input', function() { applyFilters(); });
            $('#fr-row-type-filter, #fr-inv-filter, #fr-stock-filter, #fr-gpft-filter, #fr-cvr-filter, #fr-roi-filter, #fr-fqty-filter, #fr-dil-filter').on('change', function() { applyFilters(); });

            $('#fr-zero-sold-badge').on('click', function() {
                frZeroSoldActive = !frZeroSoldActive;
                frMoreSoldActive = false;
                applyFilters();
            });
            $('#fr-more-sold-badge').on('click', function() {
                frMoreSoldActive = !frMoreSoldActive;
                frZeroSoldActive = false;
                applyFilters();
            });

            $('#fr-refresh-pricing').on('click', function() {
                table.setData('/faire/pricing-data');
            });
            $('#fr-export-pricing').on('click', function() {
                table.download('csv', 'faire_analytics_data.csv');
            });
            frBindPriceRuleUi();
            frBindPushUi();

            const frColMenu = document.getElementById('fr-column-dropdown-menu');
            if (frColMenu) {
                frColMenu.addEventListener('change', function(e) {
                    if (e.target.classList.contains('fr-column-toggle')) {
                        const field = e.target.getAttribute('data-field');
                        const col = field ? table.getColumn(field) : null;
                        if (col) {
                            if (e.target.checked) {
                                col.show();
                            } else {
                                col.hide();
                            }
                            frSaveColumnVisibilityToServer();
                        }
                    }
                });
            }
            function frSyncFromApiPage(page, reset) {
                var $btn = $('#frSyncFromApiBtn');
                var $status = $('#frSyncFromApiStatus');
                $status.text(reset ? 'Starting Faire API sync…' : ('Syncing page ' + page + '…'));
                return $.ajax({
                    url: '{{ route("faire.pricing.sync.api") }}',
                    type: 'POST',
                    timeout: 0,
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    data: { page: page, reset: reset ? 1 : 0 },
                }).then(function (res) {
                    if (!res || !res.success) {
                        throw new Error((res && res.message) || 'Sync failed.');
                    }
                    $status.text(res.message || ('Page ' + page + ' done'));
                    if (res.done) {
                        return res;
                    }
                    return frSyncFromApiPage(page + 1, false);
                });
            }

            $('#frSyncFromApiBtn').on('click', function () {
                var $btn = $(this);
                if ($btn.prop('disabled')) return;
                if (!confirm('Pull live Faire listings, wholesale prices, and stock from the Faire products API into faire_metric?\n\nUsed by pricing and listings.')) {
                    return;
                }
                $btn.prop('disabled', true);
                frSyncFromApiPage(1, true)
                    .then(function (res) {
                        if (window.toastr) toastr.success((res && res.message) || 'Faire API sync completed.');
                        else alert((res && res.message) || 'Faire API sync completed.');
                        if (table) table.setData('/faire/pricing-data');
                    })
                    .catch(function (err) {
                        var message = (err && err.message) ? err.message : 'Faire API sync failed.';
                        if (err && err.responseJSON && err.responseJSON.message) {
                            message = err.responseJSON.message;
                        }
                        if (window.toastr) toastr.error(message);
                        else alert(message);
                    })
                    .always(function () {
                        $btn.prop('disabled', false);
                    });
            });
        });
    </script>
@endsection
