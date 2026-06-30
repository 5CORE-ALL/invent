@extends('layouts.vertical', ['title' => 'Comparison'])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .tabulator {
        font-size: 13px;
        border: 1px solid #dee2e6;
    }

    .tabulator .tabulator-header {
        background: linear-gradient(90deg, #e0e7ff 0%, #f4f7fa 100%);
        border-bottom: 2px solid #2563eb;
        font-weight: 600;
    }

    .tabulator .tabulator-header .tabulator-col {
        border-right: 1px solid #e5e7eb;
    }

    .tabulator-row {
        min-height: 35px !important;
    }

    .tabulator-row:hover {
        background-color: #f8f9fa !important;
    }

    .tabulator-cell {
        padding: 8px !important;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
        padding: 8px 16px;
        margin: 0 4px;
        border-radius: 6px;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
        background: #e0eaff;
        color: #2563eb;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
        background: #2563eb;
        color: white;
    }

    .comparison-cd-btn {
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .comparison-cd-btn i {
        transition: transform 0.15s ease, color 0.15s ease;
    }

    .tabulator-cell[tabulator-field="cd_view"] {
        cursor: pointer;
    }

    .tabulator-cell[tabulator-field="cd_edit"] {
        cursor: default;
    }

    .comparison-cd-cell {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 28px;
        cursor: pointer;
    }

    .comparison-cd-btn:not(:disabled):hover i {
        color: #1d4ed8 !important;
        transform: scale(1.08);
    }

    .comparison-clink-dot {
        display: inline-block;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background-color: #1e40af;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
    }

    .comparison-clink-dot-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        min-height: 28px;
        padding: 4px 8px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        text-decoration: none;
        line-height: 1;
    }

    .comparison-clink-dot-link:hover .comparison-clink-dot {
        background-color: #1e3a8a;
    }

    .comparison-clink-dot-muted {
        background-color: #94a3b8;
    }

    .comparison-clink-dot-empty:hover .comparison-clink-dot,
    .comparison-clink-dot-link:hover .comparison-clink-dot-muted {
        background-color: #64748b;
    }

    .comparison-cd-clink-url-wrap {
        display: flex;
        align-items: center;
        gap: 6px;
        min-height: 31px;
        position: relative;
    }

    .comparison-cd-clink-url-wrap .comparison-cd-clink-url-input {
        width: 0;
        min-width: 0;
        max-width: 0;
        padding: 0;
        margin: 0;
        border: 0;
        opacity: 0;
        pointer-events: none;
        transition: max-width 0.15s ease, opacity 0.15s ease;
    }

    .comparison-cd-clink-url-wrap.is-editing .comparison-cd-clink-url-input {
        width: auto;
        min-width: 200px;
        max-width: 320px;
        padding: 0.25rem 0.5rem;
        border: 1px solid #ced4da;
        opacity: 1;
        pointer-events: auto;
    }

    .comparison-cd-clink-url-edit-btn {
        line-height: 1;
        padding: 2px 6px;
    }

    .comparison-company-dot {
        display: inline-block;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background-color: #16a34a;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
        cursor: help;
    }

    #cd-hover-preview {
        position: fixed;
        z-index: 1080;
        max-width: 320px;
        padding: 10px 12px;
        background: #1a2942;
        color: #fff;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        font-size: 12px;
        line-height: 1.5;
        pointer-events: none;
        display: none;
    }

    #cd-hover-preview .cd-hover-label {
        color: #93c5fd;
        font-weight: 600;
    }

    .comparison-history-btn {
        line-height: 1.2;
        max-width: 100%;
    }

    .comparison-history-table {
        font-size: 12px;
    }

    .comparison-history-table th,
    .comparison-history-table td {
        padding: 6px 10px !important;
        vertical-align: middle;
    }

    .comparison-history-table .ch-change {
        word-break: break-word;
    }

    .comparison-history-table .ch-when {
        white-space: nowrap;
        color: #6c757d;
    }

    .tabulator .tabulator-cell.linked-sku-col {
        padding-top: 4px !important;
        padding-bottom: 4px !important;
    }

    .tabulator .tabulator-cell.linked-sku-col .linked-sku-badge:hover {
        background-color: #cffafe !important;
    }

    .linked-sku-badge-wrap {
        display: inline-flex;
        align-items: center;
        gap: 2px;
    }

    .linked-sku-badge-wrap .comparison-linked-sku-remove {
        font-size: 0.55rem;
        opacity: 0.65;
        padding: 0;
        margin-left: 2px;
    }

    .linked-sku-badge-wrap .comparison-linked-sku-remove:hover {
        opacity: 1;
    }

    .comparison-category-cell {
        width: 100%;
        min-height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 2px 4px;
        border-radius: 4px;
    }

    .comparison-category-cell:hover {
        background: #f1f5f9;
    }

    .comparison-category-dropdown {
        position: fixed;
        z-index: 2000;
        background: #fff;
        border: 1px solid #ced4da;
        border-radius: 6px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        min-width: 240px;
        max-width: 340px;
    }

    .comparison-category-dropdown .dropdown-search-input {
        width: 100%;
        border: none;
        border-bottom: 1px solid #e5e7eb;
        border-radius: 6px 6px 0 0;
        padding: 8px 10px;
        font-size: 13px;
        outline: none;
    }

    .comparison-category-dropdown .dropdown-search-results {
        max-height: 240px;
        overflow-y: auto;
    }

    .comparison-category-dropdown .dropdown-search-item {
        padding: 8px 10px;
        cursor: pointer;
        font-size: 13px;
    }

    .comparison-category-dropdown .dropdown-search-item:hover,
    .comparison-category-dropdown .dropdown-search-item.active {
        background: #e0e7ff;
    }

    .comparison-category-dropdown .dropdown-search-item.no-results {
        cursor: default;
        color: #6c757d;
    }

    .comparison-category-dropdown .dropdown-search-item.clear-option {
        border-bottom: 1px solid #e5e7eb;
        color: #64748b;
        font-style: italic;
    }

    #comparison-cd-modal-sku-badge {
        font-size: 0.85rem;
        font-weight: 600;
        vertical-align: middle;
    }

    #comparisonCdModal .modal-dialog {
        max-width: 96vw;
    }

    #comparisonCdModal .modal-body {
        min-height: 70vh;
    }

    .cd-sheet-toolbar {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 10px 12px;
    }

    .cd-sheet-wrap {
        overflow: auto;
        max-height: 62vh;
        border: 1px solid #ced4da;
        border-radius: 6px;
        background: #fff;
    }

    .cd-sheet-table {
        border-collapse: collapse;
        min-width: 100%;
        font-size: 12px;
    }

    .cd-sheet-table th,
    .cd-sheet-table td {
        border: 1px solid #d0d7de;
        min-width: 110px;
        max-width: 260px;
        vertical-align: middle;
        text-align: center;
        padding: 0;
        height: 1px;
    }

    .cd-sheet-table th {
        background: #f3f4f6;
        color: #374151;
        font-weight: 600;
        text-align: center;
        padding: 6px 4px;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    .cd-sheet-table .cd-row-num {
        min-width: 42px;
        max-width: 42px;
        background: #f3f4f6;
        color: #6b7280;
        text-align: center;
        font-weight: 600;
        position: sticky;
        left: 0;
        z-index: 1;
        cursor: pointer;
        user-select: none;
    }

    .cd-sheet-table .cd-row-num:hover,
    .cd-sheet-table .cd-col-header:hover {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .cd-sheet-table .cd-row-num.cd-axis-selected,
    .cd-sheet-table .cd-col-header.cd-axis-selected {
        background: #2563eb;
        color: #fff;
    }

    .cd-sheet-table tr.cd-row-selected td:not([style*="background-color"]) {
        background-color: #eff6ff;
    }

    .cd-sheet-table td.cd-col-selected:not([style*="background-color"]) {
        background-color: #fef3c7;
    }

    .cd-sheet-table tr.cd-row-selected td.cd-col-selected:not([style*="background-color"]) {
        background-color: #dbeafe;
    }

    .cd-sheet-table td.cd-cell-selected {
        outline: 2px solid #2563eb;
        outline-offset: -2px;
    }

    .cd-sheet-table .cd-col-header {
        cursor: pointer;
        user-select: none;
    }

    .cd-sheet-table .cd-sheet-cell {
        padding: 4px 6px;
        outline: none;
        white-space: pre-wrap;
        word-break: break-word;
        text-align: center;
        line-height: 1.3;
        min-height: 0;
    }

    .cd-sheet-table .cd-sheet-cell-empty {
        padding: 2px 4px;
    }

    .cd-sheet-table .cd-sheet-cell-image {
        padding: 2px;
        text-align: center;
        line-height: 0;
    }

    .cd-sheet-table .cd-sheet-cell-link {
        padding: 2px 4px;
        text-align: center;
        line-height: 0;
    }

    .cd-sheet-table .cd-sheet-cell-company {
        padding: 2px 4px;
        text-align: center;
        line-height: 0;
    }

    .cd-sheet-table .cd-sheet-comm-cell {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        background: #b2ebf2;
        padding: 4px;
    }

    .cd-sheet-table .cd-sheet-comm-btn {
        border: 0;
        background: transparent;
        color: #111827;
        font-size: 18px;
        line-height: 1;
        padding: 2px 6px;
        cursor: pointer;
        border-radius: 4px;
    }

    .cd-sheet-table .cd-sheet-comm-btn:hover {
        background: rgba(255, 255, 255, 0.45);
        transform: scale(1.05);
    }

    .cd-sheet-table tr.cd-comm-row td:not(.cd-label-cell):not(.cd-row-num) {
        background: #e0f7fa;
    }

    .comparison-comm-plat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 10px;
    }

    .comparison-comm-plat-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
        text-align: center;
        text-decoration: none;
        color: inherit;
        transition: box-shadow 0.15s, transform 0.15s;
    }

    .comparison-comm-plat-card:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        transform: translateY(-1px);
        color: inherit;
    }

    .comparison-comm-plat-card i {
        font-size: 1.35rem;
        display: block;
        margin-bottom: 6px;
    }

    .cd-sheet-table .cd-sheet-link-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 2px 6px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: transparent;
        text-decoration: none;
        line-height: 0;
    }

    .cd-sheet-table .cd-sheet-link-btn:hover .comparison-clink-dot {
        background-color: #1e3a8a;
    }

    .cd-sheet-table .cd-sheet-img {
        max-width: 120px;
        max-height: 80px;
        width: auto;
        height: auto;
        object-fit: contain;
        display: block;
        margin: 0 auto;
        pointer-events: none;
    }

    .cd-sheet-table .cd-sheet-cell:focus {
        box-shadow: inset 0 0 0 2px #2563eb;
        background: #eff6ff;
    }

    .cd-sheet-table .cd-label-cell:not([style*="background-color"]) {
        background: #fed7aa;
        color: #111827;
        font-weight: 700;
        text-align: center;
    }

    .comparison-roi-table thead th {
        background: #fde047;
        font-size: 12px;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
    }

    .comparison-roi-table tbody td {
        font-size: 12px;
        text-align: center;
        vertical-align: middle;
        padding: 4px 6px;
    }

    .comparison-roi-table .comparison-roi-channel {
        font-weight: 600;
        background: #fff;
        text-align: left;
    }

    .comparison-roi-table .comparison-roi-input-cell {
        background: #fdba74;
        padding: 2px;
    }

    .comparison-roi-table .comparison-roi-input-cell input {
        width: 100%;
        min-width: 58px;
        border: 1px solid #f97316;
        border-radius: 4px;
        padding: 2px 4px;
        font-size: 12px;
        text-align: center;
        background: #fff7ed;
    }

    .comparison-roi-table .comparison-roi-calc-cell {
        background: #e5e7eb;
        font-weight: 600;
    }

    .comparison-roi-table .comparison-roi-calc-cell.comparison-roi-tier-green {
        background: #86efac;
    }

    .comparison-roi-table .comparison-roi-calc-cell.comparison-roi-tier-red {
        background: #fca5a5;
    }

    .comparison-roi-table .comparison-roi-calc-cell.comparison-roi-tier-magenta {
        background: #f0abfc;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="roi"].comparison-roi-tier-green {
        background: #4ade80;
        color: #14532d;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="roi"].comparison-roi-tier-red {
        background: #f87171;
        color: #7f1d1d;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="roi"].comparison-roi-tier-magenta {
        background: #e879f9;
        color: #701a75;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="pPct"].comparison-roi-tier-green {
        background: #4ade80;
        color: #14532d;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="pPct"].comparison-roi-tier-red {
        background: #f87171;
        color: #7f1d1d;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="pPct"].comparison-roi-tier-magenta {
        background: #e879f9;
        color: #701a75;
    }

    .comparison-roi-table .comparison-roi-lmp-header-btn,
    .comparison-roi-table .comparison-roi-lmp-link {
        font-size: 12px;
        font-weight: 600;
        text-decoration: underline;
        color: #1d4ed8;
        vertical-align: baseline;
    }

    .comparison-roi-table .comparison-roi-lmp-header-btn:hover,
    .comparison-roi-table .comparison-roi-lmp-link:hover {
        color: #1e3a8a;
    }

    .comparison-roi-table .comparison-roi-lmp-cell {
        background: #fff;
        font-weight: 600;
    }

    #comparisonRoiModal.comparison-roi-modal-stacked {
        z-index: 1075;
    }

    .cd-sheet-table .cd-label-cell:focus {
        box-shadow: inset 0 0 0 2px #2563eb;
    }

    .cd-sheet-toolbar .form-control-color {
        width: 42px;
        height: 32px;
        padding: 2px;
    }

    .cd-sheet-toolbar .cd-sheet-fill-target-select {
        width: auto;
        min-width: 72px;
    }

    .cd-sheet-layout-menu .dropdown-header {
        font-size: 11px;
        padding: 4px 12px 2px;
    }

    .cd-sheet-layout-menu .dropdown-item {
        font-size: 12px;
        padding: 4px 12px;
    }

    .cd-sheet-status {
        font-size: 12px;
        color: #6c757d;
    }
</style>
@endsection

@section('content')
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'comparison'])
                        <h4 class="mb-0">
                            <i class="fas fa-balance-scale"></i> Comparison
                        </h4>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
                        <input type="text" id="comparison-search-parent" class="form-control form-control-sm" style="max-width: 220px;" placeholder="Search Parent...">
                        <input type="text" id="comparison-search-sku" class="form-control form-control-sm" style="max-width: 220px;" placeholder="Search SKU...">
                    </div>
                    <div id="comparison-table"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonLinkedSkuModal" tabindex="-1" aria-labelledby="comparisonLinkedSkuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="comparisonLinkedSkuModalLabel">Link Sku Purchase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Link another SKU to <strong id="comparison-linked-sku-source"></strong>. Both SKUs will show each other.</p>
                <label for="comparison-linked-sku-input" class="form-label mb-1">SKU to link</label>
                <input type="text" id="comparison-linked-sku-input" class="form-control" placeholder="Search or enter SKU..." autocomplete="off">
                <div id="comparison-linked-sku-suggestions" class="list-group mt-2 d-none" style="max-height: 180px; overflow-y: auto;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="comparison-linked-sku-save-btn">
                    <i class="mdi mdi-link"></i> Link SKU
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonCommModal" tabindex="-1" aria-labelledby="comparisonCommModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="comparisonCommModalLabel">
                    <i class="fas fa-comments"></i> Supplier Communication
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1"><strong id="comparison-comm-supplier-name"></strong></p>
                <p class="text-muted small mb-3" id="comparison-comm-supplier-company"></p>
                <div id="comparison-comm-platforms" class="comparison-comm-plat-grid"></div>
                <p class="text-muted small mb-0 d-none" id="comparison-comm-empty">No communication details on file for this supplier.</p>
            </div>
        </div>
    </div>
</div>

<div id="cd-hover-preview"></div>

