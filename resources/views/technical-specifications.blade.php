@extends('layouts.vertical', ['title' => 'Technical Specifications', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    @include('partials.parent-row-highlight')
    <style>
        .card.ts-master-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .card.ts-master-card .card-body { padding: 1.25rem 1.5rem; }
        .ts-master-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; }
        .ts-master-toolbar .btn { padding:.3rem .6rem; font-size:.8rem; }
        #technical-specs-table { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        #technical-specs-table .tabulator-header { background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color:#fff; }
        #technical-specs-table .tabulator-col { background:transparent !important; border-right:1px solid rgba(255,255,255,.15); }
        #technical-specs-table .tabulator-col-title { color:#fff; font-size:11px; font-weight:600; text-transform:uppercase; }
        #technical-specs-table .tabulator-header-filter input {
            background:rgba(255,255,255,.95); border:none; border-radius:4px; color:#333;
            padding:4px 6px; width:100%; font-size:10px;
        }
        #technical-specs-table .tabulator-row:nth-child(even) { background:#f8fafc; }
        #technical-specs-table .tabulator-row:hover { background:#e8f0fe !important; }
        #technical-specs-table .tabulator-cell { font-size:11px; color:#475569; }
        .preview-cell { max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; cursor:help; }
        .toast-container { position:fixed; top:20px; right:20px; z-index:9999; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        #tsCharCount { font-variant-numeric: tabular-nums; }
        #tsLineCount { font-variant-numeric: tabular-nums; }
        #ts-column-dropdown.show {
            min-width: min(92vw, 720px);
            max-width: min(96vw, 780px);
            max-height: 70vh;
            overflow-y: auto;
            padding: 0.4rem 0.5rem 0.55rem;
        }
        #ts-column-dropdown .col-vis-groups {
            display: grid;
            grid-template-columns: repeat(3, minmax(160px, 1fr));
            gap: 8px;
            margin: 0;
        }
        #ts-column-dropdown .col-vis-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 6px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
        }
        #ts-column-dropdown .col-vis-group-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            margin: 0 0 6px;
            padding: 2px 4px;
            border-bottom: 1px solid #dee2e6;
            user-select: none;
            cursor: pointer;
        }
        #ts-column-dropdown .col-vis-group-list {
            flex: 1;
            min-height: 60px;
            max-height: 320px;
            overflow-y: auto;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        #ts-column-dropdown .col-vis-item { list-style: none; margin: 0; padding: 0; }
        #ts-column-dropdown .col-vis-item > label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 3px 5px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
            font-size: 0.8rem;
            user-select: none;
        }
        #ts-column-dropdown input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            width: 14px;
            height: 14px;
            accent-color: #0d6efd;
            cursor: pointer;
        }
        #ts-column-dropdown .col-vis-item > label:hover {
            background: rgba(0, 0, 0, 0.04);
            border-radius: 3px;
        }
        @media (max-width: 768px) {
            #ts-column-dropdown .col-vis-groups { grid-template-columns: 1fr; }
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Technical Specifications',
        'sub_title' => 'Spec key/value pairs by SKU (same product set as Description Master)',
    ])

    <div class="toast-container" id="toastContainer"></div>

    <div class="row">
        <div class="col-12">
            <div class="card ts-master-card">
                <div class="card-body">
                    <div class="mb-3 ts-master-toolbar">
                        @include('partials.parent-playback-controls')
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-outline-primary" type="button"
                                id="tsColumnVisibility" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                aria-expanded="false" title="Columns">
                                <i class="fas fa-columns"></i>
                            </button>
                            <div class="dropdown-menu" id="ts-column-dropdown" aria-labelledby="tsColumnVisibility"></div>
                        </div>
                        <button type="button" id="exportBtn" class="btn btn-primary btn-sm">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button type="button" id="importBtn" class="btn btn-info btn-sm">
                            <i class="fas fa-upload"></i> Import
                        </button>
                        <select id="statusFilter" class="form-select form-select-sm" style="width:auto; min-width:200px;">
                            <option value="all">All rows</option>
                            <option value="has">Has Specifications</option>
                            <option value="missing">Missing Specifications</option>
                        </select>
                        <span class="text-muted small" id="rowCountBadge">0 products</span>
                        <span class="text-muted small" id="selectedCountBadge">0 selected</span>
                        <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
                    </div>

                    <div id="technical-specs-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editSpecsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="editModalTitle"><i class="fas fa-edit me-2"></i>Edit Technical Specifications</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalSku">
                    <div id="bulkEditBanner" class="alert alert-info py-2 px-3 mb-3" style="display:none;">
                        <i class="fas fa-layer-group me-1"></i>
                        <strong>Bulk Edit:</strong> Saving will update
                        <strong id="bulkEditCount">0</strong> selected SKU(s).
                    </div>
                    <div class="mb-2"><strong>SKU:</strong> <span id="modalSkuLabel"></span></div>
                    <div class="mb-3"><strong>Product:</strong> <span id="modalProductLabel"></span></div>
                    <div id="bulkSkuListWrap" class="mb-3" style="display:none;">
                        <div class="small text-muted mb-1">Applying to SKUs:</div>
                        <div id="bulkSkuList" class="small border rounded bg-light p-2" style="max-height:120px; overflow:auto;"></div>
                    </div>
                    <label for="modalSpecs" class="form-label">Technical Specifications</label>
                    <textarea id="modalSpecs" class="form-control font-monospace" rows="12" placeholder="One per line:&#10;Material: Steel&#10;Weight: 2.5 kg&#10;Color: Black"></textarea>
                    <div class="d-flex justify-content-between mt-1">
                        <span class="small text-muted">Format: <code>Key: Value</code> (or <code>Key | Value</code>)</span>
                        <span class="small text-muted"><span id="tsLineCount">0</span> lines · <span id="tsCharCount">0</span> chars</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="modalSaveBtn">
                        <i class="fas fa-save"></i> <span id="modalSaveBtnLabel">Save</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewSpecsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="viewSpecsTitle"><i class="fas fa-eye me-2"></i>Technical Specifications</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewSpecsSubtitle" class="small text-muted mb-2"></div>
                    <div id="viewSpecsContent" class="p-3 bg-light border rounded" style="white-space:pre-wrap; word-break:break-word; min-height:4rem; font-family:ui-monospace,Consolas,monospace; font-size:12px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="viewCopyBtn"><i class="fas fa-copy me-1"></i> Copy</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        (function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            let table = null;
            let allRows = [];
            let editModal = null;
            let viewModal = null;
            let bulkEditSkus = [];
            let navParent = null;
            let parentPlayback = null;
            let ebayAspectNames = [];
            const aspectFields = {};
            const TS_COL_VIS_KEY = 'technical_specs_col_vis_v1';
            const TS_COL_GROUPS = [
                { key: 'ebay', label: 'eBay' },
                { key: 'amz', label: 'Amz' },
                { key: 'temu', label: 'Temu' },
            ];

            function toast(message, type) {
                const wrap = document.getElementById('toastContainer');
                if (!wrap) return;
                const el = document.createElement('div');
                el.className = 'alert alert-' + (type === 'error' ? 'danger' : 'success') + ' alert-dismissible fade show shadow-sm py-2 px-3 mb-2';
                el.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                wrap.appendChild(el);
                setTimeout(() => el.remove(), 3200);
            }

            function categoryColumn(title, field) {
                return {
                    title: title,
                    field: field,
                    width: 180,
                    headerFilter: 'input',
                    headerFilterPlaceholder: 'Filter',
                    formatter: function (cell) {
                        const v = String(cell.getValue() || '').trim();
                        return v ? esc(v) : '<span class="text-muted">—</span>';
                    },
                };
            }

            function esc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function specsText(row) {
                return String(row.technical_specifications || row.specifications_text || '').trim();
            }

            function preview100(row) {
                const t = specsText(row);
                if (!t) return '—';
                return t.length > 100 ? t.slice(0, 100) + '…' : t;
            }

            function updateRowCount(n) {
                const badge = document.getElementById('rowCountBadge');
                if (badge) badge.textContent = n + ' product' + (n === 1 ? '' : 's');
            }

            function updateSelectedCount() {
                const badge = document.getElementById('selectedCountBadge');
                if (!badge || !table) return;
                badge.textContent = table.getSelectedData().length + ' selected';
            }

            function applyStatusFilter() {
                if (!table) return;
                const mode = document.getElementById('statusFilter')?.value || 'all';
                table.setFilter(function (data) {
                    if (navParent != null && String(data.Parent || '') !== String(navParent)) return false;
                    const text = specsText(data);
                    if (mode === 'has') return text !== '';
                    if (mode === 'missing') return text === '';
                    return true;
                });
                updateRowCount(table.getDataCount('active'));
                updateSelectedCount();
            }

            function aspectKey(name) {
                if (aspectFields[name]) return aspectFields[name];
                let base = 'ebay_aspect_' + String(name).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
                if (!base || base === 'ebay_aspect_') base = 'ebay_aspect';
                let key = base;
                const taken = Object.values(aspectFields);
                let n = 2;
                while (taken.indexOf(key) !== -1) {
                    key = base + '_' + n;
                    n++;
                }
                aspectFields[name] = key;
                return key;
            }

            function rowRequiresAspect(row, name) {
                return (row.ebay_required || []).some(function (n) {
                    return String(n).toLowerCase() === String(name).toLowerCase();
                });
            }

            function stampAspectFields(row) {
                const values = row.ebay_values || {};
                ebayAspectNames.forEach(function (name) {
                    row[aspectKey(name)] = rowRequiresAspect(row, name) ? (values[name] || '') : '';
                });
            }

            function ebayAspectColumns() {
                return ebayAspectNames.map(function (name) {
                    return {
                        title: name,
                        field: aspectKey(name),
                        width: 150,
                        minWidth: 120,
                        headerTooltip: 'Required by eBay for this category',
                        headerFilter: 'input',
                        headerFilterPlaceholder: 'Filter',
                        editor: 'input',
                        editable: function (cell) {
                            return rowRequiresAspect(cell.getRow().getData(), name);
                        },
                        formatter: function (cell) {
                            const d = cell.getRow().getData();
                            if (!rowRequiresAspect(d, name)) return '<span class="text-muted">—</span>';
                            const v = String(cell.getValue() || '').trim();
                            if (!v) return '<span class="text-warning">Required</span>';
                            return esc(v);
                        },
                        cellEdited: async function (cell) {
                            const d = cell.getRow().getData();
                            const value = String(cell.getValue() || '').trim();
                            const specs = (d.description_v2_specifications || []).map(function (s) {
                                return { key: s.key, value: s.value };
                            });
                            const idx = specs.findIndex(function (s) {
                                return String(s.key || '').trim().toLowerCase() === name.toLowerCase();
                            });
                            if (value === '') {
                                if (idx >= 0) specs.splice(idx, 1);
                            } else if (idx >= 0) {
                                specs[idx] = { key: name, value: value };
                            } else {
                                specs.push({ key: name, value: value });
                            }
                            try {
                                const json = await saveSpecs(d.SKU, '', specs);
                                applySaveToRow(d.SKU, json, json.technical_specifications || '');
                                toast('Saved ' + name + ' for ' + d.SKU, 'success');
                            } catch (e) {
                                toast(e.message || 'Save failed', 'error');
                                cell.restoreOldValue();
                            }
                        },
                    };
                });
            }

            async function saveSpecs(sku, text, specs) {
                const body = {
                    sku: sku,
                    technical_specifications: text,
                    specifications_text: text,
                };
                if (Array.isArray(specs)) body.description_v2_specifications = specs;
                const res = await fetch('/technical-specifications/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(body),
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    throw new Error(json.message || 'Save failed');
                }
                return json;
            }

            function applySaveToRow(sku, json, fallbackText) {
                const text = json.technical_specifications != null ? json.technical_specifications : fallbackText;
                const specs = json.description_v2_specifications || [];
                const tRow = table?.getRows().find(r => r.getData().SKU === sku);
                if (tRow) {
                    const data = tRow.getData();
                    const byKey = {};
                    specs.forEach(function (s) {
                        const k = String(s.key || '').trim().toLowerCase();
                        if (k) byKey[k] = String(s.value || '');
                    });
                    const patch = {
                        technical_specifications: text,
                        specifications_text: text,
                        description_v2_specifications: specs,
                        ebay_values: Object.assign({}, data.ebay_values || {}),
                    };
                    ebayAspectNames.forEach(function (name) {
                        const value = rowRequiresAspect(data, name) ? (byKey[String(name).toLowerCase()] || '') : '';
                        patch[aspectKey(name)] = value;
                        if (rowRequiresAspect(data, name)) patch.ebay_values[name] = value;
                    });
                    tRow.update(patch);
                }
            }

            function resolveBulkEditSkus(clickedRow) {
                const clickedSku = String(clickedRow.SKU || '').trim();
                const selected = (table ? table.getSelectedData() : [])
                    .map(r => String(r.SKU || '').trim())
                    .filter(Boolean);
                const set = new Set(selected);
                if (clickedSku) set.add(clickedSku);
                if (selected.length === 0) return clickedSku ? [clickedSku] : [];
                return Array.from(set);
            }

            function openEdit(rowData) {
                if (table) {
                    const row = table.getRows().find(r => r.getData().SKU === rowData.SKU);
                    if (row && !row.isSelected()) row.select();
                }
                bulkEditSkus = resolveBulkEditSkus(rowData);
                const isBulk = bulkEditSkus.length > 1;

                document.getElementById('modalSku').value = rowData.SKU || '';
                document.getElementById('modalSkuLabel').textContent = isBulk
                    ? (bulkEditSkus.length + ' SKUs selected')
                    : (rowData.SKU || '');
                document.getElementById('modalProductLabel').textContent = isBulk
                    ? 'Bulk edit — same specs will be saved to every selected SKU'
                    : (rowData.Parent || rowData.title150 || rowData.SKU || '');

                const banner = document.getElementById('bulkEditBanner');
                const listWrap = document.getElementById('bulkSkuListWrap');
                const listEl = document.getElementById('bulkSkuList');
                const titleEl = document.getElementById('editModalTitle');
                const saveLabel = document.getElementById('modalSaveBtnLabel');
                if (isBulk) {
                    banner.style.display = '';
                    document.getElementById('bulkEditCount').textContent = String(bulkEditSkus.length);
                    listWrap.style.display = '';
                    listEl.textContent = bulkEditSkus.join(', ');
                    titleEl.innerHTML = '<i class="fas fa-layer-group me-2"></i>Bulk Edit Technical Specifications';
                    saveLabel.textContent = 'Save to ' + bulkEditSkus.length + ' SKUs';
                } else {
                    banner.style.display = 'none';
                    listWrap.style.display = 'none';
                    listEl.textContent = '';
                    titleEl.innerHTML = '<i class="fas fa-edit me-2"></i>Edit Technical Specifications';
                    saveLabel.textContent = 'Save';
                }

                document.getElementById('modalSpecs').value = specsText(rowData);
                updateCounts();
                editModal.show();
            }

            function openView(rowData) {
                const text = specsText(rowData);
                document.getElementById('viewSpecsSubtitle').textContent =
                    (rowData.SKU || '') + ' — ' + (rowData.Parent || rowData.title150 || '');
                document.getElementById('viewSpecsContent').textContent = text || '(empty)';
                viewModal.show();
            }

            function updateCounts() {
                const v = document.getElementById('modalSpecs').value || '';
                const lines = v.split(/\r\n|\r|\n/).filter(l => l.trim() !== '').length;
                document.getElementById('tsCharCount').textContent = String(v.length);
                document.getElementById('tsLineCount').textContent = String(lines);
            }

            function tsColumnGroup(field) {
                if (field === 'category_ebay' || String(field).indexOf('ebay_aspect_') === 0) return 'ebay';
                if (field === 'category_amz') return 'amz';
                if (field === 'category_temu') return 'temu';
                return '';
            }

            function loadTsColumnVisibility() {
                try {
                    const parsed = JSON.parse(localStorage.getItem(TS_COL_VIS_KEY) || '{}');
                    return parsed && typeof parsed === 'object' ? parsed : {};
                } catch (e) {
                    return {};
                }
            }

            function saveTsColumnVisibility() {
                if (!table) return;
                const visibility = loadTsColumnVisibility();
                table.getColumns().forEach(function (col) {
                    const field = col.getDefinition().field;
                    if (!tsColumnGroup(field)) return;
                    visibility[field] = col.isVisible();
                });
                try { localStorage.setItem(TS_COL_VIS_KEY, JSON.stringify(visibility)); } catch (e) {}
            }

            function applyTsColumnVisibility() {
                if (!table) return;
                const saved = loadTsColumnVisibility();
                table.getColumns().forEach(function (col) {
                    const field = col.getDefinition().field;
                    if (!tsColumnGroup(field) || !Object.prototype.hasOwnProperty.call(saved, field)) return;
                    if (saved[field] === false) col.hide();
                    else col.show();
                });
            }

            function syncTsGroupHeader(groupEl) {
                const headerCb = groupEl.querySelector('.col-vis-group-toggle');
                const itemCbs = groupEl.querySelectorAll('.col-vis-field-toggle');
                if (!headerCb) return;
                if (!itemCbs.length) {
                    headerCb.checked = false;
                    headerCb.indeterminate = false;
                    headerCb.disabled = true;
                    return;
                }
                headerCb.disabled = false;
                let checked = 0;
                itemCbs.forEach(function (cb) { if (cb.checked) checked++; });
                headerCb.checked = checked === itemCbs.length;
                headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
            }

            function buildTsColumnPicker() {
                const menu = document.getElementById('ts-column-dropdown');
                if (!menu || !table) return;
                applyTsColumnVisibility();
                menu.innerHTML = '';
                const wrap = document.createElement('div');
                wrap.className = 'col-vis-groups';
                const lists = {};
                const groups = {};
                TS_COL_GROUPS.forEach(function (spec) {
                    const group = document.createElement('div');
                    group.className = 'col-vis-group';
                    group.dataset.group = spec.key;
                    const title = document.createElement('label');
                    title.className = 'col-vis-group-title';
                    const groupCb = document.createElement('input');
                    groupCb.type = 'checkbox';
                    groupCb.className = 'col-vis-group-toggle';
                    groupCb.title = 'Select / deselect all in ' + spec.label;
                    title.appendChild(groupCb);
                    title.appendChild(document.createTextNode(spec.label));
                    const list = document.createElement('ul');
                    list.className = 'col-vis-group-list';
                    group.appendChild(title);
                    group.appendChild(list);
                    wrap.appendChild(group);
                    lists[spec.key] = list;
                    groups[spec.key] = group;
                    groupCb.addEventListener('change', function () {
                        list.querySelectorAll('.col-vis-field-toggle').forEach(function (cb) {
                            cb.checked = groupCb.checked;
                            const col = table.getColumn(cb.value);
                            if (col) groupCb.checked ? col.show() : col.hide();
                        });
                        groupCb.indeterminate = false;
                        saveTsColumnVisibility();
                    });
                });
                table.getColumns().forEach(function (col) {
                    const def = col.getDefinition();
                    const field = def.field;
                    const groupKey = tsColumnGroup(field);
                    if (!groupKey || !lists[groupKey]) return;
                    const titleText = String(def.title || field).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                    const li = document.createElement('li');
                    li.className = 'col-vis-item';
                    const label = document.createElement('label');
                    label.title = titleText;
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.className = 'col-vis-field-toggle';
                    cb.value = field;
                    cb.checked = col.isVisible();
                    label.appendChild(cb);
                    label.appendChild(document.createTextNode(titleText));
                    li.appendChild(label);
                    lists[groupKey].appendChild(li);
                    cb.addEventListener('change', function () {
                        const column = table.getColumn(field);
                        if (column) cb.checked ? column.show() : column.hide();
                        syncTsGroupHeader(groups[groupKey]);
                        saveTsColumnVisibility();
                    });
                });
                TS_COL_GROUPS.forEach(function (spec) {
                    if (!lists[spec.key].children.length) {
                        const empty = document.createElement('li');
                        empty.className = 'text-muted small px-1';
                        empty.textContent = 'No columns';
                        lists[spec.key].appendChild(empty);
                    }
                    syncTsGroupHeader(groups[spec.key]);
                });
                menu.appendChild(wrap);
            }

            function initTable(rows, aspectNames) {
                if (Array.isArray(aspectNames)) ebayAspectNames = aspectNames;
                rows.forEach(stampAspectFields);
                allRows = rows;
                if (table) {
                    table.replaceData(rows);
                    applyStatusFilter();
                    buildTsColumnPicker();
                    return;
                }

                table = new Tabulator('#technical-specs-table', {
                    data: rows,
                    layout: 'fitColumns',
                    height: '640px',
                    pagination: true,
                    paginationSize: 50,
                    paginationSizeSelector: [25, 50, 100, 200],
                    placeholder: 'No products found',
                    selectableRows: true,
                    rowFormatter: window.pmParentRowFormatter,
                    columns: [
                        {
                            formatter: 'rowSelection',
                            titleFormatter: 'rowSelection',
                            titleFormatterParams: { rowRange: 'active' },
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            headerSort: false,
                            width: 44,
                            minWidth: 44,
                            resizable: false,
                            frozen: true,
                            cellClick: function (e, cell) {
                                e.stopPropagation();
                                cell.getRow().toggleSelect();
                            },
                        },
                        categoryColumn('Category eBay', 'category_ebay'),
                        categoryColumn('Category Amz', 'category_amz'),
                        categoryColumn('Category Temu', 'category_temu'),
                        {
                            title: 'Parent',
                            field: 'Parent',
                            width: 200,
                            headerFilter: 'input',
                            headerFilterPlaceholder: 'Filter parent',
                            formatter: function (cell) {
                                const d = cell.getRow().getData();
                                return esc(d.Parent || d.title150 || d.SKU || '');
                            },
                        },
                        {
                            title: 'SKU',
                            field: 'SKU',
                            width: 180,
                            headerFilter: 'input',
                            headerFilterPlaceholder: 'Filter SKU',
                        },
                        {
                            title: 'Preview',
                            field: 'technical_specifications',
                            minWidth: 220,
                            headerFilter: 'input',
                            headerFilterPlaceholder: 'Filter preview',
                            formatter: function (cell) {
                                const d = cell.getRow().getData();
                                const full = specsText(d);
                                return '<span class="preview-cell" title="' + esc(full) + '">' + esc(preview100(d)) + '</span>';
                            },
                        },
                        {
                            title: 'Technical Specifications',
                            field: 'technical_specifications',
                            minWidth: 320,
                            editor: 'textarea',
                            formatter: function (cell) {
                                const v = String(cell.getValue() || '').trim();
                                if (!v) return '<span class="text-muted">—</span>';
                                return esc(v.length > 160 ? v.slice(0, 160) + '…' : v);
                            },
                            cellEdited: async function (cell) {
                                const d = cell.getRow().getData();
                                const sku = d.SKU;
                                const value = String(cell.getValue() || '');
                                try {
                                    const json = await saveSpecs(sku, value);
                                    applySaveToRow(sku, json, value);
                                    toast('Saved specs for ' + sku, 'success');
                                    applyStatusFilter();
                                } catch (e) {
                                    toast(e.message || 'Save failed', 'error');
                                    cell.restoreOldValue();
                                }
                            },
                        },
                        ...ebayAspectColumns(),
                        {
                            title: 'Action',
                            field: '_action',
                            width: 150,
                            hozAlign: 'center',
                            headerSort: false,
                            formatter: function () {
                                return '<div class="d-flex gap-1 justify-content-center">' +
                                    '<button type="button" class="btn btn-sm btn-info text-white ts-view-btn"><i class="fas fa-eye"></i></button>' +
                                    '<button type="button" class="btn btn-sm btn-primary ts-edit-btn"><i class="fas fa-edit"></i></button>' +
                                    '</div>';
                            },
                            cellClick: function (e, cell) {
                                const d = cell.getRow().getData();
                                if (e.target.closest('.ts-view-btn')) openView(d);
                                if (e.target.closest('.ts-edit-btn')) openEdit(d);
                            },
                        },
                    ],
                });

                table.on('tableBuilt', function () {
                    buildTsColumnPicker();
                });
                table.on('dataFiltered', function () {
                    updateRowCount(table.getDataCount('active'));
                    updateSelectedCount();
                });
                table.on('rowSelectionChanged', function () {
                    updateSelectedCount();
                });
                updateRowCount(rows.length);
                updateSelectedCount();
                buildTsColumnPicker();
            }

            async function loadData() {
                updateRowCount(0);
                try {
                    const res = await fetch('/technical-specifications-data', { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    if (!res.ok || json.status === 500) {
                        throw new Error(json.message || 'Failed to load data');
                    }
                    initTable(json.data || [], json.ebay_aspects || []);
                    applyStatusFilter();
                } catch (e) {
                    toast(e.message || 'Failed to load Technical Specifications data', 'error');
                    initTable([]);
                }
            }

            function exportExcel() {
                if (!table) return;
                const rows = table.getData('active').map(function (r) {
                    const row = {
                        SKU: r.SKU || '',
                        'Category eBay': r.category_ebay || '',
                        'Category Amz': r.category_amz || '',
                        'Category Temu': r.category_temu || '',
                        Parent: r.Parent || '',
                        title150: r.title150 || '',
                        technical_specifications: specsText(r),
                    };
                    ebayAspectNames.forEach(function (name) {
                        row[name] = rowRequiresAspect(r, name) ? (r[aspectKey(name)] || '') : '';
                    });
                    row.description_1500 = r.description_1500 || '';
                    row.description_1000 = r.description_1000 || '';
                    row.description_800 = r.description_800 || '';
                    row.description_600 = r.description_600 || '';
                    return row;
                });
                const ws = XLSX.utils.json_to_sheet(rows);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Technical Specs');
                XLSX.writeFile(wb, 'technical_specifications_export.xlsx');
            }

            async function importExcel(file) {
                const buf = await file.arrayBuffer();
                const wb = XLSX.read(buf, { type: 'array' });
                const sheet = wb.Sheets[wb.SheetNames[0]];
                const rows = XLSX.utils.sheet_to_json(sheet, { defval: '' });
                let ok = 0, fail = 0;
                for (const row of rows) {
                    const sku = String(row.SKU || row.sku || '').trim();
                    if (!sku) continue;
                    const text = String(
                        row.technical_specifications
                        || row.Technical_Specifications
                        || row['Technical Specifications']
                        || row.specifications_text
                        || ''
                    );
                    try {
                        const json = await saveSpecs(sku, text);
                        ok++;
                        applySaveToRow(sku, json, text);
                    } catch (_) {
                        fail++;
                    }
                }
                toast('Import done: ' + ok + ' saved' + (fail ? ', ' + fail + ' failed' : ''), fail ? 'error' : 'success');
                applyStatusFilter();
            }

            document.addEventListener('DOMContentLoaded', function () {
                editModal = new bootstrap.Modal(document.getElementById('editSpecsModal'));
                viewModal = new bootstrap.Modal(document.getElementById('viewSpecsModal'));

                parentPlayback = window.ParentPlayback.create({
                    getAllData: function () { return allRows; },
                    applyFilter: function (parent) {
                        navParent = parent;
                        applyStatusFilter();
                    },
                });

                document.getElementById('exportBtn').addEventListener('click', exportExcel);
                document.getElementById('importBtn').addEventListener('click', () => document.getElementById('importFile').click());
                document.getElementById('importFile').addEventListener('change', function (e) {
                    const file = e.target.files?.[0];
                    if (file) importExcel(file);
                    e.target.value = '';
                });
                document.getElementById('statusFilter').addEventListener('change', applyStatusFilter);
                document.getElementById('modalSpecs').addEventListener('input', updateCounts);

                document.getElementById('modalSaveBtn').addEventListener('click', async function () {
                    const text = document.getElementById('modalSpecs').value || '';
                    const skus = bulkEditSkus.length
                        ? bulkEditSkus.slice()
                        : [document.getElementById('modalSku').value].filter(Boolean);
                    if (!skus.length) {
                        toast('No SKUs to save', 'error');
                        return;
                    }
                    const btn = this;
                    btn.disabled = true;
                    let ok = 0, fail = 0;
                    try {
                        for (const sku of skus) {
                            try {
                                const json = await saveSpecs(sku, text);
                                applySaveToRow(sku, json, text);
                                ok++;
                            } catch (_) {
                                fail++;
                            }
                        }
                        if (skus.length === 1) {
                            toast(fail ? 'Save failed' : ('Saved specs for ' + skus[0]), fail ? 'error' : 'success');
                        } else {
                            toast('Bulk save: ' + ok + ' updated' + (fail ? ', ' + fail + ' failed' : ''), fail ? 'error' : 'success');
                        }
                        if (ok > 0) {
                            editModal.hide();
                            applyStatusFilter();
                            updateSelectedCount();
                        }
                    } finally {
                        btn.disabled = false;
                    }
                });

                document.getElementById('viewCopyBtn').addEventListener('click', async function () {
                    const text = document.getElementById('viewSpecsContent').textContent || '';
                    try {
                        await navigator.clipboard.writeText(text === '(empty)' ? '' : text);
                        toast('Copied to clipboard', 'success');
                    } catch (_) {
                        toast('Copy failed', 'error');
                    }
                });

                loadData();
            });
        })();
    </script>
@endsection
