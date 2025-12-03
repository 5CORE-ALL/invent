@extends('layouts.vertical', ['title' => 'Amazon ACOS Control', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .tabulator .tabulator-header {
            background: linear-gradient(90deg, #e0e7ff 0%, #e0e7ff 100%);
            border-bottom: 1px solid #2563eb;
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
        'page_title' => 'Amazon ACOS Control',
        'sub_title' => 'Amazon ACOS Control',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">

                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                        <h4 class="mb-0 fw-bold text-primary">Amazon ACOS Control</h4>
                    </div>
                    <div id="amazon-acos-table"></div>
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
            // document.documentElement.setAttribute("data-sidenav-size", "condensed");

            const table = new Tabulator("#amazon-acos-table", {
                ajaxURL: "/amazon-acos-data",
                layout: "fitData",
                pagination: "local",
                paginationSize: 50,
                height: "700px",
                columns: [{
                        title: "#",
                        formatter: "rownum",
                        hozAlign: "center",
                    },
                    {
                        title: "PARENT",
                    },
                    {
                        title: "CAMPAIGN",
                        field: "campaignName"
                    },
                    {
                        title: "AD TYPE",
                        field: "ad_type"
                    },
                    {
                        title: "STATUS",
                        field: "campaignStatus"
                    },
                    {
                        title: "BGT",
                        field: "campaignBudgetAmount",
                        hozAlign: "right",
                        formatter: function(cell) {
                            let value = parseFloat(cell.getValue()) || 0;
                            return value.toFixed(0);
                        }
                    },
                    {
                        title: "7 UB%",
                        field: "l7_spend",
                        hozAlign: "right"
                    },
                    {
                        title: "1 UB%",
                        field: "l1_spend",
                        hozAlign: "right"
                    },
                    {
                        title: "L60 Clicks",
                        field: "l60_clicks",
                        hozAlign: "right"
                    },
                    {
                        title: "L30 Clicks",
                        field: "l30_clicks",
                        hozAlign: "right"
                    },
                    {
                        title: "L15 Clicks",
                        field: "l15_clicks",
                        hozAlign: "right"
                    },
                    {
                        title: "L7 Clicks",
                        field: "l7_clicks",
                        hozAlign: "right"
                    },
                    {
                        title: "L60 SPEND",
                        field: "l60_spend",
                        hozAlign: "right"
                    },
                    {
                        title: "L30 SPEND",
                        field: "l30_spend",
                        hozAlign: "right"
                    },
                    {
                        title: "L15 SPEND",
                        field: "l15_spend",
                        hozAlign: "right"
                    },
                    {
                        title: "L7 SPEND",
                        field: "l7_spend",
                        hozAlign: "right"
                    },
                    {
                        title: "L1 SPEND",
                        field: "l1_spend",
                        hozAlign: "right"
                    },
                    {
                        title: "L60 SALES",
                        field: "l60_sales",
                        hozAlign: "right"
                    },
                    {
                        title: "L30 SALES",
                        field: "l30_sales",
                        hozAlign: "right"
                    },
                    {
                        title: "L15 SALES",
                        field: "l15_sales",
                        hozAlign: "right"
                    },
                    {
                        title: "L7 SALES",
                        field: "l7_sales",
                        hozAlign: "right"
                    },
                    {
                        title: "L60 CPC",
                        field: "l60_cpc",
                        hozAlign: "right"
                    },
                    {
                        title: "L30 CPC",
                        field: "l30_cpc",
                        hozAlign: "right"
                    },
                    {
                        title: "L7 CPC",
                        field: "l7_cpc",
                        hozAlign: "right"
                    },
                    {
                        title: "L1 CPC",
                        field: "l1_cpc",
                        hozAlign: "right"
                    },
                    {
                        title: "L60 ACOS",
                        field: "l60_acos",
                        hozAlign: "right"
                    },
                    {
                        title: "L30 ACOS",
                        field: "l30_acos",
                        hozAlign: "right"
                    },
                    {
                        title: "L15 ACOS",
                        field: "l15_acos",
                        hozAlign: "right"
                    },
                    {
                        title: "L7 ACOS",
                        field: "l7_acos",
                        hozAlign: "right"
                    },
                    {
                        title: "L60 CVR",
                        field: "l60_cvr",
                        hozAlign: "right"
                    },
                    {
                        title: "L30 CVR",
                        field: "l30_cvr",
                        hozAlign: "right"
                    },
                    {
                        title: "L15 CVR",
                        field: "l15_cvr",
                        hozAlign: "right"
                    },
                    {
                        title: "L7 CVR",
                        field: "l7_cvr",
                        hozAlign: "right"
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
                        title: "yes sbid",
                        field: "yes_sbid",
                        editor: "input"
                    }
                ],
            });

            document.body.style.zoom = "80%";
        });
    </script>
@endsection
