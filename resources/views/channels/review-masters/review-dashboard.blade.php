@extends('layouts.vertical', ['title' => 'Reviews Dashboard'])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* Resizer styling */
        .tabulator .tabulator-header .tabulator-col .tabulator-col-resize-handle {
            width: 5px;
            background-color: #dee2e6;
            cursor: ew-resize;
        }

        /* Header styling */
        .tabulator .tabulator-header {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .tabulator .tabulator-header .tabulator-col {
            text-align: center;
            background: #1a2942;
            border-right: 1px solid #ffffff;
            color: #fff;
            font-weight: bold;
            padding: 12px 8px;
        }

        .tabulator-tableholder {
            height: calc(100% - 100px) !important;
        }

        .tabulator-row {
            background-color: #ffffff !important;
            /* default black for all rows */
        }

        /* Cell styling */
        .tabulator .tabulator-cell {
            text-align: center;
            padding: 12px 8px;
            border-right: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
            font-weight: bolder;
            color: #000000;
        }

        .tabulator .tabulator-cell input,
        .tabulator .tabulator-cell select,
        .tabulator .tabulator-cell .form-select,
        .tabulator .tabulator-cell .form-control {
            font-weight: bold !important;
            color: #000000 !important;
        }

        /* Row hover effect */
        .tabulator-row:hover {
            background-color: rgba(0, 0, 0, .075) !important;
        }

        /* Parent row styling */
        .parent-row {
            background-color: #DFF0FF !important;
            font-weight: 600;
        }

        /* Pagination styling */
        .tabulator .tabulator-footer {
            background: #f4f7fa;
            border-top: 1px solid #e5e7eb;
            font-size: 1rem;
            color: #4b5563;
            padding: 5px;
            height: 90px;
        }

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
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Reviews Dashboard',
        'sub_title' => 'Reviews Dashboard',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-3">

                    </div>
                    <div id="review-dashboard"></div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const table = new Tabulator("#review-dashboard", {
                ajaxURL: "/review-dashboard-data",
                ajaxConfig: "GET",
                layout: "fitData",
                pagination: true,
                paginationSize: 100,
                paginationMode: "local",
                movableColumns: false,
                resizableColumns: true,
                height: "680px",
                columns:[
                    {
                        title: "Channels",
                        field: "channel_name",
                        headerFilter: "input",
                        headerFilterPlaceholder: "Search channel",
                        minWidth: 150
                    },
                    {
                        title: "Action Pending",
                        field: "action_pending_count",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const color = getRandomBadgeColor();
                            const rowData = cell.getData();

                            if (rowData.channel_name === 'Amazon') {
                                const url = '/review-master/amazon-product-reviews/';
                                return `<a href="${url}" class="fs-5 badge bg-${color}" target="_blank">${value}</a>`;
                            }

                            return `<span class="fs-5 badge bg-${color}">${value}</span>`;
                        }
                    },
                    {
                        title: "Ratings less than Comp",
                        field: "rating_less_than_comp",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const color = getRandomBadgeColor();
                            return `<span class="fs-5 badge bg-${color}">${value}</span>`;
                        }
                    },
                    {
                        title: "Rating < 3.5",
                        field: "rating_less_than_3_5",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const color = getRandomBadgeColor();
                            return `<span class="fs-5 badge bg-${color}">${value}</span>`;
                        }
                    },
                    {
                        title: "Rating < 4",
                        field: "rating_less_than_4",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const color = getRandomBadgeColor();
                            return `<span class="fs-5 badge bg-${color}">${value}</span>`;
                        }
                    },
                    {
                        title: "Rating < 4.5",
                        field: "rating_less_than_4_5",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const color = getRandomBadgeColor();
                            return `<span class="fs-5 badge bg-${color}">${value}</span>`;
                        }
                    },
                    {
                        title: "Negative L90",
                        field: "negation_l90",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const color = getRandomBadgeColor();
                            return `<span class="fs-5 badge bg-${color}">${value}</span>`;
                        }
                    },
                    {
                        title: "Neg Seller Feedback",
                        field: "neg_seller_feedback",
                        hozAlign: "center",
                        formatter: function(cell) {
                            const value = cell.getValue();
                            const color = getRandomBadgeColor();
                            return `<span class="fs-5 badge bg-${color}">${value}</span>`;
                        }
                    }
                ],
                ajaxResponse: function(url, params, response) {
                    const rows = response.data;
                    return rows;
                },
            });
            function getRandomBadgeColor() {
                const colors = ['primary','secondary','success','danger','warning','info','dark'];
                return colors[Math.floor(Math.random() * colors.length)];
            }
        });
    </script>
@endsection
