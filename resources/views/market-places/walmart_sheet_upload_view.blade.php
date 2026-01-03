@extends('layouts.vertical', ['title' => 'Walmart Pricing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        
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

        .tabulator-paginator label {
            margin-right: 5px;
        }

        /* Walmart-style color coding */
        .walmart-percent-value {
            font-weight: bold;
            background: none !important;
            background-color: transparent !important;
        }

        .walmart-percent-value.red {
            color: #dc3545 !important;
            background: none !important;
        }

        .walmart-percent-value.blue {
            color: #3591dc !important;
            background: none !important;
        }

        .walmart-percent-value.yellow {
            color: #ffc107 !important;
            background: none !important;
        }

        .walmart-percent-value.green {
            color: #28a745 !important;
            background: none !important;
        }

        .walmart-percent-value.pink {
            color: #e83e8c !important;
            background: none !important;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Walmart Pricing',
        'sub_title' => 'Walmart Pricing',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Walmart Pricing</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <!-- Inventory Filter -->
                    <div>
                        <select id="inventory-filter" class="form-select form-select-sm" style="width: 140px;">
                            <option value="all">All Inventory</option>
                            <option value="gt0" selected>INV &gt; 0</option>
                            <option value="eq0">INV = 0</option>
                        </select>
                    </div>

                    <!-- GPFT Filter -->
                    <div>
                        <select id="gpft-filter" class="form-select form-select-sm" style="width: 130px;">
                            <option value="all">All GPFT%</option>
                            <option value="negative">Negative</option>
                            <option value="0-10">0-10%</option>
                            <option value="10-20">10-20%</option>
                            <option value="20-30">20-30%</option>
                            <option value="30-40">30-40%</option>
                            <option value="40-50">40-50%</option>
                            <option value="50-60">50-60%</option>
                            <option value="60plus">60%+</option>
                        </select>
                    </div>

                    <!-- CVR Filter -->
                    <div>
                        <select id="cvr-filter" class="form-select form-select-sm" style="width: 120px;">
                            <option value="all">All CVR%</option>
                            <option value="0-0">0%</option>
                            <option value="0.01-1">0.01-1%</option>
                            <option value="1-2">1-2%</option>
                            <option value="2-3">2-3%</option>
                            <option value="3-4">3-4%</option>
                            <option value="0-4">0-4%</option>
                            <option value="4-7">4-7%</option>
                            <option value="7-10">7-10%</option>
                            <option value="10plus">10%+</option>
                        </select>
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
                        <i class="fa fa-download"></i> Export CSV
                    </button>

                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-arrow-down"></i> Decrease Mode
                    </button>
                    
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-arrow-up"></i> Increase Mode
                    </button>
                    
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#uploadPriceModal">
                        <i class="fa fa-dollar-sign"></i> Upload Price
                    </button>
                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#uploadListingModal">
                        <i class="fa fa-eye"></i> Upload Listing
                    </button>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadOrderModal">
                        <i class="fa fa-shopping-cart"></i> Upload Order
                    </button>
                </div>

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Top Metrics -->
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-badge" style="color: black; font-weight: bold;">Total PFT AMT: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color: black; font-weight: bold;">Total SALES AMT: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;">AVG GPFT: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-pft-badge" style="color: black; font-weight: bold;">AVG PFT: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="avg-cvr-badge" style="color: black; font-weight: bold;">Avg CVR: 0.00%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge" style="color: black; font-weight: bold;">Views: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="cvr-badge" style="color: black; font-weight: bold;">CVR: 0.00%</span>
                        
                        <!-- Walmart Metrics -->
                        <span class="badge bg-primary fs-6 p-2" id="total-products-badge" style="color: black; font-weight: bold;">Total Products: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-quantity-badge" style="color: black; font-weight: bold;">Total Quantity: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color: black; font-weight: bold;">0 Sold Count: 0</span>
                        
                        <!-- Financial Metrics -->
                        <span class="badge bg-danger fs-6 p-2" id="total-tcos-badge" style="color: white; font-weight: bold;">Total TCOS: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="total-spend-badge" style="color: black; font-weight: bold;">Total SPEND L30: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="total-cogs-badge" style="color: black; font-weight: bold;">COGS AMT: $0.00</span>
                        <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge" style="color: black; font-weight: bold;">ROI %: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-orders-badge" style="color: black; font-weight: bold;">Total Orders: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="total-walmart-l30-badge" style="color: black; font-weight: bold;">Total Walmart L30: $0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-skus-count" class="badge bg-primary">0 SKUs selected</span>
                        <select id="discount-type-select" class="form-select form-select-sm" style="width: 120px;">
                            <option value="percentage">Percentage</option>
                            <option value="dollar">Dollar</option>
                        </select>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                               placeholder="Enter %" style="width: 150px;" step="0.01" min="0">
                        <button id="apply-discount-btn" class="btn btn-sm btn-warning">
                            <i class="fas fa-check"></i> Apply Discount
                        </button>
                    </div>
                </div>
                <div id="walmart-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                    </div>
                    <div id="walmart-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Price Modal -->
    <div class="modal fade" id="uploadPriceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fa fa-dollar-sign me-2"></i>Upload Price Data</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadPriceForm" action="{{ route('walmart-sheet-upload-price') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="fa fa-file-excel text-success me-1"></i>Choose File</label>
                            <input type="file" class="form-control" name="excel_file" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i><strong>Warning:</strong> This will TRUNCATE (clear) the table before uploading!
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadPriceForm" class="btn btn-success"><i class="fa fa-upload me-1"></i>Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Listing Modal -->
    <div class="modal fade" id="uploadListingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fa fa-eye me-2"></i>Upload Listing Data</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadListingForm" action="{{ route('walmart-sheet-upload-listing-views') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="fa fa-file-excel text-success me-1"></i>Choose File</label>
                            <input type="file" class="form-control" name="excel_file" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i><strong>Warning:</strong> This will TRUNCATE (clear) the table before uploading!
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadListingForm" class="btn btn-info"><i class="fa fa-upload me-1"></i>Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Order Modal -->
    <div class="modal fade" id="uploadOrderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fa fa-shopping-cart me-2"></i>Upload Order Data</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadOrderForm" action="{{ route('walmart-sheet-upload-order') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="fa fa-file-excel text-success me-1"></i>Choose File</label>
                            <input type="file" class="form-control" name="excel_file" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i><strong>Warning:</strong> This will TRUNCATE (clear) the table before uploading!
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadOrderForm" class="btn btn-primary"><i class="fa fa-upload me-1"></i>Upload</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "walmart_column_visibility";
    let table = null;
    let decreaseModeActive = false;
    let increaseModeActive = false;
    let selectedSkus = new Set();
    
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

        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            const filteredData = table.getData('active');
            
            filteredData.forEach(row => {
                const sku = row['sku'];
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

        $('#apply-discount-btn').on('click', function() {
            applyDiscount();
        });

        $('#discount-percentage-input').on('keypress', function(e) {
            if (e.which === 13) {
                applyDiscount();
            }
        });

        function updateSelectedCount() {
            const count = selectedSkus.size;
            $('#selected-skus-count').text(`${count} SKU${count !== 1 ? 's' : ''} selected`);
            $('#discount-input-container').toggle(count > 0);
        }

        function updateSelectAllCheckbox() {
            if (!table) return;
            
            const filteredData = table.getData('active');
            
            if (filteredData.length === 0) {
                $('#select-all-checkbox').prop('checked', false);
                return;
            }
            
            const filteredSkus = new Set(filteredData.map(row => row['sku']).filter(sku => sku));
            const allFilteredSelected = filteredSkus.size > 0 && 
                Array.from(filteredSkus).every(sku => selectedSkus.has(sku));
            
            $('#select-all-checkbox').prop('checked', allFilteredSelected);
        }

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
            const totalSkus = selectedSkus.size;

            allData.forEach(row => {
                const sku = row['sku'];
                if (selectedSkus.has(sku)) {
                    const currentPrice = parseFloat(row['price']) || 0;
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
                        
                        const tableRow = table.getRows().find(r => r.getData()['sku'] === sku);
                        if (tableRow) {
                            tableRow.update({ sprice: newSPrice });
                            updatedCount++;
                        }
                    }
                }
            });
            
            showToast(`${increaseModeActive ? 'Increase' : 'Discount'} applied to ${updatedCount} SKU(s)`, 'success');
            $('#discount-percentage-input').val('');
        }

        function updateSummary() {
            const data = table.getData("all");
            
            let totalProducts = data.length;
            let totalOrders = 0;
            let totalQuantity = 0;
            let totalTcos = 0;
            let totalSpendL30 = 0;
            let totalPftAmt = 0;
            let totalSalesAmt = 0;
            let totalCogsAmt = 0;
            let totalWalmartL30 = 0;
            let zeroSoldCount = 0;
            let totalWeightedPrice = 0;
            let totalQty = 0;
            let totalViews = 0;
            
            data.forEach(row => {
                const qty = parseInt(row['total_qty']) || 0;
                const price = parseFloat(row['price']) || 0;
                const lp = parseFloat(row['lp']) || 0;
                const ship = parseFloat(row['ship']) || 0;
                const adSpend = parseFloat(row['spend']) || 0;
                const adsPercent = parseFloat(row['ads_percent']) || 0;
                
                totalQuantity += qty;
                totalOrders += parseInt(row['total_orders']) || 0;
                
                // Weighted price calculation
                totalWeightedPrice += price * qty;
                totalQty += qty;
                
                // Sales amount = Price × Qty (with Walmart 80% commission already in profit calc)
                const salesAmt = price * qty;
                totalSalesAmt += salesAmt;
                
                // Ad spend (TCOS)
                totalSpendL30 += adSpend;
                totalTcos += adsPercent;
                
                // Profit calculation (GPFT formula, but total amount)
                // PFT = (Price × 0.80 - LP - Ship) × Qty
                const adDecimal = adsPercent / 100;
                const profitPerUnit = (price * (0.80 - adDecimal)) - ship - lp;
                const profitTotal = profitPerUnit * qty;
                totalPftAmt += profitTotal;
                
                // COGS = LP × Qty (not including ship for ROI calc)
                const cogs = lp * qty;
                totalCogsAmt += cogs;
                
                // Views
                totalViews += parseInt(row['page_views']) || 0;
                
                // Walmart L30 (total sales value)
                totalWalmartL30 += salesAmt;
                
                // Count SKUs with 0 sold
                if (qty === 0) {
                    zeroSoldCount++;
                }
            });
            
            // Calculate averages (same logic as Amazon)
            const avgPrice = totalQty > 0 ? totalWeightedPrice / totalQty : 0;
            
            // AVG GPFT% = (Total PFT / Total Sales) * 100 (before ads impact on profit calc)
            // But we already subtracted ads in profit calc, so we need to add it back for GPFT
            const avgGpft = totalSalesAmt > 0 ? ((totalPftAmt / totalSalesAmt) * 100) : 0;
            
            // TACOS% = (Total Ad Spend / Total Sales) * 100
            const tacosPercent = totalSalesAmt > 0 ? ((totalSpendL30 / totalSalesAmt) * 100) : 0;
            
            // AVG PFT% = GPFT% - TACOS% (net after ads) - but since we already included ads in calc, this is same as avgGpft
            const avgPft = avgGpft; // Already includes ad impact
            
            // ROI% = (Total PFT / Total COGS) * 100
            const roiPercent = totalCogsAmt > 0 ? ((totalPftAmt / totalCogsAmt) * 100) : 0;
            
            // CVR = (Total Qty / Total Views) * 100
            const avgCvr = totalViews > 0 ? (totalQty / totalViews * 100) : 0;
            
            // Update badges (same order as Amazon)
            $('#total-pft-amt-badge').text('Total PFT AMT: $' + Math.round(totalPftAmt).toLocaleString());
            $('#total-sales-amt-badge').text('Total SALES AMT: $' + Math.round(totalSalesAmt).toLocaleString());
            $('#avg-gpft-badge').text('AVG GPFT: ' + avgGpft.toFixed(1) + '%');
            $('#avg-pft-badge').text('AVG PFT: ' + avgPft.toFixed(1) + '%');
            $('#avg-price-badge').text('Avg Price: $' + avgPrice.toFixed(2));
            $('#avg-cvr-badge').text('Avg CVR: ' + avgCvr.toFixed(1) + '%');
            $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
            $('#cvr-badge').text('CVR: ' + avgCvr.toFixed(2) + '%');
            
            // Walmart specific badges
            $('#total-products-badge').text('Total Products: ' + totalProducts.toLocaleString());
            $('#total-quantity-badge').text('Total Quantity: ' + totalQuantity.toLocaleString());
            $('#zero-sold-count-badge').text('0 Sold Count: ' + zeroSoldCount.toLocaleString());
            
            // Financial metrics
            $('#total-tcos-badge').text('Total TCOS: ' + Math.round(tacosPercent) + '%');
            // Fetch campaign spend total
            $('#total-spend-badge').text('Total SPEND L30: Loading...');
            fetch('/walmart/running/ads/data')
                .then(response => response.json())
                .then(responseData => {
                    const campaignSpendTotal = responseData.data.reduce((sum, row) => sum + (parseFloat(row.SPEND_L30) || 0), 0);
                    $('#total-spend-badge').text('Total SPEND L30: $' + Math.round(campaignSpendTotal).toLocaleString());
                    
                    // Update TACOS% with correct spend
                    const tacosPercent = totalSalesAmt > 0 ? ((campaignSpendTotal / totalSalesAmt) * 100) : 0;
                    $('#total-tcos-badge').text('Total TCOS: ' + Math.round(tacosPercent) + '%');
                })
                .catch(error => {
                    console.error('Error fetching campaign spend data:', error);
                    $('#total-spend-badge').text('Total SPEND L30: Error');
                });
            $('#total-cogs-badge').text('COGS AMT: $' + Math.round(totalCogsAmt).toLocaleString());
            $('#roi-percent-badge').text('ROI %: ' + roiPercent.toFixed(1) + '%');
            $('#total-orders-badge').text('Total Orders: ' + totalOrders.toLocaleString());
            $('#total-walmart-l30-badge').text('Total Walmart L30: $' + Math.round(totalWalmartL30).toLocaleString());
        }

        // Color functions (same as Temu)
        const getPftColor = (value) => {
            const percent = parseFloat(value);
            if (percent < 10) return 'red';
            if (percent >= 10 && percent < 15) return 'yellow';
            if (percent >= 15 && percent < 20) return 'blue';
            if (percent >= 20 && percent <= 40) return 'green';
            return 'pink';
        };

        const getRoiColor = (value) => {
            const percent = parseFloat(value);
            if (percent < 50) return 'red';
            if (percent >= 50 && percent < 75) return 'yellow';
            if (percent >= 75 && percent <= 125) return 'green';
            return 'pink';
        };

        table = new Tabulator("#walmart-table", {
            ajaxURL: "/walmart-sheet-upload-data-json",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            columns: [
                {
                    title: "SKU",
                    field: "sku",
                    headerFilter: "input",
                    frozen: true,
                    width: 150
                },
                {
                    title: "W L30",
                    field: "total_qty",
                    hozAlign: "center",
                    sorter: "number",
                    width: 80
                },
                {
                    title: "CVR %",
                    field: "conversion_rate",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        let color = '#000';
                        
                        if (value <= 4) color = '#a00211';
                        else if (value > 4 && value <= 7) color = '#ffc107';
                        else if (value > 7 && value <= 10) color = '#28a745';
                        else color = '#ff1493';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${value.toFixed(1)}%</span>`;
                    },
                    width: 90
                },
                {
                    title: "Views",
                    field: "page_views",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseInt(cell.getValue()) || 0;
                        return value.toLocaleString();
                    },
                    width: 110
                },
                {
                    title: "Price",
                    field: "price",
                    hozAlign: "center",
                    sorter: "number",
                    editor: "input",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    width: 120
                },
                {
                    title: "GPRFT %",
                    field: "profit_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getPftColor(value);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    },
                    width: 100
                },
                {
                    title: "ADS%",
                    field: "ads_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        let color = '#000';
                        
                        if (value == 0 || value == 100) color = '#a00211';
                        else if (value > 0 && value <= 7) color = '#ff1493';
                        else if (value > 7 && value <= 14) color = '#28a745';
                        else if (value > 14 && value <= 21) color = '#ffc107';
                        else if (value > 21) color = '#a00211';
                        
                        return `<span style="color: ${color}; font-weight: 600;">${value.toFixed(1)}%</span>`;
                    },
                    width: 90
                },
                {
                    title: "GROI %",
                    field: "roi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        const colorClass = getRoiColor(value);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(value)}%</span>`;
                    },
                    width: 100
                },
                {
                    title: "NPFT %",
                    field: "npft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const gprft = parseFloat(rowData['profit_percent']) || 0;
                        const ads = parseFloat(rowData['ads_percent']) || 0;
                        const npft = gprft - ads;
                        
                        const colorClass = getPftColor(npft);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(npft)}%</span>`;
                    },
                    width: 100
                },
                {
                    title: "NROI %",
                    field: "nroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const groi = parseFloat(rowData['roi_percent']) || 0;
                        const ads = parseFloat(rowData['ads_percent']) || 0;
                        const nroi = groi - ads;
                        
                        const colorClass = getRoiColor(nroi);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(nroi)}%</span>`;
                    },
                    width: 100
                },
                {
                    title: '<input type="checkbox" id="select-all-checkbox">',
                    field: "_select",
                    headerSort: false,
                    width: 50,
                    visible: false,
                    formatter: function(cell) {
                        const sku = cell.getRow().getData()['sku'];
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isChecked}>`;
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    }
                },
                {
                    title: "S PRC",
                    field: "sprice",
                    hozAlign: "center",
                    editor: "input",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();
                        const price = parseFloat(rowData['price']) || 0;
                        const sprice = parseFloat(value) || 0;
                        
                        if (!value || sprice === 0) return '';
                        
                        if (sprice === price) {
                            return '<span style="color: #999; font-style: italic;">-</span>';
                        }
                        
                        return `$${parseFloat(value).toFixed(2)}`;
                    },
                    width: 80
                },
                {
                    title: "SGPRFT%",
                    field: "sgprft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const ship = parseFloat(rowData['ship']) || 0;
                        const percentage = 0.80; // Walmart percentage
                        
                        if (sprice === 0) return '';
                        
                        // SGPRFT% = ((SPRICE × 0.80 - LP - Ship) / SPRICE) × 100
                        const sgprft = sprice > 0 ? ((sprice * percentage - lp - ship) / sprice) * 100 : 0;
                        
                        const colorClass = getPftColor(sgprft);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(sgprft)}%</span>`;
                    },
                    width: 80
                },
                {
                    title: "SROI%",
                    field: "sroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const ship = parseFloat(rowData['ship']) || 0;
                        const percentage = 0.80; // Walmart percentage
                        
                        if (sprice === 0 || lp === 0) return '';
                        
                        // SROI% = ((SPRICE × 0.80 - LP - Ship) / LP) × 100
                        const sroi = lp > 0 ? ((sprice * percentage - lp - ship) / lp) * 100 : 0;
                        
                        const colorClass = getRoiColor(sroi);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(sroi)}%</span>`;
                    },
                    width: 80
                },
                {
                    title: "SPFT%",
                    field: "spft_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const ship = parseFloat(rowData['ship']) || 0;
                        const adsPercent = parseFloat(rowData['ads_percent']) || 0;
                        const percentage = 0.80; // Walmart percentage
                        
                        if (sprice === 0) return '';
                        
                        // SGPRFT%
                        const sgprft = sprice > 0 ? ((sprice * percentage - lp - ship) / sprice) * 100 : 0;
                        
                        // SPFT% = SGPRFT% - ADS%
                        const spft = sgprft - adsPercent;
                        
                        const colorClass = getPftColor(spft);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(spft)}%</span>`;
                    },
                    width: 80
                },
                {
                    title: "SNROI%",
                    field: "snroi_percent",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sprice = parseFloat(rowData['sprice']) || 0;
                        const lp = parseFloat(rowData['lp']) || 0;
                        const ship = parseFloat(rowData['ship']) || 0;
                        const adsPercent = parseFloat(rowData['ads_percent']) || 0;
                        const percentage = 0.80; // Walmart percentage
                        
                        if (sprice === 0 || lp === 0) return '';
                        
                        // SROI%
                        const sroi = lp > 0 ? ((sprice * percentage - lp - ship) / lp) * 100 : 0;
                        
                        // SNROI% = SROI% - ADS%
                        const snroi = sroi - adsPercent;
                        
                        const colorClass = getRoiColor(snroi);
                        return `<span class="walmart-percent-value ${colorClass}">${Math.round(snroi)}%</span>`;
                    },
                    width: 80
                },
                {
                    title: "Spend",
                    field: "spend",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        return '$' + value.toFixed(2);
                    },
                    width: 100
                },
                {
                    title: "LP",
                    field: "lp",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    width: 100,
                    visible: false
                },
                {
                    title: "Ship",
                    field: "ship",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: "money",
                    formatterParams: {
                        decimal: ".",
                        thousand: ",",
                        symbol: "$",
                        precision: 2
                    },
                    width: 100,
                    visible: false
                }
            ]
        });

        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("sku", "like", value);
            updateSummary();
        });

        // Apply filters
        function applyFilters() {
            const inventoryFilter = $('#inventory-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const cvrFilter = $('#cvr-filter').val();
            const skuSearch = $('#sku-search').val();

            table.clearFilter();

            if (skuSearch) {
                table.setFilter("sku", "like", skuSearch);
            }

            if (inventoryFilter !== 'all') {
                table.addFilter(function(data) {
                    const inv = parseFloat(data.total_qty) || 0;
                    if (inventoryFilter === 'gt0') return inv > 0;
                    if (inventoryFilter === 'eq0') return inv === 0;
                    return true;
                });
            }

            if (gpftFilter !== 'all') {
                table.addFilter(function(data) {
                    const gpft = parseFloat(data.profit_percent) || 0;
                    
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
                    const cvr = parseFloat(data.conversion_rate) || 0;
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

            updateSummary();
            updateSelectAllCheckbox();
        }

        $('#inventory-filter, #gpft-filter, #cvr-filter').on('change', function() {
            applyFilters();
        });

        table.on('cellEdited', function(cell) {
            const row = cell.getRow();
            const data = row.getData();
            const field = cell.getColumn().getField();
            
            if (field === 'price' || field === 'sprice') {
                const newValue = parseFloat(cell.getValue());
                if (newValue < 0) {
                    showToast('Price cannot be negative', 'error');
                    cell.restoreOldValue();
                    return;
                }
                
                row.update({ [field]: newValue });
                row.reformat();
                showToast('Price updated successfully', 'success');
            }
        });

        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field && def.field !== '_select') {
                    const visible = def.visible !== false;
                    const li = document.createElement('li');
                    li.className = 'dropdown-item';
                    li.innerHTML = `
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="${def.field}" 
                                   id="col-${def.field}" ${visible ? 'checked' : ''}>
                            <label class="form-check-label" for="col-${def.field}">
                                ${def.title}
                            </label>
                        </div>
                    `;
                    menu.appendChild(li);
                }
            });
        }

        table.on('tableBuilt', function() {
            buildColumnDropdown();
        });

        table.on('dataLoaded', function() {
            applyFilters();
            updateSummary();
        });

        table.on('renderComplete', function() {
            updateSummary();
        });

        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.type === 'checkbox') {
                const field = e.target.value;
                const col = table.getColumn(field);
                if (e.target.checked) {
                    col.show();
                } else {
                    col.hide();
                }
            }
        });

        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => col.show());
            buildColumnDropdown();
        });

        $('#export-btn').on('click', function() {
            table.download("csv", "walmart_data.csv");
        });
    });
</script>
@endsection
