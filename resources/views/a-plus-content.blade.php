@extends('layouts.vertical', ['title' => 'A+ Content', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .card.apc-master-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .card.apc-master-card .card-body { padding: 1.25rem 1.5rem; }
        .apc-master-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; }
        .apc-master-toolbar .btn { padding:.3rem .6rem; font-size:.8rem; }
        #aplus-content-table { border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        #aplus-content-table .tabulator-header { background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color:#fff; }
        #aplus-content-table .tabulator-col { background:transparent !important; border-right:1px solid rgba(255,255,255,.15); }
        #aplus-content-table .tabulator-col-title { color:#fff; font-size:11px; font-weight:600; text-transform:uppercase; }
        #aplus-content-table .tabulator-header-filter input {
            background:rgba(255,255,255,.95); border:none; border-radius:4px; color:#333;
            padding:4px 6px; width:100%; font-size:10px;
        }
        #aplus-content-table .tabulator-row:nth-child(even) { background:#f8fafc; }
        #aplus-content-table .tabulator-row:hover { background:#e8f0fe !important; }
        #aplus-content-table .tabulator-cell { font-size:11px; color:#475569; }
        .toast-container { position:fixed; top:20px; right:20px; z-index:9999; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        .apc-thumbs { display:flex; flex-wrap:wrap; gap:4px; align-items:center; }
        .apc-thumbs img { width:36px; height:36px; object-fit:cover; border-radius:4px; border:1px solid #e2e8f0; background:#f8fafc; cursor:pointer; }
        .apc-section { border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#f8fafc; margin-bottom:14px; }
        .apc-section-title { font-size:12px; font-weight:700; color:#1e3a8a; margin-bottom:8px; }
        .apc-sync-note { font-size:11px; color:#64748b; }
        #modalDescHtml { font-family: ui-monospace, Consolas, monospace; font-size: 12px; }
        .apc-slots { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:12px; }
        .apc-slot {
            border:1px solid #e2e8f0; border-radius:10px; background:#fff; padding:10px;
            display:flex; flex-direction:column; gap:8px; min-height:210px;
        }
        .apc-slot-title { font-size:11px; font-weight:700; color:#1e3a8a; text-transform:uppercase; }
        .apc-slot-preview {
            width:100%; height:110px; border:1px dashed #cbd5e1; border-radius:8px; background:#f8fafc;
            display:flex; align-items:center; justify-content:center; overflow:hidden;
        }
        .apc-slot-preview img { width:100%; height:100%; object-fit:cover; }
        .apc-slot-preview .apc-empty { color:#94a3b8; font-size:12px; text-align:center; padding:8px; }
        .apc-slot .form-control { font-size:11px; }
        .apc-slot-actions { display:flex; gap:6px; flex-wrap:wrap; }
        .apc-slot-actions .btn { font-size:11px; padding:3px 8px; }
        #viewHtmlPreview {
            border:1px solid #e2e8f0; border-radius:8px; padding:.75rem; background:#fff;
            max-height:220px; overflow:auto; font-size:13px; line-height:1.45;
        }
        .preview-pm-wrap { display:flex; align-items:center; gap:8px; min-width:0; }
        .preview-pm-wrap .preview-cell { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .preview-magnify-btn {
            flex-shrink:0; width:28px; height:28px; border:none; border-radius:6px;
            background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color:#fff;
            display:inline-flex; align-items:center; justify-content:center; cursor:pointer;
        }
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
        'page_title' => 'A+ Content',
        'sub_title' => 'Combined Description HTML + A+ Images (autosyncs Product Master pages)',
    ])

    <div class="toast-container" id="toastContainer"></div>

    <div class="row">
        <div class="col-12">
            <div class="card apc-master-card">
                <div class="card-body">
                    <div class="mb-3 apc-master-toolbar">
                        @include('partials.parent-playback-controls')
                        <button type="button" id="exportBtn" class="btn btn-primary btn-sm">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button type="button" id="importBtn" class="btn btn-info btn-sm">
                            <i class="fas fa-upload"></i> Import
                        </button>
                        <a href="{{ route('description.for.html') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-code"></i> Description For HTML
                        </a>
                        <a href="{{ route('images.a.plus.content') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-images"></i> Images A+ Content
                        </a>
                        <select id="statusFilter" class="form-select form-select-sm" style="width:auto; min-width:200px;">
                            <option value="all">All rows</option>
                            <option value="has_html">Has HTML</option>
                            <option value="missing_html">Missing HTML</option>
                            <option value="has_images">Has Images</option>
                            <option value="missing_images">Missing Images</option>
                            <option value="complete">HTML + Images</option>
                        </select>
                        <span class="text-muted small" id="rowCountBadge">0 products</span>
                        <span class="text-muted small" id="selectedCountBadge">0 selected</span>
                        <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
                    </div>

                    <div id="aplus-content-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editApcModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="editModalTitle"><i class="fas fa-pen-to-square me-2"></i>Edit A+ Content</h5>
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
                    <div class="mb-2"><strong>Product:</strong> <span id="modalProductLabel"></span></div>
                    <div id="bulkSkuListWrap" class="mb-3" style="display:none;">
                        <div class="small text-muted mb-1">Applying to SKUs:</div>
                        <div id="bulkSkuList" class="small border rounded bg-light p-2" style="max-height:120px; overflow:auto;"></div>
                    </div>
                    <p class="apc-sync-note mb-3">
                        Saves sync automatically to <strong>Description For HTML</strong>
                        (<code>description_html</code> / <code>description_1500</code>
                        and <strong>Images A+ Content</strong>
                        <code>description_v2_images</code>.
                    </p>

                    <div class="apc-section">
                        <div class="apc-section-title"><i class="fas fa-code me-1"></i> Description (from Description For HTML)</div>
                        <textarea id="modalDescHtml" class="form-control" rows="10" placeholder="<div>…</div>"></textarea>
                        <div class="d-flex justify-content-end mt-1">
                            <span class="small text-muted" id="apcCharCount">0 chars</span>
                        </div>
                    </div>

                    <div class="apc-section mb-0">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="apc-section-title mb-0"><i class="fas fa-images me-1"></i> Images (from Images A+ Content)</div>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="clearAllBtn">
                                <i class="fas fa-trash"></i> Clear images
                            </button>
                        </div>
                        <div class="apc-slots" id="apcSlots"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="modalSaveBtn">
                        <i class="fas fa-save"></i> <span id="modalSaveBtnLabel">Save &amp; Sync</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewApcModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>A+ Content Preview</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewApcSubtitle" class="small text-muted mb-2"></div>
                    <div class="fw-semibold small mb-1">Images</div>
                    <div id="viewApcImages" class="apc-thumbs mb-3"></div>
                    <div class="fw-semibold small mb-1">HTML preview</div>
                    <div id="viewHtmlPreview"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="viewEditBtn"><i class="fas fa-edit me-1"></i> Edit</button>
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
                        <label for="slotFileInput" class="form-label fw-bold">Select Image File</label>
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
            let viewModal = null;
            let uploadModal = null;
            let compositeModal = null;
            let slotUrls = Array(MAX_SLOTS).fill('');
            let viewRowSku = '';
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

            function htmlText(row) {
                const html = String(row.description_html || '').trim();
                if (html) return html;
                const forHtml = String(row.description_for_html || '').trim();
                if (forHtml) return forHtml;
                return String(row.description_1500 || row.product_description || '').trim();
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

            function stripPreview(html) {
                const tmp = document.createElement('div');
                tmp.innerHTML = html || '';
                const t = (tmp.textContent || tmp.innerText || '').replace(/\s+/g, ' ').trim();
                if (!t) return html ? '(HTML markup)' : '—';
                return t.length > 90 ? t.slice(0, 90) + '…' : t;
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
                    const html = htmlText(data);
                    const imgs = imagesOf(data);
                    if (mode === 'has_html') return html !== '';
                    if (mode === 'missing_html') return html === '';
                    if (mode === 'has_images') return imgs.length > 0;
                    if (mode === 'missing_images') return imgs.length === 0;
                    if (mode === 'complete') return html !== '' && imgs.length > 0;
                    return true;
                });
                updateRowCount(table.getDataCount('active'));
                updateSelectedCount();
            }

            function compactSlots() {
                return slotUrls.map(u => String(u || '').trim()).filter(Boolean).slice(0, MAX_SLOTS);
            }

            function updateCharCount() {
                document.getElementById('apcCharCount').textContent =
                    ((document.getElementById('modalDescHtml').value || '').length) + ' chars';
            }

            function renderSlots() {
                const wrap = document.getElementById('apcSlots');
                wrap.innerHTML = '';
                for (let i = 0; i < MAX_SLOTS; i++) {
                    const url = resolveUrl(slotUrls[i] || '');
                    const card = document.createElement('div');
                    card.className = 'apc-slot';
                    card.innerHTML =
                        '<div class="apc-slot-title">Image ' + (i + 1) + '</div>' +
                        '<div class="apc-slot-preview" data-preview="' + i + '">' +
                            (url
                                ? '<img src="' + esc(url) + '" alt="Slot ' + (i + 1) + '">'
                                : '<div class="apc-empty"><i class="fas fa-image d-block mb-1"></i>No image</div>') +
                        '</div>' +
                        '<input type="url" class="form-control form-control-sm apc-url-input" data-slot="' + i + '" placeholder="https://… image URL" value="' + esc(slotUrls[i] || '') + '">' +
                        '<div class="apc-slot-actions">' +
                            '<button type="button" class="btn btn-outline-success apc-upload-btn" data-slot="' + i + '"><i class="fas fa-upload"></i> Upload</button>' +
                            '<button type="button" class="btn btn-outline-danger apc-clear-btn" data-slot="' + i + '"><i class="fas fa-times"></i></button>' +
                        '</div>';
                    wrap.appendChild(card);
                }

                wrap.querySelectorAll('.apc-url-input').forEach(inp => {
                    inp.addEventListener('input', function () {
                        const idx = parseInt(this.dataset.slot, 10);
                        slotUrls[idx] = this.value.trim();
                        const preview = wrap.querySelector('[data-preview="' + idx + '"]');
                        const url = resolveUrl(slotUrls[idx]);
                        if (preview) {
                            preview.innerHTML = url
                                ? '<img src="' + esc(url) + '" alt="Slot ' + (idx + 1) + '">'
                                : '<div class="apc-empty"><i class="fas fa-image d-block mb-1"></i>No image</div>';
                        }
                    });
                });
                wrap.querySelectorAll('.apc-upload-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        openSlotUpload(parseInt(this.dataset.slot, 10));
                    });
                });
                wrap.querySelectorAll('.apc-clear-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const idx = parseInt(this.dataset.slot, 10);
                        slotUrls[idx] = '';
                        renderSlots();
                    });
                });
            }

            async function saveApc(sku, html, images) {
                const res = await fetch('/a-plus-content/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        sku: sku,
                        description_html: html,
                        description_for_html: html,
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

            function applySyncedToRow(sku, json) {
                const tRow = table?.getRows().find(r => r.getData().SKU === sku);
                if (!tRow) return;
                const images = json.aplus_images || json.description_v2_images || [];
                const html = json.description_for_html != null ? json.description_for_html : (json.description_html || '');
                tRow.update({
                    description_html: json.description_html != null ? json.description_html : html,
                    description_for_html: html,
                    description_1500: json.description_1500 != null ? json.description_1500 : html,
                    product_description: json.product_description != null ? json.product_description : html,
                    aplus_images: images,
                    description_v2_images: images,
                    aplus_image_count: images.length,
                });
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
                    ? 'Bulk edit — same HTML + images will be saved to every selected SKU'
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
                    titleEl.innerHTML = '<i class="fas fa-layer-group me-2"></i>Bulk Edit A+ Content';
                    saveLabel.textContent = 'Save to ' + bulkEditSkus.length + ' SKUs';
                } else {
                    banner.style.display = 'none';
                    listWrap.style.display = 'none';
                    listEl.textContent = '';
                    titleEl.innerHTML = '<i class="fas fa-pen-to-square me-2"></i>Edit A+ Content';
                    saveLabel.textContent = 'Save & Sync';
                }

                document.getElementById('modalDescHtml').value = htmlText(rowData);
                updateCharCount();
                const imgs = imagesOf(rowData);
                slotUrls = Array(MAX_SLOTS).fill('');
                imgs.slice(0, MAX_SLOTS).forEach((u, i) => { slotUrls[i] = u; });
                renderSlots();
                editModal.show();
            }

            function openView(rowData) {
                viewRowSku = rowData.SKU || '';
                document.getElementById('viewApcSubtitle').textContent =
                    (rowData.SKU || '') + ' — ' + (rowData.Parent || rowData.title150 || '');
                const imgs = imagesOf(rowData);
                const imgWrap = document.getElementById('viewApcImages');
                imgWrap.innerHTML = imgs.length
                    ? imgs.map(u => {
                        const src = resolveUrl(u);
                        return '<img src="' + esc(src) + '" alt="" onclick="window.open(\'' + esc(src).replace(/'/g, "\\'") + '\',\'_blank\')">';
                    }).join('')
                    : '<span class="text-muted">No images</span>';
                const html = htmlText(rowData);
                document.getElementById('viewHtmlPreview').innerHTML = html || '<span class="text-muted">(empty)</span>';
                viewModal.show();
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

                table = new Tabulator('#aplus-content-table', {
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
                                rowRange: 'active',
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
                            width: 180,
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
                            width: 170,
                            headerFilter: 'input',
                            headerFilterPlaceholder: 'Filter SKU',
                        },
                        {
                            title: 'Preview (PM)',
                            field: 'description_for_html',
                            minWidth: 240,
                            headerFilter: 'input',
                            headerFilterPlaceholder: 'Filter preview',
                            formatter: function (cell) {
                                const d = cell.getRow().getData();
                                const full = htmlText(d);
                                const prev = stripPreview(full);
                                return '<div class="preview-pm-wrap">' +
                                    '<span class="preview-cell" title="' + esc(prev) + '">' + esc(prev) + '</span>' +
                                    '<button type="button" class="preview-magnify-btn" title="Open HTML preview" data-sku="' + esc(d.SKU || '') + '">' +
                                    '<i class="fas fa-magnifying-glass"></i></button></div>';
                            },
                            cellClick: function (e, cell) {
                                if (!e.target.closest('.preview-magnify-btn')) return;
                                e.stopPropagation();
                                openCompositePreview(cell.getRow().getData());
                            },
                        },
                        {
                            title: 'A+ Images',
                            field: 'aplus_images',
                            minWidth: 220,
                            formatter: function (cell) {
                                const imgs = imagesOf(cell.getRow().getData()).slice(0, 6);
                                if (!imgs.length) return '<span class="text-muted">—</span>';
                                return '<div class="apc-thumbs">' + imgs.map(u => {
                                    const src = resolveUrl(u);
                                    return '<img src="' + esc(src) + '" alt="" title="' + esc(src) + '">';
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
                                return '<span class="badge ' + (n > 0 ? 'bg-success' : 'bg-secondary') + '">' + n + '/' + MAX_SLOTS + '</span>';
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
                                    '<button type="button" class="btn btn-sm btn-info text-white apc-view-btn"><i class="fas fa-eye"></i></button>' +
                                    '<button type="button" class="btn btn-sm btn-primary apc-edit-btn"><i class="fas fa-edit"></i></button>' +
                                    '</div>';
                            },
                            cellClick: function (e, cell) {
                                const d = cell.getRow().getData();
                                if (e.target.closest('.apc-view-btn')) openView(d);
                                if (e.target.closest('.apc-edit-btn')) openEdit(d);
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
                    const res = await fetch('/a-plus-content-data', { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    if (!res.ok || json.status === 500) {
                        throw new Error(json.message || 'Failed to load data');
                    }
                    initTable(json.data || []);
                    applyStatusFilter();
                } catch (e) {
                    toast(e.message || 'Failed to load A+ Content data', 'error');
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
                        description_html: htmlText(r),
                        description_1500: r.description_1500 || '',
                        aplus_image_count: imgs.length,
                    };
                    for (let i = 0; i < MAX_SLOTS; i++) row['image_' + (i + 1)] = imgs[i] || '';
                    return row;
                });
                const ws = XLSX.utils.json_to_sheet(rows);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'A+ Content');
                XLSX.writeFile(wb, 'a_plus_content_export.xlsx');
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
                    const html = String(
                        row.description_html || row.description_for_html || row.description_1500 || ''
                    );
                    const imgs = [];
                    for (let i = 1; i <= MAX_SLOTS; i++) {
                        const u = String(row['image_' + i] || '').trim();
                        if (u) imgs.push(u);
                    }
                    try {
                        const json = await saveApc(sku, html, imgs);
                        ok++;
                        applySyncedToRow(sku, json);
                    } catch (_) {
                        fail++;
                    }
                }
                toast('Import done: ' + ok + ' saved' + (fail ? ', ' + fail + ' failed' : ''), fail ? 'error' : 'success');
                applyStatusFilter();
            }

            document.addEventListener('DOMContentLoaded', function () {
                editModal = new bootstrap.Modal(document.getElementById('editApcModal'));
                viewModal = new bootstrap.Modal(document.getElementById('viewApcModal'));
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
                document.getElementById('modalDescHtml').addEventListener('input', updateCharCount);

                document.getElementById('clearAllBtn').addEventListener('click', function () {
                    if (!confirm('Clear all A+ image slots?')) return;
                    slotUrls = Array(MAX_SLOTS).fill('');
                    renderSlots();
                });

                document.getElementById('modalSaveBtn').addEventListener('click', async function () {
                    const html = document.getElementById('modalDescHtml').value || '';
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
                                const json = await saveApc(sku, html, images);
                                applySyncedToRow(sku, json);
                                ok++;
                            } catch (_) {
                                fail++;
                            }
                        }
                        if (skus.length === 1) {
                            toast(fail ? 'Save failed' : ('Saved & synced A+ Content for ' + skus[0]), fail ? 'error' : 'success');
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

                document.getElementById('viewEditBtn').addEventListener('click', function () {
                    const row = table?.getData().find(r => r.SKU === viewRowSku);
                    viewModal.hide();
                    if (row) openEdit(row);
                });

                document.getElementById('slotFileInput').addEventListener('change', function (e) {
                    const file = e.target.files?.[0];
                    const box = document.getElementById('slotPreviewContainer');
                    const img = document.getElementById('slotPreviewImg');
                    if (!file) { box.style.display = 'none'; return; }
                    const reader = new FileReader();
                    reader.onload = function (ev) {
                        img.src = ev.target.result;
                        box.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                });

                document.getElementById('slotUploadSaveBtn').addEventListener('click', async function () {
                    const file = document.getElementById('slotFileInput').files?.[0];
                    const sku = document.getElementById('modalSku').value;
                    const slot = parseInt(document.getElementById('uploadSlotIndex').value, 10);
                    if (!file) { toast('Please select an image file', 'error'); return; }
                    if (file.size > 10 * 1024 * 1024) { toast('Image must be 10MB or smaller', 'error'); return; }

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
                        const res = await fetch('/a-plus-content/upload-image', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: fd,
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error(json.message || 'Upload failed');

                        if (Array.isArray(json.slot_images)) {
                            slotUrls = Array(MAX_SLOTS).fill('');
                            json.slot_images.slice(0, MAX_SLOTS).forEach((u, i) => { slotUrls[i] = String(u || ''); });
                        } else {
                            slotUrls[slot] = json.image_url || '';
                        }
                        applySyncedToRow(sku, {
                            aplus_images: json.aplus_images || compactSlots(),
                            description_v2_images: json.description_v2_images || compactSlots(),
                            description_for_html: document.getElementById('modalDescHtml').value || '',
                            description_html: document.getElementById('modalDescHtml').value || '',
                        });
                        renderSlots();
                        uploadModal.hide();
                        toast('Image uploaded & synced for slot ' + (slot + 1), 'success');
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
