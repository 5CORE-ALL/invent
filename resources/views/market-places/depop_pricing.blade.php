@extends('layouts.vertical', ['title' => 'Depop - Analytics', 'sidenav' => 'condensed'])

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
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title { padding-right: 0 !important; }
        .tabulator-row.dp-parent-row,
        .tabulator-row.dp-parent-row .tabulator-cell {
            background-color: #fffef2 !important;
            font-weight: 700 !important;
            min-height: 48px !important;
        }
        .tabulator-paginator label { margin-right: 5px; }
        .dp-sc { display:inline-block; width:12px; height:12px; border-radius:50%; margin-right:6px; border:1px solid #ddd; }
        .dp-sc.def { background:#6c757d; }
        .dp-sc.red { background:#dc3545; }
        .dp-sc.green { background:#28a745; }
        .dp-sc.pink { background:#e83e8c; }
        .dp-manual-dropdown { position: relative; display: inline-block; }
        .dp-manual-dropdown .dropdown-menu {
            position: absolute; top: 100%; left: 0; z-index: 1050;
            display: none; min-width: 200px; padding: .5rem 0; margin: 0;
            background: #fff; border: 1px solid #dee2e6; border-radius: .375rem;
            box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
        }
        .dp-manual-dropdown.show .dropdown-menu { display: block; }
        .dp-dropdown-item {
            display: block; width: 100%; padding: .5rem 1rem; clear: both;
            font-weight: 400; color: #212529; text-decoration: none;
            background: transparent; border: 0; cursor: pointer; white-space: nowrap;
        }
        .dp-dropdown-item:hover { background: #e9ecef; }
        #summary-stats .d-flex { gap: 8px !important; }
        #summary-stats .badge {
            font-size: 1rem; white-space: nowrap; font-weight: bold;
            display: inline-flex; align-items: center; justify-content: center;
        }
        #summary-stats .dp-filter-badge.active-filter {
            outline: 3px solid #0d6efd;
            outline-offset: 2px;
        }
        .depop-select-header { display: flex; align-items: center; justify-content: center; }
        #dpOpSpriceModal.modal { align-items: flex-start; padding-top: 1.5rem; }
        #dpOpSpriceModal .dp-op-modal-dialog { margin-top: 0; }
        #dpOpSpriceModal .dp-op-drag-header {
            cursor: move; user-select: none; gap: 30px; padding: 2.4rem 1.5rem;
        }
        #dpOpSpriceModal .dp-op-header-title {
            display: flex; align-items: center; gap: 30px; min-width: 0;
            font-size: 1.24rem; line-height: 1.2; white-space: nowrap;
        }
        #dpOpSpriceModal .dp-op-header-img-wrap {
            flex: 0 0 auto; width: 120px; height: 120px; border-radius: 12px;
            overflow: hidden; background: #fff; cursor: zoom-in;
        }
        #dpOpSpriceModal .dp-op-header-thumb {
            width: 120px !important; height: 120px !important;
            max-width: 120px !important; max-height: 120px !important;
            object-fit: cover !important; display: block;
        }
        #dpOpSpriceModal .dp-op-metric-cell { text-align: center; font-weight: 700; vertical-align: middle; }
        #dpOpSpriceModal .dp-op-price-row {
            display: flex; align-items: center; justify-content: flex-end; gap: 6px; flex-wrap: wrap;
        }
        #dpOpSpriceModal .dp-sop-icon-btn { border: 0; background: transparent; padding: 0; line-height: 0; cursor: pointer; }
        #dpOpSpriceModal .dp-sop-icon-btn img { width: 36px; height: 36px; object-fit: contain; display: block; }
        #dpOpSpriceModal .dp-sop-edit-btn {
            border: 0; background: transparent; padding: 0 2px; color: #6c757d; line-height: 1; cursor: pointer;
        }
        #dpOpSpriceModal .dp-sop-edit-btn:hover { color: #0d6efd; }
        #dpOpSpriceModal .dp-sop-sheet-input { width: 220px; display: none; }
        #dpOpSpriceModal .dp-sop-sheet-input.is-open { display: inline-block; }
        #dpOpImgHoverPreview {
            position: fixed; display: none; z-index: 200080; pointer-events: none;
            max-width: min(640px, 90vw); max-height: 80vh; object-fit: contain;
            background: #fff; border-radius: 10px; padding: 6px;
            box-shadow: 0 8px 28px rgba(0,0,0,.25);
        }
        @include('partials.channel-pef-promo', ['channelPromoPart' => 'css', 'channelPromoChannel' => 'depop'])
        @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'css', 'ebaySprcDilChannel' => 'depop'])
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Depop - Analytics',
        'sub_title'  => '',
    ])

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div id="summary-stats" class="mb-2 p-3 bg-light rounded">
                        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Summary metrics">
                            <span class="badge bg-dark fs-6 p-2" id="dp-rows-count-badge" title="Rows after filters">Row: 0</span>
                            <span class="badge bg-primary fs-6 p-2" id="dp-total-sales-badge"
                                title="Σ Depop sheet Sale AMT (item price × qty) in last 30 sale days">Sales: $0</span>
                            <span class="badge bg-info fs-6 p-2" id="dp-avg-gpft-badge"
                                title="L30 GPFT % = PFT ÷ Sales. PFT = (sheet sales × margin) − (LP × D L30). Price $0 is skipped (not −LP). No ship.">GPFT: 0%</span>
                            <span class="badge bg-success fs-6 p-2" id="dp-total-profit-badge"
                                title="PFT = Σ ((Depop sheet sales × marketplace margin) − LP × D L30). Unpriced SKUs do not subtract LP. No ship.">PFT: $0</span>
                            <span class="badge fs-6 p-2" id="dp-avg-roi-badge"
                                style="background-color:#6f42c1;color:#fff;"
                                title="L30 GROI % = PFT ÷ COGS. COGS = Σ (LP × D L30) only on SKUs with sheet sales. No ship.">GROI: 0%</span>
                            @include('partials.analytics-dil-badge', ['dilChannel' => 'depop'])
                            <span class="badge bg-success fs-6 p-2 dp-filter-badge" id="dp-sold-pct-badge"
                                data-filter="more_sold" style="cursor:pointer;"
                                title="Click to filter D L30 &gt; 0 (INV &gt; 0)">
                                Sold &gt;0: <span id="dp-more-sold-count">0</span>
                            </span>
                            <span class="badge bg-danger fs-6 p-2 dp-filter-badge" id="dp-zero-sold-badge"
                                data-filter="zero_sold" style="cursor:pointer;"
                                title="Click to filter D L30 = 0 (INV &gt; 0)">
                                0 Sold: <span id="dp-zero-sold-count">0</span>
                            </span>
                            @include('partials.lmp-missing-badge', ['lmpBadgeId' => 'depop-lmp-missing-badge', 'lmpChannelKey' => 'depop'])
                            @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'depop-price-gt-lmp-badge', 'pglChannelKey' => 'depop', 'pglPriceField' => 'price'])
                            @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'depop-price-lt80-lmp-badge', 'pltChannelKey' => 'depop', 'pltPriceField' => 'price'])
                            <span class="badge fs-6 p-2" id="depop-blue-triangle-badge"
                                style="background-color:#0d6efd;color:#fff;font-weight:700;cursor:pointer;"
                                title="Blue triangle: S PRC ≠ Price. Click to filter.">
                                <i class="fas fa-exclamation-triangle"></i> 0</span>
                            <span class="badge bg-secondary fs-6 p-2"
                                title="marketplace_percentages.percentage (marketplace = Depop). Ship is not used.">
                                Margin: {{ number_format((float) ($marginPercent ?? 87), 2) }}%
                            </span>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <select id="dp-inv-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">All Inventory</option>
                            <option value="zero">0 Inventory</option>
                            <option value="more" selected>More than 0</option>
                        </select>
                        <select id="dp-sold-filter" class="form-select form-select-sm" style="width:120px;"
                            title="Filter by Depop sheet D L30. 0 / &gt; 0 require INV &gt; 0.">
                            <option value="all">All</option>
                            <option value="zero">0 sold</option>
                            <option value="more">&gt; 0 sold</option>
                        </select>
                        <select id="dp-gpft-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0–10%</option>
                            <option value="10-20">10–20%</option>
                            <option value="20-30">20–30%</option>
                            <option value="30-40">30–40%</option>
                            <option value="40-50">40–50%</option>
                            <option value="50plus">Above 50%</option>
                        </select>
                        <select id="dp-cvr-filter" class="form-select form-select-sm" style="width:130px;"
                            title="CVR = D L30 ÷ OV L30">
                            <option value="all">All CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-3">0-3%</option>
                            <option value="3-7">3-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                        <select id="dp-roi-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">ROI%</option>
                            <option value="lt40">&lt; 40%</option>
                            <option value="40-75">40–75%</option>
                            <option value="75-125">75–125%</option>
                            <option value="gt125">125%+</option>
                        </select>
                        <div class="dp-manual-dropdown">
                            <button class="btn btn-light btn-sm dp-dil-toggle" type="button" id="dp-dil-btn">
                                <span class="dp-sc def"></span>DIL%
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dp-dropdown-item dp-dil-item active" href="#" data-color="all"><span class="dp-sc def"></span>All DIL</a></li>
                                <li><a class="dp-dropdown-item dp-dil-item" href="#" data-color="red"><span class="dp-sc red"></span>Red (&lt;25%)</a></li>
                                <li><a class="dp-dropdown-item dp-dil-item" href="#" data-color="green"><span class="dp-sc green"></span>Green (25–50%)</a></li>
                                <li><a class="dp-dropdown-item dp-dil-item" href="#" data-color="pink"><span class="dp-sc pink"></span>Pink (50%+)</a></li>
                            </ul>
                        </div>

                        <input type="text" id="sku-search" class="form-control form-control-sm"
                            style="max-width:220px;" placeholder="Search Parent or SKU...">
                        <button type="button" id="refresh-pricing-table" class="btn btn-sm btn-outline-primary" title="Refresh">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <a href="{{ route('depop.pricing.export') }}" class="btn btn-sm btn-success" id="export-btn">
                            <i class="fa fa-file-csv"></i>
                        </a>
                        @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'buttons', 'ebaySprcDilChannel' => 'depop'])
                        @include('partials.channel-pef-promo', ['channelPromoPart' => 'buttons', 'channelPromoChannel' => 'depop'])

                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                                id="sprice-mode-btn" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-sliders-h"></i> PrcM
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item sprice-mode-item active" href="#" data-mode=""><i class="fas fa-times me-1"></i> Off</a></li>
                                <li><a class="dropdown-item sprice-mode-item" href="#" data-mode="decrease"><i class="fas fa-arrow-down me-1 text-warning"></i> Decrease</a></li>
                                <li><a class="dropdown-item sprice-mode-item" href="#" data-mode="increase"><i class="fas fa-arrow-up me-1 text-success"></i> Increase</a></li>
                                <li><a class="dropdown-item sprice-mode-item" href="#" data-mode="same"><i class="fas fa-equals me-1 text-info"></i> Same Price</a></li>
                            </ul>
                        </div>

                        <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light"
                            title="Target SGROI% — S PRC = (LP × (1 + Target%/100)) / margin. No ship.">
                            <label for="dp-target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                                <span aria-hidden="true">🎯</span> SGROI:
                            </label>
                            <input type="number" id="dp-target-roi-input" class="form-control form-control-sm text-end"
                                placeholder="30" step="0.1" style="width: 56px;">
                            <button id="dp-apply-target-roi-btn" class="btn btn-sm btn-primary" type="button">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>
                        <div class="d-inline-flex align-items-center gap-1 p-1 border rounded bg-light"
                            title="Target GPFT% — S PRC = LP / (margin − Target GPFT%/100). No ship.">
                            <label for="dp-target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                                <span aria-hidden="true">🎯</span> GPFT%:
                            </label>
                            <input type="number" id="dp-target-gpft-input" class="form-control form-control-sm text-end"
                                placeholder="30" step="0.1" style="width: 56px;">
                            <button id="dp-apply-target-gpft-btn" class="btn btn-sm btn-primary" type="button">
                                <i class="fas fa-calculator"></i>
                            </button>
                        </div>

                        <form id="import-form" class="d-flex align-items-center gap-1 mb-0">
                            @csrf
                            <input type="file" name="file" id="import-file" accept=".csv,.txt"
                                class="form-control form-control-sm" style="max-width: 180px;" required>
                            <button type="submit" class="btn btn-sm btn-primary" id="import-btn">
                                <i class="fa fa-upload"></i>
                            </button>
                        </form>

                        <div class="btn-group align-items-center" role="group" aria-label="Parent navigation">
                            <button type="button" id="play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Previous parent" disabled>
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button type="button" id="play-auto" class="btn btn-sm btn-primary rounded-circle shadow-sm" title="Start parent navigation">
                                <i class="fas fa-play"></i>
                            </button>
                            <button type="button" id="play-pause" class="btn btn-sm btn-warning rounded-circle shadow-sm" style="display: none;" title="Stop navigation">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button type="button" id="play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Next parent" disabled>
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>
                    </div>

                    <div id="discount-input-container" class="p-2 bg-light border rounded mb-2" style="display: none;">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span id="selected-skus-count" class="fw-bold"></span>
                            <span id="discount-input-label" class="text-muted small d-none">Same Price ($):</span>
                            <span id="discount-type-select-wrap">
                                <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                                    <option value="percentage">Percentage</option>
                                    <option value="value">Value ($)</option>
                                </select>
                            </span>
                            <input type="number" id="discount-percentage-input" class="form-control form-control-sm"
                                placeholder="Enter %" step="0.01" style="width: 140px;">
                            <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                            <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                                <i class="fas fa-eraser"></i> Clear SPRICE
                            </button>
                        </div>
                    </div>

                    <div id="depop-table-wrapper" style="height: calc(100vh - 220px); display: flex; flex-direction: column;">
                        <div id="depop-pricing-table" style="flex: 1;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'modals', 'channelPromoChannel' => 'depop'])
    @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'modals', 'ebaySprcDilChannel' => 'depop'])

    <div class="modal fade" id="dpOpSpriceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog dp-op-modal-dialog">
            <div class="modal-content">
                <div class="modal-header dp-op-drag-header" style="background-color: #0d6efd;">
                    <h5 class="modal-title text-white dp-op-header-title">
                        <span class="dp-op-header-img-wrap" id="dpOpModalImgWrap" style="display:none;">
                            <img id="dpOpModalImg" class="dp-op-header-thumb no-img-hover" data-no-img-hover alt="Product">
                        </span>
                        <span>Offer Sprice – <span id="dpOpModalSku"></span></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered mb-0">
                        <tbody>
                            <tr>
                                <th style="width: 40%;">Offer Sprice</th>
                                <td>
                                    <div class="dp-op-price-row">
                                        <span class="text-muted">$</span>
                                        <input type="number" class="form-control form-control-sm d-inline-block"
                                            id="dpOpSpriceInput" value="" step="0.01" min="0" placeholder="0.00"
                                            style="width: 90px; text-align: right;">
                                        <button type="button" class="dp-sop-icon-btn" id="dpSopBtn"
                                            title="Double-click to open SOP sheet">
                                            <img src="{{ asset('images/sop-icon.png') }}" alt="SOP" class="no-img-hover" data-no-img-hover>
                                        </button>
                                        <button type="button" class="dp-sop-edit-btn" id="dpSopEditBtn"
                                            title="Add / edit SOP sheet link">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <input type="url" class="form-control form-control-sm dp-sop-sheet-input"
                                            id="dpSopSheetInput" placeholder="Google Sheet URL"
                                            value="{{ $sopSheetUrl ?? '' }}"
                                            autocomplete="off" spellcheck="false">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th title="SGPFT% = ((price × margin − LP) / price) × 100 — no ship">SGPFT%</th>
                                <td class="dp-op-metric-cell" id="dpOpSgpft">-</td>
                            </tr>
                            <tr>
                                <th title="SGROI% = ((price × margin − LP) / LP) × 100 — no ship">SGROI%</th>
                                <td class="dp-op-metric-cell" id="dpOpSgroi">-</td>
                            </tr>
                            <tr>
                                <th title="SPFT% = SGPFT% − Ads%">SPFT%</th>
                                <td class="dp-op-metric-cell" id="dpOpSpft">-</td>
                            </tr>
                            <tr>
                                <th title="SNROI% = ((price × margin − LP − price × Ads%) / LP) × 100">SNROI%</th>
                                <td class="dp-op-metric-cell" id="dpOpSnroi">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    @include('partials.channel-pef-promo', ['channelPromoPart' => 'script', 'channelPromoChannel' => 'depop'])
    @include('partials.ebay-sprc-dil', ['ebaySprcDilPart' => 'script', 'ebaySprcDilChannel' => 'depop'])

    const DP_MARGIN = {{ (float) ($marginPercent ?? 87) }} / 100;
    let table = null;
    let allTableData = [];
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let samePriceModeActive = false;
    let selectedSkus = new Set();
    let priceGtLmpFilterActive = false;
    let priceLt80LmpFilterActive = false;
    let blueTriangleFilterActive = false;
    let dpDilFilter = 'all';
    let dpUniqueParents = [];
    let isDpPlayActive = false;
    let currentDpParentIndex = -1;
    let dpApplyingFilters = false;
    let dpFilterSeq = 0;

    function showToast(message, type) {
        type = type || 'info';
        const container = document.querySelector('.toast-container');
        if (!container) return;
        const bg = type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info';
        const el = document.createElement('div');
        el.className = `toast align-items-center text-white bg-${bg} border-0 mb-2`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        container.appendChild(el);
        new bootstrap.Toast(el, { delay: 6000 }).show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    function money(value) {
        const n = parseFloat(value);
        if (!isFinite(n)) return '<span class="text-muted">–</span>';
        return '$' + n.toFixed(2);
    }
    function dpPctStyle(color) {
        if (window.MetricPctColors && typeof MetricPctColors.styleForCellColor === 'function') {
            return MetricPctColors.styleForCellColor(color);
        }
        if (color === '#ffc107') {
            return 'color:#000;background-color:#ffc107;font-weight:700;padding:1px 5px;border-radius:3px;';
        }
        return 'color:' + color + ';font-weight:600;';
    }
    function dpRoiColor(v) {
        if (v < 40) return '#a00211';
        if (v < 75) return '#ffc107';
        if (v < 125) return '#28a745';
        return '#d63384';
    }
    function dpGpftColor(v) {
        return v < 10 ? '#a00211' : v < 15 ? '#ffc107' : v < 20 ? '#3591dc' : v <= 40 ? '#28a745' : '#e83e8c';
    }
    function dpPctHtml(v, kind) {
        const color = kind === 'gpft' ? dpGpftColor(v) : dpRoiColor(v);
        return '<span style="' + dpPctStyle(color) + '">' + Math.round(v) + '%</span>';
    }
    function dpIsParentRow(d) {
        return !!(d && (d.is_parent || d.is_parent_summary || String(d.sku || '').toUpperCase().indexOf('PARENT') === 0));
    }
    function dpSoldQty(d) {
        return parseInt(d && (d.al30 != null ? d.al30 : d.l30), 10) || 0;
    }
    function dpInv(d) {
        if (!d) return 0;
        const raw = (d.inv != null && d.inv !== '') ? d.inv : d.INV;
        const n = parseInt(raw, 10);
        // Shopify untracked / negative stock displays as 0 and is not "more than 0".
        if (!Number.isFinite(n) || n < 0) return 0;
        return n;
    }
    function dpRowMatchesFilters(d) {
        if (!d) return false;
        if (typeof d.getData === 'function') {
            try { d = d.getData() || {}; } catch (e) { /* use original */ }
        }
        if (!d) return false;

        if (isDpPlayActive && dpUniqueParents.length > 0 && currentDpParentIndex >= 0) {
            const currentKey = dpUniqueParents[currentDpParentIndex];
            if (currentKey) {
                const p = normalizeDpParentKey(d.parent);
                if (p !== currentKey && p !== ('PARENT ' + currentKey)) return false;
            }
        }

        const invF = $('#dp-inv-filter').val() || 'more';
        const inv = dpInv(d);
        if (invF === 'zero' && inv !== 0) return false;
        if (invF === 'more' && inv <= 0) return false;

        const soldF = $('#dp-sold-filter').val();
        if (soldF === 'zero' && !(inv > 0 && dpSoldQty(d) === 0)) return false;
        if (soldF === 'more' && !(inv > 0 && dpSoldQty(d) > 0)) return false;

        const gpftF = $('#dp-gpft-filter').val();
        if (gpftF && gpftF !== 'all') {
            const v = parseFloat(d.gpft) || 0;
            if (gpftF === 'negative' && !(v < 0)) return false;
            else if (gpftF === '0-10' && !(v >= 0 && v < 10)) return false;
            else if (gpftF === '10-20' && !(v >= 10 && v < 20)) return false;
            else if (gpftF === '20-30' && !(v >= 20 && v < 30)) return false;
            else if (gpftF === '30-40' && !(v >= 30 && v < 40)) return false;
            else if (gpftF === '40-50' && !(v >= 40 && v < 50)) return false;
            else if (gpftF === '50plus' && !(v >= 50)) return false;
        }

        const cvrF = $('#dp-cvr-filter').val();
        if (cvrF && cvrF !== 'all') {
            const v = parseFloat(d.cvr) || 0;
            if (cvrF === '0-0' && v !== 0) return false;
            else if (cvrF === '0-3' && !(v > 0 && v < 3)) return false;
            else if (cvrF === '3-7' && !(v >= 3 && v < 7)) return false;
            else if (cvrF === '7-13' && !(v >= 7 && v < 13)) return false;
            else if (cvrF === '13plus' && !(v >= 13)) return false;
        }

        const roiF = $('#dp-roi-filter').val();
        if (roiF && roiF !== 'all') {
            const v = parseFloat(d.groi) || 0;
            if (roiF === 'lt40' && !(v < 40)) return false;
            else if (roiF === '40-75' && !(v >= 40 && v < 75)) return false;
            else if (roiF === '75-125' && !(v >= 75 && v < 125)) return false;
            else if (roiF === 'gt125' && !(v >= 125)) return false;
        }

        if (dpDilFilter !== 'all') {
            const ov = parseFloat(d.ov_l30) || 0;
            const dil = inv > 0 ? (ov / inv) * 100 : 0;
            if (dpDilFilter === 'red' && !(dil < 25)) return false;
            else if (dpDilFilter === 'green' && !(dil >= 25 && dil < 50)) return false;
            else if (dpDilFilter === 'pink' && !(dil >= 50)) return false;
        }

        const q = ($('#sku-search').val() || '').trim().toLowerCase();
        if (q) {
            const hay = (String(d.parent || '') + ' ' + String(d.sku || '')).toLowerCase();
            if (!hay.includes(q)) return false;
        }
        if (priceGtLmpFilterActive && window.PriceGtLmpBadge && !PriceGtLmpBadge.hasRedTriangle(d, 'price')) {
            return false;
        }
        if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge && !PriceLt80LmpBadge.hasPurpleTriangle(d, 'price')) {
            return false;
        }
        if (blueTriangleFilterActive && !dpHasBlueTriangle(d)) return false;
        return true;
    }
    function dpRowSprice(data) {
        if (!data) return 0;
        if (typeof chPromoTableSprice === 'function') {
            const saved = Number(chPromoTableSprice(data)) || 0;
            if (saved > 0) return saved;
        }
        return parseFloat(data.SPRICE != null ? data.SPRICE : data.sprice) || 0;
    }
    function dpHasBlueTriangle(data) {
        if (!data || dpIsParentRow(data)) return false;
        if (!(dpInv(data) > 0)) return false;
        const sprice = dpRowSprice(data);
        const price = parseFloat(data.price) || 0;
        return sprice > 0 && price > 0 && Math.round(sprice * 100) !== Math.round(price * 100);
    }
    window.dpHasBlueTriangle = dpHasBlueTriangle;
    function dpSpriceMetrics(data, spriceOpt) {
        const sprice = spriceOpt != null ? Number(spriceOpt) : dpRowSprice(data);
        if (!(sprice > 0)) return { sgpft: 0, sroi: 0 };
        const margin = (typeof chPromoTakehomeMargin === 'function')
            ? chPromoTakehomeMargin(data)
            : (parseFloat(data && data._margin) || DP_MARGIN);
        const lp = parseFloat(data && (data.lp != null ? data.lp : data.LP_productmaster)) || 0;
        return {
            sgpft: Math.round(((sprice * margin - lp) / sprice) * 100),
            sroi: lp > 0 ? Math.round(((sprice * margin - lp) / lp) * 100) : 0
        };
    }
    function syncDpTriangleBadgeState() {
        $('#depop-blue-triangle-badge').css({
            outline: blueTriangleFilterActive ? '3px solid #ffc107' : '',
            outlineOffset: blueTriangleFilterActive ? '2px' : ''
        });
    }
    function anyModeActive() {
        return decreaseModeActive || increaseModeActive || samePriceModeActive;
    }
    function updateSelectedCount() {
        const count = selectedSkus.size;
        $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
        $('#discount-input-container').toggle(anyModeActive() && count > 0);
    }
    function updateSelectAllHeaderCheckbox() {
        const el = document.getElementById('depop-select-all');
        if (!el || !table) return;
        const rows = table.getRows('active');
        if (!rows.length) {
            el.checked = false;
            el.indeterminate = false;
            return;
        }
        let selected = 0;
        rows.forEach(r => {
            const d = r.getData();
            if (d.sku && !dpIsParentRow(d) && selectedSkus.has(String(d.sku))) selected++;
        });
        const kids = rows.filter(r => !dpIsParentRow(r.getData())).length;
        el.checked = selected === kids && kids > 0;
        el.indeterminate = selected > 0 && selected < kids;
    }
    function syncSpriceModeBtn() {
        const $btn = $('#sprice-mode-btn');
        $btn.removeClass('btn-secondary btn-warning btn-success btn-info btn-danger');
        $('.sprice-mode-item').removeClass('active');
        if (decreaseModeActive) {
            $btn.addClass('btn-warning').html('<i class="fas fa-arrow-down"></i> Decrease');
            $('.sprice-mode-item[data-mode="decrease"]').addClass('active');
        } else if (increaseModeActive) {
            $btn.addClass('btn-success').html('<i class="fas fa-arrow-up"></i> Increase');
            $('.sprice-mode-item[data-mode="increase"]').addClass('active');
        } else if (samePriceModeActive) {
            $btn.addClass('btn-info').html('<i class="fas fa-equals"></i> Same Price');
            $('.sprice-mode-item[data-mode="same"]').addClass('active');
        } else {
            $btn.addClass('btn-secondary').html('<i class="fas fa-sliders-h"></i> PrcM');
            $('.sprice-mode-item[data-mode=""]').addClass('active');
        }
    }
    function syncDiscountInputUi() {
        const $input = $('#discount-percentage-input');
        if (samePriceModeActive) {
            $('#discount-type-select-wrap').hide();
            $('#discount-input-label').removeClass('d-none');
            $input.attr('placeholder', 'Enter price (e.g. 19.99)').attr('step', '0.01');
            $('#apply-discount-btn').text('Apply Same Price');
        } else {
            $('#discount-type-select-wrap').show();
            $('#discount-input-label').addClass('d-none');
            const t = $('#discount-type-select').val();
            $input.attr('placeholder', t === 'percentage' ? 'Enter %' : 'Enter $');
            $('#apply-discount-btn').text('Apply');
        }
    }
    function enterPriceMode(which) {
        const turningOff = !which
            || (which === 'decrease' && decreaseModeActive)
            || (which === 'increase' && increaseModeActive)
            || (which === 'same' && samePriceModeActive);
        decreaseModeActive = !turningOff && which === 'decrease';
        increaseModeActive = !turningOff && which === 'increase';
        samePriceModeActive = !turningOff && which === 'same';
        syncSpriceModeBtn();
        if (!anyModeActive()) {
            selectedSkus.clear();
        }
        updateSelectedCount();
        syncDiscountInputUi();
        if (table) table.redraw(true);
    }
    function roundToRetailPrice(price) {
        if (price < 20.99) return +price.toFixed(2);
        return +(Math.ceil(price) - 0.01).toFixed(2);
    }
    function normalizeDpParentKey(val) {
        if (val == null || val === '') return '';
        return String(val).trim().replace(/\s+/g, ' ').replace(/^PARENT\s+/i, '');
    }
    function buildDpUniqueParents() {
        const seen = {};
        const list = [];
        const rows = (Array.isArray(allTableData) && allTableData.length)
            ? allTableData
            : ((table && typeof table.getData === 'function' ? table.getData('all') : []) || []);
        rows.forEach(function(r) {
            const p = normalizeDpParentKey(r.parent);
            if (p && !seen[p]) { seen[p] = true; list.push(p); }
        });
        list.sort(function(a, b) { return String(a).localeCompare(String(b)); });
        return list;
    }
    function updateDpPlayButtonStates() {
        $('#play-backward').prop('disabled', !isDpPlayActive || currentDpParentIndex <= 0);
        $('#play-forward').prop('disabled', !isDpPlayActive || currentDpParentIndex >= dpUniqueParents.length - 1);
    }
    function applyDepopFilters() {
        if (!table || dpApplyingFilters) return;
        if (window.ParentExpand && ParentExpand.isExpanded()) {
            ParentExpand.beforeFilters(function() { applyDepopFilters(); });
            return;
        }

        const source = (Array.isArray(allTableData) && allTableData.length)
            ? allTableData
            : ((typeof table.getData === 'function' ? table.getData('all') : []) || []);
        if (!source.length) return;

        const filtered = source.filter(dpRowMatchesFilters);
        const soldF = $('#dp-sold-filter').val();
        $('.dp-filter-badge').removeClass('active-filter');
        if (soldF === 'more') $('#dp-sold-pct-badge').addClass('active-filter');
        if (soldF === 'zero') $('#dp-zero-sold-badge').addClass('active-filter');

        const seq = ++dpFilterSeq;
        dpApplyingFilters = true;
        Promise.resolve(table.setData(filtered)).then(function() {
            if (seq !== dpFilterSeq) return;
            try { table.clearFilter(true); } catch (e) { /* ignore */ }
            try { table.setPage(1); } catch (e) { /* ignore */ }
            updateSummary(filtered);
        }).catch(function() {
            /* keep last good view */
        }).then(function() {
            if (seq === dpFilterSeq) dpApplyingFilters = false;
        });
    }
    function updateSummary(rowsInput) {
        let rows = Array.isArray(rowsInput) ? rowsInput.slice() : [];
        if (!rows.length && table) {
            try {
                rows = table.getData('active') || table.getData() || [];
            } catch (e) {
                rows = [];
            }
        }

        let totalSales = 0, totalProfit = 0, totalCogs = 0, zeroSold = 0, moreSold = 0, visible = 0;
        rows.forEach(function(row) {
            if (dpIsParentRow(row)) return;
            visible++;
            const al30 = dpSoldQty(row);
            const sales = parseFloat(row.sales) || 0;
            const lp = parseFloat(row.lp) || 0;
            const margin = parseFloat(row._margin) || DP_MARGIN;
            // L30 PFT from the Depop sheet: (sales × margin) − (LP × qty).
            // Never do (Price $0 × margin) − LP — that made GROI −100%.
            if (sales > 0 && al30 > 0) {
                totalSales += sales;
                totalProfit += (sales * margin) - (lp * al30);
                totalCogs += lp * al30;
            }
            if (dpInv(row) <= 0) return;
            if (al30 === 0) zeroSold++; else moreSold++;
        });
        const gpft = totalSales > 0 ? Math.round((totalProfit / totalSales) * 100) : 0;
        const groi = totalCogs > 0 ? Math.round((totalProfit / totalCogs) * 100) : 0;
        $('#dp-rows-count-badge').text('Row: ' + visible.toLocaleString());
        $('#dp-total-sales-badge').text('Sales: $' + Math.round(totalSales).toLocaleString());
        $('#dp-total-profit-badge').text('PFT: $' + Math.round(totalProfit).toLocaleString());
        $('#dp-avg-gpft-badge').text('GPFT: ' + gpft + '%');
        $('#dp-avg-roi-badge').text('GROI: ' + groi + '%');
        $('#dp-more-sold-count').text(moreSold.toLocaleString());
        $('#dp-zero-sold-count').text(zeroSold.toLocaleString());
        const lmpRows = (allTableData && allTableData.length) ? allTableData : rows;
        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.update('#depop-price-gt-lmp-badge', lmpRows, 'depop', 'price');
            if (window.PriceLt80LmpBadge) {
                PriceLt80LmpBadge.update('#depop-price-lt80-lmp-badge', lmpRows, 'depop', 'price');
            }
        }
        if (window.LmpMissingBadge) {
            LmpMissingBadge.update('#depop-lmp-missing-badge', lmpRows, 'depop');
        }
        let blue = 0;
        rows.forEach(function(row) { if (dpHasBlueTriangle(row)) blue++; });
        $('#depop-blue-triangle-badge').html('<i class="fas fa-exclamation-triangle"></i> ' + blue.toLocaleString());
        syncDpTriangleBadgeState();
    }

    $(document).ready(function() {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

        table = new Tabulator("#depop-pricing-table", {
            ajaxURL: "{{ route('depop.pricing.data') }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                allTableData = data;
                if (window.ParentExpand) ParentExpand.captureDataset(allTableData);
                return data;
            },
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            placeholder: "No SKUs found.",
            rowFormatter: function(row) {
                if (dpIsParentRow(row.getData())) row.getElement().classList.add('dp-parent-row');
            },
            columns: [
                {
                    title: '<div class="depop-select-header"><input type="checkbox" id="depop-select-all"></div>',
                    field: "_select",
                    width: 38,
                    headerSort: false,
                    frozen: true,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (dpIsParentRow(d) || !d.sku) return '';
                        const safe = String(d.sku).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                        const checked = selectedSkus.has(String(d.sku)) ? 'checked' : '';
                        return `<input type="checkbox" class="depop-sku-chk" data-sku="${safe}" ${checked}>`;
                    }
                },
                {
                    title: "Parent",
                    field: "parent",
                    width: 120,
                    frozen: true,
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (dpIsParentRow(d)) return '';
                        const v = cell.getValue() || '';
                        if (!v) return '<span style="color:#adb5bd;">–</span>';
                        return `<span style="color:#0d6efd;font-size:11px;font-weight:600;">${v}</span>`;
                    }
                },
                (window.ParentExpand ? ParentExpand.columnDef() : { title: "P", field: "_parent_expand", width: 36, headerSort: false }),
                {
                    title: "Image",
                    field: "image",
                    width: 60,
                    headerSort: false,
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const v = cell.getValue();
                        if (dpIsParentRow(d) || !v) return '';
                        return `<img src="${v}" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:4px;">`;
                    }
                },
                {
                    title: "SKU",
                    field: "sku",
                    minWidth: 200,
                    frozen: true,
                    cssClass: "fw-bold text-primary",
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const val = cell.getValue() || '';
                        if (dpIsParentRow(d)) return `<span style="color:#1e40af;font-weight:700;">${val}</span>`;
                        return `<span class="fw-bold">${String(val).replace(/</g,'&lt;')}</span>`;
                    }
                },
                {
                    title: "INV",
                    field: "inv",
                    sorter: "number",
                    hozAlign: "center",
                    width: 55,
                    formatter: function(cell) {
                        const val = dpInv(cell.getRow().getData());
                        if (val === 0) return `<span style="color:#dc3545;font-weight:600;">0</span>`;
                        return `<span style="font-weight:600;">${val}</span>`;
                    }
                },
                {
                    title: "OV L30",
                    field: "ov_l30",
                    sorter: "number",
                    hozAlign: "center",
                    width: 60,
                    formatter: function(cell) {
                        return `<span style="font-weight:700;">${parseInt(cell.getValue(), 10) || 0}</span>`;
                    }
                },
                {
                    title: "Dil",
                    field: "dil_percent",
                    sorter: "number",
                    hozAlign: "center",
                    width: 55,
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const inv = parseFloat(row.inv) || 0;
                        const ovL30 = parseFloat(row.ov_l30) || 0;
                        if (inv === 0) return `<span style="color:#6c757d;">0%</span>`;
                        const dil = (ovL30 / inv) * 100;
                        const color = dil < 25 ? '#dc3545' : dil < 50 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${Math.round(dil)}%</span>`;
                    }
                },
                {
                    title: "D L30",
                    field: "al30",
                    sorter: "number",
                    hozAlign: "center",
                    width: 55,
                    headerTooltip: "Depop units sold from /depop/sheet last 30 sale days",
                    formatter: function(cell) {
                        return `<span style="font-weight:700;">${parseInt(cell.getValue(), 10) || 0}</span>`;
                    }
                },
                {
                    title: "Price",
                    field: "price",
                    sorter: "number",
                    hozAlign: "right",
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (dpIsParentRow(d)) return '<span style="color:#6c757d;">–</span>';
                        const lmpTri = (window.PriceGtLmpBadge ? PriceGtLmpBadge.triangleHtml(cell.getValue(), d.lmp_price || d.lmp || d.LMP) : '');
                        const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(cell.getValue(), d.lmp_price || d.lmp || d.LMP) : '');
                        return money(cell.getValue()) + lmpTri + purpleTri;
                    }
                },
                {
                    title: "GROI",
                    field: "groi",
                    sorter: "number",
                    hozAlign: "right",
                    headerTooltip: "GROI = ((Price × margin) − LP) ÷ LP. No ship. Margin from marketplace Depop.",
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (dpIsParentRow(d)) return '<span style="color:#6c757d;">–</span>';
                        return dpPctHtml(parseFloat(cell.getValue()) || 0, 'groi');
                    }
                },
                {
                    title: "GPFT",
                    field: "gpft",
                    sorter: "number",
                    hozAlign: "right",
                    headerTooltip: "GPFT = ((Price × margin) − LP) ÷ Price. No ship.",
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const v = parseFloat(cell.getValue());
                        if (isNaN(v) || (v === 0 && dpIsParentRow(d))) return '<span style="color:#6c757d;">–</span>';
                        return dpPctHtml(v, 'gpft');
                    }
                },
                {
                    title: "Profit",
                    field: "profit",
                    sorter: "number",
                    hozAlign: "right",
                    headerTooltip: "Unit profit = (Price × margin) − LP. No ship.",
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue()) || 0;
                        const color = v >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color:${color};font-weight:600;">${money(v)}</span>`;
                    }
                },
                {
                    title: "Sales",
                    field: "sales",
                    sorter: "number",
                    hozAlign: "right",
                    headerTooltip: "Σ item_price × qty from Depop sales sheet (L30 window)",
                    formatter: function(cell) { return money(cell.getValue()); }
                },
                {
                    title: "LP",
                    field: "lp",
                    sorter: "number",
                    hozAlign: "right",
                    visible: false,
                    formatter: function(cell) { return money(cell.getValue()); }
                },
                ...(typeof channelPromoAnalyticsColumns === 'function' ? channelPromoAnalyticsColumns() : (typeof channelPromoPricingColumns === 'function' ? channelPromoPricingColumns() : [])),
                {
                    title: "Sprc Dil",
                    field: "SPRC_DIL",
                    hozAlign: "center",
                    headerSort: true,
                    headerTooltip: "S PRC from Dil → Target NROI. D L30 = 0 uses min Target NROI. Formula: (LP × (1 + NROI%/100)) / margin. No ship.",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        if (dpIsParentRow(rowData) || typeof ebayDilGroiMetaForRow !== 'function') return '';
                        const meta = ebayDilGroiMetaForRow(rowData);
                        if (!meta || !(meta.sprc > 0)) return '<span style="color:#adb5bd;">–</span>';
                        return '<span style="font-weight:600;color:#6f42c1;">$' + meta.sprc.toFixed(2) + '</span>';
                    },
                    width: 78
                },
                {
                    title: "Sprice",
                    field: "sprice",
                    sorter: "number",
                    hozAlign: "right",
                    headerTooltip: "S PRC. Blue triangle = S PRC ≠ Price. Red text = S PRC ≥ LMP. No ship in SGROI/SGPFT.",
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (dpIsParentRow(d)) return '<span style="color:#6c757d;">–</span>';
                        let value = dpRowSprice(d);
                        if (!(value > 0)) return '<span class="text-muted">–</span>';
                        const live = parseFloat(d.price) || 0;
                        const lmp = parseFloat(d.lmp_price || d.lmp || d.LMP) || 0;
                        const cap = window.SpriceLmpCap ? SpriceLmpCap.apply(d, value) : null;
                        const overLmp = cap ? cap.alert : (lmp > 0 && value + 0.0001 >= lmp);
                        const formatted = '$' + value.toFixed(2);
                        const priceHtml = overLmp
                            ? '<span style="color:#dc3545;font-weight:600;">' + formatted + '</span>'
                            : '<span style="font-weight:600;">' + formatted + '</span>';
                        const blueTri = (live > 0 && Math.round(value * 100) !== Math.round(live * 100))
                            ? '<i class="fas fa-exclamation-triangle" style="color:#0d6efd;font-size:10px;margin-left:3px;" title="S PRC $'
                                + value.toFixed(2) + ' ≠ Price $' + live.toFixed(2) + '"></i>'
                            : '';
                        const redTri = overLmp
                            ? (cap ? cap.triangleHtml : '<i class="fas fa-exclamation-triangle" style="color:#dc3545;font-size:10px;margin-left:3px;"></i>')
                            : '';
                        return '<span style="white-space:nowrap;display:inline-flex;align-items:center;gap:2px;">'
                            + priceHtml + blueTri + redTri + '</span>';
                    }
                },
                {
                    title: "OP",
                    field: "op_sprice",
                    hozAlign: "center",
                    width: 50,
                    headerSort: false,
                    headerTooltip: "Offer Sprice calculator — separate from S Price. Click to view Offer Sprice and SGPFT / SGROI / SPFT / SNROI. No ship.",
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (dpIsParentRow(d)) return '';
                        return '<i class="fas fa-question-circle dp-op-btn" title="Offer Sprice"'
                            + ' style="color:#0d6efd;font-size:15px;cursor:pointer;line-height:1;"></i>';
                    },
                    cellClick: function(e, cell) {
                        const d = cell.getRow().getData();
                        if (dpIsParentRow(d)) return;
                        openDpOpSpriceModal(cell.getRow());
                    }
                },
                {
                    title: "SGROI",
                    field: "sroi",
                    sorter: "number",
                    hozAlign: "right",
                    headerTooltip: "SGROI = ((S PRC × margin) − LP) ÷ LP. No ship.",
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (dpIsParentRow(d)) return '<span style="color:#6c757d;">–</span>';
                        return dpPctHtml(dpSpriceMetrics(d).sroi, 'groi');
                    }
                },
                {
                    title: "SGPFT",
                    field: "sgpft",
                    sorter: "number",
                    hozAlign: "right",
                    headerTooltip: "SGPFT = ((S PRC × margin) − LP) ÷ S PRC. No ship.",
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        if (dpIsParentRow(d)) return '<span style="color:#6c757d;">–</span>';
                        return dpPctHtml(dpSpriceMetrics(d).sgpft, 'gpft');
                    }
                },
            ],
            dataLoaded: function() {
                if (dpApplyingFilters) return;
                if (window.ParentExpand && ParentExpand.isExpanded()) return;
                if (typeof ebayScheduleSprcDilAutoApply === 'function') ebayScheduleSprcDilAutoApply();
                setTimeout(function() {
                    applyDepopFilters();
                    if (typeof window.chPromoAutofitColumns === 'function') window.chPromoAutofitColumns(table);
                }, 100);
            },
            dataFiltered: function(_f, rows) {
                updateSummary((rows || []).map(function(r) { return r.getData ? r.getData() : r; }));
            },
            renderComplete: function() {
                updateSummary();
                updateSelectAllHeaderCheckbox();
            }
        });

        if (window.ParentExpand) {
            ParentExpand.configure({
                parentField: 'parent',
                skuField: 'sku',
                getTable: function() { return table; },
                getDataset: function() { return allTableData; },
                onAfterExpand: function() { updateSummary(table.getData()); },
                onCollapse: function() { applyDepopFilters(); }
            });
            ParentExpand.bind();
        }

        table.on('tableBuilt', function() {
            setTimeout(function() {
                if (!dpApplyingFilters && allTableData.length) applyDepopFilters();
            }, 100);
        });

        $('#dp-inv-filter, #dp-sold-filter, #dp-gpft-filter, #dp-cvr-filter, #dp-roi-filter').on('change', applyDepopFilters);
        $('#sku-search').on('input', applyDepopFilters);
        $('#refresh-pricing-table').on('click', function() { if (table) table.setData(); });

        $(document).on('click', '.dp-dil-toggle', function(e) {
            e.preventDefault();
            $(this).closest('.dp-manual-dropdown').toggleClass('show');
        });
        $(document).on('click', '.dp-dil-item', function(e) {
            e.preventDefault();
            dpDilFilter = $(this).data('color') || 'all';
            $('.dp-dil-item').removeClass('active');
            $(this).addClass('active');
            const label = $(this).text().trim();
            const cls = dpDilFilter === 'red' ? 'red' : dpDilFilter === 'green' ? 'green' : dpDilFilter === 'pink' ? 'pink' : 'def';
            $('#dp-dil-btn').html('<span class="dp-sc ' + cls + '"></span>' + (dpDilFilter === 'all' ? 'DIL%' : label));
            $(this).closest('.dp-manual-dropdown').removeClass('show');
            applyDepopFilters();
        });
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.dp-manual-dropdown').length) $('.dp-manual-dropdown').removeClass('show');
        });

        $('#dp-sold-pct-badge').on('click', function() {
            const next = $('#dp-sold-filter').val() === 'more' ? 'all' : 'more';
            $('#dp-sold-filter').val(next);
            applyDepopFilters();
        });
        $('#dp-zero-sold-badge').on('click', function() {
            const next = $('#dp-sold-filter').val() === 'zero' ? 'all' : 'zero';
            $('#dp-sold-filter').val(next);
            applyDepopFilters();
        });

        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.bind({
                badge: '#depop-price-gt-lmp-badge',
                getActive: function() { return priceGtLmpFilterActive; },
                onToggle: function(on) {
                    priceGtLmpFilterActive = on;
                    if (on) blueTriangleFilterActive = false;
                    applyDepopFilters();
                }
            });
        }
        if (window.PriceLt80LmpBadge) {
            PriceLt80LmpBadge.bind({
                badge: '#depop-price-lt80-lmp-badge',
                getActive: function() { return priceLt80LmpFilterActive; },
                onToggle: function(on) {
                    priceLt80LmpFilterActive = on;
                    if (on) blueTriangleFilterActive = false;
                    applyDepopFilters();
                }
            });
        }
        $('#depop-blue-triangle-badge').on('click', function() {
            blueTriangleFilterActive = !blueTriangleFilterActive;
            if (blueTriangleFilterActive) {
                priceGtLmpFilterActive = false;
                priceLt80LmpFilterActive = false;
            }
            applyDepopFilters();
            syncDpTriangleBadgeState();
        });

        $(document).on('click', '.sprice-mode-item', function(e) {
            e.preventDefault();
            enterPriceMode($(this).data('mode') || '');
        });
        $('#discount-type-select').on('change', syncDiscountInputUi);
        $('#apply-discount-btn').on('click', applyDiscount);
        $('#discount-percentage-input').on('keypress', function(e) { if (e.which === 13) applyDiscount(); });
        $('#clear-sprice-btn').on('click', clearSpriceForSelected);

        $(document).on('change', '#depop-select-all', function() {
            const checked = $(this).prop('checked');
            table.getRows('active').forEach(r => {
                const d = r.getData();
                if (!d.sku || dpIsParentRow(d)) return;
                if (checked) selectedSkus.add(String(d.sku));
                else selectedSkus.delete(String(d.sku));
            });
            $('.depop-sku-chk').each(function() {
                $(this).prop('checked', selectedSkus.has(String($(this).attr('data-sku'))));
            });
            updateSelectedCount();
        });
        $(document).on('change', '.depop-sku-chk', function() {
            const sku = String($(this).attr('data-sku'));
            if (!sku) return;
            if ($(this).prop('checked')) selectedSkus.add(sku);
            else selectedSkus.delete(sku);
            updateSelectedCount();
            updateSelectAllHeaderCheckbox();
        });

        $('#dp-apply-target-roi-btn').on('click', function() {
            const rawInput = $('#dp-target-roi-input').val();
            const targetRoiPct = parseFloat(String(rawInput).replace(',', '.'));
            if (rawInput === '' || !isFinite(targetRoiPct)) {
                showToast('Please enter a Target SGROI%', 'error');
                return;
            }
            if (selectedSkus.size === 0) {
                showToast('Select at least one SKU first', 'error');
                return;
            }
            const updates = [];
            let skippedNoLp = 0;
            selectedSkus.forEach(function(sku) {
                const rows = table.searchRows('sku', '=', sku);
                if (!rows.length) return;
                const row = rows[0];
                const d = row.getData();
                if (dpIsParentRow(d)) return;
                const lp = parseFloat(d.lp) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const newSprice = +((lp * (1 + targetRoiPct / 100)) / DP_MARGIN).toFixed(2);
                if (!(newSprice > 0)) return;
                const m = dpSpriceMetrics(d, newSprice);
                row.update({ sprice: newSprice, SPRICE: newSprice, sgpft: m.sgpft, SGPFT: m.sgpft, sroi: m.sroi, SROI: m.sroi });
                updates.push({ sku: sku, sprice: newSprice });
            });
            if (!updates.length) {
                showToast('No selected rows have LP > 0', 'error');
                return;
            }
            saveSpriceUpdates(updates);
            showToast('Target SGROI ' + targetRoiPct + '% applied to ' + updates.length + ' SKU(s)'
                + (skippedNoLp ? ' (' + skippedNoLp + ' skipped — no LP)' : ''), 'success');
        });
        $('#dp-apply-target-gpft-btn').on('click', function() {
            const rawInput = $('#dp-target-gpft-input').val();
            const targetGpftPct = parseFloat(String(rawInput).replace(',', '.'));
            if (rawInput === '' || !isFinite(targetGpftPct)) {
                showToast('Please enter a Target GPFT%', 'error');
                return;
            }
            const denom = DP_MARGIN - (targetGpftPct / 100);
            if (!(denom > 0)) {
                showToast('Target GPFT% must be less than the Depop margin', 'error');
                return;
            }
            if (selectedSkus.size === 0) {
                showToast('Select at least one SKU first', 'error');
                return;
            }
            const updates = [];
            selectedSkus.forEach(function(sku) {
                const rows = table.searchRows('sku', '=', sku);
                if (!rows.length) return;
                const row = rows[0];
                const d = row.getData();
                if (dpIsParentRow(d)) return;
                const lp = parseFloat(d.lp) || 0;
                if (lp <= 0) return;
                const newSprice = +(lp / denom).toFixed(2);
                if (!(newSprice > 0)) return;
                const m = dpSpriceMetrics(d, newSprice);
                row.update({ sprice: newSprice, SPRICE: newSprice, sgpft: m.sgpft, SGPFT: m.sgpft, sroi: m.sroi, SROI: m.sroi });
                updates.push({ sku: sku, sprice: newSprice });
            });
            if (!updates.length) {
                showToast('No selected rows have LP > 0', 'error');
                return;
            }
            saveSpriceUpdates(updates);
            showToast('Target GPFT ' + targetGpftPct + '% applied to ' + updates.length + ' SKU(s)', 'success');
        });
        $('#dp-target-roi-input').on('keypress', function(e) { if (e.which === 13) $('#dp-apply-target-roi-btn').click(); });
        $('#dp-target-gpft-input').on('keypress', function(e) { if (e.which === 13) $('#dp-apply-target-gpft-btn').click(); });

        function startDpPlay() {
            dpUniqueParents = buildDpUniqueParents();
            if (!dpUniqueParents.length) return;
            isDpPlayActive = true;
            currentDpParentIndex = 0;
            $('#play-auto').hide();
            $('#play-pause').show();
            applyDepopFilters();
            try { table.setPage(1); } catch (e) {}
            updateDpPlayButtonStates();
        }
        function stopDpPlay() {
            isDpPlayActive = false;
            currentDpParentIndex = -1;
            $('#play-pause').hide();
            $('#play-auto').show();
            applyDepopFilters();
            updateDpPlayButtonStates();
        }
        $('#play-auto').on('click', startDpPlay);
        $('#play-pause').on('click', stopDpPlay);
        $('#play-forward').on('click', function() {
            if (!isDpPlayActive || currentDpParentIndex >= dpUniqueParents.length - 1) return;
            currentDpParentIndex++;
            applyDepopFilters();
            try { table.setPage(1); } catch (e) {}
            updateDpPlayButtonStates();
        });
        $('#play-backward').on('click', function() {
            if (!isDpPlayActive || currentDpParentIndex <= 0) return;
            currentDpParentIndex--;
            applyDepopFilters();
            try { table.setPage(1); } catch (e) {}
            updateDpPlayButtonStates();
        });

        $('#import-form').on('submit', function(e) {
            e.preventDefault();
            const fileInput = $('#import-file')[0];
            if (!fileInput.files || !fileInput.files[0]) {
                showToast('Choose a CSV file first.', 'error');
                return;
            }
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            const $btn = $('#import-btn');
            const original = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            $.ajax({
                url: "{{ route('depop.pricing.import') }}",
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.success) {
                        showToast(res.message || 'Import complete', 'success');
                        $('#import-file').val('');
                        table.setData();
                    } else {
                        showToast(res.message || 'Import failed', 'error');
                    }
                },
                error: function(xhr) {
                    showToast((xhr.responseJSON && xhr.responseJSON.message) || 'Import failed', 'error');
                },
                complete: function() { $btn.prop('disabled', false).html(original); }
            });
        });
    });

    function applyDiscount() {
        const discountType = $('#discount-type-select').val();
        const discountValue = parseFloat($('#discount-percentage-input').val());
        if (!anyModeActive()) {
            showToast('Turn on Decrease, Increase, or Same Price mode first', 'error');
            return;
        }
        if (isNaN(discountValue) || discountValue <= 0) {
            showToast(samePriceModeActive ? 'Please enter a price (e.g. 19.99)' : 'Please enter a valid value', 'error');
            return;
        }
        if (selectedSkus.size === 0) {
            showToast('Please select at least one SKU', 'error');
            return;
        }
        const updates = [];
        table.getRows('active').forEach(function(row) {
            const d = row.getData();
            const sku = d && d.sku != null ? String(d.sku) : '';
            if (!sku || dpIsParentRow(d) || !selectedSkus.has(sku)) return;
            const currentPrice = parseFloat(d.price) || 0;
            if (!samePriceModeActive && currentPrice <= 0) return;
            let newSprice;
            if (samePriceModeActive) {
                newSprice = Math.max(0.99, discountValue);
            } else if (discountType === 'percentage') {
                newSprice = increaseModeActive
                    ? currentPrice * (1 + discountValue / 100)
                    : currentPrice * (1 - discountValue / 100);
            } else {
                newSprice = increaseModeActive
                    ? currentPrice + discountValue
                    : currentPrice - discountValue;
            }
            newSprice = Math.max(0.99, roundToRetailPrice(newSprice));
            const m = dpSpriceMetrics(d, newSprice);
            row.update({ sprice: newSprice, SPRICE: newSprice, sgpft: m.sgpft, SGPFT: m.sgpft, sroi: m.sroi, SROI: m.sroi });
            updates.push({ sku: sku, sprice: newSprice });
        });
        if (!updates.length) {
            showToast('No matching selected rows in the current view.', 'error');
            return;
        }
        saveSpriceUpdates(updates);
        const action = samePriceModeActive ? 'Same Price' : (increaseModeActive ? 'Increase' : 'Decrease');
        showToast(action + ' applied to ' + updates.length + ' SKU(s)', 'success');
        $('#discount-percentage-input').val('');
    }

    function clearSpriceForSelected() {
        if (selectedSkus.size === 0) {
            showToast('Select SKUs first', 'error');
            return;
        }
        if (!confirm('Clear SPRICE for ' + selectedSkus.size + ' SKU(s)?')) return;
        const updates = [];
        table.getRows('active').forEach(function(row) {
            const d = row.getData();
            const sku = d && d.sku != null ? String(d.sku) : '';
            if (!sku || !selectedSkus.has(sku)) return;
            row.update({ sprice: null, SPRICE: null, sgpft: 0, SGPFT: 0, sroi: 0, SROI: 0 });
            updates.push({ sku: sku, sprice: null });
        });
        if (!updates.length) return;
        saveSpriceUpdates(updates);
        showToast('Cleared SPRICE for ' + updates.length + ' SKU(s)', 'success');
    }

    function saveSpriceUpdates(updates, opts) {
        opts = opts || {};
        if (typeof chPromoBatchClearThenSave === 'function' && opts.clearFirst !== false) {
            chPromoBatchClearThenSave(updates, function(next) {
                saveSpriceUpdates(next, Object.assign({}, opts, { clearFirst: false }));
            }, {
                wipeFn: function(zeros) {
                    return $.ajax({
                        url: "{{ route('depop.pricing.save.sprice') }}",
                        method: 'POST',
                        data: { _token: '{{ csrf_token() }}', updates: zeros }
                    });
                }
            });
            return;
        }
        $.ajax({
            url: "{{ route('depop.pricing.save.sprice') }}",
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', updates: updates },
            success: function(res) {
                if (!res || res.success !== true) {
                    showToast((res && res.message) || 'Failed to save SPRICE', 'error');
                }
            },
            error: function(xhr) {
                showToast((xhr.responseJSON && xhr.responseJSON.message) || 'Failed to save SPRICE', 'error');
            }
        });
    }

    let dpOpModalRow = null;
    let dpOpModalCalc = { lp: 0, margin: DP_MARGIN, ads: 0 };

    function dpPaintOpMetric(sel, value, field) {
        const el = document.querySelector(sel);
        if (!el) return;
        el.style.backgroundColor = '';
        el.style.color = '';
        el.style.padding = '0';
        if (value == null || !isFinite(value)) {
            el.innerHTML = '<span style="color:#6c757d;font-weight:700;">-</span>';
            return;
        }
        const label = Math.round(value) + '%';
        let bg = '';
        let fg = '#212529';
        if (window.MetricPctColors) {
            const kind = MetricPctColors.kindFromField(field);
            const band = MetricPctColors.bandFor(kind, value);
            bg = MetricPctColors.colorFor(kind, value) || '';
            if (bg) fg = (band === 'yellow') ? '#000' : '#fff';
        } else {
            const isPft = field === 'SGPFT' || field === 'SPFT';
            bg = isPft
                ? (value < 0 ? '#dc3545' : (value < 10 ? '#ffc107' : '#28a745'))
                : (value < 0 ? '#dc3545' : (value < 40 ? '#ffc107' : '#28a745'));
            fg = (bg === '#ffc107') ? '#000' : '#fff';
        }
        el.innerHTML = '<span style="display:block;padding:6px 8px;font-weight:700;background:'
            + bg + ';color:' + fg + ';">' + label + '</span>';
    }

    function dpOpSpriceMetrics(opSprice, lp, margin, adsPct) {
        opSprice = parseFloat(opSprice) || 0;
        lp = parseFloat(lp) || 0;
        margin = parseFloat(margin) || 0;
        adsPct = parseFloat(adsPct) || 0;
        if (opSprice <= 0) {
            return { sgpft: null, sgroi: null, spft: null, snroi: null };
        }
        const sgpft = ((opSprice * margin - lp) / opSprice) * 100;
        const sgroi = lp > 0 ? ((opSprice * margin - lp) / lp) * 100 : 0;
        const spft = sgpft - adsPct;
        const snroi = lp > 0
            ? ((opSprice * margin - lp - opSprice * (adsPct / 100)) / lp) * 100
            : 0;
        return { sgpft: sgpft, sgroi: sgroi, spft: spft, snroi: snroi };
    }

    function dpRefreshOpModalMetrics() {
        const modal = document.getElementById('dpOpSpriceModal');
        if (!modal) return;
        const c = dpOpModalCalc || {};
        const metrics = dpOpSpriceMetrics(
            parseFloat((document.getElementById('dpOpSpriceInput') || {}).value) || 0,
            c.lp != null ? c.lp : modal.getAttribute('data-lp'),
            c.margin != null ? c.margin : modal.getAttribute('data-margin'),
            c.ads != null ? c.ads : modal.getAttribute('data-ads')
        );
        dpPaintOpMetric('#dpOpSgpft', metrics.sgpft, 'SGPFT');
        dpPaintOpMetric('#dpOpSgroi', metrics.sgroi, 'SGROI');
        dpPaintOpMetric('#dpOpSpft', metrics.spft, 'SPFT');
        dpPaintOpMetric('#dpOpSnroi', metrics.snroi, 'SNROI');
    }

    function openDpOpSpriceModal(row) {
        if (!row) return;
        dpOpModalRow = row;
        const d = row.getData() || {};
        if (dpIsParentRow(d)) return;
        const sku = d.sku || '';
        const lp = parseFloat(d.lp != null ? d.lp : d.LP_productmaster) || 0;
        const margin = (typeof chPromoTakehomeMargin === 'function')
            ? chPromoTakehomeMargin(d)
            : (parseFloat(d._margin) || DP_MARGIN);
        const ads = (typeof chPromoAdsFrac === 'function' ? ((chPromoAdsFrac() || 0) * 100) : 0) || 0;
        dpOpModalCalc = { lp: lp, margin: margin, ads: ads };
        const sVal = dpRowSprice(d);
        const modalEl = document.getElementById('dpOpSpriceModal');
        const skuEl = document.getElementById('dpOpModalSku');
        const input = document.getElementById('dpOpSpriceInput');
        if (!modalEl || !input) return;
        if (skuEl) skuEl.textContent = sku || '—';
        const imgWrap = document.getElementById('dpOpModalImgWrap');
        const imgEl = document.getElementById('dpOpModalImg');
        const imgSrc = String(d.image_path || d.image || '').trim();
        if (imgWrap && imgEl) {
            if (imgSrc) {
                imgEl.src = imgSrc;
                imgWrap.style.display = '';
            } else {
                imgEl.removeAttribute('src');
                imgWrap.style.display = 'none';
            }
        }
        modalEl.setAttribute('data-sku', sku);
        modalEl.setAttribute('data-lp', String(lp));
        modalEl.setAttribute('data-margin', String(margin));
        modalEl.setAttribute('data-ads', String(ads));
        input.value = (isFinite(sVal) && sVal > 0) ? Number(sVal).toFixed(2) : '';
        dpRefreshOpModalMetrics();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    (function initDpOpSpriceModal() {
        const modal = document.getElementById('dpOpSpriceModal');
        const input = document.getElementById('dpOpSpriceInput');
        const sopBtn = document.getElementById('dpSopBtn');
        const sopEditBtn = document.getElementById('dpSopEditBtn');
        const sopSheetInput = document.getElementById('dpSopSheetInput');
        if (!modal || !input) return;

        function dpSopSheetUrl() {
            return String((sopSheetInput && sopSheetInput.value) || '').trim();
        }
        function dpOpenSopSheetEditor() {
            if (!sopSheetInput) return;
            sopSheetInput.classList.add('is-open');
            sopSheetInput.focus();
            sopSheetInput.select();
        }
        function dpSaveSopSheetUrl() {
            if (!sopSheetInput) return;
            const url = dpSopSheetUrl();
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch("{{ route('depop.pricing.sop-sheet') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({ url: url })
            }).then(function(res) {
                if (!res.ok) throw new Error('save failed');
                sopSheetInput.classList.remove('is-open');
                if (typeof showToast === 'function') {
                    showToast(url ? 'SOP sheet link saved' : 'SOP sheet link cleared', 'success');
                }
            }).catch(function() {
                if (typeof showToast === 'function') showToast('Failed to save SOP sheet link', 'error');
            });
        }

        input.addEventListener('input', dpRefreshOpModalMetrics);
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                dpRefreshOpModalMetrics();
            }
        });
        if (sopBtn) {
            sopBtn.addEventListener('click', function(e) { e.preventDefault(); });
            sopBtn.addEventListener('dblclick', function(e) {
                e.preventDefault();
                const url = dpSopSheetUrl();
                if (!url) {
                    dpOpenSopSheetEditor();
                    return;
                }
                window.open(url, '_blank', 'noopener,noreferrer');
            });
        }
        if (sopEditBtn) {
            sopEditBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (sopSheetInput && sopSheetInput.classList.contains('is-open')) {
                    dpSaveSopSheetUrl();
                } else {
                    dpOpenSopSheetEditor();
                }
            });
        }
        if (sopSheetInput) {
            sopSheetInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    dpSaveSopSheetUrl();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    sopSheetInput.classList.remove('is-open');
                }
            });
            sopSheetInput.addEventListener('blur', function() {
                if (sopSheetInput.classList.contains('is-open')) {
                    dpSaveSopSheetUrl();
                }
            });
        }

        modal.addEventListener('shown.bs.modal', function() {
            const dialog = modal.querySelector('.modal-dialog');
            if (dialog) {
                dialog.style.position = 'fixed';
                dialog.style.left = '50%';
                dialog.style.top = '1.5rem';
                dialog.style.transform = 'translateX(-50%)';
                dialog.style.margin = '0';
            }
            dpRefreshOpModalMetrics();
            input.focus();
            input.select();
        });

        let startX = 0, startY = 0, startLeft = 0, startTop = 0;
        const header = modal.querySelector('.dp-op-drag-header');
        const dialog = modal.querySelector('.modal-dialog');
        if (!header || !dialog) return;
        function onMove(e) {
            dialog.style.left = (startLeft + (e.clientX - startX)) + 'px';
            dialog.style.top = (startTop + (e.clientY - startY)) + 'px';
            dialog.style.transform = 'none';
        }
        function onUp() {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
        }
        function dpOpImgPreview() {
            let el = document.getElementById('dpOpImgHoverPreview');
            if (el) return el;
            el = document.createElement('img');
            el.id = 'dpOpImgHoverPreview';
            el.alt = '';
            document.body.appendChild(el);
            return el;
        }
        function dpOpPlacePreview(e) {
            const preview = dpOpImgPreview();
            const pad = 16;
            const w = preview.offsetWidth || 320;
            const h = preview.offsetHeight || 320;
            let left = e.clientX + pad;
            let top = e.clientY + pad;
            if (left + w > window.innerWidth - 8) left = Math.max(8, e.clientX - w - pad);
            if (top + h > window.innerHeight - 8) top = Math.max(8, window.innerHeight - h - 8);
            preview.style.left = left + 'px';
            preview.style.top = top + 'px';
        }
        const imgWrap = document.getElementById('dpOpModalImgWrap');
        if (imgWrap) {
            imgWrap.addEventListener('mouseenter', function(e) {
                const img = document.getElementById('dpOpModalImg');
                const src = img && (img.currentSrc || img.src);
                if (!src) return;
                const preview = dpOpImgPreview();
                preview.src = src;
                preview.style.display = 'block';
                dpOpPlacePreview(e);
            });
            imgWrap.addEventListener('mousemove', dpOpPlacePreview);
            imgWrap.addEventListener('mouseleave', function() {
                const preview = document.getElementById('dpOpImgHoverPreview');
                if (preview) preview.style.display = 'none';
            });
        }
        modal.addEventListener('hidden.bs.modal', function() {
            const preview = document.getElementById('dpOpImgHoverPreview');
            if (preview) preview.style.display = 'none';
        });
        header.addEventListener('mousedown', function(e) {
            if (e.target.closest('.btn-close, .dp-op-header-img-wrap')) return;
            const r = dialog.getBoundingClientRect();
            startLeft = r.left;
            startTop = r.top;
            startX = e.clientX;
            startY = e.clientY;
            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
            e.preventDefault();
        });
    })();
</script>
@endsection
