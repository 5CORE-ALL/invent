@extends('layouts.vertical', ['title' => 'Meta Ads Manager - Ad Sets', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
        .metric-value {
            font-weight: 600;
            color: #495057;
        }
        .table th { background-color: #f8f9fa; font-weight: 600; }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Marketing Masters', 'page_title' => 'Meta Ads Manager - Ad Sets'])

    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('meta.ads.manager.adsets') }}" id="filterForm">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Campaign</label>
                                <select name="campaign_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                                    <option value="">All Campaigns</option>
                                    @foreach($campaigns as $campaign)
                                        <option value="{{ $campaign->id }}" {{ $selectedCampaignId == $campaign->id ? 'selected' : '' }}>
                                            {{ $campaign->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary w-100" onclick="location.reload()">
                                    <i class="mdi mdi-refresh"></i> Refresh
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Ad Sets</h4>
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
                        <table id="adsetsTable" class="table table-hover table-striped table-bordered dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Campaign</th>
                                    <th>Status</th>
                                    <th>Daily Budget</th>
                                    <th>Ads</th>
                                    <th>Spend</th>
                                    <th>Impressions</th>
                                    <th>Clicks</th>
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
                script.async = false;
                script.onload = callback;
                script.onerror = function() {
                    console.error('Failed to load script: ' + src);
                    callback();
                };
                document.head.appendChild(script);
            }

            function loadScriptsSequentially(index) {
                if (index >= scripts.length) {
                    setTimeout(initAdsetsTable, 300);
                    return;
                }
                loadScript(scripts[index], function() {
                    loadScriptsSequentially(index + 1);
                });
            }

            function initAdsetsTable() {
                if (typeof jQuery === 'undefined' || typeof window.jQuery === 'undefined') {
                    console.error('jQuery is not available');
                    alert('jQuery failed to load. Please refresh the page.');
                    return;
                }

                var $ = window.jQuery || jQuery;

                if (typeof $.fn.DataTable === 'undefined') {
                    console.error('DataTables is not available');
                    alert('DataTables failed to load. Please refresh the page.');
                    return;
                }

                if ($.fn.DataTable.isDataTable('#adsetsTable')) {
                    return;
                }

                try {
                    $(document).ready(function() {
            function getStatusBadge(status) {
                if (!status) return '<span class="status-badge status-pending">Unknown</span>';
                status = status.toLowerCase();
                if (status === 'active') {
                    return '<span class="status-badge status-active">Active</span>';
                } else if (status === 'paused') {
                    return '<span class="status-badge status-paused">Paused</span>';
                } else if (status === 'archived' || status === 'deleted') {
                    return '<span class="status-badge status-disabled">Archived</span>';
                }
                return '<span class="status-badge status-pending">' + status + '</span>';
            }

            var campaignId = '{{ $selectedCampaignId ?? "" }}';
            var url = '{{ route("meta.ads.manager.adsets.data") }}';
            if (campaignId) {
                url += '?campaign_id=' + campaignId;
            }

            var table = $('#adsetsTable').DataTable({
                processing: true,
                serverSide: false,
                responsive: true,
                ajax: {
                    url: url,
                    dataSrc: function(json) {
                        return json.data || [];
                    },
                    error: function(xhr, error, thrown) {
                        console.error('DataTables AJAX error:', error);
                        alert('Error loading ad sets data. Please refresh the page.');
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
                        data: 'campaign_name', 
                        name: 'campaign_name',
                        render: function(data) {
                            return data ? '<span class="badge bg-info">' + data + '</span>' : '-';
                        }
                    },
                    { 
                        data: 'status', 
                        name: 'status',
                        render: function(data) {
                            return getStatusBadge(data);
                        }
                    },
                    { 
                        data: 'daily_budget', 
                        name: 'daily_budget',
                        render: function(data) {
                            return data ? '<span class="metric-value">$' + parseFloat(data).toFixed(2) + '</span>' : '-';
                        }
                    },
                    { 
                        data: 'ads_count', 
                        name: 'ads_count',
                        render: function(data) {
                            return '<span class="badge bg-secondary">' + (data || 0) + '</span>';
                        }
                    },
                    { 
                        data: 'spend', 
                        name: 'spend',
                        render: function(data) {
                            return '<span class="metric-value text-danger">$' + parseFloat(data || 0).toFixed(2) + '</span>';
                        }
                    },
                    { 
                        data: 'impressions', 
                        name: 'impressions',
                        render: function(data) {
                            return '<span class="metric-value">' + parseInt(data || 0).toLocaleString() + '</span>';
                        }
                    },
                    { 
                        data: 'clicks', 
                        name: 'clicks',
                        render: function(data) {
                            return '<span class="metric-value">' + parseInt(data || 0).toLocaleString() + '</span>';
                        }
                    },
                    { 
                        data: 'meta_id', 
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'action-buttons',
                        render: function(data, type, row) {
                            return '<a href="/meta-ads-manager/ads?adset_id=' + row.id + '" class="btn btn-sm btn-primary"><i class="mdi mdi-eye"></i> View</a>';
                        }
                    }
                ],
                order: [[6, 'desc']], // Sort by spend descending
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
                    processing: '<div class="spinner-border spinner-border-sm" role="status"></div> Loading ad sets...',
                    emptyTable: "No ad sets available",
                    zeroRecords: "No matching ad sets found"
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

