@extends('layouts.vertical', ['title' => 'Task Deletion Record', 'sidenav' => 'condensed'])

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

        /* Red theme for deleted tasks */
        .stat-card-red {
            border-left-color: #dc3545;
        }
        .stat-card-red .stat-icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card-orange {
            border-left-color: #fd7e14;
        }
        .stat-card-orange .stat-icon {
            background: linear-gradient(135deg, #fa8305 0%, #ff6b6b 100%);
        }

        .stat-card-purple {
            border-left-color: #6f42c1;
        }
        .stat-card-purple .stat-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card-pink {
            border-left-color: #e83e8c;
        }
        .stat-card-pink .stat-icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        /* Table styling */
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

        .tabulator-row {
            border-bottom: 1px solid #e9ecef !important;
            background: white !important;
        }

        .tabulator-row:hover {
            background-color: #fff5f5 !important;
        }

        .task-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .page-title {
            font-weight: 700;
            color: #2c3e50;
            font-size: 24px;
        }

        /* Priority Badges */
        .priority-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-low {
            background-color: #6c757d;
            color: #fff;
        }

        .priority-normal {
            background-color: #0d6efd;
            color: #fff;
        }

        .priority-high {
            background-color: #fd7e14;
            color: #fff;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        
        <!-- Page Title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('tasks.index') }}">Task Manager</a></li>
                            <li class="breadcrumb-item active">Task Deletion Record</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Task Deletion Record</h4>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-red">
                    <div class="stat-icon">
                        <i class="mdi mdi-delete-forever"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">TOTAL DELETED</div>
                        <div class="stat-value">{{ $stats['total'] }}</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-orange">
                    <div class="stat-icon">
                        <i class="mdi mdi-calendar-month"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">THIS MONTH</div>
                        <div class="stat-value">{{ $stats['this_month'] }}</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-purple">
                    <div class="stat-icon">
                        <i class="mdi mdi-calendar-week"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">THIS WEEK</div>
                        <div class="stat-value">{{ $stats['this_week'] }}</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-pink">
                    <div class="stat-icon">
                        <i class="mdi mdi-calendar-today"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">TODAY</div>
                        <div class="stat-value">{{ $stats['today'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deleted Tasks Table -->
        <div class="row">
            <div class="col-12">
                <div class="card task-card">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-12 d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="{{ route('tasks.index') }}" class="btn btn-primary">
                                        <i class="mdi mdi-arrow-left me-2"></i> Back to Tasks
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Search/Filter Bar -->
                        <div class="row mb-3 p-3" style="background: #f8f9fa; border-radius: 8px;">
                            <div class="col-md-3 mb-2">
                                <label class="form-label fw-bold">Search</label>
                                <input type="text" id="filter-search" class="form-control form-control-sm" placeholder="Search all">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label fw-bold">Deleted By</label>
                                <input type="text" id="filter-deleted-by" class="form-control form-control-sm" placeholder="Name">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label fw-bold">Assignor</label>
                                <input type="text" id="filter-assignor" class="form-control form-control-sm" placeholder="Name">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label fw-bold">Assignee</label>
                                <input type="text" id="filter-assignee" class="form-control form-control-sm" placeholder="Name">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label fw-bold">Priority</label>
                                <select id="filter-priority" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>

                        <div id="deleted-tasks-table"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize Tabulator
            var table = new Tabulator("#deleted-tasks-table", {
                ajaxURL: "{{ route('tasks.deletedData') }}",
                ajaxContentType: "json",
                layout: "fitData",
                pagination: true,
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100],
                responsiveLayout: false,
                placeholder: "No Deleted Tasks Found",
                height: "600px",
                layoutColumnsOnNewData: true,
                columns: [
                    {
                        title: "ID", 
                        field: "original_task_id", 
                        width: 80,
                        formatter: function(cell) {
                            return '<strong>#' + cell.getValue() + '</strong>';
                        }
                    },
                    {
                        title: "TASK", 
                        field: "title", 
                        width: 250,
                        formatter: function(cell) {
                            var title = cell.getValue() || '';
                            return '<div style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal; line-height: 1.4;">' + 
                                   '<strong>' + title + '</strong>' + 
                                   '</div>';
                        }
                    },
                    {
                        title: "GROUP", 
                        field: "group", 
                        width: 120,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value || '<span style="color: #adb5bd;">-</span>';
                        }
                    },
                    {
                        title: "ASSIGNOR", 
                        field: "assignor_name", 
                        width: 120,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value && value !== '-') {
                                var firstName = value.trim().split(' ')[0];
                                return '<strong>' + firstName + '</strong>';
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    },
                    {
                        title: "ASSIGNEE", 
                        field: "assignee_name", 
                        width: 120,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value && value !== '-') {
                                var firstName = value.trim().split(' ')[0];
                                return '<strong>' + firstName + '</strong>';
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    },
                    {
                        title: "ETC", 
                        field: "eta_time", 
                        width: 80,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? value + ' min' : '<span style="color: #adb5bd;">-</span>';
                        }
                    },
                    {
                        title: "ATC", 
                        field: "etc_done", 
                        width: 80,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? '<strong style="color: #28a745;">' + value + ' min</strong>' : '<span style="color: #adb5bd;">0</span>';
                        }
                    },
                    {
                        title: "PRIORITY", 
                        field: "priority", 
                        width: 110,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue() || 'Normal';
                            var styles = {
                                'low': {bg: '#6c757d', color: '#fff'},
                                'normal': {bg: '#0d6efd', color: '#fff'},
                                'high': {bg: '#fd7e14', color: '#fff'}
                            };
                            var style = styles[value.toLowerCase()] || styles['normal'];
                            return '<span class="priority-badge" style="background: ' + style.bg + '; color: ' + style.color + ';">' + value.toUpperCase() + '</span>';
                        }
                    },
                    {
                        title: "STATUS", 
                        field: "status", 
                        width: 100,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue() || '-';
                            return '<span style="font-weight: 600; color: #6c757d;">' + value + '</span>';
                        }
                    },
                    {
                        title: "DELETED BY", 
                        field: "deleted_by_name", 
                        width: 120,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value) {
                                var firstName = value.trim().split(' ')[0];
                                return '<strong style="color: #dc3545;">' + firstName + '</strong>';
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    },
                    {
                        title: "DELETED DATE", 
                        field: "deleted_at", 
                        width: 150,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value) {
                                var date = new Date(value);
                                return date.toLocaleDateString() + '<br><small style="color: #6c757d;">' + date.toLocaleTimeString() + '</small>';
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    },
                ],
            });

            // Filter functionality
            function applyFilters() {
                table.clearFilter();
                var filters = [];
                
                var searchValue = $('#filter-search').val();
                if (searchValue) {
                    filters.push([
                        {field:"title", type:"like", value:searchValue},
                        {field:"group", type:"like", value:searchValue},
                        {field:"assignor_name", type:"like", value:searchValue},
                        {field:"assignee_name", type:"like", value:searchValue},
                        {field:"deleted_by_name", type:"like", value:searchValue}
                    ]);
                }
                
                var deletedByValue = $('#filter-deleted-by').val();
                if (deletedByValue) {
                    filters.push({field:"deleted_by_name", type:"like", value:deletedByValue});
                }
                
                var assignorValue = $('#filter-assignor').val();
                if (assignorValue) {
                    filters.push({field:"assignor_name", type:"like", value:assignorValue});
                }
                
                var assigneeValue = $('#filter-assignee').val();
                if (assigneeValue) {
                    filters.push({field:"assignee_name", type:"like", value:assigneeValue});
                }
                
                var priorityValue = $('#filter-priority').val();
                if (priorityValue) {
                    filters.push({field:"priority", type:"=", value:priorityValue});
                }
                
                if (filters.length > 0) {
                    table.setFilter(filters);
                }
            }

            $('#filter-search').on('keyup', applyFilters);
            $('#filter-deleted-by').on('keyup', applyFilters);
            $('#filter-assignor').on('keyup', applyFilters);
            $('#filter-assignee').on('keyup', applyFilters);
            $('#filter-priority').on('change', applyFilters);
        });
    </script>
@endsection
