@php
    $pageTitle = 'Google Shopping — not matching live';
    $pageSubtitle = 'Google Ads';
@endphp

@extends('layouts.vertical', ['title' => $pageTitle, 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #gsm-wrap .gsm-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        #gsm-wrap .gsm-diff { color: #dc2626; font-weight: 700; }
        #gsm-wrap .gsm-ok { color: #16a34a; font-weight: 700; }
        #gsm-wrap tr.gsm-blocked { background: #fff7ed; }
        #gsm-wrap tr.gsm-done { background: #f0fdf4; }
        #gsm-wrap tr.gsm-fail { background: #fef2f2; }
        #gsm-wrap .gsm-reason { font-size: 12px; color: #4b5563; max-width: 520px; }
        #gsm-wrap .gsm-status { font-size: 12px; font-weight: 600; white-space: nowrap; }
        #gsm-wrap thead th { position: sticky; top: 0; background: #e8f0fe; z-index: 1; }
    </style>
@endsection

@section('content')
    <div class="container-fluid" id="gsm-wrap">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 my-3">
            <div>
                <h4 class="mb-0">Not matching live Google Ads</h4>
                <div class="text-muted small">
                    ENABLED Shopping campaigns where SBGT ≠ LBgt or SBID ≠ LBid. Tick the campaigns to push — they are sent to Google Ads and
                    read back to confirm. SBGT 0 campaigns cannot be pushed ($0 budget); fix the reason shown instead.
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <input type="search" class="form-control form-control-sm" id="gsm-filter" placeholder="Filter by name…" style="width:200px;">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="gsm-select-all">Select all pushable</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="gsm-select-none">Clear</button>
                <button type="button" class="btn btn-sm btn-outline-primary" id="gsm-refresh" title="Read LBgt / LBid from Google Ads now (slower)">
                    <i class="fa fa-rotate"></i> Refresh from Google
                </button>
                <button type="button" class="btn btn-sm btn-warning text-dark" id="gsm-push" disabled>
                    <i class="fa fa-cloud-upload-alt"></i> Push selected (<span id="gsm-selected-count">0</span>)
                </button>
            </div>
        </div>

        <div class="alert alert-danger d-none" id="gsm-error" role="alert"></div>
        <div class="small text-muted mb-2" id="gsm-summary">Loading…</div>

        <div class="table-responsive" style="max-height: 75vh;">
            <table class="table table-sm table-bordered align-middle bg-white mb-0">
                <thead>
                    <tr>
                        <th style="width:34px;"><input type="checkbox" id="gsm-check-all" title="Select all pushable"></th>
                        <th>Campaign</th>
                        <th class="gsm-num">SBGT</th>
                        <th class="gsm-num">LBgt</th>
                        <th class="gsm-num">SBID</th>
                        <th class="gsm-num">LBid</th>
                        <th>Reason</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody id="gsm-body"></tbody>
            </table>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        (function () {
            var dataUrl = @json(route('google.shopping.campaigns.mismatches.data'));
            var pushUrl = @json(route('google.shopping.campaigns.mismatches.push'));
            var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            var BATCH = 25;
            var rows = [];
            var selected = {};

            var body = document.getElementById('gsm-body');
            var summary = document.getElementById('gsm-summary');
            var errBox = document.getElementById('gsm-error');
            var pushBtn = document.getElementById('gsm-push');
            var countEl = document.getElementById('gsm-selected-count');
            var filterEl = document.getElementById('gsm-filter');

            function esc(s) {
                return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }
            function money(v) {
                return (v === null || v === undefined) ? '—' : '$' + Number(v).toFixed(2);
            }
            function pushable(r) { return !!(r.can_push_bgt || r.can_push_bid); }
            function showError(msg) {
                errBox.textContent = msg || '';
                errBox.classList.toggle('d-none', !msg);
            }
            function selectedIds() {
                return Object.keys(selected).filter(function (id) { return selected[id]; });
            }
            function updateCount() {
                var n = selectedIds().length;
                countEl.textContent = n;
                pushBtn.disabled = n === 0;
            }

            function cellClass(diff) { return 'gsm-num' + (diff ? ' gsm-diff' : ''); }

            function render() {
                var q = (filterEl.value || '').trim().toLowerCase();
                var html = '';
                var shown = 0;
                rows.forEach(function (r) {
                    if (q && (r.name + ' ' + r.cid).toLowerCase().indexOf(q) === -1) return;
                    shown++;
                    var can = pushable(r);
                    var bgtDiff = r.can_push_bgt || r.blocked;
                    var bidDiff = r.can_push_bid;
                    var cls = r._status === 'matched' ? 'gsm-done' : (r._status === 'not_matched' ? 'gsm-fail' : (!can ? 'gsm-blocked' : ''));
                    html += '<tr class="' + cls + '" data-cid="' + esc(r.cid) + '">'
                        + '<td>' + (can && r._status !== 'matched'
                            ? '<input type="checkbox" class="gsm-check" data-cid="' + esc(r.cid) + '"' + (selected[r.cid] ? ' checked' : '') + '>' : '') + '</td>'
                        + '<td><div class="fw-semibold">' + esc(r.name) + '</div><div class="text-muted small">' + esc(r.cid) + '</div></td>'
                        + '<td class="' + cellClass(bgtDiff) + '">' + esc(r.sbgt) + '</td>'
                        + '<td class="' + cellClass(bgtDiff) + '">' + money(r.lbgt) + '</td>'
                        + '<td class="' + cellClass(bidDiff) + '">' + money(r.sbid) + '</td>'
                        + '<td class="' + cellClass(bidDiff) + '">' + money(r.lbid) + '</td>'
                        + '<td class="gsm-reason">' + r.reasons.map(esc).join('<br>') + '</td>'
                        + '<td class="gsm-status">' + (r._message ? esc(r._message) : (can ? '' : 'Cannot be pushed')) + '</td>'
                        + '</tr>';
                });
                body.innerHTML = html || '<tr><td colspan="8" class="text-center text-muted py-4">Everything matches Google Ads. Nothing to push.</td></tr>';
                var pushableCount = rows.filter(function (r) { return pushable(r) && r._status !== 'matched'; }).length;
                summary.textContent = rows.length + ' campaign' + (rows.length === 1 ? '' : 's') + ' not matching · '
                    + pushableCount + ' can be pushed · ' + (rows.length - pushableCount) + ' need a fix first'
                    + (q ? ' · showing ' + shown : '');
                updateCount();
            }

            function load(refresh) {
                showError('');
                summary.textContent = refresh ? 'Reading live values from Google Ads…' : 'Loading…';
                return fetch(dataUrl + (refresh ? '?refresh=1' : ''), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (res) { return res.json(); })
                    .then(function (json) {
                        if (!json.ok) showError(json.message || 'Could not load.');
                        rows = json.data || [];
                        selected = {};
                        render();
                    })
                    .catch(function (e) { showError('Could not load: ' + e.message); summary.textContent = ''; });
            }

            function pushBatch(ids) {
                return fetch(pushUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    credentials: 'same-origin',
                    body: JSON.stringify({ campaign_ids: ids })
                }).then(function (res) { return res.json().then(function (j) { j._http = res.status; return j; }); });
            }

            function applyResults(results) {
                (results || []).forEach(function (res) {
                    rows.forEach(function (r) {
                        if (String(r.cid) === String(res.campaign_id)) {
                            r._status = res.status;
                            r._message = res.message;
                            if (res.status === 'matched') delete selected[r.cid];
                        }
                    });
                });
            }

            document.getElementById('gsm-push').addEventListener('click', function () {
                var ids = selectedIds();
                if (!ids.length) return;
                if (!window.confirm('Push ' + ids.length + ' campaign' + (ids.length === 1 ? '' : 's') + ' to Google Ads? SBGT and SBID will be sent where they differ from the live value.')) return;

                pushBtn.disabled = true;
                showError('');
                var batches = [];
                for (var i = 0; i < ids.length; i += BATCH) batches.push(ids.slice(i, i + BATCH));
                var errors = [];
                var done = 0;

                batches.reduce(function (chain, batch) {
                    return chain.then(function () {
                        summary.textContent = 'Pushing… ' + done + ' / ' + ids.length;
                        return pushBatch(batch).then(function (json) {
                            if (json.results) applyResults(json.results);
                            if (json.push_errors && json.push_errors.length) errors = errors.concat(json.push_errors);
                            if (!json.results && json.message) errors.push(json.message);
                            done += batch.length;
                            render();
                        });
                    });
                }, Promise.resolve()).catch(function (e) {
                    errors.push(e.message);
                }).then(function () {
                    if (errors.length) showError(errors.join(' · '));
                    render();
                });
            });

            body.addEventListener('change', function (e) {
                if (!e.target.classList.contains('gsm-check')) return;
                selected[e.target.getAttribute('data-cid')] = e.target.checked;
                updateCount();
            });
            document.getElementById('gsm-check-all').addEventListener('change', function (e) {
                rows.forEach(function (r) { if (pushable(r) && r._status !== 'matched') selected[r.cid] = e.target.checked; });
                render();
            });
            document.getElementById('gsm-select-all').addEventListener('click', function () {
                rows.forEach(function (r) { if (pushable(r) && r._status !== 'matched') selected[r.cid] = true; });
                render();
            });
            document.getElementById('gsm-select-none').addEventListener('click', function () { selected = {}; render(); });
            document.getElementById('gsm-refresh').addEventListener('click', function () { load(true); });
            filterEl.addEventListener('input', render);

            load(false);
        })();
    </script>
@endsection
