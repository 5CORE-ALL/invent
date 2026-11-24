@extends('layouts.vertical', ['title' => 'Category Specifics'])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #csp {
            --ink: #14112b; --muted: #6b6785; --line: #ece8fb; --card: #fff;
            --violet: #7c3aed; --pink: #f43f8e; --cyan: #06b6d4; --lime: #c6f432; --amber: #f59e0b; --green: #10b981;
            font-family: "Segoe UI", "Trebuchet MS", sans-serif; color: var(--ink);
        }
        #csp * { box-sizing: border-box; }
        #csp .csp-hero {
            position: relative; overflow: hidden; border-radius: 28px; padding: 26px 28px 22px; color: #fff;
            background:
                radial-gradient(420px 200px at 4% -30%, rgba(244,63,142,.75) 0%, transparent 70%),
                radial-gradient(420px 220px at 100% 0%, rgba(6,182,212,.6) 0%, transparent 68%),
                linear-gradient(135deg, #14112b 0%, #2b1670 55%, #5b21b6 100%);
            box-shadow: 0 18px 44px rgba(43, 22, 112, .3);
        }
        #csp .csp-hero::after {
            content: ""; position: absolute; inset: 0; pointer-events: none;
            background: linear-gradient(110deg, transparent 35%, rgba(255,255,255,.07) 50%, transparent 65%);
            transform: translateX(-100%); animation: cspSheen 6s ease-in-out infinite;
        }
        @keyframes cspSheen { 55%, 100% { transform: translateX(100%); } }
        #csp .csp-kicker {
            display: inline-flex; align-items: center; gap: 8px; background: var(--lime); color: #1d2a02;
            font-weight: 800; font-size: 11.5px; letter-spacing: .09em; text-transform: uppercase; border-radius: 999px; padding: 5px 12px;
        }
        #csp .csp-hero h1 { font-size: 34px; font-weight: 800; letter-spacing: -.03em; margin: 12px 0 6px; }
        #csp .csp-hero p { margin: 0; max-width: 680px; color: #e4defc; font-size: 14.5px; }
        #csp .csp-stats { position: relative; z-index: 1; display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; align-items: stretch; }
        #csp .csp-stat {
            background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.22); border-radius: 16px;
            padding: 10px 14px; min-width: 120px; backdrop-filter: blur(6px);
        }
        #csp .csp-stat b { display: block; font-size: 24px; line-height: 1.1; }
        #csp .csp-stat span { font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #d9d2fb; }
        #csp .csp-review {
            border: 0; border-radius: 16px; padding: 10px 16px; font-weight: 800; font-size: 13px; cursor: pointer;
            background: var(--amber); color: #2b1a00; display: none;
        }
        #csp .csp-review:hover { filter: brightness(1.06); }

        #csp .csp-tabs-wrap { margin: 16px 0 12px; background: var(--card); border: 1px solid var(--line); border-radius: 22px; padding: 12px; }
        #csp .csp-tabs-top { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 10px; }
        #csp .csp-input {
            border: 1.5px solid #e6dffa; border-radius: 14px; padding: 9px 12px; font-size: 14px; color: var(--ink); background: #fbf9ff; min-width: 220px; flex: 1;
        }
        #csp .csp-input:focus { outline: 2px solid #ddd6fe; border-color: var(--violet); }
        #csp .csp-tabs { display: flex; flex-wrap: wrap; gap: 8px; max-height: 148px; overflow-y: auto; padding: 2px; }
        #csp .csp-tab {
            display: inline-flex; align-items: center; gap: 8px; border: 1.5px solid #ebe6fb; background: #fbf9ff; color: var(--ink);
            border-radius: 999px; padding: 7px 13px; font-size: 13px; font-weight: 700; cursor: pointer; transition: transform .12s, background .12s;
        }
        #csp .csp-tab:hover { transform: translateY(-1px); background: #f3eeff; }
        #csp .csp-tab.is-on { background: var(--ink); color: #fff; border-color: var(--ink); box-shadow: 0 8px 18px rgba(20,17,43,.25); }
        #csp .csp-dot { width: 9px; height: 9px; border-radius: 50%; flex: none; background: #c9c4dd; }
        #csp .csp-dot.confirmed { background: var(--green); }
        #csp .csp-dot.auto { background: var(--cyan); }
        #csp .csp-dot.confirm { background: var(--amber); box-shadow: 0 0 0 3px rgba(245,158,11,.25); animation: cspPulse 1.6s infinite; }
        @keyframes cspPulse { 50% { box-shadow: 0 0 0 6px rgba(245,158,11,0); } }
        #csp .csp-count { font-size: 11px; opacity: .65; font-weight: 800; }

        #csp .csp-panel { background: var(--card); border: 1px solid var(--line); border-radius: 26px; padding: 18px; }
        #csp .csp-head { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between; }
        #csp .csp-title { font-size: 24px; font-weight: 800; letter-spacing: -.02em; margin: 0; }
        #csp .csp-sub { color: var(--muted); font-size: 13px; margin-top: 2px; }
        #csp .csp-match {
            display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 10px;
            background: linear-gradient(90deg, #f6f1ff, #effcff); border: 1px solid #e4dcfb; border-radius: 16px; padding: 9px 12px; font-size: 13.5px;
        }
        #csp .csp-match .csp-path { font-weight: 700; word-break: break-word; }
        #csp .csp-badge { border-radius: 999px; padding: 3px 10px; font-size: 11.5px; font-weight: 800; letter-spacing: .02em; }
        #csp .csp-badge.confirmed { background: #d1fae5; color: #065f46; }
        #csp .csp-badge.auto { background: #cffafe; color: #155e75; }
        #csp .csp-badge.confirm { background: #fef3c7; color: #92400e; }
        #csp .csp-btn {
            border: 0; border-radius: 999px; padding: 8px 14px; font-weight: 800; font-size: 12.5px; cursor: pointer; background: var(--ink); color: #fff;
        }
        #csp .csp-btn:hover { background: var(--violet); }
        #csp .csp-btn.soft { background: #f1ecff; color: #5b21b6; }
        #csp .csp-btn.soft:hover { background: #e4dbff; }
        #csp .csp-btn:disabled { opacity: .45; cursor: not-allowed; }
        #csp .csp-chips { display: flex; flex-wrap: wrap; gap: 8px; margin: 14px 0 10px; }
        #csp .csp-chip { background: #f7f4ff; border: 1px solid #ebe6fb; border-radius: 14px; padding: 8px 12px; min-width: 110px; }
        #csp .csp-chip b { display: block; font-size: 18px; line-height: 1.1; }
        #csp .csp-chip span { font-size: 10.5px; color: var(--muted); letter-spacing: .06em; text-transform: uppercase; font-weight: 700; }
        #csp .csp-bar { height: 5px; background: #ece8fb; border-radius: 99px; margin-top: 6px; overflow: hidden; }
        #csp .csp-bar i { display: block; height: 100%; background: linear-gradient(90deg, var(--pink), var(--violet), var(--cyan)); border-radius: 99px; }
        #csp .csp-note { color: var(--muted); font-size: 12.5px; margin: 6px 2px 10px; }
        #csp .csp-empty { text-align: center; padding: 54px 12px; color: var(--muted); }
        #csp .csp-empty .big { font-size: 44px; }
        #csp .csp-empty h3 { color: var(--ink); font-size: 20px; font-weight: 800; margin: 8px 0 4px; }

        #csp .tabulator { border: 1px solid #f0ecfb; border-radius: 16px; font-size: 13.5px; background: #fff; }
        #csp .tabulator-header { background: #faf8ff; border-bottom: 1px solid #ece8fb; }
        #csp .tabulator-col { background: transparent !important; border-right: 1px solid #f1edfc !important; }
        #csp .tabulator-col-title { color: #4b4668; font-size: 11px; letter-spacing: .05em; text-transform: uppercase; font-weight: 800; }
        #csp .tabulator-col-title small { display: block; font-size: 10px; letter-spacing: 0; color: var(--muted); text-transform: none; font-weight: 700; }
        #csp .tabulator-row { border-bottom: 1px solid #f6f2fd; }
        #csp .tabulator-row:hover { background: #faf6ff !important; }
        #csp .tabulator-cell { border-right: 1px solid #f6f2fd !important; padding: 9px 10px !important; }
        #csp .csp-sku { font-weight: 800; }
        #csp .csp-miss { display: inline-block; padding: 1px 8px; border-radius: 99px; background: #fee2e2; color: #b91c1c; font-size: 11px; font-weight: 800; }
        #csp .csp-na { color: #9ca3af; }
        #csp .csp-req { color: #f43f8e; font-weight: 800; }
        #csp .csp-pill { display: inline-block; border-radius: 99px; padding: 2px 9px; font-size: 11.5px; font-weight: 800; background: #f1ecff; color: #5b21b6; }
        #csp .csp-pill.ok { background: #d1fae5; color: #065f46; }
        #csp .csp-skel { height: 220px; border-radius: 18px; background: linear-gradient(100deg, #f4f0fd 30%, #fff 50%, #f4f0fd 70%); background-size: 300% 100%; animation: cspSkel 1.3s infinite; }
        @keyframes cspSkel { to { background-position: -100% 0; } }

        #csp-modal { position: fixed; inset: 0; z-index: 2000; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(20,17,43,.55); backdrop-filter: blur(4px); }
        #csp-modal.is-open { display: flex; }
        #csp-modal .cm-card {
            width: min(720px, 100%); max-height: 88vh; overflow: hidden; display: flex; flex-direction: column;
            background: #fff; border-radius: 26px; box-shadow: 0 30px 80px rgba(20,17,43,.45); animation: cmIn .18s ease-out;
            font-family: "Segoe UI", "Trebuchet MS", sans-serif; color: #14112b;
        }
        @keyframes cmIn { from { transform: translateY(14px) scale(.98); opacity: 0; } }
        #csp-modal .cm-top { padding: 20px 22px 14px; background: linear-gradient(135deg, #14112b, #4c1d95 70%, #7c3aed); color: #fff; }
        #csp-modal .cm-top small { display: inline-block; background: #c6f432; color: #1d2a02; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; font-size: 10.5px; padding: 3px 10px; border-radius: 99px; }
        #csp-modal .cm-top h3 { margin: 10px 0 2px; font-size: 21px; font-weight: 800; letter-spacing: -.02em; }
        #csp-modal .cm-top p { margin: 0; color: #ddd6fe; font-size: 13px; }
        #csp-modal .cm-body { padding: 16px 22px; overflow: auto; }
        #csp-modal .cm-label { font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #6b6785; margin: 12px 0 6px; }
        #csp-modal .cm-item {
            display: flex; gap: 10px; align-items: flex-start; width: 100%; text-align: left; border: 1.5px solid #ebe6fb; background: #fbf9ff;
            border-radius: 14px; padding: 10px 12px; margin-bottom: 7px; cursor: pointer; font-size: 13.5px; color: #14112b;
        }
        #csp-modal .cm-item:hover { background: #f3eeff; }
        #csp-modal .cm-item.is-pick { border-color: #7c3aed; background: #f1eaff; box-shadow: 0 0 0 3px rgba(124,58,237,.14); }
        #csp-modal .cm-item .cm-radio { width: 18px; height: 18px; border-radius: 50%; border: 2px solid #c4b5fd; flex: none; margin-top: 2px; }
        #csp-modal .cm-item.is-pick .cm-radio { border-color: #7c3aed; background: radial-gradient(#7c3aed 45%, transparent 50%); }
        #csp-modal .cm-item b { display: block; font-weight: 700; word-break: break-word; }
        #csp-modal .cm-item em { font-style: normal; font-size: 11.5px; color: #6b6785; }
        #csp-modal .cm-input { width: 100%; border: 1.5px solid #e6dffa; border-radius: 14px; padding: 10px 12px; font-size: 14px; background: #fbf9ff; }
        #csp-modal .cm-input:focus { outline: 2px solid #ddd6fe; border-color: #7c3aed; }
        #csp-modal .cm-foot { padding: 14px 22px; border-top: 1px solid #efeafc; display: flex; gap: 10px; align-items: center; justify-content: flex-end; flex-wrap: wrap; }
        #csp-modal .cm-foot .cm-msg { margin-right: auto; font-size: 12.5px; color: #6b6785; }
        #csp-modal .cm-foot .cm-msg.err { color: #b91c1c; }
        #csp-modal .cm-btn { border: 0; border-radius: 999px; padding: 10px 18px; font-weight: 800; font-size: 13px; cursor: pointer; background: #14112b; color: #fff; }
        #csp-modal .cm-btn:hover { background: #7c3aed; }
        #csp-modal .cm-btn.soft { background: #f1ecff; color: #5b21b6; }
        #csp-modal .cm-btn:disabled { opacity: .4; cursor: not-allowed; }
        #csp-toast { position: fixed; right: 18px; bottom: 18px; z-index: 2100; background: #14112b; color: #fff; padding: 11px 16px; border-radius: 14px; font-weight: 700; font-size: 13px; box-shadow: 0 14px 34px rgba(20,17,43,.35); display: none; max-width: 360px; }
    </style>
