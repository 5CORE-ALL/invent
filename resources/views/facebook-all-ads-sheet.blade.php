@php
    /**
     * This blade powers three pages — All / Video / Carousal — distinguished
     * by the controller-supplied `$pageType` ('all' | 'video' | 'carousal').
     * The Video and Carousal pages restrict the Ad Type dropdown and pass
     * `?type=…` to the data endpoint so the server filters server-side.
     */
    $pageType        = $pageType        ?? 'all';
    // Optional channel lens — when set ('FB' | 'Insta') the page only
    // shows rows tagged with that CH value (powers the Facebook /
    // Instagram channel pages). Null on the generic "all" page.
    $chFilter        = $chFilter        ?? null;
    $pageTitle       = $pageTitle       ?? 'Facebook All Ads Sheet';
    $pageSubtitle    = $pageSubtitle    ?? 'Generic CSV / Excel / TSV importer — upload any sheet and view it as a table';
    $allowedAdTypes  = $allowedAdTypes  ?? ['GROUP VIDEO', 'GROUP CAROUSAL', 'PARENT VIDEO', 'PARENT CAROUSAL'];
@endphp

@extends('layouts.vertical', ['title' => $pageTitle, 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .faas-meta { font-size: 0.8rem; color: #6b7280; }
        .faas-meta strong { color: #374151; }
        .faas-batch-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 0.75rem;
            font-weight: 600;
        }
        #facebook-all-ads-table .tabulator-header { background: #f9fafb; }
        #facebook-all-ads-table .tabulator-col-title { font-weight: 600; color: #1f2937; }
        .tabulator-paginator label { margin-right: 5px; }

        /* Hide the sort triangle in column headers — clicking the header
           still sorts, the indicator just isn't drawn. Targets all
           Tabulator versions: 6.x uses .tabulator-col-sorter, older
           builds emit .tabulator-arrow. */
        #facebook-all-ads-table .tabulator-col-sorter,
        #facebook-all-ads-table .tabulator-arrow { display: none !important; }
        /* Reclaim the space the indicator used so titles don't look
           awkwardly off-centre. */
        #facebook-all-ads-table .tabulator-col-content { padding-right: 8px; }

        /* ── Sum badges (Impressions / Clicks / …) ────────────────────
           Solid-pill style. Each metric gets its own colour so users
           can scan the strip at a glance. flex-shrink:0 keeps each
           badge intact when the strip overflows horizontally — it just
           scrolls inside its container instead of squashing. */
        /* Tabulator pins the column header high in its own stacking
           context (z-index ~10), and the badge strip uses overflow-x:
           auto which creates a clipping box. Both fight Bootstrap's
           dropdown popups — bump every toolbar dropdown above them. */
        .card-body .dropdown-menu.show { z-index: 1080 !important; }
        /* The Sbgt filter is the one inside the overflow:auto strip.
           data-bs-strategy="fixed" already detaches it, but we still
           need an explicit z-index that beats Tabulator's column
           headers and frozen-row layers. */
        .faas-sbgt-dropdown .dropdown-menu.show {
            position: fixed !important;
            z-index: 1080 !important;
        }

        .faas-stat-badge {
            display: inline-block;
            flex-shrink: 0;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 6px;
            white-space: nowrap;
            line-height: 1.2;
            cursor: pointer;            /* clicks open the trend chart */
            transition: transform 0.1s ease;
        }
        .faas-stat-badge:hover { transform: translateY(-1px); filter: brightness(1.1); }
        /* Compact title so the badge strip has more horizontal room. */
        .faas-toolbar-title { font-size: 1rem; flex-shrink: 0; }
        .faas-stat-badge--count { background: #475569; }   /* slate   */
        .faas-stat-badge--impr  { background: #4c7ed8; }   /* blue    */
        .faas-stat-badge--clk   { background: #f59e0b; }   /* amber   */
        .faas-stat-badge--spend { background: #ef4444; }   /* red     */
        .faas-stat-badge--sales { background: #16a34a; }   /* green   */
        .faas-stat-badge--sold  { background: #8b5cf6; }   /* purple  */
        .faas-stat-badge--acos  { background: #ea580c; }   /* orange  */
        .faas-stat-badge--ctr   { background: #0891b2; }   /* cyan    */
        .faas-stat-badge--cvr   { background: #db2777; }   /* pink    */

        /* ── Badge trend chart modal — full screen width, pinned to top
           (same look & sizing as /all-marketplace-master adBreakdownChartModal).
           Theme uses --tz-modal-* CSS variables, so we override those *and*
           the dialog/content widths directly to be safe across themes. */
        #badgeChartModal.modal {
            --tz-modal-width: 100%;
            --tz-modal-margin: 0.5rem 0;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        #badgeChartModal .modal-dialog {
            width: 100% !important;
            max-width: none !important;
            margin: 0.5rem 0 0 0 !important;
        }
        #badgeChartModal .modal-content {
            border-radius: 0;
            width: 100%;
            max-width: 100%;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => $pageTitle,
        'sub_title'  => $pageSubtitle,
    ])

    <div class="toast-container"></div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between flex-nowrap gap-2">
                    {{-- Left group: title + sum-badges. flex-grow + overflow-x-auto
                         lets the strip scroll horizontally on narrow screens
                         instead of pushing the right-side controls onto a
                         second line. --}}
                    <div class="d-flex align-items-center flex-nowrap gap-2 flex-grow-1 overflow-x-auto py-1"
                         style="min-width:0;">
                        {{-- Live sums of the IMPR / CLK columns
                             across whatever rows are currently visible
                             (after search / header filters). Updated by
                             updateMetricBadges() in the script below. --}}
                        {{-- Count badge — number of rows currently
                             visible (after search + Sbgt filter). Not
                             a chart link; clicking does nothing. --}}
                        <span id="faasCountBadge" class="faas-stat-badge faas-stat-badge--count"
                              title="Visible row count">Count:<span id="faasCountValue">0</span></span>
                        <span id="faasImpressionsBadge" data-metric="impr" data-label="Impr"
                              class="faas-stat-badge faas-stat-badge--impr badge-chart-link"
                              title="Click for 32-day trend">Impr:<span id="faasImpressionsValue">0</span></span>
                        <span id="faasClicksBadge" data-metric="clk" data-label="Clicks"
                              class="faas-stat-badge faas-stat-badge--clk badge-chart-link"
                              title="Click for 32-day trend">Clk:<span id="faasClicksValue">0</span></span>
                        <span id="faasSpendBadge" data-metric="spend" data-label="Spend"
                              class="faas-stat-badge faas-stat-badge--spend badge-chart-link"
                              title="Click for 32-day trend">Spend:<span id="faasSpendValue">$0</span></span>
                        <span id="faasSalesBadge" data-metric="sales" data-label="Sales"
                              class="faas-stat-badge faas-stat-badge--sales badge-chart-link"
                              title="Click for 32-day trend">Sales:<span id="faasSalesValue">$0</span></span>
                        <span id="faasSoldBadge" data-metric="sold" data-label="Sold"
                              class="faas-stat-badge faas-stat-badge--sold badge-chart-link"
                              title="Click for 32-day trend">Sold:<span id="faasSoldValue">0</span></span>
                        <span id="faasAcosBadge" data-metric="acos" data-label="ACOS"
                              class="faas-stat-badge faas-stat-badge--acos badge-chart-link"
                              title="Click for 32-day trend">Acos:<span id="faasAcosValue">0%</span></span>
                        <span id="faasCtrBadge" data-metric="ctr" data-label="CTR"
                              class="faas-stat-badge faas-stat-badge--ctr badge-chart-link"
                              title="Click for 32-day trend">CTR:<span id="faasCtrValue">0%</span></span>
                        <span id="faasCvrBadge" data-metric="cvr" data-label="CVR"
                              class="faas-stat-badge faas-stat-badge--cvr badge-chart-link"
                              title="Click for 32-day trend">CVR:<span id="faasCvrValue">0%</span></span>
                    </div>

                    {{-- Right group: stays on the same row as the badges
                         (flex-shrink-0 prevents the badge strip from
                         pushing it down). --}}
                    <div class="d-flex align-items-center flex-nowrap gap-2 flex-shrink-0">
                        {{-- Column visibility (saved per-page in
                             channel_tabulator_column_settings, channel
                             "facebook_all_ads_sheet") --}}
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                    type="button"
                                    id="faasColumnsBtn"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside"
                                    aria-expanded="false">
                                <i class="fas fa-eye"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end px-2 py-1"
                                id="faasColumnDropdownMenu"
                                aria-labelledby="faasColumnsBtn"
                                style="max-height:380px; overflow-y:auto; min-width:240px;">
                                <li class="text-muted small px-2 py-1">Loading…</li>
                            </ul>
                        </div>
                        <button type="button"
                                id="faasShowAllColumnsBtn"
                                class="btn btn-sm btn-outline-secondary"
                                title="Show every column">
                            <i class="fas fa-eye"></i>
                        </button>

                        {{-- Opens the SBGT-Rule editor: bands of
                             "ACOS ≤ X% → suggested budget Y" stored in
                             facebook_sbgt_rules. Same UX as /ebay/campaign-ads. --}}
                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#sbgtRuleModal"
                                title="Edit ACOS → Sbgt brackets">
                            <i class="fas fa-sliders-h me-1"></i>Rule
                        </button>

                        {{-- Pushes each visible row's Sbgt as the new
                             daily_budget on the matching Meta campaign.
                             Mirrors the eBay "Push SBID" button. --}}
                        <button type="button"
                                id="faasPushSbgtBtn"
                                class="btn btn-sm btn-warning text-dark"
                                title="Update each campaign's daily budget on Meta to its Sbgt value">
                            <i class="fas fa-cloud-upload-alt me-1"></i>Push
                        </button>

                        {{-- Export filtered rows + visible columns (respects
                             Type / Search / Sbgt / Stat filters). --}}
                        <div class="dropdown">
                            <button class="btn btn-sm btn-success dropdown-toggle"
                                    type="button"
                                    id="faasExportBtn"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="true"
                                    aria-expanded="false"
                                    title="Export visible rows and columns">
                                <i class="fas fa-file-export me-1"></i>Export
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end py-1"
                                aria-labelledby="faasExportBtn">
                                <li>
                                    <button type="button"
                                            class="dropdown-item"
                                            id="faasExportCsvBtn">
                                        <i class="fas fa-file-csv me-2 text-muted"></i>Export CSV
                                    </button>
                                </li>
                                <li>
                                    <button type="button"
                                            class="dropdown-item"
                                            id="faasExportExcelBtn">
                                        <i class="fas fa-file-excel me-2 text-muted"></i>Export Excel
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <button type="button"
                                class="btn btn-sm btn-success"
                                data-bs-toggle="modal"
                                data-bs-target="#faasUploadModal">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Second toolbar row — sits between the badge strip and
                 the table headers. Same card so the borders flow as
                 one continuous chrome. --}}
            <div class="card-body py-2 border-top">
                {{-- Order: Type · Search · Sbgt (2nd-last) · Stat (last) --}}
                <div class="d-flex align-items-center flex-nowrap gap-2">
                    {{-- Bulk-set the CH (channel) on the selected rows, or
                         every visible row when none are checked. --}}
                    <button type="button"
                            class="btn btn-sm btn-outline-info"
                            style="flex-shrink:0;"
                            data-bs-toggle="modal"
                            data-bs-target="#bulkChModal"
                            title="Set CH (FB / Insta) on the selected or visible rows">
                        <i class="fas fa-layer-group me-1"></i>Bulk CH
                    </button>

                    {{-- Type multi-select. Uses the AD_TYPE_COLORS
                         palette for the leading dot and shortAdType()
                         for compact labels. --}}
                    <div class="dropdown faas-sbgt-dropdown" style="flex-shrink:0;">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle"
                                type="button"
                                id="faasTypeFilterBtn"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                data-bs-strategy="fixed"
                                aria-expanded="false"
                                title="Filter rows by Ad Type">
                            <span id="faasTypeFilterLabel">Type: all</span>
                        </button>
                        <ul class="dropdown-menu px-2 py-1"
                            id="faasTypeFilterMenu"
                            aria-labelledby="faasTypeFilterBtn"
                            style="max-height:300px; overflow-y:auto; min-width:200px;">
                            <li class="d-flex justify-content-between align-items-center px-2 pt-1 pb-2 border-bottom">
                                <button type="button" class="btn btn-link btn-sm p-0" id="faasTypeFilterAll">All</button>
                                <button type="button" class="btn btn-link btn-sm p-0 text-muted" id="faasTypeFilterClear">None</button>
                            </li>
                        </ul>
                    </div>

                    {{-- Global search — fills the horizontal space
                         between the leading filters and the trailing
                         Sbgt/Stat selectors. --}}
                    <input type="text"
                           id="faas-search"
                           class="form-control form-control-sm"
                           placeholder="Search across all columns…"
                           style="flex-grow:1; min-width:220px;">

                    {{-- Sbgt-band multi-select (penultimate). --}}
                    <div class="dropdown faas-sbgt-dropdown" style="flex-shrink:0;">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle"
                                type="button"
                                id="faasSbgtFilterBtn"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                data-bs-strategy="fixed"
                                aria-expanded="false"
                                title="Filter rows by suggested-budget band">
                            <span id="faasSbgtFilterLabel">Sbgt: all</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end px-2 py-1"
                            id="faasSbgtFilterMenu"
                            aria-labelledby="faasSbgtFilterBtn"
                            style="max-height:300px; overflow-y:auto; min-width:200px;">
                            <li class="d-flex justify-content-between align-items-center px-2 pt-1 pb-2 border-bottom">
                                <button type="button" class="btn btn-link btn-sm p-0" id="faasSbgtFilterAll">All</button>
                                <button type="button" class="btn btn-link btn-sm p-0 text-muted" id="faasSbgtFilterClear">None</button>
                            </li>
                        </ul>
                    </div>

                    {{-- Status multi-select (last). --}}
                    <div class="dropdown faas-sbgt-dropdown" style="flex-shrink:0;">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle"
                                type="button"
                                id="faasStatusFilterBtn"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="outside"
                                data-bs-strategy="fixed"
                                aria-expanded="false"
                                title="Filter rows by Status">
                            <span id="faasStatusFilterLabel">Stat: all</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end px-2 py-1"
                            id="faasStatusFilterMenu"
                            aria-labelledby="faasStatusFilterBtn"
                            style="max-height:300px; overflow-y:auto; min-width:200px;">
                            <li class="d-flex justify-content-between align-items-center px-2 pt-1 pb-2 border-bottom">
                                <button type="button" class="btn btn-link btn-sm p-0" id="faasStatusFilterAll">All</button>
                                <button type="button" class="btn btn-link btn-sm p-0 text-muted" id="faasStatusFilterClear">None</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div id="faas-table-wrapper" style="height: calc(100vh - 230px); display: flex; flex-direction: column;">
                    <div id="facebook-all-ads-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

{{-- ── Upload Sheet modal ─────────────────────────────────────────── --}}
<div class="modal fade"
     id="faasUploadModal"
     tabindex="-1"
     aria-labelledby="faasUploadModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#1877f2,#0d5cb6);color:#fff;">
                <h5 class="modal-title" id="faasUploadModalLabel">
                    <i class="fas fa-cloud-upload-alt me-2"></i>Upload Sheet
                </h5>
                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="faasUploadForm" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-2">
                        <div class="col-12 col-md-5">
                            <label for="faasUploadType" class="form-label small fw-semibold">Upload type</label>
                            <select id="faasUploadType" name="upload_type" class="form-select" required>
                                <option value="">— select —</option>
                                <option value="campaign">📣 Campaign</option>
                                <option value="spend">💰 Spend</option>
                                <option value="sales">🛒 Sales</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-7">
                            <label for="faasFile" class="form-label small fw-semibold">Choose a file</label>
                            <input type="file"
                                   name="file"
                                   id="faasFile"
                                   class="form-control"
                                   required>
                        </div>
                    </div>
                    <div class="faas-meta mt-2">
                        Accepts <strong>CSV, TSV, TXT, XLSX, XLS, ODS</strong> — or anything tab / comma /
                        semicolon / pipe separated. First non-empty row = header.
                    </div>
                    <div id="faasUploadStatus" class="mt-3"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button"
                        id="faasUploadBtn"
                        class="btn btn-primary">
                    <i class="fas fa-upload me-1"></i>
                    Upload
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── SBGT Rule modal ──────────────────────────────────────────────
     Edit the ACOS-bracket → Suggested-budget bands. Persisted to
     facebook_sbgt_rules (key="facebook_all"). On save we re-fetch the
     table data so the Sbgt column reflects the new rule immediately. --}}
<div class="modal fade"
     id="sbgtRuleModal"
     tabindex="-1"
     aria-labelledby="sbgtRuleModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#1877f2,#0d5cb6);color:#fff;">
                <h5 class="modal-title" id="sbgtRuleModalLabel">
                    <i class="fas fa-sliders-h me-2"></i>Sbgt Rule — ACOS % → Suggested Budget
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Bands evaluated <strong>top to bottom</strong>; the first band whose
                    <em>ACOS&nbsp;≤ max</em> wins. Set <code>ACOS&nbsp;≤</code> to <code>9999</code>
                    on the last band so it catches everything above the previous threshold.
                </p>

                <table class="table table-sm table-bordered align-middle mb-0" id="sbgt-rule-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Label</th>
                            <th style="width:140px;">Color</th>
                            <th style="width:140px;">ACOS&nbsp;≤ (%)</th>
                            <th style="width:120px;">Sbgt</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="sbgt-bands-body"></tbody>
                </table>

                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="sbgt-add-band-btn">
                    <i class="fas fa-plus me-1"></i>Add band
                </button>

                <p class="small text-danger mb-0 mt-2 d-none" id="sbgt-rule-err"></p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary" id="sbgt-rule-save-btn">
                    <i class="fas fa-save me-1"></i>Save Rule
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── Push SBGT result modal ──────────────────────────────────────
     Opens after the Push SBGT call completes. Lists every campaign
     attempted and whether the Meta API accepted the new daily_budget. --}}
<div class="modal fade"
     id="sbgtResultModal"
     tabindex="-1"
     aria-labelledby="sbgtResultModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#1877f2,#0d5cb6);color:#fff;">
                <h5 class="modal-title" id="sbgtResultModalLabel">
                    <i class="fas fa-cloud-upload-alt me-2"></i>Push SBGT — Results
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-2 mb-3" id="sbgt-result-summary">
                    {{-- filled by JS: pushed / failed / skipped pills --}}
                </div>
                <table class="table table-sm table-bordered align-middle" id="sbgt-result-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Campaign ID</th>
                            <th style="width:90px;">Sbgt</th>
                            <th style="width:110px;">Status</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody id="sbgt-result-body"></tbody>
                </table>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ── Bulk CH modal ───────────────────────────────────────────────
     Sets the CH (channel) column on every row currently visible in the
     table (after Type / Search / Sbgt / Stat filters) in one go. The
     same per-campaign propagation as the inline dropdown applies. --}}
<div class="modal fade"
     id="bulkChModal"
     tabindex="-1"
     aria-labelledby="bulkChModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#1877f2,#0d5cb6);color:#fff;">
                <h5 class="modal-title" id="bulkChModalLabel">
                    <i class="fas fa-layer-group me-2"></i>Bulk update CH
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Applies the chosen channel to
                    <span id="bulkChScope" class="fw-semibold text-dark">all visible rows</span>.
                    Tick the row checkboxes to target only those; otherwise every
                    row currently visible (after the Type / Search / Sbgt / Stat
                    filters) is used.
                    <span id="bulkChCount" class="fw-semibold text-dark">0</span>
                    campaign(s) will be updated.
                </p>
                <div class="mb-3">
                    <label for="bulkChValue" class="form-label fw-semibold">Channel (CH)</label>
                    <select id="bulkChValue" class="form-select">
                        <option value="">— Clear (remove CH) —</option>
                        @foreach (($chOptions ?? ['FB', 'Insta']) as $opt)
                            <option value="{{ $opt }}">{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="bulkChError" class="alert alert-danger py-2 small d-none mb-0"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="bulkChApplyBtn" class="btn btn-sm btn-info text-white">
                    <i class="fas fa-check me-1"></i>Apply to visible rows
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── Campaign Audit modal ────────────────────────────────────────
     Opens when the Audit cell is clicked. Renders the AUDIT_CHECKLIST
     items as point-weighted checkboxes with a live total, plus a
     comments box and an inline history list of the last 50 audits. --}}
