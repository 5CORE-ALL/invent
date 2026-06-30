@extends('layouts.vertical', ['title' => $title ?? 'Employee Activity'])

@section('css')
<style>
    .act-card { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
    .act-range { background: #e8f4fd; border: 1px solid #b6d4fe; border-radius: 8px; padding: .5rem .85rem; font-size: .82rem; color: #1e40af; }
    .period-stat {
        border-radius: 10px; padding: .85rem 1rem; background: #f8fafc;
        border: 1px solid #e2e8f0; height: 100%;
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
    .act-summary { display: flex; flex-wrap: wrap; gap: .85rem; font-size: .78rem; }
    .act-summary .item { white-space: nowrap; }
    .act-summary .item strong { font-weight: 700; }
    .act-summary .worked strong, .act-summary .worked span { color: #16a34a; }
    .act-summary .idle strong, .act-summary .idle span { color: #dc2626; }
    .act-summary .break strong, .act-summary .break span { color: #64748b; }
    .act-summary .total strong, .act-summary .total span { color: #0f172a; }
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
    .flag-card { border-left: 3px solid #fd7e14; padding: .65rem .75rem; background: #fffbf5; border-radius: 6px; margin-bottom: .5rem; font-size: .82rem; }
    .flag-card.severity-high { border-left-color: #dc3545; background: #fff5f5; }
    .act-live-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; display: inline-block; margin-right: .2rem; animation: actPulse 2s infinite; vertical-align: middle; }
    @keyframes actPulse { 0%,100%{opacity:1} 50%{opacity:.35} }
</style>
@endsection

@section('content')
@php
    $stats = $day['stats'];
    $periodStats = $period;
@endphp
<div class="container-fluid" id="employeeActivity"
     data-csrf="{{ csrf_token() }}"
     data-analyze-url="{{ route('attendance.analyze', $employee) }}">

    <div class="row mb-3">
        <div class="col-12">
            <div class="act-card p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <a href="{{ route('attendance.monitor') }}?date={{ $date }}" class="small text-muted">← Team Timeline</a>
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
                                @foreach(['00:00','04:00','06:00','09:00'] as $reset)
                                <option value="{{ $reset }}" {{ $day_reset === $reset ? 'selected' : '' }}>{{ $reset }}</option>
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
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnRefresh">
                            <i class="ri-refresh-line"></i> Refresh
                        </button>
                        @if($can_admin)
                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnRunAi">
                            <i class="ri-robot-2-line"></i> AI
                        </button>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <div class="act-range">{{ $periodStats['range_label'] }} · {{ $periodStats['days_worked'] }} day(s) with activity</div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="period-stat active">
                <div class="label">Total Active</div>
                <div class="value">{{ $periodStats['active_label'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="period-stat idle">
                <div class="label">Total Idle</div>
                <div class="value">{{ $periodStats['idle_label'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="period-stat break">
                <div class="label">Total Break</div>
                <div class="value">{{ $periodStats['break_label'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="period-stat total">
                <div class="label">Total Time</div>
                <div class="value">{{ $periodStats['total_label'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="period-stat pct">
                <div class="label">Active %</div>
                <div class="value">{{ $periodStats['active_percent'] }}%</div>
            </div>
        </div>
        @if($periodStats['avg_productivity'] !== null)
        <div class="col-6 col-md-3">
            <div class="period-stat">
                <div class="label">Avg Productivity</div>
                <div class="value">{{ $periodStats['avg_productivity'] }}</div>
            </div>
        </div>
        @endif
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
                    @if($from === $to)
                    Timeline for <strong>{{ \Carbon\Carbon::parse($date)->format('D, M j, Y') }}</strong>
                    @else
                    Timeline for <strong>{{ \Carbon\Carbon::parse($date)->format('D, M j, Y') }}</strong>
                    <span class="text-muted">(latest day in range)</span>
                    @endif
                    · {{ $day['day_range_label'] }}
                </div>

                <div class="act-summary mb-2">
                    <span class="item worked"><strong>{{ $stats['worked_label'] }}</strong> <span>Active</span></span>
                    <span class="item idle"><strong>{{ $stats['idle_label'] }}</strong> <span>Idle</span></span>
                    <span class="item break"><strong>{{ $stats['break_label'] }}</strong> <span>Break</span></span>
                    <span class="item total"><strong>{{ $stats['total_label'] }}</strong> <span>Total</span></span>
                </div>

                <div class="act-axis-times">
                    @foreach($day['axis_hours'] as $hour)
                    <span>{{ $hour }}</span>
                    @endforeach
                </div>
                <div class="act-track">
                    <div class="act-track-grid" aria-hidden="true"></div>
                    @foreach($day['segments'] as $seg)
                    @php
                        $color = $seg['color'] ?? ($seg['state'] === 'idle' ? '#ef4444' : ($seg['state'] === 'break' ? '#94a3b8' : '#22c55e'));
                    @endphp
                    <div class="act-seg {{ $seg['state'] }}"
                         style="left:{{ $seg['start_pct'] }}%;width:{{ max($seg['width_pct'], 0.12) }}%;background-color:{{ $color }}"
                         title="{{ ucfirst($seg['state']) }} · {{ $seg['start_label'] }} – {{ $seg['end_label'] }}"></div>
                    @endforeach
                </div>

                @if(count($day['app_usage']) > 0)
                <div class="mt-3">
                    <div class="small text-muted mb-1">Apps on this day</div>
                    <div class="act-apps">
                        @foreach($day['app_usage'] as $app)
                        <span class="act-app-chip"><strong>{{ $app['app'] }}</strong> · {{ $app['hits'] }}</span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="row mb-3" id="screenshots">
        <div class="col-12">
            <div class="act-card p-3">
                <h6 class="mb-3"><i class="ri-camera-line me-1"></i> Screen captures
                    @if($from !== $to)
                    <span class="text-muted fw-normal small">— {{ \Carbon\Carbon::parse($date)->format('M j, Y') }}</span>
                    @endif
                    <span class="text-muted fw-normal" id="shotCountLabel">(<span id="shotLoadedCount">{{ count($day['screenshots']) }}</span>{{ ($day['screenshot_total'] ?? 0) > 0 ? ' of '.$day['screenshot_total'] : '' }} — latest first)</span>
                </h6>
                @if(($day['screenshot_total'] ?? 0) > 0)
                <div class="shot-grid" id="shotGrid"
                     data-url="{{ route('attendance.employee.screenshots', $employee) }}"
                     data-page="1"
                     data-has-more="{{ ($day['screenshot_has_more'] ?? false) ? '1' : '0' }}"
                     data-total="{{ $day['screenshot_total'] ?? 0 }}"
                     data-date="{{ $date }}"
                     data-timezone="{{ $timezone }}"
                     data-day-reset="{{ $day_reset }}">
                    @foreach($day['screenshots'] as $shot)
                    @php
                        $pct = $shot['active_percent'];
                        $barColor = $pct >= 70 ? '#22c55e' : ($pct >= 40 ? '#eab308' : '#94a3b8');
                    @endphp
                    <a href="{{ $shot['image_url'] }}" target="_blank" class="shot-card" title="{{ $shot['captured_label'] }} — {{ $shot['app'] }}">
                        <img src="{{ $shot['thumb_url'] ?? $shot['image_url'] }}" alt="" loading="lazy">
                        <div class="shot-body">
                            <div class="shot-time">{{ $shot['captured_at'] }}</div>
                            <div class="shot-app" title="{{ $shot['app'] }}">{{ $shot['app'] }}</div>
                            <div class="shot-bar"><span style="width:{{ $pct }}%;background:{{ $barColor }}"></span></div>
                            <div class="shot-pct">{{ $pct }}% active</div>
                        </div>
                    </a>
                    @endforeach
                </div>
                <div id="shotLoader" class="shot-loader d-none" aria-live="polite">
                    <span class="spinner" aria-hidden="true"></span>
                    <span>Loading more…</span>
                </div>
                <div id="shotSentinel" style="height:1px"></div>
                <div id="shotEnd" class="shot-end {{ ($day['screenshot_has_more'] ?? false) ? 'd-none' : '' }}">All screenshots loaded</div>
                @else
                <p class="text-muted small mb-0" id="shotEmpty">No screenshots for this day. The desktop agent captures screens while the employee is clocked in.</p>
                @endif
            </div>
        </div>
    </div>

    @if($flags->isNotEmpty())
    <div class="row">
        <div class="col-12">
            <div class="act-card p-3">
                <h6 class="mb-3">Flags in this period</h6>
                @foreach($flags as $flag)
                <div class="flag-card severity-{{ $flag->severity }}">
                    <div class="d-flex justify-content-between">
                        <strong>{{ $flag->title }}</strong>
                        <span class="badge bg-{{ $flag->status === 'open' ? 'warning' : 'secondary' }}">{{ $flag->status }}</span>
                    </div>
                    <div class="text-muted small">{{ $flag->typeLabel() }} · {{ $flag->source }}</div>
                    @if($flag->description)
                    <p class="mb-1 mt-1">{{ $flag->description }}</p>
                    @endif
                    @if($can_admin && $flag->status === 'open')
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-success btn-review" data-id="{{ $flag->id }}" data-status="reviewed">Reviewed</button>
                        <button class="btn btn-outline-secondary btn-review" data-id="{{ $flag->id }}" data-status="dismissed">Dismiss</button>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
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

    document.getElementById('btnRunAi')?.addEventListener('click', async function() {
        const url = root.dataset.analyzeUrl + '?date={{ $date }}';
        const r = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }});
        const data = await r.json();
        if (data.ok) { alert('AI analysis complete. Risk score: ' + data.risk_score); location.reload(); }
        else alert('Analysis failed');
    });

    document.querySelectorAll('.btn-review').forEach(btn => {
        btn.addEventListener('click', async function() {
            await fetch('/attendance/flags/' + this.dataset.id + '/review', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ status: this.dataset.status })
            });
            location.reload();
        });
    });

    const shotGrid = document.getElementById('shotGrid');
    const shotLoader = document.getElementById('shotLoader');
    const shotSentinel = document.getElementById('shotSentinel');
    const shotEnd = document.getElementById('shotEnd');
    const shotLoadedCount = document.getElementById('shotLoadedCount');

    if (shotGrid && shotSentinel) {
        let loading = false;

        function barColor(pct) {
            return pct >= 70 ? '#22c55e' : (pct >= 40 ? '#eab308' : '#94a3b8');
        }

        function shotCardHtml(shot) {
            const pct = shot.active_percent || 0;
            const color = barColor(pct);
            const img = shot.thumb_url || shot.image_url;
            return '<a href="' + shot.image_url + '" target="_blank" class="shot-card" title="' +
                shot.captured_label + ' — ' + shot.app + '">' +
                '<img src="' + img + '" alt="" loading="lazy">' +
                '<div class="shot-body">' +
                '<div class="shot-time">' + shot.captured_at + '</div>' +
                '<div class="shot-app" title="' + shot.app + '">' + shot.app + '</div>' +
                '<div class="shot-bar"><span style="width:' + pct + '%;background:' + color + '"></span></div>' +
                '<div class="shot-pct">' + pct + '% active</div>' +
                '</div></a>';
        }

        async function loadMoreShots() {
            if (loading || shotGrid.dataset.hasMore !== '1') return;
            loading = true;
            shotLoader?.classList.remove('d-none');

            const nextPage = parseInt(shotGrid.dataset.page || '1', 10) + 1;
            const params = new URLSearchParams({
                page: String(nextPage),
                date: shotGrid.dataset.date || '',
                timezone: shotGrid.dataset.timezone || '',
                day_reset: shotGrid.dataset.dayReset || '',
            });

            try {
                const r = await fetch(shotGrid.dataset.url + '?' + params.toString(), {
                    headers: { Accept: 'application/json' },
                });
                if (!r.ok) return;
                const data = await r.json();
                if (data.screenshots?.length) {
                    shotGrid.insertAdjacentHTML('beforeend', data.screenshots.map(shotCardHtml).join(''));
                    shotGrid.dataset.page = String(data.page);
                    if (shotLoadedCount) {
                        shotLoadedCount.textContent = String(shotGrid.querySelectorAll('.shot-card').length);
                    }
                }
                shotGrid.dataset.hasMore = data.has_more ? '1' : '0';
                if (!data.has_more) {
                    shotEnd?.classList.remove('d-none');
                }
            } catch (_) {}
            finally {
                loading = false;
                shotLoader?.classList.add('d-none');
            }
        }

        const observer = new IntersectionObserver((entries) => {
            if (entries.some(e => e.isIntersecting)) {
                loadMoreShots();
            }
        }, { rootMargin: '200px' });

        observer.observe(shotSentinel);
    }
})();
</script>
@endsection