@endsection

@section('content')
    <div id="csp">
        <div class="csp-hero">
            <span class="csp-kicker"><i class="fa-solid fa-wand-magic-sparkles"></i> MFR &times; eBay</span>
            <h1>Category specifics</h1>
            <p>One tab for every MFR category from the Category list. Each tab shows the item specifics of the eBay category it matches on Ebay1 Data. When the two do not match, you get asked which eBay category is the right one.</p>
            <div class="csp-stats">
                <div class="csp-stat"><b id="csp-n-total">0</b><span>MFR categories</span></div>
                <div class="csp-stat"><b id="csp-n-ok">0</b><span>matched</span></div>
                <div class="csp-stat"><b id="csp-n-ask">0</b><span>need a confirm</span></div>
                <button type="button" class="csp-review" id="csp-review"><i class="fa-solid fa-bolt"></i> Review unmatched</button>
            </div>
        </div>

        <div class="csp-tabs-wrap">
            <div class="csp-tabs-top">
                <input id="csp-tab-search" class="csp-input" type="search" placeholder="Find an MFR category tab…" autocomplete="off">
                <span class="csp-note" style="margin:0">
                    <span class="csp-dot confirmed" style="display:inline-block"></span> confirmed &nbsp;
                    <span class="csp-dot auto" style="display:inline-block"></span> same name &nbsp;
                    <span class="csp-dot confirm" style="display:inline-block"></span> needs your confirm
                </span>
            </div>
            <div class="csp-tabs" id="csp-tabs"><div class="csp-skel" style="width:100%;height:46px"></div></div>
        </div>

        <div class="csp-panel" id="csp-panel"><div class="csp-skel"></div></div>
    </div>

    <div id="csp-modal" aria-hidden="true">
        <div class="cm-card" role="dialog" aria-modal="true">
            <div class="cm-top">
                <small>Confirm the match</small>
                <h3 id="cm-title">Which eBay category is this?</h3>
                <p id="cm-sub"></p>
            </div>
            <div class="cm-body">
                <div id="cm-suggest"></div>
                <div class="cm-label">Or search the whole eBay category tree</div>
                <input id="cm-search" class="cm-input" type="search" placeholder="e.g. car speakers, microphones…" autocomplete="off">
                <div id="cm-results" style="margin-top:8px"></div>
            </div>
            <div class="cm-foot">
                <span class="cm-msg" id="cm-msg">Pick one, then confirm.</span>
                <button type="button" class="cm-btn soft" id="cm-later">Not now</button>
                <button type="button" class="cm-btn" id="cm-save" disabled>Confirm match</button>
            </div>
        </div>
    </div>
    <div id="csp-toast"></div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            const URLS = {
                data: @json(route('category.specifics.data')),
                rows: @json(route('ebay1.data.rows')),
                aspects: @json(route('category.specifics.aspects')),
                tree: @json(route('ebay1.data.ebay.categories')),
                map: @json(route('category.specifics.map')),
                unmap: @json(route('category.specifics.unmap')),
            };
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const S = {
                cats: [], maps: {}, byPath: new Map(), bySku: new Map(),
                an: {}, aspects: {}, active: null, table: null, tabQ: '', skipped: new Set(), pick: null, modalCat: null, ready: false,
            };

            const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const $ = id => document.getElementById(id);
            const norm = s => String(s || '').toLowerCase().replace(/&/g, 'and').replace(/[^a-z0-9]+/g, '').replace(/s$/, '');
            const leaf = p => String(p || '').split('>').pop().trim();
            const aspectKey = s => String(s || '').toLowerCase().replace(/[^a-z0-9]+/g, '');

            function toast(text) {
                const el = $('csp-toast');
                el.textContent = text;
                el.style.display = 'block';
                clearTimeout(toast.t);
                toast.t = setTimeout(() => { el.style.display = 'none'; }, 3200);
            }

            function post(url, body) {
                return fetch(url, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify(body),
                }).then(r => r.json().catch(() => null).then(j => {
                    if (!r.ok || !j || j.success === false) throw new Error((j && j.message) || 'Request failed.');
                    return j;
                }));
            }

            // ---- the eBay side: every Ebay1 Data row, grouped by its eBay category ----
            function indexEbay(rows) {
                S.byPath = new Map();
                S.bySku = new Map();
                rows.forEach(r => {
                    const sku = String(r.sku || '').trim();
                    if (!sku) return;
                    S.bySku.set(sku.toUpperCase(), r);
                    const path = String(r.category || '').trim();
                    if (path) {
                        if (!S.byPath.has(path)) S.byPath.set(path, { path: path, rows: [] });
                        S.byPath.get(path).rows.push(r);
                    }
                });
            }

            function specValue(row, name) {
                if (!row._sk) {
                    row._sk = {};
                    Object.keys(row.specifics || {}).forEach(k => {
                        if (!k.startsWith('__')) row._sk[aspectKey(k)] = row.specifics[k];
                    });
                }
                const v = row._sk[aspectKey(name)];
                return v === undefined || v === null ? '' : (typeof v === 'object' ? JSON.stringify(v) : String(v));
            }

            // The specific names of an eBay category, as its tab on Ebay1 Data has them (most filled first).
            function specNames(path) {
                const group = S.byPath.get(path);
                if (!group) return [];
                const count = new Map();
                group.rows.forEach(r => Object.keys(r.specifics || {}).forEach(k => {
                    if (k.startsWith('__')) return;
                    const key = aspectKey(k);
                    const cur = count.get(key) || { name: k, filled: 0 };
                    if (String(specValue(r, k)).trim() !== '') cur.filled++;
                    count.set(key, cur);
                }));
                return [...count.values()].sort((a, b) => b.filled - a.filled || a.name.localeCompare(b.name)).map(x => x.name);
            }

            // ---- matching an MFR category with an eBay category ----
            function analyze(cat) {
                const rows = cat.skus.map(s => S.bySku.get(s.toUpperCase())).filter(Boolean);
                const cand = new Map();
                rows.forEach(r => { if (r.category) cand.set(r.category, (cand.get(r.category) || 0) + 1); });
                const candList = [...cand.entries()].sort((a, b) => b[1] - a[1]);
                const saved = S.maps[cat.key];
                let status = 'confirm';
                let path = '';
                if (saved && saved.path) {
                    status = 'confirmed';
                    path = saved.path;
                } else {
                    const n = norm(cat.name);
                    let hit = candList.find(([p]) => norm(leaf(p)) === n || norm(p) === n);
                    if (!hit && candList.length === 0) {
                        const all = [...S.byPath.keys()].filter(p => norm(leaf(p)) === n);
                        if (all.length === 1) hit = [all[0], 0];
                    }
                    if (hit) { status = 'auto'; path = hit[0]; }
                }
                return { rows: rows, candList: candList, status: status, path: path };
            }

            function analyzeAll() {
                S.an = {};
                S.cats.forEach(c => { S.an[c.key] = analyze(c); });
                const ok = S.cats.filter(c => S.an[c.key].status !== 'confirm').length;
                $('csp-n-total').textContent = S.cats.length.toLocaleString();
                $('csp-n-ok').textContent = ok.toLocaleString();
                $('csp-n-ask').textContent = (S.cats.length - ok).toLocaleString();
                $('csp-review').style.display = S.cats.length - ok > 0 ? 'inline-block' : 'none';
            }

            // ---- tabs ----
            function renderTabs() {
                const q = S.tabQ.trim().toLowerCase();
                const html = S.cats.filter(c => !q || c.name.toLowerCase().includes(q)).map(c => {
                    const a = S.an[c.key];
                    return '<button type="button" class="csp-tab' + (S.active === c.key ? ' is-on' : '') + '" data-key="' + esc(c.key) + '">'
                        + '<span class="csp-dot ' + a.status + '"></span>' + esc(c.name)
                        + '<span class="csp-count">' + c.skus.length + '</span></button>';
                }).join('');
                $('csp-tabs').innerHTML = html || '<span class="csp-note">No MFR category matches that.</span>';
            }

            function activate(key, askIfNeeded) {
                S.active = key;
                renderTabs();
                renderPanel();
                const cat = S.cats.find(c => c.key === key);
                if (askIfNeeded && cat && S.an[key].status === 'confirm' && !S.skipped.has(key)) openModal(cat);
            }

            // ---- the panel of one tab ----
            function renderPanel() {
                const panel = $('csp-panel');
                if (S.table) { S.table.destroy(); S.table = null; }
                const cat = S.cats.find(c => c.key === S.active);
                if (!cat) { panel.innerHTML = '<div class="csp-empty"><div class="big">🗂️</div><h3>No MFR categories yet</h3>Add some on the Category list page.</div>'; return; }
                const a = S.an[cat.key];
                const label = { confirmed: 'You confirmed', auto: 'Same name', confirm: 'Needs your confirm' }[a.status];

                let head = '<div class="csp-head"><div><h2 class="csp-title">' + esc(cat.name) + '</h2>'
                    + '<div class="csp-sub">' + cat.skus.length.toLocaleString() + ' SKU' + (cat.skus.length === 1 ? '' : 's') + ' in CP Master · '
                    + a.rows.length.toLocaleString() + ' on Ebay1 Data</div></div>'
                    + '<div><button type="button" class="csp-btn soft" id="csp-change"><i class="fa-solid fa-link"></i> '
                    + (a.status === 'confirm' ? 'Match it now' : 'Change match') + '</button>'
                    + (a.status === 'confirmed' ? ' <button type="button" class="csp-btn soft" id="csp-unmatch">Unmatch</button>' : '') + '</div></div>';

                head += '<div class="csp-match"><span class="csp-badge ' + a.status + '">' + label + '</span>'
                    + (a.path ? '<i class="fa-solid fa-arrows-left-right"></i> <span class="csp-path">' + esc(a.path) + '</span>'
                        : '<span class="csp-path">No eBay category is matched with this MFR category yet.</span>') + '</div>';

                if (!a.path) {
                    panel.innerHTML = head + '<div class="csp-empty"><div class="big">🧩</div><h3>Match a category to see its specifics</h3>'
                        + 'The names do not line up, so tell us which eBay category “' + esc(cat.name) + '” is.<br><br>'
                        + '<button type="button" class="csp-btn" id="csp-change2">Choose the eBay category</button></div>';
                    return;
                }

                // Rows: the SKUs of this MFR category; when none of them is on Ebay1 Data, the eBay category's own SKUs.
                let rows = a.rows;
                let fallback = false;
                if (!rows.length) { rows = (S.byPath.get(a.path) || { rows: [] }).rows; fallback = true; }
                // Every specific eBay defines for the category (required first), then any extra ones the SKUs carry.
                const asp = aspectsFor(a.path);
                const fields = [];
                const seen = new Set();
                if (asp.state === 'ok') {
                    asp.list.forEach(x => { fields.push({ name: x.name, required: x.required, tax: true, info: x }); seen.add(aspectKey(x.name)); });
                }
                specNames(a.path).forEach(n => {
                    if (!seen.has(aspectKey(n))) { fields.push({ name: n, required: false, tax: false }); seen.add(aspectKey(n)); }
                });
                const names = fields.map(f => f.name);
                const requiredCount = fields.filter(f => f.required).length;
                const total = rows.length * names.length;
                let filled = 0;
                const fill = names.map(n => {
                    let f = 0;
                    rows.forEach(r => { if (String(specValue(r, n)).trim() !== '') f++; });
                    filled += f;
                    return rows.length ? Math.round((f / rows.length) * 100) : 0;
                });
                const pct = total ? Math.round((filled / total) * 100) : 0;

                head += '<div class="csp-chips">'
                    + '<div class="csp-chip"><b>' + names.length + '</b><span>specifics</span></div>'
                    + '<div class="csp-chip"><b>' + (asp.state === 'ok' ? requiredCount : '–') + '</b><span>required by eBay</span></div>'
                    + '<div class="csp-chip"><b>' + rows.length.toLocaleString() + '</b><span>SKUs shown</span></div>'
                    + '<div class="csp-chip"><b>' + pct + '%</b><span>filled</span><div class="csp-bar"><i style="width:' + pct + '%"></i></div></div>'
                    + '<div class="csp-chip"><b>' + (total - filled).toLocaleString() + '</b><span>blank cells</span></div></div>';
                if (asp.state === 'loading') {
                    head += '<div class="csp-note"><i class="fa-solid fa-spinner fa-spin"></i> Loading every specific eBay defines for this category…</div>';
                } else if (asp.state === 'err') {
                    head += '<div class="csp-note" style="color:#b45309">Only the specifics your SKUs already carry are shown. ' + esc(asp.message) + '</div>';
                } else if (asp.state === 'ok') {
                    head += '<div class="csp-note">All ' + asp.list.length + ' specifics eBay defines for category ' + esc(asp.id) + ' are columns below'
                        + ' (<span class="csp-req">★</span> = required). Blank means no SKU has a value yet.</div>';
                }
                if (fallback) {
                    head += '<div class="csp-note">None of this MFR category’s SKUs are on Ebay1 Data, so the SKUs of the matched eBay category are shown.</div>';
                } else if (cat.skus.length > a.rows.length) {
                    head += '<div class="csp-note">' + (cat.skus.length - a.rows.length) + ' SKU(s) of this MFR category are not on Ebay1 Data and are not listed.</div>';
                }

                panel.innerHTML = head
                    + '<div class="csp-tabs-top"><input id="csp-grid-search" class="csp-input" type="search" placeholder="Search a SKU or a value in this tab…" autocomplete="off"></div>'
                    + '<div id="csp-grid"></div>';

                const columns = [
                    { title: 'SKU', field: 'sku', frozen: true, minWidth: 150, headerFilter: 'input', formatter: c => '<span class="csp-sku">' + esc(c.getValue()) + '</span>' },
                    { title: 'Parent', field: 'parent', minWidth: 110 },
                    { title: 'INV', field: 'inv', width: 70, hozAlign: 'right' },
                    {
                        title: 'eBay 1', field: 'listed', width: 96,
                        formatter: c => '<span class="csp-pill' + (['Listed', 'Created'].includes(c.getValue()) ? ' ok' : '') + '">' + esc(c.getValue() || '—') + '</span>',
                    },
                ];
                names.forEach((n, i) => {
                    const f = fields[i];
                    const tip = n + (f.required ? ' (required)' : '') + ' — ' + fill[i] + '% filled'
                        + (f.info && f.info.value_count ? ' · ' + f.info.value_count + ' allowed values, e.g. ' + f.info.values.slice(0, 6).join(', ') : '');
                    columns.push({
                        title: esc(n) + (f.required ? ' <span class="csp-req">★</span>' : '') + '<small>' + fill[i] + '% filled</small>',
                        field: 'sp' + i, minWidth: 130, headerTooltip: tip,
                        formatter: c => {
                            const v = String(c.getValue() ?? '').trim();
                            if (v === '') return '<span class="csp-miss">blank</span>';
                            if (/^(does not apply|not applicable)$/i.test(v)) return '<span class="csp-na">' + esc(v) + '</span>';
                            return esc(v);
                        },
                    });
                });

                const data = rows.map(r => {
                    const o = { sku: r.sku, parent: r.parent || '', inv: r.inv, listed: r.listed || '' };
                    names.forEach((n, i) => { o['sp' + i] = specValue(r, n); });
                    return o;
                });

                S.table = new Tabulator('#csp-grid', {
                    data: data, columns: columns, layout: 'fitDataFill', height: '60vh', movableColumns: true,
                    placeholder: 'No SKUs to show.',
                });
            }

            // eBay's full list of specifics of one category, fetched once per category and kept for the visit.
            function aspectsFor(path) {
                const cur = S.aspects[path];
                if (cur) return cur;
                S.aspects[path] = { state: 'loading', list: [], id: '', message: '' };
                const cat = S.cats.find(c => c.key === S.active);
                const saved = cat ? S.maps[cat.key] : null;
                const id = saved && saved.path === path ? (saved.id || '') : '';
                fetch(URLS.aspects + '?path=' + encodeURIComponent(path) + (id ? '&id=' + encodeURIComponent(id) : ''), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                    .then(r => r.json().catch(() => null))
                    .then(j => {
                        if (j && j.success && Array.isArray(j.aspects)) {
                            S.aspects[path] = { state: 'ok', list: j.aspects, id: j.category_id || '', message: '' };
                        } else {
                            S.aspects[path] = { state: 'err', list: [], id: '', message: (j && j.message) || 'eBay did not answer.' };
                        }
                    })
                    .catch(() => { S.aspects[path] = { state: 'err', list: [], id: '', message: 'The request failed.' }; })
                    .then(() => {
                        const now = S.cats.find(c => c.key === S.active);
                        if (now && S.an[now.key] && S.an[now.key].path === path) renderPanel();
                    });
                return S.aspects[path];
            }

            function gridSearch(q) {
                if (!S.table) return;
                q = q.trim().toLowerCase();
                if (!q) { S.table.clearFilter(); return; }
                S.table.setFilter(row => Object.values(row).some(v => String(v ?? '').toLowerCase().includes(q)));
            }

            // ---- the confirm modal ----
            function item(path, note, id) {
                const picked = S.pick && S.pick.path === path;
                return '<button type="button" class="cm-item' + (picked ? ' is-pick' : '') + '" data-path="' + esc(path) + '" data-id="' + esc(id || '') + '">'
                    + '<span class="cm-radio"></span><span><b>' + esc(path) + '</b><em>' + esc(note) + '</em></span></button>';
            }

            function renderSuggestions() {
                const cat = S.modalCat;
                if (!cat) return;
                const a = S.an[cat.key];
                const used = new Set();
                let html = '';
                if (a.candList.length) {
                    html += '<div class="cm-label">Where this category’s SKUs already sit on eBay</div>';
                    a.candList.slice(0, 6).forEach(([p, n]) => {
                        used.add(p);
                        html += item(p, n + ' of ' + a.rows.length + ' SKU' + (a.rows.length === 1 ? '' : 's') + ' use it', '');
                    });
                }
                const n = norm(cat.name);
                const similar = [...S.byPath.keys()].filter(p => {
                    if (used.has(p)) return false;
                    const l = norm(leaf(p));
                    return l.length >= 3 && n.length >= 3 && (l.includes(n) || n.includes(l));
                }).slice(0, 6);
                if (similar.length) {
                    html += '<div class="cm-label">Similar names on Ebay1 Data</div>';
                    similar.forEach(p => { html += item(p, 'name looks alike', ''); });
                }
                if (!html) html = '<div class="csp-note">Nothing close on Ebay1 Data. Search the eBay tree below.</div>';
                $('cm-suggest').innerHTML = html;
            }

            function refreshPick() {
                document.querySelectorAll('#csp-modal .cm-item').forEach(el => el.classList.toggle('is-pick', !!S.pick && el.dataset.path === S.pick.path));
                $('cm-save').disabled = !S.pick;
                $('cm-msg').className = 'cm-msg';
                $('cm-msg').textContent = S.pick ? 'Confirm: ' + S.modalCat.name + '  =  ' + leaf(S.pick.path) : 'Pick one, then confirm.';
            }

            function openModal(cat) {
                S.modalCat = cat;
                const a = S.an[cat.key];
                S.pick = a.path ? { path: a.path, id: (S.maps[cat.key] || {}).id || '' } : null;
                $('cm-title').textContent = 'Which eBay category is “' + cat.name + '”?';
                $('cm-sub').textContent = a.status === 'confirm'
                    ? 'The MFR category and the eBay categories do not match by name. Confirm the right one once and it is remembered.'
                    : 'Change which eBay category this MFR category uses.';
                $('cm-search').value = '';
                $('cm-results').innerHTML = '';
                renderSuggestions();
                refreshPick();
                $('csp-modal').classList.add('is-open');
                $('csp-modal').setAttribute('aria-hidden', 'false');
            }

            function closeModal(skip) {
                if (skip && S.modalCat) S.skipped.add(S.modalCat.key);
                $('csp-modal').classList.remove('is-open');
                $('csp-modal').setAttribute('aria-hidden', 'true');
            }

            let treeTimer = null;
            function searchTree(q) {
                clearTimeout(treeTimer);
                const box = $('cm-results');
                q = q.trim();
                if (q.length < 2) { box.innerHTML = ''; return; }
                box.innerHTML = '<div class="csp-note">Searching the eBay tree…</div>';
                treeTimer = setTimeout(() => {
                    fetch(URLS.tree + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                        .then(r => r.json().catch(() => null))
                        .then(j => {
                            if (!j || j.success === false) { box.innerHTML = '<div class="csp-note">' + esc((j && j.message) || 'The eBay categories could not be loaded.') + '</div>'; return; }
                            const list = (j.results || []).slice(0, 40);
                            box.innerHTML = list.length
                                ? list.map(c => item(c.path, 'eBay category ' + c.id, c.id)).join('')
                                : '<div class="csp-note">No eBay category found for that.</div>';
                        })
                        .catch(() => { box.innerHTML = '<div class="csp-note">The search failed. Try again.</div>'; });
                }, 280);
            }

            function saveMatch() {
                if (!S.pick || !S.modalCat) return;
                const cat = S.modalCat;
                const btn = $('cm-save');
                btn.disabled = true;
                $('cm-msg').className = 'cm-msg';
                $('cm-msg').textContent = 'Saving…';
                post(URLS.map, { mfr_name: cat.name, ebay_path: S.pick.path, ebay_category_id: S.pick.id || '' }).then(j => {
                    S.maps[cat.key] = j.map;
                    analyzeAll();
                    closeModal(false);
                    activate(cat.key, false);
                    toast('Matched “' + cat.name + '” with ' + leaf(S.pick.path));
                }).catch(e => {
                    $('cm-msg').className = 'cm-msg err';
                    $('cm-msg').textContent = e.message || 'Could not save.';
                    btn.disabled = false;
                });
            }

            function unmatch(cat) {
                post(URLS.unmap, { mfr_name: cat.name }).then(() => {
                    delete S.maps[cat.key];
                    S.skipped.delete(cat.key);
                    analyzeAll();
                    activate(cat.key, true);
                    toast('Match removed for “' + cat.name + '”');
                }).catch(e => toast(e.message || 'Could not remove the match.'));
            }

            function reviewNext() {
                const pending = S.cats.filter(c => S.an[c.key].status === 'confirm');
                if (!pending.length) return;
                const idx = pending.findIndex(c => c.key === S.active);
                const next = pending[(idx + 1) % pending.length];
                S.skipped.delete(next.key);
                activate(next.key, true);
            }

            // ---- wiring ----
            document.addEventListener('click', e => {
                const tab = e.target.closest('#csp-tabs .csp-tab');
                if (tab) { activate(tab.dataset.key, true); return; }
                if (e.target.closest('#csp-change') || e.target.closest('#csp-change2')) {
                    const cat = S.cats.find(c => c.key === S.active);
                    if (cat) openModal(cat);
                    return;
                }
                if (e.target.closest('#csp-unmatch')) {
                    const cat = S.cats.find(c => c.key === S.active);
                    if (cat) unmatch(cat);
                    return;
                }
                const pick = e.target.closest('#csp-modal .cm-item');
                if (pick) { S.pick = { path: pick.dataset.path, id: pick.dataset.id || '' }; refreshPick(); return; }
                if (e.target === $('csp-modal')) closeModal(true);
            });
            $('csp-tab-search').addEventListener('input', e => { S.tabQ = e.target.value; renderTabs(); });
            $('csp-review').addEventListener('click', reviewNext);
            $('cm-later').addEventListener('click', () => closeModal(true));
            $('cm-save').addEventListener('click', saveMatch);
            $('cm-search').addEventListener('input', e => searchTree(e.target.value));
            document.addEventListener('input', e => { if (e.target && e.target.id === 'csp-grid-search') gridSearch(e.target.value); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && $('csp-modal').classList.contains('is-open')) closeModal(true); });

            function fail(text) {
                $('csp-tabs').innerHTML = '';
                $('csp-panel').innerHTML = '<div class="csp-empty"><div class="big">😵</div><h3>Could not load</h3>' + esc(text) + '</div>';
            }

            Promise.all([
                fetch(URLS.data, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(r => r.json()),
                fetch(URLS.rows, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(r => r.json()),
            ]).then(([cats, ebay]) => {
                if (!cats || cats.success === false) throw new Error('The MFR categories could not be loaded.');
                if (!ebay || ebay.status !== 200) throw new Error((ebay && ebay.message) || 'Ebay1 Data could not be loaded.');
                S.cats = cats.categories || [];
                S.maps = cats.maps || {};
                indexEbay(ebay.data || []);
                analyzeAll();
                S.ready = true;
                const first = S.cats.find(c => S.an[c.key].status === 'confirm') || S.cats[0];
                S.active = first ? first.key : null;
                renderTabs();
                renderPanel();
                if (first && S.an[first.key].status === 'confirm') openModal(first);
            }).catch(err => fail(err.message || 'Something went wrong.'));
        })();
    </script>
@endsection
