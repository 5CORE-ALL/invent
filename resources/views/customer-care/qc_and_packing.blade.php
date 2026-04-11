@extends('layouts.vertical', ['title' => $pageTitle ?? 'QC And Packing', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .orders-hold-table {
            table-layout: auto;
            width: 100%;
            font-size: 12px;
            border: 1px solid #dee2e6;
            border-collapse: collapse;
        }

        .orders-hold-table-wrap {
            overflow-x: auto;
            width: 100%;
        }

        .orders-hold-table th,
        .orders-hold-table td {
            padding: 0.45rem 0.4rem;
            vertical-align: middle;
            border: 1px solid #dee2e6;
            text-align: center;
        }

        .orders-hold-table th {
            white-space: nowrap;
        }

        .orders-hold-table td {
            word-break: break-word;
            white-space: normal;
        }

        .orders-hold-col-idx {
            width: 4%;
        }

        .orders-hold-col-sku {
            width: 9%;
            max-width: 80px;
        }

        .orders-hold-col-img {
            width: 42px;
            min-width: 42px;
            padding: 2px !important;
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
            font-size: 16px;
        }

        .sku-cell {
            display: block;
            max-width: 80px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: default;
            transition: max-width 0.2s ease;
        }

        td:hover .sku-cell {
            max-width: 300px;
            overflow: visible;
            white-space: normal;
            word-break: break-all;
        }

        .orders-hold-col-date {
            min-width: 75px;
            width: 7%;
            white-space: nowrap;
        }

        .orders-hold-col-qty {
            width: 5%;
        }

        .orders-hold-col-parent {
            width: 10%;
        }

        .orders-hold-col-dept {
            width: 7%;
            min-width: 70px;
        }

        /* ── Department dropdown filter ──────────────────────────── */
        #dept-filter-select {
            font-size: 12px;
            padding: 2px 8px;
            height: 28px;
            min-width: 130px;
            border-radius: 0.375rem;
            border: 1px solid #dee2e6;
            cursor: pointer;
        }

        .orders-hold-col-mp {
            width: 8%;
        }

        .orders-hold-col-issue {
            width: 20%;
            min-width: 260px;
        }

        .orders-hold-col-created-by {
            width: 8%;
        }

        .orders-hold-col-created-at {
            width: 9%;
        }

        .orders-hold-col-action {
            width: 9%;
        }

        .order-num-cell {
            white-space: nowrap;
            position: relative;
        }

        .order-num-short {
            display: inline-block;
            max-width: 0;
            overflow: hidden;
            vertical-align: bottom;
            white-space: nowrap;
            opacity: 0;
            transition: max-width 0.25s ease, opacity 0.2s ease;
        }

        .order-num-cell:hover .order-num-short {
            max-width: 30ch;
            opacity: 1;
        }

        .copy-order-btn {
            color: #0d6efd;
            font-size: 0.8rem;
            line-height: 1;
            padding: 0 2px;
            border: none;
            background: none;
            cursor: pointer;
            vertical-align: middle;
            transition: color 0.15s;
        }

        .copy-order-btn:hover {
            color: #0a58ca;
        }

        .copy-order-btn.copied {
            color: #198754;
        }

        /* ── Tracking(R) cell ─────────────────────────────────────── */
        .tracking-cell {
            white-space: nowrap;
            cursor: default;
        }

        .tracking-dot {
            font-size: 1.1em;
            color: #6c757d;
            letter-spacing: 0;
        }

        .tracking-full {
            display: none;
            font-size: 0.8em;
            vertical-align: middle;
        }

        .copy-tracking-btn {
            display: none;
            color: #0d6efd;
            font-size: 0.75rem;
            line-height: 1;
            padding: 0 2px;
            border: none;
            background: none;
            cursor: pointer;
            vertical-align: middle;
        }

        .copy-tracking-btn:hover { color: #0a58ca; }
        .copy-tracking-btn.copied { color: #198754; }

        .tracking-cell:hover .tracking-dot  { display: none; }
        .tracking-cell:hover .tracking-full { display: inline; }
        .tracking-cell:hover .copy-tracking-btn { display: inline; }

        .orders-hold-col-what {
            width: 5%;
        }

        .orders-hold-col-close {
            width: 7%;
            text-align: center;
        }

        .hold-action-btn {
            width: 20px;
            height: 20px;
            padding: 0;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            flex: 0 0 20px;
            line-height: 1;
            border: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            border-radius: 0;
        }

        .hold-action-btn i {
            font-size: 16px;
            line-height: 1;
        }

        .hold-edit-btn {
            color: #0dcaf0 !important;
        }

        .hold-archive-btn {
            color: #dc3545 !important;
        }

        .hold-close-actions {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin: 0 auto;
            width: fit-content;
            max-width: 100%;
        }

        .orders-hold-close-cell {
            padding: 0.3rem 0.25rem !important;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
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

        .sku-image-preview {
            width: 88px;
            height: 88px;
            object-fit: contain;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background: #fff;
            padding: 4px;
        }

        .action-icon-hints {
            margin-top: 6px;
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            color: #6c757d;
            font-size: 12px;
        }

        .action-icon-hints i {
            font-size: 15px;
            vertical-align: middle;
            margin-right: 4px;
        }

        /* ── L30 Loss Badge ───────────────────────────────────────── */
        .l30-badge,
        .l30-issues-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 0.4rem;
            cursor: pointer;
            user-select: none;
            border: 1.5px solid;
            background: #fff;
            white-space: nowrap;
            transition: box-shadow 0.15s;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            min-width: 0;
        }

        .l30-badge {
            border-color: #e05252;
            color: #c0392b;
        }

        .l30-badge:hover {
            box-shadow: 0 2px 8px rgba(224,82,82,.2);
        }

        .l30-issues-badge {
            border-color: #4a9e6b;
            color: #27693e;
        }

        .l30-issues-badge:hover {
            box-shadow: 0 2px 8px rgba(74,158,107,.2);
        }

        #l30-sparkline-container,
        #l30-issues-sparkline-container {
            display: none;
        }

        /* ── Period filter pills ──────────────────────────────────── */
        .l30-period-pills { display: none; }
        .l30-period-pill  { display: none; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $pageTitle ?? 'QC And Packing',
        'sub_title' => 'Customer Care',
    ])

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                <button type="button" class="btn btn-primary flex-fill" data-bs-toggle="modal" data-bs-target="#ordersOnHoldIssueModal">
                    <i class="bi bi-plus-lg me-1"></i> {{ $addIssueButtonText ?? 'Add QC & Packing Issue' }}
                </button>
                <button type="button" class="btn btn-outline-secondary flex-fill" id="btnShowHistory">
                    <i class="bi bi-clock-history me-1"></i> History
                </button>
                <button type="button" class="btn btn-success flex-fill" id="btnExportCsv">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
                </button>
                <button type="button" class="btn btn-outline-info flex-fill" id="btnImportCsv">
                    <i class="bi bi-upload me-1"></i> Import CSV
                </button>
                @if($showDispatchExtras ?? false)
                <div id="l30-loss-badge" class="l30-badge flex-fill justify-content-center" role="button"
                     data-bs-toggle="modal" data-bs-target="#l30LossModal"
                     title="Last 30 Days Loss — click for detail">
                    <i class="bi bi-graph-down-arrow"></i>
                    L30 Loss: <span id="l30-badge-total">…</span>
                </div>
                <div id="l30-issues-badge" class="l30-issues-badge flex-fill justify-content-center" role="button"
                     data-bs-toggle="modal" data-bs-target="#l30IssuesModal"
                     title="Last 30 Days Issues — click for detail">
                    <i class="bi bi-exclamation-circle"></i>
                    <span id="l30-issues-badge-label">L30</span> Issues: <span id="l30-issues-badge-total">…</span>
                </div>
                @endif
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-0">{{ $introText ?? 'Use Add QC & Packing Issue to record SKU issues. SKU lookup auto-fills Parent and available QTY.' }}</p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">{{ $recordsTitle ?? 'QC And Packing Records' }}</h5>
                    <div class="d-flex align-items-center gap-2">
                        @if($showDispatchExtras ?? false)
                        <select id="dept-filter-select" class="form-select form-select-sm">
                            <option value="">All Departments</option>
                        </select>
                        @endif
                        <span class="badge bg-light text-dark" id="hold_issue_total_count">0</span>
                    </div>
                </div>
                @if($showDispatchExtras ?? false)
                <div id="dept-filter-bar" style="display:none;"></div>
                @endif
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm mb-0 orders-hold-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="orders-hold-col-idx">#</th>
                                    <th class="orders-hold-col-img"></th>
                                    <th class="orders-hold-col-sku">SKU</th>
                                    @if($showDispatchExtras ?? false)
                                    <th class="orders-hold-col-action">Order #</th>
                                    <th class="orders-hold-col-action">Loss $</th>
                                    @elseif($showOrderIdField ?? false)
                                    <th class="orders-hold-col-action">{{ $orderIdFieldLabel ?? 'Order ID' }}</th>
                                    @endif
                                    <th class="orders-hold-col-qty">Order Qty</th>
                                    <th class="orders-hold-col-mp">MKT</th>
                                    <th class="orders-hold-col-what">What?</th>
                                    <th class="orders-hold-col-action">Action</th>
                                    <th class="orders-hold-col-action">Track</th>
                                    <th class="orders-hold-col-issue">Root Cause<br>Found</th>
                                    <th class="orders-hold-col-action">Root Cause Fixed</th>
                                    <th class="orders-hold-col-dept">Dept</th>
                                    <th class="orders-hold-col-close">Close</th>
                                    <th class="orders-hold-col-created-by">Created By</th>
                                    <th class="orders-hold-col-created-at">Created At</th>
                                </tr>
                            </thead>
                            <tbody id="hold_issue_table_body">
                                <tr id="hold_issue_empty_row">
                                    <td colspan="{{ ($showDispatchExtras ?? false) ? 17 : (($showOrderIdField ?? false) ? 16 : 15) }}" class="text-center text-muted py-4">No records found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mt-3 d-none" id="holdIssueHistoryCard">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Order History</h5>
                    <span class="badge bg-light text-dark" id="hold_issue_history_total_count">0</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm mb-0 orders-hold-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="orders-hold-col-idx">#</th>
                                    <th class="orders-hold-col-img"></th>
                                    <th class="orders-hold-col-sku">SKU</th>
                                    @if($showOrderIdField ?? false)
                                    <th class="orders-hold-col-action">{{ $orderIdFieldLabel ?? 'Order ID' }}</th>
                                    @endif
                                    <th class="orders-hold-col-qty">Order Qty</th>
                                    <th class="orders-hold-col-mp">MKT</th>
                                    <th class="orders-hold-col-what">What?</th>
                                    <th class="orders-hold-col-action">Action</th>
                                    <th class="orders-hold-col-action">Track</th>
                                    <th class="orders-hold-col-issue">Root Cause<br>Found</th>
                                    <th class="orders-hold-col-action">Root Cause Fixed</th>
                                    <th class="orders-hold-col-dept">Dept</th>
                                    <th class="orders-hold-col-action">Close</th>
                                    <th class="orders-hold-col-action">Event</th>
                                    <th class="orders-hold-col-created-by">Created By</th>
                                    <th class="orders-hold-col-created-at">Logged At</th>
                                </tr>
                            </thead>
                            <tbody id="hold_issue_history_table_body">
                                <tr id="hold_issue_history_empty_row">
                                    <td colspan="{{ ($showOrderIdField ?? false) ? 17 : 16 }}" class="text-center text-muted py-4">No history found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Import CSV Modal ── --}}
    <div class="modal fade" id="importCsvModal" tabindex="-1" aria-labelledby="importCsvModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importCsvModalLabel"><i class="bi bi-upload me-2"></i>Import CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="importCsvAlert" class="d-none mb-3"></div>
                    <p class="text-muted small mb-2">
                        Upload a CSV file with the following columns (header row required):<br>
                        <code>@if($showOrderIdField ?? false)
                            sku, order_number (or order id / order_id), qty, order_qty, parent, marketplace_1, what_happened, action_1, action_1_remark, replacement_tracking, issue, issue_remark, c_action_1, c_action_1_remark, department
                        @else
                            sku, qty, order_qty, parent, marketplace_1, what_happened, action_1, action_1_remark, replacement_tracking, issue, issue_remark, c_action_1, c_action_1_remark, department
                        @endif</code>
                    </p>
                    <p class="text-muted small mb-3">
                        Required: <strong>sku</strong>, <strong>qty</strong>, <strong>issue</strong> (Root Cause Found). All other columns are optional.
                    </p>
                    <div class="mb-3">
                        <label for="importCsvFile" class="form-label">CSV File</label>
                        <input type="file" class="form-control" id="importCsvFile" accept=".csv,.txt">
                    </div>
                    <div id="importCsvProgress" class="d-none">
                        <div class="progress mb-2">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" style="width:100%"></div>
                        </div>
                        <p class="text-muted small text-center">Uploading…</p>
                    </div>
                    <div id="importCsvErrors" class="d-none">
                        <p class="fw-semibold small mb-1 text-warning">Skipped rows:</p>
                        <ul id="importCsvErrorList" class="small text-warning mb-0" style="max-height:160px;overflow-y:auto;"></ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="importCsvSampleLink" class="btn btn-sm btn-outline-secondary me-auto">
                        <i class="bi bi-download me-1"></i> Download Sample
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" id="importCsvSubmitBtn">
                        <i class="bi bi-upload me-1"></i> Import
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ordersOnHoldIssueModal" tabindex="-1" aria-labelledby="ordersOnHoldIssueModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ordersOnHoldIssueModalLabel">{{ $modalTitle ?? 'QC And Packing Issue' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="ordersOnHoldIssueForm" autocomplete="off">
                    <div class="modal-body">
                        <div id="ordersOnHoldIssueAlert" class="alert alert-danger d-none mb-3" role="alert"></div>

                        <div class="row g-3">
                            {{-- ── SKU Row 1 (always shown) ── --}}
                            <div class="col-12" id="sku-rows-wrapper">
                                <div class="sku-entry-row" data-row-index="0">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-8">
                                            <label class="form-label">SKU <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control sku-entry-input" id="hold_issue_sku" name="sku"
                                                list="hold_issue_sku_datalist" placeholder="Search SKU" required autocomplete="off">
                                            <datalist id="hold_issue_sku_datalist"></datalist>
                                            <div class="mt-1 d-none" id="hold_issue_sku_image_wrap">
                                                <img src="" alt="SKU Image" id="hold_issue_sku_image" class="sku-image-preview">
                                            </div>
                                        </div>
                                        <div style="display:none;">
                                            <input type="number" class="form-control sku-entry-qty" id="hold_issue_qty" name="qty" value="0" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Order Qty</label>
                                            <input type="number" class="form-control sku-entry-order-qty" id="hold_issue_order_qty" name="order_qty"
                                                min="0" step="1" placeholder="Qty">
                                        </div>
                                        <div style="display:none;">
                                            <input type="text" class="form-control sku-entry-parent" id="hold_issue_parent" name="parent" readonly>
                                        </div>
                                    </div>
                                </div>

                                @if($showDispatchExtras ?? false)
                                {{-- Extra SKU rows container (dispatch issues only) --}}
                                <div id="extra-sku-rows-container" class="mt-2"></div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="btn-add-sku-row">
                                    <i class="bi bi-plus-circle me-1"></i> Add Another SKU
                                </button>
                                <div class="mt-1">
                                    <small class="text-muted">Multiple SKUs for the same order are grouped and counted as <strong>1 error</strong>.</small>
                                </div>
                                @endif
                            </div>

                            @if($showDispatchExtras ?? false)
                            <div class="col-md-6">
                                <label for="hold_issue_order_number" class="form-label">Order Number</label>
                                <input type="text" class="form-control" id="hold_issue_order_number" name="order_number"
                                    placeholder="Enter order number">
                            </div>
                            <div class="col-md-6">
                                <label for="hold_issue_total_loss" class="form-label">Loss $</label>
                                <input type="number" class="form-control" id="hold_issue_total_loss" name="total_loss"
                                    step="0.01" placeholder="0.00">
                            </div>
                            @elseif($showOrderIdField ?? false)
                            <div class="col-md-6">
                                <label for="hold_issue_order_number" class="form-label">{{ $orderIdFieldLabel ?? 'Order ID' }}</label>
                                <input type="text" class="form-control" id="hold_issue_order_number" name="order_number"
                                    placeholder="Enter order ID">
                            </div>
                            @endif

                            <div class="col-md-6">
                                <label for="hold_issue_marketplace_1" class="form-label">MKT</label>
                                <input type="text" class="form-control" id="hold_issue_marketplace_1" name="marketplace_1"
                                    list="hold_issue_marketplace_datalist" placeholder="Select Marketplace">
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_what_happened" class="form-label">What?</label>
                                <input type="text" class="form-control" id="hold_issue_what_happened" name="what_happened"
                                    placeholder="e.g. 0 Stock, Damaged">
                            </div>

                            <datalist id="hold_issue_marketplace_datalist">
                                @foreach (($marketplaces ?? collect()) as $marketplace)
                                    <option value="{{ $marketplace }}"></option>
                                @endforeach
                            </datalist>

                            <div class="col-md-6">
                                <label for="hold_issue_action_1" class="form-label">Action</label>
                                <input type="text" class="form-control" id="hold_issue_action_1" name="action_1"
                                    list="hold_issue_action_datalist"
                                    placeholder="Type or select action..." autocomplete="off">
                                <datalist id="hold_issue_action_datalist">
                                    <option value="Offer Customer Alterntive / Updgrade"></option>
                                    <option value="Upgraded + Stock Alternate"></option>
                                    <option value="Alternate Sent + Stock Alternate"></option>
                                    <option value="Sent Wrong Item + Stock Outgoing"></option>
                                    <option value="Cancelled"></option>
                                    <option value="Other"></option>
                                </datalist>
                            </div>

                            <div class="col-md-6" id="action1RemarkWrap">
                                <label for="hold_issue_action_1_remark" class="form-label">Action Remark
                                    <span id="action1RemarkRequiredStar" class="text-danger d-none" aria-hidden="true">*</span></label>
                                <input type="text" class="form-control" id="hold_issue_action_1_remark" name="action_1_remark"
                                    placeholder="Write action remark...">
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_replacement_tracking" class="form-label">Replacement Tracking Number</label>
                                <input type="text" class="form-control" id="hold_issue_replacement_tracking"
                                    name="replacement_tracking" maxlength="50" placeholder="Optional tracking number">
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_text" class="form-label">Root Cause Found <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="hold_issue_text" name="issue"
                                    list="hold_issue_root_cause_found_datalist"
                                    placeholder="Type or select root cause..." autocomplete="off" required>
                                <datalist id="hold_issue_root_cause_found_datalist"></datalist>
                            </div>

                            <div class="col-12 d-none" id="rootCauseRemarkWrap">
                                <label for="hold_issue_remark" class="form-label">Root Cause Remark <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="hold_issue_remark" name="issue_remark"
                                    placeholder="Write remark for Other">
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_c_action_1" class="form-label">Root Cause Fixed</label>
                                <input type="text" class="form-control" id="hold_issue_c_action_1" name="c_action_1"
                                    list="hold_issue_root_cause_fixed_datalist"
                                    placeholder="Type or select fix..." autocomplete="off">
                                <datalist id="hold_issue_root_cause_fixed_datalist"></datalist>
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_department" class="form-label">Department</label>
                                <select class="form-select" id="hold_issue_department" name="department">
                                    <option value="">— Select —</option>
                                    <option value="Dispatch">Dispatch</option>
                                    <option value="Shipping">Shipping</option>
                                    <option value="Listing">Listing</option>
                                    <option value="Carrier">Carrier</option>
                                    <option value="Customer Care">Customer Care</option>
                                    <option value="Pricing">Pricing</option>
                                    <option value="QC">QC</option>
                                    <option value="Packaging">Packaging</option>
                                </select>
                            </div>

                            <div class="col-12 d-none" id="cAction1RemarkWrap">
                                <label for="hold_issue_c_action_1_remark" class="form-label">Root Cause Fixed Remark <span class="text-danger">*</span></label>
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

    @if($showDispatchExtras ?? false)
    {{-- ── L30 Loss Modal ───────────────────────────────────────────────── --}}
    <div class="modal fade" id="l30LossModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none" style="max-width:98vw;width:98vw;margin:10px auto 0;">
            <div class="modal-content" style="border-radius:8px;overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="bi bi-graph-down-arrow me-1"></i>
                        L30 Loss
                        <small id="l30-modal-range" style="font-size:10px;opacity:.8;"></small>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-2">
                    <div style="height:240px;display:flex;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;">
                            <canvas id="l30LossLineChart"></canvas>
                        </div>
                        <div style="width:90px;display:flex;flex-direction:column;justify-content:center;gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;">
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#dc3545;margin-bottom:1px;">Highest</div>
                                <div id="l30-loss-highest" style="font-size:14px;font-weight:700;color:#dc3545;">-</div>
                            </div>
                            <div style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;margin-bottom:1px;">Median</div>
                                <div id="l30-loss-median" style="font-size:14px;font-weight:700;color:#6c757d;">-</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#198754;margin-bottom:1px;">Lowest</div>
                                <div id="l30-loss-lowest" style="font-size:14px;font-weight:700;color:#198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div style="height:150px;margin-top:8px;">
                        <canvas id="l30LossBarChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── L30 Issues Modal ─────────────────────────────────────────────── --}}
    <div class="modal fade" id="l30IssuesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none" style="max-width:98vw;width:98vw;margin:10px auto 0;">
            <div class="modal-content" style="border-radius:8px;overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        L30 Issues
                        <small id="l30-issues-modal-range" style="font-size:10px;opacity:.8;"></small>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-2">
                    <div style="height:240px;display:flex;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;">
                            <canvas id="l30IssuesLineChart"></canvas>
                        </div>
                        <div style="width:90px;display:flex;flex-direction:column;justify-content:center;gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;">
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#dc3545;margin-bottom:1px;">Highest</div>
                                <div id="l30-issues-highest" style="font-size:14px;font-weight:700;color:#dc3545;">-</div>
                            </div>
                            <div style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;margin-bottom:1px;">Median</div>
                                <div id="l30-issues-median" style="font-size:14px;font-weight:700;color:#6c757d;">-</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#198754;margin-bottom:1px;">Lowest</div>
                                <div id="l30-issues-lowest" style="font-size:14px;font-weight:700;color:#198754;">-</div>
                            </div>
                        </div>
                    </div>
                    <div style="height:150px;margin-top:8px;">
                        <canvas id="l30IssuesBarChart"></canvas>
                    </div>
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
                                <tr><td colspan="2" class="text-center text-muted py-3">Loading…</td></tr>
                            </tbody>
                            <tfoot id="l30-issues-table-foot"></tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const skuSearchUrl = @json(route('customer.care.followups.skus'));
            const currentUserEmail = @json(auth()->user()?->email ?? '');
            const skuDetailsUrl = @json($skuDetailsUrl ?? route('customer.care.qc.and.packing.sku.details'));
            const recordsListUrl = @json($recordsListUrl ?? route('customer.care.qc.and.packing.issues.index'));
            const recordsStoreUrl = @json($recordsStoreUrl ?? route('customer.care.qc.and.packing.issues.store'));
            const recordsUpdateBaseUrl = @json($recordsUpdateBaseUrl ?? url('/customer-care/qc-and-packing/issues'));
            const historyListUrl = @json($historyListUrl ?? route('customer.care.qc.and.packing.history.index'));
            const dropdownOptionsListUrl = @json($dropdownOptionsListUrl ?? route('customer.care.qc.and.packing.dropdown.options.index'));
            const dropdownOptionsStoreUrl = @json($dropdownOptionsStoreUrl ?? route('customer.care.qc.and.packing.dropdown.options.store'));
            const dropdownOptionsDeleteUrl = @json($dropdownOptionsDeleteUrl ?? route('customer.care.qc.and.packing.dropdown.options.delete'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

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
            const addRootCauseFoundOptionBtn = document.getElementById('add-root-cause-found-option');
            const deleteRootCauseFoundOptionBtn = document.getElementById('delete-root-cause-found-option');
            const action1Input = document.getElementById('hold_issue_action_1');
            const action1RemarkInput = document.getElementById('hold_issue_action_1_remark');
            const action1RemarkWrap = document.getElementById('action1RemarkWrap');
            const replacementTrackingInput = document.getElementById('hold_issue_replacement_tracking');
            const cAction1Input = document.getElementById('hold_issue_c_action_1');
            const cAction1RemarkInput = document.getElementById('hold_issue_c_action_1_remark');
            const departmentInput = document.getElementById('hold_issue_department');
            const cAction1RemarkWrap = document.getElementById('cAction1RemarkWrap');
            const addRootCauseFixedOptionBtn = document.getElementById('add-root-cause-fixed-option');
            const deleteRootCauseFixedOptionBtn = document.getElementById('delete-root-cause-fixed-option');
            const form = document.getElementById('ordersOnHoldIssueForm');
            const alertBox = document.getElementById('ordersOnHoldIssueAlert');
            const tableBody = document.getElementById('hold_issue_table_body');
            const emptyRow = document.getElementById('hold_issue_empty_row');
            const totalCountEl = document.getElementById('hold_issue_total_count');
            const historyTotalCountEl = document.getElementById('hold_issue_history_total_count');
            const modalEl = document.getElementById('ordersOnHoldIssueModal');
            const historyTableBody = document.getElementById('hold_issue_history_table_body');
            const historyEmptyRow = document.getElementById('hold_issue_history_empty_row');
            const historyCard = document.getElementById('holdIssueHistoryCard');
            const btnShowHistory = document.getElementById('btnShowHistory');

            let skuTimer = null;
            let holdIssueRows = [];
            let holdIssueHistoryRows = [];
            let editingIssueId = null;
            let activeDeptFilter = null;

            function getFilteredRows() {
                if (!activeDeptFilter) return holdIssueRows;
                return holdIssueRows.filter(r => (r.department || '') === activeDeptFilter);
            }

            function buildDeptFilters() {
                const sel = document.getElementById('dept-filter-select');
                if (!sel) return;
                const counts = {};
                holdIssueRows.forEach(r => {
                    const d = r.department || '';
                    if (d) counts[d] = (counts[d] || 0) + 1;
                });
                const depts = Object.keys(counts).sort();
                const prev = sel.value;
                sel.innerHTML = '<option value="">All Departments (' + holdIssueRows.length + ')</option>';
                depts.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d;
                    opt.textContent = d + ' (' + counts[d] + ')';
                    if (d === prev) opt.selected = true;
                    sel.appendChild(opt);
                });
                if (prev && !counts[prev]) {
                    activeDeptFilter = null;
                    sel.value = '';
                }
            }

            document.getElementById('dept-filter-select')?.addEventListener('change', (e) => {
                activeDeptFilter = e.target.value || null;
                renderRows();
                loadL30Loss();
                loadL30Issues();
            });

            function escAttr(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;');
            }

            function showAlert(message, type = 'danger') {
                alertBox.textContent = message;
                alertBox.classList.remove('alert-danger', 'alert-success');
                alertBox.classList.add(type === 'success' ? 'alert-success' : 'alert-danger');
                alertBox.classList.remove('d-none');
            }

            function hideAlert() {
                alertBox.classList.add('d-none');
                alertBox.textContent = '';
            }

            function escapeHtml(value) {
                const el = document.createElement('div');
                el.textContent = String(value ?? '');
                return el.innerHTML;
            }

            function getStaticOptionValues(selectEl) {
                // No longer used (inputs with datalist don't need static option tracking)
                return [];
            }

            function mergeUniqueOptions(baseOptions, dynamicOptions) {
                const set = new Set();
                const merged = [];
                [...baseOptions, ...dynamicOptions].forEach((value) => {
                    const v = String(value || '').trim();
                    if (!v || set.has(v.toLowerCase())) return;
                    set.add(v.toLowerCase());
                    merged.push(v);
                });
                return merged;
            }

            function rebuildDatalistOptions(datalistId, options) {
                const dl = document.getElementById(datalistId);
                if (!dl) return;
                dl.innerHTML = options.map(v => `<option value="${escAttr(v)}"></option>`).join('');
            }

            async function fetchDropdownOptions(fieldType) {
                try {
                    const response = await fetch(`${dropdownOptionsListUrl}?field_type=${encodeURIComponent(fieldType)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        return [];
                    }
                    return Array.isArray(data?.data) ? data.data : [];
                } catch (error) {
                    return [];
                }
            }

            async function postDropdownOption(url, payload) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await response.json();
                return { response, data };
            }

            function trackingCellHtml(value) {
                const text = String(value || '').trim();
                if (!text) return '—';
                return '<span class="tracking-cell">' +
                    '<span class="tracking-dot">•</span>' +
                    '<span class="tracking-full">' + escapeHtml(text) + '</span>' +
                    '<button class="copy-tracking-btn" data-copy="' + escAttr(text) + '" title="Copy tracking number"><i class="bi bi-clipboard"></i></button>' +
                    '</span>';
            }

            function whatHappenedDotHtml(value) {
                const text = String(value || '').trim();
                if (!text) return '—';
                if (text.toLowerCase() === '0 stock') {
                    return '<span class="what-happened-dot" title="0 Stock"></span>';
                }
                if (text.toLowerCase() === 'damaged') {
                    return '<span class="what-happened-dot what-happened-dot-damaged" title="Damaged"></span>';
                }
                return escapeHtml(text);
            }

            function action1DisplayHtml(value, remark) {
                const action = String(value || '').trim();
                const rmk = String(remark || '').trim();
                if (!action) return rmk ? escapeHtml(rmk) : '—';
                return rmk ? escapeHtml(action + ': ' + rmk) : escapeHtml(action);
            }

            function rootCauseDisplayHtml(value, remark) {
                const root = String(value || '').trim();
                const rmk = String(remark || '').trim();
                if (!root) return rmk ? escapeHtml(rmk) : '—';
                return rmk ? escapeHtml(root + ': ' + rmk) : escapeHtml(root);
            }

            function rootCauseFixedDisplayHtml(value, remark) {
                const fixed = String(value || '').trim();
                const rmk = String(remark || '').trim();
                if (!fixed) return rmk ? escapeHtml(rmk) : '—';
                return rmk ? escapeHtml(fixed + ': ' + rmk) : escapeHtml(fixed);
            }

            function resetSkuImage() {
                if (!skuImage || !skuImageWrap) return;
                skuImage.setAttribute('src', '');
                skuImageWrap.classList.add('d-none');
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

            function toggleRootCauseRemarkField() {
                const selected = String(issueInput?.value || '').trim();
                const isOther = selected === 'Other';
                if (rootCauseRemarkWrap) {
                    rootCauseRemarkWrap.classList.toggle('d-none', !isOther);
                }
                if (issueRemarkInput) {
                    issueRemarkInput.required = isOther;
                    if (!isOther) issueRemarkInput.value = '';
                }
            }

            function toggleAction1RemarkField() {
                const selected = String(action1Input?.value || '').trim();
                const isOther = selected === 'Other';
                const star = document.getElementById('action1RemarkRequiredStar');
                if (star) {
                    star.classList.toggle('d-none', !isOther);
                }
                if (action1RemarkInput) {
                    action1RemarkInput.required = isOther;
                    if (!isOther) {
                        action1RemarkInput.setCustomValidity('');
                    }
                }
            }

            function toggleCAction1RemarkField() {
                const selected = String(cAction1Input?.value || '').trim();
                const isOther = selected === 'Other';
                if (cAction1RemarkWrap) {
                    cAction1RemarkWrap.classList.toggle('d-none', !isOther);
                }
                if (cAction1RemarkInput) {
                    cAction1RemarkInput.required = isOther;
                    if (!isOther) cAction1RemarkInput.value = '';
                }
            }

            async function addRootCauseOption(inputEl, fieldType, datalistId) {
                const newOption = prompt('Enter new option');
                const value = String(newOption || '').trim();
                if (!value) return;

                try {
                    const { response, data } = await postDropdownOption(dropdownOptionsStoreUrl, {
                        field_type: fieldType,
                        option_value: value,
                    });
                    if (!response.ok) {
                        showAlert(data?.message || 'Unable to add option.');
                        return;
                    }
                    // Add to datalist immediately
                    const dl = document.getElementById(datalistId);
                    if (dl && !Array.from(dl.options).some(o => o.value === value)) {
                        const opt = document.createElement('option');
                        opt.value = value;
                        dl.appendChild(opt);
                    }
                    inputEl.value = value;
                    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                } catch (error) {
                    showAlert('Unable to add option.');
                }
            }

            async function deleteRootCauseOption(inputEl, fieldType, datalistId) {
                const selected = String(inputEl?.value || '').trim();
                if (!selected) {
                    showAlert('Please type the value to delete first.');
                    return;
                }
                if (!confirm(`Delete "${selected}" from suggestions?`)) {
                    return;
                }

                try {
                    const { response, data } = await postDropdownOption(dropdownOptionsDeleteUrl, {
                        field_type: fieldType,
                        option_value: selected,
                    });
                    if (!response.ok) {
                        showAlert(data?.message || 'Unable to delete option.');
                        return;
                    }
                    // Remove from datalist
                    const dl = document.getElementById(datalistId);
                    if (dl) {
                        const opt = Array.from(dl.options).find(o => o.value === selected);
                        if (opt) opt.remove();
                    }
                    inputEl.value = '';
                    inputEl.dispatchEvent(new Event('input', { bubbles: true }));
                } catch (error) {
                    showAlert('Unable to delete option.');
                }
            }

            async function initializeDynamicRootCauseOptions() {
                const issueDynamic = await fetchDropdownOptions('root_cause_found');
                rebuildDatalistOptions('hold_issue_root_cause_found_datalist', issueDynamic);

                const fixedDynamic = await fetchDropdownOptions('root_cause_fixed');
                rebuildDatalistOptions('hold_issue_root_cause_fixed_datalist', fixedDynamic);
            }

            function updateTotalCount() {
                const filtered = getFilteredRows();
                const seenGroups = new Set();
                let errorCount = 0;
                filtered.forEach(r => {
                    if (r.group_id) {
                        if (!seenGroups.has(r.group_id)) { seenGroups.add(r.group_id); errorCount++; }
                    } else { errorCount++; }
                });
                totalCountEl.textContent = String(errorCount);
            }

            function renderRows() {
                if (!tableBody) return;
                const filtered = getFilteredRows();

                if (!filtered.length) {
                    if (emptyRow) emptyRow.classList.remove('d-none');
                    updateTotalCount();
                    return;
                }

                if (emptyRow) emptyRow.classList.add('d-none');

                const dataRowsHtml = filtered.map((row, index) => {
                    const buttonsHtml =
                        '<div class="hold-close-actions">' +
                        '<button type="button" class="btn btn-sm hold-action-btn hold-edit-btn" data-id="' + row.id +
                        '" title="Edit"><i class="bi bi-pencil-fill"></i></button>' +
                        '<br>' +
                        (currentUserEmail === 'president@5core.com' ?
                            '<button type="button" class="btn btn-sm hold-action-btn hold-archive-btn" data-id="' + row.id +
                            '" title="Archive"><i class="bi bi-archive-fill"></i></button>'
                        : '') +
                        '</div>';
                    // Group badge: show small colored pill for multi-SKU groups
                    const groupBadge = row.group_id
                        ? '<span class="badge bg-warning text-dark ms-1" style="font-size:0.7rem;" title="Grouped entry (1 error)">G</span>'
                        : '';
                    return '<tr>' +
                        '<td>' + escapeHtml(row.id) + '</td>' +
                        '<td class="orders-hold-col-img">' + (row.image_url ? '<img src="' + escAttr(row.image_url) + '" class="sku-thumb" alt="">' : '<span class="sku-thumb-placeholder"><i class="bi bi-image"></i></span>') + '</td>' +
                        '<td title="' + escAttr(row.sku) + '"><span class="sku-cell">' + escapeHtml(row.sku) + '</span>' + groupBadge + '</td>' +
                        @if($showDispatchExtras ?? false)
                        '<td class="order-num-cell">' + (row.order_number ? '<button class="copy-order-btn" data-copy="' + escAttr(row.order_number) + '" title="' + escAttr(row.order_number) + '"><i class="bi bi-clipboard"></i></button><span class="order-num-short">' + escapeHtml(row.order_number) + '</span>' : '—') + '</td>' +
                        '<td>' + (row.total_loss != null ? '$' + parseFloat(row.total_loss).toFixed(2) : '—') + '</td>' +
                        @elseif($showOrderIdField ?? false)
                        '<td class="order-num-cell">' + (row.order_number ? '<button class="copy-order-btn" data-copy="' + escAttr(row.order_number) + '" title="' + escAttr(row.order_number) + '"><i class="bi bi-clipboard"></i></button><span class="order-num-short">' + escapeHtml(row.order_number) + '</span>' : '—') + '</td>' +
                        @endif
                        '<td>' + (row.order_qty != null && row.order_qty !== '' ? escapeHtml(row.order_qty) : '—') + '</td>' +
                        '<td>' + escapeHtml(row.marketplace_1 || '—') + '</td>' +
                        '<td>' + whatHappenedDotHtml(row.what_happened) + '</td>' +
                        '<td>' + action1DisplayHtml(row.action_1, row.action_1_remark) + '</td>' +
                        '<td>' + trackingCellHtml(row.replacement_tracking) + '</td>' +
                        '<td>' + rootCauseDisplayHtml(row.issue, row.issue_remark) + '</td>' +
                        '<td>' + rootCauseFixedDisplayHtml(row.c_action_1, row.c_action_1_remark) + '</td>' +
                        '<td>' + escapeHtml(row.department || '—') + '</td>' +
                        '<td class="orders-hold-close-cell">' + buttonsHtml + '</td>' +
                        '<td>' + escapeHtml(row.created_by) + '</td>' +
                        '<td>' + escapeHtml(row.created_at) + '</td>' +
                        '</tr>';
                }).join('');

                tableBody.innerHTML = (emptyRow ? emptyRow.outerHTML : '') + dataRowsHtml;
                const nextEmpty = document.getElementById('hold_issue_empty_row');
                if (nextEmpty && holdIssueRows.length) nextEmpty.classList.add('d-none');
                updateTotalCount();
            }

            function updateHistoryTotalCount() {
                if (historyTotalCountEl) {
                    historyTotalCountEl.textContent = String(holdIssueHistoryRows.length);
                }
            }

            function renderHistoryRows() {
                if (!historyTableBody) return;

                if (!holdIssueHistoryRows.length) {
                    if (historyEmptyRow) historyEmptyRow.classList.remove('d-none');
                    updateHistoryTotalCount();
                    return;
                }

                if (historyEmptyRow) historyEmptyRow.classList.add('d-none');

                const dataRowsHtml = holdIssueHistoryRows.map((row, index) => {
                    return '<tr>' +
                        '<td>' + escapeHtml(row.issue_ref || row.orders_on_hold_issue_id || row.id) + '</td>' +
                        '<td class="orders-hold-col-img">' + (row.image_url ? '<img src="' + escAttr(row.image_url) + '" class="sku-thumb" alt="">' : '<span class="sku-thumb-placeholder"><i class="bi bi-image"></i></span>') + '</td>' +
                        '<td title="' + escAttr(row.sku) + '"><span class="sku-cell">' + escapeHtml(row.sku) + '</span></td>' +
                        @if($showOrderIdField ?? false)
                        '<td class="order-num-cell">' + (row.order_number ? '<button class="copy-order-btn" data-copy="' + escAttr(row.order_number) + '" title="' + escAttr(row.order_number) + '"><i class="bi bi-clipboard"></i></button><span class="order-num-short">' + escapeHtml(row.order_number) + '</span>' : '—') + '</td>' +
                        @endif
                        '<td>' + (row.order_qty != null && row.order_qty !== '' ? escapeHtml(row.order_qty) : '—') + '</td>' +
                        '<td>' + escapeHtml(row.marketplace_1 || '—') + '</td>' +
                        '<td>' + whatHappenedDotHtml(row.what_happened) + '</td>' +
                        '<td>' + action1DisplayHtml(row.action_1, row.action_1_remark) + '</td>' +
                        '<td>' + trackingCellHtml(row.replacement_tracking) + '</td>' +
                        '<td>' + rootCauseDisplayHtml(row.issue, row.issue_remark) + '</td>' +
                        '<td>' + rootCauseFixedDisplayHtml(row.c_action_1, row.c_action_1_remark) + '</td>' +
                        '<td>' + escapeHtml(row.department || '—') + '</td>' +
                        '<td>' + escapeHtml(row.close_note) + '</td>' +
                        '<td>' + escapeHtml(row.event_type) + '</td>' +
                        '<td>' + escapeHtml(row.created_by) + '</td>' +
                        '<td>' + escapeHtml(row.logged_at) + '</td>' +
                        '</tr>';
                }).join('');

                historyTableBody.innerHTML = (historyEmptyRow ? historyEmptyRow.outerHTML : '') + dataRowsHtml;
                const nextEmpty = document.getElementById('hold_issue_history_empty_row');
                if (nextEmpty && holdIssueHistoryRows.length) nextEmpty.classList.add('d-none');
                updateHistoryTotalCount();
            }

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
                    what_happened: row?.what_happened ?? '',
                    issue: row?.issue ?? '',
                    issue_remark: row?.issue_remark ?? '',
                    action_1: row?.action_1 ?? '',
                    action_1_remark: row?.action_1_remark ?? '',
                    replacement_tracking: row?.replacement_tracking ?? '',
                    c_action_1: row?.c_action_1 ?? '',
                    c_action_1_remark: row?.c_action_1_remark ?? '',
                    close_note: row?.close_note ?? '',
                    department: row?.department ?? '',
                    created_by: row?.created_by ?? 'System',
                    created_at: row?.created_at_display ?? row?.created_at ?? '',
                    order_number: row?.order_number ?? '',
                    total_loss: row?.total_loss ?? null,
                };
            }

            function normalizeHistoryRecord(row) {
                return {
                    id: row?.id ?? null,
                    orders_on_hold_issue_id: row?.orders_on_hold_issue_id ?? null,
                    revision_no: row?.revision_no ?? null,
                    issue_ref: row?.issue_ref ?? null,
                    event_type: row?.event_type ?? '',
                    sku: row?.sku ?? '',
                    image_url: row?.image_url ?? null,
                    qty: row?.qty ?? 0,
                    order_qty: row?.order_qty ?? '',
                    parent: row?.parent ?? '',
                    marketplace_1: row?.marketplace_1 ?? '',
                    what_happened: row?.what_happened ?? '',
                    issue: row?.issue ?? '',
                    issue_remark: row?.issue_remark ?? '',
                    action_1: row?.action_1 ?? '',
                    action_1_remark: row?.action_1_remark ?? '',
                    replacement_tracking: row?.replacement_tracking ?? '',
                    c_action_1: row?.c_action_1 ?? '',
                    c_action_1_remark: row?.c_action_1_remark ?? '',
                    close_note: row?.close_note ?? '',
                    department: row?.department ?? '',
                    created_by: row?.created_by ?? 'System',
                    logged_at: row?.logged_at_display ?? row?.logged_at ?? '',
                    order_number: row?.order_number ?? '',
                };
            }

            async function loadHoldIssueRows() {
                try {
                    const response = await fetch(recordsListUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    });
                    const data = await response.json();
                    holdIssueRows = Array.isArray(data?.data) ? data.data.map(normalizeRecord) : [];
                    buildDeptFilters();
                    renderRows();
                } catch (error) {
                    holdIssueRows = [];
                    buildDeptFilters();
                    renderRows();
                }
            }

            async function loadHoldIssueHistoryRows() {
                try {
                    const response = await fetch(historyListUrl, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    });
                    const data = await response.json();
                    holdIssueHistoryRows = Array.isArray(data?.data) ? data.data.map(normalizeHistoryRecord) : [];
                    renderHistoryRows();
                } catch (error) {
                    holdIssueHistoryRows = [];
                    renderHistoryRows();
                }
            }

            function resetForm() {
                form.reset();
                editingIssueId = null;
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'Save';                qtyInput.value = '';
                orderQtyInput.value = '';
                parentInput.value = '';
                resetSkuImage();
                marketplace1Input.value = '';
                whatHappenedInput.value = '';
                @if(($showDispatchExtras ?? false) || ($showOrderIdField ?? false))
                if (document.getElementById('hold_issue_order_number')) document.getElementById('hold_issue_order_number').value = '';
                @endif
                @if($showDispatchExtras ?? false)
                if (document.getElementById('hold_issue_total_loss')) document.getElementById('hold_issue_total_loss').value = '';
                // Clear all extra SKU rows
                const extraContainer = document.getElementById('extra-sku-rows-container');
                if (extraContainer) extraContainer.innerHTML = '';
                @endif
                issueRemarkInput.value = '';
                toggleRootCauseRemarkField();
                action1Input.value = '';
                action1RemarkInput.value = '';
                toggleAction1RemarkField();
                replacementTrackingInput.value = '';
                cAction1Input.value = '';
                cAction1RemarkInput.value = '';
                departmentInput.value = '';
                toggleCAction1RemarkField();
                hideAlert();
            }

            function getRecordById(id) {
                const normalizedId = Number(id);
                return holdIssueRows.find(r => Number(r.id) === normalizedId) || null;
            }

            function openEditModal(record) {
                if (!record) return;
                editingIssueId = Number(record.id);

                skuInput.value = record.sku || '';
                qtyInput.value = record.qty ?? '';
                orderQtyInput.value = record.order_qty ?? '';
                parentInput.value = record.parent || '';
                marketplace1Input.value = record.marketplace_1 || '';
                whatHappenedInput.value = record.what_happened || '';
                issueInput.value = record.issue || '';
                @if(($showDispatchExtras ?? false) || ($showOrderIdField ?? false))
                if (document.getElementById('hold_issue_order_number')) document.getElementById('hold_issue_order_number').value = record.order_number || '';
                @endif
                @if($showDispatchExtras ?? false)
                if (document.getElementById('hold_issue_total_loss')) document.getElementById('hold_issue_total_loss').value = record.total_loss ?? '';
                @endif
                issueRemarkInput.value = record.issue_remark || '';
                toggleRootCauseRemarkField();
                action1Input.value = record.action_1 || '';
                action1RemarkInput.value = record.action_1_remark || '';
                toggleAction1RemarkField();
                replacementTrackingInput.value = record.replacement_tracking || '';
                cAction1Input.value = record.c_action_1 || '';
                cAction1RemarkInput.value = record.c_action_1_remark || '';
                departmentInput.value = record.department || '';
                toggleCAction1RemarkField();
                hideAlert();

                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.textContent = 'Update';

                const modalInstance = window.bootstrap?.Modal?.getOrCreateInstance(modalEl);
                if (modalInstance) modalInstance.show();
            }

            async function archiveRecord(recordId) {
                if (!confirm('Archive this record?')) return;
                try {
                    const response = await fetch(recordsUpdateBaseUrl + '/' + encodeURIComponent(recordId) + '/archive', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        showAlert(data?.message || 'Unable to archive record.');
                        return;
                    }
                    holdIssueRows = holdIssueRows.filter(r => Number(r.id) !== Number(recordId));
                    buildDeptFilters();
                    renderRows();
                    loadHoldIssueHistoryRows();
                    showAlert(data?.message || 'Hold issue archived successfully.', 'success');
                } catch (error) {
                    showAlert('Unable to archive record. Please try again.');
                }
            }

            async function refreshSkuSuggestions(query) {
                const q = String(query || '').trim();
                if (q.length < 1) {
                    skuDatalist.innerHTML = '';
                    return;
                }

                try {
                    const response = await fetch(skuSearchUrl + '?q=' + encodeURIComponent(q), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await response.json();
                    const list = Array.isArray(data?.skus) ? data.skus : [];

                    skuDatalist.innerHTML = list.map(item => {
                        const sku = item?.sku ?? '';
                        const parent = item?.parent ?? '';
                        const label = parent ? (parent + ' · ' + sku) : sku;
                        return '<option value="' + escAttr(sku) + '" label="' + escAttr(label) + '"></option>';
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
                    const response = await fetch(skuDetailsUrl + '?sku=' + encodeURIComponent(sku), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await response.json();

                    if (!response.ok || !data.found) {
                        return;
                    }

                    qtyInput.value = data.qty ?? 0;
                    parentInput.value = data.parent ?? '';
                    setSkuImage(data.image_url ?? '');
                } catch (e) {
                    // Keep inputs blank on request errors.
                }
            }

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
            addRootCauseFoundOptionBtn?.addEventListener('click', function () {
                addRootCauseOption(issueInput, 'root_cause_found', 'hold_issue_root_cause_found_datalist');
            });
            deleteRootCauseFoundOptionBtn?.addEventListener('click', function () {
                deleteRootCauseOption(issueInput, 'root_cause_found', 'hold_issue_root_cause_found_datalist');
            });

            action1Input.addEventListener('input', toggleAction1RemarkField);
            action1Input.addEventListener('change', toggleAction1RemarkField);

            cAction1Input.addEventListener('input', toggleCAction1RemarkField);
            cAction1Input.addEventListener('change', toggleCAction1RemarkField);
            addRootCauseFixedOptionBtn?.addEventListener('click', function () {
                addRootCauseOption(cAction1Input, 'root_cause_fixed', 'hold_issue_root_cause_fixed_datalist');
            });
            deleteRootCauseFixedOptionBtn?.addEventListener('click', function () {
                deleteRootCauseOption(cAction1Input, 'root_cause_fixed', 'hold_issue_root_cause_fixed_datalist');
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                hideAlert();

                const sku = skuInput.value.trim();
                const issue = issueInput.value.trim();

                if (!sku) {
                    showAlert('SKU is required.');
                    skuInput.focus();
                    return;
                }
                if (!issue) {
                    showAlert('Root Cause Found is required.');
                    issueInput.focus();
                    return;
                }
                if (action1Input.value.trim() === 'Other' && action1RemarkInput.value.trim() === '') {
                    showAlert('Please enter Action remark when Action is Other.');
                    action1RemarkInput.focus();
                    return;
                }
                if (issueInput.value.trim() === 'Other' && issueRemarkInput.value.trim() === '') {
                    showAlert('Please enter Root Cause remark for Other.');
                    issueRemarkInput.focus();
                    return;
                }
                if (cAction1Input.value.trim() === 'Other' && cAction1RemarkInput.value.trim() === '') {
                    showAlert('Please enter Root Cause Fixed remark for Other.');
                    cAction1RemarkInput.focus();
                    return;
                }

                try {
                    // ── Collect extra SKU rows (dispatch issues only) ──────────────────
                    const extraSkuRows = document.querySelectorAll('#extra-sku-rows-container .extra-sku-row');
                    const isMultiSku = extraSkuRows.length > 0;

                    const sharedFields = {
                        issue: issue,
                        @if(($showDispatchExtras ?? false) || ($showOrderIdField ?? false))
                        order_number: (document.getElementById('hold_issue_order_number')?.value || '').trim(),
                        @endif
                        @if($showDispatchExtras ?? false)
                        total_loss: document.getElementById('hold_issue_total_loss')?.value || '',
                        @endif
                        marketplace_1: marketplace1Input.value.trim(),
                        what_happened: whatHappenedInput.value.trim(),
                        issue_remark: issueRemarkInput.value.trim(),
                        action_1: action1Input.value.trim(),
                        action_1_remark: action1RemarkInput.value.trim(),
                        replacement_tracking: replacementTrackingInput.value.trim(),
                        c_action_1: cAction1Input.value.trim(),
                        c_action_1_remark: cAction1RemarkInput.value.trim(),
                        department: departmentInput.value.trim(),
                    };

                    let payload;
                    if (isMultiSku) {
                        // Collect all SKUs into the skus[] array
                        const skus = [{
                            sku: sku,
                            qty: qtyInput.value === '' ? 0 : Number(qtyInput.value),
                            order_qty: orderQtyInput.value === '' ? null : Number(orderQtyInput.value),
                            parent: parentInput.value.trim(),
                        }];
                        extraSkuRows.forEach(rowEl => {
                            const skuVal = rowEl.querySelector('.extra-sku-input')?.value?.trim() || '';
                            if (skuVal) {
                                skus.push({
                                    sku: skuVal,
                                    qty: Number(rowEl.querySelector('.extra-sku-qty')?.value || 0),
                                    order_qty: rowEl.querySelector('.extra-sku-order-qty')?.value !== ''
                                        ? Number(rowEl.querySelector('.extra-sku-order-qty')?.value)
                                        : null,
                                    parent: rowEl.querySelector('.extra-sku-parent')?.value?.trim() || '',
                                });
                            }
                        });
                        payload = { ...sharedFields, skus };
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
                    const targetUrl = isEdit
                        ? recordsUpdateBaseUrl + '/' + encodeURIComponent(editingIssueId)
                        : recordsStoreUrl;

                    const response = await fetch(targetUrl, {
                        method: isEdit ? 'PUT' : 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        if (data?.errors && typeof data.errors === 'object') {
                            const firstKey = Object.keys(data.errors)[0];
                            const firstError = firstKey ? data.errors[firstKey]?.[0] : null;
                            showAlert(firstError || data?.message || 'Unable to save hold issue.');
                            return;
                        }
                        showAlert(data?.message || 'Unable to save hold issue.');
                        return;
                    }

                    if (isEdit) {
                        await loadHoldIssueRows();
                    } else if (Array.isArray(data?.rows)) {
                        data.rows.reverse().forEach(rowData => {
                            holdIssueRows.unshift(normalizeRecord(rowData));
                        });
                        buildDeptFilters();
                        renderRows();
                    } else {
                        holdIssueRows.unshift(normalizeRecord(data?.row || {}));
                        buildDeptFilters();
                        renderRows();
                    }
                    loadHoldIssueHistoryRows();
                    showAlert(data?.message || (isEdit ? 'Hold issue updated successfully.' : 'Hold issue saved successfully.'), 'success');

                    const modalInstance = window.bootstrap?.Modal?.getInstance(modalEl);
                    if (modalInstance) {
                        setTimeout(() => {
                            modalInstance.hide();
                        }, 250);
                    }
                } catch (error) {
                    showAlert('Unable to save hold issue. Please try again.');
                }
            });

            tableBody.addEventListener('click', (event) => {
                const editBtn = event.target.closest('.hold-edit-btn');
                if (editBtn) {
                    const record = getRecordById(editBtn.getAttribute('data-id'));
                    openEditModal(record);
                    return;
                }

                const archiveBtn = event.target.closest('.hold-archive-btn');
                if (archiveBtn) {
                    archiveRecord(archiveBtn.getAttribute('data-id'));
                }

                const copyBtn = event.target.closest('.copy-order-btn');
                if (copyBtn) {
                    const text = copyBtn.getAttribute('data-copy') || '';
                    navigator.clipboard.writeText(text).then(() => {
                        copyBtn.classList.add('copied');
                        copyBtn.innerHTML = '<i class="bi bi-clipboard-check"></i>';
                        setTimeout(() => {
                            copyBtn.classList.remove('copied');
                            copyBtn.innerHTML = '<i class="bi bi-clipboard"></i>';
                        }, 1500);
                    }).catch(() => {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                    });
                }

                const copyTrackBtn = event.target.closest('.copy-tracking-btn');
                if (copyTrackBtn) {
                    const text = copyTrackBtn.getAttribute('data-copy') || '';
                    navigator.clipboard.writeText(text).then(() => {
                        copyTrackBtn.classList.add('copied');
                        copyTrackBtn.innerHTML = '<i class="bi bi-clipboard-check"></i>';
                        setTimeout(() => {
                            copyTrackBtn.classList.remove('copied');
                            copyTrackBtn.innerHTML = '<i class="bi bi-clipboard"></i>';
                        }, 1500);
                    }).catch(() => {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                    });
                }
            });

            btnShowHistory.addEventListener('click', () => {
                historyCard.classList.remove('d-none');
                loadHoldIssueHistoryRows();
                historyCard.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });

            document.getElementById('btnExportCsv').addEventListener('click', () => {
                function csvEscape(val) {
                    const str = String(val ?? '').replace(/"/g, '""');
                    return /[",\n\r]/.test(str) ? `"${str}"` : str;
                }

                function buildCsv(headers, rows) {
                    const lines = [headers.map(csvEscape).join(',')];
                    rows.forEach(r => lines.push(r.map(csvEscape).join(',')));
                    return lines.join('\r\n');
                }

                function downloadCsv(content, filename) {
                    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }

                const exportIncludeOrderId = @json((bool) ($showOrderIdField ?? false));
                const exportOrderIdLabel = @json($orderIdFieldLabel ?? 'Order ID');
                const activeHeaders = ['#', 'SKU'];
                if (exportIncludeOrderId) {
                    activeHeaders.push(exportOrderIdLabel);
                }
                activeHeaders.push(
                    'Order QTY', 'MKT',
                    'What?', 'Action', 'Action Remark', 'Track',
                    'Root Cause Found', 'Root Cause Remark', 'Root Cause Fixed',
                    'Root Cause Fixed Remark', 'Dept', 'Created By', 'Created At'
                );
                const activeData = holdIssueRows.map(r => {
                    const row = [r.id, r.sku];
                    if (exportIncludeOrderId) {
                        row.push(r.order_number || '');
                    }
                    row.push(
                        r.order_qty,
                        r.marketplace_1, r.what_happened,
                        r.action_1, r.action_1_remark, r.replacement_tracking,
                        r.issue, r.issue_remark, r.c_action_1, r.c_action_1_remark,
                        r.department || '',
                        r.created_by, r.created_at
                    );
                    return row;
                });

                const pageTitle = document.querySelector('h4.page-title, h1.page-title, .page-title h4, .page-title h1')?.textContent?.trim()
                    || document.title || 'export';
                const safeTitle = pageTitle.replace(/[^a-z0-9_\-]/gi, '_').toLowerCase();
                const dateStr = new Date().toISOString().slice(0, 10);

                downloadCsv(buildCsv(activeHeaders, activeData), `${safeTitle}_active_${dateStr}.csv`);
            });

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
                const headers = {!! json_encode(($showOrderIdField ?? false)
                    ? ['sku', 'order_number', 'qty', 'order_qty', 'parent', 'marketplace_1', 'what_happened', 'action_1', 'action_1_remark', 'replacement_tracking', 'issue', 'issue_remark', 'c_action_1', 'c_action_1_remark', 'department']
                    : ['sku', 'qty', 'order_qty', 'parent', 'marketplace_1', 'what_happened', 'action_1', 'action_1_remark', 'replacement_tracking', 'issue', 'issue_remark', 'c_action_1', 'c_action_1_remark', 'department']) !!};
                const sample  = {!! json_encode(($showOrderIdField ?? false)
                    ? ['SAMPLE-SKU-001', '112-1234567-8901234', '5', '2', 'PARENT-001', 'Amazon', 'Damaged', 'Cancelled', '', 'TRK123', 'Quality Issue', '', 'Fixed', '', 'Dispatch']
                    : ['SAMPLE-SKU-001', '5', '2', 'PARENT-001', 'Amazon', 'Damaged', 'Cancelled', '', 'TRK123', 'Quality Issue', '', 'Fixed', '', 'Dispatch']) !!};
                const csv = [headers.join(','), sample.join(',')].join('\r\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a'); a.href = url;
                a.download = 'import_sample.csv';
                document.body.appendChild(a); a.click();
                document.body.removeChild(a); URL.revokeObjectURL(url);
            });

            document.getElementById('importCsvSubmitBtn').addEventListener('click', async () => {
                const fileInput = document.getElementById('importCsvFile');
                const alertEl  = document.getElementById('importCsvAlert');
                const progressEl = document.getElementById('importCsvProgress');
                const errorsEl = document.getElementById('importCsvErrors');
                const errList  = document.getElementById('importCsvErrorList');

                if (!fileInput.files.length) {
                    alertEl.className = 'alert alert-warning mb-3';
                    alertEl.textContent = 'Please select a CSV file.';
                    return;
                }

                const importUrl = '{{ $importUrl ?? "" }}';
                if (!importUrl) {
                    alertEl.className = 'alert alert-danger mb-3';
                    alertEl.textContent = 'Import URL is not configured for this page.';
                    return;
                }

                const formData = new FormData();
                formData.append('file', fileInput.files[0]);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                alertEl.className = 'd-none mb-3';
                progressEl.classList.remove('d-none');
                errorsEl.classList.add('d-none');
                document.getElementById('importCsvSubmitBtn').disabled = true;

                try {
                    const res = await fetch(importUrl, { method: 'POST', body: formData });
                    const data = await res.json();
                    progressEl.classList.add('d-none');

                    if (res.ok) {
                        alertEl.className = 'alert alert-success mb-3';
                        alertEl.textContent = data.message || 'Import complete.';
                        if (data.errors && data.errors.length) {
                            errList.innerHTML = data.errors.map(e => `<li>${e}</li>`).join('');
                            errorsEl.classList.remove('d-none');
                        }
                        await loadHoldIssueRows();
                    } else {
                        alertEl.className = 'alert alert-danger mb-3';
                        alertEl.textContent = data.message || 'Import failed.';
                    }
                } catch (err) {
                    progressEl.classList.add('d-none');
                    alertEl.className = 'alert alert-danger mb-3';
                    alertEl.textContent = 'Network error. Please try again.';
                } finally {
                    document.getElementById('importCsvSubmitBtn').disabled = false;
                }
            });

            // ── Multi-SKU: Add Another SKU row (dispatch issues only) ────────────
            const btnAddSkuRow = document.getElementById('btn-add-sku-row');
            if (btnAddSkuRow) {
                btnAddSkuRow.addEventListener('click', function () {
                    const container = document.getElementById('extra-sku-rows-container');
                    if (!container) return;

                    const rowEl = document.createElement('div');
                    rowEl.className = 'extra-sku-row border rounded p-2 mb-2 position-relative';
                    rowEl.innerHTML = `
                        <button type="button" class="btn-close position-absolute top-0 end-0 m-1 remove-extra-sku-row"
                            style="font-size:0.65rem;" title="Remove this SKU"></button>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label small mb-1">SKU <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm extra-sku-input"
                                    list="hold_issue_sku_datalist" placeholder="Search SKU" autocomplete="off">
                                <div class="mt-1 d-none extra-sku-image-wrap">
                                    <img src="" class="sku-image-preview" style="width:52px;height:52px;">
                                </div>
                            </div>
                            <div style="display:none;">
                                <input type="number" class="form-control form-control-sm extra-sku-qty" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">Order Qty</label>
                                <input type="number" class="form-control form-control-sm extra-sku-order-qty" min="0" step="1" placeholder="Qty">
                            </div>
                            <div style="display:none;">
                                <input type="text" class="form-control form-control-sm extra-sku-parent" readonly>
                            </div>
                        </div>`;
                    container.appendChild(rowEl);

                    // Wire up SKU lookup for this new row
                    const skuInput = rowEl.querySelector('.extra-sku-input');
                    const qtyInp   = rowEl.querySelector('.extra-sku-qty');
                    const parentInp = rowEl.querySelector('.extra-sku-parent');
                    const imgWrap  = rowEl.querySelector('.extra-sku-image-wrap');
                    const imgEl    = imgWrap?.querySelector('img');

                    let timer = null;
                    async function fetchAndFill(skuVal) {
                        const s = String(skuVal || '').trim();
                        qtyInp.value = '';
                        parentInp.value = '';
                        if (imgWrap) imgWrap.classList.add('d-none');
                        if (!s) return;
                        try {
                            const res = await fetch(skuDetailsUrl + '?sku=' + encodeURIComponent(s), {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            const d = await res.json();
                            if (d.found) {
                                qtyInp.value   = d.qty ?? 0;
                                parentInp.value = d.parent ?? '';
                                if (d.image_url && imgEl && imgWrap) {
                                    imgEl.src = d.image_url;
                                    imgWrap.classList.remove('d-none');
                                }
                            }
                        } catch (e) { /* ignore */ }
                    }
                    skuInput.addEventListener('input', () => {
                        clearTimeout(timer);
                        timer = setTimeout(() => refreshSkuSuggestions(skuInput.value), 220);
                    });
                    skuInput.addEventListener('change', () => fetchAndFill(skuInput.value));
                    skuInput.addEventListener('blur',   () => fetchAndFill(skuInput.value));
                    skuInput.focus();
                });

                // Remove a row when × is clicked
                document.getElementById('extra-sku-rows-container')?.addEventListener('click', function (e) {
                    const removeBtn = e.target.closest('.remove-extra-sku-row');
                    if (removeBtn) {
                        removeBtn.closest('.extra-sku-row')?.remove();
                    }
                });
            }

            modalEl.addEventListener('hidden.bs.modal', resetForm);
            initializeDynamicRootCauseOptions();
            toggleRootCauseRemarkField();
            toggleAction1RemarkField();
            toggleCAction1RemarkField();
            renderRows();
            renderHistoryRows();
            loadHoldIssueRows();

            @if($showDispatchExtras ?? false)
            // ── L30 Loss Badge ────────────────────────────────────────────────────
            const l30LossUrl = @json(route('customer.care.dispatch.issues.l30.loss'));
            let l30Data = null;
            let l30SparkChart = null;
            let l30FullChart  = null;

            async function loadL30Loss() {
                try {
                    let url = l30LossUrl;
                    if (activeDeptFilter) url += '?department=' + encodeURIComponent(activeDeptFilter);
                    const res = await fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) return;
                    const json = await res.json();
                    l30Data = json;

                    const totalEl = document.getElementById('l30-badge-total');
                    if (totalEl) {
                        totalEl.textContent = '$' + Math.round(json.total || 0);
                    }
                    renderL30Sparkline(json.daily || []);
                } catch (e) { /* silent */ }
            }

            function renderL30Sparkline(daily) { /* sparklines hidden */ }

            function _l30CalcStats(vals) {
                const sorted = [...vals].sort((a, b) => a - b);
                const mid = Math.floor(sorted.length / 2);
                const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
                return { min: sorted[0] ?? 0, max: sorted[sorted.length - 1] ?? 0, median };
            }

            const _medianLinePlugin = {
                id: 'l30MedianLine',
                afterDraw(chart) {
                    const median = chart._l30Median;
                    if (median == null) return;
                    const yScale = chart.scales.y, xScale = chart.scales.x, ctx = chart.ctx;
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
                        const meta = chart.getDatasetMeta(0);
                        const ctx = chart.ctx;
                        ctx.save();
                        ctx.font = 'bold 10px Inter,system-ui,sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        meta.data.forEach((pt, i) => {
                            const v = vals[i];
                            const color = i === 0 ? '#6c757d' : v > vals[i-1] ? '#28a745' : v < vals[i-1] ? '#dc3545' : '#6c757d';
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
                if (lineChart) { lineChart.destroy(); lineChart = null; }
                const { min, max, median } = _l30CalcStats(vals);
                const range = max - min || 1;
                const dotColors = vals.map((v, i) => i === 0 ? '#6c757d' : v > vals[i-1] ? '#28a745' : v < vals[i-1] ? '#dc3545' : '#6c757d');
                const chart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{ data: vals, backgroundColor: color + '18', borderColor: '#adb5bd',
                            borderWidth: 1.5, fill: true, tension: 0.3, pointRadius: 3, pointHoverRadius: 5,
                            pointBackgroundColor: dotColors, pointBorderColor: dotColors, pointBorderWidth: 1.5 }]
                    },
                    plugins: [_medianLinePlugin, _makeValueLabelsPlugin(vals, fmt)],
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 28, left: 2, right: 2, bottom: 2 } },
                        plugins: {
                            legend: { display: false },
                            tooltip: { titleFont: { size: 10 }, bodyFont: { size: 10 }, padding: 6,
                                callbacks: { label: ctx => {
                                    const parts = ['Value: ' + fmt(ctx.raw)];
                                    if (ctx.dataIndex > 0) {
                                        const diff = ctx.raw - vals[ctx.dataIndex - 1];
                                        parts.push('vs Yesterday: ' + (diff > 0 ? '▲' : diff < 0 ? '▼' : '▬') + ' ' + fmt(Math.abs(diff)));
                                    }
                                    return parts;
                                }
                            }}
                        },
                        scales: {
                            y: { min: Math.max(0, min - range * 0.1), max: max + range * 0.1,
                                ticks: { font: { size: 9 }, callback: v => fmt(v) } },
                            x: { ticks: { maxRotation: 45, minRotation: 45, font: { size: 8 }, autoSkip: labels.length > 20, maxTicksLimit: 31 } }
                        }
                    }
                });
                chart._l30Median = median;
                return chart;
            }

            function _buildBarChart(canvasId, labels, vals, fmt, color, barChart) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return null;
                if (barChart) { barChart.destroy(); barChart = null; }
                return new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: { labels, datasets: [{ data: vals, backgroundColor: color + 'cc', borderColor: color, borderWidth: 1 }] },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        layout: { padding: { top: 4, left: 2, right: 2, bottom: 16 } },
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => fmt(ctx.raw) } } },
                        scales: {
                            x: { ticks: { maxRotation: 45, minRotation: 45, font: { size: 7 }, autoSkip: false, maxTicksLimit: 31 } },
                            y: { ticks: { font: { size: 9 }, callback: v => fmt(v) } }
                        }
                    }
                });
            }

            function renderL30FullChart(daily) {
                const labels = daily.map(d => d.date);
                const vals   = daily.map(d => parseFloat(d.loss) || 0);
                const fmt    = v => '$' + Math.round(v).toLocaleString('en-US');
                l30FullChart = _buildLineChart('l30LossLineChart', labels, vals, fmt, '#dc3545', l30FullChart);
                if (l30FullChart) {
                    const { min, max, median } = _l30CalcStats(vals);
                    document.getElementById('l30-loss-highest').textContent = fmt(max);
                    document.getElementById('l30-loss-median').textContent  = fmt(median);
                    document.getElementById('l30-loss-lowest').textContent  = fmt(min);
                }
                _buildBarChart('l30LossBarChart', labels, vals, fmt, '#dc3545', null);
            }

            function renderL30Table(daily) { /* removed */ }

            loadL30Loss();

            document.getElementById('l30LossModal')?.addEventListener('show.bs.modal', () => {
                const daily = l30Data?.daily || [];
                const rangeEl = document.getElementById('l30-modal-range');
                if (rangeEl && l30Data) rangeEl.textContent = ' (' + l30Data.from + ' → ' + l30Data.to + ')';
                renderL30FullChart(daily);
            });

            // ── L30 Issues Badge ──────────────────────────────────────────────────
            const l30IssuesUrl = @json(route('customer.care.dispatch.issues.l30.issues'));
            let l30IssuesData       = null;
            let l30IssuesSparkChart = null;
            let l30IssuesFullChart  = null;
            let l30IssuesDays       = 30;

            async function loadL30Issues() {
                try {
                    let url = l30IssuesUrl + '?days=' + l30IssuesDays;
                    if (activeDeptFilter) url += '&department=' + encodeURIComponent(activeDeptFilter);
                    const res = await fetch(url, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) return;
                    const json = await res.json();
                    l30IssuesData = json;

                    const totalEl = document.getElementById('l30-issues-badge-total');
                    if (totalEl) totalEl.textContent = json.total || 0;
                    const labelEl = document.getElementById('l30-issues-badge-label');
                    if (labelEl) labelEl.textContent = 'L' + l30IssuesDays;
                    renderL30IssuesSparkline(json.daily || []);
                } catch (e) { /* silent */ }
            }

            function renderL30IssuesSparkline(daily) { /* sparklines hidden */ }

            function renderL30IssuesFullChart(daily) {
                const labels = daily.map(d => d.date);
                const vals   = daily.map(d => d.count);
                const fmt    = v => Math.round(v).toLocaleString('en-US');
                l30IssuesFullChart = _buildLineChart('l30IssuesLineChart', labels, vals, fmt, '#0d6efd', l30IssuesFullChart);
                if (l30IssuesFullChart) {
                    const { min, max, median } = _l30CalcStats(vals);
                    document.getElementById('l30-issues-highest').textContent = fmt(max);
                    document.getElementById('l30-issues-median').textContent  = fmt(median);
                    document.getElementById('l30-issues-lowest').textContent  = fmt(min);
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
                tbody.innerHTML = daily.slice().reverse().map(d =>
                    '<tr><td>' + escapeHtml(d.date) + '</td><td class="text-end fw-semibold">' + d.count + '</td></tr>'
                ).join('');
                const total = daily.reduce((s, d) => s + (parseInt(d.count) || 0), 0);
                if (tfoot) tfoot.innerHTML = '<tr class="table-primary fw-bold"><td>Total (L' + l30IssuesDays + ')</td><td class="text-end">' + total + '</td></tr>';
            }

            loadL30Issues();

            // Period pill clicks
            document.getElementById('l30-issues-period-pills')?.addEventListener('click', (e) => {
                const pill = e.target.closest('.l30-period-pill');
                if (!pill) return;
                e.stopPropagation();
                l30IssuesDays = parseInt(pill.getAttribute('data-days')) || 30;
                document.querySelectorAll('#l30-issues-period-pills .l30-period-pill').forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                loadL30Issues();
            });

            document.getElementById('l30IssuesModal')?.addEventListener('show.bs.modal', () => {
                const daily = l30IssuesData?.daily || [];
                const rangeEl = document.getElementById('l30-issues-modal-range');
                if (rangeEl && l30IssuesData) rangeEl.textContent = ' (' + l30IssuesData.from + ' → ' + l30IssuesData.to + ')';
                renderL30IssuesFullChart(daily);
                renderL30IssuesTable(daily);
            });
            @endif
        })();
    </script>
@endsection
