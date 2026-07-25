@extends('layouts.vertical', ['title' => 'About Brand', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .card.ab-master-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .card.ab-master-card .card-body { padding: 1.25rem 1.5rem; }
        .ab-master-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; }
        .ab-master-toolbar .btn { padding:.3rem .6rem; font-size:.8rem; }
        #about-brand-table { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        #about-brand-table .tabulator-header { background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color:#fff; }
        #about-brand-table .tabulator-col { background:transparent !important; border-right:1px solid rgba(255,255,255,.15); }
        #about-brand-table .tabulator-col-title { color:#fff; font-size:11px; font-weight:600; text-transform:uppercase; }
        #about-brand-table .tabulator-header-filter input {
            background:rgba(255,255,255,.95); border:none; border-radius:4px; color:#333;
            padding:4px 6px; width:100%; font-size:10px;
        }
        #about-brand-table .tabulator-row:nth-child(even) { background:#f8fafc; }
        #about-brand-table .tabulator-row:hover { background:#e8f0fe !important; }
        #about-brand-table .tabulator-cell { font-size:11px; color:#475569; }
        .preview-pm-wrap { display:flex; align-items:center; justify-content:center; width:100%; }
        .preview-magnify-btn {
            flex-shrink:0; width:30px; height:30px; border:none; border-radius:6px;
            background:#0d6efd; color:#fff; font-size:13px;
            display:inline-flex; align-items:center; justify-content:center; cursor:pointer;
            box-shadow:0 1px 3px rgba(13,110,253,.4);
        }
        .preview-magnify-btn:hover { background:#0b5ed7; color:#fff; }
        .toast-container { position:fixed; top:20px; right:20px; z-index:9999; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        #abCharCount { font-variant-numeric: tabular-nums; }
        #abCharCount.at-limit { color:#dc2626; font-weight:600; }
        #compositeHtmlPreview {
            border:1px solid #e2e8f0; border-radius:10px; padding:1rem; background:#fff;
            max-height:min(70vh,640px); overflow:auto;
        }
        #compositeSectionBadges .badge { font-size:10px; margin-right:4px; margin-bottom:4px; }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'About Brand',
        'sub_title' => 'Brand story text by SKU (same product set as Description Master)',
    ])

    <div class="toast-container" id="toastContainer"></div>

    <div class="row">
        <div class="col-12">
            <div class="card ab-master-card">
                <div class="card-body">
                    <div class="mb-3 ab-master-toolbar">
                        @include('partials.parent-playback-controls')
                        <button type="button" id="exportBtn" class="btn btn-primary btn-sm">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button type="button" id="importBtn" class="btn btn-info btn-sm">
                            <i class="fas fa-upload"></i> Import
                        </button>
                        <select id="statusFilter" class="form-select form-select-sm" style="width:auto; min-width:170px;">
                            <option value="all">All rows</option>
                            <option value="has">Has About Brand</option>
                            <option value="missing">Missing About Brand</option>
                        </select>
                        <span class="text-muted small" id="rowCountBadge">0 products</span>
                        <span class="text-muted small" id="selectedCountBadge">0 selected</span>
                        <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
                    </div>

                    <div id="about-brand-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editBrandModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="editModalTitle"><i class="fas fa-edit me-2"></i>Edit About Brand</h5>
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
                    <label for="modalAboutBrand" class="form-label">About Brand</label>
                    <textarea id="modalAboutBrand" class="form-control" rows="10" placeholder="Enter brand story / about brand text…"></textarea>
                    <div class="d-flex justify-content-end mt-1">
                        <span class="small text-muted" id="abCharCount">0 chars</span>
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

    <div class="modal fade" id="compositePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-search me-2"></i>HTML Preview</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="compositePreviewSubtitle" class="small text-muted mb-2"></div>
                    <div id="compositeSectionBadges" class="mb-3"></div>
                    <div id="compositeHtmlPreview">
                        <div class="text-muted text-center py-4">Loading…</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="compositeCopyBtn">
                        <i class="fas fa-copy me-1"></i> Copy HTML
                    </button>
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
            let compositeModal = null;
            let lastCompositeHtml = '';
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

            function brandText(row) {
                return String(row.about_brand || row.description_v2_brand || '').trim();
            }

            function updateRowCount(n) {
                const badge = document.getElementById('rowCountBadge');
                if (badge) badge.textContent = n + ' product' + (n === 1 ? '' : 's');
            }

            function updateSelectedCount() {
                const badge = document.getElementById('selectedCountBadge');
                if (!badge || !table) return;
                const n = table.getSelectedData().length;
                badge.textContent = n + ' selected';
            }

            function applyStatusFilter() {
                if (!table) return;
                const mode = document.getElementById('statusFilter')?.value || 'all';
                table.setFilter(function (data) {
                    if (navParent != null && String(data.Parent || '') !== String(navParent)) return false;
                    const text = brandText(data);
                    if (mode === 'has') return text !== '';
                    if (mode === 'missing') return text === '';
                    return true;
                });
                updateRowCount(table.getDataCount('active'));
                updateSelectedCount();
            }

            async function saveBrand(sku, aboutBrand) {
                const res = await fetch('/about-brand/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        sku: sku,
                        about_brand: aboutBrand,
                        description_v2_brand: aboutBrand,
                    }),
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    throw new Error(json.message || 'Save failed');
                }
                return json;
            }

            function resolveBulkEditSkus(clickedRow) {
                const clickedSku = String(clickedRow.SKU || '').trim();
                const selected = (table ? table.getSelectedData() : [])
                    .map(r => String(r.SKU || '').trim())
                    .filter(Boolean);

                // Bulk = all selected rows; always include the row whose Edit was clicked.
                const set = new Set(selected);
                if (clickedSku) set.add(clickedSku);

                // If nothing was selected, edit only the clicked row.
                if (selected.length === 0) {
                    return clickedSku ? [clickedSku] : [];
                }

                return Array.from(set);
            }

            function openEdit(rowData) {
                // Ensure the clicked row is selected so it is part of the bulk set.
                if (table) {
                    const row = table.getRows().find(r => r.getData().SKU === rowData.SKU);
                    if (row && !row.isSelected()) {
                        row.select();
                    }
                }

                bulkEditSkus = resolveBulkEditSkus(rowData);
                const isBulk = bulkEditSkus.length > 1;

                document.getElementById('modalSku').value = rowData.SKU || '';
                document.getElementById('modalSkuLabel').textContent = isBulk
                    ? (bulkEditSkus.length + ' SKUs selected')
                    : (rowData.SKU || '');
                document.getElementById('modalProductLabel').textContent = isBulk
                    ? 'Bulk edit — same About Brand text will be saved to every selected SKU'
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
                    titleEl.innerHTML = '<i class="fas fa-layer-group me-2"></i>Bulk Edit About Brand';
                    saveLabel.textContent = 'Save to ' + bulkEditSkus.length + ' SKUs';
                } else {
                    banner.style.display = 'none';
                    listWrap.style.display = 'none';
                    listEl.textContent = '';
                    titleEl.innerHTML = '<i class="fas fa-edit me-2"></i>Edit About Brand';
                    saveLabel.textContent = 'Save';
                }

                const ta = document.getElementById('modalAboutBrand');
                ta.value = brandText(rowData);
                updateCharCount();
                editModal.show();
            }

            function renderSectionBadges(sections) {
                const labels = {
                    images: 'Images',
                    bullet_points: 'Bullet Points',
                    description: 'Description',
                    features: 'Features',
                    specifications: 'Specifications',
                    package_includes: 'Package Includes',
                    about_us: 'About Us',
                };
                document.getElementById('compositeSectionBadges').innerHTML = Object.keys(labels).map(key => {
                    const on = !!(sections && sections[key]);
                    return '<span class="badge ' + (on ? 'bg-success' : 'bg-secondary') + '">' +
                        (on ? '✓ ' : '– ') + labels[key] + '</span>';
                }).join('');
            }

            async function openCompositePreview(rowData) {
                const sku = rowData.SKU || '';
                document.getElementById('compositePreviewSubtitle').textContent =
                    sku + ' — ' + (rowData.Parent || rowData.title150 || '');
                document.getElementById('compositeHtmlPreview').innerHTML =
                    '<div class="text-muted text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Building HTML preview…</div>';
                document.getElementById('compositeSectionBadges').innerHTML = '';
                lastCompositeHtml = '';
                compositeModal.show();
                try {
                    const res = await fetch('/a-plus-content/preview-html?sku=' + encodeURIComponent(sku), {
                        headers: { Accept: 'application/json' },
                    });
                    const json = await res.json();
                    if (!res.ok || !json.success) throw new Error(json.message || 'Failed to load preview');
                    renderSectionBadges(json.sections || {});
                    lastCompositeHtml = json.html || '';
                    document.getElementById('compositeHtmlPreview').innerHTML = lastCompositeHtml
                        || '<div class="text-muted text-center py-4">No HTML content available for this SKU.</div>';
                } catch (e) {
                    document.getElementById('compositeHtmlPreview').innerHTML =
                        '<div class="text-danger text-center py-4">' + esc(e.message || 'Preview failed') + '</div>';
                }
            }

            function updateCharCount() {
                const n = (document.getElementById('modalAboutBrand').value || '').length;
                const el = document.getElementById('abCharCount');
                el.textContent = n + ' chars';
                el.classList.toggle('at-limit', n > 5000);
            }

            function initTable(rows) {
                allRows = rows;
                if (table) {
                    table.replaceData(rows);
                    applyStatusFilter();
                    return;
                }

                table = new Tabulator('#about-brand-table', {
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
                            titleFormatterParams: {
                                rowRange: 'active', // header checkbox applies to filtered rows
                            },
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
                            field: 'about_brand',
                            minWidth: 90,
                            width: 100,
                            hozAlign: 'center',
                            headerSort: false,
                            headerFilter: false,
                            formatter: function (cell) {
                                const d = cell.getRow().getData();
                                return '<div class="preview-pm-wrap">' +
                                    '<button type="button" class="preview-magnify-btn" title="Open HTML preview" data-sku="' + esc(d.SKU || '') + '">' +
                                    '<i class="fas fa-search"></i></button></div>';
                            },
                            cellClick: function (e, cell) {
                                if (!e.target.closest('.preview-magnify-btn')) return;
                                e.preventDefault();
                                e.stopPropagation();
                                openCompositePreview(cell.getRow().getData());
                            },
                        },
                        {
                            title: 'About Brand',
                            field: 'about_brand',
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
                                    await saveBrand(sku, value);
                                    cell.getRow().update({
                                        about_brand: value,
                                        description_v2_brand: value,
                                    });
                                    toast('Saved About Brand for ' + sku, 'success');
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
                                    '<button type="button" class="btn btn-sm btn-primary ab-edit-btn"><i class="fas fa-edit"></i></button>' +
                                    '</div>';
                            },
                            cellClick: function (e, cell) {
                                if (e.target.closest('.ab-edit-btn')) openEdit(cell.getRow().getData());
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

                const tableEl = document.getElementById('about-brand-table');
                if (tableEl && !tableEl.dataset.previewBound) {
                    tableEl.dataset.previewBound = '1';
                    tableEl.addEventListener('click', function (e) {
                        const btn = e.target.closest('.preview-magnify-btn');
                        if (!btn || !table) return;
                        const rowEl = e.target.closest('.tabulator-row');
                        if (!rowEl) return;
                        const row = table.getRow(rowEl);
                        if (row) openCompositePreview(row.getData());
                    });
                }

                updateRowCount(rows.length);
                updateSelectedCount();
            }

            async function loadData() {
                updateRowCount(0);
                try {
                    const res = await fetch('/about-brand-data', { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    if (!res.ok || json.status === 500) {
                        throw new Error(json.message || 'Failed to load data');
                    }
                    initTable(json.data || []);
                    applyStatusFilter();
                } catch (e) {
                    toast(e.message || 'Failed to load About Brand data', 'error');
                    initTable([]);
                }
            }

            function exportExcel() {
                if (!table) return;
                const rows = table.getData('active').map(r => ({
                    SKU: r.SKU || '',
                    Parent: r.Parent || '',
                    title150: r.title150 || '',
                    about_brand: brandText(r),
                    description_1500: r.description_1500 || '',
                    description_1000: r.description_1000 || '',
                    description_800: r.description_800 || '',
                    description_600: r.description_600 || '',
                }));
                const ws = XLSX.utils.json_to_sheet(rows);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'About Brand');
                XLSX.writeFile(wb, 'about_brand_export.xlsx');
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
                    const brand = String(
                        row.about_brand || row.About_Brand || row['About Brand'] || row.description_v2_brand || ''
                    );
                    try {
                        await saveBrand(sku, brand);
                        ok++;
                        const match = (table?.getData() || []).find(r => r.SKU === sku);
                        if (match && table) {
                            const tRow = table.getRows().find(r => r.getData().SKU === sku);
                            if (tRow) tRow.update({ about_brand: brand, description_v2_brand: brand });
                        }
                    } catch (_) {
                        fail++;
                    }
                }
                toast('Import done: ' + ok + ' saved' + (fail ? ', ' + fail + ' failed' : ''), fail ? 'error' : 'success');
                applyStatusFilter();
            }

            document.addEventListener('DOMContentLoaded', function () {
                editModal = new bootstrap.Modal(document.getElementById('editBrandModal'));
                compositeModal = new bootstrap.Modal(document.getElementById('compositePreviewModal'));

                parentPlayback = window.ParentPlayback.create({
                    getAllData: function () { return allRows; },
                    applyFilter: function (parent) {
                        navParent = parent;
                        applyStatusFilter();
                    },
                });

                document.getElementById('compositeCopyBtn').addEventListener('click', async function () {
                    try {
                        await navigator.clipboard.writeText(lastCompositeHtml || '');
                        toast(lastCompositeHtml ? 'Copied HTML to clipboard' : 'Nothing to copy', lastCompositeHtml ? 'success' : 'error');
                    } catch (_) {
                        toast('Copy failed', 'error');
                    }
                });

                document.getElementById('exportBtn').addEventListener('click', exportExcel);
                document.getElementById('importBtn').addEventListener('click', () => document.getElementById('importFile').click());
                document.getElementById('importFile').addEventListener('change', function (e) {
                    const file = e.target.files?.[0];
                    if (file) importExcel(file);
                    e.target.value = '';
                });
                document.getElementById('statusFilter').addEventListener('change', applyStatusFilter);
                document.getElementById('modalAboutBrand').addEventListener('input', updateCharCount);

                document.getElementById('modalSaveBtn').addEventListener('click', async function () {
                    const brand = document.getElementById('modalAboutBrand').value || '';
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
                                await saveBrand(sku, brand);
                                const tRow = table?.getRows().find(r => r.getData().SKU === sku);
                                if (tRow) tRow.update({ about_brand: brand, description_v2_brand: brand });
                                ok++;
                            } catch (_) {
                                fail++;
                            }
                        }
                        if (skus.length === 1) {
                            toast(fail ? 'Save failed' : ('Saved About Brand for ' + skus[0]), fail ? 'error' : 'success');
                        } else {
                            toast(
                                'Bulk save: ' + ok + ' updated' + (fail ? ', ' + fail + ' failed' : ''),
                                fail ? 'error' : 'success'
                            );
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

                loadData();
            });
        })();
    </script>
@endsection
