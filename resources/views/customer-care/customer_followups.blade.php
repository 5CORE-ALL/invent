@extends('layouts.vertical', ['title' => 'Follow Up CC', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .followup-table-outer {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .followup-table-shell {
            min-width: 980px;
        }

        /*
         * Single table inside a scroll box: table.table thead th { position: sticky; top: 0 } is relative to
         * this scrollport, so rows never paint over/under a separate “floating” header (split-table bug).
         */
        .followup-table-scroll {
            max-height: min(70vh, calc(100vh - 13.5rem));
            overflow: auto;
            scrollbar-gutter: stable;
            border: 1px solid #dee2e6;
        }

        .followup-table-scroll table {
            table-layout: fixed;
            width: 100%;
            min-width: 980px;
            margin-bottom: 0;
            --followup-table-fs: calc(1rem - 1pt);
            font-size: var(--followup-table-fs);
            line-height: 1.5;
        }

        .followup-table-scroll table.table thead tr {
            background: #2c6ed5;
        }

        .followup-table-scroll table.table thead th.followup-sku-col,
        .followup-table-scroll table.table tbody td.followup-sku-col {
            font-size: calc(var(--followup-table-fs) - 1pt);
        }

        .followup-table-scroll table.table thead th {
            position: sticky;
            top: 0;
            z-index: 6;
            background: #2c6ed5 !important;
            color: #fff !important;
            font-weight: 600;
            font-size: var(--followup-table-fs);
            line-height: 1.45;
            padding: 12px 10px;
            border: 1px solid #1a56b7;
            vertical-align: middle;
            text-align: center !important;
            white-space: nowrap;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .followup-notes-cell {
            white-space: pre-wrap;
            word-break: break-word;
            vertical-align: top;
            text-align: center;
            font-size: var(--followup-table-fs);
            line-height: 1.5;
        }

        .followup-table-scroll table.table tbody td {
            padding: 10px;
            vertical-align: top;
            word-break: break-word;
            text-align: center !important;
            font-size: var(--followup-table-fs);
            line-height: 1.5;
        }

        .followup-status-dot {
            display: inline-block;
            width: 0.65rem;
            height: 0.65rem;
            border-radius: 50%;
            vertical-align: middle;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.06);
        }

        .followup-inline-status-trigger {
            cursor: pointer;
            line-height: 1;
        }

        .followup-inline-status-trigger:focus {
            outline: 2px solid rgba(44, 110, 213, 0.45);
            outline-offset: 2px;
        }

        .followup-table-scroll table.table tbody td.followup-status-cell {
            padding: 8px 4px;
            vertical-align: middle;
        }

        .followup-inline-status-select {
            min-width: 6.5rem;
            max-width: 100%;
        }

        .followup-table-scroll table.table tbody td.followup-dt-cell,
        .followup-table-scroll table.table tbody td.followup-readonly-date {
            padding: 6px 8px;
            vertical-align: middle;
        }

        .followup-table-scroll table.table .followup-inline-dt {
            font-size: var(--followup-table-fs);
            line-height: 1.4;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        .followup-table-scroll table.table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        tr.followup-row-overdue { background-color: rgba(220, 53, 69, 0.12) !important; }
        tr.followup-row-overdue:hover { background-color: rgba(220, 53, 69, 0.2) !important; }
        .stat-card { border-radius: 0.5rem; border: 1px solid #e9ecef; }

        .followup-table-scroll table.table thead th.followup-link-col,
        .followup-table-scroll table.table tbody td.followup-ref-link-cell {
            width: 3.25rem;
            min-width: 3.25rem;
            max-width: 4rem;
            padding-left: 6px;
            padding-right: 6px;
            vertical-align: middle;
        }

        .followup-ref-link-cell a {
            font-size: 1.15rem;
            color: #2c6ed5;
        }

        .followup-table-scroll table.table thead th.followup-actions-col,
        .followup-table-scroll table.table tbody td.followup-actions-col {
            width: 11rem;
            min-width: 11rem;
            max-width: 11rem;
            box-sizing: border-box;
            vertical-align: middle;
        }

        .followup-actions-col .followup-actions-btns {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            flex-wrap: nowrap;
        }

        .followup-table-scroll table.table tbody td.order-num-cell {
            word-break: normal;
            overflow: visible;
            text-align: center !important;
        }

        .followup-table-scroll table.table tbody td.followup-customer-cell {
            white-space: normal;
            line-height: 1.35;
        }

        .order-num-wrap {
            position: relative;
            display: inline-block;
            vertical-align: middle;
            max-width: 100%;
        }

        .order-num-cell {
            white-space: nowrap;
        }

        .order-num-flyout {
            position: absolute;
            left: 50%;
            top: calc(100% + 6px);
            transform: translateX(-50%);
            z-index: 20;
            min-width: max(100%, 10rem);
            max-width: min(42ch, 90vw);
            padding: 6px 10px;
            font-size: 0.8125rem;
            line-height: 1.35;
            text-align: left;
            color: #212529;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.35rem;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
            white-space: normal;
            word-break: break-all;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.15s ease, visibility 0.15s ease;
        }

        .order-num-wrap:hover .order-num-flyout {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .copy-order-btn {
            color: #0d6efd;
            font-size: 0.8rem;
            line-height: 1;
            padding: 0 2px;
            border: none;
            background: none;
            cursor: pointer;
            vertical-align: middle;
            transition: color 0.15s;
        }

        .copy-order-btn:hover {
            color: #0a58ca;
        }

        .copy-order-btn.copied {
            color: #198754;
        }

        /*
         * Toolbar: one typographic scale, light surfaces + dark text, label row then control row
         * so every block lines up on the same baseline (do not reintroduce colored badges/buttons here).
         */
        .followup-toolbar.card {
            background: #fff;
            border-color: #dee2e6 !important;
        }

        .followup-toolbar-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: flex-end;
            gap: 0.5rem;
        }

        @media (min-width: 768px) {
            .followup-toolbar-row {
                gap: 0.75rem;
            }
        }

        .followup-toolbar-item {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .followup-toolbar-label {
            font-size: 0.6875rem;
            line-height: 1.15;
            min-height: 1.05rem;
            margin-bottom: 0.2rem;
            color: #5c636a;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }

        .followup-toolbar-value,
        .followup-toolbar .form-control,
        .followup-toolbar .form-select {
            font-size: 0.875rem;
            line-height: 1.25;
            color: #212529;
        }

        .followup-toolbar .stat-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.75rem;
            min-height: 2.125rem;
            padding: 0.2rem 0.55rem;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            font-weight: 600;
        }

        .followup-toolbar .form-control,
        .followup-toolbar .form-select {
            min-height: 2.125rem;
            background: #f8f9fa;
            border-color: #ced4da;
        }

        .followup-toolbar .btn-toolbar-add {
            min-height: 2.125rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #212529;
            background: #f8f9fa;
            border: 1px solid #ced4da;
        }

        .followup-toolbar .btn-toolbar-add:hover {
            color: #212529;
            background: #e9ecef;
            border-color: #adb5bd;
        }
    </style>
@endsection

@section('content')
    {{--
        Channel integration: channel_master (active), same as /all-marketplace-master.
        // TODO: Fetch channels via API endpoint
        Dummy fallback when empty: Amazon, Flipkart, Shopify, Website, WhatsApp (id 0 — stored as null in DB).

        // TODO: Replace static data with API integration (table loaded via AJAX from followups/data).
    --}}

    <div class="container-fluid">
        {{-- Title, stats, TAT, search, status, Add — one horizontal strip (scrolls on small screens) --}}
        <div class="card mb-3 shadow-sm followup-toolbar border">
            <div class="card-body py-2 px-2 px-md-3">
                <div class="followup-toolbar-row overflow-x-auto pb-1">
                    <div class="followup-toolbar-item flex-shrink-0">
                        <div class="followup-toolbar-label" aria-hidden="true">&nbsp;</div>
                        <div class="followup-toolbar-value fw-semibold text-nowrap">Follow Up CC</div>
                    </div>
                    <div class="followup-toolbar-item flex-shrink-0">
                        <div class="followup-toolbar-label">Pending</div>
                        <div class="followup-toolbar-value stat-chip text-nowrap" id="statPending">—</div>
                    </div>
                    <div class="followup-toolbar-item flex-shrink-0">
                        <div class="followup-toolbar-label">Escalations</div>
                        <div class="followup-toolbar-value stat-chip text-nowrap" id="statEscalations">—</div>
                    </div>
                    <div class="followup-toolbar-item flex-shrink-0"
                        title="Average time from ticket created to Resolved. New resolves use exact time; older Resolved rows may use backfilled times."
                        id="tatBadge">
                        <div class="followup-toolbar-label">TAT</div>
                        <div class="followup-toolbar-value stat-chip text-nowrap"><span id="tatValue">—</span></div>
                    </div>
                    <div class="followup-toolbar-item flex-grow-1 flex-shrink-1" style="min-width: 11rem;">
                        <label class="followup-toolbar-label mb-0 d-block" for="filterSearch">Search</label>
                        <input type="text" class="form-control" id="filterSearch"
                            placeholder="Order ID, SKU, channel, customer, issue…"
                            autocomplete="off">
                    </div>
                    <div class="d-none" aria-hidden="true">
                        <select class="form-select" id="filterChannel" tabindex="-1">
                            <option value="">All</option>
                            @foreach ($channels as $ch)
                                @if ($ch->id)
                                    <option value="{{ $ch->id }}">{{ $ch->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="followup-toolbar-item flex-shrink-0" style="min-width: 9rem;">
                        <label class="followup-toolbar-label mb-0 d-block" for="filterStatus">Status</label>
                        <select class="form-select" id="filterStatus">
                            <option value="all" selected>All statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Escalated">Escalated</option>
                        </select>
                    </div>
                    <div class="followup-toolbar-item flex-shrink-0 ms-md-auto">
                        <div class="followup-toolbar-label" aria-hidden="true">&nbsp;</div>
                        <button type="button" class="btn btn-toolbar-add text-nowrap px-3" id="btnAddFollowup"
                            data-bs-toggle="modal" data-bs-target="#followupModal">
                            <i class="mdi mdi-plus me-1"></i> Add Followup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="followup-table-outer">
                <div class="followup-table-shell">
                    <div class="followup-table-scroll">
                        <table class="table table-hover mb-0 align-middle">
                            <colgroup>
                                <col style="width:5%"><col style="width:7.2%"><col
                                    style="width:33.6%"><col style="width:7.7%"><col style="width:9.2%"><col
                                    style="width:4.5%"><col
                                    style="width:7.5%"><col style="width:7.5%"><col style="width:7%"><col
                                    style="width:3.5%"><col
                                    style="width:11rem">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col">Ord</th>
                                    <th scope="col" class="followup-sku-col">SKU</th>
                                    <th scope="col">Follow up issue</th>
                                    <th scope="col">Channel</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Next</th>
                                    <th scope="col">Executive</th>
                                    <th scope="col" class="followup-link-col">Link</th>
                                    <th scope="col" class="followup-actions-col">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="followupTableBody">
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">Loading…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add / Edit modal --}}
    <div class="modal fade" id="followupModal" tabindex="-1" aria-labelledby="followupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="followupModalLabel">Add Followup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="formAlert" class="alert alert-danger d-none" role="alert"></div>
                    @include('customer-care.partials.followup-modal-form')
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnSaveFollowup">Save</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script>
        (function() {
            const dataUrl = @json(route('customer.care.followups.data'));
            const storeUrl = @json(route('customer.care.followups.store'));
            const followupBase = @json(url('/customer-care/followups'));
            const skuSearchUrl = @json(route('customer.care.followups.skus'));
            const canDeleteFollowups = @json($canDeleteFollowups ?? false);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            function escapeHtml(s) {
                if (s == null) return '';
                const d = document.createElement('div');
                d.textContent = s;
                return d.innerHTML;
            }

            function escapeAttr(s) {
                return String(s ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;');
            }

            /** Customer column: max 15 characters per line; longer text wraps with &lt;br&gt;. */
            function customerNameCellHtml(raw) {
                const trimmed = raw == null ? '' : String(raw).trim();
                if (trimmed === '') {
                    return '<td class="followup-customer-cell text-muted">—</td>';
                }
                const lines = [];
                for (let i = 0; i < trimmed.length; i += 15) {
                    lines.push(escapeHtml(trimmed.slice(i, i + 15)));
                }
                return '<td class="followup-customer-cell">' + lines.join('<br>') + '</td>';
            }

            function notesCellHtml(raw) {
                if (raw == null || String(raw).trim() === '') {
                    return '<td class="followup-notes-cell text-muted">—</td>';
                }
                const t = String(raw);
                return '<td class="followup-notes-cell">' + escapeHtml(t) + '</td>';
            }

            /** Ord: clipboard (like all-issues) + hover flyout so the column width stays fixed. */
            function orderIdCellHtml(row) {
                const raw = row.order_id;
                const trimmed = raw == null ? '' : String(raw).trim();
                const missing = trimmed === '' || trimmed === '—';
                if (missing) {
                    return '<td class="order-num-cell text-muted">—</td>';
                }
                return '<td class="order-num-cell">' +
                    '<div class="order-num-wrap">' +
                    '<button type="button" class="copy-order-btn" data-copy="' + escapeAttr(trimmed) +
                    '" title="' + escapeAttr(trimmed) + '"><i class="bi bi-clipboard"></i></button>' +
                    '<span class="order-num-flyout">' + escapeHtml(trimmed) + '</span></div></td>';
            }

            /** Scheduled date/time text only (non-editable in grid; edit via modal). */
            function followupDateDisplayCellHtml(row) {
                const raw = row.followup_display;
                const t = raw != null && String(raw).trim() !== '' ? String(raw) : '—';
                return '<td class="followup-readonly-date">' + escapeHtml(t) + '</td>';
            }

            function nextFollowupAtCellHtml(row) {
                const v = escapeAttr(row.next_followup_at || '');
                return '<td class="followup-dt-cell">' +
                    '<input type="datetime-local" class="form-control form-control-sm followup-inline-dt" ' +
                    'step="60" autocomplete="off" data-id="' + row.id +
                    '" data-field="next_followup_at" value="' + v + '" title="Next follow-up date & time">' +
                    '</td>';
            }

            /** Reference URL: icon opens in new tab; `--` when empty. */
            function referenceLinkCellHtml(row) {
                const raw = row.reference_link;
                const trimmed = raw != null ? String(raw).trim() : '';
                if (!trimmed) {
                    return '<td class="followup-ref-link-cell text-muted">--</td>';
                }
                const safe = escapeAttr(trimmed);
                return '<td class="followup-ref-link-cell">' +
                    '<a href="' + safe + '" target="_blank" rel="noopener noreferrer" title="Open reference link">' +
                    '<i class="bi bi-link-45deg" aria-hidden="true"></i><span class="visually-hidden">Open link</span>' +
                    '</a></td>';
            }

            let skuSearchTimer = null;

            async function refreshSkuDatalist(query) {
                const dl = document.getElementById('followup_sku_datalist');
                if (!dl) return;
                const q = (query || '').trim();
                if (q.length < 1) {
                    dl.innerHTML = '';
                    return;
                }
                try {
                    const res = await fetch(skuSearchUrl + '?q=' + encodeURIComponent(q), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const j = await res.json();
                    const list = j.skus || [];
                    dl.innerHTML = list.map(s => {
                        const sku = s.sku != null ? String(s.sku) : '';
                        const parent = s.parent != null ? String(s.parent) : '';
                        const label = parent ? (parent + ' · ' + sku) : sku;
                        return '<option value="' + escapeAttr(sku) + '" label="' + escapeAttr(label) +
                            '"></option>';
                    }).join('');
                } catch (err) {
                    dl.innerHTML = '';
                }
            }

            function bindSkuProductMasterAutocomplete() {
                const inp = document.getElementById('sku');
                if (!inp || inp.dataset.pmSkuBound === '1') return;
                inp.dataset.pmSkuBound = '1';
                inp.addEventListener('input', () => {
                    clearTimeout(skuSearchTimer);
                    skuSearchTimer = setTimeout(() => refreshSkuDatalist(inp.value), 220);
                });
                inp.addEventListener('focus', () => {
                    clearTimeout(skuSearchTimer);
                    refreshSkuDatalist(inp.value);
                });
            }

            bindSkuProductMasterAutocomplete();

            function statusDotHtml(status) {
                const s = status == null ? '' : String(status);
                let color = '#6c757d';
                let label = s || '—';
                if (s === 'Pending') color = '#dc3545';
                else if (s === 'Escalated') color = '#6f42c1';
                else if (s === 'Resolved') color = '#198754';
                return '<span class="followup-status-dot" style="background-color:' + color +
                    '" title="' + escapeAttr(label) + '" role="img" aria-label="' + escapeAttr('Status: ' + label) +
                    '"></span>';
            }

            function statusCellHtml(row) {
                const id = row.id;
                const st = row.status == null ? '' : String(row.status);
                const dot = statusDotHtml(st);
                return '<td class="followup-status-cell text-center">' +
                    '<button type="button" class="btn btn-link p-0 align-middle text-decoration-none followup-inline-status-trigger" ' +
                    'data-id="' + escapeAttr(String(id)) + '" data-status="' + escapeAttr(st) +
                    '" title="Change status" aria-label="Change status">' + dot + '</button></td>';
            }

            function buildQuery() {
                const p = new URLSearchParams();
                const s = document.getElementById('filterSearch').value.trim();
                if (s) p.set('search', s);
                const ch = document.getElementById('filterChannel').value;
                if (ch) p.set('channel_id', ch);
                p.set('status', document.getElementById('filterStatus').value);
                return p.toString();
            }

            async function loadTable() {
                const tbody = document.getElementById('followupTableBody');
                try {
                    const res = await fetch(dataUrl + '?' + buildQuery(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const json = await res.json();
                    if (!json.data) throw new Error('Invalid response');

                    document.getElementById('statPending').textContent = json.stats?.pending ?? '—';
                    document.getElementById('statEscalations').textContent = json.stats?.escalations ?? '—';
                    const tatEl = document.getElementById('tatValue');
                    if (tatEl) tatEl.textContent = json.stats?.tat_avg_label ?? '—';

                    if (!json.data.length) {
                        tbody.innerHTML =
                            '<tr><td colspan="11" class="text-center py-4 text-muted">No records match filters.</td></tr>';
                        return;
                    }

                    tbody.innerHTML = json.data.map(row => {
                        const overdue = row.overdue ? ' followup-row-overdue' : '';
                        return '<tr class="' + overdue.trim() + '" data-id="' + row.id + '">' +
                            orderIdCellHtml(row) +
                            '<td class="followup-sku-col">' + escapeHtml(row.sku) + '</td>' +
                            notesCellHtml(row.notes) +
                            '<td>' + escapeHtml(row.channel_name) + '</td>' +
                            customerNameCellHtml(row.customer_name) +
                            statusCellHtml(row) +
                            followupDateDisplayCellHtml(row) +
                            nextFollowupAtCellHtml(row) +
                            '<td>' + escapeHtml(row.executive) + '</td>' +
                            referenceLinkCellHtml(row) +
                            '<td class="text-nowrap text-center followup-actions-col">' +
                            '<div class="followup-actions-btns">' +
                            '<button type="button" class="btn btn-sm btn-outline-primary btn-edit" data-id="' +
                            row.id +
                            '" data-bs-toggle="tooltip" title="Edit"><i class="mdi mdi-pencil"></i></button>' +
                            (canDeleteFollowups ?
                                '<button type="button" class="btn btn-sm btn-outline-danger btn-del" data-id="' +
                                row.id +
                                '" data-bs-toggle="tooltip" title="Delete"><i class="mdi mdi-delete"></i></button>' :
                                '') +
                            '</div></td></tr>';
                    }).join('');

                    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
                } catch (e) {
                    tbody.innerHTML =
                        '<tr><td colspan="11" class="text-center text-danger py-4">Failed to load data.</td></tr>';
                }
            }

            function clearFormErrors() {
                document.querySelectorAll('[data-error-for]').forEach(el => {
                    el.textContent = '';
                    el.classList.remove('d-block');
                });
                document.querySelectorAll('#followupForm .is-invalid').forEach(el => el.classList.remove(
                    'is-invalid'));
                document.getElementById('formAlert').classList.add('d-none');
            }

            function showErrors(err) {
                clearFormErrors();
                const alert = document.getElementById('formAlert');
                if (err.message && typeof err.errors !== 'object') {
                    alert.textContent = err.message;
                    alert.classList.remove('d-none');
                    return;
                }
                if (err.errors) {
                    for (const [k, msgs] of Object.entries(err.errors)) {
                        const el = document.querySelector('[data-error-for="' + k + '"]');
                        const inp = document.getElementById(k) || document.querySelector('[name="' + k + '"]');
                        if (inp) inp.classList.add('is-invalid');
                        if (el) {
                            el.textContent = (msgs || []).join(' ');
                            el.classList.add('d-block');
                        }
                    }
                    alert.textContent = err.message || 'Please fix the highlighted fields.';
                    alert.classList.remove('d-none');
                }
            }

            function formPayload() {
                const fd = new FormData(document.getElementById('followupForm'));
                const ch = fd.get('channel_master_id');
                const payload = {
                    ticket_id: fd.get('ticket_id'),
                    order_id: fd.get('order_id') || null,
                    sku: fd.get('sku') || null,
                    channel_master_id: ch ? parseInt(ch, 10) : null,
                    customer_name: fd.get('customer_name'),
                    status: fd.get('status'),
                    comments: fd.get('comments') || null,
                    reference_link: fd.get('reference_link') || null,
                };
                if (payload.channel_master_id === 0 || isNaN(payload.channel_master_id)) payload.channel_master_id =
                    null;
                return payload;
            }

            let followupSearchDebounce = null;
            const filterSearchInput = document.getElementById('filterSearch');
            if (filterSearchInput) {
                filterSearchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        clearTimeout(followupSearchDebounce);
                        loadTable();
                    }
                });
                filterSearchInput.addEventListener('input', () => {
                    clearTimeout(followupSearchDebounce);
                    followupSearchDebounce = setTimeout(() => loadTable(), 320);
                });
            }

            document.getElementById('filterStatus').addEventListener('change', loadTable);

            document.getElementById('btnAddFollowup').addEventListener('click', () => {
                document.getElementById('followupModalLabel').textContent = 'Add Followup';
                document.getElementById('edit_id').value = '';
                document.getElementById('followupForm').reset();
                document.getElementById('ticket_id_display').value = '';
                document.getElementById('ticket_id').value = '';
                document.getElementById('ticket_id_hint').textContent =
                    'Generated automatically when you save (e.g. TKT-000001).';
                document.getElementById('ticket_id_hint').classList.remove('d-none');
                clearFormErrors();
            });

            document.getElementById('btnSaveFollowup').addEventListener('click', async () => {
                clearFormErrors();
                const form = document.getElementById('followupForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                const id = document.getElementById('edit_id').value;
                const payload = formPayload();
                if (!id) {
                    delete payload.ticket_id;
                }
                const url = id ? followupBase + '/' + id : storeUrl;
                const method = id ? 'PUT' : 'POST';
                try {
                    const res = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        showErrors({
                            message: data.message || 'Validation failed',
                            errors: data.errors || {}
                        });
                        return;
                    }
                    bootstrap.Modal.getInstance(document.getElementById('followupModal')).hide();
                    loadTable();
                } catch (e) {
                    document.getElementById('formAlert').textContent = 'Network error.';
                    document.getElementById('formAlert').classList.remove('d-none');
                }
            });

            document.getElementById('followupTableBody').addEventListener('change', async (e) => {
                const statusSel = e.target.closest('.followup-inline-status-select');
                if (statusSel && !statusSel.disabled) {
                    const id = statusSel.getAttribute('data-id');
                    if (!id) return;
                    statusSel.disabled = true;
                    try {
                        const res = await fetch(followupBase + '/' + id + '/inline-status', {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ status: statusSel.value })
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) {
                            alert(data.message || (data.errors ? Object.values(data.errors).flat().join(' ') :
                                'Could not save status.'));
                        }
                        await loadTable();
                    } catch (err) {
                        alert('Network error while saving status.');
                        await loadTable();
                    }
                    return;
                }
                const inp = e.target.closest('.followup-inline-dt');
                if (!inp || inp.disabled) return;
                const id = inp.getAttribute('data-id');
                const field = inp.getAttribute('data-field');
                if (!id || field !== 'next_followup_at') return;
                const body = { next_followup_at: inp.value || null };
                inp.disabled = true;
                try {
                    const res = await fetch(followupBase + '/' + id + '/inline-dates', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(body)
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') :
                            'Could not save date.');
                        alert(msg);
                    }
                    await loadTable();
                } catch (err) {
                    alert('Network error while saving date.');
                    await loadTable();
                }
            });

            document.getElementById('followupTableBody').addEventListener('click', async (e) => {
                const statusTrig = e.target.closest('.followup-inline-status-trigger');
                if (statusTrig) {
                    e.preventDefault();
                    const td = statusTrig.closest('td');
                    const id = statusTrig.getAttribute('data-id');
                    const rawSt = statusTrig.getAttribute('data-status') || '';
                    const statuses = ['Pending', 'Resolved', 'Escalated'];
                    const cur = statuses.includes(rawSt) ? rawSt : 'Pending';
                    td.innerHTML =
                        '<select class="form-select form-select-sm followup-inline-status-select" data-id="' +
                        escapeAttr(id) + '" aria-label="Status">' +
                        statuses.map(v =>
                            '<option value="' + escapeAttr(v) + '"' + (v === cur ? ' selected' : '') + '>' +
                            escapeHtml(v) + '</option>').join('') +
                        '</select>';
                    const sel = td.querySelector('select');
                    sel.focus();
                    let saved = false;
                    sel.addEventListener('change', () => {
                        saved = true;
                    }, { once: true });
                    sel.addEventListener('blur', () => {
                        setTimeout(() => {
                            if (!saved) {
                                loadTable();
                            }
                        }, 180);
                    }, { once: true });
                    return;
                }
                const copyOrderBtn = e.target.closest('.copy-order-btn');
                if (copyOrderBtn) {
                    e.preventDefault();
                    const text = copyOrderBtn.getAttribute('data-copy') || '';
                    try {
                        await navigator.clipboard.writeText(text);
                        copyOrderBtn.classList.add('copied');
                        copyOrderBtn.innerHTML = '<i class="bi bi-clipboard-check"></i>';
                        setTimeout(() => {
                            copyOrderBtn.classList.remove('copied');
                            copyOrderBtn.innerHTML = '<i class="bi bi-clipboard"></i>';
                        }, 1500);
                    } catch (err) {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                    }
                    return;
                }
                const btn = e.target.closest('button');
                if (!btn) return;
                const id = btn.dataset.id;
                if (btn.classList.contains('btn-del')) {
                    if (!confirm('Delete this follow-up?')) return;
                    await fetch(followupBase + '/' + id, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    loadTable();
                    return;
                }
                if (btn.classList.contains('btn-edit')) {
                    const res = await fetch(followupBase + '/' + id, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const d = await res.json();
                    document.getElementById('followupModalLabel').textContent = 'Edit Followup';
                    document.getElementById('edit_id').value = d.id;
                    document.getElementById('ticket_id').value = d.ticket_id;
                    document.getElementById('ticket_id_display').value = d.ticket_id;
                    document.getElementById('ticket_id_hint').textContent = 'Ticket ID cannot be changed.';
                    document.getElementById('ticket_id_hint').classList.remove('d-none');
                    document.getElementById('order_id').value = d.order_id || '';
                    document.getElementById('sku').value = d.sku || '';
                    document.getElementById('channel_master_id').value = d.channel_master_id || '';
                    document.getElementById('customer_name').value = d.customer_name;
                    document.getElementById('followup_status').value = d.status;
                    document.getElementById('comments').value = d.comments || '';
                    document.getElementById('reference_link').value = d.reference_link || '';
                    clearFormErrors();
                    new bootstrap.Modal(document.getElementById('followupModal')).show();
                }
            });

            loadTable();

            // TODO: Replace static data with API integration (single endpoint for list + stats).
            // TODO: Fetch channels via API endpoint for the Channel dropdown refresh.
        })();
    </script>
    {{--
    Sample $channels in controller:
        $channels = \App\Models\ChannelMaster::whereRaw('LOWER(TRIM(status)) = ?', ['active'])->orderBy('type')->orderBy('id')->get(['id','channel']);
    Dummy row shape from /customer-care/followups/data:
        {"id":1,"ticket_id":"TKT-DEMO-001","order_id":"ORD-1001","channel_name":"Amazon",
         "customecr_name":"Sample Customer","status":"Pending",
         "followup_display":"05 Apr 10:00",
         "next_followup":"03-18-2026 10:00","next_followup_at":"2026-03-18T10:00",
         "executive":"Executive A","reference_link":"https://...","overdue":false}
    --}}
@endsection
