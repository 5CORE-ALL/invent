@extends('layouts.vertical', ['title' => 'Description Master 2.0', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .card.dm2-card { border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 12px rgba(44,110,213,.06); }
        .card.dm2-card .card-body { padding: 1.25rem 1.5rem; }
        .dm2-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-bottom: 1rem; }
        .dm2-section-title { font-size: 0.95rem; font-weight: 600; color: #1e293b; margin-bottom: .5rem; display: flex; align-items: center; gap: .35rem; }
        .dm2-features-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width: 768px) { .dm2-features-grid { grid-template-columns: 1fr; } }
        .dm2-feature-box { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; background: #f8fafc; }
        .dm2-spec-table input { font-size: 12px; }
        .dm2-img-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 8px; }
        #dm2Progress .badge { font-weight: 500; }
        .modal-header-gradient { background: linear-gradient(135deg,#6B73FF 0%,#000DFF 100%); color: #fff; }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Description Master 2.0',
        'sub_title' => 'Structured Shopify + eBay descriptions — one push, full HTML',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card dm2-card">
                <div class="card-body">
                    <div class="dm2-toolbar">
                        <label class="small text-muted mb-0">SKU</label>
                        <input type="text" id="dm2Sku" class="form-control form-control-sm" style="max-width:220px;" placeholder="e.g. 138 RU" autocomplete="off">
                        <button type="button" class="btn btn-primary btn-sm" id="dm2LoadBtn"><i class="fas fa-download"></i> Load</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="dm2FetchAmazonBtn" title="A+ Content API + listings fallback"><i class="fab fa-amazon"></i> Fetch from Amazon</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="dm2FetchEbayBtn" title="Trading API GetItem"><i class="fab fa-ebay"></i> Fetch from eBay</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="dm2SaveDraftBtn"><i class="fas fa-save"></i> Save draft</button>
                    </div>
                    <div id="dm2FetchStatus" class="small text-muted mb-2" style="display:none;"><i class="fas fa-spinner fa-spin"></i> <span id="dm2FetchStatusText">Fetching…</span></div>

                    <div class="dm2-section-title">📝 Bullet Points (5 lines)</div>
                    <textarea id="dm2Bullets" class="form-control font-monospace mb-3" rows="6" placeholder="One bullet per line. Optional format: TITLE - detail text"></textarea>

                    <div class="dm2-section-title">🖼️ Images (up to 12 URLs)</div>
                    <div class="dm2-img-grid mb-3" id="dm2ImgGrid"></div>

                    <div class="dm2-section-title">📄 Product Description</div>
                    <textarea id="dm2Description" class="form-control mb-3" rows="5" placeholder="Main product description"></textarea>

                    <div class="dm2-section-title">⭐ Features (up to 4 — shown as a list on Shopify/eBay)</div>
                    <div class="dm2-features-grid mb-3" id="dm2FeaturesGrid"></div>

                    <div class="dm2-section-title">📊 Specification (key–value)</div>
                    <div class="table-responsive mb-2">
                        <table class="table table-sm dm2-spec-table">
                            <thead><tr><th style="width:42%">Spec</th><th>Value</th></tr></thead>
                            <tbody id="dm2SpecBody"></tbody>
                        </table>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted mb-0">Specification table heading</label>
                        <input type="text" id="dm2SpecHeading" class="form-control form-control-sm" placeholder="Leave blank to use “{Product name} Specification”">
                    </div>

                    <div class="dm2-section-title">📦 Package Includes</div>
                    <textarea id="dm2Package" class="form-control mb-3" rows="4" placeholder="One line per item (optional • prefix)"></textarea>

                    <div class="dm2-section-title">🏢 About Brand</div>
                    <textarea id="dm2Brand" class="form-control mb-3" rows="4"></textarea>

                    <div class="dm2-section-title mb-2">🛒 Marketplaces</div>
                    <div class="form-check form-check-inline mb-3">
                        <input class="form-check-input" type="checkbox" id="dm2PushShopify" checked>
                        <label class="form-check-label" for="dm2PushShopify">Shopify Main</label>
                    </div>
                    <div class="form-check form-check-inline mb-3">
                        <input class="form-check-input" type="checkbox" id="dm2PushEbay" checked>
                        <label class="form-check-label" for="dm2PushEbay">eBay</label>
                    </div>

                    <button type="button" class="btn btn-success" id="dm2PushBtn"><i class="fas fa-cloud-upload-alt"></i> Push to Selected Marketplaces</button>

                    <div id="dm2Progress" class="mt-3 small" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const imgGrid = document.getElementById('dm2ImgGrid');
    for (let i = 0; i < 12; i++) {
        const w = document.createElement('div');
        w.innerHTML = '<label class="small text-muted mb-0">URL ' + (i + 1) + '</label>' +
            '<input type="url" class="form-control form-control-sm dm2-img" data-i="' + i + '" placeholder="https://...">';
        imgGrid.appendChild(w);
    }
    const featEl = document.getElementById('dm2FeaturesGrid');
    for (let i = 0; i < 4; i++) {
        const box = document.createElement('div');
        box.className = 'dm2-feature-box';
        box.innerHTML = '<label class="small mb-1">Title ' + (i + 1) + '</label>' +
            '<input type="text" class="form-control form-control-sm mb-2 dm2-feat-title" data-i="' + i + '">' +
            '<label class="small mb-1">Text</label>' +
            '<textarea class="form-control form-control-sm dm2-feat-body" rows="3" data-i="' + i + '"></textarea>';
        featEl.appendChild(box);
    }
    const specBody = document.getElementById('dm2SpecBody');
    for (let i = 0; i < 10; i++) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" class="form-control form-control-sm dm2-spec-k" data-i="' + i + '"></td>' +
            '<td><input type="text" class="form-control form-control-sm dm2-spec-v" data-i="' + i + '"></td>';
        specBody.appendChild(tr);
    }

    function toast(msg, ok = true) {
        if (window.bootstrap && window.bootstrap.Toast) {
            const el = document.createElement('div');
            el.className = 'toast align-items-center text-bg-' + (ok ? 'success' : 'danger') + ' border-0 position-fixed bottom-0 end-0 m-3';
            el.setAttribute('role', 'alert');
            el.innerHTML = '<div class="d-flex"><div class="toast-body">' + String(msg).replace(/</g, '&lt;') + '</div></div>';
            document.body.appendChild(el);
            const t = new bootstrap.Toast(el, { delay: 4200 });
            t.show();
            el.addEventListener('hidden.bs.toast', () => el.remove());
        } else alert(msg);
    }

    function collectPayload() {
        const images = [];
        document.querySelectorAll('.dm2-img').forEach((inp) => {
            const v = (inp.value || '').trim();
            if (v) images.push(v);
        });
        const features = [];
        for (let i = 0; i < 4; i++) {
            features.push({
                title: (document.querySelector('.dm2-feat-title[data-i="' + i + '"]')?.value || '').trim(),
                body: (document.querySelector('.dm2-feat-body[data-i="' + i + '"]')?.value || '').trim(),
            });
        }
        const specs = [];
        for (let i = 0; i < 10; i++) {
            const k = (document.querySelector('.dm2-spec-k[data-i="' + i + '"]')?.value || '').trim();
            const v = (document.querySelector('.dm2-spec-v[data-i="' + i + '"]')?.value || '').trim();
            if (k !== '' || v !== '') specs.push({ key: k, value: v });
        }
        return {
            sku: (document.getElementById('dm2Sku').value || '').trim(),
            description_v2_bullets: document.getElementById('dm2Bullets').value || '',
            description_v2_description: document.getElementById('dm2Description').value || '',
            description_v2_images: images,
            description_v2_features: features,
            description_v2_specifications: specs,
            description_v2_package: document.getElementById('dm2Package').value || '',
            description_v2_brand: document.getElementById('dm2Brand').value || '',
            spec_table_heading: (document.getElementById('dm2SpecHeading').value || '').trim(),
        };
    }

    function fillForm(d) {
        document.getElementById('dm2Bullets').value = d.description_v2_bullets || '';
        document.getElementById('dm2Description').value = d.description_v2_description || '';
        document.getElementById('dm2Package').value = d.description_v2_package || '';
        document.getElementById('dm2Brand').value = d.description_v2_brand || '';
        const imgs = d.description_v2_images || [];
        document.querySelectorAll('.dm2-img').forEach((inp) => {
            const i = parseInt(inp.getAttribute('data-i'), 10);
            inp.value = imgs[i] || '';
        });
        const feats = d.description_v2_features || [];
        for (let i = 0; i < 4; i++) {
            const f = feats[i] || {};
            const ti = document.querySelector('.dm2-feat-title[data-i="' + i + '"]');
            const bi = document.querySelector('.dm2-feat-body[data-i="' + i + '"]');
            if (ti) ti.value = f.title || '';
            if (bi) bi.value = f.body || '';
        }
        const specs = d.description_v2_specifications || [];
        for (let i = 0; i < 10; i++) {
            const s = specs[i] || {};
            const k = document.querySelector('.dm2-spec-k[data-i="' + i + '"]');
            const v = document.querySelector('.dm2-spec-v[data-i="' + i + '"]');
            if (k) k.value = s.key || '';
            if (v) v.value = s.value || '';
        }
    }

    const dm2FetchStatus = document.getElementById('dm2FetchStatus');
    const dm2FetchStatusText = document.getElementById('dm2FetchStatusText');

    function setFetchLoading(on, text) {
        if (on) {
            dm2FetchStatus.style.display = 'block';
            dm2FetchStatusText.textContent = text || 'Fetching…';
            document.getElementById('dm2FetchAmazonBtn').disabled = true;
            document.getElementById('dm2FetchEbayBtn').disabled = true;
        } else {
            dm2FetchStatus.style.display = 'none';
            document.getElementById('dm2FetchAmazonBtn').disabled = false;
            document.getElementById('dm2FetchEbayBtn').disabled = false;
        }
    }

    async function postFetch(url, label) {
        const sku = (document.getElementById('dm2Sku').value || '').trim();
        if (!sku) { toast('Enter a SKU', false); return; }
        setFetchLoading(true, label + '…');
        try {
            const r = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: JSON.stringify({ sku }),
            });
            const j = await r.json().catch(() => ({}));
            if (!r.ok) {
                toast(j.message || ('HTTP ' + r.status), false);
                return;
            }
            if (j.data) {
                fillForm(j.data);
            }
            if (j.success) {
                toast(j.message || 'Fetched');
            } else {
                toast(j.message || 'Fetch returned no usable content', false);
            }
        } catch (e) {
            toast(e.message, false);
        } finally {
            setFetchLoading(false);
        }
    }

    document.getElementById('dm2LoadBtn').addEventListener('click', async () => {
        const sku = (document.getElementById('dm2Sku').value || '').trim();
        if (!sku) { toast('Enter a SKU', false); return; }
        try {
            const r = await fetch('/product-description-2/data?sku=' + encodeURIComponent(sku), { headers: { Accept: 'application/json' } });
            const j = await r.json();
            if (!j.success) { toast(j.message || 'Load failed', false); return; }
            fillForm(j);
            toast('Loaded data for ' + sku);
        } catch (e) { toast(e.message, false); }
    });

    document.getElementById('dm2FetchAmazonBtn').addEventListener('click', () => postFetch('/product-description-2/fetch/amazon', 'Loading A+ content from Amazon'));
    document.getElementById('dm2FetchEbayBtn').addEventListener('click', () => postFetch('/product-description-2/fetch/ebay', 'Loading description from eBay'));

    document.getElementById('dm2SaveDraftBtn').addEventListener('click', async () => {
        const p = collectPayload();
        if (!p.sku) { toast('Enter a SKU', false); return; }
        try {
            const r = await fetch('/product-description-2/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: JSON.stringify(p),
            });
            const j = await r.json();
            toast(j.message || (j.success ? 'Saved' : 'Save failed'), !!j.success);
        } catch (e) { toast(e.message, false); }
    });

    document.getElementById('dm2PushBtn').addEventListener('click', async () => {
        const p = collectPayload();
        if (!p.sku) { toast('Enter a SKU', false); return; }
        const pushShopify = document.getElementById('dm2PushShopify').checked;
        const pushEbay = document.getElementById('dm2PushEbay').checked;
        if (!pushShopify && !pushEbay) { toast('Select at least one marketplace', false); return; }
        const btn = document.getElementById('dm2PushBtn');
        const prog = document.getElementById('dm2Progress');
        prog.style.display = 'block';
        prog.innerHTML = '<div class="text-muted mb-1"><i class="fas fa-spinner fa-spin"></i> Pushing…</div>';
        btn.disabled = true;
        try {
            const body = {
                ...p,
                push_shopify_main: pushShopify,
                push_ebay: pushEbay,
            };
            const r = await fetch('/product-description-2/push', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: JSON.stringify(body),
            });
            const j = await r.json();
            let html = '';
            if (j.results) {
                if (j.results.shopify_main) {
                    const x = j.results.shopify_main;
                    const ok = x.success ? 'bg-success' : 'bg-danger';
                    html += '<div class="mb-1"><span class="badge ' + ok + '">Shopify Main</span> ' +
                        (x.message || '') + (x.retried ? ' <span class="text-muted">(retries: ' + (x.attempts || '') + ')</span>' : '') + '</div>';
                }
                if (j.results.ebay) {
                    const x = j.results.ebay;
                    const ok = x.success ? 'bg-success' : 'bg-danger';
                    html += '<div class="mb-1"><span class="badge ' + ok + '">eBay</span> ' +
                        (x.message || '') + (x.retried ? ' <span class="text-muted">(retries: ' + (x.attempts || '') + ')</span>' : '') + '</div>';
                }
            }
            prog.innerHTML = html || (j.message || '');
            toast(j.message || (j.success ? 'Done' : 'Push failed'), !!j.success);
        } catch (e) {
            prog.innerHTML = '';
            toast(e.message, false);
        } finally {
            btn.disabled = false;
        }
    });
});
</script>
@endsection
