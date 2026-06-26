@extends('layouts.vertical', ['title' => 'TopDawg - Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl; text-orientation: mixed; white-space: nowrap;
            transform: rotate(180deg); height: 80px; display: flex; align-items: center;
            justify-content: center; font-size: 11px; font-weight: 600;
        }
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        #summary-stats .badge.active-filter {
            box-shadow: 0 0 0 3px rgba(255,255,255,.85), 0 0 0 5px currentColor;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'TopDawg - Analytics',
        'sub_title' => '',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <p class="text-muted small mb-2">Price &amp; stock from <code>topdawg_products</code> (API). L30/L60 from order metrics. Run <code>php artisan topdawg:fetch</code> on server to refresh.</p>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="inventory-filter" class="form-select form-select-sm" style="width:130px;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>
                    <select id="td-stock-filter" class="form-select form-select-sm" style="width:130px;">
                        <option value="all">TD Stock</option>
                        <option value="zero">0 TD Stock</option>
                        <option value="more">More than 0</option>
                    </select>
                    <select id="nrl-filter" class="form-select form-select-sm" style="width:130px;">
                        <option value="all">All Status</option>
                        <option value="REQ" selected>REQ Only</option>
                        <option value="NR">NR Only</option>
                    </select>
                    <select id="gpft-filter" class="form-select form-select-sm" style="width:130px;">
                        <option value="all">GPFT%</option>
                        <option value="negative">Negative</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30plus">30%+</option>
                    </select>
                    {{-- Sold dropdown (mirrors Amazon tabulator + every other /pricing page).
                         Backed by `TD L30`:
                           all  → no filter
                           sold → TD L30 > 0
                           zero → TD L30 = 0
                         Single source of truth — the #zero-sold-badge / #more-sold-badge clicks
                         and the ?badge=zero_sold|more_sold URL deep-link all write into this
                         dropdown so badges + dropdown + URL stay in sync. --}}
                    <select id="sold-filter" class="form-select form-select-sm" style="width:130px;"
                            title="Filter by TD L30 sold quantity">
                        <option value="all">Sold</option>
                        <option value="sold">Sold &gt; 0</option>
                        <option value="zero">0 Sold</option>
                    </select>
                    <a href="{{ route('all.marketplace.master') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-th-large"></i> All Marketplace Master
                    </a>
                    <button id="export-btn" class="btn btn-sm btn-info"><i class="fas fa-file-csv"></i> Export CSV</button>

                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-arrow-down"></i> Decrease Mode
                    </button>
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-arrow-up"></i> Increase Mode
                    </button>
                    <button id="same-price-btn" class="btn btn-sm btn-info" title="Apply ONE price (entered in the box) to every selected SKU">
                        <i class="fas fa-equals"></i> Same Price Mode
                    </button>

                    {{-- Target ROI% bulk control — back-solves S PRC for selected rows so SROI = Target ROI%.
                         Formula: sprice = (LP × (1 + ROI%/100) + Ship) / margin   (margin = $topdawgPercentage / 100, default 0.95) --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light"
                        id="target-roi-controls"
                        title="Target ROI% — sets S PRC = (LP × (1 + Target ROI%/100) + Ship) / {{ $topdawgPercentage ?? 95 }}% on every selected row (back-solves so SROI column equals the target)">
                        <label for="target-roi-input" class="form-label mb-0 small fw-bold text-nowrap">
                            Target ROI%:
                        </label>
                        <input type="number" id="target-roi-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target ROI% applied to all selected rows when you click 'Apply S PRC'">
                        <button id="apply-target-roi-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S PRC = (LP × (1 + Target ROI%/100) + Ship) / {{ $topdawgPercentage ?? 95 }}% for every selected row">
                            <i class="fas fa-calculator"></i> Apply S PRC
                        </button>
                    </div>

                    {{-- Target GPFT% bulk control — back-solves S PRC for selected rows so SGPFT = Target GPFT%.
                         Formula: sprice = (LP + Ship) / (margin − GPFT%/100). Target GPFT% must be < margin*100. --}}
                    <div class="d-inline-flex align-items-center gap-1 ms-2 p-1 border rounded bg-light"
                        id="target-gpft-controls"
                        title="Target GPFT% — sets S PRC = (LP + Ship) / ({{ $topdawgPercentage ?? 95 }}% − Target GPFT%/100) on every selected row">
                        <label for="target-gpft-input" class="form-label mb-0 small fw-bold text-nowrap">
                            Target GPFT%:
                        </label>
                        <input type="number" id="target-gpft-input" class="form-control form-control-sm text-end"
                            placeholder="e.g. 30" step="0.1" style="width: 80px;"
                            title="Target GPFT% applied to all selected rows when you click 'Apply S PRC'. Must be less than the TopDawg take-home margin ({{ $topdawgPercentage ?? 95 }}%).">
                        <button id="apply-target-gpft-btn" class="btn btn-sm btn-success" type="button"
                            title="Compute & save S PRC = (LP + Ship) / ({{ $topdawgPercentage ?? 95 }}% − Target GPFT%/100) for every selected row">
                            <i class="fas fa-calculator"></i> Apply S PRC
                        </button>
                    </div>
                </div>
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary ({{ $topdawgPercentage ?? 95 }}% Margin)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge fs-6 p-2" id="total-sales-badge"
                              style="background:#198754;color:#fff;font-weight:bold;"
                              title="Σ (TD L30 × TD Price) across visible rows — last-30-day TopDawg sales revenue">Sales: $0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-td-l30-badge" style="color:#000;font-weight:bold;" title="Sum of TD L30 on filtered rows">TD L30: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-badge" style="color:#fff;font-weight:bold;cursor:pointer;" title="SKUs with TD L30 = 0">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-badge" style="background:#28a745;color:#fff;font-weight:bold;cursor:pointer;" title="SKUs with TD L30 &gt; 0">&gt; 0 Sold: 0</span>
                        {{-- GPFT / GROI — weighted profitability for filtered rows.
                             Same shape as /purchasing-power-pricing GPFT badge:
                               Profit (per row) = (TD Price × {{ $topdawgPercentage ?? 95 }}% − LP − Ship) × TD L30
                               Sales L30        = TD Price × TD L30
                               COGS             = LP × TD L30
                             Ship comes from CP Master (ProductMaster.Values.ship → Ship_productmaster).
                             GPFT% = (Σ Profit ÷ Σ Sales L30) × 100   (weighted gross margin)
                             GROI% = (Σ Profit ÷ Σ COGS) × 100        (weighted ROI) --}}
                        <span class="badge fs-6 p-2" id="gpft-pct-badge"
                              style="background:#6f42c1;color:#fff;font-weight:bold;"
                              title="Weighted Gross Profit %: (Σ Profit ÷ Σ Sales L30) × 100. Profit includes Ship from CP Master.">GPFT: 0%</span>
                        <span class="badge fs-6 p-2" id="groi-pct-badge"
                              style="background:#0d6efd;color:#fff;font-weight:bold;"
                              title="Weighted Gross ROI %: (Σ Profit ÷ Σ COGS) × 100. COGS = LP × TD L30; Profit includes Ship from CP Master.">GROI: 0%</span>
                        <span class="badge bg-danger fs-6 p-2" id="missing-badge" style="color:#fff;font-weight:bold;cursor:pointer;" title="REQ + INV&gt;0 + TD Price=0">Missing L: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="nmap-badge" style="color:#fff;font-weight:bold;cursor:pointer;" title="|INV − TD Stock| &gt; 3">N Map: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- Pricing-mode input bar (shown when a mode is active + SKUs selected) -->
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
                <div id="topdawg-table-wrapper" style="height:calc(100vh - 240px);display:flex;flex-direction:column;">
                    <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="max-width: 220px;">
                        <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="max-width: 220px;">
                    </div>
                    <div id="topdawg-pricing-table" style="flex:1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Links Modal -->
    <div class="modal fade" id="tdEditLinksModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="tdEditLinksSku">
                    <p class="mb-3"><strong>SKU:</strong> <span id="tdEditLinksSkuDisplay"></span></p>
                    <div class="mb-3">
                        <label for="tdEditSellerLink" class="form-label">S Link (Seller)</label>
                        <input type="url" class="form-control" id="tdEditSellerLink" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label for="tdEditBuyerLink" class="form-label">B Link (Buyer)</label>
                        <input type="url" class="form-control" id="tdEditBuyerLink" placeholder="https://...">
                    </div>
                    <div id="tdEditLinksError" class="text-danger small" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="tdSaveLinksBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const TD_MAP_TOLERANCE = 3;
    const TD_PERCENTAGE    = {{ $topdawgPercentage ?? 95 }} / 100;
    let table = null;
    // zeroSoldFilter / moreSoldFilter removed — Sold filter is now owned by the
    // #sold-filter dropdown (driven by badge clicks and the ?badge=zero_sold|more_sold URL).
    let missingFilter = false, nmapFilter = false;

    // Pricing-mode state
    let tdDecreaseModeActive = false;
    let tdIncreaseModeActive = false;
    let tdSamePriceModeActive = false;
    let tdSelectedSkus = new Set();

    function tdAnyModeActive() {
        return tdDecreaseModeActive || tdIncreaseModeActive || tdSamePriceModeActive;
    }

    function tdResetDecreaseBtn() {
        $('#decrease-btn').removeClass('btn-danger').addClass('btn-warning')
            .html('<i class="fas fa-arrow-down"></i> Decrease Mode');
    }
    function tdResetIncreaseBtn() {
        $('#increase-btn').removeClass('btn-danger').addClass('btn-success')
            .html('<i class="fas fa-arrow-up"></i> Increase Mode');
    }
    function tdResetSamePriceBtn() {
        $('#same-price-btn').removeClass('btn-danger').addClass('btn-info')
            .html('<i class="fas fa-equals"></i> Same Price Mode');
    }

    function tdSyncDiscountInputUi() {
        const $input = $('#discount-percentage-input');
        if (tdSamePriceModeActive) {
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

    function tdShowSelectColumn(show) {
        if (!table) return;
        const col = table.getColumn('_select');
        if (col) {
            if (show) col.show(); else col.hide();
        }
    }

    function tdUpdateSelectedCount() {
        const count = tdSelectedSkus.size;
        $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
        $('#discount-input-container').toggle(tdAnyModeActive() && count > 0);
    }

    function tdUpdateSelectAllHeaderCheckbox() {
        const el = document.getElementById('td-select-all');
        if (!el || !table) return;
        const rows = table.getRows('active').filter(r => {
            const p = (r.getData().Parent || '');
            return !(p && String(p).toUpperCase().startsWith('PARENT'));
        });
        if (!rows.length) { el.checked = false; el.indeterminate = false; return; }
        let selected = 0;
        rows.forEach(r => {
            const sku = String(r.getData()['(Child) sku'] || '');
            if (sku && tdSelectedSkus.has(sku)) selected++;
        });
        el.checked = selected === rows.length && rows.length > 0;
        el.indeterminate = selected > 0 && selected < rows.length;
    }

    function tdRoundToRetailPrice(price) {
        const p = parseFloat(price) || 0;
        if (p < 20.99) return +p.toFixed(2);
        return +(Math.ceil(p) - 0.01).toFixed(2);
    }

    function tdShowToast(msg, type) {
        type = type || 'success';
        if (window.toastr) { toastr[type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'success'](msg); return; }
        let c = document.querySelector('.toast-container');
        if (!c) { c = document.createElement('div'); c.className = 'toast-container'; document.body.appendChild(c); }
        const bg = type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#198754';
        const t = document.createElement('div');
        t.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;min-width:240px;color:#fff;background:' + bg + ';padding:12px 16px;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.18);font-size:14px;';
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    function tdSaveSpriceUpdates(updates) {
        $.ajax({
            url: "{{ route('topdawg.save.sprice') }}",
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', updates: updates },
            success: function(res) {
                if (!res || res.success !== true) {
                    tdShowToast((res && res.message) || 'Failed to save SPRICE', 'error');
                }
            },
            error: function(xhr) {
                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to save SPRICE';
                tdShowToast(msg, 'error');
            }
        });
    }

    function tdApplyDiscount() {
        const discountType = $('#discount-type-select').val();
        const discountValue = parseFloat($('#discount-percentage-input').val());

        if (!tdAnyModeActive()) { tdShowToast('Turn on Decrease, Increase, or Same Price mode first', 'error'); return; }
        if (isNaN(discountValue) || discountValue <= 0) {
            tdShowToast(tdSamePriceModeActive ? 'Please enter a price (e.g. 19.99)' : 'Please enter a valid value', 'error');
            return;
        }
        if (tdSelectedSkus.size === 0) { tdShowToast('Please select at least one SKU', 'error'); return; }

        const updates = [];
        let updatedCount = 0;

        table.getRows('active').forEach(function(row) {
            const d = row.getData();
            const sku = d && d['(Child) sku'] != null ? String(d['(Child) sku']) : '';
            if (!sku || !tdSelectedSkus.has(sku)) return;

            const currentPrice = parseFloat(d['TD Price']) || 0;
            if (!tdSamePriceModeActive && currentPrice <= 0) return;

            let newSprice;
            if (tdSamePriceModeActive) {
                newSprice = Math.max(0.99, discountValue);
            } else if (discountType === 'percentage') {
                newSprice = tdIncreaseModeActive
                    ? currentPrice * (1 + discountValue / 100)
                    : currentPrice * (1 - discountValue / 100);
            } else {
                newSprice = tdIncreaseModeActive
                    ? currentPrice + discountValue
                    : currentPrice - discountValue;
            }

            newSprice = Math.max(0.99, tdRoundToRetailPrice(newSprice));

            const lp   = parseFloat(d.LP_productmaster) || 0;
            const ship = parseFloat(d.Ship_productmaster) || 0;
            const sgpft = newSprice > 0 ? Math.round(((newSprice * TD_PERCENTAGE - lp - ship) / newSprice) * 100) : 0;
            const sroi  = lp > 0 ? Math.round(((newSprice * TD_PERCENTAGE - lp - ship) / lp) * 100) : 0;

            row.update({ SPRICE: newSprice, SGPFT: sgpft, SROI: sroi });
            updates.push({ sku: sku, sprice: newSprice });
            updatedCount++;
        });

        if (!updates.length) { tdShowToast('No matching selected rows in the current view.', 'warning'); return; }

        tdSaveSpriceUpdates(updates);
        const action = tdSamePriceModeActive ? 'Same Price' : (tdIncreaseModeActive ? 'Increase' : 'Decrease');
        tdShowToast(`${action} applied to ${updatedCount} SKU(s)`, 'success');
        $('#discount-percentage-input').val('');
    }

    function tdClearSpriceForSelected() {
        if (tdSelectedSkus.size === 0) { tdShowToast('Select SKUs first', 'error'); return; }
        if (!confirm(`Clear SPRICE for ${tdSelectedSkus.size} SKU(s)?`)) return;

        const updates = [];
        table.getRows('active').forEach(function(row) {
            const d = row.getData();
            const sku = d && d['(Child) sku'] != null ? String(d['(Child) sku']) : '';
            if (!sku || !tdSelectedSkus.has(sku)) return;
            row.update({ SPRICE: null, SGPFT: null, SROI: null });
            updates.push({ sku: sku, sprice: null });
        });

        if (!updates.length) return;
        tdSaveSpriceUpdates(updates);
        tdShowToast(`Cleared SPRICE for ${updates.length} SKU(s)`, 'success');
    }

    function applyUrlBadgeFilter() {
        const p = new URLSearchParams(window.location.search).get('badge');
        // Reset every badge-style filter (including the dropdown-backed Sold filter)
        // before honoring the URL deep-link, so only one filter is active afterwards.
        missingFilter = nmapFilter = false;
        $('#sold-filter').val('all');
        if (p === 'missing') missingFilter = true;
        else if (p === 'nmap') nmapFilter = true;
        else if (p === 'zero_sold') $('#sold-filter').val('zero');
        else if (p === 'more_sold') $('#sold-filter').val('sold');
    }

    function setActiveBadges() {
        $('#missing-badge').toggleClass('active-filter', missingFilter);
        $('#nmap-badge').toggleClass('active-filter', nmapFilter);
        // Sold-badge active state is now derived from the #sold-filter dropdown value,
        // which is the single source of truth for the Sold filter.
        const sold = $('#sold-filter').val();
        $('#zero-sold-badge').toggleClass('active-filter', sold === 'zero');
        $('#more-sold-badge').toggleClass('active-filter', sold === 'sold');
    }

    function getSummaryRows() {
        if (!table) return [];
        const rows = table.getRows('active');
        const data = (rows && rows.length)
            ? rows.map(r => r.getData())
            : (table.getData() || []);
        return data.filter(r => !(r.Parent && String(r.Parent).toUpperCase().startsWith('PARENT')));
    }

    function updateSummary() {
        const data = getSummaryRows();
        let totalTdL30 = 0;
        let zeroSold = 0, moreSold = 0, missing = 0, nmapC = 0;
        // Weighted GPFT / GROI accumulators — same shape as /purchasing-power-pricing
        // (gpft-pct-badge / roi-percent-badge), but Profit *includes* Ship from CP
        // Master (ProductMaster.Values.ship → Ship_productmaster). Single pass over
        // visible rows so the badges always reflect the active filters.
        let totalProfit = 0, totalSalesL30 = 0, totalCogs = 0;

        data.forEach(row => {
            const tdL30 = parseInt(row['TD L30'], 10) || 0;
            totalTdL30 += tdL30;
            tdL30 === 0 ? zeroSold++ : moreSold++;

            const inv = parseFloat(row.INV) || 0;
            const nrReq = row.nr_req || 'REQ';
            const isMissing = row.Missing === 'M';
            if (isMissing && nrReq === 'REQ' && inv > 0) missing++;
            const mapVal = row.MAP || '';
            if (nrReq === 'REQ' && inv > 0 && !isMissing) {
                if (mapVal.includes('N Map|')) nmapC++;
            }

            const price = parseFloat(row['TD Price'])           || 0;
            const lp    = parseFloat(row.LP_productmaster)      || 0;
            const ship  = parseFloat(row.Ship_productmaster)    || 0;
            totalSalesL30 += price * tdL30;
            totalCogs     += lp    * tdL30;
            totalProfit   += (price * TD_PERCENTAGE - lp - ship) * tdL30;
        });

        const gpftPct = totalSalesL30 > 0 ? (totalProfit / totalSalesL30) * 100 : 0;
        const groiPct = totalCogs     > 0 ? (totalProfit / totalCogs)     * 100 : 0;

        $('#total-td-l30-badge').text('TD L30: ' + totalTdL30.toLocaleString());
        $('#total-sales-badge').text('Sales: $' + Math.round(totalSalesL30).toLocaleString());
        $('#zero-sold-badge').text('0 Sold: ' + zeroSold.toLocaleString());
        $('#more-sold-badge').text('> 0 Sold: ' + moreSold.toLocaleString());
        $('#gpft-pct-badge').text('GPFT: ' + Math.round(gpftPct) + '%');
        $('#groi-pct-badge').text('GROI: ' + Math.round(groiPct) + '%');
        $('#missing-badge').text('Missing L: ' + missing.toLocaleString());
        $('#nmap-badge').text('N Map: ' + nmapC.toLocaleString());
    }

    function applyFilters() {
        if (!table) return;
        table.clearFilter();

        const invF = $('#inventory-filter').val();
        if (invF === 'zero') table.addFilter('INV', '=', 0);
        if (invF === 'more') table.addFilter('INV', '>', 0);

        const tdF = $('#td-stock-filter').val();
        if (tdF === 'zero') table.addFilter('TD Stock', '=', 0);
        if (tdF === 'more') table.addFilter('TD Stock', '>', 0);

        const nrl = $('#nrl-filter').val();
        if (nrl !== 'all') table.addFilter('nr_req', '=', nrl);

        const gpft = $('#gpft-filter').val();
        if (gpft !== 'all') {
            table.addFilter(data => {
                const g = parseFloat(data['GPFT%']) || 0;
                if (gpft === 'negative') return g < 0;
                if (gpft === '0-10') return g >= 0 && g < 10;
                if (gpft === '10-20') return g >= 10 && g < 20;
                if (gpft === '20-30') return g >= 20 && g < 30;
                if (gpft === '30plus') return g >= 30;
                return true;
            });
        }

        // Sold filter — driven by the #sold-filter dropdown (single source of truth).
        const soldFilter = $('#sold-filter').val();
        if (soldFilter === 'zero') {
            table.addFilter(data => (parseInt(data['TD L30'], 10) || 0) === 0);
        } else if (soldFilter === 'sold') {
            table.addFilter(data => (parseInt(data['TD L30'], 10) || 0) > 0);
        }
        if (missingFilter) table.addFilter(data => data.Missing === 'M' && data.nr_req === 'REQ' && (parseFloat(data.INV) || 0) > 0);
        if (nmapFilter) table.addFilter(data => (data.MAP || '').includes('N Map|') && data.nr_req === 'REQ' && (parseFloat(data.INV) || 0) > 0 && data.Missing !== 'M');

        setActiveBadges();
        updateSummary();
    }

    $(document).ready(function() {
        applyUrlBadgeFilter();

        table = new Tabulator('#topdawg-pricing-table', {
            ajaxURL: '/topdawg-data-json',
            layout: 'fitDataStretch',
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [50, 100, 200, 500],
            initialSort: [{ column: 'TD L30', dir: 'desc' }],
            columns: [
                {
                    // Selection column (hidden until a pricing mode is active).
                    title: '<div style="display:flex;align-items:center;justify-content:center;"><input type="checkbox" id="td-select-all" title="Select / clear all filtered SKUs"></div>',
                    field: '_select',
                    width: 50,
                    frozen: true,
                    headerSort: false,
                    visible: false,
                    hozAlign: 'center',
                    formatter: function(cell) {
                        const sku = cell.getRow().getData()['(Child) sku'];
                        if (!sku) return '';
                        const safe = String(sku).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                        const checked = tdSelectedSkus.has(String(sku)) ? 'checked' : '';
                        return `<input type="checkbox" class="td-sku-chk" data-sku="${safe}" ${checked}>`;
                    }
                },
                { title: 'Parent', field: 'Parent', frozen: true, width: 150, visible: false },
                { title: 'Image', field: 'image_path', width: 80, headerSort: false,
                    formatter: c => {
                        const v = c.getValue();
                        return v ? `<img src="${v}" alt="Product" style="width:50px;height:50px;object-fit:cover;">` : '';
                    }},
                { title: 'SKU', field: '(Child) sku', frozen: true, width: 250, headerFilter: 'input',
                    cssClass: 'text-primary fw-bold' },
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
                        openTdEditLinksModal(cell.getRow());
                    }
                },
                { title: 'INV', field: 'INV', hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'OV L30', field: 'L30', hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'Dil', field: 'Dil', hozAlign: 'center', width: 50, sorter: 'number',
                    formatter: c => {
                        const row = c.getRow().getData();
                        const inv = parseFloat(row.INV) || 0;
                        const ovL30 = parseFloat(row.L30) || 0;
                        if (inv === 0) return '<span style="color:#6c757d;">0%</span>';
                        const dil = (ovL30 / inv) * 100;
                        let color = '';
                        if (dil < 16.66) color = '#a00211';
                        else if (dil < 25) color = '#ffc107';
                        else if (dil < 50) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${Math.round(dil)}%</span>`;
                    }},
                { title: 'TD L30', field: 'TD L30', hozAlign: 'center', width: 50, sorter: 'number' },
                { title: 'TD L60', field: 'TD L60', hozAlign: 'center', width: 50, sorter: 'number', visible: false },
                { title: 'TD Stock', field: 'TD Stock', hozAlign: 'center', width: 60, sorter: 'number',
                    formatter: c => {
                        const inv = parseFloat(c.getRow().getData().INV) || 0;
                        const td = parseFloat(c.getValue()) || 0;
                        const diff = Math.abs(inv - td);
                        const color = diff > TD_MAP_TOLERANCE ? '#dc3545' : '#28a745';
                        return `<span style="color:${color};font-weight:600;">${td}</span>`;
                    }},
                { title: 'Missing L', field: 'Missing', hozAlign: 'center', width: 70,
                    formatter: c => c.getValue() === 'M'
                        ? '<span style="color:#dc3545;font-weight:bold;background:#ffe6e6;padding:2px 6px;border-radius:3px;">M</span>'
                        : '' },
                { title: 'MAP', field: 'MAP', hozAlign: 'center', width: 90,
                    formatter: c => {
                        const v = c.getValue() || '';
                        if (v === 'Map') {
                            return '<span style="color:#28a745;font-weight:bold;background:#d4edda;padding:2px 6px;border-radius:3px;">MAP</span>';
                        }
                        if (v.includes('N Map|')) {
                            const diff = v.split('|')[1];
                            return `<span style="color:#dc3545;font-weight:bold;background:#f8d7da;padding:2px 6px;border-radius:3px;">N MP (${diff})</span>`;
                        }
                        return '';
                    }},
                { title: 'NR/REQ', field: 'nr_req', hozAlign: 'center', width: 60, headerSort: false,
                    formatter: c => {
                        let value = c.getValue();
                        if (!value || String(value).trim() === '') value = 'REQ';
                        return `<select class="form-select form-select-sm nr-req-dropdown"
                            style="border:1px solid #ddd;text-align:center;cursor:pointer;padding:2px 4px;font-size:16px;width:50px;height:28px;">
                            <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                            <option value="NR" ${value === 'NR' ? 'selected' : ''}>🔴</option>
                        </select>`;
                    },
                    cellClick: (e) => e.stopPropagation() },
                { title: 'Prc', field: 'TD Price', hozAlign: 'center', width: 70, sorter: 'number',
                    formatter: c => {
                        const v = parseFloat(c.getValue()) || 0;
                        if (v === 0) {
                            return '<span style="color:#a00211;font-weight:600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left:4px;"></i></span>';
                        }
                        return `$${v.toFixed(2)}`;
                    }},
                {
                    title: 'Sales', field: 'Sales', hozAlign: 'center', width: 80,
                    sorter: function(a, b, aRow, bRow) {
                        const av = (parseFloat(aRow.getData()['TD L30']) || 0) * (parseFloat(aRow.getData()['TD Price']) || 0);
                        const bv = (parseFloat(bRow.getData()['TD L30']) || 0) * (parseFloat(bRow.getData()['TD Price']) || 0);
                        return av - bv;
                    },
                    tooltip: 'TD L30 × TD Price (last-30-day TopDawg sales)',
                    formatter: c => {
                        const d = c.getRow().getData();
                        const l30 = parseFloat(d['TD L30']) || 0;
                        const price = parseFloat(d['TD Price']) || 0;
                        const sales = l30 * price;
                        if (sales <= 0) return '<span class="text-muted">-</span>';
                        return `<strong style="color:#198754;">$${sales.toFixed(2)}</strong>`;
                    }
                },
                { title: 'SPRICE', field: 'SPRICE', hozAlign: 'center', width: 80, sorter: 'number',
                    formatter: c => {
                        const v = c.getValue();
                        if (v === null || v === undefined || v === '') return '<span class="text-muted">-</span>';
                        return `<strong>$${Number(v).toFixed(2)}</strong>`;
                    }},
                { title: 'GPFT%', field: 'GPFT%', hozAlign: 'center', width: 50, sorter: 'number',
                    formatter: c => {
                        const percent = parseFloat(c.getValue());
                        if (isNaN(percent)) return '';
                        let color = '';
                        if (percent < 10) color = '#a00211';
                        else if (percent < 15) color = '#ffc107';
                        else if (percent < 20) color = '#3591dc';
                        else if (percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${percent.toFixed(0)}%</span>`;
                    }},
                { title: 'PFT%', field: 'PFT %', hozAlign: 'center', width: 50, sorter: 'number',
                    formatter: c => {
                        const percent = parseFloat(c.getValue());
                        if (isNaN(percent)) return '';
                        let color = '';
                        if (percent < 10) color = '#a00211';
                        else if (percent < 15) color = '#ffc107';
                        else if (percent < 20) color = '#3591dc';
                        else if (percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        return `<span style="color:${color};font-weight:600;">${percent.toFixed(0)}%</span>`;
                    }},
                { title: 'ROI%', field: 'ROI%', hozAlign: 'center', width: 50, sorter: 'number',
                    formatter: c => {
                        const percent = parseFloat(c.getValue());
                        if (isNaN(percent)) return '';
                        let color = '';
                        if (percent < 40) color = '#a00211';
                        else if (percent < 75) color = '#ffc107';
                        else if (percent < 125) color = '#28a745';
                        else color = '#d63384';
                        return `<span style="color:${color};font-weight:600;">${percent.toFixed(0)}%</span>`;
                    }},
                { title: 'Profit', field: 'Profit', hozAlign: 'center', width: 70, sorter: 'number', visible: false,
                    formatter: c => {
                        const v = parseFloat(c.getValue()) || 0;
                        const color = v >= 0 ? '#28a745' : '#a00211';
                        return `<span style="color:${color};font-weight:600;">$${v.toFixed(2)}</span>`;
                    }},
                { title: 'LP', field: 'LP_productmaster', hozAlign: 'center', width: 60, sorter: 'number',
                    formatter: c => '$' + (parseFloat(c.getValue()) || 0).toFixed(2) },
                { title: 'Ship', field: 'Ship_productmaster', hozAlign: 'center', width: 60, sorter: 'number',
                    formatter: c => '$' + (parseFloat(c.getValue()) || 0).toFixed(2) },
                { title: 'TDID', field: 'TDID', width: 120, visible: false },
                { title: 'State', field: 'listing_state', width: 70, visible: false },
            ],
            ajaxResponse: function(url, params, response) {
                return Array.isArray(response) ? response : (response.data || []);
            },
        });

        table.on('dataLoaded', function() {
            setTimeout(function() {
                applyFilters();
                updateSummary();
            }, 100);
        });
        table.on('renderComplete', function() {
            setTimeout(updateSummary, 100);
        });
        table.on('dataFiltered', updateSummary);

        $('#inventory-filter, #td-stock-filter, #nrl-filter, #gpft-filter, #sold-filter').on('change', function() {
            // Keep badge "active-filter" styling in sync when the Sold dropdown changes
            // directly (not via badge click). Other handlers call setActiveBadges() themselves.
            setActiveBadges();
            applyFilters();
        });
        $('#sku-search, #parent-search').on('keyup', function() {
            table.setFilter([
                { field: '(Child) sku', type: 'like', value: $('#sku-search').val() || '' },
                { field: 'Parent', type: 'like', value: $('#parent-search').val() || '' }
            ]);
            updateSummary();
        });

        // Sold badges just toggle the #sold-filter dropdown so the dropdown stays the
        // single source of truth (mirrors Amazon tabulator). Click again to clear.
        $('#zero-sold-badge').on('click', function() {
            const next = $('#sold-filter').val() === 'zero' ? 'all' : 'zero';
            $('#sold-filter').val(next);
            setActiveBadges();
            applyFilters();
        });
        $('#more-sold-badge').on('click', function() {
            const next = $('#sold-filter').val() === 'sold' ? 'all' : 'sold';
            $('#sold-filter').val(next);
            setActiveBadges();
            applyFilters();
        });
        $('#missing-badge').on('click', function() { missingFilter = !missingFilter; nmapFilter = false; applyFilters(); });
        $('#nmap-badge').on('click', function() { nmapFilter = !nmapFilter; missingFilter = false; applyFilters(); });

        $('#export-btn').on('click', () => table.download('csv', 'topdawg_pricing.csv'));

        // ─── Pricing-mode toggles ─────────────────────────────────────────
        $('#discount-type-select').on('change', function() { tdSyncDiscountInputUi(); });

        $('#decrease-btn').on('click', function() {
            tdDecreaseModeActive = !tdDecreaseModeActive;
            tdIncreaseModeActive = false;
            tdSamePriceModeActive = false;
            tdResetIncreaseBtn();
            tdResetSamePriceBtn();
            if (tdDecreaseModeActive) {
                $(this).removeClass('btn-warning').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                tdShowSelectColumn(true);
            } else {
                tdResetDecreaseBtn();
                tdShowSelectColumn(false);
                tdSelectedSkus.clear();
            }
            tdUpdateSelectedCount();
            tdSyncDiscountInputUi();
        });

        $('#increase-btn').on('click', function() {
            tdIncreaseModeActive = !tdIncreaseModeActive;
            tdDecreaseModeActive = false;
            tdSamePriceModeActive = false;
            tdResetDecreaseBtn();
            tdResetSamePriceBtn();
            if (tdIncreaseModeActive) {
                $(this).removeClass('btn-success').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-up"></i> Increase ON');
                tdShowSelectColumn(true);
            } else {
                tdResetIncreaseBtn();
                tdShowSelectColumn(false);
                tdSelectedSkus.clear();
            }
            tdUpdateSelectedCount();
            tdSyncDiscountInputUi();
        });

        $('#same-price-btn').on('click', function() {
            tdSamePriceModeActive = !tdSamePriceModeActive;
            tdDecreaseModeActive = false;
            tdIncreaseModeActive = false;
            tdResetDecreaseBtn();
            tdResetIncreaseBtn();
            if (tdSamePriceModeActive) {
                $(this).removeClass('btn-info').addClass('btn-danger')
                    .html('<i class="fas fa-equals"></i> Same Price ON');
                tdShowSelectColumn(true);
            } else {
                tdResetSamePriceBtn();
                tdShowSelectColumn(false);
                tdSelectedSkus.clear();
            }
            tdUpdateSelectedCount();
            tdSyncDiscountInputUi();
        });

        // ─── Selection handlers ───────────────────────────────────────────
        $(document).on('change', '#td-select-all', function() {
            const checked = $(this).prop('checked');
            const rows = table.getRows('active').filter(r => {
                const p = (r.getData().Parent || '');
                return !(p && String(p).toUpperCase().startsWith('PARENT'));
            });
            rows.forEach(r => {
                const sku = String(r.getData()['(Child) sku'] || '');
                if (!sku) return;
                if (checked) tdSelectedSkus.add(sku); else tdSelectedSkus.delete(sku);
            });
            $('.td-sku-chk').each(function() {
                const sku = String($(this).attr('data-sku'));
                $(this).prop('checked', tdSelectedSkus.has(sku));
            });
            tdUpdateSelectedCount();
        });

        $(document).on('change', '.td-sku-chk', function() {
            const sku = String($(this).attr('data-sku'));
            if (!sku) return;
            if ($(this).prop('checked')) tdSelectedSkus.add(sku); else tdSelectedSkus.delete(sku);
            tdUpdateSelectedCount();
            tdUpdateSelectAllHeaderCheckbox();
        });

        // ─── Apply / Clear handlers ───────────────────────────────────────
        $('#apply-discount-btn').on('click', tdApplyDiscount);
        $('#discount-percentage-input').on('keypress', function(e) {
            if (e.which === 13) tdApplyDiscount();
        });
        $('#clear-sprice-btn').on('click', tdClearSpriceForSelected);

        /*
         * Target ROI% / Target GPFT% bulk apply (TopDawg, margin = TD_PERCENTAGE)
         * ----------------------------------------------------------------------
         * Back-solves SPRICE so the resulting SROI / SGPFT column matches the entered
         * target. TopDawg's SGPFT / SROI formulas include shipping (matches
         * TopDawgPricingController::getViewTopDawgTabularData lines 187-188 and
         * tdApplyDiscount above):
         *     SROI%  = ((sprice * TD_PERCENTAGE − lp − ship) / lp)     * 100
         *           → sprice = (lp * (1 + ROI%/100) + ship) / TD_PERCENTAGE
         *     SGPFT% = ((sprice * TD_PERCENTAGE − lp − ship) / sprice) * 100
         *           → sprice = (lp + ship) / (TD_PERCENTAGE − GPFT%/100)
         * Optimistic SGPFT / SROI written client-side, then the existing
         * /topdawg-save-sprice endpoint reconciles them server-side. Plain 2-decimal
         * rounding — no .99 snapping — because snapping would shift the achieved
         * SROI / SGPFT off the user-typed target.
         */
        $('#apply-target-roi-btn').on('click', function () {
            const rawInput = $('#target-roi-input').val();
            const targetRoiPct = parseFloat(String(rawInput).replace(',', '.'));

            if (rawInput === '' || rawInput == null) {
                tdShowToast('Please enter a Target ROI%', 'error');
                return;
            }
            if (!isFinite(targetRoiPct)) {
                tdShowToast('Target ROI% must be a number', 'error');
                return;
            }
            if (tdSelectedSkus.size === 0) {
                tdShowToast('Please select at least one SKU first (turn on Decrease / Increase / Same Price to reveal checkboxes)', 'error');
                return;
            }

            const roiMultiplier = 1 + (targetRoiPct / 100);
            const updates = [];
            let updatedCount = 0;
            let skippedNoLp  = 0;

            table.getRows('active').forEach(function (row) {
                const d = row.getData();
                const sku = d && d['(Child) sku'] != null ? String(d['(Child) sku']) : '';
                if (!sku || !tdSelectedSkus.has(sku)) return;

                const lp = parseFloat(d.LP_productmaster) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const ship = parseFloat(d.Ship_productmaster) || 0;

                const candidate = (lp * roiMultiplier + ship) / TD_PERCENTAGE;
                const newSprice = +candidate.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                const sgpft = newSprice > 0 ? Math.round(((newSprice * TD_PERCENTAGE - lp - ship) / newSprice) * 100) : 0;
                const sroi  = lp > 0       ? Math.round(((newSprice * TD_PERCENTAGE - lp - ship) / lp)     * 100) : 0;

                row.update({ SPRICE: newSprice, SGPFT: sgpft, SROI: sroi });
                updates.push({ sku: sku, sprice: newSprice });
                updatedCount++;
            });

            if (!updates.length) {
                tdShowToast('No selected rows have a usable LP > 0', 'warning');
                return;
            }

            tdSaveSpriceUpdates(updates);
            const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
            tdShowToast(`Target ROI ${targetRoiPct}% applied to ${updatedCount} SKU(s)${note}`, 'success');
        });

        $('#apply-target-gpft-btn').on('click', function () {
            const rawInput = $('#target-gpft-input').val();
            const targetGpftPct = parseFloat(String(rawInput).replace(',', '.'));

            if (rawInput === '' || rawInput == null) {
                tdShowToast('Please enter a Target GPFT%', 'error');
                return;
            }
            if (!isFinite(targetGpftPct)) {
                tdShowToast('Target GPFT% must be a number', 'error');
                return;
            }
            if (tdSelectedSkus.size === 0) {
                tdShowToast('Please select at least one SKU first (turn on Decrease / Increase / Same Price to reveal checkboxes)', 'error');
                return;
            }

            const denom = TD_PERCENTAGE - (targetGpftPct / 100);
            if (denom <= 0) {
                tdShowToast(`Target GPFT% ${targetGpftPct}% is too high — must be < ${(TD_PERCENTAGE * 100).toFixed(0)}% (TopDawg take-home).`, 'error');
                return;
            }

            const updates = [];
            let updatedCount = 0;
            let skippedNoLp  = 0;

            table.getRows('active').forEach(function (row) {
                const d = row.getData();
                const sku = d && d['(Child) sku'] != null ? String(d['(Child) sku']) : '';
                if (!sku || !tdSelectedSkus.has(sku)) return;

                const lp = parseFloat(d.LP_productmaster) || 0;
                if (lp <= 0) { skippedNoLp++; return; }
                const ship = parseFloat(d.Ship_productmaster) || 0;

                const candidate = (lp + ship) / denom;
                const newSprice = +candidate.toFixed(2);
                if (!isFinite(newSprice) || newSprice <= 0) return;

                const sgpft = newSprice > 0 ? Math.round(((newSprice * TD_PERCENTAGE - lp - ship) / newSprice) * 100) : 0;
                const sroi  = lp > 0       ? Math.round(((newSprice * TD_PERCENTAGE - lp - ship) / lp)     * 100) : 0;

                row.update({ SPRICE: newSprice, SGPFT: sgpft, SROI: sroi });
                updates.push({ sku: sku, sprice: newSprice });
                updatedCount++;
            });

            if (!updates.length) {
                tdShowToast('No selected rows have a usable LP > 0', 'warning');
                return;
            }

            tdSaveSpriceUpdates(updates);
            const note = skippedNoLp > 0 ? ` (${skippedNoLp} skipped — no LP)` : '';
            tdShowToast(`Target GPFT ${targetGpftPct}% applied to ${updatedCount} SKU(s)${note}`, 'success');
        });

        $('#target-roi-input').on('keypress', function (e) {
            if (e.which === 13) $('#apply-target-roi-btn').click();
        });
        $('#target-gpft-input').on('keypress', function (e) {
            if (e.which === 13) $('#apply-target-gpft-btn').click();
        });

        table.on('renderComplete', tdUpdateSelectAllHeaderCheckbox);

        // ---- Edit B/S Links (double-click on Links cell) ----
        let tdEditLinksRow = null;
        window.openTdEditLinksModal = function(row) {
            if (!row) return;
            tdEditLinksRow = row;
            const d = row.getData();
            $('#tdEditLinksSku').val(d['(Child) sku']);
            $('#tdEditLinksSkuDisplay').text(d['(Child) sku']);
            $('#tdEditSellerLink').val(d['S Link'] || '');
            $('#tdEditBuyerLink').val(d['B Link'] || '');
            $('#tdEditLinksError').hide().text('');
            new bootstrap.Modal(document.getElementById('tdEditLinksModal')).show();
        };

        function tdNotify(msg, type) {
            if (window.toastr) { toastr[type === 'error' ? 'error' : 'success'](msg); return; }
            let c = document.getElementById('tdToastContainer');
            if (!c) {
                c = document.createElement('div');
                c.id = 'tdToastContainer';
                c.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;';
                document.body.appendChild(c);
            }
            const t = document.createElement('div');
            t.style.cssText = 'min-width:220px;max-width:340px;color:#fff;background:' + (type === 'error' ? '#dc3545' : '#198754') + ';padding:12px 16px;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.18);font-size:14px;opacity:0;transition:opacity .25s ease;';
            t.textContent = msg;
            c.appendChild(t);
            requestAnimationFrame(function() { t.style.opacity = '1'; });
            setTimeout(function() { t.style.opacity = '0'; setTimeout(function() { t.remove(); }, 300); }, 2600);
        }

        $(document).on('click', '#tdSaveLinksBtn', function() {
            const sku = $('#tdEditLinksSku').val();
            const sellerLink = $('#tdEditSellerLink').val().trim();
            const buyerLink = $('#tdEditBuyerLink').val().trim();
            const $err = $('#tdEditLinksError');
            $err.hide().text('');
            const $btn = $(this).prop('disabled', true);
            $.ajax({
                url: '{{ url("/topdawg-save-links") }}',
                method: 'POST',
                data: { sku: sku, seller_link: sellerLink, buyer_link: buyerLink, _token: '{{ csrf_token() }}' },
                success: function(res) {
                    if (tdEditLinksRow) {
                        tdEditLinksRow.update({ 'S Link': res.seller_link || '', 'B Link': res.buyer_link || '' })
                            .then(function() { tdEditLinksRow.reformat(); })
                            .catch(function() { tdEditLinksRow.reformat(); });
                    }
                    tdNotify(sku + ': links saved', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('tdEditLinksModal'))?.hide();
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Failed to save links.';
                    $err.text(msg).show();
                },
                complete: function() { $btn.prop('disabled', false); }
            });
        });
    });
</script>
@endsection
