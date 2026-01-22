@extends('layouts.vertical', ['title' => 'Amazon - Budget under utilised', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
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
            background-color: #05bd30 !important;
            color: #ffffff !important;
        }

        .pink-bg {
            background-color: #ff01d0 !important;
            color: #ffffff !important;
        }

        .red-bg {
            background-color: #ff2727 !important;
            color: #ffffff !important;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Amazon - Budget',
        'sub_title' => 'Amazon - Budget',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                        <h4 class="mb-0 fw-bold text-primary">Budget under utilised</h4>
                    </div>

                    <!-- Filters Row -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label>From Date:</label>
                            <input type="date" id="filter-start" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label>To Date:</label>
                            <input type="date" id="filter-end" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label>Search:</label>
                            <input type="text" id="global-search" class="form-control" placeholder="Parent, Campaign, Ad Type">
                        </div>
                    </div>

                    <div id="budget-under-table"></div>
                </div>

            </div>
        </div>
    </div>

    <!-- L60 Data Modal (stacked tabular) -->
    <div class="modal fade" id="l60-data-modal" tabindex="-1" aria-labelledby="l60-data-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="l60-data-modal-label">L60 Data – All Rows</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive" style="max-height: 70vh;">
                        <table class="table table-bordered table-striped mb-0" id="l60-data-stack-table">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>#</th>
                                    <th>Parent</th>
                                    <th>Campaign</th>
                                    <th>Ad Type</th>
                                    <th class="text-end">L60 Clicks</th>
                                    <th class="text-end">L60 Spend</th>
                                    <th class="text-end">Sales L60</th>
                                    <th class="text-end">CPC L60</th>
                                    <th class="text-end">Orders L60</th>
                                    <th class="text-end">ACOS L60</th>
                                </tr>
                            </thead>
                            <tbody id="l60-data-stack-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {

            const table = new Tabulator("#budget-under-table", {
                layout: "fitData",
                pagination: "local",
                paginationSize: 50,
                height: "700px",
                ajaxURL: "/campaigns/list",
                columns: [
                    {
                        title: "#",
                        formatter: "rownum", // Tabulator built-in row number
                        hozAlign: "center",
                        width: 70,
                        frozen: true
                    },
                    {
                        title: "PARENT",
                        field: "parent",
                        frozen: true
                    },
                    {
                        title: "Campaigns",
                        field: "campaignName",
                        frozen: true
                    },
                    {
                        title: "AD TYPE",
                        field: "ad_type",
                        frozen: true
                    },
                    {
                        title: "Tab",
                        width: 80,
                        hozAlign: "center",
                        formatter: function(cell) {
                            return '<a href="#" class="l60-tab-link text-primary" title="View L60 Data">L60</a>';
                        }
                    },
                    {
                        title: "Note",
                        field: "note",
                        editor: "input",
                        width: 200,
                        frozen: true
                    },
                    {
                        title: "Status",
                        field: "campaignStatus",
                        formatter: function(cell) {
                            var val = cell.getValue();
                            if (val === "ENABLED")
                                return "<span class='badge bg-success'>ENABLED</span>";
                            if (val === "PAUSED")
                                return "<span class='badge bg-warning text-dark'>PAUSED</span>";
                            return "<span class='badge bg-secondary'>" + val + "</span>";
                        }
                    },
                    {
                        title: "BGT",
                        field: "campaignBudgetAmount",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value;
                        }
                    },
                    {
                        title: "7 UB%",
                        field: "l7_spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l7_spend = parseFloat(row.l7_spend) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;

                            // Set cell background color based on UB%
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
                        title: "1 UB%",
                        field: "l1_spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var l1_spend = parseFloat(row.l1_spend) || 0;
                            var budget = parseFloat(row.campaignBudgetAmount) || 0;
                            var ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;

                            // Set cell background color based on UB%
                            var td = cell.getElement();
                            td.classList.remove('green-bg', 'pink-bg', 'red-bg');
                            if (ub1 >= 70 && ub1 <= 90) {
                                td.classList.add('green-bg');
                            } else if (ub1 > 90) {
                                td.classList.add('pink-bg');
                            } else if (ub1 < 70) {
                                td.classList.add('red-bg');
                            }

                            return ub1.toFixed(0) + "%";
                        }
                    },

                    {
                        title: "L60 Clicks",
                        field: "l60_clicks",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "L30 Clicks",
                        field: "l30_clicks",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "L15 Clicks",
                        field: "l15_clicks",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "L7 Clicks",
                        field: "l7_clicks",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "L60 SPEND",
                        field: "l60_spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "L30 SPEND",
                        field: "l30_spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "L15 SPEND",
                        field: "l15_spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "L7 SPEND",
                        field: "l7_spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "L1 SPEND",
                        field: "l1_spend",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "SALES L60",
                        field: "l60_sales",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "SALES L30",
                        field: "l30_sales",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "SALES L15",
                        field: "l15_sales",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "SALES L7",
                        field: "l7_sales",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "CPC L60",
                        field: "l60_cpc",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "CPC L30",
                        field: "l30_cpc",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "CPC L15",
                        field: "l15_cpc",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "CPC L7",
                        field: "l7_cpc",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "Orders L60",
                        field: "l60_orders",
                        hozAlign: "right"
                    },
                    {
                        title: "Orders L30",
                        field: "l30_orders",
                        hozAlign: "right"
                    },
                    {
                        title: "Orders L15",
                        field: "l15_orders",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value + "%";
                        }
                    },
                    {
                        title: "Orders L7",
                        field: "l7_orders",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value + "%";
                        }
                    },
                    {
                        title: "ACOS L60",
                        field: "l60_acos",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "ACOS L30",
                        field: "l30_acos",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "ACOS L15",
                        field: "l15_acos",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0) + "%";
                        }
                    },
                    {
                        title: "ACOS L7",
                        field: "l7_acos",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0) + "%";
                        }
                    },

                    {
                        title: "PFT",
                        field: "pft",
                        hozAlign: "right"
                    },
                    {
                        title: "TPFT",
                        field: "tpft",
                        hozAlign: "right"
                    },
                    {
                        title: "sbid",
                        field: "sbid",
                        editor: "input"
                    },
                    {
                        title: "yes sbid",
                        field: "yes_sbid",
                        editor: "input"
                    },
                ],
                ajaxResponse: function(url, params, response) {
                    return response.data;
                },
            });
            
            table.on("tableBuilt", function() {
                table.setFilter(function(data) {
                    var budget = parseFloat(data.campaignBudgetAmount) || 0;
                    var l7_spend = parseFloat(data.l7_spend) || 0;
                    var l1_spend = parseFloat(data.l1_spend) || 0;
    
                    var ub7 = budget > 0 ? (l7_spend / (budget * 7)) * 100 : 0;
                    var ub1 = budget > 0 ? (l1_spend / budget) * 100 : 0;
    
                    return ub7 < 70 && ub1 < 70;
                });
            });



            // Function to apply combined filters
            function applyFilters() {
                let start = document.getElementById("filter-start").value;
                let end = document.getElementById("filter-end").value;
                let query = document.getElementById("global-search").value.toLowerCase();

                table.setFilter(function(data) {
                    // Date filter
                    let rowStart = new Date(data.startDate);
                    let rowEnd = new Date(data.endDate);
                    if (start && rowEnd < new Date(start)) return false;
                    if (end && rowStart > new Date(end)) return false;

                    // Search filter
                    if (query) {
                        return (
                            (data.parent && data.parent.toLowerCase().includes(query)) ||
                            (data.campaignName && data.campaignName.toLowerCase().includes(query)) ||
                            (data.ad_type && data.ad_type.toLowerCase().includes(query))
                        );
                    }

                    return true; // show row if no query
                });
            }

            // Search input: type karte hi filter apply
            document.getElementById("global-search").addEventListener("keyup", applyFilters);

            // Date filters
            document.getElementById("filter-start").addEventListener("change", applyFilters);
            document.getElementById("filter-end").addEventListener("change", applyFilters);

            // Tab link: show L60 Data of all rows in modal (stacked tabular)
            function escapeHtml(str) {
                if (str == null || str === undefined) return '';
                var div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }
            function fmtNum(v) {
                if (v == null || v === undefined || v === '') return '—';
                var n = parseFloat(v);
                return isNaN(n) ? '—' : n.toFixed(2);
            }
            document.addEventListener("click", function(e) {
                if (!e.target.closest(".l60-tab-link")) return;
                e.preventDefault();
                var data = table.getData();
                var tbody = document.getElementById("l60-data-stack-tbody");
                tbody.innerHTML = "";
                data.forEach(function(row, i) {
                    var tr = document.createElement("tr");
                    var acos = row.l60_acos != null && row.l60_acos !== '' ? parseFloat(row.l60_acos).toFixed(2) + '%' : '—';
                    tr.innerHTML = 
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + escapeHtml(row.parent || '') + '</td>' +
                        '<td>' + escapeHtml(row.campaignName || '') + '</td>' +
                        '<td>' + escapeHtml(row.ad_type || '') + '</td>' +
                        '<td class="text-end">' + fmtNum(row.l60_clicks) + '</td>' +
                        '<td class="text-end">' + fmtNum(row.l60_spend) + '</td>' +
                        '<td class="text-end">' + fmtNum(row.l60_sales) + '</td>' +
                        '<td class="text-end">' + fmtNum(row.l60_cpc) + '</td>' +
                        '<td class="text-end">' + fmtNum(row.l60_orders) + '</td>' +
                        '<td class="text-end">' + acos + '</td>';
                    tbody.appendChild(tr);
                });
                var modalEl = document.getElementById("l60-data-modal");
                if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
                    var modal = new bootstrap.Modal(modalEl);
                    modal.show();
                } else {
                    modalEl.classList.add("show");
                    modalEl.style.display = "block";
                    modalEl.setAttribute("aria-hidden", "false");
                }
            });

            document.body.style.zoom = "83%";
        });
    </script>
