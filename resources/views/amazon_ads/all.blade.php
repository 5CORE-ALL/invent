@extends('layouts.vertical', ['title' => 'Amazon Ads All', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        .amazon-ads-all .nav-tabs .nav-link {
            font-weight: 600;
        }
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
                Tabs <strong>SP</strong>, <strong>SB</strong>, and <strong>SD</strong> each load <strong>every column</strong> from
                <code>amazon_sp_campaign_reports</code>, <code>amazon_sb_campaign_reports</code>, and <code>amazon_sd_campaign_reports</code> (server-side paging; use length menu up to 500 rows per request to walk the full table). <code>yes_sbid</code> / bid fields are pinned left when present. Two extra tabs: <code>amazon_bid_caps</code>, <code>amazon_fbm_targeting_checks</code>.
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
                        <p class="text-muted small mb-0 mt-2">
                            <strong>SP / SB / SD:</strong> Calendar range matches (1) daily keys in <code>report_date_range</code> (first 10 chars <code>YYYY-MM-DD</code>), (2) the <code>date</code> column if set, (3) summary rows (<code>L30</code>, etc.) whose <code>startDate</code>/<code>endDate</code> overlap your range. For exact <code>L30</code> only, use <em>Report range</em>. <strong>Bid caps / FBM:</strong> <code>created_at</code>. Data loads via POST so filters always reach the server.
                        </p>
                    </div>

                    <ul class="nav nav-tabs nav-bordered mb-3" id="amazonAdsAllTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="tab-sp-reports" data-bs-toggle="tab"
                               href="#pane-sp-reports" role="tab"
                               aria-controls="pane-sp-reports" aria-selected="true">
                                SP — full table
                                <span class="badge bg-success ms-1 source-pill">amazon_sp_campaign_reports</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="tab-sb-reports" data-bs-toggle="tab"
                               href="#pane-sb-reports" role="tab"
                               aria-controls="pane-sb-reports" aria-selected="false">
                                SB — full table
                                <span class="badge bg-primary ms-1 source-pill">amazon_sb_campaign_reports</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="tab-sd-reports" data-bs-toggle="tab"
                               href="#pane-sd-reports" role="tab"
                               aria-controls="pane-sd-reports" aria-selected="false">
                                SD — full table
                                <span class="badge bg-info ms-1 source-pill">amazon_sd_campaign_reports</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="tab-bid-caps" data-bs-toggle="tab"
                               href="#pane-bid-caps" role="tab"
                               aria-controls="pane-bid-caps" aria-selected="false">
                                Bid caps
                                <span class="badge bg-secondary ms-1 source-pill">amazon_bid_caps</span>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="tab-fbm-targeting" data-bs-toggle="tab"
                               href="#pane-fbm-targeting" role="tab"
                               aria-controls="pane-fbm-targeting" aria-selected="false">
                                FBM targeting
                                <span class="badge bg-secondary ms-1 source-pill">amazon_fbm_targeting_checks</span>
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="amazonAdsAllTabContent">
                        <div class="tab-pane fade show active" id="pane-sp-reports" role="tabpanel"
                             aria-labelledby="tab-sp-reports">
                            <div class="amazon-raw-table-wrap">
                                <table id="amazonAdsSpReportsTable"
                                       class="table table-hover table-striped table-bordered nowrap w-100"
                                       data-raw-source="sp_reports">
                                    <thead><tr></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-sb-reports" role="tabpanel"
                             aria-labelledby="tab-sb-reports">
                            <div class="amazon-raw-table-wrap">
                                <table id="amazonAdsSbReportsTable"
                                       class="table table-hover table-striped table-bordered nowrap w-100"
                                       data-raw-source="sb_reports">
                                    <thead><tr></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-sd-reports" role="tabpanel"
                             aria-labelledby="tab-sd-reports">
                            <div class="amazon-raw-table-wrap">
                                <table id="amazonAdsSdReportsTable"
                                       class="table table-hover table-striped table-bordered nowrap w-100"
                                       data-raw-source="sd_reports">
                                    <thead><tr></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-bid-caps" role="tabpanel"
                             aria-labelledby="tab-bid-caps">
                            <div class="amazon-raw-table-wrap">
                                <table id="amazonAdsBidCapsTable"
                                       class="table table-hover table-striped table-bordered nowrap w-100"
                                       data-raw-source="bid_caps">
                                    <thead><tr></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="pane-fbm-targeting" role="tabpanel"
                             aria-labelledby="tab-fbm-targeting">
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

            var reportTabMap = {
                'sp_reports': '#tab-sp-reports',
                'sb_reports': '#tab-sb-reports',
                'sd_reports': '#tab-sd-reports',
                'bid_caps': '#tab-bid-caps',
                'fbm_targeting': '#tab-fbm-targeting'
            };
            var tabIdToSource = {
                'tab-sp-reports': 'sp_reports',
                'tab-sb-reports': 'sb_reports',
                'tab-sd-reports': 'sd_reports',
                'tab-bid-caps': 'bid_caps',
                'tab-fbm-targeting': 'fbm_targeting'
            };

            function amazonAdsFilterPayload() {
                var sumEl = document.getElementById('amazonAdsFilterSummaryRange');
                return {
                    date_from: (document.getElementById('amazonAdsFilterDateFrom') || {}).value || '',
                    date_to: (document.getElementById('amazonAdsFilterDateTo') || {}).value || '',
                    summary_report_range: sumEl ? (sumEl.value || '') : ''
                };
            }

            function amazonAdsReloadAllGrids() {
                Object.keys(amazonAdsDataTables).forEach(function (tid) {
                    try {
                        amazonAdsDataTables[tid].ajax.reload();
                    } catch (e) {}
                });
            }

            function amazonAdsShowTabForSource(sourceKey) {
                var sel = reportTabMap[sourceKey];
                if (!sel) {
                    return;
                }
                var trigger = document.querySelector(sel);
                if (trigger && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                    bootstrap.Tab.getOrCreateInstance(trigger).show();
                }
            }

            function buildColumns(sourceKey) {
                return rawSources[sourceKey].columns.map(function (c) {
                    return { data: c, title: c, defaultContent: '' };
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

            function onTabTarget(targetSelector) {
                var pane = document.querySelector(targetSelector);
                if (!pane) {
                    return;
                }
                var $table = pane.querySelector('table[data-raw-source]');
                if (!$table || !$table.id) {
                    return;
                }
                initTable($table.id, $table.getAttribute('data-raw-source'));
            }

            loadScriptsSequentially(0, function () {
                if (typeof jQuery === 'undefined') {
                    console.error('jQuery is required for DataTables');
                    return;
                }
                jQuery(function () {
                    onTabTarget('#pane-sp-reports');

                    var typeSel = document.getElementById('amazonAdsFilterReportType');
                    if (typeSel) {
                        typeSel.addEventListener('change', function () {
                            amazonAdsShowTabForSource(this.value);
                        });
                    }

                    var summarySel = document.getElementById('amazonAdsFilterSummaryRange');
                    if (summarySel) {
                        summarySel.addEventListener('change', function () {
                            amazonAdsReloadAllGrids();
                        });
                    }

                    var applyBtn = document.getElementById('amazonAdsFilterApply');
                    if (applyBtn) {
                        applyBtn.addEventListener('click', function () {
                            amazonAdsReloadAllGrids();
                        });
                    }
                    var clearBtn = document.getElementById('amazonAdsFilterClear');
                    if (clearBtn) {
                        clearBtn.addEventListener('click', function () {
                            var a = document.getElementById('amazonAdsFilterDateFrom');
                            var b = document.getElementById('amazonAdsFilterDateTo');
                            var s = document.getElementById('amazonAdsFilterSummaryRange');
                            if (a) { a.value = ''; }
                            if (b) { b.value = ''; }
                            if (s) { s.value = ''; }
                            amazonAdsReloadAllGrids();
                        });
                    }
                });

                var tabEl = document.getElementById('amazonAdsAllTabs');
                if (tabEl) {
                    tabEl.addEventListener('shown.bs.tab', function (e) {
                        var href = e.target.getAttribute('href');
                        if (href) {
                            onTabTarget(href);
                        }
                        var src = tabIdToSource[e.target.id];
                        var typeSel = document.getElementById('amazonAdsFilterReportType');
                        if (src && typeSel) {
                            typeSel.value = src;
                        }
                    });
                }
            });
        })();
    </script>
@endsection
