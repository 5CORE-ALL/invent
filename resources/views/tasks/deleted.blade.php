@extends('layouts.vertical', ['title' => 'Archived Tasks', 'sidenav' => 'condensed'])

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

        /* Red theme for archived tasks */
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

        /* Missed status row - light yellow background */
        .deleted-row-missed,
        .deleted-row-missed .tabulator-cell {
            background-color: #fffde7 !important;
        }
        .deleted-row-missed:hover,
        .deleted-row-missed:hover .tabulator-cell {
            background-color: #fff9c4 !important;
        }

        .stat-card-pink .stat-icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card-teal {
            border-left-color: #20c997;
        }
        .stat-card-teal .stat-icon {
            background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%);
        }

        .stat-card-red-missed {
            border-left-color: #dc3545;
        }
        .stat-card-red-missed .stat-icon {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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
                            <li class="breadcrumb-item active">Archived Tasks</li>
                        </ol>
                    </div>
                    <h4 class="page-title">
                        Archived Tasks
                        @if(!empty($selectedUserName))
                            <span class="badge bg-info ms-2" style="font-size: 0.75rem; font-weight: 600;">
                                <i class="mdi mdi-account me-1"></i>Filtered: {{ $selectedUserName }}
                                <button type="button" class="btn-close btn-close-white ms-2" style="font-size: 0.6rem;" onclick="clearUserFilter()" title="Clear filter"></button>
                            </span>
                        @endif
                    </h4>
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

            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-teal">
                    <div class="stat-icon">
                        <i class="mdi mdi-clock-outline"></i>
                    </div>
                    <div class="stat-content d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">TAT</div>
                            <div class="stat-value">{{ $stats['tat_avg_30'] !== null ? number_format($stats['tat_avg_30'], 1) : '-' }}</div>
                            <div class="stat-label mt-1" style="font-size: 10px; opacity: 0.9;">Avg last 30 days (days)</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-light border-0 p-2 rounded" id="tat-chart-eye-btn" title="View TAT trend">
                            <i class="mdi mdi-eye" style="font-size: 1.5rem; color: #20c997;"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-red-missed">
                    <div class="stat-icon">
                        <i class="mdi mdi-alert-circle"></i>
                    </div>
                    <div class="stat-content d-flex align-items-center justify-content-between">
                        <div>
                            <div class="stat-label">MISSED</div>
                            <div class="stat-value">{{ $stats['missed_count_30'] ?? 0 }}</div>
                            <div class="stat-label mt-1" style="font-size: 10px; opacity: 0.9;">Last 30 days</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-light border-0 p-2 rounded" id="missed-chart-eye-btn" title="View Missed trend">
                            <i class="mdi mdi-eye" style="font-size: 1.5rem; color: #dc3545;"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAT Line Graph Modal -->
        <div class="modal fade" id="tatChartModal" tabindex="-1" aria-labelledby="tatChartModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tatChartModalLabel">
                            <i class="mdi mdi-chart-line me-2"></i>TAT – Last 30 Days (Avg days)
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div style="height: 320px;">
                            <canvas id="tat-line-chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Missed Line Graph Modal -->
        <div class="modal fade" id="missedChartModal" tabindex="-1" aria-labelledby="missedChartModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="missedChartModalLabel">
                            <i class="mdi mdi-chart-line me-2"></i>Missed Tasks – Last 30 Days (Count)
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div style="height: 320px;">
                            <canvas id="missed-line-chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Archived Tasks Table -->
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <script>
        var tatChartData = @json($tatChartData ?? []);
        var tatLineChart = null;
        var missedChartData = @json($missedChartData ?? []);
        var missedLineChart = null;

        // Clear user filter and reload page
        function clearUserFilter() {
            $.ajax({
                url: '{{ route("tasks.setSelectedUser") }}',
                method: 'POST',
                data: {
                    user_name: '',
                    _token: '{{ csrf_token() }}'
                },
                success: function() {
                    window.location.reload();
                }
            });
        }

        $(document).ready(function() {
            // TAT badge eye icon: show line graph modal
            $('#tat-chart-eye-btn').on('click', function() {
                $('#tatChartModal').modal('show');
                setTimeout(function() { renderTatLineChart(); }, 300);
            });
            $('#tatChartModal').on('hidden.bs.modal', function() {
                if (tatLineChart) {
                    tatLineChart.destroy();
                    tatLineChart = null;
                }
            });

            // Missed badge eye icon: show line graph modal
            $('#missed-chart-eye-btn').on('click', function() {
                $('#missedChartModal').modal('show');
                setTimeout(function() { renderMissedLineChart(); }, 300);
            });
            $('#missedChartModal').on('hidden.bs.modal', function() {
                if (missedLineChart) {
                    missedLineChart.destroy();
                    missedLineChart = null;
                }
            });

            function renderTatLineChart() {
                var ctx = document.getElementById('tat-line-chart');
                if (!ctx) return;
                if (tatLineChart) {
                    tatLineChart.destroy();
                    tatLineChart = null;
                }
                var labels = tatChartData.map(function(d) { return d.label; });
                var values = tatChartData.map(function(d) { return d.avg != null ? d.avg : null; });
                tatLineChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Avg TAT (days)',
                            data: values,
                            borderColor: '#20c997',
                            backgroundColor: 'rgba(32, 201, 151, 0.1)',
                            fill: true,
                            tension: 0.2,
                            spanGaps: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: true },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        var v = ctx.raw;
                                        return v != null ? v + ' days' : 'No data';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { display: true, title: { display: true, text: 'Date' } },
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'TAT (days)' },
                                ticks: { stepSize: 1 }
                            }
                        }
                    }
                });
            }

            function renderMissedLineChart() {
                var ctx = document.getElementById('missed-line-chart');
                if (!ctx) return;
                if (missedLineChart) {
                    missedLineChart.destroy();
                    missedLineChart = null;
                }
                var labels = missedChartData.map(function(d) { return d.label; });
                var values = missedChartData.map(function(d) { return d.count || 0; });
                missedLineChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Missed Tasks (count)',
                            data: values,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            fill: true,
                            tension: 0.2,
                            spanGaps: false
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: true },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        var v = ctx.raw;
                                        return v + ' task' + (v != 1 ? 's' : '');
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { display: true, title: { display: true, text: 'Date' } },
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Count' },
                                ticks: { stepSize: 1 }
                            }
                        }
                    }
                });
            }

            // Initialize Tabulator
            var table = new Tabulator("#deleted-tasks-table", {
                ajaxURL: "{{ route('tasks.deletedData') }}",
                ajaxContentType: "json",
                layout: "fitData",
                pagination: true,
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100],
                responsiveLayout: false,
                placeholder: "No Archived Tasks Found",
                height: "600px",
                layoutColumnsOnNewData: true,
                rowFormatter: function(row) {
                    var data = row.getData();
                    var status = (data.status && String(data.status).trim()) || '';
                    // Missed = deleted without being Done (any status other than Done)
                    if (status.toLowerCase() !== 'done') {
                        var el = row.getElement();
                        if (el) {
                            el.classList.add('deleted-row-missed');
                            el.style.setProperty('background-color', '#fffde7', 'important');
                        }
                    }
                },
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
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var value = cell.getValue();
                            if (value && value !== '-') {
                                var firstName = value.trim().split(' ')[0];
                                var imgSrc = (row.assignor_avatar || "{{ asset('images/users/avatar-2.jpg') }}").replace(/&/g, '&amp;');
                                var nameEsc = String(firstName).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                return '<div class="d-flex align-items-center justify-content-center gap-2 flex-nowrap">' +
                                    '<img src="' + imgSrc + '" alt="" class="rounded-circle" style="width:28px;height:28px;object-fit:cover;flex-shrink:0;">' +
                                    '<strong style="font-size: 11px;">' + nameEsc + '</strong>' +
                                    '</div>';
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    },
                    {
                        title: "ASSIGNEE",
                        field: "assignee_name",
                        width: 120,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var value = cell.getValue();
                            if (value && value !== '-') {
                                var firstName = value.trim().split(' ')[0];
                                var imgSrc = (row.assignee_avatar || "{{ asset('images/users/avatar-2.jpg') }}").replace(/&/g, '&amp;');
                                var nameEsc = String(firstName).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                return '<div class="d-flex align-items-center justify-content-center gap-2 flex-nowrap">' +
                                    '<img src="' + imgSrc + '" alt="" class="rounded-circle" style="width:28px;height:28px;object-fit:cover;flex-shrink:0;">' +
                                    '<strong style="font-size: 11px;">' + nameEsc + '</strong>' +
                                    '</div>';
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
                            var value = (cell.getValue() || '').trim();
                            // Done when deleted as Done; otherwise Missed (deleted without completing)
                            var display = (value.toLowerCase() === 'done') ? 'Done' : 'Missed';
                            if (display === 'Done') {
                                return '<span style="font-weight: 600; color: #6c757d;">Done</span>';
                            }
                            return '<span style="font-weight: 600; color: #dc3545;">Missed</span>';
                        }
                    },
                    {
                        title: "TAT",
                        field: "tat",
                        width: 90,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value !== null && value !== undefined && value !== '') {
                                return '<span style="font-weight: 600;">' + Number(value).toFixed(1) + ' days</span>';
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    },
                    {
                        title: "ARCHIVED BY", 
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
                        title: "ARCHIVED DATE", 
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

            // Apply light yellow background to Missed rows (after data load / redraw)
            function styleMissedRows() {
                table.getRows().forEach(function(row) {
                    var data = row.getData();
                    var status = (data.status && String(data.status).trim()) || '';
                    if (status.toLowerCase() !== 'done') {
                        var el = row.getElement();
                        if (el) {
                            el.classList.add('deleted-row-missed');
                            el.style.setProperty('background-color', '#fffde7', 'important');
                        }
                    }
                });
            }
            table.on('dataLoaded', function() { setTimeout(styleMissedRows, 0); });
            table.on('dataProcessed', function() { setTimeout(styleMissedRows, 0); });
            table.on('renderComplete', styleMissedRows);

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
