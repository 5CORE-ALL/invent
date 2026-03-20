@extends('layouts.vertical', ['title' => 'Ebay3 Running Ads', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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

        .parent-row-bg{
            background-color: #c3efff !important;
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
        'page_title' => 'Ebay3 Running Ads',
        'sub_title' => 'Ebay3 Running Ads',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-3">
                    <div class="mb-4">
                        <!-- Title -->
                        <h4 class="fw-bold text-primary mb-3 d-flex align-items-center">
                            <i class="fa-solid fa-chart-line me-2"></i>
                            Running Ads
                        </h4>

                        <!-- Filters Row -->
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

                                    <select id="nra-filter" class="form-select form-select-md">
                                        <option value="">Select NRA</option>
                                        <option value="NRA">NRA</option>
                                        <option value="RA">RA</option>
                                        <option value="LATER">LATER</option>
                                    </select>

                                </div>
                            </div>

                            <!-- Stats -->
                            <div class="col-md-6">
                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="javascript:void(0)" id="export-btn" class="btn btn-sm btn-success d-flex align-items-center justify-content-center">
                                        <i class="fas fa-file-export me-1"></i> Export Excel/CSV
                                    </a>
                                    <button class="btn btn-success btn-md">
                                        Total Running Ads: <span id="total-campaigns" class="fw-bold ms-1 fs-4">0</span>
                                    </button>
                                    <button class="btn btn-primary btn-md">
                                        <i class="fa fa-percent me-1"></i>
                                        of Total: <span id="percentage-campaigns" class="fw-bold ms-1 fs-4">0%</span>
                                    </button>
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

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            var table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/ebay-3/ad-running/data",
                layout: "fitDataFill",
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
                        title: "EL 30",
                        field: "e_l30",
                        visible: false
                    },
                    {
                        title: "NRA",
                        field: "NR",
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
                                        data-field="NR"
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
                        title: 'SPEND L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="spend-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "SPEND_L30",
                        formatter: function(cell) {
                            let SPEND_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(SPEND_L30).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-spendL30-btn" 
                                data-spend-l30="${SPEND_L30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Spend L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-spend-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_spend_L30",
                        visible: false,
                        formatter: function(cell) {
                            let KW_SPEND_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_SPEND_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Spend L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-spend-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_spend_L30",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_SPEND_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_SPEND_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'SPEND L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="spend-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "SPEND_L7",
                        formatter: function(cell) {
                            let SPEND_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(SPEND_L7).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-spendL7-btn" 
                                data-spend-l7="${SPEND_L7}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Spend L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-spend-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_spend_L7",
                        visible: false,
                        formatter: function(cell) {
                            let KW_SPEND_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_SPEND_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Spend L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-spend-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_spend_L7",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_SPEND_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_SPEND_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'SOLD L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="sold-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "SOLD_L30",
                        formatter: function(cell) {
                            let SOLD_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(SOLD_L30).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-soldL30-btn" 
                                data-sold-l30="${SOLD_L30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Sold L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-sold-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_sold_L30",
                        visible: false,
                        formatter: function(cell) {
                            let KW_SOLD_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_SOLD_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Sold L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-sold-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_sold_L30",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_SOLD_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_SOLD_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'SOLD L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="sold-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "SOLD_L7",
                        formatter: function(cell) {
                            let SOLD_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(SOLD_L7).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-soldL7-btn" 
                                data-sold-l7="${SOLD_L7}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Sold L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-sold-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_sold_L7",
                        visible: false,
                        formatter: function(cell) {
                            let KW_SOLD_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_SOLD_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Sold L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-sold-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_sold_L7",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_SOLD_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_SOLD_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'SALES L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="sales-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "SALES_L30",
                        formatter: function(cell) {
                            let SALES_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(SALES_L30).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-salesL30-btn" 
                                data-sales-l30="${SALES_L30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Sales L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-sales-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_sales_L30",
                        visible: false,
                        formatter: function(cell) {
                            let KW_SALES_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_SALES_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Sales L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-sales-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_sales_L30",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_SALES_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_SALES_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'SALES L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="sales-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "SALES_L7",
                        formatter: function(cell) {
                            let SALES_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(SALES_L7).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-salesL7-btn" 
                                data-sales-l30="${SALES_L7}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Sales L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-sales-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_sales_L7",
                        visible: false,
                        formatter: function(cell) {
                            let KW_SALES_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_SALES_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Sales L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-sales-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_sales_L7",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_SALES_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_SALES_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'CLICKS L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="clicks-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "CLICKS_L30",
                        formatter: function(cell) {
                            let CLICKS_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(CLICKS_L30).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-clicksL30-btn" 
                                data-clicks-l30="${CLICKS_L30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Clicks L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-clicks-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_clicks_L30",
                        visible: false,
                        formatter: function(cell) {
                            let KW_CLICKS_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_CLICKS_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Clicks L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-clicks-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_clicks_L30",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_CLICKS_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_CLICKS_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'CLICKS L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="clicks-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "CLICKS_L7",
                        formatter: function(cell) {
                            let CLICKS_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(CLICKS_L7).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-clicksL7-btn" 
                                data-clicks-l7="${CLICKS_L7}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW Clicks L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-clicks-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_clicks_L7",
                        visible: false,
                        formatter: function(cell) {
                            let KW_CLICKS_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_CLICKS_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT Clicks L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-clicks-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_clicks_L7",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_CLICKS_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_CLICKS_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'IMP L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="imp-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "IMP_L30",
                        formatter: function(cell) {
                            let IMP_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(IMP_L30).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-impL30-btn" 
                                data-clicks-l7="${IMP_L30}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW IMP L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-imp-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_impr_L30",
                        visible: false,
                        formatter: function(cell) {
                            let KW_IMP_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_IMP_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT IMP L30 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-imp-l30-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_impr_L30",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_IMP_L30 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_IMP_L30).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'IMP L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="imp-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "IMP_L7",
                        formatter: function(cell) {
                            let IMP_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(IMP_L7).toFixed(0)}</span>
                                <i class="fa fa-info-circle text-primary toggle-impL7-btn" 
                                data-clicks-l7="${IMP_L7}" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    },
                    {
                        title: 'KW IMP L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="kw-imp-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "kw_impr_L7",
                        visible: false,
                        formatter: function(cell) {
                            let KW_IMP_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(KW_IMP_L7).toFixed(0)}</span>
                            `;
                        }
                    },
                    {
                        title: 'PMT IMP L7 <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> <span class="text-muted" id="pmt-imp-l7-total" style="display:inline-block; margin-top:2px;"></span>',
                        field: "pmt_impr_L7",
                        visible: false,
                        formatter: function(cell) {
                            let PMT_IMP_L7 = cell.getValue() || 0;
                            return `
                                <span>${parseFloat(PMT_IMP_L7).toFixed(0)}</span>
                            `;
                        }
                    },                  
                    {
                        title: "START AD",
                        field: "start_ad",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue();

                            let bgColor = "";
                            if (value === "KW") {
                                bgColor = "background-color:#28a745;color:#fff;";
                            } else if (value === "PMT") {
                                bgColor = "background-color:#28a745;color:#fff;";
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="start_ad"
                                        style="${bgColor}">
                                    <option value=""></option>
                                    <option value="KW" ${value === 'KW' ? 'selected' : ''}>KW</option>
                                    <option value="PMT" ${value === 'PMT' ? 'selected' : ''}>PMT</option>
                                </select>
                            `;
                        },
                        hozAlign: "center",
                        visible: false
                    },
                    {
                        title: "STOP AD",
                        field: "stop_ad",
                        formatter: function(cell) {
                            const row = cell.getRow();
                            const sku = row.getData().sku;
                            const value = cell.getValue();

                            let bgColor = "";
                            if (value === "KW") {
                                bgColor = "background-color:#dc3545;color:#fff;";
                            } else if (value === "PMT") {
                                bgColor = "background-color:#dc3545;color:#fff;";
                            }

                            return `
                                <select class="form-select form-select-sm editable-select" 
                                        data-sku="${sku}" 
                                        data-field="stop_ad"
                                        style="${bgColor}">
                                    <option value=""></option>
                                    <option value="KW" ${value === 'KW' ? 'selected' : ''}>KW</option>
                                    <option value="PMT" ${value === 'PMT' ? 'selected' : ''}>PMT</option>
                                </select>
                            `;
                        },
                        hozAlign: "center",
                        visible: false
                    },

                ],
                ajaxResponse: function(url, params, response) {
                    if (response.data && Array.isArray(response.data)) {
                        response.data = response.data.map(row => {
                            // List of fields that should be numbers
                            const numericFields = [
                                'SPEND_L30', 'kw_spend_L30', 'pmt_spend_L30',
                                'SPEND_L7', 'kw_spend_L7', 'pmt_spend_L7',
                                'CLICKS_L30', 'kw_clicks_L30', 'pmt_clicks_L30',
                                'CLICKS_L7', 'kw_clicks_L7', 'pmt_clicks_L7',
                                'IMP_L30', 'kw_impr_L30', 'pmt_impr_L30', 
                                'IMP_L7', 'kw_impr_L7', 'pmt_impr_L7',
                                'INV', 'L30', 'A_L30'
                            ];
                            
                            numericFields.forEach(field => {
                                if (row[field] !== undefined && row[field] !== null) {
                                    // Convert to number, if conversion fails keep original value
                                    const numValue = parseFloat(row[field]);
                                    row[field] = isNaN(numValue) ? 0 : numValue;
                                }
                            });
                            
                            return row;
                        });
                    }
                    return response.data;
                }
            });

            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("editable-select")) {
                    let sku = e.target.getAttribute("data-sku");
                    let field = e.target.getAttribute("data-field");
                    let value = e.target.value;

                    fetch('/update-ebay3-nr-data', {
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

            let initialSpendL30Data = {};

            table.on("dataLoaded", function(data) {
                data.forEach(row => {
                if (row.SPEND_L30 !== undefined) {
                    initialSpendL30Data[row.sku] = row.SPEND_L30;

                    fetch('/update-ebay3-nr-data', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        sku: row.sku,
                        field: 'Spend_L30', 
                        value: row.SPEND_L30
                    })
                    })
                    .then(res => res.json())
                    .then(data => {
                    console.log('SPEND_L30 saved for SKU:', row.sku);
                    })
                    .catch(err => {
                    console.error('Error saving SPEND_L30:', err);
                    });
                }
                });
            });

            table.on("tableBuilt", function () {

                function combinedFilter(data) {
                    if (!data) return false;

                    // 🔍 Search filter
                    let searchVal = $("#global-search").val()?.toLowerCase() || "";
                    if (searchVal) {
                        const sku = (data.sku || "").toLowerCase();
                        if (!sku.includes(searchVal)) {
                            return false;
                        }
                    }

                    // 🟢 Status filter
                    let statusVal = $("#status-filter").val();
                    if (statusVal && data.campaignStatus !== statusVal) {
                        return false;
                    }

                    // 📦 Inventory filter (fixed logic)
                    let invFilterVal = $("#inv-filter").val();
                    if (invFilterVal) {
                        const inv = parseFloat(data.INV || 0);

                        if (invFilterVal === "INV_0" && inv !== 0) {
                            // Show only rows where inventory = 0
                            return false;
                        } 
                        else if (invFilterVal === "OTHERS" && inv === 0) {
                            // Show only rows where inventory > 0
                            return false;
                        } 
                        else if (invFilterVal === "ALL") {
                            // Show all — no filter
                        }
                    }

                    // 🧮 NRA filter (fixed select lookup)
                    let nraFilterVal = $("#nra-filter").val();
                    if (nraFilterVal) {
                        let rowVal = data.NR || "";
                        // Try to read from select if it's rendered
                        let rowSelect = document.querySelector(`select[data-sku="${data.sku}"][data-field="NR"]`);
                        if (rowSelect && rowSelect.value) {
                            rowVal = rowSelect.value;
                        }

                        if (rowVal !== nraFilterVal) {
                            return false;
                        }
                    }

                    return true;
                }

                // 🧩 Apply combined filter
                table.setFilter(combinedFilter);

                // 📊 Update campaign stats
                function updateCampaignStats() {
                    let allRows = table.getData();
                    let filteredRows = allRows.filter(combinedFilter);

                    let total = allRows.length;
                    let filtered = filteredRows.length;

                    // Improved helper function to handle both numbers and strings
                    const calculateTotal = (field) => {
                        return filteredRows.reduce((sum, row) => {
                            const sku = (row.sku || "").toLowerCase();
                            // Skip parent rows
                            // if (sku.includes("parent")) return sum;

                            let value = row[field];
                            if (typeof value === "string") value = parseFloat(value) || 0;

                            return sum + (value || 0);
                        }, 0);
                    };

                    //calculate all total
                    let spendL30Total = calculateTotal('SPEND_L30');
                    let kwSpendL30Total = calculateTotal('kw_spend_L30');
                    let pmtSpendL30Total = calculateTotal('pmt_spend_L30');
                    let spendL7Total = calculateTotal('SPEND_L7');
                    let kwSpendL7Total = calculateTotal('kw_spend_L7');
                    let pmtSpendL7Total = calculateTotal('pmt_spend_L7');
                    let soldL30Total = calculateTotal('SOLD_L30');
                    let kwSoldL30Total = calculateTotal('kw_sold_L30');
                    let pmtSoldL30Total = calculateTotal('pmt_sold_L30');
                    let soldL7Total = calculateTotal('SOLD_L7');
                    let kwSoldL7Total = calculateTotal('kw_sold_L7');
                    let pmtSoldL7Total = calculateTotal('pmt_sold_L7');
                    let salesL30Total = calculateTotal('SALES_L30');
                    let kwSalesL30Total = calculateTotal('kw_sales_L30');
                    let pmtSalesL30Total = calculateTotal('pmt_sales_L30');
                    let salesL7Total = calculateTotal('SALES_L7');
                    let kwSalesL7Total = calculateTotal('kw_sales_L7');
                    let pmtSalesL7Total = calculateTotal('pmt_sales_L7');
                    let clicksL30Total = calculateTotal('CLICKS_L30');
                    let kwClicksL30Total = calculateTotal('kw_clicks_L30');
                    let pmtClicksL30Total = calculateTotal('pmt_clicks_L30');
                    let clicksL7Total = calculateTotal('CLICKS_L7');
                    let kwClicksL7Total = calculateTotal('kw_clicks_L7');
                    let pmtClicksL7Total = calculateTotal('pmt_clicks_L7');
                    let impL30Total = calculateTotal('IMP_L30');
                    let kwImpL30Total = calculateTotal('kw_impr_L30');
                    let pmtImpL30Total = calculateTotal('pmt_impr_L30');
                    let impL7Total = calculateTotal('IMP_L7');
                    let kwImpL7Total = calculateTotal('kw_impr_L7');
                    let pmtImpL7Total = calculateTotal('pmt_impr_L7');

                    
                    $.ajax({
                        url: "{{ route('adv-ebay3.ad-running.save-data') }}",
                        method: 'GET',
                        data: {
                            spendL30Total: spendL30Total,
                            kwSpendL30Total: kwSpendL30Total,
                            pmtSpendL30Total:pmtSpendL30Total,
                            clicksL30Total:clicksL30Total,
                            kwClicksL30Total:kwClicksL30Total,
                            pmtClicksL30Total:pmtClicksL30Total,
                            salesL30Total:salesL30Total,
                            kwSalesL30Total:kwSalesL30Total,
                            pmtSalesL30Total:pmtSalesL30Total,
                            soldL30Total:soldL30Total,
                            kwSoldL30Total:kwSoldL30Total,
                            pmtSoldL30Total:pmtSoldL30Total
                        },
                        success: function(response) {
                        },
                        error: function(xhr) {
                        }
                    });
                    


                    document.getElementById("imp-l7-total").innerText = impL7Total > 0 ? ` (${impL7Total.toFixed(0)})` : "";
                    document.getElementById("imp-l7-total").style.display = impL7Total > 0 ? "inline" : "none";

                    document.getElementById("kw-imp-l7-total").innerText = kwImpL7Total > 0 ? ` (${kwImpL7Total.toFixed(0)})` : "";
                    document.getElementById("kw-imp-l7-total").style.display = kwImpL7Total > 0 ? "inline" : "none";

                    document.getElementById("pmt-imp-l7-total").innerText = pmtImpL7Total > 0 ? ` (${pmtImpL7Total.toFixed(0)})` : "";
                    document.getElementById("pmt-imp-l7-total").style.display = pmtImpL7Total > 0 ? "inline" : "none";


                    document.getElementById("imp-l30-total").innerText = impL30Total > 0 ? ` (${impL30Total.toFixed(0)})` : "";
                    document.getElementById("imp-l30-total").style.display = impL30Total > 0 ? "inline" : "none";

                    document.getElementById("kw-imp-l30-total").innerText = kwImpL30Total > 0 ? ` (${kwImpL30Total.toFixed(0)})` : "";
                    document.getElementById("kw-imp-l30-total").style.display = kwImpL30Total > 0 ? "inline" : "none";


                    document.getElementById("pmt-imp-l30-total").innerText = pmtImpL30Total > 0 ? ` (${pmtImpL30Total.toFixed(0)})` : "";
                    document.getElementById("pmt-imp-l30-total").style.display = pmtImpL30Total > 0 ? "inline" : "none";
                      

                    document.getElementById("clicks-l7-total").innerText = clicksL7Total > 0 ? ` (${clicksL7Total.toFixed(0)})` : "";
                    document.getElementById("clicks-l7-total").style.display = clicksL7Total > 0 ? "inline" : "none";

                    document.getElementById("kw-clicks-l7-total").innerText = kwClicksL7Total > 0 ? ` (${kwClicksL7Total.toFixed(0)})` : "";
                    document.getElementById("kw-clicks-l7-total").style.display = kwClicksL7Total > 0 ? "inline" : "none";


                    document.getElementById("pmt-clicks-l7-total").innerText = pmtClicksL7Total > 0 ? ` (${pmtClicksL7Total.toFixed(0)})` : "";
                    document.getElementById("pmt-clicks-l7-total").style.display = pmtClicksL7Total > 0 ? "inline" : "none";


                    document.getElementById("clicks-l30-total").innerText = clicksL30Total > 0 ? ` (${clicksL30Total.toFixed(0)})` : "";
                    document.getElementById("clicks-l30-total").style.display = clicksL30Total > 0 ? "inline" : "none";

                    document.getElementById("kw-clicks-l30-total").innerText = kwClicksL30Total > 0 ? ` (${kwClicksL30Total.toFixed(0)})` : "";
                    document.getElementById("kw-clicks-l30-total").style.display = kwClicksL30Total > 0 ? "inline" : "none";

                    document.getElementById("pmt-clicks-l30-total").innerText = pmtClicksL30Total > 0 ? ` (${pmtClicksL30Total.toFixed(0)})` : "";
                    document.getElementById("pmt-clicks-l30-total").style.display = pmtClicksL30Total > 0 ? "inline" : "none";


                    document.getElementById("sales-l7-total").innerText = salesL7Total > 0 ? ` (${salesL7Total.toFixed(0)})` : "";
                    document.getElementById("sales-l7-total").style.display = salesL7Total > 0 ? "inline" : "none";

                    document.getElementById("kw-sales-l7-total").innerText = kwSalesL7Total > 0 ? ` (${kwSalesL7Total.toFixed(0)})` : "";
                    document.getElementById("kw-sales-l7-total").style.display = kwSalesL7Total > 0 ? "inline" : "none";

                    document.getElementById("pmt-sales-l7-total").innerText = pmtSalesL7Total > 0 ? ` (${pmtSalesL7Total.toFixed(0)})` : "";
                    document.getElementById("pmt-sales-l7-total").style.display = pmtSalesL7Total > 0 ? "inline" : "none";


                    document.getElementById("sales-l30-total").innerText = salesL30Total > 0 ? ` (${salesL30Total.toFixed(0)})` : "";
                    document.getElementById("sales-l30-total").style.display = salesL30Total > 0 ? "inline" : "none";

                    document.getElementById("kw-sales-l30-total").innerText = kwSalesL30Total > 0 ? ` (${kwSalesL30Total.toFixed(0)})` : "";
                    document.getElementById("kw-sales-l30-total").style.display = kwSalesL30Total > 0 ? "inline" : "none";


                    document.getElementById("pmt-sales-l30-total").innerText = pmtSalesL30Total > 0 ? ` (${pmtSalesL30Total.toFixed(0)})` : "";
                    document.getElementById("pmt-sales-l30-total").style.display = pmtSalesL30Total > 0 ? "inline" : "none";


                    document.getElementById("sold-l7-total").innerText = soldL7Total > 0 ? ` (${soldL7Total.toFixed(0)})` : "";
                    document.getElementById("sold-l7-total").style.display = soldL7Total > 0 ? "inline" : "none";

                    document.getElementById("kw-sold-l7-total").innerText = kwSoldL7Total > 0 ? ` (${kwSoldL7Total.toFixed(0)})` : "";
                    document.getElementById("kw-sold-l7-total").style.display = kwSoldL7Total > 0 ? "inline" : "none";

                    document.getElementById("pmt-sold-l7-total").innerText = pmtSoldL7Total > 0 ? ` (${pmtSoldL7Total.toFixed(0)})` : "";
                    document.getElementById("pmt-sold-l7-total").style.display = pmtSoldL7Total > 0 ? "inline" : "none";

                    document.getElementById("sold-l30-total").innerText = soldL30Total > 0 ? ` (${soldL30Total.toFixed(0)})` : "";
                    document.getElementById("sold-l30-total").style.display = soldL30Total > 0 ? "inline" : "none";

                    document.getElementById("kw-sold-l30-total").innerText = kwSoldL30Total > 0 ? ` (${kwSoldL30Total.toFixed(0)})` : "";
                    document.getElementById("kw-sold-l30-total").style.display = kwSoldL30Total > 0 ? "inline" : "none";

                    document.getElementById("pmt-sold-l30-total").innerText = pmtSoldL30Total > 0 ? ` (${pmtSoldL30Total.toFixed(0)})` : "";
                    document.getElementById("pmt-sold-l30-total").style.display = pmtSoldL30Total > 0 ? "inline" : "none";

                    document.getElementById("spend-l30-total").innerText = spendL30Total > 0 ? ` (${spendL30Total.toFixed(0)})` : "";
                    document.getElementById("spend-l30-total").style.display = spendL30Total > 0 ? "inline" : "none";

                    document.getElementById("kw-spend-l30-total").innerText = kwSpendL30Total > 0 ? ` (${kwSpendL30Total.toFixed(0)})` : "";
                    document.getElementById("kw-spend-l30-total").style.display = kwSpendL30Total > 0 ? "inline" : "none";


                    document.getElementById("pmt-spend-l30-total").innerText = pmtSpendL30Total > 0 ? ` (${pmtSpendL30Total.toFixed(0)})` : "";
                    document.getElementById("pmt-spend-l30-total").style.display = pmtSpendL30Total > 0 ? "inline" : "none";


                    document.getElementById("spend-l7-total").innerText = spendL7Total > 0 ? ` (${spendL7Total.toFixed(0)})` : "";
                    document.getElementById("spend-l7-total").style.display = spendL7Total > 0 ? "inline" : "none";


                    document.getElementById("kw-spend-l7-total").innerText = kwSpendL7Total > 0 ? ` (${kwSpendL7Total.toFixed(0)})` : "";
                    document.getElementById("kw-spend-l7-total").style.display = kwSpendL7Total > 0 ? "inline" : "none";


                    document.getElementById("pmt-spend-l7-total").innerText = pmtSpendL7Total > 0 ? ` (${pmtSpendL7Total.toFixed(0)})` : "";
                    document.getElementById("pmt-spend-l7-total").style.display = pmtSpendL7Total > 0 ? "inline" : "none";

                  
                    let percentage = total > 0 ? ((filtered / total) * 100).toFixed(0) : 0;
                    document.getElementById("total-campaigns").innerText = filtered;
                    document.getElementById("percentage-campaigns").innerText = percentage + "%";

                }

                // 🔁 Update stats on relevant table events
                table.on("dataFiltered", updateCampaignStats);
                table.on("pageLoaded", updateCampaignStats);
                table.on("dataProcessed", updateCampaignStats);

                // 🔍 Live search + filter events
                $("#global-search").on("keyup", function () {
                    table.setFilter(combinedFilter);
                    updateCampaignStats();
                });

                $("#status-filter, #inv-filter, #nra-filter").on("change", function () {
                    table.setFilter(combinedFilter);
                    updateCampaignStats();
                });

                // Initialize stats after build
                updateCampaignStats();
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["INV", "L30", "DIL %", "e_l30", "NR"];

                    colsToToggle.forEach(colName => {
                        let col = table.getColumn(colName);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-spendL30-btn")) {
                    let colsToToggle = ["kw_spend_L30", "pmt_spend_L30"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-spendL7-btn")) {
                    let colsToToggle = ["kw_spend_L7", "pmt_spend_L7"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-soldL30-btn")) {
                    let colsToToggle = ["kw_sold_L30", "pmt_sold_L30"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-soldL7-btn")) {
                    let colsToToggle = ["kw_sold_L7", "pmt_sold_L7"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-salesL30-btn")) {
                    let colsToToggle = ["kw_sales_L30", "pmt_sales_L30"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-salesL7-btn")) {
                    let colsToToggle = ["kw_sales_L7", "pmt_sales_L7"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-clicksL30-btn")) {
                    let colsToToggle = ["kw_clicks_L30", "pmt_clicks_L30"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-clicksL7-btn")) {
                    let colsToToggle = ["kw_clicks_L7", "pmt_clicks_L7"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-impL30-btn")) {
                    let colsToToggle = ["kw_impr_L30", "pmt_impr_L30"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
                if (e.target.classList.contains("toggle-impL7-btn")) {
                    let colsToToggle = ["kw_impr_L7", "pmt_impr_L7"];

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

                let exportData = allData.map(row => ({
                    sku: row.sku,
                    SPEND_L30: row.SPEND_L30.toFixed(2),
                }));

                let ws = XLSX.utils.json_to_sheet(exportData);
                let wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Campaigns");

                XLSX.writeFile(wb, "ebay_ad_running.xlsx");
            });

            document.body.style.zoom = "80%";
        });
    </script>
@endsection
