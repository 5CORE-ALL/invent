@extends('layouts.vertical', ['title' => 'Category Masters', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    .page-title-box .page-title { display: none; }
    .cm-toolbar {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-start;
        direction: ltr;
        gap: 8px;
        margin-bottom: 12px;
    }
    .cm-toolbar .form-control,
    .cm-toolbar .form-select,
    .cm-toolbar .input-group-text {
        height: 31px;
        padding-top: 2px;
        padding-bottom: 2px;
    }
    .cm-toolbar .cm-search { width: 180px; flex: 0 0 180px; }
    .cm-toolbar .cm-category-search { width: 220px; flex: 0 0 220px; }
    .cm-toolbar .cm-select { width: 130px; flex: 0 0 130px; }
    .cm-toolbar .cm-scope { width: 150px; flex: 0 0 150px; }
    #cm-table.tabulator { width: 100%; }
    #cm-table .tabulator-header {
        background: linear-gradient(180deg, #eef3fb 0%, #e3ebf8 100%);
        border-bottom: 1px solid #c5d4ea;
    }
    #cm-table .tabulator-header .tabulator-col {
        background: transparent;
        border-right: 1px solid #d7e0ef;
    }
    #cm-table .tabulator-header .tabulator-col-content .tabulator-col-title {
        color: #1a3d7c;
        font-weight: 700;
        font-size: 0.9rem;
        text-align: center;
    }
    #cm-table .tabulator-header .tabulator-col.tabulator-sortable { cursor: pointer; }
    #cm-table .tabulator-header .tabulator-col .tabulator-arrow { border-bottom-color: #5b7bb5; border-top-color: #5b7bb5; }
    #cm-table .tabulator-row .tabulator-cell {
        padding: 6px 8px;
        overflow: visible;
        text-align: center !important;
    }
    #cm-table .tabulator-tableholder { overflow: auto !important; }
    #cm-table .tabulator-row.cm-parent-row { background: #f1f5fb; font-weight: 700; }
    #cm-table .tabulator-row.cm-parent-row.tabulator-row-even { background: #e8eef8; }
    #cm-table .cm-cell {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 38px;
    }
    .cm-product-img {
        width: 38px;
        height: 38px;
        object-fit: cover;
        border: none;
        outline: none;
        background: transparent;
        display: block;
    }
    #cm-table .cm-input { min-width: 140px; text-align: center; }
    #cm-table .cm-input--parent { border-color: #1a3d7c; background: #f8fbff; font-weight: 700; }
    .cm-toolbar .cm-group-label { font-weight: 700; font-size: 0.8rem; color: #1a3d7c; }
    .cm-toolbar .cm-pull-btn,
    .cm-toolbar .cm-push-btn { white-space: nowrap; }
    #cm-table .cm-actions { gap: 4px; flex-wrap: nowrap; }
    #cm-table .cm-act { padding: 1px 7px; font-size: 0.75rem; line-height: 1.5; }
    #cm-table .cm-row-check, #cmSelectAll { cursor: pointer; }
    .cm-chip { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; white-space: nowrap; }
    .cm-chip--ok { background: #dcfce7; color: #166534; }
    .cm-chip--ready { background: #dbeafe; color: #1d4ed8; }
    .cm-chip--same { background: #e5e7eb; color: #374151; }
    .cm-chip--error { background: #fee2e2; color: #b91c1c; }
    .cm-chip--skip { background: #fef3c7; color: #b45309; }
    #cmResultModal .cm-result-table td { vertical-align: top; font-size: 0.85rem; }
    #cmResultModal .cm-result-scroll { max-height: 55vh; overflow: auto; }
    #cmClear {
        cursor: pointer;
        background: #e5e7eb;
        color: #374151;
        font-weight: 700;
    }
    #cmClear.cm-clear--on { background: #dc2626; color: #fff; }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Category Masters', 'sub_title' => 'Product Masters'])

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="cm-toolbar">
                    <button type="button" class="btn btn-sm btn-primary text-nowrap" id="cmEdit" title="Edit the categories of every row at once">
                        <i class="fas fa-pen me-1"></i>Edit
                    </button>
                    <button type="button" class="btn btn-sm btn-success text-nowrap d-none" id="cmSave">
                        <i class="fas fa-floppy-disk me-1"></i><span id="cmSaveLabel">Save</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary text-nowrap d-none" id="cmCancel">Cancel</button>
                    <span class="cm-group-label ms-2">Pull</span>
                    <button type="button" class="btn btn-sm btn-outline-primary cm-pull-btn" data-channel="ebay" title="Read the live category from eBay (accounts 1, 2, 3) for the selected SKUs">eBay</button>
                    <button type="button" class="btn btn-sm btn-outline-primary cm-pull-btn" data-channel="amz" title="Read the live category from Amazon for the selected SKUs">Amz</button>
                    <button type="button" class="btn btn-sm btn-outline-primary cm-pull-btn" data-channel="temu" title="Read the live category from Temu (accounts 1, 2, 3) for the selected SKUs">Temu</button>
                    <span class="cm-group-label ms-2">Push</span>
                    <button type="button" class="btn btn-sm btn-outline-danger cm-push-btn" data-channel="ebay" title="Send the saved category to eBay (dry run first)">eBay</button>
                    <button type="button" class="btn btn-sm btn-outline-danger cm-push-btn" data-channel="amz" title="Send the saved category to Amazon (dry run first)">Amz</button>
                    <button type="button" class="btn btn-sm btn-outline-danger cm-push-btn" data-channel="temu" title="Temu has no category-change call in this app">Temu</button>
                    <span class="badge bg-dark px-2 py-1 text-nowrap" title="Selected rows">Selected: <span id="cmSelectedCount">0</span></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="cmRefresh">
                        <i class="fas fa-rotate me-1"></i>Refresh
                    </button>
                    <span class="badge bg-primary px-2 py-1 text-nowrap" title="Rows currently shown">Rows: <span id="cmCount">0</span></span>
                    <span class="badge bg-info text-dark px-2 py-1 text-nowrap" title="Parent rows currently shown">Parents: <span id="cmParentCount">0</span></span>
                    <span class="badge bg-secondary px-2 py-1 text-nowrap" title="SKU rows currently shown">SKUs: <span id="cmSkuCount">0</span></span>
                    <span class="small text-muted" id="cmHint"></span>
                </div>
                <div class="cm-toolbar">
                    <div class="input-group input-group-sm cm-search">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="cmParentSearch" class="form-control" placeholder="Parent" aria-label="Search Parent">
                    </div>
                    <div class="input-group input-group-sm cm-search">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="cmSkuSearch" class="form-control" placeholder="SKU" aria-label="Search SKU">
                    </div>
                    <select id="cmRowType" class="form-select form-select-sm cm-select" aria-label="Rows">
                        <option value="all">All</option>
                        <option value="parent">Parent</option>
                        <option value="sku">SKU</option>
                    </select>
                    <div class="input-group input-group-sm cm-category-search">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="cmCategorySearch" class="form-control" placeholder="Category" aria-label="Search Category">
                    </div>
                    <select id="cmCategoryScope" class="form-select form-select-sm cm-scope" aria-label="Category column">
                        <option value="all">All categories</option>
                        <option value="category_ebay">Category eBay</option>
                        <option value="category_amz">Category Amz</option>
                        <option value="category_temu">Category Temu</option>
                    </select>
                    <button type="button" class="badge border-0 px-2 py-1 text-nowrap" id="cmClear" title="Clear search and filters">Clear</button>
                </div>
                <div id="cm-table"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cmResultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cmResultTitle">Result</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="cmResultSummary" class="mb-2 fw-semibold"></div>
                <div class="cm-result-scroll" id="cmResultBody"></div>
            </div>
            <div class="modal-footer">
                <span class="me-auto small text-muted" id="cmResultNote"></span>
                <button type="button" class="btn btn-danger d-none" id="cmResultConfirm">Confirm push</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dataUrl = @json(route('category.masters.data'));
    const saveUrl = @json(route('category.masters.save.bulk'));
    const pullUrl = @json(route('category.masters.pull'));
    const pushUrl = @json(route('category.masters.push'));
    const CATEGORY_FIELDS = ['category_ebay', 'category_amz', 'category_temu'];
    const CHANNELS = {
        ebay: { label: 'eBay', field: 'category_ebay' },
        amz: { label: 'Amazon', field: 'category_amz' },
        temu: { label: 'Temu', field: 'category_temu' },
    };
    const CHUNK = 10;

    let table = null;
    let editMode = false;
    let busy = false;
    // id -> name + channel ID of each category column, as last loaded, pulled or saved.
    let original = new Map();
    // parent name -> ids of its SKU rows (parent rows excluded).
    let childIdsByParent = new Map();
    // SKU -> row id.
    let idBySku = new Map();
    // ids ticked in the checkbox column.
    const selectedIds = new Set();

    const el = function (id) { return document.getElementById(id); };

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function fmtNum(v) {
        const n = Number(v);
        if (!Number.isFinite(n)) return '—';
        return Number.isInteger(n) ? n.toLocaleString() : String(Math.round(n * 100) / 100);
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function cleanValue(v) {
        return String(v ?? '').replace(/\s+/g, ' ').trim();
    }

    function textSort() {
        return { sorter: 'alphanum', sorterParams: { alignEmptyValues: 'bottom' }, headerSort: true, headerSortStartingDir: 'asc' };
    }

    function rowPasses(data) {
        const parentQ = (el('cmParentSearch').value || '').trim().toLowerCase();
        const skuQ = (el('cmSkuSearch').value || '').trim().toLowerCase();
        const type = el('cmRowType').value;
        const categoryQ = (el('cmCategorySearch').value || '').trim().toLowerCase();
        const scope = el('cmCategoryScope').value;

        if (type === 'parent' && !data.is_parent) return false;
        if (type === 'sku' && data.is_parent) return false;
        if (parentQ && !String(data.parent || '').toLowerCase().includes(parentQ)) return false;
        if (skuQ && !String(data.sku || '').toLowerCase().includes(skuQ)) return false;
        if (categoryQ) {
            const fields = scope === 'all' ? CATEGORY_FIELDS : [scope];
            const hit = fields.some(function (f) {
                return String(data[f] || '').toLowerCase().includes(categoryQ);
            });
            if (!hit) return false;
        }
        return true;
    }

    function filtersActive() {
        return (el('cmParentSearch').value || '').trim() !== ''
            || (el('cmSkuSearch').value || '').trim() !== ''
            || (el('cmCategorySearch').value || '').trim() !== ''
            || el('cmRowType').value !== 'all'
            || el('cmCategoryScope').value !== 'all';
    }

    function paintClearBadge() {
        el('cmClear').classList.toggle('cm-clear--on', filtersActive());
    }

    function updateCount() {
        if (!table) return;
        const rows = table.getData('active');
        const parents = rows.filter(function (r) { return r.is_parent; }).length;
        el('cmCount').textContent = rows.length.toLocaleString();
        el('cmParentCount').textContent = parents.toLocaleString();
        el('cmSkuCount').textContent = (rows.length - parents).toLocaleString();
    }

    function applyFilters() {
        if (!table) return;
        table.setFilter(rowPasses);
        updateCount();
        paintClearBadge();
    }

    function indexRows(rows) {
        original = new Map();
        childIdsByParent = new Map();
        idBySku = new Map();
        rows.forEach(function (row) {
            idBySku.set(row.sku, row.id);
            original.set(row.id, {
                category_ebay: row.category_ebay || '',
                category_ebay_id: row.category_ebay_id || '',
                category_amz: row.category_amz || '',
                category_amz_id: row.category_amz_id || '',
                category_temu: row.category_temu || '',
                category_temu_id: row.category_temu_id || '',
            });
            if (!row.is_parent && row.parent) {
                if (!childIdsByParent.has(row.parent)) childIdsByParent.set(row.parent, []);
                childIdsByParent.get(row.parent).push(row.id);
            }
        });
    }

    function categoryColumn(title, field) {
        return {
            title: title,
            field: field,
            minWidth: 170,
            widthGrow: 1,
            hozAlign: 'center',
            headerHozAlign: 'center',
            ...textSort(),
            formatter: function (cell) {
                const data = cell.getRow().getData();
                const value = cleanValue(data[field]);
                if (editMode) {
                    const cls = 'form-control form-control-sm cm-input' + (data.is_parent ? ' cm-input--parent' : '');
                    const tip = data.is_parent ? ' title="Changing this also updates every SKU of this parent"' : '';
                    return `<div class="cm-cell"><input type="text" class="${cls}" data-id="${escapeHtml(data.id)}" data-field="${field}" value="${escapeHtml(value)}" maxlength="255" placeholder="Category" autocomplete="off"${tip}></div>`;
                }
                if (!value) return '<span class="text-muted">—</span>';
                const idText = cleanValue(data[field + '_id']);
                return `<span title="${escapeHtml(idText ? value + ' (ID ' + idText + ')' : value)}">${escapeHtml(value)}</span>`;
            },
        };
    }

    // Pull / Push buttons of one row. Parent rows have no listing, so they get none.
    function actionColumn(title, action) {
        return {
            title: title,
            field: '_' + action,
            width: 190,
            hozAlign: 'center',
            headerHozAlign: 'center',
            headerSort: false,
            formatter: function (cell) {
                const data = cell.getRow().getData();
                if (data.is_parent) return '<span class="text-muted">—</span>';
                const cls = action === 'pull' ? 'btn-outline-primary' : 'btn-outline-danger';
                const buttons = Object.keys(CHANNELS).map(function (channel) {
                    return `<button type="button" class="btn btn-sm ${cls} cm-act" data-action="${action}" data-channel="${channel}" title="${action === 'pull' ? 'Pull from' : 'Push to'} ${CHANNELS[channel].label}">${channel === 'amz' ? 'Amz' : CHANNELS[channel].label}</button>`;
                }).join('');
                return `<div class="cm-cell cm-actions">${buttons}</div>`;
            },
            cellClick: function (e, cell) {
                const btn = e.target.closest('.cm-act');
                if (!btn) return;
                const id = cell.getRow().getData().id;
                // Bulk when several rows are ticked and this row is one of them.
                const ids = selectedIds.has(id) && selectedIds.size > 1 ? Array.from(selectedIds) : [id];
                runAction(btn.dataset.action, btn.dataset.channel, ids);
            },
        };
    }

    table = new Tabulator('#cm-table', {
        ajaxURL: dataUrl,
        ajaxResponse: function (url, params, response) {
            const rows = Array.isArray(response?.data) ? response.data : [];
            indexRows(rows);
            return rows;
        },
        index: 'id',
        layout: 'fitColumns',
        height: '72vh',
        pagination: false,
        placeholder: 'No rows found',
        columnDefaults: { headerSort: true, headerSortTristate: false },
        initialFilter: rowPasses,
        rowFormatter: function (row) {
            row.getElement().classList.toggle('cm-parent-row', !!row.getData().is_parent);
        },
        columns: [
            {
                title: '',
                field: '_select',
                width: 46,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                titleFormatter: function () {
                    return '<input type="checkbox" id="cmSelectAll" aria-label="Select all shown rows">';
                },
                formatter: function (cell) {
                    const id = cell.getRow().getData().id;
                    return `<input type="checkbox" class="cm-row-check" data-id="${escapeHtml(id)}"${selectedIds.has(id) ? ' checked' : ''} aria-label="Select row">`;
                },
                headerClick: function (e) {
                    const box = e.target.closest('#cmSelectAll');
                    if (!box) return;
                    table.getData('active').forEach(function (row) {
                        if (box.checked) selectedIds.add(row.id);
                        else selectedIds.delete(row.id);
                    });
                    refreshChecks();
                },
                cellClick: function (e, cell) {
                    const box = e.target.closest('.cm-row-check');
                    if (!box) return;
                    const id = cell.getRow().getData().id;
                    if (box.checked) selectedIds.add(id);
                    else selectedIds.delete(id);
                    syncSelectionUi();
                },
            },
            {
                title: 'Image',
                field: 'image',
                width: 72,
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: true,
                headerSortStartingDir: 'desc',
                sorter: function (a, b) {
                    const rank = function (v) { return v ? 1 : 0; };
                    const diff = rank(a) - rank(b);
                    if (diff !== 0) return diff;
                    return String(a || '').localeCompare(String(b || ''));
                },
                formatter: function (cell) {
                    const src = cell.getValue();
                    if (!src) return '<div class="cm-cell"><span class="text-muted">—</span></div>';
                    return `<div class="cm-cell"><img src="${escapeHtml(src)}" class="cm-product-img" alt="SKU" loading="lazy" onerror="this.outerHTML='<span class=\\'text-muted\\'>—</span>'"></div>`;
                },
            },
            {
                title: 'Parent',
                field: 'parent',
                minWidth: 140,
                widthGrow: 1,
                hozAlign: 'center',
                headerHozAlign: 'center',
                ...textSort(),
                formatter: function (cell) {
                    const v = cleanValue(cell.getValue());
                    return v ? escapeHtml(v) : '<span class="text-muted">—</span>';
                },
            },
            {
                title: 'SKU',
                field: 'sku',
                minWidth: 220,
                widthGrow: 2,
                hozAlign: 'center',
                headerHozAlign: 'center',
                ...textSort(),
                formatter: function (cell) {
                    return `<span class="fw-semibold">${escapeHtml(cell.getValue())}</span>`;
                },
            },
            {
                title: 'Inv',
                field: 'inv',
                width: 100,
                hozAlign: 'center',
                headerHozAlign: 'center',
                sorter: 'number',
                sorterParams: { alignEmptyValues: 'bottom' },
                headerSortStartingDir: 'desc',
                formatter: function (cell) { return fmtNum(cell.getValue()); },
            },
            categoryColumn('Category eBay', 'category_ebay'),
            categoryColumn('Category Amz', 'category_amz'),
            categoryColumn('Category Temu', 'category_temu'),
            actionColumn('Pull', 'pull'),
            actionColumn('Push', 'push'),
        ],
    });

    table.on('dataLoaded', updateCount);
    table.on('dataFiltered', function () {
        updateCount();
        syncSelectionUi();
    });

    // ---- Selection -------------------------------------------------------

    function syncSelectionUi() {
        el('cmSelectedCount').textContent = selectedIds.size.toLocaleString();
        const allBox = el('cmSelectAll');
        if (!allBox || !table) return;
        const shown = table.getData('active');
        const selectedShown = shown.filter(function (row) { return selectedIds.has(row.id); }).length;
        allBox.checked = shown.length > 0 && selectedShown === shown.length;
        allBox.indeterminate = selectedShown > 0 && selectedShown < shown.length;
    }

    function refreshChecks() {
        table.getRows().forEach(function (row) {
            const rowEl = row.getElement();
            if (rowEl && rowEl.isConnected) row.reformat();
        });
        syncSelectionUi();
    }

    // ---- Bulk edit -------------------------------------------------------

    function dirtyItems() {
        const items = [];
        table.getData().forEach(function (row) {
            const before = original.get(row.id);
            if (!before) return;
            const changed = {};
            CATEGORY_FIELDS.forEach(function (f) {
                const now = cleanValue(row[f]);
                if (now !== cleanValue(before[f])) {
                    changed[f] = now;
                    changed[f + '_id'] = cleanValue(row[f + '_id']);
                }
            });
            if (Object.keys(changed).length) {
                items.push(Object.assign({ id: row.id, sku: row.sku, parent: row.parent || '' }, changed));
            }
        });
        return items;
    }

    function syncEditUi() {
        const n = editMode ? dirtyItems().length : 0;
        el('cmEdit').classList.toggle('d-none', editMode);
        el('cmSave').classList.toggle('d-none', !editMode);
        el('cmCancel').classList.toggle('d-none', !editMode);
        el('cmSaveLabel').textContent = n ? 'Save (' + n + ')' : 'Save';
    }

    function setHint(text) {
        el('cmHint').textContent = text || '';
    }

    function setEditMode(on) {
        editMode = on;
        syncEditUi();
        setHint(on ? 'Editing all rows. Changing a parent row also updates its SKUs.' : '');
        table.redraw(true);
    }

    // A parent row's value flows down to every SKU row of that parent.
    el('cm-table').addEventListener('change', function (e) {
        const input = e.target.closest('.cm-input');
        if (!input || !editMode) return;
        const id = Number(input.dataset.id);
        const field = input.dataset.field;
        const value = cleanValue(input.value);
        const row = table.getRow(id);
        if (!row) return;

        const data = row.getData();
        const patch = {};
        patch[field] = value;
        // A retyped name no longer matches the channel ID Pull saved for it.
        patch[field + '_id'] = '';
        const updates = [Object.assign({ id: id }, patch)];

        if (data.is_parent && data.parent) {
            (childIdsByParent.get(data.parent) || []).forEach(function (childId) {
                const child = table.getRow(childId);
                if (child && cleanValue(child.getData()[field]) !== value) {
                    updates.push(Object.assign({ id: childId }, patch));
                }
            });
            setHint('Updated ' + (updates.length - 1) + ' SKU(s) under ' + data.parent + '.');
        }

        table.updateData(updates);
        syncEditUi();
    });

    async function save() {
        const items = dirtyItems();
        if (!items.length) {
            setEditMode(false);
            return;
        }
        const saveBtn = el('cmSave');
        saveBtn.disabled = true;
        try {
            const res = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    items: items.map(function (item) {
                        const out = { sku: item.sku };
                        CATEGORY_FIELDS.forEach(function (f) {
                            if (f in item) {
                                out[f] = item[f];
                                out[f + '_id'] = item[f + '_id'] || '';
                            }
                        });
                        return out;
                    }),
                }),
            });
            const body = await res.json().catch(function () { return {}; });
            if (!res.ok) {
                const firstError = body.errors ? Object.values(body.errors)[0]?.[0] : null;
                throw new Error(firstError || body.message || 'Unable to save categories.');
            }
            items.forEach(function (item) {
                const snap = original.get(item.id);
                CATEGORY_FIELDS.forEach(function (f) {
                    if (f in item) {
                        snap[f] = item[f];
                        snap[f + '_id'] = item[f + '_id'] || '';
                    }
                });
            });
            editMode = false;
            syncEditUi();
            table.redraw(true);
            setHint('Saved ' + (body.updated ?? items.length) + ' row(s).');
        } catch (err) {
            alert(err.message || 'Unable to save categories.');
        } finally {
            saveBtn.disabled = false;
        }
    }

    function cancelEdit() {
        const items = dirtyItems();
        if (items.length && !confirm('Discard ' + items.length + ' unsaved change(s)?')) return;
        const reverts = items.map(function (item) {
            return Object.assign({ id: item.id }, original.get(item.id));
        });
        if (reverts.length) table.updateData(reverts);
        setEditMode(false);
    }

    function reload() {
        if (editMode && dirtyItems().length && !confirm('Reload and discard unsaved changes?')) return;
        editMode = false;
        syncEditUi();
        setHint('');
        table.replaceData();
    }

    el('cmEdit').addEventListener('click', function () { setEditMode(true); });
    el('cmSave').addEventListener('click', save);
    el('cmCancel').addEventListener('click', cancelEdit);
    el('cmRefresh').addEventListener('click', reload);

    window.addEventListener('beforeunload', function (e) {
        if (editMode && dirtyItems().length) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // ---- Pull / Push -----------------------------------------------------

    let pendingPush = null;

    function setToolbarDisabled(on) {
        document.querySelectorAll('.cm-pull-btn, .cm-push-btn').forEach(function (btn) { btn.disabled = on; });
    }

    async function postJson(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(payload),
        });
        const body = await res.json().catch(function () { return {}; });
        if (!res.ok) {
            const firstError = body.errors ? Object.values(body.errors)[0]?.[0] : null;
            throw new Error(firstError || body.message || ('Request failed (' + res.status + ').'));
        }
        return body;
    }

    function chip(status) {
        const map = {
            pulled: ['cm-chip--ok', 'Pulled'],
            pushed: ['cm-chip--ok', 'Pushed'],
            ready: ['cm-chip--ready', 'Ready'],
            unchanged: ['cm-chip--same', 'Unchanged'],
            error: ['cm-chip--error', 'Error'],
            skipped: ['cm-chip--skip', 'Skipped'],
            unsupported: ['cm-chip--skip', 'Not supported'],
        };
        const hit = map[status] || ['cm-chip--same', status || '—'];
        return `<span class="cm-chip ${hit[0]}">${escapeHtml(hit[1])}</span>`;
    }

    function accountsHtml(accounts) {
        if (!Array.isArray(accounts) || !accounts.length) return '';
        return accounts.map(function (a) {
            const now = a.current ? ' (now: ' + a.current + ')' : '';
            const path = a.status === 'ok' && a.path ? ': ' + a.path : (a.message ? ': ' + a.message : '');
            return `<div class="text-muted">${escapeHtml(a.account + path + now)}</div>`;
        }).join('');
    }

    function showModal(title, summary, bodyHtml, note) {
        el('cmResultTitle').textContent = title;
        el('cmResultSummary').innerHTML = summary;
        el('cmResultBody').innerHTML = bodyHtml;
        el('cmResultNote').textContent = note || '';
        bootstrap.Modal.getOrCreateInstance(el('cmResultModal')).show();
    }

    function countBy(results) {
        const counts = {};
        results.forEach(function (r) { counts[r.status] = (counts[r.status] || 0) + 1; });
        return counts;
    }

    function runAction(action, channel, ids) {
        if (busy) return;
        if (editMode) {
            alert('Save or cancel your edits first.');
            return;
        }
        const rows = ids.map(function (id) { return table.getRow(id)?.getData(); }).filter(Boolean);
        const skuRows = rows.filter(function (r) { return !r.is_parent; });
        if (!skuRows.length) {
            alert('Tick at least one SKU row. Parent rows have no listing.');
            return;
        }
        const skippedParents = rows.length - skuRows.length;
        if (action === 'pull') runPull(channel, skuRows, skippedParents);
        else runPush(channel, skuRows, skippedParents);
    }

    function applyPulled(channel, result) {
        const id = idBySku.get(result.sku);
        if (id === undefined) return;
        const field = CHANNELS[channel].field;
        const patch = {};
        patch[field] = result.category || '';
        patch[field + '_id'] = result.category_id || '';
        table.updateData([Object.assign({ id: id }, patch)]);
        Object.assign(original.get(id) || {}, patch);
    }

    async function runPull(channel, rows, skippedParents) {
        const cfg = CHANNELS[channel];
        if (!confirm('Pull the ' + cfg.label + ' category for ' + rows.length + ' SKU(s)?\nTheir saved ' + cfg.label + ' category is replaced by the live one.')) return;

        busy = true;
        setToolbarDisabled(true);
        const results = [];
        try {
            for (let i = 0; i < rows.length; i += CHUNK) {
                setHint('Pulling ' + cfg.label + ' category… ' + Math.min(i + CHUNK, rows.length) + '/' + rows.length);
                const part = rows.slice(i, i + CHUNK).map(function (r) { return r.sku; });
                try {
                    const body = await postJson(pullUrl, { channel: channel, skus: part });
                    (body.results || []).forEach(function (r) {
                        results.push(r);
                        if (r.status === 'pulled') applyPulled(channel, r);
                    });
                } catch (err) {
                    part.forEach(function (sku) { results.push({ sku: sku, status: 'error', message: err.message }); });
                }
            }
        } finally {
            busy = false;
            setToolbarDisabled(false);
            setHint('');
        }

        const counts = countBy(results);
        const rowsHtml = results.map(function (r) {
            return `<tr>
                <td class="fw-semibold">${escapeHtml(r.sku)}</td>
                <td>${chip(r.status)}</td>
                <td>${escapeHtml(r.category || '')}${r.category_id ? ' <span class="text-muted">(ID ' + escapeHtml(r.category_id) + ')</span>' : ''}</td>
                <td>${escapeHtml(r.message || '')}${accountsHtml(r.accounts)}</td>
            </tr>`;
        }).join('');
        showModal(
            'Pull ' + cfg.label + ' category',
            'Pulled ' + (counts.pulled || 0) + ' &middot; Failed ' + (counts.error || 0) + ' &middot; Skipped ' + (counts.skipped || 0),
            `<table class="table table-sm cm-result-table"><thead><tr><th>SKU</th><th>Result</th><th>Category</th><th>Details</th></tr></thead><tbody>${rowsHtml}</tbody></table>`,
            skippedParents ? skippedParents + ' parent row(s) were ignored.' : ''
        );
    }

    async function runPush(channel, rows, skippedParents) {
        const cfg = CHANNELS[channel];
        busy = true;
        setToolbarDisabled(true);
        const plans = [];
        try {
            for (let i = 0; i < rows.length; i += CHUNK) {
                setHint('Checking ' + cfg.label + ' (dry run)… ' + Math.min(i + CHUNK, rows.length) + '/' + rows.length);
                const part = rows.slice(i, i + CHUNK).map(function (r) { return { sku: r.sku }; });
                try {
                    const body = await postJson(pushUrl, { channel: channel, dry_run: true, items: part });
                    (body.results || []).forEach(function (r) { plans.push(r); });
                } catch (err) {
                    part.forEach(function (p) { plans.push({ sku: p.sku, status: 'error', message: err.message, accounts: [] }); });
                }
            }
        } finally {
            busy = false;
            setToolbarDisabled(false);
            setHint('');
        }

        const ready = plans.filter(function (p) { return p.status === 'ready'; });
        pendingPush = ready.length ? { channel: channel, plans: ready } : null;
        const counts = countBy(plans);
        el('cmResultConfirm').classList.toggle('d-none', !ready.length);
        el('cmResultConfirm').disabled = false;
        el('cmResultConfirm').textContent = 'Confirm: push ' + ready.length + ' SKU(s) to ' + cfg.label;
        showModal(
            'Push to ' + cfg.label + ' — dry run',
            'Nothing has been sent yet. Ready ' + (counts.ready || 0) + ' &middot; Unchanged ' + (counts.unchanged || 0)
                + ' &middot; Errors ' + (counts.error || 0) + ' &middot; Not supported/skipped ' + ((counts.unsupported || 0) + (counts.skipped || 0)),
            pushTable(plans),
            skippedParents ? skippedParents + ' parent row(s) were ignored.' : ''
        );
    }

    function pushTable(plans) {
        const rowsHtml = plans.map(function (p) {
            const target = p.target_id
                ? escapeHtml(p.target_path || '') + ' <span class="text-muted">(ID ' + escapeHtml(p.target_id) + ')</span>'
                : '<span class="text-muted">—</span>';
            return `<tr>
                <td class="fw-semibold">${escapeHtml(p.sku)}</td>
                <td>${escapeHtml(p.saved_category || '')}</td>
                <td>${target}</td>
                <td>${chip(p.status)}</td>
                <td>${escapeHtml(p.message || '')}${accountsHtml(p.accounts)}</td>
            </tr>`;
        }).join('');
        return `<table class="table table-sm cm-result-table"><thead><tr><th>SKU</th><th>Saved category</th><th>Target</th><th>Result</th><th>Details</th></tr></thead><tbody>${rowsHtml}</tbody></table>`;
    }

    async function confirmPush() {
        if (!pendingPush || busy) return;
        const channel = pendingPush.channel;
        const cfg = CHANNELS[channel];
        const plans = pendingPush.plans;
        if (!confirm('Send ' + plans.length + ' SKU(s) to LIVE ' + cfg.label + ' listings now?')) return;

        busy = true;
        pendingPush = null;
        el('cmResultConfirm').disabled = true;
        setToolbarDisabled(true);
        const results = [];
        try {
            for (let i = 0; i < plans.length; i += CHUNK) {
                setHint('Pushing to ' + cfg.label + '… ' + Math.min(i + CHUNK, plans.length) + '/' + plans.length);
                const part = plans.slice(i, i + CHUNK).map(function (p) { return { sku: p.sku, target_id: p.target_id }; });
                try {
                    const body = await postJson(pushUrl, { channel: channel, dry_run: false, items: part });
                    (body.results || []).forEach(function (r) { results.push(r); });
                } catch (err) {
                    part.forEach(function (p) { results.push({ sku: p.sku, status: 'error', message: err.message, accounts: [] }); });
                }
            }
        } finally {
            busy = false;
            setToolbarDisabled(false);
            setHint('');
        }

        const counts = countBy(results);
        el('cmResultConfirm').classList.add('d-none');
        el('cmResultTitle').textContent = 'Push to ' + cfg.label + ' — result';
        el('cmResultSummary').innerHTML = 'Pushed ' + (counts.pushed || 0) + ' &middot; Unchanged ' + (counts.unchanged || 0) + ' &middot; Errors ' + (counts.error || 0);
        el('cmResultBody').innerHTML = pushTable(results);
    }

    document.querySelectorAll('.cm-pull-btn, .cm-push-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const ids = Array.from(selectedIds);
            if (!ids.length) {
                alert('Tick at least one row first.');
                return;
            }
            runAction(btn.classList.contains('cm-pull-btn') ? 'pull' : 'push', btn.dataset.channel, ids);
        });
    });
    el('cmResultConfirm').addEventListener('click', confirmPush);

    // ---- Filters ---------------------------------------------------------

    let searchTimer = null;
    function onSearchInput() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 200);
    }
    el('cmParentSearch').addEventListener('input', onSearchInput);
    el('cmSkuSearch').addEventListener('input', onSearchInput);
    el('cmCategorySearch').addEventListener('input', onSearchInput);
    el('cmRowType').addEventListener('change', applyFilters);
    el('cmCategoryScope').addEventListener('change', applyFilters);
    el('cmClear').addEventListener('click', function () {
        el('cmParentSearch').value = '';
        el('cmSkuSearch').value = '';
        el('cmCategorySearch').value = '';
        el('cmRowType').value = 'all';
        el('cmCategoryScope').value = 'all';
        applyFilters();
    });
    paintClearBadge();
});
</script>
@endsection
