@extends('layouts.vertical', ['title' => 'Pricing Master CVR', 'sidenav' => 'condensed'])

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
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Pricing Master CVR',
        'sub_title' => 'Pricing Master CVR Data with Editable SPRICE',
    ])
    <div class="toast-container"></div>
    
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
                                    <th class="text-end">AD%</th>
                                    <th class="text-end">NPFT%</th>
                                    <th class="text-end">SPRICE</th>
                                    <th class="text-end">SGPFT%</th>
                                    <th class="text-end">SPFT%</th>
                                    <th class="text-end">SROI%</th>
                                </tr>
                            </thead>
                            <tbody id="ovl30DetailsTableBody">
                                <!-- Table rows will be populated dynamically -->
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No data available</td>
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
                                    <th class="text-end" id="modal-avg-ad">0%</th>
                                    <th class="text-end" id="modal-avg-npft">0%</th>
                                    <th class="text-end" id="modal-avg-sprice">$0.00</th>
                                    <th class="text-end" id="modal-avg-sgpft">0%</th>
                                    <th class="text-end" id="modal-avg-spft">0%</th>
                                    <th class="text-end" id="modal-avg-sroi">0%</th>
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

                    <!-- SKU/Parent Filter -->
                    <select id="sku-parent-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="both">Both</option>
                        <option value="sku" selected>SKU Only</option>
                        <option value="parent">Parent Only</option>
                    </select>

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
                    <td colspan="9" class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm text-info me-2" role="status"></div>
                        Loading data for ${sku}...
                    </td>
                </tr>
            `);
        }

        function showModalEmpty(sku) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        No marketplace data available for ${sku}
                    </td>
                </tr>
            `);
        }

        function showModalError(message) {
            $('#ovl30DetailsTableBody').html(`
                <tr>
                    <td colspan="9" class="text-center text-danger py-4">
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
            let totalAD = 0;
            let totalNPFT = 0;
            let totalSPRICE = 0;
            let totalSGPFT = 0;
            let totalSPFT = 0;
            let totalSROI = 0;
            let cvrCount = 0;
            let gpftCount = 0;
            let adCount = 0;
            let npftCount = 0;
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
                
                const isEditable = ['amazon', 'ebay', 'ebaytwo', 'ebaythree', 'temu', 'doba', 'walmart', 'tiktok', 'bestbuy', 'macy', 'reverb'].includes((item.marketplace || '').toLowerCase());
                
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
                
                if (npft < 0) npftColor = '#a00211';
                else if (npft >= 0 && npft < 10) npftColor = '#ffc107';
                else if (npft >= 10 && npft < 20) npftColor = '#3591dc';
                else if (npft >= 20 && npft <= 40) npftColor = '#28a745';
                else npftColor = '#e83e8c';
                
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
                    // Always count AD% for average (even if 0)
                    totalAD += ad;
                    adCount++;
                    if (npft !== 0) {
                        totalNPFT += npft;
                        npftCount++;
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
                
                html += `
                    <tr class="${rowClass}" data-marketplace="${item.marketplace}" data-sku="${item.sku}" 
                        data-lp="${lp}" data-ship="${ship}" data-ad="${ad}" data-margin="${margin}" data-l30="${l30}">
                        <td class="${textClass}">${item.marketplace || '-'}</td>
                        <td class="${textClass}">${item.sku || '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? '$' + parseFloat(item.price || 0).toFixed(2) : '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? views.toLocaleString() : '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? l30 : '-'}</td>
                        <td class="text-end ${textClass}">${isListed && views > 0 ? '<span style="color: ' + cvrColor + '; font-weight: 600;">' + cvr.toFixed(1) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">${isListed && gpft !== 0 ? '<span style="color: ' + gpftColor + '; font-weight: 600;">' + Math.round(gpft) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">${isListed ? '<span style="color: ' + adColor + '; font-weight: 600;">' + ad.toFixed(1) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">${isListed && npft !== 0 ? '<span style="color: ' + npftColor + '; font-weight: 600;">' + Math.round(npft) + '%</span>' : '-'}</td>
                        <td class="text-end ${textClass}">
                            ${isEditable && isListed ? 
                                '<input type="number" class="form-control form-control-sm editable-sprice" value="' + sprice.toFixed(2) + '" step="0.01" style="width:80px;">' 
                                : (sprice > 0 ? '$' + sprice.toFixed(2) : '-')}
                        </td>
                        <td class="text-end ${textClass}">
                            <span class="calculated-sgpft">${Math.round(sgpft)}%</span>
                        </td>
                        <td class="text-end ${textClass}">
                            <span class="calculated-spft">${Math.round(spft)}%</span>
                        </td>
                        <td class="text-end ${textClass}">
                            <span class="calculated-sroi">${Math.round(sroi)}%</span>
                        </td>
                    </tr>
                `;
            });
            
            $('#ovl30DetailsTableBody').html(html);
            
            // Calculate averages
            // Avg CVR using CVR formula: (Total L30 / Total Views) × 100
            const avgCVR = totalViews > 0 ? (totalL30 / totalViews) * 100 : 0;
            const avgGPFT = gpftCount > 0 ? totalGPFT / gpftCount : 0;
            const avgAD = adCount > 0 ? totalAD / adCount : 0;
            const avgNPFT = npftCount > 0 ? totalNPFT / npftCount : 0;
            
            // Update totals in footer
            $('#modal-total-price').text('$' + totalPrice.toFixed(2));
            $('#modal-total-views').text(totalViews.toLocaleString());
            $('#modal-total-l30').text(totalL30.toLocaleString());
            $('#modal-avg-cvr').text(avgCVR.toFixed(1) + '%');
            $('#modal-avg-gpft').text(avgGPFT.toFixed(1) + '%');
            $('#modal-avg-ad').text(avgAD.toFixed(1) + '%');
            $('#modal-avg-npft').text(avgNPFT.toFixed(1) + '%');
            $('#modal-avg-sprice').text('$' + (spriceCount > 0 ? totalSPRICE / spriceCount : 0).toFixed(2));
            $('#modal-avg-sgpft').text((sgpftCount > 0 ? totalSGPFT / sgpftCount : 0).toFixed(1) + '%');
            $('#modal-avg-spft').text((spftCount > 0 ? totalSPFT / spftCount : 0).toFixed(1) + '%');
            $('#modal-avg-sroi').text((sroiCount > 0 ? totalSROI / sroiCount : 0).toFixed(1) + '%');
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
                        
                        // Don't show info icon for parent rows
                        if (rowData.is_parent_summary === true) {
                            return `<span style="font-weight: 600;">${value}</span>`;
                        }
                        
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
                    title: "M L30",
                    field: "m_l30",
                    hozAlign: "center",
                    width: 90,
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `<span style="font-weight: 600;">${value}</span>`;
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
            const inventoryFilter = $('#inventory-filter').val();
            const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';
            const skuParentFilter = $('#sku-parent-filter').val();

            table.clearFilter();

            // SKU/Parent filter
            if (skuParentFilter === 'sku') {
                // Show only SKU rows (hide parent rows)
                table.addFilter(function(data) {
                    return data.is_parent_summary !== true;
                });
            } else if (skuParentFilter === 'parent') {
                // Build parent view with proper ordering
                expandedParent = null; // Reset expanded state when switching to parent view
                buildParentView();
                return; // Don't apply other filters yet
            }
            // If 'both', don't add any filter

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

        $('#inventory-filter, #sku-parent-filter').on('change', function() {
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

        table.on('dataLoaded', function(data) {
            // Store full dataset for parent expand/collapse
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
