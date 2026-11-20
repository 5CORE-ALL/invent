@extends('layouts.vertical', ['title' => 'QC / PKG Supplier Wise', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #qcsw-table .tabulator { border: 1px solid #dee2e6; border-radius: 0 0 8px 8px; font-size: 13px; }
        #qcsw-table .tabulator .tabulator-header { background: #dbeafe; }
        #qcsw-table .tabulator .tabulator-row .tabulator-cell { white-space: normal; vertical-align: top; }
        .qcsw-issues { max-height: 260px; overflow-y: auto; }
        .qcsw-issue { padding: 6px 8px; margin-bottom: 6px; border-left: 3px solid #3b82f6; background: #f8fafc; border-radius: 4px; line-height: 1.35; }
        .qcsw-issue:last-child { margin-bottom: 0; }
        .qcsw-date { font-weight: 600; color: #1e3a8a; }
        .qcsw-sku { font-weight: 600; }
        .qcsw-dept { font-size: 11px; padding: 1px 6px; border-radius: 10px; background: #e0e7ff; color: #3730a3; margin-left: 4px; }
        .qcsw-label { color: #64748b; }
        .qcsw-count { font-size: 11px; color: #64748b; margin-top: 4px; }
        .qcsw-active { width: 80px; }
        .qcsw-issue-total { font-size: 1.5em; } /* 2x the default badge size (0.75em) */
        /* dark yellow "IG" (ignore) button; !important so Bootstrap's .btn colours don't win */
        .btn.qcsw-cross { background-color: #c79100 !important; border-color: #c79100 !important; color: #fff !important; font-weight: 700; letter-spacing: .5px; }
        .btn.qcsw-cross:hover, .btn.qcsw-cross:focus { background-color: #a87800 !important; border-color: #a87800 !important; }
        .qcsw-ignored { opacity: .55; border-left-color: #94a3b8; }
        .qcsw-ignored-tag { font-size: 11px; padding: 1px 6px; border-radius: 10px; background: #fee2e2; color: #991b1b; margin-left: 4px; }
        .qcsw-act { border-left-color: #ef4444; background: #fff; text-align: center; padding: 4px; white-space: nowrap; }
        .qcsw-list { scrollbar-width: thin; }
        .qcsw-detail .qcsw-issues { max-height: 75vh; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'QC / PKG Supplier Wise',
        'sub_title' => 'Customer Care',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center gap-1">
                            <label class="small text-muted mb-0" for="qcsw-period">Period</label>
                            <select id="qcsw-period" class="form-select form-select-sm" style="width:auto;" title="Only issues reported in this period">
                                @foreach(['lifetime' => 'Lifetime', '365' => 'L365', '180' => 'L180', '90' => 'L90', '30' => 'L30'] as $pv => $pl)
                                    <option value="{{ $pv }}" {{ ($period ?? 'lifetime') === (string) $pv ? 'selected' : '' }}>{{ $pl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <h4 class="mb-0 me-2">QC / PKG Supplier Wise{{ ($detailSupplier ?? null) ? ' — '.$detailSupplier : '' }}</h4>
                        <span class="badge bg-light text-dark" id="qcsw-count" title="Suppliers">0</span>
                        <span class="badge bg-danger qcsw-issue-total" id="qcsw-issue-total" title="Open issues (ignored issues not counted)">0 issues</span>
                        @php
                            $sumQuery = ['period' => $period ?? 'lifetime'] + (($detailSupplier ?? null) ? ['supplier' => $detailSupplier] : []);
                        @endphp
                            <a href="{{ route('customer.care.qc.issues.summary', ['mode' => 'sku'] + $sumQuery) }}" data-base="{{ route('customer.care.qc.issues.summary', ['mode' => 'sku']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-warning qcsw-sum-link">
                                <i class="bi bi-list-check"></i> Summary by SKU
                            </a>
                            <a href="{{ route('customer.care.qc.issues.summary', ['mode' => 'parent'] + $sumQuery) }}" data-base="{{ route('customer.care.qc.issues.summary', ['mode' => 'parent']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-warning qcsw-sum-link">
                                <i class="bi bi-diagram-3"></i> Summary by Parent
                            </a>
                        @if($detailSupplier ?? null)
                            <button type="button" class="btn btn-sm btn-outline-danger" id="qcsw-complaint-btn" title="Draft a polite email to this supplier asking for resolution">
                                <i class="bi bi-envelope-exclamation"></i> Complaints / Resolution
                            </button>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="qcsw-view-archived" title="View archived issues">
                            <i class="bi bi-eye"></i> Archived
                        </button>
                        <div class="ms-auto" style="min-width:240px;{{ ($detailSupplier ?? null) ? 'display:none;' : '' }}">
                            <input type="search" id="qcsw-search" class="form-control form-control-sm" placeholder="Search supplier, SKU, issue…" autocomplete="off" maxlength="100">
                        </div>
                    </div>
                    <p class="text-muted small mb-2">
                        @if($detailSupplier ?? null)
                            Open QC and Packaging issues for this supplier, with details and per-issue actions.
                        @else
                            Open QC and Packaging issues grouped by the supplier of each SKU. Set <b>Supplier Active</b> to Yes or No per supplier. Use the eye icon to open a supplier's issues in a new tab.
                        @endif
                    </p>
                    <div id="qcsw-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit one issue --}}
    <div class="modal fade" id="qcsw-edit-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit issue <span class="text-muted small" id="qcsw-edit-sub"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="qcsw-edit-id">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Issue?</label>
                            <input type="text" class="form-control form-control-sm" id="qcsw-edit-what" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Root Cause Found <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="qcsw-edit-issue" maxlength="255">
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1">Root Cause Remark</label>
                            <input type="text" class="form-control form-control-sm" id="qcsw-edit-issue-remark" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Action</label>
                            <select class="form-select form-select-sm" id="qcsw-edit-action">
                                <option value="">—</option>
                                @foreach(\App\Http\Controllers\CustomerCare\QcIssuesSupplierWiseController::ACTION_OPTIONS as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Action Remark</label>
                            <input type="text" class="form-control form-control-sm" id="qcsw-edit-action-remark" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Root Cause Fixed</label>
                            <input type="text" class="form-control form-control-sm" id="qcsw-edit-fixed" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Root Cause Fixed Remark</label>
                            <input type="text" class="form-control form-control-sm" id="qcsw-edit-fixed-remark" maxlength="255">
                        </div>
                    </div>
                    <div class="text-danger small mt-2 d-none" id="qcsw-edit-error"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="qcsw-edit-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Archive one issue --}}
    <div class="modal fade" id="qcsw-archive-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Archive issue <span class="text-muted small" id="qcsw-archive-sub"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="qcsw-archive-id">
                    <p class="fw-semibold mb-2">Was corrective action taken and were results obtained?</p>
                    <div class="d-flex gap-3 mb-3">
                        <label class="form-check"><input class="form-check-input" type="radio" name="qcsw-archive-taken" value="Yes"> <span class="form-check-label">Yes</span></label>
                        <label class="form-check"><input class="form-check-input" type="radio" name="qcsw-archive-taken" value="No"> <span class="form-check-label">No</span></label>
                    </div>
                    <div id="qcsw-archive-details-wrap" class="d-none">
                        <label class="form-label small mb-1" id="qcsw-archive-details-label"></label>
                        <textarea class="form-control form-control-sm" id="qcsw-archive-details" rows="3" maxlength="1000"></textarea>
                    </div>
                    <div class="text-danger small mt-2 d-none" id="qcsw-archive-error"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" id="qcsw-archive-confirm">Archive</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Complaints / Resolution email --}}
    <div class="modal fade" id="qcsw-cr-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complaints / Resolution <span class="text-muted small" id="qcsw-cr-sub"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2">From: <span id="qcsw-cr-from" class="fw-semibold">—</span> · the draft is editable; use the pen to rewrite it from a prompt.</div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="qcsw-cr-to">To (supplier email; separate several with commas)</label>
                        <input type="text" class="form-control form-control-sm" id="qcsw-cr-to" autocomplete="off" placeholder="supplier@example.com">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1" for="qcsw-cr-subject">Subject</label>
                        <input type="text" class="form-control form-control-sm" id="qcsw-cr-subject" maxlength="255">
                    </div>
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <label class="form-label small mb-0" for="qcsw-cr-body">Message</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="qcsw-cr-pen" title="Refresh the email as per a prompt"><i class="bi bi-pencil-fill"></i></button>
                        </div>
                        <div class="input-group input-group-sm mb-2 d-none" id="qcsw-cr-prompt-wrap">
                            <input type="text" class="form-control" id="qcsw-cr-prompt" maxlength="1000" placeholder="e.g. make it shorter and firmer, ask for a replacement within 7 days" autocomplete="off">
                            <button type="button" class="btn btn-primary" id="qcsw-cr-refresh"><i class="bi bi-arrow-repeat"></i> Refresh draft</button>
                        </div>
                        <textarea class="form-control form-control-sm" id="qcsw-cr-body" rows="16" style="font-family: inherit;"></textarea>
                    </div>
                    <div class="small mt-2 d-none" id="qcsw-cr-msg"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary btn-sm" id="qcsw-cr-send"><i class="bi bi-send"></i> Send</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Archived issues and resolutions --}}
    <div class="modal fade" id="qcsw-archived-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Archived issues &amp; resolutions <span class="badge bg-light text-dark" id="qcsw-archived-count">0</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div id="qcsw-archived-body" class="p-3 text-muted">Loading…</div>
                </div>
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
            const DETAIL = @json($detailSupplier ?? null); // set when opened from the eye icon (single supplier)
            const URLS = {
                page: @json(route('customer.care.qc.issues.supplier.wise')),
                summarySku: @json(route('customer.care.qc.issues.summary', ['mode' => 'sku'])),
                summaryParent: @json(route('customer.care.qc.issues.summary', ['mode' => 'parent'])),
                data: @json(route('customer.care.qc.issues.supplier.wise.data')),
                active: @json(route('customer.care.qc.issues.supplier.wise.active')),
                archived: @json(route('customer.care.qc.issues.supplier.wise.archived')),
                issueBase: @json(url('/customer-care/qc-issues-supplier-wise/issues')),
                crDraft: @json(route('customer.care.qc.issues.complaint.draft')),
                crRefresh: @json(route('customer.care.qc.issues.complaint.refresh')),
                crSend: @json(route('customer.care.qc.issues.complaint.send')),
            };
            let crSupplier = null;
            let table = null;

            function currentPeriod() { return el('qcsw-period').value || 'lifetime'; }
            function dataUrl() {
                return URLS.data + '?period=' + encodeURIComponent(currentPeriod()) + (DETAIL ? '&supplier=' + encodeURIComponent(DETAIL) : '');
            }

            // Summary page for one supplier, opened in a new tab with the current Period.
            function summaryLink(base, supplier, icon, tip) {
                const url = base + '?supplier=' + encodeURIComponent(supplier) + '&period=' + encodeURIComponent(currentPeriod());
                return '<a href="' + esc(url) + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="' + esc(tip) + '"><i class="bi ' + icon + '"></i></a>';
            }

            async function api(method, url, body) {
                const res = await fetch(url, {
                    method: method,
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
                    body: body ? JSON.stringify(body) : undefined,
                });
                const json = await res.json().catch(function () { return {}; });
                if (!res.ok) {
                    const first = json.errors ? Object.values(json.errors)[0] : null;
                    throw new Error((first && first[0]) || json.message || 'Request failed (HTTP ' + res.status + ')');
                }
                return json;
            }

            function modalFor(id) { return bootstrap.Modal.getOrCreateInstance(document.getElementById(id)); }
            function el(id) { return document.getElementById(id); }

            function findIssue(id) {
                const rows = table.getRows();
                for (let r = 0; r < rows.length; r++) {
                    const issues = rows[r].getData().issues || [];
                    for (let k = 0; k < issues.length; k++) {
                        if (issues[k].id === id) return { row: rows[r], issue: issues[k] };
                    }
                }
                return null;
            }

            // Issues, Details and Action list the same issues one under another; keep each issue the same height in all three.
            function alignRow(rowEl) {
                if (!rowEl) return;
                const lists = Array.prototype.slice.call(rowEl.querySelectorAll('.qcsw-list')).map(function (l) {
                    return Array.prototype.slice.call(l.children);
                });
                if (lists.length < 2) return;
                lists.forEach(function (items) { items.forEach(function (it) { it.style.minHeight = ''; }); });
                const n = Math.min.apply(null, lists.map(function (l) { return l.length; }));
                for (let k = 0; k < n; k++) {
                    const h = Math.max.apply(null, lists.map(function (l) { return l[k].offsetHeight; }));
                    lists.forEach(function (l) { l[k].style.minHeight = h + 'px'; });
                }
            }

            function actionHtml(i) {
                const cross = i.ignored
                    ? '<button type="button" class="btn btn-sm btn-outline-secondary qcsw-ignore" data-id="' + i.id + '" data-ignore="0" title="Ignored - Not supplier Fault (click to count it again)"><i class="bi bi-arrow-counterclockwise"></i></button> '
                    : '<button type="button" class="btn btn-sm qcsw-ignore qcsw-cross" data-id="' + i.id + '" data-ignore="1" title="Ignore - Not supplier Fault">IG</button> ';
                return '<div class="qcsw-issue qcsw-act" data-id="' + i.id + '">' + cross +
                    '<button type="button" class="btn btn-sm btn-outline-primary qcsw-edit" data-id="' + i.id + '" title="Edit this issue"><i class="bi bi-pencil-square"></i></button> ' +
                    '<button type="button" class="btn btn-sm btn-outline-danger qcsw-archive" data-id="' + i.id + '" title="Archive this issue"><i class="bi bi-archive"></i></button>' +
                    '</div>';
            }

            function esc(v) { const el = document.createElement('div'); el.textContent = String(v ?? ''); return el.innerHTML; }

            function issueHeadHtml(i) {
                return '<span class="qcsw-date">' + esc(i.date || '—') + '</span> · <span class="qcsw-sku">' + esc(i.sku) + '</span>';
            }

            function issueHtml(i) {
                const what = [i.what_happened, i.issue].filter(Boolean).join(' — ');
                return '<div class="qcsw-issue' + (i.ignored ? ' qcsw-ignored' : '') + '">' + issueHeadHtml(i) +
                    (i.department ? '<span class="qcsw-dept">' + esc(i.department) + '</span>' : '') +
                    (i.ignored ? '<span class="qcsw-ignored-tag" title="Ignore - Not supplier Fault">Ignored</span>' : '') +
                    '<div>' + (what ? esc(what) : '<span class="text-muted">—</span>') + '</div>' +
                    '</div>';
            }

            function detailsHtml(i) {
                const lines = [];
                if (i.issue_remark) lines.push('<span class="qcsw-label">Remark:</span> ' + esc(i.issue_remark));
                if (i.action) lines.push('<span class="qcsw-label">Action:</span> ' + esc(i.action) + (i.action_remark ? ' (' + esc(i.action_remark) + ')' : ''));
                if (i.root_cause_fixed) lines.push('<span class="qcsw-label">Root cause fixed:</span> ' + esc(i.root_cause_fixed) + (i.root_cause_fixed_remark ? ' (' + esc(i.root_cause_fixed_remark) + ')' : ''));
                return '<div class="qcsw-issue' + (i.ignored ? ' qcsw-ignored' : '') + '">' + issueHeadHtml(i) +
                    '<div>' + (lines.length ? lines.join('<br>') : '<span class="text-muted">—</span>') + '</div>' +
                    '</div>';
            }

            async function saveActive(supplier, value, cell) {
                const prev = value === 'Yes' ? 'No' : 'Yes';
                try {
                    const res = await fetch(URLS.active, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ supplier: supplier, active: value }),
                    });
                    const json = await res.json().catch(function () { return {}; });
                    if (!res.ok || !json.success) throw new Error(json.message || 'Save failed');
                } catch (e) {
                    cell.getRow().update({ supplier_active: prev });
                    alert(e.message || 'Could not save Supplier Active.');
                }
            }

            // Badges: supplier rows and the total number of open issues across them.
            function updateCounts(loaded) {
                // dataLoaded fires before the table holds the rows, so use the data it hands over.
                const rows = Array.isArray(loaded) ? loaded : table.getData();
                const total = rows.reduce(function (n, r) { return n + (r.issue_count || 0); }, 0);
                el('qcsw-count').textContent = rows.length + (rows.length === 1 ? ' supplier' : ' suppliers');
                el('qcsw-issue-total').textContent = total + (total === 1 ? ' issue' : ' issues');
            }

            function issuesChanged(row, issues) {
                row.update({ issues: issues, issue_count: issues.filter(function (x) { return !x.ignored; }).length });
                row.reformat();
                updateCounts();
            }

            async function toggleIgnore(btn) {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                const ignore = btn.getAttribute('data-ignore') === '1';
                const f = findIssue(id);
                if (!f) return;
                btn.disabled = true;
                try {
                    await api('POST', URLS.issueBase + '/' + id + '/ignore', { ignore: ignore });
                    f.issue.ignored = ignore;
                    issuesChanged(f.row, f.row.getData().issues);
                } catch (e) {
                    btn.disabled = false;
                    alert(e.message || 'Could not update this issue.');
                }
            }

            function openEdit(id) {
                const f = findIssue(id);
                if (!f) return;
                const i = f.issue;
                el('qcsw-edit-id').value = id;
                el('qcsw-edit-sub').textContent = '· ' + i.sku + ' · ' + i.date;
                el('qcsw-edit-what').value = i.what_happened || '';
                el('qcsw-edit-issue').value = i.issue || '';
                el('qcsw-edit-issue-remark').value = i.issue_remark || '';
                el('qcsw-edit-action').value = i.action || '';
                el('qcsw-edit-action-remark').value = i.action_remark || '';
                el('qcsw-edit-fixed').value = i.root_cause_fixed || '';
                el('qcsw-edit-fixed-remark').value = i.root_cause_fixed_remark || '';
                el('qcsw-edit-error').classList.add('d-none');
                modalFor('qcsw-edit-modal').show();
            }

            async function saveEdit() {
                const id = parseInt(el('qcsw-edit-id').value, 10);
                const err = el('qcsw-edit-error');
                const body = {
                    what_happened: el('qcsw-edit-what').value.trim(),
                    issue: el('qcsw-edit-issue').value.trim(),
                    issue_remark: el('qcsw-edit-issue-remark').value.trim(),
                    action_1: el('qcsw-edit-action').value,
                    action_1_remark: el('qcsw-edit-action-remark').value.trim(),
                    c_action_1: el('qcsw-edit-fixed').value.trim(),
                    c_action_1_remark: el('qcsw-edit-fixed-remark').value.trim(),
                };
                const btn = el('qcsw-edit-save');
                btn.disabled = true;
                try {
                    await api('PUT', URLS.issueBase + '/' + id, body);
                    const f = findIssue(id);
                    if (f) {
                        Object.assign(f.issue, {
                            what_happened: body.what_happened, issue: body.issue, issue_remark: body.issue_remark,
                            action: body.action_1, action_remark: body.action_1_remark,
                            root_cause_fixed: body.c_action_1, root_cause_fixed_remark: body.c_action_1_remark,
                        });
                        issuesChanged(f.row, f.row.getData().issues);
                    }
                    modalFor('qcsw-edit-modal').hide();
                } catch (e) {
                    err.textContent = e.message;
                    err.classList.remove('d-none');
                } finally {
                    btn.disabled = false;
                }
            }

            function archiveDetailsUi() {
                const checked = document.querySelector('input[name="qcsw-archive-taken"]:checked');
                const wrap = el('qcsw-archive-details-wrap');
                if (!checked) { wrap.classList.add('d-none'); return; }
                wrap.classList.remove('d-none');
                el('qcsw-archive-details-label').textContent = checked.value === 'Yes'
                    ? 'What results were obtained? *'
                    : 'Why are you archiving this issue without corrective action? *';
            }

            function openArchive(id) {
                const f = findIssue(id);
                if (!f) return;
                el('qcsw-archive-id').value = id;
                el('qcsw-archive-sub').textContent = '· ' + f.issue.sku + ' · ' + f.issue.date;
                document.querySelectorAll('input[name="qcsw-archive-taken"]').forEach(function (r) { r.checked = false; });
                el('qcsw-archive-details').value = '';
                el('qcsw-archive-error').classList.add('d-none');
                archiveDetailsUi();
                modalFor('qcsw-archive-modal').show();
            }

            async function confirmArchive() {
                const id = parseInt(el('qcsw-archive-id').value, 10);
                const err = el('qcsw-archive-error');
                const checked = document.querySelector('input[name="qcsw-archive-taken"]:checked');
                const details = el('qcsw-archive-details').value.trim();
                const fail = function (m) { err.textContent = m; err.classList.remove('d-none'); };
                if (!checked) return fail('Select Yes or No.');
                if (!details) return fail(checked.value === 'Yes' ? 'Enter the results obtained.' : 'Enter the reason for archiving this issue.');
                const btn = el('qcsw-archive-confirm');
                btn.disabled = true;
                try {
                    await api('POST', URLS.issueBase + '/' + id + '/archive', { corrective_action_taken: checked.value, details: details });
                    const f = findIssue(id);
                    if (f) {
                        const rest = f.row.getData().issues.filter(function (x) { return x.id !== id; });
                        if (rest.length === 0) {
                            f.row.delete();
                            updateCounts();
                        } else {
                            issuesChanged(f.row, rest);
                        }
                    }
                    modalFor('qcsw-archive-modal').hide();
                } catch (e) {
                    fail(e.message);
                } finally {
                    btn.disabled = false;
                }
            }

            function crMsg(text, ok) {
                const m = el('qcsw-cr-msg');
                m.textContent = text;
                m.className = 'small mt-2 ' + (ok ? 'text-success' : 'text-danger');
            }

            async function openComplaint(supplier) {
                crSupplier = supplier;
                ['qcsw-cr-to', 'qcsw-cr-subject', 'qcsw-cr-body', 'qcsw-cr-prompt'].forEach(function (id) { el(id).value = ''; });
                el('qcsw-cr-sub').textContent = '· ' + supplier;
                el('qcsw-cr-from').textContent = '…';
                el('qcsw-cr-prompt-wrap').classList.add('d-none');
                el('qcsw-cr-msg').className = 'small mt-2 d-none';
                modalFor('qcsw-cr-modal').show();
                try {
                    const j = await api('GET', URLS.crDraft + '?supplier=' + encodeURIComponent(supplier) + '&period=' + encodeURIComponent(currentPeriod()));
                    el('qcsw-cr-to').value = j.to || '';
                    el('qcsw-cr-subject').value = j.subject || '';
                    el('qcsw-cr-body').value = j.body || '';
                    el('qcsw-cr-from').textContent = j.from || '—';
                    if (!j.to) crMsg('No email saved for supplier "' + supplier + '". Type the address in To.', false);
                } catch (e) {
                    crMsg('Could not load the draft: ' + e.message, false);
                }
            }

            async function refreshComplaint() {
                const prompt = el('qcsw-cr-prompt').value.trim();
                if (!prompt) { crMsg('Type what you want changed first.', false); return; }
                const btn = el('qcsw-cr-refresh');
                btn.disabled = true;
                crMsg('Refreshing the draft…', true);
                try {
                    const j = await api('POST', URLS.crRefresh, {
                        supplier: crSupplier, period: currentPeriod(), prompt: prompt,
                        subject: el('qcsw-cr-subject').value, body: el('qcsw-cr-body').value,
                    });
                    el('qcsw-cr-subject').value = j.subject || el('qcsw-cr-subject').value;
                    el('qcsw-cr-body').value = j.body || '';
                    crMsg('Draft refreshed. You can still edit it before sending.', true);
                } catch (e) {
                    crMsg(e.message, false);
                } finally {
                    btn.disabled = false;
                }
            }

            async function sendComplaint() {
                const btn = el('qcsw-cr-send');
                btn.disabled = true;
                crMsg('Sending…', true);
                try {
                    const j = await api('POST', URLS.crSend, {
                        supplier: crSupplier, to: el('qcsw-cr-to').value,
                        subject: el('qcsw-cr-subject').value, body: el('qcsw-cr-body').value,
                    });
                    crMsg(j.message || 'Email sent.', true);
                } catch (e) {
                    crMsg(e.message, false);
                } finally {
                    btn.disabled = false;
                }
            }

            async function openArchived() {
                const body = el('qcsw-archived-body');
                body.className = 'p-3 text-muted';
                body.textContent = 'Loading…';
                modalFor('qcsw-archived-modal').show();
                try {
                    const json = await api('GET', URLS.archived);
                    const rows = json.data || [];
                    el('qcsw-archived-count').textContent = rows.length;
                    if (!rows.length) { body.textContent = 'No archived issues yet.'; return; }
                    body.className = '';
                    body.innerHTML = '<table class="table table-sm table-hover mb-0"><thead class="table-light"><tr>' +
                        '<th>Issue date</th><th>Supplier</th><th>SKU</th><th>Issue</th><th>Corrective action &amp; results?</th><th>Results / reason</th><th>Archived</th></tr></thead><tbody>' +
                        rows.map(function (r) {
                            const what = [r.what_happened, r.issue].filter(Boolean).join(' — ');
                            const taken = r.corrective_action_taken
                                ? '<span class="badge ' + (r.corrective_action_taken === 'Yes' ? 'bg-success' : 'bg-secondary') + '">' + esc(r.corrective_action_taken) + '</span>'
                                : '<span class="text-muted">—</span>';
                            return '<tr><td class="text-nowrap">' + esc(r.date || '—') + '</td><td>' + esc(r.supplier || '—') + '</td><td class="fw-semibold">' + esc(r.sku) + '</td>' +
                                '<td>' + esc(what || '—') + (r.issue_remark ? '<div class="text-muted small">' + esc(r.issue_remark) + '</div>' : '') + '</td>' +
                                '<td class="text-center">' + taken + '</td><td>' + (r.resolution ? esc(r.resolution) : '<span class="text-muted">—</span>') + '</td>' +
                                '<td class="text-nowrap small">' + esc(r.archived_at || '') + '<div class="text-muted">' + esc(r.archived_by || '') + '</div></td></tr>';
                        }).join('') + '</tbody></table>';
                } catch (e) {
                    body.className = 'p-3 text-danger';
                    body.textContent = e.message;
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                el('qcsw-edit-save').addEventListener('click', saveEdit);
                el('qcsw-archive-confirm').addEventListener('click', confirmArchive);
                el('qcsw-view-archived').addEventListener('click', openArchived);
                el('qcsw-cr-pen').addEventListener('click', function () {
                    const w = el('qcsw-cr-prompt-wrap');
                    w.classList.toggle('d-none');
                    if (!w.classList.contains('d-none')) el('qcsw-cr-prompt').focus();
                });
                el('qcsw-cr-refresh').addEventListener('click', refreshComplaint);
                el('qcsw-cr-prompt').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); refreshComplaint(); } });
                el('qcsw-cr-send').addEventListener('click', sendComplaint);
                if (el('qcsw-complaint-btn')) el('qcsw-complaint-btn').addEventListener('click', function () { openComplaint(DETAIL); });
                el('qcsw-period').addEventListener('change', function () {
                    const p = currentPeriod();
                    document.querySelectorAll('.qcsw-sum-link').forEach(function (a) { a.href = a.getAttribute('data-base') + '?period=' + encodeURIComponent(p) + (DETAIL ? '&supplier=' + encodeURIComponent(DETAIL) : ''); });
                    if (DETAIL) history.replaceState(null, '', URLS.page + '?supplier=' + encodeURIComponent(DETAIL) + '&period=' + encodeURIComponent(p));
                    table.setData(dataUrl());
                });
                document.querySelectorAll('input[name="qcsw-archive-taken"]').forEach(function (r) { r.addEventListener('change', archiveDetailsUi); });

                table = new Tabulator('#qcsw-table', {
                    ajaxURL: dataUrl(),
                    ajaxResponse: function (url, params, response) { return (response && response.data) || []; },
                    layout: 'fitColumns',
                    pagination: !DETAIL,
                    paginationSize: 25,
                    paginationSizeSelector: [10, 25, 50, 100],
                    placeholder: DETAIL ? 'No open issues for this supplier.' : 'No QC issues found.',
                    columns: [
                        {
                            title: 'Suppliers', field: 'supplier', width: 240, headerFilter: 'input', visible: !DETAIL,
                            formatter: function (c) {
                                const d = c.getData();
                                return '<div class="fw-semibold">' + esc(d.supplier) + '</div>';
                            },
                        },
                        {
                            title: 'Supplier Active', field: 'supplier_active', width: 150, hozAlign: 'center', visible: !DETAIL,
                            headerFilter: 'list', headerFilterParams: { values: { '': 'All', Yes: 'Yes', No: 'No' }, clearable: true },
                            formatter: function (c) {
                                const d = c.getData();
                                if (d.supplier_key === '(NO SUPPLIER)') return '<span class="text-muted">—</span>';
                                const v = c.getValue() === 'No' ? 'No' : 'Yes';
                                return '<select class="form-select form-select-sm qcsw-active mx-auto ' + (v === 'Yes' ? 'text-success' : 'text-danger') + '">' +
                                    '<option value="Yes"' + (v === 'Yes' ? ' selected' : '') + '>Yes</option>' +
                                    '<option value="No"' + (v === 'No' ? ' selected' : '') + '>No</option></select>';
                            },
                            cellClick: function (e, cell) {
                                if (e.target.closest('select')) e.stopPropagation();
                            },
                        },
                        {
                            title: 'Issue Count', field: 'issue_count', width: 120, hozAlign: 'center', sorter: 'number', visible: !DETAIL,
                            headerFilter: 'number', headerFilterPlaceholder: 'Min', headerFilterFunc: '>=',
                            formatter: function (c) {
                                const ign = (c.getData().issues || []).filter(function (x) { return x.ignored; }).length;
                                return '<span class="fw-semibold">' + esc(c.getValue()) + '</span>' + (ign ? '<div class="qcsw-count" title="Ignore - Not supplier Fault">+' + ign + ' ignored</div>' : '');
                            },
                        },
                        {
                            title: 'View', field: 'view', headerSort: false, width: 80, hozAlign: 'center', visible: !DETAIL,
                            formatter: function (c) {
                                const url = URLS.page + '?supplier=' + encodeURIComponent(c.getData().supplier) + '&period=' + encodeURIComponent(currentPeriod());
                                return '<a href="' + esc(url) + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Open issues, details and actions in a new tab"><i class="bi bi-eye"></i></a>';
                            },
                        },
                        {
                            title: 'View SKU Wise', field: 'view_sku', headerSort: false, width: 110, hozAlign: 'center', visible: !DETAIL,
                            formatter: function (c) { return summaryLink(URLS.summarySku, c.getData().supplier, 'bi-list-check', 'Open the SKU wise summary for this supplier in a new tab'); },
                        },
                        {
                            title: 'View Parent Wise', field: 'view_parent', headerSort: false, width: 120, hozAlign: 'center', visible: !DETAIL,
                            formatter: function (c) { return summaryLink(URLS.summaryParent, c.getData().supplier, 'bi-diagram-3', 'Open the parent wise summary for this supplier in a new tab'); },
                        },
                        {
                            title: 'Complaints / Resolution', field: 'complaint', headerSort: false, width: 170, hozAlign: 'center', visible: !DETAIL,
                            formatter: function (c) {
                                if (c.getData().supplier_key === '(NO SUPPLIER)') return '<span class="text-muted">—</span>';
                                return '<button type="button" class="btn btn-sm btn-outline-danger qcsw-complaint" title="Draft a polite email to this supplier asking for resolution"><i class="bi bi-envelope-exclamation"></i> Draft</button>';
                            },
                            cellClick: function (e, cell) {
                                if (e.target.closest('.qcsw-complaint')) openComplaint(cell.getData().supplier);
                            },
                        },
                        {
                            title: 'Issues', field: 'issues', headerSort: false, minWidth: 260, visible: !!DETAIL,
                            formatter: function (c) {
                                return '<div class="qcsw-issues qcsw-list">' + (c.getValue() || []).map(issueHtml).join('') + '</div>';
                            },
                        },
                        {
                            title: 'Details', field: 'details', headerSort: false, minWidth: 320, visible: !!DETAIL,
                            formatter: function (c) {
                                return '<div class="qcsw-issues qcsw-list">' + (c.getData().issues || []).map(detailsHtml).join('') + '</div>';
                            },
                        },
                        {
                            title: 'Action <i class="bi bi-eye qcsw-eye" title="View archived issues" style="cursor:pointer;"></i>',
                            field: 'actions', headerSort: false, width: 140, hozAlign: 'center', visible: !!DETAIL,
                            formatter: function (c) {
                                return '<div class="qcsw-issues qcsw-list">' + (c.getData().issues || []).map(actionHtml).join('') + '</div>';
                            },
                        },
                    ],
                    rowFormatter: function (row) {
                        requestAnimationFrame(function () { alignRow(row.getElement()); });
                    },
                });

                table.on('renderComplete', function () {
                    table.getRows('active').forEach(function (r) { alignRow(r.getElement()); });
                });
                window.addEventListener('resize', function () {
                    table.getRows('active').forEach(function (r) { alignRow(r.getElement()); });
                });

                table.on('tableBuilt', function () {
                    document.getElementById('qcsw-table').addEventListener('click', function (e) {
                        const ign = e.target.closest('.qcsw-ignore');
                        if (ign && !ign.disabled) { toggleIgnore(ign); return; }
                        const edit = e.target.closest('.qcsw-edit');
                        if (edit) { openEdit(parseInt(edit.getAttribute('data-id'), 10)); return; }
                        const arch = e.target.closest('.qcsw-archive');
                        if (arch) { openArchive(parseInt(arch.getAttribute('data-id'), 10)); return; }
                        if (e.target.closest('.qcsw-eye')) openArchived();
                    });
                    // Keep the Issues / Details / Action lists of one supplier scrolling together.
                    let syncing = false;
                    document.getElementById('qcsw-table').addEventListener('scroll', function (e) {
                        const list = e.target;
                        if (syncing || !list.classList || !list.classList.contains('qcsw-list')) return;
                        const rowEl = list.closest('.tabulator-row');
                        if (!rowEl) return;
                        syncing = true;
                        rowEl.querySelectorAll('.qcsw-list').forEach(function (o) { if (o !== list) o.scrollTop = list.scrollTop; });
                        syncing = false;
                    }, true);
                    document.getElementById('qcsw-table').addEventListener('change', function (e) {
                        const sel = e.target.closest('.qcsw-active');
                        if (!sel) return;
                        const cellEl = sel.closest('.tabulator-cell');
                        const rowEl = sel.closest('.tabulator-row');
                        const row = table.getRows().find(function (r) { return r.getElement() === rowEl; });
                        if (!row) return;
                        const value = sel.value;
                        const data = row.getData();
                        row.update({ supplier_active: value });
                        saveActive(data.supplier, value, row.getCell('supplier_active'));
                    });
                });

                table.on('dataLoaded', function (data) { updateCounts(data); });

                if (DETAIL) document.getElementById('qcsw-table').classList.add('qcsw-detail');
                document.getElementById('qcsw-search').addEventListener('input', function () {
                    const term = this.value.trim().toLowerCase();
                    if (!term) { table.clearFilter(); return; }
                    table.setFilter(function (row) {
                        if (String(row.supplier || '').toLowerCase().indexOf(term) !== -1) return true;
                        return (row.issues || []).some(function (i) {
                            return [i.sku, i.parent, i.what_happened, i.issue, i.issue_remark, i.action, i.action_remark, i.date].join(' ').toLowerCase().indexOf(term) !== -1;
                        });
                    });
                });
            });
        })();
    </script>
@endsection
