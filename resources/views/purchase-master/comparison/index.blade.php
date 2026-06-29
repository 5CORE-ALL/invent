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

    .cd-sheet-table .cd-label-cell:focus {
        box-shadow: inset 0 0 0 2px #2563eb;
    }

    .cd-sheet-fill-toolbar {
        border-top: 1px solid #dee2e6;
        margin-top: 10px;
        padding-top: 10px;
    }

    .cd-sheet-fill-toolbar .form-control-color {
        width: 42px;
        height: 32px;
        padding: 2px;
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
                        <input type="text" id="comparison-search" class="form-control form-control-sm" style="max-width: 280px;" placeholder="Search SKU or Parent...">
                        <span class="badge bg-info text-white fs-6 px-3 py-2">
                            <i class="fas fa-list"></i> Total SKUs: <strong id="comparison-total-badge">0</strong>
                        </span>
                    </div>
                    <div id="comparison-table"></div>
                </div>
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
                            <div class="row g-2 align-items-end">
                                <div class="col-lg-5">
                                    <label class="form-label small mb-1">C link Sheet URL</label>
                                    <input type="url" id="comparison-cd-google-url" class="form-control form-control-sm" placeholder="Set C link in the table — Google Sheet URL loads automatically" readonly>
                                </div>
                                <div class="col-lg-2">
                                    <label class="form-label small mb-1">Tab name</label>
                                    <input type="text" id="comparison-cd-google-tab" class="form-control form-control-sm" value="Sheet1">
                                </div>
                                <div class="col-lg-5 d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-success" id="comparison-cd-import-btn">
                                        <i class="fab fa-google"></i> Refresh from C link
                                    </button>
                                    <button type="button" class="btn btn-sm btn-info text-white" id="comparison-cd-autopopulate-suppliers-btn" title="Fill supplier name row from supplier.list using SKU and linked SKUs">
                                        <i class="mdi mdi-account-multiple-plus"></i> Autopopulate Suppliers
                                    </button>
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Row actions">
                                        <button type="button" class="btn btn-outline-secondary" id="comparison-cd-move-row-up-btn" title="Move selected row up">
                                            <i class="mdi mdi-arrow-up"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="comparison-cd-move-row-down-btn" title="Move selected row down">
                                            <i class="mdi mdi-arrow-down"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="comparison-cd-insert-row-btn" title="Insert row after selected row">
                                            <i class="mdi mdi-table-row-plus-after"></i> Insert Row
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" id="comparison-cd-delete-row-btn" title="Delete selected row">
                                            <i class="mdi mdi-table-row-remove"></i> Delete Row
                                        </button>
                                    </div>
                                    <div class="btn-group btn-group-sm" role="group" aria-label="Column actions">
                                        <button type="button" class="btn btn-outline-secondary" id="comparison-cd-move-col-left-btn" title="Move selected column left">
                                            <i class="mdi mdi-arrow-left"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="comparison-cd-move-col-right-btn" title="Move selected column right">
                                            <i class="mdi mdi-arrow-right"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="comparison-cd-insert-col-btn" title="Insert column after selected column">
                                            <i class="mdi mdi-table-column-plus-after"></i> Insert Column
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" id="comparison-cd-delete-col-btn" title="Delete selected column">
                                            <i class="mdi mdi-table-column-remove"></i> Delete Column
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="cd-sheet-fill-toolbar d-flex flex-wrap align-items-center gap-2">
                                <label class="small mb-0 fw-semibold" for="comparison-cd-fill-color">Fill color</label>
                                <input type="color" id="comparison-cd-fill-color" class="form-control form-control-color" value="#f97316" title="Pick fill color">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Fill target">
                                    <input type="radio" class="btn-check" name="comparison-cd-fill-target" id="comparison-cd-fill-target-cell" value="cell" checked>
                                    <label class="btn btn-outline-secondary" for="comparison-cd-fill-target-cell">Cell</label>
                                    <input type="radio" class="btn-check" name="comparison-cd-fill-target" id="comparison-cd-fill-target-row" value="row">
                                    <label class="btn btn-outline-secondary" for="comparison-cd-fill-target-row">Row</label>
                                    <input type="radio" class="btn-check" name="comparison-cd-fill-target" id="comparison-cd-fill-target-col" value="col">
                                    <label class="btn btn-outline-secondary" for="comparison-cd-fill-target-col">Column</label>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="comparison-cd-apply-fill-btn" title="Apply fill color to selected cell, row, or column">
                                    <i class="mdi mdi-format-color-fill"></i> Apply Fill
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="comparison-cd-clear-fill-btn" title="Clear fill color from selected cell, row, or column">
                                    <i class="mdi mdi-format-color-marker-cancel"></i> Clear Fill
                                </button>
                            </div>
                            <div class="cd-sheet-status mt-2" id="comparison-cd-sheet-status">CD uses the C link Google Sheet. Changes auto-save. Click a row #, column letter, or cell to select, then apply fill color or move/insert/delete.</div>
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
                            <div class="fw-semibold mb-2">LMP Competitors</div>
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
                    <i class="fa fa-shopping-cart"></i> LMP Competitors for SKU: <span id="comparison-lmp-modal-sku"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
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
    const updateLinkUrl = @json(route('update.rfq.link'));
    const cdHoverPreview = document.getElementById('cd-hover-preview');
    const cdModalEl = document.getElementById('comparisonCdModal');
    const cdModal = cdModalEl ? new bootstrap.Modal(cdModalEl) : null;
    const historyModalEl = document.getElementById('comparisonHistoryModal');
    const historyModal = historyModalEl ? new bootstrap.Modal(historyModalEl) : null;
    const lmpModalEl = document.getElementById('comparisonLmpModal');
    const lmpModal = lmpModalEl ? new bootstrap.Modal(lmpModalEl) : null;

    let currentCdRow = null;
    let currentSheetCells = [];
    let currentSheetFormats = { cells: {}, rows: {}, cols: {} };
    let selectedSheetRow = null;
    let selectedSheetCol = null;
    let selectedSheetCell = null;
    let lmpLoadedForSku = null;
    let table;

    const SPEC_COLUMN_COLOR = '#fed7aa';
    const LOWEST_PRICE_COLOR = '#bbf7d0';
    const SUPPLIER_NAME_ROW_COLOR = '#42c4f0';
    let autoSheetFormats = { cells: {}, rows: {}, cols: {} };
    let sheetEditorHydrating = false;
    let sheetAutoSaveTimer = null;
    let sheetSaveInFlight = false;
    let sheetSaveQueued = false;
    let tableRefreshTimer = null;

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

    function buildCdHoverHtml(row) {
        const clink = (row.clink || '').trim();
        const clinkIsSheet = !!row.clink_is_sheet || isGoogleSheetUrl(clink);
        const lmpPrice = row.lmp_price;
        const count = parseInt(row.lmp_entries_total, 10) || 0;
        const hasSheet = !!row.has_sheet_data;
        const supplierCount = parseInt(row.sheet_supplier_count, 10) || 0;

        let sheetLabel = 'No sheet saved';
        if (hasSheet) {
            sheetLabel = supplierCount + ' supplier column(s) saved';
        } else if (clinkIsSheet) {
            sheetLabel = 'C link sheet ready — click to load';
        }

        let html = '';
        html += `<div><span class="cd-hover-label">Sheet:</span> ${sheetLabel}</div>`;
        html += `<div><span class="cd-hover-label">C link:</span> ${clink ? escapeHtml(clink) : '—'}</div>`;
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

    function renderCompetitorsList(competitors, lowestPrice) {
        if (!competitors || competitors.length === 0) {
            return '<div class="alert alert-info mb-0"><i class="fa fa-info-circle"></i> No competitors found for this SKU.</div>';
        }

        let html = '<div class="table-responsive"><table class="table table-hover table-bordered table-sm mb-0">';
        html += `<thead class="table-light"><tr>
            <th>#</th><th>Image</th><th>ASIN</th><th>Product Title</th><th>Seller</th>
            <th>Price</th><th>Rating</th><th>Reviews</th><th>Link</th>
        </tr></thead><tbody>`;

        competitors.forEach(function (item, index) {
            const isLowest = lowestPrice != null && Math.abs(parseFloat(item.price) - parseFloat(lowestPrice)) < 0.01;
            const rowClass = isLowest ? 'table-success' : '';
            const priceFormatted = '$' + parseFloat(item.price).toFixed(2);
            const productLink = item.link || item.product_link || '#';
            const productTitle = item.title || item.product_title || 'N/A';
            const sellerName = item.seller_name || '—';
            const imageUrl = item.image || '';
            const imageHtml = imageUrl
                ? `<img src="${escapeHtmlAttr(imageUrl)}" style="width:50px;height:50px;object-fit:contain;" alt="">`
                : '<span class="text-muted">—</span>';
            const rating = item.rating
                ? `<span style="color:#ffc107;">${parseFloat(item.rating).toFixed(1)} <i class="fa fa-star"></i></span>`
                : '<span class="text-muted">—</span>';
            const reviews = item.reviews
                ? `<span>${parseInt(item.reviews, 10).toLocaleString()}</span>`
                : '<span class="text-muted">—</span>';

            html += `<tr class="${rowClass}">
                <td class="text-center"><strong>${index + 1}</strong></td>
                <td class="text-center">${imageHtml}</td>
                <td><span class="text-primary fw-semibold" style="font-size:11px;">${escapeHtmlAttr(item.asin || 'N/A')}</span></td>
                <td style="font-size:11px;" title="${escapeHtmlAttr(productTitle)}">${escapeHtml(productTitle.length > 60 ? productTitle.substring(0, 60) + '…' : productTitle)}</td>
                <td style="font-size:11px;">${escapeHtml(sellerName)}</td>
                <td><strong>${priceFormatted}${isLowest ? ' <i class="fa fa-trophy text-success"></i>' : ''}</strong></td>
                <td class="text-center">${rating}</td>
                <td class="text-center">${reviews}</td>
                <td class="text-center">
                    <a href="${escapeHtmlAttr(productLink)}" target="_blank" rel="noopener" class="btn btn-sm btn-info" title="View product">
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

    function loadComparisonLmpModal(sku) {
        if (!lmpModal || !sku) return;

        document.getElementById('comparison-lmp-modal-sku').textContent = sku;
        document.getElementById('comparison-lmp-data-list').innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 mb-0">Loading competitors...</p>
            </div>
        `;
        lmpModal.show();

        fetch(`${competitorsUrl}?sku=${encodeURIComponent(sku)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('comparison-lmp-data-list').innerHTML =
                    renderCompetitorsList(data.competitors || [], data.lowest_price);
            } else {
                document.getElementById('comparison-lmp-data-list').innerHTML =
                    '<div class="alert alert-warning mb-0"><i class="fa fa-info-circle"></i> No competitors found for this SKU.</div>';
            }
        })
        .catch(() => {
            document.getElementById('comparison-lmp-data-list').innerHTML =
                '<div class="alert alert-warning mb-0"><i class="fa fa-info-circle"></i> Could not load competitor data.</div>';
        });
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
        return label !== '' && label.includes('supplier name');
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
        return document.querySelector('input[name="comparison-cd-fill-target"]:checked')?.value || 'cell';
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
            let rowHtml = `<tr class="${selectedSheetRow === r ? 'cd-row-selected' : ''}"><td class="cd-row-num cd-select-row${rowSelectedClass}" data-row="${r}" title="Select row ${r + 1}">${r + 1}</td>`;
            for (let c = 0; c < colCount; c++) {
                const value = row[c] ?? '';
                const isSpec = c === specCol;
                const colSelectedClass = selectedSheetCol === c ? ' cd-col-selected' : '';
                const cellSelectedClass = selectedSheetCell && selectedSheetCell.row === r && selectedSheetCell.col === c
                    ? ' cd-cell-selected'
                    : '';
                rowHtml += `<td class="${isSpec ? 'cd-label-cell' : ''}${colSelectedClass}${cellSelectedClass}"${sheetCellTdStyle(r, c, isSpec)}>${sheetCellEditorHtml(value, r, c, isSpec)}</td>`;
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
        body.querySelectorAll('tr').forEach(tr => {
            const row = [];
            tr.querySelectorAll('.cd-sheet-cell').forEach(cell => {
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
        if (!el) return;
        el.textContent = message;
        el.classList.toggle('text-danger', !!isError);
        el.classList.toggle('text-success', !isError && message.toLowerCase().includes('saved'));
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
        setSheetStatus('Saving...', false);

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

            setSheetStatus(`Auto-saved at ${new Date().toLocaleTimeString()}`, false);

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
        document.getElementById('comparison-cd-google-tab').value = data.google_sheet_tab || 'Sheet1';
        currentSheetFormats = normalizeSheetFormats(data.formats || {});
        applyAutoSheetFormatsFromPayload(data, data.cells || []);
        renderSheetEditor(data.cells || []);
        sheetEditorHydrating = false;

        let statusMsg = 'New sheet — changes auto-save.';
        if (data.updated_at) {
            statusMsg = `Last saved ${data.updated_at} by ${data.updated_by || 'N/A'}`;
        } else if (data.has_sheet_data) {
            statusMsg = 'Comparison sheet loaded from C link.';
        } else if (isGoogleSheetUrl(clink)) {
            statusMsg = 'C link sheet URL found. Click Refresh from C link to load data.';
        } else {
            statusMsg = 'Set a Google Sheet URL in the C link column first.';
        }
        if (data.sheet_file) {
            statusMsg += ` Stored in ${data.sheet_file}.`;
        }
        setSheetStatus(statusMsg, false);
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

        fetch(`${sheetGetUrl}?sku=${encodeURIComponent(row.sku || '')}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to load sheet.');
            }

            applySheetPayload(data, row);

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

        document.getElementById('comparison-cd-lmp-list').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 mb-0">Loading competitors...</p>
            </div>
        `;

        fetch(`${competitorsUrl}?sku=${encodeURIComponent(sku)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('comparison-cd-lmp-list').innerHTML =
                    renderCompetitorsList(data.competitors || [], data.lowest_price);
            } else {
                document.getElementById('comparison-cd-lmp-list').innerHTML =
                    '<div class="alert alert-warning mb-0"><i class="fa fa-info-circle"></i> No competitors found for this SKU.</div>';
            }
        })
        .catch(() => {
            document.getElementById('comparison-cd-lmp-list').innerHTML =
                '<div class="alert alert-warning mb-0"><i class="fa fa-info-circle"></i> Could not load competitor data.</div>';
        });
    }

    function findSupplierNameRowIndex(cells, specCol) {
        for (let rowIndex = 0; rowIndex < cells.length; rowIndex++) {
            const label = String((cells[rowIndex] || [])[specCol] || '').trim().toLowerCase();
            if (label.includes('supplier name') && !label.includes('company name')) {
                return rowIndex;
            }
        }
        return null;
    }

    function ensureSupplierColumnCount(cells, specCol, neededCount) {
        const firstSupplierCol = specCol + 1;
        const colCount = Math.max(...cells.map(row => row.length), firstSupplierCol + 1);
        const currentSupplierCols = Math.max(0, colCount - firstSupplierCol);
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

    function autopopulateSupplierNamesFromList() {
        if (!currentCdRow) {
            setSheetStatus('Open a comparison row first.', true);
            return;
        }

        readCellsFromEditor();
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const supplierRowIndex = findSupplierNameRowIndex(currentSheetCells, specCol);
        if (supplierRowIndex === null) {
            setSheetStatus('Could not find a Supplier Name row in the sheet.', true);
            return;
        }

        const linkedSkus = Array.isArray(currentCdRow.linked_skus) && currentCdRow.linked_skus.length
            ? currentCdRow.linked_skus.slice()
            : [currentCdRow.sku].filter(Boolean);

        const btn = document.getElementById('comparison-cd-autopopulate-suppliers-btn');
        if (btn) btn.disabled = true;
        setSheetStatus('Loading suppliers from supplier.list...', false);

        const params = new URLSearchParams();
        params.set('sku', currentCdRow.sku || '');
        if (currentCdRow.parent) {
            params.set('parent', currentCdRow.parent);
        }
        if (currentCdRow.category) {
            params.set('category', currentCdRow.category);
        }
        linkedSkus.forEach(sku => params.append('linked_skus[]', sku));

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
                setSheetStatus('No suppliers matched this SKU or linked SKUs on supplier.list.', true);
                return;
            }

            currentSheetCells = ensureSupplierColumnCount(currentSheetCells, specCol, suppliers.length);
            const firstSupplierCol = specCol + 1;

            suppliers.forEach((supplier, index) => {
                if (!currentSheetCells[supplierRowIndex]) {
                    currentSheetCells[supplierRowIndex] = [];
                }
                currentSheetCells[supplierRowIndex][firstSupplierCol + index] = supplier.name || '';
            });

            renderSheetEditor(currentSheetCells);
            setSheetStatus(`Autopopulated ${suppliers.length} supplier name(s) from supplier.list.`, false);
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
        if (skuBadge) {
            skuBadge.textContent = row.sku || '';
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

    function cdFormatter(cell) {
        const row = cell.getRow().getData();
        const hasSheet = !!row.has_sheet_data;
        const clinkIsSheet = !!row.clink_is_sheet || isGoogleSheetUrl(row.clink);
        const title = hasSheet
            ? 'View/edit comparison sheet'
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
        if (!skus.length && row.sku) {
            skus = [row.sku];
        }

        const badges = skus.length
            ? skus.map(sku => {
                const href = supplierListCategoryUrl(category, sku);
                return `<a href="${escapeHtmlAttr(href)}" target="_blank" rel="noopener noreferrer"
                    class="badge bg-info-subtle text-dark border me-1 mb-1 text-decoration-none linked-sku-badge"
                    title="Open ${escapeHtmlAttr(category || 'supplier.list')} for ${escapeHtmlAttr(sku)}">${escapeHtml(sku)}</a>`;
            }).join('')
            : '<span class="text-muted fst-italic">No SKUs</span>';

        const manageHref = supplierListCategoryUrl(category, row.sku || '');
        const manageTitle = category
            ? `Manage linked SKUs on supplier.list (${category})`
            : 'Manage linked SKUs on supplier.list';

        return `<div class="d-flex flex-column align-items-start py-1">
            <div class="mb-1" style="line-height:1.6;">${badges}</div>
            <a href="${escapeHtmlAttr(manageHref)}" target="_blank" rel="noopener noreferrer"
                class="btn btn-sm btn-outline-primary" title="${escapeHtmlAttr(manageTitle)}"
                style="padding:2px 8px;" onclick="event.stopPropagation();">
                <i class="mdi mdi-plus"></i>
            </a>
        </div>`;
    }

    function clinkFormatter(cell) {
        const url = (cell.getValue() || '').trim();
        if (!url) {
            return '<span class="text-muted">-</span>';
        }

        return `<div style="display:flex;align-items:center;justify-content:center;">
            <a href="${escapeHtmlAttr(url)}" target="_blank" rel="noopener noreferrer"
                class="comparison-clink-dot-link"
                title="Open comparison link" aria-label="Open comparison link">
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
            }),
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                alert('Error: ' + (res.message || 'Could not save C link.'));
            }
        })
        .catch(() => alert('Could not save C link.'));
    }

    table = new Tabulator('#comparison-table', {
        ajaxURL: dataUrl,
        ajaxConfig: 'GET',
        ajaxResponse: function (url, params, response) {
            const rows = response.data || [];
            document.getElementById('comparison-total-badge').textContent = rows.length;
            return rows;
        },
        layout: 'fitColumns',
        pagination: true,
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, 200],
        movableColumns: true,
        resizableColumns: true,
        height: '650px',
        placeholder: 'No comparison data found',
        columns: [
            {
                title: 'S.No',
                formatter: 'rownum',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 70,
                headerSort: false,
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
                title: 'Linked SKU',
                field: 'linked_skus',
                hozAlign: 'left',
                headerHozAlign: 'center',
                width: 220,
                headerSort: false,
                cssClass: 'linked-sku-col',
                formatter: linkedSkuFormatter,
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
                    openComparisonModal(cell.getRow().getData());
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

    document.getElementById('comparison-search').addEventListener('input', function () {
        const value = this.value.trim().toLowerCase();
        table.setFilter(function (data) {
            if (!value) return true;
            return (data.sku || '').toLowerCase().includes(value)
                || (data.parent || '').toLowerCase().includes(value);
        });
    });

    document.getElementById('comparison-cd-import-btn')?.addEventListener('click', importComparisonGoogleSheet);
    document.getElementById('comparison-cd-autopopulate-suppliers-btn')?.addEventListener('click', autopopulateSupplierNamesFromList);
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
});
</script>
@endsection
