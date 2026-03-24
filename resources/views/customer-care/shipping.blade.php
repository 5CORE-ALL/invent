@extends('layouts.vertical', ['title' => 'Shipping', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .shipping-stat-card {
            border-radius: 0.5rem;
            border: 1px solid #e9ecef;
        }

        .shipping-slot-card {
            border: 1px solid #dee2e6;
        }

        .shipping-slot-card h5 {
            color: #2c6ed5;
        }

        .shipping-table th {
            white-space: nowrap;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Shipping',
        'sub_title' => 'Customer Care',
    ])

    <div class="container-fluid">
        <ul class="nav nav-tabs mb-3" id="shippingTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-overview" data-bs-toggle="tab" data-bs-target="#pane-overview"
                    type="button" role="tab">Overview</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-report" data-bs-toggle="tab" data-bs-target="#pane-report" type="button"
                    role="tab">Report</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-followup" data-bs-toggle="tab" data-bs-target="#pane-followup"
                    type="button" role="tab">Follow-up</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-cleared" data-bs-toggle="tab" data-bs-target="#pane-cleared"
                    type="button" role="tab">Cleared</button>
            </li>
        </ul>

        <div class="tab-content" id="shippingTabContent">
            {{-- OVERVIEW --}}
            <div class="tab-pane fade show active" id="pane-overview" role="tabpanel">
                <p class="text-muted small mb-3">
                    <strong>Performance evaluation</strong> below is calculated from daily report checklists (platforms
                    marked cleared). Automated ship-time metrics from orders can be connected later.
                    <strong>Shipping index</strong> shows last activity per platform from saved report lines.
                </p>
                <div class="row g-3 mb-4" id="overviewStatsRow">
                    <div class="col-6 col-md-4 col-xl">
                        <div class="card shipping-stat-card h-100 shadow-sm">
                            <div class="card-body py-3">
                                <h6 class="text-muted text-uppercase small mb-1">Cleared (window)</h6>
                                <span class="h4 mb-0" id="ovClearedPct">—</span>
                                <div class="small text-muted" id="ovClearedDetail"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl">
                        <div class="card shipping-stat-card h-100 shadow-sm">
                            <div class="card-body py-3">
                                <h6 class="text-muted text-uppercase small mb-1">Open follow-ups</h6>
                                <span class="h4 mb-0 text-warning" id="ovOpenFollowups">—</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl">
                        <div class="card shipping-stat-card h-100 shadow-sm">
                            <div class="card-body py-3">
                                <h6 class="text-muted text-uppercase small mb-1">Report lines</h6>
                                <span class="h4 mb-0" id="ovLinesTotal">—</span>
                                <div class="small text-muted">In selected window</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <span class="fw-semibold">Daily cleared %</span>
                        <div class="d-flex align-items-center gap-2">
                            <label class="small mb-0 text-muted">Days</label>
                            <select class="form-select form-select-sm" id="overviewDays" style="width: 5rem;">
                                <option value="7">7</option>
                                <option value="14" selected>14</option>
                                <option value="30">30</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="overviewRefresh">
                                <i class="mdi mdi-refresh"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0" id="overviewByDayTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Lines</th>
                                        <th>Cleared</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header fw-semibold">Shipping index (platform sync activity)</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0" id="overviewIndexTable">
                                <thead>
                                    <tr>
                                        <th>Platform</th>
                                        <th>Last report activity</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- REPORT --}}
            <div class="tab-pane fade" id="pane-report" role="tabpanel">
                <p class="text-muted small mb-3">
                    Two checkpoints: <strong>9:30 AM EST</strong> and <strong>3:30 PM EST</strong>. Per channel you can add
                    <strong>multiple issues</strong> (different orders/SKUs): <strong>Report issue</strong> for the first,
                    then <strong>Add another issue</strong>. Use <strong>Remove</strong> on a line to delete that issue only.
                    <strong>Cleared</strong> (switch ON) <strong>hides issues from this Report view</strong> only — they
                    stay in the system and remain on <strong>Follow-up</strong> until <strong>Resolved</strong> there; only
                    then they move to the <strong>Cleared</strong> tab (archive). New issues you add after that show on
                    Report again until you clear again.
                </p>
                <div class="row mb-3 g-2 align-items-end">
                    <div class="col-auto">
                        <label class="form-label small mb-0">Report date</label>
                        <input type="date" class="form-control" id="reportDate">
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-primary" id="reportReload">
                            <i class="mdi mdi-reload me-1"></i> Load checklists
                        </button>
                    </div>
                </div>

                @foreach ($timeSlots as $slotKey => $slotLabel)
                    <div class="card shipping-slot-card mb-4" data-slot="{{ $slotKey }}">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">{{ $slotLabel }}</h5>
                            <span class="badge bg-light text-dark">EST</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover shipping-table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Platform</th>
                                            <th style="width: 8rem;">Cleared</th>
                                            <th>Issue summary</th>
                                            <th style="width: 9rem;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="report-slot-body" data-slot="{{ $slotKey }}">
                                        <tr>
                                            <td colspan="4" class="text-muted text-center py-3">Load checklists…</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- FOLLOW-UP --}}
            <div class="tab-pane fade" id="pane-followup" role="tabpanel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <p class="text-muted small mb-0">
                        Mark items <strong>Resolved</strong> when complete. They are removed from this list and stored in
                        <code>shipping_followup_archives</code>. Items still open are <strong>not resolved</strong>.
                    </p>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#shippingManualFollowupModal">
                        <i class="mdi mdi-plus me-1"></i> Add follow-up
                    </button>
                </div>
                <div class="card mb-3">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end small">
                            <div class="col-6 col-md-4 col-lg-2">
                                <label class="form-label mb-0 text-muted">From date</label>
                                <input type="date" class="form-control form-control-sm" id="followupFilterDateFrom">
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <label class="form-label mb-0 text-muted">To date</label>
                                <input type="date" class="form-control form-control-sm" id="followupFilterDateTo">
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <label class="form-label mb-0 text-muted">Channel</label>
                                <select class="form-select form-select-sm" id="followupFilterPlatform">
                                    <option value="">All channels</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <label class="form-label mb-0 text-muted">Slot</label>
                                <select class="form-select form-select-sm" id="followupFilterSlot">
                                    <option value="">All slots</option>
                                    <option value="am_930_est">9:30 AM EST</option>
                                    <option value="pm_330_est">3:30 PM EST</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-lg-2">
                                <label class="form-label mb-0 text-muted">Quick range</label>
                                <select class="form-select form-select-sm" id="followupQuickRange">
                                    <option value="">—</option>
                                    <option value="today">Today</option>
                                    <option value="7">Last 7 days</option>
                                    <option value="30">Last 30 days</option>
                                </select>
                            </div>
                            <div class="col-12 col-lg-4">
                                <label class="form-label mb-0 text-muted">Search (order / SKU / reason)</label>
                                <input type="search" class="form-control form-control-sm" id="followupFilterSearch"
                                    placeholder="Type and Apply">
                            </div>
                            <div class="col-12 col-md-auto d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-sm btn-primary" id="followupFilterApply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="followupFilterReset">Reset</button>
                            </div>
                        </div>
                        <p class="text-muted mb-0 mt-1" style="font-size: 0.75rem;">Leave dates empty to show all open
                            follow-ups (up to 500). Manual follow-ups use <strong>created</strong> date when report date is
                            blank.</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0" id="followupsTable">
                                <thead>
                                    <tr>
                                        <th>Platform</th>
                                        <th>Slot / date</th>
                                        <th>Order</th>
                                        <th>SKU</th>
                                        <th>Reason</th>
                                        <th style="width: 8rem;"></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- CLEARED (archived resolved follow-ups) --}}
            <div class="tab-pane fade" id="pane-cleared" role="tabpanel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <p class="text-muted small mb-0">
                        Rows here are <strong>resolved</strong> follow-ups (moved from Follow-up). <strong>Cleared by</strong>
                        is the user who clicked Resolved. <strong>Restore</strong> puts the item back on Follow-up and, when
                        the report line still exists, re-adds that issue on the Report checklist.
                    </p>
                    <div class="d-flex flex-wrap gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="clearedRefreshBtn">
                            <i class="mdi mdi-refresh me-1"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end small">
                            <div class="col-6 col-md-4 col-lg-2">
                                <label class="form-label mb-0 text-muted">Archived from</label>
                                <input type="date" class="form-control form-control-sm" id="clearedFilterDateFrom">
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <label class="form-label mb-0 text-muted">Archived to</label>
                                <input type="date" class="form-control form-control-sm" id="clearedFilterDateTo">
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <label class="form-label mb-0 text-muted">Channel</label>
                                <select class="form-select form-select-sm" id="clearedFilterPlatform">
                                    <option value="">All channels</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-4 col-lg-2">
                                <label class="form-label mb-0 text-muted">Slot</label>
                                <select class="form-select form-select-sm" id="clearedFilterSlot">
                                    <option value="">All slots</option>
                                    <option value="am_930_est">9:30 AM EST</option>
                                    <option value="pm_330_est">3:30 PM EST</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-lg-2">
                                <label class="form-label mb-0 text-muted">Quick range</label>
                                <select class="form-select form-select-sm" id="clearedQuickRange">
                                    <option value="">—</option>
                                    <option value="today">Today</option>
                                    <option value="7">Last 7 days</option>
                                    <option value="30">Last 30 days</option>
                                </select>
                            </div>
                            <div class="col-12 col-lg-4">
                                <label class="form-label mb-0 text-muted">Search</label>
                                <input type="search" class="form-control form-control-sm" id="clearedFilterSearch"
                                    placeholder="Order / SKU / reason">
                            </div>
                            <div class="col-12 col-md-auto d-flex flex-wrap gap-1">
                                <button type="button" class="btn btn-sm btn-primary" id="clearedFilterApply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearedFilterReset">Reset</button>
                            </div>
                        </div>
                        <p class="text-muted mb-0 mt-1" style="font-size: 0.75rem;">Filters use <strong>archived</strong>
                            date. Empty dates = latest 500 records.</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0" id="clearedTable">
                                <thead>
                                    <tr>
                                        <th>Platform</th>
                                        <th>Slot / date</th>
                                        <th>Order</th>
                                        <th>SKU</th>
                                        <th>Reason</th>
                                        <th>Cleared by</th>
                                        <th>Cleared at</th>
                                        <th style="width: 6rem;"></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Issue modal (report) --}}
    <div class="modal fade" id="shippingIssueModal" tabindex="-1" aria-labelledby="shippingIssueModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shippingIssueModalLabel">Report shipping issue</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3" id="shippingIssuePlatformLabel"></p>
                    <div class="mb-2">
                        <label class="form-label">Order number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="issueOrderNumber" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">SKU <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="issueSku" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="issueReason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="issueModalSave">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Manual follow-up --}}
    <div class="modal fade" id="shippingManualFollowupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add follow-up</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Platform (optional)</label>
                        <select class="form-select" id="manualPlatformId">
                            <option value="">—</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Order number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="manualOrderNumber">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">SKU <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="manualSku">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" id="manualReason" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="manualFollowupSave">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    @php
        $routes = [
            'overview' => route('customer.care.shipping.overview'),
            'platforms' => route('customer.care.shipping.platforms'),
            'reportState' => route('customer.care.shipping.report.state'),
            'reportSave' => route('customer.care.shipping.report.save'),
            'followupsList' => route('customer.care.shipping.followups.list'),
            'followupsStore' => route('customer.care.shipping.followups.store'),
            'followupsResolve' => url('/customer-care/shipping/followups'), // append /{id}/resolve in JS
            'issueDestroy' => url('/customer-care/shipping/report-issues'),
            'clearedList' => route('customer.care.shipping.cleared.list'),
            'clearedRestore' => url('/customer-care/shipping/cleared'),
        ];
    @endphp
    <script>
        (function () {
            const routes = @json($routes);
            const slots = @json($timeSlots);

            /*
             * This inline script runs before the Vite bundle, so `bootstrap` is not defined yet.
             * Defer init until window load so Modal + tab events work and rows/switches render.
             */
            window.addEventListener('load', function shippingCareBoot() {
                if (typeof bootstrap === 'undefined') {
                    console.error('Shipping page: Bootstrap not loaded; checklists cannot run.');
                    return;
                }
                initShippingCarePage();
            }, { once: true });

            const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            async function fetchJson(url, options = {}) {
                const headers = Object.assign({
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest'
                }, options.headers || {});
                if (options.body && !(options.body instanceof FormData) && !headers['Content-Type']) {
                    headers['Content-Type'] = 'application/json';
                }
                const res = await fetch(url, Object.assign({}, options, { headers }));
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    let msg = data.message || data.error || res.statusText;
                    if (typeof msg !== 'string') {
                        if (data.errors && typeof data.errors === 'object') {
                            msg = Object.values(data.errors).flat().filter(Boolean).join(' ') || JSON.stringify(data.errors);
                        } else {
                            msg = msg ? String(msg) : 'Request failed';
                        }
                    }
                    throw new Error(msg || 'Request failed');
                }
                return data;
            }

            function todayDateNY() {
                const fmt = new Intl.DateTimeFormat('en-CA', {
                    timeZone: 'America/New_York',
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit'
                });
                const parts = fmt.formatToParts(new Date());
                const y = parts.find(p => p.type === 'year').value;
                const m = parts.find(p => p.type === 'month').value;
                const d = parts.find(p => p.type === 'day').value;
                return `${y}-${m}-${d}`;
            }

            /* Overview */
            async function loadOverview() {
                const days = document.getElementById('overviewDays').value;
                const data = await fetchJson(routes.overview + '?days=' + encodeURIComponent(days));
                const perf = data.performance || {};
                const pct = perf.cleared_pct;
                document.getElementById('ovClearedPct').textContent = pct != null ? pct + '%' : '—';
                document.getElementById('ovClearedDetail').textContent = pct != null ?
                    `${perf.cleared_total || 0} / ${perf.report_lines_total || 0} lines` : 'No report lines yet';
                document.getElementById('ovOpenFollowups').textContent = data.open_followups ?? '0';
                document.getElementById('ovLinesTotal').textContent = perf.report_lines_total ?? '0';

                const tbody = document.querySelector('#overviewByDayTable tbody');
                tbody.innerHTML = '';
                const byDay = perf.by_day || {};
                const keys = Object.keys(byDay).sort();
                if (!keys.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-2">No data yet</td></tr>';
                } else {
                    keys.forEach(k => {
                        const row = byDay[k];
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${k}</td><td>${row.total}</td><td>${row.cleared}</td><td>${row.pct}%</td>`;
                        tbody.appendChild(tr);
                    });
                }

                const idxBody = document.querySelector('#overviewIndexTable tbody');
                idxBody.innerHTML = '';
                (data.shipping_index || []).forEach(r => {
                    const tr = document.createElement('tr');
                    const ts = r.last_report_activity_at ? new Date(r.last_report_activity_at).toLocaleString() : '—';
                    tr.innerHTML = `<td>${escapeHtml(r.name)}</td><td>${escapeHtml(ts)}</td>`;
                    idxBody.appendChild(tr);
                });
            }

            function escapeHtml(s) {
                if (s == null) return '';
                return String(s).replace(/[&<>"']/g, c => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                } [c]));
            }

            function issuesSummaryHtml(issues, slotKey) {
                if (!issues || !issues.length) {
                    return '<span class="text-muted small">—</span>';
                }
                const lis = issues.map(i => {
                    const r = i.reason ? ' — ' + escapeHtml(i.reason) : '';
                    return `<li class="mb-1">${escapeHtml(i.order_number || '—')} / ${escapeHtml(i.sku || '—')}${r} ` +
                        `<button type="button" class="btn btn-link btn-sm text-danger p-0 align-baseline sh-delete-issue" ` +
                        `data-issue-id="${i.id}" data-slot="${escapeHtml(slotKey)}">Remove</button></li>`;
                }).join('');
                return `<ul class="small mb-0 ps-3">${lis}</ul>`;
            }

            document.getElementById('overviewRefresh')?.addEventListener('click', () => loadOverview().catch(err => alert(err.message)));
            document.getElementById('overviewDays')?.addEventListener('change', () => loadOverview().catch(err => alert(err.message)));
            document.getElementById('tab-overview')?.addEventListener('shown.bs.tab', () => loadOverview().catch(err => alert(err.message)));

            function initShippingCarePage() {
            /* Report */
            const reportDateInput = document.getElementById('reportDate');
            if (reportDateInput && !reportDateInput.value) {
                reportDateInput.value = todayDateNY();
            }

            let issueContext = null;
            const issueModalEl = document.getElementById('shippingIssueModal');

            function showShippingIssueModal() {
                if (!issueModalEl) return;
                bootstrap.Modal.getOrCreateInstance(issueModalEl).show();
            }

            function hideShippingIssueModal() {
                if (!issueModalEl) return;
                bootstrap.Modal.getInstance(issueModalEl)?.hide();
            }

            function openIssueModal({
                platformId,
                slot,
                date,
                platformName,
                cb,
                prefill,
                revertToggleOnCancel
            }) {
                issueContext = {
                    platformId,
                    slot,
                    date,
                    cb: cb || null,
                    revertToggleOnCancel: !!revertToggleOnCancel
                };
                document.getElementById('shippingIssuePlatformLabel').textContent =
                    platformName + ' · ' + (slots[slot] || slot);
                document.getElementById('issueOrderNumber').value = prefill?.order || '';
                document.getElementById('issueSku').value = prefill?.sku || '';
                document.getElementById('issueReason').value = prefill?.reason || '';
                showShippingIssueModal();
            }

            async function loadReportSlot(slot) {
                const date = reportDateInput.value;
                const data = await fetchJson(routes.reportState + '?report_date=' + encodeURIComponent(date) +
                    '&time_slot=' + encodeURIComponent(slot));
                const tbody = document.querySelector('.report-slot-body[data-slot="' + slot + '"]');
                tbody.innerHTML = '';
                const platforms = data.platforms || [];
                if (!platforms.length) {
                    tbody.innerHTML =
                        '<tr><td colspan="4" class="text-center text-muted py-3">No platforms found. Ensure migration ran and <code>shipping_platforms</code> has rows.</td></tr>';
                    return;
                }
                platforms.forEach(p => {
                    const tr = document.createElement('tr');
                    const issues = p.issues || [];
                    const checked = p.is_cleared ? 'checked' : '';
                    const summary = issuesSummaryHtml(issues, slot);
                    const btnLabel = (issues.length > 0) ? 'Add another issue' : 'Report issue';
                    tr.innerHTML = `
                        <td>${escapeHtml(p.platform_name)}</td>
                        <td>
                            <div class="form-check form-switch">
                                <input class="form-check-input sh-cleared-toggle" type="checkbox" ${checked}
                                    data-platform-id="${p.platform_id}" data-slot="${slot}"
                                    title="Off = not cleared; opens form or use Report issue">
                            </div>
                        </td>
                        <td class="sh-issue-summary">${summary}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary sh-report-issue-btn"
                                data-platform-id="${p.platform_id}" data-slot="${slot}">
                                ${btnLabel}
                            </button>
                        </td>`;
                    tbody.appendChild(tr);
                });

                tbody.querySelectorAll('.sh-cleared-toggle').forEach(cb => {
                    cb.addEventListener('change', () => onClearedToggle(cb));
                });
                tbody.querySelectorAll('.sh-report-issue-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const row = btn.closest('tr');
                        const cb = row.querySelector('.sh-cleared-toggle');
                        const platformName = row.querySelector('td')?.textContent?.trim() || 'Platform';
                        const platformId = parseInt(btn.dataset.platformId, 10);
                        const slotKey = btn.dataset.slot;
                        const dateVal = reportDateInput.value;
                        openIssueModal({
                            platformId,
                            slot: slotKey,
                            date: dateVal,
                            platformName,
                            cb,
                            prefill: { order: '', sku: '', reason: '' },
                            revertToggleOnCancel: false
                        });
                    });
                });
            }

            async function loadAllReportSlots() {
                for (const slot of Object.keys(slots)) {
                    await loadReportSlot(slot);
                }
            }

            async function onClearedToggle(cb) {
                const platformId = parseInt(cb.dataset.platformId, 10);
                const slot = cb.dataset.slot;
                const date = reportDateInput.value;
                const wantCleared = cb.checked;

                if (wantCleared) {
                    try {
                        await fetchJson(routes.reportSave, {
                            method: 'POST',
                            body: JSON.stringify({
                                report_date: date,
                                time_slot: slot,
                                shipping_platform_id: platformId,
                                is_cleared: true,
                                order_number: null,
                                sku: null,
                                reason: null
                            })
                        });
                        await loadReportSlot(slot);
                    } catch (e) {
                        cb.checked = false;
                        alert(e.message);
                    }
                    return;
                }

                const row = cb.closest('tr');
                const platformName = row.querySelector('td')?.textContent?.trim() || 'Platform';
                openIssueModal({
                    platformId,
                    slot,
                    date,
                    platformName,
                    cb,
                    prefill: { order: '', sku: '', reason: '' },
                    revertToggleOnCancel: true
                });
            }

            document.getElementById('issueModalSave')?.addEventListener('click', async () => {
                if (!issueContext) return;
                const order = document.getElementById('issueOrderNumber').value.trim();
                const sku = document.getElementById('issueSku').value.trim();
                const reason = document.getElementById('issueReason').value.trim();
                if (!order || !sku || !reason) {
                    alert('Order number, SKU, and Reason are required.');
                    return;
                }
                const slot = issueContext.slot;
                try {
                    await fetchJson(routes.reportSave, {
                        method: 'POST',
                        body: JSON.stringify({
                            report_date: issueContext.date,
                            time_slot: issueContext.slot,
                            shipping_platform_id: issueContext.platformId,
                            is_cleared: false,
                            order_number: order,
                            sku: sku,
                            reason: reason
                        })
                    });
                    issueContext = null;
                    hideShippingIssueModal();
                    await loadReportSlot(slot);
                } catch (e) {
                    alert(e.message);
                }
            });

            issueModalEl?.addEventListener('hidden.bs.modal', () => {
                if (issueContext && issueContext.revertToggleOnCancel && issueContext.cb) {
                    issueContext.cb.checked = true;
                }
                issueContext = null;
            });

            document.getElementById('reportReload')?.addEventListener('click', () => loadAllReportSlots().catch(e => alert(e.message)));
            document.getElementById('tab-report')?.addEventListener('shown.bs.tab', () => loadAllReportSlots().catch(e => alert(e.message)));

            document.getElementById('pane-report')?.addEventListener('click', async (e) => {
                const del = e.target.closest('.sh-delete-issue');
                if (!del) return;
                e.preventDefault();
                const issueId = del.getAttribute('data-issue-id');
                const slotKey = del.getAttribute('data-slot');
                if (!issueId || !confirm('Remove this issue from the report?')) return;
                try {
                    await fetchJson(routes.issueDestroy + '/' + issueId, { method: 'DELETE' });
                    await loadReportSlot(slotKey);
                } catch (err) {
                    alert(err.message);
                }
            });

            function formatLocalYMD(d) {
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${y}-${m}-${day}`;
            }

            function applyQuickRange(prefix, val) {
                const fromEl = document.getElementById(prefix + 'FilterDateFrom');
                const toEl = document.getElementById(prefix + 'FilterDateTo');
                const quickEl = document.getElementById(prefix + 'QuickRange');
                if (!fromEl || !toEl) return;
                if (val === '' || val == null) {
                    fromEl.value = '';
                    toEl.value = '';
                    if (quickEl) quickEl.value = '';
                    return;
                }
                const t = new Date();
                const todayStr = formatLocalYMD(t);
                if (val === 'today') {
                    fromEl.value = todayStr;
                    toEl.value = todayStr;
                    return;
                }
                const n = parseInt(val, 10);
                if (isNaN(n)) return;
                const start = new Date(t);
                start.setDate(start.getDate() - n);
                fromEl.value = formatLocalYMD(start);
                toEl.value = todayStr;
            }

            let shippingPlatformsLoaded = false;

            async function loadShippingPlatformSelects() {
                if (shippingPlatformsLoaded) return;
                const data = await fetchJson(routes.platforms);
                const list = data.platforms || [];
                const fill = (selId, keepFirstLabel) => {
                    const sel = document.getElementById(selId);
                    if (!sel) return;
                    const keep = sel.value;
                    while (sel.options.length > 1) sel.remove(1);
                    list.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.name;
                        sel.appendChild(opt);
                    });
                    if ([...sel.options].some(o => o.value === keep)) sel.value = keep;
                };
                fill('followupFilterPlatform');
                fill('clearedFilterPlatform');
                const manual = document.getElementById('manualPlatformId');
                if (manual) {
                    const keep = manual.value;
                    manual.querySelectorAll('option:not(:first-child)').forEach(o => o.remove());
                    list.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.name;
                        manual.appendChild(opt);
                    });
                    if ([...manual.options].some(o => o.value === keep)) manual.value = keep;
                }
                shippingPlatformsLoaded = true;
            }

            function followupsQueryString() {
                const p = new URLSearchParams();
                const df = document.getElementById('followupFilterDateFrom')?.value;
                const dt = document.getElementById('followupFilterDateTo')?.value;
                if (df) p.set('date_from', df);
                if (dt) p.set('date_to', dt);
                const pl = document.getElementById('followupFilterPlatform')?.value;
                if (pl) p.set('shipping_platform_id', pl);
                const sl = document.getElementById('followupFilterSlot')?.value;
                if (sl) p.set('time_slot', sl);
                const s = document.getElementById('followupFilterSearch')?.value.trim();
                if (s) p.set('search', s);
                const qs = p.toString();
                return qs ? ('?' + qs) : '';
            }

            function clearedQueryString() {
                const p = new URLSearchParams();
                const df = document.getElementById('clearedFilterDateFrom')?.value;
                const dt = document.getElementById('clearedFilterDateTo')?.value;
                if (df) p.set('date_from', df);
                if (dt) p.set('date_to', dt);
                const pl = document.getElementById('clearedFilterPlatform')?.value;
                if (pl) p.set('shipping_platform_id', pl);
                const sl = document.getElementById('clearedFilterSlot')?.value;
                if (sl) p.set('time_slot', sl);
                const s = document.getElementById('clearedFilterSearch')?.value.trim();
                if (s) p.set('search', s);
                const qs = p.toString();
                return qs ? ('?' + qs) : '';
            }

            /* Follow-ups */
            async function loadFollowups() {
                await loadShippingPlatformSelects();
                const data = await fetchJson(routes.followupsList + followupsQueryString());
                const tbody = document.querySelector('#followupsTable tbody');
                tbody.innerHTML = '';
                (data.followups || []).forEach(f => {
                    const tr = document.createElement('tr');
                    const slot = f.time_slot_label || '—';
                    const dt = f.report_date || '—';
                    tr.innerHTML = `
                        <td>${escapeHtml(f.platform || '—')}</td>
                        <td class="small">${escapeHtml(slot)}<br>${escapeHtml(dt)}</td>
                        <td>${escapeHtml(f.order_number)}</td>
                        <td>${escapeHtml(f.sku)}</td>
                        <td class="small">${escapeHtml(f.reason)}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-success sh-resolve" data-id="${f.id}">Resolved</button>
                        </td>`;
                    tbody.appendChild(tr);
                });
                if (!(data.followups || []).length) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-muted text-center py-3">No open follow-ups</td></tr>';
                }
                tbody.querySelectorAll('.sh-resolve').forEach(btn => {
                    btn.addEventListener('click', () => resolveFollowup(btn.dataset.id));
                });
            }

            async function resolveFollowup(id) {
                if (!confirm('Mark this follow-up as resolved? It will be archived and removed from this list.')) return;
                const url = routes.followupsResolve + '/' + id + '/resolve';
                try {
                    await fetchJson(url, { method: 'POST', body: JSON.stringify({}) });
                    await loadFollowups();
                } catch (e) {
                    alert(e.message);
                }
            }

            document.getElementById('followupQuickRange')?.addEventListener('change', (e) => {
                applyQuickRange('followup', e.target.value);
                loadFollowups().catch(err => alert(err.message));
            });
            document.getElementById('followupFilterApply')?.addEventListener('click', () => loadFollowups().catch(err => alert(err.message)));
            document.getElementById('followupFilterSearch')?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    loadFollowups().catch(err => alert(err.message));
                }
            });
            document.getElementById('followupFilterReset')?.addEventListener('click', () => {
                document.getElementById('followupFilterDateFrom').value = '';
                document.getElementById('followupFilterDateTo').value = '';
                document.getElementById('followupFilterPlatform').value = '';
                document.getElementById('followupFilterSlot').value = '';
                document.getElementById('followupFilterSearch').value = '';
                document.getElementById('followupQuickRange').value = '';
                loadFollowups().catch(err => alert(err.message));
            });

            document.getElementById('tab-followup')?.addEventListener('shown.bs.tab', () => loadFollowups().catch(e => alert(e.message)));

            async function loadCleared() {
                await loadShippingPlatformSelects();
                const data = await fetchJson(routes.clearedList + clearedQueryString());
                const tbody = document.querySelector('#clearedTable tbody');
                tbody.innerHTML = '';
                const items = data.items || [];
                items.forEach(row => {
                    const tr = document.createElement('tr');
                    const slot = row.time_slot_label || '—';
                    const dt = row.report_date || '—';
                    const clearedAt = row.cleared_at ? new Date(row.cleared_at).toLocaleString() : '—';
                    tr.innerHTML = `
                        <td>${escapeHtml(row.platform || '—')}</td>
                        <td class="small">${escapeHtml(slot)}<br>${escapeHtml(dt)}</td>
                        <td>${escapeHtml(row.order_number)}</td>
                        <td>${escapeHtml(row.sku)}</td>
                        <td class="small">${escapeHtml(row.reason)}</td>
                        <td>${escapeHtml(row.cleared_by)}</td>
                        <td class="small text-muted">${escapeHtml(clearedAt)}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-secondary sh-restore-cleared" data-id="${row.id}">Restore</button>
                        </td>`;
                    tbody.appendChild(tr);
                });
                if (!items.length) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-muted text-center py-3">No cleared records yet</td></tr>';
                }
                tbody.querySelectorAll('.sh-restore-cleared').forEach(btn => {
                    btn.addEventListener('click', () => restoreCleared(btn.dataset.id));
                });
            }

            async function restoreCleared(archiveId) {
                if (!confirm('Restore this item? It will return to Follow-up and may reappear on the Report checklist if the slot still exists.')) return;
                try {
                    await fetchJson(routes.clearedRestore + '/' + archiveId + '/restore', { method: 'POST', body: JSON.stringify({}) });
                    await loadCleared();
                } catch (e) {
                    alert(e.message);
                }
            }

            document.getElementById('clearedQuickRange')?.addEventListener('change', (e) => {
                applyQuickRange('cleared', e.target.value);
                loadCleared().catch(err => alert(err.message));
            });
            document.getElementById('clearedFilterApply')?.addEventListener('click', () => loadCleared().catch(err => alert(err.message)));
            document.getElementById('clearedFilterSearch')?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    loadCleared().catch(err => alert(err.message));
                }
            });
            document.getElementById('clearedFilterReset')?.addEventListener('click', () => {
                document.getElementById('clearedFilterDateFrom').value = '';
                document.getElementById('clearedFilterDateTo').value = '';
                document.getElementById('clearedFilterPlatform').value = '';
                document.getElementById('clearedFilterSlot').value = '';
                document.getElementById('clearedFilterSearch').value = '';
                document.getElementById('clearedQuickRange').value = '';
                loadCleared().catch(err => alert(err.message));
            });

            document.getElementById('tab-cleared')?.addEventListener('shown.bs.tab', () => loadCleared().catch(e => alert(e.message)));
            document.getElementById('clearedRefreshBtn')?.addEventListener('click', () => loadCleared().catch(e => alert(e.message)));

            /* Manual follow-up — same channel list as filters */
            async function fillManualPlatforms() {
                shippingPlatformsLoaded = false;
                await loadShippingPlatformSelects();
            }

            document.getElementById('manualFollowupSave')?.addEventListener('click', async () => {
                const pid = document.getElementById('manualPlatformId').value;
                const order = document.getElementById('manualOrderNumber').value.trim();
                const sku = document.getElementById('manualSku').value.trim();
                const reason = document.getElementById('manualReason').value.trim();
                if (!order || !sku) {
                    alert('Order number and SKU are required.');
                    return;
                }
                try {
                    const body = {
                        order_number: order,
                        sku: sku,
                        reason: reason || null
                    };
                    if (pid) body.shipping_platform_id = parseInt(pid, 10);
                    await fetchJson(routes.followupsStore, { method: 'POST', body: JSON.stringify(body) });
                    bootstrap.Modal.getInstance(document.getElementById('shippingManualFollowupModal'))?.hide();
                    document.getElementById('manualOrderNumber').value = '';
                    document.getElementById('manualSku').value = '';
                    document.getElementById('manualReason').value = '';
                    await loadFollowups();
                } catch (e) {
                    alert(e.message);
                }
            });

            document.querySelector('[data-bs-target="#shippingManualFollowupModal"]')?.addEventListener('click', () => {
                fillManualPlatforms().catch(() => {});
            });

            if (document.getElementById('pane-overview')?.classList.contains('active')) {
                loadOverview().catch(() => {});
            }
            } /* end initShippingCarePage */
        })();
    </script>
@endsection
