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
        /* Full width grid: avoid clipping wide raw tables (all DB columns, incl. yes_sbid / last_sbid; sbid_m hidden from grid) */
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
        .amazon-ads-all .amazon-ads-toolbar {
            margin-bottom: 0.35rem;
        }
        .amazon-ads-all .amazon-ads-filters {
            padding: 0.5rem 0.6rem !important;
        }
        .amazon-ads-all .amazon-ads-filters .form-label {
            font-size: 0.68rem;
            font-weight: 600;
            margin-bottom: 0.1rem;
            line-height: 1.1;
            white-space: nowrap;
        }
        .amazon-ads-all .amazon-ads-filters .form-select-sm,
        .amazon-ads-all .amazon-ads-filters .form-control-sm {
            padding-top: 0.2rem;
            padding-bottom: 0.2rem;
            font-size: 0.78rem;
        }
        /* U7 pie: mid-row, caption left of chart (no stacked title — saves height) */
        .amazon-ads-all .amazon-u7-pie-box {
            min-width: 0;
            max-width: none;
        }
        .amazon-ads-all .amazon-u7-pie-box .amazon-u7-pie-caption {
            font-size: 0.62rem;
            line-height: 1.15;
            margin-bottom: 0 !important;
            max-width: 2.5rem;
            text-align: right;
            flex-shrink: 0;
        }
        .amazon-ads-all .amazon-u7-pie-box .amazon-u7-pie-chart {
            width: 92px;
            height: 100px;
            margin: 0;
            flex-shrink: 0;
        }
        .amazon-ads-all .amazon-sbid-push-panel {
            border-left: 2px solid #0d6efd;
            padding-left: 0.45rem;
            margin-top: 0.35rem !important;
            padding-top: 0.35rem !important;
        }
        .amazon-ads-all .amazon-source-pane > p.text-muted {
            margin-bottom: 0.25rem !important;
            font-size: 0.72rem;
        }
        /* DataTables Bootstrap 5: hide sort arrows on all headers (ordering still works if enabled). */
        .amazon-ads-all table.dataTable thead > tr > th.sorting:before,
        .amazon-ads-all table.dataTable thead > tr > th.sorting:after,
        .amazon-ads-all table.dataTable thead > tr > th.sorting_asc:before,
        .amazon-ads-all table.dataTable thead > tr > th.sorting_asc:after,
        .amazon-ads-all table.dataTable thead > tr > th.sorting_desc:before,
        .amazon-ads-all table.dataTable thead > tr > th.sorting_desc:after,
        .amazon-ads-all table.dataTable thead > tr > th.sorting_asc_disabled:before,
        .amazon-ads-all table.dataTable thead > tr > th.sorting_asc_disabled:after,
        .amazon-ads-all table.dataTable thead > tr > th.sorting_desc_disabled:before,
        .amazon-ads-all table.dataTable thead > tr > th.sorting_desc_disabled:after {
            display: none !important;
            content: none !important;
        }
        .amazon-ads-all table.dataTable thead > tr > th.sorting,
        .amazon-ads-all table.dataTable thead > tr > th.sorting_asc,
        .amazon-ads-all table.dataTable thead > tr > th.sorting_desc,
        .amazon-ads-all table.dataTable thead > tr > th.sorting_asc_disabled,
        .amazon-ads-all table.dataTable thead > tr > th.sorting_desc_disabled {
            background-image: none !important;
            padding-right: 0.75rem !important;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Amazon Ads', 'page_title' => 'Amazon Ads All'])

    <div class="row amazon-ads-all amazon-ads-toolbar">
        <div class="col-12 d-flex flex-wrap justify-content-end align-items-center gap-1">
            <button type="button" class="btn btn-sm btn-outline-primary" id="amazonAdsBgtRuleBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsBgtRuleModal" title="Edit ACOS boundaries and SBGT tier amounts used for suggested budgets">BGT RULE</button>
            <button type="button" class="btn btn-sm btn-outline-primary" id="amazonAdsSbidRuleBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsSbidRuleModal" title="Edit U2%/U1% thresholds and CPC multipliers for suggested SBID (grid and bid jobs)">SBID RULE</button>
            <span class="text-muted small d-none d-md-inline" title="Fetches every row matching your filters (500 per request); same sort and search as the grid.">Export all filtered rows (CSV).</span>
            <button type="button" class="btn btn-sm btn-primary" id="amazonAdsSectionExportBtn" title="Download all rows matching current filters and DataTables search (max 50k)">Export view</button>
        </div>
    </div>

    <div class="row amazon-ads-all mb-3 d-none">
        <div class="col-12">
            <div class="alert alert-secondary mb-0 utilized-kw-note">
                <strong>Utilized KW</strong>
                (<code>/amazon/utilized/kw/ads/data</code>) loads merged rows in
                <code>AmazonSpBudgetController::getAmazonUtilizedAdsData</code>, mainly from
                <code>amazon_sp_campaign_reports</code> and <code>amazon_sb_campaign_reports</code>,
                plus <code>amazon_acos_action_history</code>, <code>amazon_datsheets</code>, <code>product_master</code>, etc.
                The grid counts <strong>report table rows</strong> (one campaign can have many: daily, L7, L30, …). Amazon&rsquo;s <strong>campaign</strong> total in Campaign Manager is unique campaigns &mdash; compare to <strong>Distinct campaign_id</strong> below the table for the same filters, not the row <em>of N entries</em> alone.
                Choose the dataset from <strong>Table</strong> below: <strong>SP</strong>, <strong>SB</strong>, and <strong>SD</strong> load <strong>every column</strong> from
                <code>amazon_sp_campaign_reports</code>, <code>amazon_sb_campaign_reports</code>, and <code>amazon_sd_campaign_reports</code> (server-side paging; use length menu up to 500 rows per request to walk the full table). Bid columns include <code>last_sbid</code> and computed <strong>SBID</strong> when shown (<code>yes_sbid</code> and <code>sbid_m</code> are not listed on All for SP but remain in row JSON for push). Also available: <code>amazon_bid_caps</code>, <code>amazon_fbm_targeting_checks</code>.
            </div>
        </div>
    </div>

    <div class="row amazon-ads-all">
        <div class="col-12">
            <div class="card">
                <div class="card-body py-2 px-2">
                    <div class="amazon-ads-filters border rounded bg-light mb-2">
                        <div class="row g-1 g-sm-2 align-items-end">
                            <div class="col-6 col-sm-4 col-md-2 col-xl-auto flex-grow-1" style="min-width: 7rem; max-width: 14rem;">
                                <label class="form-label" for="amazonAdsFilterReportType" title="Dataset">Table</label>
                                <select id="amazonAdsFilterReportType" class="form-select form-select-sm" title="amazon_sp_campaign_reports, etc.">
                                    <option value="sp_reports">SP reports</option>
                                    <option value="sb_reports">SB reports</option>
                                    <option value="sd_reports">SD reports</option>
                                    <option value="bid_caps">Bid caps</option>
                                    <option value="fbm_targeting">FBM targeting</option>
                                </select>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2 col-xl-auto flex-grow-1" style="min-width: 6.5rem; max-width: 12rem;">
                                <label class="form-label" for="amazonAdsFilterSummaryRange" title="Summary label L7/L30 or calendar dates">Range</label>
                                <select id="amazonAdsFilterSummaryRange" class="form-select form-select-sm">
                                    <option value="">Calendar</option>
                                    <option value="L1">L1</option>
                                    <option value="L7">L7</option>
                                    <option value="L14">L14</option>
                                    <option value="L15">L15</option>
                                    <option value="L30">L30</option>
                                    <option value="L60">L60</option>
                                </select>
                            </div>
                            <div class="col-6 col-sm-4 col-md-2 col-xl-auto">
                                <label class="form-label" for="amazonAdsFilterDateFrom">From</label>
                                <input type="date" id="amazonAdsFilterDateFrom" class="form-control form-control-sm">
                            </div>
                            <div class="col-6 col-sm-4 col-md-2 col-xl-auto">
                                <label class="form-label" for="amazonAdsFilterDateTo">To</label>
                                <input type="date" id="amazonAdsFilterDateTo" class="form-control form-control-sm">
                            </div>
                            <div class="col-12 col-sm-auto d-flex flex-wrap gap-1 align-items-end ms-sm-auto pt-1 pt-sm-0">
                                <button type="button" class="btn btn-sm btn-primary py-0" id="amazonAdsFilterApply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0" id="amazonAdsFilterClear">Clear</button>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap align-items-end justify-content-start column-gap-2 row-gap-1 mt-1">
                            <div class="flex-shrink-0">
                                <label class="form-label" for="amazonAdsFilterU7" title="L7 spend ÷ (budget×7)">U7%</label>
                                <select id="amazonAdsFilterU7" class="form-select form-select-sm" style="min-width: 5.5rem;" title="L7 SP ÷ (budget × 7)">
                                    <option value="">All</option>
                                    <option value="lt66">&lt; 66%</option>
                                    <option value="66_99">66–99%</option>
                                    <option value="gt99">&gt; 99%</option>
                                </select>
                            </div>
                            <div class="amazon-u7-pie-box d-flex align-items-center flex-shrink-0 border-start border-light ps-2 ms-1 ms-sm-2">
                                <div class="amazon-u7-pie-caption text-muted me-1" title="Row counts by U7% band (U7 filter ignored). Click a slice for last 30 days.">U7% mix</div>
                                <div id="amazonAdsU7Pie" class="amazon-u7-pie-chart" role="img" aria-label="U7 percent distribution pie chart"></div>
                            </div>
                            <div class="d-flex flex-wrap align-items-end gap-2 ms-auto">
                                <div class="flex-shrink-0">
                                    <label class="form-label" for="amazonAdsFilterU2" title="L2 spend ÷ (budget×2)">U2%</label>
                                    <select id="amazonAdsFilterU2" class="form-select form-select-sm" style="min-width: 5.5rem;" title="L2 SP ÷ (budget × 2)">
                                        <option value="">All</option>
                                        <option value="lt66">&lt; 66%</option>
                                        <option value="66_99">66–99%</option>
                                        <option value="gt99">&gt; 99%</option>
                                    </select>
                                </div>
                                <div class="flex-shrink-0">
                                    <label class="form-label" for="amazonAdsFilterU1" title="L1 spend ÷ budget">U1%</label>
                                    <select id="amazonAdsFilterU1" class="form-select form-select-sm" style="min-width: 5.5rem;" title="L1 SP ÷ (budget × 1)">
                                        <option value="">All</option>
                                        <option value="lt66">&lt; 66%</option>
                                        <option value="66_99">66–99%</option>
                                        <option value="gt99">&gt; 99%</option>
                                    </select>
                                </div>
                                <div class="flex-shrink-0">
                                    <label class="form-label" for="amazonAdsFilterCampaignStatus">Stat</label>
                                    <select id="amazonAdsFilterCampaignStatus" class="form-select form-select-sm" style="min-width: 5.5rem;" title="campaignStatus">
                                        <option value="">All</option>
                                        <option value="ENABLED">ON</option>
                                        <option value="PAUSED">Paused</option>
                                        <option value="ARCHIVED">Arch</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="border-top amazon-sbid-push-panel d-flex flex-wrap align-items-center gap-1 gap-sm-2">
                            <span class="small text-muted flex-shrink-0" title="SP table, current page, max 100 per call">Push SP</span>
                            <button type="button" class="btn btn-sm btn-success py-0" id="amazonAdsPushSbidBtn" disabled title="Push SBID to Amazon (current page, max 100)">SBID</button>
                            <span class="text-muted small flex-grow-1" style="min-width:4rem;font-size:0.7rem;" id="amazonAdsSbidPushStatus" aria-live="polite"></span>
                            <button type="button" class="btn btn-sm btn-outline-success py-0" id="amazonAdsPushSbgtBtn" disabled title="Push SBGT (current page)">
                                <i class="mdi mdi-cloud-upload" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1">SBGT</span>
                            </button>
                            <span class="text-muted small" style="font-size:0.7rem;" id="amazonAdsSbgtPushStatus" aria-live="polite"></span>
                        </div>
                    </div>

                    <div id="amazonAdsSourcePanels">
                        <div class="amazon-source-pane mb-0" data-pane-for="sp_reports">
                            <p class="text-muted small mb-1">
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
                            <p class="text-muted small mb-1">
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
                            <p class="text-muted small mb-1">
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
                            <p class="text-muted small mb-1">
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
                            <p class="text-muted small mb-1">
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

    <div class="modal fade" id="amazonAdsBgtRuleModal" tabindex="-1" aria-labelledby="amazonAdsBgtRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="amazonAdsBgtRuleModalLabel">BGT rule — ACOS → SBGT</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        <strong>Pink</strong> if L30 ACOS ≤ E1; <strong>green</strong> if &gt; E1 and ≤ E2; <strong>blue</strong> if &gt; E2 and ≤ E3;
                        <strong>yellow</strong> if &gt; E3 and &lt; E4; <strong>red</strong> if ACOS ≥ E4. Require <strong>E1 &lt; E2 &lt; E3 &lt; E4</strong> (all %).
                        SBGT values are the suggested daily budget <strong>tiers</strong> ($) used in the grid and SBGT push.
                    </p>
                    <div class="row g-2 mb-2">
                        <div class="col-6 col-md-3">
                            <label class="form-label small mb-0" for="amazonAdsBgtRuleE1">E1 (pink max ACOS %)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsBgtRuleE1" name="e1" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small mb-0" for="amazonAdsBgtRuleE2">E2 (green max)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsBgtRuleE2" name="e2" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small mb-0" for="amazonAdsBgtRuleE3">E3 (blue max)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsBgtRuleE3" name="e3" required>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small mb-0" for="amazonAdsBgtRuleE4">E4 (red min ACOS %)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsBgtRuleE4" name="e4" required>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6 col-md">
                            <label class="form-label small mb-0" for="amazonAdsBgtRuleSbgtPink">SBGT pink ($)</label>
                            <input type="number" step="1" min="1" class="form-control form-control-sm" id="amazonAdsBgtRuleSbgtPink" name="sbgt_pink" required>
                        </div>
                        <div class="col-6 col-md">
                            <label class="form-label small mb-0" for="amazonAdsBgtRuleSbgtGreen">SBGT green ($)</label>
                            <input type="number" step="1" min="1" class="form-control form-control-sm" id="amazonAdsBgtRuleSbgtGreen" name="sbgt_green" required>
                        </div>
                        <div class="col-6 col-md">
                            <label class="form-label small mb-0" for="amazonAdsBgtRuleSbgtBlue">SBGT blue ($)</label>
                            <input type="number" step="1" min="1" class="form-control form-control-sm" id="amazonAdsBgtRuleSbgtBlue" name="sbgt_blue" required>
                        </div>
                        <div class="col-6 col-md">
                            <label class="form-label small mb-0" for="amazonAdsBgtRuleSbgtYellow">SBGT yellow ($)</label>
                            <input type="number" step="1" min="1" class="form-control form-control-sm" id="amazonAdsBgtRuleSbgtYellow" name="sbgt_yellow" required>
                        </div>
                        <div class="col-6 col-md">
                            <label class="form-label small mb-0" for="amazonAdsBgtRuleSbgtRed">SBGT red ($)</label>
                            <input type="number" step="1" min="1" class="form-control form-control-sm" id="amazonAdsBgtRuleSbgtRed" name="sbgt_red" required>
                        </div>
                    </div>
                    <p class="small text-danger mb-0 mt-3 d-none" id="amazonAdsBgtRuleModalError" role="alert"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amazonAdsBgtRuleSaveBtn">Save rule &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsSbidRuleModal" tabindex="-1" aria-labelledby="amazonAdsSbidRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="amazonAdsSbidRuleModalLabel">SBID rule — U2% / U1% → suggested bid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        When <strong>both</strong> U2% and U1% are <strong>below</strong> the low threshold, SBID uses L1 → L2 → L7 CPC multiplied by the matching factor (first positive CPC wins).
                        When <strong>both</strong> are <strong>above</strong> the high threshold, SBID uses L1 CPC × the high-band multiplier (no L1 CPC → no SBID).
                        Otherwise the grid shows &ldquo;--&rdquo;. Same logic runs in automated bid commands.
                    </p>
                    <div class="row g-2 mb-2">
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleUtilLow">Low threshold (%)</label>
                            <input type="number" step="0.1" class="form-control form-control-sm" id="amazonAdsSbidRuleUtilLow" name="util_low" required>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleUtilHigh">High threshold (%)</label>
                            <input type="number" step="0.1" class="form-control form-control-sm" id="amazonAdsSbidRuleUtilHigh" name="util_high" required>
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleBothLowFallback">Fallback SBID (no CPC)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleBothLowFallback" name="both_low_fallback" required>
                        </div>
                    </div>
                    <p class="small fw-semibold mb-1">Both below low threshold — CPC multipliers</p>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleLowMultL1">× L1 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleLowMultL1" name="both_low_mult_l1" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleLowMultL2">× L2 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleLowMultL2" name="both_low_mult_l2" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleLowMultL7">× L7 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleLowMultL7" name="both_low_mult_l7" required>
                        </div>
                    </div>
                    <p class="small fw-semibold mb-1">Both above high threshold</p>
                    <div class="row g-2">
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleHighMultL1">× L1 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleHighMultL1" name="both_high_mult_l1" required>
                        </div>
                    </div>
                    <p class="small text-danger mb-0 mt-3 d-none" id="amazonAdsSbidRuleModalError" role="alert"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amazonAdsSbidRuleSaveBtn">Save rule &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsU7HistoryModal" tabindex="-1" aria-labelledby="amazonAdsU7HistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsU7HistoryModalLabel">U7% — daily row counts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2" id="amazonAdsU7HistoryModalSub">Last 30 calendar days (same U2/U1/Status filters as the grid; date range ignored).</p>
                    <div id="amazonAdsU7HistoryModalLoading" class="small text-muted">Loading…</div>
                    <p class="small text-danger mb-0 d-none" id="amazonAdsU7HistoryModalError" role="alert"></p>
                    <div class="table-responsive" style="max-height: 60vh;">
                        <table class="table table-sm table-striped mb-0 d-none" id="amazonAdsU7HistoryTable">
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col" data-u7-bucket-col="lt66">&lt; 66%</th>
                                    <th scope="col" data-u7-bucket-col="66_99">66–99%</th>
                                    <th scope="col" data-u7-bucket-col="gt99">&gt; 99%</th>
                                    <th scope="col" data-u7-bucket-col="na">N/A</th>
                                    <th scope="col">Total</th>
                                </tr>
                            </thead>
                            <tbody id="amazonAdsU7HistoryTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
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
            var pushSpSbgtsUrl = @json(route('amazon.ads.push-sp-sbgts'));
            var bgtRuleGetUrl = @json(route('amazon.ads.bgt-rule'));
            var bgtRuleSaveUrl = @json(route('amazon.ads.bgt-rule.save'));
            var sbidRuleGetUrl = @json(route('amazon.ads.sbid-rule'));
            var sbidRuleSaveUrl = @json(route('amazon.ads.sbid-rule.save'));
            window.amazonAdsBgtRule = @json($amazonAdsBgtRule ?? null);
            window.amazonAdsSbidRule = @json($amazonAdsSbidRule ?? null);
            var u7PieDistribUrl = @json(url('/amazon-ads/u7-distribution')) + '/';
            var u7PieHistoryUrl = @json(url('/amazon-ads/u7-distribution-history')) + '/';
            var amazonAdsU7PieChart = null;
            var u7PieRefreshTimer = null;

            function amazonAdsOpenU7HistoryModal(bucketKey, sliceLabel) {
                var modalEl = document.getElementById('amazonAdsU7HistoryModal');
                var titleEl = document.getElementById('amazonAdsU7HistoryModalLabel');
                var subEl = document.getElementById('amazonAdsU7HistoryModalSub');
                var loadEl = document.getElementById('amazonAdsU7HistoryModalLoading');
                var errEl = document.getElementById('amazonAdsU7HistoryModalError');
                var tbl = document.getElementById('amazonAdsU7HistoryTable');
                var tbody = document.getElementById('amazonAdsU7HistoryTableBody');
                if (!modalEl || !tbody) {
                    return;
                }
                if (titleEl) {
                    titleEl.textContent = 'U7% — ' + (sliceLabel || bucketKey) + ' — last 30 days';
                }
                if (subEl) {
                    subEl.textContent = 'Daily row counts for the selected band. Same U2/U1/Status filters as the grid; grid date range and U7 filter are ignored.';
                }
                errEl.classList.add('d-none');
                errEl.textContent = '';
                tbl.classList.add('d-none');
                tbody.innerHTML = '';
                loadEl.classList.remove('d-none');
                loadEl.textContent = 'Loading…';
                document.querySelectorAll('#amazonAdsU7HistoryTable thead [data-u7-bucket-col]').forEach(function (th) {
                    th.classList.remove('table-secondary');
                    if (th.getAttribute('data-u7-bucket-col') === bucketKey) {
                        th.classList.add('table-secondary');
                    }
                });
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                var sk = activeRawSourceKey || 'sp_reports';
                var p = amazonAdsFilterPayload();
                var tok = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                jQuery.ajax({
                    url: u7PieHistoryUrl + encodeURIComponent(sk),
                    type: 'POST',
                    data: {
                        _token: tok,
                        days: 30,
                        bucket: bucketKey,
                        filter_u2: p.filter_u2,
                        filter_u1: p.filter_u1,
                        filter_campaign_status: p.filter_campaign_status
                    },
                    success: function (res) {
                        loadEl.classList.add('d-none');
                        if (!res || !res.ok || !res.days || !res.days.length) {
                            errEl.textContent = (res && res.reason) ? ('Could not load history (' + res.reason + ').') : 'No history data.';
                            errEl.classList.remove('d-none');
                            return;
                        }
                        tbl.classList.remove('d-none');
                        var frag = document.createDocumentFragment();
                        res.days.forEach(function (row) {
                            var tr = document.createElement('tr');
                            var td0 = document.createElement('td');
                            td0.textContent = row.date || '';
                            tr.appendChild(td0);
                            ['lt66', '66_99', 'gt99', 'na', 'total'].forEach(function (k) {
                                var td = document.createElement('td');
                                td.textContent = String(row[k] != null ? row[k] : '');
                                if (k === bucketKey) {
                                    td.classList.add('fw-semibold');
                                }
                                tr.appendChild(td);
                            });
                            frag.appendChild(tr);
                        });
                        tbody.appendChild(frag);
                    },
                    error: function () {
                        loadEl.classList.add('d-none');
                        errEl.textContent = 'Request failed.';
                        errEl.classList.remove('d-none');
                    }
                });
            }

            function amazonAdsRefreshU7PieChartDebounced() {
                if (u7PieRefreshTimer) {
                    clearTimeout(u7PieRefreshTimer);
                }
                u7PieRefreshTimer = setTimeout(function () {
                    amazonAdsRefreshU7PieChart();
                }, 280);
            }

            function amazonAdsRefreshU7PieChart() {
                var box = document.getElementById('amazonAdsU7Pie');
                if (!box) {
                    return;
                }
                if (typeof Highcharts === 'undefined') {
                    box.innerHTML = '<p class="small text-muted mb-0">—</p>';
                    return;
                }
                var sk = activeRawSourceKey || 'sp_reports';
                var p = amazonAdsFilterPayload();
                var tok = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                jQuery.ajax({
                    url: u7PieDistribUrl + encodeURIComponent(sk),
                    type: 'POST',
                    data: {
                        _token: tok,
                        date_from: p.date_from,
                        date_to: p.date_to,
                        summary_report_range: p.summary_report_range,
                        filter_u2: p.filter_u2,
                        filter_u1: p.filter_u1,
                        filter_campaign_status: p.filter_campaign_status
                    },
                    success: function (res) {
                        if (amazonAdsU7PieChart) {
                            try {
                                amazonAdsU7PieChart.destroy();
                            } catch (e0) {}
                            amazonAdsU7PieChart = null;
                        }
                        if (!res || !res.ok) {
                            box.innerHTML = '<p class="small text-muted mb-0 px-1">No chart</p>';
                            return;
                        }
                        box.innerHTML = '';
                        var b = res.buckets || {};
                        var lt = b.lt66 || 0;
                        var mid = b['66_99'] || 0;
                        var gt = b.gt99 || 0;
                        var na = b.na || 0;
                        var seriesData = [];
                        if (lt > 0) {
                            seriesData.push({ name: '< 66%', y: lt, color: '#dc2626', bucket: 'lt66' });
                        }
                        if (mid > 0) {
                            seriesData.push({ name: '66–99%', y: mid, color: '#16a34a', bucket: '66_99' });
                        }
                        if (gt > 0) {
                            seriesData.push({ name: '> 99%', y: gt, color: '#db2777', bucket: 'gt99' });
                        }
                        if (na > 0) {
                            seriesData.push({ name: 'N/A', y: na, color: '#9ca3af', bucket: 'na' });
                        }
                        var tot = res.total || 0;
                        if (!seriesData.length || tot < 1) {
                            box.innerHTML = '<p class="small text-muted mb-0">No rows</p>';
                            return;
                        }
                        amazonAdsU7PieChart = Highcharts.chart('amazonAdsU7Pie', {
                            chart: { type: 'pie', backgroundColor: 'transparent', height: 100, spacing: [0, 0, 0, 0] },
                            credits: { enabled: false },
                            exporting: { enabled: false },
                            title: { text: null },
                            tooltip: {
                                pointFormat: '<span style="color:{point.color}">\u25cf</span> {point.name}: <b>{point.y}</b> ({point.percentage:.1f}%)<br/>Click for 30-day history.'
                            },
                            plotOptions: {
                                pie: {
                                    allowPointSelect: true,
                                    cursor: 'pointer',
                                    size: '100%',
                                    point: {
                                        events: {
                                            click: function () {
                                                var b = this.options.bucket;
                                                if (b) {
                                                    amazonAdsOpenU7HistoryModal(b, this.name);
                                                }
                                            }
                                        }
                                    },
                                    dataLabels: {
                                        enabled: true,
                                        distance: 4,
                                        style: { fontSize: '7px', fontWeight: '600', textOutline: 'none' },
                                        formatter: function () {
                                            return this.percentage > 8 ? this.point.name : '';
                                        }
                                    }
                                }
                            },
                            series: [{ type: 'pie', name: 'Rows', data: seriesData }]
                        });
                    },
                    error: function () {
                        if (amazonAdsU7PieChart) {
                            try {
                                amazonAdsU7PieChart.destroy();
                            } catch (e1) {}
                            amazonAdsU7PieChart = null;
                        }
                        box.innerHTML = '<p class="small text-danger mb-0">Error</p>';
                    }
                });
            }

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

            /** SBGT tier from row if it matches one of the configured rule tier values. */
            function amazonAdsPickSbgtTierFromRow(row) {
                if (!row || typeof row !== 'object') {
                    return null;
                }
                var t = parseInt(row.sbgt, 10);
                if (isNaN(t)) {
                    return null;
                }
                var allowed = amazonAdsAllowedSbgtTiersFromRule();
                if (allowed.indexOf(t) !== -1) {
                    return t;
                }
                return null;
            }

            function amazonAdsCollectSpSbgtPushRows() {
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
                    var tier = amazonAdsPickSbgtTierFromRow(row);
                    if (tier === null) {
                        return;
                    }
                    list.push({
                        campaign_id: String(cid).trim(),
                        sbgt: tier
                    });
                });
                return list;
            }

            function amazonAdsUpdateSbidPushButton() {
                var btn = document.getElementById('amazonAdsPushSbidBtn');
                var sbgtBtn = document.getElementById('amazonAdsPushSbgtBtn');
                var isSp = activeRawSourceKey === 'sp_reports';
                if (btn) {
                    btn.disabled = !isSp;
                    btn.title = isSp ? 'Uses sbid_m, yes_sbid, or sbid for rows on this page' : 'Switch to Table: SP — amazon_sp_campaign_reports';
                }
                if (sbgtBtn) {
                    sbgtBtn.disabled = !isSp;
                    var tiers = amazonAdsAllowedSbgtTiersFromRule();
                    sbgtBtn.title = isSp
                        ? 'Sets SP daily budget on Amazon to each row SBGT tier as dollars ($' + tiers.join(', $') + ')'
                        : 'Switch to Table: SP — amazon_sp_campaign_reports';
                }
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
            /** U7%/U2%/U1%: L7 SP÷(BGT×7), L2 SP÷(BGT×2), L1 SP÷(BGT×1), rounded %; red below 66, green 66–99, pink above 99. */
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

            /** Money / totals columns: no integer rounding; sort and export use numeric precision. */
            function amazonAdsParseFiniteNumber(data) {
                if (data === null || data === undefined || data === '') {
                    return NaN;
                }
                var n = typeof data === 'number' ? data : parseFloat(String(data).replace(/,/g, ''));
                return typeof n === 'number' && isFinite(n) ? n : NaN;
            }

            function amazonAdsRawNumberText(data) {
                var n = amazonAdsParseFiniteNumber(data);
                if (isNaN(n)) {
                    return '';
                }
                return String(n);
            }

            /** SBID from server when U2/U1 both red or both pink; otherwise null → -- */
            function renderSbidColumn(data, type) {
                if (type === 'sort' || type === 'type') {
                    var n = typeof data === 'number' ? data : parseFloat(String(data).replace(/,/g, ''));
                    return isNaN(n) ? -1 : n;
                }
                if (type === 'export' || type === 'excel' || type === 'pdf') {
                    if (data === null || data === undefined || data === '') {
                        return '';
                    }
                    var x = typeof data === 'number' ? data : parseFloat(String(data).replace(/,/g, ''));
                    return isNaN(x) ? '' : String(x);
                }
                if (type !== 'display') {
                    return data;
                }
                if (data === null || data === undefined || data === '') {
                    return '<span class="text-muted">--</span>';
                }
                var num = typeof data === 'number' ? data : parseFloat(String(data).replace(/,/g, ''));
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
             * ACOS % tier colors — uses {@see window.amazonAdsBgtRule} (same as server AmazonAcosSbgtRule).
             */
            function amazonAdsAcosTierColor(acos) {
                var r = window.amazonAdsBgtRule || {};
                var e1 = parseFloat(r.e1);
                var e2 = parseFloat(r.e2);
                var e3 = parseFloat(r.e3);
                var e4 = parseFloat(r.e4);
                var a = typeof acos === 'number' ? acos : parseFloat(String(acos));
                if (isNaN(a) || isNaN(e1) || isNaN(e2) || isNaN(e3) || isNaN(e4)) {
                    return '#6b7280';
                }
                if (a >= e4) {
                    return '#dc2626';
                }
                if (a > e3) {
                    return '#ca8a04';
                }
                if (a > e2) {
                    return '#2563eb';
                }
                if (a > e1) {
                    return '#16a34a';
                }
                return '#db2777';
            }

            /** SBGT display color by tier value (matches semantic tier from BGT rule). */
            function amazonAdsSbgtTierColor(sbgt) {
                var s = parseInt(sbgt, 10);
                var r = window.amazonAdsBgtRule || {};
                if (s === parseInt(r.sbgt_red, 10)) {
                    return '#dc2626';
                }
                if (s === parseInt(r.sbgt_yellow, 10)) {
                    return '#ca8a04';
                }
                if (s === parseInt(r.sbgt_blue, 10)) {
                    return '#2563eb';
                }
                if (s === parseInt(r.sbgt_green, 10)) {
                    return '#16a34a';
                }
                if (s === parseInt(r.sbgt_pink, 10)) {
                    return '#db2777';
                }
                return '#6b7280';
            }

            function amazonAdsAllowedSbgtTiersFromRule() {
                var r = window.amazonAdsBgtRule || {};
                var raw = [r.sbgt_red, r.sbgt_yellow, r.sbgt_blue, r.sbgt_green, r.sbgt_pink];
                var out = [];
                for (var i = 0; i < raw.length; i++) {
                    var t = parseInt(raw[i], 10);
                    if (!isNaN(t) && out.indexOf(t) === -1) {
                        out.push(t);
                    }
                }
                out.sort(function (x, y) { return x - y; });
                return out;
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
                    if (c === 'campaignStatus') {
                        col.title = 'Stat';
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
                        col.title = 'Lbid';
                        col.render = function (data, type) {
                            return renderSbidColumn(data, type);
                        };
                    }
                    if (c === 'bgt') {
                        col.title = 'BGT';
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var nb = amazonAdsParseFiniteNumber(data);
                                return isNaN(nb) ? -1 : nb;
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                return amazonAdsRawNumberText(data);
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var num = amazonAdsParseFiniteNumber(data);
                            if (isNaN(num)) {
                                return '<span class="text-muted">--</span>';
                            }
                            return '<span class="fw-semibold">' + amazonAdsRawNumberText(data) + '</span>';
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
                                var nsl = amazonAdsParseFiniteNumber(data);
                                return isNaN(nsl) ? -1 : nsl;
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                return amazonAdsRawNumberText(data);
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var fsl = amazonAdsParseFiniteNumber(data);
                            if (isNaN(fsl)) {
                                return '<span class="text-muted">--</span>';
                            }
                            return '<span class="fw-semibold">' + amazonAdsRawNumberText(data) + '</span>';
                        };
                    }
                    if (c === 'cost') {
                        col.title = 'SPL30';
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var ncst = amazonAdsParseFiniteNumber(data);
                                return isNaN(ncst) ? -1 : Math.round(ncst);
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                if (data === null || data === undefined || data === '') {
                                    return '';
                                }
                                var xcst = amazonAdsParseFiniteNumber(data);
                                return isNaN(xcst) ? '' : String(Math.round(xcst));
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var fcst = amazonAdsParseFiniteNumber(data);
                            if (isNaN(fcst)) {
                                return '<span class="text-muted">--</span>';
                            }
                            return '<span class="fw-semibold">' + String(Math.round(fcst)) + '</span>';
                        };
                    }
                    if (c === 'L7spend' || c === 'L2spend' || c === 'L1spend') {
                        if (c === 'L7spend') {
                            col.title = 'L7SP';
                        } else if (c === 'L2spend') {
                            col.title = 'L2SP';
                        } else {
                            col.title = 'L1SP';
                        }
                        col.orderable = false;
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var nl = amazonAdsParseFiniteNumber(data);
                                return isNaN(nl) ? -1 : nl;
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                return amazonAdsRawNumberText(data);
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var nld = amazonAdsParseFiniteNumber(data);
                            if (isNaN(nld)) {
                                return '<span class="text-muted">--</span>';
                            }
                            return '<span class="fw-semibold">' + amazonAdsRawNumberText(data) + '</span>';
                        };
                    }
                    if (c === 'U7%' || c === 'U2%' || c === 'U1%') {
                        col.render = function (data, type) {
                            return renderUtilPercentColumn(data, type);
                        };
                    }
                    if (c === 'CPC3') {
                        col.title = 'CPC3';
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
                        col.title = 'CPC2';
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
                        col.title = 'CPC1';
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
                        col.title = 'SL30';
                        col.render = function (data, type) {
                            if (type === 'sort' || type === 'type') {
                                var nsa = amazonAdsParseFiniteNumber(data);
                                return isNaN(nsa) ? -1 : Math.round(nsa);
                            }
                            if (type === 'export' || type === 'excel' || type === 'pdf') {
                                if (data === null || data === undefined || data === '') {
                                    return '';
                                }
                                var xsa = amazonAdsParseFiniteNumber(data);
                                return isNaN(xsa) ? '' : String(Math.round(xsa));
                            }
                            if (type !== 'display') {
                                return data;
                            }
                            if (data === null || data === undefined || data === '') {
                                return '<span class="text-muted">--</span>';
                            }
                            var nca = amazonAdsParseFiniteNumber(data);
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

                var hiddenRawColumnKeys = ['id', 'profile_id', 'campaign_id', 'report_date_range', 'ad_type', 'date', 'startDate', 'endDate'];
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
                dt.on('xhr.dt', function () {
                    amazonAdsRefreshU7PieChartDebounced();
                });
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
                    amazonAdsRefreshU7PieChartDebounced();

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

                    var pushSbgtBtn = document.getElementById('amazonAdsPushSbgtBtn');
                    if (pushSbgtBtn) {
                        pushSbgtBtn.addEventListener('click', function () {
                            var statusEl = document.getElementById('amazonAdsSbgtPushStatus');
                            if (activeRawSourceKey !== 'sp_reports') {
                                if (statusEl) {
                                    statusEl.textContent = 'Switch to the SP table first.';
                                }
                                return;
                            }
                            var rows = amazonAdsCollectSpSbgtPushRows();
                            if (!rows.length) {
                                if (statusEl) {
                                    statusEl.textContent = 'No rows on this page with campaign_id and a valid SBGT tier (' + amazonAdsAllowedSbgtTiersFromRule().join(', ') + ').';
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
                            if (!window.confirm('Push SBGT to Amazon as daily budget ($' + amazonAdsAllowedSbgtTiersFromRule().join(', $') + ') for ' + deduped.length + ' SP campaign(s) on this page?')) {
                                return;
                            }
                            pushSbgtBtn.disabled = true;
                            if (statusEl) {
                                statusEl.textContent = 'Pushing…';
                            }
                            var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                            fetch(pushSpSbgtsUrl, {
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
                                    var msg = b.message || (out.ok ? 'Done.' : 'Request failed.');
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

                    function amazonAdsFillBgtRuleForm(r) {
                        if (!r) {
                            return;
                        }
                        var map = [
                            ['amazonAdsBgtRuleE1', 'e1'],
                            ['amazonAdsBgtRuleE2', 'e2'],
                            ['amazonAdsBgtRuleE3', 'e3'],
                            ['amazonAdsBgtRuleE4', 'e4'],
                            ['amazonAdsBgtRuleSbgtPink', 'sbgt_pink'],
                            ['amazonAdsBgtRuleSbgtGreen', 'sbgt_green'],
                            ['amazonAdsBgtRuleSbgtBlue', 'sbgt_blue'],
                            ['amazonAdsBgtRuleSbgtYellow', 'sbgt_yellow'],
                            ['amazonAdsBgtRuleSbgtRed', 'sbgt_red']
                        ];
                        for (var i = 0; i < map.length; i++) {
                            var el = document.getElementById(map[i][0]);
                            if (el && r[map[i][1]] != null) {
                                el.value = String(r[map[i][1]]);
                            }
                        }
                    }

                    function amazonAdsCollectBgtRuleFromForm() {
                        function num(id) {
                            var el = document.getElementById(id);
                            if (!el) {
                                return NaN;
                            }
                            return parseFloat(String(el.value).trim());
                        }
                        function intn(id) {
                            var el = document.getElementById(id);
                            if (!el) {
                                return NaN;
                            }
                            return parseInt(String(el.value).trim(), 10);
                        }
                        return {
                            e1: num('amazonAdsBgtRuleE1'),
                            e2: num('amazonAdsBgtRuleE2'),
                            e3: num('amazonAdsBgtRuleE3'),
                            e4: num('amazonAdsBgtRuleE4'),
                            sbgt_pink: intn('amazonAdsBgtRuleSbgtPink'),
                            sbgt_green: intn('amazonAdsBgtRuleSbgtGreen'),
                            sbgt_blue: intn('amazonAdsBgtRuleSbgtBlue'),
                            sbgt_yellow: intn('amazonAdsBgtRuleSbgtYellow'),
                            sbgt_red: intn('amazonAdsBgtRuleSbgtRed')
                        };
                    }

                    var bgtRuleModalEl = document.getElementById('amazonAdsBgtRuleModal');
                    if (bgtRuleModalEl) {
                        bgtRuleModalEl.addEventListener('show.bs.modal', function () {
                            var errEl = document.getElementById('amazonAdsBgtRuleModalError');
                            if (errEl) {
                                errEl.classList.add('d-none');
                                errEl.textContent = '';
                            }
                            fetch(bgtRuleGetUrl, {
                                method: 'GET',
                                headers: {
                                    Accept: 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                credentials: 'same-origin'
                            })
                                .then(function (res) {
                                    return res.json().then(function (body) {
                                        return { ok: res.ok, body: body };
                                    });
                                })
                                .then(function (out) {
                                    if (out.ok && out.body && out.body.rule) {
                                        window.amazonAdsBgtRule = out.body.rule;
                                        amazonAdsFillBgtRuleForm(out.body.rule);
                                    }
                                })
                                .catch(function () {
                                    amazonAdsFillBgtRuleForm(window.amazonAdsBgtRule || {});
                                });
                        });
                    }

                    var bgtRuleSaveBtn = document.getElementById('amazonAdsBgtRuleSaveBtn');
                    if (bgtRuleSaveBtn) {
                        bgtRuleSaveBtn.addEventListener('click', function () {
                            var errEl = document.getElementById('amazonAdsBgtRuleModalError');
                            if (errEl) {
                                errEl.classList.add('d-none');
                                errEl.textContent = '';
                            }
                            var payload = amazonAdsCollectBgtRuleFromForm();
                            var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                            bgtRuleSaveBtn.disabled = true;
                            fetch(bgtRuleSaveUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    Accept: 'application/json',
                                    'X-CSRF-TOKEN': token,
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify(payload)
                            })
                                .then(function (res) {
                                    return res.json().then(function (body) {
                                        return { ok: res.ok, status: res.status, body: body };
                                    });
                                })
                                .then(function (out) {
                                    var b = out.body || {};
                                    if (!out.ok) {
                                        if (errEl) {
                                            errEl.textContent = b.message || b.error || 'Save failed.';
                                            errEl.classList.remove('d-none');
                                        }
                                        return;
                                    }
                                    window.amazonAdsBgtRule = b.rule || window.amazonAdsBgtRule;
                                    if (typeof bootstrap !== 'undefined' && bgtRuleModalEl) {
                                        var inst = bootstrap.Modal.getInstance(bgtRuleModalEl);
                                        if (inst) {
                                            inst.hide();
                                        }
                                    }
                                    amazonAdsReloadActiveGrid();
                                })
                                .catch(function () {
                                    if (errEl) {
                                        errEl.textContent = 'Network or server error.';
                                        errEl.classList.remove('d-none');
                                    }
                                })
                                .finally(function () {
                                    bgtRuleSaveBtn.disabled = false;
                                });
                        });
                    }

                    function amazonAdsFillSbidRuleForm(r) {
                        if (!r) {
                            return;
                        }
                        var map = [
                            ['amazonAdsSbidRuleUtilLow', 'util_low'],
                            ['amazonAdsSbidRuleUtilHigh', 'util_high'],
                            ['amazonAdsSbidRuleBothLowFallback', 'both_low_fallback'],
                            ['amazonAdsSbidRuleLowMultL1', 'both_low_mult_l1'],
                            ['amazonAdsSbidRuleLowMultL2', 'both_low_mult_l2'],
                            ['amazonAdsSbidRuleLowMultL7', 'both_low_mult_l7'],
                            ['amazonAdsSbidRuleHighMultL1', 'both_high_mult_l1']
                        ];
                        for (var si = 0; si < map.length; si++) {
                            var el = document.getElementById(map[si][0]);
                            if (el && r[map[si][1]] != null) {
                                el.value = String(r[map[si][1]]);
                            }
                        }
                    }

                    function amazonAdsCollectSbidRuleFromForm() {
                        function n2(id) {
                            var el = document.getElementById(id);
                            if (!el) {
                                return NaN;
                            }
                            return parseFloat(String(el.value).trim());
                        }
                        return {
                            util_low: n2('amazonAdsSbidRuleUtilLow'),
                            util_high: n2('amazonAdsSbidRuleUtilHigh'),
                            both_low_fallback: n2('amazonAdsSbidRuleBothLowFallback'),
                            both_low_mult_l1: n2('amazonAdsSbidRuleLowMultL1'),
                            both_low_mult_l2: n2('amazonAdsSbidRuleLowMultL2'),
                            both_low_mult_l7: n2('amazonAdsSbidRuleLowMultL7'),
                            both_high_mult_l1: n2('amazonAdsSbidRuleHighMultL1')
                        };
                    }

                    var sbidRuleModalEl = document.getElementById('amazonAdsSbidRuleModal');
                    if (sbidRuleModalEl) {
                        sbidRuleModalEl.addEventListener('show.bs.modal', function () {
                            var sErr = document.getElementById('amazonAdsSbidRuleModalError');
                            if (sErr) {
                                sErr.classList.add('d-none');
                                sErr.textContent = '';
                            }
                            fetch(sbidRuleGetUrl, {
                                method: 'GET',
                                headers: {
                                    Accept: 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                credentials: 'same-origin'
                            })
                                .then(function (res) {
                                    return res.json().then(function (body) {
                                        return { ok: res.ok, body: body };
                                    });
                                })
                                .then(function (out) {
                                    if (out.ok && out.body && out.body.rule) {
                                        window.amazonAdsSbidRule = out.body.rule;
                                        amazonAdsFillSbidRuleForm(out.body.rule);
                                    }
                                })
                                .catch(function () {
                                    amazonAdsFillSbidRuleForm(window.amazonAdsSbidRule || {});
                                });
                        });
                    }

                    var sbidRuleSaveBtn = document.getElementById('amazonAdsSbidRuleSaveBtn');
                    if (sbidRuleSaveBtn) {
                        sbidRuleSaveBtn.addEventListener('click', function () {
                            var sErr = document.getElementById('amazonAdsSbidRuleModalError');
                            if (sErr) {
                                sErr.classList.add('d-none');
                                sErr.textContent = '';
                            }
                            var sPayload = amazonAdsCollectSbidRuleFromForm();
                            var sToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                            sbidRuleSaveBtn.disabled = true;
                            fetch(sbidRuleSaveUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    Accept: 'application/json',
                                    'X-CSRF-TOKEN': sToken,
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify(sPayload)
                            })
                                .then(function (res) {
                                    return res.json().then(function (body) {
                                        return { ok: res.ok, status: res.status, body: body };
                                    });
                                })
                                .then(function (out) {
                                    var sb = out.body || {};
                                    if (!out.ok) {
                                        if (sErr) {
                                            sErr.textContent = sb.message || sb.error || 'Save failed.';
                                            sErr.classList.remove('d-none');
                                        }
                                        return;
                                    }
                                    window.amazonAdsSbidRule = sb.rule || window.amazonAdsSbidRule;
                                    if (typeof bootstrap !== 'undefined' && sbidRuleModalEl) {
                                        var sInst = bootstrap.Modal.getInstance(sbidRuleModalEl);
                                        if (sInst) {
                                            sInst.hide();
                                        }
                                    }
                                    amazonAdsReloadActiveGrid();
                                })
                                .catch(function () {
                                    if (sErr) {
                                        sErr.textContent = 'Network or server error.';
                                        sErr.classList.remove('d-none');
                                    }
                                })
                                .finally(function () {
                                    sbidRuleSaveBtn.disabled = false;
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
