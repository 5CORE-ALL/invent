@extends('layouts.vertical', ['title' => 'Failed Campaigns', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <style>
        .failed-campaigns .table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            font-size: 0.75rem;
            white-space: nowrap;
        }
        .failed-campaigns .badge {
            font-size: 0.7rem;
            font-weight: 600;
        }
        .failed-campaigns .filters-toolbar {
            display: flex;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 0.35rem 0.5rem;
        }
        .failed-campaigns .filter-field {
            flex: 0 0 auto;
            min-width: 0;
        }
        .failed-campaigns .stats-card {
            padding: 0.75rem;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
        }
        .failed-campaigns .stats-number {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .failed-campaigns .stats-label {
            font-size: 0.75rem;
            color: #6c757d;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Amazon Ads', 'page_title' => 'Failed Campaigns Tracker'])

    <div class="row failed-campaigns">
        <div class="col-12">
            <!-- Statistics Cards -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="card stats-card border">
                        <div class="stats-number text-secondary" id="totalAttempts">0</div>
                        <div class="stats-label">TOTAL ATTEMPTS</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card border-success">
                        <div class="stats-number text-success" id="successfulPushes">0</div>
                        <div class="stats-label">SUCCESSFUL</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card border-warning">
                        <div class="stats-number text-warning" id="skippedPushes">0</div>
                        <div class="stats-label">SKIPPED</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card border-danger">
                        <div class="stats-number text-danger" id="failedPushes">0</div>
                        <div class="stats-label">FAILED</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-3">
                <div class="card-body p-2">
                    <div class="filters-toolbar">
                        <div class="filter-field">
                            <label class="form-label mb-1" style="font-size: 0.7rem;">Push Type</label>
                            <select class="form-select form-select-sm" id="filterPushType">
                                <option value="">All Types</option>
                                <option value="sp_sbid">SP SBID</option>
                                <option value="sb_sbid">SB SBID</option>
                                <option value="sp_sbgt">SP SBGT</option>
                                <option value="sb_sbgt">SB SBGT</option>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label class="form-label mb-1" style="font-size: 0.7rem;">Status</label>
                            <select class="form-select form-select-sm" id="filterStatus">
                                <option value="">All</option>
                                <option value="failed" selected>Failed & Skipped</option>
                                <option value="skipped">Skipped</option>
                                <option value="failed">Failed</option>
                                <option value="success">Success</option>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label class="form-label mb-1" style="font-size: 0.7rem;">Source</label>
                            <select class="form-select form-select-sm" id="filterSource">
                                <option value="">All Sources</option>
                                <option value="web">Web</option>
                                <option value="command">Command</option>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label class="form-label mb-1" style="font-size: 0.7rem;">Date From</label>
                            <input type="date" class="form-control form-control-sm" id="filterDateFrom" 
                                   value="{{ date('Y-m-d', strtotime('-7 days')) }}">
                        </div>
                        <div class="filter-field">
                            <label class="form-label mb-1" style="font-size: 0.7rem;">Date To</label>
                            <input type="date" class="form-control form-control-sm" id="filterDateTo" 
                                   value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="filter-field align-self-end">
                            <button type="button" class="btn btn-sm btn-primary" id="applyFilters">
                                <i class="mdi mdi-filter"></i> Apply
                            </button>
                        </div>
                        <div class="filter-field align-self-end">
                            <button type="button" class="btn btn-sm btn-success" id="exportCsv">
                                <i class="mdi mdi-download"></i> Export CSV
                            </button>
                        </div>
                        <div class="filter-field align-self-end">
                            <button type="button" class="btn btn-sm btn-secondary" id="refreshData">
                                <i class="mdi mdi-refresh"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-hover mb-0" id="failedCampaignsTable">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Type</th>
                                    <th>Campaign ID</th>
                                    <th>Campaign Name</th>
                                    <th>Value</th>
                                    <th>Status</th>
                                    <th>Reason</th>
                                    <th>Source</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via DataTables Ajax -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Push Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    <!-- Details loaded dynamically -->
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            let table;

            // Initialize DataTable
            function initTable() {
                if (table) {
                    table.destroy();
                }

                table = $('#failedCampaignsTable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: '{{ route("amazon-ads.push-logs.data") }}',
                        data: function(d) {
                            d.push_type = $('#filterPushType').val();
                            d.status = $('#filterStatus').val();
                            d.source = $('#filterSource').val();
                            d.date_from = $('#filterDateFrom').val();
                            d.date_to = $('#filterDateTo').val();
                        }
                    },
                    columns: [
                        { data: 'created_at', name: 'created_at', orderable: true },
                        { data: 'push_type_name', name: 'push_type', orderable: false },
                        { data: 'campaign_id', name: 'campaign_id', orderable: false },
                        { data: 'campaign_name', name: 'campaign_name', orderable: false },
                        { data: 'value', name: 'value', orderable: false },
                        { data: 'status_badge', name: 'status', orderable: false },
                        { data: 'reason', name: 'reason', orderable: false },
                        { data: 'source', name: 'source', orderable: false },
                        { data: 'actions', name: 'actions', orderable: false, searchable: false }
                    ],
                    order: [[0, 'desc']],
                    pageLength: 25,
                    lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
                    language: {
                        emptyTable: "No failed campaigns found"
                    }
                });
            }

            // Load statistics
            function loadStats() {
                $.ajax({
                    url: '{{ route("amazon-ads.push-logs.stats") }}',
                    data: {
                        date_from: $('#filterDateFrom').val(),
                        date_to: $('#filterDateTo').val()
                    },
                    success: function(data) {
                        $('#totalAttempts').text(data.overall.total || 0);
                        $('#successfulPushes').text(data.overall.successful || 0);
                        $('#skippedPushes').text(data.overall.skipped || 0);
                        $('#failedPushes').text(data.overall.failed || 0);
                    }
                });
            }

            // Event handlers
            $('#applyFilters').click(function() {
                loadStats();
                table.ajax.reload();
            });

            $('#refreshData').click(function() {
                loadStats();
                table.ajax.reload();
            });

            $('#exportCsv').click(function() {
                let params = new URLSearchParams({
                    push_type: $('#filterPushType').val(),
                    status: $('#filterStatus').val(),
                    source: $('#filterSource').val(),
                    date_from: $('#filterDateFrom').val(),
                    date_to: $('#filterDateTo').val()
                });
                window.location.href = '{{ route("amazon-ads.push-logs.export") }}?' + params.toString();
            });

            // View details
            $(document).on('click', '.view-details', function() {
                let data = $(this).data('log');
                let html = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Push Type:</strong> ${data.push_type_name}</p>
                            <p><strong>Campaign ID:</strong> ${data.campaign_id || 'N/A'}</p>
                            <p><strong>Campaign Name:</strong> ${data.campaign_name || 'N/A'}</p>
                            <p><strong>Value:</strong> ${data.value || 'N/A'}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> ${data.status_badge}</p>
                            <p><strong>Source:</strong> ${data.source}</p>
                            <p><strong>HTTP Status:</strong> ${data.http_status || 'N/A'}</p>
                            <p><strong>Created:</strong> ${data.created_at}</p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <p><strong>Reason:</strong></p>
                            <p>${data.reason || 'N/A'}</p>
                        </div>
                    </div>
                `;
                
                if (data.request_data) {
                    html += `
                        <div class="row mt-3">
                            <div class="col-12">
                                <p><strong>Request Data:</strong></p>
                                <pre class="bg-light p-2">${JSON.stringify(JSON.parse(data.request_data), null, 2)}</pre>
                            </div>
                        </div>
                    `;
                }
                
                if (data.response_data) {
                    html += `
                        <div class="row mt-3">
                            <div class="col-12">
                                <p><strong>Response Data:</strong></p>
                                <pre class="bg-light p-2">${JSON.stringify(JSON.parse(data.response_data), null, 2)}</pre>
                            </div>
                        </div>
                    `;
                }
                
                $('#detailsContent').html(html);
                $('#detailsModal').modal('show');
            });

            // Initialize
            initTable();
            loadStats();
        });
    </script>
@endsection
