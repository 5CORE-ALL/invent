@extends('layouts.vertical', ['title' => $title ?? 'Team Timeline'])

@section('css')
<style>
    .tl-card { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
    .tl-toolbar .form-select, .tl-toolbar .form-control { min-height: 34px; font-size: .85rem; }
    .tl-search { max-width: 280px; }
    .tl-legend span { display: inline-flex; align-items: center; gap: .35rem; font-size: .75rem; color: #64748b; margin-right: 1rem; }
    .tl-legend i { width: 12px; height: 12px; border-radius: 3px; display: inline-block; }
    .tl-row {
        padding: .55rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        transition: background .15s;
    }
    .tl-row:last-child { border-bottom: 0; }
    .tl-row:hover { background: #f8fafc; }
    .tl-row-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: .2rem;
    }
    .tl-row.is-live .tl-name { font-weight: 700; }
    .tl-name {
        font-size: .95rem;
        line-height: 1.25;
        color: var(--bs-primary, #2563eb);
        text-decoration: underline;
        text-decoration-color: rgba(37, 99, 235, .35);
        text-underline-offset: 2px;
        flex-shrink: 0;
        cursor: pointer;
    }
    .tl-name:hover {
        color: #1d4ed8;
        text-decoration-color: currentColor;
    }
    .tl-summary {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: flex-end;
        gap: .9rem;
        font-size: .78rem;
        min-width: 0;
    }
    .tl-summary .item { display: inline-flex; align-items: baseline; gap: .2rem; white-space: nowrap; }
    .tl-summary .item strong { font-weight: 700; font-variant-numeric: tabular-nums; }
    .tl-summary .item.worked strong, .tl-summary .item.worked span { color: #16a34a; }
    .tl-summary .item.idle strong, .tl-summary .item.idle span { color: #dc2626; }
    .tl-summary .item.break strong, .tl-summary .item.break span { color: #64748b; }
    .tl-summary .item.total strong, .tl-summary .item.total span { color: #0f172a; }
    .tl-axis-times {
        display: flex;
        justify-content: space-between;
        font-size: .62rem;
        color: #94a3b8;
        margin-bottom: 1px;
        padding: 0 1px;
        line-height: 1;
    }
    .tl-track {
        position: relative;
        height: 18px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 2px;
        overflow: hidden;
    }
    .tl-track-grid {
        position: absolute; inset: 0; z-index: 0; pointer-events: none;
        background: repeating-linear-gradient(90deg, transparent, transparent calc(16.666% - 1px), rgba(148,163,184,.14) calc(16.666% - 1px), rgba(148,163,184,.14) 16.666%);
    }
    .tl-seg {
        position: absolute; top: 0; bottom: 0;
        min-width: 2px;
        z-index: 1;
    }
    .tl-seg.idle, .tl-seg.break { z-index: 2; }
    .tl-live-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; display: inline-block; margin-right: .25rem; animation: tlPulse 2s infinite; vertical-align: middle; }
    .tl-live-dot.idle { background: #f97316; }
    .tl-live-dot.break { background: #94a3b8; animation: none; }
    @keyframes tlPulse { 0%,100%{opacity:1} 50%{opacity:.35} }
    .tl-empty { padding: 2.5rem; text-align: center; color: #94a3b8; }
    @media (max-width: 768px) {
        .tl-row-head { flex-direction: column; align-items: flex-start; }
        .tl-summary { justify-content: flex-start; flex-wrap: wrap; }
    }
</style>
@endsection

@section('content')
<div class="container-fluid" id="teamTimelineApp"
     data-team-data-url="{{ route('attendance.monitor.team-data') }}"
     data-date="{{ $date }}"
     data-team="{{ $team }}"
     data-timezone="{{ $timezone }}"
     data-day-reset="{{ $day_reset }}">

    <div class="row mb-3">
        <div class="col-12">
            <div class="tl-card p-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h4 class="mb-1"><i class="ri-time-line me-2 text-primary"></i>Team Timeline</h4>
                    </div>
                    @if($can_admin)
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('attendance.policies') }}" class="btn btn-sm btn-outline-secondary">Policies</a>
                    </div>
                    @endif
                </div>

                <form method="get" class="tl-toolbar d-flex flex-wrap align-items-end gap-2 mb-3" id="tlFilters">
                    <div>
                        <label class="form-label small text-muted mb-0">Day</label>
                        <input type="date" name="date" class="form-control form-control-sm" value="{{ $date }}">
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-0">Team</label>
                        <select name="team" class="form-select form-select-sm">
                            <option value="all" {{ $team === 'all' ? 'selected' : '' }}>All</option>
                            @foreach($teams as $t)
                            <option value="{{ $t }}" {{ $team === $t ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-0">Timezone</label>
                        <select name="timezone" class="form-select form-select-sm">
                            @foreach(['Asia/Kolkata' => 'GMT+0530 India', 'America/Los_Angeles' => 'GMT-0700 California', 'America/New_York' => 'GMT-0400 Ohio', 'Asia/Shanghai' => 'GMT+0800 China'] as $tz => $label)
                            <option value="{{ $tz }}" {{ $timezone === $tz ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-0">Day reset</label>
                        <select name="day_reset" class="form-select form-select-sm">
                            @foreach(['00:00','04:00','06:00','09:00'] as $reset)
                            <option value="{{ $reset }}" {{ $day_reset === $reset ? 'selected' : '' }}>{{ $reset }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="ri-refresh-line"></i></button>
                </form>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div class="tl-legend">
                        <span><i style="background:#22c55e"></i> Working</span>
                        <span><i style="background:#ef4444"></i> Idle</span>
                        <span><i style="background:#94a3b8"></i> Break</span>
                    </div>
                    <input type="search" class="form-control form-control-sm tl-search" id="tlSearch" placeholder="Search employee…">
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="tl-card">
                <div id="tlRows">
                @forelse($timeline['rows'] as $row)
                    <div class="tl-row {{ $row['is_live'] ? 'is-live' : '' }}" data-user-id="{{ $row['user_id'] }}" data-name="{{ strtolower($row['name'].' '.$row['email']) }}">
                        <div class="tl-row-head">
                            <a href="{{ $row['detail_url'] }}" class="tl-name" title="{{ $row['name'] }}">
                                @if($row['is_live'])
                                    <span class="tl-live-dot {{ $row['live_state'] === 'idle' ? 'idle' : ($row['live_state'] === 'break' ? 'break' : '') }}"></span>
                                @endif
                                {{ $row['name'] }}
                            </a>
                            <div class="tl-summary">
                                <span class="item worked"><strong>{{ $row['stats']['worked_label'] }}</strong> <span>Worked</span></span>
                                <span class="item idle"><strong>{{ $row['stats']['idle_label'] }}</strong> <span>Idle</span></span>
                                <span class="item break"><strong>{{ $row['stats']['break_label'] }}</strong> <span>Break</span></span>
                                <span class="item total"><strong>{{ $row['stats']['total_label'] }}</strong> <span>Total</span></span>
                            </div>
                        </div>
                        <div class="tl-timeline">
                            <div class="tl-axis-times">
                                @foreach($timeline['axis_hours'] as $hour)
                                <span>{{ $hour }}</span>
                                @endforeach
                            </div>
                            <div class="tl-track">
                                <div class="tl-track-grid" aria-hidden="true"></div>
                                @foreach($row['segments'] as $seg)
                                @php
                                    $color = $seg['color'] ?? ($seg['state'] === 'idle' ? '#ef4444' : ($seg['state'] === 'break' ? '#94a3b8' : '#22c55e'));
                                @endphp
                                <div class="tl-seg {{ $seg['state'] }}"
                                     style="left:{{ $seg['start_pct'] }}%;width:{{ max($seg['width_pct'], 0.12) }}%;background-color:{{ $color }}"
                                     title="{{ ucfirst($seg['state']) }} · {{ $seg['start_label'] }} – {{ $seg['end_label'] }}"></div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="tl-empty">No employees match the current filters.</div>
                @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function() {
    const colors = { working: '#22c55e', idle: '#ef4444', break: '#94a3b8' };

    const search = document.getElementById('tlSearch');
    const rows = document.querySelectorAll('#tlRows .tl-row');
    search?.addEventListener('input', function() {
        const q = this.value.trim().toLowerCase();
        rows.forEach(row => {
            const name = row.dataset.name || '';
            row.style.display = !q || name.includes(q) ? '' : 'none';
        });
    });

    function summaryHtml(s) {
        return '<span class="item worked"><strong>' + s.worked_label + '</strong> <span>Worked</span></span>' +
            '<span class="item idle"><strong>' + s.idle_label + '</strong> <span>Idle</span></span>' +
            '<span class="item break"><strong>' + s.break_label + '</strong> <span>Break</span></span>' +
            '<span class="item total"><strong>' + s.total_label + '</strong> <span>Total</span></span>';
    }

    function segmentsHtml(segments) {
        return segments.map(seg => {
            const color = seg.color || colors[seg.state] || colors.working;
            const w = Math.max(seg.width_pct, 0.12);
            return '<div class="tl-seg ' + seg.state + '" style="left:' + seg.start_pct + '%;width:' + w + '%;background-color:' + color + '" title="' +
                seg.state.charAt(0).toUpperCase() + seg.state.slice(1) + ' · ' + seg.start_label + ' – ' + seg.end_label + '"></div>';
        }).join('');
    }

    const app = document.getElementById('teamTimelineApp');
    const isToday = app.dataset.date === new Date().toISOString().slice(0, 10);
    if (!isToday) return;

    const url = app.dataset.teamDataUrl
        + '?date=' + encodeURIComponent(app.dataset.date)
        + '&team=' + encodeURIComponent(app.dataset.team)
        + '&timezone=' + encodeURIComponent(app.dataset.timezone)
        + '&day_reset=' + encodeURIComponent(app.dataset.dayReset);

    async function refreshTimeline() {
        try {
            const r = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!r.ok) return;
            const data = await r.json();
            data.rows?.forEach(row => {
                const el = document.querySelector('#tlRows .tl-row[data-user-id="' + row.user_id + '"]');
                if (!el) return;
                const track = el.querySelector('.tl-track');
                if (track) {
                    track.innerHTML = '<div class="tl-track-grid" aria-hidden="true"></div>' + segmentsHtml(row.segments || []);
                }
                const summary = el.querySelector('.tl-summary');
                if (summary) summary.innerHTML = summaryHtml(row.stats);
            });
        } catch (_) {}
    }

    setInterval(refreshTimeline, 30000);
})();
</script>
@endsection
