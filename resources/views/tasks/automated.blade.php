@extends('layouts.vertical', ['title' => 'Automated Tasks', 'sidenav' => 'condensed'])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        /* Statistics Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-left: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            font-size: 28px;
            color: white;
        }

        .stat-content {
            flex: 1;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 600;
            color: #6c757d;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
        }

        .stat-unit {
            font-size: 10px;
            color: #6c757d;
            margin-top: 2px;
        }

        /* Blue - Total */
        .stat-card-blue {
            border-left-color: #3b7ddd;
        }
        .stat-card-blue .stat-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        /* Cyan - Pending */
        .stat-card-cyan {
            border-left-color: #0dcaf0;
        }
        .stat-card-cyan .stat-icon {
            background: linear-gradient(135deg, #0dcaf0 0%, #0891b2 100%);
        }

        /* Red - Overdue */
        .stat-card-red {
            border-left-color: #dc3545;
        }
        .stat-card-red .stat-icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        /* Green - Done */
        .stat-card-green {
            border-left-color: #28a745;
        }
        .stat-card-green .stat-icon {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        /* Yellow - ETC */
        .stat-card-yellow {
            border-left-color: #ffc107;
        }
        .stat-card-yellow .stat-icon {
            background: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%);
        }

        /* Teal - ATC */
        .stat-card-teal {
            border-left-color: #20c997;
        }
        .stat-card-teal .stat-icon {
            background: linear-gradient(135deg, #0ba360 0%, #3cba92 100%);
        }

        /* Orange - Done ETC */
        .stat-card-orange {
            border-left-color: #fd7e14;
        }
        .stat-card-orange .stat-icon {
            background: linear-gradient(135deg, #fa8305 0%, #ff6b6b 100%);
        }

        /* Purple - Done ATC */
        .stat-card-purple {
            border-left-color: #6610f2;
        }
        .stat-card-purple .stat-icon {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 15px;
            }
            
            .stat-value {
                font-size: 24px;
            }
            
            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
        }
        
        /* Clean Table Styling */
        #tasks-table {
            background: white;
            border-radius: 8px;
            overflow-x: auto;
            overflow-y: hidden;
        }
        
        .table-wrapper {
            overflow-x: auto;
            overflow-y: visible;
        }

        .tabulator {
            border: 1px solid #e9ecef !important;
            border-radius: 8px !important;
            font-size: 14px;
        }

        .tabulator .tabulator-header {
            background-color: #f8f9fa !important;
            border-bottom: 2px solid #e9ecef !important;
        }

        .tabulator .tabulator-header .tabulator-col {
            background-color: #f8f9fa !important;
            border-right: 1px solid #e9ecef !important;
            padding: 12px 8px !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 0 !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            font-weight: 600 !important;
            color: #495057 !important;
            font-size: 13px !important;
            text-transform: uppercase;
        }

        .tabulator-row {
            border-bottom: 1px solid #e9ecef !important;
            background: white !important;
        }

        .tabulator-row:hover {
            background-color: #f8f9fa !important;
        }

        .tabulator-row.tabulator-selected {
            background-color: #e7f3ff !important;
        }

        .tabulator-row.tabulator-selected:hover {
            background-color: #d0e8ff !important;
        }

        .tabulator-row .tabulator-cell {
            border-right: 1px solid #e9ecef !important;
            padding: 12px 8px !important;
            color: #495057;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background-color: #cff4fc;
            color: #055160;
            border: 1px solid #9eeaf9;
        }

        .status-in_progress {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffe69c;
        }

        .status-archived {
            background-color: #e2e3e5;
            color: #41464b;
            border: 1px solid #d3d6d8;
        }

        .status-completed {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-need_help {
            background-color: #ffe5d0;
            color: #984c0c;
            border: 1px solid #ffc9a0;
        }

        .status-need_approval {
            background-color: #e0cffc;
            color: #432874;
            border: 1px solid #d8bbff;
        }

        .status-dependent {
            background-color: #f7d6e6;
            color: #ab296a;
            border: 1px solid #f1b0d0;
        }

        .status-approved {
            background-color: #d1f4e0;
            color: #146c43;
            border: 1px solid #9dd9c3;
        }

        .status-hold {
            background-color: #dee2e6;
            color: #212529;
            border: 1px solid #ced4da;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Priority Badges */
        .priority-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .priority-low {
            background-color: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }

        .priority-normal {
            background-color: #cfe2ff;
            color: #084298;
            border: 1px solid #b6d4fe;
        }

        .priority-high {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Action Icon Buttons */
        .action-btn-icon {
            padding: 8px 10px;
            font-size: 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0 3px;
            display: inline-block;
            text-align: center;
            width: 36px;
            height: 36px;
            line-height: 20px;
        }

        .action-btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .action-btn-view {
            background-color: #0dcaf0;
            color: white;
        }

        .action-btn-view:hover {
            background-color: #0bb5d7;
        }

        .action-btn-edit {
            background-color: #ffc107;
            color: #000;
        }

        .action-btn-edit:hover {
            background-color: #e0a800;
        }

        .action-btn-delete {
            background-color: #dc3545;
            color: white;
        }

        .action-btn-delete:hover {
            background-color: #bb2d3b;
        }

        /* Pagination */
        .tabulator-footer {
            background: #f8f9fa !important;
            border-top: 2px solid #e9ecef !important;
            padding: 15px !important;
        }

        .tabulator-page {
            border: 1px solid #dee2e6 !important;
            background: white !important;
            color: #495057 !important;
            border-radius: 4px !important;
            margin: 0 2px !important;
        }

        .tabulator-page:hover {
            background: #e9ecef !important;
        }

        .tabulator-page.active {
            background: #0d6efd !important;
            color: white !important;
            border-color: #0d6efd !important;
        }

        /* Create Button */
        .btn-create-task {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 10px 20px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }

        .btn-create-task:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        /* Card Styling */
        .task-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .task-card .card-body {
            padding: 25px;
        }

        /* Page Title */
        .page-title {
            font-weight: 700;
            color: #2c3e50;
            font-size: 24px;
        }

        /* ID Column */
        .id-cell {
            font-weight: 600;
            color: #6c757d;
        }

        /* Empty state */
        .tabulator-placeholder {
            padding: 50px !important;
            color: #6c757d !important;
            font-size: 16px !important;
        }

        /* Horizontal Scrollbar Styling */
        .tabulator {
            overflow-x: auto !important;
        }

        .tabulator::-webkit-scrollbar {
            height: 8px;
        }

        .tabulator::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .tabulator::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .tabulator::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Status Dropdown Styling */
        .status-select {
            cursor: pointer;
            border-radius: 20px !important;
            padding: 5px 10px !important;
            font-weight: 600 !important;
            border-width: 2px !important;
        }

        .status-select:focus {
            box-shadow: none !important;
        }

        .status-select option {
            padding: 10px;
        }
    </style>
@endsection

@section('content')
    <!-- Start Content-->
    <div class="container-fluid">
        
        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('tasks.index') }}">Task Manager</a></li>
                            <li class="breadcrumb-item active">Automated Tasks</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Automated Tasks</h4>
                </div>
            </div>
        </div>     
        <!-- end page title --> 

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <!-- Total Tasks -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-blue">
                    <div class="stat-icon">
                        <i class="mdi mdi-format-list-bulleted"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">TOTAL</div>
                        <div class="stat-value">{{ $stats['total'] }}</div>
                    </div>
                </div>
            </div>

            <!-- Daily Tasks -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-cyan">
                    <div class="stat-icon">
                        <i class="mdi mdi-calendar-today"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">DAILY</div>
                        <div class="stat-value">{{ $stats['daily'] }}</div>
                    </div>
                </div>
            </div>

            <!-- Weekly Tasks -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-purple">
                    <div class="stat-icon">
                        <i class="mdi mdi-calendar-week"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">WEEKLY</div>
                        <div class="stat-value">{{ $stats['weekly'] }}</div>
                    </div>
                </div>
            </div>

            <!-- Monthly Tasks -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-orange">
                    <div class="stat-icon">
                        <i class="mdi mdi-calendar-month"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">MONTHLY</div>
                        <div class="stat-value">{{ $stats['monthly'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Tasks -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-green">
                    <div class="stat-icon">
                        <i class="mdi mdi-play-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">ACTIVE</div>
                        <div class="stat-value">{{ $stats['active'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card task-card">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-12 d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="{{ route('tasks.index') }}" class="btn btn-secondary">
                                        <i class="mdi mdi-arrow-left me-2"></i> Back to Manual Tasks
                                    </a>
                                    
                                    <a href="{{ route('tasks.automatedCreate') }}" class="btn btn-danger ms-2">
                                        <i class="mdi mdi-plus-circle me-2"></i> Create Automated Task
                                    </a>
                                    
                                    @if($isAdmin)
                                    <button type="button" class="btn btn-info ms-2" id="bulk-actions-btn">
                                        <i class="mdi mdi-format-list-checks me-2"></i> Bulk Actions
                                    </button>
                                    @endif
                                </div>
                                
                                <div>
                                    <span id="selected-count" class="text-muted" style="display: none;">
                                        <strong id="count-number">0</strong> task(s) selected
                                    </span>
                                </div>
                            </div>
                        </div>

                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-check-circle me-2"></i>{{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <!-- Search/Filter Bar -->
                        <div class="row mb-3 p-3" style="background: #f8f9fa; border-radius: 8px;">
                            <div class="col-md-2 mb-2">
                                <label class="form-label fw-bold">Search</label>
                                <input type="text" id="filter-search" class="form-control form-control-sm" placeholder="Search all">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label fw-bold">Group</label>
                                <input type="text" id="filter-group" class="form-control form-control-sm" placeholder="Enter Group">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label fw-bold">Task</label>
                                <input type="text" id="filter-task" class="form-control form-control-sm" placeholder="Enter Task">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label fw-bold">Assignor</label>
                                <select id="filter-assignor" class="form-select form-select-sm">
                                    <option value="">Select assignor</option>
                                    @foreach($users ?? [] as $user)
                                        <option value="{{ $user->name }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label fw-bold">Assignee</label>
                                <select id="filter-assignee" class="form-select form-select-sm">
                                    <option value="">Select Assignee</option>
                                    @foreach($users ?? [] as $user)
                                        <option value="{{ $user->name }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-1 mb-2">
                                <label class="form-label fw-bold">Status</label>
                                <select id="filter-status" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="pending">Todo</option>
                                    <option value="in_progress">Working</option>
                                    <option value="archived">Archived</option>
                                    <option value="completed">Done</option>
                                    <option value="need_help">Need Help</option>
                                    <option value="need_approval">Need Approval</option>
                                    <option value="dependent">Dependent</option>
                                    <option value="approved">Approved</option>
                                    <option value="hold">Hold</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-1 mb-2">
                                <label class="form-label fw-bold">Priority</label>
                                <select id="filter-priority" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>

                        <div class="table-wrapper">
                            <div id="tasks-table"></div>
                        </div>

                    </div> <!-- end card-body-->
                </div> <!-- end card-->
            </div> <!-- end col -->
        </div>
        <!-- end row -->

    </div> <!-- container -->
@endsection

@section('modal')
<!-- View Task Modal -->
<div class="modal fade" id="viewTaskModal" tabindex="-1" aria-labelledby="viewTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title" id="viewTaskModalLabel">
                    <i class="mdi mdi-file-document-outline me-2"></i>Task Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="task-details">
                <!-- Task details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Mark as Done Modal -->
<div class="modal fade" id="doneModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-check-circle me-2"></i>Mark Task as Done
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3"><strong>How much time did you actually spend on this task?</strong></p>
                <div class="mb-3">
                    <label for="atc-input" class="form-label">Actual Time to Complete (ATC) in minutes:</label>
                    <input type="number" class="form-control" id="atc-input" min="1" placeholder="e.g., 45" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirm-done-btn">
                    <i class="mdi mdi-check me-1"></i>Mark as Done
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Status Change Modal (for all other statuses) -->
<div class="modal fade" id="statusChangeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-swap-horizontal me-2"></i>Change Task Status
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3"><strong>Please provide a reason for this status change:</strong></p>
                <div class="mb-3">
                    <label for="status-change-reason" class="form-label">Reason:</label>
                    <textarea class="form-control" id="status-change-reason" rows="4" placeholder="Why are you changing the status?" required></textarea>
                </div>
                <div class="alert alert-info">
                    <i class="mdi mdi-information me-2"></i>
                    Changing to: <strong id="new-status-label"></strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-status-change-btn">
                    <i class="mdi mdi-check me-1"></i>Confirm Change
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Links Modal -->
<div class="modal fade" id="linksModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-link-variant me-2"></i>Task Links
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="links-content">
                <!-- Links will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions Modal -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-format-list-checks me-2"></i>Bulk Actions
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">
                    <i class="mdi mdi-information-outline me-2"></i>
                    <strong><span id="bulk-selected-count">0</span> task(s) selected</strong>
                </p>

                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action" id="bulk-delete-btn">
                        <i class="mdi mdi-delete text-danger me-2"></i>
                        <strong>Delete Selected Tasks</strong>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" id="bulk-priority-btn">
                        <i class="mdi mdi-flag text-warning me-2"></i>
                        <strong>Change Priority</strong>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" id="bulk-tid-btn">
                        <i class="mdi mdi-calendar text-info me-2"></i>
                        <strong>Change TID Date</strong>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" id="bulk-assignee-btn">
                        <i class="mdi mdi-account-arrow-right text-success me-2"></i>
                        <strong>Change Assignee</strong>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" id="bulk-etc-btn">
                        <i class="mdi mdi-clock-outline text-primary me-2"></i>
                        <strong>Update ETC</strong>
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Update Form Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="bulkUpdateModalTitle">Bulk Update</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bulkUpdateModalBody">
                <!-- Dynamic content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-bulk-update-btn">
                    <i class="mdi mdi-check me-1"></i>Update
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSV Upload Modal -->
<div class="modal fade" id="csvUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-file-upload me-2"></i>Upload Tasks via CSV
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="csv-upload-form" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6 class="alert-heading"><i class="mdi mdi-information me-2"></i>CSV Format Required:</h6>
                        <p class="mb-1"><strong>Columns:</strong> Group, Task, Assignor, Assignee, Status, Priority, Image, Links</p>
                        <p class="mb-1"><strong>Status Options:</strong> Todo, Working, Archived, Done, Need Help, Need Approval, Dependent, Approved, Hold, Cancelled</p>
                        <p class="mb-0"><strong>Priority Options:</strong> Low, Normal, High, Urgent</p>
                        <p class="mb-0"><small class="text-muted">Note: Assignor and Assignee should match exact user names in the system</small></p>
                    </div>

                    <div class="mb-3">
                        <label for="csv-file" class="form-label fw-bold">Select CSV File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="csv-file" name="csv_file" accept=".csv,.txt" required>
                    </div>

                    <div class="mb-3">
                        <a href="{{ route('tasks.downloadTemplate') }}" class="btn btn-sm btn-outline-primary">
                            <i class="mdi mdi-download me-1"></i> Download Sample CSV Template
                        </a>
                    </div>

                    <div id="upload-progress" style="display: none;">
                        <div class="progress mb-2">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                        </div>
                        <p class="text-center text-muted"><i class="mdi mdi-loading mdi-spin me-2"></i>Uploading and processing tasks...</p>
                    </div>

                    <div id="upload-result" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="upload-csv-submit">
                        <i class="mdi mdi-upload me-1"></i> Upload & Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    
    <script>
        $(document).ready(function() {
            var selectedTasks = [];
            var bulkActionType = '';
            var isAdmin = {{ $isAdmin ? 'true' : 'false' }};
            var currentUserId = {{ Auth::id() }};
            var currentUserEmail = '{{ Auth::user()->email }}';

            // Initialize Tabulator
            var table = new Tabulator("#tasks-table", {
                selectable: isAdmin,
                ajaxURL: "{{ route('tasks.automatedData') }}",
                ajaxParams: {},
                ajaxContentType: "json",
                ajaxResponse: function(url, params, response) {
                    console.log('===== TASK MANAGER DEBUG =====');
                    console.log('Tasks loaded:', response.length);
                    console.log('Current User ID:', currentUserId);
                    console.log('Is Admin:', isAdmin);
                    console.log('==============================');
                    return response;
                },
                rowFormatter: function(row) {
                    var data = row.getData();
                    if (data.is_automate_task == 1 || data.is_automate_task === true) {
                        row.getElement().style.backgroundColor = "#fffbea";
                    }
                },
                layout: "fitData",
                pagination: true,
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100],
                responsiveLayout: false,
                placeholder: "No Tasks Found",
                height: "600px",
                layoutColumnsOnNewData: true,
                horizontalScroll: true,
                autoResize: true,
                columns: (function() {
                    var cols = [];
                    
                    // Add checkbox column only for admin
                    if (isAdmin) {
                        cols.push({
                            formatter: "rowSelection", 
                            titleFormatter: "rowSelection", 
                            hozAlign: "center", 
                            headerSort: false, 
                            width: 60,
                            cellClick: function(e, cell) {
                                cell.getRow().toggleSelect();
                            }
                        });
                    }
                    
                    // Column Order: FLAG RAISED, GROUP, TITLE, ASSIGNER, ASSIGNEE, ETC(MIN), STATUS, L1&L2, TL, VL, FORMS, FR, CL, TYPE, ACTION
                    
                    // FLAG RAISED
                    cols.push({
                        title: "FLAG RAISED?", 
                        field: "flag_raise",
                        width: 60,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value == 1 ? '<i class="mdi mdi-flag text-danger" style="font-size: 18px;"></i>' : '-';
                        }
                    });
                    
                    // GROUP
                    cols.push({
                        title: "GROUP", 
                        field: "group", 
                        minWidth: 120, 
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? '<span style="color: #6c757d;">' + value + '</span>' : '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // TITLE
                    cols.push({
                        title: "TITLE", 
                        field: "title", 
                        width: 200,
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var title = cell.getValue() || '';
                            var description = rowData.description || '';
                            var taskId = rowData.id;
                            var isOverdue = false;
                            
                            var startDate = rowData.start_date;
                            if (startDate && !['Done', 'Archived'].includes(rowData.status)) {
                                var tidDate = new Date(startDate);
                                var overdueDate = new Date(tidDate);
                                overdueDate.setDate(overdueDate.getDate() + 10);
                                isOverdue = overdueDate < new Date();
                            }
                            
                            var overdueIcon = isOverdue ? '<i class="mdi mdi-alert-circle text-danger me-1" style="font-size: 14px;"></i>' : '';
                            var htmlTitle = String(title).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                            var isLong = title.length > 30 || description;
                            var shortTitle = title.length > 32 ? htmlTitle.substring(0, 30) + '...' : htmlTitle;
                            var expandIcon = isLong ? '<i class="mdi mdi-information-outline text-primary expand-task-info" data-id="' + taskId + '" style="cursor: pointer; font-size: 16px; margin-left: 4px;"></i>' : '';
                            
                            return '<div style="display: flex; align-items: center; gap: 4px;">' + overdueIcon + '<span style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><strong>' + shortTitle + '</strong></span>' + (isLong ? '<span style="flex-shrink: 0;">' + expandIcon + '</span>' : '') + '</div>';
                        }
                    });
                    
                    // ASSIGNER
                    cols.push({
                        title: "ASSIGNER", 
                        field: "assignor_name", 
                        width: 150, 
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value && value !== '-' ? '<strong>' + value + '</strong>' : '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // ASSIGNEE
                    cols.push({
                        title: "ASSIGNEE", 
                        field: "assignee_name", 
                        width: 150, 
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value && value !== '-' ? '<strong>' + value + '</strong>' : '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // ETC (MIN)
                    cols.push({
                        title: "ETC (MIN)", 
                        field: "eta_time", 
                        width: 90,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? '<strong>' + value + '</strong>' : '-';
                        }
                    });
                    
                    // TID (Task Initiation Date) - BLUE COLOR
                    cols.push({
                        title: "TID", 
                        field: "start_date", 
                        width: 120,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value) {
                                var date = new Date(value);
                                var day = String(date.getDate()).padStart(2, '0');
                                var month = date.toLocaleString('default', { month: 'short' });
                                return '<span style="color: #0d6efd; font-weight: 600;">' + day + '-' + month + '</span>';
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // O DATE (Due Date) - Red=overdue, Orange=due soon, Green=future
                    cols.push({
                        title: "O DATE", 
                        field: "due_date", 
                        width: 120,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value) {
                                var dueDate = new Date(value);
                                var day = String(dueDate.getDate()).padStart(2, '0');
                                var month = dueDate.toLocaleString('default', { month: 'short' });
                                
                                var now = new Date();
                                now.setHours(0, 0, 0, 0);
                                var dueDateOnly = new Date(dueDate);
                                dueDateOnly.setHours(0, 0, 0, 0);
                                
                                var diffDays = Math.ceil((dueDateOnly - now) / (1000 * 60 * 60 * 24));
                                
                                var color = diffDays < 0 ? '#dc3545' : (diffDays <= 1 ? '#fd7e14' : '#28a745');
                                
                                return '<span style="color: ' + color + '; font-weight: 600;">' + day + '-' + month + '</span>';
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // ETC (Estimated Time)
                    cols.push({
                        title: "ETC", 
                        field: "eta_time", 
                        width: 90,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? value : '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // ATC (Actual Time)
                    cols.push({
                        title: "ATC", 
                        field: "etc_done", 
                        width: 90,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? '<strong style="color: #28a745;">' + value + '</strong>' : '<span style="color: #adb5bd;">0</span>';
                        }
                    });
                    
                    // C DAY (Days since completion)
                    cols.push({
                        title: "C DAY", 
                        field: "completion_day", 
                        width: 90,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var value = cell.getValue();
                            if (rowData.status === 'Done' && rowData.completion_date) {
                                try {
                                    var completed = new Date(rowData.completion_date);
                                    var now = new Date();
                                    var diffDays = Math.ceil((now - completed) / (1000 * 60 * 60 * 24));
                                    return '<strong style="color: #6610f2;">' + diffDays + '</strong>';
                                } catch(e) {
                                    return value ? '<strong style="color: #6610f2;">' + value + '</strong>' : '<span style="color: #adb5bd;">-</span>';
                                }
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // STATUS
                    cols.push({
                        title: "STATUS", 
                        field: "status", 
                        width: 180,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var value = cell.getValue();
                            var taskId = rowData.id;
                            var assignorId = rowData.assignor_id;
                            var assigneeId = rowData.assignee_id;
                            
                            // Check if user can update status
                            var canUpdateStatus = isAdmin || assignorId === currentUserId || assigneeId === currentUserId;
                            
                            var statuses = {
                                'Todo': {color: '#0dcaf0'},
                                'Working': {color: '#ffc107'},
                                'Archived': {color: '#6c757d'},
                                'Done': {color: '#28a745'},
                                'Need Help': {color: '#fd7e14'},
                                'Need Approval': {color: '#6610f2'},
                                'Dependent': {color: '#d63384'},
                                'Approved': {color: '#20c997'},
                                'Hold': {color: '#495057'},
                                'Rework': {color: '#f5576c'}
                            };
                            var currentStatus = statuses[value] || {color: '#6c757d'};
                            
                            // Always show as badge (read-only - no status changes)
                            return '<span style="background: ' + currentStatus.color + '; color: #fff; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;">' + value + '</span>';
                        }
                    });
                    
                    // L1 & L2
                    cols.push({
                        title: "L1 & L2", 
                        field: "link1",
                        width: 80,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var link1 = rowData.link1;
                            var link2 = rowData.link2;
                            if (link1 || link2) {
                                return '<i class="mdi mdi-link text-primary" style="font-size: 18px; cursor: pointer;" title="Has links"></i>';
                            }
                            return '-';
                        }
                    });
                    
                    // TL (Training Link)
                    cols.push({
                        title: "TL",
                        field: "link3",
                        width: 60,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? '<a href="' + value + '" target="_blank"><i class="mdi mdi-link text-info" style="font-size: 18px;"></i></a>' : '-';
                        }
                    });
                    
                    // VL (Video Link)
                    cols.push({
                        title: "VL",
                        field: "link4",
                        width: 60,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? '<a href="' + value + '" target="_blank"><i class="mdi mdi-link text-danger" style="font-size: 18px;"></i></a>' : '-';
                        }
                    });
                    
                    // FORMS
                    cols.push({
                        title: "FORMS",
                        field: "link5",
                        width: 80,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? '<a href="' + value + '" target="_blank"><i class="mdi mdi-link text-success" style="font-size: 18px;"></i></a>' : '-';
                        }
                    });
                    
                    // FR (Form Report)
                    cols.push({
                        title: "FR",
                        field: "link6",
                        width: 60,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? '<a href="' + value + '" target="_blank"><i class="mdi mdi-link text-warning" style="font-size: 18px;"></i></a>' : '-';
                        }
                    });
                    
                    // CL (Checklist)
                    cols.push({
                        title: "CL",
                        field: "link7",
                        width: 60,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? '<a href="' + value + '" target="_blank"><i class="mdi mdi-link text-secondary" style="font-size: 18px;"></i></a>' : '-';
                        }
                    });
                    
                    // TYPE (Schedule Type)
                    cols.push({
                        title: "TYPE", 
                        field: "schedule_type", 
                        width: 100,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue() || '-';
                            return '<strong style="color: #6c757d;">' + value + '</strong>';
                        }
                    });
                    
                    // PRIORITY (Dark colored backgrounds)
                    cols.push({
                        title: "PRIORITY", 
                        field: "priority", 
                        width: 110, 
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue() || 'Normal';
                            var styles = {
                                'Low': {bg: '#6c757d', color: '#fff'},
                                'Normal': {bg: '#0d6efd', color: '#fff'},
                                'High': {bg: '#fd7e14', color: '#fff'},
                                'Urgent': {bg: '#dc3545', color: '#fff'},
                                'Take your time': {bg: '#20c997', color: '#fff'}
                            };
                            var style = styles[value] || styles['Normal'];
                            return '<span style="background: ' + style.bg + '; color: ' + style.color + '; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase;">' + value + '</span>';
                        }
                    });
                    
                    // IMAGE
                    cols.push({
                        title: "IMAGE", 
                        field: "image", 
                        width: 90,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value) {
                                return `<a href="/uploads/tasks/${value}" target="_blank" title="View Image">
                                    <i class="mdi mdi-image text-info" style="font-size: 20px; cursor: pointer;"></i>
                                </a>`;
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // LINKS
                    cols.push({
                        title: "LINKS", 
                        field: "id", 
                        width: 90,
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var hasLinks = rowData.training_link || rowData.video_link || rowData.form_link || 
                                          rowData.form_report_link || rowData.checklist_link || rowData.pl || rowData.process;
                            
                            if (hasLinks) {
                                return `<button class="btn btn-sm btn-link view-links" data-id="${cell.getValue()}" title="View Links">
                                    <i class="mdi mdi-link-variant text-primary" style="font-size: 20px; cursor: pointer;"></i>
                                </button>`;
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // ACTION
                    cols.push({
                        title: "ACTION", 
                        field: "id", 
                        width: 120,
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            var id = cell.getValue();
                            return `
                                <button class="action-btn-icon action-btn-edit edit-automated-task" data-id="${id}" title="Edit" style="background: #0dcaf0; color: white; border: none; padding: 8px 10px; border-radius: 6px; cursor: pointer; margin: 0 2px;">
                                    <i class="mdi mdi-pencil"></i>
                                </button>
                                <button class="action-btn-icon action-btn-delete delete-automated-task" data-id="${id}" title="Delete" style="background: #fd7e14; color: white; border: none; padding: 8px 10px; border-radius: 6px; cursor: pointer; margin: 0 2px;">
                                    <i class="mdi mdi-delete"></i>
                                </button>
                            `;
                        }
                    });
                    
                    return cols;
                })(),
            });

            // Filter functionality
            $('#filter-search').on('keyup', function() {
                var value = $(this).val();
                table.setFilter([
                    {field:"title", type:"like", value:value},
                    {field:"group", type:"like", value:value},
                    {field:"assignor.name", type:"like", value:value},
                    {field:"assignee.name", type:"like", value:value}
                ], "or");
            });

            $('#filter-group').on('keyup', function() {
                var value = $(this).val();
                if (value) {
                    table.setFilter("group", "like", value);
                } else {
                    table.clearFilter("group");
                }
            });

            $('#filter-task').on('keyup', function() {
                var value = $(this).val();
                if (value) {
                    table.setFilter("title", "like", value);
                } else {
                    table.clearFilter("title");
                }
            });

            $('#filter-assignor').on('change', function() {
                var value = $(this).val();
                if (value) {
                    table.setFilter("assignor.name", "=", value);
                } else {
                    table.clearFilter("assignor.name");
                }
            });

            $('#filter-assignee').on('change', function() {
                var value = $(this).val();
                if (value) {
                    table.setFilter("assignee.name", "=", value);
                } else {
                    table.clearFilter("assignee.name");
                }
            });

            $('#filter-status').on('change', function() {
                var value = $(this).val();
                if (value) {
                    table.setFilter("status", "=", value);
                } else {
                    table.clearFilter("status");
                }
            });

            $('#filter-priority').on('change', function() {
                var value = $(this).val();
                if (value) {
                    table.setFilter("priority", "=", value);
                } else {
                    table.clearFilter("priority");
                }
            });

            // Handle Row Selection
            table.on("rowSelectionChanged", function(data, rows) {
                selectedTasks = data.map(task => task.id);
                var count = selectedTasks.length;
                
                if (count > 0) {
                    $('#selected-count').show();
                    $('#count-number').text(count);
                    $('#bulk-actions-btn').removeClass('btn-info').addClass('btn-success');
                } else {
                    $('#selected-count').hide();
                    $('#bulk-actions-btn').removeClass('btn-success').addClass('btn-info');
                }
            });

            // Show CSV Upload Modal
            $('#upload-csv-btn').on('click', function() {
                $('#csvUploadModal').modal('show');
            });

            // Handle CSV Upload
            $('#csv-upload-form').on('submit', function(e) {
                e.preventDefault();
                
                var fileInput = $('#csv-file')[0];
                if (!fileInput.files.length) {
                    alert('Please select a CSV file');
                    return;
                }
                
                var formData = new FormData();
                formData.append('csv_file', fileInput.files[0]);
                formData.append('_token', '{{ csrf_token() }}');
                
                // Show progress
                $('#upload-progress').show();
                $('#upload-csv-submit').prop('disabled', true);
                $('#upload-result').hide();
                
                $.ajax({
                    url: '/tasks/import-csv',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#upload-progress').hide();
                        $('#upload-csv-submit').prop('disabled', false);
                        
                        var resultHtml = `
                            <div class="alert alert-success">
                                <h6 class="alert-heading"><i class="mdi mdi-check-circle me-2"></i>Import Successful!</h6>
                                <p class="mb-0">✅ ${response.imported} task(s) imported successfully</p>
                                ${response.skipped > 0 ? '<p class="mb-0">⚠️ ' + response.skipped + ' row(s) skipped due to errors</p>' : ''}
                            </div>
                        `;
                        
                        $('#upload-result').html(resultHtml).show();
                        
                            setTimeout(function() {
                                $('#csvUploadModal').modal('hide');
                                $('#csv-upload-form')[0].reset();
                                $('#upload-result').hide();
                                table.replaceData(); // Refresh table data
                            }, 2000);
                    },
                    error: function(xhr) {
                        $('#upload-progress').hide();
                        $('#upload-csv-submit').prop('disabled', false);
                        
                        var errorMsg = xhr.responseJSON?.message || 'Upload failed. Please check your CSV format.';
                        var resultHtml = `
                            <div class="alert alert-danger">
                                <h6 class="alert-heading"><i class="mdi mdi-alert-circle me-2"></i>Import Failed</h6>
                                <p class="mb-0">${errorMsg}</p>
                            </div>
                        `;
                        $('#upload-result').html(resultHtml).show();
                    }
                });
            });

            // Show Bulk Actions Modal
            $('#bulk-actions-btn').on('click', function() {
                if (selectedTasks.length === 0) {
                    // Show error notification
                    var alertHtml = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-alert-circle me-2"></i><strong>Error!</strong> Please select at least one task to perform bulk actions.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                    $('.task-card .card-body').prepend(alertHtml);
                    
                    // Auto dismiss after 4 seconds
                    setTimeout(function() {
                        $('.alert-danger').fadeOut();
                    }, 4000);
                    
                    return;
                }
                
                $('#bulk-selected-count').text(selectedTasks.length);
                $('#bulkActionsModal').modal('show');
            });

            // Bulk Delete (no confirmation)
            $('#bulk-delete-btn').on('click', function(e) {
                e.preventDefault();
                bulkUpdate('delete', {});
            });

            // Bulk Change Priority
            $('#bulk-priority-btn').on('click', function(e) {
                e.preventDefault();
                bulkActionType = 'priority';
                var html = `
                    <p class="mb-3"><strong>Select new priority for ${selectedTasks.length} task(s):</strong></p>
                    <div class="mb-3">
                        <label for="bulk-priority-select" class="form-label">Priority:</label>
                        <select class="form-select" id="bulk-priority-select">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                `;
                showBulkUpdateForm('Change Priority', html);
            });

            // Bulk Change TID
            $('#bulk-tid-btn').on('click', function(e) {
                e.preventDefault();
                bulkActionType = 'tid';
                var html = `
                    <p class="mb-3"><strong>Set new TID date for ${selectedTasks.length} task(s):</strong></p>
                    <div class="mb-3">
                        <label for="bulk-tid-input" class="form-label">TID (Task Initiation Date):</label>
                        <input type="datetime-local" class="form-control" id="bulk-tid-input" required>
                    </div>
                `;
                showBulkUpdateForm('Change TID Date', html);
            });

            // Bulk Change Assignee
            $('#bulk-assignee-btn').on('click', function(e) {
                e.preventDefault();
                bulkActionType = 'assignee';
                
                // Fetch users via AJAX
                $.ajax({
                    url: '/tasks/create',
                    type: 'GET',
                    success: function(response) {
                        var usersSelect = '<option value="">Please Select</option>';
                        // This is a workaround - we'll need to create an API endpoint
                        // For now, let's create a simple input
                        var html = `
                            <p class="mb-3"><strong>Change assignee for ${selectedTasks.length} task(s):</strong></p>
                            <div class="mb-3">
                                <label for="bulk-assignee-select" class="form-label">Assignee:</label>
                                <select class="form-select" id="bulk-assignee-select">
                                    <option value="">Loading users...</option>
                                </select>
                            </div>
                        `;
                        showBulkUpdateForm('Change Assignee', html);
                        loadUsersForBulk();
                    }
                });
            });

            // Bulk Update ETC
            $('#bulk-etc-btn').on('click', function(e) {
                e.preventDefault();
                bulkActionType = 'etc';
                var html = `
                    <p class="mb-3"><strong>Update ETC for ${selectedTasks.length} task(s):</strong></p>
                    <div class="mb-3">
                        <label for="bulk-etc-input" class="form-label">ETC (Minutes):</label>
                        <input type="number" class="form-control" id="bulk-etc-input" min="1" placeholder="e.g., 30" required>
                    </div>
                `;
                showBulkUpdateForm('Update ETC', html);
            });

            // Show Bulk Update Form
            function showBulkUpdateForm(title, content) {
                $('#bulkActionsModal').modal('hide');
                $('#bulkUpdateModalTitle').text(title);
                $('#bulkUpdateModalBody').html(content);
                $('#bulkUpdateModal').modal('show');
            }

            // Confirm Bulk Update
            $('#confirm-bulk-update-btn').on('click', function() {
                var data = {};
                
                switch(bulkActionType) {
                    case 'priority':
                        data.priority = $('#bulk-priority-select').val();
                        break;
                    case 'tid':
                        data.tid = $('#bulk-tid-input').val();
                        if (!data.tid) {
                            alert('Please select a date and time');
                            return;
                        }
                        break;
                    case 'assignee':
                        data.assignee_id = $('#bulk-assignee-select').val();
                        if (!data.assignee_id) {
                            alert('Please select an assignee');
                            return;
                        }
                        break;
                    case 'etc':
                        data.etc_minutes = $('#bulk-etc-input').val();
                        if (!data.etc_minutes || data.etc_minutes <= 0) {
                            alert('Please enter a valid ETC value');
                            return;
                        }
                        break;
                }
                
                bulkUpdate(bulkActionType, data);
            });

            // Bulk Update Function
            function bulkUpdate(action, data) {
                $.ajax({
                    url: '/tasks/bulk-update',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        action: action,
                        task_ids: selectedTasks,
                        ...data
                    },
                    success: function(response) {
                        $('#bulkUpdateModal').modal('hide');
                        $('#bulkActionsModal').modal('hide');
                        table.deselectRow();
                        table.replaceData();
                        
                        var alertHtml = `
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-check-circle me-2"></i>${response.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                        $('.task-card .card-body').prepend(alertHtml);
                        
                        setTimeout(function() {
                            $('.alert').fadeOut(function() { $(this).remove(); });
                        }, 3000);
                    },
                    error: function(xhr) {
                        alert('Error: ' + (xhr.responseJSON?.message || 'Something went wrong'));
                    }
                });
            }

            // Load Users for Bulk Assignee Change
            function loadUsersForBulk() {
                $.ajax({
                    url: '/tasks/users-list',
                    type: 'GET',
                    success: function(users) {
                        var options = '<option value="">Please Select</option>';
                        users.forEach(function(user) {
                            options += `<option value="${user.id}">${user.name}</option>`;
                        });
                        $('#bulk-assignee-select').html(options);
                    }
                });
            }

            // View Links
            $(document).on('click', '.view-links', function(e) {
                e.preventDefault();
                var taskId = $(this).data('id');
                $.ajax({
                    url: '/tasks/' + taskId,
                    type: 'GET',
                    success: function(response) {
                        var html = '<div class="list-group">';
                        
                        if (response.training_link) {
                            html += `
                                <a href="${response.training_link}" target="_blank" class="list-group-item list-group-item-action">
                                    <i class="mdi mdi-school text-primary me-2"></i>
                                    <strong>Training Link:</strong> ${response.training_link}
                                </a>`;
                        }
                        if (response.video_link) {
                            html += `
                                <a href="${response.video_link}" target="_blank" class="list-group-item list-group-item-action">
                                    <i class="mdi mdi-video text-danger me-2"></i>
                                    <strong>Video Link:</strong> ${response.video_link}
                                </a>`;
                        }
                        if (response.form_link) {
                            html += `
                                <a href="${response.form_link}" target="_blank" class="list-group-item list-group-item-action">
                                    <i class="mdi mdi-file-document text-success me-2"></i>
                                    <strong>Form Link:</strong> ${response.form_link}
                                </a>`;
                        }
                        if (response.form_report_link) {
                            html += `
                                <a href="${response.form_report_link}" target="_blank" class="list-group-item list-group-item-action">
                                    <i class="mdi mdi-file-chart text-info me-2"></i>
                                    <strong>Form Report Link:</strong> ${response.form_report_link}
                                </a>`;
                        }
                        if (response.checklist_link) {
                            html += `
                                <a href="${response.checklist_link}" target="_blank" class="list-group-item list-group-item-action">
                                    <i class="mdi mdi-checkbox-marked-outline text-warning me-2"></i>
                                    <strong>Checklist Link:</strong> ${response.checklist_link}
                                </a>`;
                        }
                        if (response.pl) {
                            html += `
                                <div class="list-group-item">
                                    <i class="mdi mdi-folder text-secondary me-2"></i>
                                    <strong>PL:</strong> ${response.pl}
                                </div>`;
                        }
                        if (response.process) {
                            html += `
                                <div class="list-group-item">
                                    <i class="mdi mdi-cog text-dark me-2"></i>
                                    <strong>Process:</strong> ${response.process}
                                </div>`;
                        }
                        
                        html += '</div>';
                        
                        if (html === '<div class="list-group"></div>') {
                            html = '<p class="text-muted">No links available for this task.</p>';
                        }
                        
                        $('#links-content').html(html);
                        $('#linksModal').modal('show');
                    }
                });
            });

            // Edit Automated Task
            $(document).on('click', '.edit-automated-task', function(e) {
                e.preventDefault();
                var taskId = $(this).data('id');
                window.location.href = '/tasks/automated/' + taskId + '/edit';
            });

            // Delete Automated Task (no confirmation)
            $(document).on('click', '.delete-automated-task', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var taskId = $(this).data('id');
                
                $.ajax({
                    url: '/tasks/automated/' + taskId,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        // Update table instantly - no reload
                        table.replaceData();
                        
                        // Show success notification
                        var alertHtml = `
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-check-circle me-2"></i>${response.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        `;
                        $('.task-card .card-body .alert').remove();
                        $('.task-card .card-body').prepend(alertHtml);
                        
                        setTimeout(function() {
                            $('.alert').fadeOut(function() { $(this).remove(); });
                        }, 3000);
                    },
                    error: function(xhr) {
                        var alertHtml = `
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-alert-circle me-2"></i>Error deleting task
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        `;
                        $('.task-card .card-body .alert').remove();
                        $('.task-card .card-body').prepend(alertHtml);
                    }
                });
            });

            // View Automated Task Info
            $(document).on('click', '.view-automated-info', function(e) {
                e.preventDefault();
                var taskData = $(this).closest('tr').data() || {};
                var taskId = $(this).data('id');
                
                // Get task data from table row
                var row = table.getRow(taskId);
                if (row) {
                    var data = row.getData();
                    var html = `
                        <div class="alert alert-warning">
                            <strong><i class="mdi mdi-robot me-2"></i>This is an automated system task</strong>
                        </div>
                        <table class="table table-borderless">
                            <tr>
                                <th width="200">Title:</th>
                                <td><strong>${data.title || '-'}</strong></td>
                            </tr>
                            <tr>
                                <th>Group:</th>
                                <td>${data.group || '-'}</td>
                            </tr>
                            <tr>
                                <th>Schedule Type:</th>
                                <td><strong>${data.schedule_type || '-'}</strong></td>
                            </tr>
                            <tr>
                                <th>Schedule Time:</th>
                                <td>${data.schedule_time || '-'}</td>
                            </tr>
                            <tr>
                                <th>Assignor:</th>
                                <td>${data.assignor_name || '-'}</td>
                            </tr>
                            <tr>
                                <th>Assignee:</th>
                                <td>${data.assignee_name || '-'}</td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>${data.status || '-'}</td>
                            </tr>
                            <tr>
                                <th>Priority:</th>
                                <td>${data.priority || '-'}</td>
                            </tr>
                        </table>
                    `;
                    $('#task-details').html(html);
                    $('#viewTaskModal').modal('show');
                }
            });

            // View Task (for manual tasks - disabled on automated page)
            $(document).on('click', '.view-task', function() {
                var taskId = $(this).data('id');
                $.ajax({
                    url: '/tasks/' + taskId,
                    type: 'GET',
                    success: function(response) {
                        var html = `
                            <div style="padding: 10px;">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="200" style="color: #6c757d; font-weight: 600;">Title:</th>
                                        <td><strong>${response.title}</strong></td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Description:</th>
                                        <td>${response.description || '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Group:</th>
                                        <td>${response.group || '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Priority:</th>
                                        <td><span class="priority-badge priority-${response.priority}">${response.priority}</span></td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Assignor:</th>
                                        <td>${response.assignor ? response.assignor.name : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Assignee:</th>
                                        <td>${response.assignee ? response.assignee.name : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Split Tasks:</th>
                                        <td>${response.split_tasks ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Flag Raise:</th>
                                        <td>${response.flag_raise ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Status:</th>
                                        <td><span class="status-badge status-${response.status}">${response.status}</span></td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">ETC (Minutes):</th>
                                        <td>${response.etc_minutes ? response.etc_minutes + ' min' : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">TID:</th>
                                        <td>${response.tid || '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">L1:</th>
                                        <td>${response.l1 || '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">L2:</th>
                                        <td>${response.l2 || '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Training Link:</th>
                                        <td>${response.training_link ? '<a href="' + response.training_link + '" target="_blank" style="color: #0d6efd;">' + response.training_link + '</a>' : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Video Link:</th>
                                        <td>${response.video_link ? '<a href="' + response.video_link + '" target="_blank" style="color: #0d6efd;">' + response.video_link + '</a>' : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Form Link:</th>
                                        <td>${response.form_link ? '<a href="' + response.form_link + '" target="_blank" style="color: #0d6efd;">' + response.form_link + '</a>' : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Form Report Link:</th>
                                        <td>${response.form_report_link ? '<a href="' + response.form_report_link + '" target="_blank" style="color: #0d6efd;">' + response.form_report_link + '</a>' : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Checklist Link:</th>
                                        <td>${response.checklist_link ? '<a href="' + response.checklist_link + '" target="_blank" style="color: #0d6efd;">' + response.checklist_link + '</a>' : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">PL:</th>
                                        <td>${response.pl || '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Process:</th>
                                        <td>${response.process || '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    ${response.image ? '<tr><th style="color: #6c757d; font-weight: 600;">Image:</th><td><img src="/uploads/tasks/' + response.image + '" class="img-thumbnail" style="max-width: 300px; border-radius: 8px;"></td></tr>' : ''}
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Created At:</th>
                                        <td>${response.created_at}</td>
                                    </tr>
                                </table>
                            </div>
                        `;
                        $('#task-details').html(html);
                        $('#viewTaskModal').modal('show');
                    }
                });
            });

            // Edit Task
            $(document).on('click', '.edit-task', function() {
                var taskId = $(this).data('id');
                window.location.href = '/tasks/' + taskId + '/edit';
            });

            // Delete Task
            $(document).on('click', '.delete-task', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var taskId = $(this).data('id');
                
                $.ajax({
                    url: '/tasks/' + taskId,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        table.replaceData();
                            
                            // Show success message
                            var alertHtml = `
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-circle me-2"></i>${response.message}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `;
                            $('.task-card .card-body').prepend(alertHtml);
                            
                            // Auto dismiss after 3 seconds
                            setTimeout(function() {
                                $('.alert').fadeOut(function() { $(this).remove(); });
                            }, 3000);
                        },
                    error: function(xhr, status, error) {
                        console.error('Delete failed:', xhr.responseJSON);
                        var errorMsg = xhr.responseJSON?.message || 'Failed to delete task. You may not have permission.';
                        alert('Error: ' + errorMsg);
                    }
                });
            });

            // Handle Status Change
            var currentTaskId = null;
            var previousStatus = null;
            var newStatusValue = null;
            
            var statusLabels = {
                'pending': 'Todo',
                'in_progress': 'Working',
                'archived': 'Archived',
                'completed': 'Done',
                'need_help': 'Need Help',
                'need_approval': 'Need Approval',
                'dependent': 'Dependent',
                'approved': 'Approved',
                'hold': 'Hold',
                'rework': 'Rework',
                'cancelled': 'Cancelled'
            };
            
            $(document).on('change', '.status-select', function() {
                var select = $(this);
                newStatusValue = select.val();
                currentTaskId = select.data('task-id');
                previousStatus = select.data('current-status');
                
                if (newStatusValue === 'completed') {
                    // Show Done Modal (ask for ATC)
                    $('#doneModal').modal('show');
                    select.val(previousStatus);
                } else {
                    // Show Status Change Modal (ask for reason)
                    var statusLabel = statusLabels[newStatusValue] || newStatusValue;
                    $('#new-status-label').text(statusLabel);
                    $('#statusChangeModal').modal('show');
                    select.val(previousStatus);
                }
            });

            // Confirm Done
            $('#confirm-done-btn').on('click', function() {
                var atc = $('#atc-input').val();
                if (!atc || atc <= 0) {
                    alert('Please enter the actual time spent on this task.');
                    return;
                }
                
                updateTaskStatus(currentTaskId, 'Done', atc, null);
                $('#doneModal').modal('hide');
                $('#atc-input').val('');
            });

            // Confirm Status Change (Rework, etc.) — send backend values (Rework, Todo, …)
            $('#confirm-status-change-btn').on('click', function() {
                var reason = $('#status-change-reason').val().trim();
                if (!reason) {
                    alert('Please provide a reason for this status change.');
                    return;
                }
                
                var finalStatus = statusLabels[newStatusValue] || newStatusValue;
                updateTaskStatus(currentTaskId, finalStatus, null, reason);
                $('#statusChangeModal').modal('hide');
                $('#status-change-reason').val('');
            });

            // Update Task Status Function
            function updateTaskStatus(taskId, status, atc = null, reworkReason = null) {
                var data = {
                    _token: '{{ csrf_token() }}',
                    status: status
                };
                
                if (atc) {
                    data.atc = atc;
                }
                
                if (reworkReason) {
                    data.rework_reason = reworkReason;
                }
                
                $.ajax({
                    url: '/tasks/' + taskId + '/update-status',
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        table.replaceData();
                        
                        // Show success message
                        var message = reworkReason ? 'Task marked for rework' : 'Status updated successfully!';
                        var alertHtml = `
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-check-circle me-2"></i>${message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                        $('.task-card .card-body').prepend(alertHtml);
                        
                        // Auto dismiss after 3 seconds
                        setTimeout(function() {
                            $('.alert').fadeOut();
                        }, 3000);
                    },
                    error: function(xhr) {
                        alert('Error updating status. Please try again.');
                        table.replaceData();
                    }
                });
            }
        });
    </script>
@endsection
