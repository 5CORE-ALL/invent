@extends('layouts.vertical', ['title' => 'Technical Specifications', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
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

            function toast(message, type) {
                const wrap = document.getElementById('toastContainer');
                if (!wrap) return;
                const el = document.createElement('div');
                el.className = 'alert alert-' + (type === 'error' ? 'danger' : 'success') + ' alert-dismissible fade show shadow-sm py-2 px-3 mb-2';
                el.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                wrap.appendChild(el);
                setTimeout(() => el.remove(), 3200);
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

            async function saveSpecs(sku, text) {
                const res = await fetch('/technical-specifications/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        sku: sku,
                        technical_specifications: text,
                        specifications_text: text,
                    }),
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
                    tRow.update({
                        technical_specifications: text,
                        specifications_text: text,
                        description_v2_specifications: specs,
                    });
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

            function initTable(rows) {
                allRows = rows;
                if (table) {
                    table.replaceData(rows);
                    applyStatusFilter();
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

                table.on('dataFiltered', function () {
                    updateRowCount(table.getDataCount('active'));
                    updateSelectedCount();
                });
                table.on('rowSelectionChanged', function () {
                    updateSelectedCount();
                });
                updateRowCount(rows.length);
                updateSelectedCount();
            }

            async function loadData() {
                updateRowCount(0);
                try {
                    const res = await fetch('/technical-specifications-data', { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    if (!res.ok || json.status === 500) {
                        throw new Error(json.message || 'Failed to load data');
                    }
                    initTable(json.data || []);
                    applyStatusFilter();
                } catch (e) {
                    toast(e.message || 'Failed to load Technical Specifications data', 'error');
                    initTable([]);
                }
            }

            function exportExcel() {
                if (!table) return;
                const rows = table.getData('active').map(r => ({
                    SKU: r.SKU || '',
                    Parent: r.Parent || '',
                    title150: r.title150 || '',
                    technical_specifications: specsText(r),
                    description_1500: r.description_1500 || '',
                    description_1000: r.description_1000 || '',
                    description_800: r.description_800 || '',
                    description_600: r.description_600 || '',
                }));
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
