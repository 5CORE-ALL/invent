@extends('layouts.vertical', ['title' => 'Meta Ads Manager - Ads', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
    @include('layouts.shared/page-title', ['sub_title' => 'Marketing Masters', 'page_title' => 'Meta Ads Manager - Ads'])

    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('meta.ads.manager.ads') }}" id="filterForm">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Ad Set</label>
                                <select name="adset_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                                    <option value="">All Ad Sets</option>
                                    @foreach($adsets as $adset)
                                        <option value="{{ $adset->id }}" {{ $selectedAdsetId == $adset->id ? 'selected' : '' }}>
                                            {{ $adset->name }}
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
                    <h4 class="card-title mb-0">Ads</h4>
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
                        <table id="adsTable" class="table table-hover table-striped table-bordered dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Campaign</th>
                                    <th>Ad Set</th>
                                    <th>Status</th>
                                    <th>Spend</th>
                                    <th>Impressions</th>
                                    <th>Clicks</th>
                                    <th>CTR</th>
                                    <th>CPC</th>
                                    <th class="no-export">Preview</th>
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
                    setTimeout(initAdsTable, 300);
                    return;
                }
                loadScript(scripts[index], function() {
                    loadScriptsSequentially(index + 1);
                });
            }

            function initAdsTable() {
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

                if ($.fn.DataTable.isDataTable('#adsTable')) {
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

            var adsetId = '{{ $selectedAdsetId ?? "" }}';
            var url = '{{ route("meta.ads.manager.ads.data") }}';
            if (adsetId) {
                url += '?adset_id=' + adsetId;
            }

            var table = $('#adsTable').DataTable({
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
                        alert('Error loading ads data. Please refresh the page.');
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
                        data: 'adset_name', 
                        name: 'adset_name',
                        render: function(data) {
                            return data ? '<span class="badge bg-secondary">' + data + '</span>' : '-';
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
                        data: 'ctr', 
                        name: 'ctr',
                        render: function(data) {
                            const ctr = parseFloat(data || 0);
                            const color = ctr > 2 ? 'text-success' : ctr > 1 ? 'text-warning' : 'text-danger';
                            return '<span class="metric-value ' + color + '">' + ctr.toFixed(2) + '%</span>';
                        }
                    },
                    { 
                        data: 'cpc', 
                        name: 'cpc',
                        render: function(data) {
                            return '<span class="metric-value">$' + parseFloat(data || 0).toFixed(2) + '</span>';
                        }
                    },
                    { 
                        data: 'preview_link', 
                        name: 'preview',
                        orderable: false,
                        searchable: false,
                        className: 'action-buttons',
                        render: function(data) {
                            return data ? '<a href="' + data + '" target="_blank" class="btn btn-sm btn-info"><i class="mdi mdi-eye"></i> Preview</a>' : '-';
                        }
                    }
                ],
                order: [[5, 'desc']], // Sort by spend descending
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
                    processing: '<div class="spinner-border spinner-border-sm" role="status"></div> Loading ads...',
                    emptyTable: "No ads available",
                    zeroRecords: "No matching ads found"
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

