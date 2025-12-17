@extends('layouts.vertical', ['title' => 'Forecast Analysis'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-footer {
            background: #f4f7fa;
            border-top: 1px solid #262626;
            font-size: 1rem;
            color: #4b5563;
            padding: 5px;
            height: 70px;
        }

        /* Pagination styling */
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            padding: 8px 16px;
            margin: 0 4px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #e0eaff;
            color: #2563eb;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: white;
        }

        #image-hover-preview {
            transition: opacity 0.2s ease;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Forecast Analysis',
        'sub_title' => 'Forecast Analysis',
    ])

    <div class="alert alert-warning mb-3">
        <strong>Items with 0 Inventory:</strong> <span id="zero_inv_count" class="fw-bold">0</span>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="mb-3 d-flex align-items-center gap-3">
                        <!-- Play/Pause Controls -->
                        <div class="d-flex align-items-center me-3">
                            <div class="btn-group time-navigation-group" role="group" aria-label="Parent navigation">
                                <button id="play-backward" class="btn btn-light rounded-circle shadow-sm me-1"
                                    style="width: 36px; height: 36px; padding: 6px;">
                                    <i class="fas fa-step-backward"></i>
                                </button>

                                <button id="play-pause" class="btn btn-light rounded-circle shadow-sm me-1"
                                    style="width: 36px; height: 36px; padding: 6px; display: none;">
                                    <i class="fas fa-pause"></i>
                                </button>

                                <button id="play-auto" class="btn btn-primary rounded-circle shadow-sm me-1"
                                    style="width: 36px; height: 36px; padding: 6px;">
                                    <i class="fas fa-play"></i>
                                </button>

                                <button id="play-forward" class="btn btn-light rounded-circle shadow-sm"
                                    style="width: 36px; height: 36px; padding: 6px;">
                                    <i class="fas fa-step-forward"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <!-- Column Management -->
                            <div class="dropdown">
                                <button class="btn btn-sm btn-primary dropdown-toggle d-flex align-items-center gap-1"
                                    type="button" id="hide-column-dropdown" data-bs-toggle="dropdown">
                                    <i class="bi bi-grid-3x3-gap-fill"></i>
                                     Column
                                </button>
                                <ul class="dropdown-menu p-3 shadow-lg border rounded-3" id="column-dropdown-menu"
                                    style="max-height: 300px; overflow-y: auto; min-width: 250px;">
                                    <li class="fw-semibold text-muted mb-2">Toggle Columns</li>
                                </ul>
                            </div>

                            <!-- 2 ORDER Color Filter -->
                            <div class="dropdown">
                                <button class="btn btn-sm btn-warning dropdown-toggle d-flex align-items-center gap-1 fw-semibold text-dark"
                                    type="button" id="order-color-filter-dropdown" data-bs-toggle="dropdown">
                                    <i class="bi bi-funnel-fill"></i>
                                    2 ORDER
                                </button>
                                <ul class="dropdown-menu p-2 shadow-lg border rounded-3">
                                    <li><button class="dropdown-item" data-filter="">All</button></li>
                                    <li><button class="dropdown-item" data-filter="yellow">🟡 Yellow</button></li>
                                </ul>
                            </div>

                            <!-- Yellow Count -->
                            <div id="yellow-count-container" class="d-none px-2 btn btn-sm rounded-2 shadow-sm border border-warning bg-warning">
                                <div class="d-flex align-items-center gap-1">
                                    <i class="bi bi-star-fill text-dark"></i>
                                    <span id="yellow-count-box" class="fw-semibold text-dark">App Pending: 0</span>
                                </div>
                            </div>


                            <!-- Show All Columns -->
                            <button id="show-all-columns-btn"
                                class="btn btn-sm btn-outline-success d-flex align-items-center gap-1">
                                <i class="bi bi-eye"></i>
                                Show All
                            </button>

                            <!-- Toggle NR -->
                            <button id="toggle-nr-rows" class="btn btn-sm btn-outline-secondary">
                                Show NR
                            </button>

                            <!-- Row Type Filter -->
                            <select id="row-data-type" class="form-select-sm border border-primary" style="width: 150px;">
                                <option value="all">🔁 Show All</option>
                                <option value="sku">🔹 SKU (Child)</option>
                                <option value="parent">🔸 Parent</option>
                            </select>

                            <!-- Stage Filter -->
                            <select id="stage-filter" class="form-select-sm border border-primary" style="width: 150px;">
                                <option value="">All Stages</option>
                                <option value="to_order_analysis">2 Order</option>
                                <option value="mip">MIP</option>
                                <option value="r2s">R2S</option>
                                <option value="transit">Transit</option>
                            </select>

                            <!-- NRP Filter -->
                            <select id="nrp-filter" class="form-select-sm border border-primary" style="width: 150px;">
                                <option value="">All NRP</option>
                                <option value="NR">2BDC</option>
                                <option value="REQ">REQ</option>
                                <option value="LATER">LATER</option>
                            </select>

                            <button id="total-transit" class="btn btn-sm btn-info">
                                 Transit
                            </button>

                            <button id="show-zero-inv" class="btn btn-sm btn-danger">
                                 0 INV
                            </button>

                            <button id="restock_needed" class="btn btn-sm btn-warning fw-semibold text-dark">
                                Zero Stock: <span id = "total_restock" class="fw-semibold text-dark">0</span>
                            </button>

                            <button id="total_msl_c" class="btn btn-sm btn-success fw-semibold text-dark">
                                 MSL_LP: $<span id="total_msl_c_value" class="fw-semibold text-dark">0.00</span>
                            </button>

                            <button id="total_msl_sp" class="btn btn-sm btn-primary fw-semibold text-dark">
                                 MSL_SP: $<span id="total_msl_sp_value" class="fw-semibold text-dark">0</span>
                            </button>


                            <button id="total_inv_value" class="btn btn-sm btn-info fw-semibold text-dark">
                                 INV Val: $<span id="total_inv_value_display" class="fw-semibold text-dark">0</span>
                            </button>

                            <button id="total_lp_value" class="btn btn-sm btn-warning fw-semibold text-dark">
                                 LP Val: $<span id="total_lp_value_display" class="fw-semibold text-dark">0</span>
                            </button>

                            {{-- <button id="total_restock_msl" class="btn btn-sm btn-dark fw-semibold text-white">
                                 Restock MSL: $<span id="total_restock_msl_value" class="fw-semibold text-white">0.00</span>
                            </button> --}}

                            <button id="total_minimal_msl" class="btn btn-sm btn-secondary fw-semibold text-white">
                                Missing Sales: $<span id="total_minimal_msl_value" class="fw-semibold text-white">0</span>
                            </button>

                            {{-- <button id="sum_restock_shopify_price" class="btn btn-sm btn-info fw-semibold text-dark">
                                Sum Restock Shopify Price: $<span id="sum_restock_shopify_price_value" class="fw-semibold text-dark">0</span>
                            </button> --}}

                            {{-- <button id="total_restock_msl_lp" class="btn btn-sm btn-warning fw-semibold text-dark">
                                 Restock MSL LP: $<span id="total_restock_msl_lp_value" class="fw-semibold text-dark">0</span>
                            </button> --}}

                            <button id="total_mip_value" class="btn btn-sm btn-warning fw-semibold text-dark">
                                 MIP Val: $<span id="total_mip_value_display" class="fw-semibold text-dark">0</span>
                            </button>

                            <button id="total_r2s_value" class="btn btn-sm btn-warning fw-semibold text-dark">
                                 R2S Val: $<span id="total_r2s_value_display" class="fw-semibold text-dark">0</span>
                            </button>

                            <button id="total_transit_value" class="btn btn-sm btn-secondary fw-semibold text-dark">
                                 Trn Val: $<span id="total_transit_value_display" class="fw-semibold text-dark">0</span>
                            </button>

                            <!-- Navigation Buttons -->
                            <a href="{{ route('to.order.analysis') }}" target="_blank" class="btn btn-sm btn-primary fw-semibold text-dark" style="text-decoration: none;">
                                2 Order
                            </a>

                            <a href="{{ route('mfrg.in.progress') }}" target="_blank" class="btn btn-sm btn-success fw-semibold text-dark" style="text-decoration: none;">
                                MIP
                            </a>

                            <a href="{{ route('ready.to.ship') }}" target="_blank" class="btn btn-sm btn-info fw-semibold text-dark" style="text-decoration: none;">
                                R2S
                            </a>

                            <a href="{{ route('transit.container.details') }}" target="_blank" class="btn btn-sm btn-secondary fw-semibold text-dark" style="text-decoration: none;">
                                Transit
                            </a>
                        </div>
                    </div>

                    <div id="forecast-table"></div>
                </div>
            </div>
        </div>
    </div>
    {{-- month view modal --}}
    <div class="modal fade" id="monthModal" tabindex="-1" aria-labelledby="monthModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0 shadow-sm">
                <div class="modal-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="modal-title mb-0">MONTH VIEW <span id="month-view-sku" class="ms-1"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body" id="monthModalBody">
                    <div class="d-flex justify-content-between gap-2 flex-nowrap w-100 px-3" id="monthCardWrapper"
                        style="overflow-x: auto;">
                        <!-- Month cards inserted here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Metric View Modal -->
    <div class="modal fade" id="metricModal" tabindex="-1" aria-labelledby="metricModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold" id="metricModalLabel">METRIC VIEW</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="metricCardWrapper" class="d-flex justify-content-between gap-2 flex-nowrap w-100 px-3">
                        <!-- Metric cards will be appended here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- link edit modal --}}
    <div class="modal fade" id="linkEditModal" tabindex="-1" aria-labelledby="linkEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg shadow-none">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title" id="linkEditModalLabel">
                        <i class="fas fa-link me-2"></i>
                        <span>Edit Link</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label id="linkLabel" class="form-label fw-lg mb-2">Link URL:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-link text-primary"></i>
                            </span>
                            <input type="url" class="form-control form-control-lg border-start-0 ps-2"
                                id="linkEditInput" placeholder="Enter URL here..." autocomplete="off"
                                spellcheck="false">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-primary" id="saveLinkBtn">
                        <i class="fas fa-check me-1"></i>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Notes Modal --}}
    <div class="modal fade" id="editNotesModal" tabindex="-1" role="dialog" aria-labelledby="editNotesLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg shadow-none" role="document">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title" id="editNotesLabel">
                        <i class="fas fa-edit me-2"></i> Edit Notes
                    </h5>
                    <button type="button" class="close text-white custom-close" data-bs-dismiss="modal"
                        aria-label="Close" style="font-size:25px; background-color: transparent; border: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <textarea id="notesInput" class="form-control form-control-lg shadow-none mb-4" rows="3"
                        placeholder="Type your note here..." style="resize: vertical;"></textarea>
                    <div class="text-end">
                        <button type="button" class="btn btn-primary" id="saveNotesBtn">
                            <i class="fas fa-save me-2"></i> Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="scouthProductsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Scout Products View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- dynamic content here -->
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.body.style.zoom = "95%";
        const getDilColor = (value) => {
            const percent = parseFloat(value) * 100;
            if (percent < 16.66) return 'red';
            if (percent >= 16.66 && percent < 25) return 'yellow';
            if (percent >= 25 && percent < 50) return 'green';
            return 'pink';
        };

        const getPftColor = (value) => {
            const percent = parseFloat(value) * 100;
            if (percent < 10) return 'red';
            if (percent >= 10 && percent < 15) return 'yellow';
            if (percent >= 15 && percent < 20) return 'blue';
            if (percent >= 20 && percent <= 40) return 'green';
            return 'pink';
        };

        const getRoiColor = (value) => {
            const percent = parseFloat(value) * 100;
            if (percent >= 0 && percent < 50) return 'red';
            if (percent >= 50 && percent < 75) return 'yellow';
            if (percent >= 75 && percent <= 100) return 'green';
            return 'pink';
        };

        //global variables for play btn
        let groupedSkuData = {};

        const table = new Tabulator("#forecast-table", {
            ajaxURL: "/forecast-analysis-data-view",
            ajaxConfig: "GET",
            layout: "fitDataFill",
            pagination: true,
            paginationSize: 200,
            paginationCounter: "rows",
            movableColumns: false,
            resizableColumns: true,
            height: "650px",
            index: "SKU",
            rowFormatter: function(row) {
                const data = row.getData();
                const sku = data["SKU"] || '';

                if (sku.toUpperCase().includes("PARENT")) {
                    row.getElement().classList.add("parent-row");
                }
            },
            columns: [{
                    title: "#",
                    field: "Image",
                    headerSort: false,
                    formatter: function(cell) {
                        const url = cell.getValue();
                        if (!url) return `<span class="text-muted">N/A</span>`;
                        return `<img 
                        src="${url}" 
                        data-full="${url}" 
                        class="hover-thumb" 
                        style="width:30px;height:30px;border-radius:6px;object-fit:contain;box-shadow:0 1px 4px #0001;cursor: pointer;"
                    >`;
                    },
                    cellMouseOver: function(e, cell) {
                        const img = cell.getElement().querySelector('.hover-thumb');
                        if (!img) return;

                        const fullUrl = img.getAttribute('data-full');

                        let preview = document.createElement('div');
                        preview.id = 'image-hover-preview';
                        preview.style.position = 'fixed';
                        preview.style.top = `${e.clientY + 10}px`;
                        preview.style.left = `${e.clientX + 10}px`;
                        preview.style.zIndex = 9999;
                        preview.style.border = '1px solid #ccc';
                        preview.style.background = '#fff';
                        preview.style.padding = '4px';
                        preview.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
                        preview.innerHTML =
                            `<img src="${fullUrl}" style="max-width:350px;max-height:350px;">`;

                        document.body.appendChild(preview);
                    },
                    cellMouseOut: function(e, cell) {
                        const preview = document.getElementById('image-hover-preview');
                        if (preview) preview.remove();
                    },
                },
                {
                    title: "Parent",
                    field: "Parent",
                    minWidth: 130,
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search parent.",
                    headerFilterFunc: "like",
                    accessor: row => row["Parent"]
                },


           
                {
                    title: "SKU",
                    field: "SKU",
                    minWidth: 130,
                    headerFilter: "input",
                    headerFilterPlaceholder: "Search sku.",
                    headerFilterFunc: "like",
                    accessor: row => row["SKU"]
                },
                
                {
                    title: "INV",
                    field: "INV",
                    accessor: row => row["INV"],
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return `<span style="display:block; text-align:center;">${value}</span>`;
                    }
                },
                // {
                //     title: "Shopify Price",
                //     field: "shopifyb2c_price",
                //     accessor: row => row["shopifyb2c_price"],
                //     formatter: function(cell) {
                //         const value = cell.getValue() || 0;
                //         const roundedValue = (value);
                //         return `<span style="display:block; text-align:center; font-weight:bold;">$${roundedValue.toLocaleString()}</span>`;
                //     }
                // },
                // {
                //     title: "INV Value",
                //     field: "inv_value",
                //     accessor: row => row["inv_value"],
                //     formatter: function(cell) {
                //         const value = cell.getValue() || 0;
                //         const roundedValue = Math.round(parseFloat(value));
                //         return `<span style="display:block; text-align:center; font-weight:bold;">$${roundedValue.toLocaleString()}</span>`;
                //     }
                // },
                // {
                //     title: "LP Value",
                //     field: "lp_value",
                //     accessor: row => row["lp_value"],
                //     formatter: function(cell) {
                //         const value = cell.getValue() || 0;
                //         const roundedValue = Math.round(parseFloat(value));
                //         return `<span style="display:block; text-align:center; font-weight:bold;">$${roundedValue.toLocaleString()}</span>`;
                //     }
                // },
               
                {
                    title: "OV L30",
                    field: "L30",
                    accessor: row => row["L30"],
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return `<span style="display:block; text-align:center;">${value}</span>`;
                    }
                },
                {
                    title: "DIL",
                    field: "ov_dil",
                    formatter: function(cell) {
                        const data = cell.getData();
                        const l30 = parseFloat(data.L30);
                        const inv = parseFloat(data.INV);

                        if (!isNaN(l30) && !isNaN(inv) && inv !== 0) {
                            const dilDecimal = (l30 / inv);
                            const color = getDilColor(dilDecimal);
                            return `<div class="text-center"><span class="dil-percent-value ${color}">${Math.round(dilDecimal * 100)}%</span></div>`;
                        }
                        return `<div class="text-center"><span class="dil-percent-value red">0%</span></div>`;
                    }
                },


                  
                {
                    title: "2 Ord",
                    field: "to_order",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const isNegative = value < 0;

                        return `<div style="text-align: center;">
                        <span style="
                            background-color: ${isNegative ? '#dc3545' : '#ffc107'};
                            color: ${isNegative ? 'white' : 'black'};
                            padding: 2px 6px;
                            border-radius: 4px;
                            display: inline-block;
                            min-width: 30px;
                            text-align: center;
                            font-weight: bold;
                        ">${value}</span>
                    </div>`;
                    }
                },

                {
                    title: "MSL",
                    field: "msl",
                    formatter: function(cell) {
                        const value = cell.getValue() || 0;
                        return `
                        <div style="text-align:center; font-weight:bold;">
                            ${value}
                            <button class="btn btn-sm btn-link text-info open-month-modal" style="padding: 0 4px;" title="View Monthly">
                                <i class="bi bi-calendar3"></i>
                            </button>
                        </div>
                    `;
                    },
                    cellClick: function(e, cell) {
                        if (e.target.closest(".open-month-modal")) {
                            const row = cell.getRow().getData();
                            const sku = row["SKU"] || '';
                            const monthData = {
                                "JAN": row["jan"],
                                "FEB": row["feb"],
                                "MAR": row["Mar"],
                                "APR": row["Apr"],
                                "MAY": row["May"],
                                "JUN": row["Jun"],
                                "JUL": row["Jul"],
                                "AUG": row["Aug"],
                                "SEP": row["Sep"],
                                "OCT": row["Oct"],
                                "NOV": row["Nov"],
                                "DEC": row["Dec"]
                            };
                            openMonthModal(monthData, sku);
                        }
                    }
                },
                {
                    title: "M AVG ",
                    field: "MSL_Four",
                    accessor: row => (row ? row["MSL_Four"] : null),
                    formatter: function(cell) {
                        const value = cell.getValue() || 0;
                        return `<div style="text-align:center; font-weight:bold;">${value.toFixed(0)}</div>`;
                    }
                },
                //   {
                //     title: "S-MSL",
                //     field: "s_msl",
                //     headerSort: false,
                //     formatter: function(cell) {
                //         const value = cell.getValue();
                //         const rowData = cell.getRow().getData();

                //         const sku = rowData.SKU ?? '';
                //         const parent = rowData.Parent ?? '';

                //         return `<div 
                //         class="editable-qty" 
                //         contenteditable="true" 
                //         data-field="S-MSL"
                //         data-original="${value ?? ''}" 
                //         data-sku='${sku}' 
                //         data-parent='${parent}' 
                //         style="outline:none; min-width:50px; text-align:center;">
                //         ${value ?? ''}
                //     </div>`;
                //     }
                // },
                // {
                //     title: "MSL_VL",
                //     field: "MSL_C",
                //     accessor: row => row["MSL_C"],
                //     formatter: function(cell) {
                //         const value = cell.getValue() || 0;
                //         const wholeNumber = Math.round(parseFloat(value));
                //         return `<div style="text-align:center; font-weight:bold;">${wholeNumber}</div>`;
                //     },
                //     sum: function(cells) {
                //         return cells.reduce((acc, cell) => acc + (cell.getValue() || 0), 0);
                //     }
                // },
                
                {
                    title: "MIP",
                    field: "order_given",
                    accessor: row => (row ? row["order_given"] : null),
                    sorter: "number",
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();

                        const sku = rowData.SKU ?? '';
                        const parent = rowData.Parent ?? '';

                        return `<div 
                            class="editable-qty" 
                            contenteditable="false" 
                            data-field="order_given" 
                            data-original='${value ?? ''}' 
                            data-sku='${sku}' 
                            data-parent='${parent}' 
                            style="outline:none; min-width:40px; text-align:center; font-weight:bold;" readonly>
                            ${value ?? ''}
                        </div>`;
                    }
                },
                {
                    title: "R2S",
                    field: "readyToShipQty",
                    accessor: row => (row ? row["readyToShipQty"] : null),
                    sorter: "number",
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        
                        return value ?? '';
                    }
                },
                // {
                //     title: "MIP Value",
                //     field: "MIP_Value",
                //     accessor: row => (row ? row["MIP_Value"] : null),
                //     sorter: "number",
                //     headerSort: true,
                //     formatter: function(cell) {
                //         const value = cell.getValue();

                //         return value ?? '';
                //     }
                // },

                // {
                //     title: "R2S Value",
                //     field: "R2S_Value",
                //     accessor: row => (row ? row["R2S_Value"] : null),
                //     sorter: "number",
                //     headerSort: true,
                //     formatter: function(cell) {
                //         const value = cell.getValue();

                //         return value ?? '';
                //     }
                // },

                // {
                //     title: "Transit Value",
                //     field: "Transit_Value",
                //     accessor: row => (row ? row["Transit_Value"] : null),
                //     sorter: "number",
                //     headerSort: true,
                //     formatter: function(cell) {
                //         const value = cell.getValue();

                //         return value ?? '';
                //     }
                // },

                // {
                //     title: "Trnst",
                //     field: "transit",
                //     accessor: row => (row ? row["transit"] : null),
                //     sorter: "number",
                //     headerSort: true,
                //     formatter: function(cell) {
                //         const value = cell.getValue();
                //         const rowData = cell.getRow().getData();

                //         const sku = rowData.SKU ?? '';
                //         const parent = rowData.Parent ?? '';

                //         return `<div 
                //             class="editable-qty" 
                //             contenteditable="true" 
                //             data-field="Transit" 
                //             data-original="${value ?? ''}" 
                //             data-sku='${sku}' 
                //             data-parent='${parent}' 
                //             style="outline:none; min-width:40px; text-align:center; font-weight:bold;">
                //             ${value ?? ''}
                //         </div>`;
                //     }
                // },

                {
                    title: "Transit",
                    field: "transit",
                    accessor: row => (row ? row["transit"] : null),
                    sorter: "number",
                    headerSort: true,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const transit = row.getData().transit;
                        let containerName = row.getData().containerName;
                        if (containerName) {
                            containerName = containerName
                                .split(",") 
                                .map(name => name.trim()) 
                                .filter(name => name.length > 0) 
                                .map(name => {
                                    const match = name.match(/(\d+)/);
                                    return match ? `C-${match[1]}` : name;
                                })
                                .join(", ");
                        }
                        return `<div style="line-height:1.5;">
                            <span style="font-weight:600;">${transit}</span><br>
                            <small class="text-info">${containerName}</small>
                        </div>`;
                    }
                },

                {
                    title: "MOQ",
                    field: "MOQ",
                    accessor: row => row["MOQ"],
                    headerSort: false,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const rowData = cell.getRow().getData();

                        const sku = rowData.SKU ?? '';
                        const parent = rowData.Parent ?? '';

                        return `<div 
                        class="editable-qty" 
                        contenteditable="true"
                        data-field="MOQ" 
                        data-original="${value ?? ''}" 
                        data-sku='${sku}' 
                        data-parent='${parent}' 
                        style="outline:none; min-width:40px; text-align:center; font-weight:bold;">
                        ${value ?? ''}
                    </div>`;
                    }
                },
                {
                    title: "Stage",
                    field: "stage",
                    accessor: row => row?.["stage"] ?? null,
                    headerSort: false,
                    formatter: function(cell) {
                        const value = cell.getValue() ?? '';
                        const rowData = cell.getRow().getData();

                        return `
                        <select class="form-select form-select-sm editable-select"
                            data-type="Stage"
                            data-sku='${rowData["SKU"]}'
                            data-parent='${rowData["Parent"]}'
                            style="width: auto; min-width: 100px; padding: 4px 24px 4px 8px;
                                font-size: 0.875rem; border-radius: 4px; border: 1px solid #dee2e6;
                                background-color: #fff;">
                            <option value="">Select</option>
                            <option value="appr_req" ${value === 'appr_req' ? 'selected' : ''}>Appr. Req</option>
                            <option value="mip" ${value === 'mip' ? 'selected' : ''}>MIP</option>
                            <option value="r2s" ${value === 'r2s' ? 'selected' : ''}>R2S</option>
                            <option value="transit" ${value === 'transit' ? 'selected' : ''}>Transit</option>
                            <option value="all_good" ${value === 'all_good' ? 'selected' : ''}>😊 All Good</option>
                            <option value="to_order_analysis" ${value === 'to_order_analysis' ? 'selected' : ''}>2 Order</option>
                        </select>
                    `;
                    }
                },
                {
                    title: "Supplier",
                    field: "Supplier Tag",
                    accessor: row => row["Supplier Tag"]
                },
                {
                    title: "NRP",
                    field: "nr",
                    accessor: row => {
                        const val = row?.["nr"];
                        // Return null/undefined as empty string, but preserve actual values
                        if (val === null || val === undefined) return '';
                        // Convert to string and normalize
                        const strVal = String(val);
                        const normalized = strVal.trim().toUpperCase();
                        // Return normalized value (even if empty string)
                        return normalized;
                    },
                    headerSort: false,
                    formatter: function(cell) {
                        const rowData = cell.getRow().getData();
                        let value = cell.getValue();
                        
                        // Get raw value from row data as fallback - check both cell value and row data
                        if (value === null || value === undefined || value === '') {
                            value = rowData["nr"];
                        }
                        
                        // Convert to string and normalize - handle all cases
                        if (value === null || value === undefined) {
                            value = '';
                        } else {
                            value = String(value).trim().toUpperCase();
                        }
                        
                        const sku = rowData["SKU"] || '';
                        const parent = rowData["Parent"] || '';

                        let bgColor = '#ffffff'; // default white
                        let textColor = '#000000'; // default black

                        // ✅ If value is empty or null, treat as REQ by default
                        if (!value || value === '') {
                            value = 'REQ';
                        }
                        
                        // Normalize value to match expected values
                        if (value !== 'REQ' && value !== 'NR' && value !== 'LATER') {
                            value = 'REQ'; // Default to REQ if value doesn't match
                        }

                        // ✅ Set background and text color based on value
                        if (value === 'NR') {
                            bgColor = '#dc3545'; // red
                            textColor = '#ffffff';
                        } else if (value === 'REQ') {
                            bgColor = '#28a745'; // green
                            textColor = '#000000';
                        } else if (value === 'LATER') {
                            bgColor = '#ffc107'; // yellow
                            textColor = '#000000';
                        }

                        return `
                            <select class="form-select form-select-sm editable-select"
                                data-type="NR"
                                data-sku='${sku}'
                                data-parent='${parent}'
                                style="
                                    width: auto; 
                                    min-width: 85px; 
                                    padding: 4px 8px;
                                    font-size: 0.875rem; 
                                    border-radius: 4px; 
                                    border: 1px solid #dee2e6;
                                    background-color: ${bgColor};
                                    color: ${textColor};
                                ">
                                <option value="REQ" ${value === 'REQ' ? 'selected' : ''}>REQ</option>
                                <option value="NR" ${value === 'NR' ? 'selected' : ''}>2BDC</option>
                                <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>LATER</option>
                            </select>
                        `;

                    }
                },
                {
                    title: "Hide",
                    field: "hide",
                    accessor: row => row?.["hide"] ?? null,
                    headerSort: false,
                    formatter: function(cell) {
                        const value = cell.getValue() ?? '';
                        const rowData = cell.getRow().getData();

                        return `
                        <select class="form-select form-select-sm editable-select"
                            data-type="Hide"
                            data-sku='${rowData["SKU"]}'
                            data-parent='${rowData["Parent"]}'
                            style="width: auto; min-width: 100px; padding: 4px 24px 4px 8px;
                                font-size: 0.875rem; border-radius: 4px; border: 1px solid #dee2e6;
                                background-color: #fff;">
                            <option value="">Select</option>
                            <option value="@Need" ${value === '@Need' ? 'selected' : ''}>@Need</option>
                            <option value="@Taken" ${value === '@Taken' ? 'selected' : ''}>@Taken</option>
                            <option value="@Senior" ${value === '@Senior' ? 'selected' : ''}>@Senior</option>
                        </select>
                    `;
                    }
                },
                {
                    title: "MSL SP",
                    field: "MSL_SP",
                    accessor: row => row["MSL_SP"],
                    formatter: function(cell) {
                        const value = cell.getValue() || 0;
                        const roundedValue = Math.floor(parseFloat(value));
                        return `<div style="text-align:center; font-weight:bold;">$${roundedValue.toLocaleString()}</div>`;
                    }
                },

                 {
                    title: "CP",
                    field: "CP",
                    accessor: row => row["CP"],
                    formatter: function(cell) {
                        const value = cell.getValue() || 0;
                        return `<span style="display:block; text-align:center; font-weight:bold;">$${value.toLocaleString()}</span>`;
                    }
                },
                {
                    title: "LP",
                    field: "LP",
                    accessor: row => row["LP"],
                    formatter: function(cell) {
                        const value = cell.getValue() || 0;
                        return `<span style="display:block; text-align:center; font-weight:bold;">$${value.toLocaleString()}</span>`;
                    }
                },
            ],
            ajaxResponse: function(url, params, response) {
                groupedSkuData = {}; // clear previous

                // Update total MSL_C from server response
                const totalMslCElement = document.getElementById('total_msl_c_value');
                if (totalMslCElement && response.total_msl_c !== undefined) {
                    const wholeNumber = Math.round(parseFloat(response.total_msl_c));
                    totalMslCElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Calculate and update total INV Value
                const totalInvValue = response.data.reduce((sum, item) => {
                    if (!item.is_parent) {
                        return sum + (parseFloat(item.inv_value) || 0);
                    }
                    return sum;
                }, 0);
                const totalInvValueElement = document.getElementById('total_inv_value_display');
                if (totalInvValueElement) {
                    const roundedTotal = Math.round(totalInvValue);
                    totalInvValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }

                // Calculate and update total LP Value
                const totalLpValue = response.data.reduce((sum, item) => {
                    if (!item.is_parent) {
                        return sum + (parseFloat(item.lp_value) || 0);
                    }
                    return sum;
                }, 0);
                const totalLpValueElement = document.getElementById('total_lp_value_display');
                if (totalLpValueElement) {
                    const roundedTotal = Math.round(totalLpValue);
                    totalLpValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }

                // Calculate and update total Restock MSL
                const totalRestockMsl = response.data.reduce((sum, item) => {
                    if (!item.is_parent && (parseFloat(item.INV) || 0) === 0) {
                        const lp = parseFloat(item.LP) || 0;
                        return sum + (lp / 4);
                    }
                        // lp* msl
                    return sum;
                }, 0);
                const totalRestockMslElement = document.getElementById('total_restock_msl_value');
                if (totalRestockMslElement) {
                    const wholeNumber = Math.round(totalRestockMsl);
                    totalRestockMslElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Calculate restock count and average shopify price for restock SKUs
                const restockItems = response.data.filter(item => !item.is_parent && (parseFloat(item.INV) || 0) === 0);
                const restockCount = restockItems.length;
                const totalShopifyPrice = restockItems.reduce((sum, item) => sum + (parseFloat(item.shopifyb2c_price) || 0), 0);
                const averageShopifyPrice = restockCount > 0 ? totalShopifyPrice / restockCount : 0;
                const totalMinimalMsl = restockItems.reduce((sum, item) => sum + (parseFloat(item.MSL_SP) || 0), 0);
                const totalMinimalMslElement = document.getElementById('total_minimal_msl_value');
                if (totalMinimalMslElement) {
                    const wholeNumber = Math.round(totalMinimalMsl);
                    totalMinimalMslElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Calculate sum of restock shopify prices
                const sumRestockShopifyPrice = restockItems.reduce((sum, item) => sum + (parseFloat(item.shopifyb2c_price) || 0), 0);
                const sumRestockShopifyPriceElement = document.getElementById('sum_restock_shopify_price_value');
                if (sumRestockShopifyPriceElement) {
                    const wholeNumber = Math.round(sumRestockShopifyPrice);
                    sumRestockShopifyPriceElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Calculate and update total MIP Value
                const totalMipValue = response.data.reduce((sum, item) => {
                    if (!item.is_parent) {
                        return sum + (parseFloat(item.MIP_Value) || 0);
                    }
                    return sum;
                }, 0);
                const totalMipValueElement = document.getElementById('total_mip_value_display');
                if (totalMipValueElement) {
                    const roundedTotal = Math.round(totalMipValue);
                    totalMipValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }

                // Calculate and update total R2S Value
                const totalR2sValue = response.data.reduce((sum, item) => {
                    if (!item.is_parent) {
                        return sum + (parseFloat(item.R2S_Value) || 0);
                    }
                    return sum;
                }, 0);
                const totalR2sValueElement = document.getElementById('total_r2s_value_display');
                if (totalR2sValueElement) {
                    const roundedTotal = Math.round(totalR2sValue);
                    totalR2sValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }

                // Calculate and update total Transit Value
                const totalTransitValue = response.data.reduce((sum, item) => {
                    if (!item.is_parent) {
                        return sum + (parseFloat(item.Transit_Value) || 0);
                    }
                    return sum;
                }, 0);
                const totalTransitValueElement = document.getElementById('total_transit_value_display');
                if (totalTransitValueElement) {
                    const roundedTotal = Math.round(totalTransitValue);
                    totalTransitValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }

                   // Calculate total restock MSL LP
                const totalLp = restockItems.reduce((sum, item) => sum + (parseFloat(item.LP) || 0), 0);
                const averageLp = restockCount > 0 ? totalLp / restockCount : 0;
                const totalRestockMslLp = restockCount * (averageLp / 4);
                const totalRestockMslLpElement = document.getElementById('total_restock_msl_lp_value');
                if (totalRestockMslLpElement) {
                    const wholeNumber = Math.round(totalRestockMslLp);
                    totalRestockMslLpElement.textContent = wholeNumber.toLocaleString('en-US');
                }


                const groupedMSL = {};
                const groupedS_MSL = {};

                const processed = response.data.map((item, index) => {
                    const sku = item["SKU"] || "";
                    const parentKey = item["Parent"] || "";

                    const total = parseFloat(item["Total"]) || 0;
                    const totalMonth = parseFloat(item["Total month"]) || 0;

                    const inv = parseFloat(item["INV"]) || 0;
                    const transit = parseFloat(item["Transit"] ?? item["transit"]) || 0;
                    const orderGiven = parseFloat(item["order_given"] ?? item["Order Given"]) || 0;
                    const r2s = parseFloat(item["readyToShipQty"] ?? item["readyToShipQty"]) || 0;
                    const msl = totalMonth > 0 ? (total / totalMonth) * 4 : 0;

                    // Get stage from item to determine which fields to use for to_order calculation
                    const itemStage = item.stage || '';
                    
                    // For to_order calculation, only use the quantity from the matching stage
                    let effectiveOrderGiven = 0;
                    let effectiveR2s = 0;
                    let effectiveTransit = transit;
                    
                    if (itemStage === 'mip') {
                        effectiveOrderGiven = orderGiven;
                    } else if (itemStage === 'r2s') {
                        effectiveR2s = r2s;
                    } else if (itemStage === 'transit') {
                        effectiveTransit = transit;
                    }

                    const toOrder = Math.round(msl - inv - effectiveTransit - effectiveOrderGiven - effectiveR2s);

                    // if (toOrder == 0) {
                    //     return false;
                    // }

                    if (!groupedMSL[parentKey]) groupedMSL[parentKey] = 0;
                    groupedMSL[parentKey] += msl;

                    const s_msl_val = parseFloat(item["s-msl"]) || 0;
                    if (!groupedS_MSL[parentKey]) groupedS_MSL[parentKey] = 0;
                    groupedS_MSL[parentKey] += s_msl_val;

                    const isParent = item.is_parent === true || item.is_parent === "true" || sku.toUpperCase().includes("PARENT");

                    // Calculate MSL_C (MSL * LP / 4)
                    const lp = parseFloat(item["LP"]) || 0;
                    const msl_c = Math.round((msl * lp / 4) * 100) / 100; // Round to 2 decimal places
                    
                    // Calculate MSL SP (shopify price * MSL / 4)
                    const shopifyPrice = parseFloat(item["shopifyb2c_price"]) || 0;
                    const msl_sp = Math.round(shopifyPrice * msl / 4);

                    // Get original values (itemStage already declared above)
                    const originalOrderGiven = parseFloat(item['order_given'] ?? item['Order Given'] ?? 0);
                    const originalReadyToShipQty = parseFloat(item['readyToShipQty'] ?? item['ready_to_ship'] ?? 0);
                    const originalTransit = parseFloat(item['transit'] ?? item['Transit'] ?? 0);
                    
                    // Clear stage fields based on current stage - only show value for matching stage
                    let finalOrderGiven = 0;
                    let finalReadyToShipQty = 0;
                    let finalTransit = 0;
                    
                    // If stage is set, only show value for matching stage
                    if (itemStage === 'mip') {
                        finalOrderGiven = originalOrderGiven;
                    } else if (itemStage === 'r2s') {
                        finalReadyToShipQty = originalReadyToShipQty;
                    } else if (itemStage === 'transit') {
                        finalTransit = originalTransit;
                    }

                    const processedItem = {
                        ...item,
                        sl_no: index + 1,
                        pft_percent: item['pft%'] ?? null,
                        msl: Math.round(msl),
                        MSL_C: msl_c,
                        MSL_SP: msl_sp,
                        to_order: toOrder,
                        parentKey: parentKey,
                        s_msl: s_msl_val,
                        is_parent: isParent,
                        isParent: isParent,
                        raw_data: item || {},
                        order_given: finalOrderGiven,
                        readyToShipQty: finalReadyToShipQty,
                        transit: finalTransit
                    };

                    // Group for play button use
                    if (!groupedSkuData[parentKey]) groupedSkuData[parentKey] = [];
                    groupedSkuData[parentKey].push(processedItem);

                    return processedItem;
                });

                // update parent rows
                processed.forEach(row => {
                    if (row.isParent) {
                        const parentKey = row.parentKey;
                        const children = groupedSkuData[parentKey] || [];
                    }
                });

                setTimeout(() => {
                    setCombinedFilters();
                }, 0);
                return processed;
            },

            ajaxError: function(xhr, textStatus, errorThrown) {
                console.error("Error loading data:", textStatus);
            },
        });

        let currentParentFilter = null;
        let currentColorFilter = null;
        let hideNRYes = true;
        let currentRowTypeFilter = 'all';
        let currentRestockFilter = false;
        let currentZeroInvFilter = false;
        let currentStageFilter = '';
        let currentNRPFilter = '';

        function setCombinedFilters() {
            const allData = table.getData();
            const groupedChildrenMap = {};
            const visibleParentKeys = new Set();

            // Calculate total restock count
            const restockCount = allData.filter(item => {
                const invValue = item.raw_data ? item.raw_data["INV"] : item["INV"];
                const inv = parseFloat(invValue) || 0;
                return !item.is_parent && inv === 0;
            }).length;
            document.getElementById('total_restock').textContent = restockCount;
            document.getElementById('zero_inv_count').textContent = restockCount;

            // Group all children by parent
            allData.forEach(item => {
                if (!item.is_parent) {
                    const key = item.Parent;
                    if (!groupedChildrenMap[key]) groupedChildrenMap[key] = [];
                    groupedChildrenMap[key].push(item);
                }
            });

            // Determine which parents should be visible
            Object.keys(groupedChildrenMap).forEach(parentKey => {
                const children = groupedChildrenMap[parentKey];

                const matchingChildren = children.filter(child => {
                    // NRP filter - treat empty/null as REQ (matching formatter logic)
                    const childNR = child.nr || '';
                    const effectiveChildNR = childNR === '' ? 'REQ' : childNR;
                    const nrpMatch = !currentNRPFilter || effectiveChildNR === currentNRPFilter;
                    
                    // NR match - if NRP filter is set to NR, show NR rows even if hideNRYes is true
                    const nrMatch = !hideNRYes || child.nr !== 'NR' || (currentNRPFilter === 'NR');
                    
                    // Stage filter - check stage field or transit field
                    // If filter is "__blank__", match empty/null/undefined stage
                    const childStage = child.stage || '';
                    let stageMatch = true;
                    if (currentStageFilter) {
                        if (currentStageFilter === '__blank__') {
                            stageMatch = !childStage || childStage === '';
                        } else if (currentStageFilter === 'transit') {
                            // For transit filter, check if transit value exists and > 0
                            const transitValue = child.raw_data ? child.raw_data["transit"] : child["transit"];
                            const transit = parseFloat(transitValue) || 0;
                            stageMatch = transit > 0;
                        } else {
                            stageMatch = childStage === currentStageFilter;
                        }
                    }
                    
                    let filterMatch = true;
                    if (currentRestockFilter) {
                        const invValue = child.raw_data ? child.raw_data["INV"] : child["INV"];
                        const l30Value = child.raw_data ? child.raw_data["L30"] : child["L30"];
                        const inv = parseFloat(invValue) || 0;
                        const l30 = parseFloat(l30Value) || 0;
                        const dilOver100 = inv === 0 || (inv > 0 && l30 / inv > 1);
                        filterMatch = dilOver100;
                    } 
                    else {
                        filterMatch = currentColorFilter === 'red' ?
                            child.to_order < 0 :
                            currentColorFilter === 'yellow' ?
                            child.to_order >= 0 :
                            true;
                    }
                    return nrMatch && nrpMatch && stageMatch && filterMatch;
                });

                if (matchingChildren.length > 0) {
                    visibleParentKeys.add(parentKey);
                }
            });

            table.setFilter(function(row) {
                const data = typeof row.getData === 'function' ? row.getData() : row;

                const isChild = !data.is_parent;
                const isParent = data.is_parent;

                let matchesFilter = true;
                if (currentRestockFilter) {
                    const invValue = data.raw_data ? data.raw_data["INV"] : data["INV"];
                    const l30Value = data.raw_data ? data.raw_data["L30"] : data["L30"];
                    const inv = parseFloat(invValue) || 0;
                    const l30 = parseFloat(l30Value) || 0;
                    const dilOver100 = inv === 0 || (inv > 0 && l30 / inv > 1);
                    matchesFilter = dilOver100;
                } else {
                    matchesFilter = currentColorFilter === 'red' ?
                        data.to_order < 0 :
                        currentColorFilter === 'yellow' ?
                        data.to_order >= 0 :
                        true;
                }

                // NRP filter - treat empty/null as REQ (matching formatter logic)
                const dataNR = data.nr || '';
                const effectiveNR = dataNR === '' ? 'REQ' : dataNR;
                const nrpMatch = !currentNRPFilter || effectiveNR === currentNRPFilter;
                
                // NR match - if NRP filter is set to NR, show NR rows even if hideNRYes is true
                const matchesNR = hideNRYes ? (data.nr !== 'NR' || currentNRPFilter === 'NR') : true;
                
                // Stage filter - check stage field or transit field
                // If filter is "__blank__", match empty/null/undefined stage
                const dataStage = data.stage || '';
                let stageMatch = true;
                if (currentStageFilter) {
                    if (currentStageFilter === '__blank__') {
                        stageMatch = !dataStage || dataStage === '';
                    } else if (currentStageFilter === 'transit') {
                        // For transit filter, check if transit value exists and > 0
                        const transitValue = data.raw_data ? data.raw_data["transit"] : data["transit"];
                        const transit = parseFloat(transitValue) || 0;
                        stageMatch = transit > 0;
                    } else {
                        stageMatch = dataStage === currentStageFilter;
                    }
                }

                // 🎯 Force filter to one parent group if play mode is active
                if (currentParentFilter) {
                    if (isParent) {
                        return data.Parent === currentParentFilter;
                    } else {
                        return data.Parent === currentParentFilter && matchesFilter && matchesNR && nrpMatch && stageMatch;
                    }
                }

                if (isChild) {
                    const showChild = matchesFilter && matchesNR && nrpMatch && stageMatch;
                    if (currentRowTypeFilter === 'parent') return false;
                    if (currentRowTypeFilter === 'sku') return showChild;
                    return showChild;
                }

                if (isParent) {
                    const showParent =
                        currentRowTypeFilter === 'parent' ?
                        true :
                        visibleParentKeys.has(data.Parent);
                    if (currentRowTypeFilter === 'sku') return false;
                    return showParent;
                }

                return false;
            });

            // update visible count
            setTimeout(() => {
                updateParentTotalsBasedOnVisibleRows();
                
                // Calculate total MSL_C and MSL_SP for visible rows
                const visibleRows = table.getRows(true);
                let totalMslC = 0;
                let totalMslSp = 0;
                
                visibleRows.forEach(row => {
                    const data = row.getData();
                    if (!data.is_parent) {
                        totalMslC += parseFloat(data.MSL_C) || 0;
                        totalMslSp += parseFloat(data.MSL_SP) || 0;
                    }
                });
                
                // Update total MSL_C display
                const totalMslCElement = document.getElementById('total_msl_c_value');
                if (totalMslCElement) {
                    const wholeNumber = Math.round(totalMslC);
                    totalMslCElement.textContent = wholeNumber.toLocaleString('en-US');
                }
                
                // Update total MSL_SP display
                const totalMslSpElement = document.getElementById('total_msl_sp_value');
                if (totalMslSpElement) {
                    const wholeNumber = Math.round(totalMslSp);
                    totalMslSpElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Calculate total Restock MSL for visible rows
                let totalRestockMsl = 0;
                visibleRows.forEach(row => {
                    const data = row.getData();
                    if (!data.is_parent && (parseFloat(data.INV) || 0) === 0) {
                        const lp = parseFloat(data.LP) || 0;
                        totalRestockMsl += (lp / 4);
                    }
                });

                // Update total Restock MSL display
                const totalRestockMslElement = document.getElementById('total_restock_msl_value');
                if (totalRestockMslElement) {
                    const wholeNumber = Math.round(totalRestockMsl);
                    totalRestockMslElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Calculate total Minimal MSL for visible rows
                const visibleRestockItems = visibleRows.filter(row => {
                    const data = row.getData();
                    return !data.is_parent && (parseFloat(data.INV) || 0) === 0;
                });
                const visibleRestockCount = visibleRestockItems.length;
                const visibleTotalShopifyPrice = visibleRestockItems.reduce((sum, row) => {
                    const data = row.getData();
                    return sum + (parseFloat(data.shopifyb2c_price) || 0);
                }, 0);
                const visibleAverageShopifyPrice = visibleRestockCount > 0 ? visibleTotalShopifyPrice / visibleRestockCount : 0;
                const totalMinimalMsl = visibleRestockItems.reduce((sum, row) => {
                    const data = row.getData();
                    return sum + (parseFloat(data.MSL_SP) || 0);
                }, 0);

                // Update total Minimal MSL display
                const totalMinimalMslElement = document.getElementById('total_minimal_msl_value');
                if (totalMinimalMslElement) {
                    const wholeNumber = Math.round(totalMinimalMsl);
                    totalMinimalMslElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Calculate sum restock shopify price for visible rows
                const visibleSumRestockShopifyPrice = visibleRestockItems.reduce((sum, row) => {
                    const data = row.getData();
                    return sum + (parseFloat(data.shopifyb2c_price) || 0);
                }, 0);

                // Update sum restock shopify price display
                const sumRestockShopifyPriceElement = document.getElementById('sum_restock_shopify_price_value');
                if (sumRestockShopifyPriceElement) {
                    const wholeNumber = Math.round(visibleSumRestockShopifyPrice);
                    sumRestockShopifyPriceElement.textContent = wholeNumber.toLocaleString('en-US');
                }

                // Calculate total restock MSL LP for visible rows
                const visibleTotalLp = visibleRestockItems.reduce((sum, row) => {
                    const data = row.getData();
                    return sum + (parseFloat(data.LP) || 0);
                }, 0);
                const visibleAverageLp = visibleRestockCount > 0 ? visibleTotalLp / visibleRestockCount : 0;
                const totalRestockMslLp = visibleRestockCount * (visibleAverageLp / 4);

                // Calculate total MIP Value for visible rows
                let totalMipValue = 0;
                visibleRows.forEach(row => {
                    const data = row.getData();
                    if (!data.is_parent) {
                        totalMipValue += parseFloat(data.MIP_Value) || 0;
                    }
                });

                // Update total MIP Value display
                const totalMipValueElement = document.getElementById('total_mip_value_display');
                if (totalMipValueElement) {
                    const roundedTotal = Math.round(totalMipValue);
                    totalMipValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }

                // Calculate total R2S Value for visible rows
                let totalR2sValue = 0;
                visibleRows.forEach(row => {
                    const data = row.getData();
                    if (!data.is_parent) {
                        totalR2sValue += parseFloat(data.R2S_Value) || 0;
                    }
                });

                // Update total R2S Value display
                const totalR2sValueElement = document.getElementById('total_r2s_value_display');
                if (totalR2sValueElement) {
                    const roundedTotal = Math.round(totalR2sValue);
                    totalR2sValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }

                // Calculate total Transit Value for visible rows
                let totalTransitValue = 0;
                visibleRows.forEach(row => {
                    const data = row.getData();
                    if (!data.is_parent) {
                        totalTransitValue += parseFloat(data.Transit_Value) || 0;
                    }
                });

                // Update total Transit Value display
                const totalTransitValueElement = document.getElementById('total_transit_value_display');
                if (totalTransitValueElement) {
                    const roundedTotal = Math.round(totalTransitValue);
                    totalTransitValueElement.textContent = roundedTotal.toLocaleString('en-US');
                }
            }, 50);

            const visibleRows = table.getRows(true).map(r => r.getData());
            const yellowCount = visibleRows.filter(r =>
                r.to_order >= 0 &&
                !r.is_parent &&
                r.nr !== 'NR'
            ).length;

            document.getElementById('yellow-count-box').textContent = `Appr Req: ${yellowCount}`;
            document.getElementById('toggle-nr-rows').textContent = hideNRYes ? "Show NR" : "Hide NR";
        }

        function updateParentTotalsBasedOnVisibleRows() {
            const visibleRows = table.getRows(true);
            const parentGroups = {};

            visibleRows.forEach(row => {
                const data = row.getData();
                const parent = data.Parent;
                if (!parent) return;

                if (!parentGroups[parent]) {
                    parentGroups[parent] = {
                        approved: 0,
                        inv: 0,
                        l30: 0,
                        orderGiven: 0,
                        transit: 0,
                        toOrder: 0,
                        parentRow: null
                    };
                }

                if (data.is_parent) {
                    // ✅ Skip update if already updated in ajaxResponse
                    parentGroups[parent].parentRow = row;
                } else {
                    const approvedValue = data.raw_data ? data.raw_data["MOQ"] : data["MOQ"];
                    const invValue = data.raw_data ? data.raw_data["INV"] : data["INV"];
                    const l30Value = data.raw_data ? data.raw_data["L30"] : data["L30"];
                    const orderGivenValue = data.raw_data ? data.raw_data["order_given"] : data["order_given"];
                    const transitValue = data.raw_data ? data.raw_data["transit"] : data["transit"];
                    const toOrderValue = data.raw_data ? data.raw_data["to_order"] : data["to_order"];

                    parentGroups[parent].approved += parseFloat(approvedValue) || 0;
                    parentGroups[parent].inv += parseFloat(invValue) || 0;
                    parentGroups[parent].l30 += parseFloat(l30Value) || 0;
                    parentGroups[parent].orderGiven += parseFloat(orderGivenValue) || 0;
                    parentGroups[parent].transit += parseFloat(transitValue) || 0;
                    parentGroups[parent].toOrder += parseFloat(toOrderValue) || 0;
                }
            });

            Object.values(parentGroups).forEach(group => {
                if (group.parentRow) {
                    const parentData = group.parentRow.getData();

                    // ✅ Only update if current values are null or 0
                    const alreadySet =
                        parentData["to_order"] !== undefined && parentData["to_order"] !== null;

                    if (!alreadySet) {
                        group.parentRow.update({
                            "MOQ": group.approved,
                            "INV": group.inv,
                            "L30": group.l30,
                            "order_given": group.orderGiven,
                            "transit": group.transit,
                            "to_order": group.toOrder
                        });
                    }
                }
            });
        }

        //modals
        function openMonthModal(monthData, sku) {
            const wrapper = document.getElementById("monthCardWrapper");
            if (!wrapper) return;

            wrapper.innerHTML = ""; // Clear previous content

            const monthOrder = [
                "JAN", "FEB", "MAR", "APR", "MAY", "JUN",
                "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"
            ];

            // Get current date to determine year for each month
            const currentDate = new Date();
            const currentYear = currentDate.getFullYear();
            const currentMonth = currentDate.getMonth(); // 0-11 (Jan = 0, Dec = 11)

            // Month index mapping (0 = Jan, 11 = Dec)
            const monthIndexMap = {
                "JAN": 0, "FEB": 1, "MAR": 2, "APR": 3,
                "MAY": 4, "JUN": 5, "JUL": 6, "AUG": 7,
                "SEP": 8, "OCT": 9, "NOV": 10, "DEC": 11
            };

            // Determine year for each month based on GenerateMovementAnalysis command logic
            // Command generates rolling data for last 12-14 months
            // 
            // Examples when current month is Jan 2026:
            //   - Data range: Nov 2025 to Jan 2026 (previousMonths=2)
            //   - JAN: 2026 (current year, monthIndex 0 <= currentMonth 0)
            //   - FEB to DEC: 2025 (previous year, monthIndex > currentMonth)
            //
            // Year assignment rule (matches rolling data):
            //   - If monthIndex > currentMonth: previous year (months after current in calendar)
            //   - If monthIndex <= currentMonth: current year (current month and months before it in same calendar)
            const getYearForMonth = (monthIndex) => {
                // If month index is greater than current month, it's from previous year
                // Example: If current month is Jan (0) and month is Feb (1), Feb is from previous year (2025)
                if (monthIndex > currentMonth) {
                    return currentYear - 1;
                }
                // If month index is less than or equal to current month, it's current year
                // Example: If current month is Jan (0) and month is Jan (0), it's current year (2026)
                return currentYear;
            };

            // Sort and display in month order
            monthOrder.forEach(month => {
                const value = monthData[month] ?? 0;
                const monthIndex = monthIndexMap[month];
                const year = getYearForMonth(monthIndex);

                const card = document.createElement("div");
                card.className = "month-card";

                const title = document.createElement("div");
                title.className = "month-title";
                title.innerText = `${month} ${year}`;

                const count = document.createElement("div");
                count.className = "month-value";
                count.innerText = value;

                card.appendChild(title);
                card.appendChild(count);
                wrapper.appendChild(card);
            });

            document.getElementById("month-view-sku").innerText = `( ${sku} )`;

            const modal = new bootstrap.Modal(document.getElementById("monthModal"));
            modal.show();
        }

        function openMetricModal(row) {
            const metricData = {
                SH: row['SH'],
                CP: row['CP'],
                MOQ: row['MOQ'],
                LP: row['LP'],
                "Shopify Price": row['shopifyb2c_price'],
                "MSL_C": row['MSL_C'],
                "MSL_SP": row['MSL_SP'],
                Freight: row['Freight'],
                "GW (KG)": row['GW (KG)'],
                "GW (LB)": row['GW (LB)'],
                "CBM MSL": row['CBM MSL'],
            };

            const wrapper = document.getElementById("metricCardWrapper");
            wrapper.innerHTML = "";

            for (const [key, value] of Object.entries(metricData)) {
                const displayValue = (!isNaN(value) && value !== null) ? parseFloat(value).toFixed(2) : '-';

                const card = document.createElement("div");
                card.className = "month-card";
                card.innerHTML = `
                <div class="month-title">${key}</div>
                <div class="month-value">${displayValue}</div>
            `;
                wrapper.appendChild(card);
            }

            const modal = new bootstrap.Modal(document.getElementById('metricModal'));
            modal.show();
        }

        const COLUMN_VIS_KEY = "tabulator_column_visibility";

        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            const savedVisibility = JSON.parse(localStorage.getItem(COLUMN_VIS_KEY) || '{}');

            const columns = table.getColumns().filter(col => col.getField());

            columns.forEach(col => {
                const field = col.getField();
                const title = col.getDefinition().title;

                // Apply saved visibility on table
                if (savedVisibility[field] === false) {
                    col.hide();
                } else {
                    col.show();
                }

                const li = document.createElement("li");
                const div = document.createElement("div");
                div.className = "form-check d-flex align-items-center gap-2 py-1 px-2 rounded hover-bg-light";

                const input = document.createElement("input");
                input.className = "form-check-input shadow-sm cursor-pointer";
                input.type = "checkbox";
                input.id = `col-${field}`;
                input.value = field;
                input.checked = col.isVisible();
                input.style.cssText = `
                    width: 18px;
                    height: 18px;
                    cursor: pointer;
                    border-color: #dee2e6;
                `;

                const label = document.createElement("label");
                label.className = "form-check-label cursor-pointer mb-0 text-dark";
                label.htmlFor = `col-${field}`;
                label.innerText = title;
                label.style.cssText = `
                    cursor: pointer;
                    font-size: 0.9rem;
                    user-select: none;
                `;

                // Add hover effect
                div.addEventListener('mouseover', () => {
                    div.style.backgroundColor = '#f8f9fa';
                });

                div.addEventListener('mouseout', () => {
                    div.style.backgroundColor = 'transparent';
                });

                // Add ripple effect on click
                div.addEventListener('click', (e) => {
                    if (e.target !== input) {
                        input.click();
                    }
                });

                div.appendChild(input);
                div.appendChild(label);
                li.appendChild(div);
                menu.appendChild(li);
            });
        }

        function saveColumnVisibilityToLocalStorage() {
            const visibility = {};
            table.getColumns().forEach(col => {
                const field = col.getField();
                if (field) {
                    visibility[field] = col.isVisible();
                }
            });
            localStorage.setItem(COLUMN_VIS_KEY, JSON.stringify(visibility));
        }

        document.addEventListener("DOMContentLoaded", () => {
            buildColumnDropdown();

            // Toggle column from dropdown
            document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
                if (e.target.type === "checkbox") {
                    const field = e.target.value;
                    const col = table.getColumn(field);
                    if (col) {
                        e.target.checked ? col.show() : col.hide();
                        saveColumnVisibilityToLocalStorage();
                    }
                }
            });

            // Show All Columns button
            document.getElementById("show-all-columns-btn").addEventListener("click", function() {
                const checkboxes = document.querySelectorAll(
                    "#column-dropdown-menu input[type='checkbox']");
                checkboxes.forEach(cb => {
                    cb.checked = true;
                    const col = table.getColumn(cb.value);
                    if (col) col.show();
                });
                saveColumnVisibilityToLocalStorage();
            });

            // Handle editable field
            $(document).off('blur', '.editable-qty').on('blur', '.editable-qty', function() {
                const $cell = $(this);
                const newValueRaw = $cell.text().trim();
                const originalValue = ($cell.data('original') ?? '').toString().trim();
                const field = $cell.data('field');
                const sku = $cell.data('sku');
                const parent = $cell.data('parent');

                // Convert raw value to number safely
                const newValue = ['MOQ', 'S-MSL', 'order_given'].includes(field) ?
                    Number(newValueRaw) :
                    newValueRaw;

                const original = ['MOQ', 'S-MSL', 'order_given'].includes(field) ?
                    Number(originalValue) :
                    originalValue;

                // Avoid unnecessary updates
                if (newValue === original) return;

                // Numeric validation
                if (['MOQ', 'S-MSL', 'order_given'].includes(field) && isNaN(newValue)) {
                    alert('Please enter a valid number.');
                    $cell.text(originalValue); // revert
                    return;
                }

                // Optional validation for date fields (YYYY-MM-DD)
                if (['Date of Appr'].includes(field)) {
                    const isValidDate = /^\d{4}-\d{2}-\d{2}$/.test(newValue);
                    if (!isValidDate) {
                        alert('Please enter a valid date in YYYY-MM-DD format.');
                        $cell.text(originalValue);
                        return;
                    }
                }

                updateForecastField({
                    sku,
                    parent,
                    column: field,
                    value: newValue
                }, function() {
                    $cell.data('original', newValue);

                    if (field === 'MOQ') {
                        const today = new Date();
                        const currentDate = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

                        const row = table.getRows().find(r =>
                            r.getData().SKU === sku && r.getData().Parent === parent
                        );

                        if (row) row.update({ "MOQ": newValue });

                        updateForecastField({
                            sku,
                            parent,
                            column: 'Date of Appr',
                            value: currentDate
                        }, function() {
                            const row = table.getRows().find(r => r.getData().SKU === sku &&
                                r.getData().Parent === parent);
                        });
                    }
                    setCombinedFilters();
                }, function() {
                    $cell.text(originalValue);
                });

            });

            // Handle link edit modal save
            $('#saveLinkBtn').on('click', function() {
                const newValue = $('#linkEditInput').val().trim();
                const field = editingField;
                const sku = editingRow['SKU'];
                const parent = editingRow['Parent'];

                editingRow[field] = newValue;

                const iconMap = {
                    'Clink': `<i class="fas fa-link text-primary me-1"></i>`,
                    'Olink': `<i class="fas fa-external-link-alt text-success me-1"></i>`,
                    'rfq_form_link': `<i class="fas fa-file-contract text-success me-1"></i>`,
                    'rfq_report': `<i class="fas fa-file-alt text-info me-1"></i>`
                };


                const iconHtml = newValue ?
                    `<a href="${newValue}" target="_blank" title="${field}">${iconMap[field] || ''}</a>` :
                    '';

                const editIcon = `<a href="#" class="edit-${field.toLowerCase()}" title="Edit ${field}">
                                    <i class="fas fa-edit text-warning"></i>
                                </a>`;

                $(editingLinkCell).html(`
                    <div class="d-flex align-items-center justify-content-center gap-1 ${field.toLowerCase()}-cell">
                        ${iconHtml}${editIcon}
                    </div>
                `);

                $('#linkEditModal').modal('hide');

                updateForecastField({
                        sku,
                        parent,
                        column: field,
                        value: newValue
                    },
                    function() {
                        console.log(`${field} saved successfully.`);
                    },
                    function() {
                        alert(`Failed to save ${field}.`);
                    }
                );
            });

            // Handle editable select field
            $(document).off('change', '.editable-select, .editable-date').on('change',
                '.editable-select, .editable-date',
                function() {
                    const $el = $(this);
                    const isSelect = $el.hasClass('editable-select');
                    const isDate = $el.hasClass('editable-date');

                    const newValue = $el.val().trim();
                    const sku = $el.data('sku');
                    const parent = $el.data('parent');
                    const field = isSelect ? $el.data('type') : $el.data('field');
                    const originalValue = isDate ? $el.data('original') : null;

                    if (field === "Stage") {
                        const row = table.getRow(sku);
                        const approvedQty = row ? row.getData()["MOQ"] : null;
                        
                        if (!approvedQty || approvedQty === "0" || parseInt(approvedQty) === 0) {
                            alert("MOQ cannot be empty or zero.");
                            $el.val('');
                            return;
                        }
                    }

                    // For date input: skip if no change
                    if (isDate && newValue === originalValue) return;

                    updateForecastField({
                            sku,
                            parent,
                            column: field,
                            value: newValue
                        },
                        function() {
                            if (isDate) {
                                $el.data('original', newValue); // update reference
                            }
                            if (field === 'NR') {
                                const row = table.getRows().find(r =>
                                    r.getData().SKU === sku && r.getData().Parent === parent
                                );
                                if (row) {
                                    // Update the row data - this will automatically trigger formatter
                                    row.update({
                                        nr: newValue
                                    }, true); // true = update existing row data
                                }

                                setCombinedFilters();
                            }
                            if (field === 'Stage') {
                                // Update the row data
                                const row = table.getRows().find(r =>
                                    r.getData().SKU === sku && r.getData().Parent === parent
                                );
                                if (row) {
                                    const rowData = row.getData();
                                    
                                    // Stage to table mapping
                                    const stageTableMap = {
                                        'mip': { table: 'mfrg-progress', field: 'order_given' },
                                        'r2s': { table: 'ready-to-ship', field: 'readyToShipQty' },
                                        'transit': { table: 'transit', field: 'transit' },
                                        'appr_req': { table: null, field: 'to_order' },
                                        'to_order_analysis': { table: null, field: 'to_order' },
                                        'all_good': { table: null, field: null }
                                    };

                                    const stageConfig = stageTableMap[newValue];
                                    
                                    // Prepare update object - clear other stage fields first
                                    const updateData = {
                                        stage: newValue
                                    };
                                    
                                    // Clear fields that don't match the selected stage
                                    if (newValue !== 'mip') {
                                        updateData['order_given'] = 0;
                                    }
                                    if (newValue !== 'r2s') {
                                        updateData['readyToShipQty'] = 0;
                                    }
                                    if (newValue !== 'transit') {
                                        updateData['transit'] = 0;
                                    }
                                    
                                    if (stageConfig && stageConfig.table) {
                                        // Check if SKU exists in the respective table and get quantity
                                        fetch(`/forecast-analysis/get-sku-quantity?sku=${encodeURIComponent(sku)}&table=${stageConfig.table}`, {
                                            method: 'GET',
                                            headers: {
                                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                                                'Accept': 'application/json'
                                            }
                                        })
                                        .then(res => res.json())
                                        .then(data => {
                                            if (data.success && data.exists) {
                                                // SKU exists in table, use its quantity
                                                const quantity = parseFloat(data.quantity) || 0;
                                                updateData[stageConfig.field] = quantity;
                                            } else {
                                                // SKU doesn't exist, set to 0 for this field
                                                updateData[stageConfig.field] = 0;
                                            }
                                            
                                            // Update row with all changes
                                            row.update(updateData, true);
                                            
                                            // Refresh the cells to show updated values
                                            setTimeout(() => {
                                                const stageCell = row.getCells().find(cell => cell.getField() === 'stage');
                                                const mipCell = row.getCells().find(cell => cell.getField() === 'order_given');
                                                const r2sCell = row.getCells().find(cell => cell.getField() === 'readyToShipQty');
                                                const transitCell = row.getCells().find(cell => cell.getField() === 'transit');
                                                
                                                if (stageCell) stageCell.reformat();
                                                if (mipCell) mipCell.reformat();
                                                if (r2sCell) r2sCell.reformat();
                                                if (transitCell) transitCell.reformat();
                                            }, 100);
                                            
                                            setCombinedFilters();
                                        })
                                        .catch(error => {
                                            console.error('Error fetching quantity:', error);
                                            // On error, still clear other fields and update stage
                                            row.update(updateData, true);
                                            setCombinedFilters();
                                        });
                                    } else {
                                        // For stages without table check (appr_req, to_order_analysis, all_good)
                                        // Still clear other stage fields
                                        row.update(updateData, true);
                                        setCombinedFilters();
                                    }
                                }
                            }
                        },
                        function() {
                            if (isDate) {
                                $el.val(originalValue); // revert on fail
                            }
                            alert(`Failed to save ${field}.`);
                        }
                    );
                });

            // Handle notes edit modal save
            $('#saveNotesBtn').on('click', function() {
                const newValue = $('#notesInput').val().trim();
                const field = editingField;
                const sku = editingRow['SKU'];
                const parent = editingRow['Parent'];

                editingRow[field] = newValue;

                // Update DOM cell content
                const display = newValue ? newValue.substring(0, 30) + (newValue.length > 30 ? '...' : '') :
                    '<em class="text-muted">No notes</em>';

                const updatedHTML = `
                    <div class="d-flex align-items-center justify-content-between notes-cell">
                        <span class="text-truncate" title="${newValue}">${display}</span>
                        <a href="#" class="edit-notes ms-2" title="Edit Notes">
                            <i class="fas fa-edit text-warning"></i>
                        </a>
                    </div>
                `;

                $(editingLinkCell).html(updatedHTML);
                $('#editNotesModal').modal('hide');

                updateForecastField({
                        sku,
                        parent,
                        column: 'Notes',
                        value: newValue
                    },
                    () => {
                        $('#editNotesModal').modal('hide');

                        const cell = $(`.edit-notes-btn[data-sku="${sku}"][data-parent="${parent}"]`)
                            .closest('td');

                        if (cell.length === 0) {
                            console.warn('Cell not found for SKU:', sku, 'and Parent:', parent);
                            return;
                        }

                        cell.empty();

                        const viewBtn = $('<i>')
                            .addClass('fas fa-eye text-info ms-2 view-note-btn')
                            .css('cursor', 'pointer')
                            .attr('title', 'View Note')
                            .attr('data-note', newValue);

                        const editBtn = $('<i>')
                            .addClass('fas fa-edit text-primary ms-2 edit-notes-btn')
                            .css('cursor', 'pointer')
                            .attr('title', 'Edit Note')
                            .attr('data-note', newValue)
                            .attr('data-sku', sku)
                            .attr('data-parent', parent);

                        cell.append(viewBtn, editBtn);
                    },
                    () => {
                        alert('Failed to save note.');
                    }
                );

            });

            // Reusable AJAX call
            function updateForecastField(data, onSuccess = () => {}, onFail = () => {}) {
                $.post('/update-forecast-data', {
                    ...data,
                    _token: $('meta[name="csrf-token"]').attr('content')
                }).done(res => {
                    if (res.success) {
                        console.log('Saved:', res.message);
                        onSuccess();
                    } else {
                        console.warn('Not saved:', res.message);
                        onFail();
                    }
                }).fail(err => {
                    console.error('AJAX failed:', err);
                    alert('Error saving data.');
                    onFail();
                });
            }

        });

        //play btn filter
        document.addEventListener('DOMContentLoaded', function() {
            document.documentElement.setAttribute("data-sidenav-size", "condensed");
            const table = Tabulator.findTable("#forecast-table")[0];

            const parentKeys = () => Object.keys(groupedSkuData);
            let currentIndex = 0;
            let isPlaying = false;

            function renderGroup(parentKey) {
                if (!groupedSkuData[parentKey]) return;
                currentParentFilter = parentKey;
                setCombinedFilters();
            }

            document.getElementById('play-auto').addEventListener('click', () => {
                isPlaying = true;
                currentIndex = 0;
                renderGroup(parentKeys()[currentIndex]);
                document.getElementById('play-pause').style.display = 'inline-block';
                document.getElementById('play-auto').style.display = 'none';
            });

            document.getElementById('play-forward').addEventListener('click', () => {
                if (!isPlaying) return;
                currentIndex = (currentIndex + 1) % parentKeys().length;
                renderGroup(parentKeys()[currentIndex]);
            });

            document.getElementById('play-backward').addEventListener('click', () => {
                if (!isPlaying) return;
                currentIndex = (currentIndex - 1 + parentKeys().length) % parentKeys().length;
                renderGroup(parentKeys()[currentIndex]);
            });

            document.getElementById('play-pause').addEventListener('click', () => {
                isPlaying = false;
                currentParentFilter = null; // Show all data
                setCombinedFilters();
                document.getElementById('play-pause').style.display = 'none';
                document.getElementById('play-auto').style.display = 'inline-block';
            });

            const countContainer = document.getElementById('yellow-count-container');
            countContainer.classList.add('d-none');

            // Set yellow filter as default
            currentColorFilter = '';
            document.getElementById('yellow-count-container').classList.remove('d-none');
            document.getElementById('order-color-filter-dropdown').innerHTML =
                '<i class="bi bi-funnel-fill text-dark"></i> <span class="text-dark fw-bold">2 Ord</span>';
            setCombinedFilters();

            document.querySelectorAll('#order-color-filter-dropdown + .dropdown-menu [data-filter]').forEach(
                btn => {
                    btn.addEventListener('click', function() {
                        const filter = this.getAttribute('data-filter');
                        currentColorFilter = filter || null;
                        setCombinedFilters();

                        const buttonText = filter ? filter.charAt(0).toUpperCase() + filter.slice(1) +
                            ' Filter' : 'All';
                        document.getElementById('order-color-filter-dropdown').innerHTML =
                            `<i class="bi bi-funnel-fill"></i> ${buttonText}`;

                        const countContainer = document.getElementById('yellow-count-container');
                        if (filter === 'yellow') {
                            countContainer.classList.remove('d-none');
                        } else {
                            countContainer.classList.add('d-none');
                        }
                    });
                });

            document.getElementById('toggle-nr-rows').addEventListener('click', function() {
                hideNRYes = !hideNRYes;
                setCombinedFilters();

                if (currentColorFilter === 'yellow') {
                    const allData = table.getRows().map(r => r.getData());
                    const yellowCount = allData.filter(r => r.to_order >= 0 && !r.is_parent && (hideNRYes ?
                        r.nr !== 'NR' : true)).length;
                    document.getElementById('yellow-count-box').textContent =
                        `Appr Req: ${yellowCount}`;
                }

                document.getElementById('toggle-nr-rows').textContent = hideNRYes ? "Show NR" : "Hide NR";

            });

            document.getElementById('row-data-type').addEventListener('change', function(e) {
                currentRowTypeFilter = e.target.value;
                setCombinedFilters();
            });

            // Stage filter event listener
            document.getElementById('stage-filter').addEventListener('change', function(e) {
                currentStageFilter = e.target.value;
                setCombinedFilters();
            });

            // NRP filter event listener
            document.getElementById('nrp-filter').addEventListener('change', function(e) {
                currentNRPFilter = e.target.value;
                setCombinedFilters();
            });


        });

        // Scout products view handler
        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('.scouth-products-view-trigger');
            if (trigger) {
                e.preventDefault();
                e.stopPropagation();

                const encoded = trigger.getAttribute('data-item');
                if (encoded) {
                    try {
                        const rawData = JSON.parse(decodeURIComponent(encoded));
                        openModal(rawData, 'scouth products view');
                    } catch (err) {
                        console.error("Failed to parse rawData", err);
                    }
                }
            }
        });

        window.openModal = function(selectedItem, type) {
            try {
                if (type.toLowerCase() === 'scouth products view') {
                    const modalId = 'scouthProductsModal';
                    return openScouthProductsView(selectedItem, modalId);
                }
            } catch (error) {
                console.error("Error in openModal:", error);
                showNotification('danger', 'Failed to open details view. Please try again.');
            }
        };

        function openScouthProductsView(data, modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;

            const title = modal.querySelector('.modal-title');
            const body = modal.querySelector('.modal-body');

            if (!data.scout_data || !data.scout_data.all_data) {
                title.textContent = 'Scout Products View Details';
                body.innerHTML = '<div class="alert alert-warning">No scout data available</div>';
                const instance = new bootstrap.Modal(modal);
                instance.show();
                return;
            }

            // Sort by price
            const sortedProducts = [...data.scout_data.all_data].sort((a, b) => {
                const priceA = parseFloat(a.price) || Infinity;
                const priceB = parseFloat(b.price) || Infinity;
                return priceA - priceB;
            });

            title.textContent = 'Scouth Products View (Sorted by Lowest Price)';

            let html = `
                    <div><strong>Parent:</strong> ${data.Parent || 'N/A'} | <strong>SKU:</strong> ${data['(Child) sku'] || 'N/A'}</div>
                    <div class="table-responsive mt-3">
                        <table class="table table-bordered table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th><th>Price</th><th>Category</th><th>Dimensions</th><th>Image</th>
                                    <th>Quality Score</th><th>Parent ASIN</th><th>Product Rank</th>
                                    <th>Rating</th><th>Reviews</th><th>Weight</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

            sortedProducts.forEach(product => {
                html += `
                <tr>
                    <td>${product.id || 'N/A'}</td>
                    <td>${product.price ? '$' + parseFloat(product.price).toFixed(2) : 'N/A'}</td>
                    <td>${product.category || 'N/A'}</td>
                    <td>${product.dimensions || 'N/A'}</td>
                    <td>
                        ${product.image_url ? `
                                                                <a href="${product.image_url}" target="_blank">
                                                                    <img src="${product.image_url}" width="60" height="60" style="border-radius:50%;">
                                                                </a>` : 'N/A'}
                    </td>
                    <td>${product.listing_quality_score || 'N/A'}</td>
                    <td>${product.parent_asin || 'N/A'}</td>
                    <td>${product.product_rank || 'N/A'}</td>
                    <td>${product.rating || 'N/A'}</td>
                    <td>${product.reviews || 'N/A'}</td>
                    <td>${product.weight || 'N/A'}</td>
                </tr>
            `;
            });

            html += '</tbody></table></div>';
            body.innerHTML = html;

            const instance = new bootstrap.Modal(modal);
            instance.show();
        }

        document.getElementById("total-transit").addEventListener("click", function(e) {
            currentColorFilter = null;

            setCombinedFilters();

            table.setSort([{
                column: "transit",
                dir: "desc",
                sorter: function(a, b) {
                    const aValue = a.getData().transit ? 1 : 0;
                    const bValue = b.getData().transit ? 1 : 0;
                    return bValue - aValue;
                }
            }]);
        });

        document.getElementById("restock_needed").addEventListener("click", function(e) {
            currentRestockFilter = true;
            currentColorFilter = null;

            setCombinedFilters();

            table.setSort([{
                column: "ov_dil",
                dir: "desc",
                sorter: function(a, b) {
                    const aData = a.getData();
                    const aInv = parseFloat(aData.raw_data ? aData.raw_data["INV"] : aData[
                        "INV"]) || 0;
                    const aL30 = parseFloat(aData.raw_data ? aData.raw_data["L30"] : aData[
                        "L30"]) || 0;
                    const aDil = aInv === 0 ? Infinity : (aL30 / aInv) * 100;

                    const bData = b.getData();
                    const bInv = parseFloat(bData.raw_data ? bData.raw_data["INV"] : bData[
                        "INV"]) || 0;
                    const bL30 = parseFloat(bData.raw_data ? bData.raw_data["L30"] : bData[
                        "L30"]) || 0;
                    const bDil = bInv === 0 ? Infinity : (bL30 / bInv) * 100;

                    return bDil - aDil;
                }
            }]);
        });
    </script>

        <script>
            (function(){
                const select = document.currentScript.previousElementSibling;
                const setColor = (val) => {
                    if(val === 'REQ') select.style.backgroundColor = 'green';
                    else if(val === 'NR') select.style.backgroundColor = 'red';
                    else if(val === 'LATER') select.style.backgroundColor = 'yellow';
                    select.style.color = (val === 'LATER') ? 'black' : 'white';
                };
                setColor(select.value);
                select.addEventListener('change', function() {
                    setColor(this.value);
                });
            })();
        </script>
@endsection
