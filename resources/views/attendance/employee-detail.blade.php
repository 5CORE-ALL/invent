@extends('layouts.vertical', ['title' => $title ?? 'Employee Activity'])

@section('css')
<style>
    .act-card { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
    .period-stats {
        display: grid;
        gap: .5rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        margin-bottom: 1rem;
    }
    @media (min-width: 576px) {
        .period-stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (min-width: 992px) {
        .period-stats { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    }
    @media (max-width: 575.98px) {
        .period-stat { padding: .65rem .75rem; }
        .period-stat .value { font-size: 1.1rem; }
        .period-stat .label { font-size: .65rem; }
    }
    .period-stat {
        border-radius: 10px; padding: .85rem 1rem; background: #f8fafc;
        border: 1px solid #e2e8f0; height: 100%; min-width: 0;
    }
    .period-stat .label { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; margin-bottom: .15rem; }
    .period-stat .value { font-size: 1.35rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    .period-stat.active .value { color: #16a34a; }
    .period-stat.idle .value { color: #dc2626; }
    .period-stat.break .value { color: #64748b; }
    .period-stat.total .value { color: #0f172a; }
    .period-stat.pct .value { color: #2563eb; }
    .day-focus-label { font-size: .78rem; color: #64748b; margin-bottom: .5rem; }
    .act-legend span { display: inline-flex; align-items: center; gap: .3rem; font-size: .72rem; color: #64748b; margin-right: .85rem; }
    .act-legend i { width: 10px; height: 10px; border-radius: 2px; display: inline-block; }
    .act-row-head { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .25rem; }
    .act-day { padding: .65rem 0; border-bottom: 1px solid #f1f5f9; }
    .act-day:last-child { border-bottom: 0; padding-bottom: 0; }
    .act-day-date { font-size: .82rem; font-weight: 600; color: #0f172a; white-space: nowrap; }
    .act-day-date .act-today { font-size: .65rem; font-weight: 600; color: #2563eb; margin-left: .35rem; }
    .act-clock { display: inline-flex; align-items: center; gap: .65rem; font-size: .75rem; font-weight: 500; color: #475569; margin-left: .75rem; }
    .act-clock .lbl { color: #94a3b8; font-weight: 600; font-size: .65rem; text-transform: uppercase; letter-spacing: .03em; margin-right: .15rem; }
    .act-clock .val { font-variant-numeric: tabular-nums; font-weight: 700; color: #0f172a; }
    .act-clock .val.is-live { color: #16a34a; }
    .act-clock .val.is-empty { color: #94a3b8; font-weight: 500; }
    .act-summary { display: flex; flex-wrap: wrap; gap: .85rem; font-size: .78rem; }
    .act-summary .item { white-space: nowrap; }
    .act-summary .item strong { font-weight: 700; }
    .act-summary .worked strong, .act-summary .worked span { color: #16a34a; }
    .act-summary .idle strong, .act-summary .idle span { color: #dc2626; }
    .act-summary .break strong, .act-summary .break span { color: #64748b; }
    .act-summary .total strong, .act-summary .total span { color: #0f172a; }
    @media (max-width: 768px) {
        .act-row-head { flex-direction: column; align-items: flex-start; }
    }
    .act-axis-times { display: flex; justify-content: space-between; font-size: .62rem; color: #94a3b8; margin-bottom: 1px; line-height: 1; }
    .act-track { position: relative; height: 22px; background: #fff; border: 1px solid #e2e8f0; border-radius: 2px; overflow: hidden; }
    .act-track-grid {
        position: absolute; inset: 0; z-index: 0; pointer-events: none;
        background: repeating-linear-gradient(90deg, transparent, transparent calc(16.666% - 1px), rgba(148,163,184,.14) calc(16.666% - 1px), rgba(148,163,184,.14) 16.666%);
    }
    .act-seg { position: absolute; top: 0; bottom: 0; min-width: 2px; z-index: 1; }
    .act-seg.idle, .act-seg.break { z-index: 2; }
    .act-apps { display: flex; flex-wrap: wrap; gap: .4rem; }
    .act-app-chip { font-size: .72rem; padding: .2rem .55rem; border-radius: 999px; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .act-app-chip strong { color: #0f172a; }
    .act-app-chip.warn { background: #fff7ed; border-color: #fdba74; color: #9a3412; }
    .act-app-chip.warn strong { color: #c2410c; }
    .act-app-meta { font-size: .68rem; color: #94a3b8; margin-top: .15rem; }
    .shot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: .75rem; }
    .shot-card {
        border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;
        background: #fff; text-decoration: none; color: inherit;
        transition: box-shadow .15s, border-color .15s;
    }
    .shot-card:hover { border-color: #94a3b8; box-shadow: 0 4px 12px rgba(0,0,0,.08); }
    .shot-card img { width: 100%; height: 120px; object-fit: cover; display: block; background: #f1f5f9; }
    .shot-body { padding: .4rem .5rem .45rem; }
    .shot-time { font-size: .72rem; font-weight: 700; color: #0f172a; }
    .shot-app { font-size: .68rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: .25rem; }
    .shot-bar { height: 4px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
    .shot-bar > span { display: block; height: 100%; border-radius: 999px; }
    .shot-pct { font-size: .65rem; color: #64748b; margin-top: .15rem; }
    .shot-loader {
        display: flex; align-items: center; justify-content: center; gap: .5rem;
        padding: 1rem; color: #64748b; font-size: .82rem;
    }
    .shot-loader .spinner {
        width: 22px; height: 22px;
        border: 2px solid #e2e8f0; border-top-color: var(--bs-primary, #0d6efd);
        border-radius: 50%; animation: shotSpin .7s linear infinite;
    }
    @keyframes shotSpin { to { transform: rotate(360deg); } }
    .shot-end { padding: .75rem; text-align: center; font-size: .78rem; color: #94a3b8; }
    .act-live-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; display: inline-block; margin-right: .2rem; animation: actPulse 2s infinite; vertical-align: middle; }
    @keyframes actPulse { 0%,100%{opacity:1} 50%{opacity:.35} }
    .shot-open-dot {
        width: 10px; height: 10px; border-radius: 50%; background: #0d9488;
        box-shadow: 0 0 0 3px rgba(13,148,136,.2); display: inline-block;
    }
</style>
@endsection

@section('content')
@php
    $periodStats = $period;
    $activityDays = $activity_days['days'] ?? [];
    $activityAxis = $activity_days['axis_hours'] ?? ($day['axis_hours'] ?? []);
    $activityRangeLabel = $activity_days['range_label'] ?? \Carbon\Carbon::parse($date)->format('D, M j, Y');
@endphp
<div class="container-fluid" id="employeeActivity"
     data-csrf="{{ csrf_token() }}">

    <div class="row mb-3">
        <div class="col-12">
            <div class="act-card p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <a href="{{ route('attendance.summary', array_filter(['team' => request('team'), 'executive' => request('executive'), 'range' => $period_key ?? 'custom', 'from' => $from, 'to' => $to, 'timezone' => $timezone, 'day_reset' => $day_reset])) }}" class="small text-muted">← Team Monitoring</a>
                        <h4 class="mb-0 mt-1">
                            @if($day['is_live'])
                                <span class="act-live-dot"></span>
                            @endif
                            {{ $employee->name }}
                        </h4>
                        <div class="text-muted small">{{ $employee->email }} · {{ $employee->designation ?? '—' }}</div>
                    </div>
                    <form method="get" class="d-flex flex-wrap align-items-end gap-2" id="filterForm">
                        <div>
                            <label class="form-label small text-muted mb-0">Period</label>
                            <select name="period" class="form-select form-select-sm" id="periodSelect">
                                @foreach($period_options as $opt)
                                <option value="{{ $opt['value'] }}" {{ ($period_key ?? 'today') === $opt['value'] ? 'selected' : '' }}>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="customRangeFields" class="d-flex flex-wrap align-items-end gap-2 {{ ($period_key ?? '') === 'custom' ? '' : 'd-none' }}">
                            <div>
                                <label class="form-label small text-muted mb-0">From</label>
                                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                            </div>
                            <div>
                                <label class="form-label small text-muted mb-0">To</label>
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
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnRefresh">
                            <i class="ri-refresh-line"></i> Refresh
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="period-stats">
        <div class="period-stat active">
            <div class="label">Total Active</div>
            <div class="value">{{ $periodStats['active_label'] }}</div>
        </div>
        <div class="period-stat idle">
            <div class="label">Total Idle</div>
            <div class="value">{{ $periodStats['idle_label'] }}</div>
        </div>
        <div class="period-stat break">
            <div class="label">Total Break</div>
            <div class="value">{{ $periodStats['break_label'] }}</div>
        </div>
        <div class="period-stat total">
            <div class="label">Total Time</div>
            <div class="value">{{ $periodStats['total_label'] }}</div>
        </div>
        <div class="period-stat pct">
            <div class="label">Active %</div>
            <div class="value">{{ $periodStats['active_percent'] }}%</div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-12">
            <div class="act-card p-3">
                <h6 class="mb-2"><i class="ri-apps-line me-1"></i> Desktop apps</h6>
                @if(count($desktop_apps) > 0)
                <div class="act-apps">
                    @foreach($desktop_apps as $app)
                    <span class="act-app-chip {{ $app['is_unproductive'] ? 'warn' : '' }}" title="{{ $app['top_window'] ? 'Top window: '.$app['top_window'] : '' }}">
                        <strong>{{ $app['app'] }}</strong>
                        · {{ $app['est_minutes'] }}m
                        · {{ $app['hits'] }} samples
                    </span>
                    @endforeach
                </div>
                @else
                <p class="text-muted small mb-0">No desktop app activity in this period. Data is collected by the desktop agent while clocked in.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <div class="act-card p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h6 class="mb-0">Activity</h6>
                    <div class="act-legend">
                        <span><i style="background:#22c55e"></i> Working</span>
                        <span><i style="background:#ef4444"></i> Idle</span>
                        <span><i style="background:#94a3b8"></i> Break</span>
                    </div>
                </div>

                <div class="day-focus-label">
                    Timeline for <strong>{{ $activityRangeLabel }}</strong>
                    @if($from !== $to)
                    · {{ count($activityDays) }} days
                    @endif
                    · {{ $day_reset }} to next day
                </div>

                <div class="act-axis-times mb-1">
                    @foreach($activityAxis as $hour)
                    <span>{{ $hour }}</span>
                    @endforeach
                </div>

                @forelse($activityDays as $activityDay)
                @php $dayStats = $activityDay['stats']; @endphp
                <div class="act-day">
                    <div class="act-row-head">
                        <div class="act-day-date">
                            @if($activityDay['is_live'])<span class="act-live-dot"></span>@endif
                            {{ $activityDay['date_label'] }}
                            @if(!empty($activityDay['is_today']))<span class="act-today">Today</span>@endif
                            <span class="act-clock">
                                <span><span class="lbl">In</span><span class="val {{ ($activityDay['login_label'] ?? '—') === '—' ? 'is-empty' : '' }}">{{ $activityDay['login_label'] ?? '—' }}</span></span>
                                <span><span class="lbl">Out</span><span class="val {{ ($activityDay['logout_label'] ?? '') === 'Live' ? 'is-live' : (($activityDay['logout_label'] ?? '—') === '—' ? 'is-empty' : '') }}">{{ $activityDay['logout_label'] ?? '—' }}</span></span>
                            </span>
                        </div>
                        <div class="act-summary">
                            <span class="item worked"><strong>{{ $dayStats['worked_label'] }}</strong> <span>Active</span></span>
                            <span class="item idle"><strong>{{ $dayStats['idle_label'] }}</strong> <span>Idle</span></span>
                            <span class="item break"><strong>{{ $dayStats['break_label'] }}</strong> <span>Break</span></span>
                            <span class="item total"><strong>{{ $dayStats['total_label'] }}</strong> <span>Total</span></span>
                        </div>
                    </div>
                    <div class="act-track">
                        <div class="act-track-grid" aria-hidden="true"></div>
                        @foreach($activityDay['segments'] as $seg)
                        @php
                            $color = $seg['color'] ?? ($seg['state'] === 'idle' ? '#ef4444' : ($seg['state'] === 'break' ? '#94a3b8' : '#22c55e'));
                        @endphp
                        <div class="act-seg {{ $seg['state'] }}"
                             style="left:{{ $seg['start_pct'] }}%;width:{{ max($seg['width_pct'], 0.12) }}%;background-color:{{ $color }}"
                             title="{{ $activityDay['date_label'] }} · {{ ucfirst($seg['state']) }} · {{ $seg['start_label'] }} – {{ $seg['end_label'] }}"></div>
                        @endforeach
                    </div>
                </div>
                @empty
                <p class="text-muted small mb-0">No activity days in this period.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="row mb-3" id="screenshots">
        <div class="col-12">
            <div class="act-card p-3">
                <a href="{{ url('/attendance/employee/'.$employee->id.'/captures').'?'.http_build_query(['date' => $date, 'timezone' => $timezone, 'day_reset' => $day_reset]) }}"
                   target="_blank" rel="noopener" class="d-inline-flex align-items-center gap-2 text-decoration-none">
                    <span class="shot-open-dot" aria-hidden="true"></span>
                    <span><i class="ri-camera-line me-1"></i> Open all screen captures</span>
                    <span class="text-muted fw-normal small">— {{ \Carbon\Carbon::parse($date)->format('M j, Y') }} (new tab)</span>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function() {
    const root = document.getElementById('employeeActivity');
    const csrf = root.dataset.csrf;

    document.getElementById('btnRefresh')?.addEventListener('click', function() {
        location.reload();
    });

    const periodSelect = document.getElementById('periodSelect');
    const customRangeFields = document.getElementById('customRangeFields');
    const filterForm = document.getElementById('filterForm');

    function toggleCustomRangeFields() {
        const isCustom = periodSelect?.value === 'custom';
        customRangeFields?.classList.toggle('d-none', !isCustom);
        customRangeFields?.classList.toggle('d-flex', isCustom);
    }

    periodSelect?.addEventListener('change', function() {
        toggleCustomRangeFields();
        if (this.value !== 'custom') {
            filterForm?.submit();
        }
    });

    filterForm?.querySelectorAll('select[name="day_reset"], select[name="timezone"]').forEach(el => {
        el.addEventListener('change', () => filterForm?.submit());
    });

    filterForm?.querySelectorAll('input[name="from"], input[name="to"]').forEach(el => {
        el.addEventListener('change', () => {
            if (periodSelect?.value === 'custom') {
                filterForm?.submit();
            }
        });
    });

    toggleCustomRangeFields();
})();
</script>
@endsection
