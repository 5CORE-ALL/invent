@extends('layouts.vertical', ['title' => 'Task Summary', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <style>
        .task-summary-table thead th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            border-bottom-width: 1px;
        }
        .task-summary-table tbody td {
            vertical-align: middle;
        }
        .task-summary-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid rgba(15, 23, 42, 0.08);
        }
        .task-summary-num {
            font-variant-numeric: tabular-nums;
            font-weight: 600;
        }
        .task-summary-search-wrap {
            max-width: 420px;
        }
        .task-summary-search-wrap .input-group-text {
            background: #f8fafc;
            border-color: #e2e8f0;
            color: #64748b;
        }
        .task-summary-search-wrap .form-control:focus {
            border-color: #94a3b8;
            box-shadow: 0 0 0 0.15rem rgba(100, 116, 139, 0.15);
        }
        .task-summary-th-sort {
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
            transition: background-color 0.12s ease, color 0.12s ease;
        }
        .task-summary-th-sort:hover {
            background-color: #e2e8f0 !important;
            color: #0f172a;
        }
        .task-summary-th-sort.is-sorted {
            color: #0f172a;
        }
        .task-summary-sort-icon {
            font-size: 0.95em;
            opacity: 0.45;
            vertical-align: -0.1em;
            margin-left: 0.15rem;
        }
        .task-summary-th-sort.is-sorted .task-summary-sort-icon {
            opacity: 1;
            color: #0d9488;
        }

        /* Interactive analytics (frontend-only, visible rows) */
        .task-summary-analytics {
            background: linear-gradient(180deg, rgba(13, 148, 136, 0.07) 0%, transparent 100%);
            border: 1px solid rgba(13, 148, 136, 0.14);
            border-radius: 14px;
            padding: 0.85rem 0.65rem 0.65rem;
            margin-bottom: 1rem;
        }
        .task-summary-analytics-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
        }
        .task-summary-analytics-badge {
            flex: 1 1 0;
            min-width: 118px;
            max-width: 200px;
            border: none;
            border-radius: 14px;
            padding: 0.8rem 0.95rem;
            text-align: left;
            background: #fff;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.07), 0 0 0 1px rgba(13, 148, 136, 0.12);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .task-summary-analytics-badge::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, #0d9488, #14b8a6);
        }
        .task-summary-analytics-badge:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 26px rgba(13, 148, 136, 0.16), 0 0 0 1px rgba(13, 148, 136, 0.2);
        }
        .task-summary-analytics-badge:focus-visible {
            outline: 2px solid #0d9488;
            outline-offset: 2px;
        }
        .task-summary-analytics-badge-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #64748b;
            padding-left: 0.35rem;
            margin-bottom: 0.15rem;
        }
        .task-summary-analytics-badge-value {
            font-size: 1.45rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: #0f766e;
            padding-left: 0.35rem;
            line-height: 1.15;
        }
        .task-summary-analytics-badge i {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.25rem;
            color: rgba(13, 148, 136, 0.35);
            pointer-events: none;
        }
        #taskSummaryAnalyticsModal .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.15);
        }
        #taskSummaryAnalyticsModal .modal-header {
            background: linear-gradient(135deg, #0f766e, #14b8a6);
            color: #fff;
            border-bottom: 0;
        }
        #taskSummaryAnalyticsModal .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        .task-summary-analytics-chart-wrap {
            position: relative;
            min-height: 320px;
        }
        .task-summary-analytics-loading {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.88);
            z-index: 2;
            border-radius: 12px;
        }
        .task-summary-analytics-loading .spinner-border {
            width: 2.5rem;
            height: 2.5rem;
            color: #0d9488;
        }
        @media (max-width: 575.98px) {
            .task-summary-analytics-badge {
                min-width: 140px;
                max-width: none;
            }
        }
    </style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ url('/') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ url('tasks') }}">Task Manager</a></li>
                        <li class="breadcrumb-item active">Task Summary</li>
                    </ol>
                </div>
                <h4 class="page-title">Task Summary</h4>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    

                    @if (!empty($rows) && count($rows))
                        <div class="task-summary-analytics">
                            <div class="task-summary-analytics-badges">
                                <button type="button" class="task-summary-analytics-badge" data-ts-metric="total" data-ts-title="Total Task Analytics" aria-label="Open total tasks chart">
                                    <div class="task-summary-analytics-badge-label">Total tasks</div>
                                    <div class="task-summary-analytics-badge-value" id="ts-analytics-val-total">0</div>
                                    <i class="ri-line-chart-line" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="task-summary-analytics-badge" data-ts-metric="assigned" data-ts-title="Assigned Task Analytics" title="Visible members with at least one assignee task" aria-label="Open assigned chart">
                                    <div class="task-summary-analytics-badge-label">Assigned (members)</div>
                                    <div class="task-summary-analytics-badge-value" id="ts-analytics-val-assigned">0</div>
                                    <i class="ri-team-line" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="task-summary-analytics-badge" data-ts-metric="overdue" data-ts-title="Overdue Analytics" aria-label="Open overdue chart">
                                    <div class="task-summary-analytics-badge-label">Overdue</div>
                                    <div class="task-summary-analytics-badge-value" id="ts-analytics-val-overdue">0</div>
                                    <i class="ri-alarm-warning-line" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="task-summary-analytics-badge" data-ts-metric="approval" data-ts-title="Approval Pending Analytics" aria-label="Open approval chart">
                                    <div class="task-summary-analytics-badge-label">Approval pending</div>
                                    <div class="task-summary-analytics-badge-value" id="ts-analytics-val-approval">0</div>
                                    <i class="ri-time-line" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="task-summary-analytics-badge" data-ts-metric="done" data-ts-title="Done Task Analytics" aria-label="Open done chart">
                                    <div class="task-summary-analytics-badge-label">Done</div>
                                    <div class="task-summary-analytics-badge-value" id="ts-analytics-val-done">0</div>
                                    <i class="ri-checkbox-circle-line" aria-hidden="true"></i>
                                </button>
                            </div>
                           
                        </div>
                        <div class="task-summary-search-wrap mb-3">
                            <label for="task-summary-search" class="visually-hidden">Search team members</label>
                            <div class="input-group">
                                <span class="input-group-text" aria-hidden="true"><i class="ri-search-line"></i></span>
                                <input type="search"
                                       class="form-control"
                                       id="task-summary-search"
                                       placeholder="Search by name or designation…"
                                       autocomplete="off"
                                       spellcheck="false" />
                            </div>
                        </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-hover table-striped table-bordered mb-0 task-summary-table">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="member" data-sort-type="text" title="Sort by team member" role="button" tabindex="0">
                                        Team Member <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort text-center" style="width: 72px;" data-sort-key="member" data-sort-type="text" title="Sort by team member" role="button" tabindex="0">
                                        Image <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="designation" data-sort-type="text" title="Sort by designation" role="button" tabindex="0">
                                        Designation <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort text-end" data-sort-key="task" data-sort-type="number" title="Sort by assignee task count" role="button" tabindex="0">
                                        Task <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort text-end" data-sort-key="assignor_task" data-sort-type="number" title="Sort by assignor task count" role="button" tabindex="0">
                                        Assignor task <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort text-end" data-sort-key="overdue" data-sort-type="number" title="Sort by overdue count" role="button" tabindex="0">
                                        Overdue <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort text-end" data-sort-key="a_task" data-sort-type="number" title="Sort by A Task count" role="button" tabindex="0">
                                        A Task <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort text-end" data-sort-key="need_approval" data-sort-type="number" title="Sort by Need Approval count" role="button" tabindex="0">
                                        Need Approval <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort text-end" data-sort-key="done" data-sort-type="number" title="Sort by done count" role="button" tabindex="0">
                                        Done <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    @php
                                        $avatarUrl = !empty($row['avatar'])
                                            ? asset('storage/' . $row['avatar'])
                                            : asset('images/users/avatar-2.jpg');
                                        $searchBlob = strtolower(
                                            trim($row['team_member'] . ' ' . ($row['designation'] ?? ''))
                                        );
                                    @endphp
                                    <tr class="task-summary-row"
                                        data-search="{{ e($searchBlob) }}"
                                        data-sort-member="{{ e($row['team_member']) }}"
                                        data-sort-designation="{{ e($row['designation'] ?? '') }}"
                                        data-sort-task="{{ (int) ($row['task'] ?? 0) }}"
                                        data-sort-assignor_task="{{ (int) ($row['assignor_task'] ?? 0) }}"
                                        data-sort-overdue="{{ (int) ($row['overdue'] ?? 0) }}"
                                        data-sort-a_task="{{ (int) ($row['a_task'] ?? 0) }}"
                                        data-sort-need_approval="{{ (int) ($row['need_approval'] ?? 0) }}"
                                        data-sort-done="{{ (int) ($row['done'] ?? 0) }}">
                                        <td>{{ $row['team_member'] }}</td>
                                        <td class="text-center">
                                            <img src="{{ $avatarUrl }}" alt="" class="task-summary-avatar" width="40" height="40" loading="lazy" />
                                        </td>
                                        <td>{{ $row['designation'] ?: '—' }}</td>
                                        <td class="text-end task-summary-num">{{ $row['task'] }}</td>
                                        <td class="text-end task-summary-num">{{ $row['assignor_task'] }}</td>
                                        <td class="text-end task-summary-num">{{ $row['overdue'] }}</td>
                                        <td class="text-end task-summary-num">{{ $row['a_task'] }}</td>
                                        <td class="text-end task-summary-num">{{ $row['need_approval'] }}</td>
                                        <td class="text-end task-summary-num">{{ $row['done'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">No team members found.</td>
                                    </tr>
                                @endforelse
                                @if (!empty($rows) && count($rows))
                                    <tr id="task-summary-filter-empty" class="d-none">
                                        <td colspan="9" class="text-center text-muted py-4">No matching team members.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="taskSummaryAnalyticsModal" tabindex="-1" aria-labelledby="taskSummaryAnalyticsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-fullscreen-sm-down modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskSummaryAnalyticsModalLabel">
                        <i class="ri-bar-chart-2-line me-2" aria-hidden="true"></i><span id="taskSummaryAnalyticsTitleText">Analytics</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2" id="taskSummaryAnalyticsSubtitle"></p>
                    <div class="task-summary-analytics-chart-wrap rounded-3 border" style="border-color: rgba(13,148,136,0.18) !important;">
                        <div class="task-summary-analytics-loading d-none" id="task-summary-analytics-loading">
                            <div class="spinner-border" role="status"><span class="visually-hidden">Loading…</span></div>
                        </div>
                        <div id="task-summary-analytics-apex" style="min-height:320px;"></div>
                    </div>
                </div>
                <div class="modal-footer flex-wrap gap-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-secondary" id="task-summary-analytics-export-png" style="border-color:#0d9488;color:#0f766e;">
                        <i class="ri-image-line me-1"></i>Export PNG
                    </button>
                    <button type="button" class="btn text-white" id="task-summary-analytics-export-csv" style="background:linear-gradient(135deg,#0f766e,#14b8a6);border:none;">
                        <i class="ri-file-excel-2-line me-1"></i>Export CSV
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js"></script>
    <script>
        (function () {
            var input = document.getElementById('task-summary-search');
            var tbody = document.querySelector('.task-summary-table tbody');
            if (!tbody) {
                return;
            }
            var emptyRow = document.getElementById('task-summary-filter-empty');
            var tsAnalyticsChart = null;
            var tsAnalyticsPayload = null;

            function getDataRows() {
                return Array.prototype.slice.call(tbody.querySelectorAll('tr.task-summary-row'));
            }

            function getVisibleTableData() {
                return Array.prototype.slice.call(tbody.querySelectorAll('tr.task-summary-row:not(.d-none)')).map(function (tr) {
                    return {
                        member: (tr.getAttribute('data-sort-member') || '').trim(),
                        task: parseInt(tr.getAttribute('data-sort-task'), 10) || 0,
                        assignor_task: parseInt(tr.getAttribute('data-sort-assignor_task'), 10) || 0,
                        overdue: parseInt(tr.getAttribute('data-sort-overdue'), 10) || 0,
                        a_task: parseInt(tr.getAttribute('data-sort-a_task'), 10) || 0,
                        need_approval: parseInt(tr.getAttribute('data-sort-need_approval'), 10) || 0,
                        done: parseInt(tr.getAttribute('data-sort-done'), 10) || 0
                    };
                });
            }

            function calculateBadgeSums() {
                var rows = getVisibleTableData();
                var totalTask = 0;
                var withAssignments = 0;
                var overdueSum = 0;
                var approvalSum = 0;
                var doneSum = 0;
                rows.forEach(function (r) {
                    totalTask += r.task;
                    if (r.task > 0) {
                        withAssignments += 1;
                    }
                    overdueSum += r.overdue;
                    approvalSum += r.need_approval;
                    doneSum += r.done;
                });
                var map = {
                    total: 'ts-analytics-val-total',
                    assigned: 'ts-analytics-val-assigned',
                    overdue: 'ts-analytics-val-overdue',
                    approval: 'ts-analytics-val-approval',
                    done: 'ts-analytics-val-done'
                };
                var vals = [totalTask, withAssignments, overdueSum, approvalSum, doneSum];
                var keys = ['total', 'assigned', 'overdue', 'approval', 'done'];
                keys.forEach(function (k, i) {
                    var el = document.getElementById(map[k]);
                    if (el) {
                        el.textContent = String(vals[i]);
                    }
                });
            }

            function buildChartSeries(metric) {
                var rows = getVisibleTableData();
                var fieldMap = {
                    total: 'task',
                    assigned: 'task',
                    overdue: 'overdue',
                    approval: 'need_approval',
                    done: 'done'
                };
                var field = fieldMap[metric] || 'task';
                var filtered = rows;
                if (metric === 'assigned') {
                    filtered = rows.filter(function (r) {
                        return r.task > 0;
                    });
                }
                var categories = filtered.map(function (r) {
                    return r.member || '(Unknown)';
                });
                var data = filtered.map(function (r) {
                    return r[field];
                });
                if (categories.length === 0) {
                    categories = ['—'];
                    data = [0];
                }
                var seriesNames = {
                    total: 'Tasks (assignee)',
                    assigned: 'Tasks (assignee)',
                    overdue: 'Overdue',
                    approval: 'Need approval',
                    done: 'Done'
                };
                return {
                    categories: categories,
                    data: data,
                    seriesName: seriesNames[metric] || 'Value'
                };
            }

            function destroyTsChart() {
                if (tsAnalyticsChart) {
                    tsAnalyticsChart.destroy();
                    tsAnalyticsChart = null;
                }
            }

            function openAnalyticsModal(metric) {
                if (typeof ApexCharts === 'undefined') {
                    return;
                }
                var btn = document.querySelector('.task-summary-analytics-badge[data-ts-metric="' + metric + '"]');
                var title = btn && btn.getAttribute('data-ts-title') ? btn.getAttribute('data-ts-title') : 'Analytics';
                var titleEl = document.getElementById('taskSummaryAnalyticsTitleText');
                var subEl = document.getElementById('taskSummaryAnalyticsSubtitle');
                if (titleEl) {
                    titleEl.textContent = title;
                }
                if (subEl) {
                    subEl.textContent = 'Uses the same visible rows as the table (after search). X-axis: team member. Y-axis: ' +
                        (metric === 'assigned' ? 'assignee task count (only members with tasks).' : 'count for this metric.');
                }
                var loading = document.getElementById('task-summary-analytics-loading');
                var mount = document.getElementById('task-summary-analytics-apex');
                if (!mount) {
                    return;
                }
                if (loading) {
                    loading.classList.remove('d-none');
                }
                destroyTsChart();
                mount.innerHTML = '';

                var built = buildChartSeries(metric);
                tsAnalyticsPayload = {
                    metric: metric,
                    title: title,
                    categories: built.categories,
                    data: built.data,
                    seriesName: built.seriesName
                };

                function renderTsApexChart() {
                    requestAnimationFrame(function () {
                        var options = {
                            chart: {
                                type: 'line',
                                height: 340,
                                toolbar: { show: true },
                                fontFamily: 'inherit',
                                animations: { enabled: true, easing: 'easeinout', speed: 550 }
                            },
                            series: [{ name: built.seriesName, data: built.data }],
                            xaxis: {
                                categories: built.categories,
                                labels: {
                                    rotate: -35,
                                    rotateAlways: built.categories.length > 6,
                                    style: { fontSize: '11px' }
                                }
                            },
                            yaxis: {
                                min: 0,
                                decimalsInFloat: 0,
                                labels: { style: { fontSize: '12px' } }
                            },
                            stroke: { curve: 'smooth', width: 3, colors: ['#0d9488'] },
                            markers: {
                                size: 5,
                                colors: ['#0d9488'],
                                strokeColors: '#fff',
                                strokeWidth: 2,
                                hover: { size: 7 }
                            },
                            colors: ['#0d9488'],
                            grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
                            dataLabels: { enabled: false },
                            tooltip: { theme: 'light', x: { show: true } }
                        };
                        tsAnalyticsChart = new ApexCharts(mount, options);
                        tsAnalyticsChart.render().then(function () {
                            if (loading) {
                                loading.classList.add('d-none');
                            }
                            if (tsAnalyticsChart && typeof tsAnalyticsChart.resize === 'function') {
                                tsAnalyticsChart.resize();
                            }
                        });
                    });
                }

                var modalEl = document.getElementById('taskSummaryAnalyticsModal');
                if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                    renderTsApexChart();
                    return;
                }
                var modalInst = bootstrap.Modal.getOrCreateInstance(modalEl);
                if (modalEl.classList.contains('show')) {
                    renderTsApexChart();
                } else {
                    modalEl.addEventListener('shown.bs.modal', function onTsModalShown() {
                        modalEl.removeEventListener('shown.bs.modal', onTsModalShown);
                        renderTsApexChart();
                    });
                    modalInst.show();
                }
            }

            function exportAnalyticsCsv() {
                if (!tsAnalyticsPayload) {
                    return;
                }
                var lines = ['Team member,' + tsAnalyticsPayload.seriesName.replace(/,/g, '')];
                tsAnalyticsPayload.categories.forEach(function (c, i) {
                    var label = '"' + String(c).replace(/"/g, '""') + '"';
                    lines.push(label + ',' + tsAnalyticsPayload.data[i]);
                });
                var blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'task-summary-analytics.csv';
                a.click();
                URL.revokeObjectURL(a.href);
            }

            function exportAnalyticsPng() {
                if (!tsAnalyticsChart || typeof tsAnalyticsChart.dataURI !== 'function') {
                    return;
                }
                tsAnalyticsChart.dataURI().then(function (uri) {
                    var a = document.createElement('a');
                    a.href = uri.imgURI;
                    a.download = 'task-summary-analytics.png';
                    a.click();
                });
            }

            document.querySelectorAll('.task-summary-analytics-badge[data-ts-metric]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var m = b.getAttribute('data-ts-metric');
                    if (m) {
                        openAnalyticsModal(m);
                    }
                });
            });

            var pngBtn = document.getElementById('task-summary-analytics-export-png');
            var csvBtn = document.getElementById('task-summary-analytics-export-csv');
            if (pngBtn) {
                pngBtn.addEventListener('click', exportAnalyticsPng);
            }
            if (csvBtn) {
                csvBtn.addEventListener('click', exportAnalyticsCsv);
            }

            var modalEl = document.getElementById('taskSummaryAnalyticsModal');
            if (modalEl) {
                modalEl.addEventListener('hidden.bs.modal', function () {
                    destroyTsChart();
                    var loading = document.getElementById('task-summary-analytics-loading');
                    if (loading) {
                        loading.classList.add('d-none');
                    }
                    var mount = document.getElementById('task-summary-analytics-apex');
                    if (mount) {
                        mount.innerHTML = '';
                    }
                });
            }

            function runFilter() {
                if (!input) {
                    return;
                }
                var rows = getDataRows();
                var q = (input.value || '').trim().toLowerCase();
                var shown = 0;
                rows.forEach(function (tr) {
                    var hay = (tr.getAttribute('data-search') || '').toLowerCase();
                    var match = !q || hay.indexOf(q) !== -1;
                    tr.classList.toggle('d-none', !match);
                    if (match) {
                        shown++;
                    }
                });
                if (emptyRow) {
                    emptyRow.classList.toggle('d-none', !(q && shown === 0));
                }
                calculateBadgeSums();
            }

            if (input) {
                input.addEventListener('input', runFilter);
                input.addEventListener('search', runFilter);
            }
            calculateBadgeSums();

            var sortState = { key: null, dir: 'asc' };
            var headers = document.querySelectorAll('.task-summary-th-sort');

            function sortIconClass(dir) {
                return dir === 'asc' ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line';
            }

            function updateHeaderIcons(sortKey, dir) {
                headers.forEach(function (th) {
                    var icon = th.querySelector('.task-summary-sort-icon');
                    if (!icon) {
                        return;
                    }
                    var same = th.getAttribute('data-sort-key') === sortKey;
                    th.classList.toggle('is-sorted', same);
                    if (same) {
                        icon.className = 'task-summary-sort-icon ' + sortIconClass(dir);
                    } else {
                        icon.className = 'task-summary-sort-icon ri-arrow-up-down-line';
                    }
                });
            }

            function compareRows(a, b, key, type) {
                var attr = 'data-sort-' + key;
                var va = a.getAttribute(attr);
                var vb = b.getAttribute(attr);
                if (va === null) {
                    va = '';
                }
                if (vb === null) {
                    vb = '';
                }
                if (type === 'number') {
                    va = parseInt(va, 10) || 0;
                    vb = parseInt(vb, 10) || 0;
                    return va - vb;
                }
                return va.toString().localeCompare(vb.toString(), undefined, { sensitivity: 'base' });
            }

            function applySort() {
                var key = sortState.key;
                var dir = sortState.dir;
                if (!key) {
                    return;
                }
                var activeTh = null;
                headers.forEach(function (th) {
                    if (th.getAttribute('data-sort-key') === key && !activeTh) {
                        activeTh = th;
                    }
                });
                var type = activeTh && activeTh.getAttribute('data-sort-type') === 'number' ? 'number' : 'text';
                var rows = getDataRows();
                rows.sort(function (a, b) {
                    var c = compareRows(a, b, key, type);
                    return dir === 'asc' ? c : -c;
                });
                rows.forEach(function (tr) {
                    tbody.appendChild(tr);
                });
                if (emptyRow) {
                    tbody.appendChild(emptyRow);
                }
                calculateBadgeSums();
            }

            function onSortClick(th) {
                var key = th.getAttribute('data-sort-key');
                if (!key) {
                    return;
                }
                if (sortState.key === key) {
                    sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
                } else {
                    sortState.key = key;
                    sortState.dir = 'asc';
                }
                updateHeaderIcons(key, sortState.dir);
                applySort();
            }

            headers.forEach(function (th) {
                th.addEventListener('click', function () {
                    onSortClick(th);
                });
                th.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        onSortClick(th);
                    }
                });
            });
        })();
    </script>
@endsection
