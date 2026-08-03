@extends('layouts.vertical', ['title' => 'Short Title Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
@include('partials.parent-row-highlight')
<style>
    #short-title-master-table.tabulator {
        width: 100%;
    }
    #short-title-master-table .tabulator-header {
        background: linear-gradient(180deg, #eef3fb 0%, #e3ebf8 100%);
        border-bottom: 1px solid #c5d4ea;
    }
    #short-title-master-table .tabulator-header .tabulator-col {
        background: transparent;
        border-right: 1px solid #d7e0ef;
    }
    #short-title-master-table .tabulator-header .tabulator-col-content .tabulator-col-title {
        color: #1a3d7c;
        font-weight: 700;
        font-size: 0.9rem;
        text-align: center;
    }
    #short-title-master-table .tabulator-row .tabulator-cell {
        padding: 10px 8px;
        overflow: visible;
    }
    #short-title-master-table .tabulator-tableholder {
        overflow: auto !important;
    }
    #short-title-master-table .stm-cell {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 44px;
    }
    #short-title-master-table .tabulator-cell[tabulator-field="select"] {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    #short-title-master-table .tabulator-cell[tabulator-field="select"] input[type="checkbox"],
    #short-title-master-table .tabulator-col[tabulator-field="select"] input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
        accent-color: #1a3d7c;
    }
    #short-title-master-table .tabulator-row.tabulator-selected {
        background-color: #e8f0fe !important;
    }
    #short-title-master-table .tabulator-row.pm-parent-row.tabulator-selected,
    #short-title-master-table .tabulator-row.pm-parent-row.tabulator-selected .tabulator-cell {
        background-color: #fde68a !important;
    }
    #short-title-master-table .stm-cell--title {
        justify-content: flex-start;
        text-align: left;
        padding: 0 8px;
        white-space: normal;
        line-height: 1.35;
        font-size: 0.9rem;
    }
    .stm-product-img {
        width: 32px;
        height: 32px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        display: block;
        cursor: zoom-in;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .stm-product-img:hover {
        transform: scale(1.15);
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.16);
        position: relative;
        z-index: 5;
    }
    .stm-img-hover-preview {
        position: fixed;
        z-index: 10600;
        padding: 6px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 16px 48px rgba(15, 23, 42, 0.22);
        border: 1px solid #e2e8f0;
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.12s ease, visibility 0.12s;
        box-sizing: border-box;
    }
    .stm-img-hover-preview.is-visible {
        opacity: 1;
        visibility: visible;
    }
    .stm-img-hover-preview img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        border-radius: 8px;
        display: block;
    }
    .stm-edit-btn {
        width: 36px;
        height: 36px;
        border: 1px solid #c5d4ea;
        border-radius: 8px;
        background: #fff;
        color: #1a3d7c;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.15s ease, box-shadow 0.15s ease;
    }
    .stm-edit-btn:hover {
        background: #eef3fb;
        box-shadow: 0 0 0 2px #4dd0e1;
        color: #0f2d5c;
    }
    #stmEditModal .modal-header {
        border-bottom: 1px solid #e8eef8;
    }
    #stmEditModal .modal-title {
        color: #1a3d7c;
        font-weight: 700;
    }
    #stmEditModalSku {
        font-weight: 600;
        color: #374151;
    }
    .stm-save-toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 1080;
        display: none;
        padding: 10px 14px;
        border-radius: 8px;
        background: #1a3d7c;
        color: #fff;
        font-size: 0.875rem;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.25);
    }
    .stm-toolbar {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
        overflow-x: auto;
        padding-bottom: 2px;
    }
    .stm-toolbar-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #1a3d7c;
        white-space: nowrap;
        margin: 0;
        flex: 0 0 auto;
    }
    .stm-toolbar .time-navigation-group {
        flex: 0 0 auto;
    }
    .stm-toolbar .time-navigation-group button {
        width: 32px;
        height: 32px;
    }
    .stm-toolbar-field {
        flex: 1 1 120px;
        min-width: 110px;
        max-width: 180px;
    }
    .stm-toolbar-field .form-control,
    .stm-toolbar-field .form-select,
    .stm-toolbar-field .input-group-text {
        height: 34px;
        font-size: 0.85rem;
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
    }
    .stm-toolbar #stmAutopopulateBtn {
        white-space: nowrap;
        flex: 0 0 auto;
    }
    .stm-toolbar .badge {
        white-space: nowrap;
        flex: 0 0 auto;
    }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Short Title Master', 'sub_title' => 'Product Masters'])

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="stm-toolbar">
                    <h4 class="stm-toolbar-title">Short Title Master</h4>
                    @include('partials.parent-playback-controls')
                    <button type="button" class="btn btn-sm btn-success" id="stmAutopopulateBtn" title="Autopopulate selected SKUs from Amazon title (removes 5 Core and SKU)">
                        <i class="fas fa-magic me-1"></i>Autopopulate from Amazon
                    </button>
                    <span class="badge bg-secondary-subtle text-secondary" id="stmSelectedCount" title="Selected rows">0 selected</span>
                    <span class="badge bg-primary-subtle text-primary" id="stmCount">0</span>
                    <div class="stm-toolbar-field">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="stmParentSearch" class="form-control" placeholder="Parent…" aria-label="Search Parent">
                        </div>
                    </div>
                    <div class="stm-toolbar-field">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="stmSkuSearch" class="form-control" placeholder="SKU…" aria-label="Search SKU">
                        </div>
                    </div>
                    <div class="stm-toolbar-field">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="stmTitleSearch" class="form-control" placeholder="Short Title…" aria-label="Search Short Title">
                        </div>
                    </div>
                    <div class="stm-toolbar-field" style="max-width: 150px;">
                        <select id="stmMissingTitleFilter" class="form-select form-select-sm" aria-label="Missing Title">
                            <option value="all">All Titles</option>
                            <option value="missing">Missing Title</option>
                            <option value="has">Has Title</option>
                        </select>
                    </div>
                </div>
                <div id="short-title-master-table"></div>
            </div>
        </div>
    </div>
