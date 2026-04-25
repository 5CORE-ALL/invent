@extends('layouts.vertical', ['title' => 'Google Ads Campaigns (raw)', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #google-ads-campaigns-raw-wrap .tabulator {
            border: 1px solid #dee2e6; border-radius: 8px; font-size: 11px;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header {
            background: #f8f9fa; border-bottom: 1px solid #dee2e6;
        }
        #google-ads-campaigns-raw-wrap .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        /* Normal horizontal headers (not vertical / aliexpress-style) */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-content-holder,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-title-holder {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: normal !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed !important;
            transform: none !important;
            white-space: normal !important;
            height: auto !important;
            min-height: 0 !important;
            display: block;
            align-items: unset;
            justify-content: unset;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.25;
            padding: 5px 3px;
            text-align: center;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            height: auto !important;
            min-height: 34px;
            padding: 0;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col {
            height: auto !important;
            min-height: 34px;
            vertical-align: middle;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-row { min-height: 32px; }
        /* Tighter horizontal padding than Tabulator defaults */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-row .tabulator-cell {
            padding: 3px 4px !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col .tabulator-col-content-holder {
            padding-left: 2px !important;
            padding-right: 2px !important;
        }
        /* Sts: column was too narrow — header wrapped one letter per line; keep title on one row like other cols */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaign_status"] .tabulator-col-title {
            white-space: nowrap !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaign_status"] .tabulator-col-content-holder,
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-header .tabulator-col[tabulator-field="campaign_status"] .tabulator-col-title-holder {
            white-space: nowrap !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell .gac-raw-status-cell {
            white-space: nowrap;
        }
        /* ── Pagination footer — same rules as aliexpress_pricing_view (amazon_tabulator_view style) ── */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-footer .tabulator-paginator {
            display: flex; align-items: center; justify-content: center; gap: 4px; flex-wrap: wrap;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            font-size: 14px !important; font-weight: 500 !important;
            min-width: 36px !important; height: 36px !important; line-height: 36px !important;
            padding: 0 10px !important; border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important; background: #fff !important;
            color: #475569 !important; cursor: pointer; transition: all 0.15s ease !important;
            text-align: center !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #f1f5f9 !important; border-color: #cbd5e1 !important; color: #1e293b !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #4361ee !important; border-color: #4361ee !important;
            color: #fff !important; font-weight: 600 !important;
            box-shadow: 0 2px 6px rgba(67,97,238,0.3) !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-footer .tabulator-paginator .tabulator-page[disabled] {
            opacity: 0.4 !important; cursor: not-allowed !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-footer .tabulator-page-counter {
            margin: 0 0.5rem;
            font-size: 12px;
            color: #334155;
        }
        #google-ads-campaigns-raw-wrap { overflow-x: auto; overflow-y: visible; }
        /* UB% utilization colors — same as /google/shopping/utilized (7 UB% / 1 UB% formatters) */
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.green-bg {
            color: #05bd30 !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.pink-bg {
            color: #ff01d0 !important;
        }
        #google-ads-campaigns-raw-wrap .tabulator .tabulator-cell.red-bg {
            color: #ff2727 !important;
        }
        #gac-raw-filter-bar {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 14px;
        }
        #gac-raw-filter-bar .gac-raw-filter-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
            letter-spacing: 0.01em;
        }
        #gac-raw-filter-bar .gac-raw-filter-select {
            min-width: 132px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #64748b;
            font-size: 0.8125rem;
            padding-top: 0.35rem;
            padding-bottom: 0.35rem;
        }
        #gac-raw-filter-bar .gac-raw-pill-dark {
            display: inline-block;
            background: #0f172a;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            vertical-align: middle;
        }
        #gac-raw-filter-bar .gac-raw-pill-muted {
            display: inline-block;
            background: #94a3b8;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
            vertical-align: middle;
        }
        #gac-raw-filter-bar .gac-raw-summary-num {
            color: #64748b;
            font-weight: 600;
            font-size: 0.875rem;
        }
        #gac-raw-filter-bar .gac-raw-summary-acos {
            color: #2563eb;
            font-weight: 700;
            font-size: 0.875rem;
        }
        #google-ads-campaigns-raw-wrap #gacRawU7PieModal .gac-raw-u7-pie-modal-chart {
            width: 100%;
            min-height: 400px;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'google_ads_campaigns (raw)',
        'sub_title'  => '',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span id="gac-raw-total" class="badge bg-secondary">Total: —</span>
                        <span id="gac-raw-page-info" class="badge bg-light text-dark border">Page: —</span>
                        <button type="button" id="gac-raw-refresh" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                        <button type="button" id="gac-raw-export" class="btn btn-sm btn-success" title="Export current page as CSV">
                            <i class="fas fa-file-csv"></i> Export
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="gac-raw-sbgt-rule-btn" data-bs-toggle="modal" data-bs-target="#gacRawSbgtRuleModal" title="Edit ACOS band thresholds and SBGT tier values">SBGT RULE</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="gac-raw-sbid-rule-btn" data-bs-toggle="modal" data-bs-target="#gacRawSbidRuleModal" title="Edit 7UB/1UB% thresholds and CPC multipliers for suggested SBID">SBID RULE</button>
                        <span class="vr align-self-center d-none d-md-inline-block mx-1"></span>
                        <button type="button" class="btn btn-sm btn-warning text-dark" id="gac-raw-push-sbgt" title="Runs Artisan budget:update-shopping — sets Google Shopping daily budgets from the saved SBGT rule (same as cron)">
                            <i class="fa fa-cloud-upload-alt"></i> Push SBGT
                        </button>
                        <button type="button" class="btn btn-sm btn-warning text-dark" id="gac-raw-push-sbid" title="Runs Artisan sbid:update — pushes SBIDs for PARENT Shopping campaigns from the saved SBID rule (same as cron)">
                            <i class="fa fa-cloud-upload-alt"></i> Push SBID
                        </button>
                    </div>
                    <div id="gac-raw-filter-bar" class="mb-3">
                        <div class="d-flex flex-wrap align-items-end gap-3 gap-md-4">
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-ub7">U7%</label>
                                <select id="gac-filter-ub7" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by 7 UB% band">
                                    <option value="all" selected>All</option>
                                    <option value="green">66% – 99%</option>
                                    <option value="pink">&gt; 99%</option>
                                    <option value="red">&lt; 66%</option>
                                </select>
                            </div>
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-ub2">U2%</label>
                                <select id="gac-filter-ub2" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by 2 UB% band">
                                    <option value="all" selected>All</option>
                                    <option value="green">66% – 99%</option>
                                    <option value="pink">&gt; 99%</option>
                                    <option value="red">&lt; 66%</option>
                                </select>
                            </div>
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-ub1">U1%</label>
                                <select id="gac-filter-ub1" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by 1 UB% band">
                                    <option value="all" selected>All</option>
                                    <option value="green">66% – 99%</option>
                                    <option value="pink">&gt; 99%</option>
                                    <option value="red">&lt; 66%</option>
                                </select>
                            </div>
                            <div class="gac-raw-filter-field">
                                <label class="gac-raw-filter-label mb-0" for="gac-filter-stat">Sts</label>
                                <select id="gac-filter-stat" class="form-select form-select-sm gac-raw-filter-select" aria-label="Filter by campaign status">
                                    <option value="all" selected>All</option>
                                    <option value="ENABLED">Enabled</option>
                                    <option value="NOT_ENABLED">All except Enabled</option>
                                    <option value="PAUSED">Paused</option>
                                    <option value="REMOVED">Removed</option>
                                </select>
                            </div>
                            <div class="gac-raw-filter-field d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="gac-raw-u7-pie-open" data-bs-toggle="modal" data-bs-target="#gacRawU7PieModal" title="Row counts by U7% band (U7 filter ignored). Opens chart; click a slice for last 30 days.">U7% mix</button>
                            </div>
                            <span class="vr align-self-stretch d-none d-md-inline-block opacity-50"></span>
                            <div class="d-flex flex-wrap align-items-center gap-3 ms-md-auto">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="gac-raw-pill-dark">SPI30</span>
                                    <span id="gac-raw-summary-spi30-val" class="gac-raw-summary-num">—</span>
                                </div>
                                <span class="vr align-self-stretch d-none d-sm-inline-block opacity-50"></span>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="gac-raw-pill-muted">ACOS</span>
                                    <span id="gac-raw-summary-acos-val" class="gac-raw-summary-acos">—</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="gac-raw-push-result" class="alert alert-secondary small d-none mt-2 mb-0 py-2" role="status" aria-live="polite">
                        <div class="fw-semibold mb-1" id="gac-raw-push-result-title"></div>
                        <pre id="gac-raw-push-result-pre" class="mb-0 small bg-white border rounded p-2" style="white-space:pre-wrap;max-height:280px;overflow:auto;"></pre>
                    </div>
                    <div id="google-ads-campaigns-raw-wrap">
                        <div id="google-ads-campaigns-raw-table"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacRawU7PieModal" tabindex="-1" aria-labelledby="gacRawU7PieModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacRawU7PieModalLabel">U7% mix</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="small text-muted mb-2">Row counts by U7% band (U7 grid filter ignored). Click a slice for the last 30 days.</p>
                    <div id="gacRawU7Pie" class="gac-raw-u7-pie-modal-chart" role="img" aria-label="U7 percent distribution pie chart"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacRawU7HistoryModal" tabindex="-1" aria-labelledby="gacRawU7HistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacRawU7HistoryModalLabel">U7% — daily row counts</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-2">
                    <p class="small text-muted mb-2" id="gacRawU7HistoryModalSub">Last 30 calendar days. Same U2/U1/Sts filters as the grid; U7 filter ignored. Each day uses the 30-day window ending on that date.</p>
                    <div id="gacRawU7HistoryModalLoading" class="small text-muted">Loading…</div>
                    <p class="small text-danger mb-0 d-none" id="gacRawU7HistoryModalError" role="alert"></p>
                    <div class="table-responsive" style="max-height: 60vh;">
                        <table class="table table-sm table-striped mb-0 d-none" id="gacRawU7HistoryTable">
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
                            <tbody id="gacRawU7HistoryTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacRawSbgtRuleModal" tabindex="-1" aria-labelledby="gacRawSbgtRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacRawSbgtRuleModalLabel">SBGT rule — L30 ACOS % → tier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">Bands are evaluated <strong>top to bottom</strong>: first match wins (same logic as the grid). Require <code>ge_low &lt; ge_20 &lt; ge_30 &lt; ge_40 &lt; ge_50 &lt; gt</code>.</p>
                    <div class="row g-2 small">
                        <div class="col-md-4"><label class="form-label mb-0" for="gacSbgtGt">ACOS &gt; (%, exclusive)</label><input type="number" step="0.01" class="form-control form-control-sm" id="gacSbgtGt"></div>
                        <div class="col-md-4"><label class="form-label mb-0" for="gacSbgtValGt">SBGT value</label><input type="number" step="1" min="1" class="form-control form-control-sm" id="gacSbgtValGt"></div>
                        <div class="col-md-4"><label class="form-label mb-0 text-muted">Band</label><p class="mb-0 form-control-plaintext small">Above threshold</p></div>
                        <div class="col-md-4"><label class="form-label mb-0" for="gacSbgtGe50">ACOS ≥ (%)</label><input type="number" step="0.01" class="form-control form-control-sm" id="gacSbgtGe50"></div>
                        <div class="col-md-4"><label class="form-label mb-0" for="gacSbgtVal5099">SBGT value</label><input type="number" step="1" min="1" class="form-control form-control-sm" id="gacSbgtVal5099"></div>
                        <div class="col-md-4"><label class="form-label mb-0 text-muted">Band</label><p class="mb-0 form-control-plaintext small">Up to next threshold</p></div>
                        <div class="col-md-4"><label class="form-label mb-0" for="gacSbgtGe40">ACOS ≥ (%)</label><input type="number" step="0.01" class="form-control form-control-sm" id="gacSbgtGe40"></div>
                        <div class="col-md-4"><label class="form-label mb-0" for="gacSbgtVal4050">SBGT value</label><input type="number" step="1" min="1" class="form-control form-control-sm" id="gacSbgtVal4050"></div>
                        <div class="col-md-4"></div>
                        <div class="col-md-4"><label class="form-label mb-0" for="gacSbgtGe30">ACOS ≥ (%)</label><input type="number" step="0.01" class="form-control form-control-sm" id="gacSbgtGe30"></div>
                        <div class="col-md-4"><label class="form-label mb-0" for="gacSbgtVal3040">SBGT value</label><input type="number" step="1" min="1" class="form-control form-control-sm" id="gacSbgtVal3040"></div>
                        <div class="col-md-4"></div>
                        <div class="col-md-4"><label class="form-label mb-0" for="gacSbgtGe20">ACOS ≥ (%)</label><input type="number" step="0.01" class="form-control form-control-sm" id="gacSbgtGe20"></div>
                        <div class="col-md-4"><label class="form-label mb-0" for="gacSbgtVal2030">SBGT value</label><input type="number" step="1" min="1" class="form-control form-control-sm" id="gacSbgtVal2030"></div>
                        <div class="col-md-4"></div>
                        <div class="col-md-4"><label class="form-label mb-0" for="gacSbgtGeLow">ACOS ≥ (%)</label><input type="number" step="0.001" class="form-control form-control-sm" id="gacSbgtGeLow"></div>
                        <div class="col-md-4"><label class="form-label mb-0" for="gacSbgtValLow">SBGT value</label><input type="number" step="1" min="1" class="form-control form-control-sm" id="gacSbgtValLow"></div>
                        <div class="col-md-4"></div>
                        <div class="col-md-4"><label class="form-label mb-0" for="gacSbgtValElse">SBGT if below min ACOS</label><input type="number" step="1" min="1" class="form-control form-control-sm" id="gacSbgtValElse"></div>
                    </div>
                    <p class="small text-danger mb-0 mt-2 d-none" id="gacRawSbgtRuleErr" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="gacRawSbgtRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="gacRawSbidRuleModal" tabindex="-1" aria-labelledby="gacRawSbidRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="gacRawSbidRuleModalLabel">SBID rule — 7UB% / 1UB% → suggested bid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">When <strong>both</strong> 7UB% and 1UB% are <strong>above</strong> the high threshold, SBID = L1 CPC × over multiplier. When <strong>both</strong> are <strong>below</strong> the low threshold, SBID uses L1 / L7 CPC × under multipliers or fallback. Otherwise SBID shows —.</p>
                    <div class="row g-2 mb-2">
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidUtilLow">Low threshold (%)</label>
                            <input type="number" step="0.1" class="form-control form-control-sm" id="gacSbidUtilLow">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidUtilHigh">High threshold (%)</label>
                            <input type="number" step="0.1" class="form-control form-control-sm" id="gacSbidUtilHigh">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidUnderFallback">Fallback (no CPC)</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="gacSbidUnderFallback">
                        </div>
                    </div>
                    <p class="small fw-semibold mb-1">Both below low — CPC multipliers</p>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidUnderMultL1">× L1 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="gacSbidUnderMultL1">
                        </div>
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidUnderMultL7">× L7 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="gacSbidUnderMultL7">
                        </div>
                    </div>
                    <p class="small fw-semibold mb-1">Both above high</p>
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label small mb-0" for="gacSbidOverMultL1">× L1 CPC</label>
                            <input type="number" step="0.01" class="form-control form-control-sm" id="gacSbidOverMultL1">
                        </div>
                    </div>
                    <p class="small text-danger mb-0 mt-2 d-none" id="gacRawSbidRuleErr" role="alert"></p>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-primary" id="gacRawSbidRuleSaveBtn">Save &amp; refresh grid</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dataUrl = @json(route('google.ads.campaigns.raw.data'));
            const gacRawRuleGetUrl = @json(route('google.ads.campaigns.raw.rule'));
            const gacRawRuleSaveUrl = @json(route('google.ads.campaigns.raw.rule.save'));
            const gacRawPushSbgtUrl = @json(route('google.ads.campaigns.raw.push.sbgt'));
            const gacRawPushSbidUrl = @json(route('google.ads.campaigns.raw.push.sbid'));
            const gacRawU7PieDistribUrl = @json(route('google.ads.campaigns.raw.u7.distribution'));
            const gacRawU7PieHistoryUrl = @json(route('google.ads.campaigns.raw.u7.history'));
            window.gacRawRule = @json($gshoppingRawRule);
            let table;
            let gacRawU7PieChart = null;
            let gacRawU7PieRefreshTimer = null;
            const GAC_RAW_U7_PIE_MODAL_CHART_H = 400;

            function updatePageInfoBadge() {
                const el = document.getElementById('gac-raw-page-info');
                if (!el || !table) return;
                try {
                    const p = table.getPage();
                    const max = table.getPageMax();
                    el.textContent = 'Page: ' + p + ' / ' + max;
                } catch (e) {
                    el.textContent = 'Page: —';
                }
            }

            function gacRawFilterParamVal(id) {
                var el = document.getElementById(id);
                return (el && el.value) ? el.value : 'all';
            }

            /** Filtered row count for badge + Tabulator remote pagination (coerce strings; prefer server fields). */
            function gacRawFilteredRowCountFromResponse(response) {
                if (!response || typeof response !== 'object') {
                    return 0;
                }
                var n = Number(response.last_row);
                if (!Number.isFinite(n)) {
                    n = Number(response.total);
                }
                if (!Number.isFinite(n) && response.summary != null && response.summary.filtered_row_count != null) {
                    n = Number(response.summary.filtered_row_count);
                }
                if (!Number.isFinite(n) || n < 0) {
                    return 0;
                }
                return Math.floor(n);
            }

            function gacRawSummaryFromResponse(response) {
                var spiEl = document.getElementById('gac-raw-summary-spi30-val');
                var acosEl = document.getElementById('gac-raw-summary-acos-val');
                if (!response || typeof response !== 'object' || !response.summary) {
                    if (spiEl) spiEl.textContent = '—';
                    if (acosEl) acosEl.textContent = '—';
                    return;
                }
                var s = response.summary;
                if (spiEl) {
                    if (s.spi30 !== null && s.spi30 !== undefined && !isNaN(Number(s.spi30))) {
                        spiEl.textContent = Number(s.spi30).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    } else {
                        spiEl.textContent = '—';
                    }
                }
                if (acosEl) {
                    if (s.acos_pct !== null && s.acos_pct !== undefined && !isNaN(Number(s.acos_pct))) {
                        acosEl.textContent = String(Math.round(Number(s.acos_pct))) + '%';
                    } else {
                        acosEl.textContent = '—';
                    }
                }
            }

            function gacRawCurrentFilterParams() {
                return {
                    filter_ub7: gacRawFilterParamVal('gac-filter-ub7'),
                    filter_ub2: gacRawFilterParamVal('gac-filter-ub2'),
                    filter_ub1: gacRawFilterParamVal('gac-filter-ub1'),
                    filter_stat: gacRawFilterParamVal('gac-filter-stat'),
                };
            }

            function gacRawReloadGridForFilters() {
                if (!table) return;
                try {
                    table.setPage(1);
                } catch (e) { /* ignore */ }
                table.setData(dataUrl);
                gacRawRefreshU7PieChartDebounced();
            }

            function gacRawPieFilterPayload() {
                var p = gacRawCurrentFilterParams();
                return {
                    filter_ub2: p.filter_ub2,
                    filter_ub1: p.filter_ub1,
                    filter_stat: p.filter_stat
                };
            }

            function gacRawU7PieModalIsOpen() {
                var m = document.getElementById('gacRawU7PieModal');
                return !!(m && m.classList.contains('show'));
            }

            function gacRawOpenU7HistoryModal(bucketKey, sliceLabel) {
                var modalEl = document.getElementById('gacRawU7HistoryModal');
                var titleEl = document.getElementById('gacRawU7HistoryModalLabel');
                var subEl = document.getElementById('gacRawU7HistoryModalSub');
                var loadEl = document.getElementById('gacRawU7HistoryModalLoading');
                var errEl = document.getElementById('gacRawU7HistoryModalError');
                var tbl = document.getElementById('gacRawU7HistoryTable');
                var tbody = document.getElementById('gacRawU7HistoryTableBody');
                if (!modalEl || !tbody) {
                    return;
                }
                if (titleEl) {
                    titleEl.textContent = 'U7% — ' + (sliceLabel || bucketKey) + ' — last 30 days';
                }
                if (subEl) {
                    subEl.textContent = 'Daily row counts for the selected band. Same U2/U1/Sts filters as the grid; U7 filter ignored. Each day uses the 30-day window ending on that date.';
                }
                errEl.classList.add('d-none');
                errEl.textContent = '';
                tbl.classList.add('d-none');
                tbody.innerHTML = '';
                loadEl.classList.remove('d-none');
                loadEl.textContent = 'Loading…';
                document.querySelectorAll('#gacRawU7HistoryTable thead [data-u7-bucket-col]').forEach(function(th) {
                    th.classList.remove('table-secondary');
                    if (th.getAttribute('data-u7-bucket-col') === bucketKey) {
                        th.classList.add('table-secondary');
                    }
                });
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                }
                var p = gacRawPieFilterPayload();
                var tok = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                jQuery.ajax({
                    url: gacRawU7PieHistoryUrl,
                    type: 'POST',
                    data: {
                        _token: tok,
                        days: 30,
                        bucket: bucketKey,
                        filter_ub2: p.filter_ub2,
                        filter_ub1: p.filter_ub1,
                        filter_stat: p.filter_stat
                    },
                    success: function(res) {
                        loadEl.classList.add('d-none');
                        if (!res || !res.ok || !res.days || !res.days.length) {
                            errEl.textContent = (res && res.reason) ? ('Could not load history (' + res.reason + ').') : 'No history data.';
                            errEl.classList.remove('d-none');
                            return;
                        }
                        tbl.classList.remove('d-none');
                        var frag = document.createDocumentFragment();
                        res.days.forEach(function(row) {
                            var tr = document.createElement('tr');
                            var td0 = document.createElement('td');
                            td0.textContent = row.date || '';
                            tr.appendChild(td0);
                            ['lt66', '66_99', 'gt99', 'na', 'total'].forEach(function(k) {
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
                    error: function() {
                        loadEl.classList.add('d-none');
                        errEl.textContent = 'Request failed.';
                        errEl.classList.remove('d-none');
                    }
                });
            }

            function gacRawRefreshU7PieChartDebounced() {
                if (gacRawU7PieRefreshTimer) {
                    clearTimeout(gacRawU7PieRefreshTimer);
                }
                gacRawU7PieRefreshTimer = setTimeout(function() {
                    if (gacRawU7PieModalIsOpen()) {
                        gacRawRefreshU7PieChart();
                    }
                }, 280);
            }

            function gacRawU7PieDataLabelFormatter() {
                var rp = Math.round(this.percentage);
                var fs = rp < 4 ? '34px' : '46px';
                return '<span style="color:#fff;text-shadow:0 0 5px rgba(0,0,0,0.9);font-size:' + fs + ';font-weight:800">' + rp + '%</span>';
            }

            function gacRawRefreshU7PieChart() {
                var box = document.getElementById('gacRawU7Pie');
                if (!box) {
                    return;
                }
                if (!gacRawU7PieModalIsOpen()) {
                    return;
                }
                if (typeof Highcharts === 'undefined') {
                    box.innerHTML = '<p class="small text-muted mb-0">—</p>';
                    return;
                }
                var p = gacRawPieFilterPayload();
                var tok = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                jQuery.ajax({
                    url: gacRawU7PieDistribUrl,
                    type: 'POST',
                    data: {
                        _token: tok,
                        filter_ub2: p.filter_ub2,
                        filter_ub1: p.filter_ub1,
                        filter_stat: p.filter_stat
                    },
                    success: function(res) {
                        if (gacRawU7PieChart) {
                            try {
                                gacRawU7PieChart.destroy();
                            } catch (e0) {}
                            gacRawU7PieChart = null;
                        }
                        if (!gacRawU7PieModalIsOpen()) {
                            return;
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
                        if (!gacRawU7PieModalIsOpen()) {
                            return;
                        }
                        gacRawU7PieChart = Highcharts.chart('gacRawU7Pie', {
                            chart: { type: 'pie', backgroundColor: 'transparent', height: GAC_RAW_U7_PIE_MODAL_CHART_H, spacing: [12, 12, 12, 12] },
                            credits: { enabled: false },
                            exporting: { enabled: false },
                            title: { text: null },
                            tooltip: {
                                useHTML: true,
                                outside: false,
                                formatter: function() {
                                    var rn = Math.round(this.point.y);
                                    var rp = Math.round(this.percentage);
                                    return '<span style="color:' + this.point.color + '">\u25cf</span> <b>' + this.point.name + '</b><br/>'
                                        + 'Rows: <b>' + rn + '</b> (' + rp + '%)<br/><span style="font-size:11px;color:#6b7280">Click for 30-day history</span>';
                                }
                            },
                            plotOptions: {
                                pie: {
                                    allowPointSelect: true,
                                    cursor: 'pointer',
                                    size: '100%',
                                    borderWidth: 1,
                                    borderColor: 'rgba(255,255,255,0.85)',
                                    states: {
                                        hover: {
                                            brightness: 0.08,
                                            halo: { size: 0 }
                                        }
                                    },
                                    point: {
                                        events: {
                                            click: function() {
                                                var bk = this.options.bucket;
                                                if (bk) {
                                                    gacRawOpenU7HistoryModal(bk, this.name);
                                                }
                                            }
                                        }
                                    },
                                    dataLabels: {
                                        enabled: true,
                                        useHTML: true,
                                        distance: -120,
                                        connectorWidth: 0,
                                        allowOverlap: true,
                                        crop: false,
                                        overflow: 'allow',
                                        style: {
                                            fontSize: '38px',
                                            fontWeight: '700',
                                            textOutline: 'none'
                                        },
                                        formatter: gacRawU7PieDataLabelFormatter
                                    }
                                }
                            },
                            series: [{ type: 'pie', name: 'Rows', data: seriesData }]
                        });
                        setTimeout(function() {
                            if (gacRawU7PieChart && typeof gacRawU7PieChart.reflow === 'function') {
                                gacRawU7PieChart.reflow();
                            }
                        }, 50);
                    },
                    error: function() {
                        if (gacRawU7PieChart) {
                            try {
                                gacRawU7PieChart.destroy();
                            } catch (e1) {}
                            gacRawU7PieChart = null;
                        }
                        if (gacRawU7PieModalIsOpen() && box) {
                            box.innerHTML = '<p class="small text-danger mb-0">Error</p>';
                        }
                    }
                });
            }

            var u7PieModalEl = document.getElementById('gacRawU7PieModal');
            if (u7PieModalEl) {
                u7PieModalEl.addEventListener('shown.bs.modal', function() {
                    gacRawRefreshU7PieChart();
                });
                u7PieModalEl.addEventListener('hidden.bs.modal', function() {
                    if (gacRawU7PieChart) {
                        try {
                            gacRawU7PieChart.destroy();
                        } catch (e2) {}
                        gacRawU7PieChart = null;
                    }
                    var pieBox = document.getElementById('gacRawU7Pie');
                    if (pieBox) {
                        pieBox.innerHTML = '';
                    }
                });
            }

            table = new Tabulator('#google-ads-campaigns-raw-table', {
                ajaxURL: dataUrl,
                ajaxConfig: { method: 'GET', credentials: 'same-origin' },
                ajaxParams: function() {
                    return gacRawCurrentFilterParams();
                },
                // Table width = sum of columns sized from header + cell text (see Tabulator layout docs)
                layout: 'fitDataTable',
                layoutColumnsOnNewData: true,
                pagination: true,
                paginationMode: 'remote',
                paginationSize: 100,
                paginationSizeSelector: [50, 100, 200, 500, 1000],
                paginationCounter: 'rows',
                paginationButtonCount: 12,
                paginationInitialPage: 1,
                placeholder: 'No rows in google_ads_campaigns.',
                selectable: true,
                autoColumns: true,
                autoColumnsDefinitions: function(defs) {
                    if (!defs.some(function(d) { return d.field === '__gac_select'; })) {
                        defs.unshift({
                            title: '',
                            field: '__gac_select',
                            formatter: 'rowSelection',
                            titleFormatter: 'rowSelection',
                            headerSort: false,
                            hozAlign: 'center',
                            headerHozAlign: 'center',
                            width: 40,
                            minWidth: 40,
                        });
                    }
                    var moneySpendTitles = {
                        spend: 'Spend',
                        l7_spend: 'L7 Spend',
                        l2_spend: 'L2 Spend',
                        l1_spend: 'L1 Spend',
                    };
                    var utilizedStyleTitles = {
                        cpc_L30: 'CPC',
                        cpc_L7: 'L7 CPC',
                        cpc_L2: 'L2 CPC',
                        cpc_L1: 'L1 CPC',
                        ad_sales_L30: 'L30 Sales',
                        acos_l30: 'ACOS L30',
                        ub7: '7 UB%',
                        ub2: '2 UB%',
                        ub1: '1 UB%',
                        bgt: 'BGT',
                        sbgt: 'SBGT',
                        sbid: 'SBID',
                    };
                    var moneyFormatter = function(c) {
                        var v = parseFloat(c.getValue());
                        if (!isFinite(v)) return '';
                        return v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    };
                    var intLocaleFormatter = function(c) {
                        var v = c.getValue();
                        if (v === null || v === undefined || v === '') return '';
                        var n = parseInt(v, 10);
                        if (!isFinite(n)) return String(v);
                        return n.toLocaleString();
                    };
                    var pct0Formatter = function(c) {
                        var v = parseFloat(c.getValue());
                        if (!isFinite(v)) return '';
                        return Math.round(v) + '%';
                    };
                    /** Same bands as google-shopping-utilized 7 UB% / 1 UB%: green 66–99%, pink &gt;99%, red &lt;66%. */
                    var ubUtilColorFormatter = function(c) {
                        var v = parseFloat(c.getValue());
                        if (!isFinite(v)) v = 0;
                        var td = c.getElement();
                        if (td) {
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (v >= 66 && v <= 99) {
                                td.classList.add('green-bg');
                            } else if (v > 99) {
                                td.classList.add('pink-bg');
                            } else if (v < 66) {
                                td.classList.add('red-bg');
                            }
                        }
                        return Math.round(v) + '%';
                    };
                    var acosFormatter = function(c) {
                        var v = parseFloat(c.getValue());
                        if (!isFinite(v)) return '';
                        return Math.round(v) + '%';
                    };
                    var sbidFormatter = function(c) {
                        var v = c.getValue();
                        if (v === null || v === undefined || v === '') return '—';
                        var n = parseFloat(v);
                        if (!isFinite(n)) return '—';
                        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    };
                    var campaignStatusFormatter = function(c) {
                        var v = c.getValue();
                        var s = v === null || v === undefined ? '' : String(v).trim();
                        if (s === '') {
                            return '<span class="gac-raw-status-cell text-muted" title="—" aria-label="No status">—</span>';
                        }
                        var u = s.toUpperCase();
                        var dotColor = '#eab308';
                        if (u === 'ENABLED') {
                            dotColor = '#22c55e';
                        } else if (u === 'PAUSED') {
                            dotColor = '#fb923c';
                        } else if (u === 'REMOVED') {
                            dotColor = '#64748b';
                        } else if (u === 'UNKNOWN' || u === 'UNSPECIFIED') {
                            dotColor = '#a855f7';
                        } else if (u === 'SUSPENDED') {
                            dotColor = '#f43f5e';
                        }
                        var tipAttr = s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/'/g, '&#39;');
                        var dot = '<span aria-hidden="true" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' + dotColor + ';"></span>';
                        return '<span class="gac-raw-status-cell" title="' + tipAttr + '" aria-label="' + tipAttr + '" style="display:inline-flex;align-items:center;justify-content:center;">' + dot + '</span>';
                    };
                    defs.forEach(function(col) {
                        if (col.field === '__gac_select') {
                            col.headerSort = false;
                            col.hozAlign = 'center';
                            col.headerHozAlign = 'center';
                            col.minWidth = 40;
                            col.width = 40;
                            return;
                        }
                        // Avoid a uniform minWidth on every column — Tabulator fits width to data unless width/minWidth is set
                        col.headerSort = false;
                        col.hozAlign = 'center';
                        col.headerHozAlign = 'center';
                        if (col.field === 'campaign_name') {
                            col.minWidth = 141;
                        } else if (col.field === 'campaign_status') {
                            col.minWidth = 44;
                            col.width = 44;
                            col.title = 'Sts';
                            col.formatter = campaignStatusFormatter;
                        } else {
                            col.minWidth = 50;
                        }
                        if (col.field === 'id' || col.field === 'campaign_id' || col.field === 'date') {
                            col.visible = false;
                        }
                        if (Object.prototype.hasOwnProperty.call(moneySpendTitles, col.field)) {
                            col.title = moneySpendTitles[col.field];
                            col.formatter = moneyFormatter;
                            col.minWidth = Math.max(col.minWidth || 0, 70);
                        }
                        if (Object.prototype.hasOwnProperty.call(utilizedStyleTitles, col.field)) {
                            col.title = utilizedStyleTitles[col.field];
                            if (col.field === 'ad_sales_L30') {
                                col.formatter = moneyFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 77);
                            } else if (col.field === 'acos_l30') {
                                col.formatter = acosFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 64);
                            } else if (col.field === 'ub7' || col.field === 'ub2' || col.field === 'ub1') {
                                col.formatter = ubUtilColorFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 57);
                            } else if (col.field === 'sbgt') {
                                col.formatter = intLocaleFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 57);
                            } else if (col.field === 'sbid') {
                                col.formatter = sbidFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 70);
                            } else {
                                col.formatter = moneyFormatter;
                                col.minWidth = Math.max(col.minWidth || 0, 70);
                            }
                        }
                        if (col.field === 'metrics_clicks') {
                            col.title = 'Click';
                            col.formatter = intLocaleFormatter;
                            col.minWidth = Math.max(col.minWidth || 0, 57);
                        }
                    });
                    return defs;
                },
                ajaxResponse: function(url, params, response) {
                    if (!response || typeof response !== 'object') {
                        var te0 = document.getElementById('gac-raw-total');
                        if (te0) {
                            te0.textContent = 'Total rows: —';
                        }
                        return { last_page: 1, last_row: 0, data: [] };
                    }
                    const lastPage = Math.max(1, parseInt(response.last_page, 10) || 1);
                    const lastRow = gacRawFilteredRowCountFromResponse(response);
                    const rows = Array.isArray(response.data) ? response.data : [];

                    const totalEl = document.getElementById('gac-raw-total');
                    if (totalEl) {
                        totalEl.textContent = 'Total rows: ' + lastRow.toLocaleString();
                    }

                    gacRawSummaryFromResponse(response);

                    return {
                        last_page: lastPage,
                        last_row: lastRow,
                        data: rows,
                    };
                },
            });

            ['gac-filter-ub7', 'gac-filter-ub2', 'gac-filter-ub1', 'gac-filter-stat'].forEach(function(fid) {
                var fel = document.getElementById(fid);
                if (fel) {
                    fel.addEventListener('change', gacRawReloadGridForFilters);
                }
            });

            table.on('pageLoaded', updatePageInfoBadge);
            table.on('dataLoaded', function() {
                updatePageInfoBadge();
                // Re-measure after formatters (e.g. toLocaleString) change cell text width
                requestAnimationFrame(function() {
                    try {
                        if (table && typeof table.redraw === 'function') {
                            table.redraw(true);
                        }
                    } catch (e) { /* ignore */ }
                });
            });

            table.on('dataLoadError', function(error) {
                console.error('google_ads_campaigns raw data load error', error);
                const totalEl = document.getElementById('gac-raw-total');
                if (totalEl) {
                    totalEl.textContent = 'Load error (see console)';
                }
            });

            document.getElementById('gac-raw-refresh').addEventListener('click', function() {
                Promise.resolve(table.setData(dataUrl)).finally(updatePageInfoBadge);
            });

            document.getElementById('gac-raw-export').addEventListener('click', function() {
                table.download('csv', 'google_ads_campaigns_page.csv');
            });

            function gacShowPushResult(title, body, variant) {
                var wrap = document.getElementById('gac-raw-push-result');
                var tEl = document.getElementById('gac-raw-push-result-title');
                var pre = document.getElementById('gac-raw-push-result-pre');
                if (!wrap || !tEl || !pre) return;
                wrap.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-secondary', 'alert-info');
                if (variant === 'error') {
                    wrap.classList.add('alert-danger');
                } else {
                    wrap.classList.add('alert-success');
                }
                tEl.textContent = title;
                pre.textContent = body || '(no console output)';
                wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            function gacShowPushLoading(title, detail) {
                var wrap = document.getElementById('gac-raw-push-result');
                var tEl = document.getElementById('gac-raw-push-result-title');
                var pre = document.getElementById('gac-raw-push-result-pre');
                if (!wrap || !tEl || !pre) return;
                wrap.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-secondary', 'alert-info');
                wrap.classList.add('alert-info');
                tEl.innerHTML = '<i class="fa fa-spinner fa-spin me-1" aria-hidden="true"></i>' + (title || 'Working…');
                pre.textContent = detail || 'Running on the server. This can take several minutes — please keep this tab open.';
                wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            function gacPushTargetCampaignIds() {
                if (!table) return [];
                var selected = table.getSelectedData();
                var rows = (selected && selected.length > 0) ? selected : table.getData();
                var seen = {};
                var ids = [];
                rows.forEach(function(row) {
                    if (!row) return;
                    var cid = row.campaign_id;
                    if (cid === null || cid === undefined || cid === '') return;
                    var s = String(cid).replace(/\D/g, '');
                    if (!s) s = String(cid).trim();
                    if (s && !seen[s]) {
                        seen[s] = true;
                        ids.push(s);
                    }
                });
                return ids;
            }

            function gacRunArtisanPush(opts) {
                var campaignIds = opts.campaign_ids || [];
                if (!campaignIds.length) {
                    window.alert('No campaigns to push: this page has no rows with a campaign_id. Load data or switch page.');
                    return;
                }
                if (!window.confirm(opts.confirmMsg)) {
                    return;
                }
                var sbgtB = document.getElementById('gac-raw-push-sbgt');
                var sbidB = document.getElementById('gac-raw-push-sbid');
                if (sbgtB) sbgtB.disabled = true;
                if (sbidB) sbidB.disabled = true;
                var origHtml = opts.btn.innerHTML;
                opts.btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Pushing…';

                gacShowPushLoading(opts.loadingTitle, opts.loadingDetail);

                var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                fetch(opts.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ campaign_ids: campaignIds }),
                })
                    .then(function(res) {
                        return res.json().then(function(body) {
                            return { ok: res.ok, status: res.status, body: body };
                        });
                    })
                    .then(function(out) {
                        var b = out.body || {};
                        var cmd = b.command || 'command';
                        var title = cmd + ' — ' + (b.ok ? 'finished' : 'failed');
                        if (b.exit_code != null) {
                            title += ' (exit ' + b.exit_code + ')';
                        }
                        var text = (b.message ? b.message + '\n\n' : '') + (b.output || '');
                        gacShowPushResult(title, text, b.ok ? 'success' : 'error');
                        if (b.ok && table) {
                            Promise.resolve(table.setData(dataUrl)).finally(updatePageInfoBadge);
                        }
                    })
                    .catch(function(err) {
                        gacShowPushResult('Request failed', String(err && err.message ? err.message : err), 'error');
                    })
                    .finally(function() {
                        opts.btn.innerHTML = origHtml;
                        if (sbgtB) sbgtB.disabled = false;
                        if (sbidB) sbidB.disabled = false;
                    });
            }

            var pushSbgtBtn = document.getElementById('gac-raw-push-sbgt');
            if (pushSbgtBtn) {
                pushSbgtBtn.addEventListener('click', function() {
                    var ids = gacPushTargetCampaignIds();
                    var nSel = table && table.getSelectedData ? table.getSelectedData().length : 0;
                    var scope = nSel > 0
                        ? ('the ' + ids.length + ' checked row(s)')
                        : ('all ' + ids.length + ' row(s) on this page');
                    gacRunArtisanPush({
                        url: gacRawPushSbgtUrl,
                        btn: pushSbgtBtn,
                        campaign_ids: ids,
                        confirmMsg: 'Run budget:update-shopping for ' + scope + '? Only matching SHOPPING PARENT campaigns in Google Ads are updated (daily budget from the saved SBGT rule).',
                        loadingTitle: 'Pushing SBGT (budget:update-shopping)…',
                        loadingDetail: 'Updating budgets for ' + ids.length + ' campaign id(s). This can take several minutes — please keep this tab open.',
                    });
                });
            }
            var pushSbidBtn = document.getElementById('gac-raw-push-sbid');
            if (pushSbidBtn) {
                pushSbidBtn.addEventListener('click', function() {
                    var ids = gacPushTargetCampaignIds();
                    var nSel = table && table.getSelectedData ? table.getSelectedData().length : 0;
                    var scope = nSel > 0
                        ? ('the ' + ids.length + ' checked row(s)')
                        : ('all ' + ids.length + ' row(s) on this page');
                    gacRunArtisanPush({
                        url: gacRawPushSbidUrl,
                        btn: pushSbidBtn,
                        campaign_ids: ids,
                        confirmMsg: 'Run sbid:update for ' + scope + '? Only matching SHOPPING PARENT campaigns in Google Ads are updated (SBID from the saved rule).',
                        loadingTitle: 'Pushing SBID (sbid:update)…',
                        loadingDetail: 'Updating SBIDs for ' + ids.length + ' campaign id(s). This can take several minutes — please keep this tab open.',
                    });
                });
            }

            function gacNum(id) {
                var el = document.getElementById(id);
                if (!el) return NaN;
                return parseFloat(String(el.value).trim());
            }
            function gacInt(id) {
                var el = document.getElementById(id);
                if (!el) return NaN;
                return parseInt(String(el.value).trim(), 10);
            }
            function gacSetVal(id, v) {
                var el = document.getElementById(id);
                if (el && v != null && v !== '') el.value = String(v);
            }
            function gacFillSbgtForm(sbgt) {
                if (!sbgt) return;
                gacSetVal('gacSbgtGt', sbgt.gt);
                gacSetVal('gacSbgtValGt', sbgt.val_gt);
                gacSetVal('gacSbgtGe50', sbgt.ge_50);
                gacSetVal('gacSbgtVal5099', sbgt.val_50_99);
                gacSetVal('gacSbgtGe40', sbgt.ge_40);
                gacSetVal('gacSbgtVal4050', sbgt.val_40_50);
                gacSetVal('gacSbgtGe30', sbgt.ge_30);
                gacSetVal('gacSbgtVal3040', sbgt.val_30_40);
                gacSetVal('gacSbgtGe20', sbgt.ge_20);
                gacSetVal('gacSbgtVal2030', sbgt.val_20_30);
                gacSetVal('gacSbgtGeLow', sbgt.ge_low);
                gacSetVal('gacSbgtValLow', sbgt.val_low);
                gacSetVal('gacSbgtValElse', sbgt.val_else);
            }
            function gacCollectSbgt() {
                return {
                    gt: gacNum('gacSbgtGt'),
                    val_gt: gacInt('gacSbgtValGt'),
                    ge_50: gacNum('gacSbgtGe50'),
                    val_50_99: gacInt('gacSbgtVal5099'),
                    ge_40: gacNum('gacSbgtGe40'),
                    val_40_50: gacInt('gacSbgtVal4050'),
                    ge_30: gacNum('gacSbgtGe30'),
                    val_30_40: gacInt('gacSbgtVal3040'),
                    ge_20: gacNum('gacSbgtGe20'),
                    val_20_30: gacInt('gacSbgtVal2030'),
                    ge_low: gacNum('gacSbgtGeLow'),
                    val_low: gacInt('gacSbgtValLow'),
                    val_else: gacInt('gacSbgtValElse'),
                };
            }
            function gacFillSbidForm(sbid) {
                if (!sbid) return;
                gacSetVal('gacSbidUtilLow', sbid.util_low);
                gacSetVal('gacSbidUtilHigh', sbid.util_high);
                gacSetVal('gacSbidOverMultL1', sbid.over_mult_l1);
                gacSetVal('gacSbidUnderMultL1', sbid.under_mult_l1);
                gacSetVal('gacSbidUnderMultL7', sbid.under_mult_l7);
                gacSetVal('gacSbidUnderFallback', sbid.under_fallback);
            }
            function gacCollectSbid() {
                return {
                    util_low: gacNum('gacSbidUtilLow'),
                    util_high: gacNum('gacSbidUtilHigh'),
                    over_mult_l1: gacNum('gacSbidOverMultL1'),
                    under_mult_l1: gacNum('gacSbidUnderMultL1'),
                    under_mult_l7: gacNum('gacSbidUnderMultL7'),
                    under_fallback: gacNum('gacSbidUnderFallback'),
                };
            }
            function gacRefreshRuleFromServer(cb) {
                fetch(gacRawRuleGetUrl, {
                    method: 'GET',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                    .then(function(out) {
                        if (out.ok && out.body && out.body.rule) {
                            window.gacRawRule = out.body.rule;
                        }
                        if (typeof cb === 'function') cb();
                    })
                    .catch(function() { if (typeof cb === 'function') cb(); });
            }

            var sbgtModalEl = document.getElementById('gacRawSbgtRuleModal');
            if (sbgtModalEl) {
                sbgtModalEl.addEventListener('show.bs.modal', function() {
                    var errEl = document.getElementById('gacRawSbgtRuleErr');
                    if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                    gacRefreshRuleFromServer(function() {
                        gacFillSbgtForm((window.gacRawRule && window.gacRawRule.sbgt) || {});
                    });
                });
            }
            var sbgtSaveBtn = document.getElementById('gacRawSbgtRuleSaveBtn');
            if (sbgtSaveBtn) {
                sbgtSaveBtn.addEventListener('click', function() {
                    var errEl = document.getElementById('gacRawSbgtRuleErr');
                    if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                    var sbidKeep = (window.gacRawRule && window.gacRawRule.sbid) ? window.gacRawRule.sbid : {};
                    var payload = { sbgt: gacCollectSbgt(), sbid: sbidKeep };
                    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                    sbgtSaveBtn.disabled = true;
                    fetch(gacRawRuleSaveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    })
                        .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                        .then(function(out) {
                            var b = out.body || {};
                            if (!out.ok) {
                                if (errEl) {
                                    errEl.textContent = b.message || b.error || 'Save failed.';
                                    errEl.classList.remove('d-none');
                                }
                                return;
                            }
                            window.gacRawRule = b.rule || window.gacRawRule;
                            if (typeof bootstrap !== 'undefined' && sbgtModalEl) {
                                var inst = bootstrap.Modal.getInstance(sbgtModalEl);
                                if (inst) inst.hide();
                            }
                            return Promise.resolve(table.setData(dataUrl));
                        })
                        .then(function() { updatePageInfoBadge(); })
                        .catch(function() {
                            if (errEl) {
                                errEl.textContent = 'Network or server error.';
                                errEl.classList.remove('d-none');
                            }
                        })
                        .finally(function() { sbgtSaveBtn.disabled = false; });
                });
            }

            var sbidModalEl = document.getElementById('gacRawSbidRuleModal');
            if (sbidModalEl) {
                sbidModalEl.addEventListener('show.bs.modal', function() {
                    var sErr = document.getElementById('gacRawSbidRuleErr');
                    if (sErr) { sErr.classList.add('d-none'); sErr.textContent = ''; }
                    gacRefreshRuleFromServer(function() {
                        gacFillSbidForm((window.gacRawRule && window.gacRawRule.sbid) || {});
                    });
                });
            }
            var sbidSaveBtn = document.getElementById('gacRawSbidRuleSaveBtn');
            if (sbidSaveBtn) {
                sbidSaveBtn.addEventListener('click', function() {
                    var sErr = document.getElementById('gacRawSbidRuleErr');
                    if (sErr) { sErr.classList.add('d-none'); sErr.textContent = ''; }
                    var sbgtKeep = (window.gacRawRule && window.gacRawRule.sbgt) ? window.gacRawRule.sbgt : {};
                    var payload = { sbgt: sbgtKeep, sbid: gacCollectSbid() };
                    var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
                    sbidSaveBtn.disabled = true;
                    fetch(gacRawRuleSaveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload),
                    })
                        .then(function(res) { return res.json().then(function(body) { return { ok: res.ok, body: body }; }); })
                        .then(function(out) {
                            var b = out.body || {};
                            if (!out.ok) {
                                if (sErr) {
                                    sErr.textContent = b.message || b.error || 'Save failed.';
                                    sErr.classList.remove('d-none');
                                }
                                return;
                            }
                            window.gacRawRule = b.rule || window.gacRawRule;
                            if (typeof bootstrap !== 'undefined' && sbidModalEl) {
                                var sInst = bootstrap.Modal.getInstance(sbidModalEl);
                                if (sInst) sInst.hide();
                            }
                            return Promise.resolve(table.setData(dataUrl));
                        })
                        .then(function() { updatePageInfoBadge(); })
                        .catch(function() {
                            if (sErr) {
                                sErr.textContent = 'Network or server error.';
                                sErr.classList.remove('d-none');
                            }
                        })
                        .finally(function() { sbidSaveBtn.disabled = false; });
                });
            }
        });
    </script>
@endsection
