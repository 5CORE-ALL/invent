@extends('layouts.vertical', ['title' => 'FACEBOOK - VIDEOADS', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-header {
            background: linear-gradient(90deg, #D8F3F3 0%, #D8F3F3 100%);
            border-bottom: 1px solid #403f3f;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
        }

        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #D8F3F3;
            border-right: 1px solid #262626;
            padding: 16px 10px;
            font-weight: 700;
            color: #1e293b;
            font-size: 1.08rem;
            letter-spacing: 0.02em;
            transition: background 0.2s;
        }

        .tabulator .tabulator-header .tabulator-col:hover {
            background: #D8F3F3;
            color: #2563eb;
        }

        .tabulator-row {
            background-color: #fff !important;
            transition: background 0.18s;
        }

        .tabulator-row:nth-child(even) {
            background-color: #f8fafc !important;
        }

        .tabulator .tabulator-cell {
            text-align: center;
            padding: 14px 10px;
            border-right: 1px solid #262626;
            border-bottom: 1px solid #262626;
            font-size: 1rem;
            color: #22223b;
            vertical-align: middle;
            transition: background 0.18s, color 0.18s;
        }

        .tabulator .tabulator-cell:focus {
            outline: 1px solid #262626;
            background: #e0eaff;
        }

        .tabulator-row:hover {
            background-color: #dbeafe !important;
        }

        .parent-row {
            background-color: #e0eaff !important;
            font-weight: 700;
        }

        #account-health-master .tabulator {
            border-radius: 18px;
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .tabulator .tabulator-row .tabulator-cell:last-child,
        .tabulator .tabulator-header .tabulator-col:last-child {
            border-right: none;
        }

        .tabulator .tabulator-footer {
            background: #f4f7fa;
            border-top: 1px solid #262626;
            font-size: 1rem;
            color: #4b5563;
            padding: 5px;
            height: 100px;
        }

        .tabulator .tabulator-footer:hover {
            background: #e0eaff;
        }

        @media (max-width: 768px) {

            .tabulator .tabulator-header .tabulator-col,
            .tabulator .tabulator-cell {
                padding: 8px 2px;
                font-size: 0.95rem;
            }
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

        .green-bg {
            color: #05bd30 !important;
        }

        .pink-bg {
            color: #ff01d0 !important;
        }

        .red-bg {
            color: #ff2727 !important;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'FACEBOOK - VIDEOADS',
        'sub_title' => 'FACEBOOK - VIDEOADS',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        {{-- <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            ACOS CONTROL KW
                        </h4> --}}

                        <!-- Filters Row -->
                        <div class="row g-3 mb-3">
                            <!-- Inventory Filters -->
                            {{-- <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <select id="clicks-filter" class="form-select form-select-md" style="width: 175px;">
                                        <option value="">Select CLICKS L30</option>
                                        <option value="ALL">ALL</option>
                                        <option value="CLICKS_L30">CLICKS L30 > 25</option>
                                        <option value="OTHERS">OTHERS</option>
                                    </select>

                                    <select id="inv-filter" class="form-select form-select-md">
                                        <option value="">Select INV</option>
                                        <option value="ALL">ALL</option>
                                        <option value="INV_0">0 INV</option>
                                        <option value="OTHERS">OTHERS</option>
                                    </select>

                                    <select id="nrl-filter" class="form-select form-select-md">
                                        <option value="">Select NRL</option>
                                        <option value="NRL">NRL</option>
                                        <option value="RL">RL</option>
                                    </select>

                                    <select id="nra-filter" class="form-select form-select-md">
                                        <option value="">Select NRA</option>
                                        <option value="NRA">NRA</option>
                                        <option value="RA">RA</option>
                                        <option value="LATER">LATER</option>
                                    </select>

                                    <select id="fba-filter" class="form-select form-select-md">
                                        <option value="">Select FBA</option>
                                        <option value="FBA">FBA</option>
                                        <option value="FBM">FBM</option>
                                        <option value="BOTH">BOTH</option>
                                    </select>
                                </div>
                            </div> --}}

                            <!-- Stats -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button id="apr-all-sbid-btn" class="btn btn-info btn-sm d-none">
                                        APR ALL SBID
                                    </button>
                                    <!-- <button class="btn btn-success btn-md">
                                        <i class="fa fa-arrow-up me-1"></i>
                                        Need to increase bids: <span id="total-campaigns" class="fw-bold ms-1 fs-4">0</span>
                                    </button>
                                    <button class="btn btn-primary btn-md">
                                        <i class="fa fa-percent me-1"></i>
                                        of Total: <span id="percentage-campaigns" class="fw-bold ms-1 fs-4">0%</span>
                                    </button> -->
                                </div>
                            </div>
                        </div>

                        <!-- Search and Controls Row -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <div class="input-group">
                                        <input type="text" id="global-search" class="form-control form-control-md"
                                            placeholder="Search campaign...">
                                    </div>
                                    <select id="status-filter" class="form-select form-select-md" style="width: 140px;">
                                        <option value="">All Status</option>
                                        <option value="ENABLED">Enabled</option>
                                        <option value="PAUSED">Paused</option>
                                        <option value="ARCHIVED">Archived</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="d-flex align-items-center mb-3 gap-2">

                        <button type="button" class="btn btn-sm btn-primary mr-2" id="import-btn">Import</button>
                        <a href="{{ route('listing_amazon.export') }}" class="btn btn-sm btn-success mr-3">Export</a>
                    </div> -->

                    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Import Editable Fields</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">

                                <a href="{{ asset('sample_excel/sample_gmv_ads.csv') }}" download class="btn btn-outline-secondary mb-3">📄 Download Sample File</a>

                                <input type="file" id="importFile" name="file" accept=".xlsx,.xls,.csv" class="form-control" />
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" id="confirmImportBtn">Import</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table Section -->
                    <div id="budget-under-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="progress-overlay"
        style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
            <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-3" style="color: white; font-size: 1.2rem; font-weight: 500;">
                Updating campaigns...
            </div>
            <div style="color: #a3e635; font-size: 0.9rem; margin-top: 0.5rem;">
                Please wait while we process your request
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {

            function showNotification(type, message) {
                const notification = $(`
                    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
                        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                            ${message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                `);

                $('body').append(notification);

                setTimeout(() => {
                    notification.find('.alert').alert('close');
                }, 3000);
            }

            const invFilter  = document.querySelector("#inv-filter");
            const nrlFilter  = document.querySelector("#nrl-filter");
            const nraFilter  = document.querySelector("#nra-filter");
            const fbaFilter  = document.querySelector("#fba-filter");


            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            var table = new Tabulator("#budget-under-table", {
                index: "Sku",
                ajaxURL: "/facebook-image-ads-data",
                layout: "fitDataFill",
                pagination: "local",
                paginationSize: 25,
                movableColumns: true,
                resizableColumns: true,
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["Sku"] || '';

                    if (sku.toUpperCase().includes("PARENT")) {
                        row.getElement().classList.add("parent-row");
                    }
                },
                columns: [{
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        headerSort: false,
                        width: 50
                    },
                    {
                        title: "Parent",
                        field: "parent"
                    },
                    {
                        title: "SKU",
                        field: "sku",
                        formatter: function(cell) {
                            let sku = cell.getValue();
                            return `
                                <span>${sku}</span>
                                <i class="fa fa-info-circle text-primary toggle-cols-btn" 
                                    data-sku="${sku}" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                                `;
                        }
                    },
                    {
                        title: "INV",
                        field: "INV",
                        visible: false
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        visible: false
                    },
                    {
                        title: "DIL %",
                        field: "DIL %",
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
                        },
                        visible: false
                    },
                    {
                        title: "S L30",
                        field: "s_l30",
                        visible: false
                    },
                    {
                        title: "CAMPAIGN NAME",
                        field: "campaignName"
                    },
                    {
                        title: "CAMPAIGN ID",
                        field: "campaign_id"
                    },
                    {
                        title: "LANDING PAGE",
                        field: "landing_page"
                    },
                    {
                        title: "STATUS",
                        field: "status"
                    },
                    {
                        title: "BUDGET",
                        field: "bgt",
                        visible: true
                    },
                    {
                        title: "IMP YEAR",
                        field: "imp_year",
                        formatter: function(cell) {
                            let imp_year = cell.getValue();
                            return `
                                <span>${imp_year}</span>
                                <i class="fa fa-info-circle text-primary toggle-imp-cols-btn" 
                                    data-sku="${imp_year}" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                                `;
                        }
                    },
                    {
                        title: "IMP L60",
                        field: "imp_l60",
                        visible: false
                    },
                    {
                        title: "IMP L30",
                        field: "imp_l30",
                        visible: false
                    },
                    {
                        title: "IMP L7",
                        field: "imp_l7",
                        visible: false
                    },
                    {
                        title: "CLKS YEAR",
                        field: "clks_year",
                        formatter: function(cell) {
                            let clks_year = cell.getValue();
                            return `
                                <span>${clks_year}</span>
                                <i class="fa fa-info-circle text-primary toggle-clks-cols-btn" 
                                    data-sku="${clks_year}" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                                `;
                        }
                    },
                    {
                        title: "CLKS L60",
                        field: "clks_l60",
                        visible: false
                    },
                    {
                        title: "CLKS L30",
                        field: "clks_l30",
                        visible: false
                    },
                    {
                        title: "CLKS L7",
                        field: "clks_l7",
                        visible: false
                    },
                    {
                        title: "CTR YEAR",
                        field: "ctr_year",
                        formatter: function(cell) {
                            let ctr_year = cell.getValue();
                            return `
                                <span>${ctr_year}</span>
                                <i class="fa fa-info-circle text-primary toggle-ctr-cols-btn" 
                                    data-sku="${ctr_year}" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                                `;
                        }
                    },
                    {
                        title: "CTR L60",
                        field: "ctr_l60",
                        visible: false
                    },
                    {
                        title: "CTR L30",
                        field: "ctr_l30",
                        visible: false
                    },
                    {
                        title: "CTR L7",
                        field: "ctr_l7",
                        visible: false
                    },
                    {
                        title: "SPEND YEAR",
                        field: "spend_year",
                        formatter: function(cell) {
                            let spend_year = cell.getValue();
                            return `
                                <span>${spend_year}</span>
                                <i class="fa fa-info-circle text-primary toggle-spend-cols-btn" 
                                    data-sku="${spend_year}" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                                `;
                        }
                    },
                    {
                        title: "SPEND L60",
                        field: "spend_l60",
                        visible: false
                    },
                    {
                        title: "SPEND L30",
                        field: "spend_l30",
                        visible: false
                    },
                    {
                        title: "SPEND L7",
                        field: "spend_l7",
                        visible: false
                    },
                    {
                        title: "SOLD YEAR",
                        field: "sold_year",
                        formatter: function(cell) {
                            let sold_year = cell.getValue();
                            return `
                                <span>${sold_year}</span>
                                <i class="fa fa-info-circle text-primary toggle-sold-cols-btn" 
                                    data-sku="${sold_year}" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                                `;
                        }
                    },
                    {
                        title: "SOLD L60",
                        field: "sold_l60",
                        visible: false
                    },
                    {
                        title: "SOLD L30",
                        field: "sold_l30",
                        visible: false
                    },
                    {
                        title: "SOLD L7",
                        field: "sold_l7",
                        visible: false
                    },
                    {
                        title: "SALES YEAR",
                        field: "sales_year",
                        formatter: function(cell) {
                            let sales_year = cell.getValue();
                            return `
                                <span>${sales_year}</span>
                                <i class="fa fa-info-circle text-primary toggle-sales-cols-btn" 
                                    data-sku="${sales_year}" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                                `;
                        }
                    },
                    {
                        title: "SALES L60",
                        field: "sales_l60",
                        visible: false
                    },
                    {
                        title: "SALES L30",
                        field: "sales_l30",
                        visible: false
                    },
                    {
                        title: "SALES L7",
                        field: "sales_l7",
                        visible: false
                    },
                    {
                        title: "ACOS YEAR",
                        field: "acos_year",
                        formatter: function(cell) {
                            let acos_year = cell.getValue();
                            return `
                                <span>${acos_year}</span>
                                <i class="fa fa-info-circle text-primary toggle-acos-cols-btn" 
                                    data-sku="${acos_year}" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                                `;
                        }
                    },
                    {
                        title: "ACOS L60",
                        field: "acos_l60",
                        visible: false
                    },
                    {
                        title: "ACOS L30",
                        field: "acos_l30",
                        visible: false
                    },
                    {
                        title: "ACOS L7",
                        field: "acos_l7",
                        visible: false
                    },
                    {
                        title: "CVR YEAR",
                        field: "cvr_year",
                        formatter: function(cell) {
                            let cvr_year = cell.getValue();
                            return `
                                <span>${cvr_year}</span>
                                <i class="fa fa-info-circle text-primary toggle-cvr-cols-btn" 
                                    data-sku="${cvr_year}" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                                `;
                        }
                    },
                    {
                        title: "CVR L60 %",
                        field: "cvr_l60",
                        formatter: function(cell, row) {
                            let data = row || cell.getRow?.().getData?.() || {};
                            let clicks = data.clks_60 || 0;
                            let adSold = data.sold_60 || 0;

                            let value = clicks > 0 ? (adSold / clicks) * 100 : 0;
                            let cvr = value.toFixed(2);

                            let color = "";
                            if (value < 5) {
                                color = "red";
                            } else if (value >= 5 && value <= 10) {
                                color = "green";
                            } else if (value > 10) {
                                color = "pink";
                            }

                            return `
                                <span class="dil-percent-value ${color}">
                                    ${cvr}%
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "CVR L30 %",
                        field: "cvr_l30",
                        formatter: function(cell, row) {
                            let data = row || cell.getRow?.().getData?.() || {};
                            let clicks = data.clks_30 || 0;
                            let adSold = data.sold_30 || 0;

                            let value = clicks > 0 ? (adSold / clicks) * 100 : 0;
                            let cvr = value.toFixed(2);

                            let color = "";
                            if (value < 5) {
                                color = "red";
                            } else if (value >= 5 && value <= 10) {
                                color = "green";
                            } else if (value > 10) {
                                color = "pink";
                            }

                            return `
                                <span class="dil-percent-value ${color}">
                                    ${cvr}%
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "CVR L7 %",
                        field: "cvr_l7",
                        formatter: function(cell, row) {
                            let data = row || cell.getRow?.().getData?.() || {};
                            let clicks = data.clks_7 || 0;
                            let adSold = data.sold_7 || 0;

                            let value = clicks > 0 ? (adSold / clicks) * 100 : 0;
                            let cvr = value.toFixed(2);

                            let color = "";
                            if (value < 5) {
                                color = "red";
                            } else if (value >= 5 && value <= 10) {
                                color = "green";
                            } else if (value > 10) {
                                color = "pink";
                            }

                            return `
                                <span class="dil-percent-value ${color}">
                                    ${cvr}%
                                </span>
                            `;
                        },
                        visible: false
                    },
                ],
                ajaxResponse: function(url, params, response) {
                    return response.data;
                }
            });

            table.on("rowSelectionChanged", function(data, rows) {
                if (data.length > 0) {
                    document.getElementById("apr-all-sbid-btn").classList.remove("d-none");
                } else {
                    document.getElementById("apr-all-sbid-btn").classList.add("d-none");
                }
            });

            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("editable-select")) {
                    let sku = e.target.getAttribute("data-sku");
                    let field = e.target.getAttribute("data-field");
                    let value = e.target.value;

                    fetch('/update-amazon-nr-nrl-fba', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                    .getAttribute('content')
                            },
                            body: JSON.stringify({
                                sku: sku,
                                field: field,
                                value: value
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            console.log(data);
                        })
                        .catch(err => console.error(err));
                }
            });

            table.on("tableBuilt", function() {
                function combinedFilter(data) {
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal && !(data.campaignName?.toLowerCase().includes(searchVal))) {
                        return false;
                    }

                    let statusVal = $("#status-filter").val();
                    if (statusVal && data.campaignStatus !== statusVal) {
                        return false;
                    }

                    let clicksFilterVal = $("#clicks-filter").val();
                    let clicks_L30 = parseFloat(data.clicks_L30) || 0;
                    if (clicksFilterVal === "CLICKS_L30") {
                        if (clicks_L30 <= 25) return false;
                    } else if (clicksFilterVal === "OTHERS") {
                        if (clicks_L30 > 25) return false;
                    }

                    let invFilterVal = $("#inv-filter").val();
                    if (invFilterVal === "INV_0") {
                        if (parseFloat(data.INV) !== 0) return false;
                    } else if (invFilterVal === "OTHERS") {
                        if (parseFloat(data.INV) === 0) return false;
                    }

                    let nrlFilterVal = $("#nrl-filter").val();
                    if (nrlFilterVal) {
                        let rowSelect = document.querySelector(
                            `select[data-sku="${data.sku}"][data-field="NRL"]`
                        );
                        let rowVal = rowSelect ? rowSelect.value : "";
                        if (!rowVal) rowVal = data.NRL || "";
                        if (rowVal !== nrlFilterVal) return false;
                    }

                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        let rowSelect = document.querySelector(
                            `select[data-sku="${data.sku}"][data-field="NR"]`
                        );
                        let rowVal = rowSelect ? rowSelect.value : "";
                        if (!rowVal) rowVal = data.NR || "";
                        if (rowVal !== nraFilterVal) return false;
                    }

                    let fbaFilterVal = $("#fba-filter").val();
                    if (fbaFilterVal) {
                        let rowSelect = document.querySelector(
                            `select[data-sku="${data.sku}"][data-field="FBA"]`
                        );
                        let rowVal = rowSelect ? rowSelect.value : "";
                        if (!rowVal) rowVal = data.FBA || "";
                        if (rowVal !== fbaFilterVal) return false;
                    }

                    return true;
                }

                table.setFilter(combinedFilter);

                function updateCampaignStats() {
                    let allRows = table.getData();
                    let filteredRows = allRows.filter(combinedFilter);

                    let total = allRows.length;
                    let filtered = filteredRows.length;

                    let percentage = total > 0 ? ((filtered / total) * 100).toFixed(0) : 0;

                    // document.getElementById("total-campaigns").innerText = filtered;
                    // document.getElementById("percentage-campaigns").innerText = percentage + "%";
                }

                table.on("dataFiltered", updateCampaignStats);
                table.on("pageLoaded", updateCampaignStats);
                table.on("dataProcessed", updateCampaignStats);

                $("#global-search").on("keyup", function() {
                    table.setFilter(combinedFilter);
                });

                $("#status-filter, #inv-filter, #nrl-filter, #nra-filter, #fba-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                });

                updateCampaignStats();
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["INV", "L30", "DIL %", "s_l30", "A_L30", "A DIL %", "NRL", "NRA", "FBA"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-imp-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["IMP L60", "IMP L30", "IMP L7"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-clks-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["CLKS L60", "CLKS L30", "CLKS L7"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-ctr-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["CTR L60", "CTR L30", "CTR L7"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-spend-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["SPEND L60", "SPEND L30", "SPEND L7"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-sold-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["SOLD L60", "SOLD L30", "SOLD L7"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-sales-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["SALES L60", "SALES L30", "SALES L7"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-acos-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["ACOS L60", "ACOS L30", "ACOS L7"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cvr-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["CVR L60 %", "CVR L30 %", "CVR L7 %"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.getElementById("apr-all-sbid-btn").addEventListener("click", function() {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                var filteredData = table.getSelectedRows();

                var campaignIds = [];
                var bgts = [];

                filteredData.forEach(function(row) {
                    var rowEl = row.getElement();
                    if (rowEl && rowEl.offsetParent !== null) {
                        var rowData = row.getData();
                        var acos = parseFloat(rowData.acos_L30) || 0;

                        if (acos > 0) {
                            var sbgtInput = rowEl.querySelector('.sbgt-input');
                            var sbgtValue = sbgtInput ? parseFloat(sbgtInput.value) || 0 : 0;

                            campaignIds.push(rowData.campaign_id);
                            bgts.push(sbgtValue);
                        }
                    }
                });

                console.log("Campaign IDs:", campaignIds);
                console.log("Bids:", bgts);

                fetch('/update-amazon-campaign-bgt-price', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .getAttribute('content')
                        },
                        body: JSON.stringify({
                            campaign_ids: campaignIds,
                            bgts: bgts
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        console.log("Backend response:", data);
                        if (data.status === 200) {
                            alert("Campaign budget updated successfully!");
                        } else {
                            alert("Something went wrong: " + data.message);
                        }
                    })
                    .then(() => {
                        table.redraw();
                    })
                    .catch(err => {
                        console.error(err);
                        alert("Request failed: " + err.message);
                    })
                    .finally(() => {
                        overlay.style.display = "none";
                    });
            });

            function updateBid(sbgtValue, campaignId) {
                const overlay = document.getElementById("progress-overlay");
                overlay.style.display = "flex";

                console.log("Updating bid for Campaign ID:", campaignId, "New Bid:", sbgtValue);

                fetch('/update-amazon-campaign-bgt-price', {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                'content')
                        },
                        body: JSON.stringify({
                            campaign_ids: [campaignId],
                            bgts: [sbgtValue]
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        console.log("Backend response:", data);
                        if (data.status === 200) {
                            alert("Campaign budget updated successfully!");
                        } else {
                            alert("Something went wrong: " + data.message);
                        }
                    })
                    .then(() => {
                        table.redraw();
                    })
                    .catch(err => {
                        console.error(err);
                        alert("Request failed: " + err.message);
                    })
                    .finally(() => {
                        overlay.style.display = "none";
                    });
            }

            document.body.style.zoom = "78%";

            $('#import-btn').on('click', function () {
                $('#importModal').modal('show');
            });

            $(document).on('click', '#confirmImportBtn', function () {
                let file = $('#importFile')[0].files[0];
                if (!file) {
                    alert('Please select a file to import.');
                    return;
                }

                let formData = new FormData();
                formData.append('file', file);
            });
        });
    </script>
@endsection
