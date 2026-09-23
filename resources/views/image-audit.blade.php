@extends('layouts.vertical', ['title' => 'Image Audit', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .image-audit .tabulator .tabulator-header .tabulator-col { font-size: 0.8rem; }
        .image-audit .tabulator-row .tabulator-cell { font-size: 0.82rem; }
        .image-audit .image-audit-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 6px;
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
            font-weight: 600;
            line-height: 1.2;
            white-space: nowrap;
            color: #fff;
            cursor: pointer;
            user-select: none;
            border: 2px solid transparent;
        }
        .image-audit .image-audit-badge:hover { filter: brightness(1.08); }
        .image-audit .image-audit-badge.is-active {
            border-color: #0f172a;
            box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.15);
        }
        .image-audit .image-audit-badge-red { background-color: #dc2626; }
        .image-audit .image-audit-badge-green { background-color: #16a34a; }
        .image-audit .image-audit-badge-amber { background-color: #d97706; }
        .image-audit .audit-dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            cursor: pointer;
            border: 1px solid rgba(0, 0, 0, 0.15);
            vertical-align: middle;
        }
        .image-audit .audit-dot-red { background-color: #dc2626; }
        .image-audit .audit-dot-green { background-color: #16a34a; }
        .image-audit .audit-dot-amber { background-color: #d97706; }
        .image-audit .audit-dot-gray { background-color: #cbd5e1; }
        .image-audit .ia-thumb {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .image-audit .audit-history {
            font-size: 0.72rem;
            line-height: 1.3;
            text-align: left;
            white-space: normal;
        }
        .image-audit .audit-history .hist-row {
            border-bottom: 1px dashed #e9ecef;
            padding: 1px 0;
        }
        .image-audit .audit-history .hist-fixed-yes { color: #16a34a; font-weight: 600; }
        .image-audit .audit-history .hist-fixed-no { color: #dc2626; font-weight: 600; }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Product Masters', 'page_title' => 'Image Audit'])

    <div class="row image-audit">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-2">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <a href="{{ route('image.master') }}" class="btn btn-sm btn-outline-primary">Image Master</a>
                        <div class="image-audit-badge image-audit-badge-amber" id="imageAuditMissingWrap" data-status="missing" role="button" tabindex="0" aria-pressed="false" title="SKUs with no product images. Click to show only these; click again to clear.">
                            <span>No images</span>
                            <span class="tabular-nums" id="imageAuditMissingValue">0</span>
                        </div>
                        <div class="image-audit-badge image-audit-badge-red" id="imageAuditPendingWrap" data-status="pending" role="button" tabindex="0" aria-pressed="false" title="Not audited in the last 30 days. Click to show only these; click again to clear.">
                            <span>Pending</span>
                            <span class="tabular-nums" id="imageAuditPendingValue">0</span>
                        </div>
                        <div class="image-audit-badge image-audit-badge-green" id="imageAuditAuditedWrap" data-status="audited" role="button" tabindex="0" aria-pressed="false" title="Audited in the last 30 days. Click to show only these; click again to clear.">
                            <span>Audited</span>
                            <span class="tabular-nums" id="imageAuditAuditedValue">0</span>
                        </div>
                        <div class="image-audit-badge image-audit-badge-amber" id="imageAuditStaleWrap" data-status="stale" role="button" tabindex="0" aria-pressed="false" title="Last audit is older than 30 days. Click to show only these; click again to clear.">
                            <span>30 days ago</span>
                            <span class="tabular-nums" id="imageAuditStaleValue">0</span>
                        </div>
                    </div>
                    <div id="imageAuditTable"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="imageAuditModal" tabindex="-1" aria-labelledby="imageAuditModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="imageAuditModalLabel">Audit images</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2" id="imageAuditModalSku"></p>
                    <input type="hidden" id="imageAuditSku">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="imageAuditFixed">
                        <label class="form-check-label fw-semibold" for="imageAuditFixed">Fixed?</label>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1" for="imageAuditDetails">Details <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="imageAuditDetails" rows="3" placeholder="What was checked or changed?"></textarea>
                    </div>
                    <p class="small text-danger mb-0 d-none" id="imageAuditError"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn-primary" id="imageAuditSaveBtn">Submit</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            var auditDataUrl = @json(route('image.audit.data'));
            var auditSaveUrl = @json(route('image.audit.save'));
            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

            function esc(str) {
                return String(str == null ? '' : str)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function renderHistory(list) {
                if (!list || !list.length) { return '<span class="text-muted">No history</span>'; }
                return '<div class="audit-history">' + list.map(function (h) {
                    var cls = h.fixed ? 'hist-fixed-yes' : 'hist-fixed-no';
                    var txt = h.fixed ? 'Fixed' : 'Not fixed';
                    return '<div class="hist-row"><span class="text-muted">' + esc(h.created_at || '') + '</span> · '
                        + '<span class="' + cls + '">' + txt + '</span> · ' + esc(h.details || '') + '</div>';
                }).join('') + '</div>';
            }

            var table = new Tabulator('#imageAuditTable', {
                ajaxURL: auditDataUrl,
                ajaxResponse: function (url, params, response) {
                    return (response && Array.isArray(response.data)) ? response.data : [];
                },
                layout: 'fitColumns',
                pagination: true,
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 250, 500],
                paginationCounter: 'rows',
                placeholder: 'No SKUs',
                columns: [
                    {
                        title: 'Image', field: 'thumb', headerSort: false, hozAlign: 'center', width: 70,
                        formatter: function (c) {
                            var url = c.getValue();
                            if (!url) { return '<span class="text-muted">—</span>'; }
                            return '<a href="' + esc(url) + '" target="_blank" rel="noopener"><img class="ia-thumb" src="' + esc(url) + '" alt=""></a>';
                        }
                    },
                    { title: 'SKU', field: 'sku', headerFilter: 'input', widthGrow: 2, formatter: function (c) { return esc(c.getValue()); } },
                    { title: 'Parent', field: 'parent', headerFilter: 'input', widthGrow: 1, formatter: function (c) { return esc(c.getValue()); } },
                    {
                        title: 'Images', field: 'image_count', hozAlign: 'center', headerHozAlign: 'center', width: 90, sorter: 'number',
                        formatter: function (c) {
                            var n = Number(c.getValue() || 0);
                            var cls = n === 0 ? 'text-danger' : 'text-body';
                            return '<span class="fw-semibold ' + cls + '">' + n + '</span>';
                        }
                    },
                    {
                        title: 'Status', field: 'dot', hozAlign: 'center', headerHozAlign: 'center', width: 80,
                        formatter: function (c) {
                            var d = c.getData();
                            var cls = d.dot === 'green' ? 'audit-dot-green' : 'audit-dot-red';
                            var title = d.dot === 'green'
                                ? ('Audited ' + (d.latest_audit_at || '') + ' (within 30 days). Click to add an audit.')
                                : 'Not audited in the last 30 days. Click to add an audit.';
                            return '<span class="audit-dot ' + cls + '" title="' + esc(title) + '"></span>';
                        },
                        cellClick: function (e, cell) { openAuditModal(cell.getData()); }
                    },
                    {
                        title: '30 days ago', field: 'stale', hozAlign: 'center', headerHozAlign: 'center', width: 170,
                        formatter: function (c) {
                            var d = c.getData();
                            if (!d.stale) { return '<span class="text-muted">—</span>'; }
                            var when = d.latest_audit_at || '';
                            return '<span class="audit-dot audit-dot-amber" title="' + esc('Last audit ' + when + ' (older than 30 days).') + '"></span>'
                                + (when ? ' <span class="small">' + esc(when) + '</span>' : '');
                        }
                    },
                    { title: 'History', field: 'history', headerSort: false, widthGrow: 3, formatter: function (c) { return renderHistory(c.getValue()); } }
                ]
            });

            var statusFilter = '';

            function rowMatchesStatus(data) {
                if (!statusFilter) { return true; }
                if (statusFilter === 'missing') { return !!data.missing; }
                if (statusFilter === 'pending') { return data.dot !== 'green'; }
                if (statusFilter === 'audited') { return data.dot === 'green'; }
                if (statusFilter === 'stale') { return !!data.stale; }
                return true;
            }

            function applyFilters() {
                if (!statusFilter) {
                    table.clearFilter();
                } else {
                    table.setFilter(function (data) { return rowMatchesStatus(data); });
                }
            }

            function updateCounts() {
                var rows = table.getData();
                var missing = 0, pending = 0, audited = 0, stale = 0;
                rows.forEach(function (r) {
                    if (!r) { return; }
                    if (r.missing) { missing++; }
                    if (r.dot === 'green') { audited++; } else { pending++; }
                    if (r.stale) { stale++; }
                });
                document.getElementById('imageAuditMissingValue').textContent = Number(missing).toLocaleString('en-US');
                document.getElementById('imageAuditPendingValue').textContent = Number(pending).toLocaleString('en-US');
                document.getElementById('imageAuditAuditedValue').textContent = Number(audited).toLocaleString('en-US');
                document.getElementById('imageAuditStaleValue').textContent = Number(stale).toLocaleString('en-US');
                document.querySelectorAll('.image-audit-badge[data-status]').forEach(function (el) {
                    var on = el.getAttribute('data-status') === statusFilter;
                    el.classList.toggle('is-active', on);
                    el.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
            }
            table.on('dataProcessed', updateCounts);

            document.querySelectorAll('.image-audit-badge[data-status]').forEach(function (el) {
                function setStatus() {
                    var next = el.getAttribute('data-status') || '';
                    statusFilter = (statusFilter === next) ? '' : next;
                    applyFilters();
                    updateCounts();
                }
                el.addEventListener('click', setStatus);
                el.addEventListener('keydown', function (e) {
                    if (e.key !== 'Enter' && e.key !== ' ') { return; }
                    e.preventDefault();
                    setStatus();
                });
            });

            function openAuditModal(row) {
                document.getElementById('imageAuditSku').value = row.sku || '';
                document.getElementById('imageAuditModalSku').textContent = row.sku || '';
                document.getElementById('imageAuditFixed').checked = false;
                document.getElementById('imageAuditDetails').value = '';
                var err = document.getElementById('imageAuditError');
                err.classList.add('d-none');
                err.textContent = '';
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('imageAuditModal')).show();
                }
            }

            document.getElementById('imageAuditSaveBtn').addEventListener('click', function () {
                var btn = this;
                var err = document.getElementById('imageAuditError');
                var sku = document.getElementById('imageAuditSku').value;
                var fixed = document.getElementById('imageAuditFixed').checked;
                var details = (document.getElementById('imageAuditDetails').value || '').trim();

                if (!details) {
                    err.textContent = 'Details is required.';
                    err.classList.remove('d-none');
                    return;
                }
                err.classList.add('d-none');
                btn.disabled = true;

                fetch(auditSaveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ sku: sku, fixed: !!fixed, details: details })
                }).then(function (res) {
                    return res.json().then(function (b) { return { ok: res.ok, body: b }; });
                }).then(function (out) {
                    btn.disabled = false;
                    if (out.ok && out.body && out.body.ok) {
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('imageAuditModal')).hide();
                        var rows = table.getData();
                        var idx = rows.findIndex(function (r) { return r && r.sku === sku; });
                        if (idx >= 0) {
                            rows[idx].dot = out.body.dot;
                            rows[idx].green = out.body.green;
                            rows[idx].stale = out.body.stale;
                            rows[idx].latest_audit_at = out.body.latest_audit_at;
                            rows[idx].history = out.body.history;
                            table.updateData([rows[idx]]);
                        }
                        updateCounts();
                    } else {
                        var msg = 'Failed to save.';
                        if (out.body && out.body.errors) {
                            msg = Object.values(out.body.errors).map(function (a) { return a.join(' '); }).join(' ');
                        } else if (out.body && out.body.message) {
                            msg = out.body.message;
                        }
                        err.textContent = msg;
                        err.classList.remove('d-none');
                    }
                }).catch(function () {
                    btn.disabled = false;
                    err.textContent = 'Failed to save.';
                    err.classList.remove('d-none');
                });
            });
        })();
    </script>
@endsection
