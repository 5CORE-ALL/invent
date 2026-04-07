@extends('layouts.vertical', ['title' => 'MIP', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    /* Image column: compact thumb */
    .table-container .wide-table th[data-column="1"],
    .table-container .wide-table td[data-column="1"] {
        width: 52px !important;
        min-width: 52px !important;
        max-width: 52px !important;
        white-space: normal !important;
    }
    .table-container .wide-table th[data-column="1"] {
        padding: 2px !important;
        vertical-align: middle;
        white-space: normal !important;
        line-height: 1.15;
        font-size: 0.65rem;
        overflow: hidden !important;
    }
    .table-container .wide-table td[data-column="1"] {
        padding: 0 !important;
        vertical-align: middle;
        line-height: 0;
    }
    .table-container .wide-table td[data-column="1"] > .mip-image-aspect {
        width: 46px !important;
        height: 46px !important;
        max-width: 46px !important;
        max-height: 46px !important;
        margin: 0 auto;
        box-sizing: border-box;
        flex-shrink: 0;
        overflow: hidden !important;
        position: relative;
    }
    .table-container .wide-table td[data-column="1"] .mip-image-aspect img {
        width: 100% !important;
        height: 100% !important;
        max-width: 46px !important;
        max-height: 46px !important;
        object-fit: contain;
        display: block;
        border-radius: 0;
        box-shadow: none;
        background: transparent;
        vertical-align: top;
    }
    /* Archived MIP Tabulator: image column */
    #mip-archived-history-table .tabulator-cell.mip-archived-image-cell {
        padding: 0 !important;
        vertical-align: middle !important;
        line-height: 0;
    }
    #mip-archived-history-table .mip-archived-img-aspect {
        width: 46px !important;
        height: 46px !important;
        max-width: 46px !important;
        max-height: 46px !important;
        margin: 0 auto;
        box-sizing: border-box;
    }
    #mip-archived-history-table .mip-archived-img-aspect img {
        width: 100% !important;
        height: 100% !important;
        max-width: 100% !important;
        max-height: 100% !important;
        object-fit: contain;
        display: block;
    }
    .preview-popup {
        position: fixed;
        display: none;
        z-index: 9999;
        pointer-events: none;
        width: min(480px, 92vw);
        height: min(480px, 85vh);
        object-fit: contain;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
        transition: opacity 0.15s ease;
    }
    /* Keep stage/popovers usable, but do not override image column clipping (global td broke thumb bounds). */
    .wide-table td {
        overflow: visible !important;
    }
    .table-container .wide-table thead th {
        writing-mode: horizontal-tb !important;
        text-orientation: mixed !important;
    }
    .table-container .wide-table td[data-column="1"] {
        overflow: hidden !important;
    }
    .wide-table .column-search {
        text-align: center;
    }

    /* Stage column: same pattern as Forecast Analysis (colored dot + invisible select overlay) */
    .wide-table .stage-dot-cell.mip-stage-dot {
        min-height: 36px;
        min-width: 44px;
        max-width: 56px;
    }
    .wide-table .stage-dot-cell .stage-status-dot {
        display: inline-block;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        flex-shrink: 0;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.12);
    }
    .wide-table .stage-dot-cell .stage-stage-select {
        opacity: 0;
        cursor: pointer;
        margin: 0 !important;
        border: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        -webkit-appearance: none;
        appearance: none;
        z-index: 2;
    }
    .wide-table .stage-dot-cell .stage-transit-icon {
        font-size: 1.05rem;
        line-height: 1;
        color: #334155;
    }
    .wide-table th[data-column="17"] {
        max-width: 64px;
    }

    .wide-table .mip-sku-cell {
        gap: 4px;
        max-width: 100%;
    }
    .wide-table .mip-sku-cell .mip-sku-text {
        min-width: 0;
        word-break: break-word;
        text-align: left;
    }
    .wide-table .mip-copy-sku {
        flex-shrink: 0;
        line-height: 1;
        color: #3bc0c3;
        opacity: 0.85;
    }
    .wide-table .mip-copy-sku:hover {
        opacity: 1;
        color: #2a9a9d;
    }

    /* Toolbar: uniform height, alignment, equal spread */
    #columnControls .toolbar-row {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: space-between;
        gap: 0;
        width: 100%;
        min-height: 44px;
    }
    #columnControls .toolbar-item {
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 1;
        min-width: 0;
    }
    #columnControls .toolbar-item.controls-group {
        flex: 0 0 auto;
        justify-content: flex-start;
        gap: 12px;
    }
    #columnControls .toolbar-item.stats-group {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0;
        min-width: 0;
        padding-left: 24px;
    }
    #columnControls .toolbar-item .btn,
    #columnControls .toolbar-item .custom-select-box {
        height: 38px !important;
        min-height: 38px;
        display: inline-flex;
        align-items: center;
    }
    #columnControls .toolbar-item .btn.rounded-circle {
        width: 38px;
        height: 38px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    #columnControls .mip-toolbar-search-input {
        width: 150px;
        min-width: 120px;
        height: 38px;
        font-size: 13px;
    }
    #columnControls #play-auto i.fa-play {
        margin-left: -1px; /* Center the play triangle - FA icon has slight right bias */
    }
    #columnControls .stat-panel {
        flex: 1;
        min-width: 60px;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    /* ── Fit MIP into one screen: flex column + scroll only the grid ── */
    .mip-minh-0 {
        min-height: 0 !important;
    }
    #mip-page-root.mip-viewport-fit {
        display: flex;
        flex-direction: column;
        max-height: calc(100dvh - 128px);
        min-height: 0;
        /* Page-scale zoom scoped here (was body zoom — narrower reflow / less jank than document.body.style.zoom) */
        zoom: 0.95;
    }
    #mip-page-root.mip-viewport-fit .page-title-box {
        flex-shrink: 0;
        margin-bottom: 0.35rem !important;
        padding-bottom: 0 !important;
    }
    #mip-page-root.mip-viewport-fit .page-title-box .page-title {
        font-size: 1.2rem;
    }
    #mip-page-root.mip-viewport-fit > .row:first-child {
        flex-shrink: 0;
    }
    #mip-page-root.mip-viewport-fit > .row.mip-main-outer {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
    }
    #mip-page-root.mip-viewport-fit > .row.mip-main-outer > [class*="col-"] {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }
    #mip-page-root.mip-viewport-fit .mip-main-col {
        min-height: 0;
    }
    #mip-page-root.mip-viewport-fit .mip-main-card {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
    }
    #mip-page-root.mip-viewport-fit .mip-card-body {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        padding: 0.45rem 0.6rem !important;
    }
    #mip-page-root.mip-viewport-fit #columnControls {
        flex-shrink: 0;
        margin-bottom: 0.45rem !important;
        padding: 0.45rem 0.6rem !important;
    }
    #mip-page-root.mip-viewport-fit #columnControls .toolbar-row {
        min-height: 40px;
    }
    #mip-page-root.mip-viewport-fit #columnControls .toolbar-item .btn,
    #mip-page-root.mip-viewport-fit #columnControls .toolbar-item .custom-select-box {
        height: 38px !important;
        min-height: 38px;
    }
    #mip-page-root.mip-viewport-fit #columnControls .toolbar-item .btn.rounded-circle {
        width: 38px;
        height: 38px;
    }
    #mip-page-root.mip-viewport-fit #columnControls .mip-toolbar-search-input {
        height: 38px;
        width: 140px;
        min-width: 110px;
        font-size: 13px;
    }
    #mip-page-root.mip-viewport-fit #columnControls .stat-panel .text-muted {
        font-size: 0.78rem !important;
    }
    #mip-page-root.mip-viewport-fit #columnControls .stat-panel .fw-bold {
        font-size: 1.05rem !important;
    }
    #mip-page-root.mip-viewport-fit .mip-table-scroll {
        flex: 1 1 auto;
        min-height: 100px;
        max-height: none !important;
        overflow: auto !important;
    }
    #mip-page-root.mip-viewport-fit .wide-table th,
    #mip-page-root.mip-viewport-fit .wide-table td {
        padding: 4px 6px !important;
        font-size: 0.8125rem !important;
    }
    #mip-page-root.mip-viewport-fit .wide-table td[data-column="1"] {
        padding: 0 !important;
    }
    #mip-page-root.mip-viewport-fit .wide-table th[data-column="1"] {
        padding: 2px !important;
        font-size: 0.65rem !important;
        line-height: 1.15;
        white-space: normal !important;
        writing-mode: horizontal-tb !important;
        text-orientation: mixed !important;
        overflow: hidden !important;
    }
    #mip-page-root.mip-viewport-fit .wide-table tbody tr:hover {
        transform: none !important;
        box-shadow: none !important;
    }

    /* Optional compact layout (localStorage mipDenseLayout=1) — previous one-screen density */
    #mip-page-root.mip-viewport-fit.mip-dense-layout {
        zoom: 0.85;
    }
    #mip-page-root.mip-viewport-fit.mip-dense-layout .page-title-box .page-title {
        font-size: 1.1rem;
    }
    #mip-page-root.mip-viewport-fit.mip-dense-layout .mip-card-body {
        padding: 0.35rem 0.45rem !important;
    }
    #mip-page-root.mip-viewport-fit.mip-dense-layout #columnControls {
        margin-bottom: 0.35rem !important;
        padding: 0.35rem 0.45rem !important;
    }
    #mip-page-root.mip-viewport-fit.mip-dense-layout #columnControls .toolbar-row {
        min-height: 36px;
    }
    #mip-page-root.mip-viewport-fit.mip-dense-layout #columnControls .toolbar-item .btn,
    #mip-page-root.mip-viewport-fit.mip-dense-layout #columnControls .toolbar-item .custom-select-box {
        height: 34px !important;
        min-height: 34px;
    }
    #mip-page-root.mip-viewport-fit.mip-dense-layout #columnControls .toolbar-item .btn.rounded-circle {
        width: 34px;
        height: 34px;
    }
    #mip-page-root.mip-viewport-fit.mip-dense-layout #columnControls .mip-toolbar-search-input {
        height: 34px;
        width: 128px;
        min-width: 100px;
        font-size: 12px;
    }
    #mip-page-root.mip-viewport-fit.mip-dense-layout #columnControls .stat-panel .text-muted {
        font-size: 0.7rem !important;
    }
    #mip-page-root.mip-viewport-fit.mip-dense-layout #columnControls .stat-panel .fw-bold {
        font-size: 0.95rem !important;
    }
    #mip-page-root.mip-viewport-fit.mip-dense-layout .wide-table th,
    #mip-page-root.mip-viewport-fit.mip-dense-layout .wide-table td {
        padding: 2px 4px !important;
        font-size: 0.75rem !important;
    }
    #mip-page-root.mip-viewport-fit.mip-dense-layout .wide-table td[data-column="1"] {
        padding: 0 !important;
    }
    #mip-page-root.mip-viewport-fit.mip-dense-layout .wide-table th[data-column="1"] {
        padding: 2px !important;
        font-size: 0.6rem !important;
        white-space: normal !important;
        writing-mode: horizontal-tb !important;
        text-orientation: mixed !important;
        overflow: hidden !important;
    }
    
