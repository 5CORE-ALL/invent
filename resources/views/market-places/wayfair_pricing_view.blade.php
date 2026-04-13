@extends('layouts.vertical', ['title' => 'Wayfair Analytics', 'sidenav' => 'condensed'])

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
        .tabulator-row.wf-parent-row,
        .tabulator-row.wf-parent-row .tabulator-cell {
            background-color: #d1e7dd !important;
            font-weight: 700 !important;
            min-height: 48px !important;
            color: #0f5132;
        }
        .tabulator .tabulator-footer {
            background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important;
            padding: 10px 16px !important;
        }
        .wf-manual-dropdown { position: relative; display: inline-block; }
        .wf-manual-dropdown .dropdown-menu {
            position: absolute; top: 100%; left: 0; z-index: 1050;
            display: none; min-width: 200px; padding: .5rem 0; margin: 0;
            background: #fff; border: 1px solid #dee2e6; border-radius: .375rem;
            box-shadow: 0 .125rem .25rem rgba(0,0,0,.075);
        }
        .wf-manual-dropdown.show .dropdown-menu { display: block; }
        .wf-dropdown-item {
            display: block; width: 100%; padding: .5rem 1rem; clear: both;
            font-weight: 400; color: #212529; text-decoration: none;
            background: transparent; border: 0; cursor: pointer; white-space: nowrap;
        }
        .wf-dropdown-item:hover { background: #e9ecef; }
        .wf-sc { display:inline-block; width:12px; height:12px; border-radius:50%; margin-right:6px; border:1px solid #ddd; }
        .wf-sc.def { background:#6c757d; }
        .wf-sc.red { background:#dc3545; }
        .wf-sc.yellow { background:#ffc107; }
        .wf-sc.green { background:#28a745; }
        .wf-sc.pink { background:#e83e8c; }
        /* SKU column: smooth width change when hover expands (+20% via JS) */
        #wayfair-pricing-table .tabulator-col[tabulator-field="sku"],
        #wayfair-pricing-table .tabulator-cell[tabulator-field="sku"] {
            transition: width 0.2s ease, min-width 0.2s ease;
        }
        #wf-image-hover-preview {
            pointer-events: auto;
            z-index: 10050;
        }
        .nrp-dot-cell { min-height: 32px; min-width: 44px; }
        .nrp-dot-cell .nrp-status-dot {
            display: inline-block; width: 12px; height: 12px; border-radius: 50%;
            border: 1px solid rgba(0,0,0,.12); flex-shrink: 0;
        }
        .nrp-dot-cell .nrp-nr-select {
            opacity: 0; cursor: pointer; font-size: 11px; padding: 0; border: 0; background: transparent;
        }
        .nrp-dot-cell .nrp-nr-select:focus { opacity: 1; outline: 1px solid #0d6efd; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Wayfair Analytics',
        'sub_title'  => 'Base-cost upload, SPRICE, and L30 sales from Wayfair daily; margin from Marketplace % (Wayfair)',
    ])

    <div class="row">
        <div class="col-12">
            <div class="alert alert-info py-2 mb-3">
                <strong>Sales</strong> match <a href="{{ route('wayfair.daily.sales') }}" class="alert-link">Wayfair Sales Data</a>
                (L30, unit price × quantity; join on <strong>Supplier Part Number</strong> / SKU). <strong>Analytics</strong> column is your uploaded base cost (New Base Cost / Current Base Cost; optional stock).
            </div>

            <div class="card border-warning mb-3">
                <div class="card-header bg-warning bg-opacity-25 py-2">
                    <strong><i class="fas fa-upload me-1"></i> Base cost upload</strong>
                    <span class="text-muted small ms-2">Table below shows merged product masters, sales, and uploaded base costs.</span>
                </div>
                <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
                    <a href="{{ route('wayfair.pricing.price.sample') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-download"></i> Sample CSV
                    </a>
                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#uploadWayfairPriceModal">
                        <i class="fas fa-upload"></i> Upload price
                    </button>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <select id="wf-row-type-filter" class="form-select form-select-sm" style="width:120px;">
                            <option value="all" selected>All Rows</option>
                            <option value="parents">Parents</option>
                            <option value="skus">SKUs</option>
                        </select>
                        <select id="wf-inv-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all">All Inventory</option>
                            <option value="zero">0 Inventory</option>
                            <option value="more" selected>More than 0</option>
                        </select>
                        <select id="wf-stock-filter" class="form-select form-select-sm" style="width:150px;">
                            <option value="all">Wayfair stock</option>
                            <option value="zero">0 Wayfair stock</option>
                            <option value="more">Wayfair stock &gt; 0</option>
                        </select>
                        <div class="d-flex flex-column gap-1" style="width:130px;" title="CVR = sold (al30) ÷ OV L30">
                            <select id="wf-gpft-filter" class="form-select form-select-sm">
                                <option value="all">GPFT%</option>
                                <option value="negative">Negative</option>
                                <option value="0-10">0–10%</option>
                                <option value="10-20">10–20%</option>
                                <option value="20-30">20–30%</option>
                                <option value="30-40">30–40%</option>
                                <option value="40-50">40–50%</option>
                                <option value="60plus">Above 60%</option>
                            </select>
                            <select id="wf-cvr-filter" class="form-select form-select-sm">
                                <option value="all">All CVR%</option>
                                <option value="0-0">0%</option>
                                <option value="0-2">0-2%</option>
                                <option value="2-4">2-4%</option>
                                <option value="4-7">4-7%</option>
                                <option value="7-13">7-13%</option>
                                <option value="13plus">13%+</option>
                            </select>
                        </div>
                        <select id="wf-roi-filter" class="form-select form-select-sm" style="width:130px;">
                            <option value="all">ROI%</option>
                            <option value="lt40">&lt; 40%</option>
                            <option value="40-75">40–75%</option>
                            <option value="75-125">75–125%</option>
                            <option value="125-175">125–175%</option>
                            <option value="175-250">175–250%</option>
                            <option value="gt250">&gt; 250%</option>
                        </select>
                        <select id="wf-fqty-filter" class="form-select form-select-sm" style="width:130px;" title="Units sold (Wayfair daily L30)">
                            <option value="all">Sold</option>
                            <option value="0">0</option>
                            <option value="0-10">1–10</option>
                            <option value="10plus">10+</option>
                        </select>
                        <select id="wf-map-filter" class="form-select form-select-sm" style="width:120px;">
                            <option value="all">Map</option>
                            <option value="map">Map only</option>
                            <option value="nmap">N Map only</option>
                        </select>
                        <div class="wf-manual-dropdown">
                            <button class="btn btn-light btn-sm wf-dil-toggle" type="button" id="wf-dil-btn">
                                <span class="wf-sc def"></span>DIL%
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="wf-dropdown-item wf-dil-item active" href="#" data-color="all">
                                    <span class="wf-sc def"></span>All DIL</a></li>
                                <li><a class="wf-dropdown-item wf-dil-item" href="#" data-color="red">
                                    <span class="wf-sc red"></span>Red (&lt;16.7%)</a></li>
                                <li><a class="wf-dropdown-item wf-dil-item" href="#" data-color="yellow">
                                    <span class="wf-sc yellow"></span>Yellow (16.7–25%)</a></li>
                                <li><a class="wf-dropdown-item wf-dil-item" href="#" data-color="green">
                                    <span class="wf-sc green"></span>Green (25–50%)</a></li>
                                <li><a class="wf-dropdown-item wf-dil-item" href="#" data-color="pink">
                                    <span class="wf-sc pink"></span>Pink (50%+)</a></li>
                            </ul>
                        </div>
                        <input type="text" id="wf-pricing-parent-search" class="form-control form-control-sm" style="max-width:200px;" placeholder="Search parent..." title="Filter by Parent column">
                        <input type="text" id="wf-pricing-sku-search" class="form-control form-control-sm" style="max-width:220px;" placeholder="Search SKU...">
                        <button type="button" id="wf-refresh-pricing" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                        <button type="button" id="wf-export-pricing" class="btn btn-sm btn-success">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </button>
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="wfColumnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-eye"></i> Columns
                            </button>
                            <ul class="dropdown-menu py-1" aria-labelledby="wfColumnVisibilityDropdown" id="wf-column-dropdown-menu" style="max-height: 400px; overflow-y: auto; min-width: 220px;">
                            </ul>
                        </div>
                        <button type="button" id="wf-show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                            <i class="fa fa-eye"></i> Show all
                        </button>
                        <button id="wf-price-mode-btn" type="button" class="btn btn-sm btn-secondary" title="Cycle: Off → Decrease → Increase → Same SPRICE (all rows)">
                            <i class="fas fa-exchange-alt"></i> Pricing mode
                        </button>
                    </div>

                    <div id="wf-discount-container" class="p-2 bg-light border rounded mb-2" style="display:none;">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span id="wf-selected-skus-count" class="fw-bold text-secondary"></span>
                            <span id="wf-discount-type-wrap">
                            <select id="wf-discount-type" class="form-select form-select-sm" style="width:120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                            </span>
                            <input type="number" id="wf-discount-input" class="form-control form-control-sm" placeholder="Enter %" step="0.01" style="width:110px;">
                            <button id="wf-apply-discount-btn" type="button" class="btn btn-primary btn-sm">Apply</button>
                            <button id="wf-clear-sprice-btn" type="button" class="btn btn-danger btn-sm">
                                <i class="fas fa-eraser"></i> Clear SPRICE
                            </button>
                        </div>
                    </div>

                    <div id="wf-summary-stats" class="mt-2 p-3 bg-light rounded mb-3">
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-primary fs-6 p-2" id="wf-total-sales-badge" style="font-weight:700;">Sales: $0</span>
                            <span class="badge bg-warning fs-6 p-2" id="wf-total-fqty-badge" style="font-weight:700;color:#111;">Sold: 0</span>
                            <span class="badge bg-success fs-6 p-2 d-none" id="wf-total-profit-badge" style="font-weight:700;" aria-hidden="true">Profit: 0</span>
                            <span class="badge bg-info fs-6 p-2" id="wf-avg-gpft-badge" style="font-weight:700;color:#111;" title="Order-style: margin × L30 sales − LP×sold (margin from Marketplace % for Wayfair).">PFt: 0%</span>
                            <span class="badge bg-secondary fs-6 p-2" id="wf-avg-roi-badge" style="font-weight:700;color:#111;">ROI: 0%</span>
                            <span class="badge bg-danger fs-6 p-2" id="wf-missing-badge" style="font-weight:700;">Missing L: 0</span>
                            <span class="badge fs-6 p-2" id="wf-map-badge" style="font-weight:700;background:#0d6efd;color:#fff;">N Map: 0</span>
                            <span class="badge fs-6 p-2" id="wf-zero-sold-badge" style="font-weight:700;background:#dc3545;color:#fff;">0 Sold: 0</span>
                            <span class="badge fs-6 p-2" id="wf-more-sold-badge" style="font-weight:700;background:#28a745;color:#fff;">&gt;0 Sold: 0</span>
                        </div>
                    </div>

                    <div id="wayfair-pricing-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="uploadWayfairPriceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Wayfair base cost sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="file" class="form-control" id="wfPriceSheetFile" accept=".xlsx,.xls,.csv,.txt">
                    <small class="text-muted">Minimum columns: <strong>Supplier Part Number</strong> (stored as <code>sku</code>) or column named <strong>sku</strong>, <strong>price</strong>, optional <strong>wayfair stock</strong> / <strong>wayfair_stock</strong>. Full Wayfair export (New Base Cost, etc.) also supported. TSV/CSV/Excel.</small>
                    <div class="mt-2 small"><a href="{{ asset('sample_excel/wayfair_pricing_sample.csv') }}" class="text-decoration-none" download>Static sample (supplier part)</a> · <a href="{{ asset('sample_excel/wayfair_pricing_sample_sku_column.csv') }}" class="text-decoration-none" download>Static sample (sku header)</a></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="wfUploadPriceSheetBtn">Upload</button>
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
        let wfMissingActive = false;
        let wfNMapActive = false;
        let wfZeroSoldActive = false;
        let wfMoreSoldActive = false;

        let wfDecreaseModeActive = false;
        let wfIncreaseModeActive = false;
        let wfUniformPriceModeActive = false;
        let wfSelectedSkus = new Set();

        let wfSkuColHoverBase = null;
        let wfSkuColHoverActive = false;

        function wfResetSkuColHoverWidth() {
            if (table && wfSkuColHoverActive && wfSkuColHoverBase != null) {
                try {
                    const col = table.getColumn('sku');
                    if (col) col.setWidth(wfSkuColHoverBase);
                } catch (err) { /* ignore */ }
            }
            wfSkuColHoverActive = false;
            wfSkuColHoverBase = null;
        }

        let wfImagePreviewHideTimer = null;
        let wfImagePreviewEl = null;

        function wfRemoveImagePreview() {
            if (wfImagePreviewHideTimer) {
                clearTimeout(wfImagePreviewHideTimer);
                wfImagePreviewHideTimer = null;
            }
            document.querySelectorAll('#wf-image-hover-preview').forEach(function(el) {
                el.remove();
            });
            wfImagePreviewEl = null;
        }

        function wfCancelImagePreviewHide() {
            if (wfImagePreviewHideTimer) {
                clearTimeout(wfImagePreviewHideTimer);
                wfImagePreviewHideTimer = null;
            }
        }

        function wfScheduleImagePreviewHide() {
            wfCancelImagePreviewHide();
            wfImagePreviewHideTimer = setTimeout(wfRemoveImagePreview, 220);
        }

        function wfEnsureImagePreviewListeners(wrap) {
            if (wrap.dataset.wfPreviewListeners === '1') return;
            wrap.dataset.wfPreviewListeners = '1';
            wrap.addEventListener('mouseenter', wfCancelImagePreviewHide);
            wrap.addEventListener('mouseleave', wfScheduleImagePreviewHide);
        }

        function wfClampImagePreviewPosition(wrap, clientX, clientY) {
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

        function wfShowImagePreview(clientX, clientY, fullUrl) {
            if (!fullUrl) return;
            wfCancelImagePreviewHide();
            const existing = wfImagePreviewEl;
            if (existing && document.body.contains(existing)) {
                const prevImg = existing.querySelector('img');
                if (prevImg && prevImg.getAttribute('src') === fullUrl) {
                    wfClampImagePreviewPosition(existing, clientX, clientY);
                    return;
                }
            }
            document.querySelectorAll('#wf-image-hover-preview').forEach(function(el) {
                el.remove();
            });
            wfImagePreviewEl = null;

            const wrap = document.createElement('div');
            wrap.id = 'wf-image-hover-preview';
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
            wfEnsureImagePreviewListeners(wrap);
            document.body.appendChild(wrap);
            wfImagePreviewEl = wrap;
            wfClampImagePreviewPosition(wrap, clientX, clientY);
        }

        function money(value) {
            return '$' + (parseFloat(value) || 0).toFixed(2);
        }

        function wfEscUrlAttr(url) {
            if (url == null || url === '') return '';
            return String(url).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        }

        function wfEscHtmlAttr(val) {
            if (val == null || val === '') return '';
            return String(val).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        }

        function wfUpdateForecastNrp(data, onSuccess, onFail) {
            onSuccess = typeof onSuccess === 'function' ? onSuccess : function() {};
            onFail = typeof onFail === 'function' ? onFail : function() {};
            $.post('{{ route("update.forecast.data") }}', {
                sku: data.sku,
                parent: data.parent != null ? String(data.parent) : '',
                column: 'NR',
                value: data.value,
                _token: $('meta[name="csrf-token"]').attr('content')
            }).done(function(res) {
                if (res.success) {
                    onSuccess();
                } else {
                    console.warn('NRP not saved:', res.message);
                    onFail();
                }
            }).fail(function(err) {
                console.error('NRP save failed:', err);
                alert('Error saving NRP.');
                onFail();
            });
        }

        function saveWayfairSpriceUpdates(updates) {
            $.ajax({
                url: '{{ route("wayfair.pricing.save.sprice") }}',
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { _token: '{{ csrf_token() }}', updates: updates },
                success: function(res) {
                    if (res.success) console.log('Wayfair SPRICE saved:', res.updated);
                },
                error: function(xhr) {
                    console.error('Wayfair SPRICE save error:', xhr.responseJSON);
                }
            });
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

        function wayfairMarginFromRow(row) {
            const m = parseFloat(row._margin);
            return Number.isFinite(m) && m > 0 ? m : 0.95;
        }

        function wfRoundToRetailPrice(price) {
            return Math.ceil(price) - 0.01;
        }

        /** SKU rows on the current pagination page only (not the whole filtered dataset). */
        function wfVisibleSkuRows() {
            if (!table) return [];
            try {
                let rows = typeof table.getRows === 'function' ? table.getRows(true) : [];
                if (!Array.isArray(rows)) rows = [];
                const allFiltered = typeof table.getRows === 'function' ? table.getRows() : [];
                const pageSize = typeof table.getPageSize === 'function' ? table.getPageSize() : null;
                const page = typeof table.getPage === 'function' ? table.getPage() : null;
                if (
                    pageSize && page &&
                    rows.length === allFiltered.length &&
                    allFiltered.length > pageSize
                ) {
                    const start = (page - 1) * pageSize;
                    rows = allFiltered.slice(start, start + pageSize);
                }
                return rows.filter(function(row) {
                    try {
                        return row && typeof row.getData === 'function' && !row.getData().is_parent;
                    } catch (e) {
                        return false;
                    }
                });
            } catch (err) {
                return [];
            }
        }

        function wfSyncSelectAllHeaderCheckbox() {
            const el = document.getElementById('wf-select-all');
            if (!el || !table) return;
            const skuRows = wfVisibleSkuRows();
            if (!skuRows.length) {
                el.checked = false;
                el.indeterminate = false;
                return;
            }
            let selected = 0;
            skuRows.forEach(function(row) {
                const d = row.getData();
                const sku = d && d.sku != null ? String(d.sku) : '';
                if (sku && wfSelectedSkus.has(sku)) selected++;
            });
            el.checked = selected === skuRows.length && skuRows.length > 0;
            el.indeterminate = selected > 0 && selected < skuRows.length;
        }

        function wfSyncPriceModeUi() {
            const $btn = $('#wf-price-mode-btn');
            const selectCol = table ? table.getColumn('_wf_select') : null;
            $('#wf-discount-type-wrap').toggle(!wfUniformPriceModeActive);
            if (wfUniformPriceModeActive) {
                $btn.removeClass('btn-secondary btn-danger btn-primary').addClass('btn-warning')
                    .html('<i class="fas fa-equals"></i> Same SPRICE');
                if (selectCol) selectCol.show();
                $('#wf-discount-input').attr('placeholder', 'SPRICE $');
                wfUpdateSelectedCount();
                return;
            }
            $('#wf-discount-input').attr('placeholder', $('#wf-discount-type').val() === 'percentage' ? 'Enter %' : 'Enter $');
            if (wfDecreaseModeActive) {
                $btn.removeClass('btn-secondary btn-primary btn-warning').addClass('btn-danger')
                    .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                if (selectCol) selectCol.show();
                wfUpdateSelectedCount();
                return;
            }
            if (wfIncreaseModeActive) {
                $btn.removeClass('btn-secondary btn-danger btn-warning').addClass('btn-primary')
                    .html('<i class="fas fa-arrow-up"></i> Increase ON');
                if (selectCol) selectCol.show();
                wfUpdateSelectedCount();
                return;
            }
            $btn.removeClass('btn-danger btn-primary btn-warning').addClass('btn-secondary')
                .html('<i class="fas fa-exchange-alt"></i> Pricing mode');
            if (selectCol) selectCol.hide();
            wfSelectedSkus.clear();
            wfUpdateSelectedCount();
        }

        function wfUpdateSelectedCount() {
            if (wfUniformPriceModeActive) {
                const cnt = wfSelectedSkus.size;
                if (cnt > 0) {
                    $('#wf-selected-skus-count').text(
                        cnt + ' SKU' + (cnt !== 1 ? 's' : '') + ' selected — Apply uses these rows only (clear checks for all SKUs).'
                    );
                } else {
                    $('#wf-selected-skus-count').text('Same SPRICE: no rows checked — Apply updates every SKU (not parent summaries).');
                }
            } else {
                const cnt = wfSelectedSkus.size;
                $('#wf-selected-skus-count').text(cnt + ' SKU' + (cnt !== 1 ? 's' : '') + ' selected');
            }
            const showPanel = wfUniformPriceModeActive
                || (wfSelectedSkus.size > 0 && (wfDecreaseModeActive || wfIncreaseModeActive));
            $('#wf-discount-container').toggle(showPanel);
        }

        /** Clear pricing-mode SKU checkboxes when table filters change (visible row set changes). */
        function wfClearSkuSelections() {
            wfSelectedSkus.clear();
            $('#wf-select-all').prop('checked', false).prop('indeterminate', false);
            wfUpdateSelectedCount();
            if (table) {
                try { table.redraw(true); } catch (err) { /* ignore */ }
            }
        }

        function wfApplyDiscount() {
            const discountType = $('#wf-discount-type').val();
            const discountVal = parseFloat($('#wf-discount-input').val());

            if (wfUniformPriceModeActive) {
                if (isNaN(discountVal) || discountVal <= 0 || !table) return;
                const newSprice = wfRoundToRetailPrice(Math.max(0.99, discountVal));
                const updates = [];
                const limitToSelection = wfSelectedSkus.size > 0;
                table.getRows().forEach(function(row) {
                    const d = row.getData();
                    if (d.is_parent) return;
                    if (limitToSelection && !wfSelectedSkus.has(d.sku)) return;
                    const margin = wayfairMarginFromRow(d);
                    const lp = parseFloat(d.lp) || 0;
                    const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - lp) / newSprice) * 100) : 0;
                    const sroi = lp > 0 ? Math.round(((newSprice * margin - lp) / lp) * 100) : 0;
                    row.update({ sprice: newSprice, sgpft: sgpft, sroi: sroi });
                    updates.push({ sku: d.sku, sprice: newSprice });
                });
                if (updates.length) saveWayfairSpriceUpdates(updates);
                $('#wf-discount-input').val('');
                return;
            }

            if (isNaN(discountVal) || discountVal === 0 || wfSelectedSkus.size === 0) return;

            const updates = [];
            wfSelectedSkus.forEach(function(sku) {
                const rows = table.searchRows('sku', '=', sku);
                if (!rows.length) return;
                const row = rows[0];
                const rowData = row.getData();
                const currentPrice = parseFloat(rowData.price) || 0;
                if (currentPrice <= 0) return;

                let newSprice;
                if (discountType === 'percentage') {
                    newSprice = wfIncreaseModeActive
                        ? currentPrice * (1 + discountVal / 100)
                        : currentPrice * (1 - discountVal / 100);
                } else {
                    newSprice = wfIncreaseModeActive
                        ? currentPrice + discountVal
                        : currentPrice - discountVal;
                }
                newSprice = wfRoundToRetailPrice(Math.max(0.99, newSprice));

                const margin = wayfairMarginFromRow(rowData);
                const lp = parseFloat(rowData.lp) || 0;
                const sgpft = newSprice > 0 ? Math.round(((newSprice * margin - lp) / newSprice) * 100) : 0;
                const sroi = lp > 0 ? Math.round(((newSprice * margin - lp) / lp) * 100) : 0;

                row.update({ sprice: newSprice, sgpft: sgpft, sroi: sroi });
                updates.push({ sku: sku, sprice: newSprice });
            });

            if (updates.length) saveWayfairSpriceUpdates(updates);
            $('#wf-discount-input').val('');
        }

        function wfClearSpriceForSelected() {
            if (wfUniformPriceModeActive) {
                const limitToSelection = wfSelectedSkus.size > 0;
                const msg = limitToSelection
                    ? ('Clear SPRICE for ' + wfSelectedSkus.size + ' selected SKU(s)?')
                    : 'Clear SPRICE for ALL SKU rows?';
                if (!confirm(msg)) return;
                const updates = [];
                table.getRows().forEach(function(row) {
                    const d = row.getData();
                    if (d.is_parent) return;
                    if (limitToSelection && !wfSelectedSkus.has(d.sku)) return;
                    row.update({ sprice: 0, sgpft: 0, sroi: 0 });
                    updates.push({ sku: d.sku, sprice: 0 });
                });
                if (updates.length) saveWayfairSpriceUpdates(updates);
                return;
            }
            if (!wfSelectedSkus.size) return;
            if (!confirm('Clear SPRICE for ' + wfSelectedSkus.size + ' SKU(s)?')) return;
            const updates = [];
            table.getRows().forEach(function(row) {
                const d = row.getData();
                if (wfSelectedSkus.has(d.sku) && !d.is_parent) {
                    row.update({ sprice: 0, sgpft: 0, sroi: 0 });
                    updates.push({ sku: d.sku, sprice: 0 });
                }
            });
            if (updates.length) saveWayfairSpriceUpdates(updates);
        }

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

                const keep = wayfairMarginFromRow(row);
                let rowOrderPft = 0;
                if (sales > 0 && fqty > 0) {
                    rowOrderPft = keep * sales - lp * fqty;
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

            $('#wf-total-sales-badge').text('Sales: $' + Math.round(totalSales).toLocaleString());
            $('#wf-total-fqty-badge').text('Sold: ' + totalFqty.toLocaleString());
            $('#wf-total-profit-badge').text('Profit: ' + Math.round(totalProfit).toLocaleString());
            $('#wf-avg-gpft-badge').text('PFt: ' + Math.round(pftPct) + '%');
            $('#wf-avg-roi-badge').text('ROI: ' + Math.round(roiPct) + '%');
            $('#wf-missing-badge').text('Missing L: ' + missingCount.toLocaleString());
            $('#wf-map-badge').text('N Map: ' + mapCount.toLocaleString());
            $('#wf-zero-sold-badge').text('0 Sold: ' + zeroSold.toLocaleString());
            $('#wf-more-sold-badge').text('>0 Sold: ' + moreSold.toLocaleString());
        }

        function applyFilters() {
            if (!table) return;
            table.clearFilter();

            const skuSearch = ($('#wf-pricing-sku-search').val() || '').toLowerCase().trim();
            const parentSearch = ($('#wf-pricing-parent-search').val() || '').toLowerCase().trim();
            const rowType = $('#wf-row-type-filter').val();
            const invFilter = $('#wf-inv-filter').val();
            const stockFilter = $('#wf-stock-filter').val();
            const gpftFilter = $('#wf-gpft-filter').val();
            const cvrFilter = $('#wf-cvr-filter').val();
            const roiFilter = $('#wf-roi-filter').val();
            const fqtyFilter = $('#wf-fqty-filter').val();
            const mapFilter = $('#wf-map-filter').val();
            const dilColor = $('.wf-dil-item.active').data('color') || 'all';

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
            if (cvrFilter !== 'all') {
                table.addFilter(function(d) {
                    const ov = parseFloat(d.ov_l30) || 0;
                    const sold = parseFloat(d.al30) || 0;
                    const cvrPercent = ov > 0 ? (sold / ov) * 100 : 0;
                    const cvrRounded = Math.round(cvrPercent * 100) / 100;
                    if (cvrFilter === '0-0') return cvrRounded === 0;
                    if (cvrFilter === '0-2') return cvrRounded > 0 && cvrRounded <= 2;
                    if (cvrFilter === '2-4') return cvrRounded > 2 && cvrRounded <= 4;
                    if (cvrFilter === '4-7') return cvrRounded > 4 && cvrRounded <= 7;
                    if (cvrFilter === '7-13') return cvrRounded > 7 && cvrRounded <= 13;
                    if (cvrFilter === '13plus') return cvrRounded > 13;
                    return true;
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
            if (wfMissingActive) table.addFilter(d => (d.missing || '').trim().toUpperCase() === 'M');
            if (wfNMapActive) table.addFilter(d => (d.map || '').startsWith('N Map|'));
            if (wfZeroSoldActive) table.addFilter(d => (parseFloat(d.al30) || 0) === 0);
            if (wfMoreSoldActive) table.addFilter(d => (parseFloat(d.al30) || 0) > 0);
        }

        function wfBuildColumnDropdown() {
            if (!table) return;
            const menu = document.getElementById('wf-column-dropdown-menu');
            if (!menu) return;
            let html = '';
            table.getColumns().forEach(function(col) {
                const field = col.getField();
                const def = col.getDefinition();
                const titleRaw = def.title;
                const titleStr = titleRaw != null ? String(titleRaw) : '';
                const label = titleStr.replace(/<[^>]*>/g, '').trim() || field;
                if (field && field !== '_wf_select' && label) {
                    const isVisible = col.isVisible();
                    const fEsc = String(field).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                    const lEsc = String(label).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                    html += '<li class="dropdown-item px-2 py-1">' +
                        '<label class="d-flex align-items-center gap-2 mb-0 w-100" style="cursor:pointer;">' +
                        '<input type="checkbox" class="wf-column-toggle" data-field="' + fEsc + '" ' + (isVisible ? 'checked' : '') + '>' +
                        '<span>' + lEsc + '</span>' +
                        '</label></li>';
                }
            });
            menu.innerHTML = html;
        }

        function wfSaveColumnVisibilityToServer() {
            if (!table) return;
            const visibility = {};
            table.getColumns().forEach(function(col) {
                const field = col.getField();
                if (field && field !== '_wf_select') {
                    visibility[field] = col.isVisible();
                }
            });
            $.ajax({
                url: '{{ route("wayfair.pricing.column.set") }}',
                method: 'POST',
                data: { visibility: visibility, _token: '{{ csrf_token() }}' }
            });
        }

        function wfApplyColumnVisibilityFromServer() {
            if (!table) return;
            $.ajax({
                url: '{{ route("wayfair.pricing.column.get") }}',
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
                        wfBuildColumnDropdown();
                    }
                }
            });
        }

        $(document).ready(function() {
            table = new Tabulator('#wayfair-pricing-table', {
                ajaxURL: '{{ route("wayfair.pricing.data") }}',
                ajaxResponse: function(url, params, response) {
                    summaryDataCache = normalizeRows(response);
                    updateSummary(summaryDataCache);
                    return response;
                },
                layout: 'fitDataStretch',
                pagination: true,
                paginationSize: 50,
                paginationSizeSelector: [50, 100, 150, 200],
                initialSort: [],
                rowFormatter: function(row) {
                    if (row.getData().is_parent === true) {
                        row.getElement().classList.add('wf-parent-row');
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
                            return '<img src="' + esc + '" data-full="' + esc + '" class="wf-hover-thumb" alt="" ' +
                                'style="width:44px;height:44px;object-fit:cover;border-radius:4px;cursor:pointer;" ' +
                                'onerror="this.onerror=null;this.style.display=\'none\'">';
                        },
                        cellMouseOver: function(e, cell) {
                            if (cell.getRow().getData().is_parent) return;
                            const img = cell.getElement().querySelector('.wf-hover-thumb');
                            if (!img) return;
                            const fullUrl = img.getAttribute('data-full');
                            wfShowImagePreview(e.clientX, e.clientY, fullUrl);
                        },
                        cellMouseMove: function(e, cell) {
                            const preview = wfImagePreviewEl;
                            if (!preview || !document.body.contains(preview)) return;
                            if (cell.getRow().getData().is_parent) return;
                            const img = cell.getElement().querySelector('.wf-hover-thumb');
                            const fullUrl = img ? img.getAttribute('data-full') : '';
                            const big = preview.querySelector('img');
                            if (!fullUrl || !big || big.getAttribute('src') !== fullUrl) return;
                            wfClampImagePreviewPosition(preview, e.clientX, e.clientY);
                        },
                        cellMouseOut: function(e, cell) {
                            const related = e.relatedTarget;
                            if (related && typeof related.closest === 'function' && related.closest('#wf-image-hover-preview')) {
                                wfCancelImagePreviewHide();
                                return;
                            }
                            wfScheduleImagePreviewHide();
                        }
                    },
                    {
                        title: "<input type=\"checkbox\" id=\"wf-select-all\" title=\"Select all SKUs on this page only\">",
                        field: '_wf_select',
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
                            const chk = wfSelectedSkus.has(d.sku) ? 'checked' : '';
                            return '<input type="checkbox" class="wf-sku-chk" data-sku="' + esc + '" ' + chk + '>';
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
                                '<button type="button" class="btn btn-sm btn-link p-0 wf-copy-sku-btn" data-sku="' + esc + '" title="Copy SKU" ' +
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
                                parts.push('<a href="' + wfEscUrlAttr(b) + '" target="_blank" rel="noopener noreferrer" ' +
                                    'class="fw-semibold" style="color:#0d6efd;" title="Buyer (Wayfair)">B</a>');
                            }
                            if (s) {
                                parts.push('<a href="' + wfEscUrlAttr(s) + '" target="_blank" rel="noopener noreferrer" ' +
                                    'class="fw-semibold" style="color:#6f42c1;" title="Seller (Partners)">S</a>');
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
                        title: 'Wayfair stock', field: 'ae_stock', sorter: 'number', hozAlign: 'center', width: 82,
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
                        title: 'Price', field: 'price', sorter: 'number', hozAlign: 'right',
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
                    {
                        title: 'NRP',
                        field: 'nr',
                        minWidth: 52,
                        width: 56,
                        hozAlign: 'center',
                        headerSort: true,
                        accessor: function(value, data) {
                            const val = data && data.nr != null ? data.nr : value;
                            if (val === null || val === undefined) return '';
                            return String(val).trim().toUpperCase();
                        },
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            if (d.is_parent) return '';
                            let value = cell.getValue();
                            if (value === null || value === undefined || value === '') {
                                value = d.nr;
                            }
                            if (value === null || value === undefined) {
                                value = '';
                            } else {
                                value = String(value).trim().toUpperCase();
                            }
                            if (!value || value === '') {
                                value = 'REQ';
                            }
                            if (value !== 'REQ' && value !== 'NR' && value !== 'LATER') {
                                value = 'REQ';
                            }
                            const sku = String(d.sku || '');
                            const parent = d.parent != null ? String(d.parent) : '';
                            let dotColor = '#22c55e';
                            let tip = 'REQ';
                            if (value === 'NR') {
                                dotColor = '#dc3545';
                                tip = '2BDC';
                            } else if (value === 'LATER') {
                                dotColor = '#facc15';
                                tip = 'LATER';
                            }
                            const skuAttr = wfEscHtmlAttr(sku);
                            const parentAttr = wfEscHtmlAttr(parent);
                            return (
                                '<div class="nrp-dot-cell position-relative d-flex justify-content-center align-items-center w-100" title="' +
                                wfEscHtmlAttr(tip + ' (click to change)') + '">' +
                                '<span class="nrp-status-dot" style="background-color:' + dotColor + ';" aria-hidden="true"></span>' +
                                '<select class="form-select form-select-sm nrp-nr-select position-absolute top-0 start-0 w-100 h-100" ' +
                                'data-type="NR" data-sku="' + skuAttr + '" data-parent="' + parentAttr + '" ' +
                                'aria-label="NRP: ' + wfEscHtmlAttr(tip) + '">' +
                                '<option value="REQ"' + (value === 'REQ' ? ' selected' : '') + '>REQ</option>' +
                                '<option value="NR"' + (value === 'NR' ? ' selected' : '') + '>2BDC</option>' +
                                '<option value="LATER"' + (value === 'LATER' ? ' selected' : '') + '>LATER</option>' +
                                '</select></div>'
                            );
                        }
                    },
                ],
                dataLoaded: function(data) {
                    wfResetSkuColHoverWidth();
                    wfRemoveImagePreview();
                    updateSummary(data);
                },
                dataFiltered: function(filters, rows) { updateSummary(rows); },
                dataProcessed: function() { updateSummary(); },
                renderComplete: function() { updateSummary(); }
            });

            table.on('tableBuilt', function() {
                wfBuildColumnDropdown();
                wfApplyColumnVisibilityFromServer();
            });

            table.on('scrollVertical', wfRemoveImagePreview);
            table.on('scrollHorizontal', wfRemoveImagePreview);

            $('#wayfair-pricing-table').on('mouseover', function(e) {
                if (!table || !e.target || typeof e.target.closest !== 'function') return;
                if (!e.target.closest('[tabulator-field="sku"]')) return;
                if (wfSkuColHoverActive) return;
                const col = table.getColumn('sku');
                if (!col) return;
                wfSkuColHoverBase = col.getWidth();
                if (wfSkuColHoverBase <= 0) return;
                col.setWidth(Math.round(wfSkuColHoverBase * 1.2));
                wfSkuColHoverActive = true;
            });

            $('#wayfair-pricing-table').on('mouseout', function(e) {
                if (!table || !wfSkuColHoverActive) return;
                const related = e.relatedTarget;
                const root = this;
                if (related && root.contains(related) && typeof related.closest === 'function' && related.closest('[tabulator-field="sku"]')) {
                    return;
                }
                const col = table.getColumn('sku');
                if (col && wfSkuColHoverBase != null) col.setWidth(wfSkuColHoverBase);
                wfSkuColHoverActive = false;
                wfSkuColHoverBase = null;
            });

            wfSyncPriceModeUi();

            $('#wf-price-mode-btn').on('click', function() {
                if (!wfDecreaseModeActive && !wfIncreaseModeActive && !wfUniformPriceModeActive) {
                    wfDecreaseModeActive = true;
                    wfIncreaseModeActive = false;
                    wfUniformPriceModeActive = false;
                } else if (wfDecreaseModeActive) {
                    wfDecreaseModeActive = false;
                    wfIncreaseModeActive = true;
                    wfUniformPriceModeActive = false;
                } else if (wfIncreaseModeActive) {
                    wfDecreaseModeActive = false;
                    wfIncreaseModeActive = false;
                    wfUniformPriceModeActive = true;
                } else {
                    wfDecreaseModeActive = false;
                    wfIncreaseModeActive = false;
                    wfUniformPriceModeActive = false;
                }
                wfSyncPriceModeUi();
            });

            $('#wf-discount-type').on('change', function() {
                $('#wf-discount-input').attr('placeholder', $(this).val() === 'percentage' ? 'Enter %' : 'Enter $');
            });
            $('#wf-apply-discount-btn').on('click', function() { wfApplyDiscount(); });
            $('#wf-discount-input').on('keypress', function(e) { if (e.which === 13) wfApplyDiscount(); });
            $('#wf-clear-sprice-btn').on('click', function() { wfClearSpriceForSelected(); });

            $(document).on('change', '#wf-select-all', function() {
                const checked = $(this).prop('checked');
                const skuRows = wfVisibleSkuRows();
                skuRows.forEach(function(row) {
                    const d = row.getData();
                    const sku = d.sku != null ? String(d.sku) : '';
                    if (!sku) return;
                    if (checked) wfSelectedSkus.add(sku); else wfSelectedSkus.delete(sku);
                });
                skuRows.forEach(function(row) {
                    const cellEl = row.getElement();
                    if (!cellEl) return;
                    const chk = cellEl.querySelector('.wf-sku-chk');
                    if (chk) chk.checked = checked;
                });
                const head = document.getElementById('wf-select-all');
                if (head) head.indeterminate = false;
                wfUpdateSelectedCount();
            });

            $(document).on('change', '.wf-sku-chk', function() {
                const sku = $(this).attr('data-sku');
                if (!sku) return;
                if ($(this).prop('checked')) wfSelectedSkus.add(sku); else wfSelectedSkus.delete(sku);
                wfUpdateSelectedCount();
                wfSyncSelectAllHeaderCheckbox();
            });

            table.on('pageLoaded', function() {
                wfSyncSelectAllHeaderCheckbox();
            });
            table.on('renderComplete', function() {
                wfSyncSelectAllHeaderCheckbox();
            });

            table.on('cellEdited', function(cell) {
                if (cell.getField() !== 'sprice') return;
                const d = cell.getRow().getData();
                if (d.is_parent) return;
                const sku = d.sku;
                const sprice = parseFloat(cell.getValue()) || 0;
                const margin = wayfairMarginFromRow(d);
                const lp = parseFloat(d.lp) || 0;
                const sgpft = sprice > 0 ? Math.round(((sprice * margin - lp) / sprice) * 100) : 0;
                const sroi = lp > 0 ? Math.round(((sprice * margin - lp) / lp) * 100) : 0;
                cell.getRow().update({ sgpft: sgpft, sroi: sroi });
                saveWayfairSpriceUpdates([{ sku: sku, sprice: sprice }]);
            });

            $('#wf-pricing-parent-search, #wf-pricing-sku-search').on('input', function() { applyFilters(); });
            $('#wf-row-type-filter, #wf-inv-filter, #wf-stock-filter, #wf-gpft-filter, #wf-cvr-filter, #wf-roi-filter, #wf-fqty-filter, #wf-map-filter').on('change', function() {
                wfClearSkuSelections();
                applyFilters();
            });

            $(document).on('click', '.wf-dil-toggle', function(e) {
                e.stopPropagation();
                $(this).closest('.wf-manual-dropdown').toggleClass('show');
            });
            $(document).on('click', '.wf-dil-item', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.wf-dil-item').removeClass('active');
                $(this).addClass('active');
                const circle = $(this).find('.wf-sc').clone();
                $('#wf-dil-btn').html('').append(circle).append('DIL%');
                $(this).closest('.wf-manual-dropdown').removeClass('show');
                wfClearSkuSelections();
                applyFilters();
            });
            $(document).on('click', function() { $('.wf-manual-dropdown').removeClass('show'); });

            $(document).on('click', '.wf-copy-sku-btn', function(e) {
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

            $('#wf-missing-badge').on('click', function() {
                wfMissingActive = !wfMissingActive;
                wfNMapActive = wfZeroSoldActive = wfMoreSoldActive = false;
                wfClearSkuSelections();
                applyFilters();
            });
            $('#wf-map-badge').on('click', function() {
                wfNMapActive = !wfNMapActive;
                wfMissingActive = wfZeroSoldActive = wfMoreSoldActive = false;
                wfClearSkuSelections();
                applyFilters();
            });
            $('#wf-zero-sold-badge').on('click', function() {
                wfZeroSoldActive = !wfZeroSoldActive;
                wfMoreSoldActive = wfMissingActive = wfNMapActive = false;
                wfClearSkuSelections();
                applyFilters();
            });
            $('#wf-more-sold-badge').on('click', function() {
                wfMoreSoldActive = !wfMoreSoldActive;
                wfZeroSoldActive = wfMissingActive = wfNMapActive = false;
                wfClearSkuSelections();
                applyFilters();
            });

            $(document).on('change', '#wayfair-pricing-table .nrp-nr-select', function() {
                const $el = $(this);
                const newValue = String($el.val() || '').trim();
                const sku = $el.data('sku');
                const parent = $el.data('parent');
                if (!sku || !table) return;
                const rows = table.getRows().filter(function(r) {
                    const d = r.getData();
                    return !d.is_parent && String(d.sku) === String(sku);
                });
                const row = rows.length ? rows[0] : null;
                const prevRaw = row ? String(row.getData().nr ?? '').trim().toUpperCase() : '';
                const prevSelect = (prevRaw === 'NR' || prevRaw === 'LATER') ? prevRaw : 'REQ';
                wfUpdateForecastNrp(
                    { sku: sku, parent: parent, value: newValue },
                    function() {
                        if (row) {
                            row.update({ nr: newValue }, true);
                            const nrCell = row.getCells().find(function(c) { return c.getField() === 'nr'; });
                            if (nrCell) nrCell.reformat();
                        }
                    },
                    function() {
                        $el.val(prevSelect);
                    }
                );
            });

            $('#wf-refresh-pricing').on('click', function() {
                table.setData('{{ route("wayfair.pricing.data") }}');
            });
            $('#wf-export-pricing').on('click', function() {
                table.download('csv', 'wayfair_analytics_data.csv');
            });

            const wfColMenu = document.getElementById('wf-column-dropdown-menu');
            if (wfColMenu) {
                wfColMenu.addEventListener('change', function(e) {
                    if (e.target.classList.contains('wf-column-toggle')) {
                        const field = e.target.getAttribute('data-field');
                        const col = field ? table.getColumn(field) : null;
                        if (col) {
                            if (e.target.checked) {
                                col.show();
                            } else {
                                col.hide();
                            }
                            wfSaveColumnVisibilityToServer();
                        }
                    }
                });
            }
            document.getElementById('wf-show-all-columns-btn')?.addEventListener('click', function() {
                if (!table) return;
                table.getColumns().forEach(function(col) {
                    const f = col.getField();
                    if (f && f !== '_wf_select') {
                        col.show();
                    }
                });
                wfBuildColumnDropdown();
                wfSaveColumnVisibilityToServer();
            });

            $('#wfUploadPriceSheetBtn').on('click', function() {
                const file = document.getElementById('wfPriceSheetFile').files[0];
                if (!file) {
                    alert('Please select a file first.');
                    return;
                }
                const formData = new FormData();
                formData.append('price_file', file);
                formData.append('_token', '{{ csrf_token() }}');
                $.ajax({
                    url: '{{ route("wayfair.pricing.upload.price") }}',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (window.toastr) toastr.success(response.message || 'Upload completed.');
                        else alert(response.message || 'Upload completed.');
                        $('#uploadWayfairPriceModal').modal('hide');
                        $('#wfPriceSheetFile').val('');
                        table.setData('{{ route("wayfair.pricing.data") }}');
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
