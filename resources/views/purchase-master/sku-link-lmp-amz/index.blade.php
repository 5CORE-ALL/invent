@extends('layouts.vertical', ['title' => 'SKU Link LMP AMZ AMZ'])

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    .linked-sku-badge-wrap .sku-link-lmp-amz-remove {
        font-size: 0.55rem;
        opacity: 0.65;
        padding: 0;
        margin-left: 2px;
    }

    .linked-sku-badge-wrap .sku-link-lmp-amz-remove:hover {
        opacity: 1;
    }

    .tabulator .tabulator-cell.sku-link-lmp-amz-m-col {
        cursor: text;
    }

    .tabulator .tabulator-cell.sku-link-lmp-amz-m-col input {
        max-width: 36px;
        text-align: center;
        text-transform: uppercase;
    }

    #skuLinkLmpAmzLmpModal .modal-body {
        max-height: calc(100vh - 200px);
        overflow-y: auto;
    }

    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-add-comp-row .form-control {
        min-width: 0;
    }

    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-add-comp-row .sku-link-lmp-amz-add-comp-asin {
        flex: 1.4 1 140px;
    }

    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-add-comp-row .sku-link-lmp-amz-add-comp-price,
    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-add-comp-row .sku-link-lmp-amz-add-comp-shipping {
        flex: 0.7 1 72px;
        max-width: 96px;
    }

    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-add-comp-row .sku-link-lmp-amz-add-comp-link,
    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-add-comp-row .sku-link-lmp-amz-add-comp-title {
        flex: 1.2 1 120px;
    }

    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-add-comp-row .sku-link-lmp-amz-add-comp-submit {
        flex: 0 0 auto;
        white-space: nowrap;
    }

    .sku-link-lmp-amz-play-group {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-radius: 50px;
        overflow: hidden;
        padding: 2px;
        background: #f8f9fa;
        display: inline-flex;
        align-items: center;
    }

    .sku-link-lmp-amz-play-group button {
        padding: 0;
        border-radius: 50% !important;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 3px;
        transition: all 0.2s ease;
        border: 1px solid #dee2e6;
        background: white;
        cursor: pointer;
    }

    .sku-link-lmp-amz-play-group button:hover {
        background-color: #f1f3f5 !important;
        transform: scale(1.05);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .sku-link-lmp-amz-play-group button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
    }

    .sku-link-lmp-amz-play-group button i {
        font-size: 1.1rem;
    }

    #sku-link-lmp-amz-play-auto {
        color: #28a745;
    }

    #sku-link-lmp-amz-play-auto:hover {
        background-color: #28a745 !important;
        color: white !important;
    }

    #sku-link-lmp-amz-play-pause {
        color: #ffc107;
        display: none;
    }

    #sku-link-lmp-amz-play-pause:hover {
        background-color: #ffc107 !important;
        color: white !important;
    }

    #sku-link-lmp-amz-play-backward,
    #sku-link-lmp-amz-play-forward {
        color: #007bff;
    }

    #sku-link-lmp-amz-play-backward:hover,
    #sku-link-lmp-amz-play-forward:hover {
        background-color: #007bff !important;
        color: white !important;
    }

    .sku-link-lmp-amz-play-group button.is-active-nav {
        background-color: #007bff !important;
        color: white !important;
        border-color: #007bff;
    }

    .tabulator-cell[tabulator-field="bulk_edit"] {
        padding-top: 4px !important;
        padding-bottom: 4px !important;
    }

    .tabulator-cell[tabulator-field="history_view"] {
        padding-top: 4px !important;
        padding-bottom: 4px !important;
    }

    .sku-link-lmp-amz-history-cell {
        line-height: 1.25;
    }

    .sku-link-lmp-amz-history-dot-icon {
        display: inline-block;
        font-size: 1.35rem;
        line-height: 1;
        color: #2563eb;
    }

    .sku-link-lmp-amz-history-dot:hover .sku-link-lmp-amz-history-dot-icon {
        color: #1d4ed8;
    }

    .sku-link-lmp-amz-history-table th,
    .sku-link-lmp-amz-history-table td {
        font-size: 12px;
        vertical-align: middle;
    }

    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-lowest-row,
    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-lowest-row > td {
        background-color: #dbeafe !important;
        color: #1e3a8a;
    }

    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-lowest-row:hover > td {
        background-color: #bfdbfe !important;
    }

    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-five-core-row,
    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-five-core-row > td,
    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-core-match-row,
    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-core-match-row > td {
        background-color: #fffef2 !important;
        color: #713f12;
        --bs-table-bg-type: #fffef2;
        --bs-table-striped-bg: #fffef2;
        --bs-table-hover-bg: #fefce8;
    }

    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-five-core-row:hover > td,
    #skuLinkLmpAmzLmpModal .sku-link-lmp-amz-core-match-row:hover > td {
        background-color: #fefce8 !important;
    }

    .sku-link-lmp-amz-competitor-thumb {
        cursor: zoom-in;
    }

    #sku-link-lmp-amz-image-hover-preview {
        position: fixed;
        z-index: 10060;
        pointer-events: auto;
        border: 1px solid #ccc;
        background: #fff;
        padding: 4px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.18);
        border-radius: 6px;
        transition: opacity 0.15s ease;
    }

    #sku-link-lmp-amz-image-hover-preview img {
        width: 480px;
        height: 480px;
        object-fit: contain;
        display: block;
    }

    .sku-link-lmp-amz-suggestion-item {
        cursor: pointer;
    }

    .sku-link-lmp-amz-suggestion-item .form-check-input {
        cursor: pointer;
        flex-shrink: 0;
    }

    .sku-link-lmp-amz-selected-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin: 0 4px 4px 0;
        padding: 2px 8px;
        border-radius: 999px;
        background: #e0e7ff;
        font-size: 12px;
    }

    .sku-link-lmp-amz-selected-chip button {
        border: 0;
        background: transparent;
        padding: 0;
        line-height: 1;
        font-size: 14px;
        color: #64748b;
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
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'sku_link_lmp_amz'])
                        <h4 class="mb-0">
                            <i class="mdi mdi-link-variant"></i> SKU Link LMP AMZ
                        </h4>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
                        <div class="btn-group sku-link-lmp-amz-play-group" role="group" aria-label="Parent navigation">
                            <button type="button" id="sku-link-lmp-amz-play-backward" class="btn btn-light rounded-circle" title="Previous parent">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button type="button" id="sku-link-lmp-amz-play-pause" class="btn btn-light rounded-circle" title="Show all products">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button type="button" id="sku-link-lmp-amz-play-auto" class="btn btn-light rounded-circle" title="Start parent navigation">
                                <i class="fas fa-play"></i>
                            </button>
                            <button type="button" id="sku-link-lmp-amz-play-forward" class="btn btn-light rounded-circle" title="Next parent">
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>
                        <input type="text" id="sku-link-lmp-amz-search-parent" class="form-control form-control-sm" style="max-width: 220px;" placeholder="Search Parent...">
                        <input type="text" id="sku-link-lmp-amz-search-sku" class="form-control form-control-sm" style="max-width: 220px;" placeholder="Search SKU...">
                        <select id="sku-link-lmp-amz-history-alert-filter" class="form-select form-select-sm" style="max-width: 200px;" title="Filter by history alert">
                            <option value="">All rows</option>
                            <option value="1">History alert (&gt;15 days)</option>
                        </select>