<div class="modal fade" id="comparisonCdModal" tabindex="-1" aria-labelledby="comparisonCdModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-lg-down modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="comparisonCdModalLabel">
                    <i class="fas fa-balance-scale"></i> Comparison Data
                    <span class="badge bg-light text-dark ms-2" id="comparison-cd-modal-sku-badge"></span>
                    <span class="visually-hidden" id="comparison-cd-modal-sku"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="comparison-cd-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="cd-sheet-tab-btn" data-bs-toggle="tab" data-bs-target="#cd-sheet-tab-pane" type="button" role="tab">
                            <i class="fas fa-table"></i> Comparison Sheet
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="cd-lmp-tab-btn" data-bs-toggle="tab" data-bs-target="#cd-lmp-tab-pane" type="button" role="tab">
                            <i class="fas fa-shopping-cart"></i> LMP Competitors
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="cd-sheet-tab-pane" role="tabpanel">
                        <div class="cd-sheet-toolbar mb-3">
                            <div class="d-flex flex-wrap align-items-end gap-2">
                                <div>
                                    <label class="form-label small mb-1">C link Sheet URL</label>
                                    <div class="comparison-cd-clink-url-wrap" id="comparison-cd-google-url-wrap">
                                        <a id="comparison-cd-google-url-link" href="#" target="_blank" rel="noopener noreferrer"
                                            class="comparison-clink-dot-link comparison-clink-dot-empty"
                                            title="Set Google Sheet URL">
                                            <span class="comparison-clink-dot comparison-clink-dot-muted" id="comparison-cd-google-url-dot" aria-hidden="true"></span>
                                        </a>
                                        <input type="url" id="comparison-cd-google-url" class="form-control form-control-sm comparison-cd-clink-url-input"
                                            placeholder="Google Sheet URL" autocomplete="off" spellcheck="false">
                                        <button type="button" class="btn btn-sm btn-outline-secondary comparison-cd-clink-url-edit-btn"
                                            id="comparison-cd-google-url-edit-btn" title="Edit C link Sheet URL" aria-label="Edit C link Sheet URL">
                                            <i class="mdi mdi-pencil-outline"></i>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label small mb-1">Tab name</label>
                                    <input type="text" id="comparison-cd-google-tab" class="form-control form-control-sm" value="Sheet1">
                                </div>
                                <button type="button" class="btn btn-sm btn-success" id="comparison-cd-import-btn">
                                    <i class="fab fa-google"></i> Refresh
                                </button>
                                <button type="button" class="btn btn-sm btn-info text-white" id="comparison-cd-autopopulate-suppliers-btn" title="Add suppliers into blank columns from column D; update C-link preloaded names when they match supplier.list for this category">
                                    <i class="mdi mdi-account-multiple-plus"></i> Suppliers
                                </button>
                                <button type="button" class="btn btn-sm btn-warning text-dark" id="comparison-cd-roi-btn" title="Open cost calculator ROI from lowest supplier price column">
                                    <i class="mdi mdi-percent"></i> ROI%
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="comparison-cd-copy-specs-btn" title="Copy Spec column labels to memory and clipboard">
                                    <i class="mdi mdi-content-copy"></i> Copy Specs
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="comparison-cd-replace-specs-btn" title="Replace Spec column with the saved template from memory">
                                    <i class="mdi mdi-clipboard-arrow-down"></i> Replace Specs
                                </button>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" id="comparison-cd-layout-menu-btn"
                                        data-bs-toggle="dropdown" aria-expanded="false" title="Move, insert, or delete rows and columns">
                                        <i class="mdi mdi-table-edit"></i> Layout
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-sm cd-sheet-layout-menu" aria-labelledby="comparison-cd-layout-menu-btn">
                                        <li><h6 class="dropdown-header">Row</h6></li>
                                        <li>
                                            <button type="button" class="dropdown-item" id="comparison-cd-move-row-up-btn">
                                                <i class="mdi mdi-arrow-up"></i> Move up
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item" id="comparison-cd-move-row-down-btn">
                                                <i class="mdi mdi-arrow-down"></i> Move down
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item" id="comparison-cd-insert-row-btn">
                                                <i class="mdi mdi-table-row-plus-after"></i> Insert row
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item text-danger" id="comparison-cd-delete-row-btn">
                                                <i class="mdi mdi-table-row-remove"></i> Delete row
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><h6 class="dropdown-header">Column</h6></li>
                                        <li>
                                            <button type="button" class="dropdown-item" id="comparison-cd-move-col-left-btn">
                                                <i class="mdi mdi-arrow-left"></i> Move left
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item" id="comparison-cd-move-col-right-btn">
                                                <i class="mdi mdi-arrow-right"></i> Move right
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item" id="comparison-cd-insert-col-btn">
                                                <i class="mdi mdi-table-column-plus-after"></i> Insert column
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item text-danger" id="comparison-cd-delete-col-btn">
                                                <i class="mdi mdi-table-column-remove"></i> Delete column
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <label class="small mb-0 fw-semibold" for="comparison-cd-fill-color">Fill</label>
                                    <input type="color" id="comparison-cd-fill-color" class="form-control form-control-color" value="#f97316" title="Pick fill color">
                                    <select id="comparison-cd-fill-target" class="form-select form-select-sm cd-sheet-fill-target-select" title="Fill target">
                                        <option value="cell" selected>Cell</option>
                                        <option value="row">Row</option>
                                        <option value="col">Column</option>
                                    </select>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="comparison-cd-apply-fill-btn" title="Apply fill color to selected cell, row, or column">
                                        <i class="mdi mdi-format-color-fill"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="comparison-cd-clear-fill-btn" title="Clear fill color from selected cell, row, or column">
                                        <i class="mdi mdi-format-color-marker-cancel"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="cd-sheet-status mt-2 d-none" id="comparison-cd-sheet-status" aria-hidden="true"></div>
                        </div>
                        <div id="comparison-cd-sheet-loading" class="text-center py-4 d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 mb-0">Loading comparison sheet...</p>
                        </div>
                        <div class="cd-sheet-wrap" id="comparison-cd-sheet-wrap">
                            <table class="cd-sheet-table" id="comparison-cd-sheet-table">
                                <thead id="comparison-cd-sheet-head"></thead>
                                <tbody id="comparison-cd-sheet-body"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="cd-lmp-tab-pane" role="tabpanel">
                        <div id="comparison-cd-clink-wrap" class="mb-3 d-none">
                            <div class="fw-semibold mb-1">Comparison Link</div>
                            <a id="comparison-cd-clink-link" href="#" target="_blank" rel="noopener noreferrer" class="comparison-clink-dot-link">
                                <span class="comparison-clink-dot" aria-hidden="true"></span> Open link
                            </a>
                            <div id="comparison-cd-clink-text" class="small text-muted mt-1 text-break"></div>
                        </div>
                        <div id="comparison-cd-lmp-wrap">
                            <div class="card mb-3 border-success">
                                <div class="card-header bg-success text-white">
                                    <strong><i class="fa fa-plus-circle"></i> Add New Competitor</strong>
                                </div>
                                <div class="card-body">
                                    <form id="comparison-cd-lmp-add-form" class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label"><strong>SKU</strong></label>
                                            <input type="text" class="form-control" id="comparison-cd-add-comp-sku" readonly>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label"><strong>ASIN</strong> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="comparison-cd-add-comp-asin" placeholder="B07ABC123" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label"><strong>Price</strong> <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="comparison-cd-add-comp-price" placeholder="29.99" step="0.01" min="0.01" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label"><strong>Product Link</strong></label>
                                            <input type="url" class="form-control" id="comparison-cd-add-comp-link" placeholder="https://amazon.com/dp/...">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label"><strong>Marketplace</strong></label>
                                            <select class="form-select" id="comparison-cd-add-comp-marketplace">
                                                <option value="amazon" selected>Amazon</option>
                                                <option value="US">US</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fa fa-plus"></i> Add Competitor
                                            </button>
                                            <button type="reset" class="btn btn-secondary">
                                                <i class="fa fa-undo"></i> Clear
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div id="comparison-cd-lmp-list">
                                <div class="text-center py-4 text-muted">Open this tab to load LMP competitors.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonLmpModal" tabindex="-1" aria-labelledby="comparisonLmpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="comparisonLmpModalLabel">
                    <i class="fa fa-shopping-cart"></i> Competitors for SKU: <span id="comparison-lmp-modal-sku"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="comparison-lmp-add-wrap" class="card mb-3 border-success">
                    <div class="card-header bg-success text-white">
                        <strong><i class="fa fa-plus-circle"></i> Add New Competitor</strong>
                    </div>
                    <div class="card-body">
                        <form id="comparison-lmp-add-form" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label"><strong>SKU</strong></label>
                                <input type="text" class="form-control" id="comparison-lmp-add-comp-sku" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><strong>ASIN</strong> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="comparison-lmp-add-comp-asin" placeholder="B07ABC123" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><strong>Price</strong> <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="comparison-lmp-add-comp-price" placeholder="29.99" step="0.01" min="0.01" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><strong>Product Link</strong></label>
                                <input type="url" class="form-control" id="comparison-lmp-add-comp-link" placeholder="https://amazon.com/dp/...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><strong>Marketplace</strong></label>
                                <select class="form-select" id="comparison-lmp-add-comp-marketplace">
                                    <option value="amazon" selected>Amazon</option>
                                    <option value="US">US</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa fa-plus"></i> Add Competitor
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fa fa-undo"></i> Clear
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div id="comparison-lmp-data-list">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 mb-0">Loading competitors...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonHistoryModal" tabindex="-1" aria-labelledby="comparisonHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="comparisonHistoryModalLabel">
                    <i class="fas fa-history"></i> Change History — <span id="comparison-history-modal-sku"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="comparison-history-loading" class="text-center py-4">
                    <div class="spinner-border text-secondary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 mb-0">Loading history...</p>
                </div>
                <div id="comparison-history-empty" class="alert alert-info mb-0 d-none">
                    <i class="fa fa-info-circle"></i> No change history found for this SKU.
                </div>
                <div id="comparison-history-error" class="alert alert-danger mb-0 d-none"></div>
                <div id="comparison-history-table-wrap" class="table-responsive d-none" style="max-height: 65vh;">
                    <table class="table table-bordered table-hover comparison-history-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 130px;">Date</th>
                                <th style="width: 120px;">User</th>
                                <th style="width: 130px;">Field</th>
                                <th>Changes</th>
                            </tr>
                        </thead>
                        <tbody id="comparison-history-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const dataUrl = @json(route('comparison.data'));
    const historyUrl = @json(route('comparison.history'));
    const sheetGetUrl = @json(route('comparison.sheet.get'));
    const sheetSaveUrl = @json(route('comparison.sheet.save'));
    const sheetSyncClinkUrl = @json(route('comparison.sheet.sync-clink'));
    const suppliersForSkuUrl = @json(route('comparison.suppliers-for-sku'));
    const supplierListUrl = @json(route('supplier.list'));
    const competitorsUrl = @json(route('amazon.competitors.get'));
    const amazonLmpAddUrl = @json(route('amazon.lmp.add'));
    const amazonLmpDeleteUrl = @json(route('amazon.lmp.delete.post'));
    const ebayLmpDataUrl = @json(route('ebay.lmp.data'));
    const updateLinkUrl = @json(route('update.rfq.link'));
    const groupMasterCategoriesUrl = @json(route('group.master.categories'));
    const groupMasterUpdateFieldUrl = @json(route('group.master.update.field'));
    const groupMasterStoreCategoryUrl = @json(route('group.master.store.category'));
    const supplierCategoriesUrl = @json(route('supplier.categories.json'));
    const shippingSlabRateUrl = @json(route('comparison.shipping-slab-rate'));
    const lmpRatesUrl = @json(route('comparison.lmp-rates'));
    const roiSaveUrl = @json(route('comparison.roi.save-cell'));
    const linkedSkuAddUrl = @json(route('comparison.linked-skus.add'));
    const linkedSkuBulkLinkUrl = @json(route('comparison.linked-skus.bulk-link'));
    const linkedSkuRemoveUrl = @json(route('comparison.linked-skus.remove'));
    const cdHoverPreview = document.getElementById('cd-hover-preview');
    const cdModalEl = document.getElementById('comparisonCdModal');
    const cdModal = cdModalEl ? new bootstrap.Modal(cdModalEl) : null;
    const historyModalEl = document.getElementById('comparisonHistoryModal');
    const historyModal = historyModalEl ? new bootstrap.Modal(historyModalEl) : null;
    const lmpModalEl = document.getElementById('comparisonLmpModal');
    const lmpModal = lmpModalEl ? new bootstrap.Modal(lmpModalEl) : null;
    const linkedSkuModalEl = document.getElementById('comparisonLinkedSkuModal');
    const linkedSkuModal = linkedSkuModalEl ? new bootstrap.Modal(linkedSkuModalEl) : null;
    const commModalEl = document.getElementById('comparisonCommModal');
    const commModal = commModalEl ? new bootstrap.Modal(commModalEl) : null;

    let linkedSkuModalRow = null;
    let comparisonSuppliersByName = {};

    const COMM_PLAT_ICON = {
        Website: 'fas fa-globe',
        Email: 'fas fa-envelope',
        Phone: 'fas fa-phone',
        WhatsApp: 'fab fa-whatsapp',
        WeChat: 'fab fa-weixin',
        QQ: 'mdi mdi-qqchat',
        Alibaba: 'fas fa-store',
        '1688': 'fas fa-shopping-bag',
    };
    const COMM_PLAT_COLOR = {
        Website: '#2563eb',
        Email: '#dc3545',
        Phone: '#0d9488',
        WhatsApp: '#25d366',
        WeChat: '#09b83e',
        QQ: '#1565c0',
        Alibaba: '#ff6a00',
        '1688': '#e65100',
    };

    const ROI_CHANNELS = ['Amazon', 'Ebay'];
    const ROI_LMP_SALE_FACTOR = 0.9;
    const ROI_SALE_NET_FACTOR = 0.7;
    const ROI_FIELD_OFFSETS = {
        cp: 1,
        cbm: 2,
        freight: 3,
        gw: 4,
        shipping: 5,
        sale: 6,
        pPct: 7,
        profit: 8,
        roi: 9,
    };

    let currentCdRow = null;
    let comparisonBulkEditSkus = null;
    let currentSheetCells = [];
    let currentSheetFormats = { cells: {}, rows: {}, cols: {} };
    let selectedSheetRow = null;
    let selectedSheetCol = null;
    let selectedSheetCell = null;
    let lmpLoadedForSku = null;
    let currentAmazonLmpSku = null;
    let currentAmazonLmpListEl = null;
    let currentAmazonLmpFormPrefix = null;
    let table;

    const SPEC_COLUMN_COLOR = '#fed7aa';
    const LOWEST_PRICE_COLOR = '#bbf7d0';
    const SUPPLIER_NAME_ROW_COLOR = '#42c4f0';
    const FIRST_SUPPLIER_COLUMN = 3; // Column D
    let autoSheetFormats = { cells: {}, rows: {}, cols: {} };
    let sheetEditorHydrating = false;
    let sheetAutoSaveTimer = null;
    let sheetSaveInFlight = false;
    let sheetSaveQueued = false;
    let tableRefreshTimer = null;
    let copiedSpecLabels = [];
    const COPIED_SPECS_STORAGE_KEY = 'comparison_copied_spec_labels';
    let allProductCategories = [];
    let supplierCategoryOptions = [];
    let productCategoriesByName = {};
    let activeCategoryDropdown = null;
    let clinkPreloadedSupplierByCol = {};
    let clinkPreloadedSupplierNames = new Set();
    let roiCellEditPrevious = {};
    let roiSaveInFlight = false;

    function getCopiedSpecLabels() {
        if (copiedSpecLabels.length) {
            return copiedSpecLabels.slice();
        }
        try {
            const stored = sessionStorage.getItem(COPIED_SPECS_STORAGE_KEY);
            if (!stored) {
                return [];
            }
            const parsed = JSON.parse(stored);
            return Array.isArray(parsed) ? parsed.slice() : [];
        } catch (e) {
            return [];
        }
    }

    function saveCopiedSpecLabelsToMemory(labels) {
        copiedSpecLabels = Array.isArray(labels) ? labels.slice() : [];
        try {
            sessionStorage.setItem(COPIED_SPECS_STORAGE_KEY, JSON.stringify(copiedSpecLabels));
        } catch (e) {
            // sessionStorage unavailable — in-memory copy still works for this page session
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function escapeHtmlAttr(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function isGoogleSheetUrl(url) {
        return /^https?:\/\/(docs|sheets)\.google\.com\/spreadsheets/i.test(String(url || '').trim());
    }

    function linkedSkusForRow(row) {
        if (!row) {
            return [];
        }
        return Array.isArray(row.linked_skus) ? row.linked_skus.filter(Boolean) : [];
    }

    function comparisonBulkEditPayload() {
        if (!Array.isArray(comparisonBulkEditSkus) || comparisonBulkEditSkus.length <= 1) {
            return [];
        }
        return comparisonBulkEditSkus.filter(Boolean);
    }

    function getSelectedComparisonRows() {
        return table ? table.getSelectedRows() : [];
    }

    function clearComparisonRowSelection() {
        if (table) {
            table.deselectRow();
        }
    }

    function resolveComparisonBulkEditSkus(rowData, tabulatorRow) {
        const selected = getSelectedComparisonRows();
        const selectedSkus = selected.map(r => r.getData().sku).filter(Boolean);
        if (selectedSkus.length > 1 && tabulatorRow?.isSelected?.()) {
            return selectedSkus;
        }
        return null;
    }

    function openComparisonModalForEdit(rowData, tabulatorRow) {
        comparisonBulkEditSkus = resolveComparisonBulkEditSkus(rowData, tabulatorRow);
        openComparisonModal(rowData);
    }

    function buildSheetRequestParams(row) {
        const params = new URLSearchParams({ sku: row?.sku || '' });
        const linked = linkedSkusForRow(row);
        if (linked.length) {
            params.set('linked_skus', linked.join(','));
        }
        if (row?.parent) {
            params.set('parent', row.parent);
        }
        return params;
    }

    function buildCdHoverHtml(row) {
        const clink = (row.clink || '').trim();
        const clinkIsSheet = !!row.clink_is_sheet || isGoogleSheetUrl(clink);
        const lmpPrice = row.lmp_price;
        const count = parseInt(row.lmp_entries_total, 10) || 0;
        const hasSheet = !!row.has_sheet_data;
        const supplierCount = parseInt(row.sheet_supplier_count, 10) || 0;
        const sheetSku = row.sheet_sku || row.sku;

        let sheetLabel = 'No sheet saved';
        if (hasSheet) {
            sheetLabel = supplierCount + ' supplier column(s) saved';
            if (sheetSku && sheetSku !== row.sku) {
                sheetLabel += ` (shared from ${sheetSku})`;
            }
        } else if (clinkIsSheet) {
            sheetLabel = 'C link sheet ready — click to load';
        }

        let html = '';
        html += `<div><span class="cd-hover-label">Sheet:</span> ${sheetLabel}</div>`;
        const clinkSku = row.clink_sku && row.clink_sku !== row.sku ? row.clink_sku : '';
        const clinkLabel = clink
            ? (clinkSku ? `${escapeHtml(clink)} (shared from ${escapeHtml(clinkSku)})` : escapeHtml(clink))
            : '—';
        html += `<div><span class="cd-hover-label">C link:</span> ${clinkLabel}</div>`;
        html += `<div><span class="cd-hover-label">LMP:</span> ${lmpPrice ? '$' + parseFloat(lmpPrice).toFixed(2) : 'N/A'}</div>`;
        html += `<div><span class="cd-hover-label">Competitors:</span> ${count}</div>`;
        html += `<div class="mt-1 text-white-50">Click to view and edit</div>`;
        return html;
    }

    function showCdHover(event, row) {
        if (!cdHoverPreview) return;
        cdHoverPreview.innerHTML = buildCdHoverHtml(row);
        cdHoverPreview.style.display = 'block';
        positionCdHover(event);
    }

    function positionCdHover(event) {
        if (!cdHoverPreview) return;
        const offset = 12;
        let left = event.clientX + offset;
        let top = event.clientY + offset;
        const rect = cdHoverPreview.getBoundingClientRect();
        if (left + rect.width > window.innerWidth - 8) {
            left = event.clientX - rect.width - offset;
        }
        if (top + rect.height > window.innerHeight - 8) {
            top = event.clientY - rect.height - offset;
        }
        cdHoverPreview.style.left = `${Math.max(8, left)}px`;
        cdHoverPreview.style.top = `${Math.max(8, top)}px`;
    }

    function hideCdHover() {
        if (cdHoverPreview) {
            cdHoverPreview.style.display = 'none';
        }
    }

    function isCompanyNameRow(rowIndex, cells) {
        if (rowIndex === null || rowIndex === undefined || Number.isNaN(rowIndex)) {
            return false;
        }
        const specCol = detectSpecColumnIndex(cells || currentSheetCells);
        const label = String(((cells || currentSheetCells)[rowIndex] || [])[specCol] || '').trim().toLowerCase();
        return label.includes('company name');
    }

    function isCommRow(rowIndex, cells) {
        if (rowIndex === null || rowIndex === undefined || Number.isNaN(rowIndex)) {
            return false;
        }
        const specCol = detectSpecColumnIndex(cells || currentSheetCells);
        const label = String(((cells || currentSheetCells)[rowIndex] || [])[specCol] || '').trim().toLowerCase();
        return label === 'comm' || label.includes('communication');
    }

    function isCommDataCell(rowIndex, colIndex) {
        if (isSheetSpecColumn(colIndex) || !isCommRow(rowIndex, currentSheetCells)) {
            return false;
        }
        return true;
    }

    function getSupplierNameForColumn(colIndex, cells) {
        const sheetCells = cells || currentSheetCells;
        const specCol = detectSpecColumnIndex(sheetCells);
        const supplierRowIndex = findSupplierNameRowIndex(sheetCells, specCol);
        if (supplierRowIndex === null) {
            return '';
        }
        return String((sheetCells[supplierRowIndex] || [])[colIndex] || '').trim();
    }

    function cacheComparisonSuppliers(suppliers) {
        comparisonSuppliersByName = {};
        (suppliers || []).forEach(function (supplier) {
            const key = normalizeSupplierNameKey(supplier?.name);
            if (key) {
                comparisonSuppliersByName[key] = supplier;
            }
        });
    }

    function loadComparisonSuppliersForCategory(category) {
        const normalizedCategory = String(category || '').trim();
        if (!normalizedCategory || !currentCdRow?.sku) {
            return Promise.resolve([]);
        }

        const params = new URLSearchParams();
        params.set('sku', currentCdRow.sku || '');
        params.set('category', normalizedCategory);
        params.set('by_category', '1');

        return fetch(`${suppliersForSkuUrl}?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            const suppliers = data.success ? (data.suppliers || []) : [];
            cacheComparisonSuppliers(suppliers);
            return suppliers;
        })
        .catch(function () {
            return [];
        });
    }

    function commCellEditorHtml(rowIndex, colIndex, supplierName) {
        const name = String(supplierName || '').trim();
        if (!name) {
            return `<div class="cd-sheet-comm-cell cd-sheet-comm-cell-empty"><span class="text-muted">-</span></div>`;
        }
        return `<div class="cd-sheet-comm-cell">
            <button type="button" class="cd-sheet-comm-btn" data-row="${rowIndex}" data-col="${colIndex}"
                data-supplier-name="${escapeHtmlAttr(name)}"
                title="Communication: ${escapeHtmlAttr(name)}" aria-label="Open communication details for ${escapeHtmlAttr(name)}">
                <i class="fas fa-comments"></i>
            </button>
        </div>`;
    }

    function openComparisonCommModal(supplierName) {
        if (!commModal) {
            return;
        }

        const name = String(supplierName || '').trim();
        let supplier = comparisonSuppliersByName[normalizeSupplierNameKey(name)] || null;

        if (!supplier && name) {
            const category = String(currentCdRow?.category || '').trim();
            if (category && Object.keys(comparisonSuppliersByName).length === 0) {
                loadComparisonSuppliersForCategory(category).then(function () {
                    openComparisonCommModal(supplierName);
                });
                return;
            }
        }

        const links = supplier?.platform_links || [];
        const nameEl = document.getElementById('comparison-comm-supplier-name');
        const companyEl = document.getElementById('comparison-comm-supplier-company');
        const gridEl = document.getElementById('comparison-comm-platforms');
        const emptyEl = document.getElementById('comparison-comm-empty');

        if (nameEl) {
            nameEl.textContent = name || 'Supplier';
        }
        if (companyEl) {
            const company = String(supplier?.company || '').trim();
            companyEl.textContent = company;
            companyEl.classList.toggle('d-none', !company);
        }

        if (!links.length) {
            if (gridEl) {
                gridEl.innerHTML = '';
                gridEl.classList.add('d-none');
            }
            emptyEl?.classList.remove('d-none');
        } else {
            emptyEl?.classList.add('d-none');
            if (gridEl) {
                gridEl.classList.remove('d-none');
                gridEl.innerHTML = links.map(function (link) {
                    const icon = COMM_PLAT_ICON[link.label] || 'fas fa-link';
                    const color = COMM_PLAT_COLOR[link.label] || '#6b7280';
                    const display = link.display || link.url || link.label;
                    const title = escapeHtmlAttr(link.label + (link.display ? ': ' + link.display : ''));
                    if (link.url) {
                        const ext = link.external ? ' target="_blank" rel="noopener noreferrer"' : '';
                        return `<a href="${escapeHtmlAttr(link.url)}" class="comparison-comm-plat-card"${ext} title="${title}">
                            <i class="${icon}" style="color:${color};"></i>
                            <div class="fw-semibold small">${escapeHtml(link.label)}</div>
                            <div class="text-muted small text-truncate">${escapeHtml(String(display))}</div>
                        </a>`;
                    }
                    return `<div class="comparison-comm-plat-card" title="${title}">
                        <i class="${icon}" style="color:${color};"></i>
                        <div class="fw-semibold small">${escapeHtml(link.label)}</div>
                        <div class="text-muted small text-truncate">${escapeHtml(String(display))}</div>
                    </div>`;
                }).join('');
            }
        }

        commModal.show();
    }

    function ensureCommRow(cells, specCol) {
        specCol = specCol ?? detectSpecColumnIndex(cells);
        let rowIndex = findRowIndexByLabel(cells, 'comm', specCol);
        if (rowIndex !== null) {
            return { cells, rowIndex };
        }

        const colCount = Math.max(...cells.map(row => row.length), 6);
        const newRow = Array.from({ length: colCount }, () => '');
        newRow[specCol] = 'Comm';

        let insertAt = 0;
        const supplierNameRow = findSupplierNameRowIndex(cells, specCol);
        if (supplierNameRow !== null) {
            insertAt = supplierNameRow + 1;
        } else {
            const companyRowIndex = findRowIndexByLabel(cells, 'company name', specCol);
            if (companyRowIndex !== null) {
                insertAt = companyRowIndex;
            } else {
                const supplierLinkRow = findRowIndexByLabel(cells, 'supplier link', specCol);
                if (supplierLinkRow !== null) {
                    insertAt = supplierLinkRow + 1;
                }
            }
        }

        const nextCells = cells.slice();
        nextCells.splice(insertAt, 0, newRow);

        return { cells: nextCells, rowIndex: insertAt };
    }

    function syncCommRowOnSheet() {
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const commEnsured = ensureCommRow(currentSheetCells, specCol);
        currentSheetCells = commEnsured.cells;
    }

    function isCompanyNameDataCell(rowIndex, colIndex, forceText) {
        if (forceText || isSheetSpecColumn(colIndex)) {
            return false;
        }
        return isCompanyNameRow(rowIndex, currentSheetCells);
    }

    function showSheetCellTooltip(event, text) {
        if (!cdHoverPreview || !text) return;
        cdHoverPreview.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');
        cdHoverPreview.style.display = 'block';
        positionCdHover(event);
    }

    function showComparisonToast(type, message) {
        const bg = (type === 'error' || type === 'danger') ? 'danger' : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'info'));
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '20000';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${bg} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHtml(message || '')}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        container.appendChild(toast);
        bootstrap.Toast.getOrCreateInstance(toast).show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    function amazonSellerTypeBadge(type) {
        if (!type) {
            return '<span class="text-muted">—</span>';
        }
        const normalized = String(type).toUpperCase();
        let cls = 'secondary';
        if (normalized === 'FBA') {
            cls = 'warning';
        } else if (normalized === 'AMZ') {
            cls = 'dark';
        }
        return `<span class="badge bg-${cls}">${escapeHtml(type)}</span>`;
    }

    function renderAmazonCompetitorsListHtml(competitors, lowestPrice) {
        if (!competitors || competitors.length === 0) {
            return '<div class="alert alert-info mb-0"><i class="fa fa-info-circle"></i> No competitors found for this SKU.</div>';
        }

        let html = '<div class="table-responsive"><table class="table table-hover table-bordered table-sm mb-0">';
        html += `<thead class="table-light"><tr>
            <th style="width:30px;">#</th>
            <th style="width:60px;">Image</th>
            <th style="width:100px;">ASIN</th>
            <th style="width:250px;">Product Title</th>
            <th>Seller</th>
            <th style="width:80px;">Price</th>
            <th style="width:90px;">Revenue<br><small>(30d)</small></th>
            <th style="width:70px;">Units<br><small>(30d)</small></th>
            <th style="width:100px;">Buy Box</th>
            <th style="width:60px;">Type</th>
            <th style="width:70px;">Rating</th>
            <th style="width:70px;">Reviews</th>
            <th style="width:140px;">Delivery</th>
            <th style="width:60px;">Link</th>
            <th style="width:80px;">Actions</th>
        </tr></thead><tbody>`;

        competitors.forEach(function (item, index) {
            const basePrice = parseFloat(item.price) || 0;
            let shipCost = 0;
            if (item.delivery) {
                const paidMatch = String(item.delivery).match(/\$\s*([\d,]+\.?\d*)\s*delivery/i);
                if (paidMatch) {
                    shipCost = parseFloat(paidMatch[1].replace(/,/g, '')) || 0;
                }
            }
            const totalPrice = basePrice + shipCost;
            const isLowest = lowestPrice != null && Math.abs(parseFloat(item.price) - parseFloat(lowestPrice)) < 0.01;
            const rowClass = isLowest ? 'table-success' : '';
            const totalFormatted = '$' + totalPrice.toFixed(2);
            const priceInner = shipCost > 0
                ? `${totalFormatted}<br><small style="color:#888;font-weight:400;">$${basePrice.toFixed(2)} + $${shipCost.toFixed(2)} ship</small>`
                : totalFormatted;
            const priceBadge = isLowest
                ? `<span class="badge bg-success">${priceInner} <i class="fa fa-trophy"></i></span>`
                : `<strong>${priceInner}</strong>`;

            const productLink = item.link || item.product_link || '#';
            const productTitle = item.title || item.product_title || 'N/A';
            const sellerName = item.seller_name || '—';
            const imageUrl = item.image || '';
            const imageHtml = imageUrl
                ? `<img src="${escapeHtmlAttr(imageUrl)}" style="width:50px;height:50px;object-fit:contain;" alt="">`
                : '<span style="color:#999;">—</span>';
            const revenue = item.monthly_revenue
                ? `<span style="color:#28a745;font-weight:600;">$${parseFloat(item.monthly_revenue).toFixed(0)}</span>`
                : '<span style="color:#999;">—</span>';
            const units = item.monthly_units_sold
                ? `<span style="color:#007bff;font-weight:600;">${parseInt(item.monthly_units_sold, 10)}</span>`
                : '<span style="color:#999;">—</span>';
            const buyBox = item.buy_box_owner
                ? `<span style="font-size:11px;">${escapeHtml(item.buy_box_owner)}</span>`
                : '<span style="color:#999;">—</span>';
            const rating = item.rating
                ? `<span style="color:#ffc107;">${parseFloat(item.rating).toFixed(1)} <i class="fa fa-star"></i></span>`
                : '<span style="color:#999;">—</span>';
            const reviews = item.reviews
                ? `<span>${parseInt(item.reviews, 10).toLocaleString()}</span>`
                : '<span style="color:#999;">—</span>';

            let deliveryHtml = '<span style="color:#999;">—</span>';
            if (item.delivery) {
                const isFree = /free/i.test(item.delivery);
                const paidMatch = String(item.delivery).match(/\$\s*([\d,]+\.?\d*)\s*delivery/i);
                if (paidMatch) {
                    deliveryHtml = `<span style="color:#dc3545;font-weight:600;" title="${escapeHtmlAttr(item.delivery)}">$${paidMatch[1]} ship</span>`;
                } else if (isFree) {
                    deliveryHtml = `<span style="color:#28a745;font-weight:600;" title="${escapeHtmlAttr(item.delivery)}">FREE</span>`;
                } else {
                    const deliveryText = String(item.delivery);
                    deliveryHtml = `<span style="font-size:10px;" title="${escapeHtmlAttr(deliveryText)}">${escapeHtml(deliveryText.substring(0, 22))}${deliveryText.length > 22 ? '…' : ''}</span>`;
                }
            }

            html += `<tr class="${rowClass}">
                <td class="text-center"><strong>${index + 1}</strong></td>
                <td class="text-center">${imageHtml}</td>
                <td><span class="text-primary fw-semibold" style="font-size:11px;">${escapeHtml(item.asin || 'N/A')}</span></td>
                <td style="font-size:11px;" title="${escapeHtmlAttr(productTitle)}">${escapeHtml(productTitle.length > 60 ? productTitle.substring(0, 60) + '…' : productTitle)}</td>
                <td style="font-size:11px;">${escapeHtml(sellerName)}</td>
                <td>${priceBadge}</td>
                <td class="text-center">${revenue}</td>
                <td class="text-center">${units}</td>
                <td style="font-size:11px;">${buyBox}</td>
                <td class="text-center">${amazonSellerTypeBadge(item.seller_type)}</td>
                <td class="text-center">${rating}</td>
                <td class="text-center">${reviews}</td>
                <td class="text-center">${deliveryHtml}</td>
                <td class="text-center">
                    <a href="${escapeHtmlAttr(productLink)}" target="_blank" rel="noopener" class="btn btn-sm btn-info" title="View Product on Amazon">
                        <i class="fa fa-external-link"></i>
                    </a>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger comparison-delete-lmp-btn"
                        data-id="${escapeHtmlAttr(String(item.id ?? ''))}"
                        data-asin="${escapeHtmlAttr(item.asin || '')}"
                        data-price="${escapeHtmlAttr(String(item.price ?? ''))}"
                        title="Delete this competitor">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        });

        html += '</tbody></table></div>';
        return html;
    }

    function renderCompetitorsList(competitors, lowestPrice) {
        return renderAmazonCompetitorsListHtml(competitors, lowestPrice);
    }

    function amazonLmpLoadingHtml() {
        return `<div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 mb-0">Loading competitors...</p>
        </div>`;
    }

    function fillAmazonLmpAddForm(prefix, sku) {
        const skuInput = document.getElementById(`${prefix}-add-comp-sku`);
        const asinInput = document.getElementById(`${prefix}-add-comp-asin`);
        const priceInput = document.getElementById(`${prefix}-add-comp-price`);
        const linkInput = document.getElementById(`${prefix}-add-comp-link`);
        const marketplaceInput = document.getElementById(`${prefix}-add-comp-marketplace`);
        if (skuInput) skuInput.value = sku || '';
        if (asinInput) asinInput.value = '';
        if (priceInput) priceInput.value = '';
        if (linkInput) linkInput.value = '';
        if (marketplaceInput) marketplaceInput.value = 'amazon';
    }

    function loadAmazonCompetitors(sku, listEl, fieldPrefix) {
        if (!sku || !listEl) {
            return Promise.resolve();
        }

        currentAmazonLmpSku = sku;
        currentAmazonLmpListEl = listEl;
        currentAmazonLmpFormPrefix = fieldPrefix || null;
        if (fieldPrefix) {
            fillAmazonLmpAddForm(fieldPrefix, sku);
        }

        listEl.innerHTML = amazonLmpLoadingHtml();

        return fetch(`${competitorsUrl}?sku=${encodeURIComponent(sku)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                listEl.innerHTML = renderAmazonCompetitorsListHtml(data.competitors || [], data.lowest_price);
            } else {
                listEl.innerHTML = '<div class="alert alert-warning mb-0"><i class="fa fa-info-circle"></i> No competitors found yet. Add your first competitor above!</div>';
            }
        })
        .catch(() => {
            listEl.innerHTML = '<div class="alert alert-warning mb-0"><i class="fa fa-info-circle"></i> No competitors found yet. Add your first competitor above!</div>';
        });
    }

    function reloadCurrentAmazonLmp() {
        if (currentAmazonLmpSku && currentAmazonLmpListEl) {
            return loadAmazonCompetitors(currentAmazonLmpSku, currentAmazonLmpListEl, currentAmazonLmpFormPrefix);
        }
        return Promise.resolve();
    }

    function submitAmazonLmpAddForm(fieldPrefix, formId) {
        const sku = document.getElementById(`${fieldPrefix}-add-comp-sku`)?.value.trim();
        const asin = document.getElementById(`${fieldPrefix}-add-comp-asin`)?.value.trim();
        const price = parseFloat(document.getElementById(`${fieldPrefix}-add-comp-price`)?.value);
        const link = document.getElementById(`${fieldPrefix}-add-comp-link`)?.value.trim();
        const marketplace = document.getElementById(`${fieldPrefix}-add-comp-marketplace`)?.value || 'amazon';
        const form = document.getElementById(formId);

        if (!asin) {
            showComparisonToast('error', 'ASIN is required');
            return Promise.resolve();
        }
        if (!price || price <= 0) {
            showComparisonToast('error', 'Valid price is required');
            return Promise.resolve();
        }

        const submitBtn = form?.querySelector('button[type="submit"]');
        const originalHtml = submitBtn?.innerHTML || '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
        }

        return fetch(amazonLmpAddUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku,
                asin,
                price,
                product_link: link || null,
                product_title: null,
                marketplace,
            }),
        })
        .then(response => response.json().then(data => ({ ok: response.ok, status: response.status, data })))
        .then(({ ok, status, data }) => {
            if (!ok) {
                let errorMsg = 'Failed to add competitor';
                if (status === 409) {
                    errorMsg = 'This ASIN is already saved for this SKU';
                } else if (data?.error) {
                    errorMsg = data.error;
                } else if (data?.message) {
                    errorMsg = data.message;
                }
                throw new Error(errorMsg);
            }
            showComparisonToast('success', 'Competitor added successfully');
            document.getElementById(`${fieldPrefix}-add-comp-asin`).value = '';
            document.getElementById(`${fieldPrefix}-add-comp-price`).value = '';
            document.getElementById(`${fieldPrefix}-add-comp-link`).value = '';
            clearTimeout(tableRefreshTimer);
            tableRefreshTimer = setTimeout(() => table?.replaceData(), 500);
            return reloadCurrentAmazonLmp();
        })
        .catch(err => {
            showComparisonToast('error', err.message || 'Failed to add competitor');
        })
        .finally(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }
        });
    }

    function deleteAmazonLmpCompetitor(button) {
        const id = button.dataset.id;
        const asin = button.dataset.asin || '';
        const price = button.dataset.price || '';

        if (!id) {
            showComparisonToast('error', 'Invalid competitor ID');
            return;
        }
        if (!confirm(`Delete competitor ${asin} ($${price}) from tracking?`)) {
            return;
        }

        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

        fetch(amazonLmpDeleteUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ id }),
        })
        .then(response => response.json().then(data => ({ ok: response.ok, data })))
        .then(({ ok, data }) => {
            if (!ok) {
                throw new Error(data?.error || 'Failed to delete competitor');
            }
            showComparisonToast('success', 'Competitor deleted successfully');
            clearTimeout(tableRefreshTimer);
            tableRefreshTimer = setTimeout(() => table?.replaceData(), 500);
            return reloadCurrentAmazonLmp();
        })
        .catch(err => {
            button.disabled = false;
            button.innerHTML = originalHtml;
            showComparisonToast('error', err.message || 'Failed to delete competitor');
        });
    }

    function renderEbayCompetitorsList(competitors, lowestPrice) {
        if (!competitors || competitors.length === 0) {
            return '<div class="alert alert-info mb-0"><i class="fa fa-info-circle"></i> No eBay competitors found for this SKU.</div>';
        }

        let html = '<div class="table-responsive"><table class="table table-hover table-bordered table-sm mb-0">';
        html += `<thead class="table-light"><tr>
            <th>#</th><th>Image</th><th>Item ID</th><th>Product Title</th>
            <th>Price</th><th>Shipping</th><th>Total</th><th>Link</th>
        </tr></thead><tbody>`;

        competitors.forEach(function (item, index) {
            const total = parseFloat(item.total_price ?? 0);
            const isLowest = lowestPrice != null && Math.abs(total - parseFloat(lowestPrice)) < 0.01;
            const rowClass = isLowest ? 'table-success' : '';
            const productLink = item.link || '#';
            const productTitle = item.title || 'N/A';
            const imageUrl = item.image || '';
            const imageHtml = imageUrl
                ? `<img src="${escapeHtmlAttr(imageUrl)}" style="width:50px;height:50px;object-fit:contain;" alt="">`
                : '<span class="text-muted">—</span>';

            html += `<tr class="${rowClass}">
                <td class="text-center"><strong>${index + 1}</strong></td>
                <td class="text-center">${imageHtml}</td>
                <td><span class="text-primary fw-semibold" style="font-size:11px;">${escapeHtmlAttr(item.item_id || 'N/A')}</span></td>
                <td style="font-size:11px;" title="${escapeHtmlAttr(productTitle)}">${escapeHtml(productTitle.length > 60 ? productTitle.substring(0, 60) + '…' : productTitle)}</td>
                <td>$${parseFloat(item.price || 0).toFixed(2)}</td>
                <td>$${parseFloat(item.shipping_cost || 0).toFixed(2)}</td>
                <td><strong>$${total.toFixed(2)}${isLowest ? ' <i class="fa fa-trophy text-success"></i>' : ''}</strong></td>
                <td class="text-center">
                    <a href="${escapeHtmlAttr(productLink)}" target="_blank" rel="noopener" class="btn btn-sm btn-info" title="View listing">
                        <i class="fa fa-external-link"></i>
                    </a>
                </td>
            </tr>`;
        });

        html += '</tbody></table></div>';
        return html;
    }

    function lmpFormatter(cell) {
        const rowData = cell.getRow().getData();
        const lmpPrice = cell.getValue();
        const sku = rowData.sku || '';
        const totalCompetitors = parseInt(rowData.lmp_entries_total, 10) || 0;
        const lmpLink = rowData.lmp_link || '';

        if (!lmpPrice && totalCompetitors === 0) {
            return '<span style="color:#999;">N/A</span>';
        }

        let html = '<div style="display:flex;flex-direction:column;align-items:center;gap:4px;">';

        if (lmpPrice) {
            const priceFormatted = '$' + parseFloat(lmpPrice).toFixed(2);
            if (lmpLink) {
                html += `<a href="${escapeHtmlAttr(lmpLink)}" target="_blank" rel="noopener"
                    style="color:#28a745;font-weight:600;font-size:14px;text-decoration:none;"
                    title="Lowest competitor link">${priceFormatted}</a>`;
            } else {
                html += `<span style="color:#28a745;font-weight:600;font-size:14px;">${priceFormatted}</span>`;
            }
        }

        if (totalCompetitors > 0) {
            html += `<a href="#" class="comparison-view-lmp-competitors" data-sku="${escapeHtmlAttr(sku)}"
                style="color:#007bff;text-decoration:none;cursor:pointer;font-size:11px;">
                <i class="fa fa-eye"></i> View ${totalCompetitors}
            </a>`;
        }

        html += '</div>';
        return html;
    }

    function loadComparisonLmpModal(sku, platform) {
        if (!sku) {
            return;
        }

        platform = String(platform || 'amazon').toLowerCase();
        const platformLabel = platform === 'ebay' ? 'eBay' : 'Amazon';

        const lmpModalEl = document.getElementById('comparisonLmpModal');
        if (!lmpModalEl || !window.bootstrap?.Modal) {
            return;
        }

        if (lmpModalEl.parentElement !== document.body) {
            document.body.appendChild(lmpModalEl);
        }

        document.getElementById('comparison-lmp-modal-sku').textContent = sku;
        const addWrap = document.getElementById('comparison-lmp-add-wrap');
        if (addWrap) {
            addWrap.classList.toggle('d-none', platform === 'ebay');
        }

        const listEl = document.getElementById('comparison-lmp-data-list');
        listEl.innerHTML = amazonLmpLoadingHtml();

        const lmpModalInstance = bootstrap.Modal.getOrCreateInstance(lmpModalEl);
        lmpModalEl.addEventListener('shown.bs.modal', function () {
            const openModals = document.querySelectorAll('.modal.show');
            const baseZ = 1050 + (openModals.length * 20);
            lmpModalEl.style.zIndex = String(baseZ + 10);
            const backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length) {
                backdrops[backdrops.length - 1].style.zIndex = String(baseZ);
            }
        }, { once: true });
        lmpModalInstance.show();

        if (platform === 'ebay') {
            fetch(`${ebayLmpDataUrl}?sku=${encodeURIComponent(sku)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    listEl.innerHTML = renderEbayCompetitorsList(data.competitors || [], data.lowest_price);
                } else {
                    listEl.innerHTML = `<div class="alert alert-warning mb-0"><i class="fa fa-info-circle"></i> No ${platformLabel} competitors found for this SKU.</div>`;
                }
            })
            .catch(() => {
                listEl.innerHTML = '<div class="alert alert-warning mb-0"><i class="fa fa-info-circle"></i> Could not load competitor data.</div>';
            });
            return;
        }

        loadAmazonCompetitors(sku, listEl, 'comparison-lmp');
    }

    function columnLetter(index) {
        let result = '';
        let n = index + 1;
        while (n > 0) {
            const rem = (n - 1) % 26;
            result = String.fromCharCode(65 + rem) + result;
            n = Math.floor((n - 1) / 26);
        }
        return result;
    }

    function normalizeSheetColor(color) {
        color = String(color || '').trim();
        if (/^#[0-9a-f]{6}$/i.test(color)) {
            return color.toLowerCase();
        }
        if (/^#[0-9a-f]{3}$/i.test(color)) {
            return '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3];
        }
        return '';
    }

    function normalizeSheetFormats(formats) {
        const source = formats || {};
        const next = { cells: {}, rows: {}, cols: {} };
        ['cells', 'rows', 'cols'].forEach(type => {
            const map = source[type] || {};
            Object.keys(map).forEach(key => {
                const color = normalizeSheetColor(map[key]);
                if (color) {
                    next[type][String(key)] = color;
                }
            });
        });
        return next;
    }

    function resolveSheetCellBackground(rowIndex, colIndex, isSpec) {
        const cellKey = `${rowIndex}:${colIndex}`;
        if (currentSheetFormats.cells[cellKey]) {
            return currentSheetFormats.cells[cellKey];
        }
        if (autoSheetFormats.cells[cellKey]) {
            return autoSheetFormats.cells[cellKey];
        }
        if (currentSheetFormats.rows[String(rowIndex)]) {
            return currentSheetFormats.rows[String(rowIndex)];
        }
        if (autoSheetFormats.rows[String(rowIndex)]) {
            return autoSheetFormats.rows[String(rowIndex)];
        }
        if (currentSheetFormats.cols[String(colIndex)]) {
            return currentSheetFormats.cols[String(colIndex)];
        }
        if (autoSheetFormats.cols[String(colIndex)]) {
            return autoSheetFormats.cols[String(colIndex)];
        }
        if (isSpec) {
            return SPEC_COLUMN_COLOR;
        }
        return '';
    }

    function sheetCellTdStyle(rowIndex, colIndex, isSpec) {
        const bg = resolveSheetCellBackground(rowIndex, colIndex, isSpec);
        return bg ? ` style="background-color:${bg};"` : '';
    }

    function detectSpecColumnIndex(cells) {
        const scores = {};
        const maxRows = Math.min(cells.length, 30);
        for (let rowIndex = 0; rowIndex < maxRows; rowIndex++) {
            const row = cells[rowIndex] || [];
            for (let colIndex = 0; colIndex < row.length; colIndex++) {
                const text = String(row[colIndex] || '').trim().toLowerCase();
                if (!text || text.startsWith('http') || text.startsWith('data:image/')) {
                    continue;
                }
                if (text.includes('supplier') || text.includes('product photo') || text.includes('person name review') || text.includes('company name')) {
                    scores[colIndex] = (scores[colIndex] || 0) + 1;
                }
            }
        }
        const keys = Object.keys(scores);
        if (!keys.length) {
            return 2;
        }
        keys.sort((a, b) => scores[b] - scores[a]);
        return parseInt(keys[0], 10);
    }

    function detectLabelColumnIndex(cells) {
        return detectSpecColumnIndex(cells);
    }

    function columnMatchesKeywords(cells, colIndex, keywords) {
        if (colIndex < 0) {
            return false;
        }
        for (let rowIndex = 0; rowIndex < Math.min(cells.length, 8); rowIndex++) {
            const text = String((cells[rowIndex] || [])[colIndex] || '').trim().toLowerCase();
            if (!text) {
                continue;
            }
            if (keywords.some(keyword => text.includes(keyword.toLowerCase()))) {
                return true;
            }
        }
        return false;
    }

    function insertSheetColumnAt(cells, index) {
        index = Math.max(0, index);
        return cells.map(row => {
            const next = Array.isArray(row) ? row.slice() : [String(row || '')];
            next.splice(index, 0, '');
            return next;
        });
    }

    function stampColumnHeader(cells, colIndex, header) {
        const existing = String((cells[0] || [])[colIndex] || '').trim();
        if (existing !== '' && !/^[A-Z]{1,3}$/.test(existing)) {
            if (isSheetImageUrl(existing) || /^https?:\/\//i.test(existing)) {
                return;
            }
        }
        if (!cells[0]) {
            cells[0] = [];
        }
        cells[0][colIndex] = header;
    }

    function ensureLeadColumns(cells) {
        cells = (cells || []).map(row => Array.isArray(row) ? row.slice() : [String(row || '')]);
        let specCol = detectSpecColumnIndex(cells);

        if (specCol < 2 && !columnMatchesKeywords(cells, 0, ['amazon'])) {
            let insertAt = Math.max(0, specCol - 1);
            if (specCol === 1 && columnMatchesKeywords(cells, 0, ['5 core', '5core', '5-core'])) {
                insertAt = 0;
            }
            cells = insertSheetColumnAt(cells, insertAt);
            specCol = detectSpecColumnIndex(cells);
        }

        while (specCol < 2) {
            cells = insertSheetColumnAt(cells, 0);
            specCol++;
        }

        specCol = detectSpecColumnIndex(cells);
        const amazonCol = specCol - 2;
        const fiveCoreCol = specCol - 1;

        if (!columnMatchesKeywords(cells, amazonCol, ['amazon'])) {
            stampColumnHeader(cells, amazonCol, 'Amazon');
        }
        if (!columnMatchesKeywords(cells, fiveCoreCol, ['5 core', '5core', '5-core'])) {
            stampColumnHeader(cells, fiveCoreCol, '5 Core');
        }

        const colCount = Math.max(...cells.map(row => row.length), 1);
        return cells.map(row => {
            while (row.length < colCount) {
                row.push('');
            }
            return row.slice(0, colCount);
        });
    }

    function parseSheetNumber(value) {
        const text = String(value || '').trim();
        if (!text) {
            return null;
        }
        const clean = text.replace(/,/g, '').replace(/[^0-9.\-]/g, '');
        if (clean === '' || Number.isNaN(Number(clean))) {
            return null;
        }
        const num = parseFloat(clean);
        return num > 0 ? num : null;
    }

    function findRowIndexByLabel(cells, labelNeedle, labelCol) {
        const needle = labelNeedle.toLowerCase();
        for (let rowIndex = 0; rowIndex < cells.length; rowIndex++) {
            const label = String((cells[rowIndex] || [])[labelCol] || '').trim().toLowerCase();
            if (label.includes(needle)) {
                return rowIndex;
            }
        }
        return null;
    }

    function findLowestSupplierColumn(cells, specCol, labelNeedle) {
        const rowIndex = findRowIndexByLabel(cells, labelNeedle, specCol);
        if (rowIndex === null) {
            return null;
        }

        const firstSupplierCol = specCol + 1;
        const colCount = Math.max(...cells.map(row => row.length), 0);
        let bestCol = null;
        let bestValue = Infinity;

        for (let colIndex = firstSupplierCol; colIndex < colCount; colIndex++) {
            const value = parseSheetNumber((cells[rowIndex] || [])[colIndex]);
            if (value === null || value >= bestValue) {
                continue;
            }
            bestValue = value;
            bestCol = colIndex;
        }

        return bestCol;
    }

    function moveSheetColumnData(cells, fromIndex, toIndex) {
        if (fromIndex === toIndex) {
            return cells;
        }

        return cells.map(row => {
            const next = Array.isArray(row) ? row.slice() : [String(row || '')];
            const value = next[fromIndex] ?? '';
            next.splice(fromIndex, 1);
            next.splice(toIndex, 0, value);
            return next;
        });
    }

    function moveLowestPriceSupplierAfterSpec(cells) {
        cells = (cells || []).map(row => Array.isArray(row) ? row.slice() : [String(row || '')]);
        const specCol = detectSpecColumnIndex(cells);
        const firstSupplierCol = specCol + 1;
        const colCount = Math.max(...cells.map(row => row.length), 0);

        if (firstSupplierCol >= colCount) {
            return { cells, moved: false, from: null, to: firstSupplierCol };
        }

        let bestCol = findLowestSupplierColumn(cells, specCol, 'usd');
        if (bestCol === null) {
            bestCol = findLowestSupplierColumn(cells, specCol, 'rmb');
        }

        if (bestCol === null || bestCol === firstSupplierCol) {
            return { cells, moved: false, from: bestCol, to: firstSupplierCol };
        }

        return {
            cells: moveSheetColumnData(cells, bestCol, firstSupplierCol),
            moved: true,
            from: bestCol,
            to: firstSupplierCol,
        };
    }

    function computeAutoSheetFormats(cells) {
        const formats = { cells: {}, rows: {}, cols: {} };
        if (!cells.length) {
            return formats;
        }

        const specCol = detectSpecColumnIndex(cells);
        formats.cols[String(specCol)] = SPEC_COLUMN_COLOR;

        for (let rowIndex = 0; rowIndex < cells.length; rowIndex++) {
            if (isSupplierNameRow(cells, rowIndex, specCol)) {
                formats.rows[String(rowIndex)] = SUPPLIER_NAME_ROW_COLOR;
            }
        }

        const firstSupplierCol = specCol + 1;
        const colCount = Math.max(...cells.map(row => row.length), 0);

        ['usd', 'rmb'].forEach(needle => {
            const rowIndex = findRowIndexByLabel(cells, needle, specCol);
            if (rowIndex === null) {
                return;
            }

            let bestCol = null;
            let bestValue = Infinity;
            for (let colIndex = firstSupplierCol; colIndex < colCount; colIndex++) {
                const value = parseSheetNumber((cells[rowIndex] || [])[colIndex]);
                if (value === null || value >= bestValue) {
                    continue;
                }
                bestValue = value;
                bestCol = colIndex;
            }

            if (bestCol !== null) {
                formats.cells[`${rowIndex}:${bestCol}`] = LOWEST_PRICE_COLOR;
            }
        });

        return formats;
    }

    function refreshAutoSheetFormats(cells) {
        autoSheetFormats = computeAutoSheetFormats(cells || currentSheetCells);
    }

    function isSupplierNameRowLabel(text) {
        const label = String(text || '').trim().toLowerCase();
        if (!label || label.includes('company name')) {
            return false;
        }
        if (label.includes('supplier name')) {
            return true;
        }
        return label === 'supplier' || label === 'suppliers';
    }

    function isSupplierNameRow(cells, rowIndex, specCol) {
        const row = cells[rowIndex] || [];
        for (let colIndex = 0; colIndex < row.length; colIndex++) {
            const text = String(row[colIndex] || '').trim();
            if (!text || !isSupplierNameRowLabel(text)) {
                continue;
            }
            if (colIndex === specCol) {
                return true;
            }
            if (text.length <= 48) {
                return true;
            }
        }
        return false;
    }

    function applyAutoSheetFormatsFromPayload(data, cells) {
        if (data?.auto_formats) {
            autoSheetFormats = normalizeSheetFormats(data.auto_formats);
            return;
        }
        refreshAutoSheetFormats(cells || data?.cells || currentSheetCells);
    }

    function shiftNumericFormatMap(map, index, delta) {
        const next = {};
        Object.keys(map || {}).forEach(key => {
            const rowIndex = parseInt(key, 10);
            if (Number.isNaN(rowIndex)) {
                return;
            }
            if (delta < 0 && rowIndex === index) {
                return;
            }
            let nextIndex = rowIndex;
            if (delta > 0 && rowIndex >= index) {
                nextIndex = rowIndex + delta;
            } else if (delta < 0 && rowIndex > index) {
                nextIndex = rowIndex + delta;
            }
            if (nextIndex >= 0) {
                next[String(nextIndex)] = map[key];
            }
        });
        return next;
    }

    function shiftCellFormatMap(map, index, axis, delta) {
        const next = {};
        Object.keys(map || {}).forEach(key => {
            const parts = key.split(':');
            if (parts.length !== 2) {
                return;
            }
            let rowIndex = parseInt(parts[0], 10);
            let colIndex = parseInt(parts[1], 10);
            if (Number.isNaN(rowIndex) || Number.isNaN(colIndex)) {
                return;
            }
            if (axis === 'row') {
                if (delta < 0 && rowIndex === index) {
                    return;
                }
                if (delta > 0 && rowIndex >= index) {
                    rowIndex += delta;
                } else if (delta < 0 && rowIndex > index) {
                    rowIndex += delta;
                }
            } else {
                if (delta < 0 && colIndex === index) {
                    return;
                }
                if (delta > 0 && colIndex >= index) {
                    colIndex += delta;
                } else if (delta < 0 && colIndex > index) {
                    colIndex += delta;
                }
            }
            if (rowIndex >= 0 && colIndex >= 0) {
                next[`${rowIndex}:${colIndex}`] = map[key];
            }
        });
        return next;
    }

    function moveFormatRow(formats, from, to) {
        formats = normalizeSheetFormats(formats);
        const rows = {};
        Object.keys(formats.rows).forEach(key => {
            let rowIndex = parseInt(key, 10);
            if (Number.isNaN(rowIndex)) {
                return;
            }
            if (rowIndex === from) {
                rowIndex = to;
            } else if (from < to && rowIndex > from && rowIndex <= to) {
                rowIndex--;
            } else if (from > to && rowIndex >= to && rowIndex < from) {
                rowIndex++;
            }
            rows[String(rowIndex)] = formats.rows[key];
        });

        const cells = {};
        Object.keys(formats.cells).forEach(key => {
            const parts = key.split(':');
            let rowIndex = parseInt(parts[0], 10);
            const colIndex = parseInt(parts[1], 10);
            if (Number.isNaN(rowIndex) || Number.isNaN(colIndex)) {
                return;
            }
            if (rowIndex === from) {
                rowIndex = to;
            } else if (from < to && rowIndex > from && rowIndex <= to) {
                rowIndex--;
            } else if (from > to && rowIndex >= to && rowIndex < from) {
                rowIndex++;
            }
            cells[`${rowIndex}:${colIndex}`] = formats.cells[key];
        });

        return { cells, rows, cols: { ...formats.cols } };
    }

    function moveFormatColumn(formats, from, to) {
        formats = normalizeSheetFormats(formats);
        const cols = {};
        Object.keys(formats.cols).forEach(key => {
            let colIndex = parseInt(key, 10);
            if (Number.isNaN(colIndex)) {
                return;
            }
            if (colIndex === from) {
                colIndex = to;
            } else if (from < to && colIndex > from && colIndex <= to) {
                colIndex--;
            } else if (from > to && colIndex >= to && colIndex < from) {
                colIndex++;
            }
            cols[String(colIndex)] = formats.cols[key];
        });

        const cells = {};
        Object.keys(formats.cells).forEach(key => {
            const parts = key.split(':');
            const rowIndex = parseInt(parts[0], 10);
            let colIndex = parseInt(parts[1], 10);
            if (Number.isNaN(rowIndex) || Number.isNaN(colIndex)) {
                return;
            }
            if (colIndex === from) {
                colIndex = to;
            } else if (from < to && colIndex > from && colIndex <= to) {
                colIndex--;
            } else if (from > to && colIndex >= to && colIndex < from) {
                colIndex++;
            }
            cells[`${rowIndex}:${colIndex}`] = formats.cells[key];
        });

        return { cells, rows: { ...formats.rows }, cols };
    }

    function getSheetFillTarget() {
        return document.getElementById('comparison-cd-fill-target')?.value || 'cell';
    }

    function getSheetFillColor() {
        return normalizeSheetColor(document.getElementById('comparison-cd-fill-color')?.value || '');
    }

    function applySheetFillColor() {
        readCellsFromEditor();
        const color = getSheetFillColor();
        if (!color) {
            setSheetStatus('Pick a valid fill color first.', true);
            return;
        }

        const target = getSheetFillTarget();
        if (target === 'cell') {
            if (!selectedSheetCell) {
                setSheetStatus('Select a cell first (click inside the grid).', true);
                return;
            }
            currentSheetFormats.cells[`${selectedSheetCell.row}:${selectedSheetCell.col}`] = color;
            setSheetStatus(`Applied fill to cell ${columnLetter(selectedSheetCell.col)}${selectedSheetCell.row + 1}.`, false);
        } else if (target === 'row') {
            if (selectedSheetRow === null) {
                setSheetStatus('Select a row first (click the row number).', true);
                return;
            }
            currentSheetFormats.rows[String(selectedSheetRow)] = color;
            setSheetStatus(`Applied fill to row ${selectedSheetRow + 1}.`, false);
        } else {
            if (selectedSheetCol === null) {
                setSheetStatus('Select a column first (click the column letter).', true);
                return;
            }
            currentSheetFormats.cols[String(selectedSheetCol)] = color;
            setSheetStatus(`Applied fill to column ${columnLetter(selectedSheetCol)}.`, false);
        }

        currentSheetFormats = normalizeSheetFormats(currentSheetFormats);
        renderSheetEditor(currentSheetCells);
        scheduleAutoSaveComparisonSheet(400);
    }

    function clearSheetFillColor() {
        readCellsFromEditor();
        const target = getSheetFillTarget();

        if (target === 'cell') {
            if (!selectedSheetCell) {
                setSheetStatus('Select a cell first (click inside the grid).', true);
                return;
            }
            delete currentSheetFormats.cells[`${selectedSheetCell.row}:${selectedSheetCell.col}`];
            setSheetStatus(`Cleared fill from cell ${columnLetter(selectedSheetCell.col)}${selectedSheetCell.row + 1}.`, false);
        } else if (target === 'row') {
            if (selectedSheetRow === null) {
                setSheetStatus('Select a row first (click the row number).', true);
                return;
            }
            delete currentSheetFormats.rows[String(selectedSheetRow)];
            setSheetStatus(`Cleared fill from row ${selectedSheetRow + 1}.`, false);
        } else {
            if (selectedSheetCol === null) {
                setSheetStatus('Select a column first (click the column letter).', true);
                return;
            }
            delete currentSheetFormats.cols[String(selectedSheetCol)];
            setSheetStatus(`Cleared fill from column ${columnLetter(selectedSheetCol)}.`, false);
        }

        currentSheetFormats = normalizeSheetFormats(currentSheetFormats);
        renderSheetEditor(currentSheetCells);
        scheduleAutoSaveComparisonSheet(400);
    }

    function isSheetImageUrl(value) {
        const url = String(value || '').trim();
        if (!url) {
            return false;
        }
        if (url.startsWith('data:image/')) {
            return true;
        }
        if (url.startsWith('/')) {
            return /\.(jpe?g|png|gif|webp|bmp|svg)(\?|$)/i.test(url)
                || url.includes('/storage/');
        }
        if (!/^https?:\/\//i.test(url)) {
            return false;
        }
        return /\.(jpe?g|png|gif|webp|bmp|svg)(\?|$)/i.test(url)
            || /googleusercontent\.com/i.test(url)
            || /ggpht\.com/i.test(url)
            || /cdn\.shopify\.com/i.test(url)
            || /docs\.google\.com\/feeds/i.test(url)
            || /drive\.google\.com\/thumbnail/i.test(url);
    }

    function isSheetLinkUrl(value) {
        const url = String(value || '').trim();
        if (!/^https?:\/\//i.test(url)) {
            return false;
        }
        return !isSheetImageUrl(url);
    }

    function isSheetSpecColumn(colIndex) {
        const specCol = detectSpecColumnIndex(currentSheetCells);
        return specCol !== null && colIndex === specCol;
    }

    function getSheetCellPlainText(cell) {
        return (cell.innerText || cell.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function convertSheetCellValue(cell, value, forceText) {
        const rowIndex = parseInt(cell.dataset.row, 10);
        const colIndex = parseInt(cell.dataset.col, 10);
        if (Number.isNaN(rowIndex) || Number.isNaN(colIndex)) {
            return false;
        }

        const td = cell.closest('td');
        if (!td) {
            return false;
        }

        td.innerHTML = sheetCellEditorHtml(value, rowIndex, colIndex, forceText || isSheetSpecColumn(colIndex));
        if (currentSheetCells[rowIndex]) {
            currentSheetCells[rowIndex][colIndex] = value;
        }
        return true;
    }

    function maybeConvertSheetCellToLink(cell) {
        if (!cell || cell.classList.contains('cd-sheet-cell-link')) {
            return false;
        }
        if (cell.getAttribute('contenteditable') !== 'true') {
            return false;
        }

        const colIndex = parseInt(cell.dataset.col, 10);
        if (isSheetSpecColumn(colIndex)) {
            return false;
        }
        if (isCompanyNameRow(parseInt(cell.dataset.row, 10), currentSheetCells)) {
            return false;
        }

        const text = getSheetCellPlainText(cell);
        if (!isSheetLinkUrl(text)) {
            return false;
        }

        return convertSheetCellValue(cell, text, false);
    }

    function maybeRefreshCompanyNameCell(cell) {
        if (!cell || cell.classList.contains('cd-sheet-cell-company')) {
            return false;
        }
        if (cell.getAttribute('contenteditable') !== 'true') {
            return false;
        }

        const rowIndex = parseInt(cell.dataset.row, 10);
        const colIndex = parseInt(cell.dataset.col, 10);
        if (isSheetSpecColumn(colIndex) || !isCompanyNameRow(rowIndex, currentSheetCells)) {
            return false;
        }

        const text = (cell.innerText || '').trimEnd();
        convertSheetCellValue(cell, text, false);
        return true;
    }

    function sheetCellEditorHtml(value, rowIndex, colIndex, forceText) {
        const rawText = String(value ?? '');
        const text = rawText.trim();
        if (!forceText && isSheetImageUrl(text)) {
            const attrValue = text.startsWith('data:image/') ? `[embedded-image:${rowIndex}:${colIndex}]` : text;
            return `<div class="cd-sheet-cell cd-sheet-cell-image" contenteditable="true" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}" data-value="${escapeHtmlAttr(attrValue)}" title="Embedded image"><img src="${escapeHtmlAttr(text)}" class="cd-sheet-img" alt="Product photo" referrerpolicy="no-referrer" loading="lazy"></div>`;
        }
        if (!forceText && isSheetLinkUrl(text)) {
            return `<div class="cd-sheet-cell cd-sheet-cell-link" contenteditable="false" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}" data-value="${escapeHtmlAttr(text)}" title="${escapeHtmlAttr(text)}">
                <a href="${escapeHtmlAttr(text)}" target="_blank" rel="noopener noreferrer"
                    class="cd-sheet-link-btn"
                    title="Open link" aria-label="Open link">
                    <span class="comparison-clink-dot" aria-hidden="true"></span>
                </a>
            </div>`;
        }
        if (!forceText && isCompanyNameDataCell(rowIndex, colIndex, forceText) && text) {
            const storedName = rawText.replace(/\r\n/g, '\n').trimEnd();
            return `<div class="cd-sheet-cell cd-sheet-cell-company" contenteditable="false" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}" data-value="${escapeHtmlAttr(storedName)}" title="${escapeHtmlAttr(storedName)}" aria-label="${escapeHtmlAttr(storedName)}">
                <span class="comparison-company-dot" aria-hidden="true"></span>
            </div>`;
        }
        if (!forceText && isCommDataCell(rowIndex, colIndex)) {
            return commCellEditorHtml(rowIndex, colIndex, getSupplierNameForColumn(colIndex));
        }
        if (!text) {
            return `<div class="cd-sheet-cell cd-sheet-cell-empty" contenteditable="true" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}"></div>`;
        }
        return `<div class="cd-sheet-cell" contenteditable="true" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}">${escapeHtml(text)}</div>`;
    }

    function renderSheetEditor(cells) {
        currentSheetCells = ensureLeadColumns(cells || []);
        if (currentSheetCells.length === 0) {
            currentSheetCells = [['Amazon', '5 Core', 'Product Photo', '', '']];
        }

        const colCountBeforeMove = Math.max(...currentSheetCells.map(row => row.length), 1);
        currentSheetCells = currentSheetCells.map(row => {
            while (row.length < colCountBeforeMove) row.push('');
            return row.slice(0, colCountBeforeMove);
        });

        const lowestMove = moveLowestPriceSupplierAfterSpec(currentSheetCells);
        currentSheetCells = lowestMove.cells;
        if (lowestMove.moved && lowestMove.from !== null && lowestMove.from !== lowestMove.to) {
            currentSheetFormats = moveFormatColumn(currentSheetFormats, lowestMove.from, lowestMove.to);
        }

        const colCount = Math.max(...currentSheetCells.map(row => row.length), 1);
        currentSheetCells = currentSheetCells.map(row => {
            while (row.length < colCount) row.push('');
            return row.slice(0, colCount);
        });

        refreshAutoSheetFormats(currentSheetCells);
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const head = document.getElementById('comparison-cd-sheet-head');
        const body = document.getElementById('comparison-cd-sheet-body');
        if (!head || !body) return;

        let headHtml = '<tr><th class="cd-row-num">#</th>';
        for (let c = 0; c < colCount; c++) {
            const selectedClass = selectedSheetCol === c ? ' cd-axis-selected' : '';
            let headerText = columnLetter(c);
            if (c === specCol - 2) headerText = 'Amazon';
            else if (c === specCol - 1) headerText = '5 Core';
            else if (c === specCol) headerText = 'Spec';
            headHtml += `<th class="cd-col-header cd-select-col${selectedClass}" data-col="${c}" title="Select column ${headerText}">${headerText}</th>`;
        }
        headHtml += '</tr>';
        head.innerHTML = headHtml;

        body.innerHTML = currentSheetCells.map((row, r) => {
            const rowSelectedClass = selectedSheetRow === r ? ' cd-axis-selected' : '';
            const commRowClass = isCommRow(r, currentSheetCells) ? ' cd-comm-row' : '';
            let rowHtml = `<tr class="${selectedSheetRow === r ? 'cd-row-selected' : ''}${commRowClass}"><td class="cd-row-num cd-select-row${rowSelectedClass}" data-row="${r}" title="Select row ${r + 1}">${r + 1}</td>`;
            for (let c = 0; c < colCount; c++) {
                const value = row[c] ?? '';
                const isSpec = c === specCol;
                const colSelectedClass = selectedSheetCol === c ? ' cd-col-selected' : '';
                const cellSelectedClass = selectedSheetCell && selectedSheetCell.row === r && selectedSheetCell.col === c
                    ? ' cd-cell-selected'
                    : '';
                let cellInner = sheetCellEditorHtml(value, r, c, isSpec);
                if (!isSpec && isCommRow(r, currentSheetCells)) {
                    cellInner = commCellEditorHtml(r, c, getSupplierNameForColumn(c));
                }
                rowHtml += `<td class="${isSpec ? 'cd-label-cell' : ''}${colSelectedClass}${cellSelectedClass}"${sheetCellTdStyle(r, c, isSpec)}>${cellInner}</td>`;
            }
            rowHtml += '</tr>';
            return rowHtml;
        }).join('');
    }

    function applySheetSelectionHighlight() {
        const table = document.getElementById('comparison-cd-sheet-table');
        if (!table) return;

        table.querySelectorAll('.cd-select-row').forEach(el => {
            const row = parseInt(el.dataset.row, 10);
            el.classList.toggle('cd-axis-selected', selectedSheetRow === row);
        });
        table.querySelectorAll('.cd-select-col').forEach(el => {
            const col = parseInt(el.dataset.col, 10);
            el.classList.toggle('cd-axis-selected', selectedSheetCol === col);
        });
        table.querySelectorAll('#comparison-cd-sheet-body tr').forEach((tr, index) => {
            tr.classList.toggle('cd-row-selected', selectedSheetRow === index);
            tr.querySelectorAll('td').forEach((td, colIndex) => {
                if (colIndex === 0) return;
                const dataCol = colIndex - 1;
                td.classList.toggle('cd-col-selected', selectedSheetCol === dataCol);
                td.classList.toggle(
                    'cd-cell-selected',
                    !!selectedSheetCell && selectedSheetCell.row === index && selectedSheetCell.col === dataCol
                );
            });
        });
    }

    function insertSheetRow() {
        readCellsFromEditor();
        const colCount = currentSheetCells[0]?.length || 6;
        const insertAt = selectedSheetRow !== null
            ? selectedSheetRow + 1
            : currentSheetCells.length;
        currentSheetCells.splice(insertAt, 0, Array.from({ length: colCount }, () => ''));
        currentSheetFormats.rows = shiftNumericFormatMap(currentSheetFormats.rows, insertAt, 1);
        currentSheetFormats.cells = shiftCellFormatMap(currentSheetFormats.cells, insertAt, 'row', 1);
        if (selectedSheetRow !== null && insertAt <= selectedSheetRow) {
            selectedSheetRow++;
        }
        renderSheetEditor(currentSheetCells);
        setSheetStatus(`Row inserted at position ${insertAt + 1}.`, false);
        scheduleAutoSaveComparisonSheet(400);
    }

    function deleteSheetRow() {
        readCellsFromEditor();
        if (currentSheetCells.length <= 1) {
            setSheetStatus('Cannot delete the last row.', true);
            return;
        }

        const idx = selectedSheetRow !== null ? selectedSheetRow : currentSheetCells.length - 1;
        currentSheetCells.splice(idx, 1);
        currentSheetFormats.rows = shiftNumericFormatMap(currentSheetFormats.rows, idx, -1);
        currentSheetFormats.cells = shiftCellFormatMap(currentSheetFormats.cells, idx, 'row', -1);
        if (selectedSheetCell && selectedSheetCell.row === idx) {
            selectedSheetCell = null;
        } else if (selectedSheetCell && selectedSheetCell.row > idx) {
            selectedSheetCell = { row: selectedSheetCell.row - 1, col: selectedSheetCell.col };
        }
        selectedSheetRow = currentSheetCells.length ? Math.min(idx, currentSheetCells.length - 1) : null;
        renderSheetEditor(currentSheetCells);
        setSheetStatus(`Row ${idx + 1} deleted.`, false);
        scheduleAutoSaveComparisonSheet(400);
    }

    function insertSheetColumn() {
        readCellsFromEditor();
        const insertAt = selectedSheetCol !== null
            ? selectedSheetCol + 1
            : (currentSheetCells[0]?.length || 0);
        currentSheetCells = currentSheetCells.map(row => {
            row.splice(insertAt, 0, '');
            return row;
        });
        currentSheetFormats.cols = shiftNumericFormatMap(currentSheetFormats.cols, insertAt, 1);
        currentSheetFormats.cells = shiftCellFormatMap(currentSheetFormats.cells, insertAt, 'col', 1);
        if (selectedSheetCol !== null && insertAt <= selectedSheetCol) {
            selectedSheetCol++;
        }
        renderSheetEditor(currentSheetCells);
        setSheetStatus(`Column inserted at ${columnLetter(insertAt)}.`, false);
        scheduleAutoSaveComparisonSheet(400);
    }

    function deleteSheetColumn() {
        readCellsFromEditor();
        const colCount = currentSheetCells[0]?.length || 0;
        if (colCount <= 1) {
            setSheetStatus('Cannot delete the last column.', true);
            return;
        }

        const idx = selectedSheetCol !== null ? selectedSheetCol : colCount - 1;
        currentSheetCells = currentSheetCells.map(row => {
            row.splice(idx, 1);
            return row;
        });
        currentSheetFormats.cols = shiftNumericFormatMap(currentSheetFormats.cols, idx, -1);
        currentSheetFormats.cells = shiftCellFormatMap(currentSheetFormats.cells, idx, 'col', -1);
        if (selectedSheetCell && selectedSheetCell.col === idx) {
            selectedSheetCell = null;
        } else if (selectedSheetCell && selectedSheetCell.col > idx) {
            selectedSheetCell = { row: selectedSheetCell.row, col: selectedSheetCell.col - 1 };
        }
        selectedSheetCol = Math.min(idx, (currentSheetCells[0]?.length || 1) - 1);
        renderSheetEditor(currentSheetCells);
        setSheetStatus(`Column ${columnLetter(idx)} deleted.`, false);
        scheduleAutoSaveComparisonSheet(400);
    }

    function moveSheetRow(direction) {
        readCellsFromEditor();
        if (selectedSheetRow === null) {
            setSheetStatus('Select a row first (click the row number).', true);
            return;
        }

        const target = direction === 'up' ? selectedSheetRow - 1 : selectedSheetRow + 1;
        if (target < 0 || target >= currentSheetCells.length) {
            setSheetStatus(direction === 'up' ? 'Row is already at the top.' : 'Row is already at the bottom.', true);
            return;
        }

        const from = selectedSheetRow;
        const moved = currentSheetCells.splice(from, 1)[0];
        currentSheetCells.splice(target, 0, moved);
        currentSheetFormats = moveFormatRow(currentSheetFormats, from, target);
        if (selectedSheetCell) {
            let rowIndex = selectedSheetCell.row;
            if (rowIndex === from) {
                rowIndex = target;
            } else if (from < target && rowIndex > from && rowIndex <= target) {
                rowIndex--;
            } else if (from > target && rowIndex >= target && rowIndex < from) {
                rowIndex++;
            }
            selectedSheetCell = { row: rowIndex, col: selectedSheetCell.col };
        }
        selectedSheetRow = target;
        renderSheetEditor(currentSheetCells);
        setSheetStatus(`Row moved ${direction === 'up' ? 'up' : 'down'} to position ${target + 1}.`, false);
        scheduleAutoSaveComparisonSheet(400);
    }

    function moveSheetColumn(direction) {
        readCellsFromEditor();
        if (selectedSheetCol === null) {
            setSheetStatus('Select a column first (click the column letter).', true);
            return;
        }

        const colCount = currentSheetCells[0]?.length || 0;
        const target = direction === 'left' ? selectedSheetCol - 1 : selectedSheetCol + 1;
        if (target < 0 || target >= colCount) {
            setSheetStatus(direction === 'left' ? 'Column is already at the left edge.' : 'Column is already at the right edge.', true);
            return;
        }

        const from = selectedSheetCol;
        currentSheetCells = currentSheetCells.map(row => {
            const moved = row.splice(from, 1)[0];
            row.splice(target, 0, moved);
            return row;
        });
        currentSheetFormats = moveFormatColumn(currentSheetFormats, from, target);
        if (selectedSheetCell) {
            let colIndex = selectedSheetCell.col;
            if (colIndex === from) {
                colIndex = target;
            } else if (from < target && colIndex > from && colIndex <= target) {
                colIndex--;
            } else if (from > target && colIndex >= target && colIndex < from) {
                colIndex++;
            }
            selectedSheetCell = { row: selectedSheetCell.row, col: colIndex };
        }
        selectedSheetCol = target;
        renderSheetEditor(currentSheetCells);
        setSheetStatus(`Column moved ${direction === 'left' ? 'left' : 'right'} to ${columnLetter(target)}.`, false);
        scheduleAutoSaveComparisonSheet(400);
    }

    function readCellsFromEditor() {
        const body = document.getElementById('comparison-cd-sheet-body');
        if (!body) return currentSheetCells;

        const rows = [];
        body.querySelectorAll('tr').forEach((tr, rowIndex) => {
            const row = [];
            tr.querySelectorAll('td').forEach((td, tdIndex) => {
                if (tdIndex === 0) {
                    return;
                }
                const colIndex = tdIndex - 1;
                if (isCommRow(rowIndex, currentSheetCells)) {
                    row.push((currentSheetCells[rowIndex] || [])[colIndex] || '');
                    return;
                }

                const cell = td.querySelector('.cd-sheet-cell');
                if (!cell) {
                    row.push((currentSheetCells[rowIndex] || [])[colIndex] || '');
                    return;
                }

                const stored = cell.dataset.value || '';
                const img = cell.querySelector('img');
                if (img && img.src && (!stored || stored.startsWith('[embedded-image:'))) {
                    row.push(img.src);
                    return;
                }
                if (stored) {
                    row.push(stored);
                    return;
                }
                row.push((cell.innerText || '').trimEnd());
            });
            rows.push(row);
        });

        currentSheetCells = rows;
        return rows;
    }

    function setSheetStatus(message, isError) {
        const el = document.getElementById('comparison-cd-sheet-status');
        if (!el) {
            return;
        }
        if (!isError) {
            el.classList.add('d-none');
            el.setAttribute('aria-hidden', 'true');
            el.textContent = '';
            return;
        }
        el.textContent = message;
        el.classList.remove('d-none');
        el.setAttribute('aria-hidden', 'false');
        el.classList.add('text-danger');
        el.classList.remove('text-success');
    }

    function cancelScheduledAutoSave() {
        clearTimeout(sheetAutoSaveTimer);
        sheetAutoSaveTimer = null;
    }

    function scheduleAutoSaveComparisonSheet(delay = 800) {
        if (sheetEditorHydrating || !currentCdRow) return;
        clearTimeout(sheetAutoSaveTimer);
        sheetAutoSaveTimer = setTimeout(() => autoSaveComparisonSheet(), delay);
    }

    function autoSaveComparisonSheet() {
        if (sheetEditorHydrating || !currentCdRow) return;
        if (sheetSaveInFlight) {
            sheetSaveQueued = true;
            return;
        }

        const activeElement = document.activeElement;
        const editingCell = activeElement?.closest?.('.cd-sheet-cell[contenteditable="true"]');
        const cells = readCellsFromEditor();

        sheetSaveInFlight = true;

        fetch(sheetSaveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: currentCdRow.sku,
                parent: currentCdRow.parent || '',
                linked_skus: linkedSkusForRow(currentCdRow),
                bulk_edit_skus: comparisonBulkEditPayload(),
                cells: cells,
                formats: currentSheetFormats,
                google_sheet_url: document.getElementById('comparison-cd-google-url').value.trim(),
                google_sheet_tab: document.getElementById('comparison-cd-google-tab').value.trim() || 'Sheet1',
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Save failed.');
            }
            currentSheetFormats = normalizeSheetFormats(data.formats || currentSheetFormats);
            const returnedCells = data.cells || cells;
            currentSheetCells = returnedCells;
            applyAutoSheetFormatsFromPayload(data, returnedCells);

            const stillEditing = editingCell
                && document.body.contains(editingCell)
                && document.activeElement === editingCell;

            if (!stillEditing) {
                renderSheetEditor(returnedCells);
            } else {
                currentSheetCells = returnedCells;
                applyAutoSheetFormatsFromPayload(data, returnedCells);
            }

            clearTimeout(tableRefreshTimer);
            tableRefreshTimer = setTimeout(() => table.replaceData(), 500);
        })
        .catch(err => {
            setSheetStatus(err.message || 'Auto-save failed.', true);
        })
        .finally(() => {
            sheetSaveInFlight = false;
            if (sheetSaveQueued) {
                sheetSaveQueued = false;
                scheduleAutoSaveComparisonSheet(300);
            }
        });
    }

    function applySheetPayload(data, row) {
        sheetEditorHydrating = true;
        cancelScheduledAutoSave();

        const clink = (row?.clink || data.clink || '').trim();
        const sheetUrl = data.google_sheet_url || (isGoogleSheetUrl(clink) ? clink : '');
        document.getElementById('comparison-cd-google-url').value = sheetUrl;
        updateCdGoogleUrlDotUI();
        document.getElementById('comparison-cd-google-tab').value = data.google_sheet_tab || 'Sheet1';
        currentSheetFormats = normalizeSheetFormats(data.formats || {});
        applyAutoSheetFormatsFromPayload(data, data.cells || []);
        let sheetCells = ensureLeadColumns(data.cells || []);
        const specCol = detectSpecColumnIndex(sheetCells);
        sheetCells = ensureCommRow(sheetCells, specCol).cells;
        renderSheetEditor(sheetCells);
        captureClinkPreloadedSuppliers(currentSheetCells);
        const category = String(row?.category || currentCdRow?.category || '').trim();
        loadComparisonSuppliersForCategory(category).then(function () {
            if (document.getElementById('comparison-cd-sheet-wrap')?.classList.contains('d-none') === false) {
                renderSheetEditor(currentSheetCells);
            }
        });
        sheetEditorHydrating = false;
    }

    function syncComparisonFromClink(row, options) {
        const opts = options || {};
        const importBtn = document.getElementById('comparison-cd-import-btn');
        if (importBtn) importBtn.disabled = true;
        setSheetStatus(opts.message || 'Loading comparison sheet from C link...', false);

        return fetch(sheetSyncClinkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: row.sku,
                parent: row.parent || '',
                google_sheet_tab: document.getElementById('comparison-cd-google-tab').value.trim() || 'Sheet1',
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Could not load C link sheet.');
            }
            applySheetPayload(data, row);
            if (opts.refreshTable) {
                table.replaceData();
            }
            return data;
        })
        .finally(() => {
            if (importBtn) importBtn.disabled = false;
        });
    }

    function loadComparisonSheet(row) {
        const loadingEl = document.getElementById('comparison-cd-sheet-loading');
        const wrapEl = document.getElementById('comparison-cd-sheet-wrap');
        loadingEl?.classList.remove('d-none');
        wrapEl?.classList.add('d-none');
        setSheetStatus('Loading comparison sheet...', false);

        fetch(`${sheetGetUrl}?${buildSheetRequestParams(row).toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to load sheet.');
            }

            if (data.sheet_sku) {
                row.sheet_sku = data.sheet_sku;
            }
            if (Array.isArray(data.linked_skus) && data.linked_skus.length) {
                row.linked_skus = data.linked_skus;
            }
            if (data.clink) {
                row.clink = data.clink;
                row.clink_is_sheet = !!data.clink_is_sheet;
                row.clink_sku = data.clink_sku || null;
            }
            currentCdRow = { ...row, sheet_sku: data.sheet_sku || row.sheet_sku };

            applySheetPayload(data, currentCdRow || row);

            const clinkIsSheet = !!data.clink_is_sheet || isGoogleSheetUrl(row.clink);
            if (clinkIsSheet && !data.has_sheet_data) {
                return syncComparisonFromClink(row, {
                    message: 'No saved sheet — loading from C link...',
                    refreshTable: true,
                });
            }
        })
        .catch(err => {
            sheetEditorHydrating = true;
            cancelScheduledAutoSave();
            currentSheetFormats = normalizeSheetFormats({});
            renderSheetEditor([]);
            sheetEditorHydrating = false;
            setSheetStatus(err.message || 'Could not load comparison sheet.', true);
        })
        .finally(() => {
            loadingEl?.classList.add('d-none');
            wrapEl?.classList.remove('d-none');
        });
    }

    function importComparisonGoogleSheet() {
        if (!currentCdRow) return;

        const clink = (currentCdRow.clink || '').trim();
        if (!isGoogleSheetUrl(clink) && !document.getElementById('comparison-cd-google-url').value.trim()) {
            setSheetStatus('Set a Google Sheet URL in the C link column first.', true);
            return;
        }

        syncComparisonFromClink(currentCdRow, {
            message: 'Refreshing comparison sheet from C link...',
            refreshTable: true,
        }).catch(err => {
            setSheetStatus(err.message || 'Could not refresh C link sheet.', true);
        });
    }

    function loadLmpTab(row) {
        const sku = row.sku || '';
        if (lmpLoadedForSku === sku) return;
        lmpLoadedForSku = sku;

        const clinkWrap = document.getElementById('comparison-cd-clink-wrap');
        const clinkLink = document.getElementById('comparison-cd-clink-link');
        const clinkText = document.getElementById('comparison-cd-clink-text');
        const clink = (row.clink || '').trim();

        if (clink) {
            clinkWrap.classList.remove('d-none');
            clinkLink.href = clink;
            clinkText.textContent = clink;
        } else {
            clinkWrap.classList.add('d-none');
            clinkLink.href = '#';
            clinkText.textContent = '';
        }

        document.getElementById('comparison-cd-lmp-list').innerHTML = amazonLmpLoadingHtml();
        loadAmazonCompetitors(sku, document.getElementById('comparison-cd-lmp-list'), 'comparison-cd');
    }

    function findSupplierNameRowIndex(cells, specCol) {
        specCol = specCol ?? detectSpecColumnIndex(cells);
        for (let rowIndex = 0; rowIndex < cells.length; rowIndex++) {
            if (isSupplierNameRow(cells, rowIndex, specCol)) {
                return rowIndex;
            }
        }
        return null;
    }

    function ensureSupplierLinkRow(cells, specCol) {
        specCol = specCol ?? detectSpecColumnIndex(cells);
        let rowIndex = findRowIndexByLabel(cells, 'supplier link', specCol);
        if (rowIndex !== null) {
            return { cells, rowIndex };
        }

        const colCount = Math.max(...cells.map(row => row.length), 6);
        const newRow = Array.from({ length: colCount }, () => '');
        newRow[specCol] = 'Supplier Link';

        let insertAt = 0;
        const personRowIndex = findRowIndexByLabel(cells, 'person name review', specCol);
        const photoRowIndex = findRowIndexByLabel(cells, 'product photo', specCol);
        if (personRowIndex !== null) {
            insertAt = personRowIndex + 1;
        } else if (photoRowIndex !== null) {
            insertAt = photoRowIndex + 1;
        }

        const nextCells = cells.slice();
        nextCells.splice(insertAt, 0, newRow);

        return { cells: nextCells, rowIndex: insertAt };
    }

    function ensureSupplierNameRow(cells, specCol) {
        specCol = specCol ?? detectSpecColumnIndex(cells);
        let rowIndex = findSupplierNameRowIndex(cells, specCol);
        if (rowIndex !== null) {
            return { cells, rowIndex };
        }

        const colCount = Math.max(...cells.map(row => row.length), 6);
        const newRow = Array.from({ length: colCount }, () => '');
        newRow[specCol] = 'Supplier Name';

        const companyRowIndex = findRowIndexByLabel(cells, 'company name', specCol);
        let insertAt = 0;
        if (companyRowIndex !== null) {
            insertAt = companyRowIndex;
        } else {
            const supplierLinkRow = findRowIndexByLabel(cells, 'supplier link', specCol);
            if (supplierLinkRow !== null) {
                insertAt = supplierLinkRow + 1;
            }
        }

        const nextCells = cells.slice();
        nextCells.splice(insertAt, 0, newRow);

        return { cells: nextCells, rowIndex: insertAt };
    }

    function ensureCompanyNameRow(cells, specCol) {
        specCol = specCol ?? detectSpecColumnIndex(cells);
        let rowIndex = findRowIndexByLabel(cells, 'company name', specCol);
        if (rowIndex !== null) {
            return { cells, rowIndex };
        }

        const colCount = Math.max(...cells.map(row => row.length), 6);
        const newRow = Array.from({ length: colCount }, () => '');
        newRow[specCol] = 'Company Name';

        let insertAt = 0;
        const supplierNameRow = findSupplierNameRowIndex(cells, specCol);
        if (supplierNameRow !== null) {
            insertAt = supplierNameRow + 1;
        } else {
            const supplierLinkRow = findRowIndexByLabel(cells, 'supplier link', specCol);
            if (supplierLinkRow !== null) {
                insertAt = supplierLinkRow + 1;
            }
        }

        const nextCells = cells.slice();
        nextCells.splice(insertAt, 0, newRow);

        return { cells: nextCells, rowIndex: insertAt };
    }

    function normalizeSupplierNameKey(name) {
        return String(name || '').trim().toLowerCase();
    }

    function supplierNamesMatch(a, b) {
        return normalizeSupplierNameKey(a) === normalizeSupplierNameKey(b);
    }

    function captureClinkPreloadedSuppliers(cells) {
        clinkPreloadedSupplierByCol = {};
        clinkPreloadedSupplierNames = new Set();
        if (!Array.isArray(cells) || !cells.length) {
            return;
        }

        const specCol = detectSpecColumnIndex(cells);
        const supplierRowIndex = findSupplierNameRowIndex(cells, specCol);
        if (supplierRowIndex === null) {
            return;
        }

        const row = cells[supplierRowIndex] || [];
        for (let col = FIRST_SUPPLIER_COLUMN; col < row.length; col++) {
            const name = String(row[col] || '').trim();
            if (!name) {
                continue;
            }
            clinkPreloadedSupplierByCol[col] = name;
            clinkPreloadedSupplierNames.add(normalizeSupplierNameKey(name));
        }
    }

    function isSupplierNameColumnBlank(cells, supplierRowIndex, col) {
        return !String((cells[supplierRowIndex] || [])[col] || '').trim();
    }

    function writeSupplierToColumn(cells, col, supplier, supplierRowIndex, supplierLinkRowIndex, companyRowIndex) {
        if (!cells[supplierRowIndex]) {
            cells[supplierRowIndex] = [];
        }
        cells[supplierRowIndex][col] = supplier.name || '';

        if (supplierLinkRowIndex !== null) {
            if (!cells[supplierLinkRowIndex]) {
                cells[supplierLinkRowIndex] = [];
            }
            cells[supplierLinkRowIndex][col] = supplier.link || '';
        }

        if (companyRowIndex !== null) {
            if (!cells[companyRowIndex]) {
                cells[companyRowIndex] = [];
            }
            cells[companyRowIndex][col] = supplier.company || '';
        }
    }

    function applySuppliersAddOnly(suppliers, supplierRowIndex, supplierLinkRowIndex, companyRowIndex) {
        const placedIds = new Set();
        let updated = 0;
        let added = 0;

        let maxCol = Math.max(
            ...currentSheetCells.map(row => row.length),
            FIRST_SUPPLIER_COLUMN
        );

        Object.keys(clinkPreloadedSupplierByCol).forEach(key => {
            const col = parseInt(key, 10);
            if (Number.isNaN(col)) {
                return;
            }

            const preloadedName = clinkPreloadedSupplierByCol[col];
            const existingName = String((currentSheetCells[supplierRowIndex] || [])[col] || '').trim();
            const supplier = suppliers.find(item => {
                if (placedIds.has(item.id)) {
                    return false;
                }
                return supplierNamesMatch(item.name, preloadedName)
                    || (existingName && supplierNamesMatch(item.name, existingName));
            });

            if (!supplier) {
                return;
            }

            writeSupplierToColumn(
                currentSheetCells,
                col,
                supplier,
                supplierRowIndex,
                supplierLinkRowIndex,
                companyRowIndex
            );
            placedIds.add(supplier.id);
            updated++;
        });

        let col = FIRST_SUPPLIER_COLUMN;
        while (placedIds.size < suppliers.length) {
            const supplier = suppliers.find(item => !placedIds.has(item.id));
            if (!supplier) {
                break;
            }

            while (col < maxCol && !isSupplierNameColumnBlank(currentSheetCells, supplierRowIndex, col)) {
                col++;
            }

            if (col >= maxCol) {
                currentSheetCells = ensureSupplierColumnCount(
                    currentSheetCells,
                    FIRST_SUPPLIER_COLUMN,
                    col - FIRST_SUPPLIER_COLUMN + 1
                );
                maxCol = Math.max(...currentSheetCells.map(row => row.length), maxCol + 1);
            }

            writeSupplierToColumn(
                currentSheetCells,
                col,
                supplier,
                supplierRowIndex,
                supplierLinkRowIndex,
                companyRowIndex
            );
            placedIds.add(supplier.id);
            added++;
            col++;
        }

        return { added, updated, placed: placedIds.size, total: suppliers.length };
    }

    function ensureSupplierColumnCount(cells, firstSupplierCol, neededCount) {
        const startCol = Math.max(0, parseInt(firstSupplierCol, 10) || 0);
        const colCount = Math.max(...cells.map(row => row.length), startCol + 1);
        const currentSupplierCols = Math.max(0, colCount - startCol);
        if (neededCount <= currentSupplierCols) {
            return cells;
        }

        const toAdd = neededCount - currentSupplierCols;
        return cells.map(row => {
            while (row.length < colCount) {
                row.push('');
            }
            for (let i = 0; i < toAdd; i++) {
                row.push('');
            }
            return row;
        });
    }

    function copySpecsToMemory() {
        readCellsFromEditor();
        if (!currentSheetCells.length) {
            setSheetStatus('No comparison sheet loaded.', true);
            return;
        }

        const specCol = detectSpecColumnIndex(currentSheetCells);
        saveCopiedSpecLabelsToMemory(currentSheetCells.map(row => String((row || [])[specCol] ?? '').trimEnd()));
        const text = copiedSpecLabels.join('\n');
        const nonEmptyCount = copiedSpecLabels.filter(label => label.trim() !== '').length;

        const finish = (message, isError) => {
            setSheetStatus(message, isError);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text)
                .then(() => finish(`Copied ${copiedSpecLabels.length} spec row(s) to memory (${nonEmptyCount} with labels).`, false))
                .catch(() => finish(`Saved ${copiedSpecLabels.length} spec row(s) to memory (${nonEmptyCount} with labels).`, false));
            return;
        }

        finish(`Saved ${copiedSpecLabels.length} spec row(s) to memory (${nonEmptyCount} with labels).`, false);
    }

    function formatRoiNumber(value, decimals) {
        if (value === null || value === undefined || value === '') {
            return '';
        }
        const num = parseFloat(value);
        if (Number.isNaN(num)) {
            return String(value).trim();
        }
        return Number(num.toFixed(decimals ?? 2)).toString();
    }

    function formatRoiCbm(value) {
        if (value === null || value === undefined || value === '') {
            return '';
        }
        const num = parseFloat(value);
        if (Number.isNaN(num)) {
            return String(value).trim();
        }
        return num.toFixed(3);
    }

    function computeFreightFromCbm(cbm) {
        const num = parseSheetNumber(cbm);
        if (num == null) {
            return '';
        }
        return formatRoiNumber(200 * num);
    }

    async function fetchPlatformLmpRates(sku) {
        if (!sku) {
            return { amazon: null, ebay: null };
        }

        try {
            const res = await fetch(`${lmpRatesUrl}?sku=${encodeURIComponent(sku)}`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (!data.success) {
                return { amazon: null, ebay: null };
            }
            return {
                amazon: data.amazon_lmp != null ? data.amazon_lmp : null,
                ebay: data.ebay_lmp != null ? data.ebay_lmp : null,
            };
        } catch (e) {
            return { amazon: null, ebay: null };
        }
    }

    function getChannelRawLmp(channel, lmpRates) {
        const key = String(channel || '').toLowerCase();
        const lmp = key === 'amazon' ? lmpRates?.amazon : (key === 'ebay' ? lmpRates?.ebay : null);
        return lmp != null ? formatRoiNumber(lmp) : '';
    }

    function getChannelLmpSale(channel, lmpRates) {
        const key = String(channel || '').toLowerCase();
        const lmp = key === 'amazon' ? lmpRates?.amazon : (key === 'ebay' ? lmpRates?.ebay : null);
        if (lmp == null) {
            return '';
        }
        return formatRoiNumber(lmp * ROI_LMP_SALE_FACTOR);
    }

    function updateManualLmpSection(lmpRates) {
        const wrap = document.getElementById('comparison-roi-manual-lmp-wrap');
        const hint = document.getElementById('comparison-roi-manual-lmp-hint');
        if (!wrap) {
            return;
        }

        const missingAmazon = lmpRates?.amazon == null;
        const missingEbay = lmpRates?.ebay == null;
        const show = missingAmazon || missingEbay;
        wrap.classList.toggle('d-none', !show);

        if (hint) {
            const parts = [];
            if (missingAmazon) {
                parts.push('Amazon');
            }
            if (missingEbay) {
                parts.push('Ebay');
            }
            hint.textContent = parts.length
                ? `No LMP found for ${parts.join(' and ')}. Enter manual LMP (sale = LMP × ${ROI_LMP_SALE_FACTOR}) for both platforms.`
                : '';
        }
    }

    function applyManualLmpToBoth() {
        const input = document.getElementById('comparison-roi-manual-lmp');
        const tbody = document.getElementById('comparison-roi-tbody');
        if (!input || !tbody || !tbody.roiRows) {
            return;
        }

        const parsed = parseSheetNumber(input.value);
        const rawLmp = parsed != null ? formatRoiNumber(parsed) : '';
        const sale = parsed != null
            ? formatRoiNumber(parsed * ROI_LMP_SALE_FACTOR)
            : String(input.value || '').trim();

        tbody.roiRows.forEach(function (row, rowIndex) {
            row.sale = sale;
            row.lmp = rawLmp;
            const tr = tbody.children[rowIndex];
            const saleInput = tr?.querySelector('[data-field="sale"]');
            if (saleInput) {
                saleInput.value = sale;
            }
            const lmpBtn = tr?.querySelector('.comparison-roi-lmp-link');
            if (lmpBtn) {
                lmpBtn.textContent = rawLmp || '—';
                lmpBtn.disabled = !rawLmp;
            }
            refreshRoiRowCalculations(tr, tbody, rowIndex);
            saveRoiCellEdit(rowIndex, 'sale', '', sale, sale);
        });
    }

    function setRoiSaveStatus(message, isError) {
        const el = document.getElementById('comparison-roi-save-status');
        if (!el) {
            return;
        }
        el.textContent = message;
        el.classList.toggle('text-danger', !!isError);
        el.classList.toggle('text-success', !isError && !!message);
    }

    function writeRoiChannelToSheet(cells, channel, rowData) {
        const specCol = detectSpecColumnIndex(cells);
        let rowIndex = findCostCalculatorChannelRow(cells, channel, specCol);
        const colCount = Math.max(...cells.map(row => row.length), specCol + 10, 6);

        if (rowIndex === null) {
            const newRow = Array.from({ length: colCount }, () => '');
            newRow[specCol] = channel;
            cells.push(newRow);
            rowIndex = cells.length - 1;
        }

        while (cells[rowIndex].length < colCount) {
            cells[rowIndex].push('');
        }

        Object.entries(ROI_FIELD_OFFSETS).forEach(([key, offset]) => {
            let value = rowData[key] ?? '';
            if ((key === 'pPct' || key === 'roi') && value) {
                value = String(value).replace('%', '');
            }
            cells[rowIndex][specCol + offset] = value;
        });

        return cells;
    }

    function saveRoiCellEdit(rowIndex, field, oldValue, newValue, displayNewValue) {
        if (!currentCdRow?.sku || roiSaveInFlight) {
            return Promise.resolve();
        }

        const tbody = document.getElementById('comparison-roi-tbody');
        const row = tbody?.roiRows?.[rowIndex];
        if (!row || !field) {
            return Promise.resolve();
        }

        const normalizedOld = String(oldValue ?? '').trim();
        const normalizedNew = String(displayNewValue ?? newValue ?? '').trim();
        if (normalizedOld === normalizedNew) {
            return Promise.resolve();
        }

        readCellsFromEditor();
        currentSheetCells = writeRoiChannelToSheet(currentSheetCells, row.channel, row);

        roiSaveInFlight = true;
        setRoiSaveStatus('Saving...', false);

        return fetch(roiSaveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: currentCdRow.sku,
                parent: currentCdRow.parent || '',
                linked_skus: linkedSkusForRow(currentCdRow),
                bulk_edit_skus: comparisonBulkEditPayload(),
                channel: row.channel,
                field,
                old_value: normalizedOld,
                new_value: normalizedNew,
                row,
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Save failed.');
            }
            if (Array.isArray(data.cells)) {
                currentSheetCells = data.cells;
            }
            setRoiSaveStatus(`Saved by ${data.updated_by || 'N/A'} at ${data.updated_at || new Date().toLocaleTimeString()}`, false);
            clearTimeout(tableRefreshTimer);
            tableRefreshTimer = setTimeout(() => table?.replaceData(), 500);
        })
        .catch(err => {
            setRoiSaveStatus(err.message || 'Save failed.', true);
        })
        .finally(() => {
            roiSaveInFlight = false;
        });
    }

    async function fetchShippingSlabRate(weightLb, sku) {
        const weight = parseSheetNumber(weightLb);
        if (weight == null) {
            return null;
        }

        const params = new URLSearchParams({
            weight_lb: String(weight),
            carrier: 'ship',
        });
        if (sku) {
            params.set('sku', sku);
        }

        try {
            const res = await fetch(`${shippingSlabRateUrl}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            return data.success ? data : null;
        } catch (e) {
            return null;
        }
    }

    function refreshRoiRowCalculations(tr, tbody, rowIndex) {
        const row = tbody.roiRows[rowIndex];
        if (!row) {
            return;
        }

        const calc = calculateRoiMetrics(row);
        row.profit = calc.profit != null ? formatRoiNumber(calc.profit) : '';
        row.pPct = calc.pPct != null ? `${formatRoiNumber(calc.pPct, 0)}%` : '';
        row.roi = calc.roi != null ? `${formatRoiNumber(calc.roi, 0)}%` : '';

        if (!tr) {
            return;
        }

        const pPctCell = tr.querySelector('[data-calc="pPct"]');
        const profitCell = tr.querySelector('[data-calc="profit"]');
        const roiCell = tr.querySelector('[data-calc="roi"]');
        if (pPctCell) {
            pPctCell.textContent = row.pPct;
            applyRoiCalcCellTier(pPctCell, 'pPct', row.pPct);
        }
        if (profitCell) {
            profitCell.textContent = row.profit;
        }
        if (roiCell) {
            roiCell.textContent = row.roi;
            applyRoiCalcCellTier(roiCell, 'roi', row.roi);
        }
    }

    function parseRoiPercentValue(value) {
        const num = parseFloat(String(value || '').replace('%', '').trim());
        return Number.isFinite(num) ? num : null;
    }

    function getPPctTierClass(pct) {
        if (pct == null) {
            return '';
        }
        if (pct > 33) {
            return 'comparison-roi-tier-magenta';
        }
        if (pct >= 20 && pct <= 33) {
            return 'comparison-roi-tier-green';
        }
        return 'comparison-roi-tier-red';
    }

    function getRoiPctTierClass(pct) {
        if (pct == null) {
            return '';
        }
        if (pct > 100) {
            return 'comparison-roi-tier-magenta';
        }
        if (pct >= 40 && pct <= 100) {
            return 'comparison-roi-tier-green';
        }
        return 'comparison-roi-tier-red';
    }

    function applyRoiCalcCellTier(cell, field, displayValue) {
        if (!cell) {
            return;
        }
        cell.classList.remove('comparison-roi-tier-red', 'comparison-roi-tier-green', 'comparison-roi-tier-magenta');
        const pct = parseRoiPercentValue(displayValue);
        const tier = field === 'pPct'
            ? getPPctTierClass(pct)
            : (field === 'roi' ? getRoiPctTierClass(pct) : '');
        if (tier) {
            cell.classList.add(tier);
        }
    }

    function roiCalcCellHtml(rowIndex, field, value) {
        const tier = field === 'pPct'
            ? getPPctTierClass(parseRoiPercentValue(value))
            : (field === 'roi' ? getRoiPctTierClass(parseRoiPercentValue(value)) : '');
        const tierClass = tier ? ` ${tier}` : '';
        return `<td class="comparison-roi-calc-cell${tierClass}" data-row="${rowIndex}" data-calc="${field}">${escapeHtml(value || '')}</td>`;
    }

    function getSheetCellText(cells, rowIndex, colIndex) {
        if (rowIndex === null || rowIndex === undefined || colIndex === null || colIndex === undefined) {
            return '';
        }
        return String((cells[rowIndex] || [])[colIndex] ?? '').trim();
    }

    function findCostCalculatorChannelRow(cells, channel, specCol) {
        const needle = channel.toLowerCase();
        for (let rowIndex = 0; rowIndex < cells.length; rowIndex++) {
            const label = getSheetCellText(cells, rowIndex, specCol).toLowerCase();
            if (label === needle || label === needle + ' ' || label.startsWith(needle + ' ')) {
                return rowIndex;
            }
        }
        return null;
    }

    function readCostCalculatorRowFromSheet(cells, channel, specCol) {
        const rowIndex = findCostCalculatorChannelRow(cells, channel, specCol);
        if (rowIndex === null) {
            return {};
        }

        const data = {};
        Object.entries(ROI_FIELD_OFFSETS).forEach(([key, offset]) => {
            data[key] = getSheetCellText(cells, rowIndex, specCol + offset);
        });
        return data;
    }

    function extractLowestPriceColumnMetrics(cells) {
        const specCol = detectSpecColumnIndex(cells);
        let priceLabel = 'usd';
        let lowestCol = findLowestSupplierColumn(cells, specCol, priceLabel);
        if (lowestCol === null) {
            priceLabel = 'rmb';
            lowestCol = findLowestSupplierColumn(cells, specCol, priceLabel);
        }
        if (lowestCol === null) {
            lowestCol = FIRST_SUPPLIER_COLUMN;
        }

        const priceRow = findRowIndexByLabel(cells, priceLabel, specCol)
            ?? findRowIndexByLabel(cells, 'supplier price', specCol);
        const gwRow = findRowIndexByLabel(cells, 'gw', specCol)
            ?? findRowIndexByLabel(cells, 'g.w', specCol)
            ?? findRowIndexByLabel(cells, 'weight', specCol);
        const cbmRow = findRowIndexByLabel(cells, 'cbm', specCol);

        const cp = parseSheetNumber(getSheetCellText(cells, priceRow, lowestCol));
        const gw = parseSheetNumber(getSheetCellText(cells, gwRow, lowestCol));
        const cbm = parseSheetNumber(getSheetCellText(cells, cbmRow, lowestCol));
        const freight = cbm != null ? 200 * cbm : null;

        return {
            specCol,
            col: lowestCol,
            colLetter: columnLetter(lowestCol),
            priceLabel,
            cp,
            gw,
            cbm,
            freight,
        };
    }

    function calculateRoiMetrics(row) {
        const cp = parseSheetNumber(row.cp) ?? 0;
        const freight = parseSheetNumber(row.freight) ?? 0;
        const shipping = parseSheetNumber(row.shipping) ?? 0;
        const sale = parseSheetNumber(row.sale) ?? 0;

        const profit = (sale * ROI_SALE_NET_FACTOR) - shipping - cp - freight;
        const hasInputs = sale > 0 || cp > 0 || freight > 0 || shipping > 0;
        const pPct = sale > 0 ? (profit / sale) * 100 : null;
        const roi = (cp + freight) > 0 ? (profit / (cp + freight)) * 100 : null;

        return {
            profit: hasInputs ? profit : null,
            pPct,
            roi,
        };
    }

    function buildRoiChannelRow(channel, cells, metrics, slabShipping, lmpRates) {
        const fromSheet = readCostCalculatorRowFromSheet(cells, channel, metrics.specCol);
        const cbm = fromSheet.cbm
            ? formatRoiCbm(fromSheet.cbm)
            : (metrics.cbm != null ? formatRoiCbm(metrics.cbm) : '');
        const lmpSale = getChannelLmpSale(channel, lmpRates);
        const rawLmp = getChannelRawLmp(channel, lmpRates);
        const row = {
            channel,
            cp: fromSheet.cp || (metrics.cp != null ? formatRoiNumber(metrics.cp) : ''),
            cbm,
            freight: computeFreightFromCbm(fromSheet.cbm || (metrics.cbm != null ? metrics.cbm : cbm)),
            gw: fromSheet.gw || (metrics.gw != null ? formatRoiNumber(metrics.gw) : ''),
            shipping: fromSheet.shipping || (slabShipping != null && slabShipping !== ''
                ? formatRoiNumber(slabShipping)
                : ''),
            sale: fromSheet.sale || lmpSale || '',
            lmp: rawLmp,
        };

        const calc = calculateRoiMetrics(row);
        row.profit = calc.profit != null ? formatRoiNumber(calc.profit) : (fromSheet.profit || '');
        row.pPct = calc.pPct != null ? `${formatRoiNumber(calc.pPct, 0)}%` : (fromSheet.pPct || '');
        row.roi = calc.roi != null ? `${formatRoiNumber(calc.roi, 0)}%` : (fromSheet.roi || '');

        return row;
    }

    function roiLmpCellHtml(rowIndex, row) {
        const platform = String(row.channel || 'amazon').toLowerCase();
        const display = row.lmp || '';
        if (!display) {
            return `<td class="comparison-roi-lmp-cell text-muted">—</td>`;
        }
        return `<td class="comparison-roi-lmp-cell">
            <button type="button" class="btn btn-link comparison-roi-lmp-link p-0 border-0"
                data-row="${rowIndex}" data-platform="${escapeHtmlAttr(platform)}"
                title="View ${escapeHtml(platform === 'ebay' ? 'eBay' : 'Amazon')} LMP competitors">
                ${escapeHtml(display)}
            </button>
        </td>`;
    }

    function renderRoiModalTable(rows) {
        const tbody = document.getElementById('comparison-roi-tbody');
        if (!tbody) {
            return;
        }

        tbody.innerHTML = rows.map((row, rowIndex) => {
            const inputCell = (field, value) =>
                `<td class="comparison-roi-input-cell"><input type="text" class="comparison-roi-input" data-row="${rowIndex}" data-field="${field}" value="${escapeHtmlAttr(value || '')}"></td>`;

            return `<tr>
                <td class="comparison-roi-channel">${escapeHtml(row.channel)}</td>
                ${inputCell('cp', row.cp)}
                ${inputCell('cbm', row.cbm)}
                ${inputCell('gw', row.gw)}
                ${inputCell('shipping', row.shipping)}
                ${inputCell('sale', row.sale)}
                ${roiLmpCellHtml(rowIndex, row)}
                ${roiCalcCellHtml(rowIndex, 'pPct', row.pPct)}
                ${roiCalcCellHtml(rowIndex, 'roi', row.roi)}
            </tr>`;
        }).join('');

        tbody.querySelectorAll('.comparison-roi-input').forEach(input => {
            input.addEventListener('input', handleRoiInputChange);
            input.addEventListener('focus', handleRoiInputFocus);
            input.addEventListener('blur', handleRoiInputBlur);
        });
        tbody.querySelectorAll('.comparison-roi-lmp-link').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const sku = currentCdRow?.sku || '';
                const platform = btn.dataset.platform || 'amazon';
                if (sku) {
                    loadComparisonLmpModal(sku, platform);
                }
            });
        });
        tbody.roiRows = rows;
    }

    function handleRoiCbmBlur(event) {
        const input = event.target;
        const formatted = formatRoiCbm(input.value);
        if (formatted === '') {
            return;
        }
        input.value = formatted;
        const tbody = document.getElementById('comparison-roi-tbody');
        const rowIndex = parseInt(input.dataset.row, 10);
        if (!tbody?.roiRows || Number.isNaN(rowIndex) || !tbody.roiRows[rowIndex]) {
            return;
        }
        tbody.roiRows[rowIndex].cbm = formatted;
    }

    function handleRoiInputFocus(event) {
        const input = event.target;
        if (!input.classList.contains('comparison-roi-input')) {
            return;
        }
        const key = `${input.dataset.row}-${input.dataset.field}`;
        roiCellEditPrevious[key] = input.value;
    }

    async function handleRoiInputBlur(event) {
        const input = event.target;
        if (!input.classList.contains('comparison-roi-input')) {
            return;
        }

        const rowIndex = parseInt(input.dataset.row, 10);
        const field = input.dataset.field;
        const tbody = document.getElementById('comparison-roi-tbody');
        if (Number.isNaN(rowIndex) || !field || !tbody?.roiRows?.[rowIndex]) {
            return;
        }

        if (field === 'cbm') {
            handleRoiCbmBlur(event);
        }

        const tr = input.closest('tr');
        if (field === 'gw') {
            await fetchShippingSlabRate(input.value, currentCdRow?.sku || '').then(function (slabInfo) {
                const shipRate = slabInfo?.rate != null ? formatRoiNumber(slabInfo.rate) : '';
                tbody.roiRows[rowIndex].shipping = shipRate;
                const shippingInput = tr?.querySelector('[data-field="shipping"]');
                if (shippingInput) {
                    shippingInput.value = shipRate;
                }
            });
        }

        refreshRoiRowCalculations(tr, tbody, rowIndex);

        const key = `${rowIndex}-${field}`;
        const oldValue = roiCellEditPrevious[key] ?? '';
        const newValue = input.value;
        delete roiCellEditPrevious[key];

        await saveRoiCellEdit(rowIndex, field, oldValue, newValue, input.value);
    }

    function handleRoiInputChange(event) {
        const input = event.target;
        const tbody = document.getElementById('comparison-roi-tbody');
        if (!tbody || !tbody.roiRows) {
            return;
        }

        const rowIndex = parseInt(input.dataset.row, 10);
        const field = input.dataset.field;
        if (Number.isNaN(rowIndex) || !field || !tbody.roiRows[rowIndex]) {
            return;
        }

        tbody.roiRows[rowIndex][field] = input.value;

        const tr = input.closest('tr');
        if (field === 'cbm') {
            const freightVal = computeFreightFromCbm(input.value);
            tbody.roiRows[rowIndex].freight = freightVal;
            const freightCell = tr?.querySelector('[data-calc="freight"]');
            if (freightCell) {
                freightCell.textContent = freightVal;
            }
        }

        if (field === 'gw') {
            fetchShippingSlabRate(input.value, currentCdRow?.sku || '').then(function (slabInfo) {
                const shipRate = slabInfo?.rate != null ? formatRoiNumber(slabInfo.rate) : '';
                tbody.roiRows[rowIndex].shipping = shipRate;
                const shippingInput = tr?.querySelector('[data-field="shipping"]');
                if (shippingInput) {
                    shippingInput.value = shipRate;
                }
                refreshRoiRowCalculations(tr, tbody, rowIndex);
            });
        }

        refreshRoiRowCalculations(tr, tbody, rowIndex);
    }

    function getRoiModalElement() {
        const el = document.getElementById('comparisonRoiModal');
        if (!el) {
            return null;
        }
        if (el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
        return el;
    }

    function getRoiModalInstance() {
        const el = getRoiModalElement();
        if (!el || !window.bootstrap?.Modal) {
            return null;
        }
        return bootstrap.Modal.getOrCreateInstance(el, { backdrop: true, focus: true });
    }

    function fixRoiModalStacking() {
        const el = getRoiModalElement();
        if (!el) {
            return;
        }

        el.classList.add('comparison-roi-modal-stacked');
        const openModals = document.querySelectorAll('.modal.show');
        const baseZ = 1050 + (openModals.length * 20);
        el.style.zIndex = String(baseZ + 10);

        const backdrops = document.querySelectorAll('.modal-backdrop');
        if (backdrops.length) {
            backdrops[backdrops.length - 1].style.zIndex = String(baseZ);
        }
    }

    async function openRoiModal() {
        readCellsFromEditor();
        if (!currentSheetCells.length) {
            setSheetStatus('Load a comparison sheet first.', true);
            return;
        }

        const metrics = extractLowestPriceColumnMetrics(currentSheetCells);
        const sku = currentCdRow?.sku || '';
        const [slabInfo, lmpRates] = await Promise.all([
            metrics.gw != null ? fetchShippingSlabRate(metrics.gw, sku) : Promise.resolve(null),
            fetchPlatformLmpRates(sku),
        ]);
        const rows = ROI_CHANNELS.map(channel => buildRoiChannelRow(
            channel,
            currentSheetCells,
            metrics,
            slabInfo?.rate,
            lmpRates
        ));
        renderRoiModalTable(rows);
        updateManualLmpSection(lmpRates);
        setRoiSaveStatus('', false);

        const manualLmpInput = document.getElementById('comparison-roi-manual-lmp');
        if (manualLmpInput) {
            manualLmpInput.value = '';
        }

        const roiModal = getRoiModalInstance();
        if (!roiModal) {
            setSheetStatus('ROI modal unavailable. Refresh the page and try again.', true);
            return;
        }

        const roiModalEl = getRoiModalElement();
        roiModalEl?.addEventListener('shown.bs.modal', fixRoiModalStacking, { once: true });
        roiModal.show();
    }

    function replaceSpecsFromMemory() {
        readCellsFromEditor();
        const template = getCopiedSpecLabels();
        if (!template.length) {
            setSheetStatus('No copied specs in memory. Use Copy Specs on a template sheet first.', true);
            return;
        }

        const specCol = detectSpecColumnIndex(currentSheetCells);
        let colCount = Math.max(
            ...currentSheetCells.map(row => row.length),
            specCol + 1,
            6
        );

        while (currentSheetCells.length < template.length) {
            currentSheetCells.push(Array.from({ length: colCount }, () => ''));
        }

        template.forEach((label, rowIndex) => {
            if (!currentSheetCells[rowIndex]) {
                currentSheetCells[rowIndex] = Array.from({ length: colCount }, () => '');
            }
            while (currentSheetCells[rowIndex].length < colCount) {
                currentSheetCells[rowIndex].push('');
            }
            currentSheetCells[rowIndex][specCol] = label;
            colCount = Math.max(colCount, currentSheetCells[rowIndex].length);
        });

        renderSheetEditor(currentSheetCells);
        const appliedCount = template.length;
        setSheetStatus(`Replaced spec labels for ${appliedCount} row(s) from saved template.`, false);
        scheduleAutoSaveComparisonSheet(400);
    }

    function autopopulateSupplierNamesFromList() {
        if (!currentCdRow) {
            setSheetStatus('Open a comparison row first.', true);
            return;
        }

        // Use row category first; fall back to latest table row if CD modal was opened before category was set.
        let category = String(currentCdRow.category || '').trim();
        if (!category && table && currentCdRow.sku) {
            const liveRow = table.getRows().find(row => row.getData().sku === currentCdRow.sku);
            if (liveRow) {
                category = String(liveRow.getData().category || '').trim();
                currentCdRow.category = category;
            }
        }
        if (!category) {
            setSheetStatus('Set a category on this row before autopopulating suppliers.', true);
            return;
        }

        readCellsFromEditor();
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const linkEnsured = ensureSupplierLinkRow(currentSheetCells, specCol);
        currentSheetCells = linkEnsured.cells;
        const supplierLinkRowIndex = linkEnsured.rowIndex;
        const nameEnsured = ensureSupplierNameRow(currentSheetCells, specCol);
        currentSheetCells = nameEnsured.cells;
        const supplierRowIndex = nameEnsured.rowIndex;
        const companyEnsured = ensureCompanyNameRow(currentSheetCells, specCol);
        currentSheetCells = companyEnsured.cells;
        const companyRowIndex = companyEnsured.rowIndex;

        const btn = document.getElementById('comparison-cd-autopopulate-suppliers-btn');
        if (btn) btn.disabled = true;
        setSheetStatus(`Loading suppliers for category "${category}" from supplier.list...`, false);

        const params = new URLSearchParams();
        params.set('sku', currentCdRow.sku || '');
        params.set('category', category);
        params.set('by_category', '1');

        fetch(`${suppliersForSkuUrl}?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Could not load suppliers.');
            }

            const suppliers = data.suppliers || [];
            if (!suppliers.length) {
                setSheetStatus(`No suppliers found on supplier.list for category "${category}".`, true);
                return;
            }

            const result = applySuppliersAddOnly(
                suppliers,
                supplierRowIndex,
                supplierLinkRowIndex,
                companyRowIndex
            );

            cacheComparisonSuppliers(suppliers);
            syncCommRowOnSheet();
            renderSheetEditor(currentSheetCells);
            const skipped = result.total - result.placed;
            let statusMsg = `Added ${result.added} supplier(s) in blank columns`;
            if (result.updated) {
                statusMsg += ` and updated ${result.updated} C-link match(es)`;
            }
            statusMsg += ` for "${category}".`;
            if (skipped > 0) {
                statusMsg += ` ${skipped} supplier(s) skipped (no blank columns left).`;
            }
            setSheetStatus(statusMsg, false);
            scheduleAutoSaveComparisonSheet(400);
        })
        .catch(err => {
            setSheetStatus(err.message || 'Could not autopopulate suppliers.', true);
        })
        .finally(() => {
            if (btn) btn.disabled = false;
        });
    }

    function openComparisonModal(row) {
        if (!cdModal) return;

        currentCdRow = row;
        cancelScheduledAutoSave();
        sheetEditorHydrating = false;
        lmpLoadedForSku = null;
        selectedSheetRow = null;
        selectedSheetCol = null;
        selectedSheetCell = null;
        currentSheetFormats = normalizeSheetFormats({});
        const skuBadge = document.getElementById('comparison-cd-modal-sku-badge');
        const skuHidden = document.getElementById('comparison-cd-modal-sku');
        let badgeText = row.sheet_sku && row.sheet_sku !== row.sku
            ? `${row.sku} (sheet: ${row.sheet_sku})`
            : (row.sku || '');
        if (Array.isArray(comparisonBulkEditSkus) && comparisonBulkEditSkus.length > 1) {
            badgeText = `Bulk edit: ${comparisonBulkEditSkus.length} SKUs (${comparisonBulkEditSkus.slice(0, 3).join(', ')}${comparisonBulkEditSkus.length > 3 ? '…' : ''})`;
        }
        if (skuBadge) {
            skuBadge.textContent = badgeText;
            skuBadge.title = Array.isArray(comparisonBulkEditSkus) && comparisonBulkEditSkus.length > 1
                ? `Changes save to: ${comparisonBulkEditSkus.join(', ')}`
                : (row.sheet_sku && row.sheet_sku !== row.sku
                    ? `Shared comparison sheet from linked SKU ${row.sheet_sku}`
                    : '');
        }
        if (skuHidden) {
            skuHidden.textContent = row.sku || '';
        }

        const sheetTabBtn = document.getElementById('cd-sheet-tab-btn');
        if (sheetTabBtn) {
            bootstrap.Tab.getOrCreateInstance(sheetTabBtn).show();
        }

        cdModal.show();
        hideCdHover();
        loadComparisonSheet(row);
    }

    function openHistoryModal(row) {
        if (!historyModal) return;

        const sku = row.sku || '';
        const parent = row.parent || '';
        document.getElementById('comparison-history-modal-sku').textContent = sku;

        const loadingEl = document.getElementById('comparison-history-loading');
        const emptyEl = document.getElementById('comparison-history-empty');
        const errorEl = document.getElementById('comparison-history-error');
        const tableWrap = document.getElementById('comparison-history-table-wrap');
        const tbody = document.getElementById('comparison-history-tbody');

        loadingEl.classList.remove('d-none');
        emptyEl.classList.add('d-none');
        errorEl.classList.add('d-none');
        errorEl.textContent = '';
        tableWrap.classList.add('d-none');
        tbody.innerHTML = '';

        historyModal.show();

        const params = new URLSearchParams({ sku: sku });
        if (parent) {
            params.set('parent', parent);
        }

        fetch(`${historyUrl}?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(response => response.json())
        .then(data => {
            loadingEl.classList.add('d-none');

            if (!data.success) {
                errorEl.textContent = data.message || 'Failed to load history.';
                errorEl.classList.remove('d-none');
                return;
            }

            const rows = data.history || [];
            if (rows.length === 0) {
                emptyEl.classList.remove('d-none');
                return;
            }

            tbody.innerHTML = rows.map(function (item) {
                return `<tr>
                    <td class="ch-when">${escapeHtml(item.updated_at || '—')}</td>
                    <td><span class="badge bg-secondary">${escapeHtml(item.updated_by || 'N/A')}</span></td>
                    <td>${escapeHtml(item.field_label || item.field || '—')}</td>
                    <td class="ch-change">${escapeHtml(item.changes || '—')}</td>
                </tr>`;
            }).join('');

            tableWrap.classList.remove('d-none');
        })
        .catch(() => {
            loadingEl.classList.add('d-none');
            errorEl.textContent = 'Could not load change history.';
            errorEl.classList.remove('d-none');
        });
    }

    function cdEditFormatter() {
        const title = 'Edit comparison data for this SKU';
        return `<button type="button" class="btn btn-sm btn-outline-primary comparison-cd-edit-btn py-0 px-2"
            title="${escapeHtmlAttr(title)}" aria-label="${escapeHtmlAttr(title)}">
            <i class="mdi mdi-pencil"></i>
        </button>`;
    }

    function cdFormatter(cell) {
        const row = cell.getRow().getData();
        const hasSheet = !!row.has_sheet_data;
        const clinkIsSheet = !!row.clink_is_sheet || isGoogleSheetUrl(row.clink);
        const sharedFrom = row.sheet_sku && row.sheet_sku !== row.sku ? row.sheet_sku : '';
        const title = hasSheet
            ? (sharedFrom
                ? `View/edit shared comparison sheet (latest from ${sharedFrom})`
                : 'View/edit comparison sheet')
            : (clinkIsSheet ? 'Load comparison sheet from C link' : 'View comparison data');
        const color = hasSheet ? '#16a34a' : (clinkIsSheet ? '#d97706' : '#2563eb');

        return `<div class="comparison-cd-cell" role="button" tabindex="0" title="${escapeHtmlAttr(title)}" aria-label="${escapeHtmlAttr(title)}">
            <span class="comparison-cd-btn">
                <i class="mdi mdi-magnify" style="font-size:18px;color:${color};line-height:1;"></i>
            </span>
        </div>`;
    }

    function historyFormatter(cell) {
        const row = cell.getRow().getData();
        const count = parseInt(row.history_count, 10) || 0;

        if (count === 0) {
            return '<span class="text-muted">—</span>';
        }

        const date = row.latest_history_at || '—';
        const user = row.latest_history_by || 'N/A';
        const change = row.latest_change || 'View history';

        return `<button type="button" class="btn btn-sm btn-link p-0 comparison-history-btn" title="${escapeHtmlAttr(change)}">
            <div><i class="fas fa-history text-secondary"></i> <small>${escapeHtml(date)}</small></div>
            <small class="text-muted d-block">${escapeHtml(user)}</small>
        </button>`;
    }

    function imageFormatter(cell) {
        const url = (cell.getValue() || '').trim();
        if (!url) {
            return '<span class="text-muted">No Image</span>';
        }

        return `<img src="${escapeHtmlAttr(url)}" alt="Product"
            style="height:40px;max-width:60px;border-radius:4px;border:1px solid #ccc;object-fit:contain;">`;
    }

    function supplierListCategoryUrl(category, searchSku) {
        const params = new URLSearchParams();
        if (category) {
            params.set('category', category);
        }
        if (searchSku) {
            params.set('search', searchSku);
        }
        const query = params.toString();
        return query ? `${supplierListUrl}?${query}` : supplierListUrl;
    }

    function linkedSkuFormatter(cell) {
        const row = cell.getRow().getData();
        const category = (row.category || '').trim();
        const rowSku = String(row.sku || '').trim();
        let skus = row.linked_skus || [];
        if (typeof skus === 'string') {
            try {
                skus = JSON.parse(skus) || [];
            } catch (e) {
                skus = [];
            }
        }
        if (!Array.isArray(skus)) {
            skus = [];
        }
        if (!skus.length && rowSku) {
            skus = [rowSku];
        }

        const badges = skus.length
            ? skus.map(function (sku) {
                const skuText = String(sku || '').trim();
                const isSelf = skuText.toUpperCase() === rowSku.toUpperCase();
                const href = supplierListCategoryUrl(category, skuText);
                const removeBtn = isSelf
                    ? ''
                    : `<button type="button" class="btn-close comparison-linked-sku-remove"
                        data-linked-sku="${escapeHtmlAttr(skuText)}" aria-label="Remove link to ${escapeHtmlAttr(skuText)}"></button>`;
                return `<span class="linked-sku-badge-wrap badge bg-info-subtle text-dark border me-1 mb-1">
                    <a href="${escapeHtmlAttr(href)}" target="_blank" rel="noopener noreferrer"
                        class="text-decoration-none text-dark linked-sku-badge"
                        title="Open ${escapeHtmlAttr(category || 'supplier.list')} for ${escapeHtmlAttr(skuText)}">${escapeHtml(skuText)}</a>${removeBtn}
                </span>`;
            }).join('')
            : '<span class="text-muted fst-italic">No SKUs</span>';

        return `<div class="d-flex flex-wrap align-items-start py-1" style="line-height:1.6;">${badges}</div>`;
    }

    function linkedSkuAddFormatter(cell) {
        const rowSku = String(cell.getRow().getData().sku || '').trim();
        if (!rowSku) {
            return '';
        }

        return `<div class="d-flex align-items-center justify-content-center py-1">
            <button type="button" class="btn btn-sm btn-outline-primary comparison-linked-sku-add-btn"
                title="Link another SKU" style="padding:2px 8px;" data-sku="${escapeHtmlAttr(rowSku)}">
                <i class="mdi mdi-plus"></i>
            </button>
        </div>`;
    }

    function applyAffectedLinkedSkuRows(affected) {
        if (!table || !Array.isArray(affected)) {
            return;
        }

        const bySku = {};
        affected.forEach(function (item) {
            if (item?.sku) {
                bySku[item.sku] = item.linked_skus || [];
            }
        });

        table.getRows().forEach(function (row) {
            const data = row.getData();
            if (!Object.prototype.hasOwnProperty.call(bySku, data.sku)) {
                return;
            }
            row.update({ linked_skus: bySku[data.sku] });
        });
    }

    function bulkLinkSelectedSkus(rowData, addBtn) {
        const selectedSkus = getSelectedComparisonRows()
            .map(function (row) { return String(row.getData().sku || '').trim(); })
            .filter(Boolean);

        if (selectedSkus.length < 2) {
            openLinkedSkuModal(rowData);
            return;
        }

        const original = addBtn?.innerHTML || '';
        if (addBtn) {
            addBtn.disabled = true;
            addBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        fetch(linkedSkuBulkLinkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ skus: selectedSkus }),
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (!res.success) {
                throw new Error(res.message || 'Could not link selected SKUs.');
            }
            applyAffectedLinkedSkuRows(res.affected);
            showComparisonToast('success', selectedSkus.length + ' SKUs linked as related.');
        })
        .catch(function (err) {
            alert(err.message || 'Could not link selected SKUs.');
        })
        .finally(function () {
            if (addBtn) {
                addBtn.disabled = false;
                addBtn.innerHTML = original;
            }
        });
    }

    function openLinkedSkuModal(rowData) {
        if (!linkedSkuModal || !rowData?.sku) {
            return;
        }

        linkedSkuModalRow = rowData;
        document.getElementById('comparison-linked-sku-source').textContent = rowData.sku;
        const input = document.getElementById('comparison-linked-sku-input');
        input.value = '';
        renderLinkedSkuSuggestions('');
        linkedSkuModal.show();
        setTimeout(function () { input?.focus(); }, 200);
    }

    function renderLinkedSkuSuggestions(term) {
        const wrap = document.getElementById('comparison-linked-sku-suggestions');
        if (!wrap || !table) {
            return;
        }

        const query = String(term || '').trim().toLowerCase();
        const currentSku = String(linkedSkuModalRow?.sku || '').trim().toUpperCase();
        const existing = new Set(
            (Array.isArray(linkedSkuModalRow?.linked_skus) ? linkedSkuModalRow.linked_skus : [])
                .map(function (sku) { return String(sku || '').trim().toUpperCase(); })
        );

        const matches = table.getData()
            .filter(function (row) {
                const sku = String(row.sku || '').trim();
                if (!sku) return false;
                const norm = sku.toUpperCase();
                if (norm === currentSku || existing.has(norm)) return false;
                if (!query) return true;
                return sku.toLowerCase().includes(query)
                    || String(row.parent || '').toLowerCase().includes(query);
            })
            .map(function (row) { return String(row.sku || '').trim(); })
            .slice(0, 8);

        if (!query || !matches.length) {
            wrap.classList.add('d-none');
            wrap.innerHTML = '';
            return;
        }

        wrap.classList.remove('d-none');
        wrap.innerHTML = matches.map(function (sku) {
            return `<button type="button" class="list-group-item list-group-item-action py-2 comparison-linked-sku-suggestion"
                data-sku="${escapeHtmlAttr(sku)}">${escapeHtml(sku)}</button>`;
        }).join('');
    }

    function saveLinkedSkuFromModal() {
        if (!linkedSkuModalRow?.sku) {
            return;
        }

        const linkedSku = document.getElementById('comparison-linked-sku-input')?.value.trim();
        if (!linkedSku) {
            alert('Enter a SKU to link.');
            return;
        }

        const btn = document.getElementById('comparison-linked-sku-save-btn');
        const original = btn?.innerHTML || '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Linking...';
        }

        fetch(linkedSkuAddUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: linkedSkuModalRow.sku,
                linked_sku: linkedSku,
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (!res.success) {
                throw new Error(res.message || 'Could not link SKU.');
            }
            applyAffectedLinkedSkuRows(res.affected);
            linkedSkuModal?.hide();
            showComparisonToast('success', 'Linked SKU updated for all related rows.');
        })
        .catch(function (err) {
            alert(err.message || 'Could not link SKU.');
        })
        .finally(function () {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        });
    }

    function removeLinkedSkuFromRow(rowData, linkedSku) {
        if (!rowData?.sku || !linkedSku) {
            return;
        }

        if (!confirm(`Remove link between "${rowData.sku}" and "${linkedSku}"?`)) {
            return;
        }

        fetch(linkedSkuRemoveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: rowData.sku,
                linked_sku: linkedSku,
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (!res.success) {
                throw new Error(res.message || 'Could not remove linked SKU.');
            }
            applyAffectedLinkedSkuRows(res.affected);
            showComparisonToast('success', 'Linked SKU removed from related rows.');
        })
        .catch(function (err) {
            alert(err.message || 'Could not remove linked SKU.');
        });
    }

    function clinkFormatter(cell) {
        const row = cell.getRow().getData();
        const url = (cell.getValue() || row.clink || '').trim();
        if (!url) {
            return '<span class="text-muted">-</span>';
        }

        const sharedFrom = row.clink_sku && row.clink_sku !== row.sku ? row.clink_sku : '';
        const title = sharedFrom
            ? `Shared C link from linked SKU ${sharedFrom}`
            : 'Open comparison link';

        return `<div style="display:flex;align-items:center;justify-content:center;">
            <a href="${escapeHtmlAttr(url)}" target="_blank" rel="noopener noreferrer"
                class="comparison-clink-dot-link"
                title="${escapeHtmlAttr(title)}" aria-label="${escapeHtmlAttr(title)}">
                <span class="comparison-clink-dot" aria-hidden="true"></span>
            </a>
        </div>`;
    }

    function saveClinkUpdate(cell, value) {
        const rowData = cell.getRow().getData();
        const sku = rowData.sku;
        if (!sku) return;

        fetch(updateLinkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: sku,
                column: 'Clink',
                value: value,
                linked_skus: linkedSkusForRow(rowData),
            }),
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                alert('Error: ' + (res.message || 'Could not save C link.'));
                return;
            }
            applyAffectedClinkRows(res.affected || [{ sku: sku, clink: value }]);
            if (currentCdRow && currentCdRow.sku === sku) {
                currentCdRow.clink = value;
            }
            showComparisonToast('success', 'C link saved for all linked SKUs.');
        })
        .catch(() => alert('Could not save C link.'));
    }

    function applyAffectedClinkRows(affected) {
        if (!table || !Array.isArray(affected)) {
            return;
        }

        const bySku = {};
        affected.forEach(function (item) {
            if (item?.sku) {
                bySku[item.sku] = item.clink || '';
            }
        });

        table.getRows().forEach(function (row) {
            const data = row.getData();
            if (!Object.prototype.hasOwnProperty.call(bySku, data.sku)) {
                return;
            }
            const clink = bySku[data.sku];
            row.update({
                clink: clink,
                clink_is_sheet: isGoogleSheetUrl(clink),
                clink_sku: null,
            });
        });
    }

    function loadProductCategories() {
        return Promise.all([
            fetch(supplierCategoriesUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(res => res.json()).catch(() => ({ categories: [] })),
            fetch(groupMasterCategoriesUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(res => res.json()).catch(() => ({ success: false, categories: [] })),
        ]).then(function ([supplierRes, productRes]) {
            supplierCategoryOptions = Array.isArray(supplierRes.categories) ? supplierRes.categories : [];
            allProductCategories = productRes.success && Array.isArray(productRes.categories)
                ? productRes.categories
                : [];
            productCategoriesByName = {};
            allProductCategories.forEach(function (cat) {
                const key = String(cat.category_name || '').trim().toLowerCase();
                if (key) {
                    productCategoriesByName[key] = cat;
                }
            });
        });
    }

    function categoryFormatter(cell) {
        const val = String(cell.getValue() || '').trim();
        const display = val ? escapeHtml(val) : '<span class="text-muted">—</span>';
        return `<div class="comparison-category-cell" title="Click to search and select category">${display}</div>`;
    }

    function closeCategoryDropdown() {
        if (activeCategoryDropdown) {
            activeCategoryDropdown.remove();
            activeCategoryDropdown = null;
        }
    }

    function positionCategoryDropdown(dropdown, cellEl) {
        const rect = cellEl.getBoundingClientRect();
        dropdown.style.left = `${Math.max(8, rect.left)}px`;
        dropdown.style.top = `${rect.bottom + 4}px`;
        const dropdownRect = dropdown.getBoundingClientRect();
        if (dropdownRect.right > window.innerWidth - 8) {
            dropdown.style.left = `${Math.max(8, window.innerWidth - dropdownRect.width - 8)}px`;
        }
        if (dropdownRect.bottom > window.innerHeight - 8) {
            dropdown.style.top = `${Math.max(8, rect.top - dropdownRect.height - 4)}px`;
        }
    }

    function renderCategoryDropdownResults(resultsEl, searchTerm, onSelect) {
        const term = String(searchTerm || '').trim().toLowerCase();
        const filtered = term
            ? supplierCategoryOptions.filter(cat => String(cat.name || '').toLowerCase().includes(term))
            : supplierCategoryOptions.slice();

        resultsEl.innerHTML = '';

        const clearItem = document.createElement('div');
        clearItem.className = 'dropdown-search-item clear-option';
        clearItem.textContent = '— No Category —';
        clearItem.addEventListener('mousedown', function (e) {
            e.preventDefault();
            onSelect('');
        });
        resultsEl.appendChild(clearItem);

        if (!filtered.length) {
            const empty = document.createElement('div');
            empty.className = 'dropdown-search-item no-results';
            empty.textContent = term ? 'No matching categories' : 'No categories loaded';
            resultsEl.appendChild(empty);
            return;
        }

        filtered.forEach(cat => {
            const item = document.createElement('div');
            item.className = 'dropdown-search-item';
            item.textContent = cat.name || '';
            item.addEventListener('mousedown', function (e) {
                e.preventDefault();
                onSelect(cat.name || '');
            });
            resultsEl.appendChild(item);
        });
    }

    function refreshComparisonRowFromServer(sku, extraSkus) {
        if (!sku || !table) return;

        const pending = [sku, ...(Array.isArray(extraSkus) ? extraSkus : [])].filter(Boolean);
        const params = new URLSearchParams({ skus: pending.join(',') });

        fetch(`${dataUrl}?${params.toString()}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) return;
            const rows = res.data || [];
            const pending = [sku, ...(Array.isArray(extraSkus) ? extraSkus : [])].filter(Boolean);
            const seen = new Set();

            while (pending.length) {
                const targetSku = pending.pop();
                if (seen.has(targetSku)) {
                    continue;
                }
                seen.add(targetSku);

                const updated = rows.find(row => row.sku === targetSku);
                if (!updated) {
                    continue;
                }

                const tabulatorRow = table.getRows().find(row => row.getData().sku === targetSku);
                if (tabulatorRow) {
                    tabulatorRow.update({
                        category_id: updated.category_id,
                        category: updated.category,
                        linked_skus: updated.linked_skus,
                        has_sheet_data: updated.has_sheet_data,
                        sheet_sku: updated.sheet_sku,
                        clink: updated.clink,
                        clink_is_sheet: updated.clink_is_sheet,
                        clink_sku: updated.clink_sku,
                    });
                }

                if (Array.isArray(updated.linked_skus)) {
                    updated.linked_skus.forEach(function (relatedSku) {
                        if (relatedSku && !seen.has(relatedSku)) {
                            pending.push(relatedSku);
                        }
                    });
                }
            }
        })
        .catch(() => {});
    }

    function resolveProductCategoryId(categoryName) {
        const key = String(categoryName || '').trim().toLowerCase();
        if (!key) {
            return Promise.resolve(null);
        }

        const existing = productCategoriesByName[key];
        if (existing) {
            return Promise.resolve(parseInt(existing.id, 10));
        }

        return fetch(groupMasterStoreCategoryUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                category_name: String(categoryName).trim(),
                status: 'active',
            }),
        })
        .then(res => res.json())
        .then(function (res) {
            if (res.success && res.category) {
                productCategoriesByName[key] = res.category;
                allProductCategories.push(res.category);
                return parseInt(res.category.id, 10);
            }

            return loadProductCategories().then(function () {
                const refreshed = productCategoriesByName[key];
                return refreshed ? parseInt(refreshed.id, 10) : null;
            });
        })
        .catch(function () {
            return loadProductCategories().then(function () {
                const refreshed = productCategoriesByName[key];
                return refreshed ? parseInt(refreshed.id, 10) : null;
            });
        });
    }

    function saveProductCategory(cell, categoryName) {
        const rowData = cell.getRow().getData();
        const productId = rowData.id;
        const sku = rowData.sku;
        if (!productId || !sku) return;

        const normalizedName = String(categoryName || '').trim();
        const currentName = String(rowData.category || '').trim();
        if (normalizedName === currentName) {
            return;
        }

        const cellEl = cell.getElement();
        cellEl.style.opacity = '0.6';

        resolveProductCategoryId(normalizedName).then(function (productCategoryId) {
            return fetch(groupMasterUpdateFieldUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    sku: sku,
                    field: 'category_id',
                    value: productCategoryId,
                }),
            });
        })
        .then(res => res.json())
        .then(function (res) {
            cellEl.style.opacity = '1';
            if (!res.success) {
                alert('Error: ' + (res.message || 'Could not save category.'));
                return;
            }

            const savedName = res.data?.category_name || normalizedName;
            cell.getRow().update({
                category_id: res.data?.category_id ?? null,
                category: savedName,
            });
            refreshComparisonRowFromServer(sku);
        })
        .catch(function () {
            cellEl.style.opacity = '1';
            alert('Could not save category.');
        });
    }

    function openCategoryDropdown(cell) {
        closeCategoryDropdown();

        const cellEl = cell.getElement();
        const rowData = cell.getRow().getData();
        const currentCategoryName = String(rowData.category || '').trim();

        const dropdown = document.createElement('div');
        dropdown.className = 'comparison-category-dropdown comparison-category-dropdown-panel';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'dropdown-search-input';
        input.placeholder = 'Search categories...';
        input.autocomplete = 'off';

        const results = document.createElement('div');
        results.className = 'dropdown-search-results';

        dropdown.appendChild(input);
        dropdown.appendChild(results);
        document.body.appendChild(dropdown);
        activeCategoryDropdown = dropdown;
        positionCategoryDropdown(dropdown, cellEl);

        const handleSelect = function (categoryName) {
            closeCategoryDropdown();
            const nextName = String(categoryName || '').trim();
            if (nextName === currentCategoryName) {
                return;
            }
            saveProductCategory(cell, nextName);
        };

        renderCategoryDropdownResults(results, '', handleSelect);
        input.focus();

        input.addEventListener('input', function () {
            renderCategoryDropdownResults(results, input.value, handleSelect);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeCategoryDropdown();
            }
        });
    }

    document.addEventListener('click', function (e) {
        if (!activeCategoryDropdown) return;
        if (e.target.closest('.comparison-category-dropdown-panel')) return;
        if (e.target.closest('.comparison-category-cell')) return;
        closeCategoryDropdown();
    });

    loadProductCategories().then(function () {
        initComparisonTable();
    });

    function initComparisonTable() {
    table = new Tabulator('#comparison-table', {
        ajaxURL: dataUrl,
        ajaxConfig: 'GET',
        ajaxURLGenerator: function (url, config, params) {
            const query = new URLSearchParams({
                page: String(params.page || 1),
                size: String(params.size || 50),
            });
            const skuTerm = (document.getElementById('comparison-search-sku')?.value || '').trim();
            const parentTerm = (document.getElementById('comparison-search-parent')?.value || '').trim();
            if (skuTerm) {
                query.set('sku', skuTerm);
            }
            if (parentTerm) {
                query.set('parent', parentTerm);
            }
            return `${url}?${query.toString()}`;
        },
        ajaxResponse: function (url, params, response) {
            if (!response.success) {
                throw new Error(response.message || 'Failed to load comparison data.');
            }
            return {
                data: response.data || [],
                last_page: response.last_page || 1,
            };
        },
        pagination: true,
        paginationMode: 'remote',
        filterMode: 'remote',
        sortMode: 'local',
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, 200],
        paginationInitialPage: 1,
        layout: 'fitColumns',
        movableColumns: true,
        resizableColumns: true,
        height: '650px',
        placeholder: 'No comparison data found',
        selectableRows: true,
        selectableRowsPersistence: false,
        columns: [
            {
                formatter: 'rowSelection',
                titleFormatter: 'rowSelection',
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                width: 44,
            },
            {
                title: 'Image',
                field: 'image',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 90,
                headerSort: false,
                formatter: imageFormatter,
            },
            {
                title: 'Parent',
                field: 'parent',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 180,
            },
            {
                title: 'SKU',
                field: 'sku',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 220,
            },
            {
                title: 'Category',
                field: 'category',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 180,
                headerSort: true,
                cssClass: 'comparison-category-col',
                formatter: categoryFormatter,
                cellClick: function (e, cell) {
                    e.stopPropagation();
                    openCategoryDropdown(cell);
                },
            },
            {
                title: 'Link Sku Purchase',
                field: 'linked_skus',
                hozAlign: 'left',
                headerHozAlign: 'center',
                width: 220,
                headerSort: false,
                cssClass: 'linked-sku-col',
                formatter: linkedSkuFormatter,
                cellClick: function (e, cell) {
                    if (e.target.closest('.comparison-linked-sku-remove')) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeLinkedSkuFromRow(
                            cell.getRow().getData(),
                            e.target.closest('.comparison-linked-sku-remove').dataset.linkedSku || ''
                        );
                    }
                },
            },
            {
                title: '+',
                field: 'linked_sku_add',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 52,
                headerSort: false,
                cssClass: 'linked-sku-add-col',
                formatter: linkedSkuAddFormatter,
                cellClick: function (e, cell) {
                    if (e.target.closest('.comparison-linked-sku-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        bulkLinkSelectedSkus(
                            cell.getRow().getData(),
                            e.target.closest('.comparison-linked-sku-add-btn')
                        );
                    }
                },
            },
            {
                title: 'C link',
                field: 'clink',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 90,
                headerTooltip: 'Comparison link',
                formatter: clinkFormatter,
                editor: 'input',
                cellEdited: function (cell) {
                    saveClinkUpdate(cell, cell.getValue());
                },
            },
            {
                title: 'LMP',
                field: 'lmp_price',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 100,
                headerSort: true,
                headerTooltip: 'Lowest market price and competition product links',
                formatter: lmpFormatter,
                cellClick: function (e) {
                    const viewLink = e.target.closest('.comparison-view-lmp-competitors');
                    if (viewLink) {
                        e.preventDefault();
                        const sku = viewLink.dataset.sku || '';
                        if (sku) {
                            loadComparisonLmpModal(sku);
                        }
                    }
                },
            },
            {
                title: 'CD',
                field: 'cd_view',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 70,
                headerSort: false,
                headerTooltip: 'Comparison Data',
                formatter: cdFormatter,
                cellMouseEnter: function (e, cell) {
                    showCdHover(e, cell.getRow().getData());
                },
                cellMouseMove: function (e) {
                    positionCdHover(e);
                },
                cellMouseLeave: function () {
                    hideCdHover();
                },
                cellClick: function (e, cell) {
                    e.preventDefault();
                    e.stopPropagation();
                    comparisonBulkEditSkus = null;
                    openComparisonModal(cell.getRow().getData());
                },
            },
            {
                title: 'Edit',
                field: 'cd_edit',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 70,
                headerSort: false,
                headerTooltip: 'Edit comparison data',
                formatter: cdEditFormatter,
                cellClick: function (e, cell) {
                    if (!e.target.closest('.comparison-cd-edit-btn')) {
                        return;
                    }
                    e.preventDefault();
                    e.stopPropagation();
                    openComparisonModalForEdit(cell.getRow().getData(), cell.getRow());
                },
            },
            {
                title: 'History',
                field: 'history_view',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 140,
                headerSort: false,
                headerTooltip: 'Change history',
                formatter: historyFormatter,
                cellClick: function (e, cell) {
                    if (e.target.closest('.comparison-history-btn')) {
                        openHistoryModal(cell.getRow().getData());
                    }
                },
            },
        ],
    });

    table.on('pageLoaded', function () {
        table.deselectRow();
    });

    document.getElementById('comparison-linked-sku-save-btn')?.addEventListener('click', saveLinkedSkuFromModal);
    document.getElementById('comparison-linked-sku-input')?.addEventListener('input', function () {
        renderLinkedSkuSuggestions(this.value);
    });
    document.getElementById('comparison-linked-sku-input')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveLinkedSkuFromModal();
        }
    });
    document.getElementById('comparison-linked-sku-suggestions')?.addEventListener('click', function (e) {
        const item = e.target.closest('.comparison-linked-sku-suggestion');
        if (!item) {
            return;
        }
        const input = document.getElementById('comparison-linked-sku-input');
        if (input) {
            input.value = item.dataset.sku || '';
        }
        renderLinkedSkuSuggestions('');
    });

    cdModalEl?.addEventListener('hidden.bs.modal', function () {
        comparisonBulkEditSkus = null;
    });

    function applyComparisonTableSearch() {
        if (!table) {
            return;
        }
        clearComparisonRowSelection();
        table.setPage(1);
    }

    let comparisonSearchTimer = null;
    function scheduleComparisonTableSearch() {
        clearTimeout(comparisonSearchTimer);
        comparisonSearchTimer = setTimeout(applyComparisonTableSearch, 300);
    }

    document.getElementById('comparison-search-sku')?.addEventListener('input', scheduleComparisonTableSearch);
    document.getElementById('comparison-search-parent')?.addEventListener('input', scheduleComparisonTableSearch);
    }

    document.getElementById('comparison-cd-import-btn')?.addEventListener('click', importComparisonGoogleSheet);
    document.getElementById('comparison-cd-autopopulate-suppliers-btn')?.addEventListener('click', autopopulateSupplierNamesFromList);
    document.getElementById('comparison-cd-roi-btn')?.addEventListener('click', openRoiModal);
    document.getElementById('comparison-roi-apply-manual-lmp')?.addEventListener('click', applyManualLmpToBoth);
    document.getElementById('comparison-roi-manual-lmp')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            applyManualLmpToBoth();
        }
    });
    document.getElementById('comparison-cd-copy-specs-btn')?.addEventListener('click', copySpecsToMemory);
    document.getElementById('comparison-cd-replace-specs-btn')?.addEventListener('click', replaceSpecsFromMemory);
    function updateCdGoogleUrlDotUI() {
        const input = document.getElementById('comparison-cd-google-url');
        const link = document.getElementById('comparison-cd-google-url-link');
        if (!input || !link) {
            return;
        }

        const url = input.value.trim();
        if (url) {
            link.href = url;
            link.classList.remove('comparison-clink-dot-empty');
            link.title = url;
            link.setAttribute('aria-label', 'Open Google Sheet');
        } else {
            link.href = '#';
            link.classList.add('comparison-clink-dot-empty');
            link.title = 'Click to set C link Sheet URL';
            link.setAttribute('aria-label', 'Set C link Sheet URL');
        }

        const dot = document.getElementById('comparison-cd-google-url-dot');
        if (dot) {
            dot.classList.toggle('comparison-clink-dot-muted', !url);
        }
    }

    function setCdGoogleUrlEditing(editing) {
        const wrap = document.getElementById('comparison-cd-google-url-wrap');
        const input = document.getElementById('comparison-cd-google-url');
        if (!wrap || !input) {
            return;
        }

        wrap.classList.toggle('is-editing', editing);
        if (editing) {
            input.focus();
            input.select();
        }
    }

    document.getElementById('comparison-cd-google-url-wrap')?.addEventListener('click', function (e) {
        const editBtn = e.target.closest('#comparison-cd-google-url-edit-btn');
        if (editBtn) {
            e.preventDefault();
            setCdGoogleUrlEditing(true);
            return;
        }

        const link = e.target.closest('#comparison-cd-google-url-link');
        if (!link) {
            return;
        }

        const input = document.getElementById('comparison-cd-google-url');
        const url = input?.value.trim() || '';
        if (!url) {
            e.preventDefault();
            setCdGoogleUrlEditing(true);
        }
    });

    document.getElementById('comparison-cd-google-url-link')?.addEventListener('dblclick', function (e) {
        e.preventDefault();
        setCdGoogleUrlEditing(true);
    });

    document.getElementById('comparison-cd-google-url')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.blur();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            setCdGoogleUrlEditing(false);
            updateCdGoogleUrlDotUI();
        }
    });

    document.getElementById('comparison-cd-google-url')?.addEventListener('blur', function () {
        window.setTimeout(function () {
            const wrap = document.getElementById('comparison-cd-google-url-wrap');
            if (wrap?.contains(document.activeElement)) {
                return;
            }
            setCdGoogleUrlEditing(false);
            updateCdGoogleUrlDotUI();
        }, 120);
    });

    document.getElementById('comparison-cd-google-url')?.addEventListener('input', updateCdGoogleUrlDotUI);
    document.getElementById('comparison-cd-google-url')?.addEventListener('change', () => scheduleAutoSaveComparisonSheet(600));
    document.getElementById('comparison-cd-google-tab')?.addEventListener('change', () => scheduleAutoSaveComparisonSheet(600));
    document.getElementById('comparison-cd-apply-fill-btn')?.addEventListener('click', applySheetFillColor);
    document.getElementById('comparison-cd-clear-fill-btn')?.addEventListener('click', clearSheetFillColor);
    document.getElementById('comparison-cd-move-row-up-btn')?.addEventListener('click', () => moveSheetRow('up'));
    document.getElementById('comparison-cd-move-row-down-btn')?.addEventListener('click', () => moveSheetRow('down'));
    document.getElementById('comparison-cd-insert-row-btn')?.addEventListener('click', insertSheetRow);
    document.getElementById('comparison-cd-delete-row-btn')?.addEventListener('click', deleteSheetRow);
    document.getElementById('comparison-cd-move-col-left-btn')?.addEventListener('click', () => moveSheetColumn('left'));
    document.getElementById('comparison-cd-move-col-right-btn')?.addEventListener('click', () => moveSheetColumn('right'));
    document.getElementById('comparison-cd-insert-col-btn')?.addEventListener('click', insertSheetColumn);
    document.getElementById('comparison-cd-delete-col-btn')?.addEventListener('click', deleteSheetColumn);

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('paste', function (e) {
        const cell = e.target.closest('.cd-sheet-cell[contenteditable="true"]');
        if (!cell) return;

        const colIndex = parseInt(cell.dataset.col, 10);
        if (isSheetSpecColumn(colIndex)) return;
        if (isCompanyNameRow(parseInt(cell.dataset.row, 10), currentSheetCells)) return;

        const pasted = (e.clipboardData || window.clipboardData)?.getData('text')?.replace(/\s+/g, ' ').trim();
        if (!pasted || !isSheetLinkUrl(pasted)) return;

        e.preventDefault();
        convertSheetCellValue(cell, pasted, false);
        scheduleAutoSaveComparisonSheet(400);
    }, true);

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('input', function (e) {
        if (e.target.closest('.cd-sheet-cell[contenteditable="true"]')) {
            scheduleAutoSaveComparisonSheet(800);
        }
    });

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('blur', function (e) {
        const cell = e.target.closest('.cd-sheet-cell[contenteditable="true"]');
        if (!cell) return;

        if (maybeConvertSheetCellToLink(cell)) {
            scheduleAutoSaveComparisonSheet(400);
            return;
        }

        if (maybeRefreshCompanyNameCell(cell)) {
            scheduleAutoSaveComparisonSheet(400);
            return;
        }

        readCellsFromEditor();
        const rowIndex = parseInt(cell.dataset.row, 10);
        const specCol = detectSpecColumnIndex(currentSheetCells);
        if (isSupplierNameRow(currentSheetCells, rowIndex, specCol)) {
            syncCommRowOnSheet();
            renderSheetEditor(currentSheetCells);
        }

        scheduleAutoSaveComparisonSheet(300);
    }, true);

    let activeCompanyTooltipCell = null;
    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('mouseover', function (e) {
        const cell = e.target.closest('.cd-sheet-cell-company');
        if (!cell || cell === activeCompanyTooltipCell) return;
        activeCompanyTooltipCell = cell;
        showSheetCellTooltip(e, cell.dataset.value || '');
    });

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('mousemove', function (e) {
        if (activeCompanyTooltipCell && e.target.closest('.cd-sheet-cell-company') === activeCompanyTooltipCell) {
            positionCdHover(e);
        }
    });

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('mouseout', function (e) {
        const cell = e.target.closest('.cd-sheet-cell-company');
        if (!cell || cell !== activeCompanyTooltipCell) return;
        const related = e.relatedTarget;
        if (related && cell.contains(related)) return;
        activeCompanyTooltipCell = null;
        hideCdHover();
    }, true);

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('click', function (e) {
        const commBtn = e.target.closest('.cd-sheet-comm-btn');
        if (commBtn) {
            e.preventDefault();
            e.stopPropagation();
            const supplierName = commBtn.dataset.supplierName || getSupplierNameForColumn(parseInt(commBtn.dataset.col, 10));
            openComparisonCommModal(supplierName);
            return;
        }

        if (e.target.closest('.cd-sheet-link-btn')) {
            e.stopPropagation();
            return;
        }

        const rowTarget = e.target.closest('.cd-select-row');
        if (rowTarget) {
            e.preventDefault();
            selectedSheetRow = parseInt(rowTarget.dataset.row, 10);
            if (Number.isNaN(selectedSheetRow)) {
                selectedSheetRow = null;
            }
            selectedSheetCell = null;
            applySheetSelectionHighlight();
            return;
        }

        const colTarget = e.target.closest('.cd-select-col');
        if (colTarget) {
            e.preventDefault();
            selectedSheetCol = parseInt(colTarget.dataset.col, 10);
            if (Number.isNaN(selectedSheetCol)) {
                selectedSheetCol = null;
            }
            selectedSheetCell = null;
            applySheetSelectionHighlight();
            return;
        }

        const cellTarget = e.target.closest('.cd-sheet-cell');
        if (cellTarget) {
            const rowIndex = parseInt(cellTarget.dataset.row, 10);
            const colIndex = parseInt(cellTarget.dataset.col, 10);
            if (!Number.isNaN(rowIndex) && !Number.isNaN(colIndex)) {
                selectedSheetRow = rowIndex;
                selectedSheetCol = colIndex;
                selectedSheetCell = { row: rowIndex, col: colIndex };
                applySheetSelectionHighlight();
            }
        }
    });

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('dblclick', function (e) {
        const dotCell = e.target.closest('.cd-sheet-cell-link, .cd-sheet-cell-company');
        if (!dotCell) return;
        e.preventDefault();
        hideCdHover();
        activeCompanyTooltipCell = null;
        const value = dotCell.dataset.value || '';
        const rowIndex = parseInt(dotCell.dataset.row, 10);
        const colIndex = parseInt(dotCell.dataset.col, 10);
        dotCell.outerHTML = sheetCellEditorHtml(value, rowIndex, colIndex, true);
        const editable = document.querySelector(`.cd-sheet-cell[data-row="${rowIndex}"][data-col="${colIndex}"]`);
        editable?.focus();
    });
    document.getElementById('cd-lmp-tab-btn')?.addEventListener('shown.bs.tab', function () {
        if (currentCdRow) {
            loadLmpTab(currentCdRow);
        }
    });

    document.getElementById('comparison-cd-lmp-add-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        submitAmazonLmpAddForm('comparison-cd', 'comparison-cd-lmp-add-form');
    });

    document.getElementById('comparison-lmp-add-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        submitAmazonLmpAddForm('comparison-lmp', 'comparison-lmp-add-form');
    });

    document.addEventListener('click', function (e) {
        const deleteBtn = e.target.closest('.comparison-delete-lmp-btn');
        if (!deleteBtn) {
            return;
        }
        e.preventDefault();
        deleteAmazonLmpCompetitor(deleteBtn);
    });
});
</script>
@endsection

@section('modal')
<div class="modal fade" id="comparisonRoiModal" tabindex="-1" aria-labelledby="comparisonRoiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="comparisonRoiModalLabel">
                    <i class="mdi mdi-percent"></i> Cost Calculator — ROI%
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div id="comparison-roi-manual-lmp-wrap" class="d-none border rounded p-2 mb-2 bg-light">
                    <div class="small text-muted mb-2" id="comparison-roi-manual-lmp-hint"></div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <label class="small mb-0 fw-semibold" for="comparison-roi-manual-lmp">Manual LMP (both):</label>
                        <input type="text" id="comparison-roi-manual-lmp" class="form-control form-control-sm" style="width: 120px;" placeholder="LMP $">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="comparison-roi-apply-manual-lmp">
                            Apply LMP × 0.9 to both
                        </button>
                    </div>
                </div>
                <div class="small text-end mb-2" id="comparison-roi-save-status"></div>
                <div class="table-responsive">
                    <table class="table table-bordered comparison-roi-table mb-0">
                        <thead>
                            <tr>
                                <th>cost calculator</th>
                                <th>CP</th>
                                <th>CBM</th>
                                <th>GW LB</th>
                                <th>Shipping</th>
                                <th>Sale</th>
                                <th>lmp</th>
                                <th>P%</th>
                                <th>ROI (G)</th>
                            </tr>
                        </thead>
                        <tbody id="comparison-roi-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
