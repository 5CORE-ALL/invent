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
        .im-slot { border:1px dashed #cbd5e1; border-radius:8px; padding:6px; margin-bottom:8px; background:#f8fafc; cursor:grab; }
        .im-slot-dragging { opacity:.6; }
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
                    <div class="mb-2">
                        <label class="form-label small">Add images (up to 12)</label>
                        <input type="file" class="form-control form-control-sm" id="modalFileInput" accept="image/*" multiple>
                    </div>
                    <div class="mb-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchAmazonBtn"><i class="fab fa-amazon"></i> Fetch Amazon images</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchEbay1Btn"><i class="fab fa-ebay"></i> Fetch eBay1</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchEbay2Btn">eBay2</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="fetchEbay3Btn">eBay3</button>
                    </div>
                    <div class="fw-semibold small mb-1">Order (drag to reorder)</div>
                    <div id="imSlots"></div>
                    <div class="mt-3">
                        <div class="fw-semibold small mb-1">Push to marketplaces</div>
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

    function openEditModal(sku) {
        const row = bySku.get(String(sku));
        if (!row) return;
        document.getElementById('modalSku').value = sku;
        document.getElementById('modalSkuLabel').textContent = sku;
        document.getElementById('modalProductLabel').textContent = row.Parent || sku;
        modalUrls = pmImageUrls(row);
        renderSlots();
        document.getElementById('modalMarketplaceChecks').innerHTML = MARKETPLACES.map(mp => `
            <div class="col-6 col-md-4 col-lg-3">
                <label class="form-check small"><input type="checkbox" class="form-check-input im-mp-chk" value="${mp}"> ${esc(LABELS[mp])}</label>
            </div>`).join('');
        if (editModal) editModal.show();
    }

    function renderSlots() {
        const el = document.getElementById('imSlots');
        el.innerHTML = modalUrls.map((url, idx) => `
            <div class="im-slot" draggable="true" data-idx="${idx}">
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <img src="${esc(url)}" alt="" style="max-height:56px;max-width:120px;object-fit:contain;">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary im-up" data-i="${idx}">↑</button>
                        <button type="button" class="btn btn-outline-secondary im-down" data-i="${idx}">↓</button>
                        <button type="button" class="btn btn-outline-danger im-del" data-i="${idx}"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div class="small text-truncate mt-1" title="${esc(url)}">${esc(url)}</div>
            </div>`).join('');
        el.querySelectorAll('.im-del').forEach(b => b.addEventListener('click', () => { modalUrls.splice(+b.dataset.i,1); renderSlots(); }));
        el.querySelectorAll('.im-up').forEach(b => b.addEventListener('click', () => { const i=+b.dataset.i; if(i>0){ const t=modalUrls[i-1]; modalUrls[i-1]=modalUrls[i]; modalUrls[i]=t; renderSlots(); }}));
        el.querySelectorAll('.im-down').forEach(b => b.addEventListener('click', () => { const i=+b.dataset.i; if(i<modalUrls.length-1){ const t=modalUrls[i+1]; modalUrls[i+1]=modalUrls[i]; modalUrls[i]=t; renderSlots(); }}));
        el.querySelectorAll('.im-slot').forEach(slot => {
            slot.addEventListener('dragstart', e => { e.dataTransfer.setData('text/plain', slot.dataset.idx); slot.classList.add('im-slot-dragging'); });
            slot.addEventListener('dragend', () => slot.classList.remove('im-slot-dragging'));
            slot.addEventListener('dragover', e => e.preventDefault());
            slot.addEventListener('drop', e => {
                e.preventDefault();
                const from = +e.dataTransfer.getData('text/plain');
                const to = +slot.dataset.idx;
                if (from === to) return;
                const moved = modalUrls.splice(from,1)[0];
                modalUrls.splice(to,0,moved);
                renderSlots();
            });
        });
    }

    document.getElementById('modalFileInput')?.addEventListener('change', async function() {
        const sku = document.getElementById('modalSku').value;
        if (!this.files?.length) return;
        const fd = new FormData();
        fd.append('sku', sku);
        for (const f of this.files) fd.append('files[]', f);
        const r = await fetch('/image-master/upload', { method:'POST', headers:{'X-CSRF-TOKEN':csrfToken,'Accept':'application/json'}, body: fd });
        const j = await r.json();
        if (j.success && j.urls) { modalUrls = modalUrls.concat(j.urls).slice(0,12); renderSlots(); toast('Uploaded'); }
        else toast(j.message || 'Upload failed', false);
        this.value = '';
    });

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
        const r = await fetch('/image-master/save-pm', {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken,'Accept':'application/json'},
            body: JSON.stringify({ sku, images: modalUrls }),
        });
        const j = await r.json();
        if (j.success) { toast('Saved to Product Master'); loadData(); }
        else toast(j.message||'Save failed', false);
    });

    document.getElementById('pushModalBtn')?.addEventListener('click', async () => {
        const sku = document.getElementById('modalSku').value;
        const checks = Array.from(document.querySelectorAll('.im-mp-chk:checked')).map(c => c.value);
        if (!checks.length) { toast('Select marketplaces', false); return; }
        if (!confirmEbay3Push(checks)) return;
        setPushProgress(true, `Pushing images to ${checks.length} marketplaces... This may take 1-2 minutes`, '');
        const progress = [];
        let okCount = 0;
        let failCount = 0;
        let metricsFailCount = 0;
        try {
            for (let i = 0; i < checks.length; i++) {
                const mp = checks[i];
                setPushProgress(true, `Pushing images to ${checks.length} marketplaces... This may take 1-2 minutes`, progress.join('<br>'));
                const controller = new AbortController();
                const t = setTimeout(() => controller.abort(), 120000);
                try {
                    const r = await fetch('/image-master/push', {
                        method:'POST',
                        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken,'Accept':'application/json'},
                        body: JSON.stringify({ sku, updates: [{ marketplace: mp, images: modalUrls }] }),
                        signal: controller.signal,
                    });
                    const j = await r.json();
                    const row = (j.results && j.results[mp]) ? j.results[mp] : null;
                    const rowOk = !!(row && row.success);
                    const rowMetrics = !!(row && row.metrics_saved);
                    if (rowOk) okCount++; else failCount++;
                    if (row && rowOk && !rowMetrics) metricsFailCount++;
                    progress.push(`${i + 1}/${checks.length} ${LABELS[mp]}: ${rowOk ? 'OK' : 'Failed'}${row && row.message ? ` - ${esc(row.message)}` : ''}`);
                } catch (e) {
                    failCount++;
                    const timeoutText = (e && e.name === 'AbortError') ? 'Request timed out after 120s' : (e.message || 'Request failed');
                    progress.push(`${i + 1}/${checks.length} ${LABELS[mp]}: Failed - ${esc(timeoutText)}`);
                } finally {
                    clearTimeout(t);
                }
            }
            setPushProgress(true, `Push finished: ${okCount} success, ${failCount} failed${metricsFailCount ? `, ${metricsFailCount} metrics save failed` : ''}`, progress.join('<br>'));
            if (failCount === 0) {
                toast(`Pushed to ${okCount} marketplace(s).${metricsFailCount ? ` ${metricsFailCount} metrics save failed.` : ''}`, true);
                loadData();
                if (editModal) editModal.hide();
            } else {
                toast(`Push completed with failures (${failCount}). See progress details.`, false);
                loadData();
            }
        } finally {
            setTimeout(() => setPushProgress(false, '', ''), 5000);
        }
    });

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
            body: JSON.stringify({ sku, updates: [{ marketplace: mp, images: urls }] }),
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
    loadData();
});
</script>
@endsection

