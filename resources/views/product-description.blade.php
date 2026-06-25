@extends('layouts.vertical', ['title' => 'Description Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .card.dm-master-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .card.dm-master-card .card-body { padding: 1.25rem 1.5rem; }
        .dm-master-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; }
        .dm-master-toolbar .btn { padding:.3rem .6rem; font-size:.8rem; }
        .table-responsive.dm-table-wrap { position:relative; border:1px solid #e2e8f0; border-radius:10px; max-height:640px; overflow:auto; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,.04); }
        #desc-master-table thead th { position:sticky; top:0; vertical-align:middle!important; background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%)!important; color:#fff; z-index:10; padding:6px 8px; font-size:10px; font-weight:600; text-transform:uppercase; white-space:nowrap; }
        #desc-master-table thead input.th-sub { background:rgba(255,255,255,.95); border:none; border-radius:4px; color:#333; padding:4px 6px; width:100%; font-size:10px; margin-top:4px; }
        #desc-master-table tbody td { padding:8px 10px; vertical-align:middle!important; border-bottom:1px solid #edf2f9; font-size:11px; line-height:1.35; color:#475569; }
        #desc-master-table tbody tr:nth-child(even){ background:#f8fafc; }
        #desc-master-table tbody tr:hover { background:#e8f0fe; }
        .preview-cell { max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; cursor:help; }
        .marketplaces-cell { vertical-align:middle!important; }
        .bp-mp-inline { display:flex; flex-wrap:wrap; align-items:flex-end; gap:6px; justify-content:flex-start; min-width:100px; }
        .bp-mp-th-title { font-weight:600; letter-spacing:0.2px; }
        .bp-mp-th-icons { display:flex; flex-wrap:wrap; gap:4px; margin-top:6px; justify-content:center; align-items:center; }
        .bp-mp-th-pill { width:22px; height:22px; border-radius:4px; font-size:8px; font-weight:700; color:#fff; display:inline-flex; align-items:center; justify-content:center; line-height:1; }
        .dm-mp-item { display:flex; flex-direction:column; align-items:center; gap:2px; min-width:34px; }
        .dm-mp-item .dm-sel-mp { width:14px; height:14px; margin:0; cursor:pointer; }
        .bp-mp-stack { display:flex; flex-direction:column; align-items:center; gap:3px; border:none; background:transparent; padding:0; cursor:pointer; }
        .bp-mp-stack:hover .marketplace-btn { transform:translateY(-1px); box-shadow:0 2px 6px rgba(0,0,0,.18); }
        .bp-mp-dot { width:10px; height:10px; border-radius:50%; border:2px solid #94a3b8; background:transparent; transition:background .15s,border-color .15s; flex-shrink:0; }
        .bp-mp-dot.pushed { background:#22c55e; border-color:#22c55e; }
        .marketplace-btn { width:28px; height:28px; border:none; border-radius:4px; color:#fff; font-weight:600; font-size:11px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all .2s; padding:0; pointer-events:none; }
        .bp-mp-stack { pointer-events:auto; }
        .btn-ebay1 { background-color:#0d6efd; }
        .btn-ebay2 { background-color:#198754; }
        .btn-ebay3 { background-color:#fd7e14; }
        .btn-macy { background-color:#0d6efd; }
        .btn-amazon { background-color:#ff9900; }
        .btn-temu { background-color:#ff6b00; }
        .btn-reverb { background-color:#333333; }
        .btn-wayfair { background-color:#7a3ff2; }
        .btn-bestbuy { background-color:#0046be; }
        .btn-shopify { background-color:#7cb342; }
        .btn-shopify-pls { background-color:#5c6bc0; }
        .btn-push-all { background:#ff9900!important; color:#232f3e!important; font-weight:600; }
        .action-buttons-cell { white-space:nowrap; vertical-align:middle!important; }
        .action-buttons-group { display:flex; align-items:center; gap:6px; flex-wrap:nowrap; }
        .action-btn { padding:5px 10px; border:none; border-radius:6px; font-size:11px; font-weight:500; display:inline-flex; align-items:center; gap:4px; cursor:pointer; }
        .view-btn { background:#17a2b8; color:#fff; }
        .pull-btn { background:#f59e0b; color:#fff; padding:5px 8px; }
        .pm-tier-btn { background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color:#fff; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        .shopify-hint { font-size:10px; color:#64748b; }
        #loadErrorBanner { display:none; }
        #viewDescModal .dm-view-body { font-size:12px; line-height:1.45; color:#334155; white-space:pre-wrap; word-break:break-word; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:.75rem; min-height:2rem; max-height:min(50vh,360px); overflow-y:auto; }
        #viewDescModal .dm-view-section-title { font-size:11px; font-weight:700; color:#1e40af; padding:.35rem .5rem; background:#eff6ff; border-radius:6px; border-left:4px solid #2563eb; margin-bottom:.5rem; margin-top:.75rem; }
        #dmSkeleton { min-height:0; }
        .dm-skel-table { width:100%; border-collapse:collapse; }
        .dm-skel-table td { padding:10px 8px; }
        .dm-skel-bar { height:12px; border-radius:4px; background:linear-gradient(90deg,#e2e8f0 0%,#f1f5f9 50%,#e2e8f0 100%); background-size:200% 100%; animation:dmShimmer 1.2s ease-in-out infinite; }
        .dm-skel-bar.w-40 { width:40%; } .dm-skel-bar.w-60 { width:60%; } .dm-skel-bar.w-80 { width:80%; }
        @keyframes dmShimmer { 0%{background-position:200% 0}100%{background-position:-200% 0} }
        #dmTableShell.is-loading .dm-table-wrap { opacity:.35; pointer-events:none; }
        .dm-modal-section { border:1px solid #e2e8f0; border-radius:10px; padding:.75rem; background:#f8fafc; margin-bottom:.75rem; }
        .dm-modal-section-title { font-size:12px; font-weight:700; color:#1e3a8a; margin-bottom:.5rem; }
        .dm-aplus-preview { border:1px dashed #cbd5e1; border-radius:8px; background:#fff; padding:.65rem; max-height:280px; overflow:auto; }
        .dm-aplus-preview img { max-width:100%; height:auto; border-radius:6px; margin-bottom:.5rem; }
        .dm-mp-group-title { font-size:11px; font-weight:600; color:#334155; }
        .btn.is-loading { opacity:.75; pointer-events:none; }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Description Master',
        'sub_title' => 'Product descriptions by marketplace',
    ])

        <div class="row">
            <div class="col-12">
            <div class="card dm-master-card">
                <div class="card-body">
                    <div id="loadErrorBanner" class="alert alert-danger mb-3 py-2" style="display:none;" role="alert">
                        <span id="loadErrorText"></span>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="retryLoadBtn">Retry</button>
                    </div>
                    <div class="mb-3 dm-master-toolbar">
                        <button type="button" id="exportBtn" class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Export</button>
                        <button type="button" id="importBtn" class="btn btn-info btn-sm"><i class="fas fa-upload"></i> Import</button>
                        <button type="button" id="pushSelectedBtn" class="btn btn-secondary btn-sm"><i class="fas fa-cloud-upload-alt"></i> Push Selected</button>
                        <button type="button" id="pushAllBtn" class="btn btn-push-all btn-sm"><i class="fas fa-cloud-upload-alt"></i> Push ALL (this page)</button>
                        <span class="text-muted small" id="rowCountBadge">0 products</span>
                        <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
        </div>

                    <div id="dmSimplePanel">
                    <div id="dmTableShell">
                        <div id="dmSkeleton" class="mb-2" style="display:none;">
                            <table class="dm-skel-table"><tbody id="dmSkeletonBody"></tbody></table>
                        </div>
                        <div class="table-responsive dm-table-wrap">
                            <table id="desc-master-table" class="table w-100">
                                <thead>
                                    <tr>
                                        <th>
                                            <div class="d-flex align-items-center gap-2"><span>SKU</span><span id="skuCountDm">(0)</span></div>
                                            <input type="text" id="skuSearchDm" class="th-sub" placeholder="Filter SKU" autocomplete="off">
                                        </th>
                                        <th>Product Name</th>
                                        <th>
                                            <div>Preview (PM)</div>
                                            <input type="text" id="previewSearchDm" class="th-sub" placeholder="Filter preview" autocomplete="off">
                                        </th>
                                        <th>Action</th>
                                        <th title="Amazon, Temu, Reverb — max 1500 characters each">
                                            <div class="bp-mp-th-title">DESC 1500</div>
                                            <div class="bp-mp-th-icons">
                                                <span class="bp-mp-th-pill btn-amazon">A</span><span class="bp-mp-th-pill btn-temu">T</span><span class="bp-mp-th-pill btn-reverb">R</span>
                                            </div>
                                        </th>
                                        <th title="Shopify Main, Shopify PLS — max 1000 characters each">
                                            <div class="bp-mp-th-title">DESC 1000</div>
                                            <div class="bp-mp-th-icons"><span class="bp-mp-th-pill btn-shopify">SM</span><span class="bp-mp-th-pill btn-shopify-pls">SP</span></div>
                                        </th>
                                        <th title="eBay1, eBay2, eBay3 — max 800 characters each">
                                            <div class="bp-mp-th-title">DESC 800</div>
                                            <div class="bp-mp-th-icons"><span class="bp-mp-th-pill btn-ebay1">E1</span><span class="bp-mp-th-pill btn-ebay2">E2</span><span class="bp-mp-th-pill btn-ebay3">E3</span></div>
                                        </th>
                                        <th title="Macy's — max 600 characters">
                                            <div class="bp-mp-th-title">DESC 600</div>
                                            <div class="bp-mp-th-icons"><span class="bp-mp-th-pill btn-macy">M</span></div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="table-body"></tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editDescModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit descriptions</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalSku">
                    <input type="hidden" id="modalAplusImagesJson" value="[]">
                    <div class="mb-2"><strong>SKU:</strong> <span id="modalSkuLabel"></span></div>
                    <div class="mb-3"><strong>Product:</strong> <span id="modalProductLabel"></span></div>
                    <p class="small text-muted mb-2"><i class="fas fa-info-circle"></i> Each marketplace has its own description. Pick a tab, edit in the editor, then Save. <strong>Fetch live</strong> pulls that marketplace's current listing description (tables &amp; images preserved).</p>

                    <div id="modalMpTabs" class="d-flex flex-wrap gap-1 mb-2"></div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <div class="fw-semibold small" id="modalActiveMpLabel">—</div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-success btn-sm" id="modalFetchLiveBtn"><i class="fas fa-cloud-download-alt"></i> Fetch live</button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="modalAiGenBtn"><i class="fas fa-wand-magic-sparkles"></i> AI Generate</button>
                        </div>
                    </div>
                    <textarea id="modalDescHtml"></textarea>
                    <div class="small text-muted mt-1" id="modalEditorHint">Editing the selected marketplace's description.</div>
                    <p class="shopify-hint mb-0 mt-1"><i class="fas fa-info-circle"></i> Some marketplaces can't be fetched live yet (no read API) — those tabs start from the last saved copy.</p>
                    <div class="mt-2 small text-muted d-none" id="modalAiLoadingWrap"><i class="fas fa-spinner fa-spin"></i> <span id="modalAiLoadingTier"></span></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="modalSavePmBtn"><i class="fas fa-save"></i> Save all</button>
                </div>
            </div>
        </div>
    </div>

                    <div class="modal fade" id="viewDescModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="viewDescTitle"><i class="fas fa-eye me-2"></i>Descriptions</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewDescSubtitle" class="small text-muted mb-2"></div>
                    <div id="viewDescContent"></div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="viewDescCopyBtn"><i class="fas fa-copy me-1"></i> Copy to clipboard</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
document.addEventListener('DOMContentLoaded', () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const LIMITS = {
        amazon: 1500, temu: 1500, reverb: 1500, wayfair: 1500, bestbuy: 1500,
        shopify_main: 1000, shopify_pls: 1000,
        ebay: 800, ebay2: 800, ebay3: 800,
        macy: 600,
    };
    const LABELS = {
        amazon: 'Amazon', temu: 'Temu', reverb: 'Reverb', wayfair: 'Wayfair', bestbuy: 'Best Buy',
        shopify_main: 'Shopify Main', shopify_pls: 'Shopify PLS',
        ebay: 'eBay1', ebay2: 'eBay2', ebay3: 'eBay3',
        macy: "Macy's",
    };
    const GROUPS = {
        g1500: ['amazon', 'temu', 'reverb', 'wayfair', 'bestbuy'],
        g1000: ['shopify_main', 'shopify_pls'],
        g800: ['ebay', 'ebay2', 'ebay3'],
        g600: ['macy'],
    };
    const MP_TILE = {
        amazon: 'btn-amazon', temu: 'btn-temu', reverb: 'btn-reverb',
        shopify_main: 'btn-shopify', shopify_pls: 'btn-shopify-pls',
        ebay: 'btn-ebay1', ebay2: 'btn-ebay2', ebay3: 'btn-ebay3',
        macy: 'btn-macy', wayfair: 'btn-wayfair', bestbuy: 'btn-bestbuy',
    };
    const MP_SHORT = {
        amazon: 'A', temu: 'T', reverb: 'R', wayfair: 'W', bestbuy: 'B',
        shopify_main: 'SM', shopify_pls: 'SP',
        ebay: 'E1', ebay2: 'E2', ebay3: 'E3',
        macy: 'M',
    };
    const ALL_MP = Object.keys(LIMITS);
    const TIER_MIN_AI = { 1500: 1400, 1000: 900, 800: 700, 600: 500 };
    const EBAY3_WARNING = 'eBay3 has different listing structure. Please verify bullet points format before pushing.';

        let tableData = [];
    const bySku = new Map();
    let editModal, viewDescModal;
    let tableBodyBound = false;
    let lastViewPlainText = '';
    let currentPage = 1;
    let listMeta = { total: 0, last_page: 1, per_page: 75 };
    let searchDebounce = null;
    let descriptionMasterLoadSeq = 0;

    const esc = (s) => {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    };

    function toast(msg, ok = true) {
        if (window.bootstrap && window.bootstrap.Toast) {
            const el = document.createElement('div');
            el.className = 'toast align-items-center text-bg-' + (ok ? 'success' : 'danger') + ' border-0 position-fixed top-0 end-0 m-3';
            el.style.zIndex = '1090';
            el.setAttribute('role', 'alert');
            el.innerHTML = '<div class="d-flex"><div class="toast-body">' + esc(msg) + '</div></div>';
            document.body.appendChild(el);
            const t = new bootstrap.Toast(el, { delay: 3200 });
            t.show();
            el.addEventListener('hidden.bs.toast', () => el.remove());
        } else {
            alert(msg);
        }
    }

    function confirmEbay3Push(marketplaces) {
        const mps = Array.isArray(marketplaces) ? marketplaces : [];
        if (!mps.includes('ebay3')) return true;
        return window.confirm(EBAY3_WARNING);
    }

    function hideLoadError() {
        const b = document.getElementById('loadErrorBanner');
        if (b) b.style.display = 'none';
    }

    function showLoadError(msg) {
        const b = document.getElementById('loadErrorBanner');
        const t = document.getElementById('loadErrorText');
        if (t) t.textContent = msg || 'Failed to load data.';
        if (b) b.style.display = 'block';
    }

    function tierForMp(mp) {
        if (GROUPS.g1500.includes(mp)) return '1500';
        if (GROUPS.g1000.includes(mp)) return '1000';
        if (GROUPS.g800.includes(mp)) return '800';
        return '600';
    }

    function getPmTextForMp(row, mp) {
        const t = tierForMp(mp);
        const d1500 = String(row.description_1500 || row.product_description || '').trim();
        const d1000 = String(row.description_1000 || '').trim();
        const d800 = String(row.description_800 || '').trim();
        const d600 = String(row.description_600 || '').trim();
        if (t === '1500') return d1500;
        if (t === '1000') {
            if (d1000) return d1000;
            return d1500.length > 1000 ? d1500.slice(0, 1000) : d1500;
        }
        if (t === '800') {
            if (d800) return d800;
            return d1500.length > 800 ? d1500.slice(0, 800) : d1500;
        }
        if (d600) return d600;
        return d1500.length > 600 ? d1500.slice(0, 600) : d1500;
    }

    function preview100(row) {
        const base = String(row.description_1500 || row.product_description || '').trim();
        if (!base) return '—';
        return base.length > 100 ? base.slice(0, 100) + '…' : base;
    }

    function getDisplayDescForMp(row, mp) {
        const d = row.descriptions || {};
        const saved = (d[mp] || '').trim();
        if (saved) return saved;
        return getPmTextForMp(row, mp);
    }

    function mpStackHtml(sku, mp, savedText) {
        const pushed = (savedText || '').trim() !== '';
        const cls = MP_TILE[mp] || 'btn-secondary';
        const short = MP_SHORT[mp] || mp;
        const tip = LABELS[mp] + (pushed ? ' — pushed' : ' — not pushed') + '. Click to push.';
        return `
            <div class="dm-mp-item">
                <input type="checkbox" class="form-check-input dm-sel-mp" data-sku="${esc(sku)}" data-mp="${esc(mp)}" title="Include in Push Selected">
                <button type="button" class="bp-mp-stack" data-push-mp="${esc(mp)}" data-sku="${esc(sku)}" title="${esc(tip)}">
                    <span class="bp-mp-dot ${pushed ? 'pushed' : ''}" aria-hidden="true"></span>
                    <span class="marketplace-btn ${cls}">${esc(short)}</span>
                </button>
            </div>`;
    }

    function groupCell(groupKey, sku, row) {
        const keys = GROUPS[groupKey] || [];
        const desc = row.descriptions || {};
        return `
            <td class="marketplaces-cell">
                <div class="bp-mp-inline">
                    ${keys.map((mp) => mpStackHtml(sku, mp, desc[mp] || '')).join('')}
                </div>
            </td>`;
    }

    function buildRowHtml(r) {
        const sku = String(r.SKU || '');
        const pm = String(r.description_1500 || r.product_description || '').trim();
        const prev100 = preview100(r);
        return `<tr data-sku="${esc(sku)}">
                <td>${esc(sku)}</td>
                <td>${esc(r.Parent || r.title150 || sku)}</td>
                <td class="preview-cell" title="${esc(pm)}">${esc(prev100)}</td>
                <td class="action-buttons-cell">
                    <div class="action-buttons-group">
                        <button type="button" class="action-btn view-btn" data-view-row="${esc(sku)}" title="View descriptions (read-only)"><i class="fas fa-eye"></i></button>
                        <button type="button" class="action-btn pull-btn" data-pull-shopify="${esc(sku)}" title="Fetch live descriptions from all marketplaces (saves each)"><i class="fas fa-cloud-download-alt"></i></button>
                        <button type="button" class="action-btn pm-tier-btn" data-edit-pm="${esc(sku)}" title="Edit descriptions &amp; push"><i class="fas fa-edit"></i></button>
                    </div>
                </td>
                ${groupCell('g1500', sku, r)}
                ${groupCell('g1000', sku, r)}
                ${groupCell('g800', sku, r)}
                ${groupCell('g600', sku, r)}
            </tr>`;
    }

    function bindTableBodyOnce() {
        if (tableBodyBound) return;
        tableBodyBound = true;
            const tbody = document.getElementById('table-body');
        if (!tbody) return;

        tbody.addEventListener('click', (e) => {
            const viewRow = e.target.closest('[data-view-row]');
            if (viewRow) {
                e.preventDefault();
                openViewModal(viewRow.getAttribute('data-view-row'));
                return;
            }
            const pullBtn = e.target.closest('[data-pull-shopify]');
            if (pullBtn) {
                e.preventDefault();
                runPullAll(pullBtn.getAttribute('data-pull-shopify'), pullBtn);
                return;
            }
            const editPmBtn = e.target.closest('[data-edit-pm]');
            if (editPmBtn) {
                openEditModal(editPmBtn.getAttribute('data-edit-pm'));
                return;
            }
            const pushBtn = e.target.closest('.bp-mp-stack[data-push-mp]');
            if (pushBtn) {
                e.preventDefault();
                pushSingleMarketplace(pushBtn.getAttribute('data-sku'), pushBtn.getAttribute('data-push-mp'));
            }
        });
    }

    function openViewModal(sku) {
        const row = bySku.get(String(sku));
        if (!row) return;
        const d1500 = String(row.description_1500 || row.product_description || '').trim();
        const d1000 = String(row.description_1000 || '').trim();
        const d800 = String(row.description_800 || '').trim();
        const d600 = String(row.description_600 || '').trim();
        let html = '<div class="mb-2 pb-2 border-bottom"><strong>Product:</strong> ' + esc(row.Parent || sku) + '</div>';
        html += '<div class="dm-view-section-title">DESC 1500 (Product Master)</div><div class="dm-view-body">' + (d1500 ? esc(d1500) : '<em class="text-muted">Empty</em>') + '</div>';
        html += '<div class="dm-view-section-title">DESC 1000 (Product Master)</div><div class="dm-view-body">' + (d1000 ? esc(d1000) : '<em class="text-muted">Empty</em>') + '</div>';
        html += '<div class="dm-view-section-title">DESC 800 (Product Master)</div><div class="dm-view-body">' + (d800 ? esc(d800) : '<em class="text-muted">Empty</em>') + '</div>';
        html += '<div class="dm-view-section-title">DESC 600 (Product Master)</div><div class="dm-view-body">' + (d600 ? esc(d600) : '<em class="text-muted">Empty</em>') + '</div>';
        html += '<div class="dm-view-section-title">Marketplace copies (metrics)</div>';
        const lines = ['DESC 1500: ' + (d1500 || '(empty)'), 'DESC 1000: ' + (d1000 || '(empty)'), 'DESC 800: ' + (d800 || '(empty)'), 'DESC 600: ' + (d600 || '(empty)')];
        ALL_MP.forEach((mp) => {
            const label = LABELS[mp];
            const t = getDisplayDescForMp(row, mp);
            html += '<div class="mb-2"><strong>' + esc(label) + '</strong> <span class="text-muted">(' + (LIMITS[mp] || '') + ' max)</span><div class="dm-view-body mt-1">' + (t ? esc(t) : '<em class="text-muted">Empty</em>') + '</div></div>';
            lines.push(label + ': ' + (t || '(empty)'));
        });
        lastViewPlainText = 'SKU: ' + sku + '\n\n' + lines.join('\n\n');
        document.getElementById('viewDescTitle').innerHTML = '<i class="fas fa-eye me-2"></i>Descriptions';
        document.getElementById('viewDescSubtitle').textContent = 'SKU: ' + sku + ' — Read only';
        document.getElementById('viewDescContent').innerHTML = html;
        if (!viewDescModal) viewDescModal = new bootstrap.Modal(document.getElementById('viewDescModal'));
        viewDescModal.show();
    }

    document.getElementById('viewDescCopyBtn')?.addEventListener('click', () => {
        const t = lastViewPlainText || '';
        if (!t.trim()) { toast('Nothing to copy', false); return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(t).then(() => toast('Copied')).catch(() => toast('Copy failed', false));
        } else {
            toast('Clipboard not available', false);
        }
    });

    function pushSingleMarketplace(sku, mp) {
        const row = bySku.get(String(sku));
        if (!row) return;
        const text = getDisplayDescForMp(row, mp);
        if (!text || !String(text).trim()) {
            toast('No text for this tier. Add it via the Edit descriptions button, then push.', false);
            openEditModal(sku);
            return;
        }
        const lim = LIMITS[mp] || 1500;
        const t = text.length > lim ? text.slice(0, lim) : text;
        pushPayload(sku, [{ marketplace: mp, description: t }], null, false);
    }

    function buildSkeletonRows(n) {
        let h = '';
        for (let r = 0; r < n; r++) {
            h += '<tr><td><div class="dm-skel-bar w-60"></div></td><td><div class="dm-skel-bar w-80"></div></td><td><div class="dm-skel-bar w-80"></div></td>';
            h += '<td><div class="dm-skel-bar w-40"></div></td><td><div class="dm-skel-bar w-80"></div></td><td><div class="dm-skel-bar w-60"></div></td><td><div class="dm-skel-bar w-60"></div></td><td><div class="dm-skel-bar w-40"></div></td></tr>';
        }
        return h;
    }

    function setLoadingUi(on) {
        const shell = document.getElementById('dmTableShell');
        const sk = document.getElementById('dmSkeleton');
        const body = document.getElementById('dmSkeletonBody');
        if (shell) shell.classList.toggle('is-loading', on);
        if (on && sk && body) {
            body.innerHTML = buildSkeletonRows(4);
            sk.style.display = 'block';
        } else if (sk) {
            sk.style.display = 'none';
        }
    }

    function loadData() {
        hideLoadError();
        const mySeq = ++descriptionMasterLoadSeq;

        setLoadingUi(true);
        const ctrl = new AbortController();
        const abortTimer = setTimeout(() => ctrl.abort(), 180000);

        fetch('/product-description-data', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: ctrl.signal,
        })
            .then(async (r) => {
                clearTimeout(abortTimer);
                const ct = r.headers.get('content-type') || '';
                if (!r.ok) {
                    let detail = '';
                    try {
                        detail = ct.includes('application/json') ? JSON.stringify(await r.json()) : (await r.text()).slice(0, 400);
                    } catch (e) { detail = r.statusText; }
                    throw new Error('HTTP ' + r.status + ': ' + detail);
                }
                if (!ct.includes('application/json')) {
                    throw new Error('Expected JSON: ' + (await r.text()).slice(0, 200));
                }
                return r.json();
            })
            .then((res) => {
                if (res.status === 500 && res.error) throw new Error(res.message || res.error);
                if (mySeq !== descriptionMasterLoadSeq) return;
                const raw = Array.isArray(res.data) ? res.data : [];
                tableData = raw.filter((i) => i && i.SKU && !String(i.SKU).toUpperCase().includes('PARENT'));
                bySku.clear();
                tableData.forEach((r) => bySku.set(String(r.SKU), r));
                listMeta = res.meta || { total: tableData.length };
                setLoadingUi(false);
                renderTable();
            })
            .catch((e) => {
                clearTimeout(abortTimer);
                setLoadingUi(false);
                if (e.name === 'AbortError' && mySeq !== descriptionMasterLoadSeq) {
                    return;
                }
                const msg = e.name === 'AbortError' ? 'Request timed out.' : (e.message || 'Error');
                console.error('Description Master: load failed', e);
                showLoadError(msg);
                toast('Failed to load: ' + msg, false);
                const tbody = document.getElementById('table-body');
                if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">Load failed. Retry or check console (F12).</td></tr>';
            });
    }

    // Client-side filter over the fully-loaded dataset (no server round-trip, no skeleton on search).
    function getFilteredData() {
        const qSku = (document.getElementById('skuSearchDm')?.value || '').trim().toLowerCase();
        const qText = (document.getElementById('previewSearchDm')?.value || '').trim().toLowerCase();
        let rows = tableData;
        if (qSku) rows = rows.filter((r) => String(r.SKU || '').toLowerCase().includes(qSku));
        if (qText) {
            rows = rows.filter((r) => {
                const hay = [r.Parent, r.title150, r.description_1500, r.product_description, r.description_1000, r.description_800, r.description_600]
                    .map((x) => String(x || '').toLowerCase()).join(' ');
                return hay.includes(qText);
            });
        }
        return rows;
    }

    function renderTable() {
        const tbody = document.getElementById('table-body');
        bindTableBodyOnce();
        const rows = getFilteredData();
        const total = listMeta.total != null ? listMeta.total : tableData.length;
        const badge = document.getElementById('rowCountBadge');
        if (badge) badge.textContent = total + ' products';
        const cnt = document.getElementById('skuCountDm');
        if (cnt) cnt.textContent = '(' + rows.length + ' shown)';
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No matching products</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(buildRowHtml).join('');
    }

    function scheduleSearch() {
        if (searchDebounce) clearTimeout(searchDebounce);
        searchDebounce = setTimeout(renderTable, 120);
    }

    function openEditModal(sku) {
        const row = bySku.get(String(sku));
        if (!row) return;
        document.getElementById('modalSku').value = sku;
        document.getElementById('modalSkuLabel').textContent = sku;
        document.getElementById('modalProductLabel').textContent = row.Parent || row.title150 || sku;
        mpContent = {};
        ALL_MP.forEach((mp) => { mpContent[mp] = String((row.descriptions && row.descriptions[mp]) || ''); });
        buildMpTabs();
        activeMp = ALL_MP[0];
        document.getElementById('modalAiLoadingWrap')?.classList.add('d-none');
        if (!editModal) editModal = new bootstrap.Modal(document.getElementById('editDescModal'));
        editModal.show();
    }

    function buildMpTabs() {
        const wrap = document.getElementById('modalMpTabs');
        if (!wrap) return;
        wrap.innerHTML = ALL_MP.map((mp) => `<button type="button" class="btn btn-sm btn-outline-secondary mp-tab" data-mp="${esc(mp)}">${esc(LABELS[mp])}</button>`).join('');
    }

    // Switch the active marketplace tab. stashFirst=true saves the current editor content before switching.
    function selectMp(mp, stashFirst) {
        if (stashFirst && activeMp) mpContent[activeMp] = getDescEditorContent();
        activeMp = mp;
        document.querySelectorAll('#modalMpTabs .mp-tab').forEach((b) => {
            const on = b.getAttribute('data-mp') === mp;
            b.classList.toggle('btn-primary', on);
            b.classList.toggle('btn-outline-secondary', !on);
        });
        const lbl = document.getElementById('modalActiveMpLabel');
        if (lbl) lbl.textContent = LABELS[mp] + ' — pushes at ' + (LIMITS[mp] || '') + ' char limit';
        setDescEditorContent(mpContent[mp] || '');
    }

    // ── Rich HTML editor (TinyMCE) ───────────────────────────────────────
    let descEditor = null;
    let descEditorPromise = null;
    let mpContent = {};
    let activeMp = '';

    // Bootstrap modals steal focus from TinyMCE dialogs (link/image/table); let TinyMCE keep it.
    document.addEventListener('focusin', (e) => {
        if (e.target.closest('.tox-tinymce-aux, .tox-dialog, .tox-tinymce') !== null) {
            e.stopImmediatePropagation();
        }
    });

    function ensureDescEditor() {
        if (descEditor) return Promise.resolve(descEditor);
        if (typeof tinymce === 'undefined') return Promise.resolve(null);
        if (descEditorPromise) return descEditorPromise;
        descEditorPromise = tinymce.init({
            selector: '#modalDescHtml',
            license_key: 'gpl',
            height: 380,
            menubar: false,
            plugins: 'lists link image table code',
            toolbar: 'undo redo | blocks | bold italic | bullist numlist | table | link image | removeformat code',
            branding: false,
            promotion: false,
            convert_urls: false,
            content_style: 'img{max-width:100%;height:auto;} table{max-width:100%;border-collapse:collapse;} body{font-size:14px;}',
        }).then((eds) => {
            descEditor = (eds && eds[0]) || (tinymce.get ? tinymce.get('modalDescHtml') : null);
            return descEditor;
        }).catch(() => null);
        return descEditorPromise;
    }

    function setDescEditorContent(html) {
        return ensureDescEditor().then((ed) => {
            if (ed) ed.setContent(html || '');
            else { const el = document.getElementById('modalDescHtml'); if (el) el.value = html || ''; }
        });
    }

    function getDescEditorContent() {
        if (descEditor) return descEditor.getContent();
        const el = document.getElementById('modalDescHtml');
        return el ? el.value : '';
    }

    document.getElementById('editDescModal')?.addEventListener('shown.bs.modal', () => {
        ensureDescEditor().then(() => selectMp(activeMp || ALL_MP[0], false));
    });

    document.getElementById('modalMpTabs')?.addEventListener('click', (e) => {
        const tab = e.target.closest('.mp-tab');
        if (tab) selectMp(tab.getAttribute('data-mp'), true);
    });

    document.getElementById('modalFetchLiveBtn')?.addEventListener('click', runFetchLive);
    document.getElementById('modalAiGenBtn')?.addEventListener('click', runAiGen);

    function setButtonLoading(btnId, loading, textWhenLoading = 'Loading...') {
        const btn = document.getElementById(btnId);
        if (!btn) return;
        if (!btn.dataset.originalText) btn.dataset.originalText = btn.innerHTML;
        if (loading) {
            btn.classList.add('is-loading');
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i>${esc(textWhenLoading)}`;
        } else {
            btn.classList.remove('is-loading');
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalText;
        }
    }

    // Fetch the ACTIVE marketplace's live description into the editor.
    function runFetchLive() {
        const sku = document.getElementById('modalSku')?.value || '';
        const mp = activeMp;
        if (!sku || !mp) return;
        const wrap = document.getElementById('modalAiLoadingWrap');
        const tierEl = document.getElementById('modalAiLoadingTier');
        if (wrap) wrap.classList.remove('d-none');
        if (tierEl) tierEl.textContent = 'Fetching live from ' + (LABELS[mp] || mp) + '…';
        setButtonLoading('modalFetchLiveBtn', true, 'Fetching...');
        fetch('/product-description/pull-marketplace', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ sku, marketplace: mp })
        })
            .then((r) => r.json())
            .then((res) => {
                if (res.success) {
                    mpContent[mp] = String(res.description_html || '');
                    if (activeMp === mp) setDescEditorContent(mpContent[mp]);
                    toast(res.message || ('Fetched ' + (LABELS[mp] || mp)));
                } else {
                    toast(res.message || 'Fetch failed', false);
                }
            })
            .catch((e) => toast('Fetch failed: ' + e.message, false))
            .finally(() => {
                if (wrap) wrap.classList.add('d-none');
                setButtonLoading('modalFetchLiveBtn', false);
            });
    }

    // AI-generate a description for the ACTIVE marketplace at its tier length.
    function runAiGen() {
        const mp = activeMp;
        if (!mp) return;
        const name = document.getElementById('modalProductLabel').textContent || '';
        const tier = tierForMp(mp);
        const tmp = document.createElement('div');
        tmp.innerHTML = getDescEditorContent() || '';
        const current = (tmp.textContent || '').replace(/\s+/g, ' ').trim();
        const wrap = document.getElementById('modalAiLoadingWrap');
        const tierEl = document.getElementById('modalAiLoadingTier');
        if (wrap) wrap.classList.remove('d-none');
        if (tierEl) tierEl.textContent = 'Generating ' + tier + '-char description for ' + (LABELS[mp] || mp) + '…';
        setButtonLoading('modalAiGenBtn', true, 'Generating...');
        fetch('/product-description/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ product_name: name, current_text: current, tier })
        })
            .then((r) => r.json())
            .then((res) => {
                if (res.success && res.description) {
                    mpContent[mp] = String(res.description);
                    if (activeMp === mp) setDescEditorContent(mpContent[mp]);
                    toast('AI description generated (' + (res.length || res.description.length) + ' chars)');
                } else toast(res.message || 'AI failed', false);
            })
            .catch((e) => toast('AI error: ' + e.message, false))
            .finally(() => {
                if (wrap) wrap.classList.add('d-none');
                setButtonLoading('modalAiGenBtn', false);
            });
    }

    // Row Pull button: fetch ALL marketplaces' live descriptions for this SKU and save them (no modal).
    function runPullAll(sku, btn) {
        if (!sku) return;
        if (btn) { btn.disabled = true; btn.dataset.o = btn.innerHTML; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
        toast('Fetching all marketplaces for ' + sku + '…');
        fetch('/product-description/pull-all', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ sku })
        })
            .then((r) => r.json())
            .then((res) => {
                toast(res.message || 'Pull complete', !!res.success);
                loadData(currentPage);
            })
            .catch((e) => toast('Pull failed: ' + e.message, false))
            .finally(() => {
                if (btn) { btn.disabled = false; btn.innerHTML = btn.dataset.o || '<i class="fas fa-cloud-download-alt"></i>'; }
            });
    }

    // Save all marketplaces' edited descriptions to their metrics (no push).
    document.getElementById('modalSavePmBtn')?.addEventListener('click', () => {
        const sku = document.getElementById('modalSku').value;
        if (activeMp) mpContent[activeMp] = getDescEditorContent();
        setButtonLoading('modalSavePmBtn', true, 'Saving...');
        const targets = ALL_MP.filter((mp) => typeof mpContent[mp] === 'string');
        const tasks = targets.map((mp) => fetch('/product-description/save-marketplace', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ sku, marketplace: mp, description: mpContent[mp] })
        }).then((r) => r.json()).then((res) => ({ mp, ok: !!res.success })).catch(() => ({ mp, ok: false })));
        Promise.all(tasks)
            .then((results) => {
                const failed = results.filter((x) => !x.ok).map((x) => LABELS[x.mp] || x.mp);
                const row = bySku.get(String(sku));
                if (row) {
                    row.descriptions = row.descriptions || {};
                    targets.forEach((mp) => { row.descriptions[mp] = mpContent[mp]; });
                }
                renderTable();
                if (failed.length) toast('Saved, but failed: ' + failed.join(', '), false);
                else toast('Saved descriptions for all marketplaces.');
            })
            .catch((e) => toast('Save failed: ' + e.message, false))
            .finally(() => setButtonLoading('modalSavePmBtn', false));
    });

    /** Appends per-marketplace attempt details when the server used automatic retries. */
    function summarizeMarketplacePushRetries(results) {
        if (!results || typeof results !== 'object') return '';
        const parts = [];
        Object.keys(results).forEach((mp) => {
            const r = results[mp];
            if (!r || typeof r !== 'object') return;
            const label = LABELS[mp] || mp;
            const att = r.attempts != null ? r.attempts : 1;
            if (r.success && r.retried) parts.push(`${label}: ok after ${att} attempts`);
            else if (!r.success && att > 1) parts.push(`${label}: failed after ${att} attempts`);
        });
        return parts.length ? parts.join(' · ') : '';
    }

    /**
     * @param {string|null} loadingBtnId — optional main push button id: disabled for whole request; shows "Retrying..." after 2s while waiting (server may retry).
     */
    function pushPayload(sku, updates, done, doneOnlyOnSuccess, loadingBtnId) {
        const updateMps = Array.isArray(updates) ? updates.map((u) => u.marketplace) : [];
        if (!confirmEbay3Push(updateMps)) {
            if (typeof done === 'function') done(false);
            return;
        }
        let retryLabelTimer;
        if (loadingBtnId) {
            retryLabelTimer = setTimeout(() => {
                setButtonLoading(loadingBtnId, true, 'Retrying...');
            }, 2000);
        }
        fetch('/product-description/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ sku, updates })
        })
            .then((r) => r.json())
            .then((res) => {
                const detail = summarizeMarketplacePushRetries(res.results);
                if (res.success) {
                    toast((res.message || 'Pushed') + (detail ? ' — ' + detail : ''));
                    updates.forEach((u) => {
                        const row = bySku.get(String(sku));
                        if (row) {
                            if (!row.descriptions) row.descriptions = {};
                            row.descriptions[u.marketplace] = u.description;
                        }
                    });
                    if (typeof done === 'function') done(true);
                } else {
                    const failMsg = (res.message || 'Push failed') + (detail ? ' — ' + detail : '');
                    toast(failMsg, false);
                    if (typeof done === 'function') done(false);
                }
                loadData(currentPage);
            })
            .catch((e) => {
                toast('Push failed: ' + e.message, false);
                if (typeof done === 'function') done(false);
            })
            .finally(() => {
                if (retryLabelTimer) clearTimeout(retryLabelTimer);
                if (loadingBtnId) setButtonLoading(loadingBtnId, false);
            });
    }

    document.getElementById('pushSelectedBtn')?.addEventListener('click', async () => {
        const pairs = [];
        document.querySelectorAll('.dm-sel-mp:checked').forEach((chk) => {
            const sku = chk.dataset.sku;
            const mp = chk.dataset.mp;
            const row = bySku.get(String(sku));
            const text = row ? getPmTextForMp(row, mp) : '';
            if (!text) return;
            const lim = LIMITS[mp] || 1500;
            const t = text.length > lim ? text.slice(0, lim) : text;
            pairs.push({ sku, mp, description: t });
        });
        if (!pairs.length) {
            toast('Select marketplace checkboxes for rows with PM text for that tier.', false);
            return;
        }
        const byS = {};
        pairs.forEach((p) => {
            if (!byS[p.sku]) byS[p.sku] = [];
            byS[p.sku].push({ marketplace: p.mp, description: p.description });
        });
        const bulkBtn = document.getElementById('pushSelectedBtn');
        const bulkAllBtn = document.getElementById('pushAllBtn');
        const prevHtml = bulkBtn ? bulkBtn.innerHTML : '';
        let retryLabelTimer;
        if (bulkBtn) {
            bulkBtn.disabled = true;
            bulkBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Pushing...';
            if (bulkAllBtn) bulkAllBtn.disabled = true;
            retryLabelTimer = setTimeout(() => {
                if (bulkBtn && bulkBtn.disabled) bulkBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Retrying...';
            }, 2000);
        }
        try {
            for (const sku of Object.keys(byS)) {
                await new Promise((resolve) => pushPayload(sku, byS[sku], resolve));
            }
            toast('Bulk push finished');
        } finally {
            if (retryLabelTimer) clearTimeout(retryLabelTimer);
            if (bulkBtn) {
                bulkBtn.disabled = false;
                bulkBtn.innerHTML = prevHtml;
            }
            if (bulkAllBtn) bulkAllBtn.disabled = false;
        }
    });

    document.getElementById('pushAllBtn')?.addEventListener('click', () => {
        if (!confirm('Push each row\'s tier-specific description to every marketplace on this page? Rows missing tier text are skipped.')) return;
        const tasks = [];
        tableData.forEach((row) => {
            const sku = String(row.SKU || '');
            ALL_MP.forEach((mp) => {
                const text = getPmTextForMp(row, mp);
                if (!text) return;
                const lim = LIMITS[mp] || 1500;
                const d = text.length > lim ? text.slice(0, lim) : text;
                tasks.push({ sku, mp, description: d });
            });
        });
        if (!tasks.length) { toast('No tier descriptions to push on this page.', false); return; }
        const bulkBtn = document.getElementById('pushAllBtn');
        const selBtn = document.getElementById('pushSelectedBtn');
        const prevHtml = bulkBtn ? bulkBtn.innerHTML : '';
        let retryLabelTimer;
        if (bulkBtn) {
            bulkBtn.disabled = true;
            bulkBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Pushing...';
            if (selBtn) selBtn.disabled = true;
            retryLabelTimer = setTimeout(() => {
                if (bulkBtn && bulkBtn.disabled) bulkBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Retrying...';
            }, 2000);
        }
        let i = 0;
        function next() {
            if (i >= tasks.length) {
                if (retryLabelTimer) clearTimeout(retryLabelTimer);
                if (bulkBtn) {
                    bulkBtn.disabled = false;
                    bulkBtn.innerHTML = prevHtml;
                }
                if (selBtn) selBtn.disabled = false;
                toast('Push ALL complete');
                loadData(currentPage);
                return;
            }
            const { sku, mp, description } = tasks[i++];
            pushPayload(sku, [{ marketplace: mp, description }], next);
        }
        next();
    });

    document.getElementById('skuSearchDm')?.addEventListener('input', scheduleSearch);
    document.getElementById('previewSearchDm')?.addEventListener('input', scheduleSearch);
    document.getElementById('retryLoadBtn')?.addEventListener('click', () => loadData(currentPage));

    document.getElementById('exportBtn')?.addEventListener('click', () => {
        const rows = tableData.map((row) => {
            const o = {
                SKU: row.SKU,
                Product: row.Parent || '',
                description_1500: row.description_1500 || row.product_description || '',
                description_1000: row.description_1000 || '',
                description_800: row.description_800 || '',
                description_600: row.description_600 || '',
            };
            const d = row.descriptions || {};
            ALL_MP.forEach((mp) => { o[LABELS[mp]] = d[mp] || ''; });
            return o;
        });
        const ws = XLSX.utils.json_to_sheet(rows);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Descriptions');
        XLSX.writeFile(wb, 'description_master_' + new Date().toISOString().slice(0, 10) + '.xlsx');
        toast('Exported ' + rows.length + ' rows');
    });

    document.getElementById('importBtn')?.addEventListener('click', () => document.getElementById('importFile').click());
    document.getElementById('importFile')?.addEventListener('change', (ev) => {
        const file = ev.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const data = new Uint8Array(e.target.result);
                const wb = XLSX.read(data, { type: 'array' });
                const sheet = wb.Sheets[wb.SheetNames[0]];
                const json = XLSX.utils.sheet_to_json(sheet);
                json.forEach((row) => {
                    const sku = row.SKU || row.sku;
                    if (!sku) return;
                    const payload = { sku };
                    if (row.description_1500 != null) payload.description_1500 = String(row.description_1500);
                    else if (row.PM_description != null) payload.description_1500 = String(row.PM_description);
                    else if (row.product_description != null) payload.product_description = String(row.product_description);
                    if (row.description_1000 != null) payload.description_1000 = String(row.description_1000);
                    if (row.description_800 != null) payload.description_800 = String(row.description_800);
                    if (row.description_600 != null) payload.description_600 = String(row.description_600);
                    if (Object.keys(payload).length > 1) {
                        fetch('/product-description/save', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                    }
                });
                toast('Import queued; reloading…');
                setTimeout(() => loadData(currentPage), 1500);
            } catch (err) { toast('Import failed', false); }
            ev.target.value = '';
        };
        reader.readAsArrayBuffer(file);
    });

    loadData(1);
});
    </script>
@endsection
