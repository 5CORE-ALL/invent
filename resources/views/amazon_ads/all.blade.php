@extends('layouts.vertical', ['title' => 'Amazon Ads All', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        .amazon-ads-all .table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            font-size: 0.75rem;
            white-space: nowrap;
        }
        .amazon-ads-all .source-pill {
            font-size: 0.7rem;
            font-weight: 600;
        }
        .amazon-ads-all .utilized-kw-note {
            font-size: 0.85rem;
        }
        /* Full width grid: avoid clipping wide raw tables (all DB columns, incl. yes_sbid / last_sbid / sbid_m) */
        .amazon-ads-all .amazon-raw-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
        }
        .amazon-ads-all .amazon-raw-table-wrap .dataTables_wrapper {
            width: 100%;
        }
        .amazon-ads-all .amazon-raw-table-wrap table.dataTable thead th,
        .amazon-ads-all .amazon-raw-table-wrap table.dataTable tbody td {
            text-align: center;
            vertical-align: middle;
        }
        .amazon-ads-all .amazon-ads-filters .form-label {
            font-size: 0.8rem;
            font-weight: 600;
        }
        .amazon-ads-all .amazon-sbid-push-panel {
            border-left: 3px solid #0d6efd;
            padding-left: 0.75rem;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Amazon Ads', 'page_title' => 'Amazon Ads All'])

    <div class="row amazon-ads-all mb-2">
        <div class="col-12 d-flex flex-wrap justify-content-end align-items-center gap-2">
            <span class="text-muted small d-none d-md-inline" title="Fetches every row matching your filters (500 per request); same sort and search as the grid.">Export all filtered rows (CSV).</span>
            <button type="button" class="btn btn-sm btn-primary" id="amazonAdsSectionExportBtn" title="Download all rows matching current filters and DataTables search (max 50k)">Export view</button>
        </div>
    </div>

    <div class="row amazon-ads-all mb-3">
        <div class="col-12">
            <div class="alert alert-secondary mb-0 utilized-kw-note">
                <strong>Utilized KW</strong>
                (<code>/amazon/utilized/kw/ads/data</code>) loads merged rows in
                <code>AmazonSpBudgetController::getAmazonUtilizedAdsData</code>, mainly from
                <code>amazon_sp_campaign_reports</code> and <code>amazon_sb_campaign_reports</code>,
                plus <code>amazon_acos_action_history</code>, <code>amazon_datsheets</code>, <code>product_master</code>, etc.
                The grid counts <strong>report table rows</strong> (one campaign can have many: daily, L7, L30, …). Amazon&rsquo;s <strong>campaign</strong> total in Campaign Manager is unique campaigns &mdash; compare to <strong>Distinct campaign_id</strong> below the table for the same filters, not the row <em>of N entries</em> alone.
                Choose the dataset from <strong>Table</strong> below: <strong>SP</strong>, <strong>SB</strong>, and <strong>SD</strong> load <strong>every column</strong> from
                <code>amazon_sp_campaign_reports</code>, <code>amazon_sb_campaign_reports</code>, and <code>amazon_sd_campaign_reports</code> (server-side paging; use length menu up to 500 rows per request to walk the full table). <code>yes_sbid</code> / bid fields are pinned left when present. Also available: <code>amazon_bid_caps</code>, <code>amazon_fbm_targeting_checks</code>.
            </div>
        </div>
    </div>

    <div class="row amazon-ads-all">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="amazon-ads-filters border rounded p-3 mb-4 bg-light">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3 col-lg-2">
                                <label class="form-label mb-1" for="amazonAdsFilterReportType">Table</label>
                                <select id="amazonAdsFilterReportType" class="form-select form-select-sm">
                                    <option value="sp_reports">SP — amazon_sp_campaign_reports</option>
                                    <option value="sb_reports">SB — amazon_sb_campaign_reports</option>
                                    <option value="sd_reports">SD — amazon_sd_campaign_reports</option>
                                    <option value="bid_caps">amazon_bid_caps</option>
                                    <option value="fbm_targeting">amazon_fbm_targeting_checks</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-lg-2">
                                <label class="form-label mb-1" for="amazonAdsFilterSummaryRange">Report range (L30, L7…)</label>
                                <select id="amazonAdsFilterSummaryRange" class="form-select form-select-sm">
                                    <option value="">— Calendar dates below —</option>
                                    <option value="L1">L1</option>
                                    <option value="L7">L7</option>
                                    <option value="L14">L14</option>
                                    <option value="L15">L15</option>
                                    <option value="L30">L30</option>
                                    <option value="L60">L60</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-lg-2">
                                <label class="form-label mb-1" for="amazonAdsFilterDateFrom">Date from</label>
                                <input type="date" id="amazonAdsFilterDateFrom" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3 col-lg-2">
                                <label class="form-label mb-1" for="amazonAdsFilterDateTo">Date to</label>
                                <input type="date" id="amazonAdsFilterDateTo" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3 col-lg-4 d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="amazonAdsFilterApply">Apply filters</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="amazonAdsFilterClear">Clear filters</button>
                            </div>
                        </div>
                        <div class="row g-3 align-items-end mt-1">
                            <div class="col-md-4 col-lg-2">
                                <label class="form-label mb-1" for="amazonAdsFilterU7">U7% filter</label>
                                <select id="amazonAdsFilterU7" class="form-select form-select-sm" title="Spend ÷ (budget × 7), as percent">
                                    <option value="">All</option>
                                    <option value="lt66">&lt; 66%</option>
                                    <option value="66_99">66% to 99%</option>
                                    <option value="gt99">&gt; 99%</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <label class="form-label mb-1" for="amazonAdsFilterU2">U2% filter</label>
                                <select id="amazonAdsFilterU2" class="form-select form-select-sm" title="Spend ÷ (budget × 2), as percent">
                                    <option value="">All</option>
                                    <option value="lt66">&lt; 66%</option>
                                    <option value="66_99">66% to 99%</option>
                                    <option value="gt99">&gt; 99%</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <label class="form-label mb-1" for="amazonAdsFilterU1">U1% filter</label>
                                <select id="amazonAdsFilterU1" class="form-select form-select-sm" title="Spend ÷ (budget × 1), as percent">
                                    <option value="">All</option>
                                    <option value="lt66">&lt; 66%</option>
                                    <option value="66_99">66% to 99%</option>
                                    <option value="gt99">&gt; 99%</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <label class="form-label mb-1" for="amazonAdsFilterCampaignStatus">Status</label>
                                <select id="amazonAdsFilterCampaignStatus" class="form-select form-select-sm" title="campaignStatus">
                                    <option value="">All</option>
                                    <option value="ENABLED">ENABLED</option>
                                    <option value="PAUSED">PAUSED</option>
                                    <option value="ARCHIVED">ARCHIVED</option>
                                </select>
                            </div>
                        </div>

                        <div class="border-top pt-3 mt-3 amazon-sbid-push-panel">
                            <p class="small fw-semibold mb-2">SBID — push to Amazon (SP)</p>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-success" id="amazonAdsPushSbidBtn" disabled title="Switch to the SP table tab">Push SBID to Amazon (current page)</button>
                                <span class="text-muted small" id="amazonAdsSbidPushStatus" aria-live="polite"></span>
                            </div>
                        </div>
                    </div>

                    <div id="amazonAdsSourcePanels">
                        <div class="amazon-source-pane mb-0" data-pane-for="sp_reports">
                            <p class="text-muted small mb-2">
                                <span class="badge bg-success source-pill">SP</span>
                                <code class="ms-1">amazon_sp_campaign_reports</code>
                            </p>
                            <div class="amazon-raw-table-wrap">
                                <table id="amazonAdsSpReportsTable"
                                       class="table table-hover table-striped table-bordered nowrap w-100"
                                       data-raw-source="sp_reports">
                                    <thead><tr></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="amazon-source-pane mb-0 d-none" data-pane-for="sb_reports">
                            <p class="text-muted small mb-2">
                                <span class="badge bg-primary source-pill">SB</span>
                                <code class="ms-1">amazon_sb_campaign_reports</code>
                            </p>
                            <div class="amazon-raw-table-wrap">
                                <table id="amazonAdsSbReportsTable"
                                       class="table table-hover table-striped table-bordered nowrap w-100"
                                       data-raw-source="sb_reports">
                                    <thead><tr></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="amazon-source-pane mb-0 d-none" data-pane-for="sd_reports">
                            <p class="text-muted small mb-2">
                                <span class="badge bg-info source-pill">SD</span>
                                <code class="ms-1">amazon_sd_campaign_reports</code>
                            </p>
                            <div class="amazon-raw-table-wrap">
                                <table id="amazonAdsSdReportsTable"
                                       class="table table-hover table-striped table-bordered nowrap w-100"
                                       data-raw-source="sd_reports">
                                    <thead><tr></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="amazon-source-pane mb-0 d-none" data-pane-for="bid_caps">
                            <p class="text-muted small mb-2">
                                <span class="badge bg-secondary source-pill">Bid caps</span>
                                <code class="ms-1">amazon_bid_caps</code>
                            </p>
                            <div class="amazon-raw-table-wrap">
                                <table id="amazonAdsBidCapsTable"
                                       class="table table-hover table-striped table-bordered nowrap w-100"
                                       data-raw-source="bid_caps">
                                    <thead><tr></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="amazon-source-pane mb-0 d-none" data-pane-for="fbm_targeting">
                            <p class="text-muted small mb-2">
                                <span class="badge bg-secondary source-pill">FBM</span>
                                <code class="ms-1">amazon_fbm_targeting_checks</code>
                            </p>
                            <div class="amazon-raw-table-wrap">
                                <table id="amazonAdsFbmTargetingTable"
                                       class="table table-hover table-striped table-bordered nowrap w-100"
                                       data-raw-source="fbm_targeting">
                                    <thead><tr></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function () {
            var rawSources = @json($rawSources ?? []);
            var amazonAdsDefaultReportDates = @json($defaultReportRangeDates ?? (object) []);
            var dataUrlTemplate = @json(url('/amazon-ads/raw-data')) + '/';
            var pushSpSbidsUrl = @json(route('amazon.ads.push-sp-sbids'));

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

            var initialized = {};
            var amazonAdsDataTables = {};
            /** Column defs from buildColumns(), keyed by DataTable id (for client CSV like amazon_tabulator_view). */
            var amazonAdsColumnDefsByTable = {};
            var activeAdsTableId = null;
            var activeRawSourceKey = 'sp_reports';

            /** Same CSV escaping as amazon_tabulator_view (#section-export-btn). */
            function amazonAdsCsvEscapeField(val) {
                if (val === null || val === undefined) {
                    return '';
                }
                var s = String(val);
                s = s.replace(/"/g, '""');
                if (/[",\n\r]/.test(s)) {
                    return '"' + s + '"';
                }
                return s;
            }

            var AMAZON_ADS_EXPORT_CHUNK = 500;
            var AMAZON_ADS_EXPORT_MAX_ROWS = 50000;

            /** POST body matching server-side DataTables so /amazon-ads/raw-data accepts it. */
            function amazonAdsBuildRawDataAjaxPayload(draw, start, length, searchVal, orderCol, orderDir, colsMetaFull, p, token) {
                var o = {
                    draw: draw,
                    start: start,
                    length: length,
                    date_from: p.date_from || '',
                    date_to: p.date_to || '',
                    summary_report_range: p.summary_report_range || '',
                    filter_u7: p.filter_u7 || '',
                    filter_u2: p.filter_u2 || '',
                    filter_u1: p.filter_u1 || '',
                    filter_campaign_status: p.filter_campaign_status || '',
                    _token: token
                };
                o['search[value]'] = searchVal || '';
                o['search[regex]'] = 'false';
                o['order[0][column]'] = orderCol;
                o['order[0][dir]'] = orderDir || 'desc';
                for (var i = 0; i < colsMetaFull.length; i++) {
                    var col = colsMetaFull[i];
                    var prefix = 'columns[' + i + ']';
                    o[prefix + '[data]'] = col.data;
                    o[prefix + '[name]'] = '';
                    o[prefix + '[searchable]'] = 'true';
                    o[prefix + '[orderable]'] = col.orderable === false ? 'false' : 'true';
                    o[prefix + '[search][value]'] = '';
                    o[prefix + '[search][regex]'] = 'false';
                }
                return o;
            }

            function amazonAdsBuildCsvFromRows(rows, exportCols) {
                var headerLine = exportCols.map(function (c) {
                    return amazonAdsCsvEscapeField(c.title != null ? String(c.title) : String(c.data));
                }).join(',');
                var bodyLines = rows.map(function (row) {
                    return exportCols.map(function (c) {
                        var raw = row[c.data];
                        var cell;
                        if (typeof c.render === 'function') {
                            try {
                                cell = c.render(raw, 'export', row, {});
                            } catch (e1) {
                                cell = raw;
                            }
                        } else {
                            cell = raw;
                        }
                        if (cell !== null && cell !== undefined && typeof cell === 'object') {
                            try {
                                cell = JSON.stringify(cell);
                            } catch (e2) {
                                cell = String(cell);
                            }
                        }
                        return amazonAdsCsvEscapeField(cell);
                    }).join(',');
                });
                return '\uFEFF' + headerLine + '\n' + bodyLines.join('\n') + '\n';
            }

            function amazonAdsClientExportViewCsv() {
                var tid = activeAdsTableId;
                var dt = tid ? amazonAdsDataTables[tid] : null;
                if (!dt || typeof dt.rows !== 'function') {
                    window.alert('Table not loaded');
                    return;
                }
                var colsMetaFull = amazonAdsColumnDefsByTable[tid];
                if (!colsMetaFull || !colsMetaFull.length) {
                    window.alert('No columns');
                    return;
                }
                var omit = ['id', 'profile_id', 'startDate', 'endDate'];
                var exportCols = colsMetaFull.filter(function (c) {
                    return c && c.data && omit.indexOf(c.data) === -1;
                });
                var info = dt.page.info();
                // Server-side: JSON uses recordsFiltered; page.info() exposes that count as recordsDisplay.
                var rawTotal = info.recordsDisplay != null ? info.recordsDisplay : info.recordsFiltered;
                var totalFiltered = parseInt(rawTotal, 10);
                if (!isFinite(totalFiltered) || totalFiltered <= 0) {
                    window.alert('No data to export');
                    return;
                }
                if (totalFiltered > AMAZON_ADS_EXPORT_MAX_ROWS) {
                    window.alert('Too many rows (' + totalFiltered + '). Narrow filters (max ' + AMAZON_ADS_EXPORT_MAX_ROWS + ' per export).');
                    return;
                }
                var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                var p = amazonAdsFilterPayload();
                var searchVal = typeof dt.search === 'function' ? String(dt.search() || '') : '';
                var ord = dt.order();
                var orderCol = ord && ord.length ? ord[0][0] : 0;
                var orderDir = ord && ord.length ? ord[0][1] : 'desc';
                var exportBtn = document.getElementById('amazonAdsSectionExportBtn');
                if (exportBtn) {
                    exportBtn.disabled = true;
                }
                var allRows = [];
                var start = 0;
                var draw = 1;
                var url = dataUrlTemplate + encodeURIComponent(activeRawSourceKey);

                function step() {
                    var len = Math.min(AMAZON_ADS_EXPORT_CHUNK, totalFiltered - start);
                    if (len <= 0) {
                        finish();
                        return;
                    }
                    var payload = amazonAdsBuildRawDataAjaxPayload(draw, start, len, searchVal, orderCol, orderDir, colsMetaFull, p, token);
                    jQuery.ajax({
                        url: url,
                        type: 'POST',
                        headers: { 'X-CSRF-TOKEN': token },
                        data: payload
                    }).done(function (json) {
                        if (json && json.error) {
                            window.alert(String(json.error));
                            if (exportBtn) {
                                exportBtn.disabled = false;
                            }
                            return;
                        }
                        var chunk = (json && json.data) ? json.data : [];
                        if (!chunk.length) {
                            if (allRows.length < totalFiltered) {
                                window.alert('Export stopped: server returned no rows while ' + totalFiltered + ' were expected.');
                            }
                            if (exportBtn) {
                                exportBtn.disabled = false;
                            }
                            return;
                        }
                        chunk.forEach(function (r) {
                            allRows.push(r);
                        });
                        start += chunk.length;
                        draw += 1;
                        if (allRows.length >= totalFiltered || chunk.length < len) {
                            finish();
                            return;
                        }
                        step();
                    }).fail(function (xhr) {
                        window.alert('Export failed (HTTP ' + (xhr && xhr.status) + ').');
                        if (exportBtn) {
                            exportBtn.disabled = false;
                        }
                    });
                }

                function finish() {
                    if (exportBtn) {
                        exportBtn.disabled = false;
                    }
                    if (!allRows.length) {
                        window.alert('No rows returned.');
                        return;
                    }
                    var csv = amazonAdsBuildCsvFromRows(allRows, exportCols);
                    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
                    var blobUrl = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = blobUrl;
                    var tbl = (rawSources[activeRawSourceKey] && rawSources[activeRawSourceKey].table) ? rawSources[activeRawSourceKey].table : 'export';
                    var day = new Date().toISOString().split('T')[0];
                    a.download = 'Amazon_' + tbl + '_Export_' + day + '.csv';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(blobUrl);
                    if (window.toastr && typeof window.toastr.success === 'function') {
                        window.toastr.success('Exported ' + allRows.length + ' row(s) matching filters.');
                    }
                }

                step();
            }

            function amazonAdsPickBidFromRow(row) {
                if (!row || typeof row !== 'object') {
                    return null;
                }
                var m = parseFloat(row.sbid_m);
                if (!isNaN(m) && m > 0) {
                    return m;
                }
                var y = parseFloat(row.yes_sbid);
                if (!isNaN(y) && y > 0) {
                    return y;
                }
                var s = parseFloat(row.sbid);
                if (!isNaN(s) && s > 0) {
                    return s;
                }
                return null;
            }

            function amazonAdsCollectSpSbidPushRows() {
                var dt = amazonAdsDataTables['amazonAdsSpReportsTable'];
                if (!dt || typeof dt.rows !== 'function') {
                    return [];
                }
                var data = dt.rows({ page: 'current' }).data();
                var list = [];
                if (!data || typeof data.toArray !== 'function') {
                    return list;
                }
                data.toArray().forEach(function (row) {
                    if (!row) {
                        return;
                    }
                    var cid = row.campaign_id;
                    if (cid === null || cid === undefined || String(cid).trim() === '') {
                        return;
                    }
                    var bid = amazonAdsPickBidFromRow(row);
                    if (bid === null) {
                        return;
                    }
                    var name = row.campaignName != null ? String(row.campaignName) : '';
                    list.push({
                        campaign_id: String(cid).trim(),
                        bid: bid,
                        campaignName: name
                    });
                });
                return list;
            }

            function amazonAdsUpdateSbidPushButton() {
                var btn = document.getElementById('amazonAdsPushSbidBtn');
                if (!btn) {
                    return;
                }
                var isSp = activeRawSourceKey === 'sp_reports';
                btn.disabled = !isSp;
                btn.title = isSp ? 'Uses sbid_m, yes_sbid, or sbid for rows on this page' : 'Switch to Table: SP — amazon_sp_campaign_reports';
            }

            /** Single-day window: latest day available for this source (server-computed). */
            function amazonAdsSetDateFiltersToLatestForSource(sourceKey) {
                var d = amazonAdsDefaultReportDates[sourceKey];
                var fromEl = document.getElementById('amazonAdsFilterDateFrom');
                var toEl = document.getElementById('amazonAdsFilterDateTo');
                if (!fromEl || !toEl) {
                    return;
                }
                if (d && typeof d === 'string') {
                    fromEl.value = d;
                    toEl.value = d;
                } else {
                    fromEl.value = '';
                    toEl.value = '';
                }
            }

            function amazonAdsFilterPayload() {
                var sumEl = document.getElementById('amazonAdsFilterSummaryRange');
                var u7 = document.getElementById('amazonAdsFilterU7');
                var u2 = document.getElementById('amazonAdsFilterU2');
                var u1 = document.getElementById('amazonAdsFilterU1');
                var st = document.getElementById('amazonAdsFilterCampaignStatus');
                return {
                    date_from: (document.getElementById('amazonAdsFilterDateFrom') || {}).value || '',
                    date_to: (document.getElementById('amazonAdsFilterDateTo') || {}).value || '',
                    summary_report_range: sumEl ? (sumEl.value || '') : '',
                    filter_u7: u7 ? (u7.value || '') : '',
                    filter_u2: u2 ? (u2.value || '') : '',
                    filter_u1: u1 ? (u1.value || '') : '',
                    filter_campaign_status: st ? (st.value || '') : ''
                };
            }

            function amazonAdsReloadActiveGrid() {
                if (activeAdsTableId && amazonAdsDataTables[activeAdsTableId]) {
                    try {
                        amazonAdsDataTables[activeAdsTableId].ajax.reload();
                    } catch (e) {}
                }
            }

            function amazonAdsShowSource(sourceKey) {
                var panels = document.querySelectorAll('#amazonAdsSourcePanels .amazon-source-pane');
                panels.forEach(function (pane) {
                    var match = pane.getAttribute('data-pane-for') === sourceKey;
                    pane.classList.toggle('d-none', !match);
                });
                var pane = document.querySelector('#amazonAdsSourcePanels .amazon-source-pane[data-pane-for="' + sourceKey + '"]');
                if (!pane) {
                    return;
                }
                var table = pane.querySelector('table[data-raw-source]');
                if (!table || !table.id) {
                    return;
                }
                activeAdsTableId = table.id;
                activeRawSourceKey = table.getAttribute('data-raw-source') || 'sp_reports';
                amazonAdsUpdateSbidPushButton();
                initTable(table.id, table.getAttribute('data-raw-source'));
            }

            /** Short labels for Amazon ad_type values in the grid (display only; sort/filter use raw). */
            /** U7%/U2%/U1%: rounded %; red below 66, green 66–99, pink above 99. */
            function renderUtilPercentColumn(data, type) {
                if (data === null || data === undefined || data === '') {
                    if (type === 'display') {
                        return '<span class="text-muted">-</span>';
                    }
                    if (type === 'sort' || type === 'type') {
                        return -1;
                    }
                    return '';
                }
                var n = typeof data === 'number' ? data : parseFloat(data, 10);
                if (isNaN(n)) {
                    if (type === 'display') {
                        return '<span class="text-muted">-</span>';
                    }
                    if (type === 'sort' || type === 'type') {
                        return -1;
                    }
                    return '';
                }
                var rounded = Math.round(n);
                if (type === 'sort' || type === 'type') {
                    return rounded;
                }
                if (type === 'export' || type === 'excel' || type === 'pdf') {
                    return String(rounded) + '%';
                }
                var color;
                if (rounded > 99) {
                    color = '#db2777';
                } else if (rounded >= 66) {
                    color = '#16a34a';
                } else {
                    color = '#dc2626';
                }
                return '<span style="color:' + color + ';font-weight:600;">' + rounded + '%</span>';
            }

            /** SBID from server when U2/U1 both red or both pink; otherwise null → -- */
            function renderSbidColumn(data, type) {
                if (type === 'sort' || type === 'type') {
                    var n = typeof data === 'number' ? data : parseFloat(data, 10);
                    return isNaN(n) ? -1 : n;
                }
                if (type === 'export' || type === 'excel' || type === 'pdf') {
                    if (data === null || data === undefined || data === '') {
                        return '';
                    }
                    var x = typeof data === 'number' ? data : parseFloat(data, 10);
                    return isNaN(x) ? '' : String(x);
                }
                if (type !== 'display') {
                    return data;
                }
                if (data === null || data === undefined || data === '') {
                    return '<span class="text-muted">--</span>';
                }
                var num = typeof data === 'number' ? data : parseFloat(data, 10);
                if (isNaN(num)) {
                    return '<span class="text-muted">--</span>';
                }
                return '<span class="fw-semibold">' + num.toFixed(2) + '</span>';
            }

            function formatAdTypeDisplay(data, type) {
                if (type === 'export' || type === 'excel' || type === 'pdf') {
                    if (data === null || data === undefined) {
                        return '';
                    }
                    var se = String(data).trim();
                    var ue = se.toUpperCase();
                    if (ue === 'SPONSORED_PRODUCTS') {
                        return 'SP';
                    }
                    if (ue === 'SPONSORED_BRANDS') {
                        return 'SB';
                    }
                    return se;
                }
                if (type !== 'display') {
                    return data;
                }
                if (data === null || data === undefined) {
                    return '';
                }
                var s = String(data).trim();
                var u = s.toUpperCase();
                if (u === 'SPONSORED_PRODUCTS') {
                    return 'SP';
                }
                if (u === 'SPONSORED_BRANDS') {
                    return 'SB';
                }
                return s;
            }

            /**
             * ACOS % tier colors (same breakpoints as SBGT / AmazonAcosSbgtRule).
             * pink = best (≤10%), green, blue, yellow, red = worst (≥40%).
             */
            function amazonAdsAcosTierColor(acos) {
                var a = typeof acos === 'number' ? acos : parseFloat(acos, 10);
                if (isNaN(a)) {
                    return '#6b7280';
                }
                if (a >= 40) {
                    return '#dc2626';
                }
                if (a > 30) {
                    return '#ca8a04';
                }
                if (a > 20) {
                    return '#2563eb';
                }
                if (a > 10) {
                    return '#16a34a';
                }
                return '#db2777';
            }

            /** SBGT tier 1 / 2 / 4 / 8 / 12 → red … pink (same scale as ACOS tiers). */
            function amazonAdsSbgtTierColor(sbgt) {
                var s = parseInt(sbgt, 10);
                if (s === 1) {
                    return '#dc2626';
                }
                if (s === 2) {
                    return '#ca8a04';
                }
                if (s === 4) {
                    return '#2563eb';
                }
                if (s === 8) {
                    return '#16a34a';
                }
                if (s === 12) {
                    return '#db2777';
                }
                return '#6b7280';
            }

            function buildColumns(sourceKey) {
                return rawSources[sourceKey].columns.map(function (c) {
                    var col = { data: c, title: c, defaultContent: '' };
                    if (c === 'ad_type') {
                        col.render = function (data, type) {
                            return formatAdTypeDisplay(data, type);
                        };
                    }
                    if (c === 'impressions') {
                        col.title = 'Impr';
                    }
                    if (c === 'unitsSoldSameSku30d') {
                        col.title = 'Units same-SKU 30d';
                    }
                    if (c === 'campaignStatus') {
                        col.title = 'Status';
                        col.render = function (data, type) {
                            var raw = data === null || data === undefined ? '' : String(data).trim();
                            var enabled = raw.toUpperCase() === 'ENABLED';
                            if (type === 'sort' || type === 'type') {
                                return enabled ? 1 : 0;
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                return raw;
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            var color = enabled ? '#16a34a' : '#dc2626';
                            var titleEsc = raw.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            var label = raw === '' ? 'Unknown' : raw;
                            var labelEsc = label.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            return '<span class="d-inline-block align-middle rounded-circle" style="width:10px;height:10px;background-color:' + color + ';" title="' + titleEsc + '" role="img" aria-label="' + labelEsc + '"></span>';
                        };
                    }
                    if (c === 'last_sbid') {
                        col.title = 'Last bid';
                    }
                    if (c === 'bgt') {
                        col.title = 'BGT';
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var nb = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(nb) ? -1 : Math.round(nb);
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                if (data === null || data === undefined || data === '') {
                                    return '';
                                }
                                var xe = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(xe) ? '' : String(Math.round(xe));
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var num = typeof data === 'number' ? data : parseFloat(data, 10);
                            if (isNaN(num)) {
                                return '<span class="text-muted">--</span>';
                            }
                            return '<span class="fw-semibold">' + String(Math.round(num)) + '</span>';
                        };
                    }
                    if (c === 'sbgt') {
                        col.title = 'SBGT';
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var ns = parseInt(data, 10);
                                return isNaN(ns) ? -1 : ns;
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                if (data === null || data === undefined || data === '') {
                                    return '';
                                }
                                var xs = parseInt(data, 10);
                                return isNaN(xs) ? '' : String(xs);
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var ti = parseInt(data, 10);
                            if (isNaN(ti)) {
                                return '<span class="text-muted">--</span>';
                            }
                            var cs = amazonAdsSbgtTierColor(ti);
                            return '<span class="fw-semibold" style="color:' + cs + ';">' + String(ti) + '</span>';
                        };
                    }
                    if (c === 'Prchase') {
                        col.title = 'Sold';
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var np = typeof data === 'number' ? data : parseInt(data, 10);
                                return isNaN(np) ? -1 : np;
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                if (data === null || data === undefined || data === '') {
                                    return '';
                                }
                                var xp = parseInt(data, 10);
                                return isNaN(xp) ? '' : String(xp);
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var ip = parseInt(data, 10);
                            if (isNaN(ip)) {
                                return '<span class="text-muted">--</span>';
                            }
                            return '<span class="fw-semibold">' + String(ip) + '</span>';
                        };
                    }
                    if (c === 'ACOS') {
                        col.title = 'ACOS';
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var na = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(na) ? -1 : Math.round(na);
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                if (data === null || data === undefined || data === '') {
                                    return '';
                                }
                                var xa = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(xa) ? '' : String(Math.round(xa));
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var pa = typeof data === 'number' ? data : parseFloat(data, 10);
                            if (isNaN(pa)) {
                                return '<span class="text-muted">--</span>';
                            }
                            var rpa = Math.round(pa);
                            var ca = amazonAdsAcosTierColor(rpa);
                            return '<span class="fw-semibold" style="color:' + ca + ';">' + String(rpa) + '%</span>';
                        };
                    }
                    if (c === 'sales') {
                        col.title = 'Sales';
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var nsl = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(nsl) ? -1 : Math.round(nsl);
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                if (data === null || data === undefined || data === '') {
                                    return '';
                                }
                                var xsl = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(xsl) ? '' : String(Math.round(xsl));
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var fsl = typeof data === 'number' ? data : parseFloat(data, 10);
                            if (isNaN(fsl)) {
                                return '<span class="text-muted">--</span>';
                            }
                            return '<span class="fw-semibold">' + String(Math.round(fsl)) + '</span>';
                        };
                    }
                    if (c === 'cost') {
                        col.title = 'SP L30';
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var ncst = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(ncst) ? -1 : Math.round(ncst);
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                if (data === null || data === undefined || data === '') {
                                    return '';
                                }
                                var xcst = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(xcst) ? '' : String(Math.round(xcst));
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var fcst = typeof data === 'number' ? data : parseFloat(data, 10);
                            if (isNaN(fcst)) {
                                return '<span class="text-muted">--</span>';
                            }
                            return '<span class="fw-semibold">' + String(Math.round(fcst)) + '</span>';
                        };
                    }
                    if (c === 'L7spend' || c === 'L2spend' || c === 'L1spend') {
                        if (c === 'L7spend') {
                            col.title = 'L7 SP';
                        } else if (c === 'L2spend') {
                            col.title = 'L2 SP';
                        } else {
                            col.title = 'L1 SP';
                        }
                        col.orderable = false;
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var nl = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(nl) ? -1 : Math.round(nl);
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                if (data === null || data === undefined || data === '') {
                                    return '';
                                }
                                var xl = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(xl) ? '' : String(Math.round(xl));
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var nld = typeof data === 'number' ? data : parseFloat(data, 10);
                            if (isNaN(nld)) {
                                return '<span class="text-muted">--</span>';
                            }
                            return '<span class="fw-semibold">' + String(Math.round(nld)) + '</span>';
                        };
                    }
                    if (c === 'U7%' || c === 'U2%' || c === 'U1%') {
                        col.render = function (data, type) {
                            return renderUtilPercentColumn(data, type);
                        };
                    }
                    if (c === 'CPC3') {
                        col.title = 'CPC 3';
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var n3 = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(n3) ? -1 : n3;
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                if (data === null || data === undefined || data === '') {
                                    return '';
                                }
                                var x3 = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(x3) ? '' : x3.toFixed(2);
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var n3d = typeof data === 'number' ? data : parseFloat(data, 10);
                            if (isNaN(n3d)) {
                                return '<span class="text-muted">--</span>';
                            }
                            return n3d.toFixed(2);
                        };
                    }
                    if (c === 'CPC2') {
                        col.title = 'CPC 2';
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var n2 = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(n2) ? -1 : n2;
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                if (data === null || data === undefined || data === '') {
                                    return '';
                                }
                                var x2 = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(x2) ? '' : x2.toFixed(2);
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var n2d = typeof data === 'number' ? data : parseFloat(data, 10);
                            if (isNaN(n2d)) {
                                return '<span class="text-muted">--</span>';
                            }
                            return n2d.toFixed(2);
                        };
                    }
                    if (c === 'costPerClick') {
                        col.title = 'CPC 1';
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var ns = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(ns) ? -1 : ns;
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                if (data === null || data === undefined || data === '') {
                                    return '';
                                }
                                var xe = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(xe) ? '' : xe.toFixed(2);
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var nc = typeof data === 'number' ? data : parseFloat(data, 10);
                            if (isNaN(nc)) {
                                return '<span class="text-muted">--</span>';
                            }
                            return nc.toFixed(2);
                        };
                    }
                    if (c === 'sales30d') {
                        col.title = 'Sales 30d';
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var nsa = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(nsa) ? -1 : Math.round(nsa);
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                if (data === null || data === undefined || data === '') {
                                    return '';
                                }
                                var xsa = typeof data === 'number' ? data : parseFloat(data, 10);
                                return isNaN(xsa) ? '' : String(Math.round(xsa));
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var nca = typeof data === 'number' ? data : parseFloat(data, 10);
                            if (isNaN(nca)) {
                                return '<span class="text-muted">--</span>';
                            }
                            return '<span class="fw-semibold">' + String(Math.round(nca)) + '</span>';
                        };
                    }
                    if (c === 'sbid') {
                        col.title = 'SBID';
                        col.render = function (data, type) {
                            return renderSbidColumn(data, type);
                        };
                    }
                    return col;
                });
            }

            function initTable(tableId, sourceKey) {
                if (initialized[tableId]) {
                    return;
                }
                if (typeof jQuery === 'undefined' || !jQuery.fn.DataTable) {
                    return;
                }
                var $ = jQuery;
                var $t = $('#' + tableId);
                if (!$t.length) {
                    return;
                }

                var meta = rawSources[sourceKey];
                if (!meta || !meta.columns || !meta.columns.length) {
                    initialized[tableId] = true;
                    var tbl = meta && meta.table ? meta.table : sourceKey;
                    $t.closest('.amazon-raw-table-wrap').html(
                        '<p class="text-muted mb-0">No columns available (table missing or empty schema): <code>' + tbl + '</code></p>'
                    );
                    return;
                }

                var cols = buildColumns(sourceKey);
                amazonAdsColumnDefsByTable[tableId] = cols;
                initialized[tableId] = true;

                var hiddenRawColumnKeys = ['id', 'profile_id', 'campaign_id', 'report_date_range', 'yes_sbid', 'ad_type', 'date', 'startDate', 'endDate'];
                var hiddenColumnDefs = [];
                hiddenRawColumnKeys.forEach(function (key) {
                    for (var ci = 0; ci < cols.length; ci++) {
                        if (cols[ci].data === key) {
                            hiddenColumnDefs.push({ targets: ci, visible: false });
                            break;
                        }
                    }
                });
                ['U7%', 'U2%', 'U1%', 'CPC3', 'CPC2', 'L7spend', 'L2spend', 'L1spend'].forEach(function (key) {
                    for (var uj = 0; uj < cols.length; uj++) {
                        if (cols[uj].data === key) {
                            hiddenColumnDefs.push({ targets: uj, orderable: false, searchable: false });
                            break;
                        }
                    }
                });

                var dt = $t.DataTable({
                    processing: true,
                    serverSide: true,
                    responsive: false,
                    autoWidth: false,
                    searching: true,
                    pageLength: 25,
                    lengthMenu: [[25, 50, 100, 250, 500], [25, 50, 100, 250, 500]],
                    order: [[0, 'desc']],
                    scrollX: true,
                    scrollCollapse: true,
                    columnDefs: hiddenColumnDefs,
                    ajax: {
                        url: dataUrlTemplate + encodeURIComponent(sourceKey),
                        type: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
                        },
                        data: function (d) {
                            var p = amazonAdsFilterPayload();
                            d.date_from = p.date_from;
                            d.date_to = p.date_to;
                            d.summary_report_range = p.summary_report_range;
                            d.filter_u7 = p.filter_u7;
                            d.filter_u2 = p.filter_u2;
                            d.filter_u1 = p.filter_u1;
                            d.filter_campaign_status = p.filter_campaign_status;
                            d._token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                        },
                        dataSrc: function (json) {
                            return json && json.data ? json.data : [];
                        },
                        error: function (xhr) {
                            console.error('Amazon Ads raw DataTable error', xhr.status, xhr.responseText);
                        }
                    },
                    columns: cols
                });
                amazonAdsDataTables[tableId] = dt;
            }

            loadScriptsSequentially(0, function () {
                if (typeof jQuery === 'undefined') {
                    console.error('jQuery is required for DataTables');
                    return;
                }
                jQuery(function () {
                    var typeSel = document.getElementById('amazonAdsFilterReportType');
                    var initialSource = (typeSel && typeSel.value) ? typeSel.value : 'sp_reports';
                    activeRawSourceKey = initialSource;
                    amazonAdsSetDateFiltersToLatestForSource(initialSource);
                    amazonAdsUpdateSbidPushButton();
                    amazonAdsShowSource(initialSource);

                    if (typeSel) {
                        typeSel.addEventListener('change', function () {
                            var sk = this.value;
                            var pane = document.querySelector('#amazonAdsSourcePanels .amazon-source-pane[data-pane-for="' + sk + '"]');
                            var tblEl = pane && pane.querySelector('table[data-raw-source]');
                            var tid = tblEl && tblEl.id;
                            var alreadyInited = tid && initialized[tid];
                            activeRawSourceKey = sk;
                            amazonAdsSetDateFiltersToLatestForSource(sk);
                            amazonAdsUpdateSbidPushButton();
                            amazonAdsShowSource(sk);
                            if (alreadyInited) {
                                amazonAdsReloadActiveGrid();
                            }
                        });
                    }

                    var pushBtn = document.getElementById('amazonAdsPushSbidBtn');
                    if (pushBtn) {
                        pushBtn.addEventListener('click', function () {
                            var statusEl = document.getElementById('amazonAdsSbidPushStatus');
                            if (activeRawSourceKey !== 'sp_reports') {
                                if (statusEl) {
                                    statusEl.textContent = 'Switch to the SP table first.';
                                }
                                return;
                            }
                            var rows = amazonAdsCollectSpSbidPushRows();
                            if (!rows.length) {
                                if (statusEl) {
                                    statusEl.textContent = 'No rows on this page with campaign_id and a positive sbid_m / yes_sbid / sbid.';
                                }
                                return;
                            }
                            var uniq = {};
                            rows.forEach(function (r) {
                                uniq[r.campaign_id] = r;
                            });
                            var deduped = Object.keys(uniq).map(function (k) {
                                return uniq[k];
                            });
                            if (deduped.length > 100) {
                                if (statusEl) {
                                    statusEl.textContent = 'Too many distinct campaigns on this page (' + deduped.length + '). Narrow filters or page size (max 100).';
                                }

                                return;
                            }
                            if (!window.confirm('Push SBID to Amazon for ' + deduped.length + ' SP campaign(s) on this page?')) {
                                return;
                            }
                            pushBtn.disabled = true;
                            if (statusEl) {
                                statusEl.textContent = 'Pushing…';
                            }
                            var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                            fetch(pushSpSbidsUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': token,
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({ rows: deduped })
                            })
                                .then(function (res) {
                                    return res.json().then(function (body) {
                                        return { ok: res.ok, status: res.status, body: body };
                                    });
                                })
                                .then(function (out) {
                                    var b = out.body || {};
                                    var parts = [];
                                    if (b.keyword_http_status != null) {
                                        parts.push('keywords HTTP ' + b.keyword_http_status);
                                    }
                                    if (b.target_http_status != null) {
                                        parts.push('targets HTTP ' + b.target_http_status);
                                    }
                                    var msg = (b.message || (out.ok ? 'Done.' : 'Request failed.')) + (parts.length ? ' (' + parts.join(', ') + ')' : '');
                                    if (statusEl) {
                                        statusEl.textContent = msg;
                                    }
                                })
                                .catch(function (err) {
                                    console.error(err);
                                    if (statusEl) {
                                        statusEl.textContent = 'Network or server error.';
                                    }
                                })
                                .finally(function () {
                                    amazonAdsUpdateSbidPushButton();
                                });
                        });
                    }

                    var summarySel = document.getElementById('amazonAdsFilterSummaryRange');
                    if (summarySel) {
                        summarySel.addEventListener('change', function () {
                            amazonAdsReloadActiveGrid();
                        });
                    }
                    ['amazonAdsFilterU7', 'amazonAdsFilterU2', 'amazonAdsFilterU1', 'amazonAdsFilterCampaignStatus'].forEach(function (id) {
                        var el = document.getElementById(id);
                        if (el) {
                            el.addEventListener('change', function () {
                                amazonAdsReloadActiveGrid();
                            });
                        }
                    });

                    var applyBtn = document.getElementById('amazonAdsFilterApply');
                    if (applyBtn) {
                        applyBtn.addEventListener('click', function () {
                            amazonAdsReloadActiveGrid();
                        });
                    }
                    var exportViewBtn = document.getElementById('amazonAdsSectionExportBtn');
                    if (exportViewBtn) {
                        exportViewBtn.addEventListener('click', function () {
                            amazonAdsClientExportViewCsv();
                        });
                    }

                    var clearBtn = document.getElementById('amazonAdsFilterClear');
                    if (clearBtn) {
                        clearBtn.addEventListener('click', function () {
                            var a = document.getElementById('amazonAdsFilterDateFrom');
                            var b = document.getElementById('amazonAdsFilterDateTo');
                            var s = document.getElementById('amazonAdsFilterSummaryRange');
                            var u7 = document.getElementById('amazonAdsFilterU7');
                            var u2 = document.getElementById('amazonAdsFilterU2');
                            var u1 = document.getElementById('amazonAdsFilterU1');
                            var st = document.getElementById('amazonAdsFilterCampaignStatus');
                            if (a) { a.value = ''; }
                            if (b) { b.value = ''; }
                            if (s) { s.value = ''; }
                            if (u7) { u7.value = ''; }
                            if (u2) { u2.value = ''; }
                            if (u1) { u1.value = ''; }
                            if (st) { st.value = ''; }
                            amazonAdsReloadActiveGrid();
                        });
                    }
                });
            });
        })();
    </script>
@endsection
