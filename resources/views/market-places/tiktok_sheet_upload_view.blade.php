@extends('layouts.vertical', ['title' => 'TikTok Pricing', 'sidenav' => 'condensed'])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">

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
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'TikTok Pricing',
        'sub_title' => 'TikTok Pricing',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>TikTok Sheet Upload</h4>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadSheetModal">
                        <i class="fa fa-upload"></i> Upload TikTok Sheet
                    </button>
                    
                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-download"></i> Export CSV
                    </button>

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
                </div>

                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">Summary Statistics</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-products-badge" style="color: black; font-weight: bold;">Total Products: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-inventory-badge" style="color: black; font-weight: bold;">Total Inventory: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="total-l30-badge" style="color: black; font-weight: bold;">Total L30: 0</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="tiktok-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search by SKU...">
                    </div>
                    <div id="tiktok-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Sheet Modal -->
    <div class="modal fade" id="uploadSheetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fa fa-upload me-2"></i>Upload TikTok Sheet</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadSheetForm" action="{{ route('tiktok-sheet-upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold"><i class="fa fa-file-excel text-success me-1"></i>Choose File</label>
                            <input type="file" class="form-control" name="excel_file" accept=".xlsx,.xls,.csv,.tsv,.txt" required>
                        </div>
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle me-2"></i>
                            <strong>Note:</strong> This will UPDATE existing prices based on SKU match.
                            <br><br>
                            <strong>Required Columns:</strong>
                            <ul class="mb-0 mt-2">
                                <li><strong>Seller SKU</strong> - To match records</li>
                                <li><strong>Retail Price (Local Currency)</strong> - Price to update</li>
                                <li><strong>Quantity in Main Warehouse</strong> - Inventory quantity</li>
                            </ul>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="uploadSheetForm" class="btn btn-primary"><i class="fa fa-upload me-1"></i>Upload</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    let table = null;
    
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

    function updateSummary() {
        if (!table) {
            return;
        }
        const data = table.getData("all");
        
        let totalProducts = data.length;
        let totalInventory = 0;
        let totalL30 = 0;
        
        data.forEach(row => {
            totalInventory += parseInt(row['INV']) || 0;
            totalL30 += parseInt(row['L30']) || 0;
        });
        
        $('#total-products-badge').text('Total Products: ' + totalProducts.toLocaleString());
        $('#total-inventory-badge').text('Total Inventory: ' + totalInventory.toLocaleString());
        $('#total-l30-badge').text('Total L30: ' + totalL30.toLocaleString());
    }

    $(document).ready(function() {
        table = new Tabulator("#tiktok-table", {
            ajaxURL: "/tiktok-sheet-data-json",
            ajaxSorting: false,
            layout: "fitDataStretch",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [10, 25, 50, 100, 200],
            paginationCounter: "rows",
            ajaxError: function(xhr, textStatus, errorThrown) {
                console.error('Table ajax error:', xhr.status, textStatus, errorThrown);
                console.error('Response:', xhr.responseText);
            },
            columns: [
                {
                    title: "SKU",
                    field: "sku",
                    headerFilter: "input",
                    frozen: true,
                    width: 150
                },
                {
                    title: "Price",
                    field: "price",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue());
                        if (value === 0 || isNaN(value)) {
                            return '<span style="color: #dc3545; font-weight: bold;">$0.00</span>';
                        }
                        return '<span style="color: #28a745; font-weight: 600;">$' + value.toFixed(2) + '</span>';
                    },
                    width: 100
                },
                {
                    title: "INV",
                    field: "INV",
                    hozAlign: "center",
                    sorter: "number",
                    width: 80
                },
                {
                    title: "L30",
                    field: "L30",
                    hozAlign: "center",
                    sorter: "number",
                    width: 80
                },
                {
                    title: "LP",
                    field: "lp",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        if (value === 0) {
                            return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 4px; border-radius: 3px;" title="Missing LP from Product Master">⚠️ $0.00</span>';
                        }
                        return '<span style="color: #28a745; font-weight: 600;">$' + value.toFixed(2) + '</span>';
                    },
                    width: 100
                },
                {
                    title: "Ship",
                    field: "ship",
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = parseFloat(cell.getValue()) || 0;
                        if (value === 0) {
                            return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 4px; border-radius: 3px;" title="Missing Ship from Product Master">⚠️ $0.00</span>';
                        }
                        return '<span style="color: #28a745; font-weight: 600;">$' + value.toFixed(2) + '</span>';
                    },
                    width: 100
                }
            ]
        });

        $('#sku-search').on('keyup', function() {
            const value = $(this).val();
            table.setFilter("sku", "like", value);
            updateSummary();
        });

        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field) {
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
            table.download("csv", "tiktok_data.csv");
        });

        // Handle form submission
        $('#uploadSheetForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $('#uploadSheetModal').modal('hide');
                    showToast(response.success, 'success');
                    
                    // Reload table data
                    table.setData("/tiktok-sheet-data-json");
                },
                error: function(xhr) {
                    let errorMessage = 'Error uploading file';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        errorMessage = xhr.responseJSON.error;
                    }
                    showToast(errorMessage, 'error');
                }
            });
        });
    });
</script>
@endsection

