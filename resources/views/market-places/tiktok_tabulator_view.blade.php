@extends('layouts.vertical', ['title' => 'TikTok Shop Analytics', 'sidenav' => 'condensed'])

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
        'page_title' => 'TikTok Shop Analytics',
        'sub_title' => 'TikTok Shop Analytics',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                {{-- <h4>TikTok Analytics</h4> --}}
                
                <!-- Upload Section -->
                <div class="mb-3 p-3 bg-light rounded">
                    <form action="{{ url('/tiktok-upload-csv') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="d-flex align-items-center gap-2">
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required style="max-width: 300px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i> Upload CSV
                            </button>
                            <a href="{{ url('/tiktok-download-sample-csv') }}" class="btn btn-secondary">
                                <i class="fas fa-download"></i> Download Sample
                            </a>
                        </div>
                        <small class="text-muted">Upload CSV with columns: sku, price (will truncate existing data)</small>
                    </form>
                </div>

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: 130px;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <select id="tiktok-stock-filter" class="form-select form-select-sm"
                        style="width: 130px;">
                        <option value="all">TT Stock</option>
                        <option value="zero">0 TT Stock</option>
                        <option value="more">More than 0</option>
                    </select>

                    <select id="gpft-filter" class="form-select form-select-sm"
                        style="width: 130px;">
                        <option value="all">GPFT%</option>
                        <option value="negative">Negative</option>
                        <option value="0-10">0-10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30-40">30-40%</option>
                        <option value="40-50">40-50%</option>
                        <option value="50-60">50-60%</option>
                        <option value="60plus">60%+</option>
                    </select>

                    <!-- DIL Filter -->
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
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <button id="export-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-file-excel"></i> Export CSV
                    </button>

                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-arrow-down"></i> Decrease Mode
                    </button>
                    
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-arrow-up"></i> Increase Mode
                    </button>

                    <button type="button" id="toggle-utilized-columns-btn" class="btn btn-sm btn-secondary">
                        <i class="fa fa-filter"></i> Show Ads Columns
                    </button>
                </div>

                <!-- Ads/Utilized Count Section (shown when Show Ads Columns is on) -->
                <div id="utilized-count-section" class="mt-2 p-3 bg-light rounded border d-none">
                    <h6 class="mb-2"><i class="fa-solid fa-chart-line me-1"></i>Ads / Utilized Stats</h6>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="badge fs-6 p-2 ads-section-badge" id="total-sku-count" data-ads-filter="all" style="color: black; font-weight: bold; background-color: #adb5bd; cursor: pointer;" title="Click to show all">Total SKU: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="total-campaign-count" data-ads-filter="campaign" style="color: black; font-weight: bold; background-color: #9ec5fe; cursor: pointer;" title="Click to filter: has campaign">Campaign: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="ad-sku-count" data-ads-filter="ad-sku" style="color: black; font-weight: bold; background-color: #b8d4a8; cursor: pointer;" title="Click to filter: SKU active in ads with &gt;0 inventory">Ad SKU: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="missing-campaign-count" data-ads-filter="missing" style="color: black; font-weight: bold; background-color: #f1aeb5; cursor: pointer;" title="Click to filter: missing campaign (no campaign, INV&gt;0, not NRA)">Missing: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="nra-missing-count" data-ads-filter="nra-missing" style="color: black; font-weight: bold; background-color: #ffe69c; cursor: pointer;" title="Click to filter: NRA missing">NRA MISSING: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="zero-inv-count" data-ads-filter="zero-inv" style="color: black; font-weight: bold; background-color: #ffda6a; cursor: pointer;" title="Click to filter: zero inventory">Zero INV: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="nra-count" data-ads-filter="nra" style="color: black; font-weight: bold; background-color: #f1aeb5; cursor: pointer;" title="Click to filter: NRA">NRA: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="ra-count" data-ads-filter="ra" style="color: black; font-weight: bold; background-color: #a3cfbb; cursor: pointer;" title="Click to filter: RA">RA: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="total-spend-badge" data-ads-filter="total-spend" style="color: black; font-weight: bold; background-color: #9ec5fe; cursor: pointer;" title="Click to filter: has spend">Total Spend: $0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="total-budget-badge" data-ads-filter="budget" style="color: black; font-weight: bold; background-color: #ced4da; cursor: pointer;" title="Click to filter: has budget">Budget: $0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="total-ad-sales-badge" data-ads-filter="ad-sales" style="color: black; font-weight: bold; background-color: #9eeaf9; cursor: pointer;" title="Click to filter: has ad sales">Ad Sales: $0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="total-ad-clicks-badge" data-ads-filter="ad-clicks" style="color: black; font-weight: bold; background-color: #a5d6e8; cursor: pointer;" title="Click to filter: has ad clicks">Ad Clicks: 0</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="avg-acos-badge" data-ads-filter="avg-acos" style="color: black; font-weight: bold; background-color: #ffe69c; cursor: pointer;" title="Click to filter: has spend/sales">Avg ACOS: 0%</span>
                        <span class="badge fs-6 p-2 ads-section-badge" id="roas-badge" data-ads-filter="roas" style="color: black; font-weight: bold; background-color: #a3cfbb; cursor: pointer;" title="Click to filter: has spend/sales">ROAS: 0.00</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-2 pt-2 border-top">
                        <label class="form-label mb-0 me-1 text-nowrap" style="font-size: 0.8rem;"><i class="fa-solid fa-upload me-1"></i>Campaign:</label>
                        <input type="file" id="l7-upload-file" accept=".xlsx,.xls,.csv" class="form-control form-control-sm d-none" style="width: 0;">
                        <button type="button" id="l7-upload-btn" class="btn btn-sm btn-primary" title="Upload L7 Report" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-upload me-1"></i>L7
                        </button>
                        <input type="file" id="l30-upload-file" accept=".xlsx,.xls,.csv" class="form-control form-control-sm d-none" style="width: 0;">
                        <button type="button" id="l30-upload-btn" class="btn btn-sm btn-primary" title="Upload L30 Report" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-upload me-1"></i>L30
                        </button>
                        <span id="upload-status-container" class="ms-2" style="font-size: 0.7rem;"></span>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary (80% Margin)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-badge" style="color: black; font-weight: bold;">Total PFT: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color: black; font-weight: bold;">Total Sales: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;">AVG GPFT: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-inv-badge" style="color: black; font-weight: bold;">Total INV: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-l30-badge" style="color: black; font-weight: bold;">Total TT L30: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter 0 sold items">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter items with sales">&gt; 0 Sold: 0</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-dil-badge" style="color: black; font-weight: bold;">DIL%: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-cogs-badge" style="color: black; font-weight: bold;">COGS: $0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge" style="color: black; font-weight: bold;">ROI%: 0%</span>
                        <span class="badge bg-danger fs-6 p-2" id="missing-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter missing SKUs">Missing: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="map-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter mapped SKUs">Map: 0</span>
                        <span class="badge bg-warning fs-6 p-2" id="inv-tt-stock-badge" style="color: black; font-weight: bold; cursor: pointer;" title="Click to filter INV > TT Stock">INV > TT Stock: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-skus-count" class="fw-bold"></span>
                        <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                            <option value="percentage">Percentage</option>
                            <option value="value">Value ($)</option>
                        </select>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                            placeholder="Enter %" step="0.01" style="width: 100px;">
                        <button id="apply-discount-btn" class="btn btn-primary btn-sm">Apply</button>
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="tiktok-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control" placeholder="Search SKU...">
                    </div>
                    <!-- Table body -->
                    <div id="tiktok-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "tiktok_tabulator_column_visibility";
    let table = null;
    let totalDistinctCampaigns = 0; // from API: COUNT(DISTINCT campaign_name) in tiktok_campaign_reports
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let selectedSkus = new Set();
    
    // Toast notification function
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
        // Discount type dropdown change handler
        $('#discount-type-select').on('change', function() {
            const discountType = $(this).val();
            const $input = $('#discount-percentage-input');
            
            if (discountType === 'percentage') {
                $input.attr('placeholder', 'Enter %');
            } else {
                $input.attr('placeholder', 'Enter $');
            }
        });

        // Decrease button toggle
        $('#decrease-btn').on('click', function() {
            decreaseModeActive = !decreaseModeActive;
            increaseModeActive = false;
            const selectColumn = table.getColumn('_select');
            
            if (decreaseModeActive) {
                $(this).removeClass('btn-warning').addClass('btn-danger').html('<i class="fas fa-arrow-down"></i> Decrease ON');
                selectColumn.show();
                $('#increase-btn').removeClass('btn-danger').addClass('btn-success').html('<i class="fas fa-arrow-up"></i> Increase Mode');
            } else {
                $(this).removeClass('btn-danger').addClass('btn-warning').html('<i class="fas fa-arrow-down"></i> Decrease Mode');
                selectColumn.hide();
                selectedSkus.clear();
                updateSelectedCount();
            }
        });
        
        // Increase Mode Toggle
        $('#increase-btn').on('click', function() {
            increaseModeActive = !increaseModeActive;
            decreaseModeActive = false;
            const selectColumn = table.getColumn('_select');
            
            if (increaseModeActive) {
                $(this).removeClass('btn-success').addClass('btn-danger').html('<i class="fas fa-arrow-up"></i> Increase ON');
                selectColumn.show();
                $('#decrease-btn').removeClass('btn-danger').addClass('btn-warning').html('<i class="fas fa-arrow-down"></i> Decrease Mode');
            } else {
                $(this).removeClass('btn-danger').addClass('btn-success').html('<i class="fas fa-arrow-up"></i> Increase Mode');
                selectColumn.hide();
                selectedSkus.clear();
                updateSelectedCount();
            }
        });

        // Toggle Utilized Columns - Show only columns that match tiktok/utilized page (like temu-decrease Show Ads Columns)
        let utilizedColumnsVisible = false;
        let originalColumnVisibilityUtilized = {};
        const utilizedColumnFields = ['(Child) sku', 'hasCampaign', 'INV', 'L30', 'TT Dil%', 'TT L30', 'NR', 'ads_price', 'budget', 'spend', 'ad_sold', 'ad_clicks', 'acos', 'out_roas', 'in_roas', 'status', 'campaign_name'];

        $('#toggle-utilized-columns-btn').on('click', function() {
            utilizedColumnsVisible = !utilizedColumnsVisible;

            if (utilizedColumnsVisible) {
                table.getColumns().forEach(function(column) {
                    const field = column.getField();
                    if (field) {
                        originalColumnVisibilityUtilized[field] = column.isVisible();
                    }
                });
                table.getColumns().forEach(function(column) {
                    const field = column.getField();
                    if (field && !utilizedColumnFields.includes(field)) {
                        column.hide();
                    } else if (field && utilizedColumnFields.includes(field)) {
                        column.show(); // show by iterating so hidden columns (e.g. ads_price) are found
                    }
                });
                $(this).html('<i class="fa fa-filter"></i> Show All Columns');
                $(this).removeClass('btn-secondary btn-primary').addClass('btn-danger');
                $('#utilized-count-section').removeClass('d-none');
                $('#summary-stats').addClass('d-none');
                updateUtilizedCounts();
            } else {
                // Restore by iterating stored keys (getColumns() may only return visible columns when some are hidden)
                Object.keys(originalColumnVisibilityUtilized).forEach(function(field) {
                    try {
                        const column = table.getColumn(field);
                        if (column) {
                            if (originalColumnVisibilityUtilized[field]) {
                                column.show();
                            } else {
                                column.hide();
                            }
                        }
                    } catch (e) {
                        console.log('Restore column not found: ' + field);
                    }
                });
                $(this).html('<i class="fa fa-filter"></i> Show Ads Columns');
                $(this).removeClass('btn-danger btn-primary').addClass('btn-secondary');
                $('#utilized-count-section').addClass('d-none');
                $('#summary-stats').removeClass('d-none');
                adsBadgeFilter = null;
                $('#utilized-count-section .ads-section-badge').removeClass('border border-3 border-dark');
                applyFilters();
            }
        });

        // Select all checkbox handler
        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            const filteredData = table.getData('active').filter(row => !(row.Parent && row.Parent.startsWith('PARENT')));
            
            filteredData.forEach(row => {
                if (isChecked) {
                    selectedSkus.add(row['(Child) sku']);
                } else {
                    selectedSkus.delete(row['(Child) sku']);
                }
            });
            
            $('.sku-select-checkbox').each(function() {
                const sku = $(this).data('sku');
                $(this).prop('checked', selectedSkus.has(sku));
            });
            
            updateSelectedCount();
        });

        // Individual checkbox handler
        $(document).on('change', '.sku-select-checkbox', function() {
            const sku = $(this).data('sku');
            if ($(this).prop('checked')) {
                selectedSkus.add(sku);
            } else {
                selectedSkus.delete(sku);
            }
            updateSelectedCount();
            updateSelectAllCheckbox();
        });

        // Apply discount button
        $('#apply-discount-btn').on('click', function() {
            applyDiscount();
        });

        // Apply discount on Enter key
        $('#discount-percentage-input').on('keypress', function(e) {
            if (e.which === 13) {
                applyDiscount();
            }
        });

        // Clear SPRICE button
        $('#clear-sprice-btn').on('click', function() {
            clearSpriceForSelected();
        });

        // 0 Sold badge click handler
        let zeroSoldFilterActive = false;
        $('#zero-sold-count-badge').on('click', function() {
            zeroSoldFilterActive = !zeroSoldFilterActive;
            moreSoldFilterActive = false;
            applyFilters();
        });

        // > 0 Sold badge click handler
        let moreSoldFilterActive = false;
        $('#more-sold-count-badge').on('click', function() {
            moreSoldFilterActive = !moreSoldFilterActive;
            zeroSoldFilterActive = false;
            applyFilters();
        });

        // Missing badge click handler
        let missingFilterActive = false;
        $('#missing-count-badge').on('click', function() {
            missingFilterActive = !missingFilterActive;
            mapFilterActive = false;
            invTTStockFilterActive = false;
            applyFilters();
        });

        // Map badge click handler
        let mapFilterActive = false;
        $('#map-count-badge').on('click', function() {
            mapFilterActive = !mapFilterActive;
            missingFilterActive = false;
            invTTStockFilterActive = false;
            applyFilters();
        });

        // INV > TT Stock badge click handler
        let invTTStockFilterActive = false;
        $('#inv-tt-stock-badge').on('click', function() {
            invTTStockFilterActive = !invTTStockFilterActive;
            missingFilterActive = false;
            mapFilterActive = false;
            applyFilters();
        });

        // Ads section badge filter (like tiktok utilized page) - toggle on click
        let adsBadgeFilter = null;
        $(document).on('click', '.ads-section-badge', function() {
            const filter = $(this).data('ads-filter');
            adsBadgeFilter = (adsBadgeFilter === filter) ? null : filter;
            $('#utilized-count-section .ads-section-badge').removeClass('border border-3 border-dark');
            if (adsBadgeFilter) {
                $('#utilized-count-section .ads-section-badge[data-ads-filter="' + adsBadgeFilter + '"]').addClass('border border-3 border-dark');
            }
            applyFilters();
            if (typeof updateUtilizedCounts === 'function') updateUtilizedCounts();
        });

        // ========== MANUAL DROPDOWN FUNCTIONALITY ==========
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
            const column = $item.data('column');
            const color = $item.data('color');
            const container = $item.closest('.manual-dropdown-container');
            const button = container.find('.btn');
            
            container.find('.column-filter').removeClass('active');
            $item.addClass('active');
            
            const statusCircle = $item.find('.status-circle').clone();
            const text = $item.text().trim();
            button.html('').append(statusCircle).append(' DIL%');
            
            container.removeClass('show');
            applyFilters();
        });

        $(document).on('click', function() {
            $('.manual-dropdown-container').removeClass('show');
        });

        // Update selected count display
        function updateSelectedCount() {
            const count = selectedSkus.size;
            $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
            $('#discount-input-container').toggle(count > 0);
        }

        // Update select all checkbox state
        function updateSelectAllCheckbox() {
            if (!table) return;
            
            const filteredData = table.getData('active').filter(row => !(row.Parent && row.Parent.startsWith('PARENT')));
            
            if (filteredData.length === 0) {
                $('#select-all-checkbox').prop('checked', false);
                return;
            }
            
            const filteredSkus = new Set(filteredData.map(row => row['(Child) sku']).filter(sku => sku));
            const allFilteredSelected = filteredSkus.size > 0 && 
                [...filteredSkus].every(sku => selectedSkus.has(sku));
            
            $('#select-all-checkbox').prop('checked', allFilteredSelected);
        }

        // Custom price rounding function
        function roundToRetailPrice(price) {
            const roundedDollar = Math.ceil(price);
            return roundedDollar - 0.01;
        }

        // Apply discount to selected SKUs
        function applyDiscount() {
            const discountType = $('#discount-type-select').val();
            const discountValue = parseFloat($('#discount-percentage-input').val());
            
            if (isNaN(discountValue) || discountValue === 0) {
                showToast('Please enter a valid discount value', 'error');
                return;
            }

            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU', 'error');
                return;
            }
            
            let updatedCount = 0;
            const updates = [];
            
            selectedSkus.forEach(sku => {
                const rows = table.searchRows("(Child) sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0];
                    const rowData = row.getData();
                    const currentPrice = parseFloat(rowData['TT Price']) || 0;
                    
                    if (currentPrice > 0) {
                        let newSprice;
                        
                        if (discountType === 'percentage') {
                            if (increaseModeActive) {
                                newSprice = currentPrice * (1 + discountValue / 100);
                            } else {
                                newSprice = currentPrice * (1 - discountValue / 100);
                            }
                        } else {
                            if (increaseModeActive) {
                                newSprice = currentPrice + discountValue;
                            } else {
                                newSprice = currentPrice - discountValue;
                            }
                        }
                        
                        newSprice = roundToRetailPrice(newSprice);
                        newSprice = Math.max(0.99, newSprice);
                        
                        const percentage = rowData['percentage'] || 0.80;
                        const lp = rowData['LP_productmaster'] || 0;
                        const ship = rowData['Ship_productmaster'] || 0;
                        
                        const sgpft = newSprice > 0 ? Math.round(((newSprice * percentage - ship - lp) / newSprice) * 100 * 100) / 100 : 0;
                        const spft = sgpft;
                        const sroi = lp > 0 ? Math.round(((newSprice * percentage - lp - ship) / lp) * 100 * 100) / 100 : 0;
                        
                        row.update({
                            SPRICE: newSprice,
                            SGPFT: sgpft,
                            SPFT: spft,
                            SROI: sroi,
                            has_custom_sprice: true
                        });
                        
                        updates.push({
                            sku: sku,
                            sprice: newSprice
                        });
                        
                        updatedCount++;
                    }
                }
            });
            
            if (updates.length > 0) {
                saveSpriceUpdates(updates);
            }
            
            showToast(`${increaseModeActive ? 'Increase' : 'Discount'} applied to ${updatedCount} SKU(s) based on TT Price`, 'success');
            $('#discount-percentage-input').val('');
        }

        // Clear SPRICE for selected SKUs
        function clearSpriceForSelected() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            if (!confirm(`Are you sure you want to clear SPRICE for ${selectedSkus.size} selected SKU(s)?`)) {
                return;
            }

            let clearedCount = 0;
            const updates = [];

            table.getRows().forEach(row => {
                const rowData = row.getData();
                const sku = rowData['(Child) sku'];
                
                if (selectedSkus.has(sku)) {
                    row.update({
                        SPRICE: 0,
                        SGPFT: 0,
                        SPFT: 0,
                        SROI: 0
                    });
                    
                    updates.push({
                        sku: sku,
                        sprice: 0
                    });
                    
                    clearedCount++;
                }
            });

            if (updates.length > 0) {
                saveSpriceUpdates(updates);
            }

            showToast(`SPRICE cleared for ${clearedCount} SKU(s)`, 'success');
        }

        // Save SPRICE updates to backend
        function saveSpriceUpdates(updates) {
            $.ajax({
                url: '/tiktok-save-sprice',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    updates: updates
                },
                success: function(response) {
                    if (response.success) {
                        console.log('SPRICE updates saved successfully:', response.updated, 'records');
                        if (response.errors && response.errors.length > 0) {
                            console.warn('Some updates had errors:', response.errors);
                        }
                    }
                },
                error: function(xhr) {
                    console.error('Error saving SPRICE updates:', xhr);
                    let errorMessage = 'Error saving SPRICE updates to database';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage += ': ' + xhr.responseJSON.error;
                    }
                    showToast(errorMessage, 'error');
                }
            });
        }

        // Initialize Tabulator
        table = new Tabulator("#tiktok-table", {
            ajaxURL: "/tiktok-data-json",
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
                column: "TT L30",
                dir: "desc"
            }],
            rowFormatter: function(row) {
                if (row.getData().Parent && row.getData().Parent.startsWith('PARENT')) {
                    row.getElement().style.backgroundColor = "rgba(69, 233, 255, 0.1)";
                }
            },
            columns: [
                {
                    title: "Parent",
                    field: "Parent",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search Parent...",
                    cssClass: "text-primary",
                    tooltip: true,
                    frozen: true,
                    width: 150,
                    visible: false
                },
                {
                    title: "Image",
                    field: "image_path",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value) {
                            const esc = (v) => String(v).replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            return `<img src="${esc(value)}" alt="Product" style="width: 50px; height: 50px; object-fit: cover;" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling && (this.nextElementSibling.style.display='inline');"><span style="display:none; font-size:10px; color:#999;">No image</span>`;
                        }
                        return '';
                    },
                    headerSort: false,
                    width: 80
                },
                {
                    title: "SKU",
                    field: "(Child) sku",
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
                                   data-sku="${sku}"
                                   title="Copy SKU"></i>`;
                        
                        return html;
                    }
                },
                {
                    title: "INV",
                    field: "INV",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number"
                },
                {
                    title: "OV L30",
                    field: "L30",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number"
                },
                {
                    title: "Dil",
                    field: "TT Dil%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const INV = parseFloat(rowData.INV) || 0;
                        const OVL30 = parseFloat(rowData['L30']) || 0;
                        
                        if (INV === 0) return '<span style="color: #6c757d;">0%</span>';
                        
                        const dil = (OVL30 / INV) * 100;
                        let color = '';
                        
                        if (dil < 16.66) color = '#a00211';
                        else if (dil >= 16.66 && dil < 25) color = '#ffc107';
                        else if (dil >= 25 && dil < 50) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "TT L30",
                    field: "TT L30",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number"
                },
                {
                    title: "TT Stock",
                    field: "TT Stock",
                    hozAlign: "center",
                    width: 60,
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
                    title: "Missing",
                    field: "Missing",
                    hozAlign: "center",
                    width: 70,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === 'M') {
                            return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 6px; border-radius: 3px;">M</span>';
                        }
                        return '';
                    }
                },
                {
                    title: "Missing",
                    field: "hasCampaign",
                    hozAlign: "center",
                    width: 80,
                    visible: false,
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        const hasCampaign = row.hasCampaign === true || row.hasCampaign === 'true' || row.hasCampaign === 1;
                        const nraValue = (row.NR || '').trim();
                        let dotColor, title;
                        if (nraValue === 'NRA') {
                            dotColor = 'yellow';
                            title = 'NRA - Not Required';
                        } else {
                            dotColor = hasCampaign ? 'green' : 'red';
                            title = hasCampaign ? 'Campaign Exists' : 'Campaign Missing';
                        }
                        return `<div style="display: flex; align-items: center; justify-content: center;"><span class="status-circle ${dotColor}" title="${title}"></span></div>`;
                    }
                },
                {
                    title: "NRA",
                    field: "NR",
                    hozAlign: "center",
                    width: 70,
                    visible: false,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const sku = row.getData()['(Child) sku'];
                        const value = (cell.getValue()?.trim()) || 'RA';
                        return `
                            <select class="form-select form-select-sm editable-select" data-sku="${sku}" data-field="NR"
                                style="width: 50px; border: 1px solid gray; padding: 2px; font-size: 20px; text-align: center;">
                                <option value="RA" ${value === 'RA' ? 'selected' : ''}>🟢</option>
                                <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>🔴</option>
                                <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>🟡</option>
                            </select>
                        `;
                    }
                },
                {
                    title: "Price",
                    field: "ads_price",
                    hozAlign: "right",
                    width: 80,
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return value > 0 ? '$' + value.toFixed(2) : (value === 0 ? '<span style="color:#999;">0</span>' : '-');
                    }
                },
                {
                    title: "Budget",
                    field: "budget",
                    hozAlign: "right",
                    width: 100,
                    visible: false,
                    editor: "number",
                    editorParams: { min: 0, step: 0.01 },
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined || value === '') return '<span style="color:#999;">-</span>';
                        return '$' + parseFloat(value).toFixed(2);
                    }
                },
                {
                    title: "Spend",
                    field: "spend",
                    hozAlign: "right",
                    width: 100,
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return value.toFixed(2);
                    }
                },
                {
                    title: "Ad Sold",
                    field: "ad_sold",
                    hozAlign: "right",
                    width: 100,
                    visible: false,
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue() || 0);
                        return value.toLocaleString();
                    }
                },
                {
                    title: "Ad Clicks",
                    field: "ad_clicks",
                    hozAlign: "right",
                    width: 100,
                    visible: false,
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue() || 0);
                        return value.toLocaleString();
                    }
                },
                {
                    title: "ACOS",
                    field: "acos",
                    hozAlign: "right",
                    width: 100,
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return Math.round(value) + '%';
                    }
                },
                {
                    title: "Out ROAS",
                    field: "out_roas",
                    hozAlign: "right",
                    width: 100,
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return value.toFixed(2);
                    }
                },
                {
                    title: "In ROAS",
                    field: "in_roas",
                    hozAlign: "right",
                    width: 100,
                    visible: false,
                    editor: "number",
                    editorParams: { min: 0, step: 0.01 },
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return value.toFixed(2);
                    }
                },
                {
                    title: "Status",
                    field: "status",
                    hozAlign: "center",
                    width: 130,
                    visible: false,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const sku = row.getData()['(Child) sku'];
                        const value = cell.getValue() || 'Not Created';
                        const colors = { "Active": "#10b981", "Inactive": "#ef4444", "Not Created": "#eab308" };
                        const selectedColor = colors[value] || "#6b7280";
                        return `
                            <select class="form-select form-select-sm editable-select" data-sku="${sku}" data-field="status"
                                style="width: 120px; border: 1px solid #d1d5db; padding: 4px 8px; font-size: 0.875rem; color: ${selectedColor}; font-weight: 500;">
                                <option value="Active" ${value === 'Active' ? 'selected' : ''} style="color: #10b981;">Active</option>
                                <option value="Inactive" ${value === 'Inactive' ? 'selected' : ''} style="color: #ef4444;">Inactive</option>
                                <option value="Not Created" ${value === 'Not Created' ? 'selected' : ''} style="color: #eab308;">Not Created</option>
                            </select>
                        `;
                    }
                },
                {
                    title: "Campaign",
                    field: "campaign_name",
                    headerSort: false,
                    width: 200,
                    visible: false,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value || value === '') return '<span style="color: #999;">-</span>';
                        return value;
                    }
                },
                {
                    title: "MAP",
                    field: "MAP",
                    hozAlign: "center",
                    width: 90,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        
                        if (value === 'Map') {
                            return '<span style="color: #28a745; font-weight: bold;">Map</span>';
                        } else if (value && value.startsWith('N Map|')) {
                            const diff = value.split('|')[1];
                            return `<span style="color: #dc3545; font-weight: bold;">N Map (${diff})</span>`;
                        } else if (value && value.startsWith('Diff|')) {
                            const diff = value.split('|')[1];
                            return `<span style="color: #ffc107; font-weight: bold;">${diff}<br>(INV > TT Stock)</span>`;
                        }
                        return '';
                    }
                },
                {
                    title: "Prc",
                    field: "TT Price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        
                        if (value === 0) {
                            return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                        }
                        
                        return `$${value.toFixed(2)}`;
                    },
                    width: 70
                },
                {
                    title: "GPFT%",
                    field: "GPFT%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "PFT%",
                    field: "PFT %",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "ROI%",
                    field: "ROI%",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 50) color = '#a00211';
                        else if (percent >= 50 && percent < 100) color = '#ffc107';
                        else if (percent >= 100 && percent < 150) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "Profit",
                    field: "Profit",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = value >= 0 ? '#28a745' : '#a00211';
                        return `<span style="color: ${color}; font-weight: 600;">$${value.toFixed(2)}</span>`;
                    },
                    width: 70
                },
                {
                    title: "Sales",
                    field: "Sales L30",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `$${value.toFixed(2)}`;
                    },
                    width: 80
                },
                {
                    title: "LP",
                    field: "LP_productmaster",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `$${value.toFixed(2)}`;
                    },
                    width: 60
                },
                {
                    title: "Ship",
                    field: "Ship_productmaster",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `$${value.toFixed(2)}`;
                    },
                    width: 60
                },
                {
                    title: "<input type='checkbox' id='select-all-checkbox'>",
                    field: "_select",
                    hozAlign: "center",
                    headerSort: false,
                    width: 40,
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'];
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type='checkbox' class='sku-select-checkbox' data-sku='${sku}' ${isChecked}>`;
                    }
                },
                {
                    title: "SPRICE",
                    field: "SPRICE",
                    hozAlign: "center",
                    editor: "number",
                    editorParams: {
                        min: 0,
                        step: 0.01
                    },
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        const rowData = cell.getRow().getData();
                        const hasCustom = rowData.has_custom_sprice;
                        const status = rowData.SPRICE_STATUS;
                        
                        let bgColor = '';
                        if (status === 'pushed') bgColor = 'background-color: #fff3cd;';
                        else if (status === 'applied') bgColor = 'background-color: #d4edda;';
                        else if (status === 'error') bgColor = 'background-color: #f8d7da;';
                        else if (hasCustom) bgColor = 'background-color: #e7f1ff;';
                        
                        return `<span style="font-weight: 600; ${bgColor} padding: 2px 6px; border-radius: 3px;">$${value.toFixed(2)}</span>`;
                    },
                    width: 80
                },
                {
                    title: "SGPFT",
                    field: "SGPFT",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "SPFT",
                    field: "SPFT",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                },
                {
                    title: "SROI",
                    field: "SROI",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        let color = '';
                        
                        if (percent < 50) color = '#a00211';
                        else if (percent >= 50 && percent < 100) color = '#ffc107';
                        else if (percent >= 100 && percent < 150) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 50
                }
            ]
        });

        // SKU Search functionality
        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("(Child) sku", "like", value);
        });

        // SPRICE cell edited - save to database
        table.on('cellEdited', function(cell) {
            const field = cell.getField();
            if (field === 'SPRICE') {
                const row = cell.getRow();
                const rowData = row.getData();
                const sku = rowData['(Child) sku'];
                const newSprice = parseFloat(cell.getValue()) || 0;
                
                const percentage = rowData['percentage'] || 0.80;
                const lp = rowData['LP_productmaster'] || 0;
                const ship = rowData['Ship_productmaster'] || 0;
                
                const sgpft = newSprice > 0 ? Math.round(((newSprice * percentage - ship - lp) / newSprice) * 100 * 100) / 100 : 0;
                const spft = sgpft;
                const sroi = lp > 0 ? Math.round(((newSprice * percentage - lp - ship) / lp) * 100 * 100) / 100 : 0;
                
                row.update({
                    SGPFT: sgpft,
                    SPFT: spft,
                    SROI: sroi,
                    has_custom_sprice: true
                });
                
                saveSpriceUpdates([{sku: sku, sprice: newSprice}]);
            } else if (field === 'in_roas') {
                const row = cell.getRow();
                const rowData = row.getData();
                const sku = rowData['(Child) sku'];
                const value = parseFloat(cell.getValue() || 0);
                const oldValue = parseFloat(rowData.in_roas || 0);
                $.ajax({
                    url: '{{ route("tiktok.utilized.update") }}',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: JSON.stringify({ sku: sku, field: 'in_roas', value: value }),
                    success: function(response) {
                        if (response && response.success) {
                            showToast('In ROAS updated', 'success');
                        }
                    },
                    error: function(xhr, status, error) {
                        cell.setValue(oldValue);
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : ('Failed to update In ROAS: ' + (xhr.statusText || error));
                        showToast(msg, 'error');
                    }
                });
            } else if (field === 'budget') {
                const row = cell.getRow();
                const rowData = row.getData();
                const sku = rowData['(Child) sku'];
                const rawVal = cell.getValue();
                const value = rawVal === '' || rawVal === null || rawVal === undefined ? null : parseFloat(rawVal);
                const oldValue = rowData.budget;
                $.ajax({
                    url: '{{ route("tiktok.utilized.update") }}',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: JSON.stringify({ sku: sku, field: 'budget', value: value }),
                    success: function(response) {
                        if (response && response.success) {
                            showToast('Budget updated', 'success');
                        }
                    },
                    error: function(xhr, status, error) {
                        cell.setValue(oldValue);
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : ('Failed to update Budget: ' + (xhr.statusText || error));
                        showToast(msg, 'error');
                    }
                });
            }
        });

        // NRA and Status editable selects (utilized columns) - save to tiktok.utilized.update
        $(document).on('change', '.editable-select', function() {
            const sku = $(this).data('sku');
            const field = $(this).data('field');
            const value = $(this).val();
            if (!sku || !field) return;
            const rows = table.searchRows("(Child) sku", "=", sku);
            const row = rows && rows.length ? rows[0] : null;
            let oldValue = null;
            if (row) {
                const rowData = row.getData();
                oldValue = rowData[field];
                rowData[field] = value;
                row.update(rowData);
            }
            const $select = $(this);
            $.ajax({
                url: '{{ route("tiktok.utilized.update") }}',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: JSON.stringify({ sku: sku, field: field, value: value }),
                success: function(response) {
                    if (response && response.success) {
                        showToast(field === 'NR' ? 'NRA updated' : 'Status updated', 'success');
                    }
                },
                error: function(xhr, status, error) {
                    if (row && oldValue !== null) {
                        const rowData = row.getData();
                        rowData[field] = oldValue;
                        row.update(rowData);
                        $select.val(oldValue);
                    }
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : ('Failed to update: ' + (xhr.statusText || error));
                    showToast(msg, 'error');
                }
            });
        });

        // L7 / L30 Upload: button triggers file input, then upload on file select
        function doUploadReport(fileInput, reportRange, statusContainerId) {
            const file = fileInput.files[0];
            if (!file) return;
            const $status = $('#' + statusContainerId);
            $status.removeClass('d-none').html('<span class="text-primary">Uploading...</span>');
            const formData = new FormData();
            formData.append('file', file);
            formData.append('report_range', reportRange);
            $.ajax({
                url: '{{ route("tiktok.utilized.upload") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    if (response && response.success) {
                        showToast(response.message || 'Upload successful', 'success');
                        if (table) table.replaceData();
                        $status.html('<span class="text-success">' + (response.message || 'Done') + '</span>');
                    } else {
                        showToast(response.message || 'Upload failed', 'error');
                        $status.html('<span class="text-danger">' + (response.message || 'Failed') + '</span>');
                    }
                    setTimeout(function() { $status.addClass('d-none').html(''); }, 4000);
                },
                error: function(xhr) {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Upload failed';
                    showToast(msg, 'error');
                    $status.html('<span class="text-danger">' + msg + '</span>');
                    setTimeout(function() { $status.addClass('d-none').html(''); }, 4000);
                }
            });
            fileInput.value = '';
        }
        $('#l7-upload-btn').on('click', function() {
            $('#l7-upload-file').off('change').on('change', function() {
                doUploadReport(this, 'L7', 'upload-status-container');
            }).trigger('click');
        });
        $('#l30-upload-btn').on('click', function() {
            $('#l30-upload-file').off('change').on('change', function() {
                doUploadReport(this, 'L30', 'upload-status-container');
            }).trigger('click');
        });

        // Copy SKU button handler
        $(document).on('click', '.copy-sku-btn', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            navigator.clipboard.writeText(sku).then(() => {
                showToast(`Copied: ${sku}`, 'success');
            });
        });

        // Apply filters
        function applyFilters() {
            const inventoryFilter = $('#inventory-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const dilFilter = $('.column-filter[data-column="dil_percent"].active')?.data('color') || 'all';

            table.clearFilter();

            // Inventory filter
            if (inventoryFilter === 'zero') {
                table.addFilter("INV", "=", 0);
            } else if (inventoryFilter === 'more') {
                table.addFilter("INV", ">", 0);
            }

            // TikTok Stock filter
            const tiktokStockFilter = $('#tiktok-stock-filter').val();
            if (tiktokStockFilter === 'zero') {
                table.addFilter("TT Stock", "=", 0);
            } else if (tiktokStockFilter === 'more') {
                table.addFilter("TT Stock", ">", 0);
            }

            // GPFT filter
            if (gpftFilter !== 'all') {
                if (gpftFilter === 'negative') {
                    table.addFilter("GPFT%", "<", 0);
                } else if (gpftFilter === '60plus') {
                    table.addFilter("GPFT%", ">=", 60);
                } else {
                    const [min, max] = gpftFilter.split('-').map(Number);
                    table.addFilter("GPFT%", ">=", min);
                    table.addFilter("GPFT%", "<", max);
                }
            }

            // DIL filter
            if (dilFilter !== 'all') {
                table.addFilter(function(data) {
                    const inv = parseFloat(data['INV']) || 0;
                    const l30 = parseFloat(data['L30']) || 0;
                    const dil = inv === 0 ? 0 : (l30 / inv) * 100;
                    
                    if (dilFilter === 'red') return dil < 16.66;
                    if (dilFilter === 'yellow') return dil >= 16.66 && dil < 25;
                    if (dilFilter === 'green') return dil >= 25 && dil < 50;
                    if (dilFilter === 'pink') return dil >= 50;
                    return true;
                });
            }

            // 0 Sold filter
            if (zeroSoldFilterActive) {
                table.addFilter("TT L30", "=", 0);
            }

            // > 0 Sold filter
            if (moreSoldFilterActive) {
                table.addFilter("TT L30", ">", 0);
            }

            // Missing filter
            if (missingFilterActive) {
                table.addFilter("Missing", "=", "M");
            }

            // Map filter
            if (mapFilterActive) {
                table.addFilter("MAP", "=", "Map");
            }

            // INV > TT Stock filter
            if (invTTStockFilterActive) {
                table.addFilter(function(data) {
                    const mapValue = data['MAP'];
                    return mapValue && mapValue.startsWith('Diff|');
                });
            }

            // Ads section badge filter (only when Show Ads Columns is on)
            if (typeof utilizedColumnsVisible !== 'undefined' && utilizedColumnsVisible && adsBadgeFilter) {
                switch (adsBadgeFilter) {
                    case 'all':
                        break;
                    case 'campaign':
                        table.addFilter(function(data) {
                            const hasCampaign = data.hasCampaign === true || data.hasCampaign === 'true' || data.hasCampaign === 1;
                            return hasCampaign;
                        });
                        break;
                    case 'ad-sku':
                        table.addFilter(function(data) {
                            const hasCampaign = data.hasCampaign === true || data.hasCampaign === 'true' || data.hasCampaign === 1;
                            const inv = parseFloat(data.INV) || 0;
                            return hasCampaign && inv > 0;
                        });
                        break;
                    case 'missing':
                        table.addFilter(function(data) {
                            const hasCampaign = data.hasCampaign === true || data.hasCampaign === 'true' || data.hasCampaign === 1;
                            const nr = (data.NR || '').trim();
                            const inv = parseFloat(data.INV) || 0;
                            return !hasCampaign && inv > 0 && nr !== 'NRA';
                        });
                        break;
                    case 'nra-missing':
                        table.addFilter(function(data) {
                            const hasCampaign = data.hasCampaign === true || data.hasCampaign === 'true' || data.hasCampaign === 1;
                            const nr = (data.NR || '').trim();
                            return !hasCampaign && nr === 'NRA';
                        });
                        break;
                    case 'zero-inv':
                        table.addFilter("INV", "<=", 0);
                        break;
                    case 'nra':
                        table.addFilter(function(data) { return (data.NR || '').trim() === 'NRA'; });
                        break;
                    case 'ra':
                        table.addFilter(function(data) { return (data.NR || '').trim() === 'RA'; });
                        break;
                    case 'total-spend':
                        table.addFilter(function(data) {
                            const spend = parseFloat(data.spend) || 0;
                            return spend > 0;
                        });
                        break;
                    case 'budget':
                        table.addFilter(function(data) {
                            const b = data.budget;
                            return b !== null && b !== undefined && b !== '' && (parseFloat(b) || 0) > 0;
                        });
                        break;
                    case 'ad-clicks':
                        table.addFilter(function(data) {
                            const clicks = parseInt(data.ad_clicks, 10) || 0;
                            return clicks > 0;
                        });
                        break;
                    case 'ad-sales':
                    case 'avg-acos':
                    case 'roas':
                        table.addFilter(function(data) {
                            const spend = parseFloat(data.spend) || 0;
                            const outRoas = parseFloat(data.out_roas) || 0;
                            return spend > 0 && outRoas > 0;
                        });
                        break;
                }
            }

            updateSummary();
        }

        $('#inventory-filter, #gpft-filter, #tiktok-stock-filter').on('change', function() {
            applyFilters();
        });

        // Update summary badges
        function updateSummary() {
            const data = table.getData('active').filter(row => {
                return !(row.Parent && row.Parent.startsWith('PARENT'));
            });

            let totalPft = 0, totalSales = 0, totalGpft = 0, totalPrice = 0, priceCount = 0;
            let totalInv = 0, totalL30 = 0, zeroSoldCount = 0, moreSoldCount = 0, totalDil = 0, dilCount = 0;
            let totalCogs = 0, totalRoi = 0, roiCount = 0;
            let missingCount = 0, mapCount = 0, invTTStockCount = 0;

            data.forEach(row => {
                totalPft += parseFloat(row.Profit) || 0;
                totalSales += parseFloat(row['Sales L30']) || 0;
                totalGpft += parseFloat(row['GPFT%']) || 0;
                
                const price = parseFloat(row['TT Price']) || 0;
                if (price > 0) {
                    totalPrice += price;
                    priceCount++;
                }
                
                totalInv += parseFloat(row.INV) || 0;
                totalL30 += parseFloat(row['TT L30']) || 0;
                
                const l30 = parseFloat(row['TT L30']) || 0;
                if (l30 === 0) {
                    zeroSoldCount++;
                } else {
                    moreSoldCount++;
                }
                
                const dil = parseFloat(row['TT Dil%']) || 0;
                if (dil > 0) {
                    totalDil += dil;
                    dilCount++;
                }
                
                const lp = parseFloat(row['LP_productmaster']) || 0;
                totalCogs += lp * l30;
                
                const roi = parseFloat(row['ROI%']) || 0;
                if (roi !== 0) {
                    totalRoi += roi;
                    roiCount++;
                }
                
                if (row['Missing'] === 'M') {
                    missingCount++;
                }
                
                const mapValue = row['MAP'];
                if (mapValue === 'Map') {
                    mapCount++;
                }
                
                if (mapValue && mapValue.startsWith('Diff|')) {
                    invTTStockCount++;
                }
            });

            const avgGpft = data.length > 0 ? totalGpft / data.length : 0;
            const avgPrice = priceCount > 0 ? totalPrice / priceCount : 0;
            const avgDil = dilCount > 0 ? totalDil / dilCount : 0;
            const avgRoi = roiCount > 0 ? totalRoi / roiCount : 0;

            $('#total-pft-amt-badge').text(`Total PFT: $${Math.round(totalPft).toLocaleString()}`);
            $('#total-sales-amt-badge').text(`Total Sales: $${Math.round(totalSales).toLocaleString()}`);
            $('#avg-gpft-badge').text(`AVG GPFT: ${avgGpft.toFixed(1)}%`);
            $('#avg-price-badge').text(`Avg Price: $${avgPrice.toFixed(2)}`);
            $('#total-inv-badge').text(`Total INV: ${totalInv.toLocaleString()}`);
            $('#total-l30-badge').text(`Total TT L30: ${totalL30.toLocaleString()}`);
            $('#zero-sold-count-badge').text(`0 Sold: ${zeroSoldCount}`);
            $('#more-sold-count-badge').text(`> 0 Sold: ${moreSoldCount}`);
            $('#avg-dil-badge').text(`DIL%: ${(avgDil * 100).toFixed(1)}%`);
            $('#total-cogs-badge').text(`COGS: $${Math.round(totalCogs).toLocaleString()}`);
            $('#roi-percent-badge').text(`ROI%: ${avgRoi.toFixed(1)}%`);
            $('#missing-count-badge').text(`Missing: ${missingCount}`);
            $('#map-count-badge').text(`Map: ${mapCount}`);
            $('#inv-tt-stock-badge').text(`INV > TT Stock: ${invTTStockCount}`);
        }

        // Update Ads/Utilized count section (from table data: campaign, NR, spend, etc.)
        function updateUtilizedCounts() {
            if (!table) return;
            const data = table.getData('all').filter(row => {
                const sku = row['(Child) sku'] || '';
                return sku && !String(row.Parent || '').startsWith('PARENT');
            });
            const processedSkus = new Set();
            const zeroInvSkus = new Set();
            const adSkuSet = new Set(); // SKU active in ads (hasCampaign) with >0 inventory
            let validSkuCount = 0, missingCount = 0, nraMissingCount = 0, nraCount = 0;
            let totalSpend = 0, totalAdSales = 0, totalBudget = 0, totalAdClicks = 0;

            data.forEach(row => {
                const sku = row['(Child) sku'] || '';
                if (!sku) return;
                const hasCampaign = row.hasCampaign === true || row.hasCampaign === 'true' || row.hasCampaign === 1;
                const nr = (row.NR || '').trim();
                const inv = parseFloat(row.INV) || 0;

                if (!processedSkus.has(sku)) {
                    processedSkus.add(sku);
                    validSkuCount++;
                    if (nr === 'NRA') nraCount++;
                }
                if (hasCampaign && inv > 0) adSkuSet.add(sku);
                if (inv <= 0) zeroInvSkus.add(sku);
                if (!hasCampaign) {
                    if (nr === 'NRA') {
                        if (!processedSkus.has('nm_' + sku)) {
                            processedSkus.add('nm_' + sku);
                            nraMissingCount++;
                        }
                    } else if (inv > 0) {
                        if (!processedSkus.has('m_' + sku)) {
                            processedSkus.add('m_' + sku);
                            missingCount++;
                        }
                    }
                }
                totalSpend += parseFloat(row.spend) || 0;
                totalBudget += parseFloat(row.budget) || 0;
                totalAdClicks += parseInt(row.ad_clicks, 10) || 0;
                const outRoas = parseFloat(row.out_roas) || 0;
                const spend = parseFloat(row.spend) || 0;
                if (outRoas > 0 && spend > 0) totalAdSales += spend * outRoas;
            });
            const zeroInvCount = zeroInvSkus.size;

            const raCount = Math.max(0, validSkuCount - nraCount);
            const avgAcos = totalAdSales > 0 ? (totalSpend / totalAdSales) * 100 : 0;
            const roas = totalSpend > 0 ? totalAdSales / totalSpend : 0;

            $('#total-sku-count').text('Total SKU: ' + validSkuCount);
            $('#total-campaign-count').text('Campaign: ' + totalDistinctCampaigns);
            $('#ad-sku-count').text('Ad SKU: ' + adSkuSet.size);
            $('#missing-campaign-count').text('Missing: ' + missingCount);
            $('#nra-missing-count').text('NRA MISSING: ' + nraMissingCount);
            $('#zero-inv-count').text('Zero INV: ' + zeroInvCount);
            $('#nra-count').text('NRA: ' + nraCount);
            $('#ra-count').text('RA: ' + raCount);
            $('#total-spend-badge').text('Total Spend: $' + Math.round(totalSpend).toLocaleString());
            $('#total-budget-badge').text('Budget: $' + totalBudget.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#total-ad-sales-badge').text('Ad Sales: $' + Math.round(totalAdSales).toLocaleString());
            $('#total-ad-clicks-badge').text('Ad Clicks: ' + totalAdClicks.toLocaleString());
            $('#avg-acos-badge').text('Avg ACOS: ' + Math.round(avgAcos) + '%');
            $('#roas-badge').text('ROAS: ' + roas.toFixed(2));
        }

        // Build Column Visibility Dropdown
        function buildColumnDropdown() {
            const columns = table.getColumns();
            let html = '';
            
            columns.forEach(col => {
                const field = col.getField();
                const title = col.getDefinition().title;
                if (field && field !== '_select' && title) {
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
                if (field && field !== '_select') {
                    visibility[field] = col.isVisible();
                }
            });
            
            $.ajax({
                url: '/tiktok-pricing-column-visibility',
                method: 'POST',
                data: {
                    visibility: visibility,
                    _token: '{{ csrf_token() }}'
                }
            });
        }

        function applyColumnVisibilityFromServer() {
            $.ajax({
                url: '/tiktok-pricing-column-visibility',
                method: 'GET',
                success: function(visibility) {
                    if (visibility && Object.keys(visibility).length > 0) {
                        Object.keys(visibility).forEach(field => {
                            const col = table.getColumn(field);
                            if (col) {
                                if (visibility[field]) {
                                    col.show();
                                } else {
                                    col.hide();
                                }
                            }
                        });
                        buildColumnDropdown();
                    }
                }
            });
        }

        // Wait for table to be built
        table.on('tableBuilt', function() {
            buildColumnDropdown();
            applyColumnVisibilityFromServer();
        });

        table.on('dataLoaded', function() {
            function afterLoad() {
                setTimeout(function() {
                    applyFilters();
                    updateSummary();
                    updateUtilizedCounts();
                }, 100);
            }
            fetch('/tiktok-distinct-campaign-count')
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res && typeof res.totalDistinctCampaigns !== 'undefined') {
                        totalDistinctCampaigns = parseInt(res.totalDistinctCampaigns, 10) || 0;
                    }
                    afterLoad();
                })
                .catch(function() { afterLoad(); });
        });

        table.on('renderComplete', function() {
            setTimeout(function() {
                updateSummary();
                updateUtilizedCounts();
            }, 100);
        });

        // Toggle column from dropdown
        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.classList.contains('column-toggle')) {
                const field = e.target.dataset.field;
                const col = table.getColumn(field);
                if (col) {
                    if (e.target.checked) {
                        col.show();
                    } else {
                        col.hide();
                    }
                    saveColumnVisibilityToServer();
                }
            }
        });

        // Show All Columns button
        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => {
                if (col.getField() !== '_select') {
                    col.show();
                }
            });
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        // Export CSV button
        $('#export-btn').on('click', function() {
            const exportData = [];
            const visibleColumns = table.getColumns().filter(col => col.isVisible() && col.getField() !== '_select');
            
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
                    
                    if (value === null || value === undefined) {
                        value = '';
                    } else if (typeof value === 'number') {
                        value = parseFloat(value.toFixed(2));
                    } else if (typeof value === 'string') {
                        value = value.replace(/<[^>]*>/g, '').trim();
                    }
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
            link.setAttribute('download', 'tiktok_pricing_export_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showToast('Export downloaded successfully!', 'success');
        });
    });
</script>
@endsection


