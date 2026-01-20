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
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
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
        toast.className = 'toast align-items-center text-white bg-' + (type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info') + ' border-0';
        toast.setAttribute('role', 'alert');
        toast.innerHTML = '<div class="d-flex"><div class="toast-body">' + message + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', function() { toast.remove(); });
    }
    
    $(document).ready(function() {
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
                table.getColumn('to_sku_display').show();
                table.getColumn('from_sku').show();
                table.getColumn('from_parent').show();
                table.getColumn('from_qty').show();
                table.getColumn('to_qty_calc').show();
                table.getColumn('ratio').show();
                table.getColumn('submit').show();
                // Reduce ACTION column width to save space
                table.getColumn('ACTION').updateDefinition({width: 100});
            } else {
                $(this).removeClass('btn-danger').addClass('btn-primary').html('<i class="fas fa-exchange-alt"></i> Transfer Mode');
                // Hide transfer columns
                table.getColumn('to_sku_display').hide();
                table.getColumn('from_sku').hide();
                table.getColumn('from_parent').hide();
                table.getColumn('from_qty').hide();
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
        $(document).on('change', '.from-sku-select', function() {
            const $select = $(this);
            const $row = $select.closest('.tabulator-row');
            
            const selectedOption = $select.find('option:selected');
            const fromParent = selectedOption.attr('data-parent') || '';
            const fromInv = parseInt(selectedOption.attr('data-inv')) || 0;
            
            // Auto-fill FROM Parent and FROM Qty
            $row.find('.from-parent-display').val(fromParent);
            $row.find('.from-qty-input').val(fromInv);
            
            // Recalculate TO Qty based on ratio
            calculateToQty($row);
        });
        
        // Ratio change handler (inline in table)
        $(document).on('change', '.ratio-select', function() {
            const $select = $(this);
            const $row = $select.closest('.tabulator-row');
            calculateToQty($row);
        });
        
        // FROM Qty input change handler
        $(document).on('input', '.from-qty-input', function() {
            const $input = $(this);
            const $row = $input.closest('.tabulator-row');
            calculateToQty($row);
        });
        
        // Calculate TO Qty for a row (FROM Qty × Ratio = TO Qty)
        function calculateToQty($row) {
            const fromQty = parseInt($row.find('.from-qty-input').val()) || 0;
            const ratio = $row.find('.ratio-select').val() || '1:1';
            const ratioParts = ratio.split(':');
            const toQty = Math.round(fromQty * (parseFloat(ratioParts[1]) / parseFloat(ratioParts[0])));
            $row.find('.to-qty-display').val(toQty);
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
            
            // User selects FROM SKU manually
            const fromSku = $row.find('.from-sku-select').val();
            const fromParent = $row.find('.from-parent-display').val();
            const fromQty = parseInt($row.find('.from-qty-input').val()) || 0;
            const toQty = parseInt($row.find('.to-qty-display').val()) || 0;
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
            
            // Prepare transfer data (backend expects to_sku as source, from_sku as destination)
            const transferData = {
                to_sku: fromSku,
                to_parent_name: fromParent,
                to_available_qty: fromInv,
                to_dil_percent: fromDil * 100,
                to_adjust_qty: fromQty,
                from_sku: toSku,
                from_parent_name: toParent,
                from_available_qty: toInv,
                from_dil_percent: toDil * 100,
                from_adjust_qty: toQty,
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
                    // Reset the row
                    $row.find('.from-sku-select').val('');
                    $row.find('.from-parent-display').val('');
                    $row.find('.from-qty-input').val('');
                    $row.find('.to-qty-display').val('');
                    $row.find('.ratio-select').val('1:1');
                    $btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
                    
                    // Reload table
                    table.setData();
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
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const sku = rowData.SKU;
                        const isChecked = selectedSkus.has(sku) ? 'checked' : '';
                        return '<input type="checkbox" class="sku-select-checkbox" data-sku="' + sku + '" ' + isChecked + '>';
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
                    frozen: true
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
                        
                        return '<select class="form-select form-select-sm action-select" data-sku="' + cell.getRow().getData().SKU + '" style="width:80px; font-size:14px; font-weight:bold; text-align:center; background-color:' + selectBg + '; color:' + selectColor + '; border:none;">' +
                            '<option value="" ' + (value === '' ? 'selected' : '') + ' style="background-color:#6c757d;color:white;">--</option>' +
                            '<option value="RB" ' + (value === 'RB' ? 'selected' : '') + ' style="background-color:#28a745;color:white;">RB</option>' +
                            '<option value="NRB" ' + (value === 'NRB' ? 'selected' : '') + ' style="background-color:#dc3545;color:white;">NRB</option>' +
                            '</select>';
                    },
                    cellClick: function(e, cell) {
                        e.stopPropagation();
                    }
                },
                {
                    title: "TO SKU",
                    field: "to_sku_display",
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
                    field: "from_sku",
                    hozAlign: "center",
                    width: 180,
                    visible: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        const currentSku = rowData.SKU;
                        let html = '<select class="form-select form-select-sm from-sku-select" data-to-sku="' + currentSku + '" style="width:170px;"><option value="">Select FROM SKU</option>';
                        allTableData.forEach(function(item) {
                            if (item.SKU && item.SKU !== currentSku) {
                                html += '<option value="' + item.SKU + '" data-parent="' + (item.Parent || '') + '" data-inv="' + (item.INV || 0) + '">' + item.SKU + '</option>';
                            }
                        });
                        html += '</select>';
                        return html;
                    }
                },
                {
                    title: "FROM Parent",
                    field: "from_parent",
                    hozAlign: "center",
                    width: 120,
                    visible: false,
                    formatter: function(cell) {
                        return '<input type="text" class="form-control form-control-sm from-parent-display" readonly style="width:110px;">';
                    }
                },
                {
                    title: "FROM Qty",
                    field: "from_qty",
                    hozAlign: "center",
                    width: 80,
                    visible: false,
                    formatter: function(cell) {
                        return '<input type="number" class="form-control form-control-sm from-qty-input" min="1" style="width:70px;">';
                    }
                },
                {
                    title: "TO Qty",
                    field: "to_qty_calc",
                    hozAlign: "center",
                    width: 70,
                    visible: false,
                    formatter: function(cell) {
                        return '<input type="text" class="form-control form-control-sm to-qty-display" readonly style="width:60px;">';
                    }
                },
                {
                    title: "Ratio",
                    field: "ratio",
                    hozAlign: "center",
                    width: 80,
                    visible: false,
                    formatter: function(cell) {
                        return '<select class="form-select form-select-sm ratio-select" style="width:70px;"><option value="1:4">1:4</option><option value="1:2">1:2</option><option value="1:1" selected>1:1</option><option value="2:1">2:1</option><option value="4:1">4:1</option></select>';
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
                        return '<button class="btn btn-success btn-sm submit-transfer-btn" title="Execute Transfer"><i class="fas fa-check"></i></button>';
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
                table.addFilter("ACTION", "=", actionVal);
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
        
        // Update table on render complete
        table.on('renderComplete', function() {
            // Table rendered
        });
        
    });
</script>
@endsection
