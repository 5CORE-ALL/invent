@extends('layouts.vertical', ['title' => 'Faire Analytics', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tabulator { border: 1px solid #dee2e6; border-radius: 8px; font-size: 12px; }
        .tabulator .tabulator-header { background: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .tabulator-col .tabulator-col-sorter { display: none !important; }
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl; text-orientation: mixed; transform: rotate(180deg);
            white-space: nowrap; height: 78px; display: flex; align-items: center;
            justify-content: center; font-size: 11px; font-weight: 600;
        }
        .tabulator .tabulator-header .tabulator-col { height: 80px !important; }
        .tabulator .tabulator-row { min-height: 50px; }
        .tabulator-row.fr-parent-row,
        .tabulator-row.fr-parent-row .tabulator-cell {
            background-color: #d1e7dd !important;
            font-weight: 700 !important;
            min-height: 48px !important;
            color: #0f5132;
        }
        .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }
        .fr-manual-dropdown { position: relative; display: inline-block; }
        .fr-manual-dropdown .dropdown-menu {
            position: absolute; top: 100%; left: 0; z-index: 1050;
            display: none; min-width: 200px; padding: .5rem 0; margin: 0;
            background: #fff; border: 1px solid #dee2e6; border-radius: .375rem;
            box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
        }
        .fr-manual-dropdown.show .dropdown-menu { display: block; }
        .fr-dropdown-item {
            display: block; width: 100%; padding: .5rem 1rem; clear: both;
            font-weight: 400; color: #212529; text-decoration: none;
            background: transparent; border: 0; cursor: pointer; white-space: nowrap;
        }
        .fr-dropdown-item:hover { background: #e9ecef; }
        .fr-sc { display:inline-block; width:12px; height:12px; border-radius:50%; margin-right:6px; border:1px solid #ddd; }
        .fr-sc.def { background:#6c757d; }
        .fr-sc.red { background:#dc3545; }
        .fr-sc.yellow { background:#ffc107; }
        .fr-sc.green { background:#28a745; }
        .fr-sc.pink { background:#e83e8c; }
        #faire-pricing-table .tabulator-col[tabulator-field="sku"],
        #faire-pricing-table .tabulator-cell[tabulator-field="sku"] {
            transition: width 0.2s ease, min-width 0.2s ease;
        }
        #fr-image-hover-preview {
            pointer-events: auto;
            z-index: 10050;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Faire Analytics',
        'sub_title'  => 'List-price upload, SPRICE, and sales merge from Faire daily data (same source as Faire Sales Data)',
    ])

    <div class="row">
        <div class="col-12">
            <div class="alert alert-info py-2 mb-3">
                <strong>Sales data</strong> is aggregated from <a href="{{ route('faire.tabulator.view') }}" class="alert-link">Faire Sales Data</a>
                (uploaded wholesale × quantity per SKU). <strong>Pricing</strong> column is your uploaded Faire list price (CSV/Excel: sku, price, stock).
            </div>

            <div class="card border-warning mb-3">
                <div class="card-header bg-warning bg-opacity-25 py-2">
                    <strong><i class="fas fa-upload me-1"></i> List price upload</strong>
                    <span class="text-muted small ms-2">Table below shows merged product masters, sales, and uploaded list prices.</span>
                </div>
                <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
                    <a href="{{ route('faire.pricing.price.sample') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download"></i> Sample CSV
                    </a>
                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#uploadFairePriceModal">
                        <i class="fas fa-upload"></i> Upload price
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <select id="fr-row-type-filter" class="form-select form-select-sm" style="width:120px;">
                            <option value="all" selected>All Rows</option>
                            <option value="parents">Parents</option>
                            <option value="skus">SKUs</option>
                        </select>
                        <select id="fr-inv-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">All Inventory</option>
                            <option value="zero">0 Inventory</option>
                            <option value="more" selected>More than 0</option>
                        </select>
                        <select id="fr-stock-filter" class="form-select form-select-sm" style="width:150px;">
                            <option value="all">Faire stock</option>
                            <option value="zero">0 Faire stock</option>
                            <option value="more">Faire stock &gt; 0</option>
                        </select>
                        <select id="fr-gpft-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0–10%</option>
                            <option value="10-20">10–20%</option>
                            <option value="20-30">20–30%</option>
                            <option value="30-40">30–40%</option>
                            <option value="40-50">40–50%</option>
                            <option value="50-60">50–60%</option>
                            <option value="60plus">60%+</option>
                        </select>
                        <select id="fr-roi-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">ROI%</option>
                            <option value="lt40">&lt; 40%</option>
                            <option value="40-75">40–75%</option>
                            <option value="75-125">75–125%</option>
                            <option value="125-250">125–250%</option>
                            <option value="gt250">&gt; 250%</option>
                        </select>
                        <select id="fr-fqty-filter" class="form-select form-select-sm" style="width:130px;" title="Units sold (Faire daily data)">
                            <option value="all">Sold</option>
                            <option value="0">0</option>
                            <option value="0-10">1–10</option>
                            <option value="10plus">10+</option>
                        </select>
                        <select id="fr-map-filter" class="form-select form-select-sm" style="width:120px;">
                            <option value="all">Map</option>
                            <option value="map">Map only</option>
                            <option value="nmap">N Map only</option>
                        </select>
                        <div class="fr-manual-dropdown">
                            <button class="btn btn-light btn-sm fr-dil-toggle" type="button" id="fr-dil-btn">
                                <span class="fr-sc def"></span>DIL%
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="fr-dropdown-item fr-dil-item active" href="#" data-color="all">
                                    <span class="fr-sc def"></span>All DIL</a></li>
                                <li><a class="fr-dropdown-item fr-dil-item" href="#" data-color="red">
                                    <span class="fr-sc red"></span>Red (&lt;16.7%)</a></li>
                                <li><a class="fr-dropdown-item fr-dil-item" href="#" data-color="yellow">
                                    <span class="fr-sc yellow"></span>Yellow (16.7–25%)</a></li>
                                <li><a class="fr-dropdown-item fr-dil-item" href="#" data-color="green">
                                    <span class="fr-sc green"></span>Green (25–50%)</a></li>
                                <li><a class="fr-dropdown-item fr-dil-item" href="#" data-color="pink">
                                    <span class="fr-sc pink"></span>Pink (50%+)</a></li>
                            </ul>
                        </div>
                        <input type="text" id="fr-pricing-parent-search" class="form-control form-control-sm" style="max-width:200px;" placeholder="Search parent..." title="Filter by Parent column">
                        <input type="text" id="fr-pricing-sku-search" class="form-control form-control-sm" style="max-width:220px;" placeholder="Search SKU...">
                        <button type="button" id="fr-refresh-pricing" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                        <button type="button" id="fr-export-pricing" class="btn btn-sm btn-success">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </button>
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="frColumnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-eye"></i> Columns
                            </button>
                            <ul class="dropdown-menu py-1" aria-labelledby="frColumnVisibilityDropdown" id="fr-column-dropdown-menu" style="max-height: 400px; overflow-y: auto; min-width: 220px;">
                            </ul>
                        </div>
                        <button type="button" id="fr-show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                            <i class="fa fa-eye"></i> Show all
                        </button>
                        <button id="fr-price-mode-btn" type="button" class="btn btn-sm btn-secondary" title="Cycle: Off → Decrease → Increase">
                            <i class="fas fa-exchange-alt"></i> Pricing mode
                        </button>
                    </div>

                    <div id="fr-discount-container" class="p-2 bg-light border rounded mb-2" style="display:none;">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span id="fr-selected-skus-count" class="fw-bold text-secondary"></span>
                            <select id="fr-discount-type" class="form-select form-select-sm" style="width:120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                            <input type="number" id="fr-discount-input" class="form-control form-control-sm" placeholder="Enter %" step="0.01" style="width:110px;">
                            <button id="fr-apply-discount-btn" type="button" class="btn btn-primary btn-sm">Apply</button>
                            <button id="fr-clear-sprice-btn" type="button" class="btn btn-danger btn-sm">
                                <i class="fas fa-eraser"></i> Clear SPRICE
                            </button>
                        </div>
                    </div>

                    <div id="fr-summary-stats" class="mt-2 p-3 bg-light rounded mb-3">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-primary fs-6 p-2" id="fr-total-sales-badge" style="font-weight:700;">Sales: $0</span>
                            <span class="badge bg-warning fs-6 p-2" id="fr-total-fqty-badge" style="font-weight:700;color:#111;">Sold: 0</span>
                            <span class="badge bg-success fs-6 p-2 d-none" id="fr-total-profit-badge" style="font-weight:700;" aria-hidden="true">Profit: 0</span>
                            <span class="badge bg-info fs-6 p-2" id="fr-avg-gpft-badge" style="font-weight:700;color:#111;" title="Same as Faire Sales Data: total order-style profit ÷ total sales (0.75×wholesale revenue − LP×qty).">PFt: 0%</span>
                            <span class="badge bg-secondary fs-6 p-2" id="fr-avg-roi-badge" style="font-weight:700;color:#111;">ROI: 0%</span>
                            <span class="badge bg-danger fs-6 p-2" id="fr-missing-badge" style="font-weight:700;">Missing L: 0</span>
                            <span class="badge fs-6 p-2" id="fr-map-badge" style="font-weight:700;background:#0d6efd;color:#fff;">N Map: 0</span>
                            <span class="badge fs-6 p-2" id="fr-zero-sold-badge" style="font-weight:700;background:#dc3545;color:#fff;">0 Sold: 0</span>
                            <span class="badge fs-6 p-2" id="fr-more-sold-badge" style="font-weight:700;background:#28a745;color:#fff;">&gt;0 Sold: 0</span>
                        </div>
                    </div>

                    <div id="faire-pricing-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="uploadFairePriceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Faire list price sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="file" class="form-control" id="frPriceSheetFile" accept=".xlsx,.xls,.csv,.txt">
                    <small class="text-muted">Simple sheet: <strong>sku</strong>, <strong>price</strong>, optional <strong>stock</strong>.
                        Faire product export (TSV/Excel) is supported: <strong>SKU</strong>, <strong>USD Unit Wholesale Price</strong> (or other currency wholesale), <strong>On Hand Inventory</strong>.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="frUploadPriceSheetBtn">Upload</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        let table = null;
        let summaryDataCache = [];
        let frMissingActive = false;
        let frNMapActive = false;
        let frZeroSoldActive = false;
        let frMoreSoldActive = false;

        let frDecreaseModeActive = false;
        let frIncreaseModeActive = false;
        let frSelectedSkus = new Set();

        let frSkuColHoverBase = null;
        let frSkuColHoverActive = false;

        function frResetSkuColHoverWidth() {
            if (table && frSkuColHoverActive && frSkuColHoverBase != null) {
                try {
                    const col = table.getColumn('sku');
                    if (col) col.setWidth(frSkuColHoverBase);
                } catch (err) { /* ignore */ }
            }
            frSkuColHoverActive = false;
            frSkuColHoverBase = null;
        }

        let frImagePreviewHideTimer = null;
        let frImagePreviewEl = null;

        function frRemoveImagePreview() {
            if (frImagePreviewHideTimer) {
                clearTimeout(frImagePreviewHideTimer);
                frImagePreviewHideTimer = null;
            }
            document.querySelectorAll('#fr-image-hover-preview').forEach(function(el) {
                el.remove();
            });
            frImagePreviewEl = null;
        }

        function frCancelImagePreviewHide() {
            if (frImagePreviewHideTimer) {
                clearTimeout(frImagePreviewHideTimer);
                frImagePreviewHideTimer = null;
            }
        }

        function frScheduleImagePreviewHide() {
            frCancelImagePreviewHide();
            frImagePreviewHideTimer = setTimeout(frRemoveImagePreview, 220);
        }

        function frEnsureImagePreviewListeners(wrap) {
            if (wrap.dataset.frPreviewListeners === '1') return;
            wrap.dataset.frPreviewListeners = '1';
            wrap.addEventListener('mouseenter', frCancelImagePreviewHide);
            wrap.addEventListener('mouseleave', frScheduleImagePreviewHide);
        }

        function frClampImagePreviewPosition(wrap, clientX, clientY) {
            const pad = 12;
            let left = clientX + pad;
            let top = clientY + pad;
            wrap.style.position = 'fixed';
            wrap.style.left = left + 'px';
            wrap.style.top = top + 'px';
            const rect = wrap.getBoundingClientRect();
            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const m = 8;
            if (rect.right > vw - m) left = Math.max(m, vw - rect.width - m);
            if (rect.bottom > vh - m) top = Math.max(m, vh - rect.height - m);
            if (left < m) left = m;
            if (top < m) top = m;
            wrap.style.left = left + 'px';
            wrap.style.top = top + 'px';
        }

        function frShowImagePreview(clientX, clientY, fullUrl) {
            if (!fullUrl) return;
            frCancelImagePreviewHide();
            const existing = frImagePreviewEl;
            if (existing && document.body.contains(existing)) {
                const prevImg = existing.querySelector('img');
                if (prevImg && prevImg.getAttribute('src') === fullUrl) {
                    frClampImagePreviewPosition(existing, clientX, clientY);
                    return;
                }
            }
            document.querySelectorAll('#fr-image-hover-preview').forEach(function(el) {
                el.remove();
            });
            frImagePreviewEl = null;

            const wrap = document.createElement('div');
            wrap.id = 'fr-image-hover-preview';
            wrap.style.zIndex = '10050';
            wrap.style.pointerEvents = 'auto';
            wrap.style.border = '1px solid #ccc';
            wrap.style.background = '#fff';
            wrap.style.padding = '4px';
            wrap.style.boxShadow = '0 4px 16px rgba(0,0,0,0.18)';
            wrap.style.borderRadius = '6px';
            const big = document.createElement('img');
            big.style.maxWidth = '350px';
            big.style.maxHeight = '350px';
            big.style.display = 'block';
            big.alt = '';
            big.src = fullUrl;
            wrap.appendChild(big);
            frEnsureImagePreviewListeners(wrap);
            document.body.appendChild(wrap);
            frImagePreviewEl = wrap;
            frClampImagePreviewPosition(wrap, clientX, clientY);
        }

        function money(value) {
            return '$' + (parseFloat(value) || 0).toFixed(2);
        }

        function frEscUrlAttr(url) {
            if (url == null || url === '') return '';
            return String(url).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        }

        function saveFaireSpriceUpdates(updates) {
            $.ajax({
                url: '{{ route("faire.pricing.save.sprice") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { _token: '{{ csrf_token() }}', updates: updates },
                success: function(res) {
                    if (res.success) console.log('Faire SPRICE saved:', res.updated);
                },
                error: function(xhr) {
                    console.error('Faire SPRICE save error:', xhr.responseJSON);
                }
            });
        }

        function frRoundToRetailPrice(price) {
            return Math.ceil(price) - 0.01;
        }

        function frSyncPriceModeUi() {
            const $btn = $('#fr-price-mode-btn');
            const selectCol = table ? table.getColumn('_fr_select') : null;
            if (frDecreaseModeActive) {
                $btn.removeClass('btn-secondary btn-primary').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                if (selectCol) selectCol.show();
                return;
            }
            if (frIncreaseModeActive) {
                $btn.removeClass('btn-secondary btn-danger').addClass('btn-primary')
                    .html('<i class="fas fa-arrow-up"></i> Increase ON');
                if (selectCol) selectCol.show();
                return;
            }
            $btn.removeClass('btn-danger btn-primary').addClass('btn-secondary')
                .html('<i class="fas fa-exchange-alt"></i> Pricing mode');
            if (selectCol) selectCol.hide();
            frSelectedSkus.clear();
            frUpdateSelectedCount();
        }

        function frUpdateSelectedCount() {
            const cnt = frSelectedSkus.size;
            $('#fr-selected-skus-count').text(cnt + ' SKU' + (cnt !== 1 ? 's' : '') + ' selected');
            $('#fr-discount-container').toggle(cnt > 0 && (frDecreaseModeActive || frIncreaseModeActive));
        }

        function frApplyDiscount() {
            const discountType = $('#fr-discount-type').val();
            const discountVal = parseFloat($('#fr-discount-input').val());
            if (isNaN(discountVal) || discountVal === 0 || frSelectedSkus.size === 0) return;

            const updates = [];
            frSelectedSkus.forEach(function(sku) {
                const rows = table.searchRows('sku', '=', sku);
                if (!rows.length) return;
                const row = rows[0];
                const rowData = row.getData();
                const currentPrice = parseFloat(rowData.price) || 0;
                if (currentPrice <= 0) return;

                let newSprice;
                if (discountType === 'percentage') {
                    newSprice = frIncreaseModeActive
                        ? currentPrice * (1 + discountVal / 100)
                        : currentPrice * (1 - discountVal / 100);
                } else {
                    newSprice = frIncreaseModeActive
                        ? currentPrice + discountVal
                        : currentPrice - discountVal;
                }
                newSprice = frRoundToRetailPrice(Math.max(0.99, newSprice));

                const margin = parseFloat(rowData._margin) || 0.75;
                const lp = parseFloat(rowData.lp) || 0;
                const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - lp) / newSprice) * 100) : 0;
                const sroi = lp > 0 ? Math.round(((newSprice * margin - lp) / lp) * 100) : 0;

                row.update({ sprice: newSprice, sgpft: sgpft, sroi: sroi });
                updates.push({ sku: sku, sprice: newSprice });
            });

            if (updates.length) saveFaireSpriceUpdates(updates);
            $('#fr-discount-input').val('');
        }

        function frClearSpriceForSelected() {
            if (!frSelectedSkus.size) return;
            if (!confirm('Clear SPRICE for ' + frSelectedSkus.size + ' SKU(s)?')) return;
            const updates = [];
            table.getRows().forEach(function(row) {
                const d = row.getData();
                if (frSelectedSkus.has(d.sku) && !d.is_parent) {
                    row.update({ sprice: 0, sgpft: 0, sroi: 0 });
                    updates.push({ sku: d.sku, sprice: 0 });
                }
            });
            if (updates.length) saveFaireSpriceUpdates(updates);
        }

        function normalizeRows(rowsInput) {
            if (Array.isArray(rowsInput)) {
                return rowsInput.map(row => {
                    if (row && typeof row.getData === 'function') return row.getData();
                    return row || {};
                });
            }
            if (rowsInput && typeof rowsInput === 'object') {
                return Object.values(rowsInput).map(row => {
                    if (row && typeof row.getData === 'function') return row.getData();
                    return row || {};
                });
            }
            return [];
        }

        /** Matches /faire-tabulator: keep 0.75 of wholesale dollars minus LP×qty (per-SKU aggregate). */
        const FAIRE_ORDER_KEEP = 0.75;

        function updateSummary(rowsInput = null) {
            let rows = normalizeRows(rowsInput);
            if (!rows.length && table && typeof table.getData === 'function') {
                const activeRows = normalizeRows(table.getData('active'));
                const allRows = normalizeRows(table.getData());
                rows = activeRows.length ? activeRows : allRows;
            }
            if (!rows.length) rows = normalizeRows(summaryDataCache);

            let totalSales = 0, totalFqty = 0, totalProfit = 0, totalCogs = 0;
            let missingCount = 0, mapCount = 0;
            let zeroSold = 0, moreSold = 0;

            rows.forEach(row => {
                if (row.is_parent) return;
                const isMissing = (row.missing || '').trim().toUpperCase() === 'M';
                const fqty = parseFloat(row.al30) || 0;
                const sales = parseFloat(row.sales) || 0;
                const lp = parseFloat(row.lp) || 0;
                const listProfitPerUnit = parseFloat(row.profit) || 0;

                totalSales += sales;
                totalFqty += fqty;
                totalCogs += lp * fqty;

                let rowOrderPft = 0;
                if (sales > 0 && fqty > 0) {
                    rowOrderPft = FAIRE_ORDER_KEEP * sales - lp * fqty;
                } else if (fqty > 0 && !isMissing) {
                    rowOrderPft = fqty * listProfitPerUnit;
                }
                totalProfit += rowOrderPft;

                if (fqty === 0) zeroSold++; else moreSold++;
                if (isMissing) missingCount++;
                if ((row.map || '').startsWith('N Map|')) mapCount++;
            });

            const pftPct = totalSales > 0 ? (totalProfit / totalSales) * 100 : 0;
            const roiPct = totalCogs > 0 ? (totalProfit / totalCogs) * 100 : 0;

            $('#fr-total-sales-badge').text('Sales: $' + Math.round(totalSales).toLocaleString());
            $('#fr-total-fqty-badge').text('Sold: ' + totalFqty.toLocaleString());
            $('#fr-total-profit-badge').text('Profit: ' + Math.round(totalProfit).toLocaleString());
            $('#fr-avg-gpft-badge').text('PFt: ' + Math.round(pftPct) + '%');
            $('#fr-avg-roi-badge').text('ROI: ' + Math.round(roiPct) + '%');
            $('#fr-missing-badge').text('Missing L: ' + missingCount.toLocaleString());
            $('#fr-map-badge').text('N Map: ' + mapCount.toLocaleString());
            $('#fr-zero-sold-badge').text('0 Sold: ' + zeroSold.toLocaleString());
            $('#fr-more-sold-badge').text('>0 Sold: ' + moreSold.toLocaleString());
        }

        function applyFilters() {
            if (!table) return;
            table.clearFilter();

            const skuSearch = ($('#fr-pricing-sku-search').val() || '').toLowerCase().trim();
            const parentSearch = ($('#fr-pricing-parent-search').val() || '').toLowerCase().trim();
            const rowType = $('#fr-row-type-filter').val();
            const invFilter = $('#fr-inv-filter').val();
            const stockFilter = $('#fr-stock-filter').val();
            const gpftFilter = $('#fr-gpft-filter').val();
            const roiFilter = $('#fr-roi-filter').val();
            const fqtyFilter = $('#fr-fqty-filter').val();
            const mapFilter = $('#fr-map-filter').val();
            const dilColor = $('.fr-dil-item.active').data('color') || 'all';

            if (skuSearch) {
                table.addFilter(d => (d.sku || '').toLowerCase().includes(skuSearch));
            }
            if (parentSearch) {
                table.addFilter(function(d) {
                    const p = (d.parent || '').toLowerCase();
                    const sku = (d.sku || '').toLowerCase();
                    if (d.is_parent === true) {
                        return p.includes(parentSearch) || sku.includes(parentSearch);
                    }
                    return p.includes(parentSearch) || sku.includes(parentSearch);
                });
            }
            if (rowType === 'parents') {
                table.addFilter(d => d.is_parent === true);
            } else if (rowType === 'skus') {
                table.addFilter(d => !d.is_parent);
            }
            if (invFilter === 'zero') {
                table.addFilter(d => (parseInt(d.inv, 10) || 0) === 0);
            } else if (invFilter === 'more') {
                table.addFilter(d => (parseInt(d.inv, 10) || 0) > 0);
            }
            if (stockFilter === 'zero') {
                table.addFilter(d => (parseInt(d.ae_stock, 10) || 0) === 0);
            } else if (stockFilter === 'more') {
                table.addFilter(d => (parseInt(d.ae_stock, 10) || 0) > 0);
            }
            if (gpftFilter !== 'all') {
                table.addFilter(function(d) {
                    const gpft = parseFloat(d.gpft) || 0;
                    if (gpftFilter === 'negative') return gpft < 0;
                    if (gpftFilter === '60plus') return gpft >= 60;
                    const parts = gpftFilter.split('-').map(Number);
                    return gpft >= parts[0] && gpft < parts[1];
                });
            }
            if (roiFilter !== 'all') {
                table.addFilter(function(d) {
                    if (d.is_parent) return true;
                    const roi = parseFloat(d.groi) || 0;
                    if (roiFilter === 'lt40') return roi < 40;
                    if (roiFilter === 'gt250') return roi > 250;
                    const parts = roiFilter.split('-').map(Number);
                    return roi >= parts[0] && roi <= parts[1];
                });
            }
            if (fqtyFilter !== 'all') {
                table.addFilter(function(d) {
                    if ((parseInt(d.inv, 10) || 0) <= 0) return false;
                    const fqty = parseFloat(d.al30) || 0;
                    if (fqtyFilter === '0') return fqty === 0;
                    if (fqtyFilter === '0-10') return fqty > 0 && fqty <= 10;
                    if (fqtyFilter === '10plus') return fqty > 10;
                    return true;
                });
            }
            if (mapFilter === 'map') {
                table.addFilter(d => (d.map || '') === 'Map');
            } else if (mapFilter === 'nmap') {
                table.addFilter(d => (d.map || '').startsWith('N Map|'));
            }
            if (dilColor !== 'all') {
                table.addFilter(function(d) {
                    const inv = parseFloat(d.inv) || 0;
                    const ovL30 = parseFloat(d.ov_l30) || 0;
                    const dil = inv === 0 ? 0 : (ovL30 / inv) * 100;
                    if (dilColor === 'red') return dil < 16.66;
                    if (dilColor === 'yellow') return dil >= 16.66 && dil < 25;
                    if (dilColor === 'green') return dil >= 25 && dil < 50;
                    if (dilColor === 'pink') return dil >= 50;
                    return true;
                });
            }
            if (frMissingActive) table.addFilter(d => (d.missing || '').trim().toUpperCase() === 'M');
            if (frNMapActive) table.addFilter(d => (d.map || '').startsWith('N Map|'));
            if (frZeroSoldActive) table.addFilter(d => (parseFloat(d.al30) || 0) === 0);
            if (frMoreSoldActive) table.addFilter(d => (parseFloat(d.al30) || 0) > 0);
        }

        function frBuildColumnDropdown() {
            if (!table) return;
            const menu = document.getElementById('fr-column-dropdown-menu');
            if (!menu) return;
            let html = '';
            table.getColumns().forEach(function(col) {
                const field = col.getField();
                const def = col.getDefinition();
                const titleRaw = def.title;
                const titleStr = titleRaw != null ? String(titleRaw) : '';
                const label = titleStr.replace(/<[^>]*>/g, '').trim() || field;
                if (field && field !== '_fr_select' && label) {
                    const isVisible = col.isVisible();
                    const fEsc = String(field).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                    const lEsc = String(label).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                    html += '<li class="dropdown-item px-2 py-1">' +
                        '<label class="d-flex align-items-center gap-2 mb-0 w-100" style="cursor:pointer;">' +
                        '<input type="checkbox" class="fr-column-toggle" data-field="' + fEsc + '" ' + (isVisible ? 'checked' : '') + '>' +
                        '<span>' + lEsc + '</span>' +
                        '</label></li>';
                }
            });
            menu.innerHTML = html;
        }

        function frSaveColumnVisibilityToServer() {
            if (!table) return;
            const visibility = {};
            table.getColumns().forEach(function(col) {
                const field = col.getField();
                if (field && field !== '_fr_select') {
                    visibility[field] = col.isVisible();
                }
            });
            $.ajax({
                url: '{{ route("faire.pricing.column.set") }}',
                method: 'POST',
                data: { visibility: visibility, _token: '{{ csrf_token() }}' }
            });
        }

        function frApplyColumnVisibilityFromServer() {
            if (!table) return;
            $.ajax({
                url: '{{ route("faire.pricing.column.get") }}',
                method: 'GET',
                success: function(visibility) {
                    if (visibility && typeof visibility === 'object' && Object.keys(visibility).length > 0) {
                        Object.keys(visibility).forEach(function(field) {
                            const col = table.getColumn(field);
                            if (col) {
                                if (visibility[field]) {
                                    col.show();
                                } else {
                                    col.hide();
                                }
                            }
                        });
                        frBuildColumnDropdown();
                    }
                }
            });
        }

        $(document).ready(function() {
            table = new Tabulator('#faire-pricing-table', {
                ajaxURL: '/faire/pricing-data',
                ajaxResponse: function(url, params, response) {
                    summaryDataCache = normalizeRows(response);
                    updateSummary(summaryDataCache);
                    return response;
                },
                layout: 'fitDataStretch',
                pagination: true,
                paginationSize: 100,
                initialSort: [],
                rowFormatter: function(row) {
                    if (row.getData().is_parent === true) {
                        row.getElement().classList.add('fr-parent-row');
                    }
                },
                columns: [
                    {
                        title: 'Image', field: 'image', width: 60, headerSort: false, frozen: true,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const src = cell.getValue();
                            if (d.is_parent || !src) return '';
                            const esc = String(src).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            return '<img src="' + esc + '" data-full="' + esc + '" class="fr-hover-thumb" alt="" ' +
                                'style="width:44px;height:44px;object-fit:cover;border-radius:4px;cursor:pointer;" ' +
                                'onerror="this.onerror=null;this.style.display=\'none\'">';
                        },
                        cellMouseOver: function(e, cell) {
                            if (cell.getRow().getData().is_parent) return;
                            const img = cell.getElement().querySelector('.fr-hover-thumb');
                            if (!img) return;
                            frShowImagePreview(e.clientX, e.clientY, img.getAttribute('data-full'));
                        },
                        cellMouseMove: function(e, cell) {
                            const preview = frImagePreviewEl;
                            if (!preview || !document.body.contains(preview)) return;
                            if (cell.getRow().getData().is_parent) return;
                            const img = cell.getElement().querySelector('.fr-hover-thumb');
                            const fullUrl = img ? img.getAttribute('data-full') : '';
                            const big = preview.querySelector('img');
                            if (!fullUrl || !big || big.getAttribute('src') !== fullUrl) return;
                            frClampImagePreviewPosition(preview, e.clientX, e.clientY);
                        },
                        cellMouseOut: function(e, cell) {
                            const related = e.relatedTarget;
                            if (related && typeof related.closest === 'function' && related.closest('#fr-image-hover-preview')) {
                                frCancelImagePreviewHide();
                                return;
                            }
                            frScheduleImagePreviewHide();
                        }
                    },
                    {
                        title: "<input type=\"checkbox\" id=\"fr-select-all\">",
                        field: '_fr_select',
                        hozAlign: 'center',
                        headerSort: false,
                        width: 38,
                        download: false,
                        visible: false,
                        frozen: true,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const sku = String(d.sku || '');
                            const esc = sku.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            const chk = frSelectedSkus.has(d.sku) ? 'checked' : '';
                            return '<input type="checkbox" class="fr-sku-chk" data-sku="' + esc + '" ' + chk + '>';
                        }
                    },
                    {
                        title: 'Parent', field: 'parent', width: 120, frozen: true,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const v = cell.getValue() || '';
                            if (!v) return '<span style="color:#adb5bd;">–</span>';
                            return '<span style="color:#0d6efd;font-size:11px;font-weight:600;">' + v + '</span>';
                        }
                    },
                    {
                        title: 'SKU', field: 'sku', minWidth: 200, frozen: true, headerFilter: 'input',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const val = cell.getValue() || '';
                            if (d.is_parent) {
                                return '<span style="color:#0f5132;font-size:13px;font-weight:700;">' + String(val).replace(/</g, '&lt;') + '</span>';
                            }
                            const raw = String(val);
                            const esc = raw.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                            return '<span class="d-inline-flex align-items-center gap-1">' +
                                '<span class="fw-bold">' + esc + '</span>' +
                                '<button type="button" class="btn btn-sm btn-link p-0 fr-copy-sku-btn" data-sku="' + esc + '" title="Copy SKU" ' +
                                'style="min-width:auto;line-height:1;color:#6c757d;vertical-align:middle;"><i class="fas fa-copy" style="font-size:12px;"></i></button>' +
                                '</span>';
                        }
                    },
                    {
                        title: 'B/S',
                        field: 'buyer_link',
                        headerSort: false,
                        hozAlign: 'center',
                        width: 64,
                        download: false,
                        frozen: true,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const b = d.buyer_link;
                            const s = d.seller_link;
                            const parts = [];
                            if (b) {
                                parts.push('<a href="' + frEscUrlAttr(b) + '" target="_blank" rel="noopener noreferrer" ' +
                                    'class="fw-semibold" style="color:#0d6efd;" title="Buyer (Faire)">B</a>');
                            }
                            if (s) {
                                parts.push('<a href="' + frEscUrlAttr(s) + '" target="_blank" rel="noopener noreferrer" ' +
                                    'class="fw-semibold" style="color:#6f42c1;" title="Seller (portal)">S</a>');
                            }
                            return parts.length ? parts.join('<span class="text-muted" style="margin:0 3px;">|</span>') : '';
                        }
                    },
                    {
                        title: 'INV', field: 'inv', sorter: 'number', hozAlign: 'center', width: 55,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="font-weight:700;">' + cell.getValue() + '</span>';
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (val === 0) return '<span style="color:#dc3545;font-weight:600;">0</span>';
                            return '<span style="font-weight:600;">' + val + '</span>';
                        }
                    },
                    {
                        title: 'Faire stock', field: 'ae_stock', sorter: 'number', hozAlign: 'center', width: 82,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="font-weight:700;">' + cell.getValue() + '</span>';
                            const val = parseInt(cell.getValue(), 10) || 0;
                            if (val === 0) return '<span style="color:#dc3545;font-weight:600;">0</span>';
                            return '<span style="font-weight:600;">' + val + '</span>';
                        }
                    },
                    {
                        title: 'OV L30', field: 'ov_l30', sorter: 'number', hozAlign: 'center', width: 60,
                        formatter: function(cell) {
                            return '<span style="font-weight:700;">' + (parseInt(cell.getValue(), 10) || 0) + '</span>';
                        }
                    },
                    {
                        title: 'Dil', field: 'dil_percent', sorter: 'number', hozAlign: 'center', width: 55,
                        formatter: function(cell) {
                            const row = cell.getRow().getData();
                            const inv = parseFloat(row.inv) || 0;
                            const ovL30 = parseFloat(row.ov_l30) || 0;
                            if (inv === 0) return '<span style="color:#6c757d;">0%</span>';
                            const dil = (ovL30 / inv) * 100;
                            let color = dil < 16.66 ? '#a00211' : dil < 25 ? '#ffc107' : dil < 50 ? '#28a745' : '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                        }
                    },
                    {
                        title: 'Sold', field: 'al30', sorter: 'number', hozAlign: 'center', width: 55,
                        formatter: function(cell) {
                            const v = parseInt(cell.getValue(), 10) || 0;
                            return '<span style="font-weight:700;">' + v + '</span>';
                        }
                    },
                    {
                        title: 'Pricing', field: 'price', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            return '<span style="font-weight:700;">' + money(cell.getValue()) + '</span>';
                        }
                    },
                    {
                        title: 'Missing L', field: 'missing', hozAlign: 'center',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const value = (cell.getValue() || '').toString().trim().toUpperCase();
                            if (value === 'M') return '<span class="badge bg-danger">L</span>';
                            return '';
                        }
                    },
                    {
                        title: 'Map', field: 'map', hozAlign: 'center', width: 90,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            const val = (cell.getValue() || '').trim();
                            if (val === 'Map') return '<span style="color:#0d6efd;font-weight:bold;">Map</span>';
                            if (val.startsWith('N Map|')) {
                                const diff = val.split('|')[1];
                                return '<span style="color:#dc3545;font-weight:bold;">N Map (' + diff + ')</span>';
                            }
                            return '';
                        }
                    },
                    {
                        title: 'GPFT', field: 'gpft', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v)) return '<span style="color:#6c757d;">–</span>';
                            if (v === 0 && !d.is_parent) return '0%';
                            if (v === 0 && d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            let color = v < 10 ? '#a00211' : v < 15 ? '#ffc107' : v < 20 ? '#3591dc' : v <= 40 ? '#28a745' : '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:' + (d.is_parent ? '700' : '600') + ';">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'GROI', field: 'groi', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue()) || 0;
                            let color;
                            if (v < 40) color = '#a00211';
                            else if (v < 75) color = '#ffc107';
                            else if (v < 125) color = '#3591dc';
                            else if (v < 250) color = '#28a745';
                            else color = '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:700;">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'Profit', field: 'profit', sorter: 'number', hozAlign: 'right', visible: false,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue()) || 0;
                            if (d.is_parent) {
                                if (v === 0) return '<span style="color:#6c757d;">–</span>';
                                const color = v >= 0 ? '#28a745' : '#dc3545';
                                return '<span style="color:' + color + ';font-weight:700;">' + money(v) + '</span>';
                            }
                            return money(v);
                        }
                    },
                    {
                        title: 'Sales', field: 'sales', sorter: 'number', hozAlign: 'right', visible: false,
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const v = parseFloat(cell.getValue()) || 0;
                            if (d.is_parent) {
                                if (v === 0) return '<span style="color:#6c757d;">–</span>';
                                return '<span style="font-weight:700;">' + money(v) + '</span>';
                            }
                            return money(v);
                        }
                    },
                    {
                        title: 'LP', field: 'lp', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            return money(cell.getValue());
                        }
                    },
                    {
                        title: 'Sprice', field: 'sprice', sorter: 'number', hozAlign: 'right',
                        editor: 'number', editorParams: { min: 0, step: 0.01 },
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            return '<span style="font-weight:600;">' + money(parseFloat(cell.getValue()) || 0) + '</span>';
                        }
                    },
                    {
                        title: 'SGPFT', field: 'sgpft', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v) || v === 0) return '0%';
                            let color = v < 10 ? '#a00211' : v < 15 ? '#ffc107' : v < 20 ? '#3591dc' : v <= 40 ? '#28a745' : '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:600;">' + Math.round(v) + '%</span>';
                        }
                    },
                    {
                        title: 'SROI', field: 'sroi', sorter: 'number', hozAlign: 'right',
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '<span style="color:#6c757d;">–</span>';
                            const v = parseFloat(cell.getValue());
                            if (isNaN(v) || v === 0) return '<span style="font-weight:700;">0%</span>';
                            let color;
                            if (v < 40) color = '#a00211';
                            else if (v < 75) color = '#ffc107';
                            else if (v < 125) color = '#3591dc';
                            else if (v < 250) color = '#28a745';
                            else color = '#e83e8c';
                            return '<span style="color:' + color + ';font-weight:700;">' + Math.round(v) + '%</span>';
                        }
                    },
                ],
                dataLoaded: function(data) {
                    frResetSkuColHoverWidth();
                    frRemoveImagePreview();
                    updateSummary(data);
                },
                dataFiltered: function(filters, rows) { updateSummary(rows); },
                dataProcessed: function() { updateSummary(); },
                renderComplete: function() { updateSummary(); }
            });

            table.on('tableBuilt', function() {
                frBuildColumnDropdown();
                frApplyColumnVisibilityFromServer();
            });

            table.on('scrollVertical', frRemoveImagePreview);
            table.on('scrollHorizontal', frRemoveImagePreview);

            $('#faire-pricing-table').on('mouseover', function(e) {
                if (!table || !e.target || typeof e.target.closest !== 'function') return;
                if (!e.target.closest('[tabulator-field="sku"]')) return;
                if (frSkuColHoverActive) return;
                const col = table.getColumn('sku');
                if (!col) return;
                frSkuColHoverBase = col.getWidth();
                if (frSkuColHoverBase <= 0) return;
                col.setWidth(Math.round(frSkuColHoverBase * 1.2));
                frSkuColHoverActive = true;
            });

            $('#faire-pricing-table').on('mouseout', function(e) {
                if (!table || !frSkuColHoverActive) return;
                const related = e.relatedTarget;
                const root = this;
                if (related && root.contains(related) && typeof related.closest === 'function' && related.closest('[tabulator-field="sku"]')) {
                    return;
                }
                const col = table.getColumn('sku');
                if (col && frSkuColHoverBase != null) col.setWidth(frSkuColHoverBase);
                frSkuColHoverActive = false;
                frSkuColHoverBase = null;
            });

            $(document).on('click', '.fr-copy-sku-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const sku = $(this).attr('data-sku');
                if (!sku) return;
                const done = function() {
                    if (window.toastr) toastr.success('SKU copied');
                };
                const fail = function() {
                    if (window.toastr) toastr.error('Could not copy SKU');
                    else alert('Could not copy SKU');
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(sku).then(done).catch(fail);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = sku;
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    try {
                        if (document.execCommand('copy')) done(); else fail();
                    } catch (err) { fail(); }
                    document.body.removeChild(ta);
                }
            });

            frSyncPriceModeUi();

            $('#fr-price-mode-btn').on('click', function() {
                if (!frDecreaseModeActive && !frIncreaseModeActive) {
                    frDecreaseModeActive = true;
                    frIncreaseModeActive = false;
                } else if (frDecreaseModeActive) {
                    frDecreaseModeActive = false;
                    frIncreaseModeActive = true;
                } else {
                    frDecreaseModeActive = false;
                    frIncreaseModeActive = false;
                }
                frSyncPriceModeUi();
            });

            $('#fr-discount-type').on('change', function() {
                $('#fr-discount-input').attr('placeholder', $(this).val() === 'percentage' ? 'Enter %' : 'Enter $');
            });
            $('#fr-apply-discount-btn').on('click', function() { frApplyDiscount(); });
            $('#fr-discount-input').on('keypress', function(e) { if (e.which === 13) frApplyDiscount(); });
            $('#fr-clear-sprice-btn').on('click', function() { frClearSpriceForSelected(); });

            $(document).on('change', '#fr-select-all', function() {
                const checked = $(this).prop('checked');
                const rows = table.getData('active').filter(function(d) { return !d.is_parent; });
                rows.forEach(function(d) {
                    if (checked) frSelectedSkus.add(d.sku); else frSelectedSkus.delete(d.sku);
                });
                $('.fr-sku-chk').prop('checked', checked);
                frUpdateSelectedCount();
            });

            $(document).on('change', '.fr-sku-chk', function() {
                const sku = $(this).attr('data-sku');
                if ($(this).prop('checked')) frSelectedSkus.add(sku); else frSelectedSkus.delete(sku);
                frUpdateSelectedCount();
            });

            table.on('cellEdited', function(cell) {
                if (cell.getField() !== 'sprice') return;
                const d = cell.getRow().getData();
                if (d.is_parent) return;
                const sku = d.sku;
                const sprice = parseFloat(cell.getValue()) || 0;
                const margin = parseFloat(d._margin) || 0.75;
                const lp = parseFloat(d.lp) || 0;
                const sgpft = sprice > 0 ? Math.round(((sprice * margin - lp) / sprice) * 100) : 0;
                const sroi = lp > 0 ? Math.round(((sprice * margin - lp) / lp) * 100) : 0;
                cell.getRow().update({ sgpft: sgpft, sroi: sroi });
                saveFaireSpriceUpdates([{ sku: sku, sprice: sprice }]);
            });

            $('#fr-pricing-parent-search, #fr-pricing-sku-search').on('input', function() { applyFilters(); });
            $('#fr-row-type-filter, #fr-inv-filter, #fr-stock-filter, #fr-gpft-filter, #fr-roi-filter, #fr-fqty-filter, #fr-map-filter').on('change', function() { applyFilters(); });

            $(document).on('click', '.fr-dil-toggle', function(e) {
                e.stopPropagation();
                $(this).closest('.fr-manual-dropdown').toggleClass('show');
            });
            $(document).on('click', '.fr-dil-item', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.fr-dil-item').removeClass('active');
                $(this).addClass('active');
                const circle = $(this).find('.fr-sc').clone();
                $('#fr-dil-btn').html('').append(circle).append('DIL%');
                $(this).closest('.fr-manual-dropdown').removeClass('show');
                applyFilters();
            });
            $(document).on('click', function() { $('.fr-manual-dropdown').removeClass('show'); });

            $('#fr-missing-badge').on('click', function() {
                frMissingActive = !frMissingActive;
                frNMapActive = frZeroSoldActive = frMoreSoldActive = false;
                applyFilters();
            });
            $('#fr-map-badge').on('click', function() {
                frNMapActive = !frNMapActive;
                frMissingActive = frZeroSoldActive = frMoreSoldActive = false;
                applyFilters();
            });
            $('#fr-zero-sold-badge').on('click', function() {
                frZeroSoldActive = !frZeroSoldActive;
                frMoreSoldActive = frMissingActive = frNMapActive = false;
                applyFilters();
            });
            $('#fr-more-sold-badge').on('click', function() {
                frMoreSoldActive = !frMoreSoldActive;
                frZeroSoldActive = frMissingActive = frNMapActive = false;
                applyFilters();
            });

            $('#fr-refresh-pricing').on('click', function() {
                table.setData('/faire/pricing-data');
            });
            $('#fr-export-pricing').on('click', function() {
                table.download('csv', 'faire_analytics_data.csv');
            });

            const frColMenu = document.getElementById('fr-column-dropdown-menu');
            if (frColMenu) {
                frColMenu.addEventListener('change', function(e) {
                    if (e.target.classList.contains('fr-column-toggle')) {
                        const field = e.target.getAttribute('data-field');
                        const col = field ? table.getColumn(field) : null;
                        if (col) {
                            if (e.target.checked) {
                                col.show();
                            } else {
                                col.hide();
                            }
                            frSaveColumnVisibilityToServer();
                        }
                    }
                });
            }
            document.getElementById('fr-show-all-columns-btn')?.addEventListener('click', function() {
                if (!table) return;
                table.getColumns().forEach(function(col) {
                    const f = col.getField();
                    if (f && f !== '_fr_select') {
                        col.show();
                    }
                });
                frBuildColumnDropdown();
                frSaveColumnVisibilityToServer();
            });

            $('#frUploadPriceSheetBtn').on('click', function() {
                const file = document.getElementById('frPriceSheetFile').files[0];
                if (!file) {
                    alert('Please select a file first.');
                    return;
                }
                const formData = new FormData();
                formData.append('price_file', file);
                formData.append('_token', '{{ csrf_token() }}');
                $.ajax({
                    url: '/faire/pricing-upload-price',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (window.toastr) toastr.success(response.message || 'Upload completed.');
                        else alert(response.message || 'Upload completed.');
                        $('#uploadFairePriceModal').modal('hide');
                        $('#frPriceSheetFile').val('');
                        table.setData('/faire/pricing-data');
                    },
                    error: function(xhr) {
                        const message = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Upload failed.';
                        if (window.toastr) toastr.error(message);
                        else alert(message);
                    }
                });
            });
        });
    </script>
@endsection