<span id="sku-link-lmp-amz-play-status" class="text-muted small"></span>
                    </div>
                    <div id="sku-link-lmp-amz-bulk-edit-badge" class="d-none mb-2 p-2 rounded border bg-light align-items-center gap-2 flex-wrap" style="min-height: 40px;">
                        <span class="fw-semibold text-dark" id="sku-link-lmp-amz-bulk-edit-count">0 selected</span>
                        <span class="text-muted small">Select rows with checkboxes (header checkbox selects all filtered rows), then use <strong>Bulk Edit</strong>.</span>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="sku-link-lmp-amz-bulk-edit-btn" title="Bulk edit selected rows">
                            <i class="mdi mdi-pencil-box-multiple-outline me-1"></i> Bulk Edit
                        </button>
                    </div>
                    <div id="sku-link-lmp-amz-table"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="skuLinkLmpAmzHistoryModal" tabindex="-1" aria-labelledby="skuLinkLmpAmzHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="skuLinkLmpAmzHistoryModalLabel">
                    <i class="fas fa-history"></i> LMP ID History — <span id="sku-link-lmp-amz-history-modal-sku"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="sku-link-lmp-amz-history-loading" class="text-center py-4">
                    <div class="spinner-border text-secondary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 mb-0">Loading history...</p>
                </div>
                <div id="sku-link-lmp-amz-history-empty" class="alert alert-info mb-0 d-none">
                    <i class="fa fa-info-circle"></i> No competitor ID history found for this SKU.
                </div>
                <div id="sku-link-lmp-amz-history-error" class="alert alert-danger mb-0 d-none"></div>
                <div id="sku-link-lmp-amz-history-table-wrap" class="table-responsive d-none" style="max-height: 65vh;">
                    <table class="table table-bordered table-hover sku-link-lmp-amz-history-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 130px;">Date</th>
                                <th style="width: 90px;">Time</th>
                                <th style="width: 120px;">User</th>
                                <th style="width: 90px;">Action</th>
                                <th style="width: 130px;">Item ID</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody id="sku-link-lmp-amz-history-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="skuLinkLmpAmzBulkEditModal" tabindex="-1" aria-labelledby="skuLinkLmpAmzBulkEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold d-flex align-items-center m-0" id="skuLinkLmpAmzBulkEditModalLabel">
                    <i class="mdi mdi-pencil-box-multiple-outline me-2"></i>
                    Bulk Edit
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border d-flex align-items-center mb-3 py-2 px-3" role="status">
                    <i class="mdi mdi-information-outline text-primary me-2"></i>
                    <div class="small">
                        Editing <strong id="sku-link-lmp-amz-bulk-target-count">0</strong> row(s):
                        <span class="text-muted" id="sku-link-lmp-amz-bulk-target-skus"></span>
                    </div>
                </div>
                <p class="text-muted small mb-3">Only fields you fill in below will be applied. Leave a field blank to keep its current value.</p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="sku-link-lmp-amz-bulk-m" class="form-label fw-semibold">M</label>
                        <input type="text" id="sku-link-lmp-amz-bulk-m" class="form-control form-control-sm" maxlength="1" placeholder="— Keep current —" style="max-width: 72px; text-align: center; text-transform: uppercase;">
                        <div class="form-text small">Single character multiplier for E LMP.</div>
                    </div>
                    <div class="col-md-8">
                        <label for="sku-link-lmp-amz-bulk-link-sku" class="form-label fw-semibold">Link another SKU</label>
                        <input type="text" id="sku-link-lmp-amz-bulk-link-sku" class="form-control form-control-sm" placeholder="— Keep current —" autocomplete="off">
                        <div class="form-text small">Links this SKU to every selected row (same as the + column modal).</div>
                    </div>
                </div>
                <div class="mt-3" id="sku-link-lmp-amz-bulk-link-together-wrap" style="display:none;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="sku-link-lmp-amz-bulk-link-together-btn">
                        <i class="mdi mdi-link-variant me-1"></i> Link all selected SKUs together
                    </button>
                    <div class="form-text small">When 2+ rows are selected, link them into one LMP group (same as + with multi-select).</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="sku-link-lmp-amz-bulk-edit-apply-btn">
                    <i class="mdi mdi-check me-1"></i> Apply
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="skuLinkLmpAmzModal" tabindex="-1" aria-labelledby="skuLinkLmpAmzModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="skuLinkLmpAmzModalLabel">Sku Link LMP AMZ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Link one or more SKUs to <strong id="sku-link-lmp-amz-source"></strong>. All linked SKUs will show each other.</p>
                <label for="sku-link-lmp-amz-input" class="form-label mb-1">Search SKU to link</label>
                <input type="text" id="sku-link-lmp-amz-input" class="form-control" placeholder="Search or enter SKU..." autocomplete="off">
                <div id="sku-link-lmp-amz-suggestions" class="list-group mt-2 d-none" style="max-height: 220px; overflow-y: auto;"></div>
                <div id="sku-link-lmp-amz-selected-wrap" class="mt-2 d-none">
                    <div class="small text-muted mb-1">Selected to link (<span id="sku-link-lmp-amz-selected-count">0</span>):</div>
                    <div id="sku-link-lmp-amz-selected-skus" class="d-flex flex-wrap"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="sku-link-lmp-amz-save-btn">
                    <i class="mdi mdi-link"></i> <span id="sku-link-lmp-amz-save-btn-label">Link SKU(s)</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="skuLinkLmpAmzLmpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fa fa-amazon"></i> Amz Competitors for SKU: <span id="sku-link-lmp-amz-lmp-sku"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="card mb-3">
                    <div class="card-body py-2 px-3">
                        <form id="sku-link-lmp-amz-add-competitor-form">
                            <input type="hidden" id="sku-link-lmp-amz-add-comp-sku" name="sku">
                            <div class="d-flex flex-nowrap align-items-center gap-2 sku-link-lmp-amz-add-comp-row">
                                <input type="text" class="form-control form-control-sm sku-link-lmp-amz-add-comp-asin" id="sku-link-lmp-amz-add-comp-asin" name="asin" required placeholder="ASIN *" aria-label="eBay Item ID">
                                <input type="number" class="form-control form-control-sm sku-link-lmp-amz-add-comp-price" id="sku-link-lmp-amz-add-comp-price" name="price" step="0.01" min="0" required placeholder="Price *" aria-label="Price">
                                <input type="url" class="form-control form-control-sm sku-link-lmp-amz-add-comp-link" id="sku-link-lmp-amz-add-comp-link" name="product_link" placeholder="Product Link" aria-label="Product Link">
                                <input type="text" class="form-control form-control-sm sku-link-lmp-amz-add-comp-title" id="sku-link-lmp-amz-add-comp-title" name="product_title" placeholder="Product Title (optional)" aria-label="Product Title">
                                <button type="submit" class="btn btn-success btn-sm sku-link-lmp-amz-add-comp-submit">
                                    <i class="fa fa-plus"></i> Add
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div id="sku-link-lmp-amz-lmp-data-list">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading competitors...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const dataUrl = @json(route('sku.link.lmp.amz.data'));
    const parentsUrl = @json(route('sku.link.lmp.amz.parents'));
    const lmpDataUrl = @json(route('sku.link.lmp.amz.lmp-data'));
    const amazonLmpAddUrl = @json(route('amazon.lmp.add'));
    const amazonLmpDeleteUrl = @json(route('amazon.lmp.delete'));
    const linkedSkuAddUrl = @json(route('sku.link.lmp.amz.linked-skus.add'));
    const linkedSkuBulkLinkUrl = @json(route('sku.link.lmp.amz.linked-skus.bulk-link'));
    const linkedSkuRemoveUrl = @json(route('sku.link.lmp.amz.linked-skus.remove'));
    const saveMUrl = @json(route('sku.link.lmp.amz.save-m'));
    const bulkSaveMUrl = @json(route('sku.link.lmp.amz.save-m-bulk'));
    const filteredSkusUrl = @json(route('sku.link.lmp.amz.filtered-skus'));
    const historyUrl = @json(route('sku.link.lmp.amz.history'));

    let searchTimer = null;
    let table = null;
    let linkedSkuModal = null;
    let linkedSkuModalRow = null;
    let linkedSkuModalSelectedSkus = new Set();
    let linkedSkuSuggestionTimer = null;
    let linkedSkuSuggestionRequestId = 0;
    let lmpModal = null;
    let currentLmpModalRow = null;
    let bulkEditModal = null;
    let bulkEditTargetSkus = [];
    let bulkSelectionSkuCache = new Set();
    let allFilteredSelected = false;
    let filteredSkuTotal = 0;
    let productUniqueParents = [];
    let isProductNavigationActive = false;
    let currentProductParentIndex = -1;
    let isPlaybackUpdating = false;

    const playAutoBtn = document.getElementById('sku-link-lmp-amz-play-auto');
    const playPauseBtn = document.getElementById('sku-link-lmp-amz-play-pause');
    const playBackwardBtn = document.getElementById('sku-link-lmp-amz-play-backward');
    const playForwardBtn = document.getElementById('sku-link-lmp-amz-play-forward');
    const playStatusEl = document.getElementById('sku-link-lmp-amz-play-status');
    const parentSearchInput = document.getElementById('sku-link-lmp-amz-search-parent');
    const skuSearchInput = document.getElementById('sku-link-lmp-amz-search-sku');
    const historyAlertFilter = document.getElementById('sku-link-lmp-amz-history-alert-filter');
