@extends('layouts.vertical', ['title' => 'Amazon Ads All'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        #amz-ads-raw-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 13px;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #amz-ads-raw-wrap .tabulator-col .tabulator-col-sorter { display: none !important; }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important;
            transform: none !important; white-space: normal !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important; text-orientation: mixed !important; transform: none !important;
            white-space: normal !important; height: auto !important; min-height: 0 !important;             display: block;
            align-items: unset; justify-content: unset; font-size: 12.5px; font-weight: 600; line-height: 1.25;
            padding: 5px 2px; text-align: center;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content { height: auto !important; min-height: 34px; padding: 0; }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col { height: auto !important; min-height: 34px; vertical-align: middle; }
        #amz-ads-raw-wrap .tabulator .tabulator-row { min-height: 32px; }
        #amz-ads-raw-wrap .tabulator .tabulator-row .tabulator-cell { padding: 3px 2px !important; }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder { padding-left: 2px !important; padding-right: 2px !important; }
        #amz-ads-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaignStatus"] .tabulator-col-title { white-space: nowrap !important; }
        #amz-ads-raw-wrap .tabulator .tabulator-cell .amz-raw-status-cell { white-space: nowrap; }
        /* Pagination footer */
        #amz-ads-raw-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; padding: 10px 16px !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important; min-width: 36px !important; height: 36px !important;
            line-height: 36px !important; padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important; color: #475569 !important;
            cursor: pointer; transition: all 0.15s ease !important; text-align: center !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover { background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important; }
        #amz-ads-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important; color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #amz-ads-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] { opacity: 0.4 !important; cursor: not-allowed !important; }
        #amz-ads-raw-wrap .tabulator .tabulator-footer .tabulator-page-counter { margin: 0 0.5rem; font-size: 12px; color: #334155; }
        #amz-ads-raw-wrap { overflow-x: auto; overflow-y: visible; }
        /* U% utilization colors */
        #amz-ads-raw-wrap .tabulator .tabulator-cell.green-bg { color: #16a34a !important; font-weight: 600; }
        #amz-ads-raw-wrap .tabulator .tabulator-cell.pink-bg { color: #db2777 !important; font-weight: 600; }
        #amz-ads-raw-wrap .tabulator .tabulator-cell.red-bg { color: #dc2626 !important; font-weight: 600; }
        /* Filter bar */
        #amz-raw-filter-bar { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; }
        #amz-raw-filter-bar .amz-raw-filter-label {
            display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px; letter-spacing: 0.01em;
        }
        #amz-raw-filter-bar .amz-raw-filter-select { min-width: 120px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: #64748b; font-size: 0.8125rem; padding-top: 0.35rem; padding-bottom: 0.35rem; }
        #amz-raw-filter-bar .amz-raw-date-input { border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: #334155; font-size: 0.8125rem; padding: 0.35rem 0.4rem; }
        /* Stat badges */
        .amz-stat-badge {
            display: inline-flex; align-items: center; flex-shrink: 0; color: #fff; font-size: 15px; font-weight: 700;
            padding: 9px 16px; border-radius: 8px; white-space: nowrap; line-height: 1.25; letter-spacing: 0.2px;
        }
        .amz-stat-badge > span { margin-left: 4px; font-size: 16px; font-weight: 800; }
        .amz-raw-icon-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .amz-raw-icon-btn > i { font-size: 14px; }
        .amz-toolbar-title { font-size: 1rem; flex-shrink: 0; }
        .amz-stat-badge--campaign { background: #4c7ed8; }
        .amz-stat-badge--acos     { background: #ea580c; }
        .amz-stat-badge--spend    { background: #ef4444; }
        .amz-stat-badge--clicks   { background: #f59e0b; }
        .amz-stat-badge--sold     { background: #8b5cf6; }
        .amz-stat-badge--cvr      { background: #16a34a; }
        .amz-stat-badge--cpc      { background: #0891b2; }
        .amz-stat-badge--sales    { background: #16a34a; }
        #amz-ads-raw-wrap #amazonAdsU7Pie { width: 100%; min-height: 400px; }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Amazon Ads', 'page_title' => 'Amazon Ads All'])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <div class="d-flex align-items-center flex-wrap gap-2 py-1">
                            <span id="amazonAdsCampaignBadgeWrap" class="amz-stat-badge amz-stat-badge--campaign" title="Distinct campaigns matching current filters">CAMPAIGN:<span id="amazonAdsCampaignBadgeValue">0</span></span>
                            <span id="amazonAdsOverallAcosBadgeWrap" class="amz-stat-badge amz-stat-badge--acos" title="Overall ACOS (L30) for the filtered set">ACOS:<span id="amazonAdsOverallAcosBadgeValue">0%</span></span>
                            <span id="amazonAdsSpendBadgeWrap" class="amz-stat-badge amz-stat-badge--spend" title="Spend (L30) total">SPEND:<span id="amazonAdsSpendBadgeValue">$0</span></span>
                            <span id="amazonAdsClicksBadgeWrap" class="amz-stat-badge amz-stat-badge--clicks" title="Clicks (L30) total">CLICKS:<span id="amazonAdsClicksBadgeValue">0</span></span>
                            <span id="amazonAdsSoldBadgeWrap" class="amz-stat-badge amz-stat-badge--sold" title="Sold (L30) total">SOLD:<span id="amazonAdsSoldBadgeValue">0</span></span>
                            <span id="amazonAdsCvrBadgeWrap" class="amz-stat-badge amz-stat-badge--cvr" title="CVR = Sold / Clicks">CVR:<span id="amazonAdsCvrBadgeValue">0%</span></span>
                            <span id="amazonAdsCpcBadgeWrap" class="amz-stat-badge amz-stat-badge--cpc" title="CPC = Spend / Clicks">CPC:<span id="amazonAdsCpcBadgeValue">$0</span></span>
                            <span id="amazonAdsSalesBadgeWrap" class="amz-stat-badge amz-stat-badge--sales" title="Sales (L30) total">SALES:<span id="amazonAdsSalesBadgeValue">$0</span></span>
                        </div>

                        <span id="amz-raw-total" class="badge bg-secondary">Total: —</span>
                        <span id="amz-raw-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="amz-raw-refresh" class="btn btn-sm btn-outline-primary amz-raw-icon-btn" title="Refresh grid" aria-label="Refresh grid">
                            <i class="fa fa-refresh"></i>
                        </button>
                        <button type="button" id="amazonAdsSectionExportBtn" class="btn btn-sm btn-success amz-raw-icon-btn" title="Export current page as CSV" aria-label="Export current page as CSV">
                            <i class="fas fa-file-csv"></i>
                        </button>
                        <a href="{{ route('amazon-ads.push-logs.index') }}" class="btn btn-sm btn-outline-secondary" title="Failed / skipped bid & budget pushes">Fail Cpg</a>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="amazonAdsBgtRuleBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsBgtRuleModal" title="Edit ACOS band thresholds and SBGT tier values">BGT RULE</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="amazonAdsSbidRuleBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsSbidRuleModal" title="Edit U2%/U1% thresholds and CPC multipliers for suggested SBID">SBID RULE</button>
                        <span class="vr align-self-center d-none d-md-inline-block mx-1"></span>
                        <button type="button" class="btn btn-sm btn-warning text-dark" id="amazonAdsPushSbgtBtn" title="Push SBGT tier as daily budget for the rows on this page (SP/SB only).">
                            <i class="fa fa-cloud-upload-alt"></i> SBGT
                        </button>
                        <button type="button" class="btn btn-sm btn-warning text-dark" id="amazonAdsPushSbidBtn" title="Push the SBID shown on this page for each row (SP/SB only).">
                            <i class="fa fa-cloud-upload-alt"></i> SBID
                        </button>
                        <span class="vr align-self-center d-none d-md-inline-block mx-1"></span>
                        <button type="button" class="btn btn-sm btn-success" id="amz-kw-bulk-push" title="Import keywords into every linked campaign at once">
                            <i class="fas fa-cloud-download-alt"></i> KW Bulk Push
                        </button>
                        <button type="button" class="btn btn-sm btn-success" id="amz-neg-bulk-push" title="Import negative keywords into every linked campaign at once">
                            <i class="fas fa-cloud-download-alt"></i> Neg Bulk Push
                        </button>
                    </div>

                    <div id="amz-raw-filter-bar" class="mb-3">
                        <div class="d-flex flex-wrap align-items-end gap-3 gap-md-4">
                            <div>
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterReportType">Table</label>
                                <select id="amazonAdsFilterReportType" class="form-select form-select-sm amz-raw-filter-select">
                                    <option value="all_reports">All (SP + SB)</option>
                                    <option value="sp_reports" selected>SP reports</option>
                                    <option value="sb_reports">SB reports</option>
                                    <option value="sd_reports">SD reports</option>
                                    <option value="sp_keywords">SP keywords</option>
                                    <option value="sp_negatives">SP negatives</option>
                                    <option value="bid_caps">Bid caps</option>
                                    <option value="fbm_targeting">FBM targeting</option>
                                </select>
                            </div>
                            <div>
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterSummaryRange">Range</label>
                                <select id="amazonAdsFilterSummaryRange" class="form-select form-select-sm amz-raw-filter-select">
                                    <option value="" selected>Calendar</option>
                                    <option value="L1">L1</option>
                                    <option value="L7">L7</option>
                                    <option value="L14">L14</option>
                                    <option value="L15">L15</option>
                                    <option value="L30">L30</option>
                                    <option value="L60">L60</option>
                                </select>
                            </div>
                            <div>
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterDateFrom">From</label>
                                <input type="date" id="amazonAdsFilterDateFrom" class="form-control form-control-sm amz-raw-date-input">
                            </div>
                            <div>
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterDateTo">To</label>
                                <input type="date" id="amazonAdsFilterDateTo" class="form-control form-control-sm amz-raw-date-input">
                            </div>
                            <div>
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterU7">U7%</label>
                                <select id="amazonAdsFilterU7" class="form-select form-select-sm amz-raw-filter-select">
                                    <option value="" selected>All</option>
                                    <option value="lt66">&lt; 66%</option>
                                    <option value="66_99">66 – 99%</option>
                                    <option value="gt99">&gt; 99%</option>
                                </select>
                            </div>
                            <div>
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterU2">U2%</label>
                                <select id="amazonAdsFilterU2" class="form-select form-select-sm amz-raw-filter-select">
                                    <option value="" selected>All</option>
                                    <option value="lt66">&lt; 66%</option>
                                    <option value="66_99">66 – 99%</option>
                                    <option value="gt99">&gt; 99%</option>
                                </select>
                            </div>
                            <div>
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterU1">U1%</label>
                                <select id="amazonAdsFilterU1" class="form-select form-select-sm amz-raw-filter-select">
                                    <option value="" selected>All</option>
                                    <option value="lt66">&lt; 66%</option>
                                    <option value="66_99">66 – 99%</option>
                                    <option value="gt99">&gt; 99%</option>
                                </select>
                            </div>
                            <div>
                                <label class="amz-raw-filter-label mb-0" for="amazonAdsFilterCampaignStatus">Stat</label>
                                <select id="amazonAdsFilterCampaignStatus" class="form-select form-select-sm amz-raw-filter-select">
                                    <option value="" selected>All</option>
                                    <option value="ENABLED">Enabled</option>
                                    <option value="PAUSED">Paused</option>
                                    <option value="ARCHIVED">Archived</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-end gap-2">
                                <button type="button" class="btn btn-sm btn-primary" id="amazonAdsFilterApply">Apply</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="amazonAdsFilterClear">Clear</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="amazonAdsU7PieOpenBtn" data-bs-toggle="modal" data-bs-target="#amazonAdsU7PieModal" title="Row counts by U7% band (U7 filter ignored). Click a slice for last 30 days.">U7% mix</button>
                            </div>
                        </div>
                    </div>

                    <div id="amz-raw-push-result" class="alert alert-secondary small d-none mt-2 mb-2 py-2" role="status" aria-live="polite">
                        <div class="fw-semibold mb-1" id="amz-raw-push-result-title"></div>
                        <pre id="amz-raw-push-result-pre" class="mb-0 small bg-white border rounded p-2" style="white-space:pre-wrap;max-height:280px;overflow:auto;"></pre>
                    </div>

                    <div id="amz-ads-raw-wrap">
                        <div class="p-2 bg-light border rounded-top d-flex align-items-center gap-2">
                            <input type="search" id="amz-filter-search" class="form-control" placeholder="Search Campaign..." autocomplete="off" aria-label="Search by campaign name" maxlength="100">
                            <span id="amz-raw-source-label" class="badge bg-dark text-nowrap"></span>
                        </div>
                        <div id="amz-ads-raw-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsU7PieModal" tabindex="-1" aria-labelledby="amazonAdsU7PieModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsU7PieModalLabel">U7% mix</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2">Row counts by U7% band (U7 grid filter ignored). Click a slice for the last 30 days.</p>
                    <div id="amazonAdsU7Pie" role="img" aria-label="U7 percent distribution pie chart"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
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
                    <p class="small text-muted mb-2" id="amazonAdsU7HistoryModalSub">Last 30 calendar days. Same U2/U1/Stat filters as the grid; U7 filter ignored.</p>
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

    <div class="modal fade" id="amazonAdsBgtRuleModal" tabindex="-1" aria-labelledby="amazonAdsBgtRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsBgtRuleModalLabel">BGT rule — ACOS % → Suggested Budget (SBGT)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Each row is an inclusive <strong>ACOS % range</strong> (From → To). Rows are checked
                        <strong>top to bottom</strong>; the first range that contains the campaign's ACOS gets its SBGT.
                        Use <code>9999</code> on <em>To</em> for the catch-all highest band.
                    </p>
                    <table class="table table-sm table-bordered align-middle mb-0" id="amazonAdsBgtRuleTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Label</th>
                                <th style="width:140px;">Color</th>
                                <th style="width:110px;">From (%)</th>
                                <th style="width:110px;">To (%)</th>
                                <th style="width:120px;">SBGT</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="amazonAdsBgtRuleBandsBody"></tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="amazonAdsBgtRuleAddBandBtn">
                        <i class="fas fa-plus me-1"></i>Add band
                    </button>
                    <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsBgtRuleModalError" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amazonAdsBgtRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="amazonAdsSbidRuleModal" tabindex="-1" aria-labelledby="amazonAdsSbidRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amazonAdsSbidRuleModalLabel">SBID rule — U2% / U1% → suggested bid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">When <strong>both</strong> U2% and U1% are <strong>below</strong> the low threshold, SBID = CPC × under multipliers (or fallback when no CPC). When <strong>both</strong> are <strong>above</strong> the high threshold, SBID = L1 CPC × over multiplier. Otherwise SBID shows —.</p>
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleUtilLow">Low threshold (%)</label>
                            <input type="number" step="0.1" class="form-control form-control-sm" id="amazonAdsSbidRuleUtilLow" name="util_low" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleUtilHigh">High threshold (%)</label>
                            <input type="number" step="0.1" class="form-control form-control-sm" id="amazonAdsSbidRuleUtilHigh" name="util_high" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleBothLowFallback">Fallback (no CPC)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleBothLowFallback" name="both_low_fallback" required>
                        </div>
                    </div>
                    <p class="small fw-semibold mb-1">Both below low — CPC multipliers</p>
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
                    <p class="small fw-semibold mb-1">Both above high</p>
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label small mb-0" for="amazonAdsSbidRuleHighMultL1">× L1 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="amazonAdsSbidRuleHighMultL1" name="both_high_mult_l1" required>
                        </div>
                    </div>
                    <p class="small text-danger mb-0 mt-2 d-none" id="amazonAdsSbidRuleModalError" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="amazonAdsSbidRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Shared campaign-link modal (keyword + negative), used by the KW / Neg "+" grid columns --}}
    <div class="modal fade" id="amzLinkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-white" id="amzLinkModalHeader">
                    <h5 class="modal-title"><i class="fas fa-link"></i> <span id="amzLinkModalTitle">Link Campaigns</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Link one or more campaigns to <strong id="amzLinkSource"></strong>. <span id="amzLinkHint"></span></p>
                    <input type="text" id="amzLinkInput" class="form-control" placeholder="Search campaign..." autocomplete="off">
                    <div id="amzLinkSuggestions" class="list-group mt-2 d-none" style="max-height: 260px; overflow-y: auto;"></div>
                    <div id="amzLinkSelectedWrap" class="mt-2 d-none">
                        <div class="small text-muted mb-1">Selected to link (<span id="amzLinkSelectedCount">0</span>):</div>
                        <div id="amzLinkSelected" class="d-flex flex-wrap gap-1"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="amzLinkSaveBtn"><i class="fas fa-link"></i> Link Campaign(s)</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var rawSources = @json($rawSources ?? []);
            var amazonAdsDefaultReportDates = @json($defaultReportRangeDates ?? (object) []);
            var dataUrlTemplate = @json(url('/amazon-ads/raw-data')) + '/';
            var pushSpSbidsUrl = @json(route('amazon.ads.push-sp-sbids'));
            var pushSbSbidsUrl = @json(route('amazon.ads.push-sb-sbids'));
            var pushSpSbgtsUrl = @json(route('amazon.ads.push-sp-sbgts'));
            var pushSbSbgtsUrl = @json(route('amazon.ads.push-sb-sbgts'));
            var bgtRuleGetUrl = @json(route('amazon.ads.bgt-rule'));
            var bgtRuleSaveUrl = @json(route('amazon.ads.bgt-rule.save'));
            var sbidRuleGetUrl = @json(route('amazon.ads.sbid-rule'));
            var sbidRuleSaveUrl = @json(route('amazon.ads.sbid-rule.save'));
            var u7PieDistribUrl = @json(url('/amazon-ads/u7-distribution')) + '/';
            var u7PieHistoryUrl = @json(url('/amazon-ads/u7-distribution-history')) + '/';
            window.amazonAdsBgtRule = @json($amazonAdsBgtRule ?? null);
            window.amazonAdsSbidRule = @json($amazonAdsSbidRule ?? null);

            // Campaign-link (keyword + negative) endpoints for the KW / Neg "+" columns.
            var kwLinkCampaignsUrl = @json(route('amazon.ads.campaign-link.campaigns'));
            var kwLinkSaveUrl = @json(route('amazon.ads.campaign-link.link'));
            var negLinkCampaignsUrl = @json(route('amazon.ads.negative-link.campaigns'));
            var negLinkSaveUrl = @json(route('amazon.ads.negative-link.link'));
            var kwPushUrl = @json(route('amazon.ads.campaign-link.push'));
            var negPushUrl = @json(route('amazon.ads.negative-link.push'));
            var kwPushAllUrl = @json(route('amazon.ads.campaign-link.push-all'));
            var negPushAllUrl = @json(route('amazon.ads.negative-link.push-all'));

            var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            var table = null;
            var activeRawSourceKey = 'sp_reports';
            var amzDrawCounter = 0;
            var amzU7PieChart = null;
            var amzU7PieRefreshTimer = null;

            var HIDDEN_COLUMNS = ['id', 'profile_id', 'campaign_id', 'report_date_range', 'ad_type', 'date', 'startDate', 'endDate'];
            var NON_ORDERABLE_COLUMNS = ['U7%', 'U2%', 'U1%', 'CPC3', 'CPC2', 'L7spend', 'L2spend', 'L1spend', 'L1cost', 'L1clicks', 'INV'];
            var PIE_SOURCES = ['sp_reports', 'sb_reports', 'sd_reports'];

            // ---- number helpers ----
            function amzFiniteNumber(data) {
                if (data === null || data === undefined || data === '') return NaN;
                var n = typeof data === 'number' ? data : parseFloat(String(data).replace(/,/g, ''));
                return (typeof n === 'number' && isFinite(n)) ? n : NaN;
            }
            function amzRawNumberText(data) {
                var n = amzFiniteNumber(data);
                return isNaN(n) ? '' : String(n);
            }
            function amzDash() { return '<span class="text-muted">--</span>'; }
            function amzEsc(s) {
                return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            // ---- rule helpers (ACOS bands / SBGT tiers) ----
            function amzBgtRuleBands() {
                var r = window.amazonAdsBgtRule || {};
                return (r && Array.isArray(r.bands)) ? r.bands : [];
            }
            function amzBandForAcos(acos) {
                var a = typeof acos === 'number' ? acos : parseFloat(String(acos));
                if (isNaN(a)) return null;
                var bands = amzBgtRuleBands();
                for (var i = 0; i < bands.length; i++) {
                    var from = parseFloat(bands[i].acos_from);
                    var to = parseFloat(bands[i].acos_to);
                    if (isNaN(from)) from = 0;
                    if (isNaN(to)) to = 9999;
                    if (a >= from && a <= to) return bands[i];
                }
                return null;
            }
            function amzAcosTierColor(acos) {
                var band = amzBandForAcos(acos);
                return (band && band.color) ? band.color : '#6b7280';
            }
            function amzSbgtTierColor(sbgt) {
                var s = parseInt(sbgt, 10);
                if (isNaN(s)) return '#6b7280';
                var bands = amzBgtRuleBands();
                for (var i = 0; i < bands.length; i++) {
                    if (parseInt(bands[i].sbgt, 10) === s && bands[i].color) return bands[i].color;
                }
                return '#6b7280';
            }
            function amzAllowedSbgtTiers() {
                var bands = amzBgtRuleBands();
                var out = [];
                for (var i = 0; i < bands.length; i++) {
                    var t = parseInt(bands[i].sbgt, 10);
                    if (!isNaN(t) && t > 0 && out.indexOf(t) === -1) out.push(t);
                }
                out.sort(function (x, y) { return x - y; });
                return out;
            }

            // ---- Tabulator formatters ----
            function fmtDashNumberRaw(cell) {
                var v = cell.getValue();
                var n = amzFiniteNumber(v);
                if (isNaN(n)) return amzDash();
                return '<span class="fw-semibold">' + amzEsc(amzRawNumberText(v)) + '</span>';
            }
            function fmtDashRounded(cell) {
                var n = amzFiniteNumber(cell.getValue());
                if (isNaN(n)) return amzDash();
                return '<span class="fw-semibold">' + Math.round(n).toLocaleString() + '</span>';
            }
            function fmtDashInt(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var n = parseInt(v, 10);
                if (isNaN(n)) return amzDash();
                return '<span class="fw-semibold">' + n.toLocaleString() + '</span>';
            }
            function fmt2dec(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var n = typeof v === 'number' ? v : parseFloat(v);
                if (isNaN(n)) return amzDash();
                return n.toFixed(2);
            }
            function fmtSbid(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var n = typeof v === 'number' ? v : parseFloat(String(v).replace(/,/g, ''));
                if (isNaN(n)) return amzDash();
                return '<span class="fw-semibold">' + n.toFixed(2) + '</span>';
            }
            function fmtCvr(cell) {
                var n = amzFiniteNumber(cell.getValue());
                if (isNaN(n)) return amzDash();
                return '<span class="fw-semibold">' + Math.round(n) + '%</span>';
            }
            function fmtAcos(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var n = typeof v === 'number' ? v : parseFloat(v);
                if (isNaN(n)) return amzDash();
                var r = Math.round(n);
                return '<span class="fw-semibold" style="color:' + amzAcosTierColor(r) + ';">' + r + '%</span>';
            }
            function fmtSbgt(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var t = parseInt(v, 10);
                if (isNaN(t)) return amzDash();
                return '<span class="fw-semibold" style="color:' + amzSbgtTierColor(t) + ';">' + t + '</span>';
            }
            function fmtUtilPercent(cell) {
                var td = cell.getElement();
                if (td) td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                var v = cell.getValue();
                if (v === null || v === undefined || v === '') return amzDash();
                var n = typeof v === 'number' ? v : parseFloat(v);
                if (isNaN(n)) return amzDash();
                if (td) {
                    if (n >= 66 && n <= 99) td.classList.add('green-bg');
                    else if (n > 99) td.classList.add('pink-bg');
                    else td.classList.add('red-bg');
                }
                return Math.round(n) + '%';
            }
            function fmtCampaignStatus(cell) {
                var v = cell.getValue();
                var raw = (v === null || v === undefined) ? '' : String(v).trim();
                if (raw === '') return '<span class="amz-raw-status-cell text-muted" title="—">—</span>';
                var enabled = raw.toUpperCase() === 'ENABLED';
                var color = enabled ? '#16a34a' : '#dc2626';
                var tip = amzEsc(raw);
                return '<span class="amz-raw-status-cell" title="' + tip + '" style="display:inline-flex;align-items:center;justify-content:center;">'
                     + '<span class="d-inline-block rounded-circle" style="width:10px;height:10px;background-color:' + color + ';"></span></span>';
            }
            function fmtAdType(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined) return '';
                var u = String(v).trim().toUpperCase();
                if (u === 'SPONSORED_PRODUCTS') return 'SP';
                if (u === 'SPONSORED_BRANDS') return 'SB';
                return amzEsc(String(v).trim());
            }
            function fmtMatchType(cell) {
                var v = cell.getValue();
                if (v === null || v === undefined || String(v).trim() === '') return '<span class="text-muted">—</span>';
                var map = {
                    'BROAD': 'Broad', 'PHRASE': 'Phrase', 'EXACT': 'Exact',
                    'NEGATIVE_EXACT': 'Neg Exact', 'NEGATIVE_PHRASE': 'Neg Phrase',
                    'TARGETING_EXPRESSION': 'Target', 'TARGETING_EXPRESSION_PREDEFINED': 'Auto'
                };
                var u = String(v).trim().toUpperCase();
                return amzEsc(map[u] || String(v).trim());
            }
            function fmtCampaignName(cell) {
                var v = cell.getValue();
                var s = (v === null || v === undefined) ? '' : String(v);
                var esc = amzEsc(s);
                var attr = esc.replace(/'/g, '&#39;');
                var copy = '<i class="fas fa-copy amz-copy-name" role="button" tabindex="0" title="Copy campaign name"'
                         + ' data-copy="' + attr + '" style="margin-left:6px;color:#94a3b8;cursor:pointer;flex-shrink:0;"></i>';
                return '<span style="display:inline-flex;align-items:center;gap:2px;max-width:100%;">'
                     + '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc + '</span>' + copy + '</span>';
            }

            // Map a source display-column name to Tabulator column def extras.
            function amzApplyColFormat(col, c) {
                if (c === 'campaignName') { col.formatter = fmtCampaignName; col.minWidth = 200; col.widthGrow = 4; col.hozAlign = 'left'; return; }
                if (c === 'campaignStatus') { col.title = 'Stat'; col.formatter = fmtCampaignStatus; col.width = 48; col.minWidth = 44; return; }
                if (c === 'ad_type') { col.formatter = fmtAdType; return; }
                if (c === 'adGroupName') { col.title = 'Ad Group'; col.hozAlign = 'left'; col.minWidth = 150; col.widthGrow = 2; return; }
                if (c === 'keyword') { col.title = 'Keyword'; col.hozAlign = 'left'; col.minWidth = 180; col.widthGrow = 3; return; }
                if (c === 'keywordText') { col.title = 'Negative KW'; col.hozAlign = 'left'; col.minWidth = 180; col.widthGrow = 3; return; }
                if (c === 'matchType') { col.title = 'Match'; col.formatter = fmtMatchType; col.minWidth = 90; return; }
                if (c === 'level') { col.title = 'Level'; col.minWidth = 80; return; }
                if (c === 'state') { col.title = 'State'; col.formatter = fmtCampaignStatus; col.width = 56; col.minWidth = 48; return; }
                if (c === 'campaign_id') { col.title = 'Camp ID'; col.minWidth = 100; return; }
                if (c === 'ad_group_id') { col.title = 'AdGrp ID'; col.minWidth = 100; return; }
                if (c === 'report_date_range') { col.title = 'Range'; col.minWidth = 80; return; }
                if (c === 'acosClicks14d') { col.title = 'ACOS14'; col.formatter = fmtAcos; return; }
                if (c === 'purchases30d') { col.title = 'Sold'; col.formatter = fmtDashInt; return; }
                if (c === 'impressions') { col.title = 'Impr'; col.formatter = fmtDashInt; return; }
                if (c === 'last_sbid') { col.title = 'Lbid'; col.formatter = fmtSbid; return; }
                if (c === 'sbid') { col.title = 'SBID'; col.formatter = fmtSbid; return; }
                if (c === 'bgt') { col.title = 'BGT'; col.formatter = fmtDashNumberRaw; return; }
                if (c === 'sbgt') { col.title = 'SBGT'; col.formatter = fmtSbgt; return; }
                if (c === 'INV') { col.title = 'INV'; col.formatter = fmtDashInt; return; }
                if (c === 'Prchase') { col.title = 'Sold'; col.formatter = fmtDashInt; return; }
                if (c === 'Cvr') { col.title = 'Cvr'; col.formatter = fmtCvr; return; }
                if (c === 'ACOS') { col.title = 'ACOS'; col.formatter = fmtAcos; return; }
                if (c === 'sales') { col.title = 'Sales'; col.formatter = fmtDashNumberRaw; return; }
                if (c === 'cost') { col.title = 'SPL30'; col.formatter = fmtDashRounded; return; }
                if (c === 'L7spend') { col.title = 'L7SP'; col.formatter = fmtDashNumberRaw; return; }
                if (c === 'L2spend') { col.title = 'L2SP'; col.formatter = fmtDashNumberRaw; return; }
                if (c === 'L1spend') { col.title = 'L1SP'; col.formatter = fmtDashNumberRaw; return; }
                if (c === 'L1cost') { col.title = 'L1Cost'; col.formatter = fmtDashRounded; return; }
                if (c === 'L1clicks') { col.title = 'L1Clk'; col.formatter = fmtDashInt; return; }
                if (c === 'U7%' || c === 'U2%' || c === 'U1%') { col.formatter = fmtUtilPercent; return; }
                if (c === 'CPC3') { col.title = 'CPC3'; col.formatter = fmt2dec; return; }
                if (c === 'CPC2') { col.title = 'CPC2'; col.formatter = fmt2dec; return; }
                if (c === 'costPerClick') { col.title = 'CPC1'; col.formatter = fmt2dec; return; }
                if (c === 'sales30d') { col.title = 'SL 30'; col.formatter = fmtDashRounded; return; }
                if (c === 'clicks') { col.title = 'Click'; col.formatter = fmtDashInt; return; }
            }

            function amzLinkCampaignFromRow(cell) {
                var d = cell.getRow().getData() || {};
                return d.campaignName ? String(d.campaignName) : '';
            }
            function amzLinkColFormatter(mode) {
                var linkCls = mode === 'neg' ? 'amz-neglink-btn' : 'amz-kwlink-btn';
                var pushCls = mode === 'neg' ? 'amz-negpush-btn' : 'amz-kwpush-btn';
                var linkColor = mode === 'neg' ? 'btn-outline-danger' : 'btn-outline-primary';
                return function (cell) {
                    var c = amzLinkCampaignFromRow(cell);
                    if (!c) return '';
                    var attr = String(c).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    return '<div class="d-inline-flex gap-1">'
                        + '<button type="button" class="btn btn-sm ' + linkColor + ' ' + linkCls + '" data-campaign="' + attr + '" title="Link campaigns" style="padding:1px 7px;"><i class="fas fa-plus"></i></button>'
                        + '<button type="button" class="btn btn-sm btn-success ' + pushCls + '" data-campaign="' + attr + '" title="Push linked campaign keywords into this campaign" style="padding:1px 7px;"><i class="fas fa-cloud-download-alt"></i></button>'
                        + '</div>';
                };
            }

            function amzBuildColumns(source) {
                var cols = (rawSources[source] && rawSources[source].columns) ? rawSources[source].columns : [];
                var defs = [{
                    title: '', field: '__sel', formatter: 'rowSelection', titleFormatter: 'rowSelection',
                    headerSort: false, hozAlign: 'center', headerHozAlign: 'center', width: 40, minWidth: 40
                }, {
                    title: 'KW Link', field: '__kwlink', headerSort: false, hozAlign: 'center', headerHozAlign: 'center',
                    width: 92, minWidth: 84, formatter: amzLinkColFormatter('kw')
                }, {
                    title: 'Neg Link', field: '__neglink', headerSort: false, hozAlign: 'center', headerHozAlign: 'center',
                    width: 92, minWidth: 84, formatter: amzLinkColFormatter('neg')
                }];
                cols.forEach(function (c) {
                    var col = { field: c, title: c, hozAlign: 'center', headerHozAlign: 'center', minWidth: 56, widthGrow: 0 };
                    col.headerSort = NON_ORDERABLE_COLUMNS.indexOf(c) === -1;
                    if (HIDDEN_COLUMNS.indexOf(c) !== -1) col.visible = false;
                    amzApplyColFormat(col, c);
                    defs.push(col);
                });
                return defs;
            }

            // ---- filter payload ----
            function amzSearchQueryVal() {
                var el = document.getElementById('amz-filter-search');
                if (!el) return '';
                var v = String(el.value || '').replace(/\s+/g, ' ').trim();
                return v.length > 100 ? v.slice(0, 100) : v;
            }
            function amzFilterPayload() {
                var g = function (id) { var e = document.getElementById(id); return e ? (e.value || '') : ''; };
                return {
                    date_from: g('amazonAdsFilterDateFrom'),
                    date_to: g('amazonAdsFilterDateTo'),
                    summary_report_range: g('amazonAdsFilterSummaryRange'),
                    filter_u7: g('amazonAdsFilterU7'),
                    filter_u2: g('amazonAdsFilterU2'),
                    filter_u1: g('amazonAdsFilterU1'),
                    filter_campaign_status: g('amazonAdsFilterCampaignStatus')
                };
            }

            // ---- badges ----
            function amzSetText(id, txt) { var e = document.getElementById(id); if (e) e.textContent = txt; }
            function amzUpdateBadges(json) {
                var camp = (json && typeof json.distinctCampaignCount === 'number' && isFinite(json.distinctCampaignCount)) ? json.distinctCampaignCount : null;
                var acos = (json && typeof json.overallAcosPercent === 'number' && isFinite(json.overallAcosPercent)) ? json.overallAcosPercent : null;
                var spend = (json && typeof json.spendTotal === 'number' && isFinite(json.spendTotal)) ? json.spendTotal : null;
                var clicks = (json && typeof json.clicksTotal === 'number' && isFinite(json.clicksTotal)) ? json.clicksTotal : null;
                var sold = (json && typeof json.soldTotal === 'number' && isFinite(json.soldTotal)) ? json.soldTotal : null;
                var sales = (json && typeof json.salesTotal === 'number' && isFinite(json.salesTotal)) ? json.salesTotal : null;

                amzSetText('amazonAdsCampaignBadgeValue', camp === null ? '0' : Number(camp).toLocaleString('en-US'));
                amzSetText('amazonAdsOverallAcosBadgeValue', acos === null ? '—' : (Math.round(acos) + '%'));
                amzSetText('amazonAdsSpendBadgeValue', spend === null ? '$0' : ('$' + Number(spend).toLocaleString('en-US', { maximumFractionDigits: 0 })));
                amzSetText('amazonAdsClicksBadgeValue', clicks === null ? '0' : Number(clicks).toLocaleString('en-US'));
                amzSetText('amazonAdsSoldBadgeValue', sold === null ? '0' : Number(sold).toLocaleString('en-US'));
                amzSetText('amazonAdsSalesBadgeValue', sales === null ? '$0' : ('$' + Number(sales).toLocaleString('en-US', { maximumFractionDigits: 0 })));
                amzSetText('amazonAdsCvrBadgeValue', (sold !== null && clicks && clicks > 0) ? ((sold / clicks * 100).toFixed(2) + '%') : '—');
                amzSetText('amazonAdsCpcBadgeValue', (spend !== null && clicks && clicks > 0) ? ('$' + (spend / clicks).toFixed(2)) : '$0');
            }
            function amzClearBadges() { amzUpdateBadges({}); }

            function amzUpdateTotalBadge(n) {
                var el = document.getElementById('amz-raw-total');
                if (el) el.textContent = 'Total: ' + (isFinite(n) ? Number(n).toLocaleString() : '—');
            }
            function amzUpdatePageInfoBadge() {
                var el = document.getElementById('amz-raw-page-info');
                if (!el || !table) return;
                try { el.textContent = 'Page: ' + table.getPage() + ' / ' + table.getPageMax(); }
                catch (e) { el.textContent = 'Page: —'; }
            }
            function amzUpdateSourceLabel() {
                var el = document.getElementById('amz-raw-source-label');
                if (!el) return;
                var tbl = (rawSources[activeRawSourceKey] && rawSources[activeRawSourceKey].table) ? rawSources[activeRawSourceKey].table : activeRawSourceKey;
                el.textContent = tbl;
            }
            function amzRefreshUiSoon() {
                setTimeout(function () { amzUpdatePageInfoBadge(); }, 0);
            }

            // ---- AJAX bridge: translate Tabulator remote params -> DataTables protocol ----
            function amzAjaxRequestFunc(url, config, params) {
                var source = activeRawSourceKey || 'sp_reports';
                var cols = (rawSources[source] && rawSources[source].columns) ? rawSources[source].columns : [];
                var size = parseInt(params.size, 10) || 100;
                var page = parseInt(params.page, 10) || 1;
                var body = new URLSearchParams();
                body.set('draw', String(++amzDrawCounter));
                body.set('start', String((page - 1) * size));
                body.set('length', String(size));
                body.set('search[value]', amzSearchQueryVal());
                body.set('search[regex]', 'false');
                var sorters = params.sort || [];
                if (sorters.length) {
                    var idx = cols.indexOf(sorters[0].field);
                    if (idx < 0) idx = 0;
                    body.set('order[0][column]', String(idx));
                    body.set('order[0][dir]', sorters[0].dir === 'asc' ? 'asc' : 'desc');
                }
                var f = amzFilterPayload();
                Object.keys(f).forEach(function (k) { body.set(k, f[k]); });
                body.set('_token', csrfToken);
                return fetch(dataUrlTemplate + encodeURIComponent(source), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    credentials: 'same-origin',
                    body: body.toString()
                }).then(function (res) { return res.json(); });
            }

            table = new Tabulator('#amz-ads-raw-table', {
                columns: amzBuildColumns('sp_reports'),
                ajaxURL: dataUrlTemplate + 'sp_reports',
                ajaxRequestFunc: amzAjaxRequestFunc,
                height: '650px',
                layout: 'fitColumns',
                layoutColumnsOnNewData: true,
                pagination: true,
                paginationMode: 'remote',
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 250, 500],
                paginationCounter: 'rows',
                paginationButtonCount: 10,
                paginationInitialPage: 1,
                sortMode: 'remote',
                placeholder: 'No rows for this source.',
                selectableRows: true,
                ajaxResponse: function (url, params, response) {
                    if (!response || typeof response !== 'object') {
                        amzUpdateTotalBadge(0);
                        amzClearBadges();
                        return { last_page: 1, data: [] };
                    }
                    var size = parseInt(params.size, 10) || 100;
                    var filtered = Number(response.recordsFiltered);
                    if (!isFinite(filtered) || filtered < 0) filtered = 0;
                    var lastPage = Math.max(1, Math.ceil(filtered / size));
                    amzUpdateBadges(response);
                    amzUpdateTotalBadge(filtered);
                    amzRefreshUiSoon();
                    amzRefreshU7PieDebounced();
                    return { last_page: lastPage, data: Array.isArray(response.data) ? response.data : [] };
                }
            });

            table.on('pageLoaded', amzRefreshUiSoon);
            table.on('dataLoaded', amzRefreshUiSoon);
            table.on('dataLoadError', function (error) {
                console.error('amazon-ads raw data load error', error);
                amzUpdateTotalBadge(NaN);
            });

            // ---- reload / source switching ----
            function amzReloadGrid() {
                if (!table) return;
                Promise.resolve(table.setData()).catch(function () {});
            }
            function amzReloadGridForFilters() {
                if (!table) return;
                var p = 1;
                try { p = table.getPage(); } catch (e) {}
                if (p && p !== 1) { table.setPage(1); } else { table.setData(); }
                amzRefreshU7PieDebounced();
            }

            function amzSetDatesToLatestForSource(sourceKey) {
                var d = amazonAdsDefaultReportDates[sourceKey];
                var fromEl = document.getElementById('amazonAdsFilterDateFrom');
                var toEl = document.getElementById('amazonAdsFilterDateTo');
                if (!fromEl || !toEl) return;
                if (d && typeof d === 'string') { fromEl.value = d; toEl.value = d; }
                else { fromEl.value = ''; toEl.value = ''; }
            }

            function amzUpdatePushButtons() {
                var sbidBtn = document.getElementById('amazonAdsPushSbidBtn');
                var sbgtBtn = document.getElementById('amazonAdsPushSbgtBtn');
                var isSp = activeRawSourceKey === 'sp_reports';
                var isSb = activeRawSourceKey === 'sb_reports';
                var ok = isSp || isSb;
                if (sbidBtn) {
                    sbidBtn.disabled = !ok;
                    sbidBtn.title = ok ? ('Pushes the SBID shown on this page for each row (' + (isSp ? 'SP keywords/targets' : 'SB keywords') + ' API)') : 'Switch to SP or SB reports to push SBID';
                }
                if (sbgtBtn) {
                    sbgtBtn.disabled = !ok;
                    sbgtBtn.title = ok ? ('Sets ' + (isSp ? 'SP' : 'SB') + ' daily budget on Amazon to each row SBGT tier as dollars') : 'Switch to SP or SB reports to push SBGT';
                }
            }
            function amzUpdatePieButton() {
                var btn = document.getElementById('amazonAdsU7PieOpenBtn');
                if (!btn) return;
                var ok = PIE_SOURCES.indexOf(activeRawSourceKey) !== -1;
                btn.disabled = !ok;
                btn.title = ok ? 'Row counts by U7% band (U7 filter ignored).' : 'U7% mix is available for SP / SB / SD reports only';
            }

            function amzSwitchSource(sourceKey) {
                if (!sourceKey || !rawSources[sourceKey]) sourceKey = 'sp_reports';
                activeRawSourceKey = sourceKey;
                amzSetDatesToLatestForSource(sourceKey);
                amzClearBadges();
                amzUpdatePushButtons();
                amzUpdatePieButton();
                amzUpdateSourceLabel();
                if (table) {
                    table.setColumns(amzBuildColumns(sourceKey));
                    Promise.resolve(table.setData()).catch(function () {});
                }
            }

            var reportTypeEl = document.getElementById('amazonAdsFilterReportType');
            if (reportTypeEl) {
                reportTypeEl.addEventListener('change', function () { amzSwitchSource(this.value); });
            }

            // Auto-reload filters
            ['amazonAdsFilterSummaryRange', 'amazonAdsFilterU7', 'amazonAdsFilterU2', 'amazonAdsFilterU1', 'amazonAdsFilterCampaignStatus'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('change', amzReloadGridForFilters);
            });
            // Apply / Clear (dates need Apply)
            var applyBtn = document.getElementById('amazonAdsFilterApply');
            if (applyBtn) applyBtn.addEventListener('click', amzReloadGridForFilters);
            var clearBtn = document.getElementById('amazonAdsFilterClear');
            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    ['amazonAdsFilterSummaryRange', 'amazonAdsFilterU7', 'amazonAdsFilterU2', 'amazonAdsFilterU1', 'amazonAdsFilterCampaignStatus'].forEach(function (id) {
                        var el = document.getElementById(id); if (el) el.value = '';
                    });
                    amzSetDatesToLatestForSource(activeRawSourceKey);
                    var s = document.getElementById('amz-filter-search'); if (s) s.value = '';
                    amzReloadGridForFilters();
                });
            }

            // Search box (debounced)
            var searchEl = document.getElementById('amz-filter-search');
            if (searchEl) {
                var searchTimer = null;
                var lastSearch = amzSearchQueryVal();
                var schedule = function (immediate) {
                    if (searchTimer) { clearTimeout(searchTimer); searchTimer = null; }
                    var run = function () {
                        var v = amzSearchQueryVal();
                        if (v === lastSearch) return;
                        lastSearch = v;
                        amzReloadGridForFilters();
                    };
                    if (immediate) run(); else searchTimer = setTimeout(run, 300);
                };
                searchEl.addEventListener('input', function () { schedule(false); });
                searchEl.addEventListener('search', function () { schedule(true); });
                searchEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); schedule(true); } });
            }

            document.getElementById('amz-raw-refresh').addEventListener('click', function () {
                Promise.resolve(table.setData()).finally(amzRefreshUiSoon);
            });
            document.getElementById('amazonAdsSectionExportBtn').addEventListener('click', function () {
                var tbl = (rawSources[activeRawSourceKey] && rawSources[activeRawSourceKey].table) ? rawSources[activeRawSourceKey].table : 'export';
                var d = new Date().toISOString().slice(0, 10);
                table.download('csv', 'Amazon_' + tbl + '_Export_' + d + '.csv');
            });

            // Copy-to-clipboard for campaign name icon
            document.addEventListener('click', function (e) {
                var icon = e.target.closest ? e.target.closest('.amz-copy-name') : null;
                if (!icon) return;
                e.stopPropagation();
                e.preventDefault();
                var tmp = document.createElement('textarea');
                tmp.innerHTML = icon.getAttribute('data-copy') || '';
                var text = tmp.value;
                var done = function () {
                    var prev = icon.className;
                    icon.className = 'fas fa-check amz-copy-name';
                    icon.style.color = '#22c55e';
                    setTimeout(function () { icon.className = prev; icon.style.color = '#94a3b8'; }, 1000);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function () {});
                } else {
                    try {
                        var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                        document.body.appendChild(ta); ta.focus(); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); done();
                    } catch (err) {}
                }
            });

            // ---- push result panel ----
            function amzShowPushResult(title, body, variant) {
                var wrap = document.getElementById('amz-raw-push-result');
                var tEl = document.getElementById('amz-raw-push-result-title');
                var pre = document.getElementById('amz-raw-push-result-pre');
                if (!wrap || !tEl || !pre) return;
                wrap.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-secondary', 'alert-info');
                wrap.classList.add(variant === 'error' ? 'alert-danger' : (variant === 'loading' ? 'alert-info' : 'alert-success'));
                if (variant === 'loading') {
                    tEl.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i>' + amzEsc(title);
                } else {
                    tEl.textContent = title;
                }
                pre.textContent = body || '(no output)';
                wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            // ---- push rows builders ----
            function amzPickBidFromRow(row) {
                var s = parseFloat(row.sbid);
                if (!isNaN(s) && s > 0) return s;
                var l = parseFloat(row.last_sbid);
                if (!isNaN(l) && l > 0) return l;
                return null;
            }
            function amzPickSbgtTierFromRow(row) {
                var t = parseInt(row.sbgt, 10);
                if (isNaN(t)) return null;
                return amzAllowedSbgtTiers().indexOf(t) !== -1 ? t : null;
            }
            function amzCurrentPushRows() {
                if (!table) return [];
                var selected = table.getSelectedData();
                return (selected && selected.length > 0) ? selected : table.getData('active');
            }
            function amzCollectSbidRows() {
                var out = [];
                amzCurrentPushRows().forEach(function (row) {
                    if (!row) return;
                    var cid = row.campaign_id;
                    if (cid === null || cid === undefined || String(cid).trim() === '') return;
                    var bid = amzPickBidFromRow(row);
                    if (bid === null) return;
                    out.push({ campaign_id: String(cid).trim(), bid: bid, campaignName: row.campaignName != null ? String(row.campaignName) : '' });
                });
                return out;
            }
            function amzCollectSbgtRows() {
                var out = [];
                amzCurrentPushRows().forEach(function (row) {
                    if (!row) return;
                    var cid = row.campaign_id;
                    if (cid === null || cid === undefined || String(cid).trim() === '') return;
                    var tier = amzPickSbgtTierFromRow(row);
                    if (tier === null) return;
                    out.push({ campaign_id: String(cid).trim(), sbgt: tier });
                });
                return out;
            }
            function amzRunPush(opts) {
                if (!opts.rows.length) {
                    window.alert('No eligible rows to push on this page.');
                    return;
                }
                if (!window.confirm(opts.confirmMsg)) return;
                var btn = opts.btn;
                var origHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Pushing…';
                amzShowPushResult(opts.loadingTitle, opts.loadingDetail, 'loading');
                fetch(opts.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ rows: opts.rows })
                })
                    .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, status: res.status, body: body }; }); })
                    .then(function (out) {
                        var b = out.body || {};
                        var success = out.ok && b.ok !== false && b.status !== 422 && b.status !== 500;
                        var title = opts.label + ' — ' + (success ? 'finished' : 'failed');
                        var text = (b.message ? b.message + '\n\n' : '') + JSON.stringify(b, null, 2);
                        amzShowPushResult(title, text, success ? 'success' : 'error');
                        if (success && table) Promise.resolve(table.setData()).finally(amzRefreshUiSoon);
                    })
                    .catch(function (err) { amzShowPushResult('Request failed', String(err && err.message ? err.message : err), 'error'); })
                    .finally(function () { btn.innerHTML = origHtml; btn.disabled = false; });
            }

            var pushSbidBtn = document.getElementById('amazonAdsPushSbidBtn');
            if (pushSbidBtn) {
                pushSbidBtn.addEventListener('click', function () {
                    var isSp = activeRawSourceKey === 'sp_reports';
                    var rows = amzCollectSbidRows();
                    var nSel = table && table.getSelectedData ? table.getSelectedData().length : 0;
                    var scope = nSel > 0 ? ('the ' + rows.length + ' checked row(s)') : ('all ' + rows.length + ' eligible row(s) on this page');
                    amzRunPush({
                        url: isSp ? pushSpSbidsUrl : pushSbSbidsUrl,
                        btn: pushSbidBtn,
                        rows: rows,
                        label: 'SBID push',
                        confirmMsg: 'Push SBID to ' + scope + '? Each row uses the SBID shown in the grid (Lbid fallback).',
                        loadingTitle: 'Pushing SBID…',
                        loadingDetail: 'Updating SBIDs for ' + rows.length + ' row(s). Waiting for Amazon Ads API — do not close this tab.'
                    });
                });
            }
            var pushSbgtBtn = document.getElementById('amazonAdsPushSbgtBtn');
            if (pushSbgtBtn) {
                pushSbgtBtn.addEventListener('click', function () {
                    var isSp = activeRawSourceKey === 'sp_reports';
                    var rows = amzCollectSbgtRows();
                    var nSel = table && table.getSelectedData ? table.getSelectedData().length : 0;
                    var scope = nSel > 0 ? ('the ' + rows.length + ' checked row(s)') : ('all ' + rows.length + ' eligible row(s) on this page');
                    amzRunPush({
                        url: isSp ? pushSpSbgtsUrl : pushSbSbgtsUrl,
                        btn: pushSbgtBtn,
                        rows: rows,
                        label: 'SBGT push',
                        confirmMsg: 'Push SBGT to ' + scope + '? Each row sets the daily budget to its SBGT tier (in dollars).',
                        loadingTitle: 'Pushing SBGT…',
                        loadingDetail: 'Updating budgets for ' + rows.length + ' row(s). Waiting for Amazon Ads API — do not close this tab.'
                    });
                });
            }

            // ---- U7% pie + history (Highcharts) ----
            function amzPieSource() {
                return PIE_SOURCES.indexOf(activeRawSourceKey) !== -1 ? activeRawSourceKey : null;
            }
            function amzU7PieModalIsOpen() {
                var m = document.getElementById('amazonAdsU7PieModal');
                return !!(m && m.classList.contains('show'));
            }
            function amzRefreshU7PieDebounced() {
                if (amzU7PieRefreshTimer) clearTimeout(amzU7PieRefreshTimer);
                amzU7PieRefreshTimer = setTimeout(function () { if (amzU7PieModalIsOpen()) amzRefreshU7Pie(); }, 280);
            }
            function amzPieFilterData() {
                var f = amzFilterPayload();
                return {
                    _token: csrfToken,
                    date_from: f.date_from,
                    date_to: f.date_to,
                    summary_report_range: f.summary_report_range,
                    filter_u2: f.filter_u2,
                    filter_u1: f.filter_u1,
                    filter_campaign_status: f.filter_campaign_status
                };
            }
            function amzRefreshU7Pie() {
                var box = document.getElementById('amazonAdsU7Pie');
                if (!box || !amzU7PieModalIsOpen()) return;
                var src = amzPieSource();
                if (!src) { box.innerHTML = '<p class="small text-muted mb-0">U7% mix is available for SP / SB / SD reports only.</p>'; return; }
                if (typeof Highcharts === 'undefined') { box.innerHTML = '<p class="small text-muted mb-0">—</p>'; return; }
                jQuery.ajax({
                    url: u7PieDistribUrl + encodeURIComponent(src),
                    type: 'POST',
                    data: amzPieFilterData(),
                    success: function (res) {
                        if (amzU7PieChart) { try { amzU7PieChart.destroy(); } catch (e) {} amzU7PieChart = null; }
                        if (!amzU7PieModalIsOpen()) return;
                        if (!res || !res.ok) { box.innerHTML = '<p class="small text-muted mb-0 px-1">No chart</p>'; return; }
                        box.innerHTML = '';
                        var b = res.buckets || {};
                        var seriesData = [];
                        if ((b.lt66 || 0) > 0) seriesData.push({ name: '< 66%', y: b.lt66, color: '#dc2626', bucket: 'lt66' });
                        if ((b['66_99'] || 0) > 0) seriesData.push({ name: '66–99%', y: b['66_99'], color: '#16a34a', bucket: '66_99' });
                        if ((b.gt99 || 0) > 0) seriesData.push({ name: '> 99%', y: b.gt99, color: '#db2777', bucket: 'gt99' });
                        if ((b.na || 0) > 0) seriesData.push({ name: 'N/A', y: b.na, color: '#9ca3af', bucket: 'na' });
                        if (!seriesData.length || (res.total || 0) < 1) { box.innerHTML = '<p class="small text-muted mb-0">No rows</p>'; return; }
                        amzU7PieChart = Highcharts.chart('amazonAdsU7Pie', {
                            chart: { type: 'pie', backgroundColor: 'transparent', height: 400, spacing: [12, 12, 12, 12] },
                            credits: { enabled: false }, exporting: { enabled: false }, title: { text: null },
                            tooltip: {
                                useHTML: true,
                                formatter: function () {
                                    return '<span style="color:' + this.point.color + '">\u25cf</span> <b>' + this.point.name + '</b><br/>'
                                        + 'Rows: <b>' + Math.round(this.point.y) + '</b> (' + Math.round(this.percentage) + '%)<br/><span style="font-size:11px;color:#6b7280">Click for 30-day history</span>';
                                }
                            },
                            plotOptions: {
                                pie: {
                                    allowPointSelect: true, cursor: 'pointer', size: '100%',
                                    borderWidth: 1, borderColor: 'rgba(255,255,255,0.85)',
                                    point: { events: { click: function () { if (this.options.bucket) amzOpenU7History(this.options.bucket, this.name); } } },
                                    dataLabels: {
                                        enabled: true, useHTML: true, distance: -120, allowOverlap: true, crop: false, overflow: 'allow',
                                        formatter: function () {
                                            var rp = Math.round(this.percentage);
                                            return '<span style="color:#fff;text-shadow:0 0 5px rgba(0,0,0,0.9);font-size:' + (rp < 4 ? '34px' : '46px') + ';font-weight:800">' + rp + '%</span>';
                                        }
                                    }
                                }
                            },
                            series: [{ type: 'pie', name: 'Rows', data: seriesData }]
                        });
                        setTimeout(function () { if (amzU7PieChart && amzU7PieChart.reflow) amzU7PieChart.reflow(); }, 50);
                    },
                    error: function () {
                        if (amzU7PieChart) { try { amzU7PieChart.destroy(); } catch (e) {} amzU7PieChart = null; }
                        if (amzU7PieModalIsOpen() && box) box.innerHTML = '<p class="small text-danger mb-0">Error</p>';
                    }
                });
            }
            function amzOpenU7History(bucketKey, sliceLabel) {
                var src = amzPieSource();
                if (!src) return;
                var modalEl = document.getElementById('amazonAdsU7HistoryModal');
                var titleEl = document.getElementById('amazonAdsU7HistoryModalLabel');
                var loadEl = document.getElementById('amazonAdsU7HistoryModalLoading');
                var errEl = document.getElementById('amazonAdsU7HistoryModalError');
                var tbl = document.getElementById('amazonAdsU7HistoryTable');
                var tbody = document.getElementById('amazonAdsU7HistoryTableBody');
                if (!modalEl || !tbody) return;
                if (titleEl) titleEl.textContent = 'U7% — ' + (sliceLabel || bucketKey) + ' — last 30 days';
                errEl.classList.add('d-none'); errEl.textContent = '';
                tbl.classList.add('d-none'); tbody.innerHTML = '';
                loadEl.classList.remove('d-none'); loadEl.textContent = 'Loading…';
                document.querySelectorAll('#amazonAdsU7HistoryTable thead [data-u7-bucket-col]').forEach(function (th) {
                    th.classList.toggle('table-secondary', th.getAttribute('data-u7-bucket-col') === bucketKey);
                });
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) bootstrap.Modal.getOrCreateInstance(modalEl).show();
                var data = amzPieFilterData();
                data.days = 30;
                data.bucket = bucketKey;
                jQuery.ajax({
                    url: u7PieHistoryUrl + encodeURIComponent(src),
                    type: 'POST',
                    data: data,
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
                            var td0 = document.createElement('td'); td0.textContent = row.date || ''; tr.appendChild(td0);
                            ['lt66', '66_99', 'gt99', 'na', 'total'].forEach(function (k) {
                                var td = document.createElement('td');
                                td.textContent = String(row[k] != null ? row[k] : '');
                                if (k === bucketKey) td.classList.add('fw-semibold');
                                tr.appendChild(td);
                            });
                            frag.appendChild(tr);
                        });
                        tbody.appendChild(frag);
                    },
                    error: function () { loadEl.classList.add('d-none'); errEl.textContent = 'Request failed.'; errEl.classList.remove('d-none'); }
                });
            }
            var u7PieModalEl = document.getElementById('amazonAdsU7PieModal');
            if (u7PieModalEl) {
                u7PieModalEl.addEventListener('shown.bs.modal', amzRefreshU7Pie);
                u7PieModalEl.addEventListener('hidden.bs.modal', function () {
                    if (amzU7PieChart) { try { amzU7PieChart.destroy(); } catch (e) {} amzU7PieChart = null; }
                    var box = document.getElementById('amazonAdsU7Pie'); if (box) box.innerHTML = '';
                });
            }

            // ---- BGT rule modal (ACOS bands -> SBGT) ----
            var amzCurrentBands = [];
            function amzRenderBands(bands) {
                var tbody = document.getElementById('amazonAdsBgtRuleBandsBody');
                if (!tbody) return;
                tbody.innerHTML = '';
                bands.forEach(function (band, i) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = ''
                        + '<td class="text-muted small">' + (i + 1) + '</td>'
                        + '<td><input type="text" class="form-control form-control-sm" value="' + String(band.label != null ? band.label : '').replace(/"/g, '&quot;') + '" data-idx="' + i + '" data-field="label"></td>'
                        + '<td><div class="d-flex align-items-center gap-2">'
                        + '<input type="color" class="form-control form-control-color form-control-sm" value="' + (band.color || '#6c757d') + '" data-idx="' + i + '" data-field="color">'
                        + '<span class="badge" style="background:' + (band.color || '#6c757d') + ';color:#fff;">' + (band.label || '—') + '</span></div></td>'
                        + '<td><input type="number" step="0.1" min="0" class="form-control form-control-sm" value="' + (band.acos_from != null ? band.acos_from : '') + '" data-idx="' + i + '" data-field="acos_from" placeholder="0"></td>'
                        + '<td><input type="number" step="0.1" min="0" class="form-control form-control-sm" value="' + (band.acos_to != null ? band.acos_to : '') + '" data-idx="' + i + '" data-field="acos_to" placeholder="9999"></td>'
                        + '<td><input type="number" step="1" min="1" class="form-control form-control-sm" value="' + (band.sbgt != null ? band.sbgt : '') + '" data-idx="' + i + '" data-field="sbgt"></td>'
                        + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-remove-idx="' + i + '" title="Remove band"><i class="fas fa-trash"></i></button></td>';
                    tbody.appendChild(tr);
                });
                tbody.querySelectorAll('input[data-idx]').forEach(function (inp) {
                    inp.addEventListener('input', function () {
                        var idx = +this.dataset.idx, fld = this.dataset.field;
                        if (!amzCurrentBands[idx]) return;
                        amzCurrentBands[idx][fld] = (fld === 'sbgt') ? (this.value === '' ? '' : parseInt(this.value, 10))
                            : (fld === 'acos_from' || fld === 'acos_to') ? (this.value === '' ? '' : parseFloat(this.value))
                            : this.value;
                        if (fld === 'label' || fld === 'color') {
                            var chip = this.closest('tr').querySelector('.badge');
                            var band = amzCurrentBands[idx];
                            if (chip) { chip.style.background = band.color || '#6c757d'; chip.textContent = band.label || '—'; }
                        }
                    });
                });
                tbody.querySelectorAll('[data-remove-idx]').forEach(function (btn) {
                    btn.addEventListener('click', function () { amzCurrentBands.splice(+this.dataset.removeIdx, 1); amzRenderBands(amzCurrentBands); });
                });
            }
            function amzLoadBandsFromRule(rule) {
                var bands = (rule && Array.isArray(rule.bands)) ? rule.bands : [];
                amzCurrentBands = bands.map(function (b) {
                    return { acos_from: Number(b.acos_from != null ? b.acos_from : 0), acos_to: Number(b.acos_to != null ? b.acos_to : 9999), sbgt: b.sbgt, label: b.label != null ? b.label : '', color: b.color || '#6c757d' };
                });
                amzRenderBands(amzCurrentBands);
            }
            function amzRefreshBgtRuleFromServer(cb) {
                fetch(bgtRuleGetUrl, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (body) { if (body && body.rule) window.amazonAdsBgtRule = body.rule; if (cb) cb(); })
                    .catch(function () { if (cb) cb(); });
            }
            var bgtModalEl = document.getElementById('amazonAdsBgtRuleModal');
            if (bgtModalEl) {
                bgtModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsBgtRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    amzRefreshBgtRuleFromServer(function () { amzLoadBandsFromRule(window.amazonAdsBgtRule || {}); });
                });
            }
            var bgtAddBtn = document.getElementById('amazonAdsBgtRuleAddBandBtn');
            if (bgtAddBtn) {
                bgtAddBtn.addEventListener('click', function () {
                    var lastTo = amzCurrentBands.length ? Number(amzCurrentBands[amzCurrentBands.length - 1].acos_to || 0) : 0;
                    amzCurrentBands.push({ acos_from: lastTo, acos_to: 9999, sbgt: 1, label: 'New band', color: '#6c757d' });
                    amzRenderBands(amzCurrentBands);
                });
            }
            var bgtSaveBtn = document.getElementById('amazonAdsBgtRuleSaveBtn');
            if (bgtSaveBtn) {
                bgtSaveBtn.addEventListener('click', function () {
                    var err = document.getElementById('amazonAdsBgtRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    var cleaned = (amzCurrentBands || []).map(function (b) {
                        return {
                            acos_from: (b.acos_from === '' || b.acos_from == null) ? NaN : parseFloat(b.acos_from),
                            acos_to: (b.acos_to === '' || b.acos_to == null) ? NaN : parseFloat(b.acos_to),
                            sbgt: (b.sbgt === '' || b.sbgt == null) ? NaN : parseInt(b.sbgt, 10),
                            label: (b.label || '').toString(), color: (b.color || '#6c757d').toString()
                        };
                    });
                    if (!cleaned.length) { if (err) { err.textContent = 'Add at least one band before saving.'; err.classList.remove('d-none'); } return; }
                    for (var i = 0; i < cleaned.length; i++) {
                        var b = cleaned[i];
                        if (!isFinite(b.acos_from) || !isFinite(b.acos_to) || !isFinite(b.sbgt)) { if (err) { err.textContent = 'Every band needs numeric From, To, and SBGT values.'; err.classList.remove('d-none'); } return; }
                        if (b.acos_from > b.acos_to) { if (err) { err.textContent = 'Each band needs From ≤ To.'; err.classList.remove('d-none'); } return; }
                    }
                    bgtSaveBtn.disabled = true;
                    fetch(bgtRuleSaveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ bands: cleaned })
                    })
                        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                        .then(function (out) {
                            var b = out.body || {};
                            if (!out.ok || b.status === 422 || b.status === 500) { if (err) { err.textContent = b.message || b.error || 'Save failed.'; err.classList.remove('d-none'); } return; }
                            window.amazonAdsBgtRule = b.rule || window.amazonAdsBgtRule;
                            if (typeof bootstrap !== 'undefined') { var inst = bootstrap.Modal.getInstance(bgtModalEl); if (inst) inst.hide(); }
                            amzUpdatePushButtons();
                            return Promise.resolve(table.setData());
                        })
                        .then(function () { amzRefreshUiSoon(); })
                        .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                        .finally(function () { bgtSaveBtn.disabled = false; });
                });
            }

            // ---- SBID rule modal ----
            var SBID_FIELDS = [
                ['amazonAdsSbidRuleUtilLow', 'util_low'],
                ['amazonAdsSbidRuleUtilHigh', 'util_high'],
                ['amazonAdsSbidRuleBothLowFallback', 'both_low_fallback'],
                ['amazonAdsSbidRuleLowMultL1', 'both_low_mult_l1'],
                ['amazonAdsSbidRuleLowMultL2', 'both_low_mult_l2'],
                ['amazonAdsSbidRuleLowMultL7', 'both_low_mult_l7'],
                ['amazonAdsSbidRuleHighMultL1', 'both_high_mult_l1']
            ];
            function amzFillSbidForm(rule) {
                if (!rule) return;
                SBID_FIELDS.forEach(function (pair) {
                    var el = document.getElementById(pair[0]);
                    if (el && rule[pair[1]] != null) el.value = String(rule[pair[1]]);
                });
            }
            function amzRefreshSbidRuleFromServer(cb) {
                fetch(sbidRuleGetUrl, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (body) { if (body && body.rule) window.amazonAdsSbidRule = body.rule; if (cb) cb(); })
                    .catch(function () { if (cb) cb(); });
            }
            var sbidModalEl = document.getElementById('amazonAdsSbidRuleModal');
            if (sbidModalEl) {
                sbidModalEl.addEventListener('show.bs.modal', function () {
                    var err = document.getElementById('amazonAdsSbidRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    amzRefreshSbidRuleFromServer(function () { amzFillSbidForm(window.amazonAdsSbidRule || {}); });
                });
            }
            var sbidSaveBtn = document.getElementById('amazonAdsSbidRuleSaveBtn');
            if (sbidSaveBtn) {
                sbidSaveBtn.addEventListener('click', function () {
                    var err = document.getElementById('amazonAdsSbidRuleModalError');
                    if (err) { err.classList.add('d-none'); err.textContent = ''; }
                    var payload = {};
                    var invalid = false;
                    SBID_FIELDS.forEach(function (pair) {
                        var el = document.getElementById(pair[0]);
                        var n = el ? parseFloat(String(el.value).trim()) : NaN;
                        if (!isFinite(n)) invalid = true;
                        payload[pair[1]] = n;
                    });
                    if (invalid) { if (err) { err.textContent = 'All SBID rule fields must be numeric.'; err.classList.remove('d-none'); } return; }
                    sbidSaveBtn.disabled = true;
                    fetch(sbidRuleSaveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    })
                        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
                        .then(function (out) {
                            var b = out.body || {};
                            if (!out.ok || b.status === 422 || b.status === 500) { if (err) { err.textContent = b.message || b.error || 'Save failed.'; err.classList.remove('d-none'); } return; }
                            window.amazonAdsSbidRule = b.rule || window.amazonAdsSbidRule;
                            if (typeof bootstrap !== 'undefined') { var inst = bootstrap.Modal.getInstance(sbidModalEl); if (inst) inst.hide(); }
                            return Promise.resolve(table.setData());
                        })
                        .then(function () { amzRefreshUiSoon(); })
                        .catch(function () { if (err) { err.textContent = 'Network or server error.'; err.classList.remove('d-none'); } })
                        .finally(function () { sbidSaveBtn.disabled = false; });
                });
            }

            // ---- Campaign link modal (KW / Neg "+" columns) ----
            (function () {
                var modalEl = document.getElementById('amzLinkModal');
                if (!modalEl || typeof bootstrap === 'undefined') return;
                var linkModal = new bootstrap.Modal(modalEl);
                var mode = 'kw';               // 'kw' | 'neg'
                var source = '';
                var selected = {};             // campaign name -> true
                var suggestTimer = null, reqId = 0;

                var inputEl = document.getElementById('amzLinkInput');
                var suggEl = document.getElementById('amzLinkSuggestions');
                var selWrap = document.getElementById('amzLinkSelectedWrap');
                var selEl = document.getElementById('amzLinkSelected');
                var selCountEl = document.getElementById('amzLinkSelectedCount');
                var saveBtn = document.getElementById('amzLinkSaveBtn');
                var titleEl = document.getElementById('amzLinkModalTitle');
                var hintEl = document.getElementById('amzLinkHint');
                var headerEl = document.getElementById('amzLinkModalHeader');
                var sourceEl = document.getElementById('amzLinkSource');

                function esc(s) {
                    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                    });
                }
                function escAttr(s) { return esc(s).replace(/'/g, '&#39;'); }
                function campaignsUrl() { return mode === 'neg' ? negLinkCampaignsUrl : kwLinkCampaignsUrl; }
                function saveUrl() { return mode === 'neg' ? negLinkSaveUrl : kwLinkSaveUrl; }

                function renderChips() {
                    var arr = Object.keys(selected);
                    selCountEl.textContent = String(arr.length);
                    if (!arr.length) { selWrap.classList.add('d-none'); selEl.innerHTML = ''; return; }
                    selWrap.classList.remove('d-none');
                    selEl.innerHTML = arr.map(function (c) {
                        return '<span class="badge bg-light text-dark border" style="font-weight:500;">' + esc(c) +
                            ' <a href="#" class="amz-link-chip-x text-danger" data-c="' + escAttr(c) + '" style="text-decoration:none;">&times;</a></span>';
                    }).join('');
                }

                function renderSuggestions(term) {
                    var q = String(term || '').trim();
                    if (q === '') { suggEl.classList.add('d-none'); suggEl.innerHTML = ''; return; }
                    var myReq = ++reqId;
                    var url = new URL(campaignsUrl(), window.location.origin);
                    url.searchParams.set('q', q);
                    url.searchParams.set('exclude', source);
                    fetch(url, { headers: { Accept: 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (json) {
                            if (myReq !== reqId) return;
                            var srcNorm = source.toUpperCase();
                            var list = (json.campaigns || []).map(function (c) { return String(c).trim(); })
                                .filter(function (c) { return c && c.toUpperCase() !== srcNorm; });
                            if (!list.length) { suggEl.classList.add('d-none'); suggEl.innerHTML = ''; return; }
                            suggEl.classList.remove('d-none');
                            suggEl.innerHTML = list.map(function (c) {
                                var checked = selected[c] ? 'checked' : '';
                                return '<div class="list-group-item list-group-item-action py-2 d-flex align-items-center gap-2 mb-0 amz-link-sugg" style="cursor:pointer;">' +
                                    '<input type="checkbox" class="form-check-input amz-link-cb" value="' + escAttr(c) + '" ' + checked + ' style="pointer-events:none;">' +
                                    '<span class="flex-grow-1">' + esc(c) + '</span></div>';
                            }).join('');
                        })
                        .catch(function () { if (myReq === reqId) { suggEl.classList.add('d-none'); suggEl.innerHTML = ''; } });
                }

                window.amzOpenLinkModal = function (m, campaign) {
                    mode = (m === 'neg') ? 'neg' : 'kw';
                    source = String(campaign || '');
                    selected = {};
                    var isNeg = mode === 'neg';
                    titleEl.textContent = isNeg ? 'Link Campaigns (Negatives)' : 'Link Campaigns (Keywords)';
                    hintEl.textContent = isNeg ? 'All linked campaigns will share negative keywords.' : 'All linked campaigns will share keywords.';
                    headerEl.className = 'modal-header text-white ' + (isNeg ? 'bg-danger' : 'bg-primary');
                    saveBtn.className = 'btn ' + (isNeg ? 'btn-danger' : 'btn-primary');
                    sourceEl.textContent = source;
                    inputEl.value = '';
                    suggEl.classList.add('d-none'); suggEl.innerHTML = '';
                    renderChips();
                    linkModal.show();
                    setTimeout(function () { inputEl.focus(); }, 300);
                };

                inputEl.addEventListener('input', function () {
                    if (suggestTimer) clearTimeout(suggestTimer);
                    var v = this.value;
                    suggestTimer = setTimeout(function () { renderSuggestions(v); }, 250);
                });
                suggEl.addEventListener('click', function (e) {
                    var item = e.target.closest('.amz-link-sugg'); if (!item) return;
                    var cb = item.querySelector('.amz-link-cb'); if (!cb) return;
                    cb.checked = !cb.checked;
                    if (cb.checked) selected[cb.value] = true; else delete selected[cb.value];
                    renderChips();
                });
                selEl.addEventListener('click', function (e) {
                    var x = e.target.closest('.amz-link-chip-x'); if (!x) return;
                    e.preventDefault();
                    delete selected[x.dataset.c];
                    suggEl.querySelectorAll('.amz-link-cb').forEach(function (cb) { if (cb.value === x.dataset.c) cb.checked = false; });
                    renderChips();
                });
                saveBtn.addEventListener('click', function () {
                    var targets = Object.keys(selected);
                    if (!targets.length) { alert('Select at least one campaign to link.'); return; }
                    saveBtn.disabled = true;
                    fetch(saveUrl(), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ campaigns: [source].concat(targets) })
                    })
                        .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
                        .then(function (out) {
                            if (!out.ok || !out.body.success) { throw new Error((out.body && out.body.message) || 'Link failed.'); }
                            linkModal.hide();
                            alert(out.body.message || 'Campaigns linked.');
                        })
                        .catch(function (err) { alert(err.message); })
                        .finally(function () { saveBtn.disabled = false; });
                });

                function amzRunRowPush(btn, url, campaign) {
                    var original = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ campaign: campaign })
                    })
                        .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
                        .then(function (out) {
                            alert((out.body && out.body.message) || (out.ok ? 'Done.' : 'Push failed.'));
                            if (out.body && out.body.added > 0 && table) { table.setData(); }
                        })
                        .catch(function (err) { alert(err.message); })
                        .finally(function () { btn.disabled = false; btn.innerHTML = original; });
                }

                // Delegate link / push clicks from the grid columns.
                var tableEl = document.getElementById('amz-ads-raw-table');
                if (tableEl) {
                    tableEl.addEventListener('click', function (e) {
                        var kw = e.target.closest('.amz-kwlink-btn');
                        if (kw) { e.stopPropagation(); if (kw.dataset.campaign) window.amzOpenLinkModal('kw', kw.dataset.campaign); return; }
                        var neg = e.target.closest('.amz-neglink-btn');
                        if (neg) { e.stopPropagation(); if (neg.dataset.campaign) window.amzOpenLinkModal('neg', neg.dataset.campaign); return; }
                        var kwp = e.target.closest('.amz-kwpush-btn');
                        if (kwp) { e.stopPropagation(); if (kwp.dataset.campaign) amzRunRowPush(kwp, kwPushUrl, kwp.dataset.campaign); return; }
                        var negp = e.target.closest('.amz-negpush-btn');
                        if (negp) { e.stopPropagation(); if (negp.dataset.campaign) amzRunRowPush(negp, negPushUrl, negp.dataset.campaign); }
                    });
                }

                // Bulk push buttons (toolbar).
                var kwBulk = document.getElementById('amz-kw-bulk-push');
                var negBulk = document.getElementById('amz-neg-bulk-push');
                function amzRunBulk(btn, url, label) {
                    if (!confirm('Push ' + label + ' into ALL linked campaigns now? This creates them on Amazon.')) return;
                    var original = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Pushing...';
                    fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({})
                    })
                        .then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); })
                        .then(function (out) { alert((out.body && out.body.message) || 'Done.'); if (table) table.setData(); })
                        .catch(function (err) { alert(err.message); })
                        .finally(function () { btn.disabled = false; btn.innerHTML = original; });
                }
                if (kwBulk) kwBulk.addEventListener('click', function () { amzRunBulk(this, kwPushAllUrl, 'keywords'); });
                if (negBulk) negBulk.addEventListener('click', function () { amzRunBulk(this, negPushAllUrl, 'negative keywords'); });
            })();

            // ---- initial state ----
            (function () {
                var params = new URLSearchParams(window.location.search);
                var deepSearch = params.get('search');
                if (deepSearch) {
                    var s = document.getElementById('amz-filter-search');
                    if (s) s.value = deepSearch;
                }
                var deepSource = params.get('source');
                if (deepSource && rawSources[deepSource]) {
                    var rt = document.getElementById('amazonAdsFilterReportType');
                    if (rt) rt.value = deepSource;
                    amzSwitchSource(deepSource);
                } else {
                    amzSetDatesToLatestForSource('sp_reports');
                }
                amzUpdatePushButtons();
                amzUpdatePieButton();
                amzUpdateSourceLabel();
            })();
        });
    </script>
@endsection
