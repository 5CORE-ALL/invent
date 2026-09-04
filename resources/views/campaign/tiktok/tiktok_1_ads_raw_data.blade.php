@extends('layouts.vertical', ['title' => 'Tiktok 1 Sheet Ads', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-paginator label { margin-right: 5px; }
        .tt1-badge-row {
            flex-wrap: wrap;
        }
        .tt1-stat-badge {
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
        .tt1-stat-badge--rows   { background: #0ea5e9; }
        .tt1-stat-badge--spend  { background: #ef4444; }
        .tt1-stat-badge--spend-l1 { background: #f87171; color: #111; }
        .tt1-stat-badge--clicks { background: #4c7ed8; }
        .tt1-stat-badge--clicks-l1 { background: #60a5fa; color: #111; }
        .tt1-stat-badge--sold   { background: #f59e0b; color: #111; }
        .tt1-stat-badge--sold-l1 { background: #fbbf24; color: #111; }
        .tt1-stat-badge--sales  { background: #16a34a; }
        .tt1-stat-badge--sales-l1 { background: #22c55e; color: #111; }
        .tt1-stat-badge--cvr    { background: #db2777; }
        .tt1-stat-badge--cvr-l1 { background: #ec4899; color: #111; }
        .tt1-range-btn {
            background: #0d9488;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            line-height: 1.2;
        }
        .tt1-range-btn:hover { filter: brightness(1.08); color: #fff; }
        .tt1-video-title-btn {
            color: #0d6efd;
            font-size: 1.1rem;
            line-height: 1;
        }
        .tt1-video-title-btn:hover { color: #0a58ca; }
        #tt1-video-title-modal-body {
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 70vh;
            overflow-y: auto;
        }
        #tt1-ads-column-dropdown-menu.show {
            min-width: min(92vw, 720px);
            max-width: min(96vw, 780px);
            max-height: 70vh;
            overflow-y: auto;
            padding: 0.4rem 0.55rem 0.6rem;
        }
        #tt1-ads-column-dropdown-menu > li.col-vis-full {
            list-style: none;
        }
        #tt1-ads-column-dropdown-menu .col-vis-selections-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            margin: 0 0 8px;
            padding: 2px 4px;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            user-select: none;
        }
        #tt1-ads-column-dropdown-menu .col-vis-selections-title input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            cursor: pointer;
        }
        #tt1-ads-column-dropdown-menu .col-vis-groups {
            display: grid;
            grid-template-columns: repeat(3, minmax(140px, 1fr));
            gap: 8px;
        }
        #tt1-ads-column-dropdown-menu .col-vis-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 6px 8px 8px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
        }
        #tt1-ads-column-dropdown-menu .col-vis-group.col-vis-drop-over {
            outline: 2px dashed #0d6efd;
        }
        #tt1-ads-column-dropdown-menu .col-vis-group-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #495057;
            margin: 0 0 6px;
            padding: 2px 4px;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            user-select: none;
        }
        #tt1-ads-column-dropdown-menu .col-vis-group-title input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            cursor: pointer;
        }
        #tt1-ads-column-dropdown-menu .col-vis-group-list {
            flex: 1;
            min-height: 60px;
            display: flex;
            flex-direction: column;
            gap: 1px;
        }
        #tt1-ads-column-dropdown-menu .col-vis-item {
            cursor: grab;
            border-radius: 4px;
        }
        #tt1-ads-column-dropdown-menu .col-vis-item.col-vis-dragging {
            opacity: 0.4;
            cursor: grabbing;
        }
        #tt1-ads-column-dropdown-menu .col-vis-item.col-vis-drop-before {
            box-shadow: inset 0 2px 0 #0d6efd;
        }
        #tt1-ads-column-dropdown-menu .col-vis-item.col-vis-drop-after {
            box-shadow: inset 0 -2px 0 #0d6efd;
        }
        #tt1-ads-column-dropdown-menu .col-vis-item > label {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 3px 5px;
            cursor: grab;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 0;
            font-size: 0.8rem;
            user-select: none;
        }
        #tt1-ads-column-dropdown-menu .col-vis-item > label input[type="checkbox"] {
            margin: 0;
            flex-shrink: 0;
            width: 14px;
            height: 14px;
        }
        #tt1-ads-column-dropdown-menu .col-vis-item > label:hover {
            background: rgba(0, 0, 0, 0.04);
            border-radius: 3px;
        }
        @media (max-width: 768px) {
            #tt1-ads-column-dropdown-menu .col-vis-groups {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Tiktok 1 Sheet Ads',
        'sub_title'  => 'Upload TikTok ads export into tiktok_campaign_reports. Old rows for the chosen range are truncated first.',
    ])

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2 tt1-badge-row">
                    <span class="tt1-stat-badge tt1-stat-badge--rows">ROWS: <span id="total-tt1-ads-raw">0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--spend">COST L30: <span id="tt1-cost-l30">$0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--spend-l1">COST L1: <span id="tt1-cost-l1">$0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--clicks">CLICKS L30: <span id="tt1-clicks-l30">0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--clicks-l1">CLICKS L1: <span id="tt1-clicks-l1">0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--sold">SOLD L30: <span id="tt1-orders-l30">0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--sold-l1">SOLD L1: <span id="tt1-orders-l1">0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--sales">ADS SALES L30: <span id="tt1-revenue-l30">$0</span></span>
                    <span class="tt1-stat-badge tt1-stat-badge--sales-l1">ADS SALES L1: <span id="tt1-revenue-l1">$0</span></span>
                    <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="tt1-open-upload">
                        <i class="fa-solid fa-upload me-1"></i>Upload
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="tt1-ads-raw-csv">
                        <i class="fas fa-download me-1"></i>CSV
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2 tt1-badge-row mt-2">
                    <span class="tt1-stat-badge tt1-stat-badge--cvr" title="CVR = Sold ÷ Clicks × 100">
                        CVR L30: <span id="tt1-cvr-l30">0%</span>
                        <span id="tt1-cvr-l30-calc">(0 ÷ 0)</span>
                    </span>
                    <span class="tt1-stat-badge tt1-stat-badge--cvr-l1" title="CVR = Sold ÷ Clicks × 100">
                        CVR L1: <span id="tt1-cvr-l1">0%</span>
                        <span id="tt1-cvr-l1-calc">(0 ÷ 0)</span>
                    </span>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="p-2 bg-light border-bottom d-flex align-items-center gap-2">
                    <input type="text" id="tt1-ads-raw-search" class="form-control form-control-sm"
                           placeholder="Search campaign, product, video, status…">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="tt1AdsColumnVisibilityDropdown" data-bs-toggle="dropdown"
                            data-bs-auto-close="outside" aria-expanded="false"
                            title="Show / hide table columns (saved like other channels)">
                            <i class="fas fa-table-columns"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="tt1AdsColumnVisibilityDropdown"
                            id="tt1-ads-column-dropdown-menu"></ul>
                    </div>
                </div>
                <div id="tt1-ads-raw-table" style="height: calc(100vh - 320px);"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tt1-upload-modal" tabindex="-1" aria-labelledby="tt1-upload-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-bold" id="tt1-upload-modal-label">
                        <i class="fa-solid fa-upload me-1"></i>Upload sheet
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="text-muted" style="font-size: 0.85rem;">
                            <i class="fa-solid fa-upload me-1"></i>Upload (xlsx / csv / txt). Old range is deleted first.
                        </span>
                        <input type="file" id="tt1-l1-file" accept=".xlsx,.xls,.csv,.tsv,.txt" class="d-none">
                        <input type="file" id="tt1-l7-file" accept=".xlsx,.xls,.csv,.tsv,.txt" class="d-none">
                        <input type="file" id="tt1-l30-file" accept=".xlsx,.xls,.csv,.tsv,.txt" class="d-none">
                        <button type="button" id="tt1-l1-upload" class="tt1-range-btn">L1</button>
                        <button type="button" id="tt1-l7-upload" class="tt1-range-btn">L7</button>
                        <button type="button" id="tt1-l30-upload" class="tt1-range-btn">L30</button>
                    </div>
                    <div id="tt1-upload-status" class="mt-2" style="font-size: 0.85rem;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tt1-video-title-modal" tabindex="-1" aria-labelledby="tt1-video-title-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tt1-video-title-modal-label">Video title</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="tt1-video-title-modal-body"></div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    function money(n) {
        return '$' + Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function applyTt1Sums(s) {
        const sums = s || {};
        const clicksL30 = Number(sums.clicks_l30 || 0);
        const clicksL1 = Number(sums.clicks_l1 || 0);
        const ordersL30 = Number(sums.orders_l30 || 0);
        const ordersL1 = Number(sums.orders_l1 || 0);
        const cvrL30 = clicksL30 > 0 ? (ordersL30 / clicksL30) * 100 : 0;
        const cvrL1 = clicksL1 > 0 ? (ordersL1 / clicksL1) * 100 : 0;

        $('#total-tt1-ads-raw').text(Number(sums.count || 0).toLocaleString('en-US'));
        $('#tt1-cost-l30').text(money(sums.cost_l30));
        $('#tt1-cost-l1').text(money(sums.cost_l1));
        $('#tt1-clicks-l30').text(clicksL30.toLocaleString('en-US'));
        $('#tt1-clicks-l1').text(clicksL1.toLocaleString('en-US'));
        $('#tt1-orders-l30').text(ordersL30.toLocaleString('en-US'));
        $('#tt1-orders-l1').text(ordersL1.toLocaleString('en-US'));
        $('#tt1-revenue-l30').text(money(sums.revenue_l30));
        $('#tt1-revenue-l1').text(money(sums.revenue_l1));
        $('#tt1-cvr-l30').text(cvrL30.toFixed(1) + '%');
        $('#tt1-cvr-l1').text(cvrL1.toFixed(1) + '%');
        $('#tt1-cvr-l30-calc').text('(' + ordersL30.toLocaleString('en-US') + ' ÷ ' + clicksL30.toLocaleString('en-US') + ')');
        $('#tt1-cvr-l1-calc').text('(' + ordersL1.toLocaleString('en-US') + ' ÷ ' + clicksL1.toLocaleString('en-US') + ')');
    }

    function numCol(title, field, width) {
        return {
            title: title,
            field: field,
            width: width || 110,
            hozAlign: 'right',
            sorter: 'number',
            headerSort: true,
            formatter: function(cell) {
                const v = cell.getValue();
                if (v === null || v === undefined || v === '') return '';
                const n = Number(v);
                return Number.isFinite(n) ? n.toLocaleString('en-US', { maximumFractionDigits: 4 }) : String(v);
            },
        };
    }

    $(document).ready(function() {
        const table = new Tabulator('#tt1-ads-raw-table', {
            ajaxURL: "{{ route('tiktok1.ads.raw.data') }}",
            ajaxResponse: function(_url, _params, response) {
                const data = (response && response.data) ? response.data : [];
                applyTt1Sums(response && response.sums ? response.sums : { count: data.length });
                return data;
            },
            layout: 'fitData',
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            initialSort: [{ column: 'cost', dir: 'desc' }],
            placeholder: 'No rows in tiktok_campaign_reports. Upload an L1 / L7 / L30 export.',
            columns: [
                { title: 'ID', field: 'id', width: 80, hozAlign: 'right', sorter: 'number', visible: false },
                { title: 'SKU', field: 'sku', minWidth: 160 },
                { title: 'Campaign name', field: 'campaign_name', minWidth: 180 },
                { title: 'Report range', field: 'report_range', width: 120, hozAlign: 'center' },
                { title: 'Creative type', field: 'creative_type', minWidth: 130 },
                {
                    title: 'Video title',
                    field: 'video_title',
                    width: 110,
                    hozAlign: 'center',
                    headerSort: false,
                    formatter: function(cell) {
                        const v = String(cell.getValue() == null ? '' : cell.getValue()).trim();
                        if (!v || v === '-' || v.toUpperCase() === 'N/A') {
                            return '';
                        }
                        return '<button type="button" class="btn btn-sm btn-link p-0 tt1-video-title-btn" title="View video title"><i class="fas fa-align-left"></i></button>';
                    },
                    cellClick: function(_e, cell) {
                        const v = String(cell.getValue() == null ? '' : cell.getValue()).trim();
                        if (!v || v === '-' || v.toUpperCase() === 'N/A') {
                            return;
                        }
                        const row = cell.getRow().getData() || {};
                        $('#tt1-video-title-modal-label').text((row.campaign_name || 'Video title') + (row.sku ? ' · ' + row.sku : ''));
                        $('#tt1-video-title-modal-body').text(v);
                        const el = document.getElementById('tt1-video-title-modal');
                        if (window.bootstrap && bootstrap.Modal) {
                            bootstrap.Modal.getOrCreateInstance(el).show();
                        } else {
                            $(el).modal('show');
                        }
                    },
                },
                { title: 'Status', field: 'status', width: 110 },
                { title: 'Exploration status', field: 'custom_status', width: 150 },
                numCol('Cost', 'cost', 100),
                numCol('Budget', 'budget', 100),
                numCol('SKU orders', 'sku_orders', 110),
                numCol('Cost per order', 'cost_per_order', 130),
                numCol('Gross revenue', 'gross_revenue', 130),
                numCol('ROI', 'roi', 90),
                numCol('In ROAS', 'in_roas', 100),
                numCol('Impressions', 'product_ad_impressions', 120),
                numCol('Clicks', 'product_ad_clicks', 100),
                numCol('Click rate', 'product_ad_click_rate', 110),
                numCol('Ad CVR', 'ad_conversion_rate', 100),
                numCol('2s view %', 'video_view_rate_2_second', 100),
                numCol('6s view %', 'video_view_rate_6_second', 100),
                numCol('25% view', 'video_view_rate_25_percent', 100),
                numCol('50% view', 'video_view_rate_50_percent', 100),
                numCol('75% view', 'video_view_rate_75_percent', 100),
                numCol('100% view', 'video_view_rate_100_percent', 110),
                { title: 'Created', field: 'created_at', minWidth: 160 },
                { title: 'Updated', field: 'updated_at', minWidth: 160 },
                { title: 'Campaign ID', field: 'campaign_id', minWidth: 140 },
                { title: 'Product ID', field: 'product_id', minWidth: 140 },
            ],
        });

        const TABULATOR_COLUMN_CHANNEL = 'tiktok1_ads_raw';
        const TABULATOR_COLUMN_VISIBILITY_URL = @json(url('/tabulator-column-visibility'));
        const TABULATOR_COLUMN_ORDER_URL = @json(url('/tabulator-column-order'));
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        const csrfHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken ? csrfToken.getAttribute('content') : '',
        };
        let savedColumnVisibilityMap = {};
        let applyingColumnOrder = false;
        const COL_VIS_CATEGORY_KEYS = ['basic', 'revenue', 'others'];
        const COL_VIS_CATEGORY_LABELS = {
            basic: 'Basic',
            revenue: 'Revenue',
            others: 'Others',
        };
        const COL_VIS_CAT_STORAGE = 'tiktok1_ads_raw_col_vis_cats';
        const DEFAULT_HIDDEN_FIELDS = { id: true };

        function columnTitle(col) {
            const def = col.getDefinition ? col.getDefinition() : {};
            const raw = def.title || def.field || '';
            return String(raw).replace(/<[^>]*>/g, '').trim() || String(def.field || '');
        }

        function classifyTt1Column(field) {
            const f = String(field || '');
            if (/^(id|sku|campaign_name|campaign_id|product_id|report_range|creative_type|video_title|status|custom_status)$/.test(f)) {
                return 'basic';
            }
            if (/^(cost|budget|sku_orders|cost_per_order|gross_revenue|roi|in_roas)$/.test(f)) {
                return 'revenue';
            }
            return 'others';
        }

        function loadCategoryOverrides() {
            try {
                const parsed = JSON.parse(localStorage.getItem(COL_VIS_CAT_STORAGE) || '{}');
                return parsed && typeof parsed === 'object' ? parsed : {};
            } catch (e) {
                return {};
            }
        }

        function saveCategoryOverrides(map) {
            try { localStorage.setItem(COL_VIS_CAT_STORAGE, JSON.stringify(map || {})); } catch (e) { /* ignore */ }
        }

        function resolveCategory(field) {
            const over = loadCategoryOverrides();
            if (over[field] && COL_VIS_CATEGORY_KEYS.indexOf(over[field]) !== -1) return over[field];
            return classifyTt1Column(field);
        }

        function syncGroupHeaderCheckbox(groupEl) {
            if (!groupEl) return;
            const headerCb = groupEl.querySelector('.col-vis-group-toggle');
            const itemCbs = groupEl.querySelectorAll('.col-vis-field-toggle');
            if (!headerCb || !itemCbs.length) {
                if (headerCb) {
                    headerCb.checked = false;
                    headerCb.indeterminate = false;
                }
                return;
            }
            let checked = 0;
            itemCbs.forEach(function (cb) { if (cb.checked) checked++; });
            headerCb.checked = checked === itemCbs.length;
            headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
        }

        function syncSelectionHeaderCheckbox() {
            const menu = document.getElementById('tt1-ads-column-dropdown-menu');
            if (!menu) return;
            menu.querySelectorAll('.col-vis-group').forEach(syncGroupHeaderCheckbox);
            const headerCb = menu.querySelector('.col-vis-selections-toggle');
            const itemCbs = menu.querySelectorAll('.col-vis-field-toggle');
            if (!headerCb || !itemCbs.length) return;
            let checked = 0;
            itemCbs.forEach(function (cb) { if (cb.checked) checked++; });
            headerCb.checked = checked === itemCbs.length;
            headerCb.indeterminate = checked > 0 && checked < itemCbs.length;
        }

        function saveColumnVisibilityToServer() {
            const visibility = {};
            const boxChecks = document.querySelectorAll('#tt1-ads-column-dropdown-menu .col-vis-field-toggle');
            if (boxChecks.length) {
                boxChecks.forEach(function (cb) {
                    if (cb.value) visibility[cb.value] = !!cb.checked;
                });
            } else {
                table.getColumns().forEach(function (col) {
                    const field = col.getField && col.getField();
                    if (field) visibility[field] = col.isVisible();
                });
            }
            savedColumnVisibilityMap = visibility;
            fetch(TABULATOR_COLUMN_VISIBILITY_URL, {
                method: 'POST',
                headers: csrfHeaders,
                body: JSON.stringify({
                    channel: TABULATOR_COLUMN_CHANNEL,
                    visibility: visibility,
                }),
            }).catch(function (err) { console.error('Error saving column visibility:', err); });
        }

        function applyColumnVisibility(map) {
            if (!map || typeof map !== 'object') return;
            savedColumnVisibilityMap = map;
            table.getColumns().forEach(function (col) {
                const field = col.getField && col.getField();
                if (!field) return;
                if (DEFAULT_HIDDEN_FIELDS[field] && map[field] !== true) {
                    col.hide();
                    return;
                }
                if (!map.hasOwnProperty(field)) return;
                if (map[field]) col.show();
                else col.hide();
            });
        }

        function currentColumnOrder() {
            return table.getColumns()
                .map(function (col) { return col.getField && col.getField(); })
                .filter(Boolean);
        }

        function applyColumnOrder(order) {
            if (!table || !Array.isArray(order) || !order.length) return;
            const existing = currentColumnOrder();
            if (!existing.length) return;
            const valid = [];
            const seen = {};
            order.forEach(function (f) {
                if (!f || seen[f] || existing.indexOf(f) === -1) return;
                seen[f] = true;
                valid.push(f);
            });
            existing.forEach(function (f) {
                if (!seen[f]) valid.push(f);
            });
            ['campaign_id', 'product_id'].forEach(function (field) {
                const i = valid.indexOf(field);
                if (i === -1) return;
                valid.splice(i, 1);
                valid.push(field);
            });
            applyingColumnOrder = true;
            try {
                for (let i = 0; i < valid.length; i++) {
                    const field = valid[i];
                    const cols = table.getColumns().filter(function (c) { return !!c.getField(); });
                    const currentIdx = cols.findIndex(function (c) { return c.getField() === field; });
                    if (currentIdx === i || currentIdx < 0) continue;
                    if (i === 0) {
                        const firstField = cols[0].getField();
                        if (firstField && firstField !== field) {
                            table.moveColumn(field, firstField, false);
                        }
                    } else if (valid[i - 1]) {
                        table.moveColumn(field, valid[i - 1], true);
                    }
                }
            } catch (err) {
                console.error('Error applying column order:', err);
            } finally {
                applyingColumnOrder = false;
            }
        }

        function saveColumnOrderToServer() {
            if (applyingColumnOrder) return;
            const order = currentColumnOrder();
            if (!order.length) return;
            fetch(TABULATOR_COLUMN_ORDER_URL, {
                method: 'POST',
                headers: csrfHeaders,
                body: JSON.stringify({
                    channel: TABULATOR_COLUMN_CHANNEL,
                    order: order,
                }),
            }).catch(function (err) { console.error('Error saving column order:', err); });
        }

        function orderFromBox() {
            return Array.from(document.querySelectorAll('#tt1-ads-column-dropdown-menu .col-vis-item'))
                .map(function (el) { return el.dataset.field; })
                .filter(Boolean);
        }

        function persistBoxOrderAndCategories() {
            const overrides = loadCategoryOverrides();
            document.querySelectorAll('#tt1-ads-column-dropdown-menu .col-vis-group').forEach(function (group) {
                const cat = group.dataset.category;
                group.querySelectorAll('.col-vis-item').forEach(function (item) {
                    if (item.dataset.field && cat) overrides[item.dataset.field] = cat;
                });
            });
            saveCategoryOverrides(overrides);
            applyColumnOrder(orderFromBox());
            saveColumnOrderToServer();
            syncSelectionHeaderCheckbox();
        }

        function bindColumnBoxDrag(root) {
            if (!root) return;
            let dragEl = null;

            function clearDropMarks() {
                root.querySelectorAll('.col-vis-drop-before, .col-vis-drop-after, .col-vis-drop-over').forEach(function (el) {
                    el.classList.remove('col-vis-drop-before', 'col-vis-drop-after', 'col-vis-drop-over');
                });
            }

            root.querySelectorAll('.col-vis-item').forEach(function (item) {
                item.draggable = true;
                const checkbox = item.querySelector('.col-vis-field-toggle');
                if (checkbox) {
                    item.dataset.field = checkbox.value;
                    checkbox.addEventListener('mousedown', function (e) { e.stopPropagation(); });
                }
                item.addEventListener('dragstart', function (e) {
                    if (e.target && e.target.closest && e.target.closest('input')) {
                        e.preventDefault();
                        return;
                    }
                    dragEl = item;
                    item.classList.add('col-vis-dragging');
                    e.dataTransfer.setData('text/plain', item.dataset.field || '');
                    e.dataTransfer.effectAllowed = 'move';
                });
                item.addEventListener('dragend', function () {
                    item.classList.remove('col-vis-dragging');
                    clearDropMarks();
                    dragEl = null;
                });
                item.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (!dragEl || dragEl === item) return;
                    const rect = item.getBoundingClientRect();
                    const before = e.clientY < rect.top + rect.height / 2;
                    item.classList.toggle('col-vis-drop-before', before);
                    item.classList.toggle('col-vis-drop-after', !before);
                    e.dataTransfer.dropEffect = 'move';
                });
                item.addEventListener('dragleave', function () {
                    item.classList.remove('col-vis-drop-before', 'col-vis-drop-after');
                });
                item.addEventListener('drop', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const before = item.classList.contains('col-vis-drop-before');
                    clearDropMarks();
                    if (!dragEl || dragEl === item) return;
                    const list = item.parentNode;
                    if (before) list.insertBefore(dragEl, item);
                    else list.insertBefore(dragEl, item.nextSibling);
                    persistBoxOrderAndCategories();
                });
            });

            root.querySelectorAll('.col-vis-group-list').forEach(function (list) {
                const group = list.closest('.col-vis-group');
                list.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (group) group.classList.add('col-vis-drop-over');
                    e.dataTransfer.dropEffect = 'move';
                });
                list.addEventListener('dragleave', function (e) {
                    if (group && !group.contains(e.relatedTarget)) {
                        group.classList.remove('col-vis-drop-over');
                    }
                });
                list.addEventListener('drop', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (group) group.classList.remove('col-vis-drop-over');
                    if (!dragEl) return;
                    if (e.target.closest('.col-vis-item')) return;
                    list.appendChild(dragEl);
                    persistBoxOrderAndCategories();
                });
            });
        }

        function buildColumnDropdown(map) {
            const menu = document.getElementById('tt1-ads-column-dropdown-menu');
            if (!menu) return;
            const vis = (map && typeof map === 'object') ? map : savedColumnVisibilityMap;
            menu.innerHTML = '';

            const showAllLi = document.createElement('li');
            showAllLi.className = 'col-vis-full';
            showAllLi.innerHTML = '<a class="dropdown-item py-1" href="#" id="tt1-ads-show-all-columns-btn"><i class="fa fa-eye"></i> Show All</a>';
            menu.appendChild(showAllLi);

            const boxLi = document.createElement('li');
            boxLi.className = 'col-vis-full';
            const wrap = document.createElement('div');
            wrap.className = 'col-vis-selections';

            const selTitle = document.createElement('label');
            selTitle.className = 'col-vis-selections-title';
            const selCb = document.createElement('input');
            selCb.type = 'checkbox';
            selCb.className = 'col-vis-selections-toggle';
            selCb.title = 'Select / deselect all columns';
            selTitle.appendChild(selCb);
            selTitle.appendChild(document.createTextNode('Selections'));
            wrap.appendChild(selTitle);

            const groupsWrap = document.createElement('div');
            groupsWrap.className = 'col-vis-groups';
            const lists = {};
            COL_VIS_CATEGORY_KEYS.forEach(function (cat) {
                const group = document.createElement('div');
                group.className = 'col-vis-group';
                group.dataset.category = cat;
                const titleEl = document.createElement('label');
                titleEl.className = 'col-vis-group-title';
                const groupCb = document.createElement('input');
                groupCb.type = 'checkbox';
                groupCb.className = 'col-vis-group-toggle';
                groupCb.dataset.group = cat;
                groupCb.title = 'Select / deselect all in ' + COL_VIS_CATEGORY_LABELS[cat];
                titleEl.appendChild(groupCb);
                titleEl.appendChild(document.createTextNode(COL_VIS_CATEGORY_LABELS[cat]));
                group.appendChild(titleEl);
                const list = document.createElement('div');
                list.className = 'col-vis-group-list';
                group.appendChild(list);
                groupsWrap.appendChild(group);
                lists[cat] = list;
            });

            table.getColumns().forEach(function (col) {
                const field = col.getField && col.getField();
                if (!field) return;
                const title = columnTitle(col);
                const cat = resolveCategory(field);
                const item = document.createElement('div');
                item.className = 'col-vis-item';
                item.dataset.field = field;
                item.dataset.group = cat;
                const label = document.createElement('label');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.value = field;
                checkbox.className = 'col-vis-field-toggle';
                checkbox.dataset.group = cat;
                checkbox.checked = vis.hasOwnProperty(field) ? (vis[field] !== false) : col.isVisible();
                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(title));
                label.title = title + ' — drag to reorder';
                item.appendChild(label);
                lists[cat].appendChild(item);
            });

            wrap.appendChild(groupsWrap);
            boxLi.appendChild(wrap);
            menu.appendChild(boxLi);
            syncSelectionHeaderCheckbox();
            bindColumnBoxDrag(wrap);
        }

        function loadAndApplyColumnVisibility() {
            const visReq = fetch(TABULATOR_COLUMN_VISIBILITY_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                method: 'GET',
                headers: csrfHeaders,
            }).then(function (r) { return r.json(); });
            const orderReq = fetch(TABULATOR_COLUMN_ORDER_URL + '?channel=' + encodeURIComponent(TABULATOR_COLUMN_CHANNEL), {
                method: 'GET',
                headers: csrfHeaders,
            }).then(function (r) { return r.json(); }).catch(function () { return {}; });

            Promise.all([visReq, orderReq])
                .then(function (results) {
                    const saved = results[0];
                    const orderResp = results[1];
                    const map = (saved && typeof saved === 'object') ? saved : {};
                    const persistHiddenId = map.id === true;
                    if (persistHiddenId) map.id = false;
                    applyColumnVisibility(map);
                    if (orderResp && orderResp.success && Array.isArray(orderResp.order)) {
                        applyColumnOrder(orderResp.order);
                    }
                    buildColumnDropdown(map);
                    if (persistHiddenId) saveColumnVisibilityToServer();
                })
                .catch(function (err) {
                    console.error('Error loading column visibility:', err);
                    buildColumnDropdown({});
                });
        }

        table.on('tableBuilt', loadAndApplyColumnVisibility);

        const colMenu = document.getElementById('tt1-ads-column-dropdown-menu');
        if (colMenu) {
            colMenu.addEventListener('change', function (e) {
                if (e.target.type !== 'checkbox') return;
                if (e.target.classList.contains('col-vis-selections-toggle')) {
                    const checked = e.target.checked;
                    colMenu.querySelectorAll('.col-vis-field-toggle').forEach(function (cb) {
                        if (DEFAULT_HIDDEN_FIELDS[cb.value] && checked) return;
                        cb.checked = checked;
                        const col = table.getColumn(cb.value);
                        if (!col) return;
                        if (checked) col.show();
                        else col.hide();
                    });
                    e.target.indeterminate = false;
                    syncSelectionHeaderCheckbox();
                    saveColumnVisibilityToServer();
                    return;
                }
                if (e.target.classList.contains('col-vis-group-toggle')) {
                    const checked = e.target.checked;
                    const groupEl = e.target.closest('.col-vis-group');
                    const itemCbs = groupEl
                        ? groupEl.querySelectorAll('.col-vis-field-toggle')
                        : colMenu.querySelectorAll('.col-vis-field-toggle[data-group="' + e.target.dataset.group + '"]');
                    itemCbs.forEach(function (cb) {
                        if (DEFAULT_HIDDEN_FIELDS[cb.value] && checked) return;
                        cb.checked = checked;
                        const col = table.getColumn(cb.value);
                        if (!col) return;
                        if (checked) col.show();
                        else col.hide();
                    });
                    e.target.indeterminate = false;
                    syncSelectionHeaderCheckbox();
                    saveColumnVisibilityToServer();
                    return;
                }
                const col = table.getColumn(e.target.value);
                if (col) {
                    if (e.target.checked) col.show();
                    else col.hide();
                }
                syncSelectionHeaderCheckbox();
                saveColumnVisibilityToServer();
            });
            colMenu.addEventListener('click', function (e) {
                const showAll = e.target.closest('#tt1-ads-show-all-columns-btn');
                if (!showAll) return;
                e.preventDefault();
                e.stopPropagation();
                table.getColumns().forEach(function (col) {
                    const field = col.getField && col.getField();
                    if (DEFAULT_HIDDEN_FIELDS[field]) {
                        col.hide();
                        return;
                    }
                    col.show();
                });
                buildColumnDropdown({});
                saveColumnVisibilityToServer();
            });
        }

        $('#tt1-ads-raw-search').on('input', function() {
            const q = $(this).val().trim().toLowerCase();
            if (!q) {
                table.clearFilter(true);
                return;
            }
            table.setFilter(function(row) {
                return Object.keys(row).some(function(key) {
                    return String(row[key] == null ? '' : row[key]).toLowerCase().includes(q);
                });
            });
        });

        $('#tt1-ads-raw-csv').on('click', function() {
            table.download('csv', 'tt1_ads.csv');
        });

        function showTt1UploadModal() {
            const el = document.getElementById('tt1-upload-modal');
            if (!el) return;
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).show();
            } else {
                $(el).modal('show');
            }
        }
        $('#tt1-open-upload').on('click', showTt1UploadModal);

        function uploadTt1(fileInput, reportRange) {
            const file = fileInput.files[0];
            if (!file) return;
            const $status = $('#tt1-upload-status');
            $status.html('<span class="text-primary">Uploading ' + reportRange + '… old rows will be truncated.</span>');
            const formData = new FormData();
            formData.append('file', file);
            formData.append('report_range', reportRange);
            $.ajax({
                url: "{{ route('tiktok.utilized.upload') }}",
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    if (response && response.success) {
                        $status.html('<span class="text-success">' + (response.message || 'Done') + '</span>');
                        table.replaceData();
                    } else {
                        $status.html('<span class="text-danger">' + ((response && response.message) || 'Upload failed') + '</span>');
                    }
                    fileInput.value = '';
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Upload failed';
                    $status.html('<span class="text-danger">' + msg + '</span>');
                    fileInput.value = '';
                }
            });
        }

        $('#tt1-l1-upload').on('click', function() {
            $('#tt1-l1-file').off('change').on('change', function() { uploadTt1(this, 'L1'); }).trigger('click');
        });
        $('#tt1-l7-upload').on('click', function() {
            $('#tt1-l7-file').off('change').on('change', function() { uploadTt1(this, 'L7'); }).trigger('click');
        });
        $('#tt1-l30-upload').on('click', function() {
            $('#tt1-l30-file').off('change').on('change', function() { uploadTt1(this, 'L30'); }).trigger('click');
        });
    });
</script>
@endsection
