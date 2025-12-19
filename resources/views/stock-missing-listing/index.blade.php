@extends('layouts.vertical', ['title' => 'Stock Missing Listing', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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

.status-active {
    background-color: #28a745 !important;
    color: #fff !important;
    font-weight: 600;
    border: none;
    border-radius: 4px;
}

.status-inactive {
    background-color: #dc3545 !important;
    color: #fff !important;
    font-weight: 600;
    border: none;
    border-radius: 4px;
}

/* Dropdown options colors */
.status-active-option {
    background-color: #28a745 !important;
    color: white;
}

.status-inactive-option {
    background-color: #dc3545 !important;
    color: white;
}

/* This ensures the background shows properly inside Tabulator */
.tabulator-cell select {
    width: 100%;
    padding: 3px 5px;
}

.kpi-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    transition: 0.2s ease-in-out;
    color: #000;
}

.kpi-blue {
    background-color: #DCEBFF !important;
}

.kpi-green {
    background-color: #DFF5E3 !important;
}

.kpi-yellow {
    background-color: #FFF1CC !important;
}

.kpi-red {
    background-color: #FFE0E0 !important;
}

.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
}

.kpi-title {
    font-size: 14px;
    font-weight: 700;
    color: #000 !important;
    margin-bottom: 4px;
}