</style>
@endsection
@section('content')
<div id="mip-page-root" class="mip-viewport-fit">
@include('layouts.shared.page-title', ['page_title' => 'MIP', 'sub_title' => 'MIP'])
<div class="row mip-main-outer g-0">
    <div class="col-12 mip-main-col d-flex flex-column mip-minh-0">
        <div class="card shadow-sm mip-main-card mb-0 d-flex flex-column mip-minh-0">
            <div class="card-body mip-card-body d-flex flex-column mip-minh-0">
                <!-- Filters Row - First Row -->
                <div class="column-controls card mb-2 p-2 shadow-sm" id="columnControls" style="background: #f8f9fa; border-radius: 8px;">
                    <div class="toolbar-row" style="overflow-x: auto;">
                        <!-- Controls Group -->
                        <div class="toolbar-item controls-group d-flex flex-nowrap">
                            <div class="btn-group time-navigation-group d-flex align-items-center gap-1" role="group">
                                <button id="play-backward" class="btn btn-light rounded-circle shadow-sm" title="Previous parent">
                                    <i class="fas fa-step-backward"></i>
                                </button>
                                <button id="play-pause" class="btn btn-light rounded-circle shadow-sm" style="display: none;" title="Pause">
                                    <i class="fas fa-pause"></i>
                                </button>
                                <button id="play-auto" class="btn btn-primary rounded-circle shadow-sm" title="Play">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button id="play-forward" class="btn btn-light rounded-circle shadow-sm" title="Next parent">
                                    <i class="fas fa-step-forward"></i>
                                </button>
                                <button id="supplier-remarks-btn" class="btn btn-success shadow-sm" style="border-radius: 6px;" title="Follow-Up History">
                                    <i class="fas fa-comment-alt"></i> Follow-Up History
                                </button>
                            </div>
                            <button type="button" class="btn btn-warning d-flex align-items-center gap-1 text-dark" id="archiveSelectedBtn" style="border-radius: 6px; display: none;" title="Archive selected rows — restore from History">
                                <i class="fas fa-archive"></i> Archive
                            </button>
                            <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-1 shadow-sm" id="mipArchivedHistoryBtn" style="border-radius: 6px;" title="View archived MIP rows">
                                <i class="fas fa-history"></i> History
                                <span class="badge rounded-pill bg-secondary" id="mipArchivedCountBadge">0</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-1 shadow-sm" id="mipToggleDenseLayout" style="border-radius: 6px;" aria-pressed="false" title="Compact layout — more rows on screen">
                                <i class="fas fa-text-height" aria-hidden="true"></i>
                                <span class="mip-density-label text-nowrap">Dense view</span>
                            </button>
                            <div class="d-flex align-items-center flex-nowrap gap-2 mip-toolbar-bulk-stage border-start ps-3 ms-1" title="Apply stage to rows checked in the first column">
                                <label for="mip-bulk-stage-select" class="mb-0 small fw-semibold text-nowrap text-secondary">Bulk Stage</label>
                                <select id="mip-bulk-stage-select" class="form-select form-select-sm" style="width: 128px; min-width: 110px;">
                                    <option value="">— Choose —</option>
                                    <option value="appr_req">Appr. Req</option>
                                    <option value="mip">MIP</option>
                                    <option value="r2s">R2S</option>
                                    <option value="transit">Transit</option>
                                    <option value="all_good">😊 All Good</option>
                                    <option value="to_order_analysis">2 Order</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-primary" id="mip-bulk-stage-apply">Apply</button>
                            </div>
                            <div class="d-flex align-items-center flex-nowrap gap-2 mip-toolbar-column-filters" role="search" aria-label="Filter table">
                                <input type="text" class="form-control form-control-sm column-search mip-toolbar-search-input" data-search-column="3" placeholder="Search SKU..." autocomplete="off" aria-label="Filter by SKU">
                                <input type="text" class="form-control form-control-sm column-search mip-toolbar-search-input" data-search-column="6" placeholder="Search Supplier..." autocomplete="off" aria-label="Filter by supplier">
                            </div>
                        </div>

                        <!-- Stats Section - Equal width, spread from left to right -->
                        <div class="toolbar-item stats-group">
                            <div class="stat-panel">
                                <div class="text-muted" style="font-size: 0.975rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">👥 Suppliers</div>
                                <div id="followSupplierCount" class="fw-bold" style="font-size: 1.15rem; line-height: 1.2; color: #000;">0</div>
                            </div>
                            <div class="stat-panel">
                                <div class="text-muted" style="font-size: 0.975rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">💰 Amount</div>
                                <div id="total-amount" class="fw-bold" style="font-size: 1.15rem; line-height: 1.2; color: #000;">$0</div>
                            </div>
                            <div class="stat-panel">
                                <div class="text-muted" style="font-size: 0.975rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">📊 CBM</div>
                                <div id="total-cbm" class="fw-bold" style="font-size: 1.15rem; line-height: 1.2; color: #000;">0</div>
                            </div>
                            <div class="stat-panel">
                                <div class="text-muted" style="font-size: 0.975rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">🔢 Items</div>
                                <div id="total-order-items" class="fw-bold" style="font-size: 1.15rem; line-height: 1.2; color: #000;">0</div>
                            </div>
                            <div class="flex-shrink-0" style="display: none; width: 1px; height: 32px; background: #dee2e6; margin: 0 8px;" id="supplier-badge-vr"></div>
                            <div class="stat-panel" style="display: none;" id="supplier-badge-container">
                                <div class="text-muted" style="font-size: 0.975rem; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase;">🏭 Current Supplier</div>
                                <div id="current-supplier" class="fw-bold text-white" style="font-size: 1rem; line-height: 1.2; background-color: #28a745; padding: 2px 8px; border-radius: 4px;">-</div>
                            </div>
                            <button type="button" id="mip-summary-export-btn" class="btn btn-link p-0 border-0 bg-transparent flex-shrink-0" title="Export summary to Excel" style="margin-left: 12px;">
                                <img src="{{ asset('assets/images/summary-icon.png') }}" alt="Summary" style="width: 36px; height: 36px; object-fit: contain; cursor: pointer;">
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Supplier Remarks Modal -->
                <div class="modal fade" id="supplierRemarksModal" tabindex="-1" aria-labelledby="supplierRemarksModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title" id="supplierRemarksModalLabel">
                                    <i class="fas fa-comment-alt"></i> Follow-Up History
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Current Supplier:</label>
                                    <div id="modal-supplier-name" class="badge bg-success fs-6 p-2" style="font-size: 1rem !important;">
                                        -
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="supplier-remark-input" class="form-label fw-bold">Add New Remark/Update:</label>
                                    <textarea class="form-control" id="supplier-remark-input" rows="3" placeholder="Enter your remark or update here..."></textarea>
                                </div>
                                <div class="mb-3 text-end">
                                    <button type="button" class="btn btn-success" id="save-remark-btn">
                                        <i class="fas fa-save"></i> Save Remark
                                    </button>
                                </div>
                                <hr>
                                <div>
                                    <label class="form-label fw-bold mb-2">Saved Remarks/Updates:</label>
                                    <div id="remarks-list" style="max-height: 400px; overflow-y: auto;">
                                        <p class="text-muted">No remarks saved yet.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Archived MIP history (separate datatable) -->
                <div class="modal fade" id="mipArchivedHistoryModal" tabindex="-1" aria-labelledby="mipArchivedHistoryModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header bg-secondary text-white">
                                <h5 class="modal-title" id="mipArchivedHistoryModalLabel">
                                    <i class="fas fa-archive me-2"></i>Archived MIP — History
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted small mb-2">Select rows and click <strong>Restore</strong> to return them to the main MIP list.</p>
                                <div id="mip-archived-history-table" class="mb-3"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-success" id="mipHistoryRestoreBtn" disabled>
                                    <i class="fas fa-undo me-1"></i> Restore selected
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                            
                <div class="wide-table-wrapper table-container mip-table-scroll">
                    <table class="wide-table">
                        <thead>
                            <tr>
                                <th data-column="0" style="width: 50px;">
                                    <input type="checkbox" id="selectAllCheckbox" title="Select All">
                                    <div class="resizer"></div>
                                </th>
                                <th data-column="1" class="p-0 m-0" style="width:52px;min-width:52px;max-width:52px;">Img</th>
                                <th data-column="2" hidden>
                                    Parent
                                    <div class="resizer"></div>
                                    <input type="text" class="form-control column-search" data-search-column="2" placeholder="Search Parent..." style="margin-top:4px; font-size:12px; height:28px;">
                                    <div class="search-results" data-results-column="2" style="position:relative; z-index:10;"></div>
                                </th>
                                <th data-column="3">
                                    SKU
                                    <div class="resizer"></div>
                                </th>
                                <th data-column="18" class="text-center" hidden>NRP<div class="resizer"></div></th>
                                <th data-column="4" class="text-center">QTY<div class="resizer"></div></th>
                                <th data-column="10" class="text-center">O<br/>Date<div class="resizer"></div></th>
                                <th data-column="11" class="text-center">D<br/>date<div class="resizer"></div></th>
                                <th data-column="5" hidden>Rate<div class="resizer"></div></th>
                                <th data-column="6" class="text-center" style="width: 112.5px; min-width: 112.5px; max-width: 112.5px;">Supplier<div class="resizer"></div></th>
                                <th data-column="7" hidden>Advance<br/>Amt<div class="resizer"></div></th>
                                <th data-column="8" hidden>Adv<br/>Date<div class="resizer"></div></th>
                                <th data-column="9" hidden>pay conf.<br/>date<div class="resizer"></div></th>
                                {{-- <th data-column="9">pay term<div class="resizer"></div></th> --}}
                                {{-- <th data-column="12">O Links<div class="resizer"></div></th> --}}
                                <th data-column="12" hidden>value<div class="resizer"></div></th>
                                <th data-column="13" hidden>Payment<br/>Pending<div class="resizer"></div></th>
                                {{-- <th data-column="15">photo<br/>packing<div class="resizer"></div></th> --}}
                                {{-- <th data-column="16">photo int.<br/>sale<div class="resizer"></div></th> --}}
                                <th data-column="14" hidden>CBM<div class="resizer"></div></th>
                                <th data-column="15">T-CBM<div class="resizer"></div></th>
                                <th data-column="23" class="text-center">Pkg Inst<div class="resizer"></div></th>
                                <th data-column="24" class="text-center">U-Manual<div class="resizer"></div></th>
                                <th data-column="25" class="text-center">Compliance<div class="resizer"></div></th>
                                <th data-column="20" class="text-center" hidden>CTN CBM E<div class="resizer"></div></th>
                                <th data-column="17" class="text-center">Stage<div class="resizer"></div></th>
                                {{-- <th data-column="19" class="text-center">BARCODE<br/>&<br/>SKU<div class="resizer"></div></th> --}}
                                {{-- <th data-column="20">artwork<br/>&<br/>maual<br/>book<div class="resizer"></div></th> --}}
                                {{-- <th data-column="21">notes<div class="resizer"></div></th> --}}
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data as $item)
                                @php
                                    $readyToShip = $item->ready_to_ship ?? '';
                                    $stageValue = $item->stage ?? '';
                                    $nrValue = strtoupper(trim($item->nr ?? ''));
                                @endphp
                                @continue($readyToShip === 'Yes')
                                @continue($nrValue === 'NR')
                                <tr data-stage="{{ $stageValue ?? '' }}" class="stage-row" data-sku="{{ $item->sku }}" data-parent="{{ e($item->parent ?? '') }}">
                                    <td data-column="0" class="text-center">
                                        <input type="checkbox" class="row-checkbox" data-sku="{{ $item->sku }}">
                                    </td>
                                    <td data-column="1" class="p-0 m-0">
                                        @if(!empty($item->Image))
                                            @php
                                                // Check if it's a storage path or full URL
                                                $imageUrl = $item->Image;
                                                if (strpos($imageUrl, 'storage/') === 0 || strpos($imageUrl, '/storage/') === 0) {
                                                    $imageUrl = asset($imageUrl);
                                                } elseif (strpos($imageUrl, 'http') !== 0 && strpos($imageUrl, '//') !== 0) {
                                                    // If it's a relative path, make it absolute
                                                    $imageUrl = asset($imageUrl);
                                                }
                                            @endphp
                                            <div class="w-100 h-100 p-0 m-0 mip-image-aspect">
                                                <img src="{{ $imageUrl }}"
                                                     class="w-100 h-100 hover-img"
                                                     data-src="{{ $imageUrl }}"
                                                     alt=""
                                                     loading="lazy"
                                                     decoding="async"
                                                     style="object-fit: contain; display: block;"
                                                     onerror="this.style.display='none'; var s=this.nextElementSibling; if(s) s.style.display='inline';">
                                                <span class="text-muted mip-img-fallback" style="display: none; line-height: normal;">No</span>
                                            </div>
                                        @else
                                            <span class="text-muted" style="line-height: normal;">No</span>
                                        @endif
                                    </td>
                                    <td data-column="2" class="text-center" hidden>
                                        {{ $item->parent ?? '' }}
                                    </td>
                                    <td data-column="3" class="text-center align-middle">
                                        <div class="d-inline-flex align-items-center justify-content-center mip-sku-cell">
                                            <span class="mip-sku-text">{{ $item->sku ?? '' }}</span>
                                            @if(!empty($item->sku))
                                                <button type="button"
                                                    class="btn btn-link mip-copy-sku p-0 border-0"
                                                    data-sku="{{ e($item->sku) }}"
                                                    title="Copy SKU"
                                                    aria-label="Copy SKU">
                                                    <i class="far fa-copy" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                    <td data-column="18" class="text-center" hidden>
                                        @php
                                            $nrValue = strtoupper(trim($item->nr ?? ''));
                                            $bgColor = '#ffffff';
                                            $textColor = '#000000';
                                            if (!$nrValue || $nrValue === '') {
                                                $nrValue = 'REQ';
                                            }
                                            // Normalize value to match expected values
                                            if ($nrValue !== 'REQ' && $nrValue !== 'NR' && $nrValue !== 'LATER') {
                                                $nrValue = 'REQ'; // Default to REQ if value doesn't match
                                            }
                                            if ($nrValue === 'NR') {
                                                $bgColor = '#dc3545';
                                                $textColor = '#ffffff';
                                            } else if ($nrValue === 'REQ') {
                                                $bgColor = '#28a745';
                                                $textColor = '#000000';
                                            } else if ($nrValue === 'LATER') {
                                                $bgColor = '#ffc107';
                                                $textColor = '#000000';
                                            }
                                        @endphp
                                        <select class="form-select form-select-sm editable-select-nrp" 
                                            data-type="NR"
                                            data-sku="{{ $item->sku }}"
                                            data-parent="{{ $item->parent ?? '' }}"
                                            style="width: auto; min-width: 85px; padding: 4px 8px;
                                                font-size: 0.875rem; border-radius: 4px; border: 1px solid #dee2e6;
                                                background-color: {{ $bgColor }}; color: {{ $textColor }};">
                                            <option value="REQ" {{ $nrValue === 'REQ' ? 'selected' : '' }}>REQ</option>
                                            <option value="NR" {{ $nrValue === 'NR' ? 'selected' : '' }}>2BDC</option>
                                            <option value="LATER" {{ $nrValue === 'LATER' ? 'selected' : '' }}>LATER</option>
                                        </select>
                                    </td>
                                    <td data-column="4" data-qty="{{ $item->qty ?? 0 }}" class="text-center" style="background-color: #e9ecef;">
                                        <input type="number" 
                                            value="{{ $item->qty ?? 0 }}" 
                                            data-sku="{{ $item->sku }}"
                                            data-column="qty"
                                            min="0"
                                            step="1"
                                            style="width:80px; text-align:center; background-color: #fff;"
                                            class="form-control form-control-sm auto-save">
                                    </td>
                                    @php
                                        $textColor = '';
                                        $daysDiff = null;
                                        $formattedDate = '';

                                        if (!empty($item->created_at)) {
                                            $date = \Carbon\Carbon::parse($item->created_at);
                                            $daysDiff = $date->diffInDays(\Carbon\Carbon::today());
                                            
                                            // Format date with 3-letter month in uppercase: 15 DEC (without year)
                                            $day = $date->format('d');
                                            $month = strtoupper($date->format('M')); // M gives 3-letter month like Jan, Feb - convert to uppercase
                                            $formattedDate = $day . ' ' . $month;

                                            if ($daysDiff > 25) {
                                                $textColor = 'color: red;';
                                            } elseif ($daysDiff >= 15 && $daysDiff <= 25) {
                                                $textColor = 'color: #ffc107;'; // Yellow text
                                            } else {
                                                $textColor = 'color: black;';
                                            }
                                        }
                                    @endphp
                                    <td data-column="10" class="text-center">
                                        <div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">
                                            <input type="date" data-sku="{{ $item->sku }}" data-column="created_at" value="{{ !empty($item->created_at) ? \Carbon\Carbon::parse($item->created_at)->format('Y-m-d') : '' }}" 
                                            class="form-control form-control-sm auto-save d-none" style="width: 80px; font-size: 13px; {{ $textColor }}">
                                            @if ($daysDiff !== null && !empty($formattedDate))
                                                <span style="font-size: 11px; {{ $textColor }}; white-space: nowrap; font-weight: 500;">
                                                    {{ $formattedDate }} ({{ $daysDiff }} D)
                                                </span>
                                            @else
                                                <span class="text-muted" style="font-size: 11px;">-</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td data-column="11" class="text-center">
                                        <input type="date"
                                            value="{{ !empty($item->delivery_date) ? \Carbon\Carbon::parse($item->delivery_date)->format('Y-m-d') : '' }}"
                                            data-sku="{{ $item->sku }}"
                                            data-column="delivery_date"
                                            class="form-control form-control-sm auto-save"
                                            style="width: 80px; font-size: 13px;">
                                    </td>
                                    <td data-column="5" hidden>
                                        <div class="input-group input-group-sm" style="width:105px;">
                                            <span class="input-group-text" style="padding: 0 6px; background: #e9ecef;">
                                                <span style="font-size: 13px; color: #6c757d;">
                                                    {{ ($item->currency_from_po ?? $item->rate_currency ?? 'USD') == 'USD' ? '$' : '¥' }}
                                                </span>
                                            </span>
                                            <input data-sku="{{ $item->sku }}" data-column="rate" type="text" 
                                                value="{{ $item->price_from_po ?? $item->rate ?? '' }}" 
                                                class="form-control form-control-sm" 
                                                style="background: #f9f9f9; font-size: 13px; cursor: not-allowed;" 
                                                readonly />
                                        </div>
                                    </td>

                                    <td data-column="6" class="text-center" style="width: 112.5px; min-width: 112.5px; max-width: 112.5px;">
                                        <select data-sku="{{ $item->sku }}" data-column="supplier" class="form-select form-select-sm auto-save" style="min-width: 105px; max-width: 105px; font-size: 12px;">
                                            <option value="">supplier</option>
                                            @foreach ($suppliers as $supplierName)
                                                <option value="{{ $supplierName }}" {{ ($item->supplier ?? '') == $supplierName ? 'selected' : '' }}>
                                                    {{ $supplierName }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td data-column="7" hidden>
                                        @php
                                            $supplier = $item->supplier ?? '';
                                            $grouped = collect($data)->where('supplier', $supplier);
                                            $firstAdvance = $grouped->first()->advance_amt ?? 0;
                                        @endphp

                                        @if ($loop->first || $item->sku === $grouped->first()->sku)
                                            <input type="number"
                                                class="form-control form-control-sm auto-save"
                                                data-sku="{{ $item->sku }}"
                                                data-supplier="{{ $supplier }}"
                                                data-column="advance_amt"
                                                value="{{ $firstAdvance }}"
                                                min="0"
                                                max="10000"
                                                step="0.01"
                                                style="min-width: 90px; max-width: 120px; font-size: 13px;"
                                                placeholder="Advance Amt"
                                                onchange="if(this.value > 10000) {
                                                    alert('Amount cannot exceed 10000');
                                                    this.value = '';
                                                    return false;
                                                }">
                                        @else
                                            <input type="number" class="form-control form-control-sm"
                                                value="{{ $firstAdvance }}"
                                                disabled
                                                style="min-width: 90px; max-width: 120px; font-size: 13px; background: #e9ecef;" />
                                        @endif
                                    </td>
                                    <td data-column="8" hidden>
                                        <input type="date" value="{{ !empty($item->adv_date) ? \Carbon\Carbon::parse($item->adv_date)->format('Y-m-d') : '' }}" data-sku="{{ $item->sku }}" 
                                        data-column="adv_date" class="form-control form-control-sm auto-save" style="width: 80px; font-size: 13px;">
                                    </td>
                                    <td data-column="9" hidden>
                                        <input type="date" value="{{ !empty($item->pay_conf_date) ? \Carbon\Carbon::parse($item->pay_conf_date)->format('Y-m-d') : '' }}" data-sku="{{ $item->sku }}"
                                         data-column="pay_conf_date" class="form-control form-control-sm auto-save" style="width: 80px; font-size: 13px;">
                                    </td>
                                    {{-- <td data-column="12">
                                        <div class="input-group input-group-sm align-items-center" style="gap: 4px;">
                                            <span class="input-group-text open-link-icon border-0 p-0 bg-transparent" style="{{ empty($item->o_links) ? 'display:none;' : '' }}; background: none !important;">
                                                <a href="{{ $item->o_links ?? '#' }}" target="_blank" title="Open Link" style="color: #3bc0c3; font-size: 20px; display: flex; align-items: center; background: none;">
                                                    <i class="mdi mdi-open-in-new" style="transition: color 0.2s; cursor: pointer;"></i>
                                                </a>
                                            </span>
                                            <span class="input-group-text edit-link-icon border-0 p-0 bg-transparent" style="cursor:pointer; background: none !important;">
                                                <a href="javascript:void(0);" class="edit-o-links" title="Edit" style="color: #6c757d; font-size: 20px; display: flex; align-items: center; background: none;">
                                                    <i class="mdi mdi-pencil-outline" style="transition: color 0.2s; cursor: pointer;"></i>
                                                </a>
                                            </span>
                                                </a>
                                            </span>
                                            <input type="text" class="form-control form-control-sm o-links-input d-none auto-save" value="{{ $item->o_links ?? '' }}" data-sku="{{ $item->sku }}" data-column="o_links" placeholder="Paste or type link here..." style="font-size: 13px; min-width: 180px; border-radius: 20px; box-shadow: 0 1px 4px rgba(60,192,195,0.08); border: 1px solid #e3e3e3; padding-left: 14px; background: #f8fafd;">
                                        </div>
                                    </td> --}}

                                    <td class="total-value d-none" data-column="12">
                                        {{ is_numeric($item->qty ?? null) && is_numeric($item->rate ?? null) ? ($item->qty * $item->rate) : '' }}
                                    </td>
                                    <td data-column="13" hidden>
                                        @php
                                            $supplier = $item->supplier ?? '';
                                            $grouped = collect($data)->where('supplier', $supplier);

                                            $supplierAdvance = $grouped->first()->advance_amt ?? 0;

                                            $totalValue = $grouped->sum(function ($row) {
                                                return (is_numeric($row->qty) && is_numeric($row->rate)) ? $row->qty * $row->rate : 0;
                                            });

                                            $thisRowValue = (is_numeric($item->qty ?? null) && is_numeric($item->rate ?? null)) ? $item->qty * $item->rate : 0;

                                            $rowAdvance = $totalValue > 0 ? ($thisRowValue / $totalValue) * $supplierAdvance : 0;
                                            $pending = $thisRowValue - $rowAdvance;
                                        @endphp
                                        {{ number_format($pending, 0) }}
                                    </td>

                                    {{-- <td data-column="15">
                                        <div class="image-upload-field d-flex align-items-center gap-2">
                                            @if(!empty($item->photo_packing))
                                                <a href="{{ $item->photo_packing }}" target="_blank" class="me-1" title="View Photo" style="width:50px;">
                                                    <img src="{{ $item->photo_packing }}" class="img-thumbnail border" style="height: 50px; width: 50px; object-fit: cover; background: #f8f9fa;">
                                                </a>
                                            @endif

                                            <label class="btn btn-sm btn-outline-primary d-flex align-items-center mb-0" style="padding: 4px 10px; border-radius: 6px; font-size: 15px;" title="Upload Photo">
                                                <i class="mdi mdi-upload" style="font-size: 18px; margin-right: 4px;"></i>
                                                <span style="font-size: 13px;"></span>
                                                <input type="file" class="d-none auto-upload" data-column="photo_packing" data-sku="{{ $item->sku }}">
                                            </label>
                                        </div>
                                    </td> --}}

                                    {{-- <td data-column="16">
                                        <div class="image-upload-field d-flex align-items-center gap-2">
                                            @if(!empty($item->photo_int_sale))
                                                <a href="{{ $item->photo_int_sale }}" target="_blank" class="me-1" title="View Photo" style="width:50px;">
                                                    <img src="{{ $item->photo_int_sale }}" class="img-thumbnail border" style="height: 50px; width: 50px; object-fit: cover; background: #f8f9fa;">
                                                </a>
                                            @endif

                                            <label class="btn btn-sm btn-outline-primary d-flex align-items-center mb-0" style="padding: 4px 10px; border-radius: 6px; font-size: 15px;" title="Upload Photo">
                                                <i class="mdi mdi-upload" style="font-size: 18px; margin-right: 4px;"></i>
                                                <span style="font-size: 13px;"></span>
                                                <input type="file" class="d-none auto-upload" data-column="photo_int_sale" data-sku="{{ $item->sku }}">
                                            </label>
                                        </div>
                                    </td> --}}

                                    <td data-column="14" hidden>
                                        {{ isset($item->CBM) ? number_format($item->CBM, 4) : 'N/A' }}
                                    </td>
                                    <td data-column="15">
                                        <input type="number"
                                            data-sku="{{ $item->sku }}"
                                            data-column="total_cbm"
                                            step="0.000000001"
                                            value="{{ is_numeric($item->qty ?? null) && is_numeric($item->CBM ?? null) ? number_format($item->qty * $item->CBM, 2, '.', '') : '' }}"
                                            class="form-control form-control-sm auto-save"
                                            style="min-width: 90px; width: 100px; font-size: 13px;"
                                            placeholder="T-CBM"
                                            readonly>
                                    </td>
                                    <td data-column="23" class="text-center">
                                        @php
                                            $pkgInst = $item->pkg_inst ?? 'No';
                                            $pkgInstYes = strtoupper(trim($pkgInst)) === 'YES';
                                        @endphp
                                        <span
                                            class="pkg-inst-toggle"
                                            data-sku="{{ $item->sku }}"
                                            data-column="pkg_inst"
                                            data-value="{{ $pkgInstYes ? 'Yes' : 'No' }}"
                                            style="display:inline-block;width:14px;height:14px;border-radius:50%;cursor:pointer;background-color: {{ $pkgInstYes ? '#28a745' : '#dc3545' }};">
                                        </span>
                                    </td>
                                    <td data-column="24" class="text-center">
                                        @php
                                            $uManual = $item->u_manual ?? 'No';
                                            $uManualYes = strtoupper(trim($uManual)) === 'YES';
                                        @endphp
                                        <span
                                            class="u-manual-toggle"
                                            data-sku="{{ $item->sku }}"
                                            data-column="u_manual"
                                            data-value="{{ $uManualYes ? 'Yes' : 'No' }}"
                                            style="display:inline-block;width:14px;height:14px;border-radius:50%;cursor:pointer;background-color: {{ $uManualYes ? '#28a745' : '#dc3545' }};">
                                        </span>
                                    </td>
                                    <td data-column="25" class="text-center">
                                        @php
                                            $compliance = $item->compliance ?? 'No';
                                            $complianceYes = strtoupper(trim($compliance)) === 'YES';
                                        @endphp
                                        <span
                                            class="compliance-toggle"
                                            data-sku="{{ $item->sku }}"
                                            data-column="compliance"
                                            data-value="{{ $complianceYes ? 'Yes' : 'No' }}"
                                            style="display:inline-block;width:14px;height:14px;border-radius:50%;cursor:pointer;background-color: {{ $complianceYes ? '#28a745' : '#dc3545' }};">
                                        </span>
                                    </td>
                                    <td data-column="20" class="text-center" hidden>
                                        {{ isset($item->ctn_cbm_e) && $item->ctn_cbm_e !== null ? number_format($item->ctn_cbm_e, 4) : 'N/A' }}
                                    </td>
                                    <td data-column="17" class="text-center align-middle">
                                        @php
                                            $stageValue = $item->stage ?? '';
                                            $sv = strtolower(trim((string) $stageValue));
                                            $mipStageTips = [
                                                '' => 'Select stage',
                                                'appr_req' => 'Appr Req — Approval',
                                                'mip' => 'MIP',
                                                'r2s' => 'R2S — Ready to ship',
                                                'transit' => 'Transit',
                                                'to_order_analysis' => 'Order — 2 Order',
                                                'all_good' => 'All Good',
                                            ];
                                            $mipStageTip = $mipStageTips[$sv] ?? 'Select stage';
                                            $mipDotColor = '#94a3b8';
                                            if ($sv === 'appr_req') {
                                                $mipDotColor = '#facc15';
                                            } elseif ($sv === 'mip') {
                                                $mipDotColor = '#2563eb';
                                            } elseif ($sv === 'to_order_analysis') {
                                                $mipDotColor = '#c2410c';
                                            } elseif ($sv === 'r2s') {
                                                $mipDotColor = '#16a34a';
                                            } elseif ($sv === 'all_good') {
                                                $mipDotColor = '#22c55e';
                                            }
                                        @endphp
                                        <div class="stage-dot-cell mip-stage-dot position-relative d-flex justify-content-center align-items-center mx-auto" title="{{ $mipStageTip }}">
                                            <span class="mip-stage-marker d-inline-flex justify-content-center align-items-center" style="pointer-events: none;">
                                                @if ($sv === 'transit')
                                                    <i class="fas fa-truck stage-transit-icon" aria-hidden="true"></i>
                                                @else
                                                    <span class="stage-status-dot" style="background-color: {{ $mipDotColor }};" aria-hidden="true"></span>
                                                @endif
                                            </span>
                                            <select class="form-select form-select-sm editable-select-stage stage-stage-select position-absolute top-0 start-0 w-100 h-100"
                                                data-type="Stage"
                                                data-sku="{{ $item->sku }}"
                                                data-parent="{{ e($item->parent ?? '') }}"
                                                aria-label="{{ $mipStageTip }}">
                                                <option value="">Select</option>
                                                <option value="appr_req" {{ $sv === 'appr_req' ? 'selected' : '' }}>Appr. Req</option>
                                                <option value="mip" {{ $sv === 'mip' ? 'selected' : '' }}>MIP</option>
                                                <option value="r2s" {{ $sv === 'r2s' ? 'selected' : '' }}>R2S</option>
                                                <option value="transit" {{ $sv === 'transit' ? 'selected' : '' }}>Transit</option>
                                                <option value="all_good" {{ $sv === 'all_good' ? 'selected' : '' }}>😊 All Good</option>
                                                <option value="to_order_analysis" {{ $sv === 'to_order_analysis' ? 'selected' : '' }}>2 Order</option>
                                            </select>
                                        </div>
                                    </td>

                                    {{-- <td data-column="19">
                                        <div class="image-upload-field d-flex align-items-center gap-2">
                                            @if(!empty($item->barcode_sku))
                                                <a href="{{ $item->barcode_sku }}" target="_blank" class="me-1" title="View Photo" style="width:50px;">
                                                    <img src="{{ $item->barcode_sku }}" class="img-thumbnail border" style="height: 50px; width: 50px; object-fit: cover; background: #f8f9fa;">
                                                </a>
                                            @endif

                                            <label class="btn btn-sm btn-outline-primary d-flex align-items-center mb-0" style="padding: 4px 10px; border-radius: 6px; font-size: 15px;" title="Upload Photo">
                                                <i class="mdi mdi-upload" style="font-size: 18px; margin-right: 4px;"></i>
                                                <span style="font-size: 13px;"></span>
                                                <input type="file" class="d-none auto-upload" data-column="barcode_sku" data-sku="{{ $item->sku }}">
                                            </label>
                                        </div>
                                    </td>

                                    <td data-column="20">
                                        <input type="text" class="form-control form-control-sm auto-save" data-sku="{{ $item->sku }}" data-column="artwork_manual_book" value="{{ $item->artwork_manual_book ?? '' }}" placeholder="Artwork Manual Book">
                                    </td>

                                    <td data-column="21">
                                        <input type="text" class="form-control form-control-sm auto-save" data-sku="{{ $item->sku }}" data-column="notes" value="{{ $item->notes ?? '' }}" style="font-size: 13px;" placeholder="Notes">
                                    </td> --}}

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
    /** Lazy-load Tabulator when opening Archived History (saves initial parse on main MIP page). */
    var mipTabulatorLoadPromise = null;
    function mipEnsureTabulator() {
        if (typeof Tabulator !== 'undefined') {
            return Promise.resolve();
        }
        if (!mipTabulatorLoadPromise) {
            mipTabulatorLoadPromise = new Promise(function (resolve, reject) {
                if (!document.querySelector('link[data-mip-tabulator-css]')) {
                    var link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css';
                    link.setAttribute('data-mip-tabulator-css', '1');
                    document.head.appendChild(link);
                }
                var s = document.createElement('script');
                s.src = 'https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js';
                s.async = true;
                s.onload = function () { resolve(); };
                s.onerror = function () { reject(new Error('Tabulator failed to load')); };
                document.body.appendChild(s);
            });
        }
        return mipTabulatorLoadPromise;
    }

    const popup = document.createElement('img');
    popup.className = 'preview-popup';
    document.body.appendChild(popup);

    function mipCopySkuToClipboard(text, onDone) {
        const t = String(text || '').trim();
        if (!t) return;
        const finish = typeof onDone === 'function' ? onDone : function () {};
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(t).then(finish).catch(function () {
                mipCopySkuFallback(t, finish);
            });
            return;
        }
        mipCopySkuFallback(t, finish);
    }

    function mipCopySkuFallback(text, onDone) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            if (typeof onDone === 'function') {
                onDone();
            }
        } catch (err) {
            alert('Unable to copy SKU.');
        }
        document.body.removeChild(ta);
    }

    function calculateTotalCBM() {
        let totalCBM = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            if (rowStage !== 'mip') return;
            if (row.style.display !== "none") {
                const input = row.querySelector('input[data-column="total_cbm"]');
                if (input) {
                    const value = parseFloat(input.value);
                    if (!isNaN(value)) totalCBM += value;
                }
            }
        });
        const el = document.getElementById('total-cbm');
        if (el) el.textContent = String(Math.round(totalCBM));
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.documentElement.setAttribute("data-sidenav-size", "condensed");

        (function initMipLayoutDensity() {
            const root = document.getElementById('mip-page-root');
            const btn = document.getElementById('mipToggleDenseLayout');
            const KEY = 'mipDenseLayout';
            function sync() {
                const dense = localStorage.getItem(KEY) === '1';
                if (root) root.classList.toggle('mip-dense-layout', dense);
                if (btn) {
                    btn.setAttribute('aria-pressed', dense ? 'true' : 'false');
                    btn.title = dense ? 'Switch to larger text and spacing' : 'Compact layout — more rows on screen';
                    const label = btn.querySelector('.mip-density-label');
                    if (label) label.textContent = dense ? 'Comfort view' : 'Dense view';
                }
            }
            if (btn) {
                btn.addEventListener('click', function () {
                    localStorage.setItem(KEY, localStorage.getItem(KEY) === '1' ? '0' : '1');
                    sync();
                });
            }
            sync();
        })();

        const table = document.querySelector('.wide-table');
        const mipTbody = table.querySelector('tbody');
        /** Static snapshot of row nodes at load; filter/sort re-query when needed inside rAF. */
        const rows = table.querySelectorAll('tbody tr');

        // Image preview: one delegated listener (was 3 listeners × every row image)
        if (mipTbody) {
            let hoverMoveRaf = false;
            let hoverClientX = 0;
            let hoverClientY = 0;
            mipTbody.addEventListener('mouseover', function (e) {
                const img = e.target.closest('.hover-img');
                if (!img || !mipTbody.contains(img)) return;
                const src = img.dataset.src || img.getAttribute('src');
                if (src) popup.src = src;
                popup.style.display = 'block';
            });
            mipTbody.addEventListener('mousemove', function (e) {
                if (popup.style.display !== 'block') return;
                hoverClientX = e.clientX;
                hoverClientY = e.clientY;
                if (hoverMoveRaf) return;
                hoverMoveRaf = true;
                requestAnimationFrame(function () {
                    hoverMoveRaf = false;
                    popup.style.top = (hoverClientY + 20) + 'px';
                    popup.style.left = (hoverClientX + 20) + 'px';
                });
            });
            mipTbody.addEventListener('mouseout', function (e) {
                const img = e.target.closest('.hover-img');
                if (!img || !mipTbody.contains(img)) return;
                const rel = e.relatedTarget;
                if (rel && img.contains(rel)) return;
                popup.style.display = 'none';
            });

            mipTbody.addEventListener('click', function (e) {
                const btn = e.target.closest('.mip-copy-sku');
                if (!btn || !mipTbody.contains(btn)) return;
                e.preventDefault();
                e.stopPropagation();
                const sku = (btn.dataset.sku || '').trim();
                if (!sku) return;
                const icon = btn.querySelector('i');
                const prevClass = icon ? icon.className : '';
                mipCopySkuToClipboard(sku, function () {
                    if (icon) {
                        icon.className = 'fas fa-check';
                    }
                    btn.classList.add('text-success');
                    setTimeout(function () {
                        if (icon) {
                            icon.className = prevClass;
                        }
                        btn.classList.remove('text-success');
                    }, 1100);
                });
            });
        }

        initColumnResizing();

        // Column Visibility
        setupColumnVisibility();


        // ✅ Column-Specific Filter (Professional Version)
        setupColumnSearch();

        // Inline Auto-Save
        setupAutoSave();

        // Stage Update Handler
        setupStageUpdate();
        setupMipBulkStage();
        setupNRPUpdate();

        // Defer row-heavy work so first paint + toolbar stay responsive (large tables).
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                filterByMIPStage();
                const rowsAfter = table.querySelectorAll('tbody tr');
                const visibleMipRows = Array.from(rowsAfter).filter(r => {
                    const stageAttr = (r.getAttribute('data-stage') || '').toLowerCase().trim();
                    const stageSelect = r.querySelector('.editable-select-stage');
                    const stage = (stageSelect ? stageSelect.value : '').toLowerCase().trim() || stageAttr;
                    return stage === 'mip' && r.style.display !== 'none';
                });
                if (visibleMipRows.length > 0 && typeof sortRowsByOrderDate === 'function') {
                    sortRowsByOrderDate(visibleMipRows);
                }

                setupCurrencyConversion();
                setupOlinkEditor();

                calculateTotalCBM();
                calculateTotalAmount();
                calculateTotalOrderItems();
            });
        });

        // Pkg Inst / U-Manual / Compliance dots: one delegated handler (was 3 listeners × rows)
        if (mipTbody) {
            mipTbody.addEventListener('click', function (e) {
                const dot = e.target.closest('.pkg-inst-toggle, .u-manual-toggle, .compliance-toggle');
                if (!dot || !mipTbody.contains(dot)) return;
                const sku = dot.dataset.sku;
                if (!sku) return;
                let column = dot.dataset.column;
                if (!column) {
                    if (dot.classList.contains('pkg-inst-toggle')) column = 'pkg_inst';
                    else if (dot.classList.contains('u-manual-toggle')) column = 'u_manual';
                    else column = 'compliance';
                }
                const label = column === 'pkg_inst' ? 'Pkg Inst.' : column === 'u_manual' ? 'U-Manual.' : 'Compliance.';
                const current = (dot.dataset.value || 'No').toLowerCase() === 'yes' ? 'Yes' : 'No';
                const next = current === 'Yes' ? 'No' : 'Yes';

                fetch('/mfrg-progresses/inline-update-by-sku', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ sku, column, value: next })
                })
                    .then(res => res.json())
                    .then(res => {
                        if (!res.success) {
                            alert('Error: ' + (res.message || 'Failed to update ' + label));
                            return;
                        }
                        dot.dataset.value = next;
                        dot.style.backgroundColor = next === 'Yes' ? '#28a745' : '#dc3545';
                    })
                    .catch(() => alert('AJAX error occurred.'));
            });
        }

        // Archive selected rows + archived history modal
        setupMipArchiveToolbar();
        setupMipArchivedHistory();
        refreshMipArchivedBadge();

        // Summary Export to Excel
        const summaryExportBtn = document.getElementById('mip-summary-export-btn');
        if (summaryExportBtn) summaryExportBtn.addEventListener('click', exportMipSummaryToExcel);

        // ========= FUNCTIONS ========= //

        function initColumnResizing() {
            const resizers = document.querySelectorAll('.resizer');
            resizers.forEach(resizer => {
                resizer.addEventListener('mousedown', initResize);
            });

            function initResize(e) {
                e.preventDefault();
                const th = e.target.parentElement;
                if (th.getAttribute('data-column') === '1') {
                    return;
                }
                const startX = e.clientX;
                const startWidth = th.offsetWidth;
                e.target.classList.add('resizing');
                th.style.width = th.style.minWidth = th.style.maxWidth = startWidth + 'px';

                document.addEventListener('mousemove', resize);
                document.addEventListener('mouseup', stopResize);

                function resize(e) {
                    const newWidth = startWidth + e.clientX - startX;
                    if (newWidth > 80) {
                        th.style.width = th.style.minWidth = th.style.maxWidth = newWidth + 'px';

                    }
                }

                function stopResize() {
                    document.removeEventListener('mousemove', resize);
                    document.removeEventListener('mouseup', stopResize);
                    document.querySelectorAll('.resizing').forEach(el => el.classList.remove('resizing'));
                    saveColumnWidths();
                }
            }

        function saveColumnWidths() {
            const widths = {};
            document.querySelectorAll('.wide-table thead th').forEach(th => {
                const col = th.getAttribute('data-column');
                if (col === null || col === '') return;
                widths[col] = col === '1' ? 52 : th.offsetWidth;
            });
            localStorage.setItem('columnWidths_mfrg', JSON.stringify(widths));
        }

        function restoreColumnWidths() {
            const widths = JSON.parse(localStorage.getItem('columnWidths_mfrg') || '{}');
                Object.keys(widths).forEach(col => {
                    const th = document.querySelector(`.wide-table thead th[data-column="${col}"]`);
                    if (th) {
                        const w = col === '1' ? 52 : widths[col];
                        th.style.width = th.style.minWidth = th.style.maxWidth = w + 'px';
                    }
                });
            }
            restoreColumnWidths();
        }

        function setupColumnVisibility() {
            const ths = document.querySelectorAll('.wide-table thead th');

            function getHiddenColumns() {
                return JSON.parse(localStorage.getItem('hiddenColumns_mfrg') || '[]');
            }

            const hiddenColumns = getHiddenColumns();
            ths.forEach((th) => {
                const columnIndex = th.getAttribute('data-column');
                if (!columnIndex) return;
                if (columnIndex === '1') return;
                if (hiddenColumns.includes(columnIndex)) {
                    document.querySelectorAll(`[data-column="${columnIndex}"]`).forEach(cell => cell.style.display = 'none');
                }
            });
        }

        // Column filters: debounced + data-sku/data-parent (avoid full table resort on every keypress)
        function setupColumnSearch() {
            let debounceTimer = null;

            function collectFilters() {
                const filters = {};
                document.querySelectorAll('.column-search').forEach(function (searchInput) {
                    const col = searchInput.getAttribute('data-search-column');
                    const val = searchInput.value.trim().toLowerCase();
                    if (val !== '') {
                        filters[col] = val;
                    }
                });
                return filters;
            }

            function applyMipColumnFilters() {
                const filters = collectFilters();
                rows.forEach(function (row) {
                    const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                    const stageSelect = row.querySelector('.editable-select-stage');
                    const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                    const rowStage = rowStageSelect || rowStageAttr;
                    if (rowStage !== 'mip') {
                        row.style.display = 'none';
                        return;
                    }
                    let show = true;
                    for (const col in filters) {
                        let cellText = '';
                        if (col === '3') {
                            cellText = (row.getAttribute('data-sku') || '').trim().toLowerCase();
                        } else if (col === '2') {
                            cellText = (row.getAttribute('data-parent') || '').trim().toLowerCase();
                        } else if (col === '6') {
                            const cell = row.querySelector('td[data-column="6"]');
                            if (!cell) {
                                show = false;
                                break;
                            }
                            const sel = cell.querySelector('select[data-column="supplier"]');
                            const opt = sel?.selectedOptions?.[0] || sel?.querySelector('option:checked');
                            cellText = (opt ? opt.textContent : (sel?.value || '')).trim().toLowerCase();
                        } else {
                            const cell = row.querySelector('td[data-column="' + col + '"]');
                            if (!cell) {
                                show = false;
                                break;
                            }
                            cellText = (cell.textContent || '').toLowerCase();
                        }
                        if (!cellText.includes(filters[col])) {
                            show = false;
                            break;
                        }
                    }
                    row.style.display = show ? '' : 'none';
                });
                const visibleRows = Array.from(rows).filter(function (r) { return r.style.display !== 'none'; });
                if (visibleRows.length > 1 && typeof sortRowsByOrderDate === 'function') {
                    sortRowsByOrderDate(visibleRows);
                }
                if (typeof calculateTotalCBM === 'function') calculateTotalCBM();
                if (typeof calculateTotalAmount === 'function') calculateTotalAmount();
                if (typeof calculateTotalOrderItems === 'function') calculateTotalOrderItems();
                if (typeof updateFollowSupplierCount === 'function') updateFollowSupplierCount();
            }

            function scheduleApply() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    debounceTimer = null;
                    applyMipColumnFilters();
                }, 200);
            }

            document.querySelectorAll('.column-search').forEach(function (input) {
                input.addEventListener('input', scheduleApply);
            });
        }

        function setupAutoSave() {
            document.querySelectorAll('.auto-save').forEach(function (input) {
                input.addEventListener('change', function () {
                    const sku = this.dataset.sku;
                    const column = this.dataset.column;
                    const value = this.value;
                    const row = this.closest('tr');

                    if (!sku || !column) return;

                    // ✅ Save via AJAX
                    fetch('/mfrg-progresses/inline-update-by-sku', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ sku, column, value })
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success) {
                            this.style.border = '2px solid green';
                            setTimeout(() => this.style.border = '', 1000);


                            // ✅ Recalculate Total on rate change
                            if (column === 'rate') {
                                const qtyCell = row.querySelector('td[data-column="4"]');
                                const totalCell = row.querySelector('td[data-column="12"]');
                                const qtyInput = qtyCell?.querySelector('input[data-column="qty"]');
                                const qty = parseFloat(qtyInput?.value || qtyCell?.innerText?.trim() || '0');
                                const rate = parseFloat(value);
                                if (!isNaN(qty) && !isNaN(rate)) {
                                    totalCell.innerText = (qty * rate).toFixed(2);
                                }
                            }
                            if (column === 'qty') {
                                const qtyNum = parseFloat(value) || 0;
                                const rateInput = row.querySelector('td[data-column="5"] input[data-column="rate"]');
                                const rateNum = parseFloat(rateInput?.value || '0') || 0;
                                const totalCell = row.querySelector('td[data-column="12"]');
                                if (totalCell) {
                                    totalCell.innerText = (qtyNum * rateNum).toFixed(2);
                                }
                                const cbmCell = row.querySelector('td[data-column="14"]');
                                const cbmNum = parseFloat(cbmCell?.textContent?.trim() || '0') || 0;
                                const totalCbmInput = row.querySelector('input[data-column="total_cbm"]');
                                if (totalCbmInput) {
                                    totalCbmInput.value = (qtyNum * cbmNum).toFixed(2);
                                }
                                if (typeof calculateTotalCBM === 'function') {
                                    calculateTotalCBM();
                                }
                                if (typeof updateFollowSupplierCount === 'function') {
                                    updateFollowSupplierCount();
                                }
                            }

                            // ✅ Recalculate Pending on advance_amt change
                            if (column === 'advance_amt') {
                                const totalCell = row.querySelector('td[data-column="12"]');
                                const pendingCell = row.querySelector('td[data-column="13"]');
                                const total = parseFloat(totalCell?.innerText?.trim() || '0');
                                const advance = parseFloat(value);
                                if (!isNaN(total) && !isNaN(advance)) {
                                    pendingCell.innerText = (total - advance).toFixed(2);
                                }
                            }

                            // ✅ Insert into Ready to Ship table
                            if (column === 'ready_to_ship' && value === 'Yes') {
                                const parent = row.querySelector('td[data-column="2"]')?.innerText?.trim() || '';
                                const skuVal = (row.getAttribute('data-sku') || row.querySelector('td[data-column="3"] .mip-sku-text')?.textContent || '').trim();
                                const supplierSelect = row.querySelector('td[data-column="6"] select[data-column="supplier"]');
                                const supplier = supplierSelect ? supplierSelect.value.trim() : '';
                                const qty = row.querySelector('td[data-column="4"] input[data-column="qty"]')?.value?.trim() || '';
                                const totalCbm = row.querySelector('td[data-column="15"] input')?.value?.trim() || '';
                                const rate = row.querySelector('td[data-column="5"] input')?.value?.trim() || '';                                

                                fetch('/ready-to-ship/insert', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({
                                        parent: parent,
                                        sku: skuVal,
                                        supplier: supplier,
                                        qty: qty,
                                        totalCbm: totalCbm,
                                        rate: rate
                                    })
                                })
                                .then(r => r.json())
                                .then(r => {
                                    if (r.success) {
                                        row.remove();
                                    } else {
                                        alert('❌ Failed to insert into Ready to Ship: ' + r.message);
                                    }
                                })
                                .catch(() => {
                                    alert('❌ Error during Ready to Ship insert.');
                                });
                            }

                        } else {
                            this.style.border = '2px solid red';
                            console.log('❌ Error:', res.message);
                        }
                    })
                    .catch(() => {
                        this.style.border = '2px solid red';
                        alert('❌ AJAX error occurred.');
                    });
                });
            });
        }

        // Reusable AJAX call for updating forecast_analysis table
        function updateForecastField(data, onSuccess = () => {}, onFail = () => {}) {
            fetch('/update-forecast-data', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(result => {
                if (result.success) {
                    console.log('Saved:', result.message);
                    onSuccess();
                } else {
                    console.warn('Not saved:', result.message);
                    onFail();
                }
            })
            .catch(err => {
                console.error('AJAX failed:', err);
                alert('Error saving data.');
                onFail();
            });
        }

        /** Match Forecast Analysis stage colors + transit truck icon. */
        function mipStageTooltip(v) {
            const tips = {
                '': 'Select stage',
                appr_req: 'Appr Req — Approval',
                mip: 'MIP',
                r2s: 'R2S — Ready to ship',
                transit: 'Transit',
                to_order_analysis: 'Order — 2 Order',
                all_good: 'All Good',
            };
            return tips[v] || 'Select stage';
        }

        function mipApplyStageSelectVisual(selectEl, value) {
            if (!selectEl) return;
            const v = String(value || '').trim().toLowerCase();
            const wrap = selectEl.closest('.mip-stage-dot');
            const slot = wrap ? wrap.querySelector('.mip-stage-marker') : null;
            if (wrap) {
                wrap.title = mipStageTooltip(v);
            }
            selectEl.setAttribute('aria-label', mipStageTooltip(v));
            if (!slot) {
                return;
            }
            if (v === 'transit') {
                slot.innerHTML = '<i class="fas fa-truck stage-transit-icon" aria-hidden="true"></i>';
            } else {
                let c = '#94a3b8';
                if (v === 'appr_req') {
                    c = '#facc15';
                } else if (v === 'mip') {
                    c = '#2563eb';
                } else if (v === 'to_order_analysis') {
                    c = '#c2410c';
                } else if (v === 'r2s') {
                    c = '#16a34a';
                } else if (v === 'all_good') {
                    c = '#22c55e';
                }
                slot.innerHTML = '<span class="stage-status-dot" style="background-color:' + c + ';" aria-hidden="true"></span>';
            }
        }

        function updateForecastFieldAsync(data) {
            return new Promise(function (resolve) {
                updateForecastField(
                    data,
                    function () { resolve(true); },
                    function () { resolve(false); }
                );
            });
        }

        function setupMipBulkStage() {
            const applyBtn = document.getElementById('mip-bulk-stage-apply');
            const bulkSel = document.getElementById('mip-bulk-stage-select');
            if (!applyBtn || !bulkSel) return;

            applyBtn.addEventListener('click', async function () {
                const stageVal = String(bulkSel.value || '').trim();
                if (!stageVal) {
                    alert('Choose a stage to apply.');
                    return;
                }
                const checked = document.querySelectorAll('.row-checkbox:checked');
                if (!checked.length) {
                    alert('Select at least one row (checkbox in the first column).');
                    return;
                }

                let ok = 0;
                let skippedQty = 0;
                let failed = 0;

                applyBtn.disabled = true;
                const prevLabel = applyBtn.textContent;
                applyBtn.textContent = '…';

                for (const cb of checked) {
                    const row = cb.closest('tr');
                    if (!row) continue;
                    const sel = row.querySelector('.editable-select-stage');
                    const sku = sel ? sel.dataset.sku : row.getAttribute('data-sku');
                    const parent = sel ? (sel.dataset.parent || '') : (row.getAttribute('data-parent') || '');
                    if (!sku) {
                        failed++;
                        continue;
                    }
                    const qtyCell = row.querySelector('td[data-column="4"] input');
                    const orderQty = qtyCell ? parseFloat(qtyCell.value) : 0;
                    if (!orderQty || orderQty === 0) {
                        skippedQty++;
                        continue;
                    }

                    const saved = await updateForecastFieldAsync({
                        sku: sku,
                        parent: parent,
                        column: 'Stage',
                        value: stageVal,
                    });

                    if (saved) {
                        ok++;
                        if (sel) {
                            sel.value = stageVal;
                            mipApplyStageSelectVisual(sel, stageVal);
                        }
                        row.setAttribute('data-stage', stageVal);
                    } else {
                        failed++;
                    }
                }

                applyBtn.disabled = false;
                applyBtn.textContent = prevLabel;

                let msg = 'Updated ' + ok + ' row(s).';
                if (skippedQty) msg += ' Skipped ' + skippedQty + ' with empty or zero order qty.';
                if (failed) msg += ' ' + failed + ' failed.';
                alert(msg);

                if (typeof filterByMIPStage === 'function') {
                    filterByMIPStage();
                }
            });
        }

        function setupStageUpdate() {
            document.querySelectorAll('.editable-select-stage').forEach(function (select) {
                select.addEventListener('focus', function () {
                    this.dataset.prevStage = this.value;
                });
                select.addEventListener('change', function () {
                    const sel = this;
                    const sku = sel.dataset.sku;
                    const parent = sel.dataset.parent;
                    const value = sel.value.trim();

                    mipApplyStageSelectVisual(sel, value);

                    const row = sel.closest('tr');
                    const qtyCell = row ? row.querySelector('td[data-column="4"] input') : null;
                    const orderQty = qtyCell ? parseFloat(qtyCell.value) : 0;

                    if (!orderQty || orderQty === 0) {
                        alert('Order Qty cannot be empty or zero.');
                        sel.value = sel.dataset.prevStage || '';
                        mipApplyStageSelectVisual(sel, sel.value);
                        return;
                    }

                    updateForecastField(
                        {
                            sku: sku,
                            parent: parent,
                            column: 'Stage',
                            value: value,
                        },
                        function () {
                            sel.value = value;
                            if (row) {
                                row.setAttribute('data-stage', value);
                            }
                            mipApplyStageSelectVisual(sel, value);
                        },
                        function () {
                            alert('Failed to save Stage.');
                            location.reload();
                        }
                    );
                });
            });
            document.querySelectorAll('.mip-stage-dot .editable-select-stage').forEach(function (sel) {
                mipApplyStageSelectVisual(sel, sel.value);
            });
        }

        function setupNRPUpdate() {
            document.querySelectorAll('.editable-select-nrp').forEach(function(select) {
                select.addEventListener('change', function() {
                    const sku = this.dataset.sku;
                    const parent = this.dataset.parent;
                    const value = this.value.trim();
                    const row = this.closest('tr');

                    // Update background color immediately
                    let bgColor = '#ffffff';
                    let textColor = '#000000';
                    if (value === 'NR') {
                        bgColor = '#dc3545';
                        textColor = '#ffffff';
                    } else if (value === 'REQ') {
                        bgColor = '#28a745';
                        textColor = '#000000';
                    } else if (value === 'LATER') {
                        bgColor = '#ffc107';
                        textColor = '#000000';
                    }
                    this.style.backgroundColor = bgColor;
                    this.style.color = textColor;

                    updateForecastField({
                        sku: sku,
                        parent: parent,
                        column: 'NR',
                        value: value
                    }, function() {
                        // Success - update the select value to ensure it matches saved value
                        this.value = value;
                        // Color already updated
                        
                        // Hide/show row based on NRP value
                        if (value === 'NR') {
                            if (row) row.style.display = 'none';
                        } else {
                            if (row) row.style.display = '';
                        }
                    }, function() {
                        alert('Failed to save NRP.');
                        // Revert color and value
                        this.style.backgroundColor = '#fff';
                        this.style.color = '#000';
                        // Reload page to get correct value from database
                        location.reload();
                    });
                });
            });
        }

        // Filter to show only MIP stage rows
        function filterByMIPStage() {
            const rows = document.querySelectorAll('.wide-table tbody tr');

            rows.forEach(row => {
                // Check stage from data attribute first (more reliable)
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                
                // Also check from select dropdown value
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                
                // Use select value if available, otherwise use data attribute
                const rowStage = rowStageSelect || rowStageAttr;
                
                // Only show MIP stage rows
                if (rowStage === 'mip') {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Recalculate totals after filtering
            calculateTotalCBM();
            calculateTotalAmount();
            calculateTotalOrderItems();
            updateFollowSupplierCount();
        }

        function setupAutoUpload() {
            document.querySelectorAll('.auto-upload').forEach(function(input) {
                input.addEventListener('change', function () {
                    const sku = this.dataset.sku;
                    const column = this.dataset.column;
                    const file = this.files[0];
                    const parentDiv = input.closest('.image-upload-field');

                    if (!sku || !column || !file) return;

                    const formData = new FormData();
                    formData.append('sku', sku);
                    formData.append('column', column);
                    formData.append('value', file); 

                    fetch('/mfrg-progresses/inline-update-by-sku', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: formData
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (res.success && res.url) {
                            // ✅ Update with image preview + upload field again
                            parentDiv.innerHTML = `
                                <a href="${res.url}" target="_blank" style="width:50px;">
                                    <img src="${res.url}" alt="Uploaded" style="height: 50px; width: 50px; object-fit: cover; background: #f8f9fa;">
                                </a>
                                <label class="btn btn-sm btn-outline-primary d-flex align-items-center mb-0" style="padding: 4px 10px; border-radius: 6px; font-size: 15px;" title="Upload Photo">
                                    <i class="mdi mdi-upload" style="font-size: 18px; margin-right: 4px;"></i>
                                    <span style="font-size: 13px;"></span>
                                    <input type="file" class="d-none auto-upload" data-column="${column}" data-sku="${sku}">
                                </label>
                            `;
                            setupAutoUpload(); // re-bind to new input
                        } else {
                            alert("❌ Upload failed: " + (res.message || 'Unknown error'));
                        }
                    })
                    .catch(() => {
                        alert("❌ AJAX error during upload");
                    });
                });
            });
        }

        setupAutoUpload();

        function setupCurrencyConversion() {
            document.querySelectorAll('.input-group').forEach(group => {
                const select = group.querySelector('.currency-select');
                const input = group.querySelector('.amount-input');

                if (!select || !input) return;

                let baseCurrency = select.value;
                let baseAmount = parseFloat(input.value) || 0;

                input.addEventListener('input', () => {
                    baseAmount = parseFloat(input.value) || 0;
                    baseCurrency = select.value;
                });

                select.addEventListener('change', function () {
                    const newCurrency = select.value;

                    if (baseCurrency === newCurrency || isNaN(baseAmount) || baseAmount === 0) return;

                    const url = `/convert-currency?amount=${baseAmount}&from=${baseCurrency}&to=${newCurrency}`;

                    fetch(url)
                        .then(res => res.json())
                        .then(data => {
                            console.log('Currency API response:', data);
                            if (data.rates && data.rates[newCurrency]) {
                                const converted = data.rates[newCurrency];
                                input.value = parseFloat(converted).toFixed(2);
                                baseAmount = parseFloat(converted);
                                baseCurrency = newCurrency;
                            } else {
                                alert('Invalid currency response (missing rates).');
                            }
                        })
                        .catch(err => {
                            alert('Currency conversion failed.');
                            console.error(err);
                        });
                });
            });
        }

        function setupOlinkEditor() {
            document.querySelectorAll('.edit-o-links').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const group = btn.closest('.input-group');
                    const input = group.querySelector('.o-links-input');
                    const openIcon = group.querySelector('.open-link-icon');
                    const editIcon = group.querySelector('.edit-link-icon');

                    input.classList.remove('d-none');
                    input.focus();
                    openIcon.style.display = 'none';
                    editIcon.style.display = 'none';

                    input.addEventListener('blur', function () {
                        input.classList.add('d-none');
                        openIcon.style.display = 'inline-flex';
                        editIcon.style.display = 'inline-flex';
                    });

                    input.addEventListener('keydown', function (e) {
                        if (e.key === 'Enter') {
                            input.blur();
                        }
                    });
                });
            });
        }

        function refreshMipArchivedBadge() {
            fetch('/mfrg-progresses/archived-count', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var el = document.getElementById('mipArchivedCountBadge');
                    if (el && typeof d.count !== 'undefined') {
                        el.textContent = d.count;
                    }
                })
                .catch(function () { /* ignore */ });
        }

        let mipArchivedHistoryTable = null;

        function setupMipArchivedHistory() {
            var historyBtn = document.getElementById('mipArchivedHistoryBtn');
            var historyModalEl = document.getElementById('mipArchivedHistoryModal');
            var restoreBtn = document.getElementById('mipHistoryRestoreBtn');
            if (!historyBtn || !historyModalEl) {
                return;
            }

            historyBtn.addEventListener('click', function () {
                mipEnsureTabulator()
                    .then(function () {
                        initMipArchivedHistoryTable();
                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            bootstrap.Modal.getOrCreateInstance(historyModalEl).show();
                        }
                    })
                    .catch(function () {
                        alert('Could not load the history table. Check your network and try again.');
                    });
            });

            if (restoreBtn) {
                restoreBtn.addEventListener('click', function () {
                    if (!mipArchivedHistoryTable) {
                        return;
                    }
                    var skus = mipArchivedHistoryTable.getSelectedData().map(function (r) {
                        return (r.sku || '').trim();
                    }).filter(Boolean);
                    if (!skus.length) {
                        return;
                    }
                    if (!confirm('Restore ' + skus.length + ' row(s) to the active MIP list?')) {
                        return;
                    }
                    fetch('/mfrg-progresses/restore', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ skus: skus }),
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.success) {
                                mipArchivedHistoryTable.deselectRow();
                                refreshMipArchivedBadge();
                                alert(data.message || 'Restored.');
                                window.location.reload();
                            } else {
                                alert(data.message || 'Restore failed.');
                            }
                        })
                        .catch(function () {
                            alert('Network error while restoring.');
                        });
                });
            }

            historyModalEl.addEventListener('hidden.bs.modal', function () {
                if (mipArchivedHistoryTable) {
                    mipArchivedHistoryTable.deselectRow();
                }
                if (restoreBtn) {
                    restoreBtn.disabled = true;
                }
            });
        }

        function initMipArchivedHistoryTable() {
            if (mipArchivedHistoryTable) {
                mipArchivedHistoryTable.replaceData();
                return;
            }
            if (typeof Tabulator === 'undefined') {
                return;
            }
            mipArchivedHistoryTable = new Tabulator('#mip-archived-history-table', {
                ajaxURL: '/mfrg-in-progress/data',
                ajaxParams: { archived: 1 },
                ajaxResponse: function (url, params, response) {
                    return response.data || [];
                },
                height: '420px',
                layout: 'fitColumns',
                selectableRows: true,
                rowHeader: {
                    formatter: 'rowSelection',
                    titleFormatter: 'rowSelection',
                    headerSort: false,
                    resizable: false,
                    frozen: true,
                    headerHozAlign: 'center',
                    hozAlign: 'center',
                    width: 50,
                },
                columns: [
                    {
                        title: 'Image',
                        field: 'Image',
                        headerSort: false,
                        cssClass: 'mip-archived-image-cell',
                        width: 52,
                        minWidth: 52,
                        maxWidth: 52,
                        formatter: function (cell) {
                            var u = cell.getValue();
                            if (!u) return '—';
                            return '<div class="w-100 h-100 p-0 m-0 mip-archived-img-aspect">' +
                                '<img src="' + u.replace(/"/g, '&quot;') + '" alt="" class="w-100 h-100" style="object-fit:contain;display:block;" />' +
                                '</div>';
                        },
                    },
                    { title: 'Parent', field: 'parent', headerFilter: 'input', minWidth: 100 },
                    { title: 'SKU', field: 'sku', headerFilter: 'input', minWidth: 120 },
                    { title: 'QTY', field: 'qty', hozAlign: 'center', width: 72 },
                    { title: 'Supplier', field: 'supplier', headerFilter: 'input', width: 90, minWidth: 90 },
                    { title: 'R2S', field: 'ready_to_ship', width: 72, hozAlign: 'center' },
                ],
            });
            mipArchivedHistoryTable.on('rowSelectionChanged', function () {
                var n = mipArchivedHistoryTable.getSelectedRows().length;
                var rb = document.getElementById('mipHistoryRestoreBtn');
                if (rb) {
                    rb.disabled = n === 0;
                }
            });
        }

        function setupMipArchiveToolbar() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            const archiveBtn = document.getElementById('archiveSelectedBtn');

            function updateMipArchiveToolbar() {
                const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                const count = checkedBoxes.length;
                const show = count > 0;
                if (archiveBtn) {
                    archiveBtn.style.display = show ? 'flex' : 'none';
                }
            }

            function runArchiveSelected() {
                const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one row.');
                    return;
                }
                if (!confirm('Remove ' + checkedBoxes.length + ' item(s) from this MIP view? They will be archived (not permanently deleted) and you can restore them from History.')) {
                    return;
                }

                const skus = Array.from(checkedBoxes).map(cb => (cb.dataset.sku || '').trim()).filter(Boolean);

                fetch('/mfrg-progresses/delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ skus: skus }),
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            checkedBoxes.forEach(checkbox => {
                                const row = checkbox.closest('tr');
                                if (row) {
                                    row.remove();
                                }
                            });

                            if (selectAllCheckbox) {
                                selectAllCheckbox.checked = false;
                                selectAllCheckbox.indeterminate = false;
                            }

                            updateMipArchiveToolbar();

                            calculateTotalCBM();
                            calculateTotalAmount();
                            calculateTotalOrderItems();
                            updateFollowSupplierCount();

                            refreshMipArchivedBadge();
                            if (mipArchivedHistoryTable) {
                                mipArchivedHistoryTable.replaceData();
                            }

                            alert(data.message || ('Archived ' + (data.deleted_count || 0) + ' item(s).'));
                        } else {
                            alert('Error: ' + (data.message || 'Failed to archive items.'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while archiving items.');
                    });
            }

            // Select All functionality
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    rowCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateMipArchiveToolbar();
                });
            }

            // Individual checkbox change
            rowCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectAllState();
                    updateMipArchiveToolbar();
                });
            });

            function updateSelectAllState() {
                if (selectAllCheckbox && rowCheckboxes.length > 0) {
                    const allChecked = Array.from(rowCheckboxes).every(cb => cb.checked);
                    const someChecked = Array.from(rowCheckboxes).some(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = someChecked && !allChecked;
                }
            }

            if (archiveBtn) {
                archiveBtn.addEventListener('click', function () { runArchiveSelected(); });
            }

            updateMipArchiveToolbar();
        }

    });
