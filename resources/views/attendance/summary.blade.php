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
    .es-table tbody td { vertical-align: middle; }
    .es-name { font-weight: 600; color: #0f172a; }
    .es-timeline-link { font-size: .72rem; }
    .es-badge-auto {
        font-size: .62rem; font-weight: 700; letter-spacing: .04em;
        background: #1e293b; color: #fff; border-radius: 4px; padding: .1rem .35rem;
    }
    .es-span { font-size: .78rem; color: #475569; white-space: nowrap; }
    .es-span-updated { font-size: .68rem; color: #94a3b8; }
    .es-live-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; display: inline-block; }
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
    .es-not-logged { font-size: .75rem; color: #ea580c; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 999px; padding: .15rem .55rem; }
    .es-table-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        width: 100%;
        margin-bottom: 1rem;
    }
    .es-table-toolbar .es-search { max-width: 280px; width: 100%; flex: 0 1 280px; }
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
                            @foreach(['00:00' => '00:00 to 23:59', '04:00' => '04:00 to 03:59 Next day', '06:00' => '06:00 to 05:59 Next day', '09:00' => '09:00 to 08:59 Next day'] as $reset => $label)
                            <option value="{{ $reset }}" {{ $day_reset === $reset ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-0">Timezone</label>
                        <select name="timezone" class="form-select form-select-sm">
                            @foreach(['Asia/Kolkata' => 'GMT+0530', 'America/Los_Angeles' => 'GMT-0700', 'America/New_York' => 'GMT-0400', 'Asia/Shanghai' => 'GMT+0800'] as $tz => $label)
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
                    @if($not_logged > 0)
                    <span class="es-not-logged" id="notLoggedBadge">{{ $not_logged }}/{{ $total_employees }} not logged</span>
                    @endif
                </div>

                <div class="es-table-toolbar">
                    <input type="search" class="form-control form-control-sm es-search" id="employeeSearch" placeholder="Search employees...">
                    <a href="{{ route('attendance.summary.export', request()->query()) }}" class="btn btn-sm btn-primary flex-shrink-0" id="btnDownload">
                        <i class="ri-download-line me-1"></i> Download CSV
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover es-table" id="summaryTable">
                        <thead>
                            <tr>
                                <th style="width:16%">Employee</th>
                                <th style="width:12%">Activity Span</th>
                                <th style="width:9%">Total Time Worked</th>
                                <th style="width:8%">Meeting Hours</th>
                                <th style="width:9%">Manual Entry</th>
                                <th style="width:10%">% Active Minutes</th>
                                <th style="width:10%">% Active Seconds</th>
                                <th style="width:9%">Idle Deduction</th>
                                <th style="width:9%">Including Idle</th>
                            </tr>
                        </thead>
                        <tbody id="summaryBody">
                            @foreach($rows as $row)
                            <tr data-name="{{ strtolower($row['name']) }}" data-email="{{ strtolower($row['email']) }}">
                                <td>
                                    <div class="es-name">{{ $row['name'] }}</div>
                                    <a href="{{ $row['timeline_url'] }}" class="es-timeline-link">Timeline</a>
                                    <span class="es-badge-auto ms-1">{{ strtoupper($row['clock_source'] ?? 'auto') }}</span>
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
                                <td><span class="es-pill teal">{{ $row['meeting_clock'] }}</span></td>
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
                                <td><span class="es-pill blue-text">{{ $row['including_idle_clock'] }}</span></td>
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
    const autoRefresh = document.getElementById('autoRefresh');
    const autoRefreshLabel = document.getElementById('autoRefreshLabel');
    let refreshTimer = null;

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

    searchInput?.addEventListener('input', function() {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('#summaryBody tr').forEach(tr => {
            const name = tr.dataset.name || '';
            const email = tr.dataset.email || '';
            tr.style.display = (!q || name.includes(q) || email.includes(q)) ? '' : 'none';
        });
    });

    function barColor(pct) {
        return pct >= 70 ? '#22c55e' : '#f97316';
    }

    function renderRows(rows) {
        const body = document.getElementById('summaryBody');
        if (!body) return;
        body.innerHTML = rows.map(row => `
            <tr data-name="${(row.name || '').toLowerCase()}" data-email="${(row.email || '').toLowerCase()}">
                <td>
                    <div class="es-name">${row.name}</div>
                    <a href="${row.timeline_url}" class="es-timeline-link">Timeline</a>
                    <span class="es-badge-auto ms-1">${(row.clock_source || 'auto').toUpperCase()}</span>
                </td>
                <td>
                    <div class="es-span">${row.activity_is_live ? '<span class="es-live-dot me-1"></span>' : ''}${row.activity_span}</div>
                    ${row.activity_updated ? `<div class="es-span-updated">${row.activity_updated}</div>` : ''}
                </td>
                <td><span class="es-pill blue">${row.worked_clock}</span></td>
                <td><span class="es-pill teal">${row.meeting_clock}</span></td>
                <td>${row.manual_clock}</td>
                <td class="es-pct"><div>${row.active_min_pct}%</div><div class="bar"><span style="width:${row.active_min_pct}%;background:#22c55e"></span></div></td>
                <td class="es-pct"><div>${row.active_sec_pct}%</div><div class="bar"><span style="width:${row.active_sec_pct}%;background:${barColor(row.active_sec_pct)}"></span></div></td>
                <td><span class="es-pill red">${row.idle_clock}</span></td>
                <td><span class="es-pill blue-text">${row.including_idle_clock}</span></td>
            </tr>
        `).join('');
        if (searchInput?.value) {
            searchInput.dispatchEvent(new Event('input'));
        }
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
            const badge = document.getElementById('notLoggedBadge');
            if (badge && data.not_logged > 0) {
                badge.textContent = data.not_logged + '/' + data.total_employees + ' not logged';
                badge.classList.remove('d-none');
            } else if (badge) {
                badge.classList.add('d-none');
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
