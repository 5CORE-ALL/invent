@extends('layouts.vertical', ['title' => 'CC Message Health'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #account-health-tabulator .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            font-weight: 600;
        }

        #account-health-tabulator .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: #fff;
        }

        #account-health-tabulator .tabulator .tabulator-tableholder .tabulator-frozen {
            z-index: 2;
        }

        .ahm-section-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6b7280;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .ahm-icon-btn {
            width: 2rem;
            height: 2rem;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        #account-health-tabulator .ahm-channel-logo {
            max-width: 36px;
            max-height: 36px;
            object-fit: contain;
            display: inline-block;
        }

        #account-health-tabulator .ahm-channel-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 4px;
            background: #f3f4f6;
            color: #9ca3af;
            font-size: 0.75rem;
        }

        .cc-health-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            margin-left: 6px;
            vertical-align: middle;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08) inset;
        }

        .cc-health-dot.green {
            background: #16a34a;
        }

        .cc-health-dot.red {
            background: #dc2626;
        }

        .cc-health-dot.yellow {
            background: #eab308;
        }

        .cc-health-dot.gray {
            background: #9ca3af;
        }

        .cc-health-cell {
            cursor: pointer;
            font-weight: 600;
        }

        .cc-health-cell.missing {
            color: #6b7280;
            font-weight: 400;
        }

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

        .cc-add-dot {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #2563eb;
            color: #fff;
            font-size: 11px;
            line-height: 1;
            cursor: pointer;
            vertical-align: middle;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08) inset;
        }

        .cc-add-dot:hover {
            background: #1d4ed8;
        }

        .cc-audit-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 6px;
            background: #f3f4f6;
            color: #2563eb;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
        }

        .cc-audit-icon:hover {
            background: #2563eb;
            color: #fff;
        }

        .cc-audit-check-yes {
            color: #16a34a;
        }

        .cc-audit-check-no {
            color: #dc2626;
        }

        .cc-avg-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 18px;
            border-radius: 999px;
            color: #fff;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            user-select: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.12);
            line-height: 1.2;
        }

        .cc-avg-badge i {
            font-size: 1.05rem;
        }

        .cc-avg-badge:hover {
            filter: brightness(0.95);
        }

        /* Threshold colors used by the avg badge and the CC health column value. */
        .cc-threshold-green {
            background: #16a34a;
            color: #fff;
        }

        .cc-threshold-yellow {
            background: #eab308;
            color: #111827;
        }

        .cc-threshold-red {
            background: #dc2626;
            color: #fff;
        }

        .cc-threshold-gray {
            background: #9ca3af;
            color: #fff;
        }

        .cc-threshold-chip {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            line-height: 1.4;
        }

        /* Threshold pill applied to the CC health value cell. */
        .cc-health-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 64px;
            padding: 3px 10px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
            line-height: 1.2;
        }

        .cc-health-pill.cc-threshold-gray {
            color: #fff;
        }

        #ccAvgTopPanel {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100vw;
            height: 15vh;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
            z-index: 1080;
            transform: translateY(-100%);
            transition: transform 0.18s ease-out;
            display: flex;
            flex-direction: column;
            padding: 6px 12px 4px;
        }

        #ccAvgTopPanel.is-open {
            transform: translateY(0);
        }

        #ccAvgTopPanel .cc-avg-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8rem;
            font-weight: 600;
            color: #111827;
            line-height: 1.2;
        }

        #ccAvgTopPanel .cc-avg-close {
            background: transparent;
            border: 0;
            padding: 0 6px;
            color: #6b7280;
            font-size: 1rem;
            cursor: pointer;
        }

        #ccAvgTopPanel .cc-avg-close:hover {
            color: #111827;
        }

        #ccAvgTopPanel .cc-avg-canvas-wrap {
            flex: 1 1 auto;
            min-height: 0;
            position: relative;
        }

        #ccAvgTopPanel canvas {
            position: absolute;
            inset: 0;
            width: 100% !important;
            height: 100% !important;
        }

        /* Full-width history modal (theme uses --tz-modal-width / --tz-modal-margin). */
        #ccHealthHistoryModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        #ccHealthHistoryModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }

        #ccHealthHistoryModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <h4 class="page-title mb-0">CC Message Health</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <span class="fw-semibold">CC Message Health</span>
                        <span id="cc-avg-badge" class="cc-avg-badge cc-threshold-gray"
                            title="Today's average CC Message Health — click for chart">
                            <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                            <span>Avg: <span id="cc-avg-badge-value">—</span></span>
                        </span>
                    </div>
                    <span class="small text-muted">
                        <span class="cc-threshold-chip cc-threshold-green me-1">&ge; 99%</span>
                        <span class="cc-threshold-chip cc-threshold-yellow me-1">98–99%</span>
                        <span class="cc-threshold-chip cc-threshold-red me-1">&lt; 98%</span>
                        <span class="cc-threshold-chip cc-threshold-gray">missing</span>
                    </span>
                </div>
                <div class="card-body p-0">
                    <div id="account-health-tabulator"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="ccAvgTopPanel" aria-hidden="true">
        <div class="cc-avg-header">
            <span><i class="fa-solid fa-chart-line me-1"></i>Daily average CC Message Health — last 30 days (<span id="cc-avg-panel-today">—</span> today)</span>
            <button type="button" class="cc-avg-close" id="cc-avg-close" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="cc-avg-canvas-wrap">
            <canvas id="cc-avg-chart"></canvas>
        </div>
    </div>

    <div class="modal fade" id="ccAuditModal" tabindex="-1" aria-labelledby="ccAuditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold mb-0" id="ccAuditModalLabel">
                        <i class="fa-solid fa-clipboard-check me-1"></i>
                        CC Message Health audit — <span id="cc-audit-channel">—</span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="ahm-section-title">New audit</p>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="cc-audit-c1"
                                    data-key="all_messages_cleared">
                                <label class="form-check-label small" for="cc-audit-c1">All messages cleared</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="cc-audit-c2"
                                    data-key="all_messages_replied_correctly">
                                <label class="form-check-label small" for="cc-audit-c2">All messages replied
                                    correctly</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="cc-audit-c3"
                                    data-key="all_messages_noted_in_all_issues">
                                <label class="form-check-label small" for="cc-audit-c3">All messages noted in All
                                    Issues</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="cc-audit-c4"
                                    data-key="all_followup_created_cleared_on_time">
                                <label class="form-check-label small" for="cc-audit-c4">All follow-up created &amp;
                                    cleared on time</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="cc-audit-remarks">Auditor remarks</label>
                        <textarea id="cc-audit-remarks" class="form-control form-control-sm" rows="2"
                            placeholder="Optional notes…"></textarea>
                    </div>
                    <div class="small text-muted mb-2">Date / time and your user are recorded automatically.</div>
                    <div class="small text-danger mb-2 d-none" id="cc-audit-error"></div>
                    <div class="d-flex justify-content-end gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-sm btn-primary" id="cc-audit-save">Save audit</button>
                    </div>

                    <p class="ahm-section-title">Audit history</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" id="cc-audit-history-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:130px;">When</th>
                                    <th style="width:130px;">User</th>
                                    <th class="text-center" style="width:60px;" title="All messages cleared">Cleared
                                    </th>
                                    <th class="text-center" style="width:60px;" title="All messages replied correctly">
                                        Replied</th>
                                    <th class="text-center" style="width:60px;"
                                        title="All messages noted in All Issues">Noted</th>
                                    <th class="text-center" style="width:60px;"
                                        title="All follow-up created &amp; cleared on time">Follow-up</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="cc-audit-history-tbody"></tbody>
                        </table>
                        <p class="text-muted small mb-0 mt-1 d-none" id="cc-audit-history-empty">No audits yet for this
                            marketplace.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ccHealthAddModal" tabindex="-1" aria-labelledby="ccHealthAddModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-semibold mb-0" id="ccHealthAddModalLabel">Add CC Message Health value</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2">
                        <span id="cc-add-modal-channel">—</span>
                    </div>
                    <label class="form-label small mb-1" for="cc-add-modal-value">Value</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                        id="cc-add-modal-value" placeholder="0.00" autocomplete="off">
                    <div class="small text-muted mt-1">Saved with today's date, current time, and your user
                        automatically.</div>
                    <div class="small text-danger mt-2 d-none" id="cc-add-modal-error"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="cc-add-modal-save">Save</button>
                </div>
            </div>
        </div>
    </div>

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

    <div class="modal fade p-0" id="ccHealthHistoryModal" tabindex="-1" aria-labelledby="ccHealthHistoryLabel"
        aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow: hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size: 13px;" id="ccHealthHistoryLabel">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="cc-history-title">CC Message Health — Rolling 32 Days</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="cc-history-range" class="form-select form-select-sm bg-white"
                            style="width: 110px; height: 26px; font-size: 11px; padding: 1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="30">30 Days</option>
                            <option value="32" selected>32 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="180">180 Days</option>
                            <option value="365">365 Days</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size: 10px;"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="cc-history-container" style="height: 20vh; display: flex; align-items: stretch;">
                        <div style="flex: 1; min-width: 0; position: relative;">
                            <canvas id="cc-history-chart"></canvas>
                        </div>
                        <div id="cc-history-ref-panel"
                            style="width: 100px; display: flex; flex-direction: column; justify-content: center; gap: 8px; padding: 6px 8px; border-left: 1px solid #e9ecef; background: #f8f9fa; border-radius: 0 4px 4px 0;">
                            <div style="text-align: center;">
                                <div
                                    style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #198754; margin-bottom: 1px;">
                                    Highest</div>
                                <div id="cc-history-highest" style="font-size: 13px; font-weight: 700; color: #198754;">
                                    —</div>
                            </div>
                            <div style="text-align: center; border-top: 1px dashed #adb5bd; border-bottom: 1px dashed #adb5bd; padding: 4px 0;">
                                <div
                                    style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #6c757d; margin-bottom: 1px;">
                                    Median</div>
                                <div id="cc-history-median" style="font-size: 13px; font-weight: 700; color: #6c757d;">
                                    —</div>
                            </div>
                            <div style="text-align: center;">
                                <div
                                    style="font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #dc3545; margin-bottom: 1px;">
                                    Lowest</div>
                                <div id="cc-history-lowest" style="font-size: 13px; font-weight: 700; color: #dc3545;">
                                    —</div>
                            </div>
                        </div>
                    </div>
                    <div id="cc-history-nodata" class="text-center py-3" style="display:none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No CC Message Health data yet for this marketplace.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const el = document.getElementById('account-health-tabulator');
            if (!el) {
                return;
            }

            const urlData = @json(route('account.health.master.tabulator.data'));
            const urlCcSave = @json(route('account.health.master.cc.health.save'));
            const urlCcHistory = @json(route('account.health.master.cc.health.history'));
            const urlCcDailyAvg = @json(route('account.health.master.cc.health.daily.average'));
            const urlScopeLinkSave = @json(route('account.health.master.scope.link.save'));
            const urlCcAuditSave = @json(route('account.health.master.cc.audit.save'));
            const urlCcAuditHistory = @json(route('account.health.master.cc.audit.history'));

            function csrf() {
                return window.__LaravelCsrfToken ||
                    (document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]')
                        .getAttribute('content')) || '';
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
                return fetch(path, Object.assign({
                    credentials: 'same-origin'
                }, options, {
                    headers
                })).then(r => {
                    if (!r.ok) {
                        return r.json().catch(() => ({})).then(j => Promise.reject({
                            status: r.status,
                            body: j
                        }));
                    }
                    if (r.status === 204) {
                        return {};
                    }
                    return r.json();
                });
            }

            function escAttr(s) {
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;');
            }

            function escHtml(s) {
                return escAttr(s);
            }

            let table = null;


            function channelLogoFormatter(cell) {
                const row = cell.getRow();
                const data = row ? row.getData() : {};
                const logo = data && data.logo ? String(data.logo).trim() : '';
                const channel = data && data.channel ? String(data.channel) : '';
                if (!logo) {
                    return '<span class="ahm-channel-logo-placeholder" title="No logo">—</span>';
                }
                return '<img src="/storage/' + escAttr(logo) + '" alt="' + escAttr(channel) +
                    '" class="ahm-channel-logo" onerror="this.outerHTML=\'<span class=&quot;ahm-channel-logo-placeholder&quot; title=&quot;No logo&quot;>—</span>\'" />';
            }

            function formatCcHealthValue(v) {
                if (v === null || v === undefined || v === '') {
                    return '—';
                }
                const n = Number(v);
                if (!isFinite(n)) {
                    return '—';
                }
                return n.toFixed(2) + '%';
            }

            // Threshold-based color classes used by the avg badge and the CC health cell.
            //   green : value >= 99
            //   yellow: 98 <= value < 99
            //   red   : value < 98
            //   gray  : value missing / NaN
            function ccHealthThresholdClass(v) {
                if (v === null || v === undefined || v === '') {
                    return 'cc-threshold-gray';
                }
                const n = Number(v);
                if (!isFinite(n)) {
                    return 'cc-threshold-gray';
                }
                if (n >= 99) {
                    return 'cc-threshold-green';
                }
                if (n >= 98) {
                    return 'cc-threshold-yellow';
                }
                return 'cc-threshold-red';
            }

            function ccHealthFormatter(cell) {
                const data = cell.getRow().getData();
                const v = (data && (data.cc_health_today !== null && data.cc_health_today !== undefined)) ?
                    data.cc_health_today : null;
                const missing = (v === null || v === undefined);
                const txt = missing ? '—' : formatCcHealthValue(v);
                const thresholdClass = ccHealthThresholdClass(v);

                const pill = document.createElement('span');
                pill.className = 'cc-health-pill ' + thresholdClass;
                pill.setAttribute('title', missing ? 'No value yet' :
                    ('Click to view history (' + txt + ')'));
                pill.textContent = txt;
                pill.addEventListener('click', function(ev) {
                    ev.stopPropagation();
                    openCcHealthHistoryModal(data);
                });
                pill.addEventListener('dblclick', function(ev) {
                    ev.stopPropagation();
                });

                return pill;
            }

            function ccHealthAddDotFormatter(cell) {
                const data = cell.getRow().getData();
                const addDot = document.createElement('span');
                addDot.className = 'cc-add-dot';
                addDot.title = 'Add current CC Message Health value';
                addDot.setAttribute('aria-label', 'Add current CC Message Health value');
                addDot.innerHTML = '<i class="fa-solid fa-plus" aria-hidden="true"></i>';
                addDot.addEventListener('click', function(ev) {
                    ev.stopPropagation();
                    openCcHealthAddModal(data);
                });
                addDot.addEventListener('dblclick', function(ev) {
                    ev.stopPropagation();
                });
                return addDot;
            }

            function ccAuditIconFormatter(cell) {
                const data = cell.getRow().getData();
                const wrap = document.createElement('span');
                wrap.className = 'cc-audit-icon';
                wrap.title = 'Open CC Message Health audit';
                wrap.setAttribute('aria-label', 'Open CC Message Health audit');
                wrap.innerHTML = '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>';
                wrap.addEventListener('click', function(ev) {
                    ev.stopPropagation();
                    openCcAuditModal(data);
                });
                wrap.addEventListener('dblclick', function(ev) {
                    ev.stopPropagation();
                });
                return wrap;
            }

            // ---- CC Message Health audit modal ----
            const ccAuditModal = document.getElementById('ccAuditModal');
            const ccAuditChannelEl = document.getElementById('cc-audit-channel');
            const ccAuditRemarksEl = document.getElementById('cc-audit-remarks');
            const ccAuditErrorEl = document.getElementById('cc-audit-error');
            const ccAuditSaveBtn = document.getElementById('cc-audit-save');
            const ccAuditHistoryTbody = document.getElementById('cc-audit-history-tbody');
            const ccAuditHistoryEmpty = document.getElementById('cc-audit-history-empty');
            const ccAuditCheckboxes = ccAuditModal ? Array.from(ccAuditModal.querySelectorAll(
                'input[type="checkbox"][data-key]')) : [];
            let ccAuditCtx = null;
            let ccAuditInFlight = false;

            function ccAuditYesNoCell(flag) {
                if (flag) {
                    return '<i class="fa-solid fa-circle-check cc-audit-check-yes" aria-label="Yes"></i>';
                }
                return '<i class="fa-solid fa-circle-xmark cc-audit-check-no" aria-label="No"></i>';
            }

            function renderCcAuditHistory(rows) {
                if (!ccAuditHistoryTbody) {
                    return;
                }
                ccAuditHistoryTbody.innerHTML = '';
                const list = Array.isArray(rows) ? rows : [];
                if (!list.length) {
                    if (ccAuditHistoryEmpty) {
                        ccAuditHistoryEmpty.classList.remove('d-none');
                    }
                    return;
                }
                if (ccAuditHistoryEmpty) {
                    ccAuditHistoryEmpty.classList.add('d-none');
                }
                list.forEach(function(r) {
                    const tr = document.createElement('tr');
                    const remarks = r.auditor_remarks ? String(r.auditor_remarks) : '';
                    tr.innerHTML =
                        '<td class="small text-nowrap">' + escHtml(r.audited_at || '—') + '</td>' +
                        '<td class="small">' + escHtml((r.user && r.user.name) ? r.user.name : '—') +
                        '</td>' +
                        '<td class="text-center">' + ccAuditYesNoCell(r.all_messages_cleared) + '</td>' +
                        '<td class="text-center">' + ccAuditYesNoCell(r.all_messages_replied_correctly) +
                        '</td>' +
                        '<td class="text-center">' + ccAuditYesNoCell(r
                            .all_messages_noted_in_all_issues) + '</td>' +
                        '<td class="text-center">' + ccAuditYesNoCell(r
                            .all_followup_created_cleared_on_time) + '</td>' +
                        '<td class="small">' + (remarks ? escHtml(remarks) :
                            '<span class="text-muted">—</span>') + '</td>';
                    ccAuditHistoryTbody.appendChild(tr);
                });
            }

            function loadCcAuditHistory(channelId) {
                if (!channelId) {
                    return Promise.resolve();
                }
                return api(urlCcAuditHistory + '?channel_id=' + encodeURIComponent(channelId)).then(function(
                    res) {
                    renderCcAuditHistory((res && res.history) ? res.history : []);
                }).catch(function() {
                    renderCcAuditHistory([]);
                });
            }

            function openCcAuditModal(rowData) {
                if (!ccAuditModal || typeof bootstrap === 'undefined' || !rowData) {
                    return;
                }
                ccAuditCtx = {
                    channelId: rowData.id,
                    channelName: rowData.channel || ''
                };
                if (ccAuditChannelEl) {
                    ccAuditChannelEl.textContent = ccAuditCtx.channelName || '—';
                }
                ccAuditCheckboxes.forEach(function(cb) {
                    cb.checked = false;
                });
                if (ccAuditRemarksEl) {
                    ccAuditRemarksEl.value = '';
                }
                if (ccAuditErrorEl) {
                    ccAuditErrorEl.textContent = '';
                    ccAuditErrorEl.classList.add('d-none');
                }
                if (ccAuditHistoryTbody) {
                    ccAuditHistoryTbody.innerHTML = '';
                }
                if (ccAuditHistoryEmpty) {
                    ccAuditHistoryEmpty.classList.add('d-none');
                }
                bootstrap.Modal.getOrCreateInstance(ccAuditModal).show();
                loadCcAuditHistory(ccAuditCtx.channelId);
            }

            function commitCcAudit() {
                if (ccAuditInFlight || !ccAuditCtx) {
                    return;
                }
                const payload = {
                    channel_id: ccAuditCtx.channelId,
                    auditor_remarks: ccAuditRemarksEl ? (ccAuditRemarksEl.value || '').trim() : ''
                };
                ccAuditCheckboxes.forEach(function(cb) {
                    const k = cb.getAttribute('data-key');
                    if (k) {
                        payload[k] = cb.checked ? 1 : 0;
                    }
                });
                ccAuditInFlight = true;
                if (ccAuditSaveBtn) {
                    ccAuditSaveBtn.disabled = true;
                }
                if (ccAuditErrorEl) {
                    ccAuditErrorEl.textContent = '';
                    ccAuditErrorEl.classList.add('d-none');
                }
                api(urlCcAuditSave, {
                    method: 'POST',
                    body: JSON.stringify(payload)
                }).then(function() {
                    ccAuditCheckboxes.forEach(function(cb) {
                        cb.checked = false;
                    });
                    if (ccAuditRemarksEl) {
                        ccAuditRemarksEl.value = '';
                    }
                    return loadCcAuditHistory(ccAuditCtx.channelId);
                }).catch(function(e) {
                    const msg = (e && e.body && (e.body.message || (e.body.errors && JSON.stringify(e
                        .body.errors)))) || 'Could not save audit. Try again.';
                    if (ccAuditErrorEl) {
                        ccAuditErrorEl.textContent = msg;
                        ccAuditErrorEl.classList.remove('d-none');
                    }
                }).finally(function() {
                    ccAuditInFlight = false;
                    if (ccAuditSaveBtn) {
                        ccAuditSaveBtn.disabled = false;
                    }
                });
            }

            if (ccAuditSaveBtn) {
                ccAuditSaveBtn.addEventListener('click', commitCcAudit);
            }

            const ccAddModal = document.getElementById('ccHealthAddModal');
            const ccAddModalChannelEl = document.getElementById('cc-add-modal-channel');
            const ccAddModalValue = document.getElementById('cc-add-modal-value');
            const ccAddModalError = document.getElementById('cc-add-modal-error');
            const ccAddModalSaveBtn = document.getElementById('cc-add-modal-save');
            let ccAddModalCtx = null;
            let ccAddModalInFlight = false;

            function openCcHealthAddModal(rowData) {
                if (!ccAddModal || typeof bootstrap === 'undefined' || !rowData) {
                    return;
                }
                ccAddModalCtx = {
                    channelId: rowData.id,
                    channelName: rowData.channel || ''
                };
                if (ccAddModalChannelEl) {
                    ccAddModalChannelEl.textContent = ccAddModalCtx.channelName || '';
                }
                if (ccAddModalValue) {
                    const cur = (rowData.cc_health_today !== null && rowData.cc_health_today !==
                        undefined) ? Number(rowData.cc_health_today).toFixed(2) : '';
                    ccAddModalValue.value = cur;
                }
                if (ccAddModalError) {
                    ccAddModalError.textContent = '';
                    ccAddModalError.classList.add('d-none');
                }
                bootstrap.Modal.getOrCreateInstance(ccAddModal).show();
                setTimeout(function() {
                    if (ccAddModalValue) {
                        ccAddModalValue.focus();
                        ccAddModalValue.select();
                    }
                }, 200);
            }

            function commitCcHealthFromModal() {
                if (ccAddModalInFlight || !ccAddModalCtx) {
                    return;
                }
                const raw = ccAddModalValue ? (ccAddModalValue.value || '').trim() : '';
                if (raw === '' || isNaN(Number(raw))) {
                    if (ccAddModalError) {
                        ccAddModalError.textContent = 'Enter a numeric value.';
                        ccAddModalError.classList.remove('d-none');
                    }
                    return;
                }
                const payload = {
                    channel_id: ccAddModalCtx.channelId,
                    value: Number(Number(raw).toFixed(2))
                };
                ccAddModalInFlight = true;
                if (ccAddModalSaveBtn) {
                    ccAddModalSaveBtn.disabled = true;
                }
                if (ccAddModalError) {
                    ccAddModalError.textContent = '';
                    ccAddModalError.classList.add('d-none');
                }
                api(urlCcSave, {
                    method: 'POST',
                    body: JSON.stringify(payload)
                }).then(function() {
                    if (ccAddModal && typeof bootstrap !== 'undefined') {
                        bootstrap.Modal.getOrCreateInstance(ccAddModal).hide();
                    }
                    return mountTable();
                }).catch(function(e) {
                    const msg = (e && e.body && (e.body.message || (e.body.errors && JSON.stringify(e
                        .body.errors)))) || 'Could not save. Try again.';
                    if (ccAddModalError) {
                        ccAddModalError.textContent = msg;
                        ccAddModalError.classList.remove('d-none');
                    }
                }).finally(function() {
                    ccAddModalInFlight = false;
                    if (ccAddModalSaveBtn) {
                        ccAddModalSaveBtn.disabled = false;
                    }
                });
            }

            if (ccAddModalSaveBtn) {
                ccAddModalSaveBtn.addEventListener('click', commitCcHealthFromModal);
            }
            if (ccAddModalValue) {
                ccAddModalValue.addEventListener('keydown', function(ev) {
                    if (ev.key === 'Enter') {
                        ev.preventDefault();
                        commitCcHealthFromModal();
                    }
                });
            }

            let ccHistoryChart = null;
            const ccHistoryModal = document.getElementById('ccHealthHistoryModal');
            const ccHistoryTitleEl = document.getElementById('cc-history-title');
            const ccHistoryRangeEl = document.getElementById('cc-history-range');
            const ccHistoryHighestEl = document.getElementById('cc-history-highest');
            const ccHistoryMedianEl = document.getElementById('cc-history-median');
            const ccHistoryLowestEl = document.getElementById('cc-history-lowest');
            const ccHistoryContainer = document.getElementById('cc-history-container');
            const ccHistoryNoDataEl = document.getElementById('cc-history-nodata');
            let ccHistoryCurrentChannel = null;
            let ccHistoryCurrentDays = 32;

            function rangeLabel(d) {
                d = parseInt(d, 10) || 0;
                if (!d) {
                    return 'Lifetime';
                }
                return d + ' Days';
            }

            function ccHistoryFmt(v) {
                const n = Number(v);
                if (!isFinite(n)) {
                    return '—';
                }
                return n.toFixed(2) + '%';
            }

            function renderCcHistoryChart(labels, values) {
                const canvas = document.getElementById('cc-history-chart');
                if (!canvas || typeof Chart === 'undefined') {
                    return;
                }
                if (ccHistoryChart) {
                    ccHistoryChart.destroy();
                    ccHistoryChart = null;
                }

                if (!values.length) {
                    if (ccHistoryContainer) ccHistoryContainer.style.display = 'none';
                    if (ccHistoryNoDataEl) ccHistoryNoDataEl.style.display = '';
                    if (ccHistoryHighestEl) ccHistoryHighestEl.textContent = '—';
                    if (ccHistoryMedianEl) ccHistoryMedianEl.textContent = '—';
                    if (ccHistoryLowestEl) ccHistoryLowestEl.textContent = '—';
                    return;
                }

                if (ccHistoryContainer) ccHistoryContainer.style.display = 'flex';
                if (ccHistoryNoDataEl) ccHistoryNoDataEl.style.display = 'none';

                const dataMin = Math.min.apply(null, values);
                const dataMax = Math.max.apply(null, values);
                const sorted = values.slice().sort(function(a, b) {
                    return a - b;
                });
                const mid = Math.floor(sorted.length / 2);
                const median = sorted.length % 2 !== 0 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;

                const range = (dataMax - dataMin) || 1;
                const yMin = Math.max(0, dataMin - range * 0.1);
                const yMax = dataMax + range * 0.1;

                if (ccHistoryHighestEl) ccHistoryHighestEl.textContent = ccHistoryFmt(dataMax);
                if (ccHistoryMedianEl) ccHistoryMedianEl.textContent = ccHistoryFmt(median);
                if (ccHistoryLowestEl) ccHistoryLowestEl.textContent = ccHistoryFmt(dataMin);

                // CC Health: higher is better, so green = up vs yesterday, red = down, gray = same/first.
                const dotColors = values.map(function(v, i) {
                    if (i === 0) return '#6c757d';
                    if (v > values[i - 1]) return '#28a745';
                    if (v < values[i - 1]) return '#dc3545';
                    return '#6c757d';
                });
                const labelColors = dotColors.slice();

                const medianLinePlugin = {
                    id: 'ccMedianLine',
                    afterDraw: function(chart) {
                        const yScale = chart.scales.y;
                        const xScale = chart.scales.x;
                        const ctx = chart.ctx;
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

                const valueLabelsPlugin = {
                    id: 'ccValueLabels',
                    afterDatasetsDraw: function(chart) {
                        const dataset = chart.data.datasets[0];
                        const meta = chart.getDatasetMeta(0);
                        const ctx = chart.ctx;
                        ctx.save();
                        ctx.font = 'bold 11px Inter, system-ui, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        meta.data.forEach(function(point, i) {
                            const val = dataset.data[i];
                            const offsetY = (i % 2 === 0) ? -10 : -20;
                            ctx.fillStyle = labelColors[i];
                            ctx.fillText(ccHistoryFmt(val), point.x, point.y + offsetY);
                        });
                        ctx.restore();
                    }
                };

                ccHistoryChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'CC Message Health',
                            data: values,
                            backgroundColor: 'rgba(108,117,125,0.08)',
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
                    plugins: [medianLinePlugin, valueLabelsPlugin],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        layout: {
                            padding: {
                                top: 26,
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
                                    label: function(ctx) {
                                        const idx = ctx.dataIndex;
                                        const parts = [];
                                        parts.push('Value: ' + ccHistoryFmt(ctx.raw));
                                        if (idx > 0) {
                                            const diff = ctx.raw - values[idx - 1];
                                            const arrow = diff < 0 ? '▼' : diff > 0 ? '▲' : '▬';
                                            parts.push('vs Yesterday: ' + arrow + ' ' + ccHistoryFmt(Math
                                                .abs(diff)));
                                        }
                                        if (idx >= 7) {
                                            const diff7 = ctx.raw - values[idx - 7];
                                            const arrow7 = diff7 < 0 ? '▼' : diff7 > 0 ? '▲' : '▬';
                                            parts.push('vs 7d Ago: ' + arrow7 + ' ' + ccHistoryFmt(Math
                                                .abs(diff7)));
                                        }
                                        return parts;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                min: yMin,
                                max: yMax,
                                ticks: {
                                    font: {
                                        size: 9
                                    },
                                    callback: function(v) {
                                        return ccHistoryFmt(v);
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 45,
                                    autoSkip: false,
                                    maxTicksLimit: Math.max(labels.length, 31),
                                    font: {
                                        size: 8
                                    }
                                }
                            }
                        }
                    }
                });
            }

            function loadCcHistoryFor(channelId, days) {
                ccHistoryCurrentDays = days;
                return api(urlCcHistory + '?channel_id=' + encodeURIComponent(channelId) + '&days=' +
                    encodeURIComponent(days)).then(function(res) {
                    const rows = (res && res.history) ? res.history : [];
                    const labels = rows.map(function(r) {
                        return r.recorded_on || '';
                    });
                    const values = rows.map(function(r) {
                        return Number(r.value);
                    });
                    renderCcHistoryChart(labels, values);
                }).catch(function() {
                    renderCcHistoryChart([], []);
                });
            }

            function openCcHealthHistoryModal(rowData) {
                if (!rowData || !ccHistoryModal || typeof bootstrap === 'undefined') {
                    return;
                }
                ccHistoryCurrentChannel = rowData;
                const days = parseInt((ccHistoryRangeEl && ccHistoryRangeEl.value) || '32', 10) || 32;
                ccHistoryCurrentDays = days;
                const channelName = rowData.channel || '—';
                if (ccHistoryTitleEl) {
                    ccHistoryTitleEl.textContent = channelName + ' — CC Message Health (Rolling ' +
                        rangeLabel(days) + ')';
                }
                bootstrap.Modal.getOrCreateInstance(ccHistoryModal).show();
                loadCcHistoryFor(rowData.id, days);
            }

            if (ccHistoryRangeEl) {
                ccHistoryRangeEl.addEventListener('change', function() {
                    if (!ccHistoryCurrentChannel) {
                        return;
                    }
                    const days = parseInt(ccHistoryRangeEl.value || '32', 10) || 32;
                    if (ccHistoryTitleEl) {
                        ccHistoryTitleEl.textContent = (ccHistoryCurrentChannel.channel || '—') +
                            ' — CC Message Health (Rolling ' + rangeLabel(days) + ')';
                    }
                    loadCcHistoryFor(ccHistoryCurrentChannel.id, days);
                });
            }

            function saveScopeLink(channelId, field, value) {
                return api(urlScopeLinkSave, {
                    method: 'POST',
                    body: JSON.stringify({
                        channel_id: channelId,
                        field: field,
                        value: value
                    })
                });
            }

            const scopeLinkModal = document.getElementById('scopeLinkModal');
            const scopeLinkInput = document.getElementById('scope-link-modal-input');
            const scopeLinkLabel = document.getElementById('scopeLinkModalLabel');
            const scopeLinkChannelEl = document.getElementById('scope-link-modal-channel');
            const scopeLinkSaveBtn = document.getElementById('scope-link-modal-save');
            const scopeLinkErrorEl = document.getElementById('scope-link-modal-error');
            let scopeLinkCtx = null;
            let scopeLinkInFlight = false;

            function openScopeLinkModal(channelId, channelName, field, currentValue) {
                if (!scopeLinkModal || typeof bootstrap === 'undefined') {
                    return;
                }
                scopeLinkCtx = {
                    channelId: channelId,
                    field: field
                };
                if (scopeLinkLabel) {
                    const fieldLabel = field === 'h_link' ? 'H link' : 'M link';
                    scopeLinkLabel.textContent = (currentValue ? 'Edit ' : 'Add ') + fieldLabel;
                }
                if (scopeLinkChannelEl) {
                    scopeLinkChannelEl.textContent = channelName || '';
                }
                if (scopeLinkInput) {
                    scopeLinkInput.value = currentValue || '';
                }
                if (scopeLinkErrorEl) {
                    scopeLinkErrorEl.textContent = '';
                    scopeLinkErrorEl.classList.add('d-none');
                }
                bootstrap.Modal.getOrCreateInstance(scopeLinkModal).show();
                setTimeout(function() {
                    if (scopeLinkInput) {
                        scopeLinkInput.focus();
                        scopeLinkInput.select();
                    }
                }, 200);
            }

            function commitScopeLinkFromModal() {
                if (scopeLinkInFlight || !scopeLinkCtx) {
                    return;
                }
                const val = scopeLinkInput ? (scopeLinkInput.value || '').trim() : '';
                scopeLinkInFlight = true;
                if (scopeLinkSaveBtn) {
                    scopeLinkSaveBtn.disabled = true;
                }
                if (scopeLinkErrorEl) {
                    scopeLinkErrorEl.textContent = '';
                    scopeLinkErrorEl.classList.add('d-none');
                }
                saveScopeLink(scopeLinkCtx.channelId, scopeLinkCtx.field, val).then(function() {
                    if (scopeLinkModal && typeof bootstrap !== 'undefined') {
                        bootstrap.Modal.getOrCreateInstance(scopeLinkModal).hide();
                    }
                    return mountTable();
                }).catch(function(e) {
                    const msg = (e && e.body && (e.body.message || (e.body.errors && JSON.stringify(e
                        .body.errors)))) || 'Could not save link.';
                    if (scopeLinkErrorEl) {
                        scopeLinkErrorEl.textContent = msg;
                        scopeLinkErrorEl.classList.remove('d-none');
                    }
                }).finally(function() {
                    scopeLinkInFlight = false;
                    if (scopeLinkSaveBtn) {
                        scopeLinkSaveBtn.disabled = false;
                    }
                });
            }

            if (scopeLinkSaveBtn) {
                scopeLinkSaveBtn.addEventListener('click', commitScopeLinkFromModal);
            }
            if (scopeLinkInput) {
                scopeLinkInput.addEventListener('keydown', function(ev) {
                    if (ev.key === 'Enter') {
                        ev.preventDefault();
                        commitScopeLinkFromModal();
                    }
                });
            }

            function genericLinkFormatter(cell, fieldName, iconColorClass, ariaLabel) {
                const row = cell.getRow();
                const data = row ? row.getData() : {};
                const link = data && data[fieldName] ? String(data[fieldName]).trim() : '';
                const channelId = data && data.id;
                const channelName = data && data.channel ? String(data.channel) : '';

                const wrap = document.createElement('span');
                wrap.className = 'link-cell-wrap';

                if (!link) {
                    const dot = document.createElement('span');
                    dot.className = 'link-empty-dot';
                    dot.title = 'Click to add link';
                    dot.setAttribute('aria-label', 'Add link');
                    dot.addEventListener('click', function(ev) {
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
                a.addEventListener('click', function(ev) {
                    ev.stopPropagation();
                });
                a.addEventListener('dblclick', function(ev) {
                    ev.stopPropagation();
                    ev.preventDefault();
                    openScopeLinkModal(channelId, channelName, fieldName, link);
                });
                wrap.appendChild(a);
                return wrap;
            }

            function missingLinkFormatter(cell) {
                return genericLinkFormatter(cell, 'm_link', 'text-primary', 'Open M link');
            }

            function hLinkFormatter(cell) {
                return genericLinkFormatter(cell, 'h_link', 'text-success', 'Open H link');
            }

            function buildTableColumns() {
                return [{
                    title: '#',
                    formatter: 'rownum',
                    hozAlign: 'center',
                    width: 50,
                    headerSort: false
                }, {
                    title: 'Img',
                    field: 'logo',
                    width: 70,
                    hozAlign: 'center',
                    headerSort: false,
                    formatter: channelLogoFormatter
                }, {
                    title: 'Marketplace',
                    field: 'channel',
                    minWidth: 240,
                    widthGrow: 1,
                    frozen: true,
                    formatter: function(cell) {
                        const s = document.createElement('span');
                        s.style.fontWeight = '600';
                        s.textContent = cell.getValue() || '';
                        return s;
                    }
                }, {
                    title: 'M link',
                    field: 'm_link',
                    width: 80,
                    hozAlign: 'center',
                    headerSort: false,
                    headerTooltip: 'M link from the marketplace\'s factors',
                    formatter: missingLinkFormatter
                }, {
                    title: 'Audit',
                    field: '_cc_audit',
                    width: 70,
                    hozAlign: 'center',
                    headerSort: false,
                    headerTooltip: 'Open CC Message Health audit + history',
                    formatter: ccAuditIconFormatter
                }, {
                    title: 'H link',
                    field: 'h_link',
                    width: 80,
                    hozAlign: 'center',
                    headerSort: false,
                    headerTooltip: 'H link from the marketplace\'s factors',
                    formatter: hLinkFormatter
                }, {
                    title: 'CC Message Health',
                    field: 'cc_health_today',
                    width: 130,
                    hozAlign: 'center',
                    sorter: 'number',
                    sorterParams: {
                        alignEmptyValues: 'bottom'
                    },
                    headerSortStartingDir: 'asc',
                    headerTooltip: 'Today\'s CC Message Health value (lowest at the top). Click to view 30-day history.',
                    formatter: ccHealthFormatter
                }, {
                    title: 'Add',
                    field: '_cc_health_add',
                    width: 60,
                    hozAlign: 'center',
                    headerSort: false,
                    headerTooltip: 'Add current CC Message Health value',
                    formatter: ccHealthAddDotFormatter
                }, {
                    field: 'type',
                    title: 'Type',
                    width: 80,
                    visible: false
                }];
            }

            function mountTable() {
                if (table) {
                    table.destroy();
                    table = null;
                }
                return api(urlData).then(function(rows) {
                    table = new Tabulator('#account-health-tabulator', {
                        layout: 'fitColumns',
                        responsiveLayout: false,
                        data: rows,
                        pagination: true,
                        paginationSize: 100,
                        paginationMode: 'local',
                        height: 600,
                        placeholder: 'No active marketplaces in Channel Master',
                        index: 'id',
                        initialSort: [{
                            column: 'cc_health_today',
                            dir: 'asc'
                        }, {
                            column: 'channel',
                            dir: 'asc'
                        }],
                        columns: buildTableColumns()
                    });
                    updateCcAvgBadge(rows);
                });
            }

            // ---- CC Health average badge + top-panel chart ----
            const ccAvgBadge = document.getElementById('cc-avg-badge');
            const ccAvgBadgeValue = document.getElementById('cc-avg-badge-value');
            const ccAvgPanel = document.getElementById('ccAvgTopPanel');
            const ccAvgClose = document.getElementById('cc-avg-close');
            const ccAvgPanelTodayEl = document.getElementById('cc-avg-panel-today');
            let ccAvgChart = null;
            let ccAvgCurrentValue = null;

            function setCcAvgBadgeThresholdClass(value) {
                if (!ccAvgBadge) {
                    return;
                }
                ccAvgBadge.classList.remove('cc-threshold-green', 'cc-threshold-yellow',
                    'cc-threshold-red', 'cc-threshold-gray');
                ccAvgBadge.classList.add(ccHealthThresholdClass(value));
            }

            function updateCcAvgBadge(rows) {
                let sum = 0;
                let count = 0;
                (rows || []).forEach(function(r) {
                    const v = r && r.cc_health_today;
                    if (v === null || v === undefined || v === '') {
                        return;
                    }
                    const n = Number(v);
                    if (!isFinite(n)) {
                        return;
                    }
                    sum += n;
                    count++;
                });
                if (!count) {
                    ccAvgCurrentValue = null;
                    if (ccAvgBadgeValue) {
                        ccAvgBadgeValue.textContent = '—';
                    }
                    setCcAvgBadgeThresholdClass(null);
                    return;
                }
                const avg = sum / count;
                ccAvgCurrentValue = avg;
                if (ccAvgBadgeValue) {
                    ccAvgBadgeValue.textContent = avg.toFixed(2) + '%';
                }
                setCcAvgBadgeThresholdClass(avg);
            }

            function renderCcAvgChart(labels, values) {
                const canvas = document.getElementById('cc-avg-chart');
                if (!canvas || typeof Chart === 'undefined') {
                    return;
                }
                if (ccAvgChart) {
                    ccAvgChart.destroy();
                    ccAvgChart = null;
                }
                ccAvgChart = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Daily average',
                            data: values,
                            borderColor: '#0ea5e9',
                            backgroundColor: 'rgba(14, 165, 233, 0.18)',
                            tension: 0.25,
                            fill: true,
                            pointRadius: 2,
                            pointHoverRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        return 'Avg: ' + Number(ctx.parsed.y).toFixed(2) + '%';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                    autoSkip: true,
                                    maxTicksLimit: 10,
                                    font: {
                                        size: 10
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(v) {
                                        return Number(v).toFixed(2) + '%';
                                    },
                                    font: {
                                        size: 10
                                    }
                                }
                            }
                        }
                    }
                });
            }

            function openCcAvgPanel() {
                if (!ccAvgPanel) {
                    return;
                }
                if (ccAvgPanelTodayEl) {
                    ccAvgPanelTodayEl.textContent = ccAvgCurrentValue !== null ?
                        ccAvgCurrentValue.toFixed(2) + '%' : '—';
                }
                ccAvgPanel.classList.add('is-open');
                ccAvgPanel.setAttribute('aria-hidden', 'false');
                api(urlCcDailyAvg + '?days=30').then(function(res) {
                    const rows = (res && res.history) ? res.history : [];
                    const labels = rows.map(function(r) {
                        return r.recorded_on || '';
                    });
                    const values = rows.map(function(r) {
                        return Number(r.avg);
                    });
                    renderCcAvgChart(labels, values);
                }).catch(function() {
                    renderCcAvgChart([], []);
                });
            }

            function closeCcAvgPanel() {
                if (!ccAvgPanel) {
                    return;
                }
                ccAvgPanel.classList.remove('is-open');
                ccAvgPanel.setAttribute('aria-hidden', 'true');
            }

            if (ccAvgBadge) {
                ccAvgBadge.addEventListener('click', function(ev) {
                    ev.stopPropagation();
                    if (ccAvgPanel && ccAvgPanel.classList.contains('is-open')) {
                        closeCcAvgPanel();
                    } else {
                        openCcAvgPanel();
                    }
                });
            }
            if (ccAvgClose) {
                ccAvgClose.addEventListener('click', function(ev) {
                    ev.stopPropagation();
                    closeCcAvgPanel();
                });
            }
            document.addEventListener('keydown', function(ev) {
                if (ev.key === 'Escape' && ccAvgPanel && ccAvgPanel.classList.contains('is-open')) {
                    closeCcAvgPanel();
                }
            });

            mountTable();
        })();
    </script>
@endsection