const modalEl = document.getElementById('skuLinkLmpAmzModal');
    if (modalEl) {
        linkedSkuModal = new bootstrap.Modal(modalEl);
    }

    const lmpModalEl = document.getElementById('skuLinkLmpAmzLmpModal');
    if (lmpModalEl) {
        lmpModal = new bootstrap.Modal(lmpModalEl);
        lmpModalEl.addEventListener('hidden.bs.modal', function () {
            lmpRemoveImagePreview();
        });
    }

    const LMP_THUMB_SIZE = 48;
    const LMP_PREVIEW_SIZE = LMP_THUMB_SIZE * 10;
    let lmpImagePreviewHideTimer = null;
    let lmpImagePreviewEl = null;

    function lmpRemoveImagePreview() {
        if (lmpImagePreviewHideTimer) {
            clearTimeout(lmpImagePreviewHideTimer);
            lmpImagePreviewHideTimer = null;
        }
        document.querySelectorAll('#sku-link-lmp-amz-image-hover-preview').forEach(function (el) {
            el.remove();
        });
        lmpImagePreviewEl = null;
    }

    function lmpCancelImagePreviewHide() {
        if (lmpImagePreviewHideTimer) {
            clearTimeout(lmpImagePreviewHideTimer);
            lmpImagePreviewHideTimer = null;
        }
    }

    function lmpScheduleImagePreviewHide() {
        lmpCancelImagePreviewHide();
        lmpImagePreviewHideTimer = setTimeout(lmpRemoveImagePreview, 200);
    }

    function lmpEnsureImagePreviewListeners(wrap) {
        if (wrap.dataset.lmpPreviewListeners === '1') {
            return;
        }
        wrap.dataset.lmpPreviewListeners = '1';
        wrap.addEventListener('mouseenter', lmpCancelImagePreviewHide);
        wrap.addEventListener('mouseleave', lmpScheduleImagePreviewHide);
    }

    function lmpClampPreviewPosition(wrap, clientX, clientY) {
        const pad = 12;
        let left = clientX + pad;
        let top = clientY + pad;
        wrap.style.left = left + 'px';
        wrap.style.top = top + 'px';
        const rect = wrap.getBoundingClientRect();
        const margin = 8;
        if (rect.right > window.innerWidth - margin) {
            left = Math.max(margin, window.innerWidth - rect.width - margin);
        }
        if (rect.bottom > window.innerHeight - margin) {
            top = Math.max(margin, window.innerHeight - rect.height - margin);
        }
        if (left < margin) {
            left = margin;
        }
        if (top < margin) {
            top = margin;
        }
        wrap.style.left = left + 'px';
        wrap.style.top = top + 'px';
    }

    function lmpShowImagePreview(clientX, clientY, fullUrl) {
        if (!fullUrl) {
            return;
        }
        lmpCancelImagePreviewHide();
        if (lmpImagePreviewEl && document.body.contains(lmpImagePreviewEl)) {
            const prevImg = lmpImagePreviewEl.querySelector('img');
            if (prevImg && prevImg.getAttribute('src') === fullUrl) {
                lmpClampPreviewPosition(lmpImagePreviewEl, clientX, clientY);
                return;
            }
        }
        lmpRemoveImagePreview();
        const wrap = document.createElement('div');
        wrap.id = 'sku-link-lmp-amz-image-hover-preview';
        const big = document.createElement('img');
        big.src = fullUrl;
        big.alt = '';
        big.width = LMP_PREVIEW_SIZE;
        big.height = LMP_PREVIEW_SIZE;
        wrap.appendChild(big);
        lmpEnsureImagePreviewListeners(wrap);
        document.body.appendChild(wrap);
        lmpImagePreviewEl = wrap;
        lmpClampPreviewPosition(wrap, clientX, clientY);
    }

    document.addEventListener('mouseover', function (e) {
        const thumb = e.target.closest('.sku-link-lmp-amz-competitor-thumb');
        if (!thumb) {
            return;
        }
        lmpShowImagePreview(e.clientX, e.clientY, thumb.getAttribute('src') || '');
    });

    document.addEventListener('mousemove', function (e) {
        if (!lmpImagePreviewEl || !e.target.closest('.sku-link-lmp-amz-competitor-thumb')) {
            return;
        }
        lmpClampPreviewPosition(lmpImagePreviewEl, e.clientX, e.clientY);
    });

    document.addEventListener('mouseout', function (e) {
        const thumb = e.target.closest('.sku-link-lmp-amz-competitor-thumb');
        if (!thumb) {
            return;
        }
        const related = e.relatedTarget;
        if (related && (
            (typeof related.closest === 'function' && related.closest('.sku-link-lmp-amz-competitor-thumb')) ||
            (typeof related.closest === 'function' && related.closest('#sku-link-lmp-amz-image-hover-preview'))
        )) {
            return;
        }
        lmpScheduleImagePreviewHide();
    });

    const bulkEditModalEl = document.getElementById('skuLinkLmpAmzBulkEditModal');
    if (bulkEditModalEl) {
        bulkEditModal = bootstrap.Modal.getOrCreateInstance(bulkEditModalEl);
    }

    const historyModalEl = document.getElementById('skuLinkLmpAmzHistoryModal');
    const historyModal = historyModalEl ? bootstrap.Modal.getOrCreateInstance(historyModalEl) : null;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
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

    function imageFormatter(cell) {
        const url = (cell.getValue() || '').trim();
        if (!url) {
            return '<span class="text-muted">No Image</span>';
        }

        return `<img src="${escapeHtmlAttr(url)}" alt="Product"
            style="height:40px;max-width:60px;border-radius:4px;border:1px solid #ccc;object-fit:contain;">`;
    }

    function resolveMValue(m) {
        const value = (m ?? '1').toString().trim().slice(0, 1);
        return value || '1';
    }

    function mFormatter(cell) {
        const value = resolveMValue(cell.getValue());
        return escapeHtml(value);
    }

    function computeAmzLmp(lmpPrice, m) {
        const lmp = parseFloat(lmpPrice);
        const multiplier = parseFloat(resolveMValue(m));
        if (!isFinite(lmp) || !isFinite(multiplier)) {
            return null;
        }
        return Math.round(lmp * multiplier * 100) / 100;
    }

    function amzLmpFormatter(cell) {
        const row = cell.getRow().getData();
        const value = cell.getValue() ?? computeAmzLmp(row.lmp_price, row.m);
        if (value == null || !isFinite(value)) {
            return '<span class="text-muted">—</span>';
        }
        return `<span style="font-weight: 600;">$${parseFloat(value).toFixed(2)}</span>`;
    }

    function amzLmpFormatter(cell) {
        const rowData = cell.getRow().getData();
        const val = parseFloat(cell.getValue()) || 0;
        const cvr60 = parseFloat(rowData.amz_lmp_60) || 0;
        const tol = 0.1;
        let arrowHtml = '';
        let arrowColor = '#6c757d';
        let arrowIcon = 'fa-minus';
        if (val > cvr60 + tol) {
            arrowColor = '#28a745';
            arrowIcon = 'fa-arrow-up';
        } else if (val < cvr60 - tol) {
            arrowColor = '#a00211';
            arrowIcon = 'fa-arrow-down';
        }
        arrowHtml = ` <span title="CVR 30 vs CVR 60: ${cvr60.toFixed(1)}%" style="vertical-align: middle;"><i class="fas ${arrowIcon}" style="color: ${arrowColor}; font-size: 12px;"></i></span>`;
        const color = val <= 4 ? '#a00211' : (val > 4 && val <= 7 ? '#ffc107' :
            (val > 7 && val <= 13 ? '#28a745' : '#e83e8c'));
        return `<span style="color: ${color}; font-weight: 600;">${val.toFixed(1)}%</span>${arrowHtml}`;
    }

    function saveMChar(cell) {
        const rowData = cell.getRow().getData();
        const sku = String(rowData.sku || '').trim();
        if (!sku) {
            return;
        }

        const nextValue = String(cell.getValue() || '').trim().slice(0, 1);
        const prevValue = resolveMValue(rowData.m);

        if (nextValue === prevValue) {
            cell.setValue(prevValue);
            return;
        }

        fetch(saveMUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: sku,
                m: nextValue,
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (!res.success) {
                throw new Error(res.message || 'Could not save M.');
            }
            const saved = (res.m || '').toString().trim().slice(0, 1);
            const displayM = saved || '1';
            const eLmp = computeAmzLmp(rowData.lmp_price, displayM);
            cell.getRow().update({
                m: displayM,
                amz_lmp: eLmp,
            });
        })
        .catch(function (err) {
            cell.setValue(prevValue);
            alert(err.message || 'Could not save M.');
        });
    }

    function lmpFormatter(cell) {
        const lmpPrice = cell.getValue();
        const rowData = cell.getRow().getData();
        const sku = rowData.sku || '';
        const totalCompetitors = rowData.lmp_entries_total || 0;
        const currentPrice = 0;
        const linkedSkus = Array.isArray(rowData.linked_lmp_skus) ? rowData.linked_lmp_skus : [];
        const linkedSkusAttr = escapeHtmlAttr(JSON.stringify(linkedSkus));

        if (!lmpPrice && totalCompetitors === 0) {
            return `<a href="#" class="sku-link-lmp-amz-view-competitors" data-sku="${escapeHtmlAttr(sku)}" data-linked-skus="${linkedSkusAttr}"
                style="color: #007bff; text-decoration: none; cursor: pointer; font-size: 12px;">
                <i class="fa fa-eye"></i> View
            </a>`;
        }

        let html = '<div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">';

        if (lmpPrice) {
            const priceFormatted = '$' + parseFloat(lmpPrice).toFixed(2);
            const priceColor = (lmpPrice < currentPrice) ? '#dc3545' : '#28a745';
            html += `<span style="color: ${priceColor}; font-weight: 600; font-size: 14px;">${priceFormatted}</span>`;
        }

        if (totalCompetitors > 0) {
            html += `<a href="#" class="sku-link-lmp-amz-view-competitors" data-sku="${escapeHtmlAttr(sku)}" data-linked-skus="${linkedSkusAttr}"
                style="color: #007bff; text-decoration: none; cursor: pointer; font-size: 11px;">
                <i class="fa fa-eye"></i> View ${totalCompetitors}
            </a>`;
        }

        html += '</div>';
        return html;
    }

    const LMP_CORE_WORD_STOP = new Set([
        'the', 'a', 'an', 'and', 'or', 'for', 'with', 'of', 'in', 'on', 'at', 'to', 'by',
        'from', 'is', 'are', 'was', 'were', 'be', 'new', 'used', 'pk', 'pc', 'pcs', 'pkgs',
        'ft', 'wo', 'wob', 'w', 'og', 'pkg', 'set', 'pair', 'each', 'per',
    ]);

    const LMP_CORE_WORD_ALIASES = {
        BLU: ['BLU', 'BLUE'],
        GR: ['GR', 'GRN', 'GREEN'],
        GRN: ['GRN', 'GREEN', 'GR'],
        YLW: ['YLW', 'YELLOW'],
        WH: ['WH', 'WHT', 'WHITE'],
        WHT: ['WHT', 'WHITE', 'WH'],
        RED: ['RED'],
        ORG: ['ORG', 'ORANGE'],
        SKY: ['SKY'],
        BLK: ['BLK', 'BLACK'],
        SLV: ['SLV', 'SILVER'],
        GLD: ['GLD', 'GOLD'],
        INCH: ['INCH', 'IN', '"'],
        IN: ['IN', 'INCH', '"'],
    };

    const LMP_CORE_WORD_MATCH_MIN = 5;

    function extractCoreWords(text) {
        return String(text || '')
            .toUpperCase()
            .replace(/[^A-Z0-9.\s]/g, ' ')
            .split(/\s+/)
            .map(function (word) { return word.trim(); })
            .filter(function (word) {
                if (!word || word.length < 2) {
                    return false;
                }
                if (/^\d+$/.test(word) && word.length < 2) {
                    return false;
                }
                return !LMP_CORE_WORD_STOP.has(word.toLowerCase());
            });
    }

    function buildCoreWordsFromRow(rowData) {
        const words = new Set();
        const parts = [
            rowData?.sku,
            rowData?.parent,
        ];

        (Array.isArray(rowData?.linked_lmp_skus) ? rowData.linked_lmp_skus : []).forEach(function (sku) {
            parts.push(sku);
        });

        parts.forEach(function (text) {
            extractCoreWords(text).forEach(function (word) {
                words.add(word);
            });
        });

        return Array.from(words);
    }

    function coreWordMatchesInTitle(word, titleUpper) {
        const aliases = LMP_CORE_WORD_ALIASES[word] || [word];
        if (aliases.some(function (alias) {
            return alias !== '' && titleUpper.includes(alias);
        })) {
            return true;
        }

        if (/^\d/.test(word)) {
            const baseNum = word.split('.')[0];
            if (baseNum.length >= 1 && titleUpper.includes(baseNum)) {
                return true;
            }
        }

        return false;
    }

    function countCoreWordMatches(title, coreWords) {
        const titleUpper = String(title || '').toUpperCase();
        let count = 0;

        coreWords.forEach(function (word) {
            if (coreWordMatchesInTitle(word, titleUpper)) {
                count++;
            }
        });

        return count;
    }

    /** Title contains 5 core / 5core / 5Core / 5 Core / 5CORE / 5 CORE (case & spacing insensitive). */
    function titleHasFiveCore(title) {
        const normalized = String(title || '')
            .replace(/[\u00a0\u2007\u202f]/g, ' ')
            .replace(/\s+/g, ' ');
        return /5\s*core/i.test(normalized);
    }

    function getSortedUniqueTotalPrices(competitors) {
        return competitors
            .map(function (item) { return parseFloat(item.total_price); })
            .filter(function (price) { return isFinite(price); })
            .sort(function (a, b) { return a - b; })
            .filter(function (price, index, list) {
                return index === 0 || price !== list[index - 1];
            });
    }

    function formatLowestVsSecondDiff(lowestPrice, secondLowestPrice) {
        const lowest = parseFloat(lowestPrice);
        const second = parseFloat(secondLowestPrice);
        if (!isFinite(lowest) || !isFinite(second) || second <= lowest) {
            return '';
        }

        const pct = ((second - lowest) / second) * 100;
        return `<span class="ms-1 text-success fw-semibold" title="vs 2nd lowest $${second.toFixed(2)}">
            <i class="fas fa-arrow-down" style="font-size: 11px;"></i> ${pct.toFixed(1)}%
        </span>`;
    }

    function renderEbayCompetitorsList(competitors, lowestPrice, rowData) {
        const wrap = document.getElementById('sku-link-lmp-amz-lmp-data-list');
        if (!wrap) {
            return;
        }

        if (!competitors || !competitors.length) {
            wrap.innerHTML = `
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> No competitors found for this SKU
                </div>
            `;
            return;
        }

        const coreWords = buildCoreWordsFromRow(rowData || currentLmpModalRow || {});
        const sortedUniquePrices = getSortedUniqueTotalPrices(competitors);
        const secondLowestPrice = sortedUniquePrices.length >= 2 ? sortedUniquePrices[1] : null;

        let html = '<div class="table-responsive"><table class="table table-hover">';
        html += `
            <thead class="table-dark">
                <tr>
                    <th>Image</th>
                    <th>Price</th>
                    <th>Shipping</th>
                    <th>Total</th>
                    <th>Title</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
        `;

        competitors.forEach(function (item) {
            const isLowest = item.total_price === lowestPrice;
            const hasFiveCore = titleHasFiveCore(item.title);
            const coreMatchCount = countCoreWordMatches(item.title, coreWords);
            const isCoreMatch = coreMatchCount >= LMP_CORE_WORD_MATCH_MIN;
            let rowClass = '';
            if (hasFiveCore) {
                rowClass = 'sku-link-lmp-amz-five-core-row';
            } else if (isCoreMatch) {
                rowClass = 'sku-link-lmp-amz-core-match-row';
            } else if (isLowest) {
                rowClass = 'sku-link-lmp-amz-lowest-row';
            }
            let badge = '';
            if (isLowest) {
                badge = '<span class="badge bg-primary ms-2">Lowest</span>';
                if (secondLowestPrice != null) {
                    badge += formatLowestVsSecondDiff(item.total_price, secondLowestPrice);
                }
            } else if (isCoreMatch) {
                badge = '<span class="badge bg-warning text-dark ms-2" title="' + coreMatchCount + ' core word matches">' + coreMatchCount + ' match</span>';
            }
            const productLink = item.link || `https://www.ebay.com/itm/${item.item_id}`;
            const imageCell = item.image
                ? `<img src="${escapeHtmlAttr(item.image)}" alt="" class="sku-link-lmp-amz-competitor-thumb" style="width:48px;height:48px;object-fit:contain;border-radius:4px;" loading="lazy">`
                : '<span class="text-muted">—</span>';

            html += `
                <tr class="${rowClass}">
                    <td>${imageCell}</td>
                    <td>$${parseFloat(item.price).toFixed(2)}</td>
                    <td>${parseFloat(item.shipping_cost) === 0 ? '<span class="badge bg-info">FREE</span>' : '$' + parseFloat(item.shipping_cost).toFixed(2)}</td>
                    <td><strong>$${parseFloat(item.total_price).toFixed(2)}</strong> ${badge}</td>
                    <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        ${escapeHtml(item.title || 'N/A')}
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="${escapeHtmlAttr(productLink)}" target="_blank" class="btn btn-sm btn-info" title="View Product on eBay">
                                <i class="fa fa-external-link"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-danger sku-link-lmp-amz-delete-competitor"
                                data-id="${escapeHtmlAttr(item.id)}"
                                title="Delete this competitor">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        wrap.innerHTML = html;
    }

    function loadEbayCompetitorsModal(rowData) {
        if (!lmpModal || !rowData?.sku) {
            return;
        }

        currentLmpModalRow = rowData;
        const sku = rowData.sku;
        const linkedSkus = Array.isArray(rowData.linked_lmp_skus) ? rowData.linked_lmp_skus : [];

        document.getElementById('sku-link-lmp-amz-lmp-sku').textContent = sku;
        document.getElementById('sku-link-lmp-amz-add-comp-sku').value = sku;
        document.getElementById('sku-link-lmp-amz-add-comp-asin').value = '';
        document.getElementById('sku-link-lmp-amz-add-comp-price').value = '';
        document.getElementById('sku-link-lmp-amz-add-comp-shipping').value = '';
        document.getElementById('sku-link-lmp-amz-add-comp-link').value = '';
        document.getElementById('sku-link-lmp-amz-add-comp-title').value = '';

        document.getElementById('sku-link-lmp-amz-lmp-data-list').innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading competitors...</p>
            </div>
        `;

        lmpModal.show();

        const query = new URLSearchParams({ sku: sku });
        linkedSkus.forEach(function (linkedSku) {
            query.append('linked_lmp_skus[]', linkedSku);
        });

        fetch(`${lmpDataUrl}?${query.toString()}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (res) { return res.json(); })
        .then(function (response) {
            if (!response.success) {
                throw new Error(response.message || 'Failed to load LMP data.');
            }
            if (response.competitors && response.competitors.length > 0) {
                renderEbayCompetitorsList(response.competitors, response.lowest_price, rowData);
            } else {
                document.getElementById('sku-link-lmp-amz-lmp-data-list').innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fa fa-info-circle"></i> No competitors found yet. Add your first competitor above!
                    </div>
                `;
            }
        })
        .catch(function (err) {
            document.getElementById('sku-link-lmp-amz-lmp-data-list').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-triangle"></i>
                    ${escapeHtml(err.message || 'Could not load competitors. Please try again.')}
                </div>
            `;
        });
    }

    function linkedLmpSkuFormatter(cell) {
        const row = cell.getRow().getData();
        const rowSku = String(row.sku || '').trim();
        let skus = row.linked_lmp_skus || [];
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

        const seenSkuNorms = new Set();
        skus = skus.filter(function (sku) {
            const norm = String(sku || '').trim().toUpperCase();
            if (!norm || seenSkuNorms.has(norm)) {
                return false;
            }
            seenSkuNorms.add(norm);
            return true;
        });

        const badges = skus.length
            ? skus.map(function (sku) {
                const skuText = String(sku || '').trim();
                const isSelf = skuText.toUpperCase() === rowSku.toUpperCase();
                const removeBtn = isSelf
                    ? ''
                    : `<button type="button" class="btn-close sku-link-lmp-amz-remove"
                        data-linked-sku="${escapeHtmlAttr(skuText)}" aria-label="Remove link to ${escapeHtmlAttr(skuText)}"></button>`;
                return `<span class="linked-sku-badge-wrap badge bg-info-subtle text-dark border me-1 mb-1">
                    <span class="linked-sku-badge">${escapeHtml(skuText)}</span>${removeBtn}
                </span>`;
            }).join('')
            : '<span class="text-muted fst-italic">No SKUs</span>';

        return `<div class="d-flex flex-wrap align-items-start py-1" style="line-height:1.6;">${badges}</div>`;
    }

    function linkedLmpSkuAddFormatter(cell) {
        const rowSku = String(cell.getRow().getData().sku || '').trim();
        if (!rowSku) {
            return '';
        }

        return `<div class="d-flex align-items-center justify-content-center py-1">
            <button type="button" class="btn btn-sm btn-outline-primary sku-link-lmp-amz-add-btn"
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
                bySku[item.sku] = item.linked_lmp_skus || [];
            }
        });

        table.getRows().forEach(function (row) {
            const data = row.getData();
            if (!Object.prototype.hasOwnProperty.call(bySku, data.sku)) {
                return;
            }
            row.update({ linked_lmp_skus: bySku[data.sku] });
        });
    }

    function getSelectedRows() {
        if (!table) {
            return [];
        }
        return table.getSelectedRows();
    }

    function getActiveSelectedRows() {
        if (!table) {
            return [];
        }
        const activeSet = new Set(table.getRows('active'));
        return getSelectedRows().filter(function (row) {
            return activeSet.has(row);
        });
    }

    function getCurrentFilterParams() {
        const query = new URLSearchParams();
        const skuTerm = (skuSearchInput?.value || '').trim();
        const parentTerm = (parentSearchInput?.value || '').trim();
        const historyAlert = (historyAlertFilter?.value || '').trim();
        if (skuTerm) {
            query.set('sku', skuTerm);
        }
        if (isProductNavigationActive && parentTerm) {
            query.set('parent_exact', parentTerm);
        } else if (parentTerm) {
            query.set('parent', parentTerm);
        }
        if (historyAlert) {
            query.set('history_alert', historyAlert);
        }
return query;
    }

    function getBulkTargetSkus(primarySku) {
        if (bulkSelectionSkuCache.size > 0) {
            return Array.from(bulkSelectionSkuCache);
        }
        const sku = String(primarySku || '').trim();
        return sku ? [sku] : [];
    }

    function clearBulkSelection() {
        bulkSelectionSkuCache = new Set();
        allFilteredSelected = false;
        filteredSkuTotal = 0;
        if (table) {
            table.deselectRow();
        }
        updateSelectAllFilteredCheckbox(false);
        updateBulkEditBadge();
    }

    function updateBulkEditBadge() {
        const badge = document.getElementById('sku-link-lmp-amz-bulk-edit-badge');
        const countEl = document.getElementById('sku-link-lmp-amz-bulk-edit-count');
        if (!badge || !countEl) {
            return;
        }

        const modalOpen = bulkEditModalEl && bulkEditModalEl.classList.contains('show');
        let count = modalOpen && bulkEditTargetSkus.length
            ? bulkEditTargetSkus.length
            : bulkSelectionSkuCache.size;

        if (count > 0) {
            badge.classList.remove('d-none');
            badge.classList.add('d-flex');
            countEl.textContent = count + ' selected';
        } else {
            badge.classList.add('d-none');
            badge.classList.remove('d-flex');
            countEl.textContent = '0 selected';
        }
    }

    function updateSelectAllFilteredCheckbox(checked) {
        const checkbox = document.getElementById('sku-link-lmp-amz-select-all-filtered');
        if (checkbox) {
            checkbox.checked = !!checked;
        }
    }

    function restoreVisibleRowSelectionFromCache() {
        if (!table || bulkSelectionSkuCache.size === 0) {
            return;
        }
        table.getRows('active').forEach(function (row) {
            const sku = String(row.getData().sku || '').trim();
            if (sku && bulkSelectionSkuCache.has(sku)) {
                row.select();
            }
        });
    }

    function fetchFilteredSkus() {
        const query = getCurrentFilterParams();
        return fetch(`${filteredSkusUrl}?${query.toString()}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (res) { return res.json(); })
        .then(function (response) {
            if (!response.success) {
                throw new Error(response.message || 'Could not load filtered SKUs.');
            }
            filteredSkuTotal = parseInt(response.total, 10) || 0;
            return Array.isArray(response.skus) ? response.skus : [];
        });
    }

    function selectAllFilteredRows() {
        return fetchFilteredSkus()
            .then(function (skus) {
                bulkSelectionSkuCache = new Set(skus.filter(Boolean));
                allFilteredSelected = bulkSelectionSkuCache.size > 0;
                restoreVisibleRowSelectionFromCache();
                updateSelectAllFilteredCheckbox(allFilteredSelected);
                updateBulkEditBadge();
                return bulkSelectionSkuCache.size;
            });
    }

    function deselectAllFilteredRows() {
        clearBulkSelection();
    }

    function handleSelectAllFilteredChange(isChecked) {
        if (isChecked) {
            selectAllFilteredRows().catch(function (err) {
                updateSelectAllFilteredCheckbox(false);
                alert(err.message || 'Could not select filtered rows.');
            });
            return;
        }
        deselectAllFilteredRows();
    }

    function resetBulkEditModalFields() {
        const mInput = document.getElementById('sku-link-lmp-amz-bulk-m');
        const linkInput = document.getElementById('sku-link-lmp-amz-bulk-link-sku');
        if (mInput) mInput.value = '';
        if (linkInput) linkInput.value = '';
    }

    function openBulkEditModal(targetSkus) {
        if (!bulkEditModal) {
            return;
        }

        bulkEditTargetSkus = Array.isArray(targetSkus)
            ? targetSkus.map(function (sku) { return String(sku || '').trim(); }).filter(Boolean)
            : [];

        if (!bulkEditTargetSkus.length) {
            alert('Select one or more rows using the checkboxes first.');
            return;
        }

        bulkSelectionSkuCache = new Set(bulkEditTargetSkus);
        restoreVisibleRowSelectionFromCache();
        updateBulkEditBadge();

        const countEl = document.getElementById('sku-link-lmp-amz-bulk-target-count');
        const skusEl = document.getElementById('sku-link-lmp-amz-bulk-target-skus');
        const togetherWrap = document.getElementById('sku-link-lmp-amz-bulk-link-together-wrap');
        const titleEl = document.getElementById('skuLinkLmpAmzBulkEditModalLabel');

        if (countEl) {
            countEl.textContent = String(bulkEditTargetSkus.length);
        }
        if (skusEl) {
            const preview = bulkEditTargetSkus.slice(0, 5).join(', ');
            skusEl.textContent = bulkEditTargetSkus.length > 5
                ? `${preview}…`
                : preview;
        }
        if (togetherWrap) {
            togetherWrap.style.display = bulkEditTargetSkus.length >= 2 ? '' : 'none';
        }
        if (titleEl) {
            titleEl.innerHTML = bulkEditTargetSkus.length === 1
                ? '<i class="mdi mdi-pencil-box-multiple-outline me-2"></i> Bulk Edit'
                : `<i class="mdi mdi-pencil-box-multiple-outline me-2"></i> Bulk Edit (${bulkEditTargetSkus.length} selected SKUs)`;
        }

        resetBulkEditModalFields();
        bulkEditModal.show();
    }

    function openBulkEditModalForRow(rowData, tabulatorRow) {
        const selectedSkus = getBulkTargetSkus(rowData?.sku);
        if (selectedSkus.length >= 1 && tabulatorRow?.isSelected?.()) {
            openBulkEditModal(selectedSkus);
            return;
        }
        if (rowData?.sku) {
            openBulkEditModal([rowData.sku]);
        }
    }

    function bulkEditFormatter() {
        const title = 'Bulk edit selected rows';
        return `<button type="button" class="btn btn-sm btn-outline-primary sku-link-lmp-amz-bulk-edit-row-btn py-0 px-2"
            title="${escapeHtmlAttr(title)}" aria-label="${escapeHtmlAttr(title)}">
            <i class="mdi mdi-pencil"></i>
        </button>`;
    }

    function historyFormatter(cell) {
        const row = cell.getRow().getData();
        const count = parseInt(row.history_count, 10) || 0;

        if (count === 0) {
            return '<span class="text-muted">—</span>';
        }

        const user = escapeHtml(row.latest_history_by || 'N/A');
        const date = escapeHtml(row.latest_history_at || '—');
        const time = escapeHtml(row.latest_history_time || '');
        const change = escapeHtml(row.latest_change || 'View history');
        const stale = !!row.history_stale;
        const alertIcon = stale
            ? '<i class="fas fa-exclamation-triangle text-danger" title="No competitor ID update in 15+ days"></i>'
            : '';

        return `<div class="sku-link-lmp-amz-history-cell text-center">
            <div class="d-flex align-items-center justify-content-center gap-1 mb-1">${alertIcon}</div>
            <div class="small fw-semibold">${user}</div>
            <div class="small text-muted">${date}</div>
            <div class="small text-muted">${time}</div>
            <button type="button" class="btn btn-sm btn-link p-0 sku-link-lmp-amz-history-dot"
                title="${escapeHtmlAttr(change)}" aria-label="View history">
                <span class="sku-link-lmp-amz-history-dot-icon">●</span>
            </button>
        </div>`;
    }

    function openHistoryModal(row) {
        if (!historyModal || !row?.sku) {
            return;
        }

        const sku = row.sku || '';
        const parent = row.parent || '';
        document.getElementById('sku-link-lmp-amz-history-modal-sku').textContent = sku;

        const loadingEl = document.getElementById('sku-link-lmp-amz-history-loading');
        const emptyEl = document.getElementById('sku-link-lmp-amz-history-empty');
        const errorEl = document.getElementById('sku-link-lmp-amz-history-error');
        const tableWrap = document.getElementById('sku-link-lmp-amz-history-table-wrap');
        const tbody = document.getElementById('sku-link-lmp-amz-history-tbody');

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
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            loadingEl.classList.add('d-none');

            if (!data.success) {
                errorEl.textContent = data.message || 'Failed to load history.';
                errorEl.classList.remove('d-none');
                return;
            }

            const rows = data.history || [];
            if (!rows.length) {
                emptyEl.classList.remove('d-none');
                return;
            }

            tbody.innerHTML = rows.map(function (item) {
                const parts = String(item.updated_at || '').split(' ');
                const datePart = escapeHtml(parts[0] || '—');
                const timePart = escapeHtml(parts[1] || '—');
                const actionClass = String(item.action || '').toLowerCase() === 'deleted' ? 'text-danger' : 'text-success';

                return `<tr>
                    <td>${datePart}</td>
                    <td>${timePart}</td>
                    <td>${escapeHtml(item.updated_by || 'N/A')}</td>
                    <td class="fw-semibold ${actionClass}">${escapeHtml(item.action || '—')}</td>
                    <td>${escapeHtml(item.item_id || '—')}</td>
                    <td>${escapeHtml(item.changes || '—')}</td>
                </tr>`;
            }).join('');

            tableWrap.classList.remove('d-none');
        })
        .catch(function () {
            loadingEl.classList.add('d-none');
            errorEl.textContent = 'Could not load competitor ID history.';
            errorEl.classList.remove('d-none');
        });
    }

    async function applyBulkEditModal() {
        const mVal = (document.getElementById('sku-link-lmp-amz-bulk-m')?.value || '').trim().slice(0, 1);
        const linkSkuVal = (document.getElementById('sku-link-lmp-amz-bulk-link-sku')?.value || '').trim();
        const targets = bulkEditTargetSkus.slice();
        const applyBtn = document.getElementById('sku-link-lmp-amz-bulk-edit-apply-btn');

        if (!targets.length) {
            alert('No rows selected.');
            return;
        }
        if (!mVal && !linkSkuVal) {
            alert('Fill in at least one field to apply.');
            return;
        }

        const original = applyBtn?.innerHTML || '';
        if (applyBtn) {
            applyBtn.disabled = true;
            applyBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Applying...';
        }

        const summary = [];

        try {
            if (mVal) {
                const res = await fetch(bulkSaveMUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        skus: targets,
                        m: mVal,
                    }),
                }).then(function (response) { return response.json(); });

                if (!res.success) {
                    throw new Error(res.message || 'Could not update M.');
                }
                summary.push(`M → ${mVal}: ${targets.length} row(s)`);
            }

            if (linkSkuVal) {
                let ok = 0;
                const failed = [];
                for (let i = 0; i < targets.length; i++) {
                    const sku = targets[i];
                    if (String(sku).toUpperCase() === String(linkSkuVal).toUpperCase()) {
                        continue;
                    }
                    try {
                        const res = await fetch(linkedSkuAddUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                sku: sku,
                                linked_sku: linkSkuVal,
                            }),
                        }).then(function (response) { return response.json(); });
                        if (!res.success) {
                            failed.push(sku);
                        } else {
                            ok++;
                        }
                    } catch (err) {
                        failed.push(sku);
                    }
                }
                let line = `Link → ${linkSkuVal}: ${ok} row(s)`;
                if (failed.length) {
                    line += ` • errors: ${failed.join(', ')}`;
                }
                summary.push(line);
            }

            bulkEditModal?.hide();
            clearBulkSelection();
            reloadTable();
            if (summary.length) {
                alert(summary.join('\n'));
            }
        } catch (err) {
            alert(err.message || 'Bulk edit failed.');
        } finally {
            if (applyBtn) {
                applyBtn.disabled = false;
                applyBtn.innerHTML = original;
            }
        }
    }

    async function bulkLinkTogetherFromModal() {
        if (bulkEditTargetSkus.length < 2) {
            alert('Select at least two SKUs to link together.');
            return;
        }

        const btn = document.getElementById('sku-link-lmp-amz-bulk-link-together-btn');
        const original = btn?.innerHTML || '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Linking...';
        }

        try {
            const res = await fetch(linkedSkuBulkLinkUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ skus: bulkEditTargetSkus }),
            }).then(function (response) { return response.json(); });

            if (!res.success) {
                throw new Error(res.message || 'Could not link selected SKUs.');
            }
            applyAffectedLinkedSkuRows(res.affected);
            alert(`${bulkEditTargetSkus.length} selected SKUs linked as related.`);
            bulkEditModal?.hide();
            clearBulkSelection();
            reloadTable();
        } catch (err) {
            alert(err.message || 'Could not link selected SKUs.');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        }
    }

    function openLinkedSkuModal(rowData) {
        if (!linkedSkuModal || !rowData?.sku) {
            return;
        }

        linkedSkuModalRow = rowData;
        linkedSkuModalSelectedSkus = new Set();
        document.getElementById('sku-link-lmp-amz-source').textContent = rowData.sku;
        const input = document.getElementById('sku-link-lmp-amz-input');
        input.value = '';
        renderLinkedSkuSuggestions('');
        updateLinkedSkuSelectedSummary();
        linkedSkuModal.show();
        setTimeout(function () { input?.focus(); }, 200);
    }

    function updateLinkedSkuSelectedSummary() {
        const wrap = document.getElementById('sku-link-lmp-amz-selected-wrap');
        const listEl = document.getElementById('sku-link-lmp-amz-selected-skus');
        const countEl = document.getElementById('sku-link-lmp-amz-selected-count');
        const saveLabel = document.getElementById('sku-link-lmp-amz-save-btn-label');
        const selected = Array.from(linkedSkuModalSelectedSkus);

        if (countEl) {
            countEl.textContent = String(selected.length);
        }
        if (saveLabel) {
            saveLabel.textContent = selected.length > 1
                ? 'Link ' + selected.length + ' SKUs'
                : 'Link SKU(s)';
        }
        if (!wrap || !listEl) {
            return;
        }

        if (!selected.length) {
            wrap.classList.add('d-none');
            listEl.innerHTML = '';
            return;
        }

        wrap.classList.remove('d-none');
        listEl.innerHTML = selected.map(function (sku) {
            return `<span class="sku-link-lmp-amz-selected-chip">
                ${escapeHtml(sku)}
                <button type="button" class="sku-link-lmp-amz-selected-remove" data-sku="${escapeHtmlAttr(sku)}" title="Remove">&times;</button>
            </span>`;
        }).join('');
    }

    function toggleLinkedSkuSuggestionSelection(sku, checked) {
        const norm = String(sku || '').trim();
        if (!norm) {
            return;
        }
        if (checked) {
            linkedSkuModalSelectedSkus.add(norm);
        } else {
            linkedSkuModalSelectedSkus.delete(norm);
        }
        updateLinkedSkuSelectedSummary();
    }

    function renderLinkedSkuSuggestions(term) {
        const wrap = document.getElementById('sku-link-lmp-amz-suggestions');
        if (!wrap) {
            return;
        }

        const query = String(term || '').trim();
        if (!query) {
            wrap.classList.add('d-none');
            wrap.innerHTML = '';
            return;
        }

        clearTimeout(linkedSkuSuggestionTimer);
        linkedSkuSuggestionTimer = setTimeout(function () {
            const requestId = ++linkedSkuSuggestionRequestId;
            const params = new URLSearchParams({ sku: query });

            fetch(`${filteredSkusUrl}?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then(function (res) { return res.json(); })
            .then(function (response) {
                if (requestId !== linkedSkuSuggestionRequestId) {
                    return;
                }
                if (!response.success) {
                    throw new Error(response.message || 'Could not search SKUs.');
                }

                const currentSku = String(linkedSkuModalRow?.sku || '').trim().toUpperCase();
                const existing = new Set(
                    (Array.isArray(linkedSkuModalRow?.linked_lmp_skus) ? linkedSkuModalRow.linked_lmp_skus : [])
                        .map(function (sku) { return String(sku || '').trim().toUpperCase(); })
                );

                const matches = (Array.isArray(response.skus) ? response.skus : [])
                    .map(function (sku) { return String(sku || '').trim(); })
                    .filter(function (sku) {
                        const norm = sku.toUpperCase();
                        return sku && norm !== currentSku && !existing.has(norm);
                    })
                    .slice(0, 12);

                if (!matches.length) {
                    wrap.classList.add('d-none');
                    wrap.innerHTML = '';
                    return;
                }

                wrap.classList.remove('d-none');
                wrap.innerHTML = matches.map(function (sku) {
                    const checked = linkedSkuModalSelectedSkus.has(sku);
                    return `<label class="list-group-item list-group-item-action py-2 sku-link-lmp-amz-suggestion-item d-flex align-items-center gap-2 mb-0">
                        <input type="checkbox" class="form-check-input sku-link-lmp-amz-suggestion-cb"
                            value="${escapeHtmlAttr(sku)}" ${checked ? 'checked' : ''}>
                        <span class="flex-grow-1">${escapeHtml(sku)}</span>
                    </label>`;
                }).join('');
            })
            .catch(function () {
                if (requestId !== linkedSkuSuggestionRequestId) {
                    return;
                }
                wrap.classList.add('d-none');
                wrap.innerHTML = '';
            });
        }, 200);
    }

    function getLinkedSkuModalSelections() {
        const selected = Array.from(linkedSkuModalSelectedSkus);
        const inputVal = String(document.getElementById('sku-link-lmp-amz-input')?.value || '').trim();
        const sourceNorm = String(linkedSkuModalRow?.sku || '').trim().toUpperCase();

        if (inputVal && inputVal.toUpperCase() !== sourceNorm) {
            const alreadySelected = selected.some(function (sku) {
                return sku.toUpperCase() === inputVal.toUpperCase();
            });
            if (!alreadySelected) {
                selected.push(inputVal);
            }
        }

        return selected;
    }

    function saveLinkedSkuFromModal() {
        if (!linkedSkuModalRow?.sku) {
            return;
        }

        const sourceSku = String(linkedSkuModalRow.sku || '').trim();
        const toLink = getLinkedSkuModalSelections();
        if (!toLink.length) {
            alert('Select one or more SKUs from the list, or enter a SKU to link.');
            return;
        }

        const allSkus = [sourceSku].concat(toLink);
        const uniqueSkus = [];
        const seen = new Set();
        allSkus.forEach(function (sku) {
            const norm = String(sku || '').trim().toUpperCase();
            if (!norm || seen.has(norm)) {
                return;
            }
            seen.add(norm);
            uniqueSkus.push(String(sku).trim());
        });

        if (uniqueSkus.length < 2) {
            alert('Select at least one SKU to link.');
            return;
        }

        const btn = document.getElementById('sku-link-lmp-amz-save-btn');
        const original = btn?.innerHTML || '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Linking...';
        }

        const isBulk = uniqueSkus.length > 2 || toLink.length > 1;
        const fetchPromise = isBulk
            ? fetch(linkedSkuBulkLinkUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ skus: uniqueSkus }),
            })
            : fetch(linkedSkuAddUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    sku: sourceSku,
                    linked_sku: toLink[0],
                }),
            });

        fetchPromise
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (!res.success) {
                throw new Error(res.message || res.error || 'Could not link SKU(s).');
            }
            applyAffectedLinkedSkuRows(res.affected);
            linkedSkuModalSelectedSkus = new Set();
            linkedSkuModal?.hide();
            reloadTable();
        })
        .catch(function (err) {
            alert(err.message || 'Could not link SKU(s).');
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

        if (!confirm(`Remove LMP link between "${rowData.sku}" and "${linkedSku}"?`)) {
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
            reloadTable();
        })
        .catch(function (err) {
            alert(err.message || 'Could not remove linked SKU.');
        });
    }

    function bulkLinkSelectedSkus(rowData, addBtn) {
        const selectedSkus = getBulkTargetSkus(rowData?.sku);

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
            clearBulkSelection();
            reloadTable();
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

    function reloadTable(clearSelection) {
        if (clearSelection !== false) {
            clearBulkSelection();
        }
        if (table) {
            table.setPage(1);
            table.replaceData();
        }
    }

    function updateProductPlayStatus() {
        if (!playStatusEl) {
            return;
        }
        if (!isProductNavigationActive || currentProductParentIndex < 0 || !productUniqueParents.length) {
            playStatusEl.textContent = '';
            return;
        }
        playStatusEl.textContent = `Parent ${currentProductParentIndex + 1} of ${productUniqueParents.length}: ${productUniqueParents[currentProductParentIndex]}`;
    }

    function updateProductPlayButtonStates() {
        if (playBackwardBtn) {
            playBackwardBtn.disabled = !isProductNavigationActive || currentProductParentIndex <= 0;
            playBackwardBtn.classList.toggle('is-active-nav', isProductNavigationActive);
        }
        if (playForwardBtn) {
            playForwardBtn.disabled = !isProductNavigationActive || currentProductParentIndex >= productUniqueParents.length - 1;
            playForwardBtn.classList.toggle('is-active-nav', isProductNavigationActive);
        }
        if (playAutoBtn) {
            playAutoBtn.style.display = isProductNavigationActive ? 'none' : '';
        }
        if (playPauseBtn) {
            playPauseBtn.style.display = isProductNavigationActive ? '' : 'none';
        }
        updateProductPlayStatus();
    }

    function loadProductUniqueParents() {
        return fetch(parentsUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (res) { return res.json(); })
        .then(function (response) {
            if (!response.success) {
                throw new Error(response.message || 'Failed to load parents.');
            }
            productUniqueParents = Array.isArray(response.data) ? response.data : [];
            return productUniqueParents;
        });
    }

    function applyProductNavigationFilter() {
        if (!isProductNavigationActive || currentProductParentIndex < 0 || !productUniqueParents.length) {
            return;
        }

        const currentParent = productUniqueParents[currentProductParentIndex] || '';
        isPlaybackUpdating = true;
        if (parentSearchInput) {
            parentSearchInput.value = currentParent;
        }
        isPlaybackUpdating = false;
        reloadTable();
        updateProductPlayButtonStates();
    }

    function stopProductNavigation() {
        isProductNavigationActive = false;
        currentProductParentIndex = -1;
        isPlaybackUpdating = true;
        if (parentSearchInput) {
            parentSearchInput.value = '';
        }
        isPlaybackUpdating = false;
        updateProductPlayButtonStates();
        reloadTable();
    }

    function startProductNavigation() {
        loadProductUniqueParents()
            .then(function (parents) {
                if (!parents.length) {
                    alert('No parent groups in data.');
                    return;
                }
                isProductNavigationActive = true;
                currentProductParentIndex = 0;
                applyProductNavigationFilter();
            })
            .catch(function (err) {
                alert(err.message || 'Could not load parents.');
            });
    }

    function nextProductParent() {
        if (!isProductNavigationActive || currentProductParentIndex >= productUniqueParents.length - 1) {
            return;
        }
        currentProductParentIndex++;
        applyProductNavigationFilter();
    }

    function previousProductParent() {
        if (!isProductNavigationActive || currentProductParentIndex <= 0) {
            return;
        }
        currentProductParentIndex--;
        applyProductNavigationFilter();
    }

    playAutoBtn?.addEventListener('click', function (e) {
        e.preventDefault();
        startProductNavigation();
    });
    playPauseBtn?.addEventListener('click', function (e) {
        e.preventDefault();
        stopProductNavigation();
    });
    playForwardBtn?.addEventListener('click', function (e) {
        e.preventDefault();
        nextProductParent();
    });
    playBackwardBtn?.addEventListener('click', function (e) {
        e.preventDefault();
        previousProductParent();
    });
    updateProductPlayButtonStates();

    document.getElementById('sku-link-lmp-amz-search-parent')?.addEventListener('input', function () {
        if (!isPlaybackUpdating && isProductNavigationActive) {
            stopProductNavigation();
        }
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { reloadTable(true); }, 300);
    });

    document.getElementById('sku-link-lmp-amz-search-sku')?.addEventListener('input', function () {
        if (isProductNavigationActive) {
            stopProductNavigation();
        }
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { reloadTable(true); }, 300);
    });

    historyAlertFilter?.addEventListener('change', function () {
        reloadTable(true);
    });
