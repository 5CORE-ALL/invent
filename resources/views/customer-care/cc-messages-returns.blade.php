@extends('layouts.vertical', ['title' => 'CC Message & Returns', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Tabulator header — teal pill so it matches the reference design. */
        #ccMessagesReturnsTable .tabulator-header {
            background: #1abc9c;
        }

        #ccMessagesReturnsTable .tabulator-header .tabulator-col {
            background: #1abc9c;
            border-right: 1px solid rgba(255, 255, 255, 0.25);
        }

        #ccMessagesReturnsTable .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            color: #000000;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }

        #ccMessagesReturnsTable .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 10px 14px;
        }

        #ccMessagesReturnsTable .tabulator-col .tabulator-arrow {
            border-bottom-color: #000000 !important;
            border-top-color: #000000 !important;
        }

        /* Keep cell layout simple — let Tabulator handle column widths/positions.
           Vertical centering is requested per-column via the `vertAlign` option
           in the JS column definitions below. */
        #ccMessagesReturnsTable .tabulator-cell {
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
        #ccMessagesReturnsTable .tabulator-cell.tabulator-editable {
            cursor: pointer;
        }
        #ccMessagesReturnsTable .tabulator-cell.tabulator-editable:hover .ccmr-next-pill {
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
                    <i class="ri-message-3-line me-2 text-primary"></i>CC Message &amp; Returns
                </h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript:void(0);">Customer Care</a></li>
                        <li class="breadcrumb-item active">CC Message &amp; Returns</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== TAT averages — quick-glance KPIs above the table ===== --}}
    <div class="row">
        <div class="col-12">
            <div class="ccmr-tat-badges">
                <div class="ccmr-tat-badge ccmr-tat-badge--messages" id="ccmrAvgTatMessages">
                    <span class="ccmr-tat-badge__icon">
                        <i class="fa-regular fa-message"></i>
                    </span>
                    <span class="ccmr-tat-badge__body">
                        <span class="ccmr-tat-badge__label">Avg TAT (Messages)</span>
                        <span class="ccmr-tat-badge__value" data-role="value">—</span>
                        <span class="ccmr-tat-badge__meta" data-role="meta">no data</span>
                    </span>
                </div>
                <div class="ccmr-tat-badge ccmr-tat-badge--returns" id="ccmrAvgTatReturns">
                    <span class="ccmr-tat-badge__icon">
                        <i class="fa-solid fa-rotate-left"></i>
                    </span>
                    <span class="ccmr-tat-badge__body">
                        <span class="ccmr-tat-badge__label">Avg TAT (Returns)</span>
                        <span class="ccmr-tat-badge__value" data-role="value">—</span>
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
                    <div id="ccMessagesReturnsTable"></div>
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
                    <ul class="ccmr-check-list">
                        <li>
                            <input type="checkbox" id="ccmr-chk-1" data-field="messages_resolved">
                            <label for="ccmr-chk-1"><span data-chk-idx="0">1. All messages resolved</span></label>
                        </li>
                        <li>
                            <input type="checkbox" id="ccmr-chk-2" data-field="unresolved_messages_followup">
                            <label for="ccmr-chk-2"><span data-chk-idx="1">2. All unresolved Messages Posted on Follow Up</span></label>
                        </li>
                        <li>
                            <input type="checkbox" id="ccmr-chk-3" data-field="activity_documented">
                            <label for="ccmr-chk-3"><span data-chk-idx="2">3. All Activity documented for Corrective Actions</span></label>
                        </li>
                    </ul>
                    <label class="form-label small mt-2 mb-1" for="ccmr-chk-notes">Notes (optional)</label>
                    <textarea id="ccmr-chk-notes" class="form-control form-control-sm" rows="2"
                              placeholder="Anything worth recording for this submission…"></textarea>
                    <div class="small text-danger mt-2 d-none" id="ccmr-chk-error"></div>
                </div>
                <div class="modal-footer py-2">
                    <span class="me-auto small text-muted" id="ccmr-chk-progress">0 / 4 checked</span>
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
                                    <th class="text-center" data-th-idx="0" title="All messages resolved">1</th>
                                    <th class="text-center" data-th-idx="1" title="Unresolved Messages on Follow Up">2</th>
                                    <th class="text-center" data-th-idx="2" title="Activity documented">3</th>
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

            // Per-channel CC Message & Returns checklist endpoints.
            const urlChecklistStore   = @json(route('customer.care.cc.messages.returns.checklist.store'));
            const urlChecklistHistory = @json(route('customer.care.cc.messages.returns.checklist.history'));
            // Parallel "Returns" checklist endpoints (writes / reads from
            // the separate cc_returns_checklists table). Drives the second
            // Status + History column pair placed after R link.
            const urlReturnsChecklistStore   = @json(route('customer.care.cc.messages.returns.returns.checklist.store'));
            const urlReturnsChecklistHistory = @json(route('customer.care.cc.messages.returns.returns.checklist.history'));

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
                    // "1.", "2." in sync.
                    items: [
                        'All messages resolved',
                        'All unresolved Messages Posted on Follow Up',
                        'All Activity documented for Corrective Actions',
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
                        'All return & Refund Done',
                        'All inventory updated',
                        'All Restocking fees applied',
                    ],
                },
            };

            // "Next" priority value (1..9). Save endpoint is gated server-side
            // to NEXT_EDITOR_EMAILS; this flag controls UI editability only.
            const urlNextValueSave = @json(route('customer.care.cc.messages.returns.next.store'));
            const canEditNext      = @json($canEditNext ?? false);

            // R link (Returns link) — handled by a local endpoint because the
            // shared AHM scope-link endpoint only accepts m_link / h_link.
            const urlRLinkSave = @json(route('customer.care.cc.messages.returns.r.link.store'));

            // "R Next" save endpoint — mirrors the Next endpoint but writes
            // to cc_returns_channel_next and drives the R Status freshness.
            const urlRNextValueSave = @json(route('customer.care.cc.messages.returns.r.next.store'));

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
                scopeLinkCtx = { channelId, field };
                if (scopeLinkLabel) {
                    const fieldLabel = field === 'h_link' ? 'H link'
                        : field === 'r_link' ? 'R link'
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
                // R link is owned by this page; everything else (m_link /
                // h_link) hits the shared AHM endpoint so the two pages
                // stay in sync.
                if (field === 'r_link') {
                    return api(urlRLinkSave, {
                        method: 'POST',
                        body: JSON.stringify({ channel_id: channelId, value }),
                    });
                }
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
                saveScopeLink(scopeLinkCtx.channelId, scopeLinkCtx.field, val)
                    .then(resp => {
                        // Propagate the new value to every row in the same scope
                        // so the user sees the new link icon immediately without
                        // a page reload. The save endpoint stores per-scope, and
                        // the response includes the canonical value back.
                        const newVal = (resp && Object.prototype.hasOwnProperty.call(resp, 'value'))
                            ? (resp.value || '')
                            : val;
                        const field = scopeLinkCtx.field;
                        if (window.__ccmrTable) {
                            window.__ccmrTable.getRows().forEach(r => {
                                const data = r.getData();
                                r.update({ [field]: newVal || null });
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

            // ---- CC checklist modal wiring ----
            const checklistFields = [
                'messages_resolved',
                'unresolved_messages_followup',
                'activity_documented',
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
                    checklistSubmitBtn.disabled = checked === 0;
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
                        row.update({ [cfg.rowField]: resp.row });
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
                    '<tr><td colspan="6" class="text-center text-muted py-3">Loading…</td></tr>';
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
                            historyBodyEl.innerHTML = rows.map(r => (
                                '<tr>' +
                                    '<td>' + escHtml(fmtDateTime(r.submitted_at)) + '</td>' +
                                    '<td>' + escHtml(r.user_name || '—') + '</td>' +
                                    '<td class="text-center">' + pill(r.messages_resolved) + '</td>' +
                                    '<td class="text-center">' + pill(r.unresolved_messages_followup) + '</td>' +
                                    '<td class="text-center">' + pill(r.activity_documented) + '</td>' +
                                    '<td>' + escHtml(r.notes || '') + '</td>' +
                                '</tr>'
                            )).join('');
                        }
                    })
                    .catch(() => {
                        if (historyBodyEl) historyBodyEl.innerHTML =
                            '<tr><td colspan="6" class="text-center text-danger py-3">Could not load history.</td></tr>';
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
                const allChecked = latest.messages_resolved
                    && latest.unresolved_messages_followup && latest.activity_documented;
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
                        && latest.unresolved_messages_followup && latest.activity_documented;
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
                id:                       row.id      || null,
                channel:                  row.channel || '',
                logo:                     row.logo    || null,
                m_link:                   row.m_link  || null,
                h_link:                   row.h_link  || null,
                r_link:                   row.r_link  || null,
                latest_checklist:         row.latest_checklist         || null,
                latest_returns_checklist: row.latest_returns_checklist || null,
                next_value:               (row.next_value === 0 || row.next_value) ? row.next_value : null,
                next_returns_value:       (row.next_returns_value === 0 || row.next_returns_value) ? row.next_returns_value : null,
                tat_minutes:              (row.tat_minutes === 0 || row.tat_minutes) ? row.tat_minutes : null,
                tat_returns_minutes:      (row.tat_returns_minutes === 0 || row.tat_returns_minutes) ? row.tat_returns_minutes : null,
            }));

            window.__ccmrTable = new Tabulator('#ccMessagesReturnsTable', {
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
                        title: 'M link',
                        field: 'm_link',
                        width: 80,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: false,
                        headerTooltip: "M link from the marketplace's factors",
                        formatter: mLinkFormatter,
                    },
                    {
                        title: 'Status',
                        field: 'latest_checklist',
                        width: 80,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: false,
                        headerTooltip: 'Open the CC Messages checklist for this channel',
                        formatter: messagesStatusFormatter,
                    },
                    {
                        title: 'History',
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
                        title: 'TAT',
                        field: 'tat_minutes',
                        width: 90,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: true,
                        sorter: 'number',
                        headerTooltip: 'Turn-around-time between the last two Messages submissions, '
                            + 'in off-hour minutes only (working window 06:00–18:00 excluded)',
                        formatter: tatCellFormatter,
                    },
                    {
                        title: 'Next',
                        field: 'next_value',
                        width: 80,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: true,
                        headerTooltip: canEditNext
                            ? 'Priority 1–9 — drives the Messages Status freshness window'
                            : 'Priority 1–9 (manager-only edit)',
                        formatter: nextValueFormatter,
                        editable: () => !!canEditNext,
                        editor: 'list',
                        editorParams: NEXT_EDITOR_PARAMS,
                        cellEdited: makeNextCellEditedHandler(urlNextValueSave, 'next_value'),
                    },
                    {
                        title: 'R link',
                        field: 'r_link',
                        width: 80,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: false,
                        headerTooltip: "R link (Returns) — shared per scope",
                        formatter: rLinkFormatter,
                    },
                    {
                        title: 'R Status',
                        field: 'latest_returns_checklist',
                        width: 90,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: false,
                        headerTooltip: 'Open the Returns checklist for this channel',
                        formatter: returnsStatusFormatter,
                    },
                    {
                        title: 'R History',
                        field: '_returns_history',
                        minWidth: 220,
                        widthGrow: 1,
                        hozAlign: 'left',
                        vertAlign: 'middle',
                        headerSort: false,
                        headerTooltip: 'Latest Returns checklist submission — click the magnifier for full history',
                        formatter: returnsHistoryFormatter,
                    },
                    {
                        title: 'R TAT',
                        field: 'tat_returns_minutes',
                        width: 90,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: true,
                        sorter: 'number',
                        headerTooltip: 'Turn-around-time between the last two Returns submissions, '
                            + 'in off-hour minutes only (working window 06:00–18:00 excluded)',
                        formatter: returnsTatCellFormatter,
                    },
                    {
                        title: 'R Next',
                        field: 'next_returns_value',
                        width: 90,
                        hozAlign: 'center',
                        vertAlign: 'middle',
                        headerSort: true,
                        headerTooltip: canEditNext
                            ? 'Priority 1–9 — drives the Returns (R Status) freshness window'
                            : 'Priority 1–9 (manager-only edit) — drives R Status freshness',
                        formatter: nextValueFormatter,
                        editable: () => !!canEditNext,
                        editor: 'list',
                        editorParams: NEXT_EDITOR_PARAMS,
                        cellEdited: makeNextCellEditedHandler(urlRNextValueSave, 'next_returns_value'),
                    },
                ],
            });

            // ---- Avg-TAT badge updaters ----
            // Read straight from whichever rows currently sit in the table
            // (post-filter / post-update) so the badges stay in sync as
            // values change in place.
            function avgOfField(rows, field) {
                let sum = 0, n = 0;
                rows.forEach(r => {
                    const v = r.getData()[field];
                    if (v !== null && v !== undefined && isFinite(v)) {
                        sum += Number(v);
                        n++;
                    }
                });
                return n > 0 ? { avg: Math.round(sum / n), count: n } : { avg: null, count: 0 };
            }

            function setBadge(badgeId, field) {
                const el = document.getElementById(badgeId);
                if (!el || !window.__ccmrTable) return;
                const rows = window.__ccmrTable.getRows();
                const { avg, count } = avgOfField(rows, field);
                const valueEl = el.querySelector('[data-role="value"]');
                const metaEl  = el.querySelector('[data-role="meta"]');
                if (valueEl) valueEl.textContent = (avg === null) ? '—' : fmtMinutesAsTat(avg);
                if (metaEl)  metaEl.textContent  = (count === 0)
                    ? 'no data'
                    : ('across ' + count + ' channel' + (count === 1 ? '' : 's'));
            }

            function refreshAvgTatBadges() {
                setBadge('ccmrAvgTatMessages', 'tat_minutes');
                setBadge('ccmrAvgTatReturns',  'tat_returns_minutes');
            }

            // Tabulator v6 builds the table asynchronously after the
            // constructor returns, so calling refreshAvgTatBadges() right
            // here would race against an empty getRows(). Wait for the
            // `tableBuilt` event instead — it fires once the rows actually
            // exist in the DOM.
            if (window.__ccmrTable && typeof window.__ccmrTable.on === 'function') {
                window.__ccmrTable.on('tableBuilt', refreshAvgTatBadges);
                window.__ccmrTable.on('dataLoaded', refreshAvgTatBadges);
                window.__ccmrTable.on('renderComplete', refreshAvgTatBadges);
            }
            // Cheap safety net for any environment where the Tabulator
            // event system isn't available — fire once on the next tick.
            setTimeout(refreshAvgTatBadges, 0);

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
                // TAT values themselves don't drift over time (they're
                // anchored to stored submission timestamps), but the
                // average DOES change when a row is updated in place
                // after a new submission. Cheap to re-run.
                refreshAvgTatBadges();
            }, 60 * 1000);
        });
    </script>
@endsection
