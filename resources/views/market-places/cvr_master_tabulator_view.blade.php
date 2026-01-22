@extends('layouts.vertical', ['title' => 'CVR Master', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

        /* Custom pagination label */
        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* ========== STATUS INDICATORS ========== */
        .status-circle {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            border: 1px solid #ddd;
        }

        .status-circle.default {
            background-color: #6c757d;
        }

        .status-circle.red {
            background-color: #dc3545;
        }

        .status-circle.yellow {
            background-color: #ffc107;
        }

        .status-circle.blue {
            background-color: #3591dc;
        }

        .status-circle.green {
            background-color: #28a745;
        }

        .status-circle.pink {
            background-color: #e83e8c;
        }

        /* ========== DROPDOWN STYLING ========== */
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
        }

        .manual-dropdown-container.show .dropdown-menu {
            display: block;
        }

        .dropdown-item {
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

        .dropdown-item:hover {
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
        'page_title' => 'CVR Master',
        'sub_title' => 'CVR Master Data',
    ])
    <div class="toast-container"></div>
    
    <!-- OV L30 Details Modal -->
    <div class="modal fade" id="ovl30DetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #17a2b8;">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-mouse-pointer me-2"></i> 
                        <span id="modalSkuName">SKU</span> - Advertising Breakdown
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead style="background-color: #17a2b8; color: white;">
                                <tr>
                                    <th>M</th>
                                    <th>SKU</th>
                                    <th class="text-end">Price</th>
                                    <th class="text-end">Views</th>
                                    <th class="text-end">L30</th>
                                    <th class="text-end">CVR%</th>
                                    <th class="text-end">GPFT%</th>
                                    <th class="text-end">NPFT%</th>
                                </tr>
                            </thead>
                            <tbody id="ovl30DetailsTableBody">
                                <!-- Table rows will be populated dynamically -->
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No data available</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-secondary">
                                <tr>
                                    <th>Total</th>
                                    <th></th>
                                    <th class="text-end" id="modal-total-price">$0.00</th>
                                    <th class="text-end" id="modal-total-views">0</th>
                                    <th class="text-end" id="modal-total-l30">0</th>
                                    <th class="text-end" id="modal-avg-cvr">0%</th>
                                    <th class="text-end" id="modal-avg-gpft">0%</th>
                                    <th class="text-end" id="modal-avg-npft">0%</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>CVR Master Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: 130px;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <!-- DIL Filter (Walmart-style dropdown) -->
                    <div class="dropdown manual-dropdown-container">
                        <button class="btn btn-light dropdown-toggle" type="button" id="dilFilterDropdown">
                            <span class="status-circle default"></span> DIL%
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dilFilterDropdown">
                            <li><a class="dropdown-item column-filter active" href="#" data-column="dil_percent" data-color="all">
                                    <span class="status-circle default"></span> All DIL</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="red">
                                    <span class="status-circle red"></span> Red (&lt;16.7%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="yellow">
                                    <span class="status-circle yellow"></span> Yellow (16.7-25%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="green">
                                    <span class="status-circle green"></span> Green (25-50%)</a></li>
                            <li><a class="dropdown-item column-filter" href="#" data-column="dil_percent" data-color="pink">
                                    <span class="status-circle pink"></span> Pink (50%+)</a></li>
                        </ul>
                    </div>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                            <!-- Columns will be populated by JavaScript -->
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <button id="export-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-file-excel"></i> Export CSV
                    </button>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-items-badge" style="color: white; font-weight: bold;">Total Items: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-inv-badge" style="color: white; font-weight: bold;">Total INV: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-l30-badge" style="color: black; font-weight: bold;">Total OV L30: 0</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-dil-badge" style="color: black; font-weight: bold;">AVG DIL%: 0%</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="cvr-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control" placeholder="Search SKU...">
                    </div>
                    <!-- Table body -->
                    <div id="cvr-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    /**
     * ========================================
     * CVR MASTER - TABULATOR VIEW
     * ========================================
     * 
     * FEATURES:
     * - Display: Image, SKU, INV, OV L30, DIL%
     * - Color-coded DIL% (Red < 16.7%, Yellow 16.7-25%, Green 25-50%, Pink 50%+)
     * - SKU-wise breakdown modal (click info icon on OV L30)
     * - Filters: Inventory, DIL%
     * - Export to CSV
     * 
     * BACKEND ENDPOINTS:
     * 1. GET /cvr-master-data-json - Main table data
     * 2. GET /cvr-master-breakdown/{sku} - Modal breakdown data
     * 3. GET/POST /cvr-master-column-visibility - Column visibility
     * ========================================
     */

    let table = null;
    
    // ==================== UTILITY FUNCTIONS ====================
    
    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
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
        
        // ==================== MODAL FUNCTIONS ====================
        
        // OV L30 Info Icon Click Handler (SKU-wise)
        $(document).on('click', '.ovl30-info-icon', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            loadMarketplaceBreakdown(sku);
        });

        function loadMarketplaceBreakdown(sku) {
            $('#modalSkuName').text(sku);
            showModalLoading(sku);
            
            const modal = new bootstrap.Modal(document.getElementById('ovl30DetailsModal'));
            modal.show();
            
            // Fetch Amazon data from backend
            $.ajax({
                url: `/cvr-master-breakdown/${sku}`,
                method: 'GET',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(data) { renderMarketplaceData(data); },
                error: function(xhr) { showModalError('Failed to load data'); }
            });
        }

        function showModalLoading(sku) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm text-info me-2" role="status"></div>
                        Loading data for ${sku}...
                    </td>
                </tr>
            `);
        }

        function showModalEmpty(sku) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No marketplace data available for ${sku}
                    </td>
                </tr>
            `);
        }

        function showModalError(message) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="8" class="text-center text-danger py-4">
                        <i class="fas fa-exclamation-circle me-2"></i>${message}
                    </td>
                </tr>
            `);
        }

        function renderMarketplaceData(data) {
            if (!data || data.length === 0) {
                showModalEmpty($('#modalSkuName').text());
                return;
            }

            let html = '';
            let totalPrice = 0;
            let totalViews = 0;
            let totalL30 = 0;
            let totalCVR = 0;
            let totalGPFT = 0;
            let totalNPFT = 0;
            let cvrCount = 0;
            let gpftCount = 0;
            let npftCount = 0;
            
            data.forEach(item => {
                const isListed = item.is_listed !== false;
                const rowClass = !isListed ? 'table-secondary' : '';
                const textClass = !isListed ? 'text-muted fst-italic' : '';
                
                // Calculate CVR% (L30 / Views * 100)
                const views = parseInt(item.views || 0);
                const l30 = parseInt(item.l30 || 0);
                const cvr = views > 0 ? (l30 / views) * 100 : 0;
                const gpft = parseFloat(item.gpft || 0);
                const npft = parseFloat(item.npft || 0);
                
                // Color coding for CVR%
                let cvrColor = '';
                if (cvr < 1) cvrColor = '#a00211'; // Dark red
                else if (cvr >= 1 && cvr < 3) cvrColor = '#ffc107'; // Yellow
                else if (cvr >= 3 && cvr < 5) cvrColor = '#28a745'; // Green
                else cvrColor = '#e83e8c'; // Pink
                
                // Color coding for GPFT% and NPFT%
                let gpftColor = '';
                let npftColor = '';
                
                if (gpft < 0) gpftColor = '#a00211'; // Dark red for negative
                else if (gpft >= 0 && gpft < 10) gpftColor = '#ffc107'; // Yellow
                else if (gpft >= 10 && gpft < 20) gpftColor = '#3591dc'; // Blue
                else if (gpft >= 20 && gpft <= 40) gpftColor = '#28a745'; // Green
                else gpftColor = '#e83e8c'; // Pink
                
                if (npft < 0) npftColor = '#a00211'; // Dark red for negative
                else if (npft >= 0 && npft < 10) npftColor = '#ffc107'; // Yellow
                else if (npft >= 10 && npft < 20) npftColor = '#3591dc'; // Blue
                else if (npft >= 20 && npft <= 40) npftColor = '#28a745'; // Green
                else npftColor = '#e83e8c'; // Pink
                
                // Add to totals only if listed
                if (isListed) {
                    totalPrice += parseFloat(item.price || 0);
                    totalViews += views;
                    totalL30 += l30;
                    if (cvr > 0) {
                        totalCVR += cvr;
                        cvrCount++;
                    }
                    if (gpft !== 0) {
                        totalGPFT += gpft;
                        gpftCount++;
                    }
                    if (npft !== 0) {
                        totalNPFT += npft;
                        npftCount++;
                    }
                }
                
                html += `
                    <tr class="${rowClass}">
                        <td class="${textClass}">${item.marketplace || '-'}</td>
                        <td class="${textClass}">${item.sku || '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? '$' + parseFloat(item.price || 0).toFixed(2) : '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? views.toLocaleString() : '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? l30 : '-'}</td>
                        <td class="text-end ${textClass}">${isListed && views > 0 ? '<span style="color: ' + cvrColor + '; font-weight: 600;">' + cvr.toFixed(1) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">${isListed && gpft !== 0 ? '<span style="color: ' + gpftColor + '; font-weight: 600;">' + Math.round(gpft) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">${isListed && npft !== 0 ? '<span style="color: ' + npftColor + '; font-weight: 600;">' + Math.round(npft) + '%</span>' : '-'}</td>
                    </tr>
                `;
            });
            
            $('#ovl30DetailsTableBody').html(html);
            
            // Calculate averages
            const avgCVR = cvrCount > 0 ? totalCVR / cvrCount : 0;
            const avgGPFT = gpftCount > 0 ? totalGPFT / gpftCount : 0;
            const avgNPFT = npftCount > 0 ? totalNPFT / npftCount : 0;
            
            // Update totals in footer
            $('#modal-total-price').text('$' + totalPrice.toFixed(2));
            $('#modal-total-views').text(totalViews.toLocaleString());
            $('#modal-total-l30').text(totalL30.toLocaleString());
            $('#modal-avg-cvr').text(avgCVR.toFixed(2) + '%');
            $('#modal-avg-gpft').text(avgGPFT.toFixed(1) + '%');
            $('#modal-avg-npft').text(avgNPFT.toFixed(1) + '%');
        }

        // ==================== TABULATOR INITIALIZATION ====================
        
        table = new Tabulator("#cvr-table", {
            ajaxURL: "/cvr-master-data-json",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            columnCalcs: "both",
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "SKU Count"
                    }
                }
            },
            initialSort: [{
                column: "dil_percent",
                dir: "desc"
            }],
            columns: [
                {
                    title: "Image",
                    field: "image_path",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value) {
                            return `<img src="${value}" alt="Product" style="width: 50px; height: 50px; object-fit: cover;">`;
                        }
                        return '';
                    },
                    headerSort: false,
                    width: 80
                },
                {
                    title: "SKU",
                    field: "sku",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    cssClass: "text-primary fw-bold",
                    tooltip: true,
                    frozen: true,
                    width: 250,
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        let html = `<span>${sku}</span>`;
                        html += `<i class="fa fa-copy text-secondary copy-sku-btn" 
                                   style="cursor: pointer; margin-left: 8px; font-size: 14px;" 
                                   data-sku="${sku}" title="Copy SKU"></i>`;
                        return html;
                    }
                },
                {
                    title: "INV",
                    field: "inventory",
                    hozAlign: "center",
                    width: 80,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        if (value === 0) {
                            return '<span style="color: #dc3545; font-weight: 600;">0</span>';
                        }
                        return `<span style="font-weight: 600;">${value}</span>`;
                    }
                },
                {
                    title: "OV L30",
                    field: "overall_l30",
                    hozAlign: "center",
                    width: 100,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku;
                        return `
                            <span style="font-weight: 600;">${value}</span>
                            <i class="fas fa-info-circle text-info ovl30-info-icon" 
                               style="cursor: pointer; font-size: 12px; margin-left: 6px;" 
                               data-sku="${sku}"
                               title="View breakdown for ${sku}"></i>
                        `;
                    }
                },
                {
                    title: "Dil",
                    field: "dil_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';
                        
                        if (value === 0) color = '#6c757d';
                        else if (value < 16.7) color = '#a00211';
                        else if (value >= 16.7 && value < 25) color = '#ffc107';
                        else if (value >= 25 && value < 50) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}%</span>`;
                    },
                    width: 80
                }
            ]
        });

        // ==================== TABLE EVENT HANDLERS ====================
        
        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("sku", "like", value);
        });

        $(document).on('click', '.copy-sku-btn', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            navigator.clipboard.writeText(sku).then(() => {
                showToast(`Copied: ${sku}`, 'success');
            });
        });

        // ==================== FILTER FUNCTIONS ====================
        
        $(document).on('click', '.manual-dropdown-container .btn', function(e) {
            e.stopPropagation();
            const container = $(this).closest('.manual-dropdown-container');
            $('.manual-dropdown-container').not(container).removeClass('show');
            container.toggleClass('show');
        });

        $(document).on('click', '.column-filter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $item = $(this);
            const container = $item.closest('.manual-dropdown-container');
            const button = container.find('.btn');
            
            container.find('.column-filter').removeClass('active');
            $item.addClass('active');
            
            const statusCircle = $item.find('.status-circle').clone();
            button.html('').append(statusCircle).append(' DIL%');
            container.removeClass('show');
            
            applyFilters();
        });

        $(document).on('click', function() {
            $('.manual-dropdown-container').removeClass('show');
        });

        function applyFilters() {
            const inventoryFilter = $('#inventory-filter').val();
            const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';

            table.clearFilter();

            if (inventoryFilter === 'zero') {
                table.addFilter("inventory", "=", 0);
            } else if (inventoryFilter === 'more') {
                table.addFilter("inventory", ">", 0);
            }

            if (dilFilter !== 'all') {
                table.addFilter(function(data) {
                    const inv = parseFloat(data['inventory']) || 0;
                    const l30 = parseFloat(data['overall_l30']) || 0;
                    const dil = inv === 0 ? 0 : (l30 / inv) * 100;
                    
                    if (dilFilter === 'red') return dil < 16.7;
                    if (dilFilter === 'yellow') return dil >= 16.7 && dil < 25;
                    if (dilFilter === 'green') return dil >= 25 && dil < 50;
                    if (dilFilter === 'pink') return dil >= 50;
                    return true;
                });
            }

            updateSummary();
        }

        $('#inventory-filter').on('change', function() {
            applyFilters();
        });

        // ==================== SUMMARY FUNCTIONS ====================
        
        function updateSummary() {
            const data = table.getData('active');
            let totalInv = 0, totalL30 = 0, totalDil = 0, dilCount = 0;

            data.forEach(row => {
                totalInv += parseFloat(row['inventory']) || 0;
                totalL30 += parseFloat(row['overall_l30']) || 0;
                const dil = parseFloat(row['dil_percent']) || 0;
                if (dil > 0) {
                    totalDil += dil;
                    dilCount++;
                }
            });

            const avgDil = dilCount > 0 ? totalDil / dilCount : 0;

            $('#total-items-badge').text(`Total Items: ${data.length.toLocaleString()}`);
            $('#total-inv-badge').text(`Total INV: ${totalInv.toLocaleString()}`);
            $('#total-l30-badge').text(`Total OV L30: ${totalL30.toLocaleString()}`);
            $('#avg-dil-badge').text(`AVG DIL%: ${avgDil.toFixed(1)}%`);
        }

        // ==================== COLUMN VISIBILITY FUNCTIONS ====================
        
        function buildColumnDropdown() {
            const columns = table.getColumns();
            let html = '';
            
            columns.forEach(col => {
                const field = col.getField();
                const title = col.getDefinition().title;
                if (field && title) {
                    const isVisible = col.isVisible();
                    html += `<li class="dropdown-item">
                        <label style="cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" class="column-toggle" data-field="${field}" ${isVisible ? 'checked' : ''}>
                            ${title.replace(/<[^>]*>/g, '')}
                        </label>
                    </li>`;
                }
            });
            
            $('#column-dropdown-menu').html(html);
        }

        function saveColumnVisibilityToServer() {
            const visibility = {};
            table.getColumns().forEach(col => {
                const field = col.getField();
                if (field) visibility[field] = col.isVisible();
            });
            
            $.ajax({
                url: '/cvr-master-column-visibility',
                method: 'POST',
                data: { visibility: visibility, _token: '{{ csrf_token() }}' }
            });
        }

        function applyColumnVisibilityFromServer() {
            $.ajax({
                url: '/cvr-master-column-visibility',
                method: 'GET',
                success: function(visibility) {
                    if (visibility && Object.keys(visibility).length > 0) {
                        Object.keys(visibility).forEach(field => {
                            const col = table.getColumn(field);
                            if (col) {
                                visibility[field] ? col.show() : col.hide();
                            }
                        });
                        buildColumnDropdown();
                    }
                }
            });
        }

        // ==================== TABLE EVENTS ====================
        
        table.on('tableBuilt', function() {
            buildColumnDropdown();
            applyColumnVisibilityFromServer();
        });

        table.on('dataLoaded', function() {
            setTimeout(function() {
                applyFilters();
                updateSummary();
            }, 100);
        });

        table.on('renderComplete', function() {
            setTimeout(() => updateSummary(), 100);
        });

        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.classList.contains('column-toggle')) {
                const field = e.target.dataset.field;
                const col = table.getColumn(field);
                if (col) {
                    e.target.checked ? col.show() : col.hide();
                    saveColumnVisibilityToServer();
                }
            }
        });

        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => col.show());
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        // ==================== EXPORT FUNCTIONS ====================
        
        $('#export-btn').on('click', function() {
            const exportData = [];
            const visibleColumns = table.getColumns().filter(col => col.isVisible());
            
            const headers = visibleColumns.map(col => {
                let title = col.getDefinition().title || col.getField();
                return title.replace(/<[^>]*>/g, '');
            });
            exportData.push(headers);
            
            const data = table.getData("active");
            data.forEach(row => {
                const rowData = [];
                visibleColumns.forEach(col => {
                    const field = col.getField();
                    let value = row[field];
                    
                    if (value === null || value === undefined) value = '';
                    else if (typeof value === 'number') value = parseFloat(value.toFixed(2));
                    else if (typeof value === 'string') value = value.replace(/<[^>]*>/g, '').trim();
                    
                    rowData.push(value);
                });
                exportData.push(rowData);
            });
            
            let csv = '';
            exportData.forEach(row => {
                csv += row.map(cell => {
                    if (typeof cell === 'string' && (cell.includes(',') || cell.includes('"') || cell.includes('\n'))) {
                        return '"' + cell.replace(/"/g, '""') + '"';
                    }
                    return cell;
                }).join(',') + '\n';
            });
            
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'cvr_master_export_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showToast('Export downloaded successfully!', 'success');
        });
    });
</script>
@endsection
