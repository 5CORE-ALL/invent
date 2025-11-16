@extends('layouts.vertical', ['title' => 'Amazon', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        /* General Styles */
        .w-25 {
            width: 10% !important;
            margin-left: 20px;
        }

        .tooltip-icon {
            position: absolute;
            right: 5px;
            bottom: 5px;
            cursor: pointer;
        }

        td {
            position: relative;
        }

        /* Custom Modal Positioning */
        .modal.right-to-left .modal-dialog {
            position: fixed;
            margin: 0;
            width: 90%;
            max-width: none;
            height: auto;
            right: 0;
            top: 0;
            transform: translateX(100%);
            transition: transform 0.3s ease-out;
        }

        .modal.right-to-left.show .modal-dialog {
            transform: translateX(0);
        }

        .modal.right-to-left .modal-content {
            height: 100%;
            border-radius: 0;
            border: none;
        }

        .modal.right-to-left .modal-header,
        .modal.right-to-left .modal-body,
        .modal.right-to-left .modal-footer {
            padding: 20px;
        }

        .modal.right-to-left .modal-header {
            border-bottom: 1px solid #dee2e6;
        }

        .modal.right-to-left .modal-footer {
            border-top: 1px solid #dee2e6;
        }

        /* Scoped Styles for Right-to-Left Modal */
        .modal.right-to-left .modal-content {
            border: 2px solid transparent;
            border-radius: 15px;
            background: linear-gradient(white, white), linear-gradient(135deg, #0d6efd, #0dcaf0);
            background-origin: border-box;
            background-clip: content-box, border-box;
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .modal.right-to-left .modal-header {
            border-bottom: 2px solid #0d6efd;
        }

        .modal.right-to-left .card {
            /* Base card styles with gradient */
            background: linear-gradient(135deg, rgba(13, 110, 253, 0.1), rgba(255, 255, 255, 0.9));
            border: 1px solid rgba(13, 110, 253, 0.2);
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        /* Status color overlays - will combine with the gradient */
        .modal.right-to-left .card.card-bg-red {
            background: linear-gradient(135deg, rgba(245, 0, 20, 0.69), rgba(255, 255, 255, 0.85));
            border-color: rgba(220, 53, 70, 0.72);
        }

        .modal.right-to-left .card.card-bg-green {
            background: linear-gradient(135deg, rgba(3, 255, 62, 0.424), rgba(255, 255, 255, 0.85));
            border-color: rgba(40, 167, 69, 0.3);
        }

        .modal.right-to-left .card.card-bg-yellow {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.15), rgba(255, 255, 255, 0.85));
            border-color: rgba(255, 193, 7, 0.3);
        }

        .modal.right-to-left .card.card-bg-blue {
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.15), rgba(255, 255, 255, 0.85));
            border-color: rgba(0, 123, 255, 0.3);
        }

        .modal.right-to-left .card.card-bg-pink {
            background: linear-gradient(135deg, rgba(232, 62, 140, 0.15), rgba(255, 255, 255, 0.85));
            border-color: rgba(232, 62, 141, 0.424);
        }

        .modal.right-to-left .card.card-bg-gray {
            background: linear-gradient(135deg, rgba(108, 117, 125, 0.15), rgba(255, 255, 255, 0.85));
            border-color: rgba(108, 117, 125, 0.3);
        }

        .modal.right-to-left .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .modal.right-to-left .modal-title {
            font-weight: bold;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .modal.right-to-left .modal-dialog {
            animation: slideInRight 0.3s ease-out;
        }

        .fa-pen {
            font-size: 16px;
            transition: color 0.3s ease;
        }

        .fa-pen:hover {
            color: #0d6efd;
        }

        /* Status indicators */
        .status-indicator {
            position: absolute;
            top: 5px;
            left: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .danger {
            background-color: #dc3545;
        }

        .warning {
            background-color: #ffc107;
        }

        .success {
            background-color: #28a745;
        }

        .info {
            background-color: #007bff;
        }

        .pink {
            background-color: #e83e8c;
        }

        /* Parent row highlight */
        .parent-row {
            background-color: rgba(69, 233, 255, 0.1) !important;
        }

        /* Loading overlay */
        #loader {
            display: none;
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            padding: 10px 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border-radius: 5px;
        }

        /* Add these styles to your existing CSS */
        .edit-icon,
        .save-icon {
            transition: all 0.3s ease;
        }

        .edit-icon:hover i {
            color: #0d6efd !important;
            transform: scale(1.1);
        }

        .save-icon:hover i {
            color: #28a745 !important;
            transform: scale(1.1);
        }

        .fa-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Notification styling */
        .alert-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 250px;
        }
    </style>
    <style>
        /* Add this to your existing CSS section */
        .dropdown-menu {
            display: none;
            position: absolute;
            z-index: 1000;
            background-color: white;
            border: 1px solid rgba(0, 0, 0, .15);
            border-radius: 0.25rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, .175);
            min-width: 10rem;
            padding: 0.5rem 0;
            margin: 0.125rem 0 0;
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.25rem 1.5rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
        }

        .dropdown-item:hover {
            color: #16181b;
            text-decoration: none;
            background-color: #f8f9fa;
        }

        .status-circle {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
        }

        .default {
            background-color: grey;
        }

        .red {
            background-color: red;
        }

        .yellow {
            background-color: yellow;
        }

        .blue {
            background-color: blue;
        }

        .green {
            background-color: green;
        }

        .pink {
            background-color: pink;
        }
    </style>
    <style>
        /* Dil% cell styling */
        .dil-percent-cell {
            padding: 8px 4px !important;
        }

        .dil-percent-value {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        .dil-percent-value.red {
            background-color: #dc3545;
            color: white;
        }

        .dil-percent-value.blue {
            background-color: #3591dc;
            color: white;
        }

        .dil-percent-value.yellow {
            background-color: #ffc107;
            color: #212529;
        }

        .dil-percent-value.green {
            background-color: #28a745;
            color: white;
        }

        .dil-percent-value.pink {
            background-color: #e83e8c;
            color: white;
        }

        .dil-percent-value.gray {
            background-color: #6c757d;
            color: white;
        }
    </style>
    <style>
        /* Fixed Header Styling */
        .dtfh-floatingparenthead {
            left: 286.875px !important;
            width: calc(100% - 286.875px) !important;
            top: 0 !important;
            position: fixed !important;
            z-index: 1000 !important;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            height: auto !important;
        }

        table.fixedHeader-floating {
            background-color: white;
            width: 100% !important;
            left: 0 !important;
            table-layout: fixed !important;
            margin-top: 0 !important;
        }

        table.fixedHeader-floating thead th {
            background-color: white !important;
            position: relative;
            vertical-align: middle;
            white-space: nowrap;
        }

        table.fixedHeader-floating.no-footer {
            border-bottom-width: 0;
        }

        table.fixedHeader-locked {
            position: absolute !important;
            background-color: white;
        }

        /* Adjust table body to account for fixed header */
        .dataTables_scrollBody {
            padding-top: 55px !important;
            overflow: visible !important;
        }

        /* Ensure proper column width synchronization */
        .fixedHeader-floating th {
            box-sizing: border-box;
        }

        /* Print styles */
        @media print {
            table.fixedHeader-floating {
                display: none;
            }

            .dtfh-floatingparenthead {
                display: none;
            }
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .dtfh-floatingparenthead {
                left: 250px !important;
                width: calc(100% - 250px) !important;
            }
        }

        @media (max-width: 992px) {
            .dtfh-floatingparenthead {
                left: 200px !important;
                width: calc(100% - 200px) !important;
            }
        }

        @media (max-width: 768px) {
            .dtfh-floatingparenthead {
                left: 0 !important;
                width: 100% !important;
            }

            .dataTables_scrollBody {
                padding-top: 100px !important;
            }
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => 'Amazon', 'sub_title' => 'Amazon Analysis'])

    <div class="row">
        <div class="col-12">
            <div class="card">

                <div class="card-header d-flex justify-content-between align-items-center">
                    <p class="text-muted mb-0"></p>

                    <a href="https://5coremanagement.com/project/task-board-list" class="btn btn-primary btn-sm"
                        title="Go to Task Board" target="_blank">
                        <i class="fas fa-tasks"></i> Add Task
                    </a>
                </div>

                <div class="card-body">
                    <table id="row-callback-datatable" class="table dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th style="vertical-align: middle; white-space: nowrap;">
                                    Channel
                                </th>
                                <th style="vertical-align: middle; white-space: nowrap;">
                                    Executive
                                </th>
                                <th style="vertical-align: middle; white-space: nowrap;">Link</th>
                                <th style="vertical-align: middle; white-space: nowrap;">Req Lising</th>
                                <th style="vertical-align: middle; white-space: nowrap;">NR List</th>
                                <th style="vertical-align: middle; white-space: nowrap;">SHEET</th>
                                <th style="vertical-align: middle; white-space: nowrap;">SH Req</th>
                                <th style="vertical-align: middle; white-space: nowrap;">Site</th>
                                <th style="vertical-align: middle; white-space: nowrap; padding-right: 4px;">S Req</th>
                                <th style="vertical-align: middle; white-space: nowrap;">Zero Inv</th>
                                <th style="vertical-align: middle; white-space: nowrap;">Act Req</th>
                                <th style="vertical-align: middle; white-space: nowrap;">Active</th>
                                <th style="vertical-align: middle; white-space: nowrap;">Exc+/-</th>
                                <th style="vertical-align: middle; white-space: nowrap;">Surpd</th>
                            </tr>
                        </thead>
                        <tbody id="table-body">
                            <!-- Rows will be dynamically inserted here -->
                        </tbody>
                    </table>
                    </table>
                    <!-- Loader element -->
                    <div id="loader"></div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script>
        document.body.style.zoom = "72%";

        (function() {
            var scriptsLoaded = {
                jquery: false,
                datatables: false,
                bootstrap: false,
                fontawesome: false,
                popper: false
            };

            function checkAllScriptsLoaded() {
                if (scriptsLoaded.jquery && scriptsLoaded.datatables &&
                    scriptsLoaded.bootstrap && scriptsLoaded.fontawesome && scriptsLoaded.popper && scriptsLoaded
                    .fixedHeader) {
                    initializeChannelMasterTable();
                }
            }



            function formatDecimal(value, decimals) {
                return (value === null || value === undefined) ? '0.00' : parseFloat(value).toFixed(decimals);
            }

            if (typeof jQuery == 'undefined') {
                var jqueryScript = document.createElement('script');
                jqueryScript.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
                jqueryScript.onload = function() {
                    scriptsLoaded.jquery = true;
                    checkAllScriptsLoaded();
                };
                document.head.appendChild(jqueryScript);
            } else {
                scriptsLoaded.jquery = true;
                checkAllScriptsLoaded();
            }

            var popperScript = document.createElement('script');
            popperScript.src = 'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js';
            popperScript.onload = function() {
                scriptsLoaded.popper = true;
                checkAllScriptsLoaded();
            };
            document.head.appendChild(popperScript);

            var bsScript = document.createElement('script');
            bsScript.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';
            bsScript.onload = function() {
                scriptsLoaded.bootstrap = true;
                checkAllScriptsLoaded();
            };
            document.head.appendChild(bsScript);

            var dtScript = document.createElement('script');
            dtScript.src = 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js';
            dtScript.onload = function() {
                scriptsLoaded.datatables = true;
                checkAllScriptsLoaded();
            };
            document.head.appendChild(dtScript);

            var faScript = document.createElement('script');
            faScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/js/all.min.js';
            faScript.onload = function() {
                scriptsLoaded.fontawesome = true;
                checkAllScriptsLoaded();
            };
            document.head.appendChild(faScript);

            var dtFixedHeaderScript = document.createElement('script');
            dtFixedHeaderScript.src = 'https://cdn.datatables.net/fixedheader/3.3.2/js/dataTables.fixedHeader.min.js';
            dtFixedHeaderScript.onload = function() {
                // No need to flag this separately unless you're gating it
                scriptsLoaded.fixedHeader = true;
                checkAllScriptsLoaded();
            };
            document.head.appendChild(dtFixedHeaderScript);

            function initializeChannelMasterTable() {
                const $ = jQuery.noConflict();

                // Configuration constants
                const FIXED_HEADER_LEFT = '286.875px';
                const FIXED_HEADER_WIDTH = `calc(100% - ${FIXED_HEADER_LEFT})`;

                // State management
                const state = {
                    table: null,
                    resizeTimer: null,
                    isTableInitialized: false,
                };
                // DOM Elements cache
                const elements = {
                    table: $('#row-callback-datatable'),
                    loader: $('#loader'),
                    dropdowns: $('.dropdown-toggle'),
                    dropdownMenus: $('.dropdown-menu')
                };

                // Core Functions
                const adjustFixedHeader = () => {
                    $('.dtfh-floatingparenthead').css({
                        'left': FIXED_HEADER_LEFT,
                        'width': FIXED_HEADER_WIDTH,
                        'top': '0'
                    });

                    elements.table.find('thead th').each((index, th) => {
                        const width = $(th).width();
                        $('.fixedHeader-floating thead th').eq(index).width(width);
                    });
                };

                // Initialize DataTable
                const initializeTable = () => {
                    state.table = elements.table.DataTable({
                        serverSide: false,
                        ajax: {
                            url: '/listing-master-data',
                            type: 'GET',
                            dataSrc: 'data',
                            error: (xhr, error, thrown) => {
                                console.error('Data loading error:', error);
                                elements.loader.html(
                                    '<div class="text-danger">Error loading data</div>');
                            }
                        },
                        fixedHeader: {
                            header: true,
                            headerOffset: 0
                        },
                        responsive: true,
                        pageLength: 25,
                        lengthMenu: [10, 25, 50, 100],
                        order: [],
                        drawCallback: function() {
                            adjustFixedHeader();
                        },
                        columns: [
                            // Your column definitions here
                            {
                                data: 'Channel ',
                                name: 'Channel',
                                render: function(data, type, row) {
                                    return data || ''; // Return data or empty string if undefined
                                }
                            },
                            {
                                data: 'Executive',
                                name: 'Executive',
                                render: function(data, type, row) {
                                    return data || ''; // Return data or empty string if undefined
                                }
                            },
                            {
                                data: 'Link',
                                name: 'Link',
                                render: function(data, type, row) {
                                    return data || ''; // Return data or empty string if undefined
                                }
                            },
                            {
                                data: 'Req Listing',
                                name: 'Req Lising',
                                render: function(data, type, row) {
                                    return data || ''; // Return data or empty string if undefined
                                }
                            },
                            {
                                data: 'NR List',
                                name: 'NR List',
                                render: function(data, type, row) {
                                    return data || ''; // Return data or empty string if undefined
                                }
                            },
                            {
                                data: 'SHEET',
                                name: 'SHEET',
                                render: function(data, type, row) {
                                    return data || ''; // Return data or empty string if undefined
                                }
                            },
                            {
                                data: 'SH Req',
                                name: 'SH Req',
                                render: function(data, type, row) {
                                    return data || ''; // Return data or empty string if undefined
                                }
                            },
                            {
                                data: 'SITE',
                                name: 'Site',
                                render: function(data, type, row) {
                                    return data || ''; // Return data or empty string if undefined
                                }
                            },
                            {
                                data: 'S Req',
                                name: 'S Req',
                                render: function(data, type, row) {
                                    return data || ''; // Return data or empty string if undefined
                                }
                            },
                            {
                                data: 'Zero Inv',
                                name: 'Zero Inv',
                                render: function(data, type, row) {
                                    return data || ''; // Return data or empty string if undefined
                                }
                            },
                            {
                                data: 'Act Req',
                                name: 'Act Req',
                                render: function(data, type, row) {
                                    return data || ''; // Return data or empty string if undefined
                                }
                            },
                            {
                                data: 'Active',
                                name: 'Active',
                                render: function(data, type, row) {
                                    return data || ''; // Return data or empty string if undefined
                                }
                            },
                            {
                                data: '',
                                name: 'Exc+/-',
                                render: function(data, type, row) {
                                    return data || ''; // Return data or empty string if undefined
                                }
                            },
                            {
                                data: 'Surpd',
                                name: 'Surpd',
                                render: function(data, type, row) {
                                    return data || ''; // Return data or empty string if undefined
                                }
                            },
                        ]
                    });
                };

                // Main initialization
                const init = () => {
                    initializeTable();
                };

                // Start the application
                init();
            }
        })();
    </script>
@endsection