</script>
<script>
    // Helper function to sort rows by order date (oldest first) - Global scope
    function sortRowsByOrderDate(rows) {
        const tbody = document.querySelector('table.wide-table tbody');
        if (!tbody) return;
        
        // Convert NodeList to Array if needed
        const rowsArray = Array.isArray(rows) ? [...rows] : Array.from(rows);
        
        if (rowsArray.length === 0) return;
        
        // Sort by order date (oldest first)
        rowsArray.sort((a, b) => {
            // First, try to extract days difference from span (most reliable for sorting)
            const dateCellA = a.querySelector('td[data-column="10"]');
            const dateCellB = b.querySelector('td[data-column="10"]');
            const spanA = dateCellA ? dateCellA.querySelector('span') : null;
            const spanB = dateCellB ? dateCellB.querySelector('span') : null;
            
            let daysA = 0;
            let daysB = 0;
            
            if (spanA) {
                const matchA = spanA.textContent.match(/\((\d+)\s*D\)/);
                daysA = matchA ? parseInt(matchA[1]) : 0;
            }
            if (spanB) {
                const matchB = spanB.textContent.match(/\((\d+)\s*D\)/);
                daysB = matchB ? parseInt(matchB[1]) : 0;
            }
            
            // If both have days difference, sort by that (higher days = older = comes first)
            if (daysA > 0 && daysB > 0) {
                return daysB - daysA; // Descending: 26D comes before 25D
            }
            
            // Fallback to date input if days difference not available
            const dateInputA = a.querySelector('input[data-column="created_at"]');
            const dateInputB = b.querySelector('input[data-column="created_at"]');
            
            let dateA = dateInputA ? dateInputA.value.trim() : '';
            let dateB = dateInputB ? dateInputB.value.trim() : '';
            
            // Extract only date part (YYYY-MM-DD) if timestamp is present
            if (dateA) {
                dateA = dateA.substring(0, 10);
            }
            if (dateB) {
                dateB = dateB.substring(0, 10);
            }
            
            // If no date, put at the end
            if (!dateA && !dateB) return 0;
            if (!dateA) return 1;
            if (!dateB) return -1;
            
            // Validate date format (YYYY-MM-DD)
            const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
            if (!dateRegex.test(dateA) || !dateRegex.test(dateB)) {
                // If invalid format, use days difference if available
                return daysB - daysA;
            }
            
            // Compare dates (oldest first) - split and compare year, month, day
            const partsA = dateA.split('-').map(Number);
            const partsB = dateB.split('-').map(Number);
            
            // Compare year first
            if (partsA[0] !== partsB[0]) {
                return partsA[0] - partsB[0]; // Older year comes first
            }
            // Compare month
            if (partsA[1] !== partsB[1]) {
                return partsA[1] - partsB[1]; // Older month comes first
            }
            // Compare day
            if (partsA[2] !== partsB[2]) {
                return partsA[2] - partsB[2]; // Older day comes first
            }
            
            // If dates are exactly the same, use days difference as tiebreaker
            // Higher days = older = should come first (descending order)
            return daysB - daysA;
        });
        
        // Get all rows from tbody to preserve hidden rows
        const allRowsInTbody = Array.from(tbody.querySelectorAll('tr'));
        const sortedRowsSet = new Set(rowsArray);
        const hiddenRows = allRowsInTbody.filter(row => !sortedRowsSet.has(row));
        
        // Remove all rows from tbody temporarily
        rowsArray.forEach(row => {
            if (row.parentNode) {
                row.parentNode.removeChild(row);
            }
        });
        
        // Append sorted visible rows first (oldest first)
        rowsArray.forEach(row => {
            tbody.appendChild(row);
        });
        
        // Append hidden rows at the end (maintain their original order)
        hiddenRows.forEach(row => {
            tbody.appendChild(row);
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        const rows = document.querySelectorAll("table.wide-table tbody tr");

        const suppliers = [];
        let supplierIndex = 0;
        let intervalId = null;

        // Collect unique suppliers (only from MIP stage rows)
        rows.forEach(row => {
            // Check stage from data attribute first (more reliable)
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            // Only collect suppliers from MIP stage rows
            if (rowStage !== 'mip') {
                return;
            }
            
            const supplierCell = row.querySelector('td[data-column="6"]');
            if (supplierCell) {
                // Get supplier from dropdown
                const supplierSelect = supplierCell.querySelector('select[data-column="supplier"]');
                const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                if (supplierName && !suppliers.includes(supplierName)) {
                    suppliers.push(supplierName);
                }
            }
        });

        function showSupplierRows(supplier) {
            const visibleRows = [];
            
            rows.forEach(row => {
                // Check stage from data attribute first (more reliable)
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                
                // Only show MIP stage rows
                if (rowStage !== 'mip') {
                    row.style.display = "none";
                    return;
                }
                
                const cell = row.querySelector('td[data-column="6"]');
                if (cell) {
                    // Get supplier from dropdown
                    const supplierSelect = cell.querySelector('select[data-column="supplier"]');
                    const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                    if (supplierName === supplier) {
                        row.style.display = "";
                        visibleRows.push(row);
                    } else {
                        row.style.display = "none";
                    }
                } else {
                    row.style.display = "none";
                }
            });

            // Sort visible rows by order date (oldest first)
            if (visibleRows.length > 0) {
                sortRowsByOrderDate(visibleRows);
            }

            // Show supplier badge with supplier name
            const supplierBadgeContainer = document.getElementById("supplier-badge-container");
            const supplierBadge = document.getElementById("current-supplier");
            const supplierBadgeVr = document.getElementById("supplier-badge-vr");
            if (supplierBadgeContainer) {
                supplierBadgeContainer.style.display = "block";
            }
            if (supplierBadgeVr) {
                supplierBadgeVr.style.display = "block";
            }
            if (supplierBadge) {
                supplierBadge.textContent = supplier || "-";
            }

            // Update modal supplier name if modal is open
            const modalSupplierName = document.getElementById("modal-supplier-name");
            if (modalSupplierName) {
                modalSupplierName.textContent = supplier || "-";
            }
            
            // Load and display remarks for this supplier
            if (typeof loadSupplierRemarks === 'function') {
                loadSupplierRemarks(supplier);
            }

            // Update counts after a small delay to ensure DOM is updated
            setTimeout(() => {
                calculateTotalCBM();
                calculateTotalAmount();
                calculateTotalOrderItems();
                updateFollowSupplierCount();
                updateCounts(); // Update pending status counts based on visible rows
            }, 50);
        }

        function playNextSupplier() {
            supplierIndex = (supplierIndex + 1) % suppliers.length;
            showSupplierRows(suppliers[supplierIndex]);
        }

        // Function to refresh supplier list (only from MIP stage rows)
        function refreshSupplierList() {
            suppliers.length = 0; // Clear existing list
            rows.forEach(row => {
                // Check stage from data attribute first (more reliable)
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                
                // Only collect suppliers from MIP stage rows
                if (rowStage !== 'mip') {
                    return;
                }
                
                const supplierCell = row.querySelector('td[data-column="6"]');
                if (supplierCell) {
                    const supplierSelect = supplierCell.querySelector('select[data-column="supplier"]');
                    const supplierName = supplierSelect ? supplierSelect.value.trim() : '';
                    if (supplierName && !suppliers.includes(supplierName)) {
                        suppliers.push(supplierName);
                    }
                }
            });
            // Reset index if current supplier is no longer in list
            if (supplierIndex >= suppliers.length) {
                supplierIndex = 0;
            }
        }

        // Play button - use event delegation to handle multiple clicks
        document.addEventListener("click", function(e) {
            const playAutoBtn = e.target.closest("#play-auto");
            if (playAutoBtn) {
                e.preventDefault();
                e.stopPropagation();
                
                // Refresh supplier list before playing
                refreshSupplierList();
                if (suppliers.length === 0) {
                    alert("No suppliers found. Please add suppliers to rows.");
                    return;
                }
                
                playAutoBtn.style.display = "none";
                const playPauseBtn = document.getElementById("play-pause");
                if (playPauseBtn) {
                    playPauseBtn.style.display = "inline-block";
                }
                
                supplierIndex = 0; // Start from first supplier
                showSupplierRows(suppliers[supplierIndex]);
            }
        });

        document.getElementById("play-pause").addEventListener("click", function () {
            this.style.display = "none";
            document.getElementById("play-auto").style.display = "inline-block";
            
            // Hide supplier badge when pausing
            const supplierBadgeContainer = document.getElementById("supplier-badge-container");
            const supplierBadgeVr = document.getElementById("supplier-badge-vr");
            if (supplierBadgeContainer) {
                supplierBadgeContainer.style.display = "none";
            }
            if (supplierBadgeVr) {
                supplierBadgeVr.style.display = "none";
            }
            
            // Show only MIP stage rows when pausing
            rows.forEach(row => {
                // Check stage from data attribute first (more reliable)
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                
                // Only show MIP stage rows
                if (rowStage === 'mip') {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
            
            const title = document.getElementById("current-supplier");
            if (title) title.textContent = "-";
            calculateTotalCBM();
            calculateTotalAmount();
            calculateTotalOrderItems();
            updateFollowSupplierCount();
            updateCounts(); // Update pending status counts when pausing
        });


        document.getElementById("play-forward").addEventListener("click", function () {
            if (suppliers.length === 0) {
                refreshSupplierList();
            }
            if (suppliers.length > 0) {
                playNextSupplier();
            }
        });

        document.getElementById("play-backward").addEventListener("click", function () {
            if (suppliers.length === 0) {
                refreshSupplierList();
            }
            if (suppliers.length > 0) {
                supplierIndex = (supplierIndex - 1 + suppliers.length) % suppliers.length;
                showSupplierRows(suppliers[supplierIndex]);
            }
        });

        function updateCounts() {
            let green = 0, yellow = 0, red = 0;

            rows.forEach(row => {
                // Check if row is MIP stage
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                if (rowStage !== 'mip') return;

                // Only count visible rows (filtered by play button or other filters)
                if (row.style.display === "none") return;

                // Check created_at (O Date) field for text color
                const dateInput = row.querySelector('input[data-column="created_at"]');
                if (!dateInput) {
                    green++; // Default to green if no date
                    return;
                }

                // Check inline style first (more reliable)
                const inlineStyle = dateInput.getAttribute('style') || '';
                const inlineStyleLower = inlineStyle.toLowerCase();
                
                // Also check the span element if it exists
                const dateSpan = dateInput.parentElement.querySelector('span');
                const spanStyle = dateSpan ? (dateSpan.getAttribute('style') || '') : '';
                const spanStyleLower = spanStyle.toLowerCase();

                // Check for red color in inline style
                if (inlineStyleLower.includes('color: red') || inlineStyleLower.includes('color:red') || 
                    inlineStyleLower.includes('color: #dc3545') || inlineStyleLower.includes('color:#dc3545') ||
                    spanStyleLower.includes('color: red') || spanStyleLower.includes('color:red') ||
                    spanStyleLower.includes('color: #dc3545') || spanStyleLower.includes('color:#dc3545')) {
                    red++;
                }
                // Check for yellow color in inline style
                else if (inlineStyleLower.includes('color: #ffc107') || inlineStyleLower.includes('color:#ffc107') ||
                         spanStyleLower.includes('color: #ffc107') || spanStyleLower.includes('color:#ffc107')) {
                    yellow++;
                }
                // Default to green (black or no color specified)
                else {
                    green++;
                }
            });

            const greenSpan = document.getElementById("greenCount");
            const yellowSpan = document.getElementById("yellowCount");
            const redSpan = document.getElementById("redCount");
            if (greenSpan) greenSpan.textContent = `(${green})`;
            if (yellowSpan) yellowSpan.textContent = `(${yellow})`;
            if (redSpan) redSpan.textContent = `(${red})`;
        }

        function filterDateRows(type) {
            rows.forEach(row => {
                // Check if row is MIP stage first
                const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
                const stageSelect = row.querySelector('.editable-select-stage');
                const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
                const rowStage = rowStageSelect || rowStageAttr;
                
                if (rowStage !== 'mip') {
                    row.style.display = "none";
                    return;
                }

                if (!type) {
                    // Show all MIP rows if no filter selected
                    row.style.display = "";
                    return;
                }

                // Check created_at (O Date) field for text color
                const dateInput = row.querySelector('input[data-column="created_at"]');
                if (!dateInput) {
                    // If no date input, default to green
                    row.style.display = (type === "green") ? "" : "none";
                    return;
                }

                // Check inline style first (more reliable)
                const inlineStyle = dateInput.getAttribute('style') || '';
                const inlineStyleLower = inlineStyle.toLowerCase();
                
                // Also check the span element if it exists
                const dateSpan = dateInput.parentElement.querySelector('span');
                const spanStyle = dateSpan ? (dateSpan.getAttribute('style') || '') : '';
                const spanStyleLower = spanStyle.toLowerCase();

                let rowColor = "green"; // Default to green
                
                // Check for red color in inline style (color: red; or color:#dc3545;)
                if (inlineStyleLower.includes('color: red') || inlineStyleLower.includes('color:red') || 
                    inlineStyleLower.includes('color: #dc3545') || inlineStyleLower.includes('color:#dc3545') ||
                    spanStyleLower.includes('color: red') || spanStyleLower.includes('color:red') ||
                    spanStyleLower.includes('color: #dc3545') || spanStyleLower.includes('color:#dc3545')) {
                    rowColor = "red";
                }
                // Check for yellow color in inline style (color: #ffc107;)
                else if (inlineStyleLower.includes('color: #ffc107') || inlineStyleLower.includes('color:#ffc107') ||
                         spanStyleLower.includes('color: #ffc107') || spanStyleLower.includes('color:#ffc107')) {
                    rowColor = "yellow";
                }
                // If no color specified or black/default, it's green
                else {
                    rowColor = "green";
                }

                row.style.display = (rowColor === type) ? "" : "none";
            });

            calculateTotalCBM();
            calculateTotalAmount();
            calculateTotalOrderItems();
            updateFollowSupplierCount();
            updateCounts(); // Update pending status counts when filtering by date
        }

        updateCounts();
        updateFollowSupplierCount();

        // Supplier Remarks Functionality
        let currentSupplierForRemarks = '';

        // Function to get supplier remarks from local storage
        function getSupplierRemarks(supplier) {
            if (!supplier) return [];
            const remarksKey = `supplier_remarks_${supplier}`;
            const remarks = localStorage.getItem(remarksKey);
            return remarks ? JSON.parse(remarks) : [];
        }

        // Function to save supplier remark
        function saveSupplierRemark(supplier, remark) {
            if (!supplier || !remark.trim()) {
                alert('Please enter a remark.');
                return;
            }

            const remarksKey = `supplier_remarks_${supplier}`;
            const remarks = getSupplierRemarks(supplier);
            
            const newRemark = {
                id: Date.now(),
                text: remark.trim(),
                timestamp: new Date().toLocaleString()
            };
            
            remarks.unshift(newRemark); // Add to beginning
            localStorage.setItem(remarksKey, JSON.stringify(remarks));
            
            // Clear input
            document.getElementById('supplier-remark-input').value = '';
            
            // Reload remarks
            loadSupplierRemarks(supplier);
            
            alert('Remark saved successfully!');
        }

        // Function to load and display supplier remarks
        function loadSupplierRemarks(supplier) {
            const remarksList = document.getElementById('remarks-list');
            if (!remarksList) return;

            const remarks = getSupplierRemarks(supplier);
            
            if (remarks.length === 0) {
                remarksList.innerHTML = '<p class="text-muted">No remarks saved yet.</p>';
                return;
            }

            let html = '<div class="list-group">';
            remarks.forEach(remark => {
                html += `
                    <div class="list-group-item mb-2" style="border-left: 4px solid #28a745;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <p class="mb-1">${remark.text}</p>
                                <small class="text-muted">${remark.timestamp}</small>
                            </div>
                            <button class="btn btn-sm btn-outline-danger delete-remark-btn" data-id="${remark.id}" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            remarksList.innerHTML = html;

            // Add delete event listeners
            remarksList.querySelectorAll('.delete-remark-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to delete this remark?')) {
                        deleteSupplierRemark(supplier, parseInt(this.dataset.id));
                    }
                });
            });
        }

        // Function to delete supplier remark
        function deleteSupplierRemark(supplier, remarkId) {
            const remarksKey = `supplier_remarks_${supplier}`;
            const remarks = getSupplierRemarks(supplier);
            const filteredRemarks = remarks.filter(r => r.id !== remarkId);
            localStorage.setItem(remarksKey, JSON.stringify(filteredRemarks));
            loadSupplierRemarks(supplier);
        }

        // Open remarks modal button
        document.getElementById('supplier-remarks-btn').addEventListener('click', function() {
            const supplierBadge = document.getElementById('current-supplier');
            const currentSupplier = supplierBadge ? supplierBadge.textContent.trim() : '';
            
            if (!currentSupplier || currentSupplier === '-') {
                alert('Please select a supplier first using the play button.');
                return;
            }

            currentSupplierForRemarks = currentSupplier;
            const modalSupplierName = document.getElementById('modal-supplier-name');
            if (modalSupplierName) {
                modalSupplierName.textContent = currentSupplier;
            }
            
            loadSupplierRemarks(currentSupplier);
            
            // Show modal (using Bootstrap)
            const modalElement = document.getElementById('supplierRemarksModal');
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        });

        // Save remark button
        document.getElementById('save-remark-btn').addEventListener('click', function() {
            const remarkText = document.getElementById('supplier-remark-input').value.trim();
            if (currentSupplierForRemarks) {
                saveSupplierRemark(currentSupplierForRemarks, remarkText);
            } else {
                alert('Please select a supplier first.');
            }
        });

        // Update remarks when supplier changes (if modal is open)
        const supplierRemarksModal = document.getElementById('supplierRemarksModal');
        if (supplierRemarksModal) {
            supplierRemarksModal.addEventListener('show.bs.modal', function() {
                const supplierBadge = document.getElementById('current-supplier');
                const currentSupplier = supplierBadge ? supplierBadge.textContent.trim() : '';
                if (currentSupplier && currentSupplier !== '-') {
                    currentSupplierForRemarks = currentSupplier;
                    loadSupplierRemarks(currentSupplier);
                }
            });
        }
    });

    function calculateTotalAmount() {
        let totalAmount = 0;

        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            // Check stage from data attribute first (more reliable)
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            // Only count MIP stage rows
            if (rowStage !== 'mip') {
                return;
            }
            
            if (row.style.display !== "none") { 
                const td = row.querySelector('.total-value');
                if (td) {
                    const value = parseFloat(td.textContent.trim());
                    if (!isNaN(value)) {
                        totalAmount += value;
                    }
                }
            }
        });

        document.getElementById('total-amount').textContent = '$' + totalAmount.toFixed(0);
    }

    function calculateTotalOrderItems() {
        let totalItems = 0;
        document.querySelectorAll('table.wide-table tbody tr').forEach(row => {
            // Check stage from data attribute first (more reliable)
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            // Only count MIP stage rows
            if (rowStage !== 'mip') {
                return;
            }
            
            // Only count visible rows
            if (row.style.display !== "none") {
                totalItems++;
            }
        });

        document.getElementById('total-order-items').textContent = totalItems;
    }

    // Function to update Follow Supplier count (defined globally)
    function updateFollowSupplierCount() {
        const followSupplierSpan = document.getElementById("followSupplierCount");
        if (!followSupplierSpan) return;
        
        const supplierSet = new Set();
        const allRows = document.querySelectorAll("table.wide-table tbody tr");
        
        allRows.forEach(row => {
            // Check stage from data attribute first (more reliable)
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            // Only count MIP stage rows
            if (rowStage !== 'mip') {
                return;
            }
            
            // Get Order Qty (check all MIP rows, not just visible ones)
            const qtyCell = row.querySelector('td[data-column="4"]');
            let qty = 0;
            if (qtyCell) {
                const qtyInput = qtyCell.querySelector('input');
                if (qtyInput) {
                    qty = parseFloat(qtyInput.value) || 0;
                } else {
                    qty = parseFloat(qtyCell.textContent.trim()) || parseFloat(qtyCell.getAttribute('data-qty')) || 0;
                }
            }
            
            // Only count suppliers with order qty > 0
            if (qty > 0) {
                const supplierCell = row.querySelector('td[data-column="6"]');
                if (supplierCell) {
                    const supplierSelect = supplierCell.querySelector('select[data-column="supplier"]');
                    if (supplierSelect) {
                        const supplierName = supplierSelect.value.trim();
                        // Count suppliers that have a value and it's not empty/default
                        if (supplierName && supplierName !== '' && supplierName !== 'supplier') {
                            supplierSet.add(supplierName);
                        }
                    }
                }
            }
        });
        
        followSupplierSpan.textContent = supplierSet.size;
    }

    // Filter to show only MIP stage on page load
    function filterByMIPStageOnLoad() {
        const rows = document.querySelectorAll('table.wide-table tbody tr.stage-row');
        rows.forEach(row => {
            const rowStageAttr = row.getAttribute('data-stage') ? row.getAttribute('data-stage').toLowerCase().trim() : '';
            const stageSelect = row.querySelector('.editable-select-stage');
            const rowStageSelect = stageSelect ? stageSelect.value.toLowerCase().trim() : '';
            const rowStage = rowStageSelect || rowStageAttr;
            
            if (rowStage === 'mip') {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        // Sort visible MIP rows by order date (oldest first)
        const visibleMipRows = Array.from(rows).filter(r => r.style.display !== 'none');
        if (visibleMipRows.length > 0 && typeof sortRowsByOrderDate === 'function') {
            sortRowsByOrderDate(visibleMipRows);
        }
        calculateTotalCBM();
        calculateTotalAmount();
        calculateTotalOrderItems();
        updateFollowSupplierCount();
    }

    function exportMipSummaryToExcel() {
        const rows = document.querySelectorAll('table.wide-table tbody tr');
        const exportData = [];
        rows.forEach(row => {
            if (row.style.display === 'none') return;
            const getCellText = (col) => {
                const cell = row.querySelector(`td[data-column="${col}"]`);
                if (!cell) return '';
                if (col === '3') {
                    const st = cell.querySelector('.mip-sku-text');
                    if (st) return (st.textContent || '').trim();
                    const ds = row.getAttribute('data-sku');
                    if (ds) return ds.trim();
                }
                const input = cell.querySelector('input, select');
                if (input) return (input.value || '').trim();
                return (cell.textContent || '').trim();
            };
            const getCellVal = (col) => {
                const cell = row.querySelector(`td[data-column="${col}"]`);
                if (!cell) return '';
                const input = cell.querySelector('input');
                return input ? (input.value || '').trim() : (cell.textContent || '').trim();
            };
            const getImageUrl = (col) => {
                const cell = row.querySelector(`td[data-column="${col}"]`);
                if (!cell) return '';
                const img = cell.querySelector('img');
                let url = img ? (img.dataset.src || img.src || '').trim() : '';
                if (url && !url.startsWith('http') && !url.startsWith('//')) {
                    url = (url.startsWith('/') ? window.location.origin : window.location.origin + '/') + url;
                }
                return url;
            };
            const orderDateStr = getCellVal('10');
            let daysDiff = '';
            let orderDateFormatted = '';
            if (orderDateStr) {
                const orderDate = new Date(orderDateStr);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                orderDate.setHours(0, 0, 0, 0);
                daysDiff = Math.floor((today - orderDate) / (1000 * 60 * 60 * 24));
                const d = String(orderDate.getDate()).padStart(2, '0');
                const m = String(orderDate.getMonth() + 1).padStart(2, '0');
                const y = String(orderDate.getFullYear()).slice(-2);
                orderDateFormatted = d + '-' + m + '-' + y;
            }
            exportData.push({
                'SKU': getCellText('3'),
                'Image': getImageUrl('1'),
                'QTY': getCellVal('4'),
                'O Date': orderDateFormatted,
                'Days': daysDiff !== '' ? daysDiff : ''
            });
        });
        if (exportData.length === 0) {
            alert('No data to export.');
            return;
        }
        try {
            const supplierBadge = document.getElementById('supplier-badge-container');
            const supplierEl = document.getElementById('current-supplier');
            const supplierName = (supplierBadge && supplierBadge.style.display !== 'none' && supplierEl) ? supplierEl.textContent.trim() : 'All supplier';
            const itemsCount = exportData.length;

            const headerRows = [
                ['Supplier', supplierName],
                ['Items', itemsCount],
                [],
                ['SKU', 'Image', 'QTY', 'O Date', 'Days']
            ];
            const dataRows = exportData.map(r => [r['SKU'], r['Image'], r['QTY'], r['O Date'], r['Days']]);
            const aoa = [...headerRows, ...dataRows];

            const ws = XLSX.utils.aoa_to_sheet(aoa);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'MIP Summary');
            const dateStr = new Date().toISOString().split('T')[0];

            const imageUrls = exportData.map(r => r['Image']).filter(url => url);
            if (imageUrls.length > 0) {
                const urlsJson = JSON.stringify([...new Set(imageUrls)]);
                const html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>MIP Summary ' + dateStr + '</title><style>body{font-family:Arial,sans-serif;padding:20px;}table{border-collapse:collapse;width:100%;margin-top:20px;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background:#3bc0c3;color:#fff;}</style></head><body><h2>MIP Summary - ' + dateStr + '</h2><p><strong>Supplier:</strong> ' + supplierName.replace(/&/g,'&amp;').replace(/</g,'&lt;') + ' | <strong>Items:</strong> ' + itemsCount + '</p><table><thead><tr><th>SKU</th><th>Image</th><th>QTY</th><th>O Date</th><th>Days</th></tr></thead><tbody>' + exportData.map(r => '<tr><td>' + (r['SKU']||'').replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</td><td>' + (r['Image'] ? '<a href="' + r['Image'].replace(/"/g,'&quot;') + '" target="_blank">View</a>' : '') + '</td><td>' + (r['QTY']||'') + '</td><td>' + (r['O Date']||'') + '</td><td>' + (r['Days']||'') + '</td></tr>').join('') + '</tbody></table><script>var urls=' + urlsJson + ';var i=0;function openNext(){if(i<urls.length){window.open(urls[i],"_blank");i++;setTimeout(openNext,800);}}setTimeout(openNext,1000);<\/script></body></html>';
                const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'MIP_Summary_' + dateStr + '.html';
                a.click();
                URL.revokeObjectURL(url);
            }
            XLSX.writeFile(wb, 'MIP_Summary_' + dateStr + '.xlsx');
        } catch (e) {
            alert('Export failed: ' + (e.message || e));
        }
    }

    // Initialize filter on page load
    filterByMIPStageOnLoad();
    

</script>

@endsection