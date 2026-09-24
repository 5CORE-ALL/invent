@extends('layouts.vertical', ['title' => 'QC PKG issues', 'sidenav' => 'condensed'])
{{-- QC PKG issues (Tabulator) — same records and actions as the previous HTML board. --}}

@php
    $importCsvHeaders = [
        'sku', 'qty', 'order_qty', 'parent', 'marketplace_1', 'what_happened', 'action_1',
        'action_1_remark', 'replacement_tracking', 'issue', 'issue_remark', 'c_action_1',
        'c_action_1_remark', 'department',
    ];
    $importCsvSampleRow = [
        'SAMPLE-SKU-001', '5', '2', 'PARENT-001', 'Amz', 'Damaged', 'Cancelled',
        '', 'TRK123', 'Quality Issue', '', 'Fixed', '', 'QC',
    ];
@endphp

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .qc-pkg-page,
        .qc-pkg-page .col-12,
        .qc-pkg-page .card,
        .qc-pkg-page .card-body { min-width: 0; }
        .qc-pkg-page .card,
        .qc-pkg-page .card-body { max-width: 100%; }
        .qc-pkg-page .card { overflow-x: clip; }
        .qc-pkg-page .card-body { overflow-x: visible; }
        #qc-pkg-wrap,
        #qc-history-wrap {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow: visible;
            padding-bottom: 56px;
        }
        #qc-pkg-wrap .tabulator,
        #qc-history-wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 0 0 8px 8px;
            font-size: 13px;
            width: 100% !important;
            max-width: 100%;
            min-width: 0;
            overflow: visible !important;
        }
        #qc-pkg-wrap .tabulator .tabulator-header,
        #qc-history-wrap .tabulator .tabulator-header,
        #qc-pkg-wrap .tabulator .tabulator-tableholder,
        #qc-history-wrap .tabulator .tabulator-tableholder,
        #qc-pkg-wrap .tabulator .tabulator-footer,
        #qc-history-wrap .tabulator .tabulator-footer { max-width: 100%; min-width: 0; }
        #qc-pkg-wrap .tabulator .tabulator-tableholder,
        #qc-history-wrap .tabulator .tabulator-tableholder {
            overflow-x: auto !important;
            overflow-y: visible !important;
            -webkit-overflow-scrolling: touch;
        }
        #qc-pkg-wrap .tabulator .tabulator-header,
        #qc-history-wrap .tabulator .tabulator-header {
            position: sticky !important;
            top: var(--tz-topbar-height, 70px) !important;
            z-index: 24 !important;
            background: #dbeafe;
            border-bottom: 1px solid #dee2e6;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-frozen,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-frozen {
            background-color: #dbeafe !important;
            z-index: 12 !important;
        }
        #qc-pkg-wrap .tabulator-row .tabulator-frozen,
        #qc-history-wrap .tabulator-row .tabulator-frozen {
            background-color: #fff !important;
            z-index: 11 !important;
        }
        #qc-pkg-wrap .tabulator-row.tabulator-selectable:hover .tabulator-frozen,
        #qc-history-wrap .tabulator-row.tabulator-selectable:hover .tabulator-frozen { background-color: #bbb !important; }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable { cursor: pointer; }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter {
            display: flex !important;
            align-items: center;
            visibility: visible !important;
            position: absolute !important;
            top: auto !important;
            bottom: 2px !important;
            left: 50% !important;
            right: auto !important;
            width: auto !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            overflow: visible !important;
            opacity: 0.4;
            transform: translateX(-50%);
            justify-content: center;
        }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter .tabulator-arrow,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-sorter .tabulator-arrow {
            display: inline-block !important;
            visibility: visible !important;
            width: 0 !important;
            height: 0 !important;
            margin: 0 !important;
            border-left: 4px solid transparent !important;
            border-right: 4px solid transparent !important;
            border-bottom: 5px solid #64748b !important;
            border-top: 0 !important;
        }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable[aria-sort="desc"] .tabulator-col-sorter .tabulator-arrow,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable[aria-sort="desc"] .tabulator-col-sorter .tabulator-arrow {
            border-bottom: 0 !important;
            border-top: 5px solid #334155 !important;
        }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable[aria-sort="asc"] .tabulator-col-sorter .tabulator-arrow,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable[aria-sort="asc"] .tabulator-col-sorter .tabulator-arrow {
            border-top: 0 !important;
            border-bottom: 5px solid #334155 !important;
        }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable:hover .tabulator-col-sorter,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable:hover .tabulator-col-sorter,
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col[aria-sort="asc"] .tabulator-col-sorter,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col[aria-sort="asc"] .tabulator-col-sorter,
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col[aria-sort="desc"] .tabulator-col-sorter,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col[aria-sort="desc"] .tabulator-col-sorter { opacity: 1; }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col {
            height: 118px !important;
            min-height: 118px;
            vertical-align: bottom;
            overflow: visible;
            background: #dbeafe !important;
            color: #000 !important;
            padding: 0 !important;
        }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: 118px !important;
            min-height: 118px;
            padding: 0 0 14px !important;
            display: flex !important;
            align-items: flex-end;
            justify-content: center;
            box-sizing: border-box;
        }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap !important;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.15;
            padding: 4px 0;
            text-align: center;
            overflow: visible;
        }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title { padding-right: 0 !important; }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="id"] .tabulator-col-title,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="id"] .tabulator-col-title,
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="issue_ref"] .tabulator-col-title,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="issue_ref"] .tabulator-col-title,
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="image_url"] .tabulator-col-title,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="image_url"] .tabulator-col-title,
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="sku"] .tabulator-col-title,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="sku"] .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            height: auto !important;
            padding: 5px 3px;
        }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="id"] .tabulator-col-content,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="id"] .tabulator-col-content,
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="issue_ref"] .tabulator-col-content,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="issue_ref"] .tabulator-col-content,
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="image_url"] .tabulator-col-content,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="image_url"] .tabulator-col-content,
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="sku"] .tabulator-col-content,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="sku"] .tabulator-col-content {
            align-items: center;
            padding-bottom: 0 !important;
        }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="sku"] .tabulator-col-sorter,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="sku"] .tabulator-col-sorter {
            top: 0 !important;
            bottom: 0 !important;
            left: auto !important;
            right: 4px !important;
            transform: none !important;
        }
        #qc-pkg-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="sku"].tabulator-sortable .tabulator-col-title,
        #qc-history-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="sku"].tabulator-sortable .tabulator-col-title { padding-right: 14px !important; }
        #qc-pkg-wrap .tabulator .tabulator-row,
        #qc-history-wrap .tabulator .tabulator-row { min-height: 32px; }
        #qc-pkg-wrap .tabulator .tabulator-row .tabulator-cell,
        #qc-history-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #qc-pkg-wrap .tabulator .tabulator-footer,
        #qc-history-wrap .tabulator .tabulator-footer {
            display: block !important;
            background: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
            overflow-x: auto;
        }
        #qc-pkg-wrap .tabulator .tabulator-footer .tabulator-paginator,
        #qc-history-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            flex-wrap: wrap;
        }
        #qc-pkg-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page,
        #qc-history-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important;
            font-weight: 500 !important;
            min-width: 36px !important;
            height: 36px !important;
            line-height: 36px !important;
            padding: 0 10px !important;
            border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important;
            background: #fff !important;
            color: #475569 !important;
            cursor: pointer;
            text-align: center !important;
        }
        #qc-pkg-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover,
        #qc-history-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover { background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important; }
        #qc-pkg-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active,
        #qc-history-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important;
            border-color: #4361ee !important;
            color: #fff !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67, 97, 238, 0.3) !important;
        }
        #qc-pkg-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled],
        #qc-history-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] { opacity: 0.4 !important; cursor: not-allowed !important; }
        #qc-pkg-wrap .tabulator .tabulator-footer .tabulator-page-counter,
        #qc-history-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        @media (max-width: 767.98px) {
            #qc-pkg-wrap .tabulator,
            #qc-history-wrap .tabulator { font-size: 12px; }
            #qc-pkg-wrap .tabulator .tabulator-footer,
            #qc-history-wrap .tabulator .tabulator-footer { padding: 8px 10px !important; }
            #qc-pkg-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page,
            #qc-history-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
                min-width: 32px !important;
                height: 32px !important;
                line-height: 32px !important;
                font-size: 13px !important;
            }
            #qc-pkg-wrap .tabulator .tabulator-header,
            #qc-history-wrap .tabulator .tabulator-header { top: var(--tz-topbar-height, 56px) !important; }
        }
        .sku-thumb, .sku-thumb-placeholder, .sku-image-preview {
            width: 36px; height: 36px; object-fit: contain; border-radius: 3px;
            background: #f8f9fa; border: 1px solid #dee2e6;
        }
        .sku-thumb-placeholder { display: inline-flex; align-items: center; justify-content: center; color: #adb5bd; }
        .sku-image-preview { width: 52px; height: 52px; }
        .issue-loss-input { width: 100%; max-width: 5.5rem; font-size: 0.8125rem; padding: 0.15rem 0.3rem; text-align: right; }
        .what-happened-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; background-color: #dc3545; vertical-align: middle; }
        .what-happened-dot-damaged { background-color: #b8860b; }
        .status-dot-indicator { font-size: 14px; line-height: 1; cursor: help; }
        .status-dot-missing { color: #dc3545; }
        .status-dot-available { color: #198754; }
        .copy-tracking-btn, .copy-order-btn { color: #0d6efd; font-size: 0.8rem; line-height: 1; padding: 0 2px; border: none; background: none; cursor: pointer; }
        .copy-tracking-btn { display: none; }
        .tracking-cell { white-space: nowrap; }
        .tracking-dot { font-size: 1.1em; color: #6c757d; }
        .tracking-full { display: none; background: #fff; padding: 0 4px; }
        .tabulator-cell:hover .tracking-dot { display: none; }
        .tabulator-cell:hover .tracking-full, .tabulator-cell:hover .copy-tracking-btn { display: inline; }
        .copy-tracking-btn.copied { color: #198754; }
        .qc-row-btn { border: none; background: transparent; padding: 1px 5px; cursor: pointer; color: #2563eb; }
        .qc-row-btn.qc-danger { color: #dc2626; }
        .created-by-name { line-height: 1.1; }
        .created-by-date { font-size: 11px; color: #6c757d; line-height: 1.1; }
        .created-by-date.is-stale { color: #dc3545; }
        #qc-pkg-table .tabulator-cell:hover:has(.tracking-cell) { overflow: visible; z-index: 30; }
        #qc-issue-modal .modal-dialog { max-height: calc(100vh - 2rem); margin: 1rem auto; }
        #qc-issue-modal .modal-content, #qc-issue-modal #qc-issue-form {
            display: flex; flex-direction: column; max-height: calc(100vh - 2rem); min-height: 0; overflow: hidden;
        }
        #qc-issue-modal .modal-header, #qc-issue-modal .modal-footer { flex-shrink: 0; }
        #qc-issue-modal .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'QC PKG issues',
        'sub_title' => 'Customer Care',
    ])

    <div class="row qc-pkg-page">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-0">Use Add QC &amp; Packing Issue to record SKU issues. SKU lookup auto-fills Parent and available QTY.</p>
                </div>
            </div>

            <div class="card mt-3 shadow-sm">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h4 class="mb-0 me-2">QC And Packing Records</h4>
                        <button type="button" class="btn btn-primary btn-sm" id="qc-add">
                            <i class="bi bi-plus-lg me-1"></i> Add QC &amp; Packing Issue
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="qc-history">
                            <i class="bi bi-clock-history me-1"></i> History
                        </button>
                        <button type="button" class="btn btn-success btn-sm" id="qc-export">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" id="qc-import">
                            <i class="bi bi-upload me-1"></i> Import CSV
                        </button>
                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                                <i class="fa-solid fa-table-columns"></i> Columns
                            </button>
                            <div class="dropdown-menu p-2" id="qc-columns-menu" style="max-height:60vh;overflow-y:auto;min-width:220px;"></div>
                        </div>
                        <span class="badge bg-light text-dark" id="qc-count">0</span>
                    </div>
                    <div id="qc-pkg-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="qc-search" class="form-control" placeholder="Search SKU, tracking, marketplace…" autocomplete="off" aria-label="Search records" maxlength="100">
                        </div>
                        <div id="qc-pkg-table"></div>
                    </div>
                </div>
            </div>

            <div class="card mt-3 d-none" id="qc-history-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Order History</h5>
                    <span class="badge bg-light text-dark" id="qc-history-count">0</span>
                </div>
                <div class="card-body p-0">
                    <div id="qc-history-wrap">
                        <div id="qc-pkg-history"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="importCsvModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-upload me-2"></i>Import CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="importCsvAlert" class="d-none mb-3"></div>
                    <p class="text-muted small mb-2">
                        Upload a CSV file with the following columns (header row required):<br>
                        <code>sku, qty, order_qty, parent, marketplace_1, what_happened, action_1, action_1_remark, replacement_tracking, issue, issue_remark, c_action_1, c_action_1_remark, department</code>
                    </p>
                    <p class="text-muted small mb-3">
                        Required: <strong>sku</strong>, <strong>qty</strong>, <strong>issue</strong> (Root Cause Found).
                        Use multiple departments separated by <strong>|</strong> or <strong>,</strong>. Other columns are optional.
                    </p>
                    <div class="mb-3">
                        <label for="importCsvFile" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="importCsvFile" accept=".csv,.txt">
                    </div>
                    <div id="importCsvProgress" class="d-none">
                        <div class="progress mb-2"><div class="progress-bar progress-bar-striped progress-bar-animated bg-info" style="width:100%"></div></div>
                        <p class="text-muted small text-center">Uploading…</p>
                    </div>
                    <div id="importCsvErrors" class="d-none">
                        <p class="fw-semibold small mb-1 text-warning">Skipped rows:</p>
                        <ul id="importCsvErrorList" class="small text-warning mb-0" style="max-height:160px;overflow-y:auto;"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="importCsvSampleLink" class="btn btn-sm btn-outline-secondary me-auto"><i class="bi bi-download me-1"></i> Download Sample</a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" id="importCsvSubmitBtn"><i class="bi bi-upload me-1"></i> Import</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="qc-issue-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="qc-issue-form" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title" id="qc-issue-modal-title">QC And Packing Issue</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="qc-alert" class="alert alert-danger d-none mb-3" role="alert"></div>
                        <input type="hidden" id="qc-id">
                        <input type="hidden" id="qc-qty" value="0">
                        <input type="hidden" id="qc-parent">
                        <input type="hidden" id="qc-product-master-id">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="qc-sku">SKU <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="qc-sku" list="qc-sku-datalist" placeholder="Search SKU" required autocomplete="off">
                                <datalist id="qc-sku-datalist"></datalist>
                                <div class="mt-1 d-none" id="qc-sku-image-wrap"><img src="" alt="SKU Image" id="qc-sku-image" class="sku-image-preview"></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="qc-order-qty">QTY</label>
                                <input type="number" class="form-control" id="qc-order-qty" min="0" step="1" placeholder="Qty">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="qc-marketplace">MKT</label>
                                <input type="text" class="form-control" id="qc-marketplace" list="qc-marketplace-datalist" placeholder="Select Marketplace">
                                <datalist id="qc-marketplace-datalist">
                                    @foreach ($marketplaces ?? collect() as $marketplace)
                                        <option value="{{ $marketplace }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="qc-what">Issue?</label>
                                <input type="text" class="form-control" id="qc-what" maxlength="100" placeholder="e.g. 0 Stock, Damaged">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="qc-action">Action</label>
                                <input type="text" class="form-control" id="qc-action" list="qc-action-datalist" placeholder="Type or select action..." autocomplete="off">
                                <datalist id="qc-action-datalist">
                                    <option value="Offer Customer Alterntive / Updgrade"></option>
                                    <option value="Upgraded + Stock Alternate"></option>
                                    <option value="Alternate Sent + Stock Alternate"></option>
                                    <option value="Sent Wrong Item + Stock Outgoing"></option>
                                    <option value="Cancelled"></option>
                                    <option value="Refund"></option>
                                    <option value="RTS"></option>
                                    <option value="NA"></option>
                                    <option value="Other"></option>
                                </datalist>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="qc-action-remark">Action Remark <span id="qc-action-remark-star" class="text-danger d-none">*</span></label>
                                <input type="text" class="form-control" id="qc-action-remark" placeholder="Write action remark...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="qc-track-r">Replacement Tracking Number</label>
                                <input type="text" class="form-control" id="qc-track-r" maxlength="50" placeholder="Optional tracking number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="qc-ctn">CTN Pkg</label>
                                <input type="text" class="form-control" id="qc-ctn" maxlength="100" placeholder="CTN packaging instructions (max 100)" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="qc-item-pkg">Instruction PKG</label>
                                <textarea class="form-control" id="qc-item-pkg" rows="2" maxlength="2000" placeholder="Item packaging instructions (max 2000)"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="qc-qe-issue">QC Enhance — Issue</label>
                                <input type="text" class="form-control" id="qc-qe-issue" maxlength="2000" autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="qc-qe-action">QC Enhance — Action Req</label>
                                <input type="text" class="form-control" id="qc-qe-action" maxlength="2000" autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="qc-qe-remark">QC Enhance — Status Remark</label>
                                <input type="text" class="form-control" id="qc-qe-remark" maxlength="2000" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="qc-root">Root Cause Found <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="qc-root" list="qc-root-found-datalist" placeholder="Type or select root cause..." required autocomplete="off">
                                <datalist id="qc-root-found-datalist"></datalist>
                            </div>
                            <div class="col-12 d-none" id="qc-root-remark-wrap">
                                <label class="form-label" for="qc-root-remark">Root Cause Remark <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="qc-root-remark" placeholder="Write remark for Other">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="qc-root-fixed">Root Cause Fixed</label>
                                <input type="text" class="form-control" id="qc-root-fixed" list="qc-root-fixed-datalist" placeholder="Type or select fix..." autocomplete="off">
                                <datalist id="qc-root-fixed-datalist"></datalist>
                            </div>
                            <div class="col-12 d-none" id="qc-root-fixed-remark-wrap">
                                <label class="form-label" for="qc-root-fixed-remark">Root Cause Fixed Remark <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="qc-root-fixed-remark" placeholder="Write remark for Other">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <div class="dropdown">
                                    <button class="form-select text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                                        <span id="qc-dept-label" class="text-truncate text-muted">Select department(s)</span>
                                    </button>
                                    <div class="dropdown-menu p-2 w-100" id="qc-dept-menu" style="max-height:240px;overflow:auto;"></div>
                                </div>
                                <div class="form-text">Click to select one or more departments.</div>
                            </div>
                            <div class="col-12 d-none" id="qc-dept-other-wrap">
                                <label class="form-label" for="qc-dept-other">Other — Responsible Dept Notes <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="qc-dept-other" rows="2" maxlength="255"></textarea>
                                <div class="form-text text-end small"><span id="qc-dept-other-count">0</span> / 255</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="qc-save">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            'use strict';
            const CSRF = @json(csrf_token());
            const CAN_ARCHIVE = @json((auth()->user()?->email ?? '') === 'president@5core.com');
            const URLS = {
                list: @json(route('customer.care.qc.and.packing.issues.index')),
                store: @json(route('customer.care.qc.and.packing.issues.store')),
                updateBase: @json(url('/customer-care/qc-and-packing/issues')),
                skuDetails: @json(route('customer.care.qc.and.packing.sku.details')),
                skuSearch: @json(route('customer.care.followups.skus')),
                history: @json(route('customer.care.qc.and.packing.history.index')),
                dropdownList: @json(route('customer.care.qc.and.packing.dropdown.options.index')),
                import: @json(route('customer.care.qc.and.packing.issues.import')),
                colVisGet: @json(route('tabulator.column.visibility.user.get')),
                colVisSet: @json(route('tabulator.column.visibility.user.set')),
            };
            const COLVIS_CHANNEL = 'qc_and_packing';
            const IMPORT_HEADERS = @json($importCsvHeaders);
            const IMPORT_SAMPLE = @json($importCsvSampleRow);
            const DEPARTMENTS = ['Dispatch','Shipping','Listing','Label','Carrier','Carrier Issue','Customer Care','Pricing','QC','Packaging','Chargeback','Orders on Hold','Other'];
            const DEPT_LABELS = { 'Carrier': 'Carrier Claims', 'Carrier Issue': 'Carrier Scan Issue' };
            const jsonHeaders = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF };
            let table = null, historyTable = null, modal = null, skuTimer = null;

            function escapeHtml(value) { const el = document.createElement('div'); el.textContent = String(value ?? ''); return el.innerHTML; }
            function escAttr(value) { return String(value ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
            function dash(v) { const t = String(v ?? '').trim(); return t === '' ? '—' : escapeHtml(t); }
            function statusIcon(has, tip) {
                const cls = has ? 'status-dot-available' : 'status-dot-missing';
                const t = String(tip ?? '').trim() || (has ? 'Has data' : 'No data');
                return '<i class="bi bi-search status-dot-indicator ' + cls + '" title="' + escAttr(t) + '"></i>';
            }
            function deptValues(row) {
                if (Array.isArray(row && row.departments) && row.departments.length) return row.departments.slice();
                const raw = String((row && row.department) || '').trim();
                if (!raw) return [];
                if (raw.charAt(0) === '[') {
                    try { const j = JSON.parse(raw); if (Array.isArray(j)) return j.map(function (x) { return String(x).trim(); }).filter(Boolean); } catch (e) {}
                }
                return raw.split(/[|,]/).map(function (s) { return s.trim(); }).filter(Boolean);
            }
            function deptLabel(row) { return deptValues(row).join(', '); }
            function shortDateParts(raw) {
                const text = String(raw ?? '').trim();
                const months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
                if (!text) return { label: '', stale: false, raw: '' };
                let day = null, monthIdx = null, year = null, hour = 0, minute = 0;
                let m = text.match(/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{2,4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/);
                if (m) {
                    day = parseInt(m[1], 10); monthIdx = parseInt(m[2], 10) - 1; year = parseInt(m[3], 10);
                    hour = m[4] ? parseInt(m[4], 10) : 0; minute = m[5] ? parseInt(m[5], 10) : 0;
                    if (year < 100) year += 2000;
                } else {
                    m = text.match(/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?/);
                    if (m) {
                        year = parseInt(m[1], 10); monthIdx = parseInt(m[2], 10) - 1; day = parseInt(m[3], 10);
                        hour = m[4] ? parseInt(m[4], 10) : 0; minute = m[5] ? parseInt(m[5], 10) : 0;
                    }
                }
                if (day === null || monthIdx === null || monthIdx < 0 || monthIdx > 11) return { label: text, stale: false, raw: text };
                const dt = new Date(year, monthIdx, day, hour, minute, 0);
                const stale = !Number.isNaN(dt.getTime()) && (Date.now() - dt.getTime()) > 14 * 24 * 60 * 60 * 1000;
                return { label: day + ' ' + months[monthIdx], stale: stale, raw: text };
            }
            function createdByHtml(name, dateRaw) {
                const who = String(name || '').trim() || '—';
                const date = shortDateParts(dateRaw);
                const dateHtml = date.label
                    ? '<div class="created-by-date' + (date.stale ? ' is-stale' : '') + '">' + escapeHtml(date.label) + '</div>'
                    : '';
                const title = [who !== '—' ? who : '', date.raw].filter(Boolean).join(' · ');
                return '<div class="created-by-combo"' + (title ? ' title="' + escAttr(title) + '"' : '') + '>' +
                    '<div class="created-by-name">' + escapeHtml(who) + '</div>' + dateHtml + '</div>';
            }

            const fmtImage = function (cell) {
                const url = cell.getValue();
                return url ? '<img src="' + escAttr(url) + '" class="sku-thumb" alt="">' : '<span class="sku-thumb-placeholder"><i class="bi bi-image"></i></span>';
            };
            const fmtWhat = function (cell) {
                const t = String(cell.getValue() || '').trim();
                if (!t) return '—';
                if (t.toLowerCase() === '0 stock') return '<span class="what-happened-dot" title="0 Stock"></span>';
                if (t.toLowerCase() === 'damaged') return '<span class="what-happened-dot what-happened-dot-damaged" title="Damaged"></span>';
                return escapeHtml(t);
            };
            const fmtAction = function (cell) {
                const d = cell.getData();
                const a = String(d.action_1 || '').trim();
                const r = String(d.action_1_remark || '').trim();
                if (!a) return r ? escapeHtml(r) : '—';
                return r ? escapeHtml(a + ': ' + r) : escapeHtml(a);
            };
            const fmtTracking = function (cell) {
                const t = String(cell.getValue() || '').trim();
                if (!t) return '—';
                return '<span class="tracking-cell"><span class="tracking-dot">•</span><span class="tracking-full">' + escapeHtml(t) + '</span>' +
                    '<button type="button" class="copy-tracking-btn" data-copy="' + escAttr(t) + '" title="Copy tracking"><i class="bi bi-clipboard"></i></button></span>';
            };
            const fmtRoot = function (cell) {
                const d = cell.getData();
                const root = String(d.issue || '').trim();
                const rmk = String(d.issue_remark || '').trim();
                const tip = (!root && !rmk) ? 'No data' : (!root ? rmk : (rmk ? root + ': ' + rmk : root));
                return statusIcon(!!(root || rmk), tip);
            };
            const fmtRootFixed = function (cell) {
                const d = cell.getData();
                const fx = String(d.c_action_1 || '').trim();
                const rmk = String(d.c_action_1_remark || '').trim();
                const tip = (!fx && !rmk) ? 'No data' : (!fx ? rmk : (rmk ? fx + ': ' + rmk : fx));
                return statusIcon(!!(fx || rmk), tip);
            };
            const fmtCtn = function (cell) {
                const d = cell.getData();
                const instr = String(d.ctn_instructions || '').trim();
                const tip = !d.product_master_id ? 'No matching product_master row' : (instr || 'No instructions');
                return statusIcon(!!(d.product_master_id && instr), tip);
            };
            const fmtItemPkg = function (cell) {
                const raw = String(cell.getData().instructions_item_pkg || '').trim();
                return statusIcon(raw !== '', raw || 'No instructions available');
            };
            const fmtQcEnhance = function (cell) {
                const d = cell.getData();
                const parts = [];
                if (String(d.qc_enhance_issue || '').trim()) parts.push('Issue: ' + String(d.qc_enhance_issue).trim());
                if (String(d.qc_enhance_action_req || '').trim()) parts.push('Action Req: ' + String(d.qc_enhance_action_req).trim());
                if (String(d.qc_enhance_status_remark || '').trim()) parts.push('Status Remark: ' + String(d.qc_enhance_status_remark).trim());
                const tip = parts.join('\n');
                return statusIcon(tip !== '', tip || 'No QC Enhance data');
            };
            const fmtLoss = function (cell) {
                const d = cell.getData();
                const n = d.total_loss;
                const v = (n != null && n !== '' && !isNaN(parseFloat(n))) ? String(Math.round(parseFloat(n))) : '';
                return '<input type="number" step="0.01" class="form-control form-control-sm issue-loss-input" value="' + escAttr(v) +
                    '" data-original="' + escAttr(v) + '" data-issue-id="' + escAttr(String(d.id)) + '" inputmode="decimal" autocomplete="off" aria-label="Loss $">';
            };
            const fmtActions = function () {
                let html = '<button type="button" class="qc-row-btn qc-edit" title="Edit"><i class="bi bi-pencil-fill"></i></button>';
                if (CAN_ARCHIVE) html += '<button type="button" class="qc-row-btn qc-danger qc-archive" title="Archive"><i class="bi bi-archive-fill"></i></button>';
                return html;
            };
            const fmtDept = function (cell) { const label = deptLabel(cell.getData()); return label ? escapeHtml(label) : '—'; };
            const fmtCreatedMain = function (cell) { const d = cell.getData(); return createdByHtml(d.created_by, d.created_at); };
            const fmtCreatedHistory = function (cell) { const d = cell.getData(); return createdByHtml(d.created_by, d.logged_at); };

            function gridOptions(columns, extra) {
                return Object.assign({
                    columns: columns,
                    height: false,
                    layout: 'fitDataFill',
                    layoutColumnsOnNewData: true,
                    pagination: true,
                    paginationMode: 'local',
                    paginationSize: 100,
                    paginationSizeSelector: [25, 50, 100, 250, 500, 1000],
                    paginationCounter: 'rows',
                    paginationButtonCount: 10,
                    paginationInitialPage: 1,
                    headerSortClickElement: 'icon',
                    columnDefaults: { tooltip: true },
                    index: 'id',
                }, extra || {});
            }
            function dataColumns(main) {
                const cols = [
                    { title: '#', field: main ? 'id' : 'issue_ref', width: main ? 55 : 70, frozen: true, formatter: main ? undefined : function (c) { return dash(c.getValue() || c.getData().orders_on_hold_issue_id || c.getData().id); } },
                    { title: '', field: 'image_url', width: 56, frozen: true, formatter: fmtImage, headerSort: false },
                    { title: 'SKU', field: 'sku', width: 140, frozen: true, formatter: function (c) { return '<span title="' + escAttr(c.getValue()) + '">' + escapeHtml(c.getValue()) + '</span>'; } },
                ];
                if (main) cols.push({ title: 'Loss $', field: 'total_loss', width: 90, formatter: fmtLoss, headerSort: false, tooltip: false });
                cols.push(
                    { title: 'QTY', field: 'order_qty', width: 60, formatter: function (c) { return dash(c.getValue()); } },
                    { title: 'MKT', field: 'marketplace_1', width: 90, formatter: function (c) { return dash(c.getValue()); } },
                    { title: 'Issue?', field: 'what_happened', width: 70, formatter: fmtWhat, hozAlign: 'center' },
                    { title: 'Action', field: 'action_1', width: 140, formatter: fmtAction },
                    { title: 'Track R', field: 'replacement_tracking', width: 80, formatter: fmtTracking, hozAlign: 'center' },
                    { title: 'Root Cause Found', field: 'issue', width: 70, formatter: fmtRoot, hozAlign: 'center' },
                    { title: 'CTN Pkg', field: 'ctn_instructions', width: 60, formatter: fmtCtn, headerSort: false, hozAlign: 'center' },
                    { title: 'item pkg', field: 'instructions_item_pkg', width: 60, formatter: fmtItemPkg, headerSort: false, hozAlign: 'center' },
                    { title: 'QC Enhance', field: 'qc_enhance_issue', width: 60, formatter: fmtQcEnhance, headerSort: false, hozAlign: 'center' },
                    { title: 'Root Cause Fixed', field: 'c_action_1', width: 70, formatter: fmtRootFixed, hozAlign: 'center' },
                    { title: 'Dept', field: 'department', width: 100, formatter: fmtDept }
                );
                if (main) {
                    cols.push({ title: 'Close', field: '_actions', width: 70, formatter: fmtActions, headerSort: false, hozAlign: 'center' });
                    cols.push({ title: 'Created By', field: 'created_by', width: 110, formatter: fmtCreatedMain, headerSort: false });
                } else {
                    cols.push({ title: 'Close', field: 'close_note', width: 90, formatter: function (c) { return dash(c.getValue()); } });
                    cols.push({ title: 'Event', field: 'event_type', width: 90, formatter: function (c) { return dash(c.getValue()); } });
                    cols.push({ title: 'Created By', field: 'created_by', width: 110, formatter: fmtCreatedHistory, headerSort: false });
                }
                return cols;
            }

            function showAlert(message) { const box = document.getElementById('qc-alert'); box.className = 'alert alert-danger mb-3'; box.textContent = message; }
            function hideAlert() { const box = document.getElementById('qc-alert'); box.className = 'alert alert-danger d-none mb-3'; box.textContent = ''; }
            function selectedDepartments() { return Array.from(document.querySelectorAll('#qc-dept-menu input:checked')).map(function (el) { return el.value; }); }
            function refreshDeptLabel() {
                const selected = selectedDepartments();
                const label = document.getElementById('qc-dept-label');
                const isOther = selected.indexOf('Other') !== -1;
                if (!selected.length) { label.textContent = 'Select department(s)'; label.classList.add('text-muted'); }
                else { label.textContent = selected.map(function (v) { return DEPT_LABELS[v] || v; }).join(', '); label.classList.remove('text-muted'); }
                document.getElementById('qc-dept-other-wrap').classList.toggle('d-none', !isOther);
                if (!isOther) { document.getElementById('qc-dept-other').value = ''; document.getElementById('qc-dept-other-count').textContent = '0'; }
            }
            function setDepartments(values) {
                const wanted = values || [];
                document.querySelectorAll('#qc-dept-menu input').forEach(function (el) { el.checked = wanted.indexOf(el.value) !== -1; });
                refreshDeptLabel();
            }
            function buildDeptMenu() {
                const menu = document.getElementById('qc-dept-menu');
                menu.innerHTML = DEPARTMENTS.map(function (value) {
                    return '<label class="d-flex align-items-center gap-2 py-1 px-1 mb-0"><input type="checkbox" value="' + escAttr(value) + '"> <span>' + escapeHtml(DEPT_LABELS[value] || value) + '</span></label>';
                }).join('');
                menu.addEventListener('change', refreshDeptLabel);
            }
            function toggleOtherFields() {
                const rootOther = document.getElementById('qc-root').value.trim() === 'Other';
                document.getElementById('qc-root-remark-wrap').classList.toggle('d-none', !rootOther);
                if (!rootOther) document.getElementById('qc-root-remark').value = '';
                document.getElementById('qc-action-remark-star').classList.toggle('d-none', document.getElementById('qc-action').value.trim() !== 'Other');
                const fixedOther = document.getElementById('qc-root-fixed').value.trim() === 'Other';
                document.getElementById('qc-root-fixed-remark-wrap').classList.toggle('d-none', !fixedOther);
                if (!fixedOther) document.getElementById('qc-root-fixed-remark').value = '';
            }
            function resetForm() {
                document.getElementById('qc-issue-form').reset();
                document.getElementById('qc-id').value = '';
                document.getElementById('qc-qty').value = '0';
                document.getElementById('qc-parent').value = '';
                document.getElementById('qc-product-master-id').value = '';
                document.getElementById('qc-sku-image-wrap').classList.add('d-none');
                document.getElementById('qc-save').textContent = 'Save';
                setDepartments([]);
                toggleOtherFields();
                hideAlert();
            }
            function fillForm(record) {
                resetForm();
                document.getElementById('qc-id').value = record.id || '';
                document.getElementById('qc-sku').value = record.sku || '';
                document.getElementById('qc-qty').value = record.qty ?? 0;
                document.getElementById('qc-order-qty').value = record.order_qty ?? '';
                document.getElementById('qc-parent').value = record.parent || '';
                document.getElementById('qc-marketplace').value = record.marketplace_1 || '';
                document.getElementById('qc-what').value = record.what_happened || '';
                document.getElementById('qc-action').value = record.action_1 || '';
                document.getElementById('qc-action-remark').value = record.action_1_remark || '';
                document.getElementById('qc-track-r').value = record.replacement_tracking || '';
                document.getElementById('qc-ctn').value = record.ctn_instructions || '';
                document.getElementById('qc-item-pkg').value = record.instructions_item_pkg || '';
                document.getElementById('qc-qe-issue').value = record.qc_enhance_issue || '';
                document.getElementById('qc-qe-action').value = record.qc_enhance_action_req || '';
                document.getElementById('qc-qe-remark').value = record.qc_enhance_status_remark || '';
                document.getElementById('qc-product-master-id').value = record.product_master_id || '';
                document.getElementById('qc-root').value = record.issue || '';
                document.getElementById('qc-root-remark').value = record.issue_remark || '';
                document.getElementById('qc-root-fixed').value = record.c_action_1 || '';
                document.getElementById('qc-root-fixed-remark').value = record.c_action_1_remark || '';
                setDepartments(deptValues(record));
                document.getElementById('qc-save').textContent = 'Update';
                toggleOtherFields();
                if (record.image_url) {
                    document.getElementById('qc-sku-image').src = record.image_url;
                    document.getElementById('qc-sku-image-wrap').classList.remove('d-none');
                } else {
                    showSkuImage(record.sku || '');
                }
            }

            async function refreshSkuSuggestions(query) {
                const list = document.getElementById('qc-sku-datalist');
                const q = String(query || '').trim();
                if (q.length < 1) { list.innerHTML = ''; return; }
                try {
                    const res = await fetch(URLS.skuSearch + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json();
                    const skus = Array.isArray(data && data.skus) ? data.skus : [];
                    list.innerHTML = skus.map(function (item) {
                        const sku = item && item.sku ? item.sku : '';
                        const parent = item && item.parent ? item.parent : '';
                        return '<option value="' + escAttr(sku) + '" label="' + escAttr(parent ? parent + ' · ' + sku : sku) + '"></option>';
                    }).join('');
                } catch (e) { list.innerHTML = ''; }
            }
            async function fillSkuDetails() {
                const sku = document.getElementById('qc-sku').value.trim();
                ['qc-qty','qc-parent','qc-product-master-id','qc-ctn','qc-item-pkg','qc-qe-issue','qc-qe-action','qc-qe-remark'].forEach(function (id) { document.getElementById(id).value = ''; });
                document.getElementById('qc-sku-image-wrap').classList.add('d-none');
                if (!sku) return;
                try {
                    const res = await fetch(URLS.skuDetails + '?sku=' + encodeURIComponent(sku), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json();
                    if (!res.ok || !data.found) return;
                    document.getElementById('qc-qty').value = data.qty ?? 0;
                    document.getElementById('qc-parent').value = data.parent || '';
                    document.getElementById('qc-product-master-id').value = data.product_master_id || '';
                    document.getElementById('qc-ctn').value = data.ctn_instructions || '';
                    document.getElementById('qc-item-pkg').value = data.instructions_item_pkg || '';
                    document.getElementById('qc-qe-issue').value = data.qc_enhance_issue || '';
                    document.getElementById('qc-qe-action').value = data.qc_enhance_action_req || '';
                    document.getElementById('qc-qe-remark').value = data.qc_enhance_status_remark || '';
                    if (data.image_url) { document.getElementById('qc-sku-image').src = data.image_url; document.getElementById('qc-sku-image-wrap').classList.remove('d-none'); }
                } catch (e) {}
            }
            async function savePackagingFields() {
                const sku = document.getElementById('qc-sku').value.trim();
                const productId = parseInt(document.getElementById('qc-product-master-id').value, 10);
                try {
                    if (sku && !Number.isNaN(productId) && productId > 0) {
                        await fetch('/dim-wt-master/update', { method: 'POST', headers: jsonHeaders, body: JSON.stringify({ product_id: productId, sku: sku, parent: document.getElementById('qc-parent').value.trim(), ctn_instructions: document.getElementById('qc-ctn').value.trim().slice(0, 100) || null }) });
                        await fetch('/instructions-item-pkg/update', { method: 'POST', headers: jsonHeaders, body: JSON.stringify({ product_id: productId, sku: sku, instructions: document.getElementById('qc-item-pkg').value.trim().slice(0, 2000) }) });
                    }
                    if (sku) {
                        await fetch('/quality-enhance/upsert-by-sku', { method: 'POST', headers: jsonHeaders, body: JSON.stringify({ sku: sku, issue: document.getElementById('qc-qe-issue').value.trim().slice(0, 2000), action_req: document.getElementById('qc-qe-action').value.trim().slice(0, 2000), status_remark: document.getElementById('qc-qe-remark').value.trim().slice(0, 2000) }) });
                    }
                } catch (e) { alert('Issue saved, but CTN PKG, item packaging, or QC Enhance could not be updated.'); }
            }
            async function loadRows() {
                const res = await fetch(URLS.list, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                const rows = Array.isArray(data && data.data) ? data.data : [];
                rows.sort(function (a, b) {
                    const ta = Date.parse(String(a.created_at || '').replace(' ', 'T')) || 0;
                    const tb = Date.parse(String(b.created_at || '').replace(' ', 'T')) || 0;
                    if (tb !== ta) return tb - ta;
                    return (Number(b.id) || 0) - (Number(a.id) || 0);
                });
                await table.setData(rows);
                document.getElementById('qc-count').textContent = String(table.getDataCount('active'));
            }
            async function loadHistory() {
                const res = await fetch(URLS.history, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                const rows = Array.isArray(data && data.data) ? data.data : [];
                if (!historyTable) {
                    historyTable = new Tabulator('#qc-pkg-history', gridOptions(dataColumns(false), {
                        placeholder: 'No history found.',
                        data: rows,
                    }));
                } else await historyTable.setData(rows);
                document.getElementById('qc-history-count').textContent = String(rows.length);
            }
            async function archiveRow(id) {
                if (!id || !confirm('Archive this record?')) return;
                const res = await fetch(URLS.updateBase + '/' + encodeURIComponent(id) + '/archive', { method: 'POST', headers: jsonHeaders, body: '{}' });
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok) { alert(data.message || 'Unable to archive record.'); return; }
                await loadRows();
                if (historyTable) await loadHistory();
            }
            function parseLoss(raw) {
                const t = String(raw ?? '').trim().replace(/[$,]/g, '');
                if (t === '') return null;
                const n = parseFloat(t);
                return isNaN(n) ? null : Math.round(n * 100) / 100;
            }
            async function saveLoss(input) {
                const id = input.getAttribute('data-issue-id');
                if (!id) return;
                const parsed = parseLoss(input.value);
                const display = parsed == null ? '' : String(Math.round(parsed));
                const original = input.getAttribute('data-original') || '';
                if (display === original) { input.value = original; return; }
                try {
                    const res = await fetch(URLS.updateBase + '/' + encodeURIComponent(id) + '/total-loss', { method: 'PATCH', headers: jsonHeaders, body: JSON.stringify({ total_loss: parsed }) });
                    const data = await res.json().catch(function () { return {}; });
                    if (!res.ok) throw new Error(data.message || 'Save failed');
                    const row = table.getRows().find(function (r) { return String(r.getData().id) === String(id); });
                    if (row) row.update({ total_loss: data.total_loss != null ? data.total_loss : null });
                } catch (e) { alert(e.message || 'Could not save Loss $'); input.value = original; }
            }
            function csvEscape(val) { const str = String(val ?? '').replace(/"/g, '""'); return /[",\n\r]/.test(str) ? '"' + str + '"' : str; }
            function downloadCsv(content, filename) {
                const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url; a.download = filename; document.body.appendChild(a); a.click(); document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
            async function loadDropdown(fieldType, datalistId) {
                try {
                    const res = await fetch(URLS.dropdownList + '?field_type=' + encodeURIComponent(fieldType), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json();
                    const opts = Array.isArray(data && data.data) ? data.data : [];
                    document.getElementById(datalistId).innerHTML = opts.map(function (o) { return '<option value="' + escAttr(o) + '"></option>'; }).join('');
                } catch (e) {}
            }
            function departmentPayload(depts) {
                if (!depts.length) return '';
                if (depts.length === 1) return depts[0];
                return depts.join(' | ');
            }
            async function showSkuImage(sku) {
                const wrap = document.getElementById('qc-sku-image-wrap');
                const img = document.getElementById('qc-sku-image');
                if (!sku) { wrap.classList.add('d-none'); return; }
                try {
                    const res = await fetch(URLS.skuDetails + '?sku=' + encodeURIComponent(sku), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json();
                    if (!res.ok || !data.found || !data.image_url) { wrap.classList.add('d-none'); return; }
                    img.src = data.image_url;
                    wrap.classList.remove('d-none');
                    if (data.product_master_id) document.getElementById('qc-product-master-id').value = data.product_master_id;
                } catch (e) { wrap.classList.add('d-none'); }
            }

            document.addEventListener('DOMContentLoaded', function () {
                buildDeptMenu();
                table = new Tabulator('#qc-pkg-table', gridOptions(dataColumns(true), {
                    placeholder: 'No records found.',
                }));
                modal = (window.bootstrap && bootstrap.Modal)
                    ? bootstrap.Modal.getOrCreateInstance(document.getElementById('qc-issue-modal'))
                    : null;
                table.on('tableBuilt', async function () {
                    try {
                        const res = await fetch(URLS.colVisGet + '?channel=' + encodeURIComponent(COLVIS_CHANNEL), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                        const map = await res.json();
                        if (map && typeof map === 'object') {
                            table.getColumns().forEach(function (col) {
                                const f = col.getField();
                                if (f && (f in map)) { if (map[f]) col.show(); else col.hide(); }
                            });
                        }
                    } catch (e) {}
                    const menu = document.getElementById('qc-columns-menu');
                    const labels = { image_url: 'Image', ctn_instructions: 'CTN Pkg', instructions_item_pkg: 'item pkg', qc_enhance_issue: 'QC Enhance', _actions: 'Close' };
                    menu.innerHTML = '';
                    table.getColumns().forEach(function (col) {
                        const field = col.getField();
                        if (!field) return;
                        const wrap = document.createElement('div');
                        wrap.className = 'form-check';
                        wrap.innerHTML = '<input class="form-check-input" type="checkbox" ' + (col.isVisible() ? 'checked' : '') + '><label class="form-check-label">' + escapeHtml(labels[field] || col.getDefinition().title || field) + '</label>';
                        wrap.querySelector('input').addEventListener('change', function () {
                            if (this.checked) col.show(); else col.hide();
                            const visibility = {};
                            table.getColumns().forEach(function (c) { if (c.getField()) visibility[c.getField()] = c.isVisible(); });
                            fetch(URLS.colVisSet, { method: 'POST', headers: jsonHeaders, body: JSON.stringify({ channel: COLVIS_CHANNEL, visibility: visibility }) });
                        });
                        menu.appendChild(wrap);
                    });
                    loadRows().catch(function (e) { console.error(e); });
                    loadDropdown('root_cause_found', 'qc-root-found-datalist');
                    loadDropdown('root_cause_fixed', 'qc-root-fixed-datalist');
                });
                table.on('cellClick', function (e, cell) {
                    const copyBtn = e.target.closest('.copy-tracking-btn');
                    if (copyBtn) {
                        const text = copyBtn.getAttribute('data-copy') || '';
                        if (navigator.clipboard) navigator.clipboard.writeText(text).then(function () {
                            copyBtn.classList.add('copied');
                            copyBtn.innerHTML = '<i class="bi bi-clipboard-check"></i>';
                            setTimeout(function () { copyBtn.classList.remove('copied'); copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1200);
                        });
                        return;
                    }
                    if (e.target.closest('.qc-edit')) { fillForm(cell.getRow().getData()); modal.show(); }
                    else if (e.target.closest('.qc-archive')) archiveRow(cell.getRow().getData().id);
                });
                document.getElementById('qc-pkg-table').addEventListener('focusout', function (e) { const inp = e.target.closest('.issue-loss-input'); if (inp) saveLoss(inp); });
                document.getElementById('qc-pkg-table').addEventListener('keydown', function (e) { const inp = e.target.closest('.issue-loss-input'); if (inp && e.key === 'Enter') { e.preventDefault(); inp.blur(); } });
                document.getElementById('qc-add').addEventListener('click', function () { resetForm(); modal.show(); });
                document.getElementById('qc-history').addEventListener('click', function () {
                    const card = document.getElementById('qc-history-card');
                    card.classList.remove('d-none');
                    loadHistory().then(function () { card.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
                });
                document.getElementById('qc-search').addEventListener('input', function () {
                    const term = this.value.trim().toLowerCase();
                    if (!term) { table.clearFilter(); document.getElementById('qc-count').textContent = String(table.getDataCount('active')); return; }
                    table.setFilter(function (row) {
                        const hay = [row.id, row.sku, row.parent, row.order_qty, row.replacement_tracking, row.what_happened, row.action_1, row.action_1_remark, row.issue, row.issue_remark, row.c_action_1, row.c_action_1_remark, row.marketplace_1, deptLabel(row), row.total_loss, row.created_by].map(function (v) { return String(v ?? '').toLowerCase(); }).join(' | ');
                        return hay.includes(term);
                    });
                    document.getElementById('qc-count').textContent = String(table.getDataCount('active'));
                });
                const skuInput = document.getElementById('qc-sku');
                skuInput.addEventListener('input', function () { clearTimeout(skuTimer); skuTimer = setTimeout(function () { refreshSkuSuggestions(skuInput.value); }, 220); });
                skuInput.addEventListener('change', fillSkuDetails);
                skuInput.addEventListener('blur', fillSkuDetails);
                document.getElementById('qc-root').addEventListener('input', toggleOtherFields);
                document.getElementById('qc-action').addEventListener('input', toggleOtherFields);
                document.getElementById('qc-root-fixed').addEventListener('input', toggleOtherFields);
                document.getElementById('qc-dept-other').addEventListener('input', function () { document.getElementById('qc-dept-other-count').textContent = String(this.value.length); });
                document.getElementById('qc-issue-modal').addEventListener('hidden.bs.modal', resetForm);
                document.getElementById('qc-issue-form').addEventListener('submit', async function (event) {
                    event.preventDefault();
                    hideAlert();
                    const sku = document.getElementById('qc-sku').value.trim();
                    const issue = document.getElementById('qc-root').value.trim();
                    const depts = selectedDepartments();
                    if (!sku) { showAlert('SKU is required.'); return; }
                    if (!issue) { showAlert('Root Cause Found is required.'); return; }
                    if (document.getElementById('qc-action').value.trim() === 'Other' && document.getElementById('qc-action-remark').value.trim() === '') { showAlert('Please enter Action remark when Action is Other.'); return; }
                    if (issue === 'Other' && document.getElementById('qc-root-remark').value.trim() === '') { showAlert('Please enter Root Cause remark for Other.'); return; }
                    if (document.getElementById('qc-root-fixed').value.trim() === 'Other' && document.getElementById('qc-root-fixed-remark').value.trim() === '') { showAlert('Please enter Root Cause Fixed remark for Other.'); return; }
                    if (depts.indexOf('Other') !== -1 && document.getElementById('qc-dept-other').value.trim() === '') { showAlert('Please enter Responsible Dept notes when Other is selected.'); return; }
                    if (departmentPayload(depts).length > 100) { showAlert('Too many departments selected. Keep the department list under 100 characters.'); return; }
                    const orderQtyRaw = document.getElementById('qc-order-qty').value;
                    const payload = {
                        sku: sku,
                        qty: document.getElementById('qc-qty').value === '' ? 0 : Number(document.getElementById('qc-qty').value),
                        order_qty: orderQtyRaw === '' ? null : Number(orderQtyRaw),
                        parent: document.getElementById('qc-parent').value.trim(),
                        marketplace_1: document.getElementById('qc-marketplace').value.trim(),
                        what_happened: document.getElementById('qc-what').value.trim(),
                        issue: issue,
                        issue_remark: document.getElementById('qc-root-remark').value.trim(),
                        action_1: document.getElementById('qc-action').value.trim(),
                        action_1_remark: document.getElementById('qc-action-remark').value.trim(),
                        replacement_tracking: document.getElementById('qc-track-r').value.trim(),
                        c_action_1: document.getElementById('qc-root-fixed').value.trim(),
                        c_action_1_remark: document.getElementById('qc-root-fixed-remark').value.trim(),
                        department: departmentPayload(depts),
                    };
                    const editId = document.getElementById('qc-id').value;
                    const saveBtn = document.getElementById('qc-save');
                    saveBtn.disabled = true;
                    saveBtn.textContent = 'Saving...';
                    try {
                        const res = await fetch(editId ? (URLS.updateBase + '/' + encodeURIComponent(editId)) : URLS.store, { method: editId ? 'PUT' : 'POST', headers: jsonHeaders, body: JSON.stringify(payload) });
                        const data = await res.json().catch(function () { return {}; });
                        if (!res.ok) {
                            const errors = data.errors || {};
                            const firstKey = Object.keys(errors)[0];
                            showAlert((firstKey && errors[firstKey] && errors[firstKey][0]) || data.message || 'Unable to save hold issue.');
                            return;
                        }
                        await savePackagingFields();
                        await loadRows();
                        if (historyTable) await loadHistory();
                        modal.hide();
                    } catch (e) {
                        showAlert('Unable to save hold issue. Please try again.');
                    } finally {
                        saveBtn.disabled = false;
                        saveBtn.textContent = editId ? 'Update' : 'Save';
                    }
                });
                document.getElementById('qc-export').addEventListener('click', function () {
                    const headers = ['#', 'SKU', 'Loss $', 'Order QTY', 'MKT', 'Issue?', 'Action', 'Action Remark', 'Track R', 'Root Cause Found', 'Root Cause Remark', 'Root Cause Fixed', 'Root Cause Fixed Remark', 'Dept', 'Created By', 'Created At'];
                    const rows = table.getData().map(function (r) {
                        return [r.id, r.sku, r.total_loss ?? '', r.order_qty, r.marketplace_1, r.what_happened, r.action_1, r.action_1_remark, r.replacement_tracking, r.issue, r.issue_remark, r.c_action_1, r.c_action_1_remark, deptLabel(r), r.created_by, r.created_at];
                    });
                    downloadCsv([headers.map(csvEscape).join(',')].concat(rows.map(function (r) { return r.map(csvEscape).join(','); })).join('\r\n'), 'qc_pkg_issues_active_' + new Date().toISOString().slice(0, 10) + '.csv');
                });
                document.getElementById('qc-import').addEventListener('click', function () {
                    document.getElementById('importCsvFile').value = '';
                    document.getElementById('importCsvAlert').className = 'd-none mb-3';
                    document.getElementById('importCsvProgress').classList.add('d-none');
                    document.getElementById('importCsvErrors').classList.add('d-none');
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('importCsvModal')).show();
                });
                document.getElementById('importCsvSampleLink').addEventListener('click', function (e) {
                    e.preventDefault();
                    downloadCsv([IMPORT_HEADERS.join(','), IMPORT_SAMPLE.join(',')].join('\r\n'), 'import_sample.csv');
                });
                document.getElementById('importCsvSubmitBtn').addEventListener('click', async function () {
                    const fileInput = document.getElementById('importCsvFile');
                    const alertEl = document.getElementById('importCsvAlert');
                    if (!fileInput.files.length) { alertEl.className = 'alert alert-warning mb-3'; alertEl.textContent = 'Please select a CSV file.'; return; }
                    const formData = new FormData();
                    formData.append('file', fileInput.files[0]);
                    formData.append('_token', CSRF);
                    alertEl.className = 'd-none mb-3';
                    document.getElementById('importCsvProgress').classList.remove('d-none');
                    this.disabled = true;
                    try {
                        const res = await fetch(URLS.import, { method: 'POST', body: formData });
                        const data = await res.json();
                        document.getElementById('importCsvProgress').classList.add('d-none');
                        alertEl.className = 'alert mb-3 alert-' + (res.ok ? 'success' : 'danger');
                        alertEl.textContent = data.message || (res.ok ? 'Import complete.' : 'Import failed.');
                        if (data.errors && data.errors.length) {
                            document.getElementById('importCsvErrorList').innerHTML = data.errors.map(function (err) { return '<li>' + escapeHtml(err) + '</li>'; }).join('');
                            document.getElementById('importCsvErrors').classList.remove('d-none');
                        }
                        if (res.ok) await loadRows();
                    } catch (err) {
                        document.getElementById('importCsvProgress').classList.add('d-none');
                        alertEl.className = 'alert alert-danger mb-3';
                        alertEl.textContent = 'Network error. Please try again.';
                    } finally { document.getElementById('importCsvSubmitBtn').disabled = false; }
                });
            });
        })();
    </script>
@endsection
