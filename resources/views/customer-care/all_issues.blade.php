@extends('layouts.vertical', ['title' => 'All Issues', 'sidenav' => 'condensed'])
{{-- All Issues (Tabulator) — converted from the shared qc_and_packing HTML datatable --}}

@php
    $importCsvHeaders = [
        'sku',
        'order_number',
        'qty',
        'order_qty',
        'parent',
        'marketplace_1',
        'what_happened',
        'action_1',
        'action_1_remark',
        'tracking_number',
        'issue_link',
        'replacement_tracking',
        'issue',
        'issue_remark',
        'c_action_1',
        'c_action_1_remark',
        'department',
    ];
    $importCsvSampleRow = [
        'SAMPLE-SKU-001',
        '112-1234567-8901234',
        '5',
        '2',
        'PARENT-001',
        'Amazon',
        'Damaged',
        'Cancelled',
        '',
        '1Z999AA10123456784',
        'https://example.com',
        'TRK123',
        'Quality Issue',
        '',
        'Fixed',
        '',
        'Dispatch',
    ];
@endphp

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }

        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        .tabulator-paginator label {
            margin-right: 5px;
        }

        .sku-thumb {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 3px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }

        .sku-thumb-placeholder {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            border-radius: 3px;
            color: #adb5bd;
        }

        .issue-img-thumb {
            max-height: 36px;
            max-width: 54px;
            object-fit: cover;
            border-radius: 4px;
            vertical-align: middle;
            border: 1px solid #dee2e6;
        }

        .what-happened-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            background-color: #dc3545;
            vertical-align: middle;
        }

        .what-happened-dot-damaged {
            background-color: #b8860b;
        }

        .status-dot-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            vertical-align: middle;
            cursor: help;
        }

        .status-dot-missing {
            background-color: #dc3545;
        }

        .status-dot-available {
            background-color: #198754;
        }

        #all-issues-tabulator .tabulator-cell,
        #all-issues-history-tabulator .tabulator-cell {
            position: relative;
        }

        /* "Issue?" column: allow the full label (e.g. "LOST IN TRANSIT") to wrap
           onto two lines instead of being clipped with an ellipsis. */
        #all-issues-tabulator .tabulator-cell[tabulator-field="what_happened"],
        #all-issues-history-tabulator .tabulator-cell[tabulator-field="what_happened"] {
            white-space: normal;
            overflow: visible;
            text-overflow: clip;
            line-height: 1.25;
        }

        /* SKU column auto-sizes to fit the longest SKU in the page; cells must
           not clip with an ellipsis or the column won't grow to show the full
           value (e.g. "GSTOOL RND BLK REST"). */
        #all-issues-tabulator .tabulator-cell.ai-sku-cell,
        #all-issues-history-tabulator .tabulator-cell.ai-sku-cell {
            white-space: nowrap;
            overflow: visible;
            text-overflow: clip;
        }

        #all-issues-tabulator .tabulator-cell:hover:has(.tracking-cell) {
            overflow: visible;
            z-index: 30;
        }

        .tracking-cell {
            white-space: nowrap;
            cursor: default;
        }

        .tracking-dot {
            font-size: 1.1em;
            color: #6c757d;
        }

        .tracking-full {
            display: none;
            background: #fff;
            padding: 0 4px;
            box-shadow: 1px 0 2px rgba(0, 0, 0, 0.08);
        }

        .copy-tracking-btn,
        .copy-order-btn {
            color: #0d6efd;
            font-size: 0.8rem;
            line-height: 1;
            padding: 0 2px;
            border: none;
            background: none;
            cursor: pointer;
        }

        .copy-tracking-btn {
            display: none;
        }

        .tracking-cell:hover .tracking-dot {
            display: none;
        }

        .tracking-cell:hover .tracking-full,
        .tracking-cell:hover .copy-tracking-btn {
            display: inline;
        }

        .copy-order-btn.copied,
        .copy-tracking-btn.copied {
            color: #198754;
        }

        .issue-link-icon {
            color: #0d6efd;
            line-height: 1;
            padding: 2px 4px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .issue-link-icon:hover {
            color: #0a58ca;
            background: rgba(13, 110, 253, 0.1);
        }

        .cb-row-btn {
            border: none;
            background: transparent;
            padding: 1px 2px;
            cursor: pointer;
            color: #2563eb;
            line-height: 1;
        }

        .cb-row-btn.cb-danger {
            color: #dc2626;
        }

        .cb-row-btn.cb-details {
            color: #0d9488;
        }
        .cb-row-btn.cb-details:hover {
            color: #115e59;
            background: rgba(13, 148, 136, 0.12);
            border-radius: 4px;
        }

        /* Read-only "Details" modal grid */
        .ai-detail-section {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            margin-bottom: 0.75rem;
            background: #fff;
        }
        .ai-detail-section-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
            font-weight: 700;
            margin-bottom: 0.5rem;
            border-bottom: 1px solid #f3f4f6;
            padding-bottom: 0.35rem;
        }
        .ai-detail-row {
            display: flex;
            gap: 0.75rem;
            padding: 0.35rem 0;
            border-bottom: 1px dashed #f3f4f6;
            font-size: 0.92rem;
            line-height: 1.35;
        }
        .ai-detail-row:last-child { border-bottom: none; }
        .ai-detail-label {
            flex: 0 0 38%;
            color: #6b7280;
            font-weight: 600;
        }
        .ai-detail-value {
            flex: 1 1 auto;
            color: #111827;
            word-break: break-word;
        }
        .ai-detail-value.empty {
            color: #9ca3af;
            font-style: italic;
        }
        .ai-detail-thumb {
            max-width: 140px;
            max-height: 140px;
            object-fit: contain;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            background: #fff;
            padding: 2px;
        }
        .ai-detail-status-line {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sku-image-preview {
            width: 88px;
            height: 88px;
            object-fit: contain;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background: #fff;
            padding: 4px;
        }

        .issue-modal-thumb {
            max-height: 48px;
            max-width: 72px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            vertical-align: middle;
        }

        /* L30 badges — compact to fit alongside filters on a single toolbar row */
        .l30-badge,
        .l30-issues-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 0.375rem;
            cursor: pointer;
            user-select: none;
            border: 1px solid;
            background: #fff;
            white-space: nowrap;
            transition: box-shadow 0.15s;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
            height: 31px;
            flex: 0 0 auto;
        }

        .l30-badge {
            border-color: #e05252;
            color: #c0392b;
        }

        .l30-badge:hover {
            box-shadow: 0 2px 8px rgba(224, 82, 82, .2);
        }

        .l30-issues-badge {
            border-color: #4a9e6b;
            color: #27693e;
        }

        .l30-issues-badge:hover {
            box-shadow: 0 2px 8px rgba(74, 158, 107, .2);
        }

        /* Single-row toolbar: keep every item from stretching across the flex
           line when wrapping (a bare <select> with min-width grows otherwise),
           and let the toolbar scroll horizontally on very narrow screens
           instead of stacking. */
        #all-issues-toolbar {
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: visible;
            scrollbar-width: thin;
        }

        #all-issues-toolbar > * {
            flex: 0 0 auto;
        }

        #all-issues-toolbar .btn {
            white-space: nowrap;
        }

        /* Search field grows to absorb extra space, while keeping a reasonable
           minimum and a cap so it never crowds out the Total badge on wide
           screens. */
        #all-issues-toolbar #ai-search-wrap {
            flex: 1 1 220px;
            min-width: 180px;
            max-width: 360px;
        }

        #all-issues-toolbar #ai-search {
            height: 31px;
            font-size: 12px;
        }

        #all-issues-toolbar #hold_issue_total_count_wrap {
            margin-left: auto;
            white-space: nowrap;
        }

        #dept-filter-select {
            font-size: 12px;
            padding: 2px 24px 2px 8px;
            height: 31px;
            width: 170px;
            min-width: 170px;
            max-width: 170px;
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
            cursor: pointer;
        }

        /* Remove black backdrop completely (matches existing All Issues UX) */
        .modal-backdrop,
        .modal-backdrop.show {
            display: none !important;
        }

        .modal.show {
            background-color: transparent !important;
        }

        /* Force the Add/Edit Issue modal to fit the viewport so the Save button
           is always visible. The form body scrolls inside the modal. */
        #ordersOnHoldIssueModal {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100% !important;
            height: 100vh !important;
            overflow: hidden !important;
            z-index: 1060 !important;
        }
        #ordersOnHoldIssueModal .modal-dialog {
            max-width: 800px !important;
            width: calc(100% - 2rem) !important;
            max-height: calc(100vh - 1rem) !important;
            height: calc(100vh - 1rem) !important;
            margin: 0.5rem auto !important;
            display: flex !important;
            align-items: stretch !important;
        }
        #ordersOnHoldIssueModal .modal-content {
            max-height: 100% !important;
            height: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
            background: #fff !important;
        }
        #ordersOnHoldIssueModal .modal-header,
        #ordersOnHoldIssueModal .modal-footer {
            flex: 0 0 auto !important;
        }
        /* The <form> wraps the body + footer, so make it a flex column too
           and let it consume the remaining space inside .modal-content. */
        #ordersOnHoldIssueModal #ordersOnHoldIssueForm {
            flex: 1 1 auto !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 0 !important;
            margin: 0 !important;
        }
        #ordersOnHoldIssueModal .modal-body {
            flex: 1 1 auto !important;
            overflow-y: auto !important;
            min-height: 0 !important;
        }

        /* Custom loader overlay (overallAmazon-style: fetch first, then build grid) */
        .ai-loader-wrap {
            position: relative;
            min-height: 80px;
        }

        .ai-loader {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.85);
            z-index: 10;
            font-size: 14px;
            color: #495057;
        }

        .ai-loader.d-none {
            display: none !important;
        }

        .ai-loader .spinner-border {
            margin-right: 0.5rem;
        }

        /* Combined Created By cell — stack "name" on top of the short date */
        .created-by-combo {
            display: flex;
            flex-direction: column;
            gap: 2px;
            line-height: 1.15;
        }

        .created-by-combo .created-by-name {
            font-weight: 600;
            font-size: 12px;
        }

        .created-by-combo .created-by-date {
            font-size: 11px;
            opacity: 0.85;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'All Issues',
        'sub_title' => 'Customer Care',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div id="all-issues-toolbar" class="d-flex align-items-center gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                        data-bs-target="#ordersOnHoldIssueModal">
                        <i class="fa-solid fa-plus"></i> All Issues
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnShowHistory">
                        <i class="fa-solid fa-clock-rotate-left"></i> History
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="btnExportCsv"
                        title="Export CSV" aria-label="Export CSV">
                        <i class="fa-solid fa-download"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" id="btnImportCsv">
                        <i class="fa-solid fa-upload"></i> Import CSV
                    </button>
                    <div id="l30-loss-badge" class="l30-badge" role="button" data-bs-toggle="modal"
                        data-bs-target="#l30LossModal" title="Last 30 Days Loss — click for detail">
                        <i class="fa-solid fa-arrow-trend-down"></i> L30 Loss: <span id="l30-badge-total">…</span>
                    </div>
                    <div id="l30-issues-badge" class="l30-issues-badge" role="button" data-bs-toggle="modal"
                        data-bs-target="#l30IssuesModal" title="Last 30 Days Issues — click for detail">
                        <i class="fa-solid fa-circle-exclamation"></i> <span id="l30-issues-badge-label">L30</span> Issues:
                        <span id="l30-issues-badge-total">…</span>
                    </div>
                    <select id="dept-filter-select" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                    </select>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" id="ai-columns-btn"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            <i class="fa-solid fa-table-columns"></i> Columns
                        </button>
                        <div class="dropdown-menu p-2" id="ai-columns-menu"
                            style="max-height: 60vh; overflow-y: auto; min-width: 220px;"></div>
                    </div>
                    <div id="ai-search-wrap" class="position-relative">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"
                            style="position: absolute; left: 9px; top: 50%; transform: translateY(-50%); color: #adb5bd; font-size: 12px; pointer-events: none;"></i>
                        <input type="text" id="ai-search" class="form-control form-control-sm"
                            placeholder="Search all columns (case-insensitive)..."
                            style="padding-left: 28px;">
                    </div>
                    <span id="hold_issue_total_count_wrap" class="badge bg-light text-dark align-self-center"
                        title="Total active error groups">Total: <span id="hold_issue_total_count">0</span></span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="all-issues-table-wrapper"
                    style="height: calc(100vh - 220px); min-height: 320px; display: flex; flex-direction: column;">
                    <div class="ai-loader-wrap" style="flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0;">
                        <div id="ai-main-loader" class="ai-loader">
                            <div class="spinner-border spinner-border-sm" role="status"></div> Loading issues…
                        </div>
                        <div id="all-issues-tabulator" style="flex: 1 1 auto; min-height: 0;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- History --}}
    <div class="row mt-3 d-none" id="holdIssueHistoryCard">
        <div class="card shadow-sm">
            <div class="card-body py-2 d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Order History</h5>
                <span class="badge bg-light text-dark" id="hold_issue_history_total_count">0</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="ai-loader-wrap" id="all-issues-history-wrapper"
                    style="height: 60vh; min-height: 320px; display: flex; flex-direction: column;">
                    <div id="ai-history-loader" class="ai-loader d-none">
                        <div class="spinner-border spinner-border-sm" role="status"></div> Loading history…
                    </div>
                    <div id="all-issues-history-tabulator" style="flex: 1 1 auto; min-height: 0;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Row Details Modal (read-only — opened by the Details column magnifier) ── --}}
    <div class="modal fade" id="allIssuesDetailsModal" tabindex="-1" aria-hidden="true" aria-labelledby="allIssuesDetailsModalLabel">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="allIssuesDetailsModalLabel">
                        <i class="bi bi-search me-2"></i>Issue details
                        <span class="text-muted small ms-1" id="allIssuesDetailsModalSubtitle"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="allIssuesDetailsModalBody">
                    {{-- Populated by openDetailsModal() in JS --}}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Import CSV Modal ── --}}
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
                        <code>sku, order_number, qty, order_qty, parent, marketplace_1, what_happened, action_1,
                            action_1_remark, tracking_number, issue_link, replacement_tracking, issue, issue_remark,
                            c_action_1, c_action_1_remark, department</code>
                    </p>
                    <p class="text-muted small mb-3">
                        Required: <strong>sku</strong>, <strong>qty</strong>, <strong>issue</strong> (Root Cause),
                        <strong>department</strong>. Use multiple departments separated by <strong>|</strong> or
                        <strong>,</strong>
                        (e.g. <code>Dispatch|QC</code>). Other columns are optional.
                    </p>
                    <div class="mb-3">
                        <label for="importCsvFile" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="importCsvFile" accept=".csv,.txt">
                    </div>
                    <div id="importCsvProgress" class="d-none">
                        <div class="progress mb-2">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-info"
                                style="width:100%"></div>
                        </div>
                        <p class="text-muted small text-center">Uploading…</p>
                    </div>
                    <div id="importCsvErrors" class="d-none">
                        <p class="fw-semibold small mb-1 text-warning">Skipped rows:</p>
                        <ul id="importCsvErrorList" class="small text-warning mb-0"
                            style="max-height:160px;overflow-y:auto;"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="importCsvSampleLink" class="btn btn-sm btn-outline-secondary me-auto">
                        <i class="bi bi-download me-1"></i> Download Sample
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" id="importCsvSubmitBtn"><i
                            class="bi bi-upload me-1"></i> Import</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Add / Edit Modal ── --}}
    <div class="modal fade" id="ordersOnHoldIssueModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ordersOnHoldIssueModalLabel">All Issues</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="ordersOnHoldIssueForm" autocomplete="off">
                    <div class="modal-body">
                        <div id="ordersOnHoldIssueAlert" class="alert alert-danger d-none mb-3" role="alert"></div>

                        <div class="row g-3">
                            <div class="col-12" id="sku-rows-wrapper">
                                <div class="sku-entry-row" data-row-index="0">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-8">
                                            <label class="form-label">SKU <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control sku-entry-input"
                                                id="hold_issue_sku" name="sku" list="hold_issue_sku_datalist"
                                                placeholder="Search SKU" required autocomplete="off">
                                            <datalist id="hold_issue_sku_datalist"></datalist>
                                            <div class="mt-1 d-none" id="hold_issue_sku_image_wrap">
                                                <img src="" alt="SKU Image" id="hold_issue_sku_image"
                                                    class="sku-image-preview">
                                            </div>
                                        </div>
                                        <div style="display:none;">
                                            <input type="number" class="form-control sku-entry-qty" id="hold_issue_qty"
                                                name="qty" value="0" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">QTY</label>
                                            <input type="number" class="form-control sku-entry-order-qty"
                                                id="hold_issue_order_qty" name="order_qty" min="0" step="1"
                                                placeholder="Qty">
                                        </div>
                                        <div style="display:none;">
                                            <input type="text" class="form-control sku-entry-parent"
                                                id="hold_issue_parent" name="parent" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div id="extra-sku-rows-container" class="mt-2"></div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="btn-add-sku-row">
                                    <i class="bi bi-plus-circle me-1"></i> Add Another SKU
                                </button>
                                <div class="mt-1">
                                    <small class="text-muted">Multiple SKUs for the same order are grouped and counted as
                                        <strong>1 error</strong>.</small>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_order_number" class="form-label">Order Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="hold_issue_order_number"
                                    name="order_number" placeholder="Enter order number" required>
                            </div>
                            <div class="col-md-6">
                                <label for="hold_issue_total_loss" class="form-label">Loss $</label>
                                <input type="number" class="form-control" id="hold_issue_total_loss" name="total_loss"
                                    step="0.01" placeholder="0.00">
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_marketplace_1" class="form-label">MKT</label>
                                {{-- Options show the channel ALIAS to the user
                                     (e.g. "Ebay 1", "P Power") while the
                                     submitted value remains the canonical
                                     `channel` name from channel_master so the
                                     backend stays untouched. --}}
                                <select class="form-select" id="hold_issue_marketplace_1" name="marketplace_1">
                                    <option value="">Select Marketplace</option>
                                    @foreach ($mktChannels ?? collect() as $mkt)
                                        <option value="{{ $mkt['channel'] }}">{{ $mkt['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_what_happened" class="form-label">Issue?</label>
                                <div class="input-group">
                                    <select class="form-select" id="hold_issue_what_happened" name="what_happened">
                                        <option value="">— Select issue —</option>
                                        <optgroup label="Common issues" id="hold_issue_what_happened_builtin_group">
                                            <option value="Wrong Item Sent">Wrong Item Sent</option>
                                            <option value="Wrong Quantity Sent">Wrong Quantity Sent</option>
                                            <option value="0 Stock">0 Stock</option>
                                            <option value="Damaged">Damaged</option>
                                        </optgroup>
                                        <optgroup label="Custom issues" id="hold_issue_what_happened_custom_group"></optgroup>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" id="add-what-happened-option" title="Add custom issue"><i class="bi bi-plus-lg"></i></button>
                                    <button type="button" class="btn btn-outline-danger" id="delete-what-happened-option" title="Delete the selected custom issue"><i class="bi bi-trash"></i></button>
                                </div>
                                <div class="form-text">Pick a built-in issue or click + to add your own.</div>
                            </div>

                            {{-- ── Issue sub-sections (conditional, mirrors Action sub-section pattern) ── --}}
                            <div class="col-12 d-none" id="whatHappenedSubsection">
                                {{-- 1. Wrong Item Sent --}}
                                <div class="border rounded p-3 d-none" id="wrongItemSubsection" data-issue-key="wrong_item">
                                    <div class="fw-semibold mb-2 small text-uppercase text-muted">Wrong Item Sent details</div>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-6">
                                            <label for="hold_issue_wrong_sent_sku" class="form-label small mb-1">
                                                Wrongly sent SKU <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="hold_issue_wrong_sent_sku"
                                                name="wrong_sent_sku" list="hold_issue_wrong_sent_sku_datalist"
                                                placeholder="Search SKU" autocomplete="off">
                                            <datalist id="hold_issue_wrong_sent_sku_datalist"></datalist>
                                            <div class="d-flex align-items-center gap-2 mt-1 d-none" id="wrongSentSkuPreview">
                                                <img src="" alt="Wrong SKU" id="wrongSentSkuImage"
                                                    class="sku-image-preview" style="width:48px;height:48px;">
                                                <div class="small text-muted">
                                                    Available: <span id="wrongSentQtyAvailable" class="fw-semibold text-dark">—</span>
                                                </div>
                                            </div>
                                        </div>
                                        {{-- Wrongly sent quantity. Optional at form-level; only
                                             becomes required when "Outgoing needed?" below is
                                             checked, since the Shopify deduction needs a number. --}}
                                        <div class="col-md-3">
                                            <label for="hold_issue_wrong_sent_qty" class="form-label small mb-1">
                                                Qty wrongly sent
                                            </label>
                                            <input type="number" min="0" step="1" class="form-control"
                                                id="hold_issue_wrong_sent_qty" name="wrong_sent_qty" placeholder="Qty">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="hold_issue_issue_notes" class="form-label small mb-1">
                                                Notes <span class="text-muted">(≤200)</span>
                                            </label>
                                            <textarea class="form-control" id="hold_issue_issue_notes" name="issue_notes"
                                                maxlength="200" rows="2" placeholder="Notes..."></textarea>
                                            <div class="form-text text-end small">
                                                <span id="issueNotesCharCount">0</span> / 200
                                            </div>
                                        </div>
                                        {{-- "Why it happened" dropdown — built-in starter options +
                                             custom user-added options. Same UX as the Issue? / Action
                                             dropdowns: the + button adds a custom option, the trash
                                             button deletes the currently-selected custom option.
                                             Optional (not enforced). --}}
                                        <div class="col-md-6">
                                            <label for="hold_issue_wrong_sent_reason" class="form-label small mb-1">
                                                Why it happened?
                                            </label>
                                            <div class="input-group">
                                                <select class="form-select" id="hold_issue_wrong_sent_reason"
                                                    name="wrong_sent_reason">
                                                    <option value="">— Select reason —</option>
                                                    <optgroup label="Common reasons" id="hold_issue_wrong_sent_reason_builtin_group">
                                                        <option value="Picker error">Picker error</option>
                                                        <option value="Label swap">Label swap</option>
                                                        <option value="Mis-scan / barcode mismatch">Mis-scan / barcode mismatch</option>
                                                        <option value="Look-alike SKU">Look-alike SKU</option>
                                                        <option value="Listing image mismatch">Listing image mismatch</option>
                                                        <option value="Other">Other</option>
                                                    </optgroup>
                                                    <optgroup label="Custom reasons" id="hold_issue_wrong_sent_reason_custom_group"></optgroup>
                                                </select>
                                                <button type="button" class="btn btn-outline-secondary"
                                                    id="add-wrong-sent-reason-option" title="Add custom reason"><i class="bi bi-plus-lg"></i></button>
                                                <button type="button" class="btn btn-outline-danger"
                                                    id="delete-wrong-sent-reason-option" title="Delete the selected custom reason"><i class="bi bi-trash"></i></button>
                                            </div>
                                            <div class="form-text">Pick a built-in reason or click + to add your own.</div>
                                        </div>
                                        {{-- Outgoing trigger for the Wrongly Sent SKU. Mirrors the
                                             Replacement "Outgoing needed?" pattern but lives in its
                                             own column set so an issue can fire BOTH outgoings
                                             (replacement SKU + wrongly sent SKU) on a single save.
                                             Optional. When ticked, /outgoing-view receives a row
                                             with reason "Wrong Item Sent (All Issues)" and Shopify
                                             inventory is decremented by Qty wrongly sent. --}}
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    id="hold_issue_wrong_sent_outgoing_needed"
                                                    name="wrong_sent_outgoing_needed" value="1">
                                                <label class="form-check-label"
                                                    for="hold_issue_wrong_sent_outgoing_needed">
                                                    Outgoing needed? <span class="text-muted small">(deduct wrongly sent qty from Shopify)</span>
                                                </label>
                                                <div class="form-text mt-1 d-none"
                                                    id="wrongSentOutgoingProcessedNotice">
                                                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                                                    Already processed — Shopify inventory was decremented and a row was added to <a href="/outgoing-view" target="_blank">/outgoing-view</a>. Re-saves will not double-decrement.
                                                </div>
                                            </div>
                                        </div>
                                        {{-- Warehouse picker: required only when "Outgoing needed?"
                                             above is checked. --}}
                                        <div class="col-md-6 d-none" id="wrongSentOutgoingWarehouseWrap">
                                            <label for="hold_issue_wrong_sent_outgoing_warehouse_id"
                                                class="form-label small mb-1">
                                                Outgoing warehouse <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select"
                                                id="hold_issue_wrong_sent_outgoing_warehouse_id"
                                                name="wrong_sent_outgoing_warehouse_id">
                                                <option value="">— Select warehouse —</option>
                                                @foreach (($outgoingWarehouses ?? collect()) as $w)
                                                    <option value="{{ $w->id }}">{{ $w->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="form-text small">
                                                Saving will deduct <strong>Qty wrongly sent</strong> from Shopify inventory and create an <a href="/outgoing-view" target="_blank">/outgoing-view</a> row (reason: <em>Wrong Item Sent (All Issues)</em>).
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- 2. Wrong Quantity Sent --}}
                                <div class="border rounded p-3 d-none" id="wrongQtySubsection" data-issue-key="wrong_qty">
                                    <div class="fw-semibold mb-2 small text-uppercase text-muted">Wrong Quantity Sent details</div>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-12">
                                            <label class="form-label small mb-1">Mismatch <span class="text-danger">*</span></label>
                                            <div class="d-flex gap-3 pt-1">
                                                <label class="form-check-label">
                                                    <input class="form-check-input me-1 qty-mismatch-radio" type="radio"
                                                        name="qty_mismatch_type" id="qtyMismatch_less" value="less">
                                                    Quantity less
                                                </label>
                                                <label class="form-check-label">
                                                    <input class="form-check-input me-1 qty-mismatch-radio" type="radio"
                                                        name="qty_mismatch_type" id="qtyMismatch_more" value="more">
                                                    Quantity more
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6 d-none" id="qtySentWrap">
                                            <label for="hold_issue_qty_sent" class="form-label small mb-1">
                                                Quantity sent <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" min="0" step="any" class="form-control"
                                                id="hold_issue_qty_sent" name="qty_sent" placeholder="0">
                                        </div>
                                        <div class="col-md-6 d-none" id="qtyOrderedWrap">
                                            <label for="hold_issue_qty_ordered" class="form-label small mb-1">
                                                Quantity ordered <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" min="0" step="any" class="form-control"
                                                id="hold_issue_qty_ordered" name="qty_ordered" placeholder="0">
                                        </div>
                                    </div>
                                </div>

                                {{-- 3. Generic notes — shown for any other issue (built-in like
                                     0 Stock / Damaged, or any custom user-added issue). --}}
                                <div class="border rounded p-3 d-none" id="customIssueSubsection" data-issue-key="generic">
                                    <div class="fw-semibold mb-2 small text-uppercase text-muted" id="customIssueSubsectionTitle">
                                        Issue details
                                    </div>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-12">
                                            <label for="hold_issue_custom_issue_notes" class="form-label small mb-1">
                                                Notes <span class="text-muted">(max 200 chars)</span>
                                            </label>
                                            <textarea class="form-control" id="hold_issue_custom_issue_notes"
                                                maxlength="200" rows="3"
                                                placeholder="Describe what happened for this issue..."></textarea>
                                            <div class="form-text text-end small">
                                                <span id="customIssueNotesCharCount">0</span> / 200
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_action_1" class="form-label">Action</label>
                                <div class="input-group">
                                    <select class="form-select" id="hold_issue_action_1" name="action_1">
                                        <option value="">— Select action —</option>
                                        <optgroup label="Common actions" id="hold_issue_action_builtin_group">
                                            <option value="Refund">Refund</option>
                                            <option value="Replacement">Replacement</option>
                                            <option value="Alternate Sent">Alternate Sent</option>
                                            <option value="Other">Other</option>
                                        </optgroup>
                                        <optgroup label="Custom actions" id="hold_issue_action_custom_group"></optgroup>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" id="add-action-option" title="Add custom action"><i class="bi bi-plus-lg"></i></button>
                                    <button type="button" class="btn btn-outline-danger" id="delete-action-option" title="Delete the selected custom action"><i class="bi bi-trash"></i></button>
                                </div>
                                <div class="form-text">Choose a built-in action or click + to add your own. Custom actions are reusable across all issues.</div>
                            </div>

                            <div class="col-md-6" id="action1RemarkWrap">
                                <label for="hold_issue_action_1_remark" class="form-label">Action Remark
                                    <span id="action1RemarkRequiredStar" class="text-danger d-none"
                                        aria-hidden="true">*</span></label>
                                <input type="text" class="form-control" id="hold_issue_action_1_remark"
                                    name="action_1_remark" placeholder="Write action remark...">
                            </div>

                            {{-- ── Action sub-sections (conditional) ─────────────────────── --}}
                            <div class="col-12 d-none" id="actionSubsection">
                                {{-- Refund --}}
                                <div class="border rounded p-3 d-none" id="refundSubsection" data-action-key="refund">
                                    <div class="fw-semibold mb-2 small text-uppercase text-muted">Refund details</div>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-6">
                                            <label class="form-label small mb-1">Refund type <span class="text-danger">*</span></label>
                                            <div class="d-flex gap-3 pt-1">
                                                <label class="form-check-label">
                                                    <input class="form-check-input me-1" type="radio" name="refund_type"
                                                        id="refundType_partial" value="partial">
                                                    Partial refund
                                                </label>
                                                <label class="form-check-label">
                                                    <input class="form-check-input me-1" type="radio" name="refund_type"
                                                        id="refundType_full" value="full">
                                                    Full refund
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="hold_issue_refund_amount" class="form-label small mb-1">
                                                Amount $ <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" step="0.01" min="0" class="form-control"
                                                id="hold_issue_refund_amount" name="refund_amount" placeholder="0.00">
                                        </div>
                                    </div>
                                </div>

                                {{-- Replacement / Alternate Sent (identical schema, single shared form block) --}}
                                <div class="border rounded p-3 d-none" id="replacementSubsection" data-action-key="replacement">
                                    <div class="fw-semibold mb-2 small text-uppercase text-muted" id="replacementSubsectionTitle">
                                        Replacement details
                                    </div>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-6">
                                            <label for="hold_issue_replacement_sku" class="form-label small mb-1">
                                                Replacement SKU <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="hold_issue_replacement_sku"
                                                name="replacement_sku" list="hold_issue_replacement_sku_datalist"
                                                placeholder="Search SKU" autocomplete="off">
                                            <datalist id="hold_issue_replacement_sku_datalist"></datalist>
                                            <div class="d-flex align-items-center gap-2 mt-1 d-none"
                                                id="replacementSkuPreview">
                                                <img src="" alt="Replacement SKU"
                                                    id="replacementSkuImage" class="sku-image-preview"
                                                    style="width:48px;height:48px;">
                                                <div class="small text-muted">
                                                    Available: <span id="replacementQtyAvailable" class="fw-semibold text-dark">—</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label for="hold_issue_replacement_qty_sending" class="form-label small mb-1">
                                                Qty sending <span class="text-danger">*</span>
                                            </label>
                                            <input type="number" min="0" step="1" class="form-control"
                                                id="hold_issue_replacement_qty_sending" name="replacement_qty_sending"
                                                placeholder="Qty">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="hold_issue_replacement_tracking_30" class="form-label small mb-1">
                                                Tracking ID
                                            </label>
                                            <input type="text" class="form-control" id="hold_issue_replacement_tracking_30"
                                                maxlength="30" placeholder="Up to 30 chars">
                                        </div>
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    id="hold_issue_outgoing_needed" name="outgoing_needed" value="1">
                                                <label class="form-check-label" for="hold_issue_outgoing_needed">
                                                    Outgoing needed?
                                                </label>
                                                <div class="form-text mt-1 d-none" id="outgoingProcessedNotice">
                                                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                                                    Already processed — Shopify inventory was decremented and a row was added to <a href="/outgoing-view" target="_blank">/outgoing-view</a>. Re-saves will not double-decrement.
                                                </div>
                                            </div>
                                        </div>
                                        {{-- Warehouse picker: required when Outgoing needed is checked --}}
                                        <div class="col-md-6 d-none" id="outgoingWarehouseWrap">
                                            <label for="hold_issue_outgoing_warehouse_id" class="form-label small mb-1">
                                                Outgoing warehouse <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" id="hold_issue_outgoing_warehouse_id"
                                                name="outgoing_warehouse_id">
                                                <option value="">— Select warehouse —</option>
                                                @foreach (($outgoingWarehouses ?? collect()) as $w)
                                                    <option value="{{ $w->id }}">{{ $w->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="form-text small">
                                                Saving will deduct <strong>Quantity sending</strong> from Shopify inventory and create an <a href="/outgoing-view" target="_blank">/outgoing-view</a> row (reason: <em>Replacement (All Issues)</em>).
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Other --}}
                                <div class="border rounded p-3 d-none" id="otherSubsection" data-action-key="other">
                                    <div class="fw-semibold mb-2 small text-uppercase text-muted">Notes</div>
                                    <textarea class="form-control" id="hold_issue_other_notes" rows="3"
                                        placeholder="Write notes about this action..."></textarea>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_issue_link" class="form-label">Link</label>
                                <input type="text" class="form-control" id="hold_issue_issue_link" name="issue_link"
                                    maxlength="500" placeholder="https://… or paste any reference URL" autocomplete="off"
                                    inputmode="url">
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_text" class="form-label">Root Cause</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="hold_issue_text" name="issue"
                                        list="hold_issue_root_cause_found_datalist"
                                        placeholder="Type or select root cause..." autocomplete="off">
                                    <button type="button" class="btn btn-outline-secondary"
                                        id="add-root-cause-found-option" title="Add option"><i
                                            class="bi bi-plus-lg"></i></button>
                                    <button type="button" class="btn btn-outline-danger"
                                        id="delete-root-cause-found-option" title="Delete option"><i
                                            class="bi bi-trash"></i></button>
                                </div>
                                <datalist id="hold_issue_root_cause_found_datalist"></datalist>
                            </div>

                            <div class="col-12 d-none" id="rootCauseRemarkWrap">
                                <label for="hold_issue_remark" class="form-label">Root Cause Remark <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="hold_issue_remark" name="issue_remark"
                                    placeholder="Write remark for Other">
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_image_1" class="form-label">Image 1</label>
                                <input type="file" accept="image/*" class="form-control" id="hold_issue_image_1"
                                    autocomplete="off">
                                <div id="hold_issue_image_1_existing" class="mt-1 d-none small"></div>
                            </div>
                            <div class="col-md-6">
                                <label for="hold_issue_image_2" class="form-label">Image 2</label>
                                <input type="file" accept="image/*" class="form-control" id="hold_issue_image_2"
                                    autocomplete="off">
                                <div id="hold_issue_image_2_existing" class="mt-1 d-none small"></div>
                            </div>
                            <div class="col-12">
                                <p class="text-muted small mb-0">Attach screenshots or image files. You can also paste an
                                    image from the clipboard (Ctrl/Cmd+V) while this dialog is focused — it fills Image 1,
                                    then Image 2.</p>
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_c_action_1" class="form-label">Root Cause Fixed</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="hold_issue_c_action_1"
                                        name="c_action_1" list="hold_issue_root_cause_fixed_datalist"
                                        placeholder="Type or select fix..." autocomplete="off">
                                    <button type="button" class="btn btn-outline-secondary"
                                        id="add-root-cause-fixed-option" title="Add option"><i
                                            class="bi bi-plus-lg"></i></button>
                                    <button type="button" class="btn btn-outline-danger"
                                        id="delete-root-cause-fixed-option" title="Delete option"><i
                                            class="bi bi-trash"></i></button>
                                </div>
                                <datalist id="hold_issue_root_cause_fixed_datalist"></datalist>
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_department" class="form-label">Department <span
                                        class="text-danger">*</span></label>
                                <div class="dropdown qc-dept-multiselect" id="hold_issue_department_ui">
                                    <button
                                        class="form-select text-start d-flex align-items-center justify-content-between"
                                        type="button" id="hold_issue_department_toggle" data-bs-toggle="dropdown"
                                        data-bs-auto-close="outside" aria-expanded="false">
                                        <span id="hold_issue_department_label" class="text-truncate text-muted">Select
                                            department(s)</span>
                                    </button>
                                    <div class="dropdown-menu p-2 w-100" id="hold_issue_department_menu"
                                        style="max-height:240px;overflow:auto;"></div>
                                </div>
                                <select class="form-select d-none" id="hold_issue_department" name="department[]"
                                    multiple size="5">
                                    <option value="Dispatch">Dispatch</option>
                                    <option value="Shipping">Shipping</option>
                                    <option value="Listing">Listing</option>
                                    <option value="Carrier">Carrier and Claim</option>
                                    <option value="Carrier Issue">Carrier Issue</option>
                                    <option value="Customer Care">Customer Care</option>
                                    <option value="Pricing">Pricing</option>
                                    <option value="QC">QC</option>
                                    <option value="Packaging">Packaging</option>
                                    <option value="Chargeback">Chargeback</option>
                                    <option value="Orders on Hold">Orders on Hold</option>
                                </select>
                                <div class="form-text">Click to select one or more departments.</div>
                            </div>

                            <div class="col-12 d-none" id="cAction1RemarkWrap">
                                <label for="hold_issue_c_action_1_remark" class="form-label">Root Cause Fixed Remark <span
                                        class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="hold_issue_c_action_1_remark"
                                    name="c_action_1_remark" placeholder="Write remark for Other">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ── L30 Loss Modal ── --}}
    <div class="modal fade" id="l30LossModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none" style="max-width:98vw;width:98vw;margin:10px auto 0;">
            <div class="modal-content" style="border-radius:8px;overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="bi bi-graph-down-arrow me-1"></i> L30 Loss
                        <small id="l30-modal-range" style="font-size:10px;opacity:.8;"></small>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" style="font-size:10px;"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-2">
                    <div style="height:240px;display:flex;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;"><canvas id="l30LossLineChart"></canvas></div>
                        <div
                            style="width:90px;display:flex;flex-direction:column;justify-content:center;gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;">
                            <div style="text-align:center;">
                                <div
                                    style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#dc3545;margin-bottom:1px;">
                                    Highest</div>
                                <div id="l30-loss-highest" style="font-size:14px;font-weight:700;color:#dc3545;">-</div>
                            </div>
                            <div
                                style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                                <div
                                    style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;margin-bottom:1px;">
                                    Median</div>
                                <div id="l30-loss-median" style="font-size:14px;font-weight:700;color:#6c757d;">-</div>
                            </div>
                            <div style="text-align:center;">
                                <div
                                    style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#198754;margin-bottom:1px;">
                                    Lowest</div>
                                <div id="l30-loss-lowest" style="font-size:14px;font-weight:700;color:#198754;">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── L30 Issues Modal ── --}}
    <div class="modal fade" id="l30IssuesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none" style="max-width:98vw;width:98vw;margin:10px auto 0;">
            <div class="modal-content" style="border-radius:8px;overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="bi bi-exclamation-circle me-1"></i> L30 Issues
                        <small id="l30-issues-modal-range" style="font-size:10px;opacity:.8;"></small>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" style="font-size:10px;"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-2">
                    <div style="height:240px;display:flex;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;"><canvas id="l30IssuesLineChart"></canvas></div>
                        <div
                            style="width:90px;display:flex;flex-direction:column;justify-content:center;gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;">
                            <div style="text-align:center;">
                                <div
                                    style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#dc3545;margin-bottom:1px;">
                                    Highest</div>
                                <div id="l30-issues-highest" style="font-size:14px;font-weight:700;color:#dc3545;">-</div>
                            </div>
                            <div
                                style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                                <div
                                    style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;margin-bottom:1px;">
                                    Median</div>
                                <div id="l30-issues-median" style="font-size:14px;font-weight:700;color:#6c757d;">-</div>
                            </div>
                            <div style="text-align:center;">
                                <div
                                    style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#198754;margin-bottom:1px;">
                                    Lowest</div>
                                <div id="l30-issues-lowest" style="font-size:14px;font-weight:700;color:#198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div style="height:150px;margin-top:8px;"><canvas id="l30IssuesBarChart"></canvas></div>
                    <hr class="my-2">
                    <div class="table-responsive" style="max-height:220px;overflow-y:auto;">
                        <table class="table table-sm table-striped table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end">Issues</th>
                                </tr>
                            </thead>
                            <tbody id="l30-issues-table-body">
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-3">Loading…</td>
                                </tr>
                            </tbody>
                            <tfoot id="l30-issues-table-foot"></tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function() {
            'use strict';
            // ── Config / routes ────────────────────────────────────────────────
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const currentUserEmail = @json(auth()->user()?->email ?? '');
            const skuSearchUrl = @json(route('customer.care.followups.skus'));
            const skuDetailsUrl = @json(route('customer.care.dispatch.issues.sku.details'));
            const replacementSkuDetailsUrl = @json(route('customer.care.dispatch.issues.replacement.sku.details'));
            const recordsListUrl = @json(route('customer.care.dispatch.issues.list.index'));
            const recordsStoreUrl = @json(route('customer.care.dispatch.issues.list.store'));
            const recordsUpdateBaseUrl = @json(route('customer.care.dispatch.issues.list.index', [], false));
            const historyListUrl = @json(route('customer.care.dispatch.issues.history.index'));
            const dropdownOptionsListUrl = @json(route('customer.care.dispatch.issues.dropdown.options.index'));
            const dropdownOptionsStoreUrl = @json(route('customer.care.dispatch.issues.dropdown.options.store'));
            const dropdownOptionsDeleteUrl = @json(route('customer.care.dispatch.issues.dropdown.options.delete'));
            const importUrl = @json(route('customer.care.dispatch.issues.import'));
            const archiveBase = @json(url('/customer-care/all-issues/issues'));
            const l30LossUrl = @json(route('customer.care.dispatch.issues.l30.loss'));
            const l30IssuesUrl = @json(route('customer.care.dispatch.issues.l30.issues'));
            const colVisGet = @json(route('tabulator.column.visibility.user.get'));
            const colVisSet = @json(route('tabulator.column.visibility.user.set'));
            const COLVIS_CHANNEL = 'all_issues';
            const importCsvHeaders = @json($importCsvHeaders);
            const importCsvSampleRow = @json($importCsvSampleRow);

            const jsonHeaders = {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            };
            const getHeaders = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };

            // ── State ──────────────────────────────────────────────────────────
            let table, historyTable;
            let holdIssueRows = [];
            let holdIssueHistoryRows = [];
            let activeDeptFilter = null;
            let editingIssueId = null;
            let skuTimer = null;

            // ── Layout: keep the table wrappers anchored to the viewport so the
            //    Tabulator (with its sticky pagination footer) is always visible
            //    even when the toolbar wraps to multiple lines on narrow screens.
            //    Without this, the wrapper used a fixed `calc(100vh - 220px)`,
            //    which under-counted the toolbar height and pushed the table
            //    (and its pagination bar) below the viewport — the user then
            //    scrolled the page and the data + pagination scrolled out of
            //    view, making them appear to "disappear".
            function fitWrapperToViewport(wrapper, opts) {
                if (!wrapper) return;
                const bottomGap = (opts && typeof opts.bottomGap === 'number') ? opts.bottomGap : 16;
                const minHeight = (opts && typeof opts.minHeight === 'number') ? opts.minHeight : 320;
                const rect = wrapper.getBoundingClientRect();
                const available = window.innerHeight - rect.top - bottomGap;
                wrapper.style.height = Math.max(minHeight, available) + 'px';
            }

            function fitAllIssuesTables() {
                const mainWrap = document.getElementById('all-issues-table-wrapper');
                fitWrapperToViewport(mainWrap);
                const historyCard = document.getElementById('holdIssueHistoryCard');
                if (historyCard && !historyCard.classList.contains('d-none')) {
                    fitWrapperToViewport(document.getElementById('all-issues-history-wrapper'));
                }
                if (table && typeof table.redraw === 'function') {
                    try { table.redraw(true); } catch (e) {}
                }
                if (historyTable && typeof historyTable.redraw === 'function') {
                    try { historyTable.redraw(true); } catch (e) {}
                }
            }

            let fitResizeTimer = null;
            function scheduleFitAllIssuesTables() {
                clearTimeout(fitResizeTimer);
                fitResizeTimer = setTimeout(fitAllIssuesTables, 80);
            }

            // ── Generic helpers ────────────────────────────────────────────────
            function escapeHtml(value) {
                const el = document.createElement('div');
                el.textContent = String(value ?? '');
                return el.innerHTML;
            }

            function escAttr(value) {
                return String(value ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
            }

            function dash(v) {
                const t = String(v ?? '').trim();
                return t === '' ? '—' : escapeHtml(t);
            }

            function money(n) {
                const v = Number(n) || 0;
                return '$' + v.toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function statusDot(has, tip) {
                const cls = has ? 'status-dot-available' : 'status-dot-missing';
                const t = String(tip ?? '').trim() || (has ? '' : 'No data');
                const title = t ? ' title="' + escAttr(t) + '"' : '';
                return '<span class="status-dot-indicator ' + cls + '"' + title + '></span>';
            }

            function setText(id, value) {
                const el = document.getElementById(id);
                if (el) el.textContent = value;
            }

            function parseDepartmentList(val) {
                if (val == null || val === '') return [];
                if (Array.isArray(val)) return val.map(x => String(x).trim()).filter(Boolean);
                const s = String(val).trim();
                if (!s) return [];
                if (s.startsWith('[')) {
                    try {
                        const j = JSON.parse(s);
                        return Array.isArray(j) ? j.map(x => String(x).trim()).filter(Boolean) : [];
                    } catch (e) {
                        return [];
                    }
                }
                return [s];
            }

            function rowDepartments(r) {
                if (Array.isArray(r.departments) && r.departments.length) return r.departments;
                return parseDepartmentList(r.department);
            }

            function linkHrefFromText(text) {
                const t = String(text || '').trim();
                if (!t) return '';
                if (/^https?:\/\//i.test(t)) return t;
                if (/^\/\//.test(t)) return 'https:' + t;
                return 'https://' + t;
            }

            // ── Tabulator cell formatters ──────────────────────────────────────
            const fmtImage = function(cell) {
                const url = cell.getValue();
                return url ? '<img src="' + escAttr(url) + '" class="sku-thumb" loading="lazy" alt="">' :
                    '<span class="sku-thumb-placeholder"><i class="bi bi-image"></i></span>';
            };
            const fmtSku = function(cell) {
                const d = cell.getData();
                const badge = d.group_id ?
                    ' <span class="badge bg-warning text-dark" style="font-size:.7rem;" title="Grouped entry (1 error)">G</span>' :
                    '';
                return '<span title="' + escAttr(d.sku) + '">' + escapeHtml(d.sku) + '</span>' + badge;
            };
            const fmtOrderNum = function(cell) {
                const v = String(cell.getValue() || '').trim();
                if (!v) return '—';
                return '<button class="copy-order-btn" data-copy="' + escAttr(v) + '" title="' + escAttr(v) +
                    '"><i class="bi bi-clipboard"></i></button>';
            };
            // Loss $ — column shows the Amazon listing price for that SKU
            // (from amazon_datsheets.price), so users can see the per-unit
            // dollar value at a glance. The price × qty total is in the
            // tooltip alongside it.
            //
            // Rounded to whole dollars in the cell (no cents) per request —
            // exact two-decimal values still appear in the hover tooltip.
            const fmtLoss = function(cell) {
                const d = cell.getData();
                const price = d.amz_price;
                if (price == null || isNaN(parseFloat(price))) return '—';
                return '$' + Math.round(Number(price)).toLocaleString();
            };
            const tooltipLoss = function(e, cell) {
                const d = cell.getData();
                const price = d.amz_price;
                const loss = d.amz_loss;
                if (price == null || isNaN(parseFloat(price))) {
                    return 'No Amazon price for this SKU';
                }
                const orderQty = d.order_qty;
                const qty = d.qty;
                const lossQty = (orderQty != null && !isNaN(parseFloat(orderQty)) && parseFloat(orderQty) > 0)
                    ? orderQty
                    : qty;
                const parts = ['Amazon price $' + Number(price).toFixed(2)];
                if (lossQty != null && !isNaN(parseFloat(lossQty))) {
                    parts.push('× qty ' + lossQty);
                }
                if (loss != null && !isNaN(parseFloat(loss))) {
                    parts.push('= total loss $' + Number(loss).toFixed(2));
                }
                return parts.join(' ');
            };
            const fmtWhatHappened = function(cell) {
                const t = String(cell.getValue() || '').trim();
                if (!t) return '—';
                if (t.toLowerCase() === '0 stock') return '<span class="what-happened-dot" title="0 Stock"></span>';
                if (t.toLowerCase() === 'damaged')
                return '<span class="what-happened-dot what-happened-dot-damaged" title="Damaged"></span>';
                return escapeHtml(t);
            };
            const fmtAction = function(cell) {
                const d = cell.getData();
                const a = String(d.action_1 || '').trim();
                const r = String(d.action_1_remark || '').trim();
                if (!a) return r ? escapeHtml(r) : '—';
                return r ? escapeHtml(a + ': ' + r) : escapeHtml(a);
            };
            const fmtTracking = function(cell) {
                const t = String(cell.getValue() || '').trim();
                if (!t) return '—';
                return '<span class="tracking-cell"><span class="tracking-dot">•</span>' +
                    '<span class="tracking-full">' + escapeHtml(t) + '</span>' +
                    '<button class="copy-tracking-btn" data-copy="' + escAttr(t) +
                    '" title="Copy tracking"><i class="bi bi-clipboard"></i></button></span>';
            };
            const fmtIssueImg = function(cell) {
                const u = String(cell.getValue() || '').trim();
                if (!u) return '—';
                return '<a href="' + escAttr(u) + '" target="_blank" rel="noopener"><img src="' + escAttr(u) +
                    '" class="issue-img-thumb" loading="lazy" alt=""></a>';
            };
            const fmtLink = function(cell) {
                const t = String(cell.getValue() || '').trim();
                if (!t) return '—';
                const href = linkHrefFromText(t);
                return '<a href="' + escAttr(href) +
                    '" target="_blank" rel="noopener noreferrer" class="issue-link-icon" title="' + escAttr(t) +
                    '"><i class="bi bi-link-45deg fs-5"></i></a>';
            };
            const fmtRootCause = function(cell) {
                const d = cell.getData();
                const root = String(d.issue || '').trim();
                const rmk = String(d.issue_remark || '').trim();
                const tip = (!root && !rmk) ? 'No data' : (!root ? rmk : (rmk ? root + ': ' + rmk : root));
                return statusDot(!!(root || rmk), tip);
            };
            const fmtRootCauseFixed = function(cell) {
                const d = cell.getData();
                const fx = String(d.c_action_1 || '').trim();
                const rmk = String(d.c_action_1_remark || '').trim();
                const tip = (!fx && !rmk) ? 'No data' : (!fx ? rmk : (rmk ? fx + ': ' + rmk : fx));
                return statusDot(!!(fx || rmk), tip);
            };
            // Show a status dot for the Instructions Item PKG column. Data is
            // joined server-side from product_master → instructions_item_pkg.
            // Green dot = packaging instructions exist for this SKU (full text
            // in the native tooltip). Red dot = no SKU match in product_master
            // or no instructions saved yet on the Dim Wt Master page.
            const fmtCtn = function(cell) {
                const d = cell.getData();
                const text = String(d.instructions_item_pkg || '').trim();
                if (!text) return statusDot(false, 'No Instructions item PKG saved for this SKU');
                return statusDot(true, text);
            };
            // Created At cell — shows a compact "D Mon" string (e.g. "1 Apr").
            // The full Pacific-time timestamp is exposed via the column's
            // `tooltip` callback (`tooltipCreatedAt`) below.
            const fmtCreatedAt = function(cell) {
                const d = cell.getData();
                const short = String(d.created_at_short || d.created_at_display || d.created_at || '').trim();
                if (!short) return '';
                const raw = d.created_at_raw ? new Date(String(d.created_at_raw).replace(' ', 'T')) : null;
                const stale = raw && !isNaN(raw.getTime()) && (Date.now() - raw.getTime()) > 14 * 24 * 60 * 60 *
                    1000;
                return stale ? '<span class="text-danger">' + escapeHtml(short) + '</span>' : escapeHtml(short);
            };

            // Tooltip for the Created At cell — full date + time in
            // America/Los_Angeles (Pacific), formatted server-side. Falls back
            // to the app-timezone string if the Pacific value is missing.
            const tooltipCreatedAt = function(e, cell) {
                const d = cell.getData();
                const pac = String(d.created_at_pacific || '').trim();
                if (pac) return pac + ' (Pacific)';
                return String(d.created_at_display || '').trim();
            };

            // Created By cell — truncate to 8 characters in the column.
            // The full name is still exposed via the column's `tooltip`
            // callback (the cell title attribute + Tabulator tooltip).
            const fmtCreatedBy = function(cell) {
                const raw = String(cell.getValue() ?? '').trim();
                if (raw === '') return dash('');
                const shown = raw.length > 8 ? raw.slice(0, 8) + '…' : raw;
                return escapeHtml(shown);
            };
            const tooltipCreatedBy = function(e, cell) {
                return String(cell.getValue() ?? '').trim();
            };

            // Derive a "D Mon" string (no year, no time) from whatever
            // timestamp fields the row carries. We try, in order:
            //   1) the server-provided `created_at_short` (e.g. "20 Jun"),
            //   2) parse "DD-MM-YYYY HH:MM" out of `created_at_display`,
            //   3) parse the raw DB timestamp `created_at_raw` as a Date.
            // This way the cell never falls back to showing the full year +
            // time, even if the server response is from a slightly older
            // cache that didn't include `created_at_short`.
            const SHORT_MONTH_NAMES = [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
            ];
            function shortDateFromRow(d) {
                const fromServer = String(d.created_at_short || '').trim();
                if (fromServer) return fromServer;
                const disp = String(d.created_at_display || '').trim();
                const m = disp.match(/^(\d{1,2})[-\/](\d{1,2})[-\/]\d{2,4}/);
                if (m) {
                    const day = parseInt(m[1], 10);
                    const monthIdx = parseInt(m[2], 10) - 1;
                    if (day > 0 && monthIdx >= 0 && monthIdx < 12) {
                        return day + ' ' + SHORT_MONTH_NAMES[monthIdx];
                    }
                }
                const raw = d.created_at_raw || d.created_at;
                if (raw) {
                    const ts = new Date(String(raw).replace(' ', 'T'));
                    if (!isNaN(ts.getTime())) {
                        return ts.getDate() + ' ' + SHORT_MONTH_NAMES[ts.getMonth()];
                    }
                }
                return '';
            }

            // Combined "Created By" cell — renders the user name (truncated to
            // 8 chars) on the first line and the short "D Mon" date underneath.
            // The hover tooltip is also combined: full name + full date+time
            // (with year) in Pacific timezone, separated by a newline.
            const fmtCreatedByCombo = function(cell) {
                const d = cell.getData();
                const rawName = String(d.created_by ?? '').trim();
                const nameShort = rawName === ''
                    ? '—'
                    : (rawName.length > 8 ? rawName.slice(0, 8) + '…' : rawName);
                const dateShort = shortDateFromRow(d);
                const rawTs = d.created_at_raw ? new Date(String(d.created_at_raw).replace(' ', 'T')) : null;
                const stale = rawTs && !isNaN(rawTs.getTime())
                    && (Date.now() - rawTs.getTime()) > 14 * 24 * 60 * 60 * 1000;
                const dateHtml = dateShort
                    ? (stale
                        ? '<div class="created-by-date text-danger">' + escapeHtml(dateShort) + '</div>'
                        : '<div class="created-by-date text-muted">' + escapeHtml(dateShort) + '</div>')
                    : '';
                return '<div class="created-by-combo">' +
                    '<div class="created-by-name">' + escapeHtml(nameShort) + '</div>' +
                    dateHtml +
                    '</div>';
            };
            const tooltipCreatedByCombo = function(e, cell) {
                const d = cell.getData();
                const name = String(d.created_by ?? '').trim();
                const pac = String(d.created_at_pacific || '').trim();
                const fallbackDate = String(d.created_at_display || '').trim();
                const parts = [];
                if (name) parts.push(name);
                if (pac) parts.push(pac + ' (Pacific)');
                else if (fallbackDate) parts.push(fallbackDate);
                return parts.join('\n');
            };
            const fmtLoggedAt = function(cell) {
                const disp = String(cell.getValue() || '').trim();
                return disp ? escapeHtml(disp) : '';
            };
            // Magnifier button rendered in the new "Details" column. Click handler
            // lives in the table-level cellClick listener (it filters by field name).
            const fmtDetails = function () {
                return '<button type="button" class="cb-row-btn cb-details" title="View all column data for this row" aria-label="View details">' +
                    '<i class="bi bi-search"></i>' +
                    '</button>';
            };

            // Build one "label : value" line for the read-only details modal.
            // `valueHtml` is inserted as raw HTML; pass already-escaped content
            // (or use buildDetailsRow with escapeHtml) — empty values render the
            // grey "—" placeholder so the layout is uniform.
            function buildDetailsRow(label, valueHtml, isEmpty) {
                const labelHtml = '<div class="ai-detail-label">' + escapeHtml(label) + '</div>';
                const valueClass = 'ai-detail-value' + (isEmpty ? ' empty' : '');
                const inner = isEmpty ? '—' : valueHtml;
                return '<div class="ai-detail-row">' + labelHtml +
                    '<div class="' + valueClass + '">' + inner + '</div></div>';
            }

            // Normalize → either an HTML-safe string or the empty marker.
            function detailsTextRow(label, raw) {
                const t = String(raw ?? '').trim();
                return buildDetailsRow(label, escapeHtml(t), t === '');
            }

            // Tracking-style row: copy button + monospace text.
            function detailsTrackingRow(label, raw) {
                const t = String(raw ?? '').trim();
                if (!t) return buildDetailsRow(label, '', true);
                const html = '<span class="tracking-cell" style="display:inline-flex;align-items:center;gap:6px;">' +
                    '<span class="tracking-full">' + escapeHtml(t) + '</span>' +
                    '<button class="copy-tracking-btn" data-copy="' + escAttr(t) +
                    '" title="Copy tracking"><i class="bi bi-clipboard"></i></button>' +
                    '</span>';
                return buildDetailsRow(label, html, false);
            }

            // Image row: shows a clickable thumbnail (open full size in new tab).
            function detailsImageRow(label, url) {
                const u = String(url ?? '').trim();
                if (!u) return buildDetailsRow(label, '', true);
                const html = '<a href="' + escAttr(u) + '" target="_blank" rel="noopener">' +
                    '<img src="' + escAttr(u) + '" class="ai-detail-thumb" loading="lazy" alt="">' +
                    '</a>';
                return buildDetailsRow(label, html, false);
            }

            // Reference link row: hyperlink + raw text fallback.
            function detailsLinkRow(label, raw) {
                const t = String(raw ?? '').trim();
                if (!t) return buildDetailsRow(label, '', true);
                const href = linkHrefFromText(t);
                const html = '<a href="' + escAttr(href) + '" target="_blank" rel="noopener noreferrer">' +
                    escapeHtml(t) + '</a>';
                return buildDetailsRow(label, html, false);
            }

            // Status field with green/red dot + the actual reason (root + remark, etc).
            function detailsStatusRow(label, value, remark) {
                const v = String(value ?? '').trim();
                const r = String(remark ?? '').trim();
                if (!v && !r) return buildDetailsRow(label, '', true);
                const dot = statusDot(true, '');
                const text = v ? (r ? v + ' — ' + r : v) : r;
                const html = '<span class="ai-detail-status-line">' + dot + escapeHtml(text) + '</span>';
                return buildDetailsRow(label, html, false);
            }

            // Render the body of the read-only details modal from a Tabulator row's
            // data object. Sections mirror the visible column groups so it's easy
            // to find a value here that is too truncated in the grid.
            function renderDetailsModalBody(d) {
                if (!d) return '<div class="text-muted">No data.</div>';

                const lossPrice = d.amz_price;
                const lossTotal = d.amz_loss;
                // Rounded to whole dollars to match the Loss $ column.
                const lossDisplay = (lossPrice == null || isNaN(parseFloat(lossPrice)))
                    ? ''
                    : ('$' + Math.round(Number(lossPrice)).toLocaleString() +
                        (lossTotal != null && !isNaN(parseFloat(lossTotal))
                            ? '  (total loss $' + Math.round(Number(lossTotal)).toLocaleString() + ')'
                            : ''));

                const action1 = String(d.action_1 || '').trim();
                const action1Remark = String(d.action_1_remark || '').trim();
                const actionDisplay = action1
                    ? (action1Remark ? action1 + ' — ' + action1Remark : action1)
                    : action1Remark;

                const departments = (Array.isArray(d.departments) && d.departments.length)
                    ? d.departments.join(', ')
                    : (function () {
                        const list = parseDepartmentList(d.department);
                        return list && list.length ? list.join(', ') : (d.department || '');
                    })();

                const createdAtDisplay = String(d.created_at_pacific || d.created_at_display || d.created_at || '').trim();

                const sections = [];

                // Order info
                sections.push(
                    '<div class="ai-detail-section">' +
                    '<div class="ai-detail-section-title">Order</div>' +
                    detailsTextRow('SKU', d.sku) +
                    detailsTextRow('Parent', d.parent) +
                    detailsTextRow('Order #', d.order_number) +
                    detailsTextRow('QTY', d.order_qty != null && d.order_qty !== '' ? d.order_qty : d.qty) +
                    detailsTextRow('Marketplace', d.marketplace_1) +
                    buildDetailsRow('Loss $', escapeHtml(lossDisplay), lossDisplay === '') +
                    '</div>'
                );

                // Issue + action
                sections.push(
                    '<div class="ai-detail-section">' +
                    '<div class="ai-detail-section-title">Issue</div>' +
                    detailsTextRow('Issue?', d.what_happened) +
                    buildDetailsRow('Action', escapeHtml(actionDisplay), actionDisplay === '') +
                    detailsStatusRow('Root Cause', d.issue, d.issue_remark) +
                    detailsStatusRow('Root Cause Fixed', d.c_action_1, d.c_action_1_remark) +
                    detailsStatusRow('Instr Pkg', d.instructions_item_pkg, '') +
                    '</div>'
                );

                // Tracking + media
                sections.push(
                    '<div class="ai-detail-section">' +
                    '<div class="ai-detail-section-title">Tracking &amp; media</div>' +
                    detailsTrackingRow('Tracking', d.tracking_number) +
                    detailsTrackingRow('Track R (replacement)', d.replacement_tracking) +
                    detailsImageRow('SKU image', d.image_url) +
                    detailsImageRow('Img 1', d.image_1_url) +
                    detailsImageRow('Img 2', d.image_2_url) +
                    detailsLinkRow('Link', d.issue_link) +
                    '</div>'
                );

                // Replacement / refund detail (only render fields that exist).
                const replacementParts = [];
                if (String(d.replacement_sku || '').trim() !== '') {
                    replacementParts.push(detailsTextRow('Replacement SKU', d.replacement_sku));
                }
                if (d.replacement_qty_sending != null && String(d.replacement_qty_sending).trim() !== '') {
                    replacementParts.push(detailsTextRow('Qty sending', d.replacement_qty_sending));
                }
                if (d.outgoing_needed != null && String(d.outgoing_needed).trim() !== '') {
                    const yn = String(d.outgoing_needed) === '1' || d.outgoing_needed === true ? 'Yes' : 'No';
                    replacementParts.push(detailsTextRow('Outgoing needed', yn));
                }
                if (d.refund_amount != null && String(d.refund_amount).trim() !== '') {
                    const amt = '$' + Number(d.refund_amount).toFixed(2);
                    replacementParts.push(buildDetailsRow('Refund amount', escapeHtml(amt), false));
                }
                if (String(d.refund_type || '').trim() !== '') {
                    replacementParts.push(detailsTextRow('Refund type', d.refund_type));
                }
                if (replacementParts.length) {
                    sections.push(
                        '<div class="ai-detail-section">' +
                        '<div class="ai-detail-section-title">Replacement / refund</div>' +
                        replacementParts.join('') +
                        '</div>'
                    );
                }

                // Wrong Item Sent — outgoing trigger (separate from the
                // Replacement outgoing above). Only render when at least one
                // field is populated so non-Wrong-Item issues don't show an
                // empty section.
                const wrongSentParts = [];
                if (String(d.wrong_sent_sku || '').trim() !== '') {
                    wrongSentParts.push(detailsTextRow('Wrongly sent SKU', d.wrong_sent_sku));
                }
                if (d.wrong_sent_qty != null && String(d.wrong_sent_qty).trim() !== '') {
                    wrongSentParts.push(detailsTextRow('Qty wrongly sent', d.wrong_sent_qty));
                }
                if (String(d.wrong_sent_reason || '').trim() !== '') {
                    wrongSentParts.push(detailsTextRow('Why it happened', d.wrong_sent_reason));
                }
                if (d.wrong_sent_outgoing_needed != null) {
                    const yn = String(d.wrong_sent_outgoing_needed) === '1' || d.wrong_sent_outgoing_needed === true ? 'Yes' : 'No';
                    if (yn === 'Yes' || String(d.wrong_sent_sku || '').trim() !== '') {
                        wrongSentParts.push(detailsTextRow('Outgoing needed (wrong item)', yn));
                    }
                }
                if (d.wrong_sent_outgoing_processed_at) {
                    wrongSentParts.push(detailsTextRow('Outgoing processed at', d.wrong_sent_outgoing_processed_at));
                }
                if (wrongSentParts.length) {
                    sections.push(
                        '<div class="ai-detail-section">' +
                        '<div class="ai-detail-section-title">Wrong Item Sent</div>' +
                        wrongSentParts.join('') +
                        '</div>'
                    );
                }

                // Audit
                sections.push(
                    '<div class="ai-detail-section">' +
                    '<div class="ai-detail-section-title">Department &amp; audit</div>' +
                    detailsTextRow('Department', departments) +
                    detailsTextRow('Created by', d.created_by) +
                    detailsTextRow('Created at', createdAtDisplay) +
                    detailsTextRow('Close note', d.close_note) +
                    detailsTextRow('Event', d.event_type) +
                    detailsTextRow('Logged at', d.logged_at_display) +
                    '</div>'
                );

                return sections.join('');
            }

            // Open the read-only details modal for a Tabulator row. Subtitle uses
            // SKU / order # / id (whichever is populated first) so the user can see
            // which row they're looking at.
            function openDetailsModal(rowData) {
                if (!rowData) return;
                const subtitleParts = [];
                if (rowData.sku) subtitleParts.push(String(rowData.sku));
                if (rowData.order_number) subtitleParts.push('Ord #' + String(rowData.order_number));
                if (rowData.id != null) subtitleParts.push('Row #' + String(rowData.id));
                const subtitle = subtitleParts.join('  ·  ');

                const subEl = document.getElementById('allIssuesDetailsModalSubtitle');
                if (subEl) subEl.textContent = subtitle;

                const body = document.getElementById('allIssuesDetailsModalBody');
                if (body) body.innerHTML = renderDetailsModalBody(rowData);

                const el = document.getElementById('allIssuesDetailsModal');
                if (!el) return;
                bootstrap.Modal.getOrCreateInstance(el).show();
            }

            const fmtActions = function() {
                let html =
                    '<div><button type="button" class="cb-row-btn cb-edit" title="Edit"><i class="bi bi-pencil-fill"></i></button>';
                if (currentUserEmail === 'president@5core.com') {
                    html +=
                        '<button type="button" class="cb-row-btn cb-danger cb-archive" title="Archive"><i class="bi bi-archive-fill"></i></button>';
                }
                return html + '</div>';
            };

            // Dept cell: one pill per department, stacked vertically.
            const fmtDept = function (cell) {
                const d = cell.getData();
                const list = (Array.isArray(d.departments) && d.departments.length)
                    ? d.departments
                    : parseDepartmentList(d.department);
                if (!list || !list.length) return '—';
                return '<div class="dept-stack">' +
                    list.map(function (x) {
                        return '<span class="dept-pill">' + escapeHtml(x) + '</span>';
                    }).join('') +
                    '</div>';
            };

            // ── Tabulator setup ────────────────────────────────────────────────
            const mainColumns = [{
                    title: '#',
                    field: 'id',
                    width: 55
                },
                {
                    title: '',
                    field: 'image_url',
                    width: 56,
                    formatter: fmtImage,
                    headerSort: false
                },
                // SKU column auto-fits to the widest SKU in the current page
                // (fitDataStretch layout sizes unset-width columns to data).
                // minWidth prevents short SKUs from collapsing the header.
                {
                    title: 'SKU',
                    field: 'sku',
                    minWidth: 130,
                    formatter: fmtSku,
                    cssClass: 'ai-sku-cell'
                },
                {
                    title: 'Ord',
                    field: 'order_number',
                    width: 60,
                    formatter: fmtOrderNum,
                    hozAlign: 'center'
                },
                {
                    title: 'Loss $',
                    field: 'amz_price',
                    width: 70,
                    formatter: fmtLoss,
                    tooltip: tooltipLoss
                },
                {
                    title: 'QTY',
                    field: 'order_qty',
                    width: 60,
                    formatter: function(c) {
                        return dash(c.getValue());
                    }
                },
                {
                    title: 'MKT',
                    field: 'marketplace_1',
                    width: 90,
                    formatter: function(c) {
                        return dash(c.getValue());
                    }
                },
                {
                    title: 'Issue?',
                    field: 'what_happened',
                    width: 140,
                    minWidth: 100,
                    formatter: fmtWhatHappened,
                    hozAlign: 'center',
                    variableHeight: true
                },
                {
                    title: 'Action',
                    field: 'action_1',
                    width: 130,
                    formatter: fmtAction
                },
                // Read-only "view all column data" column. Click the magnifier
                // to open a modal that lists every relevant field for the row,
                // including images and tracking values truncated in the grid.
                // Placed right after the Action column for quick access.
                {
                    title: 'Details',
                    field: '_details',
                    width: 70,
                    formatter: fmtDetails,
                    headerSort: false,
                    hozAlign: 'center'
                },
                // Hidden by default — the values are surfaced in the Details
                // modal (magnifier in the column right after Action). Users
                // can re-enable any of these from the "Columns" dropdown.
                {
                    title: 'Tracking',
                    field: 'tracking_number',
                    width: 80,
                    formatter: fmtTracking,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'Track R',
                    field: 'replacement_tracking',
                    width: 80,
                    formatter: fmtTracking,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'Img 1',
                    field: 'image_1_url',
                    width: 60,
                    formatter: fmtIssueImg,
                    headerSort: false,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'Img 2',
                    field: 'image_2_url',
                    width: 60,
                    formatter: fmtIssueImg,
                    headerSort: false,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'Link',
                    field: 'issue_link',
                    width: 50,
                    formatter: fmtLink,
                    headerSort: false,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'Root Cause',
                    field: 'issue',
                    width: 60,
                    formatter: fmtRootCause,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'Instr Pkg',
                    field: '_ctn',
                    width: 60,
                    formatter: fmtCtn,
                    headerSort: false,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'RC Fixed',
                    field: 'c_action_1',
                    width: 60,
                    formatter: fmtRootCauseFixed,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'Dept',
                    field: 'department',
                    width: 130,
                    variableHeight: true,
                    formatter: fmtDept,
                },
                // Combined Created By + Created At column. The cell shows the
                // user name (truncated) on top and the short date below; the
                // hover tooltip shows the full name + full Pacific timestamp.
                {
                    title: 'Created By',
                    field: 'created_by',
                    width: 88,
                    formatter: fmtCreatedByCombo,
                    tooltip: tooltipCreatedByCombo
                },
                // Action buttons (edit + archive) — pinned to the right edge
                // so they remain visible regardless of horizontal scroll.
                {
                    title: 'Close',
                    field: '_actions',
                    width: 10,
                    minWidth: 10,
                    formatter: fmtActions,
                    headerSort: false,
                    hozAlign: 'center',
                   
                },
            ];

            const historyColumns = [{
                    title: 'Ref',
                    field: 'issue_ref',
                    width: 70
                },
                {
                    title: '',
                    field: 'image_url',
                    width: 56,
                    formatter: fmtImage,
                    headerSort: false
                },
                // SKU column auto-fits to the widest SKU in the current page.
                {
                    title: 'SKU',
                    field: 'sku',
                    minWidth: 130,
                    formatter: fmtSku,
                    cssClass: 'ai-sku-cell'
                },
                {
                    title: 'Ord',
                    field: 'order_number',
                    width: 60,
                    formatter: fmtOrderNum,
                    hozAlign: 'center'
                },
                {
                    title: 'QTY',
                    field: 'order_qty',
                    width: 60,
                    formatter: function(c) {
                        return dash(c.getValue());
                    }
                },
                {
                    title: 'MKT',
                    field: 'marketplace_1',
                    width: 90,
                    formatter: function(c) {
                        return dash(c.getValue());
                    }
                },
                {
                    title: 'Issue?',
                    field: 'what_happened',
                    width: 140,
                    minWidth: 100,
                    formatter: fmtWhatHappened,
                    hozAlign: 'center',
                    variableHeight: true
                },
                {
                    title: 'Action',
                    field: 'action_1',
                    width: 130,
                    formatter: fmtAction
                },
                // Read-only details modal — same magnifier as the main grid.
                // Placed right after the Action column for quick access.
                {
                    title: 'Details',
                    field: '_details',
                    width: 70,
                    formatter: fmtDetails,
                    headerSort: false,
                    hozAlign: 'center'
                },
                // Hidden by default — values are visible via the Details
                // magnifier modal. Users can re-enable from the Columns menu.
                {
                    title: 'Tracking',
                    field: 'tracking_number',
                    width: 80,
                    formatter: fmtTracking,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'Track R',
                    field: 'replacement_tracking',
                    width: 80,
                    formatter: fmtTracking,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'Img 1',
                    field: 'image_1_url',
                    width: 60,
                    formatter: fmtIssueImg,
                    headerSort: false,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'Img 2',
                    field: 'image_2_url',
                    width: 60,
                    formatter: fmtIssueImg,
                    headerSort: false,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'Link',
                    field: 'issue_link',
                    width: 50,
                    formatter: fmtLink,
                    headerSort: false,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'Root Cause',
                    field: 'issue',
                    width: 60,
                    formatter: fmtRootCause,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'Instr Pkg',
                    field: '_ctn',
                    width: 60,
                    formatter: fmtCtn,
                    headerSort: false,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'RC Fixed',
                    field: 'c_action_1',
                    width: 60,
                    formatter: fmtRootCauseFixed,
                    hozAlign: 'center',
                    visible: false
                },
                {
                    title: 'Dept',
                    field: 'department',
                    width: 130,
                    variableHeight: true,
                    formatter: fmtDept,
                },
                {
                    title: 'Close',
                    field: 'close_note',
                    width: 200,
                    minWidth: 140,
                    maxWidth: 260,
                    variableHeight: true,
                    formatter: function(c) {
                        return dash(c.getValue());
                    }
                },
                {
                    title: 'Event',
                    field: 'event_type',
                    width: 80,
                    formatter: function(c) {
                        return dash(c.getValue());
                    }
                },
                {
                    title: 'Created By',
                    field: 'created_by',
                    width: 72,
                    formatter: fmtCreatedBy,
                    tooltip: tooltipCreatedBy
                },
                {
                    title: 'Logged At',
                    field: 'logged_at_display',
                    width: 120,
                    formatter: fmtLoggedAt
                },
            ];

            function handleCopyClick(e) {
                const copyBtn = e.target.closest('.copy-order-btn, .copy-tracking-btn');
                if (!copyBtn) return false;
                const text = copyBtn.getAttribute('data-copy') || '';
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function() {
                        copyBtn.classList.add('copied');
                        copyBtn.innerHTML = '<i class="bi bi-clipboard-check"></i>';
                        setTimeout(function() {
                            copyBtn.classList.remove('copied');
                            copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>';
                        }, 1200);
                    });
                }
                return true;
            }

            // Custom loader overlays (overallAmazon-style: fetch first, then construct grid).
            function showMainLoader() {
                document.getElementById('ai-main-loader')?.classList.remove('d-none');
            }

            function hideMainLoader() {
                document.getElementById('ai-main-loader')?.classList.add('d-none');
            }

            function showHistoryLoader() {
                document.getElementById('ai-history-loader')?.classList.remove('d-none');
            }

            function hideHistoryLoader() {
                document.getElementById('ai-history-loader')?.classList.add('d-none');
            }

            // Build the main Tabulator with already-fetched rows (matches overallAmazon pattern).
            function buildMainTabulator(rows) {
                table = new Tabulator('#all-issues-tabulator', {
                    data: rows,
                    layout: 'fitDataStretch',
                    height: '100%',
                    placeholder: 'No issues found.',
                    index: 'id',
                    pagination: true,
                    paginationSize: 50,
                    paginationSizeSelector: [25, 50, 100, 200],
                    paginationCounter: 'rows',
                    columnDefaults: {
                        tooltip: true
                    },
                    columns: mainColumns,
                });

                table.on('cellClick', function(e, cell) {
                    if (handleCopyClick(e)) return;
                    const field = cell.getField();
                    if (field === '_details') {
                        const btn = e.target.closest('button');
                        if (!btn) return;
                        openDetailsModal(cell.getRow().getData());
                        return;
                    }
                    if (field !== '_actions') return;
                    const btn = e.target.closest('button');
                    if (!btn) return;
                    const data = cell.getRow().getData();
                    if (btn.classList.contains('cb-edit')) {
                        openEditModal(data);
                    } else if (btn.classList.contains('cb-archive')) {
                        archiveRecord(data.id);
                    }
                });

                // After first render, schedule non-critical work without blocking.
                table.on('tableBuilt', function() {
                    applyFilters();
                    // Re-fit the wrapper now that the header is measured so the
                    // pagination footer is guaranteed to sit inside the viewport.
                    fitAllIssuesTables();
                    const idle = window.requestIdleCallback || function(fn) {
                        return setTimeout(fn, 1);
                    };
                    idle(function() {
                        loadColumnVisibility();
                    });
                    idle(function() {
                        loadL30Loss();
                    });
                    idle(function() {
                        loadL30Issues();
                    });
                });
            }

            // Build the history Tabulator with already-fetched rows.
            function buildHistoryTabulator(rows) {
                historyTable = new Tabulator('#all-issues-history-tabulator', {
                    data: rows,
                    layout: 'fitDataStretch',
                    height: '100%',
                    placeholder: 'No history found.',
                    index: 'id',
                    pagination: true,
                    paginationSize: 50,
                    paginationSizeSelector: [25, 50, 100],
                    paginationCounter: 'rows',
                    columnDefaults: {
                        tooltip: true
                    },
                    columns: historyColumns,
                });
                historyTable.on('cellClick', function(e, cell) {
                    if (handleCopyClick(e)) return;
                    if (cell && cell.getField && cell.getField() === '_details') {
                        const btn = e.target.closest('button');
                        if (!btn) return;
                        openDetailsModal(cell.getRow().getData());
                    }
                });
                historyTable.on('tableBuilt', function() {
                    fitAllIssuesTables();
                });
            }

            // ── Column visibility (persisted per user) ─────────────────────────
            function buildColumnsMenu() {
                const menu = document.getElementById('ai-columns-menu');
                if (!menu) return;
                const labels = {
                    image_url: 'Image',
                    _ctn: 'Instr Pkg',
                    _actions: 'Close',
                    _details: 'Details',
                    created_at_display: 'Created At'
                };
                menu.innerHTML = '';
                table.getColumns().forEach(function(col) {
                    const field = col.getField();
                    if (!field) return;
                    const def = col.getDefinition();
                    const title = labels[field] || (def.title && def.title.trim() ? def.title : field);
                    const id = 'ai-col-' + field;
                    const wrap = document.createElement('div');
                    wrap.className = 'form-check';
                    wrap.innerHTML = '<input class="form-check-input" type="checkbox" id="' + id + '"' + (col
                            .isVisible() ? ' checked' : '') + '>' +
                        '<label class="form-check-label" for="' + id + '">' + escapeHtml(title) + '</label>';
                    wrap.querySelector('input').addEventListener('change', function() {
                        if (this.checked) {
                            col.show();
                        } else {
                            col.hide();
                        }
                        saveColumnVisibility();
                    });
                    menu.appendChild(wrap);
                });
            }
            async function loadColumnVisibility() {
                try {
                    const res = await fetch(colVisGet + '?channel=' + encodeURIComponent(COLVIS_CHANNEL), {
                        headers: getHeaders
                    });
                    const map = await res.json();
                    if (map && typeof map === 'object') {
                        table.getColumns().forEach(function(col) {
                            const f = col.getField();
                            if (!f || !(f in map)) return;
                            if (map[f]) {
                                col.show();
                            } else {
                                col.hide();
                            }
                        });
                    }
                } catch (e) {
                    /* ignore */ }
                buildColumnsMenu();
            }
            async function saveColumnVisibility() {
                const visibility = {};
                table.getColumns().forEach(function(col) {
                    const f = col.getField();
                    if (f) visibility[f] = col.isVisible();
                });
                try {
                    await fetch(colVisSet, {
                        method: 'POST',
                        headers: jsonHeaders,
                        body: JSON.stringify({
                            channel: COLVIS_CHANNEL,
                            visibility: visibility
                        })
                    });
                } catch (e) {
                    /* ignore */ }
            }

            // ── Data normalization ─────────────────────────────────────────────
            function normalizeRecord(row) {
                return {
                    id: row?.id ?? null,
                    sku: row?.sku ?? '',
                    image_url: row?.image_url ?? null,
                    qty: row?.qty ?? 0,
                    order_qty: row?.order_qty ?? '',
                    parent: row?.parent ?? '',
                    group_id: row?.group_id ?? null,
                    marketplace_1: row?.marketplace_1 ?? '',
                    marketplace_2: row?.marketplace_2 ?? '',
                    what_happened: row?.what_happened ?? '',
                    issue: row?.issue ?? '',
                    issue_remark: row?.issue_remark ?? '',
                    action_1: row?.action_1 ?? '',
                    action_1_remark: row?.action_1_remark ?? '',
                    tracking_number: row?.tracking_number ?? '',
                    issue_link: row?.issue_link ?? '',
                    replacement_tracking: row?.replacement_tracking ?? '',
                    image_1_url: row?.image_1_url ?? null,
                    image_2_url: row?.image_2_url ?? null,
                    c_action_1: row?.c_action_1 ?? '',
                    c_action_1_remark: row?.c_action_1_remark ?? '',
                    close_note: row?.close_note ?? '',
                    department: row?.department ?? '',
                    departments: Array.isArray(row?.departments) ? row.departments : parseDepartmentList(row
                        ?.department),
                    created_by: row?.created_by ?? 'System',
                    created_at_raw: row?.created_at ?? '',
                    created_at_display: row?.created_at_display ?? row?.created_at ?? '',
                    // Compact date string ("20 Jun") + Pacific-timezone full
                    // timestamp used by the combined Created By cell + tooltip.
                    created_at_short: row?.created_at_short ?? '',
                    created_at_pacific: row?.created_at_pacific ?? '',
                    order_number: row?.order_number ?? '',
                    total_loss: row?.total_loss ?? null,
                    // Amazon datasheet price + derived total loss used by the
                    // Loss $ column. These come from buildAmazonPriceMap() on
                    // the server; they MUST be carried through normalizeRecord
                    // or the cell silently falls back to "—".
                    amz_price: row?.amz_price ?? null,
                    amz_loss: row?.amz_loss ?? null,
                    // Instructions item PKG text used by the Instr Pkg dot
                    // formatter (server-joined from product_master →
                    // instructions_item_pkg).
                    instructions_item_pkg: row?.instructions_item_pkg ?? null,
                    refund_type: row?.refund_type ?? '',
                    refund_amount: row?.refund_amount ?? null,
                    replacement_sku: row?.replacement_sku ?? '',
                    replacement_qty_sending: row?.replacement_qty_sending ?? '',
                    outgoing_needed: !!row?.outgoing_needed,
                    outgoing_warehouse_id: row?.outgoing_warehouse_id ?? '',
                    outgoing_processed_at: row?.outgoing_processed_at ?? null,
                    outgoing_inventory_id: row?.outgoing_inventory_id ?? null,
                    wrong_sent_sku: row?.wrong_sent_sku ?? '',
                    issue_notes: row?.issue_notes ?? '',
                    qty_mismatch_type: row?.qty_mismatch_type ?? '',
                    qty_sent: row?.qty_sent ?? '',
                    qty_ordered: row?.qty_ordered ?? '',
                    // Wrong Item Sent → outgoing trigger sub-fields. Mirror the
                    // Replacement outgoing_* shape so the modal can prefill +
                    // lock the checkbox after a successful Shopify deduction.
                    wrong_sent_qty: row?.wrong_sent_qty ?? '',
                    wrong_sent_outgoing_needed: !!row?.wrong_sent_outgoing_needed,
                    wrong_sent_outgoing_warehouse_id: row?.wrong_sent_outgoing_warehouse_id ?? '',
                    wrong_sent_outgoing_processed_at: row?.wrong_sent_outgoing_processed_at ?? null,
                    wrong_sent_outgoing_inventory_id: row?.wrong_sent_outgoing_inventory_id ?? null,
                    // "Why it happened" dropdown value (built-in or custom).
                    wrong_sent_reason: row?.wrong_sent_reason ?? '',
                };
            }

            function normalizeHistoryRecord(row) {
                return {
                    id: row?.id ?? null,
                    orders_on_hold_issue_id: row?.orders_on_hold_issue_id ?? null,
                    issue_ref: row?.issue_ref ?? (row?.orders_on_hold_issue_id ?? row?.id),
                    event_type: row?.event_type ?? '',
                    sku: row?.sku ?? '',
                    image_url: row?.image_url ?? null,
                    order_qty: row?.order_qty ?? '',
                    parent: row?.parent ?? '',
                    marketplace_1: row?.marketplace_1 ?? '',
                    what_happened: row?.what_happened ?? '',
                    issue: row?.issue ?? '',
                    issue_remark: row?.issue_remark ?? '',
                    action_1: row?.action_1 ?? '',
                    action_1_remark: row?.action_1_remark ?? '',
                    tracking_number: row?.tracking_number ?? '',
                    issue_link: row?.issue_link ?? '',
                    replacement_tracking: row?.replacement_tracking ?? '',
                    image_1_url: row?.image_1_url ?? null,
                    image_2_url: row?.image_2_url ?? null,
                    c_action_1: row?.c_action_1 ?? '',
                    c_action_1_remark: row?.c_action_1_remark ?? '',
                    close_note: row?.close_note ?? '',
                    department: row?.department ?? '',
                    created_by: row?.created_by ?? 'System',
                    order_number: row?.order_number ?? '',
                    logged_at_display: row?.logged_at_display ?? row?.logged_at ?? '',
                    // Carry through history-table server fields so the same
                    // Instr Pkg column / combined Created By cell work there.
                    instructions_item_pkg: row?.instructions_item_pkg ?? null,
                };
            }

            // ── Department filter + search ─────────────────────────────────────
            function rowMatchesActiveDeptFilter(r) {
                if (!activeDeptFilter) return true;
                const needle = String(activeDeptFilter).trim().toLowerCase();
                return rowDepartments(r).some(d => String(d).trim().toLowerCase() === needle);
            }

            function getFilteredRows() {
                if (!activeDeptFilter) return holdIssueRows;
                return holdIssueRows.filter(rowMatchesActiveDeptFilter);
            }

            function applyFilters() {
                if (!table) {
                    updateTotalCount();
                    return;
                }
                const term = (document.getElementById('ai-search')?.value || '').trim().toLowerCase();
                table.setFilter(function(row) {
                    if (activeDeptFilter) {
                        const needle = activeDeptFilter.toLowerCase();
                        if (!rowDepartments(row).some(d => String(d).trim().toLowerCase() === needle))
                        return false;
                    }
                    if (term) {
                        return Object.values(row).some(v => v !== null && v !== undefined && String(v)
                            .toLowerCase().includes(term));
                    }
                    return true;
                });
                updateTotalCount();
            }

            function updateTotalCount() {
                const filtered = getFilteredRows();
                const seenGroups = new Set();
                let errorCount = 0;
                filtered.forEach(r => {
                    if (r.group_id) {
                        if (!seenGroups.has(r.group_id)) {
                            seenGroups.add(r.group_id);
                            errorCount++;
                        }
                    } else {
                        errorCount++;
                    }
                });
                setText('hold_issue_total_count', String(errorCount));
            }

            function buildDeptFilters() {
                const sel = document.getElementById('dept-filter-select');
                if (!sel) return;
                const counts = {};
                holdIssueRows.forEach(r => {
                    rowDepartments(r).forEach(d => {
                        if (d) counts[d] = (counts[d] || 0) + 1;
                    });
                });
                const depts = Object.keys(counts).sort();
                const prev = sel.value !== '' ? sel.value : (activeDeptFilter || '');
                sel.innerHTML = '<option value="">All Departments (' + holdIssueRows.length + ')</option>';
                depts.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d;
                    opt.textContent = d + ' (' + counts[d] + ')';
                    if (d === prev) opt.selected = true;
                    sel.appendChild(opt);
                });
                if (prev && !Object.prototype.hasOwnProperty.call(counts, prev)) {
                    activeDeptFilter = null;
                    sel.value = '';
                } else if (prev) {
                    activeDeptFilter = prev;
                    sel.value = prev;
                }
            }

            // Fetch main rows from the server, then either build the Tabulator or
            // refresh its data via replaceData() (overallAmazon-style flow).
            async function loadHoldIssueRows() {
                showMainLoader();
                try {
                    const res = await fetch(recordsListUrl, {
                        headers: getHeaders
                    });
                    const data = await res.json();
                    holdIssueRows = Array.isArray(data?.data) ? data.data.map(normalizeRecord) : [];
                } catch (e) {
                    holdIssueRows = [];
                }
                buildDeptFilters();
                if (table) {
                    table.replaceData(holdIssueRows);
                    applyFilters();
                } else {
                    buildMainTabulator(holdIssueRows);
                }
                hideMainLoader();
            }

            // Fetch history rows on demand; build the history Tabulator the first time.
            async function loadHoldIssueHistoryRows() {
                showHistoryLoader();
                try {
                    const res = await fetch(historyListUrl, {
                        headers: getHeaders
                    });
                    const data = await res.json();
                    holdIssueHistoryRows = Array.isArray(data?.data) ? data.data.map(normalizeHistoryRecord) : [];
                } catch (e) {
                    holdIssueHistoryRows = [];
                }
                if (historyTable) {
                    historyTable.replaceData(holdIssueHistoryRows);
                } else {
                    buildHistoryTabulator(holdIssueHistoryRows);
                }
                setText('hold_issue_history_total_count', String(holdIssueHistoryRows.length));
                hideHistoryLoader();
            }

            // ── Modal element refs ─────────────────────────────────────────────
            const form = document.getElementById('ordersOnHoldIssueForm');
            const alertBox = document.getElementById('ordersOnHoldIssueAlert');
            const modalEl = document.getElementById('ordersOnHoldIssueModal');
            const skuInput = document.getElementById('hold_issue_sku');
            const skuDatalist = document.getElementById('hold_issue_sku_datalist');
            const skuImageWrap = document.getElementById('hold_issue_sku_image_wrap');
            const skuImage = document.getElementById('hold_issue_sku_image');
            const qtyInput = document.getElementById('hold_issue_qty');
            const orderQtyInput = document.getElementById('hold_issue_order_qty');
            const parentInput = document.getElementById('hold_issue_parent');
            const marketplace1Input = document.getElementById('hold_issue_marketplace_1');
            const whatHappenedInput = document.getElementById('hold_issue_what_happened');
            const issueInput = document.getElementById('hold_issue_text');
            const issueRemarkInput = document.getElementById('hold_issue_remark');
            const rootCauseRemarkWrap = document.getElementById('rootCauseRemarkWrap');
            const action1Input = document.getElementById('hold_issue_action_1');
            const action1RemarkInput = document.getElementById('hold_issue_action_1_remark');
            const trackingNumberInput = document.getElementById('hold_issue_tracking_number');
            const issueLinkInput = document.getElementById('hold_issue_issue_link');
            const replacementTrackingInput = document.getElementById('hold_issue_replacement_tracking');
            const cAction1Input = document.getElementById('hold_issue_c_action_1');
            const cAction1RemarkInput = document.getElementById('hold_issue_c_action_1_remark');
            const cAction1RemarkWrap = document.getElementById('cAction1RemarkWrap');
            const departmentInput = document.getElementById('hold_issue_department');

            function showAlert(message, type) {
                alertBox.textContent = message;
                alertBox.classList.remove('alert-danger', 'alert-success');
                alertBox.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
                alertBox.classList.remove('d-none');
            }

            function hideAlert() {
                alertBox.classList.add('d-none');
                alertBox.textContent = '';
            }

            // ── Department multiselect ─────────────────────────────────────────
            function getDepartmentPayload() {
                if (!departmentInput) return [];
                return Array.from(departmentInput.selectedOptions || []).map(o => o.value.trim()).filter(Boolean);
            }

            function updateDepartmentLabel() {
                const label = document.getElementById('hold_issue_department_label');
                if (!label || !departmentInput) return;
                const selected = Array.from(departmentInput.selectedOptions).map(o => o.textContent.trim()).filter(
                    Boolean);
                if (!selected.length) {
                    label.textContent = 'Select department(s)';
                    label.classList.add('text-muted');
                } else {
                    label.textContent = selected.join(', ');
                    label.classList.remove('text-muted');
                }
            }

            function syncDepartmentDropdown() {
                const menu = document.getElementById('hold_issue_department_menu');
                if (!menu || !departmentInput) {
                    updateDepartmentLabel();
                    return;
                }
                const selected = Array.from(departmentInput.selectedOptions).map(o => o.value);
                menu.querySelectorAll('.qc-dept-checkbox').forEach(cb => {
                    cb.checked = selected.includes(cb.value);
                });
                updateDepartmentLabel();
            }

            function buildDepartmentDropdown() {
                if (!departmentInput) return;
                const menu = document.getElementById('hold_issue_department_menu');
                if (!menu) return;
                menu.innerHTML = Array.from(departmentInput.options).map(o =>
                    '<label class="dropdown-item d-flex align-items-center gap-2 px-2 py-1 mb-0" style="cursor:pointer;">' +
                    '<input type="checkbox" class="form-check-input m-0 qc-dept-checkbox" value="' + escAttr(o
                        .value) + '">' +
                    '<span>' + escapeHtml(o.textContent) + '</span></label>'
                ).join('');
                menu.querySelectorAll('.qc-dept-checkbox').forEach(cb => {
                    cb.addEventListener('change', () => {
                        const opt = Array.from(departmentInput.options).find(o => o.value === cb.value);
                        if (opt) opt.selected = cb.checked;
                        updateDepartmentLabel();
                    });
                });
                syncDepartmentDropdown();
            }

            function setDepartmentMultiSelect(record) {
                if (!departmentInput) return;
                const depts = rowDepartments(record);
                Array.from(departmentInput.options).forEach(o => {
                    o.selected = depts.includes(o.value);
                });
                syncDepartmentDropdown();
            }

            function clearDepartmentMultiSelect() {
                if (!departmentInput) return;
                Array.from(departmentInput.options).forEach(o => {
                    o.selected = false;
                });
                syncDepartmentDropdown();
            }

            // ── Conditional "Other" remark fields ──────────────────────────────
            function toggleRootCauseRemarkField() {
                const isOther = String(issueInput?.value || '').trim() === 'Other';
                if (rootCauseRemarkWrap) rootCauseRemarkWrap.classList.toggle('d-none', !isOther);
                if (issueRemarkInput) {
                    issueRemarkInput.required = isOther;
                    if (!isOther) issueRemarkInput.value = '';
                }
            }

            function toggleAction1RemarkField() {
                const isOther = String(action1Input?.value || '').trim() === 'Other';
                const star = document.getElementById('action1RemarkRequiredStar');
                if (star) star.classList.toggle('d-none', !isOther);
                if (action1RemarkInput) {
                    action1RemarkInput.required = isOther;
                    if (!isOther) action1RemarkInput.setCustomValidity('');
                }
            }

            // ── Action sub-sections (Refund / Replacement / Alternate Sent / Other) ──
            // Returns the canonical sub-section key for a given Action value,
            // or '' if no special sub-section applies.
            function actionSubsectionKey(actionValue) {
                const v = String(actionValue || '').trim().toLowerCase();
                if (v === 'refund') return 'refund';
                if (v === 'replacement') return 'replacement';
                if (v === 'alternate sent') return 'replacement'; // shares the same form
                if (v === 'other') return 'other';
                return '';
            }

            function clearReplacementSubsection() {
                const skuInp = document.getElementById('hold_issue_replacement_sku');
                const qtyInp = document.getElementById('hold_issue_replacement_qty_sending');
                const trkInp = document.getElementById('hold_issue_replacement_tracking_30');
                const outChk = document.getElementById('hold_issue_outgoing_needed');
                const whInp  = document.getElementById('hold_issue_outgoing_warehouse_id');
                const whWrap = document.getElementById('outgoingWarehouseWrap');
                const notice = document.getElementById('outgoingProcessedNotice');
                const preview = document.getElementById('replacementSkuPreview');
                const qtyAv = document.getElementById('replacementQtyAvailable');
                const img = document.getElementById('replacementSkuImage');
                if (skuInp) skuInp.value = '';
                if (qtyInp) qtyInp.value = '';
                if (trkInp) trkInp.value = '';
                if (outChk) { outChk.checked = false; outChk.disabled = false; }
                if (whInp) whInp.value = '';
                if (whWrap) whWrap.classList.add('d-none');
                if (notice) notice.classList.add('d-none');
                if (preview) preview.classList.add('d-none');
                if (qtyAv) qtyAv.textContent = '—';
                if (img) img.setAttribute('src', '');
            }

            // Show/hide the warehouse picker when "Outgoing needed?" is toggled.
            function toggleOutgoingWarehouseVisibility() {
                const checked = !!document.getElementById('hold_issue_outgoing_needed')?.checked;
                document.getElementById('outgoingWarehouseWrap')?.classList.toggle('d-none', !checked);
            }

            function clearRefundSubsection() {
                document.getElementsByName('refund_type').forEach
                    ? Array.from(document.getElementsByName('refund_type')).forEach(r => { r.checked = false; })
                    : null;
                Array.from(document.getElementsByName('refund_type')).forEach(r => { r.checked = false; });
                const amt = document.getElementById('hold_issue_refund_amount');
                if (amt) amt.value = '';
            }

            function clearOtherSubsection() {
                const ta = document.getElementById('hold_issue_other_notes');
                if (ta) ta.value = '';
            }

            function toggleActionSubsection() {
                const key = actionSubsectionKey(action1Input?.value);
                const wrap = document.getElementById('actionSubsection');
                const refund = document.getElementById('refundSubsection');
                const repl = document.getElementById('replacementSubsection');
                const other = document.getElementById('otherSubsection');
                const replTitle = document.getElementById('replacementSubsectionTitle');

                if (!wrap) return;

                // Show the wrapper only if any sub-section is active.
                wrap.classList.toggle('d-none', !key);
                refund?.classList.toggle('d-none', key !== 'refund');
                repl?.classList.toggle('d-none', key !== 'replacement');
                other?.classList.toggle('d-none', key !== 'other');

                // Customise the Replacement panel title for "Alternate Sent".
                if (replTitle) {
                    const action = String(action1Input?.value || '').trim();
                    replTitle.textContent = (action.toLowerCase() === 'alternate sent')
                        ? 'Alternate Sent details'
                        : 'Replacement details';
                }

                // Wipe data from any sub-section that's no longer active so we
                // don't accidentally save stale values from a previous selection.
                if (key !== 'refund') clearRefundSubsection();
                if (key !== 'replacement') clearReplacementSubsection();
                if (key !== 'other') clearOtherSubsection();
            }

            // Replacement / Alternate Sent SKU lookup: image + Shopify available qty.
            let replacementSkuTimer = null;
            async function fillReplacementSkuDetails() {
                const skuInp = document.getElementById('hold_issue_replacement_sku');
                const preview = document.getElementById('replacementSkuPreview');
                const qtyAv = document.getElementById('replacementQtyAvailable');
                const img = document.getElementById('replacementSkuImage');
                if (!skuInp) return;
                const sku = skuInp.value.trim();
                if (!sku) {
                    preview?.classList.add('d-none');
                    if (qtyAv) qtyAv.textContent = '—';
                    if (img) img.setAttribute('src', '');
                    return;
                }
                try {
                    const res = await fetch(replacementSkuDetailsUrl + '?sku=' + encodeURIComponent(sku), {
                        headers: getHeaders,
                    });
                    const data = await res.json();
                    if (!res.ok || !data.found) {
                        preview?.classList.add('d-none');
                        if (qtyAv) qtyAv.textContent = '—';
                        if (img) img.setAttribute('src', '');
                        return;
                    }
                    if (img && data.image_url) img.setAttribute('src', data.image_url);
                    if (qtyAv) qtyAv.textContent = (data.qty_available != null) ? Number(data.qty_available) : '—';
                    preview?.classList.remove('d-none');
                } catch (e) { /* ignore */ }
            }

            function toggleCAction1RemarkField() {
                const isOther = String(cAction1Input?.value || '').trim() === 'Other';
                if (cAction1RemarkWrap) cAction1RemarkWrap.classList.toggle('d-none', !isOther);
                if (cAction1RemarkInput) {
                    cAction1RemarkInput.required = isOther;
                    if (!isOther) cAction1RemarkInput.value = '';
                }
            }

            function resetSkuImage() {
                if (skuImage && skuImageWrap) {
                    skuImage.setAttribute('src', '');
                    skuImageWrap.classList.add('d-none');
                }
            }

            function setSkuImage(url) {
                if (!skuImage || !skuImageWrap) return;
                const u = String(url || '').trim();
                if (!u) {
                    resetSkuImage();
                    return;
                }
                skuImage.setAttribute('src', u);
                skuImageWrap.classList.remove('d-none');
            }

            // ── Dropdown option management (root cause found / fixed) ───────────
            function rebuildDatalistOptions(datalistId, options) {
                const dl = document.getElementById(datalistId);
                if (!dl) return;
                dl.innerHTML = options.map(v => '<option value="' + escAttr(v) + '"></option>').join('');
            }
            async function fetchDropdownOptions(fieldType) {
                try {
                    const res = await fetch(dropdownOptionsListUrl + '?field_type=' + encodeURIComponent(
                    fieldType), {
                        headers: getHeaders
                    });
                    const data = await res.json();
                    if (!res.ok) return [];
                    return Array.isArray(data?.data) ? data.data : [];
                } catch (e) {
                    return [];
                }
            }
            async function postDropdownOption(url, payload) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: jsonHeaders,
                    body: JSON.stringify(payload)
                });
                const data = await res.json().catch(() => ({}));
                return {
                    response: res,
                    data
                };
            }
            async function initializeDynamicRootCauseOptions() {
                rebuildDatalistOptions('hold_issue_root_cause_found_datalist', await fetchDropdownOptions(
                    'root_cause_found'));
                rebuildDatalistOptions('hold_issue_root_cause_fixed_datalist', await fetchDropdownOptions(
                    'root_cause_fixed'));
                await loadActionCustomOptions();
                await loadWhatHappenedCustomOptions();
                await loadWrongSentReasonCustomOptions();
            }

            // ── Issue? dropdown: built-in + custom user-added options ──
            async function loadWhatHappenedCustomOptions() {
                const sel = document.getElementById('hold_issue_what_happened');
                const grp = document.getElementById('hold_issue_what_happened_custom_group');
                if (!sel || !grp) return;
                const previous = sel.value;
                let custom = [];
                try { custom = await fetchDropdownOptions('what_happened'); } catch (e) { custom = []; }
                const builtIns = new Set(
                    Array.from(document.querySelectorAll('#hold_issue_what_happened_builtin_group option'))
                        .map(o => o.value.toLowerCase().trim())
                );
                const seen = new Set();
                grp.innerHTML = (custom || [])
                    .filter(v => {
                        const k = String(v).toLowerCase().trim();
                        if (!k || seen.has(k) || builtIns.has(k)) return false;
                        seen.add(k); return true;
                    })
                    .map(v => '<option value="' + escAttr(v) + '">' + escapeHtml(v) + '</option>')
                    .join('');
                if (previous && Array.from(sel.options).some(o => o.value === previous)) {
                    sel.value = previous;
                }
            }

            function ensureWhatHappenedOptionPresent(value) {
                const v = String(value || '').trim();
                if (!v) return;
                const sel = document.getElementById('hold_issue_what_happened');
                if (!sel) return;
                if (Array.from(sel.options).some(o => o.value === v)) return;
                const grp = document.getElementById('hold_issue_what_happened_custom_group');
                if (!grp) return;
                const opt = document.createElement('option');
                opt.value = v;
                opt.textContent = v;
                grp.appendChild(opt);
            }

            // Preserve legacy / unknown marketplace values when opening an
            // existing issue for edit. The MKT <select> is sourced from
            // channel_master, but some older rows store a marketplace_1 that
            // no longer matches any channel (or matches by a different cased
            // spelling). Without this helper the select would silently reset
            // to "" and the user could accidentally lose the original value.
            function ensureMarketplaceOptionPresent(value) {
                const v = String(value || '').trim();
                if (!v) return;
                const sel = document.getElementById('hold_issue_marketplace_1');
                if (!sel) return;
                if (Array.from(sel.options).some(o => o.value === v)) return;
                const opt = document.createElement('option');
                opt.value = v;
                opt.textContent = v + ' (legacy)';
                sel.appendChild(opt);
            }

            async function addWhatHappenedOption() {
                const value = String(prompt('Enter new issue type') || '').trim();
                if (!value) return;
                try {
                    const { response, data } = await postDropdownOption(dropdownOptionsStoreUrl, {
                        field_type: 'what_happened',
                        option_value: value,
                    });
                    if (!response.ok) {
                        showAlert(data?.message || 'Unable to add issue type.');
                        return;
                    }
                    await loadWhatHappenedCustomOptions();
                    const sel = document.getElementById('hold_issue_what_happened');
                    if (sel) {
                        sel.value = value;
                        sel.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                } catch (e) {
                    showAlert('Unable to add issue type.');
                }
            }

            async function deleteWhatHappenedOption() {
                const sel = document.getElementById('hold_issue_what_happened');
                if (!sel) return;
                const selected = sel.value.trim();
                if (!selected) {
                    showAlert('Pick the custom issue to delete first.');
                    return;
                }
                const isBuiltIn = !!document.querySelector(
                    '#hold_issue_what_happened_builtin_group option[value="' + selected.replace(/"/g, '&quot;') + '"]'
                );
                if (isBuiltIn) {
                    showAlert('Built-in issues cannot be deleted. Pick a custom issue.');
                    return;
                }
                if (!confirm('Delete custom issue "' + selected + '"?')) return;
                try {
                    const { response, data } = await postDropdownOption(dropdownOptionsDeleteUrl, {
                        field_type: 'what_happened',
                        option_value: selected,
                    });
                    if (!response.ok) {
                        showAlert(data?.message || 'Unable to delete issue type.');
                        return;
                    }
                    await loadWhatHappenedCustomOptions();
                    sel.value = '';
                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                } catch (e) {
                    showAlert('Unable to delete issue type.');
                }
            }

            // ── Issue? sub-sections (Wrong Item Sent / Wrong Quantity Sent / Generic) ──
            // Built-ins with their own structured forms get specific keys; everything
            // else (other built-ins like Damaged / 0 Stock, plus any custom user-added
            // issue) falls through to 'generic' so we can show a free-text Notes box.
            function whatHappenedSubsectionKey(value) {
                const v = String(value || '').trim().toLowerCase();
                if (!v)                          return '';
                if (v === 'wrong item sent')     return 'wrong_item';
                if (v === 'wrong quantity sent') return 'wrong_qty';
                return 'generic';
            }

            function clearWrongItemSubsection() {
                const skuInp = document.getElementById('hold_issue_wrong_sent_sku');
                const notes  = document.getElementById('hold_issue_issue_notes');
                const qtyInp = document.getElementById('hold_issue_wrong_sent_qty');
                const outChk = document.getElementById('hold_issue_wrong_sent_outgoing_needed');
                const whInp  = document.getElementById('hold_issue_wrong_sent_outgoing_warehouse_id');
                const whWrap = document.getElementById('wrongSentOutgoingWarehouseWrap');
                const notice = document.getElementById('wrongSentOutgoingProcessedNotice');
                const reason = document.getElementById('hold_issue_wrong_sent_reason');
                const preview = document.getElementById('wrongSentSkuPreview');
                const qtyAv  = document.getElementById('wrongSentQtyAvailable');
                const img    = document.getElementById('wrongSentSkuImage');
                const cnt    = document.getElementById('issueNotesCharCount');
                if (skuInp) skuInp.value = '';
                if (notes) notes.value = '';
                if (qtyInp) qtyInp.value = '';
                if (outChk) { outChk.checked = false; outChk.disabled = false; }
                if (whInp) whInp.value = '';
                if (whWrap) whWrap.classList.add('d-none');
                if (notice) notice.classList.add('d-none');
                if (reason) reason.value = '';
                if (preview) preview.classList.add('d-none');
                if (qtyAv) qtyAv.textContent = '—';
                if (img) img.setAttribute('src', '');
                if (cnt) cnt.textContent = '0';
            }

            // Show/hide the warehouse picker for the Wrong Item Sent outgoing
            // checkbox. Mirrors toggleOutgoingWarehouseVisibility() above but
            // for the Wrong Item Sent sub-section's own checkbox/picker pair.
            function toggleWrongSentOutgoingWarehouseVisibility() {
                const checked = !!document.getElementById('hold_issue_wrong_sent_outgoing_needed')?.checked;
                document.getElementById('wrongSentOutgoingWarehouseWrap')?.classList.toggle('d-none', !checked);
            }

            function clearWrongQtySubsection() {
                Array.from(document.getElementsByName('qty_mismatch_type')).forEach(r => { r.checked = false; });
                const sentInp = document.getElementById('hold_issue_qty_sent');
                const ordInp  = document.getElementById('hold_issue_qty_ordered');
                if (sentInp) sentInp.value = '';
                if (ordInp)  ordInp.value = '';
                document.getElementById('qtySentWrap')?.classList.add('d-none');
                document.getElementById('qtyOrderedWrap')?.classList.add('d-none');
            }

            function clearCustomIssueSubsection() {
                const ta  = document.getElementById('hold_issue_custom_issue_notes');
                const cnt = document.getElementById('customIssueNotesCharCount');
                if (ta)  ta.value = '';
                if (cnt) cnt.textContent = '0';
            }

            function toggleWhatHappenedSubsection() {
                const sel = document.getElementById('hold_issue_what_happened');
                const key = whatHappenedSubsectionKey(sel?.value);
                const wrap = document.getElementById('whatHappenedSubsection');
                const wi = document.getElementById('wrongItemSubsection');
                const wq = document.getElementById('wrongQtySubsection');
                const cs = document.getElementById('customIssueSubsection');
                if (!wrap) return;
                wrap.classList.toggle('d-none', !key);
                wi?.classList.toggle('d-none', key !== 'wrong_item');
                wq?.classList.toggle('d-none', key !== 'wrong_qty');
                cs?.classList.toggle('d-none', key !== 'generic');
                // Use the actual selected label as the section title so users
                // see e.g. "Damaged details" / "<custom> details".
                if (key === 'generic') {
                    const t = document.getElementById('customIssueSubsectionTitle');
                    const label = String(sel?.value || '').trim();
                    if (t) t.textContent = (label ? label + ' details' : 'Issue details');
                }
                if (key !== 'wrong_item') clearWrongItemSubsection();
                if (key !== 'wrong_qty')  clearWrongQtySubsection();
                if (key !== 'generic')    clearCustomIssueSubsection();
            }

            // Reveal qty-sent + qty-ordered inputs when either Less/More radio is chosen.
            function refreshQtyMismatchVisibility() {
                const anyChecked = Array.from(document.getElementsByName('qty_mismatch_type')).some(r => r.checked);
                document.getElementById('qtySentWrap')?.classList.toggle('d-none', !anyChecked);
                document.getElementById('qtyOrderedWrap')?.classList.toggle('d-none', !anyChecked);
            }

            // Wrongly Sent SKU lookup reuses the same endpoint as Replacement SKU.
            let wrongSkuTimer = null;
            async function fillWrongSentSkuDetails() {
                const skuInp = document.getElementById('hold_issue_wrong_sent_sku');
                const preview = document.getElementById('wrongSentSkuPreview');
                const qtyAv = document.getElementById('wrongSentQtyAvailable');
                const img = document.getElementById('wrongSentSkuImage');
                if (!skuInp) return;
                const sku = skuInp.value.trim();
                if (!sku) {
                    preview?.classList.add('d-none');
                    if (qtyAv) qtyAv.textContent = '—';
                    if (img) img.setAttribute('src', '');
                    return;
                }
                try {
                    const res = await fetch(replacementSkuDetailsUrl + '?sku=' + encodeURIComponent(sku), {
                        headers: getHeaders,
                    });
                    const data = await res.json();
                    if (!res.ok || !data.found) {
                        preview?.classList.add('d-none');
                        if (qtyAv) qtyAv.textContent = '—';
                        if (img) img.setAttribute('src', '');
                        return;
                    }
                    if (img && data.image_url) img.setAttribute('src', data.image_url);
                    if (qtyAv) qtyAv.textContent = (data.qty_available != null) ? Number(data.qty_available) : '—';
                    preview?.classList.remove('d-none');
                } catch (e) { /* ignore */ }
            }

            // ── Action dropdown: built-in options + custom user-added actions ──
            // The built-in <optgroup> is already in the HTML; this fills the
            // "Custom actions" group from customer_care_issue_dropdown_options
            // (field_type = 'action').
            async function loadActionCustomOptions() {
                const sel = document.getElementById('hold_issue_action_1');
                const grp = document.getElementById('hold_issue_action_custom_group');
                if (!sel || !grp) return;
                const previous = sel.value; // preserve current selection across reload
                let custom = [];
                try {
                    custom = await fetchDropdownOptions('action');
                } catch (e) { custom = []; }
                // Avoid duplicates with built-ins.
                const builtIns = new Set(
                    Array.from(document.querySelectorAll('#hold_issue_action_builtin_group option'))
                        .map(o => o.value.toLowerCase().trim())
                );
                const seen = new Set();
                grp.innerHTML = (custom || [])
                    .filter(v => {
                        const k = String(v).toLowerCase().trim();
                        if (!k || seen.has(k) || builtIns.has(k)) return false;
                        seen.add(k); return true;
                    })
                    .map(v => '<option value="' + escAttr(v) + '">' + escapeHtml(v) + '</option>')
                    .join('');
                // Re-apply previous selection if still present.
                if (previous && Array.from(sel.options).some(o => o.value === previous)) {
                    sel.value = previous;
                }
            }

            // Append a saved value (e.g. legacy free-text typed before the dropdown
            // existed) to the Custom group so the edit modal can display it.
            function ensureActionOptionPresent(value) {
                const v = String(value || '').trim();
                if (!v) return;
                const sel = document.getElementById('hold_issue_action_1');
                if (!sel) return;
                if (Array.from(sel.options).some(o => o.value === v)) return;
                const grp = document.getElementById('hold_issue_action_custom_group');
                if (!grp) return;
                const opt = document.createElement('option');
                opt.value = v;
                opt.textContent = v;
                grp.appendChild(opt);
            }

            async function addActionOption() {
                const value = String(prompt('Enter new action') || '').trim();
                if (!value) return;
                try {
                    const { response, data } = await postDropdownOption(dropdownOptionsStoreUrl, {
                        field_type: 'action',
                        option_value: value,
                    });
                    if (!response.ok) {
                        showAlert(data?.message || 'Unable to add action.');
                        return;
                    }
                    await loadActionCustomOptions();
                    const sel = document.getElementById('hold_issue_action_1');
                    if (sel) {
                        sel.value = value;
                        sel.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                } catch (e) {
                    showAlert('Unable to add action.');
                }
            }

            async function deleteActionOption() {
                const sel = document.getElementById('hold_issue_action_1');
                if (!sel) return;
                const selected = sel.value.trim();
                if (!selected) {
                    showAlert('Pick the custom action to delete first.');
                    return;
                }
                // Only allow deleting custom actions, not built-ins.
                const isBuiltIn = !!document.querySelector(
                    '#hold_issue_action_builtin_group option[value="' + selected.replace(/"/g, '&quot;') + '"]'
                );
                if (isBuiltIn) {
                    showAlert('Built-in actions cannot be deleted. Pick a custom action.');
                    return;
                }
                if (!confirm('Delete custom action "' + selected + '"?')) return;
                try {
                    const { response, data } = await postDropdownOption(dropdownOptionsDeleteUrl, {
                        field_type: 'action',
                        option_value: selected,
                    });
                    if (!response.ok) {
                        showAlert(data?.message || 'Unable to delete action.');
                        return;
                    }
                    await loadActionCustomOptions();
                    sel.value = '';
                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                } catch (e) {
                    showAlert('Unable to delete action.');
                }
            }

            // ── "Why it happened" dropdown (Wrong Item Sent panel) ──────────
            // Same UX as the Action / Issue? dropdowns: built-in optgroup is
            // already in the HTML; this just fills the "Custom reasons"
            // optgroup from customer_care_issue_dropdown_options where
            // field_type = 'wrong_sent_reason'.
            async function loadWrongSentReasonCustomOptions() {
                const sel = document.getElementById('hold_issue_wrong_sent_reason');
                const grp = document.getElementById('hold_issue_wrong_sent_reason_custom_group');
                if (!sel || !grp) return;
                const previous = sel.value;
                let custom = [];
                try { custom = await fetchDropdownOptions('wrong_sent_reason'); } catch (e) { custom = []; }
                const builtIns = new Set(
                    Array.from(document.querySelectorAll('#hold_issue_wrong_sent_reason_builtin_group option'))
                        .map(o => o.value.toLowerCase().trim())
                );
                const seen = new Set();
                grp.innerHTML = (custom || [])
                    .filter(v => {
                        const k = String(v).toLowerCase().trim();
                        if (!k || seen.has(k) || builtIns.has(k)) return false;
                        seen.add(k); return true;
                    })
                    .map(v => '<option value="' + escAttr(v) + '">' + escapeHtml(v) + '</option>')
                    .join('');
                if (previous && Array.from(sel.options).some(o => o.value === previous)) {
                    sel.value = previous;
                }
            }

            // Preserve legacy / unknown reasons saved before they were added
            // to the dropdown — append them to the Custom group on edit so the
            // <select> can display the original value instead of falling back
            // to "" and silently losing user data.
            function ensureWrongSentReasonOptionPresent(value) {
                const v = String(value || '').trim();
                if (!v) return;
                const sel = document.getElementById('hold_issue_wrong_sent_reason');
                if (!sel) return;
                if (Array.from(sel.options).some(o => o.value === v)) return;
                const grp = document.getElementById('hold_issue_wrong_sent_reason_custom_group');
                if (!grp) return;
                const opt = document.createElement('option');
                opt.value = v;
                opt.textContent = v;
                grp.appendChild(opt);
            }

            async function addWrongSentReasonOption() {
                const value = String(prompt('Enter new reason') || '').trim();
                if (!value) return;
                try {
                    const { response, data } = await postDropdownOption(dropdownOptionsStoreUrl, {
                        field_type: 'wrong_sent_reason',
                        option_value: value,
                    });
                    if (!response.ok) {
                        showAlert(data?.message || 'Unable to add reason.');
                        return;
                    }
                    await loadWrongSentReasonCustomOptions();
                    const sel = document.getElementById('hold_issue_wrong_sent_reason');
                    if (sel) {
                        sel.value = value;
                        sel.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                } catch (e) {
                    showAlert('Unable to add reason.');
                }
            }

            async function deleteWrongSentReasonOption() {
                const sel = document.getElementById('hold_issue_wrong_sent_reason');
                if (!sel) return;
                const selected = sel.value.trim();
                if (!selected) {
                    showAlert('Pick the custom reason to delete first.');
                    return;
                }
                const isBuiltIn = !!document.querySelector(
                    '#hold_issue_wrong_sent_reason_builtin_group option[value="' + selected.replace(/"/g, '&quot;') + '"]'
                );
                if (isBuiltIn) {
                    showAlert('Built-in reasons cannot be deleted. Pick a custom reason.');
                    return;
                }
                if (!confirm('Delete custom reason "' + selected + '"?')) return;
                try {
                    const { response, data } = await postDropdownOption(dropdownOptionsDeleteUrl, {
                        field_type: 'wrong_sent_reason',
                        option_value: selected,
                    });
                    if (!response.ok) {
                        showAlert(data?.message || 'Unable to delete reason.');
                        return;
                    }
                    await loadWrongSentReasonCustomOptions();
                    sel.value = '';
                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                } catch (e) {
                    showAlert('Unable to delete reason.');
                }
            }

            async function addRootCauseOption(inputEl, fieldType, datalistId) {
                const value = String(prompt('Enter new option') || '').trim();
                if (!value) return;
                try {
                    const {
                        response,
                        data
                    } = await postDropdownOption(dropdownOptionsStoreUrl, {
                        field_type: fieldType,
                        option_value: value
                    });
                    if (!response.ok) {
                        showAlert(data?.message || 'Unable to add option.');
                        return;
                    }
                    const dl = document.getElementById(datalistId);
                    if (dl && !Array.from(dl.options).some(o => o.value === value)) {
                        const opt = document.createElement('option');
                        opt.value = value;
                        dl.appendChild(opt);
                    }
                    inputEl.value = value;
                    inputEl.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                } catch (e) {
                    showAlert('Unable to add option.');
                }
            }
            async function deleteRootCauseOption(inputEl, fieldType, datalistId) {
                const selected = String(inputEl?.value || '').trim();
                if (!selected) {
                    showAlert('Please type the value to delete first.');
                    return;
                }
                if (!confirm('Delete "' + selected + '" from suggestions?')) return;
                try {
                    const {
                        response,
                        data
                    } = await postDropdownOption(dropdownOptionsDeleteUrl, {
                        field_type: fieldType,
                        option_value: selected
                    });
                    if (!response.ok) {
                        showAlert(data?.message || 'Unable to delete option.');
                        return;
                    }
                    const dl = document.getElementById(datalistId);
                    if (dl) {
                        const opt = Array.from(dl.options).find(o => o.value === selected);
                        if (opt) opt.remove();
                    }
                    inputEl.value = '';
                    inputEl.dispatchEvent(new Event('input', {
                        bubbles: true
                    }));
                } catch (e) {
                    showAlert('Unable to delete option.');
                }
            }

            // ── SKU lookup ─────────────────────────────────────────────────────
            async function refreshSkuSuggestions(query) {
                const q = String(query || '').trim();
                if (q.length < 1) {
                    skuDatalist.innerHTML = '';
                    return;
                }
                try {
                    const res = await fetch(skuSearchUrl + '?q=' + encodeURIComponent(q), {
                        headers: getHeaders
                    });
                    const data = await res.json();
                    const list = Array.isArray(data?.skus) ? data.skus : [];
                    skuDatalist.innerHTML = list.map(item => {
                        const sku = item?.sku ?? '';
                        const parent = item?.parent ?? '';
                        const label = parent ? (parent + ' · ' + sku) : sku;
                        return '<option value="' + escAttr(sku) + '" label="' + escAttr(label) +
                            '"></option>';
                    }).join('');
                } catch (e) {
                    skuDatalist.innerHTML = '';
                }
            }
            async function fillSkuDetails() {
                const sku = skuInput.value.trim();
                qtyInput.value = '';
                parentInput.value = '';
                resetSkuImage();
                if (!sku) return;
                try {
                    const res = await fetch(skuDetailsUrl + '?sku=' + encodeURIComponent(sku), {
                        headers: getHeaders
                    });
                    const data = await res.json();
                    if (!res.ok || !data.found) return;
                    qtyInput.value = data.qty ?? 0;
                    parentInput.value = data.parent ?? '';
                    setSkuImage(data.image_url ?? '');
                } catch (e) {
                    /* ignore */ }
            }

            // ── Modal open/reset/edit/archive ──────────────────────────────────
            function resetForm() {
                form.reset();
                editingIssueId = null;
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'Save';
                qtyInput.value = '';
                orderQtyInput.value = '';
                parentInput.value = '';
                resetSkuImage();
                document.getElementById('extra-sku-rows-container').innerHTML = '';
                ['hold_issue_image_1', 'hold_issue_image_2'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                ['hold_issue_image_1_existing', 'hold_issue_image_2_existing'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.classList.add('d-none');
                        el.innerHTML = '';
                    }
                });
                clearDepartmentMultiSelect();
                clearRefundSubsection();
                clearReplacementSubsection();
                clearOtherSubsection();
                clearWrongItemSubsection();
                clearWrongQtySubsection();
                clearCustomIssueSubsection();
                toggleRootCauseRemarkField();
                toggleAction1RemarkField();
                toggleActionSubsection();
                toggleWhatHappenedSubsection();
                toggleCAction1RemarkField();
                hideAlert();
            }

            function openEditModal(record) {
                if (!record) return;
                editingIssueId = Number(record.id);
                skuInput.value = record.sku || '';
                qtyInput.value = record.qty ?? '';
                orderQtyInput.value = record.order_qty ?? '';
                parentInput.value = record.parent || '';
                ensureMarketplaceOptionPresent(record.marketplace_1);
                marketplace1Input.value = record.marketplace_1 || '';
                ensureWhatHappenedOptionPresent(record.what_happened);
                whatHappenedInput.value = record.what_happened || '';
                // Pre-fill Issue? sub-section
                clearWrongItemSubsection();
                clearWrongQtySubsection();
                toggleWhatHappenedSubsection();
                const whKey = whatHappenedSubsectionKey(record.what_happened);
                if (whKey === 'wrong_item') {
                    document.getElementById('hold_issue_wrong_sent_sku').value = record.wrong_sent_sku || '';
                    document.getElementById('hold_issue_issue_notes').value = record.issue_notes || '';
                    const cnt = document.getElementById('issueNotesCharCount');
                    if (cnt) cnt.textContent = String((record.issue_notes || '').length);
                    if (record.wrong_sent_sku) fillWrongSentSkuDetails();
                    // Prefill "Why it happened" — append legacy/unknown values
                    // to the Custom group so the select can show them instead
                    // of silently falling back to "" and losing the value.
                    ensureWrongSentReasonOptionPresent(record.wrong_sent_reason);
                    const reasonSel = document.getElementById('hold_issue_wrong_sent_reason');
                    if (reasonSel) reasonSel.value = record.wrong_sent_reason || '';
                    // Prefill the new Wrong-Item outgoing trigger fields.
                    const qtyInp = document.getElementById('hold_issue_wrong_sent_qty');
                    if (qtyInp) qtyInp.value =
                        (record.wrong_sent_qty != null && record.wrong_sent_qty !== '')
                            ? record.wrong_sent_qty : '';
                    const outChk = document.getElementById('hold_issue_wrong_sent_outgoing_needed');
                    if (outChk) outChk.checked = !!record.wrong_sent_outgoing_needed;
                    const wsWh = document.getElementById('hold_issue_wrong_sent_outgoing_warehouse_id');
                    if (wsWh) wsWh.value = record.wrong_sent_outgoing_warehouse_id
                        ? String(record.wrong_sent_outgoing_warehouse_id) : '';
                    toggleWrongSentOutgoingWarehouseVisibility();
                    // Lock the checkbox + show the "already processed" notice when
                    // Shopify has already been decremented for this issue, so
                    // re-saves don't double-deduct.
                    if (record.wrong_sent_outgoing_processed_at) {
                        const cb = document.getElementById('hold_issue_wrong_sent_outgoing_needed');
                        const notice = document.getElementById('wrongSentOutgoingProcessedNotice');
                        if (cb) { cb.checked = true; cb.disabled = true; }
                        if (notice) notice.classList.remove('d-none');
                    }
                } else if (whKey === 'wrong_qty') {
                    const t = String(record.qty_mismatch_type || '').toLowerCase();
                    if (t === 'less') document.getElementById('qtyMismatch_less').checked = true;
                    if (t === 'more') document.getElementById('qtyMismatch_more').checked = true;
                    document.getElementById('hold_issue_qty_sent').value =
                        (record.qty_sent != null && record.qty_sent !== '') ? record.qty_sent : '';
                    document.getElementById('hold_issue_qty_ordered').value =
                        (record.qty_ordered != null && record.qty_ordered !== '') ? record.qty_ordered : '';
                    refreshQtyMismatchVisibility();
                } else if (whKey === 'generic') {
                    const ta = document.getElementById('hold_issue_custom_issue_notes');
                    if (ta) ta.value = record.issue_notes || '';
                    const cnt = document.getElementById('customIssueNotesCharCount');
                    if (cnt) cnt.textContent = String((record.issue_notes || '').length);
                }
                issueInput.value = record.issue || '';
                document.getElementById('hold_issue_order_number').value = record.order_number || '';
                const tl = record.total_loss;
                document.getElementById('hold_issue_total_loss').value = (tl != null && tl !== '') ? String(Math.round(
                    parseFloat(tl))) : '';
                issueRemarkInput.value = record.issue_remark || '';
                toggleRootCauseRemarkField();
                // If the record has a saved action that isn't in the dropdown
                // (legacy free-text or a since-deleted custom), add it inline so
                // the select can display it for the user.
                ensureActionOptionPresent(record.action_1);
                action1Input.value = record.action_1 || '';
                if (action1RemarkInput) action1RemarkInput.value = record.action_1_remark || '';
                toggleAction1RemarkField();
                // Pre-fill Action sub-section based on the record's saved data.
                clearRefundSubsection();
                clearReplacementSubsection();
                clearOtherSubsection();
                toggleActionSubsection();
                const subKey = actionSubsectionKey(record.action_1);
                if (subKey === 'refund') {
                    const rt = String(record.refund_type || '').toLowerCase();
                    if (rt === 'partial') document.getElementById('refundType_partial').checked = true;
                    if (rt === 'full') document.getElementById('refundType_full').checked = true;
                    document.getElementById('hold_issue_refund_amount').value =
                        (record.refund_amount != null && record.refund_amount !== '') ? record.refund_amount : '';
                } else if (subKey === 'replacement') {
                    document.getElementById('hold_issue_replacement_sku').value = record.replacement_sku || '';
                    document.getElementById('hold_issue_replacement_qty_sending').value =
                        (record.replacement_qty_sending != null && record.replacement_qty_sending !== '')
                            ? record.replacement_qty_sending : '';
                    document.getElementById('hold_issue_replacement_tracking_30').value = record.replacement_tracking || '';
                    document.getElementById('hold_issue_outgoing_needed').checked = !!record.outgoing_needed;
                    const whSel = document.getElementById('hold_issue_outgoing_warehouse_id');
                    if (whSel) whSel.value = record.outgoing_warehouse_id ? String(record.outgoing_warehouse_id) : '';
                    toggleOutgoingWarehouseVisibility();
                    // If outgoing was already processed, lock the checkbox so we
                    // can't accidentally re-trigger and double-decrement Shopify.
                    if (record.outgoing_processed_at) {
                        const cb = document.getElementById('hold_issue_outgoing_needed');
                        const notice = document.getElementById('outgoingProcessedNotice');
                        if (cb) { cb.checked = true; cb.disabled = true; }
                        if (notice) notice.classList.remove('d-none');
                    }
                    if (record.replacement_sku) fillReplacementSkuDetails();
                } else if (subKey === 'other') {
                    document.getElementById('hold_issue_other_notes').value = record.action_1_remark || '';
                }
                if (trackingNumberInput) trackingNumberInput.value = record.tracking_number || '';
                if (issueLinkInput) issueLinkInput.value = record.issue_link || '';
                if (replacementTrackingInput) replacementTrackingInput.value = record.replacement_tracking || '';
                (function() {
                    const e1 = document.getElementById('hold_issue_image_1_existing');
                    const e2 = document.getElementById('hold_issue_image_2_existing');
                    document.getElementById('hold_issue_image_1').value = '';
                    document.getElementById('hold_issue_image_2').value = '';
                    if (record.image_1_url) {
                        e1.innerHTML = '<span class="text-muted small me-1">Current:</span><a href="' + escAttr(
                            record.image_1_url) + '" target="_blank" rel="noopener"><img src="' + escAttr(record
                            .image_1_url) + '" class="issue-modal-thumb" alt="Preview"></a>';
                        e1.classList.remove('d-none');
                    } else {
                        e1.innerHTML = '';
                        e1.classList.add('d-none');
                    }
                    if (record.image_2_url) {
                        e2.innerHTML = '<span class="text-muted small me-1">Current:</span><a href="' + escAttr(
                            record.image_2_url) + '" target="_blank" rel="noopener"><img src="' + escAttr(record
                            .image_2_url) + '" class="issue-modal-thumb" alt="Preview"></a>';
                        e2.classList.remove('d-none');
                    } else {
                        e2.innerHTML = '';
                        e2.classList.add('d-none');
                    }
                })();
                cAction1Input.value = record.c_action_1 || '';
                cAction1RemarkInput.value = record.c_action_1_remark || '';
                // For dept-split groups: pre-select ALL departments in the group
                // (same group_id + same SKU) so saving without changes preserves them.
                let editDepts = rowDepartments(record);
                if (record.group_id) {
                    const set = new Set();
                    holdIssueRows.forEach(function (r) {
                        if (r.group_id === record.group_id && r.sku === record.sku) {
                            rowDepartments(r).forEach(function (d) { set.add(d); });
                        }
                    });
                    if (set.size) editDepts = Array.from(set);
                }
                setDepartmentMultiSelect({ departments: editDepts });
                toggleCAction1RemarkField();
                hideAlert();
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'Update';
                window.bootstrap?.Modal?.getOrCreateInstance(modalEl)?.show();
            }
            async function archiveRecord(recordId) {
                if (!recordId || !confirm('Archive this record?')) return;
                try {
                    const res = await fetch(archiveBase + '/' + encodeURIComponent(recordId) + '/archive', {
                        method: 'POST',
                        headers: jsonHeaders,
                        body: '{}'
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        alert(data?.message || 'Unable to archive record.');
                        return;
                    }
                    await loadHoldIssueRows();
                    if (historyTable) loadHoldIssueHistoryRows();
                } catch (e) {
                    alert('Unable to archive record. Please try again.');
                }
            }

            // ── Build FormData (images + multi-sku) ────────────────────────────
            function fillIssueFormData(fd, payload, isMultiSku) {
                for (const k of Object.keys(payload)) {
                    if (k === 'skus') continue;
                    const v = payload[k];
                    if (k === 'department' && Array.isArray(v)) {
                        v.forEach(d => fd.append('department[]', d));
                        continue;
                    }
                    if (v === undefined || v === null) continue;
                    if (typeof v === 'object') continue;
                    fd.append(k, String(v));
                }
                if (isMultiSku && Array.isArray(payload.skus)) {
                    payload.skus.forEach((row, i) => {
                        fd.append('skus[' + i + '][sku]', row.sku ?? '');
                        fd.append('skus[' + i + '][qty]', String(row.qty ?? 0));
                        fd.append('skus[' + i + '][order_qty]', row.order_qty == null || row.order_qty === '' ?
                            '' : String(row.order_qty));
                        fd.append('skus[' + i + '][parent]', row.parent ?? '');
                    });
                }
                const img1 = document.getElementById('hold_issue_image_1');
                const img2 = document.getElementById('hold_issue_image_2');
                if (img1 && img1.files && img1.files[0]) fd.append('image_1', img1.files[0]);
                if (img2 && img2.files && img2.files[0]) fd.append('image_2', img2.files[0]);
            }

            async function submitIssueForm(event) {
                event.preventDefault();
                hideAlert();
                const sku = skuInput.value.trim();
                const issue = issueInput.value.trim();
                const orderNumberEl = document.getElementById('hold_issue_order_number');
                const orderNumber = (orderNumberEl?.value || '').trim();
                if (!sku) {
                    showAlert('SKU is required.');
                    skuInput.focus();
                    return;
                }
                if (!orderNumber) {
                    showAlert('Order Number is required.');
                    orderNumberEl?.focus();
                    return;
                }
                if (action1Input.value.trim() === 'Other' && action1RemarkInput && action1RemarkInput.value
                .trim() === '') {
                    showAlert('Please enter Action remark when Action is Other.');
                    action1RemarkInput.focus();
                    return;
                }
                if (issue === 'Other' && issueRemarkInput.value.trim() === '') {
                    showAlert('Please enter Root Cause remark for Other.');
                    issueRemarkInput.focus();
                    return;
                }
                if (cAction1Input.value.trim() === 'Other' && cAction1RemarkInput.value.trim() === '') {
                    showAlert('Please enter Root Cause Fixed remark for Other.');
                    cAction1RemarkInput.focus();
                    return;
                }
                const deptPayload = getDepartmentPayload();
                if (!deptPayload.length) {
                    showAlert('Select at least one department.');
                    if (departmentInput) departmentInput.focus();
                    return;
                }

                // Action sub-section validation + payload assembly.
                const actionVal = action1Input.value.trim();
                const subKey = actionSubsectionKey(actionVal);
                let refundType = '';
                let refundAmount = '';
                let replacementSku = '';
                let replacementQtySending = '';
                let replacementTrackingValue = (replacementTrackingInput?.value || '').trim();
                let outgoingNeeded = false;
                let otherNotes = '';

                if (subKey === 'refund') {
                    const rt = (Array.from(document.getElementsByName('refund_type')).find(r => r.checked) || {}).value || '';
                    const amt = document.getElementById('hold_issue_refund_amount').value;
                    if (!rt) { showAlert('Please pick Partial or Full refund.'); return; }
                    if (amt === '' || isNaN(Number(amt)) || Number(amt) < 0) {
                        showAlert('Refund amount is required.');
                        document.getElementById('hold_issue_refund_amount').focus();
                        return;
                    }
                    refundType = rt;
                    refundAmount = amt;
                } else if (subKey === 'replacement') {
                    const rsku = document.getElementById('hold_issue_replacement_sku').value.trim();
                    const rqty = document.getElementById('hold_issue_replacement_qty_sending').value;
                    const rtrk = document.getElementById('hold_issue_replacement_tracking_30').value.trim();
                    const rout = document.getElementById('hold_issue_outgoing_needed').checked;
                    if (!rsku) {
                        showAlert('Replacement SKU is required.');
                        document.getElementById('hold_issue_replacement_sku').focus();
                        return;
                    }
                    if (rqty === '' || isNaN(Number(rqty)) || Number(rqty) < 0) {
                        showAlert('Quantity sending is required.');
                        document.getElementById('hold_issue_replacement_qty_sending').focus();
                        return;
                    }
                    if (rtrk.length > 30) {
                        showAlert('Tracking ID must be at most 30 characters.');
                        document.getElementById('hold_issue_replacement_tracking_30').focus();
                        return;
                    }
                    replacementSku = rsku;
                    replacementQtySending = rqty;
                    // Replacement tracking ID for this action lives in the same column
                    // as the existing Replacement Tracking field, but capped at 30 chars.
                    if (rtrk) replacementTrackingValue = rtrk;
                    outgoingNeeded = rout;
                    // If Outgoing needed is checked, a warehouse must be picked.
                    if (rout) {
                        const whSel = document.getElementById('hold_issue_outgoing_warehouse_id');
                        if (!whSel || !whSel.value) {
                            showAlert('Please pick an outgoing warehouse.');
                            whSel?.focus();
                            return;
                        }
                    }
                } else if (subKey === 'other') {
                    otherNotes = document.getElementById('hold_issue_other_notes').value.trim();
                }

                // Issue? sub-section validation + payload assembly.
                const whatHappenedVal = whatHappenedInput.value.trim();
                const whSubKey = whatHappenedSubsectionKey(whatHappenedVal);
                let wrongSku = '';
                let issueNotesVal = '';
                let qtyMismatchType = '';
                let qtySentVal = '';
                let qtyOrderedVal = '';
                // Wrong Item Sent → outgoing trigger payload (independent of
                // the Replacement outgoing). Default to "off" so nothing fires
                // when the user hasn't ticked the new checkbox.
                let wrongSentQtyVal = '';
                let wrongSentOutgoingNeeded = false;
                let wrongSentOutgoingWarehouseId = '';
                let wrongSentReasonVal = '';

                if (whSubKey === 'wrong_item') {
                    const ws = document.getElementById('hold_issue_wrong_sent_sku').value.trim();
                    const n = document.getElementById('hold_issue_issue_notes').value.trim();
                    if (!ws) {
                        showAlert('Wrongly sent SKU is required.');
                        document.getElementById('hold_issue_wrong_sent_sku').focus();
                        return;
                    }
                    if (n.length > 200) {
                        showAlert('Notes must be at most 200 characters.');
                        document.getElementById('hold_issue_issue_notes').focus();
                        return;
                    }
                    wrongSku = ws;
                    issueNotesVal = n;

                    // Optional reason. Just trim and pass through — values
                    // longer than 64 chars are clipped server-side, so we
                    // don't need to enforce a length here.
                    wrongSentReasonVal = (document.getElementById('hold_issue_wrong_sent_reason')?.value || '').trim();

                    // Optional Qty wrongly sent. Empty is allowed; only validate
                    // sign / numeric shape if the user typed something.
                    const wsq = (document.getElementById('hold_issue_wrong_sent_qty')?.value || '').trim();
                    if (wsq !== '') {
                        if (isNaN(Number(wsq)) || Number(wsq) < 0) {
                            showAlert('Qty wrongly sent must be a non-negative number.');
                            document.getElementById('hold_issue_wrong_sent_qty')?.focus();
                            return;
                        }
                        wrongSentQtyVal = wsq;
                    }

                    // Outgoing checkbox is itself optional — it ONLY becomes
                    // strict when the user has checked it (Qty + Warehouse
                    // both required at that point so Shopify gets a usable
                    // pair to deduct).
                    const outChk = document.getElementById('hold_issue_wrong_sent_outgoing_needed');
                    wrongSentOutgoingNeeded = !!outChk?.checked;
                    if (wrongSentOutgoingNeeded) {
                        if (wsq === '' || isNaN(Number(wsq)) || Number(wsq) <= 0) {
                            showAlert('Qty wrongly sent must be greater than 0 to deduct from Shopify.');
                            document.getElementById('hold_issue_wrong_sent_qty')?.focus();
                            return;
                        }
                        const whSel = document.getElementById('hold_issue_wrong_sent_outgoing_warehouse_id');
                        if (!whSel || !whSel.value) {
                            showAlert('Please pick an outgoing warehouse for the wrongly sent SKU.');
                            whSel?.focus();
                            return;
                        }
                        wrongSentOutgoingWarehouseId = whSel.value;
                    }
                } else if (whSubKey === 'wrong_qty') {
                    const r = (Array.from(document.getElementsByName('qty_mismatch_type')).find(x => x.checked) || {}).value || '';
                    const qs = document.getElementById('hold_issue_qty_sent').value;
                    const qo = document.getElementById('hold_issue_qty_ordered').value;
                    if (!r) { showAlert('Pick "Quantity less" or "Quantity more".'); return; }
                    if (qs === '' || isNaN(Number(qs)) || Number(qs) < 0) {
                        showAlert('Quantity sent is required.');
                        document.getElementById('hold_issue_qty_sent').focus();
                        return;
                    }
                    if (qo === '' || isNaN(Number(qo)) || Number(qo) < 0) {
                        showAlert('Quantity ordered is required.');
                        document.getElementById('hold_issue_qty_ordered').focus();
                        return;
                    }
                    qtyMismatchType = r;
                    qtySentVal = qs;
                    qtyOrderedVal = qo;
                } else if (whSubKey === 'generic') {
                    // Free-text notes for any other built-in (Damaged / 0 Stock)
                    // or custom user-added issue. Empty is allowed; only enforce
                    // the 200-char cap that matches the DB column.
                    const n = (document.getElementById('hold_issue_custom_issue_notes')?.value || '').trim();
                    if (n.length > 200) {
                        showAlert('Notes must be at most 200 characters.');
                        document.getElementById('hold_issue_custom_issue_notes')?.focus();
                        return;
                    }
                    issueNotesVal = n;
                }

                try {
                    const extraSkuRows = document.querySelectorAll('#extra-sku-rows-container .extra-sku-row');
                    const isMultiSku = extraSkuRows.length > 0;
                    const sharedFields = {
                        issue: issue,
                        order_number: (document.getElementById('hold_issue_order_number')?.value || '').trim(),
                        total_loss: document.getElementById('hold_issue_total_loss')?.value || '',
                        marketplace_1: marketplace1Input.value.trim(),
                        what_happened: whatHappenedInput.value.trim(),
                        issue_remark: issueRemarkInput.value.trim(),
                        action_1: actionVal,
                        // For "Other" we route the textarea into action_1_remark; otherwise
                        // we keep whatever the user typed in the small Action Remark field.
                        action_1_remark: subKey === 'other' ? otherNotes : (action1RemarkInput?.value || '').trim(),
                        tracking_number: (trackingNumberInput?.value || '').trim(),
                        issue_link: (issueLinkInput?.value || '').trim(),
                        replacement_tracking: replacementTrackingValue,
                        c_action_1: cAction1Input.value.trim(),
                        c_action_1_remark: cAction1RemarkInput.value.trim(),
                        department: deptPayload,
                        // Action sub-section fields:
                        refund_type: refundType,
                        refund_amount: refundAmount,
                        replacement_sku: replacementSku,
                        replacement_qty_sending: replacementQtySending,
                        outgoing_needed: outgoingNeeded ? '1' : '0',
                        outgoing_warehouse_id: outgoingNeeded
                            ? (document.getElementById('hold_issue_outgoing_warehouse_id')?.value || '')
                            : '',
                        // Issue? sub-section fields:
                        wrong_sent_sku: wrongSku,
                        issue_notes: issueNotesVal,
                        qty_mismatch_type: qtyMismatchType,
                        qty_sent: qtySentVal,
                        qty_ordered: qtyOrderedVal,
                        // Wrong Item Sent → outgoing trigger payload:
                        wrong_sent_qty: wrongSentQtyVal,
                        wrong_sent_outgoing_needed: wrongSentOutgoingNeeded ? '1' : '0',
                        wrong_sent_outgoing_warehouse_id: wrongSentOutgoingNeeded
                            ? wrongSentOutgoingWarehouseId : '',
                        // "Why it happened" reason for the Wrong Item Sent panel:
                        wrong_sent_reason: wrongSentReasonVal,
                    };
                    let payload;
                    if (isMultiSku) {
                        const skus = [{
                            sku: sku,
                            qty: qtyInput.value === '' ? 0 : Number(qtyInput.value),
                            order_qty: orderQtyInput.value === '' ? null : Number(orderQtyInput.value),
                            parent: parentInput.value.trim(),
                        }];
                        extraSkuRows.forEach(rowEl => {
                            const skuVal = rowEl.querySelector('.extra-sku-input')?.value?.trim() || '';
                            if (skuVal) {
                                const oq = rowEl.querySelector('.extra-sku-order-qty')?.value;
                                skus.push({
                                    sku: skuVal,
                                    qty: Number(rowEl.querySelector('.extra-sku-qty')?.value || 0),
                                    order_qty: oq !== '' && oq != null ? Number(oq) : null,
                                    parent: rowEl.querySelector('.extra-sku-parent')?.value
                                    ?.trim() || '',
                                });
                            }
                        });
                        payload = {
                            ...sharedFields,
                            skus
                        };
                    } else {
                        payload = {
                            sku: sku,
                            qty: qtyInput.value === '' ? 0 : Number(qtyInput.value),
                            order_qty: orderQtyInput.value === '' ? null : Number(orderQtyInput.value),
                            parent: parentInput.value.trim(),
                            ...sharedFields,
                        };
                    }

                    const isEdit = editingIssueId !== null;
                    const targetUrl = isEdit ? recordsUpdateBaseUrl + '/' + encodeURIComponent(editingIssueId) :
                        recordsStoreUrl;
                    const fd = new FormData();
                    if (isEdit) fd.append('_method', 'PUT');
                    fillIssueFormData(fd, payload, isMultiSku);
                    const response = await fetch(targetUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: fd,
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        if (data?.errors && typeof data.errors === 'object') {
                            const firstKey = Object.keys(data.errors)[0];
                            const firstError = firstKey ? data.errors[firstKey]?.[0] : null;
                            showAlert(firstError || data?.message || 'Unable to save issue.');
                            return;
                        }
                        showAlert(data?.message || 'Unable to save issue.');
                        return;
                    }
                    await loadHoldIssueRows();
                    if (historyTable) loadHoldIssueHistoryRows();
                    // If outgoing was attempted but failed, the server still saves
                    // the issue and returns an `outgoing_warning` string. Show it
                    // as a warning so the user knows to retry the outgoing piece.
                    if (data?.outgoing_warning) {
                        showAlert(data.outgoing_warning, 'danger');
                    } else {
                        showAlert(data?.message || (isEdit ? 'Issue updated successfully.' :
                            'Issue saved successfully.'), 'success');
                    }
                    const modalInstance = window.bootstrap?.Modal?.getInstance(modalEl);
                    if (modalInstance) setTimeout(() => modalInstance.hide(), 250);
                } catch (e) {
                    showAlert('Unable to save issue. Please try again.');
                }
            }

            // ── Multi-SKU: Add Another SKU row ─────────────────────────────────
            function wireAddSkuRow() {
                const btnAddSkuRow = document.getElementById('btn-add-sku-row');
                if (!btnAddSkuRow) return;
                btnAddSkuRow.addEventListener('click', function() {
                    const container = document.getElementById('extra-sku-rows-container');
                    if (!container) return;
                    const rowEl = document.createElement('div');
                    rowEl.className = 'extra-sku-row border rounded p-2 mb-2 position-relative';
                    rowEl.innerHTML =
                        '<button type="button" class="btn-close position-absolute top-0 end-0 m-1 remove-extra-sku-row" style="font-size:0.65rem;" title="Remove this SKU"></button>' +
                        '<div class="row g-2 align-items-end">' +
                        '<div class="col-md-8"><label class="form-label small mb-1">SKU <span class="text-danger">*</span></label>' +
                        '<input type="text" class="form-control form-control-sm extra-sku-input" list="hold_issue_sku_datalist" placeholder="Search SKU" autocomplete="off">' +
                        '<div class="mt-1 d-none extra-sku-image-wrap"><img src="" class="sku-image-preview" style="width:52px;height:52px;"></div></div>' +
                        '<div style="display:none;"><input type="number" class="form-control form-control-sm extra-sku-qty" readonly></div>' +
                        '<div class="col-md-4"><label class="form-label small mb-1">QTY</label>' +
                        '<input type="number" class="form-control form-control-sm extra-sku-order-qty" min="0" step="1" placeholder="Qty"></div>' +
                        '<div style="display:none;"><input type="text" class="form-control form-control-sm extra-sku-parent" readonly></div>' +
                        '</div>';
                    container.appendChild(rowEl);
                    const sInput = rowEl.querySelector('.extra-sku-input');
                    const qtyInp = rowEl.querySelector('.extra-sku-qty');
                    const parentInp = rowEl.querySelector('.extra-sku-parent');
                    const imgWrap = rowEl.querySelector('.extra-sku-image-wrap');
                    const imgEl = imgWrap?.querySelector('img');
                    let timer = null;
                    async function fetchAndFill(skuVal) {
                        const s = String(skuVal || '').trim();
                        qtyInp.value = '';
                        parentInp.value = '';
                        if (imgWrap) imgWrap.classList.add('d-none');
                        if (!s) return;
                        try {
                            const res = await fetch(skuDetailsUrl + '?sku=' + encodeURIComponent(s), {
                                headers: getHeaders
                            });
                            const d = await res.json();
                            if (d.found) {
                                qtyInp.value = d.qty ?? 0;
                                parentInp.value = d.parent ?? '';
                                if (d.image_url && imgEl && imgWrap) {
                                    imgEl.src = d.image_url;
                                    imgWrap.classList.remove('d-none');
                                }
                            }
                        } catch (e) {
                            /* ignore */ }
                    }
                    sInput.addEventListener('input', () => {
                        clearTimeout(timer);
                        timer = setTimeout(() => refreshSkuSuggestions(sInput.value), 220);
                    });
                    sInput.addEventListener('change', () => fetchAndFill(sInput.value));
                    sInput.addEventListener('blur', () => fetchAndFill(sInput.value));
                    sInput.focus();
                });
                document.getElementById('extra-sku-rows-container')?.addEventListener('click', function(e) {
                    const removeBtn = e.target.closest('.remove-extra-sku-row');
                    if (removeBtn) removeBtn.closest('.extra-sku-row')?.remove();
                });
            }

            // ── CSV export (client-side, active rows) ──────────────────────────
            function exportCsv() {
                function csvEscape(val) {
                    const str = String(val ?? '').replace(/"/g, '""');
                    return /[",\n\r]/.test(str) ? '"' + str + '"' : str;
                }

                function buildCsv(headers, rows) {
                    return [headers.map(csvEscape).join(',')].concat(rows.map(r => r.map(csvEscape).join(','))).join(
                        '\r\n');
                }

                function downloadCsv(content, filename) {
                    const blob = new Blob([content], {
                        type: 'text/csv;charset=utf-8;'
                    });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }
                const headers = ['#', 'SKU', 'Order Number', 'Order QTY', 'MKT', 'Issue?', 'Action', 'Action Remark',
                    'Tracking', 'Link', 'Track R', 'Root Cause', 'Root Cause Remark', 'Root Cause Fixed',
                    'Root Cause Fixed Remark', 'Dept', 'Created By', 'Created At'
                ];
                const data = holdIssueRows.map(r => [
                    r.id, r.sku, r.order_number || '', r.order_qty, r.marketplace_1, r.what_happened, r
                    .action_1, r.action_1_remark,
                    r.tracking_number || '', r.issue_link || '', r.replacement_tracking, r.issue, r
                    .issue_remark, r.c_action_1, r.c_action_1_remark,
                    r.department || '', r.created_by, r.created_at_display,
                ]);
                const dateStr = new Date().toISOString().slice(0, 10);
                downloadCsv(buildCsv(headers, data), 'all_issues_active_' + dateStr + '.csv');
            }

            function wireImportCsv() {
                document.getElementById('btnImportCsv').addEventListener('click', () => {
                    document.getElementById('importCsvFile').value = '';
                    document.getElementById('importCsvAlert').className = 'd-none mb-3';
                    document.getElementById('importCsvAlert').innerHTML = '';
                    document.getElementById('importCsvProgress').classList.add('d-none');
                    document.getElementById('importCsvErrors').classList.add('d-none');
                    document.getElementById('importCsvErrorList').innerHTML = '';
                    new bootstrap.Modal(document.getElementById('importCsvModal')).show();
                });
                document.getElementById('importCsvSampleLink').addEventListener('click', (e) => {
                    e.preventDefault();
                    const csv = [importCsvHeaders.join(','), importCsvSampleRow.join(',')].join('\r\n');
                    const blob = new Blob([csv], {
                        type: 'text/csv;charset=utf-8;'
                    });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'import_sample.csv';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                });
                document.getElementById('importCsvSubmitBtn').addEventListener('click', async () => {
                    const fileInput = document.getElementById('importCsvFile');
                    const alertEl = document.getElementById('importCsvAlert');
                    const progressEl = document.getElementById('importCsvProgress');
                    const errorsEl = document.getElementById('importCsvErrors');
                    const errList = document.getElementById('importCsvErrorList');
                    if (!fileInput.files.length) {
                        alertEl.className = 'alert alert-warning mb-3';
                        alertEl.textContent = 'Please select a CSV file.';
                        return;
                    }
                    const formData = new FormData();
                    formData.append('file', fileInput.files[0]);
                    formData.append('_token', csrfToken);
                    alertEl.className = 'd-none mb-3';
                    progressEl.classList.remove('d-none');
                    errorsEl.classList.add('d-none');
                    document.getElementById('importCsvSubmitBtn').disabled = true;
                    try {
                        const res = await fetch(importUrl, {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();
                        progressEl.classList.add('d-none');
                        if (res.ok) {
                            alertEl.className = 'alert alert-success mb-3';
                            alertEl.textContent = data.message || 'Import complete.';
                            if (data.errors && data.errors.length) {
                                errList.innerHTML = data.errors.map(x => '<li>' + escapeHtml(x) + '</li>')
                                    .join('');
                                errorsEl.classList.remove('d-none');
                            }
                            await loadHoldIssueRows();
                            if (historyTable) loadHoldIssueHistoryRows();
                        } else {
                            alertEl.className = 'alert alert-danger mb-3';
                            alertEl.textContent = data.message || 'Import failed.';
                        }
                    } catch (e) {
                        progressEl.classList.add('d-none');
                        alertEl.className = 'alert alert-danger mb-3';
                        alertEl.textContent = 'Network error. Please try again.';
                    } finally {
                        document.getElementById('importCsvSubmitBtn').disabled = false;
                    }
                });
            }

            // ── L30 Loss / Issues charts (Chart.js) ────────────────────────────
            let l30Data = null,
                l30FullChart = null;
            let l30IssuesData = null,
                l30IssuesFullChart = null;
            const l30IssuesDays = 30;

            function _l30CalcStats(vals) {
                const sorted = [...vals].sort((a, b) => a - b);
                const mid = Math.floor(sorted.length / 2);
                const median = sorted.length % 2 !== 0 ? sorted[mid] : ((sorted[mid - 1] ?? 0) + (sorted[mid] ?? 0)) /
                2;
                return {
                    min: sorted[0] ?? 0,
                    max: sorted[sorted.length - 1] ?? 0,
                    median
                };
            }
            const _medianLinePlugin = {
                id: 'l30MedianLine',
                afterDraw(chart) {
                    const median = chart._l30Median;
                    if (median == null) return;
                    const yScale = chart.scales.y,
                        xScale = chart.scales.x,
                        ctx = chart.ctx;
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

            function _makeValueLabelsPlugin(vals, fmt) {
                return {
                    id: 'l30ValueLabels',
                    afterDatasetsDraw(chart) {
                        const meta = chart.getDatasetMeta(0),
                            ctx = chart.ctx;
                        ctx.save();
                        ctx.font = 'bold 10px Inter,system-ui,sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        meta.data.forEach((pt, i) => {
                            const v = vals[i];
                            const color = i === 0 ? '#6c757d' : v > vals[i - 1] ? '#28a745' : v < vals[i - 1] ?
                                '#dc3545' : '#6c757d';
                            ctx.fillStyle = color;
                            const offsetY = (i % 2 === 0) ? -10 : -20;
                            ctx.fillText(fmt(v), pt.x, pt.y + offsetY);
                        });
                        ctx.restore();
                    }
                };
            }

            function _buildLineChart(canvasId, labels, vals, fmt, color, lineChart) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return null;
                if (lineChart) {
                    lineChart.destroy();
                    lineChart = null;
                }
                const {
                    min,
                    max,
                    median
                } = _l30CalcStats(vals);
                const range = max - min || 1;
                const dotColors = vals.map((v, i) => i === 0 ? '#6c757d' : v > vals[i - 1] ? '#28a745' : v < vals[i -
                    1] ? '#dc3545' : '#6c757d');
                const chart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            data: vals,
                            backgroundColor: color + '18',
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
                    plugins: [_medianLinePlugin, _makeValueLabelsPlugin(vals, fmt)],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                top: 28,
                                left: 2,
                                right: 2,
                                bottom: 2
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                titleFont: {
                                    size: 10
                                },
                                bodyFont: {
                                    size: 10
                                },
                                padding: 6,
                                callbacks: {
                                    label: ctx => {
                                        const parts = ['Value: ' + fmt(ctx.raw)];
                                        if (ctx.dataIndex > 0) {
                                            const diff = ctx.raw - vals[ctx.dataIndex - 1];
                                            parts.push('vs Yesterday: ' + (diff > 0 ? '▲' : diff < 0 ? '▼' :
                                                '▬') + ' ' + fmt(Math.abs(diff)));
                                        }
                                        return parts;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                min: Math.max(0, min - range * 0.1),
                                max: max + range * 0.1,
                                ticks: {
                                    font: {
                                        size: 9
                                    },
                                    callback: v => fmt(v)
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    font: {
                                        size: 8
                                    },
                                    autoSkip: labels.length > 20,
                                    maxTicksLimit: 31
                                }
                            }
                        }
                    }
                });
                chart._l30Median = median;
                return chart;
            }

            function _buildBarChart(canvasId, labels, vals, fmt, color, barChart) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return null;
                if (barChart) {
                    barChart.destroy();
                    barChart = null;
                }
                return new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            data: vals,
                            backgroundColor: color + 'cc',
                            borderColor: color,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        layout: {
                            padding: {
                                top: 4,
                                left: 2,
                                right: 2,
                                bottom: 16
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: ctx => fmt(ctx.raw)
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    font: {
                                        size: 7
                                    },
                                    autoSkip: false,
                                    maxTicksLimit: 31
                                }
                            },
                            y: {
                                ticks: {
                                    font: {
                                        size: 9
                                    },
                                    callback: v => fmt(v)
                                }
                            }
                        }
                    }
                });
            }
            async function loadL30Loss() {
                try {
                    let url = l30LossUrl;
                    if (activeDeptFilter) url += '?department=' + encodeURIComponent(activeDeptFilter);
                    const res = await fetch(url, {
                        headers: getHeaders
                    });
                    if (!res.ok) return;
                    l30Data = await res.json();
                    setText('l30-badge-total', '$' + Math.round(l30Data.total || 0));
                } catch (e) {
                    /* silent */ }
            }

            function renderL30LossChart(daily) {
                const labels = daily.map(d => d.date);
                const vals = daily.map(d => parseFloat(d.loss) || 0);
                const fmt = v => '$' + Math.round(v).toLocaleString('en-US');
                l30FullChart = _buildLineChart('l30LossLineChart', labels, vals, fmt, '#dc3545', l30FullChart);
                if (l30FullChart) {
                    const {
                        min,
                        max,
                        median
                    } = _l30CalcStats(vals);
                    setText('l30-loss-highest', fmt(max));
                    setText('l30-loss-median', fmt(median));
                    setText('l30-loss-lowest', fmt(min));
                }
            }
            async function loadL30Issues() {
                try {
                    let url = l30IssuesUrl + '?days=' + l30IssuesDays;
                    if (activeDeptFilter) url += '&department=' + encodeURIComponent(activeDeptFilter);
                    const res = await fetch(url, {
                        headers: getHeaders
                    });
                    if (!res.ok) return;
                    l30IssuesData = await res.json();
                    setText('l30-issues-badge-total', l30IssuesData.total || 0);
                    setText('l30-issues-badge-label', 'L' + l30IssuesDays);
                } catch (e) {
                    /* silent */ }
            }

            function renderL30IssuesChart(daily) {
                const labels = daily.map(d => d.date);
                const vals = daily.map(d => d.count);
                const fmt = v => Math.round(v).toLocaleString('en-US');
                l30IssuesFullChart = _buildLineChart('l30IssuesLineChart', labels, vals, fmt, '#0d6efd',
                    l30IssuesFullChart);
                if (l30IssuesFullChart) {
                    const {
                        min,
                        max,
                        median
                    } = _l30CalcStats(vals);
                    setText('l30-issues-highest', fmt(max));
                    setText('l30-issues-median', fmt(median));
                    setText('l30-issues-lowest', fmt(min));
                }
                _buildBarChart('l30IssuesBarChart', labels, vals, fmt, '#0d6efd', null);
            }

            function renderL30IssuesTable(daily) {
                const tbody = document.getElementById('l30-issues-table-body');
                const tfoot = document.getElementById('l30-issues-table-foot');
                if (!tbody) return;
                if (!daily.length) {
                    tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">No data.</td></tr>';
                    if (tfoot) tfoot.innerHTML = '';
                    return;
                }
                tbody.innerHTML = daily.slice().reverse().map(d => '<tr><td>' + escapeHtml(d.date) +
                    '</td><td class="text-end fw-semibold">' + d.count + '</td></tr>').join('');
                const total = daily.reduce((s, d) => s + (parseInt(d.count) || 0), 0);
                if (tfoot) tfoot.innerHTML = '<tr class="table-primary fw-bold"><td>Total (L' + l30IssuesDays +
                    ')</td><td class="text-end">' + total + '</td></tr>';
            }

            // ── UI wiring ──────────────────────────────────────────────────────
            function wireUi() {
                document.getElementById('dept-filter-select')?.addEventListener('change', (e) => {
                    activeDeptFilter = e.target.value || null;
                    applyFilters();
                    loadL30Loss();
                    loadL30Issues();
                });
                document.getElementById('ai-search')?.addEventListener('input', applyFilters);

                skuInput.addEventListener('input', () => {
                    clearTimeout(skuTimer);
                    skuTimer = setTimeout(() => refreshSkuSuggestions(skuInput.value), 220);
                });
                skuInput.addEventListener('focus', () => {
                    clearTimeout(skuTimer);
                    refreshSkuSuggestions(skuInput.value);
                });
                skuInput.addEventListener('change', fillSkuDetails);
                skuInput.addEventListener('blur', fillSkuDetails);

                issueInput.addEventListener('input', toggleRootCauseRemarkField);
                action1Input.addEventListener('input', function () {
                    toggleAction1RemarkField();
                    toggleActionSubsection();
                });
                action1Input.addEventListener('change', function () {
                    toggleAction1RemarkField();
                    toggleActionSubsection();
                });
                cAction1Input.addEventListener('input', toggleCAction1RemarkField);
                cAction1Input.addEventListener('change', toggleCAction1RemarkField);

                // Replacement / Alternate Sent SKU autocomplete + image / qty preview.
                const replSkuInput = document.getElementById('hold_issue_replacement_sku');
                if (replSkuInput) {
                    replSkuInput.addEventListener('input', function () {
                        clearTimeout(replacementSkuTimer);
                        replacementSkuTimer = setTimeout(function () {
                            // Reuse the main SKU search endpoint for autocomplete suggestions.
                            const q = String(replSkuInput.value || '').trim();
                            if (q.length < 1) {
                                document.getElementById('hold_issue_replacement_sku_datalist').innerHTML = '';
                                return;
                            }
                            fetch(skuSearchUrl + '?q=' + encodeURIComponent(q), { headers: getHeaders })
                                .then(r => r.json())
                                .then(data => {
                                    const list = Array.isArray(data?.skus) ? data.skus : [];
                                    const dl = document.getElementById('hold_issue_replacement_sku_datalist');
                                    if (dl) {
                                        dl.innerHTML = list.map(item => {
                                            const sku = item?.sku ?? '';
                                            const parent = item?.parent ?? '';
                                            const label = parent ? (parent + ' · ' + sku) : sku;
                                            return '<option value="' + escAttr(sku) + '" label="' + escAttr(label) + '"></option>';
                                        }).join('');
                                    }
                                })
                                .catch(() => { /* ignore */ });
                        }, 220);
                    });
                    replSkuInput.addEventListener('change', fillReplacementSkuDetails);
                    replSkuInput.addEventListener('blur', fillReplacementSkuDetails);
                }

                document.getElementById('add-root-cause-found-option')?.addEventListener('click', () =>
                    addRootCauseOption(issueInput, 'root_cause_found', 'hold_issue_root_cause_found_datalist'));
                document.getElementById('delete-root-cause-found-option')?.addEventListener('click', () =>
                    deleteRootCauseOption(issueInput, 'root_cause_found', 'hold_issue_root_cause_found_datalist'));
                document.getElementById('add-root-cause-fixed-option')?.addEventListener('click', () =>
                    addRootCauseOption(cAction1Input, 'root_cause_fixed', 'hold_issue_root_cause_fixed_datalist'));
                document.getElementById('delete-root-cause-fixed-option')?.addEventListener('click', () =>
                    deleteRootCauseOption(cAction1Input, 'root_cause_fixed', 'hold_issue_root_cause_fixed_datalist')
                    );
                document.getElementById('add-action-option')?.addEventListener('click', addActionOption);
                document.getElementById('delete-action-option')?.addEventListener('click', deleteActionOption);

                // Issue? dropdown wiring (custom add/delete + sub-section toggle).
                const whatHappenedSelect = document.getElementById('hold_issue_what_happened');
                if (whatHappenedSelect) {
                    whatHappenedSelect.addEventListener('change', toggleWhatHappenedSubsection);
                }
                document.getElementById('add-what-happened-option')?.addEventListener('click', addWhatHappenedOption);
                document.getElementById('delete-what-happened-option')?.addEventListener('click', deleteWhatHappenedOption);
                // "Why it happened?" dropdown inside the Wrong Item Sent panel.
                document.getElementById('add-wrong-sent-reason-option')?.addEventListener('click', addWrongSentReasonOption);
                document.getElementById('delete-wrong-sent-reason-option')?.addEventListener('click', deleteWrongSentReasonOption);

                // Wrong Item Sent: SKU autocomplete (reuses /skus search) + image/qty preview.
                const wrongSkuInput = document.getElementById('hold_issue_wrong_sent_sku');
                if (wrongSkuInput) {
                    wrongSkuInput.addEventListener('input', function () {
                        clearTimeout(wrongSkuTimer);
                        wrongSkuTimer = setTimeout(function () {
                            const q = String(wrongSkuInput.value || '').trim();
                            const dl = document.getElementById('hold_issue_wrong_sent_sku_datalist');
                            if (!dl) return;
                            if (q.length < 1) { dl.innerHTML = ''; return; }
                            fetch(skuSearchUrl + '?q=' + encodeURIComponent(q), { headers: getHeaders })
                                .then(r => r.json())
                                .then(data => {
                                    const list = Array.isArray(data?.skus) ? data.skus : [];
                                    dl.innerHTML = list.map(item => {
                                        const sku = item?.sku ?? '';
                                        const parent = item?.parent ?? '';
                                        const label = parent ? (parent + ' · ' + sku) : sku;
                                        return '<option value="' + escAttr(sku) + '" label="' + escAttr(label) + '"></option>';
                                    }).join('');
                                })
                                .catch(() => { /* ignore */ });
                        }, 220);
                    });
                    wrongSkuInput.addEventListener('change', fillWrongSentSkuDetails);
                    wrongSkuInput.addEventListener('blur', fillWrongSentSkuDetails);
                }

                // Wrong Item Sent: live char counter on the notes textarea (max 200).
                const issueNotesEl = document.getElementById('hold_issue_issue_notes');
                if (issueNotesEl) {
                    const cnt = document.getElementById('issueNotesCharCount');
                    issueNotesEl.addEventListener('input', function () {
                        if (cnt) cnt.textContent = String(issueNotesEl.value.length);
                    });
                }

                // Generic issue notes (Damaged / 0 Stock / custom): same live counter.
                const customIssueNotesEl = document.getElementById('hold_issue_custom_issue_notes');
                if (customIssueNotesEl) {
                    const cnt = document.getElementById('customIssueNotesCharCount');
                    customIssueNotesEl.addEventListener('input', function () {
                        if (cnt) cnt.textContent = String(customIssueNotesEl.value.length);
                    });
                }

                // Wrong Quantity Sent: reveal qty inputs once a radio is picked.
                Array.from(document.getElementsByName('qty_mismatch_type')).forEach(r => {
                    r.addEventListener('change', refreshQtyMismatchVisibility);
                });

                // Outgoing needed checkbox: reveal warehouse picker when on.
                document.getElementById('hold_issue_outgoing_needed')?.addEventListener('change', toggleOutgoingWarehouseVisibility);
                // Wrong Item Sent → its own outgoing checkbox + warehouse picker.
                document.getElementById('hold_issue_wrong_sent_outgoing_needed')
                    ?.addEventListener('change', toggleWrongSentOutgoingWarehouseVisibility);

                form.addEventListener('submit', submitIssueForm);
                modalEl.addEventListener('hidden.bs.modal', resetForm);

                // Dropdown options (Root Cause Found/Fixed) are only needed when the modal opens.
                let dropdownOptionsLoaded = false;
                modalEl.addEventListener('show.bs.modal', function() {
                    if (dropdownOptionsLoaded) return;
                    dropdownOptionsLoaded = true;
                    initializeDynamicRootCauseOptions();
                });

                document.getElementById('btnShowHistory').addEventListener('click', () => {
                    const card = document.getElementById('holdIssueHistoryCard');
                    card.classList.remove('d-none');
                    loadHoldIssueHistoryRows();
                    // The history card was display:none, so its wrapper had no
                    // measurable position; size it now that it's visible.
                    fitAllIssuesTables();
                    card.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                });
                document.getElementById('btnExportCsv').addEventListener('click', exportCsv);

                wireAddSkuRow();
                wireImportCsv();

                // Paste image into modal → Image 1 then Image 2
                modalEl.addEventListener('paste', function(pe) {
                    const items = pe.clipboardData && pe.clipboardData.items;
                    if (!items) return;
                    for (let i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') === -1) continue;
                        const file = items[i].getAsFile();
                        if (!file) continue;
                        const inp1 = document.getElementById('hold_issue_image_1');
                        const inp2 = document.getElementById('hold_issue_image_2');
                        if (!inp1 || !inp2) return;
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        if (!inp1.files || inp1.files.length === 0) {
                            inp1.files = dt.files;
                        } else if (!inp2.files || inp2.files.length === 0) {
                            inp2.files = dt.files;
                        } else {
                            return;
                        }
                        pe.preventDefault();
                        return;
                    }
                });

                // Lazy-load Chart.js only on first L30 modal open (saves ~80 KB on first paint).
                let chartJsPromise = null;

                function ensureChartJs() {
                    if (window.Chart) return Promise.resolve();
                    if (!chartJsPromise) {
                        chartJsPromise = new Promise(function(resolve, reject) {
                            const s = document.createElement('script');
                            s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
                            s.async = true;
                            s.onload = resolve;
                            s.onerror = reject;
                            document.head.appendChild(s);
                        });
                    }
                    return chartJsPromise;
                }

                document.getElementById('l30LossModal')?.addEventListener('show.bs.modal', () => {
                    const daily = l30Data?.daily || [];
                    const rangeEl = document.getElementById('l30-modal-range');
                    if (rangeEl && l30Data) rangeEl.textContent = ' (' + l30Data.from + ' → ' + l30Data.to +
                    ')';
                    ensureChartJs().then(() => renderL30LossChart(daily));
                });
                document.getElementById('l30IssuesModal')?.addEventListener('show.bs.modal', () => {
                    const daily = l30IssuesData?.daily || [];
                    const rangeEl = document.getElementById('l30-issues-modal-range');
                    if (rangeEl && l30IssuesData) rangeEl.textContent = ' (' + l30IssuesData.from + ' → ' +
                        l30IssuesData.to + ')';
                    ensureChartJs().then(() => {
                        renderL30IssuesChart(daily);
                        renderL30IssuesTable(daily);
                    });
                });
            }

            // ── Boot ───────────────────────────────────────────────────────────
            document.addEventListener('DOMContentLoaded', function() {
                buildDepartmentDropdown();
                toggleRootCauseRemarkField();
                toggleAction1RemarkField();
                toggleCAction1RemarkField();
                wireUi();
                // Size the table wrappers to the viewport before fetching rows
                // so the placeholder loader sits in the correct area.
                fitAllIssuesTables();
                window.addEventListener('resize', scheduleFitAllIssuesTables);
                window.addEventListener('orientationchange', scheduleFitAllIssuesTables);
                // Toolbar can wrap to multiple lines (it has many buttons + badges)
                // which changes the offset of the table wrapper; watch the card
                // for size changes and re-fit.
                if (typeof ResizeObserver !== 'undefined') {
                    const cardEl = document.querySelector('#all-issues-table-wrapper')?.closest('.card');
                    if (cardEl) {
                        const ro = new ResizeObserver(scheduleFitAllIssuesTables);
                        ro.observe(cardEl);
                    }
                }
                // Fetch first, then build the Tabulator with the resolved rows
                // (overallAmazon-style). The custom #ai-main-loader overlay is
                // already visible in the DOM, so the user sees feedback immediately.
                loadHoldIssueRows();
            });
        })();
    </script>
@endsection
