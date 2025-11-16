


@extends('layouts.vertical', ['title' => 'Google Shopping Ads Report', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
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
        'page_title' => 'Google Shopping Ads Report',
        'sub_title' => 'Google Shopping Ads Report',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            Google Shopping Ads Report
                        </h4>

                        <div class="row g-3 mb-3">
                            <!-- Inventory Filters -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <select id="inv-filter" class="form-select form-select-md">
                                        <option value="">Select INV</option>
                                        <option value="ALL">ALL</option>
                                        <option value="INV_0">0 INV</option>
                                        <option value="OTHERS">OTHERS</option>
                                    </select>

                                    <select id="acos-filter" class="form-select form-select-md">
                                        <option value="">Select ACOS</option>
                                        <option value="PINK">PINK</option>
                                        <option value="GREEN">GREEN</option>
                                        <option value="RED">RED</option>
                                    </select>

                                    <select id="cvr-filter" class="form-select form-select-md">
                                        <option value="">Select CVR</option>
                                        <option value="PINK">PINK</option>
                                        <option value="GREEN">GREEN</option>
                                        <option value="RED">RED</option>
                                    </select>
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
                            <!-- Left: Search & Status -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <input type="text" id="global-search" class="form-control form-control-md" placeholder="Search campaign...">
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

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            var table = new Tabulator("#budget-under-table", {
                index: "Sku",
                ajaxURL: "/google/shopping/ads-report/data",
                layout: "fitData",
                movableColumns: true,
                resizableColumns: true,
                height: "700px",             
                virtualDom: true,
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = (data.sku || "").toLowerCase().trim();

                    if (sku.includes("parent ")) {
                        row.getElement().classList.add("parent-row-bg");
                    }
                },
                columns: [
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
                            const L30 = parseFloat(data.L30);
                            const inv = parseFloat(data.INV);

                            if (!isNaN(L30) && !isNaN(inv) && inv !== 0) {
                                const dilDecimal = (L30 / inv);
                                const color = getDilColor(dilDecimal);
                                return `<div class="text-center"><span class="dil-percent-value ${color}">${Math.round(dilDecimal * 100)}%</span></div>`;
                            }
                            return `<div class="text-center"><span class="dil-percent-value red">0%</span></div>`;
                        },
                        visible: false
                    },
                    {
                        title: "NRA",
                        field: "NRA",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue()?.trim();

                            let bgColor = "";
                            if (value === "NRA") {
                                bgColor = "background-color:#dc3545;color:#fff;"; // red
                            } else if (value === "RA") {
                                bgColor = "background-color:#28a745;color:#fff;"; // green
                            } else if (value === "LATER") {
                                bgColor = "background-color:#ffc107;color:#000;"; // yellow
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="NRA"
                                        style="width: 100px; ${bgColor}">
                                    <option value="RA" ${value === 'RA' ? 'selected' : ''}>RA</option>
                                    <option value="NRA" ${value === 'NRA' ? 'selected' : ''}>NRA</option>
                                    <option value="LATER" ${value === 'LATER' ? 'selected' : ''}>LATER</option>
                                </select>
                            `;
                        },
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "CAMPAIGN",
                        field: "campaignName"
                    },
                    {
                        title: "STATUS",
                        field: "campaignStatus"
                    },
                    {
                        title: "7 UB%",
                        field: "spend_L7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var spend_L7 = parseFloat(row.spend_L7) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub7 = budget > 0 ? (spend_L7 / (budget * 7)) * 100 : 0;

                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub7 >= 70 && ub7 <= 90) {
                                td.classList.add('green-bg');
                            } else if (ub7 > 90) {
                                td.classList.add('pink-bg');
                            } else if (ub7 < 70) {
                                td.classList.add('red-bg');
                            }

                            return ub7.toFixed(0) + "%";
                        }
                    },
                    {
                        title: 'IMP L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="imp-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "impressions_L30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let impressions_L30 = cell.getValue();
                            return `
                                <span>${parseFloat(cell.getValue() || 0).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary impressions_L30_btn" 
                                    data-impression-L30="${impressions_L30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'IMP L60 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="imp-l60-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "impressions_L60",
                        hozAlign: "right",
                        formatter: function(cell) {
                            return `
                                <span>${parseFloat(cell.getValue() || 0).toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: 'IMP L15 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="imp-l15-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "impressions_L15",
                        hozAlign: "right",
                        formatter: function(cell) {
                            return `
                                <span>${parseFloat(cell.getValue() || 0).toFixed(0)}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: 'IMP L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="imp-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "impressions_L7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let impressions_L7 = cell.getValue();
                            return `
                                <span>${impressions_L7}</span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: 'Clicks L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="clicks-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "clicks_L30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let color = value < 50 ? "red" : "green";
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${value.toFixed(0)}
                                </span>
                                <i class="fa fa-info-circle text-primary clicks_L30_btn" 
                                data-clicks-L30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'Clicks L60 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="clicks-l60-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "clicks_L60",
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
                        title: 'Clicks L15 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="clicks-l15-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "clicks_L15",
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
                        title: 'Clicks L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="clicks-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "clicks_L7",
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
                        title: 'Spend L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="spend-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "spend_L30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary spend_L30_btn" 
                                data-spend-L30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'Spend L60 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="spend-l60-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "spend_L60",
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
                        title: 'Spend L15 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="spend-l15-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "spend_L15",
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
                        title: 'Spend L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="spend-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "spend_L7",
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
                        title: 'Ad Sales L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="ad-sales-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "ad_sales_L30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary ad_sales_L30_btn" 
                                    data-ad_sales-L30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'Ad Sales L60 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="ad-sales-l60-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "ad_sales_L60",
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
                        title: 'Ad Sales L15 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="ad-sales-l15-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "ad_sales_L15",
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
                        title: 'Ad Sales L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="ad-sales-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "ad_sales_L7",
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
                        title: 'Ad Sold L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="ad-sold-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "ad_sold_L30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            return `
                                <span>${value.toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary ad_sold_L30_btn" 
                                    data-ad_sold-L30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'Ad Sold L60 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="ad-sold-l60-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "ad_sold_L60",
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
                        title: 'Ad Sold L15 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="ad-sold-l15-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "ad_sold_L15",
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
                        title: 'Ad Sold L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="ad-sold-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "ad_sold_L7",
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
                        title: 'ACOS L30',
                        field: "acos_L30",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let row = cell.getRow().getData();
                            let adSales = parseFloat(row.ad_sales_L30 || 0);

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
                                <i class="fa fa-info-circle text-primary acos_L30_btn" 
                                    data-acos-L30="${value}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "ACOS L60",
                        field: "acos_L60",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let row = cell.getRow().getData();
                            let adSales = parseFloat(row.ad_sales_L60 || 0);

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
                        field: "acos_L15",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let row = cell.getRow().getData();
                            let adSales = parseFloat(row.ad_sales_L15 || 0);

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
                        field: "acos_L7",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue() || 0);
                            let row = cell.getRow().getData();
                            let adSales = parseFloat(row.ad_sales_L7 || 0);

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
                        title: 'CPC L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="cpc-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "cpc_L30",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var cpc_L30 = parseFloat(row.cpc_L30) || 0;

                            return `
                                <span>
                                    ${cpc_L30.toFixed(2)}
                                </span>
                                <i class="fa fa-info-circle text-primary cpc_L30_btn" 
                                    data-cpc-L30="${cpc_L30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'CPC L60 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="cpc-l60-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "cpc_L60",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var cpc_L60 = parseFloat(row.cpc_L60) || 0;
                            return cpc_L60.toFixed(2);
                        },
                        visible: false
                    },
                    {
                        title: 'CPC L15 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="cpc-l15-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "cpc_L15",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var cpc_L15 = parseFloat(row.cpc_L15) || 0;
                            return cpc_L15.toFixed(2);
                        },
                        visible: false
                    },
                    {
                        title: 'CPC L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="cpc-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "cpc_L7",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var cpc_L7 = parseFloat(row.cpc_L7) || 0;
                            return `
                                <span>
                                    ${cpc_L7.toFixed(2)}
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "CVR L30",
                        field: "cvr_L30",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ad_sold_L30 = parseFloat(row.ad_sold_L30) || 0;
                            var clicks_L30 = parseFloat(row.clicks_L30) || 0;
                            
                            var cvr_L30 = (clicks_L30 > 0) ? (ad_sold_L30 / clicks_L30) * 100 : 0;
                            let color = "";
                            if (cvr_L30 < 5) {
                                color = "red";
                            } else if (cvr_L30 >= 5 && cvr_L30 <= 10) {
                                color = "green";
                            } else if (cvr_L30 > 10){
                                color = "#e83e8c";
                            }
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${cvr_L30.toFixed(0)}%
                                </span>
                                <i class="fa fa-info-circle text-primary cvr_L30_btn" 
                                    data-cvr-L30="${cvr_L30}" 
                                    style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: "CVR L60",
                        field: "cvr_L60",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ad_sold_L60 = parseFloat(row.ad_sold_L60) || 0;
                            var clicks_L60 = parseFloat(row.clicks_L60) || 0;
                            
                            var cvr_L60 = (clicks_L60 > 0) ? (ad_sold_L60 / clicks_L60) * 100 : 0;
                            let color = "";
                            if (cvr_L60 < 5) {
                                color = "red";
                            } else if (cvr_L60 >= 5 && cvr_L60 <= 10) {
                                color = "green";
                            } else if (cvr_L60 > 10){
                                color = "#e83e8c";
                            }
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${cvr_L60.toFixed(0)}%
                                </span>
                            `;

                        },
                        visible: false
                    },
                    {
                        title: "CVR L15",
                        field: "cvr_L15",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ad_sold_L15 = parseFloat(row.ad_sold_L15) || 0;
                            var clicks_L15 = parseFloat(row.clicks_L15) || 0;

                            var cvr_L15 = (clicks_L15 > 0) ? (ad_sold_L15 / clicks_L15) * 100 : 0;
                            let color = "";
                            if (cvr_L15 < 5) {
                                color = "red";
                            } else if (cvr_L15 >= 5 && cvr_L15 <= 10) {
                                color = "green";
                            } else if (cvr_L15 > 10){
                                color = "#e83e8c";
                            }
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${cvr_L15.toFixed(0)}%
                                </span>
                            `;
                        },
                        visible: false
                    },
                    {
                        title: "CVR L7",
                        field: "cvr_L7",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var ad_sold_L7 = parseFloat(row.ad_sold_L7) || 0;
                            var clicks_L7 = parseFloat(row.clicks_L7) || 0;

                            var cvr_L7 = (clicks_L7 > 0) ? (ad_sold_L7 / clicks_L7) * 100 : 0;
                            let color = "";
                            if (cvr_L7 < 5) {
                                color = "red";
                            } else if (cvr_L7 >= 5 && cvr_L7 <= 10) {
                                color = "green";
                            } else if (cvr_L7 > 10){
                                color = "#e83e8c";
                            }
                            return `
                                <span style="color:${color}; font-weight:600;">
                                    ${cvr_L7.toFixed(0)}%
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
                        },
                        visible: false
                    }
                ],
                ajaxResponse: function(url, params, response) {
                    return response.data;
                }
            });

            // document.addEventListener("change", function(e){
            //     if(e.target.classList.contains("editable-select")){
            //         let sku   = e.target.getAttribute("data-sku");
            //         let field = e.target.getAttribute("data-field");
            //         let value = e.target.value;

            //         fetch('/update-amazon-nr-nrl-fba', {
            //             method: 'POST',
            //             headers: {
            //                 'Content-Type': 'application/json',
            //                 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            //             },
            //             body: JSON.stringify({
            //                 sku: sku,
            //                 field: field,
            //                 value: value
            //             })
            //         })
            //         .then(res => res.json())
            //         .then(data => {
            //             console.log(data);
            //         })
            //         .catch(err => console.error(err));
            //     }
            // });

            table.on("tableBuilt", function() {

                function combinedFilter(data) {

                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal && !(data.sku?.toLowerCase().includes(searchVal))) {
                        return false;
                    }

                    let statusVal = $("#status-filter").val();
                    if (statusVal && data.campaignStatus !== statusVal) {
                        return false;
                    }

                    let invFilterVal = $("#inv-filter").val();
                    if (!invFilterVal) {
                        // if (parseFloat(data.INV) === 0) return false;
                    } else if (invFilterVal === "INV_0") {
                        if (parseFloat(data.INV) !== 0) return false;
                    } else if (invFilterVal === "OTHERS") {
                        if (parseFloat(data.INV) === 0) return false;
                    }

                    let acosFilterVal = $("#acos-filter").val();
                    if (acosFilterVal) {
                        let acosFields = ["acos_L30"];

                        let matched = acosFields.every(field => {
                            let val = parseFloat(data[field]) || 0;

                            if (acosFilterVal === "PINK") {
                                return val < 7;
                            }
                            if (acosFilterVal === "GREEN") {
                                return val >= 7 && val <= 14;
                            }
                            if (acosFilterVal === "RED") {
                                return val > 14;
                            }
                            return false;
                        });

                        if (!matched) return false;
                    }

                    let cvrFilterVal = $("#cvr-filter").val();
                    if (cvrFilterVal) {
                        let cvrFields = ["cvr_L30"];

                        let matched = cvrFields.every(field => {
                            let val = parseFloat(data[field]) || 0;

                            if (cvrFilterVal === "PINK") {
                                return val > 10;
                            }
                            if (cvrFilterVal === "GREEN") {
                                return val >= 5 && val <= 10;
                            }
                            if (cvrFilterVal === "RED") {
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

                    // Improved helper function to handle both numbers and strings
                    const calculateTotal = (field) => {
                        return filteredRows.reduce((sum, row) => {
                            //const sku = (row.sku || "").toLowerCase().trim();
                            //if (!sku.includes("parent ")) {
                                let value = row[field];
                                // Ensure value is a number
                                if (typeof value === 'string') {
                                    value = parseFloat(value) || 0;
                                }
                                return sum + (value || 0);
                            //}
                            //return sum;
                        }, 0);
                    };

                    let impl30Total = calculateTotal('impressions_L30');
                    let impl60Total = calculateTotal('impressions_L60');
                    let impl15Total = calculateTotal('impressions_L15');
                    let impl7Total = calculateTotal('impressions_L7');
                    let clicksl30Total = calculateTotal('clicks_L30');
                    let clicksl60Total = calculateTotal('clicks_L60');
                    let clicksl15Total = calculateTotal('clicks_L15');
                    let clicksL7Total = calculateTotal('clicks_L7');
                    let spendL30Total = calculateTotal('spend_L30');
                    let spendL60Total = calculateTotal('spend_L60');
                    let spendl15Total = calculateTotal('spend_L15');
                    let spendl7Total = calculateTotal('spend_L7');
                    let adSalesl30Total = calculateTotal('ad_sales_L30');
                    let adSalesl60Total = calculateTotal('ad_sales_L60');
                    let adSalesl15Total = calculateTotal('ad_sales_L15');
                    let adSalesl7Total = calculateTotal('ad_sales_L7');
                    let adSoldl30Total = calculateTotal('ad_sold_L30');
                    let adSoldl60Total = calculateTotal('ad_sold_L60');
                    let adSoldl15Total = calculateTotal('ad_sold_L15');
                    let adSoldl7Total = calculateTotal('ad_sold_L7');
                    let cpcL30Total = calculateTotal('cpc_L30');
                    let cpcL60Total = calculateTotal('cpc_L60');
                    let cpcL15Total = calculateTotal('cpc_L15');
                    let cpcl7Total = calculateTotal('cpc_L7');


                    $.ajax({
                        url: "{{ route('adv-shopify.gshopping.save-data') }}",
                        method: 'GET',
                        data: {
                            spendL30Total: spendL30Total,
                            clicksl30Total: clicksl30Total,
                            adSalesl30Total:adSalesl30Total,
                            adSoldl30Total:adSoldl30Total,                       
                        },
                        success: function(response) {
                        },
                        error: function(xhr) {
                        }
                    });
                        

                       
                    document.getElementById("cpc-l7-total").innerText = cpcl7Total > 0 ? ` (${cpcl7Total.toFixed(2)})` : "";
                    document.getElementById("cpc-l7-total").style.display = cpcl7Total > 0 ? "inline" : "none";
                    
                    document.getElementById("cpc-l15-total").innerText = cpcL15Total > 0 ? ` (${cpcL15Total.toFixed(2)})` : "";
                    document.getElementById("cpc-l15-total").style.display = cpcL15Total > 0 ? "inline" : "none";


                    document.getElementById("cpc-l60-total").innerText = cpcL60Total > 0 ? ` (${cpcL60Total.toFixed(2)})` : "";
                    document.getElementById("cpc-l60-total").style.display = cpcL60Total > 0 ? "inline" : "none";
                  
                    
                    document.getElementById("cpc-l7-total").innerText = cpcL30Total > 0 ? ` (${cpcL30Total.toFixed(2)})` : "";
                    document.getElementById("cpc-l7-total").style.display = cpcL30Total > 0 ? "inline" : "none";

                    
                    document.getElementById("ad-sold-l7-total").innerText = adSoldl7Total > 0 ? ` (${adSoldl7Total.toFixed(2)})` : "";
                    document.getElementById("ad-sold-l7-total").style.display = adSoldl7Total > 0 ? "inline" : "none";

                     
                    document.getElementById("ad-sold-l15-total").innerText = adSoldl15Total > 0 ? ` (${adSoldl15Total.toFixed(2)})` : "";
                    document.getElementById("ad-sold-l15-total").style.display = adSoldl15Total > 0 ? "inline" : "none";

                      
                    document.getElementById("ad-sold-l60-total").innerText = adSoldl60Total > 0 ? ` (${adSoldl60Total.toFixed(2)})` : "";
                    document.getElementById("ad-sold-l60-total").style.display = adSoldl60Total > 0 ? "inline" : "none";

                    
                    document.getElementById("ad-sold-l30-total").innerText = adSoldl30Total > 0 ? ` (${adSoldl30Total.toFixed(2)})` : "";
                    document.getElementById("ad-sold-l30-total").style.display = adSoldl30Total > 0 ? "inline" : "none";

                    
                    document.getElementById("ad-sales-l7-total").innerText = adSalesl7Total > 0 ? ` (${adSalesl7Total.toFixed(2)})` : "";
                    document.getElementById("ad-sales-l7-total").style.display = adSalesl7Total > 0 ? "inline" : "none";

                    
                    document.getElementById("ad-sales-l15-total").innerText = adSalesl15Total > 0 ? ` (${adSalesl15Total.toFixed(2)})` : "";
                    document.getElementById("ad-sales-l15-total").style.display = adSalesl15Total > 0 ? "inline" : "none";

                    
                    document.getElementById("ad-sales-l60-total").innerText = adSalesl60Total > 0 ? ` (${adSalesl60Total.toFixed(2)})` : "";
                    document.getElementById("ad-sales-l60-total").style.display = adSalesl60Total > 0 ? "inline" : "none";

                     
                    document.getElementById("ad-sales-l30-total").innerText = adSalesl30Total > 0 ? ` (${adSalesl30Total.toFixed(2)})` : "";
                    document.getElementById("ad-sales-l30-total").style.display = adSalesl30Total > 0 ? "inline" : "none";


                    document.getElementById("spend-l7-total").innerText = spendl7Total > 0 ? ` (${spendl7Total.toFixed(2)})` : "";
                    document.getElementById("spend-l7-total").style.display = spendl7Total > 0 ? "inline" : "none";
                    
                    
                    document.getElementById("spend-l15-total").innerText = spendl15Total > 0 ? ` (${spendl15Total.toFixed(2)})` : "";
                    document.getElementById("spend-l15-total").style.display = spendl15Total > 0 ? "inline" : "none";

                     
                    document.getElementById("spend-l60-total").innerText = spendL60Total > 0 ? ` (${spendL60Total.toFixed(2)})` : "";
                    document.getElementById("spend-l60-total").style.display = spendL60Total > 0 ? "inline" : "none";

                    
                    document.getElementById("spend-l30-total").innerText = spendL30Total > 0 ? ` (${spendL30Total.toFixed(2)})` : "";
                    document.getElementById("spend-l30-total").style.display = spendL30Total > 0 ? "inline" : "none";
                    
                    
                    document.getElementById("clicks-l7-total").innerText = clicksL7Total > 0 ? ` (${clicksL7Total.toFixed(2)})` : "";
                    document.getElementById("clicks-l7-total").style.display = clicksL7Total > 0 ? "inline" : "none";
                     
                    
                    document.getElementById("clicks-l15-total").innerText = clicksl15Total > 0 ? ` (${clicksl15Total.toFixed(2)})` : "";
                    document.getElementById("clicks-l15-total").style.display = clicksl15Total > 0 ? "inline" : "none";


                    document.getElementById("imp-l30-total").innerText = impl30Total > 0 ? ` (${impl30Total.toFixed(2)})` : "";
                    document.getElementById("imp-l30-total").style.display = impl30Total > 0 ? "inline" : "none";

                    document.getElementById("imp-l60-total").innerText = impl60Total > 0 ? ` (${impl60Total.toFixed(2)})` : "";
                    document.getElementById("imp-l60-total").style.display = impl60Total > 0 ? "inline" : "none";

                    document.getElementById("imp-l15-total").innerText = impl15Total > 0 ? ` (${impl15Total.toFixed(2)})` : "";
                    document.getElementById("imp-l15-total").style.display = impl15Total > 0 ? "inline" : "none";

                    document.getElementById("imp-l7-total").innerText = impl7Total > 0 ? ` (${impl7Total.toFixed(2)})` : "";
                    document.getElementById("imp-l7-total").style.display = impl7Total > 0 ? "inline" : "none";

                    document.getElementById("clicks-l30-total").innerText = clicksl30Total > 0 ? ` (${clicksl30Total.toFixed(2)})` : "";
                    document.getElementById("clicks-l30-total").style.display = clicksl30Total > 0 ? "inline" : "none";


                    document.getElementById("clicks-l60-total").innerText = clicksl60Total > 0 ? ` (${clicksl60Total.toFixed(2)})` : "";
                    document.getElementById("clicks-l60-total").style.display = clicksl60Total > 0 ? "inline" : "none";
                    
                    
   
                     
                    
                    
 
                    
 
                    



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

                $("#status-filter,#inv-filter,#acos-filter,#cvr-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                });

                updateCampaignStats();
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["INV", "L30", "DIL %", "NRA"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("impressions_L30_btn")) {
                    let colsToToggle = ["impressions_L60", "impressions_L15", "impressions_L7"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("clicks_L30_btn")) {
                    let colsToToggle = ["clicks_L15", "clicks_L7", "clicks_L60"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("spend_L30_btn")) {
                    let colsToToggle = ["spend_L15", "spend_L7", "spend_L60"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("ad_sales_L30_btn")) {
                    let colsToToggle = ["ad_sales_L15", "ad_sales_L7", "ad_sales_L60"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("ad_sold_L30_btn")) {
                    let colsToToggle = ["ad_sold_L15", "ad_sold_L7", "ad_sold_L60"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("acos_L30_btn")) {
                    let colsToToggle = ["acos_L15", "acos_L7", "acos_L60"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("cpc_L30_btn")) {
                    let colsToToggle = ["cpc_L15", "cpc_L7", "cpc_L60"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }

                if (e.target.classList.contains("cvr_L30_btn")) {
                    let colsToToggle = ["cvr_L15", "cvr_L7", "cvr_L60"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });
        });
    </script>
@endsection