</div>
<div class="stm-save-toast" id="stmSaveToast"></div>
<div id="stmImgHoverPreview" class="stm-img-hover-preview" aria-hidden="true">
    <img src="" alt="Preview">
</div>

{{-- Edit Short Title modal --}}
<div class="modal fade" id="stmEditModal" tabindex="-1" aria-labelledby="stmEditModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stmEditModalTitle">
                    <i class="fas fa-pen me-2"></i><span id="stmEditModalTitleText">Edit Short Title</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2 small text-muted" id="stmEditModalSkuLine">SKU: <span id="stmEditModalSku">—</span></div>
                <div class="mb-2 small text-muted d-none" id="stmEditModalBulkLine">
                    Applying to <strong id="stmEditModalBulkCount">0</strong> selected SKU(s).
                    <div class="mt-1 text-break" id="stmEditModalBulkSkus" style="max-height: 72px; overflow: auto; font-size: 0.8rem;"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="stmEditModalInput" class="form-label fw-semibold mb-0">Short Title</label>
                    <small class="text-muted"><span id="stmEditCharCount">0</span>/40</small>
                </div>
                <textarea id="stmEditModalInput" class="form-control" rows="3" maxlength="40" placeholder="Enter short title (max 40 chars)…"></textarea>
                <div class="form-text">Internal use only. Keep whole words — max 40 characters. With checkboxes selected, Save applies to all selected rows.</div>
                <input type="hidden" id="stmEditModalSkuValue">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="stmEditModalSaveBtn">
                    <i class="fas fa-save me-1"></i>Save
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const dataUrl = @json(route('short.title.master.data'));
    const saveUrl = @json(route('short.title.master.save'));
    const autoUrl = @json(route('short.title.master.autopopulate'));
    let table = null;
    let editRow = null;
    let bulkEditRows = null;
    let navParent = null;
    let parentPlayback = null;

    const editModalEl = document.getElementById('stmEditModal');
    const editModal = bootstrap.Modal.getOrCreateInstance(editModalEl, {
        backdrop: true,
        keyboard: true,
    });

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function showToast(msg, isError) {
        const el = document.getElementById('stmSaveToast');
        el.textContent = msg;
        el.style.background = isError ? '#b91c1c' : '#1a3d7c';
        el.style.display = 'block';
        clearTimeout(showToast._t);
        showToast._t = setTimeout(() => { el.style.display = 'none'; }, 2200);
    }

    function updateEditCharCount() {
        const input = document.getElementById('stmEditModalInput');
        const countEl = document.getElementById('stmEditCharCount');
        const len = (input.value || '').length;
        countEl.textContent = String(len);
        countEl.classList.toggle('text-danger', len >= 40);
        countEl.classList.toggle('fw-semibold', len >= 40);
    }

    function isParentSku(sku) {
        return window.isPmParentSku
            ? window.isPmParentSku(sku)
            : String(sku || '').toUpperCase().includes('PARENT');
    }

    function resolveBulkEditRows(clickedRow) {
        if (!table || !clickedRow) return null;
        const selected = table.getSelectedRows() || [];
        if (selected.length < 1) return null;

        const clickedInSelection = selected.some(function (r) {
            return r === clickedRow;
        });
        if (!clickedInSelection) return null;

        // Multi-select + pencil = bulk edit (exclude PARENT placeholder rows)
        const targets = selected.filter(function (r) {
            const d = r.getData() || {};
            return !isParentSku(d.sku);
        });
        return targets.length > 1 ? targets : null;
    }

    function openEditModal(row) {
        const data = row.getData() || {};
        bulkEditRows = resolveBulkEditRows(row);
        editRow = row;

        const titleText = document.getElementById('stmEditModalTitleText');
        const skuLine = document.getElementById('stmEditModalSkuLine');
        const bulkLine = document.getElementById('stmEditModalBulkLine');

        if (bulkEditRows && bulkEditRows.length > 1) {
            const skus = bulkEditRows.map(function (r) {
                return String((r.getData() || {}).sku || '').trim();
            }).filter(Boolean);
            titleText.textContent = 'Bulk Edit Short Title (' + skus.length + ')';
            skuLine.classList.add('d-none');
            bulkLine.classList.remove('d-none');
            document.getElementById('stmEditModalBulkCount').textContent = String(skus.length);
            document.getElementById('stmEditModalBulkSkus').textContent = skus.join(', ');
            document.getElementById('stmEditModalSkuValue').value = skus[0] || '';
            // Prefill from clicked row when in selection
            const input = document.getElementById('stmEditModalInput');
            input.value = (data.short_title || '').slice(0, 40);
        } else {
            bulkEditRows = null;
            titleText.textContent = 'Edit Short Title';
            skuLine.classList.remove('d-none');
            bulkLine.classList.add('d-none');
            document.getElementById('stmEditModalSku').textContent = data.sku || '—';
            document.getElementById('stmEditModalSkuValue').value = data.sku || '';
            document.getElementById('stmEditModalInput').value = (data.short_title || '').slice(0, 40);
        }

        updateEditCharCount();
        editModal.show();
        setTimeout(() => {
            const input = document.getElementById('stmEditModalInput');
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        }, 250);
    }

    editModalEl.addEventListener('hidden.bs.modal', function () {
        bulkEditRows = null;
        editRow = null;
        document.getElementById('stmEditModalTitleText').textContent = 'Edit Short Title';
        document.getElementById('stmEditModalSkuLine').classList.remove('d-none');
        document.getElementById('stmEditModalBulkLine').classList.add('d-none');
    });

    document.getElementById('stmEditModalInput').addEventListener('input', updateEditCharCount);

    function hasShortTitle(data) {
        return !!(data.short_title && String(data.short_title).trim());
    }

    function updateCount() {
        if (!table) return;
        document.getElementById('stmCount').textContent = String(table.getDataCount('active'));
    }

    function updateSelectedCount() {
        if (!table) return;
        const n = table.getSelectedData().length;
        document.getElementById('stmSelectedCount').textContent = n + ' selected';
    }

    function applyFilters() {
        if (!table) return;
        const skuQ = (document.getElementById('stmSkuSearch').value || '').trim().toLowerCase();
        const parentQ = (document.getElementById('stmParentSearch').value || '').trim().toLowerCase();
        const titleQ = (document.getElementById('stmTitleSearch').value || '').trim().toLowerCase();
        const missingTitleFilter = document.getElementById('stmMissingTitleFilter').value || 'all';

        table.setFilter(function (data) {
            if (navParent != null && String(data.parent || '') !== String(navParent)) {
                return false;
            }
            if (skuQ && !String(data.sku || '').toLowerCase().includes(skuQ)) {
                return false;
            }
            if (parentQ && !String(data.parent || '').toLowerCase().includes(parentQ)) {
                return false;
            }
            if (titleQ && !String(data.short_title || '').toLowerCase().includes(titleQ)) {
                return false;
            }
            if (missingTitleFilter === 'missing' && hasShortTitle(data)) {
                return false;
            }
            if (missingTitleFilter === 'has' && !hasShortTitle(data)) {
                return false;
            }
            return true;
        });
        updateCount();
    }

    async function saveShortTitle(sku, shortTitle, row, options) {
        options = options || {};
        try {
            const resp = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ sku: sku, short_title: shortTitle }),
            });
            const data = await resp.json();
            if (!resp.ok || !data.success) {
                throw new Error(data.message || 'Save failed');
            }
            if (row) {
                row.update({
                    short_title: data.short_title || '',
                    has_saved: !!(data.short_title && String(data.short_title).trim()),
                });
            }
            if (!options.silent) {
                showToast('Saved');
            }
            return true;
        } catch (e) {
            if (!options.silent) {
                showToast(e.message || 'Save failed', true);
            }
            return false;
        }
    }

    document.getElementById('stmEditModalSaveBtn').addEventListener('click', async function () {
        const shortTitle = (document.getElementById('stmEditModalInput').value || '').trim();
        const btn = this;
        btn.disabled = true;

        try {
            if (bulkEditRows && bulkEditRows.length > 1) {
                let ok = 0;
                let fail = 0;
                for (const row of bulkEditRows) {
                    const sku = String((row.getData() || {}).sku || '').trim();
                    if (!sku) {
                        fail++;
                        continue;
                    }
                    const saved = await saveShortTitle(sku, shortTitle, row, { silent: true });
                    if (saved) ok++;
                    else fail++;
                }
                if (fail === 0) {
                    showToast('Saved ' + ok + ' short title(s)');
                    editModal.hide();
                    bulkEditRows = null;
                    editRow = null;
                } else {
                    showToast('Saved ' + ok + ', failed ' + fail, true);
                }
            } else {
                const sku = (document.getElementById('stmEditModalSkuValue').value || '').trim();
                if (!sku) return;
                const ok = await saveShortTitle(sku, shortTitle, editRow);
                if (ok) {
                    editModal.hide();
                    editRow = null;
                }
            }
        } finally {
            btn.disabled = false;
        }
    });

    function getSelectedSkusForAutopopulate() {
        if (!table) return [];
        return (table.getSelectedData() || [])
            .map(function (d) { return String(d.sku || '').trim(); })
            .filter(function (sku) {
                return sku !== '' && !(window.isPmParentSku ? window.isPmParentSku(sku) : sku.toUpperCase().includes('PARENT'));
            });
    }

    document.getElementById('stmAutopopulateBtn').addEventListener('click', function () {
        const skus = getSelectedSkusForAutopopulate();
        if (!skus.length) {
            alert('Select at least one SKU checkbox first.\nAutopopulate only runs on selected SKUs.');
            return;
        }
        if (!confirm('Autopopulate / shorten Short titles for ' + skus.length + ' selected SKU(s)?\n\n• Fill empty rows from Amazon title\n• Remove “5 Core” and the SKU\n• Keep whole leading words, max 40 characters\n• Also shorten any existing titles over 40 chars')) {
            return;
        }
        const btn = this;
        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Autopopulating…';
        fetch(autoUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify({ only_missing: true, skus: skus }),
        })
        .then(r => r.json())
        .then(resp => {
            alert(resp.message || 'Done');
            if (table) {
                table.replaceData().then(function () {
                    if (parentPlayback) parentPlayback.rebuildParents();
                    applyFilters();
                });
            }
        })
        .catch(() => alert('Autopopulate failed'))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = original;
        });
    });

    parentPlayback = window.ParentPlayback.create({
        getAllData: function () {
            if (!table) return [];
            return (table.getData('all') || []).map(function (r) {
                return { Parent: r.parent, SKU: r.sku };
            });
        },
        applyFilter: function (parent) {
            navParent = parent;
            applyFilters();
        },
    });

    table = new Tabulator('#short-title-master-table', {
        ajaxURL: dataUrl,
        ajaxResponse: function (url, params, response) {
            return response?.data || [];
        },
        layout: 'fitColumns',
        height: '70vh',
        rowHeight: 52,
        pagination: true,
        paginationSize: 25,
        paginationSizeSelector: [25, 50, 100, 200],
        placeholder: 'No products found',
        selectableRows: true,
        selectableRowsRangeMode: 'click',
        initialSort: [
            { column: 'parent', dir: 'asc' },
            { column: 'sku', dir: 'asc' },
        ],
        rowFormatter: function (row) {
            const el = row.getElement();
            const d = row.getData() || {};
            if (window.isPmParentSku && window.isPmParentSku(d.sku || d.SKU)) {
                el.classList.add('pm-parent-row');
            } else {
                el.classList.remove('pm-parent-row');
            }
        },
        columns: [
            {
                formatter: 'rowSelection',
                titleFormatter: 'rowSelection',
                titleFormatterParams: {
                    rowRange: 'active',
                },
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                width: 48,
                frozen: true,
                field: 'select',
                vertAlign: 'middle',
                cellClick: function (e) {
                    e.stopPropagation();
                },
            },
            {
                title: 'Image',
                field: 'image',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 64,
                vertAlign: 'middle',
                formatter: function (cell) {
                    const src = cell.getValue();
                    if (!src) {
                        return '<div class="stm-cell"><span class="text-muted">—</span></div>';
                    }
                    return `<div class="stm-cell"><img src="${escapeHtml(src)}" class="stm-product-img" alt="SKU" loading="lazy" onerror="this.outerHTML='<span class=\\'text-muted\\'>—</span>'"></div>`;
                },
            },
            {
                title: 'Parent',
                field: 'parent',
                minWidth: 120,
                widthGrow: 1,
                hozAlign: 'center',
                headerHozAlign: 'center',
                vertAlign: 'middle',
                formatter: function (cell) {
                    const val = (cell.getValue() || '').trim();
                    if (!val) {
                        return '<div class="stm-cell"><span class="text-muted">—</span></div>';
                    }
                    return `<div class="stm-cell"><span>${escapeHtml(val)}</span></div>`;
                },
            },
            {
                title: 'SKU',
                field: 'sku',
                minWidth: 160,
                widthGrow: 1,
                hozAlign: 'center',
                headerHozAlign: 'center',
                vertAlign: 'middle',
                // PARENT placeholder SKUs sort last within each parent (same as /product-master)
                sorter: function (a, b) {
                    const skuA = String(a || '');
                    const skuB = String(b || '');
                    const aParent = skuA.toUpperCase().includes('PARENT');
                    const bParent = skuB.toUpperCase().includes('PARENT');
                    if (aParent !== bParent) {
                        return aParent ? 1 : -1;
                    }
                    return skuA.localeCompare(skuB, undefined, { sensitivity: 'base' });
                },
                formatter: function (cell) {
                    return `<div class="stm-cell"><span class="fw-semibold">${escapeHtml(cell.getValue() || '')}</span></div>`;
                },
            },
            {
                title: 'Short Title',
                field: 'short_title',
                minWidth: 280,
                widthGrow: 4,
                hozAlign: 'left',
                headerHozAlign: 'center',
                vertAlign: 'middle',
                formatter: function (cell) {
                    const val = (cell.getValue() || '').trim();
                    if (!val) {
                        return '<div class="stm-cell stm-cell--title"><span class="text-muted">—</span></div>';
                    }
                    return `<div class="stm-cell stm-cell--title">${escapeHtml(val)}</div>`;
                },
            },
            {
                title: 'Edit',
                field: 'edit',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 80,
                vertAlign: 'middle',
                headerSort: false,
                formatter: function () {
                    return `<div class="stm-cell">
                        <button type="button" class="stm-edit-btn" title="Edit / Bulk edit selected" aria-label="Edit Short Title">
                            <i class="fas fa-pen"></i>
                        </button>
                    </div>`;
                },
                cellClick: function (e, cell) {
                    e.preventDefault();
                    e.stopPropagation();
                    openEditModal(cell.getRow());
                },
            },
        ],
    });

    table.on('dataLoaded', function () {
        if (parentPlayback) parentPlayback.rebuildParents();
        updateCount();
        updateSelectedCount();
    });
    table.on('dataFiltered', function () {
        updateCount();
        updateSelectedCount();
    });
    table.on('rowSelectionChanged', updateSelectedCount);

    (function setupImageHoverPreview() {
        const root = document.getElementById('short-title-master-table');
        const layer = document.getElementById('stmImgHoverPreview');
        const layerImg = layer ? layer.querySelector('img') : null;
        if (!root || !layer || !layerImg) return;

        let hideTimer = null;
        function hidePreview() {
            layer.classList.remove('is-visible');
            layer.setAttribute('aria-hidden', 'true');
        }

        function positionPreview(anchorImg) {
            const r = anchorImg.getBoundingClientRect();
            const size = Math.min(260, Math.floor(window.innerWidth * 0.85), Math.floor(window.innerHeight * 0.55));
            let left = r.left + (r.width / 2) - (size / 2);
            let top = r.bottom + 10;
            left = Math.max(10, Math.min(left, window.innerWidth - size - 10));
            if (top + size > window.innerHeight - 10) {
                top = Math.max(10, r.top - size - 10);
            }
            layer.style.width = size + 'px';
            layer.style.height = size + 'px';
            layer.style.left = left + 'px';
            layer.style.top = top + 'px';
        }

        root.addEventListener('mouseover', function (e) {
            const img = e.target.closest('.stm-product-img');
            if (!img) return;
            const src = img.getAttribute('src');
            if (!src) return;
            clearTimeout(hideTimer);
            layerImg.src = src;
            positionPreview(img);
            layer.classList.add('is-visible');
            layer.setAttribute('aria-hidden', 'false');
        });

        root.addEventListener('mouseout', function (e) {
            const img = e.target.closest('.stm-product-img');
            if (!img) return;
            hideTimer = setTimeout(hidePreview, 60);
        });

        window.addEventListener('scroll', function () {
            clearTimeout(hideTimer);
            hidePreview();
        }, true);
    })();

    let searchTimer = null;
    function onSearchInput() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 200);
    }
    document.getElementById('stmSkuSearch').addEventListener('input', onSearchInput);
    document.getElementById('stmParentSearch').addEventListener('input', onSearchInput);
    document.getElementById('stmTitleSearch').addEventListener('input', onSearchInput);
    document.getElementById('stmMissingTitleFilter').addEventListener('change', applyFilters);
});
</script>
@endsection
