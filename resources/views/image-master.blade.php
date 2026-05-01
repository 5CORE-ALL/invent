@extends('layouts.vertical', ['title' => 'Image Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .card.im-master-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .table-responsive { position:relative; border:1px solid #e2e8f0; border-radius:10px; max-height:640px; overflow:auto; background:#fff; }
        #im-master-table thead th { position:sticky; top:0; vertical-align:middle!important; background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%)!important; color:#fff; z-index:10; padding:6px 8px; font-size:10px; font-weight:600; text-transform:uppercase; }
        #im-master-table tbody td { padding:8px 10px; vertical-align:middle!important; border-bottom:1px solid #edf2f9; font-size:11px; }
        .im-thumb { width:56px; height:56px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0; background:#f8fafc; }
        .marketplaces-cell { vertical-align:middle!important; }
        .bp-mp-inline { display:flex; flex-wrap:wrap; align-items:flex-end; gap:6px; justify-content:flex-start; min-width:120px; }
        .bp-mp-stack { display:flex; flex-direction:column; align-items:center; gap:3px; border:none; background:transparent; padding:0; cursor:pointer; }
        .bp-mp-dot { width:10px; height:10px; border-radius:50%; border:2px solid #94a3b8; background:transparent; transition:background .15s,border-color .15s; flex-shrink:0; }
        .bp-mp-dot.pushed { background:#22c55e; border-color:#22c55e; }
        .marketplace-btn { width:28px; height:28px; border:none; border-radius:4px; color:#fff; font-weight:600; font-size:10px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; padding:0; }
        .btn-ebay1 { background-color:#0d6efd; } .btn-ebay2 { background-color:#198754; } .btn-ebay3 { background-color:#fd7e14; }
        .btn-macy { background-color:#e20074; } .btn-amazon { background-color:#ff9900; color:#232f3e!important; }
        .btn-temu { background-color:#ff6b00; } .btn-reverb { background-color:#333; } .btn-shopify { background-color:#7cb342; }
        .btn-shopify-pls { background-color:#5c6bc0; } .btn-walmart { background-color:#0071ce; } .btn-wayfair { background-color:#7b3f9a; }
        .btn-shein { background-color:#000; } .btn-doba { background-color:#6c757d; } .btn-aliexpress { background-color:#e43225; }
        .btn-bestbuy { background-color:#0046be; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        /* ── Image card grid ─────────────────────────────────────── */
        .im-grid { display:flex; flex-wrap:wrap; gap:10px; min-height:40px; padding:4px 0; }
        .im-card { width:120px; border:2px solid #e2e8f0; border-radius:10px; overflow:hidden; cursor:pointer; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.06); transition:box-shadow .15s,border-color .15s; user-select:none; position:relative; }
        .im-card:hover { box-shadow:0 3px 12px rgba(44,110,213,.18); border-color:#93c5fd; }
        .im-card.im-card-dragging { opacity:.45; border:2px dashed #6366f1; }
        /* Selected state */
        .im-card.is-selected { border:2px solid #6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.22); }
        .im-card.is-selected .im-card-img-wrap::after { content:''; position:absolute; inset:0; background:rgba(99,102,241,.12); pointer-events:none; }
        /* Push-order badge — shows sequence number on selected cards */
        .im-card-check { position:absolute; bottom:5px; left:5px; background:#6366f1; color:#fff; border-radius:50%; width:22px; height:22px; font-size:11px; display:none; align-items:center; justify-content:center; pointer-events:none; z-index:4; font-weight:800; line-height:1; box-shadow:0 1px 4px rgba(99,102,241,.45); }
        .im-card.is-selected .im-card-check { display:flex; }
        .im-card-img-wrap { position:relative; width:120px; height:100px; background:#f1f5f9; overflow:hidden; }
        .im-card-img-wrap img { width:100%; height:100%; object-fit:cover; display:block; }
        .im-card-badge { position:absolute; top:4px; left:4px; background:rgba(0,0,0,.55); color:#fff; border-radius:4px; font-size:9px; font-weight:700; padding:1px 5px; line-height:1.5; pointer-events:none; z-index:3; }
        .im-card-del { position:absolute; top:4px; right:4px; background:rgba(220,38,38,.88); border:none; color:#fff; border-radius:50%; width:22px; height:22px; font-size:11px; cursor:pointer; display:none; align-items:center; justify-content:center; padding:0; line-height:1; transition:background .12s; z-index:5; }
        .im-card-del:hover { background:#b91c1c; }
        .im-card:hover .im-card-del { display:flex; }
        .im-card-footer { padding:4px 6px 5px; }
        .im-card-name { font-size:10px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:108px; }
        .im-card-arrows { display:flex; gap:3px; margin-top:3px; }
        .im-card-arrows button { flex:1; border:1px solid #e2e8f0; background:#f8fafc; border-radius:4px; font-size:11px; cursor:pointer; padding:1px 0; color:#475569; transition:background .1s; }
        .im-card-arrows button:hover { background:#e0e7ff; color:#4338ca; }
        /* stored-image badge tint */
        .im-card.is-stored .im-card-img-wrap { border-bottom:2px solid #6366f1; }
        /* selection bar */
        .im-select-bar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:6px; }
        .im-select-info { font-size:11px; font-weight:600; color:#6366f1; background:#eef2ff; border-radius:5px; padding:2px 8px; }
        /* ─────────────────────────────────────────────────────────── */
        .toast-container { z-index:1100; }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('layouts.shared/page-title', [
        'page_title' => 'Image Master',
        'sub_title' => 'Product images by marketplace',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card im-master-card">
                <div class="card-body">
                    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" id="exportBtn" class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Export</button>
                        <button type="button" id="importBtn" class="btn btn-info btn-sm"><i class="fas fa-upload"></i> Import</button>
                        <button type="button" id="pushSelectedBtn" class="btn btn-secondary btn-sm"><i class="fas fa-cloud-upload-alt"></i> Push Selected</button>
                        <button type="button" id="pushAllBtn" class="btn btn-warning btn-sm"><i class="fas fa-cloud-upload-alt"></i> Push ALL to All Marketplaces</button>
                        <span class="text-muted small" id="rowCountBadge">0 products</span>
                        <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
                    </div>
                    <div class="table-responsive">
                        <table id="im-master-table" class="table w-100">
                            <thead>
                                <tr>
                                    <th>SKU <input type="text" id="skuSearchIm" class="form-control form-control-sm mt-1" placeholder="Search"></th>
                                    <th>Product Name</th>
                                    <th>Preview</th>
                                    <th>Action</th>
                                    <th title="eBay1–3, Macy's, Reverb">
                                        <div class="small fw-bold">CHANNELS</div>
                                        <div class="d-flex flex-wrap gap-1 justify-content-center mt-1">
                                            <span class="marketplace-btn btn-ebay1" style="pointer-events:none;">E1</span>
                                            <span class="marketplace-btn btn-ebay2" style="pointer-events:none;">E2</span>
                                            <span class="marketplace-btn btn-ebay3" style="pointer-events:none;">E3</span>
                                            <span class="marketplace-btn btn-macy" style="pointer-events:none;">M</span>
                                            <span class="marketplace-btn btn-reverb" style="pointer-events:none;">R</span>
                                        </div>
                                    </th>
                                    <th title="Amazon, Temu">
                                        <div class="small fw-bold">AMZ / TEMU</div>
                                        <div class="d-flex flex-wrap gap-1 justify-content-center mt-1">
                                            <span class="marketplace-btn btn-amazon" style="pointer-events:none;">A</span>
                                            <span class="marketplace-btn btn-temu" style="pointer-events:none;">T</span>
                                        </div>
                                    </th>
                                    <th title="Shopify, extended">
                                        <div class="small fw-bold">SHOPIFY</div>
                                        <div class="d-flex flex-wrap gap-1 justify-content-center mt-1">
                                            <span class="marketplace-btn btn-shopify" style="pointer-events:none;">SM</span>
                                            <span class="marketplace-btn btn-shopify-pls" style="pointer-events:none;">PLS</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>
                    <div id="pushProgressBox" class="alert alert-info mt-3 py-2 px-3" style="display:none;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <div class="small" id="pushProgressTitle">Pushing...</div>
                        </div>
                        <div class="small mt-2" id="pushProgressDetails"></div>
                    </div>
                    <div id="rainbow-loader" class="text-center py-4" style="display:none;">
                        <div class="spinner-border text-primary"></div>
                        <div class="mt-2 text-muted small">Loading Image Master…</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editImModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-images me-2"></i>Edit images &amp; push</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalSku">
                    <div class="mb-2"><strong>SKU:</strong> <span id="modalSkuLabel"></span></div>
                    <div class="mb-3"><strong>Product:</strong> <span id="modalProductLabel"></span></div>

                    {{-- ── ADD IMAGES SECTION ─────────────────────────────────── --}}
                    <div class="border rounded p-3 mb-3" style="background:#f8fafc;">
                        <div class="fw-semibold small mb-2"><i class="fas fa-folder-open me-1"></i>Add Images</div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                            <label class="btn btn-outline-primary btn-sm mb-0" for="modalFileInput" style="cursor:pointer;">
                                <i class="fas fa-image me-1"></i>Choose Files
                            </label>
                            <input type="file" class="d-none" id="modalFileInput" accept=".jpg,.jpeg,.png,.webp" multiple>
                            <span class="text-muted small" id="fileChosenLabel">No file chosen</span>
                        </div>
                        {{-- Pre-upload preview list --}}
                        <div id="uploadPreviewList" class="mb-2" style="display:none;">
                            <div class="small text-muted mb-1">Selected files (not yet uploaded):</div>
                            <div id="uploadPreviewItems" class="d-flex flex-wrap gap-2"></div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-success btn-sm" id="uploadImagesBtn" style="display:none;">
                                <i class="fas fa-upload me-1"></i>Upload Images
                            </button>
                            <div class="spinner-border spinner-border-sm text-success" id="uploadSpinner" role="status" style="display:none;"></div>
                            <span class="small text-success fw-semibold" id="uploadSuccessMsg" style="display:none;"></span>
                        </div>
                    </div>
                    {{-- ── END ADD IMAGES ──────────────────────────────────────── --}}

                    <!-- <div class="mb-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchAmazonBtn"><i class="fab fa-amazon"></i> Fetch Amazon images</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchEbay1Btn"><i class="fab fa-ebay"></i> Fetch eBay1</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchEbay2Btn">eBay2</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchEbay3Btn">eBay3</button>
                    </div> -->
                    <div class="fw-semibold small mb-1 d-flex align-items-center gap-2 flex-wrap">
                        Order (drag to reorder)
                        <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2" id="selAllBtn" style="font-size:10px;">Select All</button>
                        <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2" id="selNoneBtn" style="font-size:10px;">Clear</button>
                        <span id="selectionInfo" class="im-select-info" style="display:none;"></span>
                        <span class="text-muted small ms-auto">Click card to select · drag to reorder</span>
                    </div>
                    <div id="imSlots"></div>
                    <div class="mt-3">
                        <div class="fw-semibold small mb-1 d-flex align-items-center flex-wrap gap-2">
                            <span>Push to marketplaces</span>
                            <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2" id="mpSelAllBtn" style="font-size:10px;">All channels</button>
                            <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2" id="mpSelNoneBtn" style="font-size:10px;">None</button>
                        </div>
                        <div id="modalMarketplaceChecks" class="row g-1"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="savePmBtn"><i class="fas fa-save"></i> Save to Product Master</button>
                    <button type="button" class="btn btn-primary" id="pushModalBtn"><i class="fas fa-cloud-upload-alt"></i> Push selected</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Push mode selection popup ──────────────────────────────── --}}
    <div class="modal fade" id="pushModeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header py-2" style="background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%);">
                    <h6 class="modal-title text-white mb-0"><i class="fas fa-cloud-upload-alt me-2"></i>How to push images?</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pb-1">
                    <p class="small mb-1">Pushing <strong id="pmImageCount"></strong> image(s) to <strong id="pmMpCount"></strong> marketplace(s).</p>
                    <p class="small text-muted mb-0">What should happen to the <strong>existing</strong> marketplace images?</p>
                </div>
                <div class="modal-footer flex-column gap-2 pt-2 pb-3 border-0">
                    <button type="button" class="btn btn-danger w-100" id="pmReplaceBtn">
                        <i class="fas fa-exchange-alt me-1"></i><strong>Replace</strong> — remove existing, use only selected
                    </button>
                    <button type="button" class="btn btn-success w-100" id="pmAddBtn">
                        <i class="fas fa-plus me-1"></i><strong>Add</strong> — keep existing, append selected
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
    {{-- ── End push mode popup ─────────────────────────────────────── --}}

    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const MARKETPLACES = ['ebay','ebay2','ebay3','amazon','temu','macy','reverb','shopify_main','shopify_pls'];
    const LABELS = {
        ebay:'eBay1', ebay2:'eBay2', ebay3:'eBay3', amazon:'Amazon', temu:'Temu', macy:"Macy's", reverb:'Reverb',
        shopify_main:'Shopify Main', shopify_pls:'Shopify PLS',
    };
    const MP_TILE = {
        ebay:'btn-ebay1', ebay2:'btn-ebay2', ebay3:'btn-ebay3', amazon:'btn-amazon', temu:'btn-temu', macy:'btn-macy', reverb:'btn-reverb',
        shopify_main:'btn-shopify', shopify_pls:'btn-shopify-pls',
    };
    const MP_SHORT = {
        ebay:'E1', ebay2:'E2', ebay3:'E3', amazon:'A', temu:'T', macy:'M', reverb:'R',
        shopify_main:'SM', shopify_pls:'PLS',
    };
    const GROUPS = {
        g1: ['ebay','ebay2','ebay3','macy','reverb'],
        g2: ['amazon','temu'],
        g3: ['shopify_main','shopify_pls'],
    };
    const EBAY3_WARN = 'eBay3 has different listing structure. Please verify images before pushing.';

    let tableData = [];
    const bySku = new Map();
    let editModal;
    let modalUrls = [];
    let pendingFiles = [];
    let selectedUrls = new Set();   // URLs checked for push

    // Encode URL so spaces/special chars load correctly in <img src>
    const imgSrc = url => { try { return encodeURI(decodeURIComponent(url)); } catch(_) { return encodeURI(url); } };

    const esc = (s) => { const d=document.createElement('div'); d.textContent = String(s??''); return d.innerHTML; };

    function toast(msg, ok=true) {
        if (!window.bootstrap?.Toast) { alert(msg); return; }
        const id = 't'+Date.now();
        document.getElementById('toastContainer').insertAdjacentHTML('beforeend',
            `<div id="${id}" class="toast align-items-center text-bg-${ok?'success':'danger'} border-0"><div class="d-flex"><div class="toast-body">${esc(msg)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`);
        const el = document.getElementById(id);
        const t = new bootstrap.Toast(el, { delay: 3200 });
        t.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    function setPushProgress(visible, title = '', detailsHtml = '') {
        const box = document.getElementById('pushProgressBox');
        const t = document.getElementById('pushProgressTitle');
        const d = document.getElementById('pushProgressDetails');
        if (!box || !t || !d) return;
        box.style.display = visible ? 'block' : 'none';
        if (title) t.textContent = title;
        d.innerHTML = detailsHtml || '';
    }

    function confirmEbay3Push(mps) {
        if (!Array.isArray(mps) || !mps.includes('ebay3')) return true;
        return window.confirm(EBAY3_WARN);
    }

    function loadData() {
        document.getElementById('rainbow-loader').style.display = 'block';
        fetch('/image-master-data').then(r=>r.json()).then(res => {
            const raw = Array.isArray(res.data) ? res.data : [];
            tableData = raw.filter(i => i && i.SKU && !String(i.SKU).toUpperCase().includes('PARENT'));
            bySku.clear();
            tableData.forEach(r => bySku.set(String(r.SKU), r));
            renderTable(tableData);
            document.getElementById('rowCountBadge').textContent = tableData.length + ' products';
        }).catch(e => toast('Load failed: '+e.message, false))
        .finally(() => { document.getElementById('rainbow-loader').style.display = 'none'; });
    }

    function mpStackHtml(sku, mp, val) {
        const pushed = (val || '').trim() !== '';
        const tile = MP_TILE[mp] || 'btn-secondary';
        const short = MP_SHORT[mp] || mp;
        return `<button type="button" class="bp-mp-stack" data-push-mp="${mp}" data-sku="${esc(sku)}" title="${esc(LABELS[mp])}">
            <span class="bp-mp-dot ${pushed?'pushed':''}"></span>
            <span class="marketplace-btn ${tile}">${esc(short)}</span>
        </button>`;
    }

    function groupCell(gkey, sku, row) {
        const keys = GROUPS[gkey] || [];
        const im = row.image_master || {};
        return `<div class="marketplaces-cell"><div class="bp-mp-inline">${keys.map(mp => mpStackHtml(sku, mp, im[mp]||'')).join('')}</div></div>`;
    }

    function renderTable(rows) {
        const tbody = document.getElementById('table-body');
        const q = (document.getElementById('skuSearchIm')?.value || '').trim().toLowerCase();
        if (q) rows = rows.filter(r => String(r.SKU||'').toLowerCase().includes(q));
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No products</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const sku = String(r.SKU||'');
            const thumb = r.preview_thumb || r.image_path || r.main_image || r.image1 || '';
            const thumbImg = thumb ? `<img class="im-thumb" src="${esc(thumb)}" alt="">` : '<span class="text-muted">—</span>';
            return `<tr data-sku="${esc(sku)}">
                <td>${esc(sku)}</td>
                <td>${esc(r.Parent||sku)}</td>
                <td>${thumbImg}</td>
                <td><button type="button" class="btn btn-sm btn-primary edit-btn" data-edit="${esc(sku)}"><i class="fas fa-edit"></i> Edit</button></td>
                <td>${groupCell('g1', sku, r)}</td>
                <td>${groupCell('g2', sku, r)}</td>
                <td>${groupCell('g3', sku, r)}</td>
            </tr>`;
        }).join('');
        document.querySelectorAll('.edit-btn[data-edit]').forEach(b => b.addEventListener('click', () => openEditModal(b.dataset.edit)));
        document.querySelectorAll('.bp-mp-stack[data-push-mp]').forEach(b => b.addEventListener('click', () => quickPush(b.dataset.sku, b.dataset.pushMp)));
    }

    function pmImageUrls(row) {
        const u = [];
        for (let i=1;i<=12;i++) {
            const v = row['image'+i];
            if (v && String(v).trim()) u.push(String(v).trim());
        }
        if (u.length) return u;
        ['main_image','image_path'].forEach(k => {
            const v = row[k];
            if (v && String(v).trim()) u.push(String(v).trim());
        });
        return u.slice(0,12);
    }

    // storedImageIds: url → DB id (so we can delete from server)
    const storedImageMeta = new Map();

    function openEditModal(sku) {
        const row = bySku.get(String(sku));
        if (!row) return;
        document.getElementById('modalSku').value = sku;
        document.getElementById('modalSkuLabel').textContent = sku;
        document.getElementById('modalProductLabel').textContent = row.Parent || sku;
        storedImageMeta.clear();
        selectedUrls.clear();
        modalUrls = pmImageUrls(row);
        renderSlots();
        // Reset the upload section
        pendingFiles = [];
        document.getElementById('modalFileInput').value = '';
        document.getElementById('fileChosenLabel').textContent = 'No file chosen';
        document.getElementById('uploadPreviewList').style.display = 'none';
        document.getElementById('uploadPreviewItems').innerHTML = '';
        document.getElementById('uploadImagesBtn').style.display = 'none';
        document.getElementById('uploadSuccessMsg').style.display = 'none';
        document.getElementById('modalMarketplaceChecks').innerHTML = MARKETPLACES.map(mp => `
            <div class="col-6 col-md-4 col-lg-3">
                <label class="form-check small"><input type="checkbox" class="form-check-input im-mp-chk" value="${mp}" checked> ${esc(LABELS[mp])}</label>
            </div>`).join('');
        if (editModal) editModal.show();
        // Load stored images for this SKU in the background
        loadStoredSkuImages(sku);
    }

    async function loadStoredSkuImages(sku) {
        try {
            const r = await fetch('/image-master/sku-images?sku=' + encodeURIComponent(sku));
            const j = await r.json();
            if (!j.success || !j.images?.length) return;
            const existingSet = new Set(modalUrls);
            j.images.forEach(img => {
                storedImageMeta.set(img.url, { id: img.id, name: img.name });
                if (!existingSet.has(img.url)) {
                    modalUrls.push(img.url);
                    existingSet.add(img.url);
                }
            });
            if (modalUrls.length > 12) modalUrls = modalUrls.slice(0, 12);
            renderSlots();
        } catch (_) {}
    }

    function updateSelectionUI() {
        const info = document.getElementById('selectionInfo');
        const pushBtn = document.getElementById('pushModalBtn');
        if (selectedUrls.size > 0) {
            info.style.display = 'inline';
            info.textContent   = `${selectedUrls.size} of ${modalUrls.length} selected`;
            if (pushBtn) pushBtn.innerHTML = `<i class="fas fa-cloud-upload-alt"></i> Push selected (${selectedUrls.size})`;
        } else {
            info.style.display = 'none';
            if (pushBtn) pushBtn.innerHTML = `<i class="fas fa-cloud-upload-alt"></i> Push selected`;
        }
    }

    function renderSlots() {
        const el = document.getElementById('imSlots');
        if (!modalUrls.length) {
            el.innerHTML = '<div class="text-muted small py-2">No images yet. Upload or fetch from a marketplace above.</div>';
            updateSelectionUI();
            return;
        }

        // Build push-order map: url → sequence number (based on grid position, not click order)
        // If nothing is selected, every image will be pushed, so every card shows its grid position.
        let pushSeq = 0;
        const pushOrderMap = new Map();
        modalUrls.forEach(u => {
            if (selectedUrls.size === 0 || selectedUrls.has(u)) {
                pushOrderMap.set(u, ++pushSeq);
            }
        });

        el.innerHTML = '<div class="im-grid" id="imGrid">' +
            modalUrls.map((url, idx) => {
                const meta     = storedImageMeta.get(url);
                const isStored = !!meta;
                const isSel    = selectedUrls.size === 0 ? false : selectedUrls.has(url);
                const name     = meta?.name ?? decodeURIComponent(url.split('/').pop().split('?')[0]);
                const dbId     = meta?.id ?? '';
                const pushPos  = pushOrderMap.get(url);  // sequence number shown on card
                return `<div class="im-card${isStored?' is-stored':''}${isSel?' is-selected':''}"
                            draggable="true" data-idx="${idx}"
                            data-url="${esc(url)}" data-dbid="${esc(String(dbId))}">
                    <div class="im-card-img-wrap">
                        <img src="${esc(url)}" alt=""
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22100%22%3E%3Crect width=%22120%22 height=%22100%22 fill=%22%23f1f5f9%22/%3E%3Ctext x=%2260%22 y=%2254%22 font-size=%2211%22 fill=%22%2394a3b8%22 text-anchor=%22middle%22%3ENo preview%3C/text%3E%3C/svg%3E'">
                        <span class="im-card-badge">${idx + 1}</span>
                        <span class="im-card-check">${pushPos !== undefined ? pushPos : ''}</span>
                        <button type="button" class="im-card-del" data-i="${idx}" title="Delete image"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="im-card-footer">
                        <div class="im-card-name" title="${esc(url)}">${esc(name)}</div>
                        <div class="im-card-arrows">
                            <button type="button" class="im-up" data-i="${idx}" title="Move left">&#8592;</button>
                            <button type="button" class="im-down" data-i="${idx}" title="Move right">&#8594;</button>
                        </div>
                    </div>
                </div>`;
            }).join('') + '</div>';

        const grid = document.getElementById('imGrid');

        // ── Delete button ──────────────────────────────────────────
        grid.querySelectorAll('.im-card-del').forEach(b => {
            b.addEventListener('click', async (e) => {
                e.stopPropagation();
                const i      = +b.dataset.i;
                const card   = b.closest('.im-card');
                const dbId   = card?.dataset.dbid;
                const removed = modalUrls.splice(i, 1)[0];
                storedImageMeta.delete(removed);
                selectedUrls.delete(removed);
                renderSlots();
                if (dbId) {
                    try {
                        await fetch('/image-master/sku-image/' + dbId, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        });
                    } catch (_) {}
                }
            });
        });

        // ── Click card body = toggle selection ────────────────────
        grid.querySelectorAll('.im-card').forEach(card => {
            card.addEventListener('click', (e) => {
                // Don't toggle when clicking buttons inside the card
                if (e.target.closest('.im-card-del, .im-card-arrows')) return;
                const url = card.dataset.url;
                if (selectedUrls.has(url)) selectedUrls.delete(url);
                else selectedUrls.add(url);
                card.classList.toggle('is-selected', selectedUrls.has(url));
                const chk = card.querySelector('.im-card-check');
                if (chk) chk.style.display = selectedUrls.has(url) ? 'flex' : '';
                updateSelectionUI();
            });
        });

        // ── Arrow buttons ─────────────────────────────────────────
        grid.querySelectorAll('.im-up').forEach(b => {
            b.addEventListener('click', (e) => {
                e.stopPropagation();
                const i = +b.dataset.i;
                if (i > 0) { [modalUrls[i-1], modalUrls[i]] = [modalUrls[i], modalUrls[i-1]]; renderSlots(); }
            });
        });
        grid.querySelectorAll('.im-down').forEach(b => {
            b.addEventListener('click', (e) => {
                e.stopPropagation();
                const i = +b.dataset.i;
                if (i < modalUrls.length - 1) { [modalUrls[i+1], modalUrls[i]] = [modalUrls[i], modalUrls[i+1]]; renderSlots(); }
            });
        });

        // ── Drag to reorder ───────────────────────────────────────
        let dragFrom = null;
        grid.querySelectorAll('.im-card').forEach(card => {
            card.addEventListener('dragstart', e => {
                dragFrom = +card.dataset.idx;
                card.classList.add('im-card-dragging');
                e.dataTransfer.effectAllowed = 'move';
            });
            card.addEventListener('dragend', () => card.classList.remove('im-card-dragging'));
            card.addEventListener('dragover', e => e.preventDefault());
            card.addEventListener('drop', e => {
                e.preventDefault();
                const to = +card.dataset.idx;
                if (dragFrom === null || dragFrom === to) return;
                const moved = modalUrls.splice(dragFrom, 1)[0];
                modalUrls.splice(to, 0, moved);
                dragFrom = null;
                renderSlots();
            });
        });

        updateSelectionUI();
    }

    // ── ADD IMAGES: two-step flow (preview → explicit upload) ───────────────────

    document.getElementById('selAllBtn')?.addEventListener('click', () => {
        modalUrls.forEach(u => selectedUrls.add(u));
        renderSlots();
    });
    document.getElementById('selNoneBtn')?.addEventListener('click', () => {
        selectedUrls.clear();
        renderSlots();
    });

    document.getElementById('modalFileInput')?.addEventListener('change', function () {
        pendingFiles = Array.from(this.files || []);
        const label  = document.getElementById('fileChosenLabel');
        const list   = document.getElementById('uploadPreviewList');
        const items  = document.getElementById('uploadPreviewItems');
        const btn    = document.getElementById('uploadImagesBtn');
        const msg    = document.getElementById('uploadSuccessMsg');

        msg.style.display = 'none';

        if (!pendingFiles.length) {
            label.textContent = 'No file chosen';
            list.style.display = 'none';
            btn.style.display = 'none';
            items.innerHTML = '';
            return;
        }

        label.textContent = pendingFiles.length === 1
            ? pendingFiles[0].name
            : `${pendingFiles.length} files selected`;

        items.innerHTML = '';
        pendingFiles.forEach((f, idx) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const card = document.createElement('div');
                card.className = 'text-center';
                card.style.cssText = 'width:90px;';
                card.innerHTML = `
                    <img src="${esc(e.target.result)}" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;">
                    <div class="small text-truncate mt-1" style="max-width:88px;" title="${esc(f.name)}">${esc(f.name)}</div>`;
                items.appendChild(card);
            };
            reader.readAsDataURL(f);
        });

        list.style.display = 'block';
        btn.style.display  = 'inline-flex';
    });

    document.getElementById('uploadImagesBtn')?.addEventListener('click', async function () {
        const sku = document.getElementById('modalSku').value;
        if (!pendingFiles.length) return;
        if (modalUrls.length >= 12) { toast('Maximum 12 images already added', false); return; }

        const btn     = this;
        const spinner = document.getElementById('uploadSpinner');
        const msg     = document.getElementById('uploadSuccessMsg');

        btn.disabled       = true;
        spinner.style.display = 'inline-block';
        msg.style.display  = 'none';

        try {
            const fd = new FormData();
            fd.append('sku', sku);
            const allowed = 12 - modalUrls.length;
            pendingFiles.slice(0, allowed).forEach(f => fd.append('files[]', f));

            const r = await fetch('/image-master/upload', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: fd,
            });
            const j = await r.json();

            if (j.success && j.urls?.length) {
                // Register DB ids so ✕ can delete from server
                (j.images || []).forEach(img => {
                    storedImageMeta.set(img.url, { id: img.id, name: img.name });
                });
                modalUrls = modalUrls.concat(j.urls).slice(0, 12);
                renderSlots();
                const count = j.urls.length;
                msg.textContent   = `${count} image${count > 1 ? 's' : ''} uploaded successfully!`;
                msg.style.display = 'inline';
                toast(`${count} image${count > 1 ? 's' : ''} uploaded`);
                // Reset file input & preview
                document.getElementById('modalFileInput').value = '';
                document.getElementById('fileChosenLabel').textContent = 'No file chosen';
                document.getElementById('uploadPreviewList').style.display = 'none';
                document.getElementById('uploadPreviewItems').innerHTML = '';
                btn.style.display = 'none';
                pendingFiles = [];
            } else {
                toast(j.message || 'Upload failed', false);
            }
        } catch (e) {
            toast(e.message || 'Upload error', false);
        } finally {
            btn.disabled          = false;
            spinner.style.display = 'none';
        }
    });
    // ── END ADD IMAGES ───────────────────────────────────────────────────────────

    document.getElementById('fetchAmazonBtn')?.addEventListener('click', async () => {
        const sku = document.getElementById('modalSku').value;
        const r = await fetch('/image-master/amazon-images?sku='+encodeURIComponent(sku));
        const j = await r.json();
        if (j.success && j.images?.length) {
            const add = j.images.map(x => typeof x==='string'?x:(x.url||x.locator||'')).filter(Boolean);
            modalUrls = modalUrls.concat(add).slice(0,12);
            renderSlots();
            toast('Amazon images loaded');
        } else toast(j.message || 'No Amazon images', false);
    });

    async function fetchEbay(account) {
        const sku = document.getElementById('modalSku').value;
        const r = await fetch('/image-master/ebay-images?sku='+encodeURIComponent(sku)+'&account='+encodeURIComponent(account));
        const j = await r.json();
        if (j.success && j.images?.length) {
            modalUrls = modalUrls.concat(j.images).slice(0,12);
            renderSlots();
            toast('eBay images loaded');
        } else toast(j.message || 'No eBay images', false);
    }
    document.getElementById('fetchEbay1Btn')?.addEventListener('click', () => fetchEbay('ebay'));
    document.getElementById('fetchEbay2Btn')?.addEventListener('click', () => fetchEbay('ebay2'));
    document.getElementById('fetchEbay3Btn')?.addEventListener('click', () => fetchEbay('ebay3'));

    document.getElementById('savePmBtn')?.addEventListener('click', async () => {
        const sku = document.getElementById('modalSku').value;

        // ── If all images have been removed, also clear every marketplace ──
        if (modalUrls.length === 0) {
            if (!confirm('You have removed all images.\n\nSave to Product Master AND remove images from ALL marketplaces?')) return;

            setPushProgress(true, 'Removing images from all marketplaces…', '');
            const clearUpdates = MARKETPLACES.map(mp => ({ marketplace: mp, images: [] }));
            const progress = [];
            for (const mp of MARKETPLACES) {
                try {
                    const cr = await fetch('/image-master/push', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                        body: JSON.stringify({ sku, mode: 'replace', updates: [{ marketplace: mp, images: [] }] }),
                        signal: AbortSignal.timeout(300000),
                    });
                    const cj = await cr.json();
                    const ok = cj.results?.[mp]?.success ?? cj.success;
                    const msg = cj.results?.[mp]?.message ?? cj.message ?? '';
                    progress.push(`${LABELS[mp]}: ${ok ? 'Cleared' : 'Failed'} ${msg ? '— '+esc(msg) : ''}`);
                } catch (e) {
                    progress.push(`${LABELS[mp]}: Failed — ${esc(e.message || 'error')}`);
                }
                setPushProgress(true, 'Removing images from all marketplaces…', progress.join('<br>'));
            }
            setPushProgress(true, 'Marketplace images cleared. Saving Product Master…', progress.join('<br>'));
        }

        // ── Save to Product Master ──────────────────────────────────────────
        try {
            const r = await fetch('/image-master/save-pm', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ sku, images: modalUrls }),
            });
            const j = await r.json();
            if (j.success) {
                toast(modalUrls.length === 0 ? 'Images cleared from Product Master & all marketplaces' : 'Saved to Product Master');
                loadData();
                if (modalUrls.length === 0 && editModal) editModal.hide();
            } else {
                toast(j.message || 'Save failed', false);
            }
        } catch (e) {
            toast(e.message || 'Save failed', false);
        } finally {
            setTimeout(() => setPushProgress(false, '', ''), 5000);
        }
    });

    // ── Push mode popup ────────────────────────────────────────────────────────
    let pushModeModal;
    if (window.bootstrap?.Modal) {
        pushModeModal = new bootstrap.Modal(document.getElementById('pushModeModal'));
    }

    document.getElementById('pushModalBtn')?.addEventListener('click', () => {
        const sku    = document.getElementById('modalSku').value;
        const checks = Array.from(document.querySelectorAll('.im-mp-chk:checked')).map(c => c.value);
        if (!checks.length) { toast('Select at least one marketplace', false); return; }

        const imagesToPush = selectedUrls.size > 0
            ? modalUrls.filter(u => selectedUrls.has(u))
            : modalUrls;
        if (!imagesToPush.length) { toast('No images to push', false); return; }
        if (!confirmEbay3Push(checks)) return;

        // Show mode-choice popup
        document.getElementById('pmImageCount').textContent = imagesToPush.length;
        document.getElementById('pmMpCount').textContent    = checks.length;
        if (pushModeModal) pushModeModal.show();
    });

    async function doPush(mode) {
        if (pushModeModal) pushModeModal.hide();

        const sku    = document.getElementById('modalSku').value;
        const checks = Array.from(document.querySelectorAll('.im-mp-chk:checked')).map(c => c.value);
        // Always iterate modalUrls to preserve visual grid order — never iterate the Set directly
        const imagesToPush = selectedUrls.size > 0
            ? modalUrls.filter(u => selectedUrls.has(u))   // grid order, selected only
            : [...modalUrls];                               // grid order, all

        const selLabel = selectedUrls.size > 0 ? `${imagesToPush.length} selected` : `all ${imagesToPush.length}`;
        const modeLabel = mode === 'add' ? 'adding to' : 'replacing';

        const progress = [];
        let okCount = 0, failCount = 0, metricsFailCount = 0;
        const updates = checks.map(mp => ({ marketplace: mp, images: imagesToPush }));
        const msTimeout = Math.max(600000, checks.length * 120000);
        try {
            setPushProgress(true, `Pushing ${selLabel} image(s) (${modeLabel} existing) to ${checks.length} marketplace(s)… One request, please wait.`, '');
            const controller = new AbortController();
            const t = setTimeout(() => controller.abort(), msTimeout);
            try {
                const r = await fetch('/image-master/push', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ sku, mode, updates }),
                    signal: controller.signal,
                });
                const rawText = await r.text();
                let j = null;
                try {
                    j = JSON.parse(rawText);
                } catch (parseErr) {
                    progress.push(`Bad response (HTTP ${r.status}). ${esc(rawText.slice(0, 280))}`);
                    failCount = checks.length;
                }
                if (j && !r.ok && (j.message || j.error)) {
                    progress.unshift(`${esc(String(j.message || j.error))} (HTTP ${r.status})`);
                } else if (j && !r.ok) {
                    progress.unshift(`HTTP ${r.status}`);
                }
                if (j && j.results) {
                okCount = 0;
                failCount = 0;
                metricsFailCount = 0;
                let idx = 0;
                for (const mp of checks) {
                    idx++;
                    const row = j.results[mp] ? j.results[mp] : null;
                    const rowOk = !!(row && row.success);
                    if (rowOk) { okCount++; } else { failCount++; }
                    if (row && rowOk && !row.metrics_saved) { metricsFailCount++; }
                    progress.push(`${idx}/${checks.length} ${LABELS[mp]}: ${rowOk ? 'OK' : 'Failed'}${row && row.message ? ` - ${esc(row.message)}` : ''}`);
                }
                } else if (j && failCount === 0) {
                    failCount = checks.length;
                }
            } catch (e) {
                failCount = checks.length;
                const txt = (e?.name === 'AbortError') ? `Request timed out after ${Math.round(msTimeout/1000)}s` : (e.message || 'Request failed');
                progress.push(`Batch failed: ${esc(txt)}`);
            } finally {
                clearTimeout(t);
            }
            setPushProgress(true, `Push finished: ${okCount} success, ${failCount} failed${metricsFailCount ? `, ${metricsFailCount} metrics save failed` : ''}`, progress.join('<br>'));
            if (failCount === 0) {
                toast(`Pushed to ${okCount} marketplace(s).${metricsFailCount ? ` ${metricsFailCount} metrics save failed.` : ''}`, !metricsFailCount);
                loadData();
                if (editModal) editModal.hide();
            } else {
                toast(`Push completed with failures (${failCount}). See progress details.`, false);
                loadData();
            }
        } finally {
            setTimeout(() => setPushProgress(false, '', ''), 5000);
        }
    }

    document.getElementById('pmReplaceBtn')?.addEventListener('click', () => doPush('replace'));
    document.getElementById('pmAddBtn')?.addEventListener('click', ()     => doPush('add'));
    // ── End push mode popup ────────────────────────────────────────────────────

    function quickPush(sku, mp) {
        const row = bySku.get(String(sku));
        if (!row) return;
        const urls = pmImageUrls(row);
        if (!urls.length) { toast('No images on Product Master for this SKU — open Edit first.', false); openEditModal(sku); return; }
        if (!confirmEbay3Push([mp])) return;
        setPushProgress(true, `Pushing images to 1 marketplace... This may take 1-2 minutes`, `1/1 ${LABELS[mp]}: in progress`);
        fetch('/image-master/push', {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken,'Accept':'application/json'},
            body: JSON.stringify({ sku, mode: 'replace', updates: [{ marketplace: mp, images: urls }] }),
        }).then(r=>r.json()).then(j => {
            const rowRes = (j.results && j.results[mp]) ? j.results[mp] : null;
            const ok = !!(rowRes && rowRes.success);
            const msg = rowRes?.message || j.message || (ok ? 'Updated' : 'Failed');
            setPushProgress(true, `Push finished: ${ok ? '1 success' : '1 failed'}`, `1/1 ${LABELS[mp]}: ${ok ? 'OK' : 'Failed'} - ${esc(msg)}`);
            if (ok) { toast(LABELS[mp]+' pushed'); loadData(); }
            else toast(msg, false);
        }).catch(e => {
            setPushProgress(true, 'Push finished: 1 failed', `1/1 ${LABELS[mp]}: Failed - ${esc(e.message || 'Request failed')}`);
            toast(e.message, false);
        }).finally(() => setTimeout(() => setPushProgress(false, '', ''), 4000));
    }

    document.getElementById('skuSearchIm')?.addEventListener('input', () => renderTable(tableData));

    document.getElementById('exportBtn')?.addEventListener('click', () => {
        const rows = tableData.map(row => {
            const o = { SKU: row.SKU, Product: row.Parent||'', Preview: row.preview_thumb||'' };
            const im = row.image_master||{};
            MARKETPLACES.forEach(mp => { o[LABELS[mp]] = im[mp]||''; });
            return o;
        });
        const ws = XLSX.utils.json_to_sheet(rows);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Image Master');
        XLSX.writeFile(wb, 'image_master_'+new Date().toISOString().split('T')[0]+'.xlsx');
    });

    document.getElementById('importBtn')?.addEventListener('click', () => document.getElementById('importFile').click());
    document.getElementById('importFile')?.addEventListener('change', function(e) {
        const f = e.target.files?.[0];
        if (!f) return;
        const reader = new FileReader();
        reader.onload = function(ev) {
            try {
                const wb = XLSX.read(new Uint8Array(ev.target.result), { type:'array' });
                const json = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]]);
                json.forEach(row => {
                    const sku = String(row.SKU||'').trim();
                    if (!sku) return;
                    const item = bySku.get(sku);
                    if (!item) return;
                    if (!item.image_master) item.image_master = {};
                    MARKETPLACES.forEach(mp => {
                        const k = LABELS[mp];
                        if (row[k] != null) item.image_master[mp] = String(row[k]);
                    });
                });
                renderTable(tableData);
                toast('Import merged into table (save/push from Edit modal)');
            } catch (err) { toast('Import failed', false); }
        };
        reader.readAsArrayBuffer(f);
        e.target.value = '';
    });

    document.getElementById('pushSelectedBtn')?.addEventListener('click', () => toast('Select rows in a future update, or use Edit → Push selected', false));
    document.getElementById('pushAllBtn')?.addEventListener('click', () => {
        if (!confirm('Push Product Master images to ALL marketplaces for ALL loaded products? This may take a long time.')) return;
        toast('Bulk push: use per-row icons or extend with row selection.', false);
    });

    if (window.bootstrap?.Modal) {
        editModal = new bootstrap.Modal(document.getElementById('editImModal'));
    }
    document.getElementById('mpSelAllBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('#editImModal .im-mp-chk').forEach(c => { c.checked = true; });
    });
    document.getElementById('mpSelNoneBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('#editImModal .im-mp-chk').forEach(c => { c.checked = false; });
    });
    loadData();
});
</script>
@endsection

