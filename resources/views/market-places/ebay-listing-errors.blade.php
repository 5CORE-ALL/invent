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
        #ele .ele-views { display:inline-flex; background:#fff; border:1px solid var(--line); border-radius:999px; padding:3px; gap:2px; }
        #ele .ele-view { border:0; background:transparent; border-radius:999px; padding:6px 13px; font-size:12px; font-weight:700; color:var(--muted); cursor:pointer; transition: background .15s, color .15s; }
        #ele .ele-view.active { background:#6d28d9; color:#fff; }
        #ele .ele-pulled { font-size:11px; color:var(--muted); }
        #ele .ele-status.live { background:#dcfce7; color:#166534; }
        #ele .ele-nosku { display:inline-block; font-size:10px; font-weight:800; background:#fef3c7; color:#92400e; border-radius:999px; padding:1px 7px; margin-left:4px; }
        #ele .ele-pull { border:0; border-radius:999px; background:#111827; color:#fff; font-weight:700; font-size:13px; padding:8px 14px; cursor:pointer; }
        #ele .ele-thumb { width:42px; height:42px; object-fit:cover; border-radius:10px; background:#f4f2fb; display:block; }
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
        #ele-fix .iss { display:flex; gap:8px; align-items:flex-start; background:#fff1f2; color:#9f1239; border-radius:10px; padding:8px 10px; font-size:13px; margin:6px 0; }
        #ele-fix .iss i { font-style:normal; font-size:10px; font-weight:800; letter-spacing:.04em; text-transform:uppercase; background:#fff; border-radius:999px; padding:1px 7px; flex:none; margin-top:2px; }
        #ele-fix .hint { background:#eef2ff; color:#3730a3; border-radius:10px; padding:8px 10px; font-size:12px; margin:8px 0; display:none; }
        #ele-fix .ele-openbtn { border-radius:999px; padding:8px 14px; font-weight:700; font-size:13px; background:#0f172a; color:#fff; text-decoration:none; }
        #ele .ele-fix.relist { background:#db2777; } #ele .ele-fix.stock { background:#0f766e; } #ele .ele-fix.violation { background:#4f46e5; }
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
                <p>CP Master SKUs that are on eBay and have an error, active or not. The Fix button offers the action that matches each error.</p>
            </div>
            <div class="ele-tabs">
                <button type="button" class="ele-tab active" data-ch="ebay1">eBay 1</button>
                <button type="button" class="ele-tab" data-ch="ebay2">eBay 2</button>
                <button type="button" class="ele-tab" data-ch="ebay3">eBay 3</button>
            </div>
        </div>
        <div class="ele-bar">
            <div class="ele-chip"><b id="ele-count">–</b><span>with errors</span></div>
            <button type="button" class="ele-pull" id="ele-pull">Pull from eBay</button>
            <input id="ele-search" class="ele-search" placeholder="Search SKU, item id, error…">
        </div>
        <div id="ele-table"></div>
    </div>

    <div id="ele-overlay"><div class="card"><div class="spin"></div><div id="ele-overlay-text">Loading…</div></div></div>

    <div id="ele-fix">
        <div class="card">
            <h3 id="ele-fix-title">Fix listing</h3>
            <div class="small text-muted" id="ele-fix-sub"></div>
            <div id="ele-fix-issues"></div>
            <div id="ele-qty-box">
                <label>Quantity to push <span id="ele-qty-note" class="text-muted"></span></label>
                <input id="ele-qty" type="number" min="0" placeholder="e.g. 5">
            </div>
            <div class="actions">
                <button type="button" id="ele-relist" style="background:#db2777;color:#fff">Relist</button>
                <a id="ele-open" href="#" target="_blank" rel="noopener" class="ele-openbtn">Open on eBay</a>
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
                refresh: @json(route('ebay.listing.errors.refresh')),
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

            // The Fix button follows the error: a listing that is not active is relisted.
            function primary(codes) {
                return { cls: 'relist', label: 'Relist' };
            }
            function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

            const table = new Tabulator('#ele-table', {
                index: 'id', layout: 'fitColumns', height: 'calc(100vh - 280px)',
                placeholder: 'No CP Master listing with an error. Use Pull from eBay to refresh.',
                columns: [
                    { title: '', field: 'image', width: 62, headerSort: false, hozAlign: 'center', formatter: function (cell) {
                        const src = String(cell.getValue() || '');
                        if (!src) return '';
                        return '<img class="ele-thumb" src="' + src.replace(/"/g, '') + '" alt="">';
                    }},
                    { title: 'SKU', field: 'sku', width: 180, headerFilter: 'input', formatter: function (cell) {
                        const d = cell.getData();
                        const name = String(d.sku || '');
                        const parent = String(d.parent || '');
                        const extra = parent && parent !== name ? '<div class="small text-muted">' + esc(parent) + '</div>' : '';
                        return '<strong>' + esc(name) + '</strong>' + extra;
                    }},
                    { title: 'Item ID', field: 'item_id', width: 130, formatter: function (cell) {
                        const d = cell.getData();
                        return d.link ? '<a href="' + d.link + '" target="_blank" rel="noopener">' + d.item_id + '</a>' : d.item_id;
                    }},
                    { title: 'Title', field: 'title', minWidth: 180 },
                    { title: 'Status', field: 'status', width: 110, hozAlign: 'center', formatter: function (c) { const v = String(c.getValue() || ''); return '<span class="ele-status' + (v === 'ACTIVE' ? ' live' : '') + '">' + v + '</span>'; } },
                    { title: 'Price', field: 'price', width: 90, hozAlign: 'right', formatter: function (c) { const v = c.getValue(); return v == null ? '' : '$' + Number(v).toFixed(2); } },
                    { title: 'Error', field: 'error', minWidth: 220, formatter: function (c) { return '<span class="ele-err">' + String(c.getValue() || '').replace(/[&<>]/g, function (s) { return { '&':'&amp;','<':'&lt;','>':'&gt;' }[s]; }) + '</span>'; } },
                    { title: '', field: 'id', width: 110, hozAlign: 'center', headerSort: false, formatter: function (c) { const k = primary(c.getData().codes); return '<button type="button" class="ele-fix ' + k.cls + '">' + k.label + '</button>'; }, cellClick: function (e, cell) { if (e.target.closest('.ele-fix')) openFix(cell.getData()); } }
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

            function show(id, on) { document.getElementById(id).style.display = on ? '' : 'none'; }

            // The fix window only offers what the row's errors call for.
            function renderFix(row) {
                const codes = row.codes || [];
                document.getElementById('ele-fix-title').textContent = row.sku || row.item_id;
                document.getElementById('ele-fix-sub').textContent = 'Item ' + row.item_id + ' · ' + (row.status || '');
                document.getElementById('ele-fix-issues').innerHTML = (row.issues || []).map(function (i) {
                    return '<div class="iss"><i>not active</i><span>' + esc(i.text) + '</span></div>';
                }).join('');
                const needRelist = codes.indexOf('inactive') !== -1;
                show('ele-qty-box', needRelist);
                show('ele-relist', needRelist);
                document.getElementById('ele-open').href = row.link || ('https://www.ebay.com/itm/' + row.item_id);
                const suggested = row.inv != null && row.inv > 0 ? row.inv : (row.stock != null ? row.stock : '');
                document.getElementById('ele-qty').value = suggested;
                document.getElementById('ele-qty-note').textContent = row.inv != null ? '(CP Master INV: ' + row.inv + ')' : '';
            }
            function openFix(row) {
                current = row;
                renderFix(row);
                document.getElementById('ele-fix').classList.add('show');
            }
            document.getElementById('ele-close').addEventListener('click', function () { document.getElementById('ele-fix').classList.remove('show'); });

            // Apply what a push / check found to the row: drop the issue that is fixed, keep the rest.
            function settle(code, newText) {
                current.issues = (current.issues || []).filter(function (i) { return i.code !== code; });
                if (newText) current.issues.push({ code: code, text: newText });
                current.codes = current.issues.map(function (i) { return i.code; }).filter(function (c, i, a) { return a.indexOf(c) === i; });
                current.error = current.issues.map(function (i) { return i.text; }).join(' • ');
                const live = table.getRow(current.id);
                if (!current.issues.length) {
                    if (live) live.delete();
                    document.getElementById('ele-fix').classList.remove('show');
                    document.getElementById('ele-count').textContent = table.getDataCount();
                    return;
                }
                if (live) live.update({ issues: current.issues, codes: current.codes, error: current.error, status: current.status, stock: current.stock, item_id: current.item_id });
                renderFix(current);
            }

            function run(action) {
                if (!current) return;
                const qty = document.getElementById('ele-qty').value;
                busy(action === 'check' ? 'Asking eBay…' : (action === 'relist' ? 'Relisting on ' : 'Pushing to ') + (action === 'check' ? '' : channel + '…'));
                const payload = { channel: channel, item_id: current.item_id, sku: current.sku, action: action };
                if (qty !== '') payload.quantity = Number(qty);
                post(action === 'check' ? URLS.check : URLS.push, payload).then(function (j) {
                    const ok = !!(j && j.success);
                    toast(ok, (j && (j.message || j.error)) || (ok ? 'Done.' : 'Failed.'));
                    if (!ok) return;
                    if (j.item_id) current.item_id = j.item_id;
                    if (action === 'relist') {
                        current.status = 'ACTIVE';
                        if (qty !== '') current.stock = Number(qty);
                        settle('inactive');
                    } else if (action === 'check') {
                        if (j.status) current.status = j.status;
                        if (String(j.status).toUpperCase() === 'ACTIVE') settle('inactive');
                        else settle('inactive', j.error || j.message);
                    }
                }).catch(function () { toast(false, 'The request failed.'); }).finally(idle);
            }
            document.getElementById('ele-relist').addEventListener('click', function () { run('relist'); });
            document.getElementById('ele-check').addEventListener('click', function () { run('check'); });
            document.getElementById('ele-pull').addEventListener('click', function () {
                busy('Pulling inactive listings from ' + channel + '… this can take a minute');
                post(URLS.refresh, { channel: channel }).then(function (j) {
                    toast(!!(j && j.success), (j && j.message) || 'Pull finished.');
                    load();
                }).catch(function () { idle(); toast(false, 'The pull failed.'); });
            });

            load();
        });
    </script>
@endsection