<div class="modal fade"
     id="auditModal"
     tabindex="-1"
     aria-labelledby="auditModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#1877f2,#0d5cb6);color:#fff;">
                <h5 class="modal-title" id="auditModalLabel">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Campaign Audit — <span id="auditCampaignName" class="fw-normal opacity-75"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="badge bg-secondary">Campaign ID: <code id="auditCampaignId" class="text-white"></code></span>
                    <span class="badge bg-light text-dark border">
                        Live score:
                        <strong id="auditLiveScore">0%</strong>
                        <small class="text-muted">(<span id="auditLiveEarned">0</span>/<span id="auditLiveTotal">0</span> pts)</small>
                    </span>
                </div>

                <div class="table-responsive border rounded mb-2">
                    <table class="table table-sm align-middle mb-0" id="auditChecklistTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;"></th>
                                <th>Check</th>
                                <th style="min-width:200px;">Notes</th>
                                <th class="text-end" style="width:80px;">Points</th>
                                <th style="width:36px;"></th>
                            </tr>
                        </thead>
                        <tbody id="auditChecklistBody"></tbody>
                    </table>
                </div>

                {{-- Manually add a custom check + point row to the checklist
                     above. Added rows are scored and saved alongside the
                     built-in checklist items. --}}
                <div class="d-flex flex-wrap align-items-end gap-2 mb-3 p-2 border rounded bg-light">
                    <div class="flex-grow-1" style="min-width:200px;">
                        <label class="form-label small text-muted mb-1">Add custom check</label>
                        <input type="text" id="auditCustomLabel"
                               class="form-control form-control-sm"
                               placeholder="e.g. Landing page loads under 3s">
                    </div>
                    <div style="width:90px;">
                        <label class="form-label small text-muted mb-1">Points</label>
                        <input type="number" id="auditCustomWeight" min="0" step="1" value="5"
                               class="form-control form-control-sm text-end">
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="auditAddCustomBtn">
                        <i class="fas fa-plus me-1"></i>Add
                    </button>
                </div>

                <label class="form-label small text-muted mb-1">Comments</label>
                <textarea id="auditComments"
                          class="form-control form-control-sm"
                          rows="3"
                          placeholder="Notes about what looked off or what to revisit next time…"></textarea>

                <hr class="my-3">
                <h6 class="small text-uppercase text-muted mb-2">Audit history</h6>
                <div id="auditHistoryEmpty" class="small text-muted d-none">No prior audits for this campaign.</div>
                <div class="table-responsive border rounded" id="auditHistoryWrap">
                    <table class="table table-sm mb-0" id="auditHistoryTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width:160px;">When</th>
                                <th style="width:160px;">By</th>
                                <th class="text-end" style="width:80px;">Score</th>
                                <th>Comments</th>
                            </tr>
                        </thead>
                        <tbody id="auditHistoryBody"></tbody>
                    </table>
                </div>

                <p class="small text-danger mb-0 mt-2 d-none" id="auditError"></p>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary" id="auditSaveBtn">
                    <i class="fas fa-save me-1"></i>Save Audit
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── Badge history chart modal ───────────────────────────────────
     Same line-chart idea as /all-marketplace-master: clickable badge
     opens this modal, /facebook-all-ads-sheet/badge-history feeds it,
     and Chart.js draws a 32-day rolling line with dots (green = up,
     red = down, grey = flat), a dashed median line, and a side panel
     showing HIGHEST / MEDIAN / LOWEST. --}}
