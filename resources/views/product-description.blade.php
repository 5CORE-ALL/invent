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
        .btn-amazon { background:#ff9900; color:#232f3e!important; }
        .btn-temu { background:#e02020; }
        .btn-reverb { background:#1a1a1a; }
        .btn-shopify { background:#96bf48; color:#1a1a1a!important; }
        .btn-shopify-pls { background:#5c6ac4; }
        .btn-ebay1 { background:#0d6efd; }
        .btn-ebay2 { background:#198754; }
        .btn-ebay3 { background:#fd7e14; }
        .btn-macy { background:#e20074; }
        .btn-push-all { background:#ff9900!important; color:#232f3e!important; font-weight:600; }
        .action-buttons-cell { white-space:nowrap; vertical-align:middle!important; }
        .action-buttons-group { display:flex; align-items:center; gap:6px; flex-wrap:nowrap; }
        .action-btn { padding:5px 10px; border:none; border-radius:6px; font-size:11px; font-weight:500; display:inline-flex; align-items:center; gap:4px; cursor:pointer; }
        .view-btn { background:#17a2b8; color:#fff; }
        .edit-mp-btn { background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color:#fff; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        .shopify-hint { font-size:10px; color:#64748b; }
        #loadErrorBanner { display:none; }
        #viewDescModal .dm-view-body { font-size:12px; line-height:1.45; color:#334155; white-space:pre-wrap; word-break:break-word; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:.75rem; min-height:2rem; max-height:min(50vh,360px); overflow-y:auto; }
        #viewDescModal .dm-view-section-title { font-size:11px; font-weight:700; color:#1e40af; padding:.35rem .5rem; background:#eff6ff; border-radius:6px; border-left:4px solid #2563eb; margin-bottom:.5rem; margin-top:.75rem; }
        #editMarketplaceDescModal .mp-edit-block { border:1px solid #e2e8f0; border-radius:8px; padding:.75rem; margin-bottom:.75rem; background:#fafbfc; }
        #editMarketplaceDescModal .mp-edit-textarea { font-size:12px; line-height:1.45; }
        #editMarketplaceDescModal .pm-tier-btn { background:#64748b; color:#fff; }
        #dmSkeleton { min-height:200px; }
        .dm-skel-table { width:100%; border-collapse:collapse; }
        .dm-skel-table td { padding:10px 8px; }
        .dm-skel-bar { height:12px; border-radius:4px; background:linear-gradient(90deg,#e2e8f0 0%,#f1f5f9 50%,#e2e8f0 100%); background-size:200% 100%; animation:dmShimmer 1.2s ease-in-out infinite; }
        .dm-skel-bar.w-40 { width:40%; } .dm-skel-bar.w-60 { width:60%; } .dm-skel-bar.w-80 { width:80%; }
        @keyframes dmShimmer { 0%{background-position:200% 0}100%{background-position:-200% 0} }
        #dmTableShell.is-loading .dm-table-wrap { opacity:.35; pointer-events:none; }
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
                        <label class="small text-muted mb-0 me-1">Per page</label>
                        <select id="perPageSelect" class="form-select form-select-sm" style="width:88px;display:inline-block;vertical-align:middle;">
                            <option value="50">50</option>
                            <option value="75" selected>75</option>
                            <option value="100">100</option>
                        </select>
                        <button type="button" id="exportBtn" class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Export</button>
                        <button type="button" id="importBtn" class="btn btn-info btn-sm"><i class="fas fa-upload"></i> Import</button>
                        <button type="button" id="pushSelectedBtn" class="btn btn-secondary btn-sm"><i class="fas fa-cloud-upload-alt"></i> Push Selected</button>
                        <button type="button" id="pushAllBtn" class="btn btn-push-all btn-sm"><i class="fas fa-cloud-upload-alt"></i> Push ALL (this page)</button>
                        <span class="text-muted small" id="rowCountBadge">0 products</span>
                        <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
        </div>

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
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 px-1" id="dmPaginationWrap">
                            <div class="small text-muted" id="dmPageInfo"></div>
                            <nav><ul class="pagination pagination-sm mb-0" id="dmPagination"></ul></nav>
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
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit descriptions &amp; push</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalSku">
                    <div class="mb-2"><strong>SKU:</strong> <span id="modalSkuLabel"></span></div>
                    <div class="mb-3"><strong>Product:</strong> <span id="modalProductLabel"></span></div>
                    <div class="mb-3">
                        <div class="fw-semibold mb-1">Select marketplaces to push</div>
                        <div id="modalMarketplaceChecks" class="row g-1"></div>
                    </div>

                    <div class="border rounded p-2 mb-2 bg-light">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                            <span class="fw-semibold small">DESC 1500 — Amazon (A), Temu (T), Reverb (R)</span>
                            <button type="button" class="btn btn-primary btn-sm modal-ai-tier" data-tier="1500"><i class="fas fa-wand-magic-sparkles"></i> AI Generate</button>
                        </div>
                        <textarea class="form-control form-control-sm" id="modalDesc1500" rows="5" maxlength="1500" placeholder="1400–1500 characters (AI target)"></textarea>
                        <div class="small text-muted mt-1"><span id="modalDescCounter1500">0</span>/1500</div>
                    </div>
                    <div class="border rounded p-2 mb-2 bg-light">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                            <span class="fw-semibold small">DESC 1000 — Shopify Main (SM), Shopify PLS (SP)</span>
                            <button type="button" class="btn btn-primary btn-sm modal-ai-tier" data-tier="1000"><i class="fas fa-wand-magic-sparkles"></i> AI Generate</button>
                            </div>
                        <textarea class="form-control form-control-sm" id="modalDesc1000" rows="4" maxlength="1000" placeholder="900–1000 characters (AI target)"></textarea>
                        <div class="small text-muted mt-1"><span id="modalDescCounter1000">0</span>/1000</div>
                            </div>
                    <div class="border rounded p-2 mb-2 bg-light">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                            <span class="fw-semibold small">DESC 800 — eBay1 (E1), eBay2 (E2), eBay3 (E3)</span>
                            <button type="button" class="btn btn-primary btn-sm modal-ai-tier" data-tier="800"><i class="fas fa-wand-magic-sparkles"></i> AI Generate</button>
                        </div>
                        <textarea class="form-control form-control-sm" id="modalDesc800" rows="4" maxlength="800" placeholder="700–800 characters (AI target)"></textarea>
                        <div class="small text-muted mt-1"><span id="modalDescCounter800">0</span>/800</div>
                    </div>
                    <div class="border rounded p-2 mb-2 bg-light">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                            <span class="fw-semibold small">DESC 600 — Macy's (M)</span>
                            <button type="button" class="btn btn-primary btn-sm modal-ai-tier" data-tier="600"><i class="fas fa-wand-magic-sparkles"></i> AI Generate</button>
                        </div>
                        <textarea class="form-control form-control-sm" id="modalDesc600" rows="4" maxlength="600" placeholder="500–600 characters (AI target)"></textarea>
                        <div class="small text-muted mt-1"><span id="modalDescCounter600">0</span>/600</div>
                    </div>
                    <p class="shopify-hint mb-0"><i class="fas fa-info-circle"></i> Shopify Main &amp; PLS: listing body combines Bullet Points Master + the 1000-char description.</p>
                    <div class="mt-2 small text-muted d-none" id="modalAiLoadingWrap"><i class="fas fa-spinner fa-spin"></i> <span id="modalAiLoadingTier"></span></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="modalSavePmBtn"><i class="fas fa-save"></i> Save to PM</button>
                    <button type="button" class="btn btn-primary" id="modalPushBtn"><i class="fas fa-cloud-upload-alt"></i> Push to selected marketplaces</button>
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

    <div class="modal fade" id="editMarketplaceDescModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-store me-2"></i>Edit marketplace descriptions</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="mpEditSubtitle" class="small text-muted mb-2"></p>
                    <p class="small text-muted mb-3 border-bottom pb-2">Each block saves and pushes that marketplace only. Reset clears the stored copy in metrics (live listings may still show the old text until you push).</p>
                    <div id="mpEditFields"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const LIMITS = {
        amazon: 1500, temu: 1500, reverb: 1500,
        shopify_main: 1000, shopify_pls: 1000,
        ebay: 800, ebay2: 800, ebay3: 800,
        macy: 600,
    };
    const LABELS = {
        amazon: 'Amazon', temu: 'Temu', reverb: 'Reverb',
        shopify_main: 'Shopify Main', shopify_pls: 'Shopify PLS',
        ebay: 'eBay1', ebay2: 'eBay2', ebay3: 'eBay3',
        macy: "Macy's",
    };
    const GROUPS = {
        g1500: ['amazon', 'temu', 'reverb'],
        g1000: ['shopify_main', 'shopify_pls'],
        g800: ['ebay', 'ebay2', 'ebay3'],
        g600: ['macy'],
    };
    const MP_TILE = {
        amazon: 'btn-amazon', temu: 'btn-temu', reverb: 'btn-reverb',
        shopify_main: 'btn-shopify', shopify_pls: 'btn-shopify-pls',
        ebay: 'btn-ebay1', ebay2: 'btn-ebay2', ebay3: 'btn-ebay3',
        macy: 'btn-macy',
    };
    const MP_SHORT = {
        amazon: 'A', temu: 'T', reverb: 'R',
        shopify_main: 'SM', shopify_pls: 'SP',
        ebay: 'E1', ebay2: 'E2', ebay3: 'E3',
        macy: 'M',
    };
    const ALL_MP = Object.keys(LIMITS);
    const TIER_MIN_AI = { 1500: 1400, 1000: 900, 800: 700, 600: 500 };

        let tableData = [];
    const bySku = new Map();
    let editModal, viewDescModal, marketplaceEditModal;
    let mpEditCurrentSku = '';
    let tableBodyBound = false;
    let lastViewPlainText = '';
    let currentPage = 1;
    let listMeta = { total: 0, last_page: 1, per_page: 75 };
    let searchDebounce = null;

    const esc = (s) => {
        if (s == null) return '';
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    };

    function toast(msg, ok = true) {
        if (window.bootstrap && window.bootstrap.Toast) {
            const el = document.createElement('div');
            el.className = 'toast align-items-center text-bg-' + (ok ? 'success' : 'danger') + ' border-0 position-fixed bottom-0 end-0 m-3';
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
                        <button type="button" class="action-btn edit-mp-btn" data-edit-mp="${esc(sku)}" title="Edit marketplace descriptions (push per channel)"><i class="fas fa-pen"></i></button>
                        <button type="button" class="action-btn pm-tier-btn" data-edit-pm="${esc(sku)}" title="Edit Product Master tiers (1500–600) &amp; push"><i class="fas fa-align-left"></i></button>
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
            const editMpBtn = e.target.closest('[data-edit-mp]');
            if (editMpBtn) {
                openMarketplaceEditModal(editMpBtn.getAttribute('data-edit-mp'));
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

    function updateMpEditCounter(ta, mp) {
        const max = LIMITS[mp] || 1500;
        const cnt = document.getElementById('mp-edit-cnt-' + mp);
        if (!cnt || !ta) return;
        const n = ta.value.length;
        cnt.textContent = (max - n) + ' remaining · ' + n + '/' + max;
    }

    function openMarketplaceEditModal(sku) {
        mpEditCurrentSku = String(sku);
        const row = bySku.get(mpEditCurrentSku);
        if (!row) return;
        document.getElementById('mpEditSubtitle').textContent = 'SKU: ' + sku + ' — ' + (row.Parent || row.title150 || '');
        const container = document.getElementById('mpEditFields');
        container.innerHTML = ALL_MP.map((mp) => {
            const max = LIMITS[mp] || 1500;
            const shopifyHint = (mp === 'shopify_main' || mp === 'shopify_pls')
                ? '<p class="shopify-hint mb-1"><i class="fas fa-info-circle"></i> Shopify listing body combines Bullet Points Master + this description (editable).</p>'
                : '';
            return `
                <div class="mp-edit-block" data-mp="${esc(mp)}">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
                        <strong>${esc(LABELS[mp])}</strong>
                        <span class="small text-muted" id="mp-edit-cnt-${esc(mp)}">0/${max}</span>
                    </div>
                    ${shopifyHint}
                    <textarea class="form-control form-control-sm mp-edit-textarea" id="mp-edit-ta-${esc(mp)}" data-mp="${esc(mp)}" rows="4" maxlength="${max}"></textarea>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <button type="button" class="btn btn-sm btn-primary save-mp-btn" data-mp="${esc(mp)}"><i class="fas fa-cloud-upload-alt"></i> Save &amp; push</button>
                        <button type="button" class="btn btn-sm btn-outline-danger reset-mp-btn" data-mp="${esc(mp)}"><i class="fas fa-eraser"></i> Reset</button>
                    </div>
                </div>`;
        }).join('');
        ALL_MP.forEach((mp) => {
            const ta = document.getElementById('mp-edit-ta-' + mp);
            if (ta) {
                ta.value = getDisplayDescForMp(row, mp);
                updateMpEditCounter(ta, mp);
            }
        });
        if (!marketplaceEditModal) marketplaceEditModal = new bootstrap.Modal(document.getElementById('editMarketplaceDescModal'));
        marketplaceEditModal.show();
    }

    document.getElementById('editMarketplaceDescModal')?.addEventListener('input', (e) => {
        const ta = e.target.closest('.mp-edit-textarea');
        if (!ta) return;
        const mp = ta.getAttribute('data-mp');
        if (mp) updateMpEditCounter(ta, mp);
    });

    document.getElementById('editMarketplaceDescModal')?.addEventListener('click', (e) => {
        const saveBtn = e.target.closest('.save-mp-btn');
        const resetBtn = e.target.closest('.reset-mp-btn');
        const sku = mpEditCurrentSku;
        const row = bySku.get(String(sku));
        if (!sku || !row) return;

        if (saveBtn) {
            const mp = saveBtn.getAttribute('data-mp');
            const ta = document.getElementById('mp-edit-ta-' + mp);
            const raw = ta ? ta.value : '';
            const text = String(raw).trim();
            if (!text) {
                toast('Description cannot be empty. Use Reset to clear the stored copy.', false);
                return;
            }
            const lim = LIMITS[mp] || 1500;
            const chunk = text.length > lim ? text.slice(0, lim) : text;
            pushPayload(sku, [{ marketplace: mp, description: chunk }], null, false);
            return;
        }

        if (resetBtn) {
            const mp = resetBtn.getAttribute('data-mp');
            if (!mp) return;
            const label = LABELS[mp] || mp;
            if (!confirm('Clear saved description for ' + label + '? This removes the stored copy in Description Master. Live listings may still show the previous text until you push new content.')) return;
            fetch('/product-description/reset-marketplace', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ sku, marketplace: mp }),
            })
                .then((r) => r.json())
                .then((res) => {
                    if (res.success) {
                        toast(res.message || 'Cleared');
                        if (!row.descriptions) row.descriptions = {};
                        row.descriptions[mp] = '';
                        const ta = document.getElementById('mp-edit-ta-' + mp);
                        if (ta) {
                            ta.value = getDisplayDescForMp(row, mp);
                            updateMpEditCounter(ta, mp);
                        }
                        renderTable();
                        loadData(currentPage);
                    } else toast(res.message || 'Reset failed', false);
                })
                .catch((err) => toast('Reset failed: ' + err.message, false));
        }
    });

    function pushSingleMarketplace(sku, mp) {
        const row = bySku.get(String(sku));
        if (!row) return;
        const text = getDisplayDescForMp(row, mp);
        if (!text || !String(text).trim()) {
            toast('Add text for this tier in Product Master tiers (align-left) or edit marketplace copy.', false);
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
            body.innerHTML = buildSkeletonRows(12);
            sk.style.display = 'block';
        } else if (sk) {
            sk.style.display = 'none';
        }
    }

    function loadData(page) {
        hideLoadError();
        const p = page != null ? page : currentPage;
        currentPage = p;
        const perPage = parseInt(document.getElementById('perPageSelect')?.value || '75', 10) || 75;
        const qSku = (document.getElementById('skuSearchDm')?.value || '').trim();
        const qText = (document.getElementById('previewSearchDm')?.value || '').trim();

        setLoadingUi(true);
        const ctrl = new AbortController();
        const abortTimer = setTimeout(() => ctrl.abort(), 120000);

        const params = new URLSearchParams({ page: String(p), per_page: String(perPage) });
        if (qSku) params.set('q_sku', qSku);
        if (qText) params.set('q_text', qText);

        fetch('/product-description-data?' + params.toString(), {
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
                const raw = Array.isArray(res.data) ? res.data : [];
                tableData = raw.filter((i) => i && i.SKU && !String(i.SKU).toUpperCase().includes('PARENT'));
                bySku.clear();
                tableData.forEach((r) => bySku.set(String(r.SKU), r));
                listMeta = res.meta || { total: tableData.length, last_page: 1, per_page: perPage, current_page: p };
                setLoadingUi(false);
                document.getElementById('rowCountBadge').textContent = (listMeta.total != null ? listMeta.total : tableData.length) + ' products (page ' + (listMeta.current_page || p) + ' / ' + (listMeta.last_page || 1) + ')';
                document.getElementById('skuCountDm').textContent = '(' + tableData.length + ' on page)';
                renderTable();
                renderPagination();
            })
            .catch((e) => {
                clearTimeout(abortTimer);
                setLoadingUi(false);
                const msg = e.name === 'AbortError' ? 'Request timed out (2 min).' : (e.message || 'Error');
                showLoadError(msg);
                toast('Failed to load: ' + msg, false);
                const tbody = document.getElementById('table-body');
                if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-3">Load failed. Retry or check console (F12).</td></tr>';
            });
    }

    function renderTable() {
        const tbody = document.getElementById('table-body');
        bindTableBodyOnce();
        if (!tableData.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No products on this page</td></tr>';
            return;
        }
        tbody.innerHTML = tableData.map(buildRowHtml).join('');
    }

    function renderPagination() {
        const ul = document.getElementById('dmPagination');
        const info = document.getElementById('dmPageInfo');
        if (!ul || !info) return;
        const cur = listMeta.current_page || currentPage || 1;
        const last = listMeta.last_page || 1;
        const from = listMeta.from;
        const to = listMeta.to;
        const total = listMeta.total;
        info.textContent = (from != null && to != null && total != null)
            ? ('Showing ' + from + '–' + to + ' of ' + total)
            : ('Page ' + cur + ' of ' + last);

        let html = '';
        const addLi = (label, page, disabled, active) => {
            html += '<li class="page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '') + '">';
            html += '<a class="page-link" href="#" data-page="' + page + '">' + label + '</a></li>';
        };
        addLi('«', cur - 1, cur <= 1, false);
        const windowSize = 5;
        let start = Math.max(1, cur - Math.floor(windowSize / 2));
        let end = Math.min(last, start + windowSize - 1);
        start = Math.max(1, end - windowSize + 1);
        for (let i = start; i <= end; i++) addLi(String(i), i, false, i === cur);
        addLi('»', cur + 1, cur >= last, false);
        ul.innerHTML = html;
        ul.querySelectorAll('a.page-link').forEach((a) => {
            a.addEventListener('click', (ev) => {
                ev.preventDefault();
                const pg = parseInt(a.getAttribute('data-page'), 10);
                if (!pg || pg < 1 || pg > last || pg === cur) return;
                loadData(pg);
            });
        });
    }

    function scheduleSearch() {
        if (searchDebounce) clearTimeout(searchDebounce);
        searchDebounce = setTimeout(() => loadData(1), 420);
    }

    function openEditModal(sku) {
        const row = bySku.get(String(sku));
        if (!row) return;
        document.getElementById('modalSku').value = sku;
        document.getElementById('modalSkuLabel').textContent = sku;
        document.getElementById('modalProductLabel').textContent = row.Parent || row.title150 || sku;
        document.getElementById('modalDesc1500').value = String(row.description_1500 || row.product_description || '');
        document.getElementById('modalDesc1000').value = String(row.description_1000 || '');
        document.getElementById('modalDesc800').value = String(row.description_800 || '');
        document.getElementById('modalDesc600').value = String(row.description_600 || '');
        updateAllModalCounters();
        document.getElementById('modalMarketplaceChecks').innerHTML = ALL_MP.map((mp) => `
            <div class="col-6 col-md-4 col-lg-3">
                <label class="form-check small">
                    <input type="checkbox" class="form-check-input modal-mp-chk" value="${esc(mp)}">
                    <span>${esc(LABELS[mp])} <span class="text-muted">(${LIMITS[mp]})</span></span>
                </label>
            </div>
        `).join('');
        document.getElementById('modalAiLoadingWrap')?.classList.add('d-none');
        if (!editModal) editModal = new bootstrap.Modal(document.getElementById('editDescModal'));
        editModal.show();
    }

    function getModalTextForMp(mp) {
        const d1500 = document.getElementById('modalDesc1500').value.trim();
        const d1000 = document.getElementById('modalDesc1000').value.trim();
        const d800 = document.getElementById('modalDesc800').value.trim();
        const d600 = document.getElementById('modalDesc600').value.trim();
        const t = tierForMp(mp);
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

    function updateAllModalCounters() {
        [['modalDesc1500','modalDescCounter1500',1500],['modalDesc1000','modalDescCounter1000',1000],['modalDesc800','modalDescCounter800',800],['modalDesc600','modalDescCounter600',600]].forEach(([id, cid, max]) => {
            const el = document.getElementById(id);
            const c = document.getElementById(cid);
            if (el && c) c.textContent = el.value.length + '/' + max;
        });
    }

    ['modalDesc1500','modalDesc1000','modalDesc800','modalDesc600'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', updateAllModalCounters);
    });

    document.getElementById('editDescModal')?.addEventListener('click', (e) => {
        const btn = e.target.closest('.modal-ai-tier');
        if (!btn) return;
        const tier = btn.getAttribute('data-tier');
        const name = document.getElementById('modalProductLabel').textContent || '';
        const field = { '1500':'modalDesc1500','1000':'modalDesc1000','800':'modalDesc800','600':'modalDesc600' }[tier];
        const current = document.getElementById(field)?.value || '';
        const wrap = document.getElementById('modalAiLoadingWrap');
        const tierEl = document.getElementById('modalAiLoadingTier');
        if (wrap) wrap.classList.remove('d-none');
        if (tierEl) tierEl.textContent = 'Generating ' + tier + '-char description…';
        fetch('/product-description/generate', {
                method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ product_name: name, current_text: current, tier })
        })
            .then((r) => r.json())
            .then((res) => {
                if (wrap) wrap.classList.add('d-none');
                if (res.success && res.description) {
                    document.getElementById(field).value = res.description;
                    updateAllModalCounters();
                    const len = res.length || res.description.length;
                    const min = TIER_MIN_AI[tier] || 0;
                    if (len < min) toast('AI returned ' + len + ' chars (target ' + min + '+). Edit or regenerate.', false);
                    else toast('AI description generated (' + len + ' chars)');
                } else toast(res.message || 'AI failed', false);
            })
            .catch((e) => {
                if (wrap) wrap.classList.add('d-none');
                toast('AI error: ' + e.message, false);
            });
    });

    document.getElementById('modalSavePmBtn')?.addEventListener('click', () => {
        const sku = document.getElementById('modalSku').value;
        const payload = {
            sku,
            description_1500: document.getElementById('modalDesc1500').value,
            description_1000: document.getElementById('modalDesc1000').value,
            description_800: document.getElementById('modalDesc800').value,
            description_600: document.getElementById('modalDesc600').value,
        };
        fetch('/product-description/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then((r) => r.json())
            .then((res) => {
                if (res.success) {
                    toast(res.message || 'Saved');
                    loadData(currentPage);
                } else toast(res.message || 'Save failed', false);
            });
    });

    document.getElementById('modalPushBtn')?.addEventListener('click', () => {
        const sku = document.getElementById('modalSku').value;
        const checks = Array.from(document.querySelectorAll('.modal-mp-chk:checked')).map((c) => c.value);
        if (!checks.length) { toast('Select at least one marketplace', false); return; }
        const updates = [];
        for (const mp of checks) {
            const text = getModalTextForMp(mp);
            if (!text.trim()) {
                toast('Missing text for ' + LABELS[mp] + ' (fill that tier in the modal).', false);
                return;
            }
            const lim = LIMITS[mp] || 1500;
            const chunk = text.length > lim ? text.slice(0, lim) : text;
            updates.push({ marketplace: mp, description: chunk });
        }
        pushPayload(sku, updates, () => { if (editModal) editModal.hide(); }, true);
    });

    function pushPayload(sku, updates, done, doneOnlyOnSuccess) {
        fetch('/product-description/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ sku, updates })
        })
            .then((r) => r.json())
            .then((res) => {
                if (res.success) {
                    toast(res.message || 'Pushed');
                    updates.forEach((u) => {
                        const row = bySku.get(String(sku));
                        if (row) {
                            if (!row.descriptions) row.descriptions = {};
                            row.descriptions[u.marketplace] = u.description;
                        }
                    });
                    if (typeof done === 'function' && doneOnlyOnSuccess) done();
                } else toast(res.message || 'Push failed', false);
                loadData(currentPage);
                if (typeof done === 'function' && !doneOnlyOnSuccess) done();
            })
            .catch((e) => {
                toast('Push failed: ' + e.message, false);
                if (typeof done === 'function' && !doneOnlyOnSuccess) done();
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
        for (const sku of Object.keys(byS)) {
            await new Promise((resolve) => pushPayload(sku, byS[sku], resolve));
        }
        toast('Bulk push finished');
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
        let i = 0;
        function next() {
            if (i >= tasks.length) { toast('Push ALL complete'); loadData(currentPage); return; }
            const { sku, mp, description } = tasks[i++];
            pushPayload(sku, [{ marketplace: mp, description }], next);
        }
        next();
    });

    document.getElementById('skuSearchDm')?.addEventListener('input', scheduleSearch);
    document.getElementById('previewSearchDm')?.addEventListener('input', scheduleSearch);
    document.getElementById('perPageSelect')?.addEventListener('change', () => loadData(1));
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
        XLSX.writeFile(wb, 'description_master_page_' + (listMeta.current_page || 1) + '_' + new Date().toISOString().slice(0, 10) + '.xlsx');
        toast('Exported ' + rows.length + ' rows (current page)');
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
