@extends('layouts.vertical', ['title' => 'Images A+ Content', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    @include('partials.parent-row-highlight')
    <style>
        .card.iac-master-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .card.iac-master-card .card-body { padding: 1.25rem 1.5rem; }
        .iac-master-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; }
        .iac-master-toolbar .btn { padding:.3rem .6rem; font-size:.8rem; }
        #images-aplus-table { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        #images-aplus-table .tabulator-header { background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color:#fff; }
        #images-aplus-table .tabulator-col { background:transparent !important; border-right:1px solid rgba(255,255,255,.15); }
        #images-aplus-table .tabulator-col-title { color:#fff; font-size:11px; font-weight:600; text-transform:uppercase; }
        #images-aplus-table .tabulator-header-filter input {
            background:rgba(255,255,255,.95); border:none; border-radius:4px; color:#333;
            padding:4px 6px; width:100%; font-size:10px;
        }
        #images-aplus-table .tabulator-row:nth-child(even) { background:#f8fafc; }
        #images-aplus-table .tabulator-row:hover { background:#e8f0fe !important; }
        #images-aplus-table .tabulator-cell { font-size:11px; color:#475569; }
        .toast-container { position:fixed; top:20px; right:20px; z-index:9999; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        .iac-thumbs { display:flex; flex-wrap:wrap; gap:4px; align-items:center; }
        .iac-thumbs img { width:36px; height:36px; object-fit:cover; border-radius:4px; border:1px solid #e2e8f0; background:#f8fafc; cursor:pointer; }
        .iac-slots { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:12px; }
        .iac-slot {
            border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; padding:10px;
            display:flex; flex-direction:column; gap:8px; min-height:210px;
        }
        .iac-slot-title { font-size:11px; font-weight:700; color:#1e3a8a; text-transform:uppercase; letter-spacing:.03em; }
        .iac-slot-preview {
            width:100%; height:110px; border:1px dashed #cbd5e1; border-radius:8px; background:#fff;
            display:flex; align-items:center; justify-content:center; overflow:hidden; position:relative;
        }
        .iac-slot-preview img { width:100%; height:100%; object-fit:cover; }
        .iac-slot-preview .iac-empty { color:#94a3b8; font-size:12px; text-align:center; padding:8px; }
        .iac-slot .form-control { font-size:11px; }
        .iac-slot-actions { display:flex; gap:6px; flex-wrap:wrap; }
        .iac-slot-actions .btn { font-size:11px; padding:3px 8px; }
        .preview-pm-wrap { display:flex; align-items:center; gap:8px; min-width:0; width:100%; }
        .preview-pm-wrap .preview-cell { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .preview-magnify-btn {
            flex-shrink:0; width:30px; height:30px; border:none; border-radius:6px;
            background:#0d6efd; color:#fff; font-size:13px;
            display:inline-flex; align-items:center; justify-content:center; cursor:pointer;
            box-shadow:0 1px 3px rgba(13,110,253,.4);
        }
        .preview-magnify-btn:hover { background:#0b5ed7; color:#fff; }
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
        'page_title' => 'Images A+ Content',
        'sub_title' => 'A+ Content images by SKU (same product set as Description Master)',
    ])

    <div class="toast-container" id="toastContainer"></div>

    <div class="row">
        <div class="col-12">
            <div class="card iac-master-card">
                <div class="card-body">
                    <div class="mb-3 iac-master-toolbar">
                        @include('partials.parent-playback-controls')
                        <button type="button" id="exportBtn" class="btn btn-primary btn-sm">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button type="button" id="importBtn" class="btn btn-info btn-sm">
                            <i class="fas fa-upload"></i> Import
                        </button>
                        <select id="statusFilter" class="form-select form-select-sm" style="width:auto; min-width:200px;">
                            <option value="all">All rows</option>
                            <option value="has">Has A+ Images</option>
                            <option value="missing">Missing A+ Images</option>
                        </select>
                        <span class="text-muted small" id="rowCountBadge">0 products</span>
                        <span class="text-muted small" id="selectedCountBadge">0 selected</span>
                        <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
                    </div>

                    <div id="images-aplus-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Per-SKU A+ image editor (similar to Amazon A+ Contents image inputs) --}}
    <div class="modal fade" id="editImagesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="editModalTitle"><i class="fas fa-images me-2"></i>A+ Content Images</h5>
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
                    <p class="small text-muted mb-3">
                        Up to 12 A+ Content image slots per SKU. Upload a file (like Amz A+ Contents) or paste an image URL, then Save.
                    </p>
                    <div class="iac-slots" id="iacSlots"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" id="clearAllBtn">
                        <i class="fas fa-trash"></i> Clear all
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="modalSaveBtn">
                        <i class="fas fa-save"></i> <span id="modalSaveBtnLabel">Save Images</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="compositePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-magnifying-glass me-2"></i>HTML Preview</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="compositePreviewSubtitle" class="small text-muted mb-2"></div>
                    <div id="compositeSectionBadges" class="mb-3"></div>
                    <div id="compositeHtmlPreview"><div class="text-muted text-center py-4">Loading…</div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="compositeCopyBtn"><i class="fas fa-copy me-1"></i> Copy HTML</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Single-slot upload modal (Amazon A+ Contents style) --}}
    <div class="modal fade" id="slotUploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%);color:#fff;">
                    <h5 class="modal-title">
                        <i class="fas fa-upload me-2"></i>Upload Image — Slot <span id="uploadSlotLabel"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="uploadSlotIndex">
                    <div class="mb-3">
                        <label for="slotFileInput" class="form-label fw-bold">
                            <i class="fas fa-image text-primary me-1"></i>Select Image File
                        </label>
                        <input type="file" class="form-control" id="slotFileInput" accept="image/*">
                        <div class="form-text">JPG, PNG, GIF, WEBP, SVG — max 10MB</div>
                    </div>
                    <div id="slotPreviewContainer" style="display:none;">
                        <label class="form-label fw-bold">Preview:</label>
                        <div class="text-center">
                            <img id="slotPreviewImg" src="" alt="Preview" style="max-width:100%;max-height:300px;border:1px solid #ddd;border-radius:4px;padding:5px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="slotUploadSaveBtn">
                        <i class="fas fa-save me-2"></i>Upload
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
            const MAX_SLOTS = 12;
            let table = null;
            let allRows = [];
            let editModal = null;
            let uploadModal = null;
            let compositeModal = null;
            let slotUrls = Array(MAX_SLOTS).fill('');
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

            function imagesOf(row) {
                const arr = row.aplus_images || row.description_v2_images || [];
                return Array.isArray(arr) ? arr.map(u => String(u || '').trim()).filter(Boolean) : [];
            }

            function resolveUrl(url) {
                url = String(url || '').trim();
                if (!url) return '';
                if (/^https?:\/\//i.test(url) || url.startsWith('data:') || url.startsWith('/')) return url;
                return '/storage/' + url.replace(/^storage\//, '');
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
                    const count = imagesOf(data).length;
                    if (mode === 'has') return count > 0;
                    if (mode === 'missing') return count === 0;
                    return true;
                });
                updateRowCount(table.getDataCount('active'));
                updateSelectedCount();
            }

            function compactSlots() {
                return slotUrls.map(u => String(u || '').trim()).filter(Boolean).slice(0, MAX_SLOTS);
            }

            function renderSlots() {
                const wrap = document.getElementById('iacSlots');
                wrap.innerHTML = '';
                for (let i = 0; i < MAX_SLOTS; i++) {
                    const url = resolveUrl(slotUrls[i] || '');
                    const card = document.createElement('div');
                    card.className = 'iac-slot';
                    card.dataset.slot = String(i);
                    card.innerHTML =
                        '<div class="iac-slot-title">Image ' + (i + 1) + '</div>' +
                        '<div class="iac-slot-preview" data-preview="' + i + '">' +
                            (url
                                ? '<img src="' + esc(url) + '" alt="Slot ' + (i + 1) + '" onerror="this.style.display=\'none\'">'
                                : '<div class="iac-empty"><i class="fas fa-image d-block mb-1"></i>No image</div>') +
                        '</div>' +
                        '<input type="url" class="form-control form-control-sm iac-url-input" data-slot="' + i + '" placeholder="https://… image URL" value="' + esc(slotUrls[i] || '') + '">' +
                        '<div class="iac-slot-actions">' +
                            '<button type="button" class="btn btn-outline-success iac-upload-btn" data-slot="' + i + '"><i class="fas fa-upload"></i> Upload</button>' +
                            '<button type="button" class="btn btn-outline-danger iac-clear-btn" data-slot="' + i + '"><i class="fas fa-times"></i></button>' +
                        '</div>';
                    wrap.appendChild(card);
                }

                wrap.querySelectorAll('.iac-url-input').forEach(inp => {
                    inp.addEventListener('input', function () {
                        const idx = parseInt(this.dataset.slot, 10);
                        slotUrls[idx] = this.value.trim();
                        const preview = wrap.querySelector('[data-preview="' + idx + '"]');
                        const url = resolveUrl(slotUrls[idx]);
                        if (preview) {
                            preview.innerHTML = url
                                ? '<img src="' + esc(url) + '" alt="Slot ' + (idx + 1) + '" onerror="this.parentNode.innerHTML=\'<div class=\\\'iac-empty\\\'>Invalid URL</div>\'">'
                                : '<div class="iac-empty"><i class="fas fa-image d-block mb-1"></i>No image</div>';
                        }
                    });
                });
                wrap.querySelectorAll('.iac-upload-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        openSlotUpload(parseInt(this.dataset.slot, 10));
                    });
                });
                wrap.querySelectorAll('.iac-clear-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const idx = parseInt(this.dataset.slot, 10);
                        slotUrls[idx] = '';
                        renderSlots();
                    });
                });
            }

            async function saveImages(sku, images) {
                const res = await fetch('/images-a-plus-content/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        sku: sku,
                        aplus_images: images,
                        description_v2_images: images,
                    }),
                });
                const json = await res.json();
                if (!res.ok || !json.success) {
                    throw new Error(json.message || 'Save failed');
                }
                return json;
            }

            function applyToRow(sku, images) {
                const tRow = table?.getRows().find(r => r.getData().SKU === sku);
                if (tRow) {
                    tRow.update({
                        aplus_images: images,
                        description_v2_images: images,
                        aplus_image_count: images.length,
                    });
                }
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
                    ? 'Bulk edit — same A+ images will be saved to every selected SKU'
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
                    titleEl.innerHTML = '<i class="fas fa-layer-group me-2"></i>Bulk Edit A+ Content Images';
                    saveLabel.textContent = 'Save to ' + bulkEditSkus.length + ' SKUs';
                } else {
                    banner.style.display = 'none';
                    listWrap.style.display = 'none';
                    listEl.textContent = '';
                    titleEl.innerHTML = '<i class="fas fa-images me-2"></i>A+ Content Images';
                    saveLabel.textContent = 'Save Images';
                }

                const imgs = imagesOf(rowData);
                slotUrls = Array(MAX_SLOTS).fill('');
                imgs.slice(0, MAX_SLOTS).forEach((u, i) => { slotUrls[i] = u; });
                renderSlots();
                editModal.show();
            }

            function openSlotUpload(slot) {
                document.getElementById('uploadSlotIndex').value = String(slot);
                document.getElementById('uploadSlotLabel').textContent = String(slot + 1);
                document.getElementById('slotFileInput').value = '';
                document.getElementById('slotPreviewContainer').style.display = 'none';
                uploadModal.show();
            }

            function renderSectionBadges(sections) {
                const labels = {
                    bullet_points: 'Bullet Points',
                    description: 'Description',
                    features: 'Features',
                    specifications: 'Specifications',
                    package_includes: 'Package Includes',
                    about_us: 'About Us',
                    images: 'Images',
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
                        || '<div class="text-muted text-center py-4">No content available.</div>';
                } catch (e) {
                    document.getElementById('compositeHtmlPreview').innerHTML =
                        '<div class="text-danger text-center py-4">' + esc(e.message || 'Preview failed') + '</div>';
                }
            }

            function initTable(rows) {
                allRows = rows;
                if (table) {
                    table.replaceData(rows);
                    applyStatusFilter();
                    return;
                }

                table = new Tabulator('#images-aplus-table', {
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
                            title: 'Preview (PM)',
                            field: 'description_1500',
                            minWidth: 260,
                            width: 280,
                            headerFilter: 'input',
                            headerFilterPlaceholder: 'Filter preview',
                            headerSort: false,
                            formatter: function (cell) {
                                const d = cell.getRow().getData();
                                const t = String(d.description_1500 || d.product_description || '').trim();
                                const prev = !t ? '—' : (t.length > 70 ? t.slice(0, 70) + '…' : t);
                                const sku = esc(d.SKU || '');
                                return '<div class="preview-pm-wrap">' +
                                    '<span class="preview-cell" title="' + esc(t) + '">' + esc(prev) + '</span>' +
                                    '<button type="button" class="preview-magnify-btn" title="View HTML: Images, Bullets, Description, Features, Specs, Package, About Us" data-sku="' + sku + '">' +
                                    '<i class="fas fa-search"></i></button></div>';
                            },
                            cellClick: function (e, cell) {
                                const btn = e.target.closest('.preview-magnify-btn');
                                if (!btn) return;
                                e.preventDefault();
                                e.stopPropagation();
                                openCompositePreview(cell.getRow().getData());
                            },
                        },
                        {
                            title: 'A+ Images',
                            field: 'aplus_images',
                            minWidth: 260,
                            formatter: function (cell) {
                                const imgs = imagesOf(cell.getRow().getData()).slice(0, 8);
                                if (!imgs.length) return '<span class="text-muted">—</span>';
                                return '<div class="iac-thumbs">' + imgs.map(u => {
                                    const src = resolveUrl(u);
                                    return '<img src="' + esc(src) + '" alt="" title="' + esc(src) + '" onclick="window.open(\'' + esc(src).replace(/'/g, "\\'") + '\',\'_blank\')">';
                                }).join('') + '</div>';
                            },
                        },
                        {
                            title: 'Count',
                            field: 'aplus_image_count',
                            width: 80,
                            hozAlign: 'center',
                            formatter: function (cell) {
                                const n = imagesOf(cell.getRow().getData()).length;
                                const cls = n > 0 ? 'bg-success' : 'bg-secondary';
                                return '<span class="badge ' + cls + '">' + n + ' / ' + MAX_SLOTS + '</span>';
                            },
                        },
                        {
                            title: 'Action',
                            field: '_action',
                            width: 190,
                            hozAlign: 'center',
                            headerSort: false,
                            formatter: function () {
                                return '<div class="d-flex gap-1 justify-content-center">' +
                                    '<button type="button" class="btn btn-sm btn-outline-primary iac-preview-btn" title="HTML Preview">' +
                                    '<i class="fas fa-search"></i></button>' +
                                    '<button type="button" class="btn btn-sm btn-primary iac-edit-btn"><i class="fas fa-images me-1"></i>Images</button>' +
                                    '</div>';
                            },
                            cellClick: function (e, cell) {
                                const d = cell.getRow().getData();
                                if (e.target.closest('.iac-preview-btn')) openCompositePreview(d);
                                if (e.target.closest('.iac-edit-btn')) openEdit(d);
                            },
                        },
                    ],
                });

                // Fallback click handler (in case Tabulator cellClick is blocked)
                const tableEl = document.getElementById('images-aplus-table');
                if (tableEl && !tableEl.dataset.previewBound) {
                    tableEl.dataset.previewBound = '1';
                    tableEl.addEventListener('click', function (e) {
                        const btn = e.target.closest('.preview-magnify-btn, .iac-preview-btn');
                        if (!btn || !table) return;
                        const rowEl = e.target.closest('.tabulator-row');
                        if (!rowEl) return;
                        const row = table.getRow(rowEl);
                        if (row) openCompositePreview(row.getData());
                    });
                }

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
                    const res = await fetch('/images-a-plus-content-data', { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    if (!res.ok || json.status === 500) {
                        throw new Error(json.message || 'Failed to load data');
                    }
                    initTable(json.data || []);
                    applyStatusFilter();
                } catch (e) {
                    toast(e.message || 'Failed to load Images A+ Content data', 'error');
                    initTable([]);
                }
            }

            function exportExcel() {
                if (!table) return;
                const rows = table.getData('active').map(r => {
                    const imgs = imagesOf(r);
                    const row = {
                        SKU: r.SKU || '',
                        Parent: r.Parent || '',
                        title150: r.title150 || '',
                        description_1500: r.description_1500 || '',
                        aplus_image_count: imgs.length,
                    };
                    for (let i = 0; i < MAX_SLOTS; i++) {
                        row['image_' + (i + 1)] = imgs[i] || '';
                    }
                    return row;
                });
                const ws = XLSX.utils.json_to_sheet(rows);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'A+ Images');
                XLSX.writeFile(wb, 'images_a_plus_content_export.xlsx');
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
                    const imgs = [];
                    for (let i = 1; i <= MAX_SLOTS; i++) {
                        const u = String(row['image_' + i] || row['Image_' + i] || row['Image ' + i] || '').trim();
                        if (u) imgs.push(u);
                    }
                    // Also accept a single comma/newline separated column
                    if (!imgs.length) {
                        const blob = String(row.aplus_images || row.description_v2_images || '').trim();
                        if (blob) {
                            blob.split(/[\n,|]+/).map(s => s.trim()).filter(Boolean).forEach(u => imgs.push(u));
                        }
                    }
                    try {
                        const json = await saveImages(sku, imgs.slice(0, MAX_SLOTS));
                        ok++;
                        applyToRow(sku, json.aplus_images || imgs);
                    } catch (_) {
                        fail++;
                    }
                }
                toast('Import done: ' + ok + ' saved' + (fail ? ', ' + fail + ' failed' : ''), fail ? 'error' : 'success');
                applyStatusFilter();
            }

            document.addEventListener('DOMContentLoaded', function () {
                editModal = new bootstrap.Modal(document.getElementById('editImagesModal'));
                uploadModal = new bootstrap.Modal(document.getElementById('slotUploadModal'));
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

                document.getElementById('clearAllBtn').addEventListener('click', function () {
                    if (!confirm('Clear all A+ image slots for this SKU?')) return;
                    slotUrls = Array(MAX_SLOTS).fill('');
                    renderSlots();
                });

                document.getElementById('modalSaveBtn').addEventListener('click', async function () {
                    const images = compactSlots();
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
                                const json = await saveImages(sku, images);
                                applyToRow(sku, json.aplus_images || images);
                                ok++;
                            } catch (_) {
                                fail++;
                            }
                        }
                        if (skus.length === 1) {
                            toast(fail ? 'Save failed' : ('Saved A+ images for ' + skus[0]), fail ? 'error' : 'success');
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

                document.getElementById('slotFileInput').addEventListener('change', function (e) {
                    const file = e.target.files?.[0];
                    const box = document.getElementById('slotPreviewContainer');
                    const img = document.getElementById('slotPreviewImg');
                    if (!file) {
                        box.style.display = 'none';
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = function (ev) {
                        img.src = ev.target.result;
                        box.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                });

                document.getElementById('slotUploadSaveBtn').addEventListener('click', async function () {
                    const fileInput = document.getElementById('slotFileInput');
                    const file = fileInput.files?.[0];
                    const sku = document.getElementById('modalSku').value;
                    const slot = parseInt(document.getElementById('uploadSlotIndex').value, 10);
                    if (!file) {
                        toast('Please select an image file', 'error');
                        return;
                    }
                    if (file.size > 10 * 1024 * 1024) {
                        toast('Image must be 10MB or smaller', 'error');
                        return;
                    }
                    const btn = this;
                    btn.disabled = true;
                    const original = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading...';
                    try {
                        const fd = new FormData();
                        fd.append('sku', sku);
                        fd.append('slot', String(slot));
                        fd.append('image_file', file);
                        fd.append('current_images', JSON.stringify(slotUrls.map(u => String(u || '').trim())));
                        const res = await fetch('/images-a-plus-content/upload-image', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: fd,
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) {
                            throw new Error(json.message || 'Upload failed');
                        }
                        if (Array.isArray(json.slot_images)) {
                            slotUrls = Array(MAX_SLOTS).fill('');
                            json.slot_images.slice(0, MAX_SLOTS).forEach((u, i) => { slotUrls[i] = String(u || ''); });
                        } else {
                            slotUrls[slot] = json.image_url || '';
                        }
                        applyToRow(sku, json.aplus_images || compactSlots());
                        renderSlots();
                        uploadModal.hide();
                        toast('Image uploaded for slot ' + (slot + 1), 'success');
                    } catch (e) {
                        toast(e.message || 'Upload failed', 'error');
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = original;
                    }
                });

                loadData();
            });
        })();
    </script>
@endsection
