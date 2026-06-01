@extends('layouts.vertical', ['title' => $pageTitle ?? 'Orders On Hold', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .orders-hold-table {
            table-layout: fixed;
            width: 100%;
            font-size: 12px;
            border: 1px solid #dee2e6;
            border-collapse: collapse;
        }

        .orders-hold-table th,
        .orders-hold-table td {
            padding: 0.45rem 0.4rem;
            vertical-align: middle;
            border: 1px solid #dee2e6;
            text-align: center;
        }

        .orders-hold-table td {
            word-break: break-word;
            white-space: normal;
        }

        .orders-hold-col-idx {
            width: 4%;
        }

        .orders-hold-col-sku {
            width: 11%;
        }

        .orders-hold-col-qty {
            width: 7%;
        }

        .orders-hold-col-parent {
            width: 13%;
        }

        .orders-hold-col-mp {
            width: 11%;
        }

        .orders-hold-col-issue {
            width: 10%;
        }

        .orders-hold-col-created-by {
            width: 11%;
        }

        .orders-hold-col-created-at {
            width: 12%;
        }

        .orders-hold-col-action {
            width: 10%;
        }

        .orders-hold-col-what {
            width: 6%;
        }

        .orders-hold-col-close {
            width: 8%;
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

        .hold-delete-btn {
            color: #842029 !important;
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

        .cc-action-select {
            font-size: 12px;
            padding: 0.2rem 1.4rem 0.2rem 0.4rem;
            width: 100%;
            min-width: 0;
            max-width: 100%;
            box-sizing: border-box;
            font-weight: 600;
            border-color: #ced4da;
        }

        .orders-hold-table td.label-td,
        .orders-hold-table td.cc-history-td {
            text-align: center;
        }

        .parent-col {
            display: none;
        }

        .cc-action-col {
            display: none;
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

        .shopify-link-icon {
            color: #28a745;
            text-decoration: none;
            margin-left: 6px;
            font-size: 0.85rem;
            white-space: nowrap;
        }

        .m-link-icon {
            color: #20c4c4;
            text-decoration: none;
            font-size: 1.25rem;
            line-height: 1;
        }

        .m-link-icon:hover {
            color: #17a2a2;
        }

        .m-link-none {
            display: inline-block;
            width: 11px;
            height: 11px;
            border-radius: 50%;
            background-color: #dc3545;
        }

        .shopify-link-icon:hover {
            color: #1e7e34;
        }

        .sku-thumb {
            width: 44px;
            height: 44px;
            object-fit: contain;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background: #fff;
            padding: 2px;
        }

        .option-zoom {
            cursor: zoom-in;
        }

        #optionsHoverPreview {
            position: fixed;
            z-index: 2000;
            max-width: 340px;
            max-height: 340px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: #fff;
            padding: 4px;
            box-shadow: 0 6px 22px rgba(0, 0, 0, 0.28);
            display: none;
            pointer-events: none;
            object-fit: contain;
        }

        .cc-action-select.cc-action-bg-pending {
            background-color: #ffffff !important;
            color: #212529 !important;
        }

        .cc-action-select.cc-action-bg-offer {
            background-color: #ffe600 !important;
            color: #4d3b00 !important;
        }

        .cc-action-select.cc-action-bg-refunded {
            background-color: #00e676 !important;
            color: #0a3d22 !important;
        }

        .cc-action-select.cc-action-bg-sent {
            background-color: #cfe2ff !important;
            color: #084298 !important;
        }

        /* History badges reuse the bg classes without the select scope */
        .badge.cc-action-bg-offer {
            background-color: #ffe600 !important;
            color: #4d3b00 !important;
        }

        .badge.cc-action-bg-refunded {
            background-color: #00e676 !important;
            color: #0a3d22 !important;
        }

        .badge.cc-action-bg-sent {
            background-color: #cfe2ff !important;
            color: #084298 !important;
        }

        .badge.cc-action-bg-pending {
            background-color: #e9ecef !important;
            color: #212529 !important;
        }

        .cc-history-cell {
            text-align: left;
            font-size: 11px;
            line-height: 1.3;
        }

        .cc-history-entry {
            padding: 2px 0;
        }

        .cc-history-view-btn {
            font-size: 11px;
            text-decoration: none;
        }

        .created-cell-wrap {
            font-size: 11px;
            line-height: 1.3;
        }

        .created-history-btn {
            font-size: 11px;
            text-decoration: none;
        }

        .cc-history-val {
            font-weight: 600;
            display: block;
        }

        .cc-history-meta {
            color: #6c757d;
            display: block;
        }

        .label-dot-btn {
            border: 0;
            background: transparent;
            padding: 2px;
            line-height: 1;
            cursor: pointer;
        }

        .label-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            display: inline-block;
            vertical-align: middle;
        }

        .label-dot-red {
            background-color: #dc3545;
        }

        .label-dot-green {
            background-color: #198754;
        }

        .label-dot-blue {
            background-color: #0d6efd;
        }

        .options-bulb-btn {
            border: 0;
            background: transparent;
            padding: 2px;
            line-height: 1;
            cursor: pointer;
        }

        .options-bulb-btn i {
            color: #ffc107;
            font-size: 1.15rem;
            filter: drop-shadow(0 0 3px rgba(255, 193, 7, 0.85));
            transition: filter 0.15s ease, transform 0.15s ease;
        }

        .options-bulb-btn.options-bulb-on i {
            filter: drop-shadow(0 0 6px rgba(255, 193, 7, 1)) drop-shadow(0 0 10px rgba(255, 193, 7, 0.7));
        }

            .options-bulb-btn:hover i {
                transform: scale(1.15);
                filter: drop-shadow(0 0 7px rgba(255, 193, 7, 1)) drop-shadow(0 0 12px rgba(255, 193, 7, 0.8));
            }

            .options-bulb-btn.options-bulb-none i {
                color: #6c757d;
                filter: none;
            }

            .options-bulb-btn.options-bulb-none:hover i {
                color: #495057;
                filter: none;
            }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $pageTitle ?? 'Orders On Hold',
        'sub_title' => 'Customer Care',
    ])

    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-start align-items-center gap-2 mb-3">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ordersOnHoldIssueModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Hold Issue
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
            </div>
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-0">Use <strong>Add Hold Issue</strong> to record SKU hold issues. SKU lookup auto-fills Parent and available QTY.</p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <h5 class="mb-0">{{ $recordsTitle ?? 'Orders On Hold Records' }}</h5>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <select id="dept-filter-select" class="form-select form-select-sm" style="min-width: 180px;">
                            <option value="">All Departments</option>
                        </select>
                        <span class="badge bg-light text-dark" id="hold_issue_total_count">0</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm mb-0 orders-hold-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="orders-hold-col-idx">#</th>
                                    <th class="orders-hold-col-qty">Image</th>
                                    <th class="orders-hold-col-sku">SKU</th>
                                    <th class="orders-hold-col-qty">Shopify</th>
                                    <th class="orders-hold-col-action">Ord</th>
                                    <th class="orders-hold-col-qty">Qty Avl</th>
                                    <th class="orders-hold-col-qty">Order Qty</th>
                                    <th class="orders-hold-col-parent parent-col">Parent</th>
                                    <th class="orders-hold-col-mp">MKT1</th>
                                    <th class="orders-hold-col-mp">MKT2</th>
                                    <th class="orders-hold-col-qty">M link</th>
                                    <th class="orders-hold-col-what">Issue?</th>
                                    <th class="orders-hold-col-qty">Options</th>
                                    <th class="orders-hold-col-action cc-action-col" title="Customer Care Action">CC
                                        action</th>
                                    <th class="orders-hold-col-qty" title="Customer Care Action">CC Action</th>
                                    <th class="orders-hold-col-created-at" title="Customer Care Action change history">
                                        CC History</th>                                    <th class="orders-hold-col-issue">Root Cause</th>
                                    <th class="orders-hold-col-action">Root Cause Fixed</th>
                                    <th class="orders-hold-col-close">Close</th>
                                    <th class="orders-hold-col-mp">Dept</th>
                                    <th class="orders-hold-col-created-at">Created</th>
                                </tr>
                            </thead>
                            <tbody id="hold_issue_table_body">
                                <tr id="hold_issue_empty_row">
                                    <td colspan="21" class="text-center text-muted py-4">No records found.</td>
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
                                    <th class="orders-hold-col-qty">Shopify</th>
                                    <th class="orders-hold-col-qty">Qty Avl</th>
                                    <th class="orders-hold-col-qty">Order Qty</th>
                                    <th class="orders-hold-col-parent parent-col">Parent</th>
                                    <th class="orders-hold-col-mp">MKT1</th>
                                    <th class="orders-hold-col-mp">MKT2</th>
                                    <th class="orders-hold-col-what">Issue?</th>
                                    <th class="orders-hold-col-action cc-action-col" title="Customer Care Action">CC
                                        action</th>                                    <th class="orders-hold-col-issue">Root Cause</th>
                                    <th class="orders-hold-col-action">Root Cause Fixed</th>
                                    <th class="orders-hold-col-action">Close</th>
                                    <th class="orders-hold-col-action">Event</th>
                                    <th class="orders-hold-col-mp">Dept</th>
                                    <th class="orders-hold-col-created-by">Created By</th>
                                    <th class="orders-hold-col-created-at">Logged At</th>
                                </tr>
                            </thead>
                            <tbody id="hold_issue_history_table_body">
                                <tr id="hold_issue_history_empty_row">
                                    <td colspan="17" class="text-center text-muted py-4">No history found.</td>
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
                        <code>sku, qty, order_qty, parent, marketplace_1, marketplace_2, what_happened, action_1, action_1_remark, replacement_tracking, issue, issue_remark, c_action_1, c_action_1_remark</code>
                    </p>
                    <p class="text-muted small mb-3">Required: <strong>sku</strong>, <strong>qty</strong>, <strong>issue</strong> (Root Cause Found).</p>
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

    {{-- ── Label Checklist Modal ── --}}
    <div class="modal fade" id="labelModal" tabindex="-1" aria-labelledby="labelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="labelModalLabel"><i class="bi bi-tag me-2"></i>CC Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="labelModalAlert" class="alert alert-danger d-none mb-3" role="alert"></div>
                    <input type="hidden" id="label_issue_id" value="">
                    <div class="form-check mb-3">
                        <input class="form-check-input label-check" type="checkbox" id="label_alternate_upgrade_done">
                        <label class="form-check-label" for="label_alternate_upgrade_done">Alternate/Upgrade Done?</label>
                    </div>
                    <div class="form-check mb-3 ms-4 d-none" id="label_q2_wrap">
                        <input class="form-check-input label-check" type="checkbox" id="label_stock_adjustment_done">
                        <label class="form-check-label" for="label_stock_adjustment_done">If Alternate/Upgrade Sent, Has
                            the Stock Adjustment Being Done?</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input label-check" type="checkbox" id="label_refunded">
                        <label class="form-check-label" for="label_refunded">Refunded?</label>
                    </div>
                    <div class="form-check mb-3 ms-4 d-none" id="label_q4_wrap">
                        <input class="form-check-input label-check" type="checkbox" id="label_label_voided">
                        <label class="form-check-label" for="label_label_voided">If refunded, Has a label being
                            Voided?</label>
                    </div>
                    <p class="text-muted small mb-0">Only one set can be selected. Ticking
                        <strong>Alternate/Upgrade + Stock Adjustment</strong> shows a
                        <span class="label-dot label-dot-green d-inline-block align-middle"></span> green dot;
                        ticking <strong>Refunded + Label Voided</strong> shows a
                        <span class="label-dot label-dot-blue d-inline-block align-middle"></span> blue dot.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="labelSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Options (Variant / Upgrade SKU) Modal ── --}}
    <div class="modal fade" id="optionsModal" tabindex="-1" aria-labelledby="optionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="optionsModalLabel"><i
                            class="bi bi-lightbulb-fill text-warning me-2"></i>Options</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="optionsModalAlert" class="alert alert-danger d-none mb-3" role="alert"></div>
                    <div id="optionsReadonlyNote" class="alert alert-info d-none py-2 mb-3 small" role="alert">
                        View only. Adding or removing options is restricted to authorised users.
                    </div>
                    <input type="hidden" id="options_issue_id" value="">
                    <div class="mb-3 text-center d-none" id="options_sku_image_wrap">
                        <img src="" alt="SKU Image" id="options_sku_image" class="sku-image-preview option-zoom">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SKU</label>
                        <input type="text" class="form-control" id="options_sku_display" readonly>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="options_no_options">
                        <label class="form-check-label fw-semibold" for="options_no_options">No options</label>
                        <div class="form-text">No variant/upgrade options for this SKU. The bulb shows a grey cross.</div>
                    </div>

                    <datalist id="options_sku_datalist"></datalist>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Variant SKUs</label>
                        <div class="input-group input-group-sm mb-2 options-add-row" id="variant_add_row">
                            <input type="text" class="form-control" id="options_variant_input"
                                list="options_sku_datalist" placeholder="Search SKU from product master…">
                            <button type="button" class="btn btn-outline-primary" id="options_variant_add_btn"><i
                                    class="bi bi-plus-lg"></i> Add</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0 options-items-table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:70px;">Image</th>
                                        <th>SKU</th>
                                        <th style="width:120px;">Inv Available</th>
                                        <th style="width:48px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="options_variant_tbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold">Upgrade SKUs</label>
                        <div class="input-group input-group-sm mb-2 options-add-row" id="upgrade_add_row">
                            <input type="text" class="form-control" id="options_upgrade_input"
                                list="options_sku_datalist" placeholder="Search SKU from product master…">
                            <button type="button" class="btn btn-outline-primary" id="options_upgrade_add_btn"><i
                                    class="bi bi-plus-lg"></i> Add</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0 options-items-table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:70px;">Image</th>
                                        <th>SKU</th>
                                        <th style="width:120px;">Inv Available</th>
                                        <th style="width:48px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="options_upgrade_tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="optionsSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CC Action History Modal ── --}}
    <div class="modal fade" id="ccHistoryModal" tabindex="-1" aria-labelledby="ccHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ccHistoryModalLabel"><i class="bi bi-clock-history me-2"></i>CC Action
                        History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><strong>SKU:</strong> <span id="cc_history_sku"></span></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:48px;">#</th>
                                    <th>Status</th>
                                    <th>By</th>
                                    <th>Date &amp; Time</th>
                                </tr>
                            </thead>
                            <tbody id="cc_history_tbody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Created / Edit History Modal ── --}}
    <div class="modal fade" id="createdHistoryModal" tabindex="-1" aria-labelledby="createdHistoryModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createdHistoryModalLabel"><i class="bi bi-clock-history me-2"></i>Created
                        / Edit History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2"><strong>SKU:</strong> <span id="created_history_sku"></span></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:48px;">#</th>
                                    <th>Event</th>
                                    <th>By</th>
                                    <th>Date &amp; Time</th>
                                </tr>
                            </thead>
                            <tbody id="created_history_tbody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ordersOnHoldIssueModal" tabindex="-1" aria-labelledby="ordersOnHoldIssueModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ordersOnHoldIssueModalLabel">Orders On Hold Issue</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="ordersOnHoldIssueForm" autocomplete="off">
                    <div class="modal-body">
                        <div id="ordersOnHoldIssueAlert" class="alert alert-danger d-none mb-3" role="alert"></div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="hold_issue_sku" class="form-label">SKU <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="hold_issue_sku" name="sku"
                                    list="hold_issue_sku_datalist" placeholder="Search SKU" required>
                                <datalist id="hold_issue_sku_datalist"></datalist>
                                <div class="mt-2 d-none" id="hold_issue_sku_image_wrap">
                                    <img src="" alt="SKU Image" id="hold_issue_sku_image" class="sku-image-preview">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_order_number" class="form-label">Ord</label>
                                <input type="text" class="form-control" id="hold_issue_order_number"
                                    name="order_number" placeholder="Enter order number">
                            </div>

                            <div class="col-md-4">
                                <label for="hold_issue_qty" class="form-label">Qty as in Stock</label>
                                <input type="number" class="form-control" id="hold_issue_qty" name="qty" readonly>
                            </div>

                            <div class="col-md-4">
                                <label for="hold_issue_order_qty" class="form-label">Order Qty</label>
                                <input type="number" class="form-control" id="hold_issue_order_qty" name="order_qty"
                                    min="0" step="1" placeholder="Enter order qty">
                            </div>

                            <div class="col-md-4">
                                <label for="hold_issue_parent" class="form-label">Parent</label>
                                <input type="text" class="form-control" id="hold_issue_parent" name="parent" readonly>
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_marketplace_1" class="form-label">MKT1</label>
                                <input type="text" class="form-control" id="hold_issue_marketplace_1" name="marketplace_1"
                                    list="hold_issue_marketplace_datalist" placeholder="Select Marketplace">
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_marketplace_2" class="form-label">MKT2</label>
                                <input type="text" class="form-control" id="hold_issue_marketplace_2" name="marketplace_2"
                                    list="hold_issue_marketplace_datalist" placeholder="Select Marketplace">
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_what_happened" class="form-label">Issue?</label>
                                <select class="form-select" id="hold_issue_what_happened" name="what_happened">
                                    <option value="">Select</option>
                                    <option value="0 Stock">0 Stock</option>
                                    <option value="Damaged">Damaged</option>
                                </select>
                            </div>

                            <datalist id="hold_issue_marketplace_datalist">
                                @foreach (($marketplaces ?? collect()) as $marketplace)
                                    <option value="{{ $marketplace }}"></option>
                                @endforeach
                            </datalist>

                            <div class="col-md-4 d-none">
                                <label for="hold_issue_action_1" class="form-label" title="Customer Care Action">CC
                                    action</label>
                                <select class="form-select" id="hold_issue_action_1" name="action_1">
                                    <option value="Pending" selected>Pending</option>
                                    <option value="V/U Offer">V/U Offer</option>
                                    <option value="Refunded">Refunded</option>
                                    <option value="V/U Sent">V/U Sent</option>
                                </select>
                            </div>

                            <div class="col-md-8 d-none" id="action1RemarkWrap">
                                <label for="hold_issue_action_1_remark" class="form-label">Action Remark</label>
                                <input type="text" class="form-control" id="hold_issue_action_1_remark" name="action_1_remark"
                                    placeholder="Write remark for Other">
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_replacement_tracking" class="form-label">Replacement Tracking Number</label>
                                <input type="text" class="form-control" id="hold_issue_replacement_tracking"
                                    name="replacement_tracking" maxlength="50" placeholder="Optional tracking number">
                            </div>

                            <div class="col-12">
                                <label for="hold_issue_text" class="form-label">Root Cause Found <span class="text-danger">*</span></label>
                                <select class="form-select" id="hold_issue_text" name="issue" required>
                                    <option value="">Select Root Cause Found</option>
                                    <option value="Mapping">Mapping</option>
                                    <option value="Replacement Issued But not Entered">Replacement Issued But not Entered</option>
                                    <option value="FBA stock Issued But not Entered">FBA stock Issued But not Entered</option>
                                    <option value="Alternate Issued But not Entered">Alternate Issued But not Entered</option>
                                    <option value="Stock Balance not Entered">Stock Balance not Entered</option>
                                    <option value="Reserve Stock Issue">Reserve Stock Issue</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="col-12 d-none" id="rootCauseRemarkWrap">
                                <label for="hold_issue_remark" class="form-label">Root Cause Remark</label>
                                <input type="text" class="form-control" id="hold_issue_remark" name="issue_remark"
                                    placeholder="Write remark for Other">
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_c_action_1" class="form-label">Root Cause Fixed</label>
                                <input type="text" class="form-control" id="hold_issue_c_action_1" name="c_action_1"
                                    list="hold_issue_root_cause_fixed_datalist" placeholder="Select Root Cause Fixed">
                                <datalist id="hold_issue_root_cause_fixed_datalist">
                                    <option value="Mapping Fixed"></option>
                                    <option value="Replacement Entry Fixed"></option>
                                    <option value="FBA Entry Fixed"></option>
                                    <option value="Alternate Entry Fixed"></option>
                                    <option value="Stock Balance Fixed"></option>
                                    <option value="Reserve Stock Fixed"></option>
                                    <option value="Other"></option>
                                </datalist>
                            </div>

                            <div class="col-md-6 d-none" id="cAction1RemarkWrap">
                                <label for="hold_issue_c_action_1_remark" class="form-label">Root Cause Fixed Remark</label>
                                <input type="text" class="form-control" id="hold_issue_c_action_1_remark"
                                    name="c_action_1_remark" placeholder="Write remark for Other">
                            </div>

                            <div class="col-md-6">
                                <label for="hold_issue_department" class="form-label">Department <span
                                        class="text-danger">*</span></label>
                                <select class="form-select" id="hold_issue_department" name="department[]" multiple
                                    size="5" required>
                                    <option value="Dispatch">Dispatch</option>
                                    <option value="Shipping">Shipping</option>
                                    <option value="Listing">Listing</option>
                                    <option value="Carrier">Carrier and Claim</option>
                                    <option value="Carrier Issue">Carrier Issue</option>
                                    <option value="Customer Care">Customer Care</option>
                                    <option value="Pricing">Pricing</option>
                                    <option value="QC">QC</option>
                                    <option value="Packaging">Packaging</option>
                                    <option value="Orders on Hold">Orders on Hold</option>
                                </select>
                                <div class="form-text">Select one or more. Hold <kbd>Ctrl</kbd> (Windows) or
                                    <kbd>⌘</kbd> (Mac) for multiple.</div>
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
@endsection

@section('script')
    <script>
        (function() {
            const skuSearchUrl = @json(route('customer.care.followups.skus'));
            const skuDetailsUrl = @json(route('customer.care.orders.on.hold.sku.details'));
            const recordsListUrl = @json(route('customer.care.orders.on.hold.issues.index'));
            const recordsStoreUrl = @json(route('customer.care.orders.on.hold.issues.store'));
            const recordsUpdateBaseUrl = @json(url('/customer-care/orders-on-hold/issues'));
            const historyListUrl = @json(route('customer.care.orders.on.hold.history.index'));
            const shopifyStoreUrl = @json(config('services.shopify.store_url'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            function shopifySkuLinkHtml(sku) {
                const s = String(sku || '').trim();
                if (!s || !shopifyStoreUrl) return '';
                const url = 'https://' + shopifyStoreUrl + '/admin/products/inventory?query=' +
                    encodeURIComponent(s);
                return ' <a href="' + url +
                    '" target="_blank" rel="noopener" class="shopify-link-icon" title="View in Shopify"><i class="bi bi-box-arrow-up-right"></i></a>';
            }

            function shopifyCellHtml(sku) {
                const s = String(sku || '').trim();
                if (!s || !shopifyStoreUrl) return '<span class="text-muted">—</span>';
                const url = 'https://' + shopifyStoreUrl + '/admin/products/inventory?query=' +
                    encodeURIComponent(s);
                return '<a href="' + url +
                    '" target="_blank" rel="noopener" class="shopify-link-icon" title="View in Shopify"><i class="bi bi-box-arrow-up-right"></i></a>';
            }

            function skuImageHtml(row) {
                const url = String(row?.image_url || '').trim();
                if (!url) return '<span class="text-muted">—</span>';
                return '<img src="' + escAttr(url) + '" alt="" class="sku-thumb option-zoom" loading="lazy">';
            }

            function mLinkHtml(row) {
                const url = String(row?.m_link || '').trim();
                if (!url) {
                    return '<span class="m-link-none" title="No marketplace Customer Care message link"></span>';
                }
                return '<a href="' + escAttr(url) +
                    '" target="_blank" rel="noopener" class="m-link-icon" title="Open marketplace Customer Care message page"><i class="bi bi-link-45deg"></i></a>';
            }

            const skuInput = document.getElementById('hold_issue_sku');
            const orderNumberInput = document.getElementById('hold_issue_order_number');
            const skuDatalist = document.getElementById('hold_issue_sku_datalist');
            const skuImageWrap = document.getElementById('hold_issue_sku_image_wrap');
            const skuImage = document.getElementById('hold_issue_sku_image');
            const qtyInput = document.getElementById('hold_issue_qty');
            const orderQtyInput = document.getElementById('hold_issue_order_qty');
            const parentInput = document.getElementById('hold_issue_parent');
            const marketplace1Input = document.getElementById('hold_issue_marketplace_1');
            const marketplace2Input = document.getElementById('hold_issue_marketplace_2');
            const whatHappenedInput = document.getElementById('hold_issue_what_happened');
            const issueInput = document.getElementById('hold_issue_text');
            const issueRemarkInput = document.getElementById('hold_issue_remark');
            const rootCauseRemarkWrap = document.getElementById('rootCauseRemarkWrap');
            const action1Input = document.getElementById('hold_issue_action_1');
            const action1RemarkInput = document.getElementById('hold_issue_action_1_remark');
            const action1RemarkWrap = document.getElementById('action1RemarkWrap');
            const replacementTrackingInput = document.getElementById('hold_issue_replacement_tracking');
            const cAction1Input = document.getElementById('hold_issue_c_action_1');
            const cAction1RemarkInput = document.getElementById('hold_issue_c_action_1_remark');
            const cAction1RemarkWrap = document.getElementById('cAction1RemarkWrap');
            const departmentInput = document.getElementById('hold_issue_department');
            const deptFilterSelect = document.getElementById('dept-filter-select');
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

            function parseDepartmentList(val) {
                if (Array.isArray(val)) return val.map(x => String(x).trim()).filter(Boolean);
                if (val === null || val === undefined) return [];
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

            function departmentDisplayHtml(r) {
                const depts = rowDepartments(r);
                if (!depts.length) return '—';
                return escapeHtml(depts.join(', '));
            }

            const CC_ACTION_OPTIONS = ['Pending', 'V/U Offer', 'Refunded', 'V/U Sent'];

            function ccActionValue(row) {
                const v = String(row?.action_1 ?? '').trim();
                return CC_ACTION_OPTIONS.includes(v) ? v : 'Pending';
            }

            function ccActionBgClass(value) {
                switch (String(value || 'Pending').trim()) {
                    case 'V/U Offer':
                        return 'cc-action-bg-offer';
                    case 'Refunded':
                        return 'cc-action-bg-refunded';
                    case 'V/U Sent':
                        return 'cc-action-bg-sent';
                    default:
                        return 'cc-action-bg-pending';
                }
            }

            function ccActionSelectHtml(row) {
                const current = ccActionValue(row);
                let opts = '';
                CC_ACTION_OPTIONS.forEach(o => {
                    opts += '<option value="' + escAttr(o) + '"' + (o === current ? ' selected' : '') + '>' +
                        escapeHtml(o) + '</option>';
                });
                return '<select class="form-select form-select-sm cc-action-select ' + ccActionBgClass(current) +
                    '" data-id="' + escAttr(row.id) + '">' + opts + '</select>';
            }

            function ccHistoryHtml(row) {
                const hist = Array.isArray(row?.cc_action_history) ? row.cc_action_history : [];
                if (!hist.length) return '<span class="text-muted">—</span>';
                const latest = hist[hist.length - 1] || {};
                const v = escapeHtml(latest.value ?? '');
                const by = escapeHtml(latest.by ?? '');
                const at = escapeHtml(latest.at ?? '');
                return '<div class="cc-history-cell">' +
                    '<div class="cc-history-entry"><span class="cc-history-val">' + v +
                    '</span><span class="cc-history-meta">' + by + ' · ' + at + '</span></div>' +
                    '<button type="button" class="btn btn-link btn-sm p-0 cc-history-view-btn" data-id="' + escAttr(
                        row.id) + '">View all (' + hist.length + ')</button>' +
                    '</div>';
            }

            function rowLabel(row) {
                const l = row?.label || {};
                return {
                    alternate_upgrade_done: !!l.alternate_upgrade_done,
                    stock_adjustment_done: !!l.stock_adjustment_done,
                    refunded: !!l.refunded,
                    label_voided: !!l.label_voided,
                };
            }

            function labelDotState(row) {
                const l = rowLabel(row);
                if (l.alternate_upgrade_done && l.stock_adjustment_done) return 'green';
                if (l.refunded && l.label_voided) return 'blue';
                return 'red';
            }

            function labelDotHtml(row) {
                const state = labelDotState(row);
                const cls = state === 'green' ? 'label-dot-green' :
                    (state === 'blue' ? 'label-dot-blue' : 'label-dot-red');
                return '<button type="button" class="label-dot-btn" data-id="' + escAttr(row.id) +
                    '" title="Open label checklist"><span class="label-dot ' + cls + '"></span></button>';
            }

            function createdCellHtml(row) {
                const by = escapeHtml(row.created_by || '—');
                const at = escapeHtml(row.created_at || '');
                return '<div class="created-cell-wrap">' +
                    '<div class="created-by fw-semibold">' + by + '</div>' +
                    '<div class="created-at text-muted">' + at + '</div>' +
                    '<button type="button" class="btn btn-link btn-sm p-0 created-history-btn" data-id="' + escAttr(
                        row.id) + '">View history</button>' +
                    '</div>';
            }

            function rowOptions(row) {
                const o = row?.options || {};
                return {
                    variant_skus: Array.isArray(o.variant_skus) ? o.variant_skus : [],
                    upgrade_skus: Array.isArray(o.upgrade_skus) ? o.upgrade_skus : [],
                    no_options: !!o.no_options,
                };
            }

            function optionsBulbHtml(row) {
                const o = rowOptions(row);
                if (o.no_options) {
                    return '<button type="button" class="options-bulb-btn options-bulb-none" data-id="' + escAttr(row
                        .id) + '" title="No options"><i class="bi bi-x-circle-fill"></i></button>';
                }
                const lit = (o.variant_skus.length || o.upgrade_skus.length) ? ' options-bulb-on' : '';
                return '<button type="button" class="options-bulb-btn' + lit + '" data-id="' + escAttr(row.id) +
                    '" title="Variant / Upgrade SKU options"><i class="bi bi-lightbulb-fill"></i></button>';
            }

            function getDepartmentPayload() {
                if (!departmentInput) return [];
                return Array.from(departmentInput.selectedOptions || []).map(o => o.value.trim()).filter(Boolean);
            }

            function setDepartmentMultiSelect(record) {
                if (!departmentInput) return;
                const depts = rowDepartments(record);
                Array.from(departmentInput.options).forEach(o => {
                    o.selected = depts.includes(o.value);
                });
            }

            function clearDepartmentMultiSelect() {
                if (!departmentInput) return;
                Array.from(departmentInput.options).forEach(o => {
                    o.selected = false;
                });
            }

            function rowMatchesActiveDeptFilter(r) {
                if (!activeDeptFilter) return true;
                const needle = String(activeDeptFilter).trim().toLowerCase();
                return rowDepartments(r).some(d => String(d).trim().toLowerCase() === needle);
            }

            function getFilteredRows() {
                if (!activeDeptFilter) return holdIssueRows;
                return holdIssueRows.filter(rowMatchesActiveDeptFilter);
            }

            function buildDeptFilters() {
                if (!deptFilterSelect) return;
                const counts = {};
                holdIssueRows.forEach(r => {
                    rowDepartments(r).forEach(d => {
                        if (d) counts[d] = (counts[d] || 0) + 1;
                    });
                });
                const depts = Object.keys(counts).sort();
                const prev = deptFilterSelect.value !== '' ? deptFilterSelect.value : (activeDeptFilter || '');
                deptFilterSelect.innerHTML = '<option value="">All Departments (' + holdIssueRows.length + ')</option>';
                depts.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d;
                    opt.textContent = d + ' (' + counts[d] + ')';
                    if (d === prev) opt.selected = true;
                    deptFilterSelect.appendChild(opt);
                });
                if (prev && !Object.prototype.hasOwnProperty.call(counts, prev)) {
                    activeDeptFilter = null;
                    deptFilterSelect.value = '';
                } else if (prev) {
                    activeDeptFilter = prev;
                    deptFilterSelect.value = prev;
                }
            }

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

            function updateTotalCount() {
                totalCountEl.textContent = String(holdIssueRows.length);
            }

            function renderRows() {
                if (!tableBody) return;

                buildDeptFilters();
                const rows = getFilteredRows();

                if (!rows.length) {
                    if (emptyRow) emptyRow.classList.remove('d-none');
                    tableBody.innerHTML = emptyRow ? emptyRow.outerHTML : '';
                    const e = document.getElementById('hold_issue_empty_row');
                    if (e) e.classList.remove('d-none');
                    updateTotalCount();
                    return;
                }

                if (emptyRow) emptyRow.classList.add('d-none');

                const dataRowsHtml = rows.map((row, index) => {
                    const buttonsHtml =
                        '<div class="hold-close-actions">' +
                        '<button type="button" class="btn btn-sm hold-action-btn hold-edit-btn" data-id="' + row.id +
                        '" title="Edit"><i class="bi bi-pencil-fill"></i></button>' +
                        '<button type="button" class="btn btn-sm hold-action-btn hold-delete-btn" data-id="' + row.id +
                        '" title="Delete permanently"><i class="bi bi-trash-fill"></i></button>' +
                        '</div>';
                    return '<tr>' +
                        '<td>' + escapeHtml(row.id) + '</td>' +
                        '<td>' + skuImageHtml(row) + '</td>' +
                        '<td>' + escapeHtml(row.sku) + '</td>' +
                        '<td>' + shopifyCellHtml(row.sku) + '</td>' +
                        '<td class="order-num-cell">' + (row.order_number ?
                            '<button class="copy-order-btn" data-copy="' + escAttr(row.order_number) +
                            '" title="' + escAttr(row.order_number) +
                            '"><i class="bi bi-clipboard"></i></button><span class="order-num-short">' +
                            escapeHtml(row.order_number) + '</span>' : '—') + '</td>' +
                        '<td>' + escapeHtml(row.qty) + '</td>' +
                        '<td>' + escapeHtml(row.order_qty) + '</td>' +
                        '<td class="parent-col">' + escapeHtml(row.parent) + '</td>' +
                        '<td>' + escapeHtml(row.marketplace_1) + '</td>' +
                        '<td>' + escapeHtml(row.marketplace_2) + '</td>' +
                        '<td class="m-link-td">' + mLinkHtml(row) + '</td>' +
                        '<td>' + whatHappenedDotHtml(row.what_happened) + '</td>' +
                        '<td class="options-td">' + optionsBulbHtml(row) + '</td>' +
                        '<td class="cc-action-col">' + ccActionSelectHtml(row) + '</td>' +
                        '<td class="label-td">' + labelDotHtml(row) + '</td>' +
                        '<td class="cc-history-td">' + ccHistoryHtml(row) + '</td>' +                        '<td>' + rootCauseDisplayHtml(row.issue, row.issue_remark) + '</td>' +
                        '<td>' + rootCauseFixedDisplayHtml(row.c_action_1, row.c_action_1_remark) + '</td>' +
                        '<td class="orders-hold-close-cell">' + buttonsHtml + '</td>' +
                        '<td>' + departmentDisplayHtml(row) + '</td>' +
                        '<td class="created-cell">' + createdCellHtml(row) + '</td>' +
                        '</tr>';
                }).join('');

                tableBody.innerHTML = (emptyRow ? emptyRow.outerHTML : '') + dataRowsHtml;
                const nextEmpty = document.getElementById('hold_issue_empty_row');
                if (nextEmpty) nextEmpty.classList.add('d-none');
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
                        '<td>' + shopifyCellHtml(row.sku) + '</td>' +
                        '<td>' + escapeHtml(row.qty) + '</td>' +
                        '<td>' + escapeHtml(row.order_qty) + '</td>' +
                        '<td class="parent-col">' + escapeHtml(row.parent) + '</td>' +
                        '<td>' + escapeHtml(row.marketplace_1) + '</td>' +
                        '<td>' + escapeHtml(row.marketplace_2) + '</td>' +
                        '<td>' + whatHappenedDotHtml(row.what_happened) + '</td>' +
                        '<td class="cc-action-col"><span class="badge ' + ccActionBgClass(ccActionValue(row)) +
                        '" style="font-weight:600;">' + escapeHtml(ccActionValue(row)) + '</span></td>' +                        '<td>' + rootCauseDisplayHtml(row.issue, row.issue_remark) + '</td>' +
                        '<td>' + rootCauseFixedDisplayHtml(row.c_action_1, row.c_action_1_remark) + '</td>' +
                        '<td>' + escapeHtml(row.close_note) + '</td>' +
                        '<td>' + escapeHtml(row.event_type) + '</td>' +
                        '<td>' + departmentDisplayHtml(row) + '</td>' +
                        '<td>' + escapeHtml(row.created_by) + '</td>' +
                        '<td>' + escapeHtml(row.logged_at) + '</td>' +
                        '</tr>';
                }).join('');

                historyTableBody.innerHTML = (historyEmptyRow ? historyEmptyRow.outerHTML : '') + dataRowsHtml;
                const nextEmpty = document.getElementById('hold_issue_history_empty_row');
                if (nextEmpty && holdIssueHistoryRows.length) nextEmpty.classList.add('d-none');
                updateHistoryTotalCount();
            }

            function formatDateToMonthDay(dateStr) {
                if (!dateStr) return '';
                const parts = String(dateStr).split(' ')[0].split('-');
                if (parts.length >= 3) {
                    const day = parseInt(parts[0], 10);
                    const monthIndex = parseInt(parts[1], 10) - 1;
                    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                    return day + ' ' + months[monthIndex];
                }
                return dateStr;
            }

            function normalizeRecord(row) {
                return {
                    id: row?.id ?? null,
                    sku: row?.sku ?? '',
                    image_url: row?.image_url ?? '',
                    m_link: row?.m_link ?? '',
                    order_number: row?.order_number ?? '',
                    qty: row?.qty ?? 0,
                    order_qty: row?.order_qty ?? '',
                    parent: row?.parent ?? '',
                    marketplace_1: row?.marketplace_1 ?? '',
                    marketplace_2: row?.marketplace_2 ?? '',
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
                    departments: Array.isArray(row?.departments) ? row.departments : parseDepartmentList(row?.department),
                    cc_action_history: Array.isArray(row?.cc_action_history) ? row.cc_action_history : [],
                    label: {
                        alternate_upgrade_done: !!(row?.label?.alternate_upgrade_done),
                        stock_adjustment_done: !!(row?.label?.stock_adjustment_done),
                        refunded: !!(row?.label?.refunded),
                        label_voided: !!(row?.label?.label_voided),
                    },
                    options: {
                        variant_skus: Array.isArray(row?.options?.variant_skus) ? row.options.variant_skus : [],
                        upgrade_skus: Array.isArray(row?.options?.upgrade_skus) ? row.options.upgrade_skus : [],
                        no_options: !!(row?.options?.no_options),
                    },
                    created_by: row?.created_by ?? 'System',
                    created_at: formatDateToMonthDay(row?.created_at_display ?? row?.created_at ?? ''),
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
                    marketplace_2: row?.marketplace_2 ?? '',
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
                    departments: Array.isArray(row?.departments) ? row.departments : parseDepartmentList(row?.department),
                    created_by: row?.created_by ?? 'System',
                    logged_at: formatDateToMonthDay(row?.logged_at_display ?? row?.logged_at ?? ''),
                    logged_at_full: row?.logged_at_display ?? row?.logged_at ?? '',
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
                if (orderNumberInput) orderNumberInput.value = '';
                qtyInput.value = '';
                orderQtyInput.value = '';
                parentInput.value = '';
                resetSkuImage();
                marketplace1Input.value = '';
                marketplace2Input.value = '';
                whatHappenedInput.value = '';
                issueRemarkInput.value = '';
                toggleRootCauseRemarkField();
                action1Input.value = 'Pending';
                action1RemarkInput.value = '';
                toggleAction1RemarkField();
                replacementTrackingInput.value = '';
                cAction1Input.value = '';
                cAction1RemarkInput.value = '';
                toggleCAction1RemarkField();
                clearDepartmentMultiSelect();
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
                if (orderNumberInput) orderNumberInput.value = record.order_number || '';
                qtyInput.value = record.qty ?? '';
                orderQtyInput.value = record.order_qty ?? '';
                parentInput.value = record.parent || '';
                marketplace1Input.value = record.marketplace_1 || '';
                marketplace2Input.value = record.marketplace_2 || '';
                whatHappenedInput.value = record.what_happened || '';
                issueInput.value = record.issue || '';
                issueRemarkInput.value = record.issue_remark || '';
                toggleRootCauseRemarkField();
                action1Input.value = ccActionValue(record);
                action1RemarkInput.value = record.action_1_remark || '';
                toggleAction1RemarkField();
                replacementTrackingInput.value = record.replacement_tracking || '';
                cAction1Input.value = record.c_action_1 || '';
                cAction1RemarkInput.value = record.c_action_1_remark || '';
                toggleCAction1RemarkField();
                setDepartmentMultiSelect(record);
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

            async function deleteRecord(recordId) {
                if (!confirm(
                        'Permanently delete this record and all its related data (label, options, CC history)?\nThis cannot be undone.'
                    )) return;
                try {
                    const response = await fetch(recordsUpdateBaseUrl + '/' + encodeURIComponent(recordId), {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        alert(data?.message || 'Unable to delete record.');
                        return;
                    }
                    holdIssueRows = holdIssueRows.filter(r => Number(r.id) !== Number(recordId));
                    renderRows();
                    loadHoldIssueHistoryRows();
                } catch (error) {
                    alert('Unable to delete record. Please try again.');
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

            issueInput.addEventListener('change', toggleRootCauseRemarkField);

            action1Input.addEventListener('change', toggleAction1RemarkField);

            cAction1Input.addEventListener('input', toggleCAction1RemarkField);
            cAction1Input.addEventListener('change', toggleCAction1RemarkField);

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
                if (getDepartmentPayload().length === 0) {
                    showAlert('Please select at least one Department.');
                    if (departmentInput) departmentInput.focus();
                    return;
                }

                try {
                    const payload = {
                        sku: sku,
                        order_number: orderNumberInput ? orderNumberInput.value.trim() : '',
                        qty: qtyInput.value === '' ? 0 : Number(qtyInput.value),
                        order_qty: orderQtyInput.value === '' ? null : Number(orderQtyInput.value),
                        parent: parentInput.value.trim(),
                        marketplace_1: marketplace1Input.value.trim(),
                        marketplace_2: marketplace2Input.value.trim(),
                        what_happened: whatHappenedInput.value.trim(),
                        issue: issue,
                        issue_remark: issueRemarkInput.value.trim(),
                        action_1: action1Input.value.trim(),
                        action_1_remark: action1RemarkInput.value.trim(),
                        replacement_tracking: replacementTrackingInput.value.trim(),
                        c_action_1: cAction1Input.value.trim(),
                        c_action_1_remark: cAction1RemarkInput.value.trim(),
                        department: getDepartmentPayload(),
                    };

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

                const optionsBtn = event.target.closest('.options-bulb-btn');
                if (optionsBtn) {
                    openOptionsModal(optionsBtn.getAttribute('data-id'));
                    return;
                }

                const ccHistBtn = event.target.closest('.cc-history-view-btn');
                if (ccHistBtn) {
                    openCcHistoryModal(ccHistBtn.getAttribute('data-id'));
                    return;
                }

                const createdHistBtn = event.target.closest('.created-history-btn');
                if (createdHistBtn) {
                    openCreatedHistoryModal(createdHistBtn.getAttribute('data-id'));
                    return;
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
                    return;
                }

                const labelBtn = event.target.closest('.label-dot-btn');
                if (labelBtn) {
                    openLabelModal(labelBtn.getAttribute('data-id'));
                    return;
                }

                const archiveBtn = event.target.closest('.hold-archive-btn');
                if (archiveBtn) {
                    archiveRecord(archiveBtn.getAttribute('data-id'));
                    return;
                }

                const deleteBtn = event.target.closest('.hold-delete-btn');
                if (deleteBtn) {
                    deleteRecord(deleteBtn.getAttribute('data-id'));
                }
            });

            const labelModalEl = document.getElementById('labelModal');
            let labelEditingId = null;

            function toggleLabelConditionals() {
                const q1 = document.getElementById('label_alternate_upgrade_done');
                const q2wrap = document.getElementById('label_q2_wrap');
                const q2 = document.getElementById('label_stock_adjustment_done');
                const q3 = document.getElementById('label_refunded');
                const q4wrap = document.getElementById('label_q4_wrap');
                const q4 = document.getElementById('label_label_voided');

                // Conditional visibility: Q2 only when Q1, Q4 only when Q3.
                if (q2wrap) q2wrap.classList.toggle('d-none', !q1.checked);
                if (!q1.checked && q2) q2.checked = false;
                if (q4wrap) q4wrap.classList.toggle('d-none', !q3.checked);
                if (!q3.checked && q4) q4.checked = false;

                // Only one set may be active: if Set A (Alternate/Upgrade) is on, lock Set B
                // (Refunded), and vice versa.
                const aActive = q1.checked;
                const bActive = q3.checked;
                q3.disabled = aActive;
                q4.disabled = aActive;
                q1.disabled = bActive;
                q2.disabled = bActive;
            }

            function openLabelModal(id) {
                const rec = holdIssueRows.find(r => Number(r.id) === Number(id));
                if (!rec) return;
                labelEditingId = Number(id);
                const l = rowLabel(rec);
                document.getElementById('label_issue_id').value = id;
                document.getElementById('label_alternate_upgrade_done').checked = l.alternate_upgrade_done;
                document.getElementById('label_stock_adjustment_done').checked = l.stock_adjustment_done;
                document.getElementById('label_refunded').checked = l.refunded;
                document.getElementById('label_label_voided').checked = l.label_voided;
                toggleLabelConditionals();
                document.getElementById('labelModalAlert').classList.add('d-none');
                window.bootstrap?.Modal?.getOrCreateInstance(labelModalEl)?.show();
            }

            document.getElementById('label_alternate_upgrade_done')?.addEventListener('change', function () {
                if (this.checked) {
                    document.getElementById('label_refunded').checked = false;
                    document.getElementById('label_label_voided').checked = false;
                }
                toggleLabelConditionals();
            });
            document.getElementById('label_refunded')?.addEventListener('change', function () {
                if (this.checked) {
                    document.getElementById('label_alternate_upgrade_done').checked = false;
                    document.getElementById('label_stock_adjustment_done').checked = false;
                }
                toggleLabelConditionals();
            });

            function refreshLabelDot(id) {
                const rec = holdIssueRows.find(r => Number(r.id) === Number(id));
                if (!rec) return;
                const btn = tableBody.querySelector('.label-dot-btn[data-id="' + String(id) + '"]');
                const td = btn ? btn.closest('.label-td') : null;
                if (td) td.innerHTML = labelDotHtml(rec);
            }

            document.getElementById('labelSaveBtn')?.addEventListener('click', async () => {
                if (labelEditingId === null) return;
                const payload = {
                    alternate_upgrade_done: document.getElementById('label_alternate_upgrade_done').checked,
                    stock_adjustment_done: document.getElementById('label_stock_adjustment_done').checked,
                    refunded: document.getElementById('label_refunded').checked,
                    label_voided: document.getElementById('label_label_voided').checked,
                };
                const alertEl = document.getElementById('labelModalAlert');
                alertEl.classList.add('d-none');
                try {
                    const res = await fetch(recordsUpdateBaseUrl + '/' + encodeURIComponent(labelEditingId) +
                        '/label', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify(payload),
                        });
                    const data = await res.json();
                    if (!res.ok) {
                        alertEl.textContent = data?.message || 'Unable to save label.';
                        alertEl.classList.remove('d-none');
                        return;
                    }
                    const rec = holdIssueRows.find(r => Number(r.id) === Number(labelEditingId));
                    if (rec) rec.label = data.label || payload;
                    refreshLabelDot(labelEditingId);
                    window.bootstrap?.Modal?.getInstance(labelModalEl)?.hide();
                } catch (e) {
                    alertEl.textContent = 'Unable to save label. Please try again.';
                    alertEl.classList.remove('d-none');
                }
            });

            const createdHistoryModalEl = document.getElementById('createdHistoryModal');

            function openCreatedHistoryModal(id) {
                const rec = holdIssueRows.find(r => Number(r.id) === Number(id));
                document.getElementById('created_history_sku').textContent = rec ? (rec.sku || '') : '';
                const events = holdIssueHistoryRows
                    .filter(h => Number(h.orders_on_hold_issue_id) === Number(id));
                const tbody = document.getElementById('created_history_tbody');
                tbody.innerHTML = events.length ? events.map((h, i) => {
                    return '<tr><td>' + (i + 1) + '</td><td>' + escapeHtml(h.event_type || '') + '</td><td>' +
                        escapeHtml(h.created_by || '') + '</td><td>' + escapeHtml(h.logged_at_full || h.logged_at ||
                            '') + '</td></tr>';
                }).join('') : '<tr><td colspan="4" class="text-center text-muted">No history.</td></tr>';
                window.bootstrap?.Modal?.getOrCreateInstance(createdHistoryModalEl)?.show();
            }

            const ccHistoryModalEl = document.getElementById('ccHistoryModal');

            function openCcHistoryModal(id) {
                const rec = holdIssueRows.find(r => Number(r.id) === Number(id));
                if (!rec) return;
                document.getElementById('cc_history_sku').textContent = rec.sku || '';
                const hist = Array.isArray(rec.cc_action_history) ? rec.cc_action_history.slice().reverse() : [];
                const tbody = document.getElementById('cc_history_tbody');
                tbody.innerHTML = hist.length ? hist.map((h, i) => {
                    return '<tr><td>' + (hist.length - i) + '</td><td><span class="badge ' + ccActionBgClass(h
                            ?.value) + '">' + escapeHtml(h?.value ?? '') + '</span></td><td>' + escapeHtml(h?.by ??
                            '') +
                        '</td><td>' + escapeHtml(h?.at ?? '') + '</td></tr>';
                }).join('') : '<tr><td colspan="4" class="text-center text-muted">No history.</td></tr>';
                window.bootstrap?.Modal?.getOrCreateInstance(ccHistoryModalEl)?.show();
            }

            const optionsModalEl = document.getElementById('optionsModal');

            let optionsHoverPreviewEl = null;

            function ensureHoverPreview() {
                if (!optionsHoverPreviewEl) {
                    optionsHoverPreviewEl = document.createElement('img');
                    optionsHoverPreviewEl.id = 'optionsHoverPreview';
                    document.body.appendChild(optionsHoverPreviewEl);
                }
                return optionsHoverPreviewEl;
            }

            function hideHoverPreview() {
                if (optionsHoverPreviewEl) optionsHoverPreviewEl.style.display = 'none';
            }

            function moveHoverPreview(e) {
                if (!optionsHoverPreviewEl || optionsHoverPreviewEl.style.display === 'none') return;
                const pad = 18;
                const w = optionsHoverPreviewEl.offsetWidth || 340;
                const h = optionsHoverPreviewEl.offsetHeight || 340;
                let x = e.clientX + pad;
                let y = e.clientY + pad;
                if (x + w > window.innerWidth) x = e.clientX - w - pad;
                if (y + h > window.innerHeight) y = e.clientY - h - pad;
                optionsHoverPreviewEl.style.left = Math.max(4, x) + 'px';
                optionsHoverPreviewEl.style.top = Math.max(4, y) + 'px';
            }

            function attachImageHoverPreview(container) {
                if (!container) return;
                container.addEventListener('mouseover', (e) => {
                    const img = e.target.closest('img.option-zoom');
                    if (!img) return;
                    const src = img.getAttribute('src');
                    if (!src) return;
                    const p = ensureHoverPreview();
                    p.setAttribute('src', src);
                    p.style.display = 'block';
                });
                container.addEventListener('mousemove', moveHoverPreview);
                container.addEventListener('mouseout', (e) => {
                    if (e.target.closest('img.option-zoom')) hideHoverPreview();
                });
            }

            attachImageHoverPreview(optionsModalEl);
            attachImageHoverPreview(tableBody);
            optionsModalEl?.addEventListener('hidden.bs.modal', hideHoverPreview);

            let optionsEditingId = null;
            let optionsCanEdit = false;
            let optionsVariants = [];
            let optionsUpgrades = [];
            let optionsSkuTimer = null;

            function optionsInvDisplay(inv) {
                if (inv === null || inv === undefined || inv === '') return '—';
                const n = Number(inv);
                return Number.isFinite(n) ? String(n) : '—';
            }

            function optionItemRowHtml(item, type, idx) {
                const url = String(item?.image_url || '').trim();
                const img = url ?
                    '<img src="' + escAttr(url) + '" alt="" class="sku-thumb option-zoom">' :
                    '<span class="text-muted">—</span>';
                const remove = optionsCanEdit ?
                    '<button type="button" class="btn btn-sm btn-link text-danger p-0 options-remove-btn" data-type="' +
                    type + '" data-idx="' + idx + '" title="Remove"><i class="bi bi-x-circle-fill"></i></button>' : '';
                return '<tr><td>' + img + '</td><td>' + escapeHtml(item.sku) + shopifySkuLinkHtml(item.sku) +
                    '</td><td>' + optionsInvDisplay(item.inv) + '</td><td class="text-center">' + remove + '</td></tr>';
            }

            function renderOptionItems() {
                const vt = document.getElementById('options_variant_tbody');
                const ut = document.getElementById('options_upgrade_tbody');
                if (vt) vt.innerHTML = optionsVariants.length ? optionsVariants.map((it, i) => optionItemRowHtml(it,
                    'variant', i)).join('') : '<tr><td colspan="4" class="text-center text-muted">None added.</td></tr>';
                if (ut) ut.innerHTML = optionsUpgrades.length ? optionsUpgrades.map((it, i) => optionItemRowHtml(it,
                    'upgrade', i)).join('') : '<tr><td colspan="4" class="text-center text-muted">None added.</td></tr>';
            }

            function applyOptionsEditPermission() {
                const noOptCb = document.getElementById('options_no_options');
                const noOpt = !!(noOptCb && noOptCb.checked);
                ['variant_add_row', 'upgrade_add_row'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.classList.toggle('d-none', !optionsCanEdit || noOpt);
                });
                const saveBtn = document.getElementById('optionsSaveBtn');
                if (saveBtn) saveBtn.classList.toggle('d-none', !optionsCanEdit);
                const note = document.getElementById('optionsReadonlyNote');
                if (note) note.classList.toggle('d-none', optionsCanEdit);
                if (noOptCb) noOptCb.disabled = !optionsCanEdit;
            }

            document.getElementById('options_no_options')?.addEventListener('change', function () {
                if (this.checked) {
                    optionsVariants = [];
                    optionsUpgrades = [];
                    renderOptionItems();
                }
                applyOptionsEditPermission();
            });

            async function openOptionsModal(id) {
                const rec = holdIssueRows.find(r => Number(r.id) === Number(id));
                if (!rec) return;
                optionsEditingId = Number(id);
                optionsVariants = [];
                optionsUpgrades = [];
                optionsCanEdit = false;
                document.getElementById('options_issue_id').value = id;
                document.getElementById('options_sku_display').value = rec.sku || '';
                const optImgWrap = document.getElementById('options_sku_image_wrap');
                const optImg = document.getElementById('options_sku_image');
                const optUrl = String(rec.image_url || '').trim();
                if (optImg && optImgWrap) {
                    if (optUrl) {
                        optImg.setAttribute('src', optUrl);
                        optImgWrap.classList.remove('d-none');
                    } else {
                        optImg.setAttribute('src', '');
                        optImgWrap.classList.add('d-none');
                    }
                }
                document.getElementById('optionsModalAlert').classList.add('d-none');
                document.getElementById('options_variant_input').value = '';
                document.getElementById('options_upgrade_input').value = '';
                document.getElementById('options_no_options').checked = false;
                applyOptionsEditPermission();
                renderOptionItems();
                window.bootstrap?.Modal?.getOrCreateInstance(optionsModalEl)?.show();
                try {
                    const res = await fetch(recordsUpdateBaseUrl + '/' + encodeURIComponent(id) + '/options', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await res.json();
                    optionsCanEdit = !!data.can_edit;
                    optionsVariants = Array.isArray(data.variants) ? data.variants : [];
                    optionsUpgrades = Array.isArray(data.upgrades) ? data.upgrades : [];
                    document.getElementById('options_no_options').checked = !!data.no_options;
                    applyOptionsEditPermission();
                    renderOptionItems();
                } catch (e) {}
            }

            function refreshOptionsBulb(id) {
                const rec = holdIssueRows.find(r => Number(r.id) === Number(id));
                if (!rec) return;
                const btn = tableBody.querySelector('.options-bulb-btn[data-id="' + String(id) + '"]');
                const td = btn ? btn.closest('td') : null;
                if (td) td.innerHTML = optionsBulbHtml(rec);
            }

            async function refreshOptionsSkuSuggestions(q) {
                const dl = document.getElementById('options_sku_datalist');
                if (!dl) return;
                const term = String(q || '').trim();
                if (term.length < 1) {
                    dl.innerHTML = '';
                    return;
                }
                try {
                    const res = await fetch(skuSearchUrl + '?q=' + encodeURIComponent(term), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await res.json();
                    const list = Array.isArray(data?.skus) ? data.skus : [];
                    dl.innerHTML = list.map(it => '<option value="' + escAttr(it?.sku ?? '') + '"></option>').join('');
                } catch (e) {
                    dl.innerHTML = '';
                }
            }

            async function addOptionSku(type) {
                if (!optionsCanEdit) return;
                const input = document.getElementById(type === 'variant' ? 'options_variant_input' :
                    'options_upgrade_input');
                const sku = (input?.value || '').trim();
                if (!sku) return;
                const list = type === 'variant' ? optionsVariants : optionsUpgrades;
                if (list.some(x => String(x.sku).toLowerCase() === sku.toLowerCase())) {
                    input.value = '';
                    return;
                }
                let item = {
                    sku: sku,
                    inv: null,
                    image_url: null
                };
                try {
                    const res = await fetch(skuDetailsUrl + '?sku=' + encodeURIComponent(sku), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await res.json();
                    if (data && data.found) {
                        item = {
                            sku: data.sku || sku,
                            inv: data.qty ?? null,
                            image_url: data.image_url ?? null
                        };
                    }
                } catch (e) {}
                list.push(item);
                input.value = '';
                renderOptionItems();
            }

            document.getElementById('options_variant_input')?.addEventListener('input', () => {
                clearTimeout(optionsSkuTimer);
                optionsSkuTimer = setTimeout(() => refreshOptionsSkuSuggestions(document.getElementById(
                    'options_variant_input').value), 220);
            });
            document.getElementById('options_upgrade_input')?.addEventListener('input', () => {
                clearTimeout(optionsSkuTimer);
                optionsSkuTimer = setTimeout(() => refreshOptionsSkuSuggestions(document.getElementById(
                    'options_upgrade_input').value), 220);
            });
            document.getElementById('options_variant_add_btn')?.addEventListener('click', () => addOptionSku(
                'variant'));
            document.getElementById('options_upgrade_add_btn')?.addEventListener('click', () => addOptionSku(
                'upgrade'));
            ['options_variant_input', 'options_upgrade_input'].forEach(id => {
                document.getElementById(id)?.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        addOptionSku(id === 'options_variant_input' ? 'variant' : 'upgrade');
                    }
                });
            });
            optionsModalEl?.addEventListener('click', (event) => {
                const rm = event.target.closest('.options-remove-btn');
                if (!rm || !optionsCanEdit) return;
                const type = rm.getAttribute('data-type');
                const idx = parseInt(rm.getAttribute('data-idx'), 10);
                const list = type === 'variant' ? optionsVariants : optionsUpgrades;
                if (idx >= 0 && idx < list.length) {
                    list.splice(idx, 1);
                    renderOptionItems();
                }
            });

            document.getElementById('optionsSaveBtn')?.addEventListener('click', async () => {
                if (optionsEditingId === null || !optionsCanEdit) return;
                const payload = {
                    variant_skus: optionsVariants.map(x => x.sku),
                    upgrade_skus: optionsUpgrades.map(x => x.sku),
                    no_options: document.getElementById('options_no_options').checked,
                };
                const alertEl = document.getElementById('optionsModalAlert');
                alertEl.classList.add('d-none');
                try {
                    const res = await fetch(recordsUpdateBaseUrl + '/' + encodeURIComponent(optionsEditingId) +
                        '/options', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify(payload),
                        });
                    const data = await res.json();
                    if (!res.ok) {
                        alertEl.textContent = data?.message || 'Unable to save options.';
                        alertEl.classList.remove('d-none');
                        return;
                    }
                    const rec = holdIssueRows.find(r => Number(r.id) === Number(optionsEditingId));
                    if (rec) rec.options = data.options || {
                        variant_skus: payload.variant_skus,
                        upgrade_skus: payload.upgrade_skus
                    };
                    refreshOptionsBulb(optionsEditingId);
                    window.bootstrap?.Modal?.getInstance(optionsModalEl)?.hide();
                } catch (e) {
                    alertEl.textContent = 'Unable to save options. Please try again.';
                    alertEl.classList.remove('d-none');
                }
            });

            function applyCcActionBg(sel, value) {
                sel.classList.remove('cc-action-bg-pending', 'cc-action-bg-offer', 'cc-action-bg-refunded',
                    'cc-action-bg-sent');
                sel.classList.add(ccActionBgClass(value));
            }

            tableBody.addEventListener('change', async (event) => {
                const sel = event.target.closest('.cc-action-select');
                if (!sel) return;

                const id = sel.getAttribute('data-id');
                const newVal = sel.value;
                const rec = holdIssueRows.find(r => Number(r.id) === Number(id));
                applyCcActionBg(sel, newVal);

                try {
                    const res = await fetch(recordsUpdateBaseUrl + '/' + encodeURIComponent(id) + '/cc-action', {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            cc_action: newVal
                        }),
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        if (rec) {
                            sel.value = ccActionValue(rec);
                            applyCcActionBg(sel, ccActionValue(rec));
                        }
                        return;
                    }
                    if (rec) {
                        rec.action_1 = data.cc_action ?? (newVal === 'Pending' ? '' : newVal);
                        if (Array.isArray(data.cc_action_history)) {
                            rec.cc_action_history = data.cc_action_history;
                        }
                        const tr = sel.closest('tr');
                        const histTd = tr ? tr.querySelector('.cc-history-td') : null;
                        if (histTd) histTd.innerHTML = ccHistoryHtml(rec);
                    }
                } catch (e) {
                    if (rec) {
                        sel.value = ccActionValue(rec);
                        applyCcActionBg(sel, ccActionValue(rec));
                    }
                }
            });

            deptFilterSelect?.addEventListener('change', (e) => {
                activeDeptFilter = e.target.value || null;
                renderRows();
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

                const activeHeaders = ['#', 'SKU', 'Ord', 'QTY', 'Order QTY', 'Parent', 'MKT1', 'MKT2',
                    'Issue?', 'CC Action', 'CC History', 'Action Remark', 'Replacement Tracking',
                    'Root Cause Found', 'Root Cause Remark', 'Root Cause Fixed',
                    'Root Cause Fixed Remark', 'Department', 'Created By', 'Created At'];
                const activeData = getFilteredRows().map(r => [
                    r.id, r.sku, r.order_number, r.qty, r.order_qty, r.parent,
                    r.marketplace_1, r.marketplace_2, r.what_happened,
                    ccActionValue(r),
                    (Array.isArray(r.cc_action_history) ? r.cc_action_history : []).map(h => (h.value || '') +
                        ' (' + (h.by || '') + ' ' + (h.at || '') + ')').join(' | '),
                    r.action_1_remark, r.replacement_tracking,
                    r.issue, r.issue_remark, r.c_action_1, r.c_action_1_remark,
                    rowDepartments(r).join(', '), r.created_by, r.created_at
                ]);

                const dateStr = new Date().toISOString().slice(0, 10);
                downloadCsv(buildCsv(activeHeaders, activeData), `orders_on_hold_active_${dateStr}.csv`);
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
                const headers = ['sku','qty','order_qty','parent','marketplace_1','marketplace_2','what_happened','action_1','action_1_remark','replacement_tracking','issue','issue_remark','c_action_1','c_action_1_remark'];
                const sample  = ['SAMPLE-SKU-001','5','2','PARENT-001','Amazon','eBay','Damaged','Cancelled','','TRK123','Quality Issue','','Fixed',''];
                const csv = [headers.join(','), sample.join(',')].join('\r\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a'); a.href = url;
                a.download = 'import_sample.csv';
                document.body.appendChild(a); a.click();
                document.body.removeChild(a); URL.revokeObjectURL(url);
            });

            document.getElementById('importCsvSubmitBtn').addEventListener('click', async () => {
                const fileInput  = document.getElementById('importCsvFile');
                const alertEl    = document.getElementById('importCsvAlert');
                const progressEl = document.getElementById('importCsvProgress');
                const errorsEl   = document.getElementById('importCsvErrors');
                const errList    = document.getElementById('importCsvErrorList');

                if (!fileInput.files.length) {
                    alertEl.className = 'alert alert-warning mb-3';
                    alertEl.textContent = 'Please select a CSV file.';
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
                    const res  = await fetch('{{ route("customer.care.orders.on.hold.issues.import") }}', { method: 'POST', body: formData });
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

            modalEl.addEventListener('hidden.bs.modal', resetForm);
            toggleRootCauseRemarkField();
            toggleAction1RemarkField();
            toggleCAction1RemarkField();
            renderRows();
            renderHistoryRows();
            loadHoldIssueRows();
            loadHoldIssueHistoryRows();
        })();
    </script>
@endsection
