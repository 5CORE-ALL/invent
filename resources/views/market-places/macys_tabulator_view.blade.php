@extends('layouts.vertical', ['title' => "Macy's Pricing", 'sidenav' => 'condensed'])

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
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => "Macy's Pricing",
        'sub_title' => "Macy's Pricing",
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Macy's Data</h4>
                <div class="d-flex align-items-center flex-wrap gap-2">
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

                    <select id="dil-filter" class="form-select form-select-sm"
                        style="width: auto; display: inline-block;">
                        <option value="all">All DIL%</option>
                        <option value="red">Red (&lt;16.7%)</option>
                        <option value="yellow">Yellow (16.7-25%)</option>
                        <option value="green">Green (25-50%)</option>
                        <option value="pink">Pink (50%+)</option>
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

                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#uploadPriceModal">
                        <i class="fa fa-dollar-sign"></i> Upload Price
                    </button>

                    <button id="sugg-amz-prc-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-copy"></i> Sugg Amz Prc
                    </button>

                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-arrow-down"></i> Decrease Mode
                    </button>
                    
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-arrow-up"></i> Increase Mode
                    </button>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary ({{ $macysPercentage }}% Margin)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-badge" style="color: black; font-weight: bold;">Total PFT: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color: black; font-weight: bold;">Total Sales: $0</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;">AVG GPFT: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-inv-badge" style="color: black; font-weight: bold;">Total INV: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-l30-badge" style="color: black; font-weight: bold;">Total MC L30: 0</span>
                        <span class="badge bg-danger fs-6 p-2" id="zero-sold-count-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter 0 sold items">0 Sold: 0</span>
                        <span class="badge fs-6 p-2" id="more-sold-count-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter items with sales">&gt; 0 Sold</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-dil-badge" style="color: black; font-weight: bold;">DIL%: 0%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-cogs-badge" style="color: black; font-weight: bold;">COGS: $0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge" style="color: black; font-weight: bold;">ROI%: 0%</span>
                        <span class="badge bg-danger fs-6 p-2" id="less-amz-badge" style="color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices less than Amazon">&lt; Amz</span>
                        <span class="badge fs-6 p-2" id="more-amz-badge" style="background-color: #28a745; color: white; font-weight: bold; cursor: pointer;" title="Click to filter prices greater than Amazon">&gt; Amz</span>
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
                        <button id="clear-sprice-btn" class="btn btn-danger btn-sm">
                            <i class="fas fa-eraser"></i> Clear SPRICE
                        </button>
                    </div>
                </div>
                <div id="macys-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control" placeholder="Search SKU...">
                    </div>
                    <!-- Table body -->
                    <div id="macys-table" style="flex: 1;"></div>
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
                    <form id="uploadPriceForm" action="{{ route('macys.upload.price') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="fa fa-file-excel text-success me-1"></i>Choose File</label>
                            <input type="file" class="form-control" name="excel_file" accept=".xlsx,.xls,.csv,.tsv" required>
                            <small class="text-muted">Supported formats: Excel (.xlsx, .xls), CSV, TSV</small>
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
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "macys_tabulator_column_visibility";
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
        });

        // > 0 Sold badge click handler - filter to show items with sales > 0
        let moreSoldFilterActive = false;
        $('#more-sold-count-badge').on('click', function() {
            moreSoldFilterActive = !moreSoldFilterActive;
            zeroSoldFilterActive = false; // Deactivate the other filter
            applyFilters();
        });

        // < Amz badge click handler - filter prices less than Amazon
        let lessAmzFilterActive = false;
        $('#less-amz-badge').on('click', function() {
            lessAmzFilterActive = !lessAmzFilterActive;
            moreAmzFilterActive = false; // Deactivate the other filter
            applyFilters();
        });

        // > Amz badge click handler - filter prices greater than Amazon
        let moreAmzFilterActive = false;
        $('#more-amz-badge').on('click', function() {
            moreAmzFilterActive = !moreAmzFilterActive;
            lessAmzFilterActive = false; // Deactivate the other filter
            applyFilters();
        });

        // Upload Price Form Handler
        $('#uploadPriceForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            $.ajax({
                url: $(this).attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#uploadPriceModal').modal('hide');
                    showToast(response.success || 'Price data uploaded successfully!', 'success');
                    table.setData(); // Reload table data
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON?.error || 'Error uploading file';
                    showToast(errorMsg, 'error');
                }
            });
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

        // Custom price rounding function to round to .99 endings
        function roundToRetailPrice(price) {
            // Round to the nearest dollar and subtract 0.01 to make it .99
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
            const updates = []; // Store updates for backend saving
            
            // Loop through selected SKUs
            selectedSkus.forEach(sku => {
                const rows = table.searchRows("(Child) sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0];
                    const rowData = row.getData();
                    const currentPrice = parseFloat(rowData['MC Price']) || 0;
                    
                    if (currentPrice > 0) {
                        let newSprice;
                        
                        if (discountType === 'percentage') {
                            if (decreaseModeActive) {
                                newSprice = currentPrice * (1 - discountValue / 100);
                            } else {
                                newSprice = currentPrice * (1 + discountValue / 100);
                            }
                        } else {
                            if (decreaseModeActive) {
                                newSprice = currentPrice - discountValue;
                            } else {
                                newSprice = currentPrice + discountValue;
                            }
                        }
                        
                        // Apply retail price rounding (round to .99 endings)
                        newSprice = roundToRetailPrice(newSprice);
                        
                        // Ensure minimum price
                        newSprice = Math.max(0.99, newSprice);
                        
                        // Calculate metrics with 80% margin
                        const percentage = 0.80; // 80% margin for Macys
                        const lp = parseFloat(rowData['LP_productmaster']) || 0;
                        const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                        
                        const sgpft = newSprice > 0 ? Math.round(((newSprice * percentage - ship - lp) / newSprice) * 100 * 100) / 100 : 0;
                        const spft = sgpft; // Same as SGPFT for Macys (no ads)
                        const sroi = lp > 0 ? Math.round(((newSprice * percentage - lp - ship) / lp) * 100 * 100) / 100 : 0;
                        
                        // Update SPRICE and metrics in table
                        row.update({
                            SPRICE: newSprice,
                            SGPFT: sgpft,
                            SPFT: spft,
                            SROI: sroi
                        });
                        
                        // Store update for backend saving
                        updates.push({
                            sku: sku,
                            sprice: newSprice
                        });
                        
                        updatedCount++;
                    }
                }
            });
            
            // Save to backend if there are updates
            if (updates.length > 0) {
                saveSpriceUpdates(updates);
            }
            
            showToast(`${decreaseModeActive ? 'Decrease' : 'Increase'} applied to ${updatedCount} SKU(s) based on MC Price`, 'success');
            $('#discount-percentage-input').val('');
        }

        // Apply Amazon suggested price
        function applySuggestAmazonPrice() {
            if (selectedSkus.size === 0) {
                showToast('Please select SKUs first', 'error');
                return;
            }

            let updatedCount = 0;
            let noAmazonPriceCount = 0;
            const updates = []; // Store updates for backend saving

            // Loop through selected SKUs
            selectedSkus.forEach(sku => {
                const rows = table.searchRows("(Child) sku", "=", sku);
                
                if (rows.length > 0) {
                    const row = rows[0];
                    const rowData = row.getData();
                    const amazonPrice = parseFloat(rowData['A Price']);
                    
                    if (amazonPrice && amazonPrice > 0) {
                        // Calculate metrics with 80% margin
                        const percentage = 0.80; // 80% margin for Macys
                        const lp = parseFloat(rowData['LP_productmaster']) || 0;
                        const ship = parseFloat(rowData['Ship_productmaster']) || 0;
                        
                        const sgpft = amazonPrice > 0 ? Math.round(((amazonPrice * percentage - ship - lp) / amazonPrice) * 100 * 100) / 100 : 0;
                        const spft = sgpft; // Same as SGPFT for Macys (no ads)
                        const sroi = lp > 0 ? Math.round(((amazonPrice * percentage - lp - ship) / lp) * 100 * 100) / 100 : 0;
                        
                        // Update the row with Amazon price and calculated metrics
                        row.update({
                            SPRICE: amazonPrice,
                            SGPFT: sgpft,
                            SPFT: spft,
                            SROI: sroi
                        });
                        
                        // Store update for backend saving
                        updates.push({
                            sku: sku,
                            sprice: amazonPrice
                        });
                        
                        updatedCount++;
                    } else {
                        noAmazonPriceCount++;
                    }
                } else {
                    noAmazonPriceCount++;
                }
            });
            
            // Save to backend if there are updates
            if (updates.length > 0) {
                saveSpriceUpdates(updates);
            }
            
            let message = `Amazon price applied to ${updatedCount} SKU(s)`;
            if (noAmazonPriceCount > 0) {
                message += ` (${noAmazonPriceCount} SKU(s) had no Amazon price or not found)`;
            }
            
            showToast(message, updatedCount > 0 ? 'success' : 'warning');
        }

        // Save SPRICE updates to backend (unified function for all SPRICE updates)
        function saveSpriceUpdates(updates) {
            $.ajax({
                url: '/macys-save-sprice-batch',
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
                        // Show subtle success notification
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

            // Get all rows and filter by selected SKUs
            table.getRows().forEach(row => {
                const rowData = row.getData();
                const sku = rowData['(Child) sku'];
                
                if (selectedSkus.has(sku)) {
                    // Clear SPRICE in table
                    row.update({
                        SPRICE: 0,
                        SGPFT: 0,
                        SPFT: 0,
                        SROI: 0
                    });
                    
                    // Store update for backend saving
                    updates.push({
                        sku: sku,
                        sprice: 0
                    });
                    
                    clearedCount++;
                }
            });

            // Save to backend if there are updates
            if (updates.length > 0) {
                saveSpriceUpdates(updates);
            }

            showToast(`SPRICE cleared for ${clearedCount} SKU(s)`, 'success');
        }

        // SAVE SPRICE to database with retry
        function saveSpriceWithRetry(sku, sprice, row, retryCount = 0) {
            const maxRetries = 3;
            
            $.ajax({
                url: '/macys-save-sprice-tabulator',
                method: 'POST',
                data: {
                    sku: sku,
                    sprice: sprice,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    showToast(`SPRICE saved for ${sku}`, 'success');
                    if (response.spft_percent !== undefined) {
                        row.update({ SPFT: response.spft_percent });
                    }
                    if (response.sroi_percent !== undefined) {
                        row.update({ SROI: response.sroi_percent });
                    }
                    if (response.sgpft_percent !== undefined) {
                        row.update({ SGPFT: response.sgpft_percent });
                    }
                },
                error: function(xhr) {
                    if (retryCount < maxRetries) {
                        setTimeout(() => saveSpriceWithRetry(sku, sprice, row, retryCount + 1), 2000);
                    } else {
                        showToast(`Failed to save SPRICE for ${sku}`, 'error');
                    }
                }
            });
        }

        // Initialize Tabulator
        table = new Tabulator("#macys-table", {
            ajaxURL: "/macys-data-json",
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
                column: "MC L30",
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
                            return `<img src="${value}" alt="Product" style="width: 50px; height: 50px; object-fit: cover;">`;
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
                    title: "Links",
                    field: "links_column",
                    frozen: true,
                    width: 100,
                    hozAlign: "center",
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const buyerLink = rowData['B Link'] || '';
                        const sellerLink = rowData['S Link'] || '';
                        
                        let html = '<div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">';
                        
                        if (sellerLink) {
                            html += `<a href="${sellerLink}" target="_blank" class="text-info" style="font-size: 12px; text-decoration: none;">
                                <i class="fa fa-link"></i> S Link
                            </a>`;
                        }
                        
                        if (buyerLink) {
                            html += `<a href="${buyerLink}" target="_blank" class="text-success" style="font-size: 12px; text-decoration: none;">
                                <i class="fa fa-link"></i> B Link
                            </a>`;
                        }
                        
                        if (!sellerLink && !buyerLink) {
                            html += '<span class="text-muted" style="font-size: 12px;">-</span>';
                        }
                        
                        html += '</div>';
                        return html;
                    },
                    headerSort: false
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
                    field: "MC Dil%",
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
                    title: "MC L30",
                    field: "MC L30",
                    hozAlign: "center",
                    width: 50,
                    sorter: "number"
                },
                {
                    title: "MC SQty",
                    field: "MC Sales Qty",
                    hozAlign: "center",
                    width: 60,
                    sorter: "number",
                    formatter: function(cell) {
                        let val = parseFloat(cell.getValue()) || 0;
                        return `<span style="font-weight: 600; color: #6f42c1;">${val}</span>`;
                    }
                },
                {
                    title: "NR/REQ",
                    field: "nr_req",
                    hozAlign: "center",
                    headerSort: false,
                    formatter: function(cell) {
                        let value = cell.getValue();
                        if (value === null || value === undefined || value === '' || value.trim() === '') {
                            value = 'REQ';
                        }
                        
                        return `<select class="form-select form-select-sm nr-req-dropdown" 
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
                    title: "Prc",
                    field: "MC Price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue() || 0);
                        const rowData = cell.getRow().getData();
                        const amazonPrice = parseFloat(rowData['A Price']) || 0;
                        
                        if (value === 0) {
                            return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                        }
                        
                        // Show red if MC Price is less than Amazon Price
                        if (amazonPrice > 0 && value < amazonPrice) {
                            return `<span style="color: #a00211; font-weight: 600;">$${value.toFixed(2)}</span>`;
                        }
                        
                        // Show green if MC Price is greater than Amazon Price
                        if (amazonPrice > 0 && value > amazonPrice) {
                            return `<span style="color: #28a745; font-weight: 600;">$${value.toFixed(2)}</span>`;
                        }
                        
                        return `$${value.toFixed(2)}`;
                    },
                    width: 70
                },
                {
                    title: "A Price",
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

        // NR/REQ dropdown change handler
        $(document).on('change', '.nr-req-dropdown', function() {
            const $cell = $(this).closest('.tabulator-cell');
            const $rowEl = $cell.closest('.tabulator-row');
            const row = table.getRow($rowEl[0]);
            const rowData = row.getData();
            const sku = rowData['(Child) sku'];
            const newValue = $(this).val();
            
            $.ajax({
                url: '{{ url("/macys-update-nr-req") }}',
                method: 'POST',
                data: {
                    sku: sku,
                    nr_req: newValue,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    showToast(`${sku}: Status updated to ${newValue}`, 'success');
                    row.update({ nr_req: newValue });
                },
                error: function(xhr) {
                    showToast(`Failed to update status for ${sku}`, 'error');
                }
            });
        });

        // SPRICE cell edited - save to database
        table.on('cellEdited', function(cell) {
            if (cell.getField() === 'SPRICE') {
                const row = cell.getRow();
                const rowData = row.getData();
                const sku = rowData['(Child) sku'];
                const newSprice = parseFloat(cell.getValue()) || 0;
                
                // Recalculate SGPFT, SPFT, SROI with 80% margin
                const percentage = 0.80; // 80% margin for Macys
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
                
                // Save to database
                saveSpriceWithRetry(sku, newSprice, row);
            }
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
            const nrlFilter = $('#nrl-filter').val();
            const gpftFilter = $('#gpft-filter').val();
            const dilFilter = $('#dil-filter').val();

            // Clear all filters first
            table.clearFilter();

            // Inventory filter
            if (inventoryFilter === 'zero') {
                table.addFilter("INV", "=", 0);
            } else if (inventoryFilter === 'more') {
                table.addFilter("INV", ">", 0);
            }

            // NRL filter
            if (nrlFilter === 'REQ') {
                table.addFilter("nr_req", "=", "REQ");
            } else if (nrlFilter === 'NR') {
                table.addFilter("nr_req", "=", "NR");
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

            // DIL filter (calculated as L30 / INV * 100)
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

            // 0 Sold filter (based on MC L30) - triggered by badge click
            if (zeroSoldFilterActive) {
                table.addFilter("MC L30", "=", 0);
            }

            // > 0 Sold filter (based on MC L30) - triggered by badge click
            if (moreSoldFilterActive) {
                table.addFilter("MC L30", ">", 0);
            }

            // < Amz filter - show prices less than Amazon price
            if (lessAmzFilterActive) {
                table.addFilter(function(data) {
                    const mcPrice = parseFloat(data['MC Price']) || 0;
                    const amazonPrice = parseFloat(data['A Price']) || 0;
                    return amazonPrice > 0 && mcPrice > 0 && mcPrice < amazonPrice;
                });
            }

            // > Amz filter - show prices greater than Amazon price
            if (moreAmzFilterActive) {
                table.addFilter(function(data) {
                    const mcPrice = parseFloat(data['MC Price']) || 0;
                    const amazonPrice = parseFloat(data['A Price']) || 0;
                    return amazonPrice > 0 && mcPrice > 0 && mcPrice > amazonPrice;
                });
            }

            updateSummary();
        }

        $('#inventory-filter, #nrl-filter, #gpft-filter, #dil-filter').on('change', function() {
            applyFilters();
        });

        // Update summary badges
        function updateSummary() {
            const inventoryFilter = $('#inventory-filter').val();
            const data = table.getData('active').filter(row => {
                return !(row.Parent && row.Parent.startsWith('PARENT'));
            });

            let totalPft = 0, totalSales = 0, totalGpft = 0, totalPrice = 0, priceCount = 0;
            let totalInv = 0, totalL30 = 0, zeroSoldCount = 0, totalDil = 0, dilCount = 0;
            let totalCogs = 0, totalRoi = 0, roiCount = 0;

            data.forEach(row => {
                totalPft += parseFloat(row.Profit) || 0;
                totalSales += parseFloat(row['Sales L30']) || 0;
                totalGpft += parseFloat(row['GPFT%']) || 0;
                
                const price = parseFloat(row['MC Price']) || 0;
                if (price > 0) {
                    totalPrice += price;
                    priceCount++;
                }
                
                totalInv += parseFloat(row.INV) || 0;
                totalL30 += parseFloat(row['MC L30']) || 0;
                
                if ((parseFloat(row['MC L30']) || 0) === 0) {
                    zeroSoldCount++;
                }
                
                const dil = parseFloat(row['MC Dil%']) || 0;
                if (dil > 0) {
                    totalDil += dil;
                    dilCount++;
                }
                
                const lp = parseFloat(row['LP_productmaster']) || 0;
                const l30 = parseFloat(row['MC L30']) || 0;
                totalCogs += lp * l30;
                
                const roi = parseFloat(row['ROI%']) || 0;
                if (roi !== 0) {
                    totalRoi += roi;
                    roiCount++;
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
            $('#total-l30-badge').text(`Total MC L30: ${totalL30.toLocaleString()}`);
            $('#zero-sold-count-badge').text(`0 Sold: ${zeroSoldCount}`);
            $('#avg-dil-badge').text(`DIL%: ${(avgDil * 100).toFixed(1)}%`);
            $('#total-cogs-badge').text(`COGS: $${Math.round(totalCogs).toLocaleString()}`);
            $('#roi-percent-badge').text(`ROI%: ${avgRoi.toFixed(1)}%`);
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
                url: '/macys-pricing-column-visibility',
                method: 'POST',
                data: {
                    visibility: visibility,
                    _token: '{{ csrf_token() }}'
                }
            });
        }

        function applyColumnVisibilityFromServer() {
            $.ajax({
                url: '/macys-pricing-column-visibility',
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
            setTimeout(function() {
                applyFilters();
                updateSummary();
            }, 100);
        });

        table.on('renderComplete', function() {
            setTimeout(function() {
                updateSummary();
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

        // Export Sheet button
        document.getElementById("export-btn").addEventListener("click", function() {
            const exportData = [];
            const visibleColumns = table.getColumns().filter(col => col.isVisible() && col.getField() !== '_select');
            
            // Get headers
            const headers = visibleColumns.map(col => col.getDefinition().title || col.getField());
            exportData.push(headers);
            
            // Get filtered data
            const data = table.getData("active");
            data.forEach(row => {
                const rowData = [];
                visibleColumns.forEach(col => {
                    const field = col.getField();
                    let value = row[field];
                    
                    // Clean up values
                    if (value === null || value === undefined) {
                        value = '';
                    } else if (typeof value === 'number') {
                        value = parseFloat(value.toFixed(2));
                    }
                    rowData.push(value);
                });
                exportData.push(rowData);
            });
            
            // Create CSV
            let csv = '';
            exportData.forEach(row => {
                csv += row.map(cell => {
                    if (typeof cell === 'string' && (cell.includes(',') || cell.includes('"') || cell.includes('\n'))) {
                        return '"' + cell.replace(/"/g, '""') + '"';
                    }
                    return cell;
                }).join(',') + '\n';
            });
            
            // Download
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'macys_pricing_export_' + new Date().toISOString().slice(0,10) + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showToast('Export downloaded successfully!', 'success');
        });
    });
</script>
@endsection

