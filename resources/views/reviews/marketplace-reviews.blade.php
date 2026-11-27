@extends('layouts.vertical', ['title' => 'Reviews', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #rv-page {
            --rv-bg: #0f0b1e;
            --rv-card: rgba(255, 255, 255, .06);
            --rv-line: rgba(255, 255, 255, .12);
            --rv-text: #f4f1ff;
            --rv-mute: #a79fc7;
            --rv-grad: linear-gradient(120deg, #8b5cf6 0%, #ec4899 55%, #22d3ee 100%);
            background: radial-gradient(1200px 500px at 0% -10%, rgba(139, 92, 246, .35), transparent 60%),
                        radial-gradient(900px 500px at 100% 0%, rgba(236, 72, 153, .28), transparent 55%),
                        var(--rv-bg);
            color: var(--rv-text);
            border-radius: 24px;
            padding: 28px;
            margin-bottom: 24px;
        }
        #rv-page .rv-hero { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
        #rv-page h1 { font-size: 2.1rem; font-weight: 800; margin: 0; letter-spacing: -.02em; }
        #rv-page h1 span { background: var(--rv-grad); -webkit-background-clip: text; background-clip: text; color: transparent; }
        #rv-page .rv-sub { color: var(--rv-mute); margin: 4px 0 0; }
        #rv-page .rv-actions { display: flex; flex-wrap: wrap; gap: 10px; }
        #rv-page .rv-btn {
            border: 1px solid var(--rv-line); background: var(--rv-card); color: var(--rv-text);
            border-radius: 999px; padding: 9px 18px; font-weight: 600; font-size: .86rem; cursor: pointer;
            transition: transform .15s ease, background .15s ease, box-shadow .15s ease; backdrop-filter: blur(8px);
        }
        #rv-page .rv-btn:hover { transform: translateY(-2px); background: rgba(255, 255, 255, .12); }
        #rv-page .rv-btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }
        #rv-page .rv-btn.primary { background: var(--rv-grad); border-color: transparent; box-shadow: 0 8px 28px rgba(236, 72, 153, .35); }
        #rv-page .rv-btn.primary:hover { box-shadow: 0 10px 34px rgba(236, 72, 153, .5); }

        #rv-page .rv-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 14px; margin-bottom: 20px; }
        #rv-page .rv-stat { background: var(--rv-card); border: 1px solid var(--rv-line); border-radius: 18px; padding: 16px 18px; backdrop-filter: blur(10px); }
        #rv-page .rv-stat .k { color: var(--rv-mute); font-size: .74rem; text-transform: uppercase; letter-spacing: .08em; font-weight: 700; }
        #rv-page .rv-stat .v { font-size: 1.9rem; font-weight: 800; line-height: 1.15; margin-top: 4px; }
        #rv-page .rv-stat .v.grad { background: var(--rv-grad); -webkit-background-clip: text; background-clip: text; color: transparent; }

        #rv-page .rv-chips { display: flex; gap: 8px; overflow-x: auto; padding: 4px 2px 12px; scrollbar-width: thin; }
        #rv-page .rv-chip {
            flex: 0 0 auto; border: 1px solid var(--rv-line); background: var(--rv-card); color: var(--rv-text);
            border-radius: 999px; padding: 8px 14px; font-size: .84rem; font-weight: 600; cursor: pointer; white-space: nowrap;
            transition: all .15s ease;
        }
        #rv-page .rv-chip:hover { border-color: #c4b5fd; }
        #rv-page .rv-chip.active { background: var(--rv-grad); border-color: transparent; box-shadow: 0 6px 22px rgba(139, 92, 246, .45); }
        #rv-page .rv-chip .n { margin-left: 6px; background: rgba(0, 0, 0, .28); border-radius: 999px; padding: 1px 8px; font-size: .74rem; }
        #rv-page .rv-chip .auto { margin-left: 4px; }

        #rv-page .rv-filters { display: flex; flex-wrap: wrap; gap: 10px; margin: 6px 0 14px; }
        #rv-page .rv-filters input, #rv-page .rv-filters select {
            background: var(--rv-card); border: 1px solid var(--rv-line); color: var(--rv-text);
            border-radius: 14px; padding: 9px 14px; font-size: .86rem; outline: none;
        }
        #rv-page .rv-filters input { min-width: 260px; }
        #rv-page .rv-filters input:focus, #rv-page .rv-filters select:focus { border-color: #a78bfa; box-shadow: 0 0 0 3px rgba(167, 139, 250, .25); }
        #rv-page .rv-filters option { color: #111; }

        #rv-page .rv-note { background: rgba(250, 204, 21, .1); border: 1px solid rgba(250, 204, 21, .35); color: #fde68a; border-radius: 14px; padding: 10px 14px; font-size: .84rem; margin-bottom: 14px; }
        #rv-page .rv-progress { display: none; background: var(--rv-card); border: 1px solid var(--rv-line); border-radius: 14px; padding: 10px 14px; margin-bottom: 14px; font-size: .86rem; }
        #rv-page .rv-progress .bar { height: 6px; border-radius: 999px; background: rgba(255, 255, 255, .12); overflow: hidden; margin-top: 8px; }
        #rv-page .rv-progress .bar i { display: block; height: 100%; width: 0; background: var(--rv-grad); transition: width .3s ease; }

        /* table */
        #rv-page .tabulator { background: transparent; border: 1px solid var(--rv-line); border-radius: 18px; color: var(--rv-text); font-size: .86rem; overflow: hidden; }
        #rv-page .tabulator .tabulator-header { background: rgba(255, 255, 255, .06); border-bottom: 1px solid var(--rv-line); color: var(--rv-mute); }
        #rv-page .tabulator .tabulator-header .tabulator-col { background: transparent; border-right: 0; }
        #rv-page .tabulator .tabulator-header .tabulator-col .tabulator-col-content { padding: 10px 10px; }
        #rv-page .tabulator .tabulator-col-title { font-size: .72rem; text-transform: uppercase; letter-spacing: .07em; font-weight: 700; }
        #rv-page .tabulator .tabulator-tableholder { background: transparent; }
        #rv-page .tabulator-row { background: transparent; border-bottom: 1px solid rgba(255, 255, 255, .06); min-height: 0; }
        #rv-page .tabulator-row:hover { background: rgba(255, 255, 255, .05) !important; }
        #rv-page .tabulator-row .tabulator-cell { border-right: 0; padding: 10px; white-space: normal; }
        #rv-page .tabulator-row.tabulator-selected { background: rgba(139, 92, 246, .25) !important; }
        #rv-page .tabulator-footer { background: rgba(255, 255, 255, .05); border-top: 1px solid var(--rv-line); color: var(--rv-mute); }
        #rv-page .tabulator-footer .tabulator-page { background: transparent; color: var(--rv-text); border-color: var(--rv-line); border-radius: 10px; }
        #rv-page .tabulator-footer .tabulator-page.active { background: var(--rv-grad); border-color: transparent; }
        #rv-page .tabulator-placeholder span { color: var(--rv-mute) !important; }
        #rv-page .tabulator-row.tabulator-group {
            background: linear-gradient(90deg, rgba(139, 92, 246, .22), rgba(236, 72, 153, .1)); border-bottom: 1px solid var(--rv-line);
            color: var(--rv-text); font-weight: 700; padding: 8px 12px;
        }
        #rv-page .tabulator-row.tabulator-group:hover { background: linear-gradient(90deg, rgba(139, 92, 246, .32), rgba(236, 72, 153, .16)) !important; }
        #rv-page .tabulator-row.tabulator-group span { color: inherit !important; margin-left: 0; }
        #rv-page .tabulator-row .tabulator-responsive-collapse { color: var(--rv-text); }

        .rv-grp { display: flex; align-items: center; gap: 12px; }
        .rv-grp img { width: 38px; height: 38px; border-radius: 10px; object-fit: cover; background: rgba(255, 255, 255, .1); }
        .rv-grp .sku { font-weight: 800; }
        .rv-grp .ttl { color: var(--rv-mute); font-weight: 500; font-size: .78rem; max-width: 520px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .rv-grp .meta { margin-left: auto; font-size: .8rem; color: #fcd34d; font-weight: 700; }
        .rv-pill { display: inline-block; border-radius: 999px; padding: 3px 11px; font-size: .74rem; font-weight: 700; border: 1px solid rgba(255, 255, 255, .18); background: rgba(255, 255, 255, .08); white-space: nowrap; }
        .rv-pill.pushed { background: rgba(52, 211, 153, .18); border-color: rgba(52, 211, 153, .5); color: #6ee7b7; }
        .rv-pill.pending { background: rgba(251, 191, 36, .16); border-color: rgba(251, 191, 36, .45); color: #fcd34d; }
        .rv-pill.failed { background: rgba(248, 113, 113, .18); border-color: rgba(248, 113, 113, .5); color: #fca5a5; cursor: help; }
        .rv-pill.skipped { color: #c4b5fd; }
        .rv-stars { color: #fbbf24; letter-spacing: 1px; font-size: .95rem; white-space: nowrap; }
        .rv-stars .off { color: rgba(255, 255, 255, .18); }
        .rv-title { font-weight: 700; margin-bottom: 2px; }
        .rv-body { color: #ddd6fe; line-height: 1.45; }
        .rv-verified { color: #6ee7b7; font-size: .72rem; font-weight: 700; }
        .rv-del { background: transparent; border: 0; color: #fca5a5; cursor: pointer; font-size: 1rem; opacity: .6; }
        .rv-del:hover { opacity: 1; }

        #rv-page .rv-modal-bg { position: fixed; inset: 0; background: rgba(5, 3, 15, .7); backdrop-filter: blur(6px); z-index: 2000; display: none; align-items: center; justify-content: center; padding: 16px; }
        #rv-page .rv-modal { background: #1a1333; border: 1px solid var(--rv-line); border-radius: 22px; width: min(520px, 100%); padding: 22px; box-shadow: 0 30px 80px rgba(0, 0, 0, .6); }
        #rv-page .rv-modal h3 { font-weight: 800; margin: 0 0 14px; }
        #rv-page .rv-modal label { display: block; font-size: .74rem; color: var(--rv-mute); text-transform: uppercase; letter-spacing: .06em; font-weight: 700; margin: 10px 0 4px; }
        #rv-page .rv-modal input, #rv-page .rv-modal select, #rv-page .rv-modal textarea {
            width: 100%; background: rgba(255, 255, 255, .07); border: 1px solid var(--rv-line); color: var(--rv-text); border-radius: 12px; padding: 9px 12px; outline: none;
        }
        #rv-page .rv-modal option { color: #111; }
        #rv-page .rv-modal .row2 { display: flex; gap: 10px; }
        #rv-page .rv-modal .foot { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Reviews', 'sub_title' => 'Marketplace reviews'])

    <div id="rv-page">
        <div class="rv-hero">
            <div>
                <h1>Reviews <span>drop</span> ✨</h1>
                <p class="rv-sub">Every review of every product from every marketplace, then one tap to push them to Shopify.</p>
            </div>
            <div class="rv-actions">
                <button class="rv-btn" id="rv-add">＋ Add review</button>
                <button class="rv-btn" id="rv-import">⬆ Import CSV</button>
                <button class="rv-btn" id="rv-template">⬇ CSV template</button>
                <button class="rv-btn" id="rv-fetch">⚡ Fetch reviews</button>
                <button class="rv-btn primary" id="rv-push">🚀 Push to Shopify</button>
                <input type="file" id="rv-file" accept=".csv,text/csv" style="display:none">
            </div>
        </div>

        @unless ($tableReady)
            <div class="rv-note">The reviews table does not exist yet. Run <b>php artisan migrate</b> on the server.</div>
        @endunless
        @unless ($pushConfigured)
            <div class="rv-note">Push to Shopify is not set up: {{ $pushHint }}</div>
        @endunless

        <div class="rv-stats">
            <div class="rv-stat"><div class="k">Reviews</div><div class="v grad" id="st-total">0</div></div>
            <div class="rv-stat"><div class="k">Products</div><div class="v" id="st-products">0</div></div>
            <div class="rv-stat"><div class="k">Avg rating</div><div class="v" id="st-avg">—</div></div>
            <div class="rv-stat"><div class="k">On Shopify</div><div class="v" id="st-pushed" style="color:#6ee7b7">0</div></div>
            <div class="rv-stat"><div class="k">Waiting</div><div class="v" id="st-pending" style="color:#fcd34d">0</div></div>
            <div class="rv-stat"><div class="k">Failed</div><div class="v" id="st-failed" style="color:#fca5a5">0</div></div>
        </div>

        <div class="rv-chips" id="rv-chips"></div>

        <div class="rv-filters">
            <input type="search" id="rv-q" placeholder="🔍 Search SKU, review text, reviewer…" autocomplete="off">
            <select id="rv-rating">
                <option value="0">All stars</option>
                <option value="5">⭐ 5</option><option value="4">⭐ 4</option><option value="3">⭐ 3</option>
                <option value="2">⭐ 2</option><option value="1">⭐ 1</option>
            </select>
            <select id="rv-shop">
                <option value="">Shopify: any</option>
                <option value="pending">Waiting to push</option>
                <option value="pushed">On Shopify</option>
                <option value="failed">Failed</option>
                <option value="skipped">Skipped</option>
            </select>
            <select id="rv-group">
                <option value="sku">Group by product</option>
                <option value="">No grouping</option>
            </select>
        </div>

        <div class="rv-progress" id="rv-progress"><span id="rv-progress-text"></span><div class="bar"><i id="rv-progress-bar"></i></div></div>

        <div id="rv-table"></div>

        <div class="rv-modal-bg" id="rv-modal-bg">
            <div class="rv-modal">
                <h3>＋ Add a review</h3>
                <label>SKU</label><input id="rv-m-sku" placeholder="e.g. SS HD 2 PK 4.5 FT GR WB">
                <div class="row2">
                    <div style="flex:1"><label>Marketplace</label>
                        <select id="rv-m-mp">
                            @foreach ($marketplaces as $m)
                                <option value="{{ $m['slug'] }}">{{ $m['label'] }}</option>
                            @endforeach
                        </select></div>
                    <div style="width:110px"><label>Stars</label>
                        <select id="rv-m-rating"><option>5</option><option>4</option><option>3</option><option>2</option><option>1</option></select></div>
                </div>
                <label>Title</label><input id="rv-m-title" maxlength="255">
                <label>Review</label><textarea id="rv-m-body" rows="4"></textarea>
                <div class="row2">
                    <div style="flex:1"><label>Reviewer</label><input id="rv-m-name"></div>
                    <div style="flex:1"><label>Date</label><input id="rv-m-date" type="date"></div>
                </div>
                <div class="foot">
                    <button class="rv-btn" id="rv-m-cancel">Cancel</button>
                    <button class="rv-btn primary" id="rv-m-save">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function () {
            const MARKETPLACES = @json($marketplaces);
            const URLS = {
                data: @json(route('mpreviews.data')),
                fetch: @json(route('mpreviews.fetch')),
                push: @json(route('mpreviews.push')),
                import: @json(route('mpreviews.import')),
                store: @json(route('mpreviews.store')),
                destroy: @json(url('marketplace-reviews')),
            };
            const CSRF = document.querySelector('meta[name="csrf-token"]').content;
            const state = { marketplace: 'all', stats: null };
            let table = null;
            let busy = false;

            const $ = (id) => document.getElementById(id);
            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

            async function api(url, opts = {}) {
                const res = await fetch(url, Object.assign({
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                }, opts));
                let json = {};
                try { json = await res.json(); } catch (e) { /* not json */ }
                if (!res.ok) { throw new Error(json.message || ('HTTP ' + res.status)); }
                return json;
            }
            const post = (url, body) => api(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(body || {}),
            });

            function progress(text, pct) {
                const box = $('rv-progress');
                box.style.display = text ? 'block' : 'none';
                $('rv-progress-text').textContent = text || '';
                $('rv-progress-bar').style.width = (pct || 0) + '%';
            }
            function setBusy(v) { busy = v; ['rv-fetch', 'rv-push', 'rv-import', 'rv-add'].forEach((id) => $(id).disabled = v); }

            const stars = (n) => {
                n = parseInt(n || 0, 10);
                if (!n) { return '<span class="rv-pill skipped">no rating</span>'; }
                return '<span class="rv-stars">' + '★'.repeat(n) + '<span class="off">' + '★'.repeat(5 - n) + '</span></span>';
            };

            function buildTable() {
                table = new Tabulator('#rv-table', {
                    layout: 'fitColumns',
                    height: '640px',
                    placeholder: 'No reviews yet — hit ⚡ Fetch reviews, import a CSV, or add one by hand.',
                    selectableRows: true,
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [50, 100, 250, 500],
                    groupBy: 'sku',
                    groupStartOpen: true,
                    groupToggleElement: 'header',
                    groupHeader: function (value, count, data) {
                        const first = data[0] || {};
                        const rated = data.filter((d) => d.rating);
                        const avg = rated.length ? (rated.reduce((a, d) => a + d.rating, 0) / rated.length).toFixed(1) : '—';
                        const img = first.image ? '<img src="' + esc(first.image) + '" alt="">' : '<img alt="">';
                        return '<div class="rv-grp">' + img +
                            '<div><div class="sku">' + esc(value) + '</div><div class="ttl">' + esc(first.title_product || '') + '</div></div>' +
                            '<div class="meta">★ ' + avg + ' · ' + count + ' review' + (count === 1 ? '' : 's') + '</div></div>';
                    },
                    columns: [
                        { formatter: 'rowSelection', titleFormatter: 'rowSelection', hozAlign: 'center', headerSort: false, width: 44, cssClass: 'rv-sel' },
                        { title: 'Marketplace', field: 'marketplace_label', width: 130, formatter: (c) => '<span class="rv-pill">' + esc(c.getValue()) + '</span>' },
                        { title: 'Stars', field: 'rating', width: 110, formatter: (c) => stars(c.getValue()) },
                        {
                            title: 'Review', field: 'body', minWidth: 320, formatter: function (c) {
                                const d = c.getRow().getData();
                                return (d.title ? '<div class="rv-title">' + esc(d.title) + '</div>' : '') +
                                    '<div class="rv-body">' + esc(d.body || '') + '</div>';
                            }
                        },
                        {
                            title: 'Reviewer', field: 'reviewer', width: 150, formatter: function (c) {
                                const d = c.getRow().getData();
                                return esc(d.reviewer || '—') + (d.verified ? '<div class="rv-verified">✔ verified buyer</div>' : '');
                            }
                        },
                        { title: 'Date', field: 'reviewed_at', width: 105, formatter: (c) => esc(c.getValue() || '—') },
                        { title: 'Via', field: 'source', width: 80, formatter: (c) => '<span class="rv-pill">' + esc(c.getValue()) + '</span>' },
                        {
                            title: 'Shopify', field: 'shopify_status', width: 120, formatter: function (c) {
                                const d = c.getRow().getData();
                                const label = { pending: 'waiting', pushed: 'on Shopify ✓', failed: 'failed', skipped: 'skipped' }[d.shopify_status] || d.shopify_status;
                                const tip = d.shopify_error ? ' title="' + esc(d.shopify_error) + '"' : (d.shopify_pushed_at ? ' title="' + esc(d.shopify_pushed_at) + '"' : '');
                                return '<span class="rv-pill ' + esc(d.shopify_status) + '"' + tip + '>' + esc(label) + '</span>';
                            }
                        },
                        {
                            title: '', width: 48, headerSort: false, hozAlign: 'center',
                            formatter: () => '<button class="rv-del" title="Delete">🗑</button>',
                            cellClick: async function (e, cell) {
                                if (!e.target.closest('.rv-del')) { return; }
                                if (!confirm('Delete this review from the app? (It is not removed from the marketplace or Shopify.)')) { return; }
                                await api(URLS.destroy + '/' + cell.getRow().getData().id, { method: 'DELETE' });
                                load();
                            }
                        },
                    ],
                });
            }

            function renderChips() {
                const per = (state.stats && state.stats.per_marketplace) || {};
                let total = 0;
                Object.values(per).forEach((p) => total += p.count);
                let html = '<button class="rv-chip ' + (state.marketplace === 'all' ? 'active' : '') + '" data-mp="all">🌍 All marketplaces<span class="n">' + total + '</span></button>';
                MARKETPLACES.forEach((m) => {
                    const c = (per[m.slug] || {}).count || 0;
                    html += '<button class="rv-chip ' + (state.marketplace === m.slug ? 'active' : '') + '" data-mp="' + esc(m.slug) + '">' +
                        esc(m.label) + (m.auto ? '<span class="auto" title="Fetched automatically">⚡</span>' : '') +
                        '<span class="n">' + c + '</span></button>';
                });
                $('rv-chips').innerHTML = html;
            }

            function renderStats() {
                const s = state.stats || {};
                $('st-total').textContent = (s.total || 0).toLocaleString();
                $('st-products').textContent = (s.products || 0).toLocaleString();
                $('st-avg').textContent = s.avg ? s.avg + ' ★' : '—';
                $('st-pushed').textContent = (s.pushed || 0).toLocaleString();
                $('st-pending').textContent = (s.pending || 0).toLocaleString();
                $('st-failed').textContent = (s.failed || 0).toLocaleString();
            }

            async function load() {
                const qs = new URLSearchParams({
                    marketplace: state.marketplace,
                    rating: $('rv-rating').value,
                    shopify: $('rv-shop').value,
                    q: $('rv-q').value.trim(),
                });
                try {
                    const res = await api(URLS.data + '?' + qs.toString());
                    state.stats = res.stats;
                    renderStats();
                    renderChips();
                    table.setData(res.rows || []);
                    if (res.stats && res.stats.truncated) { progress('Showing the newest 6,000 reviews. Narrow it with a marketplace or search.', 0); }
                } catch (e) {
                    progress('Could not load reviews: ' + e.message, 0);
                }
            }

            async function runFetch() {
                const targets = state.marketplace === 'all'
                    ? MARKETPLACES.filter((m) => m.auto).map((m) => m.slug)
                    : [state.marketplace];
                const allowed = targets.filter((slug) => (MARKETPLACES.find((m) => m.slug === slug) || {}).auto);
                if (!allowed.length) {
                    alert('This marketplace has no automatic review source (its API only returns a star average). Use ⬆ Import CSV or ＋ Add review.');
                    return;
                }
                setBusy(true);
                let newTotal = 0; const errs = [];
                try {
                    for (const slug of allowed) {
                        let offset = 0;
                        while (offset !== null) {
                            const r = await post(URLS.fetch, { marketplace: slug, offset });
                            newTotal += r.new;
                            (r.errors || []).forEach((e) => errs.push(slug + ' · ' + e));
                            const pct = r.total_skus ? Math.round((r.processed / r.total_skus) * 100) : 100;
                            progress('⚡ ' + slug + ': ' + r.processed + ' / ' + r.total_skus + ' products · ' + newTotal + ' new reviews', pct);
                            offset = r.next_offset;
                        }
                    }
                    progress('Done — ' + newTotal + ' new review' + (newTotal === 1 ? '' : 's') + (errs.length ? ' · ' + errs.length + ' product(s) had no reviews or an error (e.g. ' + errs[0] + ')' : ''), 100);
                } catch (e) {
                    progress('Fetch stopped: ' + e.message, 0);
                } finally {
                    setBusy(false);
                    load();
                }
            }

            async function runPush() {
                const selected = table.getSelectedData().map((d) => d.id);
                const s = state.stats || {};
                const scope = selected.length
                    ? selected.length + ' selected review(s)'
                    : 'all waiting reviews' + (state.marketplace === 'all' ? ' from every marketplace' : ' from ' + state.marketplace) + ' (' + (s.pending || 0) + ')';
                if (!confirm('Push ' + scope + ' to Shopify through Judge.me?\n\nThey will appear on the product pages as customer reviews.')) { return; }
                setBusy(true);
                let pushed = 0, failed = 0; const errs = [];
                let afterId = 0;
                try {
                    while (afterId !== null) {
                        const body = { after_id: afterId, include_failed: $('rv-shop').value === 'failed' };
                        if (selected.length) { body.ids = selected; } else { body.marketplace = state.marketplace; }
                        const r = await post(URLS.push, body);
                        pushed += r.pushed; failed += r.failed;
                        (r.errors || []).forEach((e) => errs.push(e));
                        progress('🚀 pushed ' + pushed + ' · failed ' + failed + ' · ' + r.remaining + ' to go', 50);
                        afterId = r.after_id;
                    }
                    progress('Done — ' + pushed + ' on Shopify' + (failed ? ', ' + failed + ' failed (hover the red tag). First error: ' + (errs[0] || '') : '') + '.', 100);
                } catch (e) {
                    progress('Push stopped: ' + e.message, 0);
                } finally {
                    setBusy(false);
                    load();
                }
            }

            // events
            $('rv-chips').addEventListener('click', (e) => {
                const b = e.target.closest('.rv-chip'); if (!b) { return; }
                state.marketplace = b.dataset.mp; renderChips(); load();
            });
            let t = null;
            $('rv-q').addEventListener('input', () => { clearTimeout(t); t = setTimeout(load, 350); });
            $('rv-rating').addEventListener('change', load);
            $('rv-shop').addEventListener('change', load);
            $('rv-group').addEventListener('change', (e) => table.setGroupBy(e.target.value || false));
            $('rv-fetch').addEventListener('click', () => { if (!busy) { runFetch(); } });
            $('rv-push').addEventListener('click', () => { if (!busy) { runPush(); } });

            $('rv-import').addEventListener('click', () => $('rv-file').click());
            $('rv-file').addEventListener('change', async (e) => {
                const file = e.target.files[0]; if (!file) { return; }
                const fd = new FormData(); fd.append('file', file);
                if (state.marketplace !== 'all') { fd.append('marketplace', state.marketplace); }
                setBusy(true);
                try {
                    const r = await api(URLS.import, { method: 'POST', body: fd });
                    progress('Imported ' + r.saved + ' new · ' + r.skipped + ' duplicates · ' + r.errors + ' rows skipped (missing sku or marketplace).', 100);
                } catch (err) { progress('Import failed: ' + err.message, 0); }
                e.target.value = ''; setBusy(false); load();
            });
            $('rv-template').addEventListener('click', () => {
                const csv = 'sku,marketplace,rating,title,body,reviewer,date,review_id,verified\n' +
                    'SS HD 2 PK 4.5 FT GR WB,ebay1,5,Great stands,Sturdy and easy to set up,Jamie R.,2026-11-02,,yes\n';
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
                a.download = 'reviews-template.csv'; a.click();
            });

            const modal = $('rv-modal-bg');
            $('rv-add').addEventListener('click', () => {
                if (state.marketplace !== 'all') { $('rv-m-mp').value = state.marketplace; }
                modal.style.display = 'flex';
            });
            $('rv-m-cancel').addEventListener('click', () => modal.style.display = 'none');
            $('rv-m-save').addEventListener('click', async () => {
                try {
                    await post(URLS.store, {
                        sku: $('rv-m-sku').value.trim(), marketplace: $('rv-m-mp').value, rating: parseInt($('rv-m-rating').value, 10),
                        title: $('rv-m-title').value, body: $('rv-m-body').value, reviewer: $('rv-m-name').value, date: $('rv-m-date').value || null,
                    });
                    modal.style.display = 'none';
                    ['rv-m-sku', 'rv-m-title', 'rv-m-body', 'rv-m-name', 'rv-m-date'].forEach((id) => $(id).value = '');
                    load();
                } catch (e) { alert(e.message); }
            });

            buildTable();
            renderChips();
            table.on('tableBuilt', load);
        })();
    </script>
@endsection