.kpi-value {
    font-size: 22px;
    font-weight: 800;
    color: #000;
}
</style>
@endsection
@section('content')
@include('layouts.shared.page-title', [
'page_title' => 'Stock Missing Listing',
'sub_title' => 'Stock Missing Listing',
])
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <div class="mb-4">
                    <!-- Filters Row -->
                    <div class="row g-3 mb-3">
                        <!-- Stats -->
                        <div class="col-md-6">
                            <div class="d-flex gap-2 justify-content-end">
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 align-items-center mb-2">
                        <div class="col-md-6">
                            <div id="total-notlisted-kpi" style="font-size:18px; font-weight:bold; color:#d9534f;">
                                Total Not Listed: 0
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 align-items-center mb-3">
                        <div class="col-md-6">
                            <div class="d-flex gap-2">
                                <div class="input-group">
                                    <input type="text" id="global-search" class="form-control form-control-md"
                                        placeholder="Search...">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="d-flex justify-content-end gap-2">
                                <button id="exportXlsBtn" class="btn btn-success">
                                    Export XLS
                                </button>
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
            Loading data...
        </div>
        <div style="color: #a3e635; font-size: 0.9rem; margin-top: 0.5rem;">
            Please wait while we process your request
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
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

    function updateTotalNotListed(data) {
        if (Array.isArray(data) && data.length && typeof data[0].getData === "function") {
            data = data.map(r => r.getData());
        }

        let total = 0;

        (data || []).forEach(row => {
            const ls = row.listing_status || {};

            marketplaces.forEach(mp => {
                if (ls[mp] === "Not Listed") {
                    total++;
                }
            });
        });

        document.getElementById("total-notlisted-kpi").innerHTML =
            "Total Not Listed: " + total;
    }
    
    function formatMarketName(mp) {
        return mp.replace(/_/g, " ").toUpperCase();
    }

    function statusFormatter(cell) {
        let val = cell.getValue() || "Not Listed";

        let color =
            val === "Listed" ? "green" :
            val === "NRL" ? "orange" :
            val === "Missing" ? "red" :
            val === "Live" ? "blue" :
            val === "Inactive" ? "gray" :
            "red";

        return `<span style="font-weight:bold; color:${color};">${val}</span>`;
    }

    let marketplaces = [];
    let table = null;
    let originalDataCache = [];

    function buildMarketplaceColumns() {

        const baseCols = [{
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
                field: "sku"
            },
            {
                title: "INV",
                field: "inv",
                formatter: function(cell) {
                    const value = cell.getValue();
                    return value !== null && value !== undefined ? value : 0;
                }
            },
        ];

        const marketCols = marketplaces.map(mp => {
            return {
                title: formatMarketName(mp), 
                field: `listing_status.${mp}`,
                formatter: statusFormatter,
                headerSort: false,
                headerHozAlign: "center",
                titleFormatter: () => createStatusFilterHeader(mp),
                accessorDownload: function(value, data) {
                    return value ? value : "Not Listed";
                },
            };
        });

        const allCols = baseCols.concat(marketCols);

        table.setColumns(allCols);
    }

    function updateMarketplaceCounts(data) {
        if (Array.isArray(data) && data.length && typeof data[0].getData === "function") {
            data = data.map(r => r.getData());
        }

        const counts = {};
        marketplaces.forEach(mp => counts[mp] = 0);

        (data || []).forEach(row => {
            const ls = row.listing_status || {};

            marketplaces.forEach(mp => {
                if (ls[mp] === "Not Listed") {
                    counts[mp]++;
                }
            });
        });

        marketplaces.forEach(mp => {
            const el = document.getElementById("cnt-" + mp);
            if (el) el.innerHTML = `(${counts[mp]})`;
        });
    }


    table = new Tabulator("#budget-under-table", {
        index: "sku",
        ajaxURL: "/stock-missing-listing/data",
        layout: "fitDataFill",
        pagination: "local",
        paginationSize: 25,
        paginationSizeSelector: [25, 50, 100, 200],
        movableColumns: true,
        resizableColumns: true,

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
                field: "sku"
            },
            {
                title: "INV",
                field: "inv",
                formatter: function(cell) {
                    const value = cell.getValue();
                    return value !== null && value !== undefined ? value : 0;
                }
            },
        ],

        ajaxResponse: function(url, params, response) {
            if (response && Array.isArray(response.data) && response.data.length) {
                const detected = Object.keys(response.data[0].listing_status || {});
                const same = detected.length === marketplaces.length && detected.every((v, i) =>
                    v === marketplaces[i]);
                originalDataCache = response.data || [];

                if (!same) {
                    marketplaces = detected;
                    buildMarketplaceColumns();
                }
            }

            setTimeout(() => {
                const active = table.getData("active");
                updateMarketplaceCounts(active);
                updateTotalNotListed(active);
            }, 50);

            return response.data;
        }
    });

    table.on("dataFiltered", function(filters, rows) {
        updateMarketplaceCounts(rows);
        updateTotalNotListed(rows);
    });

    function createStatusFilterHeader(mp) {
        const wrapper = document.createElement("div");
        wrapper.style.textAlign = "center";

        const title = document.createElement("div");
        title.innerHTML = formatMarketName(mp) + "<br/><span id='cnt-" + mp +
            "' style='color:red;font-weight:bold;'>(0)</span>";

        const select = document.createElement("select");
        select.style.marginTop = "4px";
        select.style.width = "90px";

        ["All", "Listed", "NRL", "Missing", "Live", "Inactive", "Not Listed"].forEach(st => {
            const opt = document.createElement("option");
            opt.value = st;
            opt.textContent = st;
            select.appendChild(opt);
        });

        select.addEventListener("change", () => {
            let val = select.value;
            let field = "listing_status." + mp;

            if (val === "All") {
                let filters = table.getFilters();

                filters.forEach(f => {
                    if (f.field === field) {
                        table.removeFilter(f.field, f.type, f.value);
                    }
                });

                return;
            }

            table.setFilter(field, "=", val);
        });

        wrapper.appendChild(title);
        wrapper.appendChild(select);
        return wrapper;
    }

    table.on("tableBuilt", function() {
        function combinedFilter(data) {
            let searchVal = $("#global-search").val()?.toLowerCase() || "";
            if (searchVal && !(data.parent?.toLowerCase().includes(searchVal) || data.sku?.toLowerCase().includes(searchVal))) {
                return false;
            }

            return true;
        }

        table.setFilter(combinedFilter);

        $("#global-search").on("keyup", function() {
            table.setFilter(combinedFilter);
        });
    });

    // Export to Excel
    document.getElementById("exportXlsBtn").addEventListener("click", function () {
        table.download("xlsx", "stock-missing-listing-data.xlsx", {
            sheetName: "Stock Missing Listing",
        });
    });

});
</script>
@endsection

