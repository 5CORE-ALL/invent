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
        .preview-cell { max-width:230px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; cursor:help; }
        .action-buttons-group { display:flex; align-items:center; gap:6px; }
        .action-btn { padding:5px 10px; border:none; border-radius:6px; font-size:11px; font-weight:500; display:inline-flex; align-items:center; gap:4px; }
        .view-btn { background:#17a2b8; color:#fff; }
        .edit-btn { background:linear-gradient(135deg,#2c6ed5 0%,#1a56b7 100%); color:#fff; }
        .mp-cell-wrap { min-width:220px; max-width:260px; }
        .mp-group-wrap { min-width:260px; max-width:320px; }
        .mp-group-item { border:1px solid #e5e7eb; border-radius:6px; padding:6px; margin-bottom:6px; background:#fff; }
        .mp-group-item:last-child { margin-bottom:0; }
        .mp-top-row { display:flex; align-items:center; justify-content:space-between; gap:6px; margin-bottom:4px; }
        .mp-status-dot { width:10px; height:10px; border-radius:50%; border:2px solid #94a3b8; display:inline-block; margin-left:4px; }
        .mp-status-dot.success { background:#22c55e; border-color:#22c55e; }
        .mp-counter { font-size:10px; color:#6c757d; }
        .mp-counter.warning { color:#b8860b; font-weight:600; }
        .mp-counter.error { color:#dc3545; font-weight:700; }
        .mp-input { width:100%; min-height:54px; max-height:96px; resize:vertical; font-size:11px; padding:6px; border:1px solid #cfd6e4; border-radius:6px; }
        .mp-input:focus { outline:none; border-color:#2c6ed5; box-shadow:0 0 0 2px rgba(44,110,213,.2); }
        .group-badge { font-size:10px; }
        .btn-push-all { background:#ff9900!important; color:#232f3e!important; font-weight:600; }
        .btn-push-all:hover { background:#e88b00!important; color:#fff!important; }
        .toast-container { z-index:1100; }
        .rainbow-loader { display:none; text-align:center; padding:40px; }
        .rainbow-loader .loading-text { margin-top:16px; font-weight:600; color:#2c6ed5; }
        .modal-header-gradient { background:linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color:#fff; }
        .ai-edit-panel { border:1px solid #dee2e6; border-radius:8px; padding:10px; background:#f8fafc; }
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
                        <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display:none;">
                    </div>

                    <div class="table-responsive">
                        <table id="bullet-master-table" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAllRows" title="Select All Rows"></th>
                                    <th>SKU</th>
                                    <th>Product Name</th>
                                    <th>
                                        <div class="th-caption">
                                            <span>Current Bullets (Preview)</span>
                                            <span id="previewCount">(0)</span>
                                        </div>
                                        <input type="text" id="previewSearch" class="th-sub" placeholder="Search preview">
                                    </th>
                                    <th>ACTION</th>

                                    <th title="150 chars marketplaces">
                                        <div class="th-caption">MARKETPLACES (150)</div>
                                        <div class="th-sub"><input type="checkbox" class="group-market" data-group="g150"> Select Group</div>
                                    </th>
                                    <th title="100 chars marketplaces">
                                        <div class="th-caption">MARKETPLACES (100)</div>
                                        <div class="th-sub"><input type="checkbox" class="group-market" data-group="g100"> Select Group</div>
                                    </th>
                                    <th title="80 chars marketplaces">
                                        <div class="th-caption">MARKETPLACES (80)</div>
                                        <div class="th-sub"><input type="checkbox" class="group-market" data-group="g80"> Select Group</div>
                                    </th>
                                    <th title="60 chars marketplaces">
                                        <div class="th-caption">MARKETPLACES (60)</div>
                                        <div class="th-sub"><input type="checkbox" class="group-market" data-group="g60"> Select Group</div>
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
                    <div class="ai-edit-panel mb-3">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <button class="btn btn-primary btn-sm" id="editModalAiGenerateBtn"><i class="fas fa-wand-magic-sparkles"></i> AI Generate</button>
                            <span id="editModalAiLoading" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Generating...</span>
                        </div>
                        <div id="editModalAiFields" class="row g-2"></div>
                    </div>
                    <div id="modalFields" class="row g-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveModalBtn"><i class="fas fa-save"></i> Save Selected</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="aiGenerateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-magic me-2"></i>AI Generate Bullet Points</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="aiSku">
                    <div class="mb-2"><strong>SKU:</strong> <span id="aiSkuLabel"></span></div>
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" class="form-control" id="aiProductName" readonly>
                    </div>
                    <div class="d-flex gap-2 mb-3">
                        <button class="btn btn-primary" id="aiGenerateBtn"><i class="fas fa-wand-magic-sparkles"></i> Generate</button>
                        <span id="aiLoading" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Generating...</span>
                    </div>
                    <div id="aiFields" class="row g-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="aiApplyBtn"><i class="fas fa-check"></i> Apply To Row</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewRowModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>View Bullet Points</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewRowContent"></div>
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
    const LIMITS = { ebay:150, ebay2:150, ebay3:150, walmart:150, macy:150, aliexpress:150, faire:150, bestbuy:150, wayfair:100, shein:80, doba:60 };
    const LABELS = { ebay:'eBay 1', ebay2:'eBay 2', ebay3:'eBay 3', walmart:'Walmart', macy:"Macy's", aliexpress:'AliExpress', faire:'Faire', bestbuy:'BestBuy', wayfair:'Wayfair', shein:'Shein', doba:'DOBA' };
    const MARKETPLACES = Object.keys(LIMITS);
    const GROUPS = {
        g150: ['ebay', 'ebay2', 'ebay3', 'walmart', 'macy', 'aliexpress', 'faire', 'bestbuy'],
        g100: ['wayfair'],
        g80: ['shein'],
        g60: ['doba']
    };
    let tableData = [];
    let editRowModal, viewRowModal, aiGenerateModal;

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
    const trunc = (s, n=50) => (!s ? '-' : (String(s).length > n ? String(s).slice(0,n) + '...' : String(s)));

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
                    tbody.innerHTML = `<tr><td colspan="9" class="text-danger">Render failed: ${esc(e.message || e)}</td></tr>`;
                }
                document.getElementById('previewCount').textContent = `(${tableData.length})`;
            })
            .catch(e => toast('Failed to load data: ' + e.message, false))
            .finally(() => { document.getElementById('rainbow-loader').style.display = 'none'; });
    }

    function mpCell(sku, mp, val, groupKey) {
        const lim = LIMITS[mp];
        const len = (val || '').length;
        const pushed = (val || '').trim() !== '';
        return `
            <div class="mp-cell-wrap mp-group-item" title="${LABELS[mp]} limit ${lim}">
                <div class="mp-top-row">
                    <label class="form-check mb-0 d-flex align-items-center gap-1">
                        <input class="form-check-input mp-check" type="checkbox" data-sku="${esc(sku)}" data-mp="${mp}" data-group="${groupKey}">
                        <small>${esc(LABELS[mp])}</small><span class="mp-status-dot ${pushed ? 'success' : ''}"></span>
                    </label>
                    <span class="mp-counter ${len>lim ? 'error' : (len > lim*0.9 ? 'warning' : '')}" data-counter="${esc(sku)}-${mp}">${len}/${lim}</span>
                </div>
                <textarea class="mp-input" data-sku="${esc(sku)}" data-mp="${mp}" data-limit="${lim}" placeholder="Enter bullet points (${lim} max)">${esc(val || '')}</textarea>
            </div>
        `;
    }

    function groupCell(groupKey, sku, bp, preview) {
        const marketplaces = GROUPS[groupKey] || [];
        return `
            <div class="mp-group-wrap">
                ${marketplaces.map(mp => mpCell(sku, mp, bp[mp] || preview || '', groupKey)).join('')}
            </div>
        `;
    }

    function renderTable(rows) {
        rows = Array.isArray(rows) ? rows : Object.values(rows || {});
        const tbody = document.getElementById('table-body');
        tbody.innerHTML = rows.map(r => {
            const sku = String(r.SKU || '');
            const preview = r.default_bullets || [r.bullet1, r.bullet2, r.bullet3, r.bullet4, r.bullet5].filter(Boolean).join(' ');
            const bp = r.bullet_points || {};
            return `<tr data-sku="${esc(sku)}">
                <td><input type="checkbox" class="form-check-input row-check" data-sku="${esc(sku)}"></td>
                <td>${esc(sku)}</td>
                <td>${esc(r.Parent || sku)}</td>
                <td class="preview-cell" title="${esc(preview || '')}">${esc(trunc(preview, 60))}</td>
                <td>
                    <div class="action-buttons-group">
                        <button class="action-btn view-btn" data-view="${esc(sku)}"><i class="fas fa-eye"></i> View</button>
                        <button class="action-btn edit-btn" data-edit="${esc(sku)}"><i class="fas fa-edit"></i> Edit</button>
                    </div>
                </td>
                <td>${groupCell('g150', sku, bp, preview)}</td>
                <td>${groupCell('g100', sku, bp, preview)}</td>
                <td>${groupCell('g80', sku, bp, preview)}</td>
                <td>${groupCell('g60', sku, bp, preview)}</td>
            </tr>`;
        }).join('');

        bindRowEvents();
    }

    function bindRowEvents() {
        document.querySelectorAll('.mp-input').forEach(t => {
            t.addEventListener('input', function() {
                const lim = Number(this.dataset.limit || 150);
                if (this.value.length > lim) this.value = this.value.slice(0, lim);
                const c = document.querySelector(`[data-counter="${cssEsc(this.dataset.sku + '-' + this.dataset.mp)}"]`);
                if (c) {
                    const len = this.value.length;
                    c.textContent = `${len}/${lim}`;
                    c.classList.remove('warning', 'error');
                    if (len > lim) c.classList.add('error');
                    else if (len > lim*0.9) c.classList.add('warning');
                }
                const dot = this.closest('.mp-cell-wrap')?.querySelector('.mp-status-dot');
                if (dot) dot.classList.toggle('success', this.value.trim().length > 0);
            });
        });

        document.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => openEditModal(b.dataset.edit)));
        document.querySelectorAll('[data-view]').forEach(b => b.addEventListener('click', () => openViewModal(b.dataset.view)));
    }

    function getRowUpdates(sku, onlyChecked=true) {
        const tr = document.querySelector(`tr[data-sku="${cssEsc(sku)}"]`);
        if (!tr) return [];
        const updates = [];
        tr.querySelectorAll('.mp-input').forEach(inp => {
            const mp = inp.dataset.mp;
            const chk = tr.querySelector(`.mp-check[data-mp="${mp}"]`);
            if (!onlyChecked || (chk && chk.checked)) {
                const val = inp.value.trim();
                if (val) updates.push({ marketplace: mp, bullet_points: val });
            }
        });
        return updates;
    }

    function openViewModal(sku) {
        const row = bySku.get(String(sku));
        if (!row) return;
        const bp = row.bullet_points || {};
        document.getElementById('viewRowContent').innerHTML = `
            <div><strong>SKU:</strong> ${esc(sku)}</div>
            <hr>
            ${MARKETPLACES.map(mp => `<div class="mb-2"><strong>${esc(LABELS[mp])} (${LIMITS[mp]}):</strong><div class="border rounded p-2 mt-1" style="white-space:pre-wrap;">${esc(bp[mp] || row.default_bullets || '')}</div></div>`).join('')}
        `;
        if (viewRowModal) viewRowModal.show();
    }

    function openEditModal(sku) {
        const row = bySku.get(String(sku));
        if (!row) return;
        const bp = row.bullet_points || {};
        document.getElementById('modalSku').value = sku;
        document.getElementById('modalSkuLabel').textContent = sku;
        document.getElementById('modalProductLabel').textContent = row.Parent || sku;
        renderEditModalAiFields(row);
        document.getElementById('modalFields').innerHTML = MARKETPLACES.map(mp => {
            const val = bp[mp] || row.default_bullets || '';
            const lim = LIMITS[mp];
            return `<div class="col-md-6"><div class="border rounded p-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-check mb-0"><input type="checkbox" class="form-check-input modal-mp-check" data-mp="${mp}" checked> <span>${esc(LABELS[mp])} <span class="badge bg-secondary">${lim}</span></span></label>
                    <small class="mp-counter" data-modal-counter="${mp}">${(val || '').length}/${lim}</small>
                </div>
                <textarea class="form-control modal-mp-input" data-mp="${mp}" data-limit="${lim}" rows="3">${esc(val)}</textarea>
            </div></div>`;
        }).join('');

        document.querySelectorAll('.modal-mp-input').forEach(inp => inp.addEventListener('input', function() {
            const lim = Number(this.dataset.limit || 150);
            if (this.value.length > lim) this.value = this.value.slice(0, lim);
            const c = document.querySelector(`[data-modal-counter="${this.dataset.mp}"]`);
            if (c) c.textContent = `${this.value.length}/${lim}`;
        }));

        if (editRowModal) editRowModal.show();
    }

    function renderEditModalAiFields(row) {
        const current = [row.bullet1, row.bullet2, row.bullet3, row.bullet4, row.bullet5];
        document.getElementById('editModalAiFields').innerHTML = [1,2,3,4,5].map(i => `
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label mb-1">Bullet ${i} <span id="editAiCount${i}" class="text-muted">0/200</span></label>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Rating">
                        <button type="button" class="btn btn-outline-success edit-ai-rate" data-idx="${i}" data-rating="good"><i class="fas fa-thumbs-up"></i></button>
                        <button type="button" class="btn btn-outline-danger edit-ai-rate" data-idx="${i}" data-rating="bad"><i class="fas fa-thumbs-down"></i></button>
                    </div>
                </div>
                <textarea class="form-control edit-ai-bullet" data-idx="${i}" rows="2" maxlength="200">${esc(current[i-1] || '')}</textarea>
            </div>
        `).join('');
        bindEditAICountersAndRatings();
    }

    function bindEditAICountersAndRatings() {
        document.querySelectorAll('.edit-ai-bullet').forEach(t => {
            const idx = t.dataset.idx;
            const update = () => {
                const len = t.value.length;
                const el = document.getElementById('editAiCount' + idx);
                if (el) el.textContent = `${len}/200`;
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

    function openAIModal(sku) {
        const row = bySku.get(String(sku));
        if (!row) return;
        document.getElementById('aiSku').value = sku;
        document.getElementById('aiSkuLabel').textContent = sku;
        document.getElementById('aiProductName').value = row.Parent || sku;
        const current = [row.bullet1, row.bullet2, row.bullet3, row.bullet4, row.bullet5];
        document.getElementById('aiFields').innerHTML = [1,2,3,4,5].map(i => `
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <label class="form-label mb-1">Bullet ${i} <span id="aiCount${i}" class="text-muted">0/200</span></label>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Rating">
                        <button type="button" class="btn btn-outline-success ai-rate" data-idx="${i}" data-rating="good"><i class="fas fa-thumbs-up"></i></button>
                        <button type="button" class="btn btn-outline-danger ai-rate" data-idx="${i}" data-rating="bad"><i class="fas fa-thumbs-down"></i></button>
                    </div>
                </div>
                <textarea class="form-control ai-bullet" data-idx="${i}" rows="2" maxlength="200">${esc(current[i-1] || '')}</textarea>
            </div>
        `).join('');
        bindAICounters();
        bindAIRatings();
        if (aiGenerateModal) aiGenerateModal.show();
    }

    function bindAICounters() {
        document.querySelectorAll('.ai-bullet').forEach(t => {
            const idx = t.dataset.idx;
            const update = () => {
                const len = t.value.length;
                const el = document.getElementById('aiCount' + idx);
                if (el) el.textContent = `${len}/200`;
            };
            t.addEventListener('input', update);
            update();
        });
    }

    function bindAIRatings() {
        document.querySelectorAll('.ai-rate').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = this.dataset.idx;
                const rating = this.dataset.rating;
                const groupButtons = document.querySelectorAll(`.ai-rate[data-idx="${idx}"]`);
                groupButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const field = document.querySelector(`.ai-bullet[data-idx="${idx}"]`);
                if (field) field.dataset.rating = rating;
            });
        });
    }

    function collectBulkItems(mode) {
        const items = [];
        if (mode === 'selected') {
            document.querySelectorAll('.row-check:checked').forEach(r => {
                const sku = r.dataset.sku;
                const updates = getRowUpdates(sku, true);
                if (updates.length) items.push({ sku, updates });
            });
        } else {
            document.querySelectorAll('.row-check').forEach(r => {
                const sku = r.dataset.sku;
                const updates = getRowUpdates(sku, false);
                if (updates.length) items.push({ sku, updates });
            });
        }
        return items;
    }

    function bulkPush(mode) {
        const items = collectBulkItems(mode);
        if (!items.length) {
            toast(mode === 'selected' ? 'Select row(s) and marketplace checkbox(es) first.' : 'Select at least one row first.', false);
            return;
        }
        const btn = mode === 'selected' ? document.getElementById('pushSelectedBtn') : document.getElementById('pushAllBtn');
        const old = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Pushing...';

        fetch('/bullet-point-master/update-bulk', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ items })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) toast(res.message || 'Updated successfully');
            else toast(res.message || 'Completed with failures', false);
            loadData();
        })
        .catch(e => toast('Push failed: ' + e.message, false))
        .finally(() => { btn.disabled = false; btn.innerHTML = old; });
    }

    function exportData() {
        const rows = Array.from(document.querySelectorAll('#table-body tr')).map(tr => {
            const sku = tr.getAttribute('data-sku');
            const row = bySku.get(sku) || {};
            const out = { SKU: sku, ProductName: row.Parent || sku, Preview: row.default_bullets || '' };
            MARKETPLACES.forEach(mp => {
                const inp = tr.querySelector(`.mp-input[data-mp="${mp}"]`);
                out[LABELS[mp]] = inp ? inp.value : '';
            });
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
                    const tr = document.querySelector(`tr[data-sku="${cssEsc(sku)}"]`);
                    if (!tr) return;
                    MARKETPLACES.forEach(mp => {
                        const key = LABELS[mp];
                        const v = row[key];
                        if (typeof v === 'string') {
                            const inp = tr.querySelector(`.mp-input[data-mp="${mp}"]`);
                            if (inp) {
                                const lim = Number(inp.dataset.limit || 150);
                                inp.value = v.slice(0, lim);
                                inp.dispatchEvent(new Event('input'));
                            }
                        }
                    });
                });
                toast('Import loaded into table. Click push to save.');
            } catch (err) {
                toast('Import failed: ' + err.message, false);
            }
        };
        reader.readAsArrayBuffer(file);
    }

    // events
    document.getElementById('previewSearch').addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        const rows = !q ? tableData : tableData.filter(r => (String(r.default_bullets || '').toLowerCase().includes(q) || String(r.SKU || '').toLowerCase().includes(q) || String(r.Parent || '').toLowerCase().includes(q)));
        renderTable(rows);
    });

    document.getElementById('selectAllRows').addEventListener('change', function() {
        document.querySelectorAll('.row-check').forEach(ch => ch.checked = this.checked);
    });

    document.querySelectorAll('.group-market').forEach(h => {
        h.addEventListener('change', function() {
            const groupKey = this.dataset.group;
            document.querySelectorAll(`.mp-check[data-group="${groupKey}"]`).forEach(ch => ch.checked = this.checked);
        });
    });

    document.getElementById('saveModalBtn').addEventListener('click', function() {
        const sku = document.getElementById('modalSku').value;
        const aiBullets = Array.from(document.querySelectorAll('.edit-ai-bullet'))
            .map(t => t.value.trim())
            .filter(Boolean);
        if (aiBullets.length) {
            const combined = aiBullets.join(' ');
            document.querySelectorAll('.modal-mp-input').forEach(inp => {
                const lim = Number(inp.dataset.limit || 150);
                inp.value = combined.slice(0, lim);
                inp.dispatchEvent(new Event('input'));
            });
        }
        const updates = [];
        document.querySelectorAll('.modal-mp-input').forEach(inp => {
            const mp = inp.dataset.mp;
            const chk = document.querySelector(`.modal-mp-check[data-mp="${mp}"]`);
            if (chk && chk.checked) {
                const text = inp.value.trim();
                if (text) updates.push({ marketplace: mp, bullet_points: text });
            }
        });
        if (!updates.length) { toast('Select at least one marketplace with content.', false); return; }

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
                t.value = (bullets[i] || '').slice(0, 200);
                t.dispatchEvent(new Event('input'));
            });
            toast('AI bullets generated');
        }).catch(e => toast('AI generation failed: ' + e.message, false))
        .finally(() => {
            btn.disabled = false;
            document.getElementById('editModalAiLoading').style.display = 'none';
        });
    });

    document.getElementById('aiGenerateBtn').addEventListener('click', function() {
        const sku = document.getElementById('aiSku').value;
        const productName = document.getElementById('aiProductName').value;
        const btn = this;
        btn.disabled = true;
        document.getElementById('aiLoading').style.display = 'inline';
        fetch('/bullet-point-master/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ product_id: sku, sku, product_name: productName })
        }).then(r => r.json()).then(res => {
            if (!res.success) {
                toast(res.message || 'AI generation failed', false);
                return;
            }
            const bullets = res.bullets || [];
            document.querySelectorAll('.ai-bullet').forEach((t, i) => {
                t.value = (bullets[i] || '').slice(0, 200);
                t.dispatchEvent(new Event('input'));
            });
        }).catch(e => toast('AI generation failed: ' + e.message, false))
        .finally(() => { btn.disabled = false; document.getElementById('aiLoading').style.display = 'none'; });
    });

    document.getElementById('aiApplyBtn').addEventListener('click', function() {
        const sku = document.getElementById('aiSku').value;
        const tr = document.querySelector(`tr[data-sku="${cssEsc(sku)}"]`);
        if (!tr) return;
        const bullets = Array.from(document.querySelectorAll('.ai-bullet')).map(t => t.value.trim()).filter(Boolean);
        const combined = bullets.join(' ');
        tr.querySelectorAll('.mp-input').forEach(inp => {
            const lim = Number(inp.dataset.limit || 150);
            inp.value = combined.slice(0, lim);
            inp.dispatchEvent(new Event('input'));
        });
        toast('AI bullets applied to row. Select marketplaces and push.');
        if (aiGenerateModal) aiGenerateModal.hide();
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
            aiGenerateModal = new bootstrap.Modal(document.getElementById('aiGenerateModal'));
        } else {
            console.warn('Bootstrap JS not available; modals/toasts will be degraded.');
        }
        loadData();
    });
});
</script>
@endsection
