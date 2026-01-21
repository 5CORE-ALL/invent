@extends('layouts.vertical', ['title' => 'Doba pricing Inc/Dsc', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        .parent-row {
            background-color: #bde0ff !important;
            font-weight: bold !important;
        }

        .tabulator-row.parent-row {
            background-color: #bde0ff !important;
            font-weight: bold !important;
        }

        .tabulator-row.nr-hide {
            background-color: rgba(220, 53, 69, 0.1) !important;
        }

        /* Color coded cells */
        .dil-percent-value {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        .dil-percent-value.red {
            color: #dc3545;
        }

        .dil-percent-value.blue {
            color: #3591dc;
        }

        .dil-percent-value.yellow {
            color: #ffc107;
        }

        .dil-percent-value.green {
            color: #28a745;
        }

        .dil-percent-value.pink {
            color: #e83e8c;
        }

        .dil-percent-value.gray {
            color: #6c757d;
        }

        /* SKU Tooltips */
        .sku-tooltip-container {
            position: relative;
            display: inline-block;
        }

        .sku-tooltip {
            visibility: hidden;
            width: auto;
            min-width: 120px;
            background-color: #fff;
            color: #333;
            text-align: left;
            border-radius: 4px;
            padding: 8px;
            position: absolute;
            z-index: 1001;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #ddd;
            white-space: nowrap;
        }

        .sku-tooltip-container:hover .sku-tooltip {
            visibility: visible;
            opacity: 1;
        }

        .sku-link {
            padding: 4px 0;
            white-space: nowrap;
        }

        .sku-link a {
            color: #0d6efd;
            text-decoration: none;
        }

        .sku-link a:hover {
            text-decoration: underline;
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
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Doba Listing',
        'sub_title' => 'Doba Listing',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Filters -->
                    <select id="inventory-filter" class="form-select form-select-sm" style="width: 120px;">
                        <option value="">All INV</option>
                        <option value="positive" selected>INV > 0</option>
                        <option value="zero">INV = 0</option>
                    </select>

                    <select id="parent-filter" class="form-select form-select-sm" style="width: 150px;">
                        <option value="">All Products</option>
                        <option value="parent">Parent Only</option>
                        <option value="child">Child Only</option>
                    </select>

                    <select id="missing-filter" class="form-select form-select-sm" style="width: 150px;">
                        <option value="">All Products</option>
                        <option value="missing">Missing Only</option>
                    </select>

                    <!-- Column Visibility -->
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" 
                                data-bs-toggle="dropdown" id="column-visibility-btn">
                            <i class="fas fa-columns"></i> Columns
                        </button>
                        <div class="dropdown-menu" id="column-dropdown-menu" style="max-height: 300px; overflow-y: auto;">
                            <button class="dropdown-item" id="show-all-columns-btn">Show All Columns</button>
                            <div class="dropdown-divider"></div>
                        </div>
                    </div>

                    <!-- Export -->
                    <button id="export-csv-btn" class="btn btn-success btn-sm">
                        <i class="fas fa-download"></i> Export CSV
                    </button>

                    <!-- Reload -->
                    <button id="reload-data-btn" class="btn btn-info btn-sm">
                        <i class="fas fa-refresh"></i> Reload
                    </button>
                    
                    <!-- Decrease/Increase Modes -->
                    <button id="decrease-btn" class="btn btn-sm btn-warning">
                        <i class="fas fa-percent"></i> Decrease
                    </button>
                    
                    <button id="increase-btn" class="btn btn-sm btn-success">
                        <i class="fas fa-percent"></i> Increase
                    </button>
                    
                    <!-- Push to Doba -->
                    <button id="push-to-doba-btn" class="btn btn-sm btn-primary" style="display: none;">
                        <i class="fas fa-upload"></i> Push to Doba
                    </button>
                </div>

                <!-- Summary Stats from marketplace_daily_metrics -->
                <div id="summary-stats" class="mt-3 mb-2">
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <span id="total-skus" class="badge bg-primary p-2 fw-bold fs-6" style="color: white !important;">Total SKUs: 0</span>
                        <span id="zero-sold-count" class="badge bg-danger p-2 fw-bold fs-6" style="color: white !important;">0 SOLD: 0</span>
                        <span id="sold-count" class="badge bg-success p-2 fw-bold fs-6" style="color: white !important;">SOLD: 0</span>
                        <span id="missing-count" class="badge bg-warning p-2 fw-bold fs-6" style="color: black !important; cursor: pointer;" title="Click to filter missing items"><i class="fas fa-exclamation-triangle"></i> Missing: 0</span>
                        <span id="total-sales-badge" class="badge fs-6 p-2" style="background-color: #17a2b8; color: white; font-weight: bold;">Total Sales: $0</span>
                        <span id="pft-percentage-badge" class="badge bg-danger fs-6 p-2" style="color: white; font-weight: bold;">GPFT %: 0%</span>
                        <span id="roi-percentage-badge" class="badge fs-6 p-2" style="background-color: purple; color: white; font-weight: bold;">ROI %: 0%</span>
                        <span id="pft-total-badge" class="badge bg-dark fs-6 p-2" style="color: white; font-weight: bold;">GPFT Total: $0</span>
                        <span id="total-cogs-badge" class="badge bg-secondary fs-6 p-2" style="color: white; font-weight: bold;">Total COGS: $0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <!-- Discount Input Box (shown when SKUs are selected) -->
                <div id="discount-input-container" class="p-2 bg-light border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0 fw-bold">Type:</label>
                        <select id="discount-type-select" class="form-select form-select-sm" style="width: 130px;">
                            <option value="percentage">Percentage</option>
                            <option value="value">Value ($)</option>
                        </select>
                        
                        <label class="mb-0 fw-bold" id="discount-input-label">Value:</label>
                        <input type="number" id="discount-percentage-input" class="form-control form-control-sm" 
                            placeholder="Enter percentage" step="0.01" min="0"
                            style="width: 150px; display: inline-block;">
                        <button id="apply-discount-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-check"></i> Apply
                        </button>
                        <span id="selected-skus-count" class="text-muted ms-2"></span>
                    </div>
                </div>
                <div id="doba-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control" placeholder="Search SKU...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div id="doba-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        const COLUMN_VIS_KEY = "doba_tabulator_column_visibility";
        let table = null; // Global table reference
        let decreaseModeActive = false; // Track decrease mode state
        let increaseModeActive = false; // Track increase mode state
        let selectedSkus = new Set(); // Track selected SKUs across all pages

        $(document).ready(function() {

            // SKU Search functionality
            $('#sku-search').on('keyup', function() {
                const value = $(this).val();
                table.setFilter("(Child) sku", "like", value);
            });

            // Discount type dropdown change handler
            $('#discount-type-select').on('change', function() {
                const type = $(this).val();
                const $input = $('#discount-percentage-input');
                
                if (type === 'percentage') {
                    $input.attr('placeholder', 'Enter percentage');
                    $input.attr('max', '100');
                } else {
                    $input.attr('placeholder', 'Enter value');
                    $input.removeAttr('max');
                }
            });

            // Decrease Mode Toggle
            $('#decrease-btn').on('click', function() {
                decreaseModeActive = !decreaseModeActive;
                const selectColumn = table.getColumn('_select');
                
                if (decreaseModeActive) {
                    // Disable increase mode if active
                    if (increaseModeActive) {
                        increaseModeActive = false;
                        $('#increase-btn').removeClass('btn-danger').addClass('btn-success');
                        $('#increase-btn').html('<i class="fas fa-percent"></i> Increase');
                    }
                    selectColumn.show();
                    $(this).removeClass('btn-warning').addClass('btn-danger');
                    $(this).html('<i class="fas fa-times"></i> Cancel Decrease');
                } else {
                    selectColumn.hide();
                    $(this).removeClass('btn-danger').addClass('btn-warning');
                    $(this).html('<i class="fas fa-percent"></i> Decrease');
                    // Clear all selections
                    selectedSkus.clear();
                    $('.sku-select-checkbox').prop('checked', false);
                    $('#select-all-checkbox').prop('checked', false);
                    $('#discount-input-container').hide();
                }
            });

            // Increase Mode Toggle
            $('#increase-btn').on('click', function() {
                increaseModeActive = !increaseModeActive;
                const selectColumn = table.getColumn('_select');
                
                if (increaseModeActive) {
                    // Disable decrease mode if active
                    if (decreaseModeActive) {
                        decreaseModeActive = false;
                        $('#decrease-btn').removeClass('btn-danger').addClass('btn-warning');
                        $('#decrease-btn').html('<i class="fas fa-percent"></i> Decrease');
                    }
                    selectColumn.show();
                    $(this).removeClass('btn-success').addClass('btn-danger');
                    $(this).html('<i class="fas fa-times"></i> Cancel Increase');
                } else {
                    selectColumn.hide();
                    $(this).removeClass('btn-danger').addClass('btn-success');
                    $(this).html('<i class="fas fa-percent"></i> Increase');
                    // Clear all selections
                    selectedSkus.clear();
                    $('.sku-select-checkbox').prop('checked', false);
                    $('#select-all-checkbox').prop('checked', false);
                    $('#discount-input-container').hide();
                }
            });

            // Checkbox change handler - track selected SKUs
            $(document).on('change', '.sku-select-checkbox', function() {
                const sku = $(this).data('sku');
                const isChecked = $(this).prop('checked');
                
                if (isChecked) {
                    selectedSkus.add(sku);
                } else {
                    selectedSkus.delete(sku);
                }
                
                updateSelectedCount();
                updateSelectAllCheckbox();
                updatePushButtonVisibility(); // Update push button count when selection changes
            });

            // Update selected count and discount input visibility
            function updateSelectedCount() {
                const selectedCount = selectedSkus.size;
                
                if (selectedCount > 0) {
                    $('#discount-input-container').show();
                    $('#selected-skus-count').text(`(${selectedCount} SKU${selectedCount > 1 ? 's' : ''} selected)`);
                } else {
                    $('#discount-input-container').hide();
                }
            }

            // Update select all checkbox state based on current selections
            function updateSelectAllCheckbox() {
                if (!table) return;
                
                // Get all filtered data (excluding parent rows)
                const filteredData = table.getData('active').filter(row => !row.is_parent);
                
                if (filteredData.length === 0) {
                    $('#select-all-checkbox').prop('checked', false);
                    return;
                }
                
                // Get all filtered SKUs
                const filteredSkus = new Set(filteredData.map(row => row['(Child) sku']).filter(sku => sku));
                
                // Check if all filtered SKUs are selected
                const allFilteredSelected = filteredSkus.size > 0 && 
                    Array.from(filteredSkus).every(sku => selectedSkus.has(sku));
                
                $('#select-all-checkbox').prop('checked', allFilteredSelected);
            }

            // Select All checkbox handler
            $(document).on('change', '#select-all-checkbox', function() {
                const isChecked = $(this).prop('checked');
                
                // Get all filtered data (excluding parent rows)
                const filteredData = table.getData('active').filter(row => !row.is_parent);
                
                // Add or remove all filtered SKUs from the selected set
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
                
                // Update all visible checkboxes
                $('.sku-select-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    $(this).prop('checked', selectedSkus.has(sku));
                });
                
                updateSelectedCount();
                updatePushButtonVisibility(); // Update push button count when select all changes
            });

            // Apply Discount/Increase Button
            $('#apply-discount-btn').on('click', function() {
                const inputValue = parseFloat($('#discount-percentage-input').val());
                
                if (isNaN(inputValue) || inputValue < 0) {
                    showToast('danger', 'Please enter a valid positive number');
                    return;
                }
                
                if (selectedSkus.size === 0) {
                    showToast('danger', 'Please select at least one SKU');
                    return;
                }
                
                if (!decreaseModeActive && !increaseModeActive) {
                    showToast('danger', 'Please activate Decrease or Increase mode first');
                    return;
                }
                
                const mode = increaseModeActive ? 'increase' : 'decrease';
                const discountType = $('#discount-type-select').val();
                let successCount = 0;
                let errorCount = 0;
                let totalToProcess = selectedSkus.size;
                const skusToProcess = Array.from(selectedSkus);
                let currentIndex = 0;
                
                // Disable button during processing
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Applying...');
                
                // Process SKUs sequentially (like Amazon)
                function processNextSku() {
                    if (currentIndex >= skusToProcess.length) {
                        // All done
                        $('#apply-discount-btn').prop('disabled', false).html('<i class="fas fa-check"></i> Apply');
                        
                        if (successCount > 0) {
                            const action = mode === 'decrease' ? 'decreased' : 'increased';
                            showToast('success', `Successfully ${action} SPRICE for ${successCount} SKU(s)`);
                        }
                        if (errorCount > 0) {
                            showToast('warning', `${errorCount} SKU(s) could not be updated`);
                        }
                        
                        // DON'T clear selections - keep checkboxes checked like Amazon
                        // Update Push to Doba button visibility
                        updatePushButtonVisibility();
                        return;
                    }
                    
                    const sku = skusToProcess[currentIndex];
                    
                    // Find the row
                    let row = null;
                    table.getRows().forEach(r => {
                        if (r.getData()['(Child) sku'] === sku) {
                            row = r;
                        }
                    });
                    
                    if (row) {
                        const rowData = row.getData();
                        const currentPrice = parseFloat(rowData['doba Price']) || 0;
                        
                        if (currentPrice <= 0) {
                            row.update({ apply_status: 'error' });
                            errorCount++;
                            currentIndex++;
                            processNextSku();
                            return;
                        }
                        
                        // Show clock icon while processing
                        row.update({ apply_status: 'applying' });
                        
                        let newPrice;
                        if (discountType === 'percentage') {
                            const adjustmentAmount = currentPrice * (inputValue / 100);
                            newPrice = mode === 'decrease' 
                                ? currentPrice - adjustmentAmount 
                                : currentPrice + adjustmentAmount;
                        } else {
                            newPrice = mode === 'decrease' 
                                ? currentPrice - inputValue 
                                : currentPrice + inputValue;
                        }
                        
                        newPrice = Math.max(0, newPrice);
                        newPrice = parseFloat(newPrice.toFixed(2));
                        
                        // Calculate Self Pick Price = SPRICE - SHIP
                        const ship = parseFloat(rowData.Ship_productmaster) || 0;
                        const calculatedSelfPick = Math.max(0, newPrice - ship);
                        const selfPickValue = parseFloat(calculatedSelfPick.toFixed(2));
                        
                        // Calculate SPFT and SROI with new price
                        const lp = parseFloat(rowData.LP_productmaster) || 0;
                        
                        let spftValue = 0;
                        let sroiValue = 0;
                        if (newPrice > 0 && lp > 0) {
                            spftValue = ((newPrice * 0.95) - ship - lp) / newPrice * 100;
                            sroiValue = ((newPrice * 0.95) - ship - lp) / lp * 100;
                        }
                        
                        // Save to database
                        $.ajax({
                            url: '/doba/save-sprice',
                            method: 'POST',
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                sku: sku,
                                sprice: newPrice,
                                spft_percent: spftValue.toFixed(2),
                                sroi_percent: sroiValue.toFixed(2),
                                s_self_pick: selfPickValue
                            },
                            success: function(response) {
                                // Update row with new values and show double tick
                                row.update({ 
                                    sprice: newPrice, 
                                    s_self_pick: selfPickValue,
                                    spft: spftValue,
                                    sroi: sroiValue,
                                    apply_status: 'applied' 
                                });
                                successCount++;
                                currentIndex++;
                                // Small delay before next to avoid overwhelming
                                setTimeout(processNextSku, 100);
                            },
                            error: function(xhr) {
                                console.error('Save error for SKU:', sku, xhr.responseText);
                                row.update({ apply_status: 'error' });
                                errorCount++;
                                currentIndex++;
                                setTimeout(processNextSku, 100);
                            }
                        });
                    } else {
                        errorCount++;
                        currentIndex++;
                        processNextSku();
                    }
                }
                
                // Start processing
                processNextSku();
            });

            // Allow Enter key to apply discount
            $('#discount-percentage-input').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#apply-discount-btn').click();
                }
            });

            // Save push status to database
            function savePushStatusToDatabase(sku, pushStatus, rowData) {
                return $.ajax({
                    url: '/doba/save-sprice',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        sku: sku,
                        sprice: rowData.sprice || 0,
                        spft_percent: rowData.spft || 0,
                        sroi_percent: rowData.sroi || 0,
                        s_self_pick: rowData.s_self_pick || 0,
                        push_status: pushStatus,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    }
                });
            }

            // Push price to Doba API with retry functionality (5 retries, 1 minute gap)
            function pushPriceToDobaWithRetry(sku, price, selfPickPrice = null, maxRetries = 5, delay = 5000) {
                return new Promise((resolve, reject) => {
                    let attempt = 0;
                    
                    function attemptPush() {
                        attempt++;
                        console.log(`Attempt ${attempt}/${maxRetries} for SKU ${sku}`);
                        
                        const requestData = {
                            sku: sku,
                            price: price,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        };
                        
                        // Add self_pick_price if provided
                        if (selfPickPrice !== null && selfPickPrice > 0) {
                            requestData.self_pick_price = selfPickPrice;
                        }
                        
                        $.ajax({
                            url: '/doba/push-price',
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: requestData,
                            success: function(response) {
                                console.log(`Attempt ${attempt} response for SKU ${sku}:`, response);
                                
                                if (response.errors && response.errors.length > 0) {
                                    const errorMsg = response.errors[0].message || 'Unknown error';
                                    console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg);
                                    
                                    if (attempt < maxRetries) {
                                        console.log(`Retry ${attempt + 1}/${maxRetries} for SKU ${sku} in ${delay/1000} seconds...`);
                                        setTimeout(attemptPush, delay);
                                    } else {
                                        console.error(`Max retries (${maxRetries}) reached for SKU ${sku}`);
                                        reject({ error: true, response: response, message: errorMsg });
                                    }
                                } else if (response.success === false) {
                                    const errorMsg = response.errors?.[0]?.message || 'Push failed';
                                    console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg);
                                    
                                    if (attempt < maxRetries) {
                                        console.log(`Retry ${attempt + 1}/${maxRetries} for SKU ${sku} in ${delay/1000} seconds...`);
                                        setTimeout(attemptPush, delay);
                                    } else {
                                        console.error(`Max retries (${maxRetries}) reached for SKU ${sku}`);
                                        reject({ error: true, response: response, message: errorMsg });
                                    }
                                } else {
                                    console.log(`Successfully pushed price for SKU ${sku} on attempt ${attempt}`);
                                    resolve({ success: true, response: response });
                                }
                            },
                            error: function(xhr) {
                                const errorMsg = xhr.responseJSON?.errors?.[0]?.message || xhr.responseText || 'Network error';
                                console.error(`Attempt ${attempt} for SKU ${sku} failed:`, errorMsg);
                                
                                if (attempt < maxRetries) {
                                    console.log(`Retry ${attempt + 1}/${maxRetries} for SKU ${sku} in ${delay/1000} seconds...`);
                                    setTimeout(attemptPush, delay);
                                } else {
                                    console.error(`Max retries (${maxRetries}) reached for SKU ${sku}`);
                                    reject({ error: true, xhr: xhr, message: errorMsg });
                                }
                            }
                        });
                    }
                    
                    attemptPush();
                });
            }

            // Push to Doba button handler
            $('#push-to-doba-btn').on('click', function() {
                // Get all SKUs that have SPRICE set
                const skusWithSprice = [];
                
                table.getRows().forEach(row => {
                    const data = row.getData();
                    if (!data.is_parent && data.sprice && data.sprice > 0) {
                        skusWithSprice.push({
                            sku: data['(Child) sku'],
                            price: data.sprice,
                            selfPickPrice: data.s_self_pick || null, // Use calculated S (PP) (SPRICE - SHIP)
                            row: row
                        });
                    }
                });
                
                if (skusWithSprice.length === 0) {
                    showToast('warning', 'No SKUs with SPRICE found. Please set SPRICE first.');
                    return;
                }
                
                // Confirm before pushing
                if (!confirm(`Are you sure you want to push prices for ${skusWithSprice.length} SKU(s) to Doba?`)) {
                    return;
                }
                
                const $btn = $(this);
                const originalHtml = $btn.html();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Pushing...');
                
                let currentIndex = 0;
                let successCount = 0;
                let errorCount = 0;
                
                function processNextSku() {
                    if (currentIndex >= skusWithSprice.length) {
                        // All done
                        $btn.prop('disabled', false).html(originalHtml);
                        
                        if (successCount > 0 && errorCount === 0) {
                            showToast('success', `Successfully pushed prices for ${successCount} SKU(s) to Doba`);
                        } else if (successCount > 0 && errorCount > 0) {
                            showToast('warning', `Pushed ${successCount} SKU(s), ${errorCount} failed`);
                        } else {
                            showToast('danger', `Failed to push prices for ${errorCount} SKU(s)`);
                        }
                        return;
                    }
                    
                    const { sku, price, selfPickPrice, row } = skusWithSprice[currentIndex];
                    
                    // Update status to show processing
                    $btn.html(`<i class="fas fa-spinner fa-spin"></i> ${currentIndex + 1}/${skusWithSprice.length}`);
                    
                    // Update row status to pushing
                    row.update({ push_status: 'pushing' });
                    
                    // 5 retries with 5 second gap
                    pushPriceToDobaWithRetry(sku, price, selfPickPrice, 5, 5000)
                        .then((result) => {
                            successCount++;
                            console.log(`SKU ${sku}: Price pushed successfully`);
                            row.update({ push_status: 'pushed', apply_status: null });
                            
                            // Force update the cell to show double tick immediately
                            const pushCell = row.getCell('_push');
                            if (pushCell) {
                                pushCell.getElement().innerHTML = '<i class="fa-solid fa-check-double" style="color: #28a745;" title="Pushed to Doba"></i>';
                            }
                            
                            // Get fresh row data for saving
                            const freshRowData = row.getData();
                            
                            // Save pushed status to database
                            savePushStatusToDatabase(sku, 'pushed', freshRowData)
                                .done(function() {
                                    console.log(`Push status 'pushed' saved to DB for SKU ${sku}`);
                                })
                                .fail(function(err) {
                                    console.error(`Failed to save push status to DB for SKU ${sku}:`, err);
                                });
                            
                            // Process next SKU with delay to avoid rate limiting
                            currentIndex++;
                            setTimeout(processNextSku, 2000);
                        })
                        .catch((error) => {
                            errorCount++;
                            console.error(`SKU ${sku}: Failed to push price after all retries`);
                            row.update({ push_status: 'error', apply_status: null });
                            
                            // Force update the cell to show error button
                            const pushCell = row.getCell('_push');
                            if (pushCell) {
                                pushCell.getElement().innerHTML = `<button class="push-single-btn" data-sku="${sku}" data-price="${price}" style="border: none; background: none; color: #dc3545; cursor: pointer;" title="Push failed - Click to retry">
                                    <i class="fa-solid fa-x"></i>
                                </button>`;
                            }
                            
                            // Uncheck the checkbox (like Amazon)
                            selectedSkus.delete(sku);
                            
                            // Get fresh row data for saving
                            const freshRowData = row.getData();
                            
                            // Save error status to database
                            savePushStatusToDatabase(sku, 'error', freshRowData)
                                .done(function() {
                                    console.log(`Push status 'error' saved to DB for SKU ${sku}`);
                                })
                                .fail(function(err) {
                                    console.error(`Failed to save push status to DB for SKU ${sku}:`, err);
                                });
                            
                            // Process next SKU with delay
                            currentIndex++;
                            setTimeout(processNextSku, 2000);
                        });
                }
                
                // Start processing
                processNextSku();
            });

            // Single push button click handler
            $(document).on('click', '.push-single-btn', function(e) {
                e.stopPropagation();
                
                const $btn = $(this);
                const sku = $btn.data('sku');
                const price = $btn.data('price');
                
                if (!sku || !price) {
                    showToast('danger', 'Invalid SKU or price');
                    return;
                }
                
                // Immediately show spinner on button (like Amazon)
                $btn.prop('disabled', true);
                $btn.html('<i class="fas fa-spinner fa-spin" style="color: #ffc107;"></i>');
                
                // Find the row
                let row = null;
                table.getRows().forEach(r => {
                    if (r.getData()['(Child) sku'] === sku) {
                        row = r;
                    }
                });
                
                if (!row) {
                    showToast('danger', 'Row not found');
                    $btn.prop('disabled', false);
                    $btn.html('<i class="fas fa-upload"></i>');
                    return;
                }
                
                // Get S (PP) from row data (calculated as SPRICE - SHIP)
                const rowData = row.getData();
                const selfPickPrice = rowData.s_self_pick || null;
                
                // Update status to pushing (this also updates the cell formatter)
                row.update({ push_status: 'pushing' });
                
                // 5 retries with 5 second gap
                pushPriceToDobaWithRetry(sku, price, selfPickPrice, 5, 5000)
                    .then((result) => {
                        // Success - update row immediately
                        row.update({ push_status: 'pushed', apply_status: null });
                        
                        // Force update the cell to show double tick immediately
                        const pushCell = row.getCell('_push');
                        if (pushCell) {
                            pushCell.getElement().innerHTML = '<i class="fa-solid fa-check-double" style="color: #28a745;" title="Pushed to Doba"></i>';
                        }
                        
                        // Get fresh row data for saving
                        const freshRowData = row.getData();
                        
                        // Save pushed status to database so it persists after refresh
                        savePushStatusToDatabase(sku, 'pushed', freshRowData)
                            .done(function() {
                                console.log(`Push status 'pushed' saved to DB for SKU ${sku}`);
                            })
                            .fail(function(err) {
                                console.error(`Failed to save push status to DB for SKU ${sku}:`, err);
                            });
                        
                        showToast('success', `Price pushed successfully for ${sku}`);
                    })
                    .catch((error) => {
                        // Failed after all retries - update row
                        row.update({ push_status: 'error', apply_status: null });
                        
                        // Force update the cell to show error button immediately
                        const pushCell = row.getCell('_push');
                        if (pushCell) {
                            pushCell.getElement().innerHTML = `<button class="push-single-btn" data-sku="${sku}" data-price="${price}" style="border: none; background: none; color: #dc3545; cursor: pointer;" title="Push failed - Click to retry">
                                <i class="fa-solid fa-x"></i>
                            </button>`;
                        }
                        
                        // Uncheck the checkbox (like Amazon)
                        selectedSkus.delete(sku);
                        
                        // Get fresh row data for saving
                        const freshRowData = row.getData();
                        
                        // Save error status to database so it persists after refresh
                        savePushStatusToDatabase(sku, 'error', freshRowData)
                            .done(function() {
                                console.log(`Push status 'error' saved to DB for SKU ${sku}`);
                            })
                            .fail(function(err) {
                                console.error(`Failed to save push status to DB for SKU ${sku}:`, err);
                            });
                        
                        const errorMsg = error.message || 'Unknown error after 5 retries';
                        showToast('danger', `Failed to push price for ${sku}: ${errorMsg}`);
                    });
            });

            table = new Tabulator("#doba-table", {
                ajaxURL: "/doba-data-view",
                ajaxConfig: "GET",
                ajaxResponse: function(url, params, response) {
                    // Process data exactly like doba_pricing_cvr.blade.php
                    if (response && response.data) {
                        const processedData = response.data.map((item, index) => {
                            const inv = Number(item.INV) || 0;
                            const l30 = Number(item.L30) || 0;
                            const dobaL30 = Number(item['doba L30']) || 0;
                            const dobaL60 = Number(item['doba L60']) || 0;
                            const quantityL7 = Number(item.quantity_l7) || 0;
                            const quantityL7Prev = Number(item.quantity_l7_prev) || 0;
                            const ovDil = inv > 0 ? l30 / inv : 0;
                            const price = Number(item['doba Price']) || 0;
                            const ship = Number(item.Ship_productmaster) || 0;
                            const lp = Number(item.LP_productmaster) || 0;
                            // Use PFT_percentage and ROI_percentage from controller (already in percentage form)
                            const npft_pct = Number(item.PFT_percentage) || 0;
                            const roi_pct = Number(item.ROI_percentage) || 0;
                            const sold = Number(item['doba L30']) || 0;
                            const promo = sold === 0 ? price * 0.90 : 0;
                            const promo_pu = promo - ship;

                            // SPFT and SROI calculations using same formula as PFT and ROI
                            const sprice = Number(item.SPRICE) || 0;
                            const spft = sprice > 0 ? ((sprice * 0.95) - ship - lp) / sprice * 100 : 0;
                            const sprofit = sprice > 0 ? (sprice * 0.95) - ship - lp : 0;
                            const sroi = lp > 0 && sprice > 0 ? (((sprice * 0.95) - ship - lp) / lp) * 100 : 0;

                            return {
                                sl_no: index + 1,
                                'Sl': index + 1,
                                Parent: item.Parent || item.parent || item.parent_asin || item.Parent_ASIN || '(No Parent)',
                                '(Child) sku': item['(Child) sku'] || '',
                                'R&A': item['R&A'] !== undefined ? item['R&A'] : '',
                                INV: inv,
                                L30: l30,
                                ov_dil: ovDil,
                                'doba L30': dobaL30,
                                'doba L60': dobaL60,
                                quantity_l7: quantityL7,
                                quantity_l7_prev: quantityL7Prev,
                                'doba Price': price,
                                Profit: item.Total_pft || item.Profit || 0,
                                'Sales L30': dobaL30,
                                Roi: item.ROI_percentage || 0,
                                PFT_percentage: item.PFT_percentage || 0,
                                pickup_price: item['PICK UP PRICE '] || item.pickup_price || 0,
                                is_parent: item['(Child) sku'] ? item['(Child) sku'].toUpperCase().includes("PARENT") : false,
                                raw_data: item || {},
                                NR: item.NR || '',
                                NPFT_pct: npft_pct,
                                Promo: promo,
                                Promo_PU: promo_pu,
                                missing: (inv === 0 && dobaL30 === 0) ? 1 : 0, // Missing indicator
                                LP_productmaster: lp,
                                Ship_productmaster: ship,
                                sprice: item.SPRICE || 0,
                                spft: item.SPFT || spft,
                                sprofit: sprofit,
                                sroi: item.SROI || sroi,
                                s_self_pick: Number(item.S_SELF_PICK) || 0, // Saved S (PP)
                                s_l30: Number(item.s_l30) || 0,  // S L30 from doba_daily_data
                                self_pick_price: Number(item.self_pick_price) || 0,
                                msrp: Number(item.msrp) || 0,
                                map: Number(item.map) || 0,
                                push_status: item.PUSH_STATUS || null, // Saved push status from DB
                                push_status_updated_at: item.PUSH_STATUS_UPDATED_AT || null // Timestamp when push status was updated
                            };
                        });
                        return processedData;
                    }
                    return [];
                },
                pagination: "local",
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200],
                layout: "fitData",
                responsiveLayout: "hide",
                placeholder: "No Data Available",
                selectable: false,
                headerFilterPlaceholder: "",
                columns: [
                    {
                        title: "SKU",
                        field: "(Child) sku",
                        width: 150,
                        frozen: true,
                        formatter: function(cell, formatterParams) {
                            const value = cell.getValue();
                            const rawData = cell.getRow().getData().raw_data || {};
                            const imageUrl = rawData.image || '';
                            const buyerLink = rawData['B Link'] || '';
                            const sellerLink = rawData['S Link'] || '';
                            
                            if (buyerLink || sellerLink || imageUrl) {
                                return `
                                    <div class="sku-tooltip-container">
                                        <span class="sku-text">${value}</span>
                                        <div class="sku-tooltip">
                                            ${imageUrl ? `<img src="${imageUrl}" alt="SKU Image" style="max-width:120px;max-height:120px;border-radius:6px;display:block;margin:0 auto 6px auto;">` : ''}
                                            ${buyerLink ? `<div class="sku-link"><a href="${buyerLink}" target="_blank" rel="noopener noreferrer">Buyer link</a></div>` : ''}
                                            ${sellerLink ? `<div class="sku-link"><a href="${sellerLink}" target="_blank" rel="noopener noreferrer">Seller link</a></div>` : ''}
                                        </div>
                                    </div>
                                `;
                            }
                            return value;
                        }
                    },
                    
                    {
                        title: "INV",
                        field: "INV",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value.toString();
                        }
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            return parseFloat(cell.getValue()) || 0;
                        }
                    },
                    {
                        title: "OV DIL",
                        field: "ov_dil",
                        width: 80,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            const percent = value * 100;
                            let style = '';
                            if (percent < 16.66) style = 'color: #dc3545; font-weight: 800;'; // red - bold
                            else if (percent >= 16.66 && percent < 25) style = 'color: #ffc107; font-weight: bold;'; // yellow
                            else if (percent >= 25 && percent < 50) style = 'color: #28a745; font-weight: bold;'; // green
                            else style = 'color: #e83e8c; font-weight: 800;'; // pink - bold
                            
                            return `<span style="${style}">${Math.round(percent)}%</span>`;
                        }
                    },
                    {
                        title: "SOLD",
                        field: "doba L30",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            return parseFloat(cell.getValue()) || 0;
                        }
                    },
                    {
                        title: "S L30",
                        field: "s_l30",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseInt(cell.getValue()) || 0;
                            if (value > 0) {
                                return `<span style="color: #28a745; font-weight: bold;">${value}</span>`;
                            }
                            return value;
                        }
                    },
                    {
                        title: "SOLD L60",
                        field: "doba L60",
                        width: 70,
                        sorter: "number",
                        visible: false,
                        formatter: function(cell, formatterParams) {
                            return cell.getValue() || 0;
                        }
                    },
                    {
                        title: "SOLD 7",
                        field: "quantity_l7",
                        width: 70,
                        sorter: "number",
                        visible: false,
                        formatter: function(cell, formatterParams) {
                            const value = parseInt(cell.getValue()) || 0;
                            if (value > 0) {
                                return `<span style="color: #28a745; font-weight: bold;">${value}</span>`;
                            }
                            return value;
                        }
                    },
                    {
                        title: "P SOLD 7",
                        field: "quantity_l7_prev",
                        width: 70,
                        sorter: "number",
                        visible: false,
                        formatter: function(cell, formatterParams) {
                            const value = parseInt(cell.getValue()) || 0;
                            if (value > 0) {
                                return `<span style="color: #3591dc; font-weight: bold;">${value}</span>`;
                            }
                            return value;
                        }
                    },
                    {
                        title: "PROMO",
                        field: "Promo",
                        width: 80,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return `<span style="color: #000; font-weight: bold;">$${value.toFixed(2)}</span>`;
                        }
                    },
                    {
                        title: "Promo PU",
                        field: "Promo_PU",
                        width: 90,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return `<span style="color: #000; font-weight: bold;">$${value.toFixed(2)}</span>`;
                        }
                    },
                    {
                        title: "Missing",
                        field: "missing",
                        width: 80,
                        hozAlign: "center",
                        formatter: function(cell, formatterParams) {
                            const value = parseInt(cell.getValue()) || 0;
                            if (value === 1) {
                                return '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i></span>';
                            }
                            return '';
                        }
                    },
                    {
                        title: "PRICE",
                        field: "doba Price",
                        width: 80,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return `$${value.toFixed(2)}`;
                        }
                    },
                    {
                        title: "SHIP",
                        field: "Ship_productmaster",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value > 0 ? `$${value.toFixed(2)}` : '';
                        }
                    },
                    {
                        title: "Self Pick",
                        field: "self_pick_price",
                        width: 85,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value > 0 ? `$${value.toFixed(2)}` : '';
                        }
                    },
                    {
                        title: "NPFT%",
                        field: "NPFT_pct",
                        width: 80,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            // Value is already a percentage from controller (PFT_percentage)
                            let style = '';
                            // getPftColor logic from pricing CVR
                            if (value < 10) style = 'color: #dc3545; font-weight: 800;'; // red - extra bold
                            else if (value >= 10 && value < 15) style = 'color: #ffc107; font-weight: bold;'; // yellow
                            else if (value >= 15 && value < 20) style = 'color: #3591dc; font-weight: bold;'; // blue
                            else if (value >= 20 && value <= 40) style = 'color: #28a745; font-weight: bold;'; // green
                            else style = 'color: #e83e8c; font-weight: 800;'; // pink - extra bold
                            
                            return `<span style="${style}">${Math.round(value)}%</span>`;
                        }
                    },
                    {
                        title: "ROI",
                        field: "Roi",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            // Value is already a percentage from controller (ROI_percentage)
                            let style = '';
                            
                            // getRoiColor logic from pricing CVR
                            if (value < 50) style = 'color: #dc3545; font-weight: 800;'; // red - extra bold
                            else if (value >= 50 && value < 75) style = 'color: #ffc107; font-weight: bold;'; // yellow
                            else if (value >= 75 && value <= 125) style = 'color: #28a745; font-weight: bold;'; // green
                            else style = 'color: #e83e8c; font-weight: 800;'; // pink - extra bold
                            
                            return `<span style="${style}">${Math.round(value)}%</span>`;
                        }
                    },
                    {
                        field: "_select",
                        hozAlign: "center",
                        headerSort: false,
                        visible: false,
                        width: 50,
                        titleFormatter: function(column) {
                            return `<div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                <input type="checkbox" id="select-all-checkbox" style="cursor: pointer;" title="Select All Filtered SKUs">
                            </div>`;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = rowData.is_parent;
                            
                            if (isParent) return '';
                            
                            const sku = rowData['(Child) sku'];
                            const isSelected = selectedSkus.has(sku);
                            
                            // Just show checkbox - status icons are in _push column (like Amazon)
                            return `<input type="checkbox" class="sku-select-checkbox" data-sku="${sku}" ${isSelected ? 'checked' : ''} style="cursor: pointer;">`;
                        }
                    },
                    {
                        title: "SPRICE",
                        field: "sprice",
                        width: 80,
                        sorter: "number",
                        editor: "number",
                        editorParams: {
                            min: 0,
                            step: 0.01
                        },
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value > 0 ? `<span class="badge bg-primary">$${value.toFixed(2)}</span>` : '';
                        }
                    },
                    {
                        title: "S (PP)",
                        field: "s_self_pick",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value > 0 ? `<span class="badge bg-secondary">$${value.toFixed(2)}</span>` : '';
                        }
                    },
                    {
                        title: "SPFT",
                        field: "spft",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            if (value === 0) return '';
                            let style = '';
                            // Match NPFT% coloring
                            if (value < 10) style = 'color: #dc3545; font-weight: 800;'; // red - extra bold
                            else if (value >= 10 && value < 15) style = 'color: #ffc107; font-weight: bold;'; // yellow
                            else if (value >= 15 && value < 20) style = 'color: #3591dc; font-weight: bold;'; // blue
                            else if (value >= 20 && value <= 40) style = 'color: #28a745; font-weight: bold;'; // green
                            else style = 'color: #e83e8c; font-weight: 800;'; // pink - extra bold
                            return `<span style="${style}">${Math.round(value)}%</span>`;
                        }
                    },
                    {
                        title: "SROI",
                        field: "sroi",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            if (value === 0) return '';
                            let style = '';
                            // Match ROI coloring
                            if (value < 50) style = 'color: #dc3545; font-weight: 800;'; // red - extra bold
                            else if (value >= 50 && value < 75) style = 'color: #ffc107; font-weight: bold;'; // yellow
                            else if (value >= 75 && value <= 125) style = 'color: #28a745; font-weight: bold;'; // green
                            else style = 'color: #e83e8c; font-weight: 800;'; // pink - extra bold
                            return `<span style="${style}">${Math.round(value)}%</span>`;
                        }
                    },
                    {
                        title: "",
                        field: "_push",
                        width: 50,
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const isParent = rowData.is_parent;
                            const sprice = parseFloat(rowData.sprice) || 0;
                            const pushStatus = rowData.push_status || null;
                            const applyStatus = rowData.apply_status || null;
                            
                            if (isParent || sprice <= 0) return '';
                            
                            const sku = rowData['(Child) sku'];
                            
                            // Show spinner while applying discount
                            if (applyStatus === 'applying') {
                                return '<i class="fas fa-clock fa-spin" style="color: #ffc107;" title="Applying discount..."></i>';
                            }
                            
                            // Show spinner while pushing
                            if (pushStatus === 'pushing') {
                                return '<i class="fas fa-spinner fa-spin" style="color: #ffc107;" title="Pushing..."></i>';
                            }
                            
                            // Show double tick only if pushed successfully
                            if (pushStatus === 'pushed') {
                                return '<i class="fa-solid fa-check-double" style="color: #28a745;" title="Pushed to Doba"></i>';
                            }
                            
                            // Show error with retry button
                            if (pushStatus === 'error') {
                                return `<button class="push-single-btn" data-sku="${sku}" data-price="${sprice}" style="border: none; background: none; color: #dc3545; cursor: pointer;" title="Push failed - Click to retry">
                                    <i class="fa-solid fa-x"></i>
                                </button>`;
                            }
                            
                            // Default: show push button (for new SPRICE, after discount applied, or null status)
                            return `<button class="push-single-btn" data-sku="${sku}" data-price="${sprice}" style="border: none; background: none; color: #0d6efd; cursor: pointer;" title="Push to Doba">
                                <i class="fas fa-upload"></i>
                            </button>`;
                        }
                    },
                    {
                        title: "MSRP",
                        field: "msrp",
                        width: 75,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value > 0 ? `$${value.toFixed(2)}` : '';
                        }
                    },
                    {
                        title: "MAP",
                        field: "map",
                        width: 70,
                        sorter: "number",
                        formatter: function(cell, formatterParams) {
                            const value = parseFloat(cell.getValue()) || 0;
                            return value > 0 ? `$${value.toFixed(2)}` : '';
                        }
                    }
                ],
                rowFormatter: function(row) {
                    const data = row.getData();
                    if (data.is_parent) {
                        row.getElement().classList.add("parent-row");
                    }
                    if (data.NR === 'NRA') {
                        row.getElement().classList.add('nr-hide');
                    }
                }
            });

            // Apply filters
            function applyFilters() {
                const inventoryFilter = $('#inventory-filter').val();
                const parentFilter = $('#parent-filter').val();
                const missingFilter = $('#missing-filter').val();
                
                table.clearFilter(true);
                
                if (inventoryFilter === 'positive') {
                    table.setFilter("INV", ">", 0);
                } else if (inventoryFilter === 'zero') {
                    table.setFilter("INV", "=", 0);
                }
                
                if (parentFilter === 'parent') {
                    table.setFilter("is_parent", "=", true);
                } else if (parentFilter === 'child') {
                    table.setFilter("is_parent", "!=", true);
                }

                if (missingFilter === 'missing') {
                    table.setFilter("missing", "=", 1);
                }
                
                // Update select all checkbox after filter is applied
                setTimeout(function() {
                    updateSelectAllCheckbox();
                }, 100);
            }

            $('#inventory-filter, #parent-filter, #missing-filter').on('change', function() {
                applyFilters();
            });

            // Fetch and display summary metrics from marketplace_daily_metrics table
            function fetchDobaSummaryMetrics() {
                fetch('/doba/summary-metrics')
                    .then(response => response.json())
                    .then(result => {
                        if (result.success && result.data) {
                            const data = result.data;
                            $('#total-sales-badge').text('Total Sales: $' + Math.round(parseFloat(data.total_sales)).toLocaleString());
                            $('#pft-percentage-badge').text('GPFT %: ' + parseFloat(data.pft_percentage).toFixed(1) + '%');
                            $('#roi-percentage-badge').text('ROI %: ' + parseFloat(data.roi_percentage).toFixed(1) + '%');
                            $('#pft-total-badge').text('GPFT Total: $' + Math.round(parseFloat(data.total_pft)).toLocaleString());
                            $('#total-cogs-badge').text('Total COGS: $' + Math.round(parseFloat(data.total_cogs)).toLocaleString());
                            $('#metrics-date-badge').text('Date: ' + data.date);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching Doba metrics:', error);
                    });
            }

            // Update summary badges for SKU counts
            function updateSummary() {
                const tableData = table.getData("active");
                const filteredData = tableData.filter(row => !row.is_parent);
                
                // For missing count, use all data (not filtered by INV)
                const allData = table.getData();
                const allNonParentData = allData.filter(row => !row.is_parent);
                
                const totalSkus = filteredData.length;
                let zeroSold = 0;
                let sold = 0;
                let missing = 0;

                filteredData.forEach(row => {
                    const inv = parseFloat(row.INV) || 0;
                    const dobaL30 = parseFloat(row['doba L30']) || 0;
                    
                    // 0 SOLD: doba L30 == 0, INV > 0
                    if (dobaL30 === 0 && inv > 0) zeroSold++;
                    // SOLD: count all SKUs with any doba L30
                    if (dobaL30 > 0) sold++;
                });

                // Calculate missing from all data (not filtered)
                allNonParentData.forEach(row => {
                    const inv = parseFloat(row.INV) || 0;
                    const dobaL30 = parseFloat(row['doba L30']) || 0;
                    // Missing: INV == 0 AND doba L30 == 0
                    if (inv === 0 && dobaL30 === 0) missing++;
                });

                $('#total-skus').text('Total SKUs: ' + totalSkus);
                $('#zero-sold-count').text('0 SOLD: ' + zeroSold);
                $('#sold-count').text('SOLD: ' + sold);
                $('#missing-count').html('<i class="fas fa-exclamation-triangle"></i> Missing: ' + missing);
            }

            // Build Column Visibility Dropdown
            function buildColumnDropdown() {
                const columns = table.getColumns();
                const menu = document.getElementById("column-dropdown-menu");
                
                // Clear existing column items
                const existingItems = menu.querySelectorAll('.column-toggle-item');
                existingItems.forEach(item => item.remove());
                
                columns.forEach(column => {
                    if (column.getField() === 'sl_no') return; // Skip sl_no column
                    
                    const item = document.createElement('label');
                    item.className = 'dropdown-item column-toggle-item d-flex align-items-center';
                    item.innerHTML = `
                        <input type="checkbox" class="form-check-input me-2" 
                               data-column="${column.getField()}" 
                               ${column.isVisible() ? 'checked' : ''}>
                        ${column.getDefinition().title}
                    `;
                    menu.appendChild(item);
                });
            }

            // Handle SPRICE cell edit
            table.on('cellEdited', function(cell) {
                const field = cell.getColumn().getField();
                
                if (field === 'sprice') {
                    const rowData = cell.getRow().getData();
                    const sprice = parseFloat(cell.getValue()) || 0;
                    const lp = parseFloat(rowData.LP_productmaster) || 0;
                    const ship = parseFloat(rowData.Ship_productmaster) || 0;
                    const sku = rowData['(Child) sku'];
                    
                    if (sprice > 0 && lp > 0) {
                        // Calculate SPFT% = ((sprice * 0.95) - ship - lp) / sprice * 100
                        const spft = ((sprice * 0.95) - ship - lp) / sprice * 100;
                        
                        // Calculate SROI% = ((sprice * 0.95) - ship - lp) / lp * 100
                        const sroi = ((sprice * 0.95) - ship - lp) / lp * 100;
                        
                        // Calculate S(PP) = SPRICE - SHIP
                        const sSelfPick = sprice - ship;
                        
                        // Update row data with all calculated values
                        // Reset push_status to null so push button shows again (like Amazon)
                        cell.getRow().update({
                            spft: spft,
                            sroi: sroi,
                            s_self_pick: sSelfPick,
                            push_status: null,
                            apply_status: null
                        });
                        
                        // Force refresh the _push cell to show upload button immediately
                        const pushCell = cell.getRow().getCell('_push');
                        if (pushCell) {
                            pushCell.getElement().innerHTML = `<button class="push-single-btn" data-sku="${sku}" data-price="${sprice}" style="border: none; background: none; color: #0d6efd; cursor: pointer;" title="Push to Doba">
                                <i class="fas fa-upload"></i>
                            </button>`;
                        }
                        
                        // Save to backend with S(PP) and reset push_status
                        $.ajax({
                            url: '/doba/save-sprice',
                            method: 'POST',
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                sku: sku,
                                sprice: sprice,
                                spft_percent: spft,
                                sroi_percent: sroi,
                                s_self_pick: sSelfPick,
                                push_status: null
                            },
                            success: function(response) {
                                showToast('success', 'SPRICE updated successfully');
                            },
                            error: function(xhr) {
                                showToast('danger', 'Failed to update SPRICE');
                                console.error(xhr);
                            }
                        });
                    }
                }
            });

            // Wait for table to be built
            table.on('tableBuilt', function() {
                buildColumnDropdown();
                updateSummary();
                applyFilters(); // Apply default INV > 0 filter
            });

            table.on('dataLoaded', function() {
                setTimeout(() => {
                    updateSummary();
                    fetchDobaSummaryMetrics(); // Fetch financial metrics from marketplace_daily_metrics
                    // Refresh checkboxes to reflect selectedSkus set
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    updatePushButtonVisibility();
                }, 100);
            });

            table.on('renderComplete', function() {
                setTimeout(() => {
                    updateSummary();
                    // Refresh checkboxes to reflect selectedSkus set
                    $('.sku-select-checkbox').each(function() {
                        const sku = $(this).data('sku');
                        $(this).prop('checked', selectedSkus.has(sku));
                    });
                    updateSelectAllCheckbox();
                    updatePushButtonVisibility();
                }, 100);
            });

            // Update Push to Doba button visibility based on SELECTED SKUs with SPRICE (like Amazon)
            function updatePushButtonVisibility() {
                // Only count selected SKUs that have SPRICE > 0
                let spriceCount = 0;
                selectedSkus.forEach(sku => {
                    const row = table.getRows().find(r => r.getData()['(Child) sku'] === sku);
                    if (row) {
                        const data = row.getData();
                        if (!data.is_parent && data.sprice && data.sprice > 0) {
                            spriceCount++;
                        }
                    }
                });
                
                if (spriceCount > 0) {
                    $('#push-to-doba-btn').show().html(`<i class="fas fa-upload"></i> Push to Doba (${spriceCount})`);
                } else {
                    $('#push-to-doba-btn').hide();
                }
            }

            // Toggle column from dropdown
            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.type === 'checkbox') {
                    const columnField = e.target.getAttribute('data-column');
                    const column = table.getColumn(columnField);
                    
                    if (e.target.checked) {
                        column.show();
                    } else {
                        column.hide();
                    }
                }
            });

            // Show All Columns button
            document.getElementById("show-all-columns-btn").addEventListener("click", function() {
                table.getColumns().forEach(column => {
                    column.show();
                });
                buildColumnDropdown();
            });

            // Export CSV
            $('#export-csv-btn').on('click', function() {
                table.download("csv", "doba_pricing_cvr_export.csv");
            });

            // Reload Data
            $('#reload-data-btn').on('click', function() {
                table.replaceData("/doba-data-view");
            });

            // Toast notification
            function showToast(type, message) {
                const toast = $(`
                    <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">${message}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `);
                $('.toast-container').append(toast);
                const bsToast = new bootstrap.Toast(toast[0]);
                bsToast.show();
                setTimeout(() => toast.remove(), 5000);
            }

            // Missing filter toggle on badge click
            let missingFilterActive = false;
            $('#missing-count').on('click', function() {
                if (missingFilterActive) {
                    // Remove filter
                    table.clearFilter();
                    $('#missing-filter').val('');
                    $(this).css({
                        'background-color': '#ffc107',
                        'color': '#000'
                    });
                    showToast('info', 'Showing all items');
                } else {
                    // Apply missing filter
                    table.setFilter("missing", "=", 1);
                    $('#missing-filter').val('missing');
                    $(this).css({
                        'background-color': '#dc3545',
                        'color': '#ffffff'
                    });
                    const filteredCount = table.getData("active").length;
                    showToast('warning', `Filtered to ${filteredCount} missing items`);
                }
                missingFilterActive = !missingFilterActive;
            });
        });
    </script>
@endsection
