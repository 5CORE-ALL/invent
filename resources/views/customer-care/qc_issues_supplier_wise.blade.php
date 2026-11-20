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
        .qcsw-sum-item { padding: 6px 8px; margin-bottom: 6px; border-left: 3px solid #8b5cf6; background: #faf5ff; border-radius: 4px; line-height: 1.35; }
        .qcsw-sum-item:last-child { margin-bottom: 0; }
        .qcsw-sum-head { font-weight: 600; }
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
                        <h4 class="mb-0 me-2">QC / PKG Supplier Wise{{ ($detailSupplier ?? null) ? ' — '.$detailSupplier : '' }}</h4>
                        <span class="badge bg-light text-dark" id="qcsw-count">0</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="qcsw-view-archived" title="View archived issues">
                            <i class="bi bi-eye"></i> Archived
                        </button>
                        <div class="ms-auto" style="min-width:240px;{{ ($detailSupplier ?? null) ? 'display:none;' : '' }}">
                            <input type="search" id="qcsw-search" class="form-control form-control-sm" placeholder="Search supplier, SKU, issue…" autocomplete="off" maxlength="100">
                        </div>
                    </div>
                    <p class="text-muted small mb-2">
                        @if($detailSupplier ?? null)
                            Open QC and Packaging issues for this supplier, with details, AI summary and per-issue actions.
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
                data: @json(route('customer.care.qc.issues.supplier.wise.data')),
                active: @json(route('customer.care.qc.issues.supplier.wise.active')),
                summary: @json(route('customer.care.qc.issues.supplier.wise.summary')),
                archived: @json(route('customer.care.qc.issues.supplier.wise.archived')),
                issueBase: @json(url('/customer-care/qc-issues-supplier-wise/issues')),
            };
            let table = null;

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
                return '<div class="qcsw-issue qcsw-act" data-id="' + i.id + '">' +
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
                return '<div class="qcsw-issue">' + issueHeadHtml(i) +
                    (i.department ? '<span class="qcsw-dept">' + esc(i.department) + '</span>' : '') +
                    '<div>' + (what ? esc(what) : '<span class="text-muted">—</span>') + '</div>' +
                    '</div>';
            }

            function detailsHtml(i) {
                const lines = [];
                if (i.issue_remark) lines.push('<span class="qcsw-label">Remark:</span> ' + esc(i.issue_remark));
                if (i.action) lines.push('<span class="qcsw-label">Action:</span> ' + esc(i.action) + (i.action_remark ? ' (' + esc(i.action_remark) + ')' : ''));
                if (i.root_cause_fixed) lines.push('<span class="qcsw-label">Root cause fixed:</span> ' + esc(i.root_cause_fixed) + (i.root_cause_fixed_remark ? ' (' + esc(i.root_cause_fixed_remark) + ')' : ''));
                return '<div class="qcsw-issue">' + issueHeadHtml(i) +
                    '<div>' + (lines.length ? lines.join('<br>') : '<span class="text-muted">—</span>') + '</div>' +
                    '</div>';
            }

            function summaryHtml(d) {
                const btn = function (label, cls) {
                    return '<button type="button" class="btn btn-sm ' + cls + ' qcsw-sum-btn" data-supplier="' + esc(d.supplier) + '">' + label + '</button>';
                };
                if (!Array.isArray(d.summary) || !d.summary.length) {
                    return btn('<i class="bi bi-stars"></i> Generate summary', 'btn-outline-primary');
                }
                const items = d.summary.map(function (g) {
                    const head = (g.group || (g.skus || []).length)
                        ? '<div class="qcsw-sum-head">' + esc(g.group) + ((g.skus || []).length ? ' <span class="qcsw-label">(' + esc(g.skus.join(', ')) + ')</span>' : '') + '</div>'
                        : '';
                    return '<div class="qcsw-sum-item">' + head + '<div>' + esc(g.summary) + '</div></div>';
                }).join('');
                return '<div class="qcsw-issues">' + (d.summary_stale ? '<div class="text-warning small mb-1">Outdated — issues changed since this was written.</div>' : '') + items + '</div>' +
                    '<div class="mt-1">' + btn('<i class="bi bi-arrow-repeat"></i> Regenerate', 'btn-link p-0') + '</div>';
            }

            async function generateSummary(btn) {
                const supplier = btn.getAttribute('data-supplier');
                const rowEl = btn.closest('.tabulator-row');
                const row = table.getRows().find(function (r) { return r.getElement() === rowEl; });
                if (!row) return;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Summarising…';
                try {
                    const res = await fetch(URLS.summary, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ supplier: supplier, force: true }),
                    });
                    const json = await res.json().catch(function () { return {}; });
                    if (!res.ok || !json.success) throw new Error(json.message || 'Summary failed');
                    row.update({ summary: json.summary, summary_stale: false });
                    row.reformat();
                } catch (e) {
                    btn.disabled = false;
                    btn.innerHTML = 'Retry';
                    alert(e.message || 'Could not generate summary.');
                }
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

            // After an edit/archive the issues change, so any saved AI summary is out of date.
            function issuesChanged(row, issues) {
                const d = row.getData();
                row.update({ issues: issues, issue_count: issues.length, summary_stale: Array.isArray(d.summary) && d.summary.length > 0 });
                row.reformat();
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
                            el('qcsw-count').textContent = table.getDataCount();
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
                document.querySelectorAll('input[name="qcsw-archive-taken"]').forEach(function (r) { r.addEventListener('change', archiveDetailsUi); });

                table = new Tabulator('#qcsw-table', {
                    ajaxURL: URLS.data + (DETAIL ? '?supplier=' + encodeURIComponent(DETAIL) : ''),
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
                            formatter: function (c) { return '<span class="fw-semibold">' + esc(c.getValue()) + '</span>'; },
                        },
                        {
                            title: 'View', field: 'view', headerSort: false, width: 80, hozAlign: 'center', visible: !DETAIL,
                            formatter: function (c) {
                                const url = URLS.page + '?supplier=' + encodeURIComponent(c.getData().supplier);
                                return '<a href="' + esc(url) + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Open issues, details, summary and actions in a new tab"><i class="bi bi-eye"></i></a>';
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
                            title: 'Summary', field: 'summary', headerSort: false, minWidth: 340, visible: !!DETAIL,
                            formatter: function (c) { return summaryHtml(c.getData()); },
                        },
                        {
                            title: 'Action <i class="bi bi-eye qcsw-eye" title="View archived issues" style="cursor:pointer;"></i>',
                            field: 'actions', headerSort: false, width: 110, hozAlign: 'center', visible: !!DETAIL,
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
                        const btn = e.target.closest('.qcsw-sum-btn');
                        if (btn && !btn.disabled) { generateSummary(btn); return; }
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

                table.on('dataLoaded', function (data) {
                    document.getElementById('qcsw-count').textContent = data.length;
                });

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
