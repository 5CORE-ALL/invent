{{--
    Shared body of the "<channel> Ads" Tabulator pages (Pinterest Ads, ChatGPT Ads). Same layout as /amazon-tabulator-view:
    filter bar, summary badges, Tabulator grid and the dynamic "Columns" header-box panel
    (resources/views/partials/tabulator-column-manager.blade.php).

    Include once per section from the page blade:
        @include('partials.ads-tabulator-page', ['adsPart' => 'css'])
        @include('partials.ads-tabulator-page', ['adsPart' => 'content', 'adsTitle' => '...', 'adsKey' => 'pinterest'])
        @include('partials.ads-tabulator-page', ['adsPart' => 'script', 'adsKey' => 'pinterest', 'dataUrl' => $dataUrl, 'channelKey' => $channelKey])

    adsKey is only used to build unique element ids / storage keys.
--}}
@if ($adsPart === 'css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        #ads-filter-bar { gap: 4px 6px !important; }
        #ads-filter-bar .form-select {
            width: auto !important;
            max-width: 120px;
            padding-right: 1.2rem !important;
            padding-left: 0.4rem !important;
            background-position: right 0.3rem center !important;
        }
        #ads-filter-bar #parent-search,
        #ads-filter-bar #sku-search { width: 140px !important; }
        #ads-filter-bar .btn-sm { padding: 0.2rem 0.4rem; font-size: 0.8rem; }

        /* Summary badges */
        #ads-summary-stats .badge {
            font-size: 0.85rem !important;
            padding: 0.45rem 0.6rem !important;
            font-weight: 700;
        }

        /* Uniform body cells + fixed row height */
        #ads-table .tabulator-row {
            height: 36px !important;
            max-height: 36px !important;
            min-height: 36px !important;
        }
        #ads-table .tabulator-row .tabulator-cell {
            font-size: 13px !important;
            line-height: 1.2 !important;
            height: 36px !important;
            max-height: 36px !important;
            padding-top: 2px !important;
            padding-bottom: 2px !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            vertical-align: middle !important;
        }
        #ads-table .tabulator-row .tabulator-cell img.hover-thumb {
            width: 28px !important;
            height: 28px !important;
            max-width: 28px !important;
            max-height: 28px !important;
            object-fit: cover !important;
            display: block !important;
            margin: 0 auto;
            border-radius: 4px;
            cursor: zoom-in;
        }

        /* Only the table body scrolls */
        #ads-table-wrapper { min-height: 0; min-width: 0; overflow: hidden; }
        #ads-table { height: 100% !important; min-height: 0 !important; overflow: hidden; }
        #ads-table .tabulator-tableholder {
            overflow: auto !important;
            overflow-anchor: none;
            scrollbar-gutter: stable;
        }

        /* Vertical column headers — same look as Amazon */
        #ads-table .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        #ads-table .tabulator-col .tabulator-col-sorter { display: none !important; }

        #ads-image-preview {
            position: fixed;
            z-index: 10050;
            display: none;
            padding: 4px;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.25);
            pointer-events: none;
        }
        #ads-image-preview img { display: block; max-width: 280px; max-height: 280px; object-fit: contain; }
    </style>
