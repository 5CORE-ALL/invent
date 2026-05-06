@extends('layouts.vertical', ['title' => 'Inventory History', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-run-snapshot:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
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
        'page_title' => 'Inventory History',
        'sub_title' => 'Daily Inventory Snapshots & Sales Tracking',
    ])
    
    <div class="toast-container"></div>
    
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4 class="mb-3">
                    <i class="fas fa-history me-2"></i>Inventory History
                </h4>
                
                <!-- Stats Cards -->
                <div class="row mb-3" id="stats-container">
                    <div class="col-md-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="stats-number" id="total-records">-</div>
                                <div class="stats-label">Total Records</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card" style="border-left-color: #28a745;">
                            <div class="card-body">
                                <div class="stats-number" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;" id="total-skus">-</div>
                                <div class="stats-label">Unique SKUs</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card" style="border-left-color: #f5576c;">
                            <div class="card-body">
                                <div class="stats-number" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;" id="latest-date">-</div>
                                <div class="stats-label">Latest Snapshot</div>
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

                    <!-- Run Snapshot Button -->
                    <button type="button" class="btn btn-sm btn-run-snapshot" id="run-snapshot-btn">
                        <i class="fas fa-camera me-2"></i>Run Snapshot Now
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
                            <div class="col-md-3">
                                <input type="text" id="general-search" class="form-control form-control-sm" placeholder="Search all columns...">
                            </div>
                            <div class="col-md-2">
                                <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU...">
                            </div>
                            <div class="col-md-2">
                                <input type="date" id="date-search" class="form-control form-control-sm" placeholder="Filter by date...">
                            </div>
                            <div class="col-md-2">
                                <input type="date" id="start-date-search" class="form-control form-control-sm" placeholder="Start date...">
                            </div>
                            <div class="col-md-2">
                                <input type="date" id="end-date-search" class="form-control form-control-sm" placeholder="End date...">
                            </div>
                            <div class="col-md-1">
                                <button id="clear-filters-btn" class="btn btn-sm btn-outline-danger w-100" title="Clear all filters">
                                    <i class="fas fa-times"></i> Clear
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
    
    // Initialize Tabulator
    function initTable() {
        console.log('Initializing Tabulator table...');
        table = new Tabulator("#inventory-history-table", {
            layout: "fitColumns",
            height: "100%",
            placeholder: "No inventory history records found. Click 'Run Snapshot Now' to create your first snapshot.",
            pagination: true,
            paginationSize: 50,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            ajaxURL: "{{ route('inventory-history.get-data') }}",
            ajaxConfig: {
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            },
            ajaxError: function(xhr, textStatus, errorThrown){
                console.error('AJAX Error:', textStatus, errorThrown);
                console.error('Response:', xhr.responseText);
                showToast('Failed to load data: ' + textStatus, 'error');
            },
            ajaxResponse: function(url, params, response) {
                console.log('Data received:', response);
                return response.data;
            },
            columns: [
                {
                    title: "#",
                    field: "id",
                    width: 70,
                    frozen: true,
                    headerFilter: false,
                    hozAlign: "center"
                },
                {
                    title: "Snapshot Date",
                    field: "snapshot_date_formatted",
                    width: 150,
                    frozen: true,
                    sorter: "date",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        return `<strong>${row.snapshot_date_formatted}</strong><br><small class="text-muted">${row.day_of_week}</small>`;
                    }
                },
                {
                    title: "SKU",
                    field: "sku",
                    width: 200,
                    frozen: true,
                    formatter: function(cell) {
                        return `<code class="text-primary">${cell.getValue()}</code>`;
                    }
                },
                {
                    title: "Product Name",
                    field: "product_name",
                    width: 300,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return `<div style="max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${value}">${value}</div>`;
                    }
                },
                {
                    title: "Opening",
                    field: "opening_inventory",
                    width: 120,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        return `<span class="badge bg-secondary">${cell.getValue().toLocaleString()}</span>`;
                    }
                },
                {
                    title: "Closing",
                    field: "closing_inventory",
                    width: 120,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        return `<span class="badge bg-info">${cell.getValue().toLocaleString()}</span>`;
                    }
                },
                {
                    title: "Sold",
                    field: "sold_quantity",
                    width: 120,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value > 0) {
                            return `<span class="badge badge-sold"><i class="fas fa-arrow-down me-1"></i>${value.toLocaleString()}</span>`;
                        }
                        return '<span class="text-muted">-</span>';
                    }
                },
                {
                    title: "Restocked",
                    field: "restocked_quantity",
                    width: 130,
                    hozAlign: "right",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value > 0) {
                            return `<span class="badge badge-restocked"><i class="fas fa-arrow-up me-1"></i>${value.toLocaleString()}</span>`;
                        }
                        return '<span class="text-muted">-</span>';
                    }
                },
                {
                    title: "Recorded At",
                    field: "created_at",
                    width: 180,
                    hozAlign: "center",
                    formatter: function(cell) {
                        return `<small class="text-muted">${cell.getValue()}</small>`;
                    }
                }
            ],
            dataLoaded: function(data) {
                console.log('Data loaded successfully. Total rows:', data.length);
                updateColumnVisibility();
                loadStats();
            },
            renderComplete: function(){
                console.log('Table render complete');
            }
        });
    }

    // Load statistics
    function loadStats() {
        console.log('Loading statistics...');
        $.ajax({
            url: "{{ route('inventory-history.get-stats') }}",
            method: 'GET',
            success: function(response) {
                console.log('Stats loaded:', response);
                $('#total-records').text(response.total_records.toLocaleString());
                $('#total-skus').text(response.total_skus.toLocaleString());
                $('#latest-date').text(response.latest_date || 'N/A');
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
        table.download("xlsx", "inventory_history.xlsx", {sheetName: "Inventory History"});
        showToast('Excel file downloaded successfully!', 'success');
    });

    // Run snapshot
    $('#run-snapshot-btn').on('click', function() {
        if (!confirm('Are you sure you want to run the inventory snapshot now?')) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true);
        btn.html('<i class="fas fa-spinner fa-spin me-2"></i>Running...');

        $.ajax({
            url: "{{ route('inventory-history.run-snapshot') }}",
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                showToast(response.message, 'success');
                table.replaceData();
                loadStats();
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Snapshot failed. Please try again.';
                showToast(message, 'error');
            },
            complete: function() {
                btn.prop('disabled', false);
                btn.html('<i class="fas fa-camera me-2"></i>Run Snapshot Now');
            }
        });
    });

    // Refresh data
    $('#refresh-btn').on('click', function() {
        table.replaceData();
        loadStats();
        showToast('Data refreshed successfully!', 'success');
    });

    // Search filters
    $('#general-search').on('keyup', function() {
        table.setFilter([
            [
                {field: "sku", type: "like", value: this.value},
                {field: "product_name", type: "like", value: this.value},
                {field: "snapshot_date_formatted", type: "like", value: this.value}
            ]
        ]);
    });

    $('#sku-search').on('keyup', function() {
        table.setFilter("sku", "like", this.value);
    });

    $('#date-search').on('change', function() {
        if (this.value) {
            table.setFilter("snapshot_date", "=", this.value);
        } else {
            table.clearFilter("snapshot_date");
        }
    });

    $('#start-date-search, #end-date-search').on('change', function() {
        const startDate = $('#start-date-search').val();
        const endDate = $('#end-date-search').val();
        
        if (startDate && endDate) {
            table.setFilter([
                {field: "snapshot_date", type: ">=", value: startDate},
                {field: "snapshot_date", type: "<=", value: endDate}
            ]);
        } else {
            table.clearFilter("snapshot_date");
        }
    });

    $('#clear-filters-btn').on('click', function() {
        $('#general-search, #sku-search, #date-search, #start-date-search, #end-date-search').val('');
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
