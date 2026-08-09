@extends('layouts.vertical', ['title' => 'Ads Link'])

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .tabulator {
        font-size: 12px;
        border: 1px solid #dee2e6;
    }

    .tabulator .tabulator-header {
        background: linear-gradient(90deg, #e0e7ff 0%, #f4f7fa 100%);
        border-bottom: 2px solid #2563eb;
        font-weight: 600;
        font-size: 11px;
    }

    .tabulator .tabulator-header .tabulator-col {
        border-right: 1px solid #e5e7eb;
    }

    .tabulator-row {
        min-height: 28px !important;
    }

    .tabulator-row:hover {
        background-color: #f8f9fa !important;
    }

    .tabulator-cell {
        padding: 2px 4px !important;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
        padding: 4px 10px;
        margin: 0 2px;
        border-radius: 6px;
        font-size: 0.85rem;
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
        padding-top: 2px !important;
        padding-bottom: 2px !important;
        overflow: hidden;
    }

    .tabulator .tabulator-cell.linked-sku-col .linked-sku-badge:hover {
        background-color: #cffafe !important;
    }

    .linked-sku-badge-wrap {
        display: inline-flex;
        align-items: center;
        gap: 1px;
        font-size: 10px;
        padding: 1px 5px !important;
        margin: 0 2px 0 0 !important;
        white-space: nowrap;
        max-width: 140px;
    }

    .linked-sku-badge-wrap .linked-sku-badge {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 110px;
    }

    .linked-sku-badge-wrap .ads-link-remove {
        font-size: 0.45rem;
        opacity: 0.65;
        padding: 0;
        margin-left: 1px;
        flex-shrink: 0;
    }

    .linked-sku-badge-wrap .ads-link-remove:hover {
        opacity: 1;
    }

    .ads-link-chip-row {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: center;
        gap: 2px;
        overflow: hidden;
        max-width: 100%;
        white-space: nowrap;
        line-height: 1.2;
        padding: 0;
    }

    .ads-link-chip-more {
        font-size: 10px;
        color: #64748b;
        flex-shrink: 0;
        white-space: nowrap;
    }

    .ads-link-play-group {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-radius: 50px;
        overflow: hidden;
        padding: 2px;
        background: #f8f9fa;
        display: inline-flex;
        align-items: center;
    }

    .ads-link-play-group button {
        padding: 0;
        border-radius: 50% !important;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 2px;
        transition: all 0.2s ease;
        border: 1px solid #dee2e6;
        background: white;
        cursor: pointer;
    }

    .ads-link-play-group button:hover {
        background-color: #f1f3f5 !important;
        transform: scale(1.05);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .ads-link-play-group button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none !important;
        box-shadow: none !important;
    }

    .ads-link-play-group button i {
        font-size: 0.95rem;
    }

    #ads-link-play-auto {
        color: #28a745;
    }

    #ads-link-play-auto:hover {
        background-color: #28a745 !important;
        color: white !important;
    }

    #ads-link-play-pause {
        color: #ffc107;
        display: none;
    }

    #ads-link-play-pause:hover {
        background-color: #ffc107 !important;
        color: white !important;
    }

    #ads-link-play-backward,
    #ads-link-play-forward {
        color: #007bff;
    }

    #ads-link-play-backward:hover,
    #ads-link-play-forward:hover {
        background-color: #007bff !important;
        color: white !important;
    }

    .ads-link-play-group button.is-active-nav {
        background-color: #007bff !important;
        color: white !important;
        border-color: #007bff;
    }

    .ads-link-suggestion-item {
        cursor: pointer;
    }

    .ads-link-suggestion-item .form-check-input {
        cursor: pointer;
        flex-shrink: 0;
    }

    .ads-link-selected-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin: 0 4px 4px 0;
        padding: 2px 8px;
        border-radius: 999px;
        background: #e0e7ff;
        font-size: 12px;
    }

    .ads-link-selected-chip button {
        border: 0;
        background: transparent;
        padding: 0;
        line-height: 1;
        font-size: 14px;
        color: #64748b;
    }

    .tabulator-cell[tabulator-field="bulk_edit"] {
        padding-top: 4px !important;
        padding-bottom: 4px !important;
    }

    .tabulator-cell[tabulator-field="history_view"] {
        padding-top: 4px !important;
        padding-bottom: 4px !important;
    }

    .ads-link-history-cell {
        line-height: 1.25;
    }

    .ads-link-history-dot-icon {
        display: inline-block;
        font-size: 1.35rem;
        line-height: 1;
        color: #2563eb;
    }

    .ads-link-history-dot:hover .ads-link-history-dot-icon {
        color: #1d4ed8;
    }

    .ads-link-history-table th,
    .ads-link-history-table td {
        font-size: 12px;
        vertical-align: middle;
    }

    .tabulator .tabulator-cell.ads-link-list-col {
        padding-top: 2px !important;
        padding-bottom: 2px !important;
        text-align: center !important;
        overflow: hidden;
    }

    .tabulator .tabulator-cell.ads-link-list-col .d-flex {
        justify-content: center;
        flex-wrap: nowrap !important;
        overflow: hidden;
    }

    .tabulator .tabulator-cell.linked-sku-col {
        text-align: center !important;
    }

    .tabulator .tabulator-cell.linked-sku-col .d-flex {
        justify-content: center;
    }

    .ads-link-field-chip-wrap {
        display: inline-flex;
        align-items: center;
        gap: 1px;
        font-size: 10px;
        padding: 1px 5px !important;
        margin: 0 2px 0 0 !important;
        white-space: nowrap;
        max-width: 100px;
    }

    .ads-link-field-chip-wrap .ads-link-field-remove {
        font-size: 0.45rem;
        opacity: 0.65;
        padding: 0;
        margin-left: 1px;
        flex-shrink: 0;
    }

    .ads-link-field-chip-wrap .ads-link-field-remove:hover {
        opacity: 1;
    }

    .ads-link-field-add-btn {
        padding: 0 4px !important;
        line-height: 1.1;
        font-size: 11px;
        min-width: 22px;
    }

    .tabulator .tabulator-cell.ads-link-campaign-col {
        padding-top: 2px !important;
        padding-bottom: 2px !important;
        text-align: center !important;
        overflow: hidden;
    }

    .ads-link-campaign-chip {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        background: #e7f5ff;
        color: #1971c2;
        border: 1px solid #a5d8ff;
        border-radius: 10px;
        padding: 1px 6px;
        margin: 0 2px 0 0;
        font-size: 10px;
        white-space: nowrap;
        max-width: 160px;
    }

    .ads-link-campaign-chip .ads-link-campaign-name {
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 120px;
    }

    .ads-link-campaign-chip .ads-link-campaign-remove {
        cursor: pointer;
        color: #e03131;
        font-size: 0.65rem;
        line-height: 1;
        border: none;
        background: transparent;
        padding: 0;
        opacity: 0.75;
    }

    .ads-link-campaign-chip .ads-link-campaign-remove:hover {
        opacity: 1;
    }

    .ads-link-campaign-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        border: 1px solid rgba(0, 0, 0, 0.15);
        flex: 0 0 auto;
    }

    .ads-link-campaign-dot-green {
        background-color: #16a34a;
    }

    .ads-link-campaign-dot-red {
        background-color: #dc2626;
    }

    /* KW(-) red header — shared look with /google/shopping/missing */
    .tabulator-header .tabulator-col.ads-link-kw-neg-col {
        background-color: #dc2626 !important;
        border-right-color: #b91c1c !important;
    }
    .tabulator-header .tabulator-col.ads-link-kw-neg-col .tabulator-col-content,
    .tabulator-header .tabulator-col.ads-link-kw-neg-col .tabulator-col-title {
        color: #fff !important;
        font-weight: 700;
    }
    .tabulator-header .tabulator-col.ads-link-kw-neg-col .tabulator-col-sorter {
        display: none !important;
    }

    .ads-link-campaign-add-btn {
        padding: 0 4px !important;
        line-height: 1.1;
        font-size: 11px;
        min-width: 22px;
        color: #2f9e44 !important;
        border-color: #adb5bd !important;
        background: #fff !important;
    }

    .ads-link-campaign-add-btn:hover {
        background: #f1f3f5 !important;
        color: #2b8a3e !important;
    }

    #ads-link-campaign-suggestions .list-group-item {
        cursor: pointer;
        font-size: 0.85rem;
        padding: 0.4rem 0.75rem;
    }

    #ads-link-campaign-suggestions .list-group-item.relevant {
        background: #f0fdf4;
    }

    #ads-link-campaign-suggestions .list-group-item.linked {
        opacity: 0.55;
        cursor: default;
    }

    #ads-link-page .card-body {
        padding: 0.75rem;
    }

    #ads-link-page .mb-3 {
        margin-bottom: 0.5rem !important;
    }
</style>
@endsection

