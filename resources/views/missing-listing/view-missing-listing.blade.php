@extends('layouts.vertical', ['title' => 'Missing Listings', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
            background-color: #28a745 !important;    /* green */
            color: #fff !important;
            font-weight: 600;
            border: none;
            border-radius: 4px;
        }

        .status-inactive {
            background-color: #dc3545 !important;   /* red */
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
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
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
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
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
        'page_title' => 'Missing Listings',
        'sub_title' => 'Missing Listings',
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

                        <div class="row g-3 align-items-center mb-2">
                            <div class="col-md-6">
                                <div id="total-notlisted-kpi"
                                    style="font-size:18px; font-weight:bold; color:#d9534f;">
                                    Total Not Listed: 0
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 align-items-center mb-3">
                            <div class="col-md-6">
                                <div class="d-flex gap-2">
                                    <div class="input-group">
                                        <input type="text" id="global-search" class="form-control form-control-md"
                                            placeholder="Search campaign...">
                                    </div>
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
            function updateTotalNotListed(data) {
                let total = 0;

                data.forEach(row => {
                    const ls = row.listing_status || {};
                    marketplaces.forEach(mp => {
                        if (ls[mp] === "Not Listed") total++;
                    });
                });

                document.getElementById("total-notlisted-kpi").innerHTML =
                    "Total Not Listed: " + total;
            }

            function updateKpis(table) {
                let data = table.getData();

                let totalAdSold = 0;
                let totalAdSales = 0;
                let totalSpend = 0;

                data.forEach(row => {
                    totalAdSold += Number(row.ad_sold || 0);
                    totalAdSales += Number(row.ad_sales || 0);
                    totalSpend += Number(row.spend || 0);
                });

                let totalAcos = totalAdSales > 0 ? ((totalSpend / totalAdSales) * 100).toFixed(2) : 0;

                document.getElementById("kpi-ad-sold").textContent = totalAdSold.toLocaleString();
                document.getElementById("kpi-ad-sales").textContent = totalAdSales.toLocaleString();
                document.getElementById("kpi-ad-spend").textContent = totalSpend.toLocaleString();
                document.getElementById("kpi-acos").textContent = totalAcos + "%";
            }

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

            function formatMarketName(mp) {
                return mp.replace(/_/g, " ").toUpperCase();
            }

            function statusFormatter(cell) {
                let val = cell.getValue() || "Not Listed";

                let color =
                    val === "Listed" ? "green" :
                    val === "NRL"    ? "orange" :
                                    "red";

                return `<span style="font-weight:bold; color:${color};">${val}</span>`;
            }

            let marketplaces = [];
            let table = null;

            function buildMarketplaceColumns() {

                const baseCols = [
                    {
                        formatter: "rowSelection",
                        titleFormatter: "rowSelection",
                        hozAlign: "center",
                        headerSort: false,
                        width: 50
                    },
                    { title: "Parent", field: "parent" },
                    { title: "SKU", field: "sku" },
                ];

                const marketCols = marketplaces.map(mp => {
                    return {
                        title: "",
                        field: `listing_status.${mp}`,
                        formatter: statusFormatter,
                        headerSort: false,
                        headerHozAlign: "center",
                        titleFormatter: function() {
                            return createStatusFilterHeader(mp);
                        },
                    };
                });

                const allCols = baseCols.concat(marketCols);

                table.setColumns(allCols);
            }

            function updateMarketplaceCounts(data) {
                const counts = {};
                marketplaces.forEach(mp => counts[mp] = 0);

                data.forEach(row => {
                    const ls = row.listing_status || {};
                    marketplaces.forEach(mp => {
                        if (ls[mp] === "Not Listed") counts[mp]++;
                    });
                });

                marketplaces.forEach(mp => {
                    const el = document.getElementById("cnt-" + mp);
                    if (el) el.innerHTML = `(${counts[mp]})`;
                });
            }

            table = new Tabulator("#budget-under-table", {
                index: "sku",
                ajaxURL: "/stock/missing/listing/data",
                layout: "fitDataFill",
                pagination: "local",
                paginationSize: 25,
                paginationSizeSelector: [25, 50, 100, 200],
                movableColumns: true,
                resizableColumns: true,

                columns: [
                    { formatter: "rowSelection", titleFormatter: "rowSelection", hozAlign: "center", headerSort: false, width: 50 },
                    { title: "Parent", field: "parent" },
                    { title: "SKU", field: "sku" },
                ],

                ajaxResponse: function(url, params, response) {
                    if (response && Array.isArray(response.data) && response.data.length) {
                        const detected = Object.keys(response.data[0].listing_status || {});
                        const same = detected.length === marketplaces.length && detected.every((v, i) => v === marketplaces[i]);
                        if (!same) {
                            marketplaces = detected;
                            buildMarketplaceColumns();
                        }
                    }

                    setTimeout(() => {
                        updateMarketplaceCounts(response.data || []);
                        updateTotalNotListed(response.data || []);
                    }, 50);

                    return response.data;
                }
            });

            function createStatusFilterHeader(mp) {
                const wrapper = document.createElement("div");
                wrapper.style.textAlign = "center";

                const title = document.createElement("div");
                title.innerHTML = formatMarketName(mp) + "<br/><span id='cnt-" + mp + "' style='color:red;font-weight:bold;'>(0)</span>";

                const select = document.createElement("select");
                select.style.marginTop = "4px";
                select.style.width = "90px";

                ["All", "Listed", "NRL", "Not Listed"].forEach(st => {
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

            document.addEventListener("change", function(e) {
                if (e.target.classList.contains("editable-status-select")) {
                    let sku = e.target.getAttribute("data-sku");
                    let value = e.target.value;

                    fetch('/tiktok-gmv-ad/update-status', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            sku: sku,
                            status: value
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        console.log("Status updated", data);
                        toastr.success("Status updated");
                    })
                    .catch(err => {
                        console.error(err);
                        toastr.error("Update failed");
                    });
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

                    let adStatusVal = $("#ad-status-filter").val(); 
                    if (adStatusVal && data.ad_status !== adStatusVal) {
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

                $("#ad-status-filter, #inv-filter, #nrl-filter, #nra-filter, #fba-filter").on("change", function() {
                    table.setFilter(combinedFilter);
                });

                updateCampaignStats();
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-cols-btn")) {
                    let btn = e.target;

                    let colsToToggle = ["INV", "L30", "DIL %", "A_L30", "A DIL %", "NRL", "NRA", "FBA"];

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
                    let colsToToggle = ["acos_L15", "acos_L7"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
                        if (col) {
                            col.toggle();
                        }
                    });
                }
            });

            document.addEventListener("click", function(e) {
                if (e.target.classList.contains("toggle-clicks-cols-btn")) {
                    let colsToToggle = ["clicks_L15", "clicks_L7"];

                    colsToToggle.forEach(colField => {
                        let col = table.getColumn(colField);
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

                $.ajax({
                    url: "{{ route('tiktok.import') }}",
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    success: function (response) {
                        $('#importModal').modal('hide');
                        $('#importFile').val('');
                        showNotification('success', 'Import successful! Processed: ' + response.processed);
                        location.reload();
                    },
                    error: function (xhr) {
                        let message = 'Import failed';

                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.error) {
                                message = xhr.responseJSON.error;
                            }

                            else if (xhr.responseJSON.errors && Array.isArray(xhr.responseJSON.errors)) {
                                message = xhr.responseJSON.errors.join('<br>');
                            }
                        }

                        showNotification('danger', message);
                    }
                });
            });
        });
    </script>
@endsection
