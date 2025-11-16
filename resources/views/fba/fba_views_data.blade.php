@extends('layouts.vertical', ['title' => 'FBA Sales Data', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
@endsection
@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
 @include('layouts.shared.page-title', [
        'page_title' => 'FBA Analytics Data',
        'sub_title' => 'FBA Analytics Data',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>FBA Data </h4>
                <div>
                    <select id="inventory-filter" class="form-select form-select-sm me-2"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more">More than 0</option>
                    </select>
                    <select id="parent-filter" class="form-select form-select-sm me-2"
                        style="width: auto; display: inline-block;">
                        <option value="show">Show Parent</option>
                        <option value="hide">Hide Parent</option>
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
                    <a href="{{ url('/fba-manual-sample') }}" class="btn btn-sm btn-info me-2">
                        <i class="fa fa-download"></i> Sample Template
                    </a>
                    <a href="{{ url('/fba-manual-export') }}" class="btn btn-sm btn-success me-2">
                        <i class="fa fa-file-excel"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                        data-bs-target="#importModal">
                        <i class="fa fa-upload"></i>
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="fba-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">

                    <!--Table body (scrollable section) -->
                    <div id="fba-table" style="flex: 1;"></div>

                </div>
            </div>
        </div>
    </div>

    <!-- Inv age Modal -->
    <div class="modal fade" id="invageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Inv age Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>SKU:</strong> <span id="modalSKU"></span></p>
                    <p><strong>Inv age:</strong> <span id="modalInvage"></span></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import FBA Manual Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="importForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="csvFile" class="form-label">Choose CSV File</label>
                            <input type="file" class="form-control" id="csvFile" name="file" accept=".csv"
                                required>
                        </div>
                        <small class="text-muted">
                            <i class="fa fa-info-circle"></i> CSV must have: SKU, Dimensions, Weight, Qty in each box, Total
                            qty Sent, Total Send Cost, Inbound qty, Send cost, IN Charges
                        </small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="uploadBtn">Upload & Import</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- LMP Modal -->
        <div class="modal fade" id="lmpModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
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
            $(document).ready(function() {
                const table = new Tabulator("#fba-table", {
                    ajaxURL: "/fba-data-json",
                    layout: "fitData",
                    pagination: true,
                    paginationSize: 50,
                    rowFormatter: function(row) {
                        if (row.getData().is_parent) {
                            row.getElement().classList.add("parent-row");
                        }
                    },
                    columns: [{
                            title: "Parent",
                            field: "Parent",
                            headerFilter: "input",
                            headerFilterPlaceholder: "Search Parent...",
                            cssClass: "text-primary",
                            tooltip: true,
                            frozen: true
                        },
                        {
                            title: "Child SKU",
                            field: "SKU",
                            headerFilter: "input",
                            headerFilterPlaceholder: "Search SKU...",
                            cssClass: "font-weight-bold",
                            tooltip: true,
                            frozen: true
                        },
                        {
                            title: "FBA SKU",
                            field: "FBA_SKU",
                            headerFilter: "input",
                            headerFilterPlaceholder: "Search SKU...",
                            cssClass: "font-weight-bold",
                            tooltip: true,
                            frozen: true
                        },

                        // {
                        //     title: "Shopify INV",
                        //     field: "Shopify_INV",
                        //     hozAlign: "center"
                        // },
                        {
                            title: "FBA INV",
                            field: "FBA_Quantity",
                            hozAlign: "center"
                        },


                        {
                            title: "L60 Units",
                            field: "l60_units",
                            hozAlign: "center"
                        },

                        {
                            title: "L30 Units",
                            field: "l30_units",
                            hozAlign: "center"
                        },

                        {
                            title: "FBA Dil",
                            field: "FBA_Dil",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '';
                                const formattedValue = `${value.toFixed(0)}%`;
                                let color = '';
                                if (value <= 50) color = 'red';
                                else if (value <= 100) color = 'green';
                                else color = 'purple';
                                return `<span style="color:${color}; font-weight:600;">${formattedValue}</span>`;
                            },
                        },

                        {
                            title: "Inv age",
                            field: "Inv_age",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = cell.getValue();
                                return `<span>${value || ''}</span> <i class="fa fa-eye" style="cursor:pointer; color:#3b7ddd; margin-left:5px;" onclick="openInvageModal('${value || ''}', '${cell.getRow().getData().SKU}')"></i>`;
                            }
                        },

                        {
                            title: "FBA Price",
                            field: "FBA_Price",
                            hozAlign: "center",
                            // formatter: "dollar"
                        },


                        {
                            title: "Pft%",
                            field: "Pft%",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const data = cell.getRow().getData();
                                return data['Pft%_HTML'] ||
                                    `${parseFloat(cell.getValue() || 0).toFixed(1)}%`;
                            },
                        },

                        {
                            title: "ROI%",
                            field: "ROI%",
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },

                        {
                            title: "TPFT",
                            field: "TPFT",
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },

                        {
                            title: "S Price",
                            field: "S_Price",
                            hozAlign: "center",
                            editor: "input",
                            cellEdited: function(cell) {
                                var data = cell.getRow().getData();
                                var value = cell.getValue();

                                $.ajax({
                                    url: '/update-fba-manual-data',
                                    method: 'POST',
                                    data: {
                                        sku: data.FBA_SKU,
                                        field: 's_price',
                                        value: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function() {
                                        table
                                            .replaceData();
                                    }
                                });
                            }
                        },
                        {
                            title: "SPft%",
                            field: "SPft%",
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },
                        {
                            title: "SROI%",
                            field: "SROI%",
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },

                        {
                            title: "LMP ",
                            field: "lmp_1",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = cell.getValue();
                                const rowData = cell.getRow().getData();
                                if (value > 0) {
                                    return `<a href="#" class="lmp-link" data-sku="${rowData.SKU}" data-lmp-data='${JSON.stringify(rowData.lmp_data)}' style="color: blue; text-decoration: underline;">${value}</a>`;
                                } else {
                                    return value || '';
                                }
                            }
                        },

                        {
                            title: "LP",
                            field: "LP",
                            hozAlign: "center"
                        },

                        {
                            title: "FBA Ship",
                            field: "FBA_Ship_Calculation",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '';
                                return value.toFixed(2);
                            }
                        },
                        {
                            title: "FBA_CVR",
                            field: "FBA_CVR",
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },

                        {
                            title: "Views",
                            field: "Current_Month_Views",
                            hozAlign: "center"
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
                            }
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
                            }
                        },
                        {
                            title: "FBA Fee",
                            field: "Fulfillment_Fee",
                            hozAlign: "center"
                        },

                        {
                            title: "FBA Fee Manual",
                            field: "FBA_Fee_Manual",
                            hozAlign: "center",
                            editor: "input",
                            formatter: function(cell) {
                                cell.getElement().style.color = "#a80f8b"; // dark text
                                return cell.getValue();
                            }
                        },

                        ,

                        {
                            title: "ASIN",
                            field: "ASIN"
                        },
                        {
                            title: "Barcode",
                            field: "Barcode",
                            editor: "list",
                            editorParams: {
                                values: ["", "M", "A"],
                                autocomplete: true,
                                allowEmpty: true,
                                listOnEmpty: true
                            },
                            hozAlign: "center"
                        },
                        {
                            title: "Done",
                            field: "Done",
                            formatter: "tickCross",
                            hozAlign: "center",
                            editor: true,
                            cellClick: function(e, cell) {
                                var currentValue = cell.getValue();
                                cell.setValue(!currentValue);
                            }
                        },


                        {
                            title: "Dispatch Date",
                            field: "Dispatch_Date",
                            hozAlign: "center",
                            editor: "input"
                        },
                        {
                            title: "Weight",
                            field: "Weight",
                            hozAlign: "center",
                            editor: "input"
                        },
                        {
                            title: "Quantity Box",
                            field: "Quantity_in_each_box",
                            hozAlign: "center",
                            editor: "input"
                        },
                        {
                            title: "Sent Quantity",
                            field: "Total_quantity_sent",
                            hozAlign: "center",
                            editor: "input"
                        },
                        {
                            title: "Send Cost",
                            field: "Send_Cost",
                            hozAlign: "center",
                            editor: "input"
                        },
                        {
                            title: "IN Charges",
                            field: "IN_Charges",
                            hozAlign: "center",
                            editor: "input"
                        },
                        {
                            title: "Warehouse INV Reduction",
                            field: "Warehouse_INV_Reduction",
                            formatter: "tickCross",
                            hozAlign: "center",
                            editor: true,
                            cellClick: function(e, cell) {
                                var currentValue = cell.getValue();
                                cell.setValue(!currentValue);
                            }
                        },
                        {
                            title: "Shipping Amount",
                            field: "Shipping_Amount",
                            hozAlign: "center",
                            editor: "input"
                        },
                        {
                            title: "Inbound Quantity",
                            field: "Inbound_Quantity",
                            hozAlign: "center",
                            editor: "input"
                        },

                        {
                            title: "FBA Send",
                            field: "FBA_Send",
                            hozAlign: "center",
                            formatter: "tickCross",
                            editor: true,
                            cellClick: function(e, cell) {
                                var currentValue = cell.getValue();
                                cell.setValue(!currentValue);
                            }
                        },

                        {
                            title: "L x W x H",
                            field: "Dimensions",
                            hozAlign: "center",
                            editor: "input"
                        },
                        {
                            title: "Jan",
                            field: "Jan",
                            hozAlign: "center"
                        },
                        {
                            title: "Feb",
                            field: "Feb",
                            hozAlign: "center"
                        },
                        {
                            title: "Mar",
                            field: "Mar",
                            hozAlign: "center"
                        },
                        {
                            title: "Apr",
                            field: "Apr",
                            hozAlign: "center"
                        },
                        {
                            title: "May",
                            field: "May",
                            hozAlign: "center"
                        },
                        {
                            title: "Jun",
                            field: "Jun",
                            hozAlign: "center"
                        },
                        {
                            title: "Jul",
                            field: "Jul",
                            hozAlign: "center"
                        },
                        {
                            title: "Aug",
                            field: "Aug",
                            hozAlign: "center"
                        },
                        {
                            title: "Sep",
                            field: "Sep",
                            hozAlign: "center"
                        },
                        {
                            title: "Oct",
                            field: "Oct",
                            hozAlign: "center"
                        },
                        {
                            title: "Nov",
                            field: "Nov",
                            hozAlign: "center"
                        },
                        {
                            title: "Dec",
                            field: "Dec",
                            hozAlign: "center"
                        }
                    ]
                });

                table.on('cellEdited', function(cell) {
                    var row = cell.getRow();
                    var data = row.getData();
                    var field = cell.getColumn().getField();
                    var value = cell.getValue();

                    if (field === 'Barcode' || field === 'Done' || field === 'Listed' || field === 'Live' ||
                        field === 'Dispatch_Date' || field === 'Weight' || field ===
                        'Quantity_in_each_box' ||
                        field === 'Total_quantity_sent' || field === 'Send_Cost' || field ===
                        'IN_Charges' ||
                        field === 'Warehouse_INV_Reduction' || field === 'Shipping_Amount' || field ===
                        'Inbound_Quantity' || field === 'FBA_Send' || field === 'Dimensions' || field ===
                        'FBA_Fee_Manual') {
                        $.ajax({
                            url: '/update-fba-manual-data',
                            method: 'POST',
                            data: {
                                sku: data.FBA_SKU,
                                field: field.toLowerCase(),
                                value: value,
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                console.log('Data saved successfully');
                            },
                            error: function(xhr) {
                                console.error('Error saving data');
                            }
                        });
                    }
                });

                // INV 0 and More than 0 Filter
                function applyFilters() {
                    const inventoryFilter = $('#inventory-filter').val();
                    const parentFilter = $('#parent-filter').val();
                    const pftFilter = $('#pft-filter').val();

                    table.clearFilter(true);

                    if (inventoryFilter === 'zero') {
                        table.addFilter('FBA_Quantity', '=', 0);
                    } else if (inventoryFilter === 'more') {
                        table.addFilter('FBA_Quantity', '>', 0);
                    }

                    if (parentFilter === 'hide') {
                        table.addFilter(function(data) {
                            return data.is_parent !== true;
                        });
                    }

                    if (pftFilter !== 'all') {
                        table.addFilter(function(data) {
                            const value = parseFloat(data['Pft%']);
                            if (isNaN(value)) return false;

                            switch (pftFilter) {
                                case '0-10':
                                    return value >= 0 && value <= 10;
                                case '11-14':
                                    return value >= 11 && value <= 14;
                                case '15-20':
                                    return value >= 15 && value <= 20;
                                case '21-49':
                                    return value >= 21 && value <= 49;
                                case '50+':
                                    return value >= 50;
                                default:
                                    return true;
                            }
                        });
                    }
                }

                $('#inventory-filter').on('change', function() {
                    applyFilters();
                });

                $('#parent-filter').on('change', function() {
                    applyFilters();
                });

                $('#pft-filter').on('change', function() {
                    applyFilters();
                });

                // AJAX Import Handler
                $('#importForm').on('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData();
                    const file = $('#csvFile')[0].files[0];

                    if (!file) return;

                    formData.append('file', file);
                    formData.append('_token', '{{ csrf_token() }}');

                    const uploadBtn = $('#uploadBtn');
                    uploadBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing...');

                    $.ajax({
                        url: '/fba-manual-import',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                showToast(response.message, 'success');
                                $('#importModal').modal('hide');
                                $('#importForm')[0].reset();
                                table.setData('/fba-data-json');
                            }
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON?.message || 'Import failed';
                            showToast(errorMsg, 'error');
                        },
                        complete: function() {
                            uploadBtn.prop('disabled', false).html(
                                '<i class="fa fa-upload"></i> Import');
                        }
                    });
                });
            });

            // LMP Modal Event Listener
            $(document).on('click', '.lmp-link', function(e) {
                e.preventDefault();
                const sku = $(this).data('sku');
                let data = $(this).data('lmp-data');
                console.log('SKU:', sku);
                console.log('Raw data:', data);
                try {
                    if (typeof data === 'string') {
                        data = JSON.parse(data);
                    }
                    console.log('Parsed data:', data);
                    openLmpModal(sku, data);
                } catch (error) {
                    console.error('Error parsing LMP data:', error);
                    alert('Error loading LMP data');
                }
            });

            // LMP Modal Function
            function openLmpModal(sku, data) {
                console.log('Opening modal for SKU:', sku, 'Data length:', data.length);
                console.log('lmpDataList exists:', $('#lmpDataList').length);
                $('#lmpSku').text(sku);
                let html = '';
                data.forEach(item => {
                    console.log('Item:', item);
                    html += `<div style="margin-bottom: 10px; border: 1px solid #ccc; padding: 10px;">
                    <strong>Price: $${item.price}</strong><br>
                    <a href="${item.link}" target="_blank">View Link</a>
                    ${item.image ? `<br><img src="${item.image}" alt="Product Image" style="max-width: 100px; max-height: 100px;">` : ''}
                </div>`;
                });
                console.log('Generated HTML:', html);
                $('#lmpDataList').html(html);
                $('#lmpModal').appendTo('body').modal('show');
                console.log('Modal shown');
            }
        </script>
    @endsection
