@extends('layouts.vertical', ['title' => 'New Marketplaces', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet">
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --border-radius: 12px;
            --box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa !important;
            color: var(--dark-color) !important;
        }

        .container {
            max-width: 1200px !important;
            margin-top: 30px !important;
            margin-bottom: 50px !important;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 15px 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }

        .header h4 {
            font-weight: 600;
            margin: 0;
        }

        .metric-value {
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
        }

        /* Negative growth (Red) */
        .negative-growth {
            background-color: rgb(255, 0, 0);
            color: rgb(0, 0, 0);
            width: 60px;
            text-align: center;
        }

        /* Zero growth (Yellow) */
        .zero-growth {
            background-color: rgb(255, 196, 0);
            color: rgb(0, 0, 0);
            width: 60px;
            text-align: center;
        }

        /* EXACTLY 100% (Magenta) */
        .exact-100 {
            background-color: #ff00ff;
            color: rgb(0, 0, 0);
            width: 60px;
            text-align: center;
        }

        .search-box {
            max-width: 350px;
            margin-left: auto;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            padding: 5px;
            transition: var(--transition);
        }

        .dataTables_wrapper .dataTables_filter input {
            border: none;
            border-radius: 50px;
            padding: 8px 15px;
            margin-left: 10px;
        }

        .table>thead {
            vertical-align: bottom;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        th.sorting {
            color: white !important;
            font-size: 10px;
        }

        /* Rest of your existing styles... */
        /* Keep all your existing styles, just add these new ones below */

        /* DataTables custom styling */
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_processing,
        .dataTables_wrapper .dataTables_paginate {
            color: #333;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.5em 1em;
            margin: 0 2px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--primary-color);
            color: white !important;
            border: 1px solid var(--primary-color);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #e9ecef;
            border: 1px solid #ddd;
        }

        /* Loading indicator */
        .dataTables_processing {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            z-index: 100;
        }

        /* Responsive adjustments for DataTables */
        @media (max-width: 768px) {
            .dataTables_wrapper .dataTables_filter {
                float: none;
                text-align: left;
            }

            .dataTables_wrapper .dataTables_filter input {
                width: 100%;
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>
    <style>
        /* Right-to-Left Modal Animation */
        .modal.right-to-left .modal-dialog {
            margin: 0;
            right: 0;
            width: 400px;
            max-width: 80%;
            height: 100%;
            transform: translateX(100%);
            transition: transform 0.3s ease-out;
        }

        .modal.right-to-left.show .modal-dialog {
            transform: translateX(0);
        }

        .modal.right-to-left .modal-content {
            height: 100%;
            overflow-y: auto;
            border-radius: 0;
            border: none;
        }

        /* Keep your existing modal styling */
        .modal.right-to-left .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .modal.right-to-left .modal-footer {
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        /* Sticky Dashboard Cards */
        .dashboard-header {
            position: sticky;
            top: 0;
            background-color: white;
            z-index: 1030;
            padding-top: 10px;
        }

        /* Scrollable table */
        .table-container {
            overflow-x: auto;
            max-width: 100%;
        }

        .table-responsive {
            max-height: 500px;
            overflow-y: auto;
            overflow-x: auto;
        }

        /* Sticky Table Header */
        thead.sticky-top th {
            position: sticky;
            top: 0;
            z-index: 1020;
            background-color: #fff;
            box-shadow: 0 2px 2px rgba(0, 0, 0, 0.05);
        }

        /* Optional Cleanup */
        table th,
        table td {
            white-space: nowrap;
        }

        .dropdown-search-item {
            padding: 6px 10px;
            cursor: pointer;
        }

        .dropdown-search-item:hover {
            background-color: #eee;
        }

        /* ========== PLAY/PAUSE NAVIGATION BUTTONS ========== */
        .time-navigation-group {
            margin-left: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 50px;
            overflow: hidden;
            padding: 2px;
            background: #f8f9fa;
            display: inline-flex;
            align-items: center;
        }

        .time-navigation-group button {
            padding: 0;
            border-radius: 50% !important;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.2s ease;
            border: 1px solid #dee2e6;
            background: white;
            cursor: pointer;
        }

        .time-navigation-group button:hover {
            background-color: #f1f3f5 !important;
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .time-navigation-group button:active {
            transform: scale(0.95);
        }

        .time-navigation-group button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .time-navigation-group button i {
            font-size: 1.1rem;
            transition: transform 0.2s ease;
        }

        /* Play button */
        #play-auto {
            color: #28a745;
        }

        #play-auto:hover {
            background-color: #28a745 !important;
            color: white !important;
        }

        /* Pause button */
        #play-pause {
            color: #ffc107;
            display: none;
        }

        #play-pause:hover {
            background-color: #ffc107 !important;
            color: white !important;
        }

        /* Navigation buttons */
        #play-backward,
        #play-forward {
            color: #007bff;
        }

        #play-backward:hover,
        #play-forward:hover {
            background-color: #007bff !important;
            color: white !important;
        }

        /* Button state colors - must come after hover styles */
        #play-auto.btn-success,
        #play-pause.btn-success {
            background-color: #28a745 !important;
            color: white !important;
        }

        #play-auto.btn-warning,
        #play-pause.btn-warning {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }

        #play-auto.btn-danger,
        #play-pause.btn-danger {
            background-color: #dc3545 !important;
            color: white !important;
        }

        #play-auto.btn-light,
        #play-pause.btn-light {
            background-color: #f8f9fa !important;
            color: #212529 !important;
        }

        /* Ensure hover doesn't override state colors */
        #play-auto.btn-success:hover,
        #play-pause.btn-success:hover {
            background-color: #28a745 !important;
            color: white !important;
        }

        #play-auto.btn-warning:hover,
        #play-pause.btn-warning:hover {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }

        #play-auto.btn-danger:hover,
        #play-pause.btn-danger:hover {
            background-color: #dc3545 !important;
            color: white !important;
        }

        /* Active state styling */
        .time-navigation-group button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .time-navigation-group button {
                width: 36px;
                height: 36px;
            }

            .time-navigation-group button i {
                font-size: 1rem;
            }
        }

        /* Add to your CSS file or style section */
        .hide-column {
            display: none !important;
        }

        .dataTables_length, .dataTables_filter {
            display: none;
        }

        #play-auto.green-btn {
            background-color: green !important;
            color: white;
        }

        #play-auto.red-btn {
            background-color: red !important;
            color: white;
        }

        th small.badge {
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 12px;
        }

        .dataTables_processing {
            top: 150px !important;  /* Try 80–100px depending on your header height */
            z-index: 1000 !important;
            background: none !important;
            border: none;
        }

        #channelTable {
            width: 100% !important;
            table-layout: fixed;
        }

        .select2-container {
            z-index: 9999 !important;
        }

        .text-truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', [
        'page_title' => 'New Marketplaces',
        'sub_title' => 'New Marketplace Dashboard',
    ])
    <div class="container mt-4">

        <div class="d-flex justify-content-between align-items-center mb-3">

            <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="mdi mdi-upload me-1"></i> Import CSV
                </button>

                <a href="{{ route('new-marketplaces.export') }}" class="btn btn-success">
                    <i class="mdi mdi-download me-1"></i> Export CSV
                </a>
            </div>

            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createMarketplaceModal">
                + Create Marketplace
            </button>
        </div>

        <div class="row">
            <!-- Left side: Status Counts -->
            <div class="col-md-6">
                <table class="table table-bordered text-center">
                    <thead class="table-light">
                        <tr>
                            <th>Status</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><a href="#" class="status-link" data-status="Not Started">Opportunity Sales</a></td>
                            <td>{{ $counts['opportunity'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td><a href="#" class="status-link" data-status="Applied">In Review</a></td>
                            <td>{{ $counts['review'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td><a href="#" class="status-link" data-status="Onboarded">Onboarded</a></td>
                            <td>{{ $counts['onboarded'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td><a href="#" class="status-link" data-status="Rejected">Rejected</a></td>
                            <td>{{ $counts['rejected'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td><a href="#" class="status-link" data-status="Re-apply">Re-apply</a></td>
                            <td>{{ $counts['reapply'] ?? 0 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Right side: Followups & Application Summary -->
            <div class="col-md-6">
                <table class="table table-bordered text-center">
                    <thead class="table-light">
                        <tr>
                            <th colspan="2">To Followup</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($followups->isEmpty())
                            <tr>
                                <td colspan="2">No channels need follow-up today</td>
                            </tr>
                        @else
                            @foreach($followups as $item)
                                <tr>
                                    <td>{{ $item->channel_name }}</td>
                                    <td>Follow up (Day {{ \Carbon\Carbon::parse($item->apply_date)->diffInDays(now()) }})</td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>

                <table class="table table-bordered text-center mt-4">
                    <thead class="table-light">
                        <tr>
                            <th>Application</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><a href="#" class="range-link" data-range="this_week">This Week</a></td>
                            <td>{{ $applicationStats['this_week'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td><a href="#" class="range-link" data-range="last_30_days">Last 30 Days</a></td>
                            <td>{{ $applicationStats['last_30_days'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td><a href="#" class="range-link" data-range="all_time">All Time</a></td>
                            <td>{{ $applicationStats['all_time'] ?? 0 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Create Marketplace Modal -->
        <div class="modal fade" id="createMarketplaceModal" tabindex="-1" aria-labelledby="createMarketplaceLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <form id="createMarketplaceForm" method="POST" action="{{ route('new.marketplaces.store') }}">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="createMarketplaceLabel">Create Marketplace</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <div class="container-fluid">
                                <div class="row g-3">
                                    <!-- Row 1 -->
                                    <!-- <div class="col-md-4 mb-3">
                                        <label for="channel_name" class="form-label fw-bold">Channel Name</label>
                                        <select id="channel_name" name="channel_name" class="form-select select2" required>
                                            <option value="" selected disabled>Select Channel</option>
                                        </select>
                                    </div> -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Channel Name</label>
                                        <input type="text" name="channel_name" class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Link as Customer</label>
                                        <input type="url" name="link_customer" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Type</label>
                                        <select name="type" class="form-select">
                                            <option selected disabled>Select Type</option>
                                            <option value="B2B">B2B</option>
                                            <option value="B2C">B2C</option>
                                            <option value="Dropship">Dropship</option>
                                            <option value="C2C">C2C</option>
                                        </select>
                                    </div>

                                    <!-- Row 2 -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Priority</label>
                                        <select name="priority" class="form-select">
                                            <option selected disabled>Select Priority</option>
                                            <option value="High">High</option>
                                            <option value="Low">Low</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Category Allowed</label>
                                        <input type="text" name="category_allowed" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Link Seller/Supplier</label>
                                        <input type="url" name="link_seller" class="form-control">
                                    </div>

                                    <!-- Row 3 -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Last Year Traffic (Monthly)</label>
                                        <input type="number" name="last_year_traffic" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Current Traffic (Monthly)</label>
                                        <input type="number" name="current_traffic" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">US Presence (%)</label>
                                        <input type="number" name="us_presence" class="form-control" min="0" max="100">
                                    </div>

                                    <!-- Row 4 -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">US Visitor Count</label>
                                        <input type="number" name="us_visitors" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Commission Charges</label>
                                        <input type="text" name="commission" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Applied Through</label>
                                        <select name="applied_through" class="form-select">
                                            <option selected disabled>Select Source</option>
                                            <option value="Portal">Direct Portal</option>
                                            <option value="Email">Email</option>
                                            <option value="Via Third Party">Via Third Party</option>
                                        </select>
                                    </div>

                                    <!-- Row 5 -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Status</label>
                                        <select name="status" class="form-select">
                                            <option selected disabled>Select Status</option>
                                            <option value="Not Started">Not Started</option>
                                            <option value="Applied">Applied</option>
                                            <option value="Processed">Processed</option>
                                            <option value="Rejected">Rejected</option>
                                            <option value="Resubmit">Resubmit</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">ID When Applied</label>
                                        <input type="text" name="applied_id" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Password</label>
                                        <input type="text" name="password" class="form-control">
                                    </div>

                                    <!-- Row 6 -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Remarks If Any</label>
                                        <input type="text" name="remarks" class="form-control">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Apply Date</label>
                                        <input type="date" name="apply_date" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success">Save Marketplace</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>


        <!-- Marketplace Status Modal -->
        <div class="modal fade" id="marketplaceStatusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title" id="statusModalLabel">Marketplaces</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-striped table-hover" id="statusMarketplacesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Channel Name</th>
                                    <th>Type</th>
                                    <th>Link Seller/Supplier</th>
                                    <th>Applied Through</th>
                                    <th>Status</th>
                                    <th>Apply Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="status-marketplace-body">
                                <!-- JS will populate rows -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>


        <!-- Import Modal -->
        <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('new-marketplaces.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                <h5 class="modal-title">Import New Marketplaces</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                <p><strong>Note:</strong> Download <a href="{{ asset('sample_excel/sample_new_marketplaces.csv') }}" target="_blank">Sample CSV</a> before uploading.</p>
                <div class="mb-3">
                    <label for="csv_file" class="form-label">Choose CSV file</label>
                    <input type="file" name="csv_file" class="form-control" required accept=".csv">
                </div>
                </div>
                <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </div>
            </form>
        </div>
        </div>


        <!-- Edit Marketplace Modal -->
        <div class="modal fade" id="editMarketplaceModal" tabindex="-1" aria-labelledby="editMarketplaceLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <form id="editMarketplaceForm">
                    @csrf
                    <input type="hidden" id="edit_id" name="id">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="editMarketplaceLabel">Edit Marketplace</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <div class="container-fluid">
                                <div class="row g-3">
                                    <!-- Row 1 -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Channel Name</label>
                                        <input type="text" class="form-control" id="edit_channel_name" name="channel_name" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Link as Customer</label>
                                        <input type="url" class="form-control" id="edit_link_customer" name="link_customer">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Type</label>
                                        <select class="form-select" id="edit_type" name="type">
                                            <option disabled selected>Select Type</option>
                                            <option value="B2B">B2B</option>
                                            <option value="B2C">B2C</option>
                                            <option value="Dropship">Dropship</option>
                                            <option value="C2C">C2C</option>
                                        </select>
                                    </div>

                                    <!-- Row 2 -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Priority</label>
                                        <select class="form-select" id="edit_priority" name="priority">
                                            <option disabled selected>Select Priority</option>
                                            <option value="High">High</option>
                                            <option value="Low">Low</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Category Allowed</label>
                                        <input type="text" class="form-control" id="edit_category_allowed" name="category_allowed">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Link Seller/Supplier</label>
                                        <input type="url" class="form-control" id="edit_link_seller" name="link_seller">
                                    </div>

                                    <!-- Row 3 -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Last Year Traffic (Monthly)</label>
                                        <input type="number" class="form-control" id="edit_last_year_traffic" name="last_year_traffic">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Current Traffic (Monthly)</label>
                                        <input type="number" class="form-control" id="edit_current_traffic" name="current_traffic">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">US Presence (%)</label>
                                        <input type="number" class="form-control" id="edit_us_presence" name="us_presence" min="0" max="100">
                                    </div>

                                    <!-- Row 4 -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">US Visitor Count</label>
                                        <input type="number" class="form-control" id="edit_us_visitors" name="us_visitors">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Commission Charges</label>
                                        <input type="text" class="form-control" id="edit_commission" name="commission">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Applied Through</label>
                                        <select class="form-select" id="edit_applied_through" name="applied_through">
                                            <option disabled selected>Select Source</option>
                                            <option value="Portal">Direct Portal</option>
                                            <option value="Email">Email</option>
                                            <option value="Via Third Party">Via Third Party</option>
                                        </select>
                                    </div>

                                    <!-- Row 5 -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Status</label>
                                        <select class="form-select" id="edit_status" name="status" required>
                                            <option disabled selected>Select Status</option>
                                            <option value="Not Started">Not Started</option>
                                            <option value="Applied">Applied</option>
                                            <option value="Processed">Processed</option>
                                            <option value="Rejected">Rejected</option>
                                            <option value="Resubmit">Resubmit</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">ID When Applied</label>
                                        <input type="text" class="form-control" id="edit_applied_id" name="applied_id">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Password</label>
                                        <input type="text" class="form-control" id="edit_password" name="password">
                                    </div>

                                    <!-- Row 6 -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Remarks If Any</label>
                                        <input type="text" class="form-control" id="edit_remarks" name="remarks">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Apply Date</label>
                                        <input type="date" class="form-control" id="edit_apply_date" name="apply_date">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-success">Update Marketplace</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>






    </div>
@endsection

@section('script')
    <!-- 1. Load jQuery FIRST -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- 2. Then load DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

    <!-- 3. Then load other dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // Use jQuery.noConflict() if needed
        var jq = jQuery.noConflict(true);

        // Global variables
        let originalChannelData = [];
        let allChannelData = [];
        let table;
        let isPlaying = false;
        let currentChannelIndex = 0;
        let uniqueChannels = [];
        let uniqueChannelRows = [];
        let selectedChannel = '';

        // Enhanced number parsing function
        function parseNumber(value) {
            if (value === null || value === undefined || value === '' || 
                value === '#DIV/0!' || value === 'N/A') return 0;
            if (typeof value === 'number') return value;
            const cleaned = String(value).replace(/[^0-9.-]/g, '');
            return parseFloat(cleaned) || 0;
        }

        // Function to update all summary cards
        function updateAllTotals(data) {
            let l60SalesTotal = 0;
            let l30SalesTotal = 0;
            let l60OrdersTotal = 0;
            let l30OrdersTotal = 0;
            let growthValues = [];
            let gprofitValues = [];
            let groiValues = [];
            
            data.forEach(row => {
                try {
                    // Process sales - try multiple possible column names
                    const l60Sales = parseNumber(
                        row['L-60 Sales'] || row['L40 Sales'] || row['L40-Dollar'] || row['L40-Dollars'] || 0
                    );
                    const l30Sales = parseNumber(
                        row['L30 Sales'] || row['L30-Dollar'] || row['L30-Dollars'] || 0
                    );
                    
                    // Process orders - try multiple possible column names
                    const l60Orders = parseNumber(
                        row['L60 Orders'] || row['L40-Dollars'] || 0
                    );
                    const l30Orders = parseNumber(
                        row['L30 Orders'] || row['L30-Dollars'] || 0
                    );
                    
                    // Process metrics
                    const growth = parseNumber(row['Growth'] || 0);
                    const gprofit = parseNumber(row['Gprofit%'] || row['Growth%'] || 0);
                    const groi = parseNumber(row['G Roi%'] || row['G.Rents'] || 0);
                    
                    // Add to totals
                    l60SalesTotal += l60Sales;
                    l30SalesTotal += l30Sales;
                    l60OrdersTotal += l60Orders;
                    l30OrdersTotal += l30Orders;
                    
                    // Collect for averages
                    if (!isNaN(growth)) growthValues.push(growth);
                    if (!isNaN(gprofit)) gprofitValues.push(gprofit);
                    if (!isNaN(groi)) groiValues.push(groi);
                    
                } catch (e) {
                    console.error("Error processing row:", row, e);
                }
            });

            let growthTotal = 0;
            if (l30SalesTotal !== 0) {
                growthTotal = ((l30SalesTotal - l60SalesTotal) / l60SalesTotal) * 100;
            }

            // Update the cards with formatted values
            const l60Badge = document.getElementById('l60SalesCountBadge');
            const l30Badge = document.getElementById('l30SalesCountBadge');
            const l60OrdersBadge = document.getElementById('l60OrdersCountBadge');
            const l30OrdersBadge = document.getElementById('l30OrdersCountBadge');
            const growthBadge = document.getElementById('growthPercentageBadge');
            const gprofitBadge = document.getElementById('gprofitPercentage');
            const groiBadge = document.getElementById('groiPercentageBadge');

            if (l60Badge) l60Badge.textContent = Math.round(l60SalesTotal).toLocaleString('en-US');
            if (l30Badge) l30Badge.textContent = Math.round(l30SalesTotal).toLocaleString('en-US');
            if (l60OrdersBadge) l60OrdersBadge.textContent = Math.round(l60OrdersTotal).toLocaleString('en-US');
            if (l30OrdersBadge) l30OrdersBadge.textContent = Math.round(l30OrdersTotal).toLocaleString('en-US');
            if (growthBadge) growthBadge.textContent = growthTotal.toFixed(0) + '%';
            
            // Calculate G profit and G roi using totalPft, totalL30Sales, totalCogs
            let totalPft = 0;
            let totalCogs = 0;
            let totalL30Sales = 0;
            data.forEach(function(row) {
                const pft = parseNumber(row['pft']);
                totalPft += pft;
                const cogs = parseNumber(row['COGS']);
                totalCogs += cogs;
                const l30Sales = parseNumber(row['L30 Sales']);
                totalL30Sales += l30Sales;
            });
            let gProfit = totalL30Sales !== 0 ? (totalPft / totalL30Sales * 100) : null;
            let gRoi = totalCogs !== 0 ? (totalPft / totalCogs * 100) : null;
            if (gprofitBadge) gprofitBadge.textContent = gProfit !== null ? gProfit.toFixed(1) + '%' : 'N/A';
            if (groiBadge) groiBadge.textContent = gRoi !== null ? gRoi.toFixed(1) + '%' : 'N/A';
        }


        // jq('.select2').select2({
        //     dropdownParent: jq('#createMarketplaceModal'),
        //     placeholder: 'Select Channel',
        //     allowClear: true,
        //     ajax: {
        //         url: '{{ route("channels.fetch") }}', // We'll add this route
        //         dataType: 'json',
        //         delay: 250,
        //         data: function (params) {
        //             return {
        //                 searchTerm: params.term // search input
        //             };
        //         },
        //         processResults: function (data) {
        //             return {
        //                 results: data.data.map(function (item) {
        //                     return {
        //                         id: item,
        //                         text: item
        //                     };
        //                 })
        //             };
        //         },
        //         cache: true
        //     }
        // });

        jq('#createMarketplaceForm').on('submit', function(e) {
            e.preventDefault();

            $.ajax({
                url: $(this).attr('action'),
                method: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    alert(response.message);
                    $('#createMarketplaceModal').modal('hide');
                    $('#createMarketplaceForm')[0].reset();
                    // Reload your table here
                },
                error: function(xhr) {
                    console.error(xhr.responseJSON);
                    alert('Something went wrong!');
                }
            });
        });

        jq(document).ready(function () {
            jq('.status-link, .range-link').on('click', function (e) {
                e.preventDefault();
                const status = $(this).data('status');
                const range  = jq(this).data('range');

                let modalTitle = '';
                let requestData = {};

                if (status) {
                    modalTitle = status + ' Marketplaces';
                    requestData = { status: status };
                    ajaxUrl = '{{ route("new.marketplaces.byStatus") }}';
                } else if (range) {
                    modalTitle = range.replace(/_/g, ' ').toUpperCase() + ' Applications';
                    requestData = { filter: range };
                    ajaxUrl = '{{ route("new.marketplaces.dashboard") }}'; // ✅ SAME index route
                }

                jq('#statusModalLabel').text(modalTitle);

                // Fetch filtered data
                $.ajax({
                    url: ajaxUrl,
                    type: 'GET',
                    data: requestData,
                    success: function (response) {
                        const tbody = $('#status-marketplace-body');
                        tbody.empty();

                        if (!response.data || response.data.length === 0) {
                            tbody.append('<tr><td colspan="5" class="text-center">No data available</td></tr>');

                        } else {

                            response.data.forEach(item => {
                                tbody.append(`
                                    <tr>
                                        <td>${item.channel_name || '-'}</td>
                                        <td>${item.type || '-'}</td>
                                        <td>
                                            ${item.link_seller 
                                                ? `<a href="${item.link_seller}" target="_blank" title="${item.link_seller}" class="text-primary text-decoration-underline d-inline-block text-truncate" style="max-width: 200px;">${item.link_seller}</a>` 
                                                : '-'}
                                        </td>
                                        <td>${item.applied_through || '-'}</td>
                                        <td><select class="form-select status-dropdown" data-id="${item.id}">
                                                <option value="Not Started" ${item.status === 'Not Started' ? 'selected' : ''}>Not Started</option>
                                                <option value="Applied" ${item.status === 'Applied' ? 'selected' : ''}>Applied</option>
                                                <option value="Processed" ${item.status === 'Processed' ? 'selected' : ''}>Processed</option>
                                                <option value="Rejected" ${item.status === 'Rejected' ? 'selected' : ''}>Rejected</option>
                                                <option value="Resubmit" ${item.status === 'Resubmit' ? 'selected' : ''}>Resubmit</option>
                                            </select>
                                        </td>
                                        <td>${item.apply_date || '-'}</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary edit-marketplace" data-id="${item.id}">Edit</button>
                                        </td>
                                    </tr>
                                `);
                            });
                        }

                        $('#marketplaceStatusModal').modal('show');
                    },
                    error: function () {
                        alert('Failed to load data');
                    }
                });
            });
        });

        //Edit button click
        jq(document).on('click', '.edit-marketplace', function () {
            let id = $(this).data('id');
             console.log("Edit button clicked for ID:", id); 

            $.ajax({
                url: `/edit-new-marketplaces/${id}`, // ← This matches your Laravel route
                type: 'GET',
                success: function (response) {
                    if (response.success) {
                        let data = response.data;

                        // Fill modal fields
                        $('#edit_id').val(data.id);
                        $('#edit_channel_name').val(data.channel_name);
                        $('#edit_link_customer').val(data.link_customer);
                        $('#edit_type').val(data.type);
                        $('#edit_priority').val(data.priority);
                        $('#edit_category_allowed').val(data.category_allowed);
                        $('#edit_link_seller').val(data.link_seller);
                        $('#edit_last_year_traffic').val(data.last_year_traffic);
                        $('#edit_current_traffic').val(data.current_traffic);
                        $('#edit_us_presence').val(data.us_presence);
                        $('#edit_us_visitors').val(data.us_visitors);
                        $('#edit_commission').val(data.commission);
                        $('#edit_applied_through').val(data.applied_through);
                        $('#edit_status').val(data.status);
                        $('#edit_applied_id').val(data.applied_id);
                        $('#edit_password').val(data.password);
                        $('#edit_remarks').val(data.remarks);
                        $('#edit_apply_date').val(data.apply_date);
                        // Show modal
                        $('#editMarketplaceModal').modal('show');
                    } else {
                        alert('Marketplace not found');
                    }
                },
                error: function () {
                    alert('Error loading data.');
                }
            });
        });


        //Edit form submit
        jq('#editMarketplaceForm').on('submit', function (e) {
            e.preventDefault();

            let id = $('#edit_id').val();
            let formData = $(this).serialize();

            $.post(`/new-marketplaces/${id}`, formData, function (res) {
                $('#editMarketplaceModal').modal('hide');
                loadMarketplaceData(); // refresh table
            }).fail(function (xhr) {
                alert(xhr.responseJSON.message || 'Something went wrong.');
            });
        });


        // Handle status update from dropdown directly
        jq('#status-marketplace-body').on('change', '.status-dropdown', function () {
            const newStatus = jq(this).val();
            const id = jq(this).data('id');

            // Optional: disable dropdown while processing
            jq(this).prop('disabled', true);

            jq.ajax({
                url: '{{ route("marketplaces.updateStatus") }}',
                method: 'POST',
                data: {
                    id: id,
                    status: newStatus,
                    _token: '{{ csrf_token() }}'
                },
                success: function (response) {
                    // Optional: feedback
                    toastr.success('Status updated successfully!');
                },
                error: function () {
                    toastr.error('Failed to update status.');
                },
                complete: () => {
                    jq('.status-dropdown[data-id="' + id + '"]').prop('disabled', false);
                }
            });
        });








        function updatePlayButtonColor() {
            try {
                if (!table || !table.rows) return;
                
                const visibleRow = table.rows({ search: 'applied' }).nodes().to$();
                if (!visibleRow || !visibleRow.length) return;
                
                const raCheckbox = visibleRow.find('.ra-checkbox');
                if (raCheckbox.length) {
                    const isChecked = raCheckbox.prop('checked');
                    console.log('Checkbox checked (updatePlayButtonColor):', isChecked);

                    const playPauseBtn = jq('#play-pause');
                    if (playPauseBtn.length) {
                        playPauseBtn
                            .removeClass('btn-light btn-success btn-danger')
                            .addClass(isChecked ? 'btn-success' : 'btn-danger')
                            .css('color', 'white');
                    }
                }
            } catch (e) {
                console.error('Error in updatePlayButtonColor:', e);
            }
        }

        // Play/Pause functionality
        function startPlayback() {
            try {
                console.log('Starting playback...');

                if (!originalChannelData.length) {
                    console.error('No channel data available');
                    return;
                }

                // Get unique channels
                uniqueChannels = [...new Set(originalChannelData.map(item => item['Channel ']?.trim()))].filter(Boolean);
                console.log('Found unique channels:', uniqueChannels);

                uniqueChannelRows = uniqueChannels.map(channel => {
                    return originalChannelData.find(item => item['Channel ']?.trim() === channel);
                });

                if (uniqueChannelRows.length === 0) {
                    console.error('No unique channels found');
                    return;
                }

                currentChannelIndex = 0;
                isPlaying = true;

                // Configure table for single-channel view
                table.page.len(1).draw();
                table.search('').columns().search('').draw();

                // Show first channel
                showCurrentChannel();

                // Update UI
                const playAutoBtn = document.getElementById('play-auto');
                const playPauseBtn = document.getElementById('play-pause');
                
                if (playAutoBtn) playAutoBtn.style.display = 'none';
                if (playPauseBtn) playPauseBtn.style.display = 'block';
                
                table.column(1).visible(true);

                console.log('Playback started. Current channel:', uniqueChannelRows[currentChannelIndex]['Channel ']);

                setTimeout(() => {
                    updatePlayButtonColor();
                }, 500);
            } catch (e) {
                console.error('Error in startPlayback:', e);
            }
        }

        function stopPlayback() {
            try {
                console.log('Stopping playback...');
                isPlaying = false;

                // Restore table to normal view
                table.clear().rows.add(originalChannelData).draw();
                table.page.len(25).draw();

                // Update UI
                const playAutoBtn = document.getElementById('play-auto');
                const playPauseBtn = document.getElementById('play-pause');
                
                if (playAutoBtn) playAutoBtn.style.display = 'block';
                if (playPauseBtn) playPauseBtn.style.display = 'none';
                
                table.column(1).visible(false);
            } catch (e) {
                console.error('Error in stopPlayback:', e);
            }
        }

        function showCurrentChannel() {
            try {
                if (!isPlaying || !uniqueChannelRows.length) return;

                const currentRow = uniqueChannelRows[currentChannelIndex];
                console.log('Showing channel:', currentRow['Channel ']);

                if (currentRow) {
                    // Clear table and add only the current row
                    table.clear().rows.add([currentRow]).draw();

                    // Update search input to show current channel
                    const searchInput = document.getElementById('channelSearchInput');
                    if (searchInput) {
                        searchInput.value = currentRow['Channel ']?.trim() || '';
                    }

                    // Scroll to the top of the table
                    const tableContainer = document.getElementById('channelTable')?.parentElement;
                    if (tableContainer) {
                        tableContainer.scrollTop = 0;
                    }

                    setTimeout(() => {
                        updatePlayButtonColor();
                    }, 500);
                }
            } catch (e) {
                console.error('Error in showCurrentChannel:', e);
            }
        }

        function nextChannel() {
            try {
                if (!isPlaying) return;

                if (currentChannelIndex < uniqueChannelRows.length - 1) {
                    currentChannelIndex++;
                    console.log('Moving to next channel. New index:', currentChannelIndex);
                    showCurrentChannel();
                } else {
                    console.log('Reached end of channel list');
                    stopPlayback();
                }
            } catch (e) {
                console.error('Error in nextChannel:', e);
            }
        }

        function previousChannel() {
            try {
                if (!isPlaying) return;

                if (currentChannelIndex > 0) {
                    currentChannelIndex--;
                    console.log('Moving to previous channel. New index:', currentChannelIndex);
                    showCurrentChannel();
                }
            } catch (e) {
                console.error('Error in previousChannel:', e);
            }
        }

        // Sorting toggle logic
        let currentSortColumn = 'Channel ';
        let currentSortOrder = 'asc';

        function setupSorting() {
            try {
                let sortDirection = 'asc';

                const toggleSortBtn = document.getElementById('toggleSort');
                const sortDirectionText = document.getElementById('sortDirectionText');
                const sortMetricSelect = document.getElementById('sortMetric');

                if (toggleSortBtn && sortDirectionText) {
                    toggleSortBtn.addEventListener('click', function() {
                        sortDirection = (sortDirection === 'asc') ? 'desc' : 'asc';
                        sortDirectionText.textContent = sortDirection === 'asc' ? 'Low to High' : 'High to Low';
                        const metricIndex = parseInt(document.getElementById('sortMetric').value);
                        table.order([metricIndex, sortDirection]).draw();
                    });
                }

                if (sortMetricSelect) {
                    sortMetricSelect.addEventListener('change', function() {
                        const metricIndex = parseInt(this.value);
                        table.order([metricIndex, sortDirection]).draw();
                    });
                }

                // Initial sort
                if (sortMetricSelect) {
                    sortMetricSelect.value = '3';
                }
                table.order([3, sortDirection]).draw();
            } catch (e) {
                console.error('Error in setupSorting:', e);
            }
        }

        // Dropdown functionality
        function populateChannelDropdown(searchTerm = '') {
            try {
                const channelData = originalChannelData.map(row => row['Channel ']);
                const uniqueChannels = [...new Set(channelData)].filter(ch => ch && ch.trim() !== '');
                uniqueChannels.sort();

                const lowerSearch = searchTerm.toLowerCase();
        
                const sortedChannels = uniqueChannels.sort((a, b) => {
                    const aMatch = a.toLowerCase().includes(lowerSearch);
                    const bMatch = b.toLowerCase().includes(lowerSearch);
                    if (aMatch && !bMatch) return -1;
                    if (!aMatch && bMatch) return 1;
                    return a.localeCompare(b);
                });

                const dropdown = document.getElementById('channelSearchDropdown');
                if (!dropdown) return;

                dropdown.innerHTML = '';

                uniqueChannels.forEach(channel => {
                    const item = document.createElement('div');
                    item.className = 'dropdown-search-item';
                    item.dataset.value = channel;
                    item.textContent = channel;
                    dropdown.appendChild(item);
                });

                dropdown.style.display = 'block';
            } catch (e) {
                console.error('Error in populateChannelDropdown:', e);
            }
        }

      
    </script>
@endsection