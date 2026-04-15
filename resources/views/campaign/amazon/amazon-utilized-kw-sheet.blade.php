@extends('layouts.vertical', ['title' => 'Utilized KW — sheet', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        .amazon-utilized-kw-sheet.amazon-ads-all .table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            font-size: 0.75rem;
            white-space: nowrap;
        }
        .amazon-utilized-kw-sheet .amazon-raw-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
        }
        .amazon-utilized-kw-sheet .amazon-raw-table-wrap .dataTables_wrapper {
            width: 100%;
        }
        .amazon-utilized-kw-sheet #amazon-utilized-kw-sheet-loading {
            font-size: 0.9rem;
        }
        .amazon-utilized-kw-sheet table.dataTable td {
            vertical-align: middle;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Amazon', 'page_title' => 'Utilized KW — sheet (test layout)'])

    <div class="row amazon-utilized-kw-sheet amazon-ads-all mb-3">
        <div class="col-12">
            <p class="text-muted small mb-2">
                Same data as <a href="{{ route('amazon.utilized.kw') }}">Amz KW Ad</a> (<code>/amazon/utilized/kw/ads/data</code>),
                columns match the <code>test</code> file layout. Table style matches
                <a href="{{ route('amazon.ads.all') }}">Amazon Ads All</a>.
            </p>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <button type="button" class="btn btn-outline-primary btn-sm" id="amazon-utilized-kw-sheet-download" disabled>Download .tsv</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('amazon.utilized.kw') }}">Open Tabulator view</a>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('amazon.ads.all') }}">Amazon Ads All</a>
                <span id="amazon-utilized-kw-sheet-status" class="small text-muted"></span>
            </div>
            <details class="border rounded bg-light px-3 py-2 mb-0 small">
                <summary class="fw-semibold cursor-pointer user-select-none py-1">Calculations (7ub, 2ub, 1ub &amp; budget)</summary>
                <div class="pt-2 pb-1 text-muted">
                    <p class="mb-2"><strong class="text-dark">Daily budget</strong> (<code>bgt</code> column) used in UB math:</p>
                    <ul class="mb-3">
                        <li><code>budget = utilization_budget</code> when that field is set (e.g. parent aggregate rows).</li>
                        <li>Otherwise <code>budget = campaignBudgetAmount</code>.</li>
                    </ul>
                    <p class="mb-2"><strong class="text-dark">7ub</strong> (same idea as Amz KW Ad &quot;7 UB%&quot;):</p>
                    <ul class="mb-3">
                        <li><code>7ub = round((l7_spend ÷ (budget × 7)) × 100)</code> and shown with a <code>%</code> sign.</li>
                        <li>Meaning: last-7-day spend vs <strong>seven days</strong> of daily budget (100% = you spent one full daily budget on average per day over the window).</li>
                        <li>Shown as <code>-</code> if there is no campaign or <code>budget ≤ 0</code>.</li>
                    </ul>
                    <p class="mb-2"><strong class="text-dark">1ub</strong> (same as Amz KW Ad &quot;1 UB%&quot;):</p>
                    <ul class="mb-3">
                        <li><code>1ub = round((l1_spend ÷ budget) × 100)</code> with a <code>%</code> sign.</li>
                        <li>Meaning: last-1-day spend as a percent of <strong>one day</strong> of budget.</li>
                        <li>Shown as <code>-</code> if there is no campaign or <code>budget ≤ 0</code>.</li>
                    </ul>
                    <p class="mb-2"><strong class="text-dark">2ub</strong> on <em>this</em> sheet (matches legacy <code>test</code> TSV):</p>
                    <ul class="mb-3">
                        <li><code>2ub = format as currency: l2_spend ÷ 2</code> (average spend per day over the last-2-day window), not a percent.</li>
                        <li>On <a href="{{ route('amazon.utilized.kw') }}">Amz KW Ad</a>, the &quot;2 UB%&quot; column shows <code>(l2_spend ÷ budget) × 100</code>.</li>
                    </ul>
                    <p class="mb-2"><strong class="text-dark">Conditional text color</strong> (columns <strong>7ub</strong>, <strong>2ub</strong>, <strong>1ub</strong>):</p>
                    <ul class="mb-0">
                        <li><span style="color:#FF00FF;font-weight:600">Magenta #FF00FF</span> if value is <strong>≥ 99%</strong> (for <strong>2ub</strong>, the <em>percentage</em> used is <code>(l2_spend ÷ budget) × 100</code>; the cell still shows dollars).</li>
                        <li><span style="color:#FF0000;font-weight:600">Red #FF0000</span> if <strong>≤ 66%</strong>.</li>
                        <li><span style="color:#00FF00;font-weight:600">Green #00FF00</span> if strictly between those bands (<strong>&gt; 66%</strong> and <strong>&lt; 99%</strong>).</li>
                    </ul>
                    <p class="mb-0 mt-2"><strong class="text-dark">ACOS</strong> text color (same tier idea as your sheet): ≤10% magenta <code>#FF00FF</code>, 10–20% green <code>#00FF00</code>, 20–30% blue <code>#5C9CEE</code>, 30–40% gold <code>#FFCC00</code>, ≥40% pink <code>#FF8FAB</code>; neutral gray when L30 spend and sales are both 0.</p>
                    <p class="mb-0 mt-2"><strong class="text-dark">Sbgt</strong> follows ACOS color tiers: pink (≤10%) → <strong>12</strong>, green (10–20%) → <strong>8</strong>, blue (20–30%) → <strong>4</strong>, yellow (30–40%) → <strong>2</strong>, red (≥40%) → <strong>1</strong>. <strong class="text-dark">sbid</strong> = computed SBID from utilization (same rules as the SBID column on Amz KW Ad), not the stored bid field alone.</p>
                </div>
            </details>
        </div>
    </div>

    <div class="row amazon-utilized-kw-sheet amazon-ads-all">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div id="amazon-utilized-kw-sheet-loading" class="alert alert-light border mb-3 mb-0">
                        Loading…
                    </div>
                    <div id="amazon-utilized-kw-sheet-error" class="alert alert-danger d-none mb-3"></div>
                    <div class="amazon-raw-table-wrap d-none" id="amazon-utilized-kw-sheet-table-wrap">
                        <table id="amazonUtilizedKwSheetTable"
                               class="table table-hover table-striped table-bordered nowrap w-100">
                            <thead><tr></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function () {
            var SHEET_COLUMNS = [
                'State', 'Campaign name', 'bgt', 'Clicks', 'Total cost', 'L7spend', 'L2spend', 'L1spend',
                'CPC', 'Purchases', 'Sales', 'ACOS', 'L7cpc', 'L2cpc', 'L1cpc', '7ub', '2ub', '1ub',
                'Sbgt', 'sbid'
            ];
            var SHEET_KEYS = [
                'state', 'campaign_name', 'bgt', 'clicks', 'total_cost', 'l7spend', 'l2spend', 'l1spend',
                'cpc', 'purchases', 'sales', 'acos', 'l7cpc', 'l2cpc', 'l1cpc', 'ub7', 'ub2', 'ub1',
                'sbgt', 'sbid'
            ];
            var DATA_URL = '/amazon/utilized/kw/ads/data';
            var FETCH_TIMEOUT_MS = 600000;

            var scripts = [
                'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js',
                'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js'
            ];

            function loadScript(src, cb) {
                var s = document.createElement('script');
                s.src = src;
                s.async = false;
                s.onload = cb;
                s.onerror = function () { cb(); };
                document.head.appendChild(s);
            }

            function loadScriptsSequentially(i, done) {
                if (i >= scripts.length) {
                    done();
                    return;
                }
                loadScript(scripts[i], function () { loadScriptsSequentially(i + 1, done); });
            }

            function escapeCell(s) {
                return String(s).replace(/\t/g, ' ').replace(/\r|\n/g, ' ');
            }

            function escapeHtml(s) {
                return String(s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function num(v, d) {
                var n = parseFloat(v);
                return isNaN(n) ? d : n;
            }

            function formatMoney2(n) {
                return '$' + num(n, 0).toFixed(2);
            }

            function formatTotalCost(n) {
                var x = Math.round(n);
                if (Math.abs(n - x) < 0.005) {
                    return '$' + x;
                }
                return '$' + num(n, 0).toFixed(2);
            }

            function hasCampaign(r) {
                if (!r || typeof r !== 'object') {
                    return false;
                }
                if (Object.prototype.hasOwnProperty.call(r, 'hasCampaign')) {
                    return !!r.hasCampaign;
                }
                return !!(r.campaign_id && r.campaignName);
            }

            function utilizedBudget(r) {
                if (r.utilization_budget !== undefined && r.utilization_budget !== null && r.utilization_budget !== '') {
                    return num(r.utilization_budget, 0);
                }
                return num(r.campaignBudgetAmount, 0);
            }

            /** SBGT from ACOS color tier (same as Amz KW Ad SBGT mutator): pink 12, green 8, blue 4, yellow 2, red 1. */
            function computeSbgtFromAcos(acos) {
                var a = num(acos, 0);
                if (a >= 40) {
                    return '1';
                }
                if (a > 30) {
                    return '2';
                }
                if (a > 20) {
                    return '4';
                }
                if (a > 10) {
                    return '8';
                }
                return '12';
            }

            /** Same logic as Amz KW Ad “SBID” column formatter (computed bid, not DB `sbid` alone). */
            function computeSbidDisplay(r) {
                if (!hasCampaign(r)) {
                    return '-';
                }
                var l1_cpc = num(r.l1_cpc, 0);
                var l7_cpc = num(r.l7_cpc, 0);
                var budget = utilizedBudget(r);
                var l7_spend = num(r.l7_spend, 0);
                var l1_spend = num(r.l1_spend, 0);
                var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                var ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
                var price = num(r.price, 0);
                var avg_cpc = num(r.avg_cpc, 0);
                var sbid = 0;
                var rowUtilizationType = 'all';
                if (ub7 > 99 && ub1 > 99) {
                    rowUtilizationType = 'over';
                } else if (ub7 < 66 && ub1 < 66) {
                    rowUtilizationType = 'under';
                } else if (ub7 >= 66 && ub7 <= 99 && ub1 >= 66 && ub1 <= 99) {
                    rowUtilizationType = 'correctly';
                }
                if (ub7 === 0 && ub1 === 0) {
                    if (price < 50) {
                        sbid = 0.50;
                    } else if (price >= 50 && price < 100) {
                        sbid = 1.00;
                    } else if (price >= 100 && price < 200) {
                        sbid = 1.50;
                    } else {
                        sbid = 2.00;
                    }
                } else if (rowUtilizationType === 'over') {
                    if (l1_cpc > 0) {
                        sbid = Math.floor(l1_cpc * 0.90 * 100) / 100;
                    } else if (l7_cpc > 0) {
                        sbid = Math.floor(l7_cpc * 0.90 * 100) / 100;
                    } else if (avg_cpc > 0) {
                        sbid = Math.floor(avg_cpc * 0.90 * 100) / 100;
                    } else {
                        sbid = 1.00;
                    }
                } else if (rowUtilizationType === 'under') {
                    if (l1_cpc > 0) {
                        sbid = Math.floor(l1_cpc * 1.10 * 100) / 100;
                    } else if (l7_cpc > 0) {
                        sbid = Math.floor(l7_cpc * 1.10 * 100) / 100;
                    } else if (avg_cpc > 0) {
                        sbid = Math.floor(avg_cpc * 1.10 * 100) / 100;
                    } else {
                        sbid = 1.00;
                    }
                }
                if (price < 10 && sbid > 0.10) {
                    sbid = 0.10;
                } else if (price >= 10 && price < 20 && sbid > 0.20) {
                    sbid = 0.20;
                }
                if (sbid === 0) {
                    return '-';
                }
                return parseFloat(sbid.toFixed(2)).toString();
            }

            function rowToCells(r) {
                var has = hasCampaign(r);
                var budget = utilizedBudget(r);
                var campaignName = (r.campaignName && String(r.campaignName).trim()) ? String(r.campaignName).trim() : String(r.sku || '').trim();
                var l30Spend = num(r.l30_spend, 0);
                var l30Sales = num(r.l30_sales, 0);
                var acos = num(r.acos, 0);
                if (l30Spend === 0 && l30Sales === 0) {
                    acos = 0;
                }
                var l7Spend = num(r.l7_spend, 0);
                var l2Spend = num(r.l2_spend, 0);
                var l1Spend = num(r.l1_spend, 0);
                var l7Cpc = num(r.l7_cpc, 0);
                var l1Cpc = num(r.l1_cpc, 0);
                var l2Clicks = parseInt(r.l2_clicks, 10) || 0;
                var l2Cpc = num(r.l2_cpc, 0);
                if (l2Cpc <= 0 && l2Clicks > 0) {
                    l2Cpc = l2Spend / l2Clicks;
                }
                var avgCpc = num(r.avg_cpc, 0);
                var ub7 = (has && budget > 0) ? (l7Spend / (budget * 7)) * 100 : 0;
                var ub1 = (has && budget > 0) ? (l1Spend / budget) * 100 : 0;
                var state = String(r.campaignStatus || '').trim().toUpperCase();
                var clicksL30 = num(r.l30_clicks, 0);
                var purchases = parseInt(r.l30_purchases, 10) || 0;

                return [
                    state,
                    campaignName,
                    formatMoney2(budget),
                    clicksL30.toFixed(2),
                    formatTotalCost(l30Spend),
                    formatMoney2(l7Spend),
                    l2Spend.toFixed(2),
                    l1Spend.toFixed(2),
                    has ? formatMoney2(avgCpc) : '-',
                    String(purchases),
                    formatMoney2(l30Sales),
                    Math.round(acos) + '%',
                    has ? l7Cpc.toFixed(2) : '-',
                    has ? l2Cpc.toFixed(2) : '-',
                    has ? l1Cpc.toFixed(2) : '-',
                    has && budget > 0 ? Math.round(ub7) + '%' : '-',
                    has && budget > 0 ? formatMoney2(l2Spend / 2) : '-',
                    has && budget > 0 ? Math.round(ub1) + '%' : '-',
                    computeSbgtFromAcos(acos),
                    computeSbidDisplay(r)
                ];
            }

            function cellsToObject(cells) {
                var o = {};
                for (var i = 0; i < SHEET_KEYS.length; i++) {
                    o[SHEET_KEYS[i]] = cells[i] != null ? cells[i] : '';
                }
                return o;
            }

            /** Adds _ub7Pct / _ub2Pct / _ub1Pct for conditional coloring (same % rules as Amz KW Ad). */
            function rowToRecord(rawRow) {
                var o = cellsToObject(rowToCells(rawRow));
                var has = hasCampaign(rawRow);
                var budget = utilizedBudget(rawRow);
                var l7Spend = num(rawRow.l7_spend, 0);
                var l2Spend = num(rawRow.l2_spend, 0);
                var l1Spend = num(rawRow.l1_spend, 0);
                var l30Spend = num(rawRow.l30_spend, 0);
                var l30Sales = num(rawRow.l30_sales, 0);
                var acosN = num(rawRow.acos, 0);
                if (l30Spend === 0 && l30Sales === 0) {
                    o._acosNum = 0;
                    o._acosNeutral = true;
                } else {
                    o._acosNum = acosN;
                    o._acosNeutral = false;
                }
                if (has && budget > 0) {
                    o._ub7Pct = (l7Spend / (budget * 7)) * 100;
                    o._ub2Pct = (l2Spend / budget) * 100;
                    o._ub1Pct = (l1Spend / budget) * 100;
                } else {
                    o._ub7Pct = null;
                    o._ub2Pct = null;
                    o._ub1Pct = null;
                }
                return o;
            }

            /** ACOS text color tiers (same bands as spreadsheet conditional rules). */
            function acosTierTextColor(a) {
                if (a >= 40) {
                    return '#FF8FAB';
                }
                if (a > 30) {
                    return '#FFCC00';
                }
                if (a > 20) {
                    return '#5C9CEE';
                }
                if (a > 10) {
                    return '#00FF00';
                }
                return '#FF00FF';
            }

            function acosColumnRender(data, type, row) {
                var n = row._acosNum;
                if (type === 'sort' || type === 'type') {
                    return (n == null || isNaN(n)) ? -1 : n;
                }
                var text = data == null ? '' : String(data);
                if (type !== 'display') {
                    return text;
                }
                if (n == null || isNaN(n)) {
                    return escapeHtml(text);
                }
                var col = '#6c757d';
                if (!row._acosNeutral && n !== 0) {
                    col = acosTierTextColor(n);
                }
                return '<span style="color:' + col + ';font-weight:600;">' + escapeHtml(text) + '</span>';
            }

            function ubColumnRender(pctField) {
                return function (data, type, row) {
                    var p = row[pctField];
                    if (type === 'sort' || type === 'type') {
                        return (p == null || isNaN(p)) ? -1 : p;
                    }
                    var text = data == null ? '' : String(data);
                    if (type === 'display') {
                        if (text === '-' || p == null || isNaN(p)) {
                            return escapeHtml(text);
                        }
                        var col;
                        if (p >= 99) {
                            col = '#FF00FF';
                        } else if (p <= 66) {
                            col = '#FF0000';
                        } else {
                            col = '#00FF00';
                        }
                        return '<span style="color:' + col + ';font-weight:600;">' + escapeHtml(text) + '</span>';
                    }
                    return text;
                };
            }

            function tableDataToTsv(data) {
                var lines = [SHEET_COLUMNS.join('\t')];
                for (var r = 0; r < data.length; r++) {
                    var row = data[r];
                    var parts = SHEET_KEYS.map(function (k) {
                        return escapeCell(row[k] != null ? row[k] : '');
                    });
                    lines.push(parts.join('\t'));
                }
                return lines.join('\n') + '\n';
            }

            function buildTableDataChunked(rows, onProgress, done) {
                var out = [];
                var i = 0;
                var CHUNK = 150;

                function step() {
                    var end = Math.min(i + CHUNK, rows.length);
                    for (; i < end; i++) {
                        try {
                            var row = rows[i];
                            if (!row || typeof row !== 'object') {
                                continue;
                            }
                            out.push(rowToRecord(row));
                        } catch (err) {
                            var blank = {};
                            SHEET_KEYS.forEach(function (k) { blank[k] = '?'; });
                            blank._ub7Pct = null;
                            blank._ub2Pct = null;
                            blank._ub1Pct = null;
                            blank._acosNum = null;
                            blank._acosNeutral = true;
                            out.push(blank);
                        }
                    }
                    if (onProgress) {
                        onProgress(i, rows.length);
                    }
                    if (i < rows.length) {
                        window.requestAnimationFrame(step);
                    } else {
                        done(out);
                    }
                }

                window.requestAnimationFrame(step);
            }

            function initDataTable(tableData) {
                var $ = jQuery;
                var $t = $('#amazonUtilizedKwSheetTable');
                if (!$t.length || typeof $.fn.DataTable === 'undefined') {
                    return;
                }
                if ($.fn.DataTable.isDataTable($t)) {
                    $t.DataTable().clear().destroy();
                }

                var cols = SHEET_COLUMNS.map(function (title, idx) {
                    return { data: SHEET_KEYS[idx], title: title, defaultContent: '' };
                });

                var ixUb7 = SHEET_KEYS.indexOf('ub7');
                var ixUb2 = SHEET_KEYS.indexOf('ub2');
                var ixUb1 = SHEET_KEYS.indexOf('ub1');
                var ixAcos = SHEET_KEYS.indexOf('acos');

                $t.DataTable({
                    data: tableData,
                    columns: cols,
                    columnDefs: [
                        { targets: ixAcos, render: acosColumnRender },
                        { targets: ixUb7, render: ubColumnRender('_ub7Pct') },
                        { targets: ixUb2, render: ubColumnRender('_ub2Pct') },
                        { targets: ixUb1, render: ubColumnRender('_ub1Pct') }
                    ],
                    paging: true,
                    pageLength: 25,
                    lengthMenu: [[25, 50, 100, 250, 500], [25, 50, 100, 250, 500]],
                    order: [[1, 'asc']],
                    searching: true,
                    info: true,
                    deferRender: true,
                    autoWidth: false,
                    responsive: false,
                    scrollX: true,
                    scrollCollapse: true,
                    processing: false
                });

                window.__amazonUtilizedKwSheetTsv = tableDataToTsv(tableData);
            }

            function run() {
                var loadingEl = document.getElementById('amazon-utilized-kw-sheet-loading');
                var errEl = document.getElementById('amazon-utilized-kw-sheet-error');
                var wrapEl = document.getElementById('amazon-utilized-kw-sheet-table-wrap');
                var statusEl = document.getElementById('amazon-utilized-kw-sheet-status');
                var dlBtn = document.getElementById('amazon-utilized-kw-sheet-download');
                if (!loadingEl || !statusEl || !dlBtn) {
                    return;
                }

                function showError(msg) {
                    loadingEl.classList.add('d-none');
                    if (wrapEl) {
                        wrapEl.classList.add('d-none');
                    }
                    errEl.textContent = msg;
                    errEl.classList.remove('d-none');
                    statusEl.textContent = '';
                    dlBtn.disabled = true;
                }

                var waitStart = Date.now();
                var waitTimer = setInterval(function () {
                    var s = Math.floor((Date.now() - waitStart) / 1000);
                    statusEl.textContent = 'Waiting for server… ' + s + 's';
                    loadingEl.textContent = 'Requesting ' + DATA_URL + '… (' + s + 's — large JSON can take several minutes)';
                }, 1000);

                var controller = new AbortController();
                var timeoutId = setTimeout(function () {
                    controller.abort();
                }, FETCH_TIMEOUT_MS);

                fetch(DATA_URL, { credentials: 'same-origin', signal: controller.signal })
                    .then(function (res) {
                        clearTimeout(timeoutId);
                        clearInterval(waitTimer);
                        var ct = (res.headers.get('content-type') || '').toLowerCase();
                        if (!res.ok) {
                            return res.text().then(function (t) {
                                throw new Error('HTTP ' + res.status + (t ? ': ' + t.slice(0, 200) : ''));
                            });
                        }
                        if (ct.indexOf('application/json') === -1 && ct.indexOf('text/json') === -1) {
                            return res.text().then(function (t) {
                                throw new Error('Expected JSON but got ' + (ct || 'unknown') + '. ' + t.slice(0, 180));
                            });
                        }
                        statusEl.textContent = 'Parsing JSON…';
                        loadingEl.textContent = 'Parsing JSON…';
                        return res.json();
                    })
                    .then(function (payload) {
                        if (!payload) {
                            payload = {};
                        }
                        var rows = Array.isArray(payload.data) ? payload.data : [];
                        if (rows.length === 0) {
                            loadingEl.classList.add('d-none');
                            if (wrapEl) {
                                wrapEl.classList.remove('d-none');
                            }
                            initDataTable([]);
                            statusEl.textContent = 'No rows';
                            dlBtn.disabled = true;
                            window.__amazonUtilizedKwSheetTsv = SHEET_COLUMNS.join('\t') + '\n';
                            return;
                        }
                        statusEl.textContent = 'Building rows… 0 / ' + rows.length;
                        loadingEl.textContent = 'Building rows for table…';
                        buildTableDataChunked(rows, function (cur, tot) {
                            if (cur % 600 === 0 || cur === tot) {
                                statusEl.textContent = 'Building rows… ' + cur + ' / ' + tot;
                            }
                        }, function (tableData) {
                            loadingEl.classList.add('d-none');
                            if (wrapEl) {
                                wrapEl.classList.remove('d-none');
                            }
                            if (typeof jQuery === 'undefined' || !jQuery.fn.DataTable) {
                                showError('DataTables failed to load.');
                                return;
                            }
                            initDataTable(tableData);
                            statusEl.textContent = tableData.length + ' rows';
                            dlBtn.disabled = tableData.length === 0;
                        });
                    })
                    .catch(function (e) {
                        clearTimeout(timeoutId);
                        clearInterval(waitTimer);
                        var msg = (e && e.name === 'AbortError')
                            ? 'Timed out after ' + (FETCH_TIMEOUT_MS / 1000) + 's.'
                            : (e && e.message ? e.message : String(e));
                        showError(msg);
                    });

                dlBtn.addEventListener('click', function () {
                    var text = window.__amazonUtilizedKwSheetTsv;
                    if (!text) {
                        return;
                    }
                    var blob = new Blob([text], { type: 'text/tab-separated-values;charset=utf-8' });
                    var a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = 'amazon-utilized-kw-sheet.tsv';
                    a.click();
                    URL.revokeObjectURL(a.href);
                });
            }

            loadScriptsSequentially(0, function () {
                if (typeof jQuery === 'undefined') {
                    var loadingEl = document.getElementById('amazon-utilized-kw-sheet-loading');
                    if (loadingEl) {
                        loadingEl.textContent = 'jQuery is required for DataTables.';
                    }
                    return;
                }
                jQuery(function () {
                    run();
                });
            });
        })();
    </script>
@endsection
