@extends('layouts.vertical', ['title' => 'Ebay Pricing Data', 'sidenav' => 'condensed'])

@section('css')
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
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Ebay Pricing Data',
        'sub_title' => 'Ebay Pricing Data',
    ])
    <div class="toast-container"></div>
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>eBay Data</h4>
                <div>
                    <select id="inventory-filter" class="form-select form-select-sm me-2"
                        style="width: auto; display: inline-block;">
                        <option value="all" selected>All Inventory</option>
                        <option value="zero">0 Inventory</option>
                        <option value="more" selected>More than 0</option>
                    </select>

                    <select id="nrl-filter" class="form-select form-select-sm me-2"
                        style="width: auto; display: inline-block;">
                        <option value="all">All Status</option>
                        <option value="REQ" selected>REQ Only</option>
                        <option value="NR">NR Only</option>
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

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <h6 class="mb-3">All Calculations Summary</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Top Metrics -->
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-badge" style="color: black; font-weight: bold;">Total PFT AMT: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-badge" style="color: black; font-weight: bold;">Total SALES AMT: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="avg-gpft-badge" style="color: black; font-weight: bold;">AVG GPFT: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-price-badge" style="color: black; font-weight: bold;">Avg Price: $0.00</span>
                        <span class="badge bg-danger fs-6 p-2" id="avg-cvr-badge" style="color: black; font-weight: bold;">Avg CVR: 0.00%</span>
                        <span class="badge bg-info fs-6 p-2" id="total-views-badge" style="color: black; font-weight: bold;">Views: 0</span>
                        <span class="badge bg-secondary fs-6 p-2" id="cvr-badge" style="color: black; font-weight: bold;">CVR: 0.00%</span>
                        
                        <!-- eBay Metrics -->
                        <span class="badge bg-primary fs-6 p-2" id="total-fba-inv-badge" style="color: black; font-weight: bold;">Total eBay INV: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="total-fba-l30-badge" style="color: black; font-weight: bold;">Total eBay L30: 0</span>
                        <span class="badge bg-warning fs-6 p-2" id="avg-dil-percent-badge" style="color: black; font-weight: bold;">DIL %: 0%</span>
                        
                        <!-- Financial Metrics -->
                        <span class="badge bg-danger fs-6 p-2" id="total-tcos-badge" style="color: black; font-weight: bold;">Total TCOS: 0%</span>
                        <span class="badge bg-warning fs-6 p-2" id="total-spend-l30-badge" style="color: black; font-weight: bold;">Total Spend L30: $0.00</span>
                        <span class="badge bg-success fs-6 p-2" id="total-pft-amt-summary-badge" style="color: black; font-weight: bold;">Total PFT AMT: $0.00</span>
                        <span class="badge bg-primary fs-6 p-2" id="total-sales-amt-summary-badge" style="color: black; font-weight: bold;">Total SALES AMT: $0.00</span>
                        <span class="badge bg-info fs-6 p-2" id="total-cogs-amt-badge" style="color: black; font-weight: bold;">COGS AMT: $0.00</span>
                        <span class="badge bg-secondary fs-6 p-2" id="roi-percent-badge" style="color: black; font-weight: bold;">ROI %: 0%</span>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="ebay-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="sku-search" class="form-control" placeholder="Search SKU...">
                    </div>
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
                        width: 250,
                        formatter: function(cell) {
                            const sku = cell.getValue();
                            return `
                                <span>${sku}</span>
                                <i class="fa fa-copy text-secondary copy-sku-btn" 
                                   style="cursor: pointer; margin-left: 8px; font-size: 14px;" 
                                   data-sku="${sku}"
                                   title="Copy SKU"></i>
                            `;
                        }
                    },
                    
                    {
                        title: "INV",
                        field: "INV",
                        hozAlign: "center",
                        width: 50,
                        sorter: "number"
                    },
                    {
                        title: "E Stock",
                        field: "eBay Stock",
                        hozAlign: "center",
                        width: 60,
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            if (value === 0) {
                                return '<span style="color: #dc3545; font-weight: 600;">0</span>';
                            }
                            return `<span style="font-weight: 600;">${value}</span>`;
                        }
                    },
                    {
                        title: "Missing",
                        field: "Missing",
                        hozAlign: "center",
                        width: 70,
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const itemId = rowData['eBay_item_id'];
                            if (!itemId || itemId === null || itemId === '') {
                                return '<span style="color: #dc3545; font-weight: bold; background-color: #ffe6e6; padding: 2px 6px; border-radius: 3px;">M</span>';
                            }
                            return '';
                        }
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        hozAlign: "center",
                        width: 50,
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
                        width: 50
                    },
                    {
                        title: "E L30",
                        field: "eBay L30",
                        hozAlign: "center",
                        width: 30,
                        sorter: "number"
                    },
                    {
                        title: "MAP",
                        field: "MAP",
                        hozAlign: "center",
                        width: 90,
                        headerFilter: "select",
                        headerFilterParams: {
                            values: {"": "All", "MP": "MP", "N MP": "N MP (Mismatch)"}
                        },
                        formatter: function(cell) {
                            const rowData = cell.getRow().getData();
                            const itemId = rowData['eBay_item_id'];
                            if (!itemId || itemId === null || itemId === '') {
                                return '';
                            }
                            const ebayStock = parseFloat(rowData['eBay Stock']) || 0;
                            const inv = parseFloat(rowData['INV']) || 0;
                            if (inv > 0 && ebayStock === 0) {
                                return `<span style=\"color: #dc3545; font-weight: bold;\">N MP<br>(${inv})</span>`;
                            }
                            if (inv > 0 && ebayStock > 0) {
                                if (inv === ebayStock) {
                                    return '<span style="color: #28a745; font-weight: bold;">MP</span>';
                                } else {
                                    const diff = inv - ebayStock;
                                    const sign = diff > 0 ? '+' : '';
                                    return `<span style=\"color: #dc3545; font-weight: bold;\">N MP<br>(${sign}${diff})</span>`;
                                }
                            }
                            return '';
                        }
                    },
                    
                    // {
                    //     title: "eBay L60",
                    //     field: "eBay L60",
                    //     hozAlign: "center",
                    //     width: 100,
                    //     visible: false
                    // },
                    {
                        title: "S CVR",
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
                        width: 60
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
                        title: "NR/REQ",
                        field: "nr_req",
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            let value = cell.getValue();
                            const rowData = cell.getRow().getData();
                            const inv = parseFloat(rowData['INV']) || 0;
                            const isParent = rowData['Parent'] && rowData['Parent'].startsWith('PARENT');
                            
                            // Don't show dropdown for parent rows
                            if (isParent) {
                                return '';
                            }
                            
                            // Default to REQ if not set
                            if (!value || value === '') {
                                value = 'REQ';
                            }
                            
                            let bgColor = '#f8f9fa';
                            let textColor = '#000';
                            
                            if (value === 'REQ') {
                                bgColor = '#28a745';
                                textColor = 'white';
                            } else if (value === 'NR') {
                                bgColor = '#dc3545';
                                textColor = 'white';
                            }
                            
                            return `<select class="form-select form-select-sm nr-req-dropdown" 
                                style="background-color: ${bgColor}; color: ${textColor}; border: 1px solid #ddd; text-align: center; cursor: pointer; padding: 4px;">
                                <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>REQ</option>
                                <option value="NR" ${value === 'NR' ? 'selected' : ''}>NR</option>
                            </select>`;
                        },
                        cellClick: function(e, cell) {
                            e.stopPropagation();
                        },
                        width: 90
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
                        width: 70
                    },

                      {
                        title: "GPFT %",
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
                        width: 50
                    },


                     {
                        title: "AD%",
                        field: "AD%",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            
                            const rowData = cell.getRow().getData();
                            const kwSpend = parseFloat(rowData['kw_spend_L30'] || 0);
                            const adPercent = parseFloat(value || 0);
                            
                            // If KW ads spend > 0 but AD% is 0, show red alert
                            if (kwSpend > 0 && adPercent === 0) {
                                return `<span style="color: #dc3545; font-weight: 600;">100%</span>`;
                            }
                            
                            return `${parseFloat(value).toFixed(0)}%`;
                        },
                        width: 55
                    },

                     {
                        title: "PFT %",
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
                        width: 50
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
                        width: 65
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
                        width: 70
                    
                    },
                    // {
                    //     title: "AD <br> Spend <br> L30",
                    //     field: "AD_Spend_L30",
                    //     hozAlign: "center",
                    //     sorter: "number",
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
                    //     title: "AD Sales L30",
                    //     field: "AD_Sales_L30",
                    //     hozAlign: "center",
                    //     sorter: "number",
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
                    //     sorter: "number",
                    //     width: 120
                    // },
                   
                    // {
                    //     title: "TACOS <br> L30",
                    //     field: "TacosL30",
                    //     hozAlign: "center",
                    //     sorter: "number",
                    //     formatter: function(cell) {
                    //         const value = cell.getValue();
                    //         if (value === null || value === undefined) return '';
                    //         const percent = parseFloat(value) * 100;
                    //         let color = '';
                            
                    //         // getTacosColor logic from inc/dec page
                    //         if (percent <= 7) color = '#e83e8c'; // pink
                    //         else if (percent > 7 && percent <= 14) color = '#28a745'; // green
                    //         else if (percent > 14 && percent <= 21) color = '#ffc107'; // yellow
                    //         else color = '#a00211'; // red
                            
                    //         return `<span style="color: ${color}; font-weight: 600;">${parseFloat(value).toFixed(2)}%</span>`;
                    //     },
                    //     width: 80
                    // },
                    // {
                    //     title: "Total Sales L30",
                    //     field: "T_Sale_l30",
                    //     hozAlign: "center",
                    //     sorter: "number",
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
                    //     sorter: "number",
                    //     formatter: "money",
                    //     formatterParams: {
                    //         decimal: ".",
                    //         thousand: ",",
                    //         symbol: "$",
                    //         precision: 2
                    //     },
                    //     width: 130
                    // },
                    //     width: 130
                    // },
                   
                   
                    {
                        title: "S PRC",
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
                        width: 80
                    },

                    {
                        title: "S GPFT",
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
                        width: 80
                    },
                    {
                        title: "S PFT",
                        field: "SPFT",
                        hozAlign: "center",
                        sorter: "number",
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
                        width: 80
                    },
                    {
                        title: "SROI",
                        field: "SROI",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            if (value === null || value === undefined) return '';
                            const percent = parseFloat(value);
                            if (isNaN(percent)) return '';
                            
                            let color = '';
                            // Same as ROI% color logic
                            if (percent < 50) color = '#a00211'; // red
                            else if (percent >= 50 && percent < 75) color = '#ffc107'; // yellow
                            else if (percent >= 75 && percent <= 125) color = '#28a745'; // green
                            else color = '#e83e8c'; // pink
                            
                            return `<span style="color: ${color}; font-weight: 600;">${percent.toFixed(0)}%</span>`;
                        },
                        width: 80
                    },


                        {
                        title: "SPEND L30",
                        field: "AD_Spend_L30",
                        hozAlign: "center",
                        sorter: "number",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>$${value.toFixed(2)}</span>
                                <i class="fa fa-info-circle text-primary toggle-spendL30-btn" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${parseFloat(value).toFixed(2)}</strong>`;
                        },
                        width: 90
                    },

                    {
                        title: "KW SPEND L30",
                        field: "kw_spend_L30",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return `$${value.toFixed(2)}`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${parseFloat(value).toFixed(2)}</strong>`;
                        },
                        width: 100
                    },

                    {
                        title: "PMT SPEND L30",
                        field: "pmt_spend_L30",
                        hozAlign: "center",
                        sorter: "number",
                        visible: false,
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            return `$${value.toFixed(2)}`;
                        },
                        bottomCalc: "sum",
                        bottomCalcFormatter: function(cell) {
                            const value = cell.getValue();
                            return `<strong>$${parseFloat(value).toFixed(2)}</strong>`;
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

            // SKU Search functionality
            $('#sku-search').on('keyup', function() {
                const value = $(this).val();
                table.setFilter("(Child) sku", "like", value);
            });

            // NR/REQ dropdown change handler
            $(document).on('change', '.nr-req-dropdown', function() {
                const $select = $(this);
                const value = $select.val();
                
                // Update dropdown colors
                if (value === 'REQ') {
                    $select.css('background-color', '#28a745').css('color', 'white');
                } else if (value === 'NR') {
                    $select.css('background-color', '#dc3545').css('color', 'white');
                } else {
                    $select.css('background-color', '#f8f9fa').css('color', '#000');
                }
                
                // Find the row and get SKU
                const $cell = $select.closest('.tabulator-cell');
                const row = table.getRow($cell.closest('.tabulator-row')[0]);
                
                if (!row) {
                    console.error('Could not find row');
                    return;
                }
                
                const sku = row.getData()['(Child) sku'];
                
                // Update the row data
                row.update({nr_req: value});
                
                // Save to database using listing_ebay endpoint
                $.ajax({
                    url: '/listing_ebay/save-status',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        sku: sku,
                        nr_req: value
                    },
                    success: function(response) {
                        console.log('NR/REQ saved successfully for', sku, 'value:', value);
                        const message = value === 'REQ' ? 'REQ updated' : (value === 'NR' ? 'NR updated' : 'Status cleared');
                        showToast('success', message);
                    },
                    error: function(xhr) {
                        console.error('Failed to save NR/REQ for', sku, 'Error:', xhr.responseText);
                        showToast('error', `Failed to save NR/REQ for ${sku}`);
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
                    table.addFilter(function(data) {
                        if (nrlFilter === 'REQ') {
                            return data.nr_req === 'REQ';
                        } else if (nrlFilter === 'NR') {
                            return data.nr_req === 'NR';
                        }
                        return true;
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
                
                updateCalcValues();
                updateSummary();
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

            // Update summary badges for INV > 0
            function updateSummary() {
                const data = table.getData();
                let totalTcos = 0;
                let totalSpendL30 = 0;
                let totalPftAmt = 0;
                let totalSalesAmt = 0;
                let totalLpAmt = 0;
                let totalFbaInv = 0;
                let totalFbaL30 = 0;
                let totalDilPercent = 0;
                let dilCount = 0;

                data.forEach(row => {
                    totalTcos += parseFloat(row['AD%'] || 0);
                    totalSpendL30 += parseFloat(row['AD_Spend_L30'] || 0);
                    totalPftAmt += parseFloat(row['Total_pft'] || 0);
                    totalSalesAmt += parseFloat(row['T_Sale_l30'] || 0);
                    totalLpAmt += parseFloat(row['LP_productmaster'] || 0) * parseFloat(row['eBay L30'] || 0);
                    totalFbaInv += parseFloat(row.INV || 0);
                    totalFbaL30 += parseFloat(row['eBay L30'] || 0);
                    
                    const dil = parseFloat(row['E Dil%'] || 0);
                    if (!isNaN(dil)) {
                        totalDilPercent += dil;
                        dilCount++;
                    }
                });

                let totalWeightedPrice = 0;
                let totalL30 = 0;
                data.forEach(row => {
                    const price = parseFloat(row['eBay Price'] || 0);
                    const l30 = parseFloat(row['eBay L30'] || 0);
                    totalWeightedPrice += price * l30;
                    totalL30 += l30;
                });
                const avgPrice = totalL30 > 0 ? totalWeightedPrice / totalL30 : 0;
                $('#avg-price-badge').text('Avg Price: $' + Math.round(avgPrice));

                let totalViews = 0;
                data.forEach(row => {
                    totalViews += parseFloat(row.views || 0);
                });
                const avgCVR = totalViews > 0 ? (totalL30 / totalViews * 100) : 0;
                $('#avg-cvr-badge').text('Avg CVR: ' + avgCVR.toFixed(1) + '%');
                $('#total-views-badge').text('Views: ' + totalViews.toLocaleString());
                $('#cvr-badge').text('CVR: ' + avgCVR.toFixed(2) + '%');
                

                $('#total-tcos-badge').text('Total TCOS: ' + Math.round(totalTcos) + '%');
                $('#total-spend-l30-badge').text('Total Spend L30: $' + Math.round(totalSpendL30));
                $('#total-pft-amt-summary-badge').text('Total PFT AMT: $' + Math.round(totalPftAmt));
                $('#total-sales-amt-summary-badge').text('Total SALES AMT: $' + Math.round(totalSalesAmt));
                $('#total-cogs-amt-badge').text('COGS AMT: $' + Math.round(totalLpAmt));
                const roiPercent = totalLpAmt > 0 ? Math.round((totalPftAmt / totalLpAmt) * 100) : 0;
                $('#roi-percent-badge').text('ROI %: ' + roiPercent + '%');
                $('#total-fba-inv-badge').text('Total eBay INV: ' + Math.round(totalFbaInv).toLocaleString());
                $('#total-fba-l30-badge').text('Total eBay L30: ' + Math.round(totalFbaL30).toLocaleString());
                const avgDilPercent = dilCount > 0 ? (totalDilPercent / dilCount) : 0;
                $('#avg-dil-percent-badge').text('DIL %: ' + Math.round(avgDilPercent) + '%');
                $('#total-pft-amt').text('$' + Math.round(totalPftAmt));
                $('#total-pft-amt-badge').text('Total PFT AMT: $' + Math.round(totalPftAmt));
                $('#total-sales-amt').text('$' + Math.round(totalSalesAmt));
                $('#total-sales-amt-badge').text('Total SALES AMT: $' + Math.round(totalSalesAmt));
                const avgGpft = totalSalesAmt > 0 ? Math.round((totalPftAmt / totalSalesAmt) * 100) : 0;
                $('#avg-gpft-badge').text('AVG GPFT: ' + avgGpft + '%');
                $('#avg-gpft-summary').text(avgGpft + '%');
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
                updateSummary();
                // Initialize Bootstrap tooltips for dynamically created elements
                setTimeout(function() {
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                        new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }, 100);
            });

            // Also initialize tooltips when table is rendered
            table.on('renderComplete', function() {
                setTimeout(function() {
                    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
                        new bootstrap.Tooltip(tooltipTriggerEl);
                    });
                }, 100);
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

            // Toggle SPEND L30 breakdown columns
            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-spendL30-btn")) {
                    let colsToToggle = ["kw_spend_L30", "pmt_spend_L30"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                    
                    // Update column visibility in cache
                    saveColumnVisibilityToServer();
                    buildColumnDropdown();
                }

                // Copy SKU to clipboard
                if (e.target.classList.contains("copy-sku-btn")) {
                    const sku = e.target.getAttribute("data-sku");
                    
                    // Copy to clipboard
                    navigator.clipboard.writeText(sku).then(function() {
                        showToast('success', `SKU "${sku}" copied to clipboard!`);
                    }).catch(function(err) {
                        // Fallback for older browsers
                        const textarea = document.createElement('textarea');
                        textarea.value = sku;
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textarea);
                        showToast('success', `SKU "${sku}" copied to clipboard!`);
                    });
                }
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
