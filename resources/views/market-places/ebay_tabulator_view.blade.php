@extends('layouts.vertical', ['title' => 'eBay Pricing Decrease', 'sidenav' => 'condensed'])

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
        'page_title' => 'eBay Pricing Decrease',
        'sub_title' => 'eBay Pricing Decrease',
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

                    <select id="nrl-filter" class="form-select form-select-sm me-2"
                        style="width: auto; display: inline-block;">
                        <option value="all">All NRL</option>
                        <option value="REQ">REQ</option>
                        <option value="NRL">NRL</option>
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

                    <span class="me-3 px-3 py-1" style="background-color: #e3f2fd; border-radius: 5px;">
                        <strong>PFT%:</strong> <span id="pft-calc" class="text-primary fw-bold">0.00%</span>
                    </span>
                    <span class="me-3 px-3 py-1" style="background-color: #e8f5e9; border-radius: 5px;">
                        <strong>ROI%:</strong> <span id="roi-calc" class="text-success fw-bold">0.00%</span>
                    </span>

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
                ajaxSorting: false,
                layout: "fitDataStretch",
                pagination: true,
                paginationSize: 100,
                paginationCounter: "rows",
                columnCalcs: "both",
                initialSort: [{
                    column: "SCVR",
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
                        width: 80,
                        sorter: "number"
                    },
                    {
                        title: "OV<br> L30",
                        field: "L30",
                        hozAlign: "center",
                        width: 80,
                        sorter: "number"
                    },

                     {
                        title: "Dil",
                        field: "E Dil%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const INV = parseFloat(rowData.INV) || 0;
                            const OVL30 = parseFloat(rowData['L30']) || 0;
                            
                            if (INV === 0) return '<span style="color: #6c757d;">0%</span>';
                            
                            const dil = (OVL30 / INV) * 100;
                            let color = '';
                            
                            // Color logic from inc/dec page - getDilColor
                            if (dil < 16.66) color = '#a00211'; // red
                            else if (dil >= 16.66 && dil < 25) color = '#ffc107'; // yellow
                            else if (dil >= 25 && dil < 50) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink (50 and above)
                            
                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(dil)}%</span>`;
                        },
                        width: 60
                    },
                    {
                        title: "E <br> L30",
                        field: "eBay L30",
                        hozAlign: "center",
                        width: 60,
                        sorter: "number"
                    },

                    
                    // {
                    //     title: "eBay L60",
                    //     field: "eBay L60",
                    //     hozAlign: "center",
                    //     width: 100,
                    //     visible: false
                    // },
                    {
                        title: "S <br> CVR",
                        field: "SCVR",
                        hozAlign: "center",
                        sorter: function(a, b, aRow, bRow) {
                            const aData = aRow.getData();
                            const bData = bRow.getData();
                            
                            const aViews = parseFloat(aData.views || 0);
                            const bViews = parseFloat(bData.views || 0);
                            const aL30 = parseFloat(aData['eBay L30'] || 0);
                            const bL30 = parseFloat(bData['eBay L30'] || 0);
                            
                            const aValue = aViews === 0 ? 0 : (aL30 / aViews) * 100;
                            const bValue = bViews === 0 ? 0 : (bL30 / bViews) * 100;
                            
                            return aValue - bValue;
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const views = parseFloat(rowData.views || 0);
                            const l30 = parseFloat(rowData['eBay L30'] || 0);
                            
                            if (views === 0) {
                                return '<span style="color: #6c757d; font-weight: 600;">0.0%</span>';
                            }
                            
                            const scvrValue = (l30 / views) * 100;
                            let color = '';
                            
                            // getCvrColor logic from inc/dec page
                            if (scvrValue <= 4) color = '#a00211'; // red
                            else if (scvrValue > 4 && scvrValue <= 7) color = '#ffc107'; // yellow
                            else if (scvrValue > 7 && scvrValue <= 10) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${scvrValue.toFixed(1)}%</span>`;
                        },
                        width: 100
                    },

                    {
                        title: "View",
                        field: "views",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            let color = '';
                            
                            // getViewColor logic from inc/dec page
                            if (value >= 30) color = '#28a745'; // green
                            else color = '#a00211'; // red
                            
                            return `<span style="color: ${color}; font-weight: 600;">${Math.round(value)}</span>`;
                        },
                        width: 50
                    },


                     {
                        title: "NRL",
                        field: "NR",
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            let value = cell.getValue();
                            
                            // If empty or null, default to REQ
                            if (!value || value === '') {
                                value = 'REQ';
                            }
                            
                            let dotColor = value === 'NRL' ? '#dc3545' : '#28a745';
                            
                            return `<select class="form-select form-select-sm nr-select" 
                                style="border: 1px solid #ddd; text-align: center; cursor: pointer; padding: 4px;">
                                <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>🟢</option>
                                <option value="NRL" ${value === 'NRL' ? 'selected' : ''}>🔴</option>
                            </select>`;
                        },
                        cellClick: function(e, cell) {
                            e.stopPropagation();
                        },
                        width: 70
                    },
                   
                    {
                        title: "Prc",
                        field: "eBay Price",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            
                            if (value === 0) {
                                // Show $0.00 in red with red alert icon
                                return `<span style="color: #a00211; font-weight: 600;">$0.00 <i class="fas fa-exclamation-triangle" style="margin-left: 4px;"></i></span>`;
                            }
                            
                            // Normal price formatting
                            return `$${value.toFixed(2)}`;
                        },
                        width: 120
                    },

                      {
                        title: "GPFT <br>%",
                        field: "GPFT%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            let color = '';
                            
                            // getPftColor logic from inc/dec page (same as PFT)
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 100
                    },


                     {
                        title: "AD%",
                        field: "AD%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            return `${parseFloat(value).toFixed(0)}%`;
                        },
                        width: 100
                    },

                     {
                        title: "PFT <br>%",
                        field: "PFT %",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const gpft = parseFloat(rowData['GPFT%'] || 0);
                            const ad = parseFloat(rowData['AD%'] || 0);
                            
                            // PFT% = GPFT% - AD%
                            const percent = gpft - ad;
                            let color = '';
                            
                            // getPftColor logic from inc/dec page
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        bottomCalc: "avg",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseFloat(value).toFixed(2)}%</strong>`;
                        },
                        width: 70
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
                            
                            // getRoiColor logic from inc/dec page
                            if (percent < 50) color = '#a00211'; // red
                            else if (percent >= 50 && percent < 75) color = '#ffc107'; // yellow
                            else if (percent >= 75 && percent <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        bottomCalc: "avg",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>${parseFloat(value).toFixed(2)}%</strong>`;
                        },
                        width: 70
                    },
                  
                    
                    {
                        title: "LMP",
                        field: "lmp_price",
                        hozAlign: "center",
                        sorter: "number",
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
                    // {
                    //     title: "AD <br> Spend <br> L30",
                    //     field: "AD_Spend_L30",
                    //     hozAlign: "center",
                    //     formatter: "money",
                    //     formatterParams: {
                    //         decimal: ".",
                    //         thousand: ",",
                    //         symbol: "$",
                    //         precision: 2
                    //     },
                     
                    // },
                    // {
                    //     title: "AD Sales L30",
                    //     field: "AD_Sales_L30",
                    //     hozAlign: "center",
                    //     formatter: "money",
                    //     formatterParams: {
                    //         decimal: ".",
                    //         thousand: ",",
                    //         symbol: "$",
                    //         precision: 2
                    //     },
                    //     width: 130
                    // },
                    // {
                    //     title: "AD Units L30",
                    //     field: "AD_Units_L30",
                    //     hozAlign: "center",
                    //     width: 120
                    // },
                   
                    {
                        title: "TACOS <br> L30",
                        field: "TacosL30",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value) * 100;
                            let color = '';
                            
                            // getTacosColor logic from inc/dec page
                            if (percent <= 7) color = '#e83e8c'; // pink
                            else if (percent > 7 && percent <= 14) color = '#28a745'; // green
                            else if (percent > 14 && percent <= 21) color = '#ffc107'; // yellow
                            else color = '#a00211'; // red
                            
                            return `<span style="color: ${color}; font-weight: 600;">${parseFloat(value).toFixed(2)}%</span>`;
                        },
                        width: 80
                    },
                    // {
                    //     title: "Total Sales L30",
                    //     field: "T_Sale_l30",
                    //     hozAlign: "center",
                    //     formatter: "money",
                    //     formatterParams: {
                    //         decimal: ".",
                    //         thousand: ",",
                    //         symbol: "$",
                    //         precision: 2
                    //     },
                    //     width: 140
                    // },
                    // {
                    //     title: "Total Profit",
                    //     field: "Total_pft",
                    //     hozAlign: "center",
                    //     formatter: "money",
                    //     formatterParams: {
                    //         decimal: ".",
                    //         thousand: ",",
                    //         symbol: "$",
                    //         precision: 2
                    //     },
                    //     width: 130
                    // },
                   
                   
                    {
                        title: "S <br> PRC",
                        field: "SPRICE",
                        hozAlign: "center",
                        editor: "input",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const hasCustomSprice = rowData.has_custom_sprice;
                            
                            if (!value) return '';
                            
                            const formattedValue = `$${parseFloat(value).toFixed(2)}`;
                            
                            // If using default eBay Price (not custom), show in blue
                            if (hasCustomSprice === false) {
                                return `<span style="color: #0d6efd; font-weight: 500;">${formattedValue}</span>`;
                            }
                            
                            return formattedValue;
                        },
                        width: 120
                    },

                    {
                        title: "S <br> GPFT",
                        field: "SGPFT",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';
                            
                            let color = '';
                            // Same as GPFT% color logic
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 100
                    },
                    {
                        title: "S <br> PFT",
                        field: "SPFT",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const sgpft = parseFloat(rowData.SGPFT || 0);
                            const ad = parseFloat(rowData['AD%'] || 0);
                            
                            // SPFT = SGPFT - AD%
                            const percent = sgpft - ad;
                            if (isNaN(percent)) return '';
                            
                            let color = '';
                            // Same as PFT% color logic
                            if (percent < 10) color = '#a00211'; // red
                            else if (percent >= 10 && percent < 15) color = '#ffc107'; // yellow
                            else if (percent >= 15 && percent < 20) color = '#3591dc'; // blue
                            else if (percent >= 20 && percent <= 40) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 100
                    },
                    {
                        title: "SROI",
                        field: "SROI",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const sprice = parseFloat(rowData.SPRICE || 0);
                            const lp = parseFloat(rowData.LP_productmaster || 0);
                            const ship = parseFloat(rowData.Ship_productmaster || 0);
                            
                            if (lp === 0) return '';
                            
                            // SROI = ((SPRICE * 0.86 - ship - lp) / lp) * 100 (same as ROI% but with SPRICE)
                            const percent = ((sprice * 0.86 - ship - lp) / lp) * 100;
                            if (isNaN(percent)) return '';
                            
                            let color = '';
                            // Same as ROI% color logic
                            if (percent < 50) color = '#a00211'; // red
                            else if (percent >= 50 && percent < 75) color = '#ffc107'; // yellow
                            else if (percent >= 75 && percent <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 100
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
                    //     },
                    //     width: 100
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
                    //     },
                    //     width: 100
                    // }
                ]
            });

            // NR select change handler
            $(document).on('change', '.nr-select', function() {
                const $select = $(this);
                const value = $select.val();
                const cell = table.searchRows("(Child) sku", "=", $select.closest('.tabulator-cell').parent().find('[tabulator-field="(Child) sku"]').text())[0]?.getCell('NR');
                
                if (!cell) {
                    console.error('Could not find cell');
                    return;
                }
                
                const row = cell.getRow();
                const sku = row.getData()['(Child) sku'];
                
                // Update the row data
                row.update({NR: value});
                
                // Save to database
                $.ajax({
                    url: '/ebay/save-nr',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: sku,
                        nr: value
                    },
                    success: function(response) {
                        console.log('NRL saved successfully for', sku, 'value:', value);
                        const message = response.message || `NRL updated to "${value}" for ${sku}`;
                        showToast('success', message);
                    },
                    error: function(xhr) {
                        console.error('Failed to save NRL for', sku, 'Error:', xhr.responseText);
                        showToast('error', `Failed to save NRL for ${sku}`);
                    }
                });
            });

            table.on('cellEdited', function(cell) {
                var row = cell.getRow();
                var data = row.getData();
                var field = cell.getColumn().getField();
                var value = cell.getValue();

                if (field === 'SPRICE') {
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
                                SROI: response.sroi_percent,
                                SGPFT: response.sgpft_percent
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
                const nrlFilter = $('#nrl-filter').val();
                const pftFilter = $('#pft-filter').val();

                table.clearFilter(true);

                if (inventoryFilter === 'zero') {
                    table.addFilter('INV', '=', 0);
                } else if (inventoryFilter === 'more') {
                    table.addFilter('INV', '>', 0);
                }

                if (nrlFilter !== 'all') {
                    if (nrlFilter === 'REQ') {
                        // Show all data except NRL
                        table.addFilter(function(data) {
                            return data.NR !== 'NRL';
                        });
                    } else {
                        // Show only NRL
                        table.addFilter('NR', '=', nrlFilter);
                    }
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
                
                updateCalcValues();
            }

            $('#inventory-filter, #nrl-filter, #pft-filter').on('change', function() {
                applyFilters();
            });
            
            // Update PFT% and ROI% calc values
            function updateCalcValues() {
                const data = table.getData("active");
                let totalSales = 0;
                let totalProfit = 0;
                let sumLp = 0;
                
                data.forEach(row => {
                    const profit = parseFloat(row['Total_pft']) || 0;
                    const salesL30 = parseFloat(row['T_Sale_l30']) || 0;
                    // Only add if both values are > 0 (matching inc/dec page logic)
                    if (profit > 0 && salesL30 > 0) {
                        totalProfit += profit;
                        totalSales += salesL30;
                    }
                    sumLp += parseFloat(row['LP_productmaster']) || 0;
                });
                
                const avgPft = totalSales > 0 ? (totalProfit / totalSales) * 100 : 0;
                // ROI% = (total profit / sum of LP) * 100
                const avgRoi = sumLp > 0 ? (totalProfit / sumLp) * 100 : 0;
                
                $('#pft-calc').text(avgPft.toFixed(2) + '%');
                $('#roi-calc').text(avgRoi.toFixed(2) + '%');
            }

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
                updateCalcValues();
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
