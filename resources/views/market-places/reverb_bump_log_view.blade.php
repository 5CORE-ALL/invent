@extends('layouts.vertical', ['title' => 'Reverb Bump - History & Monitor', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title { font-size: 11px; font-weight: 600; white-space: normal; }
        .tabulator .tabulator-cell { font-size: 12px; }
        .rb-pill { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; color: #fff; }
        .rb-ok { background: #198754; } .rb-err { background: #dc3545; } .rb-none { background: #6c757d; } .rb-warn { background: #fd7e14; }
        .rb-null { color: #adb5bd; }
        /* Same green / red / grey dots as the rolling-history dots on the analytics pages (.summary-trend-dot in head-css) */
        .rb-dot.summary-trend-dot { width: 10px !important; height: 10px !important; min-width: 10px !important; min-height: 10px !important; margin: 0 2px !important; vertical-align: middle; cursor: default; }
        .rb-dot.skip { background: #f59e0b; }
        .rb-dots { white-space: nowrap; }
        .rb-dot-label { margin-left: 4px; vertical-align: middle; }
    </style>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Reverb Bump - History & Monitor',
        'sub_title' => 'Change log of every Bump % update, plus the SKUs that need a follow-up',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                    <h4 class="mb-0 me-3">Reverb Bump — History &amp; Monitor</h4>
                    <a href="{{ route('reverb.bump.raw') }}" class="btn btn-sm btn-outline-secondary"><i class="fa fa-arrow-left"></i> Back to /reverb-bump</a>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="reload-btn"><i class="fa fa-rotate"></i> Reload</button>
                    <button type="button" class="btn btn-sm btn-success ms-auto" id="export-btn"><i class="fa fa-file-csv"></i> Export CSV</button>
                </div>
                <div class="small mb-2">
                    <span class="summary-trend-dot rb-dot up"></span> ok / up
                    <span class="summary-trend-dot rb-dot down ms-2"></span> failed / down
                    <span class="summary-trend-dot rb-dot skip ms-2"></span> skipped (exception)
                    <span class="summary-trend-dot rb-dot none ms-2"></span> none / no change
                    <span class="text-muted ms-2">— hover a dot for details. History column = last 8 pushes per SKU (oldest → newest).</span>
                </div>
                <div class="small text-muted mb-2" id="summary-line"></div>

                <ul class="nav nav-tabs" id="logTabs">
                    <li class="nav-item"><a class="nav-link active" href="#" data-tab="no_sales">No sales — fix price / images / title <span class="badge bg-danger" id="cnt-no_sales">0</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="#" data-tab="move_to_low">Selling — move 5% → 2% <span class="badge bg-success" id="cnt-move_to_low">0</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="#" data-tab="monitor">Monitor (views / sales since change) <span class="badge bg-secondary" id="cnt-monitor">0</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="#" data-tab="history">Change log <span class="badge bg-secondary" id="cnt-history">0</span></a></li>
                </ul>
                <div class="small text-muted mt-2" id="tab-help"></div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="log-table" style="height: calc(100vh - 330px);"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        (function () {
            const URL_DATA = @json(route('reverb.bump.log.data'));
            let payload = { history: [], monitor: [], settings: {} };
            let table = null;
            let tab = 'no_sales';

            const esc = v => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
            const dash = v => (v === null || v === undefined || v === '') ? '<span class="rb-null">—</span>' : esc(v);
            const money = c => (c.getValue() === null || c.getValue() === undefined) ? dash(null) : Number(c.getValue()).toFixed(2);

            const HELP = {
                no_sales: 'Bump was changed at least N days ago (default 14) and the SKU still has 0 sales in the last 30 days → check price, images and title.',
                move_to_low: 'Last update set a bump above 2% and the SKU is now selling → run Sold vs Bump again (Apply, then Push S Bump%) to move it to 2%. The weekly auto-run does this for you when enabled.',
                monitor: 'Every SKU with a bump update: views and sales when the bump was set vs now.',
                history: 'Every fill, push and exception skip (latest 5000), newest first.',
            };

            function linkFormatter(cell) {
                const d = cell.getRow().getData();
                const t = esc(cell.getValue());
                return d.listing_id ? '<a href="https://reverb.com/item/' + encodeURIComponent(d.listing_id) + '" target="_blank" rel="noopener">' + t + '</a>' : t;
            }
            const pill = (txt, cls) => '<span class="rb-pill ' + cls + '">' + esc(txt) + '</span>';

            // dot kinds: up = green, down = red, none = grey, skip = amber
            const dot = (kind, tip) => '<span class="summary-trend-dot rb-dot ' + kind + '" title="' + esc(tip) + '"></span>';
            const DOT_OF_STATUS = { ok: 'up', failed: 'down', skipped: 'skip' };
            const trendKind = (now, then) => {
                if (now === null || now === undefined || then === null || then === undefined) return 'none';
                return now > then ? 'up' : (now < then ? 'down' : 'flat');
            };
            const trendDot = (now, then, what) => {
                const kind = trendKind(now, then);
                if (kind === 'none') return dot('none', 'No ' + what + ' baseline');
                const d = Number(now) - Number(then);
                return dot(kind, what + ': ' + then + ' → ' + now + (d ? ' (' + (d > 0 ? '+' : '') + d + ')' : ' (same)'));
            };

            // last results per SKU (newest last) from the change log → the dots shown in the "History" column
            function historyBySku() {
                const map = {};
                (payload.history || []).forEach(h => {          // history is newest first
                    if (h.action === 'fill') return;             // a push (or skip) is the outcome that matters
                    (map[h.sku] = map[h.sku] || []).push(h);
                });
                Object.keys(map).forEach(k => { map[k] = map[k].slice(0, 8).reverse(); });
                return map;
            }
            function historyDotsFormatter(cell) {
                const list = (historyMap[cell.getRow().getData().sku] || []);
                if (!list.length) return '<span class="rb-dots">' + dot('none', 'No push history yet') + '</span>';
                return '<span class="rb-dots">' + list.map(h =>
                    dot(DOT_OF_STATUS[h.status] || 'none',
                        h.at + ' · ' + h.action + ' ' + (h.new_bump || '') + ' · ' + h.status + (h.reason ? ' · ' + h.reason : '') + (h.message && h.status === 'failed' ? ' · ' + h.message : ''))
                ).join('') + '</span>';
            }
            let historyMap = {};

            const monitorCols = [
                { title: 'SKU', field: 'sku', frozen: true, width: 170, headerFilter: 'input', formatter: linkFormatter },
                { title: 'Title', field: 'title', width: 240, headerFilter: 'input' },
                { title: 'Changed', field: 'changed_at', width: 140 },
                { title: 'Days', field: 'days_since', width: 60, hozAlign: 'right' },
                { title: 'Bump set', field: 'new_bump', width: 80, hozAlign: 'right' },
                { title: 'Live Bump%', field: 'live_bump', width: 90, hozAlign: 'right', formatter: c => dash(c.getValue()) },
                { title: 'Sold then', field: 'sold_then', width: 80, hozAlign: 'right' },
                {
                    title: 'Sold now (L30)', field: 'sold_now', width: 105, hozAlign: 'right',
                    formatter: c => { const d = c.getRow().getData(); return trendDot(d.sold_now, d.sold_then, 'Sold') + '<span class="rb-dot-label">' + esc(c.getValue()) + '</span>'; },
                },
                { title: 'Views then', field: 'views_then', width: 85, hozAlign: 'right', formatter: c => dash(c.getValue()) },
                {
                    title: 'Views now', field: 'views_now', width: 100, hozAlign: 'right',
                    formatter: c => { const d = c.getRow().getData(); return trendDot(d.views_now, d.views_then, 'Views') + '<span class="rb-dot-label">' + (c.getValue() === null || c.getValue() === undefined ? '—' : esc(c.getValue())) + '</span>'; },
                },
                {
                    title: 'Views Δ', field: 'views_delta', width: 80, hozAlign: 'right',
                    formatter: c => { const v = c.getValue(); return v === null ? dash(null) : (v > 0 ? '+' : '') + v; },
                },
                { title: 'History', field: 'sku', width: 120, headerSort: false, download: false, formatter: historyDotsFormatter },
                { title: 'Price', field: 'price', width: 80, hozAlign: 'right', formatter: money },
                {
                    title: 'Verdict', field: 'review', width: 140,
                    formatter: c => c.getValue() === 'no_sales' ? pill('fix price/images/title', 'rb-err')
                        : c.getValue() === 'move_to_low' ? pill('move to 2%', 'rb-ok') : pill('watching', 'rb-none'),
                },
            ];

            const historyCols = [
                { title: 'When', field: 'at', width: 140 },
                { title: 'Source', field: 'source', width: 70 },
                { title: 'Action', field: 'action', width: 70, headerFilter: 'list', headerFilterParams: { values: ['', 'fill', 'push', 'skip'], clearable: true } },
                {
                    title: 'Status', field: 'status', width: 95,
                    headerFilter: 'list', headerFilterParams: { values: ['', 'ok', 'failed', 'skipped'], clearable: true },
                    formatter: c => {
                        const s = c.getValue();
                        const d = c.getRow().getData();
                        return dot(DOT_OF_STATUS[s] || 'none', d.message || d.reason || s) + '<span class="rb-dot-label">' + esc(s) + '</span>';
                    },
                },
                { title: 'SKU', field: 'sku', width: 170, headerFilter: 'input' },
                { title: 'Sold', field: 'sold', width: 55, hozAlign: 'right' },
                { title: 'INV', field: 'inv', width: 60, hozAlign: 'right' },
                { title: 'Price', field: 'price', width: 80, hozAlign: 'right', formatter: money },
                { title: 'Old S Bump%', field: 'old_s_bump', width: 90, hozAlign: 'right', formatter: c => dash(c.getValue()) },
                { title: 'Old live', field: 'old_live_bump', width: 70, hozAlign: 'right', formatter: c => dash(c.getValue()) },
                { title: 'New Bump', field: 'new_bump', width: 80, hozAlign: 'right', formatter: c => dash(c.getValue()) },
                { title: 'Reason', field: 'reason', width: 260, headerFilter: 'input', formatter: c => dash(c.getValue()) },
                { title: 'Message', field: 'message', width: 240, formatter: c => dash(c.getValue()) },
                { title: 'Run', field: 'run_id', width: 150, headerFilter: 'input' },
            ];

            function rowsForTab() {
                if (tab === 'history') return payload.history || [];
                const mon = (payload.monitor || []).map(r => Object.assign({}, r, {
                    views_delta: (r.views_then !== null && r.views_then !== undefined && r.views_now !== null && r.views_now !== undefined)
                        ? Number(r.views_now) - Number(r.views_then) : null,
                }));
                if (tab === 'monitor') return mon;
                return mon.filter(r => r.review === tab);
            }

            function render() {
                historyMap = historyBySku();
                const rows = rowsForTab();
                document.getElementById('tab-help').textContent = HELP[tab];
                const cols = tab === 'history' ? historyCols : monitorCols;
                if (table) table.destroy();
                table = new Tabulator('#log-table', {
                    data: rows,
                    columns: cols,
                    layout: 'fitData',
                    height: '100%',
                    pagination: true,
                    paginationSize: 100,
                    paginationSizeSelector: [50, 100, 250, 500, true],
                    movableColumns: true,
                    placeholder: 'Nothing here yet',
                });
                const mon = payload.monitor || [];
                document.getElementById('cnt-no_sales').textContent = mon.filter(r => r.review === 'no_sales').length;
                document.getElementById('cnt-move_to_low').textContent = mon.filter(r => r.review === 'move_to_low').length;
                document.getElementById('cnt-monitor').textContent = mon.length;
                document.getElementById('cnt-history').textContent = (payload.history || []).length;
                const days = payload.settings && payload.settings.review_after_days;
                document.getElementById('summary-line').textContent =
                    (days ? 'No-sales review after ' + days + ' days · ' : '') +
                    (payload.settings && payload.settings.auto_enabled ? 'weekly auto-run ON' : 'weekly auto-run OFF');
            }

            async function load() {
                const res = await fetch(URL_DATA, { headers: { 'Accept': 'application/json' } });
                payload = await res.json();
                if (payload.table_ready === false) {
                    document.getElementById('tab-help').textContent = 'reverb_bump_change_log table is missing — run php artisan migrate';
                }
                render();
            }

            document.querySelectorAll('#logTabs [data-tab]').forEach(a => a.addEventListener('click', e => {
                e.preventDefault();
                document.querySelectorAll('#logTabs .nav-link').forEach(x => x.classList.remove('active'));
                a.classList.add('active');
                tab = a.dataset.tab;
                render();
            }));
            document.getElementById('reload-btn').addEventListener('click', load);
            document.getElementById('export-btn').addEventListener('click', () => table && table.download('csv', 'reverb_bump_' + tab + '.csv'));
            document.addEventListener('DOMContentLoaded', load);
        })();
    </script>
@endsection
