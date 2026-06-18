@extends('layouts.vertical', ['title' => 'Newegg Pricing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        .editable-cell {
            cursor: pointer;
        }
        .ne-thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
            cursor: zoom-in;
        }
        #ne-img-preview {
            position: fixed;
            display: none;
            z-index: 99999;
            pointer-events: none;
            border: 2px solid #0d6efd;
            border-radius: 6px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.35);
            background: #fff;
            padding: 3px;
        }
        #ne-img-preview img {
            display: block;
            max-width: 320px;
            max-height: 320px;
            object-fit: contain;
        }

        /* Colored status circles + Walmart-style manual dropdown (used by the DIL% filter). */
        .status-circle {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        .status-circle.default { background-color: #6c757d; }
        .status-circle.red     { background-color: #a00211; }
        .status-circle.yellow  { background-color: #ffc107; }
        .status-circle.green   { background-color: #28a745; }
        .status-circle.pink    { background-color: #e83e8c; }

        .manual-dropdown-container {
            position: relative;
            display: inline-block;
        }
        .manual-dropdown-container .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            display: none;
            min-width: 200px;
            padding: 0.5rem 0;
            margin: 0;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            list-style: none;
        }
        .manual-dropdown-container.show .dropdown-menu { display: block; }
        .manual-dropdown-container .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.5rem 1rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            text-decoration: none;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            cursor: pointer;
        }
        .manual-dropdown-container .dropdown-item:hover {
            color: #1e2125;
            background-color: #e9ecef;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Newegg Pricing',
        'sub_title' => 'Newegg Pricing & Inventory',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Newegg Pricing & Inventory</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <select id="inventory-filter" class="form-select form-select-sm" style="width: 130px;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <select id="n-stock-filter" class="form-select form-select-sm" style="width: 130px;"
                        title="Newegg listing stock (N INV)">
                        <option value="all">N Stock</option>
                        <option value="zero">0 N Stock</option>
                        <option value="more">More than 0</option>
                    </select>

                    <select id="nr-filter" class="form-select form-select-sm" style="width: 130px;">
                        <option value="all">All Status</option>
                        <option value="REQ">REQ Only</option>
                        <option value="NR">NR Only</option>
                    </select>

                    <select id="status-filter" class="form-select form-select-sm" style="width: 130px;"
                        title="Newegg listing status">
                        <option value="all">All Listings</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>

                    <select id="pft-filter" class="form-select form-select-sm" style="width: 130px;">
                        <option value="all">PFT%</option>
                        <option value="negative">Negative</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30-40">30-40%</option>
                        <option value="40-50">40-50%</option>
                        <option value="50plus">Above 50%</option>
                    </select>

                    <select id="roi-filter" class="form-select form-select-sm" style="width: 130px;">
                        <option value="all">ROI%</option>
                        <option value="lt50">&lt; 50%</option>
                        <option value="50-75">50–75%</option>
                        <option value="75-125">75–125%</option>
                        <option value="gt125">125%+</option>
                    </select>

                    <div class="dropdown manual-dropdown-container" id="dilFilterContainer">
                        <button class="btn btn-sm btn-light dropdown-toggle" type="button" id="dilFilterDropdown">
                            <span class="status-circle default"></span> DIL%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dilFilterDropdown">
                            <li><a class="dropdown-item dil-filter-item active" href="#" data-color="all">
                                <span class="status-circle default"></span> All DIL</a></li>
                            <li><a class="dropdown-item dil-filter-item" href="#" data-color="red">
                                <span class="status-circle red"></span> Red (&lt;16.7%)</a></li>
                            <li><a class="dropdown-item dil-filter-item" href="#" data-color="yellow">
                                <span class="status-circle yellow"></span> Yellow (16.7–25%)</a></li>
                            <li><a class="dropdown-item dil-filter-item" href="#" data-color="green">
                                <span class="status-circle green"></span> Green (25–50%)</a></li>
                            <li><a class="dropdown-item dil-filter-item" href="#" data-color="pink">
                                <span class="status-circle pink"></span> Pink (50%+)</a></li>
                        </ul>
                    </div>

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
                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-file-excel"></i> Export
                    </button>

                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-arrow-down"></i> Decrease Mode
                    </button>
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-arrow-up"></i> Increase Mode
                    </button>
                    <button id="same-price-btn" class="btn btn-sm btn-info"
                        title="Apply ONE price (entered in the box) to every selected SKU">
                        <i class="fas fa-equals"></i> Same Price Mode
                    </button>
                </div>

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-dark fs-6 p-2" id="total-l30-badge" style="color: white; font-weight: bold;">Total L30: 0</span>
                        <span class="badge fs-6 p-2" id="avg-price-badge" style="background-color: purple; color: white; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="pft-badge" style="color: black; font-weight: bold;">PFT: 0%</span>
                        <span class="badge fs-6 p-2" id="roi-badge" style="background-color: #e83e8c; color: white; font-weight: bold;">ROI: 0%</span>
                        <span class="badge fs-6 p-2" id="ne-missing-badge" style="background-color: #c0392b; color: white; font-weight: bold; cursor: pointer;" title="Not listed on Newegg, REQ, INV > 0 — click to filter">Missing L: 0</span>
                        <span class="badge fs-6 p-2" id="ne-map-badge" style="background-color: #198754; color: white; font-weight: bold; cursor: pointer;" title="Listed, REQ, INV ≈ Newegg stock — click to filter">Map: 0</span>
                        <span class="badge fs-6 p-2" id="ne-nmap-badge" style="background-color: #a71d2a; color: white; font-weight: bold; cursor: pointer;" title="Listed, REQ, INV ≠ Newegg stock — click to filter">N Map: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount / Same Price input (shown when at least one SKU is selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <span id="discount-input-label" class="text-muted small d-none">Same Price ($):</span>
                        <span id="discount-type-select-wrap">
                            <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                                <option value="percentage">Percentage</option>
                                <option value="value">Value ($)</option>
                            </select>
                        </span>
                        <input type="number" id="discount-percentage-input"
                            class="form-control form-control-sm" placeholder="Enter %" step="0.01"
                            style="width: 140px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                        <button id="push-newegg-btn" class="btn btn-dark btn-sm"
                            title="Push each selected SKU's SPRICE (or Price if no SPRICE) live to Newegg">
                            <i class="fas fa-cloud-upload-alt"></i> Push to Newegg
                        </button>
                    </div>
                </div>
                <div id="newegg-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm"
                            placeholder="Search by SKU or Title...">
                    </div>
                    <div id="newegg-pricing-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating image preview -->
    <div id="ne-img-preview"><img src="" alt="preview"></div>

    <!-- Buyer / Seller link modal -->
    <div class="modal fade" id="bsLinkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Buyer / Seller Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="bs-sku">
                    <div class="mb-2"><small class="text-muted">SKU: <span id="bs-sku-label" class="fw-bold"></span></small></div>
                    <div class="mb-3">
                        <label class="form-label">Buyer Link</label>
                        <input type="url" class="form-control" id="bs-buyer-link" placeholder="https://...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Seller Link</label>
                        <input type="url" class="form-control" id="bs-seller-link" placeholder="https://...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="bs-save-btn">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        let table = null;
        let decreaseModeActive  = false;
        let increaseModeActive  = false;
        let samePriceModeActive = false;
        let selectedSkus        = new Set();

        // Inventory filter (matches reverb-pricing): 'all' | 'zero' | 'more'. Default 'more'.
        let inventoryFilter = 'more';

        // Additional reverb-style filters.
        let nStockFilter = 'all';   // 'all' | 'zero' | 'more'  (Newegg listing stock)
        let nrFilter     = 'all';   // 'all' | 'REQ' | 'NR'
        let statusFilter = 'all';   // 'all' | 'Active' | 'Inactive'
        let pftFilter    = 'all';   // 'all' | 'negative' | '0-10' | '10-20' | '20-30' | '30-40' | '40-50' | '50plus'
        let roiFilter    = 'all';   // 'all' | 'lt50' | '50-75' | '75-125' | 'gt125'
        let dilFilter    = 'all';   // 'all' | 'red' | 'yellow' | 'green' | 'pink'

        // Range helper for numeric bucket filters.
        function inRange(n, lo, hi) { return n >= lo && n < hi; }

        // PFT% bucket match — mirrors reverb's GPFT% filter.
        function pftMatches(pct, bucket) {
            if (bucket === 'all') return true;
            const n = parseFloat(pct);
            if (isNaN(n)) return false;
            switch (bucket) {
                case 'negative': return n < 0;
                case '0-10':     return inRange(n, 0, 10);
                case '10-20':    return inRange(n, 10, 20);
                case '20-30':    return inRange(n, 20, 30);
                case '30-40':    return inRange(n, 30, 40);
                case '40-50':    return inRange(n, 40, 50);
                case '50plus':   return n >= 50;
                default:         return true;
            }
        }

        // ROI% buckets follow the same color thresholds used by the ROI cell formatter.
        function roiMatches(pct, bucket) {
            if (bucket === 'all') return true;
            const n = parseFloat(pct);
            if (isNaN(n)) return false;
            switch (bucket) {
                case 'lt50':    return n < 50;
                case '50-75':   return inRange(n, 50, 75);
                case '75-125':  return n >= 75 && n <= 125;
                case 'gt125':   return n > 125;
                default:        return true;
            }
        }

        // DIL% color buckets — same thresholds as dilFormatter().
        function dilMatches(pct, color) {
            if (color === 'all') return true;
            const n = parseFloat(pct) || 0;
            switch (color) {
                case 'red':    return n < 16.7;
                case 'yellow': return n >= 16.7 && n < 25;
                case 'green':  return n >= 25  && n < 50;
                case 'pink':   return n >= 50;
                default:       return true;
            }
        }

        // Bootstrap-toast helper — same UX as reverb-pricing.
        function showToast(message, type = 'info') {
            const toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                console[type === 'error' ? 'error' : 'log'](message);
                return;
            }
            const toast = document.createElement('div');
            const bg = type === 'error' ? 'danger' : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'info'));
            toast.className = `toast align-items-center text-white bg-${bg} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>`;
            toastContainer.appendChild(toast);
            new bootstrap.Toast(toast).show();
            toast.addEventListener('hidden.bs.toast', () => toast.remove());
        }

        // Round to retail .99 endings (Reverb's rule: only above $20.99).
        function roundToRetailPrice(price) {
            if (price < 20.99) return +price.toFixed(2);
            return Math.ceil(price) - 0.01;
        }

        function moneyCol(title, field, visible = true) {
            return {
                title, field, visible,
                hozAlign: "right", sorter: "number",
                formatter: "money",
                formatterParams: { decimal: ".", thousand: ",", symbol: "$", precision: 2 }
            };
        }

        // DIL% = sell-through (OVL30 / INV). Same color buckets as other marketplace pages.
        function dilFormatter(cell) {
            const v = cell.getValue();
            if (v === null || v === undefined) return '<span style="color:#a00211;font-weight:bold;">0%</span>';
            const n = parseFloat(v);
            let color = '#a00211';
            if (n < 16.7) color = '#a00211';
            else if (n < 25) color = '#ffc107';
            else if (n < 50) color = '#28a745';
            else color = '#e83e8c';
            return `<span style="color:${color}; font-weight:bold;">${n.toFixed(0)}%</span>`;
        }

        // ── Missing-listing / mapping state + helpers (same rules as map-issues) ──
        let neMissingActive = false, neMapActive = false, neNMapActive = false;

        function neNr(row) {
            return String((row && row.nr) || 'REQ').trim().toUpperCase();
        }

        // INV vs Newegg stock = Map when diff ≤ 3 units (when 3% of INV < 3) else within rounded 3%.
        function neWithinMapTolerance(inv, neStock) {
            const i = parseFloat(inv) || 0;
            const s = parseFloat(neStock) || 0;
            if (i <= 0) return true;
            const diff = Math.abs(i - s);
            if (i * 0.03 < 3) return diff <= 3;
            return Math.round((diff / i) * 100) <= 3;
        }

        // Missing L — not listed on Newegg, REQ, INV > 0.
        function neRowMissingL(row) {
            if (!row) return false;
            const inv = parseFloat(row.inv) || 0;
            return !row.on_newegg && neNr(row) === 'REQ' && inv > 0;
        }

        // Map status — listed, REQ, INV > 0, Newegg stock > 0. Returns 'map' | 'nmap' | ''.
        function neMapStatus(row) {
            if (!row || !row.on_newegg) return '';
            const inv = parseFloat(row.inv) || 0;
            const neStock = parseFloat(row.available_quantity) || 0;
            if (neNr(row) !== 'REQ' || inv <= 0 || neStock <= 0) return '';
            return neWithinMapTolerance(inv, neStock) ? 'map' : 'nmap';
        }

        $(document).ready(function() {
            $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

            table = new Tabulator("#newegg-pricing-table", {
                ajaxURL: "{{ route('newegg.pricing.data') }}",
                ajaxSorting: false,
                layout: "fitData",
                responsiveLayout: false,
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [10, 25, 50, 100, 200],
                paginationCounter: "rows",
                placeholder: "No Data Available",
                ajaxResponse: function(url, params, response) {
                    return Array.isArray(response) ? response : (response.data || []);
                },
                initialSort: [{ column: "l30", dir: "desc" }],
                columns: [
                    {
                        title: `<input type="checkbox" id="select-all-checkbox">`,
                        field: "_select",
                        hozAlign: "center",
                        headerSort: false,
                        frozen: true,
                        visible: false,
                        width: 50,
                        formatter: function(cell) {
                            const sku = cell.getRow().getData().sku;
                            if (!sku) return '';
                            const isChecked = selectedSkus.has(sku);
                            return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isChecked ? 'checked' : ''}>`;
                        }
                    },
                    {
                        title: "Image", field: "image", hozAlign: "center", headerSort: false, frozen: true,
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (!v) return '';
                            return `<img src="${v}" class="ne-thumb" alt="img" loading="lazy">`;
                        }
                    },
                    { title: "SKU", field: "sku", frozen: true, headerFilter: "input", headerFilterPlaceholder: "Search SKU...", cssClass: "text-primary fw-bold" },
                    { title: "Title", field: "title", visible: false, tooltip: true },
                    { title: "INV", field: "inv", hozAlign: "center", sorter: "number" },
                    { title: "N INV", field: "available_quantity", hozAlign: "center", sorter: "number" },
                    { title: "OVL30", field: "ovl30", hozAlign: "center", sorter: "number" },
                    { title: "DIL %", field: "dil", hozAlign: "center", sorter: "number", formatter: dilFormatter },
                    {
                        title: "B/S", field: "bs", hozAlign: "center", headerSort: false,
                        cssClass: "editable-cell",
                        formatter: function(cell) {
                            const d = cell.getRow().getData();
                            const parts = [];
                            if (d.buyer_link) {
                                parts.push(`<a href="${d.buyer_link}" target="_blank" title="Buyer link" style="font-weight:bold;color:#0d6efd;text-decoration:none;">B</a>`);
                            }
                            if (d.seller_link) {
                                parts.push(`<a href="${d.seller_link}" target="_blank" title="Seller link" style="font-weight:bold;color:#198754;text-decoration:none;">S</a>`);
                            }
                            return parts.join(' / ');
                        },
                        cellClick: function(e, cell) {
                            if (e.target && e.target.tagName === 'A') return; // let links open
                            openBsModal(cell.getRow().getData());
                        }
                    },
                    moneyCol("Price", "price"),
                    { title: "L30", field: "l30", hozAlign: "center", sorter: "number",
                        formatter: function(cell) {
                            const v = parseInt(cell.getValue()) || 0;
                            return v > 0 ? `<span style="color:#28a745;font-weight:bold;">${v}</span>` : '0';
                        }
                    },
                    {
                        title: "Pft %", field: "pft_pct", hozAlign: "right", sorter: "number",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined) return '';
                            const n = parseFloat(v) || 0;
                            const color = n >= 0 ? '#28a745' : '#dc3545';
                            return `<span style="color:${color};font-weight:bold;">${n.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "ROI %", field: "roi", hozAlign: "right", sorter: "number",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined) return '';
                            const n = parseFloat(v) || 0;
                            let color = '#6c757d';
                            if (n < 50) color = '#dc3545';
                            else if (n < 75) color = '#ffc107';
                            else if (n <= 125) color = '#28a745';
                            else color = '#e83e8c';
                            return `<span style="color:${color};font-weight:bold;">${n.toFixed(0)}%</span>`;
                        }
                    },
                    {
                        title: "SPrice", field: "sprice", hozAlign: "right", sorter: "number",
                        editor: "number", editorParams: { min: 0, step: 0.01 },
                        cssClass: "editable-cell",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined || v === '') return '<span style="color:#bbb;">—</span>';
                            return '$' + (parseFloat(v) || 0).toFixed(2);
                        }
                    },
                    {
                        title: "SPft %", field: "spft", hozAlign: "right", sorter: "number",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined || v === '') return '';
                            const n = parseFloat(v) || 0;
                            const color = n >= 0 ? '#28a745' : '#dc3545';
                            return `<span style="color:${color};font-weight:bold;">${n.toFixed(1)}%</span>`;
                        }
                    },
                    {
                        title: "SROI %", field: "sroi", hozAlign: "right", sorter: "number",
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (v === null || v === undefined || v === '') return '';
                            const n = parseFloat(v) || 0;
                            let color = '#6c757d';
                            if (n < 50) color = '#dc3545';
                            else if (n < 75) color = '#ffc107';
                            else if (n <= 125) color = '#28a745';
                            else color = '#e83e8c';
                            return `<span style="color:${color};font-weight:bold;">${n.toFixed(0)}%</span>`;
                        }
                    },
                    {
                        title: "NR/REQ", field: "nr", hozAlign: "center",
                        headerSort: false, cssClass: "editable-cell",
                        formatter: function(cell) {
                            const v = cell.getValue() || 'REQ';
                            const color = v === 'NR' ? '#dc3545' : '#28a745';
                            return `<span title="Click to toggle" style="display:inline-block;width:14px;height:14px;border-radius:50%;background:${color};"></span>`;
                        },
                        cellClick: function(e, cell) {
                            const row = cell.getRow();
                            const data = row.getData();
                            const next = (data.nr === 'NR') ? 'REQ' : 'NR';
                            row.update({ nr: next });
                            fetch("{{ route('newegg.pricing.save.nr') }}", {
                                method: "POST",
                                headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}" },
                                body: JSON.stringify({ sku: data.sku, nr: next })
                            })
                            .then(r => r.json())
                            .then(res => { if (!res.success) alert(res.error || "Failed to save NR"); })
                            .catch(() => alert("Failed to save NR"));
                        }
                    },
                    {
                        title: "Status", field: "status", hozAlign: "center",
                        formatter: function(cell) {
                            const v = cell.getValue() || '';
                            if (!v) return '';
                            const isActive = v === 'Active';
                            const color = isActive ? '#28a745' : '#dc3545';
                            const letter = isActive ? 'A' : 'I';
                            return `<span title="${v}" style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:${color};color:#fff;font-weight:bold;font-size:12px;">${letter}</span>`;
                        }
                    },
                    {
                        title: "Missing L", field: "missing_l", hozAlign: "center", headerSort: false,
                        formatter: function(cell) {
                            return neRowMissingL(cell.getRow().getData())
                                ? '<span style="color:#c0392b;font-weight:bold;">Missing L</span>'
                                : '';
                        }
                    },
                    {
                        title: "Map", field: "map_status", hozAlign: "center", headerSort: false,
                        formatter: function(cell) {
                            const st = neMapStatus(cell.getRow().getData());
                            if (st === 'map') return '<span style="color:#198754;font-weight:bold;">Map</span>';
                            if (st === 'nmap') return '<span style="color:#dc3545;font-weight:bold;">N Map</span>';
                            return '';
                        }
                    },
                    moneyCol("LP", "lp", false),
                    moneyCol("Ship", "ship", false),
                    { title: "Currency", field: "currency", visible: false }
                ]
            });

            // Floating image preview on thumbnail hover.
            const imgPreview = document.getElementById('ne-img-preview');
            const imgPreviewImg = imgPreview ? imgPreview.querySelector('img') : null;
            const tableEl = document.getElementById('newegg-pricing-table');

            function positionPreview(e) {
                const pad = 16;
                let x = e.clientX + pad;
                let y = e.clientY + pad;
                const w = imgPreview.offsetWidth || 326;
                const h = imgPreview.offsetHeight || 326;
                if (x + w > window.innerWidth) x = e.clientX - w - pad;
                if (y + h > window.innerHeight) y = window.innerHeight - h - pad;
                if (y < 0) y = pad;
                imgPreview.style.left = x + 'px';
                imgPreview.style.top = y + 'px';
            }

            if (tableEl && imgPreview && imgPreviewImg) {
                tableEl.addEventListener('mouseover', function(e) {
                    const thumb = e.target.closest('.ne-thumb');
                    if (!thumb) return;
                    imgPreviewImg.src = thumb.getAttribute('src');
                    imgPreview.style.display = 'block';
                    positionPreview(e);
                });
                tableEl.addEventListener('mousemove', function(e) {
                    if (imgPreview.style.display === 'block') positionPreview(e);
                });
                tableEl.addEventListener('mouseout', function(e) {
                    if (e.target.closest('.ne-thumb')) imgPreview.style.display = 'none';
                });
            }

            // Save SPRICE / NR on edit.
            table.on("cellEdited", function(cell) {
                const field = cell.getField();
                const row = cell.getRow();
                const data = row.getData();

                if (field === "sprice") {
                    fetch("{{ route('newegg.pricing.save.sprice') }}", {
                        method: "POST",
                        headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}" },
                        body: JSON.stringify({ sku: data.sku, sprice: cell.getValue() })
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            row.update({ spft: res.spft, sroi: res.sroi, sprice: res.sprice });
                        } else {
                            alert(res.error || "Failed to save SPrice");
                        }
                    })
                    .catch(() => alert("Failed to save SPrice"));
                }
            });

            // Open Buyer/Seller link modal by clicking the B/S cell.
            let bsModal = null;
            function openBsModal(d) {
                d = d || {};
                document.getElementById('bs-sku').value = d.sku || '';
                document.getElementById('bs-sku-label').textContent = d.sku || '';
                document.getElementById('bs-buyer-link').value = d.buyer_link || '';
                document.getElementById('bs-seller-link').value = d.seller_link || '';
                if (!bsModal) bsModal = new bootstrap.Modal(document.getElementById('bsLinkModal'));
                bsModal.show();
            }

            document.getElementById('bs-save-btn').addEventListener('click', function() {
                const sku = document.getElementById('bs-sku').value;
                const buyer = document.getElementById('bs-buyer-link').value.trim();
                const seller = document.getElementById('bs-seller-link').value.trim();
                fetch("{{ route('newegg.pricing.save.links') }}", {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}" },
                    body: JSON.stringify({ sku: sku, buyer_link: buyer, seller_link: seller })
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const rows = table.searchRows('sku', '=', sku);
                        if (rows.length) {
                            rows[0].update({ buyer_link: res.buyer_link, seller_link: res.seller_link })
                                .then(() => rows[0].reformat());
                        }
                        if (bsModal) bsModal.hide();
                    } else {
                        alert(res.error || "Failed to save links");
                    }
                })
                .catch(() => alert("Failed to save links"));
            });

            // Combined filter: SKU/Title search + INV / N Stock / NR / Status / PFT / ROI / DIL
            // dropdowns + active Missing L / Map / N Map badge.
            function applyNeFilters() {
                const search = ($('#sku-search').val() || '').trim().toLowerCase();
                table.setFilter(function(row) {
                    if (search) {
                        const sku = String(row.sku || '').toLowerCase();
                        const title = String(row.title || '').toLowerCase();
                        if (sku.indexOf(search) === -1 && title.indexOf(search) === -1) return false;
                    }

                    // INV (Shopify inventory)
                    const invVal = parseInt(row.inv) || 0;
                    if (inventoryFilter === 'zero' && invVal !== 0) return false;
                    if (inventoryFilter === 'more' && invVal <= 0) return false;

                    // N Stock (Newegg listing stock)
                    const nStock = parseInt(row.available_quantity) || 0;
                    if (nStockFilter === 'zero' && nStock !== 0) return false;
                    if (nStockFilter === 'more' && nStock <= 0) return false;

                    // NR / REQ flag
                    if (nrFilter !== 'all') {
                        const nr = String(row.nr || 'REQ').toUpperCase();
                        if (nrFilter === 'REQ' && nr !== 'REQ') return false;
                        if (nrFilter === 'NR'  && nr !== 'NR')  return false;
                    }

                    // Listing status
                    if (statusFilter !== 'all') {
                        const st = String(row.status || '');
                        if (st !== statusFilter) return false;
                    }

                    // PFT / ROI / DIL bucket filters
                    if (!pftMatches(row.pft_pct, pftFilter)) return false;
                    if (!roiMatches(row.roi,     roiFilter)) return false;
                    if (!dilMatches(row.dil,     dilFilter)) return false;

                    // Missing / Map / N Map badge filters
                    if (neMissingActive && !neRowMissingL(row)) return false;
                    if (neMapActive && neMapStatus(row) !== 'map') return false;
                    if (neNMapActive && neMapStatus(row) !== 'nmap') return false;

                    return true;
                });
                updateBadgeStyles();
                setTimeout(updateSummary, 100);
            }

            function updateBadgeStyles() {
                $('#ne-missing-badge').css('outline', neMissingActive ? '3px solid #000' : 'none');
                $('#ne-map-badge').css('outline', neMapActive ? '3px solid #000' : 'none');
                $('#ne-nmap-badge').css('outline', neNMapActive ? '3px solid #000' : 'none');
            }

            $('#sku-search').on('keyup', applyNeFilters);

            $('#ne-missing-badge').on('click', function() {
                neMissingActive = !neMissingActive;
                neMapActive = neNMapActive = false;
                applyNeFilters();
            });
            $('#ne-map-badge').on('click', function() {
                neMapActive = !neMapActive;
                neMissingActive = neNMapActive = false;
                applyNeFilters();
            });
            $('#ne-nmap-badge').on('click', function() {
                neNMapActive = !neNMapActive;
                neMissingActive = neMapActive = false;
                applyNeFilters();
            });

            // ── Toolbar filter wiring ──────────────────────────────────────────
            $('#inventory-filter').on('change', function() { inventoryFilter = $(this).val(); applyNeFilters(); });
            $('#n-stock-filter')  .on('change', function() { nStockFilter    = $(this).val(); applyNeFilters(); });
            $('#nr-filter')       .on('change', function() { nrFilter        = $(this).val(); applyNeFilters(); });
            $('#status-filter')   .on('change', function() { statusFilter    = $(this).val(); applyNeFilters(); });
            $('#pft-filter')      .on('change', function() { pftFilter       = $(this).val(); applyNeFilters(); });
            $('#roi-filter')      .on('change', function() { roiFilter       = $(this).val(); applyNeFilters(); });

            // DIL% manual dropdown (colored pill button + 5 color options).
            $(document).on('click', '#dilFilterContainer .btn', function(e) {
                e.stopPropagation();
                $('.manual-dropdown-container').not('#dilFilterContainer').removeClass('show');
                $('#dilFilterContainer').toggleClass('show');
            });
            $(document).on('click', '.dil-filter-item', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const $item = $(this);
                const color = $item.data('color');
                $('#dilFilterContainer .dil-filter-item').removeClass('active');
                $item.addClass('active');
                const circle = $item.find('.status-circle').clone();
                $('#dilFilterDropdown').html('').append(circle).append(' DIL%');
                $('#dilFilterContainer').removeClass('show');
                dilFilter = color;
                applyNeFilters();
            });
            $(document).on('click', function() { $('.manual-dropdown-container').removeClass('show'); });

            // ── SPRICE bulk tools (Increase / Decrease / Same Price) ──────────
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

            // Swap the input panel between %/$ entry (Increase/Decrease) and a flat $ price (Same Price).
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

            function updateSelectedCount() {
                const count = selectedSkus.size;
                $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
                $('#discount-input-container').toggle(count > 0);
            }

            function updateSelectAllCheckbox() {
                if (!table) return;
                const visible = table.getData('active').filter(r => r.sku);
                if (visible.length === 0) { $('#select-all-checkbox').prop('checked', false); return; }
                const allSelected = visible.every(r => selectedSkus.has(r.sku));
                $('#select-all-checkbox').prop('checked', allSelected);
            }

            function enterMode(which) {
                decreaseModeActive  = (which === 'decrease') ? !decreaseModeActive  : false;
                increaseModeActive  = (which === 'increase') ? !increaseModeActive  : false;
                samePriceModeActive = (which === 'same')     ? !samePriceModeActive : false;

                resetDecreaseBtn(); resetIncreaseBtn(); resetSamePriceBtn();
                const anyOn = decreaseModeActive || increaseModeActive || samePriceModeActive;

                if (decreaseModeActive) {
                    $('#decrease-btn').removeClass('btn-warning').addClass('btn-danger')
                        .html('<i class="fas fa-arrow-down"></i> Decrease ON');
                } else if (increaseModeActive) {
                    $('#increase-btn').removeClass('btn-success').addClass('btn-danger')
                        .html('<i class="fas fa-arrow-up"></i> Increase ON');
                } else if (samePriceModeActive) {
                    $('#same-price-btn').removeClass('btn-info').addClass('btn-danger')
                        .html('<i class="fas fa-equals"></i> Same Price ON');
                }

                const selCol = table.getColumn('_select');
                if (selCol) {
                    if (anyOn) {
                        selCol.show();
                    } else {
                        selCol.hide();
                        selectedSkus.clear();
                        updateSelectedCount();
                    }
                }
                syncDiscountInputUi();
                table.redraw(true);
            }

            $('#decrease-btn').on('click',  () => enterMode('decrease'));
            $('#increase-btn').on('click',  () => enterMode('increase'));
            $('#same-price-btn').on('click', () => enterMode('same'));
            $('#discount-type-select').on('change', syncDiscountInputUi);

            // Header "select all" — selects every currently-visible SKU.
            $(document).on('change', '#select-all-checkbox', function() {
                const checked = $(this).prop('checked');
                table.getData('active').forEach(r => {
                    if (!r.sku) return;
                    if (checked) selectedSkus.add(r.sku); else selectedSkus.delete(r.sku);
                });
                table.redraw(true);
                updateSelectedCount();
            });

            // Per-row checkbox.
            $(document).on('change', '.sku-select-checkbox', function() {
                const sku = $(this).data('sku');
                if ($(this).prop('checked')) selectedSkus.add(sku); else selectedSkus.delete(sku);
                updateSelectedCount();
                updateSelectAllCheckbox();
            });

            // Apply on click / Enter.
            $('#apply-discount-btn').on('click', applyDiscount);
            $('#discount-percentage-input').on('keypress', function(e) { if (e.which === 13) applyDiscount(); });
            $('#clear-sprice-btn').on('click', clearSpriceForSelected);
            $('#push-newegg-btn').on('click', pushSelectedToNewegg);

            // Compute and apply Increase / Decrease / Same Price to the selected SKUs.
            function applyDiscount() {
                if (!decreaseModeActive && !increaseModeActive && !samePriceModeActive) {
                    showToast('Turn on Decrease, Increase, or Same Price mode first', 'error');
                    return;
                }
                const discountType  = $('#discount-type-select').val();
                const discountValue = parseFloat($('#discount-percentage-input').val());
                if (isNaN(discountValue) || discountValue <= 0) {
                    showToast(samePriceModeActive ? 'Please enter a price (e.g. 19.99)' : 'Please enter a valid value', 'error');
                    return;
                }
                if (selectedSkus.size === 0) {
                    showToast('Please select at least one SKU', 'error');
                    return;
                }

                let updatedCount = 0;
                const updates = [];

                selectedSkus.forEach(sku => {
                    const rows = table.searchRows('sku', '=', sku);
                    if (rows.length === 0) return;
                    const row = rows[0];
                    const d   = row.getData();
                    const currentPrice = parseFloat(d.price) || 0;

                    // %/$ modes need a positive Newegg price to compute against;
                    // Same Price mode works regardless of current Newegg price.
                    if (!samePriceModeActive && !(currentPrice > 0)) return;

                    let newSprice;
                    if (samePriceModeActive) {
                        newSprice = discountValue;
                    } else if (discountType === 'percentage') {
                        newSprice = increaseModeActive
                            ? currentPrice * (1 + discountValue / 100)
                            : currentPrice * (1 - discountValue / 100);
                    } else {
                        newSprice = increaseModeActive
                            ? currentPrice + discountValue
                            : currentPrice - discountValue;
                    }
                    newSprice = Math.max(0.99, roundToRetailPrice(newSprice));

                    // Optimistic SPFT / SROI using the row's server-provided factor (~0.80 by default).
                    const factor = parseFloat(d.factor) || 0.80;
                    const lp     = parseFloat(d.lp)     || 0;
                    const ship   = parseFloat(d.ship)   || 0;
                    const profit = (newSprice * factor) - lp - ship;
                    const spft   = newSprice > 0 ? Math.round((profit / newSprice) * 100 * 10) / 10 : 0;
                    const sroi   = lp > 0 ? Math.round((profit / lp) * 100) : 0;

                    row.update({ sprice: newSprice, spft: spft, sroi: sroi });
                    updates.push({ sku: sku, sprice: newSprice });
                    updatedCount++;
                });

                if (updates.length > 0) saveSpriceUpdates(updates);

                const action = samePriceModeActive ? 'Same Price'
                    : (increaseModeActive ? 'Increase' : 'Decrease');
                const suffix = samePriceModeActive ? '' : ' based on Newegg Price';
                showToast(`${action} applied to ${updatedCount} SKU(s)${suffix}`, 'success');
                $('#discount-percentage-input').val('');
            }

            function clearSpriceForSelected() {
                if (selectedSkus.size === 0) { showToast('Please select SKUs first', 'error'); return; }
                if (!confirm(`Clear SPRICE for ${selectedSkus.size} selected SKU(s)?`)) return;

                const updates = [];
                selectedSkus.forEach(sku => {
                    const rows = table.searchRows('sku', '=', sku);
                    if (rows.length === 0) return;
                    rows[0].update({ sprice: null, spft: null, sroi: null });
                    updates.push({ sku: sku, sprice: null });
                });
                if (updates.length > 0) saveSpriceUpdates(updates);
                showToast(`SPRICE cleared for ${updates.length} SKU(s)`, 'success');
            }

            // Live-push each selected SKU's SPRICE (or current Newegg price as fallback)
            // to the Newegg Marketplace API. Mirrors the reverb:push-price flow but
            // batched: one HTTP request → one Newegg PUT covers every selected SKU.
            function pushSelectedToNewegg() {
                if (selectedSkus.size === 0) { showToast('Please select SKUs first', 'error'); return; }

                const updates = [];
                const skipped = [];
                selectedSkus.forEach(sku => {
                    const rows = table.searchRows('sku', '=', sku);
                    if (rows.length === 0) return;
                    const d = rows[0].getData();
                    // Prefer SPRICE (user-entered); fall back to live Newegg price.
                    const price = parseFloat(d.sprice) > 0 ? parseFloat(d.sprice)
                                : (parseFloat(d.price) > 0 ? parseFloat(d.price) : 0);
                    if (price <= 0) { skipped.push(sku); return; }
                    updates.push({ sku: sku, price: +price.toFixed(2) });
                });

                if (updates.length === 0) {
                    showToast('No selected SKU has a positive SPRICE or Price to push', 'error');
                    return;
                }

                const summary = `Push ${updates.length} price${updates.length !== 1 ? 's' : ''} live to Newegg?`
                    + (skipped.length ? `\n(${skipped.length} skipped — no SPRICE/Price)` : '');
                if (!confirm(summary)) return;

                const $btn = $('#push-newegg-btn');
                const origHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Pushing...');

                $.ajax({
                    url: "{{ route('newegg.pricing.push') }}",
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    data: { updates: updates },
                    success: function(res) {
                        const pushed = res.pushed || 0;
                        const failed = res.failed || 0;
                        const fails  = (res.results || []).filter(r => !r.success);

                        if (pushed > 0) {
                            showToast(`Pushed ${pushed} price${pushed !== 1 ? 's' : ''} to Newegg` +
                                (failed > 0 ? ` (${failed} failed)` : ''), failed > 0 ? 'warning' : 'success');
                            // Reflect new live price in the Price column for pushed rows.
                            (res.results || []).filter(r => r.success).forEach(r => {
                                const rows = table.searchRows('sku', '=', r.sku);
                                if (rows.length) rows[0].update({ price: r.price });
                            });
                        }
                        if (fails.length) {
                            console.warn('Newegg push failures:', fails);
                            const first = fails.slice(0, 3).map(f => `• ${f.sku}: ${f.error}`).join('\n');
                            const more  = fails.length > 3 ? `\n…and ${fails.length - 3} more (see console)` : '';
                            showToast(`Failed:\n${first}${more}`, 'error');
                        }
                        if (!res.success && pushed === 0 && fails.length === 0) {
                            showToast(res.error || 'Push failed', 'error');
                        }
                    },
                    error: function(xhr) {
                        const r = xhr.responseJSON || {};
                        const msg = r.error || `Push failed (HTTP ${xhr.status})`;
                        showToast(msg, 'error');
                        if (r.results) console.warn('Newegg push results:', r.results);
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(origHtml);
                    }
                });
            }

            // Bulk save through one HTTP request (mirrors reverb-save-sprice pattern).
            function saveSpriceUpdates(updates) {
                $.ajax({
                    url: "{{ route('newegg.pricing.save.sprice.bulk') }}",
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    data: { updates: updates },
                    success: function(res) {
                        if (!res.success) {
                            showToast(res.error || 'Failed to save SPRICE updates', 'error');
                            return;
                        }
                        // Reconcile each row with the server's authoritative SPFT/SROI/SPRICE.
                        (res.results || []).forEach(r => {
                            const rows = table.searchRows('sku', '=', r.sku);
                            if (rows.length) {
                                rows[0].update({
                                    sprice: r.sprice,
                                    spft:   r.spft,
                                    sroi:   r.sroi,
                                });
                            }
                        });
                        if (res.errors && res.errors.length) {
                            console.warn('Newegg SPRICE bulk save partial errors:', res.errors);
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Error saving SPRICE updates';
                        showToast(msg, 'error');
                    }
                });
            }

            function updateSummary() {
                const data = table.getData("active");
                let totalSkus = 0, withPrice = 0, totalInv = 0, totalOvl30 = 0, totalL30 = 0;
                let totalWeightedPrice = 0, priceCount = 0;
                // Overall PFT/ROI accumulators (over L30), same approach as amazon-tabulator-view.
                let totalPftAmt = 0, totalSalesAmt = 0, totalCogsAmt = 0;

                data.forEach(row => {
                    if (!row.sku) return;
                    totalSkus++;
                    totalInv += parseInt(row.inv) || 0;
                    totalOvl30 += parseInt(row.ovl30) || 0;
                    const l30 = parseInt(row.l30) || 0;
                    totalL30 += l30;
                    const price = parseFloat(row.price);
                    if (!isNaN(price) && price > 0) {
                        withPrice++;
                        totalWeightedPrice += price;
                        priceCount++;

                        // PFT/ROI weighted by units sold (L30).
                        const pftEach = parseFloat(row.pft) || 0;
                        const lp = parseFloat(row.lp) || 0;
                        totalPftAmt += pftEach * l30;
                        totalSalesAmt += price * l30;
                        totalCogsAmt += lp * l30;
                    }
                });

                const avgPrice = priceCount > 0 ? totalWeightedPrice / priceCount : 0;
                // Overall PFT% = total profit / total sales; ROI% = total profit / total COGS.
                const pftPct = totalSalesAmt > 0 ? (totalPftAmt / totalSalesAmt) * 100 : 0;
                const roiPct = totalCogsAmt > 0 ? (totalPftAmt / totalCogsAmt) * 100 : 0;

                $('#total-l30-badge').text('Total L30: ' + totalL30.toLocaleString());
                $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
                $('#pft-badge').text('PFT: ' + Math.round(pftPct) + '%');
                $('#roi-badge').text('ROI: ' + Math.round(roiPct) + '%');

                // Missing L / Map / N Map counted over the full dataset (stable regardless of active filter).
                let missingCount = 0, mapCount = 0, nmapCount = 0;
                table.getData().forEach(row => {
                    if (!row.sku) return;
                    if (neRowMissingL(row)) {
                        missingCount++;
                    } else {
                        const st = neMapStatus(row);
                        if (st === 'map') mapCount++;
                        else if (st === 'nmap') nmapCount++;
                    }
                });
                $('#ne-missing-badge').text('Missing L: ' + missingCount.toLocaleString());
                $('#ne-map-badge').text('Map: ' + mapCount.toLocaleString());
                $('#ne-nmap-badge').text('N Map: ' + nmapCount.toLocaleString());
            }

            const COL_URL = '/newegg-pricing-column-visibility';

            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-menu");
                menu.innerHTML = '';
                fetch(COL_URL, { headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                    .then(r => r.json())
                    .then(savedVisibility => {
                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            if (!def.field) return;
                            // Internal toolbar columns (selection checkbox) are not user-toggleable.
                            if (def.field === '_select') return;
                            const li = document.createElement("li");
                            const label = document.createElement("label");
                            label.style.cssText = "display:block;padding:5px 10px;cursor:pointer;";
                            const checkbox = document.createElement("input");
                            checkbox.type = "checkbox";
                            checkbox.value = def.field;
                            checkbox.checked = savedVisibility[def.field] !== false;
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
                    // _select is controlled by the toolbar mode buttons, not user preference.
                    if (!def.field || def.field === '_select') return;
                    visibility[def.field] = col.isVisible();
                });
                fetch(COL_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ visibility })
                });
            }

            function applyColumnVisibilityFromServer() {
                fetch(COL_URL, { headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
                    .then(r => r.json())
                    .then(savedVisibility => {
                        table.getColumns().forEach(col => {
                            const def = col.getDefinition();
                            if (!def.field) return;
                            if (def.field === '_select') return; // toolbar-controlled
                            if (savedVisibility[def.field] === false) col.hide();
                        });
                    });
            }

            table.on('tableBuilt', function() {
                applyColumnVisibilityFromServer();
                buildColumnDropdown();
                // Make sure the initial INV filter ("More than 0") is applied.
                applyNeFilters();
            });
            table.on('dataLoaded', updateSummary);
            table.on('dataProcessed', updateSummary);
            table.on('dataFiltered', function() { updateSummary(); updateSelectAllCheckbox(); });
            table.on('renderComplete', updateSelectAllCheckbox);

            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.type === 'checkbox') {
                    const col = table.getColumn(e.target.value);
                    if (e.target.checked) col.show(); else col.hide();
                    saveColumnVisibilityToServer();
                }
            });

            document.getElementById("show-all-columns-btn").addEventListener("click", function() {
                table.getColumns().forEach(col => {
                    const def = col.getDefinition();
                    if (def.field === '_select') return; // toolbar-controlled, leave hidden
                    col.show();
                });
                buildColumnDropdown();
                saveColumnVisibilityToServer();
            });

            $('#export-btn').on('click', function() {
                table.download("csv", "newegg_pricing.csv");
            });
        });
    </script>
@endsection
