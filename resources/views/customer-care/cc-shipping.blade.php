@extends('layouts.vertical', ['title' => 'CC Shipping', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Tabulator header — teal pill so it matches the reference design. */
        #ccShippingTable .tabulator-header {
            background: #1abc9c;
        }

        #ccShippingTable .tabulator-header .tabulator-col {
            background: #1abc9c;
            border-right: 1px solid rgba(255, 255, 255, 0.25);
        }

        /* Headers wrap onto multiple lines so long titles like
           "9 AM Clear history" / "3PM Clear History" are visible in full
           regardless of column width. */
        #ccShippingTable .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            color: #000000;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.3px;
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: clip !important;
            text-align: center;
            line-height: 1.25;
            word-break: normal;
        }

        #ccShippingTable .tabulator-header,
        #ccShippingTable .tabulator-header .tabulator-col,
        #ccShippingTable .tabulator-header .tabulator-col .tabulator-col-content {
            min-height: 56px;
            height: auto !important;
        }

        #ccShippingTable .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 10px 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #ccShippingTable .tabulator-col .tabulator-arrow {
            border-bottom-color: #000000 !important;
            border-top-color: #000000 !important;
        }

        /* Keep cell layout simple — let Tabulator handle column widths/positions.
           Vertical centering is requested per-column via the `vertAlign` option
           in the JS column definitions below. */
        #ccShippingTable .tabulator-cell {
            padding: 8px 14px !important;
            white-space: normal !important;
        }

        /* Channel logo thumbnail in the IMG column (sourced from channel_master.logo). */
        .channel-logo-thumb {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 6px;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 2px;
            display: inline-block;
        }

        .channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            background: #f1f3f5;
            border: 1px dashed #ced4da;
            color: #adb5bd;
            font-size: 14px;
        }

        /* Channel name "pill" — light blue background, blue bold text. */
        .channel-pill {
            display: inline-block;
            padding: 4px 14px;
            background: #e7f1ff;
            color: #0d6efd;
            border-radius: 14px;
            font-size: 13px;
            font-weight: 700;
        }

        /* M link / H link cell — copied from /account-health-master/tabulator
           so the icons + empty-state dot stay visually consistent. */
        .link-cell-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-width: 32px;
            min-height: 24px;
        }

        .link-empty-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #dc2626;
            cursor: pointer;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08) inset;
        }

        .link-empty-dot:hover {
            background: #b91c1c;
        }

        /* ---- Status (red cross) button for the per-channel checklist ---- */
        .ccmr-status-btn {
            background: transparent;
            border: 0;
            padding: 0;
            cursor: pointer;
            line-height: 0;
            color: #dc2626;
            font-size: 22px;
            transition: transform 0.12s ease, color 0.12s ease;
        }
        .ccmr-status-btn:hover { transform: scale(1.12); color: #b91c1c; }
        .ccmr-status-btn.is-complete { color: #16a34a; }
        .ccmr-status-btn.is-complete:hover { color: #15803d; }
        .ccmr-status-btn:focus { outline: none; }
        .ccmr-status-btn:focus-visible {
            outline: 2px solid rgba(13, 110, 253, 0.45);
            border-radius: 4px;
        }

        /* ---- "History" cell (latest user + timestamp on a single row) ---- */
        .ccmr-history-cell {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 8px;
            line-height: 1.2;
            font-size: 12px;
            white-space: nowrap;
        }
        .ccmr-history-cell .h-user {
            font-weight: 600;
            color: #212529;
        }
        .ccmr-history-cell .h-time {
            color: #6c757d;
            font-size: 11px;
        }
        .ccmr-history-cell.is-empty {
            color: #adb5bd;
            font-style: italic;
        }
        .ccmr-history-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            color: #0d6efd;
            cursor: pointer;
            text-decoration: none;
            border-radius: 50%;
            transition: background 0.12s ease, color 0.12s ease, transform 0.12s ease;
            flex-shrink: 0;
        }
        .ccmr-history-link:hover {
            background: #e7f1ff;
            color: #084298;
            transform: scale(1.06);
            text-decoration: none;
        }

        /* ---- Checklist modal layout ---- */
        #ccmrChecklistModal .ccmr-check-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        #ccmrChecklistModal .ccmr-check-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 12px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            margin-bottom: 8px;
            background: #fff;
            transition: border-color 0.15s ease, background 0.15s ease;
        }
        #ccmrChecklistModal .ccmr-check-list li:hover {
            border-color: #90caf9;
            background: #f5faff;
        }
        #ccmrChecklistModal .ccmr-check-list li.is-checked {
            background: #e8f5e9;
            border-color: #66bb6a;
        }
        #ccmrChecklistModal .ccmr-check-list input[type="checkbox"] {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            accent-color: #1976d2;
            flex-shrink: 0;
        }
        #ccmrChecklistModal .ccmr-check-list label {
            font-weight: 600;
            color: #212529;
            margin: 0;
            cursor: pointer;
        }

        /* ---- History modal table ---- */
        #ccmrHistoryModal .ccmr-history-table {
            width: 100%;
            font-size: 12px;
        }
        #ccmrHistoryModal .ccmr-history-table th,
        #ccmrHistoryModal .ccmr-history-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: top;
        }
        #ccmrHistoryModal .ccmr-history-table th {
            background: #f8f9fa;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
            color: #495057;
            letter-spacing: 0.3px;
        }
        #ccmrHistoryModal .ccmr-check-pill {
            display: inline-block;
            min-width: 18px;
            text-align: center;
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
        }
        #ccmrHistoryModal .ccmr-check-pill.is-yes { background: #d1fae5; color: #065f46; }
        #ccmrHistoryModal .ccmr-check-pill.is-no  { background: #fee2e2; color: #991b1b; }

        /* ---- "Missed" rows in the history table ---- */
        #ccmrHistoryBody tr.is-missed {
            background: #fef2f2;
        }
        #ccmrHistoryBody tr.is-missed td {
            color: #991b1b;
        }
        .ccmr-missed-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 2px 10px;
            background: #fee2e2;
            color: #991b1b;
            font-weight: 700;
            font-size: 11px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .ccmr-missed-emoji {
            font-size: 14px;
            line-height: 1;
            text-transform: none;
        }

        /* ---- Post-submit delivery-truck celebration in the Status cell ---- */
        .ccmr-delivery-celebration {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            min-height: 36px;
            overflow: hidden;
        }
        .ccmr-delivery-img {
            max-width: 100%;
            max-height: 60px;
            object-fit: contain;
            animation: ccmrDeliveryDrive 1.6s ease-out 1 both,
                       ccmrDeliveryBob   1.2s ease-in-out 1.6s infinite alternate;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.15));
        }
        @keyframes ccmrDeliveryDrive {
            0%   { transform: translateX(-120%); opacity: 0; }
            70%  { transform: translateX(6%);    opacity: 1; }
            100% { transform: translateX(0);     opacity: 1; }
        }
        @keyframes ccmrDeliveryBob {
            0%   { transform: translateY(0)     scale(1); }
            100% { transform: translateY(-1px)  scale(1.02); }
        }

        /* ---- 9AM-Clear window banner inside the submit modal ---- */
        .ccmr-window-banner {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .ccmr-window-banner.is-open {
            background: #ecfdf5;
            border-color: #6ee7b7;
            color: #065f46;
        }
        .ccmr-window-banner.is-closed {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #991b1b;
        }
        .ccmr-window-banner i {
            font-size: 16px;
        }

        /* ---- "Next" priority cell (1..9 dropdown — manager-only) ---- */
        .ccmr-next-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            min-height: 28px;
            padding: 2px 10px;
            border-radius: 14px;
            background: #fff7e6;
            color: #b45309;
            font-weight: 700;
            font-size: 13px;
            border: 1px solid #fcd34d;
        }
        .ccmr-next-pill.is-empty {
            background: #f8f9fa;
            color: #adb5bd;
            border-style: dashed;
            border-color: #ced4da;
            font-weight: 500;
            font-style: italic;
        }
        /* When the cell is editable for the manager, show a subtle hover so
           they know it's interactive. Tabulator adds `tabulator-editable`
           on cells whose `editable` callback returned true. */
        #ccShippingTable .tabulator-cell.tabulator-editable {
            cursor: pointer;
        }
        #ccShippingTable .tabulator-cell.tabulator-editable:hover .ccmr-next-pill {
            background: #fde68a;
            border-color: #f59e0b;
            color: #92400e;
        }

        /* ---- TAT cell pill — read-only, sortable minutes count ---- */
        .ccmr-tat-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
            min-height: 24px;
            padding: 2px 10px;
            border-radius: 12px;
            background: #eef2ff;
            color: #3730a3;
            font-weight: 700;
            font-size: 12px;
            border: 1px solid #c7d2fe;
            white-space: nowrap;
        }
        .ccmr-tat-pill.is-empty {
            background: #f8f9fa;
            color: #adb5bd;
            border-style: dashed;
            border-color: #ced4da;
            font-weight: 500;
            font-style: italic;
        }

        /* ---- Average-TAT badges shown above the table ---- */
        .ccmr-tat-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 14px;
        }
        .ccmr-tat-badge {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            border-radius: 12px;
            border: 1px solid transparent;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            min-width: 220px;
            background: #fff;
        }
        .ccmr-tat-badge__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            font-size: 18px;
            flex-shrink: 0;
        }
        .ccmr-tat-badge__body {
            display: inline-flex;
            flex-direction: column;
            line-height: 1.2;
        }
        .ccmr-tat-badge__label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            color: #6c757d;
        }
        .ccmr-tat-badge__value {
            font-size: 22px;
            font-weight: 800;
            color: #212529;
            line-height: 1.1;
            margin-top: 2px;
        }
        .ccmr-tat-badge__meta {
            font-size: 11px;
            color: #6c757d;
            margin-top: 2px;
        }
        /* Messages variant: indigo accent (matches TAT pill colour). */
        .ccmr-tat-badge--messages {
            background: linear-gradient(135deg, #eef2ff 0%, #ffffff 60%);
            border-color: #c7d2fe;
        }
        .ccmr-tat-badge--messages .ccmr-tat-badge__icon {
            background: #c7d2fe;
            color: #3730a3;
        }
        .ccmr-tat-badge--messages .ccmr-tat-badge__value { color: #3730a3; }
        /* Returns variant: amber accent (echoes the R-side cell pills). */
        .ccmr-tat-badge--returns {
            background: linear-gradient(135deg, #fff7e6 0%, #ffffff 60%);
            border-color: #fcd34d;
        }
        .ccmr-tat-badge--returns .ccmr-tat-badge__icon {
            background: #fde68a;
            color: #92400e;
        }
        .ccmr-tat-badge--returns .ccmr-tat-badge__value { color: #92400e; }

        /* Success / missed split values inside the 30-day history badges. */
        .ccmr-hist-success { color: #16a34a; }
        .ccmr-hist-missed  { color: #dc2626; }
        .ccmr-hist-sep {
            color: #adb5bd;
            margin: 0 6px;
            font-weight: 500;
        }

        /* Grow the "Next" dropdown so all 10 options (clear + 1..9) are
           visible without scrolling. Tabulator's list editor floats its
           container as `.tabulator-edit-list` (appended near the cell) and
           each option is `.tabulator-edit-list-item`. */
        .tabulator-edit-list {
            max-height: none !important;
            padding: 4px 0;
        }
        .tabulator-edit-list .tabulator-edit-list-item {
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 600;
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="page-title mb-0">
                    <i class="ri-truck-line me-2 text-primary"></i>CC Shipping
                </h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript:void(0);">Customer Care</a></li>
                        <li class="breadcrumb-item active">CC Shipping</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== TAT averages — quick-glance KPIs above the table ===== --}}
    <div class="row">
        <div class="col-12">
            <div class="ccmr-tat-badges">
                {{-- 9AM-Clear (Messages-side) success / missed totals — last
                     30 calendar days, Sundays excluded. --}}
                <div class="ccmr-tat-badge ccmr-tat-badge--messages" id="ccmrHistAgg9am">
                    <span class="ccmr-tat-badge__icon">
                        <i class="fa-regular fa-clock"></i>
                    </span>
                    <span class="ccmr-tat-badge__body">
                        <span class="ccmr-tat-badge__label">9AM Clear · last 30 d (ex-Sun)</span>
                        <span class="ccmr-tat-badge__value">
                            <span class="ccmr-hist-success" data-role="success">0</span>
                            <span class="ccmr-hist-sep">/</span>
                            <span class="ccmr-hist-missed" data-role="missed">0</span>
                        </span>
                        <span class="ccmr-tat-badge__meta" data-role="meta">no data</span>
                    </span>
                </div>
                {{-- 3 PM Clear (Returns-side) success / missed totals — same
                     30-day, ex-Sunday rule. --}}
                <div class="ccmr-tat-badge ccmr-tat-badge--returns" id="ccmrHistAgg3pm">
                    <span class="ccmr-tat-badge__icon">
                        <i class="fa-solid fa-truck-fast"></i>
                    </span>
                    <span class="ccmr-tat-badge__body">
                        <span class="ccmr-tat-badge__label">3 PM Clear · last 30 d (ex-Sun)</span>
                        <span class="ccmr-tat-badge__value">
                            <span class="ccmr-hist-success" data-role="success">0</span>
                            <span class="ccmr-hist-sep">/</span>
                            <span class="ccmr-hist-missed" data-role="missed">0</span>
                        </span>
                        <span class="ccmr-tat-badge__meta" data-role="meta">no data</span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div id="ccShippingTable"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ================ M / H link modal (shared scope link) ================
         Same markup + IDs as /account-health-master/tabulator so the helper
         JS below can drive it without changes. Saves through the existing
         `account.health.master.scope.link.save` endpoint. --}}
    <div class="modal fade" id="scopeLinkModal" tabindex="-1" aria-labelledby="scopeLinkModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold mb-0" id="scopeLinkModalLabel">Add link</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2">
                        <span id="scope-link-modal-channel">—</span>
                    </div>
                    <label class="form-label small mb-1" for="scope-link-modal-input">URL</label>
                    <input type="url" class="form-control form-control-sm" id="scope-link-modal-input"
                        placeholder="https://…" autocomplete="off">
                    <div class="small text-muted mt-1">Leave blank and save to clear the link.</div>
                    <div class="small text-danger mt-2 d-none" id="scope-link-modal-error"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="scope-link-modal-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ================ CC checklist modal ================
         Triggered by the red-cross "Status" column. Four required items the
         agent confirms before submission. Submit POSTs to the checklist
         store endpoint and refreshes the row's "History" cell in place. --}}
    <div class="modal fade" id="ccmrChecklistModal" tabindex="-1" aria-labelledby="ccmrChecklistLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold mb-0" id="ccmrChecklistLabel">
                        <i class="fa-regular fa-square-check me-1 text-primary"></i>
                        <span id="ccmrChecklistKind">CC Messages</span> Checklist
                        <span class="badge bg-info-subtle text-info ms-2" id="ccmrChecklistChannel">—</span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{-- 9AM-Clear window banner — shown only on the
                         Messages-side ("9AM Clear") submission. Hidden
                         for Returns submissions, which have no time limit. --}}
                    <div id="ccmrWindowBanner" class="ccmr-window-banner d-none mb-3"></div>
                    <ul class="ccmr-check-list">
                        <li>
                            <input type="checkbox" id="ccmr-chk-1" data-field="messages_resolved">
                            <label for="ccmr-chk-1"><span data-chk-idx="0">1. All cancellation done</span></label>
                        </li>
                        <li>
                            <input type="checkbox" id="ccmr-chk-2" data-field="unresolved_messages_followup">
                            <label for="ccmr-chk-2"><span data-chk-idx="1">2. All Labels created</span></label>
                        </li>
                        <li>
                            <input type="checkbox" id="ccmr-chk-3" data-field="activity_documented">
                            <label for="ccmr-chk-3"><span data-chk-idx="2">3. All Labels Sent to Dispatch</span></label>
                        </li>
                        <li>
                            <input type="checkbox" id="ccmr-chk-4" data-field="extra_check">
                            <label for="ccmr-chk-4"><span data-chk-idx="3">4. All labels purchased @ lowest price possible</span></label>
                        </li>
                        <li>
                            <input type="checkbox" id="ccmr-chk-5" data-field="extra_check_2">
                            <label for="ccmr-chk-5"><span data-chk-idx="4">5. All Split/Combo Messages Sent</span></label>
                        </li>
                    </ul>
                    <label class="form-label small mt-2 mb-1" for="ccmr-chk-notes">Notes (optional)</label>
                    <textarea id="ccmr-chk-notes" class="form-control form-control-sm" rows="2"
                              placeholder="Anything worth recording for this submission…"></textarea>
                    <div class="small text-danger mt-2 d-none" id="ccmr-chk-error"></div>
                </div>
                <div class="modal-footer py-2">
                    <span class="me-auto small text-muted" id="ccmr-chk-progress">0 / 5 checked</span>
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="ccmr-chk-submit">
                        <i class="fa-solid fa-paper-plane me-1"></i>Submit
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ================ CC history modal ================ --}}
    <div class="modal fade" id="ccmrHistoryModal" tabindex="-1" aria-labelledby="ccmrHistoryLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold mb-0" id="ccmrHistoryLabel">
                        <i class="fa-solid fa-clock-rotate-left me-1 text-primary"></i>
                        <span id="ccmrHistoryKind">CC Messages</span> Checklist History
                        <span class="badge bg-info-subtle text-info ms-2" id="ccmrHistoryChannel">—</span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="ccmrHistoryEmpty" class="text-center text-muted py-4 d-none">
                        No history yet for this channel.
                    </div>
                    <div class="table-responsive">
                        <table class="ccmr-history-table">
                            <thead>
                                <tr>
                                    <th>Date / Time</th>
                                    <th>User</th>
                                    <th class="text-center" data-th-idx="0" title="All cancellation done">1</th>
                                    <th class="text-center" data-th-idx="1" title="All Labels created">2</th>
                                    <th class="text-center" data-th-idx="2" title="All Labels Sent to Dispatch">3</th>
                                    <th class="text-center" data-th-idx="3" title="All labels purchased @ lowest price possible">4</th>
                                    <th class="text-center" data-th-idx="4" title="All Split/Combo Messages Sent">5</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="ccmrHistoryBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-after-vite')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        $(function () {
            // Active channels (with logo + m_link / h_link) pulled from
            // AuditMasterController::ccMessagesReturns(). Each row already
            // includes the resolved scope-wide M and H links.
            const channelsWithLogo = @json($channelsWithLogo);

            // Endpoint shared with /account-health-master/tabulator. POSTs
            // { channel_id, field: 'm_link'|'h_link', value: <url> }.
            const urlScopeLinkSave = @json(route('account.health.master.scope.link.save'));

            // Per-channel CC Shipping checklist endpoints (Messages-side).
            const urlChecklistStore   = @json(route('customer.care.cc.shipping.checklist.store'));
            const urlChecklistHistory = @json(route('customer.care.cc.shipping.checklist.history'));
            // Parallel Returns-side endpoints for the second Status + History
            // pair placed after R link — backed by cc_shipping_returns_checklists.
            const urlReturnsChecklistStore   = @json(route('customer.care.cc.shipping.returns.checklist.store'));
            const urlReturnsChecklistHistory = @json(route('customer.care.cc.shipping.returns.checklist.history'));

            // Lookup table that tells every checklist helper which endpoint
            // to call, which row field carries the latest submission, and
            // what to show in the modal title — driven by a `kind` string
            // of 'messages' (default) or 'returns'. Adding a third workflow
            // later means appending one more entry here.
            const CHECKLIST_KINDS = {
                messages: {
                    storeUrl:      urlChecklistStore,
                    historyUrl:    urlChecklistHistory,
                    rowField:      'latest_checklist',
                    nextField:     'next_value',
                    titleLabel:    'CC Messages',
                    historyLabel:  'CC Messages',
                    // Item labels (one per data-field in order). Numbering
                    // is prepended at render time so we don't have to keep
                    // "1.", "2." in sync. The Shipping page uses FOUR items
                    // for both halves — backed by the extra_check column on
                    // both cc_shipping_*  tables.
                    items: [
                        'All cancellation done',
                        'All Labels created',
                        'All Labels Sent to Dispatch',
                        'All labels purchased @ lowest price possible',
                        'All Split/Combo Messages Sent',
                    ],
                },
                returns: {
                    storeUrl:      urlReturnsChecklistStore,
                    historyUrl:    urlReturnsChecklistHistory,
                    rowField:      'latest_returns_checklist',
                    nextField:     'next_returns_value',
                    titleLabel:    'Returns',
                    historyLabel:  'Returns',
                    items: [
                        'All cancellation done',
                        'All Labels created',
                        'All Labels Sent to Dispatch',
                        'All labels purchased @ lowest price possible',
                        'All Split/Combo Messages Sent',
                    ],
                },
            };

            // "Next" priority value (1..9). Save endpoint is gated server-side
            // to NEXT_EDITOR_EMAILS; this flag controls UI editability only.
            const urlNextValueSave = @json(route('customer.care.cc.shipping.next.store'));
            const canEditNext      = @json($canEditNext ?? false);

            // R link (Returns link) — handled by a local endpoint because the
            // shared AHM scope-link endpoint only accepts m_link / h_link.
            // (The R-link STORAGE itself is shared per-scope across pages.)
            const urlRLinkSave = @json(route('customer.care.cc.messages.returns.r.link.store'));

            // Shipping page "R Next" — writes to cc_shipping_returns_channel_next.
            const urlRNextValueSave = @json(route('customer.care.cc.shipping.r.next.store'));

            // Daily-submission window snapshots — server-side state at
            // page render. Each modal open re-evaluates the open/closed
            // flag client-side from the user's wall clock against the
            // same EST hour range, so a tab left open across the upper
            // bound auto-locks.
            //   - 9AM Clear (Messages side):  09:00–10:00 EST
            //   - 3 PM Clear (Returns side):  15:00–16:00 EST
            const NINE_AM_CLEAR_WINDOW = @json($nineAmClearWindow ?? null);
            const THREE_PM_CLEAR_WINDOW = @json($threePmClearWindow ?? null);

            // Windows keyed by checklist kind. Add more entries here if a
            // future workflow also needs a time gate.
            const SHIPPING_WINDOWS = {
                messages: NINE_AM_CLEAR_WINDOW,
                returns:  THREE_PM_CLEAR_WINDOW,
            };

            // "S link" (Shipping link) — per-channel, lives in
            // cc_shipping_channel_links. Unlike M / H / R link this is NOT
            // scope-shared, so the front-end POSTs { channel_id, value }
            // and the controller updates exactly one row.
            const urlSLinkSave = @json(route('customer.care.cc.shipping.s.link.store'));
            const PER_CHANNEL_LINK_FIELDS = ['s_link', 'r_link'];

            function channelIdsMatch(rowId, targetId) {
                if (rowId == null || targetId == null || rowId === '' || targetId === '') {
                    return false;
                }
                return String(rowId) === String(targetId);
            }

            /** Update exactly one channel row after a per-channel link save (S / R link). */
            function applyPerChannelLinkToTable(field, channelId, newVal) {
                if (!window.__ccmrTable || !PER_CHANNEL_LINK_FIELDS.includes(field)) {
                    return;
                }
                const target = window.__ccmrTable.getRows().find(r =>
                    channelIdsMatch(r.getData().id, channelId));
                if (!target) {
                    return;
                }
                target.update({ [field]: newVal || null });
            }

            // ---- Generic fetch + CSRF helper (copied from tabulator-master) ----
            function csrf() {
                const meta = document.querySelector('meta[name="csrf-token"]');
                return (window.__LaravelCsrfToken) || (meta && meta.getAttribute('content')) || '';
            }

            function api(path, options = {}) {
                const headers = Object.assign({
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }, options.headers || {});
                if (options.body && !(options.body instanceof FormData) && !headers['Content-Type']) {
                    headers['Content-Type'] = 'application/json';
                }
                return fetch(path, Object.assign({ credentials: 'same-origin' }, options, { headers }))
                    .then(r => {
                        if (!r.ok) {
                            return r.json().catch(() => ({})).then(j => Promise.reject({
                                status: r.status, body: j,
                            }));
                        }
                        if (r.status === 204) return {};
                        return r.json();
                    });
            }

            // ---- M / H link modal wiring (copied from tabulator-master) ----
            const scopeLinkModal     = document.getElementById('scopeLinkModal');
            const scopeLinkInput     = document.getElementById('scope-link-modal-input');
            const scopeLinkLabel     = document.getElementById('scopeLinkModalLabel');
            const scopeLinkChannelEl = document.getElementById('scope-link-modal-channel');
            const scopeLinkSaveBtn   = document.getElementById('scope-link-modal-save');
            const scopeLinkErrorEl   = document.getElementById('scope-link-modal-error');
            let scopeLinkCtx = null;
            let scopeLinkInFlight = false;

            function openScopeLinkModal(channelId, channelName, field, currentValue) {
                if (!scopeLinkModal || typeof bootstrap === 'undefined') return;
                if (channelId == null || channelId === '') {
                    console.warn('Cannot edit link: missing channel id');
                    return;
                }
                scopeLinkCtx = { channelId: String(channelId), field };
                if (scopeLinkLabel) {
                    const fieldLabel = field === 'h_link' ? 'H link'
                        : field === 'r_link' ? 'R link'
                        : field === 's_link' ? 'S link'
                        : 'M link';
                    scopeLinkLabel.textContent = (currentValue ? 'Edit ' : 'Add ') + fieldLabel;
                }
                if (scopeLinkChannelEl) scopeLinkChannelEl.textContent = channelName || '';
                if (scopeLinkInput)     scopeLinkInput.value = currentValue || '';
                if (scopeLinkErrorEl) {
                    scopeLinkErrorEl.textContent = '';
                    scopeLinkErrorEl.classList.add('d-none');
                }
                bootstrap.Modal.getOrCreateInstance(scopeLinkModal).show();
                setTimeout(() => {
                    if (scopeLinkInput) {
                        scopeLinkInput.focus();
                        scopeLinkInput.select();
                    }
                }, 200);
            }

            function saveScopeLink(channelId, field, value) {
                // R link is per-channel (cc_returns_channel_links).
                if (field === 'r_link') {
                    return api(urlRLinkSave, {
                        method: 'POST',
                        body: JSON.stringify({ channel_id: channelId, value }),
                    });
                }
                // S link is per-channel and lives in cc_shipping_channel_links.
                if (field === 's_link') {
                    return api(urlSLinkSave, {
                        method: 'POST',
                        body: JSON.stringify({ channel_id: channelId, value }),
                    });
                }
                // Everything else (m_link / h_link) hits the shared AHM
                // scope-link endpoint.
                return api(urlScopeLinkSave, {
                    method: 'POST',
                    body: JSON.stringify({ channel_id: channelId, field, value }),
                });
            }

            function commitScopeLinkFromModal() {
                if (scopeLinkInFlight || !scopeLinkCtx) return;
                const val = scopeLinkInput ? (scopeLinkInput.value || '').trim() : '';
                scopeLinkInFlight = true;
                if (scopeLinkSaveBtn) scopeLinkSaveBtn.disabled = true;
                if (scopeLinkErrorEl) {
                    scopeLinkErrorEl.textContent = '';
                    scopeLinkErrorEl.classList.add('d-none');
                }
                const ctxField = scopeLinkCtx.field;
                const ctxChannelId = scopeLinkCtx.channelId;
                saveScopeLink(ctxChannelId, ctxField, val)
                    .then(resp => {
                        const newVal = (resp && Object.prototype.hasOwnProperty.call(resp, 'value'))
                            ? (resp.value || '')
                            : val;
                        const channelId = (resp && resp.channel_id != null)
                            ? resp.channel_id
                            : ctxChannelId;
                        if (PER_CHANNEL_LINK_FIELDS.includes(ctxField)) {
                            applyPerChannelLinkToTable(ctxField, channelId, newVal);
                        } else if (window.__ccmrTable) {
                            // M / H links are scope-shared (not used on this page's columns).
                            window.__ccmrTable.getRows().forEach(r => {
                                r.update({ [ctxField]: newVal || null });
                            });
                        }
                        if (scopeLinkModal && typeof bootstrap !== 'undefined') {
                            bootstrap.Modal.getOrCreateInstance(scopeLinkModal).hide();
                        }
                    })
                    .catch(e => {
                        const msg = (e && e.body && (e.body.message ||
                            (e.body.errors && JSON.stringify(e.body.errors)))) || 'Could not save link.';
                        if (scopeLinkErrorEl) {
                            scopeLinkErrorEl.textContent = msg;
                            scopeLinkErrorEl.classList.remove('d-none');
                        }
                    })
                    .finally(() => {
                        scopeLinkInFlight = false;
                        if (scopeLinkSaveBtn) scopeLinkSaveBtn.disabled = false;
                    });
            }

            if (scopeLinkSaveBtn) scopeLinkSaveBtn.addEventListener('click', commitScopeLinkFromModal);
            if (scopeLinkInput) {
                scopeLinkInput.addEventListener('keydown', ev => {
                    if (ev.key === 'Enter') {
                        ev.preventDefault();
                        commitScopeLinkFromModal();
                    }
                });
            }

            // ---- Generic link-cell formatter (same shape as tabulator-master) ----
            function genericLinkFormatter(cell, fieldName, iconColorClass, ariaLabel) {
                const data        = cell.getRow().getData() || {};
                const link        = data[fieldName] ? String(data[fieldName]).trim() : '';
                const channelId   = data.id;
                const channelName = data.channel ? String(data.channel) : '';

                const wrap = document.createElement('span');
                wrap.className = 'link-cell-wrap';

                if (!link) {
                    const dot = document.createElement('span');
                    dot.className = 'link-empty-dot';
                    dot.title = 'Click to add link';
                    dot.setAttribute('aria-label', 'Add link');
                    dot.addEventListener('click', ev => {
                        ev.stopPropagation();
                        openScopeLinkModal(channelId, channelName, fieldName, '');
                    });
                    wrap.appendChild(dot);
                    return wrap;
                }

                const a = document.createElement('a');
                a.href = link;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
                a.className = iconColorClass + ' text-decoration-none';
                a.title = link + ' — double-click to edit';
                a.setAttribute('aria-label', ariaLabel);
                a.innerHTML = '<i class="fa-solid fa-link" aria-hidden="true"></i>';
                a.addEventListener('click', ev => ev.stopPropagation());
                a.addEventListener('dblclick', ev => {
                    ev.stopPropagation();
                    ev.preventDefault();
                    openScopeLinkModal(channelId, channelName, fieldName, link);
                });
                wrap.appendChild(a);
                return wrap;
            }

            function mLinkFormatter(cell) {
                return genericLinkFormatter(cell, 'm_link', 'text-primary', 'Open M link');
            }
            function hLinkFormatter(cell) {
                return genericLinkFormatter(cell, 'h_link', 'text-success', 'Open H link');
            }
            function rLinkFormatter(cell) {
                return genericLinkFormatter(cell, 'r_link', 'text-warning', 'Open R link');
            }
            function sLinkFormatter(cell) {
                return genericLinkFormatter(cell, 's_link', 'text-primary', 'Open S link');
            }

            // ---- CC checklist modal wiring ----
            // Five data-fields on the Shipping page (the 4th — extra_check
            // — was added by the 2026_05_23_000700 migration, the 5th —
            // extra_check_2 — by the 2026_05_23_000800 migration).
            const checklistFields = [
                'messages_resolved',
                'unresolved_messages_followup',
                'activity_documented',
                'extra_check',
                'extra_check_2',
            ];
            const checklistModalEl    = document.getElementById('ccmrChecklistModal');
            const checklistChannelEl  = document.getElementById('ccmrChecklistChannel');
            const checklistNotesEl    = document.getElementById('ccmr-chk-notes');
            const checklistErrorEl    = document.getElementById('ccmr-chk-error');
            const checklistSubmitBtn  = document.getElementById('ccmr-chk-submit');
            const checklistProgressEl = document.getElementById('ccmr-chk-progress');
            const checklistInputs     = checklistFields.map(f =>
                document.querySelector('#ccmrChecklistModal input[data-field="' + f + '"]'));
            let checklistCtx = null;
            let checklistInFlight = false;

            function updateChecklistProgress() {
                const total = checklistInputs.length;
                let checked = 0;
                checklistInputs.forEach(inp => {
                    if (inp && inp.checked) checked++;
                    if (inp) {
                        const li = inp.closest('li');
                        if (li) li.classList.toggle('is-checked', inp.checked);
                    }
                });
                if (checklistProgressEl) {
                    checklistProgressEl.textContent = checked + ' / ' + total + ' checked';
                }
                if (checklistSubmitBtn) {
                    // Disable when no box ticked OR when this kind has a
                    // closed time window. Kinds without an entry in
                    // SHIPPING_WINDOWS bypass the time check entirely.
                    const k = checklistCtx && checklistCtx.kind ? checklistCtx.kind : 'messages';
                    const win = SHIPPING_WINDOWS[k];
                    const windowOpen = win ? currentWindowState(win).open : true;
                    checklistSubmitBtn.disabled = (checked === 0) || !windowOpen;
                }
            }

            checklistInputs.forEach(inp => {
                if (inp) inp.addEventListener('change', updateChecklistProgress);
            });

            const checklistKindEl = document.getElementById('ccmrChecklistKind');
            // Spans inside each <label> that carry the visible item text —
            // swapped per kind so Messages and Returns can have completely
            // different wording while sharing one modal and one DB schema.
            const checklistLabelEls = Array.from(
                document.querySelectorAll('#ccmrChecklistModal [data-chk-idx]')
            ).sort((a, b) => Number(a.dataset.chkIdx) - Number(b.dataset.chkIdx));

            function applyChecklistLabels(kind) {
                const cfg = CHECKLIST_KINDS[kind] || CHECKLIST_KINDS.messages;
                const items = Array.isArray(cfg.items) ? cfg.items : [];
                checklistLabelEls.forEach((el, i) => {
                    const text = items[i] || '';
                    el.textContent = (i + 1) + '. ' + text;
                });
            }

            // ---- Submission-window logic ----
            // Both Shipping-page submissions ("9AM Clear" Messages-side
            // and "3 PM Clear" Returns-side) are gated to a one-hour
            // window in America/New_York. We re-evaluate the open/closed
            // flag every time the modal opens so a page that stays open
            // across the upper boundary still locks correctly.
            const windowBannerEl = document.getElementById('ccmrWindowBanner');

            function currentWindowState(win) {
                if (!win) {
                    return { open: true, label: '' };
                }
                try {
                    const fmt = new Intl.DateTimeFormat('en-US', {
                        timeZone: win.tz,
                        hour12:   false,
                        hour:     '2-digit',
                        minute:   '2-digit',
                    });
                    const parts = fmt.formatToParts(new Date());
                    const hh = parseInt((parts.find(p => p.type === 'hour') || {}).value || '0', 10);
                    const mm = (parts.find(p => p.type === 'minute') || {}).value || '00';
                    const open = hh >= win.from_hour && hh < win.to_hour;
                    return {
                        open,
                        label: String(hh).padStart(2, '0') + ':' + mm + ' ' + win.tz,
                    };
                } catch (e) {
                    return {
                        open:  !!win.open,
                        label: (win.now_label || '') + ' ' + win.tz,
                    };
                }
            }

            function applyWindowBanner(kind) {
                if (!windowBannerEl) return;
                const win = SHIPPING_WINDOWS[kind];
                if (!win) {
                    // No time gate for this kind → hide banner, leave
                    // Submit gated only by the "at least one checked" rule.
                    windowBannerEl.classList.add('d-none');
                    windowBannerEl.innerHTML = '';
                    return;
                }
                const state = currentWindowState(win);
                windowBannerEl.classList.remove('d-none', 'is-open', 'is-closed');
                windowBannerEl.classList.add(state.open ? 'is-open' : 'is-closed');
                const winLabel = String(win.from_hour).padStart(2, '0') + ':00'
                    + '–'
                    + String(win.to_hour).padStart(2, '0') + ':00';
                const title = win.label || '';
                windowBannerEl.innerHTML = state.open
                    ? '<i class="fa-solid fa-clock"></i>'
                        + '<span>' + escHtml(title) + ' window OPEN · '
                        + winLabel + ' ' + win.tz
                        + ' · now ' + state.label
                        + '</span>'
                    : '<i class="fa-solid fa-lock"></i>'
                        + '<span>' + escHtml(title) + ' window CLOSED · only '
                        + winLabel + ' ' + win.tz
                        + ' submissions allowed · now ' + state.label
                        + '</span>';
                if (checklistSubmitBtn) {
                    checklistSubmitBtn.disabled = !state.open;
                }
            }

            // ---- Delivery-truck celebration after a successful submit ----
            // Shows the delivery PNG inside the Status cell for 5 seconds,
            // then merges the new submission data into the Tabulator row —
            // which re-runs the formatter and lands on the green ✓ icon.
            const DELIVERY_CELEBRATION_MS = 5000;
            const DELIVERY_IMG_URL = @json(asset('images/cc-shipping-delivery.png'));

            function playDeliveryCelebrationAndUpdate(row, rowFieldName, newRowData) {
                if (!row) return;
                let cell = null;
                try {
                    cell = row.getCell(rowFieldName);
                } catch (e) { cell = null; }
                const el = cell && cell.getElement ? cell.getElement() : null;

                // Fallback: if we can't grab the cell DOM (Tabulator version
                // mismatch / column not found), skip straight to the update.
                if (!el) {
                    row.update({ [rowFieldName]: newRowData });
                    return;
                }

                el.innerHTML = '';
                const wrap = document.createElement('div');
                wrap.className = 'ccmr-delivery-celebration';
                wrap.title = 'Submission saved — updating status…';
                wrap.innerHTML = '<img src="' + DELIVERY_IMG_URL +
                    '" alt="Submission received" class="ccmr-delivery-img"/>';
                el.appendChild(wrap);

                setTimeout(function () {
                    try {
                        // row.update() re-runs the column formatter and
                        // replaces the celebration with the green tick.
                        row.update({ [rowFieldName]: newRowData });
                    } catch (e) {
                        // If the table was torn down (page navigation), do
                        // nothing — the next page load will reflect the
                        // saved row anyway.
                    }
                }, DELIVERY_CELEBRATION_MS);
            }

            function openChecklistModal(channelId, channelName, kind) {
                if (!checklistModalEl || typeof bootstrap === 'undefined') return;
                const k = CHECKLIST_KINDS[kind] ? kind : 'messages';
                checklistCtx = { channelId, channelName, kind: k };
                if (checklistChannelEl) checklistChannelEl.textContent = channelName || '';
                // Swap title + per-item labels so the modal clearly shows
                // whether the agent is submitting the Messages or Returns
                // checklist. Icon, badge, and list structure stay shared.
                if (checklistKindEl) {
                    checklistKindEl.textContent = CHECKLIST_KINDS[k].titleLabel;
                }
                applyChecklistLabels(k);
                checklistInputs.forEach(inp => {
                    if (inp) inp.checked = false;
                });
                if (checklistNotesEl) checklistNotesEl.value = '';
                if (checklistErrorEl) {
                    checklistErrorEl.textContent = '';
                    checklistErrorEl.classList.add('d-none');
                }
                updateChecklistProgress();
                // Show / hide the per-kind window banner and gate the
                // Submit button based on whether the window is open.
                applyWindowBanner(k);
                bootstrap.Modal.getOrCreateInstance(checklistModalEl).show();
            }

            function submitChecklist() {
                if (checklistInFlight || !checklistCtx) return;
                const k = CHECKLIST_KINDS[checklistCtx.kind] ? checklistCtx.kind : 'messages';
                const cfg = CHECKLIST_KINDS[k];
                const payload = { channel_id: checklistCtx.channelId };
                checklistInputs.forEach((inp, i) => {
                    payload[checklistFields[i]] = inp && inp.checked ? 1 : 0;
                });
                const notes = checklistNotesEl ? (checklistNotesEl.value || '').trim() : '';
                if (notes) payload.notes = notes;

                checklistInFlight = true;
                if (checklistSubmitBtn) checklistSubmitBtn.disabled = true;
                if (checklistErrorEl) {
                    checklistErrorEl.textContent = '';
                    checklistErrorEl.classList.add('d-none');
                }

                api(cfg.storeUrl, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                }).then(resp => {
                    const row = window.__ccmrTable && window.__ccmrTable.getRows().find(r =>
                        r.getData().id === checklistCtx.channelId);
                    if (row && resp && resp.row) {
                        // Play the delivery-truck celebration in the Status
                        // cell for 5 seconds, then merge the new submission
                        // into the row — the normal formatter re-runs at
                        // that point and flips the icon to green ✓.
                        playDeliveryCelebrationAndUpdate(row, cfg.rowField, resp.row);
                        // Optimistically bump the 30-day badge: +1 success
                        // / -1 missed for the workflow that was just
                        // submitted to.
                        if (typeof window.__ccmrBumpHistoryBadge === 'function') {
                            window.__ccmrBumpHistoryBadge(k);
                        }
                    }
                    if (checklistModalEl && typeof bootstrap !== 'undefined') {
                        bootstrap.Modal.getOrCreateInstance(checklistModalEl).hide();
                    }
                }).catch(e => {
                    const msg = (e && e.body && (e.body.message ||
                        (e.body.errors && JSON.stringify(e.body.errors)))) || 'Could not save checklist.';
                    if (checklistErrorEl) {
                        checklistErrorEl.textContent = msg;
                        checklistErrorEl.classList.remove('d-none');
                    }
                }).finally(() => {
                    checklistInFlight = false;
                    if (checklistSubmitBtn) checklistSubmitBtn.disabled = false;
                    updateChecklistProgress();
                });
            }
            if (checklistSubmitBtn) checklistSubmitBtn.addEventListener('click', submitChecklist);

            // ---- History modal wiring ----
            const historyModalEl   = document.getElementById('ccmrHistoryModal');
            const historyChannelEl = document.getElementById('ccmrHistoryChannel');
            const historyBodyEl    = document.getElementById('ccmrHistoryBody');
            const historyEmptyEl   = document.getElementById('ccmrHistoryEmpty');
            const historyKindEl    = document.getElementById('ccmrHistoryKind');

            function fmtDateTime(iso) {
                if (!iso) return '—';
                const d = new Date(iso);
                if (isNaN(d.getTime())) return '—';
                const date = d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: '2-digit' });
                const time = d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', hour12: true });
                return date + ' ' + time;
            }

            function fmtRelative(iso) {
                if (!iso) return '';
                const d = new Date(iso);
                if (isNaN(d.getTime())) return '';
                const diffSec = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
                if (diffSec < 60)     return diffSec + 's';
                if (diffSec < 3600)   return Math.floor(diffSec / 60) + 'm';
                if (diffSec < 86400)  return Math.floor(diffSec / 3600) + 'h';
                const days = Math.floor(diffSec / 86400);
                if (days < 30)        return days + 'd';
                if (days < 365)       return Math.floor(days / 30) + 'mo';
                return Math.floor(days / 365) + 'y';
            }

            function escHtml(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            // Numbered <th> cells in the History modal — their `title`
            // tooltips are swapped per kind so hovering shows the right
            // item description for whichever workflow is being viewed.
            const historyThEls = Array.from(
                document.querySelectorAll('#ccmrHistoryModal [data-th-idx]')
            ).sort((a, b) => Number(a.dataset.thIdx) - Number(b.dataset.thIdx));

            function applyHistoryHeaderTooltips(kind) {
                const cfg = CHECKLIST_KINDS[kind] || CHECKLIST_KINDS.messages;
                const items = Array.isArray(cfg.items) ? cfg.items : [];
                historyThEls.forEach((th, i) => {
                    if (items[i]) th.setAttribute('title', items[i]);
                });
            }

            function openHistoryModal(channelId, channelName, kind) {
                if (!historyModalEl || typeof bootstrap === 'undefined') return;
                const k = CHECKLIST_KINDS[kind] ? kind : 'messages';
                const cfg = CHECKLIST_KINDS[k];
                if (historyKindEl)    historyKindEl.textContent = cfg.historyLabel;
                if (historyChannelEl) historyChannelEl.textContent = channelName || '';
                applyHistoryHeaderTooltips(k);
                if (historyBodyEl)    historyBodyEl.innerHTML =
                    '<tr><td colspan="8" class="text-center text-muted py-3">Loading…</td></tr>';
                if (historyEmptyEl)   historyEmptyEl.classList.add('d-none');
                bootstrap.Modal.getOrCreateInstance(historyModalEl).show();

                const url = cfg.historyUrl + '?channel_id=' + encodeURIComponent(channelId) + '&limit=100';
                api(url, { method: 'GET' })
                    .then(resp => {
                        const rows = (resp && resp.rows) || [];
                        if (!rows.length) {
                            if (historyBodyEl) historyBodyEl.innerHTML = '';
                            if (historyEmptyEl) historyEmptyEl.classList.remove('d-none');
                            return;
                        }
                        if (historyEmptyEl) historyEmptyEl.classList.add('d-none');
                        const pill = b => b
                            ? '<span class="ccmr-check-pill is-yes">✓</span>'
                            : '<span class="ccmr-check-pill is-no">✕</span>';
                        if (historyBodyEl) {
                            historyBodyEl.innerHTML = rows.map(r => {
                                // Synthetic "missed" rows have status set
                                // and no checklist booleans — render them
                                // distinctively so the gap is obvious.
                                if (r && r.status === 'missed') {
                                    const when = r.submitted_at
                                        ? new Date(r.submitted_at).toLocaleDateString(undefined, {
                                            year: 'numeric', month: 'short', day: '2-digit'
                                          })
                                        : '—';
                                    return '<tr class="is-missed">' +
                                        '<td>' + escHtml(when) + '</td>' +
                                        '<td colspan="7" class="text-center">' +
                                            '<span class="ccmr-missed-pill">' +
                                                '<span class="ccmr-missed-emoji" aria-hidden="true">😢</span>' +
                                                'missed' +
                                            '</span>' +
                                        '</td>' +
                                    '</tr>';
                                }
                                return '<tr>' +
                                    '<td>' + escHtml(fmtDateTime(r.submitted_at)) + '</td>' +
                                    '<td>' + escHtml(r.user_name || '—') + '</td>' +
                                    '<td class="text-center">' + pill(r.messages_resolved) + '</td>' +
                                    '<td class="text-center">' + pill(r.unresolved_messages_followup) + '</td>' +
                                    '<td class="text-center">' + pill(r.activity_documented) + '</td>' +
                                    '<td class="text-center">' + pill(r.extra_check) + '</td>' +
                                    '<td class="text-center">' + pill(r.extra_check_2) + '</td>' +
                                    '<td>' + escHtml(r.notes || '') + '</td>' +
                                '</tr>';
                            }).join('');
                        }
                    })
                    .catch(() => {
                        if (historyBodyEl) historyBodyEl.innerHTML =
                            '<tr><td colspan="8" class="text-center text-danger py-3">Could not load history.</td></tr>';
                    });
            }

            // ---- Status column formatter ----
            // The icon is green (✓) only when:
            //   - the most recent submission has ALL four boxes ticked, AND
            //   - the submission is still within the freshness window defined
            //     by the channel's "Next" value (in hours).
            // After `next_value` hours from the submission timestamp, the
            // icon automatically reverts to red (✕) — meaning "due again".
            // If Next is unset, a fully-ticked submission stays green forever
            // (no expiry).
            function isStatusFresh(latest, nextValue) {
                if (!latest) return false;
                // Shipping page requires ALL FIVE boxes ticked before the
                // icon flips green (extra_check / extra_check_2 are the 4th
                // and 5th items).
                const allChecked = latest.messages_resolved
                    && latest.unresolved_messages_followup
                    && latest.activity_documented
                    && latest.extra_check
                    && latest.extra_check_2;
                if (!allChecked) return false;

                const hours = parseInt(nextValue, 10);
                if (!hours || isNaN(hours) || hours <= 0) {
                    return true;
                }
                if (!latest.submitted_at) return true;

                const submittedMs = new Date(latest.submitted_at).getTime();
                if (isNaN(submittedMs)) return true;

                const ageMs = Date.now() - submittedMs;
                return ageMs < hours * 3600 * 1000;
            }

            function statusCrossFormatter(cell, kind) {
                const k = CHECKLIST_KINDS[kind] ? kind : 'messages';
                const cfg = CHECKLIST_KINDS[k];
                const data = cell.getRow().getData() || {};
                const channelId = data.id;
                const channelName = data.channel || '';
                const latest = data[cfg.rowField];
                // Each kind reads its own Next field — Messages uses
                // next_value, Returns uses next_returns_value.
                const nextValue = data[cfg.nextField];

                const fresh = isStatusFresh(latest, nextValue);
                const kindWord = cfg.titleLabel;

                // Compose a richer tooltip explaining WHY the icon is the
                // colour it is — helpful for understanding the time-based
                // expiry without having to open the history modal.
                let tip;
                if (!latest) {
                    tip = 'Open ' + kindWord + ' checklist for ' + channelName + ' (no submissions yet)';
                } else if (fresh) {
                    tip = nextValue
                        ? kindWord + ': all items confirmed — fresh for ' + nextValue + 'h from last submission · click to submit again'
                        : kindWord + ': all items confirmed on last submission · click to submit again';
                } else {
                    const allChecked = latest.messages_resolved
                        && latest.unresolved_messages_followup
                        && latest.activity_documented
                        && latest.extra_check
                        && latest.extra_check_2;
                    if (allChecked && nextValue) {
                        tip = kindWord + ': last submission expired (' + nextValue + 'h window passed) · click to re-submit';
                    } else {
                        tip = 'Open ' + kindWord + ' checklist for ' + channelName;
                    }
                }

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ccmr-status-btn' + (fresh ? ' is-complete' : '');
                btn.title = tip;
                btn.setAttribute('aria-label', 'Open ' + kindWord + ' checklist');
                btn.innerHTML = fresh
                    ? '<i class="fa-solid fa-circle-check" aria-hidden="true"></i>'
                    : '<i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>';
                btn.addEventListener('click', ev => {
                    ev.stopPropagation();
                    openChecklistModal(channelId, channelName, k);
                });
                return btn;
            }

            // Thin per-kind wrappers so Tabulator's `(cell)`-only formatter
            // signature can route to the kind-aware implementation above.
            function messagesStatusFormatter(cell) { return statusCrossFormatter(cell, 'messages'); }
            function returnsStatusFormatter(cell)  { return statusCrossFormatter(cell, 'returns'); }

            // ---- "Next" column shared helpers ----
            // The two "Next" columns (Messages-side `Next` and Returns-side
            // `R Next`) share the same dropdown values, the same edit gate,
            // and the same save flow — only the endpoint URL and the row
            // field they update differ. Factored out so both columns stay
            // in lock-step automatically.
            const NEXT_EDITOR_PARAMS = {
                values: {
                    '':  '— (clear)',
                    '1': '1',
                    '2': '2',
                    '3': '3',
                    '4': '4',
                    '5': '5',
                    '6': '6',
                    '7': '7',
                    '8': '8',
                    '9': '9',
                },
                autocomplete: false,
                clearable: false,
                placeholderEmpty: 'Select 1–9',
            };

            function makeNextCellEditedHandler(saveUrl, rowFieldName) {
                return function (cell) {
                    if (!canEditNext) return;
                    const data = cell.getRow().getData();
                    const raw = cell.getValue();
                    const next = (raw === '' || raw === null || raw === undefined)
                        ? null
                        : parseInt(raw, 10);
                    const payload = { channel_id: data.id };
                    if (next !== null && !isNaN(next)) payload.next_value = next;

                    api(saveUrl, {
                        method: 'POST',
                        body: JSON.stringify(payload),
                    }).then(resp => {
                        const newVal = resp && resp.row && resp.row.next_value !== undefined
                            ? resp.row.next_value
                            : (next ?? null);
                        cell.getRow().update({ [rowFieldName]: newVal });
                    }).catch(e => {
                        cell.restoreOldValue();
                        const msg = (e && e.body && (e.body.message ||
                            (e.body.errors && JSON.stringify(e.body.errors))))
                            || 'Could not save Next value.';
                        if (typeof window.alert === 'function') {
                            window.alert(msg);
                        }
                    });
                };
            }

            // ---- "Next" column formatter ----
            // Renders the saved 1..9 value as a small amber pill, or "—" when
            // unset. Tabulator's edit lifecycle handles the actual dropdown.
            function nextValueFormatter(cell) {
                const v = cell.getValue();
                const wrap = document.createElement('span');
                if (v === null || v === undefined || v === '') {
                    wrap.className = 'ccmr-next-pill is-empty';
                    wrap.textContent = '—';
                    wrap.title = canEditNext
                        ? 'Click to set priority (1–9)'
                        : 'Only mgr-operations@5core.com can set this';
                } else {
                    wrap.className = 'ccmr-next-pill';
                    wrap.textContent = String(v).padStart(2, '0');
                    wrap.title = canEditNext
                        ? 'Click to change (1–9). 00 / blank clears.'
                        : 'Set by manager — read-only';
                }
                return wrap;
            }

            function historyCellFormatter(cell, kind) {
                const k = CHECKLIST_KINDS[kind] ? kind : 'messages';
                const cfg = CHECKLIST_KINDS[k];
                const data = cell.getRow().getData() || {};
                const channelId = data.id;
                const channelName = data.channel || '';
                const latest = data[cfg.rowField];

                const wrap = document.createElement('div');
                wrap.className = 'ccmr-history-cell';

                if (!latest) {
                    wrap.classList.add('is-empty');
                    wrap.textContent = 'No submissions yet';
                    return wrap;
                }

                const linkEl = document.createElement('a');
                linkEl.className = 'ccmr-history-link';
                linkEl.href = 'javascript:void(0)';
                linkEl.title = 'View full ' + cfg.historyLabel + ' history';
                linkEl.setAttribute('aria-label', 'View full ' + cfg.historyLabel + ' history');
                linkEl.innerHTML = '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>';
                linkEl.addEventListener('click', ev => {
                    ev.stopPropagation();
                    openHistoryModal(channelId, channelName, k);
                });
                wrap.appendChild(linkEl);

                const userEl = document.createElement('span');
                userEl.className = 'h-user';
                userEl.textContent = latest.user_name || '—';
                wrap.appendChild(userEl);

                const timeEl = document.createElement('span');
                timeEl.className = 'h-time';
                // Compact relative time (e.g. "5m", "2h", "3d"). Full date is
                // available as a tooltip and the History modal still shows
                // the absolute date + time.
                timeEl.textContent = fmtRelative(latest.submitted_at);
                if (latest.submitted_at) {
                    timeEl.title = fmtDateTime(latest.submitted_at);
                }
                wrap.appendChild(timeEl);

                return wrap;
            }

            function messagesHistoryFormatter(cell) { return historyCellFormatter(cell, 'messages'); }
            function returnsHistoryFormatter(cell)  { return historyCellFormatter(cell, 'returns'); }

            // ---- TAT column formatter ----
            // Renders off-hours-minutes (server-computed) as either a bare
            // minute count for short gaps ("45m") or H/M for longer ones
            // ("2h 13m"). Null / no prior submission renders a muted dash.
            function fmtMinutesAsTat(n) {
                if (n === null || n === undefined) return null;
                const m = Math.max(0, parseInt(n, 10));
                if (!isFinite(m)) return null;
                if (m < 60) return m + 'm';
                const h = Math.floor(m / 60);
                const r = m % 60;
                return r === 0 ? (h + 'h') : (h + 'h ' + r + 'm');
            }

            function makeTatCellFormatter(kindLabel) {
                return function (cell) {
                    const v = cell.getValue();
                    const display = fmtMinutesAsTat(v);
                    const wrap = document.createElement('span');
                    if (display === null) {
                        wrap.className = 'ccmr-tat-pill is-empty';
                        wrap.textContent = '—';
                        wrap.title = 'Needs at least two ' + kindLabel + ' submissions to compute a TAT';
                    } else {
                        wrap.className = 'ccmr-tat-pill';
                        wrap.textContent = display;
                        wrap.title = v + ' off-hour minutes between the last two ' + kindLabel
                            + ' submissions (working window 06:00–18:00 excluded)';
                    }
                    return wrap;
                };
            }
            const tatCellFormatter        = makeTatCellFormatter('Messages');
            const returnsTatCellFormatter = makeTatCellFormatter('Returns');

            // ---- Build table data + columns ----
            const tableData = channelsWithLogo.map(row => ({
                id:                       row.id != null ? row.id : null,
                channel:                  row.channel || '',
                logo:                     row.logo    || null,
                m_link:                   row.m_link  || null,
                h_link:                   row.h_link  || null,
                r_link:                   row.r_link  || null,
                s_link:                   row.s_link  || null,
                latest_checklist:         row.latest_checklist         || null,
                latest_returns_checklist: row.latest_returns_checklist || null,
                next_value:               (row.next_value === 0 || row.next_value) ? row.next_value : null,
                next_returns_value:       (row.next_returns_value === 0 || row.next_returns_value) ? row.next_returns_value : null,
                tat_minutes:              (row.tat_minutes === 0 || row.tat_minutes) ? row.tat_minutes : null,
                tat_returns_minutes:      (row.tat_returns_minutes === 0 || row.tat_returns_minutes) ? row.tat_returns_minutes : null,
            }));

            window.__ccmrTable = new Tabulator('#ccShippingTable', {
                data: tableData,
                layout: 'fitColumns',
                rowHeight: 52,
                pagination: 'local',
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100],
                placeholder: 'No channels found in Channel Master.',
                columns: [
                    {
                        title: 'IMG',
                        field: 'logo',
                        width: 90,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: false,
                        formatter: function (cell) {
                            const logo = cell.getValue();
                            const channel = (cell.getRow().getData().channel || '').toString();
                            if (!logo) {
                                return `<span class="channel-logo-placeholder" title="No logo">
                                            <i class="fas fa-image"></i>
                                        </span>`;
                            }
                            const safeChannel = channel.replace(/"/g, '&quot;');
                            const url = `/storage/${logo}`;
                            return `<img src="${url}" alt="${safeChannel}" class="channel-logo-thumb"
                                         onerror="this.outerHTML='<span class=&quot;channel-logo-placeholder&quot;><i class=&quot;fas fa-image&quot;></i></span>'"/>`;
                        },
                    },
                    {
                        title: 'Channels',
                        field: 'channel',
                        hozAlign: 'left',
                        vertAlign: 'middle',
                        headerSort: true,
                        formatter: cell => {
                            const value = (cell.getValue() ?? '').toString();
                            const safe = value
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;');
                            return `<span class="channel-pill">${safe}</span>`;
                        },
                    },
                    {
                        title: 'S link',
                        field: 's_link',
                        width: 80,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: false,
                        headerTooltip: 'S link (Shipping) — per-channel',
                        formatter: sLinkFormatter,
                    },
                    {
                        title: '9AM Clear',
                        field: 'latest_checklist',
                        width: 80,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: false,
                        headerTooltip: 'Open the CC Messages checklist for this channel',
                        formatter: messagesStatusFormatter,
                    },
                    {
                        title: '9 AM Clear history',
                        field: '_history',
                        minWidth: 220,
                        widthGrow: 1,
                        hozAlign: 'left',
                        vertAlign: 'middle',
                        headerSort: false,
                        headerTooltip: 'Latest CC Messages checklist submission — click the magnifier for full history',
                        formatter: messagesHistoryFormatter,
                    },
                    {
                        title: '3 PM Clear',
                        field: 'latest_returns_checklist',
                        width: 90,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: false,
                        headerTooltip: 'Open the Returns checklist for this channel',
                        formatter: returnsStatusFormatter,
                    },
                    {
                        title: '3PM Clear History',
                        field: '_returns_history',
                        minWidth: 220,
                        widthGrow: 1,
                        hozAlign: 'left',
                        vertAlign: 'middle',
                        headerSort: false,
                        headerTooltip: 'Latest Returns checklist submission — click the magnifier for full history',
                        formatter: returnsHistoryFormatter,
                    },
                ],
            });

            // ---- 30-day Success / Missed badge updaters ----
            // Driven entirely by the server-rendered SHIPPING_HISTORY_AGG
            // snapshot — the page re-pulls fresh numbers when reloaded
            // and also after a successful submission (which we increment
            // in place so the badge animates).
            const SHIPPING_HISTORY_AGG = @json($shippingHistoryAgg ?? null);

            function setHistoryBadge(badgeId, agg) {
                const el = document.getElementById(badgeId);
                if (!el) return;
                const successEl = el.querySelector('[data-role="success"]');
                const missedEl  = el.querySelector('[data-role="missed"]');
                const metaEl    = el.querySelector('[data-role="meta"]');
                if (!agg) {
                    if (successEl) successEl.textContent = '—';
                    if (missedEl)  missedEl.textContent  = '—';
                    if (metaEl)    metaEl.textContent    = 'no data';
                    return;
                }
                if (successEl) successEl.textContent = Number(agg.success || 0);
                if (missedEl)  missedEl.textContent  = Number(agg.missed  || 0);
                if (metaEl) {
                    metaEl.textContent = (agg.channels || 0) + ' channels × '
                        + (agg.days || 0) + ' eligible days = '
                        + (agg.total || 0) + ' slots';
                }
            }

            function refreshHistoryBadges() {
                if (!SHIPPING_HISTORY_AGG) return;
                setHistoryBadge('ccmrHistAgg9am', SHIPPING_HISTORY_AGG.nine_am_clear  || null);
                setHistoryBadge('ccmrHistAgg3pm', SHIPPING_HISTORY_AGG.three_pm_clear || null);
            }

            // First render — call straight away because this draws from
            // a static server payload (no race against Tabulator data).
            refreshHistoryBadges();

            // After a successful submission we bump the corresponding
            // workflow's success counter by 1 (capped to total) and
            // decrement missed by 1 (floored at 0). One submission per
            // channel-day, so this is a safe optimistic update without
            // requiring a server round-trip just to re-render the badge.
            window.__ccmrBumpHistoryBadge = function (kind) {
                if (!SHIPPING_HISTORY_AGG) return;
                const key = kind === 'returns' ? 'three_pm_clear' : 'nine_am_clear';
                const agg = SHIPPING_HISTORY_AGG[key];
                if (!agg) return;
                if (agg.success < agg.total) {
                    agg.success += 1;
                    if (agg.missed > 0) agg.missed -= 1;
                }
                refreshHistoryBadges();
            };

            // Re-format every row once a minute so the "Status" icon flips
            // back from green ✓ to red ✕ as soon as the Next-hours window
            // passes, and the relative time in the "History" column ticks
            // forward. We re-format only those rows whose Status COULD have
            // changed (had a fully-ticked submission with a Next value set),
            // to keep this cheap even with hundreds of channels.
            setInterval(function () {
                if (!window.__ccmrTable) return;
                window.__ccmrTable.getRows().forEach(r => {
                    const d = r.getData() || {};
                    if (d.latest_checklist || d.latest_returns_checklist) {
                        // Covers Status expiry + relative time in both the
                        // History and R History cells.
                        r.reformat();
                    }
                });
            }, 60 * 1000);
        });
    </script>
@endsection