document.getElementById('sku-link-lmp-amz-bulk-edit-btn')?.addEventListener('click', function () {
        openBulkEditModal(getBulkTargetSkus());
    });

    document.getElementById('sku-link-lmp-amz-bulk-edit-apply-btn')?.addEventListener('click', function () {
        applyBulkEditModal();
    });

    document.getElementById('sku-link-lmp-amz-bulk-link-together-btn')?.addEventListener('click', function () {
        bulkLinkTogetherFromModal();
    });

    bulkEditModalEl?.addEventListener('hidden.bs.modal', function () {
        bulkEditTargetSkus = [];
        resetBulkEditModalFields();
    });

    document.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'sku-link-lmp-amz-select-all-filtered') {
            handleSelectAllFilteredChange(!!e.target.checked);
        }
    });

    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'sku-link-lmp-amz-select-all-filtered') {
            e.stopPropagation();
        }
    }, true);

    document.getElementById('sku-link-lmp-amz-save-btn')?.addEventListener('click', saveLinkedSkuFromModal);
    document.getElementById('sku-link-lmp-amz-input')?.addEventListener('input', function () {
        renderLinkedSkuSuggestions(this.value);
    });
    document.getElementById('sku-link-lmp-amz-input')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveLinkedSkuFromModal();
        }
    });
    document.getElementById('sku-link-lmp-amz-suggestions')?.addEventListener('change', function (e) {
        if (!e.target.classList.contains('sku-link-lmp-amz-suggestion-cb')) {
            return;
        }
        toggleLinkedSkuSuggestionSelection(e.target.value, e.target.checked);
    });
    document.getElementById('sku-link-lmp-amz-suggestions')?.addEventListener('click', function (e) {
        const item = e.target.closest('.sku-link-lmp-amz-suggestion-item');
        if (!item || e.target.classList.contains('sku-link-lmp-amz-suggestion-cb')) {
            return;
        }
        const cb = item.querySelector('.sku-link-lmp-amz-suggestion-cb');
        if (!cb) {
            return;
        }
        cb.checked = !cb.checked;
        toggleLinkedSkuSuggestionSelection(cb.value, cb.checked);
    });
    document.getElementById('sku-link-lmp-amz-selected-skus')?.addEventListener('click', function (e) {
        const btn = e.target.closest('.sku-link-lmp-amz-selected-remove');
        if (!btn) {
            return;
        }
        const sku = btn.dataset.sku || '';
        linkedSkuModalSelectedSkus.delete(sku);
        document.querySelectorAll('.sku-link-lmp-amz-suggestion-cb').forEach(function (cb) {
            if (cb.value === sku) {
                cb.checked = false;
            }
        });
        updateLinkedSkuSelectedSummary();
    });

    document.getElementById('sku-link-lmp-amz-add-competitor-form')?.addEventListener('submit', function (e) {
        e.preventDefault();

        const submitBtn = this.querySelector('button[type="submit"]');
        const original = submitBtn?.innerHTML || '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
        }

        const formData = new FormData(this);
        const payload = Object.fromEntries(formData.entries());
        payload.shipping_cost = payload.shipping_cost || 0;

        fetch(amazonLmpAddUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })
        .then(function (res) { return res.json(); })
        .then(function (response) {
            if (response.error) {
                throw new Error(response.error);
            }
            if (currentLmpModalRow) {
                loadEbayCompetitorsModal(currentLmpModalRow);
            }
            reloadTable();
        })
        .catch(function (err) {
            alert(err.message || 'Could not add competitor.');
        })
        .finally(function () {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = original;
            }
        });
    });

    document.addEventListener('click', function (e) {
        const viewLink = e.target.closest('.sku-link-lmp-amz-view-competitors');
        if (viewLink) {
            e.preventDefault();
            const sku = viewLink.dataset.sku || '';
            let linkedSkus = [];
            try {
                linkedSkus = JSON.parse(viewLink.dataset.linkedSkus || '[]');
            } catch (err) {
                linkedSkus = [];
            }
            loadEbayCompetitorsModal({ sku: sku, linked_lmp_skus: linkedSkus });
            return;
        }

        const deleteBtn = e.target.closest('.sku-link-lmp-amz-delete-competitor');
        if (deleteBtn) {
            e.preventDefault();
            if (!confirm('Delete this competitor?')) {
                return;
            }

            fetch(amazonLmpDeleteUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ id: deleteBtn.dataset.id }),
            })
            .then(function (res) { return res.json(); })
            .then(function (response) {
                if (response.error) {
                    throw new Error(response.error);
                }
                if (currentLmpModalRow) {
                    loadEbayCompetitorsModal(currentLmpModalRow);
                }
                reloadTable();
            })
            .catch(function (err) {
                alert(err.message || 'Could not delete competitor.');
            });
        }
    });

    table = new Tabulator('#sku-link-lmp-amz-table', {
        ajaxURL: dataUrl,
        ajaxConfig: 'GET',
        ajaxURLGenerator: function (url, config, params) {
            const query = new URLSearchParams({
                page: String(params.page || 1),
                size: String(params.size || 50),
            });
            const skuTerm = (skuSearchInput?.value || '').trim();
            const parentTerm = (parentSearchInput?.value || '').trim();
            if (skuTerm) {
                query.set('sku', skuTerm);
            }
            if (isProductNavigationActive && parentTerm) {
                query.set('parent_exact', parentTerm);
            } else if (parentTerm) {
                query.set('parent', parentTerm);
            }
            const historyAlert = (historyAlertFilter?.value || '').trim();
            if (historyAlert) {
                query.set('history_alert', historyAlert);
            }
return `${url}?${query.toString()}`;
        },
        ajaxResponse: function (url, params, response) {
            if (!response.success) {
                throw new Error(response.message || 'Failed to load SKU Link LMP AMZ data.');
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
        placeholder: 'No SKU Link LMP AMZ data found',
        selectableRows: true,
        selectableRowsPersistence: true,
        columns: [
            {
                formatter: 'rowSelection',
                titleFormatter: function () {
                    return '<input type="checkbox" id="sku-link-lmp-amz-select-all-filtered" title="Select all filtered rows" aria-label="Select all filtered rows">';
                },
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
                width: 200,
            },
            {
                title: 'LMP',
                field: 'lmp_price',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 90,
                headerSort: true,
                formatter: lmpFormatter,
            },
            {
                title: 'M',
                field: 'm',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 52,
                headerSort: false,
                cssClass: 'sku-link-lmp-amz-m-col',
                editor: 'input',
                editorParams: {
                    elementAttributes: {
                        maxlength: '1',
                    },
                },
                formatter: mFormatter,
                cellEdited: function (cell) {
                    saveMChar(cell);
                },
            },
            {
                title: 'Sku Link LMP AMZ',
                field: 'linked_lmp_skus',
                hozAlign: 'left',
                headerHozAlign: 'center',
                width: 220,
                headerSort: false,
                cssClass: 'linked-sku-col',
                formatter: linkedLmpSkuFormatter,
                cellClick: function (e, cell) {
                    if (e.target.closest('.sku-link-lmp-amz-remove')) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeLinkedSkuFromRow(
                            cell.getRow().getData(),
                            e.target.closest('.sku-link-lmp-amz-remove').dataset.linkedSku || ''
                        );
                    }
                },
            },
            {
                title: '+',
                field: 'linked_lmp_sku_add',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 52,
                headerSort: false,
                cssClass: 'linked-sku-add-col',
                formatter: linkedLmpSkuAddFormatter,
                cellClick: function (e, cell) {
                    if (e.target.closest('.sku-link-lmp-amz-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        bulkLinkSelectedSkus(
                            cell.getRow().getData(),
                            e.target.closest('.sku-link-lmp-amz-add-btn')
                        );
                    }
                },
            },
            {
                title: 'AMZ',
                field: 'amz_lmp',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 90,
                headerSort: true,
                sorter: 'number',
                formatter: amzLmpFormatter,
            },
            {
                title: 'Edit',
                field: 'bulk_edit',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 70,
                headerSort: false,
                headerTooltip: 'Bulk edit selected rows',
                formatter: bulkEditFormatter,
                cellClick: function (e, cell) {
                    if (!e.target.closest('.sku-link-lmp-amz-bulk-edit-row-btn')) {
                        return;
                    }
                    e.preventDefault();
                    e.stopPropagation();
                    openBulkEditModalForRow(cell.getRow().getData(), cell.getRow());
                },
            },
            {
                title: 'History',
                field: 'history_view',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 140,
                headerSort: false,
                headerTooltip: 'Competitor ID add/delete history',
                formatter: historyFormatter,
                cellClick: function (e, cell) {
                    if (e.target.closest('.sku-link-lmp-amz-history-dot')) {
                        e.preventDefault();
                        e.stopPropagation();
                        openHistoryModal(cell.getRow().getData());
                    }
                },
            },
        ],
    });

    table.on('rowSelectionChanged', function () {
        if (allFilteredSelected && bulkSelectionSkuCache.size > 0) {
            table.getRows('active').forEach(function (row) {
                const sku = String(row.getData().sku || '').trim();
                if (!sku) {
                    return;
                }
                if (row.isSelected()) {
                    bulkSelectionSkuCache.add(sku);
                } else {
                    bulkSelectionSkuCache.delete(sku);
                    allFilteredSelected = false;
                    updateSelectAllFilteredCheckbox(false);
                }
            });
        } else {
            bulkSelectionSkuCache = new Set(
                getSelectedRows()
                    .map(function (row) { return String(row.getData().sku || '').trim(); })
                    .filter(Boolean)
            );
        }
        updateBulkEditBadge();
    });

    table.on('pageLoaded', function () {
        restoreVisibleRowSelectionFromCache();
        if (allFilteredSelected && bulkSelectionSkuCache.size > 0) {
            updateSelectAllFilteredCheckbox(true);
        }
    });

    table.on('dataLoaded', function () {
        restoreVisibleRowSelectionFromCache();
    });
});
</script>
@endsection