@section('content')
<div class="container-fluid mt-2" id="ads-link-page">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'ads_link'])
                        <h4 class="mb-0">
                            <i class="mdi mdi-link-variant"></i> Ads Link
                        </h4>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
                        <div class="btn-group ads-link-play-group" role="group" aria-label="Parent navigation">
                            <button type="button" id="ads-link-play-backward" class="btn btn-light rounded-circle" title="Previous parent">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button type="button" id="ads-link-play-pause" class="btn btn-light rounded-circle" title="Show all products">
                                <i class="fas fa-pause"></i>
                            </button>
                            <button type="button" id="ads-link-play-auto" class="btn btn-light rounded-circle" title="Start parent navigation">
                                <i class="fas fa-play"></i>
                            </button>
                            <button type="button" id="ads-link-play-forward" class="btn btn-light rounded-circle" title="Next parent">
                                <i class="fas fa-step-forward"></i>
                            </button>
                        </div>
                        <input type="text" id="ads-link-search-parent" class="form-control form-control-sm" style="max-width: 220px;" placeholder="Search Parent...">
                        <input type="text" id="ads-link-search-sku" class="form-control form-control-sm" style="max-width: 220px;" placeholder="Search SKU...">
                        <button type="button" id="ads-link-merge-btn" class="btn btn-sm btn-success"
                            title="Merge keywords across linked Campaign KW campaigns (duplicates skipped)">
                            <i class="fas fa-object-group me-1"></i> Merge
                        </button>
                        <span id="ads-link-play-status" class="text-muted small"></span>
                    </div>
                    <div id="ads-link-bulk-edit-badge" class="d-none mb-2 p-2 rounded border bg-light align-items-center gap-2 flex-wrap" style="min-height: 40px;">
                        <span class="fw-semibold text-dark" id="ads-link-bulk-edit-count">0 selected</span>
                        <span class="text-muted small">Select rows with checkboxes, then use <strong>Bulk Edit</strong>.</span>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="ads-link-bulk-edit-btn" title="Bulk edit selected rows">
                            <i class="mdi mdi-pencil-box-multiple-outline me-1"></i> Bulk Edit
                        </button>
                    </div>
                    <div id="ads-link-table"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adsLinkFieldModal" tabindex="-1" aria-labelledby="adsLinkFieldModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adsLinkFieldModalLabel">Edit Field</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">
                    Editing <strong id="ads-link-field-modal-label"></strong> for
                    <strong id="ads-link-field-modal-sku"></strong>
                </p>
                <div class="border rounded p-2 mb-3 bg-light">
                    <div class="small text-muted mb-2">
                        Bulk add: download the template, fill one keyword per row, then upload.
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ads-link-field-download-template-btn">
                            <i class="mdi mdi-download me-1"></i> Download Template
                        </button>
                        <label class="btn btn-sm btn-outline-primary mb-0" for="ads-link-field-bulk-file">
                            <i class="mdi mdi-upload me-1"></i> Upload CSV
                        </label>
                        <input type="file" id="ads-link-field-bulk-file" class="d-none" accept=".csv,text/csv,.txt">
                        <span class="small text-muted" id="ads-link-field-bulk-status"></span>
                    </div>
                </div>
                <label for="ads-link-field-input" class="form-label mb-1">Add item</label>
                <div class="input-group mb-2">
                    <input type="text" id="ads-link-field-input" class="form-control" placeholder="Type and press Enter..." autocomplete="off">
                    <button type="button" class="btn btn-outline-primary" id="ads-link-field-add-item-btn">Add</button>
                </div>
                <div id="ads-link-field-items" class="d-flex flex-wrap gap-1 mb-2"></div>
                <div class="form-text small">Items are saved for this page only (separate from LMP).</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="ads-link-field-save-btn">
                    <i class="mdi mdi-content-save"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adsLinkHistoryModal" tabindex="-1" aria-labelledby="adsLinkHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="adsLinkHistoryModalLabel">
                    <i class="fas fa-history"></i> Ads Link History — <span id="ads-link-history-modal-sku"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ads-link-history-loading" class="text-center py-4">
                    <div class="spinner-border text-secondary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 mb-0">Loading history...</p>
                </div>
                <div id="ads-link-history-empty" class="alert alert-info mb-0 d-none">
                    <i class="fa fa-info-circle"></i> No Ads link history found for this SKU.
                </div>
                <div id="ads-link-history-error" class="alert alert-danger mb-0 d-none"></div>
                <div id="ads-link-history-table-wrap" class="table-responsive d-none" style="max-height: 65vh;">
                    <table class="table table-bordered table-hover ads-link-history-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 130px;">Date</th>
                                <th style="width: 90px;">Time</th>
                                <th style="width: 120px;">User</th>
                                <th style="width: 90px;">Action</th>
                                <th style="width: 160px;">Linked SKU</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody id="ads-link-history-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adsLinkBulkEditModal" tabindex="-1" aria-labelledby="adsLinkBulkEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold d-flex align-items-center m-0" id="adsLinkBulkEditModalLabel">
                    <i class="mdi mdi-pencil-box-multiple-outline me-2"></i>
                    Bulk Edit
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border d-flex align-items-center mb-3 py-2 px-3" role="status">
                    <i class="mdi mdi-information-outline text-primary me-2"></i>
                    <div class="small">
                        Editing <strong id="ads-link-bulk-target-count">0</strong> row(s):
                        <span class="text-muted" id="ads-link-bulk-target-skus"></span>
                    </div>
                </div>
                <p class="text-muted small mb-3">Only fields you fill in below will be applied. Leave a field blank to keep its current value.</p>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="ads-link-bulk-link-sku" class="form-label fw-semibold">Link another SKU</label>
                        <input type="text" id="ads-link-bulk-link-sku" class="form-control form-control-sm" placeholder="— Keep current —" autocomplete="off">
                        <div class="form-text small">Links this SKU to every selected row (same as the + column modal).</div>
                    </div>
                </div>
                <div class="mt-3" id="ads-link-bulk-link-together-wrap" style="display:none;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="ads-link-bulk-link-together-btn">
                        <i class="mdi mdi-link-variant me-1"></i> Link all selected SKUs together
                    </button>
                    <div class="form-text small">When 2+ rows are selected, link them into one Ads group (same as + with multi-select).</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="ads-link-bulk-edit-apply-btn">
                    <i class="mdi mdi-check me-1"></i> Apply
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adsLinkModal" tabindex="-1" aria-labelledby="adsLinkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adsLinkModalLabel">Sku Link Ads</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Link one or more SKUs to <strong id="ads-link-source"></strong>. All linked SKUs will show each other.</p>
                <label for="ads-link-input" class="form-label mb-1">Search SKU to link</label>
                <input type="text" id="ads-link-input" class="form-control" placeholder="Search or enter SKU..." autocomplete="off">
                <div id="ads-link-suggestions" class="list-group mt-2 d-none" style="max-height: 220px; overflow-y: auto;"></div>
                <div id="ads-link-selected-wrap" class="mt-2 d-none">
                    <div class="small text-muted mb-1">Selected to link (<span id="ads-link-selected-count">0</span>):</div>
                    <div id="ads-link-selected-skus" class="d-flex flex-wrap"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="ads-link-save-btn">
                    <i class="mdi mdi-link"></i> <span id="ads-link-save-btn-label">Link SKU(s)</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adsLinkCampaignModal" tabindex="-1" aria-labelledby="adsLinkCampaignModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adsLinkCampaignModalLabel">Add Campaign</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">
                    Link SP campaigns as <strong id="ads-link-campaign-modal-type">KW</strong> for parent
                    <strong id="ads-link-campaign-modal-parent"></strong>
                    (<span class="text-muted">(<span id="ads-link-campaign-modal-link-sku"></span>)</span>.
                    Same data as Amz Ads Missing.
                </p>
                <label for="ads-link-campaign-search" class="form-label mb-1">Quick search</label>
                <input type="text" id="ads-link-campaign-search" class="form-control" placeholder="Search campaigns..." autocomplete="off">
                <div id="ads-link-campaign-suggestions" class="list-group mt-2" style="max-height: 320px; overflow-y: auto;">
                    <div class="list-group-item text-muted small">Type to search campaigns…</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adsLinkKeywordsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="fas fa-key"></i> Keywords — <span id="ads-link-kw-title"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ads-link-kw-campaign-wrap" class="mb-2 d-none">
                    <label for="ads-link-kw-campaign-select" class="form-label mb-1 small">Campaign</label>
                    <select id="ads-link-kw-campaign-select" class="form-select form-select-sm"></select>
                </div>
                <div id="ads-link-kw-loading" class="text-center py-4">
                    <div class="spinner-border text-secondary" role="status"><span class="visually-hidden">Loading...</span></div>
                    <p class="mt-2 mb-0">Loading keywords...</p>
                </div>
                <div id="ads-link-kw-empty" class="alert alert-info mb-0 d-none"><i class="fa fa-info-circle"></i> No keywords found for this campaign.</div>
                <div id="ads-link-kw-error" class="alert alert-danger mb-0 d-none"></div>
                <div id="ads-link-kw-table-wrap" class="table-responsive d-none" style="max-height: 65vh;">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Keyword</th>
                                <th style="width: 90px;">Match</th>
                                <th style="width: 70px;" class="text-end">Impr</th>
                                <th style="width: 60px;" class="text-end">Clk</th>
                                <th style="width: 80px;" class="text-end">Cost</th>
                                <th style="width: 70px;" class="text-end">CPC</th>
                                <th style="width: 60px;" class="text-end">Sold</th>
                                <th style="width: 80px;" class="text-end">Sales</th>
                                <th style="width: 70px;" class="text-end">ACOS</th>
                            </tr>
                        </thead>
                        <tbody id="ads-link-kw-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="adsLinkNegativesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-ban"></i> Negative Keywords — <span id="ads-link-neg-title"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ads-link-neg-campaign-wrap" class="mb-2 d-none">
                    <label for="ads-link-neg-campaign-select" class="form-label mb-1 small">Campaign</label>
                    <select id="ads-link-neg-campaign-select" class="form-select form-select-sm"></select>
                </div>
                <div id="ads-link-neg-loading" class="text-center py-4">
                    <div class="spinner-border text-secondary" role="status"><span class="visually-hidden">Loading...</span></div>
                    <p class="mt-2 mb-0">Loading negative keywords...</p>
                </div>
                <div id="ads-link-neg-empty" class="alert alert-info mb-0 d-none"><i class="fa fa-info-circle"></i> No negative keywords found for this campaign.</div>
                <div id="ads-link-neg-error" class="alert alert-danger mb-0 d-none"></div>
                <div id="ads-link-neg-table-wrap" class="table-responsive d-none" style="max-height: 65vh;">
                    <table class="table table-sm table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Negative Keyword</th>
                                <th style="width: 120px;">Match</th>
                                <th style="width: 110px;">Level</th>
                                <th style="width: 90px;">State</th>
                            </tr>
                        </thead>
                        <tbody id="ads-link-neg-tbody"></tbody>
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
    const dataUrl = @json(route('ads.link.data'));
    const parentsUrl = @json(route('ads.link.parents'));
    const linkedSkuAddUrl = @json(route('ads.link.linked-skus.add'));
    const linkedSkuBulkLinkUrl = @json(route('ads.link.linked-skus.bulk-link'));
    const linkedSkuRemoveUrl = @json(route('ads.link.linked-skus.remove'));
    const filteredSkusUrl = @json(route('ads.link.filtered-skus'));
    const historyUrl = @json(route('ads.link.history'));
    const saveListFieldUrl = @json(route('ads.link.fields.list'));
    const saveSplFieldUrl = @json(route('ads.link.fields.spl'));
    const campaignsUrl = @json(route('ads.link.campaigns'));
    const campaignLinkUrl = @json(route('ads.link.campaigns.link'));
    const campaignUnlinkUrl = @json(route('ads.link.campaigns.unlink'));
    const campaignMergeUrl = @json(route('ads.link.campaigns.merge'));
    const campaignLinkKeywordsUrl = @json(route('amazon.ads.campaign-link.keywords'));
    const negativeLinkKeywordsUrl = @json(route('amazon.ads.negative-link.keywords'));

    const LIST_FIELD_LABELS = {
        plus_kw: '+ KW',
        minus_kw: '(-) KW',
        plus_pt: '+ PT',
        minus_pt: '(-) PT',
        plus_kw_spl: '+ KW SPL',
        pt_spl: 'PT SPL',
        spl_minus_kw: 'SPL (-)KW',
        spl_minus_pt: 'SPL (-)PT',
    };

    let searchTimer = null;
    let table = null;
    let linkedSkuModal = null;
    let linkedSkuModalRow = null;
    let linkedSkuModalSelectedSkus = new Set();
    let linkedSkuSuggestionTimer = null;
    let linkedSkuSuggestionRequestId = 0;
    let bulkEditModal = null;
    let bulkEditTargetSkus = [];
    let bulkSelectionSkuCache = new Set();
    let fieldModal = null;
    let fieldModalSku = '';
    let fieldModalField = '';
    let fieldModalItems = [];
    let campaignModal = null;
    let campaignModalParent = '';
    let campaignModalType = 'KW';
    let campaignModalLinkSku = '';
    let campaignModalLinkedNames = new Set();
    let campaignSearchTimer = null;
    let campaignSearchRequestId = 0;
    let keywordsModal = null;
    let keywordsModalCampaigns = [];
    let negativesModal = null;
    let negativesModalCampaigns = [];
    let productUniqueParents = [];
    let isProductNavigationActive = false;
    let currentProductParentIndex = -1;
    let isPlaybackUpdating = false;

    const playAutoBtn = document.getElementById('ads-link-play-auto');
    const playPauseBtn = document.getElementById('ads-link-play-pause');
    const playBackwardBtn = document.getElementById('ads-link-play-backward');
    const playForwardBtn = document.getElementById('ads-link-play-forward');
    const playStatusEl = document.getElementById('ads-link-play-status');
    const parentSearchInput = document.getElementById('ads-link-search-parent');
    const skuSearchInput = document.getElementById('ads-link-search-sku');

    const modalEl = document.getElementById('adsLinkModal');
    if (modalEl) {
        linkedSkuModal = new bootstrap.Modal(modalEl);
    }

    const bulkEditModalEl = document.getElementById('adsLinkBulkEditModal');
    if (bulkEditModalEl) {
        bulkEditModal = bootstrap.Modal.getOrCreateInstance(bulkEditModalEl);
    }

    const historyModalEl = document.getElementById('adsLinkHistoryModal');
    const historyModal = historyModalEl ? bootstrap.Modal.getOrCreateInstance(historyModalEl) : null;

    const fieldModalEl = document.getElementById('adsLinkFieldModal');
    if (fieldModalEl) {
        fieldModal = bootstrap.Modal.getOrCreateInstance(fieldModalEl);
    }

    const campaignModalEl = document.getElementById('adsLinkCampaignModal');
    if (campaignModalEl) {
        campaignModal = bootstrap.Modal.getOrCreateInstance(campaignModalEl);
    }

    const keywordsModalEl = document.getElementById('adsLinkKeywordsModal');
    if (keywordsModalEl) {
        keywordsModal = bootstrap.Modal.getOrCreateInstance(keywordsModalEl);
    }

    const negativesModalEl = document.getElementById('adsLinkNegativesModal');
    if (negativesModalEl) {
        negativesModal = bootstrap.Modal.getOrCreateInstance(negativesModalEl);
    }

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
            return '';
        }

        return `<img src="${escapeHtmlAttr(url)}" alt="Product"
            style="height:28px;max-width:40px;border-radius:3px;border:1px solid #ccc;object-fit:contain;">`;
    }

    function skuFormatter(cell) {
        const sku = String(cell.getValue() || '').trim();
        if (!sku) {
            return '<span class="text-muted">—</span>';
        }
        return `<div class="d-flex align-items-center justify-content-center gap-1">
            <span>${escapeHtml(sku)}</span>
            <button type="button" class="btn btn-sm btn-link p-0 ads-link-copy-sku" data-sku="${escapeHtmlAttr(sku)}" title="Copy SKU" aria-label="Copy SKU">
                <i class="fas fa-copy"></i>
            </button>
        </div>`;
    }

    function copySkuToClipboard(sku, btn) {
        const value = String(sku || '').trim();
        if (!value) {
            return;
        }

        const showCopied = function () {
            if (!btn) {
                return;
            }
            const icon = btn.querySelector('i');
            if (!icon) {
                return;
            }
            const originalClass = icon.className;
            icon.className = 'fas fa-check text-success';
            setTimeout(function () { icon.className = originalClass; }, 1200);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(showCopied).catch(function () {
                fallbackCopyText(value);
                showCopied();
            });
            return;
        }
        fallbackCopyText(value);
        showCopied();
    }

    function fallbackCopyText(value) {
        const textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
        } catch (err) {
            /* no-op */
        }
        document.body.removeChild(textarea);
    }

    function linkedAdsSkuFormatter(cell) {
        const row = cell.getRow().getData();
        const rowSku = String(row.sku || '').trim();
        let skus = row.linked_ads_skus || [];
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

        const maxVisible = 3;
        const visible = skus.slice(0, maxVisible);
        const hiddenCount = Math.max(0, skus.length - visible.length);
        const allTitle = skus.join(', ');

        const badges = visible.length
            ? visible.map(function (sku) {
                const skuText = String(sku || '').trim();
                const isSelf = skuText.toUpperCase() === rowSku.toUpperCase();
                const removeBtn = isSelf
                    ? ''
                    : `<button type="button" class="btn-close ads-link-remove"
                        data-linked-sku="${escapeHtmlAttr(skuText)}" aria-label="Remove link to ${escapeHtmlAttr(skuText)}"></button>`;
                return `<span class="linked-sku-badge-wrap badge bg-info-subtle text-dark border" title="${escapeHtmlAttr(skuText)}">
                    <span class="linked-sku-badge">${escapeHtml(skuText)}</span>${removeBtn}
                </span>`;
            }).join('')
            : '';

        const more = hiddenCount > 0
            ? `<span class="ads-link-chip-more" title="${escapeHtmlAttr(allTitle)}">+${hiddenCount}</span>`
            : '';

        return `<div class="ads-link-chip-row" title="${escapeHtmlAttr(allTitle)}">${badges}${more}</div>`;
    }

    function linkedAdsSkuAddFormatter(cell) {
        const rowSku = String(cell.getRow().getData().sku || '').trim();
        if (!rowSku) {
            return '';
        }

        return `<div class="d-flex align-items-center justify-content-center">
            <button type="button" class="btn btn-sm btn-outline-primary ads-link-add-btn"
                title="Link another SKU" style="padding:1px 6px;line-height:1;" data-sku="${escapeHtmlAttr(rowSku)}">
                <i class="mdi mdi-plus"></i>
            </button>
        </div>`;
    }

    function parseListField(value) {
        if (Array.isArray(value)) {
            return value.map(function (item) { return String(item || '').trim(); }).filter(Boolean);
        }
        if (typeof value === 'string' && value.trim() !== '') {
            try {
                const parsed = JSON.parse(value);
                if (Array.isArray(parsed)) {
                    return parsed.map(function (item) { return String(item || '').trim(); }).filter(Boolean);
                }
            } catch (e) {
                return value.split(/[\n,]+/).map(function (item) { return item.trim(); }).filter(Boolean);
            }
        }
        return [];
    }

    function listFieldFormatter(fieldKey) {
        return function (cell) {
            const row = cell.getRow().getData();
            const items = parseListField(row[fieldKey]);
            const maxVisible = 2;
            const visible = items.slice(0, maxVisible);
            const hiddenCount = Math.max(0, items.length - visible.length);
            const allTitle = items.join(', ');

            const chips = visible.map(function (item) {
                return `<span class="ads-link-field-chip-wrap badge bg-info-subtle text-dark border" title="${escapeHtmlAttr(item)}">
                    <span>${escapeHtml(item)}</span>
                    <button type="button" class="btn-close ads-link-field-remove"
                        data-field="${escapeHtmlAttr(fieldKey)}" data-item="${escapeHtmlAttr(item)}"
                        aria-label="Remove ${escapeHtmlAttr(item)}"></button>
                </span>`;
            }).join('');

            const more = hiddenCount > 0
                ? `<span class="ads-link-chip-more" title="${escapeHtmlAttr(allTitle)}">+${hiddenCount}</span>`
                : '';

            return `<div class="ads-link-chip-row" title="${escapeHtmlAttr(allTitle)}">
                ${chips}${more}
                <button type="button" class="btn btn-sm btn-outline-secondary ads-link-field-add-btn"
                    data-field="${escapeHtmlAttr(fieldKey)}" title="Edit ${escapeHtmlAttr(LIST_FIELD_LABELS[fieldKey] || fieldKey)}">+</button>
            </div>`;
        };
    }

    function campaignStatusDotHtml(campaign) {
        const dot = String(campaign?.dot || '');
        if (dot !== 'green' && dot !== 'red') {
            return '';
        }
        const status = campaign?.status || (dot === 'green' ? 'ENABLED' : 'PAUSED');
        const title = status.charAt(0) + status.slice(1).toLowerCase();
        return `<span class="ads-link-campaign-dot ads-link-campaign-dot-${dot}" title="${escapeHtmlAttr(title)}"></span>`;
    }

    function campaignFormatter(type) {
        const field = type === 'PT' ? 'pt' : 'kw';
        return function (cell) {
            const row = cell.getRow().getData();
            const campaigns = Array.isArray(row[field]) ? row[field] : [];
            const maxVisible = 2;
            const visible = campaigns.slice(0, maxVisible);
            const hiddenCount = Math.max(0, campaigns.length - visible.length);
            const allTitle = campaigns.map(function (c) { return c.campaign_name || ''; }).filter(Boolean).join(', ');

            const chips = visible.map(function (c) {
                const name = String(c.campaign_name || '');
                return `<span class="ads-link-campaign-chip" title="${escapeHtmlAttr(name)}">
                    ${campaignStatusDotHtml(c)}
                    <span class="ads-link-campaign-name">${escapeHtml(name)}</span>
                    <button type="button" class="ads-link-campaign-remove" data-id="${escapeHtmlAttr(c.id)}"
                        data-type="${escapeHtmlAttr(type)}"
                        aria-label="Remove ${escapeHtmlAttr(name)}" title="Remove">&times;</button>
                </span>`;
            }).join('');

            const more = hiddenCount > 0
                ? `<span class="ads-link-chip-more" title="${escapeHtmlAttr(allTitle)}">+${hiddenCount}</span>`
                : '';

            return `<div class="ads-link-chip-row" title="${escapeHtmlAttr(allTitle)}">
                ${chips}${more}
                <button type="button" class="btn btn-sm btn-outline-secondary ads-link-campaign-add-btn"
                    data-type="${escapeHtmlAttr(type)}" title="Add ${escapeHtmlAttr(type)} campaign">+</button>
            </div>`;
        };
    }

    function openCampaignModal(rowData, type) {
        if (!campaignModal || !rowData?.parent) {
            return;
        }
        campaignModalType = type === 'PT' ? 'PT' : 'KW';
        campaignModalParent = String(rowData.parent || '').trim();
        campaignModalLinkSku = String(rowData.campaign_link_sku || '').trim()
            || (campaignModalParent ? `PARENT ${campaignModalParent}` : '');
        const list = Array.isArray(rowData[campaignModalType === 'PT' ? 'pt' : 'kw'])
            ? rowData[campaignModalType === 'PT' ? 'pt' : 'kw']
            : [];
        campaignModalLinkedNames = new Set(
            list.map(function (c) { return String(c.campaign_name || '').trim().toLowerCase(); }).filter(Boolean)
        );

        document.getElementById('ads-link-campaign-modal-type').textContent = campaignModalType;
        document.getElementById('ads-link-campaign-modal-parent').textContent = campaignModalParent;
        document.getElementById('ads-link-campaign-modal-link-sku').textContent = campaignModalLinkSku;
        document.getElementById('adsLinkCampaignModalLabel').textContent = `Add Campaign ${campaignModalType}`;

        const searchInput = document.getElementById('ads-link-campaign-search');
        if (searchInput) {
            searchInput.value = campaignModalParent || '';
        }
        campaignModal.show();
        setTimeout(function () {
            searchInput?.focus();
            searchInput?.select();
            loadCampaignSuggestions(searchInput?.value || '');
        }, 200);
    }

    function renderCampaignSuggestions(items) {
        const wrap = document.getElementById('ads-link-campaign-suggestions');
        if (!wrap) {
            return;
        }
        if (!items.length) {
            wrap.innerHTML = '<div class="list-group-item text-muted small">No matching campaigns</div>';
            return;
        }
        wrap.innerHTML = items.map(function (item) {
            const name = String(item.campaign_name || '');
            const linked = campaignModalLinkedNames.has(name.trim().toLowerCase());
            const classes = [
                'list-group-item',
                'list-group-item-action',
                item.relevant ? 'relevant' : '',
                linked ? 'linked' : '',
            ].filter(Boolean).join(' ');
            const badge = linked
                ? '<span class="badge bg-secondary ms-auto">Linked</span>'
                : (item.relevant ? '<span class="badge bg-success-subtle text-success border ms-auto">Relevant</span>' : '');
            return `<button type="button" class="${classes}" data-name="${escapeHtmlAttr(name)}" ${linked ? 'disabled' : ''}>
                <span class="d-flex align-items-center gap-2 w-100">
                    ${campaignStatusDotHtml(item)}
                    <span class="text-start flex-grow-1">${escapeHtml(name)}</span>
                    ${badge}
                </span>
            </button>`;
        }).join('');
    }

    function loadCampaignSuggestions(query) {
        const requestId = ++campaignSearchRequestId;
        const wrap = document.getElementById('ads-link-campaign-suggestions');
        if (wrap) {
            wrap.innerHTML = '<div class="list-group-item text-muted small">Searching…</div>';
        }
        const params = new URLSearchParams({
            q: query || '',
            parent: campaignModalParent || '',
            sku: campaignModalLinkSku || '',
        });
        fetch(`${campaignsUrl}?${params.toString()}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (requestId !== campaignSearchRequestId) {
                    return;
                }
                renderCampaignSuggestions(Array.isArray(json?.data) ? json.data : []);
            })
            .catch(function () {
                if (requestId !== campaignSearchRequestId) {
                    return;
                }
                if (wrap) {
                    wrap.innerHTML = '<div class="list-group-item text-danger small">Failed to load campaigns</div>';
                }
            });
    }

    function scheduleCampaignSearch(query) {
        clearTimeout(campaignSearchTimer);
        campaignSearchTimer = setTimeout(function () {
            loadCampaignSuggestions(query);
        }, 250);
    }

    function updateParentCampaignRows(parent, payload) {
        if (!table || !parent) {
            return;
        }
        const parentNorm = String(parent).trim().toUpperCase();
        const kw = Array.isArray(payload?.kw) ? payload.kw : [];
        const pt = Array.isArray(payload?.pt) ? payload.pt : [];
        const linkSku = payload?.campaign_link_sku || `PARENT ${parent}`;
        const keywordCount = Number(payload?.keyword_count) || 0;
        const negativeCount = Number(payload?.negative_count) || 0;
        const kwCampaign = payload?.kw_campaign || (kw[0]?.campaign_name || null);
        table.getRows().forEach(function (row) {
            const data = row.getData();
            if (String(data.parent || '').trim().toUpperCase() === parentNorm) {
                row.update({
                    kw: kw,
                    pt: pt,
                    campaign_link_sku: linkSku,
                    keyword_count: keywordCount,
                    kw_campaign: kwCampaign,
                    negative_count: negativeCount,
                });
            }
        });
    }

    function keywordCountFormatter(cell) {
        const row = cell.getRow().getData();
        const n = Number(row.keyword_count) || 0;
        const campaigns = Array.isArray(row.kw)
            ? row.kw.map(function (c) { return String(c.campaign_name || '').trim(); }).filter(Boolean)
            : [];
        if (!campaigns.length) {
            return '';
        }
        if (!n) {
            return '<span class="text-muted">0</span>';
        }
        return `<a href="#" class="ads-link-kw-open fw-semibold text-info" data-campaign="${escapeHtmlAttr(campaigns[0])}" title="${escapeHtmlAttr(campaigns.join(', '))}">${escapeHtml(String(n))}</a>`;
    }

    function negativeCountFormatter(cell) {
        const row = cell.getRow().getData();
        const n = Number(row.negative_count) || 0;
        const campaigns = Array.isArray(row.kw)
            ? row.kw.map(function (c) { return String(c.campaign_name || '').trim(); }).filter(Boolean)
            : [];
        if (!campaigns.length) {
            return '';
        }
        if (!n) {
            return '<span class="text-muted">0</span>';
        }
        return `<a href="#" class="ads-link-neg-open fw-semibold text-danger" data-campaign="${escapeHtmlAttr(campaigns[0])}" title="${escapeHtmlAttr(campaigns.join(', '))}">${escapeHtml(String(n))}</a>`;
    }

    function fmtKwNum(v, dec) {
        if (v === null || v === undefined || v === '') {
            return '<span class="text-muted">—</span>';
        }
        const n = Number(v);
        if (!isFinite(n)) {
            return '<span class="text-muted">—</span>';
        }
        return dec
            ? n.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec })
            : n.toLocaleString('en-US');
    }

    async function loadKeywordsForCampaign(campaign) {
        const loading = document.getElementById('ads-link-kw-loading');
        const empty = document.getElementById('ads-link-kw-empty');
        const errorEl = document.getElementById('ads-link-kw-error');
        const wrap = document.getElementById('ads-link-kw-table-wrap');
        const tbody = document.getElementById('ads-link-kw-tbody');
        loading?.classList.remove('d-none');
        empty?.classList.add('d-none');
        errorEl?.classList.add('d-none');
        wrap?.classList.add('d-none');
        if (tbody) {
            tbody.innerHTML = '';
        }
        try {
            const url = new URL(campaignLinkKeywordsUrl, window.location.origin);
            url.searchParams.set('campaign', campaign);
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            const json = await res.json();
            if (!json.success) {
                throw new Error(json.message || 'Failed to load keywords.');
            }
            loading?.classList.add('d-none');
            const kws = json.keywords || [];
            if (!kws.length) {
                empty?.classList.remove('d-none');
                document.getElementById('ads-link-kw-title').textContent = campaign;
                return;
            }
            if (tbody) {
                tbody.innerHTML = kws.map(function (k) {
                    return '<tr>'
                        + '<td>' + escapeHtml(k.keyword) + '</td>'
                        + '<td>' + escapeHtml(k.match_type || '—') + '</td>'
                        + '<td class="text-end">' + fmtKwNum(k.impressions) + '</td>'
                        + '<td class="text-end">' + fmtKwNum(k.clicks) + '</td>'
                        + '<td class="text-end">' + fmtKwNum(k.cost, 2) + '</td>'
                        + '<td class="text-end">' + fmtKwNum(k.cpc, 2) + '</td>'
                        + '<td class="text-end">' + fmtKwNum(k.sold) + '</td>'
                        + '<td class="text-end">' + fmtKwNum(k.sales, 2) + '</td>'
                        + '<td class="text-end">' + (k.acos === null || k.acos === undefined
                            ? '<span class="text-muted">—</span>'
                            : (Number(k.acos).toFixed(1) + '%')) + '</td>'
                        + '</tr>';
                }).join('');
            }
            document.getElementById('ads-link-kw-title').textContent = campaign + ' (' + kws.length + ')';
            wrap?.classList.remove('d-none');
        } catch (e) {
            loading?.classList.add('d-none');
            if (errorEl) {
                errorEl.textContent = e.message || 'Failed to load keywords.';
                errorEl.classList.remove('d-none');
            }
        }
    }

    function openKeywordsModal(rowData, preferredCampaign) {
        if (!keywordsModal) {
            return;
        }
        keywordsModalCampaigns = Array.isArray(rowData?.kw)
            ? rowData.kw.map(function (c) { return String(c.campaign_name || '').trim(); }).filter(Boolean)
            : [];
        if (!keywordsModalCampaigns.length && preferredCampaign) {
            keywordsModalCampaigns = [preferredCampaign];
        }
        if (!keywordsModalCampaigns.length) {
            return;
        }

        const selectWrap = document.getElementById('ads-link-kw-campaign-wrap');
        const select = document.getElementById('ads-link-kw-campaign-select');
        if (select && selectWrap) {
            if (keywordsModalCampaigns.length > 1) {
                select.innerHTML = keywordsModalCampaigns.map(function (name) {
                    return `<option value="${escapeHtmlAttr(name)}">${escapeHtml(name)}</option>`;
                }).join('');
                const preferred = preferredCampaign && keywordsModalCampaigns.includes(preferredCampaign)
                    ? preferredCampaign
                    : keywordsModalCampaigns[0];
                select.value = preferred;
                selectWrap.classList.remove('d-none');
            } else {
                select.innerHTML = '';
                selectWrap.classList.add('d-none');
            }
        }

        keywordsModal.show();
        loadKeywordsForCampaign(
            (select && keywordsModalCampaigns.length > 1 ? select.value : null)
            || preferredCampaign
            || keywordsModalCampaigns[0]
        );
    }

    async function loadNegativesForCampaign(campaign) {
        const loading = document.getElementById('ads-link-neg-loading');
        const empty = document.getElementById('ads-link-neg-empty');
        const errorEl = document.getElementById('ads-link-neg-error');
        const wrap = document.getElementById('ads-link-neg-table-wrap');
        const tbody = document.getElementById('ads-link-neg-tbody');
        loading?.classList.remove('d-none');
        empty?.classList.add('d-none');
        errorEl?.classList.add('d-none');
        wrap?.classList.add('d-none');
        if (tbody) {
            tbody.innerHTML = '';
        }
        try {
            const url = new URL(negativeLinkKeywordsUrl, window.location.origin);
            url.searchParams.set('campaign', campaign);
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            const json = await res.json();
            if (!json.success) {
                throw new Error(json.message || 'Failed to load negative keywords.');
            }
            loading?.classList.add('d-none');
            const kws = json.keywords || [];
            if (!kws.length) {
                empty?.classList.remove('d-none');
                document.getElementById('ads-link-neg-title').textContent = campaign;
                return;
            }
            if (tbody) {
                tbody.innerHTML = kws.map(function (k) {
                    return '<tr>'
                        + '<td>' + escapeHtml(k.keyword) + '</td>'
                        + '<td>' + escapeHtml(k.match_type || '—') + '</td>'
                        + '<td>' + escapeHtml(k.level || '—') + '</td>'
                        + '<td>' + escapeHtml(k.state || '—') + '</td>'
                        + '</tr>';
                }).join('');
            }
            document.getElementById('ads-link-neg-title').textContent = campaign + ' (' + kws.length + ')';
            wrap?.classList.remove('d-none');
        } catch (e) {
            loading?.classList.add('d-none');
            if (errorEl) {
                errorEl.textContent = e.message || 'Failed to load negative keywords.';
                errorEl.classList.remove('d-none');
            }
        }
    }

    function openNegativesModal(rowData, preferredCampaign) {
        if (!negativesModal) {
            return;
        }
        negativesModalCampaigns = Array.isArray(rowData?.kw)
            ? rowData.kw.map(function (c) { return String(c.campaign_name || '').trim(); }).filter(Boolean)
            : [];
        if (!negativesModalCampaigns.length && preferredCampaign) {
            negativesModalCampaigns = [preferredCampaign];
        }
        if (!negativesModalCampaigns.length) {
            return;
        }

        const selectWrap = document.getElementById('ads-link-neg-campaign-wrap');
        const select = document.getElementById('ads-link-neg-campaign-select');
        if (select && selectWrap) {
            if (negativesModalCampaigns.length > 1) {
                select.innerHTML = negativesModalCampaigns.map(function (name) {
                    return `<option value="${escapeHtmlAttr(name)}">${escapeHtml(name)}</option>`;
                }).join('');
                const preferred = preferredCampaign && negativesModalCampaigns.includes(preferredCampaign)
                    ? preferredCampaign
                    : negativesModalCampaigns[0];
                select.value = preferred;
                selectWrap.classList.remove('d-none');
            } else {
                select.innerHTML = '';
                selectWrap.classList.add('d-none');
            }
        }

        negativesModal.show();
        loadNegativesForCampaign(
            (select && negativesModalCampaigns.length > 1 ? select.value : null)
            || preferredCampaign
            || negativesModalCampaigns[0]
        );
    }

    function mergeFormatter(cell) {
        const row = cell.getRow().getData();
        const campaigns = Array.isArray(row.kw) ? row.kw : [];
        if (campaigns.length < 2) {
            return '<span class="text-muted small">—</span>';
        }
        return `<button type="button" class="btn btn-sm btn-success ads-link-merge-row-btn py-0 px-2"
            title="Merge keywords across ${campaigns.length} linked Campaign KW campaigns">
            <i class="fas fa-object-group"></i> Merge
        </button>`;
    }

    async function mergeKeywordsForParent(parent, triggerBtn, options) {
        const parentName = String(parent || '').trim();
        const skipConfirm = !!(options && options.skipConfirm);
        if (!parentName) {
            window.alert('Parent is required to merge.');
            return false;
        }
        if (!skipConfirm && !window.confirm(
            'Merge all keywords between linked Campaign KW campaigns for "' + parentName + '"?\n\n'
            + 'Each campaign will receive the union of keywords. Duplicates (same text + match type) are skipped.'
        )) {
            return false;
        }

        const btn = triggerBtn || document.getElementById('ads-link-merge-btn');
        const original = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Merging...';
        }

        try {
            const res = await fetch(campaignMergeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ parent: parentName }),
            });
            const body = await res.json().catch(function () { return {}; });
            if (!res.ok || !body.success) {
                throw new Error(body.message || 'Merge failed.');
            }
            updateParentCampaignRows(parentName, body);
            if (!(options && options.silent)) {
                window.alert(body.message || 'Merge complete.');
            }
            return body;
        } catch (err) {
            window.alert(err.message || 'Merge failed.');
            return false;
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        }
    }

    function getParentsForMerge() {
        const selected = typeof getSelectedRows === 'function' ? getSelectedRows() : [];
        const parents = [];
        const seen = {};
        selected.forEach(function (row) {
            const parent = String(row.getData().parent || '').trim();
            const key = parent.toUpperCase();
            if (!parent || seen[key]) {
                return;
            }
            seen[key] = true;
            parents.push(parent);
        });
        if (parents.length) {
            return parents;
        }
        if (isProductNavigationActive && parentSearchInput?.value) {
            return [String(parentSearchInput.value).trim()].filter(Boolean);
        }
        return [];
    }

    function linkCampaignFromModal(campaignName) {
        const name = String(campaignName || '').trim();
        if (!campaignModalParent || !name) {
            return;
        }
        fetch(campaignLinkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                parent: campaignModalParent,
                type: campaignModalType,
                campaign_name: name,
            }),
        })
            .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
            .then(function (out) {
                if (!out.ok || !out.body?.success) {
                    window.alert(out.body?.message || 'Failed to link campaign.');
                    return;
                }
                updateParentCampaignRows(campaignModalParent, out.body);
                const list = Array.isArray(out.body[campaignModalType === 'PT' ? 'pt' : 'kw'])
                    ? out.body[campaignModalType === 'PT' ? 'pt' : 'kw']
                    : [];
                campaignModalLinkedNames = new Set(
                    list.map(function (c) { return String(c.campaign_name || '').trim().toLowerCase(); }).filter(Boolean)
                );
                loadCampaignSuggestions(document.getElementById('ads-link-campaign-search')?.value || '');
            })
            .catch(function () {
                window.alert('Failed to link campaign.');
            });
    }

    function unlinkCampaignFromRow(rowData, linkId) {
        const parent = String(rowData?.parent || '').trim();
        const id = Number(linkId);
        if (!parent || !id) {
            return;
        }
        fetch(campaignUnlinkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ parent: parent, id: id }),
        })
            .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
            .then(function (out) {
                if (!out.ok || !out.body?.success) {
                    window.alert(out.body?.message || 'Failed to unlink campaign.');
                    return;
                }
                updateParentCampaignRows(parent, out.body);
            })
            .catch(function () {
                window.alert('Failed to unlink campaign.');
            });
    }

    function splFormatter(cell) {
        const value = cell.getValue();
        if (value === null || value === undefined || value === '') {
            return '';
        }
        const num = parseFloat(value);
        if (!isFinite(num)) {
            return '';
        }
        return `<span class="fw-semibold">${num.toFixed(2)}</span>`;
    }

    function openFieldModal(rowData, fieldKey) {
        if (!fieldModal || !rowData?.sku || !LIST_FIELD_LABELS[fieldKey]) {
            return;
        }
        fieldModalSku = String(rowData.sku || '').trim();
        fieldModalField = fieldKey;
        fieldModalItems = parseListField(rowData[fieldKey]);
        document.getElementById('ads-link-field-modal-label').textContent = LIST_FIELD_LABELS[fieldKey];
        document.getElementById('ads-link-field-modal-sku').textContent = fieldModalSku;
        document.getElementById('ads-link-field-input').value = '';
        const statusEl = document.getElementById('ads-link-field-bulk-status');
        if (statusEl) {
            statusEl.textContent = '';
        }
        const fileInput = document.getElementById('ads-link-field-bulk-file');
        if (fileInput) {
            fileInput.value = '';
        }
        renderFieldModalItems();
        fieldModal.show();
        setTimeout(function () {
            document.getElementById('ads-link-field-input')?.focus();
        }, 200);
    }

    function renderFieldModalItems() {
        const wrap = document.getElementById('ads-link-field-items');
        if (!wrap) {
            return;
        }
        if (!fieldModalItems.length) {
            wrap.innerHTML = '<span class="text-muted small">No items yet.</span>';
            return;
        }
        wrap.innerHTML = fieldModalItems.map(function (item) {
            return `<span class="ads-link-selected-chip">
                ${escapeHtml(item)}
                <button type="button" class="ads-link-field-modal-remove" data-item="${escapeHtmlAttr(item)}" title="Remove">&times;</button>
            </span>`;
        }).join('');
    }

    function addFieldModalItem() {
        const input = document.getElementById('ads-link-field-input');
        const value = String(input?.value || '').trim();
        if (!value) {
            return;
        }
        const exists = fieldModalItems.some(function (item) {
            return item.toUpperCase() === value.toUpperCase();
        });
        if (!exists) {
            fieldModalItems.push(value);
            renderFieldModalItems();
        }
        if (input) {
            input.value = '';
            input.focus();
        }
    }

    function downloadFieldTemplate() {
        const fieldLabel = (LIST_FIELD_LABELS[fieldModalField] || 'keywords').replace(/[^\w\-]+/g, '_');
        const skuSlug = String(fieldModalSku || 'sku').replace(/[^\w\-]+/g, '_');
        const lines = [
            'keyword',
            'example keyword 1',
            'example keyword 2',
            'example keyword 3',
        ];
        const blob = new Blob([lines.join('\n') + '\n'], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `ads_link_${fieldLabel}_${skuSlug}_template.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        const statusEl = document.getElementById('ads-link-field-bulk-status');
        if (statusEl) {
            statusEl.textContent = 'Template downloaded.';
        }
    }

    function parseCsvKeywords(text) {
        const lines = String(text || '').split(/\r?\n/);
        const keywords = [];
        const seen = new Set(fieldModalItems.map(function (item) {
            return item.toUpperCase();
        }));

        lines.forEach(function (line, index) {
            let raw = String(line || '').trim();
            if (!raw) {
                return;
            }
            // Support simple CSV with optional quotes / first-column only.
            if (raw.indexOf(',') !== -1) {
                const first = raw.match(/^"([^"]*)"|([^,]*)/);
                raw = first ? (first[1] || first[2] || '').trim() : raw.split(',')[0].trim();
            }
            raw = raw.replace(/^"(.*)"$/, '$1').trim();
            if (!raw) {
                return;
            }
            const lower = raw.toLowerCase();
            if (index === 0 && (lower === 'keyword' || lower === 'keywords' || lower === 'item' || lower === 'items')) {
                return;
            }
            const key = raw.toUpperCase();
            if (seen.has(key)) {
                return;
            }
            seen.add(key);
            keywords.push(raw);
        });

        return keywords;
    }

    function handleFieldBulkUpload(file) {
        const statusEl = document.getElementById('ads-link-field-bulk-status');
        if (!file) {
            return;
        }

        const reader = new FileReader();
        reader.onload = function () {
            const imported = parseCsvKeywords(String(reader.result || ''));
            if (!imported.length) {
                if (statusEl) {
                    statusEl.textContent = 'No new keywords found in file.';
                }
                return;
            }
            fieldModalItems = fieldModalItems.concat(imported);
            renderFieldModalItems();
            if (statusEl) {
                statusEl.textContent = `Added ${imported.length} keyword(s) from CSV. Click Save to apply.`;
            }
        };
        reader.onerror = function () {
            if (statusEl) {
                statusEl.textContent = 'Could not read file.';
            }
        };
        reader.readAsText(file);
    }

    function saveFieldModal() {
        if (!fieldModalSku || !fieldModalField) {
            return;
        }
        const btn = document.getElementById('ads-link-field-save-btn');
        const original = btn?.innerHTML || '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        }

        fetch(saveListFieldUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: fieldModalSku,
                field: fieldModalField,
                items: fieldModalItems,
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (!res.success) {
                throw new Error(res.message || 'Could not save.');
            }
            if (table) {
                table.getRows().forEach(function (row) {
                    const data = row.getData();
                    if (String(data.sku || '').trim().toUpperCase() === fieldModalSku.toUpperCase()) {
                        const patch = {};
                        patch[fieldModalField] = Array.isArray(res.items) ? res.items : fieldModalItems;
                        row.update(patch);
                    }
                });
            }
            fieldModal?.hide();
        })
        .catch(function (err) {
            alert(err.message || 'Could not save.');
        })
        .finally(function () {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        });
    }

    function removeListFieldItem(rowData, fieldKey, item) {
        if (!rowData?.sku || !fieldKey || !item) {
            return;
        }
        const current = parseListField(rowData[fieldKey]).filter(function (value) {
            return value.toUpperCase() !== String(item).trim().toUpperCase();
        });

        fetch(saveListFieldUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: rowData.sku,
                field: fieldKey,
                items: current,
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (!res.success) {
                throw new Error(res.message || 'Could not remove item.');
            }
            const patch = {};
            patch[fieldKey] = Array.isArray(res.items) ? res.items : current;
            if (table) {
                table.getRows().forEach(function (row) {
                    if (String(row.getData().sku || '').trim().toUpperCase() === String(rowData.sku).trim().toUpperCase()) {
                        row.update(patch);
                    }
                });
            }
        })
        .catch(function (err) {
            alert(err.message || 'Could not remove item.');
        });
    }

    function saveSplValue(cell) {
        const row = cell.getRow().getData();
        const field = cell.getField();
        const sku = String(row.sku || '').trim();
        if (!sku || (field !== 'plus_kw_spl' && field !== 'pt_spl')) {
            return;
        }

        let value = cell.getValue();
        if (value === '' || value === null || value === undefined) {
            value = null;
        } else {
            value = parseFloat(value);
            if (!isFinite(value)) {
                alert('Enter a valid number.');
                cell.restoreOldValue();
                return;
            }
        }

        fetch(saveSplFieldUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: sku,
                field: field,
                value: value,
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (!res.success) {
                throw new Error(res.message || 'Could not save.');
            }
            cell.getRow().update({ [field]: res.value });
        })
        .catch(function (err) {
            alert(err.message || 'Could not save.');
            cell.restoreOldValue();
        });
    }

    function applyAffectedLinkedSkuRows(affected) {
        if (!table || !Array.isArray(affected)) {
            return;
        }

        const bySku = {};
        affected.forEach(function (item) {
            if (item?.sku) {
                bySku[item.sku] = item.linked_ads_skus || [];
            }
        });

        table.getRows().forEach(function (row) {
            const data = row.getData();
            if (!Object.prototype.hasOwnProperty.call(bySku, data.sku)) {
                return;
            }
            row.update({ linked_ads_skus: bySku[data.sku] });
        });
    }

    function getSelectedRows() {
        if (!table) {
            return [];
        }
        return table.getSelectedRows();
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
        if (table) {
            table.deselectRow();
        }
        updateBulkEditBadge();
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

    function updateBulkEditBadge() {
        const badge = document.getElementById('ads-link-bulk-edit-badge');
        const countEl = document.getElementById('ads-link-bulk-edit-count');
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

    function resetBulkEditModalFields() {
        const linkInput = document.getElementById('ads-link-bulk-link-sku');
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

        const countEl = document.getElementById('ads-link-bulk-target-count');
        const skusEl = document.getElementById('ads-link-bulk-target-skus');
        const togetherWrap = document.getElementById('ads-link-bulk-link-together-wrap');
        const titleEl = document.getElementById('adsLinkBulkEditModalLabel');

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
        return `<button type="button" class="btn btn-sm btn-outline-primary ads-link-bulk-edit-row-btn py-0 px-2"
            title="${escapeHtmlAttr(title)}" aria-label="${escapeHtmlAttr(title)}">
            <i class="mdi mdi-pencil"></i>
        </button>`;
    }

    function historyFormatter(cell) {
        const row = cell.getRow().getData();
        const count = parseInt(row.history_count, 10) || 0;

        if (count === 0) {
            return '';
        }

        const user = escapeHtml(row.latest_history_by || 'N/A');
        const date = escapeHtml(row.latest_history_at || '—');
        const time = escapeHtml(row.latest_history_time || '');
        const change = row.latest_change || 'View history';
        const stale = !!row.history_stale;
        const alertIcon = stale
            ? '<i class="fas fa-exclamation-triangle text-danger me-1" title="No Ads link update in 15+ days"></i>'
            : '';
        const tip = `${row.latest_history_by || 'N/A'} · ${row.latest_history_at || ''} ${row.latest_history_time || ''} · ${change}`;

        return `<div class="ads-link-history-cell text-center d-flex align-items-center justify-content-center gap-1" title="${escapeHtmlAttr(tip)}">
            ${alertIcon}
            <button type="button" class="btn btn-sm btn-link p-0 ads-link-history-dot"
                title="${escapeHtmlAttr(tip)}" aria-label="View history">
                <span class="ads-link-history-dot-icon" style="font-size:1rem;">●</span>
            </button>
            <span class="small text-muted" style="font-size:10px;white-space:nowrap;">${user}</span>
        </div>`;
    }

    function openHistoryModal(row) {
        if (!historyModal || !row?.sku) {
            return;
        }

        const sku = row.sku || '';
        const parent = row.parent || '';
        document.getElementById('ads-link-history-modal-sku').textContent = sku;

        const loadingEl = document.getElementById('ads-link-history-loading');
        const emptyEl = document.getElementById('ads-link-history-empty');
        const errorEl = document.getElementById('ads-link-history-error');
        const tableWrap = document.getElementById('ads-link-history-table-wrap');
        const tbody = document.getElementById('ads-link-history-tbody');

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
                const actionClass = String(item.action || '').toLowerCase() === 'unlinked' ? 'text-danger' : 'text-success';

                return `<tr>
                    <td>${datePart}</td>
                    <td>${timePart}</td>
                    <td>${escapeHtml(item.updated_by || 'N/A')}</td>
                    <td class="fw-semibold ${actionClass}">${escapeHtml(item.action || '—')}</td>
                    <td>${escapeHtml(item.linked_sku || '—')}</td>
                    <td>${escapeHtml(item.changes || '—')}</td>
                </tr>`;
            }).join('');

            tableWrap.classList.remove('d-none');
        })
        .catch(function () {
            loadingEl.classList.add('d-none');
            errorEl.textContent = 'Could not load Ads link history.';
            errorEl.classList.remove('d-none');
        });
    }

    async function applyBulkEditModal() {
        const linkSkuVal = (document.getElementById('ads-link-bulk-link-sku')?.value || '').trim();
        const targets = bulkEditTargetSkus.slice();
        const applyBtn = document.getElementById('ads-link-bulk-edit-apply-btn');

        if (!targets.length) {
            alert('No rows selected.');
            return;
        }
        if (!linkSkuVal) {
            alert('Fill in a SKU to link.');
            return;
        }

        const original = applyBtn?.innerHTML || '';
        if (applyBtn) {
            applyBtn.disabled = true;
            applyBtn.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Applying...';
        }

        try {
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

            if (failed.length) {
                alert(`Linked ${ok} row(s). Failed: ${failed.join(', ')}`);
            } else {
                alert(`Linked "${linkSkuVal}" to ${ok} row(s).`);
            }
            bulkEditModal?.hide();
            clearBulkSelection();
            reloadTable();
        } catch (err) {
            alert(err.message || 'Could not apply bulk edit.');
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

        const btn = document.getElementById('ads-link-bulk-link-together-btn');
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
        document.getElementById('ads-link-source').textContent = rowData.sku;
        const input = document.getElementById('ads-link-input');
        input.value = '';
        renderLinkedSkuSuggestions('');
        updateLinkedSkuSelectedSummary();
        linkedSkuModal.show();
        setTimeout(function () { input?.focus(); }, 200);
    }

    function updateLinkedSkuSelectedSummary() {
        const wrap = document.getElementById('ads-link-selected-wrap');
        const listEl = document.getElementById('ads-link-selected-skus');
        const countEl = document.getElementById('ads-link-selected-count');
        const saveLabel = document.getElementById('ads-link-save-btn-label');
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
            return `<span class="ads-link-selected-chip">
                ${escapeHtml(sku)}
                <button type="button" class="ads-link-selected-remove" data-sku="${escapeHtmlAttr(sku)}" title="Remove">&times;</button>
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
        const wrap = document.getElementById('ads-link-suggestions');
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
                    (Array.isArray(linkedSkuModalRow?.linked_ads_skus) ? linkedSkuModalRow.linked_ads_skus : [])
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
                    return `<label class="list-group-item list-group-item-action py-2 ads-link-suggestion-item d-flex align-items-center gap-2 mb-0">
                        <input type="checkbox" class="form-check-input ads-link-suggestion-cb"
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
        const inputVal = String(document.getElementById('ads-link-input')?.value || '').trim();
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

        const btn = document.getElementById('ads-link-save-btn');
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

        if (!confirm(`Remove Ads link between "${rowData.sku}" and "${linkedSku}"?`)) {
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

    function reloadTable() {
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

    parentSearchInput?.addEventListener('input', function () {
        if (!isPlaybackUpdating && isProductNavigationActive) {
            stopProductNavigation();
        }
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { reloadTable(); }, 300);
    });

    skuSearchInput?.addEventListener('input', function () {
        if (isProductNavigationActive) {
            stopProductNavigation();
        }
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { reloadTable(); }, 300);
    });

    document.getElementById('ads-link-save-btn')?.addEventListener('click', saveLinkedSkuFromModal);
    document.getElementById('ads-link-input')?.addEventListener('input', function () {
        renderLinkedSkuSuggestions(this.value);
    });
    document.getElementById('ads-link-input')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveLinkedSkuFromModal();
        }
    });
    document.getElementById('ads-link-suggestions')?.addEventListener('change', function (e) {
        if (!e.target.classList.contains('ads-link-suggestion-cb')) {
            return;
        }
        toggleLinkedSkuSuggestionSelection(e.target.value, e.target.checked);
    });
    document.getElementById('ads-link-suggestions')?.addEventListener('click', function (e) {
        const item = e.target.closest('.ads-link-suggestion-item');
        if (!item || e.target.classList.contains('ads-link-suggestion-cb')) {
            return;
        }
        const cb = item.querySelector('.ads-link-suggestion-cb');
        if (!cb) {
            return;
        }
        cb.checked = !cb.checked;
        toggleLinkedSkuSuggestionSelection(cb.value, cb.checked);
    });
    document.getElementById('ads-link-selected-skus')?.addEventListener('click', function (e) {
        const btn = e.target.closest('.ads-link-selected-remove');
        if (!btn) {
            return;
        }
        const sku = btn.dataset.sku || '';
        linkedSkuModalSelectedSkus.delete(sku);
        document.querySelectorAll('.ads-link-suggestion-cb').forEach(function (cb) {
            if (cb.value === sku) {
                cb.checked = false;
            }
        });
        updateLinkedSkuSelectedSummary();
    });

    document.getElementById('ads-link-bulk-edit-btn')?.addEventListener('click', function () {
        openBulkEditModal(getBulkTargetSkus());
    });

    document.getElementById('ads-link-bulk-edit-apply-btn')?.addEventListener('click', function () {
        applyBulkEditModal();
    });

    document.getElementById('ads-link-bulk-link-together-btn')?.addEventListener('click', function () {
        bulkLinkTogetherFromModal();
    });

    bulkEditModalEl?.addEventListener('hidden.bs.modal', function () {
        bulkEditTargetSkus = [];
        resetBulkEditModalFields();
    });

    document.getElementById('ads-link-field-add-item-btn')?.addEventListener('click', addFieldModalItem);
    document.getElementById('ads-link-field-download-template-btn')?.addEventListener('click', downloadFieldTemplate);
    document.getElementById('ads-link-field-bulk-file')?.addEventListener('change', function (e) {
        const file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
        handleFieldBulkUpload(file);
        e.target.value = '';
    });
    document.getElementById('ads-link-field-input')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addFieldModalItem();
        }
    });
    document.getElementById('ads-link-field-items')?.addEventListener('click', function (e) {
        const btn = e.target.closest('.ads-link-field-modal-remove');
        if (!btn) {
            return;
        }
        const item = btn.dataset.item || '';
        fieldModalItems = fieldModalItems.filter(function (value) {
            return value.toUpperCase() !== item.toUpperCase();
        });
        renderFieldModalItems();
    });
    document.getElementById('ads-link-field-save-btn')?.addEventListener('click', saveFieldModal);

    document.getElementById('ads-link-campaign-search')?.addEventListener('input', function () {
        scheduleCampaignSearch(this.value || '');
    });

    document.getElementById('ads-link-campaign-suggestions')?.addEventListener('click', function (e) {
        const btn = e.target.closest('button[data-name]');
        if (!btn || btn.disabled) {
            return;
        }
        linkCampaignFromModal(btn.dataset.name || '');
    });

    document.getElementById('ads-link-kw-campaign-select')?.addEventListener('change', function () {
        if (this.value) {
            loadKeywordsForCampaign(this.value);
        }
    });

    document.getElementById('ads-link-neg-campaign-select')?.addEventListener('change', function () {
        if (this.value) {
            loadNegativesForCampaign(this.value);
        }
    });

    document.getElementById('ads-link-merge-btn')?.addEventListener('click', async function () {
        const parents = getParentsForMerge();
        if (!parents.length) {
            window.alert('Select one or more rows (or navigate to a parent), then click Merge.');
            return;
        }
        if (!window.confirm(
            'Merge keywords for ' + parents.length + ' parent(s)?\n\n'
            + 'Linked Campaign KW campaigns will receive the union of keywords. Duplicates are skipped.'
        )) {
            return;
        }
        const btn = this;
        const messages = [];
        for (let i = 0; i < parents.length; i++) {
            const result = await mergeKeywordsForParent(parents[i], btn, { skipConfirm: true, silent: true });
            if (result && result.message) {
                messages.push(parents[i] + ': ' + result.message);
            }
        }
        if (messages.length) {
            window.alert(messages.join('\n\n'));
        }
    });

    table = new Tabulator('#ads-link-table', {
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
            return `${url}?${query.toString()}`;
        },
        ajaxResponse: function (url, params, response) {
            if (!response.success) {
                throw new Error(response.message || 'Failed to load Ads Link data.');
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
        height: 'calc(100vh - 220px)',
        placeholder: 'No Ads Link data found',
        selectableRows: true,
        selectableRowsPersistence: true,
        columns: [
            {
                formatter: 'rowSelection',
                titleFormatter: 'rowSelection',
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                width: 36,
                minWidth: 36,
                maxWidth: 40,
                widthGrow: 0,
                widthShrink: 0,
            },
            {
                title: 'Image',
                field: 'image',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 48,
                minWidth: 48,
                maxWidth: 56,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: false,
                formatter: imageFormatter,
            },
            {
                title: 'Parent',
                field: 'parent',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 110,
                widthGrow: 0,
            },
            {
                title: 'SKU',
                field: 'sku',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 140,
                widthGrow: 0,
                formatter: skuFormatter,
                cellClick: function (e, cell) {
                    const btn = e.target.closest('.ads-link-copy-sku');
                    if (!btn) {
                        return;
                    }
                    e.preventDefault();
                    e.stopPropagation();
                    copySkuToClipboard(btn.dataset.sku || cell.getRow().getData().sku || '', btn);
                },
            },
            {
                title: 'Sku Link Ads',
                field: 'linked_ads_skus',
                hozAlign: 'center',
                headerHozAlign: 'center',
                minWidth: 160,
                widthGrow: 3,
                headerSort: false,
                cssClass: 'linked-sku-col',
                formatter: linkedAdsSkuFormatter,
                cellClick: function (e, cell) {
                    if (e.target.closest('.ads-link-remove')) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeLinkedSkuFromRow(
                            cell.getRow().getData(),
                            e.target.closest('.ads-link-remove').dataset.linkedSku || ''
                        );
                    }
                },
            },
            {
                title: '+',
                field: 'linked_ads_sku_add',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 36,
                minWidth: 36,
                maxWidth: 40,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: false,
                cssClass: 'linked-sku-add-col',
                formatter: linkedAdsSkuAddFormatter,
                cellClick: function (e, cell) {
                    if (e.target.closest('.ads-link-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        bulkLinkSelectedSkus(
                            cell.getRow().getData(),
                            e.target.closest('.ads-link-add-btn')
                        );
                    }
                },
            },
            {
                title: 'Campaign KW',
                field: 'kw',
                hozAlign: 'center',
                headerHozAlign: 'center',
                minWidth: 160,
                widthGrow: 2,
                headerSort: false,
                cssClass: 'ads-link-campaign-col',
                formatter: campaignFormatter('KW'),
                cellClick: function (e, cell) {
                    const removeBtn = e.target.closest('.ads-link-campaign-remove');
                    if (removeBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        unlinkCampaignFromRow(cell.getRow().getData(), removeBtn.dataset.id || '');
                        return;
                    }
                    if (e.target.closest('.ads-link-campaign-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        openCampaignModal(cell.getRow().getData(), 'KW');
                    }
                },
            },
            {
                title: 'KW',
                field: 'keyword_count',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 72,
                minWidth: 60,
                maxWidth: 90,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: true,
                sorter: 'number',
                formatter: keywordCountFormatter,
                cellClick: function (e, cell) {
                    const link = e.target.closest('.ads-link-kw-open');
                    if (!link) {
                        return;
                    }
                    e.preventDefault();
                    e.stopPropagation();
                    openKeywordsModal(cell.getRow().getData(), link.dataset.campaign || '');
                },
            },
            {
                title: 'KW(-)',
                field: 'negative_count',
                cssClass: 'ads-link-kw-neg-col',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 72,
                minWidth: 60,
                maxWidth: 90,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: true,
                sorter: 'number',
                headerTooltip: 'Amz KW negatives — same count as /google/shopping/missing',
                formatter: negativeCountFormatter,
                cellClick: function (e, cell) {
                    const link = e.target.closest('.ads-link-neg-open');
                    if (!link) {
                        return;
                    }
                    e.preventDefault();
                    e.stopPropagation();
                    openNegativesModal(cell.getRow().getData(), link.dataset.campaign || '');
                },
            },
            {
                title: 'Merge',
                field: 'kw_merge',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 88,
                minWidth: 80,
                maxWidth: 110,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: false,
                headerTooltip: 'Merge keywords across linked Campaign KW campaigns',
                formatter: mergeFormatter,
                cellClick: function (e, cell) {
                    const btn = e.target.closest('.ads-link-merge-row-btn');
                    if (!btn) {
                        return;
                    }
                    e.preventDefault();
                    e.stopPropagation();
                    mergeKeywordsForParent(cell.getRow().getData().parent || '', btn);
                },
            },
            {
                title: 'Campaign PT',
                field: 'pt',
                hozAlign: 'center',
                headerHozAlign: 'center',
                minWidth: 160,
                widthGrow: 2,
                headerSort: false,
                cssClass: 'ads-link-campaign-col',
                formatter: campaignFormatter('PT'),
                cellClick: function (e, cell) {
                    const removeBtn = e.target.closest('.ads-link-campaign-remove');
                    if (removeBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        unlinkCampaignFromRow(cell.getRow().getData(), removeBtn.dataset.id || '');
                        return;
                    }
                    if (e.target.closest('.ads-link-campaign-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        openCampaignModal(cell.getRow().getData(), 'PT');
                    }
                },
            },
            {
                title: '+ KW',
                field: 'plus_kw',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 58,
                minWidth: 52,
                maxWidth: 90,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: false,
                cssClass: 'ads-link-list-col',
                formatter: listFieldFormatter('plus_kw'),
                cellClick: function (e, cell) {
                    const removeBtn = e.target.closest('.ads-link-field-remove');
                    if (removeBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeListFieldItem(cell.getRow().getData(), 'plus_kw', removeBtn.dataset.item || '');
                        return;
                    }
                    if (e.target.closest('.ads-link-field-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        openFieldModal(cell.getRow().getData(), 'plus_kw');
                    }
                },
            },
            {
                title: '(-) KW',
                field: 'minus_kw',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 62,
                minWidth: 56,
                maxWidth: 90,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: false,
                cssClass: 'ads-link-list-col',
                formatter: listFieldFormatter('minus_kw'),
                cellClick: function (e, cell) {
                    const removeBtn = e.target.closest('.ads-link-field-remove');
                    if (removeBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeListFieldItem(cell.getRow().getData(), 'minus_kw', removeBtn.dataset.item || '');
                        return;
                    }
                    if (e.target.closest('.ads-link-field-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        openFieldModal(cell.getRow().getData(), 'minus_kw');
                    }
                },
            },
            {
                title: '+ PT',
                field: 'plus_pt',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 58,
                minWidth: 52,
                maxWidth: 90,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: false,
                cssClass: 'ads-link-list-col',
                formatter: listFieldFormatter('plus_pt'),
                cellClick: function (e, cell) {
                    const removeBtn = e.target.closest('.ads-link-field-remove');
                    if (removeBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeListFieldItem(cell.getRow().getData(), 'plus_pt', removeBtn.dataset.item || '');
                        return;
                    }
                    if (e.target.closest('.ads-link-field-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        openFieldModal(cell.getRow().getData(), 'plus_pt');
                    }
                },
            },
            {
                title: '(-) PT',
                field: 'minus_pt',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 62,
                minWidth: 56,
                maxWidth: 90,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: false,
                cssClass: 'ads-link-list-col',
                formatter: listFieldFormatter('minus_pt'),
                cellClick: function (e, cell) {
                    const removeBtn = e.target.closest('.ads-link-field-remove');
                    if (removeBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeListFieldItem(cell.getRow().getData(), 'minus_pt', removeBtn.dataset.item || '');
                        return;
                    }
                    if (e.target.closest('.ads-link-field-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        openFieldModal(cell.getRow().getData(), 'minus_pt');
                    }
                },
            },
            {
                title: '+ KW SPL',
                field: 'plus_kw_spl',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 78,
                minWidth: 70,
                maxWidth: 100,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: false,
                cssClass: 'ads-link-list-col',
                formatter: listFieldFormatter('plus_kw_spl'),
                cellClick: function (e, cell) {
                    const removeBtn = e.target.closest('.ads-link-field-remove');
                    if (removeBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeListFieldItem(cell.getRow().getData(), 'plus_kw_spl', removeBtn.dataset.item || '');
                        return;
                    }
                    if (e.target.closest('.ads-link-field-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        openFieldModal(cell.getRow().getData(), 'plus_kw_spl');
                    }
                },
            },
            {
                title: 'PT SPL',
                field: 'pt_spl',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 68,
                minWidth: 60,
                maxWidth: 100,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: false,
                cssClass: 'ads-link-list-col',
                formatter: listFieldFormatter('pt_spl'),
                cellClick: function (e, cell) {
                    const removeBtn = e.target.closest('.ads-link-field-remove');
                    if (removeBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeListFieldItem(cell.getRow().getData(), 'pt_spl', removeBtn.dataset.item || '');
                        return;
                    }
                    if (e.target.closest('.ads-link-field-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        openFieldModal(cell.getRow().getData(), 'pt_spl');
                    }
                },
            },
            {
                title: 'SPL (-)KW',
                field: 'spl_minus_kw',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 82,
                minWidth: 74,
                maxWidth: 110,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: false,
                cssClass: 'ads-link-list-col',
                formatter: listFieldFormatter('spl_minus_kw'),
                cellClick: function (e, cell) {
                    const removeBtn = e.target.closest('.ads-link-field-remove');
                    if (removeBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeListFieldItem(cell.getRow().getData(), 'spl_minus_kw', removeBtn.dataset.item || '');
                        return;
                    }
                    if (e.target.closest('.ads-link-field-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        openFieldModal(cell.getRow().getData(), 'spl_minus_kw');
                    }
                },
            },
            {
                title: 'SPL (-)PT',
                field: 'spl_minus_pt',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 82,
                minWidth: 74,
                maxWidth: 110,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: false,
                cssClass: 'ads-link-list-col',
                formatter: listFieldFormatter('spl_minus_pt'),
                cellClick: function (e, cell) {
                    const removeBtn = e.target.closest('.ads-link-field-remove');
                    if (removeBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeListFieldItem(cell.getRow().getData(), 'spl_minus_pt', removeBtn.dataset.item || '');
                        return;
                    }
                    if (e.target.closest('.ads-link-field-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        openFieldModal(cell.getRow().getData(), 'spl_minus_pt');
                    }
                },
            },
            {
                title: 'Edit',
                field: 'bulk_edit',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 44,
                minWidth: 44,
                maxWidth: 50,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: false,
                headerTooltip: 'Bulk edit selected rows',
                formatter: bulkEditFormatter,
                cellClick: function (e, cell) {
                    if (!e.target.closest('.ads-link-bulk-edit-row-btn')) {
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
                width: 72,
                minWidth: 64,
                maxWidth: 90,
                widthGrow: 0,
                widthShrink: 0,
                headerSort: false,
                headerTooltip: 'Ads SKU link history',
                formatter: historyFormatter,
                cellClick: function (e, cell) {
                    if (e.target.closest('.ads-link-history-dot')) {
                        e.preventDefault();
                        e.stopPropagation();
                        openHistoryModal(cell.getRow().getData());
                    }
                },
            },
        ],
    });

    // Keep multi-select intact when clicking action buttons (+, edit, chips, copy, etc.).
    // Tabulator toggles row selection on mousedown; stop that without blocking the button click.
    const adsLinkTableEl = document.getElementById('ads-link-table');
    if (adsLinkTableEl) {
        adsLinkTableEl.addEventListener('mousedown', function (e) {
            if (e.target.closest([
                'button',
                'a',
                'input',
                'label',
                '.btn-close',
                '.ads-link-add-btn',
                '.ads-link-field-add-btn',
                '.ads-link-campaign-add-btn',
                '.ads-link-campaign-remove',
                '.ads-link-campaign-chip',
                '.ads-link-kw-open',
                '.ads-link-neg-open',
                '.ads-link-merge-row-btn',
                '.ads-link-copy-sku',
                '.ads-link-remove',
                '.ads-link-field-remove',
                '.ads-link-history-dot',
                '.ads-link-bulk-edit-row-btn',
                '.linked-sku-badge',
                '.linked-sku-badge-wrap',
            ].join(','))) {
                e.stopPropagation();
            }
        }, true);
    }

    table.on('rowSelectionChanged', function () {
        bulkSelectionSkuCache = new Set(
            getSelectedRows()
                .map(function (row) { return String(row.getData().sku || '').trim(); })
                .filter(Boolean)
        );
        updateBulkEditBadge();
    });

    table.on('pageLoaded', function () {
        restoreVisibleRowSelectionFromCache();
    });

    table.on('dataLoaded', function () {
        restoreVisibleRowSelectionFromCache();
    });
});
</script>
@endsection
