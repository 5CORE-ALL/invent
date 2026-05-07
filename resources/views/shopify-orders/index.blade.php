@extends('layouts.vertical', ['title' => 'Shopify Orders (30 Days)', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<style>
    .tabulator-col .tabulator-col-sorter {
        display: inline-block !important;
    }

    .tabulator .tabulator-header .tabulator-col {
        height: auto !important;
    }

    .tabulator-paginator label {
        margin-right: 5px;
    }

    .stats-card {
        border-left: 4px solid #667eea;
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    }

    .stats-card:hover {
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        transform: translateY(-2px);
    }

    .stats-number {
        font-size: 2rem;
        font-weight: bold;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stats-label {
        color: #6c757d;
        font-size: 0.85rem;
        text-transform: uppercase;
        font-weight: 600;
    }

    .badge-sold {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
    }

    .badge-restocked {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
    }

    .btn-run-snapshot {
        background: #28a745;
        border: none;
        color: white;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-run-snapshot:hover {
        background: #218838;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        color: white;
    }

    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    }

    .custom-toast {
        min-width: 300px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        padding: 16px;
        margin-bottom: 10px;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    .custom-toast.success {
        border-left: 4px solid #28a745;
    }

    .custom-toast.error {
        border-left: 4px solid #dc3545;
    }
</style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    @include('layouts.shared.page-title', [
        'page_title' => 'SKU Sales by Source (Last 30 Days)',
        'sub_title' => 'Amazon, eBay, Shopify & Other Sources - PST Timezone',
    ])
    
    <div class="toast-container"></div>
    
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4 class="mb-3">
                    <i class="fas fa-chart-bar me-2"></i>SKU Sales by Source (Last 30 Days)
                </h4>
                
                <!-- Stats Cards -->
                <div class="row mb-3" id="stats-container">
                    <div class="col-md-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="stats-number" id="total-records">-</div>
                                <div class="stats-label">Total Orders</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card" style="border-left-color: #28a745;">
                            <div class="card-body">
                                <div class="stats-number" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;" id="total-quantity">-</div>
                                <div class="stats-label">Total Quantity</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card" style="border-left-color: #f5576c;">
                            <div class="card-body">
                                <div class="stats-number" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;" id="total-amount">-</div>
                                <div class="stats-label">Total Revenue</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Control Bar -->
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <!-- Column Visibility -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu" style="max-height: 400px; overflow-y: auto;"></ul>
                    </div>
                    
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <!-- Export Button -->
                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-download"></i> Export Excel
                    </button>

                    <!-- Refresh Button -->
                    <button type="button" class="btn btn-sm btn-info" id="refresh-btn">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
            </div>
            
            <div class="card-body" style="padding: 0;">
                <div id="inventory-table-wrapper" style="height: calc(100vh - 350px); display: flex; flex-direction: column;">
                    <!-- Search Bar -->
                    <div class="p-2 bg-light border-bottom">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU...">
                            </div>
                            <div class="col-md-3">
                                <select id="source-filter" class="form-control form-control-sm">
                                    <option value="">All Sources</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="number" id="min-quantity" class="form-control form-control-sm" placeholder="Min Qty...">
                            </div>
                            <div class="col-md-3">
                                <button id="clear-filters-btn" class="btn btn-sm btn-outline-danger" title="Clear all filters">
                                    <i class="fas fa-times"></i> Clear Filters
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tabulator Table -->
                    <div id="inventory-history-table" style="flex: 1; overflow: auto;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-after-vite')
<!-- External JavaScript Libraries (jQuery already loaded in layout) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Custom JavaScript -->
<script>
console.log('=== Inventory History Page Loading ===');
console.log('jQuery version:', $.fn.jquery);
console.log('Tabulator available:', typeof Tabulator !== 'undefined');

$(document).ready(function() {
    console.log('Document ready - initializing...');
    let table;
    let allSources = [];
    
    // Initialize Tabulator with dynamic columns
    function initTable() {
        console.log('Fetching data to build columns...');
        
        $.ajax({
            url: "{{ route('shopify-orders.get-data') }}",
            method: 'GET',
            success: function(response) {
                console.log('Data fetched:', response);
                allSources = response.sources || [];
                const data = response.data || [];
                
                // Populate source filter dropdown
                const sourceFilter = $('#source-filter');
                sourceFilter.empty();
                sourceFilter.append('<option value="">All Sources</option>');
                allSources.forEach(function(source) {
                    sourceFilter.append(`<option value="${source}">${source}</option>`);
                });
                
                // Build dynamic columns
                const columns = [
                    {
                        title: "SKU",
                        field: "sku",
                        width: 200,
                        frozen: true,
                        formatter: function(cell) {
                            return `<code class="text-primary fw-bold">${cell.getValue()}</code>`;
                        }
                    }
                ];
                
                // Add a column for each source
                allSources.forEach(function(source, index) {
                    columns.push({
                        title: source,
                        field: source,
                        width: 80,
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value > 0) {
                                return `<span style="color: #000; font-weight: 500;">${value}</span>`;
                            }
                            return '<span style="color: #ccc;">-</span>';
                        }
                    });
                });
                
                // Add Total column
                columns.push({
                    title: "Total",
                    field: "total",
                    width: 90,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return `<span style="color: #000; font-weight: bold; font-size: 15px;">${value}</span>`;
                    }
                });
                
                // Initialize Tabulator with dynamic columns
                console.log('Initializing Tabulator with', columns.length, 'columns');
                table = new Tabulator("#inventory-history-table", {
                    layout: "fitDataFill",
                    height: "600px",
                    placeholder: "No SKU sales data found in the last 30 days.",
                    pagination: true,
                    paginationSize: 50,
                    paginationSizeSelector: [25, 50, 100, 200, 500],
                    columns: columns,
                    data: data,
                    dataLoaded: function(data) {
                        console.log('Data loaded successfully. Total rows:', data.length);
                        updateColumnVisibility();
                        loadStats();
                    },
                    renderComplete: function(){
                        console.log('Table render complete');
                    }
                });
                
                showToast('Table loaded with ' + allSources.length + ' sources!', 'success');
            },
            error: function(xhr, status, error) {
                console.error('Error fetching data:', error);
                showToast('Failed to load data: ' + error, 'error');
            }
        });
    }

    // Load statistics
    function loadStats() {
        console.log('Loading statistics...');
        $.ajax({
            url: "{{ route('shopify-orders.get-stats') }}",
            method: 'GET',
            success: function(response) {
                console.log('Stats loaded:', response);
                $('#total-skus').text(response.total_skus.toLocaleString());
                $('#total-quantity').text(response.total_quantity.toLocaleString());
                $('#total-sources').text(response.total_sources.toLocaleString());
                $('#total-orders').text(response.total_orders.toLocaleString());
            },
            error: function(xhr, status, error) {
                console.error('Stats load error:', error);
                showToast('Failed to load statistics', 'error');
            }
        });
    }

    // Column visibility management
    function updateColumnVisibility() {
        const columns = table.getColumns();
        const menu = $('#column-dropdown-menu');
        menu.empty();

        columns.forEach(col => {
            const field = col.getField();
            const def = col.getDefinition();
            const title = def.title;
            const visible = col.isVisible();

            if (field && title && !def.frozen) {
                const li = $('<li class="dropdown-item" style="cursor: pointer;"></li>');
                const checkbox = $(`
                    <label style="cursor: pointer; display: block; padding: 4px 0;">
                        <input type="checkbox" class="me-2" ${visible ? 'checked' : ''} data-field="${field}">
                        ${title}
                    </label>
                `);
                
                li.append(checkbox);
                menu.append(li);

                checkbox.find('input').on('change', function() {
                    if (this.checked) {
                        col.show();
                    } else {
                        col.hide();
                    }
                });
            }
        });
    }

    // Show all columns
    $('#show-all-columns-btn').on('click', function() {
        table.getColumns().forEach(col => col.show());
        updateColumnVisibility();
    });

    // Export to Excel
    $('#export-btn').on('click', function() {
        table.download("xlsx", "sku_sales_by_source_30days.xlsx", {sheetName: "SKU Sales"});
        showToast('Excel file downloaded successfully!', 'success');
    });

    // Refresh data
    $('#refresh-btn').on('click', function() {
        if (table) {
            $.ajax({
                url: "{{ route('shopify-orders.get-data') }}",
                method: 'GET',
                success: function(response) {
                    table.setData(response.data);
                    loadStats();
                    showToast('Data refreshed successfully!', 'success');
                },
                error: function() {
                    showToast('Failed to refresh data', 'error');
                }
            });
        } else {
            initTable();
        }
    });

    // Search filters
    $('#sku-search').on('keyup', function() {
        applyFilters();
    });

    $('#source-filter').on('change', function() {
        applyFilters();
    });

    $('#min-quantity').on('keyup', function() {
        applyFilters();
    });

    function applyFilters() {
        const skuSearch = $('#sku-search').val();
        const sourceFilter = $('#source-filter').val();
        const minQty = $('#min-quantity').val();
        
        let filters = [];
        
        if (skuSearch) {
            filters.push({field: "sku", type: "like", value: skuSearch});
        }
        
        if (sourceFilter) {
            filters.push(function(data) {
                return data[sourceFilter] > 0;
            });
        }
        
        if (minQty) {
            filters.push(function(data) {
                return data.total >= parseInt(minQty);
            });
        }
        
        if (filters.length > 0) {
            table.setFilter(filters);
        } else {
            table.clearFilter();
        }
    }

    $('#clear-filters-btn').on('click', function() {
        $('#sku-search, #source-filter, #min-quantity').val('');
        table.clearFilter();
        showToast('All filters cleared', 'success');
    });

    // Toast notification
    function showToast(message, type = 'success') {
        const toast = $(`
            <div class="custom-toast ${type}">
                <div class="d-flex align-items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2" style="font-size: 20px; color: ${type === 'success' ? '#28a745' : '#dc3545'};"></i>
                    <div>
                        <strong>${type === 'success' ? 'Success' : 'Error'}</strong>
                        <p class="mb-0 small">${message}</p>
                    </div>
                </div>
            </div>
        `);

        $('.toast-container').append(toast);
        
        setTimeout(() => {
            toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Initialize table on page load
    initTable();
});
</script>
@endsection
