@extends('layouts.vertical', ['title' => 'Label Issues', 'sidenav' => 'condensed'])
{{-- Label Issues (Tabulator) — same records and actions as the previous HTML board. --}}

@php
    $importCsvHeaders = [
        'sku', 'order_number', 'qty', 'order_qty', 'parent', 'marketplace_1',
        'what_happened', 'action_1', 'action_1_remark', 'replacement_tracking',
        'issue', 'issue_remark', 'c_action_1', 'c_action_1_remark', 'department',
    ];
    $importCsvSampleRow = [
        'SAMPLE-SKU-001', '112-1234567-8901234', '5', '2', 'PARENT-001', 'Amz',
        'Damaged', 'Cancelled', '', 'TRK123', 'Quality Issue', '', 'Fixed', '', 'Label',
    ];
@endphp

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter { display: none !important; }
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
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title { padding-right: 0 !important; }
        .tabulator-paginator label { margin-right: 5px; }
        .sku-thumb, .sku-thumb-placeholder, .sku-image-preview {
            width: 36px; height: 36px; object-fit: contain; border-radius: 3px;
            background: #f8f9fa; border: 1px solid #dee2e6;
        }
        .sku-thumb-placeholder {
            display: inline-flex; align-items: center; justify-content: center; color: #adb5bd;
        }
        .sku-image-preview { width: 52px; height: 52px; }
        .issue-loss-input {
            width: 100%; max-width: 5.5rem; font-size: 0.8125rem;
            padding: 0.15rem 0.3rem; text-align: right;
        }
        .what-happened-dot {
            width: 10px; height: 10px; border-radius: 50%; display: inline-block;
            background-color: #dc3545; vertical-align: middle;
        }
        .what-happened-dot-damaged { background-color: #b8860b; }
        .status-dot-indicator { font-size: 14px; line-height: 1; cursor: help; }
        .status-dot-missing { color: #dc3545; }
        .status-dot-available { color: #198754; }
        .copy-tracking-btn, .copy-order-btn {
            color: #0d6efd; font-size: 0.8rem; line-height: 1; padding: 0 2px;
            border: none; background: none; cursor: pointer;
        }
        .copy-tracking-btn { display: none; }
        .tracking-cell { white-space: nowrap; }
        .tracking-dot { font-size: 1.1em; color: #6c757d; }
        .tracking-full { display: none; background: #fff; padding: 0 4px; }
        .tabulator-cell:hover .tracking-dot { display: none; }
        .tabulator-cell:hover .tracking-full,
        .tabulator-cell:hover .copy-tracking-btn { display: inline; }
        .copy-order-btn.copied, .copy-tracking-btn.copied { color: #198754; }
        .li-row-btn { border: none; background: transparent; padding: 1px 5px; cursor: pointer; color: #2563eb; }
        .li-row-btn.li-danger { color: #dc2626; }
        #label-tabulator .tabulator-cell:hover:has(.tracking-cell) { overflow: visible; z-index: 30; }
        #li-issue-modal .modal-dialog { max-height: calc(100vh - 2rem); margin: 1rem auto; }
        #li-issue-modal .modal-content, #li-issue-modal #li-issue-form {
            display: flex; flex-direction: column; max-height: calc(100vh - 2rem);
            min-height: 0; overflow: hidden;
        }
        #li-issue-modal .modal-header, #li-issue-modal .modal-footer { flex-shrink: 0; }
        #li-issue-modal .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Label Issues',
        'sub_title' => 'Customer Care',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-0">Use Add Label Issue to record SKU issues. SKU lookup auto-fills Parent and available QTY.</p>
                </div>
            </div>

            <div class="card mt-3 shadow-sm">
                <div class="card-header py-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h5 class="mb-0 me-2">Label Issues Records</h5>
                        <button type="button" class="btn btn-primary btn-sm" id="li-add">
                            <i class="bi bi-plus-lg me-1"></i> Add Label Issue
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="li-history">
                            <i class="bi bi-clock-history me-1"></i> History
                        </button>
                        <button type="button" class="btn btn-success btn-sm" id="li-export">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" id="li-import">
                            <i class="bi bi-upload me-1"></i> Import CSV
                        </button>
                        <div class="dropdown">
                            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                                <i class="fa-solid fa-table-columns"></i> Columns
                            </button>
                            <div class="dropdown-menu p-2" id="li-columns-menu" style="max-height:60vh;overflow-y:auto;min-width:220px;"></div>
                        </div>
                        <div class="input-group input-group-sm ms-xl-auto" style="max-width:320px;">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="search" id="li-search" class="form-control" placeholder="Search SKU, order, tracking, carrier…" autocomplete="off">
                        </div>
                        <span class="badge bg-light text-dark" id="li-count">0</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="label-table-wrapper" style="height: calc(100vh - 280px); display: flex; flex-direction: column;">
                        <div id="label-tabulator" style="flex:1;"></div>
                    </div>
                </div>
            </div>

            <div class="card mt-3 d-none" id="li-history-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Order History</h5>
                    <span class="badge bg-light text-dark" id="li-history-count">0</span>
                </div>
                <div class="card-body p-0">
                    <div style="height: 420px;">
                        <div id="label-history-tabulator" style="height:100%;"></div>
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
                        <code>sku, order_number (or order id / order_id), qty, order_qty, parent, marketplace_1, what_happened, action_1, action_1_remark, replacement_tracking, issue, issue_remark, c_action_1, c_action_1_remark, department</code>
                    </p>
                    <p class="text-muted small mb-3">
                        Required: <strong>sku</strong>, <strong>qty</strong>, <strong>issue</strong> (Root Cause Found),
                        <strong>department</strong>. Use multiple departments separated by <strong>|</strong> or
                        <strong>,</strong> (e.g. <code>Label|QC</code>). Other columns are optional.
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
                    <a href="#" id="importCsvSampleLink" class="btn btn-sm btn-outline-secondary me-auto">
                        <i class="bi bi-download me-1"></i> Download Sample
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-info" id="importCsvSubmitBtn"><i class="bi bi-upload me-1"></i> Import</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="li-issue-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="li-issue-form" autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title" id="li-issue-modal-title">Label Issue</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="li-alert" class="alert alert-danger d-none mb-3" role="alert"></div>
                        <input type="hidden" id="li-id">
                        <input type="hidden" id="li-qty" value="0">
                        <input type="hidden" id="li-parent">
                        <input type="hidden" id="li-product-master-id">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="li-sku">SKU <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="li-sku" list="li-sku-datalist" placeholder="Search SKU" required autocomplete="off">
                                <datalist id="li-sku-datalist"></datalist>
                                <div class="mt-1 d-none" id="li-sku-image-wrap">
                                    <img src="" alt="SKU Image" id="li-sku-image" class="sku-image-preview">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="li-order-qty">QTY</label>
                                <input type="number" class="form-control" id="li-order-qty" min="0" step="1" placeholder="Qty">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="li-order-number">Order ID</label>
                                <input type="text" class="form-control" id="li-order-number" placeholder="Enter order ID">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="li-marketplace">MKT</label>
                                <input type="text" class="form-control" id="li-marketplace" list="li-marketplace-datalist" placeholder="Select Marketplace">
                                <datalist id="li-marketplace-datalist">
                                    @foreach ($marketplaces ?? collect() as $marketplace)
                                        <option value="{{ $marketplace }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="li-what">Issue?</label>
                                <input type="text" class="form-control" id="li-what" maxlength="100" placeholder="e.g. 0 Stock, Damaged">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="li-action">Action</label>
                                <input type="text" class="form-control" id="li-action" list="li-action-datalist" placeholder="Type or select action..." autocomplete="off">
                                <datalist id="li-action-datalist">
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
                            <div class="col-md-6" id="li-action-remark-wrap">
                                <label class="form-label" for="li-action-remark">Action Remark <span id="li-action-remark-star" class="text-danger d-none">*</span></label>
                                <input type="text" class="form-control" id="li-action-remark" placeholder="Write action remark...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="li-track-r">Replacement Tracking Number</label>
                                <input type="text" class="form-control" id="li-track-r" maxlength="50" placeholder="Optional tracking number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="li-ctn">CTN Pkg</label>
                                <input type="text" class="form-control" id="li-ctn" maxlength="100" placeholder="CTN packaging instructions (max 100)" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="li-item-pkg">Instruction PKG</label>
                                <textarea class="form-control" id="li-item-pkg" rows="2" maxlength="2000" placeholder="Item packaging instructions (max 2000)" autocomplete="off"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="li-qe-issue">QC Enhance — Issue</label>
                                <input type="text" class="form-control" id="li-qe-issue" maxlength="2000" placeholder="Issue (from Quality Enhance)" autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="li-qe-action">QC Enhance — Action Req</label>
                                <input type="text" class="form-control" id="li-qe-action" maxlength="2000" placeholder="Action required" autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="li-qe-remark">QC Enhance — Status Remark</label>
                                <input type="text" class="form-control" id="li-qe-remark" maxlength="2000" placeholder="Status / remark" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="li-root">Root Cause Found <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="li-root" list="li-root-found-datalist" placeholder="Type or select root cause..." required autocomplete="off">
                                <datalist id="li-root-found-datalist"></datalist>
                            </div>
                            <div class="col-12 d-none" id="li-root-remark-wrap">
                                <label class="form-label" for="li-root-remark">Root Cause Remark <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="li-root-remark" placeholder="Write remark for Other">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="li-root-fixed">Root Cause Fixed</label>
                                <input type="text" class="form-control" id="li-root-fixed" list="li-root-fixed-datalist" placeholder="Type or select fix..." autocomplete="off">
                                <datalist id="li-root-fixed-datalist"></datalist>
                            </div>
                            <div class="col-12 d-none" id="li-root-fixed-remark-wrap">
                                <label class="form-label" for="li-root-fixed-remark">Root Cause Fixed Remark <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="li-root-fixed-remark" placeholder="Write remark for Other">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department <span class="text-danger">*</span></label>
                                <div class="dropdown" id="li-dept-ui">
                                    <button class="form-select text-start" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                                        <span id="li-dept-label" class="text-truncate text-muted">Select department(s)</span>
                                    </button>
                                    <div class="dropdown-menu p-2 w-100" id="li-dept-menu" style="max-height:240px;overflow:auto;"></div>
                                </div>
                                <div class="form-text">Click to select one or more departments.</div>
                            </div>
                            <div class="col-12 d-none" id="li-dept-other-wrap">
                                <label class="form-label" for="li-dept-other">Other — Responsible Dept Notes <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="li-dept-other" rows="2" maxlength="255" placeholder="Describe the special case / responsible dept..."></textarea>
                                <div class="form-text text-end small"><span id="li-dept-other-count">0</span> / 255</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="li-save">Save</button>
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
                list: @json(route('customer.care.label.issues.list.index')),
                store: @json(route('customer.care.label.issues.list.store')),
                updateBase: @json(url('/customer-care/label-issues/issues')),
                skuDetails: @json(route('customer.care.label.issues.sku.details')),
                skuSearch: @json(route('customer.care.followups.skus')),
                history: @json(route('customer.care.label.issues.history.index')),
                dropdownList: @json(route('customer.care.label.issues.dropdown.options.index')),
                import: @json(route('customer.care.label.issues.import')),
                colVisGet: @json(route('tabulator.column.visibility.user.get')),
                colVisSet: @json(route('tabulator.column.visibility.user.set')),
            };
            const COLVIS_CHANNEL = 'label_issues';
            const IMPORT_HEADERS = @json($importCsvHeaders);
            const IMPORT_SAMPLE = @json($importCsvSampleRow);
            const DEPARTMENTS = [
                'Dispatch', 'Shipping', 'Listing', 'Label', 'Carrier', 'Carrier Issue',
                'Customer Care', 'Pricing', 'QC', 'Packaging', 'Chargeback', 'Orders on Hold', 'Other',
            ];
            const DEPT_LABELS = { 'Carrier': 'Carrier Claims', 'Carrier Issue': 'Carrier Scan Issue' };
            const jsonHeaders = {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF,
            };

            let table = null;
            let historyTable = null;
            let modal = null;
            let skuTimer = null;

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
            function statusIcon(has, tip) {
                const cls = has ? 'status-dot-available' : 'status-dot-missing';
                const t = String(tip ?? '').trim() || (has ? 'Has data' : 'No data');
                return '<i class="bi bi-search status-dot-indicator ' + cls + '" title="' + escAttr(t) + '"></i>';
            }
            function deptLabel(row) {
                if (Array.isArray(row.departments) && row.departments.length) return row.departments.join(', ');
                return String(row.department || '').trim();
            }

            const fmtImage = function (cell) {
                const url = cell.getValue();
                return url
                    ? '<img src="' + escAttr(url) + '" class="sku-thumb" alt="">'
                    : '<span class="sku-thumb-placeholder"><i class="bi bi-image"></i></span>';
            };
            const fmtOrderNum = function (cell) {
                const v = String(cell.getValue() || '').trim();
                if (!v) return '—';
                return '<button type="button" class="copy-order-btn" data-copy="' + escAttr(v) +
                    '" title="' + escAttr(v) + '"><i class="bi bi-clipboard"></i></button>';
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
                return '<span class="tracking-cell"><span class="tracking-dot">•</span>' +
                    '<span class="tracking-full">' + escapeHtml(t) + '</span>' +
                    '<button type="button" class="copy-tracking-btn" data-copy="' + escAttr(t) +
                    '" title="Copy tracking"><i class="bi bi-clipboard"></i></button></span>';
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
                const pid = d.product_master_id;
                const instr = String(d.ctn_instructions || '').trim();
                const tip = !pid ? 'No matching product_master row' : (instr || 'No instructions');
                return statusIcon(!!(pid && instr), tip);
            };
            const fmtItemPkg = function (cell) {
                const raw = String(cell.getData().instructions_item_pkg || '').trim();
                return statusIcon(raw !== '', raw || 'No instructions available');
            };
            const fmtQcEnhance = function (cell) {
                const d = cell.getData();
                const parts = [];
                const issue = String(d.qc_enhance_issue || '').trim();
                const action = String(d.qc_enhance_action_req || '').trim();
                const remark = String(d.qc_enhance_status_remark || '').trim();
                if (issue) parts.push('Issue: ' + issue);
                if (action) parts.push('Action Req: ' + action);
                if (remark) parts.push('Status Remark: ' + remark);
                const tip = parts.join('\n');
                return statusIcon(tip !== '', tip || 'No QC Enhance data');
            };
            function formatShortDate(raw) {
                const text = String(raw ?? '').trim();
                if (!text) return '';
                const months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
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
                if (day === null || monthIdx === null || monthIdx < 0 || monthIdx > 11) return escapeHtml(text);
                const dt = new Date(year, monthIdx, day, hour, minute, 0);
                const stale = !Number.isNaN(dt.getTime()) && (Date.now() - dt.getTime()) > 14 * 24 * 60 * 60 * 1000;
                const label = day + ' ' + months[monthIdx];
                return stale
                    ? '<span class="text-danger" title="' + escAttr(text) + '">' + escapeHtml(label) + '</span>'
                    : '<span title="' + escAttr(text) + '">' + escapeHtml(label) + '</span>';
            }
            const fmtCreatedAt = function (cell) { return formatShortDate(cell.getData().created_at); };
            const fmtLoggedAt = function (cell) { return formatShortDate(cell.getData().logged_at); };
            const fmtLoss = function (cell) {
                const d = cell.getData();
                const n = d.total_loss;
                const v = (n != null && n !== '' && !isNaN(parseFloat(n))) ? String(Math.round(parseFloat(n))) : '';
                return '<input type="number" step="0.01" class="form-control form-control-sm issue-loss-input" ' +
                    'value="' + escAttr(v) + '" data-original="' + escAttr(v) + '" data-issue-id="' + escAttr(String(d.id)) +
                    '" inputmode="decimal" autocomplete="off" aria-label="Loss $">';
            };
            const fmtActions = function () {
                let html = '<button type="button" class="li-row-btn li-edit" title="Edit"><i class="bi bi-pencil-fill"></i></button>';
                if (CAN_ARCHIVE) {
                    html += '<button type="button" class="li-row-btn li-danger li-archive" title="Archive"><i class="bi bi-archive-fill"></i></button>';
                }
                return html;
            };
            const fmtDept = function (cell) {
                const label = deptLabel(cell.getData());
                return label ? escapeHtml(label) : '—';
            };

            function dataColumns(includeActions) {
                const cols = [
                    includeActions
                        ? { title: '#', field: 'id', width: 55 }
                        : { title: '#', field: 'issue_ref', width: 70, formatter: function (c) { return dash(c.getValue() || c.getData().orders_on_hold_issue_id || c.getData().id); } },
                    { title: '', field: 'image_url', width: 56, formatter: fmtImage, headerSort: false },
                    { title: 'SKU', field: 'sku', width: 140, formatter: function (c) { return '<span title="' + escAttr(c.getValue()) + '">' + escapeHtml(c.getValue()) + '</span>'; } },
                    { title: 'Order ID', field: 'order_number', width: 70, formatter: fmtOrderNum, hozAlign: 'center' },
                ];
                if (includeActions) {
                    cols.push({ title: 'Loss $', field: 'total_loss', width: 90, formatter: fmtLoss, headerSort: false, tooltip: false });
                }
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
                    { title: 'Dept', field: 'department', width: 90, formatter: fmtDept },
                );
                if (includeActions) {
                    cols.push({ title: 'Close', field: '_actions', width: 70, formatter: fmtActions, headerSort: false, hozAlign: 'center' });
                    cols.push({ title: 'Created By', field: 'created_by', width: 110, formatter: function (c) { return dash(c.getValue()); } });
                    cols.push({ title: 'Created At', field: 'created_at', width: 80, formatter: fmtCreatedAt });
                } else {
                    cols.push({ title: 'Close', field: 'close_note', width: 90, formatter: function (c) { return dash(c.getValue()); } });
                    cols.push({ title: 'Event', field: 'event_type', width: 90, formatter: function (c) { return dash(c.getValue()); } });
                    cols.push({ title: 'Created By', field: 'created_by', width: 110, formatter: function (c) { return dash(c.getValue()); } });
                    cols.push({ title: 'Logged At', field: 'logged_at', width: 80, formatter: fmtLoggedAt });
                }
                return cols;
            }

            function showAlert(message, kind) {
                const box = document.getElementById('li-alert');
                box.className = 'alert mb-3 alert-' + (kind || 'danger');
                box.textContent = message;
            }
            function hideAlert() {
                const box = document.getElementById('li-alert');
                box.className = 'alert alert-danger d-none mb-3';
                box.textContent = '';
            }

            function selectedDepartments() {
                return Array.from(document.querySelectorAll('#li-dept-menu input:checked')).map(function (el) { return el.value; });
            }
            function refreshDeptLabel() {
                const selected = selectedDepartments();
                const label = document.getElementById('li-dept-label');
                if (!selected.length) {
                    label.textContent = 'Select department(s)';
                    label.classList.add('text-muted');
                } else {
                    label.textContent = selected.map(function (v) { return DEPT_LABELS[v] || v; }).join(', ');
                    label.classList.remove('text-muted');
                }
                const isOther = selected.indexOf('Other') !== -1;
                document.getElementById('li-dept-other-wrap').classList.toggle('d-none', !isOther);
                if (!isOther) {
                    document.getElementById('li-dept-other').value = '';
                    document.getElementById('li-dept-other-count').textContent = '0';
                }
            }
            function setDepartments(values) {
                const wanted = (values || []).map(function (v) { return String(v).trim(); });
                document.querySelectorAll('#li-dept-menu input').forEach(function (el) {
                    el.checked = wanted.indexOf(el.value) !== -1;
                });
                refreshDeptLabel();
            }
            function buildDeptMenu() {
                const menu = document.getElementById('li-dept-menu');
                menu.innerHTML = DEPARTMENTS.map(function (value) {
                    const label = DEPT_LABELS[value] || value;
                    return '<label class="d-flex align-items-center gap-2 py-1 px-1 mb-0">' +
                        '<input type="checkbox" value="' + escAttr(value) + '"> <span>' + escapeHtml(label) + '</span></label>';
                }).join('');
                menu.addEventListener('change', refreshDeptLabel);
            }

            function toggleOtherFields() {
                const rootOther = document.getElementById('li-root').value.trim() === 'Other';
                document.getElementById('li-root-remark-wrap').classList.toggle('d-none', !rootOther);
                if (!rootOther) document.getElementById('li-root-remark').value = '';
                const actionOther = document.getElementById('li-action').value.trim() === 'Other';
                document.getElementById('li-action-remark-star').classList.toggle('d-none', !actionOther);
                const fixedOther = document.getElementById('li-root-fixed').value.trim() === 'Other';
                document.getElementById('li-root-fixed-remark-wrap').classList.toggle('d-none', !fixedOther);
                if (!fixedOther) document.getElementById('li-root-fixed-remark').value = '';
            }

            function resetForm() {
                document.getElementById('li-issue-form').reset();
                document.getElementById('li-id').value = '';
                document.getElementById('li-qty').value = '0';
                document.getElementById('li-parent').value = '';
                document.getElementById('li-product-master-id').value = '';
                document.getElementById('li-sku-image-wrap').classList.add('d-none');
                document.getElementById('li-save').textContent = 'Save';
                setDepartments([]);
                toggleOtherFields();
                hideAlert();
            }

            function fillForm(record) {
                resetForm();
                document.getElementById('li-id').value = record.id || '';
                document.getElementById('li-sku').value = record.sku || '';
                document.getElementById('li-qty').value = record.qty ?? 0;
                document.getElementById('li-order-qty').value = record.order_qty ?? '';
                document.getElementById('li-parent').value = record.parent || '';
                document.getElementById('li-order-number').value = record.order_number || '';
                document.getElementById('li-marketplace').value = record.marketplace_1 || '';
                document.getElementById('li-what').value = record.what_happened || '';
                document.getElementById('li-action').value = record.action_1 || '';
                document.getElementById('li-action-remark').value = record.action_1_remark || '';
                document.getElementById('li-track-r').value = record.replacement_tracking || '';
                document.getElementById('li-ctn').value = record.ctn_instructions || '';
                document.getElementById('li-item-pkg').value = record.instructions_item_pkg || '';
                document.getElementById('li-qe-issue').value = record.qc_enhance_issue || '';
                document.getElementById('li-qe-action').value = record.qc_enhance_action_req || '';
                document.getElementById('li-qe-remark').value = record.qc_enhance_status_remark || '';
                document.getElementById('li-product-master-id').value = record.product_master_id || '';
                document.getElementById('li-root').value = record.issue || '';
                document.getElementById('li-root-remark').value = record.issue_remark || '';
                document.getElementById('li-root-fixed').value = record.c_action_1 || '';
                document.getElementById('li-root-fixed-remark').value = record.c_action_1_remark || '';
                const depts = Array.isArray(record.departments) && record.departments.length
                    ? record.departments
                    : String(record.department || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
                setDepartments(depts);
                if (record.department_other_note) {
                    document.getElementById('li-dept-other').value = record.department_other_note;
                    document.getElementById('li-dept-other-count').textContent = String(record.department_other_note.length);
                }
                document.getElementById('li-save').textContent = 'Update';
                document.getElementById('li-issue-modal-title').textContent = 'Label Issue';
                toggleOtherFields();
            }

            async function refreshSkuSuggestions(query) {
                const q = String(query || '').trim();
                const list = document.getElementById('li-sku-datalist');
                if (q.length < 1) { list.innerHTML = ''; return; }
                try {
                    const res = await fetch(URLS.skuSearch + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json();
                    const skus = Array.isArray(data && data.skus) ? data.skus : [];
                    list.innerHTML = skus.map(function (item) {
                        const sku = item && item.sku ? item.sku : '';
                        const parent = item && item.parent ? item.parent : '';
                        const label = parent ? (parent + ' · ' + sku) : sku;
                        return '<option value="' + escAttr(sku) + '" label="' + escAttr(label) + '"></option>';
                    }).join('');
                } catch (e) { list.innerHTML = ''; }
            }

            async function fillSkuDetails() {
                const sku = document.getElementById('li-sku').value.trim();
                document.getElementById('li-qty').value = '';
                document.getElementById('li-parent').value = '';
                document.getElementById('li-product-master-id').value = '';
                document.getElementById('li-ctn').value = '';
                document.getElementById('li-item-pkg').value = '';
                document.getElementById('li-qe-issue').value = '';
                document.getElementById('li-qe-action').value = '';
                document.getElementById('li-qe-remark').value = '';
                document.getElementById('li-sku-image-wrap').classList.add('d-none');
                if (!sku) return;
                try {
                    const res = await fetch(URLS.skuDetails + '?sku=' + encodeURIComponent(sku), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json();
                    if (!res.ok || !data.found) return;
                    document.getElementById('li-qty').value = data.qty ?? 0;
                    document.getElementById('li-parent').value = data.parent || '';
                    document.getElementById('li-product-master-id').value = data.product_master_id || '';
                    document.getElementById('li-ctn').value = data.ctn_instructions || '';
                    document.getElementById('li-item-pkg').value = data.instructions_item_pkg || '';
                    document.getElementById('li-qe-issue').value = data.qc_enhance_issue || '';
                    document.getElementById('li-qe-action').value = data.qc_enhance_action_req || '';
                    document.getElementById('li-qe-remark').value = data.qc_enhance_status_remark || '';
                    if (data.image_url) {
                        document.getElementById('li-sku-image').src = data.image_url;
                        document.getElementById('li-sku-image-wrap').classList.remove('d-none');
                    }
                } catch (e) { /* keep blanks */ }
            }

            async function savePackagingFields() {
                const sku = document.getElementById('li-sku').value.trim();
                const productId = parseInt(document.getElementById('li-product-master-id').value, 10);
                const parent = document.getElementById('li-parent').value.trim();
                const ctn = document.getElementById('li-ctn').value.trim().slice(0, 100);
                const itemPkg = document.getElementById('li-item-pkg').value.trim().slice(0, 2000);
                try {
                    if (sku && !Number.isNaN(productId) && productId > 0) {
                        await fetch('/dim-wt-master/update', {
                            method: 'POST', headers: jsonHeaders,
                            body: JSON.stringify({ product_id: productId, sku: sku, parent: parent, ctn_instructions: ctn || null }),
                        });
                        await fetch('/instructions-item-pkg/update', {
                            method: 'POST', headers: jsonHeaders,
                            body: JSON.stringify({ product_id: productId, sku: sku, instructions: itemPkg }),
                        });
                    }
                    if (sku) {
                        await fetch('/quality-enhance/upsert-by-sku', {
                            method: 'POST', headers: jsonHeaders,
                            body: JSON.stringify({
                                sku: sku,
                                issue: document.getElementById('li-qe-issue').value.trim().slice(0, 2000),
                                action_req: document.getElementById('li-qe-action').value.trim().slice(0, 2000),
                                status_remark: document.getElementById('li-qe-remark').value.trim().slice(0, 2000),
                            }),
                        });
                    }
                } catch (e) {
                    alert('Issue saved, but CTN PKG, item packaging, or QC Enhance could not be updated.');
                }
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
                document.getElementById('li-count').textContent = String(table.getDataCount('active'));
            }

            async function loadHistory() {
                const res = await fetch(URLS.history, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                const rows = Array.isArray(data && data.data) ? data.data : [];
                if (!historyTable) {
                    historyTable = new Tabulator('#label-history-tabulator', {
                        layout: 'fitDataStretch',
                        height: '100%',
                        placeholder: 'No history found.',
                        index: 'id',
                        pagination: true,
                        paginationSize: 50,
                        paginationSizeSelector: [25, 50, 100],
                        columnDefaults: { tooltip: true },
                        columns: dataColumns(false),
                        data: rows,
                    });
                } else {
                    await historyTable.setData(rows);
                }
                document.getElementById('li-history-count').textContent = String(rows.length);
            }

            async function archiveRow(id) {
                if (!id || !confirm('Archive this record?')) return;
                const res = await fetch(URLS.updateBase + '/' + encodeURIComponent(id) + '/archive', {
                    method: 'POST', headers: jsonHeaders, body: '{}',
                });
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
                    const res = await fetch(URLS.updateBase + '/' + encodeURIComponent(id) + '/total-loss', {
                        method: 'PATCH', headers: jsonHeaders, body: JSON.stringify({ total_loss: parsed }),
                    });
                    const data = await res.json().catch(function () { return {}; });
                    if (!res.ok) throw new Error(data.message || 'Save failed');
                    const row = table.getRow(id);
                    if (row) row.update({ total_loss: data.total_loss != null ? data.total_loss : null });
                } catch (e) {
                    alert(e.message || 'Could not save Loss $');
                    input.value = original;
                }
            }

            function copyFromButton(btn) {
                const text = btn.getAttribute('data-copy') || '';
                const done = function () {
                    btn.classList.add('copied');
                    btn.innerHTML = '<i class="bi bi-clipboard-check"></i>';
                    setTimeout(function () {
                        btn.classList.remove('copied');
                        btn.innerHTML = '<i class="bi bi-clipboard"></i>';
                    }, 1200);
                };
                if (navigator.clipboard) navigator.clipboard.writeText(text).then(done);
            }

            function csvEscape(val) {
                const str = String(val ?? '').replace(/"/g, '""');
                return /[",\n\r]/.test(str) ? '"' + str + '"' : str;
            }
            function downloadCsv(content, filename) {
                const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url; a.download = filename;
                document.body.appendChild(a); a.click(); document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }

            async function loadDropdown(fieldType, datalistId) {
                try {
                    const res = await fetch(URLS.dropdownList + '?field_type=' + encodeURIComponent(fieldType), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json();
                    const opts = Array.isArray(data && data.data) ? data.data : [];
                    document.getElementById(datalistId).innerHTML = opts.map(function (o) {
                        return '<option value="' + escAttr(o) + '"></option>';
                    }).join('');
                } catch (e) { /* ignore */ }
            }

            function buildColumnsMenu() {
                const menu = document.getElementById('li-columns-menu');
                const labels = { image_url: 'Image', ctn_instructions: 'CTN Pkg', instructions_item_pkg: 'item pkg', qc_enhance_issue: 'QC Enhance', _actions: 'Close' };
                menu.innerHTML = '';
                table.getColumns().forEach(function (col) {
                    const field = col.getField();
                    if (!field) return;
                    const title = labels[field] || col.getDefinition().title || field;
                    const wrap = document.createElement('div');
                    wrap.className = 'form-check';
                    wrap.innerHTML = '<input class="form-check-input" type="checkbox" ' + (col.isVisible() ? 'checked' : '') + '>' +
                        '<label class="form-check-label">' + escapeHtml(title) + '</label>';
                    wrap.querySelector('input').addEventListener('change', function () {
                        if (this.checked) col.show(); else col.hide();
                        const visibility = {};
                        table.getColumns().forEach(function (c) { if (c.getField()) visibility[c.getField()] = c.isVisible(); });
                        fetch(URLS.colVisSet, { method: 'POST', headers: jsonHeaders, body: JSON.stringify({ channel: COLVIS_CHANNEL, visibility: visibility }) });
                    });
                    menu.appendChild(wrap);
                });
            }

            document.addEventListener('DOMContentLoaded', function () {
                buildDeptMenu();
                modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('li-issue-modal'));
                table = new Tabulator('#label-tabulator', {
                    layout: 'fitDataStretch',
                    height: '100%',
                    placeholder: 'No records found.',
                    index: 'id',
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [25, 50, 100, 200],
                    paginationCounter: 'rows',
                    columnDefaults: { tooltip: true },
                    columns: dataColumns(true),
                });

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
                    } catch (e) { /* ignore */ }
                    buildColumnsMenu();
                    loadRows().catch(function (e) { console.error(e); });
                    loadDropdown('root_cause_found', 'li-root-found-datalist');
                    loadDropdown('root_cause_fixed', 'li-root-fixed-datalist');
                });

                table.on('cellClick', function (e, cell) {
                    const copyBtn = e.target.closest('.copy-order-btn, .copy-tracking-btn');
                    if (copyBtn) { copyFromButton(copyBtn); return; }
                    const editBtn = e.target.closest('.li-edit');
                    const archiveBtn = e.target.closest('.li-archive');
                    if (!editBtn && !archiveBtn) return;
                    const data = cell.getRow().getData();
                    if (editBtn) {
                        fillForm(data);
                        document.getElementById('li-issue-modal-title').textContent = 'Label Issue';
                        modal.show();
                    } else {
                        archiveRow(data.id);
                    }
                });

                document.getElementById('label-tabulator').addEventListener('focusout', function (e) {
                    const inp = e.target.closest('.issue-loss-input');
                    if (inp) saveLoss(inp);
                });
                document.getElementById('label-tabulator').addEventListener('keydown', function (e) {
                    const inp = e.target.closest('.issue-loss-input');
                    if (inp && e.key === 'Enter') { e.preventDefault(); inp.blur(); }
                });

                document.getElementById('li-add').addEventListener('click', function () {
                    resetForm();
                    document.getElementById('li-issue-modal-title').textContent = 'Label Issue';
                    modal.show();
                });
                document.getElementById('li-history').addEventListener('click', function () {
                    const card = document.getElementById('li-history-card');
                    card.classList.remove('d-none');
                    loadHistory().then(function () { card.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
                });
                document.getElementById('li-search').addEventListener('input', function () {
                    const term = this.value.trim().toLowerCase();
                    if (!term) {
                        table.clearFilter();
                        document.getElementById('li-count').textContent = String(table.getDataCount('active'));
                        return;
                    }
                    table.setFilter(function (row) {
                        const depts = Array.isArray(row.departments) ? row.departments.join(' ') : '';
                        const hay = [row.id, row.sku, row.parent, row.order_number, row.order_qty, row.replacement_tracking,
                            row.what_happened, row.action_1, row.action_1_remark, row.issue, row.issue_remark,
                            row.c_action_1, row.c_action_1_remark, row.marketplace_1, row.department, depts,
                            row.total_loss, row.created_by, row.close_note].map(function (v) { return String(v ?? '').toLowerCase(); }).join(' | ');
                        return hay.includes(term);
                    });
                    document.getElementById('li-count').textContent = String(table.getDataCount('active'));
                });

                const skuInput = document.getElementById('li-sku');
                skuInput.addEventListener('input', function () {
                    clearTimeout(skuTimer);
                    skuTimer = setTimeout(function () { refreshSkuSuggestions(skuInput.value); }, 220);
                });
                skuInput.addEventListener('change', fillSkuDetails);
                skuInput.addEventListener('blur', fillSkuDetails);
                document.getElementById('li-root').addEventListener('input', toggleOtherFields);
                document.getElementById('li-action').addEventListener('input', toggleOtherFields);
                document.getElementById('li-root-fixed').addEventListener('input', toggleOtherFields);
                document.getElementById('li-dept-other').addEventListener('input', function () {
                    document.getElementById('li-dept-other-count').textContent = String(this.value.length);
                });
                document.getElementById('li-issue-modal').addEventListener('hidden.bs.modal', resetForm);

                document.getElementById('li-issue-form').addEventListener('submit', async function (event) {
                    event.preventDefault();
                    hideAlert();
                    const sku = document.getElementById('li-sku').value.trim();
                    const issue = document.getElementById('li-root').value.trim();
                    const depts = selectedDepartments();
                    const otherNote = document.getElementById('li-dept-other').value.trim();
                    if (!sku) { showAlert('SKU is required.'); return; }
                    if (!issue) { showAlert('Root Cause Found is required.'); return; }
                    if (document.getElementById('li-action').value.trim() === 'Other' && document.getElementById('li-action-remark').value.trim() === '') {
                        showAlert('Please enter Action remark when Action is Other.'); return;
                    }
                    if (issue === 'Other' && document.getElementById('li-root-remark').value.trim() === '') {
                        showAlert('Please enter Root Cause remark for Other.'); return;
                    }
                    if (document.getElementById('li-root-fixed').value.trim() === 'Other' && document.getElementById('li-root-fixed-remark').value.trim() === '') {
                        showAlert('Please enter Root Cause Fixed remark for Other.'); return;
                    }
                    if (!depts.length) { showAlert('Select at least one department.'); return; }
                    if (depts.indexOf('Other') !== -1 && otherNote === '') {
                        showAlert('Please enter Responsible Dept notes when Other is selected.'); return;
                    }
                    const orderQtyRaw = document.getElementById('li-order-qty').value;
                    const payload = {
                        sku: sku,
                        qty: document.getElementById('li-qty').value === '' ? 0 : Number(document.getElementById('li-qty').value),
                        order_qty: orderQtyRaw === '' ? null : Number(orderQtyRaw),
                        parent: document.getElementById('li-parent').value.trim(),
                        order_number: document.getElementById('li-order-number').value.trim(),
                        marketplace_1: document.getElementById('li-marketplace').value.trim(),
                        what_happened: document.getElementById('li-what').value.trim(),
                        issue: issue,
                        issue_remark: document.getElementById('li-root-remark').value.trim(),
                        action_1: document.getElementById('li-action').value.trim(),
                        action_1_remark: document.getElementById('li-action-remark').value.trim(),
                        replacement_tracking: document.getElementById('li-track-r').value.trim(),
                        c_action_1: document.getElementById('li-root-fixed').value.trim(),
                        c_action_1_remark: document.getElementById('li-root-fixed-remark').value.trim(),
                        department: depts,
                        department_other_note: depts.indexOf('Other') !== -1 ? otherNote : '',
                    };
                    const editId = document.getElementById('li-id').value;
                    const saveBtn = document.getElementById('li-save');
                    saveBtn.disabled = true;
                    saveBtn.textContent = 'Saving...';
                    try {
                        const res = await fetch(editId ? (URLS.updateBase + '/' + encodeURIComponent(editId)) : URLS.store, {
                            method: editId ? 'PUT' : 'POST',
                            headers: jsonHeaders,
                            body: JSON.stringify(payload),
                        });
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

                document.getElementById('li-export').addEventListener('click', function () {
                    const headers = ['#', 'SKU', 'Order ID', 'Loss $', 'Order QTY', 'MKT', 'Issue?', 'Action', 'Action Remark', 'Track R', 'Root Cause Found', 'Root Cause Remark', 'Root Cause Fixed', 'Root Cause Fixed Remark', 'Dept', 'Created By', 'Created At'];
                    const rows = table.getData().map(function (r) {
                        return [r.id, r.sku, r.order_number || '', r.total_loss ?? '', r.order_qty, r.marketplace_1, r.what_happened, r.action_1, r.action_1_remark, r.replacement_tracking, r.issue, r.issue_remark, r.c_action_1, r.c_action_1_remark, deptLabel(r), r.created_by, r.created_at];
                    });
                    const lines = [headers.map(csvEscape).join(',')].concat(rows.map(function (r) { return r.map(csvEscape).join(','); }));
                    downloadCsv(lines.join('\r\n'), 'label_issues_active_' + new Date().toISOString().slice(0, 10) + '.csv');
                });

                document.getElementById('li-import').addEventListener('click', function () {
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
                    formData.append('_token', CSRF);
                    alertEl.className = 'd-none mb-3';
                    progressEl.classList.remove('d-none');
                    errorsEl.classList.add('d-none');
                    this.disabled = true;
                    try {
                        const res = await fetch(URLS.import, { method: 'POST', body: formData });
                        const data = await res.json();
                        progressEl.classList.add('d-none');
                        alertEl.className = 'alert mb-3 alert-' + (res.ok ? 'success' : 'danger');
                        alertEl.textContent = data.message || (res.ok ? 'Import complete.' : 'Import failed.');
                        if (data.errors && data.errors.length) {
                            errList.innerHTML = data.errors.map(function (err) { return '<li>' + escapeHtml(err) + '</li>'; }).join('');
                            errorsEl.classList.remove('d-none');
                        }
                        if (res.ok) await loadRows();
                    } catch (err) {
                        progressEl.classList.add('d-none');
                        alertEl.className = 'alert alert-danger mb-3';
                        alertEl.textContent = 'Network error. Please try again.';
                    } finally {
                        document.getElementById('importCsvSubmitBtn').disabled = false;
                    }
                });
            });
        })();
    </script>
@endsection
