@extends('layouts.vertical', ['title' => 'Image Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    @include('partials.marketplace-master-button-colors')
    <style>
        .card.im-master-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .table-responsive { position:relative; border:1px solid #e2e8f0; border-radius:10px; max-height:640px; overflow:auto; background:#fff; }
        #im-master-table thead th { position:sticky; top:0; vertical-align:middle!important; background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%)!important; color:#fff; z-index:10; padding:6px 8px; font-size:10px; font-weight:600; text-transform:uppercase; }
        #im-master-table tbody td { padding:8px 10px; vertical-align:middle!important; border-bottom:1px solid #edf2f9; font-size:11px; }
        .im-thumb { width:56px; height:56px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0; background:#f8fafc; }
        .im-img-count { font-size:12px; font-weight:700; background:none; white-space:nowrap; }
        .im-img-count.ok { color:#16a34a; }
        .im-img-count.low { color:#dc2626; }
        .marketplaces-cell { vertical-align:middle!important; }
        .bp-mp-inline { display:flex; flex-wrap:wrap; align-items:flex-end; gap:6px; justify-content:flex-start; min-width:120px; }
        .bp-mp-th-title { font-weight:600; letter-spacing:0.2px; }
        .bp-mp-th-icons { display:flex; flex-wrap:wrap; gap:4px; margin-top:6px; justify-content:center; align-items:center; }
        .bp-mp-th-pill { width:22px; height:22px; border-radius:4px; font-size:8px; font-weight:700; color:#fff; display:inline-flex; align-items:center; justify-content:center; line-height:1; }
        .bp-mp-stack { display:flex; flex-direction:column; align-items:center; gap:3px; border:none; background:transparent; padding:0; cursor:pointer; }
        .bp-mp-stack:hover .marketplace-btn:not(:disabled) { transform:translateY(-1px); box-shadow:0 2px 6px rgba(0,0,0,.18); }
        .bp-mp-stack:disabled { cursor:not-allowed; opacity:.45; filter:grayscale(.7); }
        .bp-mp-dot { width:10px; height:10px; border-radius:50%; border:2px solid #94a3b8; background:transparent; transition:background .15s,border-color .15s; flex-shrink:0; }
        .bp-mp-dot.pushed { background:#22c55e; border-color:#22c55e; }
        .bp-mp-dot.failed { background:#ef4444; border-color:#ef4444; }
        .marketplace-btn { width:28px; height:28px; border:none; border-radius:4px; color:#fff; font-weight:600; font-size:11px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all .2s; padding:0; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        /* ── Image card grid ─────────────────────────────────────── */
        .im-grid { display:flex; flex-wrap:wrap; gap:10px; min-height:40px; padding:4px 0; }
        .im-card { width:120px; border:2px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.06); transition:box-shadow .15s,border-color .15s; user-select:none; position:relative; }
        .im-card:hover { box-shadow:0 3px 12px rgba(44,110,213,.18); border-color:#93c5fd; }
        .im-card.im-card-dragging { opacity:.45; border:2px dashed #6366f1; }
        .im-card-img-wrap { position:relative; width:120px; height:100px; background:#f1f5f9; overflow:hidden; }
        .im-card-img-wrap img { width:100%; height:100%; object-fit:cover; display:block; }
        /* Edit product images modal: full height, top to bottom */
        #editImModal .modal-dialog { --bs-modal-margin:0; height:100vh; margin:0 auto; }
        #editImModal .modal-content { height:100vh; max-height:100vh; border-radius:0; }
        /* Hero 1 / Hero 2 cards: 1.5x size in the top row */
        .im-grid-hero { gap:14px; margin-bottom:6px; }
        .im-card.im-card-hero { width:180px; }
        .im-card.im-card-hero .im-card-img-wrap { width:180px; height:150px; }
        .im-card.im-card-hero .im-card-name { max-width:162px; font-size:13px; }
        .im-card.im-card-hero .im-card-filename { max-width:162px; font-size:10px; }
        .im-card.im-card-hero .im-card-arrows button { font-size:13px; padding:3px 0; }
        .im-card-badge { position:absolute; top:4px; left:4px; background:rgba(0,0,0,.55); color:#fff; border-radius:4px; font-size:9px; font-weight:700; padding:1px 5px; line-height:1.5; pointer-events:none; z-index:3; }
        .im-card-del { position:absolute; top:4px; right:4px; background:rgba(220,38,38,.88); border:none; color:#fff; border-radius:50%; width:22px; height:22px; font-size:11px; cursor:pointer; display:none; align-items:center; justify-content:center; padding:0; line-height:1; transition:background .12s; z-index:5; }
        .im-card-del:hover { background:#b91c1c; }
        .im-card:hover .im-card-del { display:flex; }
        .im-card-footer { padding:4px 6px 5px; }
        .im-card-name { font-size:11px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:108px; font-weight:600; }
        .im-card-filename { font-size:9px; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:108px; margin-top:1px; }
        .im-card-arrows { display:flex; gap:3px; margin-top:3px; }
        .im-card-arrows button { flex:1; border:1px solid #e2e8f0; background:#f8fafc; border-radius:4px; font-size:11px; cursor:pointer; padding:1px 0; color:#475569; transition:background .1s; }
        .im-card-arrows button:hover { background:#e0e7ff; color:#4338ca; }
        .im-card.is-main-default { border-color:#22c55e; box-shadow:0 0 0 2px rgba(34,197,94,.25); }
        .im-card-badge-main { background:#16a34a; }
        .im-main-mp-section { border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; background:#f8fafc; }
        .im-main-mp-row { display:flex; align-items:center; gap:8px; margin-bottom:6px; }
        .im-main-mp-row label { min-width:92px; font-size:11px; font-weight:600; margin:0; color:#334155; }
        .im-main-mp-row select { flex:1; min-width:0; font-size:11px; padding:2px 6px; }
        /* stored-image badge tint */
        .im-card.is-stored .im-card-img-wrap { border-bottom:2px solid #6366f1; }
        /* pending upload preview */
        .im-pending-card { width:90px; position:relative; }
        .im-pending-img-wrap { position:relative; display:inline-block; }
        .im-pending-del { position:absolute; top:2px; right:2px; background:rgba(220,38,38,.88); border:none; color:#fff; border-radius:50%; width:20px; height:20px; font-size:10px; cursor:pointer; display:flex; align-items:center; justify-content:center; padding:0; line-height:1; z-index:2; }
        .im-pending-del:hover { background:#b91c1c; }
        /* selection bar */
        .im-select-bar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:6px; }
        .im-select-info { font-size:11px; font-weight:600; color:#6366f1; background:#eef2ff; border-radius:5px; padding:2px 8px; }
        /* ─────────────────────────────────────────────────────────── */
        .toast-container { z-index:1100; }
        .shopify-row-pull-btn { background:#f59e0b; color:#fff; border:none; padding:5px 8px; border-radius:4px; }
        .shopify-row-pull-btn:hover { background:#d97706; color:#fff; }
        .im-hist { display:inline-flex; flex-direction:column; gap:2px; cursor:help; white-space:nowrap; font-size:10px; line-height:1.25; }
        .im-hist-date { font-weight:600; color:#334155; }
        .im-hist-counts .ok { color:#16a34a; font-weight:700; }
        .im-hist-counts .bad { color:#dc2626; font-weight:700; }
        #imHistoryPop { position:fixed; z-index:2000; display:none; width:320px; max-height:420px; overflow:auto; background:#fff; border:1px solid #cbd5e1; border-radius:8px; box-shadow:0 8px 24px rgba(15,23,42,.18); padding:10px 12px; font-size:11px; color:#0f172a; pointer-events:none; }
        .im-hist-act { font-weight:700; color:#2563eb; }
        #imHistoryPop .hp-title { font-weight:700; font-size:12px; margin-bottom:4px; }
        #imHistoryPop .hp-meta { color:#475569; margin-bottom:6px; }
        #imHistoryPop .hp-section { font-weight:700; margin:6px 0 3px; text-transform:uppercase; font-size:9px; letter-spacing:.3px; color:#64748b; }
        #imHistoryPop .hp-row { display:flex; gap:6px; align-items:flex-start; padding:1px 0; }
        #imHistoryPop .hp-row .mk { min-width:16px; font-weight:700; }
        #imHistoryPop .hp-row .mk.ok { color:#16a34a; }
        #imHistoryPop .hp-row .mk.bad { color:#dc2626; }
        #imHistoryPop .hp-row .nm { font-weight:600; min-width:92px; }
        #imHistoryPop .hp-row .msg { color:#64748b; white-space:normal; word-break:break-word; }
        .im-slot-th { min-width:84px; text-align:center; }
        .im-slot-th .im-slot-name { width:72px; max-width:100%; text-align:center; background:transparent; border:1px solid transparent; color:#fff; font-size:10px; font-weight:700; text-transform:uppercase; padding:1px 3px; border-radius:4px; }
        .im-slot-th .im-slot-name:hover, .im-slot-th .im-slot-name:focus { background:rgba(255,255,255,.18); border-color:rgba(255,255,255,.6); outline:none; }
        .im-slot-th .im-slot-name::placeholder { color:rgba(255,255,255,.7); }
        .im-slot-thumb { width:56px; height:56px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0; background:#f8fafc; }
        .im-slot-cell { position:relative; display:inline-block; line-height:0; }
        .im-ai-edit-btn { position:absolute; right:-6px; bottom:-6px; width:22px; height:20px; padding:0; display:inline-flex; align-items:center; justify-content:center; font-size:9px; line-height:1; color:#475569; background:#fff; border:1px solid #94a3b8; border-radius:5px; cursor:pointer; box-shadow:0 1px 3px rgba(0,0,0,.2); }
        .im-ai-edit-btn:hover { background:#2563eb; border-color:#2563eb; color:#fff; }
        .ai-edit-pane { border:1px solid #e2e8f0; border-radius:10px; padding:8px; text-align:center; background:#f8fafc; min-height:260px; display:flex; flex-direction:column; align-items:center; justify-content:center; }
        .ai-edit-pane img { max-width:100%; max-height:340px; object-fit:contain; border-radius:6px; background:#fff; }
        .ai-edit-thumbs { display:flex; flex-wrap:wrap; gap:6px; justify-content:center; margin-top:8px; }
        .ai-edit-thumbs img { width:64px; height:64px; object-fit:cover; border-radius:6px; border:2px solid #e2e8f0; cursor:pointer; background:#fff; }
        .ai-edit-thumbs img.sel { border-color:#16a34a; box-shadow:0 0 0 2px rgba(22,163,74,.25); }
        .ai-edit-pane .ai-edit-cap { font-size:11px; font-weight:700; color:#475569; margin-bottom:6px; text-transform:uppercase; }
        .im-prompts-menu .ip-row { display:flex; align-items:center; gap:10px; padding:4px 6px; border-bottom:1px solid #f1f5f9; }
        .im-prompts-menu .ip-row:last-child { border-bottom:none; }
        .im-prompts-menu .ip-name { flex:1; font-size:12px; font-weight:600; color:#334155; white-space:nowrap; }
        .im-prompts-menu .ip-dot { width:8px; height:8px; border-radius:50%; background:#22c55e; display:inline-block; margin-left:6px; }
        .hero-row { display:grid; grid-template-columns:150px 1fr 1fr 74px; gap:8px; align-items:center; padding:4px 0; border-bottom:1px solid #f1f5f9; font-size:12px; }
        .hero-row-head { font-weight:700; font-size:10px; text-transform:uppercase; color:#64748b; border-bottom:2px solid #e2e8f0; }
        .hero-row-all { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:6px 8px; }
        .hero-mp { font-weight:600; color:#334155; }
        .hero-thumbs { display:flex; gap:4px; }
        .hero-thumbs img { width:32px; height:32px; object-fit:cover; border-radius:4px; border:1px solid #e2e8f0; background:#f8fafc; }
        .im-icon-expand { display:inline-flex; align-items:center; }
        .im-icon-expand .im-icon-expand-text { max-width:0; overflow:hidden; white-space:nowrap; opacity:0; margin-left:0; transition:max-width .25s ease, opacity .2s ease, margin-left .25s ease; }
        .im-icon-expand:hover .im-icon-expand-text, .im-icon-expand:focus-visible .im-icon-expand-text { max-width:140px; opacity:1; margin-left:6px; }
        .im-col-hidden { display:none!important; }
        .im-mp-open { width:30px; height:30px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:50%; }
        .mpg-wrap { display:flex; gap:12px; align-items:flex-start; overflow-x:auto; padding-bottom:6px; }
        .mpg-col { flex:0 0 210px; border:1px solid #e2e8f0; border-radius:10px; background:#f8fafc; }
        .mpg-col.mpg-over { border-color:#6366f1; box-shadow:0 0 0 2px rgba(99,102,241,.2); }
        .mpg-head { display:flex; align-items:center; gap:6px; padding:8px; border-bottom:1px solid #e2e8f0; background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color:#fff; border-radius:9px 9px 0 0; }
        .mpg-head .mpg-name-input { flex:1; min-width:0; background:transparent; border:1px solid transparent; color:#fff; font-size:11px; font-weight:700; text-transform:uppercase; padding:1px 4px; border-radius:4px; }
        .mpg-head .mpg-name-input:focus { background:rgba(255,255,255,.18); border-color:rgba(255,255,255,.6); outline:none; }
        .mpg-head .mpg-count { font-size:10px; opacity:.85; white-space:nowrap; }
        .mpg-head .mpg-del { background:none; border:none; color:#fff; opacity:.75; padding:0 2px; cursor:pointer; font-size:12px; }
        .mpg-head .mpg-del:hover { opacity:1; }
        .mpg-list { min-height:56px; padding:8px; display:flex; flex-direction:column; gap:5px; }
        .mpg-list:empty::after { content:'Drop marketplaces here'; color:#94a3b8; font-size:11px; text-align:center; padding:14px 0; }
        .mpg-item { display:flex; align-items:center; gap:6px; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:4px 6px; cursor:grab; font-size:11px; margin:0; }
        .mpg-item.dragging { opacity:.4; }
        .mpg-item.disabled { opacity:.55; cursor:grab; }
        .mpg-item .marketplace-btn { width:26px; height:22px; font-size:9px; cursor:inherit; flex-shrink:0; }
        .mpg-item .mpg-lbl { flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:600; color:#334155; }
        .mpg-add-bar { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
        #im-master-table thead th[data-sort] { cursor:pointer; user-select:none; white-space:nowrap; }
        #im-master-table thead th[data-sort]:hover { filter:brightness(1.12); }
        #im-master-table thead th .im-sort-ind { display:inline-block; margin-left:4px; font-size:9px; opacity:.45; }
        #im-master-table thead th.im-sorted .im-sort-ind { opacity:1; }
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
                        <button type="button" id="heroImageBtn" class="btn btn-warning btn-sm" title="Choose Hero 1 / Hero 2 per marketplace for the selected rows (or all rows in view if none selected)"><i class="fas fa-star"></i> Hero Image</button>
                        <div class="dropdown" id="promptsDropdown">
                            <button type="button" id="promptsBtn" class="btn btn-dark btn-sm dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><i class="fas fa-wand-magic-sparkles"></i> Prompts</button>
                            <div class="dropdown-menu im-prompts-menu p-2" id="promptsMenu" style="min-width:260px;max-height:70vh;overflow:auto;"></div>
                        </div>
                        <button type="button" id="pushSelectedBtn" class="btn btn-secondary btn-sm im-icon-expand" title="Push Selected" aria-label="Push Selected"><i class="fas fa-cloud-upload-alt"></i><span class="im-icon-expand-text">Push Selected</span></button>
                        <button type="button" id="pushAllBtn" class="btn btn-warning btn-sm"><i class="fas fa-cloud-upload-alt"></i> Push ALL to All Marketplaces</button>
                        <span class="text-muted small" id="rowCountBadge">0 products</span>
                        <span class="text-muted small" id="selectedCountBadge">0 selected</span>
                    </div>
                    <div id="pushProgressBox" class="alert alert-info mb-3 py-2 px-3" style="display:none;word-break:break-word;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="spinner-border spinner-border-sm text-primary" id="pushSpinner" role="status"></div>
                            <div class="small fw-semibold flex-grow-1" id="pushProgressTitle">Pushing...</div>
                            <button type="button" class="btn-close btn-sm" id="pushProgressClose" style="font-size:10px;" onclick="document.getElementById('pushProgressBox').style.display='none'"></button>
                        </div>
                        <div class="progress mt-2" id="pushProgressBarWrap" style="height:16px;display:none;">
                            <div id="pushProgressBar" class="progress-bar bg-info" role="progressbar" style="width:0%;font-size:10px;line-height:16px;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="small mt-2" id="pushProgressDetails" style="max-height:200px;overflow-y:auto;white-space:pre-wrap;"></div>
                    </div>
                    <div class="table-responsive">
                        <table id="im-master-table" class="table w-100">
                            <thead>
                                <tr>
                                    <th style="width:36px;text-align:center;" data-sort="selected" title="Sort by selected">
                                        <input type="checkbox" class="form-check-input" id="selectAllRowsIm" title="Select all visible rows" aria-label="Select all visible rows">
                                    </th>
                                    <th data-sort="sku">SKU <input type="text" id="skuSearchIm" class="form-control form-control-sm mt-1" placeholder="Search"></th>
                                    <th style="width:72px;text-align:center;" data-sort="images" title="Saved Product Master images for this SKU">Images</th>
                                    <th data-sort="name">Product Name</th>
                                    <th data-sort="history" title="Hover a row's History to see the last image push">History</th>
                                    <th id="imActionTh">Action</th>
                                    <th style="width:54px;text-align:center;" data-sort="mp" title="Open marketplaces picker (groups, checkboxes, push)">MP</th>
                                    <th class="im-col-hidden" title="eBay1–3, Macy's, Amz, Temu, Reverb, Wayfair, Best Buy">
                                        <div class="bp-mp-th-title">MARKET PLACES</div>
                                        <div class="bp-mp-th-icons">
                                            <span class="bp-mp-th-pill btn-ebay1">E1</span><span class="bp-mp-th-pill btn-ebay2">E2</span><span class="bp-mp-th-pill btn-ebay3">E3</span><span class="bp-mp-th-pill btn-macy">M</span><span class="bp-mp-th-pill btn-amazon">A</span><span class="bp-mp-th-pill btn-temu">T</span><span class="bp-mp-th-pill btn-reverb">R</span><span class="bp-mp-th-pill btn-wayfair">W</span><span class="bp-mp-th-pill btn-bestbuy">B</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
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
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-images me-2"></i>Edit product images</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalSku">
                    <div class="mb-2"><strong>SKU:</strong> <span id="modalSkuLabel"></span></div>
                    <div class="mb-3"><strong>Product:</strong> <span id="modalProductLabel"></span></div>

                    {{-- ── ADD IMAGES SECTION ─────────────────────────────────── --}}
                    <div class="border rounded p-3 mb-3" style="background:#f8fafc;">
                        <div class="fw-semibold small mb-2"><i class="fas fa-folder-open me-1"></i>Upload Images</div>
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                            <label class="btn btn-outline-primary btn-sm mb-0" for="modalFileInput" style="cursor:pointer;">
                                <i class="fas fa-image me-1"></i>Choose Files
                            </label>
                            <input type="file" class="d-none" id="modalFileInput" accept=".jpg,.jpeg,.png,.webp" multiple>
                            <span class="text-muted small" id="fileChosenLabel">No file chosen</span>
                        </div>
                        {{-- Pre-upload preview list --}}
                        <div id="uploadPreviewList" class="mb-2" style="display:none;">
                            <div class="small text-muted mb-1">Selected files will be uploaded when you click <strong>Add to list</strong> or <strong>Save Images</strong>.</div>
                            <div id="uploadPreviewItems" class="d-flex flex-wrap gap-2"></div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-success btn-sm" id="uploadImagesBtn" style="display:none;">
                                <i class="fas fa-plus me-1"></i>Add to list
                            </button>
                            <div class="spinner-border spinner-border-sm text-success" id="uploadSpinner" role="status" style="display:none;"></div>
                            <span class="small text-success fw-semibold" id="uploadSuccessMsg" style="display:none;"></span>
                            <span class="small text-muted">Max 20 images. Drag cards to reorder before saving.</span>
                        </div>
                    </div>
                    {{-- ── END ADD IMAGES ──────────────────────────────────────── --}}

                    <!-- <div class="mb-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchAmazonBtn"><i class="fab fa-amazon"></i> Fetch Amz images</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchEbay1Btn"><i class="fab fa-ebay"></i> Fetch eBay1</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchEbay2Btn">eBay2</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchEbay3Btn">eBay3</button>
                    </div> -->
                    <div class="fw-semibold small mb-1 d-flex align-items-center justify-content-between gap-2 flex-wrap">
                        <span>Order (drag to reorder)</span>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="downloadAllImagesBtn" style="display:none;" title="Download all images of this SKU in the shown order as a ZIP file">
                                <i class="fas fa-download me-1"></i>Download all (ZIP)
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="removeAllImagesBtn" style="display:none;">
                                <i class="fas fa-trash-alt me-1"></i>Remove all
                            </button>
                            <span class="text-muted small">Drag cards to reorder</span>
                        </div>
                    </div>
                    <div id="imSlots"></div>
                    <div class="alert alert-info small mt-3 mb-0">
                        Save images here first. Use the <strong>Hero Image</strong> button in the toolbar to choose Hero 1 / Hero 2 per marketplace, and the MP button in the table row to push.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="savePmBtn"><i class="fas fa-save"></i> Save Images</button>
                </div>
            </div>
        </div>
    </div>

    </div>

    <div class="modal fade" id="shopifyPullModal" tabindex="-1" aria-labelledby="shopifyPullModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="shopifyPullModalTitle"><i class="fas fa-download me-2"></i>Shopify Image Pull</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning small mb-3">
                        This imports current product images from Shopify into Product Master only. It does not push anything back to Shopify.
                    </div>
                    <div class="small text-muted mb-2" id="shopifyPullScopeText">Scope: currently filtered SKUs.</div>
                    <div id="shopifyPullPanel" class="border rounded bg-light p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>Progress</strong>
                            <span class="small text-muted" id="shopifyPullStatus">Ready</span>
                        </div>
                        <div class="progress mb-3" style="height: 12px;">
                            <div id="shopifyPullProgress" class="progress-bar bg-warning" role="progressbar" style="width:0%"></div>
                        </div>
                        <div id="shopifyPullLog" class="small font-monospace bg-white border rounded p-2" style="max-height:280px; overflow:auto;"></div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="startShopifyPullBtn" class="btn btn-warning"><i class="fas fa-play"></i> Run in BG</button>
                    <button type="button" id="pauseShopifyPullBtn" class="btn btn-outline-warning" style="display:none;"><i class="fas fa-pause"></i> Pause</button>
                    <button type="button" id="resumeShopifyPullBtn" class="btn btn-outline-success" style="display:none;"><i class="fas fa-play"></i> Resume</button>
                    <button type="button" id="stopShopifyPullBtn" class="btn btn-outline-danger" style="display:none;"><i class="fas fa-stop"></i> Stop</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="shopifyPullConfirmModal" tabindex="-1" aria-labelledby="shopifyPullConfirmTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning-subtle">
                    <h5 class="modal-title" id="shopifyPullConfirmTitle"><i class="fas fa-triangle-exclamation me-2"></i>Confirm Shopify Pull</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2" id="shopifyPullConfirmScope">Do you want to pull images from Shopify?</p>
                    <div class="alert alert-warning small mb-0">
                        This action will update the existing Product Master image fields. It will not update Shopify.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="shopifyPullConfirmCancelBtn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="shopifyPullConfirmBtn"><i class="fas fa-download"></i> Yes, Pull Images</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── AI edit modal (Nano Banana) ────────────────────────────── --}}
    <div class="modal fade" id="aiEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-pen me-2"></i>Edit <span id="aiEditName"></span> — <span id="aiEditSku"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="ai-edit-pane"><div class="ai-edit-cap">Current image</div><div id="aiEditOriginal"></div></div>
                        </div>
                        <div class="col-md-6">
                            <div class="ai-edit-pane"><div class="ai-edit-cap">New image (Nano Banana)</div><div id="aiEditResult"><span class="text-muted small">Click <strong>Prompt</strong> to generate.</span></div></div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label small fw-semibold mb-1">Prompt for this image</label>
                        <textarea id="aiEditPrompt" class="form-control" rows="4" placeholder="Describe how to change this image…"></textarea>
                        <div id="aiEditMsg" class="small mt-2"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" id="aiEditSavePromptBtn"><i class="fas fa-save me-1"></i>Save prompt</button>
                    <div class="d-flex align-items-center gap-1" title="How many images to generate each time">
                        <label for="aiEditCount" class="small text-muted mb-0">Images</label>
                        <select id="aiEditCount" class="form-select form-select-sm" style="width:64px;"><option>1</option><option>2</option><option>3</option><option>4</option></select>
                    </div>
                    <button type="button" class="btn btn-primary" id="aiEditGenerateBtn" title="Generate new image(s) from this prompt"><i class="fas fa-wand-magic-sparkles me-1"></i><span id="aiEditGenLabel">Prompt</span></button>
                    <button type="button" class="btn btn-success" id="aiEditApplyBtn" style="display:none;"><i class="fas fa-check me-1"></i>Use selected image (overwrite)</button>
                    <button type="button" class="btn btn-secondary ms-auto" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Slot prompt modal ──────────────────────────────────────── --}}
    <div class="modal fade" id="slotPromptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-wand-magic-sparkles me-2"></i>Prompt — <span id="slotPromptName"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <textarea id="slotPromptText" class="form-control" rows="10" placeholder="Write the prompt for this image…"></textarea>
                    <div id="slotPromptMsg" class="small mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary me-auto" id="slotPromptCopyBtn"><i class="fas fa-copy me-1"></i>Copy</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="slotPromptSaveBtn"><i class="fas fa-save me-1"></i>Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Hero image modal ───────────────────────────────────────── --}}
    <div class="modal fade" id="heroImageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-star me-2"></i>Hero Image — <span id="heroSkuLabel"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">When you push, <strong>Hero 1</strong> is sent first (main image) and <strong>Hero 2</strong> second on each marketplace. Hero 2 &ldquo;Auto&rdquo; keeps the natural order. Default is Image 1.</p>
                    <div id="heroScopeNote" class="alert alert-warning small py-2 mb-3" style="display:none;"></div>
                    <div class="hero-row hero-row-all mb-2">
                        <span class="hero-mp fw-bold">Apply to all marketplaces</span>
                        <select id="heroAllMain" class="form-select form-select-sm"></select>
                        <select id="heroAllHero2" class="form-select form-select-sm"></select>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="heroApplyAllBtn">Apply</button>
                    </div>
                    <div class="hero-row hero-row-head">
                        <span class="hero-mp">Marketplace</span><span>Hero image 1</span><span>Hero image 2</span><span></span>
                    </div>
                    <div id="heroRows"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary me-auto" id="heroResetBtn">Reset all</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="heroSaveBtn"><i class="fas fa-save"></i> Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── MP picker modal (groups, drag & drop, checkboxes) ───────── --}}
    <div class="modal fade" id="mpPickerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-search me-2"></i>Marketplaces — <span id="mpPickerSku"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mpg-add-bar mb-3">
                        <input type="text" id="mpgNewName" class="form-control form-control-sm" style="max-width:220px;" placeholder="New column name">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="mpgAddBtn"><i class="fas fa-plus me-1"></i>Add column</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="mpgResetBtn" title="Restore default groups">Reset layout</button>
                        <span class="text-muted small ms-auto">Drag marketplaces between columns to regroup. Green dot = already pushed.</span>
                    </div>
                    <div id="mpgWrap" class="mpg-wrap"></div>
                </div>
                <div class="modal-footer">
                    <span class="me-auto small text-muted" id="mpgSelCount">0 selected</span>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="mpgPushBtn"><i class="fas fa-cloud-upload-alt"></i> Push selected</button>
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
                    <p class="small text-warning mb-0 mt-2" id="pmAddHint" style="display:none;">
                        Add is only available when every selected marketplace is Shopify Main, Shopify PLS, or Reverb.
                    </p>
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
    <div id="imHistoryPop" role="tooltip"></div>
@endsection

@section('script')
@include('partials.marketplace-api-config')
@include('partials.all-marketplace-master-channels', ['allMpMaster' => 'image'])
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const __mp = window.__ALL_MP__ || {};
    const MARKETPLACES = __mp.marketplaces || [];
    const ENABLED_MARKETPLACES = __mp.enabled || [];
    const LABELS = __mp.labels || {};
    const MP_TILE = {};
    const MP_SHORT = {};
    Object.entries(__mp.tiles || {}).forEach(([k, v]) => {
        MP_TILE[k] = v.cls;
        MP_SHORT[k] = v.short;
    });
    const GROUPS = __mp.groups || { gChannels: [], gShopify: [] };
    const ADD_MODE_MARKETPLACES = ['shopify_main', 'shopify_pls', 'reverb'];
    const SHOPIFY_MARKETPLACES = ['shopify_main', 'shopify_pls'];
    const PM_MAX_IMAGES = 20;
    const MP_IMAGE_LIMITS = {
        ebay: 12, ebay2: 12, ebay3: 12, amazon: 9, temu: 12, temu2: 12, wayfair: 12, bestbuy: 12, macy: 12, reverb: 25,
        doba: 12, walmart: 12, faire: 12, shein: 12, aliexpress: 12,
        shopify_main: 20, shopify_pls: 20,
    };
    const EBAY3_WARN = 'eBay3 has different listing structure. Please verify images before pushing.';

    let tableData = [];
    let visibleFilteredRows = [];
    const bySku = new Map();
    const selectedSkus = new Set();
    let editModal;
    let shopifyPullModal;
    let shopifyPullConfirmModal;
    let shopifyPullPollTimer = null;
    let shopifyPullSelectedSkus = null;
    let shopifyPullConfirmResolver = null;
    /** Remaining SKU chunks queued after the active background pull job. */
    let shopifyPullChunkQueue = [];
    let shopifyPullChunkMeta = { totalSkus: 0, chunkIndex: 0, chunkCount: 0, ok: 0, fail: 0, cancelled: false };
    let shopifyPullAdvancingChunk = false;
    const SHOPIFY_PULL_CHUNK_SIZE = 25;
    let modalUrls = [];
    let knownImageUrls = []; // every image URL seen this modal session (loaded + uploaded/fetched), to detect removals on Save
    let modalMainByMp = {};
    let modalHero2ByMp = {};   // mp -> image index (absent = automatic)
    let pendingFiles = [];
    let selectedUrls = new Set();   // URLs checked for push

    // Encode URL so spaces/special chars load correctly in <img src>.
    // Local storage URLs should load from the same host/port the user has open.
    const imgSrc = url => {
        try {
            const parsed = new URL(String(url), window.location.origin);
            if ((parsed.hostname === 'localhost' || parsed.hostname === '127.0.0.1') && parsed.pathname.startsWith('/storage/')) {
                return window.location.origin + encodeURI(decodeURIComponent(parsed.pathname)) + parsed.search;
            }
            return encodeURI(decodeURIComponent(String(url)));
        } catch(_) {
            return encodeURI(String(url));
        }
    };

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

    function pushFailureMessage(j, mp, httpStatus) {
        const rowRes = (j && j.results && j.results[mp]) ? j.results[mp] : null;
        if (rowRes?.message) return String(rowRes.message);
        if (j?.message) return String(j.message);
        if (rowRes && rowRes.success === false) {
            return `${LABELS[mp] || mp} push failed (no error detail). Check server logs.`;
        }
        if (httpStatus) return `Request failed (HTTP ${httpStatus}).`;
        return 'Push failed.';
    }

    function isImagePushJobActive(status) {
        return status === 'running';
    }

    function sleepMs(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    async function fetchImagePushJobStatus() {
        const res = await fetch('/image-master/push/status', { headers: { 'Accept': 'application/json' } });
        const j = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(j.message || `HTTP ${res.status}`);
        return j;
    }

    function renderImagePushJobProgress(j, titleFallback) {
        const job = j?.job || {};
        const lines = (job.messages || []).slice(-25).map(item =>
            `<div class="${item.ok ? 'text-success' : 'text-danger'}">[${esc(item.time || '')}] ${esc(item.message || '')}</div>`
        );
        const title = job.last_message || titleFallback || 'Image push in progress…';
        const idx = Number(job.current_index ?? 0);
        const total = Number(job.total ?? 0);
        const detail = total > 0 ? `${title} (${idx}/${total})` : title;
        // Spinner spins only while the job is actually running (so the final render stops it).
        setPushProgress(true, detail, lines.join(''), false, isImagePushJobActive(job.status));
        setPushProgressBar(idx, total);
    }

    // On page load / refresh: if a background image push is still running, re-show its progress
    // and keep polling until it finishes — so refreshing the page doesn't lose the progress view.
    async function resumeImagePushProgress() {
        try {
            let j = await fetchImagePushJobStatus();
            if (!isImagePushJobActive(j?.job?.status)) return;
            renderImagePushJobProgress(j, 'Image push in progress…');
            while (isImagePushJobActive(j?.job?.status)) {
                await sleepMs(2500);
                j = await fetchImagePushJobStatus();
                renderImagePushJobProgress(j);
            }
            renderImagePushJobProgress(j); // final state; box stays until the user closes it
        } catch (_) { /* ignore */ }
    }

    async function queueImagePushAndWait(payload, onProgress) {
        setPushProgressBar(0, 0); // indeterminate until the job reports a total
        const res = await fetch('/image-master/push', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        const rawText = await res.text();
        let j = null;
        try { j = rawText ? JSON.parse(rawText) : null; } catch (_) {}
        if (!j) {
            throw new Error(res.ok ? 'Invalid server response' : `HTTP ${res.status}${rawText ? ': ' + rawText.slice(0, 200) : ''}`);
        }
        if (j.dry_run || j.queued === false) {
            setPushProgressBar(1, 1, j?.success === false);
            return { res, j };
        }
        if (res.status === 409 && isImagePushJobActive(j?.job?.status)) {
            if (onProgress) onProgress(j);
        } else if (onProgress) {
            onProgress(j);
        }
        while (isImagePushJobActive(j?.job?.status)) {
            await sleepMs(2500);
            j = await fetchImagePushJobStatus();
            if (onProgress) onProgress(j);
        }
        const ft = Number(j?.job?.total ?? 0);
        setPushProgressBar(ft > 0 ? ft : 1, ft > 0 ? ft : 1, j?.success === false);
        return { res: { ok: j.success !== false, status: j.success ? 200 : 422 }, j };
    }

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

    // Visual progress bar inside the push box. total<=0 => indeterminate "Queued…" bar.
    function setPushProgressBar(done, total, hasError = false) {
        const wrap = document.getElementById('pushProgressBarWrap');
        const bar = document.getElementById('pushProgressBar');
        if (!wrap || !bar) return;
        wrap.style.display = '';
        const t = Number(total) || 0;
        if (t <= 0) {
            bar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
            bar.style.width = '100%';
            bar.textContent = 'Queued…';
            bar.setAttribute('aria-valuenow', '0');
            return;
        }
        const d = Math.max(0, Math.min(Number(done) || 0, t));
        const pct = Math.round((d / t) * 100);
        const finished = d >= t;
        bar.className = 'progress-bar ' + (hasError ? 'bg-danger' : (finished ? 'bg-success' : 'bg-info progress-bar-striped progress-bar-animated'));
        bar.style.width = pct + '%';
        bar.textContent = `${d}/${t} (${pct}%)`;
        bar.setAttribute('aria-valuenow', String(pct));
    }

    function setPushProgress(visible, title = '', detailsHtml = '', hasError = false, busy = false) {
        const box = document.getElementById('pushProgressBox');
        const t = document.getElementById('pushProgressTitle');
        const d = document.getElementById('pushProgressDetails');
        const spinner = document.getElementById('pushSpinner');
        if (!box || !t || !d) return;
        box.style.display = visible ? 'block' : 'none';
        if (title) t.textContent = title;
        d.innerHTML = detailsHtml || '';
        // Spinner only spins while a push is actively in progress (busy), not when finished.
        if (spinner) spinner.style.display = visible && busy && !hasError ? 'inline-block' : 'none';
        box.className = 'alert mb-3 py-2 px-3' + (hasError ? ' alert-danger' : ' alert-info');
        box.style.wordBreak = 'break-word';
        if (!visible) {
            const wrap = document.getElementById('pushProgressBarWrap');
            const bar = document.getElementById('pushProgressBar');
            if (wrap) wrap.style.display = 'none';
            if (bar) { bar.style.width = '0%'; bar.textContent = ''; bar.className = 'progress-bar bg-info'; }
        }
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

    function hasPushedImages(val) {
        const s = (val || '').trim();
        if (!s || s === '[]') return false;
        try {
            const parsed = JSON.parse(s);
            return !(Array.isArray(parsed) && parsed.length === 0);
        } catch (_) {
            return true;
        }
    }

    function mpStackHtml(sku, mp, val) {
        const pushed = hasPushedImages(val);
        const tile = MP_TILE[mp] || 'btn-secondary';
        const short = MP_SHORT[mp] || mp;
        const st = mpPushTileState(mp, { label: LABELS[mp], implemented: ENABLED_MARKETPLACES.includes(mp) });
        return `<button type="button" class="bp-mp-stack${st.noApiClass}" data-push-mp="${esc(mp)}" data-sku="${esc(sku)}" data-api-configured="${st.configured ? '1' : '0'}" title="${esc(st.title)}" ${st.disabled ? 'disabled' : ''}>
            <span class="bp-mp-dot ${pushed?'pushed':''}"></span>
            <span class="marketplace-btn ${tile}">${esc(short)}</span>
        </button>`;
    }

    function groupCell(gkey, sku, row) {
        const keys = (GROUPS[gkey] || []).filter((mp) => ENABLED_MARKETPLACES.includes(mp));
        const im = row.image_master || {};
        return `<div class="marketplaces-cell"><div class="bp-mp-inline">${keys.map(mp => mpStackHtml(sku, mp, im[mp]||'')).join('')}</div></div>`;
    }

    function updateSelectedCount() {
        const badge = document.getElementById('selectedCountBadge');
        if (badge) badge.textContent = selectedSkus.size + ' selected';
    }

    function syncSelectAllCheckbox() {
        const selectAll = document.getElementById('selectAllRowsIm');
        if (!selectAll) return;
        const visibleSkus = visibleFilteredRows.map(r => String(r.SKU || '')).filter(Boolean);
        if (!visibleSkus.length) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
            return;
        }
        const selectedVisible = visibleSkus.filter(sku => selectedSkus.has(sku));
        selectAll.checked = selectedVisible.length === visibleSkus.length;
        selectAll.indeterminate = selectedVisible.length > 0 && selectedVisible.length < visibleSkus.length;
    }

    /** Header checkbox: fill/clear all visible row checkboxes. */
    function fillAllVisibleRows(checked) {
        visibleFilteredRows.forEach(r => {
            const sku = String(r.SKU || '');
            if (!sku) return;
            if (checked) selectedSkus.add(sku);
            else selectedSkus.delete(sku);
        });
        document.querySelectorAll('.row-select-im[data-sku]').forEach(cb => {
            cb.checked = !!checked;
        });
        updateSelectedCount();
        syncSelectAllCheckbox();
    }

    function bindRowCheckboxEvents(root = document) {
        root.querySelectorAll('.row-select-im[data-sku]').forEach(cb => {
            cb.addEventListener('change', function() {
                const sku = String(this.dataset.sku || '');
                if (!sku) return;
                if (this.checked) selectedSkus.add(sku);
                else selectedSkus.delete(sku);
                updateSelectedCount();
                syncSelectAllCheckbox();
            });
        });
    }

    function fmtHistoryDate(raw, withTime = true) {
        if (!raw) return '';
        const d = new Date(String(raw).replace(' ', 'T'));
        if (isNaN(d.getTime())) return String(raw);
        const opts = withTime
            ? { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }
            : { month: 'short', day: 'numeric', year: 'numeric' };
        return d.toLocaleString(undefined, opts);
    }

    /** Compact "1 APR" label; the year is shown in the hover popup. */
    function fmtHistoryShort(raw) {
        if (!raw) return '';
        const d = new Date(String(raw).replace(' ', 'T'));
        if (isNaN(d.getTime())) return String(raw);
        return d.getDate() + ' ' + d.toLocaleString('en-US', { month: 'short' }).toUpperCase();
    }

    const HIST_ACTION_LABEL = {
        add: 'Added', edit: 'Edited', remove: 'Removed', reorder: 'Reordered',
        upload: 'Uploaded', delete: 'Deleted', shopify_pull: 'Shopify pull', push: 'Pushed',
    };
    const histTs = (raw) => { const t = new Date(String(raw || '').replace(' ', 'T')).getTime(); return isNaN(t) ? null : t; };

    /** Most recent event of any kind (image change or marketplace push) for a row. */
    function historyLatest(r) {
        const acts = Array.isArray(r.activity) ? r.activity : [];
        const h = r.push_history;
        let best = null;
        if (acts.length) {
            const t = histTs(acts[0].at);
            if (t !== null) best = { ts: t, at: acts[0].at, kind: acts[0].action };
        }
        if (h && h.last_at) {
            const t = histTs(h.last_at);
            if (t !== null && (!best || t > best.ts)) best = { ts: t, at: h.last_at, kind: 'push' };
        }
        return best;
    }

    function historyCellHtml(sku, r) {
        const latest = historyLatest(r);
        if (!latest) return '<span class="text-muted">—</span>';
        const h = r.push_history;
        const acts = Array.isArray(r.activity) ? r.activity : [];
        let line2;
        if (latest.kind === 'push' && h) {
            const pushed = Array.isArray(h.pushed) ? h.pushed : [];
            const ok = pushed.filter(p => p.success).length;
            const bad = pushed.length - ok;
            line2 = `<span class="ok">${ok}✓</span>${bad ? ` <span class="bad">${bad}✗</span>` : ''}`;
        } else {
            line2 = `<span class="im-hist-act">${esc(HIST_ACTION_LABEL[latest.kind] || latest.kind)}</span>`;
        }
        const total = acts.length + (h && h.last_at ? 1 : 0);
        return `<span class="im-hist" data-history-sku="${esc(sku)}">
            <span class="im-hist-date"><i class="far fa-clock me-1"></i>${esc(fmtHistoryShort(latest.at))}</span>
            <span class="im-hist-counts">${line2}${total > 1 ? ` <span class="text-muted">· ${total}</span>` : ''}</span>
        </span>`;
    }

    function pushPopHtml(sku, h) {
        const pushed = Array.isArray(h.pushed) ? h.pushed : [];
        const ok = pushed.filter(p => p.success);
        const bad = pushed.filter(p => !p.success);
        const who = h.user_name || h.user_email
            ? `${esc(h.user_name || h.user_email)}${h.user_email && h.user_name ? ` (${esc(h.user_email)})` : ''}${h.user_id ? ` · ID #${esc(h.user_id)}` : ''}`
            : '<span class="text-muted">Unknown (pushed before history tracking)</span>';
        const line = (p, isOk) => `<div class="hp-row"><span class="mk ${isOk ? 'ok' : 'bad'}">${isOk ? '✓' : '✗'}</span><span class="nm">${esc(LABELS[p.marketplace] || p.marketplace)}</span>${!isOk && p.message ? `<span class="msg">${esc(p.message)}</span>` : ''}</div>`;
        const lastSuccess = Object.entries(h.last_success || {})
            .filter(([mp]) => !ok.some(p => p.marketplace === mp))
            .sort((a, b) => String(b[1]).localeCompare(String(a[1])));
        return `<div class="hp-section">Last marketplace push</div>
            <div class="hp-meta"><div><b>Date:</b> ${esc(fmtHistoryDate(h.last_at))}</div><div><b>By:</b> ${who}</div>${h.mode ? `<div><b>Mode:</b> ${esc(h.mode)}</div>` : ''}</div>
            ${ok.length ? `<div class="hp-section">Pushed successfully (${ok.length})</div>${ok.map(p => line(p, true)).join('')}` : ''}
            ${bad.length ? `<div class="hp-section">Failed (${bad.length})</div>${bad.map(p => line(p, false)).join('')}` : ''}
            ${lastSuccess.length ? `<div class="hp-section">Earlier successful pushes</div>${lastSuccess.map(([mp, at]) => `<div class="hp-row"><span class="mk ok">✓</span><span class="nm">${esc(LABELS[mp] || mp)}</span><span class="msg">${esc(fmtHistoryDate(at))}</span></div>`).join('')}` : ''}`;
    }

    function historyPopHtml(sku, r) {
        const acts = Array.isArray(r.activity) ? r.activity : [];
        const h = r.push_history;
        const actHtml = acts.map(a => {
            const who = a.user_name || a.user_email || 'System';
            return `<div class="hp-row"><span class="mk ok">●</span><span class="msg" style="color:#0f172a;">
                <b>${esc(HIST_ACTION_LABEL[a.action] || a.action)}</b> — ${esc(a.summary || '')}<br>
                <span style="color:#64748b;">${esc(fmtHistoryDate(a.at))} · ${esc(who)}</span></span></div>`;
        }).join('');
        return `<div class="hp-title">${esc(sku)} — history</div>
            ${acts.length ? `<div class="hp-section">Image changes (latest ${acts.length})</div>${actHtml}` : ''}
            ${h && h.last_at ? pushPopHtml(sku, h) : ''}`;
    }

    (function bindHistoryHover() {
        const pop = document.getElementById('imHistoryPop');
        const tbody = document.getElementById('table-body');
        if (!pop || !tbody) return;
        const hide = () => { pop.style.display = 'none'; };
        tbody.addEventListener('mouseover', (e) => {
            const el = e.target.closest('[data-history-sku]');
            if (!el) return;
            const sku = el.dataset.historySku;
            const hr = bySku.get(sku);
            if (!hr || !historyLatest(hr)) return;
            pop.innerHTML = historyPopHtml(sku, hr);
            pop.style.display = 'block';
            const r = el.getBoundingClientRect();
            const pw = pop.offsetWidth, ph = pop.offsetHeight;
            let left = r.right + 8;
            if (left + pw > window.innerWidth - 8) left = Math.max(8, r.left - pw - 8);
            let top = r.top;
            if (top + ph > window.innerHeight - 8) top = Math.max(8, window.innerHeight - ph - 8);
            pop.style.left = left + 'px';
            pop.style.top = top + 'px';
        });
        tbody.addEventListener('mouseout', (e) => {
            const el = e.target.closest('[data-history-sku]');
            if (el && !el.contains(e.relatedTarget)) hide();
        });
        document.querySelector('.table-responsive')?.addEventListener('scroll', hide, { passive: true });
    })();

    let imSort = { key: null, dir: 'asc' };

    function groupPushedCount(gkey, row) {
        const keys = (GROUPS[gkey] || []).filter((mp) => ENABLED_MARKETPLACES.includes(mp));
        const im = row.image_master || {};
        return keys.filter(mp => hasPushedImages(im[mp] || '')).length;
    }

    function imSortValue(key, r) {
        const sku = String(r.SKU || '');
        if (/^slot\d+$/.test(key)) {
            const u = pmImageUrls(r)[Number(key.slice(4))];
            return u ? String(u).split('?')[0].split('/').pop().toLowerCase() : null;
        }
        switch (key) {
            case 'selected': return selectedSkus.has(sku) ? 1 : 0;
            case 'sku': return sku;
            case 'images': return pmImageUrls(r).length;
            case 'name': return String(r.Parent || sku);
            case 'history': {
                const l = historyLatest(r);
                return l ? l.ts : null;
            }
            case 'mp': {
                const im = r.image_master || {};
                return ENABLED_MARKETPLACES.filter(mp => hasPushedImages(im[mp] || '')).length;
            }
            case 'channels': return groupPushedCount('gChannels', r);
            case 'shopify': return groupPushedCount('gShopify', r);
        }
        return null;
    }

    function sortRows(rows) {
        if (!imSort.key) return rows;
        const dir = imSort.dir === 'desc' ? -1 : 1;
        return rows.slice().sort((a, b) => {
            const va = imSortValue(imSort.key, a), vb = imSortValue(imSort.key, b);
            // empty values always last
            if (va === null && vb === null) return 0;
            if (va === null) return 1;
            if (vb === null) return -1;
            if (typeof va === 'number' && typeof vb === 'number') return (va - vb) * dir;
            return String(va).localeCompare(String(vb), undefined, { numeric: true, sensitivity: 'base' }) * dir;
        });
    }

    function updateSortIndicators() {
        document.querySelectorAll('#im-master-table thead th[data-sort]').forEach(th => {
            let ind = th.querySelector('.im-sort-ind');
            if (!ind) {
                ind = document.createElement('span');
                ind.className = 'im-sort-ind';
                th.appendChild(ind);
            }
            const active = imSort.key === th.dataset.sort;
            th.classList.toggle('im-sorted', active);
            ind.textContent = active ? (imSort.dir === 'asc' ? '▲' : '▼') : '⇅';
        });
    }

    // ── Image slot columns: one image per column (Hero 1, Hero 2, 3 … 14), editable headers ──
    const IM_SLOT_COUNT = 14;
    const IM_SLOT_KEY = 'im_slot_names_v1';
    const imSlotDefault = (i) => i === 0 ? 'Hero 1' : i === 1 ? 'Hero 2' : String(i + 1);
    let imSlotNames = (() => {
        let saved = [];
        try { saved = JSON.parse(localStorage.getItem(IM_SLOT_KEY) || '[]'); } catch (_) {}
        return Array.from({ length: IM_SLOT_COUNT }, (_, i) => (saved && typeof saved[i] === 'string' && saved[i].trim()) ? saved[i] : imSlotDefault(i));
    })();
    (function buildSlotHeaders() {
        const actionTh = document.getElementById('imActionTh');
        if (!actionTh) return;
        // Column before Hero 1: opens the image editor for the row (magnifying glass).
        const viewTh = document.createElement('th');
        viewTh.style.cssText = 'width:54px;text-align:center;';
        viewTh.title = 'Open the image editor for this row';
        viewTh.innerHTML = '<i class="fas fa-search" aria-hidden="true"></i>';
        actionTh.parentNode.insertBefore(viewTh, actionTh);
        for (let i = 0; i < IM_SLOT_COUNT; i++) {
            const th = document.createElement('th');
            th.className = 'im-slot-th';
            th.dataset.sort = 'slot' + i;
            th.title = `Image ${i + 1} — click header name to rename, click elsewhere on the header to sort`;
            const inp = document.createElement('input');
            inp.type = 'text';
            inp.className = 'im-slot-name';
            inp.value = imSlotNames[i];
            inp.placeholder = imSlotDefault(i);
            inp.maxLength = 24;
            inp.addEventListener('change', () => {
                imSlotNames[i] = inp.value.trim() || imSlotDefault(i);
                inp.value = imSlotNames[i];
                try { localStorage.setItem(IM_SLOT_KEY, JSON.stringify(imSlotNames)); } catch (_) {}
            });
            inp.addEventListener('keydown', (e) => { if (e.key === 'Enter') inp.blur(); });
            th.appendChild(inp);
            actionTh.parentNode.insertBefore(th, actionTh);

        }
    })();

    document.querySelectorAll('#im-master-table thead th[data-sort]').forEach(th => {
        th.addEventListener('click', (e) => {
            if (e.target.closest('input, button, select, a')) return;
            const key = th.dataset.sort;
            if (imSort.key === key) imSort.dir = imSort.dir === 'asc' ? 'desc' : 'asc';
            else imSort = { key, dir: 'asc' };
            renderTable(tableData);
        });
    });

    function renderTable(rows) {
        const tbody = document.getElementById('table-body');
        const q = (document.getElementById('skuSearchIm')?.value || '').trim().toLowerCase();
        if (q) rows = rows.filter(r => String(r.SKU||'').toLowerCase().includes(q));
        rows = sortRows(rows);
        updateSortIndicators();
        visibleFilteredRows = rows;
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="22" class="text-center text-muted py-3">No products</td></tr>';
            updateSelectedCount();
            syncSelectAllCheckbox();
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const sku = String(r.SKU||'');
            const checked = selectedSkus.has(sku) ? 'checked' : '';
            const slotUrls = pmImageUrls(r);
            const slotCells = Array.from({ length: IM_SLOT_COUNT }, (_, i) => {
                const u = slotUrls[i];
                if (!u) return '<td class="text-center"><span class="text-muted">—</span></td>';
                return `<td class="text-center"><div class="im-slot-cell">
                    <img class="im-slot-thumb" src="${esc(imgSrc(u))}" alt="${esc(imSlotNames[i])}" title="${esc(imSlotNames[i])}" loading="lazy">
                    <button type="button" class="im-ai-edit-btn" data-ai-edit="${esc(sku)}" data-slot="${i + 1}" title="Edit ${esc(imSlotNames[i])} with AI" aria-label="Edit ${esc(imSlotNames[i])}"><i class="fas fa-pen"></i></button>
                </div></td>`;
            }).join('');
            const imgCount = slotUrls.length;
            const IM_TARGET_IMAGES = 14; // green at 14 or more, red below
            const imgCountHtml = `<span class="im-img-count ${imgCount >= IM_TARGET_IMAGES ? 'ok' : 'low'}" title="${imgCount} of ${IM_TARGET_IMAGES} images saved">${imgCount}/${IM_TARGET_IMAGES}</span>`;
            return `<tr data-sku="${esc(sku)}">
                <td class="text-center"><input type="checkbox" class="form-check-input row-select-im" data-sku="${esc(sku)}" ${checked} aria-label="Select ${esc(sku)}"></td>
                <td>${esc(sku)}</td>
                <td class="text-center">${imgCountHtml}</td>
                <td>${esc(r.Parent||sku)}</td>
                <td>${historyCellHtml(sku, r)}</td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-primary edit-btn" data-edit="${esc(sku)}" title="View / edit images" aria-label="View / edit images for ${esc(sku)}"><i class="fas fa-search"></i></button></td>
                ${slotCells}
                <td>
                    <div class="d-flex gap-1 flex-wrap align-items-center">
                        <button type="button" class="shopify-row-pull-btn" data-shopify-pull-sku="${esc(sku)}" title="Pull Shopify images for this SKU"><i class="fas fa-download"></i></button>
                    </div>
                </td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-primary im-mp-open" data-mp-open="${esc(sku)}" title="Marketplaces for ${esc(sku)}"><i class="fas fa-search"></i></button></td>
            </tr>`;
        }).join('');
        bindRowCheckboxEvents(tbody);
        updateSelectedCount();
        syncSelectAllCheckbox();
        document.querySelectorAll('.im-mp-open[data-mp-open]').forEach(b => b.addEventListener('click', () => openMpPicker(b.dataset.mpOpen)));
        document.querySelectorAll('.im-ai-edit-btn[data-ai-edit]').forEach(b => b.addEventListener('click', () => openAiEditModal(b.dataset.aiEdit, parseInt(b.dataset.slot, 10))));
        document.querySelectorAll('.edit-btn[data-edit]').forEach(b => b.addEventListener('click', () => openEditModal(b.dataset.edit)));
        document.querySelectorAll('.shopify-row-pull-btn[data-shopify-pull-sku]').forEach(b => b.addEventListener('click', () => startSingleShopifyPull(b.dataset.shopifyPullSku, b)));
        document.querySelectorAll('.bp-mp-stack[data-push-mp]:not(:disabled)').forEach(b => b.addEventListener('click', () => {
            quickPush(b.dataset.sku, b.dataset.pushMp);
        }));
    }


    // ── MP picker modal: grouped marketplaces, drag & drop regroup, checkboxes ──
    const MPG_KEY = 'im_mp_groups_v2';
    function defaultMpGroups() {
        return [
            { id: 'gChannels', name: 'Market Places', mps: [...(GROUPS.gChannels || []), ...(GROUPS.gShopify || [])] },
        ];
    }
    function normalizeMpGroups(gs) {
        const seen = new Set();
        let out = (Array.isArray(gs) ? gs : []).filter(g => g && g.id).map(g => ({
            id: String(g.id), name: String(g.name || 'Group'),
            mps: (Array.isArray(g.mps) ? g.mps : []).filter(mp => {
                if (!ENABLED_MARKETPLACES.includes(mp) || seen.has(mp)) return false;
                seen.add(mp); return true;
            }),
        }));
        if (!out.length) out = defaultMpGroups().map(g => ({ ...g, mps: g.mps.filter(mp => { if (!ENABLED_MARKETPLACES.includes(mp) || seen.has(mp)) return false; seen.add(mp); return true; }) }));
        ENABLED_MARKETPLACES.forEach(mp => { if (!seen.has(mp)) { out[0].mps.push(mp); seen.add(mp); } });
        return out;
    }
    function loadMpGroups() {
        try { return normalizeMpGroups(JSON.parse(localStorage.getItem(MPG_KEY) || 'null')); }
        catch (_) { return normalizeMpGroups(null); }
    }
    let mpGroups = loadMpGroups();
    let mpPickerSku = '';
    const mpPickerSelected = new Set();
    let mpDragMp = null;
    const saveMpGroups = () => { try { localStorage.setItem(MPG_KEY, JSON.stringify(mpGroups)); } catch (_) {} };

    function mpItemState(mp) {
        return mpPushTileState(mp, { label: LABELS[mp], implemented: ENABLED_MARKETPLACES.includes(mp) });
    }

    function renderMpPicker() {
        const wrap = document.getElementById('mpgWrap');
        if (!wrap) return;
        const row = bySku.get(String(mpPickerSku)) || {};
        const im = row.image_master || {};
        wrap.innerHTML = mpGroups.map(g => `
            <div class="mpg-col" data-gid="${esc(g.id)}">
                <div class="mpg-head">
                    <input type="checkbox" class="form-check-input mpg-group-chk m-0" data-gid="${esc(g.id)}" title="Select all in this column" aria-label="Select all in ${esc(g.name)}">
                    <input type="text" class="mpg-name-input" data-gid="${esc(g.id)}" value="${esc(g.name)}" title="Rename column">
                    <span class="mpg-count">${g.mps.length}</span>
                    ${mpGroups.length > 1 ? `<button type="button" class="mpg-del" data-gid="${esc(g.id)}" title="Delete column (marketplaces move to the first other column)"><i class="fas fa-times"></i></button>` : ''}
                </div>
                <div class="mpg-list" data-gid="${esc(g.id)}">
                    ${g.mps.map(mp => {
                        const st = mpItemState(mp);
                        const pushed = hasPushedImages(im[mp] || '');
                        return `<label class="mpg-item ${st.disabled ? 'disabled' : ''}" draggable="true" data-mp="${esc(mp)}" title="${esc(st.title || '')}">
                            <input type="checkbox" class="form-check-input mpg-chk m-0" data-mp="${esc(mp)}" ${st.disabled ? 'disabled' : ''} ${mpPickerSelected.has(mp) && !st.disabled ? 'checked' : ''}>
                            <span class="marketplace-btn ${MP_TILE[mp] || 'btn-secondary'}">${esc(MP_SHORT[mp] || mp)}</span>
                            <span class="mpg-lbl">${esc(LABELS[mp] || mp)}</span>
                            <span class="bp-mp-dot ${pushed ? 'pushed' : ''}"></span>
                        </label>`;
                    }).join('')}
                </div>
            </div>`).join('');
        refreshMpPickerChecks();
    }

    function refreshMpPickerChecks() {
        mpGroups.forEach(g => {
            const chk = document.querySelector(`.mpg-group-chk[data-gid="${CSS.escape(g.id)}"]`);
            if (!chk) return;
            const sel = g.mps.filter(mp => !mpItemState(mp).disabled);
            const n = sel.filter(mp => mpPickerSelected.has(mp)).length;
            chk.checked = sel.length > 0 && n === sel.length;
            chk.indeterminate = n > 0 && n < sel.length;
            chk.disabled = sel.length === 0;
        });
        const c = document.getElementById('mpgSelCount');
        if (c) c.textContent = mpPickerSelected.size + ' selected';
    }

    function openMpPicker(sku) {
        mpPickerSku = String(sku);
        mpPickerSelected.clear();
        document.getElementById('mpPickerSku').textContent = sku;
        renderMpPicker();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('mpPickerModal')).show();
    }

    function moveMpTo(mp, gid, beforeMp) {
        mpGroups.forEach(g => { g.mps = g.mps.filter(x => x !== mp); });
        const target = mpGroups.find(g => g.id === gid);
        if (!target) return;
        const idx = beforeMp ? target.mps.indexOf(beforeMp) : -1;
        if (idx >= 0) target.mps.splice(idx, 0, mp); else target.mps.push(mp);
        saveMpGroups();
        renderMpPicker();
    }

    (function bindMpPicker() {
        const wrap = document.getElementById('mpgWrap');
        if (!wrap) return;
        wrap.addEventListener('change', (e) => {
            const t = e.target;
            if (t.classList.contains('mpg-chk')) {
                if (t.checked) mpPickerSelected.add(t.dataset.mp); else mpPickerSelected.delete(t.dataset.mp);
                refreshMpPickerChecks();
            } else if (t.classList.contains('mpg-group-chk')) {
                const g = mpGroups.find(x => x.id === t.dataset.gid);
                if (!g) return;
                g.mps.filter(mp => !mpItemState(mp).disabled).forEach(mp => {
                    if (t.checked) mpPickerSelected.add(mp); else mpPickerSelected.delete(mp);
                });
                renderMpPicker();
            } else if (t.classList.contains('mpg-name-input')) {
                const g = mpGroups.find(x => x.id === t.dataset.gid);
                if (g) { g.name = t.value.trim() || g.name; saveMpGroups(); }
            }
        });
        wrap.addEventListener('click', (e) => {
            const del = e.target.closest('.mpg-del');
            if (!del) return;
            const idx = mpGroups.findIndex(g => g.id === del.dataset.gid);
            if (idx < 0 || mpGroups.length < 2) return;
            const [removed] = mpGroups.splice(idx, 1);
            if (removed.mps.length) mpGroups[0].mps.push(...removed.mps);
            saveMpGroups();
            renderMpPicker();
        });
        wrap.addEventListener('dragstart', (e) => {
            const item = e.target.closest('.mpg-item');
            if (!item) return;
            mpDragMp = item.dataset.mp;
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', mpDragMp); } catch (_) {}
        });
        wrap.addEventListener('dragend', () => {
            mpDragMp = null;
            wrap.querySelectorAll('.dragging').forEach(el => el.classList.remove('dragging'));
            wrap.querySelectorAll('.mpg-over').forEach(el => el.classList.remove('mpg-over'));
        });
        wrap.addEventListener('dragover', (e) => {
            if (!mpDragMp) return;
            const col = e.target.closest('.mpg-col');
            if (!col) return;
            e.preventDefault();
            wrap.querySelectorAll('.mpg-over').forEach(el => { if (el !== col) el.classList.remove('mpg-over'); });
            col.classList.add('mpg-over');
        });
        wrap.addEventListener('drop', (e) => {
            if (!mpDragMp) return;
            const col = e.target.closest('.mpg-col');
            if (!col) return;
            e.preventDefault();
            const list = col.querySelector('.mpg-list');
            const items = [...list.querySelectorAll('.mpg-item')].filter(el => el.dataset.mp !== mpDragMp);
            const before = items.find(el => { const r = el.getBoundingClientRect(); return e.clientY < r.top + r.height / 2; });
            moveMpTo(mpDragMp, col.dataset.gid, before ? before.dataset.mp : null);
            mpDragMp = null;
        });

        document.getElementById('mpgAddBtn')?.addEventListener('click', () => {
            const inp = document.getElementById('mpgNewName');
            const name = (inp.value || '').trim();
            if (!name) { toast('Enter a column name first.', false); inp.focus(); return; }
            mpGroups.push({ id: 'g' + Date.now().toString(36), name, mps: [] });
            inp.value = '';
            saveMpGroups();
            renderMpPicker();
        });
        document.getElementById('mpgNewName')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); document.getElementById('mpgAddBtn').click(); }
        });
        document.getElementById('mpgResetBtn')?.addEventListener('click', () => {
            mpGroups = normalizeMpGroups(null);
            saveMpGroups();
            renderMpPicker();
        });
        document.getElementById('mpgPushBtn')?.addEventListener('click', () => pushFromMpPicker());
    })();

    function pushFromMpPicker() {
        const sku = mpPickerSku;
        const row = bySku.get(String(sku));
        const mps = ENABLED_MARKETPLACES.filter(mp => mpPickerSelected.has(mp) && !mpItemState(mp).disabled);
        if (!row) return;
        if (!mps.length) { toast('Select at least one marketplace.', false); return; }
        const urls = pmImageUrls(row);
        if (!urls.length) { toast('No images on Product Master for this SKU — open Edit first.', false); return; }
        if (!confirmEbay3Push(mps)) return;
        if (!window.confirm(`Push ${urls.length} image(s) for ${sku} to ${mps.length} marketplace(s)?\n\n${mps.map(mp => LABELS[mp] || mp).join(', ')}\n\nThis will replace existing marketplace images.`)) return;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('mpPickerModal')).hide();
        setPushProgress(true, `Queuing push for ${mps.length} marketplace(s)…`, '', false, true);
        queueImagePushAndWait({
            sku,
            mode: 'replace',
            updates: mps.map(mp => ({ marketplace: mp, images: urls })),
            main_by_marketplace: mainByMarketplacePayloadFromRow(row),
        }, (st) => renderImagePushJobProgress(st, `Pushing ${sku} to ${mps.length} marketplace(s)…`))
        .then(({ res, j }) => {
            let ok = 0, fail = 0;
            const lines = mps.map((mp, i) => {
                const r = j && j.results ? j.results[mp] : null;
                const good = !!(r && r.success);
                if (good) ok++; else fail++;
                return `${i + 1}/${mps.length} ${esc(LABELS[mp] || mp)}: ${good ? 'OK' : 'Failed'}${r && r.message ? ` - ${esc(r.message)}` : ''}`;
            });
            if (!(j && j.results) && !res.ok) lines.unshift(esc(j?.message || `HTTP ${res.status}`));
            setPushProgress(true, `Push finished: ${ok} success, ${fail} failed`, lines.join('<br>'), fail > 0);
            toast(fail ? `Push completed with ${fail} failure(s).` : `Pushed to ${ok} marketplace(s).`, !fail);
            loadData();
        }).catch(e => {
            setPushProgress(true, 'Push failed', esc(e.message || 'Request failed'), true);
            toast(e.message || 'Push failed', false);
        });
    }

    function defaultMainByMarketplace() {
        const o = {};
        MARKETPLACES.forEach(mp => { o[mp] = 0; });
        return o;
    }

    function normalizeMainByMarketplace(raw) {
        const out = defaultMainByMarketplace();
        if (!raw || typeof raw !== 'object') return out;
        MARKETPLACES.forEach(mp => {
            if (raw[mp] != null && raw[mp] !== '') {
                out[mp] = Math.max(0, Math.min(PM_MAX_IMAGES - 1, parseInt(raw[mp], 10) || 0));
            }
        });
        return out;
    }

    const HERO2_SUFFIX = '__hero2';

    /** Hero 2 is stored server-side as "<mp>__hero2": image index + 1 (absent/0 = automatic). */
    function hero2FromRaw(raw) {
        const out = {};
        if (!raw || typeof raw !== 'object') return out;
        MARKETPLACES.forEach(mp => {
            const v = parseInt(raw[mp + HERO2_SUFFIX], 10) || 0;
            if (v > 0) out[mp] = Math.min(PM_MAX_IMAGES - 1, v - 1);
        });
        return out;
    }

    function clampMainIndicesAfterDelete(deletedIdx) {
        MARKETPLACES.forEach(mp => {
            let idx = modalMainByMp[mp] ?? 0;
            if (idx === deletedIdx) idx = 0;
            else if (idx > deletedIdx) idx--;
            modalMainByMp[mp] = Math.max(0, idx);
            if (modalHero2ByMp[mp] != null) {
                const h = modalHero2ByMp[mp];
                if (h === deletedIdx) delete modalHero2ByMp[mp];
                else if (h > deletedIdx) modalHero2ByMp[mp] = h - 1;
            }
        });
    }

    function remapMainIndicesAfterMove(from, to) {
        if (from === to) return;
        MARKETPLACES.forEach(mp => {
            let idx = modalMainByMp[mp] ?? 0;
            if (idx === from) idx = to;
            else if (from < to && idx > from && idx <= to) idx--;
            else if (from > to && idx >= to && idx < from) idx++;
            modalMainByMp[mp] = idx;
            if (modalHero2ByMp[mp] != null) {
                let h = modalHero2ByMp[mp];
                if (h === from) h = to;
                else if (from < to && h > from && h <= to) h--;
                else if (from > to && h >= to && h < from) h++;
                modalHero2ByMp[mp] = h;
            }
        });
    }

    function clampMainIndicesToLength() {
        const max = Math.max(0, modalUrls.length - 1);
        MARKETPLACES.forEach(mp => {
            let idx = modalMainByMp[mp] ?? 0;
            if (modalUrls.length === 0) idx = 0;
            else if (idx > max) idx = 0;
            modalMainByMp[mp] = idx;
            if (modalHero2ByMp[mp] != null && (modalUrls.length < 2 || modalHero2ByMp[mp] > max)) delete modalHero2ByMp[mp];
        });
    }

    function mainByMarketplacePayload() {
        const out = {};
        const max = Math.max(0, modalUrls.length - 1);
        MARKETPLACES.forEach(mp => {
            const idx = Math.max(0, Math.min(max, modalMainByMp[mp] ?? 0));
            if (idx > 0) out[mp] = idx;
            const h = modalHero2ByMp[mp];
            if (h != null && h <= max && h !== idx) out[mp + HERO2_SUFFIX] = h + 1;
        });
        return out;
    }

    function mainByMarketplacePayloadFromRow(row) {
        const out = {};
        const map = normalizeMainByMarketplace(row?.image_main_by_marketplace);
        MARKETPLACES.forEach(mp => {
            const idx = map[mp] ?? 0;
            if (idx > 0) out[mp] = idx;
        });
        const h2 = hero2FromRaw(row?.image_main_by_marketplace);
        Object.keys(h2).forEach(mp => { if (h2[mp] !== (map[mp] ?? 0)) out[mp + HERO2_SUFFIX] = h2[mp] + 1; });
        return out;
    }

    function mainImageLabelFor(mp, idx) {
        return `Image ${(idx ?? 0) + 1}`;
    }

    function mainImageIndexFor(mp, source) {
        if (source === 'modal') return modalMainByMp[mp] ?? 0;
        return normalizeMainByMarketplace(source?.image_main_by_marketplace)[mp] ?? 0;
    }

    function pmImageUrls(row) {
        const u = [];
        for (let i=1;i<=PM_MAX_IMAGES;i++) {
            const v = row['image'+i];
            if (v && String(v).trim()) u.push(String(v).trim());
        }
        if (u.length) return u;
        ['main_image'].forEach(k => {
            const v = row[k];
            if (v && String(v).trim()) u.push(String(v).trim());
        });
        return u.slice(0, PM_MAX_IMAGES);
    }


    // ── Prompts: one editable/copyable prompt per image slot (shared, saved on the server) ──
    let slotPrompts = {};
    let slotPromptCurrent = 0;
    fetch('/image-master/slot-prompts', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json()).then(j => { if (j && j.prompts) slotPrompts = j.prompts; })
        .catch(() => {});

    function renderPromptsMenu() {
        const menu = document.getElementById('promptsMenu');
        if (!menu) return;
        menu.innerHTML = imSlotNames.map((name, i) => {
            const has = !!String(slotPrompts[i + 1] || '').trim();
            return `<div class="ip-row">
                <span class="ip-name">${esc(name)}${has ? '<span class="ip-dot" title="Prompt saved"></span>' : ''}</span>
                <button type="button" class="btn btn-sm btn-outline-primary ip-open" data-slot="${i + 1}">Prompt</button>
            </div>`;
        }).join('');
    }
    document.getElementById('promptsDropdown')?.addEventListener('show.bs.dropdown', renderPromptsMenu);
    document.getElementById('promptsMenu')?.addEventListener('click', (e) => {
        const b = e.target.closest('.ip-open');
        if (!b) return;
        slotPromptCurrent = parseInt(b.dataset.slot, 10) || 0;
        document.getElementById('slotPromptName').textContent = imSlotNames[slotPromptCurrent - 1] || ('Image ' + slotPromptCurrent);
        document.getElementById('slotPromptText').value = slotPrompts[slotPromptCurrent] || '';
        document.getElementById('slotPromptMsg').textContent = '';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('slotPromptModal')).show();
    });

    document.getElementById('slotPromptCopyBtn')?.addEventListener('click', async () => {
        const ta = document.getElementById('slotPromptText');
        const msg = document.getElementById('slotPromptMsg');
        const text = ta.value;
        if (!text) { msg.className = 'small mt-2 text-muted'; msg.textContent = 'Nothing to copy.'; return; }
        try {
            await navigator.clipboard.writeText(text);
        } catch (_) {
            ta.select();
            document.execCommand('copy');
        }
        msg.className = 'small mt-2 text-success';
        msg.textContent = 'Copied to clipboard.';
    });

    document.getElementById('slotPromptSaveBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('slotPromptSaveBtn');
        const msg = document.getElementById('slotPromptMsg');
        const prompt = document.getElementById('slotPromptText').value;
        const label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
        try {
            const r = await fetch('/image-master/slot-prompts', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ slot: slotPromptCurrent, prompt }),
            });
            const j = await r.json().catch(() => ({}));
            if (r.ok && j.success) {
                if (prompt.trim()) slotPrompts[slotPromptCurrent] = prompt.trim(); else delete slotPrompts[slotPromptCurrent];
                msg.className = 'small mt-2 text-success';
                msg.textContent = j.message || 'Saved.';
                toast(j.message || 'Prompt saved');
            } else {
                msg.className = 'small mt-2 text-danger';
                msg.textContent = j.message || 'Save failed.';
            }
        } catch (e) {
            msg.className = 'small mt-2 text-danger';
            msg.textContent = e.message || 'Save failed.';
        } finally {
            btn.disabled = false;
            btn.innerHTML = label;
        }
    });

    // ── AI edit (Nano Banana): per-image Edit modal ───────────────────────────
    let aiEdit = { sku: '', slot: 0, path: '', results: [], selected: -1 };

    function aiEditMsg(text, cls) {
        const m = document.getElementById('aiEditMsg');
        m.className = 'small mt-2 ' + (cls || 'text-muted');
        m.textContent = text || '';
    }

    function renderAiResults() {
        const box = document.getElementById('aiEditResult');
        const apply = document.getElementById('aiEditApplyBtn');
        const r = aiEdit.results;
        if (!r.length) {
            box.innerHTML = '<span class="text-muted small">Click <strong>Prompt</strong> to generate.</span>';
            apply.style.display = 'none';
            aiEdit.path = '';
            return;
        }
        if (aiEdit.selected < 0 || aiEdit.selected >= r.length) aiEdit.selected = r.length - 1;
        aiEdit.path = r[aiEdit.selected].path;
        box.innerHTML = `<img src="${esc(r[aiEdit.selected].url)}" alt="">`
            + `<div class="ai-edit-thumbs">${r.map((x, i) => `<img src="${esc(x.url)}" data-i="${i}" class="${i === aiEdit.selected ? 'sel' : ''}" alt="Option ${i + 1}" title="Option ${i + 1}">`).join('')}</div>`;
        apply.style.display = '';
    }

    document.getElementById('aiEditResult')?.addEventListener('click', (e) => {
        const t = e.target.closest('.ai-edit-thumbs img');
        if (!t) return;
        aiEdit.selected = parseInt(t.dataset.i, 10) || 0;
        renderAiResults();
    });

    function openAiEditModal(sku, slot) {
        const row = bySku.get(String(sku));
        if (!row) return;
        const url = pmImageUrls(row)[slot - 1];
        if (!url) return;
        aiEdit = { sku: String(sku), slot, path: '', results: [], selected: -1 };
        document.getElementById('aiEditGenLabel').textContent = 'Prompt';
        document.getElementById('aiEditName').textContent = imSlotNames[slot - 1] || ('Image ' + slot);
        document.getElementById('aiEditSku').textContent = sku;
        document.getElementById('aiEditOriginal').innerHTML = `<img src="${esc(imgSrc(url))}" alt="">`;
        renderAiResults();
        document.getElementById('aiEditPrompt').value = slotPrompts[slot] || '';
        aiEditMsg(slotPrompts[slot] ? '' : 'No saved prompt for this image yet — write one below (or use the Prompts button in the toolbar).');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('aiEditModal')).show();
    }

    document.getElementById('aiEditSavePromptBtn')?.addEventListener('click', async () => {
        const prompt = document.getElementById('aiEditPrompt').value;
        try {
            const r = await fetch('/image-master/slot-prompts', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ slot: aiEdit.slot, prompt }),
            });
            const j = await r.json().catch(() => ({}));
            if (r.ok && j.success) {
                if (prompt.trim()) slotPrompts[aiEdit.slot] = prompt.trim(); else delete slotPrompts[aiEdit.slot];
                aiEditMsg(j.message || 'Prompt saved.', 'text-success');
            } else {
                aiEditMsg(j.message || 'Could not save prompt.', 'text-danger');
            }
        } catch (e) {
            aiEditMsg(e.message || 'Could not save prompt.', 'text-danger');
        }
    });

    document.getElementById('aiEditGenerateBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('aiEditGenerateBtn');
        const prompt = document.getElementById('aiEditPrompt').value.trim();
        if (!prompt) { aiEditMsg('Write a prompt first.', 'text-danger'); return; }
        const count = Math.max(1, Math.min(4, parseInt(document.getElementById('aiEditCount').value, 10) || 1));
        const label = btn.innerHTML;
        btn.disabled = true;
        let made = 0;
        let error = '';
        for (let n = 1; n <= count; n++) {
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span>Generating ${n}/${count}…`;
            aiEditMsg(`Nano Banana is generating image ${n} of ${count}… this can take up to a minute each.`);
            try {
                const r = await fetch('/image-master/ai-edit/generate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ sku: aiEdit.sku, slot: aiEdit.slot, prompt }),
                });
                const j = await r.json().catch(() => ({}));
                if (r.ok && j.success) {
                    aiEdit.results.push({ path: j.path, url: j.preview_url });
                    aiEdit.selected = aiEdit.results.length - 1;
                    made++;
                    renderAiResults();
                } else {
                    error = j.message || 'Generation failed.';
                    break;
                }
            } catch (e) {
                error = e.message || 'Generation failed.';
                break;
            }
        }
        btn.disabled = false;
        btn.innerHTML = label;
        if (aiEdit.results.length) document.getElementById('aiEditGenLabel').textContent = 'Generate more';
        if (error) {
            aiEditMsg((made ? `${made} generated, then stopped: ` : '') + error, 'text-danger');
        } else {
            aiEditMsg(`${made} new image(s) generated (${aiEdit.results.length} total). Click a thumbnail to choose one, or generate more.`, 'text-success');
        }
    });

    document.getElementById('aiEditApplyBtn')?.addEventListener('click', async () => {
        if (!aiEdit.path) return;
        if (!window.confirm(`Overwrite ${imSlotNames[aiEdit.slot - 1] || 'this image'} for ${aiEdit.sku} with the new image?`)) return;
        const btn = document.getElementById('aiEditApplyBtn');
        const label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';
        try {
            const r = await fetch('/image-master/ai-edit/apply', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ sku: aiEdit.sku, slot: aiEdit.slot, path: aiEdit.path, prompt: document.getElementById('aiEditPrompt').value }),
            });
            const j = await r.json().catch(() => ({}));
            if (r.ok && j.success) {
                toast(j.message || 'Image replaced');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('aiEditModal')).hide();
                loadData();
            } else {
                aiEditMsg(j.message || 'Could not save the image.', 'text-danger');
            }
        } catch (e) {
            aiEditMsg(e.message || 'Could not save the image.', 'text-danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = label;
        }
    });

    // ── Hero Image modal: Hero 1 / Hero 2 per marketplace ─────────────────────
    let heroRows = [];        // rows the settings will be applied to
    let heroUsingSelection = false;
    let heroMain = {};   // mp -> index
    let heroHero2 = {};  // mp -> index (absent = Auto)

    function heroOptionsHtml(n, selected, withAuto) {
        let h = withAuto ? `<option value="auto"${selected == null ? ' selected' : ''}>Auto</option>` : '';
        for (let i = 0; i < n; i++) h += `<option value="${i}"${selected === i ? ' selected' : ''}>Image ${i + 1}</option>`;
        return h;
    }

    function heroThumbsHtml(urls, mp) {
        const m = heroMain[mp] ?? 0;
        const h = heroHero2[mp];
        const t = (i) => urls[i] ? `<img src="${esc(imgSrc(urls[i]))}" alt="">` : '';
        return `<span class="hero-thumbs">${t(m)}${h != null && h !== m ? t(h) : ''}</span>`;
    }

    function renderHeroRows() {
        const single = heroRows.length === 1 ? pmImageUrls(heroRows[0]) : null;
        const n = Math.max(1, ...heroRows.map(r => pmImageUrls(r).length));
        const wrap = document.getElementById('heroRows');
        if (!wrap) return;
        wrap.innerHTML = MARKETPLACES.map(mp => `
            <div class="hero-row" data-mp="${esc(mp)}">
                <span class="hero-mp">${esc(LABELS[mp] || mp)}</span>
                <select class="form-select form-select-sm hero-sel-main" data-mp="${esc(mp)}">${heroOptionsHtml(n, heroMain[mp] ?? 0, false)}</select>
                <select class="form-select form-select-sm hero-sel-2" data-mp="${esc(mp)}">${heroOptionsHtml(n, heroHero2[mp] ?? null, true)}</select>
                <span class="hero-thumbs-cell">${single ? heroThumbsHtml(single, mp) : ''}</span>
            </div>`).join('');
        const allMain = document.getElementById('heroAllMain');
        const allH2 = document.getElementById('heroAllHero2');
        if (allMain) allMain.innerHTML = heroOptionsHtml(n, 0, false);
        if (allH2) allH2.innerHTML = heroOptionsHtml(n, null, true);
    }

    function openHeroModal() {
        const withImages = (r) => pmImageUrls(r).length > 0;
        const selected = tableData.filter(r => selectedSkus.has(String(r.SKU || '')) && withImages(r));
        heroUsingSelection = selected.length > 0;
        heroRows = heroUsingSelection ? selected : currentFilteredRowsForPull().filter(withImages);
        if (!heroRows.length) {
            toast('No products with images to set hero images for.', false);
            return;
        }
        if (heroRows.length === 1) {
            heroMain = normalizeMainByMarketplace(heroRows[0].image_main_by_marketplace);
            heroHero2 = hero2FromRaw(heroRows[0].image_main_by_marketplace);
        } else {
            heroMain = defaultMainByMarketplace();
            heroHero2 = {};
        }
        document.getElementById('heroSkuLabel').textContent = heroRows.length === 1
            ? String(heroRows[0].SKU)
            : `${heroRows.length} SKUs (${heroUsingSelection ? 'selected' : 'all in current view'})`;
        const note = document.getElementById('heroScopeNote');
        if (note) {
            note.textContent = heroRows.length > 1
                ? `These settings will be applied to ${heroRows.length} SKUs, replacing their current hero choices. Image numbers that a SKU does not have are skipped for that SKU.`
                : '';
            note.style.display = note.textContent ? '' : 'none';
        }
        renderHeroRows();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('heroImageModal')).show();
    }
    document.getElementById('heroImageBtn')?.addEventListener('click', openHeroModal);

    function heroSetFor(mp, which, rawVal) {
        const val = rawVal === 'auto' ? null : (parseInt(rawVal, 10) || 0);
        if (which === 'main') heroMain[mp] = val ?? 0;
        else if (val === null) delete heroHero2[mp];
        else heroHero2[mp] = val;
    }

    document.getElementById('heroRows')?.addEventListener('change', (e) => {
        const sel = e.target.closest('select[data-mp]');
        if (!sel) return;
        heroSetFor(sel.dataset.mp, sel.classList.contains('hero-sel-main') ? 'main' : 'h2', sel.value);
        const cell = sel.closest('.hero-row')?.querySelector('.hero-thumbs-cell');
        if (cell && heroRows.length === 1) cell.innerHTML = heroThumbsHtml(pmImageUrls(heroRows[0]), sel.dataset.mp);
    });

    document.getElementById('heroApplyAllBtn')?.addEventListener('click', () => {
        const m = document.getElementById('heroAllMain').value;
        const h = document.getElementById('heroAllHero2').value;
        MARKETPLACES.forEach(mp => { heroSetFor(mp, 'main', m); heroSetFor(mp, 'h2', h); });
        renderHeroRows();
        document.getElementById('heroAllMain').value = m;
        document.getElementById('heroAllHero2').value = h;
    });

    document.getElementById('heroResetBtn')?.addEventListener('click', () => {
        heroMain = defaultMainByMarketplace();
        heroHero2 = {};
        renderHeroRows();
    });

    document.getElementById('heroSaveBtn')?.addEventListener('click', async () => {
        if (!heroRows.length) return;
        if (heroRows.length > 1 && !window.confirm(`Apply these hero image settings to ${heroRows.length} SKUs${heroUsingSelection ? '' : ' (everything in the current view)'}?`)) return;
        const btn = document.getElementById('heroSaveBtn');
        const label = btn.innerHTML;
        btn.disabled = true;
        let ok = 0, fail = 0, firstErr = '';
        for (let i = 0; i < heroRows.length; i++) {
            const row = heroRows[i];
            const urls = pmImageUrls(row);
            const payload = {};
            MARKETPLACES.forEach(mp => {
                const m = Math.max(0, Math.min(urls.length - 1, heroMain[mp] ?? 0));
                if (m > 0) payload[mp] = m;
                const h = heroHero2[mp];
                if (h != null && h !== m && h <= urls.length - 1) payload[mp + HERO2_SUFFIX] = h + 1;
            });
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Saving ${i + 1}/${heroRows.length}…`;
            try {
                const r = await fetch('/image-master/save-pm', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ sku: String(row.SKU), images: urls, main_by_marketplace: payload, removed_urls: [] }),
                });
                const j = await r.json().catch(() => ({}));
                if (j.success) {
                    row.image_main_by_marketplace = j.image_main_by_marketplace || payload;
                    ok++;
                } else {
                    fail++;
                    if (!firstErr) firstErr = `${row.SKU}: ${j.message || 'Save failed'}`;
                }
            } catch (e) {
                fail++;
                if (!firstErr) firstErr = `${row.SKU}: ${e.message || 'Save failed'}`;
            }
        }
        btn.disabled = false;
        btn.innerHTML = label;
        if (fail === 0) {
            toast(`Hero images saved for ${ok} SKU(s)`);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('heroImageModal')).hide();
        } else {
            toast(`Saved ${ok}, failed ${fail}. ${firstErr}`, false);
        }
        loadData();
    });

    // storedImageIds: url → DB id (so we can delete from server)
    const storedImageMeta = new Map();

    function openEditModal(sku) {
        const row = bySku.get(String(sku));
        if (!row) return;
        document.getElementById('modalSku').value = sku;
        document.getElementById('modalSkuLabel').textContent = sku;
        document.getElementById('modalProductLabel').textContent = row.Parent || sku;
        storedImageMeta.clear();
        modalUrls = pmImageUrls(row);
        knownImageUrls = [...modalUrls];
        modalMainByMp = normalizeMainByMarketplace(row.image_main_by_marketplace);
        modalHero2ByMp = hero2FromRaw(row.image_main_by_marketplace);
        renderSlots();
        // Reset the upload section
        pendingFiles = [];
        updatePendingFileUi();
        document.getElementById('uploadSuccessMsg').style.display = 'none';
        if (editModal) editModal.show();
        // Load stored-image metadata for saved URLs in the background.
        // Do not append every upload here; the modal list must reflect Product Master.
        loadStoredSkuImages(sku);
    }

    async function loadStoredSkuImages(sku) {
        try {
            const r = await fetch('/image-master/sku-images?sku=' + encodeURIComponent(sku));
            const j = await r.json();
            if (!j.success || !j.images?.length) return;
            const existingSet = new Set(modalUrls);
            j.images.forEach(img => {
                if (existingSet.has(img.url)) {
                    storedImageMeta.set(img.url, { id: img.id, name: img.name });
                }
            });
            renderSlots();
        } catch (_) {}
    }

    function renderSlots() {
        const el = document.getElementById('imSlots');
        const removeAllBtn = document.getElementById('removeAllImagesBtn');
        if (removeAllBtn) removeAllBtn.style.display = modalUrls.length ? 'inline-flex' : 'none';
        const downloadAllBtn = document.getElementById('downloadAllImagesBtn');
        if (downloadAllBtn) downloadAllBtn.style.display = modalUrls.length ? 'inline-flex' : 'none';
        if (!modalUrls.length) {
            el.innerHTML = '<div class="text-muted small py-2">No images yet. Upload or fetch from a marketplace above.</div>';
            return;
        }

        const cardHtml = (url, idx) => {
                const meta     = storedImageMeta.get(url);
                const isStored = !!meta;
                const name     = meta?.name ?? decodeURIComponent(url.split('/').pop().split('?')[0]);
                const dbId     = meta?.id ?? '';
                const isDefaultMain = idx === 0;
                const slotName = imSlotNames[idx] || ('Image ' + (idx + 1));
                const heroCls  = idx < 2 ? ' im-card-hero' : '';
                return `<div class="im-card${heroCls}${isStored?' is-stored':''}${isDefaultMain?' is-main-default':''}"
                            draggable="true" data-idx="${idx}"
                            data-url="${esc(url)}" data-dbid="${esc(String(dbId))}">
                    <div class="im-card-img-wrap">
                        <img src="${esc(imgSrc(url))}" alt=""
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22100%22%3E%3Crect width=%22120%22 height=%22100%22 fill=%22%23f1f5f9%22/%3E%3Ctext x=%2260%22 y=%2254%22 font-size=%2211%22 fill=%22%2394a3b8%22 text-anchor=%22middle%22%3ENo preview%3C/text%3E%3C/svg%3E'">
                        <span class="im-card-badge${isDefaultMain?' im-card-badge-main':''}">${isDefaultMain ? 'MAIN' : (idx + 1)}</span>
                        <button type="button" class="im-card-del" data-i="${idx}" title="Delete image"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="im-card-footer">
                        <div class="im-card-name" title="Image ${idx + 1}">${esc(slotName)}</div>
                        <div class="im-card-filename" title="${esc(url)}">${esc(name)}</div>
                        <div class="im-card-arrows">
                            <button type="button" class="im-up" data-i="${idx}" title="Move left">&#8592;</button>
                            <button type="button" class="im-set-main" data-i="${idx}" title="Set as main (#1) for all marketplaces">&#9733;</button>
                            <button type="button" class="im-down" data-i="${idx}" title="Move right">&#8594;</button>
                            <button type="button" class="im-dl" data-i="${idx}" title="Download this image"><i class="fas fa-download"></i></button>
                        </div>
                    </div>
                </div>`;
        };
        const heroCards = modalUrls.slice(0, 2).map((u, i) => cardHtml(u, i)).join('');
        const restCards = modalUrls.slice(2).map((u, i) => cardHtml(u, i + 2)).join('');
        el.innerHTML = '<div id="imGrid">'
            + `<div class="im-grid im-grid-hero">${heroCards}</div>`
            + (restCards ? `<div class="im-grid">${restCards}</div>` : '')
            + '</div>';

        const grid = document.getElementById('imGrid');

        // ── Delete button ──────────────────────────────────────────
        grid.querySelectorAll('.im-card-del').forEach(b => {
            b.addEventListener('click', async (e) => {
                e.stopPropagation();
                const i      = +b.dataset.i;
                clampMainIndicesAfterDelete(i);
                const removed = modalUrls.splice(i, 1)[0];
                storedImageMeta.delete(removed);
                renderSlots();
            });
        });

        // ── Download one image ─────────────────────────────────────
        grid.querySelectorAll('.im-dl').forEach(b => {
            b.addEventListener('click', async (e) => {
                e.stopPropagation();
                const i = +b.dataset.i;
                const url = modalUrls[i];
                if (!url) return;
                b.disabled = true;
                try {
                    await downloadFromServer(
                        '/image-master/download?url=' + encodeURIComponent(url)
                            + '&sku=' + encodeURIComponent(document.getElementById('modalSku').value)
                            + '&n=' + (i + 1),
                        { headers: { 'Accept': 'application/json' } },
                        'image_' + (i + 1) + '.jpg'
                    );
                } catch (err) {
                    alert(err.message || 'Download failed.');
                } finally {
                    b.disabled = false;
                }
            });
        });

        grid.querySelectorAll('.im-set-main').forEach(b => {
            b.addEventListener('click', (e) => {
                e.stopPropagation();
                const i = +b.dataset.i;
                if (i <= 0) return;
                const picked = modalUrls.splice(i, 1)[0];
                modalUrls.unshift(picked);
                modalMainByMp = defaultMainByMarketplace();
                modalHero2ByMp = {};
                renderSlots();
            });
        });

        // ── Arrow buttons ─────────────────────────────────────────
        grid.querySelectorAll('.im-up').forEach(b => {
            b.addEventListener('click', (e) => {
                e.stopPropagation();
                const i = +b.dataset.i;
                if (i > 0) {
                    remapMainIndicesAfterMove(i, i - 1);
                    [modalUrls[i-1], modalUrls[i]] = [modalUrls[i], modalUrls[i-1]];
                    renderSlots();
                }
            });
        });
        grid.querySelectorAll('.im-down').forEach(b => {
            b.addEventListener('click', (e) => {
                e.stopPropagation();
                const i = +b.dataset.i;
                if (i < modalUrls.length - 1) {
                    remapMainIndicesAfterMove(i, i + 1);
                    [modalUrls[i+1], modalUrls[i]] = [modalUrls[i], modalUrls[i+1]];
                    renderSlots();
                }
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
                remapMainIndicesAfterMove(dragFrom, to);
                const moved = modalUrls.splice(dragFrom, 1)[0];
                modalUrls.splice(to, 0, moved);
                dragFrom = null;
                renderSlots();
            });
        });

        clampMainIndicesToLength();
    }

    // Fetch a file from the server and save it with the name the server gives it.
    async function downloadFromServer(url, options, fallbackName) {
        const res = await fetch(url, options);
        if (!res.ok) {
            const j = await res.json().catch(() => null);
            throw new Error((j && j.message) || ('Download failed (' + res.status + ').'));
        }
        const disposition = res.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
        const name = match ? decodeURIComponent(match[1]) : fallbackName;
        const blob = await res.blob();
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = name;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 10000);
    }

    document.getElementById('downloadAllImagesBtn')?.addEventListener('click', async function () {
        if (!modalUrls.length) return;
        const btn = this;
        const label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Preparing ZIP...';
        try {
            await downloadFromServer('/image-master/download-zip', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ sku: document.getElementById('modalSku').value, urls: modalUrls })
            }, 'images.zip');
        } catch (err) {
            alert(err.message || 'Download failed.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = label;
        }
    });

    document.getElementById('removeAllImagesBtn')?.addEventListener('click', () => {
        if (!modalUrls.length) return;
        if (!confirm('Remove all images from this list? Click Save Images afterward to apply the change to Product Master.')) return;
        modalUrls = [];
        storedImageMeta.clear();
        modalMainByMp = defaultMainByMarketplace();
        modalHero2ByMp = {};
        pendingFiles = [];
        updatePendingFileUi();
        document.getElementById('uploadSuccessMsg').style.display = 'none';
        renderSlots();
    });

    function syncPendingFileChrome() {
        const label = document.getElementById('fileChosenLabel');
        const list = document.getElementById('uploadPreviewList');
        const items = document.getElementById('uploadPreviewItems');
        const btn = document.getElementById('uploadImagesBtn');
        const input = document.getElementById('modalFileInput');

        if (!pendingFiles.length) {
            if (label) label.textContent = 'No file chosen';
            if (list) list.style.display = 'none';
            if (btn) btn.style.display = 'none';
            if (items) items.innerHTML = '';
            if (input) input.value = '';
            return;
        }

        if (label) {
            label.textContent = pendingFiles.length === 1
                ? pendingFiles[0].name
                : `${pendingFiles.length} files selected`;
        }
        if (list) list.style.display = 'block';
        if (btn) btn.style.display = 'inline-flex';
    }

    function reindexPendingPreviewCards() {
        document.querySelectorAll('#uploadPreviewItems .im-pending-card').forEach((card, idx) => {
            const delBtn = card.querySelector('.im-pending-del');
            if (delBtn) delBtn.dataset.idx = String(idx);
        });
    }

    function updatePendingFileUi() {
        const items = document.getElementById('uploadPreviewItems');
        if (!items) {
            syncPendingFileChrome();
            return;
        }

        items.innerHTML = '';
        if (!pendingFiles.length) {
            syncPendingFileChrome();
            return;
        }

        pendingFiles.forEach((f, idx) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const card = document.createElement('div');
                card.className = 'im-pending-card text-center';
                card.innerHTML = `
                    <div class="im-pending-img-wrap">
                        <img src="${esc(e.target.result)}" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;">
                        <button type="button" class="im-pending-del" data-idx="${idx}" title="Remove"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="small text-truncate mt-1" style="max-width:88px;" title="${esc(f.name)}">${esc(f.name)}</div>`;
                items.appendChild(card);
            };
            reader.readAsDataURL(f);
        });

        syncPendingFileChrome();
    }

    document.getElementById('uploadPreviewItems')?.addEventListener('click', (e) => {
        const btn = e.target.closest('.im-pending-del');
        if (!btn) return;
        const idx = +btn.dataset.idx;
        if (Number.isNaN(idx) || idx < 0 || idx >= pendingFiles.length) return;
        pendingFiles.splice(idx, 1);
        btn.closest('.im-pending-card')?.remove();
        if (!pendingFiles.length) {
            syncPendingFileChrome();
            return;
        }
        reindexPendingPreviewCards();
        syncPendingFileChrome();
    });

    // ── ADD IMAGES: two-step flow (preview → explicit upload) ───────────────────

    document.getElementById('modalFileInput')?.addEventListener('change', function () {
        pendingFiles = Array.from(this.files || []);
        document.getElementById('uploadSuccessMsg').style.display = 'none';
        updatePendingFileUi();
    });

    async function uploadPendingFiles() {
        const sku = document.getElementById('modalSku').value;
        if (!pendingFiles.length) return true;
        if (modalUrls.length >= PM_MAX_IMAGES) {
            toast(`You already have the maximum of ${PM_MAX_IMAGES} images. Remove some before adding more.`, false);
            return false;
        }

        // Enforce the image cap: if the selection would exceed it, add NOTHING and let the user
        // remove the extra files (or existing images) and try again — no silent partial add.
        const allowed = PM_MAX_IMAGES - modalUrls.length;
        if (pendingFiles.length > allowed) {
            toast(`Image limit is ${PM_MAX_IMAGES}. You already have ${modalUrls.length}, so only ${allowed} more can be added. You selected ${pendingFiles.length} — please remove ${pendingFiles.length - allowed} and try again.`, false);
            return false;
        }

        const btn     = document.getElementById('uploadImagesBtn');
        const spinner = document.getElementById('uploadSpinner');
        const msg     = document.getElementById('uploadSuccessMsg');

        if (btn) btn.disabled = true;
        if (spinner) spinner.style.display = 'inline-block';
        if (msg) msg.style.display = 'none';

        try {
            const fd = new FormData();
            fd.append('sku', sku);
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
                modalUrls = modalUrls.concat(j.urls).slice(0, PM_MAX_IMAGES);
                knownImageUrls = knownImageUrls.concat(j.urls);
                renderSlots();
                const count = j.urls.length;
                msg.textContent   = `${count} image${count > 1 ? 's' : ''} uploaded successfully!`;
                msg.style.display = 'inline';
                toast(`${count} image${count > 1 ? 's' : ''} uploaded`);
                pendingFiles = [];
                updatePendingFileUi();
            } else {
                toast(j.message || 'Upload failed', false);
                return false;
            }
        } catch (e) {
            toast(e.message || 'Upload error', false);
            return false;
        } finally {
            if (btn) btn.disabled = false;
            if (spinner) spinner.style.display = 'none';
        }
        return true;
    }

    document.getElementById('uploadImagesBtn')?.addEventListener('click', async function () {
        await uploadPendingFiles();
    });
    // ── END ADD IMAGES ───────────────────────────────────────────────────────────

    document.getElementById('fetchAmazonBtn')?.addEventListener('click', async () => {
        const sku = document.getElementById('modalSku').value;
        const r = await fetch('/image-master/amazon-images?sku='+encodeURIComponent(sku));
        const j = await r.json();
        if (j.success && j.images?.length) {
            const add = j.images.map(x => typeof x==='string'?x:(x.url||x.locator||'')).filter(Boolean);
            modalUrls = modalUrls.concat(add).slice(0, PM_MAX_IMAGES);
            knownImageUrls = knownImageUrls.concat(add);
            renderSlots();
            toast('Amz images loaded');
        } else toast(j.message || 'No Amz images', false);
    });

    async function fetchEbay(account) {
        const sku = document.getElementById('modalSku').value;
        const r = await fetch('/image-master/ebay-images?sku='+encodeURIComponent(sku)+'&account='+encodeURIComponent(account));
        const j = await r.json();
        if (j.success && j.images?.length) {
            modalUrls = modalUrls.concat(j.images).slice(0, PM_MAX_IMAGES);
            knownImageUrls = knownImageUrls.concat(j.images);
            renderSlots();
            toast('eBay images loaded');
        } else toast(j.message || 'No eBay images', false);
    }
    document.getElementById('fetchEbay1Btn')?.addEventListener('click', () => fetchEbay('ebay'));
    document.getElementById('fetchEbay2Btn')?.addEventListener('click', () => fetchEbay('ebay2'));
    document.getElementById('fetchEbay3Btn')?.addEventListener('click', () => fetchEbay('ebay3'));

    document.getElementById('savePmBtn')?.addEventListener('click', async () => {
        const sku = document.getElementById('modalSku').value;
        const saveBtn = document.getElementById('savePmBtn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        }

        try {
            const uploaded = await uploadPendingFiles();
            if (!uploaded) return;

            if (modalUrls.length === 0 && !confirm('Save this product with no images? This only clears Product Master images; it will not change marketplace listings.')) {
                return;
            }

            // Images seen this session but no longer in the list = removed by the user. The server
            // deletes only those (and only when they match a product_images row for THIS sku and are
            // not in the saved set), so a kept image can never be deleted by mistake.
            const removedUrls = knownImageUrls.filter(u => !modalUrls.includes(u));

            const r = await fetch('/image-master/save-pm', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ sku, images: modalUrls, main_by_marketplace: mainByMarketplacePayload(), removed_urls: removedUrls }),
            });
            const j = await r.json();
            if (j.success) {
                toast(modalUrls.length === 0 ? 'Product Master images cleared' : 'Images saved to Product Master');
                const savedRow = bySku.get(String(sku));
                if (savedRow && j.image_main_by_marketplace) {
                    savedRow.image_main_by_marketplace = j.image_main_by_marketplace;
                }
                loadData();
                if (editModal) editModal.hide();
            } else {
                toast(j.message || 'Save failed', false);
            }
        } catch (e) {
            toast(e.message || 'Save failed', false);
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Images';
            }
        }
    });

    // ── Push mode popup ────────────────────────────────────────────────────────
    let pushModeModal;
    if (window.bootstrap?.Modal) {
        pushModeModal = new bootstrap.Modal(document.getElementById('pushModeModal'));
    }

    function syncPushModeModal(checks, imageCount) {
        const addBtn = document.getElementById('pmAddBtn');
        const addHint = document.getElementById('pmAddHint');
        const addOk = checks.length > 0 && checks.every(mp => ADD_MODE_MARKETPLACES.includes(mp));
        if (addBtn) {
            addBtn.disabled = !addOk;
            addBtn.title = addOk ? '' : 'Add mode requires Shopify Main, Shopify PLS, and/or Reverb only';
        }
        if (addHint) addHint.style.display = addOk ? 'none' : 'block';
        const countEl = document.getElementById('pmImageCount');
        const mpCountEl = document.getElementById('pmMpCount');
        if (countEl) countEl.textContent = String(imageCount);
        if (mpCountEl) mpCountEl.textContent = String(checks.length);
    }

    function canPushImages(checks, imagesToPush) {
        if (imagesToPush.length > 0) return true;
        return checks.length > 0 && checks.every(mp => SHOPIFY_MARKETPLACES.includes(mp));
    }

    function confirmShopifyClear(checks) {
        const names = checks.map(mp => LABELS[mp] || mp).join(', ');
        return window.confirm(`Remove all images from ${names}? This only works for Shopify stores.`);
    }

    document.getElementById('pushModalBtn')?.addEventListener('click', () => {
        const sku    = document.getElementById('modalSku').value;
        const checks = Array.from(document.querySelectorAll('.im-mp-chk:checked:not(:disabled)')).map(c => c.value);
        if (!checks.length) { toast('Select at least one marketplace', false); return; }

        const imagesToPush = selectedUrls.size > 0
            ? modalUrls.filter(u => selectedUrls.has(u))
            : modalUrls;
        if (!canPushImages(checks, imagesToPush)) {
            toast('No images to push. Save images first, or select only Shopify to clear all images.', false);
            return;
        }
        if (imagesToPush.length === 0 && !confirmShopifyClear(checks)) return;
        if (!confirmEbay3Push(checks)) return;

        syncPushModeModal(checks, imagesToPush.length);
        if (pushModeModal) pushModeModal.show();
    });

    async function doPush(mode) {
        if (pushModeModal) pushModeModal.hide();

        const sku    = document.getElementById('modalSku').value;
        const checks = Array.from(document.querySelectorAll('.im-mp-chk:checked:not(:disabled)')).map(c => c.value);
        // Always iterate modalUrls to preserve visual grid order — never iterate the Set directly
        const imagesToPush = selectedUrls.size > 0
            ? modalUrls.filter(u => selectedUrls.has(u))   // grid order, selected only
            : [...modalUrls];                               // grid order, all

        if (!canPushImages(checks, imagesToPush)) {
            toast('No images to push. Save images first, or select only Shopify to clear all images.', false);
            return;
        }
        if (imagesToPush.length === 0 && mode === 'replace' && !confirmShopifyClear(checks)) return;

        const selLabel = selectedUrls.size > 0 ? `${imagesToPush.length} selected` : (imagesToPush.length ? `all ${imagesToPush.length}` : 'clear-all');
        const modeLabel = mode === 'add' ? 'adding to' : 'replacing';

        const progress = [];
        let okCount = 0, failCount = 0, metricsFailCount = 0;
        const updates = checks.map(mp => ({ marketplace: mp, images: imagesToPush }));
        try {
            setPushProgress(true, `Queuing push for ${checks.length} marketplace(s)…`, '', false, true);
            const { res, j } = await queueImagePushAndWait({
                sku,
                mode,
                updates,
                main_by_marketplace: mainByMarketplacePayload(),
            }, (st) => renderImagePushJobProgress(st, `Pushing ${selLabel} image(s) to ${checks.length} marketplace(s)…`));

            if (j && j.results) {
                okCount = 0;
                failCount = 0;
                metricsFailCount = Number(j.total_metrics_failed ?? 0);
                let idx = 0;
                for (const mp of checks) {
                    idx++;
                    const row = j.results[mp] ? j.results[mp] : null;
                    const rowOk = !!(row && row.success);
                    if (rowOk) { okCount++; } else { failCount++; }
                    progress.push(`${idx}/${checks.length} ${LABELS[mp]}: ${rowOk ? 'OK' : 'Failed'}${row && row.message ? ` - ${esc(row.message)}` : ''}`);
                }
            } else if (!res.ok) {
                failCount = checks.length;
                progress.push(esc(j?.message || `HTTP ${res.status}`));
            }
            const hasAnyError = failCount > 0;
            setPushProgress(true, `Push finished: ${okCount} success, ${failCount} failed${metricsFailCount ? `, ${metricsFailCount} metrics save failed` : ''}`, progress.join('<br>'), hasAnyError);
            if (!hasAnyError) {
                toast(`Pushed to ${okCount} marketplace(s).${metricsFailCount ? ` ${metricsFailCount} metrics save failed.` : ''}`, !metricsFailCount);
                loadData();
                if (editModal) editModal.hide();
                // Progress stays visible until the user closes it (the ✕ on the progress box).
            } else {
                toast(`Push completed with failures (${failCount}). See error details below.`, false);
                loadData();
                // Keep failures visible until user clicks elsewhere — don't auto-hide
            }
        } catch (e) {
            failCount = checks.length;
            setPushProgress(true, 'Push failed', esc(e.message || 'Request failed'), true);
            toast(e.message || 'Push failed', false);
        } finally {
            // spinner already stopped in setPushProgress(hasError=true); do nothing here
        }
    }

    document.getElementById('pmReplaceBtn')?.addEventListener('click', () => doPush('replace'));
    document.getElementById('pmAddBtn')?.addEventListener('click', ()     => doPush('add'));
    // ── End push mode popup ────────────────────────────────────────────────────

    function quickPush(sku, mp) {
        if (!marketplaceApiGuard(mp, LABELS[mp])) return;
        const row = bySku.get(String(sku));
        if (!row) return;
        const urls = pmImageUrls(row);
        if (!urls.length) { toast('No images on Product Master for this SKU — open Edit first.', false); openEditModal(sku); return; }
        if (!confirmEbay3Push([mp])) return;
        const mainIdx = mainImageIndexFor(mp, row);
        const mainLabel = mainImageLabelFor(mp, mainIdx);
        if (!window.confirm(`Push ${urls.length} image(s) for ${sku} to ${LABELS[mp]}?\n\nMain image for this platform: ${mainLabel} (sent first).\nThis will replace existing marketplace images.`)) return;
        setPushProgress(true, `Queuing ${LABELS[mp]} push…`, '', false, true);
        queueImagePushAndWait({
            sku,
            mode: 'replace',
            updates: [{ marketplace: mp, images: urls }],
            main_by_marketplace: mainByMarketplacePayloadFromRow(row),
        }, (st) => renderImagePushJobProgress(st, `Pushing to ${LABELS[mp]}…`))
        .then(({ res, j }) => {
            const rowRes = (j.results && j.results[mp]) ? j.results[mp] : null;
            const ok = !!(rowRes && rowRes.success);
            const msg = ok ? (rowRes?.message || j.message || 'Updated') : pushFailureMessage(j, mp, res.status);
            setPushProgress(true, `Push finished: ${ok ? '1 success' : '1 failed'}`, `1/1 ${LABELS[mp]}: ${ok ? '✓ OK' : '✗ Failed'} — ${esc(msg)}`, !ok);
            if (ok) { toast(LABELS[mp]+' pushed'); loadData(); }
            else toast(msg, false);
        }).catch(e => {
            setPushProgress(true, 'Push finished: 1 failed', `1/1 ${LABELS[mp]}: ✗ Failed — ${esc(e.message || 'Request failed')}`, true);
            toast(e.message, false);
        });
    }

    document.getElementById('skuSearchIm')?.addEventListener('input', () => renderTable(tableData));

    document.getElementById('exportBtn')?.addEventListener('click', () => {
        const rows = tableData.map(row => {
            const o = {
                SKU: row.SKU,
                'Image Count': pmImageUrls(row).length,
                Product: row.Parent||'',
                Preview: row.preview_thumb||'',
            };
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

    let bulkPushAllRunning = false;

    function failedMarketplaceSummary(results) {
        if (!results || typeof results !== 'object') return '';
        const failed = MARKETPLACES.filter(mp => results[mp] && !results[mp].success);
        if (!failed.length) return '';
        return failed.map(mp => LABELS[mp] || mp).join(', ');
    }

    function rowsForBulkPush(mode) {
        const filtered = currentFilteredRowsForPull().filter(r => pmImageUrls(r).length > 0);
        if (mode === 'selected') {
            return filtered.filter(r => selectedSkus.has(String(r.SKU || '')));
        }
        return filtered;
    }

    async function bulkPushAll(mode = 'all') {
        if (bulkPushAllRunning) {
            toast('Bulk push is already running.', false);
            return;
        }
        if (mode === 'selected' && selectedSkus.size === 0) {
            toast('Select at least one row (checkbox) before Push Selected.', false);
            return;
        }
        const rows = rowsForBulkPush(mode);
        if (!rows.length) {
            toast(mode === 'selected'
                ? 'No selected products with images to push.'
                : 'No products with images in the current view.', false);
            return;
        }
        const configuredMps = typeof filterConfiguredMarketplaces === 'function'
            ? filterConfiguredMarketplaces(ENABLED_MARKETPLACES)
            : ENABLED_MARKETPLACES.filter(mp => isMarketplaceApiConfigured(mp));
        if (!configuredMps.length) {
            toast('No marketplaces have API credentials configured.', false);
            return;
        }
        const mpCount = configuredMps.length;
        const skuLabel = rows.length === 1 ? '1 product' : `${rows.length} products`;
        const scopeLabel = mode === 'selected' ? 'selected ' : '';
        if (!window.confirm(
            `Push images to ${mpCount} configured marketplace(s) for ${scopeLabel}${skuLabel}?\n\n`
            + 'Per-platform main image settings (Image 1 by default) will be applied.\n'
            + 'Each product is sent in one batch; this may take a long time.'
        )) return;
        if (!confirmEbay3Push(configuredMps)) return;

        bulkPushAllRunning = true;
        const pushAllBtn = document.getElementById('pushAllBtn');
        const pushSelectedBtn = document.getElementById('pushSelectedBtn');
        if (pushAllBtn) pushAllBtn.disabled = true;
        if (pushSelectedBtn) pushSelectedBtn.disabled = true;

        let okSkus = 0;
        let failSkus = 0;
        const progress = [];

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const sku = String(row.SKU || '');
            const urls = pmImageUrls(row);
            if (!urls.length) continue;

            const updates = configuredMps.map(mp => ({ marketplace: mp, images: urls }));
            const main_by_marketplace = mainByMarketplacePayloadFromRow(row);
            setPushProgress(
                true,
                `Bulk push ${i + 1}/${rows.length}: queuing ${sku}… (${mpCount} marketplaces)`,
                progress.slice(-40).join('<br>'),
                false,
                true
            );

            try {
                const { res, j } = await queueImagePushAndWait(
                    { sku, mode: 'replace', updates, main_by_marketplace },
                    (st) => renderImagePushJobProgress(st, `Bulk push ${i + 1}/${rows.length}: ${sku}…`)
                );
                const mpFailed = Number(j?.total_failed ?? 0);
                const mpOk = Number(j?.total_success ?? 0);
                const metricsFailed = Number(j?.total_metrics_failed ?? 0);
                const batchOk = res.ok && j && mpFailed === 0 && (mpOk > 0 || j.success);
                if (batchOk) {
                    okSkus++;
                    const metricsNote = metricsFailed > 0 ? ` (${metricsFailed} metrics save failed)` : '';
                    progress.push(`✓ ${esc(sku)} — ${mpOk}/${mpCount} marketplace(s)${metricsNote}`);
                } else {
                    failSkus++;
                    const failedMps = failedMarketplaceSummary(j?.results);
                    const errMsg = j?.message || (mpFailed ? `${mpFailed} marketplace(s) failed` : `HTTP ${res.status}`);
                    progress.push(`✗ ${esc(sku)} — ${esc(errMsg)}${failedMps ? ` (${esc(failedMps)})` : ''}`);
                }
            } catch (e) {
                failSkus++;
                progress.push(`✗ ${esc(sku)} — ${esc(e.message || 'Request failed')}`);
            }

            if (i < rows.length - 1) await sleepMs(1500);
        }

        bulkPushAllRunning = false;
        if (pushAllBtn) pushAllBtn.disabled = false;
        if (pushSelectedBtn) pushSelectedBtn.disabled = false;

        const hasError = failSkus > 0;
        setPushProgress(
            true,
            `Bulk push finished: ${okSkus} SKU(s) ok, ${failSkus} failed`,
            progress.join('<br>'),
            hasError
        );
        toast(`Bulk push done: ${okSkus} ok, ${failSkus} failed`, !hasError);
        loadData();
        // Progress stays visible until the user closes it (the ✕ on the progress box).
    }

    document.getElementById('selectAllRowsIm')?.addEventListener('change', function() {
        fillAllVisibleRows(!!this.checked);
    });
    document.getElementById('pushSelectedBtn')?.addEventListener('click', () => bulkPushAll('selected'));
    document.getElementById('pushAllBtn')?.addEventListener('click', () => bulkPushAll('all'));

    // ── Shopify image pull (mirrors Bullet Points pull) ───────────────────────

    function appendShopifyPullLog(message, ok = true) {
        const log = document.getElementById('shopifyPullLog');
        if (!log) return;
        const line = document.createElement('div');
        line.className = ok ? 'text-success' : 'text-danger';
        line.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
        log.appendChild(line);
        log.scrollTop = log.scrollHeight;
    }

    function setShopifyPullProgress(done, total, text) {
        const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        const bar = document.getElementById('shopifyPullProgress');
        const status = document.getElementById('shopifyPullStatus');
        if (bar) bar.style.width = pct + '%';
        if (status) status.textContent = text || `${done}/${total}`;
    }

    function currentFilteredRowsForPull() {
        const skuQ = (document.getElementById('skuSearchIm')?.value || '').trim().toLowerCase();
        return tableData.filter(r => {
            const sku = String(r.SKU || '');
            if (!sku || sku.toUpperCase().includes('PARENT')) return false;
            if (skuQ && !sku.toLowerCase().includes(skuQ)) return false;
            return true;
        });
    }

    function isShopifyPullActive(status) {
        return ['running', 'paused', 'stopping'].includes(status);
    }

    function renderShopifyPullJob(job) {
        job = job || {};
        const log = document.getElementById('shopifyPullLog');
        const pullBtn = document.getElementById('startShopifyPullBtn');
        const pauseBtn = document.getElementById('pauseShopifyPullBtn');
        const resumeBtn = document.getElementById('resumeShopifyPullBtn');
        const stopBtn = document.getElementById('stopShopifyPullBtn');
        const status = job.status || 'idle';
        const total = Number(job.total || 0);
        const done = Number(job.current_index || 0);
        const active = isShopifyPullActive(status);

        if (pullBtn) pullBtn.disabled = active;
        if (pauseBtn) pauseBtn.style.display = status === 'running' ? 'inline-block' : 'none';
        if (resumeBtn) resumeBtn.style.display = status === 'paused' ? 'inline-block' : 'none';
        if (stopBtn) stopBtn.style.display = active ? 'inline-block' : 'none';

        let text = job.last_message || 'Ready';
        if (status === 'running' && job.current_sku) text = `Running ${done + 1}/${total}: ${job.current_sku}`;
        if (status === 'paused') text = `Paused ${done}/${total}`;
        if (status === 'completed') text = `Done: ${job.ok_count || 0} ok, ${job.fail_count || 0} failed`;
        if (status === 'stopped') text = `Stopped: ${job.ok_count || 0} ok, ${job.fail_count || 0} failed`;
        setShopifyPullProgress(done, total, text);

        if (log) {
            log.innerHTML = '';
            (job.messages || []).forEach(item => {
                const line = document.createElement('div');
                line.className = item.ok ? 'text-success' : 'text-danger';
                line.textContent = `[${item.time || ''}] ${item.message || ''}`;
                log.appendChild(line);
            });
            log.scrollTop = log.scrollHeight;
        }
    }

    async function fetchShopifyPullStatus() {
        const res = await fetch('/image-master/shopify-pull/status', {
            headers: { 'Accept': 'application/json' }
        });
        const payload = await res.json().catch(() => ({}));
        if (!res.ok || !payload.success) throw new Error(payload.message || 'Unable to load Shopify pull status');
        return payload.job || {};
    }

    function clearShopifyPullChunkQueue(cancelled = false) {
        shopifyPullChunkQueue = [];
        if (cancelled) shopifyPullChunkMeta.cancelled = true;
    }

    function chunkArray(list, size) {
        const out = [];
        for (let i = 0; i < list.length; i += size) out.push(list.slice(i, i + size));
        return out;
    }

    async function maybeStartNextShopifyPullChunk(finishedJob) {
        if (shopifyPullAdvancingChunk) return;
        if (shopifyPullChunkMeta.cancelled) {
            clearShopifyPullChunkQueue();
            return;
        }
        if (!shopifyPullChunkQueue.length) {
            if (shopifyPullChunkMeta.chunkCount > 1 && finishedJob) {
                const ok = shopifyPullChunkMeta.ok + Number(finishedJob.ok_count || 0);
                const fail = shopifyPullChunkMeta.fail + Number(finishedJob.fail_count || 0);
                const chunksDone = shopifyPullChunkMeta.chunkCount;
                shopifyPullChunkMeta.chunkCount = 0; // prevent repeat toast on later polls
                toast(`Shopify pull finished all chunks: ${ok} ok, ${fail} failed`, fail === 0);
                appendShopifyPullLog(`All ${chunksDone} chunk(s) finished: ${ok} ok, ${fail} failed.`, fail === 0);
            }
            return;
        }

        shopifyPullAdvancingChunk = true;
        try {
            if (finishedJob) {
                shopifyPullChunkMeta.ok += Number(finishedJob.ok_count || 0);
                shopifyPullChunkMeta.fail += Number(finishedJob.fail_count || 0);
            }

            const nextChunk = shopifyPullChunkQueue.shift();
            shopifyPullChunkMeta.chunkIndex += 1;
            const label = `chunk ${shopifyPullChunkMeta.chunkIndex}/${shopifyPullChunkMeta.chunkCount} (${nextChunk.length} SKU(s))`;
            appendShopifyPullLog(`Starting ${label}…`, true);
            toast(`Starting Shopify pull ${label}…`);

            const res = await fetch('/image-master/shopify-pull/start', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ skus: nextChunk })
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok || !payload.success) throw new Error(payload.message || 'Unable to start next pull chunk');
            renderShopifyPullJob(payload.job);
            startShopifyPullPolling();
        } catch (e) {
            appendShopifyPullLog('Next chunk failed to start: ' + e.message, false);
            toast('Shopify pull chunk failed: ' + e.message, false);
            clearShopifyPullChunkQueue(true);
        } finally {
            shopifyPullAdvancingChunk = false;
        }
    }

    async function pollShopifyPullStatus() {
        try {
            const job = await fetchShopifyPullStatus();
            const wasActive = shopifyPullPollTimer !== null;
            const status = job.status || 'idle';
            renderShopifyPullJob(job);
            if (isShopifyPullActive(status)) {
                startShopifyPullPolling();
                return;
            }
            stopShopifyPullPolling();
            if (status === 'stopped') {
                clearShopifyPullChunkQueue(true);
                if (wasActive) loadData();
                return;
            }
            if (status === 'completed') {
                const hadMoreChunks = shopifyPullChunkQueue.length > 0 && !shopifyPullChunkMeta.cancelled;
                await maybeStartNextShopifyPullChunk(job);
                if (wasActive && !hadMoreChunks) loadData();
                else if (hadMoreChunks) { /* next chunk polling already started */ }
                else if (wasActive) loadData();
            }
        } catch (e) {
            appendShopifyPullLog('Status check failed: ' + e.message, false);
        }
    }

    function startShopifyPullPolling() {
        if (shopifyPullPollTimer !== null) return;
        shopifyPullPollTimer = window.setInterval(pollShopifyPullStatus, 3000);
    }

    function stopShopifyPullPolling() {
        if (shopifyPullPollTimer === null) return;
        window.clearInterval(shopifyPullPollTimer);
        shopifyPullPollTimer = null;
    }

    async function openShopifyPullModal(skus = null) {
        shopifyPullSelectedSkus = Array.isArray(skus) && skus.length
            ? skus.map(sku => String(sku || '').trim()).filter(Boolean)
            : null;
        const scope = document.getElementById('shopifyPullScopeText');
        if (scope) {
            if (shopifyPullSelectedSkus) {
                const n = shopifyPullSelectedSkus.length;
                const preview = shopifyPullSelectedSkus.slice(0, 8).join(', ');
                scope.textContent = n === 1
                    ? `Scope: selected SKU ${preview}.`
                    : `Scope: ${n} selected SKU(s) — ${preview}${n > 8 ? '…' : ''}. Pulled in chunks of ${SHOPIFY_PULL_CHUNK_SIZE}.`;
            } else {
                scope.textContent = `Scope: currently filtered SKUs. Pulled in chunks of ${SHOPIFY_PULL_CHUNK_SIZE}.`;
            }
        }
        if (shopifyPullModal) shopifyPullModal.show();
        await pollShopifyPullStatus();
    }

    function confirmShopifyPull(scopeText) {
        const scope = document.getElementById('shopifyPullConfirmScope');
        if (scope) {
            scope.textContent = `Do you want to pull images from Shopify for ${scopeText}?`;
        }

        return new Promise(resolve => {
            shopifyPullConfirmResolver = resolve;
            if (shopifyPullConfirmModal) {
                shopifyPullConfirmModal.show();
            } else {
                resolve(false);
            }
        });
    }

    async function startShopifyPullJobForSkus(skus, options = {}) {
        skus = (skus || []).map(sku => String(sku || '').trim()).filter(Boolean);
        if (!skus.length) {
            toast('No SKUs loaded to pull from Shopify.', false);
            return false;
        }

        const chunks = chunkArray(skus, SHOPIFY_PULL_CHUNK_SIZE);
        const chunkNote = chunks.length > 1
            ? ` in ${chunks.length} chunks of up to ${SHOPIFY_PULL_CHUNK_SIZE}`
            : '';
        const scopeText = options.scopeText || `${skus.length} SKU(s)${chunkNote}`;
        if (!await confirmShopifyPull(scopeText)) {
            return false;
        }

        shopifyPullChunkMeta = {
            totalSkus: skus.length,
            chunkIndex: 1,
            chunkCount: chunks.length,
            ok: 0,
            fail: 0,
            cancelled: false,
        };
        shopifyPullChunkQueue = chunks.slice(1);
        const firstChunk = chunks[0];

        try {
            const res = await fetch('/image-master/shopify-pull/start', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ skus: firstChunk })
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok || !payload.success) throw new Error(payload.message || 'Unable to start Shopify pull');
            renderShopifyPullJob(payload.job);
            startShopifyPullPolling();
            const startedMsg = chunks.length > 1
                ? `Background Shopify pull started (chunk 1/${chunks.length}, ${firstChunk.length} SKU(s)). Remaining chunks start automatically.`
                : (options.successMessage || payload.message || 'Background Shopify image pull started.');
            if (chunks.length > 1) {
                appendShopifyPullLog(`Queued ${chunks.length} chunk(s) for ${skus.length} SKU(s).`, true);
            }
            toast(startedMsg);
            return true;
        } catch (e) {
            clearShopifyPullChunkQueue(true);
            toast('Shopify pull start failed: ' + e.message, false);
            if (e.message.includes('already')) pollShopifyPullStatus();
            return false;
        }
    }

    async function startSingleShopifyPull(sku, btn) {
        sku = String(sku || '').trim();
        if (!sku) {
            toast('SKU missing for Shopify pull.', false);
            return;
        }

        const oldHtml = btn ? btn.innerHTML : '';
        const oldTitle = btn ? btn.getAttribute('title') : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.setAttribute('title', 'Syncing Shopify images...');
        }

        const started = await startShopifyPullJobForSkus([sku], {
            scopeText: `SKU ${sku}`,
            successMessage: `Shopify image sync started for ${sku}.`,
        });

        if (!started && btn) {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            if (oldTitle) btn.setAttribute('title', oldTitle);
        }
    }

    async function startShopifyPullToLocal() {
        const fromCheckboxes = Array.from(selectedSkus).filter(Boolean);
        const rows = (shopifyPullSelectedSkus && shopifyPullSelectedSkus.length)
            ? shopifyPullSelectedSkus.map(sku => ({ SKU: sku }))
            : (fromCheckboxes.length
                ? fromCheckboxes.map(sku => ({ SKU: sku }))
                : currentFilteredRowsForPull());
        const skus = rows.map(row => String(row.SKU || '').trim()).filter(Boolean);
        if (!skus.length) {
            toast('No SKUs loaded to pull from Shopify.', false);
            return;
        }
        const scopeText = (shopifyPullSelectedSkus && shopifyPullSelectedSkus.length) || fromCheckboxes.length
            ? `${skus.length} selected SKU(s)`
            : `${skus.length} currently filtered SKU(s)`;
        await startShopifyPullJobForSkus(skus, { scopeText });
    }

    async function controlShopifyPull(action) {
        try {
            if (action === 'stop') clearShopifyPullChunkQueue(true);
            const res = await fetch(`/image-master/shopify-pull/${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({})
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok || !payload.success) throw new Error(payload.message || `Unable to ${action} Shopify pull`);
            renderShopifyPullJob(payload.job);
            if (isShopifyPullActive((payload.job || {}).status || 'idle')) startShopifyPullPolling();
            toast(`Shopify pull ${action} requested.`);
        } catch (e) {
            toast(`Shopify pull ${action} failed: ${e.message}`, false);
        }
    }

    document.getElementById('pullShopifyBtn')?.addEventListener('click', () => {
        const selected = Array.from(selectedSkus).filter(Boolean);
        openShopifyPullModal(selected.length ? selected : null);
    });
    document.getElementById('startShopifyPullBtn')?.addEventListener('click', startShopifyPullToLocal);
    document.getElementById('pauseShopifyPullBtn')?.addEventListener('click', () => controlShopifyPull('pause'));
    document.getElementById('resumeShopifyPullBtn')?.addEventListener('click', () => controlShopifyPull('resume'));
    document.getElementById('stopShopifyPullBtn')?.addEventListener('click', () => controlShopifyPull('stop'));
    document.getElementById('shopifyPullConfirmBtn')?.addEventListener('click', () => {
        if (shopifyPullConfirmResolver) shopifyPullConfirmResolver(true);
        shopifyPullConfirmResolver = null;
        if (shopifyPullConfirmModal) shopifyPullConfirmModal.hide();
    });
    document.getElementById('shopifyPullConfirmModal')?.addEventListener('hidden.bs.modal', () => {
        if (shopifyPullConfirmResolver) shopifyPullConfirmResolver(false);
        shopifyPullConfirmResolver = null;
    });

    if (window.bootstrap?.Modal) {
        editModal = new bootstrap.Modal(document.getElementById('editImModal'));
        shopifyPullModal = new bootstrap.Modal(document.getElementById('shopifyPullModal'));
        shopifyPullConfirmModal = new bootstrap.Modal(document.getElementById('shopifyPullConfirmModal'));
    }
    document.getElementById('mpSelAllBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('#editImModal .im-mp-chk:not(:disabled)').forEach(c => { c.checked = true; });
    });
    document.getElementById('mpSelNoneBtn')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelectorAll('#editImModal .im-mp-chk').forEach(c => { c.checked = false; });
    });
    loadData();
    pollShopifyPullStatus();
    resumeImagePushProgress();
});
</script>
@endsection

