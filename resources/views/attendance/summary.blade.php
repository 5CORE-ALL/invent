@extends('layouts.vertical', ['title' => $title ?? 'Team Monitoring'])

@section('css')
<style>
    .es-card { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
    .es-toolbar {
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-end;
        gap: .5rem;
        width: 100%;
        overflow-x: auto;
        padding-bottom: 2px;
    }
    .es-toolbar > div, .es-toolbar #customRangeFields > div { flex: 0 0 auto; }
    .es-toolbar .form-select, .es-toolbar .form-control { min-height: 34px; font-size: .85rem; width: auto; min-width: 118px; }
    .es-toolbar .es-field-exec .form-select { min-width: 150px; max-width: 180px; }
    .es-toolbar .es-field-range .form-select { min-width: 118px; max-width: 130px; }
    .es-toolbar .es-field-reset .form-select { min-width: 170px; max-width: 210px; }
    .es-toolbar .es-field-tz .form-select { min-width: 140px; max-width: 170px; }
    .es-toolbar input[type=date] { min-width: 138px; }
    .es-toolbar .btn { flex: 0 0 auto; white-space: nowrap; }
    .es-ss { position: relative; min-width: 168px; }
    select.es-ss-native,
    .es-toolbar select.es-ss-native.form-select {
        display: none !important;
    }
    .es-ss-btn {
        min-height: 34px; font-size: .85rem; width: 168px; max-width: 200px;
        text-align: left; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .es-ss-panel {
        position: absolute; left: 0; top: calc(100% + 4px); z-index: 1080;
        background: #1f2937; color: #fff; border: 1px solid #374151; border-radius: 8px;
        box-shadow: 0 12px 32px rgba(0,0,0,.28);
        min-width: 260px; max-width: 320px; padding: .5rem;
        display: none;
    }
    .es-ss.is-open .es-ss-panel,
    .es-ss-panel.is-open { display: block; }
    .es-ss-q {
        margin-bottom: .45rem; background: #111827; border-color: #4b5563; color: #fff;
    }
    .es-ss-q::placeholder { color: #9ca3af; }
    .es-ss-list { max-height: 280px; overflow: auto; }
    .es-ss-item {
        appearance: none; display: block; width: 100%; text-align: left;
        border: 0; background: transparent; border-radius: 6px;
        padding: .4rem .55rem; font-size: .82rem; color: #f9fafb; cursor: pointer;
    }
    .es-ss-item:hover { background: #374151; }
    .es-ss-item.is-on { background: #eab308; color: #111827; font-weight: 700; }
    .es-ss-empty { padding: .5rem; font-size: .78rem; color: #9ca3af; }
    .es-kpi {
        border-radius: 10px; padding: .85rem 1rem; color: #fff; height: 100%;
        display: flex; flex-direction: column; justify-content: center;
        align-items: center; text-align: center;
    }
    .es-kpi .val { font-size: 1.35rem; font-weight: 700; line-height: 1.2; font-variant-numeric: tabular-nums; }
    .es-kpi .lbl { font-size: .72rem; opacity: .92; margin-top: .15rem; }
    .es-kpi.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
    .es-kpi.green { background: linear-gradient(135deg, #22c55e, #16a34a); }
    .es-kpi.orange { background: linear-gradient(135deg, #f97316, #ea580c); }
    .es-kpi.teal { background: linear-gradient(135deg, #14b8a6, #0d9488); }
    .es-kpi.red { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .es-kpi.gray { background: #f8fafc; color: #0f172a; border: 1px solid #e2e8f0; }
    .es-kpi.gray .lbl { color: #64748b; }
    .es-table { font-size: .82rem; margin-bottom: 0; table-layout: fixed; width: 100%; min-width: 1160px; }
    .es-avatar-col { text-align: center; }
    .es-avatar {
        width: 36px; height: 36px; border-radius: 50%; object-fit: cover;
        display: inline-block; vertical-align: middle;
        border: 1px solid #e2e8f0; background: #f1f5f9;
    }
    .es-table thead th {
        font-size: .7rem; text-transform: uppercase; letter-spacing: .03em;
        color: #64748b; font-weight: 600; white-space: nowrap; vertical-align: middle;
        background: #f8fafc;
    }
    .es-table thead th.es-sort {
        cursor: pointer; user-select: none; padding-right: 1.1rem; position: relative;
    }
    .es-table thead th.es-sort:hover { color: #0f172a; background: #f1f5f9; }
    .es-table thead th.es-sort::after {
        content: '↕'; position: absolute; right: .35rem; top: 50%; transform: translateY(-50%);
        font-size: .62rem; color: #cbd5e1; font-weight: 700;
    }
    .es-table thead th.es-sort.is-asc::after { content: '↑'; color: #2563eb; }
    .es-table thead th.es-sort.is-desc::after { content: '↓'; color: #2563eb; }
    .es-table thead th.es-th-idle {
        background: #dc2626;
        color: #fff;
    }
    .es-table thead th.es-th-idle:hover { background: #b91c1c; color: #fff; }
    .es-table thead th.es-th-idle::after { color: rgba(255,255,255,.7); }
    .es-table thead th.es-th-idle.is-asc::after,
    .es-table thead th.es-th-idle.is-desc::after { color: #fff; }
    .es-table tbody td { vertical-align: middle; }
    .es-table tbody tr.es-selectable { cursor: pointer; }
    .es-table tbody tr.es-selected { background: #eff6ff; }
    .es-table tbody tr.es-selected:hover { background: #dbeafe; }
    .es-name { font-weight: 600; color: #0f172a; }
    .es-tm-col,
    .es-ts-col { text-align: center; width: 3.2rem; }
    .es-table thead th.es-tm-col,
    .es-table thead th.es-ts-col {
        background: #e8f1fc;
        color: #0f172a;
        font-weight: 800;
        letter-spacing: 0.02em;
        text-transform: none;
    }
    .task-summary-tm-badge {
        flex-shrink: 0;
        width: 1.4rem;
        height: 1.4rem;
        padding: 0;
        margin: 0;
        border: none;
        border-radius: 7px;
        background: linear-gradient(135deg, #b45309, #f59e0b);
        color: #fff;
        text-decoration: none;
        font-size: 0.6rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        line-height: 1;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        vertical-align: middle;
        box-shadow: 0 1px 3px rgba(245, 158, 11, 0.45), inset 0 -1px 0 rgba(0, 0, 0, 0.08);
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }
    .task-summary-tm-badge:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(245, 158, 11, 0.5), inset 0 -1px 0 rgba(0, 0, 0, 0.08);
        background: linear-gradient(135deg, #92400e, #b45309);
        color: #fff;
        text-decoration: none;
    }
    .task-summary-tm-badge:focus-visible {
        outline: 2px solid #b45309;
        outline-offset: 2px;
    }
    .task-summary-tm-badge.task-summary-tm-badge-director {
        background: linear-gradient(135deg, #4338ca, #6366f1);
        box-shadow: 0 1px 3px rgba(99, 102, 241, 0.45), inset 0 -1px 0 rgba(0, 0, 0, 0.08);
    }
    .task-summary-tm-badge.task-summary-tm-badge-director:hover {
        background: linear-gradient(135deg, #3730a3, #4f46e5);
    }
    .task-summary-tm-badge.task-summary-tm-badge-mgr {
        background: linear-gradient(135deg, #0e7490, #06b6d4);
        box-shadow: 0 1px 3px rgba(6, 182, 212, 0.45), inset 0 -1px 0 rgba(0, 0, 0, 0.08);
    }
    .task-summary-tm-badge.task-summary-tm-badge-mgr:hover {
        background: linear-gradient(135deg, #155e75, #0e7490);
    }
    .task-summary-tm-badge.task-summary-tm-badge-exec {
        background: linear-gradient(135deg, #b45309, #f59e0b);
        box-shadow: 0 1px 3px rgba(245, 158, 11, 0.45), inset 0 -1px 0 rgba(0, 0, 0, 0.08);
    }
    .task-summary-tm-badge.task-summary-tm-badge-exec:hover {
        background: linear-gradient(135deg, #92400e, #b45309);
    }
    .es-time-col { text-align: center; }
    .es-time-btn {
        appearance: none; border: 0; background: #f0fdfa; color: #0d9488;
        width: 32px; height: 32px; border-radius: 8px; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid #ccfbf1; text-decoration: none;
    }
    .es-time-btn:hover { background: #ccfbf1; color: #0f766e; }
    .es-time-btn i { font-size: 1rem; line-height: 1; }
    .es-time-btn .task-magnify-icon {
        width: 18px;
        height: 18px;
        object-fit: contain;
        display: block;
        pointer-events: none;
    }
    .tl-modal-legend { display: flex; flex-wrap: wrap; gap: .75rem; font-size: .72rem; color: #64748b; }
    .tl-modal-legend span { display: inline-flex; align-items: center; gap: .3rem; }
    .tl-modal-legend i { width: 10px; height: 10px; border-radius: 2px; display: inline-block; }
    .tl-modal-axis { display: flex; justify-content: space-between; font-size: .62rem; color: #94a3b8; margin-bottom: 2px; }
    .tl-modal-day { padding: .55rem 0; border-bottom: 1px solid #f1f5f9; }
    .tl-modal-day:last-child { border-bottom: 0; }
    .tl-modal-day-head { display: flex; justify-content: space-between; gap: .75rem; align-items: center; margin-bottom: .2rem; flex-wrap: wrap; }
    .tl-modal-date { font-size: .82rem; font-weight: 600; color: #0f172a; }
    .tl-modal-date .is-today { font-size: .65rem; color: #2563eb; margin-left: .35rem; }
    .tl-modal-clock { font-size: .72rem; color: #64748b; margin-left: .65rem; }
    .tl-modal-stats { display: flex; flex-wrap: wrap; gap: .7rem; font-size: .75rem; }
    .tl-modal-stats strong { font-weight: 700; }
    .tl-modal-track { position: relative; height: 18px; background: #fff; border: 1px solid #e2e8f0; border-radius: 2px; overflow: hidden; }
    .tl-modal-track-grid {
        position: absolute; inset: 0; pointer-events: none;
        background: repeating-linear-gradient(90deg, transparent, transparent calc(16.666% - 1px), rgba(148,163,184,.14) calc(16.666% - 1px), rgba(148,163,184,.14) 16.666%);
    }
    .tl-modal-seg { position: absolute; top: 0; bottom: 0; min-width: 2px; }
    .tl-modal-empty { padding: 2rem; text-align: center; color: #94a3b8; }
    .es-span { font-size: .78rem; color: #475569; white-space: nowrap; }
    .es-span-updated { font-size: .68rem; color: #94a3b8; }
    .es-live-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; display: inline-block; }
    .es-live-col { text-align: center; }
    .es-live-status {
        width: 12px; height: 12px; border-radius: 50%; display: inline-block;
        box-shadow: 0 0 0 3px rgba(0,0,0,.04);
    }
    .es-live-status.working { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.18); }
    .es-live-status.idle { background: #eab308; box-shadow: 0 0 0 3px rgba(234,179,8,.2); }
    .es-live-status.absent { background: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,.18); }
    .es-live-btn {
        appearance: none; border: 0; background: transparent; padding: 8px;
        cursor: pointer; border-radius: 999px; line-height: 0;
    }
    .es-live-btn:hover .es-live-status { transform: scale(1.2); }
    .es-live-btn:hover .es-live-status.working { box-shadow: 0 0 0 5px rgba(34,197,94,.28); }
    .es-live-btn:hover .es-live-status.idle { box-shadow: 0 0 0 5px rgba(234,179,8,.3); }
    .es-live-btn:hover .es-live-status.absent { box-shadow: 0 0 0 5px rgba(239,68,68,.28); }
    .es-shot-col { text-align: center; overflow: visible; }
    .es-shot-row {
        display: inline-flex; align-items: center; justify-content: center; gap: .4rem;
    }
    .es-shot-dot {
        width: 10px; height: 10px; border-radius: 50%;
        background: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,.2);
        flex: 0 0 auto; display: inline-block; text-decoration: none;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .es-shot-dot:hover {
        transform: scale(1.35);
        box-shadow: 0 0 0 5px rgba(13,148,136,.32);
    }
    .es-shot-thumb {
        display: inline-block; width: 72px; height: 44px; border-radius: 6px;
        overflow: hidden; border: 1px solid #e2e8f0; background: #f1f5f9;
        vertical-align: middle;
        transform: scale(0.8);
        transform-origin: center center;
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .es-shot-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .es-shot-thumb:hover {
        transform: scale(2);
        border-color: #94a3b8;
        position: relative;
        z-index: 30;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .2);
    }
    .es-table tbody tr:has(.es-shot-thumb:hover) { position: relative; z-index: 25; }
    .table-responsive:has(.es-shot-thumb:hover) { overflow: visible; }
    .es-shot-time { font-size: .65rem; color: #94a3b8; margin-top: .15rem; }
    .es-pill {
        display: inline-block; min-width: 52px; text-align: center;
        padding: .2rem .55rem; border-radius: 6px; font-weight: 700;
        font-variant-numeric: tabular-nums; font-size: .78rem;
    }
    .es-pill.blue { background: #dbeafe; color: #1d4ed8; }
    .es-pill.teal { background: #ccfbf1; color: #0f766e; }
    .es-pill.red { color: #dc2626; font-weight: 600; }
    .es-pill.blue-text { color: #2563eb; font-weight: 700; }
    .es-pct { min-width: 90px; }
    .es-pct .bar { height: 4px; border-radius: 999px; background: #e2e8f0; overflow: hidden; margin-top: .2rem; }
    .es-pct .bar > span { display: block; height: 100%; border-radius: 999px; }
    .es-live-toggles {
        display: inline-flex; align-items: center; flex-wrap: wrap; gap: .35rem;
    }
    .es-live-toggle {
        appearance: none; font: inherit; line-height: 1.2;
        font-size: .75rem; font-weight: 600; border-radius: 999px;
        padding: .15rem .65rem; cursor: pointer; user-select: none;
        border: 1px solid transparent; background: #f8fafc; color: #64748b;
    }
    .es-live-toggle.is-live { color: #15803d; background: #f0fdf4; border-color: #bbf7d0; }
    .es-live-toggle.is-absent { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
    .es-live-toggle.is-idle { color: #a16207; background: #fefce8; border-color: #fde68a; }
    .es-live-toggle.is-all { color: #334155; background: #f1f5f9; border-color: #cbd5e1; }
    .es-live-toggle:hover { filter: brightness(.97); }
    .es-live-toggle.is-live.is-active { background: #22c55e; border-color: #16a34a; color: #fff; }
    .es-live-toggle.is-absent.is-active { background: #ef4444; border-color: #dc2626; color: #fff; }
    .es-live-toggle.is-idle.is-active { background: #eab308; border-color: #ca8a04; color: #fff; }
    .es-live-toggle.is-all.is-active { background: #334155; border-color: #1e293b; color: #fff; }
    .es-table-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        width: 100%;
        margin-bottom: 1rem;
    }
    .es-table-toolbar-left {
        display: flex;
        align-items: center;
        gap: .5rem;
        flex: 1;
        min-width: 0;
        flex-wrap: wrap;
    }
    .es-table-toolbar .es-search { max-width: 280px; width: 100%; flex: 0 1 280px; }
    .es-status-filter {
        width: auto;
        min-width: 160px;
        max-width: 200px;
        height: 31px;
        font-size: .85rem;
    }
    .es-header-actions { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
</style>
@endsection

@section('content')
<div class="container-fluid" id="employeeSummary"
     data-refresh-url="{{ route('attendance.summary.data') }}"
     data-avatar-fallback="{{ asset('images/users/avatar-2.jpg') }}">

    <div class="row mb-3">
        <div class="col-12">
            <div class="es-card p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h4 class="mb-1">Team Monitoring</h4>
                        <p class="text-muted small mb-0">View aggregated work hours, activity levels, and time breakdown for your team.</p>
                    </div>
                    <div class="es-header-actions">
                        <a href="{{ route('attendance.live.team', array_filter(['team' => $team, 'timezone' => $timezone, 'executive' => $executive ?? null])) }}"
                           class="btn btn-sm btn-danger">
                            <i class="ri-vidicon-line me-1"></i> Team Monitor Video
                        </a>
                        <a href="{{ route('attendance.monitor', array_filter(['date' => $to, 'team' => $team, 'timezone' => $timezone, 'day_reset' => $day_reset, 'executive' => $executive ?? null])) }}"
                           class="btn btn-sm btn-outline-primary">
                            <i class="ri-time-line me-1"></i> Team Timeline
                        </a>
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-muted">Auto Refresh</span>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="autoRefresh">
                            </div>
                            <span class="small text-muted" id="autoRefreshLabel">Off</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3" id="kpiRow">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="es-kpi blue"><div class="val" id="kpiWorked">{{ $totals['time_worked'] }}</div><div class="lbl">Time Worked · L30</div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="es-kpi green"><div class="val" id="kpiActive">{{ $totals['timer_active'] }}</div><div class="lbl">Timer (Active) · L30</div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="es-kpi orange"><div class="val" id="kpiManual">{{ $totals['manual_entry'] }}</div><div class="lbl">Manual Entry · L30</div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="es-kpi teal"><div class="val" id="kpiMeeting">{{ $totals['meeting_hours'] }}</div><div class="lbl">Meeting Hours · L30</div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="es-kpi red"><div class="val" id="kpiIdle">{{ $totals['idle_time'] }}</div><div class="lbl">Idle Time · L30</div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="es-kpi gray"><div class="val" id="kpiEmployees">{{ $totals['employees_worked'] }}</div><div class="lbl">Employees Worked · L30</div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="es-card p-3">
                @php
                    $liveToggleCounts = collect($rows)->countBy(fn ($row) => $row['live_status'] ?? 'absent');
                @endphp
                <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                    <h6 class="mb-0"><i class="ri-group-line me-1"></i> Team Summary</h6>
                    <div class="es-live-toggles" id="liveToggles" role="group" aria-label="Live status filter">
                        <button type="button" class="es-live-toggle is-live" data-live-filter="working" title="Show live executives">
                            Live <span id="liveCount">{{ (int) ($liveToggleCounts['working'] ?? 0) }}</span>
                        </button>
                        <button type="button" class="es-live-toggle is-absent" data-live-filter="absent" title="Show absent executives">
                            Absent <span id="absentCount">{{ (int) ($liveToggleCounts['absent'] ?? 0) }}</span>
                        </button>
                        <button type="button" class="es-live-toggle is-idle" data-live-filter="idle" title="Show idle executives">
                            Idle <span id="idleCount">{{ (int) ($liveToggleCounts['idle'] ?? 0) }}</span>
                        </button>
                        <button type="button" class="es-live-toggle is-all is-active" data-live-filter="all" title="Show all executives">
                            ALL
                        </button>
                    </div>
                </div>

                <form method="get" class="es-toolbar mb-2" id="filterForm">
                    <div class="es-field-team">
                        <label class="form-label small text-muted mb-0">Designation</label>
                        @php
                            $teamLabel = $team === 'all' ? 'All Employees' : $team;
                        @endphp
                        <div class="es-ss" data-ss>
                            <select name="team" id="teamSelect" class="form-select form-select-sm es-ss-native">
                                <option value="all" {{ $team === 'all' ? 'selected' : '' }}>All Employees</option>
                                @foreach($teams as $t)
                                <option value="{{ $t }}" {{ $team === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="form-select form-select-sm es-ss-btn" data-ss-btn>{{ $teamLabel }}</button>
                            <div class="es-ss-panel" data-ss-panel>
                                <input type="search" class="form-control form-control-sm es-ss-q" placeholder="Quick search..." autocomplete="off">
                                <div class="es-ss-list">
                                    <button type="button" class="es-ss-item {{ $team === 'all' ? 'is-on' : '' }}" data-ss-val="all">All Employees</button>
                                    @foreach($teams as $t)
                                    <button type="button" class="es-ss-item {{ $team === $t ? 'is-on' : '' }}" data-ss-val="{{ $t }}">{{ $t }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="es-field-exec">
                        <label class="form-label small text-muted mb-0">Executive</label>
                        @php
                            $selectedExec = collect($executives ?? [])->firstWhere('id', (int) ($executive ?? 0));
                            $execLabel = is_array($selectedExec) ? ($selectedExec['name'] ?? 'All executives') : 'All executives';
                        @endphp
                        <div class="es-ss" data-ss>
                            <select name="executive" class="form-select form-select-sm es-ss-native" id="executiveSelect">
                                <option value="0" {{ (int) ($executive ?? 0) === 0 ? 'selected' : '' }}>All executives</option>
                                @foreach($executives as $exec)
                                <option value="{{ $exec['id'] }}" {{ (int) ($executive ?? 0) === (int) $exec['id'] ? 'selected' : '' }}>{{ $exec['name'] }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="form-select form-select-sm es-ss-btn" data-ss-btn>{{ $execLabel }}</button>
                            <div class="es-ss-panel" data-ss-panel>
                                <input type="search" class="form-control form-control-sm es-ss-q" placeholder="Quick search..." autocomplete="off">
                                <div class="es-ss-list">
                                    <button type="button" class="es-ss-item {{ (int) ($executive ?? 0) === 0 ? 'is-on' : '' }}" data-ss-val="0">All executives</button>
                                    @foreach($executives as $exec)
                                    <button type="button" class="es-ss-item {{ (int) ($executive ?? 0) === (int) $exec['id'] ? 'is-on' : '' }}" data-ss-val="{{ $exec['id'] }}">{{ $exec['name'] }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="es-field-range">
                        <label class="form-label small text-muted mb-0">Range</label>
                        <select name="range" class="form-select form-select-sm" id="rangeSelect">
                            <option value="l30" {{ ($range_key ?? 'l30') === 'l30' ? 'selected' : '' }}>L30 Days</option>
                            <option value="today" {{ ($range_key ?? '') === 'today' ? 'selected' : '' }}>Today</option>
                            <option value="week" {{ ($range_key ?? '') === 'week' ? 'selected' : '' }}>This week</option>
                            <option value="month" {{ ($range_key ?? '') === 'month' ? 'selected' : '' }}>This month</option>
                            <option value="prev_month" {{ ($range_key ?? '') === 'prev_month' ? 'selected' : '' }}>Previous month</option>
                            <option value="custom" {{ ($range_key ?? '') === 'custom' ? 'selected' : '' }}>Custom</option>
                        </select>
                    </div>
                    <div id="customRangeFields" class="d-flex align-items-end gap-2 {{ ($range_key ?? '') === 'custom' ? '' : 'd-none' }}">
                        <div>
                            <label class="form-label small text-muted mb-0">Start Date</label>
                            <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="form-label small text-muted mb-0">End Date</label>
                            <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div class="es-field-reset">
                        <label class="form-label small text-muted mb-0">Day reset</label>
                        <select name="day_reset" class="form-select form-select-sm">
                            @foreach(\App\Services\Attendance\AttendanceTimelineService::dayResetOptions($timezone) as $reset => $label)
                            <option value="{{ $reset }}" {{ $day_reset === $reset ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="es-field-tz">
                        <label class="form-label small text-muted mb-0">Timezone</label>
                        <select name="timezone" class="form-select form-select-sm">
                            @foreach(\App\Services\Attendance\AttendanceTimelineService::timezoneOptions() as $tz => $label)
                            <option value="{{ $tz }}" {{ $timezone === $tz ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="ri-refresh-line"></i> Refresh
                    </button>
                </form>

                <div class="es-table-toolbar">
                    <div class="es-table-toolbar-left">
                        <input type="search" class="form-control form-control-sm es-search" id="employeeSearch" placeholder="Search employees...">
                        <select class="form-select form-select-sm es-status-filter" id="statusFilter" aria-label="Status filter">
                            <option value="all">Status: All</option>
                            <option value="logged">Status: Logged</option>
                            <option value="not_logged">Status: Not logged</option>
                        </select>
                    </div>
                    <a href="{{ route('attendance.summary.export', request()->query()) }}" class="btn btn-sm btn-primary flex-shrink-0" id="btnDownload">
                        <i class="ri-download-line me-1"></i> Download CSV
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover es-table" id="summaryTable">
                        <thead>
                            <tr>
                                <th class="es-avatar-col" style="width:5%">Image</th>
                                <th class="es-sort is-asc" data-sort="name" data-type="text" style="width:13%" aria-sort="ascending">Employee</th>
                                <th class="es-tm-col" style="width:4%" title="TM — open Task Manager for this employee as assignee (new tab)">TM</th>
                                <th class="es-ts-col" style="width:4%" title="TS — open Task Summary for this employee (new tab)">TS</th>
                                <th class="es-time-col" style="width:5%">Time</th>
                                <th class="es-sort es-live-col" data-sort="live" data-type="num" style="width:6%">Live</th>
                                <th class="es-sort es-shot-col" data-sort="lastImage" data-type="num" style="width:9%">Last Image</th>
                                <th class="es-sort" data-sort="span" data-type="num" style="width:12%">Activity Span</th>
                                <th class="es-sort" data-sort="worked" data-type="num" style="width:11%">Total Time</th>
                                <th class="es-sort" data-sort="manual" data-type="num" style="width:10%">Manual</th>
                                <th class="es-sort" data-sort="activeMin" data-type="num" style="width:10%">% Active Minutes</th>
                                <th class="es-sort" data-sort="activeSec" data-type="num" style="width:10%">% Active Seconds</th>
                                <th class="es-sort es-th-idle" data-sort="idle" data-type="num" style="width:9%">Idle</th>
                                <th class="es-sort" data-sort="including" data-type="num" style="width:9%">Work Time</th>
                            </tr>
                        </thead>
                        <tbody id="summaryBody">
                            @foreach($rows as $row)
                            <tr class="es-selectable {{ (int) ($executive ?? 0) === (int) $row['user_id'] ? 'es-selected' : '' }}"
                                data-user-id="{{ $row['user_id'] }}"
                                data-name="{{ strtolower($row['name']) }}"
                                data-display-name="{{ $row['name'] }}"
                                data-email="{{ strtolower($row['email']) }}"
                                data-has-worked="{{ !empty($row['has_worked']) ? '1' : '0' }}"
                                data-span="{{ (int) ($row['activity_start_minutes'] ?? -1) }}"
                                data-worked="{{ (int) ($row['worked_seconds'] ?? 0) }}"
                                data-meeting="{{ (int) ($row['meeting_seconds'] ?? 0) }}"
                                data-manual="{{ (int) ($row['manual_seconds'] ?? 0) }}"
                                data-active-min="{{ (int) ($row['active_min_pct'] ?? 0) }}"
                                data-active-sec="{{ (int) ($row['active_sec_pct'] ?? 0) }}"
                                data-idle="{{ (int) ($row['idle_seconds'] ?? 0) }}"
                                data-including="{{ (int) ($row['work_time_seconds'] ?? $row['including_idle_seconds'] ?? 0) }}"
                                data-live="{{ (int) ($row['live_sort'] ?? 3) }}"
                                data-live-status="{{ $row['live_status'] ?? 'absent' }}"
                                data-last-image="{{ (int) ($row['last_image_sort'] ?? 0) }}">
                                <td class="es-avatar-col">
                                    <img src="{{ $row['avatar_url'] }}" alt="" class="es-avatar" loading="lazy"
                                         onerror="this.onerror=null;this.src='{{ asset('images/users/avatar-2.jpg') }}';">
                                </td>
                                <td>
                                    <div class="es-name">{{ $row['name'] }}</div>
                                </td>
                                <td class="es-tm-col">
                                    @php
                                        $tmLevel = strtolower((string) ($row['org_level'] ?? ''));
                                        $tmBadgeMod = $tmLevel === 'director'
                                            ? 'task-summary-tm-badge-director'
                                            : ($tmLevel === 'mgr'
                                                ? 'task-summary-tm-badge-mgr'
                                                : 'task-summary-tm-badge-exec');
                                        $tmUrl = $row['tm_url'] ?? route('tasks.index', array_filter([
                                            'assignee' => $row['name'],
                                            'user_id' => (int) ($row['user_id'] ?? 0),
                                        ]));
                                    @endphp
                                    <a href="{{ $tmUrl }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="task-summary-tm-badge {{ $tmBadgeMod }}"
                                       title="Open Task Manager for {{ e($row['name']) }} (assignee filter)"
                                       aria-label="Open Task Manager for {{ e($row['name']) }} as assignee">TM</a>
                                </td>
                                <td class="es-ts-col">
                                    @php
                                        $tsUrl = $row['ts_url'] ?? route('tasks.summary', array_filter([
                                            'member' => $row['name'],
                                            'user_id' => (int) ($row['user_id'] ?? 0),
                                        ]));
                                    @endphp
                                    <a href="{{ $tsUrl }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="task-summary-tm-badge {{ $tmBadgeMod }}"
                                       title="Open Task Summary for {{ e($row['name']) }}"
                                       aria-label="Open Task Summary for {{ e($row['name']) }}">TS</a>
                                </td>
                                <td class="es-time-col">
                                    <a href="{{ $row['timeline_url'] }}" target="_blank" rel="noopener"
                                        class="es-time-btn" title="Open timeline in a new tab">
                                        <img src="{{ asset('assets/images/task-magnify-icon.png') }}" alt="" class="task-magnify-icon" aria-hidden="true">
                                    </a>
                                </td>
                                <td class="es-live-col">
                                    <button type="button" class="es-live-btn"
                                        data-live-url="{{ $row['live_url'] }}"
                                        data-name="{{ $row['name'] }}"
                                        title="Watch live screen and record — {{ $row['live_label'] ?? 'Absent' }}">
                                        <span class="es-live-status {{ $row['live_status'] ?? 'absent' }}"></span>
                                    </button>
                                </td>
                                <td class="es-shot-col">
                                    <div class="es-shot-row">
                                        @if(!empty($row['last_image_thumb']))
                                        <a href="{{ $row['last_image_url'] }}" target="_blank" rel="noopener" class="es-shot-thumb" title="{{ $row['last_image_label'] }}">
                                            <img src="{{ $row['last_image_thumb'] }}" alt="" loading="lazy">
                                        </a>
                                        @else
                                        <span class="text-muted">—</span>
                                        @endif
                                        @if(!empty($row['captures_url']))
                                        <a href="{{ $row['captures_url'] }}" target="_blank" rel="noopener" class="es-shot-dot" title="Open all screen captures in a new tab"></a>
                                        @endif
                                    </div>
                                    @if(!empty($row['last_image_time']))
                                    <div class="es-shot-time">{{ $row['last_image_time'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="es-span">
                                        @if($row['activity_is_live'])<span class="es-live-dot me-1"></span>@endif
                                        {{ $row['activity_span'] }}
                                    </div>
                                    @if($row['activity_updated'])
                                    <div class="es-span-updated">{{ $row['activity_updated'] }}</div>
                                    @endif
                                </td>
                                <td><span class="es-pill blue">{{ $row['worked_clock'] }}</span></td>
                                <td>{{ $row['manual_clock'] }}</td>
                                <td class="es-pct">
                                    <div>{{ $row['active_min_pct'] }}%</div>
                                    <div class="bar"><span style="width:{{ $row['active_min_pct'] }}%;background:#22c55e"></span></div>
                                </td>
                                <td class="es-pct">
                                    <div>{{ $row['active_sec_pct'] }}%</div>
                                    <div class="bar"><span style="width:{{ $row['active_sec_pct'] }}%;background:{{ $row['active_sec_pct'] >= 70 ? '#22c55e' : '#f97316' }}"></span></div>
                                </td>
                                <td><span class="es-pill red">{{ $row['idle_clock'] }}</span></td>
                                <td><span class="es-pill blue-text">{{ $row['work_time_clock'] ?? $row['including_idle_clock'] }}</span></td>
                            </tr>
                            @endforeach
                            @if(count($rows) === 0)
                            <tr class="es-empty-row">
                                <td colspan="14" class="text-center text-muted py-4">No employees match the current filters.</td>
                            </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="timelineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="timelineModalTitle">Timeline</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div class="small text-muted" id="timelineModalRange"></div>
                    <div class="tl-modal-legend">
                        <span><i style="background:#22c55e"></i> Working</span>
                        <span><i style="background:#ef4444"></i> Idle</span>
                        <span><i style="background:#94a3b8"></i> Break</span>
                    </div>
                </div>
                <div id="timelineModalBody"><div class="tl-modal-empty">Loading…</div></div>
            </div>
            <div class="modal-footer py-2">
                <a href="#" class="btn btn-sm btn-outline-primary" id="timelineFullLink" target="_blank">Open full page</a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    function initSearchSelect(root) {
        if (!root || root.dataset.ssReady) return;
        const select = root.querySelector('select');
        const btn = root.querySelector('[data-ss-btn]');
        const panel = root.querySelector('[data-ss-panel]');
        const q = panel && panel.querySelector('.es-ss-q');
        const list = panel && panel.querySelector('.es-ss-list');
        if (!select || !btn || !panel || !q || !list) return;
        root.dataset.ssReady = '1';

        const items = Array.from(list.querySelectorAll('.es-ss-item'));
        let empty = list.querySelector('.es-ss-empty');
        if (!empty) {
            empty = document.createElement('div');
            empty.className = 'es-ss-empty';
            empty.textContent = 'No match';
            empty.hidden = true;
            list.appendChild(empty);
        }

        const syncBtn = () => {
            btn.textContent = select.selectedOptions[0]?.textContent?.trim() || 'Select';
            items.forEach(item => {
                item.classList.toggle('is-on', String(item.dataset.ssVal) === String(select.value));
            });
        };

        function filterList() {
            const needle = (q.value || '').trim().toLowerCase();
            let shown = 0;
            items.forEach(item => {
                const ok = !needle || (item.textContent || '').toLowerCase().includes(needle);
                item.hidden = !ok;
                if (ok) shown++;
            });
            empty.hidden = shown > 0;
        }

        function placePanel() {
            const r = btn.getBoundingClientRect();
            panel.style.position = 'fixed';
            panel.style.left = Math.max(8, Math.min(r.left, window.innerWidth - 280)) + 'px';
            panel.style.top = (r.bottom + 4) + 'px';
            panel.style.minWidth = Math.max(260, r.width) + 'px';
            panel.style.zIndex = '2000';
        }

        function closePanel() {
            root.classList.remove('is-open');
            panel.classList.remove('is-open');
        }

        function openPanel() {
            document.querySelectorAll('[data-ss]').forEach(el => {
                el.classList.remove('is-open');
                const p = el._esPanel || el.querySelector('[data-ss-panel]');
                if (p) p.classList.remove('is-open');
            });
            q.value = '';
            filterList();
            if (panel.parentNode !== document.body) {
                document.body.appendChild(panel);
            }
            root._esPanel = panel;
            root.classList.add('is-open');
            panel.classList.add('is-open');
            placePanel();
            q.focus();
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (panel.classList.contains('is-open')) closePanel();
            else openPanel();
        });

        items.forEach(item => {
            item.addEventListener('mousedown', function (ev) {
                ev.preventDefault();
                select.value = item.dataset.ssVal;
                syncBtn();
                closePanel();
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });

        q.addEventListener('input', filterList);
        q.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closePanel();
            if (e.key === 'Enter') {
                e.preventDefault();
                const first = items.find(it => !it.hidden);
                if (first) first.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
            }
        });
        q.addEventListener('click', function (e) { e.stopPropagation(); });
        select.addEventListener('change', syncBtn);
        document.addEventListener('click', function (e) {
            if (!root.contains(e.target) && !panel.contains(e.target)) closePanel();
        });
        window.addEventListener('resize', function () {
            if (panel.classList.contains('is-open')) placePanel();
        });
        window.addEventListener('scroll', function () {
            if (panel.classList.contains('is-open')) placePanel();
        }, true);
        select._esSyncBtn = syncBtn;
    }

    document.querySelectorAll('[data-ss]').forEach(initSearchSelect);
})();
</script>
@endsection

@section('script')
<script>
(function() {
    const root = document.getElementById('employeeSummary');
    const filterForm = document.getElementById('filterForm');
    const rangeSelect = document.getElementById('rangeSelect');
    const customRangeFields = document.getElementById('customRangeFields');
    const searchInput = document.getElementById('employeeSearch');
    const statusFilter = document.getElementById('statusFilter');
    const liveToggles = document.getElementById('liveToggles');
    const autoRefresh = document.getElementById('autoRefresh');
    const autoRefreshLabel = document.getElementById('autoRefreshLabel');
    const executiveSelect = document.getElementById('executiveSelect');
    let refreshTimer = null;
    let sortKey = 'name';
    let sortDir = 'asc';
    let kpiOnlyRefresh = false;

    function currentStatus() {
        return statusFilter?.value || 'all';
    }

    function currentLiveFilter() {
        return liveToggles?.querySelector('.es-live-toggle.is-active')?.dataset.liveFilter || 'all';
    }

    function applyRowFilters() {
        const q = (searchInput?.value || '').trim().toLowerCase();
        const status = currentStatus();
        const liveFilter = currentLiveFilter();
        document.querySelectorAll('#summaryBody tr').forEach(tr => {
            const name = tr.dataset.name || '';
            const email = tr.dataset.email || '';
            const hasWorked = tr.dataset.hasWorked === '1';
            const liveStatus = tr.dataset.liveStatus || 'absent';
            const matchesSearch = !q || name.includes(q) || email.includes(q);
            const matchesStatus = status === 'all'
                || (status === 'logged' && hasWorked)
                || (status === 'not_logged' && !hasWorked);
            const matchesLive = liveFilter === 'all' || liveStatus === liveFilter;
            tr.style.display = (matchesSearch && matchesStatus && matchesLive) ? '' : 'none';
        });
        syncLiveToggles();
    }

    function sortValue(tr, key) {
        const raw = tr.dataset[key];
        if (key === 'name') {
            return (raw || '').toString();
        }
        const n = Number(raw);
        return Number.isFinite(n) ? n : 0;
    }

    function applySort() {
        const body = document.getElementById('summaryBody');
        if (!body) return;
        const rows = Array.from(body.querySelectorAll('tr'));
        rows.sort((a, b) => {
            const av = sortValue(a, sortKey);
            const bv = sortValue(b, sortKey);
            let cmp = 0;
            if (typeof av === 'string' || typeof bv === 'string') {
                cmp = String(av).localeCompare(String(bv), undefined, { sensitivity: 'base' });
            } else {
                cmp = av - bv;
            }
            if (cmp === 0) {
                cmp = sortValue(a, 'name').toString().localeCompare(sortValue(b, 'name').toString(), undefined, { sensitivity: 'base' });
            }
            return sortDir === 'asc' ? cmp : -cmp;
        });
        rows.forEach(tr => body.appendChild(tr));
        document.querySelectorAll('#summaryTable thead th.es-sort').forEach(th => {
            const active = th.dataset.sort === sortKey;
            th.classList.toggle('is-asc', active && sortDir === 'asc');
            th.classList.toggle('is-desc', active && sortDir === 'desc');
            th.setAttribute('aria-sort', active ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none');
        });
    }

    document.querySelectorAll('#summaryTable thead th.es-sort').forEach(th => {
        th.addEventListener('click', function() {
            const key = this.dataset.sort;
            if (sortKey === key) {
                sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                sortKey = key;
                sortDir = this.dataset.type === 'text' ? 'asc' : 'desc';
            }
            applySort();
        });
    });

    function syncLiveToggles() {
        const active = currentLiveFilter();
        liveToggles?.querySelectorAll('.es-live-toggle').forEach(btn => {
            const on = btn.dataset.liveFilter === active;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function setLiveFilter(value) {
        liveToggles?.querySelectorAll('.es-live-toggle').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.liveFilter === String(value || 'all'));
        });
        applyRowFilters();
    }

    function paintLiveCounts(rows) {
        const counts = { working: 0, absent: 0, idle: 0 };
        (rows || []).forEach(row => {
            const key = row.live_status || 'absent';
            if (counts[key] != null) counts[key]++;
            else counts.absent++;
        });
        const liveEl = document.getElementById('liveCount');
        const absentEl = document.getElementById('absentCount');
        const idleEl = document.getElementById('idleCount');
        if (liveEl) liveEl.textContent = counts.working;
        if (absentEl) absentEl.textContent = counts.absent;
        if (idleEl) idleEl.textContent = counts.idle;
    }

    function toggleCustomRange() {
        const isCustom = rangeSelect?.value === 'custom';
        customRangeFields?.classList.toggle('d-none', !isCustom);
        customRangeFields?.classList.toggle('d-flex', isCustom);
    }

    rangeSelect?.addEventListener('change', function() {
        toggleCustomRange();
        if (this.value !== 'custom') {
            filterForm?.submit();
        }
    });

    filterForm?.querySelectorAll('select:not(#rangeSelect):not(#executiveSelect), input[type=date]').forEach(el => {
        el.addEventListener('change', () => filterForm?.submit());
    });

    function highlightExecutive(userId) {
        const id = String(userId || 0);
        document.querySelectorAll('#summaryBody tr').forEach(tr => {
            tr.classList.toggle('es-selected', id !== '0' && tr.dataset.userId === id);
        });
    }

    function paintKpis(totals) {
        if (!totals) return;
        document.getElementById('kpiWorked').textContent = totals.time_worked;
        document.getElementById('kpiActive').textContent = totals.timer_active;
        document.getElementById('kpiManual').textContent = totals.manual_entry;
        document.getElementById('kpiMeeting').textContent = totals.meeting_hours;
        document.getElementById('kpiIdle').textContent = totals.idle_time;
        document.getElementById('kpiEmployees').textContent = totals.employees_worked;
        highlightExecutive(executiveSelect?.value || 0);
    }

    function syncExecutiveUrl() {
        const url = new URL(window.location.href);
        const id = executiveSelect?.value || '0';
        if (!id || id === '0') url.searchParams.delete('executive');
        else url.searchParams.set('executive', id);
        window.history.replaceState({}, '', url);
    }

    async function selectExecutive(userId, { refreshTable = true } = {}) {
        if (executiveSelect) {
            executiveSelect.value = String(userId || 0);
            if (typeof executiveSelect._esSyncBtn === 'function') {
                executiveSelect._esSyncBtn();
            }
        }
        setLiveFilter('all');
        highlightExecutive(userId);
        syncExecutiveUrl();
        kpiOnlyRefresh = !refreshTable;
        await refreshData();
        kpiOnlyRefresh = false;
    }

    executiveSelect?.addEventListener('change', function() {
        selectExecutive(this.value);
    });

    toggleCustomRange();

    searchInput?.addEventListener('input', applyRowFilters);
    statusFilter?.addEventListener('change', applyRowFilters);

    liveToggles?.addEventListener('click', function(e) {
        const btn = e.target.closest('.es-live-toggle');
        if (!btn) return;
        setLiveFilter(btn.dataset.liveFilter || 'all');
    });

    function barColor(pct) {
        return pct >= 70 ? '#22c55e' : '#f97316';
    }

    function escapeAttr(value) {
        return String(value || '').replace(/[&<>"']/g, ch => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[ch]));
    }

    function avatarCell(row) {
        const fallback = root?.dataset.avatarFallback || '';
        const src = row.avatar_url || fallback;
        return '<img src="' + escapeAttr(src) + '" alt="" class="es-avatar" loading="lazy" onerror="this.onerror=null;this.src=\'' + escapeAttr(fallback) + '\';">';
    }

    function lastImageCell(row) {
        const thumb = row.last_image_thumb
            ? '<a href="' + escapeAttr(row.last_image_url) + '" target="_blank" rel="noopener" class="es-shot-thumb" title="' +
                escapeAttr(row.last_image_label) + '"><img src="' + escapeAttr(row.last_image_thumb) +
                '" alt="" loading="lazy"></a>'
            : '<span class="text-muted">—</span>';
        const dot = row.captures_url
            ? '<a href="' + escapeAttr(row.captures_url) + '" target="_blank" rel="noopener" class="es-shot-dot" title="Open all screen captures in a new tab"></a>'
            : '';
        const time = row.last_image_time ? '<div class="es-shot-time">' + escapeAttr(row.last_image_time) + '</div>' : '';
        return '<div class="es-shot-row">' + thumb + dot + '</div>' + time;
    }

    function tmBadgeClass(level) {
        const l = String(level || '').toLowerCase();
        if (l === 'director') return 'task-summary-tm-badge-director';
        if (l === 'mgr') return 'task-summary-tm-badge-mgr';
        return 'task-summary-tm-badge-exec';
    }

    function tmCell(row) {
        let href = row.tm_url || '';
        if (!href) {
            const params = new URLSearchParams();
            if (row.name) params.set('assignee', row.name);
            if (row.user_id) params.set('user_id', String(row.user_id));
            href = '{{ route('tasks.index') }}' + (params.toString() ? '?' + params.toString() : '');
        }
        const name = row.name || 'employee';
        return '<a href="' + escapeAttr(href) + '" target="_blank" rel="noopener noreferrer" class="task-summary-tm-badge ' +
            tmBadgeClass(row.org_level) + '" title="Open Task Manager for ' + escapeAttr(name) +
            ' (assignee filter)" aria-label="Open Task Manager for ' + escapeAttr(name) + ' as assignee">TM</a>';
    }

    function tsCell(row) {
        let href = row.ts_url || '';
        if (!href) {
            const params = new URLSearchParams();
            if (row.name) params.set('member', row.name);
            if (row.user_id) params.set('user_id', String(row.user_id));
            href = '{{ route('tasks.summary') }}' + (params.toString() ? '?' + params.toString() : '');
        }
        const name = row.name || 'employee';
        return '<a href="' + escapeAttr(href) + '" target="_blank" rel="noopener noreferrer" class="task-summary-tm-badge ' +
            tmBadgeClass(row.org_level) + '" title="Open Task Summary for ' + escapeAttr(name) +
            '" aria-label="Open Task Summary for ' + escapeAttr(name) + '">TS</a>';
    }

    function renderRows(rows) {
        const body = document.getElementById('summaryBody');
        if (!body) return;
        const selectedId = String(executiveSelect?.value || 0);
        if (!rows.length) {
            body.innerHTML = '<tr class="es-empty-row"><td colspan="14" class="text-center text-muted py-4">No employees match the current filters.</td></tr>';
            return;
        }
        body.innerHTML = rows.map(row => `
            <tr class="es-selectable${selectedId !== '0' && String(row.user_id) === selectedId ? ' es-selected' : ''}" data-user-id="${row.user_id || ''}" data-name="${(row.name || '').toLowerCase()}" data-display-name="${escapeAttr(row.name || '')}" data-email="${(row.email || '').toLowerCase()}" data-has-worked="${row.has_worked ? '1' : '0'}" data-span="${row.activity_start_minutes ?? -1}" data-worked="${row.worked_seconds || 0}" data-meeting="${row.meeting_seconds || 0}" data-manual="${row.manual_seconds || 0}" data-active-min="${row.active_min_pct || 0}" data-active-sec="${row.active_sec_pct || 0}" data-idle="${row.idle_seconds || 0}" data-including="${row.work_time_seconds ?? row.including_idle_seconds ?? 0}" data-live="${row.live_sort ?? 3}" data-live-status="${escapeAttr(row.live_status || 'absent')}" data-last-image="${row.last_image_sort || 0}">
                <td class="es-avatar-col">${avatarCell(row)}</td>
                <td>
                    <div class="es-name">${row.name}</div>
                </td>
                <td class="es-tm-col">${tmCell(row)}</td>
                <td class="es-ts-col">${tsCell(row)}</td>
                <td class="es-time-col"><a href="${escapeAttr(row.timeline_url || '#')}" target="_blank" rel="noopener" class="es-time-btn" title="Open timeline in a new tab"><img src="{{ asset('assets/images/task-magnify-icon.png') }}" alt="" class="task-magnify-icon" aria-hidden="true"></a></td>
                <td class="es-live-col"><button type="button" class="es-live-btn" data-live-url="${escapeAttr(row.live_url || '')}" data-name="${escapeAttr(row.name || '')}" title="Watch live screen and record — ${escapeAttr(row.live_label || 'Absent')}"><span class="es-live-status ${row.live_status || 'absent'}"></span></button></td>
                <td class="es-shot-col">${lastImageCell(row)}</td>
                <td>
                    <div class="es-span">${row.activity_is_live ? '<span class="es-live-dot me-1"></span>' : ''}${row.activity_span}</div>
                    ${row.activity_updated ? `<div class="es-span-updated">${row.activity_updated}</div>` : ''}
                </td>
                <td><span class="es-pill blue">${row.worked_clock}</span></td>
                <td>${row.manual_clock}</td>
                <td class="es-pct"><div>${row.active_min_pct}%</div><div class="bar"><span style="width:${row.active_min_pct}%;background:#22c55e"></span></div></td>
                <td class="es-pct"><div>${row.active_sec_pct}%</div><div class="bar"><span style="width:${row.active_sec_pct}%;background:${barColor(row.active_sec_pct)}"></span></div></td>
                <td><span class="es-pill red">${row.idle_clock}</span></td>
                <td><span class="es-pill blue-text">${row.work_time_clock || row.including_idle_clock}</span></td>
            </tr>
        `).join('');
        applySort();
        applyRowFilters();
    }

    async function refreshData() {
        const params = new URLSearchParams(new FormData(filterForm));
        if (kpiOnlyRefresh) {
            params.set('kpi_only', '1');
        }
        try {
            const r = await fetch(root.dataset.refreshUrl + '?' + params.toString(), {
                headers: { Accept: 'application/json' },
            });
            if (!r.ok) return;
            const data = await r.json();
            paintKpis(data.l30_totals || data.totals);
            if (!kpiOnlyRefresh) {
                const nextRows = Array.isArray(data.rows) ? data.rows : [];
                if (nextRows.length || !document.querySelector('#summaryBody tr:not(.es-empty-row)')) {
                    renderRows(nextRows);
                    paintLiveCounts(nextRows);
                }
            }
        } catch (_) {}
    }

    autoRefresh?.addEventListener('change', function() {
        autoRefreshLabel.textContent = this.checked ? 'On' : 'Off';
        if (refreshTimer) clearInterval(refreshTimer);
        if (this.checked) {
            refreshTimer = setInterval(refreshData, 60000);
        }
    });

    function openLiveWatch(url, name) {
        if (!url) return;
        const w = 1100;
        const h = 740;
        const left = Math.max(0, Math.round((window.screen.width - w) / 2));
        const top = Math.max(0, Math.round((window.screen.height - h) / 2));
        const features = 'popup=yes,width=' + w + ',height=' + h + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=no';
        const popup = window.open(url, 'live-watch-' + encodeURIComponent(name || 'employee'), features);
        if (!popup) {
            window.open(url, '_blank');
        }
    }

    function showTimelineModal() {
        const el = document.getElementById('timelineModal');
        if (!el) return;
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
            return;
        }
        if (window.jQuery) {
            window.jQuery(el).modal('show');
        }
    }

    function renderTimelineModal(data) {
        const title = document.getElementById('timelineModalTitle');
        const range = document.getElementById('timelineModalRange');
        const body = document.getElementById('timelineModalBody');
        const full = document.getElementById('timelineFullLink');
        if (title) title.textContent = (data.name || 'Employee') + ' — Timeline';
        if (range) range.textContent = data.range_label || '';
        if (full) {
            full.href = data.full_url || '#';
            full.classList.toggle('d-none', !data.full_url);
        }
        const days = data.days || [];
        const axis = data.axis_hours || [];
        if (!days.length) {
            body.innerHTML = '<div class="tl-modal-empty">No activity in this period.</div>';
            return;
        }
        const axisHtml = '<div class="tl-modal-axis">' + axis.map(h => '<span>' + escapeAttr(h) + '</span>').join('') + '</div>';
        body.innerHTML = days.map(day => {
            const stats = day.stats || {};
            const segs = (day.segments || []).map(seg => {
                const color = seg.color || (seg.state === 'idle' ? '#ef4444' : (seg.state === 'break' ? '#94a3b8' : '#22c55e'));
                const width = Math.max(Number(seg.width_pct) || 0, 0.12);
                return '<div class="tl-modal-seg" style="left:' + (seg.start_pct || 0) + '%;width:' + width + '%;background-color:' + color + '" title="' +
                    escapeAttr((day.date_label || '') + ' · ' + (seg.state || '') + ' · ' + (seg.start_label || '') + ' – ' + (seg.end_label || '')) + '"></div>';
            }).join('');
            return '<div class="tl-modal-day">' +
                '<div class="tl-modal-day-head">' +
                    '<div class="tl-modal-date">' + (day.is_live ? '<span class="es-live-dot me-1"></span>' : '') +
                        escapeAttr(day.date_label || '') +
                        (day.is_today ? '<span class="is-today">Today</span>' : '') +
                        '<span class="tl-modal-clock">In ' + escapeAttr(day.login_label || '—') +
                        ' · Out ' + escapeAttr(day.logout_label || '—') + '</span>' +
                    '</div>' +
                    '<div class="tl-modal-stats">' +
                        '<span><strong>' + escapeAttr(stats.worked_label || '0m') + '</strong> Active</span>' +
                        '<span><strong>' + escapeAttr(stats.idle_label || '0m') + '</strong> Idle</span>' +
                        '<span><strong>' + escapeAttr(stats.break_label || '0m') + '</strong> Break</span>' +
                        '<span><strong>' + escapeAttr(stats.total_label || '0m') + '</strong> Total</span>' +
                    '</div>' +
                '</div>' +
                axisHtml +
                '<div class="tl-modal-track"><div class="tl-modal-track-grid"></div>' + segs + '</div>' +
            '</div>';
        }).join('');
    }

    async function openTimeline(apiUrl, name) {
        if (!apiUrl) return;
        const params = new URLSearchParams(new FormData(filterForm));
        params.set('period', 'custom');
        document.getElementById('timelineModalTitle').textContent = (name || 'Employee') + ' — Timeline';
        document.getElementById('timelineModalRange').textContent = '';
        document.getElementById('timelineModalBody').innerHTML = '<div class="tl-modal-empty">Loading…</div>';
        showTimelineModal();
        try {
            const r = await fetch(apiUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } });
            if (!r.ok) throw new Error('Failed');
            renderTimelineModal(await r.json());
        } catch (_) {
            document.getElementById('timelineModalBody').innerHTML = '<div class="tl-modal-empty">Could not load timeline.</div>';
        }
    }

    document.getElementById('summaryBody')?.addEventListener('click', function(e) {
        const btn = e.target.closest('.es-live-btn');
        if (btn) {
            openLiveWatch(btn.dataset.liveUrl, btn.dataset.name || 'Employee');
            return;
        }
        if (e.target.closest('a, button')) return;
        const tr = e.target.closest('tr[data-user-id]');
        if (!tr) return;
        const id = tr.dataset.userId || '0';
        selectExecutive(executiveSelect?.value === id ? 0 : id);
    });
})();
</script>
@endsection
