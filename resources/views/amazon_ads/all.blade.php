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
        .amazon-ads-all .amazon-ads-filters .form-label {
            font-size: 0.8rem;
            font-weight: 600;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Amazon Ads', 'page_title' => 'Amazon Ads All'])

    <div class="row amazon-ads-all mb-3">
        <div class="col-12">
            <div class="alert alert-secondary mb-0 utilized-kw-note">
                <strong>Utilized KW</strong>
                (<code>/amazon/utilized/kw</code>) loads merged rows in
                <code>AmazonSpBudgetController::getAmazonUtilizedAdsData</code>, mainly from
                <code>amazon_sp_campaign_reports</code> and <code>amazon_sb_campaign_reports</code>,
                plus <code>amazon_acos_action_history</code>, <code>amazon_datsheets</code>, <code>product_master</code>, etc.
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
                        <p class="text-muted small mb-0 mt-2">
                            <strong>SP / SB / SD:</strong> Calendar range matches (1) daily keys in <code>report_date_range</code> (first 10 chars <code>YYYY-MM-DD</code>), (2) the <code>date</code> column if set, (3) summary rows (<code>L30</code>, etc.) whose <code>startDate</code>/<code>endDate</code> overlap your range. For exact <code>L30</code> only, use <em>Report range</em>. <strong>U7/U2/U1 filters</strong> apply on campaign tables only (same formula as the columns: <code>COALESCE(spend,cost)</code> when both exist). <strong>Status</strong> filters <code>campaignStatus</code> when present. <strong>Bid caps / FBM:</strong> <code>created_at</code> (status filter no-op if no <code>campaignStatus</code> column). Data loads via POST so filters always reach the server.
                        </p>
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
            var dataUrlTemplate = @json(url('/amazon-ads/raw-data')) + '/';

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
            var activeAdsTableId = null;

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
                initTable(table.id, table.getAttribute('data-raw-source'));
            }

            /** Short labels for Amazon ad_type values in the grid (display only; sort/filter use raw). */
            /** U7%/U2%/U1%: rounded %; red below 66, green 66–99, purple above 99. */
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
                    color = '#9333ea';
                } else if (rounded >= 66) {
                    color = '#16a34a';
                } else {
                    color = '#dc2626';
                }
                return '<span style="color:' + color + ';font-weight:600;">' + rounded + '%</span>';
            }

            function formatAdTypeDisplay(data, type) {
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

            function buildColumns(sourceKey) {
                return rawSources[sourceKey].columns.map(function (c) {
                    var col = { data: c, title: c, defaultContent: '' };
                    if (c === 'ad_type') {
                        col.render = function (data, type) {
                            return formatAdTypeDisplay(data, type);
                        };
                    }
                    if (c === 'U7%' || c === 'U2%' || c === 'U1%') {
                        col.render = function (data, type) {
                            return renderUtilPercentColumn(data, type);
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
                initialized[tableId] = true;

                var hiddenRawColumnKeys = ['id'];
                var hiddenColumnDefs = [];
                hiddenRawColumnKeys.forEach(function (key) {
                    for (var ci = 0; ci < cols.length; ci++) {
                        if (cols[ci].data === key) {
                            hiddenColumnDefs.push({ targets: ci, visible: false });
                            break;
                        }
                    }
                });
                ['U7%', 'U2%', 'U1%'].forEach(function (key) {
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
                    amazonAdsShowSource(initialSource);

                    if (typeSel) {
                        typeSel.addEventListener('change', function () {
                            amazonAdsShowSource(this.value);
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
