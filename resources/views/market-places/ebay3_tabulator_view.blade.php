@extends('layouts.vertical', ['title' => 'eBay3 Pricing Decrease', 'sidenav' => 'condensed'])

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
        
        /* eBay3 specific styling - purple accent */
        .badge.bg-ebay3 {
            background-color: #6f42c1 !important;
        }
        
        /* PARENT row light blue background */
        .tabulator-row.parent-row {
            background-color: rgba(69, 233, 255, 0.25) !important;
        }
        .tabulator-row.parent-row:hover {
            background-color: rgba(69, 233, 255, 0.35) !important;
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
        'page_title' => 'eBay3 Pricing Decrease',
        'sub_title' => 'eBay3 Pricing Decrease',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>eBay3 Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select id="view-mode-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="sku" selected>SKU Only</option>
                        <option value="parent">Parent Only</option>
                        <option value="both">Both (Parent + SKU)</option>
                    </select>

                    <select id="inventory-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Status</option>
                        <option value="REQ" selected>REQ Only</option>
                        <option value="NR">NR Only</option>
                    </select>

                    <select id="gpft-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
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

                    <select id="cvr-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">CVR</option>
                        <option value="0-0">0 to 0.00%</option>
                        <option value="0.01-1">0.01 - 1%</option>
                        <option value="1-2">1-2%</option>
                        <option value="2-3">2-3%</option>
                        <option value="3-4">3-4%</option>
                        <option value="0-4">0-4%</option>
                        <option value="4-7">4-7%</option>
                        <option value="7-10">7-10%</option>
                        <option value="10plus">10%+</option>
                    </select>

                    <select id="status-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">Status</option>
                        <option value="REQ">REQ</option>
                        <option value="NR">NR</option>
                    </select>

                    <select id="ads-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">AD%</option>
                        <option value="0-10">Below 10%</option>
                        <option value="10-20">10-20%</option>
                        <option value="20-30">20-30%</option>
                        <option value="30-100">30-100%</option>
                        <option value="100plus">100%+</option>
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

                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-arrow-down"></i> Decrease Mode
                    </button>
                    
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-arrow-up"></i> Increase Mode
                    </button>

                    <button id="clear-all-sprice-btn" class="btn btn-sm btn-danger">
                        <i class="fas fa-trash"></i> Clear All SPRICE
                    </button>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">All Calculations Summary</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Financial Metrics -->
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-badge" style="color: black; font-weight: bold;">Total PFT AMT: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color: black; font-weight: bold;">Total SALES AMT: $0.00</span>
                        
                        <!-- eBay3 Metrics -->
                        <span class="badge bg-success fs-6 p-2" id="total-fba-l30-badge" style="color: black; font-weight: bold;">Total eBay3 L30: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter 0 sold items">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter items with sales">&gt; 0 Sold: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge" style="color: black; font-weight: bold;">Views: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge" style="color: black; font-weight: bold;">ROI %: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;">Avg GPFT: 0%</span>
                        
                        <!-- Ad Spend & Net Metrics -->
                        <span class="badge bg-danger fs-6 p-2" id="total-tcos-badge" style="color: white; font-weight: bold;">Total TCOS: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="total-spend-l30-badge" style="color: black; font-weight: bold;">Total Spend L30: ${{ number_format(($kwSpent ?? 0) + ($pmtSpent ?? 0), 2) }}</span>
                        <span class="badge fs-6 p-2" id="total-kw-spend-l30-badge" style="background-color: #dc3545; color: white; font-weight: bold;">KW Spend L30: ${{ number_format($kwSpent ?? 0, 2) }}</span>
                        <span class="badge fs-6 p-2" id="total-pmt-spend-l30-badge" style="background-color: #28a745; color: white; font-weight: bold;">PMT Spend L30: ${{ number_format($pmtSpent ?? 0, 2) }}</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-npft-badge" style="color: white; font-weight: bold;">Net PFT %: 0%</span>
                        
                        <!-- Price Comparison Badges -->
                        <span class="badge bg-danger fs-6 p-2" id="less-amz-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices less than Amazon">&lt; Amz: 0</span>
                        <span class="badge fs-6 p-2" id="more-amz-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices greater than Amazon">&gt; Amz: 0</span>
                        
                        <!-- Stock Mapping Badges -->
                        <span class="badge bg-danger fs-6 p-2" id="missing-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter missing SKUs">Missing: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="map-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter mapped SKUs">Map: 0</span>
                        <span class="badge bg-warning fs-6 p-2" id="inv-stock-badge" style="color: black; font-weight: bold; cursor: pointer;" title="Click to filter not mapped SKUs">N Map: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) -->
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
                        <button id="sugg-amz-prc-btn" class="btn btn-sm btn-info">
                            <i class="fas fa-copy"></i> Sugg Amz Prc
                        </button>
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="ebay3-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control" placeholder="Search SKU...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="ebay3-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "ebay3_tabulator_column_visibility";
    const KW_SPENT = {{ $kwSpent ?? 0 }};
    const PMT_SPENT = {{ $pmtSpent ?? 0 }};
    const TOTAL_ADS_SPENT = KW_SPENT + PMT_SPENT;
    let table = null;
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
                selectColumn.show();
                $(this).removeClass('btn-warning').addClass('btn-danger');
                $(this).html('<i class="fas fa-times"></i> Cancel Decrease');
                $('#increase-btn').removeClass('btn-danger').addClass('btn-success').html('<i class="fas fa-arrow-up"></i> Increase Mode');
            } else {
                selectColumn.hide();
                $(this).removeClass('btn-danger').addClass('btn-warning');
                $(this).html('<i class="fas fa-arrow-down"></i> Decrease Mode');
                selectedSkus.clear();
                updateSelectedCount();
                updateSelectAllCheckbox();
            }
        });
        
        // Increase Mode Toggle
        $('#increase-btn').on('click', function() {
            increaseModeActive = !increaseModeActive;
            decreaseModeActive = false;
            const selectColumn = table.getColumn('_select');
            
            if (increaseModeActive) {
                selectColumn.show();
                $(this).removeClass('btn-success').addClass('btn-danger');
                $(this).html('<i class="fas fa-times"></i> Cancel Increase');
                $('#decrease-btn').removeClass('btn-danger').addClass('btn-warning').html('<i class="fas fa-arrow-down"></i> Decrease Mode');
            } else {
                selectColumn.hide();
                selectedSkus.clear();
                $(this).removeClass('btn-danger').addClass('btn-success');
                $(this).html('<i class="fas fa-arrow-up"></i> Increase Mode');
                updateSelectedCount();
                updateSelectAllCheckbox();
            }
        });

        // Clear All SPRICE button handler
        $('#clear-all-sprice-btn').on('click', function() {
            if (!confirm('Are you sure you want to clear ALL SPRICE data? This action cannot be undone.')) {
                return;
            }
            
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Clearing...');
            
            $.ajax({
                url: '/clear-all-sprice-ebay3',
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    showToast('All SPRICE data cleared successfully!', 'success');
                    // Refresh the table to show updated data
                    table.setData('/ebay3-data-json');
                },
                error: function(xhr) {
                    showToast('Failed to clear SPRICE data: ' + (xhr.responseJSON?.error || 'Unknown error'), 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fas fa-trash"></i> Clear All SPRICE');
                }
            });
        });

        // Select all checkbox handler
        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            
            const filteredData = table.getData('active').filter(row => !(row.Parent && row.Parent.startsWith('PARENT')));
            
            filteredData.forEach(row => {
                const sku = row['(Child) sku'];
                if (sku) {
                    if (isChecked) {
                        selectedSkus.add(sku);
                    } else {
                        selectedSkus.delete(sku);
                    }
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

        // Sugg Amz Prc button
        $('#sugg-amz-prc-btn').on('click', function() {
            applySuggestAmazonPrice();
        });

        // Clear SPRICE button
        $('#clear-sprice-btn').on('click', function() {
            clearSpriceForSelected();
        });

        // 0 Sold badge click handler - filter to show only 0 sold items
        let zeroSoldFilterActive = false;
        $('#zero-sold-count-badge').on('click', function() {
            zeroSoldFilterActive = !zeroSoldFilterActive;
            moreSoldFilterActive = false; // Deactivate the other filter
            applyFilters();
            updateBadgeStyles();
        });

        // > 0 Sold badge click handler - filter to show items with sales > 0
        let moreSoldFilterActive = false;
        $('#more-sold-count-badge').on('click', function() {
            moreSoldFilterActive = !moreSoldFilterActive;
            zeroSoldFilterActive = false; // Deactivate the other filter
            applyFilters();
            updateBadgeStyles();
        });

        // < Amz badge click handler - filter prices less than Amazon
        let lessAmzFilterActive = false;
        $('#less-amz-badge').on('click', function() {
            lessAmzFilterActive = !lessAmzFilterActive;
            moreAmzFilterActive = false; // Deactivate the other filter
            applyFilters();
            updateBadgeStyles();
        });

        // > Amz badge click handler - filter prices greater than Amazon
        let moreAmzFilterActive = false;
        $('#more-amz-badge').on('click', function() {
            moreAmzFilterActive = !moreAmzFilterActive;
            lessAmzFilterActive = false; // Deactivate the other filter
            applyFilters();
            updateBadgeStyles();
        });

        // Missing badge click handler - filter SKUs missing in eBay
        let missingFilterActive = false;
        $('#missing-count-badge').on('click', function() {
            missingFilterActive = !missingFilterActive;
            mapFilterActive = false; // Deactivate other filters
            invStockFilterActive = false;
            applyFilters();
            updateBadgeStyles();
        });

        // Map badge click handler - filter SKUs where INV = Stock
        let mapFilterActive = false;
        $('#map-count-badge').on('click', function() {
            mapFilterActive = !mapFilterActive;
            missingFilterActive = false; // Deactivate other filters
            invStockFilterActive = false;
            applyFilters();
            updateBadgeStyles();
        });

        // INV > Stock badge click handler - filter SKUs where INV > Stock
        let invStockFilterActive = false;
        $('#inv-stock-badge').on('click', function() {
            invStockFilterActive = !invStockFilterActive;
            missingFilterActive = false; // Deactivate other filters
            mapFilterActive = false;
            applyFilters();
            updateBadgeStyles();
        });

        // Update badge styles based on active filters
        function updateBadgeStyles() {
            // 0 Sold badge
            if (zeroSoldFilterActive) {
                $('#zero-sold-count-badge').css('opacity', '1').css('box-shadow', '0 0 10px rgba(220, 53, 69, 0.8)');
            } else {
                $('#zero-sold-count-badge').css('opacity', '0.8').css('box-shadow', 'none');
            }

            // > 0 Sold badge
            if (moreSoldFilterActive) {
                $('#more-sold-count-badge').css('opacity', '1').css('box-shadow', '0 0 10px rgba(40, 167, 69, 0.8)');
            } else {
                $('#more-sold-count-badge').css('opacity', '0.8').css('box-shadow', 'none');
            }

            // < Amz badge
            if (lessAmzFilterActive) {
                $('#less-amz-badge').css('opacity', '1').css('box-shadow', '0 0 10px rgba(220, 53, 69, 0.8)');
            } else {
                $('#less-amz-badge').css('opacity', '0.8').css('box-shadow', 'none');
            }

            // > Amz badge
            if (moreAmzFilterActive) {
                $('#more-amz-badge').css('opacity', '1').css('box-shadow', '0 0 10px rgba(40, 167, 69, 0.8)');
            } else {
                $('#more-amz-badge').css('opacity', '0.8').css('box-shadow', 'none');
            }

            // Missing badge
            if (missingFilterActive) {
                $('#missing-count-badge').css('opacity', '1').css('box-shadow', '0 0 10px rgba(220, 53, 69, 0.8)');
            } else {
                $('#missing-count-badge').css('opacity', '0.8').css('box-shadow', 'none');
            }

            // Map badge
            if (mapFilterActive) {
                $('#map-count-badge').css('opacity', '1').css('box-shadow', '0 0 10px rgba(40, 167, 69, 0.8)');
            } else {
                $('#map-count-badge').css('opacity', '0.8').css('box-shadow', 'none');
            }

            // INV > Stock badge
            if (invStockFilterActive) {
                $('#inv-stock-badge').css('opacity', '1').css('box-shadow', '0 0 10px rgba(255, 193, 7, 0.8)');
            } else {
                $('#inv-stock-badge').css('opacity', '0.8').css('box-shadow', 'none');
            }
        }

        // Apply All button handler
        $(document).on('click', '#apply-all-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            window.applyAllSelectedPrices();
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
                Array.from(filteredSkus).every(sku => selectedSkus.has(sku));
            
            $('#select-all-checkbox').prop('checked', allFilteredSelected);
        }

        // Retry function for saving SPRICE
        function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
            return new Promise((resolve, reject) => {
                if (row) {
                    row.update({ SPRICE_STATUS: 'processing' });
                }
                
                $.ajax({
                    url: '/ebay3/save-sprice',
                    method: 'POST',
                    data: {
                        sku: sku,
                        sprice: sprice,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (row) {
                            row.update({
                                SPRICE: sprice,
                                SPFT: response.data?.spft || response.spft_percent,
                                SROI: response.data?.sroi || response.sroi_percent,
                                SGPFT: response.data?.sgpft || response.sgpft_percent,
                                SPRICE_STATUS: 'saved'
                            });
                        }
                        resolve(response);
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.error || xhr.responseText || 'Failed to save SPRICE';
                        console.error(`Attempt ${retryCount + 1} for SKU ${sku} failed:`, errorMsg);
                        
                        if (retryCount < 1) {
                            console.log(`Retrying SKU ${sku} in 2 seconds...`);
                            setTimeout(() => {
                                saveSpriceWithRetry(sku, sprice, row, retryCount + 1)
                                    .then(resolve)
                                    .catch(reject);
                            }, 2000);
                        } else {
                            console.error(`Max retries reached for SKU ${sku}`);
                            if (row) {
                                row.update({ SPRICE_STATUS: 'error' });
                            }
                            reject({ error: true, xhr: xhr });
                        }
                    }
                });
            });
        }

        // Apply price with retry logic
        async function applyPriceWithRetry(sku, price, cell, retries = 0) {
            const $btn = cell ? $(cell.getElement()).find('.apply-price-btn') : null;
            const row = cell ? cell.getRow() : null;
            const rowData = row ? row.getData() : null;

            if (retries === 0 && cell && $btn && row) {
                $btn.prop('disabled', true);
                $btn.html('<i class="fas fa-spinner fa-spin"></i>');
                $btn.attr('style', 'border: none; background: none; color: #ffc107; padding: 0;');
                if (rowData) {
                    rowData.SPRICE_STATUS = 'processing';
                    row.update(rowData);
                }
            }

            try {
                const response = await $.ajax({
                    url: '/push-ebay3-price-tabulator',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: { sku: sku, price: price }
                });

                if (response.errors && response.errors.length > 0) {
                    throw new Error(response.errors[0].message || 'API error');
                }

                if (rowData) {
                    rowData.SPRICE_STATUS = 'pushed';
                    row.update(rowData);
                }
                
                if ($btn && cell) {
                    $btn.prop('disabled', false);
                    $btn.html('<i class="fa-solid fa-check-double"></i>');
                    $btn.attr('style', 'border: none; background: none; color: #28a745; padding: 0;');
                }
                
                showToast(`Price $${price.toFixed(2)} pushed successfully for SKU: ${sku}`, 'success');
                
                return true;
            } catch (xhr) {
                const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseJSON?.error || xhr.responseJSON?.message || 'Failed to apply price';
                console.error(`Attempt ${retries + 1} for SKU ${sku} failed:`, errorMsg);

                if (retries < 5) {
                    console.log(`Retrying SKU ${sku} in 5 seconds...`);
                    await new Promise(resolve => setTimeout(resolve, 5000));
                    return applyPriceWithRetry(sku, price, cell, retries + 1);
                } else {
                    if (rowData) {
                        rowData.SPRICE_STATUS = 'error';
                        row.update(rowData);
                    }
                    
                    if ($btn && cell) {
                        $btn.prop('disabled', false);
                        $btn.html('<i class="fa-solid fa-x"></i>');
                        $btn.attr('style', 'border: none; background: none; color: #dc3545; padding: 0;');
                    }
                    
                    showToast(`Failed to apply price for SKU: ${sku} after multiple retries.`, 'error');
                    
                    return false;
                }
            }
        }

        // Apply all selected prices
        window.applyAllSelectedPrices = function() {
            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU to apply prices', 'error');
                return;
            }
            
            const $btn = $('#apply-all-btn');
            if ($btn.length === 0 || $btn.prop('disabled')) {
                return;
            }
            
            const originalHtml = $btn.html();
            $btn.prop('disabled', true);
            $btn.html('<i class="fas fa-spinner fa-spin" style="color: #ffc107;"></i>');
            
            const tableData = table.getData('all');
            const skusToProcess = [];
            
            selectedSkus.forEach(sku => {
                const row = tableData.find(r => r['(Child) sku'] === sku);
                if (row) {
                    const sprice = parseFloat(row.SPRICE) || 0;
                    if (sprice > 0) {
                        skusToProcess.push({ sku: sku, price: sprice });
                    }
                }
            });
            
            if (skusToProcess.length === 0) {
                $btn.prop('disabled', false);
                $btn.html(originalHtml);
                showToast('No valid prices found for selected SKUs', 'error');
                return;
            }
            
            let successCount = 0;
            let errorCount = 0;
            let currentIndex = 0;
            
            function processNextSku() {
                if (currentIndex >= skusToProcess.length) {
                    $btn.prop('disabled', false);
                    
                    if (errorCount === 0) {
                        $btn.html(`<i class="fas fa-check-double" style="color: #28a745;"></i>`);
                        showToast(`Successfully applied prices to ${successCount} SKU${successCount > 1 ? 's' : ''}`, 'success');
                        
                        setTimeout(() => {
                            $btn.html(originalHtml);
                        }, 3000);
                    } else {
                        $btn.html(originalHtml);
                        showToast(`Applied to ${successCount} SKU${successCount > 1 ? 's' : ''}, ${errorCount} failed`, 'error');
                    }
                    return;
                }
                
                const { sku, price } = skusToProcess[currentIndex];
                
                const row = table.getRows().find(r => r.getData()['(Child) sku'] === sku);
                if (row) {
                    const acceptCell = row.getCell('_accept');
                    if (acceptCell) {
                        const $cellElement = $(acceptCell.getElement());
                        const $btnInCell = $cellElement.find('.apply-price-btn');
                        if ($btnInCell.length) {
                            $btnInCell.prop('disabled', true);
                            $btnInCell.html('<i class="fas fa-spinner fa-spin"></i>');
                            $btnInCell.attr('style', 'border: none; background: none; color: #ffc107; padding: 0;');
                        }
                    }
                }
                
                $.ajax({
                    url: '/ebay3/save-sprice',
                    method: 'POST',
                    data: {
                        sku: sku,
                        sprice: price,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(saveResponse) {
                        if (saveResponse.error) {
                            errorCount++;
                            if (row) {
                                const rowData = row.getData();
                                rowData.SPRICE_STATUS = 'error';
                                row.update(rowData);
                            }
                            currentIndex++;
                            setTimeout(processNextSku, 2000);
                            return;
                        }
                        
                        $.ajax({
                            url: '/push-ebay3-price-tabulator',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: { sku: sku, price: price }
                        }).then(function(result) {
                            if (result.errors && result.errors.length > 0) {
                                throw new Error(result.errors[0].message);
                            }
                            successCount++;
                            if (row) {
                                const rowData = row.getData();
                                rowData.SPRICE_STATUS = 'pushed';
                                row.update(rowData);
                                
                                const acceptCell = row.getCell('_accept');
                                if (acceptCell) {
                                    const $cellElement = $(acceptCell.getElement());
                                    const $btnInCell = $cellElement.find('.apply-price-btn');
                                    if ($btnInCell.length) {
                                        $btnInCell.prop('disabled', false);
                                        $btnInCell.html('<i class="fa-solid fa-check-double"></i>');
                                        $btnInCell.attr('style', 'border: none; background: none; color: #28a745; padding: 0;');
                                    }
                                }
                            }
                            currentIndex++;
                            setTimeout(processNextSku, 2000);
                        }).catch(function(error) {
                            errorCount++;
                            if (row) {
                                const rowData = row.getData();
                                rowData.SPRICE_STATUS = 'error';
                                row.update(rowData);
                            }
                            currentIndex++;
                            setTimeout(processNextSku, 2000);
                        });
                    },
                    error: function(xhr) {
                        errorCount++;
                        if (row) {
                            const rowData = row.getData();
                            rowData.SPRICE_STATUS = 'error';
                            row.update(rowData);
                        }
                        currentIndex++;
                        setTimeout(processNextSku, 2000);
                    }
                });
            }
            
            processNextSku();
        };

        // Apply discount to selected SKUs
        function applyDiscount() {
            const discountValue = parseFloat($('#discount-percentage-input').val());
            const discountType = $('#discount-type-select').val();
            
            if (isNaN(discountValue) || discountValue <= 0) {
                showToast('Please enter a valid discount value', 'error');
                return;
            }

            if (selectedSkus.size === 0) {
                showToast('Please select at least one SKU', 'error');
                return;
            }

            const allData = table.getData('all');
            let updatedCount = 0;
            let errorCount = 0;
            const totalSkus = selectedSkus.size;

            allData.forEach(row => {
                const isParent = row.Parent && row.Parent.startsWith('PARENT');
                if (isParent) return;
                
                const sku = row['(Child) sku'];
                if (selectedSkus.has(sku)) {
                    const currentPrice = parseFloat(row['eBay Price']) || 0;
                    if (currentPrice > 0) {
                        let newSPrice;
                        
                        if (discountType === 'percentage') {
                            if (increaseModeActive) {
                                newSPrice = currentPrice * (1 + discountValue / 100);
                            } else {
                                newSPrice = currentPrice * (1 - discountValue / 100);
                            }
                        } else {
                            if (increaseModeActive) {
                                newSPrice = currentPrice + discountValue;
                            } else {
                                newSPrice = currentPrice - discountValue;
                            }
                        }
                        
                        newSPrice = Math.max(0.01, newSPrice);
                        
                        const originalSPrice = parseFloat(row['SPRICE']) || 0;
                        
                        const tableRow = table.getRows().find(r => {
                            const rowData = r.getData();
                            return rowData['(Child) sku'] === sku;
                        });
                        
                        if (tableRow) {
                            tableRow.update({ 
                                SPRICE: newSPrice,
                                SPRICE_STATUS: 'processing'
                            });
                        }
                        
                        saveSpriceWithRetry(sku, newSPrice, tableRow)
                            .then((response) => {
                                updatedCount++;
                                if (updatedCount + errorCount === totalSkus) {
                                    if (errorCount === 0) {
                                        showToast(`Discount applied to ${updatedCount} SKU(s)`, 'success');
                                    } else {
                                        showToast(`Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`, 'error');
                                    }
                                }
                            })
                            .catch((error) => {
                                errorCount++;
                                if (tableRow) {
                                    tableRow.update({ SPRICE: originalSPrice });
                                }
                                if (updatedCount + errorCount === totalSkus) {
                                    showToast(`Discount applied to ${updatedCount} SKU(s), ${errorCount} failed`, 'error');
                                }
                            });
                    }
                }
            });
        }

        // Apply Amazon suggested price
        function applySuggestAmazonPrice() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            let updatedCount = 0;
            let noAmazonPriceCount = 0;
            const totalSkus = selectedSkus.size;

            const allData = table.getData('all');

            allData.forEach(row => {
                const isParent = row.Parent && row.Parent.startsWith('PARENT');
                if (isParent) return;

                const sku = row['(Child) sku'];
                if (selectedSkus.has(sku)) {
                    const amazonPrice = parseFloat(row['A Price']);
                    
                    if (amazonPrice && amazonPrice > 0) {
                        const tableRow = table.getRows().find(r => {
                            const rowData = r.getData();
                            return rowData['(Child) sku'] === sku;
                        });
                        
                        if (tableRow) {
                            tableRow.update({ 
                                SPRICE: amazonPrice,
                                SPRICE_STATUS: 'processing'
                            });

                            saveSpriceWithRetry(sku, amazonPrice, tableRow)
                                .then((response) => {
                                    updatedCount++;
                                    if (updatedCount + noAmazonPriceCount === totalSkus) {
                                        let message = `Amazon price applied to ${updatedCount} SKU(s)`;
                                        if (noAmazonPriceCount > 0) {
                                            message += ` (${noAmazonPriceCount} SKU(s) had no Amazon price)`;
                                        }
                                        showToast(message, updatedCount > 0 ? 'success' : 'warning');
                                    }
                                })
                                .catch((error) => {
                                    noAmazonPriceCount++;
                                    if (updatedCount + noAmazonPriceCount === totalSkus) {
                                        showToast(`Failed to apply Amazon price`, 'error');
                                    }
                                });
                        }
                    } else {
                        noAmazonPriceCount++;
                    }
                }
            });

            // Handle case where no async operations were started
            if (updatedCount + noAmazonPriceCount === totalSkus && updatedCount === 0) {
                showToast(`${noAmazonPriceCount} SKU(s) had no Amazon price`, 'warning');
            }
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
            const allData = table.getData('all');

            allData.forEach(row => {
                const isParent = row.Parent && row.Parent.startsWith('PARENT');
                if (isParent) return;

                const sku = row['(Child) sku'];
                if (selectedSkus.has(sku)) {
                    const tableRow = table.getRows().find(r => {
                        const rowData = r.getData();
                        return rowData['(Child) sku'] === sku;
                    });
                    
                    if (tableRow) {
                        tableRow.update({ 
                            SPRICE: 0,
                            SPRICE_STATUS: 'processing'
                        });

                        saveSpriceWithRetry(sku, 0, tableRow)
                            .then((response) => {
                                clearedCount++;
                                if (clearedCount === selectedSkus.size) {
                                    showToast(`SPRICE cleared for ${clearedCount} SKU(s)`, 'success');
                                }
                            })
                            .catch((error) => {
                                console.error('Failed to clear SPRICE for', sku);
                            });
                    }
                }
            });
        }

        // Store all unfiltered data for summary calculations
        let allTableData = [];
        
        // Initialize Tabulator
        table = new Tabulator("#ebay3-table", {
            ajaxURL: "/ebay3-data-json",
            ajaxResponse: function(url, params, response) {
                // Store all unfiltered data for summary calculations
                allTableData = response || [];
                console.log('API Response - Total rows:', allTableData.length);
                
                // Calculate total L30 for verification
                let totalL30 = 0;
                let parentCount = 0;
                allTableData.forEach(row => {
                    const sku = row['(Child) sku'] || '';
                    if (sku.toUpperCase().includes('PARENT')) {
                        parentCount++;
                    } else {
                        totalL30 += parseFloat(row['eBay L30'] || 0);
                    }
                });
                console.log('Total eBay3 L30 from API:', totalL30, '(excluding', parentCount, 'PARENT rows)');
                
                return response;
            },
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            columnCalcs: "both",
            dataTree: true,
            dataTreeStartExpanded: false,
            dataTreeChildField: "_children",
            dataTreeChildColumnCalcs: true,
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "SKU Count"
                    }
                }
            },
            initialSort: [{
                column: "Parent",
                dir: "asc"
            }],
            rowFormatter: function(row) {
                const sku = row.getData()['(Child) sku'] || '';
                if (sku.toUpperCase().includes('PARENT')) {
                    row.getElement().classList.add('parent-row');
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
                    visible: false, 
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
                    width: 80,
                    visible: false
                },
                {
                    title: "Sku",
                    field: "(Child) sku",
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search SKU...",
                    cssClass: "text-primary fw-bold",
                    tooltip: true,
                    frozen: true,
                    width: 250,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = cell.getValue();
                        const isParent = sku && sku.toUpperCase().startsWith('PARENT');
                        
                        if (isParent) {
                            return `<span style="font-weight: 700;">${sku}</span>`;
                        }
                        
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
                    sorter: "number",
                    bottomCalc: "sum",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return value ? value.toLocaleString() : '0';
                    }
                },
                {
                    title: "OV L30",
                    field: "L30",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number",
                    bottomCalc: "sum",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return value ? value.toLocaleString() : '0';
                    }
                },
                {
                    title: "Dil",
                    field: "E Dil%",
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
                    title: "E Stock",
                    field: "eBay Stock",
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
                    title: "E L30",
                    field: "eBay L30",
                    hozAlign: "center",
                    width: 30,
                    sorter: "number"
                },
                {
                    title: "Missing",
                    field: "Missing",
                    hozAlign: "center",
                    width: 70,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const itemId = rowData['eBay_item_id'];
                        
                        // Missing = SKU exists in ProductMaster but not in eBay3 (no item_id)
                        if (!itemId || itemId === null || itemId === '') {
                            return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 6px; border-radius: 3px;">M</span>';
                        }
                        return '';
                    }
                },
                {
                    title: "MAP",
                    field: "MAP",
                    hozAlign: "center",
                    width: 90,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const ebayStock = parseFloat(rowData['eBay Stock']) || 0;
                        const inv = parseFloat(rowData['INV']) || 0;
                        
                        if (inv > 0 && ebayStock > 0) {
                            if (inv === ebayStock) {
                                return '<span style="color: #28a745; font-weight: bold;">MP</span>';
                            } else {
                                // Show signed difference: +X means INV has X more, -X means INV has X less
                                const diff = inv - ebayStock;
                                const sign = diff > 0 ? '+' : '';
                                return `<span style="color: #dc3545; font-weight: bold;">N MP<br>(${sign}${diff})</span>`;
                            }
                        }
                        return '';
                    }
                },
               
                {
                    title: "View",
                    field: "views",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        let color = '';
                        
                        if (value >= 30) color = '#28a745';
                        else color = '#a00211';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}</span>`;
                    },
                    width: 50
                },
               
                {
                    title: "S CVR",
                    field: "SCVR",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const aData = aRow.getData();
                        const bData = bRow.getData();
                        
                        const aViews = parseFloat(aData.views || 0);
                        const bViews = parseFloat(bData.views || 0);
                        const aL30 = parseFloat(aData['eBay L30'] || 0);
                        const bL30 = parseFloat(bData['eBay L30'] || 0);
                        
                        const aValue = aViews === 0 ? 0 : (aL30 / aViews) * 100;
                        const bValue = bViews === 0 ? 0 : (bL30 / bViews) * 100;
                        
                        return aValue - bValue;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const views = parseFloat(rowData.views || 0);
                        const l30 = parseFloat(rowData['eBay L30'] || 0);
                        
                        if (views === 0) {
                            return '<span style="color: #6c757d; font-weight: 600;">0.0%</span>';
                        }
                        
                        const scvrValue = (l30 / views) * 100;
                        let color = '';
                        
                        if (scvrValue <= 4) color = '#a00211';
                        else if (scvrValue > 4 && scvrValue <= 7) color = '#ffc107';
                        else if (scvrValue > 7 && scvrValue <= 10) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${scvrValue.toFixed(1)}%</span>`;
                    },
                    width: 60
                },
              
                {
                    title: "NR/REQ",
                    field: "nr_req",
                    hozAlign: "center",
                    headerSort: false,
                    formatter: function(cell) {
                        let value = cell.getValue();
                        if (value === null || value === undefined || value === '' || (typeof value === 'string' && value.trim() === '')) {
                            value = 'REQ';
                        }
                        
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'] || '';
                        
                        return `<select class="form-select form-select-sm nr-req-dropdown" 
                            data-sku="${sku}"
                            style="border: 1px solid #ddd; text-align: center; cursor: pointer; padding: 2px 4px; font-size: 16px; width: 50px; height: 28px;">
                            <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                            <option value="NR" ${value === 'NR' ? 'selected' : ''}>🔴</option>
                        </select>`;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    },
                    width: 60
                },
                {
                    title: "LMP",
                    field: "lmp_price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const lmpEntries = rowData.lmp_entries || [];
                        
                        if (!value && value !== 0) {
                            return '<span style="color: #999;">-</span>';
                        }
                        
                        if (lmpEntries.length > 0) {
                            return `<a href="#" class="lmp-modal-trigger" style="color: #007bff; text-decoration: underline; cursor: pointer;" data-lmp-entries='${JSON.stringify(lmpEntries).replace(/'/g, "&#39;")}'>$${parseFloat(value).toFixed(2)}</a>`;
                        }
                        
                        return `$${parseFloat(value).toFixed(2)}`;
                    },
                    cellClick: function(e, cell) {
                        if (e.target.classList.contains('lmp-modal-trigger')) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            const lmpEntries = JSON.parse(e.target.dataset.lmpEntries || '[]');
                            showLmpModal(lmpEntries);
                        }
                    },
                    width: 70
                },
                {
                    title: "Prc",
                    field: "eBay Price",
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
                    title: "A Prc",
                    field: "A Price",
                    hozAlign: "center",
                    sorter: "number",
                   
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === null || value === 0 || isNaN(value)) {
                            return '<span style="color: #6c757d;">-</span>';
                        }
                        return `$${value.toFixed(2)}`;
                    },
                    width: 70
                },
                {
                    title: "GPFT %",
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
                    title: "AD%",
                    field: "AD%",
                    hozAlign: "center",
                    sorter: function(a, b, aRow, bRow) {
                        const aData = aRow.getData();
                        const bData = bRow.getData();
                        
                        const aKwSpend = parseFloat(aData['kw_spend_L30'] || 0);
                        const bKwSpend = parseFloat(bData['kw_spend_L30'] || 0);
                        
                        let aVal = parseFloat(a || 0);
                        let bVal = parseFloat(b || 0);
                        
                        if (aKwSpend > 0 && aVal === 0) aVal = 100;
                        if (bKwSpend > 0 && bVal === 0) bVal = 100;
                        
                        return aVal - bVal;
                    },
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        
                        const rowData = cell.getRow().getData();
                        const kwSpend = parseFloat(rowData['kw_spend_L30'] || 0);
                        const adPercent = parseFloat(value || 0);
                        
                        if (kwSpend > 0 && adPercent === 0) {
                            return `<span style="color: #dc3545; font-weight: 600;">100%</span>`;
                        }
                        
                        return `${parseFloat(value).toFixed(0)}%`;
                    },
                    width: 55
                },
                {
                    title: "PFT %",
                    field: "PFT %",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const gpft = parseFloat(rowData['GPFT%'] || 0);
                        const ad = parseFloat(rowData['AD%'] || 0);
                        
                        const percent = gpft - ad;
                        let color = '';
                        
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    bottomCalc: "avg",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return `<strong>${parseFloat(value).toFixed(2)}%</strong>`;
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
                        else if (percent >= 50 && percent < 75) color = '#ffc107';
                        else if (percent >= 75 && percent <= 125) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    bottomCalc: "avg",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return `<strong>${parseFloat(value).toFixed(2)}%</strong>`;
                    },
                    width: 65
                },
                {
                    field: "_select",
                    hozAlign: "center",
                    headerSort: false,
                    visible: false,
                    titleFormatter: function(column) {
                        return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                            <span>Select</span>
                            <input type="checkbox" id="select-all-checkbox" style="cursor: pointer;" title="Select All Filtered SKUs">
                        </div>`;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'];
                        const isSelected = selectedSkus.has(sku);
                        
                        return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isSelected ? 'checked' : ''} style="cursor: pointer;">`;
                    }
                },
                {
                    title: "S PRC",
                    field: "SPRICE",
                    hozAlign: "center",
                    editor: "input",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const hasCustomSprice = rowData.has_custom_sprice;
                        const ebayPrice = parseFloat(rowData['eBay Price']) || 0;
                        const sprice = parseFloat(value) || 0;
                        
                        if (!value) return '';
                        
                        if (sprice === ebayPrice) {
                            return '<span style="color: #999; font-style: italic;">-</span>';
                        }
                        
                        const formattedValue = `$${parseFloat(value).toFixed(2)}`;
                        
                        if (hasCustomSprice === false) {
                            return `<span style="color: #0d6efd; font-weight: 500;">${formattedValue}</span>`;
                        }
                        
                        return formattedValue;
                    },
                    width: 80
                },
                {
                    field: "_accept",
                    hozAlign: "center",
                    headerSort: false,
                    titleFormatter: function(column) {
                        return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px; flex-direction: column;">
                            <span>Accept</span>
                            <button type="button" class="btn btn-sm" id="apply-all-btn" title="Apply All Selected Prices to eBay3" style="border: none; background: none; padding: 0; cursor: pointer; color: #28a745;">
                                <i class="fas fa-check-double" style="font-size: 1.2em;"></i>
                            </button>
                        </div>`;
                    },
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData['(Child) sku'];
                        const sprice = parseFloat(rowData.SPRICE) || 0;
                        const status = rowData.SPRICE_STATUS || null;

                        if (!sprice || sprice === 0) {
                            return '<span style="color: #999;">N/A</span>';
                        }

                        let icon = '<i class="fas fa-check"></i>';
                        let iconColor = '#28a745';
                        let titleText = 'Apply Price to eBay3';

                        if (status === 'processing') {
                            icon = '<i class="fas fa-spinner fa-spin"></i>';
                            iconColor = '#ffc107';
                            titleText = 'Price pushing in progress...';
                        } else if (status === 'pushed') {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            iconColor = '#28a745';
                            titleText = 'Price pushed to eBay3';
                        } else if (status === 'saved') {
                            icon = '<i class="fa-solid fa-check-double"></i>';
                            iconColor = '#28a745';
                            titleText = 'SPRICE saved (Click to push to eBay3)';
                        } else if (status === 'error') {
                            icon = '<i class="fa-solid fa-x"></i>';
                            iconColor = '#dc3545';
                            titleText = 'Error applying price to eBay3';
                        }

                        return `<button type="button" class="btn btn-sm apply-price-btn btn-circle" data-sku="${sku}" data-price="${sprice}" data-status="${status || ''}" title="${titleText}" style="border: none; background: none; color: ${iconColor}; padding: 0;">
                            ${icon}
                        </button>`;
                    },
                    cellClick: function(e, cell) {
                        const $target = $(e.target);
                        
                        if ($target.hasClass('apply-price-btn') || $target.closest('.apply-price-btn').length) {
                            e.stopPropagation();
                            const $btn = $target.hasClass('apply-price-btn') ? $target : $target.closest('.apply-price-btn');
                            const sku = $btn.attr('data-sku') || $btn.data('sku');
                            const price = parseFloat($btn.attr('data-price') || $btn.data('price'));
                            const currentStatus = $btn.attr('data-status') || '';
                            
                            if (!sku || !price || price <= 0 || isNaN(price)) {
                                showToast('Invalid SKU or price', 'error');
                                return;
                            }
                            
                            if (currentStatus === 'saved' || !currentStatus) {
                                const row = cell.getRow();
                                row.update({ SPRICE_STATUS: 'processing' });
                                
                                saveSpriceWithRetry(sku, price, row)
                                    .then((response) => {
                                        applyPriceWithRetry(sku, price, cell, 0);
                                    })
                                    .catch((error) => {
                                        row.update({ SPRICE_STATUS: 'error' });
                                        showToast('Failed to save SPRICE', 'error');
                                    });
                            } else {
                                applyPriceWithRetry(sku, price, cell, 0);
                            }
                        }
                    }
                },
                {
                    title: "S GPFT",
                    field: "SGPFT",
                    hozAlign: "center",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === null || value === undefined) return '';
                        const percent = parseFloat(value);
                        if (isNaN(percent)) return '';
                        
                        let color = '';
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 80
                },
                {
                    title: "S PFT",
                    field: "SPFT",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sgpft = parseFloat(rowData.SGPFT || 0);
                        const ad = parseFloat(rowData['AD%'] || 0);
                        
                        const percent = sgpft - ad;
                        if (isNaN(percent)) return '';
                        
                        let color = '';
                        if (percent < 10) color = '#a00211';
                        else if (percent >= 10 && percent < 15) color = '#ffc107';
                        else if (percent >= 15 && percent < 20) color = '#3591dc';
                        else if (percent >= 20 && percent <= 40) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 80
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
                        if (isNaN(percent)) return '';
                        
                        let color = '';
                        if (percent < 50) color = '#a00211';
                        else if (percent >= 50 && percent < 75) color = '#ffc107';
                        else if (percent >= 75 && percent <= 125) color = '#28a745';
                        else color = '#e83e8c';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                    },
                    width: 80
                },
                {
                    title: "SPEND L30",
                    field: "AD_Spend_L30",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `
                            <span>$${value.toFixed(2)}</span>
                            <i class="fa fa-info-circle text-primary toggle-spendL30-btn" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    },
                    bottomCalc: "sum",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return `<strong>$${parseFloat(value).toFixed(2)}</strong>`;
                    },
                    width: 90
                },
                {
                    title: "KW SPEND L30",
                    field: "kw_spend_L30",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `$${value.toFixed(2)}`;
                    },
                    bottomCalc: "sum",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return `<strong>$${parseFloat(value).toFixed(2)}</strong>`;
                    },
                    width: 100
                },
                {
                    title: "KW %",
                    field: "kw_percent",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const kwSpend = parseFloat(rowData['kw_spend_L30'] || 0);
                        const pmtSpend = parseFloat(rowData['pmt_spend_L30'] || 0);
                        const total = kwSpend + pmtSpend;
                        const percent = total > 0 ? (kwSpend / total) * 100 : 0;
                        return `${percent.toFixed(1)}%`;
                    },
                    width: 70
                },
                {
                    title: "PMT SPEND L30",
                    field: "pmt_spend_L30",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        return `$${value.toFixed(2)}`;
                    },
                    bottomCalc: "sum",
                    bottomCalcFormatter: function(cell) {
                        const value = cell.getValue();
                        return `<strong>$${parseFloat(value).toFixed(2)}</strong>`;
                    },
                    width: 100
                },
                {
                    title: "PMT %",
                    field: "pmt_percent",
                    hozAlign: "center",
                    sorter: "number",
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const kwSpend = parseFloat(rowData['kw_spend_L30'] || 0);
                        const pmtSpend = parseFloat(rowData['pmt_spend_L30'] || 0);
                        const total = kwSpend + pmtSpend;
                        const percent = total > 0 ? (pmtSpend / total) * 100 : 0;
                        return `${percent.toFixed(1)}%`;
                    },
                    width: 70
                }
            ]
        });

        // SKU Search functionality
        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("(Child) sku", "like", value);
        });

        // NR/REQ dropdown change handler
        $(document).on('change', '.nr-req-dropdown', function() {
            const $select = $(this);
            const value = $select.val();
            const sku = $select.data('sku');
            
            if (!sku) {
                console.error('Could not find SKU in dropdown data attribute');
                showToast('Could not find SKU', 'error');
                return;
            }
            
            console.log('Saving NR/REQ for SKU:', sku, 'Value:', value);
            
            $.ajax({
                url: '/listing_ebaythree/save-status',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    sku: sku,
                    nr_req: value
                },
                success: function(response) {
                    if (response.status === 'success') {
                        console.log('NR/REQ saved successfully for', sku, 'value:', value);
                        const message = value === 'REQ' ? 'REQ updated' : (value === 'NR' ? 'NR updated' : 'Status cleared');
                        showToast(message, 'success');
                    } else {
                        console.error('Save failed:', response);
                        showToast(response.message || 'Failed to save status', 'error');
                    }
                },
                error: function(xhr) {
                    console.error('Failed to save NR/REQ for', sku, 'Error:', xhr.responseText);
                    showToast(`Failed to save NR/REQ for ${sku}`, 'error');
                }
            });
        });

        table.on('cellEdited', function(cell) {
            var row = cell.getRow();
            var data = row.getData();
            var field = cell.getColumn().getField();
            var value = cell.getValue();

            if (field === 'SPRICE') {
                row.update({ SPRICE_STATUS: 'processing' });
                
                saveSpriceWithRetry(data['(Child) sku'], value, row)
                    .then((response) => {
                        showToast('SPRICE saved successfully', 'success');
                    })
                    .catch((error) => {
                        showToast('Failed to save SPRICE', 'error');
                    });
            } else if (field === 'Listed' || field === 'Live') {
                $.ajax({
                    url: '/ebay3/update-listed-live',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: data['(Child) sku'],
                        field: field,
                        value: value
                    },
                    success: function(response) {
                        showToast(field + ' status updated successfully', 'success');
                    },
                    error: function(error) {
                        showToast('Failed to update ' + field + ' status', 'error');
                    }
                });
            }
        });

        // Apply filters
        function applyFilters() {
            const viewModeFilter = $('#view-mode-filter').val();
            const inventoryFilter = $('#inventory-filter').val();
            const nrlFilter = $('#nrl-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const cvrFilter = $('#cvr-filter').val();
            const statusFilter = $('#status-filter').val();
            const adsFilter = $('#ads-filter').val();

            table.clearFilter(true);
            
            // Disable tree mode for SKU-only view
            if (viewModeFilter === 'sku') {
                // Flatten the tree for SKU-only view
                const flatData = [];
                allTableData.forEach(parent => {
                    if (parent._children && Array.isArray(parent._children)) {
                        // Add only child rows, skip parent
                        flatData.push(...parent._children);
                    } else {
                        // If no children, check if it's not a parent row
                        const sku = parent['(Child) sku'] || '';
                        if (!sku.toUpperCase().includes('PARENT')) {
                            flatData.push(parent);
                        }
                    }
                });
                table.setData(flatData);
            } else {
                // Restore original tree data for parent or both mode
                table.setData(allTableData);
            }

            // View Mode Filter - controls parent/SKU/both visibility
            if (viewModeFilter === 'parent') {
                // Show only parent rows, hide child rows
                table.addFilter(function(data) {
                    const sku = data['(Child) sku'] || '';
                    return sku.toUpperCase().includes('PARENT');
                });
            }
            // If 'both' is selected, no additional filter needed
            // If 'sku' is selected, data is already filtered above

            if (inventoryFilter === 'zero') {
                table.addFilter(function(data) {
                    // For tree data, filter based on the INV value (sum for parents)
                    return parseFloat(data.INV || 0) === 0;
                });
            } else if (inventoryFilter === 'more') {
                table.addFilter(function(data) {
                    // Filter by INV > 0 for all rows including PARENT
                    return parseFloat(data.INV || 0) > 0;
                });
            }

            // Skip other filters for PARENT rows in tree mode
            if (nrlFilter !== 'all') {
                table.addFilter(function(data) {
                    // Skip filter for parent rows
                    const sku = data['(Child) sku'] || '';
                    if (sku.toUpperCase().includes('PARENT')) return true;
                    
                    if (nrlFilter === 'REQ') {
                        return data.nr_req === 'REQ';
                    } else if (nrlFilter === 'NR') {
                        return data.nr_req === 'NR';
                    }
                    return true;
                });
            }

            if (gpftFilter !== 'all') {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const gpft = parseFloat(data['GPFT%']) || 0;
                    
                    if (gpftFilter === 'negative') return gpft < 0;
                    if (gpftFilter === '0-10') return gpft >= 0 && gpft < 10;
                    if (gpftFilter === '10-20') return gpft >= 10 && gpft < 20;
                    if (gpftFilter === '20-30') return gpft >= 20 && gpft < 30;
                    if (gpftFilter === '30-40') return gpft >= 30 && gpft < 40;
                    if (gpftFilter === '40-50') return gpft >= 40 && gpft < 50;
                    if (gpftFilter === '50-60') return gpft >= 50 && gpft < 60;
                    if (gpftFilter === '60plus') return gpft >= 60;
                    return true;
                });
            }

            if (cvrFilter !== 'all') {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const scvrValue = parseFloat(data['SCVR'] || 0);
                    const views = parseFloat(data.views || 0);
                    const l30 = parseFloat(data['eBay L30'] || 0);
                    const cvr = views > 0 ? (l30 / views) * 100 : 0;
                    
                    const cvrRounded = Math.round(cvr * 100) / 100;
                    
                    if (cvrFilter === '0-0') return cvrRounded === 0;
                    if (cvrFilter === '0.01-1') return cvrRounded >= 0.01 && cvrRounded <= 1;
                    if (cvrFilter === '1-2') return cvrRounded > 1 && cvrRounded <= 2;
                    if (cvrFilter === '2-3') return cvrRounded > 2 && cvrRounded <= 3;
                    if (cvrFilter === '3-4') return cvrRounded > 3 && cvrRounded <= 4;
                    if (cvrFilter === '0-4') return cvrRounded >= 0 && cvrRounded <= 4;
                    if (cvrFilter === '4-7') return cvrRounded > 4 && cvrRounded <= 7;
                    if (cvrFilter === '7-10') return cvrRounded > 7 && cvrRounded <= 10;
                    if (cvrFilter === '10plus') return cvrRounded > 10;
                    return true;
                });
            }

            if (statusFilter !== 'all') {
                table.addFilter(function(data) {
                    const status = data.nr_req || '';
                    
                    if (statusFilter === 'REQ') {
                        return status === 'REQ';
                    } else if (statusFilter === 'NR') {
                        return status === 'NR';
                    }
                    return true;
                });
            }

            if (adsFilter !== 'all') {
                table.addFilter(function(data) {
                    const adValue = data['AD%'];
                    const kwSpend = parseFloat(data['kw_spend_L30'] || 0);
                    
                    let adPercent;
                    if (kwSpend > 0 && (adValue === null || adValue === undefined || adValue === '' || parseFloat(adValue) === 0)) {
                        adPercent = 100;
                    } else if (adValue === null || adValue === undefined || adValue === '' || isNaN(parseFloat(adValue))) {
                        return false;
                    } else {
                        adPercent = parseFloat(adValue);
                    }
                    
                    if (adsFilter === '0-10') return adPercent >= 0 && adPercent < 10;
                    if (adsFilter === '10-20') return adPercent >= 10 && adPercent < 20;
                    if (adsFilter === '20-30') return adPercent >= 20 && adPercent < 30;
                    if (adsFilter === '30-100') return adPercent >= 30 && adPercent <= 100;
                    if (adsFilter === '100plus') return adPercent > 100;
                    return true;
                });
            }
            
            // 0 Sold filter (based on eBay L30) - triggered by badge click
            if (zeroSoldFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const l30 = parseFloat(data['eBay L30']) || 0;
                    return l30 === 0;
                });
            }

            // > 0 Sold filter (based on eBay L30) - triggered by badge click
            if (moreSoldFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const l30 = parseFloat(data['eBay L30']) || 0;
                    return l30 > 0;
                });
            }

            // < Amz filter - show prices less than Amazon price
            if (lessAmzFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const ebayPrice = parseFloat(data['eBay Price']) || 0;
                    const amazonPrice = parseFloat(data['A Price']) || 0;
                    return amazonPrice > 0 && ebayPrice > 0 && ebayPrice < amazonPrice;
                });
            }

            // > Amz filter - show prices greater than Amazon price
            if (moreAmzFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const ebayPrice = parseFloat(data['eBay Price']) || 0;
                    const amazonPrice = parseFloat(data['A Price']) || 0;
                    return amazonPrice > 0 && ebayPrice > 0 && ebayPrice > amazonPrice;
                });
            }

            // Missing filter - show SKUs missing in eBay
            if (missingFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const itemId = data['eBay_item_id'];
                    // Missing: SKU exists in ProductMaster but not in eBay3 (no item_id)
                    return !itemId || itemId === null || itemId === '';
                });
            }

            // Map filter - show SKUs where INV = eBay Stock
            if (mapFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const ebayStock = parseFloat(data['eBay Stock']) || 0;
                    const inv = parseFloat(data['INV']) || 0;
                    return inv > 0 && ebayStock > 0 && inv === ebayStock;
                });
            }

            // N Map filter - show SKUs where INV != eBay Stock (not mapped)
            if (invStockFilterActive) {
                table.addFilter(function(data) {
                    // Skip filter for parent rows in tree mode
                    const sku = data['(Child) sku'] || '';
                    if (viewModeFilter !== 'sku' && sku.toUpperCase().includes('PARENT')) return true;
                    
                    const ebayStock = parseFloat(data['eBay Stock']) || 0;
                    const inv = parseFloat(data['INV']) || 0;
                    // Show both: INV > Stock AND Stock > INV
                    return inv > 0 && ebayStock > 0 && inv !== ebayStock;
                });
            }
            
            updateCalcValues();
            updateSummary();
            setTimeout(function() {
                updateSelectAllCheckbox();
            }, 100);
        }

        $('#view-mode-filter, #inventory-filter, #nrl-filter, #gpft-filter, #cvr-filter, #status-filter, #ads-filter').on('change', function() {
            applyFilters();
        });
        
        // Update calc values
        function updateCalcValues() {
            const data = table.getData("active");
            let totalSales = 0;
            let totalProfit = 0;
            let sumLp = 0;
            
            data.forEach(row => {
                const profit = parseFloat(row['Total_pft']) || 0;
                const salesL30 = parseFloat(row['T_Sale_l30']) || 0;
                if (profit > 0 && salesL30 > 0) {
                    totalProfit += profit;
                    totalSales += salesL30;
                }
                sumLp += parseFloat(row['LP_productmaster']) || 0;
            });
        }

        // Update summary badges - Use FILTERED data for badge counts
        function updateSummary() {
            // Use filtered data (active rows only) for accurate counts
            const data = table.getData('active');
            
            console.log('updateSummary - Filtered rows:', data.length);
            
            let totalTcos = 0;
            let totalSpendL30 = 0;
            // Note: KW and PMT spend are set from header constants, not calculated from rows
            let totalKwSpendL30 = 0;
            let totalPmtSpendL30 = 0;
            let totalPftAmt = 0;
            let totalSalesAmt = 0;
            let totalLpAmt = 0;
            let totalFbaInv = 0;
            let totalFbaL30 = 0;
            let totalDilPercent = 0;
            let dilCount = 0;
            let zeroSoldCount = 0;
            let moreSoldCount = 0;
            let lessAmzCount = 0;
            let moreAmzCount = 0;
            let missingCount = 0;
            let mapCount = 0;
            let invStockCount = 0;
            
            // Track parents already counted for KW spend (parent-wise ads) - NOT USED, using header constants
            // const countedParentsKw = new Set();
            // PMT spend is per listing (item_id), so we track by item_id - NOT USED, using header constants
            // const countedItemsPmt = new Set();

            data.forEach(row => {
                // Count all rows regardless of filters
                totalTcos += parseFloat(row['AD%'] || 0);
                totalPftAmt += parseFloat(row['Total_pft'] || 0);
                totalSalesAmt += parseFloat(row['T_Sale_l30'] || 0);
                totalLpAmt += parseFloat(row['LP_productmaster'] || 0) * parseFloat(row['eBay L30'] || 0);
                totalFbaInv += parseFloat(row.INV || 0);
                totalFbaL30 += parseFloat(row['eBay L30'] || 0);
                
                // NOTE: KW and PMT spend now come from header constants (more accurate)
                // Removed row-wise aggregation to prevent incorrect totals
                
                const l30 = parseFloat(row['eBay L30'] || 0);
                if (l30 === 0) {
                    zeroSoldCount++;
                } else {
                    moreSoldCount++;
                }
                
                const dil = parseFloat(row['E Dil%'] || 0);
                if (!isNaN(dil)) {
                    totalDilPercent += dil;
                    dilCount++;
                }

                // Compare eBay Price with Amazon Price
                const ebayPrice = parseFloat(row['eBay Price']) || 0;
                const amazonPrice = parseFloat(row['A Price']) || 0;
                
                // Count for < Amz
                if (amazonPrice > 0 && ebayPrice > 0 && ebayPrice < amazonPrice) {
                    lessAmzCount++;
                }
                
                // Count for > Amz
                if (amazonPrice > 0 && ebayPrice > 0 && ebayPrice > amazonPrice) {
                    moreAmzCount++;
                }

                // Count Missing - SKU exists in ProductMaster but not in eBay3 (no item_id)
                const itemId = row['eBay_item_id'];
                if (!itemId || itemId === null || itemId === '') {
                    missingCount++;
                }

                // Stock comparison for Map and INV > Stock
                const ebayStock = parseFloat(row['eBay Stock']) || 0;
                const inv = parseFloat(row['INV']) || 0;

                // Count Map - INV = eBay Stock
                if (inv > 0 && ebayStock > 0 && inv === ebayStock) {
                    mapCount++;
                }

                // Count N Map (not mapped) - INV != eBay Stock
                if (inv > 0 && ebayStock > 0 && inv !== ebayStock) {
                    invStockCount++;
                }
            });
            
            // Total Spend = Use header-level constants (more accurate than row aggregation)
            totalSpendL30 = TOTAL_ADS_SPENT;
            totalKwSpendL30 = KW_SPENT;
            totalPmtSpendL30 = PMT_SPENT;

            let totalWeightedPrice = 0;
            let totalL30 = 0;
            data.forEach(row => {
                const price = parseFloat(row['eBay Price'] || 0);
                const l30 = parseFloat(row['eBay L30'] || 0);
                totalWeightedPrice += price * l30;
                totalL30 += l30;
            });
            const avgPrice = totalL30 > 0 ? totalWeightedPrice / totalL30 : 0;
            $('#avg-price-badge').text('Avg Price: $' + Math.round(avgPrice));

            let totalViews = 0;
            data.forEach(row => {
                totalViews += parseFloat(row.views || 0);
            });
            const avgCVR = totalViews > 0 ? (totalL30 / totalViews * 100) : 0;
            $('#avg-cvr-badge').text('Avg CVR: ' + avgCVR.toFixed(1) + '%');
            $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());

            // Calculate TCOS = (Total Ad Spend / Total Sales) * 100
            const tcosPercent = totalSalesAmt > 0 ? ((totalSpendL30 / totalSalesAmt) * 100).toFixed(2) : '0.00';
            
            // Calculate Net PFT % = Avg GPFT % - TCOS %
            const avgGpft = totalSalesAmt > 0 ? ((totalPftAmt / totalSalesAmt) * 100) : 0;
            const nPftPercent = avgGpft - parseFloat(tcosPercent);
            
            // Calculate Net PFT Amount = Total PFT - Total Ad Spend
            const nPftAmount = totalPftAmt - totalSpendL30;

            $('#total-tcos-badge').text('Total TCOS: ' + tcosPercent + '%');
            $('#total-spend-l30-badge').text('Total Spend L30: $' + totalSpendL30.toFixed(2));
            $('#total-kw-spend-l30-badge').text('KW Spend L30: $' + totalKwSpendL30.toFixed(2));
            $('#total-pmt-spend-l30-badge').text('PMT Spend L30: $' + totalPmtSpendL30.toFixed(2));
            $('#total-npft-badge').text('Net PFT %: ' + nPftPercent.toFixed(2) + '%');
            $('#total-npft-amt-badge').text('Net PFT AMT: $' + Math.round(nPftAmount));
            $('#total-cogs-amt-badge').text('COGS AMT: $' + Math.round(totalLpAmt));
            const roiPercent = totalLpAmt > 0 ? Math.round((totalPftAmt / totalLpAmt) * 100) : 0;
            $('#roi-percent-badge').text('ROI %: ' + roiPercent + '%');
            $('#total-fba-inv-badge').text('Total eBay3 INV: ' + Math.round(totalFbaInv).toLocaleString());
            $('#total-fba-l30-badge').text('Total eBay3 L30: ' + Math.round(totalFbaL30).toLocaleString());
            $('#zero-sold-count-badge').text('0 Sold: ' + zeroSoldCount.toLocaleString());
            $('#more-sold-count-badge').text('> 0 Sold: ' + moreSoldCount.toLocaleString());
            const avgDilPercent = dilCount > 0 ? (totalDilPercent / dilCount) : 0;
            $('#avg-dil-percent-badge').text('DIL %: ' + Math.round(avgDilPercent) + '%');
            $('#total-pft-amt-badge').text('Total PFT AMT: $' + Math.round(totalPftAmt));
            $('#total-sales-amt-badge').text('Total SALES AMT: $' + Math.round(totalSalesAmt));
            
            // Update price comparison badges
            $('#less-amz-badge').text('< Amz: ' + lessAmzCount);
            $('#more-amz-badge').text('> Amz: ' + moreAmzCount);

            // Update stock mapping badges
            $('#missing-count-badge').text('Missing: ' + missingCount);
            $('#map-count-badge').text('Map: ' + mapCount);
            $('#inv-stock-badge').text('N Map: ' + invStockCount);
            
            // Display Avg GPFT
            $('#avg-gpft-badge').text('Avg GPFT: ' + avgGpft.toFixed(1) + '%');
            
            // Calculate Avg PFT = Average of individual PFT% values (same formula as row-level)
            let totalPftPercent = 0;
            let pftPercentCount = 0;
            data.forEach(row => {
                const pftPercent = parseFloat(row['PFT %'] || 0);
                if (!isNaN(pftPercent)) {
                    totalPftPercent += pftPercent;
                    pftPercentCount++;
                }
            });
            const avgPft = pftPercentCount > 0 ? (totalPftPercent / pftPercentCount).toFixed(1) : '0.0';
            $('#avg-pft-badge').text('Avg PFT: ' + avgPft + '%');
        }

        // Build Column Visibility Dropdown
        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            fetch('/ebay3-column-visibility', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => response.json())
                .then(savedVisibility => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (!def.field) return;

                        const li = document.createElement("li");
                        const label = document.createElement("label");
                        label.style.display = "block";
                        label.style.padding = "5px 10px";
                        label.style.cursor = "pointer";

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
                if (def.field) {
                    visibility[def.field] = col.isVisible();
                }
            });

            fetch('/ebay3-column-visibility', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    visibility: visibility
                })
            });
        }

        function applyColumnVisibilityFromServer() {
            fetch('/ebay3-column-visibility', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => response.json())
                .then(savedVisibility => {
                    table.getColumns().forEach(col => {
                        const def = col.getDefinition();
                        if (def.field && savedVisibility[def.field] === false) {
                            col.hide();
                        }
                    });
                });
        }

        // Wait for table to be built
        table.on('tableBuilt', function() {
            applyColumnVisibilityFromServer();
            buildColumnDropdown();
            applyFilters();
        });

        table.on('dataLoaded', function() {
            updateCalcValues();
            updateSummary();
            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            }, 100);
        });

        table.on('renderComplete', function() {
            setTimeout(function() {
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                updateSelectAllCheckbox();
            }, 100);
        });

        // Toggle column from dropdown
        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.type === 'checkbox') {
                const field = e.target.value;
                const col = table.getColumn(field);
                if (e.target.checked) {
                    col.show();
                } else {
                    col.hide();
                }
                saveColumnVisibilityToServer();
            }
        });

        // Show All Columns button
        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => {
                col.show();
            });
            buildColumnDropdown();
            saveColumnVisibilityToServer();
        });

        // Toggle SPEND L30 breakdown columns
        document.addEventListener("click", function(e) {
            if (e.target.classList.contains("toggle-spendL30-btn")) {
                let colsToToggle = ["kw_spend_L30", "kw_percent", "pmt_spend_L30", "pmt_percent"];

                colsToToggle.forEach(colField => {
                    let col = table.getColumn(colField);
                    if (col) {
                        col.toggle();
                    }
                });
                
                saveColumnVisibilityToServer();
                buildColumnDropdown();
            }

            // Copy SKU to clipboard
            if (e.target.classList.contains("copy-sku-btn")) {
                const sku = e.target.getAttribute("data-sku");
                
                navigator.clipboard.writeText(sku).then(function() {
                    showToast(`SKU "${sku}" copied to clipboard!`, 'success');
                }).catch(function(err) {
                    const textarea = document.createElement('textarea');
                    textarea.value = sku;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    showToast(`SKU "${sku}" copied to clipboard!`, 'success');
                });
            }
        });
        
        // LMP Modal function
        window.showLmpModal = function(lmpEntries) {
            let modalHtml = `
                <div class="modal fade" id="lmpModal" tabindex="-1" aria-labelledby="lmpModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="lmpModalLabel">Lowest Marketplace Prices</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Price</th>
                                            <th>Title</th>
                                            <th>Seller</th>
                                            <th>Link</th>
                                        </tr>
                                    </thead>
                                    <tbody>
            `;
            
            lmpEntries.forEach(function(entry) {
                const price = entry.price ? '$' + parseFloat(entry.price).toFixed(2) : '-';
                const title = entry.title || '-';
                const seller = entry.seller || '-';
                const link = entry.link || '#';
                
                modalHtml += `
                    <tr>
                        <td><strong>${price}</strong></td>
                        <td>${title}</td>
                        <td>${seller}</td>
                        <td><a href="${link}" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-external-link-alt"></i> View</a></td>
                    </tr>
                `;
            });
            
            modalHtml += `
                                    </tbody>
                                </table>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            $('#lmpModal').remove();
            
            // Add modal to body
            $('body').append(modalHtml);
            
            // Show modal
            var lmpModal = new bootstrap.Modal(document.getElementById('lmpModal'));
            lmpModal.show();
        };
    });
</script>
@endsection

