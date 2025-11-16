@extends('layouts.vertical', ['title' => 'Traffic Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .tabulator .tabulator-header {
            background: linear-gradient(90deg, #e0e7ff 0%, #e0e7ff 100%);
            border-bottom: 2px solid #2563eb;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
        }

        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #e0e7ff;
            border-right: 1px solid #000000;
            padding: 16px 10px;
            font-weight: 700;
            color: #1e293b;
            font-size: 1.08rem;
            letter-spacing: 0.02em;
            transition: background 0.2s;
        }

        .tabulator .tabulator-header .tabulator-col:hover {
            background: #e0eaff;
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
            border-right: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            font-size: 1rem;
            color: #22223b;
            vertical-align: middle;
            transition: background 0.18s, color 0.18s;
        }

        .tabulator .tabulator-cell:focus {
            outline: 2px solid #2563eb;
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
            border-top: 1px solid #e5e7eb;
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

        .bg-green {
            background-color: #d4edda !important;
        }

        .bg-red {
            background-color: #f8d7da !important;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Traffic Master',
        'sub_title' => 'Traffic Master',
    ])
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert"
            style="background-color: #d1e7dd; color: #0f5132;">
            <style>
                .alert-success .btn-close {
                    filter: invert(41%) sepia(94%) saturate(362%) hue-rotate(89deg) brightness(90%) contrast(92%);
                }
            </style>
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                        <h4 class="mb-0 fw-bold text-primary">Traffic Master</h4>
                    </div>
                    <div id="traffic-table"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Traffic Details Modal -->
    <div class="modal fade" id="trafficModal" tabindex="-1" aria-labelledby="trafficModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered shadow-none">
            <div class="modal-content shadow-lg border-0 ">
                <!-- Modal Header -->
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold" id="trafficModalLabel">
                        📊 Traffic Details for - <span id="modalSku" class="text-dark"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-center">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 35%">Platform</th>
                                    <th style="width: 30%">Impressions</th>
                                    <th style="width: 30%">Clicks</th>
                                </tr>
                            </thead>
                            <tbody id="modalDetails" class="fw-medium">
                                <!-- Dynamic content will be injected here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Close
                    </button>
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
            document.documentElement.setAttribute("data-sidenav-size", "condensed");

            const getDilColor = (value) => {
                const percent = parseFloat(value) * 100;
                if (percent < 16.66) return 'red';
                if (percent >= 16.66 && percent < 25) return 'yellow';
                if (percent >= 25 && percent < 50) return 'green';
                return 'pink';
            };

            function groupBy(array, key) {
                return array.reduce((result, obj) => {
                    const groupKey = obj[key];
                    if (!result[groupKey]) result[groupKey] = [];
                    result[groupKey].push(obj);
                    return result;
                }, {});
            }

            const table = new Tabulator("#traffic-table", {
                index: "Sku",
                ajaxURL: "/fetch-traffic-rate/data",
                ajaxConfig: "GET",
                layout: "fitColumns",
                pagination: true,
                paginationSize: 100,
                paginationMode: "local",
                movableColumns: false,
                resizableColumns: true,
                height: "650px",
                rowFormatter: function(row) {
                    const data = row.getData();
                    const sku = data["Sku"] || '';

                    if (sku.toUpperCase().includes("PARENT")) {
                        row.getElement().classList.add("parent-row");
                    }
                },
                columns: [{
                        title: "Parent",
                        field: "Parent",
                        minWidth: 130,
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search parent.",
                        headerFilterFunc: "like",
                    },
                    {
                        title: "SKU",
                        field: "Sku",
                        minWidth: 130,
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search sku.",
                        headerFilterFunc: "like",
                    },
                    {
                        title: "INV",
                        field: "INV",
                        headerSort: true,
                        titleFormatter: function() {
                            return `<div>
                                INV<br>
                                <span id="total-inv-header" style="font-size:16px;color:black;font-weight:600;"></span>
                            </div>`;
                        },
                        formatter: "plaintext",
                        hozAlign: "center"
                    },
                    {
                        title: "OV L30",
                        field: "L30",
                        headerSort: true,
                        titleFormatter: function() {
                            return `<div>
                                OV L30<br>
                                <span id="total-l30-header" style="font-size:16px;color:black;font-weight:600;"></span>
                            </div>`;
                        },
                        formatter: "plaintext",
                        hozAlign: "center"
                    },
                    {
                        title: "Dil%",
                        field: "Dil",
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
                        title: "Impressions",
                        field: "Impressions",
                        formatter: function(cell) {
                            let value = cell.getValue() || 0;
                            return `${value} <span class="text-primary view-btn"> V</span>`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('view-btn')) {
                                showTrafficModal(cell.getRow().getData());
                            }
                        }
                    },
                    {
                        title: "Clicks",
                        field: "Clicks",
                        formatter: function(cell) {
                            let value = cell.getValue() || 0;
                            return `${value} <span class="text-primary view-btn"> C</span>`;
                        },
                        cellClick: function(e, cell) {
                            if (e.target.classList.contains('view-btn')) {
                                showTrafficModal(cell.getRow().getData());
                            }
                        }
                    },
                    {
                        title: "Views/Sessions",
                        field: "views",
                    },
                ],
                ajaxResponse: function(url, params, response) {
                    const rows = response.data;

                    rows.forEach(row => {
                        const inv = parseFloat(row.INV);
                        const l30 = parseFloat(row.L30);
                        if (!isNaN(inv) && inv !== 0 && !isNaN(l30)) {
                            row.dilColor = getDilColor(l30 / inv);
                        } else {
                            row.dilColor = "red";
                        }
                    });

                    return rows;
                },

            });

            table.on("dataProcessed", function() {
                const data = table.getData();
                groupedSkuData = groupBy(data, "Parent");

                setTimeout(() => updateTotalInvAndL30(table), 100);
            });

            function updateTotalInvAndL30(table) {
                const data = table.getData("active");

                const totalINV = data.reduce((sum, row) => sum + (parseFloat(row["INV"]) || 0), 0);
                const totalL30 = data.reduce((sum, row) => sum + (parseFloat(row["L30"]) || 0), 0);

                document.getElementById("total-inv-header").textContent = totalINV.toLocaleString();
                document.getElementById("total-l30-header").textContent = totalL30.toLocaleString();

            }

            function showTrafficModal(rowData) {
                document.getElementById("modalSku").textContent = rowData.Sku;

                let tbody = document.getElementById("modalDetails");
                tbody.innerHTML = "";

                for (let platform in rowData.details) {
                    let tr = document.createElement("tr");

                    tr.innerHTML = `
                        <td>${platform}</td>
                        <td>${rowData.details[platform].impressions}</td>
                        <td>${rowData.details[platform].clicks}</td>
                    `;

                    tbody.appendChild(tr);
                }

                let modal = new bootstrap.Modal(document.getElementById("trafficModal"));
                modal.show();
            }

            // document.body.style.zoom = "100%";
        });
    </script>
@endsection
