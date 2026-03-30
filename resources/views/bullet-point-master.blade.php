@extends('layouts.vertical', ['title' => 'Bullet Points Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <style>
        .card.bp-master-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .card.bp-master-card .card-body { padding: 1.25rem 1.5rem; }
        .bp-master-toolbar { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; }
        .bp-master-toolbar .btn { padding:.3rem .6rem; font-size:.8rem; border-radius:6px; }
        .table-responsive { position:relative; border:1px solid #e2e8f0; border-radius:10px; max-height:640px; overflow:auto; box-shadow:0 2px 8px rgba(0,0,0,.04); background:#fff; }
        #bullet-master-table thead th { position:sticky; top:0; vertical-align:middle!important; background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%)!important; color:#fff; z-index:10; padding:6px 8px; font-size:10px; font-weight:600; text-transform:uppercase; white-space:nowrap; }
        #bullet-master-table thead .th-caption { display:flex; align-items:center; gap:6px; }
        #bullet-master-table thead .th-sub { margin-top:4px; }
        #bullet-master-table thead input, #bullet-master-table thead select { background:rgba(255,255,255,.95); border:none; border-radius:4px; color:#333; padding:4px 6px; width:100%; font-size:10px; }
        #bullet-master-table tbody td { padding:8px 10px; vertical-align:middle!important; border-bottom:1px solid #edf2f9; font-size:11px; line-height:1.35; color:#475569; }
        #bullet-master-table tbody tr:nth-child(even){ background:#f8fafc; }
        #bullet-master-table tbody tr:hover{ background:#e8f0fe; }
        .table-img-cell img { width:36px; height:36px; object-fit:cover; border-radius:4px; }
        .preview-cell { max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; cursor:help; }
        .action-buttons-cell { white-space:nowrap; vertical-align:middle!important; }
        .action-buttons-group { display:flex; align-items:center; gap:6px; }
        .action-btn { padding:5px 10px; border:none; border-radius:6px; font-size:11px; font-weight:500; display:inline-flex; align-items:center; gap:4px; }
        .view-btn { background:#17a2b8; color:#fff; }
        .edit-btn { background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color:#fff; }
        /* Title Master–style horizontal marketplace cells */
        .marketplaces-cell { vertical-align:middle!important; }
        .bp-mp-inline { display:flex; flex-wrap:wrap; align-items:flex-end; gap:6px; justify-content:flex-start; min-width:120px; }
        .bp-mp-th-title { font-weight:600; letter-spacing:0.2px; }
        .bp-mp-th-legend { margin-top:4px; font-size:8px; font-weight:500; opacity:0.92; line-height:1.3; text-transform:none; letter-spacing:0; white-space:normal; max-width:220px; }
        .bp-mp-th-icons { display:flex; flex-wrap:wrap; gap:4px; margin-top:6px; justify-content:center; align-items:center; }
        .bp-mp-th-pill { width:22px; height:22px; border-radius:4px; font-size:8px; font-weight:700; color:#fff; display:inline-flex; align-items:center; justify-content:center; line-height:1; }
        .bp-mp-stack { display:flex; flex-direction:column; align-items:center; gap:3px; border:none; background:transparent; padding:0; cursor:pointer; }
        .bp-mp-stack:hover .marketplace-btn:not(:disabled) { transform:translateY(-1px); box-shadow:0 2px 6px rgba(0,0,0,.18); }
        .bp-mp-dot { width:10px; height:10px; border-radius:50%; border:2px solid #94a3b8; background:transparent; transition:background .15s,border-color .15s; flex-shrink:0; }
        .bp-mp-dot.pushed { background:#22c55e; border-color:#22c55e; }
        .marketplace-btn { width:28px; height:28px; border:none; border-radius:4px; color:#fff; font-weight:600; font-size:11px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all .2s; padding:0; }
        .btn-ebay1 { background-color:#0d6efd; }
        .btn-ebay2 { background-color:#198754; }
        .btn-ebay3 { background-color:#fd7e14; }
        .btn-macy { background-color:#0d6efd; }
        .btn-amazon { background-color:#ff9900; }
        .btn-temu { background-color:#ff6b00; }
        .btn-reverb { background-color:#333333; }
        .btn-shopify { background-color:#7cb342; }
        .btn-shopify-pls { background-color:#5c6bc0; }
        .mp-counter { font-size:10px; color:#6c757d; }
        .mp-counter.warning { color:#b8860b; font-weight:600; }
        .mp-counter.error { color:#dc3545; font-weight:700; }
        .group-badge { font-size:10px; }
        .btn-push-all { background:#ff9900!important; color:#232f3e!important; font-weight:600; }
        .btn-push-all:hover { background:#e88b00!important; color:#fff!important; }
        .toast-container { z-index:1100; }
        .rainbow-loader { display:none; text-align:center; padding:40px; }
        .rainbow-loader .loading-text { margin-top:16px; font-weight:600; color:#2c6ed5; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        .ai-edit-panel { border:1px solid #dee2e6; border-radius:8px; padding:10px; background:#f8fafc; }
        .modal-market-wrap { border:1px solid #dee2e6; border-radius:8px; padding:10px; background:#fff; }
        /* View-all bullet points modal */
        #viewRowModal .bp-view-section { margin-bottom:1.25rem; }
        #viewRowModal .bp-view-section-title {
            font-family: ui-monospace, Consolas, monospace;
            font-size:11px; font-weight:700; color:#1e40af; letter-spacing:0.02em;
            padding:.35rem .5rem; background:#eff6ff; border-radius:6px; border-left:4px solid #2563eb;
            margin-bottom:.65rem;
        }
        #viewRowModal .bp-view-mp { margin-bottom:1rem; padding-bottom:1rem; border-bottom:1px solid #e2e8f0; }
        #viewRowModal .bp-view-mp:last-child { border-bottom:none; padding-bottom:0; margin-bottom:0; }
        #viewRowModal .bp-view-mp-label { font-weight:600; color:#0f172a; font-size:13px; margin-bottom:.35rem; display:flex; align-items:center; justify-content:space-between; gap:.5rem; flex-wrap:wrap; }
        #viewRowModal .bp-view-char { font-size:11px; font-weight:500; color:#64748b; }
        #viewRowModal .bp-view-char.at-min { color:#15803d; font-weight:600; }
        #viewRowModal .bp-view-char.under-min { color:#b45309; font-weight:600; }
        #viewRowModal .bp-view-body {
            font-size:12px; line-height:1.45; color:#334155; white-space:pre-wrap; word-break:break-word;
            background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:.65rem .75rem; min-height:2.25rem;
        }
        #viewRowModal .bp-view-empty { color:#94a3b8; font-style:italic; }
        #viewRowModal .modal-body { max-height:min(70vh, 560px); overflow-y:auto; }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Bullet Points Master',
        'sub_title' => 'Manage Product Bullet Points',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card bp-master-card">
                <div class="card-body">
                    <div class="mb-3 bp-master-toolbar">
                        <button id="exportBtn" class="btn btn-primary"><i class="fas fa-download"></i> Export</button>
                        <button id="importBtn" class="btn btn-info"><i class="fas fa-upload"></i> Import</button>
                        <button id="pushSelectedBtn" class="btn btn-secondary"><i class="fas fa-cloud-upload-alt"></i> Push Selected</button>
                        <button id="pushAllBtn" class="btn btn-push-all"><i class="fas fa-cloud-upload-alt"></i> Push ALL to All Marketplaces</button>
                        <span class="text-muted small" id="rowCountBadge">0 products</span>
                        <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
                    </div>

                    <div class="table-responsive">
                        <table id="bullet-master-table" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>
                                        <div class="d-flex align-items-center gap-2"><span>SKU</span><span id="skuCountBp">(0)</span></div>
                                        <input type="text" id="skuSearchBp" class="th-sub mt-1" placeholder="Search SKU">
                                    </th>
                                    <th>Product Name</th>
                                    <th>
                                        <div class="th-caption">Current Bullets (Preview) <span id="previewCountBp">(0)</span></div>
                                        <input type="text" id="previewSearchBp" class="th-sub" placeholder="Search preview">
                                    </th>
                                    <th>Action</th>
                                    <th title="eBay1–3, Macy's, Amazon, Temu, Reverb — min 190 chars per bullet">
                                        <div class="bp-mp-th-title">MARKET PLACES</div>
                                        <div class="bp-mp-th-icons">
                                            <span class="bp-mp-th-pill btn-ebay1">E1</span><span class="bp-mp-th-pill btn-ebay2">E2</span><span class="bp-mp-th-pill btn-ebay3">E3</span><span class="bp-mp-th-pill btn-macy">M</span><span class="bp-mp-th-pill btn-amazon">A</span><span class="bp-mp-th-pill btn-temu">T</span><span class="bp-mp-th-pill btn-reverb">R</span>
                                        </div>
                                    </th>
                                    <th title="Shopify Main, Shopify PLS — min 190 chars per bullet">
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

                    <div id="rainbow-loader" class="rainbow-loader">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                        <div class="loading-text">Loading Bullet Points Master Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editRowModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Marketplace Bullet Points</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalSku">
                    <div class="mb-2"><strong>SKU:</strong> <span id="modalSkuLabel"></span></div>
                    <div class="mb-2"><strong>Product:</strong> <span id="modalProductLabel"></span></div>
                    <div class="modal-market-wrap mb-3">
                        <div class="fw-semibold mb-2">Select Marketplaces</div>
                        <div id="modalMarketplaceChecks" class="row g-2"></div>
                    </div>
                    <div class="ai-edit-panel mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <button class="btn btn-primary btn-sm" id="editModalAiGenerateBtn"><i class="fas fa-wand-magic-sparkles"></i> AI Generate</button>
                            <span id="editModalAiLoading" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Generating...</span>
                        </div>
                        <div id="editModalAiFields" class="row g-2"></div>
                    </div>
                    <div class="small text-muted">Each non-empty bullet: minimum 190 characters; no maximum. Empty slots are ignored.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveModalBtn"><i class="fas fa-save"></i> Save Selected</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewRowModal" tabindex="-1" aria-labelledby="viewRowModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="viewRowModalTitle"><i class="fas fa-eye me-2"></i>Bullet Points</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewRowContent"></div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="viewCopyAllBtn" title="Copy all text below to clipboard">
                        <i class="fas fa-copy me-1"></i> Copy all
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
@endsection

@section('script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const MIN_BULLET_CHARS = 190;
    const MARKETPLACES = ['ebay', 'ebay2', 'ebay3', 'macy', 'amazon', 'temu', 'reverb', 'shopify_main', 'shopify_pls'];
    const LABELS = {
        ebay: 'eBay 1', ebay2: 'eBay 2', ebay3: 'eBay 3', macy: "Macy's", amazon: 'Amazon', temu: 'Temu', reverb: 'Reverb',
        shopify_main: 'Shopify Main', shopify_pls: 'Shopify PLS',
    };
    /** Labels in view modal (eBay1-style names) */
    const VIEW_LABELS = {
        ebay: 'eBay1', ebay2: 'eBay2', ebay3: 'eBay3', macy: "Macy's", amazon: 'Amazon', temu: 'Temu', reverb: 'Reverb',
        shopify_main: 'Shopify Main', shopify_pls: 'Shopify PLS',
    };
    const VIEW_SECTIONS = [
        { banner: '========== Marketplaces (min ' + MIN_BULLET_CHARS + ' chars per bullet) ==========', keys: MARKETPLACES },
    ];
    const GROUPS = {
        gChannels: ['ebay', 'ebay2', 'ebay3', 'macy', 'amazon', 'temu', 'reverb'],
        gShopify: ['shopify_main', 'shopify_pls'],
    };
    /** Short labels on tiles (horizontal row, Title Master style) */
    const MP_TILE = {
        ebay: { cls: 'btn-ebay1', short: 'E1' },
        ebay2: { cls: 'btn-ebay2', short: 'E2' },
        ebay3: { cls: 'btn-ebay3', short: 'E3' },
        macy: { cls: 'btn-macy', short: 'M' },
        amazon: { cls: 'btn-amazon', short: 'A' },
        temu: { cls: 'btn-temu', short: 'T' },
        reverb: { cls: 'btn-reverb', short: 'R' },
        shopify_main: { cls: 'btn-shopify', short: 'SM' },
        shopify_pls: { cls: 'btn-shopify-pls', short: 'PLS' },
    };
    let tableData = [];
    let editRowModal, viewRowModal;
    let preselectedMarketplace = null;
    let lastViewModalPlainText = '';

    const bySku = new Map();
    const cssEsc = (s) => (window.CSS && typeof window.CSS.escape === 'function')
        ? window.CSS.escape(String(s))
        : String(s).replace(/([ !"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');

    const esc = (s) => {
        if (s == null) return '';
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    };
    const trunc = (s, n = 56) => (!s ? '-' : (String(s).length > n ? String(s).slice(0, n) + '…' : String(s)));
    function toast(msg, ok=true) {
        if (!window.bootstrap || !window.bootstrap.Toast) {
            alert(msg);
            return;
        }
        const id = 't' + Date.now();
        const cls = ok ? 'text-bg-success' : 'text-bg-danger';
        document.getElementById('toastContainer').insertAdjacentHTML('beforeend',
            `<div id="${id}" class="toast align-items-center ${cls} border-0" role="alert"><div class="d-flex"><div class="toast-body">${esc(msg)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`);
        const el = document.getElementById(id);
        const t = new bootstrap.Toast(el, { delay: 2400 });
        t.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    function loadData() {
        document.getElementById('rainbow-loader').style.display = 'block';
        fetch('/bullet-point-master-combined-data')
            .then(r => r.json())
            .then(res => {
                const raw = Array.isArray(res.data) ? res.data : Object.values(res.data || {});
                tableData = raw.filter(i => i && i.SKU && !String(i.SKU).toUpperCase().includes('PARENT'));
                bySku.clear();
                tableData.forEach(r => bySku.set(String(r.SKU), r));
                try {
                    renderTable(tableData);
                } catch (e) {
                    console.error('renderTable failed', e);
                    const tbody = document.getElementById('table-body');
                    tbody.innerHTML = `<tr><td colspan="6" class="text-danger">Render failed: ${esc(e.message || e)}</td></tr>`;
                }
                const badge = document.getElementById('rowCountBadge');
                if (badge) badge.textContent = `${tableData.length} products`;
            })
            .catch(e => toast('Failed to load data: ' + e.message, false))
            .finally(() => { document.getElementById('rainbow-loader').style.display = 'none'; });
    }

    function mpStackHtml(sku, mp, val) {
        const pushed = (val || '').trim() !== '';
        const tile = MP_TILE[mp] || { cls: 'btn-secondary', short: '?' };
        const tip = `${LABELS[mp]}. ${pushed ? 'Pushed' : 'Not pushed'}. Click to push.`;
        return `
            <button type="button" class="bp-mp-stack" data-push-mp="${mp}" data-sku="${esc(sku)}" title="${esc(tip)}">
                <span class="bp-mp-dot ${pushed ? 'pushed' : ''}" aria-hidden="true"></span>
                <span class="marketplace-btn ${tile.cls}">${esc(tile.short)}</span>
            </button>`;
    }

    function groupCell(groupKey, sku, bp) {
        const marketplaces = GROUPS[groupKey] || [];
        return `
            <div class="marketplaces-cell">
                <div class="bp-mp-inline">
                    ${marketplaces.map(mp => mpStackHtml(sku, mp, bp[mp] ?? '')).join('')}
                </div>
            </div>`;
    }

    function renderTable(rows) {
        rows = Array.isArray(rows) ? rows : Object.values(rows || {});
        const badge = document.getElementById('rowCountBadge');
        if (badge) badge.textContent = `${rows.length} products`;
        const pc = document.getElementById('previewCountBp');
        const sc = document.getElementById('skuCountBp');
        if (pc) pc.textContent = `(${rows.length})`;
        if (sc) sc.textContent = `(${rows.length})`;
        const tbody = document.getElementById('table-body');
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No products found</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => {
            const sku = String(r.SKU || '');
            const preview = r.default_bullets || [r.bullet1, r.bullet2, r.bullet3, r.bullet4, r.bullet5].filter(Boolean).join(' ');
            const bp = r.bullet_points || {};
            return `<tr data-sku="${esc(sku)}">
                <td>${esc(sku)}</td>
                <td>${esc(r.Parent || sku)}</td>
                <td class="preview-cell" title="${esc(preview || '')}">${esc(trunc(preview, 64))}</td>
                <td class="action-buttons-cell">
                    <div class="action-buttons-group">
                        <button type="button" class="action-btn view-btn" data-view="${esc(sku)}" title="View Bullet Points" aria-label="View Bullet Points"><i class="fas fa-eye" aria-hidden="true"></i></button>
                        <button type="button" class="action-btn edit-btn" data-edit="${esc(sku)}"><i class="fas fa-edit"></i> Edit</button>
                    </div>
                </td>
                <td>${groupCell('gChannels', sku, bp)}</td>
                <td>${groupCell('gShopify', sku, bp)}</td>
            </tr>`;
        }).join('');

        bindRowEvents();
    }

    function bindRowEvents() {
        document.querySelectorAll('.view-btn[data-view]').forEach(b => b.addEventListener('click', () => openViewModal(b.dataset.view)));
        document.querySelectorAll('.edit-btn[data-edit]').forEach(b => b.addEventListener('click', () => openEditModal(b.dataset.edit)));
        document.querySelectorAll('.bp-mp-stack[data-push-mp]').forEach(b => b.addEventListener('click', () => {
            pushSingleMarketplace(b.dataset.sku, b.dataset.pushMp);
        }));
    }

    function charCountClassForMin(len, minLen) {
        if (len === 0) return 'bp-view-char';
        if (len < minLen) return 'bp-view-char under-min';
        if (len === minLen) return 'bp-view-char at-min';
        return 'bp-view-char';
    }

    function renderViewMarketplaceBlock(mpKey, label, text) {
        const raw = text == null ? '' : String(text);
        const trimmed = raw.trim();
        const empty = trimmed === '';
        const len = raw.length;
        const charCls = charCountClassForMin(len, MIN_BULLET_CHARS);
        const bodyHtml = empty
            ? `<div class="bp-view-body bp-view-empty">No bullet points saved yet</div>`
            : `<div class="bp-view-body">${esc(raw)}</div>`;
        return `
            <div class="bp-view-mp" data-mp="${esc(mpKey)}">
                <div class="bp-view-mp-label">
                    <span>${esc(label)}:</span>
                    <span class="${charCls}">${len} character${len === 1 ? '' : 's'} (min ${MIN_BULLET_CHARS})</span>
                </div>
                ${bodyHtml}
            </div>`;
    }

    function buildViewModalHtml(sku, row) {
        const bp = row.bullet_points || {};
        const lines = [];

        let html = `<div class="mb-2 pb-2 border-bottom"><strong>Product:</strong> ${esc(row.Parent || sku)}</div>`;

        VIEW_SECTIONS.forEach((sec) => {
            html += `<div class="bp-view-section">`;
            html += `<div class="bp-view-section-title">${esc(sec.banner)}</div>`;
            lines.push(sec.banner);
            sec.keys.forEach((mp) => {
                const label = VIEW_LABELS[mp] || LABELS[mp] || mp;
                const text = bp[mp];
                html += renderViewMarketplaceBlock(mp, label, text);
                lines.push(`${label}: ${(text && String(text).trim()) ? String(text) : 'No bullet points saved yet'}`);
            });
            lines.push('');
            html += `</div>`;
        });

        const extraKeys = Object.keys(bp).filter((k) => {
            if (VIEW_SECTIONS.some((s) => s.keys.includes(k))) return false;
            return true;
        });
        if (extraKeys.length) {
            html += `<div class="bp-view-section">`;
            html += `<div class="bp-view-section-title">${esc('========== Other (from API) ==========')}</div>`;
            lines.push('========== Other (from API) ==========');
            extraKeys.sort().forEach((k) => {
                const text = bp[k];
                html += renderViewMarketplaceBlock(k, k, text);
                lines.push(`${k}: ${(text && String(text).trim()) ? String(text) : 'No bullet points saved yet'}`);
                lines.push('');
            });
            html += `</div>`;
        }

        lastViewModalPlainText = `Bullet Points - ${sku}\nProduct: ${row.Parent || sku}\n\n` + lines.join('\n').trim();
        return html;
    }

    function openViewModal(sku) {
        const row = bySku.get(String(sku));
        if (!row) return;
        const titleEl = document.getElementById('viewRowModalTitle');
        if (titleEl) {
            titleEl.innerHTML = `<i class="fas fa-eye me-2" aria-hidden="true"></i>${esc('Bullet Points - ' + sku)}`;
        }
        document.getElementById('viewRowContent').innerHTML = buildViewModalHtml(sku, row);
        if (viewRowModal) viewRowModal.show();
    }

    function copyViewModalToClipboard() {
        const text = lastViewModalPlainText || '';
        if (!text.trim()) {
            toast('Nothing to copy', false);
            return;
        }
        const done = () => toast('Copied all bullet points to clipboard');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopy(text, done));
        } else {
            fallbackCopy(text, done);
        }
    }

    function fallbackCopy(text, onOk) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            onOk();
        } catch (e) {
            toast('Copy failed', false);
        }
        document.body.removeChild(ta);
    }

    function openEditModal(sku) {
        const row = bySku.get(String(sku));
        if (!row) return;
        document.getElementById('modalSku').value = sku;
        document.getElementById('modalSkuLabel').textContent = sku;
        document.getElementById('modalProductLabel').textContent = row.Parent || sku;
        document.getElementById('modalMarketplaceChecks').innerHTML = MARKETPLACES.map(mp => `
            <div class="col-md-3 col-sm-4 col-6">
                <label class="form-check">
                    <input type="checkbox" class="form-check-input modal-mp-check" data-mp="${mp}" ${preselectedMarketplace ? (preselectedMarketplace === mp ? 'checked' : '') : ''}>
                    <span>${esc(LABELS[mp])}</span>
                </label>
            </div>
        `).join('');
        renderEditModalAiFields(row);
        if (editRowModal) editRowModal.show();
        preselectedMarketplace = null;
    }

    function renderEditModalAiFields(row) {
        const current = splitBulletsForModal((row.default_bullets || '').trim() !== '' ? row.default_bullets : [row.bullet1, row.bullet2, row.bullet3, row.bullet4, row.bullet5].filter(Boolean).join('\n'));
        document.getElementById('editModalAiFields').innerHTML = [1,2,3,4,5].map(i => `
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label mb-1">Bullet ${i} <span id="editAiCount${i}" class="text-muted">0 chars (min 190)</span></label>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Rating">
                        <button type="button" class="btn btn-outline-success edit-ai-rate" data-idx="${i}" data-rating="good"><i class="fas fa-thumbs-up"></i></button>
                        <button type="button" class="btn btn-outline-danger edit-ai-rate" data-idx="${i}" data-rating="bad"><i class="fas fa-thumbs-down"></i></button>
                    </div>
                </div>
                <textarea class="form-control edit-ai-bullet" data-idx="${i}" rows="4">${esc(current[i-1] || '')}</textarea>
            </div>
        `).join('');
        bindEditAICountersAndRatings();
    }

    function splitBulletsForModal(text) {
        const normalized = String(text || '').replace(/\r/g, '\n');
        const raw = normalized.split(/\n|[;|]/).map(s => s.replace(/^[-*\d\.\)\s]+/, '').trim()).filter(Boolean);
        const out = raw.slice(0, 5);
        while (out.length < 5) out.push('');
        return out.map(v => v);
    }

    function bindEditAICountersAndRatings() {
        document.querySelectorAll('.edit-ai-bullet').forEach(t => {
            const idx = t.dataset.idx;
            const update = () => {
                const len = t.value.length;
                const el = document.getElementById('editAiCount' + idx);
                if (el) {
                    el.textContent = `${len} chars (min 190)`;
                    el.classList.toggle('text-warning', len > 0 && len < 190);
                    el.classList.toggle('text-muted', len === 0);
                }
            };
            t.addEventListener('input', update);
            update();
        });

        document.querySelectorAll('.edit-ai-rate').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = this.dataset.idx;
                const rating = this.dataset.rating;
                const groupButtons = document.querySelectorAll(`.edit-ai-rate[data-idx="${idx}"]`);
                groupButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const field = document.querySelector(`.edit-ai-bullet[data-idx="${idx}"]`);
                if (field) field.dataset.rating = rating;
            });
        });
    }

    function getFiveBulletsFromModal() {
        return Array.from(document.querySelectorAll('.edit-ai-bullet')).map(t => t.value.trim()).filter(Boolean);
    }

    /** Five slots in order (empty strings allowed) — use for per-bullet validation. */
    function getBulletLinesFromModal() {
        return Array.from(document.querySelectorAll('.edit-ai-bullet')).map(t => t.value.trim());
    }

    /** Newline-separated payload; preserves empty slots so bullet N in the UI matches line N after split. */
    function bulletLinesToPayload(lines) {
        return lines.join('\n');
    }

    /** Non-empty bullets must be at least MIN_BULLET_CHARS (empty slots skipped). Server uses the same rule. */
    function validateBulletMinimumLengths(lines) {
        const failures = [];
        lines.forEach((line, i) => {
            if (!line) return;
            if (line.length < MIN_BULLET_CHARS) {
                failures.push(`Bullet ${i + 1} is only ${line.length} characters (minimum ${MIN_BULLET_CHARS} required).`);
            }
        });
        return failures.length ? failures.join(' ') : null;
    }

    function getBulletLinesForPush(sku, row) {
        const fromModal = getBulletLinesFromModal();
        if (fromModal.some(l => l.length)) {
            return fromModal;
        }
        return splitBulletsForModal((row.default_bullets || '').trim() !== '' ? row.default_bullets : [row.bullet1, row.bullet2, row.bullet3, row.bullet4, row.bullet5].filter(Boolean).join('\n')).map(s => s.trim());
    }

    function pushSingleMarketplace(sku, mp) {
        const row = bySku.get(String(sku));
        if (!row) return;
        const lines = getBulletLinesForPush(sku, row);
        const bullets = lines.filter(Boolean);
        if (!bullets.length) {
            preselectedMarketplace = mp;
            openEditModal(sku);
            toast('Add bullet points in the modal, then click the marketplace again to push.', false);
            return;
        }
        const limitErr = validateBulletMinimumLengths(lines);
        if (limitErr) { toast(limitErr, false); return; }
        const combined = bulletLinesToPayload(lines);
        const payload = { sku, updates: [{ marketplace: mp, bullet_points: combined }] };
        fetch('/bullet-point-master/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(res => {
            const rmp = res.results && (res.results[mp] || res.results[String(mp).toLowerCase()]);
            if (res.success && rmp && rmp.success) {
                toast(`${LABELS[mp]} pushed`);
                loadData();
            } else {
                const msg = rmp ? rmp.message : (res.message || 'Push failed');
                toast(msg, false);
            }
        })
        .catch(e => toast('Push failed: ' + e.message, false));
    }

    function bulkPush(mode) {
        toast('Use Edit modal to push bullet points to selected marketplaces.', false);
    }

    function exportData() {
        const rows = tableData.map(row => {
            const sku = String(row.SKU || '');
            const bp = row.bullet_points || {};
            const out = { SKU: sku, ProductName: row.Parent || sku, Preview: row.default_bullets || '' };
            MARKETPLACES.forEach(mp => { out[LABELS[mp]] = bp[mp] || ''; });
            return out;
        });
        const ws = XLSX.utils.json_to_sheet(rows);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Bullet Points Master');
        XLSX.writeFile(wb, 'bullet_points_master_' + new Date().toISOString().split('T')[0] + '.xlsx');
    }

    function importData(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
                const json = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]]);
                if (!json.length) { toast('No rows in file', false); return; }

                json.forEach(row => {
                    const sku = String(row.SKU || '').trim();
                    if (!sku) return;
                    MARKETPLACES.forEach(mp => {
                        const key = LABELS[mp];
                        const v = row[key];
                        if (typeof v === 'string') {
                            const item = bySku.get(sku);
                            if (item) {
                                item.bullet_points = item.bullet_points || {};
                                item.bullet_points[mp] = v;
                            }
                        }
                    });
                });
                renderTable(tableData);
                toast('Import loaded into table. Open Edit modal and save to push.');
            } catch (err) {
                toast('Import failed: ' + err.message, false);
            }
        };
        reader.readAsArrayBuffer(file);
    }

    function applyTableFilters() {
        const skuQ = (document.getElementById('skuSearchBp') && document.getElementById('skuSearchBp').value.toLowerCase().trim()) || '';
        const prevQ = (document.getElementById('previewSearchBp') && document.getElementById('previewSearchBp').value.toLowerCase().trim()) || '';
        let rows = tableData;
        if (skuQ) {
            rows = rows.filter(r => String(r.SKU || '').toLowerCase().includes(skuQ));
        }
        if (prevQ) {
            rows = rows.filter(r => {
                const preview = String(r.default_bullets || [r.bullet1, r.bullet2, r.bullet3, r.bullet4, r.bullet5].filter(Boolean).join(' ') || '').toLowerCase();
                return preview.includes(prevQ) || String(r.Parent || '').toLowerCase().includes(prevQ);
            });
        }
        renderTable(rows);
    }
    const skuSearchBp = document.getElementById('skuSearchBp');
    const previewSearchBp = document.getElementById('previewSearchBp');
    if (skuSearchBp) skuSearchBp.addEventListener('input', applyTableFilters);
    if (previewSearchBp) previewSearchBp.addEventListener('input', applyTableFilters);

    document.getElementById('saveModalBtn').addEventListener('click', function() {
        const sku = document.getElementById('modalSku').value;
        const lines = getBulletLinesFromModal();
        const aiBullets = lines.filter(Boolean);
        if (!aiBullets.length) { toast('Add at least one bullet point before saving.', false); return; }

        const combined = bulletLinesToPayload(lines);
        const selected = Array.from(document.querySelectorAll('.modal-mp-check:checked')).map(chk => chk.dataset.mp);
        const minErr = validateBulletMinimumLengths(lines);
        if (minErr) { toast(minErr, false); return; }
        const updates = selected.map(mp => ({ marketplace: mp, bullet_points: combined }));
        if (!updates.length) { toast('Select at least one marketplace.', false); return; }

        const btn = this; const old = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        fetch('/bullet-point-master/update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ sku, updates })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) { toast('Saved marketplace bullet points'); if (editRowModal) editRowModal.hide(); loadData(); }
            else toast(res.message || 'Save failed', false);
        })
        .catch(e => toast('Save failed: ' + e.message, false))
        .finally(() => { btn.disabled = false; btn.innerHTML = old; });
    });

    const viewCopyAllBtn = document.getElementById('viewCopyAllBtn');
    if (viewCopyAllBtn) viewCopyAllBtn.addEventListener('click', copyViewModalToClipboard);

    document.getElementById('pushSelectedBtn').addEventListener('click', () => bulkPush('selected'));
    document.getElementById('pushAllBtn').addEventListener('click', () => bulkPush('all'));
    document.getElementById('exportBtn').addEventListener('click', exportData);
    document.getElementById('importBtn').addEventListener('click', () => document.getElementById('importFile').click());
    document.getElementById('importFile').addEventListener('change', function(e) { if (e.target.files[0]) importData(e.target.files[0]); this.value = ''; });

    document.getElementById('editModalAiGenerateBtn').addEventListener('click', function() {
        const sku = document.getElementById('modalSku').value;
        const productName = document.getElementById('modalProductLabel').textContent || sku;
        const btn = this;
        btn.disabled = true;
        document.getElementById('editModalAiLoading').style.display = 'inline';
        fetch('/bullet-point-master/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ product_id: sku, sku, product_name: productName })
        }).then(async (r) => {
            const payload = await r.json().catch(() => ({}));
            if (!r.ok || !payload.success) {
                throw new Error(payload.message || 'AI generation failed');
            }
            return payload;
        }).then((res) => {
            const bullets = res.bullets || [];
            document.querySelectorAll('.edit-ai-bullet').forEach((t, i) => {
                t.value = (bullets[i] || '');
                t.dispatchEvent(new Event('input'));
            });
            const lens = bullets.map(b => String(b || '').length);
            console.info('[BP AI] generated bullet lengths', lens);
            toast('AI bullets generated');
        }).catch(e => toast('AI generation failed: ' + e.message, false))
        .finally(() => {
            btn.disabled = false;
            document.getElementById('editModalAiLoading').style.display = 'none';
        });
    });

    function waitForBootstrap() {
        if (window.bootstrap && window.bootstrap.Modal && window.bootstrap.Toast) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            const existing = document.querySelector('script[data-bp-bootstrap]');
            if (existing) {
                existing.addEventListener('load', () => resolve(), { once: true });
                existing.addEventListener('error', () => resolve(), { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';
            script.async = true;
            script.dataset.bpBootstrap = '1';
            script.onload = () => resolve();
            script.onerror = () => resolve();
            document.head.appendChild(script);
        });
    }

    waitForBootstrap().then(() => {
        if (window.bootstrap && window.bootstrap.Modal) {
            editRowModal = new bootstrap.Modal(document.getElementById('editRowModal'));
            viewRowModal = new bootstrap.Modal(document.getElementById('viewRowModal'));
        } else {
            console.warn('Bootstrap JS not available; modals/toasts will be degraded.');
        }
        loadData();
    });
});
</script>
@endsection
