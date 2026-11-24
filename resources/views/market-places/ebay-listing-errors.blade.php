@extends('layouts.vertical', ['title' => 'Ebay Listing Errors'])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #ele { --ink:#16132b; --muted:#6d6888; --line:#ece7fb; font-family: "Segoe UI", system-ui, sans-serif; color: var(--ink); }
        #ele .ele-hero {
            display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap;
            padding:18px 20px; border-radius:18px; color:#fff; margin-bottom:14px;
            background: linear-gradient(120deg, #312e81 0%, #7c3aed 55%, #db2777 100%);
            box-shadow: 0 12px 30px rgba(124,58,237,.28);
        }
        #ele .ele-hero h1 { font-size:22px; font-weight:800; margin:0; letter-spacing:-.3px; }
        #ele .ele-hero p { margin:4px 0 0; font-size:13px; opacity:.9; }
        #ele .ele-tabs { display:flex; gap:8px; }
        #ele .ele-tab {
            border:0; border-radius:999px; padding:8px 16px; font-weight:700; font-size:13px; cursor:pointer;
            background: rgba(255,255,255,.18); color:#fff; transition: transform .15s, background .15s;
        }
        #ele .ele-tab.active { background:#fff; color:#6d28d9; transform: translateY(-1px); }
        #ele .ele-bar { display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
        #ele .ele-chip { background:#fff; border:1px solid var(--line); border-radius:12px; padding:8px 12px; min-width:110px; }
        #ele .ele-chip b { display:block; font-size:18px; }
        #ele .ele-chip span { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; }
        #ele .ele-search { margin-left:auto; border:1px solid var(--line); border-radius:999px; padding:8px 14px; min-width:240px; }
        #ele .tabulator { border:1px solid var(--line); border-radius:14px; overflow:hidden; background:#fff; }
        #ele .tabulator .tabulator-header { background:#f7f5ff; font-size:12px; }
        #ele .ele-err { color:#9f1239; font-size:12px; }
        #ele .ele-fix { border:0; border-radius:999px; background:#6d28d9; color:#fff; font-size:12px; font-weight:700; padding:4px 10px; }
        #ele .ele-status { font-size:11px; font-weight:800; letter-spacing:.04em; padding:2px 8px; border-radius:999px; background:#fee2e2; color:#9f1239; }
        #ele-overlay { position:fixed; inset:0; z-index:2000; display:none; align-items:center; justify-content:center; background:rgba(22,19,43,.45); backdrop-filter:blur(3px); }
        #ele-overlay.show { display:flex; animation: eleIn .2s ease; }
        @keyframes eleIn { from { opacity:0 } to { opacity:1 } }
        #ele-overlay .card { width:min(440px,92vw); background:#fff; border-radius:18px; padding:22px; text-align:center; box-shadow:0 20px 50px rgba(0,0,0,.25); }
        #ele-overlay .spin { width:42px; height:42px; margin:0 auto 10px; border-radius:50%; border:4px solid #ede9fe; border-top-color:#7c3aed; animation: eleSpin .7s linear infinite; }
        @keyframes eleSpin { to { transform: rotate(360deg) } }
        #ele-fix { position:fixed; inset:0; z-index:1900; display:none; align-items:center; justify-content:center; background:rgba(22,19,43,.45); }
        #ele-fix.show { display:flex; }
        #ele-fix .card { width:min(520px,94vw); background:#fff; border-radius:18px; padding:20px; }
        #ele-fix h3 { font-size:16px; font-weight:800; margin:0 0 4px; }
        #ele-fix .err { background:#fff1f2; color:#9f1239; border-radius:10px; padding:8px 10px; font-size:13px; margin:10px 0; }
        #ele-fix label { font-size:12px; font-weight:700; color:var(--muted); }
        #ele-fix input { width:100%; border:1px solid var(--line); border-radius:10px; padding:8px 10px; }
        #ele-fix .row2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        #ele-fix .actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:14px; }
        #ele-fix button { border:0; border-radius:999px; padding:8px 14px; font-weight:700; font-size:13px; cursor:pointer; }
        #ele-toast { position:fixed; top:16px; right:16px; z-index:2100; display:none; max-width:360px; border-radius:14px; padding:12px 14px; color:#fff; font-size:13px; box-shadow:0 10px 30px rgba(0,0,0,.2); }
        #ele-toast.ok { background:#059669; } #ele-toast.bad { background:#e11d48; }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div id="ele">
        <div class="ele-hero">
            <div>
                <h1>Ebay Listing Errors</h1>
                <p>Listings that are on eBay but not active, with the error, and a way to fix and push them.</p>
            </div>
            <div class="ele-tabs">
                <button type="button" class="ele-tab active" data-ch="ebay1">eBay 1</button>
                <button type="button" class="ele-tab" data-ch="ebay2">eBay 2</button>
                <button type="button" class="ele-tab" data-ch="ebay3">eBay 3</button>
            </div>
        </div>
        <div class="ele-bar">
            <div class="ele-chip"><b id="ele-count">–</b><span>not active</span></div>
            <input id="ele-search" class="ele-search" placeholder="Search SKU, item id, error…">
        </div>
        <div id="ele-table"></div>
    </div>

    <div id="ele-overlay"><div class="card"><div class="spin"></div><div id="ele-overlay-text">Loading…</div></div></div>

    <div id="ele-fix">
        <div class="card">
            <h3 id="ele-fix-title">Fix listing</h3>
            <div class="small text-muted" id="ele-fix-sub"></div>
            <div class="err" id="ele-fix-err"></div>
            <div class="row2">
                <div><label>Quantity</label><input id="ele-qty" type="number" min="0" placeholder="e.g. 5"></div>
                <div><label>Price</label><input id="ele-price" type="number" min="0" step="0.01" placeholder="e.g. 19.99"></div>
            </div>
            <div class="actions">
                <button type="button" id="ele-push-qty" style="background:#6d28d9;color:#fff">Push quantity</button>
                <button type="button" id="ele-push-price" style="background:#0f766e;color:#fff">Push price</button>
                <button type="button" id="ele-relist" style="background:#db2777;color:#fff">Relist</button>
                <button type="button" id="ele-check" style="background:#eef2ff;color:#3730a3">Check live error</button>
                <button type="button" id="ele-close" style="background:#f1f5f9;color:#334155;margin-left:auto">Close</button>
            </div>
        </div>
    </div>
    <div id="ele-toast"></div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const URLS = {
                data: @json(route('ebay.listing.errors.data')),
                check: @json(route('ebay.listing.errors.check')),
                push: @json(route('ebay.listing.errors.push'))
            };
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            let channel = 'ebay1';
            let current = null;

            const overlay = document.getElementById('ele-overlay');
            const toastEl = document.getElementById('ele-toast');
            function busy(text) { document.getElementById('ele-overlay-text').textContent = text || 'Working…'; overlay.classList.add('show'); }
            function idle() { overlay.classList.remove('show'); }
            function toast(ok, text) {
                toastEl.className = ok ? 'ok' : 'bad';
                toastEl.style.display = 'block';
                toastEl.textContent = text;
                clearTimeout(toastEl._t);
                toastEl._t = setTimeout(function () { toastEl.style.display = 'none'; }, ok ? 4000 : 9000);
            }
            function post(url, payload) {
                return fetch(url, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(payload)
                }).then(function (r) { return r.json().catch(function () { return {}; }); });
            }

            const table = new Tabulator('#ele-table', {
                index: 'id', layout: 'fitColumns', height: 'calc(100vh - 280px)',
                placeholder: 'No inactive listings on this account.',
                columns: [
                    { title: 'SKU', field: 'sku', width: 170, headerFilter: 'input' },
                    { title: 'Item ID', field: 'item_id', width: 130, formatter: function (cell) {
                        const d = cell.getData();
                        return d.link ? '<a href="' + d.link + '" target="_blank" rel="noopener">' + d.item_id + '</a>' : d.item_id;
                    }},
                    { title: 'Title', field: 'title', minWidth: 180 },
                    { title: 'Status', field: 'status', width: 110, hozAlign: 'center', formatter: function (c) { return '<span class="ele-status">' + (c.getValue() || '') + '</span>'; } },
                    { title: 'Stock', field: 'stock', width: 80, hozAlign: 'right' },
                    { title: 'Price', field: 'price', width: 90, hozAlign: 'right', formatter: function (c) { const v = c.getValue(); return v == null ? '' : '$' + Number(v).toFixed(2); } },
                    { title: 'Error', field: 'error', minWidth: 220, formatter: function (c) { return '<span class="ele-err">' + String(c.getValue() || '').replace(/[&<>]/g, function (s) { return { '&':'&amp;','<':'&lt;','>':'&gt;' }[s]; }) + '</span>'; } },
                    { title: '', field: 'id', width: 90, hozAlign: 'center', headerSort: false, formatter: function () { return '<button type="button" class="ele-fix">Fix</button>'; }, cellClick: function (e, cell) { if (e.target.closest('.ele-fix')) openFix(cell.getData()); } }
                ]
            });

            function load() {
                busy('Loading ' + channel + ' listings…');
                fetch(URLS.data + '?channel=' + channel, { headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        const rows = (j && j.rows) || [];
                        table.setData(rows);
                        document.getElementById('ele-count').textContent = rows.length;
                    })
                    .catch(function () { toast(false, 'Could not load the listings.'); })
                    .finally(idle);
            }

            document.querySelectorAll('.ele-tab').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.querySelectorAll('.ele-tab').forEach(function (b) { b.classList.remove('active'); });
                    btn.classList.add('active');
                    channel = btn.dataset.ch;
                    load();
                });
            });
            document.getElementById('ele-search').addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                table.setFilter(function (d) {
                    return !q || [d.sku, d.item_id, d.title, d.error, d.status].join(' ').toLowerCase().includes(q);
                });
            });

            function openFix(row) {
                current = row;
                document.getElementById('ele-fix-title').textContent = row.sku || row.item_id;
                document.getElementById('ele-fix-sub').textContent = 'Item ' + row.item_id + ' · ' + (row.status || '');
                document.getElementById('ele-fix-err').textContent = row.error || '';
                document.getElementById('ele-qty').value = row.stock != null ? row.stock : '';
                document.getElementById('ele-price').value = row.price != null ? row.price : '';
                document.getElementById('ele-fix').classList.add('show');
            }
            document.getElementById('ele-close').addEventListener('click', function () { document.getElementById('ele-fix').classList.remove('show'); });

            function run(action) {
                if (!current) return;
                const qty = document.getElementById('ele-qty').value;
                const price = document.getElementById('ele-price').value;
                busy(action === 'check' ? 'Asking eBay…' : 'Pushing to ' + channel + '…');
                const url = action === 'check' ? URLS.check : URLS.push;
                const payload = { channel: channel, item_id: current.item_id, sku: current.sku, action: action };
                if (qty !== '') payload.quantity = Number(qty);
                if (price !== '') payload.price = Number(price);
                post(url, payload).then(function (j) {
                    const ok = !!(j && j.success);
                    toast(ok, (j && (j.message || j.error)) || (ok ? 'Done.' : 'Failed.'));
                    if (j && j.error !== undefined) {
                        current.error = j.error || current.error;
                        document.getElementById('ele-fix-err').textContent = current.error;
                    }
                    if (j && j.status) current.status = j.status;
                    if (j && j.item_id) current.item_id = j.item_id;
                    if (ok && action !== 'check') {
                        current.error = (j && j.message) || current.error;
                        document.getElementById('ele-fix-err').textContent = current.error;
                    }
                    const live = table.getRow(current.id);
                    if (live) {
                        live.update({ error: current.error, status: current.status, item_id: current.item_id });
                        if (String(current.status).toUpperCase() === 'ACTIVE') live.delete();
                    }
                }).catch(function () { toast(false, 'The request failed.'); }).finally(idle);
            }
            document.getElementById('ele-push-qty').addEventListener('click', function () { run('qty'); });
            document.getElementById('ele-push-price').addEventListener('click', function () { run('price'); });
            document.getElementById('ele-relist').addEventListener('click', function () { run('relist'); });
            document.getElementById('ele-check').addEventListener('click', function () { run('check'); });

            load();
        });
    </script>
@endsection
