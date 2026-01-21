@extends('layouts.vertical', ['title' => 'Stock Balance Tabulator', 'sidenav' => 'condensed'])

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
        
        /* DIL% colors */
        .dil-red { color: #a00211; font-weight: 600; }
        .dil-yellow { color: #ffc107; font-weight: 600; }
        .dil-green { color: #28a745; font-weight: 600; }
        .dil-pink { color: #e83e8c; font-weight: 600; }
        
        /* Custom success toast styling */
        .toast-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast-success-big {
            min-width: 400px;
            background-color: #1a5928 !important;
            border: 2px solid #0d3d1a !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
        }
        
        .toast-success-big .toast-body {
            font-size: 18px !important;
            font-weight: 700 !important;
            color: white !important;
            padding: 20px 25px !important;
            letter-spacing: 0.5px;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Stock Balance Transfer',
        'sub_title' => 'Multi-SKU Transfer System',
    ])
    
    <div class="toast-container"></div>
    
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>Stock Balance Transfer</h4>
                
                <!-- Filters -->
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <select id="parent-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="">All Parents</option>
                    </select>
                    
                    <select id="dil-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="">All DIL%</option>
                        <option value="red">Red (&lt;16.7%)</option>
                        <option value="yellow">Yellow (16.7-25%)</option>
                        <option value="green">Green (25-50%)</option>
                        <option value="pink">Pink (50%+)</option>
                    </select>
                    
                    <select id="action-filter" class="form-select form-select-sm" style="width: auto;">
                        <option value="">Show All</option>
                        <option value="RB" selected>RB</option>
                        <option value="NRB">NRB</option>
                        <option value="--">-- (No Action)</option>
                    </select>
                    
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All Columns
                    </button>
                    
                    <button id="export-btn" class="btn btn-sm btn-info">
                        <i class="fas fa-file-excel"></i> Export CSV
                    </button>
                    
                    <button id="transfer-mode-btn" class="btn btn-sm btn-primary">
                        <i class="fas fa-exchange-alt"></i> Transfer Mode
                    </button>
                    
                    <button id="toggle-history-btn" class="btn btn-sm btn-secondary">
                        <i class="fas fa-history"></i> Show History
                    </button>
                </div>
            </div>
            
            <!-- History Table Container (Hidden by default) -->
            <div class="card-body" id="history-table-container" style="display: none; padding: 0;">
                <div class="p-3 bg-light border-bottom">
                    <h5><i class="fas fa-history"></i> Transfer History</h5>
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-bordered table-hover mb-0" id="history-table">
                        <thead class="table-dark">
                            <tr>
                                <th>From Parent</th>
                                <th>From SKU</th>
                                <th>From DIL %</th>
                                <th>From Available</th>
                                <th>From Adjust Qty</th>
                                <th>To Parent</th>
                                <th>To SKU</th>
                                <th>To DIL %</th>
                                <th>To Available</th>
                                <th>To Adjust Qty</th>
                                <th>Transferred By</th>
                                <th>Transferred At</th>
                            </tr>
                        </thead>
                        <tbody id="history-table-body">
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card-body" style="padding: 0;">
                <!-- Bulk Actions Panel (shown when SKUs are selected) -->
                <div id="bulk-actions-panel" class="p-2 bg-warning border-bottom" style="display: none;">
                    <div class="d-flex align-items-center gap-2">
                        <span id="selected-count" class="fw-bold">0 SKUs selected</span>
                        <button id="bulk-action-blank" class="btn btn-sm btn-secondary">
                            Set to --
                        </button>
                        <button id="bulk-action-rb" class="btn btn-sm btn-success">
                            <i class="fas fa-circle"></i> Set to RB
                        </button>
                        <button id="bulk-action-nrb" class="btn btn-sm btn-danger">
                            <i class="fas fa-circle"></i> Set to NRB
                        </button>
                        <button id="clear-selection" class="btn btn-sm btn-light">
                            <i class="fas fa-times"></i> Clear Selection
                        </button>
                    </div>
                </div>
                
                <div id="stock-balance-table-wrapper" style="height: calc(100vh - 250px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control" placeholder="Search SKU...">
                    </div>
                    <!-- Table -->
                    <div id="stock-balance-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    let table = null;
    let transferModeActive = false;
    let selectedSkus = new Set();
    let allTableData = [];
    
    // Toast notification
    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        let toastClass = 'toast align-items-center text-white border-0';
        
        if (type === 'success') {
            toastClass += ' toast-success-big';
        } else if (type === 'error') {
            toastClass += ' bg-danger';
        } else {
            toastClass += ' bg-info';
        }
        
        toast.className = toastClass;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + message + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', function() { toast.remove(); });
    }
    
    $(document).ready(function() {
        // Select all checkbox handler (works with filtered data)
        $(document).on('change', '#select-all-checkbox', function() {
            const isChecked = $(this).prop('checked');
            const filteredData = table.getData('active'); // Get only filtered/visible data
            
            if (isChecked) {
                // Add all filtered SKUs to selection
                filteredData.forEach(function(row) {
                    selectedSkus.add(row.SKU);
                });
            } else {
                // Remove all filtered SKUs from selection
                filteredData.forEach(function(row) {
                    selectedSkus.delete(row.SKU);
                });
            }
            
            // Update checkboxes in visible rows
            table.redraw(true);
            updateBulkActionsPanel();
        });
        
        // Individual checkbox handler
        $(document).on('change', '.sku-checkbox', function() {
            const sku = $(this).data('sku');
            if ($(this).prop('checked')) {
                selectedSkus.add(sku);
            } else {
                selectedSkus.delete(sku);
                // Uncheck select-all if any item is unchecked
                $('#select-all-checkbox').prop('checked', false);
            }
            updateBulkActionsPanel();
        });
        
        // Update bulk actions panel visibility
        function updateBulkActionsPanel() {
            const count = selectedSkus.size;
            $('#selected-count').text(count + ' SKU' + (count !== 1 ? 's' : '') + ' selected');
            
            if (count > 0) {
                $('#bulk-actions-panel').slideDown(200);
            } else {
                $('#bulk-actions-panel').slideUp(200);
            }
        }
        
        // Clear selection button
        $('#clear-selection').on('click', function() {
            selectedSkus.clear();
            $('#select-all-checkbox').prop('checked', false);
            table.redraw(true);
            updateBulkActionsPanel();
        });
        
        // Bulk action buttons
        $('#bulk-action-blank').on('click', function() {
            bulkUpdateAction('');
        });
        
        $('#bulk-action-rb').on('click', function() {
            bulkUpdateAction('RB');
        });
        
        $('#bulk-action-nrb').on('click', function() {
            bulkUpdateAction('NRB');
        });
        
        // Bulk update ACTION for selected SKUs
        function bulkUpdateAction(actionValue) {
            if (selectedSkus.size === 0) {
                showToast('No SKUs selected', 'error');
                return;
            }
            
            const skuArray = Array.from(selectedSkus);
            const actionText = actionValue === '' ? '--' : actionValue;
            
            if (!confirm('Update ACTION to "' + actionText + '" for ' + skuArray.length + ' selected SKU(s)?')) {
                return;
            }
            
            // Update all selected SKUs
            let updated = 0;
            let errors = 0;
            
            skuArray.forEach(function(sku) {
                $.ajax({
                    url: '/stock-balance-update-action',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({
                        sku: sku,
                        action: actionValue || null
                    }),
                    success: function(response) {
                        updated++;
                        // Update in table
                        const rows = table.searchRows("SKU", "=", sku);
                        if (rows.length > 0) {
                            rows[0].update({ACTION: actionValue});
                        }
                        // Update in allTableData
                        const item = allTableData.find(function(i) { return i.SKU === sku; });
                        if (item) {
                            item.ACTION = actionValue;
                        }
                        
                        // Show success after all updates
                        if (updated === skuArray.length) {
                            showToast('Updated ' + updated + ' SKU(s) to ' + actionText, 'success');
                            table.redraw(true);
                        }
                    },
                    error: function(xhr) {
                        errors++;
                        if (updated + errors === skuArray.length) {
                            showToast('Updated ' + updated + ' SKU(s), ' + errors + ' failed', 'warning');
                        }
                    }
                });
            });
        }
        
        // Copy SKU button handler
        $(document).on('click', '.copy-sku-icon', function(e) {
            e.stopPropagation();
            const sku = $(this).data('sku');
            const $icon = $(this);
            
            navigator.clipboard.writeText(sku).then(function() {
                // Success - change icon temporarily
                const originalClass = $icon.attr('class');
                $icon.removeClass('fa-copy').addClass('fa-check text-success');
                
                setTimeout(function() {
                    $icon.attr('class', originalClass);
                }, 2000);
                
                showToast('Copied: ' + sku, 'success');
            }).catch(function(err) {
                showToast('Failed to copy SKU', 'error');
                console.error('Copy failed:', err);
            });
        });
        
        // History toggle functionality
        $('#toggle-history-btn').on('click', function() {
            const $container = $('#history-table-container');
            const isVisible = $container.is(':visible');
            
            if (isVisible) {
                $container.slideUp(300);
                $(this).html('<i class="fas fa-history"></i> Show History');
            } else {
                $container.slideDown(300);
                $(this).html('<i class="fas fa-history"></i> Hide History');
                // Load history data if not already loaded
                loadHistoryData();
            }
        });
        
        // Load history data
        function loadHistoryData() {
            $.ajax({
                url: '/stock-balance-data-list',
                method: 'GET',
                success: function(response) {
                    if (response.data && response.data.length > 0) {
                        renderHistoryTable(response.data);
                    } else {
                        $('#history-table-body').html('<tr><td colspan="12" class="text-center">No transfer history found</td></tr>');
                    }
                },
                error: function(xhr) {
                    $('#history-table-body').html('<tr><td colspan="12" class="text-center text-danger">Error loading history</td></tr>');
                    console.error('Error loading history:', xhr);
                }
            });
        }
        
        // Render history table
        function renderHistoryTable(data) {
            let html = '';
            data.forEach(function(item) {
                html += '<tr>' +
                    '<td>' + (item.from_parent_name || '-') + '</td>' +
                    '<td><strong>' + (item.from_sku || '-') + '</strong></td>' +
                    '<td>' + (item.from_dil_percent != null ? item.from_dil_percent + '%' : '-') + '</td>' +
                    '<td>' + (item.from_available_qty || '-') + '</td>' +
                    '<td class="text-danger"><strong>' + (item.from_adjust_qty || '-') + '</strong></td>' +
                    '<td>' + (item.to_parent_name || '-') + '</td>' +
                    '<td><strong>' + (item.to_sku || '-') + '</strong></td>' +
                    '<td>' + (item.to_dil_percent != null ? item.to_dil_percent + '%' : '-') + '</td>' +
                    '<td>' + (item.to_available_qty || '-') + '</td>' +
                    '<td class="text-success"><strong>' + (item.to_adjust_qty || '-') + '</strong></td>' +
                    '<td>' + (item.transferred_by || '-') + '</td>' +
                    '<td>' + (item.transferred_at || '-') + '</td>' +
                    '</tr>';
            });
            $('#history-table-body').html(html);
        }
        
        // Transfer Mode Toggle (like BestBuy Decrease/Increase mode)
        $('#transfer-mode-btn').on('click', function() {
            transferModeActive = !transferModeActive;
            
            if (transferModeActive) {
                $(this).removeClass('btn-primary').addClass('btn-danger').html('<i class="fas fa-exchange-alt"></i> Transfer ON');
                // Show transfer columns
                // table.getColumn('from_sku_display').show(); // TO SKU - Hidden per request
                table.getColumn('from_qty').show();
                table.getColumn('to_sku').show();
                // table.getColumn('to_parent').show(); // FROM Parent - Hidden per request
                // table.getColumn('from_inv_display').show(); // FROM INV - Hidden per request
                table.getColumn('from_sold_display').show();
                table.getColumn('from_dil_display').show();
                table.getColumn('to_qty_calc').show();
                table.getColumn('ratio').show();
                table.getColumn('submit').show();
                // Reduce ACTION column width to save space
                table.getColumn('ACTION').updateDefinition({width: 100});
                
                // Initialize Select2 immediately when Transfer Mode is activated
                setTimeout(function() {
                    initializeSelect2FromSku();
                }, 100);
            } else {
                $(this).removeClass('btn-danger').addClass('btn-primary').html('<i class="fas fa-exchange-alt"></i> Transfer Mode');
                // Hide transfer columns
                // table.getColumn('from_sku_display').hide(); // Already hidden
                table.getColumn('from_qty').hide();
                table.getColumn('to_sku').hide();
                // table.getColumn('to_parent').hide(); // Already hidden
                // table.getColumn('from_inv_display').hide(); // Already hidden
                table.getColumn('from_sold_display').hide();
                table.getColumn('from_dil_display').hide();
                table.getColumn('to_qty_calc').hide();
                table.getColumn('ratio').hide();
                table.getColumn('submit').hide();
                // Restore ACTION column width
                table.getColumn('ACTION').updateDefinition({width: 60});
            }
        });
        
        // ACTION dropdown change handler (save to database)
        $(document).on('change', '.action-select', function() {
            const $select = $(this);
            const sku = $select.data('sku');
            const action = $select.val();
            
            // Update styling based on selection
            if (action === 'RB') {
                $select.css({'background-color': '#28a745', 'color': 'white'});
            } else if (action === 'NRB') {
                $select.css({'background-color': '#dc3545', 'color': 'white'});
            } else {
                $select.css({'background-color': '#fff', 'color': '#212529'});
            }
            
            // Save to database
            $.ajax({
                url: '/stock-balance-update-action',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    sku: sku,
                    action: action || null
                }),
                success: function(response) {
                    showToast('ACTION updated for ' + sku, 'success');
                    // Update the row data in table
                    const rows = table.searchRows("SKU", "=", sku);
                    if (rows.length > 0) {
                        rows[0].update({ACTION: action});
                    }
                },
                error: function(xhr) {
                    showToast('Failed to update ACTION for ' + sku, 'error');
                    // Revert the dropdown
                    const oldValue = allTableData.find(function(i) { return i.SKU === sku; })?.ACTION || '';
                    $select.val(oldValue);
                }
            });
        });
        
        // FROM SKU dropdown change handler (inline in table)
        $(document).on('change', '.to-sku-select', function() {
            const $select = $(this);
            const $row = $select.closest('.tabulator-row');
            const row = table.getRow($row[0]);
            const rowData = row.getData();
            const toSku = rowData.SKU; // Current row's SKU (TO SKU)
            const fromSku = $select.val();
            
            if (fromSku) {
                // Save FROM SKU specifically for this TO SKU
                const savedData = JSON.parse(localStorage.getItem('transfer_' + toSku) || '{}');
                savedData.fromSku = fromSku;
                localStorage.setItem('transfer_' + toSku, JSON.stringify(savedData));
                
                const selectedOption = $select.find('option:selected');
                const fromParent = selectedOption.attr('data-parent') || '';
                const fromInv = selectedOption.attr('data-inv') || 0;
                
                // Get FROM SKU data for SOLD, DIL%, and set FROM Qty
                const fromItem = allTableData.find(function(i) { return i.SKU === fromSku; });
                const fromSold = fromItem ? (fromItem.SOLD || 0) : 0;
                const fromDil = fromItem ? (parseFloat(fromItem.DIL) || 0) : 0;
                const fromDilPercent = Math.round(fromDil * 100);
                
                // Auto-fill FROM fields
                $row.find('.to-parent-display').val(fromParent);
                $row.find('.from-inv-display').val(fromInv);
                $row.find('.from-sold-display').val(fromSold);
                $row.find('.from-qty-input').val(fromInv); // Set FROM Qty to FROM SKU's INV
                
                // Set FROM DIL% with color coding
                const $dilSpan = $row.find('.from-dil-percent');
                let dilClass = '';
                if (fromDilPercent < 16.66) dilClass = 'dil-red';
                else if (fromDilPercent >= 16.66 && fromDilPercent < 25) dilClass = 'dil-yellow';
                else if (fromDilPercent >= 25 && fromDilPercent < 50) dilClass = 'dil-green';
                else dilClass = 'dil-pink';
                
                $dilSpan.attr('class', 'from-dil-percent ' + dilClass).text(fromDilPercent + '%');
            } else {
                // Clear fields if no FROM SKU selected
                $row.find('.to-parent-display').val('');
                $row.find('.from-inv-display').val('');
                $row.find('.from-sold-display').val('');
                $row.find('.from-qty-input').val('');
                $row.find('.from-dil-percent').attr('class', 'from-dil-percent').text('-');
            }
        });
        
        // Ratio change handler (inline in table)
        $(document).on('change', '.ratio-select', function() {
            const $select = $(this);
            const $row = $select.closest('.tabulator-row');
            const row = table.getRow($row[0]);
            const rowData = row.getData();
            const toSku = rowData.SKU; // Current row's SKU (TO SKU)
            const ratio = $select.val();
            
            // Save Ratio specifically for this TO SKU
            const savedData = JSON.parse(localStorage.getItem('transfer_' + toSku) || '{}');
            savedData.ratio = ratio;
            localStorage.setItem('transfer_' + toSku, JSON.stringify(savedData));
            
            // Recalculate TO Qty
            calculateToQty($row);
        });
        
        // FROM Qty input change handler
        $(document).on('input', '.from-qty-input', function() {
            const $row = $(this).closest('.tabulator-row');
            calculateToQty($row);
        });
        
        // Calculate TO Qty based on FROM Qty × Ratio
        function calculateToQty($row) {
            const fromQty = parseInt($row.find('.from-qty-input').val()) || 0;
            const ratio = $row.find('.ratio-select').val() || '1:1';
            
            if (fromQty > 0) {
                const ratioParts = ratio.split(':');
                const toQty = Math.round(fromQty * (parseFloat(ratioParts[1]) / parseFloat(ratioParts[0])));
                $row.find('.to-qty-display').val(toQty);
            } else {
                $row.find('.to-qty-display').val('');
            }
        }
        
        // Submit transfer button handler (inline in table)
        $(document).on('click', '.submit-transfer-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $btn = $(this);
            const $row = $btn.closest('.tabulator-row');
            const row = table.getRow($row[0]);
            const rowData = row.getData();
            
            // Table row SKU is the TO SKU (destination)
            const toSku = rowData.SKU;
            const toParent = rowData.Parent || '';
            const toInv = parseInt(rowData.INV) || 0;
            const toDil = parseFloat(rowData.DIL) || 0;
            const toQty = parseInt($row.find('.to-qty-display').val()) || 0;
            
            // User selects FROM SKU manually (from dropdown)
            const fromSku = $row.find('.to-sku-select').val();
            const fromParent = $row.find('.to-parent-display').val();
            const fromQty = parseInt($row.find('.from-qty-input').val()) || 0;
            const ratio = $row.find('.ratio-select').val();
            
            // Validation
            if (!fromSku) {
                showToast('Please select a FROM SKU', 'error');
                return;
            }
            
            if (fromQty <= 0) {
                showToast('FROM Qty must be greater than 0', 'error');
                return;
            }
            
            if (toQty <= 0) {
                showToast('TO Qty must be greater than 0', 'error');
                return;
            }
            
            // Get FROM SKU data
            const fromItem = allTableData.find(function(i) { return i.SKU === fromSku; });
            const fromInv = fromItem ? (parseInt(fromItem.INV) || 0) : 0;
            const fromDil = fromItem ? (parseFloat(fromItem.DIL) || 0) : 0;
            
            if (fromQty > fromInv) {
                showToast('Insufficient inventory for ' + fromSku + '. Available: ' + fromInv, 'error');
                return;
            }
            
            // Confirm
            if (!confirm('Transfer ' + fromQty + ' units FROM ' + fromSku + ' TO ' + toQty + ' units TO ' + toSku + '?')) {
                return;
            }
            
            // Prepare transfer data
            // Backend: to_sku = destination, from_sku = source (SWAPPING SKUs!)
            // UI: FROM SKU (source), TO SKU (destination)
            // Transfer: Deduct FROM Qty from FROM SKU, Add TO Qty to TO SKU
            const transferData = {
                to_sku: toSku,             // TO SKU (destination - add TO here)
                to_parent_name: toParent,
                to_available_qty: toInv,
                to_dil_percent: toDil * 100,
                to_adjust_qty: toQty,      // Add TO Qty
                from_sku: fromSku,         // FROM SKU (source - deduct FROM here)
                from_parent_name: fromParent,
                from_available_qty: fromInv,
                from_dil_percent: fromDil * 100,
                from_adjust_qty: fromQty,  // Deduct FROM Qty
                ratio: ratio,
                _token: $('meta[name="csrf-token"]').attr('content')
            };
            
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: '{{ route("stock.balance.store") }}',
                method: 'POST',
                data: transferData,
                timeout: 120000,
                success: function(response) {
                    showToast(response.message || 'Transfer successful!', 'success');
                    
                    // Keep per-SKU preferences saved (don't clear)
                    // Each row will remember its own FROM SKU and Ratio
                    
                    // Reload table to get fresh inventory data
                    table.setData().then(function() {
                        if (transferModeActive) {
                            setTimeout(function() {
                                initializeSelect2FromSku();
                            }, 100);
                        }
                    });
                    
                    $btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
                    const errorMsg = xhr.responseJSON?.error || 'Transfer failed';
                    const details = xhr.responseJSON?.details || '';
                    showToast(errorMsg + (details ? '<br>' + details : ''), 'error');
                }
            });
        });
        
        // Initialize Tabulator
        table = new Tabulator("#stock-balance-table", {
            ajaxURL: "/stock-balance-inventory-data",
            ajaxResponse: function(url, params, response) {
                allTableData = response.data || [];
                return response.data || [];
            },
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200],
            paginationCounter: "rows",
            initialSort: [{
                column: "DIL",
                dir: "desc"
            }],
            columns: [
                {
                    title: "<input type='checkbox' id='select-all-checkbox'>",
                    field: "_select",
                    hozAlign: "center",
                    headerSort: false,
                    width: 40,
                    visible: true,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.SKU;
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return '<input type="checkbox" class="sku-checkbox" data-sku="' + sku + '" ' + isChecked + '>';
                    }
                },
                {
                    title: "Image",
                    field: "IMAGE_URL",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? '<img src="' + value + '" style="width:40px;height:40px;object-fit:cover;">' : '';
                    },
                    headerSort: false,
                    width: 60
                },
                {
                    title: "Parent",
                    field: "Parent",
                    headerFilter: "input",
                    width: 150,
                    frozen: true,
                    visible: false
                },
                {
                    title: "SKU",
                    field: "SKU",
                    headerFilter: "input",
                    frozen: true,
                    width: 230,
                    cssClass: "fw-bold",
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        return '<span>' + sku + '</span>' +
                            '<i class="fa fa-copy text-secondary copy-sku-icon" ' +
                            'style="cursor: pointer; margin-left: 8px; font-size: 14px;" ' +
                            'data-sku="' + sku + '" ' +
                            'title="Copy SKU"></i>';
                    }
                },
                {
                    title: "INV",
                    field: "INV",
                    hozAlign: "center",
                    sorter: "number",
                    width: 60
                },
                {
                    title: "SOLD",
                    field: "SOLD",
                    hozAlign: "center",
                    sorter: "number",
                    width: 60
                },
                {
                    title: "DIL%",
                    field: "DIL",
                    hozAlign: "center",
                    sorter: "number",
                    width: 70,
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        if (value <= 0) return '<span>-</span>';
                        
                        const percent = Math.round(value * 100);
                        let className = '';
                        
                        if (percent < 16.66) className = 'dil-red';
                        else if (percent >= 16.66 && percent < 25) className = 'dil-yellow';
                        else if (percent >= 25 && percent < 50) className = 'dil-green';
                        else className = 'dil-pink';
                        
                        return '<span class="' + className + '">' + percent + '%</span>';
                    }
                },
                {
                    title: "ACTION",
                    field: "ACTION",
                    hozAlign: "center",
                    width: 90,
                    headerSort: false,
                    formatter: function(cell) {
                        const value = cell.getValue() || '';
                        let selectBg = '#6c757d';
                        let selectColor = 'white';
                        
                        if (value === 'RB') {
                            selectBg = '#28a745';
                        } else if (value === 'NRB') {
                            selectBg = '#dc3545';
                        }
                        
                        // Use colored dots instead of text
                        return '<select class="form-select form-select-sm action-select" data-sku="' + cell.getRow().getData().SKU + '" style="width:80px; font-size:14px; font-weight:bold; text-align:center; background-color:' + selectBg + '; color:' + selectColor + '; border:none;">' +
                            '<option value="" ' + (value === '' ? 'selected' : '') + ' style="background-color:#6c757d;color:white;">--</option>' +
                            '<option value="RB" ' + (value === 'RB' ? 'selected' : '') + ' style="background-color:#28a745;color:white;">🟢</option>' +
                            '<option value="NRB" ' + (value === 'NRB' ? 'selected' : '') + ' style="background-color:#dc3545;color:white;">🔴</option>' +
                            '</select>';
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    }
                },
                {
                    title: "TO SKU",
                    field: "from_sku_display",
                    hozAlign: "center",
                    width: 150,
                    visible: false,
                    cssClass: "fw-bold text-success",
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        return '<div style="padding: 6px; background-color: #d4edda; border-radius: 4px; font-weight: bold;">' + rowData.SKU + '</div>';
                    }
                },
                {
                    title: "FROM SKU",
                    field: "to_sku",
                    hozAlign: "center",
                    width: 240,
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const currentSku = rowData.SKU;
                        let html = '<select class="form-select form-select-sm to-sku-select" data-from-sku="' + currentSku + '" style="width:230px;"><option value="">Search FROM SKU...</option>';
                        allTableData.forEach(function(item) {
                            // Exclude SKUs that contain "PARENT" in the SKU name
                            if (item.SKU && item.SKU !== currentSku && item.SKU.toUpperCase().indexOf('PARENT') === -1) {
                                // Show SKU only
                                const displayText = item.SKU;
                                html += '<option value="' + item.SKU + '" data-parent="' + (item.Parent || '') + '" data-inv="' + (item.INV || 0) + '" data-search="' + item.SKU + ' ' + (item.Parent || '') + '">' + displayText + '</option>';
                            }
                        });
                        html += '</select>';
                        return html;
                    }
                },
                {
                    title: "FROM Parent",
                    field: "to_parent",
                    hozAlign: "center",
                    width: 120,
                    visible: false,
                    formatter: function(cell) {
                        return '<input type="text" class="form-control form-control-sm to-parent-display" readonly style="width:110px;">';
                    }
                },
                {
                    title: "FROM INV",
                    field: "from_inv_display",
                    hozAlign: "center",
                    width: 70,
                    visible: false,
                    formatter: function(cell) {
                        return '<input type="text" class="form-control form-control-sm from-inv-display" readonly style="width:60px;">';
                    }
                },
                {
                    title: "FROM SOLD",
                    field: "from_sold_display",
                    hozAlign: "center",
                    width: 80,
                    visible: false,
                    formatter: function(cell) {
                        return '<input type="text" class="form-control form-control-sm from-sold-display" readonly style="width:70px;">';
                    }
                },
                {
                    title: "FROM DIL%",
                    field: "from_dil_display",
                    hozAlign: "center",
                    width: 80,
                    visible: false,
                    formatter: function(cell) {
                        return '<span class="from-dil-percent" style="font-weight:600;">-</span>';
                    }
                },
                {
                    title: "FROM Qty",
                    field: "from_qty",
                    hozAlign: "center",
                    width: 80,
                    visible: false,
                    formatter: function(cell) {
                        return '<input type="number" class="form-control form-control-sm from-qty-input" min="1" value="" placeholder="Select FROM SKU first" style="width:70px;">';
                    }
                },
                {
                    title: "TO Qty",
                    field: "to_qty_calc",
                    hozAlign: "center",
                    width: 70,
                    visible: false,
                    formatter: function(cell) {
                        return '<input type="text" class="form-control form-control-sm to-qty-display" readonly placeholder="Calc" style="width:60px; background-color:#d4edda; font-weight:bold;">';
                    }
                },
                {
                    title: "Ratio",
                    field: "ratio",
                    hozAlign: "center",
                    width: 100,
                    visible: false,
                    formatter: function(cell) {
                        return '<select class="form-select form-select-sm ratio-select" style="width:90px; font-size:14px;"><option value="1:4">1:4</option><option value="1:2">1:2</option><option value="1:1" selected>1:1</option><option value="2:1">2:1</option><option value="4:1">4:1</option></select>';
                    }
                },
                {
                    title: "Submit",
                    field: "submit",
                    hozAlign: "center",
                    width: 70,
                    visible: false,
                    headerSort: false,
                    formatter: function(cell) {
                        return '<button class="btn btn-success btn submit-transfer-btn" title="Execute Transfer"><i class="fas fa-check"></i></button>';
                    }
                }
            ]
        });
        
        // SKU Search
        $('#sku-search').on('keyup', function() {
            table.setFilter("SKU", "like", $(this).val());
        });
        
        // Filters
        $('#parent-filter').on('change', function() {
            applyAllFilters();
        });
        
        $('#dil-filter').on('change', function() {
            applyAllFilters();
        });
        
        $('#action-filter').on('change', function() {
            applyAllFilters();
        });
        
        // Apply all filters together
        function applyAllFilters() {
            table.clearFilter();
            
            // Parent filter
            const parentVal = $('#parent-filter').val();
            if (parentVal) {
                table.addFilter("Parent", "=", parentVal);
            }
            
            // DIL filter
            const dilVal = $('#dil-filter').val();
            if (dilVal) {
                table.addFilter(function(data) {
                    const dil = (parseFloat(data.DIL) || 0) * 100;
                    if (dilVal === 'red') return dil < 16.66;
                    if (dilVal === 'yellow') return dil >= 16.66 && dil < 25;
                    if (dilVal === 'green') return dil >= 25 && dil < 50;
                    if (dilVal === 'pink') return dil >= 50;
                    return true;
                });
            }
            
            // ACTION filter
            const actionVal = $('#action-filter').val();
            if (actionVal) {
                if (actionVal === '--') {
                    // Filter for blank/null/empty ACTION values
                    table.addFilter(function(data) {
                        return !data.ACTION || data.ACTION === '' || data.ACTION === null;
                    });
                } else {
                    table.addFilter("ACTION", "=", actionVal);
                }
            }
        }
        
        // Export CSV
        $('#export-btn').on('click', function() {
            table.download("csv", "stock_balance_export.csv");
        });
        
        // Show all columns
        $('#show-all-columns-btn').on('click', function() {
            table.getColumns().forEach(function(col) {
                if (col.getField() !== '_select') {
                    col.show();
                }
            });
        });
        
        // Populate parent filter and apply default filters
        table.on('dataLoaded', function() {
            const parents = new Set();
            allTableData.forEach(function(item) {
                if (item.Parent) parents.add(item.Parent);
            });
            
            $('#parent-filter').html('<option value="">All Parents</option>');
            Array.from(parents).sort().forEach(function(parent) {
                $('#parent-filter').append('<option value="' + parent + '">' + parent + '</option>');
            });
            
            // Apply default RB filter
            applyAllFilters();
        });
        
        // Initialize Select2 on FROM SKU dropdowns
        function initializeSelect2FromSku() {
            $('.to-sku-select').each(function() {
                const $select = $(this);
                const $row = $select.closest('.tabulator-row');
                const row = table.getRow($row[0]);
                const rowData = row.getData();
                const toSku = rowData.SKU; // Current row's SKU (TO SKU)
                
                // Load saved data for THIS specific TO SKU
                const savedData = JSON.parse(localStorage.getItem('transfer_' + toSku) || '{}');
                const savedFromSku = savedData.fromSku || null;
                const savedRatio = savedData.ratio || '1:1';
                
                // Destroy existing Select2 if any
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }
                
                // Initialize Select2
                $select.select2({
                    placeholder: 'Search FROM SKU...',
                    allowClear: true,
                    width: '230px',
                    dropdownAutoWidth: false,
                    matcher: function(params, data) {
                        // If no search term, return all
                        if ($.trim(params.term) === '') {
                            return data;
                        }
                        
                        // Search in both SKU and Parent
                        const searchTerm = params.term.toLowerCase();
                        const text = (data.text || '').toLowerCase();
                        const searchData = $(data.element).attr('data-search') || '';
                        
                        if (text.indexOf(searchTerm) > -1 || searchData.toLowerCase().indexOf(searchTerm) > -1) {
                            return data;
                        }
                        
                        return null;
                    }
                });
                
                // Auto-load saved FROM SKU for THIS specific row
                if (savedFromSku) {
                    $select.val(savedFromSku).trigger('change');
                }
                
                // Set saved ratio for THIS row
                $row.find('.ratio-select').val(savedRatio);
            });
        }
        
        // Update table on render complete
        table.on('renderComplete', function() {
            // Initialize Select2 when Transfer Mode is active
            if (transferModeActive) {
                initializeSelect2FromSku();
            }
        });
        
    });
</script>
@endsection
