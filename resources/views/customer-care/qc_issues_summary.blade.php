@php
    $isSku = $mode === 'sku';
    $title = $isSku ? 'QC / PKG Summary by SKU' : 'QC / PKG Summary by Parent';
@endphp
@extends('layouts.vertical', ['title' => $title, 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #qcs-table .tabulator { border: 1px solid #dee2e6; border-radius: 0 0 8px 8px; font-size: 13px; }
        #qcs-table .tabulator .tabulator-header { background: #dbeafe; }
        /* every cell: content centred both ways */
        #qcs-table .tabulator .tabulator-row .tabulator-cell {
            white-space: normal; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;
        }
        #qcs-table .tabulator .tabulator-col-title { text-align: center; }
        .qcs-tag { display: inline-block; font-size: 11px; padding: 1px 6px; border-radius: 10px; background: #e0e7ff; color: #3730a3; margin: 0 4px 2px 0; }
        .qcs-issue-total { font-size: 1.5em; } /* 2x the default badge size (0.75em) */
        .qcs-tag-manual { background: #fef3c7; color: #92400e; }
        .qcs-tag-manual .qcs-del { cursor: pointer; margin-left: 4px; font-weight: 700; }
        /* dark yellow "IG" (ignore) button; !important so Bootstrap's .btn colours don't win */
        .btn.qcs-ig { background-color: #c79100 !important; border-color: #c79100 !important; color: #fff !important; font-weight: 700; letter-spacing: .5px; }
        .btn.qcs-ig:hover, .btn.qcs-ig:focus { background-color: #a87800 !important; border-color: #a87800 !important; }
        .qcs-muted { color: #64748b; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $title,
        'sub_title' => 'Customer Care',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center gap-1">
                            <label class="small text-muted mb-0" for="qcs-period">Period</label>
                            <select id="qcs-period" class="form-select form-select-sm" style="width:auto;" title="Only issues reported in this period">
                                @foreach(['lifetime' => 'Lifetime', '365' => 'L365', '180' => 'L180', '90' => 'L90', '30' => 'L30'] as $pv => $pl)
                                    <option value="{{ $pv }}" {{ ($period ?? 'lifetime') === (string) $pv ? 'selected' : '' }}>{{ $pl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <h4 class="mb-0 me-2">{{ $title }}{{ ($supplier ?? null) ? ' — '.$supplier : '' }}</h4>
                        <span class="badge bg-light text-dark" id="qcs-count" title="{{ $isSku ? 'SKUs' : 'Parents' }}">0</span>
                        <span class="badge bg-danger qcs-issue-total" id="qcs-issue-total" title="Open issues (ignored issues not counted)">0 issues</span>
                        <div class="ms-auto" style="min-width:240px;">
                            <input type="search" id="qcs-search" class="form-control form-control-sm" placeholder="Search…" autocomplete="off" maxlength="100">
                        </div>
                    </div>
                    <p class="text-muted small mb-2">
                        @if($isSku)
                            Open QC and Packaging issues rolled up per SKU and supplier.
                        @else
                            Open QC and Packaging issues rolled up per parent. Sibling SKUs are grouped together only when they come from the same supplier.
                        @endif
                    </p>
                    <div id="qcs-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add a common issue to one row --}}
    <div class="modal fade" id="qcs-ci-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add common issue <span class="text-muted small" id="qcs-ci-sub"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="qcs-ci-key">
                    <label class="form-label small mb-1" for="qcs-ci-text">Common issue</label>
                    <input type="text" class="form-control form-control-sm" id="qcs-ci-text" maxlength="255" placeholder="e.g. Scratched finish">
                    <div class="text-danger small mt-2 d-none" id="qcs-ci-error"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="qcs-ci-save">Add</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit one issue --}}
    <div class="modal fade" id="qcs-edit-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit issue <span class="text-muted small" id="qcs-edit-sub"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="qcs-edit-id">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Issue?</label>
                            <input type="text" class="form-control form-control-sm" id="qcs-edit-what" maxlength="100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Root Cause Found <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="qcs-edit-issue" maxlength="255">
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1">Root Cause Remark</label>
                            <input type="text" class="form-control form-control-sm" id="qcs-edit-issue-remark" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Action</label>
                            <select class="form-select form-select-sm" id="qcs-edit-action">
                                <option value="">—</option>
                                @foreach(\App\Http\Controllers\CustomerCare\QcIssuesSupplierWiseController::ACTION_OPTIONS as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Action Remark</label>
                            <input type="text" class="form-control form-control-sm" id="qcs-edit-action-remark" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Root Cause Fixed</label>
                            <input type="text" class="form-control form-control-sm" id="qcs-edit-fixed" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Root Cause Fixed Remark</label>
                            <input type="text" class="form-control form-control-sm" id="qcs-edit-fixed-remark" maxlength="255">
                        </div>
                    </div>
                    <div class="text-danger small mt-2 d-none" id="qcs-edit-error"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="qcs-edit-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Archive one issue --}}
    <div class="modal fade" id="qcs-pick-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit an issue <span class="text-muted small" id="qcs-pick-sub"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" id="qcs-pick-body"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="qcs-archive-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Archive issue <span class="text-muted small" id="qcs-archive-sub"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="qcs-archive-id">
                    <p class="fw-semibold mb-2">Was corrective action taken and were results obtained?</p>
                    <div class="d-flex gap-3 mb-3">
                        <label class="form-check"><input class="form-check-input" type="radio" name="qcs-archive-taken" value="Yes"> <span class="form-check-label">Yes</span></label>
                        <label class="form-check"><input class="form-check-input" type="radio" name="qcs-archive-taken" value="No"> <span class="form-check-label">No</span></label>
                    </div>
                    <div id="qcs-archive-details-wrap" class="d-none">
                        <label class="form-label small mb-1" id="qcs-archive-details-label"></label>
                        <textarea class="form-control form-control-sm" id="qcs-archive-details" rows="3" maxlength="1000"></textarea>
                    </div>
                    <div class="text-danger small mt-2 d-none" id="qcs-archive-error"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" id="qcs-archive-confirm">Archive</button>
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
            const IS_SKU = @json($isSku);
            const SUPPLIER = @json($supplier ?? null); // set when opened from a supplier's detail tab
            const URLS = {
                data: @json(route('customer.care.qc.issues.summary.data', ['mode' => $mode])),
                commonAdd: @json(route('customer.care.qc.issues.summary.common.add', ['mode' => $mode])),
                commonRemoveBase: @json(url('/customer-care/qc-issues-supplier-wise/summary/'.$mode.'/common-issue')),
                issueBase: @json(url('/customer-care/qc-issues-supplier-wise/issues')),
                ignoreBulk: @json(route('customer.care.qc.issues.supplier.wise.issues.ignore.bulk')),
                archiveBulk: @json(route('customer.care.qc.issues.supplier.wise.issues.archive.bulk')),
            };
            let table = null;

            function currentPeriod() { return document.getElementById('qcs-period').value || 'lifetime'; }
            function extraQuery() { return SUPPLIER ? '&supplier=' + encodeURIComponent(SUPPLIER) : ''; }
            function dataUrl() { return URLS.data + '?period=' + encodeURIComponent(currentPeriod()) + extraQuery(); }

            function esc(v) { const el = document.createElement('div'); el.textContent = String(v ?? ''); return el.innerHTML; }
            function el(id) { return document.getElementById(id); }
            function modalFor(id) { return bootstrap.Modal.getOrCreateInstance(el(id)); }
            // Counts, Total Sold and % all depend on the issues, so reload the rows after any change.
            function reload() { return table.replaceData(dataUrl()); }

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

            function findIssue(id) {
                const rows = table.getData();
                for (let r = 0; r < rows.length; r++) {
                    const issues = rows[r].issues || [];
                    for (let k = 0; k < issues.length; k++) if (issues[k].id === id) return issues[k];
                }
                return null;
            }
            function showErr(id, msg) { const e = el(id); e.textContent = msg; e.classList.remove('d-none'); }

            // ---- common issues (+) ----
            function openAddCommon(key, label) {
                el('qcs-ci-key').value = key;
                el('qcs-ci-sub').textContent = '· ' + label;
                el('qcs-ci-text').value = '';
                el('qcs-ci-error').classList.add('d-none');
                modalFor('qcs-ci-modal').show();
                setTimeout(function () { el('qcs-ci-text').focus(); }, 300);
            }
            async function saveCommon() {
                const text = el('qcs-ci-text').value.trim();
                if (!text) return showErr('qcs-ci-error', 'Enter the common issue.');
                const btn = el('qcs-ci-save');
                btn.disabled = true;
                try {
                    await api('POST', URLS.commonAdd, { key: el('qcs-ci-key').value, text: text });
                    modalFor('qcs-ci-modal').hide();
                    await reload();
                } catch (e) { showErr('qcs-ci-error', e.message); } finally { btn.disabled = false; }
            }

            // ---- row actions (IG / edit / archive): one set of three buttons per row ----
            function rowByKey(key) {
                return table.getData().find(function (r) { return r.key === key; }) || null;
            }
            // IG ignores the entire row: every issue listed in it is marked "Not supplier fault".
            async function ignoreRow(btn) {
                const row = rowByKey(btn.getAttribute('data-key'));
                if (!row || !(row.issues || []).length) return;
                const n = row.issues.length;
                if (!window.confirm('Ignore all ' + n + ' issue' + (n === 1 ? '' : 's') + ' of ' + row.label + ' (Not supplier fault)?')) return;
                btn.disabled = true;
                try {
                    await api('POST', URLS.ignoreBulk, { ids: row.issues.map(function (i) { return i.id; }), ignore: true });
                    await reload();
                } catch (e) { btn.disabled = false; alert(e.message || 'Could not update this row.'); }
            }

            // Edit: pick which of the row's issues to edit.
            function openPick(key) {
                const row = rowByKey(key);
                if (!row) return;
                el('qcs-pick-sub').textContent = '· ' + row.label;
                el('qcs-pick-body').innerHTML = '<table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Date</th><th>SKU</th><th>Issue</th><th></th></tr></thead><tbody>' +
                    (row.issues || []).map(function (i) {
                        const what = [i.what_happened, i.issue].filter(Boolean).join(' — ');
                        return '<tr><td class="text-nowrap">' + esc(i.date) + '</td><td class="fw-semibold">' + esc(i.sku) + '</td><td>' + esc(what || '—') + '</td>' +
                            '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary qcs-pick-edit" data-id="' + i.id + '" title="Edit this issue"><i class="bi bi-pencil-square"></i></button></td></tr>';
                    }).join('') + '</tbody></table>';
                modalFor('qcs-pick-modal').show();
            }

            function openEdit(id) {
                const i = findIssue(id);
                if (!i) return;
                el('qcs-edit-id').value = id;
                el('qcs-edit-sub').textContent = '· ' + i.sku + ' · ' + i.date;
                el('qcs-edit-what').value = i.what_happened || '';
                el('qcs-edit-issue').value = i.issue || '';
                el('qcs-edit-issue-remark').value = i.issue_remark || '';
                el('qcs-edit-action').value = i.action || '';
                el('qcs-edit-action-remark').value = i.action_remark || '';
                el('qcs-edit-fixed').value = i.root_cause_fixed || '';
                el('qcs-edit-fixed-remark').value = i.root_cause_fixed_remark || '';
                el('qcs-edit-error').classList.add('d-none');
                modalFor('qcs-edit-modal').show();
            }
            async function saveEdit() {
                const id = parseInt(el('qcs-edit-id').value, 10);
                const btn = el('qcs-edit-save');
                btn.disabled = true;
                try {
                    await api('PUT', URLS.issueBase + '/' + id, {
                        what_happened: el('qcs-edit-what').value.trim(),
                        issue: el('qcs-edit-issue').value.trim(),
                        issue_remark: el('qcs-edit-issue-remark').value.trim(),
                        action_1: el('qcs-edit-action').value,
                        action_1_remark: el('qcs-edit-action-remark').value.trim(),
                        c_action_1: el('qcs-edit-fixed').value.trim(),
                        c_action_1_remark: el('qcs-edit-fixed-remark').value.trim(),
                    });
                    modalFor('qcs-edit-modal').hide();
                    await reload();
                } catch (e) { showErr('qcs-edit-error', e.message); } finally { btn.disabled = false; }
            }

            function archiveDetailsUi() {
                const checked = document.querySelector('input[name="qcs-archive-taken"]:checked');
                const wrap = el('qcs-archive-details-wrap');
                if (!checked) { wrap.classList.add('d-none'); return; }
                wrap.classList.remove('d-none');
                el('qcs-archive-details-label').textContent = checked.value === 'Yes'
                    ? 'What results were obtained? *'
                    : 'Why are you archiving this issue without corrective action? *';
            }
            function openArchive(key) {
                const row = rowByKey(key);
                if (!row || !(row.issues || []).length) return;
                el('qcs-archive-id').value = JSON.stringify(row.issues.map(function (i) { return i.id; }));
                el('qcs-archive-sub').textContent = '· ' + row.label + ' · ' + row.issues.length + ' issue' + (row.issues.length === 1 ? '' : 's');
                document.querySelectorAll('input[name="qcs-archive-taken"]').forEach(function (r) { r.checked = false; });
                el('qcs-archive-details').value = '';
                el('qcs-archive-error').classList.add('d-none');
                archiveDetailsUi();
                modalFor('qcs-archive-modal').show();
            }
            async function confirmArchive() {
                const ids = JSON.parse(el('qcs-archive-id').value || '[]');
                const checked = document.querySelector('input[name="qcs-archive-taken"]:checked');
                const details = el('qcs-archive-details').value.trim();
                if (!checked) return showErr('qcs-archive-error', 'Select Yes or No.');
                if (!details) return showErr('qcs-archive-error', checked.value === 'Yes' ? 'Enter the results obtained.' : 'Enter the reason for archiving this issue.');
                const btn = el('qcs-archive-confirm');
                btn.disabled = true;
                try {
                    await api('POST', URLS.archiveBulk, { ids: ids, corrective_action_taken: checked.value, details: details });
                    modalFor('qcs-archive-modal').hide();
                    await reload();
                } catch (e) { showErr('qcs-archive-error', e.message); } finally { btn.disabled = false; }
            }

            document.addEventListener('DOMContentLoaded', function () {
                const columns = [
                    {
                        title: IS_SKU ? 'SKU' : 'Parent', field: 'label', width: 200, headerFilter: 'input',
                        formatter: function (c) { return '<span class="fw-semibold">' + esc(c.getValue()) + '</span>'; },
                    },
                ];
                if (IS_SKU) {
                    columns.push({ title: 'Parent', field: 'parent', width: 160, headerFilter: 'input', formatter: function (c) { return esc(c.getValue() || '—'); } });
                } else {
                    columns.push({
                        title: 'Sibling SKUs', field: 'sku_count', width: 220, sorter: 'number',
                        formatter: function (c) {
                            const d = c.getData();
                            return '<div class="fw-semibold">' + d.sku_count + ' SKU' + (d.sku_count === 1 ? '' : 's') + '</div><div class="qcs-muted">' + esc(d.skus.join(', ')) + '</div>';
                        },
                    });
                }
                columns.push(
                    { title: 'Supplier', field: 'supplier', width: 200, headerFilter: 'input' },
                    { title: 'Total Sold', field: 'total_sold', width: 110, hozAlign: 'center', sorter: 'number', formatter: function (c) { const v = c.getValue(); return v === null || v === undefined ? '—' : Number(v).toLocaleString(); } },
                    { title: 'Issue Count', field: 'issue_count', width: 110, hozAlign: 'center', sorter: 'number', formatter: function (c) { return '<span class="fw-semibold">' + esc(c.getValue()) + '</span>'; } },
                    {
                        title: '%', field: 'issue_pct', width: 90, hozAlign: 'center', sorter: 'number',
                        headerTooltip: 'Issue count / total sold',
                        formatter: function (c) {
                            const v = c.getValue();
                            if (v === null || v === undefined) return '<span class="text-muted" title="Nothing sold in this period">—</span>';
                            const cls = v < 1 ? 'bg-success' : (v <= 3 ? 'bg-warning text-dark' : 'bg-danger');
                            return '<span class="badge ' + cls + '">' + Number(v).toFixed(2) + '%</span>';
                        },
                    },
                    {
                        title: 'QC / PKG', field: 'qc_count', width: 100, hozAlign: 'center', headerSort: false,
                        formatter: function (c) { const d = c.getData(); return d.qc_count + ' / ' + d.pkg_count; },
                    },
                    {
                        title: 'First', field: 'first_date', width: 110, hozAlign: 'center', headerSort: false,
                    },
                    { title: 'Last', field: 'last_date', width: 110, hozAlign: 'center', sorter: function (a, b, aRow, bRow) { return String(aRow.getData().last_iso).localeCompare(String(bRow.getData().last_iso)); } },
                    {
                        title: 'Common issues', field: 'top_issues', minWidth: 200, headerSort: false,
                        formatter: function (c) {
                            const d = c.getData();
                            const auto = (c.getValue() || []).map(function (t) { return '<span class="qcs-tag">' + esc(t) + '</span>'; }).join('');
                            const manual = (d.manual_issues || []).map(function (m) {
                                return '<span class="qcs-tag qcs-tag-manual">' + esc(m.text) + '<span class="qcs-del" data-id="' + m.id + '" title="Remove">×</span></span>';
                            }).join('');
                            return '<div>' + (auto + manual || '—') + ' <button type="button" class="btn btn-sm btn-outline-primary py-0 px-1 qcs-add-ci" data-key="' + esc(d.key) + '" data-label="' + esc(d.label) + '" title="Add a common issue"><i class="bi bi-plus-lg"></i></button></div>';
                        },
                    },
                    {
                        title: 'Action', field: 'key', width: 150, headerSort: false,
                        formatter: function (c) {
                            const k = esc(c.getValue());
                            return '<div class="d-inline-flex gap-1">' +
                                '<button type="button" class="btn btn-sm qcs-ig" data-key="' + k + '" title="Ignore - Not supplier Fault (whole row)">IG</button>' +
                                '<button type="button" class="btn btn-sm btn-outline-primary qcs-edit" data-key="' + k + '" title="Edit an issue of this row"><i class="bi bi-pencil-square"></i></button>' +
                                '<button type="button" class="btn btn-sm btn-outline-danger qcs-archive" data-key="' + k + '" title="Archive this row\'s issues"><i class="bi bi-archive"></i></button>' +
                                '</div>';
                        },
                    }
                );

                table = new Tabulator('#qcs-table', {
                    ajaxURL: dataUrl(),
                    ajaxResponse: function (url, params, response) { return (response && response.data) || []; },
                    layout: 'fitColumns',
                    pagination: true,
                    paginationSize: 25,
                    paginationSizeSelector: [10, 25, 50, 100],
                    placeholder: 'No open QC / packaging issues.',
                    columns: columns,
                    columnDefaults: { hozAlign: 'center', headerHozAlign: 'center' },
                });

                table.on('tableBuilt', function () {
                    el('qcs-table').addEventListener('click', function (e) {
                        const add = e.target.closest('.qcs-add-ci');
                        if (add) { openAddCommon(add.getAttribute('data-key'), add.getAttribute('data-label')); return; }
                        const del = e.target.closest('.qcs-del');
                        if (del) {
                            api('DELETE', URLS.commonRemoveBase + '/' + del.getAttribute('data-id')).then(reload).catch(function (err) { alert(err.message); });
                            return;
                        }
                        const ig = e.target.closest('.qcs-ig');
                        if (ig && !ig.disabled) { ignoreRow(ig); return; }
                        const ed = e.target.closest('.qcs-edit');
                        if (ed) { openPick(ed.getAttribute('data-key')); return; }
                        const ar = e.target.closest('.qcs-archive');
                        if (ar) openArchive(ar.getAttribute('data-key'));
                    });
                    el('qcs-pick-body').addEventListener('click', function (e) {
                        const b = e.target.closest('.qcs-pick-edit');
                        if (!b) return;
                        modalFor('qcs-pick-modal').hide();
                        openEdit(parseInt(b.getAttribute('data-id'), 10));
                    });
                });
                el('qcs-ci-save').addEventListener('click', saveCommon);
                el('qcs-ci-text').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); saveCommon(); } });
                el('qcs-edit-save').addEventListener('click', saveEdit);
                el('qcs-archive-confirm').addEventListener('click', confirmArchive);
                document.querySelectorAll('input[name="qcs-archive-taken"]').forEach(function (r) { r.addEventListener('change', archiveDetailsUi); });

                document.getElementById('qcs-period').addEventListener('change', function () {
                    history.replaceState(null, '', location.pathname + '?period=' + encodeURIComponent(currentPeriod()) + extraQuery());
                    table.setData(dataUrl());
                });

                table.on('dataLoaded', function (data) {
                    const issues = data.reduce(function (n, r) { return n + (r.issue_count || 0); }, 0);
                    const noun = IS_SKU ? ['SKU', 'SKUs'] : ['parent', 'parents'];
                    document.getElementById('qcs-count').textContent = data.length + ' ' + (data.length === 1 ? noun[0] : noun[1]);
                    document.getElementById('qcs-issue-total').textContent = issues + (issues === 1 ? ' issue' : ' issues');
                });

                document.getElementById('qcs-search').addEventListener('input', function () {
                    const term = this.value.trim().toLowerCase();
                    if (!term) { table.clearFilter(); return; }
                    table.setFilter(function (row) {
                        return [row.label, row.parent, row.supplier, (row.skus || []).join(' '), (row.top_issues || []).join(' ')].join(' ').toLowerCase().indexOf(term) !== -1;
                    });
                });
            });
        })();
    </script>
@endsection