@elseif ($adsPart === 'content')
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-2 d-flex flex-column">

                <div class="d-flex align-items-center flex-wrap gap-2" id="ads-filter-bar">
                    <input type="text" id="parent-search" class="form-control form-control-sm" placeholder="Search Parent..." style="width: 140px; display: inline-block;">
                    <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU..." style="width: 140px; display: inline-block;">

                    <select id="inventory-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                        <option value="all">INV</option>
                        <option value="zero">Zero</option>
                        <option value="more" selected>More</option>
                    </select>

                    <select id="dil-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                        <option value="all">DIL%</option>
                        <option value="red">Red &lt;25%</option>
                        <option value="green">Green 25-50%</option>
                        <option value="pink">Pink 50%+</option>
                    </select>

                    <!-- Column header boxes (Basic / Price / Ads / Other + your own) -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            aria-expanded="false" title="Columns">
                            <i class="fas fa-columns"></i>
                        </button>
                        <ul class="dropdown-menu" id="column-dropdown-menu" aria-labelledby="columnVisibilityDropdown">
                            <!-- Populated dynamically: Basic / Price / Ads / Other -->
                        </ul>
                    </div>
                </div>

                <div id="ads-summary-stats" class="mt-2 d-flex flex-wrap gap-2">
                    <span class="badge bg-primary" id="ads-count-badge" title="SKUs in the current view">SKUs: 0</span>
                    <span class="badge bg-info text-dark" id="ads-inv-badge" title="Total INV in the current view">INV: 0</span>
                    <span class="badge bg-warning text-dark" id="ads-ovl30-badge" title="Total OV L30 in the current view">OV L30: 0</span>
                    <span class="badge bg-success" id="ads-dil-badge" title="OV L30 ÷ INV of the current view">Dil: 0%</span>
                </div>

                <div id="ads-table-wrapper" class="mt-2" style="height: calc(100vh - 260px); min-height: 360px;">
                    <div id="ads-table"></div>
                </div>
            </div>
        </div>
    </div>
    <div id="ads-image-preview"><img alt=""></div>
