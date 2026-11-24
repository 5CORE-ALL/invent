@extends('layouts.vertical', ['title' => 'Purchase Description'])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .pd-summary { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .pd-badge {
            flex: 1 1 0; min-width: 150px; display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 14px; border-radius: 10px; color: #fff; font-size: 0.95rem; box-shadow: 0 1px 3px rgba(0,0,0,.12);
        }
        .pd-badge .label { font-weight: 500; opacity: .95; }
        .pd-badge .value { font-weight: 700; font-size: 1.15rem; }
        .pd-badge--skus { background: #2563eb; }
        .pd-badge--selected { background: #7c3aed; }
        .pd-badge--inv { background: #059669; }
        .pd-badge--ovl30 { background: #d97706; }

        .pd-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 10px; }
        .pd-toolbar .pd-search { max-width: 280px; }

        #pd-table .tabulator-header { background: #D8F3F3; border-bottom: 1px solid #403f3f; }
        #pd-table .tabulator-header .tabulator-col { background: #3bc0c3; color: #fff; font-weight: 700; border-right: 1px solid #fff; }
        #pd-table .tabulator-header .tabulator-col .tabulator-col-content { padding: 10px 8px; }
        #pd-table .tabulator-row:nth-child(even) { background-color: #f8fafc; }
        #pd-table .tabulator-row:hover { background-color: #dbeafe; }
        #pd-table .tabulator-row.tabulator-selected { background-color: #ede9fe !important; }
        #pd-table .tabulator-row.pd-parent-row { background-color: #fffef2 !important; font-weight: 500; }
        #pd-table .tabulator-cell { padding: 7px 8px; font-size: 0.9rem; }
        .pd-img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; }

        .pd-badge--missing { background: #dc2626; cursor: pointer; user-select: none; }
        .pd-badge--missing.is-active { outline: 3px solid #fecaca; box-shadow: 0 0 0 2px #dc2626 inset; }

        .pd-desc-wrap { display: flex; align-items: center; gap: 6px; }
        .pd-desc-text { flex: 1 1 auto; white-space: normal; word-break: break-word; line-height: 1.25; }
        .pd-desc-empty { color: #dc2626; font-style: italic; }
        .pd-copy-btn { flex: 0 0 auto; border: 0; background: #f1f5f9; color: #475569; border-radius: 6px; padding: 3px 8px; }
        .pd-copy-btn:hover { background: #e2e8f0; }
        .pd-copy-btn.copied { background: #dcfce7; color: #166534; }
        .pd-edit-btn { border: 0; background: #e0f2fe; color: #0369a1; border-radius: 6px; padding: 3px 9px; }
        .pd-edit-btn:hover { background: #bae6fd; }
        .pd-count { font-size: .8rem; }
        .pd-count.over { color: #dc2626; font-weight: 700; }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Purchase Description', 'sub_title' => 'Purchase'])

    <div class="row">
        <div class="col-12">
            <div class="pd-summary" aria-label="Summary">
                <span class="pd-badge pd-badge--skus" title="Rows in the current view">
                    <span class="label">📦 SKUs</span><span class="value" id="pd-total-skus">0</span>
                </span>
                <span class="pd-badge pd-badge--selected" title="Selected rows">
                    <span class="label">☑️ Selected</span><span class="value" id="pd-total-selected">0</span>
                </span>
                <span class="pd-badge pd-badge--missing" id="pd-badge-missing" title="SKUs with no purchase description — click to show only these">
                    <span class="label">⚠️ Missing description</span><span class="value" id="pd-total-missing">0</span>
                </span>
                <span class="pd-badge pd-badge--inv" title="Total inventory of SKUs in the current view (parent rows excluded)">
                    <span class="label">🏬 Inv</span><span class="value" id="pd-total-inv">0</span>
                </span>
                <span class="pd-badge pd-badge--ovl30" title="Total OV L30 of SKUs in the current view (parent rows excluded)">
                    <span class="label">🛒 OV L30</span><span class="value" id="pd-total-ovl30">0</span>
                </span>
            </div>
        </div>

        <div class="col-12">
            <div id="pd-flash" class="alert d-none py-2" role="alert"></div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="pd-toolbar">
                        <input type="text" id="pd-search" class="form-control form-control-sm border-primary pd-search" placeholder="Search parent or SKU…" autocomplete="off">
                        <button type="button" id="pd-bulk-btn" class="btn btn-sm btn-primary" disabled title="Edit the purchase description of all selected SKUs">
                            <i class="fas fa-pen"></i> Bulk edit <span id="pd-bulk-count"></span>
                        </button>
                        <button type="button" id="pd-refresh-btn" class="btn btn-sm btn-outline-secondary" title="Reload from CP Master">
                            <i class="fas fa-rotate"></i> Refresh
                        </button>
                        <span id="pd-loading" class="text-muted small d-none"><i class="fas fa-spinner fa-spin"></i> Loading…</span>
                    </div>
                    <div id="pd-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit / bulk edit modal --}}
    <div class="modal fade" id="pdEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pd-modal-title">Edit purchase description</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2" id="pd-modal-info"></div>
                    <textarea id="pd-modal-input" class="form-control" rows="4" maxlength="{{ $max }}"
                        placeholder="Short purchase description (max {{ $max }} characters)" aria-label="Purchase description"></textarea>
                    <div class="d-flex justify-content-between mt-1">
                        <span class="small text-muted" id="pd-modal-hint"></span>
                        <span class="pd-count" id="pd-modal-count">0 / {{ $max }}</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="pd-modal-save">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Same data feed CP Master uses (lite = identity, image, Shopify inv / L30 only).
            const DATA_URL = '/product-master-data-view?lite=1';
            const URLS = {
                descriptions: @json(route('purchase.description.data')),
                saveBulk: @json(route('purchase.description.save-bulk')),
            };
            const CSRF = @json(csrf_token());
            const MAX = @json($max);
            let table = null;
            let missingOnly = false;
            let editTargets = []; // SKUs being edited in the modal

            const $ = (id) => document.getElementById(id);
            const normSku = (s) => String(s || '').replace(/\u00a0/g, ' ').trim().toUpperCase();
            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
            const isParentSku = (sku) => String(sku || '').toUpperCase().includes('PARENT');

            // Same colour rules as CP Master `getDilTextColor`.
            function dilColor(percent) {
                if (percent === null || isNaN(percent)) return '#6c757d';
                if (percent < 25) return '#dc3545';
                if (percent < 50) return '#1b5e20';
                return '#ad1457';
            }

            function flash(type, message) {
                const el = $('pd-flash');
                el.className = 'alert alert-' + type + ' py-2';
                el.textContent = message;
                clearTimeout(flash._t);
                flash._t = setTimeout(() => el.classList.add('d-none'), 7000);
            }

            // Mirrors CP Master: parent rows show the sum of their child SKUs.
            function buildRows(data, descriptions) {
                const parentTotals = {};
                data.forEach((item) => {
                    if (item.Parent && !isParentSku(item.SKU)) {
                        const t = parentTotals[item.Parent] || (parentTotals[item.Parent] = {inv: 0, ovl30: 0});
                        t.inv += Number(item.shopify_inv) || 0;
                        t.ovl30 += Number(item.shopify_quantity) || 0;
                    }
                });

                return data.map((item, idx) => {
                    const parentRow = isParentSku(item.SKU);
                    const totals = parentRow ? (parentTotals[item.Parent] || {inv: 0, ovl30: 0}) : null;
                    const inv = parentRow ? totals.inv : (Number(item.shopify_inv) || 0);
                    const ovl30 = parentRow ? totals.ovl30 : (Number(item.shopify_quantity) || 0);
                    return {
                        rowKey: item.id != null ? item.id : 'r' + idx,
                        image: item.image_path || '',
                        parent: item.Parent || '',
                        sku: item.SKU || '',
                        inv: inv,
                        ovl30: ovl30,
                        dil: inv > 0 ? (ovl30 / inv) * 100 : null,
                        description: (descriptions && descriptions[normSku(item.SKU)]) || '',
                        isParent: parentRow,
                    };
                });
            }

            function updateBadges() {
                if (!table) return;
                const rows = table.getData('active');
                const skuRows = rows.filter((r) => !r.isParent);
                $('pd-total-skus').textContent = rows.length.toLocaleString();
                const selected = table.getSelectedData();
                $('pd-total-selected').textContent = selected.length.toLocaleString();
                // Missing = every SKU row (parent rows excluded) without a description, regardless of filters.
                $('pd-total-missing').textContent = table.getData()
                    .filter((r) => !r.isParent && !String(r.description || '').trim()).length.toLocaleString();
                $('pd-badge-missing').classList.toggle('is-active', missingOnly);
                const bulkN = selected.filter((r) => !r.isParent).length;
                $('pd-bulk-btn').disabled = bulkN < 1;
                $('pd-bulk-count').textContent = bulkN ? '(' + bulkN + ')' : '';
                $('pd-total-inv').textContent = skuRows.reduce((s, r) => s + (Number(r.inv) || 0), 0).toLocaleString();
                $('pd-total-ovl30').textContent = skuRows.reduce((s, r) => s + (Number(r.ovl30) || 0), 0).toLocaleString();
            }

            function buildTable() {
                table = new Tabulator('#pd-table', {
                    data: [],
                    height: '70vh',
                    layout: 'fitColumns',
                    placeholder: 'No products found',
                    index: 'rowKey',
                    selectableRows: true,
                    rowFormatter: (row) => {
                        row.getElement().classList.toggle('pd-parent-row', !!row.getData().isParent);
                    },
                    columns: [
                        {title: 'Select', field: 'select', formatter: 'rowSelection', titleFormatter: 'rowSelection',
                            headerSort: false, hozAlign: 'center', headerHozAlign: 'center', width: 80, resizable: false,
                            cellClick: (e, cell) => { e.stopPropagation(); cell.getRow().toggleSelect(); }},
                        {title: 'Image', field: 'image', headerSort: false, hozAlign: 'center', width: 90,
                            formatter: (cell) => {
                                const v = cell.getValue();
                                return v ? '<img class="pd-img" loading="lazy" src="' + esc(v) + '" alt="">' : '-';
                            }},
                        {title: 'Parent', field: 'parent', sorter: 'string', minWidth: 128, widthGrow: 1.6,
                            sorterParams: {locale: true},
                            formatter: (cell) => esc(cell.getValue()) || '-'},
                        {title: 'SKU', field: 'sku', sorter: 'string', minWidth: 160, widthGrow: 2.4,
                            sorterParams: {locale: true},
                            formatter: (cell) => esc(cell.getValue()) || '-'},
                        {title: 'Inv', field: 'inv', sorter: 'number', hozAlign: 'center', width: 100,
                            formatter: (cell) => '<b>' + (Number(cell.getValue()) || 0).toLocaleString() + '</b>'},
                        {title: 'OV L30', field: 'ovl30', sorter: 'number', hozAlign: 'center', width: 110,
                            formatter: (cell) => '<b>' + (Number(cell.getValue()) || 0).toLocaleString() + '</b>'},
                        {title: 'Dil%', field: 'dil', sorter: 'number', hozAlign: 'center', width: 100,
                            sorterParams: {alignEmptyValues: 'bottom'},
                            formatter: (cell) => {
                                const v = cell.getValue();
                                if (v === null || v === undefined) return '<b>-</b>';
                                return '<span style="color:' + dilColor(v) + ';font-weight:600;">' + Math.round(v) + '%</span>';
                            }},
                        {title: 'Purchase Description (max ' + MAX + ')', field: 'description', sorter: 'string',
                            minWidth: 320, widthGrow: 5, variableHeight: true,
                            formatter: (cell) => {
                                const row = cell.getRow().getData();
                                if (row.isParent) return '<span class="text-muted">-</span>';
                                const v = String(cell.getValue() || '');
                                if (!v) return '<span class="pd-desc-empty">Missing</span>';
                                return '<div class="pd-desc-wrap"><span class="pd-desc-text">' + esc(v) + '</span>'
                                    + '<button type="button" class="pd-copy-btn" title="Copy description"><i class="fas fa-copy"></i></button></div>';
                            },
                            cellClick: (e, cell) => {
                                const btn = e.target.closest('.pd-copy-btn');
                                if (!btn) return;
                                e.stopPropagation();
                                copyText(String(cell.getValue() || ''), btn);
                            }},
                        {title: 'Edit', field: 'edit', headerSort: false, hozAlign: 'center', headerHozAlign: 'center', width: 80,
                            formatter: (cell) => cell.getRow().getData().isParent
                                ? '<span class="text-muted">-</span>'
                                : '<button type="button" class="pd-edit-btn" title="Edit (applies to all selected SKUs when multiple rows are selected)"><i class="fas fa-pen"></i></button>',
                            cellClick: (e, cell) => {
                                if (!e.target.closest('.pd-edit-btn')) return;
                                e.stopPropagation();
                                const row = cell.getRow();
                                const selected = table.getSelectedData().filter((r) => !r.isParent);
                                // With multiple rows selected, any row's edit icon edits all selected SKUs (bulk).
                                if (selected.length > 1) openEdit(selected);
                                else openEdit([row.getData()]);
                            }},
                    ],
                });
                table.on('rowSelectionChanged', updateBadges);
                table.on('dataFiltered', updateBadges);
                table.on('dataLoaded', updateBadges);
            }

            async function load() {
                $('pd-loading').classList.remove('d-none');
                try {
                    const opts = {headers: {'Accept': 'application/json'}, credentials: 'same-origin'};
                    const [prodRes, descRes] = await Promise.all([
                        fetch(DATA_URL + '&_ts=' + Date.now(), opts),
                        fetch(URLS.descriptions, opts),
                    ]);
                    const json = await prodRes.json();
                    if (!prodRes.ok || !Array.isArray(json.data)) throw new Error(json.message || 'Could not load products.');
                    let descriptions = {};
                    try {
                        const dJson = await descRes.json();
                        if (!descRes.ok || !dJson.success) throw new Error(dJson.message || 'Could not load purchase descriptions.');
                        descriptions = dJson.descriptions || {};
                    } catch (err) {
                        flash('danger', err.message);
                    }
                    await table.setData(buildRows(json.data, descriptions));
                    applySearch();
                    updateBadges();
                } catch (err) {
                    flash('danger', err.message);
                } finally {
                    $('pd-loading').classList.add('d-none');
                }
            }

            function applySearch() {
                const q = $('pd-search').value.trim().toLowerCase();
                if (!q && !missingOnly) { table.clearFilter(); return; }
                table.setFilter((row) => {
                    if (missingOnly && (row.isParent || String(row.description || '').trim())) return false;
                    return !q || String(row.parent).toLowerCase().includes(q) || String(row.sku).toLowerCase().includes(q);
                });
            }

            // ---------------------------------------------------------------- copy
            async function copyText(text, btn) {
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(text);
                    } else {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.style.position = 'fixed';
                        ta.style.opacity = '0';
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        ta.remove();
                    }
                    btn.classList.add('copied');
                    btn.innerHTML = '<i class="fas fa-check"></i>';
                    setTimeout(() => {
                        btn.classList.remove('copied');
                        btn.innerHTML = '<i class="fas fa-copy"></i>';
                    }, 1200);
                } catch (err) {
                    flash('danger', 'Could not copy to clipboard.');
                }
            }

            // ---------------------------------------------------------------- edit / bulk edit
            function updateModalCount() {
                const len = $('pd-modal-input').value.length;
                const el = $('pd-modal-count');
                el.textContent = len + ' / ' + MAX;
                el.classList.toggle('over', len > MAX);
            }

            function openEdit(rows) {
                rows = rows.filter((r) => !r.isParent);
                if (!rows.length) return;
                editTargets = rows.map((r) => r.sku);
                const bulk = rows.length > 1;
                $('pd-modal-title').textContent = bulk ? 'Bulk edit purchase description' : 'Edit purchase description';
                $('pd-modal-info').innerHTML = bulk
                    ? 'Applies to <b>' + rows.length + '</b> selected SKUs.'
                    : 'SKU: <b>' + esc(rows[0].sku) + '</b>';
                // Prefill when every target already shares the same text (always true for a single SKU).
                const first = String(rows[0].description || '');
                const same = rows.every((r) => String(r.description || '') === first);
                $('pd-modal-input').value = same ? first : '';
                $('pd-modal-hint').textContent = bulk
                    ? (same ? 'Saving overwrites the description of every selected SKU.' : 'Selected SKUs have different descriptions — saving overwrites all of them. Leave blank to clear.')
                    : '';
                updateModalCount();
                bootstrap.Modal.getOrCreateInstance($('pdEditModal')).show();
                setTimeout(() => $('pd-modal-input').focus(), 250);
            }

            async function saveEdit() {
                const description = $('pd-modal-input').value.trim();
                if (description.length > MAX) { flash('warning', 'Purchase description can be at most ' + MAX + ' characters.'); return; }
                if (editTargets.length > 1 && !description
                    && !confirm('Clear the purchase description of ' + editTargets.length + ' SKUs?')) return;
                const btn = $('pd-modal-save');
                btn.disabled = true;
                try {
                    const res = await fetch(URLS.saveBulk, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
                        credentials: 'same-origin',
                        body: JSON.stringify({skus: editTargets, description}),
                    });
                    let json = {};
                    try { json = await res.json(); } catch (e) {}
                    if (!res.ok || json.success === false) {
                        throw new Error(json.message || (json.errors ? Object.values(json.errors).flat().join(' ') : 'Request failed (' + res.status + ').'));
                    }
                    const saved = new Set(editTargets);
                    const updates = table.getData().filter((r) => saved.has(r.sku)).map((r) => ({rowKey: r.rowKey, description}));
                    await table.updateData(updates);
                    bootstrap.Modal.getOrCreateInstance($('pdEditModal')).hide();
                    flash('success', json.message || 'Saved.');
                    if (missingOnly) applySearch();
                    updateBadges();
                } catch (err) {
                    flash('danger', err.message);
                } finally {
                    btn.disabled = false;
                }
            }

            $('pd-modal-input').addEventListener('input', updateModalCount);
            $('pd-modal-save').addEventListener('click', saveEdit);
            $('pd-bulk-btn').addEventListener('click', () => openEdit(table.getSelectedData()));
            $('pd-badge-missing').addEventListener('click', () => {
                missingOnly = !missingOnly;
                applySearch();
                updateBadges();
            });

            $('pd-refresh-btn').addEventListener('click', load);
            $('pd-search').addEventListener('input', applySearch);

            buildTable();
            table.on('tableBuilt', load);
        });
    </script>
@endsection
