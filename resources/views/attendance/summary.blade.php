@extends('layouts.vertical', ['title' => $title ?? 'Team Monitoring'])

@section('css')
<style>
    .es-card { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
    .es-toolbar .form-select, .es-toolbar .form-control { min-height: 34px; font-size: .85rem; }
    .es-kpi {
        border-radius: 10px; padding: .85rem 1rem; color: #fff; height: 100%;
        display: flex; flex-direction: column; justify-content: center;
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
    .es-table { font-size: .82rem; margin-bottom: 0; table-layout: fixed; width: 100%; min-width: 1100px; }
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
    .es-name { font-weight: 600; color: #0f172a; }
    .es-timeline-link { font-size: .72rem; }
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
    .es-shot-col { text-align: center; }
    .es-shot-thumb {
        display: inline-block; width: 72px; height: 44px; border-radius: 6px;
        overflow: hidden; border: 1px solid #e2e8f0; background: #f1f5f9;
        vertical-align: middle;
    }
    .es-shot-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .es-shot-thumb:hover { border-color: #94a3b8; }
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
    .es-not-logged {
        appearance: none; font: inherit; line-height: 1.2;
        font-size: .75rem; color: #ea580c; background: #fff7ed; border: 1px solid #fed7aa;
        border-radius: 999px; padding: .15rem .55rem; cursor: pointer; user-select: none;
    }
    .es-not-logged:hover { background: #ffedd5; border-color: #fdba74; }
    .es-not-logged.is-active { background: #ea580c; color: #fff; border-color: #ea580c; }
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
     data-refresh-url="{{ route('attendance.summary.data') }}">

    <div class="row mb-3">
        <div class="col-12">
            <div class="es-card p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h4 class="mb-1">Team Monitoring</h4>
                        <p class="text-muted small mb-0">View aggregated work hours, activity levels, and time breakdown for your team.</p>
                    </div>
                    <div class="es-header-actions">
                        <a href="{{ route('attendance.monitor', ['date' => $to, 'team' => $team, 'timezone' => $timezone, 'day_reset' => $day_reset]) }}"
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

                <form method="get" class="d-flex flex-wrap align-items-end gap-2 es-toolbar" id="filterForm">
                    <div>
                        <label class="form-label small text-muted mb-0">Team</label>
                        <select name="team" class="form-select form-select-sm">
                            <option value="all" {{ $team === 'all' ? 'selected' : '' }}>All Employees</option>
                            @foreach($teams as $t)
                            <option value="{{ $t }}" {{ $team === $t ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-0">Range</label>
                        <select name="range" class="form-select form-select-sm" id="rangeSelect">
                            <option value="today" {{ ($range_key ?? '') === 'today' ? 'selected' : '' }}>Today</option>
                            <option value="week" {{ ($range_key ?? '') === 'week' ? 'selected' : '' }}>This week</option>
                            <option value="month" {{ ($range_key ?? '') === 'month' ? 'selected' : '' }}>This month</option>
                            <option value="prev_month" {{ ($range_key ?? '') === 'prev_month' ? 'selected' : '' }}>Previous month</option>
                            <option value="custom" {{ ($range_key ?? 'custom') === 'custom' ? 'selected' : '' }}>Custom</option>
                        </select>
                    </div>
                    <div id="customRangeFields" class="d-flex flex-wrap align-items-end gap-2 {{ ($range_key ?? 'custom') === 'custom' ? '' : 'd-none' }}">
                        <div>
                            <label class="form-label small text-muted mb-0">Start Date</label>
                            <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="form-label small text-muted mb-0">End Date</label>
                            <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                        </div>
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-0">Day reset</label>
                        <select name="day_reset" class="form-select form-select-sm">
                            @foreach(\App\Services\Attendance\AttendanceTimelineService::dayResetOptions($timezone) as $reset => $label)
                            <option value="{{ $reset }}" {{ $day_reset === $reset ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
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
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3" id="kpiRow">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="es-kpi blue"><div class="val" id="kpiWorked">{{ $totals['time_worked'] }}</div><div class="lbl">Time Worked</div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="es-kpi green"><div class="val" id="kpiActive">{{ $totals['timer_active'] }}</div><div class="lbl">Timer (Active)</div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="es-kpi orange"><div class="val" id="kpiManual">{{ $totals['manual_entry'] }}</div><div class="lbl">Manual Entry</div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="es-kpi teal"><div class="val" id="kpiMeeting">{{ $totals['meeting_hours'] }}</div><div class="lbl">Meeting Hours</div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="es-kpi red"><div class="val" id="kpiIdle">{{ $totals['idle_time'] }}</div><div class="lbl">Idle Time</div></div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="es-kpi gray"><div class="val" id="kpiEmployees">{{ $totals['employees_worked'] }}</div><div class="lbl">Employees Worked</div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="es-card p-3">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                    <h6 class="mb-0"><i class="ri-group-line me-1"></i> Team Summary</h6>
                    <button type="button" class="es-not-logged {{ $not_logged > 0 ? '' : 'd-none' }}" id="notLoggedBadge" aria-pressed="false" title="Show only employees who have not logged">
                        {{ $not_logged }}/{{ $total_employees }} not logged
                    </button>
                </div>

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
                                <th class="es-sort is-asc" data-sort="name" data-type="text" style="width:16%" aria-sort="ascending">Employee</th>
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
                            <tr data-name="{{ strtolower($row['name']) }}"
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
                                data-last-image="{{ (int) ($row['last_image_sort'] ?? 0) }}">
                                <td>
                                    <div class="es-name">{{ $row['name'] }}</div>
                                    <a href="{{ $row['timeline_url'] }}" class="es-timeline-link">Timeline</a>
                                </td>
                                <td class="es-live-col">
                                    <span class="es-live-status {{ $row['live_status'] ?? 'absent' }}" title="{{ $row['live_label'] ?? 'Absent' }}"></span>
                                </td>
                                <td class="es-shot-col">
                                    @if(!empty($row['last_image_thumb']))
                                    <a href="{{ $row['last_image_url'] }}" target="_blank" class="es-shot-thumb" title="{{ $row['last_image_label'] }}">
                                        <img src="{{ $row['last_image_thumb'] }}" alt="" loading="lazy">
                                    </a>
                                    <div class="es-shot-time">{{ $row['last_image_time'] }}</div>
                                    @else
                                    <span class="text-muted">—</span>
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
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
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
    const notLoggedBadge = document.getElementById('notLoggedBadge');
    const autoRefresh = document.getElementById('autoRefresh');
    const autoRefreshLabel = document.getElementById('autoRefreshLabel');
    let refreshTimer = null;
    let sortKey = 'name';
    let sortDir = 'asc';

    function currentStatus() {
        return statusFilter?.value || 'all';
    }

    function applyRowFilters() {
        const q = (searchInput?.value || '').trim().toLowerCase();
        const status = currentStatus();
        document.querySelectorAll('#summaryBody tr').forEach(tr => {
            const name = tr.dataset.name || '';
            const email = tr.dataset.email || '';
            const hasWorked = tr.dataset.hasWorked === '1';
            const matchesSearch = !q || name.includes(q) || email.includes(q);
            const matchesStatus = status === 'all'
                || (status === 'logged' && hasWorked)
                || (status === 'not_logged' && !hasWorked);
            tr.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
        });
        syncBadgeState();
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

    function syncBadgeState() {
        const isNotLogged = currentStatus() === 'not_logged';
        notLoggedBadge?.classList.toggle('is-active', isNotLogged);
        notLoggedBadge?.setAttribute('aria-pressed', isNotLogged ? 'true' : 'false');
    }

    function setStatusFilter(value) {
        if (statusFilter) {
            statusFilter.value = value;
        }
        applyRowFilters();
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

    filterForm?.querySelectorAll('select:not(#rangeSelect), input[type=date]').forEach(el => {
        el.addEventListener('change', () => filterForm?.submit());
    });

    toggleCustomRange();

    searchInput?.addEventListener('input', applyRowFilters);
    statusFilter?.addEventListener('change', applyRowFilters);

    notLoggedBadge?.addEventListener('click', function() {
        setStatusFilter(currentStatus() === 'not_logged' ? 'all' : 'not_logged');
    });

    function barColor(pct) {
        return pct >= 70 ? '#22c55e' : '#f97316';
    }

    function escapeAttr(value) {
        return String(value || '').replace(/[&<>"']/g, ch => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[ch]));
    }

    function lastImageCell(row) {
        if (!row.last_image_thumb) {
            return '<span class="text-muted">—</span>';
        }
        return '<a href="' + escapeAttr(row.last_image_url) + '" target="_blank" class="es-shot-thumb" title="' +
            escapeAttr(row.last_image_label) + '"><img src="' + escapeAttr(row.last_image_thumb) +
            '" alt="" loading="lazy"></a><div class="es-shot-time">' + escapeAttr(row.last_image_time) + '</div>';
    }

    function renderRows(rows) {
        const body = document.getElementById('summaryBody');
        if (!body) return;
        body.innerHTML = rows.map(row => `
            <tr data-name="${(row.name || '').toLowerCase()}" data-email="${(row.email || '').toLowerCase()}" data-has-worked="${row.has_worked ? '1' : '0'}" data-span="${row.activity_start_minutes ?? -1}" data-worked="${row.worked_seconds || 0}" data-meeting="${row.meeting_seconds || 0}" data-manual="${row.manual_seconds || 0}" data-active-min="${row.active_min_pct || 0}" data-active-sec="${row.active_sec_pct || 0}" data-idle="${row.idle_seconds || 0}" data-including="${row.work_time_seconds ?? row.including_idle_seconds ?? 0}" data-live="${row.live_sort ?? 3}" data-last-image="${row.last_image_sort || 0}">
                <td>
                    <div class="es-name">${row.name}</div>
                    <a href="${row.timeline_url}" class="es-timeline-link">Timeline</a>
                </td>
                <td class="es-live-col"><span class="es-live-status ${row.live_status || 'absent'}" title="${row.live_label || 'Absent'}"></span></td>
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
        try {
            const r = await fetch(root.dataset.refreshUrl + '?' + params.toString(), {
                headers: { Accept: 'application/json' },
            });
            if (!r.ok) return;
            const data = await r.json();
            document.getElementById('kpiWorked').textContent = data.totals.time_worked;
            document.getElementById('kpiActive').textContent = data.totals.timer_active;
            document.getElementById('kpiManual').textContent = data.totals.manual_entry;
            document.getElementById('kpiMeeting').textContent = data.totals.meeting_hours;
            document.getElementById('kpiIdle').textContent = data.totals.idle_time;
            document.getElementById('kpiEmployees').textContent = data.totals.employees_worked;
            renderRows(data.rows || []);
            if (notLoggedBadge) {
                notLoggedBadge.textContent = data.not_logged + '/' + data.total_employees + ' not logged';
                notLoggedBadge.classList.toggle('d-none', !(data.not_logged > 0));
                if (!(data.not_logged > 0) && currentStatus() === 'not_logged') {
                    setStatusFilter('all');
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
})();
</script>
@endsection
