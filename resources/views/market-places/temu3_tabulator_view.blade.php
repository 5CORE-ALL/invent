@extends('layouts.vertical', ['title' => 'Temu 3 Sales Data', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        
        /* Vertical column headers */
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
        
        .tabulator .tabulator-header .tabulator-col {
            height: 80px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        .tabulator .tabulator-cell {
            text-align: center !important;
            padding: 2px 4px !important;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #temu3-table,
        #temu3-table .tabulator {
            width: 100%;
            max-width: 100%;
        }

        /* Custom pagination label */
        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* Link tooltip styling */
        .link-tooltip {
            position: absolute;
            background-color: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .link-tooltip a {
            text-decoration: none;
        }

        .link-tooltip a:hover {
            text-decoration: underline;
        }

        .temu-order-id-hit {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 22px;
            cursor: default;
        }
        .temu-order-id-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #22c55e;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.28);
        }
        .temu-order-id-tip {
            display: none;
            position: fixed;
            z-index: 10050;
            background: #fff;
            color: #111827;
            border: 1px solid #22c55e;
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.3;
            white-space: nowrap;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.16);
            pointer-events: none;
        }
        #temu3-table .tabulator-cell { padding: 4px 6px; white-space: nowrap; }
        #temu3-table .tabulator-header-filter input { min-width: 0; }
        #summary-stats {
            max-width: 100%;
            min-width: 0;
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(0, 1.35fr) minmax(0, 1.2fr) minmax(0, 0.75fr);
            gap: 0.5rem;
            background: transparent !important;
            padding: 0 !important;
        }
        #summary-stats .amm-badge-block {
            min-width: 0;
            border-radius: 10px;
            padding: 0.4rem 0.5rem 0.5rem;
            border: 1px solid transparent;
        }
        #summary-stats .amm-badge-block-title {
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            line-height: 1;
            margin: 0 0 0.35rem;
        }
        #summary-stats .amm-badge-block--l30 { background: #dbeafe; border-color: #93c5fd; }
        #summary-stats .amm-badge-block--l30 .amm-badge-block-title { color: #1e40af; }
        #summary-stats .amm-badge-block--yesterday { background: #ccfbf1; border-color: #5eead4; }
        #summary-stats .amm-badge-block--yesterday .amm-badge-block-title { color: #115e59; }
        #summary-stats .amm-badge-block--projected { background: #ede9fe; border-color: #c4b5fd; }
        #summary-stats .amm-badge-block--projected .amm-badge-block-title { color: #5b21b6; }
        #summary-stats .amm-badge-block--others { background: #ffedd5; border-color: #fdba74; }
        #summary-stats .amm-badge-block--others .amm-badge-block-title { color: #9a3412; }
        #summary-stats .ebay2-summary-badge-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.4rem;
            width: 100%;
        }
        #summary-stats .ebay2-summary-badge-row > .badge {
            flex: 0 0 auto;
            font-size: 0.8125rem;
            padding: 0.4rem 0.55rem;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
        }
        @media (max-width: 1199.98px) {
            #summary-stats { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 767.98px) {
            #summary-stats { grid-template-columns: 1fr; }
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Temu 3 Sales Data',
        'sub_title' => 'Same temu3_orders rows as /temu3-decrease',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Temu 3 Sales Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-success dropdown-toggle" type="button"
                            id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-file-excel"></i> Export
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                            <li>
                                <a class="dropdown-item export-l30" href="#" data-action="l30">
                                    <i class="fa fa-download me-1"></i> Export L30 Data
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item export-l7" href="#" data-action="l7">
                                    <i class="fa fa-download me-1"></i> Export L7 Data
                                </a>
                            </li>
                        </ul>
                    </div>
                    <span id="export-loading" class="ms-2" style="display: none;">
                        <span class="spinner-border spinner-border-sm text-success" role="status"></span>
                        <span class="ms-1">Loading L7 data...</span>
                    </span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadOrdersModal" title="Upload Temu Seller Center order export (replaces temu3_orders)">
                        <i class="fa fa-upload"></i> Upload Sales
                    </button>
                    <a href="{{ route('temu3.decrease') }}" class="btn btn-sm btn-outline-primary" title="View Temu 3 pricing (DIL%, CVR, orders from temu3_orders)">
                        <i class="fa fa-chart-line"></i> Temu Analytics
                    </a>
                </div>

                <div id="summary-stats" class="mt-2">
                    <div class="amm-badge-block amm-badge-block--l30">
                        <div class="amm-badge-block-title">L30</div>
                        <div class="ebay2-summary-badge-row">
                            <span class="badge bg-primary fs-6 p-2" id="total-orders-badge" style="color: white; font-weight: bold;">Orders: 0</span>
                            <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: white; font-weight: bold;">Quantity: 0</span>
                            <span class="badge fs-6 p-2" id="api-line-sales-badge" style="background-color: #0f766e; color: white; font-weight: bold;"
                                title="Σ Line Sales (goods base × qty) for the sheet window.">API Line Sales: $0</span>
                            <span class="badge bg-info fs-6 p-2" id="temu-full-price-sales-badge" style="color: white; font-weight: bold;"
                                title="Temu Full Price Sales = Σ Line Sales × 1.1364">Temu Full Price Sales: $0</span>
                            <span class="badge bg-secondary fs-6 p-2" id="l30-sales-badge" style="color: white; font-weight: bold;"
                                title="L30 Sales = Σ Temu Price × Qty">L30 Sales: $0</span>
                            <span class="badge bg-danger fs-6 p-2" id="pft-percentage-badge" style="color: white; font-weight: bold;"
                                title="GPFT % = GPFT$ badge ÷ Temu Full Price Sales × 100">GPFT: 0%</span>
                            <span class="badge fs-6 p-2" id="roi-percentage-badge" style="background-color: purple; color: white; font-weight: bold;"
                                title="GROI % = GPFT$ ÷ Total COGS × 100">GROI: 0%</span>
                            <span class="badge bg-dark fs-6 p-2" id="pft-total-badge" style="color: white; font-weight: bold;"
                                title="GPFT$ = Σ (Line Sales × Temu margin) − COGS − COGS Ship">GPFT$: $0</span>
                        </div>
                    </div>
                    <div class="amm-badge-block amm-badge-block--yesterday">
                        <div class="amm-badge-block-title">Yesterday</div>
                        <div class="ebay2-summary-badge-row">
                            <span class="badge fs-6 p-2" id="y-sales-badge" style="background-color: #6f42c1; color: white; font-weight: bold;"
                                title="Y Line Sales = Σ goods-base line sales for Pacific yesterday{{ !empty($temu3YDate) ? ' (' . $temu3YDate . ')' : '' }}.">Y Line Sales: ${{ number_format((float) ($temu3YSales ?? 0), 0) }}</span>
                            <span class="badge fs-6 p-2" id="l30-full-sales-badge" style="background-color: #0e7490; color: white; font-weight: bold;"
                                title="L30 Full Sales = Y Line Sales badge × 1.1364">L30 Full Sales: ${{ number_format((float) ($temu3L30FullSales ?? 0), 0) }}</span>
                            <span class="badge fs-6 p-2" id="y-sales-gpft-badge" style="background-color: #1d4ed8; color: white; font-weight: bold;"
                                title="Y Sales GPFT$ = Σ (Line Sales − COGS − COGS Ship)">Y Sales GPFT$: $0</span>
                            <span class="badge fs-6 p-2" id="y-sales-roi-badge" style="background-color: #6d28d9; color: white; font-weight: bold;"
                                title="Y Sales ROI% = Y Sales GPFT$ badge ÷ Σ COGS × 100">Y Sales ROI%: 0%</span>
                            <span class="badge fs-6 p-2" id="y-sales-gpft-pct-badge" style="background-color: #0369a1; color: white; font-weight: bold;"
                                title="Y Sales GPFT% = Y Sales GPFT$ badge ÷ (Σ Line Sales × 1.1364) × 100">Y Sales GPFT%: 0%</span>
                        </div>
                    </div>
                    <div class="amm-badge-block amm-badge-block--projected">
                        <div class="amm-badge-block-title">Projected</div>
                        <div class="ebay2-summary-badge-row">
                            <span class="badge fs-6 p-2" id="p-sales-badge" style="background-color: #0d6efd; color: white; font-weight: bold;"
                                title="P-Sales = (L7 Line Sales ÷ 7) × 30. % is P-Sales vs line sales.">P-Sales: $0<span id="p-sales-vs"></span></span>
                            <span class="badge fs-6 p-2" id="p-full-price-badge" style="background-color: #0d6efd; color: white; font-weight: bold;"
                                title="P Full Price = P-Sales × 1.1364">P Full Price: $0</span>
                            <span class="badge fs-6 p-2" id="p-gpft-badge" style="background-color: #0d6efd; color: white; font-weight: bold;"
                                title="P GPFT$ = (L7 GPFT$ ÷ 7) × 30">P GPFT$: $0</span>
                            <span class="badge fs-6 p-2" id="p-gpft-pct-badge" style="background-color: #0d6efd; color: white; font-weight: bold;"
                                title="P GPFT% = P GPFT$ ÷ P Full Price × 100">P GPFT%: 0%</span>
                            <span class="badge fs-6 p-2" id="p-groi-badge" style="background-color: #0d6efd; color: white; font-weight: bold;"
                                title="P GROI% = P GPFT$ ÷ P COGS × 100. P COGS = (L7 COGS ÷ 7) × 30.">P GROI%: 0%</span>
                        </div>
                    </div>
                    <div class="amm-badge-block amm-badge-block--others">
                        <div class="amm-badge-block-title">Others</div>
                        <div class="ebay2-summary-badge-row">
                            <span class="badge bg-primary fs-6 p-2" id="total-cogs-badge" style="color: white; font-weight: bold;">COGS: $0</span>
                            <span class="badge fs-6 p-2" id="cogs-ship-badge" style="background-color: #b45309; color: white; font-weight: bold;"
                                title="Σ COGS Ship. Shipping Master Ship slab for Weight Order.">COGS Ship: $0</span>
                            @include('partials.analytics-dil-badge', ['dilChannel' => 'temu3'])
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="temu3-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU & Parent Search -->
                    <div class="p-2 bg-light border-bottom d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU..." style="max-width: 220px;">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="temu3-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="uploadOrdersModal" tabindex="-1" aria-labelledby="uploadOrdersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="uploadOrdersModalLabel">
                        <i class="fa fa-upload me-2"></i>Upload Temu 3 Sales
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadOrdersForm" method="POST" action="{{ route('temu3.orders.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="ordersFile" class="form-label fw-bold">
                                <i class="fa fa-file-excel text-success me-1"></i>Temu Seller Center order export
                            </label>
                            <input type="file" class="form-control" name="orders_file" id="ordersFile"
                                   accept=".xlsx,.xls,.csv,.tsv,.txt" required>
                            <div class="form-text">
                                Accepts .xlsx, .xls, .csv, .tsv, or .txt (Max: 40MB)
                            </div>
                        </div>
                        <div class="alert alert-info mb-2">
                            <strong>Format:</strong> Order ID, order status, contribution sku, SKU ID,
                            quantity purchased, purchase date, <strong>goods base price</strong>, tracking number, …
                            <br>
                            Each upload <strong>replaces</strong> all previous Temu 3 order rows (old orders are truncated).
                            This is the same sales file as <strong>Up Orders</strong> on Temu Analytics.
                            <br>
                            <a href="{{ route('temu3.orders.sample') }}" class="alert-link">
                                <i class="fa fa-download"></i> Download Sample File
                            </a>
                        </div>
                        <div id="ordersUploadResult" class="alert" style="display:none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="startOrdersUploadBtn">
                        <i class="fa fa-upload me-1"></i>Upload Sales
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    // Same margin as /temu-tabulator — marketplace_percentages.Temu (no hardcode)
    const TEMU_MARGIN = {{ (float) $temuMargin }};
    const TEMU_PRICE_MULT = 1.1364;

    function pacificYmd(date) {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: 'America/Los_Angeles', year: 'numeric', month: '2-digit', day: '2-digit'
        }).formatToParts(date);
        const get = type => parts.find(p => p.type === type).value;
        return get('year') + '-' + get('month') + '-' + get('day');
    }
    function addDaysYmd(ymd, delta) {
        const parts = ymd.split('-').map(Number);
        const dt = new Date(Date.UTC(parts[0], parts[1] - 1, parts[2] + delta));
        return dt.getUTCFullYear() + '-' + String(dt.getUTCMonth() + 1).padStart(2, '0') + '-' + String(dt.getUTCDate()).padStart(2, '0');
    }
    function temuL7DateKeys() {
        const yesterday = addDaysYmd(pacificYmd(new Date()), -1);
        const keys = new Set();
        for (let i = 0; i < 7; i++) keys.add(addDaysYmd(yesterday, -i));
        return keys;
    }
    function temuIdDot(value) {
        const id = String(value || '').trim();
        if (!id) return '';
        const safe = id.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;');
        return '<span class="temu-order-id-hit" data-full="' + safe + '" aria-label="' + safe + '"><span class="temu-order-id-dot"></span></span>';
    }
    function temuShortDate(value) {
        const raw = String(value || '').trim();
        if (!raw) return '';
        const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        let day = 0, month = 0;
        if (match) {
            day = parseInt(match[3], 10);
            month = parseInt(match[2], 10) - 1;
        } else {
            const parsed = new Date(raw);
            if (isNaN(parsed.getTime())) return raw;
            day = parsed.getDate();
            month = parsed.getMonth();
        }
        if (month < 0 || month > 11 || !(day > 0)) return raw;
        const safeFull = raw.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        return '<span title="' + safeFull + '">' + day + ' ' + months[month] + '</span>';
    }
    const TEMU_FREIGHT = 2.99;
    const TEMU_FREIGHT_CAP = 26.99;
    /** Base = stored sheet unit as-is (goods-only); the $2.99 is added once in R Price / Temu Price. */
    function temuGoodsBase(rawUnit) {
        const b = parseFloat(rawUnit) || 0;
        if (b <= 0) return 0;
        return +b.toFixed(2);
    }
    function temuRowBase(row) {
        const goods = parseFloat(row && row.goods_base_price) || 0;
        return temuGoodsBase(goods > 0 ? goods : (row && row.base_price_total));
    }
    function temuPriceFromBase(basePrice) {
        const b = parseFloat(basePrice) || 0;
        if (b <= 0) return 0;
        let price = b * TEMU_PRICE_MULT;
        if (price <= TEMU_FREIGHT_CAP) price += TEMU_FREIGHT;
        return price;
    }
    function temuFbPrice(basePrice, quantity) {
        const base = parseFloat(basePrice) || 0;
        const qty = parseInt(quantity) || 0;
        if (qty <= 0 || base <= 0) return 0;
        return base <= TEMU_FREIGHT_CAP ? base + TEMU_FREIGHT : base;
    }
    function temuRowRPrice(row) {
        const qty = parseInt(row && row.quantity_purchased) || 0;
        return temuFbPrice(temuRowBase(row), qty);
    }
    function temuRowTemuPrice(row) {
        return temuPriceFromBase(temuRowBase(row));
    }
    function temuRowCogs(row) {
        const qty = parseInt(row && row.quantity_purchased) || 0;
        return qty * (parseFloat(row && row.lp) || 0);
    }
    function temuRowYSalesGpftDollar(row) {
        const lineSales = parseFloat(row && row.line_sales) || 0;
        const ship = parseFloat(row && row.cogs_ship) || 0;
        return lineSales - temuRowCogs(row) - ship;
    }
    function temuRowYSalesRoiPercent(row) {
        const cogs = temuRowCogs(row);
        if (!(cogs > 0)) return null;
        return (temuRowYSalesGpftDollar(row) / cogs) * 100;
    }
    function temuRowYSalesGpftPercent(row) {
        const lineSales = parseFloat(row && row.line_sales) || 0;
        const denom = lineSales * TEMU_PRICE_MULT;
        if (!(denom > 0)) return null;
        return (temuRowYSalesGpftDollar(row) / denom) * 100;
    }
    function temuRowGpftDollar(row) {
        const lineSales = parseFloat(row && row.line_sales) || 0;
        const ship = parseFloat(row && row.cogs_ship) || 0;
        return lineSales * TEMU_MARGIN - temuRowCogs(row) - ship;
    }
    function temuRowGpftPercent(row) {
        const qty = parseInt(row && row.quantity_purchased) || 0;
        const sales = temuRowTemuPrice(row) * qty;
        if (!(sales > 0)) return 0;
        return (temuRowGpftDollar(row) / sales) * 100;
    }
    function temuRowGroiPercent(row) {
        const cogs = temuRowCogs(row);
        if (!(cogs > 0)) return 0;
        return (temuRowGpftDollar(row) / cogs) * 100;
    }
    const COLUMN_VIS_KEY = "temu3_tabulator_column_visibility";
    let table = null;
    
    // Toast notification function
    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    $(document).ready(function() {
        // Set CSRF token for AJAX requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        // Initialize Tabulator from temu3_orders (same source as /temu3-decrease)
        console.log("Initializing Tabulator for Temu 3 orders...");
        table = new Tabulator("#temu3-table", {
            ajaxURL: "/temu3/daily-data",
            ajaxSorting: false,
            layout: "fitData",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            ajaxResponse: function(url, params, response) {
                if (Array.isArray(response)) {
                    return response;
                }
                return response;
            },
            ajaxError: function(error) {
                showToast("Error loading data: " + (error.message || "Unknown error"), "error");
            },
            dataLoaded: function(data) {
                updateSummary();
            },
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "Show",
                        "first": "First",
                        "first_title": "First Page",
                        "last": "Last",
                        "last_title": "Last Page",
                        "prev": "Prev",
                        "prev_title": "Prev Page",
                        "next": "Next",
                        "next_title": "Next Page",
                        "counter": {
                            "showing": "Showing",
                            "of": "of",
                            "rows": "rows"
                        }
                    }
                }
            },
            rowFormatter: function(row) {
                if (row.getData().Parent && row.getData().Parent.startsWith('PARENT')) {
                    row.getElement().style.backgroundColor = "#fffef2";
                }
            },
            columnDefaults: {
                hozAlign: "center",
                headerHozAlign: "center",
                vertAlign: "middle",
                widthGrow: 0
            },
            initialSort: [{
                column: "created_at",
                dir: "desc"
            }],
            columns: [
                {
                    title: "Parent",
                    field: "Parent",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search Parent...",
                    cssClass: "text-primary",
                    tooltip: true,
                    width: 90,
                    minWidth: 70,
                    visible: false
                },
                {
                    title: "Order ID",
                    field: "order_id",
                    width: 56,
                    tooltip: false,
                    frozen: true,
                    headerTooltip: "Hover the green dot to see the full Order ID",
                    formatter: function(cell) { return temuIdDot(cell.getValue()); }
                },
                {
                    title: "Image",
                    field: "image_path",
                    width: 56,
                    headerSort: false,
                    tooltip: false,
                    frozen: true,
                    headerTooltip: "Product photo from CP Master",
                    formatter: function(cell) {
                        const value = String(cell.getValue() || '').trim();
                        if (!value) return '';
                        const safe = value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;');
                        return '<img src="' + safe + '" alt="Product" class="hover-thumb" style="width:32px;height:32px;object-fit:cover;border-radius:4px;">';
                    }
                },
                {
                    title: "SKU",
                    field: "contribution_sku",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    width: 110,
                    minWidth: 80,
                    tooltip: true,
                    cssClass: "text-primary fw-bold",
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const isParent = rowData.Parent && rowData.Parent.startsWith('PARENT');
                        if (isParent) return '';
                        return sku || '';
                    }
                },
                {
                    title: "Qty Purchased",
                    field: "quantity_purchased",
                    hozAlign: "center",
                    sorter: "number",
                    width: 48,
                    minWidth: 40
                },
                {
                    title: "Weight",
                    field: "weight",
                    sorter: "number",
                    headerTooltip: "Item WT ACT (lb) from Dim & Wt Master",
                    formatter: function(cell) {
                        const n = parseFloat(cell.getValue());
                        return n > 0 ? n.toFixed(2) : '';
                    }
                },
                {
                    title: "Weight Order",
                    field: "weight_order",
                    sorter: "number",
                    headerTooltip: "Weight Order = Weight × Qty Purchased",
                    formatter: function(cell) {
                        const n = parseFloat(cell.getValue());
                        return n > 0 ? n.toFixed(2) : '';
                    }
                },
                {
                    title: "Qty Shipped",
                    field: "quantity_shipped",
                    hozAlign: "center",
                    sorter: "number",
                    width: 48,
                    minWidth: 40,
                    visible: false
                },
                {
                    title: "Qty To Ship",
                    field: "quantity_to_ship",
                    hozAlign: "center",
                    sorter: "number",
                    width: 48,
                    minWidth: 40,
                    visible: false
                },
                {
                    title: "B Pric",
                    field: "fb_price",
                    hozAlign: "center",
                    sorter: "number",
                    width: 62,
                    minWidth: 54,
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "", precision: 2 },
                    mutator: function(value, data) {
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        return temuFbPrice(temuRowBase(data), quantity).toFixed(2);
                    }
                },
                {
                    title: "R Price",
                    field: "r_price",
                    hozAlign: "center",
                    sorter: "number",
                    width: 62,
                    minWidth: 54,
                    headerTooltip: "R Price = Base Price + $2.99 if Base Price ≤ $26.99; otherwise Base Price",
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "", precision: 2 },
                    mutator: function(value, data) {
                        return temuRowRPrice(data).toFixed(2);
                    }
                },
                {
                    title: "Price",
                    field: "temu_price",
                    hozAlign: "center",
                    sorter: "number",
                    width: 62,
                    minWidth: 54,
                    headerTooltip: "Temu Price = Base × 1.1364; +$2.99 if that result ≤ $26.99",
                    mutator: function(value, data) {
                        return temuRowTemuPrice(data);
                    },
                    formatter: function(cell) {
                        const rPrice = temuRowRPrice(cell.getRow().getData());
                        const price = parseFloat(cell.getValue()) || temuRowTemuPrice(cell.getRow().getData());
                        if (!(price > 0)) return '';
                        const tip = 'Temu Price = Base × 1.1364 (+$2.99 if ≤ $26.99) → ' + price.toFixed(2);
                        return `<span title="${tip}">${price.toFixed(2)}</span>`;
                    }
                },
                {
                    title: "LP",
                    field: "lp",
                    hozAlign: "center",
                    sorter: "number",
                    width: 55,
                    minWidth: 48,
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "", precision: 2 }
                },
                {
                    title: "COGS",
                    field: "cogs",
                    hozAlign: "center",
                    sorter: "number",
                    width: 58,
                    minWidth: 50,
                    visible: false,
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "", precision: 2 },
                    mutator: function(value, data) {
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        const lp = parseFloat(data.lp) || 0;
                        return (quantity * lp).toFixed(2);
                    }
                },
                {
                    title: "Hdl Charge",
                    field: "handling_charge",
                    headerTooltip: "Handling Charge saved on Shipping Master (included in Temu Ship)",
                    hozAlign: "center",
                    width: 50,
                    minWidth: 44,
                    visible: false
                },
                {
                    title: "O-Size Charge",
                    field: "o_size_charge",
                    headerTooltip: "O-Size Charge saved on Shipping Master (included in Temu Ship)",
                    hozAlign: "center",
                    width: 50,
                    minWidth: 44,
                    visible: false
                },
                {
                    title: "Temu Ship",
                    field: "temu_ship",
                    headerTooltip: "Saved Temu ship = slab + Handling Charge + O-Size Charge",
                    hozAlign: "center",
                    sorter: "number",
                    width: 62,
                    minWidth: 54,
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "", precision: 2 }
                },
                {
                    title: "COGS Ship",
                    field: "cogs_ship",
                    sorter: "number",
                    headerTooltip: "Shipping Master Ship slab rate for Weight Order",
                    formatter: function(cell) {
                        const raw = cell.getValue();
                        if (raw === null || raw === undefined || raw === '') return '';
                        const n = parseFloat(raw);
                        return isFinite(n) ? '$' + n.toFixed(2) : '';
                    }
                },
                {
                    title: "Y Sales GPFT$",
                    field: "y_sales_gpft",
                    sorter: "number",
                    headerTooltip: "Y Sales GPFT$ = Line Sales − COGS − COGS Ship",
                    mutator: function(value, data) { return temuRowYSalesGpftDollar(data); },
                    formatter: function(cell) {
                        const n = parseFloat(cell.getValue());
                        if (!isFinite(n)) return '';
                        const color = n >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">$${n.toFixed(2)}</span>`;
                    }
                },
                {
                    title: "Y Sales ROI%",
                    field: "y_sales_roi",
                    sorter: "number",
                    headerTooltip: "Y Sales ROI% = Y Sales GPFT$ ÷ COGS × 100",
                    mutator: function(value, data) {
                        const n = temuRowYSalesRoiPercent(data);
                        return n === null ? '' : n;
                    },
                    formatter: function(cell) {
                        const raw = cell.getValue();
                        if (raw === '' || raw === null || raw === undefined) return '';
                        const n = parseFloat(raw);
                        if (!isFinite(n)) return '';
                        const color = n >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">${Math.round(n)}%</span>`;
                    }
                },
                {
                    title: "Y Sales GPFT%",
                    field: "y_sales_gpft_pct",
                    sorter: "number",
                    headerTooltip: "Y Sales GPFT% = Y Sales GPFT$ ÷ (Line Sales × 1.1364) × 100",
                    mutator: function(value, data) {
                        const n = temuRowYSalesGpftPercent(data);
                        return n === null ? '' : n;
                    },
                    formatter: function(cell) {
                        const raw = cell.getValue();
                        if (raw === '' || raw === null || raw === undefined) return '';
                        const n = parseFloat(raw);
                        if (!isFinite(n)) return '';
                        const color = n >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">${Math.round(n)}%</span>`;
                    }
                },
                {
                    title: "GPFT$",
                    field: "pft",
                    hozAlign: "center",
                    sorter: "number",
                    width: 62,
                    minWidth: 54,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const color = value >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">${parseFloat(value).toFixed(2)}</span>`;
                    },
                    headerTooltip: "GPFT$ = (Line Sales × Temu margin) − COGS − COGS Ship",
                    mutator: function(value, data) {
                        return temuRowGpftDollar(data).toFixed(2);
                    }
                },
                {
                    title: "GROI %",
                    field: "groi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    width: 48,
                    minWidth: 42,
                    headerTooltip: "GROI % = GPFT$ ÷ LP × 100",
                    mutator: function(value, data) {
                        return temuRowGroiPercent(data);
                    },
                    formatter: function(cell) {
                        const n = parseFloat(cell.getValue());
                        if (!isFinite(n)) return '';
                        const color = n >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">${Math.round(n)}%</span>`;
                    }
                },
                {
                    title: "GPFT %",
                    field: "gpft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    width: 48,
                    minWidth: 42,
                    headerTooltip: "GPFT % = GPFT$ ÷ Price × 100",
                    mutator: function(value, data) {
                        return temuRowGpftPercent(data);
                    },
                    formatter: function(cell) {
                        const n = parseFloat(cell.getValue());
                        if (!isFinite(n)) return '';
                        const color = n >= 0 ? '#28a745' : '#dc3545';
                        return `<span style="color: ${color}; font-weight: bold;">${Math.round(n)}%</span>`;
                    }
                },
                {
                    title: "L30 Sales",
                    field: "l30_sales",
                    visible: false,
                    hozAlign: "center",
                    sorter: "number",
                    width: 62,
                    minWidth: 54,
                    headerTooltip: "L30 Sales = Temu Price × Qty",
                    formatter: "money",
                    formatterParams: { decimal: ".", thousand: ",", symbol: "", precision: 2 },
                    mutator: function(value, data) {
                        const quantity = parseInt(data.quantity_purchased) || 0;
                        return (quantity * temuRowTemuPrice(data)).toFixed(2);
                    }
                },
                {
                    title: "Order Status",
                    field: "order_status",
                    width: 80,
                    minWidth: 64,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        let color = 'secondary';
                        if (value.toLowerCase().includes('delivered')) color = 'success';
                        else if (value.toLowerCase().includes('shipped')) color = 'info';
                        else if (value.toLowerCase().includes('cancelled') || value.toLowerCase().includes('cancel')) color = 'danger';
                        else if (value.toLowerCase().includes('pending')) color = 'warning';
                        return `<span class="badge bg-${color}">${value}</span>`;
                    }
                },
                {
                    title: "Tracking",
                    field: "tracking_number",
                    width: 56,
                    tooltip: false,
                    headerTooltip: "Hover the green dot to see the full tracking number",
                    formatter: function(cell) { return temuIdDot(cell.getValue()); }
                },
                {
                    title: "Carrier",
                    field: "carrier",
                    visible: false
                },
                {
                    title: "Created At",
                    field: "created_at",
                    sorter: "datetime",
                    headerTooltip: "Shown as day and month. Hover a date for the full timestamp.",
                    formatter: function(cell) { return temuShortDate(cell.getValue()); }
                }
            ]
        });

        const orderIdTip = document.createElement('div');
        orderIdTip.className = 'temu-order-id-tip';
        document.body.appendChild(orderIdTip);
        function hideOrderIdTip() { orderIdTip.style.display = 'none'; }
        function showOrderIdTip(dot) {
            const id = dot.getAttribute('data-full') || '';
            if (!id) { hideOrderIdTip(); return; }
            orderIdTip.textContent = id;
            orderIdTip.style.display = 'block';
            const rect = dot.getBoundingClientRect();
            let left = rect.right + 8;
            let top = rect.top + (rect.height / 2) - (orderIdTip.offsetHeight / 2);
            if (left + orderIdTip.offsetWidth > window.innerWidth - 8) left = Math.max(8, rect.left - orderIdTip.offsetWidth - 8);
            if (top + orderIdTip.offsetHeight > window.innerHeight - 8) top = window.innerHeight - orderIdTip.offsetHeight - 8;
            orderIdTip.style.left = left + 'px';
            orderIdTip.style.top = Math.max(8, top) + 'px';
        }
        $('#temu3-table').on('mouseenter', '.temu-order-id-hit', function() { showOrderIdTip(this); })
            .on('mouseleave', '.temu-order-id-hit', hideOrderIdTip);
        document.getElementById('temu3-table').addEventListener('scroll', hideOrderIdTip, true);

        $('#sku-search').on('keyup', function() {
            table.setFilter([
                { field: 'contribution_sku', type: 'like', value: $('#sku-search').val() || '' }
            ]);
        });

        function updateSummary() {
            const data = table.getData("active");
            let totalOrders = 0, totalQuantity = 0, totalPft = 0, totalL30Sales = 0;
            let totalCogs = 0, totalCogsShip = 0, totalLineSales = 0;
            let totalYSalesGpft = 0, totalYSalesCogs = 0;
            let l7LineSales = 0, l7Gpft = 0, l7Cogs = 0;
            const l7Keys = temuL7DateKeys();

            data.forEach(row => {
                if (row.Parent && String(row.Parent).startsWith('PARENT')) return;
                const sku = String(row.contribution_sku || '');
                if (!sku || !row.order_id || row.order_id === '') return;
                if (sku.toUpperCase().indexOf('PARENT') !== -1) return;
                totalOrders++;
                const quantity = parseInt(row.quantity_purchased) || 0;
                const basePrice = temuRowBase(row);
                const temuPrice = temuRowTemuPrice(row);
                const lp = parseFloat(row.lp) || 0;
                const lineSales = parseFloat(row.line_sales) || 0;
                totalQuantity += quantity;
                totalLineSales += lineSales;
                totalCogsShip += parseFloat(row.cogs_ship) || 0;
                totalYSalesGpft += temuRowYSalesGpftDollar(row);
                totalYSalesCogs += temuRowCogs(row);
                const inL7 = l7Keys.has(String(row.created_at || '').slice(0, 10));
                if (inL7) l7LineSales += lineSales;
                if (quantity > 0 && basePrice > 0) {
                    const gpft = temuRowGpftDollar(row);
                    totalPft += gpft;
                    totalCogs += lp * quantity;
                    totalL30Sales += quantity * temuPrice;
                    if (inL7) {
                        l7Gpft += gpft;
                        l7Cogs += lp * quantity;
                    }
                }
            });

            const fullSales = totalLineSales * TEMU_PRICE_MULT;
            const fullBadge = Math.round(fullSales);
            const gpftBadge = Math.round(totalPft);
            const cogsBadge = Math.round(totalCogs);
            const pftPercentage = fullBadge !== 0 ? (gpftBadge / fullBadge) * 100 : 0;
            const roiPercentage = cogsBadge !== 0 ? (gpftBadge / cogsBadge) * 100 : 0;

            $('#total-orders-badge').text('Orders: ' + totalOrders.toLocaleString());
            $('#total-quantity-badge').text('Quantity: ' + totalQuantity.toLocaleString());
            $('#api-line-sales-badge').text('API Line Sales: $' + Math.round(totalLineSales).toLocaleString());
            $('#temu-full-price-sales-badge').text('Temu Full Price Sales: $' + fullBadge.toLocaleString());
            $('#pft-percentage-badge').text('GPFT: ' + Math.round(pftPercentage) + '%');
            $('#roi-percentage-badge').text('GROI: ' + Math.round(roiPercentage) + '%');
            $('#pft-total-badge').text('GPFT$: $' + gpftBadge.toLocaleString());
            $('#pft-total-badge').toggleClass('bg-danger', totalPft < 0).toggleClass('bg-dark', totalPft >= 0);
            $('#l30-sales-badge').text('L30 Sales: $' + Math.round(totalL30Sales).toLocaleString());
            $('#total-cogs-badge').text('COGS: $' + cogsBadge.toLocaleString());
            $('#cogs-ship-badge').text('COGS Ship: $' + Math.round(totalCogsShip).toLocaleString());
            const ySalesRoi = totalYSalesCogs !== 0 ? (totalYSalesGpft / totalYSalesCogs) * 100 : 0;
            const ySalesGpftPct = fullSales !== 0 ? (totalYSalesGpft / fullSales) * 100 : 0;
            $('#y-sales-gpft-badge').text('Y Sales GPFT$: $' + Math.round(totalYSalesGpft).toLocaleString())
                .css('background-color', totalYSalesGpft < 0 ? '#dc3545' : '#1d4ed8');
            $('#y-sales-roi-badge').text('Y Sales ROI%: ' + Math.round(ySalesRoi) + '%');
            $('#y-sales-gpft-pct-badge').text('Y Sales GPFT%: ' + Math.round(ySalesGpftPct) + '%');
            const pSales = (l7LineSales / 7) * 30;
            const pFull = pSales * TEMU_PRICE_MULT;
            const pGpft = (l7Gpft / 7) * 30;
            const pCogs = (l7Cogs / 7) * 30;
            const pVs = totalLineSales > 0 ? ((pSales - totalLineSales) / totalLineSales) * 100 : null;
            $('#p-sales-badge').contents().filter(function() { return this.nodeType === 3; }).first()
                .replaceWith('P-Sales: $' + Math.round(pSales).toLocaleString());
            if (pVs == null || !isFinite(pVs)) {
                $('#p-sales-vs').text('');
            } else if (Math.abs(pVs) < 0.1) {
                $('#p-sales-vs').text(' 0%').css('color', '#e5e7eb');
            } else {
                const up = pVs > 0;
                $('#p-sales-vs').text(' ' + (up ? '+' : '−') + Math.abs(Math.round(pVs)) + '%').css('color', up ? '#86efac' : '#fecaca');
            }
            $('#p-full-price-badge').text('P Full Price: $' + Math.round(pFull).toLocaleString());
            $('#p-gpft-badge').text('P GPFT$: $' + Math.round(pGpft).toLocaleString()).css('background-color', pGpft < 0 ? '#dc3545' : '#0d6efd');
            $('#p-gpft-pct-badge').text('P GPFT%: ' + Math.round(pFull !== 0 ? (pGpft / pFull) * 100 : 0) + '%');
            $('#p-groi-badge').text('P GROI%: ' + Math.round(pCogs !== 0 ? (pGpft / pCogs) * 100 : 0) + '%');
        
            if (window.AnalyticsDilBadge) {
                var __dilRows = (typeof allTableData !== 'undefined' && Array.isArray(allTableData) && allTableData.length) ? allTableData
                    : (typeof summaryDataCache !== 'undefined' && Array.isArray(summaryDataCache) && summaryDataCache.length) ? summaryDataCache
                    : (typeof table !== 'undefined' && table && typeof table.getData === 'function') ? (function () { try { return table.getData() || []; } catch (e) { return []; } })()
                    : (typeof tableData !== 'undefined' && Array.isArray(tableData) ? tableData : []);
                AnalyticsDilBadge.paintFromRows(__dilRows);
            }
        }

        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';
            fetch('/temu3-column-visibility', { method: 'GET', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                .then(response => response.json())
                .then(savedVisibility => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (!def.field) return;
                        const li = document.createElement("li");
                        const label = document.createElement("label");
                        label.style.display = "block"; label.style.padding = "5px 10px"; label.style.cursor = "pointer";
                        const checkbox = document.createElement("input");
                        checkbox.type = "checkbox"; checkbox.value = def.field;
                        const forceHide = def.field === 'handling_charge' || def.field === 'o_size_charge' || def.field === 'carrier';
                        checkbox.checked = forceHide ? false : savedVisibility[def.field] !== false;
                        checkbox.style.marginRight = "8px";
                        label.appendChild(checkbox);
                        label.appendChild(document.createTextNode(def.title));
                        li.appendChild(label);
                        menu.appendChild(li);
                    });
                });
        }

        function saveColumnVisibilityToServer() {
            const visibility = {};
            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field) visibility[def.field] = col.isVisible();
            });
            fetch('/temu3-column-visibility', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ visibility: visibility })
            });
        }

        function applyColumnVisibilityFromServer() {
            fetch('/temu3-column-visibility', { method: 'GET', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                .then(response => response.json())
                .then(savedVisibility => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (def.field === 'handling_charge' || def.field === 'o_size_charge' || def.field === 'carrier') {
                            col.hide();
                            return;
                        }
                        if (def.field && savedVisibility[def.field] === false) col.hide();
                    });
                });
        }

        table.on('tableBuilt', function() {
            applyColumnVisibilityFromServer();
            buildColumnDropdown();
        });
        table.on('dataLoaded', updateSummary);
        table.on('dataProcessed', updateSummary);
        table.on('renderComplete', updateSummary);

        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.type === 'checkbox') {
                const col = table.getColumn(e.target.value);
                e.target.checked ? col.show() : col.hide();
                saveColumnVisibilityToServer();
            }
        });

        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => col.show());
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        // Export L30 Data - uses current table data
        $(document).on('click', '.export-l30', function(e) {
            e.preventDefault();
            table.download("csv", "temu3_l30_orders.csv");
        });

        // Export L7 Data - fetches from L7 endpoint and downloads as CSV
        $(document).on('click', '.export-l7', function(e) {
            e.preventDefault();
            const $loading = $('#export-loading');
            $loading.show();
            $.ajax({
                url: '{{ url("/temu3/daily-data-l7") }}',
                method: 'GET',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                success: function(data) {
                    if (!Array.isArray(data)) {
                        showToast('Invalid response from L7 endpoint', 'error');
                        return;
                    }
                    const columns = ['Parent', 'order_id', 'image_path', 'contribution_sku',
                        'quantity_purchased', 'weight', 'weight_order', 'line_sales', 'base_price_total', 'fb_price', 'temu_price',
                        'lp', 'temu_ship', 'cogs_ship', 'y_sales_gpft', 'y_sales_roi', 'y_sales_gpft_pct', 'pft', 'gpft_percent', 'groi_percent', 'l30_sales', 'order_status', 'tracking_number', 'created_at'];
                    const headers = columns.join(',');
                    const escapeCsv = function(val) {
                        if (val === null || val === undefined) return '""';
                        const s = String(val);
                        if (s.includes(',') || s.includes('"') || s.includes('\n')) {
                            return '"' + s.replace(/"/g, '""') + '"';
                        }
                        return '"' + s + '"';
                    };
                    const rows = data.map(function(row) {
                        const qty = parseInt(row.quantity_purchased) || 0;
                        const temuPrice = temuRowTemuPrice(row);
                        const l7Sales = (qty * temuPrice).toFixed(2);
                        const rowData = {
                            ...row,
                            base_price_total: temuRowBase(row),
                            temu_price: temuPrice,
                            pft: temuRowGpftDollar(row).toFixed(2),
                            cogs_ship: row.cogs_ship,
                            y_sales_gpft: temuRowYSalesGpftDollar(row),
                            y_sales_roi: temuRowYSalesRoiPercent(row),
                            y_sales_gpft_pct: temuRowYSalesGpftPercent(row),
                            gpft_percent: temuRowGpftPercent(row),
                            groi_percent: temuRowGroiPercent(row),
                            l30_sales: l7Sales
                        };
                        return columns.map(function(col) {
                            return escapeCsv(rowData[col] ?? '');
                        }).join(',');
                    });
                    const csv = [headers, ...rows].join('\n');
                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = 'temu3_l7_orders.csv';
                    link.click();
                    URL.revokeObjectURL(link.href);
                    showToast('L7 data exported successfully', 'success');
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Failed to fetch L7 data';
                    showToast(msg, 'error');
                },
                complete: function() {
                    $loading.hide();
                }
            });
        });

        $('#startOrdersUploadBtn').on('click', function() {
            const fileInput = document.getElementById('ordersFile');
            const file = fileInput && fileInput.files && fileInput.files[0];
            if (!file) {
                showToast('Choose a Temu 3 order export first', 'error');
                return;
            }
            const $btn = $(this);
            const $result = $('#ordersUploadResult');
            $result.hide().removeClass('alert-success alert-danger');
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>Uploading…');

            const fd = new FormData();
            fd.append('orders_file', file);
            fd.append('_token', '{{ csrf_token() }}');

            $.ajax({
                url: '{{ route("temu3.orders.upload") }}',
                method: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                timeout: 180000,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                success: function(res) {
                    const msg = (res && res.message) || 'Sales uploaded';
                    $result.addClass(res && res.success === false ? 'alert-danger' : 'alert-success')
                        .text(msg).show();
                    showToast(msg, res && res.success === false ? 'error' : 'success');
                    if (res && res.success !== false) {
                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    }
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message)
                        || 'Temu 3 sales upload failed';
                    $result.addClass('alert-danger').text(msg).show();
                    showToast(msg, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fa fa-upload me-1"></i>Upload Sales');
                }
            });
        });

        $('#uploadOrdersModal').on('hidden.bs.modal', function() {
            $('#ordersFile').val('');
            $('#ordersUploadResult').hide().removeClass('alert-success alert-danger').text('');
            $('#startOrdersUploadBtn').prop('disabled', false).html('<i class="fa fa-upload me-1"></i>Upload Sales');
        });

    });
</script>
@endsection
