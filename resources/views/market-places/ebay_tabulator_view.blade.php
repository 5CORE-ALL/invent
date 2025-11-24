@extends('layouts.vertical', ['title' => 'eBay Pricing Data', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
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
        'page_title' => 'eBay Pricing Data',
        'sub_title' => 'eBay Pricing Data',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>eBay Data</h4>
                <div>
                    <select id="inventory-filter" class="form-select form-select-sm me-2"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>
                    <select id="parent-filter" class="form-select form-select-sm me-2"
                        style="width: auto; display: inline-block;">
                        <option value="show">Show Parent</option>
                        <option value="hide" selected>Hide Parent</option>
                    </select>

                    <select id="pft-filter" class="form-select form-select-sm me-2"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Pft%</option>
                        <option value="0-10">0-10%</option>
                        <option value="11-14">11-14%</option>
                        <option value="15-20">15-20%</option>
                        <option value="21-49">21-49%</option>
                        <option value="50+">50%+</option>
                    </select>

                    <!-- Column Visibility Dropdown -->
                    <div class="dropdown d-inline-block me-2">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                            id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu"
                            style="max-height: 400px; overflow-y: auto;">
                            <!-- Columns will be populated by JavaScript -->
                        </ul>
                    </div>
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <a href="{{ url('/ebay-export') }}" class="btn btn-sm btn-success me-2">
                        <i class="fa fa-file-excel"></i> Export
                    </a>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="ebay-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- Table body (scrollable section) -->
                    <div id="ebay-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- LMP Modal -->
    <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">LMP Data for <span id="lmpSku"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="lmpDataList"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        const COLUMN_VIS_KEY = "ebay_tabulator_column_visibility";

        $(document).ready(function() {
            const table = new Tabulator("#ebay-table", {
                ajaxURL: "/ebay-data-json",
                ajaxSorting: true,
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 50,
                paginationCounter: "rows",
                initialSort: [{
                    column: "E Dil%",
                    dir: "asc"
                }],
                rowFormatter: function(row) {
                    if (row.getData().Parent && row.getData().Parent.startsWith('PARENT')) {
                        row.getElement().style.backgroundColor = "rgba(69, 233, 255, 0.1)";
                    }
                },
                columns: [{
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
                        width: 250
                    },
                    
                    {
                        title: "INV",
                        field: "INV",
                        hozAlign: "center",
                        width: 80
                    },
                    {
                        title: "L30",
                        field: "L30",
                        hozAlign: "center",
                        width: 80
                    },

                     {
                        title: "Dil%",
                        field: "E Dil%",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const INV = parseFloat(rowData.INV) || 0;
                            const OVL30 = parseFloat(rowData['L30']) || 0;
                            
                            if (INV === 0) return '0%';
                            
                            const dil = Math.round((OVL30 / INV) * 100);
                            return `${dil}%`;
                        },
                        width: 100
                    },
                    {
                        title: "L30",
                        field: "eBay L30",
                        hozAlign: "center",
                        width: 100
                    },
                    {
                        title: "eBay L60",
                        field: "eBay L60",
                        hozAlign: "center",
                        width: 100,
                        visible: false
                    },
                   
                    {
                        title: "Price",
                        field: "eBay Price",
                        hozAlign: "center",
                        formatter: "money",
                        formatterParams: {
                            decimal: ".",
                            thousand: ",",
                            symbol: "$",
                            precision: 2
                        },
                    
                    },
                    {
                        title: "LMP",
                        field: "lmp_price",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const sku = rowData['(Child) sku'];
                            const lmpEntries = rowData.lmp_entries || [];
                            
                            if (value && lmpEntries.length > 0) {
                                const jsonData = JSON.stringify(lmpEntries);
                                return `<a href="#" class="lmp-link" data-sku="${sku}" data-lmp-data='${jsonData}'>$${parseFloat(value).toFixed(2)}</a>`;
                            }
                            return value ? `$${parseFloat(value).toFixed(2)}` : '';
                        },
                    
                    },
                    {
                        title: "AD <br> Spend <br> L30",
                        field: "AD_Spend_L30",
                        hozAlign: "center",
                        formatter: "money",
                        formatterParams: {
                            decimal: ".",
                            thousand: ",",
                            symbol: "$",
                            precision: 2
                        },
                     
                    },
                    {
                        title: "AD Sales L30",
                        field: "AD_Sales_L30",
                        hozAlign: "center",
                        formatter: "money",
                        formatterParams: {
                            decimal: ".",
                            thousand: ",",
                            symbol: "$",
                            precision: 2
                        },
                        width: 130
                    },
                    {
                        title: "AD Units L30",
                        field: "AD_Units_L30",
                        hozAlign: "center",
                        width: 120
                    },
                    {
                        title: "AD%",
                        field: "AD%",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            return `${parseFloat(value).toFixed(2)}%`;
                        },
                        width: 100
                    },
                    {
                        title: "TACOS L30",
                        field: "TacosL30",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            return `${parseFloat(value).toFixed(2)}%`;
                        },
                        width: 120
                    },
                    {
                        title: "Total Sales L30",
                        field: "T_Sale_l30",
                        hozAlign: "center",
                        formatter: "money",
                        formatterParams: {
                            decimal: ".",
                            thousand: ",",
                            symbol: "$",
                            precision: 2
                        },
                        width: 140
                    },
                    {
                        title: "Total Profit",
                        field: "Total_pft",
                        hozAlign: "center",
                        formatter: "money",
                        formatterParams: {
                            decimal: ".",
                            thousand: ",",
                            symbol: "$",
                            precision: 2
                        },
                        width: 130
                    },
                    {
                        title: "PFT %",
                        field: "PFT %",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            return `${parseFloat(value).toFixed(2)}%`;
                        },
                        width: 100
                    },
                    {
                        title: "ROI%",
                        field: "ROI%",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            return `${parseFloat(value).toFixed(2)}%`;
                        },
                        width: 100
                    },
                    {
                        title: "GPFT%",
                        field: "GPFT%",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            return `${parseFloat(value).toFixed(2)}%`;
                        },
                        width: 100
                    },
                    {
                        title: "Views",
                        field: "views",
                        hozAlign: "center",
                        width: 100
                    },
                    {
                        title: "NR",
                        field: "NR",
                        hozAlign: "center",
                        editor: "input",
                        width: 100
                    },
                    {
                        title: "SPRICE",
                        field: "SPRICE",
                        hozAlign: "center",
                        editor: "input",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? `$${parseFloat(value).toFixed(2)}` : '';
                        },
                        width: 120
                    },
                    {
                        title: "SPFT",
                        field: "SPFT",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? `${parseFloat(value).toFixed(2)}%` : '';
                        },
                        width: 100
                    },
                    {
                        title: "SROI",
                        field: "SROI",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? `${parseFloat(value).toFixed(2)}%` : '';
                        },
                        width: 100
                    },
                    {
                        title: "Listed",
                        field: "Listed",
                        formatter: "tickCross",
                        hozAlign: "center",
                        editor: true,
                        cellClick: function(e, cell) {
                            var currentValue = cell.getValue();
                            cell.setValue(!currentValue);
                        },
                        width: 100
                    },
                    {
                        title: "Live",
                        field: "Live",
                        formatter: "tickCross",
                        hozAlign: "center",
                        editor: true,
                        cellClick: function(e, cell) {
                            var currentValue = cell.getValue();
                            cell.setValue(!currentValue);
                        },
                        width: 100
                    }
                ]
            });

            table.on('cellEdited', function(cell) {
                var row = cell.getRow();
                var data = row.getData();
                var field = cell.getColumn().getField();
                var value = cell.getValue();

                if (field === 'NR') {
                    // Save NR value
                    $.ajax({
                        url: '/save-nr-ebay',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            sku: data['(Child) sku'],
                            nr: value
                        },
                        success: function(response) {
                            showToast('success', 'NR value saved successfully');
                        },
                        error: function(error) {
                            showToast('error', 'Failed to save NR value');
                        }
                    });
                } else if (field === 'SPRICE') {
                    // Save SPRICE and recalculate SPFT, SROI
                    $.ajax({
                        url: '/save-sprice-ebay',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            sku: data['(Child) sku'],
                            sprice: value
                        },
                        success: function(response) {
                            // Update calculated fields
                            cell.getRow().update({
                                SPFT: response.spft_percent,
                                SROI: response.sroi_percent
                            });
                            showToast('success', 'SPRICE saved successfully');
                        },
                        error: function(error) {
                            showToast('error', 'Failed to save SPRICE');
                        }
                    });
                } else if (field === 'Listed' || field === 'Live') {
                    // Save Listed/Live status
                    $.ajax({
                        url: '/update-listed-live-ebay',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            sku: data['(Child) sku'],
                            field: field,
                            value: value
                        },
                        success: function(response) {
                            showToast('success', field + ' status updated successfully');
                        },
                        error: function(error) {
                            showToast('error', 'Failed to update ' + field + ' status');
                        }
                    });
                }
            });

            // Apply filters
            function applyFilters() {
                const inventoryFilter = $('#inventory-filter').val();
                const parentFilter = $('#parent-filter').val();
                const pftFilter = $('#pft-filter').val();

                table.clearFilter(true);

                if (inventoryFilter === 'zero') {
                    table.addFilter('INV', '=', 0);
                } else if (inventoryFilter === 'more') {
                    table.addFilter('INV', '>', 0);
                }

                if (parentFilter === 'hide') {
                    table.addFilter(function(data) {
                        return !data.Parent || !data.Parent.startsWith('PARENT');
                    });
                }

                if (pftFilter !== 'all') {
                    table.addFilter(function(data) {
                        const pft = parseFloat(data['PFT %']) || 0;
                        switch (pftFilter) {
                            case '0-10': return pft >= 0 && pft <= 10;
                            case '11-14': return pft >= 11 && pft <= 14;
                            case '15-20': return pft >= 15 && pft <= 20;
                            case '21-49': return pft >= 21 && pft <= 49;
                            case '50+': return pft >= 50;
                            default: return true;
                        }
                    });
                }
            }

            $('#inventory-filter, #parent-filter, #pft-filter').on('change', function() {
                applyFilters();
            });

            // Build Column Visibility Dropdown
            function buildColumnDropdown() {
                const menu = document.getElementById("column-dropdown-menu");
                menu.innerHTML = '';

                fetch('/ebay-column-visibility', {
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

                fetch('/ebay-column-visibility', {
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
                fetch('/ebay-column-visibility', {
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
                // Data loaded
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

            // Toast notification
            function showToast(type, message) {
                const toast = $(`
                    <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert">
                        <div class="d-flex">
                            <div class="toast-body">${message}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                        </div>
                    </div>
                `);
                $('.toast-container').append(toast);
                const bsToast = new bootstrap.Toast(toast[0]);
                bsToast.show();
                setTimeout(() => toast.remove(), 3000);
            }
        });

        // LMP Modal Event Listener
        $(document).on('click', '.lmp-link', function(e) {
            e.preventDefault();
            const sku = $(this).data('sku');
            let data = $(this).data('lmp-data');
            
            try {
                if (typeof data === 'string') {
                    data = JSON.parse(data);
                }
                openLmpModal(sku, data);
            } catch (error) {
                console.error('Error parsing LMP data:', error);
                alert('Error loading LMP data');
            }
        });

        // LMP Modal Function
        function openLmpModal(sku, data) {
            $('#lmpSku').text(sku);
            let html = '';
            data.forEach(item => {
                html += `<div style="margin-bottom: 10px; border: 1px solid #ccc; padding: 10px;">
                    <strong>Price: $${item.price}</strong><br>
                    <a href="${item.link}" target="_blank">View Link</a>
                    ${item.image ? `<br><img src="${item.image}" alt="Product Image" style="max-width: 100px; max-height: 100px;">` : ''}
                </div>`;
            });
            $('#lmpDataList').html(html);
            $('#lmpModal').modal('show');
        }
    </script>
@endsection
