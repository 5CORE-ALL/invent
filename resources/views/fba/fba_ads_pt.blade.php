@extends('layouts.vertical', ['title' => 'FBA ADS PT', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
        .parent-row-bg{
            background-color: #c3efff !important;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'FBA ADS PT',
        'sub_title' => 'FBA Product Targeting Ads Data',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                                                <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            FBA Ads   PT
                        </h4>

                        <!-- Filters & Export Row -->
                        <div class="row g-2 align-items-center mb-3">
                            <!-- Filter Section -->
                            <div class="col-md-9">
                                <div class="d-flex flex-wrap gap-2">
                                    <div>
                                        <label class="form-label small text-muted mb-1">INV Filter</label>
                                        <select id="inv-filter" class="form-select form-select-sm">
                                            <option value="">All</option>
                                            <option value="red">Red (&lt; 50)</option>
                                            <option value="green">Green (≥ 50)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small text-muted mb-1">ACOS Filter</label>
                                        <select id="acos-filter" class="form-select form-select-sm">
                                            <option value="">All</option>
                                            <option value="pink">Pink (&lt; 7%)</option>
                                            <option value="green">Green (7-14%)</option>
                                            <option value="red">Red (&gt; 14%)</option>
                                            <option value="black">Black (100%)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small text-muted mb-1">CVR Filter</label>
                                        <select id="cvr-filter" class="form-select form-select-sm">
                                            <option value="">All</option>
                                            <option value="red">Red (&lt; 5%)</option>
                                            <option value="green">Green (5-10%)</option>
                                            <option value="pink">Pink (&gt; 10%)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 d-flex justify-content-end gap-2">
                                <a href="javascript:void(0)" id="export-btn" class="btn btn-sm btn-success d-flex align-items-center justify-content-center">
                                    <i class="fas fa-file-export me-1"></i> Export Excel/CSV
                                </a>
                                <button class="btn btn-success btn-md d-flex align-items-center">
                                    <span>Total Campaigns: <span id="total-campaigns" class="fw-bold ms-1 fs-5">0</span></span>
                                </button>
                                <button class="btn btn-primary btn-md d-flex align-items-center">
                                    <i class="fa fa-percent me-1"></i>
                                    <span>Of Total: <span id="percentage-campaigns" class="fw-bold ms-1 fs-5">0%</span></span>
                                </button>
                            </div>
                        </div>

                        <!-- Search and Controls Row -->
                        <div class="row g-3 align-items-center">
                            <!-- Left: Search -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <input type="text" id="global-search" class="form-control form-control-md" placeholder="Search SKU...">
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

@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <!-- SheetJS for Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.body.style.zoom = "85%";

            console.log("AJAX URL:", "{{ url('fba-ads-pt-data-json') }}");

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            var table = new Tabulator("#budget-under-table", {
                index: "SKU",
                ajaxURL: "{{ url('fba-ads-pt-data-json') }}",
                ajaxResponse:function(url, params, response){
                    console.log("AJAX URL:", url);
                    console.log("Response from server:", response);
                    console.log("Number of records:", Array.isArray(response) ? response.length : 'NOT AN ARRAY');
                    console.log("Response type:", typeof response);
                    console.log("First record:", response && response[0] ? response[0] : 'NO DATA');
                    return response;
                },
                ajaxError:function(error){
                    console.error("AJAX Error:", error);
                    console.error("Error details:", JSON.stringify(error));
                },
                layout: "fitData",
                pagination: true,
                paginationSize: 50,
                paginationCounter: "rows",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",             
                virtualDom: true,
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = (data.SKU || "").toLowerCase().trim();

                    if (sku.includes("parent ")) {
                        row.getElement().classList.add("parent-row-bg");
                    }
                },
                columns: [
                    {
                        title: "Parent",
                        field: "Parent"
                    },
                    {
                        title: "SKU",
                        field: "SKU",
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
                        title: "Campaign Name",
                        field: "Campaign_Name",
                        width: 200,
                        headerSort: true
                    },
                    {
                        title: "FBA QTY",
                        field: "FBA_Quantity",
                        visible: false
                    },
                    {
                        title: "Shopify INV",
                        field: "Shopify_INV",
                        visible: false
                    },
                    {
                        title: "Shopify OV L30",
                        field: "Shopify_OV_L30",
                        visible: false
                    },
                    {
                        title: "DIL",
                        field: "Dil",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            const color = getDilColor(value / 100);
                            return `<div class="text-center"><span class="dil-percent-value ${color}">${Math.round(value)}%</span></div>`;
                        },
                        visible: false
                    },
                    {
                        title: "L30 Units",
                        field: "l30_units",
                        visible: false
                    },
                    {
                        title: "FBA DIL",
                        field: "FBA_Dil",
                        formatter: function(cell) {
                            const value = parseFloat(cell.getValue() || 0);
                            const color = getDilColor(value / 100);
                            return `<div class="text-center"><span class="dil-percent-value ${color}">${Math.round(value)}%</span></div>`;
                        },
                        visible: false
                    },
                    {
                        title: "IMP L30",
                        field: "Ads_L30_Impressions",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let impressions_l30 = cell.getValue();
                            return `
                                <span>${parseFloat(cell.getValue() || 0).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary impressions_l30_btn" 
                                    data-impression-l30="${impressions_l30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "IMP L60",
                        field: "Ads_L60_Impressions",
                        hozAlign: "right",
                        formatter: function(cell) {
                            return `
                                <span>${parseFloat(cell.getValue() || 0).toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "IMP L15",
                        field: "Ads_L15_Impressions",
                        hozAlign: "right",
                        formatter: function(cell) {
                            return `
                                <span>${parseFloat(cell.getValue() || 0).toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "IMP L7",
                        field: "Ads_L7_Impressions",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let impressions_l7 = cell.getValue();
                            return `
                                <span>${impressions_l7}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Clicks L30",
                        field: "Ads_L30_Clicks",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let color = value < 50 ? "red" : "green";
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}
                                </span>
                                <i class="fa fa-info-circle text-primary clicks_l30_btn" 
                                data-clicks-l30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "Clicks L60",
                        field: "Ads_L60_Clicks",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let color = value < 50 ? "red" : "green";
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Clicks L15",
                        field: "Ads_L15_Clicks",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let color = value < 50 ? "red" : "green";
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Clicks L7",
                        field: "Ads_L7_Clicks",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let color = value < 50 ? "red" : "green";
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Spend L30",
                        field: "Ads_L30_Spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary spend_l30_btn" 
                                data-spend-l30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "Spend L60",
                        field: "Ads_L60_Spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Spend L15",
                        field: "Ads_L15_Spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Spend L7",
                        field: "Ads_L7_Spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Ad Sales L30",
                        field: "Ads_L30_Sales",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary ad_sales_l30_btn" 
                                    data-ad_sales-l30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "Ad Sales L60",
                        field: "Ads_L60_Sales",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Ad Sales L15",
                        field: "Ads_L15_Sales",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Ad Sales L7",
                        field: "Ads_L7_Sales",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Ad Sold L30",
                        field: "Ads_L30_Orders",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary ad_sold_l30_btn" 
                                    data-ad_sold-l30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "Ad Sold L60",
                        field: "Ads_L60_Orders",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Ad Sold L15",
                        field: "Ads_L15_Orders",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "Ad Sold L7",
                        field: "Ads_L7_Orders",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "ACOS L30",
                        field: "Ads_L30_ACOS",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let row = cell.getRow().getData();
                            let adSales = parseFloat(row.Ads_L30_Sales || 0);

                            if (adSales === 0) {
                                value = 100;
                            }

                            let color = "green";
                            if(value == 100){
                                color = "#000000";
                            }else if (value < 7) {
                                color = "#e83e8c";
                            } else if (value >= 7 && value <= 14) {
                                color = "green";
                            } else if (value > 14) {
                                color = "red";
                            }

                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}%
                                </span>
                                <i class="fa fa-info-circle text-primary acos_l30_btn" 
                                    data-acos-l30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "ACOS L60",
                        field: "Ads_L60_ACOS",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let row = cell.getRow().getData();
                            let adSales = parseFloat(row.Ads_L60_Sales || 0);

                            if (adSales === 0) {
                                value = 100;
                            }

                            let color = "green";
                            if(value == 100){
                                color = "#000000";
                            }else if (value < 7) {
                                color = "#e83e8c";
                            }else if (value >= 7 && value <= 14) {
                                color = "green";
                            } else if (value > 14) {
                                color = "red";
                            }

                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}%
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "ACOS L15",
                        field: "Ads_L15_ACOS",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let row = cell.getRow().getData();
                            let adSales = parseFloat(row.Ads_L15_Sales || 0);

                            if (adSales === 0) {
                                value = 100;
                            }

                            let color = "green";
                            if(value == 100){
                                color = "#000000";
                            }else if (value < 7) {
                                color = "#e83e8c";
                            }else if (value >= 7 && value <= 14) {
                                color = "green";
                            } else if (value > 14) {
                                color = "red";
                            }

                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}%
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "ACOS L7",
                        field: "Ads_L7_ACOS",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let row = cell.getRow().getData();
                            let adSales = parseFloat(row.Ads_L7_Sales || 0);

                            if (adSales === 0) {
                                value = 100;
                            }

                            let color = "green";
                            if(value == 100){
                                color = "#000000";
                            }else if (value < 7) {
                                color = "#e83e8c";
                            }else if (value >= 7 && value <= 14) {
                                color = "green";
                            } else if (value > 14) {
                                color = "red";
                            }

                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}%
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "CPC L30",
                        field: "Ads_L30_CPC",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var clicks = parseFloat(row.Ads_L30_Clicks) || 0;
                            var spend = parseFloat(row.Ads_L30_Spend) || 0;
                            var cpc_l30 = clicks > 0 ? (spend / clicks) : 0;

                            return `
                                <span>
                                    ${cpc_l30.toFixed(2)}
                                </span>
                                <i class="fa fa-info-circle text-primary cpc_l30_btn" 
                                    data-cpc-l30="${cpc_l30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "CPC L60",
                        field: "Ads_L60_CPC",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var clicks = parseFloat(row.Ads_L60_Clicks) || 0;
                            var spend = parseFloat(row.Ads_L60_Spend) || 0;
                            var cpc_l60 = clicks > 0 ? (spend / clicks) : 0;
                            return cpc_l60.toFixed(2);
                        },
                        visible: false
                    },
                    {
                        title: "CPC L15",
                        field: "Ads_L15_CPC",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var clicks = parseFloat(row.Ads_L15_Clicks) || 0;
                            var spend = parseFloat(row.Ads_L15_Spend) || 0;
                            var cpc_l15 = clicks > 0 ? (spend / clicks) : 0;
                            return cpc_l15.toFixed(2);
                        },
                        visible: false
                    },
                    {
                        title: "CPC L7",
                        field: "Ads_L7_CPC",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var clicks = parseFloat(row.Ads_L7_Clicks) || 0;
                            var spend = parseFloat(row.Ads_L7_Spend) || 0;
                            var cpc_l7 = clicks > 0 ? (spend / clicks) : 0;
                            return `
                                <span>
                                    ${cpc_l7.toFixed(2)}
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "CVR L30",
                        field: "Ads_L30_CVR",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var cvr_l30 = parseFloat(cell.getValue() || 0);
                            let color = "";
                            if (cvr_l30 < 5) {
                                color = "red";
                            } else if (cvr_l30 >= 5 && cvr_l30 <= 10) {
                                color = "green";
                            } else if (cvr_l30 > 10){
                                color = "#e83e8c";
                            }
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${cvr_l30.toFixed(0)}%
                                </span>
                                <i class="fa fa-info-circle text-primary cvr_l30_btn" 
                                    data-cvr-l30="${cvr_l30}" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "CVR L60",
                        field: "Ads_L60_CVR",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ad_sold_l60 = parseFloat(row.Ads_L60_Orders || 0);
                            var clicks_l60 = parseFloat(row.Ads_L60_Clicks || 0);
                            
                            var cvr_l60 = (clicks_l60 > 0) ? (ad_sold_l60 / clicks_l60) * 100 : 0;
                            let color = "";
                            if (cvr_l60 < 5) {
                                color = "red";
                            } else if (cvr_l60 >= 5 && cvr_l60 <= 10) {
                                color = "green";
                            } else if (cvr_l60 > 10){
                                color = "#e83e8c";
                            }
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${cvr_l60.toFixed(0)}%
                                </span>
                            `;

                        },
                        visible: false
                    },
                    {
                        title: "CVR L15",
                        field: "Ads_L15_CVR",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ad_sold_l15 = parseFloat(row.Ads_L15_Orders || 0);
                            var clicks_l15 = parseFloat(row.Ads_L15_Clicks || 0);

                            var cvr_l15 = (clicks_l15 > 0) ? (ad_sold_l15 / clicks_l15) * 100 : 0;
                            let color = "";
                            if (cvr_l15 < 5) {
                                color = "red";
                            } else if (cvr_l15 >= 5 && cvr_l15 <= 10) {
                                color = "green";
                            } else if (cvr_l15 > 10){
                                color = "#e83e8c";
                            }
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${cvr_l15.toFixed(0)}%
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "CVR L7",
                        field: "Ads_L7_CVR",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ad_sold_l7 = parseFloat(row.Ads_L7_Orders || 0);
                            var clicks_l7 = parseFloat(row.Ads_L7_Clicks || 0);

                            var cvr_l7 = (clicks_l7 > 0) ? (ad_sold_l7 / clicks_l7) * 100 : 0;
                            let color = "";
                            if (cvr_l7 < 5) {
                                color = "red";
                            } else if (cvr_l7 >= 5 && cvr_l7 <= 10) {
                                color = "green";
                            } else if (cvr_l7 > 10){
                                color = "#e83e8c";
                            }
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${cvr_l7.toFixed(0)}%
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "TPFT%",
                        field: "TPFT",
                        hozAlign: "center",
                        formatter: function(cell){
                            let value = parseFloat(cell.getValue()) || 0;
                            let percent = value.toFixed(0);
                            let color = "";

                            if (value < 10) {
                                color = "red";
                            } else if (value >= 10 && value < 15) {
                                color = "#ffc107";
                            } else if (value >= 15 && value < 20) {
                                color = "blue";
                            } else if (value >= 20 && value <= 40) {
                                color = "green";
                            } else if (value > 40) {
                                color = "#e83e8c";
                            }

                            return `
                                <span style="font-weight:600; color:${color};">
                                    ${percent}%
                                </span>
                            `;
                        }
                    }
                ]
            });

            table.on("tableBuilt", function() {

                function combinedFilter(data) {

                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal && !(data.SKU?.toLowerCase().includes(searchVal))) {
                        return false;
                    }

                    let invFilterVal = $("#inv-filter").val();
                    if (invFilterVal === "red") {
                        if (parseFloat(data.FBA_Quantity) >= 50) return false;
                    } else if (invFilterVal === "green") {
                        if (parseFloat(data.FBA_Quantity) < 50) return false;
                    }

                    let acosFilterVal = $("#acos-filter").val();
                    if (acosFilterVal) {
                        let acosFields = ["Ads_L30_ACOS"];

                        let matched = acosFields.every(field => {
                            let val = parseFloat(data[field]) || 0;
                            let adSales = parseFloat(data.Ads_L30_Sales) || 0;
                            if (adSales === 0) val = 100;

                            if (acosFilterVal === "pink") {
                                return val < 7;
                            }
                            if (acosFilterVal === "green") {
                                return val >= 7 && val <= 14;
                            }
                            if (acosFilterVal === "red") {
                                return val > 14;
                            }
                            if (acosFilterVal === "black") {
                                return val === 100;
                            }
                            return false;
                        });

                        if (!matched) return false;
                    }

                    let cvrFilterVal = $("#cvr-filter").val();
                    if (cvrFilterVal) {
                        let cvrFields = ["Ads_L30_CVR"];

                        let matched = cvrFields.every(field => {
                            let val = parseFloat(data[field]) || 0;

                            if (cvrFilterVal === "pink") {
                                return val > 10;
                            }
                            if (cvrFilterVal === "green") {
                                return val >= 5 && val <= 10;
                            }
                            if (cvrFilterVal === "red") {
                                return val < 5;
                            }
                            return false;
                        });

                        if (!matched) return false;
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

                    const totalEl = document.getElementById("total-campaigns");
                    const percentageEl = document.getElementById("percentage-campaigns");

                    if (totalEl) totalEl.innerText = filtered;
                    if (percentageEl) percentageEl.innerText = percentage + "%";
                }

                table.on("dataFiltered", updateCampaignStats);
                table.on("pageLoaded", updateCampaignStats);
                table.on("dataProcessed", updateCampaignStats);

                $("#global-search").on("keyup", function() {
                    table.setFilter(combinedFilter);
                });

                $("#inv-filter,#acos-filter,#cvr-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                });

                updateCampaignStats();
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["FBA_Quantity", "Shopify_INV", "Shopify_OV_L30", "Dil", "l30_units", "FBA_Dil"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("impressions_l30_btn")) {
                    let colsToToggle = ["Ads_L60_Impressions", "Ads_L15_Impressions", "Ads_L7_Impressions"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("clicks_l30_btn")) {
                    let colsToToggle = ["Ads_L15_Clicks", "Ads_L7_Clicks", "Ads_L60_Clicks"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("spend_l30_btn")) {
                    let colsToToggle = ["Ads_L15_Spend", "Ads_L7_Spend", "Ads_L60_Spend"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("ad_sales_l30_btn")) {
                    let colsToToggle = ["Ads_L15_Sales", "Ads_L7_Sales", "Ads_L60_Sales"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("ad_sold_l30_btn")) {
                    let colsToToggle = ["Ads_L15_Orders", "Ads_L7_Orders", "Ads_L60_Orders"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("acos_l30_btn")) {
                    let colsToToggle = ["Ads_L15_ACOS", "Ads_L7_ACOS", "Ads_L60_ACOS"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("cpc_l30_btn")) {
                    let colsToToggle = ["Ads_L15_CPC", "Ads_L7_CPC", "Ads_L60_CPC"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("cvr_l30_btn")) {
                    let colsToToggle = ["Ads_L15_CVR", "Ads_L7_CVR", "Ads_L60_CVR"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.getElementById("export-btn").addEventListener("click", function () {
                let allData = table.getData("active"); 

                if (allData.length === 0) {
                    alert("No data available to export!");
                    return;
                }

                let exportData = allData.map(row => ({ ...row }));

                let ws = XLSX.utils.json_to_sheet(exportData);
                let wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "FBA_Ads");

                XLSX.writeFile(wb, "fba_ads_kw_report.xlsx");
            });
        });
    </script>
@endsection