@elseif ($adsPart === 'script')
    <script>
        /** Saved in channel_tabulator_column_settings (shared across users, same as the other Tabulator pages). */
        const TABULATOR_COLUMN_CHANNEL = @json($channelKey);
        const TABULATOR_COLUMN_VISIBILITY_URL = '/tabulator-column-visibility';
        const TABULATOR_COLUMN_ORDER_URL = '/tabulator-column-order';
        const ADS_DATA_URL = @json($dataUrl);

        function adsEsc(s) {
            if (s == null) return '';
            return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
        function adsNum(v) {
            const n = parseFloat(v);
            return isFinite(n) ? n : 0;
        }
        function adsDil(row) {
            const inv = adsNum(row.inv);
            return inv > 0 ? (adsNum(row.ov_l30) / inv) * 100 : 0;
        }
        function adsMoney(cell) {
            const v = cell.getValue();
            if (v === null || v === undefined || v === '') return '<span style="color:#6c757d;">-</span>';
            return '$' + adsNum(v).toFixed(2);
        }

        // ---- image hover preview ----
        const adsPreviewEl = document.getElementById('ads-image-preview');
        function adsShowPreview(x, y, url) {
            if (!adsPreviewEl || !url) return;
            adsPreviewEl.querySelector('img').src = url;
            adsPreviewEl.style.display = 'block';
            adsMovePreview(x, y);
        }
        function adsMovePreview(x, y) {
            if (!adsPreviewEl) return;
            const w = adsPreviewEl.offsetWidth || 290;
            const h = adsPreviewEl.offsetHeight || 290;
            let left = x + 16;
            let top = y + 16;
            if (left + w > window.innerWidth - 8) left = x - w - 16;
            if (top + h > window.innerHeight - 8) top = Math.max(8, window.innerHeight - h - 8);
            adsPreviewEl.style.left = Math.max(8, left) + 'px';
            adsPreviewEl.style.top = Math.max(8, top) + 'px';
        }
        function adsHidePreview() {
            if (adsPreviewEl) adsPreviewEl.style.display = 'none';
        }

        let allTableData = [];

        let table = new Tabulator('#ads-table', {
            ajaxURL: ADS_DATA_URL,
            ajaxConfig: {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            },
            ajaxResponse: function(url, params, response) {
                const payload = (response && response.data) ? response.data : (Array.isArray(response) ? response : []);
                allTableData = payload;
                return payload;
            },
            layout: 'fitDataStretch',
            height: '100%',
            autoResize: false,
            movableColumns: true,
            rowHeight: 36,
            sortMode: 'local',
            headerSortElement: false,
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [25, 50, 100, 200, 500, 1000, true],
            initialSort: [{ column: 'parent', dir: 'asc' }],
            placeholder: 'No data',
            columns: [
                {
                    title: 'Parent',
                    field: 'parent',
                    sorter: 'alphanum',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    cssClass: 'text-primary',
                    tooltip: true,
                    width: 120,
                    formatter: function(cell) {
                        const s = String(cell.getValue() || '').trim();
                        return s ? adsEsc(s) : '—';
                    }
                },
                {
                    title: 'Image',
                    field: 'image_path',
                    hozAlign: 'center',
                    headerSort: false,
                    width: 70,
                    formatter: function(cell) {
                        const src = cell.getValue();
                        if (!src) return '';
                        const u = adsEsc(src);
                        return '<img src="' + u + '" data-full="' + u + '" class="hover-thumb no-img-hover" data-no-img-hover loading="lazy" />';
                    },
                    cellMouseOver: function(e, cell) {
                        const img = cell.getElement().querySelector('.hover-thumb');
                        if (img) adsShowPreview(e.clientX, e.clientY, img.getAttribute('data-full'));
                    },
                    cellMouseMove: function(e) { adsMovePreview(e.clientX, e.clientY); },
                    cellMouseOut: adsHidePreview
                },
                {
                    title: 'SKU',
                    field: 'sku',
                    sorter: 'alphanum',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    width: 200,
                    formatter: function(cell) {
                        const sku = cell.getValue() || '';
                        return '<div style="display:flex;align-items:center;gap:4px;flex-wrap:nowrap;min-width:0;max-width:100%;">'
                            + '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;" title="' + adsEsc(sku) + '">' + adsEsc(sku) + '</span>'
                            + '<button type="button" class="btn btn-sm btn-link copy-sku-btn p-0 flex-shrink-0" data-sku="' + adsEsc(sku) + '" title="Copy SKU"><i class="fas fa-copy"></i></button>'
                            + '</div>';
                    }
                },
                @if (!empty($adsRowActionColumnsView))
                    @include($adsRowActionColumnsView)
                @endif
                {
                    title: 'INV',
                    field: 'inv',
                    hozAlign: 'center',
                    width: 60,
                    sorter: 'number',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    formatter: function(cell) { return String(Math.round(adsNum(cell.getValue()))); }
                },
                {
                    title: 'OV L30',
                    field: 'ov_l30',
                    hozAlign: 'center',
                    width: 60,
                    sorter: 'number',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    formatter: function(cell) { return String(Math.round(adsNum(cell.getValue()))); }
                },
                {
                    title: 'Dil',
                    field: 'dil_percent',
                    hozAlign: 'center',
                    width: 60,
                    headerTooltip: 'OV L30 ÷ INV. Red <25% · Green 25–50% · Pink 50%+.',
                    sorter: function(a, b, aRow, bRow) { return adsDil(aRow.getData()) - adsDil(bRow.getData()); },
                    formatter: function(cell) {
                        const dil = adsDil(cell.getRow().getData());
                        if (!(dil > 0)) return '<span style="color:#6c757d;">0%</span>';
                        let color = '#a00211';
                        if (dil >= 50) color = '#e83e8c';
                        else if (dil >= 25) color = '#28a745';
                        return '<span style="color:' + color + ';font-weight:600;">' + Math.round(dil) + '%</span>';
                    }
                },
                {
                    title: 'Price',
                    field: 'price',
                    hozAlign: 'center',
                    width: 75,
                    sorter: 'number',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    formatter: adsMoney
                },
                {
                    title: 'LP',
                    field: 'lp',
                    hozAlign: 'center',
                    width: 65,
                    sorter: 'number',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    headerTooltip: 'Landed product cost (product master)',
                    formatter: adsMoney
                },
                {
                    title: 'Ship',
                    field: 'ship',
                    hozAlign: 'center',
                    width: 65,
                    sorter: 'number',
                    sorterParams: { alignEmptyValues: 'bottom' },
                    headerTooltip: 'Shipping cost (product master)',
                    formatter: adsMoney
                },
                @if (!empty($adsExtraColumnsView))
                    @include($adsExtraColumnsView)
                @endif
            ]
        });

        // ---- filters ----
        function adsApplyFilters() {
            const parentQ = (document.getElementById('parent-search').value || '').trim().toLowerCase();
            const skuQ = (document.getElementById('sku-search').value || '').trim().toLowerCase();
            const invF = document.getElementById('inventory-filter').value;
            const dilF = document.getElementById('dil-filter').value;

            table.setFilter(function(row) {
                if (parentQ && String(row.parent || '').toLowerCase().indexOf(parentQ) === -1) return false;
                if (skuQ && String(row.sku || '').toLowerCase().indexOf(skuQ) === -1) return false;
                const inv = adsNum(row.inv);
                if (invF === 'zero' && inv > 0) return false;
                if (invF === 'more' && !(inv > 0)) return false;
                if (dilF !== 'all') {
                    const dil = adsDil(row);
                    if (dilF === 'red' && !(dil < 25)) return false;
                    if (dilF === 'green' && !(dil >= 25 && dil < 50)) return false;
                    if (dilF === 'pink' && !(dil >= 50)) return false;
                }
                return true;
            });
        }
        ['parent-search', 'sku-search'].forEach(function(id) {
            document.getElementById(id).addEventListener('input', adsApplyFilters);
        });
        ['inventory-filter', 'dil-filter'].forEach(function(id) {
            document.getElementById(id).addEventListener('change', adsApplyFilters);
        });

        // ---- summary badges (current filtered view) ----
        function adsUpdateSummary() {
            const rows = table.getData('active') || [];
            let inv = 0;
            let ov = 0;
            rows.forEach(function(r) { inv += adsNum(r.inv); ov += adsNum(r.ov_l30); });
            const dil = inv > 0 ? Math.round((ov / inv) * 100) : 0;
            document.getElementById('ads-count-badge').textContent = 'SKUs: ' + rows.length.toLocaleString();
            document.getElementById('ads-inv-badge').textContent = 'INV: ' + Math.round(inv).toLocaleString();
            document.getElementById('ads-ovl30-badge').textContent = 'OV L30: ' + Math.round(ov).toLocaleString();
            document.getElementById('ads-dil-badge').textContent = 'Dil: ' + dil + '%';
        }
        table.on('dataFiltered', adsUpdateSummary);
        table.on('dataLoaded', adsUpdateSummary);

        // ---- copy SKU ----
        document.addEventListener('click', function(e) {
            const btn = e.target.closest ? e.target.closest('.copy-sku-btn') : null;
            if (!btn) return;
            e.preventDefault();
            const sku = btn.getAttribute('data-sku') || '';
            if (navigator.clipboard) navigator.clipboard.writeText(sku);
        });

        // ---- Columns panel: header boxes (Basic / Price / Ads / Other + your own), same as /amazon-tabulator-view ----
        function classifyAdsColumn(field, title) {
            const f = String(field || '');
            const t = String(title || '').toLowerCase();
            if (/^(parent|image_path|sku|inv|ov_l30|dil_percent|_preview)$/i.test(f)) return 'basic';
            if (/^(price|lp|ship)$/i.test(f) || /\b(price|prc|lp|ship|gpft|groi|nroi|pft)\b/i.test(t)) return 'price';
            if (/(spend|acos|roas|tacos|campaign|click|impr|ctr|cpc|cpm|budget|bgt|bid|ads?)/i.test(f)
                || /\b(ads?|acos|roas|spend|campaign|clicks?|impr|ctr|cpc|cpm|bgt|bid)\b/i.test(t)) return 'ads';
            return 'other';
        }

        const tcmConfig = {
            channel: TABULATOR_COLUMN_CHANNEL,
            visibilityUrl: TABULATOR_COLUMN_VISIBILITY_URL,
            orderUrl: TABULATOR_COLUMN_ORDER_URL,
            groups: [
                { key: 'basic', label: 'Basic' },
                { key: 'price', label: 'Price' },
                { key: 'ads', label: 'Ads' },
                { key: 'other', label: 'Other' }
            ],
            fallbackGroup: 'other',
            showAll: true
        };
        const tcmHooks = {
            classify: function(field, title) { return classifyAdsColumn(field, title); }
        };
        @include('partials.tabulator-column-manager')

        table.on('tableBuilt', function() {
            adsApplyFilters();
            Promise.resolve(applyColumnVisibilityFromServer()).finally(function() {
                buildColumnDropdown();
            });
        });

        @if (!empty($adsAfterScriptView))
            @include($adsAfterScriptView)
        @endif
    </script>
@endif
