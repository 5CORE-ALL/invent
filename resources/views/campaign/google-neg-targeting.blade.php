@extends('layouts.vertical', ['title' => 'Google Ads - N Targeting'])

@section('css')
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .gnt .tabulator { font-size: 13px; border: 1px solid #dee2e6; border-radius: 0 0 8px 8px; }
    .gnt .tabulator .tabulator-header { background: #dbeafe; border-bottom: 1px solid #93c5fd; font-weight: 600; position: sticky; top: var(--tz-topbar-height, 70px); z-index: 24; }
    .gnt .tabulator .tabulator-header .tabulator-col .tabulator-col-title { font-size: 12.5px; text-align: center; padding: 6px 4px; white-space: normal; line-height: 1.2; }
    .gnt .tabulator-row { min-height: 34px; }
    .gnt .tabulator-row:hover { background-color: #f8fafc !important; }
    .gnt .tabulator-cell { padding: 5px !important; }
    .gnt .gnt-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; vertical-align: middle; box-shadow: 0 0 0 2px rgba(0,0,0,.06); }
    .gnt .gnt-badge { font-size: .72rem; cursor: pointer; user-select: none; border: 2px solid transparent; }
    .gnt .gnt-badge.is-active { border-color: #111827; box-shadow: 0 0 0 2px #fde68a; }
    .gnt .kwc-btn { display: inline-flex; align-items: center; gap: 6px; padding: 2px 10px; border-radius: 6px; background: #fff; border: 1px solid #1d4ed8; color: #1d4ed8; font-size: 12px; font-weight: 700; cursor: pointer; }
    .gnt .kwc-btn:hover { background: #eff6ff; }
    .gnt .kwc-btn .kwc-n { background: #1d4ed8; color: #fff; border-radius: 999px; padding: 0 7px; font-size: 11px; line-height: 18px; }
    .gnt .kwc-btn.kwc-empty .kwc-n { background: #94a3b8; }
    .gnt .gnt-count { display: inline-block; padding: 2px 10px; border-radius: 999px; color: #fff; font-size: 12px; font-weight: 700; cursor: pointer; min-width: 34px; }
    .gnt-item { display: flex; align-items: center; gap: 8px; padding: 5px 10px; border: 1px solid #c7d2fe; background: #eef2ff; border-radius: 8px; margin-bottom: 5px; font-size: 13px; }
    .gnt-chip { padding: 2px 8px; border-radius: 999px; color: #fff; font-size: 11px; font-weight: 700; }
</style>
@endsection

@section('content')
<div class="container-fluid mt-4 gnt">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="mb-0"><i class="fas fa-ban"></i> Google N Targeting</h4>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <button type="button" id="gnt-clear" class="btn btn-outline-light btn-sm d-none">Clear</button>
                <button type="button" id="gnt-push" class="btn btn-warning btn-sm fw-bold"
                        title="Add the negative keywords a campaign is missing from its KW cousins to Google Ads. Ticked campaigns only, or every campaign with cousins when nothing is ticked. You see the list before anything is sent.">
                    <i class="fas fa-upload"></i> Push N Targeting
                </button>
            </div>
            <span class="small">Negative keywords from the stored Google Ads sync (same data as N Target). KW cousins are the groups of the Keyword Targeting page.</span>
        </div>
        <div class="card-body">
            <div class="mb-3 d-flex gap-1 flex-nowrap align-items-center" style="overflow-x:auto;">
                <div class="btn-group btn-group-sm flex-shrink-0" id="gnt-status" title="Campaign status. Click several to combine.">
                    <button type="button" class="btn btn-primary active" data-status="">All</button>
                    <button type="button" class="btn btn-outline-primary" data-status="ENABLED">Enabled</button>
                    <button type="button" class="btn btn-outline-primary" data-status="PAUSED">Paused</button>
                </div>
                <span class="text-muted small text-nowrap flex-shrink-0" id="gnt-count"></span>
                <span class="badge bg-danger gnt-badge text-nowrap flex-shrink-0" role="button" data-f="m" id="gnt-b-m" title="M = Missing: campaigns with no negative keywords. Click to filter.">M N Target: 0</span>
                <span class="badge bg-primary gnt-badge text-nowrap flex-shrink-0" role="button" data-f="low" id="gnt-b-low" title="Campaigns with fewer than 50 negative keywords. Click to filter.">N TGT &lt; 50: 0</span>
                <span class="badge bg-danger gnt-badge text-nowrap flex-shrink-0" role="button" data-f="nocousin" id="gnt-b-nc" title="M = Missing: campaigns whose parent has no KW cousins. Click to filter.">M KW cousins: 0</span>
                <span class="badge bg-success gnt-badge text-nowrap flex-shrink-0" role="button" data-f="cousin" id="gnt-b-c" title="Campaigns that have KW cousins. Click to filter.">With cousins: 0</span>
                <a class="small text-nowrap ms-auto flex-shrink-0" href="{{ route('amazon.ads.fbm-targeting') }}" title="KW cousins are added and removed here">Manage cousins</a>
            </div>
            <div id="gnt-table"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="gntNegModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title"><i class="fas fa-ban"></i> Negative keywords - <span id="gnt-neg-title"></span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-2" id="gnt-neg-body" style="max-height:60vh;overflow:auto;"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="gntCousinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title"><i class="fas fa-link"></i> KW Cousins - <span id="gnt-cz-title"></span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Cousins share negative keywords. They come from the Keyword Targeting page, where they are added, removed and named.</p>
                <div class="fw-semibold mb-2" id="gnt-cz-name"></div>
                <div id="gnt-cz-list" style="max-height:300px;overflow-y:auto;"></div>
            </div>
            <div class="modal-footer py-2">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('amazon.ads.fbm-targeting') }}">Manage on Keyword Targeting</a>
                <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="gntPushModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-warning py-2">
                <h5 class="modal-title"><i class="fas fa-upload"></i> Push N Targeting to Google Ads</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">
                    Each campaign gets the campaign-level negative keywords its KW cousins have and it does not (same text + match type is never added twice).
                    Only the ticked rows are pushed; nothing else on Google Ads is touched.
                </p>
                <div id="gp-summary" class="fw-semibold mb-2"></div>
                <div id="gp-tools" class="d-none align-items-center gap-2 mb-2">
                    <input type="text" id="gp-filter" class="form-control form-control-sm" style="max-width:260px;" placeholder="Filter campaigns..." autocomplete="off">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="gp-all">Select shown</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="gp-none">Clear shown</button>
                    <span class="small text-muted ms-auto" id="gp-sel"></span>
                </div>
                <div id="gp-wrap" style="max-height:420px;overflow-y:auto;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" id="gp-confirm" class="btn btn-warning btn-sm fw-bold" disabled><i class="fas fa-upload"></i> Push selected</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function () {
    const urls = {
        data: @json(route('google.neg-targeting.data')),
        negs: @json(route('google.shopping.campaigns.negatives')),
        check: @json(route('google.neg-targeting.push-check')),
        push: @json(route('google.neg-targeting.push')),
    };
    const csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const $ = function (id) { return document.getElementById(id); };
    const esc = function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
    const modal = function (id, show) {
        const el = $(id);
        if (window.bootstrap && bootstrap.Modal) { const m = bootstrap.Modal.getOrCreateInstance(el); show ? m.show() : m.hide(); }
        else if (window.jQuery) { window.jQuery(el).modal(show ? 'show' : 'hide'); }
    };
    const post = function (url, body) {
        return fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(body),
        }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (b) { return { ok: r.ok && b.success !== false, body: b }; }); });
    };

    let groups = {};
    let low = 50;
    const statusSel = new Set();
    let quick = ''; // '', m, low, nocousin, cousin

    // ---------- grid ----------
    const table = new Tabulator('#gnt-table', {
        ajaxURL: urls.data,
        ajaxResponse: function (url, params, resp) {
            groups = (resp && resp.groups) || {};
            low = Number(resp && resp.low) || 50;
            return (resp && resp.data) || [];
        },
        index: 'campaign_id',
        layout: 'fitColumns',
        pagination: true,
        paginationSize: 100,
        paginationSizeSelector: [50, 100, 250, 500],
        paginationCounter: 'rows',
        placeholder: 'Loading campaigns…',
        columns: [
            { formatter: 'rowSelection', titleFormatter: 'rowSelection', hozAlign: 'center', headerSort: false, width: 40,
              cellClick: function (e, cell) { cell.getRow().toggleSelect(); } },
            { title: 'Campaign', field: 'campaign_name', headerFilter: 'input', widthGrow: 4, tooltip: true },
            { title: 'Parent', field: 'parent', headerFilter: 'input', widthGrow: 2, tooltip: true },
            { title: 'Status', field: 'status', width: 80, hozAlign: 'center', headerHozAlign: 'center',
              formatter: function (c) {
                  const s = String(c.getValue() || '');
                  const col = s === 'ENABLED' ? '#16a34a' : (s === 'PAUSED' ? '#f59e0b' : '#94a3b8');
                  return '<span class="gnt-dot" style="background:' + col + '" title="' + esc(s || 'Unknown') + '"></span>';
              } },
            { title: 'KWC Cousin', field: 'cousin_name', headerFilter: 'input', widthGrow: 2, tooltip: true },
            { title: 'KW cousins', field: 'cousin_count', width: 100, hozAlign: 'center', headerHozAlign: 'center', sorter: 'number',
              formatter: function (c) {
                  const n = Number(c.getValue() || 0);
                  return '<span class="kwc-btn' + (n ? '' : ' kwc-empty') + '" title="' + (n ? n + ' cousin parent' + (n === 1 ? '' : 's') + '. Click to view.' : 'No KW cousins.') + '">KWC <span class="kwc-n">' + n + '</span></span>';
              },
              cellClick: function (e, cell) { openCousins(cell.getRow().getData()); } },
            { title: 'N Target', field: 'neg_count', width: 100, hozAlign: 'center', headerHozAlign: 'center', sorter: 'number',
              headerTooltip: 'Negative keywords (campaign + ad group level), the same count as the N Target column on Google Shopping',
              formatter: function (c) {
                  const n = Number(c.getValue() || 0);
                  const bg = n === 0 ? '#dc3545' : (n < low ? '#d97706' : (n > 100 ? '#6f42c1' : '#198754'));
                  return '<span class="gnt-count" style="background:' + bg + '" title="View negative keywords">' + (n === 0 ? 'M' : n.toLocaleString()) + '</span>';
              },
              cellClick: function (e, cell) { openNegs(cell.getRow().getData()); } },
            { title: 'Push', headerSort: false, width: 80, hozAlign: 'center', headerHozAlign: 'center',
              formatter: function (c) {
                  return c.getRow().getData().cousin_count > 0
                      ? '<button type="button" class="btn btn-sm btn-outline-warning py-0" title="Push the negatives this campaign is missing from its KW cousins"><i class="fas fa-upload"></i></button>'
                      : '<span class="text-muted">-</span>';
              },
              cellClick: function (e, cell) { const d = cell.getRow().getData(); if (d.cousin_count > 0) openPush([d.campaign_id]); } },
        ],
    });

    function inQuick(d) {
        const n = Number(d.neg_count || 0), c = Number(d.cousin_count || 0);
        return quick === '' || (quick === 'm' && n === 0) || (quick === 'low' && n < low) || (quick === 'nocousin' && c === 0) || (quick === 'cousin' && c > 0);
    }
    function applyFilters() {
        table.setFilter(function (d) { return (statusSel.size === 0 || statusSel.has(d.status)) && inQuick(d); });
    }
    function updateCounts() {
        const rows = table.getData();
        let m = 0, lo = 0, nc = 0, wc = 0;
        rows.forEach(function (d) {
            const n = Number(d.neg_count || 0);
            if (n === 0) m++;
            if (n < low) lo++;
            if (Number(d.cousin_count || 0) > 0) wc++; else nc++;
        });
        $('gnt-b-m').textContent = 'M N Target: ' + m.toLocaleString();
        $('gnt-b-low').innerHTML = 'N TGT &lt; ' + low + ': ' + lo.toLocaleString();
        $('gnt-b-nc').textContent = 'M KW cousins: ' + nc.toLocaleString();
        $('gnt-b-c').textContent = 'With cousins: ' + wc.toLocaleString();
        $('gnt-count').textContent = table.getDataCount('active').toLocaleString() + ' of ' + rows.length.toLocaleString() + ' campaigns';
    }
    table.on('dataProcessed', updateCounts);
    table.on('dataFiltered', updateCounts);

    $('gnt-status').addEventListener('click', function (e) {
        const b = e.target.closest('button[data-status]');
        if (!b) return;
        const s = b.getAttribute('data-status');
        if (s === '') statusSel.clear(); else if (statusSel.has(s)) statusSel.delete(s); else statusSel.add(s);
        this.querySelectorAll('button').forEach(function (x) {
            const v = x.getAttribute('data-status');
            const on = v === '' ? statusSel.size === 0 : statusSel.has(v);
            x.classList.toggle('active', on); x.classList.toggle('btn-primary', on); x.classList.toggle('btn-outline-primary', !on);
        });
        applyFilters();
    });
    document.querySelectorAll('.gnt-badge').forEach(function (b) {
        b.addEventListener('click', function () {
            const f = b.getAttribute('data-f');
            quick = quick === f ? '' : f;
            document.querySelectorAll('.gnt-badge').forEach(function (x) { x.classList.toggle('is-active', x.getAttribute('data-f') === quick); });
            applyFilters();
        });
    });

    // Selection
    function picked() { return table.getSelectedData().map(function (d) { return String(d.campaign_id); }); }
    function syncSel() { $('gnt-clear').classList.toggle('d-none', picked().length === 0); }
    table.on('rowSelectionChanged', syncSel);
    $('gnt-clear').addEventListener('click', function () { table.deselectRow(); });

    // ---------- negatives viewer (existing endpoint) ----------
    function chip(m) {
        const u = String(m || '').toUpperCase();
        const bg = u === 'EXACT' ? '#0d6efd' : (u === 'PHRASE' ? '#6f42c1' : (u === 'BROAD' ? '#20c997' : '#6c757d'));
        return '<span class="gnt-chip" style="background:' + bg + '">' + esc(u || '--') + '</span>';
    }
    function openNegs(row) {
        const body = $('gnt-neg-body');
        $('gnt-neg-title').textContent = row.campaign_name + ' (' + row.campaign_id + ')';
        body.innerHTML = '<span class="text-muted small">Loading…</span>';
        modal('gntNegModal', true);
        fetch(urls.negs + '?campaign_id=' + encodeURIComponent(row.campaign_id), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); }).then(function (resp) {
                const list = (resp && resp.data) || [];
                if (!list.length) { body.innerHTML = '<span class="text-muted small">No negative keywords stored for this campaign.</span>'; return; }
                const c = resp.counts || {};
                body.innerHTML = '<div class="small text-muted mb-2">Campaign-level: <strong>' + (c.campaign || 0) + '</strong> · Ad group-level: <strong>' + (c.ad_group || 0) + '</strong></div>' +
                    '<table class="table table-sm table-hover mb-0"><thead><tr><th>Level</th><th>Ad group</th><th>Negative keyword</th><th class="text-center">Match</th></tr></thead><tbody>' +
                    list.map(function (d) {
                        return '<tr><td>' + (String(d.level).toUpperCase() === 'CAMPAIGN' ? 'Campaign' : 'Ad group') + '</td><td>' + (d.ad_group_name ? esc(d.ad_group_name) : '<span class="text-muted">--</span>') +
                            '</td><td>' + esc(d.keyword_text) + '</td><td class="text-center">' + chip(d.match_type) + '</td></tr>';
                    }).join('') + '</tbody></table>';
            }).catch(function () { body.innerHTML = '<span class="text-danger small">Failed to load negative keywords.</span>'; });
    }

    // ---------- cousins (read-only: the groups of the Keyword Targeting page) ----------
    function openCousins(row) {
        const g = row.gid != null ? groups[row.gid] : null;
        $('gnt-cz-title').textContent = row.campaign_name;
        $('gnt-cz-name').textContent = g && g.name ? 'Cousin name: ' + g.name : '';
        const own = String(row.parent || '').toUpperCase();
        const list = g ? g.parents.filter(function (p) { return p !== own; }) : [];
        $('gnt-cz-list').innerHTML = list.length
            ? list.map(function (p) { return '<div class="gnt-item">' + esc(p) + '</div>'; }).join('')
            : '<span class="text-muted small">No cousins yet (parent ' + esc(own || '-') + ').</span>';
        modal('gntCousinModal', true);
    }

    // ---------- push ----------
    const gp = { rows: [], sel: new Set(), filter: '', batch: 3, busy: false };
    const gpShown = function () {
        const q = gp.filter.trim().toLowerCase();
        return q ? gp.rows.filter(function (r) { return r.campaign_name.toLowerCase().indexOf(q) >= 0 || String(r.parent).toLowerCase().indexOf(q) >= 0; }) : gp.rows;
    };
    function gpUpdate() {
        let kws = 0;
        gp.rows.forEach(function (r) { if (gp.sel.has(r.campaign_id)) kws += r.to_add; });
        $('gp-sel').textContent = gp.sel.size.toLocaleString() + ' of ' + gp.rows.length.toLocaleString() + ' campaigns selected · ' + kws.toLocaleString() + ' negative keyword(s)';
        $('gp-confirm').disabled = gp.busy || gp.sel.size === 0;
        const all = $('gp-chk-all');
        if (all) {
            const sh = gpShown(), n = sh.filter(function (r) { return gp.sel.has(r.campaign_id); }).length;
            all.checked = sh.length > 0 && n === sh.length; all.indeterminate = n > 0 && n < sh.length;
        }
    }
    function gpDraw() {
        const rows = gpShown();
        if (!rows.length) { $('gp-wrap').innerHTML = '<span class="text-muted small">No campaigns match the filter</span>'; gpUpdate(); return; }
        $('gp-wrap').innerHTML = '<table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr>' +
            '<th style="width:36px"><input type="checkbox" id="gp-chk-all"></th><th>Campaign</th><th>Parent</th><th>Will get (sample)</th><th style="width:90px" class="text-end">To add</th></tr></thead><tbody>' +
            rows.map(function (r) {
                return '<tr><td><input type="checkbox" class="gp-row" data-id="' + esc(r.campaign_id) + '"' + (gp.sel.has(r.campaign_id) ? ' checked' : '') + '></td>' +
                    '<td>' + esc(r.campaign_name) + '</td><td class="small">' + esc(r.parent) + '</td>' +
                    '<td class="small text-muted">' + esc(r.sample.join(', ')) + (r.to_add > r.sample.length ? ' …' : '') + '</td>' +
                    '<td class="text-end">' + r.to_add.toLocaleString() + '</td></tr>';
            }).join('') + '</tbody></table>';
        gpUpdate();
    }
    function openPush(ids) {
        $('gp-summary').className = 'fw-semibold mb-2';
        $('gp-summary').textContent = 'Checking what is missing…';
        $('gp-wrap').innerHTML = '';
        $('gp-tools').classList.add('d-none'); $('gp-tools').classList.remove('d-flex');
        $('gp-confirm').disabled = true;
        gp.rows = []; gp.sel = new Set(); gp.filter = ''; $('gp-filter').value = '';
        modal('gntPushModal', true);
        post(urls.check, ids && ids.length ? { ids: ids } : {}).then(function (r) {
            const b = r.body || {};
            if (!r.ok) { $('gp-summary').className = 'fw-semibold mb-2 text-danger'; $('gp-summary').textContent = b.message || 'Could not check.'; return; }
            gp.rows = b.rows || []; gp.batch = Number(b.batch) || 3;
            gp.sel = new Set(gp.rows.map(function (x) { return x.campaign_id; }));
            const note = (b.up_to_date ? b.up_to_date.toLocaleString() + ' campaign(s) already have everything. ' : '') + (b.no_cousins ? b.no_cousins.toLocaleString() + ' have no KW cousins. ' : '');
            if (!gp.rows.length) { $('gp-summary').textContent = 'Nothing to push. ' + note; return; }
            $('gp-summary').textContent = gp.rows.length.toLocaleString() + ' campaign(s) are missing negative keywords. ' + note;
            $('gp-tools').classList.remove('d-none'); $('gp-tools').classList.add('d-flex');
            gpDraw();
        }).catch(function (e) { $('gp-summary').className = 'fw-semibold mb-2 text-danger'; $('gp-summary').textContent = 'Failed: ' + e.message; });
    }
    $('gnt-push').addEventListener('click', function () { openPush(picked()); });
    $('gp-wrap').addEventListener('change', function (e) {
        const t = e.target;
        if (t.id === 'gp-chk-all') { gpShown().forEach(function (r) { t.checked ? gp.sel.add(r.campaign_id) : gp.sel.delete(r.campaign_id); }); gpDraw(); }
        else if (t.classList.contains('gp-row')) { const id = t.getAttribute('data-id'); t.checked ? gp.sel.add(id) : gp.sel.delete(id); gpUpdate(); }
    });
    $('gp-filter').addEventListener('input', function () { gp.filter = this.value; gpDraw(); });
    $('gp-all').addEventListener('click', function () { gpShown().forEach(function (r) { gp.sel.add(r.campaign_id); }); gpDraw(); });
    $('gp-none').addEventListener('click', function () { gpShown().forEach(function (r) { gp.sel.delete(r.campaign_id); }); gpDraw(); });

    // Pushes a few campaigns per request so no request runs long, with live progress.
    $('gp-confirm').addEventListener('click', function () {
        const ids = gp.rows.filter(function (r) { return gp.sel.has(r.campaign_id); }).map(function (r) { return r.campaign_id; });
        if (!ids.length || !confirm('Add the missing negative keywords to ' + ids.length.toLocaleString() + ' campaign(s) on Google Ads?')) return;
        gp.busy = true; $('gp-confirm').disabled = true;
        $('gp-tools').classList.add('d-none'); $('gp-tools').classList.remove('d-flex');
        const results = []; let done = 0, failedBatches = 0;
        const next = function () {
            if (done >= ids.length) { return finish(); }
            const chunk = ids.slice(done, done + gp.batch);
            $('gp-summary').className = 'fw-semibold mb-2';
            $('gp-summary').textContent = 'Pushing… ' + done.toLocaleString() + ' / ' + ids.length.toLocaleString() + ' campaigns';
            post(urls.push, { ids: chunk }).then(function (r) {
                if (r.ok && r.body.results) { r.body.results.forEach(function (x) { results.push(x); }); }
                else { failedBatches++; chunk.forEach(function (id) { results.push({ campaign_id: id, campaign_name: id, to_add: 0, added: 0, failed: 0, message: (r.body && r.body.message) || 'Request failed' }); }); }
                done += chunk.length; next();
            }).catch(function (e) { failedBatches++; chunk.forEach(function (id) { results.push({ campaign_id: id, campaign_name: id, to_add: 0, added: 0, failed: 0, message: e.message }); }); done += chunk.length; next(); });
        };
        const finish = function () {
            gp.busy = false;
            const added = results.reduce(function (n, x) { return n + Number(x.added || 0); }, 0);
            const failed = results.reduce(function (n, x) { return n + Number(x.failed || 0); }, 0);
            $('gp-summary').className = 'fw-semibold mb-2 ' + (failed || failedBatches ? 'text-warning' : 'text-success');
            $('gp-summary').textContent = 'Done: ' + added.toLocaleString() + ' negative keyword(s) added' + (failed ? ', ' + failed.toLocaleString() + ' failed (already exist or rejected)' : '') + '.';
            $('gp-wrap').innerHTML = '<table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr><th>Campaign</th><th style="width:110px" class="text-end">Added</th><th style="width:80px" class="text-end">Failed</th><th>Note</th></tr></thead><tbody>' +
                results.map(function (x) {
                    return '<tr><td>' + esc(x.campaign_name) + '</td><td class="text-end">' + Number(x.added || 0) + ' / ' + Number(x.to_add || 0) + '</td><td class="text-end ' + (x.failed ? 'text-danger fw-bold' : '') + '">' + Number(x.failed || 0) + '</td><td class="small">' + esc(x.message || '') + '</td></tr>';
                }).join('') + '</tbody></table>';
            table.setData(urls.data); // N Target counts include what was just pushed
        };
        next();
    });
})();
</script>
@endsection
