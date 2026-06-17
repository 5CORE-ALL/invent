<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Amazon Ads - Failed Campaigns Tracker</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- Tabulator CSS -->
    <link href="https://unpkg.com/tabulator-tables@5.5.0/dist/css/tabulator_bootstrap4.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            margin: 10px 0;
        }
        .stat-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stat-rate {
            font-size: 16px;
            margin-top: 5px;
        }
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .table-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .badge-large {
            font-size: 13px;
            padding: 6px 12px;
        }
        .reason-text {
            color: #dc3545;
            font-weight: 500;
        }
        .tabulator {
            font-size: 14px;
        }
        .tabulator .tabulator-header {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .loading-overlay.active {
            display: flex;
        }
        .spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .top-reasons {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .reason-item {
            padding: 12px;
            border-left: 4px solid #dc3545;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container-fluid">
            <h1><i class="fas fa-exclamation-triangle"></i> Amazon Ads - Failed Campaigns Tracker</h1>
            <p class="mb-0">Monitor and analyze campaigns that failed to update</p>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card bg-white">
                    <div class="stat-label">
                        <i class="fas fa-list"></i> Total Attempts
                    </div>
                    <div class="stat-value text-primary" id="stat-total">0</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card bg-white border-left-success">
                    <div class="stat-label text-success">
                        <i class="fas fa-check-circle"></i> Successful
                    </div>
                    <div class="stat-value text-success" id="stat-success">0</div>
                    <div class="stat-rate text-muted" id="stat-success-rate">0%</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card bg-white border-left-warning">
                    <div class="stat-label text-warning">
                        <i class="fas fa-exclamation-circle"></i> Skipped
                    </div>
                    <div class="stat-value text-warning" id="stat-skipped">0</div>
                    <div class="stat-rate text-muted" id="stat-skip-rate">0%</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card bg-white border-left-danger">
                    <div class="stat-label text-danger">
                        <i class="fas fa-times-circle"></i> Failed
                    </div>
                    <div class="stat-value text-danger" id="stat-failed">0</div>
                    <div class="stat-rate text-muted" id="stat-fail-rate">0%</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <h5 class="mb-3"><i class="fas fa-filter"></i> Filters</h5>
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Push Type</label>
                        <select id="filter-push-type" class="form-control">
                            <option value="">All Types</option>
                            <option value="sp_sbid">SP Bid</option>
                            <option value="sb_sbid">SB Bid</option>
                            <option value="sp_sbgt">SP Budget</option>
                            <option value="sb_sbgt">SB Budget</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Status</label>
                        <select id="filter-status" class="form-control">
                            <option value="">Failed & Skipped</option>
                            <option value="failed">Failed Only</option>
                            <option value="skipped">Skipped Only</option>
                            <option value="success">Success Only</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Source</label>
                        <select id="filter-source" class="form-control">
                            <option value="">All Sources</option>
                            <option value="web">Web</option>
                            <option value="command">Command</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Date From</label>
                        <input type="date" id="filter-date-from" class="form-control">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>Date To</label>
                        <input type="date" id="filter-date-to" class="form-control">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button id="btn-apply-filters" class="btn btn-primary btn-block">
                            <i class="fas fa-search"></i> Apply
                        </button>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <input type="text" id="filter-campaign-id" class="form-control" placeholder="🔍 Search Campaign ID">
                </div>
                <div class="col-md-3">
                    <input type="text" id="filter-campaign-name" class="form-control" placeholder="🔍 Search Campaign Name">
                </div>
                <div class="col-md-6 text-right">
                    <button id="btn-export" class="btn btn-success">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                    <button id="btn-refresh" class="btn btn-info ml-2">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button id="btn-clear" class="btn btn-secondary ml-2">
                        <i class="fas fa-eraser"></i> Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="table-card">
            <h5 class="mb-3"><i class="fas fa-table"></i> Campaign Push Logs</h5>
            <div id="tabulator-table"></div>
        </div>

        <!-- Top Failure Reasons -->
        <div class="top-reasons">
            <h5 class="mb-3"><i class="fas fa-chart-bar"></i> Top Failure Reasons</h5>
            <div id="top-reasons-container">
                <p class="text-muted">Loading...</p>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-info-circle"></i> Campaign Details</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="modal-body-content">
                    <!-- Content loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Tabulator -->
    <script src="https://unpkg.com/tabulator-tables@5.5.0/dist/js/tabulator.min.js"></script>
    
    <script>
        let table;
        
        // Set default dates
        document.addEventListener('DOMContentLoaded', function() {
            // Set date defaults
            const today = new Date().toISOString().split('T')[0];
            const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
            document.getElementById('filter-date-from').value = weekAgo;
            document.getElementById('filter-date-to').value = today;
            
            // Initialize
            initTable();
            loadStats();
            setupEventListeners();
        });

        function showLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        function initTable() {
            table = new Tabulator("#tabulator-table", {
                ajaxURL: "/amazon-ads/push-logs/data",
                ajaxParams: getFilters,
                ajaxResponse: function(url, params, response) {
                    return {
                        data: response.data,
                        last_page: response.last_page,
                    };
                },
                pagination: true,
                paginationMode: "remote",
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200],
                layout: "fitDataStretch",
                placeholder: "No failed campaigns found",
                height: "500px",
                columns: [
                    {
                        title: "Date/Time",
                        field: "created_at",
                        width: 160,
                        formatter: function(cell) {
                            const date = new Date(cell.getValue());
                            return date.toLocaleString('en-US', {
                                month: 'short',
                                day: 'numeric',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                        }
                    },
                    {
                        title: "Type",
                        field: "push_type",
                        width: 130,
                        formatter: function(cell) {
                            const type = cell.getValue();
                            const badges = {
                                'sp_sbid': '<span class="badge badge-primary badge-large">SP Bid</span>',
                                'sb_sbid': '<span class="badge badge-info badge-large">SB Bid</span>',
                                'sp_sbgt': '<span class="badge badge-success badge-large">SP Budget</span>',
                                'sb_sbgt': '<span class="badge badge-warning badge-large">SB Budget</span>',
                            };
                            return badges[type] || type;
                        }
                    },
                    {
                        title: "Campaign ID",
                        field: "campaign_id",
                        width: 150,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? `<code>${value}</code>` : '<span class="text-muted">N/A</span>';
                        }
                    },
                    {
                        title: "Campaign Name",
                        field: "campaign_name",
                        widthGrow: 2,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value || '<span class="text-muted">N/A</span>';
                        }
                    },
                    {
                        title: "Value",
                        field: "value",
                        width: 90,
                        formatter: function(cell) {
                            const value = cell.getValue();
                            return value ? '$' + parseFloat(value).toFixed(2) : '-';
                        }
                    },
                    {
                        title: "Status",
                        field: "status",
                        width: 110,
                        formatter: function(cell) {
                            const status = cell.getValue();
                            const badges = {
                                'success': '<span class="badge badge-success badge-large"><i class="fas fa-check"></i> Success</span>',
                                'skipped': '<span class="badge badge-warning badge-large"><i class="fas fa-exclamation"></i> Skipped</span>',
                                'failed': '<span class="badge badge-danger badge-large"><i class="fas fa-times"></i> Failed</span>',
                            };
                            return badges[status] || status;
                        }
                    },
                    {
                        title: "Reason",
                        field: "reason",
                        widthGrow: 3,
                        formatter: function(cell) {
                            const reason = cell.getValue();
                            return reason ? `<span class="reason-text">${reason}</span>` : '-';
                        }
                    },
                    {
                        title: "Source",
                        field: "source",
                        width: 90,
                        formatter: function(cell) {
                            const source = cell.getValue();
                            return source === 'web' ? 
                                '<span class="badge badge-light"><i class="fas fa-desktop"></i> Web</span>' : 
                                '<span class="badge badge-dark"><i class="fas fa-terminal"></i> CLI</span>';
                        }
                    },
                    {
                        title: "Actions",
                        width: 100,
                        formatter: function(cell) {
                            return '<button class="btn btn-sm btn-info view-details"><i class="fas fa-eye"></i> View</button>';
                        },
                        cellClick: function(e, cell) {
                            if (e.target.closest('.view-details')) {
                                showDetails(cell.getRow().getData());
                            }
                        }
                    }
                ]
            });
        }

        function getFilters() {
            return {
                push_type: document.getElementById('filter-push-type').value,
                status: document.getElementById('filter-status').value,
                source: document.getElementById('filter-source').value,
                date_from: document.getElementById('filter-date-from').value,
                date_to: document.getElementById('filter-date-to').value,
                campaign_id: document.getElementById('filter-campaign-id').value,
                campaign_name: document.getElementById('filter-campaign-name').value,
            };
        }

        function loadStats() {
            const filters = {
                date_from: document.getElementById('filter-date-from').value,
                date_to: document.getElementById('filter-date-to').value,
            };

            showLoading();
            fetch("/amazon-ads/push-logs/stats?" + new URLSearchParams(filters))
                .then(response => response.json())
                .then(data => {
                    // Update overall stats
                    document.getElementById('stat-total').textContent = data.overall.total.toLocaleString();
                    document.getElementById('stat-success').textContent = data.overall.success.toLocaleString();
                    document.getElementById('stat-skipped').textContent = data.overall.skipped.toLocaleString();
                    document.getElementById('stat-failed').textContent = data.overall.failed.toLocaleString();
                    
                    document.getElementById('stat-success-rate').textContent = data.overall.success_rate + '%';
                    document.getElementById('stat-skip-rate').textContent = data.overall.skip_rate + '%';
                    document.getElementById('stat-fail-rate').textContent = data.overall.fail_rate + '%';

                    // Update top reasons
                    displayTopReasons(data.top_reasons);
                    hideLoading();
                })
                .catch(error => {
                    console.error('Error loading stats:', error);
                    hideLoading();
                });
        }

        function displayTopReasons(reasons) {
            const container = document.getElementById('top-reasons-container');
            if (!reasons || reasons.length === 0) {
                container.innerHTML = '<p class="text-muted"><i class="fas fa-info-circle"></i> No failure data available for this period</p>';
                return;
            }

            let html = '';
            reasons.forEach((reason, index) => {
                html += `
                    <div class="reason-item">
                        <strong>${index + 1}. ${reason.reason}</strong>
                        <span class="badge badge-danger float-right">${reason.count} times</span>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        function showDetails(data) {
            const modal = document.getElementById('detailsModal');
            const modalBody = document.getElementById('modal-body-content');
            
            let html = `
                <table class="table table-bordered table-striped">
                    <tr><th width="30%">Campaign ID:</th><td><code>${data.campaign_id || 'N/A'}</code></td></tr>
                    <tr><th>Campaign Name:</th><td>${data.campaign_name || 'N/A'}</td></tr>
                    <tr><th>Push Type:</th><td>${getPushTypeName(data.push_type)}</td></tr>
                    <tr><th>Value:</th><td>$${data.value || 'N/A'}</td></tr>
                    <tr><th>Status:</th><td><span class="badge badge-${getStatusColor(data.status)}">${data.status}</span></td></tr>
                    <tr><th>Reason:</th><td class="reason-text">${data.reason || 'N/A'}</td></tr>
                    <tr><th>Source:</th><td>${data.source}</td></tr>
                    <tr><th>Date:</th><td>${new Date(data.created_at).toLocaleString()}</td></tr>
                    <tr><th>HTTP Status:</th><td>${data.http_status || 'N/A'}</td></tr>
                </table>
            `;

            if (data.request_data && Object.keys(data.request_data).length > 0) {
                html += '<h6 class="mt-3"><i class="fas fa-arrow-up"></i> Request Data:</h6>';
                html += '<pre class="bg-light p-3 border rounded">' + JSON.stringify(data.request_data, null, 2) + '</pre>';
            }

            if (data.response_data && Object.keys(data.response_data).length > 0) {
                html += '<h6 class="mt-3"><i class="fas fa-arrow-down"></i> Response Data:</h6>';
                html += '<pre class="bg-light p-3 border rounded">' + JSON.stringify(data.response_data, null, 2) + '</pre>';
            }

            modalBody.innerHTML = html;
            $(modal).modal('show');
        }

        function getPushTypeName(type) {
            const names = {
                'sp_sbid': 'SP Bid (Sponsored Products)',
                'sb_sbid': 'SB Bid (Sponsored Brands)',
                'sp_sbgt': 'SP Budget (Sponsored Products)',
                'sb_sbgt': 'SB Budget (Sponsored Brands)',
            };
            return names[type] || type;
        }

        function getStatusColor(status) {
            const colors = {
                'success': 'success',
                'skipped': 'warning',
                'failed': 'danger',
            };
            return colors[status] || 'secondary';
        }

        function setupEventListeners() {
            document.getElementById('btn-apply-filters').addEventListener('click', function() {
                showLoading();
                table.setData().then(() => {
                    loadStats();
                });
            });

            document.getElementById('btn-refresh').addEventListener('click', function() {
                showLoading();
                table.setData().then(() => {
                    loadStats();
                });
            });

            document.getElementById('btn-clear').addEventListener('click', function() {
                document.getElementById('filter-push-type').value = '';
                document.getElementById('filter-status').value = '';
                document.getElementById('filter-source').value = '';
                document.getElementById('filter-campaign-id').value = '';
                document.getElementById('filter-campaign-name').value = '';
                
                const today = new Date().toISOString().split('T')[0];
                const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                document.getElementById('filter-date-from').value = weekAgo;
                document.getElementById('filter-date-to').value = today;
                
                document.getElementById('btn-apply-filters').click();
            });

            document.getElementById('btn-export').addEventListener('click', function() {
                const filters = getFilters();
                const params = new URLSearchParams(filters);
                window.location.href = "/amazon-ads/push-logs/export?" + params.toString();
            });

            // Apply filters on Enter key
            ['filter-campaign-id', 'filter-campaign-name'].forEach(id => {
                document.getElementById(id).addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        document.getElementById('btn-apply-filters').click();
                    }
                });
            });
        }
    </script>
</body>
</html>
