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
            width: 22%;
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

        .orders-hold-col-mp {
            width: 8%;
        }

        .orders-hold-col-issue {
            width: 10%;
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
        .l30-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 5px 12px 5px 10px;
            background: linear-gradient(135deg, #fff5f5 0%, #fff 100%);
            border: 1.5px solid #f5c2c7;
            border-radius: 0.5rem;
            cursor: pointer;
            user-select: none;
            transition: box-shadow 0.15s, border-color 0.15s;
            text-decoration: none;
        }

        .l30-badge:hover {
            box-shadow: 0 2px 10px rgba(220,53,69,.18);
            border-color: #dc3545;
        }

        .l30-badge-info {
            display: flex;
            flex-direction: column;
            line-height: 1.25;
        }

        .l30-badge-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #dc3545;
        }

        .l30-badge-value {
            font-size: 17px;
            font-weight: 700;
            color: #212529;
            min-width: 60px;
        }

        #l30-sparkline-container {
            width: 80px;
            height: 34px;
            flex-shrink: 0;
        }

        /* ── L30 Issues Badge ─────────────────────────────────────── */
        .l30-issues-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 5px 12px 5px 10px;
            background: linear-gradient(135deg, #f0f5ff 0%, #fff 100%);
            border: 1.5px solid #b6d0fe;
            border-radius: 0.5rem;
            cursor: pointer;
            user-select: none;
            transition: box-shadow 0.15s, border-color 0.15s;
            text-decoration: none;
        }

        .l30-issues-badge:hover {
            box-shadow: 0 2px 10px rgba(13,110,253,.18);
            border-color: #0d6efd;
        }

        .l30-issues-badge .l30-badge-label {
            color: #0d6efd;
        }

        #l30-issues-sparkline-container {
            width: 80px;
            height: 34px;
            flex-shrink: 0;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $pageTitle ?? 'QC And Packing',
        'sub_title' => 'Customer Care',
    ])

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-start align-items-center gap-2 mb-3">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ordersOnHoldIssueModal">
                    <i class="bi bi-plus-lg me-1"></i> {{ $addIssueButtonText ?? 'Add QC & Packing Issue' }}
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnShowHistory">
                    <i class="bi bi-clock-history me-1"></i> History
                </button>
                <button type="button" class="btn btn-success" id="btnExportCsv">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
                </button>
                <button type="button" class="btn btn-outline-info" id="btnImportCsv">
                    <i class="bi bi-upload me-1"></i> Import CSV
                </button>
                @if($showDispatchExtras ?? false)
                <div id="l30-loss-badge" class="l30-badge" role="button"
                     data-bs-toggle="modal" data-bs-target="#l30LossModal"
                     title="Last 30 Days Loss — click for detail">
                    <div class="l30-badge-info">
                        <span class="l30-badge-label"><i class="bi bi-graph-down-arrow me-1"></i>L30 Loss</span>
                        <span class="l30-badge-value" id="l30-badge-total">…</span>
                    </div>
                    <div id="l30-sparkline-container"></div>
                </div>
                <div id="l30-issues-badge" class="l30-issues-badge" role="button"
                     data-bs-toggle="modal" data-bs-target="#l30IssuesModal"
                     title="Last 30 Days Issues — click for detail">
                    <div class="l30-badge-info">
                        <span class="l30-badge-label"><i class="bi bi-exclamation-circle me-1"></i>L30 Issues</span>
                        <span class="l30-badge-value" id="l30-issues-badge-total">…</span>
                    </div>
                    <div id="l30-issues-sparkline-container"></div>
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
                    <span class="badge bg-light text-dark" id="hold_issue_total_count">0</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm mb-0 orders-hold-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="orders-hold-col-idx">#</th>
                                    <th class="orders-hold-col-sku">SKU</th>
                                    <th class="orders-hold-col-date">Issue Date</th>
                                    @if($showDispatchExtras ?? false)
                                    <th class="orders-hold-col-action">Order #</th>
                                    <th class="orders-hold-col-action">Refund ($)</th>
                                    <th class="orders-hold-col-action">Total Loss ($)</th>
                                    @endif
                                    <th class="orders-hold-col-qty">QTY</th>
                                    <th class="orders-hold-col-qty">Order Qty</th>
                                    <th class="orders-hold-col-parent">Parent</th>
                                    <th class="orders-hold-col-mp">MKT1</th>
                                    <th class="orders-hold-col-what">What?</th>
                                    <th class="orders-hold-col-action">Action</th>
                                    <th class="orders-hold-col-action">Replacement Tracking</th>
                                    <th class="orders-hold-col-issue">Root Cause<br>Found</th>
                                    <th class="orders-hold-col-action">Root Cause Fixed</th>
                                    <th class="orders-hold-col-close">Close</th>
                                    <th class="orders-hold-col-created-by">Created By</th>
                                    <th class="orders-hold-col-created-at">Created At</th>
                                </tr>
                            </thead>
                            <tbody id="hold_issue_table_body">
                                <tr id="hold_issue_empty_row">
                                    <td colspan="15" class="text-center text-muted py-4">No records found.</td>
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
                                    <th class="orders-hold-col-sku">SKU</th>
                                    <th class="orders-hold-col-qty">QTY</th>
                                    <th class="orders-hold-col-qty">Order Qty</th>
                                    <th class="orders-hold-col-parent">Parent</th>
                                    <th class="orders-hold-col-mp">MKT1</th>
                                    <th class="orders-hold-col-what">What?</th>
                                    <th class="orders-hold-col-action">Action</th>
                                    <th class="orders-hold-col-action">Replacement Tracking</th>
                                    <th class="orders-hold-col-issue">Root Cause<br>Found</th>
                                    <th class="orders-hold-col-action">Root Cause Fixed</th>
                                    <th class="orders-hold-col-action">Close</th>
                                    <th class="orders-hold-col-action">Event</th>
                                    <th class="orders-hold-col-created-by">Created By</th>
                                    <th class="orders-hold-col-created-at">Logged At</th>
                                </tr>
                            </thead>
                            <tbody id="hold_issue_history_table_body">
                                <tr id="hold_issue_history_empty_row">
                                    <td colspan="16" class="text-center text-muted py-4">No history found.</td>
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
                        <code>sku, qty, order_qty, parent, marketplace_1, what_happened, action_1, action_1_remark, replacement_tracking, issue, issue_remark, c_action_1, c_action_1_remark</code>
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
                                        <div class="col-md-5">
                                            <label class="form-label">SKU <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control sku-entry-input" id="hold_issue_sku" name="sku"
                                                list="hold_issue_sku_datalist" placeholder="Search SKU" required autocomplete="off">
                                            <datalist id="hold_issue_sku_datalist"></datalist>
                                            <div class="mt-1 d-none" id="hold_issue_sku_image_wrap">
                                                <img src="" alt="SKU Image" id="hold_issue_sku_image" class="sku-image-preview">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Qty in Stock</label>
                                            <input type="number" class="form-control sku-entry-qty" id="hold_issue_qty" name="qty" readonly>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Order Qty</label>
                                            <input type="number" class="form-control sku-entry-order-qty" id="hold_issue_order_qty" name="order_qty"
                                                min="0" step="1" placeholder="Qty">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Parent</label>
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

                            <div class="col-md-3">
                                <label for="hold_issue_date" class="form-label">Issue Date</label>
                                <input type="text" class="form-control" id="hold_issue_date" name="issue_date"
                                    placeholder="e.g. 01-Apr-2026 or any format">
                            </div>

                            @if($showDispatchExtras ?? false)
                            <div class="col-md-4">
                                <label for="hold_issue_order_number" class="form-label">Order Number</label>
                                <input type="text" class="form-control" id="hold_issue_order_number" name="order_number"
                                    placeholder="Enter order number">
                            </div>
                            <div class="col-md-4">
                                <label for="hold_issue_refund_amount" class="form-label">Refund Amount ($)</label>
                                <input type="number" class="form-control" id="hold_issue_refund_amount" name="refund_amount"
                                    min="0" step="0.01" placeholder="0.00">
                            </div>
                            <div class="col-md-4">
                                <label for="hold_issue_total_loss" class="form-label">Total Loss ($)</label>
                                <input type="number" class="form-control" id="hold_issue_total_loss" name="total_loss"
                                    step="0.01" placeholder="0.00">
                            </div>
                            @endif

                            <div class="col-md-6">
                                <label for="hold_issue_marketplace_1" class="form-label">MKT1</label>
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

                            <div class="col-md-8 d-none" id="action1RemarkWrap">
                                <label for="hold_issue_action_1_remark" class="form-label">Action Remark</label>
                                @if($showDispatchExtras ?? false)
                                <textarea class="form-control" id="hold_issue_action_1_remark" name="action_1_remark"
                                    rows="3" placeholder="Write action remark..."></textarea>
                                @else
                                <input type="text" class="form-control" id="hold_issue_action_1_remark" name="action_1_remark"
                                    placeholder="Write remark for Other">
                                @endif
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_replacement_tracking" class="form-label">Replacement Tracking Number</label>
                                <input type="text" class="form-control" id="hold_issue_replacement_tracking"
                                    name="replacement_tracking" maxlength="50" placeholder="Optional tracking number">
                            </div>

                            <div class="col-12">
                                <label for="hold_issue_text" class="form-label">Root Cause Found <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="hold_issue_text" name="issue"
                                    list="hold_issue_root_cause_found_datalist"
                                    placeholder="Type or select root cause..." required autocomplete="off">
                                <datalist id="hold_issue_root_cause_found_datalist"></datalist>
                            </div>

                            <div class="col-12 d-none" id="rootCauseRemarkWrap">
                                <label for="hold_issue_remark" class="form-label">Root Cause Remark</label>
                                <input type="text" class="form-control" id="hold_issue_remark" name="issue_remark"
                                    placeholder="Write remark for Other">
                            </div>

                            <div class="col-md-4">
                                <label for="hold_issue_c_action_1" class="form-label">Root Cause Fixed</label>
                                <input type="text" class="form-control" id="hold_issue_c_action_1" name="c_action_1"
                                    list="hold_issue_root_cause_fixed_datalist"
                                    placeholder="Type or select fix..." autocomplete="off">
                                <datalist id="hold_issue_root_cause_fixed_datalist"></datalist>
                            </div>

                            <div class="col-md-8 d-none" id="cAction1RemarkWrap">
                                <label for="hold_issue_c_action_1_remark" class="form-label">Root Cause Fixed Remark</label>
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
    <div class="modal fade" id="l30LossModal" tabindex="-1" aria-labelledby="l30LossModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="l30LossModalLabel">
                        <i class="bi bi-graph-down-arrow me-2 text-danger"></i>
                        Last 30 Days Loss
                        <small class="text-muted fw-normal ms-2" id="l30-modal-range" style="font-size:12px;"></small>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="l30-chart-full" style="height:320px;"></div>
                    <hr class="my-3">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th class="text-end">Total Loss ($)</th>
                                    <th class="text-end">Issues</th>
                                </tr>
                            </thead>
                            <tbody id="l30-table-body">
                                <tr><td colspan="3" class="text-center text-muted py-3">Loading…</td></tr>
                            </tbody>
                            <tfoot id="l30-table-foot"></tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── L30 Issues Modal ─────────────────────────────────────────────── --}}
    <div class="modal fade" id="l30IssuesModal" tabindex="-1" aria-labelledby="l30IssuesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="l30IssuesModalLabel">
                        <i class="bi bi-exclamation-circle me-2 text-primary"></i>
                        Last 30 Days Issues
                        <small class="text-muted fw-normal ms-2" id="l30-issues-modal-range" style="font-size:12px;"></small>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="l30-issues-chart-full" style="height:320px;"></div>
                    <hr class="my-3">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover mb-0">
                            <thead class="table-light">
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
    <script>
        (function() {
            const skuSearchUrl = @json(route('customer.care.followups.skus'));
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

            function whatHappenedDotHtml(value) {
                const text = String(value || '').trim();
                if (text.toLowerCase() === '0 stock') {
                    return '<span class="what-happened-dot" title="0 Stock"></span>';
                }
                if (text.toLowerCase() === 'damaged') {
                    return '<span class="what-happened-dot what-happened-dot-damaged" title="Damaged"></span>';
                }
                return '—';
            }

            function action1DisplayHtml(value, remark) {
                const action = String(value || '').trim();
                const rmk = String(remark || '').trim();
                if (!action) return '—';
                if (action === 'Other' && rmk) {
                    return escapeHtml(action + ': ' + rmk);
                }
                return escapeHtml(action);
            }

            function rootCauseDisplayHtml(value, remark) {
                const root = String(value || '').trim();
                const rmk = String(remark || '').trim();
                if (!root) return '—';
                if (root === 'Other' && rmk) {
                    return escapeHtml(root + ': ' + rmk);
                }
                return escapeHtml(root);
            }

            function rootCauseFixedDisplayHtml(value, remark) {
                const fixed = String(value || '').trim();
                const rmk = String(remark || '').trim();
                if (!fixed) return '—';
                if (fixed === 'Other' && rmk) {
                    return escapeHtml(fixed + ': ' + rmk);
                }
                return escapeHtml(fixed);
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
                if (action1RemarkWrap) {
                    action1RemarkWrap.classList.toggle('d-none', !isOther);
                }
                if (action1RemarkInput) {
                    action1RemarkInput.required = isOther;
                    if (!isOther) action1RemarkInput.value = '';
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
                // Count distinct errors: each unique group_id = 1 error; rows without group_id = 1 each
                const seenGroups = new Set();
                let errorCount = 0;
                holdIssueRows.forEach(r => {
                    if (r.group_id) {
                        if (!seenGroups.has(r.group_id)) {
                            seenGroups.add(r.group_id);
                            errorCount++;
                        }
                    } else {
                        errorCount++;
                    }
                });
                totalCountEl.textContent = String(errorCount);
            }

            function renderRows() {
                if (!tableBody) return;

                if (!holdIssueRows.length) {
                    if (emptyRow) emptyRow.classList.remove('d-none');
                    updateTotalCount();
                    return;
                }

                if (emptyRow) emptyRow.classList.add('d-none');

                const dataRowsHtml = holdIssueRows.map((row, index) => {
                    const buttonsHtml =
                        '<div class="hold-close-actions">' +
                        '<button type="button" class="btn btn-sm hold-action-btn hold-edit-btn" data-id="' + row.id +
                        '" title="Edit"><i class="bi bi-pencil-fill"></i></button>' +
                        '<button type="button" class="btn btn-sm hold-action-btn hold-archive-btn" data-id="' + row.id +
                        '" title="Archive"><i class="bi bi-archive-fill"></i></button>' +
                        '</div>';
                    // Group badge: show small colored pill for multi-SKU groups
                    const groupBadge = row.group_id
                        ? '<span class="badge bg-warning text-dark ms-1" style="font-size:0.7rem;" title="Grouped entry (1 error)">G</span>'
                        : '';
                    return '<tr>' +
                        '<td>' + escapeHtml(row.id) + '</td>' +
                        '<td>' + escapeHtml(row.sku) + groupBadge + '</td>' +
                        '<td>' + escapeHtml(row.issue_date || '—') + '</td>' +
                        @if($showDispatchExtras ?? false)
                        '<td class="order-num-cell">' + (row.order_number ? '<button class="copy-order-btn" data-copy="' + escAttr(row.order_number) + '" title="' + escAttr(row.order_number) + '"><i class="bi bi-clipboard"></i></button><span class="order-num-short">' + escapeHtml(row.order_number) + '</span>' : '—') + '</td>' +
                        '<td>' + (row.refund_amount != null ? '$' + parseFloat(row.refund_amount).toFixed(2) : '—') + '</td>' +
                        '<td>' + (row.total_loss != null ? '$' + parseFloat(row.total_loss).toFixed(2) : '—') + '</td>' +
                        @endif
                        '<td>' + escapeHtml(row.qty) + '</td>' +
                        '<td>' + escapeHtml(row.order_qty) + '</td>' +
                        '<td>' + escapeHtml(row.parent) + '</td>' +
                        '<td>' + escapeHtml(row.marketplace_1) + '</td>' +
                        '<td>' + whatHappenedDotHtml(row.what_happened) + '</td>' +
                        '<td>' + action1DisplayHtml(row.action_1, row.action_1_remark) + '</td>' +
                        '<td>' + escapeHtml(row.replacement_tracking || '—') + '</td>' +
                        '<td>' + rootCauseDisplayHtml(row.issue, row.issue_remark) + '</td>' +
                        '<td>' + rootCauseFixedDisplayHtml(row.c_action_1, row.c_action_1_remark) + '</td>' +
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
                        '<td>' + escapeHtml(row.sku) + '</td>' +
                        '<td>' + escapeHtml(row.qty) + '</td>' +
                        '<td>' + escapeHtml(row.order_qty) + '</td>' +
                        '<td>' + escapeHtml(row.parent) + '</td>' +
                        '<td>' + escapeHtml(row.marketplace_1) + '</td>' +
                        '<td>' + whatHappenedDotHtml(row.what_happened) + '</td>' +
                        '<td>' + action1DisplayHtml(row.action_1, row.action_1_remark) + '</td>' +
                        '<td>' + escapeHtml(row.replacement_tracking || '—') + '</td>' +
                        '<td>' + rootCauseDisplayHtml(row.issue, row.issue_remark) + '</td>' +
                        '<td>' + rootCauseFixedDisplayHtml(row.c_action_1, row.c_action_1_remark) + '</td>' +
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
                    created_by: row?.created_by ?? 'System',
                    created_at: row?.created_at_display ?? row?.created_at ?? '',
                    issue_date: row?.issue_date ?? '',
                    order_number: row?.order_number ?? '',
                    refund_amount: row?.refund_amount ?? null,
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
                    created_by: row?.created_by ?? 'System',
                    logged_at: row?.logged_at_display ?? row?.logged_at ?? '',
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
                    renderRows();
                } catch (error) {
                    holdIssueRows = [];
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
                if (submitBtn) submitBtn.textContent = 'Save';
                qtyInput.value = '';
                orderQtyInput.value = '';
                parentInput.value = '';
                resetSkuImage();
                marketplace1Input.value = '';
                whatHappenedInput.value = '';
                document.getElementById('hold_issue_date').value = '';
                @if($showDispatchExtras ?? false)
                if (document.getElementById('hold_issue_order_number')) document.getElementById('hold_issue_order_number').value = '';
                if (document.getElementById('hold_issue_refund_amount')) document.getElementById('hold_issue_refund_amount').value = '';
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
                document.getElementById('hold_issue_date').value = record.issue_date || '';
                @if($showDispatchExtras ?? false)
                if (document.getElementById('hold_issue_order_number')) document.getElementById('hold_issue_order_number').value = record.order_number || '';
                if (document.getElementById('hold_issue_refund_amount')) document.getElementById('hold_issue_refund_amount').value = record.refund_amount ?? '';
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
                if (issueInput.value.trim() === 'Other' && issueRemarkInput.value.trim() === '') {
                    showAlert('Please enter Root Cause remark for Other.');
                    issueRemarkInput.focus();
                    return;
                }
                if (action1Input.value.trim() === 'Other' && action1RemarkInput.value.trim() === '') {
                    showAlert('Please enter Action remark for Other.');
                    action1RemarkInput.focus();
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
                        issue_date: document.getElementById('hold_issue_date').value.trim(),
                        @if($showDispatchExtras ?? false)
                        order_number: (document.getElementById('hold_issue_order_number')?.value || '').trim(),
                        refund_amount: document.getElementById('hold_issue_refund_amount')?.value || '',
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
                        // Multi-SKU response: add all rows
                        data.rows.reverse().forEach(rowData => {
                            holdIssueRows.unshift(normalizeRecord(rowData));
                        });
                        renderRows();
                    } else {
                        holdIssueRows.unshift(normalizeRecord(data?.row || {}));
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

                const activeHeaders = ['#', 'SKU', 'Issue Date', 'QTY', 'Order QTY', 'Parent', 'MKT1',
                    'What?', 'Action', 'Action Remark', 'Replacement Tracking',
                    'Root Cause Found', 'Root Cause Remark', 'Root Cause Fixed',
                    'Root Cause Fixed Remark', 'Created By', 'Created At'];
                const activeData = holdIssueRows.map(r => [
                    r.id, r.sku, r.issue_date || '', r.qty, r.order_qty, r.parent,
                    r.marketplace_1, r.what_happened,
                    r.action_1, r.action_1_remark, r.replacement_tracking,
                    r.issue, r.issue_remark, r.c_action_1, r.c_action_1_remark,
                    r.created_by, r.created_at
                ]);

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
                const headers = ['sku','issue_date','qty','order_qty','parent','marketplace_1','what_happened','action_1','action_1_remark','replacement_tracking','issue','issue_remark','c_action_1','c_action_1_remark'];
                const sample  = ['SAMPLE-SKU-001','5','2','PARENT-001','Amazon','Damaged','Cancelled','','TRK123','Quality Issue','','Fixed',''];
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
                            <div class="col-md-5">
                                <label class="form-label small mb-1">SKU <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm extra-sku-input"
                                    list="hold_issue_sku_datalist" placeholder="Search SKU" autocomplete="off">
                                <div class="mt-1 d-none extra-sku-image-wrap">
                                    <img src="" class="sku-image-preview" style="width:52px;height:52px;">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Qty in Stock</label>
                                <input type="number" class="form-control form-control-sm extra-sku-qty" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Order Qty</label>
                                <input type="number" class="form-control form-control-sm extra-sku-order-qty" min="0" step="1" placeholder="Qty">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Parent</label>
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
                    const res = await fetch(l30LossUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) return;
                    const json = await res.json();
                    l30Data = json;

                    const totalEl = document.getElementById('l30-badge-total');
                    if (totalEl) {
                        totalEl.textContent = '$' + (json.total || 0).toFixed(2);
                    }
                    renderL30Sparkline(json.daily || []);
                } catch (e) { /* silent */ }
            }

            function renderL30Sparkline(daily) {
                const el = document.getElementById('l30-sparkline-container');
                if (!el || typeof Highcharts === 'undefined') return;
                if (l30SparkChart) { l30SparkChart.destroy(); l30SparkChart = null; }
                l30SparkChart = Highcharts.chart(el, {
                    chart: { type: 'area', margin: [2,2,2,2], backgroundColor: 'transparent', animation: false },
                    title: { text: '' }, credits: { enabled: false },
                    xAxis: { visible: false },
                    yAxis: { visible: false, min: 0 },
                    legend: { enabled: false },
                    tooltip: {
                        headerFormat: '',
                        pointFormatter: function () { return '<b>' + this.category + '</b>: $' + this.y.toFixed(2); },
                        outside: true,
                    },
                    plotOptions: {
                        area: {
                            marker: { enabled: false, states: { hover: { enabled: true, radius: 3 } } },
                            lineWidth: 1.5,
                            color: '#dc3545',
                            fillOpacity: 0.15,
                            states: { hover: { lineWidth: 2 } },
                        },
                    },
                    series: [{
                        data: daily.length ? daily.map(d => ({ x: daily.indexOf(d), y: parseFloat(d.loss) || 0, category: d.date })) : [0],
                    }],
                });
            }

            function renderL30FullChart(daily) {
                const el = document.getElementById('l30-chart-full');
                if (!el || typeof Highcharts === 'undefined') return;
                if (l30FullChart) { l30FullChart.destroy(); l30FullChart = null; }
                const cats = daily.map(d => d.date);
                const vals = daily.map(d => parseFloat(d.loss) || 0);
                l30FullChart = Highcharts.chart(el, {
                    chart: { type: 'area', backgroundColor: '#fff', animation: false },
                    title: { text: '' },
                    credits: { enabled: false },
                    xAxis: {
                        categories: cats,
                        labels: { rotation: -45, style: { fontSize: '10px' } },
                    },
                    yAxis: { title: { text: 'Loss ($)' }, min: 0 },
                    legend: { enabled: false },
                    tooltip: {
                        headerFormat: '<b>{point.key}</b><br>',
                        pointFormat: 'Loss: <b>${point.y:.2f}</b>',
                    },
                    plotOptions: {
                        area: {
                            color: '#dc3545',
                            fillOpacity: 0.12,
                            marker: { enabled: true, radius: 4, symbol: 'circle' },
                            dataLabels: {
                                enabled: true,
                                formatter: function () { return this.y > 0 ? '$' + this.y.toFixed(0) : ''; },
                                style: { fontSize: '9px', color: '#333', textOutline: 'none' },
                            },
                        },
                    },
                    series: [{ name: 'Loss', data: vals }],
                });
            }

            function renderL30Table(daily) {
                const tbody = document.getElementById('l30-table-body');
                const tfoot = document.getElementById('l30-table-foot');
                if (!tbody) return;
                if (!daily.length) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No loss data in the last 30 days.</td></tr>';
                    if (tfoot) tfoot.innerHTML = '';
                    return;
                }
                tbody.innerHTML = daily.slice().reverse().map(d =>
                    '<tr>' +
                    '<td>' + escapeHtml(d.date) + '</td>' +
                    '<td class="text-end text-danger fw-semibold">$' + parseFloat(d.loss).toFixed(2) + '</td>' +
                    '<td class="text-end">' + d.count + '</td>' +
                    '</tr>'
                ).join('');
                const grandTotal  = daily.reduce((s, d) => s + (parseFloat(d.loss) || 0), 0);
                const totalIssues = daily.reduce((s, d) => s + (parseInt(d.count) || 0), 0);
                if (tfoot) {
                    tfoot.innerHTML =
                        '<tr class="table-danger fw-bold">' +
                        '<td>Total (L30)</td>' +
                        '<td class="text-end">$' + grandTotal.toFixed(2) + '</td>' +
                        '<td class="text-end">' + totalIssues + '</td>' +
                        '</tr>';
                }
            }

            loadL30Loss();

            document.getElementById('l30LossModal')?.addEventListener('show.bs.modal', () => {
                const daily = l30Data?.daily || [];
                const rangeEl = document.getElementById('l30-modal-range');
                if (rangeEl && l30Data) rangeEl.textContent = l30Data.from + ' → ' + l30Data.to;
                renderL30FullChart(daily);
                renderL30Table(daily);
            });

            // ── L30 Issues Badge ──────────────────────────────────────────────────
            const l30IssuesUrl = @json(route('customer.care.dispatch.issues.l30.issues'));
            let l30IssuesData       = null;
            let l30IssuesSparkChart = null;
            let l30IssuesFullChart  = null;

            async function loadL30Issues() {
                try {
                    const res = await fetch(l30IssuesUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) return;
                    const json = await res.json();
                    l30IssuesData = json;

                    const totalEl = document.getElementById('l30-issues-badge-total');
                    if (totalEl) totalEl.textContent = json.total || 0;
                    renderL30IssuesSparkline(json.daily || []);
                } catch (e) { /* silent */ }
            }

            function renderL30IssuesSparkline(daily) {
                const el = document.getElementById('l30-issues-sparkline-container');
                if (!el || typeof Highcharts === 'undefined') return;
                if (l30IssuesSparkChart) { l30IssuesSparkChart.destroy(); l30IssuesSparkChart = null; }
                l30IssuesSparkChart = Highcharts.chart(el, {
                    chart: { type: 'area', margin: [2,2,2,2], backgroundColor: 'transparent', animation: false },
                    title: { text: '' }, credits: { enabled: false },
                    xAxis: { visible: false },
                    yAxis: { visible: false, min: 0 },
                    legend: { enabled: false },
                    tooltip: {
                        headerFormat: '',
                        pointFormatter: function () { return '<b>' + this.category + '</b>: ' + this.y + ' issues'; },
                        outside: true,
                    },
                    plotOptions: {
                        area: {
                            marker: { enabled: false, states: { hover: { enabled: true, radius: 3 } } },
                            lineWidth: 1.5,
                            color: '#0d6efd',
                            fillOpacity: 0.15,
                            states: { hover: { lineWidth: 2 } },
                        },
                    },
                    series: [{
                        data: daily.length ? daily.map(d => ({ x: daily.indexOf(d), y: d.count, category: d.date })) : [0],
                    }],
                });
            }

            function renderL30IssuesFullChart(daily) {
                const el = document.getElementById('l30-issues-chart-full');
                if (!el || typeof Highcharts === 'undefined') return;
                if (l30IssuesFullChart) { l30IssuesFullChart.destroy(); l30IssuesFullChart = null; }
                l30IssuesFullChart = Highcharts.chart(el, {
                    chart: { type: 'area', backgroundColor: '#fff', animation: false },
                    title: { text: '' },
                    credits: { enabled: false },
                    xAxis: {
                        categories: daily.map(d => d.date),
                        labels: { rotation: -45, style: { fontSize: '10px' } },
                    },
                    yAxis: { title: { text: 'Issues' }, min: 0, allowDecimals: false },
                    legend: { enabled: false },
                    tooltip: {
                        headerFormat: '<b>{point.key}</b><br>',
                        pointFormat: 'Issues: <b>{point.y}</b>',
                    },
                    plotOptions: {
                        area: {
                            color: '#0d6efd',
                            fillOpacity: 0.12,
                            marker: { enabled: true, radius: 4, symbol: 'circle' },
                            dataLabels: {
                                enabled: true,
                                formatter: function () { return this.y > 0 ? this.y : ''; },
                                style: { fontSize: '9px', color: '#333', textOutline: 'none' },
                            },
                        },
                    },
                    series: [{ name: 'Issues', data: daily.map(d => d.count) }],
                });
            }

            function renderL30IssuesTable(daily) {
                const tbody = document.getElementById('l30-issues-table-body');
                const tfoot = document.getElementById('l30-issues-table-foot');
                if (!tbody) return;
                if (!daily.length) {
                    tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">No issues in the last 30 days.</td></tr>';
                    if (tfoot) tfoot.innerHTML = '';
                    return;
                }
                tbody.innerHTML = daily.slice().reverse().map(d =>
                    '<tr>' +
                    '<td>' + escapeHtml(d.date) + '</td>' +
                    '<td class="text-end fw-semibold">' + d.count + '</td>' +
                    '</tr>'
                ).join('');
                const grandTotal = daily.reduce((s, d) => s + (parseInt(d.count) || 0), 0);
                if (tfoot) {
                    tfoot.innerHTML =
                        '<tr class="table-primary fw-bold">' +
                        '<td>Total (L30)</td>' +
                        '<td class="text-end">' + grandTotal + '</td>' +
                        '</tr>';
                }
            }

            loadL30Issues();

            document.getElementById('l30IssuesModal')?.addEventListener('show.bs.modal', () => {
                const daily = l30IssuesData?.daily || [];
                const rangeEl = document.getElementById('l30-issues-modal-range');
                if (rangeEl && l30IssuesData) rangeEl.textContent = l30IssuesData.from + ' → ' + l30IssuesData.to;
                renderL30IssuesFullChart(daily);
                renderL30IssuesTable(daily);
            });
            @endif
        })();
    </script>
@endsection
