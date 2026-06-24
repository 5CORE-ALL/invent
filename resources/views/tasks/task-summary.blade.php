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
        :root {
            --avatar-size: 35px;
        }
        .task-summary-avatar {
            width: var(--avatar-size);
            height: var(--avatar-size);
            max-width: none;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid rgba(15, 23, 42, 0.08);
            transition: box-shadow 0.2s ease, transform 0.2s ease, width 0.3s ease, height 0.3s ease;
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
        .task-summary-col-overdue {
            color: #dc2626 !important;
            font-weight: 700;
        }
        .task-summary-col-overdue-positive {
            color: #dc2626 !important;
            font-weight: 700;
        }
        .task-summary-col-missed {
            color: #dc2626 !important;
            font-weight: 700;
            background-color: #fef2f2;
        }
        /* TAT colour scale —
             ≥ 3 days   → red text
             1 ≤ x < 3  → green text
             < 1 day    → pink background (a bit too short / suspicious) */
        .task-summary-tat-high {
            color: #dc2626 !important;
            font-weight: 700;
        }
        .task-summary-tat-mid {
            color: #15803d !important;
            font-weight: 700;
        }
        .task-summary-tat-low {
            background-color: #fce7f3 !important;
            color: #831843 !important;
            font-weight: 600;
        }
        /* "A Task H" column hidden by request — kept in markup so sort key
           stays addressable from JS; remove these rules to bring it back. */
        .task-summary-table th.task-summary-col-a-task-h,
        .task-summary-table td.task-summary-col-a-task-h {
            display: none !important;
        }
        /* Highlight rows where the team member is a Manager. The rule is on
           [data-sort-org_level="mgr"] so it works in both flat and grouped
           views, and uses !important so it wins over .table-striped's
           alternating cell colour. */
        .task-summary-table tbody tr.task-summary-row[data-sort-org_level="mgr"] > td {
            background-color: #fef9c3 !important;
        }
        .task-summary-table.table-hover tbody tr.task-summary-row[data-sort-org_level="mgr"]:hover > td {
            background-color: #fde68a !important;
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
            min-width: 220px;
            max-width: 560px;
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
        /* Single-row top bar — wraps gracefully on small screens */
        .task-summary-topbar {
            row-gap: 0.4rem;
        }
        @media (max-width: 575.98px) {
            .task-summary-topbar .task-summary-topbar-left,
            .task-summary-topbar .task-summary-topbar-right,
            .task-summary-topbar .task-summary-search-wrap {
                flex: 1 1 100%;
            }
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
            /* Icon hidden by request — column headers stay clickable for sort. */
            display: none !important;
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

        /* ------------------------------------------------------------------
           Hierarchy grouping (Director → Mgr → Exec)
           ------------------------------------------------------------------ */
        .task-summary-group-toggle-wrap {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            padding: 0.2rem 0.55rem;
            font-size: 0.78rem;
            color: #475569;
        }
        .task-summary-group-toggle-wrap .form-check {
            margin-bottom: 0;
            min-height: 0;
            padding-left: 1.65rem;
        }
        .task-summary-group-toggle-wrap .form-check-input {
            margin-top: 0.18rem;
            cursor: pointer;
        }
        .task-summary-group-toggle-wrap .form-check-input:checked {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
        }
        .task-summary-group-toggle-wrap .form-check-label {
            font-weight: 600;
            color: #1f2937;
            cursor: pointer;
        }
        .task-summary-group-collapse-actions .btn {
            font-size: 0.7rem;
            padding: 0.1rem 0.5rem;
            background: transparent;
            border: 1px solid #cbd5e1;
            color: #475569;
            border-radius: 999px;
        }
        .task-summary-group-collapse-actions .btn:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .task-summary-table tbody tr.task-summary-group-header td {
            background-color: #f8fafc;
            font-weight: 700;
            text-align: left;
            padding-top: 0.55rem;
            padding-bottom: 0.55rem;
            border-top: 2px solid #cbd5e1;
            border-bottom: 1px solid #e2e8f0;
            white-space: normal;
            color: #0f172a;
            position: sticky;
            top: 0;
            z-index: 0;
        }
        .task-summary-table tbody tr.task-summary-group-header[data-level="director"] td {
            background-color: #eef2ff;
            border-top-color: #6366f1;
            color: #1e3a8a;
        }
        .task-summary-table tbody tr.task-summary-group-header[data-level="mgr"] td {
            background-color: #ecfeff;
            border-top-color: #06b6d4;
            color: #0e7490;
        }
        .task-summary-table tbody tr.task-summary-group-header[data-level="others"] td {
            background-color: #f8fafc;
            border-top-color: #94a3b8;
            color: #475569;
        }
        .task-summary-group-header .task-summary-group-header-inner {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            text-align: left;
        }
        .task-summary-group-header .task-summary-group-chevron {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.4rem;
            height: 1.4rem;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.06);
            color: inherit;
            border: none;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.2s ease;
            flex-shrink: 0;
        }
        .task-summary-group-header .task-summary-group-chevron:hover {
            background: rgba(15, 23, 42, 0.12);
        }
        .task-summary-group-header[data-collapsed="true"] .task-summary-group-chevron {
            transform: rotate(-90deg);
        }
        /* "Focus on this group" button — same footprint as the chevron */
        .task-summary-group-header .task-summary-group-focus {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.4rem;
            height: 1.4rem;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.06);
            color: inherit;
            border: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.15s ease;
            flex-shrink: 0;
            margin-right: 0.1rem;
        }
        .task-summary-group-header .task-summary-group-focus:hover {
            background: rgba(15, 23, 42, 0.18);
            transform: scale(1.1);
        }
        /* Highlight the currently-focused group's button so the user knows
           a second click will restore the full view. */
        .task-summary-group-header.is-focused .task-summary-group-focus {
            background: #1d4ed8;
            color: #fff;
            box-shadow: 0 0 0 2px rgba(29, 78, 216, 0.25);
        }
        .task-summary-group-header.is-focused .task-summary-group-focus:hover {
            background: #1e40af;
        }
        .task-summary-group-header .task-summary-group-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 800;
            padding: 0.1em 0.55em;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.08);
            color: inherit;
            flex-shrink: 0;
        }
        .task-summary-group-header[data-level="director"] .task-summary-group-label {
            background: #c7d2fe;
            color: #1e3a8a;
        }
        .task-summary-group-header[data-level="mgr"] .task-summary-group-label {
            background: #a5f3fc;
            color: #155e75;
        }
        .task-summary-group-header[data-level="others"] .task-summary-group-label {
            background: #e2e8f0;
            color: #334155;
        }
        .task-summary-group-header .task-summary-group-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: inherit;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .task-summary-group-header .task-summary-group-meta {
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 500;
            margin-left: auto;
            flex-shrink: 0;
        }
        /* Indent leaf rows visually based on their hierarchy depth */
        .task-summary-table tbody tr.task-summary-row[data-depth="1"] td:first-child {
            padding-left: 1.5rem;
        }
        .task-summary-table tbody tr.task-summary-row[data-depth="2"] td:first-child {
            padding-left: 2.5rem;
        }
        .task-summary-table tbody tr.task-summary-row[data-depth="3"] td:first-child {
            padding-left: 3.5rem;
        }
        .task-summary-table tbody tr.task-summary-row[data-depth="1"] .task-summary-member-cell-inner::before,
        .task-summary-table tbody tr.task-summary-row[data-depth="2"] .task-summary-member-cell-inner::before,
        .task-summary-table tbody tr.task-summary-row[data-depth="3"] .task-summary-member-cell-inner::before {
            content: '↳';
            color: #94a3b8;
            margin-right: 0.3rem;
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
                    

                    @php
                        $visibility = $visibility ?? null;
                        $scope = $visibility['scope'] ?? 'all';
                        $scopeIcon = match ($scope) {
                            'all' => 'ri-global-line',
                            'team' => 'ri-team-line',
                            'self' => 'ri-user-line',
                            default => 'ri-eye-line',
                        };
                        $scopeBg = match ($scope) {
                            'all' => '#eef2ff',
                            'team' => '#ecfeff',
                            'self' => '#fef3c7',
                            default => '#f1f5f9',
                        };
                        $scopeFg = match ($scope) {
                            'all' => '#1e3a8a',
                            'team' => '#0e7490',
                            'self' => '#92400e',
                            default => '#334155',
                        };
                        $scopeBorder = match ($scope) {
                            'all' => '#c7d2fe',
                            'team' => '#a5f3fc',
                            'self' => '#fcd34d',
                            default => '#cbd5e1',
                        };
                    @endphp
                    @if ($visibility && $scope !== 'all')
                        <div class="task-summary-visibility-banner d-flex flex-wrap align-items-center gap-2 mb-3"
                             role="status"
                             style="background: {{ $scopeBg }}; color: {{ $scopeFg }}; border: 1px solid {{ $scopeBorder }}; border-left: 4px solid {{ $scopeFg }}; border-radius: 12px; padding: 0.55rem 0.85rem; font-size: 0.84rem;">
                            <i class="{{ $scopeIcon }}" aria-hidden="true" style="font-size:1.1rem;"></i>
                            <span class="fw-semibold" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.1em 0.5em; border-radius: 999px; background: rgba(255,255,255,0.55);">
                                {{ $visibility['role_label'] }} view
                            </span>
                            <span class="flex-grow-1">{{ $visibility['label'] }}</span>
                            @if ($scope === 'team')
                                <small style="color: {{ $scopeFg }}; opacity: 0.8;">
                                    <i class="ri-information-line me-1"></i>To see other managers' or directors' data, ask an admin to update your role.
                                </small>
                            @endif
                        </div>
                    @endif

                    @if (!empty($rows) && count($rows))
                        <div class="task-summary-analytics">
                            <div class="task-summary-analytics-badges">
                                <button type="button" class="task-summary-analytics-badge" data-ts-metric="assigned" data-ts-title="Members Analytics" title="Active members with at least one assignee task" aria-label="Open members chart">
                                    <div class="task-summary-analytics-badge-label">Members</div>
                                    <div class="task-summary-analytics-badge-value" id="ts-analytics-val-assigned">{{ number_format($taskDashboardStats['assigned_members']) }}</div>
                                    <i class="ri-team-line" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="task-summary-analytics-badge" data-ts-metric="total" data-ts-title="Total Task Analytics" aria-label="Open total tasks chart">
                                    <div class="task-summary-analytics-badge-label">Total tasks</div>
                                    <div class="task-summary-analytics-badge-value" id="ts-analytics-val-total">{{ number_format($taskDashboardStats['total_tasks']) }}</div>
                                    <i class="ri-line-chart-line" aria-hidden="true"></i>
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
                        {{-- Single-row top controls: Group toggle · Search (flex grow) · Avatar size --}}
                        <div class="task-summary-topbar d-flex flex-wrap align-items-center mb-3 gap-2">
                            <div class="d-flex flex-wrap align-items-center gap-2 task-summary-topbar-left">
                                <div class="task-summary-group-toggle-wrap">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="task-summary-group-toggle" />
                                        <label class="form-check-label" for="task-summary-group-toggle">
                                            <i class="ri-organization-chart me-1"></i>Group by hierarchy
                                        </label>
                                    </div>
                                </div>
                                <div class="task-summary-group-toggle-wrap">
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="task-summary-juniors-only-toggle" />
                                        <label class="form-check-label" for="task-summary-juniors-only-toggle" title="Show only members who have juniors tagged under them (plus those juniors)">
                                            <i class="ri-user-shared-line me-1"></i>Only with juniors
                                        </label>
                                    </div>
                                </div>
                                <div class="task-summary-group-collapse-actions d-none" id="task-summary-group-collapse-actions">
                                    <button type="button" class="btn" data-action="expand-all" title="Expand every group">
                                        <i class="ri-arrow-down-s-line"></i> Expand all
                                    </button>
                                    <button type="button" class="btn" data-action="collapse-all" title="Collapse every group">
                                        <i class="ri-arrow-up-s-line"></i> Collapse all
                                    </button>
                                </div>
                            </div>
                            <div class="task-summary-search-wrap flex-grow-1 m-0">
                                <label for="task-summary-search" class="visually-hidden">Search team members</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text" aria-hidden="true"><i class="ri-search-line"></i></span>
                                    <input type="search"
                                           class="form-control"
                                           id="task-summary-search"
                                           placeholder="Search by name or designation…"
                                           autocomplete="off"
                                           spellcheck="false" />
                                </div>
                            </div>
                            {{-- Avatar size controls hidden by request (fixed at 35px) --}}
                            <div class="d-none task-summary-topbar-right">
                                <span class="text-muted small d-none d-md-inline">Avatar:</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="adjustAvatarSize(-5)" title="Decrease size">
                                    <i class="mdi mdi-minus"></i>
                                </button>
                                <span id="avatar-size-display" class="badge bg-light text-dark" style="min-width: 42px;">35px</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="adjustAvatarSize(5)" title="Increase size">
                                    <i class="mdi mdi-plus"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="resetAvatarSize()" title="Reset to default">
                                    <i class="mdi mdi-refresh"></i>
                                </button>
                            </div>
                        </div>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-hover table-striped table-bordered mb-0 task-summary-table">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="task-summary-th-sort task-summary-col-member" data-sort-key="member" data-sort-type="text" title="Member — sort by team member name" role="button" tabindex="0">
                                        Member <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" title="TM — open this member's task panel (badge colour reflects their org level)">
                                        TM
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="member" data-sort-type="text" title="Team — sort by team member" role="button" tabindex="0">
                                        Team <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="designation" data-sort-type="text" title="Sort by designation" role="button" tabindex="0">
                                        Designation <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="org_level" data-sort-type="text" title="Sort by org level (Mgr / Director / Exec)" role="button" tabindex="0">
                                        Role <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" title="Open Roles &amp; Responsibilities (per designation, AI-seeded, tracks per-person progress)">
                                        R&amp;R
                                    </th>
                                    <th scope="col" title="Open the Checklist for R&amp;R (weighted checkpoints per R&amp;R item, AI-seeded, tracks per-person score)">
                                        CL R&amp;R
                                    </th>
                                    <th scope="col" title="Open the Manager checklist (per-designation leadership checkpoints, AI-seeded, score also includes juniors' average)">
                                        CL Mgr
                                    </th>
                                    <th scope="col" title="Open the General Checklist (single team-wide list, AI-seeded, tracks General score)">
                                        CL Gen
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="task" data-sort-type="number" title="Sort by assignee task count" role="button" tabindex="0">
                                        Task <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="l30_hrs" data-sort-type="number" title="Attendance — current month work hours from Team Logger ({{ \Carbon\Carbon::now()->format('M Y') }})" role="button" tabindex="0">
                                        ATT <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="assignor_task" data-sort-type="number" title="Task Given — sort by tasks this member has assigned to others" role="button" tabindex="0">
                                        Task-G <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="done" data-sort-type="number" title="Sort by done count" role="button" tabindex="0">
                                        Done <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="overdue" data-sort-type="number" title="Overdue — number of overdue tasks (sortable)" role="button" tabindex="0">
                                        O-Due <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="tat_l30" data-sort-type="float" title="TAT — average Turn-Around Time in days (task start → completion) over the last 30 days" role="button" tabindex="0">
                                        TAT <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="missed_l30" data-sort-type="number" title="Miss — L30 missed tasks (is_missed = true, last 30 days)" role="button" tabindex="0">
                                        Miss <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="a_task" data-sort-type="number" title="Sort by A Task count" role="button" tabindex="0">
                                        A Task <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort task-summary-col-a-task-h" data-sort-key="a_task_h" data-sort-type="number" title="Sort by automated task ETC hours (rounded)" role="button" tabindex="0">
                                        A Task H <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" class="task-summary-th-sort" data-sort-key="need_approval" data-sort-type="number" title="Appr — number of tasks pending approval (status = Need Approval)" role="button" tabindex="0">
                                        APPR <i class="task-summary-sort-icon ri-arrow-up-down-line" aria-hidden="true"></i>
                                    </th>
                                    <th scope="col" title="Summary — open the team-member dashboard (own metrics + tagged juniors)">
                                        Summ
                                    </th>
                                    <th scope="col" title="KPI — open the badges modal (tagged badges for this team member)">
                                        KPI
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
                                        data-user-id="{{ (int) ($row['user_id'] ?? 0) }}"
                                        data-sort-member="{{ e($row['team_member']) }}"
                                        data-sort-designation="{{ e($row['designation'] ?? '') }}"
                                        data-sort-org_level="{{ e($row['org_level'] ?? '') }}"
                                        data-sort-task="{{ (int) ($row['task'] ?? 0) }}"
                                        data-sort-l30_hrs="{{ (float) ($row['l30_hrs'] ?? 0) }}"
                                        data-sort-assignor_task="{{ (int) ($row['assignor_task'] ?? 0) }}"
                                        data-sort-overdue="{{ (int) ($row['overdue'] ?? 0) }}"
                                        data-sort-tat_l30="{{ $row['tat_l30_days'] !== null ? (float) $row['tat_l30_days'] : -1 }}"
                                        data-sort-missed_l30="{{ (int) ($row['missed_l30'] ?? 0) }}"
                                        data-sort-a_task="{{ (int) ($row['a_task'] ?? 0) }}"
                                        data-sort-a_task_h="{{ (int) ($row['a_task_h'] ?? 0) }}"
                                        data-sort-need_approval="{{ (int) ($row['need_approval'] ?? 0) }}"
                                        data-sort-done="{{ (int) ($row['done'] ?? 0) }}">
                                        @php
                                            $tmLevel = strtolower((string) ($row['org_level'] ?? ''));
                                            $tmBadgeMod = $tmLevel === 'director'
                                                ? 'task-summary-tm-badge-director'
                                                : ($tmLevel === 'mgr'
                                                    ? 'task-summary-tm-badge-mgr'
                                                    : ($tmLevel === 'exec' ? 'task-summary-tm-badge-exec' : ''));
                                        @endphp
                                        <td class="task-summary-col-member">
                                            <span class="task-summary-member-cell-inner">
                                                <span class="task-summary-member-name">{{ $row['team_member'] }}</span>
                                                <button type="button"
                                                        class="task-summary-tm-profile-btn"
                                                        data-user-id="{{ (int) ($row['user_id'] ?? 0) }}"
                                                        data-user-name="{{ e($row['team_member']) }}"
                                                        title="Open team member profile (large image, scores, juniors)"
                                                        aria-label="Open profile for {{ e($row['team_member']) }}">
                                                    <i class="ri-search-eye-line" style="font-size:1rem;" aria-hidden="true"></i>
                                                </button>
                                            </span>
                                        </td>
                                        <td class="task-summary-col-tm text-center">
                                            <button type="button"
                                                    class="task-summary-tm-badge task-summary-user-tasks-dot {{ $tmBadgeMod }}"
                                                    data-user-name="{{ e($row['team_member']) }}"
                                                    title="View tasks panel for {{ e($row['team_member']) }}"
                                                    aria-label="View tasks panel for {{ e($row['team_member']) }}">TM</button>
                                        </td>
                                        <td class="task-summary-avatar-cell">
                                            <span class="task-summary-avatar-wrap">
                                                <img src="{{ $avatarUrl }}" alt="" class="task-summary-avatar" loading="lazy" />
                                            </span>
                                        </td>
                                        @php
                                            // Compact display: "Manager" → "Mgr", "Executive" → "Exc".
                                            // Whole-word match so "Management" / "Execution" are not affected.
                                            // Full original is still kept in the cell title for hover.
                                            $designationFull = (string) ($row['designation'] ?? '');
                                            $designationCompact = $designationFull === ''
                                                ? '—'
                                                : preg_replace_callback(
                                                    '/\b(Manager|Executive)\b/i',
                                                    fn ($m) => strtolower($m[1]) === 'manager' ? 'Mgr' : 'Exc',
                                                    $designationFull
                                                );
                                        @endphp
                                        <td @if($designationFull !== '' && $designationCompact !== $designationFull) title="{{ e($designationFull) }}" @endif>{{ $designationCompact }}</td>
                                        <td class="task-summary-role-cell text-center">
                                            @php
                                                $roleUserId = (int) ($row['user_id'] ?? 0);
                                                $roleLevel = strtolower((string) ($row['org_level'] ?? ''));
                                                // Tags dot is hidden when no user record exists for the row.
                                                $roleDisabled = $roleUserId === 0;
                                                $orgCtrl = $orgLevelControl ?? ['can_edit_any' => false, 'is_manager' => false];
                                                $canEditAnyRole = (bool) ($orgCtrl['can_edit_any'] ?? false);
                                                $viewerIsManager = (bool) ($orgCtrl['is_manager'] ?? false);
                                                // Manager: can only edit rows whose current role is Exec or empty.
                                                $managerCanEditThisRow = $viewerIsManager
                                                    && ($roleLevel === '' || $roleLevel === 'exec');
                                                $roleSelectEnabled = $roleUserId !== 0
                                                    && ($canEditAnyRole || $managerCanEditThisRow);
                                                if ($canEditAnyRole) {
                                                    $roleDisabledTitle = '';
                                                } elseif ($viewerIsManager) {
                                                    $roleDisabledTitle = $managerCanEditThisRow
                                                        ? 'Managers can only assign — / Exec.'
                                                        : 'Managers can only change Executive (or unassigned) users. Ask a Director.';
                                                } else {
                                                    $roleDisabledTitle = 'Only Directors (or admins) can change roles.';
                                                }
                                            @endphp
                                            <select class="form-select form-select-sm task-summary-role-select"
                                                    data-user-id="{{ $roleUserId }}"
                                                    data-user-name="{{ e($row['team_member']) }}"
                                                    aria-label="Org role for {{ e($row['team_member']) }}"
                                                    @if(! $roleSelectEnabled) disabled title="{{ $roleDisabledTitle }}" @endif>
                                                <option value="" @selected($roleLevel === '')>—</option>
                                                @if($canEditAnyRole || $roleLevel === 'mgr' || ! $viewerIsManager)
                                                    <option value="mgr" @selected($roleLevel === 'mgr')>Mgr</option>
                                                @endif
                                                @if($canEditAnyRole || $roleLevel === 'director' || ! $viewerIsManager)
                                                    <option value="director" @selected($roleLevel === 'director')>Director</option>
                                                @endif
                                                <option value="exec" @selected($roleLevel === 'exec')>Exec</option>
                                            </select>
                                            @if ($canEditTags ?? false)
                                                <button type="button"
                                                        class="task-summary-role-mgr-dot task-summary-role-mgr-tags-btn"
                                                        data-user-id="{{ $roleUserId }}"
                                                        data-user-name="{{ e($row['team_member']) }}"
                                                        data-designation="{{ e($row['designation'] ?? '') }}"
                                                        title="Open tags — assign the people this {{ $roleLevel === 'director' ? 'director' : 'manager' }} is responsible for"
                                                        aria-label="Open tags for {{ e($row['team_member']) }}"
                                                        @if(! in_array($roleLevel, ['mgr', 'director'], true) || $roleDisabled) style="display:none;" @endif></button>
                                            @endif
                                        </td>
                                        <td class="task-summary-rr-cell text-center">
                                            @php
                                                $rrDesignation = trim((string) ($row['designation'] ?? ''));
                                                $rrUserId = (int) ($row['user_id'] ?? 0);
                                                $rrCanManage = (bool) ($row['can_manage'] ?? false);
                                                // Locked because the viewer (a manager) doesn't have permission
                                                // to mutate data for this row (target is another mgr/director).
                                                $rrPermissionLocked = ! $rrCanManage;
                                                $rrDisabled = $rrDesignation === '' || $rrUserId === 0 || $rrPermissionLocked;
                                                if ($rrDesignation === '' || $rrUserId === 0) {
                                                    $rrLockedTitle = 'Set a designation on this user to view R&R';
                                                } elseif ($rrPermissionLocked) {
                                                    $rrLockedTitle = 'Managers can only manage their tagged juniors or Executives.';
                                                } else {
                                                    $rrLockedTitle = 'View R&R for ' . e($rrDesignation);
                                                }
                                            @endphp
                                            <button type="button"
                                                    class="rr-search-icon-btn task-summary-rr-btn"
                                                    data-user-id="{{ $rrUserId }}"
                                                    data-user-name="{{ e($row['team_member']) }}"
                                                    data-designation="{{ e($rrDesignation) }}"
                                                    @if($rrDisabled) disabled @endif
                                                    title="{{ $rrLockedTitle }}"
                                                    aria-label="Open R&R for {{ e($row['team_member']) }}">
                                                <i class="ri-search-eye-line" style="font-size:1.15rem;" aria-hidden="true"></i>
                                            </button>
                                        </td>
                                        <td class="task-summary-clrr-cell text-center">
                                            @php
                                                $clrrLockedTitle = $rrPermissionLocked
                                                    ? 'Managers can only manage their tagged juniors or Executives.'
                                                    : (($rrDesignation === '' || $rrUserId === 0)
                                                        ? 'Set a designation on this user to view CL R&R'
                                                        : 'View CL R&R checklist & score for ' . e($rrDesignation));
                                            @endphp
                                            <button type="button"
                                                    class="clrr-search-icon-btn task-summary-clrr-btn"
                                                    data-user-id="{{ $rrUserId }}"
                                                    data-user-name="{{ e($row['team_member']) }}"
                                                    data-designation="{{ e($rrDesignation) }}"
                                                    @if($rrDisabled) disabled @endif
                                                    title="{{ $clrrLockedTitle }}"
                                                    aria-label="Open CL R&R for {{ e($row['team_member']) }}">
                                                <i class="ri-search-eye-line" style="font-size:1.15rem;" aria-hidden="true"></i>
                                            </button>
                                            @unless($rrDisabled)
                                                <span class="cl-score-chip is-clrr" title="CL R&R score">{{ (int) ($row['score_clrr'] ?? 0) }}%</span>
                                                <button type="button"
                                                        class="cl-history-dot is-clrr task-summary-score-history-btn"
                                                        data-user-id="{{ $rrUserId }}"
                                                        data-user-name="{{ e($row['team_member']) }}"
                                                        data-designation="{{ e($rrDesignation) }}"
                                                        data-score-type="clrr"
                                                        title="View lifetime CL R&R score history"
                                                        aria-label="CL R&R score history for {{ e($row['team_member']) }}">
                                                    <i class="ri-line-chart-line" aria-hidden="true"></i>
                                                </button>
                                            @endunless
                                        </td>
                                        <td class="task-summary-clmgr-cell text-center">
                                            @php
                                                $clmgrLockedTitle = $rrPermissionLocked
                                                    ? 'Managers can only manage their tagged juniors or Executives.'
                                                    : (($rrDesignation === '' || $rrUserId === 0)
                                                        ? 'Set a designation on this user to view CL Mgr'
                                                        : 'View Manager checklist & combined score for ' . e($row['team_member']));
                                            @endphp
                                            <button type="button"
                                                    class="clmgr-search-icon-btn task-summary-clmgr-btn"
                                                    data-user-id="{{ $rrUserId }}"
                                                    data-user-name="{{ e($row['team_member']) }}"
                                                    data-designation="{{ e($rrDesignation) }}"
                                                    @if($rrDisabled) disabled @endif
                                                    title="{{ $clmgrLockedTitle }}"
                                                    aria-label="Open CL Mgr for {{ e($row['team_member']) }}">
                                                <i class="ri-search-eye-line" style="font-size:1.15rem;" aria-hidden="true"></i>
                                            </button>
                                            @unless($rrDisabled)
                                                <span class="cl-score-chip is-clmgr" title="CL Mgr own score (combined includes juniors — open modal)">{{ (int) ($row['score_clmgr'] ?? 0) }}%</span>
                                                <button type="button"
                                                        class="cl-history-dot is-clmgr task-summary-score-history-btn"
                                                        data-user-id="{{ $rrUserId }}"
                                                        data-user-name="{{ e($row['team_member']) }}"
                                                        data-designation="{{ e($rrDesignation) }}"
                                                        data-score-type="clmgr"
                                                        title="View lifetime CL Mgr combined score history"
                                                        aria-label="CL Mgr score history for {{ e($row['team_member']) }}">
                                                    <i class="ri-line-chart-line" aria-hidden="true"></i>
                                                </button>
                                            @endunless
                                        </td>
                                        <td class="task-summary-clgen-cell text-center">
                                            @php
                                                $clgenDisabled = $rrUserId === 0 || $rrPermissionLocked;
                                                if ($rrUserId === 0) {
                                                    $clgenTitle = 'No user record found for this row';
                                                } elseif ($rrPermissionLocked) {
                                                    $clgenTitle = 'Managers can only manage their tagged juniors or Executives.';
                                                } else {
                                                    $clgenTitle = 'View General Checklist & score for ' . e($row['team_member']);
                                                }
                                            @endphp
                                            <button type="button"
                                                    class="clgen-search-icon-btn task-summary-clgen-btn"
                                                    data-user-id="{{ $rrUserId }}"
                                                    data-user-name="{{ e($row['team_member']) }}"
                                                    @if($clgenDisabled) disabled @endif
                                                    title="{{ $clgenTitle }}"
                                                    aria-label="Open General Checklist for {{ e($row['team_member']) }}">
                                                <i class="ri-search-eye-line" style="font-size:1.15rem;" aria-hidden="true"></i>
                                            </button>
                                            @if($rrUserId > 0)
                                                <span class="cl-score-chip is-clgen" title="CL Gen (global) score">{{ (int) ($row['score_clgen'] ?? 0) }}%</span>
                                                <button type="button"
                                                        class="cl-history-dot is-clgen task-summary-score-history-btn"
                                                        data-user-id="{{ $rrUserId }}"
                                                        data-user-name="{{ e($row['team_member']) }}"
                                                        data-designation="{{ e($row['designation'] ?? '') }}"
                                                        data-score-type="clgen"
                                                        title="View lifetime CL Gen score history"
                                                        aria-label="CL Gen score history for {{ e($row['team_member']) }}">
                                                    <i class="ri-line-chart-line" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                        </td>
                                        <td class="task-summary-num">{{ $row['task'] }}</td>
                                        <td class="task-summary-num" style="background-color: #e8f5e9;">
                                            @php
                                                $hours = $row['l30_hrs'] ?? 0;
                                                $roundedHours = round($hours);
                                            @endphp
                                            {{ $roundedHours > 0 ? $roundedHours . 'h' : '—' }}
                                        </td>
                                        <td class="task-summary-num">{{ $row['assignor_task'] }}</td>
                                        <td class="task-summary-num task-summary-col-done">{{ $row['done'] }}</td>
                                        <td class="task-summary-num task-summary-col-overdue">{{ $row['overdue'] }}</td>
                                        @php
                                            $tat = $row['tat_l30_days'] ?? null;
                                            $tatClass = '';
                                            $tatDisplay = '—';
                                            if ($tat !== null) {
                                                $tatVal = (float) $tat;
                                                // > 2 days is shown as a rounded integer; anything ≤ 2 keeps one decimal.
                                                $tatDisplay = ($tatVal > 2)
                                                    ? ((string) (int) round($tatVal)) . 'd'
                                                    : number_format($tatVal, 1) . 'd';
                                                if ($tatVal >= 3) {
                                                    $tatClass = 'task-summary-tat-high';   // red text
                                                } elseif ($tatVal < 1) {
                                                    $tatClass = 'task-summary-tat-low';    // pink bg
                                                } else {
                                                    $tatClass = 'task-summary-tat-mid';    // 1 ≤ x < 3 → green text
                                                }
                                            }
                                        @endphp
                                        <td class="task-summary-num {{ $tatClass }}"
                                            @if($tat !== null) title="Avg TAT over last 30 days · based on {{ (int) ($row['tat_l30_count'] ?? 0) }} completed task{{ ((int) ($row['tat_l30_count'] ?? 0) === 1) ? '' : 's' }} · raw {{ number_format((float) $tat, 1) }}d" @else title="No tasks completed in the last 30 days" @endif>
                                            {{ $tatDisplay }}
                                        </td>
                                        <td class="task-summary-num task-summary-col-missed"
                                            title="Missed tasks in the last 30 days (is_missed = true · by start_date)">
                                            {{ (int) ($row['missed_l30'] ?? 0) }}
                                        </td>
                                        <td class="task-summary-num">{{ $row['a_task'] }}</td>
                                        <td class="task-summary-num task-summary-col-a-task-h" title="Total ETC hours (assignee) for automated tasks, rounded">{{ (int) ($row['a_task_h'] ?? 0) }}</td>
                                        <td class="task-summary-num">{{ $row['need_approval'] }}</td>
                                        <td>
                                            @php
                                                $kpiData = [];
                                                for ($k = 1; $k <= 5; $k++) {
                                                    $kpiData[] = [
                                                        'label' => $row['kpi_' . $k . '_label'] ?? ('KPI ' . $k),
                                                        'value' => $row['kpi_' . $k] ?? null,
                                                    ];
                                                }
                                            @endphp
                                            <button type="button"
                                                    class="kpi-search-icon-btn task-summary-kpi-btn"
                                                    data-user-id="{{ (int) ($row['user_id'] ?? 0) }}"
                                                    data-user-name="{{ e($row['team_member']) }}"
                                                    data-kpi="{{ e(json_encode($kpiData)) }}"
                                                    title="Open dashboard for {{ e($row['team_member']) }} (own metrics + tagged juniors)"
                                                    aria-label="Open dashboard for {{ e($row['team_member']) }}">
                                                <i class="ri-search-eye-line" style="font-size:1.15rem;" aria-hidden="true"></i>
                                            </button>
                                        </td>
                                        <td class="task-summary-kpi-badges-cell text-center">
                                            <button type="button"
                                                    class="kpi-badges-search-icon-btn task-summary-kpi-badges-btn"
                                                    data-user-id="{{ (int) ($row['user_id'] ?? 0) }}"
                                                    data-user-name="{{ e($row['team_member']) }}"
                                                    data-designation="{{ e($row['designation'] ?? '') }}"
                                                    @if((int) ($row['user_id'] ?? 0) === 0) disabled title="No user record found for this row" @else title="View KPI badges tagged on {{ e($row['team_member']) }}" @endif
                                                    aria-label="Open KPI badges for {{ e($row['team_member']) }}">
                                                <i class="ri-search-eye-line" style="font-size:1.15rem;" aria-hidden="true"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="21" class="text-center text-muted py-4">
                                            @if (($visibility['scope'] ?? 'all') !== 'all')
                                                No team members visible to you. Ask an admin to update your Role (Mgr/Director) or tag juniors under you.
                                            @else
                                                No team members found.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                                @if (!empty($rows) && count($rows))
                                    <tr id="task-summary-filter-empty" class="d-none">
                                        <td colspan="21" class="text-center text-muted py-4">No matching team members.</td>
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

    @include('partials.r-and-r')
    @include('partials.cl-r-and-r')
    @include('partials.cl-mgr')
    @include('partials.cl-gen')
    @include('partials.mgr-tags')
    @include('partials.user-dashboard')
    @include('partials.score-history')
    @include('partials.team-member-profile')
    @include('partials.user-badges')

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

    <div class="modal fade" id="taskSummaryKpiModal" tabindex="-1" aria-labelledby="taskSummaryKpiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="taskSummaryKpiModalLabel">
                        <i class="ri-bar-chart-box-line me-2" aria-hidden="true"></i><span id="taskSummaryKpiModalName">KPI</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">KPI</th>
                                    <th scope="col" class="text-end">Value</th>
                                </tr>
                            </thead>
                            <tbody id="taskSummaryKpiModalBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
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
            <div class="p-3 border-bottom bg-white" id="ts-user-panel-kpi-wrap">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="ri-bar-chart-box-line" style="color:#0d9488;" aria-hidden="true"></i>
                    <span class="ts-lbl" style="color:#0f766e;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;font-size:0.7rem;">KPI Dashboard</span>
                </div>
                <div class="ts-user-panel-stat-grid" id="ts-user-panel-kpi" aria-label="KPI cards for this user"></div>
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
                        a_task_h: parseInt(tr.getAttribute('data-sort-a_task_h'), 10) || 0,
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

            var kpiModalEl = document.getElementById('taskSummaryKpiModal');

            function tsGetRowKpiList(tr) {
                if (!tr) {
                    return [];
                }
                var kpiBtn = tr.querySelector('.task-summary-kpi-btn');
                if (!kpiBtn) {
                    return [];
                }
                try {
                    return JSON.parse(kpiBtn.getAttribute('data-kpi') || '[]');
                } catch (err) {
                    return [];
                }
            }

            function tsRenderUserPanelKpi(kpiList) {
                var grid = document.getElementById('ts-user-panel-kpi');
                var wrap = document.getElementById('ts-user-panel-kpi-wrap');
                if (!grid) {
                    return;
                }
                grid.innerHTML = '';
                if (!kpiList || !kpiList.length) {
                    if (wrap) wrap.classList.add('d-none');
                    return;
                }
                if (wrap) wrap.classList.remove('d-none');
                kpiList.forEach(function (kpi) {
                    var div = document.createElement('div');
                    div.className = 'ts-user-panel-stat';
                    var valStr = (kpi.value === null || kpi.value === undefined || kpi.value === '') ? '—' : String(kpi.value);
                    var valEl = document.createElement('div');
                    valEl.className = 'ts-val';
                    valEl.textContent = valStr;
                    var lblEl = document.createElement('div');
                    lblEl.className = 'ts-lbl';
                    lblEl.textContent = kpi.label || 'KPI';
                    div.appendChild(valEl);
                    div.appendChild(lblEl);
                    grid.appendChild(div);
                });
            }

            function openKpiModal(name, kpiList) {
                var nameEl = document.getElementById('taskSummaryKpiModalName');
                var bodyEl = document.getElementById('taskSummaryKpiModalBody');
                if (nameEl) {
                    nameEl.textContent = name ? (name + ' — KPI') : 'KPI';
                }
                if (bodyEl) {
                    bodyEl.innerHTML = '';
                    (kpiList || []).forEach(function (kpi) {
                        var tr = document.createElement('tr');
                        var tdLabel = document.createElement('td');
                        tdLabel.textContent = kpi.label || '—';
                        var tdVal = document.createElement('td');
                        tdVal.className = 'text-end task-summary-num';
                        tdVal.textContent = (kpi.value === null || kpi.value === undefined || kpi.value === '') ? '—' : kpi.value;
                        tr.appendChild(tdLabel);
                        tr.appendChild(tdVal);
                        bodyEl.appendChild(tr);
                    });
                }
                if (kpiModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(kpiModalEl).show();
                }
            }

            // KPI button now opens the new User Dashboard modal (defined in
            // its own IIFE further down). The old openKpiModal() function is
            // kept available for any external caller that still wants it.
            tbody.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest && e.target.closest('.task-summary-kpi-btn');
                if (!btn || !tbody.contains(btn)) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                var userName = (btn.getAttribute('data-user-name') || '').trim();
                if (window.taskSummaryUserDashboard && typeof window.taskSummaryUserDashboard.open === 'function') {
                    var userId = parseInt(btn.getAttribute('data-user-id'), 10) || null;
                    window.taskSummaryUserDashboard.open(userId, userName);
                    return;
                }
                // Fallback: legacy small KPI list modal (if dashboard module didn't load).
                var kpiList = [];
                try {
                    kpiList = JSON.parse(btn.getAttribute('data-kpi') || '[]');
                } catch (err) {
                    kpiList = [];
                }
                openKpiModal(userName, kpiList);
            });

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
                recomputeAnalyticsBadges();
            }

            // Recompute the top-bar analytics badges (Members / Total tasks /
            // Overdue / Approval pending / Done) from whatever rows are
            // currently visible — respects both the search filter and the
            // hierarchy-collapse "task-summary-row-collapsed-hidden" class.
            function recomputeAnalyticsBadges() {
                var rows = tbody.querySelectorAll(
                    'tr.task-summary-row'
                    + ':not(.d-none)'
                    + ':not(.task-summary-row-collapsed-hidden)'
                    + ':not(.task-summary-row-juniors-hidden)'
                    + ':not(.task-summary-row-orphan-hidden)'
                );
                var sum = { task: 0, overdue: 0, done: 0, approval: 0, members: 0 };
                rows.forEach(function (tr) {
                    var taskN = parseInt(tr.getAttribute('data-sort-task'), 10) || 0;
                    sum.task += taskN;
                    sum.overdue += parseInt(tr.getAttribute('data-sort-overdue'), 10) || 0;
                    sum.done += parseInt(tr.getAttribute('data-sort-done'), 10) || 0;
                    sum.approval += parseInt(tr.getAttribute('data-sort-need_approval'), 10) || 0;
                    if (taskN > 0) sum.members += 1;
                });
                var fmt = function (n) {
                    try { return Number(n || 0).toLocaleString(); }
                    catch (e) { return String(n || 0); }
                };
                var pairs = {
                    'ts-analytics-val-total': sum.task,
                    'ts-analytics-val-assigned': sum.members,
                    'ts-analytics-val-overdue': sum.overdue,
                    'ts-analytics-val-approval': sum.approval,
                    'ts-analytics-val-done': sum.done
                };
                Object.keys(pairs).forEach(function (id) {
                    var node = document.getElementById(id);
                    if (node) node.textContent = fmt(pairs[id]);
                });
            }
            // Expose so other modules (hierarchy collapse, role-change re-render)
            // can ping it after they change row visibility.
            window.taskSummaryRecomputeBadges = recomputeAnalyticsBadges;

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
                var labels = ['As assignee', 'As assignor', 'Done', 'Overdue', 'A task', 'A task H', 'Need appr.'];
                var classes = ['', '', 'ts-stat-done', 'ts-stat-overdue', '', '', ''];
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
                var aTaskH = 0;
                var needAppr = 0;
                (tasks || []).forEach(function (t) {
                    if (tsPanelFieldMatchesUser(userNorm, t.assignee_name)) {
                        asAssignee += 1;
                        if (t.is_automate_task == 1 || t.is_automate_task === true) {
                            aTaskH += (parseFloat(t.eta_time) || 0) / 60;
                        }
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
                    { lbl: 'A task H', val: String(Math.round(aTaskH)), cls: '' },
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
                tsRenderUserPanelKpi(tsGetRowKpiList(tr));
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
                if (type === 'float') {
                    va = parseFloat(va) || 0;
                    vb = parseFloat(vb) || 0;
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
                var st = activeTh && activeTh.getAttribute('data-sort-type');
                var type = st === 'number' ? 'number' : st === 'float' ? 'float' : 'text';
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

            // Default: highest assignee task count first (matches server order; shows sort on Task column)
            sortState.key = 'task';
            sortState.dir = 'desc';
            updateHeaderIcons('task', 'desc');
            applySort();

            // Sync the analytics badges to the initial visible row set so
            // viewers with scoped visibility (manager/exec) see their own
            // numbers, not the org-wide ones from the server template.
            recomputeAnalyticsBadges();
        })();

        // -------------------------------------------------------------------
        // Hierarchy grouping (Director → Mgr → Exec) — toggle + render
        // -------------------------------------------------------------------
        (function () {
            var tbody = document.querySelector('.task-summary-table tbody');
            var toggle = document.getElementById('task-summary-group-toggle');
            var collapseActions = document.getElementById('task-summary-group-collapse-actions');
            var searchInput = document.getElementById('task-summary-search');
            if (!tbody || !toggle) return;

            var orgGraph = @json($orgGraph ?? []);

            // Build juniorsOf / hasManager indexes from the pair list.
            var juniorsOf = {};
            var hasManager = {};
            (orgGraph || []).forEach(function (p) {
                var m = parseInt(p.m, 10);
                var j = parseInt(p.j, 10);
                if (!m || !j || m === j) return;
                if (!juniorsOf[m]) juniorsOf[m] = [];
                juniorsOf[m].push(j);
                hasManager[j] = true;
            });

            function getDataRows() {
                return Array.prototype.slice.call(tbody.querySelectorAll('tr.task-summary-row'));
            }
            function getRowById() {
                var map = {};
                getDataRows().forEach(function (tr) {
                    var id = parseInt(tr.getAttribute('data-user-id'), 10);
                    if (id) map[id] = tr;
                });
                return map;
            }
            function memberName(tr) {
                return (tr.getAttribute('data-sort-member') || '').trim();
            }
            function orgLevelOf(tr) {
                return (tr.getAttribute('data-sort-org_level') || '').toLowerCase();
            }
            function designationOf(tr) {
                return (tr.getAttribute('data-sort-designation') || '').trim();
            }

            // Snapshot the original tr → original index so we can restore flat
            // order verbatim when the user turns the hierarchy view off.
            var originalOrder = getDataRows();
            originalOrder.forEach(function (tr, i) {
                tr.setAttribute('data-original-index', String(i));
            });

            // Empty-state row (already in tbody) — keep it pinned to the end.
            var emptyRow = document.getElementById('task-summary-filter-empty');

            // ------- Tree builder -------
            // Returns: [
            //   { kind: 'director'|'mgr'|'others', row?: tr, children: [ leaf|subGroup ] }
            // ]
            // leaf := { kind: 'leaf', row: tr, depth: number }
            function buildTree() {
                var rows = getDataRows();
                var byId = {};
                rows.forEach(function (tr) {
                    var id = parseInt(tr.getAttribute('data-user-id'), 10);
                    if (id) byId[id] = tr;
                });

                // Sort all candidates alphabetically by name for stable groups.
                var byName = function (a, b) {
                    return memberName(a).localeCompare(memberName(b), undefined, { sensitivity: 'base' });
                };

                // Only show a Director / standalone-Mgr as its own group
                // when they actually have juniors tagged under them. Anyone
                // empty falls through to the "Others" bucket at the bottom
                // (kept the visited[] map untouched so the fall-through
                // works automatically).
                var hasAnyJunior = function (tr) {
                    var id = parseInt(tr.getAttribute('data-user-id'), 10);
                    return id && juniorsOf[id] && juniorsOf[id].length > 0;
                };
                var directors = rows.filter(function (tr) {
                    return orgLevelOf(tr) === 'director' && hasAnyJunior(tr);
                }).sort(byName);
                var standaloneMgrs = rows.filter(function (tr) {
                    var id = parseInt(tr.getAttribute('data-user-id'), 10);
                    return orgLevelOf(tr) === 'mgr' && !hasManager[id] && hasAnyJunior(tr);
                }).sort(byName);

                var visited = {}; // user_id → true (to avoid infinite cycles)

                function buildLeaf(tr, depth) {
                    var id = parseInt(tr.getAttribute('data-user-id'), 10);
                    if (visited[id]) return null; // cycle guard
                    visited[id] = true;

                    var level = orgLevelOf(tr);
                    var childIds = juniorsOf[id] || [];
                    // Only Mgr-level users get a nested subgroup. Director leaves keep their children flat as well.
                    if (level === 'mgr' && childIds.length > 0) {
                        var sub = { kind: 'mgr', row: tr, depth: depth, children: [] };
                        childIds
                            .map(function (cid) { return byId[cid]; })
                            .filter(Boolean)
                            .sort(byName)
                            .forEach(function (ctr) {
                                var leaf = buildLeaf(ctr, depth + 1);
                                if (leaf) sub.children.push(leaf);
                            });
                        return sub;
                    }
                    return { kind: 'leaf', row: tr, depth: depth };
                }

                var groups = [];
                directors.forEach(function (dtr) {
                    var did = parseInt(dtr.getAttribute('data-user-id'), 10);
                    if (visited[did]) return;
                    visited[did] = true;
                    var grp = { kind: 'director', row: dtr, depth: 0, children: [] };
                    (juniorsOf[did] || [])
                        .map(function (cid) { return byId[cid]; })
                        .filter(Boolean)
                        .sort(byName)
                        .forEach(function (ctr) {
                            var leaf = buildLeaf(ctr, 1);
                            if (leaf) grp.children.push(leaf);
                        });
                    groups.push(grp);
                });

                standaloneMgrs.forEach(function (mtr) {
                    var mid = parseInt(mtr.getAttribute('data-user-id'), 10);
                    if (visited[mid]) return;
                    visited[mid] = true;
                    var grp = { kind: 'mgr', row: mtr, depth: 0, children: [] };
                    (juniorsOf[mid] || [])
                        .map(function (cid) { return byId[cid]; })
                        .filter(Boolean)
                        .sort(byName)
                        .forEach(function (ctr) {
                            var leaf = buildLeaf(ctr, 1);
                            if (leaf) grp.children.push(leaf);
                        });
                    groups.push(grp);
                });

                // Anyone left without a group → "Others".
                // "Others" bucket intentionally omitted — hierarchy view
                // now shows ONLY real teams (Directors and Managers who
                // actually have juniors tagged under them). Users not
                // attached to a team are simply hidden in this view.
                // (Toggle off "Group by hierarchy" to see everyone in the
                // flat sortable list.)
                return groups;
            }

            // ------- Render -------
            function escapeHtml(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            function makeHeaderTr(groupId, level, labelText, name, meta) {
                var tr = document.createElement('tr');
                tr.className = 'task-summary-group-header';
                tr.setAttribute('data-group-id', groupId);
                tr.setAttribute('data-level', level); // 'director' | 'mgr' | 'others'
                tr.setAttribute('data-collapsed', 'false');
                var td = document.createElement('td');
                td.setAttribute('colspan', '21');
                td.innerHTML =
                    '<div class="task-summary-group-header-inner">'
                    + '<button type="button" class="task-summary-group-chevron" data-action="toggle-group" aria-label="Toggle group">'
                    + '<i class="ri-arrow-down-s-line"></i></button>'
                    + '<button type="button" class="task-summary-group-focus" data-action="focus-group" '
                    +    'title="Focus on this group — collapse everyone else" '
                    +    'aria-label="Focus on this group, collapse all others">'
                    + '<i class="ri-focus-3-line"></i></button>'
                    + '<span class="task-summary-group-label">' + escapeHtml(labelText) + '</span>'
                    + '<span class="task-summary-group-name">' + escapeHtml(name) + '</span>'
                    + '<span class="task-summary-group-meta">' + escapeHtml(meta) + '</span>'
                    + '</div>';
                tr.appendChild(td);
                return tr;
            }

            function flattenGroup(group, out) {
                var name = group.row ? memberName(group.row) : 'Others';
                var subtitle = group.row ? designationOf(group.row) : 'Unassigned / no role';
                if (group.kind === 'director') {
                    var dgId = 'd' + (group.row ? group.row.getAttribute('data-user-id') : '');
                    var dCount = countLeaves(group);
                    out.headers.push({
                        type: 'director', id: dgId,
                        label: 'Director',
                        name: name + (subtitle ? ' · ' + subtitle : ''),
                        meta: dCount + ' direct report' + (dCount === 1 ? '' : 's')
                    });
                    out.rowsInGroup[dgId] = [];
                    // The director themselves is the first row in the group.
                    if (group.row) {
                        group.row.setAttribute('data-depth', '0');
                        group.row.setAttribute('data-group-id', dgId);
                        out.rowsInGroup[dgId].push(group.row);
                    }
                    group.children.forEach(function (child) {
                        if (child.kind === 'mgr') {
                            var mgId = 'm' + child.row.getAttribute('data-user-id');
                            var mCount = countLeaves(child);
                            out.headers.push({
                                type: 'mgr', id: mgId, parent: dgId,
                                label: 'Manager',
                                name: memberName(child.row) + (designationOf(child.row) ? ' · ' + designationOf(child.row) : ''),
                                meta: mCount + ' junior' + (mCount === 1 ? '' : 's')
                            });
                            out.rowsInGroup[mgId] = [];
                            child.row.setAttribute('data-depth', String(child.depth));
                            child.row.setAttribute('data-group-id', mgId);
                            out.rowsInGroup[mgId].push(child.row);
                            child.children.forEach(function (sub) {
                                sub.row.setAttribute('data-depth', String(sub.depth));
                                sub.row.setAttribute('data-group-id', mgId);
                                out.rowsInGroup[mgId].push(sub.row);
                            });
                        } else {
                            child.row.setAttribute('data-depth', String(child.depth));
                            child.row.setAttribute('data-group-id', dgId);
                            out.rowsInGroup[dgId].push(child.row);
                        }
                    });
                    return;
                }

                if (group.kind === 'mgr') {
                    var mgId2 = 'm' + group.row.getAttribute('data-user-id');
                    var mCount2 = countLeaves(group);
                    out.headers.push({
                        type: 'mgr', id: mgId2,
                        label: 'Manager',
                        name: name + (subtitle ? ' · ' + subtitle : ''),
                        meta: mCount2 + ' junior' + (mCount2 === 1 ? '' : 's')
                    });
                    out.rowsInGroup[mgId2] = [];
                    group.row.setAttribute('data-depth', '0');
                    group.row.setAttribute('data-group-id', mgId2);
                    out.rowsInGroup[mgId2].push(group.row);
                    group.children.forEach(function (child) {
                        child.row.setAttribute('data-depth', String(child.depth));
                        child.row.setAttribute('data-group-id', mgId2);
                        out.rowsInGroup[mgId2].push(child.row);
                    });
                    return;
                }

                if (group.kind === 'others') {
                    var oId = 'others';
                    out.headers.push({
                        type: 'others', id: oId,
                        label: 'Others',
                        name: 'Unassigned / no role',
                        meta: group.children.length + ' member' + (group.children.length === 1 ? '' : 's')
                    });
                    out.rowsInGroup[oId] = [];
                    group.children.forEach(function (child) {
                        child.row.setAttribute('data-depth', '0');
                        child.row.setAttribute('data-group-id', oId);
                        out.rowsInGroup[oId].push(child.row);
                    });
                }
            }

            function countLeaves(group) {
                if (group.kind === 'leaf') return 1;
                var n = group.children ? group.children.reduce(function (s, c) { return s + countLeaves(c); }, 0) : 0;
                // Don't count the manager/director row itself as a "report" — only their children.
                return n;
            }

            // Persist collapse state across renders.
            var collapsedGroups = {};

            function renderGroupedView() {
                var groups = buildTree();
                var out = { headers: [], rowsInGroup: {} };
                groups.forEach(function (g) { flattenGroup(g, out); });

                // Snapshot existing header rows so we can purge them and add fresh.
                Array.prototype.slice.call(tbody.querySelectorAll('tr.task-summary-group-header')).forEach(function (h) {
                    h.remove();
                });

                // Track which rows are placed in a group so we can hide
                // anything orphaned (used to land in the now-removed
                // "Others" bucket — empty Directors / Mgrs / unassigned).
                var placedRows = [];
                Object.keys(out.rowsInGroup).forEach(function (gid) {
                    (out.rowsInGroup[gid] || []).forEach(function (tr) { placedRows.push(tr); });
                });
                var placedSet = new WeakSet ? new WeakSet(placedRows) : null;
                var placedFlag = {}; // fallback for envs without WeakSet
                placedRows.forEach(function (tr) {
                    var id = parseInt(tr.getAttribute('data-user-id'), 10) || 0;
                    if (id) placedFlag[id] = true;
                });

                // Append in order: header → its rows; header → its rows; …
                out.headers.forEach(function (h) {
                    var hr = makeHeaderTr(h.id, h.type, h.label, h.name, h.meta);
                    if (collapsedGroups[h.id]) hr.setAttribute('data-collapsed', 'true');
                    tbody.appendChild(hr);
                    (out.rowsInGroup[h.id] || []).forEach(function (tr) {
                        tbody.appendChild(tr);
                        if (collapsedGroups[h.id]) tr.classList.add('task-summary-row-collapsed-hidden');
                        else tr.classList.remove('task-summary-row-collapsed-hidden');
                    });
                });

                // Hide every row that didn't get placed in a group (the
                // ex-"Others" pool). Also strip stale grouping attributes.
                getDataRows().forEach(function (tr) {
                    var isPlaced = placedSet
                        ? placedSet.has(tr)
                        : !!placedFlag[parseInt(tr.getAttribute('data-user-id'), 10) || 0];
                    if (isPlaced) {
                        tr.classList.remove('task-summary-row-orphan-hidden');
                    } else {
                        tr.classList.add('task-summary-row-orphan-hidden');
                        tr.removeAttribute('data-depth');
                        tr.removeAttribute('data-group-id');
                    }
                });

                if (emptyRow) tbody.appendChild(emptyRow);
                refreshHeaderVisibility();
            }

            // Hide a header row if every row inside it is currently hidden
            // by ANY visibility source (search, collapse, juniors-only filter).
            function refreshHeaderVisibility() {
                var headers = tbody.querySelectorAll('tr.task-summary-group-header');
                headers.forEach(function (h) {
                    var gid = h.getAttribute('data-group-id');
                    var visible = tbody.querySelectorAll(
                        'tr.task-summary-row[data-group-id="' + gid + '"]'
                        + ':not(.d-none)'
                        + ':not(.task-summary-row-collapsed-hidden)'
                        + ':not(.task-summary-row-juniors-hidden)'
                        + ':not(.task-summary-row-orphan-hidden)'
                    ).length;
                    h.classList.toggle('d-none', visible === 0);
                });
            }

            function setRowsCollapsed(gid, collapsed) {
                tbody.querySelectorAll('tr.task-summary-row[data-group-id="' + gid + '"]').forEach(function (tr) {
                    tr.classList.toggle('task-summary-row-collapsed-hidden', collapsed);
                });
                // Cascade collapse to nested headers (e.g. Director collapse hides Mgr sub-headers).
                tbody.querySelectorAll('tr.task-summary-group-header[data-level="mgr"]').forEach(function (sub) {
                    var sgid = sub.getAttribute('data-group-id');
                    // A nested mgr header belongs to a director if any of its rows are in director's tree;
                    // simpler heuristic: if the Mgr's header row is within the director's child rows,
                    // we collapsed those rows above. So toggle visibility based on whether ANY of its rows
                    // are still visible.
                    if (collapsed) sub.classList.add('d-none');
                });
                refreshHeaderVisibility();
            }

            function isHierarchyActive() {
                return toggle.checked;
            }

            function activateHierarchy() {
                tbody.parentElement.classList.add('is-hierarchical');
                if (collapseActions) collapseActions.classList.remove('d-none');
                renderGroupedView();
            }

            function deactivateHierarchy() {
                tbody.parentElement.classList.remove('is-hierarchical');
                if (collapseActions) collapseActions.classList.add('d-none');
                // Drop group headers.
                Array.prototype.slice.call(tbody.querySelectorAll('tr.task-summary-group-header')).forEach(function (h) {
                    h.remove();
                });
                // Restore original flat order — also strip both
                // hierarchy-only hide classes so the flat list shows
                // everyone again (including ex-"Others" rows).
                var rows = getDataRows();
                rows.forEach(function (tr) {
                    tr.removeAttribute('data-depth');
                    tr.removeAttribute('data-group-id');
                    tr.classList.remove('task-summary-row-collapsed-hidden');
                    tr.classList.remove('task-summary-row-orphan-hidden');
                });
                rows.sort(function (a, b) {
                    var ai = parseInt(a.getAttribute('data-original-index'), 10) || 0;
                    var bi = parseInt(b.getAttribute('data-original-index'), 10) || 0;
                    return ai - bi;
                });
                rows.forEach(function (tr) { tbody.appendChild(tr); });
                if (emptyRow) tbody.appendChild(emptyRow);
            }

            toggle.addEventListener('change', function () {
                if (isHierarchyActive()) activateHierarchy(); else deactivateHierarchy();
            });

            // Collapse / expand-all controls.
            if (collapseActions) {
                collapseActions.addEventListener('click', function (e) {
                    var btn = e.target && e.target.closest && e.target.closest('button[data-action]');
                    if (!btn) return;
                    var act = btn.getAttribute('data-action');
                    var headers = tbody.querySelectorAll('tr.task-summary-group-header');
                    headers.forEach(function (h) {
                        var gid = h.getAttribute('data-group-id');
                        var collapse = act === 'collapse-all';
                        collapsedGroups[gid] = collapse;
                        h.setAttribute('data-collapsed', collapse ? 'true' : 'false');
                        h.classList.remove('is-focused');
                        tbody.querySelectorAll('tr.task-summary-row[data-group-id="' + gid + '"]').forEach(function (tr) {
                            tr.classList.toggle('task-summary-row-collapsed-hidden', collapse);
                        });
                        // For collapse-all, also visually collapse nested mgr headers under directors.
                        if (collapse) h.classList.remove('d-none'); // keep header itself visible
                    });
                    focusedGroupId = null;
                    refreshHeaderVisibility();
                });
            }

            // Delegated click for individual group chevrons.
            tbody.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest && e.target.closest('button.task-summary-group-chevron');
                if (!btn) return;
                var h = btn.closest('tr.task-summary-group-header');
                if (!h) return;
                var gid = h.getAttribute('data-group-id');
                var collapsed = h.getAttribute('data-collapsed') === 'true';
                var nextCollapsed = !collapsed;
                h.setAttribute('data-collapsed', nextCollapsed ? 'true' : 'false');
                collapsedGroups[gid] = nextCollapsed;
                tbody.querySelectorAll('tr.task-summary-row[data-group-id="' + gid + '"]').forEach(function (tr) {
                    tr.classList.toggle('task-summary-row-collapsed-hidden', nextCollapsed);
                });
                refreshHeaderVisibility();
            });

            // Tracks which group (if any) is currently focused; clicking
            // the focus button on that same group again returns the table
            // to its normal expanded state.
            var focusedGroupId = null;

            // Expand every group / clear the focus state.
            function unfocusAllGroups() {
                var headers = Array.prototype.slice.call(
                    tbody.querySelectorAll('tr.task-summary-group-header')
                );
                headers.forEach(function (h) {
                    var gid = h.getAttribute('data-group-id');
                    h.setAttribute('data-collapsed', 'false');
                    h.classList.remove('is-focused');
                    h.classList.remove('d-none');
                    collapsedGroups[gid] = false;
                    tbody.querySelectorAll('tr.task-summary-row[data-group-id="' + gid + '"]').forEach(function (tr) {
                        tr.classList.remove('task-summary-row-collapsed-hidden');
                    });
                    var fbtn = h.querySelector('button.task-summary-group-focus');
                    if (fbtn) {
                        fbtn.setAttribute('title', 'Focus on this group — collapse everyone else');
                    }
                });
                focusedGroupId = null;
                refreshHeaderVisibility();
            }

            // "Focus on this group" — collapse every other group and keep
            // only this one (plus any nested mgr sub-groups under a director)
            // expanded. Walks the header order so we don't need server hints.
            // Toggle: clicking the focus button on the already-focused group
            // restores the full hierarchy.
            function focusGroup(targetHeader) {
                var targetGid = targetHeader.getAttribute('data-group-id');

                // Second click on the same group → unfocus.
                if (focusedGroupId === targetGid) {
                    unfocusAllGroups();
                    return;
                }

                var headers = Array.prototype.slice.call(
                    tbody.querySelectorAll('tr.task-summary-group-header')
                );
                var targetLvl = targetHeader.getAttribute('data-level');
                var keep = {};
                keep[targetGid] = true;

                // If this is a director, also keep every header that appears
                // between it and the next top-level group (those are the
                // director's own Manager sub-groups).
                if (targetLvl === 'director') {
                    var idx = headers.indexOf(targetHeader);
                    for (var i = idx + 1; i < headers.length; i++) {
                        var nLvl = headers[i].getAttribute('data-level');
                        if (nLvl === 'director' || nLvl === 'others') break;
                        keep[headers[i].getAttribute('data-group-id')] = true;
                    }
                }

                headers.forEach(function (h) {
                    var gid = h.getAttribute('data-group-id');
                    var shouldCollapse = !keep[gid];
                    var isFocused = gid === targetGid;
                    h.setAttribute('data-collapsed', shouldCollapse ? 'true' : 'false');
                    h.classList.toggle('is-focused', isFocused);
                    collapsedGroups[gid] = shouldCollapse;
                    h.classList.remove('d-none');
                    tbody.querySelectorAll('tr.task-summary-row[data-group-id="' + gid + '"]').forEach(function (tr) {
                        tr.classList.toggle('task-summary-row-collapsed-hidden', shouldCollapse);
                    });
                    var fbtn = h.querySelector('button.task-summary-group-focus');
                    if (fbtn) {
                        fbtn.setAttribute(
                            'title',
                            isFocused
                                ? 'Click again to restore the full hierarchy'
                                : 'Focus on this group — collapse everyone else'
                        );
                    }
                });
                focusedGroupId = targetGid;
                refreshHeaderVisibility();
            }

            // Delegated click for the new "Focus on this group" button.
            tbody.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest && e.target.closest('button.task-summary-group-focus');
                if (!btn) return;
                e.preventDefault();
                var h = btn.closest('tr.task-summary-group-header');
                if (!h) return;
                focusGroup(h);
            });

            // Re-run header visibility whenever the search input filters rows.
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    if (isHierarchyActive()) refreshHeaderVisibility();
                });
                searchInput.addEventListener('search', function () {
                    if (isHierarchyActive()) refreshHeaderVisibility();
                });
            }

            // After every collapse / expand operation, refresh the analytics
            // badges so they reflect only the rows currently shown on screen.
            function pingBadges() {
                if (window.taskSummaryRecomputeBadges) window.taskSummaryRecomputeBadges();
            }
            tbody.addEventListener('click', function (e) {
                var tgt = e.target;
                if (!tgt || !tgt.closest) return;
                if (tgt.closest('button.task-summary-group-chevron')
                    || tgt.closest('button.task-summary-group-focus')) {
                    // setTimeout to run AFTER the existing handler toggles classes.
                    setTimeout(pingBadges, 0);
                }
            });
            if (collapseActions) {
                collapseActions.addEventListener('click', function (e) {
                    if (e.target && e.target.closest && e.target.closest('button[data-action]')) {
                        setTimeout(pingBadges, 0);
                    }
                });
            }
            toggle.addEventListener('change', function () {
                setTimeout(pingBadges, 0);
            });

            // ---------------- "Only with juniors" filter ----------------
            var juniorsOnlyToggle = document.getElementById('task-summary-juniors-only-toggle');

            // Build a Set of user_ids to KEEP when the filter is on:
            //   - every user that has at least one tagged junior, PLUS
            //   - every user that is a junior of one of the above.
            function buildJuniorsOnlyKeepSet() {
                var keep = {};
                Object.keys(juniorsOf).forEach(function (mid) {
                    var midN = parseInt(mid, 10);
                    if (!midN) return;
                    keep[midN] = true;
                    (juniorsOf[midN] || []).forEach(function (jid) {
                        if (jid) keep[parseInt(jid, 10)] = true;
                    });
                });
                return keep;
            }

            function applyJuniorsOnlyFilter(enabled) {
                var keep = enabled ? buildJuniorsOnlyKeepSet() : null;
                getDataRows().forEach(function (tr) {
                    var id = parseInt(tr.getAttribute('data-user-id'), 10) || 0;
                    var shouldHide = enabled && !keep[id];
                    tr.classList.toggle('task-summary-row-juniors-hidden', shouldHide);
                });
                refreshHeaderVisibility();
                pingBadges();
            }

            if (juniorsOnlyToggle) {
                juniorsOnlyToggle.addEventListener('change', function () {
                    applyJuniorsOnlyFilter(!!juniorsOnlyToggle.checked);
                });
            }

            // Allow other modules (e.g. Role dropdown) to ask for a refresh
            // after they mutate org_level for any row.
            window.taskSummaryHierarchy = {
                rebuildIfActive: function () {
                    if (isHierarchyActive()) renderGroupedView();
                    // Re-apply juniors-only filter so newly-rendered headers honor it.
                    if (juniorsOnlyToggle && juniorsOnlyToggle.checked) {
                        applyJuniorsOnlyFilter(true);
                    }
                    pingBadges();
                },
                refreshHeaders: refreshHeaderVisibility
            };

            // Hide collapsed rows + juniors-only-hidden rows via dedicated
            // classes so each filter source is independent.
            var styleNode = document.createElement('style');
            styleNode.textContent =
                '.task-summary-table tbody tr.task-summary-row-collapsed-hidden,'
                + ' .task-summary-table tbody tr.task-summary-row-juniors-hidden,'
                + ' .task-summary-table tbody tr.task-summary-row-orphan-hidden {'
                + ' display: none !important;'
                + ' }';
            document.head.appendChild(styleNode);
        })();

        // -------------------------------------------------------------------
        // R&R modal (Roles & Responsibilities per designation)
        // -------------------------------------------------------------------
        (function () {
            var csrfToken = (function () {
                var meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.getAttribute('content') : '';
            })();

            var endpoints = {
                get: @json(route('tasks.designationRR.get')),
                generate: @json(route('tasks.designationRR.generate')),
                add: @json(route('tasks.designationRR.add')),
                suggest: @json(route('tasks.designationRR.suggest')),
                updateBase: @json(url('/tasks/designation-rr/items')),
                deleteBase: @json(url('/tasks/designation-rr/items')),
                progress: @json(route('tasks.designationRR.progress'))
            };

            var state = {
                userId: null,
                userName: '',
                designation: '',
                items: []
            };

            // All element lookups happen lazily (every call) so the partial can
            // be re-rendered or moved without breaking the modal.
            function getModalEl() { return document.getElementById('taskSummaryRrModal'); }
            function el(id) { return document.getElementById(id); }

            // Lazy Bootstrap modal init — Bootstrap may not be ready at IIFE
            // startup but it will be by the time the user clicks the button.
            function getBsModal() {
                var modalEl = getModalEl();
                if (!modalEl) return null;
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    return bootstrap.Modal.getOrCreateInstance(modalEl);
                }
                return null;
            }

            // Fallback show/hide for the rare case Bootstrap's JS is missing.
            function fallbackShow(modalEl) {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                modalEl.removeAttribute('aria-hidden');
                modalEl.setAttribute('aria-modal', 'true');
                document.body.classList.add('modal-open');
                if (!document.getElementById('ts-rr-fallback-backdrop')) {
                    var bd = document.createElement('div');
                    bd.id = 'ts-rr-fallback-backdrop';
                    bd.className = 'modal-backdrop fade show';
                    document.body.appendChild(bd);
                    bd.addEventListener('click', function () { fallbackHide(modalEl); });
                }
                modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (b) {
                    b.addEventListener('click', function () { fallbackHide(modalEl); }, { once: true });
                });
            }
            function fallbackHide(modalEl) {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                document.body.classList.remove('modal-open');
                var bd = document.getElementById('ts-rr-fallback-backdrop');
                if (bd) bd.remove();
            }

            function showModal() {
                var modalEl = getModalEl();
                if (!modalEl) {
                    console.error('[R&R] taskSummaryRrModal element not found in DOM. Did the partial load?');
                    return;
                }
                var bs = getBsModal();
                if (bs) {
                    bs.show();
                } else {
                    console.warn('[R&R] Bootstrap modal API not available; using manual fallback.');
                    fallbackShow(modalEl);
                }
            }

            var PANE_IDS = {
                loading: 'ts-rr-loading',
                aiLoading: 'ts-rr-ai-loading',
                empty: 'ts-rr-empty',
                content: 'ts-rr-content'
            };

            function showOnly(targetKey) {
                Object.keys(PANE_IDS).forEach(function (key) {
                    var node = el(PANE_IDS[key]);
                    if (node) {
                        node.classList.toggle('d-none', key !== targetKey);
                    }
                });
            }

            function showError(msg) {
                var node = el('ts-rr-error');
                if (!node) return;
                node.textContent = msg || 'Something went wrong.';
                node.classList.remove('d-none');
            }

            function clearError() {
                var node = el('ts-rr-error');
                if (node) {
                    node.classList.add('d-none');
                    node.textContent = '';
                }
            }

            function escapeHtml(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function statusLabel(s) {
                if (s === 'done') return 'Done';
                if (s === 'in_progress') return 'In progress';
                return 'Pending';
            }

            function renderProgress(items) {
                var total = items.length;
                var done = items.filter(function (i) { return i.status === 'done'; }).length;
                var inProg = items.filter(function (i) { return i.status === 'in_progress'; }).length;
                var pct = total > 0 ? Math.round((done / total) * 100) : 0;
                var fill = el('ts-rr-modal-progress-fill');
                var text = el('ts-rr-modal-progress-text');
                if (fill) fill.style.width = pct + '%';
                if (text) {
                    text.textContent = total > 0
                        ? (done + '/' + total + ' done · ' + inProg + ' in progress · ' + pct + '%')
                        : '';
                }
            }

            function renderItems(items) {
                var list = el('ts-rr-item-list');
                if (!list) return;
                list.innerHTML = '';
                items.forEach(function (item) {
                    list.appendChild(buildItemNode(item));
                });
            }

            function buildItemNode(item) {
                var wrap = document.createElement('div');
                wrap.className = 'rr-item d-flex align-items-start gap-2';
                wrap.setAttribute('data-item-id', item.id);

                var titleCol = document.createElement('div');
                titleCol.className = 'flex-grow-1 min-w-0';
                var srcBadge = '<span class="rr-source-badge ' + (item.source === 'manual' ? 'is-manual' : '') + '">'
                    + (item.source === 'ai' ? 'AI' : 'Manual') + '</span>';
                var titleHtml = '<div class="rr-item-title" data-role="title">' + escapeHtml(item.title) + srcBadge + '</div>';
                if (item.description) {
                    titleHtml += '<div class="rr-item-desc" data-role="desc">' + escapeHtml(item.description) + '</div>';
                }
                titleCol.innerHTML = titleHtml;

                var actionsCol = document.createElement('div');
                actionsCol.className = 'd-flex align-items-center gap-1 flex-shrink-0';

                var edit = document.createElement('button');
                edit.type = 'button';
                edit.className = 'rr-edit-btn';
                edit.setAttribute('data-action', 'edit');
                edit.setAttribute('title', 'Edit this responsibility');
                edit.setAttribute('aria-label', 'Edit ' + (item.title || 'item'));
                edit.innerHTML = '<i class="ri-pencil-line"></i>';
                actionsCol.appendChild(edit);

                var del = document.createElement('button');
                del.type = 'button';
                del.className = 'rr-delete-btn';
                del.setAttribute('data-action', 'delete');
                del.setAttribute('title', 'Remove this item');
                del.setAttribute('aria-label', 'Remove ' + (item.title || 'item'));
                del.innerHTML = '<i class="ri-delete-bin-line"></i>';
                actionsCol.appendChild(del);

                wrap.appendChild(titleCol);
                wrap.appendChild(actionsCol);
                return wrap;
            }

            function enterEditMode(row) {
                if (!row || row.classList.contains('is-editing')) return;
                var itemId = row.getAttribute('data-item-id');
                var item = state.items.find(function (i) { return String(i.id) === String(itemId); });
                if (!item) return;
                row.classList.add('is-editing');

                var titleCol = row.querySelector('.flex-grow-1');
                if (!titleCol) return;
                titleCol.dataset.originalHtml = titleCol.innerHTML;

                var srcBadge = '<span class="rr-source-badge ' + (item.source === 'manual' ? 'is-manual' : '') + '">'
                    + (item.source === 'ai' ? 'AI' : 'Manual') + '</span>';
                titleCol.innerHTML =
                    '<div class="rr-edit-form">'
                    + '<div class="d-flex align-items-center gap-2 mb-1">'
                    +   '<input type="text" class="form-control form-control-sm rr-edit-title" maxlength="500" value="' + escapeHtml(item.title) + '" />'
                    +   srcBadge
                    + '</div>'
                    + '<textarea class="form-control form-control-sm rr-edit-desc" rows="2" maxlength="2000" placeholder="Optional description…">' + escapeHtml(item.description || '') + '</textarea>'
                    + '<div class="mt-1 d-flex gap-1 justify-content-end">'
                    +   '<button type="button" class="btn btn-sm btn-light" data-action="edit-cancel"><i class="ri-close-line"></i> Cancel</button>'
                    +   '<button type="button" class="btn btn-sm btn-primary rr-edit-save-btn" data-action="edit-save" style="background:#6d28d9;border-color:#6d28d9;"><i class="ri-check-line"></i> Save</button>'
                    + '</div>'
                    + '</div>';

                // Swap the action buttons for a single visual hint while editing.
                var actions = row.querySelector('.flex-shrink-0');
                if (actions) actions.style.display = 'none';

                var input = titleCol.querySelector('.rr-edit-title');
                if (input) {
                    input.focus();
                    input.select();
                }
            }

            function exitEditMode(row, item) {
                if (!row) return;
                row.classList.remove('is-editing');
                var titleCol = row.querySelector('.flex-grow-1');
                if (!titleCol) return;

                if (item) {
                    var srcBadge = '<span class="rr-source-badge ' + (item.source === 'manual' ? 'is-manual' : '') + '">'
                        + (item.source === 'ai' ? 'AI' : 'Manual') + '</span>';
                    var html = '<div class="rr-item-title" data-role="title">' + escapeHtml(item.title) + srcBadge + '</div>';
                    if (item.description) {
                        html += '<div class="rr-item-desc" data-role="desc">' + escapeHtml(item.description) + '</div>';
                    }
                    titleCol.innerHTML = html;
                } else if (titleCol.dataset.originalHtml) {
                    titleCol.innerHTML = titleCol.dataset.originalHtml;
                }
                delete titleCol.dataset.originalHtml;

                var actions = row.querySelector('.flex-shrink-0');
                if (actions) actions.style.display = '';
            }

            function saveItem(row) {
                var itemId = row.getAttribute('data-item-id');
                var item = state.items.find(function (i) { return String(i.id) === String(itemId); });
                if (!item) return;
                var titleInput = row.querySelector('.rr-edit-title');
                var descInput = row.querySelector('.rr-edit-desc');
                var newTitle = titleInput ? titleInput.value.trim() : '';
                var newDesc = descInput ? descInput.value.trim() : '';
                if (!newTitle) {
                    showError('Title cannot be empty.');
                    return;
                }
                clearError();
                var saveBtn = row.querySelector('.rr-edit-save-btn');
                if (saveBtn) saveBtn.disabled = true;
                postJson(endpoints.updateBase + '/' + encodeURIComponent(itemId), {
                    title: newTitle,
                    description: newDesc === '' ? null : newDesc
                }, 'PATCH')
                    .then(function (data) {
                        if (saveBtn) saveBtn.disabled = false;
                        item.title = (data.item && data.item.title) || newTitle;
                        item.description = (data.item && (data.item.description !== undefined)) ? data.item.description : (newDesc === '' ? null : newDesc);
                        exitEditMode(row, item);
                    })
                    .catch(function (err) {
                        if (saveBtn) saveBtn.disabled = false;
                        showError(err.message || 'Could not save changes.');
                    });
            }

            function applyState(data) {
                state.designation = data.designation || '';
                state.items = Array.isArray(data.items) ? data.items : [];

                var desEl = el('ts-rr-modal-designation');
                if (desEl) {
                    desEl.innerHTML = state.designation
                        ? '<i class="ri-briefcase-line me-1"></i>' + escapeHtml(state.designation)
                        : '<em>No designation set</em>';
                }

                var regen = el('ts-rr-regenerate-btn');
                if (data.needs_ai_seed) {
                    showOnly('empty');
                    renderProgress([]);
                    if (regen) regen.style.display = 'none';
                    return;
                }

                renderItems(state.items);
                renderProgress(state.items);
                showOnly('content');
                if (regen) {
                    regen.style.display = state.items.length === 0 ? 'inline-flex' : 'none';
                }
            }

            function loadFromServer() {
                clearError();
                showOnly('loading');
                var params = new URLSearchParams();
                if (state.userId) params.set('user_id', String(state.userId));
                if (state.designation) params.set('designation', state.designation);
                var url = endpoints.get + (endpoints.get.indexOf('?') >= 0 ? '&' : '?') + params.toString();

                fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) {
                        if (!r.ok) throw new Error('Could not load R&R (HTTP ' + r.status + ')');
                        return r.json();
                    })
                    .then(function (data) {
                        applyState(data);
                    })
                    .catch(function (err) {
                        showOnly('content');
                        var list = el('ts-rr-item-list');
                        if (list) list.innerHTML = '';
                        renderProgress([]);
                        showError(err && err.message ? err.message : 'Failed to load R&R.');
                    });
            }

            function postJson(url, body, method) {
                return fetch(url, {
                    method: method || 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: body ? JSON.stringify(body) : null
                }).then(function (r) {
                    return r.json().then(function (data) {
                        if (!r.ok || data.success === false) {
                            var msg = (data && data.message) ? data.message : 'Request failed (HTTP ' + r.status + ')';
                            throw new Error(msg);
                        }
                        return data;
                    });
                });
            }

            function generateWithAi() {
                if (!state.designation) {
                    showError('Designation is required before generating R&R.');
                    return;
                }
                clearError();
                showOnly('aiLoading');
                postJson(endpoints.generate, { designation: state.designation })
                    .then(function () {
                        loadFromServer();
                    })
                    .catch(function (err) {
                        showOnly('empty');
                        showError(err.message || 'AI generation failed.');
                    });
            }

            function addItem() {
                var titleInput = el('ts-rr-add-title');
                var addBtn = el('ts-rr-add-btn');
                var title = (titleInput && titleInput.value || '').trim();
                if (!title) {
                    showError('Enter a responsibility title first.');
                    return;
                }
                if (!state.designation) {
                    showError('Designation is required.');
                    return;
                }
                clearError();
                if (addBtn) addBtn.disabled = true;
                postJson(endpoints.add, { designation: state.designation, title: title })
                    .then(function (data) {
                        if (addBtn) addBtn.disabled = false;
                        if (titleInput) titleInput.value = '';
                        state.items.push(data.item);
                        renderItems(state.items);
                        renderProgress(state.items);
                    })
                    .catch(function (err) {
                        if (addBtn) addBtn.disabled = false;
                        showError(err.message || 'Could not add item.');
                    });
            }

            function suggestItem() {
                if (!state.designation) {
                    showError('Designation is required.');
                    return;
                }
                var btn = el('ts-rr-ai-suggest-btn');
                var titleInput = el('ts-rr-add-title');
                var hint = (titleInput && titleInput.value || '').trim();
                var originalHtml = btn ? btn.innerHTML : '';
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="ri-loader-4-line spin"></i> Thinking…';
                }
                clearError();
                var payload = { designation: state.designation };
                if (hint !== '') payload.hint = hint;
                postJson(endpoints.suggest, payload)
                    .then(function (data) {
                        if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
                        if (data.item) {
                            // Clear the draft text only after a successful refinement.
                            if (titleInput) titleInput.value = '';
                            state.items.push(data.item);
                            renderItems(state.items);
                            renderProgress(state.items);
                            // If we were in the empty state (no items yet), flip to content.
                            showOnly('content');
                            var regen = el('ts-rr-regenerate-btn');
                            if (regen) regen.style.display = 'none';
                        }
                    })
                    .catch(function (err) {
                        if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
                        showError(err.message || 'AI could not suggest a responsibility.');
                        // Leave the draft text intact so the user can try again or click +Add.
                    });
            }

            function deleteItem(itemId, rowEl) {
                if (!itemId) return;
                if (!window.confirm('Remove this responsibility from the designation? This affects every user with this designation.')) {
                    return;
                }
                clearError();
                postJson(endpoints.deleteBase + '/' + encodeURIComponent(itemId), null, 'DELETE')
                    .then(function () {
                        state.items = state.items.filter(function (i) { return String(i.id) !== String(itemId); });
                        if (rowEl && rowEl.parentNode) rowEl.parentNode.removeChild(rowEl);
                        renderProgress(state.items);
                        if (state.items.length === 0) {
                            applyState({ designation: state.designation, items: [], needs_ai_seed: true });
                        }
                    })
                    .catch(function (err) {
                        showError(err.message || 'Could not delete item.');
                    });
            }

            // Status-per-user has been removed from the R&R modal. Items
            // are now just editable text rows (edit / delete), so no
            // per-user progress update is needed here.

            // Single delegated click handler on document — works regardless of
            // when the magnifying-glass button is added to the DOM.
            document.addEventListener('click', function (e) {
                var target = e.target;
                if (!target || !target.closest) return;

                // Open R&R modal from magnifying-glass button
                var openBtn = target.closest('.task-summary-rr-btn');
                if (openBtn) {
                    e.preventDefault();
                    if (openBtn.disabled) return;
                    state.userId = parseInt(openBtn.getAttribute('data-user-id'), 10) || null;
                    state.userName = openBtn.getAttribute('data-user-name') || '';
                    state.designation = (openBtn.getAttribute('data-designation') || '').trim();
                    state.items = [];

                    var userLabel = el('ts-rr-modal-user');
                    if (userLabel) {
                        userLabel.textContent = state.userName
                            ? (state.userName + ' — R&R')
                            : 'Roles & Responsibilities';
                    }
                    var addTitle = el('ts-rr-add-title');
                    if (addTitle) addTitle.value = '';
                    clearError();
                    showModal();
                    loadFromServer();
                    return;
                }

                // Modal-internal buttons (also delegated so they work even if
                // the modal markup is re-rendered).
                if (target.closest('#ts-rr-generate-ai-btn') || target.closest('#ts-rr-regenerate-btn')) {
                    e.preventDefault();
                    generateWithAi();
                    return;
                }
                if (target.closest('#ts-rr-add-btn')) {
                    e.preventDefault();
                    addItem();
                    return;
                }
                if (target.closest('#ts-rr-ai-suggest-btn')) {
                    e.preventDefault();
                    suggestItem();
                    return;
                }
                var del = target.closest('#ts-rr-item-list button[data-action="delete"]');
                if (del) {
                    e.preventDefault();
                    var rowD = del.closest('.rr-item');
                    if (rowD) deleteItem(rowD.getAttribute('data-item-id'), rowD);
                    return;
                }
                var editBtn = target.closest('#ts-rr-item-list button[data-action="edit"]');
                if (editBtn) {
                    e.preventDefault();
                    var rowE = editBtn.closest('.rr-item');
                    if (rowE) enterEditMode(rowE);
                    return;
                }
                var saveBtn = target.closest('#ts-rr-item-list button[data-action="edit-save"]');
                if (saveBtn) {
                    e.preventDefault();
                    var rowS = saveBtn.closest('.rr-item');
                    if (rowS) saveItem(rowS);
                    return;
                }
                var cancelBtn = target.closest('#ts-rr-item-list button[data-action="edit-cancel"]');
                if (cancelBtn) {
                    e.preventDefault();
                    var rowC = cancelBtn.closest('.rr-item');
                    if (rowC) exitEditMode(rowC, null);
                    return;
                }
            });

            // Enter key on add-title input submits the item; inline-edit
            // Enter saves, Escape cancels.
            document.addEventListener('keydown', function (e) {
                var t = e.target;
                if (!t) return;
                if (e.key === 'Enter' && t.id === 'ts-rr-add-title') {
                    e.preventDefault();
                    addItem();
                    return;
                }
                if (e.key === 'Enter' && t.classList && t.classList.contains('rr-edit-title')) {
                    e.preventDefault();
                    var row = t.closest('.rr-item');
                    if (row) saveItem(row);
                    return;
                }
                if (e.key === 'Escape' && (
                    (t.classList && (t.classList.contains('rr-edit-title') || t.classList.contains('rr-edit-desc')))
                )) {
                    e.preventDefault();
                    var rowE = t.closest('.rr-item');
                    if (rowE) exitEditMode(rowE, null);
                }
            });
        })();

        // -------------------------------------------------------------------
        // CL R&R modal (Checklist for R&R — weighted checkpoints + scoring)
        // -------------------------------------------------------------------
        (function () {
            var csrfToken = (function () {
                var meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.getAttribute('content') : '';
            })();

            var endpoints = {
                get: @json(route('tasks.designationRR.checklist.get')),
                generate: @json(route('tasks.designationRR.checklist.generate')),
                add: @json(route('tasks.designationRR.checklist.add')),
                suggest: @json(route('tasks.designationRR.checklist.suggest')),
                updateBase: @json(url('/tasks/designation-rr/checklist/items')),
                deleteBase: @json(url('/tasks/designation-rr/checklist/items')),
                progress: @json(route('tasks.designationRR.checklist.progress'))
            };

            var state = {
                userId: null,
                userName: '',
                designation: '',
                items: [],
                overall: { percent: 0, earned: 0, total: 0, checked: 0, count: 0 }
            };

            var PANE_IDS = {
                loading: 'ts-clrr-loading',
                aiLoading: 'ts-clrr-ai-loading',
                needsRr: 'ts-clrr-needs-rr',
                empty: 'ts-clrr-empty',
                content: 'ts-clrr-content'
            };

            function el(id) { return document.getElementById(id); }
            function getModalEl() { return document.getElementById('taskSummaryClRrModal'); }

            function getBsModal() {
                var m = getModalEl();
                if (!m) return null;
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    return bootstrap.Modal.getOrCreateInstance(m);
                }
                return null;
            }

            function fallbackShow(modalEl) {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                modalEl.removeAttribute('aria-hidden');
                modalEl.setAttribute('aria-modal', 'true');
                document.body.classList.add('modal-open');
                if (!document.getElementById('ts-clrr-fallback-backdrop')) {
                    var bd = document.createElement('div');
                    bd.id = 'ts-clrr-fallback-backdrop';
                    bd.className = 'modal-backdrop fade show';
                    document.body.appendChild(bd);
                    bd.addEventListener('click', function () { fallbackHide(modalEl); });
                }
                modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (b) {
                    b.addEventListener('click', function () { fallbackHide(modalEl); }, { once: true });
                });
            }
            function fallbackHide(modalEl) {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                document.body.classList.remove('modal-open');
                var bd = document.getElementById('ts-clrr-fallback-backdrop');
                if (bd) bd.remove();
            }

            function showModal() {
                var modalEl = getModalEl();
                if (!modalEl) {
                    console.error('[CL R&R] taskSummaryClRrModal element not found in DOM. Did the partial load?');
                    return;
                }
                var bs = getBsModal();
                if (bs) {
                    bs.show();
                } else {
                    console.warn('[CL R&R] Bootstrap modal API not available; using manual fallback.');
                    fallbackShow(modalEl);
                }
            }

            function showOnly(targetKey) {
                Object.keys(PANE_IDS).forEach(function (key) {
                    var node = el(PANE_IDS[key]);
                    if (node) node.classList.toggle('d-none', key !== targetKey);
                });
            }

            function showError(msg) {
                var node = el('ts-clrr-error');
                if (!node) return;
                node.textContent = msg || 'Something went wrong.';
                node.classList.remove('d-none');
            }

            function clearError() {
                var node = el('ts-clrr-error');
                if (node) {
                    node.classList.add('d-none');
                    node.textContent = '';
                }
            }

            function escapeHtml(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            // Re-compute scores entirely client-side so check toggles feel instant.
            // The server computes the canonical numbers on every getDesignationChecklist().
            function recomputeScores() {
                var earnedT = 0, totalT = 0, checkedT = 0, countT = 0;
                state.items.forEach(function (item) {
                    var iE = 0, iT = 0, iC = 0;
                    item.checkpoints.forEach(function (cp) {
                        var w = Math.max(1, parseInt(cp.weightage, 10) || 1);
                        iT += w;
                        if (cp.checked) {
                            iE += w;
                            iC++;
                        }
                    });
                    item.score = {
                        percent: iT > 0 ? Math.round((iE / iT) * 100) : 0,
                        earned: iE,
                        total: iT,
                        checked: iC,
                        count: item.checkpoints.length
                    };
                    earnedT += iE; totalT += iT; checkedT += iC; countT += item.checkpoints.length;
                });
                state.overall = {
                    percent: totalT > 0 ? Math.round((earnedT / totalT) * 100) : 0,
                    earned: earnedT,
                    total: totalT,
                    checked: checkedT,
                    count: countT
                };
            }

            function renderOverall() {
                var fill = el('ts-clrr-modal-progress-fill');
                var score = el('ts-clrr-modal-score');
                var detail = el('ts-clrr-modal-score-detail');
                if (fill) fill.style.width = state.overall.percent + '%';
                if (score) score.textContent = state.overall.percent + '%';
                if (detail) {
                    detail.textContent = state.overall.earned + '/' + state.overall.total
                        + ' points · ' + state.overall.checked + ' of ' + state.overall.count + ' checkpoints done';
                }
            }

            function renderItemScore(itemEl, item) {
                var pctEl = itemEl.querySelector('[data-role="item-score"]');
                var barEl = itemEl.querySelector('[data-role="item-score-bar"]');
                if (pctEl) pctEl.textContent = item.score.percent + '% · ' + item.score.earned + '/' + item.score.total;
                if (barEl) barEl.style.width = item.score.percent + '%';
                itemEl.setAttribute('data-fully-done', item.score.count > 0 && item.score.checked === item.score.count ? 'true' : 'false');
            }

            function renderItems() {
                var list = el('ts-clrr-item-list');
                if (!list) return;
                list.innerHTML = '';

                state.items.forEach(function (item) {
                    var card = document.createElement('div');
                    card.className = 'clrr-item-card';
                    card.setAttribute('data-item-id', item.id);
                    card.setAttribute('data-fully-done', item.score.count > 0 && item.score.checked === item.score.count ? 'true' : 'false');

                    var header = document.createElement('div');
                    header.className = 'clrr-item-header';
                    header.innerHTML =
                        '<div class="clrr-item-title">' + escapeHtml(item.title) + '</div>'
                        + '<button type="button" class="clrr-regen-btn" data-action="regen-item" title="Re-generate checkpoints for this R&R with AI">'
                        + '<i class="ri-refresh-line"></i> AI</button>'
                        + '<span class="clrr-item-score" data-role="item-score">'
                        + item.score.percent + '% · ' + item.score.earned + '/' + item.score.total + '</span>';

                    var barWrap = document.createElement('div');
                    barWrap.className = 'clrr-item-score-bar-wrap';
                    barWrap.innerHTML = '<div class="clrr-item-score-bar-fill" data-role="item-score-bar" style="width:' + item.score.percent + '%;"></div>';

                    card.appendChild(header);
                    card.appendChild(barWrap);

                    item.checkpoints.forEach(function (cp) {
                        var row = document.createElement('div');
                        row.className = 'clrr-checkpoint' + (cp.checked ? ' is-checked' : '');
                        row.setAttribute('data-checkpoint-id', cp.id);

                        var cbId = 'ts-clrr-cb-' + cp.id;
                        var inputHtml = '<input class="form-check-input" type="checkbox" id="' + cbId + '" data-action="toggle" ' + (cp.checked ? 'checked' : '') + ' />';
                        var srcBadge = '<span class="clrr-source-badge ' + (cp.source === 'manual' ? 'is-manual' : '') + '">'
                            + (cp.source === 'ai' ? 'AI' : 'Manual') + '</span>';
                        var bodyHtml = '<div class="clrr-checkpoint-body">'
                            + '<label class="clrr-checkpoint-title" for="' + cbId + '">'
                            + escapeHtml(cp.title) + srcBadge
                            + '</label>'
                            + (cp.description ? '<div class="clrr-checkpoint-desc">' + escapeHtml(cp.description) + '</div>' : '')
                            + '</div>';
                        var actionsHtml = '<div class="clrr-checkpoint-actions">'
                            + '<input type="number" class="form-control form-control-sm clrr-weight-input" min="1" max="10" step="1" '
                            + 'value="' + (parseInt(cp.weightage, 10) || 1) + '" data-action="weight" title="Weightage (1–10)" />'
                            + '<button type="button" class="clrr-delete-btn" data-action="delete" title="Remove checkpoint" aria-label="Remove checkpoint"><i class="ri-delete-bin-line"></i></button>'
                            + '</div>';

                        row.innerHTML = inputHtml + bodyHtml + actionsHtml;
                        card.appendChild(row);
                    });

                    var addRow = document.createElement('div');
                    addRow.className = 'clrr-add-row';
                    addRow.innerHTML =
                        '<input type="text" class="form-control form-control-sm clrr-add-input" placeholder="Add a checkpoint…" maxlength="500" />'
                        + '<input type="number" class="form-control form-control-sm clrr-weight-input" min="1" max="10" step="1" value="1" title="Weightage (1–10)" />'
                        + '<button type="button" class="clrr-add-btn" data-action="add" title="Add the typed checkpoint manually"><i class="ri-add-line"></i> Add</button>'
                        + '<button type="button" class="clrr-ai-suggest-btn" data-action="suggest-ai" title="Ask AI to suggest a new checkpoint contextual to this R&R">'
                        +     '<i class="ri-sparkling-line"></i> Ask AI'
                        + '</button>';
                    card.appendChild(addRow);

                    list.appendChild(card);
                });
            }

            function applyState(data) {
                state.designation = data.designation || '';
                state.items = Array.isArray(data.items) ? data.items : [];
                state.overall = data.overall || { percent: 0, earned: 0, total: 0, checked: 0, count: 0 };

                var desEl = el('ts-clrr-modal-designation');
                if (desEl) {
                    desEl.innerHTML = state.designation
                        ? '<i class="ri-briefcase-line me-1"></i>' + escapeHtml(state.designation)
                        : '<em>No designation set</em>';
                }

                var refresh = el('ts-clrr-refresh-ai-btn');

                if (data.needs_rr_seed) {
                    showOnly('needsRr');
                    if (refresh) refresh.style.display = 'none';
                    renderOverall();
                    return;
                }

                if (data.needs_checklist_seed && (!state.items.some(function (i) { return i.checkpoints.length > 0; }))) {
                    showOnly('empty');
                    if (refresh) refresh.style.display = 'none';
                    renderOverall();
                    return;
                }

                renderItems();
                renderOverall();
                showOnly('content');
                if (refresh) refresh.style.display = 'inline-flex';
            }

            function postJson(url, body, method) {
                return fetch(url, {
                    method: method || 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: body ? JSON.stringify(body) : null
                }).then(function (r) {
                    return r.json().then(function (data) {
                        if (!r.ok || data.success === false) {
                            var msg = (data && data.message) ? data.message : 'Request failed (HTTP ' + r.status + ')';
                            throw new Error(msg);
                        }
                        return data;
                    });
                });
            }

            function loadFromServer() {
                clearError();
                showOnly('loading');
                var params = new URLSearchParams();
                if (state.userId) params.set('user_id', String(state.userId));
                if (state.designation) params.set('designation', state.designation);
                var url = endpoints.get + (endpoints.get.indexOf('?') >= 0 ? '&' : '?') + params.toString();

                fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) {
                        if (!r.ok) throw new Error('Could not load CL R&R (HTTP ' + r.status + ')');
                        return r.json();
                    })
                    .then(applyState)
                    .catch(function (err) {
                        showOnly('content');
                        var list = el('ts-clrr-item-list');
                        if (list) list.innerHTML = '';
                        showError(err && err.message ? err.message : 'Failed to load CL R&R.');
                    });
            }

            function generate(opts) {
                opts = opts || {};
                if (!state.designation) {
                    showError('Designation is required.');
                    return;
                }
                clearError();
                showOnly('aiLoading');
                var body = { designation: state.designation };
                if (opts.force) body.force = true;
                if (opts.item_id) body.item_id = opts.item_id;
                postJson(endpoints.generate, body)
                    .then(function () { loadFromServer(); })
                    .catch(function (err) {
                        showOnly(state.items.length > 0 ? 'content' : 'empty');
                        showError(err.message || 'AI generation failed.');
                    });
            }

            function toggleCheckpoint(cpId, checked) {
                if (!state.userId) {
                    showError('Cannot save: user is missing.');
                    return;
                }
                clearError();
                postJson(endpoints.progress, {
                    user_id: state.userId,
                    designation_rr_checkpoint_id: parseInt(cpId, 10),
                    checked: !!checked
                })
                    .then(function () {
                        state.items.forEach(function (item) {
                            item.checkpoints.forEach(function (cp) {
                                if (String(cp.id) === String(cpId)) {
                                    cp.checked = !!checked;
                                    cp.checked_at = checked ? new Date().toISOString() : null;
                                }
                            });
                        });
                        recomputeScores();
                        // Update only the affected item card + the overall header (no full re-render).
                        var cpRow = document.querySelector('#ts-clrr-item-list .clrr-checkpoint[data-checkpoint-id="' + cpId + '"]');
                        if (cpRow) {
                            cpRow.classList.toggle('is-checked', !!checked);
                            var itemEl = cpRow.closest('.clrr-item-card');
                            var itemId = itemEl ? itemEl.getAttribute('data-item-id') : null;
                            var item = state.items.find(function (i) { return String(i.id) === String(itemId); });
                            if (itemEl && item) renderItemScore(itemEl, item);
                        }
                        renderOverall();
                    })
                    .catch(function (err) {
                        // Revert checkbox visually if the save failed.
                        var cb = document.querySelector('#ts-clrr-item-list .clrr-checkpoint[data-checkpoint-id="' + cpId + '"] input[type="checkbox"]');
                        if (cb) cb.checked = !checked;
                        showError(err.message || 'Could not save check state.');
                    });
            }

            function updateWeight(cpId, weight, fromCard) {
                weight = Math.max(1, Math.min(10, parseInt(weight, 10) || 1));
                clearError();
                postJson(endpoints.updateBase + '/' + encodeURIComponent(cpId), { weightage: weight }, 'PATCH')
                    .then(function () {
                        state.items.forEach(function (item) {
                            item.checkpoints.forEach(function (cp) {
                                if (String(cp.id) === String(cpId)) cp.weightage = weight;
                            });
                        });
                        recomputeScores();
                        if (fromCard) {
                            var itemId = fromCard.getAttribute('data-item-id');
                            var item = state.items.find(function (i) { return String(i.id) === String(itemId); });
                            if (item) renderItemScore(fromCard, item);
                        }
                        renderOverall();
                    })
                    .catch(function (err) {
                        showError(err.message || 'Could not update weightage.');
                    });
            }

            function deleteCheckpoint(cpId) {
                if (!window.confirm('Remove this checkpoint? This affects every user with this designation.')) return;
                clearError();
                postJson(endpoints.deleteBase + '/' + encodeURIComponent(cpId), null, 'DELETE')
                    .then(function () {
                        state.items.forEach(function (item) {
                            item.checkpoints = item.checkpoints.filter(function (cp) {
                                return String(cp.id) !== String(cpId);
                            });
                        });
                        recomputeScores();
                        renderItems();
                        renderOverall();
                    })
                    .catch(function (err) {
                        showError(err.message || 'Could not delete checkpoint.');
                    });
            }

            function addCheckpointFromCard(card) {
                var addInput = card.querySelector('.clrr-add-input');
                var weightInput = card.querySelectorAll('.clrr-weight-input');
                var weight = 1;
                if (weightInput && weightInput.length > 0) {
                    // The last weight input in this card is the "add row" one.
                    weight = parseInt(weightInput[weightInput.length - 1].value, 10) || 1;
                }
                var title = (addInput && addInput.value || '').trim();
                if (!title) {
                    showError('Enter a checkpoint title first.');
                    return;
                }
                var itemId = parseInt(card.getAttribute('data-item-id'), 10);
                clearError();
                postJson(endpoints.add, {
                    designation_rr_item_id: itemId,
                    title: title,
                    weightage: weight
                })
                    .then(function (data) {
                        if (addInput) addInput.value = '';
                        var item = state.items.find(function (i) { return i.id === itemId; });
                        if (item) {
                            item.checkpoints.push(data.checkpoint);
                            recomputeScores();
                            renderItems();
                            renderOverall();
                        }
                    })
                    .catch(function (err) {
                        showError(err.message || 'Could not add checkpoint.');
                    });
            }

            function suggestCheckpointFromCard(card, btn) {
                var itemId = parseInt(card.getAttribute('data-item-id'), 10);
                if (!itemId) return;
                clearError();
                var originalHtml = btn ? btn.innerHTML : '';
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="ri-loader-4-line spin"></i> Thinking…';
                }
                postJson(endpoints.suggest, { designation_rr_item_id: itemId })
                    .then(function (data) {
                        if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
                        var item = state.items.find(function (i) { return i.id === itemId; });
                        if (item && data.checkpoint) {
                            item.checkpoints.push(data.checkpoint);
                            recomputeScores();
                            renderItems();
                            renderOverall();
                        }
                    })
                    .catch(function (err) {
                        if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
                        showError(err.message || 'AI could not suggest a checkpoint.');
                    });
            }

            // Open from any magnifying-glass CL R&R button.
            document.addEventListener('click', function (e) {
                var target = e.target;
                if (!target || !target.closest) return;

                var openBtn = target.closest('.task-summary-clrr-btn');
                if (openBtn) {
                    e.preventDefault();
                    if (openBtn.disabled) return;
                    state.userId = parseInt(openBtn.getAttribute('data-user-id'), 10) || null;
                    state.userName = openBtn.getAttribute('data-user-name') || '';
                    state.designation = (openBtn.getAttribute('data-designation') || '').trim();
                    state.items = [];
                    state.overall = { percent: 0, earned: 0, total: 0, checked: 0, count: 0 };

                    var userLabel = el('ts-clrr-modal-user');
                    if (userLabel) {
                        userLabel.textContent = state.userName
                            ? (state.userName + ' — CL R&R')
                            : 'CL R&R — Checklist';
                    }
                    renderOverall();
                    clearError();
                    showModal();
                    loadFromServer();
                    return;
                }

                if (target.closest('#ts-clrr-generate-ai-btn')) {
                    e.preventDefault();
                    generate({ force: false });
                    return;
                }
                if (target.closest('#ts-clrr-refresh-ai-btn')) {
                    e.preventDefault();
                    if (!window.confirm('Refresh will replace existing AI-seeded checkpoints with new ones. Continue?')) return;
                    generate({ force: true });
                    return;
                }
                var regenBtn = target.closest('button[data-action="regen-item"]');
                if (regenBtn) {
                    e.preventDefault();
                    var card = regenBtn.closest('.clrr-item-card');
                    if (!card) return;
                    var itemId = parseInt(card.getAttribute('data-item-id'), 10);
                    if (!window.confirm('Re-generate AI checkpoints for this R&R item? Existing checkpoints will be replaced.')) return;
                    generate({ force: true, item_id: itemId });
                    return;
                }
                var addBtn = target.closest('button[data-action="add"]');
                if (addBtn && addBtn.closest('#ts-clrr-item-list')) {
                    e.preventDefault();
                    var addCard = addBtn.closest('.clrr-item-card');
                    if (addCard) addCheckpointFromCard(addCard);
                    return;
                }
                var suggestBtn = target.closest('button[data-action="suggest-ai"]');
                if (suggestBtn && suggestBtn.closest('#ts-clrr-item-list')) {
                    e.preventDefault();
                    var suggestCard = suggestBtn.closest('.clrr-item-card');
                    if (suggestCard) suggestCheckpointFromCard(suggestCard, suggestBtn);
                    return;
                }
                var delBtn = target.closest('#ts-clrr-item-list button[data-action="delete"]');
                if (delBtn) {
                    e.preventDefault();
                    var row = delBtn.closest('.clrr-checkpoint');
                    if (row) deleteCheckpoint(row.getAttribute('data-checkpoint-id'));
                    return;
                }
            });

            // Checkbox toggles.
            document.addEventListener('change', function (e) {
                var t = e.target;
                if (!t || !t.closest) return;
                var cb = t.closest('#ts-clrr-item-list input[type="checkbox"][data-action="toggle"]');
                if (cb) {
                    var row = cb.closest('.clrr-checkpoint');
                    if (row) toggleCheckpoint(row.getAttribute('data-checkpoint-id'), cb.checked);
                    return;
                }
                // Weightage editor on existing checkpoints.
                var w = t.closest('#ts-clrr-item-list .clrr-checkpoint input.clrr-weight-input[data-action="weight"]');
                if (w) {
                    var rowW = w.closest('.clrr-checkpoint');
                    var card = w.closest('.clrr-item-card');
                    if (rowW) updateWeight(rowW.getAttribute('data-checkpoint-id'), w.value, card);
                }
            });

            // Enter in add-input commits the checkpoint.
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                var t = e.target;
                if (t && t.classList && t.classList.contains('clrr-add-input')) {
                    e.preventDefault();
                    var card = t.closest('.clrr-item-card');
                    if (card) addCheckpointFromCard(card);
                }
            });
        })();

        // -------------------------------------------------------------------
        // CL Mgr modal (Manager / Senior checklist + juniors roll-up score)
        // -------------------------------------------------------------------
        (function () {
            var csrfToken = (function () {
                var meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.getAttribute('content') : '';
            })();

            var endpoints = {
                get: @json(route('tasks.mgrChecklist.get')),
                generate: @json(route('tasks.mgrChecklist.generate')),
                add: @json(route('tasks.mgrChecklist.add')),
                updateBase: @json(url('/tasks/mgr-checklist/items')),
                deleteBase: @json(url('/tasks/mgr-checklist/items')),
                progress: @json(route('tasks.mgrChecklist.progress')),
                juniorsAdd: @json(route('tasks.mgrChecklist.juniors.add')),
                juniorsRemove: @json(route('tasks.mgrChecklist.juniors.remove'))
            };

            var state = {
                userId: null,
                userName: '',
                designation: '',
                items: [],
                juniors: [],
                eligible: [],
                ownScore: { percent: 0, earned: 0, total: 0, checked: 0, count: 0 },
                juniorsScore: { percent: 0, count: 0 },
                combinedScore: { percent: 0, own_weight: 0.6, juniors_weight: 0.4 }
            };

            var PANE_IDS = {
                loading: 'ts-clmgr-loading',
                aiLoading: 'ts-clmgr-ai-loading',
                empty: 'ts-clmgr-empty',
                content: 'ts-clmgr-content'
            };

            function el(id) { return document.getElementById(id); }
            function getModalEl() { return document.getElementById('taskSummaryClMgrModal'); }
            function getBsModal() {
                var m = getModalEl();
                if (!m) return null;
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    return bootstrap.Modal.getOrCreateInstance(m);
                }
                return null;
            }
            function fallbackShow(modalEl) {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                modalEl.removeAttribute('aria-hidden');
                modalEl.setAttribute('aria-modal', 'true');
                document.body.classList.add('modal-open');
                if (!document.getElementById('ts-clmgr-fallback-backdrop')) {
                    var bd = document.createElement('div');
                    bd.id = 'ts-clmgr-fallback-backdrop';
                    bd.className = 'modal-backdrop fade show';
                    document.body.appendChild(bd);
                    bd.addEventListener('click', function () { fallbackHide(modalEl); });
                }
                modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (b) {
                    b.addEventListener('click', function () { fallbackHide(modalEl); }, { once: true });
                });
            }
            function fallbackHide(modalEl) {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                document.body.classList.remove('modal-open');
                var bd = document.getElementById('ts-clmgr-fallback-backdrop');
                if (bd) bd.remove();
            }
            function showModal() {
                var modalEl = getModalEl();
                if (!modalEl) {
                    console.error('[CL Mgr] taskSummaryClMgrModal element not found in DOM. Did the partial load?');
                    return;
                }
                var bs = getBsModal();
                if (bs) bs.show(); else fallbackShow(modalEl);
            }

            function showOnly(key) {
                Object.keys(PANE_IDS).forEach(function (k) {
                    var n = el(PANE_IDS[k]);
                    if (n) n.classList.toggle('d-none', k !== key);
                });
            }
            function showError(msg) {
                var n = el('ts-clmgr-error');
                if (!n) return;
                n.textContent = msg || 'Something went wrong.';
                n.classList.remove('d-none');
            }
            function clearError() {
                var n = el('ts-clmgr-error');
                if (n) { n.classList.add('d-none'); n.textContent = ''; }
            }
            function escapeHtml(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            // Recompute Own score (client-side) on toggle/weight change.
            function recomputeOwnScore() {
                var earned = 0, total = 0, checked = 0;
                state.items.forEach(function (i) {
                    var w = Math.max(1, parseInt(i.weightage, 10) || 1);
                    total += w;
                    if (i.checked) { earned += w; checked++; }
                });
                state.ownScore = {
                    percent: total > 0 ? Math.round((earned / total) * 100) : 0,
                    earned: earned,
                    total: total,
                    checked: checked,
                    count: state.items.length
                };
                recomputeCombined();
            }
            function recomputeJuniorsScore() {
                if (state.juniors.length === 0) {
                    state.juniorsScore = { percent: 0, count: 0 };
                } else {
                    var sum = 0;
                    state.juniors.forEach(function (j) { sum += parseInt(j.blend_percent, 10) || 0; });
                    state.juniorsScore = {
                        percent: Math.round(sum / state.juniors.length),
                        count: state.juniors.length
                    };
                }
                recomputeCombined();
            }
            function recomputeCombined() {
                var ownW = parseFloat(state.combinedScore.own_weight) || 0.6;
                var juW = parseFloat(state.combinedScore.juniors_weight) || 0.4;
                var combined;
                if (state.juniors.length === 0) {
                    combined = state.ownScore.percent;
                } else {
                    combined = Math.round((state.ownScore.percent * ownW) + (state.juniorsScore.percent * juW));
                }
                state.combinedScore.percent = combined;
            }

            function renderScores() {
                var oP = el('ts-clmgr-own-pct'); if (oP) oP.textContent = state.ownScore.percent + '%';
                var oS = el('ts-clmgr-own-sub'); if (oS) oS.textContent = state.ownScore.earned + '/' + state.ownScore.total + ' pts · ' + state.ownScore.checked + ' of ' + state.ownScore.count + ' done';
                var jP = el('ts-clmgr-juniors-pct'); if (jP) jP.textContent = state.juniorsScore.percent + '%';
                var jS = el('ts-clmgr-juniors-sub'); if (jS) jS.textContent = state.juniorsScore.count + ' junior' + (state.juniorsScore.count === 1 ? '' : 's');
                var cP = el('ts-clmgr-combined-pct'); if (cP) cP.textContent = state.combinedScore.percent + '%';
                var cS = el('ts-clmgr-combined-sub'); if (cS) {
                    cS.textContent = Math.round((state.combinedScore.own_weight || 0.6) * 100) + '% own · '
                        + Math.round((state.combinedScore.juniors_weight || 0.4) * 100) + '% juniors';
                }
                var fill = el('ts-clmgr-progress-fill'); if (fill) fill.style.width = state.combinedScore.percent + '%';
                var badge = el('ts-clmgr-juniors-badge'); if (badge) badge.textContent = String(state.juniors.length);
            }

            function renderItems() {
                var list = el('ts-clmgr-list');
                if (!list) return;
                list.innerHTML = '';

                // Group by category preserving original order.
                var groups = [];
                var idx = {};
                state.items.forEach(function (it) {
                    var cat = (it.category && String(it.category).trim()) || 'General';
                    if (idx[cat] === undefined) {
                        idx[cat] = groups.length;
                        groups.push({ name: cat, items: [] });
                    }
                    groups[idx[cat]].items.push(it);
                });

                groups.forEach(function (group) {
                    var card = document.createElement('div');
                    card.className = 'clmgr-cat-card';
                    var doneCnt = group.items.filter(function (i) { return i.checked; }).length;
                    var header = document.createElement('div');
                    header.className = 'clmgr-cat-header';
                    header.innerHTML =
                        '<i class="ri-folder-2-line"></i><span>' + escapeHtml(group.name) + '</span>'
                        + '<span class="clmgr-cat-count">' + doneCnt + '/' + group.items.length + '</span>';
                    card.appendChild(header);

                    group.items.forEach(function (it) {
                        var row = document.createElement('div');
                        row.className = 'clmgr-item' + (it.checked ? ' is-checked' : '');
                        row.setAttribute('data-item-id', it.id);
                        var cbId = 'ts-clmgr-cb-' + it.id;
                        var inputHtml = '<input class="form-check-input" type="checkbox" id="' + cbId + '" data-action="toggle" ' + (it.checked ? 'checked' : '') + ' />';
                        var srcBadge = '<span class="clmgr-source-badge ' + (it.source === 'manual' ? 'is-manual' : '') + '">'
                            + (it.source === 'ai' ? 'AI' : 'Manual') + '</span>';
                        var bodyHtml = '<div class="clmgr-item-body">'
                            + '<label class="clmgr-item-title" for="' + cbId + '">'
                            + escapeHtml(it.title) + srcBadge
                            + '</label>'
                            + (it.description ? '<div class="clmgr-item-desc">' + escapeHtml(it.description) + '</div>' : '')
                            + '</div>';
                        var actionsHtml = '<div class="clmgr-item-actions">'
                            + '<input type="number" class="form-control form-control-sm clmgr-weight-input" min="1" max="10" step="1" '
                            + 'value="' + (parseInt(it.weightage, 10) || 1) + '" data-action="weight" title="Weightage (1–10)" />'
                            + '<button type="button" class="clmgr-delete-btn" data-action="delete" title="Remove" aria-label="Remove checkpoint"><i class="ri-delete-bin-line"></i></button>'
                            + '</div>';
                        row.innerHTML = inputHtml + bodyHtml + actionsHtml;
                        card.appendChild(row);
                    });
                    list.appendChild(card);
                });
            }

            function renderJuniors() {
                var list = el('ts-clmgr-juniors-list');
                var empty = el('ts-clmgr-juniors-empty');
                if (!list) return;
                list.innerHTML = '';
                if (state.juniors.length === 0) {
                    if (empty) empty.classList.remove('d-none');
                } else {
                    if (empty) empty.classList.add('d-none');
                    state.juniors.forEach(function (j) {
                        var row = document.createElement('div');
                        row.className = 'clmgr-junior-row';
                        row.setAttribute('data-junior-id', j.id);
                        row.innerHTML =
                            '<div class="clmgr-junior-meta">'
                            + '<div class="clmgr-junior-name">' + escapeHtml(j.name) + '</div>'
                            + '<div class="clmgr-junior-des">' + escapeHtml(j.designation || '—') + '</div>'
                            + '</div>'
                            + '<div class="clmgr-junior-scores">'
                            + '<span class="pill clrr" title="CL R&R %">R ' + j.clrr_percent + '%</span>'
                            + '<span class="pill clgen" title="CL Gen %">G ' + j.clgen_percent + '%</span>'
                            + '<span class="pill blend" title="(R+G)/2 — contributes to manager\u2019s combined score">Avg ' + j.blend_percent + '%</span>'
                            + '</div>'
                            + '<button type="button" class="clmgr-junior-remove" data-action="remove-junior" title="Unassign junior"><i class="ri-close-line"></i></button>';
                        list.appendChild(row);
                    });
                }
                // Refresh the eligible-juniors select.
                var sel = el('ts-clmgr-add-junior');
                if (sel) {
                    sel.innerHTML = '<option value="">Select a team member…</option>';
                    state.eligible.forEach(function (u) {
                        var opt = document.createElement('option');
                        opt.value = String(u.id);
                        opt.textContent = u.name + (u.designation ? ' — ' + u.designation : '');
                        sel.appendChild(opt);
                    });
                }
            }

            function applyState(data) {
                state.designation = data.designation || '';
                state.items = Array.isArray(data.items) ? data.items : [];
                state.juniors = Array.isArray(data.juniors) ? data.juniors : [];
                state.eligible = Array.isArray(data.eligible_juniors) ? data.eligible_juniors : [];
                state.ownScore = data.own_score || { percent: 0, earned: 0, total: 0, checked: 0, count: 0 };
                state.juniorsScore = data.juniors_score || { percent: 0, count: 0 };
                state.combinedScore = data.combined_score || { percent: 0, own_weight: 0.6, juniors_weight: 0.4 };

                var desEl = el('ts-clmgr-modal-designation');
                if (desEl) {
                    desEl.innerHTML = state.designation
                        ? '<i class="ri-briefcase-line me-1"></i>' + escapeHtml(state.designation)
                        : '<em>No designation set</em>';
                }

                var refresh = el('ts-clmgr-refresh-ai-btn');

                if (data.needs_seed && state.items.length === 0) {
                    renderScores();
                    if (refresh) refresh.style.display = 'none';
                    showOnly('empty');
                    return;
                }

                renderItems();
                renderJuniors();
                renderScores();
                showOnly('content');
                if (refresh) refresh.style.display = 'inline-flex';
            }

            function postJson(url, body, method) {
                return fetch(url, {
                    method: method || 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: body ? JSON.stringify(body) : null
                }).then(function (r) {
                    return r.json().then(function (data) {
                        if (!r.ok || data.success === false) {
                            var msg = (data && data.message) ? data.message : 'Request failed (HTTP ' + r.status + ')';
                            throw new Error(msg);
                        }
                        return data;
                    });
                });
            }

            function loadFromServer() {
                clearError();
                showOnly('loading');
                var params = new URLSearchParams();
                if (state.userId) params.set('user_id', String(state.userId));
                if (state.designation) params.set('designation', state.designation);
                var url = endpoints.get + (endpoints.get.indexOf('?') >= 0 ? '&' : '?') + params.toString();
                fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) {
                        if (!r.ok) throw new Error('Could not load CL Mgr (HTTP ' + r.status + ')');
                        return r.json();
                    })
                    .then(applyState)
                    .catch(function (err) {
                        showOnly('content');
                        var list = el('ts-clmgr-list');
                        if (list) list.innerHTML = '';
                        showError(err && err.message ? err.message : 'Failed to load CL Mgr.');
                    });
            }

            function generate(opts) {
                opts = opts || {};
                if (!state.designation) {
                    showError('Designation is required.');
                    return;
                }
                clearError();
                showOnly('aiLoading');
                var body = { designation: state.designation };
                if (opts.force) body.force = true;
                postJson(endpoints.generate, body)
                    .then(function () { loadFromServer(); })
                    .catch(function (err) {
                        showOnly(state.items.length > 0 ? 'content' : 'empty');
                        showError(err.message || 'AI generation failed.');
                    });
            }

            function toggleItem(itemId, checked) {
                if (!state.userId) {
                    showError('Cannot save: user is missing.');
                    return;
                }
                clearError();
                postJson(endpoints.progress, {
                    user_id: state.userId,
                    designation_mgr_checkpoint_id: parseInt(itemId, 10),
                    checked: !!checked
                })
                    .then(function () {
                        state.items.forEach(function (i) {
                            if (String(i.id) === String(itemId)) {
                                i.checked = !!checked;
                                i.checked_at = checked ? new Date().toISOString() : null;
                            }
                        });
                        recomputeOwnScore();
                        renderItems();
                        renderScores();
                    })
                    .catch(function (err) {
                        var cb = document.querySelector('#ts-clmgr-list .clmgr-item[data-item-id="' + itemId + '"] input[type="checkbox"]');
                        if (cb) cb.checked = !checked;
                        showError(err.message || 'Could not save check state.');
                    });
            }

            function updateWeight(itemId, weight) {
                weight = Math.max(1, Math.min(10, parseInt(weight, 10) || 1));
                clearError();
                postJson(endpoints.updateBase + '/' + encodeURIComponent(itemId), { weightage: weight }, 'PATCH')
                    .then(function () {
                        state.items.forEach(function (i) {
                            if (String(i.id) === String(itemId)) i.weightage = weight;
                        });
                        recomputeOwnScore();
                        renderScores();
                    })
                    .catch(function (err) {
                        showError(err.message || 'Could not update weightage.');
                    });
            }

            function deleteItem(itemId) {
                if (!window.confirm('Remove this checkpoint from the manager checklist? Affects every user with this designation.')) return;
                clearError();
                postJson(endpoints.deleteBase + '/' + encodeURIComponent(itemId), null, 'DELETE')
                    .then(function () {
                        state.items = state.items.filter(function (i) { return String(i.id) !== String(itemId); });
                        recomputeOwnScore();
                        renderItems();
                        renderScores();
                        if (state.items.length === 0) showOnly('empty');
                    })
                    .catch(function (err) {
                        showError(err.message || 'Could not delete checkpoint.');
                    });
            }

            function addItem() {
                var t = el('ts-clmgr-add-title');
                var c = el('ts-clmgr-add-category');
                var w = el('ts-clmgr-add-weight');
                var btn = el('ts-clmgr-add-btn');
                var title = (t && t.value || '').trim();
                if (!title) {
                    showError('Enter a checkpoint title first.');
                    return;
                }
                if (!state.designation) {
                    showError('Designation is required.');
                    return;
                }
                var payload = {
                    designation: state.designation,
                    title: title,
                    category: (c && c.value || '').trim() || null,
                    weightage: parseInt((w && w.value) || '1', 10) || 1
                };
                clearError();
                if (btn) btn.disabled = true;
                postJson(endpoints.add, payload)
                    .then(function (data) {
                        if (btn) btn.disabled = false;
                        if (t) t.value = '';
                        state.items.push(data.item);
                        recomputeOwnScore();
                        renderItems();
                        renderScores();
                    })
                    .catch(function (err) {
                        if (btn) btn.disabled = false;
                        showError(err.message || 'Could not add checkpoint.');
                    });
            }

            function addJunior() {
                var sel = el('ts-clmgr-add-junior');
                if (!sel || !sel.value) {
                    showError('Select a team member first.');
                    return;
                }
                if (!state.userId) {
                    showError('Manager user is missing.');
                    return;
                }
                var jid = parseInt(sel.value, 10);
                clearError();
                postJson(endpoints.juniorsAdd, {
                    manager_user_id: state.userId,
                    junior_user_id: jid
                })
                    .then(function (data) {
                        state.juniors.push(data.junior);
                        state.eligible = state.eligible.filter(function (u) { return parseInt(u.id, 10) !== jid; });
                        recomputeJuniorsScore();
                        renderJuniors();
                        renderScores();
                    })
                    .catch(function (err) {
                        showError(err.message || 'Could not add junior.');
                    });
            }

            function removeJunior(juniorId) {
                if (!window.confirm('Unassign this junior from the manager? Their score will no longer contribute.')) return;
                clearError();
                postJson(endpoints.juniorsRemove, {
                    manager_user_id: state.userId,
                    junior_user_id: parseInt(juniorId, 10)
                }, 'DELETE')
                    .then(function () {
                        var removed = state.juniors.find(function (j) { return String(j.id) === String(juniorId); });
                        state.juniors = state.juniors.filter(function (j) { return String(j.id) !== String(juniorId); });
                        if (removed) {
                            state.eligible.push({
                                id: removed.id,
                                name: removed.name,
                                email: removed.email,
                                designation: removed.designation
                            });
                            state.eligible.sort(function (a, b) { return String(a.name).localeCompare(String(b.name)); });
                        }
                        recomputeJuniorsScore();
                        renderJuniors();
                        renderScores();
                    })
                    .catch(function (err) {
                        showError(err.message || 'Could not remove junior.');
                    });
            }

            // Open from any CL Mgr magnifying-glass button.
            document.addEventListener('click', function (e) {
                var target = e.target;
                if (!target || !target.closest) return;

                var openBtn = target.closest('.task-summary-clmgr-btn');
                if (openBtn) {
                    e.preventDefault();
                    if (openBtn.disabled) return;
                    state.userId = parseInt(openBtn.getAttribute('data-user-id'), 10) || null;
                    state.userName = openBtn.getAttribute('data-user-name') || '';
                    state.designation = (openBtn.getAttribute('data-designation') || '').trim();
                    state.items = [];
                    state.juniors = [];
                    state.eligible = [];
                    state.ownScore = { percent: 0, earned: 0, total: 0, checked: 0, count: 0 };
                    state.juniorsScore = { percent: 0, count: 0 };
                    state.combinedScore = { percent: 0, own_weight: 0.6, juniors_weight: 0.4 };
                    var userLabel = el('ts-clmgr-modal-user');
                    if (userLabel) {
                        userLabel.textContent = state.userName
                            ? (state.userName + ' — CL Mgr')
                            : 'CL Mgr — Manager Checklist';
                    }
                    // Reset to checklist tab.
                    document.querySelectorAll('#taskSummaryClMgrModal .clmgr-tab').forEach(function (b) {
                        var on = b.getAttribute('data-tab') === 'checklist';
                        b.classList.toggle('is-active', on);
                        b.setAttribute('aria-selected', on ? 'true' : 'false');
                    });
                    document.querySelectorAll('#taskSummaryClMgrModal [data-pane]').forEach(function (p) {
                        p.classList.toggle('d-none', p.getAttribute('data-pane') !== 'checklist');
                    });
                    var addT = el('ts-clmgr-add-title'); if (addT) addT.value = '';
                    renderScores();
                    clearError();
                    showModal();
                    loadFromServer();
                    return;
                }

                // Tab switching inside the modal.
                var tabBtn = target.closest('#taskSummaryClMgrModal .clmgr-tab');
                if (tabBtn) {
                    e.preventDefault();
                    var name = tabBtn.getAttribute('data-tab');
                    document.querySelectorAll('#taskSummaryClMgrModal .clmgr-tab').forEach(function (b) {
                        var on = b === tabBtn;
                        b.classList.toggle('is-active', on);
                        b.setAttribute('aria-selected', on ? 'true' : 'false');
                    });
                    document.querySelectorAll('#taskSummaryClMgrModal [data-pane]').forEach(function (p) {
                        p.classList.toggle('d-none', p.getAttribute('data-pane') !== name);
                    });
                    return;
                }

                if (target.closest('#ts-clmgr-generate-ai-btn')) {
                    e.preventDefault();
                    generate({ force: false });
                    return;
                }
                if (target.closest('#ts-clmgr-refresh-ai-btn')) {
                    e.preventDefault();
                    if (!window.confirm('Refresh will replace existing manager checkpoints for this designation with new AI-generated ones. Continue?')) return;
                    generate({ force: true });
                    return;
                }
                if (target.closest('#ts-clmgr-add-btn')) {
                    e.preventDefault();
                    addItem();
                    return;
                }
                if (target.closest('#ts-clmgr-add-junior-btn')) {
                    e.preventDefault();
                    addJunior();
                    return;
                }
                var del = target.closest('#ts-clmgr-list button[data-action="delete"]');
                if (del) {
                    e.preventDefault();
                    var row = del.closest('.clmgr-item');
                    if (row) deleteItem(row.getAttribute('data-item-id'));
                    return;
                }
                var rem = target.closest('#ts-clmgr-juniors-list button[data-action="remove-junior"]');
                if (rem) {
                    e.preventDefault();
                    var jrow = rem.closest('.clmgr-junior-row');
                    if (jrow) removeJunior(jrow.getAttribute('data-junior-id'));
                    return;
                }
            });

            // Toggles + weight changes via delegated change handler.
            document.addEventListener('change', function (e) {
                var t = e.target;
                if (!t || !t.closest) return;
                var cb = t.closest('#ts-clmgr-list input[type="checkbox"][data-action="toggle"]');
                if (cb) {
                    var row = cb.closest('.clmgr-item');
                    if (row) toggleItem(row.getAttribute('data-item-id'), cb.checked);
                    return;
                }
                var w = t.closest('#ts-clmgr-list input.clmgr-weight-input[data-action="weight"]');
                if (w) {
                    var rowW = w.closest('.clmgr-item');
                    if (rowW) updateWeight(rowW.getAttribute('data-item-id'), w.value);
                }
            });

            // Enter in add inputs commits the new checkpoint.
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                var t = e.target;
                if (!t) return;
                if (t.id === 'ts-clmgr-add-title' || t.id === 'ts-clmgr-add-category' || t.id === 'ts-clmgr-add-weight') {
                    e.preventDefault();
                    addItem();
                }
            });
        })();

        // -------------------------------------------------------------------
        // CL Gen modal (Global / General team-wide checklist + score)
        // -------------------------------------------------------------------
        (function () {
            var csrfToken = (function () {
                var meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.getAttribute('content') : '';
            })();

            var endpoints = {
                get: @json(route('tasks.generalChecklist.get')),
                generate: @json(route('tasks.generalChecklist.generate')),
                add: @json(route('tasks.generalChecklist.add')),
                updateBase: @json(url('/tasks/general-checklist/items')),
                deleteBase: @json(url('/tasks/general-checklist/items')),
                progress: @json(route('tasks.generalChecklist.progress'))
            };

            var state = {
                userId: null,
                userName: '',
                items: [],
                score: { percent: 0, earned: 0, total: 0, checked: 0, count: 0 }
            };

            var PANE_IDS = {
                loading: 'ts-clgen-loading',
                aiLoading: 'ts-clgen-ai-loading',
                empty: 'ts-clgen-empty',
                content: 'ts-clgen-content'
            };

            function el(id) { return document.getElementById(id); }
            function getModalEl() { return document.getElementById('taskSummaryClGenModal'); }

            function getBsModal() {
                var m = getModalEl();
                if (!m) return null;
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    return bootstrap.Modal.getOrCreateInstance(m);
                }
                return null;
            }

            function fallbackShow(modalEl) {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                modalEl.removeAttribute('aria-hidden');
                modalEl.setAttribute('aria-modal', 'true');
                document.body.classList.add('modal-open');
                if (!document.getElementById('ts-clgen-fallback-backdrop')) {
                    var bd = document.createElement('div');
                    bd.id = 'ts-clgen-fallback-backdrop';
                    bd.className = 'modal-backdrop fade show';
                    document.body.appendChild(bd);
                    bd.addEventListener('click', function () { fallbackHide(modalEl); });
                }
                modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (b) {
                    b.addEventListener('click', function () { fallbackHide(modalEl); }, { once: true });
                });
            }
            function fallbackHide(modalEl) {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                document.body.classList.remove('modal-open');
                var bd = document.getElementById('ts-clgen-fallback-backdrop');
                if (bd) bd.remove();
            }

            function showModal() {
                var modalEl = getModalEl();
                if (!modalEl) {
                    console.error('[CL Gen] taskSummaryClGenModal element not found in DOM. Did the partial load?');
                    return;
                }
                var bs = getBsModal();
                if (bs) bs.show(); else fallbackShow(modalEl);
            }

            function showOnly(key) {
                Object.keys(PANE_IDS).forEach(function (k) {
                    var n = el(PANE_IDS[k]);
                    if (n) n.classList.toggle('d-none', k !== key);
                });
            }

            function showError(msg) {
                var n = el('ts-clgen-error');
                if (!n) return;
                n.textContent = msg || 'Something went wrong.';
                n.classList.remove('d-none');
            }

            function clearError() {
                var n = el('ts-clgen-error');
                if (n) { n.classList.add('d-none'); n.textContent = ''; }
            }

            function escapeHtml(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            // Optimistic client-side recompute so toggles feel instant.
            function recomputeScore() {
                var earned = 0, total = 0, checked = 0;
                state.items.forEach(function (i) {
                    var w = Math.max(1, parseInt(i.weightage, 10) || 1);
                    total += w;
                    if (i.checked) {
                        earned += w;
                        checked++;
                    }
                });
                state.score = {
                    percent: total > 0 ? Math.round((earned / total) * 100) : 0,
                    earned: earned,
                    total: total,
                    checked: checked,
                    count: state.items.length
                };
            }

            function renderScore() {
                var fill = el('ts-clgen-modal-progress-fill');
                var sc = el('ts-clgen-modal-score');
                var detail = el('ts-clgen-modal-score-detail');
                if (fill) fill.style.width = state.score.percent + '%';
                if (sc) sc.textContent = state.score.percent + '%';
                if (detail) {
                    detail.textContent = state.score.earned + '/' + state.score.total
                        + ' points · ' + state.score.checked + ' of ' + state.score.count + ' checks done';
                }
            }

            function renderItems() {
                var list = el('ts-clgen-list');
                if (!list) return;
                list.innerHTML = '';

                // Group by category (preserve original order across categories
                // by walking the items array and bucketing the first time we
                // see each category).
                var groups = []; // [{name, items}]
                var idxByCat = {};
                state.items.forEach(function (it) {
                    var cat = (it.category && String(it.category).trim()) || 'General';
                    if (idxByCat[cat] === undefined) {
                        idxByCat[cat] = groups.length;
                        groups.push({ name: cat, items: [] });
                    }
                    groups[idxByCat[cat]].items.push(it);
                });

                groups.forEach(function (group) {
                    var card = document.createElement('div');
                    card.className = 'clgen-cat-card';
                    var doneInCat = group.items.filter(function (i) { return i.checked; }).length;

                    var header = document.createElement('div');
                    header.className = 'clgen-cat-header';
                    header.innerHTML =
                        '<i class="ri-folder-2-line"></i><span>' + escapeHtml(group.name) + '</span>'
                        + '<span class="clgen-cat-count">' + doneInCat + '/' + group.items.length + '</span>';
                    card.appendChild(header);

                    group.items.forEach(function (it) {
                        var row = document.createElement('div');
                        row.className = 'clgen-item' + (it.checked ? ' is-checked' : '');
                        row.setAttribute('data-item-id', it.id);

                        var cbId = 'ts-clgen-cb-' + it.id;
                        var inputHtml = '<input class="form-check-input" type="checkbox" id="' + cbId + '" data-action="toggle" ' + (it.checked ? 'checked' : '') + ' />';
                        var srcBadge = '<span class="clgen-source-badge ' + (it.source === 'manual' ? 'is-manual' : '') + '">'
                            + (it.source === 'ai' ? 'AI' : 'Manual') + '</span>';
                        var bodyHtml = '<div class="clgen-item-body">'
                            + '<label class="clgen-item-title" for="' + cbId + '">'
                            + escapeHtml(it.title) + srcBadge
                            + '</label>'
                            + (it.description ? '<div class="clgen-item-desc">' + escapeHtml(it.description) + '</div>' : '')
                            + '</div>';
                        var actionsHtml = '<div class="clgen-item-actions">'
                            + '<input type="number" class="form-control form-control-sm clgen-weight-input" min="1" max="10" step="1" '
                            + 'value="' + (parseInt(it.weightage, 10) || 1) + '" data-action="weight" title="Weightage (1–10)" />'
                            + '<button type="button" class="clgen-delete-btn" data-action="delete" title="Remove" aria-label="Remove checkpoint"><i class="ri-delete-bin-line"></i></button>'
                            + '</div>';

                        row.innerHTML = inputHtml + bodyHtml + actionsHtml;
                        card.appendChild(row);
                    });

                    list.appendChild(card);
                });
            }

            function applyState(data) {
                state.items = Array.isArray(data.items) ? data.items : [];
                state.score = data.score || { percent: 0, earned: 0, total: 0, checked: 0, count: 0 };
                renderScore();
                if (data.needs_seed || state.items.length === 0) {
                    var refresh = el('ts-clgen-refresh-ai-btn');
                    if (refresh) refresh.style.display = 'none';
                    showOnly('empty');
                    return;
                }
                renderItems();
                showOnly('content');
                var refreshBtn = el('ts-clgen-refresh-ai-btn');
                if (refreshBtn) refreshBtn.style.display = 'inline-flex';
            }

            function postJson(url, body, method) {
                return fetch(url, {
                    method: method || 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: body ? JSON.stringify(body) : null
                }).then(function (r) {
                    return r.json().then(function (data) {
                        if (!r.ok || data.success === false) {
                            var msg = (data && data.message) ? data.message : 'Request failed (HTTP ' + r.status + ')';
                            throw new Error(msg);
                        }
                        return data;
                    });
                });
            }

            function loadFromServer() {
                clearError();
                showOnly('loading');
                var params = new URLSearchParams();
                if (state.userId) params.set('user_id', String(state.userId));
                var url = endpoints.get + (endpoints.get.indexOf('?') >= 0 ? '&' : '?') + params.toString();
                fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) {
                        if (!r.ok) throw new Error('Could not load General Checklist (HTTP ' + r.status + ')');
                        return r.json();
                    })
                    .then(applyState)
                    .catch(function (err) {
                        showOnly('content');
                        var list = el('ts-clgen-list');
                        if (list) list.innerHTML = '';
                        showError(err && err.message ? err.message : 'Failed to load.');
                    });
            }

            function generate(opts) {
                opts = opts || {};
                clearError();
                showOnly('aiLoading');
                postJson(endpoints.generate, opts.force ? { force: true } : {})
                    .then(function () { loadFromServer(); })
                    .catch(function (err) {
                        showOnly(state.items.length > 0 ? 'content' : 'empty');
                        showError(err.message || 'AI generation failed.');
                    });
            }

            function toggleItem(itemId, checked) {
                if (!state.userId) {
                    showError('Cannot save: user is missing.');
                    return;
                }
                clearError();
                postJson(endpoints.progress, {
                    user_id: state.userId,
                    general_checklist_item_id: parseInt(itemId, 10),
                    checked: !!checked
                })
                    .then(function () {
                        state.items.forEach(function (i) {
                            if (String(i.id) === String(itemId)) {
                                i.checked = !!checked;
                                i.checked_at = checked ? new Date().toISOString() : null;
                            }
                        });
                        recomputeScore();
                        var row = document.querySelector('#ts-clgen-list .clgen-item[data-item-id="' + itemId + '"]');
                        if (row) row.classList.toggle('is-checked', !!checked);
                        // Refresh the category counter on the affected group.
                        renderItems();
                        renderScore();
                    })
                    .catch(function (err) {
                        var cb = document.querySelector('#ts-clgen-list .clgen-item[data-item-id="' + itemId + '"] input[type="checkbox"]');
                        if (cb) cb.checked = !checked;
                        showError(err.message || 'Could not save check state.');
                    });
            }

            function updateWeight(itemId, weight) {
                weight = Math.max(1, Math.min(10, parseInt(weight, 10) || 1));
                clearError();
                postJson(endpoints.updateBase + '/' + encodeURIComponent(itemId), { weightage: weight }, 'PATCH')
                    .then(function () {
                        state.items.forEach(function (i) {
                            if (String(i.id) === String(itemId)) i.weightage = weight;
                        });
                        recomputeScore();
                        renderScore();
                    })
                    .catch(function (err) {
                        showError(err.message || 'Could not update weightage.');
                    });
            }

            function deleteItem(itemId) {
                if (!window.confirm('Remove this checkpoint from the General Checklist? It will be removed for everyone.')) return;
                clearError();
                postJson(endpoints.deleteBase + '/' + encodeURIComponent(itemId), null, 'DELETE')
                    .then(function () {
                        state.items = state.items.filter(function (i) { return String(i.id) !== String(itemId); });
                        recomputeScore();
                        renderItems();
                        renderScore();
                        if (state.items.length === 0) showOnly('empty');
                    })
                    .catch(function (err) {
                        showError(err.message || 'Could not delete checkpoint.');
                    });
            }

            function addItem() {
                var titleEl = el('ts-clgen-add-title');
                var catEl = el('ts-clgen-add-category');
                var wEl = el('ts-clgen-add-weight');
                var btn = el('ts-clgen-add-btn');
                var title = (titleEl && titleEl.value || '').trim();
                if (!title) {
                    showError('Enter a checkpoint title first.');
                    return;
                }
                var payload = {
                    title: title,
                    category: (catEl && catEl.value || '').trim() || null,
                    weightage: parseInt((wEl && wEl.value) || '1', 10) || 1
                };
                clearError();
                if (btn) btn.disabled = true;
                postJson(endpoints.add, payload)
                    .then(function (data) {
                        if (btn) btn.disabled = false;
                        if (titleEl) titleEl.value = '';
                        // Keep category/weight for quick repeat-add.
                        state.items.push(data.item);
                        recomputeScore();
                        renderItems();
                        renderScore();
                    })
                    .catch(function (err) {
                        if (btn) btn.disabled = false;
                        showError(err.message || 'Could not add checkpoint.');
                    });
            }

            // Open from any CL Gen magnifying-glass button.
            document.addEventListener('click', function (e) {
                var target = e.target;
                if (!target || !target.closest) return;

                var openBtn = target.closest('.task-summary-clgen-btn');
                if (openBtn) {
                    e.preventDefault();
                    if (openBtn.disabled) return;
                    state.userId = parseInt(openBtn.getAttribute('data-user-id'), 10) || null;
                    state.userName = openBtn.getAttribute('data-user-name') || '';
                    state.items = [];
                    state.score = { percent: 0, earned: 0, total: 0, checked: 0, count: 0 };
                    var userLabel = el('ts-clgen-modal-user');
                    if (userLabel) {
                        userLabel.textContent = state.userName
                            ? (state.userName + ' — CL Gen')
                            : 'CL Gen — General Checklist';
                    }
                    var addT = el('ts-clgen-add-title');
                    if (addT) addT.value = '';
                    renderScore();
                    clearError();
                    showModal();
                    loadFromServer();
                    return;
                }

                if (target.closest('#ts-clgen-generate-ai-btn')) {
                    e.preventDefault();
                    generate({ force: false });
                    return;
                }
                if (target.closest('#ts-clgen-refresh-ai-btn')) {
                    e.preventDefault();
                    if (!window.confirm('Refresh will replace ALL existing checkpoints with new AI-generated ones. Continue?')) return;
                    generate({ force: true });
                    return;
                }
                if (target.closest('#ts-clgen-add-btn')) {
                    e.preventDefault();
                    addItem();
                    return;
                }
                var del = target.closest('#ts-clgen-list button[data-action="delete"]');
                if (del) {
                    e.preventDefault();
                    var row = del.closest('.clgen-item');
                    if (row) deleteItem(row.getAttribute('data-item-id'));
                    return;
                }
            });

            // Checkbox toggles + weight changes via delegated change handler.
            document.addEventListener('change', function (e) {
                var t = e.target;
                if (!t || !t.closest) return;
                var cb = t.closest('#ts-clgen-list input[type="checkbox"][data-action="toggle"]');
                if (cb) {
                    var row = cb.closest('.clgen-item');
                    if (row) toggleItem(row.getAttribute('data-item-id'), cb.checked);
                    return;
                }
                var w = t.closest('#ts-clgen-list input.clgen-weight-input[data-action="weight"]');
                if (w) {
                    var rowW = w.closest('.clgen-item');
                    if (rowW) updateWeight(rowW.getAttribute('data-item-id'), w.value);
                }
            });

            // Enter in add-title submits the new checkpoint.
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                var t = e.target;
                if (t && (t.id === 'ts-clgen-add-title' || t.id === 'ts-clgen-add-category' || t.id === 'ts-clgen-add-weight')) {
                    e.preventDefault();
                    addItem();
                }
            });
        })();

        // -------------------------------------------------------------------
        // Role column — org-level dropdown + Mgr tags modal
        // -------------------------------------------------------------------
        (function () {
            var csrfToken = (function () {
                var meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.getAttribute('content') : '';
            })();

            var endpoints = {
                updateLevel: @json(route('tasks.users.orgLevel')),
                tagsGet: @json(route('tasks.mgrTags.get')),
                juniorsAdd: @json(route('tasks.mgrChecklist.juniors.add')),
                juniorsRemove: @json(route('tasks.mgrChecklist.juniors.remove'))
            };

            var state = {
                userId: null,
                userName: '',
                designation: '',
                juniors: [],
                eligible: []
            };

            function el(id) { return document.getElementById(id); }
            function getModalEl() { return document.getElementById('taskSummaryMgrTagsModal'); }
            function getBsModal() {
                var m = getModalEl();
                if (!m) return null;
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    return bootstrap.Modal.getOrCreateInstance(m);
                }
                return null;
            }
            function fallbackShow(modalEl) {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                modalEl.removeAttribute('aria-hidden');
                modalEl.setAttribute('aria-modal', 'true');
                document.body.classList.add('modal-open');
                if (!document.getElementById('ts-mgrtag-fallback-backdrop')) {
                    var bd = document.createElement('div');
                    bd.id = 'ts-mgrtag-fallback-backdrop';
                    bd.className = 'modal-backdrop fade show';
                    document.body.appendChild(bd);
                    bd.addEventListener('click', function () { fallbackHide(modalEl); });
                }
                modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (b) {
                    b.addEventListener('click', function () { fallbackHide(modalEl); }, { once: true });
                });
            }
            function fallbackHide(modalEl) {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                document.body.classList.remove('modal-open');
                var bd = document.getElementById('ts-mgrtag-fallback-backdrop');
                if (bd) bd.remove();
            }
            function showModal() {
                var m = getModalEl();
                if (!m) {
                    console.error('[Role Tags] taskSummaryMgrTagsModal element not found. Did the partial load?');
                    return;
                }
                var bs = getBsModal();
                if (bs) bs.show(); else fallbackShow(m);
            }

            function escapeHtml(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }
            function showError(msg) {
                var n = el('ts-mgrtag-error');
                if (!n) return;
                n.textContent = msg || 'Something went wrong.';
                n.classList.remove('d-none');
            }
            function clearError() {
                var n = el('ts-mgrtag-error');
                if (n) { n.classList.add('d-none'); n.textContent = ''; }
            }

            function renderCount() {
                var c = el('ts-mgrtag-modal-count');
                if (c) {
                    var n = state.juniors.length;
                    c.textContent = n > 0
                        ? '· ' + n + ' tagged'
                        : '';
                }
                // Sync the row dot's tooltip / data attribute too.
                if (state.userId) {
                    var dot = document.querySelector('.task-summary-role-mgr-tags-btn[data-user-id="' + state.userId + '"]');
                    if (dot) {
                        var n2 = state.juniors.length;
                        dot.setAttribute('data-junior-count', String(n2));
                        dot.setAttribute('title', n2 > 0
                            ? 'Tags — ' + n2 + ' junior' + (n2 === 1 ? '' : 's') + ' assigned'
                            : 'Tags — assign juniors this manager is responsible for');
                    }
                }
            }

            function renderChips() {
                var wrap = el('ts-mgrtag-chips');
                if (!wrap) return;
                wrap.innerHTML = '';
                state.juniors.forEach(function (j) {
                    var chip = document.createElement('span');
                    chip.className = 'mgrtag-chip';
                    chip.setAttribute('data-junior-id', j.id);
                    chip.innerHTML =
                        '<i class="ri-user-line" aria-hidden="true"></i>'
                        + '<span>' + escapeHtml(j.name) + '</span>'
                        + (j.designation ? '<span class="mgrtag-chip-des">· ' + escapeHtml(j.designation) + '</span>' : '')
                        + '<button type="button" class="mgrtag-chip-remove" data-action="remove" aria-label="Remove tag for ' + escapeHtml(j.name) + '">&times;</button>';
                    wrap.appendChild(chip);
                });
            }

            function renderEligibleSelect() {
                var sel = el('ts-mgrtag-add-select');
                if (!sel) return;
                sel.innerHTML = '<option value="">Select a team member…</option>';
                state.eligible.forEach(function (u) {
                    var opt = document.createElement('option');
                    opt.value = String(u.id);
                    opt.textContent = u.name + (u.designation ? ' — ' + u.designation : '');
                    sel.appendChild(opt);
                });
            }

            function postJson(url, body, method) {
                return fetch(url, {
                    method: method || 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: body ? JSON.stringify(body) : null
                }).then(function (r) {
                    return r.json().then(function (data) {
                        if (!r.ok || data.success === false) {
                            var msg = (data && data.message) ? data.message : 'Request failed (HTTP ' + r.status + ')';
                            throw new Error(msg);
                        }
                        return data;
                    });
                });
            }

            function loadTags() {
                clearError();
                var loading = el('ts-mgrtag-loading');
                var content = el('ts-mgrtag-content');
                if (loading) loading.classList.remove('d-none');
                if (content) content.classList.add('d-none');

                var url = endpoints.tagsGet + (endpoints.tagsGet.indexOf('?') >= 0 ? '&' : '?')
                    + 'user_id=' + encodeURIComponent(state.userId);
                fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) {
                        if (!r.ok) throw new Error('Could not load tags (HTTP ' + r.status + ')');
                        return r.json();
                    })
                    .then(function (data) {
                        if (loading) loading.classList.add('d-none');
                        if (content) content.classList.remove('d-none');
                        state.juniors = Array.isArray(data.juniors) ? data.juniors : [];
                        state.eligible = Array.isArray(data.eligible) ? data.eligible : [];
                        renderChips();
                        renderEligibleSelect();
                        renderCount();
                    })
                    .catch(function (err) {
                        if (loading) loading.classList.add('d-none');
                        if (content) content.classList.remove('d-none');
                        showError(err && err.message ? err.message : 'Failed to load tags.');
                    });
            }

            function addTag() {
                var sel = el('ts-mgrtag-add-select');
                var btn = el('ts-mgrtag-add-btn');
                if (!sel || !sel.value) {
                    showError('Select a team member first.');
                    return;
                }
                if (!state.userId) {
                    showError('Manager user is missing.');
                    return;
                }
                var jid = parseInt(sel.value, 10);
                clearError();
                if (btn) btn.disabled = true;
                postJson(endpoints.juniorsAdd, {
                    manager_user_id: state.userId,
                    junior_user_id: jid
                })
                    .then(function (data) {
                        if (btn) btn.disabled = false;
                        var j = data.junior || {};
                        state.juniors.push({
                            id: j.id,
                            name: j.name,
                            email: j.email,
                            designation: j.designation,
                            avatar: j.avatar
                        });
                        state.eligible = state.eligible.filter(function (u) { return parseInt(u.id, 10) !== jid; });
                        renderChips();
                        renderEligibleSelect();
                        renderCount();
                    })
                    .catch(function (err) {
                        if (btn) btn.disabled = false;
                        showError(err.message || 'Could not add tag.');
                    });
            }

            function removeTag(juniorId) {
                if (!window.confirm('Remove this tag? The junior will no longer be linked to this manager (also affects CL Mgr score).')) return;
                clearError();
                postJson(endpoints.juniorsRemove, {
                    manager_user_id: state.userId,
                    junior_user_id: parseInt(juniorId, 10)
                }, 'DELETE')
                    .then(function () {
                        var removed = state.juniors.find(function (j) { return String(j.id) === String(juniorId); });
                        state.juniors = state.juniors.filter(function (j) { return String(j.id) !== String(juniorId); });
                        if (removed) {
                            state.eligible.push({
                                id: removed.id,
                                name: removed.name,
                                email: removed.email,
                                designation: removed.designation
                            });
                            state.eligible.sort(function (a, b) { return String(a.name).localeCompare(String(b.name)); });
                        }
                        renderChips();
                        renderEligibleSelect();
                        renderCount();
                    })
                    .catch(function (err) {
                        showError(err.message || 'Could not remove tag.');
                    });
            }

            function saveOrgLevel(selectEl) {
                var userId = parseInt(selectEl.getAttribute('data-user-id'), 10);
                if (!userId) return;
                var value = selectEl.value || null;
                var prevValue = selectEl.getAttribute('data-prev-value') || '';
                selectEl.disabled = true;
                postJson(endpoints.updateLevel, { user_id: userId, org_level: value })
                    .then(function (data) {
                        selectEl.disabled = false;
                        selectEl.setAttribute('data-prev-value', value || '');
                        // Update the row's data-sort attribute so sorting reflects the new value immediately.
                        var tr = selectEl.closest('tr');
                        if (tr) tr.setAttribute('data-sort-org_level', value || '');
                        // Show / hide the Tags dot in this row (Mgr and Director both get the dot).
                        var dot = tr ? tr.querySelector('.task-summary-role-mgr-tags-btn') : null;
                        if (dot) dot.style.display = (value === 'mgr' || value === 'director') ? '' : 'none';
                        // Re-render the hierarchy view if it's currently active so the new role takes effect.
                        if (window.taskSummaryHierarchy && typeof window.taskSummaryHierarchy.rebuildIfActive === 'function') {
                            window.taskSummaryHierarchy.rebuildIfActive();
                        }
                    })
                    .catch(function (err) {
                        selectEl.disabled = false;
                        // Revert UI on failure.
                        selectEl.value = prevValue;
                        alert('Could not save role: ' + (err.message || 'unknown error'));
                    });
            }

            // Capture initial dropdown values so we can revert on failed save.
            document.querySelectorAll('.task-summary-role-select').forEach(function (s) {
                s.setAttribute('data-prev-value', s.value || '');
            });

            // (User Dashboard IIFE is registered globally below.)

            // Save on change (delegated so re-rendered rows work too).
            document.addEventListener('change', function (e) {
                var t = e.target;
                if (!t || !t.classList || !t.classList.contains('task-summary-role-select')) return;
                saveOrgLevel(t);
            });

            // Open tags modal on dot click; chip remove + add inside modal.
            // (Skips silently if the dot doesn't exist for the current viewer.)
            document.addEventListener('click', function (e) {
                var target = e.target;
                if (!target || !target.closest) return;

                var dot = target.closest('.task-summary-role-mgr-tags-btn');
                if (dot) {
                    e.preventDefault();
                    state.userId = parseInt(dot.getAttribute('data-user-id'), 10) || null;
                    state.userName = dot.getAttribute('data-user-name') || '';
                    state.designation = (dot.getAttribute('data-designation') || '').trim();
                    state.juniors = [];
                    state.eligible = [];
                    var userLabel = el('ts-mgrtag-modal-user');
                    if (userLabel) {
                        userLabel.textContent = state.userName
                            ? (state.userName + ' — Tags')
                            : 'Manager — Tags';
                    }
                    var desEl = el('ts-mgrtag-modal-designation');
                    if (desEl) {
                        desEl.innerHTML = state.designation
                            ? '<i class="ri-briefcase-line me-1"></i>' + escapeHtml(state.designation)
                            : '';
                    }
                    var countEl = el('ts-mgrtag-modal-count');
                    if (countEl) countEl.textContent = '';
                    renderChips();
                    renderEligibleSelect();
                    clearError();
                    showModal();
                    loadTags();
                    return;
                }

                if (target.closest('#ts-mgrtag-add-btn')) {
                    e.preventDefault();
                    addTag();
                    return;
                }
                var rem = target.closest('#ts-mgrtag-chips button[data-action="remove"]');
                if (rem) {
                    e.preventDefault();
                    var chip = rem.closest('.mgrtag-chip');
                    if (chip) removeTag(chip.getAttribute('data-junior-id'));
                    return;
                }
            });
        })();

        // -------------------------------------------------------------------
        // User Dashboard modal (KPI column magnifying glass)
        // -------------------------------------------------------------------
        (function () {
            var endpoint = @json(route('tasks.userDashboard.get'));

            function el(id) { return document.getElementById(id); }
            function getModalEl() { return document.getElementById('taskSummaryUserDashboardModal'); }
            function getBsModal() {
                var m = getModalEl();
                if (!m) return null;
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    return bootstrap.Modal.getOrCreateInstance(m);
                }
                return null;
            }
            function fallbackShow(modalEl) {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                modalEl.removeAttribute('aria-hidden');
                modalEl.setAttribute('aria-modal', 'true');
                document.body.classList.add('modal-open');
                if (!document.getElementById('ts-udash-fallback-backdrop')) {
                    var bd = document.createElement('div');
                    bd.id = 'ts-udash-fallback-backdrop';
                    bd.className = 'modal-backdrop fade show';
                    document.body.appendChild(bd);
                    bd.addEventListener('click', function () { fallbackHide(modalEl); });
                }
                modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (b) {
                    b.addEventListener('click', function () { fallbackHide(modalEl); }, { once: true });
                });
            }
            function fallbackHide(modalEl) {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                document.body.classList.remove('modal-open');
                var bd = document.getElementById('ts-udash-fallback-backdrop');
                if (bd) bd.remove();
            }
            function showModal() {
                var m = getModalEl();
                if (!m) {
                    console.error('[UserDashboard] taskSummaryUserDashboardModal not found in DOM.');
                    return;
                }
                var bs = getBsModal();
                if (bs) bs.show(); else fallbackShow(m);
            }

            function escapeHtml(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            function rolePill(level) {
                var l = (level || '').toLowerCase();
                if (l === 'mgr') return { cls: 'is-mgr', text: 'Manager' };
                if (l === 'director') return { cls: 'is-director', text: 'Director' };
                if (l === 'exec') return { cls: 'is-exec', text: 'Executive' };
                return { cls: 'is-none', text: 'No role' };
            }

            function renderMetricTile(label, value, modCls) {
                return '<div class="ts-udash-tile ' + (modCls || '') + '">'
                    + '<div class="val">' + escapeHtml(value) + '</div>'
                    + '<div class="lbl">' + escapeHtml(label) + '</div>'
                    + '</div>';
            }

            function renderMetricsGrid(m) {
                var l30 = m.l30_hrs ? Math.round(m.l30_hrs) + 'h' : '—';
                var tat = (m.tat_l30_days === null || m.tat_l30_days === undefined)
                    ? '—'
                    : (Number(m.tat_l30_days).toFixed(1) + 'd');
                var html = ''
                    + renderMetricTile('Task', m.task || 0)
                    + renderMetricTile('ATT', l30)
                    + renderMetricTile('Task-G', m.assignor_task || 0)
                    + renderMetricTile('Done', m.done || 0, 'is-done')
                    + renderMetricTile('O-Due', m.overdue || 0, 'is-overdue')
                    + renderMetricTile('TAT', tat)
                    + renderMetricTile('Miss', m.missed_l30 || 0, 'is-overdue')
                    + renderMetricTile('A Task', m.a_task || 0)
                    + renderMetricTile('Appr', m.need_approval || 0);
                return html;
            }

            function renderScoreCard(modCls, label, pct) {
                pct = parseInt(pct, 10) || 0;
                return '<div class="ts-udash-score-card ' + modCls + '">'
                    + '<div class="lbl">' + escapeHtml(label) + '</div>'
                    + '<div class="pct">' + pct + '%</div>'
                    + '<div class="bar"><div class="fill" style="width:' + pct + '%;"></div></div>'
                    + '</div>';
            }

            function renderScoresGrid(scores, mgr) {
                var html = ''
                    + renderScoreCard('is-rr', 'R&R', scores.rr_percent || 0)
                    + renderScoreCard('is-clrr', 'CL R&R', scores.clrr_percent || 0)
                    + renderScoreCard('is-clmgr', mgr && mgr.has_mgr_checklist ? 'CL Mgr (Combined)' : 'CL Mgr',
                        mgr ? (mgr.combined_percent || 0) : 0)
                    + renderScoreCard('is-clgen', 'CL Gen', scores.clgen_percent || 0);
                return html;
            }

            function renderJunior(j) {
                var m = j.metrics || {};
                var s = j.scores || {};
                return '<div class="ts-udash-junior-card">'
                    + '<img class="ts-udash-junior-avatar" src="' + escapeHtml(j.avatar || '') + '" alt="" loading="lazy" />'
                    + '<div class="min-w-0">'
                    +   '<div class="ts-udash-junior-name">' + escapeHtml(j.name || '—') + '</div>'
                    +   '<div class="ts-udash-junior-des">' + escapeHtml(j.designation || '') + '</div>'
                    + '</div>'
                    + '<div class="ts-udash-junior-pills">'
                    +   '<span class="pill task" title="Tasks">T ' + (m.task || 0) + '</span>'
                    +   '<span class="pill done" title="Done">D ' + (m.done || 0) + '</span>'
                    +   '<span class="pill overdue" title="Overdue">O ' + (m.overdue || 0) + '</span>'
                    +   '<span class="pill score" title="(CL R&R + CL Gen) / 2">Score ' + (s.blend_percent || 0) + '%</span>'
                    + '</div>'
                    + '</div>';
            }

            function applyPayload(data) {
                var u = data.user || {};

                // Header.
                var av = el('ts-udash-user-avatar'); if (av) av.setAttribute('src', u.avatar || '');
                var nm = el('ts-udash-user-name'); if (nm) nm.textContent = u.name || '—';
                var de = el('ts-udash-user-des'); if (de) de.textContent = u.designation || '';
                var pill = rolePill(u.org_level);
                var rp = el('ts-udash-user-role');
                if (rp) {
                    rp.className = 'ts-udash-role-pill ' + pill.cls;
                    rp.textContent = pill.text;
                }

                // Self metrics.
                var mWrap = el('ts-udash-self-metrics');
                if (mWrap) mWrap.innerHTML = renderMetricsGrid(u.metrics || {});

                // Self scores.
                var sWrap = el('ts-udash-self-scores');
                if (sWrap) sWrap.innerHTML = renderScoresGrid(u.scores || {}, data.mgr || null);

                // Juniors.
                var jWrap = el('ts-udash-juniors-list');
                var jEmpty = el('ts-udash-juniors-empty');
                var jCount = el('ts-udash-juniors-count');
                var juniors = Array.isArray(data.juniors) ? data.juniors : [];
                if (jCount) jCount.textContent = String(juniors.length);
                if (jWrap) jWrap.innerHTML = juniors.map(renderJunior).join('');
                if (jEmpty) jEmpty.classList.toggle('d-none', juniors.length > 0);
            }

            function open(userId, userName) {
                if (!userId) {
                    console.warn('[UserDashboard] no user id for', userName);
                    return;
                }
                var loading = el('ts-udash-loading');
                var content = el('ts-udash-content');
                var errEl = el('ts-udash-error');
                if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                if (loading) loading.classList.remove('d-none');
                if (content) content.classList.add('d-none');

                // Pre-fill header from the table row so the modal isn't blank
                // while the AJAX is in flight.
                var nameEl = el('ts-udash-user-name');
                if (nameEl) nameEl.textContent = userName || 'Loading…';
                showModal();

                var url = endpoint + (endpoint.indexOf('?') >= 0 ? '&' : '?') + 'user_id=' + encodeURIComponent(userId);
                fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) {
                        return r.json().then(function (data) {
                            if (!r.ok || data.success === false) {
                                var msg = (data && data.message) ? data.message : 'Request failed (HTTP ' + r.status + ')';
                                throw new Error(msg);
                            }
                            return data;
                        });
                    })
                    .then(function (data) {
                        if (loading) loading.classList.add('d-none');
                        if (content) content.classList.remove('d-none');
                        applyPayload(data);
                    })
                    .catch(function (err) {
                        if (loading) loading.classList.add('d-none');
                        if (content) content.classList.remove('d-none');
                        if (errEl) {
                            errEl.textContent = err && err.message ? err.message : 'Failed to load dashboard.';
                            errEl.classList.remove('d-none');
                        }
                    });
            }

            window.taskSummaryUserDashboard = { open: open };
        })();

        // -------------------------------------------------------------------
        // Lifetime score history modal (dot next to each CL column score)
        // -------------------------------------------------------------------
        (function () {
            var endpoint = @json(route('tasks.userScoreHistory.get'));
            var chartInstance = null;

            function el(id) { return document.getElementById(id); }
            function getModalEl() { return document.getElementById('taskSummaryScoreHistoryModal'); }
            function getBsModal() {
                var m = getModalEl();
                if (!m) return null;
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    return bootstrap.Modal.getOrCreateInstance(m);
                }
                return null;
            }
            function fallbackShow(modalEl) {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                modalEl.removeAttribute('aria-hidden');
                modalEl.setAttribute('aria-modal', 'true');
                document.body.classList.add('modal-open');
                if (!document.getElementById('ts-schist-fallback-backdrop')) {
                    var bd = document.createElement('div');
                    bd.id = 'ts-schist-fallback-backdrop';
                    bd.className = 'modal-backdrop fade show';
                    document.body.appendChild(bd);
                    bd.addEventListener('click', function () { fallbackHide(modalEl); });
                }
                modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (b) {
                    b.addEventListener('click', function () { fallbackHide(modalEl); }, { once: true });
                });
            }
            function fallbackHide(modalEl) {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                document.body.classList.remove('modal-open');
                var bd = document.getElementById('ts-schist-fallback-backdrop');
                if (bd) bd.remove();
            }
            function showModal() {
                var m = getModalEl();
                if (!m) {
                    console.error('[ScoreHistory] taskSummaryScoreHistoryModal not found');
                    return;
                }
                var bs = getBsModal();
                if (bs) bs.show(); else fallbackShow(m);
            }

            function destroyChart() {
                if (chartInstance) {
                    try { chartInstance.destroy(); } catch (e) {}
                    chartInstance = null;
                }
                var mount = el('ts-schist-chart');
                if (mount) mount.innerHTML = '';
            }

            var TYPE_META = {
                clrr:  { label: 'CL R&R',  color: '#0e7490' },
                clmgr: { label: 'CL Mgr',  color: '#1d4ed8' },
                clgen: { label: 'CL Gen',  color: '#b45309' }
            };

            function renderChart(points, color) {
                if (typeof ApexCharts === 'undefined') {
                    var empty = el('ts-schist-empty');
                    if (empty) {
                        empty.classList.remove('d-none');
                        empty.querySelector('.fw-semibold').textContent = 'Chart library not loaded';
                    }
                    return;
                }
                destroyChart();
                var mount = el('ts-schist-chart');
                if (!mount) return;

                var series = [{
                    name: 'Score %',
                    data: points.map(function (p) {
                        return { x: new Date(p.t).getTime(), y: parseInt(p.p, 10) || 0 };
                    })
                }];

                var options = {
                    chart: {
                        type: 'line',
                        height: 340,
                        toolbar: { show: true },
                        animations: { enabled: true, easing: 'easeinout', speed: 500 },
                        fontFamily: 'inherit'
                    },
                    series: series,
                    stroke: { curve: 'smooth', width: 3, colors: [color] },
                    markers: {
                        size: 4,
                        colors: [color],
                        strokeColors: '#fff',
                        strokeWidth: 2,
                        hover: { size: 6 }
                    },
                    colors: [color],
                    xaxis: {
                        type: 'datetime',
                        labels: { style: { fontSize: '11px' }, datetimeUTC: false }
                    },
                    yaxis: {
                        min: 0,
                        max: 100,
                        tickAmount: 5,
                        labels: { formatter: function (v) { return Math.round(v) + '%'; }, style: { fontSize: '12px' } }
                    },
                    grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
                    dataLabels: { enabled: false },
                    tooltip: { theme: 'light', x: { format: 'dd MMM yyyy HH:mm' } }
                };

                chartInstance = new ApexCharts(mount, options);
                chartInstance.render();
            }

            function open(opts) {
                opts = opts || {};
                var userId = parseInt(opts.userId, 10);
                var type = String(opts.type || '').toLowerCase();
                if (!userId || !TYPE_META[type]) return;

                var loading = el('ts-schist-loading');
                var content = el('ts-schist-content');
                var emptyEl = el('ts-schist-empty');
                var errEl = el('ts-schist-error');
                if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                if (loading) loading.classList.remove('d-none');
                if (content) content.classList.add('d-none');
                if (emptyEl) emptyEl.classList.add('d-none');
                destroyChart();

                // Pre-fill header.
                var nameEl = el('ts-schist-user');
                if (nameEl) nameEl.textContent = (opts.userName || 'User') + ' — score history';
                var typeEl = el('ts-schist-type');
                if (typeEl) typeEl.textContent = TYPE_META[type].label;
                var desEl = el('ts-schist-designation');
                if (desEl) desEl.textContent = opts.designation || '';
                var curEl = el('ts-schist-current');
                if (curEl) curEl.textContent = '…';

                showModal();

                var url = endpoint + (endpoint.indexOf('?') >= 0 ? '&' : '?')
                    + 'user_id=' + encodeURIComponent(userId)
                    + '&score_type=' + encodeURIComponent(type);

                fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) {
                        return r.json().then(function (data) {
                            if (!r.ok || data.success === false) {
                                var msg = (data && data.message) ? data.message : 'Request failed (HTTP ' + r.status + ')';
                                throw new Error(msg);
                            }
                            return data;
                        });
                    })
                    .then(function (data) {
                        if (loading) loading.classList.add('d-none');
                        if (curEl) curEl.textContent = (data.current || 0) + '%';
                        var pts = Array.isArray(data.points) ? data.points.filter(function (p) { return p.t; }) : [];
                        if (pts.length < 2) {
                            // We always include a "now" point — so empty == truly empty
                            // OR a single snapshot. Show empty state for the truly empty case.
                            if (pts.length === 1) {
                                if (content) content.classList.remove('d-none');
                                renderChart(pts.concat(pts), TYPE_META[type].color);
                                return;
                            }
                            if (emptyEl) emptyEl.classList.remove('d-none');
                            return;
                        }
                        if (content) content.classList.remove('d-none');
                        renderChart(pts, TYPE_META[type].color);
                    })
                    .catch(function (err) {
                        if (loading) loading.classList.add('d-none');
                        if (errEl) {
                            errEl.textContent = err && err.message ? err.message : 'Failed to load history.';
                            errEl.classList.remove('d-none');
                        }
                    });
            }

            // Open on history-dot click.
            document.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest && e.target.closest('.task-summary-score-history-btn');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                open({
                    userId: parseInt(btn.getAttribute('data-user-id'), 10),
                    userName: btn.getAttribute('data-user-name') || '',
                    designation: btn.getAttribute('data-designation') || '',
                    type: btn.getAttribute('data-score-type') || 'clrr'
                });
            });

            // Clean up the chart on modal close to avoid leaks.
            var modalEl = getModalEl();
            if (modalEl) {
                modalEl.addEventListener('hidden.bs.modal', destroyChart);
            }
        })();

        // -------------------------------------------------------------------
        // Team Member Profile modal (magnifier in the Team Member column)
        // -------------------------------------------------------------------
        (function () {
            var endpoint = @json(route('tasks.userDashboard.get'));

            function el(id) { return document.getElementById(id); }
            function getModalEl() { return document.getElementById('taskSummaryTmProfileModal'); }
            function getBsModal() {
                var m = getModalEl();
                if (!m) return null;
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    return bootstrap.Modal.getOrCreateInstance(m);
                }
                return null;
            }
            function fallbackShow(modalEl) {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                modalEl.removeAttribute('aria-hidden');
                modalEl.setAttribute('aria-modal', 'true');
                document.body.classList.add('modal-open');
                if (!document.getElementById('ts-tm-fallback-backdrop')) {
                    var bd = document.createElement('div');
                    bd.id = 'ts-tm-fallback-backdrop';
                    bd.className = 'modal-backdrop fade show';
                    document.body.appendChild(bd);
                    bd.addEventListener('click', function () { fallbackHide(modalEl); });
                }
                modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (b) {
                    b.addEventListener('click', function () { fallbackHide(modalEl); }, { once: true });
                });
            }
            function fallbackHide(modalEl) {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                document.body.classList.remove('modal-open');
                var bd = document.getElementById('ts-tm-fallback-backdrop');
                if (bd) bd.remove();
            }
            function showModal() {
                var m = getModalEl();
                if (!m) {
                    console.error('[TM Profile] taskSummaryTmProfileModal not found');
                    return;
                }
                var bs = getBsModal();
                if (bs) bs.show(); else fallbackShow(m);
            }

            function escapeHtml(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            function rolePill(level) {
                var l = (level || '').toLowerCase();
                if (l === 'mgr') return { cls: 'is-mgr', text: 'Manager' };
                if (l === 'director') return { cls: 'is-director', text: 'Director' };
                if (l === 'exec') return { cls: 'is-exec', text: 'Executive' };
                return { cls: 'is-none', text: 'No role' };
            }

            function renderScoreCard(modCls, label, pct) {
                pct = parseInt(pct, 10) || 0;
                return '<div class="tm-score-card ' + modCls + '">'
                    + '<div class="lbl">' + escapeHtml(label) + '</div>'
                    + '<div class="pct">' + pct + '%</div>'
                    + '<div class="bar"><div class="fill" style="width:' + pct + '%;"></div></div>'
                    + '</div>';
            }
            function renderScores(scores, mgr) {
                return ''
                    + renderScoreCard('is-rr', 'R&R', (scores && scores.rr_percent) || 0)
                    + renderScoreCard('is-clrr', 'CL R&R', (scores && scores.clrr_percent) || 0)
                    + renderScoreCard('is-clmgr',
                        mgr && mgr.has_mgr_checklist ? 'CL Mgr (Combined)' : 'CL Mgr',
                        mgr ? (mgr.combined_percent || 0) : 0)
                    + renderScoreCard('is-clgen', 'CL Gen', (scores && scores.clgen_percent) || 0);
            }

            function renderTile(label, value, modCls) {
                return '<div class="tm-tile ' + (modCls || '') + '">'
                    + '<div class="val">' + escapeHtml(value) + '</div>'
                    + '<div class="lbl">' + escapeHtml(label) + '</div>'
                    + '</div>';
            }
            function renderMetrics(m) {
                m = m || {};
                var l30 = m.l30_hrs ? Math.round(m.l30_hrs) + 'h' : '—';
                var tat = (m.tat_l30_days === null || m.tat_l30_days === undefined) ? '—' : (Number(m.tat_l30_days).toFixed(1) + 'd');
                return ''
                    + renderTile('Task', m.task || 0)
                    + renderTile('ATT', l30)
                    + renderTile('Task-G', m.assignor_task || 0)
                    + renderTile('Done', m.done || 0, 'is-done')
                    + renderTile('O-Due', m.overdue || 0, 'is-overdue')
                    + renderTile('TAT', tat)
                    + renderTile('Miss', m.missed_l30 || 0, 'is-overdue')
                    + renderTile('Appr', m.need_approval || 0);
            }

            function renderPersonRow(p) {
                var m = p.metrics || {};
                var s = p.scores || {};
                return '<div class="tm-people-row">'
                    + '<img class="tm-people-avatar" src="' + escapeHtml(p.avatar || '') + '" alt="" loading="lazy" />'
                    + '<div class="min-w-0">'
                    +   '<div class="tm-people-name">' + escapeHtml(p.name || '—') + '</div>'
                    +   '<div class="tm-people-meta">' + escapeHtml(p.designation || '') + '</div>'
                    + '</div>'
                    + '<div class="tm-people-pills">'
                    +   '<span class="pill t" title="Tasks">T ' + (m.task || 0) + '</span>'
                    +   '<span class="pill d" title="Done">D ' + (m.done || 0) + '</span>'
                    +   '<span class="pill o" title="Overdue">O ' + (m.overdue || 0) + '</span>'
                    +   '<span class="pill s" title="(CL R&R + CL Gen) / 2">Score ' + (s.blend_percent || 0) + '%</span>'
                    + '</div>'
                    + '</div>';
            }

            function applyPayload(data) {
                var u = data.user || {};
                var profile = data.profile || {};

                // Hero
                var av = el('ts-tm-avatar'); if (av) av.setAttribute('src', u.avatar || '');
                var nm = el('ts-tm-name'); if (nm) nm.textContent = u.name || '—';
                var de = el('ts-tm-des'); if (de) de.textContent = u.designation || '—';
                var pill = rolePill(u.org_level);
                var rp = el('ts-tm-role');
                if (rp) {
                    rp.className = 'tm-role-pill ' + pill.cls;
                    rp.textContent = pill.text;
                }
                // Tenure / department extras (only shown if available).
                var extraBits = [];
                if (profile.tenure_label) extraBits.push('with the company for ' + profile.tenure_label);
                if (profile.resource_department) extraBits.push('Dept: ' + profile.resource_department);
                var extra = el('ts-tm-extra');
                if (extra) extra.textContent = extraBits.length ? ' · ' + extraBits.join(' · ') : '';

                // Contact list
                var contact = el('ts-tm-contact');
                if (contact) {
                    contact.innerHTML = '';
                    if (u.email) {
                        contact.innerHTML += '<a href="mailto:' + escapeHtml(u.email) + '"><i class="ri-mail-line"></i>' + escapeHtml(u.email) + '</a>';
                    }
                    if (profile.phone) {
                        contact.innerHTML += '<a href="tel:' + escapeHtml(profile.phone) + '"><i class="ri-phone-line"></i>' + escapeHtml(profile.phone) + '</a>';
                    }
                    if (profile.date_of_joining) {
                        contact.innerHTML += '<span><i class="ri-calendar-event-line"></i>Joined ' + escapeHtml(profile.date_of_joining) + '</span>';
                    }
                }

                // Scores band
                var scoresWrap = el('ts-tm-scores');
                if (scoresWrap) scoresWrap.innerHTML = renderScores(u.scores || {}, data.mgr || null);

                // Metric tiles
                var metricsWrap = el('ts-tm-metrics');
                if (metricsWrap) metricsWrap.innerHTML = renderMetrics(u.metrics || {});

                // Managers (people this user reports to)
                var managers = Array.isArray(data.managers) ? data.managers : [];
                var mgrsWrap = el('ts-tm-managers-wrap');
                var mgrsList = el('ts-tm-managers');
                var mgrsCount = el('ts-tm-managers-count');
                if (managers.length > 0 && mgrsWrap && mgrsList) {
                    mgrsWrap.classList.remove('d-none');
                    mgrsList.innerHTML = managers.map(renderPersonRow).join('');
                    if (mgrsCount) mgrsCount.textContent = String(managers.length);
                } else if (mgrsWrap) {
                    mgrsWrap.classList.add('d-none');
                }

                // Juniors — only shown for Mgr / Director (or when juniors exist anyway).
                var level = (u.org_level || '').toLowerCase();
                var juniors = Array.isArray(data.juniors) ? data.juniors : [];
                var showJuniors = level === 'mgr' || level === 'director' || juniors.length > 0;

                var junWrap = el('ts-tm-juniors-wrap');
                var junList = el('ts-tm-juniors');
                var junEmpty = el('ts-tm-juniors-empty');
                var junCount = el('ts-tm-juniors-count');

                if (!showJuniors) {
                    if (junWrap) junWrap.classList.add('d-none');
                } else if (junWrap && junList) {
                    junWrap.classList.remove('d-none');
                    if (junCount) junCount.textContent = String(juniors.length);
                    if (juniors.length === 0) {
                        junList.innerHTML = '';
                        if (junEmpty) junEmpty.classList.remove('d-none');
                    } else {
                        if (junEmpty) junEmpty.classList.add('d-none');
                        junList.innerHTML = juniors.map(renderPersonRow).join('');
                    }
                }
            }

            function open(userId, userName) {
                if (!userId) return;
                var loading = el('ts-tm-loading');
                var content = el('ts-tm-content');
                var errEl = el('ts-tm-error');
                if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                if (loading) loading.classList.remove('d-none');
                if (content) content.classList.add('d-none');

                // Pre-fill the hero so the modal isn't blank during AJAX.
                var nameEl = el('ts-tm-name');
                if (nameEl) nameEl.textContent = userName || 'Loading…';

                showModal();

                var url = endpoint + (endpoint.indexOf('?') >= 0 ? '&' : '?') + 'user_id=' + encodeURIComponent(userId);
                fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) {
                        return r.json().then(function (data) {
                            if (!r.ok || data.success === false) {
                                var msg = (data && data.message) ? data.message : 'Request failed (HTTP ' + r.status + ')';
                                throw new Error(msg);
                            }
                            return data;
                        });
                    })
                    .then(function (data) {
                        if (loading) loading.classList.add('d-none');
                        if (content) content.classList.remove('d-none');
                        applyPayload(data);
                    })
                    .catch(function (err) {
                        if (loading) loading.classList.add('d-none');
                        if (content) content.classList.remove('d-none');
                        if (errEl) {
                            errEl.textContent = err && err.message ? err.message : 'Failed to load profile.';
                            errEl.classList.remove('d-none');
                        }
                    });
            }

            // Bind to the magnifier next to the TM badge.
            document.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest && e.target.closest('.task-summary-tm-profile-btn');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                var userId = parseInt(btn.getAttribute('data-user-id'), 10) || null;
                var name = btn.getAttribute('data-user-name') || '';
                open(userId, name);
            });

            window.taskSummaryTmProfile = { open: open };
        })();

        // -------------------------------------------------------------------
        // KPI Badges modal (KPI column magnifier)
        // -------------------------------------------------------------------
        (function () {
            var csrfToken = (function () {
                var meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.getAttribute('content') : '';
            })();

            var endpoints = {
                get: @json(route('tasks.userBadges.get')),
                award: @json(route('tasks.userBadges.award')),
                remove: @json(route('tasks.userBadges.remove')),
                create: @json(route('tasks.badges.create')),
                deleteBase: @json(url('/tasks/badges'))
            };

            var state = {
                userId: null,
                userName: '',
                designation: '',
                canAward: false,
                canManagePool: false,
                awards: [],
                available: []
            };

            function el(id) { return document.getElementById(id); }
            function getModalEl() { return document.getElementById('taskSummaryUserBadgesModal'); }
            function getBsModal() {
                var m = getModalEl();
                if (!m) return null;
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    return bootstrap.Modal.getOrCreateInstance(m);
                }
                return null;
            }
            function fallbackShow(modalEl) {
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                modalEl.removeAttribute('aria-hidden');
                modalEl.setAttribute('aria-modal', 'true');
                document.body.classList.add('modal-open');
                if (!document.getElementById('ts-ub-fallback-backdrop')) {
                    var bd = document.createElement('div');
                    bd.id = 'ts-ub-fallback-backdrop';
                    bd.className = 'modal-backdrop fade show';
                    document.body.appendChild(bd);
                    bd.addEventListener('click', function () { fallbackHide(modalEl); });
                }
                modalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function (b) {
                    b.addEventListener('click', function () { fallbackHide(modalEl); }, { once: true });
                });
            }
            function fallbackHide(modalEl) {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
                document.body.classList.remove('modal-open');
                var bd = document.getElementById('ts-ub-fallback-backdrop');
                if (bd) bd.remove();
            }
            function showModal() {
                var m = getModalEl();
                if (!m) {
                    console.error('[UserBadges] taskSummaryUserBadgesModal not found');
                    return;
                }
                var bs = getBsModal();
                if (bs) bs.show(); else fallbackShow(m);
            }

            function escapeHtml(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }
            function showError(msg) {
                var n = el('ts-ub-error');
                if (!n) return;
                n.textContent = msg || 'Something went wrong.';
                n.classList.remove('d-none');
            }
            function clearError() {
                var n = el('ts-ub-error');
                if (n) { n.classList.add('d-none'); n.textContent = ''; }
            }

            function pickContrastText(/* unused — chips are always white on coloured bg */) {
                return '#fff';
            }

            function renderAwardChip(a) {
                var color = a.color || '#0d9488';
                var label = (a.name || '—');
                var noteTitle = a.note ? (label + ' — ' + a.note + (a.awarded_by ? ' (by ' + a.awarded_by + ')' : '')) : label;
                var html = '<span class="ub-chip" style="background:' + escapeHtml(color) + ';color:' + pickContrastText() + ';" data-award-id="' + a.award_id + '" data-badge-id="' + a.badge_id + '" title="' + escapeHtml(noteTitle) + '">'
                    + '<i class="' + escapeHtml(a.icon || 'ri-medal-line') + '"></i>'
                    + '<span>' + escapeHtml(label) + '</span>';
                if (state.canAward) {
                    html += '<button type="button" class="ub-chip-remove" data-action="remove-award" aria-label="Remove ' + escapeHtml(label) + '">&times;</button>';
                }
                html += '</span>';
                return html;
            }

            function renderPoolChip(b) {
                var color = b.color || '#0d9488';
                var title = b.description ? (b.name + ' — ' + b.description) : b.name;
                var disabled = state.canAward ? '' : 'disabled';
                return '<button type="button" class="ub-pool-chip ' + (state.canAward ? '' : 'is-disabled') + '" '
                    + 'style="border:1.5px solid ' + escapeHtml(color) + ';color:' + escapeHtml(color) + ';" '
                    + 'data-badge-id="' + b.id + '" data-action="award-from-pool" ' + disabled + ' '
                    + 'title="' + escapeHtml(title) + '">'
                    + '<i class="' + escapeHtml(b.icon || 'ri-medal-line') + '"></i>'
                    + '<span>' + escapeHtml(b.name) + '</span>'
                    + '</button>';
            }

            function updateCount() {
                var c = el('ts-ub-modal-count');
                if (c) {
                    var n = state.awards.length;
                    c.textContent = n > 0 ? '· ' + n + ' tagged' : '';
                }
                // Sync row count badge if present.
                var rowBtn = document.querySelector('.task-summary-kpi-badges-btn[data-user-id="' + state.userId + '"]');
                if (rowBtn) {
                    var n2 = state.awards.length;
                    rowBtn.setAttribute('data-badge-count', String(n2));
                }
            }

            function render() {
                var awardedWrap = el('ts-ub-awarded');
                var poolWrap = el('ts-ub-pool');
                var empty = el('ts-ub-empty');
                var poolEmpty = el('ts-ub-pool-empty');
                var createWrap = el('ts-ub-create-wrap');

                if (awardedWrap) {
                    if (state.awards.length === 0) {
                        awardedWrap.innerHTML = '';
                        if (empty) empty.classList.remove('d-none');
                    } else {
                        if (empty) empty.classList.add('d-none');
                        awardedWrap.innerHTML = state.awards.map(renderAwardChip).join('');
                    }
                }
                if (poolWrap) {
                    if (state.available.length === 0) {
                        poolWrap.innerHTML = '';
                        if (poolEmpty) poolEmpty.classList.remove('d-none');
                    } else {
                        if (poolEmpty) poolEmpty.classList.add('d-none');
                        poolWrap.innerHTML = state.available.map(renderPoolChip).join('');
                    }
                }
                if (createWrap) {
                    createWrap.classList.toggle('d-none', !state.canManagePool);
                }
                updateCount();
            }

            function postJson(url, body, method) {
                return fetch(url, {
                    method: method || 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: body ? JSON.stringify(body) : null
                }).then(function (r) {
                    return r.json().then(function (data) {
                        if (!r.ok || data.success === false) {
                            var msg = (data && data.message) ? data.message : 'Request failed (HTTP ' + r.status + ')';
                            throw new Error(msg);
                        }
                        return data;
                    });
                });
            }

            function loadBadges() {
                clearError();
                var loading = el('ts-ub-loading');
                var content = el('ts-ub-content');
                if (loading) loading.classList.remove('d-none');
                if (content) content.classList.add('d-none');
                var url = endpoints.get + (endpoints.get.indexOf('?') >= 0 ? '&' : '?') + 'user_id=' + encodeURIComponent(state.userId);
                fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) {
                        return r.json().then(function (data) {
                            if (!r.ok || data.success === false) {
                                var msg = (data && data.message) ? data.message : 'Could not load badges.';
                                throw new Error(msg);
                            }
                            return data;
                        });
                    })
                    .then(function (data) {
                        if (loading) loading.classList.add('d-none');
                        if (content) content.classList.remove('d-none');
                        state.canAward = !!data.can_award;
                        state.canManagePool = !!data.can_manage_pool;
                        state.awards = Array.isArray(data.awards) ? data.awards : [];
                        state.available = Array.isArray(data.available) ? data.available : [];
                        render();
                    })
                    .catch(function (err) {
                        if (loading) loading.classList.add('d-none');
                        if (content) content.classList.remove('d-none');
                        showError(err && err.message ? err.message : 'Failed to load badges.');
                    });
            }

            function awardBadge(badgeId) {
                if (!state.canAward) return;
                clearError();
                postJson(endpoints.award, {
                    user_id: state.userId,
                    badge_id: parseInt(badgeId, 10)
                })
                    .then(function (data) {
                        if (!data.award) return;
                        state.awards.unshift(data.award);
                        state.available = state.available.filter(function (b) { return parseInt(b.id, 10) !== parseInt(badgeId, 10); });
                        render();
                    })
                    .catch(function (err) {
                        showError(err.message || 'Could not tag badge.');
                    });
            }

            function removeBadge(badgeId) {
                if (!state.canAward) return;
                if (!window.confirm('Remove this badge from the user?')) return;
                clearError();
                postJson(endpoints.remove, {
                    user_id: state.userId,
                    badge_id: parseInt(badgeId, 10)
                }, 'DELETE')
                    .then(function () {
                        var removed = state.awards.find(function (a) { return parseInt(a.badge_id, 10) === parseInt(badgeId, 10); });
                        state.awards = state.awards.filter(function (a) { return parseInt(a.badge_id, 10) !== parseInt(badgeId, 10); });
                        if (removed) {
                            state.available.push({
                                id: removed.badge_id,
                                name: removed.name,
                                icon: removed.icon,
                                color: removed.color,
                                description: removed.description
                            });
                            state.available.sort(function (a, b) {
                                return String(a.name).localeCompare(String(b.name));
                            });
                        }
                        render();
                    })
                    .catch(function (err) {
                        showError(err.message || 'Could not remove badge.');
                    });
            }

            function createBadge() {
                var nameEl = el('ts-ub-create-name');
                var iconEl = el('ts-ub-create-icon');
                var colorEl = el('ts-ub-create-color');
                var btn = el('ts-ub-create-btn');
                var name = (nameEl && nameEl.value || '').trim();
                if (!name) {
                    showError('Enter a badge name first.');
                    return;
                }
                var payload = {
                    name: name,
                    icon: (iconEl && iconEl.value || '').trim() || 'ri-medal-line',
                    color: (colorEl && colorEl.value || '').trim() || '#b45309'
                };
                if (btn) btn.disabled = true;
                clearError();
                postJson(endpoints.create, payload)
                    .then(function (data) {
                        if (btn) btn.disabled = false;
                        if (nameEl) nameEl.value = '';
                        if (iconEl) iconEl.value = '';
                        if (!data.badge) return;
                        state.available.push(data.badge);
                        state.available.sort(function (a, b) {
                            return String(a.name).localeCompare(String(b.name));
                        });
                        render();
                    })
                    .catch(function (err) {
                        if (btn) btn.disabled = false;
                        showError(err.message || 'Could not create badge.');
                    });
            }

            function updateIconPreview() {
                var iconEl = el('ts-ub-create-icon');
                var colorEl = el('ts-ub-create-color');
                var preview = el('ts-ub-create-icon-preview');
                if (!preview) return;
                var i = (iconEl && iconEl.value || '').trim() || 'ri-medal-line';
                var c = (colorEl && colorEl.value || '').trim() || '#b45309';
                preview.style.background = c;
                preview.innerHTML = '<i class="' + escapeHtml(i) + '"></i>';
            }

            // Open from any KPI badges magnifier.
            document.addEventListener('click', function (e) {
                var target = e.target;
                if (!target || !target.closest) return;

                var openBtn = target.closest('.task-summary-kpi-badges-btn');
                if (openBtn) {
                    e.preventDefault();
                    if (openBtn.disabled) return;
                    state.userId = parseInt(openBtn.getAttribute('data-user-id'), 10) || null;
                    state.userName = openBtn.getAttribute('data-user-name') || '';
                    state.designation = (openBtn.getAttribute('data-designation') || '').trim();
                    state.canAward = false;
                    state.canManagePool = false;
                    state.awards = [];
                    state.available = [];
                    var userLabel = el('ts-ub-modal-user');
                    if (userLabel) userLabel.textContent = state.userName ? (state.userName + ' — KPI Badges') : 'KPI Badges';
                    var desEl = el('ts-ub-modal-designation');
                    if (desEl) desEl.innerHTML = state.designation ? ('<i class="ri-briefcase-line me-1"></i>' + escapeHtml(state.designation)) : '';
                    var countEl = el('ts-ub-modal-count');
                    if (countEl) countEl.textContent = '';
                    render();
                    clearError();
                    showModal();
                    loadBadges();
                    return;
                }

                // Award from pool chip
                var poolBtn = target.closest('button[data-action="award-from-pool"]');
                if (poolBtn) {
                    e.preventDefault();
                    if (poolBtn.disabled) return;
                    var bid = poolBtn.getAttribute('data-badge-id');
                    if (bid) awardBadge(bid);
                    return;
                }

                // Remove an awarded chip
                var remBtn = target.closest('button[data-action="remove-award"]');
                if (remBtn) {
                    e.preventDefault();
                    var chip = remBtn.closest('.ub-chip');
                    var rmId = chip ? chip.getAttribute('data-badge-id') : null;
                    if (rmId) removeBadge(rmId);
                    return;
                }

                // Create new badge
                if (target.closest('#ts-ub-create-btn')) {
                    e.preventDefault();
                    createBadge();
                    return;
                }
            });

            // Icon / colour preview live updates.
            document.addEventListener('input', function (e) {
                if (e.target && (e.target.id === 'ts-ub-create-icon' || e.target.id === 'ts-ub-create-color')) {
                    updateIconPreview();
                }
            });
            // Enter on name commits.
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                var t = e.target;
                if (t && (t.id === 'ts-ub-create-name' || t.id === 'ts-ub-create-icon')) {
                    e.preventDefault();
                    createBadge();
                }
            });

            window.taskSummaryUserBadges = {
                open: function (userId, name) {
                    state.userId = parseInt(userId, 10) || null;
                    state.userName = name || '';
                    if (!state.userId) return;
                    var label = el('ts-ub-modal-user');
                    if (label) label.textContent = (state.userName ? state.userName + ' — ' : '') + 'KPI Badges';
                    state.awards = []; state.available = []; render(); clearError();
                    showModal();
                    loadBadges();
                }
            };
        })();

        // Avatar size — controls are hidden; size fixed at 35px regardless
        // of any value previously persisted in localStorage.
        const DEFAULT_AVATAR_SIZE = 35;
        let currentAvatarSize = DEFAULT_AVATAR_SIZE;
        try { localStorage.setItem('avatarSize', DEFAULT_AVATAR_SIZE); } catch (e) {}
        document.documentElement.style.setProperty('--avatar-size', currentAvatarSize + 'px');
        var __avatarSizeDisplay = document.getElementById('avatar-size-display');
        if (__avatarSizeDisplay) __avatarSizeDisplay.textContent = currentAvatarSize + 'px';

        // The +/-/reset functions are still defined in case some external
        // caller invokes them, but the buttons are hidden in the UI.
        function adjustAvatarSize(delta) {
            currentAvatarSize = Math.max(20, Math.min(80, currentAvatarSize + delta));
            document.documentElement.style.setProperty('--avatar-size', currentAvatarSize + 'px');
            var d = document.getElementById('avatar-size-display');
            if (d) d.textContent = currentAvatarSize + 'px';
            try { localStorage.setItem('avatarSize', currentAvatarSize); } catch (e) {}
        }
        function resetAvatarSize() {
            currentAvatarSize = DEFAULT_AVATAR_SIZE;
            document.documentElement.style.setProperty('--avatar-size', currentAvatarSize + 'px');
            var d = document.getElementById('avatar-size-display');
            if (d) d.textContent = currentAvatarSize + 'px';
            try { localStorage.setItem('avatarSize', currentAvatarSize); } catch (e) {}
        }
    </script>
@endsection
