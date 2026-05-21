@php
    /**
     * This blade powers three pages — All / Video / Carousal — distinguished
     * by the controller-supplied `$pageType` ('all' | 'video' | 'carousal').
     * The Video and Carousal pages restrict the Ad Type dropdown and pass
     * `?type=…` to the data endpoint so the server filters server-side.
     */
    $pageType        = $pageType        ?? 'all';
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

        /* ── Sum badges (Impressions / Clicks / …) ────────────────────
           Solid-pill style. Each metric gets its own colour so users
           can scan the strip at a glance. flex-shrink:0 keeps each
           badge intact when the strip overflows horizontally — it just
           scrolls inside its container instead of squashing. */
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
        }
        /* Compact title so the badge strip has more horizontal room. */
        .faas-toolbar-title { font-size: 1rem; flex-shrink: 0; }
        .faas-stat-badge--impr  { background: #4c7ed8; }   /* blue    */
        .faas-stat-badge--clk   { background: #f59e0b; }   /* amber   */
        .faas-stat-badge--spend { background: #ef4444; }   /* red     */
        .faas-stat-badge--sales { background: #16a34a; }   /* green   */
        .faas-stat-badge--sold  { background: #8b5cf6; }   /* purple  */
        .faas-stat-badge--acos  { background: #ea580c; }   /* orange  */
        .faas-stat-badge--ctr   { background: #0891b2; }   /* cyan    */
        .faas-stat-badge--cvr   { background: #db2777; }   /* pink    */
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
                        <h5 class="mb-0 faas-toolbar-title">
                            <i class="fab fa-facebook-f me-1 text-primary"></i>
                            Imported Rows
                        </h5>
                        {{-- Live sums of the IMPRESSIONS / CLICKS columns
                             across whatever rows are currently visible
                             (after search / header filters). Updated by
                             updateMetricBadges() in the script below. --}}
                        <span id="faasImpressionsBadge" class="faas-stat-badge faas-stat-badge--impr"
                              title="Sum of IMPRESSIONS column for the currently-visible rows">Impressions:<span id="faasImpressionsValue">0</span></span>
                        <span id="faasClicksBadge" class="faas-stat-badge faas-stat-badge--clk"
                              title="Sum of CLICKS column for the currently-visible rows">Clicks:<span id="faasClicksValue">0</span></span>
                        <span id="faasSpendBadge" class="faas-stat-badge faas-stat-badge--spend"
                              title="Sum of SPEND column for the currently-visible rows">Spend:<span id="faasSpendValue">$0</span></span>
                        <span id="faasSalesBadge" class="faas-stat-badge faas-stat-badge--sales"
                              title="Sum of SALES column for the currently-visible rows">Sales:<span id="faasSalesValue">$0</span></span>
                        <span id="faasSoldBadge" class="faas-stat-badge faas-stat-badge--sold"
                              title="Sum of SOLD column for the currently-visible rows">Sold:<span id="faasSoldValue">0</span></span>
                        <span id="faasAcosBadge" class="faas-stat-badge faas-stat-badge--acos"
                              title="ACOS = (Σ Spend / Σ Sales) × 100">Acos:<span id="faasAcosValue">0%</span></span>
                        <span id="faasCtrBadge" class="faas-stat-badge faas-stat-badge--ctr"
                              title="CTR = (Σ Clicks / Σ Impressions) × 100">CTR:<span id="faasCtrValue">0%</span></span>
                        <span id="faasCvrBadge" class="faas-stat-badge faas-stat-badge--cvr"
                              title="CVR = (Σ Sold / Σ Clicks) × 100">CVR:<span id="faasCvrValue">0%</span></span>
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
                                <i class="fas fa-eye me-1"></i> Columns
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
                            <i class="fas fa-sliders-h me-1"></i>Sbgt Rule
                        </button>

                        {{-- Pushes each visible row's Sbgt as the new
                             daily_budget on the matching Meta campaign.
                             Mirrors the eBay "Push SBID" button. --}}
                        <button type="button"
                                id="faasPushSbgtBtn"
                                class="btn btn-sm btn-warning text-dark"
                                title="Update each campaign's daily budget on Meta to its Sbgt value">
                            <i class="fas fa-cloud-upload-alt me-1"></i>Push SBGT
                        </button>

                        <button type="button"
                                class="btn btn-sm btn-success"
                                data-bs-toggle="modal"
                                data-bs-target="#faasUploadModal">
                            <i class="fas fa-cloud-upload-alt me-1"></i>
                            Upload Sheet
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                <div id="faas-table-wrapper" style="height: calc(100vh - 220px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text"
                               id="faas-search"
                               class="form-control form-control-sm"
                               placeholder="Search across all columns…">
                    </div>
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
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                        || document.querySelector('input[name="_token"]')?.value
                        || '';

        // Page-specific config (set by the controller).
        const PAGE_TYPE = @json($pageType);
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

        // Tabulator column visibility is shared across users via the
        // channel_tabulator_column_settings table (channel_name +
        // visibility JSON). Same endpoints as /ebay3-tabulator.
        const FAAS_COLUMN_CHANNEL    = 'facebook_all_ads_sheet';
        const COLUMN_VISIBILITY_URL  = '/tabulator-column-visibility';
        // In-memory copy of the saved map so every table rebuild can
        // re-apply it without an extra round trip.
        let savedColumnVisibility = {};

        let tabulator = null;

        // Render the Ad Type cell as a coloured pill so values are scannable.
        function formatAdTypeCell(cell) {
            const v = cell.getValue();
            if (!v) {
                return '<span class="text-muted small">— Select —</span>';
            }
            const c = AD_TYPE_COLORS[v] || { bg: '#e5e7eb', fg: '#374151' };
            return `<span style="display:inline-block;padding:2px 10px;border-radius:999px;`
                 + `background:${c.bg};color:${c.fg};font-size:0.75rem;font-weight:600;">${v}</span>`;
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
                        'Acos', 'Sbgt', 'IMPRESSIONS', 'CLICKS', 'CTR',
                        'SPEND', 'SALES', 'SOLD', 'CVR',
                    ]);
                    const cols = (resp.columns || []).map(c => ({
                        title:        c.title,
                        field:        c.field,
                        headerFilter: NO_HEADER_FILTER.has(c.field) ? false : 'input',
                        headerSort:   true,
                        widthGrow:    1,
                        minWidth:     120,
                        formatter:    'plaintext',
                    }));
                    // Prepend row index + Ad Type dropdown columns.
                    cols.unshift(
                        {
                            title: '#',
                            field: '_row_index',
                            width: 60,
                            headerSort: true,
                            hozAlign: 'center',
                        },
                        {
                            title:        'Ad Type',
                            field:        'ad_type',
                            width:        180,
                            headerFilter: 'list',
                            headerFilterParams: {
                                values:      { '': '— all —', ...Object.fromEntries(AD_TYPES.map(v => [v, v])) },
                                clearable:   true,
                            },
                            editor:       'list',
                            editorParams: {
                                values:        ['', ...AD_TYPES],
                                clearable:     true,
                                autocomplete:  true,
                                listOnEmpty:   true,
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
            if (tabulator) {
                // 'active' = rows after filters / search are applied,
                // which is what users see in the table.
                const rows = tabulator.getData('active') || [];
                rows.forEach(r => {
                    imprSum  += toNumber(r.IMPRESSIONS);
                    clkSum   += toNumber(r.CLICKS);
                    spendSum += toNumber(r.SPEND);
                    salesSum += toNumber(r.SALES);
                    soldSum  += toNumber(r.SOLD);
                });
            }
            // Match column formatters: Spend & Sales are whole-dollar
            // amounts (controller already rounds them). Sold is a count.
            imprEl.textContent  = imprSum.toLocaleString();
            clkEl.textContent   = clkSum.toLocaleString();
            spendEl.textContent = '$' + Math.round(spendSum).toLocaleString();
            salesEl.textContent = '$' + Math.round(salesSum).toLocaleString();
            soldEl.textContent  = soldSum.toLocaleString();
            // Ratios use the same decimals the controller passes to
            // divPct(): Acos & CTR → 0 dp, CVR → 2 dp (default).
            acosEl.textContent  = divPct(spendSum, salesSum, 0);
            ctrEl.textContent   = divPct(clkSum,   imprSum,  0);
            cvrEl.textContent   = divPct(soldSum,  clkSum);
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
            input.oninput = function () {
                const v = (this.value || '').toLowerCase().trim();
                if (!v) { tabulator.clearFilter(); return; }
                tabulator.setFilter(function (row) {
                    for (const k in row) {
                        if (k.startsWith('_')) continue;
                        const cell = row[k];
                        if (cell != null && String(cell).toLowerCase().includes(v)) return true;
                    }
                    return false;
                });
            };
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
        // empty Sbgt value, then POSTs them in one batch. The backend
        // calls Meta once per campaign to update `daily_budget`.
        const SBGT_PUSH_URL = '/facebook-all-ads-sheet/push-sbgt';

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

            fetch(SBGT_PUSH_URL, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept':       'application/json',
                },
                body: JSON.stringify({ campaigns: rows }),
            })
                .then(r => r.json().then(j => ({ ok: r.ok, body: j })))
                .then(({ ok, body }) => {
                    if (!ok || body.success === false) {
                        alert('Push SBGT failed: ' + (body.error || 'unknown error'));
                        return;
                    }
                    renderSbgtResult(body);
                })
                .catch(err => alert('Network error: ' + err.message))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
        });

        // Initial load — pull saved column visibility first so the very
        // first table render already respects it (no flash of hidden cols).
        fetchColumnVisibility()
            .then(() => loadBatches())
            .then(() => loadTable());
    })();
</script>
@endsection