<div class="modal fade p-0"
     id="badgeChartModal"
     tabindex="-1"
     aria-labelledby="badgeChartModalLabel"
     aria-hidden="true">
    <div class="modal-dialog shadow-none m-0 mx-0">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#0d6efd;color:#fff;">
                <h6 class="modal-title fw-bold" id="badgeChartModalLabel">
                    <i class="fas fa-chart-line me-1"></i>
                    <span id="badgeChartTitle">Trend</span>
                </h6>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <select id="badgeChartRange" class="form-select form-select-sm" style="width:120px;">
                        <option value="7">7 Days</option>
                        <option value="14">14 Days</option>
                        <option value="32" selected>32 Days</option>
                        <option value="60">60 Days</option>
                        <option value="90">90 Days</option>
                    </select>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body p-0">
                <div class="d-flex">
                    <div style="flex:1; min-height:300px; padding:8px;">
                        <canvas id="badgeChartCanvas"></canvas>
                        <p class="text-center text-muted small mb-0 d-none" id="badgeChartEmpty">
                            No history available for this metric in the selected window.
                        </p>
                    </div>
                    <div style="width:120px; border-left:1px solid #dee2e6; padding:14px 10px; text-align:center; font-family:'Inter',system-ui,sans-serif;">
                        <div class="small text-uppercase fw-bold" style="color:#dc3545;">Highest</div>
                        <div class="fs-5 fw-bold" id="badgeChartHighest">—</div>
                        <hr class="my-2">
                        <div class="small text-uppercase fw-bold" style="color:#6c757d;">Median</div>
                        <div class="fs-5 fw-bold" id="badgeChartMedian">—</div>
                        <hr class="my-2">
                        <div class="small text-uppercase fw-bold" style="color:#198754;">Lowest</div>
                        <div class="fs-5 fw-bold" id="badgeChartLowest">—</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                        || document.querySelector('input[name="_token"]')?.value
                        || '';

        // Page-specific config (set by the controller).
        const PAGE_TYPE = @json($pageType);
        // Channel lens ('FB' | 'Insta' | null) — sent to the data feed as
        // ?ch=… so the server filters rows by the CH column.
        const CH_FILTER = @json($chFilter);
        // Dropdown choices on this page. On Video/Carousal pages this is a
        // restricted subset so users can't pick a value that would make the
        // row disappear from the page they're currently looking at.
        const AD_TYPES  = @json($allowedAdTypes);
        const AD_TYPE_COLORS = {
            'GROUP VIDEO':     { bg: '#dbeafe', fg: '#1e40af' },
            'GROUP CAROUSAL':  { bg: '#dcfce7', fg: '#166534' },
            'PARENT VIDEO':    { bg: '#fef3c7', fg: '#92400e' },
            'PARENT CAROUSAL': { bg: '#fce7f3', fg: '#9d174d' },
        };
        // CH (channel) dropdown — which platform the campaign runs on.
        const CH_OPTIONS = @json($chOptions ?? ['FB', 'Insta']);
        const CH_COLORS = {
            'FB':    { bg: '#e0e7ff', fg: '#3730a3' },  // indigo  → Facebook
            'Insta': { bg: '#fce7f3', fg: '#9d174d' },  // pink    → Instagram
        };
        // Frontend-only short labels for the Ad-Type column. The DB
        // and the rest of the code keep using the full strings — only
        // what the user sees is shortened.
        function shortAdType(v) {
            if (!v) return v;
            return v.replace(/^GROUP\b/,  'G')
                    .replace(/^PARENT\b/, 'P');
        }

        // Tabulator column visibility is shared across users via the
        // channel_tabulator_column_settings table (channel_name +
        // visibility JSON). Same endpoints as /ebay3-tabulator.
        const FAAS_COLUMN_CHANNEL    = 'facebook_all_ads_sheet';
        const COLUMN_VISIBILITY_URL  = '/tabulator-column-visibility';
        // In-memory copy of the saved map so every table rebuild can
        // re-apply it without an extra round trip.
        let savedColumnVisibility = {};

        let tabulator = null;

        // Render the Ad Type cell as a coloured pill — pill text uses
        // the short label (G VIDEO / P CAROUSAL / …), but the underlying
        // cell value stays the full string.
        function formatAdTypeCell(cell) {
            const v = cell.getValue();
            if (!v) {
                return '<span class="text-muted small">— Select —</span>';
            }
            const c     = AD_TYPE_COLORS[v] || { bg: '#e5e7eb', fg: '#374151' };
            const label = shortAdType(v);
            return `<span style="display:inline-block;padding:2px 10px;border-radius:999px;`
                 + `background:${c.bg};color:${c.fg};font-size:0.75rem;font-weight:600;">${label}</span>`;
        }

        // Render the CH (channel) cell as a coloured pill — FB / Insta.
        function formatChCell(cell) {
            const v = cell.getValue();
            if (!v) {
                return '<span class="text-muted small">— Select —</span>';
            }
            const c = CH_COLORS[v] || { bg: '#e5e7eb', fg: '#374151' };
            return `<span style="display:inline-block;padding:2px 10px;border-radius:999px;`
                 + `background:${c.bg};color:${c.fg};font-size:0.75rem;font-weight:600;">${v}</span>`;
        }

        // Renders the Status column (sourced from Meta's "Campaign
        // delivery") as a coloured dot. The full status text lives in
        // the title= tooltip so users can still hover to see it; the
        // header-filter dropdown also still shows the words.
        function formatStatusCell(cell) {
            const raw = (cell.getValue() ?? '').toString().trim();
            if (!raw) return '';
            // Normalise so "Not_delivering" / "not delivering" / etc.
            // all hit the same key.
            const key = raw.toLowerCase().replace(/[\s_-]+/g, '_');
            const dotColors = {
                'active'           : '#16a34a',  // green
                'inactive'         : '#ca8a04',  // mustard
                'archived'         : '#dc2626',  // red
                'not_delivering'   : '#2563eb',  // blue
            };
            const color = dotColors[key] || '#9ca3af'; // neutral grey for anything else
            const safe  = raw.replace(/</g, '&lt;');
            return `<span title="${safe}"`
                 + ` style="display:inline-block;width:12px;height:12px;border-radius:50%;`
                 + `background:${color};box-shadow:0 0 0 1px rgba(0,0,0,0.05);"></span>`;
        }

        // Cells in the Acos / Sbgt columns get coloured text to match
        // the ACOS-band the row falls into. The colour comes from the
        // hidden `_sbgt_color` field set by the controller, so editing
        // the rule (Sbgt-Rule modal) re-paints the whole table after
        // the next loadTable().
        function formatBandColoredCell(cell) {
            const value = cell.getValue();
            const color = cell.getRow().getData()._sbgt_color;
            const el    = cell.getElement();
            // Always reset background — we only colour the text now.
            el.style.background = '';
            if (color) {
                el.style.color      = color;
                el.style.fontWeight = '700';
            } else {
                // No band match → reset any leftover styling from a
                // previous render (in case the row was re-projected).
                el.style.color      = '';
                el.style.fontWeight = '';
            }
            return (value === null || value === undefined || value === '') ? '' : String(value);
        }

        // Persist a row's chosen Ad Type to the backend on edit.
        function onAdTypeEdited(cell) {
            const row     = cell.getRow();
            const id      = row.getData()._id;
            const value   = cell.getValue() || '';
            const oldVal  = cell.getOldValue() || '';
            if (!id) return;

            const fd = new FormData();
            fd.append('ad_type', value);
            fd.append('_token',  csrfToken);

            fetch(`/facebook-all-ads-sheet/${id}/ad-type`, {
                method:      'POST',
                credentials: 'same-origin',
                headers:     { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body:        fd,
            })
            .then(async r => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok || !data.success) throw new Error(data.message || `HTTP ${r.status}`);
                return data;
            })
            .catch(err => {
                // Revert the cell on save failure.
                row.update({ ad_type: oldVal });
                alert('Failed to save Ad Type: ' + err.message);
            });
        }

        // Persist a row's chosen CH (channel) to the backend on edit.
        function onChEdited(cell) {
            const row    = cell.getRow();
            const id     = row.getData()._id;
            const value  = cell.getValue() || '';
            const oldVal = cell.getOldValue() || '';
            if (!id) return;

            const fd = new FormData();
            fd.append('ch',     value);
            fd.append('_token', csrfToken);

            fetch(`/facebook-all-ads-sheet/${id}/ch`, {
                method:      'POST',
                credentials: 'same-origin',
                headers:     { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body:        fd,
            })
            .then(async r => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok || !data.success) throw new Error(data.message || `HTTP ${r.status}`);
                return data;
            })
            .catch(err => {
                row.update({ ch: oldVal });
                alert('Failed to save CH: ' + err.message);
            });
        }

        function showStatus(html, type = 'info') {
            const cls = {
                info:    'alert-info',
                success: 'alert-success',
                error:   'alert-danger',
                warn:    'alert-warning',
            }[type] || 'alert-info';
            document.getElementById('faasUploadStatus').innerHTML =
                `<div class="alert ${cls} mb-0 py-2 small">${html}</div>`;
        }

        function clearStatus() {
            document.getElementById('faasUploadStatus').innerHTML = '';
        }

        function uploadTypeLabel(t) {
            if (t === 'campaign') return '📣 Campaign';
            if (t === 'spend')    return '💰 Spend';
            if (t === 'sales')    return '🛒 Sales';
            if (t === 'merged')   return '🔗 Merged';
            return '';
        }

        // The batch pill / row counter / batch dropdown were removed from
        // the toolbar. updateBatchPill is now a no-op so existing callers
        // (loadTable.then(...)) keep working without touching missing DOM.
        function updateBatchPill(batch) { /* no-op — pill removed from UI */ }

        // loadBatches still pings /batches (cheap) so the merged-view
        // logic and post-upload refresh stay correct, but we no longer
        // render the dropdown options anywhere.
        function loadBatches(selectedId = '__merged__') {
            return fetch('/facebook-all-ads-sheet/batches', { credentials: 'same-origin' })
                .then(r => r.json())
                .catch(() => ({}));
        }

        function loadTable(batchId = '__merged__') {
            const params = new URLSearchParams();
            // '__merged__' means "join latest Campaign + latest Spend" — send
            // `view=merged` instead of a real batch id.
            if (batchId === '__merged__' || !batchId) {
                params.set('view', 'merged');
            } else {
                params.set('batch_id', batchId);
            }
            if (PAGE_TYPE !== 'all') params.set('type', PAGE_TYPE);
            if (CH_FILTER) params.set('ch', CH_FILTER);
            const url = '/facebook-all-ads-sheet/data?' + params.toString();

            return fetch(url, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(resp => {
                    if (!resp.success) {
                        showStatus('Failed to load data.', 'error');
                        return;
                    }
                    updateBatchPill(resp.batch);

                    // Numeric / metric columns get no per-column search box —
                    // a free-text filter on a number doesn't make sense and
                    // it eats vertical space under the header.
                    const NO_HEADER_FILTER = new Set([
                        'Campaign name',
                        'Acos', 'Sbgt', 'IMPR', 'CLK', 'CTR',
                        'SPEND', 'SALES', 'SOLD', 'CVR', 'CPS',
                    ]);
                    // Columns whose cell should be painted with the
                    // matched ACOS-band colour (set by the controller as
                    // a hidden `_sbgt_color` field on each row).
                    const COLOR_BY_BAND = new Set(['Acos', 'Sbgt']);
                    // Empty SALES → "$0" in red so missing-revenue rows
                    // jump out instead of looking like a blank cell.
                    function formatSalesCell(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || String(v).trim() === '') {
                            return '<span style="color:#dc2626;font-weight:700;">$0</span>';
                        }
                        return String(v);
                    }
                    // Same idea for SOLD — show "0" in red when no
                    // orders attribute to the row, without the $ prefix.
                    function formatSoldCell(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || String(v).trim() === '') {
                            return '<span style="color:#dc2626;font-weight:700;">0</span>';
                        }
                        return String(v);
                    }
                    // CVR is a ratio — show "0%" in red when blank
                    // (no clicks → no conversion to compute).
                    function formatCvrCell(cell) {
                        const v = cell.getValue();
                        if (v === null || v === undefined || String(v).trim() === '') {
                            return '<span style="color:#dc2626;font-weight:700;">0%</span>';
                        }
                        return String(v);
                    }
                    // Campaign name can be very long — truncate to 70
                    // chars in the cell but expose the full text on
                    // hover (and to header search) via a title tooltip.
                    function formatCampaignNameCell(cell) {
                        const v = (cell.getValue() ?? '').toString();
                        if (!v) return '';
                        const safe = v.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                        const attr = safe.replace(/'/g, '&#39;');
                        const copy = `<i class="fas fa-copy faas-copy-name" role="button" tabindex="0"`
                                   + ` title="Copy campaign name" data-copy="${attr}"`
                                   + ` style="margin-left:6px;color:#94a3b8;cursor:pointer;flex-shrink:0;"></i>`;
                        const text = v.length <= 70
                            ? safe
                            : `<span title="${safe}">${v.slice(0, 70).replace(/&/g, '&amp;').replace(/</g, '&lt;')}…</span>`;
                        return `<span style="display:inline-flex;align-items:center;gap:2px;max-width:100%;">`
                             + `<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${text}</span>`
                             + `${copy}</span>`;
                    }
                    // Audit column — small button that opens the audit
                    // modal. Cell text shows the latest score (0–100%)
                    // colour-coded green/amber/red. Empty for never-
                    // audited campaigns ("Audit" prompt only).
                    function formatAuditCell(cell) {
                        const row   = cell.getRow().getData();
                        const cid   = (row['CAMPAIGN ID'] ?? '').toString();
                        const score = row._audit_score;
                        if (!cid || !/^\d{6,}$/.test(cid)) return '';
                        if (score == null) {
                            return `<button type="button"
                                            class="btn btn-sm btn-outline-primary py-0 px-2"
                                            style="font-size:11px;"
                                            data-audit-cid="${cid}">
                                        <i class="fas fa-clipboard-check"></i> Audit
                                    </button>`;
                        }
                        const colour = score >= 80 ? '#16a34a'
                                     : score >= 50 ? '#ca8a04'
                                     : '#dc2626';
                        return `<button type="button"
                                        class="btn btn-sm py-0 px-2"
                                        style="font-size:11px;background:${colour};color:#fff;font-weight:700;"
                                        data-audit-cid="${cid}">${score}%</button>`;
                    }
                    // History column — last audit "MMM dd · name" with
                    // a tooltip carrying the comments. Empty when
                    // never audited.
                    function formatHistoryCell(cell) {
                        const row = cell.getRow().getData();
                        const at  = row._audit_at;
                        if (!at) return '<span class="text-muted small">—</span>';
                        // Trim seconds and reformat: "2026-05-22 02:45:11" → "May 22"
                        const d = new Date(at.replace(' ', 'T'));
                        const when = isNaN(d) ? at
                            : d.toLocaleDateString('en-US', { month: 'short', day: '2-digit' });
                        const who  = (row._audit_by || '—').toString().replace(/</g, '&lt;');
                        const tip  = (row._audit_comments || 'No comments')
                            .toString().replace(/"/g, '&quot;').slice(0, 200);
                        return `<span title="${tip}" style="cursor:help;font-size:11px;">`
                             + `${when} <span class="text-muted">·</span> ${who}</span>`;
                    }

                    // Link column — icon-only anchor (opens in a new
                    // tab). Falls back to plain text if the value
                    // isn't a URL.
                    function formatLinkCell(cell) {
                        const v = (cell.getValue() ?? '').toString().trim();
                        if (!v) return '';
                        const isUrl = /^https?:\/\//i.test(v);
                        const safeHref = v.replace(/"/g, '&quot;');
                        const safeText = v.replace(/&/g, '&amp;').replace(/</g, '&lt;');
                        if (!isUrl) return safeText;
                        return `<a href="${safeHref}" target="_blank" rel="noopener noreferrer"`
                             + ` title="${safeHref}"`
                             + ` style="color:#2563eb;font-size:14px;">`
                             + `<i class="fas fa-external-link-alt"></i>`
                             + `</a>`;
                    }
                    // Distinct Status values present in this dataset →
                    // also populates the toolbar Status filter.
                    statusValues = Array.from(new Set(
                        (resp.data || [])
                            .map(r => (r['Status'] ?? '').toString().trim())
                            .filter(v => v !== '')
                    )).sort();

                    // First-load default: pre-select "active" so users
                    // see only delivering campaigns until they widen
                    // the filter themselves.
                    if (!defaultStatusApplied) {
                        defaultStatusApplied = true;
                        if (statusValues.some(v => statusKey(v) === 'active')) {
                            statusFilterSelected.add('active');
                        }
                    }
                    buildStatusFilter();
                    const cols = (resp.columns || []).map(c => {
                        if (c.field === 'Status') {
                            return {
                                // Header is "Stat" — frontend rename only;
                                // field stays "Status" so filter/lookup
                                // code keeps working unchanged.
                                title:        'Stat',
                                field:        c.field,
                                headerSort:   true,
                                widthGrow:    0,
                                width:        90,
                                minWidth:     80,
                                hozAlign:     'center',
                                headerFilter: false,
                                formatter:    formatStatusCell,
                            };
                        }
                        let formatter = 'plaintext';
                        if (COLOR_BY_BAND.has(c.field))      formatter = formatBandColoredCell;
                        else if (c.field === 'SALES')        formatter = formatSalesCell;
                        else if (c.field === 'SOLD')         formatter = formatSoldCell;
                        else if (c.field === 'CVR')          formatter = formatCvrCell;
                        else if (c.field === 'Campaign name') formatter = formatCampaignNameCell;
                        else if (c.field === 'Link')         formatter = formatLinkCell;
                        else if (c.field === 'Audit')        formatter = formatAuditCell;
                        else if (c.field === 'History')      formatter = formatHistoryCell;

                        // Numeric columns ship as already-formatted
                        // strings ("$165", "1.8%", "$1,234"). Without
                        // a custom sorter Tabulator defaults to text
                        // sort which goes wildly wrong for those —
                        // strip everything except digits, sign and
                        // decimal, then sort numerically.
                        const NUMERIC = new Set([
                            'Acos','Sbgt','IMPR','CLK','CTR',
                            'SPEND','SALES','SOLD','CVR','CPS',
                        ]);
                        const sorter = NUMERIC.has(c.field)
                            ? function (a, b) {
                                const na = parseFloat(String(a ?? '').replace(/[^\d.\-]/g, ''));
                                const nb = parseFloat(String(b ?? '').replace(/[^\d.\-]/g, ''));
                                const va = isFinite(na) ? na : -Infinity;
                                const vb = isFinite(nb) ? nb : -Infinity;
                                return va - vb;
                            }
                            // Default Tabulator string sort for text
                            // columns is fine.
                            : undefined;
                        // Compact metric columns get a much smaller
                        // min-width so 2–4-character values like "1%"
                        // or "68" don't reserve a 120px slot. Campaign
                        // name keeps a wide min so long names render
                        // without wrapping. Tabulator's fitDataStretch
                        // layout fills any leftover horizontal space.
                        const NARROW = new Set([
                            'Acos','Sbgt','IMPR','CLK','CTR','SPEND','SALES','SOLD','CVR','CPS',
                        ]);
                        const minWidth = NARROW.has(c.field) ? 70
                                       : (c.field === 'Campaign name' ? 220
                                       : (c.field === 'CAMPAIGN ID'  ? 150
                                       : (c.field === 'Link'         ? 60
                                       : (c.field === 'Audit'        ? 80
                                       : (c.field === 'History'      ? 140 : 100)))));
                        const widthGrow = NARROW.has(c.field) ? 0
                                       : (c.field === 'Campaign name' ? 3
                                       : (c.field === 'Link'         ? 0
                                       : (c.field === 'Audit'        ? 0
                                       : (c.field === 'History'      ? 0 : 1))));
                        // Link header — show just the icon, no word.
                        const title = c.field === 'Link'
                            ? '<i class="fas fa-link" title="Link"></i>'
                            : c.title;
                        // Action columns aren't sortable — sorting them
                        // is meaningless (Audit is a button, History is
                        // a person+date string, Link is an icon).
                        const NO_SORT = new Set(['Audit', 'History', 'Link']);
                        return {
                            // Tabulator renders `title` as innerHTML,
                            // so the Link column can swap its label
                            // for an icon by passing an <i> element.
                            title:        title,
                            field:        c.field,
                            // All header search inputs are off — users
                            // filter via the "Search across all columns"
                            // input above the table instead.
                            headerFilter: false,
                            headerSort:   !NO_SORT.has(c.field),
                            widthGrow:    widthGrow,
                            minWidth:     minWidth,
                            formatter:    formatter,
                            sorter:       sorter,
                        };
                    });
                    // Prepend the Ad Type + CH dropdown columns. (Row index
                    // hidden by request — the data is still in the row
                    // payload as `_row_index` for any future use.)
                    cols.unshift(
                        {
                            // Row-selection checkbox. Used by Bulk CH —
                            // when any rows are checked the bulk action
                            // targets only those, otherwise all visible.
                            formatter:       'rowSelection',
                            titleFormatter:  'rowSelection',
                            titleFormatterParams: { rowRange: 'active' },
                            hozAlign:        'center',
                            headerHozAlign:  'center',
                            headerSort:      false,
                            width:           42,
                            frozen:          true,
                            cellClick:       function (e, cell) { cell.getRow().toggleSelect(); },
                        },
                        {
                            // CH = channel the campaign runs on (FB / Insta).
                            title:        'CH',
                            field:        'ch',
                            width:        90,
                            headerFilter: false,
                            editor:       'list',
                            editorParams: {
                                values: {
                                    '': '— Select —',
                                    ...Object.fromEntries(CH_OPTIONS.map(v => [v, v])),
                                },
                                clearable:        true,
                                autocomplete:     true,
                                listOnEmpty:      true,
                                placeholderEmpty: '— Select —',
                            },
                            cellEdited:   onChEdited,
                            formatter:    formatChCell,
                        },
                        {
                            // Header is "Type" but the field stays
                            // ad_type so all backend code is unchanged.
                            title:        'Type',
                            field:        'ad_type',
                            width:        130,
                            headerFilter: false,
                            editor:       'list',
                            editorParams: {
                                values: {
                                    '': '— Select —',
                                    ...Object.fromEntries(AD_TYPES.map(v => [v, shortAdType(v)])),
                                },
                                clearable:        true,
                                autocomplete:     true,
                                listOnEmpty:      true,
                                placeholderEmpty: '— Select —',
                            },
                            cellEdited:   onAdTypeEdited,
                            formatter:    formatAdTypeCell,
                        }
                    );

                    if (tabulator) {
                        tabulator.destroy();
                    }
                    tabulator = new Tabulator('#facebook-all-ads-table', {
                        data:                   resp.data || [],
                        columns:                cols,
                        layout:                 'fitDataStretch',
                        // Let the table fill #faas-table-wrapper which itself
                        // is sized to the viewport. Same pattern as
                        // /topdawg/sales-dashboard.
                        height:                 '100%',
                        pagination:             true,
                        paginationSize:         100,
                        paginationSizeSelector: [25, 50, 100, 250, 500],
                        paginationCounter:      'rows',
                        movableColumns:         true,
                        resizableColumns:       true,
                        clipboard:              true,
                        // Row checkboxes feed the Bulk CH action.
                        selectableRows:         true,
                        selectableRowsRangeMode:'click',
                        // Centre every column unless a column overrides
                        // hozAlign explicitly (e.g. the # row-index column).
                        columnDefaults: {
                            hozAlign:       'center',
                            headerHozAlign: 'center',
                            vertAlign:      'middle',
                        },
                        placeholder:            resp.batch
                            ? 'No rows in this upload.'
                            : 'No uploads yet — click Upload Sheet to get started.',
                    });

                    // Tabulator builds asynchronously — wait until columns
                    // exist before touching them. tableBuilt fires once per
                    // construction.
                    tabulator.on('tableBuilt', function () {
                        bindSearch();
                        applyColumnVisibility();
                        buildColumnDropdown();
                        // Re-apply whatever Sbgt/Type/Status/search
                        // filters are currently selected — ensures the
                        // "default = active only" pre-selection takes
                        // effect on first paint, and survives table
                        // rebuilds (after upload, page-type switch,…).
                        applyAllFilters();
                        updateMetricBadges();
                    });
                    // Recompute sums whenever the visible row set changes
                    // (search box, header filters, ad-type filter, …).
                    tabulator.on('dataFiltered', updateMetricBadges);
                    tabulator.on('dataLoaded',   updateMetricBadges);
                });
        }

        // ── Live sum badges (Impressions / Clicks) ────────────────────
        // Strips currency / thousand separators / "%" from a cell value
        // before summing. Anything non-numeric becomes 0.
        function toNumber(v) {
            if (v === null || v === undefined || v === '') return 0;
            if (typeof v === 'number') return isFinite(v) ? v : 0;
            const n = parseFloat(String(v).replace(/[^0-9.\-]/g, ''));
            return isFinite(n) ? n : 0;
        }

        // Mirrors the controller's divPct(): (num / den) * 100, with the
        // given decimals; renders empty/zero denominator as "0%".
        function divPct(num, den, decimals = 2) {
            if (!den || !isFinite(num) || !isFinite(den)) return '0%';
            return ((num / den) * 100).toFixed(decimals) + '%';
        }

        function updateMetricBadges() {
            const countEl = document.getElementById('faasCountValue');
            const imprEl  = document.getElementById('faasImpressionsValue');
            const clkEl   = document.getElementById('faasClicksValue');
            const spendEl = document.getElementById('faasSpendValue');
            const salesEl = document.getElementById('faasSalesValue');
            const soldEl  = document.getElementById('faasSoldValue');
            const acosEl  = document.getElementById('faasAcosValue');
            const ctrEl   = document.getElementById('faasCtrValue');
            const cvrEl   = document.getElementById('faasCvrValue');
            if (!imprEl || !clkEl || !spendEl || !salesEl || !soldEl
                || !acosEl || !ctrEl || !cvrEl) return;

            let imprSum = 0, clkSum = 0, spendSum = 0, salesSum = 0, soldSum = 0;
            let visibleCount = 0;
            if (tabulator) {
                // 'active' = rows after filters / search are applied,
                // which is what users see in the table.
                const rows = tabulator.getData('active') || [];
                visibleCount = rows.length;
                rows.forEach(r => {
                    imprSum  += toNumber(r.IMPR);
                    clkSum   += toNumber(r.CLK);
                    spendSum += toNumber(r.SPEND);
                    salesSum += toNumber(r.SALES);
                    soldSum  += toNumber(r.SOLD);
                });
            }
            if (countEl) countEl.textContent = visibleCount.toLocaleString();
            // Match column formatters: Spend & Sales are whole-dollar
            // amounts (controller already rounds them). Sold is a count.
            imprEl.textContent  = imprSum.toLocaleString();
            clkEl.textContent   = clkSum.toLocaleString();
            spendEl.textContent = '$' + Math.round(spendSum).toLocaleString();
            salesEl.textContent = '$' + Math.round(salesSum).toLocaleString();
            soldEl.textContent  = soldSum.toLocaleString();
            // Ratios use the same decimals the controller passes to
            // divPct(): Acos → 0 dp, CTR & CVR → 1 dp.
            acosEl.textContent  = divPct(spendSum, salesSum, 0);
            ctrEl.textContent   = divPct(clkSum,   imprSum,  1);
            cvrEl.textContent   = divPct(soldSum,  clkSum,   1);
        }

        // ── Column visibility (channel_tabulator_column_settings) ─────
        function fetchColumnVisibility() {
            return fetch(`${COLUMN_VISIBILITY_URL}?channel=${encodeURIComponent(FAAS_COLUMN_CHANNEL)}`,
                { credentials: 'same-origin' })
                .then(r => r.json())
                .then(map => {
                    // Backend returns {} (empty object) when no row exists.
                    savedColumnVisibility = (map && typeof map === 'object' && !Array.isArray(map))
                        ? map
                        : {};
                    return savedColumnVisibility;
                })
                .catch(() => { savedColumnVisibility = {}; return {}; });
        }

        function applyColumnVisibility() {
            if (!tabulator) return;
            tabulator.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (!def.field) return;
                if (savedColumnVisibility[def.field] === false) col.hide();
                else col.show();
            });
        }

        function saveColumnVisibility() {
            if (!tabulator) return Promise.resolve();
            const visibility = {};
            tabulator.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field) visibility[def.field] = col.isVisible();
            });
            savedColumnVisibility = visibility;
            return fetch(COLUMN_VISIBILITY_URL, {
                method:      'POST',
                credentials: 'same-origin',
                headers:     {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept':       'application/json',
                },
                body: JSON.stringify({
                    channel:    FAAS_COLUMN_CHANNEL,
                    visibility: visibility,
                }),
            }).catch(err => console.warn('column visibility save failed:', err));
        }

        // Build a checkbox per column inside the Columns dropdown.
        function buildColumnDropdown() {
            const menu = document.getElementById('faasColumnDropdownMenu');
            if (!menu || !tabulator) return;
            menu.innerHTML = '';
            tabulator.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (!def.field) return;
                // Skip the row-index helper column — toggling it is meaningless.
                if (def.field === '_row_index') return;

                const li    = document.createElement('li');
                const label = document.createElement('label');
                label.style.cssText = 'display:flex; align-items:center; gap:6px; padding:4px 6px; cursor:pointer; user-select:none; white-space:nowrap;';

                const cb = document.createElement('input');
                cb.type    = 'checkbox';
                cb.value   = def.field;
                cb.checked = col.isVisible();
                cb.addEventListener('change', () => {
                    if (cb.checked) col.show();
                    else            col.hide();
                    saveColumnVisibility();
                });

                label.appendChild(cb);
                label.appendChild(document.createTextNode(def.title || def.field));
                li.appendChild(label);
                menu.appendChild(li);
            });
        }

        // "Show all" — un-hides every column and persists the new state.
        document.getElementById('faasShowAllColumnsBtn')?.addEventListener('click', function () {
            if (!tabulator) return;
            tabulator.getColumns().forEach(col => col.show());
            saveColumnVisibility().then(() => buildColumnDropdown());
        });

        function bindSearch() {
            const input = document.getElementById('faas-search');
            if (!input || !tabulator) return;
            // Each keystroke recomposes the full filter list so the
            // search box, Sbgt, Type and Status filters always AND
            // together correctly.
            input.oninput = applyAllFilters;
        }

        // ── Wiring ──────────────────────────────────────────────
        function submitUpload() {
            const fileInput = document.getElementById('faasFile');
            const typeSel   = document.getElementById('faasUploadType');
            const uploadType = typeSel.value;
            if (!uploadType) {
                showStatus('Please pick an upload type (Campaign / Spend / Sales).', 'warn');
                return;
            }
            if (!fileInput.files.length) {
                showStatus('Please choose a file first.', 'warn');
                return;
            }

            const fd = new FormData();
            fd.append('file',        fileInput.files[0]);
            fd.append('upload_type', uploadType);
            fd.append('_token',      csrfToken);

            const btn = document.getElementById('faasUploadBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading…';
            showStatus(`Parsing and importing as <strong>${uploadType}</strong> — this may take a moment for large files…`, 'info');

            fetch('/facebook-all-ads-sheet/upload', {
                method:      'POST',
                credentials: 'same-origin',
                headers:     { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body:        fd,
            })
            .then(async r => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok || !data.success) {
                    throw new Error(data.message || `HTTP ${r.status}`);
                }
                return data;
            })
            .then(resp => {
                fileInput.value = '';
                // Close the modal once the import succeeded.
                const modalEl = document.getElementById('faasUploadModal');
                if (modalEl && window.bootstrap?.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }
                clearStatus();
                // After any upload, return to the Merged view — that's where
                // the user expects to see Campaign + Spend together. The
                // newly-uploaded batch is automatically included because
                // Merged pulls the latest batch of each type.
                return loadBatches('__merged__').then(() => loadTable('__merged__'));
            })
            .catch(err => {
                showStatus('Upload failed: ' + err.message, 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload';
            });
        }

        document.getElementById('faasUploadBtn').addEventListener('click', submitUpload);
        document.getElementById('faasUploadForm').addEventListener('submit', function (e) {
            // Pressing Enter inside the form should also trigger upload.
            e.preventDefault();
            submitUpload();
        });

        // Reset modal state when it's closed (so reopening shows a clean slate).
        document.getElementById('faasUploadModal')?.addEventListener('hidden.bs.modal', function () {
            clearStatus();
            document.getElementById('faasFile').value = '';
            document.getElementById('faasUploadType').value = '';
        });

        // ── SBGT Rule editor ──────────────────────────────────────────
        // Same shape as the eBay SBID rule: an array of { acos_max,
        // sbgt, label, color } bands persisted server-side.
        const SBGT_RULE_GET_URL  = '/facebook-all-ads-sheet/rule';
        const SBGT_RULE_SAVE_URL = '/facebook-all-ads-sheet/rule';
        let currentSbgtRule = { bands: [] };

        // Selected values for each multi-select filter. Empty set →
        // that filter is inactive.
        let sbgtFilterSelected   = new Set();   // numbers (1..20)
        let typeFilterSelected   = new Set();   // ad-type strings
        let statusFilterSelected = new Set();   // status strings (lowercased keys)
        // Distinct Status values seen in the current dataset — refilled
        // every time loadTable() returns. Used to drive the Status
        // dropdown's checkbox list.
        let statusValues = [];
        // Default behaviour: show only "active" status rows on the
        // very first table load. Once true, we never auto-tick again
        // so any explicit user choice (including clearing all) sticks.
        let defaultStatusApplied = false;

        // Same colour map formatStatusCell uses, exposed so the Status
        // filter can prefix each option with the matching dot.
        const STATUS_DOTS = {
            'active'         : '#16a34a',
            'inactive'       : '#ca8a04',
            'archived'       : '#dc2626',
            'not_delivering' : '#2563eb',
        };
        function statusKey(s) {
            return (s ?? '').toString().toLowerCase().replace(/[\s_-]+/g, '_');
        }
        function statusDotColor(s) {
            return STATUS_DOTS[statusKey(s)] || '#9ca3af';
        }

        // Composite filter — every active source AND-ed together
        // through a single Tabulator function filter. Mixing filter
        // objects with raw functions inside setFilter([...]) is
        // unreliable in Tabulator 6; a single combined predicate
        // sidesteps that and behaves identically across versions.
        function applyAllFilters() {
            if (!tabulator) return;

            const sbgtSel   = sbgtFilterSelected;
            const typeSel   = typeFilterSelected;
            const statusSel = statusFilterSelected;
            const term = (document.getElementById('faas-search')?.value || '')
                .toLowerCase().trim();

            // Fast path — no filters active → clear so Tabulator skips
            // the per-row predicate cost entirely.
            if (sbgtSel.size === 0 && typeSel.size === 0 && statusSel.size === 0 && !term) {
                tabulator.clearFilter(false);
                return;
            }

            tabulator.setFilter(function (row) {
                // Sbgt — exact-match by numeric value.
                if (sbgtSel.size > 0) {
                    const v = Number(row['Sbgt']);
                    if (!sbgtSel.has(v)) return false;
                }
                // Type (ad_type) — exact-match by full string.
                if (typeSel.size > 0) {
                    if (!typeSel.has(row['ad_type'])) return false;
                }
                // Status — normalised keys so "Not delivering",
                // "not_delivering", "Not-Delivering" all match.
                if (statusSel.size > 0) {
                    if (!statusSel.has(statusKey(row['Status']))) return false;
                }
                // Free-text search — match against any column whose
                // key isn't an internal/hidden one (prefix `_`).
                if (term) {
                    let hit = false;
                    for (const k in row) {
                        if (k.startsWith('_')) continue;
                        const cell = row[k];
                        if (cell != null && String(cell).toLowerCase().includes(term)) {
                            hit = true;
                            break;
                        }
                    }
                    if (!hit) return false;
                }
                return true;
            });
        }

        // Build / refresh the toolbar Sbgt filter dropdown. Each band
        // becomes a checkbox row with its band colour as a leading
        // dot, matching the colour painting in the table cells.
        function buildSbgtFilter() {
            const menu = document.getElementById('faasSbgtFilterMenu');
            if (!menu) return;
            // Wipe everything except the All / None header row, which
            // is the first <li> in the menu.
            const headerLi = menu.firstElementChild;
            menu.innerHTML = '';
            if (headerLi) menu.appendChild(headerLi);

            // Sort by sbgt desc so the "best" band sits at the top.
            const bands = (currentSbgtRule.bands || []).slice().sort(
                (a, b) => (Number(b.sbgt) || 0) - (Number(a.sbgt) || 0)
            );

            // Drop any selected values that no longer exist in the
            // current rule (band may have been removed in the modal).
            const valid = new Set(bands.map(b => Number(b.sbgt)));
            sbgtFilterSelected = new Set([...sbgtFilterSelected].filter(v => valid.has(v)));

            bands.forEach(b => {
                const li    = document.createElement('li');
                const label = document.createElement('label');
                label.style.cssText = 'display:flex;align-items:center;gap:8px;'
                                    + 'padding:4px 6px;cursor:pointer;user-select:none;'
                                    + 'white-space:nowrap;font-size:12px;';
                const cb = document.createElement('input');
                cb.type    = 'checkbox';
                cb.value   = String(b.sbgt);
                cb.checked = sbgtFilterSelected.has(Number(b.sbgt));
                cb.addEventListener('change', () => {
                    if (cb.checked) sbgtFilterSelected.add(Number(b.sbgt));
                    else            sbgtFilterSelected.delete(Number(b.sbgt));
                    applySbgtFilter();
                });

                const dot = document.createElement('span');
                dot.style.cssText = `display:inline-block;width:10px;height:10px;`
                                  + `border-radius:50%;background:${b.color || '#9ca3af'};`
                                  + `box-shadow:0 0 0 1px rgba(0,0,0,0.05);`;

                const text = document.createElement('span');
                text.textContent = `${b.sbgt}${b.label ? ' · ' + b.label : ''}`;

                label.appendChild(cb);
                label.appendChild(dot);
                label.appendChild(text);
                li.appendChild(label);
                menu.appendChild(li);
            });

            updateSbgtFilterLabel();
        }

        // Update the button text to reflect the current selection count.
        function updateSbgtFilterLabel() {
            const lbl = document.getElementById('faasSbgtFilterLabel');
            if (!lbl) return;
            const n = sbgtFilterSelected.size;
            if (n === 0)        lbl.textContent = 'Sbgt: all';
            else if (n <= 3)    lbl.textContent = 'Sbgt: ' + [...sbgtFilterSelected].sort((a,b)=>b-a).join(', ');
            else                lbl.textContent = `Sbgt: ${n} bands`;
        }

        // Sbgt selection changed — refresh label, recompose all filters.
        function applySbgtFilter() {
            updateSbgtFilterLabel();
            applyAllFilters();
        }

        // "All" → tick every band; "None" → clear all ticks.
        document.getElementById('faasSbgtFilterAll')?.addEventListener('click', function () {
            sbgtFilterSelected = new Set(
                (currentSbgtRule.bands || []).map(b => Number(b.sbgt))
            );
            buildSbgtFilter();
            applySbgtFilter();
        });
        document.getElementById('faasSbgtFilterClear')?.addEventListener('click', function () {
            sbgtFilterSelected = new Set();
            buildSbgtFilter();
            applySbgtFilter();
        });

        // ── Generic builder for Type / Status multi-select dropdowns ──
        // Each option is rendered as [checkbox] [colour-dot] [label].
        function buildCheckboxFilter(menuId, options, selectedSet, onChange) {
            const menu = document.getElementById(menuId);
            if (!menu) return;
            // Preserve the All/None header row (always the first <li>).
            const headerLi = menu.firstElementChild;
            menu.innerHTML = '';
            if (headerLi) menu.appendChild(headerLi);

            // Drop selections that no longer exist in the option list.
            const validValues = new Set(options.map(o => o.value));
            [...selectedSet].forEach(v => { if (!validValues.has(v)) selectedSet.delete(v); });

            options.forEach(opt => {
                const li    = document.createElement('li');
                const label = document.createElement('label');
                label.style.cssText = 'display:flex;align-items:center;gap:8px;'
                                    + 'padding:4px 6px;cursor:pointer;user-select:none;'
                                    + 'white-space:nowrap;font-size:12px;';
                const cb = document.createElement('input');
                cb.type    = 'checkbox';
                cb.value   = String(opt.value);
                cb.checked = selectedSet.has(opt.value);
                cb.addEventListener('change', () => {
                    if (cb.checked) selectedSet.add(opt.value);
                    else            selectedSet.delete(opt.value);
                    onChange();
                });
                const dot = document.createElement('span');
                dot.style.cssText = `display:inline-block;width:10px;height:10px;`
                                  + `border-radius:50%;background:${opt.color || '#9ca3af'};`
                                  + `box-shadow:0 0 0 1px rgba(0,0,0,0.05);`;
                const text = document.createElement('span');
                text.textContent = opt.label;
                label.append(cb, dot, text);
                li.appendChild(label);
                menu.appendChild(li);
            });
        }

        // ── Type filter ──────────────────────────────────────────────
        function buildTypeFilter() {
            const opts = (AD_TYPES || []).map(v => ({
                value: v,
                label: shortAdType(v),
                color: (AD_TYPE_COLORS[v] || {}).bg || '#9ca3af',
            }));
            buildCheckboxFilter('faasTypeFilterMenu', opts, typeFilterSelected, function () {
                updateTypeFilterLabel();
                applyAllFilters();
            });
            updateTypeFilterLabel();
        }
        function updateTypeFilterLabel() {
            const lbl = document.getElementById('faasTypeFilterLabel');
            if (!lbl) return;
            const n = typeFilterSelected.size;
            if (n === 0)     lbl.textContent = 'Type: all';
            else if (n <= 2) lbl.textContent = 'Type: ' + [...typeFilterSelected].map(shortAdType).join(', ');
            else             lbl.textContent = `Type: ${n} sel`;
        }
        document.getElementById('faasTypeFilterAll')?.addEventListener('click', function () {
            typeFilterSelected = new Set(AD_TYPES || []);
            buildTypeFilter();
            applyAllFilters();
        });
        document.getElementById('faasTypeFilterClear')?.addEventListener('click', function () {
            typeFilterSelected = new Set();
            buildTypeFilter();
            applyAllFilters();
        });

        // ── Status filter ────────────────────────────────────────────
        // Options come from whatever distinct Status values were in
        // the last loadTable() response, so the dropdown stays in
        // sync with what's actually in the dataset.
        function buildStatusFilter() {
            const opts = (statusValues || []).map(v => ({
                value: statusKey(v),    // key matches what applyAllFilters compares
                label: v,
                color: statusDotColor(v),
            }));
            buildCheckboxFilter('faasStatusFilterMenu', opts, statusFilterSelected, function () {
                updateStatusFilterLabel();
                applyAllFilters();
            });
            updateStatusFilterLabel();
        }
        function updateStatusFilterLabel() {
            const lbl = document.getElementById('faasStatusFilterLabel');
            if (!lbl) return;
            const n = statusFilterSelected.size;
            if (n === 0)     lbl.textContent = 'Stat: all';
            else if (n <= 2) lbl.textContent = 'Stat: ' + [...statusFilterSelected].join(', ');
            else             lbl.textContent = `Stat: ${n} sel`;
        }
        document.getElementById('faasStatusFilterAll')?.addEventListener('click', function () {
            statusFilterSelected = new Set((statusValues || []).map(statusKey));
            buildStatusFilter();
            applyAllFilters();
        });
        document.getElementById('faasStatusFilterClear')?.addEventListener('click', function () {
            statusFilterSelected = new Set();
            buildStatusFilter();
            applyAllFilters();
        });

        // Load once at page boot so the filter is usable before the
        // user opens the Rule modal.
        function fetchSbgtRule() {
            return fetch(SBGT_RULE_GET_URL, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(rule => {
                    currentSbgtRule = (rule && Array.isArray(rule.bands))
                        ? rule
                        : { bands: [] };
                    buildSbgtFilter();
                    return currentSbgtRule;
                })
                .catch(() => { currentSbgtRule = { bands: [] }; });
        }


        // Build one editable <tr> per band. Re-rendered whenever a band
        // is added / removed; individual field edits update
        // currentSbgtRule.bands in-place to avoid losing focus.
        function renderSbgtBands(bands) {
            const tbody = document.getElementById('sbgt-bands-body');
            if (!tbody) return;
            tbody.innerHTML = '';
            bands.forEach((band, i) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-muted small">${i + 1}</td>
                    <td><input type="text" class="form-control form-control-sm"
                               value="${(band.label ?? '').toString().replace(/"/g, '&quot;')}"
                               data-idx="${i}" data-field="label"></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <input type="color" class="form-control form-control-color form-control-sm"
                                   value="${band.color || '#6c757d'}" data-idx="${i}" data-field="color">
                            <span class="badge"
                                  style="background:${band.color || '#6c757d'};color:#fff;">${band.label || '—'}</span>
                        </div>
                    </td>
                    <td><input type="number" step="0.1" min="0"
                               class="form-control form-control-sm"
                               value="${band.acos_max ?? ''}"
                               data-idx="${i}" data-field="acos_max"></td>
                    <td><input type="number" step="1" min="0"
                               class="form-control form-control-sm"
                               value="${band.sbgt ?? ''}"
                               data-idx="${i}" data-field="sbgt"></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-remove-idx="${i}" title="Remove band">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>`;
                tbody.appendChild(tr);
            });

            // Wire field inputs → write back into currentSbgtRule.bands.
            tbody.querySelectorAll('input[data-idx]').forEach(inp => {
                inp.addEventListener('input', function () {
                    const idx = +this.dataset.idx;
                    const fld = this.dataset.field;
                    if (!currentSbgtRule.bands[idx]) return;
                    currentSbgtRule.bands[idx][fld] = (fld === 'acos_max' || fld === 'sbgt')
                        ? (this.value === '' ? '' : parseFloat(this.value))
                        : this.value;
                    // Refresh the colour preview chip when label/color change.
                    if (fld === 'label' || fld === 'color') {
                        const row = this.closest('tr');
                        const chip = row?.querySelector('.badge');
                        const band = currentSbgtRule.bands[idx];
                        if (chip) {
                            chip.style.background = band.color || '#6c757d';
                            chip.textContent      = band.label || '—';
                        }
                    }
                });
            });

            // Wire the per-row remove buttons.
            tbody.querySelectorAll('[data-remove-idx]').forEach(btn => {
                btn.addEventListener('click', function () {
                    const idx = +this.dataset.removeIdx;
                    currentSbgtRule.bands.splice(idx, 1);
                    renderSbgtBands(currentSbgtRule.bands);
                });
            });
        }

        // Load the persisted rule each time the modal opens, so two
        // tabs editing the rule don't overwrite each other on save.
        document.getElementById('sbgtRuleModal')?.addEventListener('show.bs.modal', function () {
            const errEl = document.getElementById('sbgt-rule-err');
            errEl?.classList.add('d-none');
            fetch(SBGT_RULE_GET_URL, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(rule => {
                    currentSbgtRule = (rule && Array.isArray(rule.bands))
                        ? rule
                        : { bands: [] };
                    renderSbgtBands(currentSbgtRule.bands);
                });
        });

        // "Add band" — appends a sane new row and re-renders.
        document.getElementById('sbgt-add-band-btn')?.addEventListener('click', function () {
            currentSbgtRule.bands.push({
                acos_max: 9999,
                sbgt:     1,
                label:    'New band',
                color:    '#6c757d',
            });
            renderSbgtBands(currentSbgtRule.bands);
        });

        // Persist + re-fetch table so the Sbgt column reflects new bands.
        document.getElementById('sbgt-rule-save-btn')?.addEventListener('click', function () {
            const errEl = document.getElementById('sbgt-rule-err');
            errEl?.classList.add('d-none');

            // Client-side guard: every band must have a numeric acos_max
            // and sbgt. Empty strings would be coerced to 0 server-side
            // and silently change behaviour.
            const cleaned = (currentSbgtRule.bands || []).map(b => ({
                acos_max: (b.acos_max === '' || b.acos_max === null || b.acos_max === undefined)
                    ? NaN : parseFloat(b.acos_max),
                sbgt:     (b.sbgt === '' || b.sbgt === null || b.sbgt === undefined)
                    ? NaN : parseInt(b.sbgt, 10),
                label:    (b.label || '').toString(),
                color:    (b.color || '#6c757d').toString(),
            }));
            if (!cleaned.length) {
                errEl.textContent = 'Add at least one band before saving.';
                errEl.classList.remove('d-none');
                return;
            }
            for (const b of cleaned) {
                if (!isFinite(b.acos_max) || !isFinite(b.sbgt)) {
                    errEl.textContent = 'Every band needs a numeric ACOS limit and Sbgt value.';
                    errEl.classList.remove('d-none');
                    return;
                }
            }

            const btn = this;
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';

            fetch(SBGT_RULE_SAVE_URL, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept':       'application/json',
                },
                body: JSON.stringify({ bands: cleaned }),
            })
                .then(r => r.json().then(j => ({ ok: r.ok, body: j })))
                .then(({ ok, body }) => {
                    if (!ok || body.success === false) {
                        errEl.textContent = body.error || 'Failed to save rule.';
                        errEl.classList.remove('d-none');
                        return;
                    }
                    currentSbgtRule = body.rule || currentSbgtRule;
                    bootstrap.Modal.getInstance(document.getElementById('sbgtRuleModal')).hide();
                    // Re-build the toolbar Sbgt filter so its options
                    // mirror whatever was just saved.
                    buildSbgtFilter();
                    // Reload table so the Sbgt column re-projects with
                    // the new bands.
                    loadTable();
                })
                .catch(err => {
                    errEl.textContent = 'Network error: ' + err.message;
                    errEl.classList.remove('d-none');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
        });

        // ── Push SBGT to Meta ─────────────────────────────────────────
        // Collects every visible row that has a campaign id AND a non-
        // empty Sbgt value, then POSTs in chunks. The backend calls Meta
        // once per campaign to update `daily_budget`.
        const SBGT_PUSH_URL = '/facebook-all-ads-sheet/push-sbgt';
        const SBGT_PUSH_CHUNK_SIZE = 20;

        function collectSbgtRowsToPush() {
            if (!tabulator) return [];
            const rows = tabulator.getData('active') || [];
            const out = [];
            rows.forEach(r => {
                const cid  = (r['CAMPAIGN ID'] ?? '').toString().trim();
                const sbgt = toNumber(r['Sbgt']);
                // Meta campaign IDs are large numeric strings — guard
                // against placeholders ("—", "N/A") that snuck in.
                if (cid && /^\d{6,}$/.test(cid) && sbgt > 0) {
                    out.push({ campaign_id: cid, sbgt: sbgt });
                }
            });
            return out;
        }

        function sbgtPushErrorMessage(body, status) {
            if (!body || typeof body !== 'object') {
                return status ? ('HTTP ' + status) : 'unknown error';
            }
            return body.error || body.message || 'unknown error';
        }

        async function pushSbgtInChunks(allRows, btn) {
            const chunks = [];
            for (let i = 0; i < allRows.length; i += SBGT_PUSH_CHUNK_SIZE) {
                chunks.push(allRows.slice(i, i + SBGT_PUSH_CHUNK_SIZE));
            }

            const aggregated = { pushed: 0, failed: 0, skipped: 0, results: [] };
            let done = 0;

            for (const chunk of chunks) {
                done += chunk.length;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Pushing '
                    + done + '/' + allRows.length + '…';

                const resp = await fetch(SBGT_PUSH_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept':       'application/json',
                    },
                    body: JSON.stringify({ campaigns: chunk }),
                });

                let body = {};
                try {
                    body = await resp.json();
                } catch (_) {
                    throw new Error('Server returned a non-JSON response (HTTP '
                        + resp.status + '). The request may have timed out — try fewer rows.');
                }

                if (!resp.ok || body.success === false) {
                    throw new Error(sbgtPushErrorMessage(body, resp.status));
                }

                aggregated.pushed  += body.pushed  ?? 0;
                aggregated.failed  += body.failed  ?? 0;
                aggregated.skipped += body.skipped ?? 0;
                aggregated.results.push(...(body.results || []));
            }

            return aggregated;
        }

        function renderSbgtResult(payload) {
            const summary = document.getElementById('sbgt-result-summary');
            const body    = document.getElementById('sbgt-result-body');
            if (summary) {
                summary.innerHTML =
                    `<span class="badge bg-success">Pushed: ${payload.pushed ?? 0}</span>` +
                    `<span class="badge bg-danger">Failed: ${payload.failed ?? 0}</span>` +
                    `<span class="badge bg-secondary">Skipped: ${payload.skipped ?? 0}</span>`;
            }
            if (body) {
                body.innerHTML = '';
                (payload.results || []).forEach((r, i) => {
                    const tr = document.createElement('tr');
                    const cls = r.status === 'pushed'
                        ? 'badge bg-success'
                        : (r.status === 'failed' ? 'badge bg-danger' : 'badge bg-secondary');
                    tr.innerHTML = `
                        <td class="text-muted small">${i + 1}</td>
                        <td><code>${r.campaign_id || ''}</code></td>
                        <td>${r.sbgt != null ? '$' + r.sbgt : '—'}</td>
                        <td><span class="${cls}">${r.status}</span></td>
                        <td class="small">${(r.reason || '').toString().replace(/</g, '&lt;')}</td>`;
                    body.appendChild(tr);
                });
            }
            const modalEl = document.getElementById('sbgtResultModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        document.getElementById('faasPushSbgtBtn')?.addEventListener('click', function () {
            const rows = collectSbgtRowsToPush();
            if (rows.length === 0) {
                alert('No campaigns with both a Campaign ID and an Sbgt value are currently visible.\n\n'
                    + 'Tip: upload Spend + Sales sheets, then come back and try again.');
                return;
            }
            if (!confirm(`Push suggested daily budget to ${rows.length} Meta campaign(s)?\n\n`
                + 'This updates live ad budgets on Meta — make sure the Sbgt rule is what you want.')) {
                return;
            }

            const btn = this;
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Pushing…';

            pushSbgtInChunks(rows, btn)
                .then(payload => renderSbgtResult(payload))
                .catch(err => alert('Push SBGT failed: ' + err.message))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
        });

        // ── Bulk CH update ───────────────────────────────────────────
        // Sets the CH column on every currently-visible (filtered) row.
        // Collects the distinct campaign ids on screen and POSTs them
        // with the chosen channel; the backend propagates per campaign.
        const BULK_CH_URL = '/facebook-all-ads-sheet/bulk-ch';

        // Distinct, valid campaign ids out of a list of row-data objects.
        function campaignIdsFromRows(rows) {
            const seen = new Set();
            (rows || []).forEach(r => {
                const cid = (r['CAMPAIGN ID'] ?? '').toString().trim();
                if (cid && /^\d{6,}$/.test(cid)) seen.add(cid);
            });
            return [...seen];
        }

        // Bulk CH targets the checked rows when any are selected, and
        // falls back to every visible (filtered) row otherwise.
        function collectBulkChTarget() {
            if (!tabulator) return { cids: [], scope: 'visible' };
            const selected = tabulator.getSelectedData() || [];
            if (selected.length > 0) {
                return { cids: campaignIdsFromRows(selected), scope: 'selected' };
            }
            return { cids: campaignIdsFromRows(tabulator.getData('active')), scope: 'visible' };
        }

        // Refresh the "N row(s) will be updated" hint + scope wording
        // whenever the modal opens, so it reflects the current selection
        // / filters.
        document.getElementById('bulkChModal')?.addEventListener('show.bs.modal', function () {
            const countEl = document.getElementById('bulkChCount');
            const scopeEl = document.getElementById('bulkChScope');
            const errEl   = document.getElementById('bulkChError');
            if (errEl) errEl.classList.add('d-none');
            const target = collectBulkChTarget();
            if (countEl) countEl.textContent = target.cids.length.toString();
            if (scopeEl) scopeEl.textContent = target.scope === 'selected'
                ? 'selected (checked) rows'
                : 'all visible rows';
        });

        document.getElementById('bulkChApplyBtn')?.addEventListener('click', function () {
            const errEl = document.getElementById('bulkChError');
            const ch    = document.getElementById('bulkChValue').value;
            const target = collectBulkChTarget();
            const cids   = target.cids;

            errEl.classList.add('d-none');
            if (cids.length === 0) {
                errEl.textContent = target.scope === 'selected'
                    ? 'None of the checked rows have a valid Campaign ID.'
                    : 'No campaigns with a valid Campaign ID are currently visible.';
                errEl.classList.remove('d-none');
                return;
            }

            const label = ch === '' ? 'clear CH on' : `set CH = "${ch}" for`;
            const scopeWord = target.scope === 'selected' ? 'selected' : 'visible';
            if (!confirm(`This will ${label} ${cids.length} ${scopeWord} campaign(s). Continue?`)) {
                return;
            }

            const btn = this;
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Applying…';

            fetch(BULK_CH_URL, {
                method:      'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept':       'application/json',
                },
                body: JSON.stringify({ ch: ch, campaign_ids: cids }),
            })
            .then(async r => {
                const data = await r.json().catch(() => ({}));
                if (!r.ok || !data.success) throw new Error(data.message || `HTTP ${r.status}`);
                return data;
            })
            .then(data => {
                bootstrap.Modal.getInstance(document.getElementById('bulkChModal')).hide();
                loadTable();
            })
            .catch(err => {
                errEl.textContent = 'Failed to update CH: ' + err.message;
                errEl.classList.remove('d-none');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = original;
            });
        });

        // ── Badge trend chart ────────────────────────────────────────
        // Each toolbar badge has data-metric/data-label; clicking opens
        // the chart modal and asks the backend for the daily series.
        // Same look & feel as the chart on /all-marketplace-master.
        const BADGE_HISTORY_URL = '/facebook-all-ads-sheet/badge-history';
        let badgeChartInstance = null;
        let activeBadgeMetric  = null;
        let activeBadgeLabel   = '';

        // Format a number per metric so chart labels and the side panel
        // use the same conventions as the badges (currency for $, % for
        // ratio metrics, plain integers otherwise).
        function fmtBadgeValue(metric, v) {
            if (v === null || v === undefined || isNaN(v)) return '—';
            if (metric === 'spend' || metric === 'sales') {
                return '$' + Math.round(v).toLocaleString('en-US');
            }
            if (metric === 'acos' || metric === 'ctr' || metric === 'cvr') {
                return Number(v).toFixed(1) + '%';
            }
            return Math.round(v).toLocaleString('en-US');
        }

        // Push the click handler — only ever bound once.
        document.querySelectorAll('.badge-chart-link').forEach(el => {
            el.addEventListener('click', function () {
                const metric = this.dataset.metric;
                const label  = this.dataset.label || metric.toUpperCase();
                openBadgeChart(metric, label);
            });
        });

        // Range dropdown re-loads the chart with the new window length.
        document.getElementById('badgeChartRange')?.addEventListener('change', function () {
            if (activeBadgeMetric) loadBadgeChart(activeBadgeMetric, activeBadgeLabel);
        });

        function openBadgeChart(metric, label) {
            activeBadgeMetric = metric;
            activeBadgeLabel  = label;
            const titleEl = document.getElementById('badgeChartTitle');
            const days    = parseInt(document.getElementById('badgeChartRange').value || '32', 10);
            if (titleEl) titleEl.textContent = `${label} (Rolling L${days})`;

            const modalEl = document.getElementById('badgeChartModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
            loadBadgeChart(metric, label);
        }

        function loadBadgeChart(metric, label) {
            const days = parseInt(document.getElementById('badgeChartRange').value || '32', 10);
            const titleEl = document.getElementById('badgeChartTitle');
            if (titleEl) titleEl.textContent = `${label} (Rolling L${days})`;

            // Limit chart to currently-visible campaigns so the badges
            // and the chart agree about scope. Empty list → backend
            // falls back to the global aggregate.
            const cids = [];
            if (tabulator) {
                (tabulator.getData('active') || []).forEach(r => {
                    const cid = (r['CAMPAIGN ID'] ?? '').toString().trim();
                    if (cid && /^\d{6,}$/.test(cid)) cids.push(cid);
                });
            }
            const params = new URLSearchParams({ metric, days: String(days) });
            if (cids.length) params.set('campaign_ids', cids.join(','));

            fetch(`${BADGE_HISTORY_URL}?${params.toString()}`, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(resp => {
                    const data = (resp && resp.data) || [];
                    renderBadgeChart(metric, data);
                })
                .catch(err => console.error('badge-history fetch failed:', err));
        }

        function renderBadgeChart(metric, data) {
            const canvas  = document.getElementById('badgeChartCanvas');
            const emptyEl = document.getElementById('badgeChartEmpty');
            if (!canvas) return;

            // Reset side-panel + tear down previous chart before
            // checking for emptiness so we never end up with stale
            // numbers from a different metric.
            if (badgeChartInstance) { badgeChartInstance.destroy(); badgeChartInstance = null; }
            ['badgeChartHighest','badgeChartMedian','badgeChartLowest'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '—';
            });

            if (!data.length) {
                canvas.style.display = 'none';
                emptyEl?.classList.remove('d-none');
                return;
            }
            canvas.style.display = '';
            emptyEl?.classList.add('d-none');

            const labels = data.map(d => d.date);
            const values = data.map(d => Number(d.value) || 0);

            const dataMin = Math.min(...values);
            const dataMax = Math.max(...values);
            const sorted  = [...values].sort((a, b) => a - b);
            const mid     = Math.floor(sorted.length / 2);
            const median  = sorted.length % 2 !== 0
                ? sorted[mid]
                : (sorted[mid - 1] + sorted[mid]) / 2;
            const range = (dataMax - dataMin) || 1;
            const yMin  = Math.max(0, dataMin - range * 0.1);
            const yMax  = dataMax + range * 0.1;

            // Side panel — same colour rules as /all-marketplace-master.
            const refRed = '#dc3545', refGray = '#6c757d', refGreen = '#198754';
            const setStat = (id, v) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.textContent = fmtBadgeValue(metric, v);
                el.style.color = (v === 0) ? refGreen : (v > 0 ? refRed : refGray);
            };
            setStat('badgeChartHighest', dataMax);
            setStat('badgeChartMedian',  median);
            setStat('badgeChartLowest',  dataMin);

            // Per-day dot colours: green = improved vs prev day,
            // red = worse, grey = flat. Inverted for "lower is better"
            // metrics (Acos — same convention as the parent page).
            const invertedMetrics = ['acos'];
            const isInverted = invertedMetrics.includes(metric);
            const dotColors = values.map((v, i) => {
                if (i === 0) return refGray;
                if (isInverted) {
                    return v < values[i - 1] ? '#28a745'
                         : v > values[i - 1] ? '#dc3545'
                         : refGray;
                }
                return v > values[i - 1] ? '#28a745'
                     : v < values[i - 1] ? '#dc3545'
                     : refGray;
            });

            // Plugin: dashed median line.
            const medianLinePlugin = {
                id: 'medianLine',
                afterDraw(chart) {
                    const yScale = chart.scales.y;
                    const xScale = chart.scales.x;
                    const ctx = chart.ctx;
                    const yPixel = yScale.getPixelForValue(median);
                    ctx.save();
                    ctx.setLineDash([6, 4]);
                    ctx.strokeStyle = '#6c757d';
                    ctx.lineWidth = 1.2;
                    ctx.beginPath();
                    ctx.moveTo(xScale.left, yPixel);
                    ctx.lineTo(xScale.right, yPixel);
                    ctx.stroke();
                    ctx.restore();
                }
            };
            // Plugin: value label above each dot, alternating offset
            // so labels don't clobber each other on dense series.
            const labelColors = values.map(v => v === 0 ? refGreen : (v > 0 ? refRed : refGray));
            const valueLabelsPlugin = {
                id: 'valueLabels',
                afterDatasetsDraw(chart) {
                    const meta = chart.getDatasetMeta(0);
                    const ctx  = chart.ctx;
                    ctx.save();
                    ctx.font         = 'bold 11px Inter, system-ui, sans-serif';
                    ctx.textAlign    = 'center';
                    ctx.textBaseline = 'bottom';
                    meta.data.forEach((point, i) => {
                        const offY = (i % 2 === 0) ? -10 : -20;
                        ctx.fillStyle = labelColors[i];
                        ctx.fillText(fmtBadgeValue(metric, values[i]), point.x, point.y + offY);
                    });
                    ctx.restore();
                }
            };

            badgeChartInstance = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: activeBadgeLabel,
                        data: values,
                        backgroundColor: 'rgba(108,117,125,0.08)',
                        borderColor:     '#adb5bd',
                        borderWidth:     1.5,
                        fill:            true,
                        tension:         0.3,
                        pointRadius:     3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: dotColors,
                        pointBorderColor:     dotColors,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 24, right: 16, bottom: 12, left: 16 } },
                    plugins: {
                        legend:  { display: false },
                        tooltip: {
                            callbacks: { label: (ctx) => fmtBadgeValue(metric, ctx.parsed.y) }
                        },
                    },
                    scales: {
                        y: { min: yMin, max: yMax,
                             ticks: { callback: (v) => fmtBadgeValue(metric, v) } },
                        x: { ticks: { autoSkip: false, maxRotation: 60, minRotation: 45 } },
                    },
                },
                plugins: [medianLinePlugin, valueLabelsPlugin],
            });
        }

        // Initial load — pull saved column visibility first so the very
        // first table render already respects it (no flash of hidden cols).
        // ── Campaign Audit modal ─────────────────────────────────────
        // Click handler: any "Audit" button (formatAuditCell) opens
        // the modal seeded with the latest audit + history. Save
        // POSTs to /audit and refreshes just the row in-place so the
        // table doesn't have to be reloaded.
        const AUDIT_GET_URL  = '/facebook-all-ads-sheet/audit';
        const AUDIT_SAVE_URL = '/facebook-all-ads-sheet/audit';
        let auditCurrentCid  = '';
        let auditChecklistCache = [];   // mirrors AUDIT_CHECKLIST from server
        let auditCustomSeq   = 0;       // counter for client-side custom check keys

        // ── Copy campaign name to clipboard (icon in the name cell) ─────
        function faasCopyText(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(text);
            }
            return new Promise((resolve, reject) => {
                try {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.focus(); ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    resolve();
                } catch (err) { reject(err); }
            });
        }
        document.addEventListener('click', function (e) {
            const icon = e.target.closest && e.target.closest('.faas-copy-name');
            if (!icon) return;
            e.stopPropagation();
            e.preventDefault();
            const tmp = document.createElement('textarea');
            tmp.innerHTML = icon.getAttribute('data-copy') || '';
            faasCopyText(tmp.value).then(() => {
                const prev = icon.className;
                icon.className = 'fas fa-check faas-copy-name';
                icon.style.color = '#22c55e';
                setTimeout(() => { icon.className = prev; icon.style.color = '#94a3b8'; }, 1000);
            }).catch(() => {});
        });

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-audit-cid]');
            if (!btn) return;
            e.preventDefault();
            const cid = btn.dataset.auditCid;
            // Look up the row by campaign id so the modal title can
            // include the campaign name without another query.
            let name = '';
            if (tabulator) {
                const match = (tabulator.getData() || [])
                    .find(r => (r['CAMPAIGN ID'] ?? '').toString() === cid);
                if (match) name = match['Campaign name'] || '';
            }
            openAuditModal(cid, name);
        });

        function openAuditModal(cid, name) {
            auditCurrentCid = cid;
            document.getElementById('auditCampaignName').textContent = name || '';
            document.getElementById('auditCampaignId').textContent   = cid;
            document.getElementById('auditError')?.classList.add('d-none');
            document.getElementById('auditComments').value = '';
            auditCustomSeq = 0;
            const cl = document.getElementById('auditCustomLabel');
            const cw = document.getElementById('auditCustomWeight');
            if (cl) cl.value = '';
            if (cw) cw.value = '5';
            // Empty state until the fetch completes.
            document.getElementById('auditChecklistBody').innerHTML =
                '<tr><td colspan="5" class="text-center small text-muted py-3">Loading…</td></tr>';

            fetch(`${AUDIT_GET_URL}?campaign_id=${encodeURIComponent(cid)}`,
                { credentials: 'same-origin' })
                .then(r => r.json())
                .then(resp => {
                    if (!resp.success) throw new Error(resp.error || 'Failed to load audit.');
                    auditChecklistCache = resp.checklist || [];
                    const ticked = (resp.latest && resp.latest.checks) || {};
                    const notes  = (resp.latest && resp.latest.notes)  || {};
                    const custom = (resp.latest && resp.latest.custom_items) || [];
                    renderAuditChecklist(auditChecklistCache, ticked, notes);
                    custom.forEach(ci => appendAuditCustomRow(ci));
                    document.getElementById('auditComments').value =
                        (resp.latest && resp.latest.comments) || '';
                    renderAuditHistory(resp.history || []);
                    recalcAuditScore();
                })
                .catch(err => {
                    const e = document.getElementById('auditError');
                    e.textContent = err.message;
                    e.classList.remove('d-none');
                });

            const modalEl = document.getElementById('auditModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }

        function escapeAuditHtml(s) {
            return (s ?? '').toString()
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function renderAuditChecklist(items, ticked, notes) {
            const tbody = document.getElementById('auditChecklistBody');
            tbody.innerHTML = '';
            notes = notes || {};
            items.forEach(it => {
                const tr = document.createElement('tr');
                const checked = ticked && ticked[it.key] ? 'checked' : '';
                const note    = escapeAuditHtml(notes[it.key] || '');
                tr.innerHTML = `
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input"
                               data-audit-key="${it.key}"
                               data-audit-weight="${it.weight}" ${checked}>
                    </td>
                    <td>${escapeAuditHtml(it.label)}</td>
                    <td>
                        <input type="text" class="form-control form-control-sm audit-note-input"
                               data-audit-note-key="${it.key}"
                               value="${note}" placeholder="Add a note…">
                    </td>
                    <td class="text-end fw-semibold text-muted">${it.weight}</td>
                    <td></td>`;
                tbody.appendChild(tr);
            });
            tbody.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.addEventListener('change', recalcAuditScore);
            });
        }

        // Append a single manually-added custom check row. `item` may be
        // undefined (a brand-new blank row using the Add inputs) or a saved
        // { key, label, weight, checked, note } object loaded from history.
        function appendAuditCustomRow(item) {
            const tbody = document.getElementById('auditChecklistBody');
            if (!tbody) return;
            item = item || {};
            const key     = item.key || ('custom_' + (++auditCustomSeq));
            const label   = escapeAuditHtml(item.label || '');
            const weight  = Number(item.weight) || 0;
            const note    = escapeAuditHtml(item.note || '');
            const checked = item.checked ? 'checked' : '';
            const tr = document.createElement('tr');
            tr.dataset.auditCustom = '1';
            tr.innerHTML = `
                <td class="text-center">
                    <input type="checkbox" class="form-check-input"
                           data-audit-key="${key}"
                           data-audit-weight="${weight}" ${checked}>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm audit-custom-label"
                           value="${label}" placeholder="Custom check…">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm audit-note-input"
                           value="${note}" placeholder="Add a note…">
                </td>
                <td class="text-end">
                    <input type="number" min="0" step="1"
                           class="form-control form-control-sm text-end audit-custom-weight"
                           style="width:70px;display:inline-block;" value="${weight}">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 audit-custom-remove"
                            title="Remove this check"><i class="fas fa-times"></i></button>
                </td>`;
            tbody.appendChild(tr);
            const cb = tr.querySelector('input[type="checkbox"]');
            cb.addEventListener('change', recalcAuditScore);
            const wt = tr.querySelector('.audit-custom-weight');
            wt.addEventListener('input', function () {
                cb.dataset.auditWeight = Number(this.value) || 0;
                recalcAuditScore();
            });
            tr.querySelector('.audit-custom-remove').addEventListener('click', function () {
                tr.remove();
                recalcAuditScore();
            });
            return tr;
        }

        function recalcAuditScore() {
            let earned = 0, total = 0;
            document.querySelectorAll('#auditChecklistBody input[type="checkbox"]')
                .forEach(cb => {
                    const w = Number(cb.dataset.auditWeight) || 0;
                    total += w;
                    if (cb.checked) earned += w;
                });
            const pct = total > 0 ? Math.round((earned / total) * 100) : 0;
            document.getElementById('auditLiveScore').textContent  = pct + '%';
            document.getElementById('auditLiveEarned').textContent = earned;
            document.getElementById('auditLiveTotal').textContent  = total;
        }

        // "Add custom check" button — pulls the label/points inputs, appends
        // a row, persists the audit immediately, then clears the inputs ready
        // for the next entry (the modal stays open).
        document.getElementById('auditAddCustomBtn')?.addEventListener('click', function () {
            const labelEl  = document.getElementById('auditCustomLabel');
            const weightEl = document.getElementById('auditCustomWeight');
            const label = (labelEl.value || '').trim();
            if (!label) {
                labelEl.focus();
                labelEl.classList.add('is-invalid');
                setTimeout(() => labelEl.classList.remove('is-invalid'), 1200);
                return;
            }
            appendAuditCustomRow({
                label:   label,
                weight:  Math.max(0, Number(weightEl.value) || 0),
                checked: true,
            });
            labelEl.value = '';
            weightEl.value = '5';
            recalcAuditScore();
            performAuditSave({ button: this, closeOnSuccess: false })
                .then(() => labelEl.focus());
        });

        document.getElementById('auditCustomLabel')?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('auditAddCustomBtn')?.click();
            }
        });

        function renderAuditHistory(rows) {
            const body  = document.getElementById('auditHistoryBody');
            const empty = document.getElementById('auditHistoryEmpty');
            const wrap  = document.getElementById('auditHistoryWrap');
            body.innerHTML = '';
            if (!rows.length) {
                empty.classList.remove('d-none');
                wrap.classList.add('d-none');
                return;
            }
            empty.classList.add('d-none');
            wrap.classList.remove('d-none');
            rows.forEach(r => {
                const score = Number(r.score_pct) || 0;
                const colour = score >= 80 ? '#16a34a'
                             : score >= 50 ? '#ca8a04'
                             : '#dc2626';
                const tr = document.createElement('tr');
                const safeC = (r.comments || '').toString().replace(/</g, '&lt;');
                tr.innerHTML = `
                    <td class="small">${r.audited_at || '—'}</td>
                    <td class="small">${(r.audited_by_name || '—').toString().replace(/</g, '&lt;')}</td>
                    <td class="text-end small fw-bold" style="color:${colour}">${score}%</td>
                    <td class="small text-muted">${safeC || '—'}</td>`;
                body.appendChild(tr);
            });
        }

        // Collect the modal state + POST it. `opts.closeOnSuccess` hides the
        // modal afterwards (the Save button); the Add button persists without
        // closing so more custom checks can be added. `opts.button` is shown
        // in a spinner state while the request is in flight.
        function performAuditSave(opts) {
            opts = opts || {};
            const checks = {};
            const notes  = {};
            const customItems = [];
            document.querySelectorAll('#auditChecklistBody tr').forEach(tr => {
                const cb = tr.querySelector('input[type="checkbox"]');
                if (!cb) return;
                const noteEl = tr.querySelector('.audit-note-input');
                const note   = noteEl ? (noteEl.value || '').trim() : '';
                if (tr.dataset.auditCustom === '1') {
                    const labelEl  = tr.querySelector('.audit-custom-label');
                    const weightEl = tr.querySelector('.audit-custom-weight');
                    const label = labelEl ? (labelEl.value || '').trim() : '';
                    if (!label) return; // skip blank custom rows
                    customItems.push({
                        key:     cb.dataset.auditKey,
                        label:   label,
                        weight:  Math.max(0, Number(weightEl ? weightEl.value : 0) || 0),
                        checked: !!cb.checked,
                        note:    note,
                    });
                } else {
                    checks[cb.dataset.auditKey] = !!cb.checked;
                    if (note) notes[cb.dataset.auditKey] = note;
                }
            });
            const comments = document.getElementById('auditComments').value || '';
            const btn = opts.button || null;
            const original = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';
            }

            // Look up the row's campaign name once more (might have
            // changed if the user switched filters between open + save).
            let campaignName = '';
            if (tabulator) {
                const match = (tabulator.getData() || [])
                    .find(r => (r['CAMPAIGN ID'] ?? '').toString() === auditCurrentCid);
                if (match) campaignName = match['Campaign name'] || '';
            }

            return fetch(AUDIT_SAVE_URL, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept':       'application/json',
                },
                body: JSON.stringify({
                    campaign_id:   auditCurrentCid,
                    campaign_name: campaignName,
                    checks:        checks,
                    notes:         notes,
                    custom_items:  customItems,
                    comments:      comments,
                }),
            })
                .then(r => r.json().then(j => ({ ok: r.ok, body: j })))
                .then(({ ok, body }) => {
                    if (!ok || body.success === false) {
                        const e = document.getElementById('auditError');
                        e.textContent = body.error || 'Failed to save.';
                        e.classList.remove('d-none');
                        return;
                    }
                    document.getElementById('auditError')?.classList.add('d-none');
                    // Patch the corresponding Tabulator row in place so
                    // the Audit / History columns repaint without a
                    // full table reload.
                    if (tabulator) {
                        const row = tabulator.getRows().find(r =>
                            (r.getData()['CAMPAIGN ID'] ?? '').toString() === auditCurrentCid);
                        if (row) {
                            row.update({
                                _audit_score:    body.score_pct,
                                _audit_at:       body.audited_at,
                                _audit_by:       body.audited_by_name,
                                _audit_comments: comments,
                            });
                            row.reformat();
                        }
                    }
                    if (opts.closeOnSuccess) {
                        bootstrap.Modal.getInstance(document.getElementById('auditModal'))?.hide();
                    }
                })
                .catch(err => {
                    const e = document.getElementById('auditError');
                    e.textContent = 'Network error: ' + err.message;
                    e.classList.remove('d-none');
                })
                .finally(() => {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = original;
                    }
                });
        }

        document.getElementById('auditSaveBtn')?.addEventListener('click', function () {
            performAuditSave({ button: this, closeOnSuccess: true });
        });

        // ── Export (filtered rows + visible columns) ───────────────────
        function faasColumnExportTitle(col) {
            const raw = col.getDefinition().title || col.getField() || '';
            const plain = String(raw).replace(/<[^>]*>/g, '').trim();
            return plain || col.getField();
        }

        function faasVisibleExportColumns() {
            if (!tabulator) return [];
            return tabulator.getColumns()
                .filter(col => col.isVisible())
                .map(col => ({
                    field: col.getField(),
                    title: faasColumnExportTitle(col),
                }))
                .filter(c => c.field && ! c.field.startsWith('_'));
        }

        function faasExportCellValue(row, field) {
            if (field === 'Audit') {
                return row._audit_score != null ? `${row._audit_score}%` : '';
            }
            if (field === 'History') {
                if (! row._audit_at) return '';
                const d = new Date(String(row._audit_at).replace(' ', 'T'));
                const when = isNaN(d)
                    ? row._audit_at
                    : d.toLocaleDateString('en-US', { month: 'short', day: '2-digit' });
                return `${when} · ${row._audit_by || '—'}`;
            }
            const v = row[field];
            if (v === null || v === undefined) return '';
            return String(v);
        }

        function faasCsvEscapeCell(v) {
            const s = String(v ?? '').replace(/"/g, '""');
            return /[",\r\n]/.test(s) ? `"${s}"` : s;
        }

        function faasExportFilename(ext) {
            // Compose CH lens + PAGE_TYPE so the export filename reflects the exact view
            // (e.g. /facebook-ads/video → facebook_fb_video_ads_…csv,
            //       /facebook-ads      → facebook_fb_ads_…csv).
            const chSlug   = CH_FILTER ? `${CH_FILTER.toLowerCase()}_` : '';
            const typeSlug = PAGE_TYPE === 'all' ? (CH_FILTER ? '' : 'all_') : `${PAGE_TYPE}_`;
            const pageSlug = (chSlug + typeSlug + 'ads').replace(/_+/g, '_').replace(/^_|_$/g, '');
            const dateStr  = new Date().toISOString().slice(0, 10);
            return `facebook_${pageSlug}_${dateStr}.${ext}`;
        }

        function exportFaasData(format) {
            if (! tabulator) {
                alert('Table not loaded yet.');
                return;
            }

            const rows = tabulator.getData('active');
            if (! rows.length) {
                alert('No data to export.');
                return;
            }

            const columns = faasVisibleExportColumns();
            if (! columns.length) {
                alert('No visible columns to export.');
                return;
            }

            if (format === 'csv') {
                const header = columns.map(c => faasCsvEscapeCell(c.title));
                const lines  = [header.join(',')];
                rows.forEach(row => {
                    lines.push(columns.map(c => faasCsvEscapeCell(faasExportCellValue(row, c.field))).join(','));
                });
                const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
                const a    = document.createElement('a');
                a.href     = URL.createObjectURL(blob);
                a.download = faasExportFilename('csv');
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(a.href);
                return;
            }

            if (format === 'xlsx') {
                if (typeof XLSX === 'undefined') {
                    alert('Excel export is unavailable — reload the page and try again.');
                    return;
                }
                const exportData = rows.map(row => {
                    const obj = {};
                    columns.forEach(c => {
                        obj[c.title] = faasExportCellValue(row, c.field);
                    });
                    return obj;
                });
                const ws = XLSX.utils.json_to_sheet(exportData);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Facebook Ads');
                XLSX.writeFile(wb, faasExportFilename('xlsx'));
            }
        }

        document.getElementById('faasExportCsvBtn')?.addEventListener('click', () => exportFaasData('csv'));
        document.getElementById('faasExportExcelBtn')?.addEventListener('click', () => exportFaasData('xlsx'));

        // Type filter is driven by AD_TYPES (static for this page) so
        // it can be built immediately, before the first data fetch.
        buildTypeFilter();

        fetchColumnVisibility()
            .then(() => fetchSbgtRule())   // populates the Sbgt filter dropdown
            .then(() => loadBatches())
            .then(() => loadTable());
    })();
</script>
@endsection
