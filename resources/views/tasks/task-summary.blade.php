@extends('layouts.vertical', ['title' => 'Task Summary', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <style>
        .task-summary-table {
            width: max-content;
            max-width: 100%;
            margin-left: auto;
            margin-right: auto;
            table-layout: auto;
        }
        .task-summary-table thead th,
        .task-summary-table tbody td {
            text-align: center;
            vertical-align: middle;
            padding-top: 0.4rem;
            padding-bottom: 0.4rem;
            padding-left: 0.45rem;
            padding-right: 0.45rem;
            white-space: nowrap;
            width: 1%;
        }
        .task-summary-table th.task-summary-col-member,
        .task-summary-table td.task-summary-col-member {
            text-align: left;
        }
        .task-summary-table thead th.task-summary-col-member {
            text-align: left;
        }
        .task-summary-table thead th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            border-bottom-width: 1px;
        }
        .task-summary-avatar-cell {
            overflow: visible;
        }
        .task-summary-avatar-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            vertical-align: middle;
        }
        .task-summary-avatar {
            width: 30px;
            height: 30px;
            max-width: none;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid rgba(15, 23, 42, 0.08);
            transition: box-shadow 0.2s ease, transform 0.2s ease;
            transform-origin: center center;
        }
        .task-summary-avatar-wrap:hover .task-summary-avatar {
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.2);
            transform: scale(1.08);
        }
        /* Large preview above cursor (avoids .table-responsive clipping) */
        #task-summary-avatar-flyout {
            position: fixed;
            z-index: 1080;
            width: 96px;
            height: 96px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #fff;
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.35);
            pointer-events: none;
            transform: translate(-50%, calc(-100% - 14px));
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.12s ease, visibility 0.12s ease;
        }
        #task-summary-avatar-flyout.is-visible {
            opacity: 1;
            visibility: visible;
        }
        #task-summary-avatar-flyout img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .task-summary-col-overdue-positive {
            color: #dc2626 !important;
            font-weight: 700;
        }
        .task-summary-col-done {
            color: #15803d !important;
            font-weight: 600;
        }
        #ts-analytics-val-overdue {
            color: #dc2626;
        }
        #ts-analytics-val-done {
            color: #15803d;
        }
        .task-summary-num {
            font-variant-numeric: tabular-nums;
            font-weight: 600;
        }
        .task-summary-member-cell-inner {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            max-width: 100%;
        }
        .task-summary-member-name {
            min-width: 0;
        }
        .task-summary-user-tasks-dot {
            flex-shrink: 0;
            width: 0.5rem;
            height: 0.5rem;
            min-width: 0.5rem;
            min-height: 0.5rem;
            padding: 0;
            margin: 0;
            border: none;
            border-radius: 50%;
            background: #0d9488;
            box-shadow: 0 0 0 1px rgba(13, 148, 136, 0.35);
            cursor: pointer;
            vertical-align: middle;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        }
        .task-summary-user-tasks-dot:hover {
            background: #0f766e;
            transform: scale(1.35);
            box-shadow: 0 0 0 2px rgba(13, 148, 136, 0.45);
        }
        .task-summary-user-tasks-dot:focus-visible {
            outline: 2px solid #0d9488;
            outline-offset: 2px;
        }
        #taskSummaryUserPanel.offcanvas-end {
            width: min(560px, 100vw);
        }
        @media (min-width: 992px) {
            #taskSummaryUserPanel.offcanvas-end {
                width: min(640px, 42vw);
            }
        }
        #taskSummaryUserPanel .offcanvas-header {
            background: linear-gradient(135deg, #f0fdfa 0%, #fff 100%);
        }
        .ts-user-panel-stat-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.5rem;
        }
        @media (max-width: 400px) {
            .ts-user-panel-stat-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        .ts-user-panel-stat {
            border-radius: 10px;
            padding: 0.45rem 0.5rem;
            text-align: center;
            background: #fff;
            border: 1px solid rgba(13, 148, 136, 0.15);
            font-size: 0.75rem;
        }
        .ts-user-panel-stat .ts-val {
            font-weight: 800;
            font-size: 1.1rem;
            font-variant-numeric: tabular-nums;
            line-height: 1.2;
        }
        .ts-user-panel-stat .ts-lbl {
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 0.62rem;
            font-weight: 700;
        }
        .ts-user-panel-stat.ts-stat-overdue .ts-val {
            color: #dc2626;
        }
        .ts-user-panel-stat.ts-stat-done .ts-val {
            color: #15803d;
        }
        #ts-user-panel-table-wrap {
            max-height: calc(100vh - 320px);
            min-height: 200px;
            overflow: auto;
        }
        #ts-user-panel-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f8fafc;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            border-bottom-width: 2px;
            white-space: nowrap;
        }
        #ts-user-panel-table td {
            font-size: 0.8125rem;
            vertical-align: middle;
        }
        /* Match Task Manager Tabulator automated-task row styling */
        #ts-user-panel-table tbody tr.ts-user-panel-row-automated td {
            background-color: #fffbea !important;
        }
        #ts-user-panel-table tbody tr.ts-user-panel-row-automated-alt td {
            background-color: #fff7cc !important;
        }
        #ts-user-panel-table tbody tr.ts-user-panel-row-automated td:first-child {
            box-shadow: inset 4px 0 0 #ffc107;
        }
        #ts-user-panel-table.table-hover tbody tr.ts-user-panel-row-automated:hover td,
        #ts-user-panel-table.table-hover tbody tr.ts-user-panel-row-automated-alt:hover td {
            background-color: #fef3c7 !important;
        }
        .ts-user-panel-status {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.2em 0.45em;
            border-radius: 6px;
            white-space: nowrap;
        }
        .task-summary-search-wrap {
            max-width: 420px;
            margin-left: auto;
            margin-right: auto;
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
            justify-content: center;
            gap: 0.65rem;
        }
        .task-summary-analytics-badge {
            flex: 1 1 0;
            min-width: 118px;
            max-width: 200px;
            border: none;
            border-radius: 14px;
            padding: 0.8rem 0.95rem;
            text-align: center;
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
            margin-bottom: 0.15rem;
        }
        .task-summary-analytics-badge-value {
            font-size: 1.45rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: #0f766e;
            line-height: 1.15;
        }
        .task-summary-analytics-badge i {
            position: absolute;
            right: 10px;
            top: 8px;
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
    @php
        $taskDashboardStats = $taskDashboardStats ?? [
            'total_tasks' => 0,
            'assigned_members' => 0,
            'pending' => 0,
            'overdue' => 0,
            'approval_pending' => 0,
            'done' => 0,
        ];
    @endphp
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
                                    <div class="task-summary-analytics-badge-value" id="ts-analytics-val-total">{{ number_format($taskDashboardStats['total_tasks']) }}</div>
                                    <i class="ri-line-chart-line" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="task-summary-analytics-badge" data-ts-metric="assigned" data-ts-title="Assigned Task Analytics" title="Active members with at least one assignee task" aria-label="Open assigned chart">
                                    <div class="task-summary-analytics-badge-label">Assigned (members)</div>
                                    <div class="task-summary-analytics-badge-value" id="ts-analytics-val-assigned">{{ number_format($taskDashboardStats['assigned_members']) }}</div>
                                    <i class="ri-team-line" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="task-summary-analytics-badge" data-ts-metric="overdue" data-ts-title="Overdue Analytics" aria-label="Open overdue chart">
                                    <div class="task-summary-analytics-badge-label">Overdue</div>
                                    <div class="task-summary-analytics-badge-value" id="ts-analytics-val-overdue">{{ number_format($taskDashboardStats['overdue']) }}</div>
                                    <i class="ri-alarm-warning-line" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="task-summary-analytics-badge" data-ts-metric="approval" data-ts-title="Approval Pending Analytics" aria-label="Open approval chart">
                                    <div class="task-summary-analytics-badge-label">Approval pending</div>
                                    <div class="task-summary-analytics-badge-value" id="ts-analytics-val-approval">{{ number_format($taskDashboardStats['approval_pending']) }}</div>
                                    <i class="ri-time-line" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="task-summary-analytics-badge" data-ts-metric="done" data-ts-title="Done Task Analytics" aria-label="Open done chart">
                                    <div class="task-summary-analytics-badge-label">Done</div>
                                    <div class="task-summary-analytics-badge-value" id="ts-analytics-val-done">{{ number_format($taskDashboardStats['done']) }}</div>
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
                                    <th scope="col" class="task-summary-th-sort task-summary-col-member" data-sort-key="member" data-sort-type="text" title="Sort by team member" role="button" tabindex="0">
                                        Team Member <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="member" data-sort-type="text" title="Sort by team member" role="button" tabindex="0">
                                        Image <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="designation" data-sort-type="text" title="Sort by designation" role="button" tabindex="0">
                                        Designation <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="task" data-sort-type="number" title="Sort by assignee task count" role="button" tabindex="0">
                                        Task <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="assignor_task" data-sort-type="number" title="Sort by assignor task count" role="button" tabindex="0">
                                        Assignor task <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="done" data-sort-type="number" title="Sort by done count" role="button" tabindex="0">
                                        Done <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="overdue" data-sort-type="number" title="Sort by overdue count" role="button" tabindex="0">
                                        Overdue <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="a_task" data-sort-type="number" title="Sort by A Task count" role="button" tabindex="0">
                                        A Task <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="need_approval" data-sort-type="number" title="Sort by Need Approval count" role="button" tabindex="0">
                                        Need Approval <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    @php
                                        $avatarUrl = !empty($row['avatar'])
                                            ? asset('storage/' . $row['avatar'])
                                            : asset('images/users/add-image-placeholder.svg');
                                        $searchBlob = strtolower(
                                            trim($row['team_member'] . ' ' . ($row['designation'] ?? ''))
                                        );
                                    @endphp
                                    <tr class="task-summary-row"
                                        data-search="{{ e($searchBlob) }}"
                                        data-user-email="{{ e($row['email'] ?? '') }}"
                                        data-sort-member="{{ e($row['team_member']) }}"
                                        data-sort-designation="{{ e($row['designation'] ?? '') }}"
                                        data-sort-task="{{ (int) ($row['task'] ?? 0) }}"
                                        data-sort-assignor_task="{{ (int) ($row['assignor_task'] ?? 0) }}"
                                        data-sort-overdue="{{ (int) ($row['overdue'] ?? 0) }}"
                                        data-sort-a_task="{{ (int) ($row['a_task'] ?? 0) }}"
                                        data-sort-need_approval="{{ (int) ($row['need_approval'] ?? 0) }}"
                                        data-sort-done="{{ (int) ($row['done'] ?? 0) }}">
                                        <td class="task-summary-col-member">
                                            <span class="task-summary-member-cell-inner">
                                                <button type="button"
                                                        class="task-summary-user-tasks-dot"
                                                        data-user-name="{{ e($row['team_member']) }}"
                                                        title="View summary and tasks on this page"
                                                        aria-label="View tasks and summary for {{ e($row['team_member']) }}"></button>
                                                <span class="task-summary-member-name">{{ $row['team_member'] }}</span>
                                            </span>
                                        </td>
                                        <td class="task-summary-avatar-cell">
                                            <span class="task-summary-avatar-wrap">
                                                <img src="{{ $avatarUrl }}" alt="" class="task-summary-avatar" loading="lazy" />
                                            </span>
                                        </td>
                                        <td>{{ $row['designation'] ?: '—' }}</td>
                                        <td class="task-summary-num">{{ $row['task'] }}</td>
                                        <td class="task-summary-num">{{ $row['assignor_task'] }}</td>
                                        <td class="task-summary-num task-summary-col-done">{{ $row['done'] }}</td>
                                        <td class="task-summary-num @if(($row['overdue'] ?? 0) > 0) task-summary-col-overdue-positive @endif">{{ $row['overdue'] }}</td>
                                        <td class="task-summary-num">{{ $row['a_task'] }}</td>
                                        <td class="task-summary-num">{{ $row['need_approval'] }}</td>
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

    <div id="task-summary-avatar-flyout" aria-hidden="true">
        <img src="" alt="" />
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

    <div class="offcanvas offcanvas-end shadow-lg border-start" tabindex="-1" id="taskSummaryUserPanel" aria-labelledby="taskSummaryUserPanelLabel">
        <div class="offcanvas-header border-bottom py-3">
            <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0 me-2">
                <img src="" alt="" class="rounded-circle border flex-shrink-0 d-none" id="ts-user-panel-avatar" width="52" height="52" style="object-fit:cover;" />
                <div class="min-w-0">
                    <h5 class="offcanvas-title mb-0 text-truncate" id="taskSummaryUserPanelLabel"></h5>
                    <div class="text-muted small text-truncate" id="ts-user-panel-designation"></div>
                    <div class="text-muted small text-truncate d-none" id="ts-user-panel-email"></div>
                </div>
            </div>
            <button type="button" class="btn-close text-reset flex-shrink-0" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <div class="p-3 border-bottom bg-light">
                <div class="ts-user-panel-stat-grid" id="ts-user-panel-stats" aria-label="Summary counts for this user"></div>
            </div>
            <div class="px-3 py-2 border-bottom d-flex flex-wrap align-items-center gap-2 bg-white">
                <input type="search" class="form-control form-control-sm flex-grow-1" style="min-width: 140px;" id="ts-user-panel-task-search" placeholder="Filter tasks by title, status, assignee…" autocomplete="off" />
                <button type="button" class="btn btn-sm btn-outline-secondary" id="ts-user-panel-open-tm" title="Opens full Task Manager filtered to this user">
                    <i class="ri-external-link-line me-1" aria-hidden="true"></i>Task Manager
                </button>
            </div>
            <div class="flex-grow-1 d-flex flex-column px-0 pb-0">
                <div id="ts-user-panel-loading" class="text-center py-5">
                    <div class="spinner-border text-teal" style="color:#0d9488 !important;" role="status"><span class="visually-hidden">Loading…</span></div>
                    <p class="text-muted small mt-2 mb-0">Loading tasks…</p>
                </div>
                <div id="ts-user-panel-error" class="alert alert-danger mx-3 mt-3 d-none" role="alert"></div>
                <div id="ts-user-panel-table-wrap" class="d-none px-3 pb-3">
                    <table class="table table-sm table-hover table-bordered mb-0" id="ts-user-panel-table">
                        <thead>
                            <tr>
                                <th scope="col">Task</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="d-none d-md-table-cell">Assignee</th>
                                <th scope="col" class="d-none d-lg-table-cell">Assignor</th>
                                <th scope="col" class="text-nowrap">Start</th>
                                <th scope="col" class="text-center text-nowrap">Open</th>
                            </tr>
                        </thead>
                        <tbody id="ts-user-panel-tbody"></tbody>
                    </table>
                </div>
                <p id="ts-user-panel-empty" class="text-muted text-center py-5 mb-0 d-none px-3">No tasks for this user in your current view.</p>
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

            var avatarFlyout = document.getElementById('task-summary-avatar-flyout');
            var avatarFlyoutImg = avatarFlyout ? avatarFlyout.querySelector('img') : null;

            function tsPositionAvatarFlyout(clientX, clientY) {
                if (!avatarFlyout) {
                    return;
                }
                avatarFlyout.style.left = clientX + 'px';
                avatarFlyout.style.top = clientY + 'px';
            }

            function tsHideAvatarFlyout() {
                if (!avatarFlyout) {
                    return;
                }
                avatarFlyout.classList.remove('is-visible');
                avatarFlyout.setAttribute('aria-hidden', 'true');
            }

            if (avatarFlyout && avatarFlyoutImg) {
                tbody.addEventListener('mouseover', function (e) {
                    var wrap = e.target.closest && e.target.closest('.task-summary-avatar-wrap');
                    if (!wrap || !tbody.contains(wrap)) {
                        return;
                    }
                    var img = wrap.querySelector('.task-summary-avatar');
                    if (!img) {
                        return;
                    }
                    var src = img.getAttribute('src');
                    if (!src) {
                        return;
                    }
                    avatarFlyoutImg.setAttribute('src', src);
                    avatarFlyout.classList.add('is-visible');
                    avatarFlyout.setAttribute('aria-hidden', 'false');
                    tsPositionAvatarFlyout(e.clientX, e.clientY);
                });
                tbody.addEventListener('mousemove', function (e) {
                    if (!avatarFlyout.classList.contains('is-visible')) {
                        return;
                    }
                    tsPositionAvatarFlyout(e.clientX, e.clientY);
                });
                tbody.addEventListener('mouseout', function (e) {
                    var wrap = e.target.closest && e.target.closest('.task-summary-avatar-wrap');
                    if (!wrap || !tbody.contains(wrap)) {
                        return;
                    }
                    var rel = e.relatedTarget;
                    if (rel && wrap.contains(rel)) {
                        return;
                    }
                    tsHideAvatarFlyout();
                });
                avatarFlyoutImg.addEventListener('error', function () {
                    tsHideAvatarFlyout();
                });
                window.addEventListener(
                    'scroll',
                    function () {
                        tsHideAvatarFlyout();
                    },
                    true
                );
            }

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
                    subEl.textContent = 'Chart uses visible table rows (after search). Top badges match Task Manager row counts. X-axis: team member. Y-axis: ' +
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
            }

            if (input) {
                input.addEventListener('input', runFilter);
                input.addEventListener('search', runFilter);
            }

            var tsTasksDataUrl = @json(route('tasks.data'));
            var tsTaskShowBase = @json(rtrim(url('/tasks'), '/'));
            var tsSetSelectedUserUrl = @json(route('tasks.setSelectedUser'));
            var tsTasksIndexUrl = @json(route('tasks.index'));
            var tsCsrfToken = document.querySelector('meta[name="csrf-token"]')
                ? document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                : '';
            var tsUserPanelTasks = [];
            var tsUserPanelName = '';

            var tsUserPanelEl = document.getElementById('taskSummaryUserPanel');

            function tsGetUserPanelOffcanvas() {
                if (!tsUserPanelEl || typeof bootstrap === 'undefined' || !bootstrap.Offcanvas) {
                    return null;
                }
                return bootstrap.Offcanvas.getOrCreateInstance(tsUserPanelEl);
            }

            function tsStatusBadgeClass(status) {
                var s = String(status || '').toLowerCase();
                if (s === 'done') return 'bg-success';
                if (s === 'todo') return 'bg-secondary';
                if (s === 'working') return 'bg-primary';
                if (s === 'need approval') return 'bg-warning text-dark';
                if (s === 'archived') return 'bg-dark';
                if (s === 'need help') return 'bg-danger';
                if (s === 'dependent') return 'bg-info text-dark';
                return 'bg-secondary';
            }

            function tsFormatStart(val) {
                if (!val) return '—';
                var str = String(val);
                return str.length >= 10 ? str.slice(0, 10) : str;
            }

            function tsTaskIsOverdueForPanel(t) {
                if (!t || !t.start_date || t.status === 'Archived') {
                    return false;
                }
                var startDate = new Date(t.start_date);
                if (isNaN(startDate.getTime())) {
                    return false;
                }
                startDate.setHours(0, 0, 0, 0);
                startDate.setDate(startDate.getDate() + 1);
                var now = new Date();
                now.setHours(0, 0, 0, 0);
                return now > startDate;
            }

            function tsPanelFieldMatchesUser(userNorm, fieldVal) {
                if (!userNorm) {
                    return false;
                }
                if (fieldVal === undefined || fieldVal === null) {
                    return false;
                }
                var f = String(fieldVal).toLowerCase();
                if (f === '' || f === '-' || f === '—') {
                    return false;
                }
                return f.indexOf(userNorm) !== -1;
            }

            function tsSetUserPanelStatsLoading() {
                var grid = document.getElementById('ts-user-panel-stats');
                if (!grid) {
                    return;
                }
                var labels = ['As assignee', 'As assignor', 'Done', 'Overdue', 'A task', 'Need appr.'];
                var classes = ['', '', 'ts-stat-done', 'ts-stat-overdue', '', ''];
                grid.innerHTML = '';
                labels.forEach(function (lbl, i) {
                    var div = document.createElement('div');
                    div.className = 'ts-user-panel-stat ' + (classes[i] || '');
                    div.innerHTML = '<div class="ts-val text-muted">…</div><div class="ts-lbl">' + lbl + '</div>';
                    grid.appendChild(div);
                });
            }

            function tsSetUserPanelStatsFromTasks(tasks, userName) {
                var grid = document.getElementById('ts-user-panel-stats');
                if (!grid) {
                    return;
                }
                var userNorm = String(userName || '').trim().toLowerCase();
                var asAssignee = 0;
                var asAssignor = 0;
                var done = 0;
                var overdue = 0;
                var aTask = 0;
                var needAppr = 0;
                (tasks || []).forEach(function (t) {
                    if (tsPanelFieldMatchesUser(userNorm, t.assignee_name)) {
                        asAssignee += 1;
                    }
                    if (tsPanelFieldMatchesUser(userNorm, t.assignor_name)) {
                        asAssignor += 1;
                    }
                    var st = String(t.status || '').trim();
                    if (st === 'Done') {
                        done += 1;
                    }
                    if (st === 'Todo') {
                        aTask += 1;
                    }
                    if (st === 'Need Approval') {
                        needAppr += 1;
                    }
                    if (tsTaskIsOverdueForPanel(t)) {
                        overdue += 1;
                    }
                });
                var specs = [
                    { lbl: 'As assignee', val: asAssignee, cls: '' },
                    { lbl: 'As assignor', val: asAssignor, cls: '' },
                    { lbl: 'Done', val: done, cls: 'ts-stat-done' },
                    { lbl: 'Overdue', val: overdue, cls: 'ts-stat-overdue' },
                    { lbl: 'A task', val: aTask, cls: '' },
                    { lbl: 'Need appr.', val: needAppr, cls: '' }
                ];
                grid.innerHTML = '';
                specs.forEach(function (s) {
                    var div = document.createElement('div');
                    div.className = 'ts-user-panel-stat ' + (s.cls || '');
                    div.innerHTML = '<div class="ts-val">' + String(s.val) + '</div><div class="ts-lbl">' + s.lbl + '</div>';
                    grid.appendChild(div);
                });
            }

            function tsRenderUserPanelTasks(rows) {
                var tbodyPanel = document.getElementById('ts-user-panel-tbody');
                var wrap = document.getElementById('ts-user-panel-table-wrap');
                var emptyEl = document.getElementById('ts-user-panel-empty');
                if (!tbodyPanel) return;
                tbodyPanel.innerHTML = '';
                var q = (document.getElementById('ts-user-panel-task-search') || {}).value;
                q = (q || '').trim().toLowerCase();
                var list = rows;
                if (q) {
                    list = rows.filter(function (t) {
                        var blob = [
                            t.title,
                            t.status,
                            t.assignee_name,
                            t.assignor_name,
                            t.group
                        ].join(' ').toLowerCase();
                        return blob.indexOf(q) !== -1;
                    });
                }
                if (list.length === 0) {
                    if (wrap) wrap.classList.add('d-none');
                    if (emptyEl) emptyEl.classList.remove('d-none');
                    return;
                }
                if (wrap) wrap.classList.remove('d-none');
                if (emptyEl) emptyEl.classList.add('d-none');
                var tsAutomatedVisualIndex = 0;
                list.forEach(function (t) {
                    var isAutoTask = t.is_automate_task == 1 || t.is_automate_task === true || String(t.is_automate_task) === '1';
                    var tr = document.createElement('tr');
                    if (isAutoTask) {
                        tr.classList.add('ts-user-panel-row-automated');
                        if (tsAutomatedVisualIndex % 2 === 1) {
                            tr.classList.add('ts-user-panel-row-automated-alt');
                        }
                        tsAutomatedVisualIndex += 1;
                    }
                    var tdTitle = document.createElement('td');
                    tdTitle.className = 'fw-medium';
                    var titleText = document.createTextNode(t.title || '—');
                    tdTitle.appendChild(titleText);
                    if (isAutoTask) {
                        tdTitle.appendChild(document.createTextNode(' '));
                        var auto = document.createElement('span');
                        auto.className = 'badge bg-warning text-dark ms-1';
                        auto.style.fontSize = '0.65rem';
                        auto.textContent = 'Auto';
                        tdTitle.appendChild(auto);
                    }
                    var tdSt = document.createElement('td');
                    var badge = document.createElement('span');
                    badge.className = 'ts-user-panel-status ' + tsStatusBadgeClass(t.status);
                    badge.textContent = t.status || '—';
                    tdSt.appendChild(badge);
                    var tdAsg = document.createElement('td');
                    tdAsg.className = 'd-none d-md-table-cell';
                    tdAsg.textContent = t.assignee_name || '—';
                    var tdAso = document.createElement('td');
                    tdAso.className = 'd-none d-lg-table-cell';
                    tdAso.textContent = t.assignor_name || '—';
                    var tdStart = document.createElement('td');
                    tdStart.className = 'text-nowrap text-muted small';
                    tdStart.textContent = tsFormatStart(t.start_date);
                    var tdOpen = document.createElement('td');
                    tdOpen.className = 'text-center';
                    if (t.id) {
                        var a = document.createElement('a');
                        a.href = tsTaskShowBase + '/' + encodeURIComponent(t.id);
                        a.className = 'btn btn-sm btn-link py-0 px-1';
                        a.textContent = 'View';
                        a.setAttribute('target', '_blank');
                        a.setAttribute('rel', 'noopener noreferrer');
                        tdOpen.appendChild(a);
                    } else {
                        tdOpen.textContent = '—';
                    }
                    tr.appendChild(tdTitle);
                    tr.appendChild(tdSt);
                    tr.appendChild(tdAsg);
                    tr.appendChild(tdAso);
                    tr.appendChild(tdStart);
                    tr.appendChild(tdOpen);
                    tbodyPanel.appendChild(tr);
                });
            }

            function tsLoadUserTasks(userName) {
                var loading = document.getElementById('ts-user-panel-loading');
                var errEl = document.getElementById('ts-user-panel-error');
                var wrap = document.getElementById('ts-user-panel-table-wrap');
                var emptyEl = document.getElementById('ts-user-panel-empty');
                if (errEl) {
                    errEl.classList.add('d-none');
                    errEl.textContent = '';
                }
                if (wrap) wrap.classList.add('d-none');
                if (emptyEl) emptyEl.classList.add('d-none');
                if (loading) loading.classList.remove('d-none');
                var url = tsTasksDataUrl + (tsTasksDataUrl.indexOf('?') >= 0 ? '&' : '?') + 'user_name=' + encodeURIComponent(userName);
                fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) {
                        if (!r.ok) throw new Error('Could not load tasks');
                        return r.json();
                    })
                    .then(function (data) {
                        if (loading) loading.classList.add('d-none');
                        tsUserPanelTasks = Array.isArray(data) ? data : [];
                        tsSetUserPanelStatsFromTasks(tsUserPanelTasks, userName);
                        tsRenderUserPanelTasks(tsUserPanelTasks);
                    })
                    .catch(function (err) {
                        if (loading) loading.classList.add('d-none');
                        tsSetUserPanelStatsFromTasks([], userName);
                        if (errEl) {
                            errEl.textContent = err && err.message ? err.message : 'Failed to load tasks.';
                            errEl.classList.remove('d-none');
                        }
                    });
            }

            var tsSearchPanel = document.getElementById('ts-user-panel-task-search');
            if (tsSearchPanel) {
                tsSearchPanel.addEventListener('input', function () {
                    tsRenderUserPanelTasks(tsUserPanelTasks);
                });
            }

            var tsOpenTmBtn = document.getElementById('ts-user-panel-open-tm');
            if (tsOpenTmBtn) {
                tsOpenTmBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (!tsUserPanelName) return;
                    var fd = new FormData();
                    fd.append('_token', tsCsrfToken);
                    fd.append('user_name', tsUserPanelName);
                    fetch(tsSetSelectedUserUrl, {
                        method: 'POST',
                        body: fd,
                        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                        credentials: 'same-origin'
                    })
                        .finally(function () {
                            window.location.href = tsTasksIndexUrl;
                        });
                });
            }

            tbody.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest && e.target.closest('.task-summary-user-tasks-dot');
                if (!btn || !tbody.contains(btn)) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                var name = (btn.getAttribute('data-user-name') || '').trim();
                if (!name) {
                    return;
                }
                var tr = btn.closest('tr');
                if (!tr) {
                    return;
                }
                var panel = tsGetUserPanelOffcanvas();
                if (!panel) {
                    var fd0 = new FormData();
                    fd0.append('_token', tsCsrfToken);
                    fd0.append('user_name', name);
                    fetch(tsSetSelectedUserUrl, {
                        method: 'POST',
                        body: fd0,
                        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                        credentials: 'same-origin'
                    }).finally(function () {
                        window.location.href = tsTasksIndexUrl;
                    });
                    return;
                }
                tsUserPanelName = name;
                var titleEl = document.getElementById('taskSummaryUserPanelLabel');
                var desEl = document.getElementById('ts-user-panel-designation');
                var emailEl = document.getElementById('ts-user-panel-email');
                var avEl = document.getElementById('ts-user-panel-avatar');
                if (titleEl) titleEl.textContent = name;
                var des = (tr.getAttribute('data-sort-designation') || '').trim();
                if (desEl) {
                    desEl.textContent = des || '—';
                    desEl.classList.toggle('d-none', !des);
                }
                var em = (tr.getAttribute('data-user-email') || '').trim();
                if (emailEl) {
                    emailEl.textContent = em || '';
                    emailEl.classList.toggle('d-none', !em);
                }
                if (avEl) {
                    var img = tr.querySelector('.task-summary-avatar');
                    var src = img && img.getAttribute('src');
                    if (src) {
                        avEl.setAttribute('src', src);
                        avEl.classList.remove('d-none');
                    } else {
                        avEl.classList.add('d-none');
                    }
                }
                tsSetUserPanelStatsLoading();
                if (tsSearchPanel) tsSearchPanel.value = '';
                tsUserPanelTasks = [];
                panel.show();
                tsLoadUserTasks(name);
            });

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
