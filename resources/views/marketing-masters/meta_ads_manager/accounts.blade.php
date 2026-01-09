@extends('layouts.vertical', ['title' => 'Meta Ads Manager - Accounts', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <style>
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-paused { background-color: #fff3cd; color: #856404; }
        .status-disabled { background-color: #f8d7da; color: #721c24; }
        .status-pending { background-color: #d1ecf1; color: #0c5460; }
        .table th { background-color: #f8f9fa; font-weight: 600; }
        .metric-badge {
            display: inline-block;
            padding: 2px 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            font-weight: 600;
            color: #495057;
        }
        .action-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Marketing Masters', 'page_title' => 'Meta Ads Manager - Accounts'])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Ad Accounts</h4>
                    <div class="d-flex gap-2">
                        <button type="button" id="exportExcelBtn" class="btn btn-sm btn-success" style="display: none;">
                            <i class="mdi mdi-file-excel"></i> Excel
                        </button>
                        <button type="button" id="exportPrintBtn" class="btn btn-sm btn-info" style="display: none;">
                            <i class="mdi mdi-printer"></i> Print
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" onclick="location.reload()">
                            <i class="mdi mdi-refresh"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="accountsTable" class="table table-hover table-striped table-bordered dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Meta ID</th>
                                    <th>Status</th>
                                    <th>Currency</th>
                                    <th>Timezone</th>
                                    <th>Campaigns</th>
                                    <th>Last Synced</th>
                                    <th class="no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- DataTables will populate this -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        // Load DataTables scripts sequentially and initialize
        (function() {
            var scripts = [
                'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js',
                'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js',
                'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js',
                'https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js',
                'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
                'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js',
                'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js'
            ];

            function loadScript(src, callback) {
                var script = document.createElement('script');
                script.src = src;
                script.async = false; // Load synchronously
                script.onload = callback;
                script.onerror = function() {
                    console.error('Failed to load script: ' + src);
                    callback(); // Continue even if one fails
                };
                document.head.appendChild(script);
            }

            function loadScriptsSequentially(index) {
                if (index >= scripts.length) {
                    // All scripts loaded, wait a bit then initialize
                    setTimeout(initAccountsTable, 300);
                    return;
                }
                loadScript(scripts[index], function() {
                    loadScriptsSequentially(index + 1);
                });
            }

            function initAccountsTable() {
                // Verify jQuery is available
                if (typeof jQuery === 'undefined' || typeof window.jQuery === 'undefined') {
                    console.error('jQuery is not available');
                    alert('jQuery failed to load. Please refresh the page.');
                    return;
                }

                // Use window.jQuery to be safe
                var $ = window.jQuery || jQuery;

                // Verify DataTables is loaded
                if (typeof $.fn.DataTable === 'undefined') {
                    console.error('DataTables is not available');
                    alert('DataTables failed to load. Please refresh the page.');
                    return;
                }

                // Check if already initialized
                if ($.fn.DataTable.isDataTable('#accountsTable')) {
                    console.log('Table already initialized');
                    return;
                }

                // Initialize table
                try {
                    $(document).ready(function() {
                    function getStatusBadge(status) {
                        if (!status) return '<span class="status-badge status-pending">Unknown</span>';
                        status = status.toLowerCase();
                        if (status === 'active' || status === 'enabled') {
                            return '<span class="status-badge status-active">Active</span>';
                        } else if (status === 'paused') {
                            return '<span class="status-badge status-paused">Paused</span>';
                        } else if (status === 'disabled' || status === 'archived') {
                            return '<span class="status-badge status-disabled">Disabled</span>';
                        }
                        return '<span class="status-badge status-pending">' + status + '</span>';
                    }

                        var table = $('#accountsTable').DataTable({
                        processing: true,
                        serverSide: false,
                        responsive: true,
                        ajax: {
                            url: '{{ route("meta.ads.manager.accounts.data") }}',
                            type: 'GET',
                            dataSrc: function(json) {
                                return json.data || [];
                            },
                            error: function(xhr, error, thrown) {
                                console.error('DataTables AJAX error:', error);
                                alert('Error loading accounts data. Please refresh the page.');
                            }
                        },
                        columns: [
                            { data: 'id', name: 'id', width: '60px' },
                            { 
                                data: 'name', 
                                name: 'name',
                                render: function(data, type, row) {
                                    return '<strong>' + (data || 'N/A') + '</strong>';
                                }
                            },
                            { 
                                data: 'meta_id', 
                                name: 'meta_id',
                                render: function(data) {
                                    return '<code>' + (data || 'N/A') + '</code>';
                                }
                            },
                            { 
                                data: 'account_status', 
                                name: 'account_status',
                                render: function(data) {
                                    return getStatusBadge(data);
                                }
                            },
                            { 
                                data: 'currency', 
                                name: 'currency',
                                render: function(data) {
                                    return data ? '<span class="badge bg-secondary">' + data + '</span>' : '-';
                                }
                            },
                            { data: 'timezone', name: 'timezone' },
                            { 
                                data: 'campaigns_count', 
                                name: 'campaigns_count',
                                render: function(data) {
                                    return '<span class="metric-badge">' + (data || 0) + '</span>';
                                }
                            },
                            { 
                                data: 'synced_at', 
                                name: 'synced_at',
                                render: function(data) {
                                    if (!data) return '-';
                                    const date = new Date(data);
                                    return date.toLocaleString();
                                }
                            },
                            { 
                                data: 'id', 
                                name: 'actions',
                                orderable: false,
                                searchable: false,
                                className: 'action-buttons',
                                render: function(data, type, row) {
                                    return '<a href="/meta-ads-manager/campaigns?ad_account_id=' + row.id + '" class="btn btn-sm btn-primary"><i class="mdi mdi-eye"></i> View</a>';
                                }
                            }
                        ],
                        order: [[1, 'asc']],
                        pageLength: 25,
                        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                        dom: 'frtip',
                        buttons: [
                            {
                                extend: 'excel',
                                text: 'Excel',
                                exportOptions: {
                                    columns: ':not(.no-export)'
                                }
                            },
                            {
                                extend: 'print',
                                text: 'Print',
                                exportOptions: {
                                    columns: ':not(.no-export)'
                                }
                            }
                        ],
                        language: {
                            processing: '<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div> Loading accounts...',
                            emptyTable: "No accounts available",
                            zeroRecords: "No matching accounts found",
                            search: "Search:",
                            lengthMenu: "Show _MENU_ entries",
                            info: "Showing _START_ to _END_ of _TOTAL_ accounts",
                            infoEmpty: "No accounts available",
                            infoFiltered: "(filtered from _MAX_ total accounts)"
                        }
                    });

                    // Move export buttons to header
                    var excelBtn = table.button('.buttons-excel').node();
                    var printBtn = table.button('.buttons-print').node();
                    
                    if (excelBtn) {
                        $('#exportExcelBtn').on('click', function() {
                            $(excelBtn).click();
                        }).show();
                    }
                    if (printBtn) {
                        $('#exportPrintBtn').on('click', function() {
                            $(printBtn).click();
                        }).show();
                    }
                    });
                } catch(e) {
                    console.error('Error initializing table:', e);
                    alert('Error initializing table: ' + e.message);
                }
            }

            // Start loading scripts when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    loadScriptsSequentially(0);
                });
            } else {
                loadScriptsSequentially(0);
            }
        })();
    </script>
@endsection
