@extends('layouts.vertical', ['title' => 'Pricing Master ', 'sidenav' => 'condensed'])

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

        /* Vertical headers for modal table */
        #ovl30DetailsModal .modal-vertical-header th {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 80px;
            vertical-align: middle;
            font-size: 11px;
            font-weight: 600;
            padding: 5px;
        }
        
        /* Exception for M and SKU columns - keep them horizontal */
        #ovl30DetailsModal .modal-vertical-header th:nth-child(1),
        #ovl30DetailsModal .modal-vertical-header th:nth-child(2) {
            writing-mode: horizontal-tb;
            transform: none;
            height: auto;
            min-height: 80px;
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
        
        /* Totals row styling - bold text with dark background */
        #ovl30DetailsModal .modal-totals-row {
            background-color: #172426d1 !important;
            font-weight: bold !important;
            color: #000 !important;
        }
        
        #ovl30DetailsModal .modal-totals-row th {
            font-weight: bold !important;
        }
        
        /* White text for specific total columns (Price, Views, SPRICE) */
        #ovl30DetailsModal .modal-totals-row #modal-total-price,
        #ovl30DetailsModal .modal-totals-row #modal-total-views,
        #ovl30DetailsModal .modal-totals-row #modal-avg-sprice {
            color: white !important;
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

        /* Parent row styling - Light blue background like Amazon */
        .parent-row {
            background-color: #bde0ff !important;
            font-weight: bold !important;
        }

        .tabulator-row.parent-row {
            background-color: #bde0ff !important;
            font-weight: bold !important;
        }

        /* Expand/collapse icon */
        .parent-toggle-icon {
            cursor: pointer;
            margin-right: 8px;
            color: #17a2b8;
            font-size: 13px;
        }

        .parent-toggle-icon:hover {
            color: #0d6efd;
        }

        /* Push price button styling */
        .push-price-btn {
            font-size: 14px;
            padding: 4px 10px;
            white-space: nowrap;
            min-width: 36px;
        }
        
        .push-price-btn i {
            font-size: 12px;
        }
        
        .push-price-btn:disabled {
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        /* Pushed By column styling */
        .pushed-by-info {
            font-size: 11px;
        }
        
        .pushed-by-info strong {
            display: block;
            color: #28a745;
        }
        
        .pushed-by-info .text-muted {
            font-size: 10px;
        }

        /* Modal width - 95% of screen */
        .modal-xxl {
            max-width: 90% !important;
        }

        /* SKU column - prevent wrapping */
        #ovl30DetailsModal table td:nth-child(2),
        #ovl30DetailsModal table th:nth-child(2) {
            white-space: nowrap !important;
            min-width: 280px !important;
        }

        /* Parent SKU dot - P column */
        .parent-sku-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #17a2b8;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .parent-sku-dot:hover {
            background-color: #0d6efd;
        }
        .parent-sku-dot.no-parent {
            background-color: #dee2e6;
            cursor: default;
        }

        /* Row selection checkboxes */
        .row-select-cb, .select-all-cb {
            cursor: pointer;
            width: 16px;
            height: 16px;
        }

        /* Summary header bar */
        .summary-header-bar {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-size: 14px;
        }
        .summary-item {
            color: #495057;
        }
        .summary-item strong {
            color: #212529;
            margin-right: 4px;
        }

    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Pricing Master',
        'sub_title' => 'Pricing Master Data with Editable SPRICE',
    ])
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>
    
    <!-- Remark History Modal -->
    <div class="modal fade" id="remarkHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #6c757d;">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-history me-2"></i> 
                        Remark History - <span id="historySkuName"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead style="background-color: #6c757d; color: white;">
                                <tr>
                                    <th style="width: 50%;">Remark</th>
                                    <th>User</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="remarkHistoryTableBody">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No remarks yet</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- OV L30 Details Modal -->
    <div class="modal fade" id="ovl30DetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xxl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #17a2b8;">
                    <div class="modal-title text-white d-flex align-items-center justify-content-between w-100" style="font-size: 2em;">
                        <div class="d-flex align-items-center gap-3">
                            <i class="fas fa-mouse-pointer me-2"></i> 
                            <span id="modalSkuName" style="font-weight: bold;">SKU</span>
                        </div>
                        <div class="d-flex align-items-center gap-5">
                            <span>
                                <strong>Total INV:</strong> <span id="modal-header-inv">0</span>
                            </span>
                            <span>
                                <strong>L30 Sold:</strong> <span id="modal-header-l30">0</span>
                            </span>
                            <span>
                                <strong>Dil %:</strong> <span id="modal-header-dil">0%</span>
                            </span>
                            <span id="modal-header-lmp-link" style="cursor: pointer; text-decoration: underline;" title="Click to view LMP competitors">
                                <i class="fas fa-search me-2"></i><strong>LMP</strong>
                            </span>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead style="background-color: #17a2b8; color: white;">
                                <tr class="modal-vertical-header">
                                    <th>M</th>
                                    <th>SKU</th>
                                    <th>Price</th>
                                    <th>Views</th>
                                    <th>CVR%</th>
                                    <th>GPFT%</th>
                                    <th>AD%</th>
                                    <th>TACOS CH</th>
                                    <th>NPFT%</th>
                                    <th>LMP</th>
                                    <th>Links</th>
                                    <th>SPRICE</th>
                                    <th>SGPFT%</th>
                                    <th>SPFT%</th>
                                    <th>SROI%</th>
                                    <th>Push</th>
                                    <th>Pushed By</th>
                                </tr>
                                <tr class="modal-totals-row">
                                    <th><img id="modal-product-image" src="" alt="" style="width: 50px; height: 50px; object-fit: cover; display: none;"></th>
                                    <th>Total</th>
                                    <th class="text-end" id="modal-total-price">$0.00</th>
                                    <th class="text-end" id="modal-total-views">0</th>
                                    <th class="text-end" id="modal-avg-cvr">0%</th>
                                    <th class="text-end" id="modal-avg-gpft">0%</th>
                                    <th class="text-end" id="modal-avg-ad">0%</th>
                                    <th class="text-end" id="modal-avg-tacos">0%</th>
                                    <th class="text-end" id="modal-avg-npft">0%</th>
                                    <th></th>
                                    <th></th>
                                    <th class="text-end" id="modal-avg-sprice">$0.00</th>
                                    <th class="text-end" id="modal-avg-sgpft">0%</th>
                                    <th class="text-end" id="modal-avg-spft">0%</th>
                                    <th class="text-end" id="modal-avg-sroi">0%</th>
                                    <th></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="ovl30DetailsTableBody">
                                <!-- Table rows will be populated dynamically -->
                                <tr>
                                    <td colspan="17" class="text-center text-muted py-4">No data available</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LMP Competitors Modal (Amazon + eBay in single view) -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa fa-shopping-cart"></i> LMP Competitors for SKU: <span id="lmpSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="lmpDataList">
                        <div class="text-center py-5 text-muted">
                            <div class="spinner-border text-primary me-2"></div>Loading competitors...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="card shadow-sm">
            <!-- Header Bar - Totals -->
            <div class="summary-header-bar px-4 py-3 d-flex flex-wrap align-items-center gap-4 border-bottom">
                <span class="summary-item"><strong>Total INV:</strong> <span id="total-inv-badge">0</span></span>
                <span class="summary-item"><strong>Total OV L30:</strong> <span id="total-l30-badge">0</span></span>
                <span class="summary-item"><strong>DIL:</strong> <span id="avg-dil-badge">0%</span></span>
                <span class="summary-item"><strong>Total Views:</strong> <span id="total-views-badge">0</span></span>
                <span class="summary-item"><strong>CVR:</strong> <span id="avg-cvr-badge">0%</span></span>
                <span class="summary-item"><strong>Avg Price:</strong> <span id="avg-price-badge">$0.00</span></span>
                <span class="summary-item"><strong>Amz LMP:</strong> <span id="amz-lmp-badge">$0.00</span></span>
                <span class="summary-item d-flex align-items-center gap-2 ms-auto">
                    <label class="mb-0 fw-semibold">Change Price:</label>
                    <input type="number" id="change-price-input" class="form-control form-control-sm" placeholder="Enter price" step="0.01" min="0" style="width: 100px;">
                    <button type="button" id="apply-change-price-btn" class="btn btn-sm btn-primary">
                        <i class="fas fa-check"></i> Apply
                    </button>
                </span>
            </div>
            <div class="card-body py-3">
             
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

                    <!-- SKU/Parent Filter -->
                    <select id="sku-parent-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="both">Both</option>
                        <option value="sku" selected>SKU Only</option>
                        <option value="parent">Parent Only</option>
                    </select>

                    <button type="button" id="remove-filter-btn" class="btn btn-sm btn-outline-danger" title="Remove all filters">
                        <i class="fas fa-times-circle"></i> Remove Filter
                    </button>

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
            const imagePath = $(this).data('image') || '';
            const inv = $(this).data('inv') || 0;
            const l30 = $(this).data('l30') || 0;
            const dil = $(this).data('dil') || 0;
            loadMarketplaceBreakdown(sku, imagePath, inv, l30, dil);
        });
        
        // LMP Info Icon Click Handler (from modal breakdown)
        $(document).on('click', '.lmp-info-icon', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            const marketplace = $(this).data('marketplace');
            console.log('Opening LMP modal for:', sku, 'marketplace:', marketplace);
            loadLmpCompetitorsModal(sku);
        });
        
        // LMP Header Link Click Handler (from modal header)
        $(document).on('click', '#modal-header-lmp-link', function(e) {
            e.stopPropagation();
            const sku = $('#modalSkuName').text();
            console.log('Opening LMP modal from header for:', sku);
            loadLmpCompetitorsModal(sku);
        });

        function loadMarketplaceBreakdown(sku, imagePath, inv, l30, dil) {
            $('#modalSkuName').text(sku);
            
            // Set product image in modal totals row
            const imgElement = $('#modal-product-image');
            if (imagePath) {
                imgElement.attr('src', imagePath);
                imgElement.attr('alt', sku);
                imgElement.show();
            } else {
                imgElement.hide();
            }
            
            // Update header stats with color formatting
            $('#modal-header-inv').text(inv.toLocaleString());
            $('#modal-header-l30').text(l30.toLocaleString());
            
            // Apply color formatting to Dil %
            let dilColor = '';
            const dilValue = parseFloat(dil);
            if (dilValue >= 50) dilColor = '#a00211'; // Dark red
            else if (dilValue >= 30 && dilValue < 50) dilColor = '#dc3545'; // Red
            else if (dilValue >= 20 && dilValue < 30) dilColor = '#ffc107'; // Yellow
            else if (dilValue >= 10 && dilValue < 20) dilColor = '#3591dc'; // Blue
            else if (dilValue >= 5 && dilValue < 10) dilColor = '#28a745'; // Green
            else dilColor = '#e83e8c'; // Pink
            
            $('#modal-header-dil').html(`<span style="color: ${dilColor}; font-weight: 600;">${dilValue.toFixed(1)}%</span>`);
            
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
                    <td colspan="17" class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm text-info me-2" role="status"></div>
                        Loading data for ${sku}...
                    </td>
                </tr>
            `);
        }

        function showModalEmpty(sku) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="17" class="text-center text-muted py-4">
                        No marketplace data available for ${sku}
                    </td>
                </tr>
            `);
        }

        function showModalError(message) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="17" class="text-center text-danger py-4">
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
            
            // Sort data by L30 in descending order (highest to lowest)
            data.sort((a, b) => {
                const l30A = parseInt(a.l30 || 0);
                const l30B = parseInt(b.l30 || 0);
                return l30B - l30A;
            });

            let html = '';
            let totalPrice = 0;
            let totalViews = 0;
            let totalL30 = 0;
            let totalCVR = 0;
            let totalPftAmount = 0; // Sum of PFT amounts
            let totalNpftAmount = 0; // Sum of NPFT amounts
            let totalSalesAmount = 0; // Sum of sales amounts
            let totalAD = 0;
            let totalTACOS = 0;
            let totalSPRICE = 0;
            let totalSGPFT = 0;
            let totalSPFT = 0;
            let totalSROI = 0;
            let cvrCount = 0;
            let adCount = 0;
            let tacosCount = 0;
            let spriceCount = 0;
            let sgpftCount = 0;
            let spftCount = 0;
            let sroiCount = 0;
            
            data.forEach(item => {
                const isListed = item.is_listed !== false;
                const rowClass = !isListed ? 'table-secondary' : '';
                const textClass = !isListed ? 'text-muted fst-italic' : '';
                
                // Calculate CVR% (L30 / Views * 100)
                const views = parseInt(item.views || 0);
                const l30 = parseInt(item.l30 || 0);
                const cvr = views > 0 ? (l30 / views) * 100 : 0;
                const gpft = parseFloat(item.gpft || 0);
                const ad = parseFloat(item.ad || 0);
                const tacosCh = parseFloat(item.tacos_ch || 0);
                const npft = parseFloat(item.npft || 0);
                
                // SPRICE and calculated values
                const sprice = parseFloat(item.sprice || 0);
                const lp = parseFloat(item.lp || 0);
                const ship = parseFloat(item.ship || 0);
                const margin = parseFloat(item.margin || 0.80);
                
                let sgpft = 0, spft = 0, sroi = 0;
                if (sprice > 0) {
                    sgpft = ((sprice * margin - ship - lp) / sprice) * 100;
                    spft = l30 == 0 ? sgpft : (sgpft - ad);
                    sroi = lp > 0 ? ((sprice * margin - lp - ship) / lp) * 100 : 0;
                }
                
                const isEditable = ['amazon', 'doba', 'ebay', 'ebaytwo', 'ebaythree', 'temu', 'walmart', 'tiktok', 'bestbuy', 'macy', 'reverb', 'tiendamia', 'sb2c', 'shopifyb2c', 'sb2b', 'shopifyb2b'].includes((item.marketplace || '').toLowerCase());
                
                // Color coding for CVR%
                let cvrColor = '';
                if (cvr < 1) cvrColor = '#a00211'; // Dark red
                else if (cvr >= 1 && cvr < 3) cvrColor = '#ffc107'; // Yellow
                else if (cvr >= 3 && cvr < 5) cvrColor = '#28a745'; // Green
                else cvrColor = '#e83e8c'; // Pink
                
                // Color coding for GPFT%, AD%, and NPFT%
                let gpftColor = '';
                let adColor = '';
                let npftColor = '';
                
                if (gpft < 0) gpftColor = '#a00211';
                else if (gpft >= 0 && gpft < 10) gpftColor = '#ffc107';
                else if (gpft >= 10 && gpft < 20) gpftColor = '#3591dc';
                else if (gpft >= 20 && gpft <= 40) gpftColor = '#28a745';
                else gpftColor = '#e83e8c';
                
                // AD% color: lower is better
                if (ad >= 100) adColor = '#a00211'; // Dark red for 100%+
                else if (ad >= 50) adColor = '#dc3545'; // Red
                else if (ad >= 20) adColor = '#ffc107'; // Yellow
                else if (ad >= 10) adColor = '#3591dc'; // Blue
                else if (ad > 0) adColor = '#28a745'; // Green (low is good)
                else adColor = '#6c757d'; // Gray for 0
                
                // TACOS CH color: lower is better (same as AD%)
                let tacosColor = '';
                if (tacosCh >= 100) tacosColor = '#a00211';
                else if (tacosCh >= 50) tacosColor = '#dc3545';
                else if (tacosCh >= 20) tacosColor = '#ffc107';
                else if (tacosCh >= 10) tacosColor = '#3591dc';
                else if (tacosCh > 0) tacosColor = '#28a745';
                else tacosColor = '#6c757d';
                
                if (npft < 0) npftColor = '#a00211';
                else if (npft >= 0 && npft < 10) npftColor = '#ffc107';
                else if (npft >= 10 && npft < 20) npftColor = '#3591dc';
                else if (npft >= 20 && npft <= 40) npftColor = '#28a745';
                else npftColor = '#e83e8c';
                
                // Color coding for SGPFT%, SPFT%, SROI%
                let sgpftColor = '';
                if (sgpft < 0) sgpftColor = '#a00211';
                else if (sgpft >= 0 && sgpft < 10) sgpftColor = '#ffc107';
                else if (sgpft >= 10 && sgpft < 20) sgpftColor = '#3591dc';
                else if (sgpft >= 20 && sgpft <= 40) sgpftColor = '#28a745';
                else sgpftColor = '#e83e8c';
                
                let spftColor = '';
                if (spft < 0) spftColor = '#a00211';
                else if (spft >= 0 && spft < 10) spftColor = '#ffc107';
                else if (spft >= 10 && spft < 20) spftColor = '#3591dc';
                else if (spft >= 20 && spft <= 40) spftColor = '#28a745';
                else spftColor = '#e83e8c';
                
                let sroiColor = '';
                if (sroi < 0) sroiColor = '#a00211';
                else if (sroi >= 0 && sroi < 50) sroiColor = '#ffc107';
                else if (sroi >= 50 && sroi < 100) sroiColor = '#3591dc';
                else if (sroi >= 100 && sroi <= 150) sroiColor = '#28a745';
                else sroiColor = '#e83e8c';
                
                // Add to totals only if listed
                if (isListed) {
                    // Calculate sold amount = price × L30 qty
                    const price = parseFloat(item.price || 0);
                    const soldAmount = price * l30;
                    totalPrice += soldAmount;
                    totalViews += views;
                    totalL30 += l30;
                    
                    // Calculate PFT amount = Sales Amount × GPFT%
                    const pftAmount = soldAmount * (gpft / 100);
                    totalPftAmount += pftAmount;
                    
                    // Calculate NPFT amount = Sales Amount × NPFT%
                    const npftAmount = soldAmount * (npft / 100);
                    totalNpftAmount += npftAmount;
                    
                    totalSalesAmount += soldAmount;
                    
                    if (cvr > 0) {
                        totalCVR += cvr;
                        cvrCount++;
                    }
                    // Always count AD% for average (even if 0)
                    totalAD += ad;
                    adCount++;
                    // Count TACOS CH% if present
                    if (tacosCh !== 0) {
                        totalTACOS += tacosCh;
                        tacosCount++;
                    }
                    if (sprice > 0) {
                        totalSPRICE += sprice;
                        spriceCount++;
                    }
                    if (sgpft !== 0) {
                        totalSGPFT += sgpft;
                        sgpftCount++;
                    }
                    if (spft !== 0) {
                        totalSPFT += spft;
                        spftCount++;
                    }
                    if (sroi !== 0) {
                        totalSROI += sroi;
                        sroiCount++;
                    }
                }
                
                // Determine if upload button should be shown (Amazon, Doba, Walmart, Shopify B2C, Shopify B2B)
                const canPushPrice = ['amazon', 'doba', 'walmart', 'sb2c', 'sb2b', 'reverb'].includes((item.marketplace || '').toLowerCase()) && isListed;
                
                html += `
                    <tr class="${rowClass}" data-marketplace="${item.marketplace}" data-sku="${item.sku}" 
                        data-lp="${lp}" data-ship="${ship}" data-ad="${ad}" data-margin="${margin}" data-l30="${l30}">
                        <td class="${textClass}">${item.marketplace || '-'}</td>
                        <td class="${textClass}" style="white-space: nowrap; min-width: 250px;">${item.sku || '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? '$' + parseFloat(item.price || 0).toFixed(2) : '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? views.toLocaleString() : '-'}</td>
                        <td class="text-end ${textClass}">${isListed && views > 0 ? '<span style="color: ' + cvrColor + '; font-weight: 600;">' + cvr.toFixed(1) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">${isListed && gpft !== 0 ? '<span style="color: ' + gpftColor + '; font-weight: 600;">' + Math.round(gpft) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? '<span style="color: ' + adColor + '; font-weight: 600;">' + ad.toFixed(1) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">
                            ${isListed && tacosCh !== 0 ? '<span style="color: ' + tacosColor + '; font-weight: 600;">' + tacosCh.toFixed(1) + '%</span>' : '-'}
                        </td>
                        <td class="text-end ${textClass}">${isListed && npft !== 0 ? '<span style="color: ' + npftColor + '; font-weight: 600;">' + Math.round(npft) + '%</span>' : '-'}</td>
                        <td class="text-center ${textClass}">
                            ${isListed ? 
                                '<i class="fas fa-circle lmp-info-icon" style="cursor: pointer; color: #17a2b8; font-size: 10px;" ' +
                                'data-marketplace="' + item.marketplace + '" ' +
                                'data-sku="' + item.sku + '" ' +
                                'title="View LMP Data for ' + item.marketplace + '"></i>' 
                                : '-'}
                        </td>
                        <td class="text-center ${textClass}" style="white-space: nowrap;">
                            ${(item.buyer_link || item.seller_link) ? 
                                (item.buyer_link ? '<a href="' + item.buyer_link + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary me-1" title="Buyer link">B</a>' : '') +
                                (item.seller_link ? '<a href="' + item.seller_link + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Seller link">S</a>' : '')
                                : '-'}
                        </td>
                        <td class="text-end ${textClass}">
                            ${isEditable && isListed ? 
                                '<input type="number" class="form-control form-control-sm editable-sprice" value="' + sprice.toFixed(2) + '" step="0.01" style="width:80px;">' 
                                : (sprice > 0 ? '$' + sprice.toFixed(2) : '-')}
                        </td>
                        <td class="text-end ${textClass}">
                            <span class="calculated-sgpft" style="color: ${sgpftColor}; font-weight: 600;">${Math.round(sgpft)}%</span>
                        </td>
                        <td class="text-end ${textClass}">
                            <span class="calculated-spft" style="color: ${spftColor}; font-weight: 600;">${Math.round(spft)}%</span>
                        </td>
                        <td class="text-end ${textClass}">
                            <span class="calculated-sroi" style="color: ${sroiColor}; font-weight: 600;">${Math.round(sroi)}%</span>
                        </td>
                        <td class="text-center ${textClass}">
                            ${canPushPrice ? 
                                '<button class="btn btn-sm btn-primary push-price-btn" ' +
                                'data-sku="' + item.sku + '" ' +
                                'data-marketplace="' + item.marketplace + '" ' +
                                'title="Push price to ' + item.marketplace + '">' +
                                '<i class="fas fa-upload"></i></button>' 
                                : '-'}
                        </td>
                        <td class="text-center ${textClass}">
                            ${item.pushed_by ? 
                                '<div class="pushed-by-info"><strong>' + item.pushed_by + '</strong>' +
                                '<div class="text-muted">' + item.pushed_at + '</div></div>'
                                : '<span class="text-muted">-</span>'}
                        </td>
                    </tr>
                `;
            });
            
            $('#ovl30DetailsTableBody').html(html);
            
            // Calculate averages
            // Avg CVR using CVR formula: (Total L30 / Total Views) × 100
            const avgCVR = totalViews > 0 ? (totalL30 / totalViews) * 100 : 0;
            // Avg GPFT% = (Total PFT Amount / Total Sales Amount) × 100
            const avgGPFT = totalSalesAmount > 0 ? (totalPftAmount / totalSalesAmount) * 100 : 0;
            const avgAD = adCount > 0 ? totalAD / adCount : 0;
            const avgTACOS = tacosCount > 0 ? totalTACOS / tacosCount : 0;
            // Avg NPFT% = (Total NPFT Amount / Total Sales Amount) × 100
            const avgNPFT = totalSalesAmount > 0 ? (totalNpftAmount / totalSalesAmount) * 100 : 0;
            const avgSGPFT = sgpftCount > 0 ? totalSGPFT / sgpftCount : 0;
            const avgSPFT = spftCount > 0 ? totalSPFT / spftCount : 0;
            const avgSROI = sroiCount > 0 ? totalSROI / sroiCount : 0;
            
            // Apply color formatting for totals row
            // CVR% color
            let cvrColorTotal = '';
            if (avgCVR < 1) cvrColorTotal = '#a00211';
            else if (avgCVR >= 1 && avgCVR < 3) cvrColorTotal = '#ffc107';
            else if (avgCVR >= 3 && avgCVR < 5) cvrColorTotal = '#28a745';
            else cvrColorTotal = '#e83e8c';
            
            // GPFT%, NPFT%, SGPFT%, SPFT% color
            let gpftColorTotal = '';
            if (avgGPFT < 0) gpftColorTotal = '#a00211';
            else if (avgGPFT >= 0 && avgGPFT < 10) gpftColorTotal = '#ffc107';
            else if (avgGPFT >= 10 && avgGPFT < 20) gpftColorTotal = '#3591dc';
            else if (avgGPFT >= 20 && avgGPFT <= 40) gpftColorTotal = '#28a745';
            else gpftColorTotal = '#e83e8c';
            
            let npftColorTotal = '';
            if (avgNPFT < 0) npftColorTotal = '#a00211';
            else if (avgNPFT >= 0 && avgNPFT < 10) npftColorTotal = '#ffc107';
            else if (avgNPFT >= 10 && avgNPFT < 20) npftColorTotal = '#3591dc';
            else if (avgNPFT >= 20 && avgNPFT <= 40) npftColorTotal = '#28a745';
            else npftColorTotal = '#e83e8c';
            
            let sgpftColorTotal = '';
            if (avgSGPFT < 0) sgpftColorTotal = '#a00211';
            else if (avgSGPFT >= 0 && avgSGPFT < 10) sgpftColorTotal = '#ffc107';
            else if (avgSGPFT >= 10 && avgSGPFT < 20) sgpftColorTotal = '#3591dc';
            else if (avgSGPFT >= 20 && avgSGPFT <= 40) sgpftColorTotal = '#28a745';
            else sgpftColorTotal = '#e83e8c';
            
            let spftColorTotal = '';
            if (avgSPFT < 0) spftColorTotal = '#a00211';
            else if (avgSPFT >= 0 && avgSPFT < 10) spftColorTotal = '#ffc107';
            else if (avgSPFT >= 10 && avgSPFT < 20) spftColorTotal = '#3591dc';
            else if (avgSPFT >= 20 && avgSPFT <= 40) spftColorTotal = '#28a745';
            else spftColorTotal = '#e83e8c';
            
            // AD% color (lower is better)
            let adColorTotal = '';
            if (avgAD >= 100) adColorTotal = '#a00211';
            else if (avgAD >= 50) adColorTotal = '#dc3545';
            else if (avgAD >= 20) adColorTotal = '#ffc107';
            else if (avgAD >= 10) adColorTotal = '#3591dc';
            else if (avgAD > 0) adColorTotal = '#28a745';
            else adColorTotal = '#6c757d';
            
            // TACOS CH color (lower is better, same as AD%)
            let tacosColorTotal = '';
            if (avgTACOS >= 100) tacosColorTotal = '#a00211';
            else if (avgTACOS >= 50) tacosColorTotal = '#dc3545';
            else if (avgTACOS >= 20) tacosColorTotal = '#ffc107';
            else if (avgTACOS >= 10) tacosColorTotal = '#3591dc';
            else if (avgTACOS > 0) tacosColorTotal = '#28a745';
            else tacosColorTotal = '#6c757d';
            
            // SROI% color
            let sroiColorTotal = '';
            if (avgSROI < 0) sroiColorTotal = '#a00211';
            else if (avgSROI >= 0 && avgSROI < 50) sroiColorTotal = '#ffc107';
            else if (avgSROI >= 50 && avgSROI < 100) sroiColorTotal = '#3591dc';
            else if (avgSROI >= 100 && avgSROI <= 150) sroiColorTotal = '#28a745';
            else sroiColorTotal = '#e83e8c';
            
            // Update totals with color formatting
            // Calculate average price = Total Sold Amount / Total Sold Qty (L30)
            const avgPrice = totalL30 > 0 ? totalPrice / totalL30 : 0;
            $('#modal-total-price').text('$' + avgPrice.toFixed(2));
            $('#modal-total-views').text(totalViews.toLocaleString());
            $('#modal-avg-cvr').html(`<span style="color: ${cvrColorTotal}; font-weight: 600;">${avgCVR.toFixed(1)}%</span>`);
            $('#modal-avg-gpft').html(`<span style="color: ${gpftColorTotal}; font-weight: 600;">${avgGPFT.toFixed(1)}%</span>`);
            $('#modal-avg-ad').html(`<span style="color: ${adColorTotal}; font-weight: 600;">${avgAD.toFixed(1)}%</span>`);
            $('#modal-avg-tacos').html(`<span style="color: ${tacosColorTotal}; font-weight: 600;">${avgTACOS.toFixed(1)}%</span>`);
            $('#modal-avg-npft').html(`<span style="color: ${npftColorTotal}; font-weight: 600;">${avgNPFT.toFixed(1)}%</span>`);
            
            $('#modal-avg-sprice').text('$' + (spriceCount > 0 ? totalSPRICE / spriceCount : 0).toFixed(2));
            $('#modal-avg-sgpft').html(`<span style="color: ${sgpftColorTotal}; font-weight: 600;">${avgSGPFT.toFixed(1)}%</span>`);
            $('#modal-avg-spft').html(`<span style="color: ${spftColorTotal}; font-weight: 600;">${avgSPFT.toFixed(1)}%</span>`);
            $('#modal-avg-sroi').html(`<span style="color: ${sroiColorTotal}; font-weight: 600;">${avgSROI.toFixed(1)}%</span>`);
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
            columnCalcs: "top",
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
            rowFormatter: function(row) {
                const data = row.getData();
                if (data.is_parent_summary === true) {
                    row.getElement().style.backgroundColor = "#bde0ff";
                    row.getElement().style.fontWeight = "bold";
                    row.getElement().classList.add("parent-row");
                }
            },
            columns: [
                {
                    title: "#",
                    field: "_selected",
                    headerSort: false,
                    width: 40,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku;
                        const checked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type="checkbox" class="row-select-cb" data-sku="${(sku || '').replace(/"/g, '&quot;')}" ${checked}>`;
                    },
                    titleFormatter: function(column) {
                        const allChecked = isAllFilteredSelected();
                        return `<input type="checkbox" class="select-all-cb" title="Select all filtered rows SKUs (excludes parent rows)" ${allChecked ? 'checked' : ''}>`;
                    }
                },
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
                    title: "P",
                    field: "parent",
                    headerSort: false,
                    width: 40,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const parent = cell.getValue();
                        if (!parent) {
                            return '<span class="parent-sku-dot no-parent" title="No parent"></span>';
                        }
                        return `<span class="parent-sku-dot parent-sku-dot-btn" 
                                    data-parent="${parent.replace(/"/g, '&quot;')}" 
                                    title="Click to view SKUs for parent: ${parent.replace(/"/g, '&quot;')}"></span>`;
                    }
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
                        const rowData = cell.getRow().getData();
                        let html = '';
                        
                        // Add toggle icon for parent rows in "Parent Only" view
                        if (rowData.is_parent_summary === true) {
                            const isExpanded = rowData._expanded === true;
                            const iconClass = isExpanded ? 'fa-chevron-up' : 'fa-chevron-down';
                            html += `<i class="fas ${iconClass} parent-toggle-icon" 
                                       data-parent="${rowData.parent}" 
                                       title="Click to expand/collapse"></i>`;
                        }
                        
                        html += `<span>${sku}</span>`;
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
                        const imagePath = rowData.image_path || '';
                        const inv = rowData.inventory ?? rowData.inv ?? 0;
                        const dilPercent = rowData.dil_percent || 0;
                        
                        // Don't show info icon for parent rows
                        if (rowData.is_parent_summary === true) {
                            return `<span style="font-weight: 600;">${value}</span>`;
                        }
                        
                        return `
                            <span style="font-weight: 600;">${value}</span>
                            <i class="fas fa-info-circle text-info ovl30-info-icon" 
                               style="cursor: pointer; font-size: 12px; margin-left: 6px;" 
                               data-sku="${sku}"
                               data-image="${imagePath}"
                               data-inv="${inv}"
                               data-l30="${value}"
                               data-dil="${dilPercent}"
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
                },
                {
                    title: "Total Views",
                    field: "total_views",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue() || 0);
                        if (value === 0) {
                            return '<span style="color: #6c757d;">0</span>';
                        }
                        return `<span style="font-weight: 600;">${value.toLocaleString()}</span>`;
                    },
                    width: 110
                },
                {
                    title: "Avg CVR",
                    field: "avg_cvr",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';
                        
                        // Color coding for CVR%
                        if (value === 0) color = '#6c757d';
                        else if (value < 1) color = '#a00211';
                        else if (value >= 1 && value < 3) color = '#ffc107';
                        else if (value >= 3 && value < 5) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${value.toFixed(1)}%</span>`;
                    },
                    width: 90
                },
                {
                    title: "Amz LMP",
                    field: "amazon_lmp_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.sku;
                        const count = parseInt(rowData.amazon_lmp_count || 0);
                        if (rowData.is_parent_summary === true) {
                            const v = cell.getValue();
                            return v != null ? '<span style="font-weight: 600;">$' + parseFloat(v).toFixed(2) + '</span>' : '<span class="text-muted">-</span>';
                        }
                        const value = cell.getValue();
                        let html = '<div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">';
                        if (value == null || value === '') {
                            html += '<span class="text-muted">-</span>';
                        } else {
                            const price = parseFloat(value);
                            const avgPrice = parseFloat(rowData.avg_price || 0);
                            const color = (avgPrice > 0 && price < avgPrice) ? '#dc3545' : '#28a745';
                            html += `<span style="color: ${color}; font-weight: 600;">$${price.toFixed(2)}</span>`;
                        }
                        if (count > 0) {
                            html += `<a href="#" class="view-lmp-competitors" data-sku="${(sku || '').replace(/"/g, '&quot;')}" data-marketplace="amazon" style="color: #007bff; text-decoration: none; cursor: pointer; font-size: 11px;"><i class="fa fa-eye"></i> View ${count}</a>`;
                        }
                        html += '</div>';
                        return html;
                    },
                    width: 100
                },
                {
                    title: "Avg Price",
                    field: "avg_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        if (value === 0) {
                            return '<span style="color: #6c757d;">-</span>';
                        }
                        return `<span style="font-weight: 600;">$${value.toFixed(2)}</span>`;
                    },
                    width: 100
                },
                {
                    title: "Avg GPFT",
                    field: "avg_gpft",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';
                        
                        // Color coding for GPFT%
                        if (value < 0) color = '#a00211';
                        else if (value >= 0 && value < 10) color = '#ffc107';
                        else if (value >= 10 && value < 20) color = '#3591dc';
                        else if (value >= 20 && value <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}%</span>`;
                    },
                    width: 100
                },
                {
                    title: "Avg AD",
                    field: "avg_ad",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';
                        
                        // Color coding for AD% (lower is better)
                        if (value >= 100) color = '#a00211';
                        else if (value >= 50) color = '#dc3545';
                        else if (value >= 20) color = '#ffc107';
                        else if (value >= 10) color = '#3591dc';
                        else if (value > 0) color = '#28a745';
                        else color = '#6c757d';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${value.toFixed(1)}%</span>`;
                    },
                    width: 90
                },
                {
                    title: "Avg PFT",
                    field: "avg_pft",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';
                        
                        // Color coding for PFT% (Net Profit)
                        if (value < 0) color = '#a00211';
                        else if (value >= 0 && value < 10) color = '#ffc107';
                        else if (value >= 10 && value < 20) color = '#3591dc';
                        else if (value >= 20 && value <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}%</span>`;
                    },
                    width: 90
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

        // ==================== SPRICE EDITING ====================
        
        // Real-time calculation when SPRICE changes
        $(document).on('input', '.editable-sprice', function() {
            const input = $(this);
            const row = input.closest('tr');
            const sprice = parseFloat(input.val()) || 0;
            const lp = parseFloat(row.attr('data-lp')) || 0;
            const ship = parseFloat(row.attr('data-ship')) || 0;
            const ad = parseFloat(row.attr('data-ad')) || 0;
            const margin = parseFloat(row.attr('data-margin')) || 0.80;
            const l30 = parseFloat(row.attr('data-l30')) || 0;
            
            if (sprice > 0) {
                const sgpft = ((sprice * margin - ship - lp) / sprice) * 100;
                const spft = l30 == 0 ? sgpft : (sgpft - ad);
                const sroi = lp > 0 ? ((sprice * margin - lp - ship) / lp) * 100 : 0;
                
                row.find('.calculated-sgpft').text(Math.round(sgpft) + '%');
                row.find('.calculated-spft').text(Math.round(spft) + '%');
                row.find('.calculated-sroi').text(Math.round(sroi) + '%');
            }
        });
        
        // Auto-save on blur
        $(document).on('blur', '.editable-sprice', function() {
            const input = $(this);
            const row = input.closest('tr');
            const sku = row.attr('data-sku');
            const marketplace = row.attr('data-marketplace');
            const sprice = parseFloat(input.val()) || 0;
            
            if (sprice === 0) return;
            
            const lp = parseFloat(row.attr('data-lp')) || 0;
            const ship = parseFloat(row.attr('data-ship')) || 0;
            const ad = parseFloat(row.attr('data-ad')) || 0;
            const margin = parseFloat(row.attr('data-margin')) || 0.80;
            const l30 = parseFloat(row.attr('data-l30')) || 0;
            
            const sgpft = sprice > 0 ? ((sprice * margin - ship - lp) / sprice) * 100 : 0;
            const spft = l30 == 0 ? sgpft : (sgpft - ad);
            const sroi = lp > 0 ? ((sprice * margin - lp - ship) / lp) * 100 : 0;
            
            input.css('border-color', '#ffc107');
            
            $.ajax({
                url: '/cvr-master-save-suggested-data',
                method: 'POST',
                data: { sku: sku, marketplace: marketplace, sprice: sprice, sgpft: sgpft, spft: spft, sroi: sroi, _token: '{{ csrf_token() }}' },
                success: function() {
                    input.css('border-color', '#28a745');
                    setTimeout(() => input.css('border-color', ''), 1000);
                    showToast('Saved!', 'success');
                },
                error: function() {
                    input.css('border-color', '#dc3545');
                    showToast('Failed to save', 'error');
                }
            });
        });
        
        // ==================== PRICE PUSH TO AMAZON ====================
        
        // Push price button click handler
        $(document).on('click', '.push-price-btn', function(e) {
            e.stopPropagation();
            const btn = $(this);
            const row = btn.closest('tr');
            const sku = btn.data('sku');
            const marketplace = btn.data('marketplace');
            const priceInput = row.find('.editable-sprice');
            const price = parseFloat(priceInput.val()) || 0;
            
            if (price <= 0) {
                showToast('Please enter a valid price greater than 0', 'error');
                priceInput.focus();
                return;
            }
            
            // Confirm before pushing
            if (!confirm(`Push price $${price.toFixed(2)} to ${marketplace.toUpperCase()} for SKU: ${sku}?`)) {
                return;
            }
            
            // Disable button and show loading state
            const originalHtml = btn.html();
            btn.prop('disabled', true);
            btn.html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: '/cvr-master-push-price',
                method: 'POST',
                data: {
                    sku: sku,
                    price: price,
                    marketplace: marketplace,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, 'success');
                        btn.html('<i class="fas fa-check"></i>');
                        btn.removeClass('btn-primary').addClass('btn-success');
                        
                        // Reload modal data to show pushed_by info
                        setTimeout(() => {
                            const currentSku = $('#modalSkuName').text();
                            const currentImage = $('#modal-product-image').attr('src');
                            const currentInv = $('#modal-header-inv').text().replace(/,/g, '');
                            const currentL30 = $('#modal-header-l30').text().replace(/,/g, '');
                            const currentDil = parseFloat($('#modal-header-dil').text());
                            loadMarketplaceBreakdown(currentSku, currentImage, currentInv, currentL30, currentDil);
                        }, 1500);
                    } else {
                        showToast(response.message || 'Failed to push price', 'error');
                        btn.html(originalHtml);
                        btn.prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    console.error('Price push failed:', {
                        sku: sku,
                        marketplace: marketplace,
                        status: xhr.status,
                        error: xhr.responseJSON
                    });
                    
                    let errorMsg = 'Failed to push price';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    showToast(errorMsg, 'error');
                    btn.html(originalHtml);
                    btn.prop('disabled', false);
                }
            });
        });

        // ==================== BULK CHANGE PRICE ====================
        $('#apply-change-price-btn').on('click', function() {
            const price = parseFloat($('#change-price-input').val()) || 0;
            if (price <= 0) {
                showToast('Please enter a valid price greater than 0', 'error');
                $('#change-price-input').focus();
                return;
            }

            let skus = [];
            if (selectedSkus.size > 0) {
                skus = Array.from(selectedSkus);
            } else {
                const rows = table.getRows('active');
                rows.forEach(r => {
                    const d = r.getData();
                    if (d.is_parent_summary !== true) skus.push(d.sku);
                });
            }

            if (skus.length === 0) {
                showToast('Please select SKUs or ensure table has data', 'error');
                return;
            }

            const msg = `Apply price $${price.toFixed(2)} to ${skus.length} SKU(s) across all marketplaces?\n\n` +
                'Amazon, Walmart, Shopify B2C, Reverb: full price\n' +
                'Doba & Shopify Wholesale: 25% discount applied\n' +
                'Shopify B2B: 25% discount + shipping deducted';
            if (!confirm(msg)) return;

            const btn = $(this);
            const origHtml = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying...');

            $.ajax({
                url: '/cvr-master-bulk-change-price',
                method: 'POST',
                data: {
                    price: price,
                    skus: skus,
                    _token: '{{ csrf_token() }}'
                },
                success: function(res) {
                    if (res.success) {
                        showToast(res.message || `Updated ${res.updated || 0} SKU(s) across marketplaces`, 'success');
                        $('#change-price-input').val('');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(res.message || 'Some updates failed', 'error');
                    }
                    btn.prop('disabled', false).html(origHtml);
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Failed to apply price';
                    showToast(msg, 'error');
                    btn.prop('disabled', false).html(origHtml);
                }
            });
        });

        // ==================== LMP COMPETITORS MODAL ====================
        
        function loadLmpCompetitorsModal(sku) {
            $('#lmpSku').text(sku);
            const modal = new bootstrap.Modal(document.getElementById('lmpModal'));
            modal.show();
            
            $('#lmpDataList').html('<div class="text-center py-5 text-muted"><div class="spinner-border text-primary me-2"></div>Loading competitors...</div>');
            
            let amazonData = null;
            let ebayData = null;
            let loaded = 0;
            
            function tryRender() {
                loaded++;
                if (loaded < 2) return;
                renderLmpCombined(sku, amazonData, ebayData);
            }
            
            $.ajax({
                url: '/amazon/competitors',
                method: 'GET',
                data: { sku: sku },
                success: function(res) {
                    amazonData = res.success && res.competitors ? res : null;
                    tryRender();
                },
                error: function() {
                    amazonData = null;
                    tryRender();
                }
            });
            
            $.ajax({
                url: '/ebay-lmp-data',
                method: 'GET',
                data: { sku: sku },
                success: function(res) {
                    ebayData = res.success && res.competitors ? res : null;
                    tryRender();
                },
                error: function() {
                    ebayData = null;
                    tryRender();
                }
            });
        }
        
        function renderLmpCombined(sku, amazonRes, ebayRes) {
            const amzList = (amazonRes && amazonRes.competitors) ? amazonRes.competitors : [];
            const ebayList = (ebayRes && ebayRes.competitors) ? ebayRes.competitors : [];
            
            const amzLowest = amzList.length ? Math.min(...amzList.map(c => parseFloat(c.price) || 0).filter(p => p > 0)) : null;
            const ebayTotals = ebayList.map(c => parseFloat(c.total_price || c.price) || 0).filter(t => t > 0);
            const ebayLowest = ebayTotals.length ? Math.min(...ebayTotals) : null;
            
            if (amzList.length === 0 && ebayList.length === 0) {
                $('#lmpDataList').html('<div class="alert alert-info"><i class="fa fa-info-circle"></i> No Amazon or eBay competitors found for this SKU</div>');
                return;
            }
            
            const maxRows = Math.max(amzList.length, ebayList.length);
            let html = '';
            if ((amzLowest != null && amzLowest > 0) || (ebayLowest != null && ebayLowest > 0)) {
                const parts = [];
                if (amzLowest != null && amzLowest > 0) parts.push('<span class="badge bg-warning text-dark me-1">Amz lowest: $' + amzLowest.toFixed(2) + '</span>');
                if (ebayLowest != null && ebayLowest > 0) parts.push('<span class="badge bg-info text-dark">eBay lowest: $' + ebayLowest.toFixed(2) + '</span>');
                html += '<div class="mb-3">' + parts.join(' ') + '</div>';
            }
            
            html += '<div class="table-responsive"><table class="table table-hover table-bordered table-sm"><thead class="table-light"><tr><th>#</th><th>SKU</th><th>Amz</th><th>eBay</th></tr></thead><tbody>';
            
            for (let i = 0; i < maxRows; i++) {
                const amz = amzList[i];
                const ebay = ebayList[i];
                const amzPrice = amz ? (parseFloat(amz.price) || 0) : null;
                const ebayPrice = ebay ? (parseFloat(ebay.total_price || ebay.price) || 0) : null;
                const amzLink = amz ? (amz.product_link || amz.link || '') : '';
                const ebayLink = ebay ? (ebay.link || ebay.product_link || '') : '';
                const amzImage = amz ? (amz.image || '') : '';
                const ebayImage = ebay ? (ebay.image || '') : '';
                
                const amzLowestFlag = amzPrice > 0 && amzLowest != null && Math.abs(amzPrice - amzLowest) < 0.01;
                const ebayLowestFlag = ebayPrice > 0 && ebayLowest != null && Math.abs(ebayPrice - ebayLowest) < 0.01;
                
                const amzImgHtml = amzImage ? `<img src="${amzImage.replace(/"/g, '&quot;')}" alt="Amz" class="rounded" style="height:40px;width:40px;object-fit:contain;margin-right:6px;" onerror="this.style.display='none'">` : '';
                const ebayImgHtml = ebayImage ? `<img src="${ebayImage.replace(/"/g, '&quot;')}" alt="eBay" class="rounded" style="height:40px;width:40px;object-fit:contain;margin-right:6px;" onerror="this.style.display='none'">` : '';
                
                const amzCell = amzPrice != null && amzPrice > 0
                    ? `<div class="d-flex align-items-center">${amzImgHtml}<span>${amzLowestFlag ? '<i class="fa fa-trophy text-success me-1"></i>' : ''}<span style="font-weight: 600;">$${amzPrice.toFixed(2)}</span>${amzLink ? ` <a href="${amzLink.replace(/"/g, '&quot;')}" target="_blank" class="text-primary ms-1" title="Open product"><i class="fa fa-external-link"></i></a>` : ''}</span></div>`
                    : amz ? `<div class="d-flex align-items-center">${amzImgHtml}<span class="text-muted">-</span></div>` : '<span class="text-muted">-</span>';
                
                const ebayCell = ebayPrice != null && ebayPrice > 0
                    ? `<div class="d-flex align-items-center">${ebayImgHtml}<span>${ebayLowestFlag ? '<i class="fa fa-trophy text-success me-1"></i>' : ''}<span style="font-weight: 600;">$${ebayPrice.toFixed(2)}</span>${ebayLink ? ` <a href="${ebayLink.replace(/"/g, '&quot;')}" target="_blank" class="text-primary ms-1" title="Open product"><i class="fa fa-external-link"></i></a>` : ''}</span></div>`
                    : ebay ? `<div class="d-flex align-items-center">${ebayImgHtml}<span class="text-muted">-</span></div>` : '<span class="text-muted">-</span>';
                
                const rowClass = (amzLowestFlag || ebayLowestFlag) ? 'table-success' : '';
                html += `<tr class="${rowClass}"><td>${i+1}</td><td>${sku}</td><td>${amzCell}</td><td>${ebayCell}</td></tr>`;
            }
            
            html += '</tbody></table></div>';
            $('#lmpDataList').html(html);
        }
        
        $(document).on('click', '.view-lmp-competitors', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const sku = $(this).data('sku');
            if (sku) loadLmpCompetitorsModal(sku);
        });
        
        // ==================== REMARK FUNCTIONS ====================
        
        // Submit remark
        $(document).on('click', '.submit-remark-btn', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            const remarkInput = $(`.remark-input[data-sku="${sku}"]`);
            const remark = remarkInput.val().trim();
            
            if (!remark) {
                showToast('Please enter a remark', 'error');
                return;
            }
            
            $.ajax({
                url: '/cvr-master-remark',
                method: 'POST',
                data: {
                    sku: sku,
                    remark: remark,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    showToast('Remark saved successfully', 'success');
                    remarkInput.val(''); // Clear input
                    
                    // Update the cell directly
                    const row = table.getRow(function(row){ return row.getData().sku === sku; });
                    if (row) {
                        row.update({latest_remark: remark, remark_solved: false});
                    }
                },
                error: function() {
                    showToast('Failed to save remark', 'error');
                }
            });
        });

        // Open remark history modal
        $(document).on('click', '.remark-history-icon', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            loadRemarkHistory(sku);
        });

        function loadRemarkHistory(sku) {
            $('#historySkuName').text(sku);
            const modal = new bootstrap.Modal(document.getElementById('remarkHistoryModal'));
            modal.show();
            
            $('#remarkHistoryTableBody').html(`
                <tr>
                    <td colspan="5" class="text-center">
                        <div class="spinner-border spinner-border-sm text-info me-2" role="status"></div>
                        Loading history...
                    </td>
                </tr>
            `);
            
            $.ajax({
                url: `/cvr-master-remark-history/${sku}`,
                method: 'GET',
                success: function(data) {
                    if (data.length === 0) {
                        $('#remarkHistoryTableBody').html(`
                            <tr><td colspan="5" class="text-center text-muted py-4">No remarks yet for this SKU</td></tr>
                        `);
                        return;
                    }
                    
                    let html = '';
                    data.forEach(item => {
                        const statusClass = item.is_solved ? 'success' : 'warning';
                        const statusText = item.is_solved ? 'Solved' : 'Pending';
                        const statusIcon = item.is_solved ? 'check-circle' : 'clock';
                        
                        html += `
                            <tr>
                                <td>${item.remark}</td>
                                <td>${item.user_name}</td>
                                <td><small>${item.created_at}</small></td>
                                <td>
                                    <span class="badge bg-${statusClass}">
                                        <i class="fas fa-${statusIcon}"></i> ${statusText}
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-${item.is_solved ? 'warning' : 'success'} toggle-solved-btn" 
                                            data-id="${item.id}"
                                            title="${item.is_solved ? 'Mark as Pending' : 'Mark as Solved'}">
                                        <i class="fas fa-${item.is_solved ? 'undo' : 'check'}"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    $('#remarkHistoryTableBody').html(html);
                },
                error: function() {
                    $('#remarkHistoryTableBody').html(`
                        <tr><td colspan="5" class="text-center text-danger py-4">Failed to load history</td></tr>
                    `);
                }
            });
        }

        // Toggle solved status
        $(document).on('click', '.toggle-solved-btn', function(e) {
            e.stopPropagation();
            const id = $(this).data('id');
            const sku = $('#historySkuName').text();
            
            $.ajax({
                url: `/cvr-master-remark-toggle/${id}`,
                method: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    showToast('Status updated', 'success');
                    loadRemarkHistory(sku); // Reload history
                    
                    // Update the row in table if it's the latest remark
                    const row = table.getRow(function(row){ return row.getData().sku === sku; });
                    if (row) {
                        const currentData = row.getData();
                        row.update({remark_solved: response.is_solved});
                    }
                },
                error: function() {
                    showToast('Failed to update status', 'error');
                }
            });
        });

        // Store all data for parent expand/collapse
        let fullDataset = [];
        let expandedParent = null;
        let dotExpandedParent = null;
        // Prevent dataLoaded side-effects for local setData operations
        let suppressDataLoadedHandler = false;

        // Row selection - Set of selected SKUs
        let selectedSkus = new Set();

        function isAllFilteredSelected() {
            if (!table) return false;
            const rows = table.getRows('active');
            if (rows.length === 0) return false;
            return rows.every(r => selectedSkus.has(r.getData().sku));
        }

        // Row checkbox click
        $(document).on('change', '.row-select-cb', function() {
            if (!table) return;
            const sku = $(this).data('sku');
            if ($(this).is(':checked')) {
                selectedSkus.add(sku);
            } else {
                selectedSkus.delete(sku);
            }
            const row = table.getRow(function(r) { return r.getData().sku === sku; });
            if (row) row.reformat();
            const headerCb = document.querySelector('#cvr-table .select-all-cb');
            if (headerCb) headerCb.checked = isAllFilteredSelected();
        });

        // Select all checkbox click
        $(document).on('change', '.select-all-cb', function() {
            if (!table) return;
            const $cb = $(this);
            const rows = table.getRows('active');
            if ($cb.is(':checked')) {
                rows.forEach(r => selectedSkus.add(r.getData().sku));
            } else {
                rows.forEach(r => selectedSkus.delete(r.getData().sku));
            }
            table.getRows().forEach(r => r.reformat());
            const headerCb = document.querySelector('#cvr-table .select-all-cb');
            if (headerCb) headerCb.checked = isAllFilteredSelected();
        });

        // Parent SKU dot click - expand to show parent + all child rows with full data
        $(document).on('click', '.parent-sku-dot-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const $dot = $(this);
            const parentVal = $dot.data('parent');
            if (!parentVal) return;

            if (dotExpandedParent === parentVal) {
                // Keep current value; applyFilters() needs it to restore fullDataset
                applyFilters();
                return;
            }

            dotExpandedParent = parentVal;

            const parentRow = fullDataset.find(row =>
                row.is_parent_summary === true && row.parent === parentVal
            );
            const childRows = fullDataset.filter(row =>
                row.parent === parentVal && row.is_parent_summary !== true
            );

            let displayData = [];
            if (parentRow) {
                parentRow._expanded = true;
                displayData.push(parentRow);
            }
            displayData = displayData.concat(childRows);

            suppressDataLoadedHandler = true;
            table.setData(displayData).then(() => {
                updateSummary();
            });
        });

        // Parent toggle handler
        $(document).on('click', '.parent-toggle-icon', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const clickedParent = $(this).data('parent');
            
            console.log('=== Parent Toggle Clicked ===');
            console.log('Clicked parent:', clickedParent);
            console.log('Current expandedParent:', expandedParent);
            
            // Toggle expanded state
            if (expandedParent === clickedParent) {
                expandedParent = null; // Collapse
                console.log('→ Collapsing parent');
            } else {
                expandedParent = clickedParent; // Expand
                console.log('→ Expanding parent:', clickedParent);
            }
            
            // Rebuild parent view
            buildParentView();
            
            return false; // Prevent default action and stop propagation
        });

        function buildParentView() {
            console.log('=== Building Parent View ===');
            console.log('fullDataset length:', fullDataset.length);
            console.log('expandedParent:', expandedParent);
            
            if (!fullDataset || fullDataset.length === 0) {
                console.error('❌ No data available in fullDataset');
                return;
            }
            
            const parentRows = fullDataset.filter(row => row.is_parent_summary === true);
            const childRows = fullDataset.filter(row => row.is_parent_summary !== true);
            
            console.log('Parents found:', parentRows.length);
            console.log('Children found:', childRows.length);
            
            // Debug: Show first few parent values
            if (parentRows.length > 0) {
                console.log('Sample parent values:', parentRows.slice(0, 3).map(p => p.parent));
            }
            
            let displayData = [];
            
            // Build ordered list: parent, then its children if expanded
            parentRows.forEach(parent => {
                // Mark parent as expanded or not (for icon display)
                parent._expanded = (expandedParent === parent.parent);
                
                // Add parent
                displayData.push(parent);
                
                // Add children RIGHT AFTER parent if this parent is expanded
                if (expandedParent !== null && expandedParent === parent.parent) {
                    const children = childRows.filter(child => {
                        const matches = child.parent === expandedParent;
                        return matches;
                    });
                    console.log('✓ Parent matched! Adding', children.length, 'children for parent:', parent.parent);
                    
                    // Debug: show sample children
                    if (children.length > 0) {
                        console.log('Sample children SKUs:', children.slice(0, 3).map(c => c.sku));
                    } else {
                        console.warn('⚠ No children found for parent:', expandedParent);
                        console.log('Looking for children with parent field =', expandedParent);
                        console.log('Sample child parent values:', childRows.slice(0, 5).map(c => ({sku: c.sku, parent: c.parent})));
                    }
                    
                    displayData = displayData.concat(children);
                }
            });
            
            console.log('Final display data length:', displayData.length);
            console.log('Expected:', parentRows.length, '+ children if expanded');
            
            // Update table
            suppressDataLoadedHandler = true;
            table.setData(displayData).then(() => {
                console.log('✓ Table updated successfully');
                updateSummary();
            }).catch(err => {
                console.error('❌ Error updating table:', err);
            });
        }

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
            const wasInDotView = !!dotExpandedParent;
            dotExpandedParent = null;

            const doFilters = function() {
                const inventoryFilter = $('#inventory-filter').val();
                const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';
                const skuParentFilter = $('#sku-parent-filter').val();

                table.clearFilter();

                // SKU/Parent filter
                if (skuParentFilter === 'sku') {
                    table.addFilter(function(data) {
                        return data.is_parent_summary !== true;
                    });
                } else if (skuParentFilter === 'parent') {
                    expandedParent = null;
                    buildParentView();
                    return;
                }

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
            };

            if (wasInDotView && fullDataset.length > 0) {
                suppressDataLoadedHandler = true;
                table.setData(fullDataset).then(doFilters);
            } else {
                doFilters();
            }
        }

        $('#inventory-filter, #sku-parent-filter').on('change', function() {
            applyFilters();
        });

        $('#remove-filter-btn').on('click', function() {
            $('#inventory-filter').val('more');
            $('#sku-parent-filter').val('sku');
            const $allDil = $('.column-filter[data-column="dil_percent"][data-color="all"]');
            $('.column-filter[data-column="dil_percent"]').removeClass('active');
            $allDil.addClass('active');
            $('#dilFilterDropdown').html('').append($allDil.find('.status-circle').clone()).append(' DIL%');
            applyFilters();
        });

        // ==================== SUMMARY FUNCTIONS ====================
        
        function updateSummary() {
            const data = table.getData('active');
            let totalInv = 0, totalL30 = 0, totalDil = 0, dilCount = 0;
            let totalViews = 0, totalCvr = 0, cvrCount = 0;
            let totalPrice = 0, priceCount = 0;
            let totalAmzLmp = 0, amzLmpCount = 0;

            data.forEach(row => {
                totalInv += parseFloat(row['inventory']) || 0;
                totalL30 += parseFloat(row['overall_l30']) || 0;
                const dil = parseFloat(row['dil_percent']) || 0;
                if (dil > 0) {
                    totalDil += dil;
                    dilCount++;
                }
                totalViews += parseInt(row['total_views']) || 0;
                totalCvr += parseFloat(row['avg_cvr']) || 0;
                cvrCount++;
                const price = parseFloat(row['avg_price']) || 0;
                if (price > 0) {
                    totalPrice += price;
                    priceCount++;
                }
                if (!row.is_parent_summary) {
                    const amzLmp = parseFloat(row['amazon_lmp_price']) || 0;
                    if (amzLmp > 0) {
                        totalAmzLmp += amzLmp;
                        amzLmpCount++;
                    }
                }
            });

            const avgDil = dilCount > 0 ? totalDil / dilCount : 0;
            const avgCvr = cvrCount > 0 ? totalCvr / cvrCount : 0;
            const avgPrice = priceCount > 0 ? totalPrice / priceCount : 0;
            const avgAmzLmp = amzLmpCount > 0 ? totalAmzLmp / amzLmpCount : 0;

            $('#total-inv-badge').text(totalInv.toLocaleString());
            $('#total-l30-badge').text(totalL30.toLocaleString());
            $('#avg-dil-badge').text(avgDil.toFixed(1) + '%');
            $('#total-views-badge').text(totalViews.toLocaleString());
            $('#avg-cvr-badge').text(avgCvr.toFixed(1) + '%');
            $('#avg-price-badge').text('$' + avgPrice.toFixed(2));
            $('#amz-lmp-badge').text('$' + avgAmzLmp.toFixed(2));

            const headerCb = document.querySelector('#cvr-table .select-all-cb');
            if (headerCb) headerCb.checked = isAllFilteredSelected();
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

        table.on('dataLoaded', function(data) {
            // Ignore dataLoaded triggered by local setData (dot view / parent view / restore)
            if (suppressDataLoadedHandler) {
                suppressDataLoadedHandler = false;
                return;
            }

            // Store full dataset from server load
            fullDataset = data;
            
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
