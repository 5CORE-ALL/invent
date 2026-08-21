@extends('layouts.vertical', ['title' => 'Purchasing Power - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter { display: none !important; }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title { padding-right: 0px !important; }
        .tabulator-paginator label { margin-right: 5px; }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Purchasing Power - Analytics',
        'sub_title'  => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="inventory-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">All Status</option>
                        <option value="REQ" selected>REQ Only</option>
                        <option value="NR">NR Only</option>
                    </select>

                    <div class="d-flex flex-column gap-1" style="width: auto;" title="CVR = PP L30 ÷ OV L30">
                        <select id="gpft-filter" class="form-select form-select-sm" style="width: auto;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40-50">40-50%</option>
                            <option value="50plus">Above 50%</option>
                        </select>
                        <select id="cvr-filter" class="form-select form-select-sm" style="width: auto;">
                            <option value="all">All CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0-3">0-3%</option>
                            <option value="3-7">3-7%</option>
                            <option value="7-13">7-13%</option>
                            <option value="13plus">13%+</option>
                        </select>
                    </div>

                    {{-- Sold dropdown (mirrors Amazon tabulator + /doba + /shopify-b2c + /macys).
                         Backed by `PP L30`:
                           all  → no filter
                           sold → PP L30 > 0
                           zero → PP L30 = 0
                         Single source of truth — #zero-sold-count-badge / #more-sold-count-badge
                         click handlers just toggle this dropdown so badges + dropdown stay synced. --}}
                    <select id="sold-filter" class="form-select form-select-sm" style="width: auto;"
                            title="Filter by PP L30 sold quantity">
                        <option value="all">Sold</option>
                        <option value="sold">Sold &gt; 0</option>
                        <option value="zero">0 Sold</option>
                    </select>

                    <select id="roi-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">ROI%</option>
                        <option value="lt40">&lt; 40%</option>
                        <option value="40-75">40–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="gt125">125%+</option>
                    </select>

                    <select id="dil-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="all">All DIL%</option>
                        <option value="red">Red (&lt;16.7%)</option>
                        <option value="yellow">Yellow (16.7-25%)</option>
                        <option value="green">Green (25-50%)</option>
                        <option value="pink">Pink (50%+)</option>
                    </select>

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;"></ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>
                    <button id="export-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-file-excel"></i> Export CSV
                    </button>
                    <button id="sugg-amz-prc-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-copy"></i> Sugg Amz Prc
                    </button>
                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-arrow-down"></i> Decrease Mode
                    </button>
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-arrow-up"></i> Increase Mode
                    </button>
                    <button id="same-price-btn" class="btn btn-sm btn-info" title="Apply ONE price (entered in the box) to every selected SKU">
                        <i class="fas fa-equals"></i> Same Price Mode
                    </button>
                    <button type="button" id="pp-rule-btn" class="btn btn-sm btn-outline-dark"
                        title="Price rules: Dil %, PP sold qty, Discount % → SPRICE = (STD × (1−Disc%)) − Ship">
                        <i class="fas fa-sliders-h"></i> Rule
                    </button>

                    {{-- Target ROI% bulk control — back-solves S PRC for selected rows so SROI = Target ROI%.
                         Formula: sprice = (LP × (1 + ROI%/100)) / margin   (Ship excluded for Purchasing Power; margin = $ppPercentage / 100) --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light"
                        id="target-roi-controls"
                        title="Target ROI% — sets S PRC = (LP × (1 + Target ROI%/100)) / {{ $ppPercentage }}% on every selected row (Ship not used)">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            Target ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target ROI% applied to all selected rows when you click 'Apply S PRC'">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S PRC = (LP × (1 + Target ROI%/100)) / {{ $ppPercentage }}% for every selected row (Ship not used)">
                            <i class="fas fa-calculator"></i> Apply S PRC
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves S PRC for selected rows so SGPFT = Target GPFT%.
                         Formula: sprice = LP / (margin − GPFT%/100). Ship excluded. Target GPFT% must be < margin*100. --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S PRC = LP / ({{ $ppPercentage }}% − Target GPFT%/100) on every selected row (Ship not used)">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            Target GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target GPFT% applied to all selected rows when you click 'Apply S PRC'. Must be less than the Purchasing Power take-home margin ({{ $ppPercentage }}%).">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S PRC = LP / ({{ $ppPercentage }}% − Target GPFT%/100) for every selected row (Ship not used)">
                            <i class="fas fa-calculator"></i> Apply S PRC
                        </button>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary ({{ $ppPercentage }}% Margin)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-badge" style="color:black;font-weight:bold;">Total PFT: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color:black;font-weight:bold;">Total Sales: $0</span>
                        {{-- PFT $ and GPFT % (weighted) — these match the /all-marketplace-master
                             Purchasing Power row exactly. PFT = Σ Profit (dollars), GPFT % =
                             (Σ Profit ÷ Σ Sales L30) × 100 — weighted by sales (the standard
                             accounting margin used across the channel master and sales pages). --}}
                        <span class="badge fs-6 p-2" id="pft-badge"
                              style="background:#198754;color:#fff;font-weight:bold;"
                              title="Sum of per-row Profit dollars across visible rows (matches /all-marketplace-master Total PFT)">PFT: $0</span>
                        <span class="badge fs-6 p-2" id="gpft-pct-badge"
                              style="background:#6f42c1;color:#fff;font-weight:bold;"
                              title="Weighted Gross Profit %: (Σ Profit ÷ Σ Sales L30) × 100. Matches /all-marketplace-master Gprofit% formula and /purchasing-power-sales GPFT % (rev) badge.">GPFT: 0%</span>
                        <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge" style="color:white;font-weight:bold;" title="Weighted GROI% = (Σ Profit ÷ Σ COGS) × 100.">GROI: 0%</span>
                        <span class="badge fs-6 p-2" id="ads-percent-badge" style="background-color:#d63384;color:white;font-weight:bold;" title="Purchasing Power has no ads — Ads%/TACOS is always 0% (same as /all-marketplace-master).">Ads: 0%</span>
                        <span class="badge fs-6 p-2" id="npft-percent-badge" style="background-color:#0f766e;color:white;font-weight:bold;" title="NPFT% = GPFT% (Purchasing Power has no ads — same as /all-marketplace-master N PFT).">NPFT: 0%</span>
                        <span class="badge fs-6 p-2" id="nroi-percent-badge" style="background-color:#6f42c1;color:white;font-weight:bold;" title="NROI% = GROI% (Purchasing Power has no ads — same as /all-marketplace-master N ROI).">NROI: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color:black;font-weight:bold;">Avg Price: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-inv-badge" style="color:black;font-weight:bold;">Total INV: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-l30-badge" style="color:black;font-weight:bold;">Total PP L30: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="total-pp-stock-badge" style="color:white;font-weight:bold;">PP Stock: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color:white;font-weight:bold;cursor:pointer;" title="Click to filter 0 sold">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge" style="background-color:#28a745;color:white;font-weight:bold;cursor:pointer;">&gt; 0 Sold</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-dil-badge" style="color:black;font-weight:bold;">DIL%: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-cogs-badge" style="color:black;font-weight:bold;">COGS: $0</span>
                        <span class="badge bg-danger fs-6 p-2" id="less-amz-badge" style="color:white;font-weight:bold;cursor:pointer;">&lt; Amz</span>
                        <span class="badge fs-6 p-2" id="more-amz-badge" style="background-color:#28a745;color:white;font-weight:bold;cursor:pointer;">&gt; Amz</span>
                        <span class="badge bg-danger fs-6 p-2" id="missing-badge" style="color:white;font-weight:bold;cursor:pointer;">MISSING: 0</span>
                        @include('partials.price-gt-lmp-badge', ['pglBadgeId' => 'purchasingpower-price-gt-lmp-badge', 'pglChannelKey' => 'purchasingpower', 'pglPriceField' => 'PP Price'])
                        @include('partials.price-lt80-lmp-badge', ['pltBadgeId' => 'purchasingpower-price-lt80-lmp-badge', 'pltChannelKey' => 'purchasingpower', 'pltPriceField' => 'PP Price'])
                        <span class="badge bg-danger fs-6 p-2" id="mapping-badge" style="color:white;font-weight:bold;cursor:pointer;">MAPPING: 0</span>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding:0;">
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display:none;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <span id="discount-input-label" class="text-muted small d-none">Same Price ($):</span>
                        <span id="discount-type-select-wrap">
                        <select id="discount-type-select" class="form-select form-select-sm" style="width:120px;">
                            <option value="percentage">Percentage</option>
                            <option value="value">Value ($)</option>
                        </select>
                        </span>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm"
                            placeholder="Enter %" step="0.01" style="width:140px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="pp-table-wrapper" style="height:calc(100vh - 200px);display:flex;flex-direction:column;">
                    <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="max-width: 220px;">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="max-width: 220px;">
                    </div>
                    <div id="pp-table" style="flex:1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Links Modal -->
    <div class="modal fade" id="editLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editLinksSku">
                    <p class="mb-3"><strong>SKU:</strong> <span id="editLinksSkuDisplay"></span></p>
                    <div class="mb-3">
                        <label for="editSellerLink" class="form-label">S Link (Seller)</label>
                        <input type="url" class="form-control" id="editSellerLink" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label for="editBuyerLink" class="form-label">B Link (Buyer)</label>
                        <input type="url" class="form-control" id="editBuyerLink" placeholder="https://...">
                    </div>
                    <div id="editLinksError" class="text-danger small" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Price Rule (PP-specific modal/table — same rule as /faire-pricing) -->
    <div class="modal fade" id="ppPriceRuleModal" tabindex="-1" aria-labelledby="ppPriceRuleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="ppPriceRuleModalLabel">
                        <i class="fas fa-sliders-h me-1"></i> Price Rule
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Match rows by <strong>Dil %</strong> and <strong>Sold qty (PP L30)</strong>.
                        Apply sets <strong>SPRICE = (STD prc × (1 − Discount%/100)) − Ship</strong>.
                        Blank min/max = no limit. If SKUs are checked, only those are updated.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-2" id="pp-price-rule-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:12%">Dil % min</th>
                                    <th style="width:12%">Dil % max</th>
                                    <th style="width:14%">Sold min</th>
                                    <th style="width:14%">Sold max</th>
                                    <th style="width:14%">Discount %</th>
                                    <th style="width:8%"></th>
                                </tr>
                            </thead>
                            <tbody id="pp-price-rule-tbody"></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="pp-price-rule-add-btn">
                        <i class="fas fa-plus"></i> Add rule
                    </button>
                    <div id="pp-price-rule-msg" class="small mt-2 text-danger d-none"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-sm btn-outline-success" id="pp-price-rule-save-btn">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" id="pp-price-rule-apply-btn">
                        <i class="fas fa-check"></i> Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "pp_tabulator_column_visibility";
    let table = null;
    let allTableData = [];
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let samePriceModeActive = false;
    let selectedSkus = new Set();

    // ---- Price Rule (same logic as Faire; separate modal/table + storage) ----
    const PP_PRICE_RULES_KEY = 'pp_price_rules_v1';
    let ppPriceRules = [];

    function ppDefaultPriceRules() {
        return [{ dil_min: null, dil_max: null, sold_min: null, sold_max: null, discount_pct: 25 }];
    }

    function ppLoadPriceRules() {
        try {
            const raw = localStorage.getItem(PP_PRICE_RULES_KEY);
            if (!raw) return ppDefaultPriceRules();
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) && parsed.length ? parsed : ppDefaultPriceRules();
        } catch (e) {
            return ppDefaultPriceRules();
        }
    }

    function ppSavePriceRulesToStorage(rules) {
        localStorage.setItem(PP_PRICE_RULES_KEY, JSON.stringify(rules || []));
    }

    function ppNumOrNull(v) {
        if (v === null || v === undefined || v === '') return null;
        const n = parseFloat(v);
        return isFinite(n) ? n : null;
    }

    function ppInRange(val, min, max) {
        if (min !== null && min !== undefined && val < min) return false;
        if (max !== null && max !== undefined && val > max) return false;
        return true;
    }

    function ppReadPriceRulesFromDom() {
        const rules = [];
        $('#pp-price-rule-tbody tr').each(function() {
            const $tr = $(this);
            rules.push({
                dil_min: ppNumOrNull($tr.find('[data-field="dil_min"]').val()),
                dil_max: ppNumOrNull($tr.find('[data-field="dil_max"]').val()),
                sold_min: ppNumOrNull($tr.find('[data-field="sold_min"]').val()),
                sold_max: ppNumOrNull($tr.find('[data-field="sold_max"]').val()),
                discount_pct: ppNumOrNull($tr.find('[data-field="discount_pct"]').val()),
            });
        });
        return rules;
    }

    function ppRenderPriceRules(rules) {
        ppPriceRules = Array.isArray(rules) ? rules : [];
        const $tb = $('#pp-price-rule-tbody');
        $tb.empty();
        if (!ppPriceRules.length) ppPriceRules = ppDefaultPriceRules();
        ppPriceRules.forEach(function(rule, idx) {
            const r = rule || {};
            $tb.append(
                '<tr data-idx="' + idx + '">' +
                '<td><input type="number" class="form-control form-control-sm" data-field="dil_min" step="0.1" placeholder="—" value="' + (r.dil_min != null ? r.dil_min : '') + '"></td>' +
                '<td><input type="number" class="form-control form-control-sm" data-field="dil_max" step="0.1" placeholder="—" value="' + (r.dil_max != null ? r.dil_max : '') + '"></td>' +
                '<td><input type="number" class="form-control form-control-sm" data-field="sold_min" step="1" placeholder="—" title="PP L30 sold qty" value="' + (r.sold_min != null ? r.sold_min : '') + '"></td>' +
                '<td><input type="number" class="form-control form-control-sm" data-field="sold_max" step="1" placeholder="—" title="PP L30 sold qty" value="' + (r.sold_max != null ? r.sold_max : '') + '"></td>' +
                '<td><input type="number" class="form-control form-control-sm" data-field="discount_pct" step="0.1" placeholder="e.g. 25" value="' + (r.discount_pct != null ? r.discount_pct : '') + '"></td>' +
                '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger pp-price-rule-del" title="Remove"><i class="fas fa-trash"></i></button></td>' +
                '</tr>'
            );
        });
    }

    function ppRuleMatchesRow(rule, d) {
        const inv = parseFloat(d.INV) || 0;
        const ovL30 = parseFloat(d.L30) || 0;
        const dilVal = inv > 0 ? (ovL30 / inv) * 100 : 0;
        const soldVal = parseFloat(d['PP L30']) || 0;
        return ppInRange(dilVal, rule.dil_min, rule.dil_max)
            && ppInRange(soldVal, rule.sold_min, rule.sold_max);
    }

    function ppApplyPriceRules() {
        const $msg = $('#pp-price-rule-msg');
        $msg.addClass('d-none').text('');
        if (!table) return;

        const rules = ppReadPriceRulesFromDom().filter(function(r) {
            return r.discount_pct !== null && isFinite(r.discount_pct);
        });
        if (!rules.length) {
            $msg.removeClass('d-none').text('Add at least one rule with a Discount %.');
            return;
        }

        const restrictSelected = selectedSkus.size > 0;
        const updates = [];
        let matched = 0;
        let skippedNoStd = 0;
        const margin = (typeof PP_MARGIN === 'number' && PP_MARGIN > 0)
            ? PP_MARGIN
            : {{ isset($ppPercentage) ? ((float) $ppPercentage / 100) : 0.65 }};

        table.getRows().forEach(function(row) {
            const d = row.getData();
            const sku = d['(Child) sku'];
            if (!sku) return;
            if (restrictSelected && !selectedSkus.has(sku)) return;

            let hit = null;
            for (let i = 0; i < rules.length; i++) {
                if (ppRuleMatchesRow(rules[i], d)) {
                    hit = rules[i];
                    break;
                }
            }
            if (!hit) return;
            matched++;

            const std = parseFloat(d.standard_price);
            if (!(std > 0)) {
                skippedNoStd++;
                return;
            }

            const factor = 1 - (parseFloat(hit.discount_pct) / 100);
            const ship = parseFloat(d.Ship_productmaster) || 0;
            // Same rule as Faire: SPRICE = (STD × (1 − Discount%/100)) − Ship
            let raw = Math.max(0.99, (std * factor) - ship);
            let newSprice = (typeof roundToRetailPrice === 'function')
                ? roundToRetailPrice(raw)
                : (raw < 20.99 ? +raw.toFixed(2) : Math.ceil(raw) - 0.01);
            const lp = parseFloat(d.LP_productmaster) || 0;
            const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - lp) / newSprice) * 10000) / 100 : 0;
            const sroi = lp > 0 ? Math.round(((newSprice * margin - lp) / lp) * 10000) / 100 : 0;
            row.update({ SPRICE: newSprice, SGPFT: sgpft, SPFT: sgpft, SROI: sroi });
            updates.push({ sku: sku, sprice: newSprice });
        });

        if (!updates.length) {
            $msg.removeClass('d-none').text(
                matched
                    ? ('Matched ' + matched + ' row(s) but none have STD prc > 0' + (skippedNoStd ? ' (' + skippedNoStd + ')' : '') + '.')
                    : (restrictSelected
                        ? 'No checked rows match the Dil % / Sold qty filters.'
                        : 'No rows match the Dil % / Sold qty filters.')
            );
            return;
        }

        ppSavePriceRulesToStorage(ppReadPriceRulesFromDom());
        if (typeof saveSpriceUpdates === 'function') {
            saveSpriceUpdates(updates);
        } else {
            $.ajax({
                url: '{{ route("pp.save.sprice.batch") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { _token: '{{ csrf_token() }}', updates: updates }
            });
        }
        const tip = 'Applied SPRICE on ' + updates.length + ' SKU(s)'
            + (skippedNoStd ? ' (' + skippedNoStd + ' skipped — no STD)' : '')
            + '.';
        showToast(tip, 'success');
    }

    function ppBindPriceRuleUi() {
        $('#pp-rule-btn').on('click', function() {
            ppRenderPriceRules(ppLoadPriceRules());
            $('#pp-price-rule-msg').addClass('d-none').text('');
            const el = document.getElementById('ppPriceRuleModal');
            if (el && window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).show();
            } else {
                $(el).modal('show');
            }
        });

        $('#pp-price-rule-add-btn').on('click', function() {
            ppPriceRules = ppReadPriceRulesFromDom();
            ppPriceRules.push({ dil_min: null, dil_max: null, sold_min: null, sold_max: null, discount_pct: 25 });
            ppRenderPriceRules(ppPriceRules);
        });

        $(document).on('click', '#pp-price-rule-tbody .pp-price-rule-del', function() {
            const idx = parseInt($(this).closest('tr').attr('data-idx'), 10);
            ppPriceRules = ppReadPriceRulesFromDom();
            if (ppPriceRules.length <= 1) {
                ppPriceRules = ppDefaultPriceRules();
            } else {
                ppPriceRules.splice(idx, 1);
            }
            ppRenderPriceRules(ppPriceRules);
        });

        $('#pp-price-rule-save-btn').on('click', function() {
            const rules = ppReadPriceRulesFromDom();
            ppSavePriceRulesToStorage(rules);
            ppPriceRules = rules;
            showToast('Price rules saved', 'success');
        });

        $('#pp-price-rule-apply-btn').on('click', function() {
            ppApplyPriceRules();
        });
    }


    /** Std Prc vs Amz/channel price: reduce / hold / increase → red / yellow / green. */
    function ppStdPrcChangeDotMeta(stdPrc, comparePrice) {
        const sp = parseFloat(stdPrc);
        const ap = parseFloat(comparePrice);
        if (!isFinite(sp) || sp <= 0 || !isFinite(ap) || ap <= 0) return null;
        const sp2 = sp.toFixed(2);
        const ap2 = ap.toFixed(2);
        if (parseFloat(sp2) < parseFloat(ap2)) {
            return { kind: 'reduce', color: '#dc3545', title: 'Reduce vs Amz price' };
        }
        if (parseFloat(sp2) > parseFloat(ap2)) {
            return { kind: 'increase', color: '#28a745', title: 'Increase vs Amz price' };
        }
        return { kind: 'hold', color: '#ffc107', title: 'Hold (matches Amz price)' };
    }

    function ppStdPrcChangeDotHtml(stdPrc, comparePrice) {
        const meta = ppStdPrcChangeDotMeta(stdPrc, comparePrice);
        if (!meta) return '';
        return '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;' +
            'background:' + meta.color + ';flex-shrink:0;" title="' + meta.title + ' — Std Prc (shared with Amazon)"></span>';
    }

    function applyPpStandardPriceToLinkedRows(sku, std, appliedSkus) {
        if (typeof table === 'undefined' || !table) return null;
        const target = String(sku || '').trim().toUpperCase();
        const appliedSet = new Set(
            (Array.isArray(appliedSkus) ? appliedSkus : [])
                .map(function(s) { return String(s || '').trim().toUpperCase(); })
                .filter(Boolean)
        );
        if (target) appliedSet.add(target);
        let primaryRow = null;
        (table.getRows('all') || table.getRows() || []).forEach(function(r) {
            const d = r.getData();
            if (!d) return;
            const rowSku = String(d['(Child) sku'] || d.sku || d.SKU || '').trim();
            if (!rowSku) return;
            const rowKey = rowSku.toUpperCase();
            const linked = Array.isArray(d.linked_lmp_skus) ? d.linked_lmp_skus : [];
            const inGroup = appliedSet.has(rowKey)
                || linked.some(function(s) { return String(s || '').trim().toUpperCase() === target; })
                || (target && rowKey === target);
            if (!inGroup) return;
            r.update({ STANDARD_PRICE: std, standard_price: std });
            if (rowKey === target) primaryRow = r;
        });
        return primaryRow;
    }

    document.addEventListener('lmp-modal-sp-saved', function(e) {
        const detail = (e && e.detail) || {};
        const sku = detail.sku;
        const saved = parseFloat(detail.standard_price);
        if (!sku || !isFinite(saved) || saved <= 0) return;
        applyPpStandardPriceToLinkedRows(sku, saved, detail.applied_skus);
    });

    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    // Mode button visual resets — keep each in their idle styling.
    function resetDecreaseBtn() {
        $('#decrease-btn').removeClass('btn-danger').addClass('btn-warning')
            .html('<i class="fas fa-arrow-down"></i> Decrease Mode');
    }
    function resetIncreaseBtn() {
        $('#increase-btn').removeClass('btn-danger').addClass('btn-success')
            .html('<i class="fas fa-arrow-up"></i> Increase Mode');
    }
    function resetSamePriceBtn() {
        $('#same-price-btn').removeClass('btn-danger').addClass('btn-info')
            .html('<i class="fas fa-equals"></i> Same Price Mode');
    }
    // Swap the discount-input panel between %/$ and Same Price modes.
    function syncDiscountInputUi() {
        const $input = $('#discount-percentage-input');
        if (samePriceModeActive) {
            $('#discount-type-select-wrap').hide();
            $('#discount-input-label').removeClass('d-none');
            $input.attr('placeholder', 'Enter price (e.g. 19.99)').attr('step', '0.01');
            $('#apply-discount-btn').text('Apply Same Price');
        } else {
            $('#discount-type-select-wrap').show();
            $('#discount-input-label').addClass('d-none');
            const t = $('#discount-type-select').val();
            $input.attr('placeholder', t === 'percentage' ? 'Enter %' : 'Enter $');
            $('#apply-discount-btn').text('Apply');
        }
    }

    $(document).ready(function() {

        $('#discount-type-select').on('change', function() { syncDiscountInputUi(); });

        $('#decrease-btn').on('click', function() {
            decreaseModeActive = !decreaseModeActive;
            increaseModeActive = false;
            samePriceModeActive = false;
            const selectColumn = table.getColumn('_select');

            resetIncreaseBtn();
            resetSamePriceBtn();
            if (decreaseModeActive) {
                $(this).removeClass('btn-warning').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                selectColumn.show();
            } else {
                resetDecreaseBtn();
                selectColumn.hide(); selectedSkus.clear(); updateSelectedCount();
            }
            syncDiscountInputUi();
        });

        $('#increase-btn').on('click', function() {
            increaseModeActive = !increaseModeActive;
            decreaseModeActive = false;
            samePriceModeActive = false;
            const selectColumn = table.getColumn('_select');

            resetDecreaseBtn();
            resetSamePriceBtn();
            if (increaseModeActive) {
                $(this).removeClass('btn-success').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-up"></i> Increase ON');
                selectColumn.show();
            } else {
                resetIncreaseBtn();
                selectColumn.hide(); selectedSkus.clear(); updateSelectedCount();
            }
            syncDiscountInputUi();
        });

        // Same Price Mode — entered price applies to ALL selected SKUs.
        $('#same-price-btn').on('click', function() {
            samePriceModeActive = !samePriceModeActive;
            decreaseModeActive = false;
            increaseModeActive = false;
            const selectColumn = table.getColumn('_select');

            resetDecreaseBtn();
            resetIncreaseBtn();
            if (samePriceModeActive) {
                $(this).removeClass('btn-info').addClass('btn-danger')
                    .html('<i class="fas fa-equals"></i> Same Price ON');
                selectColumn.show();
            } else {
                resetSamePriceBtn();
                selectColumn.hide(); selectedSkus.clear(); updateSelectedCount();
            }
            syncDiscountInputUi();
        });

        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            table.getData('active').filter(r => !(r.Parent && r.Parent.startsWith('PARENT'))).forEach(r => {
                isChecked ? selectedSkus.add(r['(Child) sku']) : selectedSkus.delete(r['(Child) sku']);
            });
            $('.sku-select-checkbox').each(function() { $(this).prop('checked', selectedSkus.has($(this).data('sku'))); });
            updateSelectedCount();
        });

        $(document).on('change', '.sku-select-checkbox', function() {
            const sku = $(this).data('sku');
            $(this).prop('checked') ? selectedSkus.add(sku) : selectedSkus.delete(sku);
            updateSelectedCount(); updateSelectAllCheckbox();
        });

        $('#apply-discount-btn').on('click', function() { applyDiscount(); });
        $('#discount-percentage-input').on('keypress', function(e) { if (e.which === 13) applyDiscount(); });
        $('#sugg-amz-prc-btn').on('click', function() { applySuggestAmazonPrice(); });
        $('#clear-sprice-btn').on('click', function() { clearSpriceForSelected(); });

        /*
         * Target ROI% / Target GPFT% bulk apply (Purchasing Power, margin = $ppPercentage / 100)
         * --------------------------------------------------------------------------------------
         * Ship is intentionally excluded from all Purchasing Power formulas.
         * Back-solves SPRICE so the resulting SROI / SGPFT column matches the entered target:
         *     SROI%  = ((sprice * margin − lp) / lp)     * 100
         *           → sprice = (lp * (1 + ROI%/100)) / margin
         *     SGPFT% = ((sprice * margin − lp) / sprice) * 100
         *           → sprice = lp / (margin − GPFT%/100)
         * Optimistic SGPFT / SPFT / SROI are written client-side, then the existing
         * bulk /pp-save-sprice-batch endpoint reconciles them server-side. Rounding
         * is plain 2-decimal — no .99 / .49 retail snapping — because snapping would
         * shift the achieved SROI / SGPFT off the user-typed target.
         */
        const PP_MARGIN = {{ $ppPercentage }} / 100;

        $('#apply-target-roi-btn').on('click', function () {
            const rawInput = $('#target-roi-input').val();
            const targetRoiPct = parseFloat(String(rawInput).replace(',', '.'));

            if (rawInput === '' || rawInput == null) {
                showToast('Please enter a Target ROI%', 'error');
                return;
            }
            if (!isFinite(targetRoiPct)) {
                showToast('Target ROI% must be a number', 'error');
                return;
            }
            if (selectedSkus.size === 0) {
                const selectColumn = table && table.getColumn ? table.getColumn('_select') : null;
                if (selectColumn) selectColumn.show();
                showToast('Please select at least one SKU first (turn on Decrease / Increase / Same Price to reveal checkboxes)', 'error');
                return;
            }

            const roiMultiplier = 1 + (targetRoiPct / 100);
            const updates = [];
            let updatedCount = 0;
            let skippedNoLp = 0;

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (!rows.length) return;
                const row = rows[0];
                const rowData = row.getData();
                if (rowData.Parent && String(rowData.Parent).startsWith('PARENT')) return;

                const lp = parseFloat(rowData['LP_productmaster']) || 0;
                if (lp <= 0) { skippedNoLp++; return; }

                const candidate = (lp * roiMultiplier) / PP_MARGIN;
                const newSprice = +candidate.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                const sgpft = newSprice > 0 ? Math.round(((newSprice * PP_MARGIN - lp) / newSprice) * 10000) / 100 : 0;
                const sroi  = lp > 0       ? Math.round(((newSprice * PP_MARGIN - lp) / lp)     * 10000) / 100 : 0;

                row.update({ SPRICE: newSprice, SGPFT: sgpft, SPFT: sgpft, SROI: sroi });
                updates.push({ sku, sprice: newSprice });
                updatedCount++;
            });

            if (updates.length === 0) {
                showToast('No selected rows have a usable LP > 0', 'warning');
                return;
            }

            saveSpriceUpdates(updates);
            const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
            showToast(`Target ROI ${targetRoiPct}% applied to ${updatedCount} SKU(s)${note}`, 'success');
        });

        $('#apply-target-gpft-btn').on('click', function () {
            const rawInput = $('#target-gpft-input').val();
            const targetGpftPct = parseFloat(String(rawInput).replace(',', '.'));

            if (rawInput === '' || rawInput == null) {
                showToast('Please enter a Target GPFT%', 'error');
                return;
            }
            if (!isFinite(targetGpftPct)) {
                showToast('Target GPFT% must be a number', 'error');
                return;
            }
            if (selectedSkus.size === 0) {
                const selectColumn = table && table.getColumn ? table.getColumn('_select') : null;
                if (selectColumn) selectColumn.show();
                showToast('Please select at least one SKU first (turn on Decrease / Increase / Same Price to reveal checkboxes)', 'error');
                return;
            }

            const denom = PP_MARGIN - (targetGpftPct / 100);
            if (denom <= 0) {
                showToast(`Target GPFT% ${targetGpftPct}% is too high — must be < ${(PP_MARGIN * 100).toFixed(0)}% (Purchasing Power take-home).`, 'error');
                return;
            }

            const updates = [];
            let updatedCount = 0;
            let skippedNoLp = 0;

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (!rows.length) return;
                const row = rows[0];
                const rowData = row.getData();
                if (rowData.Parent && String(rowData.Parent).startsWith('PARENT')) return;

                const lp = parseFloat(rowData['LP_productmaster']) || 0;
                if (lp <= 0) { skippedNoLp++; return; }

                const candidate = lp / denom;
                const newSprice = +candidate.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                const sgpft = newSprice > 0 ? Math.round(((newSprice * PP_MARGIN - lp) / newSprice) * 10000) / 100 : 0;
                const sroi  = lp > 0       ? Math.round(((newSprice * PP_MARGIN - lp) / lp)     * 10000) / 100 : 0;

                row.update({ SPRICE: newSprice, SGPFT: sgpft, SPFT: sgpft, SROI: sroi });
                updates.push({ sku, sprice: newSprice });
                updatedCount++;
            });

            if (updates.length === 0) {
                showToast('No selected rows have a usable LP > 0', 'warning');
                return;
            }

            saveSpriceUpdates(updates);
            const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
            showToast(`Target GPFT ${targetGpftPct}% applied to ${updatedCount} SKU(s)${note}`, 'success');
        });

        $('#target-roi-input').on('keypress', function(e) {
            if (e.which === 13) $('#apply-target-roi-btn').click();
        });
        $('#target-gpft-input').on('keypress', function(e) {
            if (e.which === 13) $('#apply-target-gpft-btn').click();
        });

        // Sold filter is now owned by the #sold-filter dropdown (mirrors Amazon tabulator).
        // Other "badge active" flags (Amz, mapping, missing) remain unchanged below.
        let lessAmzFilterActive = false, moreAmzFilterActive = false;
        let missingFilterActive = false, mappingFilterActive = false;
        let priceGtLmpFilterActive = false;
        let priceLt80LmpFilterActive = false;

        // Sold badges just toggle the dropdown so the dropdown stays the single source of
        // truth. Clicking the same badge twice clears the filter (toggle semantics preserved).
        $('#zero-sold-count-badge').on('click', function() {
            const next = $('#sold-filter').val() === 'zero' ? 'all' : 'zero';
            $('#sold-filter').val(next);
            applyFilters();
        });
        $('#more-sold-count-badge').on('click', function() {
            const next = $('#sold-filter').val() === 'sold' ? 'all' : 'sold';
            $('#sold-filter').val(next);
            applyFilters();
        });
        $('#less-amz-badge').on('click', function() { lessAmzFilterActive = !lessAmzFilterActive; moreAmzFilterActive = false; applyFilters(); });
        $('#more-amz-badge').on('click', function() { moreAmzFilterActive = !moreAmzFilterActive; lessAmzFilterActive = false; applyFilters(); });
        $('#missing-badge').on('click', function() { missingFilterActive = !missingFilterActive; mappingFilterActive = false; applyFilters(); });
        $('#mapping-badge').on('click', function() { mappingFilterActive = !mappingFilterActive; missingFilterActive = false; applyFilters(); });

        function updateSelectedCount() {
            const count = selectedSkus.size;
            $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
            $('#discount-input-container').toggle(count > 0);
        }

        function updateSelectAllCheckbox() {
            if (!table) return;
            const filteredSkus = new Set(table.getData('active').filter(r => !(r.Parent && r.Parent.startsWith('PARENT'))).map(r => r['(Child) sku']).filter(s => s));
            $('#select-all-checkbox').prop('checked', filteredSkus.size > 0 && [...filteredSkus].every(s => selectedSkus.has(s)));
        }

        function roundToRetailPrice(price) { 
            if (price < 20.99) {
                return +price.toFixed(2);
            }
            return Math.ceil(price) - 0.01; 
        }

        /** |INV − PP Stock| ≤ 3 → MAP; > 3 → N MP (same as Wayfair / TikTok / Reverb). */
        function ppInvPpStockDiff(ourInv, ppInv) {
            return Math.abs((parseFloat(ourInv) || 0) - (parseFloat(ppInv) || 0));
        }
        function ppInvPpStockWithinTolerance(ourInv, ppInv) {
            return ppInvPpStockDiff(ourInv, ppInv) <= 3;
        }

        function applyDiscount() {
            const discountType  = $('#discount-type-select').val();
            const discountValue = parseFloat($('#discount-percentage-input').val());

            if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                showToast('Turn on Decrease, Increase, or Same Price mode first', 'error');
                return;
            }
            if (isNaN(discountValue) || discountValue <= 0) {
                showToast(samePriceModeActive ? 'Please enter a price (e.g. 19.99)' : 'Enter a valid value', 'error');
                return;
            }
            if (selectedSkus.size === 0) { showToast('Select at least one SKU', 'error'); return; }

            let updatedCount = 0;
            const updates = [];

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (!rows.length) return;
                const row = rows[0], rowData = row.getData();
                const currentPrice = parseFloat(rowData['PP Price']) || 0;
                // Same Price mode applies even when PP Price is empty;
                // %/$ modes still need a positive PP Price to compute against.
                if (!samePriceModeActive && currentPrice <= 0) return;

                let newSprice;
                if (samePriceModeActive) {
                    newSprice = Math.max(0.99, discountValue);
                } else if (discountType === 'percentage') {
                    newSprice = decreaseModeActive ? currentPrice * (1 - discountValue / 100) : currentPrice * (1 + discountValue / 100);
                } else {
                    newSprice = decreaseModeActive ? currentPrice - discountValue : currentPrice + discountValue;
                }
                newSprice = Math.max(0.99, roundToRetailPrice(newSprice));

                const percentage = {{ $ppPercentage }} / 100;
                const lp   = parseFloat(rowData['LP_productmaster']) || 0;
                // Ship excluded from Purchasing Power formulas
                const sgpft = newSprice > 0 ? Math.round(((newSprice * percentage - lp) / newSprice) * 10000) / 100 : 0;
                const sroi  = lp > 0    ? Math.round(((newSprice * percentage - lp) / lp) * 10000) / 100 : 0;

                row.update({ SPRICE: newSprice, SGPFT: sgpft, SPFT: sgpft, SROI: sroi });
                updates.push({ sku, sprice: newSprice });
                updatedCount++;
            });

            if (updates.length) saveSpriceUpdates(updates);
            const action = samePriceModeActive ? 'Same Price' : (decreaseModeActive ? 'Decrease' : 'Increase');
            showToast(`${action} applied to ${updatedCount} SKU(s)`, 'success');
            $('#discount-percentage-input').val('');
        }

        function applySuggestAmazonPrice() {
            if (selectedSkus.size === 0) { showToast('Select SKUs first', 'error'); return; }
            let updatedCount = 0, noAmzCount = 0;
            const updates = [];
            const percentage = {{ $ppPercentage }} / 100;

            selectedSkus.forEach(sku => {
                const rows = table.searchRows('(Child) sku', '=', sku);
                if (!rows.length) { noAmzCount++; return; }
                const row = rows[0], rowData = row.getData();
                const amazonPrice = parseFloat(rowData['A Price']);
                if (!amazonPrice || amazonPrice <= 0) { noAmzCount++; return; }

                const lp   = parseFloat(rowData['LP_productmaster']) || 0;
                // Ship excluded from Purchasing Power formulas
                const sgpft = Math.round(((amazonPrice * percentage - lp) / amazonPrice) * 10000) / 100;
                const sroi  = lp > 0 ? Math.round(((amazonPrice * percentage - lp) / lp) * 10000) / 100 : 0;
                row.update({ SPRICE: amazonPrice, SGPFT: sgpft, SPFT: sgpft, SROI: sroi });
                updates.push({ sku, sprice: amazonPrice });
                updatedCount++;
            });

            if (updates.length) saveSpriceUpdates(updates);
            let msg = `Amz price applied to ${updatedCount} SKU(s)`;
            if (noAmzCount) msg += ` (${noAmzCount} had no Amz price)`;
            showToast(msg, updatedCount > 0 ? 'success' : 'warning');
        }

        function saveSpriceUpdates(updates) {
            $.ajax({
                url: '/pp-save-sprice-batch',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { updates },
                success: function(response) {
                    if (response.success) console.log('PP SPRICE saved:', response.updated, 'records');
                },
                error: function(xhr) {
                    showToast('Error saving SPRICE: ' + (xhr.responseJSON?.error || 'Unknown'), 'error');
                }
            });
        }

        function clearSpriceForSelected() {
            if (selectedSkus.size === 0) { showToast('Select SKUs first', 'error'); return; }
            if (!confirm(`Clear SPRICE for ${selectedSkus.size} SKU(s)?`)) return;

            let clearedCount = 0;
            const updates = [];
            table.getRows().forEach(row => {
                const sku = row.getData()['(Child) sku'];
                if (!selectedSkus.has(sku)) return;
                row.update({ SPRICE: 0, SGPFT: 0, SPFT: 0, SROI: 0 });
                updates.push({ sku, sprice: 0 });
                clearedCount++;
            });
            if (updates.length) saveSpriceUpdates(updates);
            showToast(`SPRICE cleared for ${clearedCount} SKU(s)`, 'success');
        }

        function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
            $.ajax({
                url: '/pp-save-sprice-tabulator',
                method: 'POST',
                data: { sku, sprice, _token: '{{ csrf_token() }}' },
                success: function(response) {
                    showToast(`✓ SPRICE saved: ${sku} = $${parseFloat(sprice).toFixed(2)}`, 'success');
                    if (response.spft_percent  !== undefined) row.update({ SPFT:  response.spft_percent });
                    if (response.sroi_percent  !== undefined) row.update({ SROI:  response.sroi_percent });
                    if (response.sgpft_percent !== undefined) row.update({ SGPFT: response.sgpft_percent });
                },
                error: function(xhr) {
                    if (retryCount < 3) setTimeout(() => saveSpriceWithRetry(sku, sprice, row, retryCount + 1), 2000);
                    else showToast(`Failed to save SPRICE for ${sku}`, 'error');
                }
            });
        }

        // Initialize Tabulator
        table = new Tabulator('#pp-table', {
            ajaxURL: '/pp-data-json',
            ajaxSorting: false,
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: 'rows',
            columnCalcs: 'both',
            langs: { default: { pagination: { page_size: 'SKU Count' } } },
            initialSort: [{ column: 'PP L30', dir: 'desc' }],
            rowFormatter: function(row) {
                if (row.getData().Parent && row.getData().Parent.startsWith('PARENT'))
                    row.getElement().style.backgroundColor = '#fffef2';
            },
            columns: [
                { title: 'Parent', field: 'Parent', headerFilter: 'input', headerFilterPlaceholder: 'Search Parent...', cssClass: 'text-primary', tooltip: true, frozen: true, width: 150, visible: false },
                (window.ParentExpand ? ParentExpand.columnDef() : { title: 'P', field: '_parent_expand', width: 36, frozen: true, headerSort: false }),
                {
                    title: 'Image', field: 'image_path', headerSort: false, width: 80,
                    formatter: function(cell) {
                        const v = cell.getValue();
                        return v ? `<img src="${v}" style="width:50px;height:50px;object-fit:cover;">` : '';
                    }
                },
                {
                    title: 'SKU', field: '(Child) sku', headerFilter: 'input', headerFilterPlaceholder: 'Search SKU...',
                    cssClass: 'text-primary fw-bold', tooltip: true, frozen: true, width: 250,
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        return `<span>${sku}</span><i class="fa fa-copy text-secondary copy-sku-btn" style="cursor:pointer;margin-left:8px;font-size:14px;" data-sku="${sku}" title="Copy SKU"></i>`;
                    }
                },
                {
                    title: 'Links', field: 'links_column', frozen: true, width: 55, hozAlign: 'center', headerSort: false, visible: true,
                    tooltip: 'Double-click to add / edit links',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const buyerLink = d['B Link'] || '';
                        const sellerLink = d['S Link'] || '';
                        let html = '<div style="display:flex;flex-direction:column;gap:4px;align-items:center;">';
                        if (sellerLink) {
                            html += `<a href="${sellerLink}" target="_blank" class="text-info" style="font-size:12px;text-decoration:none;"><i class="fa fa-link"></i> S</a>`;
                        }
                        if (buyerLink) {
                            html += `<a href="${buyerLink}" target="_blank" class="text-success" style="font-size:12px;text-decoration:none;"><i class="fa fa-link"></i> B</a>`;
                        }
                        if (!sellerLink && !buyerLink) {
                            html += '<span class="text-muted" style="font-size:12px;">-</span>';
                        }
                        html += '</div>';
                        return html;
                    },
                    cellDblClick: function(e, cell) {
                        e.stopPropagation();
                        openEditLinksModal(cell.getRow());
                    }
                },
                { title: 'INV',  field: 'INV',  hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'OV L30', field: 'L30', hozAlign: 'center', width: 50, sorter: 'number' },
                {
                    title: 'Dil', field: 'PP Dil%', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const inv = parseFloat(d.INV) || 0;
                        if (inv === 0) return '<span style="color:#6c757d;">0%</span>';
                        const dil = (parseFloat(d['L30']) || 0) / inv * 100;
                        const color = dil < 16.66 ? '#a00211' : dil < 25 ? '#ffc107' : dil < 50 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${Math.round(dil)}%</span>`;
                    }
                },
                { title: 'PP L30',   field: 'PP L30',  hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'PP Stock', field: 'PP INV',  hozAlign: 'center', width: 60, sorter: 'number',
                    headerTooltip: 'Purchasing Power stock from MCM OF21 (purchasing_power_products.stock). Hover a cell for its source.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const v = parseInt(cell.getValue()) || 0;
                        const source = d['PP Stock Source'] || 'unknown';
                        const color = v === 0 ? '#a00211' : v < 5 ? '#ffc107' : '#28a745';
                        return `<span title="Source: ${source}" style="color:${color};font-weight:600;">${v}</span>`;
                    }
                },
                {
                    title: 'NR/REQ', field: 'nr_req', hozAlign: 'center', headerSort: false, width: 60,
                    formatter: function(cell) {
                        const v = cell.getValue() || 'REQ';
                        return `<select class="form-select form-select-sm nr-req-dropdown" style="border:1px solid #ddd;text-align:center;cursor:pointer;padding:2px 4px;font-size:16px;width:50px;height:28px;">
                            <option value="REQ" ${v === 'REQ' ? 'selected' : ''}>🟢</option>
                            <option value="NR"  ${v === 'NR'  ? 'selected' : ''}>🔴</option>
                        </select>`;
                    },
                    cellClick: function(e) { e.stopPropagation(); }
                },
                {
                    title: 'Std Prc', field: 'STANDARD_PRICE', hozAlign: 'center', sorter: 'number', width: 70,
                    headerTooltip: 'Standard Price (Std Prc) — same shared value as /amazon-tabulator-view. Editable; saves to all Sku Link LMP siblings. Dot vs Amz price.',
                    editor: 'input',
                    editable: function(cell) {
                        const d = cell.getRow().getData();
                        const sku = String(d['(Child) sku'] || d.sku || d.SKU || '');
                        return !!sku && !String(d.Parent || '').toUpperCase().startsWith('PARENT');
                    },
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const value = cell.getValue();
                        const std = parseFloat(value) || 0;
                        if (!value || std <= 0) return '';
                        const amzPrice = parseFloat(d['A Price'] || d.a_price || d.amazon_price || 0) || 0;
                        const channelPrice = parseFloat(d['PP Price'] || d.price || 0) || 0;
                        const comparePrice = amzPrice > 0 ? amzPrice : channelPrice;
                        const dot = ppStdPrcChangeDotHtml(std, comparePrice);
                        if (comparePrice > 0 && comparePrice.toFixed(2) === std.toFixed(2)) {
                            return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' + dot + '</span>';
                        }
                        return '<span style="display:inline-flex;align-items:center;justify-content:center;gap:4px;">' + dot + ('$' + std.toFixed(2)) + '</span>';
                    }
                },
                {
                    title: 'Price', field: 'PP Price', hozAlign: 'center', sorter: 'number', width: 70,
                    headerTooltip: 'Purchasing Power listed price from MCM OF21 (purchasing_power_products). Hover a cell for its source.',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const v = parseFloat(cell.getValue() || 0);
                        const amz = parseFloat(d['A Price']) || 0;
                        const source = d['PP Price Source'] || 'unknown';
                        const tip = `Source: ${source}`;
                        if (v === 0) {
                            return `<span title="${tip}" style="color:#a00211;font-weight:600;">$0.00 <i class="fas fa-exclamation-triangle"></i></span>`;
                        }
                        const lmpTri = (window.PriceGtLmpBadge ? PriceGtLmpBadge.triangleHtml(v, d.lmp_price || d.lmp || d.LMP) : '');
                        const purpleTri = (window.PriceLt80LmpBadge ? PriceLt80LmpBadge.triangleHtml(v, d.lmp_price || d.lmp || d.LMP) : '');
                        const color = amz > 0 ? (v < amz ? '#a00211' : v > amz ? '#28a745' : '') : '';
                        return `<span title="${tip}" style="color:${color};font-weight:${color ? '600' : 'normal'};">$${v.toFixed(2)}</span>${lmpTri}${purpleTri}`;
                    }
                },
                {
                    title: 'A Price', field: 'A Price', hozAlign: 'center', sorter: 'number', width: 70,
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue());
                        return (!v || isNaN(v)) ? '<span style="color:#6c757d;">-</span>' : `$${v.toFixed(2)}`;
                    }
                },
                {
                    title: "<span style='color:#a00211;'>Missing</span>", field: 'Missing', hozAlign: 'center', width: 60,
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const price = parseFloat(d['PP Price']) || 0, inv = parseFloat(d.INV) || 0, nrReq = d.nr_req || 'REQ';
                        if (nrReq === 'NR' || inv === 0) return '';
                        return price === 0 ? '<span style="color:#a00211;font-weight:600;">M</span>' : '';
                    }
                },
                {
                    title: 'Mapping',
                    field: 'Mapping',
                    hozAlign: 'center',
                    width: 90,
                    headerTooltip: 'MAP when |INV − PP Stock| ≤ 3; N MP when > 3 (NR rows excluded).',
                    formatter: function(cell) {
                        const d = cell.getRow().getData();
                        const ourInv = parseFloat(d.INV) || 0, mcInv = parseFloat(d['PP INV']) || 0;
                        const price = parseFloat(d['PP Price']) || 0, nrReq = d.nr_req || 'REQ';
                        if (nrReq === 'NR' || ourInv === 0 || price === 0) return '';
                        const diff = ppInvPpStockDiff(ourInv, mcInv);
                        return ppInvPpStockWithinTolerance(ourInv, mcInv)
                            ? '<span style="color:#28a745;font-weight:600;background-color:#d4edda;padding:2px 6px;border-radius:3px;">MAP</span>'
                            : `<span style="color:#a00211;font-weight:600;background-color:#f8d7da;padding:2px 6px;border-radius:3px;">N MP (${diff})</span>`;
                    }
                },
                {
                    title: 'GPFT%', field: 'GPFT%', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const p = parseFloat(cell.getValue());
                        const color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'NPFT', field: 'PFT %', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        // Purchasing Power has no ads — NPFT% = GPFT%
                        const p = parseFloat(cell.getRow().getData()['GPFT%'] ?? cell.getValue());
                        if (!isFinite(p)) return '';
                        const color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'GROI%', field: 'ROI%', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const p = parseFloat(cell.getValue());
                        if (!isFinite(p)) return '';
                        const color = p < 40 ? '#a00211' : p < 75 ? '#ffc107' : p < 125 ? '#28a745' : '#d63384';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'NROI', field: 'NROI', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        // Purchasing Power has no ads — NROI% = GROI% (ROI%)
                        const p = parseFloat(cell.getRow().getData()['ROI%']);
                        if (!isFinite(p)) return '';
                        const color = p < 40 ? '#a00211' : p < 75 ? '#ffc107' : p < 125 ? '#28a745' : '#d63384';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'Profit', field: 'Profit', hozAlign: 'center', sorter: 'number', visible: false, width: 70,
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue() || 0);
                        return `<span style="color:${v >= 0 ? '#28a745' : '#a00211'};font-weight:600;">$${v.toFixed(2)}</span>`;
                    }
                },
                {
                    title: 'Sales', field: 'Sales L30', hozAlign: 'center', sorter: 'number', visible: false, width: 80,
                    formatter: function(cell) { return `$${parseFloat(cell.getValue() || 0).toFixed(2)}`; }
                },
                {
                    title: 'LP', field: 'LP_productmaster', hozAlign: 'center', sorter: 'number', visible: false, width: 60,
                    formatter: function(cell) { return `$${parseFloat(cell.getValue() || 0).toFixed(2)}`; }
                },
                {
                    title: 'Ship', field: 'Ship_productmaster', hozAlign: 'center', sorter: 'number', visible: false, width: 60,
                    formatter: function(cell) { return `$${parseFloat(cell.getValue() || 0).toFixed(2)}`; }
                },
                {
                    title: "<input type='checkbox' id='select-all-checkbox'>",
                    field: '_select', hozAlign: 'center', headerSort: false, width: 40, visible: false,
                    formatter: function(cell) {
                        const sku = cell.getRow().getData()['(Child) sku'];
                        return `<input type='checkbox' class='sku-select-checkbox' data-sku='${sku}' ${selectedSkus.has(sku) ? 'checked' : ''}>`;
                    }
                },
                {
                    title: 'SPRICE', field: 'SPRICE', hozAlign: 'center', editor: 'number',
                    editorParams: { min: 0, step: 0.01 }, sorter: 'number', width: 80,
                    formatter: function(cell) {
                        const v = parseFloat(cell.getValue() || 0);
                        const d = cell.getRow().getData();
                        let bg = '';
                        if (d.SPRICE_STATUS === 'pushed') bg = 'background-color:#fff3cd;';
                        else if (d.SPRICE_STATUS === 'applied') bg = 'background-color:#d4edda;';
                        else if (d.has_custom_sprice) bg = 'background-color:#e7f1ff;';
                        return `<span style="font-weight:600;${bg}padding:2px 6px;border-radius:3px;">$${v.toFixed(2)}</span>`;
                    }
                },
                {
                    title: 'SGPFT', field: 'SGPFT', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        const p = parseFloat(cell.getValue());
                        const color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'SNPFT', field: 'SPFT', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        // Purchasing Power has no ads — SNPFT = SGPFT
                        const p = parseFloat(cell.getRow().getData().SGPFT ?? cell.getValue());
                        if (!isFinite(p)) return '';
                        const color = p < 10 ? '#a00211' : p < 15 ? '#ffc107' : p < 20 ? '#3591dc' : p <= 40 ? '#28a745' : '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                },
                {
                    title: 'SNROI', field: 'SROI', hozAlign: 'center', sorter: 'number', width: 50,
                    formatter: function(cell) {
                        // Purchasing Power has no ads — SNROI = gross SROI (no Ads% cut)
                        const p = parseFloat(cell.getValue());
                        if (!isFinite(p)) return '';
                        const color = p < 40 ? '#a00211' : p < 75 ? '#ffc107' : p < 125 ? '#28a745' : '#d63384';
                        return `<span style="color:${color};font-weight:600;">${p.toFixed(0)}%</span>`;
                    }
                }
            ]
        });

        $('#sku-search, #parent-search').on('keyup', function() {
            table.setFilter([
                { field: '(Child) sku', type: 'like', value: $('#sku-search').val() || '' },
                { field: 'Parent', type: 'like', value: $('#parent-search').val() || '' }
            ]);
        });

        $(document).on('change', '.nr-req-dropdown', function() {
            const $cell = $(this).closest('.tabulator-cell');
            const row = table.getRow($cell.closest('.tabulator-row')[0]);
            const sku = row.getData()['(Child) sku'];
            const newValue = $(this).val();
            $.ajax({
                url: '{{ url("/pp-update-nr-req") }}',
                method: 'POST',
                data: { sku, nr_req: newValue, _token: '{{ csrf_token() }}' },
                success: function() { showToast(`${sku}: updated to ${newValue}`, 'success'); row.update({ nr_req: newValue }); },
                error: function() { showToast(`Failed to update ${sku}`, 'error'); }
            });
        });

        // Open Edit Links modal
        let editLinksRow = null;
        function openEditLinksModal(row) {
            if (!row) return;
            editLinksRow = row;
            const d = row.getData();
            $('#editLinksSku').val(d['(Child) sku']);
            $('#editLinksSkuDisplay').text(d['(Child) sku']);
            $('#editSellerLink').val(d['S Link'] || '');
            $('#editBuyerLink').val(d['B Link'] || '');
            $('#editLinksError').hide().text('');
            new bootstrap.Modal(document.getElementById('editLinksModal')).show();
        }

        // Save links
        $(document).on('click', '#saveLinksBtn', function() {
            const sku = $('#editLinksSku').val();
            const sellerLink = $('#editSellerLink').val().trim();
            const buyerLink = $('#editBuyerLink').val().trim();
            const $err = $('#editLinksError');
            $err.hide().text('');

            const $btn = $(this).prop('disabled', true);
            $.ajax({
                url: '{{ url("/pp-update-links") }}',
                method: 'POST',
                data: { sku, seller_link: sellerLink, buyer_link: buyerLink, _token: '{{ csrf_token() }}' },
                success: function(res) {
                    if (editLinksRow) {
                        editLinksRow.update({ 'S Link': res.seller_link || '', 'B Link': res.buyer_link || '' })
                            .then(function() { editLinksRow.reformat(); })
                            .catch(function() { editLinksRow.reformat(); });
                    }
                    showToast(`${sku}: links saved`, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('editLinksModal'))?.hide();
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Failed to save links.';
                    $err.text(msg).show();
                },
                complete: function() { $btn.prop('disabled', false); }
            });
        });

        table.on('cellEdited', function(cell) {
            const field = cell.getField();
            const row = cell.getRow();
            const d = row.getData();
            const value = cell.getValue();

            if (field === 'STANDARD_PRICE') {
                const sku = d['(Child) sku'] || d.sku || d.SKU;
                const std = parseFloat(value);
                if (!sku || !isFinite(std) || std <= 0) {
                    row.update({ STANDARD_PRICE: null, standard_price: null });
                    return;
                }
                $.ajax({
                    url: '/save-amazon-sprice',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        sprice: std,
                        is_standard_price: 1
                    },
                    success: function(response) {
                        const saved = parseFloat(response.data || response.STANDARD_PRICE || std) || std;
                        applyPpStandardPriceToLinkedRows(sku, saved, response.applied_skus);
                        const n = Array.isArray(response.applied_skus) ? response.applied_skus.length : 1;
                        showToast(n > 1 ? ('Std Prc saved for ' + n + ' linked SKUs') : 'Std Prc saved', 'success');
                    },
                    error: function() {
                        showToast('Failed to save Std Prc', 'error');
                    }
                });
                return;
            }

            if (field !== 'SPRICE') return;
            const newSprice = parseFloat(cell.getValue()) || 0;
            const percentage = {{ $ppPercentage }} / 100;
            const lp   = d.LP_productmaster || 0;
            // Ship excluded from Purchasing Power formulas
            const sgpft = newSprice > 0 ? Math.round(((newSprice * percentage - lp) / newSprice) * 10000) / 100 : 0;
            const sroi  = lp > 0 ? Math.round(((newSprice * percentage - lp) / lp) * 10000) / 100 : 0;
            row.update({ SGPFT: sgpft, SPFT: sgpft, SROI: sroi, has_custom_sprice: true });

            // Auto-save immediately — no Send button needed
            saveSpriceWithRetry(d['(Child) sku'], newSprice, row);
        });

        $(document).on('click', '.copy-sku-btn', function(e) {
            e.stopPropagation();
            navigator.clipboard.writeText($(this).data('sku')).then(() => showToast(`Copied: ${$(this).data('sku')}`, 'success'));
        });

        function applyFilters() {
            if (window.ParentExpand && ParentExpand.isExpanded()) {
                ParentExpand.beforeFilters(function(){ applyFilters(); });
                return;
            }
            const inv   = $('#inventory-filter').val();
            const nrl   = $('#nrl-filter').val();
            const gpft  = $('#gpft-filter').val();
            const cvrF  = $('#cvr-filter').val();
            const dil   = $('#dil-filter').val();
            const roi   = $('#roi-filter').val();
            table.clearFilter();

            if (inv === 'zero') table.addFilter('INV', '=', 0);
            else if (inv === 'more') table.addFilter('INV', '>', 0);

            if (nrl === 'REQ') table.addFilter('nr_req', '=', 'REQ');
            else if (nrl === 'NR') table.addFilter('nr_req', '=', 'NR');

            if (gpft !== 'all') {
                if (gpft === 'negative') table.addFilter('GPFT%', '<', 0);
                else if (gpft === '50plus') table.addFilter('GPFT%', '>=', 50);
                else { const [min, max] = gpft.split('-').map(Number); table.addFilter('GPFT%', '>=', min); table.addFilter('GPFT%', '<', max); }
            }

            if (cvrF !== 'all') {
                table.addFilter(function(d) {
                    const ov = parseFloat(d.L30) || 0;
                    const sold = parseFloat(d['PP L30']) || 0;
                    const cvrPercent = ov > 0 ? (sold / ov) * 100 : 0;
                    const cvrRounded = Math.round(cvrPercent * 100) / 100;
                    if (cvrF === '0-0') return cvrRounded === 0;
                    if (cvrF === '0-3') return cvrRounded > 0 && cvrRounded <= 3;
                    if (cvrF === '3-7') return cvrRounded > 3 && cvrRounded <= 7;
                    if (cvrF === '7-13') return cvrRounded > 7 && cvrRounded <= 13;
                    if (cvrF === '13plus') return cvrRounded > 13;
                    return true;
                });
            }

            // ROI% filter (same as AliExpress)
            if (roi !== 'all') {
                table.addFilter(function(d) {
                    const roiVal = parseFloat(d['ROI%']) || 0;
                    if (roi === 'lt40')  return roiVal < 40;
                    if (roi === '40-75') return roiVal >= 40 && roiVal < 75;
                    if (roi === '75-125') return roiVal >= 75 && roiVal < 125;
                    if (roi === 'gt125') return roiVal >= 125;
                    return true;
                });
            }

            if (dil !== 'all') {
                table.addFilter(function(data) {
                    const inv2 = parseFloat(data.INV) || 0, l30 = parseFloat(data.L30) || 0;
                    const d2 = inv2 === 0 ? 0 : (l30 / inv2) * 100;
                    if (dil === 'red') return d2 < 16.66;
                    if (dil === 'yellow') return d2 >= 16.66 && d2 < 25;
                    if (dil === 'green') return d2 >= 25 && d2 < 50;
                    if (dil === 'pink') return d2 >= 50;
                    return true;
                });
            }

            const soldFilter = $('#sold-filter').val();
            if (soldFilter === 'zero') table.addFilter('PP L30', '=', 0);
            else if (soldFilter === 'sold') table.addFilter('PP L30', '>', 0);
            if (lessAmzFilterActive) table.addFilter(d => { const mc = parseFloat(d['PP Price']) || 0, amz = parseFloat(d['A Price']) || 0; return amz > 0 && mc > 0 && mc < amz; });
            if (moreAmzFilterActive) table.addFilter(d => { const mc = parseFloat(d['PP Price']) || 0, amz = parseFloat(d['A Price']) || 0; return amz > 0 && mc > 0 && mc > amz; });
            if (missingFilterActive) table.addFilter(d => { return (d.nr_req || 'REQ') === 'REQ' && (parseFloat(d.INV) || 0) > 0 && (parseFloat(d['PP Price']) || 0) === 0; });
            if (mappingFilterActive) table.addFilter(d => {
                const ourInv = parseFloat(d.INV) || 0, mcInv = parseFloat(d['PP INV']) || 0, price = parseFloat(d['PP Price']) || 0;
                return (d.nr_req || 'REQ') === 'REQ' && ourInv > 0 && price > 0 && !ppInvPpStockWithinTolerance(ourInv, mcInv);
            });
            if (priceGtLmpFilterActive && window.PriceGtLmpBadge) {
                table.addFilter(function(data) {
                    return PriceGtLmpBadge.hasRedTriangle(data, 'PP Price');
                });
            }
            if (priceLt80LmpFilterActive && window.PriceLt80LmpBadge) {
                table.addFilter(function(data) {
                    return PriceLt80LmpBadge.hasPurpleTriangle(data, 'PP Price');
                });
            }

            updateSummary();
        }

        if (window.PriceGtLmpBadge) {
            PriceGtLmpBadge.bind({
                badge: '#purchasingpower-price-gt-lmp-badge',
                getActive: function() { return priceGtLmpFilterActive; },
                onToggle: function(on) {
                    priceGtLmpFilterActive = on;
                    applyFilters();
                }
            });
        }
        if (window.PriceLt80LmpBadge) {
            PriceLt80LmpBadge.bind({
                badge: '#purchasingpower-price-lt80-lmp-badge',
                getActive: function() { return priceLt80LmpFilterActive; },
                onToggle: function(on) {
                    priceLt80LmpFilterActive = on;
                                        applyFilters();
                }
            });
        }

        $('#inventory-filter, #nrl-filter, #gpft-filter, #cvr-filter, #dil-filter, #roi-filter, #sold-filter').on('change', function() { applyFilters(); });

        function updateSummary() {
            const data = table.getData('active').filter(r => !(r.Parent && r.Parent.startsWith('PARENT')));
            let totalPft = 0, totalSales = 0, totalPrice = 0, priceCount = 0;
            let totalInv = 0, totalL30 = 0, zeroSold = 0, totalDil = 0, dilCount = 0;
            let totalCogs = 0, missingCount = 0, mappingCount = 0;
            let totalPpStock = 0;

            data.forEach(row => {
                totalPft   += parseFloat(row.Profit) || 0;
                totalSales += parseFloat(row['Sales L30']) || 0;

                const price = parseFloat(row['PP Price']) || 0, inv = parseFloat(row.INV) || 0, nrReq = row.nr_req || 'REQ';
                if (price > 0) { totalPrice += price; priceCount++; }
                else if (nrReq === 'REQ' && inv > 0) missingCount++;

                totalInv  += inv;
                totalL30  += parseFloat(row['PP L30']) || 0;
                if ((parseFloat(row['PP L30']) || 0) === 0) zeroSold++;

                const dil = parseFloat(row['PP Dil%']) || 0;
                if (dil > 0) { totalDil += dil; dilCount++; }

                const lp = parseFloat(row.LP_productmaster) || 0, l30 = parseFloat(row['PP L30']) || 0;
                totalCogs += lp * l30;

                if (nrReq === 'REQ' && inv > 0 && price > 0) {
                    if (!ppInvPpStockWithinTolerance(inv, row['PP INV'])) mappingCount++;
                }

                totalPpStock += parseFloat(row['PP INV']) || 0;
            });

            const avgPrice = priceCount > 0 ? totalPrice / priceCount : 0;
            const avgDil = dilCount > 0 ? totalDil / dilCount : 0;
            // Weighted ROI % = (Σ Profit ÷ Σ COGS) × 100.
            // Matches /all-marketplace-master's G ROI cell exactly (same formula
            // /bestbuy/daily-sales & /aliexpress-tabulator use). The simple-average
            // version that lived here (Σ ROI% ÷ count) was misleading — a single
            // SKU with $1 COGS and 800% ROI swung the badge a lot even though it
            // contributed almost no profit. Weighted = the right number for
            // leadership and matches the channel master row.
            const roiPctWeighted = totalCogs > 0 ? (totalPft / totalCogs) * 100 : 0;

            $('#total-pft-amt-badge').text(`Total PFT: $${Math.round(totalPft).toLocaleString()}`);
            $('#total-sales-amt-badge').text(`Total Sales: $${Math.round(totalSales).toLocaleString()}`);
            $('#avg-price-badge').text(`Avg Price: $${avgPrice.toFixed(2)}`);
            // PFT $ (sum of Profit) and GPFT % weighted by sales — same shape as
            // /all-marketplace-master's Total PFT + Gprofit% cells and the
            // /purchasing-power-sales page badges. Weighted GPFT respects sales
            // volume per SKU (industry-standard margin), unlike a simple per-row
            // average that lets low-sales SKUs swing the % unfairly.
            const gpftPctWeighted = totalSales > 0 ? (totalPft / totalSales) * 100 : 0;
            $('#pft-badge').text(`PFT: $${Math.round(totalPft).toLocaleString()}`);
            $('#gpft-pct-badge').text(`GPFT: ${gpftPctWeighted.toFixed(1)}%`);
            $('#total-inv-badge').text(`Total INV: ${totalInv.toLocaleString()}`);
            $('#total-l30-badge').text(`Total PP L30: ${totalL30.toLocaleString()}`);
            $('#zero-sold-count-badge').text(`0 Sold: ${zeroSold}`);
            $('#avg-dil-badge').text(`DIL%: ${(avgDil * 100).toFixed(1)}%`);
            $('#total-cogs-badge').text(`COGS: $${Math.round(totalCogs).toLocaleString()}`);
            $('#roi-percent-badge').text(`GROI: ${roiPctWeighted.toFixed(1)}%`);
            // Purchasing Power has no ads — Ads%=0, NPFT=GPFT, NROI=GROI (same as /all-marketplace-master).
            $('#ads-percent-badge').text('Ads: 0%');
            $('#npft-percent-badge').text('NPFT: ' + gpftPctWeighted.toFixed(1) + '%');
            $('#nroi-percent-badge').text('NROI: ' + roiPctWeighted.toFixed(1) + '%');
            $('#missing-badge').text(`MISSING: ${missingCount}`);
            $('#mapping-badge').text(`MAPPING: ${mappingCount}`);
            $('#total-pp-stock-badge').text(`PP Stock: ${totalPpStock.toLocaleString()}`);
            if (window.PriceGtLmpBadge && table) {
                PriceGtLmpBadge.update('#purchasingpower-price-gt-lmp-badge', table.getData(), 'purchasingpower', 'PP Price');
                if (window.PriceLt80LmpBadge) {
                    PriceLt80LmpBadge.update('#purchasingpower-price-lt80-lmp-badge', table.getData(), 'purchasingpower', 'PP Price');
                }
            }
        }

        function buildColumnDropdown() {
            let html = '';
            table.getColumns().forEach(col => {
                const field = col.getField(), title = col.getDefinition().title;
                if (field && field !== '_select' && title) {
                    html += `<li class="dropdown-item"><label style="cursor:pointer;display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" class="column-toggle" data-field="${field}" ${col.isVisible() ? 'checked' : ''}>
                        ${title.replace(/<[^>]*>/g, '')}
                    </label></li>`;
                }
            });
            $('#column-dropdown-menu').html(html);
        }

        function saveColumnVisibilityToServer() {
            const visibility = {};
            table.getColumns().forEach(col => { if (col.getField() && col.getField() !== '_select') visibility[col.getField()] = col.isVisible(); });
            $.ajax({ url: '/pp-pricing-column-visibility', method: 'POST', data: { visibility, _token: '{{ csrf_token() }}' } });
        }

        function applyColumnVisibilityFromServer() {
            $.ajax({
                url: '/pp-pricing-column-visibility', method: 'GET',
                success: function(visibility) {
                    if (visibility && Object.keys(visibility).length > 0) {
                        Object.keys(visibility).forEach(field => {
                            const col = table.getColumn(field);
                            if (col) visibility[field] ? col.show() : col.hide();
                        });
                        buildColumnDropdown();
                    }
                }
            });
        }

        if (window.ParentExpand) {
            ParentExpand.configure({
                parentField: 'Parent',
                skuField: '(Child) sku',
                getTable: () => table,
                getDataset: () => allTableData,
                onAfterExpand: () => { if (typeof updateSummary === 'function') updateSummary(); },
                onCollapse: () => { if (typeof applyFilters === 'function') applyFilters(); },
            });
            ParentExpand.bind();
        }

        table.on('tableBuilt', function() { buildColumnDropdown(); applyColumnVisibilityFromServer(); });
        table.on('dataLoaded', function(data) {
            allTableData = Array.isArray(data) ? data : [];
            if (window.ParentExpand) ParentExpand.captureDataset(allTableData);
            setTimeout(function() { applyFilters(); updateSummary(); }, 100);
        });
        table.on('renderComplete', function() { setTimeout(function() { updateSummary(); }, 100); });

        document.getElementById('column-dropdown-menu').addEventListener('change', function(e) {
            if (e.target.classList.contains('column-toggle')) {
                const col = table.getColumn(e.target.dataset.field);
                if (col) { e.target.checked ? col.show() : col.hide(); saveColumnVisibilityToServer(); }
            }
        });

        document.getElementById('show-all-columns-btn').addEventListener('click', function() {
            table.getColumns().forEach(col => { if (col.getField() !== '_select') col.show(); });
            buildColumnDropdown(); saveColumnVisibilityToServer();
        });

        document.getElementById('export-btn').addEventListener('click', function() {
            const visibleCols = table.getColumns().filter(c => c.isVisible() && c.getField() !== '_select');
            const headers = visibleCols.map(c => c.getDefinition().title || c.getField());
            const rows = table.getData('active').map(row => visibleCols.map(col => {
                let v = row[col.getField()];
                if (v === null || v === undefined) return '';
                if (typeof v === 'number') return parseFloat(v.toFixed(2));
                return v;
            }));
            let csv = [headers, ...rows].map(row => row.map(cell => {
                if (typeof cell === 'string' && (cell.includes(',') || cell.includes('"') || cell.includes('\n')))
                    return '"' + cell.replace(/"/g, '""') + '"';
                return cell;
            }).join(',')).join('\n');
            const link = document.createElement('a');
            link.setAttribute('href', URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' })));
            link.setAttribute('download', 'purchasing_power_pricing_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link); link.click(); document.body.removeChild(link);
            showToast('Export downloaded!', 'success');
        });

        ppBindPriceRuleUi();
    });
</script>
@endsection
