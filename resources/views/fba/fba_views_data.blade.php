@extends('layouts.vertical', ['title' => 'FBA Pricing Data', 'sidenav' => 'condensed'])

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
        'page_title' => 'FBA pricing data',
        'sub_title' => 'FBA pricing data',
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
                        <option value="more" id="more-inventory-option" selected>More than 0</option>
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


    <div class="modal fade" id="yearsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Years Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>SKU:</strong> <span id="modalSKU"></span></p>
                    <p><strong>Year:</strong> <span id="modalYear"></span></p>

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
                            <i class="fa fa-info-circle"></i> CSV must have: SKU, Dimensions, Weight, Qty in each box,
                            Total
                            qty Sent, Total Send Cost, Inbound qty, Send cost
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
            const COLUMN_VIS_KEY = "fba_tabulator_column_visibility";

            $(document).ready(function() {
                const table = new Tabulator("#fba-table", {
                    ajaxURL: "/fba-data-json",
                    ajaxSorting: true,
                    layout: "fitData",
                    pagination: true,
                    paginationSize: 50,
                    initialSort: [{
                        column: "FBA_Dil",
                        dir: "asc"
                    }],
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
                            title: "Child <br> SKU",
                            field: "SKU",
                            headerFilter: "input",
                            headerFilterPlaceholder: "Search SKU...",
                            cssClass: "font-weight-bold",
                            tooltip: true,
                            frozen: true
                        },
                        {
                            title: "FBA <br>SKU",
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
                            title: "FBA <br> INV",
                            field: "FBA_Quantity",
                            hozAlign: "center"
                        },


                        // {
                        //     title: "L60  FBA",
                        //     field: "l60_units",
                        //     hozAlign: "center"
                        // },

                        {
                            title: "L30 <br> FBA",
                            field: "l30_units",
                            hozAlign: "center"
                        },

                        {
                            title: "FBA Dil",
                            field: "FBA_Dil",
                            sorter: "number",
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
                            title: "FBA <br> CVR",
                            field: "FBA_CVR",
                            sorter: function(a, b) {
                                const numA = parseFloat(a.replace(/<[^>]*>/g, '').replace('%', ''));
                                const numB = parseFloat(b.replace(/<[^>]*>/g, '').replace('%', ''));
                                return numA - numB;
                            },
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
                            title: "Inv<br> age",
                            field: "Inv_age",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = cell.getValue();
                                return `<span>${value || ''}</span> <i class="fa fa-eye" style="cursor:pointer; color:#3b7ddd; margin-left:5px;" onclick="openInvageModal('${value || ''}', '${cell.getRow().getData().SKU}')"></i>`;
                            }
                        },

                        {
                            title: "FBA<br> Price",
                            field: "FBA_Price",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const price = parseFloat(cell.getValue() || 0);
                                const lmp = parseFloat(cell.getRow().getData().lmp_1 || 0);
                                let color = '';
                                if (lmp > 0) {
                                    if (price > lmp) color = 'red';
                                    else if (price < lmp) color = 'darkgreen';
                                }
                                return `<span style="color:${color};">${price.toFixed(2)}</span>`;
                            }
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
                            title: "GPFT%",
                            field: "GPFT%",
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },

                        {
                            title: "GROI%",
                            field: "GROI%",
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },

                        {
                            title: "TACOS",
                            field: "Ads_Percentage",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue() || 0);
                                return value > 0 ? value.toFixed(0) + '%' : '100%';
                            }
                        },



                        // {
                        //     title: "Pft%",
                        //     field: "Pft%",
                        //     hozAlign: "center",
                        //     formatter: function(cell) {
                        //         return cell.getValue();
                        //     },
                        // },


                        {
                            title: "PRFT<br>%",
                            field: "TPFT",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue() || 0);
                                return value.toFixed(0) + '%';
                            },
                        },

                        {
                            title: "ROI%",
                            field: "ROI",
                            hozAlign: "center",
                             formatter: function(cell) {
                                const value = parseFloat(cell.getValue() || 0);
                                return value.toFixed(0) + '%';
                            },
                        },



                        {
                            title: "S <br> Price",
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

                                // Push price to Amazon
                                $.ajax({
                                    url: '/push-fba-price',
                                    method: 'POST',
                                    data: {
                                        sku: data.FBA_SKU,
                                        price: value,
                                        _token: '{{ csrf_token() }}'
                                    },
                                    success: function(result) {
                                        console.log('Price pushed to Amazon', result);
                                    },
                                    error: function(xhr) {
                                        console.error('Failed to push price', xhr.responseJSON);
                                    }
                                });
                            }
                        },

                        {
                            title: "SGPFT%",
                            field: "SGPFT%",
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },

                         {
                            title: "SGROI%",
                            field: "SGROI%",
                            hozAlign: "center",
                            formatter: function(cell) {
                                return cell.getValue();
                            },
                        },



                        {
                            title: "SPft%",
                            field: "SPFT",
                            hozAlign: "center",
                             formatter: function(cell) {
                                const value = parseFloat(cell.getValue() || 0);
                                return value.toFixed(0) + '%';
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
                            title: "LP",
                            field: "LP",
                            hozAlign: "center"
                        },

                        {
                            title: "FBA<br> Ship",
                            field: "FBA_Ship_Calculation",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const value = parseFloat(cell.getValue());
                                if (isNaN(value)) return '';
                                return value.toFixed(2);
                            }
                        },



                        // {
                        //     title: "Listed",
                        //     field: "Listed",
                        //     formatter: "tickCross",
                        //     hozAlign: "center",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },
                        // {
                        //     title: "Live",
                        //     field: "Live",
                        //     formatter: "tickCross",
                        //     hozAlign: "center",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },
                        {
                            title: "FBA<br> Fee",
                            field: "Fulfillment_Fee",
                            hozAlign: "center"
                        },

                        {
                            title: "FBA <br> Fee <br> M",
                            field: "FBA_Fee_Manual",
                            hozAlign: "center",
                            editable: function(cell) {
                                // Only editable if Fulfillment_Fee is 0
                                const fulfillmentFee = parseFloat(cell.getRow().getData()
                                    .Fulfillment_Fee) || 0;
                                return fulfillmentFee === 0;
                            },
                            editor: "input",
                            formatter: function(cell) {
                                const fulfillmentFee = parseFloat(cell.getRow().getData()
                                    .Fulfillment_Fee) || 0;
                                if (fulfillmentFee === 0) {
                                    cell.getElement().style.color = "#a80f8b";
                                } else {
                                    cell.getElement().style.color = "#999";
                                    cell.getElement().style.cursor = "not-allowed";
                                }
                                return cell.getValue();
                            }
                        },

                        ,

                        // {
                        //     title: "ASIN",
                        //     field: "ASIN"
                        // },
                        // {
                        //     title: "Barcode",
                        //     field: "Barcode",
                        //     editor: "list",
                        //     editorParams: {
                        //         values: ["", "M", "A"],
                        //         autocomplete: true,
                        //         allowEmpty: true,
                        //         listOnEmpty: true
                        //     },
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Done",
                        //     field: "Done",
                        //     formatter: "tickCross",
                        //     hozAlign: "center",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },


                        // {
                        //     title: "Dispatch Date",
                        //     field: "Dispatch_Date",
                        //     hozAlign: "center",
                        //     editor: "input"
                        // },
                        // {
                        //     title: "Weight",
                        //     field: "Weight",
                        //     hozAlign: "center",
                        //     editor: "input"
                        // },
                        // {
                        //     title: "Quantity Box",
                        //     field: "Quantity_in_each_box",
                        //     hozAlign: "center",
                        //     editor: "input"
                        // },
                        // {
                        //     title: "Sent Quantity",
                        //     field: "Total_quantity_sent",
                        //     hozAlign: "center",
                        //     editor: "input"
                        // },
                        {
                            title: "Send <br> Cost",
                            field: "Send_Cost",
                            hozAlign: "center",
                            editor: "input"
                        },
                        {
                            title: "Comm %",
                            field: "Commission_Percentage",
                            hozAlign: "center",
                            editor: "input"
                        },

                        // {
                        //     title: "Warehouse INV Reduction",
                        //     field: "Warehouse_INV_Reduction",
                        //     formatter: "tickCross",
                        //     hozAlign: "center",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },
                        // {
                        //     title: "Shipping Amount",
                        //     field: "Shipping_Amount",
                        //     hozAlign: "center",
                        //     editor: "input"
                        // },
                        // {
                        //     title: "Inbound Quantity",
                        //     field: "Inbound_Quantity",
                        //     hozAlign: "center",
                        //     editor: "input"
                        // },

                        // {
                        //     title: "FBA Send",
                        //     field: "FBA_Send",
                        //     hozAlign: "center",
                        //     formatter: "tickCross",
                        //     editor: true,
                        //     cellClick: function(e, cell) {
                        //         var currentValue = cell.getValue();
                        //         cell.setValue(!currentValue);
                        //     }
                        // },

                        // {
                        //     title: "L x W x H",
                        //     field: "Dimensions",
                        //     hozAlign: "center",
                        //     editor: "input"
                        // },

                        // {
                        //     title: "History",
                        //     field: "history",
                        //     hozAlign: "center",
                        //     formatter: function(cell) {
                        //         const value = cell.getValue();
                        //         return `<span>${value || ''}</span> <i class="fa fa-eye" style="cursor:pointer; color:#3b7ddd; margin-left:5px;" onclick="openYearsModal('${value || ''}', '${cell.getRow().getData().SKU}')"></i>`;
                        //     }
                        // },


                        // {
                        //     title: "Jan",
                        //     field: "Jan",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Feb",
                        //     field: "Feb",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Mar",
                        //     field: "Mar",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Apr",
                        //     field: "Apr",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "May",
                        //     field: "May",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Jun",
                        //     field: "Jun",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Jul",
                        //     field: "Jul",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Aug",
                        //     field: "Aug",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Sep",
                        //     field: "Sep",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Oct",
                        //     field: "Oct",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Nov",
                        //     field: "Nov",
                        //     hozAlign: "center"
                        // },
                        // {
                        //     title: "Dec",
                        //     field: "Dec",
                        //     hozAlign: "center"
                        // }
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
                        field === 'Total_quantity_sent' || field === 'Send_Cost' ||
                        field === 'Commission_Percentage' || field === 'Ads_Percentage' ||
                        field === 'Warehouse_INV_Reduction' || field === 'Shipping_Amount' || field ===
                        'Inbound_Quantity' || field === 'FBA_Send' || field === 'Dimensions' || field ===
                        'FBA_Fee_Manual') {
                        $.ajax({
                            url: '/update-fba-sku-manual-data',
                            method: 'POST',
                            data: {
                                sku: data.FBA_SKU,
                                field: field.toLowerCase(),
                                value: value,
                                fulfillment_fee: parseFloat(data.Fulfillment_Fee) || 0,
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(response) {
                                console.log('Data saved successfully');
                                if (response.updatedRow) {
                                    row.update(response.updatedRow);
                                }

                                // Tabulator ke internal real row data ko update kar do
                                row.update({
                                    [field.toUpperCase()]: value, // Tabulator display
                                    [field]: value // backend JSON key
                                });

                                let d = row.getData();

                                let PRICE = parseFloat(d.FBA_Price) || 0;
                                let LP = parseFloat(d.LP) || 0;
                                let COMMISSION_PERCENTAGE = parseFloat(d.Commission_Percentage) ||
                                    0;

                                // Get FBA_SHIP from response or existing row data
                                let FBA_SHIP = parseFloat(response.updatedRow?.FBA_SHIP ?? d
                                    .FBA_Ship_Calculation ?? 0);

                                console.log('GPFT Calculation:', {
                                    PRICE: PRICE,
                                    LP: LP,
                                    COMMISSION_PERCENTAGE: COMMISSION_PERCENTAGE,
                                    FBA_SHIP: FBA_SHIP,
                                    from_response: response.updatedRow?.FBA_SHIP,
                                    from_row: d.FBA_Ship_Calculation
                                });

                                // Initialize update object
                                let updateData = {
                                    FBA_Ship_Calculation: FBA_SHIP
                                };

                                // Calculate values based on which field was edited
                                if (field === 'Commission_Percentage') {
                                    // Only GPFT and TPFT depend on commission
                                    let GPFT = 0;
                                    if (PRICE > 0) {
                                        GPFT = ((PRICE * (1 - (COMMISSION_PERCENTAGE / 100 +
                                            0.05)) -
                                            LP - FBA_SHIP) / PRICE);
                                    }
                                    let TPFT = GPFT - parseFloat(d.Ads_Percentage || 0);

                                    updateData['GPFT%'] = `${(GPFT*100).toFixed(2)} %`;
                                    updateData['TPFT'] = TPFT.toFixed(0);

                                    console.log('Commission edited - Updated GPFT:', GPFT, 'TPFT:',
                                        TPFT);

                                } else if (field === 'Ads_Percentage') {
                                    // Only TPFT depends on ads percentage
                                    let TPFT = GPFT - parseFloat(d.Ads_Percentage || 0);
                                    updateData['TPFT'] = TPFT.toFixed(0);

                                    console.log('Ads edited - Updated TPFT:', TPFT);

                                } else {
                                    // Other fields affect PFT, ROI, GPFT, TPFT
                                    let PFT = 0;
                                    if (PRICE > 0) {
                                        PFT = (((PRICE * 0.66) - LP - FBA_SHIP) / PRICE);
                                    }

                                    let ROI = 0;
                                    if (LP > 0) {
                                        ROI = (((PRICE * 0.66) - LP - FBA_SHIP) / LP);
                                    }

                                    let GPFT = 0;
                                    if (PRICE > 0) {
                                        GPFT = ((PRICE * (1 - (COMMISSION_PERCENTAGE / 100 +
                                            0.05)) -
                                            LP - FBA_SHIP) / PRICE);
                                    }

                                    let TPFT = GPFT - parseFloat(d.Ads_Percentage || 0);

                                    updateData['Pft%'] = `${(PFT*100).toFixed(2)} %`;
                                    updateData['ROI%'] = (ROI * 100).toFixed(2);
                                    updateData['GPFT%'] = `${(GPFT*100).toFixed(2)} %`;
                                    updateData['TPFT'] = TPFT.toFixed(2);

                                    console.log('Other field edited - Updated all calculations');
                                }

                                row.update(updateData);
                            },
                            error: function(xhr) {
                                console.error('Error saving data');
                            }
                        });
                    }
                });

                function calculateRowValues(rowData) {
                    let PRICE = parseFloat(rowData.PRICE) || 0;
                    let LP = parseFloat(rowData.LP) || 0;

                    let fbaFee = parseFloat(rowData.FBA_Fee_Manual) || 0;
                    let sendCost = parseFloat(rowData.Send_Cost) || 0;

                    // FBA_SHIP calculation
                    let FBA_SHIP = fbaFee + sendCost;

                    // PFT calculation
                    let PFT = 0;
                    if (PRICE > 0) {
                        PFT = (((PRICE * 0.66) - LP - FBA_SHIP) / PRICE).toFixed(2);
                    }

                    return {
                        FBA_SHIP,
                        PFT
                    };
                }


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

                // Build Column Visibility Dropdown
                function buildColumnDropdown() {
                    const menu = document.getElementById("column-dropdown-menu");
                    menu.innerHTML = '';

                    // Fetch saved visibility from server
                    fetch('/fba-column-visibility', {
                            method: 'GET',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Content-Type': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(savedVisibility => {
                            const columns = table.getColumns().filter(col => col.getField());

                            columns.forEach(col => {
                                const field = col.getField();
                                const title = col.getDefinition().title || field;
                                const isVisible = savedVisibility[field] !== undefined ? savedVisibility[
                                    field] : col.isVisible();

                                const li = document.createElement('li');
                                li.classList.add('px-3', 'py-1');

                                const checkbox = document.createElement('input');
                                checkbox.type = 'checkbox';
                                checkbox.classList.add('form-check-input', 'me-2');
                                checkbox.checked = isVisible;
                                checkbox.dataset.field = field;

                                const label = document.createElement('label');
                                label.classList.add('form-check-label');
                                label.style.cursor = 'pointer';
                                label.textContent = title;

                                label.prepend(checkbox);
                                li.appendChild(label);
                                menu.appendChild(li);
                            });
                        })
                        .catch(error => {
                            console.error('Error fetching column visibility:', error);
                            // Fallback to default behavior
                            const columns = table.getColumns().filter(col => col.getField());
                            columns.forEach(col => {
                                const field = col.getField();
                                const title = col.getDefinition().title || field;
                                const isVisible = col.isVisible();

                                const li = document.createElement('li');
                                li.classList.add('px-3', 'py-1');

                                const checkbox = document.createElement('input');
                                checkbox.type = 'checkbox';
                                checkbox.classList.add('form-check-input', 'me-2');
                                checkbox.checked = isVisible;
                                checkbox.dataset.field = field;

                                const label = document.createElement('label');
                                label.classList.add('form-check-label');
                                label.style.cursor = 'pointer';
                                label.textContent = title;

                                label.prepend(checkbox);
                                li.appendChild(label);
                                menu.appendChild(li);
                            });
                        });
                }

                function saveColumnVisibilityToServer() {
                    const visibility = {};
                    table.getColumns().forEach(col => {
                        const field = col.getField();
                        if (field) {
                            visibility[field] = col.isVisible();
                        }
                    });

                    fetch('/fba-column-visibility', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                visibility: visibility
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                console.error('Failed to save column visibility');
                            }
                        })
                        .catch(error => {
                            console.error('Error saving column visibility:', error);
                        });
                }

                function applyColumnVisibilityFromServer() {
                    fetch('/fba-column-visibility', {
                            method: 'GET',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Content-Type': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(savedVisibility => {
                            table.getColumns().forEach(col => {
                                const field = col.getField();
                                if (field && savedVisibility[field] !== undefined) {
                                    if (savedVisibility[field]) {
                                        col.show();
                                    } else {
                                        col.hide();
                                    }
                                }
                            });
                        })
                        .catch(error => {
                            console.error('Error applying column visibility:', error);
                        });
                }

                // Wait for table to be built, then apply saved visibility and build dropdown
                table.on('tableBuilt', function() {
                    applyColumnVisibilityFromServer();
                    buildColumnDropdown();
                    applyFilters(); // Apply default filters on load
                });

                // Toggle column from dropdown
                document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                    if (e.target.type === 'checkbox') {
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
                        if (col.getField()) {
                            col.show();
                        }
                    });
                    buildColumnDropdown();
                    saveColumnVisibilityToServer();
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
