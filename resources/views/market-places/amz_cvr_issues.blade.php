@extends('layouts.vertical', ['title' => 'Amz CVR Issues', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #amz_cvr_issues_wrap .tabulator {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            font-size: 12px;
            overflow-x: hidden !important;
        }
        /* No inner under-scroll — page scrolls; tableholder does not */
        #amz_cvr_issues_wrap .tabulator .tabulator-tableholder {
            overflow: hidden !important;
        }
        #amz_cvr_issues_wrap .tabulator .tabulator-header {
            overflow: hidden !important;
        }
        #amz_cvr_issues_wrap .tabulator .tabulator-tableholder::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }

        /* Hide sort arrows; clicking header still sorts */
        #amz_cvr_issues_wrap .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        /* Vertical column headers (same as amazon-tabulator-view) */
        #amz_cvr_issues_wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
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

        /* Keep checkbox column header upright */
        #amz_cvr_issues_wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="row_select"] .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            height: auto !important;
        }

        #amz_cvr_issues_wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="row_select"] .tabulator-col-content .tabulator-col-title input {
            transform: none !important;
        }

        #amz_cvr_issues_wrap .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        #amz_cvr_issues_wrap .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        #amz_cvr_issues_wrap .tabulator .tabulator-cell {
            padding: 4px 6px !important;
        }

        #amz_cvr_issues_wrap .hover-thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            cursor: zoom-in;
        }

        /* Audit modal: full width, top-aligned; ~20vh initially, grows with issues */
        #amzCvrAuditModal.modal {
            padding: 0 !important;
            z-index: 2100 !important;
        }
        #amzCvrAuditModal .modal-dialog,
        #amzCvrAuditModal .amz-cvr-audit-modal-dialog {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            width: 100vw !important;
            max-width: 100vw !important;
            height: auto !important;
            min-height: 20vh !important;
            max-height: 100vh !important;
            margin: 0 !important;
            padding: 0 !important;
            transform: none !important;
        }
        #amzCvrAuditModal .modal-content {
            width: 100% !important;
            max-width: 100% !important;
            height: auto !important;
            min-height: 20vh !important;
            max-height: 100vh !important;
            border: 0 !important;
            border-radius: 0 !important;
            display: flex !important;
            flex-direction: column !important;
        }
        #amzCvrAuditModal .modal-body {
            flex: 1 1 auto !important;
            height: auto !important;
            max-height: none !important;
            overflow-y: visible !important;
        }
        /* When many issues push past viewport, scroll body only */
        #amzCvrAuditModal.amz-cvr-audit-tall .modal-dialog {
            height: 100vh !important;
            max-height: 100vh !important;
        }
        #amzCvrAuditModal.amz-cvr-audit-tall .modal-content {
            height: 100vh !important;
            max-height: 100vh !important;
        }
        #amzCvrAuditModal.amz-cvr-audit-tall .modal-body {
            overflow-y: auto !important;
            max-height: calc(100vh - 110px) !important;
        }

        /* LMP: our product row (same pattern as ebay-tabulator-view) */
        #amzCvrLmpModal .lmp-five-core-row,
        #amzCvrLmpModal .lmp-five-core-row > td {
            background-color: #dbeafe !important;
            color: #1e3a8a;
            --bs-table-bg-type: #dbeafe;
            --bs-table-striped-bg: #dbeafe;
            --bs-table-hover-bg: #bfdbfe;
            font-weight: 600;
        }
        #amzCvrLmpModal .lmp-five-core-row:hover > td {
            background-color: #bfdbfe !important;
        }
        @include('partials.lmp-ignore', ['lmpIgnorePart' => 'css', 'lmpIgnoreModal' => '#amzCvrLmpModal'])
        #amzCvrLmpModal .lmp-five-core-row .lmp-five-core-price {
            font-size: 14px;
            font-weight: 700;
        }

        #amz_cvr_issues_wrap .amz-cvr-audit-btn {
            border: 1px solid #2f9e44;
            background: #fff;
            color: #2f9e44;
            border-radius: 6px;
            width: 28px;
            height: 28px;
            padding: 0;
            line-height: 1;
            cursor: pointer;
            font-weight: 700;
        }
        #amz_cvr_issues_wrap .amz-cvr-audit-btn:hover {
            background: #ebfbee;
        }
        #amz_cvr_issues_wrap .amz-cvr-history-cell {
            font-size: 11px;
            line-height: 1.35;
            text-align: left;
            white-space: normal;
            min-width: 260px;
        }
        #amz_cvr_issues_wrap .amz-cvr-history-line {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px 8px;
            white-space: nowrap;
        }
        #amz_cvr_issues_wrap .amz-cvr-history-user {
            font-weight: 600;
            color: #212529;
            white-space: nowrap;
        }
        #amz_cvr_issues_wrap .amz-cvr-history-meta {
            color: #6c757d;
            white-space: nowrap;
        }
        #amz_cvr_issues_wrap .tabulator-cell[tabulator-field="audit_history_ts"] {
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: clip !important;
        }
        #amz_cvr_issues_wrap .amz-cvr-history-cvr-badge {
            display: inline-block;
            margin-top: 2px;
            padding: 1px 6px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 10px;
            line-height: 1.3;
            cursor: pointer;
        }
        #amz-cvr-avg-cvr-badge:hover,
        #amz_cvr_issues_wrap .amz-cvr-history-cvr-badge:hover {
            opacity: 0.9;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.15);
        }
        /* CVR graph rule — 3 BG colors (same bands as Analytics Amz CVR L30) */
        #amz_cvr_issues_wrap .amz-cvr-history-cvr-badge.cvr-bg-red,
        #amz-cvr-avg-cvr-badge.cvr-bg-red {
            background-color: #a00211 !important;
            color: #fff !important;
        }
        #amz_cvr_issues_wrap .amz-cvr-history-cvr-badge.cvr-bg-yellow,
        #amz-cvr-avg-cvr-badge.cvr-bg-yellow {
            background-color: #ffc107 !important;
            color: #000 !important;
        }
        #amz_cvr_issues_wrap .amz-cvr-history-cvr-badge.cvr-bg-green,
        #amz-cvr-avg-cvr-badge.cvr-bg-green {
            background-color: #28a745 !important;
            color: #fff !important;
        }

        /* Equal-size toolbar badges / filters / refresh */
        #amz-cvr-toolbar {
            --amz-cvr-ctrl-h: 32px;
            --amz-cvr-ctrl-fs: 13px;
            --amz-cvr-ctrl-radius: 6px;
        }
        #amz-cvr-toolbar > .badge,
        #amz-cvr-toolbar > .form-select,
        #amz-cvr-toolbar > .form-control,
        #amz-cvr-toolbar > .btn {
            height: var(--amz-cvr-ctrl-h) !important;
            min-height: var(--amz-cvr-ctrl-h) !important;
            max-height: var(--amz-cvr-ctrl-h) !important;
            font-size: var(--amz-cvr-ctrl-fs) !important;
            font-weight: 600 !important;
            line-height: 1.2 !important;
            border-radius: var(--amz-cvr-ctrl-radius) !important;
            display: inline-flex !important;
            align-items: center !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            vertical-align: middle;
        }
        #amz-cvr-toolbar > .badge {
            padding: 0 0.75rem !important;
            justify-content: center;
        }
        #amz-cvr-toolbar > .form-select {
            padding: 0 2rem 0 0.65rem !important;
            width: auto !important;
            min-width: 8.5rem;
        }
        #amz-cvr-toolbar > .form-control {
            padding: 0 0.65rem !important;
            width: 160px !important;
            min-width: 140px;
            font-weight: 500 !important;
        }
        #amz-cvr-toolbar > .btn {
            padding: 0 0.7rem !important;
            justify-content: center;
            width: var(--amz-cvr-ctrl-h);
        }
        #amz-cvr-toolbar > .amz-cvr-keep-wrap {
            height: var(--amz-cvr-ctrl-h) !important;
            margin: 0 !important;
            padding: 0 0.55rem !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.35rem;
            font-size: var(--amz-cvr-ctrl-fs) !important;
            font-weight: 600 !important;
            border: 1px solid #ced4da;
            border-radius: var(--amz-cvr-ctrl-radius) !important;
            background: #fff;
            white-space: nowrap;
            user-select: none;
            cursor: pointer;
        }
        #amz-cvr-toolbar > .amz-cvr-keep-wrap .form-check-input {
            margin: 0 !important;
            cursor: pointer;
        }
        #amz-cvr-toolbar > .amz-cvr-keep-wrap .form-check-label {
            margin: 0 !important;
            cursor: pointer;
            line-height: 1.2;
        }
        #amz-cvr-toolbar > .amz-cvr-keep-wrap:has(input:checked) {
            background: #e7f1ff;
            border-color: #0d6efd;
            color: #0d6efd;
        }
        #amz-cvr-toolbar > #amz-cvr-export-group {
            height: var(--amz-cvr-ctrl-h) !important;
            margin: 0 !important;
        }
        #amz-cvr-toolbar > #amz-cvr-export-group > .btn {
            height: var(--amz-cvr-ctrl-h) !important;
            min-height: var(--amz-cvr-ctrl-h) !important;
            max-height: var(--amz-cvr-ctrl-h) !important;
            font-size: var(--amz-cvr-ctrl-fs) !important;
            font-weight: 600 !important;
            line-height: 1.2 !important;
            border-radius: var(--amz-cvr-ctrl-radius) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.35rem;
            padding: 0 0.7rem !important;
            width: auto !important;
            box-sizing: border-box !important;
            margin: 0 !important;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amz CVR Issues',
        'sub_title' => 'Amz CVR issues',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div id="amz-cvr-toolbar" class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span id="amz-cvr-issues-rows" class="badge bg-dark"
                            style="color: #fff;"
                            title="Rows currently shown after filters">Rows: —</span>
                        <span id="amz-cvr-issues-selected" class="badge bg-primary">Selected: 0</span>
                        <span id="amz-cvr-avg-cvr-badge" class="badge cvr-bg-red"
                            style="cursor: pointer;"
                            title="Click for rolling CVR graph. Overall = (Σ Sold L30 ÷ Σ Views L30) × 100. BG: Red ≤4% · Yellow 4–7% · Green &gt;7%">CVR: 0.0%</span>
                        <span id="amz-cvr-ml-badge" class="badge"
                            style="background-color: #28a745; color: #fff; cursor: pointer;"
                            title="Missing L — INV&gt;0, not listed on Amz (price ≤ 0), REQ. Click to filter.">
                            ML: <span id="amz-cvr-ml-count">0</span>
                        </span>
                        <select id="amz-cvr-cvr-filter" class="form-select form-select-sm"
                            title="CVR% slabs — same bands as Analytics Amz / Sprice × CVR Rule">
                            <option value="all">CVR%</option>
                            <option value="zero">0%</option>
                            <option value="yellow">0.01–3.5%</option>
                            <option value="blue">3.51–7%</option>
                            <option value="green">7.01–13%</option>
                            <option value="pink">&gt;13.01%</option>
                        </select>
                        <select id="amz-cvr-views-filter" class="form-select form-select-sm"
                            title="Filter by Views (sessions L30)">
                            <option value="all">Views</option>
                            <option value="zero">0</option>
                            <option value="1-70">1 to 70</option>
                            <option value="71-300">71 to 300</option>
                            <option value="gt300">&gt; 300</option>
                        </select>
                        <select id="amz-cvr-history-date-filter" class="form-select form-select-sm"
                            title="Filter by Audit History date (latest dates on top)">
                            <option value="all">History date</option>
                            <option value="none">No history</option>
                        </select>
                        <input type="text" id="amz-cvr-sku-search" class="form-control form-control-sm"
                            placeholder="Search SKU..." title="Filter rows by SKU (partial match)">
                        <label class="amz-cvr-keep-wrap form-check mb-0"
                            title="Keep applied filters for 5 minutes after refresh (this user only)">
                            <input type="checkbox" class="form-check-input" id="amz-cvr-keep-filters">
                            <span class="form-check-label">Keep 5m</span>
                        </label>
                        <div class="btn-group" id="amz-cvr-export-group">
                            <button type="button" class="btn btn-sm btn-success dropdown-toggle"
                                data-bs-toggle="dropdown" aria-expanded="false" title="Export CSV">
                                <i class="fa fa-download"></i> Export
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#" id="amz-cvr-export-filtered">
                                        <i class="fas fa-filter text-primary me-1"></i> Export Filtered
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" id="amz-cvr-export-all">
                                        <i class="fas fa-list text-secondary me-1"></i> Export All
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <button type="button" id="amz-cvr-issues-refresh-btn" class="btn btn-sm btn-outline-primary" title="Reload table">
                            <i class="fa fa-refresh"></i>
                        </button>
                    </div>

                    <div id="amz_cvr_issues_wrap">
                        <div id="amz_cvr_issues"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Audit Modal --}}
    <div class="modal fade" id="amzCvrAuditModal" tabindex="-1" aria-labelledby="amzCvrAuditModalLabel" aria-hidden="true">
        <div class="modal-dialog amz-cvr-audit-modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white py-2">
                    <h5 class="modal-title mb-0" id="amzCvrAuditModalLabel">
                        <i class="fas fa-clipboard-check me-1"></i>
                        Audit — <span id="amzCvrAuditSku" class="fw-normal opacity-75"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3 small">
                        <div class="col-md-4">
                            <div class="text-muted">Parent</div>
                            <div class="fw-semibold" id="amzCvrAuditParent">—</div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-muted">INV</div>
                            <div class="fw-semibold" id="amzCvrAuditInv">—</div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-muted">Views</div>
                            <div class="fw-semibold" id="amzCvrAuditViews">—</div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-muted">CVR L30</div>
                            <div class="fw-semibold" id="amzCvrAuditCvr">—</div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-muted">Price</div>
                            <div class="fw-semibold" id="amzCvrAuditPrice">—</div>
                        </div>
                    </div>
                    <input type="hidden" id="amzCvrAuditSkuInput" value="">
                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                            <div class="form-label fw-semibold mb-0">Issue found</div>
                            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" id="amzCvrAddIssueBtn"
                                title="Add issue type with assignee for future tasks">
                                <i class="fas fa-plus"></i> Issue
                            </button>
                        </div>
                        <div class="d-flex flex-wrap gap-3 align-items-center" id="amzCvrAuditIssueOptions">
                            <div class="form-check mb-0">
                                <input class="form-check-input amz-cvr-issue-opt" type="checkbox" value="pricing" id="amzCvrIssuePricing">
                                <label class="form-check-label" for="amzCvrIssuePricing">Pricing Issue</label>
                            </div>
                            <div class="form-check mb-0">
                                <input class="form-check-input amz-cvr-issue-opt" type="checkbox" value="compliance" id="amzCvrIssueCompliance">
                                <label class="form-check-label" for="amzCvrIssueCompliance">Compliance Issue</label>
                            </div>
                            <div class="form-check mb-0">
                                <input class="form-check-input amz-cvr-issue-opt" type="checkbox" value="missing_listing" id="amzCvrIssueMissingListing">
                                <label class="form-check-label" for="amzCvrIssueMissingListing">Missing listing Issue</label>
                            </div>
                            <div class="form-check mb-0">
                                <input class="form-check-input amz-cvr-issue-opt" type="checkbox" value="advertisement" id="amzCvrIssueAdvertisement">
                                <label class="form-check-label" for="amzCvrIssueAdvertisement">Advertisement Issue</label>
                            </div>
                            <span id="amzCvrCustomIssueOptions" class="d-flex flex-wrap gap-3"></span>
                            <div class="form-check mb-0">
                                <input class="form-check-input amz-cvr-issue-opt" type="checkbox" value="other" id="amzCvrIssueOther">
                                <label class="form-check-label" for="amzCvrIssueOther">Other Issue</label>
                            </div>
                        </div>
                        <div id="amzCvrAuditIssueOtherWrap" class="mt-2 d-none">
                            <label for="amzCvrAuditIssueOtherText" class="form-label small text-muted mb-1">Additional issue</label>
                            <textarea id="amzCvrAuditIssueOtherText" class="form-control" rows="2"
                                placeholder="Describe the additional issue..." maxlength="1000"></textarea>
                        </div>
                    </div>
                    <div id="amzCvrAuditBulkNote" class="small text-muted mb-2 d-none"></div>
                    <div id="amzCvrAuditTaskRows" class="d-flex flex-column gap-2 mb-2"></div>
                    <div class="mb-0">
                        <button type="button" class="btn btn-success btn-sm" id="amzCvrAuditSubmitTaskBtn">
                            <i class="fas fa-paper-plane me-1"></i> <span id="amzCvrAuditSubmitLabel">Submit</span>
                        </button>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary btn-sm" id="amzCvrAuditSaveBtn">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Add custom Issue type modal --}}
    <div class="modal fade" id="amzCvrAddIssueModal" tabindex="-1" aria-labelledby="amzCvrAddIssueModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amzCvrAddIssueModalLabel">
                        <i class="fas fa-plus me-1"></i> Add Issue Type
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        New issues are saved for future task allotment with the selected assignee.
                    </p>
                    <div class="mb-2">
                        <label for="amzCvrNewIssueLabel" class="form-label fw-semibold mb-1">Issue name</label>
                        <input type="text" id="amzCvrNewIssueLabel" class="form-control"
                            placeholder="e.g. Inventory" maxlength="200" autocomplete="off">
                        <small class="text-muted">“Issue” is added automatically if missing.</small>
                    </div>
                    <div class="mb-2">
                        <label for="amzCvrNewIssueAssigneeSearch" class="form-label fw-semibold mb-1">Assignee</label>
                        <div class="position-relative" id="amzCvrNewIssueAssigneeWrap">
                            <input type="text" id="amzCvrNewIssueAssigneeSearch" class="form-control"
                                placeholder="Quick Search assignee..." autocomplete="off">
                            <input type="hidden" id="amzCvrNewIssueAssigneeId" value="">
                            <div id="amzCvrNewIssueAssigneeDropdown"
                                class="list-group position-absolute w-100 shadow-sm d-none"
                                style="z-index: 1080; max-height: 220px; overflow-y: auto; top: 100%; left: 0;">
                            </div>
                        </div>
                    </div>
                    <div id="amzCvrCustomIssueList" class="mt-3"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary btn-sm" id="amzCvrSaveNewIssueBtn">
                        <i class="fas fa-save me-1"></i> Save Issue
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Rolling CVR Graph Modal --}}
    <div class="modal fade" id="amzCvrRollingChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white py-2">
                    <h6 class="modal-title mb-0">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="amzCvrRollingChartTitle">CVR Rolling History</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="amzCvrRollingChartDays" class="form-select form-select-sm bg-white"
                            style="width: 110px; height: 28px; font-size: 12px;">
                            <option value="7">7 Days</option>
                            <option value="30" selected>30 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div class="d-flex align-items-stretch" style="min-height: 260px;">
                        <div id="amzCvrRollingChartContainer" style="flex: 1; min-width: 0; height: 260px;"></div>
                        <div style="width: 96px; display: flex; flex-direction: column; justify-content: center; gap: 10px; padding: 8px; border-left: 1px solid #e9ecef; background: #f8f9fa;">
                            <div class="text-center">
                                <div class="small text-danger fw-bold" style="font-size: 10px;">HIGHEST</div>
                                <div id="amzCvrChartHighest" class="fw-bold text-danger">—</div>
                            </div>
                            <div class="text-center border-top border-bottom py-2">
                                <div class="small text-muted fw-bold" style="font-size: 10px;">MEDIAN</div>
                                <div id="amzCvrChartMedian" class="fw-bold text-muted">—</div>
                            </div>
                            <div class="text-center">
                                <div class="small text-success fw-bold" style="font-size: 10px;">LOWEST</div>
                                <div id="amzCvrChartLowest" class="fw-bold text-success">—</div>
                            </div>
                        </div>
                    </div>
                    <div id="amzCvrRollingChartLoading" class="text-center py-4 d-none">
                        <i class="fa fa-spinner fa-spin me-1"></i> Loading…
                    </div>
                    <div id="amzCvrRollingChartNoData" class="text-center text-muted py-4 d-none">No rolling CVR data found.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- LMP Competitors Modal (same API as Analytics Amz) --}}
    <div class="modal fade" id="amzCvrLmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white flex-wrap gap-2 py-2">
                    <h5 class="modal-title mb-0">
                        <i class="fa fa-shopping-cart me-1"></i>
                        Competitors for SKU: <span id="amzCvrLmpSku"></span>
                    </h5>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <button type="button" id="amzCvrLmpPullBtn" class="btn btn-sm btn-light"
                            title="Pull live prices from Amz API">
                            <i class="fas fa-cloud-download-alt"></i> Pull
                        </button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="card mb-3 border-success">
                        <div class="card-header bg-success text-white py-2">
                            <strong><i class="fa fa-plus-circle"></i> Add New Competitor</strong>
                        </div>
                        <div class="card-body py-2">
                            <form id="amzCvrAddCompForm" class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small mb-0">SKU</label>
                                    <input type="text" class="form-control form-control-sm" id="amzCvrAddCompSku" readonly>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-0">ASIN <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="amzCvrAddCompAsin" placeholder="B07ABC123" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small mb-0">Price <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm" id="amzCvrAddCompPrice" placeholder="29.99" step="0.01" min="0.01" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-0">Product Link</label>
                                    <input type="url" class="form-control form-control-sm" id="amzCvrAddCompLink" placeholder="https://amazon.com/dp/...">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-success btn-sm w-100">
                                        <i class="fa fa-plus"></i> Add
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div id="amzCvrLmpDataList">
                        <div class="text-center py-5 text-muted">
                            <div class="spinner-border text-primary me-2"></div>Loading competitors...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        @include('partials.lmp-ignore', ['lmpIgnorePart' => 'script'])
        let amz_cvr_issues = null;
        let amzCvrLmpCurrentSku = '';
        let amzCvrCurrentCompetitors = [];
        let amzCvrMlFilterActive = false;
        let amzCvrSavedHistoryDate = null;
        const AMZ_CVR_COMPETITORS_URL = @json(route('amazon.competitors.get'));
        const AMZ_CVR_LMP_ADD_URL = @json(route('amazon.lmp.add'));
        const AMZ_CVR_LMP_DELETE_URL = @json(route('amazon.lmp.delete.post'));
        const AMZ_CVR_CSRF = @json(csrf_token());
        const AMZ_CVR_USER_ID = @json(auth()->id());
        const AMZ_CVR_FILTER_TTL_MS = 5 * 60 * 1000;
        const AMZ_CVR_FILTER_STORAGE_KEY = 'amz_cvr_issues_filters_u' + String(AMZ_CVR_USER_ID || 'guest');

        function amzCvrEsc(s) {
            return String(s ?? '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function clearAmzCvrSavedFilters() {
            try { localStorage.removeItem(AMZ_CVR_FILTER_STORAGE_KEY); } catch (e) {}
            amzCvrSavedHistoryDate = null;
        }

        function collectAmzCvrFilterState() {
            return {
                cvr: (document.getElementById('amz-cvr-cvr-filter') || {}).value || 'all',
                views: (document.getElementById('amz-cvr-views-filter') || {}).value || 'all',
                historyDate: (document.getElementById('amz-cvr-history-date-filter') || {}).value || 'all',
                sku: String((document.getElementById('amz-cvr-sku-search') || {}).value || ''),
                ml: !!amzCvrMlFilterActive
            };
        }

        function saveAmzCvrFilterState() {
            if (!AMZ_CVR_USER_ID) return;
            const keepEl = document.getElementById('amz-cvr-keep-filters');
            if (!keepEl || !keepEl.checked) {
                clearAmzCvrSavedFilters();
                return;
            }
            try {
                localStorage.setItem(AMZ_CVR_FILTER_STORAGE_KEY, JSON.stringify({
                    userId: AMZ_CVR_USER_ID,
                    expiresAt: Date.now() + AMZ_CVR_FILTER_TTL_MS,
                    filters: collectAmzCvrFilterState()
                }));
            } catch (e) {}
        }

        function restoreAmzCvrFilterState() {
            if (!AMZ_CVR_USER_ID) return false;
            let raw = null;
            try { raw = localStorage.getItem(AMZ_CVR_FILTER_STORAGE_KEY); } catch (e) { return false; }
            if (!raw) return false;

            let data = null;
            try { data = JSON.parse(raw); } catch (e) {
                clearAmzCvrSavedFilters();
                return false;
            }

            if (!data || data.userId !== AMZ_CVR_USER_ID
                || !data.expiresAt || Date.now() > Number(data.expiresAt)
                || !data.filters) {
                clearAmzCvrSavedFilters();
                return false;
            }

            const f = data.filters;
            const keepEl = document.getElementById('amz-cvr-keep-filters');
            if (keepEl) keepEl.checked = true;

            const cvrEl = document.getElementById('amz-cvr-cvr-filter');
            if (cvrEl && f.cvr) cvrEl.value = f.cvr;

            const viewsEl = document.getElementById('amz-cvr-views-filter');
            if (viewsEl && f.views) viewsEl.value = f.views;

            amzCvrSavedHistoryDate = f.historyDate ? String(f.historyDate) : null;
            const histEl = document.getElementById('amz-cvr-history-date-filter');
            if (histEl && (f.historyDate === 'all' || f.historyDate === 'none')) {
                histEl.value = f.historyDate;
            }

            const skuEl = document.getElementById('amz-cvr-sku-search');
            if (skuEl) skuEl.value = f.sku != null ? String(f.sku) : '';

            amzCvrMlFilterActive = !!f.ml;
            return true;
        }

        /** Missing L — same rule as Analytics Amz / map-issues: INV>0, REQ, price ≤ 0. */
        function isAmzCvrMissingL(row) {
            if (!row) return false;
            const nr = String(row.NR || '').trim().toUpperCase();
            if (nr === 'NR' || nr === 'NRL') return false;
            const inv = parseFloat(row.INV || 0) || 0;
            if (inv <= 0) return false;
            const price = parseFloat(row.price || 0) || 0;
            return price <= 0;
        }

        function updateAmzCvrMlBadge() {
            if (!amz_cvr_issues) return;
            let count = 0;
            amz_cvr_issues.getData().forEach(function(row) {
                if (isAmzCvrMissingL(row)) count++;
            });
            const countEl = document.getElementById('amz-cvr-ml-count');
            const badgeEl = document.getElementById('amz-cvr-ml-badge');
            if (countEl) countEl.textContent = count.toLocaleString();
            if (badgeEl) {
                // 0 → green, otherwise red
                badgeEl.style.backgroundColor = count === 0 ? '#28a745' : '#dc3545';
                badgeEl.style.color = '#fff';
                badgeEl.style.outline = amzCvrMlFilterActive ? '2px solid #000' : '';
            }
        }

        /** Rows badge + overall CVR for currently visible (filtered) rows */
        function updateAmzCvrRowsBadge() {
            if (!amz_cvr_issues) return;
            let n = 0;
            let rows = [];
            try {
                n = amz_cvr_issues.getDataCount('active');
                rows = amz_cvr_issues.getData('active') || [];
            } catch (e) {
                const activeRows = amz_cvr_issues.getRows('active');
                rows = (Array.isArray(activeRows) ? activeRows : []).map(function(r) {
                    return typeof r.getData === 'function' ? r.getData() : r;
                });
                n = rows.length;
            }
            const el = document.getElementById('amz-cvr-issues-rows');
            if (el) el.textContent = 'Rows: ' + n.toLocaleString();

            let sold = 0;
            let views = 0;
            (rows || []).forEach(function(row) {
                sold += parseFloat(row.A_L30) || 0;
                views += parseFloat(row.Sess30) || 0;
            });
            const avgCvr = views > 0 ? (sold / views) * 100 : 0;
            const cvrEl = document.getElementById('amz-cvr-avg-cvr-badge');
            if (cvrEl) {
                cvrEl.textContent = 'CVR: ' + avgCvr.toFixed(1) + '%';
                amzCvrApplyBadgeBg(cvrEl, avgCvr);
            }
        }

        function amzCvrRowCvrL30(row) {
            const aL30 = parseFloat(row['A_L30']) || 0;
            const sess30 = parseFloat(row['Sess30']) || 0;
            return sess30 <= 0 ? 0 : (aL30 / sess30) * 100;
        }

        /**
         * CVR badge BG class — 3 colors as per Analytics Amz CVR L30 graph rule:
         * Red ≤4%, Yellow 4–7%, Green >7%.
         */
        function amzCvrBadgeBgClass(cvr) {
            const v = parseFloat(cvr);
            if (!isFinite(v) || v <= 4) return 'cvr-bg-red';
            if (v <= 7) return 'cvr-bg-yellow';
            return 'cvr-bg-green';
        }

        function amzCvrApplyBadgeBg(el, cvr) {
            if (!el) return;
            el.classList.remove('cvr-bg-red', 'cvr-bg-yellow', 'cvr-bg-green');
            el.classList.add(amzCvrBadgeBgClass(cvr));
        }

        function amzCvrIsZero(cvr) {
            return !isFinite(cvr) || Math.abs(cvr) < 0.005;
        }

        /** Same slab bands as Analytics Amz (low 3.5 / mid 7 / high 13). */
        function amzCvrSlab(cvr) {
            const v = parseFloat(cvr) || 0;
            const low = 3.5, mid = 7, high = 13;
            const pinkAfter = high + 0.01;
            if (v <= low) return 'red'; // yellow band 0.01–3.5
            if (v <= mid) return 'blue';
            if (v <= pinkAfter) return 'green';
            return 'pink';
        }

        function amzCvrMatchesCvrFilter(row, cvrFilter) {
            if (!cvrFilter || cvrFilter === 'all') return true;
            const cvr = amzCvrRowCvrL30(row);
            const isZero = amzCvrIsZero(cvr);
            if (cvrFilter === 'zero' || cvrFilter === '0-0') return isZero;
            if (isZero) return false;
            let key = cvrFilter;
            if (key === '0-3') key = 'yellow';
            else if (key === '3-7') key = 'blue';
            else if (key === '7-13') key = 'green';
            else if (key === '13plus') key = 'pink';
            const slab = amzCvrSlab(cvr);
            if (key === 'yellow') return slab === 'red';
            if (key === 'blue' || key === 'green' || key === 'pink') return slab === key;
            return true;
        }

        function amzCvrMatchesViewsFilter(row, viewsFilter) {
            if (!viewsFilter || viewsFilter === 'all') return true;
            const views = Math.round(parseFloat(row.Sess30) || 0);
            if (viewsFilter === 'zero') return views === 0;
            if (viewsFilter === '1-70') return views >= 1 && views <= 70;
            if (viewsFilter === '71-300') return views >= 71 && views <= 300;
            if (viewsFilter === 'gt300') return views > 300;
            return true;
        }

        function amzCvrSetOptionLabel(selectEl, value, label, count) {
            if (!selectEl) return;
            const opt = selectEl.querySelector('option[value="' + value + '"]');
            if (!opt) return;
            opt.textContent = label + ' (' + Number(count || 0).toLocaleString() + ')';
        }

        /** Refresh CVR% / Views option labels with (count) from full dataset */
        function updateAmzCvrFilterOptionCounts() {
            if (!amz_cvr_issues) return;
            let rows = [];
            try {
                rows = amz_cvr_issues.getData('all') || [];
            } catch (e) {
                rows = amz_cvr_issues.getData() || [];
            }

            const cvrCounts = { all: rows.length, zero: 0, yellow: 0, blue: 0, green: 0, pink: 0 };
            const viewsCounts = { all: rows.length, zero: 0, '1-70': 0, '71-300': 0, gt300: 0 };

            rows.forEach(function(row) {
                const cvr = amzCvrRowCvrL30(row);
                if (amzCvrIsZero(cvr)) {
                    cvrCounts.zero++;
                } else {
                    const slab = amzCvrSlab(cvr);
                    if (slab === 'red') cvrCounts.yellow++;
                    else if (slab === 'blue') cvrCounts.blue++;
                    else if (slab === 'green') cvrCounts.green++;
                    else if (slab === 'pink') cvrCounts.pink++;
                }

                const views = Math.round(parseFloat(row.Sess30) || 0);
                if (views === 0) viewsCounts.zero++;
                else if (views >= 1 && views <= 70) viewsCounts['1-70']++;
                else if (views >= 71 && views <= 300) viewsCounts['71-300']++;
                else if (views > 300) viewsCounts.gt300++;
            });

            const cvrSel = document.getElementById('amz-cvr-cvr-filter');
            amzCvrSetOptionLabel(cvrSel, 'all', 'CVR%', cvrCounts.all);
            amzCvrSetOptionLabel(cvrSel, 'zero', '0%', cvrCounts.zero);
            amzCvrSetOptionLabel(cvrSel, 'yellow', '0.01–3.5%', cvrCounts.yellow);
            amzCvrSetOptionLabel(cvrSel, 'blue', '3.51–7%', cvrCounts.blue);
            amzCvrSetOptionLabel(cvrSel, 'green', '7.01–13%', cvrCounts.green);
            amzCvrSetOptionLabel(cvrSel, 'pink', '>13.01%', cvrCounts.pink);

            const viewsSel = document.getElementById('amz-cvr-views-filter');
            amzCvrSetOptionLabel(viewsSel, 'all', 'Views', viewsCounts.all);
            amzCvrSetOptionLabel(viewsSel, 'zero', '0', viewsCounts.zero);
            amzCvrSetOptionLabel(viewsSel, '1-70', '1 to 70', viewsCounts['1-70']);
            amzCvrSetOptionLabel(viewsSel, '71-300', '71 to 300', viewsCounts['71-300']);
            amzCvrSetOptionLabel(viewsSel, 'gt300', '> 300', viewsCounts.gt300);

            refreshAmzCvrHistoryDateFilterOptions(rows);
        }

        function amzCvrSortHistoryLatestFirst(hist) {
            const list = Array.isArray(hist) ? hist.slice() : [];
            list.sort(function(a, b) {
                const tb = parseInt(b && b.sort_ts, 10) || 0;
                const ta = parseInt(a && a.sort_ts, 10) || 0;
                if (tb !== ta) return tb - ta;
                return (parseInt(b && b.id, 10) || 0) - (parseInt(a && a.id, 10) || 0);
            });
            return list;
        }

        function amzCvrHistoryEntriesForFilter(row, dateFilter) {
            const hist = amzCvrSortHistoryLatestFirst(row && row.audit_history);
            if (!dateFilter || dateFilter === 'all') return hist;
            if (dateFilter === 'none') return [];
            return hist.filter(function(h) { return String(h.date_key || '') === String(dateFilter); });
        }

        function amzCvrMatchesHistoryDateFilter(row, dateFilter) {
            if (!dateFilter || dateFilter === 'all') return true;
            const hist = Array.isArray(row.audit_history) ? row.audit_history : [];
            if (dateFilter === 'none') return hist.length === 0;
            return hist.some(function(h) { return String(h.date_key || '') === String(dateFilter); });
        }

        function refreshAmzCvrHistoryDateFilterOptions(rows) {
            const sel = document.getElementById('amz-cvr-history-date-filter');
            if (!sel) return;
            const prev = amzCvrSavedHistoryDate || sel.value || 'all';
            const dateMap = {};
            let noneCount = 0;
            (rows || []).forEach(function(row) {
                const hist = Array.isArray(row.audit_history) ? row.audit_history : [];
                if (!hist.length) {
                    noneCount++;
                    return;
                }
                const seen = {};
                hist.forEach(function(h) {
                    const key = String(h.date_key || '');
                    if (!key || seen[key]) return;
                    seen[key] = true;
                    if (!dateMap[key]) {
                        dateMap[key] = {
                            key: key,
                            label: h.date_label || key,
                            sort_ts: parseInt(h.sort_ts, 10) || 0,
                            count: 0
                        };
                    }
                    dateMap[key].count++;
                    dateMap[key].sort_ts = Math.max(dateMap[key].sort_ts, parseInt(h.sort_ts, 10) || 0);
                });
            });

            const dates = Object.values(dateMap).sort(function(a, b) {
                return (b.sort_ts || 0) - (a.sort_ts || 0);
            });

            let html = '<option value="all">History date (' + rows.length + ')</option>'
                + '<option value="none">No history (' + noneCount + ')</option>';
            dates.forEach(function(d) {
                html += '<option value="' + amzCvrEsc(d.key) + '">'
                    + amzCvrEsc(d.label) + ' (' + d.count + ')</option>';
            });
            sel.innerHTML = html;
            if (prev === 'all' || prev === 'none' || dateMap[prev]) {
                sel.value = prev;
                if (amzCvrSavedHistoryDate && String(amzCvrSavedHistoryDate) === String(prev)) {
                    amzCvrSavedHistoryDate = null;
                }
            } else {
                sel.value = 'all';
            }
        }

        function applyAmzCvrFilters(opts) {
            opts = opts || {};
            if (!amz_cvr_issues) return;
            const cvrFilter = (document.getElementById('amz-cvr-cvr-filter') || {}).value || 'all';
            const viewsFilter = (document.getElementById('amz-cvr-views-filter') || {}).value || 'all';
            const historyDateFilter = (document.getElementById('amz-cvr-history-date-filter') || {}).value || 'all';
            const skuSearch = String((document.getElementById('amz-cvr-sku-search') || {}).value || '')
                .trim()
                .toLowerCase();
            amz_cvr_issues.clearFilter();
            if (amzCvrMlFilterActive) {
                amz_cvr_issues.addFilter(function(data) {
                    return isAmzCvrMissingL(data);
                });
            }
            if (cvrFilter !== 'all') {
                amz_cvr_issues.addFilter(function(data) {
                    return amzCvrMatchesCvrFilter(data, cvrFilter);
                });
            }
            if (viewsFilter !== 'all') {
                amz_cvr_issues.addFilter(function(data) {
                    return amzCvrMatchesViewsFilter(data, viewsFilter);
                });
            }
            if (historyDateFilter !== 'all') {
                amz_cvr_issues.addFilter(function(data) {
                    return amzCvrMatchesHistoryDateFilter(data, historyDateFilter);
                });
            }
            if (skuSearch) {
                amz_cvr_issues.addFilter(function(data) {
                    const sku = String(data['(Child) sku'] || '').toLowerCase();
                    return sku.indexOf(skuSearch) !== -1;
                });
            }
            // Always keep latest history rows on top.
            amz_cvr_issues.setSort([
                { column: 'audit_history_ts', dir: 'desc' },
                { column: 'CVR_L30', dir: 'asc' }
            ]);
            updateAmzCvrMlBadge();
            updateAmzCvrRowsBadge();
            if (opts.persist) saveAmzCvrFilterState();
        }

        function applyAmzCvrMlFilter() {
            applyAmzCvrFilters();
        }

        function amzCvrCsvEscape(val) {
            const s = String(val == null ? '' : val);
            if (/[",\n\r]/.test(s)) {
                return '"' + s.replace(/"/g, '""') + '"';
            }
            return s;
        }

        function amzCvrExportRow(row) {
            const inv = parseFloat(row.INV) || 0;
            const ovL30 = parseFloat(row.L30) || 0;
            const dil = inv > 0 ? (ovL30 / inv) * 100 : 0;
            const sold = parseFloat(row.A_L30) || 0;
            const views = parseFloat(row.Sess30) || 0;
            const cvr = views > 0 ? (sold / views) * 100 : 0;
            const price = parseFloat(row.price) || 0;
            const lmp = parseFloat(row.lmp_price) || 0;
            const groi = row['GROI%'];
            const rating = row.amz_avg_rating;
            const reviewCount = parseInt(row.amz_review_count, 10) || 0;
            const hist = amzCvrSortHistoryLatestFirst(row.audit_history);
            const latest = hist.length ? hist[0] : null;

            return {
                Parent: row.Parent || '',
                SKU: row['(Child) sku'] || '',
                INV: Math.round(inv),
                'OV L30': Math.round(ovL30),
                'Dil%': Math.round(dil),
                NR: row.NR || '',
                'Missing Listing': isAmzCvrMissingL(row) ? 'M' : '',
                Views: Math.round(views),
                'Views L7': Math.round(parseFloat(row.Sess7) || 0),
                Sold: Math.round(sold),
                'CVR L30%': cvr.toFixed(2),
                Rating: (rating !== null && rating !== undefined && rating !== '' && parseFloat(rating) > 0)
                    ? parseFloat(rating).toFixed(1)
                    : '',
                Reviews: reviewCount || '',
                Price: price > 0 ? price.toFixed(2) : '',
                LMP: lmp > 0 ? lmp.toFixed(2) : '',
                'GROI%': (groi === null || groi === undefined || groi === '')
                    ? ''
                    : (parseFloat(groi) || 0).toFixed(0),
                'Latest Audit User': latest ? (latest.user || '') : '',
                'Latest Audit Date': latest ? (latest.date_label || '') : '',
                'Latest Audit Tasks': latest ? (parseInt(latest.task_count, 10) || 0) : '',
                'Latest Audit CVR%': (latest && latest.cvr_l30 != null && latest.cvr_l30 !== '')
                    ? (parseFloat(latest.cvr_l30) || 0).toFixed(2)
                    : ''
            };
        }

        function exportAmzCvrIssues(mode) {
            if (!amz_cvr_issues) {
                alert('Table is not ready yet.');
                return;
            }

            let rows = [];
            try {
                rows = mode === 'all'
                    ? (amz_cvr_issues.getData('all') || amz_cvr_issues.getData() || [])
                    : (amz_cvr_issues.getData('active') || []);
            } catch (e) {
                rows = amz_cvr_issues.getData() || [];
            }

            if (!rows.length) {
                alert(mode === 'all' ? 'No data to export.' : 'No filtered rows to export.');
                return;
            }

            const mapped = rows.map(amzCvrExportRow);
            const headers = Object.keys(mapped[0]);
            const lines = [headers.map(amzCvrCsvEscape).join(',')];
            mapped.forEach(function(r) {
                lines.push(headers.map(function(h) { return amzCvrCsvEscape(r[h]); }).join(','));
            });

            const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            const filename = (mode === 'all'
                ? 'amz_cvr_issues_all_'
                : 'amz_cvr_issues_filtered_') + stamp + '.csv';

            const blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        function openAmzCvrLmpModal(sku, options) {
            options = options || {};
            amzCvrLmpCurrentSku = sku;
            document.getElementById('amzCvrLmpSku').textContent = sku;
            document.getElementById('amzCvrAddCompSku').value = sku;
            document.getElementById('amzCvrAddCompAsin').value = '';
            document.getElementById('amzCvrAddCompPrice').value = '';
            document.getElementById('amzCvrAddCompLink').value = '';

            const modalEl = document.getElementById('amzCvrLmpModal');
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
            loadAmzCvrCompetitors(sku, !!options.refresh);
        }

        function loadAmzCvrCompetitors(sku, refresh) {
            const listEl = document.getElementById('amzCvrLmpDataList');
            listEl.innerHTML = '<div class="text-center py-5 text-muted"><div class="spinner-border text-primary me-2"></div>'
                + (refresh ? 'Pulling live prices...' : 'Loading competitors...') + '</div>';

            const params = new URLSearchParams({ sku: sku });
            if (refresh) params.set('refresh', '1');

            fetch(AMZ_CVR_COMPETITORS_URL + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    const comps = (res.success && Array.isArray(res.competitors)) ? res.competitors : [];
                    amzCvrCurrentCompetitors = comps;
                    renderAmzCvrCompetitors(comps, res.lowest_price);
                    if (refresh && res.success && res.lowest_price != null && amz_cvr_issues) {
                        amz_cvr_issues.getRows().forEach(function(row) {
                            if (row.getData()['(Child) sku'] === sku) {
                                row.update({
                                    lmp_price: res.lowest_price,
                                    lmp_entries_total: comps.length
                                });
                            }
                        });
                    }
                })
                .catch(function() {
                    listEl.innerHTML = '<div class="alert alert-danger mb-0"><i class="fa fa-exclamation-triangle"></i> Could not load competitors.</div>';
                })
                .finally(function() {
                    const btn = document.getElementById('amzCvrLmpPullBtn');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-cloud-download-alt"></i> Pull';
                    }
                });
        }

        function getAmzCvrLmpOurListing(sku) {
            let row = null;
            if (amz_cvr_issues && sku) {
                const match = amz_cvr_issues.getRows().find(function(r) {
                    return (r.getData()['(Child) sku'] || '') === sku;
                });
                row = match ? match.getData() : null;
            }
            if (!row) {
                return { price: 0, sku: '', image: '', title: '', link: '', sold: 0, rating: null, reviews: null };
            }
            const asin = row.asin ? String(row.asin).trim() : '';
            return {
                price: parseFloat(row.price) || 0,
                asin: asin,
                image: row.image_path || '',
                title: '5 Core — ' + (sku || row['(Child) sku'] || ''),
                link: asin ? ('https://www.amazon.com/dp/' + asin) : '',
                sold: Math.round(parseFloat(row.A_L30) || 0),
                rating: row.amz_avg_rating != null ? parseFloat(row.amz_avg_rating) : null,
                reviews: row.amz_review_count != null ? parseInt(row.amz_review_count, 10) || 0 : null,
            };
        }

        function buildAmzCvrLmpFiveCoreRowHtml(our) {
            const price = parseFloat(our.price) || 0;
            if (price <= 0) return '';
            const img = our.image
                ? '<img src="' + amzCvrEsc(our.image) + '" alt="" style="width:40px;height:40px;object-fit:contain;border-radius:4px;" loading="lazy">'
                : '<span class="text-muted"><i class="fas fa-store"></i></span>';
            const linkBtn = our.link
                ? '<a href="' + amzCvrEsc(our.link) + '" target="_blank" rel="noopener" class="btn btn-sm btn-primary" title="Open our Amz listing"><i class="fa fa-external-link"></i></a>'
                : '<span class="text-muted small">—</span>';
            const rating = (our.rating != null && our.rating > 0)
                ? '<span style="color:#ffc107;">' + our.rating.toFixed(1) + ' <i class="fa fa-star"></i></span>'
                : '<span class="text-muted">—</span>';
            const reviews = (our.reviews != null)
                ? Number(our.reviews).toLocaleString()
                : '<span class="text-muted">—</span>';
            const sold = '<span style="color:#007bff;font-weight:600;">' + (our.sold || 0).toLocaleString() + '</span>';

            return ''
                + '<tr class="lmp-five-core-row" title="Our 5 Core listing — sorted by price to show market level">'
                +   '<td class="text-center"><span class="badge bg-primary">★</span></td>'
                +   '<td class="text-center">' + img + '</td>'
                +   '<td><span class="text-primary fw-semibold" style="font-size:11px;">' + amzCvrEsc(our.asin || '—') + '</span></td>'
                +   '<td style="font-size:11px;" title="' + amzCvrEsc(our.title) + '">' + amzCvrEsc(our.title) + '</td>'
                +   '<td><span class="badge bg-primary">Ours</span></td>'
                +   '<td>'
                +     '<strong class="lmp-five-core-price">$' + price.toFixed(2) + '</strong>'
                +     ' <span class="badge bg-primary ms-1">5 CORE</span>'
                +   '</td>'
                +   '<td class="text-center">' + sold + '</td>'
                +   '<td class="text-center">' + rating + '</td>'
                +   '<td class="text-center">' + reviews + '</td>'
                +   '<td class="text-center"><span class="badge bg-info text-dark">Ours</span></td>'
                +   '<td class="text-center">' + linkBtn + '</td>'
                +   '<td class="text-center text-muted small">—</td>'
                +   '<td class="text-center text-muted small">—</td>'
                + '</tr>';
        }

        function renderAmzCvrCompetitors(competitors, lowestPrice) {
            competitors = Array.isArray(competitors) ? competitors : [];
            amzCvrCurrentCompetitors = competitors;
            const lowest = (window.LmpIgnore && LmpIgnore.l1)
                ? (LmpIgnore.l1(competitors, 'price') || 0)
                : (parseFloat(lowestPrice) || 0);
            const our = getAmzCvrLmpOurListing(amzCvrLmpCurrentSku);
            const fiveCoreHtml = buildAmzCvrLmpFiveCoreRowHtml(our);

            if (!competitors.length && !fiveCoreHtml) {
                document.getElementById('amzCvrLmpDataList').innerHTML =
                    '<div class="alert alert-warning mb-0"><i class="fa fa-info-circle"></i> No competitors found yet. Add one above.</div>';
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-hover table-bordered table-sm mb-0">';
            html += '<thead class="table-dark"><tr>'
                + '<th>#</th><th>Image</th><th>ASIN</th><th>Title</th><th>Seller</th>'
                + '<th>Price</th><th title="Competitor monthly units sold">L30</th><th>Rating</th><th>Reviews</th><th>Delivery</th><th>Link</th>'
                + LmpIgnore.header()
                + '<th></th>'
                + '</tr></thead><tbody>';

            const rows = competitors.map(function(item, index) {
                const price = parseFloat(item.price) || 0;
                const ignored = !!item.ignored;
                const isLowest = !ignored && lowest > 0 && Math.abs(price - lowest) < 0.01;
                const rowClass = (ignored ? 'lmp-ignored-row ' : '') + (isLowest ? 'table-success' : '');
                const img = item.image
                    ? '<img src="' + amzCvrEsc(item.image) + '" style="width:40px;height:40px;object-fit:contain;" alt="">'
                    : '<span class="text-muted">—</span>';
                const title = String(item.product_title || item.title || 'N/A');
                const link = item.product_link || item.link || '#';
                const rating = item.rating != null
                    ? '<span style="color:#ffc107;">' + parseFloat(item.rating).toFixed(1) + ' <i class="fa fa-star"></i></span>'
                    : '<span class="text-muted">—</span>';
                const reviews = item.reviews != null
                    ? Number(item.reviews).toLocaleString()
                    : '<span class="text-muted">—</span>';
                const soldQty = item.monthly_units_sold != null && item.monthly_units_sold !== ''
                    ? parseInt(item.monthly_units_sold, 10)
                    : null;
                const sold = (soldQty != null && !isNaN(soldQty))
                    ? '<span style="color:#007bff;font-weight:600;" title="Competitor monthly units sold">'
                        + soldQty.toLocaleString() + '</span>'
                    : '<span class="text-muted">—</span>';
                let delivery = '<span class="text-muted">—</span>';
                if (item.delivery) {
                    const delText = String(item.delivery);
                    if (/\bfree\b/i.test(delText)) {
                        delivery = '<span class="badge bg-info text-dark" title="'
                            + amzCvrEsc(delText) + '">FREE</span>';
                    } else {
                        delivery = '<span title="' + amzCvrEsc(delText) + '">'
                            + amzCvrEsc(delText.substring(0, 40))
                            + (delText.length > 40 ? '…' : '')
                            + '</span>';
                    }
                }
                const priceBadge = isLowest
                    ? '<strong>$' + price.toFixed(2) + '</strong> <span class="badge bg-success ms-1">L1</span>'
                    : (ignored ? '<strong>$' + price.toFixed(2) + '</strong> <span class="badge bg-secondary ms-1">Ignored</span>' : '<strong>$' + price.toFixed(2) + '</strong>');

                const rowHtml = ''
                    + '<tr class="' + rowClass + '">'
                    +   '<td class="text-center">' + (index + 1) + '</td>'
                    +   '<td class="text-center">' + img + '</td>'
                    +   '<td><span class="text-primary fw-semibold" style="font-size:11px;">' + amzCvrEsc(item.asin || 'N/A') + '</span></td>'
                    +   '<td style="font-size:11px;" title="' + amzCvrEsc(title) + '">' + amzCvrEsc(title.substring(0, 60)) + (title.length > 60 ? '…' : '') + '</td>'
                    +   '<td style="font-size:11px;">' + amzCvrEsc(item.seller_name || '—') + '</td>'
                    +   '<td>' + priceBadge + '</td>'
                    +   '<td class="text-center">' + sold + '</td>'
                    +   '<td class="text-center">' + rating + '</td>'
                    +   '<td class="text-center">' + reviews + '</td>'
                    +   '<td class="text-center" style="font-size:11px;">' + delivery + '</td>'
                    +   '<td class="text-center"><a href="' + amzCvrEsc(link) + '" target="_blank" rel="noopener" class="btn btn-sm btn-info"><i class="fa fa-external-link"></i></a></td>'
                    +   '<td class="text-center align-middle">' + LmpIgnore.checkbox(item, 'amazon', amzCvrLmpCurrentSku || '') + '</td>'
                    +   '<td class="text-center"><button type="button" class="btn btn-sm btn-danger amz-cvr-del-lmp" data-id="'
                    +     amzCvrEsc(item.id) + '" title="Delete"><i class="fa fa-trash"></i></button></td>'
                    + '</tr>';
                return { price: price, html: rowHtml };
            });

            // Insert 5 Core / Ours row at our price position (same as ebay-tabulator-view)
            let fiveCoreInserted = false;
            rows.forEach(function(row) {
                if (!fiveCoreInserted && fiveCoreHtml && our.price > 0 && row.price >= our.price) {
                    html += fiveCoreHtml;
                    fiveCoreInserted = true;
                }
                html += row.html;
            });
            if (!fiveCoreInserted && fiveCoreHtml) {
                html += fiveCoreHtml;
            }

            html += '</tbody></table></div>';
            if (lowest > 0) {
                html = '<div class="mb-2 small text-muted">L1 (lowest non-ignored): <strong>$'
                    + lowest.toFixed(2) + '</strong></div>' + html;
            }
            document.getElementById('amzCvrLmpDataList').innerHTML = html;
        }
        LmpIgnore.bind({
            modal: '#amzCvrLmpModal',
            marketplace: 'amazon',
            sku: function() { return amzCvrLmpCurrentSku; },
            onToggled: function(id, ignored) {
                amzCvrCurrentCompetitors.forEach(function(c) {
                    if (String(c.id) === String(id)) c.ignored = ignored;
                });
                renderAmzCvrCompetitors(amzCvrCurrentCompetitors, LmpIgnore.l1(amzCvrCurrentCompetitors, 'price'));
            }
        });

        function updateAmzCvrIssuesSelected() {
            const n = document.querySelectorAll('#amz_cvr_issues .row-select-checkbox:checked').length;
            const el = document.getElementById('amz-cvr-issues-selected');
            if (el) el.textContent = 'Selected: ' + n;
            syncAmzCvrAuditBulkUi();
        }

        function getAmzCvrSelectedSkus() {
            const skus = [];
            const seen = {};
            document.querySelectorAll('#amz_cvr_issues .row-select-checkbox:checked').forEach(function(cb) {
                const sku = String(cb.getAttribute('data-sku') || '').trim();
                if (!sku || seen[sku]) return;
                seen[sku] = true;
                skus.push(sku);
            });
            return skus;
        }

        function getAmzCvrAuditTargetSkus() {
            const selected = getAmzCvrSelectedSkus();
            if (selected.length) return selected;
            const current = (document.getElementById('amzCvrAuditSkuInput')?.value || '').trim();
            return current ? [current] : [];
        }

        function syncAmzCvrAuditBulkUi() {
            const modal = document.getElementById('amzCvrAuditModal');
            if (!modal || !modal.classList.contains('show')) return;
            const targets = getAmzCvrAuditTargetSkus();
            const note = document.getElementById('amzCvrAuditBulkNote');
            const label = document.getElementById('amzCvrAuditSubmitLabel');
            const current = (document.getElementById('amzCvrAuditSkuInput')?.value || '').trim();
            if (note) {
                if (targets.length > 1) {
                    note.classList.remove('d-none');
                    note.innerHTML = '<i class="fas fa-layer-group me-1"></i>Bulk submit will apply to <strong>'
                        + targets.length + '</strong> selected SKUs'
                        + (current ? (' (opened on ' + amzCvrEsc(current) + ')') : '') + '.';
                } else {
                    note.classList.add('d-none');
                    note.textContent = '';
                }
            }
            if (label) {
                label.textContent = targets.length > 1
                    ? ('Submit (' + targets.length + ' SKUs)')
                    : 'Submit';
            }
        }

        function amzCvrTaskTitleForSku(templateTitle, sourceSku, targetSku, issueLabel) {
            const t = String(templateTitle || '').trim();
            const src = String(sourceSku || '').trim();
            const tgt = String(targetSku || '').trim();
            if (!tgt) return t;
            if (src && t.indexOf(src) !== -1) {
                return t.split(src).join(tgt);
            }
            return defaultAmzCvrTaskTitle(tgt, issueLabel || '');
        }

        function applyAmzCvrHistoryToRow(sku, history) {
            if (!amz_cvr_issues || !history || !sku) return;
            const rows = amz_cvr_issues.getRows();
            for (let i = 0; i < rows.length; i++) {
                const rd = rows[i].getData();
                if ((rd['(Child) sku'] || '') !== sku) continue;
                const hist = Array.isArray(rd.audit_history) ? rd.audit_history.slice() : [];
                hist.unshift(history);
                const sorted = amzCvrSortHistoryLatestFirst(hist).slice(0, 10);
                const latest = sorted[0] || history;
                const dates = [];
                sorted.forEach(function(h) {
                    if (h.date_key && dates.indexOf(h.date_key) === -1) dates.push(h.date_key);
                });
                rows[i].update({
                    audit_history: sorted,
                    audit_history_latest: latest,
                    audit_history_ts: parseInt(latest.sort_ts, 10) || 0,
                    audit_history_dates: dates
                });
                break;
            }
        }

        function getAmzCvrSkuCvrL30(sku) {
            if (!amz_cvr_issues || !sku) return null;
            try {
                const rows = amz_cvr_issues.getRows();
                for (let i = 0; i < rows.length; i++) {
                    const rd = rows[i].getData();
                    if ((rd['(Child) sku'] || '') === sku) {
                        return Math.round(amzCvrRowCvrL30(rd) * 100) / 100;
                    }
                }
            } catch (e) { /* ignore */ }
            return null;
        }

        function storeAmzCvrAuditHistory(sku, taskCount) {
            const body = new FormData();
            body.append('_token', AMZ_CVR_CSRF);
            body.append('sku', sku);
            body.append('task_count', String(taskCount));
            const cvr = getAmzCvrSkuCvrL30(sku);
            if (cvr !== null && isFinite(cvr)) {
                body.append('cvr_l30', String(cvr));
            }
            return fetch(AMZ_CVR_HISTORY_STORE_URL, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': AMZ_CVR_CSRF
                },
                body: body,
                credentials: 'same-origin'
            })
                .then(function(res) { return res.json().catch(function() { return null; }); })
                .then(function(data) {
                    if (data && data.history) {
                        applyAmzCvrHistoryToRow(sku, data.history);
                    }
                    return data;
                })
                .catch(function() { return null; });
        }

        function buildAmzCvrIssuesColumns() {
            return [
                {
                    title: "<div style='transform: rotate(0deg) !important; display: flex; justify-content: center; align-items: center;'><input type='checkbox' id='amz-cvr-issues-select-all' title='Select all filtered rows' style='transform: rotate(0deg) !important; width: 16px; height: 16px; cursor: pointer;'></div>",
                    field: 'row_select',
                    hozAlign: 'center',
                    headerSort: false,
                    headerVertical: false,
                    width: 40,
                    frozen: true,
                    formatter: function(cell) {
                        var row = cell.getRow().getData();
                        var sku = row['(Child) sku'] || '';
                        return "<input type='checkbox' class='row-select-checkbox' data-sku='" + amzCvrEsc(sku) + "' style='width: 16px; height: 16px; cursor: pointer;'>";
                    },
                    cellClick: function(e) {
                        e.stopPropagation();
                    }
                },
                {
                    title: 'Image',
                    field: 'image_path',
                    hozAlign: 'center',
                    headerSort: false,
                    width: 80,
                    frozen: true,
                    formatter: function(cell) {
                        const imagePath = cell.getValue();
                        if (!imagePath) return '';
                        const u = String(imagePath).replace(/"/g, '&quot;');
                        return '<img src="' + u + '" class="hover-thumb" alt="" />';
                    }
                },
                {
                    title: 'Parent',
                    field: 'Parent',
                    headerFilter: 'input',
                    headerFilterPlaceholder: 'Search Parent...',
                    cssClass: 'text-primary',
                    tooltip: true,
                    frozen: true,
                    width: 120,
                    formatter: function(cell) {
                        var val = cell.getValue();
                        var s = (val != null && val !== '') ? String(val).trim() : '';
                        return s || '—';
                    }
                },
                {
                    title: 'SKU',
                    field: '(Child) sku',
                    headerFilter: 'input',
                    headerFilterPlaceholder: 'Search SKU...',
                    frozen: true,
                    width: 200,
                    formatter: function(cell) {
                        const sku = cell.getValue() || '';
                        return '<div style="display: flex; align-items: center; gap: 5px;">'
                            + '<span>' + amzCvrEsc(sku) + '</span>'
                            + '<button type="button" class="btn btn-sm btn-link copy-sku-btn p-0" data-sku="' + amzCvrEsc(sku) + '" title="Copy SKU">'
                            + '<i class="fas fa-copy"></i></button></div>';
                    }
                },
                {
                    title: 'INV',
                    field: 'INV',
                    hozAlign: 'center',
                    sorter: 'number',
                    width: 55,
                    headerTooltip: 'Shopify inventory',
                    formatter: function(cell) {
                        const num = Math.round(parseFloat(cell.getValue()) || 0);
                        if (num === 0) {
                            return '<span style="color: #dc3545; font-weight: 600;">0</span>';
                        }
                        return '<span style="font-weight: 600;">' + num.toLocaleString('en-US') + '</span>';
                    }
                },
                {
                    title: 'OV L30',
                    field: 'L30',
                    hozAlign: 'center',
                    sorter: 'number',
                    width: 65,
                    headerTooltip: 'Overall sold L30 (Shopify quantity)',
                    formatter: function(cell) {
                        return Math.round(parseFloat(cell.getValue()) || 0).toLocaleString('en-US');
                    }
                },
                {
                    title: 'Dil',
                    field: 'E Dil%',
                    hozAlign: 'center',
                    sorter: 'number',
                    width: 55,
                    headerTooltip: 'Dil% = OV L30 / INV × 100',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const inv = parseFloat(row.INV) || 0;
                        const ovL30 = parseFloat(row.L30) || 0;
                        if (inv === 0) return '<span style="color: #6c757d;">0%</span>';
                        const dil = (ovL30 / inv) * 100;
                        let color = '#e83e8c';
                        if (dil < 16.66) color = '#a00211';
                        else if (dil < 25) color = '#ffc107';
                        else if (dil < 50) color = '#28a745';
                        return '<span style="color: ' + color + '; font-weight: 600;">' + Math.round(dil) + '%</span>';
                    }
                },
                {
                    title: 'Missing Listing',
                    field: 'is_missing',
                    hozAlign: 'center',
                    headerSort: false,
                    width: 85,
                    headerTooltip: 'Missing Listing — INV&gt;0, REQ, price ≤ 0 (same as ML badge)',
                    formatter: function(cell) {
                        if (isAmzCvrMissingL(cell.getRow().getData())) {
                            return '<span style="font-size: 16px; color: #dc3545; font-weight: bold;">M</span>';
                        }
                        return '';
                    }
                },
                {
                    title: 'Views',
                    field: 'Sess30',
                    hozAlign: 'center',
                    sorter: 'number',
                    width: 65,
                    headerTooltip: 'Amz sessions L30 (View L30) — red when &lt; 70',
                    formatter: function(cell) {
                        const num = Math.round(cell.getValue() || 0);
                        const text = num.toLocaleString('en-US');
                        if (num < 70) {
                            return '<span style="color: #dc3545; font-weight: 600;">' + text + '</span>';
                        }
                        return text;
                    }
                },
                {
                    title: 'Views L7',
                    field: 'Sess7',
                    hozAlign: 'center',
                    sorter: 'number',
                    width: 70,
                    headerTooltip: 'Amz sessions L7 (View L7)',
                    formatter: function(cell) {
                        return Math.round(cell.getValue() || 0).toLocaleString('en-US');
                    }
                },
                {
                    title: 'Sold',
                    field: 'A_L30',
                    hozAlign: 'center',
                    sorter: 'number',
                    width: 60,
                    headerTooltip: 'Amz units ordered L30 (A L30)',
                    formatter: function(cell) {
                        return Math.round(cell.getValue() || 0).toLocaleString('en-US');
                    }
                },
                {
                    title: 'CVR L30',
                    field: 'CVR_L30',
                    hozAlign: 'center',
                    width: 70,
                    headerTooltip: 'CVR L30 = Sold (A L30) / Views (Sess30) × 100',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const aL30 = parseFloat(row['A_L30']) || 0;
                        const sess30 = parseFloat(row['Sess30']) || 0;
                        if (sess30 === 0) {
                            return '<span style="color: #a00211; font-weight: 600;">0.0%</span>';
                        }
                        const cvr = (aL30 / sess30) * 100;
                        let color = '#e83e8c';
                        if (cvr <= 4) color = '#a00211';
                        else if (cvr <= 7) color = '#ffc107';
                        else if (cvr <= 13) color = '#28a745';
                        return '<span style="color: ' + color + '; font-weight: 600;">' + Math.round(cvr) + '%</span>';
                    },
                    sorter: function(a, b, aRow, bRow) {
                        const calc = function(row) {
                            const aL30 = parseFloat(row['A_L30']) || 0;
                            const sess30 = parseFloat(row['Sess30']) || 0;
                            return sess30 === 0 ? 0 : (aL30 / sess30) * 100;
                        };
                        return calc(aRow.getData()) - calc(bRow.getData());
                    }
                },
                {
                    title: 'Reviews',
                    field: 'amz_avg_rating',
                    hozAlign: 'center',
                    width: 85,
                    headerTooltip: 'Avg rating + review count from amazon:collect-reviews',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const rating = row.amz_avg_rating;
                        const reviews = row.amz_review_count;
                        if (rating === null || rating === undefined || rating === '' || parseFloat(rating) <= 0) {
                            return '<span style="color: #6c757d;">-</span>';
                        }
                        const ratingVal = parseFloat(rating);
                        let ratingColor = '#a00211';
                        if (ratingVal >= 3 && ratingVal <= 3.5) ratingColor = '#ffc107';
                        else if (ratingVal >= 3.51 && ratingVal <= 3.99) ratingColor = '#3591dc';
                        else if (ratingVal >= 4 && ratingVal <= 4.5) ratingColor = '#28a745';
                        else if (ratingVal > 4.5) ratingColor = '#e83e8c';
                        const count = parseInt(reviews, 10) || 0;
                        const reviewColor = count < 4 ? '#a00211' : '#6c757d';
                        const reviewLabel = count === 1 ? '1 review' : (count.toLocaleString() + ' reviews');
                        return '<div style="display: flex; flex-direction: column; align-items: center; gap: 2px;">'
                            + '<span style="color: ' + ratingColor + '; font-weight: 600;"><i class="fa fa-star"></i> ' + ratingVal.toFixed(1) + '</span>'
                            + '<span style="font-size: 11px; color: ' + reviewColor + '; font-weight: 600;">' + reviewLabel + '</span>'
                            + '</div>';
                    },
                    sorter: function(a, b, aRow, bRow) {
                        return (parseFloat(aRow.getData().amz_avg_rating) || 0) - (parseFloat(bRow.getData().amz_avg_rating) || 0);
                    }
                },
                {
                    title: 'Price',
                    field: 'price',
                    hozAlign: 'center',
                    sorter: 'number',
                    width: 70,
                    headerTooltip: 'Amz listing price',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const price = parseFloat(cell.getValue() || 0);
                        const lmpPrice = parseFloat(row.lmp_price || 0);
                        if (price <= 0) {
                            if (lmpPrice > 0) {
                                return '<span style="color: #6c757d; font-style: italic;" title="Reference LMP (no Amz price)">$'
                                    + lmpPrice.toFixed(2) + '</span>';
                            }
                            return '<span style="color: #999;">—</span>';
                        }
                        const formatted = '$' + price.toFixed(2);
                        if (lmpPrice > 0 && price > lmpPrice) {
                            return '<span style="color: #dc3545; font-weight: 600;">' + formatted + '</span>';
                        }
                        return formatted;
                    }
                },
                {
                    title: 'LMP',
                    field: 'lmp_price',
                    hozAlign: 'center',
                    sorter: 'number',
                    width: 90,
                    headerTooltip: 'Lowest marketplace price — click to open competitors modal',
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        if (window.ParentExpand) {
                            const avgHtml = ParentExpand.parentAvgLmpHtml(row, {
                                dataset: typeof allTableData !== 'undefined' ? allTableData : undefined
                            });
                            if (avgHtml !== null) return avgHtml;
                        }
                        const sku = row['(Child) sku'] || '';
                        const lmpPrice = parseFloat(cell.getValue());
                        const total = parseInt(row.lmp_entries_total, 10) || 0;
                        const currentPrice = parseFloat(row.price || 0);
                        let priceHtml = '<span style="color: #999;">N/A</span>';
                        if (lmpPrice > 0) {
                            const priceColor = (currentPrice > 0 && lmpPrice < currentPrice) ? '#dc3545' : '#28a745';
                            priceHtml = '<span style="color: ' + priceColor + '; font-weight: 600;">$'
                                + lmpPrice.toFixed(2) + '</span>';
                        }
                        const viewLabel = total > 0 ? ('View ' + total) : 'View';
                        return '<div class="d-flex flex-column align-items-center gap-1">'
                            + priceHtml
                            + '<a href="#" class="amz-cvr-view-lmp" data-sku="' + amzCvrEsc(sku) + '"'
                            + ' style="color:#007bff;text-decoration:none;font-size:11px;cursor:pointer;">'
                            + '<i class="fa fa-eye"></i> ' + viewLabel + '</a></div>';
                    },
                    cellClick: function(e, cell) {
                        const link = e.target.closest('.amz-cvr-view-lmp');
                        const rowData = cell.getRow().getData() || {};
                        const sku = link
                            ? (link.getAttribute('data-sku') || '')
                            : (rowData['(Child) sku'] || '');
                        if (!sku) return;
                        e.preventDefault();
                        e.stopPropagation();
                        openAmzCvrLmpModal(sku, { row: rowData });
                    }
                },
                {
                    title: 'GROI%',
                    field: 'GROI%',
                    hozAlign: 'center',
                    sorter: 'number',
                    width: 65,
                    headerTooltip: 'GROI% = ((Price × 0.80 − Ship − LP) / LP) × 100',
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) {
                            return '0%';
                        }
                        const percent = parseFloat(value);
                        let color = '#e83e8c';
                        if (percent < 50) color = '#a00211';
                        else if (percent < 75) color = '#ffc107';
                        else if (percent <= 125) color = '#28a745';
                        return '<span style="color: ' + color + '; font-weight: 600;">' + percent.toFixed(0) + '%</span>';
                    }
                },
                {
                    title: 'Audit',
                    field: 'audit',
                    hozAlign: 'center',
                    headerSort: false,
                    width: 70,
                    headerTooltip: 'Open audit modal',
                    formatter: function(cell) {
                        const sku = cell.getRow().getData()['(Child) sku'] || '';
                        return '<button type="button" class="amz-cvr-audit-btn" data-sku="'
                            + amzCvrEsc(sku) + '" title="Open audit"><i class="fa fa-search"></i></button>';
                    },
                    cellClick: function(e, cell) {
                        e.preventDefault();
                        e.stopPropagation();
                        openAmzCvrAuditModal(cell.getRow().getData());
                    }
                },
                {
                    title: 'History',
                    field: 'audit_history_ts',
                    hozAlign: 'left',
                    sorter: 'number',
                    width: 360,
                    minWidth: 320,
                    headerTooltip: 'Audit task history (latest on top): user, date, tasks, CVR at audit',
                    formatter: function(cell) {
                        const row = cell.getRow().getData() || {};
                        const dateFilter = (document.getElementById('amz-cvr-history-date-filter') || {}).value || 'all';
                        const hist = amzCvrHistoryEntriesForFilter(row, dateFilter === 'none' ? 'all' : dateFilter);
                        const show = (dateFilter !== 'all' && dateFilter !== 'none')
                            ? hist
                            : amzCvrSortHistoryLatestFirst(row.audit_history).slice(0, 3);
                        if (!show.length) {
                            return '<span style="color:#999;">—</span>';
                        }
                        const tip = amzCvrSortHistoryLatestFirst(row.audit_history).map(function(h) {
                            const cvrPart = (h.cvr_l30 != null && h.cvr_l30 !== '')
                                ? (' · CVR ' + (parseFloat(h.cvr_l30) || 0).toFixed(1) + '%')
                                : '';
                            return (h.user || '') + ' · ' + (h.date_label || '') + ' · '
                                + (h.task_count || 0) + ' task(s)' + cvrPart;
                        }).join('\n');
                        return '<div class="amz-cvr-history-cell" title="' + amzCvrEsc(tip) + '">'
                            + show.map(function(h, idx) {
                                const count = parseInt(h.task_count, 10) || 0;
                                const cvrVal = (h.cvr_l30 != null && h.cvr_l30 !== '')
                                    ? (parseFloat(h.cvr_l30) || 0)
                                    : null;
                                const cvrBg = cvrVal !== null ? amzCvrBadgeBgClass(cvrVal) : '';
                                return '<div class="amz-cvr-history-line' + (idx ? ' mt-1' : '') + '">'
                                    + '<span class="amz-cvr-history-user">' + amzCvrEsc(h.user || '') + '</span>'
                                    + '<span class="amz-cvr-history-meta">' + amzCvrEsc(h.date_label || '')
                                    + ' · ' + count + ' task' + (count === 1 ? '' : 's') + '</span>'
                                    + (cvrVal !== null
                                        ? '<span class="amz-cvr-history-cvr-badge ' + cvrBg + '"'
                                            + ' data-sku="' + amzCvrEsc(row['(Child) sku'] || '') + '"'
                                            + ' title="Click for rolling CVR graph">CVR: '
                                            + cvrVal.toFixed(1) + '%</span>'
                                        : '')
                                    + '</div>';
                            }).join('')
                            + '</div>';
                    }
                }
            ];
        }

        const AMZ_CVR_TASK_STORE_URL = @json(route('tasks.store'));
        const AMZ_CVR_HISTORY_STORE_URL = @json(route('amz.cvr.issues.history.store'));
        const AMZ_CVR_METRICS_HISTORY_URL = @json(url('/amazon-metrics-history'));
        let amzCvrRollingChart = null;
        let amzCvrRollingChartSku = null;
        let amzCvrRollingChartDays = 30;

        function amzCvrFmtPct(v) {
            const n = Number(v);
            if (!isFinite(n)) return '—';
            return n.toFixed(1) + '%';
        }

        function openAmzCvrRollingChart(sku) {
            amzCvrRollingChartSku = sku ? String(sku).trim() : null;
            amzCvrRollingChartDays = parseInt(document.getElementById('amzCvrRollingChartDays')?.value, 10);
            if (!isFinite(amzCvrRollingChartDays)) amzCvrRollingChartDays = 30;
            const title = document.getElementById('amzCvrRollingChartTitle');
            if (title) {
                title.textContent = amzCvrRollingChartSku
                    ? ('CVR Rolling — ' + amzCvrRollingChartSku)
                    : 'CVR Rolling (visible SKUs)';
            }
            const modalEl = document.getElementById('amzCvrRollingChartModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            loadAmzCvrRollingChart();
        }

        function loadAmzCvrRollingChart() {
            const loading = document.getElementById('amzCvrRollingChartLoading');
            const noData = document.getElementById('amzCvrRollingChartNoData');
            const container = document.getElementById('amzCvrRollingChartContainer');
            if (loading) loading.classList.remove('d-none');
            if (noData) noData.classList.add('d-none');
            if (container) container.style.visibility = 'hidden';

            const params = new URLSearchParams();
            params.set('days', String(amzCvrRollingChartDays || 30));
            if (amzCvrRollingChartSku) {
                params.set('sku', amzCvrRollingChartSku);
            } else if (amz_cvr_issues) {
                let rows = [];
                try {
                    rows = amz_cvr_issues.getData('active') || [];
                } catch (e) {
                    rows = (amz_cvr_issues.getData() || []);
                }
                const skus = [];
                const seen = {};
                rows.forEach(function(r) {
                    const s = String(r['(Child) sku'] || '').trim();
                    if (!s || seen[s]) return;
                    seen[s] = true;
                    skus.push(s);
                });
                if (skus.length) {
                    params.set('skus', JSON.stringify(skus.slice(0, 2000)));
                }
            }

            fetch(AMZ_CVR_METRICS_HISTORY_URL + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (loading) loading.classList.add('d-none');
                    const rows = Array.isArray(data) ? data : [];
                    const points = rows.map(function(d) {
                        const val = amzCvrRollingChartSku
                            ? (Number(d.cvr_percent) || 0)
                            : (Number(d.avg_cvr_percent != null ? d.avg_cvr_percent : d.cvr_percent) || 0);
                        return {
                            label: d.date_formatted || d.date || '',
                            value: val
                        };
                    }).filter(function(p) { return p.label; });

                    if (!points.length) {
                        if (noData) noData.classList.remove('d-none');
                        if (amzCvrRollingChart) {
                            amzCvrRollingChart.destroy();
                            amzCvrRollingChart = null;
                        }
                        document.getElementById('amzCvrChartHighest').textContent = '—';
                        document.getElementById('amzCvrChartMedian').textContent = '—';
                        document.getElementById('amzCvrChartLowest').textContent = '—';
                        return;
                    }

                    if (container) container.style.visibility = 'visible';
                    const values = points.map(function(p) { return p.value; });
                    const sorted = values.slice().sort(function(a, b) { return a - b; });
                    const mid = Math.floor(sorted.length / 2);
                    const median = sorted.length % 2
                        ? sorted[mid]
                        : ((sorted[mid - 1] + sorted[mid]) / 2);
                    const highest = Math.max.apply(null, values);
                    const lowest = Math.min.apply(null, values);
                    document.getElementById('amzCvrChartHighest').textContent = amzCvrFmtPct(highest);
                    document.getElementById('amzCvrChartMedian').textContent = amzCvrFmtPct(median);
                    document.getElementById('amzCvrChartLowest').textContent = amzCvrFmtPct(lowest);

                    if (typeof Highcharts === 'undefined') {
                        if (noData) {
                            noData.textContent = 'Chart library not loaded.';
                            noData.classList.remove('d-none');
                        }
                        return;
                    }

                    if (amzCvrRollingChart) {
                        amzCvrRollingChart.destroy();
                        amzCvrRollingChart = null;
                    }

                    amzCvrRollingChart = Highcharts.chart('amzCvrRollingChartContainer', {
                        chart: { type: 'area', height: 260, spacingTop: 16, backgroundColor: 'transparent' },
                        title: { text: null },
                        credits: { enabled: false },
                        legend: { enabled: false },
                        xAxis: {
                            categories: points.map(function(p) { return p.label; }),
                            labels: { rotation: -45, style: { fontSize: '10px' } }
                        },
                        yAxis: {
                            title: { text: 'CVR %' },
                            labels: { format: '{value}%' },
                            plotLines: [{
                                value: median,
                                color: '#6c757d',
                                dashStyle: 'Dash',
                                width: 1,
                                zIndex: 4,
                                label: { text: 'Median', style: { color: '#6c757d', fontSize: '10px' } }
                            }]
                        },
                        tooltip: {
                            shared: true,
                            formatter: function() {
                                const p = this.points && this.points[0];
                                if (!p) return false;
                                return '<b>' + p.key + '</b><br/>CVR: <b>' + amzCvrFmtPct(p.y) + '</b>';
                            }
                        },
                        plotOptions: {
                            area: {
                                fillOpacity: 0.12,
                                marker: {
                                    enabled: true,
                                    radius: 3,
                                    states: { hover: { radius: 5 } }
                                }
                            }
                        },
                        series: [{
                            name: 'CVR',
                            data: values,
                            color: '#0d6efd',
                            lineWidth: 2
                        }]
                    });
                })
                .catch(function() {
                    if (loading) loading.classList.add('d-none');
                    if (noData) {
                        noData.textContent = 'Could not load rolling CVR data.';
                        noData.classList.remove('d-none');
                    }
                });
        }
        @php
            $amzCvrIssueAssigneeEmails = [
                'pricing' => 'pricing1@5core.com',
                'compliance' => 'mgr-content@5core.com',
                'missing_listing' => 'ecomm6@5core.com',
                'advertisement' => 'mgr-advertisement@5core.com',
            ];
            $amzCvrIssueAssigneeUsers = \App\Models\User::query()
                ->where(function ($q) use ($amzCvrIssueAssigneeEmails) {
                    foreach ($amzCvrIssueAssigneeEmails as $email) {
                        $q->orWhereRaw('LOWER(email) = ?', [strtolower($email)]);
                    }
                })
                ->get(['id', 'name', 'email']);
            $amzCvrIssueAssigneeMap = [];
            foreach ($amzCvrIssueAssigneeEmails as $key => $email) {
                $user = $amzCvrIssueAssigneeUsers->first(
                    fn ($u) => strtolower((string) $u->email) === strtolower($email)
                );
                $amzCvrIssueAssigneeMap[$key] = [
                    'label' => match ($key) {
                        'pricing' => 'Pricing Issue',
                        'compliance' => 'Compliance Issue',
                        'missing_listing' => 'Missing listing Issue',
                        'advertisement' => 'Advertisement Issue',
                        default => ucfirst(str_replace('_', ' ', $key)) . ' Issue',
                    },
                    'email' => $email,
                    'user_id' => $user ? (int) $user->id : null,
                    'name' => $user ? (string) $user->name : null,
                    'custom' => false,
                ];
            }
            foreach (($customIssueTypes ?? []) as $customIssue) {
                $ck = (string) ($customIssue['key'] ?? '');
                if ($ck === '') {
                    continue;
                }
                $amzCvrIssueAssigneeMap[$ck] = [
                    'id' => (int) ($customIssue['id'] ?? 0),
                    'label' => (string) ($customIssue['label'] ?? $ck),
                    'email' => (string) ($customIssue['email'] ?? ''),
                    'user_id' => ! empty($customIssue['user_id']) ? (int) $customIssue['user_id'] : null,
                    'name' => $customIssue['name'] ?? null,
                    'custom' => true,
                ];
            }
        @endphp
        const AMZ_CVR_ISSUE_TYPES_STORE_URL = @json(route('amz.cvr.issues.types.store'));
        const AMZ_CVR_ISSUE_TYPES_DESTROY_URL = @json(url('/amz-cvr-issues/issue-types'));
        let AMZ_CVR_ISSUE_ASSIGNEES = @json($amzCvrIssueAssigneeMap);
        let amzCvrCustomIssues = @json($customIssueTypes ?? []);
        let amzCvrAuditUsers = null;
        let amzCvrActiveAssigneeKey = null;

        function getAmzCvrAuditUsers() {
            if (Array.isArray(amzCvrAuditUsers)) return amzCvrAuditUsers;
            try {
                const el = document.getElementById('quick-assignee-users-data');
                if (el && el.textContent) {
                    amzCvrAuditUsers = JSON.parse(el.textContent) || [];
                } else {
                    amzCvrAuditUsers = [];
                }
            } catch (e) {
                amzCvrAuditUsers = [];
            }
            return amzCvrAuditUsers;
        }

        function registerAmzCvrCustomIssue(issue) {
            if (!issue || !issue.key) return;
            AMZ_CVR_ISSUE_ASSIGNEES[issue.key] = {
                id: issue.id || null,
                label: issue.label,
                email: issue.email || '',
                user_id: issue.user_id || null,
                name: issue.name || null,
                custom: true,
            };
            const exists = amzCvrCustomIssues.some(function(x) { return x.key === issue.key; });
            if (!exists) {
                amzCvrCustomIssues.push(issue);
            } else {
                amzCvrCustomIssues = amzCvrCustomIssues.map(function(x) {
                    return x.key === issue.key ? issue : x;
                });
            }
            renderAmzCvrCustomIssueCheckboxes();
            renderAmzCvrCustomIssueManageList();
            syncAmzCvrIssueUi();
        }

        function unregisterAmzCvrCustomIssue(issueKey) {
            delete AMZ_CVR_ISSUE_ASSIGNEES[issueKey];
            amzCvrCustomIssues = amzCvrCustomIssues.filter(function(x) { return x.key !== issueKey; });
            const cb = document.querySelector('.amz-cvr-issue-opt[value="' + issueKey + '"]');
            if (cb) cb.checked = false;
            renderAmzCvrCustomIssueCheckboxes();
            renderAmzCvrCustomIssueManageList();
            syncAmzCvrIssueUi();
        }

        function renderAmzCvrCustomIssueCheckboxes() {
            const wrap = document.getElementById('amzCvrCustomIssueOptions');
            if (!wrap) return;
            const checked = {};
            wrap.querySelectorAll('.amz-cvr-issue-opt').forEach(function(cb) {
                checked[cb.value] = !!cb.checked;
            });
            wrap.innerHTML = (amzCvrCustomIssues || []).map(function(issue) {
                const id = 'amzCvrIssue_' + String(issue.key).replace(/[^a-zA-Z0-9_]/g, '_');
                const isChecked = checked[issue.key] ? ' checked' : '';
                return ''
                    + '<div class="form-check mb-0 amz-cvr-custom-issue-check" data-issue-id="' + amzCvrEsc(issue.id) + '">'
                    +   '<input class="form-check-input amz-cvr-issue-opt" type="checkbox" value="' + amzCvrEsc(issue.key) + '"'
                    +     ' id="' + amzCvrEsc(id) + '"' + isChecked + '>'
                    +   '<label class="form-check-label" for="' + amzCvrEsc(id) + '">' + amzCvrEsc(issue.label) + '</label>'
                    + '</div>';
            }).join('');
        }

        function renderAmzCvrCustomIssueManageList() {
            const list = document.getElementById('amzCvrCustomIssueList');
            if (!list) return;
            if (!amzCvrCustomIssues.length) {
                list.innerHTML = '<div class="small text-muted">No custom issues yet.</div>';
                return;
            }
            list.innerHTML = '<div class="fw-semibold small mb-1">Saved custom issues</div>'
                + '<div class="list-group list-group-flush">'
                + amzCvrCustomIssues.map(function(issue) {
                    const who = issue.name
                        ? (amzCvrEsc(issue.name) + ' &lt;' + amzCvrEsc(issue.email || '') + '&gt;')
                        : amzCvrEsc(issue.email || 'No assignee');
                    return ''
                        + '<div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-start gap-2">'
                        +   '<div class="small">'
                        +     '<div class="fw-semibold">' + amzCvrEsc(issue.label) + '</div>'
                        +     '<div class="text-muted">' + who + '</div>'
                        +   '</div>'
                        +   '<button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 amz-cvr-del-issue"'
                        +     ' data-id="' + amzCvrEsc(issue.id) + '" data-key="' + amzCvrEsc(issue.key) + '" title="Remove">'
                        +     '<i class="fas fa-trash"></i></button>'
                        + '</div>';
                }).join('')
                + '</div>';
        }

        function hideAmzCvrNewIssueAssigneeDropdown() {
            const dd = document.getElementById('amzCvrNewIssueAssigneeDropdown');
            if (dd) {
                dd.classList.add('d-none');
                dd.innerHTML = '';
            }
        }

        function renderAmzCvrNewIssueAssigneeDropdown(query) {
            const dd = document.getElementById('amzCvrNewIssueAssigneeDropdown');
            if (!dd) return;
            const q = String(query || '').trim().toLowerCase();
            const users = getAmzCvrAuditUsers().filter(function(u) {
                if (!q) return true;
                return String(u.name || '').toLowerCase().indexOf(q) !== -1;
            }).slice(0, 50);
            if (!users.length) {
                dd.innerHTML = '<div class="list-group-item text-muted small">No matching assignee</div>';
                dd.classList.remove('d-none');
                return;
            }
            dd.innerHTML = users.map(function(u) {
                return '<button type="button" class="list-group-item list-group-item-action amz-cvr-new-issue-assignee-opt py-1 px-2"'
                    + ' data-id="' + amzCvrEsc(u.id) + '" data-name="' + amzCvrEsc(u.name) + '">'
                    + amzCvrEsc(u.name) + '</button>';
            }).join('');
            dd.classList.remove('d-none');
        }

        function openAmzCvrAddIssueModal() {
            document.getElementById('amzCvrNewIssueLabel').value = '';
            document.getElementById('amzCvrNewIssueAssigneeSearch').value = '';
            document.getElementById('amzCvrNewIssueAssigneeId').value = '';
            hideAmzCvrNewIssueAssigneeDropdown();
            renderAmzCvrCustomIssueManageList();
            const modalEl = document.getElementById('amzCvrAddIssueModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        function hideAmzCvrAssigneeDropdowns() {
            document.querySelectorAll('.amz-cvr-task-assignee-dd').forEach(function(dd) {
                dd.classList.add('d-none');
                dd.innerHTML = '';
            });
            amzCvrActiveAssigneeKey = null;
        }

        function renderAmzCvrAssigneeDropdownForRow(key, query) {
            const dd = document.querySelector('.amz-cvr-task-assignee-dd[data-issue="' + key + '"]');
            if (!dd) return;
            const q = String(query || '').trim().toLowerCase();
            const users = getAmzCvrAuditUsers().filter(function(u) {
                if (!q) return true;
                return String(u.name || '').toLowerCase().indexOf(q) !== -1;
            }).slice(0, 50);

            amzCvrActiveAssigneeKey = key;
            if (!users.length) {
                dd.innerHTML = '<div class="list-group-item text-muted small">No matching assignee</div>';
                dd.classList.remove('d-none');
                return;
            }

            dd.innerHTML = users.map(function(u) {
                return '<button type="button" class="list-group-item list-group-item-action amz-cvr-assignee-opt py-1 px-2"'
                    + ' data-issue="' + amzCvrEsc(key) + '"'
                    + ' data-id="' + amzCvrEsc(u.id) + '" data-name="' + amzCvrEsc(u.name) + '">'
                    + amzCvrEsc(u.name) + '</button>';
            }).join('');
            dd.classList.remove('d-none');
        }

        function defaultAmzCvrTaskTitle(sku, label) {
            const parts = [];
            if (sku) parts.push(sku);
            if (label) parts.push(label);
            return parts.join(' — ') || label || '';
        }

        function collectAmzCvrTaskRowState() {
            const state = {};
            document.querySelectorAll('.amz-cvr-task-row').forEach(function(row) {
                const key = row.getAttribute('data-issue');
                if (!key) return;
                state[key] = {
                    title: (row.querySelector('.amz-cvr-task-title')?.value || '').trim(),
                    group: (row.querySelector('.amz-cvr-task-group')?.value || '').trim(),
                    assigneeId: (row.querySelector('.amz-cvr-task-assignee-id')?.value || '').trim(),
                    assigneeName: (row.querySelector('.amz-cvr-task-assignee-search')?.value || '').trim(),
                };
            });
            return state;
        }

        function resetAmzCvrIssueOptions() {
            document.querySelectorAll('.amz-cvr-issue-opt').forEach(function(cb) { cb.checked = false; });
            const otherText = document.getElementById('amzCvrAuditIssueOtherText');
            if (otherText) otherText.value = '';
            syncAmzCvrIssueUi();
        }

        function syncAmzCvrAuditModalHeight() {
            const modalEl = document.getElementById('amzCvrAuditModal');
            if (!modalEl) return;
            modalEl.classList.remove('amz-cvr-audit-tall');
            const dialog = modalEl.querySelector('.modal-dialog');
            if (!dialog) return;
            // Grow with content; only cap/scroll once content exceeds the viewport
            requestAnimationFrame(function() {
                const needsScroll = dialog.scrollHeight > (window.innerHeight - 2);
                modalEl.classList.toggle('amz-cvr-audit-tall', needsScroll);
            });
        }

        function syncAmzCvrIssueUi() {
            const otherCb = document.getElementById('amzCvrIssueOther');
            const otherWrap = document.getElementById('amzCvrAuditIssueOtherWrap');
            const otherChecked = !!(otherCb && otherCb.checked);
            if (otherWrap) {
                otherWrap.classList.toggle('d-none', !otherChecked);
            }
            renderAmzCvrTaskRows();
            syncAmzCvrAuditModalHeight();
        }

        function getAmzCvrSelectedIssueKeys() {
            const keys = [];
            document.querySelectorAll('.amz-cvr-issue-opt:checked').forEach(function(cb) {
                keys.push(cb.value);
            });
            return keys;
        }

        function renderAmzCvrTaskRows() {
            const wrap = document.getElementById('amzCvrAuditTaskRows');
            if (!wrap) return;
            const sku = (document.getElementById('amzCvrAuditSkuInput')?.value || '').trim();
            const prev = collectAmzCvrTaskRowState();
            const keys = getAmzCvrSelectedIssueKeys();

            if (!keys.length) {
                wrap.innerHTML = '<div class="small text-muted">Select one or more issues to create task inputs.</div>';
                return;
            }

            wrap.innerHTML = keys.map(function(key) {
                const isOther = key === 'other';
                const meta = isOther
                    ? { label: 'Other Issue', email: '', user_id: null, name: null }
                    : (AMZ_CVR_ISSUE_ASSIGNEES[key] || { label: key, email: '', user_id: null, name: null });
                const label = meta.label || key;
                const saved = prev[key] || {};
                const title = saved.title || defaultAmzCvrTaskTitle(sku, label);
                const group = saved.group || 'Amz';
                const assigneeId = isOther
                    ? (saved.assigneeId || '')
                    : (meta.user_id ? String(meta.user_id) : '');
                const assigneeDisplay = isOther
                    ? (saved.assigneeName || '')
                    : (meta.name
                        ? (meta.name + ' (' + meta.email + ')')
                        : (meta.email || 'User not found'));
                const assigneeReadonly = !isOther ? ' readonly' : '';
                const assigneePlaceholder = isOther ? 'Quick Search assignee...' : 'Auto-assigned';
                const missingUser = !isOther && !meta.user_id;

                return ''
                    + '<div class="border rounded px-2 py-1 amz-cvr-task-row" data-issue="' + amzCvrEsc(key) + '">'
                    +   '<div class="row g-2 align-items-center">'
                    +     '<div class="col-12 col-md-2">'
                    +       '<div class="fw-semibold small text-truncate" title="' + amzCvrEsc(label) + '">' + amzCvrEsc(label) + '</div>'
                    +       (missingUser
                        ? '<small class="text-danger">User not found</small>'
                        : '')
                    +     '</div>'
                    +     '<div class="col-12 col-md-4">'
                    +       '<input type="text" class="form-control form-control-sm amz-cvr-task-title"'
                    +         ' data-issue="' + amzCvrEsc(key) + '"'
                    +         ' value="' + amzCvrEsc(title) + '" maxlength="1000" autocomplete="off"'
                    +         ' placeholder="Task" title="Task">'
                    +     '</div>'
                    +     '<div class="col-12 col-md-2">'
                    +       '<input type="text" class="form-control form-control-sm amz-cvr-task-group"'
                    +         ' data-issue="' + amzCvrEsc(key) + '"'
                    +         ' value="' + amzCvrEsc(group) + '" maxlength="100" autocomplete="off"'
                    +         ' placeholder="Group" title="Group">'
                    +     '</div>'
                    +     '<div class="col-12 col-md-4">'
                    +       '<div class="position-relative amz-cvr-task-assignee-wrap" data-issue="' + amzCvrEsc(key) + '">'
                    +         '<input type="text" class="form-control form-control-sm amz-cvr-task-assignee-search"'
                    +           ' data-issue="' + amzCvrEsc(key) + '"'
                    +           ' value="' + amzCvrEsc(assigneeDisplay) + '"'
                    +           ' placeholder="' + amzCvrEsc(assigneePlaceholder) + '"'
                    +           ' title="Assignee' + (isOther ? ' (manual)' : ' (auto)') + '"'
                    +           assigneeReadonly + ' autocomplete="off">'
                    +         '<input type="hidden" class="amz-cvr-task-assignee-id" data-issue="' + amzCvrEsc(key) + '"'
                    +           ' value="' + amzCvrEsc(assigneeId) + '">'
                    +         '<div class="list-group position-absolute w-100 shadow-sm d-none amz-cvr-task-assignee-dd"'
                    +           ' data-issue="' + amzCvrEsc(key) + '"'
                    +           ' style="z-index: 1080; max-height: 220px; overflow-y: auto; top: 100%; left: 0;"></div>'
                    +       '</div>'
                    +     '</div>'
                    +   '</div>'
                    + '</div>';
            }).join('');
        }

        function buildAmzCvrTaskJobs() {
            const jobs = [];
            document.querySelectorAll('.amz-cvr-task-row').forEach(function(row) {
                const key = row.getAttribute('data-issue');
                if (!key) return;
                const title = (row.querySelector('.amz-cvr-task-title')?.value || '').trim();
                const group = (row.querySelector('.amz-cvr-task-group')?.value || '').trim() || 'Amz';
                const assigneeId = (row.querySelector('.amz-cvr-task-assignee-id')?.value || '').trim();
                const assigneeLabel = (row.querySelector('.amz-cvr-task-assignee-search')?.value || '').trim();

                if (key === 'other') {
                    const otherText = (document.getElementById('amzCvrAuditIssueOtherText')?.value || '').trim();
                    jobs.push({
                        key: 'other',
                        label: 'Other Issue',
                        issueText: otherText ? ('Other Issue: ' + otherText) : 'Other Issue',
                        title: title,
                        group: group,
                        assigneeId: assigneeId,
                        assigneeLabel: assigneeLabel || 'manual',
                        manual: true,
                    });
                    return;
                }

                const meta = AMZ_CVR_ISSUE_ASSIGNEES[key];
                if (!meta) return;
                jobs.push({
                    key: key,
                    label: meta.label,
                    issueText: meta.label,
                    title: title,
                    group: group,
                    assigneeId: assigneeId || (meta.user_id ? String(meta.user_id) : ''),
                    assigneeLabel: assigneeLabel || meta.email,
                    manual: false,
                    email: meta.email,
                });
            });
            return jobs;
        }

        function createAmzCvrTask(payload) {
            const body = new FormData();
            body.append('_token', AMZ_CVR_CSRF);
            body.append('title', payload.title);
            body.append('description', payload.description);
            body.append('group', payload.group || 'Amz');
            body.append('priority', 'normal');
            body.append('assignee_id', payload.assigneeId);
            body.append('etc_minutes', '10');
            body.append('tid', new Date().toISOString().slice(0, 16));

            return fetch(AMZ_CVR_TASK_STORE_URL, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': AMZ_CVR_CSRF
                },
                body: body,
                credentials: 'same-origin'
            }).then(function(res) {
                return res.json().then(function(data) {
                    return { ok: res.ok, status: res.status, data: data };
                }).catch(function() {
                    return { ok: res.ok, status: res.status, data: null };
                });
            });
        }

        function openAmzCvrAuditModal(row) {
            row = row || {};
            const sku = row['(Child) sku'] || '';
            const sess30 = parseFloat(row.Sess30) || 0;
            const aL30 = parseFloat(row.A_L30) || 0;
            const cvr = sess30 <= 0 ? 0 : (aL30 / sess30) * 100;
            const price = parseFloat(row.price) || 0;

            document.getElementById('amzCvrAuditSku').textContent = sku || '—';
            document.getElementById('amzCvrAuditSkuInput').value = sku;
            document.getElementById('amzCvrAuditParent').textContent = row.Parent || '—';
            document.getElementById('amzCvrAuditInv').textContent = Math.round(parseFloat(row.INV) || 0).toLocaleString('en-US');
            document.getElementById('amzCvrAuditViews').textContent = Math.round(sess30).toLocaleString('en-US');
            document.getElementById('amzCvrAuditCvr').textContent = sess30 <= 0 ? '0.0%' : (Math.round(cvr) + '%');
            document.getElementById('amzCvrAuditPrice').textContent = price > 0 ? ('$' + price.toFixed(2)) : '—';
            resetAmzCvrIssueOptions();

            const modalEl = document.getElementById('amzCvrAuditModal');
            // Keep modal on <body> so sidenav/content wrappers cannot clip width
            if (modalEl && modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
            bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, focus: true }).show();
            setTimeout(function() {
                syncAmzCvrAuditBulkUi();
                syncAmzCvrAuditModalHeight();
            }, 0);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const auditModalEl = document.getElementById('amzCvrAuditModal');
            if (auditModalEl && auditModalEl.parentElement !== document.body) {
                document.body.appendChild(auditModalEl);
            }

            restoreAmzCvrFilterState();

            amz_cvr_issues = new Tabulator('#amz_cvr_issues', {
                ajaxURL: @json(route('amz.cvr.issues.data')),
                ajaxResponse: function(url, params, response) {
                    return (response && response.data) ? response.data : (response || []);
                },
                layout: 'fitDataStretch',
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                paginationCounter: 'rows',
                movableColumns: true,
                initialSort: [
                    { column: 'audit_history_ts', dir: 'desc' },
                    { column: 'CVR_L30', dir: 'asc' }
                ],
                columns: buildAmzCvrIssuesColumns()
            });

            amz_cvr_issues.on('dataLoaded', function() {
                updateAmzCvrFilterOptionCounts();
                updateAmzCvrMlBadge();
                applyAmzCvrFilters();
            });
            amz_cvr_issues.on('dataProcessed', function() {
                updateAmzCvrFilterOptionCounts();
                updateAmzCvrMlBadge();
                updateAmzCvrRowsBadge();
            });
            amz_cvr_issues.on('dataFiltered', function() {
                updateAmzCvrRowsBadge();
            });

            document.getElementById('amz-cvr-issues-refresh-btn')?.addEventListener('click', function() {
                if (amz_cvr_issues) amz_cvr_issues.replaceData();
            });

            document.getElementById('amz-cvr-ml-badge')?.addEventListener('click', function() {
                amzCvrMlFilterActive = !amzCvrMlFilterActive;
                applyAmzCvrFilters({ persist: true });
            });

            document.getElementById('amz-cvr-cvr-filter')?.addEventListener('change', function() {
                applyAmzCvrFilters({ persist: true });
            });

            document.getElementById('amz-cvr-views-filter')?.addEventListener('change', function() {
                applyAmzCvrFilters({ persist: true });
            });

            document.getElementById('amz-cvr-history-date-filter')?.addEventListener('change', function() {
                applyAmzCvrFilters({ persist: true });
                if (amz_cvr_issues) {
                    amz_cvr_issues.redraw(true);
                }
            });

            document.getElementById('amz-cvr-sku-search')?.addEventListener('input', function() {
                applyAmzCvrFilters({ persist: true });
            });

            document.getElementById('amz-cvr-keep-filters')?.addEventListener('change', function() {
                if (this.checked) {
                    saveAmzCvrFilterState();
                } else {
                    clearAmzCvrSavedFilters();
                }
            });

            document.getElementById('amz-cvr-export-filtered')?.addEventListener('click', function(e) {
                e.preventDefault();
                exportAmzCvrIssues('filtered');
            });

            document.getElementById('amz-cvr-export-all')?.addEventListener('click', function(e) {
                e.preventDefault();
                exportAmzCvrIssues('all');
            });

            document.getElementById('amz-cvr-avg-cvr-badge')?.addEventListener('click', function() {
                openAmzCvrRollingChart(null);
            });


            document.getElementById('amzCvrRollingChartDays')?.addEventListener('change', function() {
                amzCvrRollingChartDays = parseInt(this.value, 10);
                if (!isFinite(amzCvrRollingChartDays)) amzCvrRollingChartDays = 30;
                loadAmzCvrRollingChart();
            });

            document.addEventListener('click', function(e) {
                const badge = e.target.closest('.amz-cvr-history-cvr-badge');
                if (!badge) return;
                e.preventDefault();
                e.stopPropagation();
                openAmzCvrRollingChart(badge.getAttribute('data-sku') || null);
            });

            document.addEventListener('change', function(e) {
                if (e.target && e.target.id === 'amz-cvr-issues-select-all') {
                    var checked = e.target.checked;
                    document.querySelectorAll('#amz_cvr_issues .row-select-checkbox').forEach(function(cb) {
                        cb.checked = checked;
                    });
                    updateAmzCvrIssuesSelected();
                    return;
                }
                if (e.target && e.target.classList && e.target.classList.contains('row-select-checkbox')) {
                    updateAmzCvrIssuesSelected();
                }
            });

            const taskRowsEl = document.getElementById('amzCvrAuditTaskRows');
            taskRowsEl?.addEventListener('focusin', function(e) {
                const input = e.target.closest('.amz-cvr-task-assignee-search');
                if (!input || input.readOnly) return;
                const key = input.getAttribute('data-issue');
                if (key) renderAmzCvrAssigneeDropdownForRow(key, input.value);
            });
            taskRowsEl?.addEventListener('input', function(e) {
                const input = e.target.closest('.amz-cvr-task-assignee-search');
                if (!input || input.readOnly) return;
                const key = input.getAttribute('data-issue');
                const idInput = document.querySelector('.amz-cvr-task-assignee-id[data-issue="' + key + '"]');
                if (idInput) idInput.value = '';
                if (key) renderAmzCvrAssigneeDropdownForRow(key, input.value);
            });
            taskRowsEl?.addEventListener('click', function(e) {
                const opt = e.target.closest('.amz-cvr-assignee-opt');
                if (!opt) return;
                const key = opt.getAttribute('data-issue');
                const idInput = document.querySelector('.amz-cvr-task-assignee-id[data-issue="' + key + '"]');
                const searchInput = document.querySelector('.amz-cvr-task-assignee-search[data-issue="' + key + '"]');
                if (idInput) idInput.value = opt.getAttribute('data-id') || '';
                if (searchInput) searchInput.value = opt.getAttribute('data-name') || '';
                hideAmzCvrAssigneeDropdowns();
            });
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.amz-cvr-task-assignee-wrap')) {
                    hideAmzCvrAssigneeDropdowns();
                }
            });

            document.getElementById('amzCvrAuditIssueOptions')?.addEventListener('change', function(e) {
                if (e.target && e.target.classList.contains('amz-cvr-issue-opt')) {
                    syncAmzCvrIssueUi();
                }
            });

            renderAmzCvrCustomIssueCheckboxes();
            renderAmzCvrCustomIssueManageList();

            document.getElementById('amzCvrAddIssueBtn')?.addEventListener('click', function() {
                openAmzCvrAddIssueModal();
            });

            document.getElementById('amzCvrNewIssueAssigneeSearch')?.addEventListener('focus', function() {
                renderAmzCvrNewIssueAssigneeDropdown(this.value);
            });
            document.getElementById('amzCvrNewIssueAssigneeSearch')?.addEventListener('input', function() {
                document.getElementById('amzCvrNewIssueAssigneeId').value = '';
                renderAmzCvrNewIssueAssigneeDropdown(this.value);
            });
            document.getElementById('amzCvrNewIssueAssigneeDropdown')?.addEventListener('click', function(e) {
                const opt = e.target.closest('.amz-cvr-new-issue-assignee-opt');
                if (!opt) return;
                document.getElementById('amzCvrNewIssueAssigneeId').value = opt.getAttribute('data-id') || '';
                document.getElementById('amzCvrNewIssueAssigneeSearch').value = opt.getAttribute('data-name') || '';
                hideAmzCvrNewIssueAssigneeDropdown();
            });
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#amzCvrNewIssueAssigneeWrap')) {
                    hideAmzCvrNewIssueAssigneeDropdown();
                }
            });

            document.getElementById('amzCvrSaveNewIssueBtn')?.addEventListener('click', function() {
                const label = (document.getElementById('amzCvrNewIssueLabel')?.value || '').trim();
                const assigneeId = (document.getElementById('amzCvrNewIssueAssigneeId')?.value || '').trim();
                if (!label) {
                    alert('Please enter an Issue name.');
                    document.getElementById('amzCvrNewIssueLabel')?.focus();
                    return;
                }
                if (!assigneeId) {
                    alert('Please select an Assignee.');
                    document.getElementById('amzCvrNewIssueAssigneeSearch')?.focus();
                    return;
                }

                const btn = this;
                const orig = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Saving...';

                const body = new FormData();
                body.append('_token', AMZ_CVR_CSRF);
                body.append('label', label);
                body.append('assignee_user_id', assigneeId);

                fetch(AMZ_CVR_ISSUE_TYPES_STORE_URL, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': AMZ_CVR_CSRF
                    },
                    body: body,
                    credentials: 'same-origin'
                })
                    .then(function(res) {
                        return res.json().then(function(data) {
                            return { ok: res.ok, status: res.status, data: data };
                        }).catch(function() {
                            return { ok: res.ok, status: res.status, data: null };
                        });
                    })
                    .then(function(result) {
                        if (!result.ok) {
                            let msg = (result.data && (result.data.message || result.data.error)) || 'Could not save issue.';
                            if (result.data && result.data.errors) {
                                msg = Object.values(result.data.errors).flat().join('\n');
                            }
                            alert(msg);
                            return;
                        }
                        if (result.data && result.data.issue) {
                            registerAmzCvrCustomIssue(result.data.issue);
                        }
                        document.getElementById('amzCvrNewIssueLabel').value = '';
                        document.getElementById('amzCvrNewIssueAssigneeSearch').value = '';
                        document.getElementById('amzCvrNewIssueAssigneeId').value = '';
                        alert((result.data && result.data.message) ? result.data.message : 'Issue saved.');
                    })
                    .catch(function() { alert('Could not save issue.'); })
                    .finally(function() {
                        btn.disabled = false;
                        btn.innerHTML = orig;
                    });
            });

            document.getElementById('amzCvrCustomIssueList')?.addEventListener('click', function(e) {
                const delBtn = e.target.closest('.amz-cvr-del-issue');
                if (!delBtn) return;
                const id = delBtn.getAttribute('data-id');
                const key = delBtn.getAttribute('data-key');
                if (!id || !confirm('Remove this custom issue type?')) return;

                fetch(AMZ_CVR_ISSUE_TYPES_DESTROY_URL + '/' + encodeURIComponent(id), {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': AMZ_CVR_CSRF
                    },
                    credentials: 'same-origin'
                })
                    .then(function(res) {
                        return res.json().then(function(data) {
                            return { ok: res.ok, data: data };
                        }).catch(function() {
                            return { ok: res.ok, data: null };
                        });
                    })
                    .then(function(result) {
                        if (!result.ok) {
                            alert((result.data && result.data.message) || 'Could not remove issue.');
                            return;
                        }
                        unregisterAmzCvrCustomIssue(key || (result.data && result.data.issue_key) || '');
                    })
                    .catch(function() { alert('Could not remove issue.'); });
            });

            document.getElementById('amzCvrAuditIssueOtherText')?.addEventListener('input', function() {
                // Keep other task title in sync only if still at default-ish content
                const row = document.querySelector('.amz-cvr-task-row[data-issue="other"]');
                if (!row) return;
                const titleInput = row.querySelector('.amz-cvr-task-title');
                if (!titleInput) return;
                const sku = (document.getElementById('amzCvrAuditSkuInput')?.value || '').trim();
                const otherText = this.value.trim();
                const label = otherText ? ('Other Issue: ' + otherText) : 'Other Issue';
                // Always refresh other title from SKU + current other text when typing other details
                titleInput.value = defaultAmzCvrTaskTitle(sku, label);
            });

            document.getElementById('amzCvrAuditSubmitTaskBtn')?.addEventListener('click', function() {
                const sourceSku = document.getElementById('amzCvrAuditSkuInput').value.trim();
                const targetSkus = getAmzCvrAuditTargetSkus();
                const jobs = buildAmzCvrTaskJobs();
                const otherChecked = !!document.getElementById('amzCvrIssueOther')?.checked;
                const otherText = (document.getElementById('amzCvrAuditIssueOtherText')?.value || '').trim();

                if (!targetSkus.length) {
                    alert('No SKU selected for audit submit.');
                    return;
                }
                if (!jobs.length) {
                    alert('Please select at least one Issue found option.');
                    return;
                }
                if (otherChecked && !otherText) {
                    alert('Please describe the additional issue for Other.');
                    document.getElementById('amzCvrAuditIssueOtherText')?.focus();
                    return;
                }

                for (let i = 0; i < jobs.length; i++) {
                    const job = jobs[i];
                    if (!job.title) {
                        alert('Please enter a Task for ' + job.label + '.');
                        document.querySelector('.amz-cvr-task-title[data-issue="' + job.key + '"]')?.focus();
                        return;
                    }
                    if (job.manual && !job.assigneeId) {
                        alert('Please select a manual Assignee for Other Issue.');
                        document.querySelector('.amz-cvr-task-assignee-search[data-issue="other"]')?.focus();
                        return;
                    }
                    if (!job.manual && !job.assigneeId) {
                        alert('No Task Manager user found for ' + job.label + ' (' + (job.email || '') + '). Please create/activate that user first.');
                        return;
                    }
                }

                if (targetSkus.length > 1) {
                    const okBulk = confirm(
                        'Apply this audit to ' + targetSkus.length + ' selected SKUs?\n\n'
                        + 'Each SKU will get ' + jobs.length + ' task(s) ('
                        + (targetSkus.length * jobs.length) + ' total).'
                    );
                    if (!okBulk) return;
                }

                const btn = this;
                const orig = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Submitting...';

                let chain = Promise.resolve();
                const results = [];
                targetSkus.forEach(function(sku) {
                    jobs.forEach(function(job) {
                        chain = chain.then(function() {
                            const title = amzCvrTaskTitleForSku(job.title, sourceSku, sku, job.label);
                            const descParts = [];
                            descParts.push('SKU: ' + sku);
                            descParts.push('Issue found: ' + job.issueText);
                            descParts.push('Assignee: ' + job.assigneeLabel);
                            if (targetSkus.length > 1) {
                                descParts.push('Bulk audit from: ' + (sourceSku || sku));
                            }
                            return createAmzCvrTask({
                                title: title,
                                description: descParts.join('\n'),
                                group: job.group || 'Amz',
                                assigneeId: job.assigneeId
                            }).then(function(result) {
                                results.push({ sku: sku, job: job, result: result });
                            });
                        });
                    });
                });

                chain.then(function() {
                    const failed = results.filter(function(r) { return !r.result.ok; });
                    const okCount = results.length - failed.length;
                    const okBySku = {};
                    results.forEach(function(r) {
                        if (!r.result.ok) return;
                        okBySku[r.sku] = (okBySku[r.sku] || 0) + 1;
                    });
                    const okSkuList = Object.keys(okBySku);

                    function finishAlerts() {
                        applyAmzCvrFilters();
                        updateAmzCvrFilterOptionCounts();
                        if (!failed.length) {
                            alert(
                                targetSkus.length > 1
                                    ? (okCount + ' tasks submitted across ' + targetSkus.length + ' selected SKUs.')
                                    : (okCount === 1
                                        ? 'Task submitted to Task Manager.'
                                        : (okCount + ' tasks submitted (one per selected issue / assignee).'))
                            );
                            resetAmzCvrIssueOptions();
                            syncAmzCvrAuditBulkUi();
                            return;
                        }
                        const first = failed[0].result;
                        let msg = 'Created ' + okCount + ' of ' + results.length + ' tasks'
                            + (targetSkus.length > 1 ? (' across ' + targetSkus.length + ' SKUs') : '') + '.';
                        if (first.status === 422 && first.data && first.data.errors) {
                            msg += '\n' + Object.values(first.data.errors).flat().join('\n');
                        } else if (first.data && (first.data.message || first.data.error)) {
                            msg += '\n' + (first.data.message || first.data.error);
                        } else {
                            msg += '\nSome tasks failed.';
                        }
                        alert(msg);
                    }

                    if (!okSkuList.length) {
                        finishAlerts();
                        return;
                    }

                    let histChain = Promise.resolve();
                    okSkuList.forEach(function(sku) {
                        histChain = histChain.then(function() {
                            return storeAmzCvrAuditHistory(sku, okBySku[sku]);
                        });
                    });
                    return histChain.then(finishAlerts);
                })
                    .catch(function() { alert('Could not create task(s).'); })
                    .finally(function() {
                        btn.disabled = false;
                        btn.innerHTML = orig;
                        syncAmzCvrAuditBulkUi();
                    });
            });

            document.getElementById('amzCvrAuditSaveBtn')?.addEventListener('click', function() {
                const sku = document.getElementById('amzCvrAuditSkuInput').value.trim();
                const jobs = buildAmzCvrTaskJobs();
                const otherChecked = !!document.getElementById('amzCvrIssueOther')?.checked;
                const otherText = (document.getElementById('amzCvrAuditIssueOtherText')?.value || '').trim();
                if (!sku) return;
                if (!jobs.length) {
                    alert('Please select at least one Issue found option.');
                    return;
                }
                if (otherChecked && !otherText) {
                    alert('Please describe the additional issue for Other.');
                    document.getElementById('amzCvrAuditIssueOtherText')?.focus();
                    return;
                }
                // Hook for future save API — closes modal for now
                const modalEl = document.getElementById('amzCvrAuditModal');
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            });

            document.addEventListener('click', function(e) {
                var copyBtn = e.target.closest('.copy-sku-btn');
                if (copyBtn) {
                    var sku = copyBtn.getAttribute('data-sku') || '';
                    if (!sku || !navigator.clipboard) return;
                    navigator.clipboard.writeText(sku).then(function() {
                        copyBtn.title = 'Copied!';
                        setTimeout(function() { copyBtn.title = 'Copy SKU'; }, 1200);
                    });
                    return;
                }

                var delBtn = e.target.closest('.amz-cvr-del-lmp');
                if (delBtn) {
                    e.preventDefault();
                    var id = delBtn.getAttribute('data-id');
                    if (!id || !confirm('Delete this competitor?')) return;
                    fetch(AMZ_CVR_LMP_DELETE_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': AMZ_CVR_CSRF,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ id: id })
                    })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res.success === false) {
                                alert(res.message || res.error || 'Delete failed');
                                return;
                            }
                            loadAmzCvrCompetitors(amzCvrLmpCurrentSku, false);
                            if (amz_cvr_issues) amz_cvr_issues.replaceData();
                        })
                        .catch(function() { alert('Delete failed'); });
                    return;
                }
            });

            document.getElementById('amzCvrLmpPullBtn')?.addEventListener('click', function() {
                if (!amzCvrLmpCurrentSku) return;
                this.disabled = true;
                this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Pulling...';
                loadAmzCvrCompetitors(amzCvrLmpCurrentSku, true);
            });

            document.getElementById('amzCvrAddCompForm')?.addEventListener('submit', function(e) {
                e.preventDefault();
                var sku = document.getElementById('amzCvrAddCompSku').value.trim();
                var asin = document.getElementById('amzCvrAddCompAsin').value.trim();
                var price = parseFloat(document.getElementById('amzCvrAddCompPrice').value);
                var link = document.getElementById('amzCvrAddCompLink').value.trim();
                if (!asin) { alert('ASIN is required'); return; }
                if (!price || price <= 0) { alert('Valid price is required'); return; }

                var submitBtn = this.querySelector('button[type="submit"]');
                var orig = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

                fetch(AMZ_CVR_LMP_ADD_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': AMZ_CVR_CSRF,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        sku: sku,
                        asin: asin,
                        price: price,
                        product_link: link || null,
                        marketplace: 'amazon'
                    })
                })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success === false) {
                            alert(res.message || res.error || 'Add failed');
                            return;
                        }
                        document.getElementById('amzCvrAddCompAsin').value = '';
                        document.getElementById('amzCvrAddCompPrice').value = '';
                        document.getElementById('amzCvrAddCompLink').value = '';
                        loadAmzCvrCompetitors(sku, false);
                        if (amz_cvr_issues) amz_cvr_issues.replaceData();
                    })
                    .catch(function() { alert('Add failed'); })
                    .finally(function() {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = orig;
                    });
            });
        });
    </script>
@endsection
