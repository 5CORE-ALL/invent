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
        .btn-ebay1 { background-color:#0d6efd; } .btn-ebay2 { background-color:#198754; } .btn-ebay3 { background-color:#fd7e14; }
        .btn-macy { background-color:#0d6efd; } .btn-amazon { background-color:#ff9900; color:#232f3e!important; }
        .btn-temu { background-color:#ff6b00; } .btn-reverb { background-color:#333333; }
        .btn-shopify { background-color:#7cb342; } .btn-shopify-pls { background-color:#5c6bc0; }
        .btn-wayfair { background-color:#7a3ff2; } .btn-bestbuy { background-color:#0046be; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        /* ── Image card grid ─────────────────────────────────────── */
        .im-grid { display:flex; flex-wrap:wrap; gap:10px; min-height:40px; padding:4px 0; }
        .im-card { width:120px; border:2px solid #e2e8f0; border-radius:10px; overflow:hidden; background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.06); transition:box-shadow .15s,border-color .15s; user-select:none; position:relative; }
        .im-card:hover { box-shadow:0 3px 12px rgba(44,110,213,.18); border-color:#93c5fd; }
        .im-card.im-card-dragging { opacity:.45; border:2px dashed #6366f1; }
        .im-card-img-wrap { position:relative; width:120px; height:100px; background:#f1f5f9; overflow:hidden; }
        .im-card-img-wrap img { width:100%; height:100%; object-fit:cover; display:block; }
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
        /* selection bar */
        .im-select-bar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:6px; }
        .im-select-info { font-size:11px; font-weight:600; color:#6366f1; background:#eef2ff; border-radius:5px; padding:2px 8px; }
        /* ─────────────────────────────────────────────────────────── */
        .toast-container { z-index:1100; }
        .shopify-row-pull-btn { background:#f59e0b; color:#fff; border:none; padding:5px 8px; border-radius:4px; }
        .shopify-row-pull-btn:hover { background:#d97706; color:#fff; }
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
                        <button type="button" id="pullShopifyBtn" class="btn btn-warning btn-sm"><i class="fas fa-download"></i> Shopify Pull</button>
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
                                    <th title="eBay1–3, Macy's, Amazon, Temu, Reverb, Wayfair, Best Buy">
                                        <div class="bp-mp-th-title">MARKET PLACES</div>
                                        <div class="bp-mp-th-icons">
                                            <span class="bp-mp-th-pill btn-ebay1">E1</span><span class="bp-mp-th-pill btn-ebay2">E2</span><span class="bp-mp-th-pill btn-ebay3">E3</span><span class="bp-mp-th-pill btn-macy">M</span><span class="bp-mp-th-pill btn-amazon">A</span><span class="bp-mp-th-pill btn-temu">T</span><span class="bp-mp-th-pill btn-reverb">R</span><span class="bp-mp-th-pill btn-wayfair">W</span><span class="bp-mp-th-pill btn-bestbuy">B</span>
                                        </div>
                                    </th>
                                    <th title="Shopify Main, Shopify PLS">
                                        <div class="bp-mp-th-title">SHOPIFY</div>
                                        <div class="bp-mp-th-icons">
                                            <span class="bp-mp-th-pill btn-shopify">SM</span><span class="bp-mp-th-pill btn-shopify-pls">PLS</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>
                    <div id="pushProgressBox" class="alert alert-info mt-3 py-2 px-3" style="display:none;word-break:break-word;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="spinner-border spinner-border-sm text-primary" id="pushSpinner" role="status"></div>
                            <div class="small fw-semibold flex-grow-1" id="pushProgressTitle">Pushing...</div>
                            <button type="button" class="btn-close btn-sm" id="pushProgressClose" style="font-size:10px;" onclick="document.getElementById('pushProgressBox').style.display='none'"></button>
                        </div>
                        <div class="small mt-2" id="pushProgressDetails" style="max-height:200px;overflow-y:auto;white-space:pre-wrap;"></div>
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
                            <span class="small text-muted">Max 12 images. Drag cards to reorder before saving.</span>
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
                        <span class="text-muted small ms-auto">Drag cards to reorder</span>
                    </div>
                    <div id="imSlots"></div>
                    <div id="imMainByMpSection" class="im-main-mp-section mt-3" style="display:none;">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2 flex-wrap">
                            <span class="fw-semibold small"><i class="fas fa-star text-warning me-1"></i>Main image per marketplace</span>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="resetMainAllBtn">Reset all to #1</button>
                        </div>
                        <p class="small text-muted mb-2 mb-md-3">When you push, the selected image is sent <strong>first</strong> as the main/hero image on that platform. Default is image #1 for every marketplace.</p>
                        <div id="imMainByMpGrid" class="row g-2"></div>
                    </div>
                    <div class="alert alert-info small mt-3 mb-0">
                        Save images here first. Use the marketplace buttons in the table row after saving when you are ready to push images.
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
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const MARKETPLACES = ['ebay','ebay2','ebay3','amazon','temu','wayfair','bestbuy','macy','reverb','shopify_main','shopify_pls'];
    const ENABLED_MARKETPLACES = ['ebay','ebay2','ebay3','amazon','temu','wayfair','bestbuy','macy','reverb','shopify_main','shopify_pls'];
    const LABELS = {
        ebay:'eBay1', ebay2:'eBay2', ebay3:'eBay3', amazon:'Amazon', temu:'Temu', wayfair:'Wayfair', bestbuy:'Best Buy', macy:"Macy's", reverb:'Reverb',
        shopify_main:'Shopify Main', shopify_pls:'Shopify PLS',
    };
    const MP_TILE = {
        ebay:'btn-ebay1', ebay2:'btn-ebay2', ebay3:'btn-ebay3', amazon:'btn-amazon', temu:'btn-temu', wayfair:'btn-wayfair', bestbuy:'btn-bestbuy', macy:'btn-macy', reverb:'btn-reverb',
        shopify_main:'btn-shopify', shopify_pls:'btn-shopify-pls',
    };
    const MP_SHORT = {
        ebay:'E1', ebay2:'E2', ebay3:'E3', amazon:'A', temu:'T', wayfair:'W', bestbuy:'B', macy:'M', reverb:'R',
        shopify_main:'SM', shopify_pls:'PLS',
    };
    const GROUPS = {
        gChannels: ['ebay','ebay2','ebay3','macy','amazon','temu','reverb','wayfair','bestbuy'],
        gShopify: ['shopify_main','shopify_pls'],
    };
    const ADD_MODE_MARKETPLACES = ['shopify_main', 'shopify_pls', 'reverb'];
    const SHOPIFY_MARKETPLACES = ['shopify_main', 'shopify_pls'];
    const MP_IMAGE_LIMITS = {
        ebay: 12, ebay2: 12, ebay3: 12, amazon: 9, temu: 12, wayfair: 12, bestbuy: 12, macy: 12, reverb: 25,
        shopify_main: 20, shopify_pls: 20,
    };
    const EBAY3_WARN = 'eBay3 has different listing structure. Please verify images before pushing.';

    let tableData = [];
    const bySku = new Map();
    let editModal;
    let shopifyPullModal;
    let shopifyPullConfirmModal;
    let shopifyPullPollTimer = null;
    let shopifyPullSelectedSkus = null;
    let shopifyPullConfirmResolver = null;
    let modalUrls = [];
    let modalMainByMp = {};
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

    function setPushProgress(visible, title = '', detailsHtml = '', hasError = false) {
        const box = document.getElementById('pushProgressBox');
        const t = document.getElementById('pushProgressTitle');
        const d = document.getElementById('pushProgressDetails');
        const spinner = document.getElementById('pushSpinner');
        if (!box || !t || !d) return;
        box.style.display = visible ? 'block' : 'none';
        if (title) t.textContent = title;
        d.innerHTML = detailsHtml || '';
        if (spinner) spinner.style.display = visible && !hasError ? 'inline-block' : 'none';
        box.className = 'alert mt-3 py-2 px-3' + (hasError ? ' alert-danger' : ' alert-info');
        box.style.wordBreak = 'break-word';
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
        const enabled = ENABLED_MARKETPLACES.includes(mp);
        const title = enabled ? LABELS[mp] : `${LABELS[mp]} image push is not implemented yet`;
        return `<button type="button" class="bp-mp-stack" data-push-mp="${mp}" data-sku="${esc(sku)}" title="${esc(title)}" ${enabled ? '' : 'disabled'}>
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
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No products</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const sku = String(r.SKU||'');
            const thumb = r.preview_thumb || r.main_image || r.image1 || r.image_path || '';
            const thumbImg = thumb ? `<img class="im-thumb" src="${esc(imgSrc(thumb))}" alt="">` : '<span class="text-muted">—</span>';
            return `<tr data-sku="${esc(sku)}">
                <td>${esc(sku)}</td>
                <td>${esc(r.Parent||sku)}</td>
                <td>${thumbImg}</td>
                <td>
                    <div class="d-flex gap-1 flex-wrap align-items-center">
                        <button type="button" class="btn btn-sm btn-primary edit-btn" data-edit="${esc(sku)}"><i class="fas fa-edit"></i> Edit</button>
                        <button type="button" class="shopify-row-pull-btn" data-shopify-pull-sku="${esc(sku)}" title="Pull Shopify images for this SKU"><i class="fas fa-download"></i></button>
                    </div>
                </td>
                <td>${groupCell('gChannels', sku, r)}</td>
                <td>${groupCell('gShopify', sku, r)}</td>
            </tr>`;
        }).join('');
        document.querySelectorAll('.edit-btn[data-edit]').forEach(b => b.addEventListener('click', () => openEditModal(b.dataset.edit)));
        document.querySelectorAll('.shopify-row-pull-btn[data-shopify-pull-sku]').forEach(b => b.addEventListener('click', () => startSingleShopifyPull(b.dataset.shopifyPullSku, b)));
        document.querySelectorAll('.bp-mp-stack[data-push-mp]:not(:disabled)').forEach(b => b.addEventListener('click', () => quickPush(b.dataset.sku, b.dataset.pushMp)));
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
                out[mp] = Math.max(0, Math.min(11, parseInt(raw[mp], 10) || 0));
            }
        });
        return out;
    }

    function clampMainIndicesAfterDelete(deletedIdx) {
        MARKETPLACES.forEach(mp => {
            let idx = modalMainByMp[mp] ?? 0;
            if (idx === deletedIdx) idx = 0;
            else if (idx > deletedIdx) idx--;
            modalMainByMp[mp] = Math.max(0, idx);
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
        });
    }

    function clampMainIndicesToLength() {
        const max = Math.max(0, modalUrls.length - 1);
        MARKETPLACES.forEach(mp => {
            let idx = modalMainByMp[mp] ?? 0;
            if (modalUrls.length === 0) idx = 0;
            else if (idx > max) idx = 0;
            modalMainByMp[mp] = idx;
        });
    }

    function mainByMarketplacePayload() {
        const out = {};
        const max = Math.max(0, modalUrls.length - 1);
        MARKETPLACES.forEach(mp => {
            const idx = Math.max(0, Math.min(max, modalMainByMp[mp] ?? 0));
            if (idx > 0) out[mp] = idx;
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
        return out;
    }

    function mainImageLabelFor(mp, idx) {
        return `Image ${(idx ?? 0) + 1}`;
    }

    function mainImageIndexFor(mp, source) {
        if (source === 'modal') return modalMainByMp[mp] ?? 0;
        return normalizeMainByMarketplace(source?.image_main_by_marketplace)[mp] ?? 0;
    }

    function renderMainByMpSelectors() {
        const section = document.getElementById('imMainByMpSection');
        const grid = document.getElementById('imMainByMpGrid');
        if (!section || !grid) return;
        if (!modalUrls.length) {
            section.style.display = 'none';
            grid.innerHTML = '';
            return;
        }
        section.style.display = 'block';
        clampMainIndicesToLength();
        grid.innerHTML = MARKETPLACES.map(mp => {
            const idx = modalMainByMp[mp] ?? 0;
            const opts = modalUrls.map((url, i) =>
                `<option value="${i}"${i === idx ? ' selected' : ''}>Image ${i + 1}</option>`
            ).join('');
            return `<div class="col-6 col-md-4 col-lg-3">
                <div class="im-main-mp-row">
                    <label for="im-main-${mp}">${esc(LABELS[mp] || mp)}</label>
                    <select id="im-main-${mp}" class="form-select form-select-sm im-main-mp-select" data-mp="${mp}">${opts}</select>
                </div>
            </div>`;
        }).join('');
        grid.querySelectorAll('.im-main-mp-select').forEach(sel => {
            sel.addEventListener('change', () => {
                modalMainByMp[sel.dataset.mp] = parseInt(sel.value, 10) || 0;
                renderSlots();
            });
        });
    }

    function pmImageUrls(row) {
        const u = [];
        for (let i=1;i<=12;i++) {
            const v = row['image'+i];
            if (v && String(v).trim()) u.push(String(v).trim());
        }
        if (u.length) return u;
        ['main_image'].forEach(k => {
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
        modalUrls = pmImageUrls(row);
        modalMainByMp = normalizeMainByMarketplace(row.image_main_by_marketplace);
        renderSlots();
        // Reset the upload section
        pendingFiles = [];
        document.getElementById('modalFileInput').value = '';
        document.getElementById('fileChosenLabel').textContent = 'No file chosen';
        document.getElementById('uploadPreviewList').style.display = 'none';
        document.getElementById('uploadPreviewItems').innerHTML = '';
        document.getElementById('uploadImagesBtn').style.display = 'none';
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
        if (!modalUrls.length) {
            el.innerHTML = '<div class="text-muted small py-2">No images yet. Upload or fetch from a marketplace above.</div>';
            return;
        }

        el.innerHTML = '<div class="im-grid" id="imGrid">' +
            modalUrls.map((url, idx) => {
                const meta     = storedImageMeta.get(url);
                const isStored = !!meta;
                const name     = meta?.name ?? decodeURIComponent(url.split('/').pop().split('?')[0]);
                const dbId     = meta?.id ?? '';
                const isDefaultMain = idx === 0;
                return `<div class="im-card${isStored?' is-stored':''}${isDefaultMain?' is-main-default':''}"
                            draggable="true" data-idx="${idx}"
                            data-url="${esc(url)}" data-dbid="${esc(String(dbId))}">
                    <div class="im-card-img-wrap">
                        <img src="${esc(imgSrc(url))}" alt=""
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22120%22 height=%22100%22%3E%3Crect width=%22120%22 height=%22100%22 fill=%22%23f1f5f9%22/%3E%3Ctext x=%2260%22 y=%2254%22 font-size=%2211%22 fill=%22%2394a3b8%22 text-anchor=%22middle%22%3ENo preview%3C/text%3E%3C/svg%3E'">
                        <span class="im-card-badge${isDefaultMain?' im-card-badge-main':''}">${isDefaultMain ? 'MAIN' : (idx + 1)}</span>
                        <button type="button" class="im-card-del" data-i="${idx}" title="Delete image"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="im-card-footer">
                        <div class="im-card-name">Image ${idx + 1}</div>
                        <div class="im-card-filename" title="${esc(url)}">${esc(name)}</div>
                        <div class="im-card-arrows">
                            <button type="button" class="im-up" data-i="${idx}" title="Move left">&#8592;</button>
                            <button type="button" class="im-set-main" data-i="${idx}" title="Set as main (#1) for all marketplaces">&#9733;</button>
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
                clampMainIndicesAfterDelete(i);
                const removed = modalUrls.splice(i, 1)[0];
                storedImageMeta.delete(removed);
                renderSlots();
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

        renderMainByMpSelectors();
    }

    document.getElementById('resetMainAllBtn')?.addEventListener('click', () => {
        modalMainByMp = defaultMainByMarketplace();
        renderSlots();
    });

    // ── ADD IMAGES: two-step flow (preview → explicit upload) ───────────────────

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

    async function uploadPendingFiles() {
        const sku = document.getElementById('modalSku').value;
        if (!pendingFiles.length) return true;
        if (modalUrls.length >= 12) {
            toast('Maximum 12 images already added', false);
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

            const r = await fetch('/image-master/save-pm', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ sku, images: modalUrls, main_by_marketplace: mainByMarketplacePayload() }),
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
        const msTimeout = Math.max(600000, checks.length * 120000);
        try {
            setPushProgress(true, `Pushing ${selLabel} image(s) (${modeLabel} existing) to ${checks.length} marketplace(s)… One request, please wait.`, '');
            const controller = new AbortController();
            const t = setTimeout(() => controller.abort(), msTimeout);
            try {
                const r = await fetch('/image-master/push', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        sku,
                        mode,
                        updates,
                        main_by_marketplace: mainByMarketplacePayload(),
                    }),
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
            const hasAnyError = failCount > 0;
            setPushProgress(true, `Push finished: ${okCount} success, ${failCount} failed${metricsFailCount ? `, ${metricsFailCount} metrics save failed` : ''}`, progress.join('<br>'), hasAnyError);
            if (!hasAnyError) {
                toast(`Pushed to ${okCount} marketplace(s).${metricsFailCount ? ` ${metricsFailCount} metrics save failed.` : ''}`, !metricsFailCount);
                loadData();
                if (editModal) editModal.hide();
                setTimeout(() => setPushProgress(false, '', ''), 5000);
            } else {
                toast(`Push completed with failures (${failCount}). See error details below.`, false);
                loadData();
                // Keep failures visible until user clicks elsewhere — don't auto-hide
            }
        } finally {
            // spinner already stopped in setPushProgress(hasError=true); do nothing here
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
        const mainIdx = mainImageIndexFor(mp, row);
        const mainLabel = mainImageLabelFor(mp, mainIdx);
        if (!window.confirm(`Push ${urls.length} image(s) for ${sku} to ${LABELS[mp]}?\n\nMain image for this platform: ${mainLabel} (sent first).\nThis will replace existing marketplace images.`)) return;
        setPushProgress(true, `Pushing images to 1 marketplace... This may take 1-2 minutes`, `1/1 ${LABELS[mp]}: in progress (${mainLabel} first)`);
        fetch('/image-master/push', {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken,'Accept':'application/json'},
            body: JSON.stringify({
                sku,
                mode: 'replace',
                updates: [{ marketplace: mp, images: urls }],
                main_by_marketplace: mainByMarketplacePayloadFromRow(row),
            }),
        }).then(r=>r.json()).then(j => {
            const rowRes = (j.results && j.results[mp]) ? j.results[mp] : null;
            const ok = !!(rowRes && rowRes.success);
            const msg = rowRes?.message || j.message || (ok ? 'Updated' : 'Failed');
            setPushProgress(true, `Push finished: ${ok ? '1 success' : '1 failed'}`, `1/1 ${LABELS[mp]}: ${ok ? '✓ OK' : '✗ Failed'} — ${esc(msg)}`, !ok);
            if (ok) { toast(LABELS[mp]+' pushed'); loadData(); setTimeout(() => setPushProgress(false,'',''), 5000); }
            else toast(msg, false);
        }).catch(e => {
            setPushProgress(true, 'Push finished: 1 failed', `1/1 ${LABELS[mp]}: ✗ Failed — ${esc(e.message || 'Request failed')}`, true);
            toast(e.message, false);
        });
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

    let bulkPushAllRunning = false;

    function failedMarketplaceSummary(results) {
        if (!results || typeof results !== 'object') return '';
        const failed = MARKETPLACES.filter(mp => results[mp] && !results[mp].success);
        if (!failed.length) return '';
        return failed.map(mp => LABELS[mp] || mp).join(', ');
    }

    async function bulkPushAll() {
        if (bulkPushAllRunning) {
            toast('Bulk push is already running.', false);
            return;
        }
        const rows = currentFilteredRowsForPull().filter(r => pmImageUrls(r).length > 0);
        if (!rows.length) {
            toast('No products with images in the current view.', false);
            return;
        }
        const mpCount = ENABLED_MARKETPLACES.length;
        const skuLabel = rows.length === 1 ? '1 product' : `${rows.length} products`;
        if (!window.confirm(
            `Push images to ALL ${mpCount} marketplaces for ${skuLabel}?\n\n`
            + 'Per-platform main image settings (Image 1 by default) will be applied.\n'
            + 'Each product is sent in one batch; this may take a long time.'
        )) return;
        if (!confirmEbay3Push(ENABLED_MARKETPLACES)) return;

        bulkPushAllRunning = true;
        const pushAllBtn = document.getElementById('pushAllBtn');
        const pushSelectedBtn = document.getElementById('pushSelectedBtn');
        if (pushAllBtn) pushAllBtn.disabled = true;
        if (pushSelectedBtn) pushSelectedBtn.disabled = true;

        let okSkus = 0;
        let failSkus = 0;
        const progress = [];
        const msTimeout = Math.max(600000, mpCount * 120000);

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const sku = String(row.SKU || '');
            const urls = pmImageUrls(row);
            if (!urls.length) continue;

            const updates = ENABLED_MARKETPLACES.map(mp => ({ marketplace: mp, images: urls }));
            const main_by_marketplace = mainByMarketplacePayloadFromRow(row);
            setPushProgress(
                true,
                `Bulk push ${i + 1}/${rows.length}: ${sku}… (${mpCount} marketplaces)`,
                progress.slice(-40).join('<br>')
            );

            const controller = new AbortController();
            const t = setTimeout(() => controller.abort(), msTimeout);
            try {
                const r = await fetch('/image-master/push', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ sku, mode: 'replace', updates, main_by_marketplace }),
                    signal: controller.signal,
                });
                const rawText = await r.text();
                let j = null;
                try { j = JSON.parse(rawText); } catch (_) {}
                const mpFailed = Number(j?.total_failed ?? 0);
                const mpOk = Number(j?.total_success ?? 0);
                const metricsFailed = Number(j?.total_metrics_failed ?? 0);
                const batchOk = r.ok && j && mpFailed === 0 && (mpOk > 0 || j.success);
                if (batchOk) {
                    okSkus++;
                    const metricsNote = metricsFailed > 0 ? ` (${metricsFailed} metrics save failed)` : '';
                    progress.push(`✓ ${esc(sku)} — ${mpOk}/${mpCount} marketplace(s)${metricsNote}`);
                } else {
                    failSkus++;
                    const failedMps = failedMarketplaceSummary(j?.results);
                    const errMsg = j?.message || (mpFailed ? `${mpFailed} marketplace(s) failed` : `HTTP ${r.status}`);
                    progress.push(`✗ ${esc(sku)} — ${esc(errMsg)}${failedMps ? ` (${esc(failedMps)})` : ''}`);
                }
            } catch (e) {
                failSkus++;
                const txt = (e?.name === 'AbortError')
                    ? `Timed out after ${Math.round(msTimeout / 1000)}s`
                    : (e.message || 'Request failed');
                progress.push(`✗ ${esc(sku)} — ${esc(txt)}`);
            } finally {
                clearTimeout(t);
            }

            if (i < rows.length - 1) await new Promise(resolve => setTimeout(resolve, 2000));
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
        if (!hasError) setTimeout(() => setPushProgress(false, '', ''), 8000);
    }

    document.getElementById('pushAllBtn')?.addEventListener('click', () => bulkPushAll());

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

    async function pollShopifyPullStatus() {
        try {
            const job = await fetchShopifyPullStatus();
            const wasActive = shopifyPullPollTimer !== null;
            renderShopifyPullJob(job);
            if (isShopifyPullActive(job.status || 'idle')) {
                startShopifyPullPolling();
            } else {
                stopShopifyPullPolling();
                if (wasActive && ['completed', 'stopped'].includes(job.status || '')) {
                    loadData();
                }
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
            scope.textContent = shopifyPullSelectedSkus
                ? `Scope: selected SKU ${shopifyPullSelectedSkus.join(', ')}.`
                : 'Scope: currently filtered SKUs.';
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

        const scopeText = options.scopeText || `${skus.length} SKU(s)`;
        if (!await confirmShopifyPull(scopeText)) {
            return false;
        }

        try {
            const res = await fetch('/image-master/shopify-pull/start', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ skus })
            });
            const payload = await res.json().catch(() => ({}));
            if (!res.ok || !payload.success) throw new Error(payload.message || 'Unable to start Shopify pull');
            renderShopifyPullJob(payload.job);
            startShopifyPullPolling();
            toast(options.successMessage || payload.message || 'Background Shopify image pull started.');
            return true;
        } catch (e) {
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
        const rows = shopifyPullSelectedSkus
            ? shopifyPullSelectedSkus.map(sku => ({ SKU: sku }))
            : currentFilteredRowsForPull();
        const skus = rows.map(row => String(row.SKU || '').trim()).filter(Boolean);
        if (!skus.length) {
            toast('No SKUs loaded to pull from Shopify.', false);
            return;
        }
        const scopeText = shopifyPullSelectedSkus
            ? `SKU: ${skus.join(', ')}`
            : `${skus.length} currently filtered SKU(s)`;
        await startShopifyPullJobForSkus(skus, { scopeText });
    }

    async function controlShopifyPull(action) {
        try {
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

    document.getElementById('pullShopifyBtn')?.addEventListener('click', () => openShopifyPullModal());
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
});
</script>
@endsection

