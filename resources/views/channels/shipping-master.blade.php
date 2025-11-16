@extends('layouts.vertical', ['title' => 'Shipping Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
            border-right: 1px solid #e5e7eb;
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
        'page_title' => 'Shipping Master',
        'sub_title' => 'Shipping Master',
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
                        <h4 class="mb-0 fw-bold text-primary">Shipping Master</h4>
                    </div>
                    <div id="shipping-table"></div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.documentElement.setAttribute("data-sidenav-size", "condensed");

            const channelsOrder = ["Shopify", "Temu", "Tiktok Shop", "Aliexpress", "Shein", "DHGate"];
            let shopifyData = {};

            const table = new Tabulator("#shipping-table", {
                layout: "fitData",
                pagination: "local",
                paginationSize: 25,
                height: "700px",
                ajaxURL: "/fetch-shipping-rate/data",
                columns: [
                    { title: "Channel", field: "channel", width: 150, frozen: true },
                    {
                        title: "Carrier Name",
                        field: "carrier_name",
                        width: 175,
                        editor: "input"
                    },
                    ...generateLbsColumns(),
                    {
                        title: "Updation Date",
                        field: "updation_date",
                        hozAlign: "center",
                        editor: "input"
                    },
                    { title: "Channel", field: "channel_id", visible: false },
                    { title: "Shipping Rate Id", field: "rate_id", visible: false },

                ],

                ajaxResponse: function (url, params, response) {
                    const preferredOrder = ['Shopify', 'Temu', 'Tiktok Shop', 'Aliexpress', 'Shein', 'DHGate'];

                    const shopify = response.find(item => item.channel === "Shopify");
                    if (shopify) {
                        shopifyData = shopify;
                    }

                    response.sort((a, b) => {
                        const indexA = preferredOrder.indexOf(a.channel);
                        const indexB = preferredOrder.indexOf(b.channel);

                        if (indexA === -1 && indexB === -1) {
                            return a.channel.localeCompare(b.channel);
                        }

                        if (indexA === -1) return 1;
                        if (indexB === -1) return -1;

                        return indexA - indexB;
                    });

                    table.redraw(true);

                    return response;
                },
            });

            table.on("cellEdited", function (cell) {
                const row = cell.getRow();
                const rowData = row.getData();
                const field = cell.getField();
                const newValue = cell.getValue();

                fetch("/update-shipping-rate", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        id: rowData.rate_id,
                        channel_id: rowData.channel_id,
                        field: field,
                        value: newValue
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error("Failed to update");
                    }
                    return response.json();
                })
                .then(data => {
                    if (!rowData.rate_id && data.data && data.data.id) {
                        row.update({ rate_id: data.data.id });
                    }
                })
                .catch(error => {
                    alert("Failed to update. Please try again.");
                });
            });

            function generateLbsColumns() {
                const columns = [];

                const fractional = [0.25, 0.5, 0.75];
                fractional.forEach((val) => {
                    const label = `${val} lbs`;
                    const field = "w_" + String(val).replace(".", "_") + "_lbs";

                    columns.push({
                        title: label,
                        field: field,
                        editor: "input",
                        formatter: (cell) => {
                            const rowData = cell.getRow().getData();

                            const value = parseFloat(cell.getValue());
                            const shopifyValue = parseFloat(shopifyData[field]);
                            const cellEl = cell.getElement();
                            cellEl.style.backgroundColor = ""; // reset before applying new

                            if (rowData.channel === 'Shopify') {
                                return isNaN(value) ? "" : `$${value}`;
                            }

                            if (!isNaN(value) && !isNaN(shopifyValue)) {
                                cellEl.style.backgroundColor = value > shopifyValue ? "#ff071df2" : "#19bf41";
                            }

                            return isNaN(value) ? "" : `$${value}`;
                        },
                        width: 120
                    });
                });

                for (let i = 1; i <= 20; i++) {
                    const label = `${i} lbs`;
                    const field = "w_" + i + "_lbs";

                    columns.push({
                        title: label,
                        field: field,
                        editor: "input",
                        formatter: (cell) => {
                            const rowData = cell.getRow().getData();

                            const value = parseFloat(cell.getValue());
                            const shopifyValue = parseFloat(shopifyData[field]);
                            const cellEl = cell.getElement();
                            cellEl.style.backgroundColor = ""; // reset

                            if (rowData.channel === 'Shopify') {
                                return isNaN(value) ? "" : `$${value}`;
                            }

                            if (!isNaN(value) && !isNaN(shopifyValue)) {
                                cellEl.style.backgroundColor = value > shopifyValue ? "#f8d7da" : "#d4edda";
                            }

                            return isNaN(value) ? "" : `$${value}`;
                        },
                        width: 115
                    });
                }

                return columns;
            }

            document.body.style.zoom = "83%";
        });
    </script>

