@extends('layouts.vertical', ['title' => 'Purchase Specific Siblings'])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        .pss-summary { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
        .pss-badge {
            flex: 1 1 0; min-width: 150px; display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 14px; border-radius: 10px; color: #fff; font-size: 0.95rem; box-shadow: 0 1px 3px rgba(0,0,0,.12);
        }
        .pss-badge .label { font-weight: 500; opacity: .95; }
        .pss-badge .value { font-weight: 700; font-size: 1.15rem; }
        .pss-badge--skus { background: #2563eb; }
        .pss-badge--selected { background: #7c3aed; }

        .pss-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 10px; }
        .pss-toolbar .pss-search { max-width: 280px; }
        .pss-toolbar .select2-container { min-width: 260px; }
        .pss-toolbar .select2-container--bootstrap-5 .select2-selection { min-height: calc(1.5em + .5rem + 2px); font-size: .875rem; }

        #pss-table .tabulator-header { background: #D8F3F3; border-bottom: 1px solid #403f3f; }
        #pss-table .tabulator-header .tabulator-col { background: #3bc0c3; color: #fff; font-weight: 700; border-right: 1px solid #fff; }
        #pss-table .tabulator-header .tabulator-col .tabulator-col-content { padding: 10px 8px; }
        #pss-table .tabulator-row:nth-child(even) { background-color: #f8fafc; }
        #pss-table .tabulator-row:hover { background-color: #dbeafe; }
        #pss-table .tabulator-row.tabulator-selected { background-color: #ede9fe !important; }
        #pss-table .tabulator-row.pss-parent-row { background-color: #fffef2 !important; font-weight: 500; }
        #pss-table .tabulator-cell { padding: 7px 8px; font-size: 0.9rem; }
        .pss-img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; }

        .pss-add-btn { border: 0; background: #dcfce7; color: #166534; border-radius: 50%; width: 28px; height: 28px; line-height: 1; padding: 0; }
        .pss-add-btn:hover { background: #bbf7d0; }
        .pss-edit-btn { border: 0; background: #e0f2fe; color: #0369a1; border-radius: 6px; padding: 3px 9px; }
        .pss-edit-btn:hover { background: #bae6fd; }
        .pss-name-wrap { display: flex; align-items: center; gap: 8px; }
        .pss-name { flex: 1 1 auto; font-weight: 600; white-space: normal; word-break: break-word; }
        .pss-plus-btn { border: 0; background: #ede9fe; color: #6d28d9; border-radius: 50%; width: 28px; height: 28px; line-height: 1; padding: 0; }
        .pss-plus-btn:hover { background: #ddd6fe; }
        .pss-copy-btn { border: 0; background: #fef3c7; color: #b45309; border-radius: 6px; padding: 3px 9px; }
        .pss-copy-btn:hover { background: #fde68a; }
        #pssCopyModal .select2-container { width: 100% !important; }
        .pss-result-list { max-height: 200px; overflow-y: auto; font-size: .85rem; }
        .pss-result-list .ok { color: #166534; }
        .pss-result-list .skip { color: #92400e; }
        .pss-result-list .fail { color: #b91c1c; }
        .pss-members { display: flex; flex-wrap: wrap; gap: 4px; max-height: 110px; overflow-y: auto; }
        .pss-member { background: #e2e8f0; color: #334155; border-radius: 4px; padding: 1px 7px; font-size: .78rem; }
        #pssSiblingModal .select2-container { width: 100% !important; }
        .pss-count { font-size: .8rem; }
        .pss-count.over { color: #dc2626; font-weight: 700; }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Purchase Specific Siblings', 'sub_title' => 'Purchase'])

    <div class="row">
        <div class="col-12">
            <div class="pss-summary" aria-label="Summary">
                <span class="pss-badge pss-badge--skus" title="Rows in the current view">
                    <span class="label">📦 SKUs</span><span class="value" id="pss-total-skus">0</span>
                </span>
                <span class="pss-badge pss-badge--selected" title="Selected rows">
                    <span class="label">☑️ Selected</span><span class="value" id="pss-total-selected">0</span>
                </span>
            </div>
        </div>

        <div class="col-12">
            <div id="pss-flash" class="alert d-none py-2" role="alert"></div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="pss-toolbar">
                        <input type="text" id="pss-search" class="form-control form-control-sm border-primary pss-search" placeholder="Search parent, SKU or sibling…" autocomplete="off">
                        <select id="pss-name-filter" aria-label="Filter by purchase specific name"></select>
                        <button type="button" id="pss-refresh-btn" class="btn btn-sm btn-outline-secondary" title="Reload from CP Master">
                            <i class="fas fa-rotate"></i> Refresh
                        </button>
                        <span id="pss-loading" class="text-muted small d-none"><i class="fas fa-spinner fa-spin"></i> Loading…</span>
                    </div>
                    <div id="pss-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Create / edit sibling name modal --}}
    <div class="modal fade" id="pssModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pss-modal-title">Create purchase sibling</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small text-muted mb-2" id="pss-modal-info"></div>
                    <input type="text" id="pss-modal-input" class="form-control" maxlength="{{ $max }}" list="pss-name-options"
                        placeholder="Purchase sibling name (max {{ $max }} characters)" aria-label="Purchase sibling name" autocomplete="off">
                    <datalist id="pss-name-options"></datalist>
                    <div class="d-flex justify-content-between mt-1">
                        <span class="small text-muted" id="pss-modal-hint"></span>
                        <span class="pss-count" id="pss-modal-count">0 / {{ $max }}</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="pss-modal-save">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Add more SKUs to a purchase sibling name --}}
    <div class="modal fade" id="pssSiblingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pss-sib-title">Purchase sibling</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="pss-sib-name">Sibling name</label>
                        <input type="text" id="pss-sib-name" class="form-control" maxlength="{{ $max }}" list="pss-name-options"
                            placeholder="Purchase sibling name (max {{ $max }} characters)" autocomplete="off">
                        <div class="small text-muted mt-1" id="pss-sib-name-hint"></div>
                    </div>
                    <div class="mb-3">
                        <div class="fw-semibold mb-1">Current siblings <span class="text-muted fw-normal" id="pss-sib-member-count"></span></div>
                        <div class="pss-members" id="pss-sib-members"></div>
                    </div>
                    <div>
                        <label class="form-label fw-semibold" for="pss-sib-select">Add SKUs</label>
                        <select id="pss-sib-select" class="form-select" multiple></select>
                        <div class="small text-muted mt-1">SKUs already under another sibling name are marked “in …” and will be moved to this name.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="pss-sib-save">Add to sibling</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Copy specs modal --}}
    <div class="modal fade" id="pssCopyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Copy specs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 small" id="pss-copy-info"></div>
                    <label class="form-label fw-semibold" for="pss-copy-from">Copy from</label>
                    <select id="pss-copy-from" class="form-select"></select>
                    <div class="small text-muted mt-2">
                        Copies the <b>Critical</b> specs (with their Amazon / 5 Core values) of the chosen SKU.
                        A spec the destination already has is skipped, never overwritten.
                    </div>
                    <div class="pss-result-list mt-3 d-none" id="pss-copy-results"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="pss-copy-self-btn">Copy</button>
                    <button type="button" class="btn btn-success" id="pss-copy-siblings-btn">Copy to siblings</button>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Same data feed CP Master uses (lite = identity + image only).
            const DATA_URL = '/product-master-data-view?lite=1';
            const URLS = {
                siblings: @json(route('purchase.specific.siblings.data')),
                save: @json(route('purchase.specific.siblings.save')),
                copySpecs: @json(route('purchase.specific.siblings.copy-specs')),
            };
            const CSRF = @json(csrf_token());
            const MAX = @json($max);
            let table = null;
            let editTargets = []; // SKUs being edited in the modal

            const $ = (id) => document.getElementById(id);
            const normSku = (s) => String(s || '').replace(/\u00a0/g, ' ').trim().toUpperCase();
            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
            const isParentSku = (sku) => String(sku || '').toUpperCase().includes('PARENT');

            function flash(type, message) {
                const el = $('pss-flash');
                el.className = 'alert alert-' + type + ' py-2';
                el.textContent = message;
                clearTimeout(flash._t);
                flash._t = setTimeout(() => el.classList.add('d-none'), 7000);
            }

            function buildRows(data, siblings) {
                return data.map((item, idx) => ({
                    rowKey: item.id != null ? item.id : 'r' + idx,
                    image: item.image_path || '',
                    parent: item.Parent || '',
                    sku: item.SKU || '',
                    sibling: (siblings && siblings[normSku(item.SKU)]) || '',
                    isParent: isParentSku(item.SKU),
                }));
            }

            function refreshNameOptions() {
                const names = [...new Set(table.getData().map((r) => r.sibling).filter(Boolean))]
                    .sort((a, b) => a.localeCompare(b, undefined, {sensitivity: 'base'}));
                $('pss-name-options').innerHTML = names.map((n) => '<option value="' + esc(n) + '"></option>').join('');
                refreshNameFilter(names);
            }

            // Quick-search dropdown over the Purchase Specific Siblings column.
            const NO_SIBLING = '__none__';
            let nameFilter = '';
            const $nameFilter = window.jQuery ? window.jQuery('#pss-name-filter') : null;

            function refreshNameFilter(names) {
                if (!$nameFilter) return;
                const counts = {};
                let none = 0;
                table.getData().forEach((r) => {
                    if (r.isParent) return;
                    if (r.sibling) counts[r.sibling] = (counts[r.sibling] || 0) + 1;
                    else none++;
                });
                if (nameFilter && nameFilter !== NO_SIBLING && !counts[nameFilter]) nameFilter = '';
                const opts = ['<option value=""></option>', '<option value="' + NO_SIBLING + '">(No sibling) — ' + none.toLocaleString() + '</option>']
                    .concat(names.map((n) => '<option value="' + esc(n) + '">' + esc(n) + ' — ' + (counts[n] || 0) + '</option>'));
                $nameFilter.html(opts.join(''));
                if (!$nameFilter.data('select2')) {
                    $nameFilter.select2({
                        theme: 'bootstrap-5',
                        placeholder: 'Filter by purchase specific name…',
                        allowClear: true,
                        width: 'style',
                    });
                    $nameFilter.on('change', function () {
                        nameFilter = window.jQuery(this).val() || '';
                        applySearch();
                        updateBadges();
                    });
                }
                $nameFilter.val(nameFilter || null).trigger('change.select2');
                applySearch();
                updateBadges();
            }

            function updateBadges() {
                if (!table) return;
                $('pss-total-skus').textContent = table.getData('active').length.toLocaleString();
                $('pss-total-selected').textContent = table.getSelectedData().length.toLocaleString();
            }

            function buildTable() {
                table = new Tabulator('#pss-table', {
                    data: [],
                    height: '70vh',
                    layout: 'fitColumns',
                    placeholder: 'No products found',
                    index: 'rowKey',
                    selectableRows: true,
                    rowFormatter: (row) => {
                        row.getElement().classList.toggle('pss-parent-row', !!row.getData().isParent);
                    },
                    columns: [
                        {title: 'Select', field: 'select', formatter: 'rowSelection', titleFormatter: 'rowSelection',
                            headerSort: false, hozAlign: 'center', headerHozAlign: 'center', width: 80, resizable: false,
                            cellClick: (e, cell) => { e.stopPropagation(); cell.getRow().toggleSelect(); }},
                        {title: 'Image', field: 'image', headerSort: false, hozAlign: 'center', width: 90,
                            formatter: (cell) => {
                                const v = cell.getValue();
                                return v ? '<img class="pss-img" loading="lazy" src="' + esc(v) + '" alt="">' : '-';
                            }},
                        {title: 'Parent', field: 'parent', sorter: 'string', minWidth: 160, widthGrow: 2,
                            sorterParams: {locale: true},
                            formatter: (cell) => esc(cell.getValue()) || '-'},
                        {title: 'SKU', field: 'sku', sorter: 'string', minWidth: 200, widthGrow: 3,
                            sorterParams: {locale: true},
                            formatter: (cell) => esc(cell.getValue()) || '-'},
                        {title: 'Purchase Specific Siblings', field: 'sibling', sorter: 'string', minWidth: 260, widthGrow: 3,
                            sorterParams: {locale: true, alignEmptyValues: 'bottom'},
                            formatter: (cell) => {
                                const row = cell.getRow().getData();
                                if (row.isParent) return '<span class="text-muted">-</span>';
                                const v = String(cell.getValue() || '');
                                if (!v) {
                                    return '<button type="button" class="pss-add-btn" title="Create purchase sibling name"><i class="fas fa-plus"></i></button>';
                                }
                                return '<div class="pss-name-wrap"><span class="pss-name">' + esc(v) + '</span>'
                                    + '<button type="button" class="pss-edit-btn" title="Edit purchase sibling name"><i class="fas fa-pen"></i></button></div>';
                            },
                            cellClick: (e, cell) => {
                                if (!e.target.closest('.pss-add-btn, .pss-edit-btn')) return;
                                e.stopPropagation();
                                const row = cell.getRow();
                                const selected = table.getSelectedData().filter((r) => !r.isParent);
                                // The + / pencil on a selected row applies to every selected SKU at once.
                                if (row.isSelected() && selected.length > 1) openEdit(selected);
                                else openEdit([row.getData()]);
                            }},
                        {title: '+', field: 'add', headerSort: false, hozAlign: 'center', headerHozAlign: 'center', width: 70, resizable: false,
                            formatter: (cell) => cell.getRow().getData().isParent
                                ? '<span class="text-muted">-</span>'
                                : '<button type="button" class="pss-plus-btn" title="Add SKUs to this purchase sibling"><i class="fas fa-plus"></i></button>',
                            cellClick: (e, cell) => {
                                if (!e.target.closest('.pss-plus-btn')) return;
                                e.stopPropagation();
                                openSiblingModal(cell.getRow().getData());
                            }},
                        {title: 'Copy Specs', field: 'copySpecs', headerSort: false, hozAlign: 'center', headerHozAlign: 'center', width: 110, resizable: false,
                            formatter: (cell) => cell.getRow().getData().isParent
                                ? '<span class="text-muted">-</span>'
                                : '<button type="button" class="pss-copy-btn" title="Copy specs from another SKU"><i class="fas fa-copy"></i></button>',
                            cellClick: (e, cell) => {
                                if (!e.target.closest('.pss-copy-btn')) return;
                                e.stopPropagation();
                                openCopyModal(cell.getRow().getData());
                            }},
                    ],
                });
                table.on('rowSelectionChanged', updateBadges);
                table.on('dataFiltered', updateBadges);
                table.on('dataLoaded', updateBadges);
            }

            async function load() {
                $('pss-loading').classList.remove('d-none');
                try {
                    const opts = {headers: {'Accept': 'application/json'}, credentials: 'same-origin'};
                    const [res, sibRes] = await Promise.all([
                        fetch(DATA_URL + '&_ts=' + Date.now(), opts),
                        fetch(URLS.siblings, opts),
                    ]);
                    const json = await res.json();
                    if (!res.ok || !Array.isArray(json.data)) throw new Error(json.message || 'Could not load products.');
                    let siblings = {};
                    try {
                        const sJson = await sibRes.json();
                        if (!sibRes.ok || !sJson.success) throw new Error(sJson.message || 'Could not load purchase siblings.');
                        siblings = sJson.siblings || {};
                    } catch (err) {
                        flash('danger', err.message);
                    }
                    await table.setData(buildRows(json.data, siblings));
                    refreshNameOptions();
                    applySearch();
                    updateBadges();
                } catch (err) {
                    flash('danger', err.message);
                } finally {
                    $('pss-loading').classList.add('d-none');
                }
            }

            function applySearch() {
                const q = $('pss-search').value.trim().toLowerCase();
                if (!q && !nameFilter) { table.clearFilter(); return; }
                table.setFilter((row) => {
                    if (nameFilter === NO_SIBLING) {
                        if (row.isParent || row.sibling) return false;
                    } else if (nameFilter && row.sibling !== nameFilter) {
                        return false;
                    }
                    return !q || String(row.parent).toLowerCase().includes(q)
                        || String(row.sku).toLowerCase().includes(q)
                        || String(row.sibling).toLowerCase().includes(q);
                });
            }

            // ---------------------------------------------------------------- create / edit name
            function updateModalCount() {
                const len = $('pss-modal-input').value.length;
                const el = $('pss-modal-count');
                el.textContent = len + ' / ' + MAX;
                el.classList.toggle('over', len > MAX);
            }

            function openEdit(rows) {
                rows = rows.filter((r) => !r.isParent);
                if (!rows.length) return;
                editTargets = rows.map((r) => r.sku);
                const bulk = rows.length > 1;
                $('pss-modal-title').textContent = bulk ? 'Create purchase sibling for selected SKUs' : 'Create purchase sibling';
                $('pss-modal-info').innerHTML = bulk
                    ? 'Applies to <b>' + rows.length + '</b> selected SKUs.'
                    : 'SKU: <b>' + esc(rows[0].sku) + '</b>';
                const first = String(rows[0].sibling || '');
                const same = rows.every((r) => String(r.sibling || '') === first);
                $('pss-modal-input').value = same ? first : '';
                $('pss-modal-hint').textContent = 'Pick an existing name to group SKUs as siblings. Leave blank to clear.';
                updateModalCount();
                bootstrap.Modal.getOrCreateInstance($('pssModal')).show();
                setTimeout(() => $('pss-modal-input').focus(), 250);
            }

            async function saveEdit() {
                const name = $('pss-modal-input').value.trim();
                if (name.length > MAX) { flash('warning', 'Purchase sibling name can be at most ' + MAX + ' characters.'); return; }
                if (editTargets.length > 1 && !name
                    && !confirm('Clear the purchase sibling name of ' + editTargets.length + ' SKUs?')) return;
                const btn = $('pss-modal-save');
                btn.disabled = true;
                try {
                    const res = await fetch(URLS.save, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
                        credentials: 'same-origin',
                        body: JSON.stringify({skus: editTargets, name}),
                    });
                    let json = {};
                    try { json = await res.json(); } catch (e) {}
                    if (!res.ok || json.success === false) {
                        throw new Error(json.message || (json.errors ? Object.values(json.errors).flat().join(' ') : 'Request failed (' + res.status + ').'));
                    }
                    const saved = new Set(editTargets);
                    const finalName = json.name ?? name;
                    await table.updateData(table.getData().filter((r) => saved.has(r.sku)).map((r) => ({rowKey: r.rowKey, sibling: finalName})));
                    refreshNameOptions();
                    bootstrap.Modal.getOrCreateInstance($('pssModal')).hide();
                    flash('success', json.message || 'Saved.');
                } catch (err) {
                    flash('danger', err.message);
                } finally {
                    btn.disabled = false;
                }
            }

            // ---------------------------------------------------------------- add SKUs to a sibling name
            let sibRow = null; // row whose "+" was clicked
            const $sel = window.jQuery ? window.jQuery('#pss-sib-select') : null;

            function openSiblingModal(rowData) {
                if (!$sel) { flash('danger', 'jQuery / Select2 failed to load.'); return; }
                sibRow = rowData;
                const name = String(rowData.sibling || '');
                const nameInput = $('pss-sib-name');
                nameInput.value = name;
                nameInput.readOnly = !!name;
                $('pss-sib-name-hint').textContent = name
                    ? 'Adding SKUs to this sibling name.'
                    : 'This SKU has no sibling name yet — enter one; it will be created with this SKU plus the SKUs you pick.';
                $('pss-sib-title').textContent = name ? 'Purchase sibling: ' + name : 'Create purchase sibling';

                const all = table.getData().filter((r) => !r.isParent && r.sku);
                const members = name ? all.filter((r) => r.sibling === name) : [rowData];
                const memberSkus = new Set(members.map((r) => r.sku));
                $('pss-sib-member-count').textContent = '(' + members.length + ')';
                $('pss-sib-members').innerHTML = members.map((r) => '<span class="pss-member">' + esc(r.sku) + '</span>').join('')
                    || '<span class="text-muted small">None yet</span>';

                const seen = new Set();
                const options = [];
                all.forEach((r) => {
                    if (memberSkus.has(r.sku) || seen.has(r.sku)) return;
                    seen.add(r.sku);
                    options.push(new Option(r.sku + (r.sibling ? ' — in ' + r.sibling : ''), r.sku, false, false));
                });
                $sel.empty();
                options.forEach((o) => $sel[0].appendChild(o));
                if (!$sel.data('select2')) {
                    $sel.select2({
                        theme: 'bootstrap-5',
                        dropdownParent: window.jQuery('#pssSiblingModal'),
                        placeholder: 'Search and select SKUs…',
                        closeOnSelect: false,
                        width: '100%',
                    });
                }
                $sel.val(null).trigger('change');
                bootstrap.Modal.getOrCreateInstance($('pssSiblingModal')).show();
            }

            async function saveSiblings() {
                if (!sibRow) return;
                const name = $('pss-sib-name').value.trim();
                if (!name) { flash('warning', 'Enter a purchase sibling name.'); $('pss-sib-name').focus(); return; }
                const picked = ($sel.val() || []).slice();
                const hadName = !!sibRow.sibling;
                const skus = hadName ? picked : [sibRow.sku, ...picked];
                if (!picked.length && hadName) { flash('warning', 'Select at least one SKU to add.'); return; }

                const bySku = new Map(table.getData().map((r) => [r.sku, r]));
                const moving = picked.filter((s) => bySku.get(s) && bySku.get(s).sibling && bySku.get(s).sibling !== name);
                if (moving.length && !confirm(moving.length + ' SKU(s) already belong to another sibling name and will be moved to "' + name + '". Continue?')) return;

                const btn = $('pss-sib-save');
                btn.disabled = true;
                try {
                    const res = await fetch(URLS.save, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
                        credentials: 'same-origin',
                        body: JSON.stringify({skus, name}),
                    });
                    let json = {};
                    try { json = await res.json(); } catch (e) {}
                    if (!res.ok || json.success === false) {
                        throw new Error(json.message || (json.errors ? Object.values(json.errors).flat().join(' ') : 'Request failed (' + res.status + ').'));
                    }
                    const saved = new Set(skus);
                    const finalName = json.name ?? name;
                    await table.updateData(table.getData().filter((r) => saved.has(r.sku)).map((r) => ({rowKey: r.rowKey, sibling: finalName})));
                    refreshNameOptions();
                    bootstrap.Modal.getOrCreateInstance($('pssSiblingModal')).hide();
                    flash('success', picked.length + ' SKU(s) added to "' + finalName + '".');
                } catch (err) {
                    flash('danger', err.message);
                } finally {
                    btn.disabled = false;
                }
            }

            $('pss-sib-save').addEventListener('click', saveSiblings);

            // ---------------------------------------------------------------- copy specs
            let copyRow = null; // row whose copy icon was clicked
            const $copyFrom = window.jQuery ? window.jQuery('#pss-copy-from') : null;

            // SKUs that share the clicked row's sibling name (including the row itself).
            function siblingSkusOf(rowData) {
                if (!rowData.sibling) return [];
                const out = [];
                const seen = new Set();
                table.getData().forEach((r) => {
                    if (r.isParent || !r.sku || r.sibling !== rowData.sibling || seen.has(r.sku)) return;
                    seen.add(r.sku);
                    out.push(r.sku);
                });
                return out;
            }

            function openCopyModal(rowData) {
                if (!$copyFrom) { flash('danger', 'jQuery / Select2 failed to load.'); return; }
                copyRow = rowData;
                $('pss-copy-results').classList.add('d-none');
                $('pss-copy-results').innerHTML = '';
                $('pss-copy-info').innerHTML = 'Copy specs into SKU <b>' + esc(rowData.sku) + '</b>'
                    + (rowData.sibling ? ' · sibling <b>' + esc(rowData.sibling) + '</b>' : '');

                const seen = new Set([rowData.sku]);
                const opts = ['<option value=""></option>'];
                table.getData().forEach((r) => {
                    if (r.isParent || !r.sku || seen.has(r.sku)) return;
                    seen.add(r.sku);
                    opts.push('<option value="' + esc(r.sku) + '">' + esc(r.sku) + (r.sibling ? ' — ' + esc(r.sibling) : '') + '</option>');
                });
                $copyFrom.html(opts.join(''));
                if (!$copyFrom.data('select2')) {
                    $copyFrom.select2({
                        theme: 'bootstrap-5',
                        dropdownParent: window.jQuery('#pssCopyModal'),
                        placeholder: 'Search and select a SKU to copy from…',
                        allowClear: true,
                        width: '100%',
                    });
                }
                $copyFrom.val(null).trigger('change');

                const sibs = siblingSkusOf(rowData);
                const btn = $('pss-copy-siblings-btn');
                btn.disabled = sibs.length < 1;
                btn.textContent = 'Copy to siblings' + (sibs.length ? ' (' + sibs.length + ')' : '');
                btn.title = rowData.sibling ? 'Copy to every SKU under "' + rowData.sibling + '"' : 'This SKU has no purchase sibling name yet';
                bootstrap.Modal.getOrCreateInstance($('pssCopyModal')).show();
            }

            async function runCopy(targets) {
                const from = $copyFrom.val();
                if (!from) { flash('warning', 'Select a SKU to copy from.'); return; }
                const toSkus = targets.filter((s) => s !== from);
                if (!toSkus.length) { flash('warning', 'Nothing to copy to (the source SKU is the only target).'); return; }
                const btns = [$('pss-copy-self-btn'), $('pss-copy-siblings-btn')];
                const wasDisabled = btns.map((b) => b.disabled);
                btns.forEach((b) => { b.disabled = true; });
                const box = $('pss-copy-results');
                box.classList.remove('d-none');
                box.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin"></i> Copying…</span>';
                try {
                    const res = await fetch(URLS.copySpecs, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF},
                        credentials: 'same-origin',
                        body: JSON.stringify({from_sku: from, to_skus: toSkus}),
                    });
                    let json = {};
                    try { json = await res.json(); } catch (e) {}
                    if (!res.ok || json.success === false) {
                        throw new Error(json.message || (json.errors ? Object.values(json.errors).flat().join(' ') : 'Request failed (' + res.status + ').'));
                    }
                    box.innerHTML = '<div class="fw-semibold mb-1">' + esc(json.message) + '</div>' + (json.results || []).map((r) =>
                        '<div class="' + (r.status === 'copied' ? 'ok' : r.status === 'skipped' ? 'skip' : 'fail') + '"><b>' + esc(r.sku) + '</b> — ' + esc(r.message) + '</div>'
                    ).join('');
                    flash('success', json.message);
                } catch (err) {
                    box.innerHTML = '<div class="fail">' + esc(err.message) + '</div>';
                    flash('danger', err.message);
                } finally {
                    btns.forEach((b, i) => { b.disabled = wasDisabled[i]; });
                }
            }

            $('pss-copy-self-btn').addEventListener('click', () => runCopy([copyRow.sku]));
            $('pss-copy-siblings-btn').addEventListener('click', () => runCopy(siblingSkusOf(copyRow)));

            $('pss-modal-input').addEventListener('input', updateModalCount);
            $('pss-modal-input').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); saveEdit(); } });
            $('pss-modal-save').addEventListener('click', saveEdit);

            $('pss-refresh-btn').addEventListener('click', load);
            $('pss-search').addEventListener('input', applySearch);

            buildTable();
            table.on('tableBuilt', load);
        });
    </script>
@endsection
