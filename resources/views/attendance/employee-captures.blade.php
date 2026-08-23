@extends('layouts.vertical', ['title' => $title ?? 'Screen captures'])

@section('css')
<style>
    .shot-page-card { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
    .shot-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: .75rem; overflow: visible; }
    .shot-card {
        border: 1px solid #e2e8f0; border-radius: 8px; overflow: visible;
        background: #fff; text-decoration: none; color: inherit;
        transition: box-shadow .15s, border-color .15s;
        position: relative;
    }
    .shot-card:hover { z-index: 20; }
    .shot-card-frame {
        overflow: hidden; border-radius: 8px 8px 0 0; background: #f1f5f9;
        border-bottom: 1px solid #e2e8f0;
    }
    .shot-card img {
        width: 100%; height: 120px; object-fit: cover; display: block;
        transform: scale(0.8);
        transform-origin: center center;
        transition: transform .18s ease;
    }
    .shot-card:hover img { transform: scale(2); }
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
    .shot-dot {
        width: 10px; height: 10px; border-radius: 50%; background: #0d9488;
        box-shadow: 0 0 0 3px rgba(13,148,136,.2);
        display: inline-block; vertical-align: middle; margin-right: .35rem;
    }
</style>
@endsection

@section('content')
<div class="container-fluid" id="employeeCaptures">
    <div class="row mb-3">
        <div class="col-12">
            <div class="shot-page-card p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <a href="{{ route('attendance.summary') }}" class="small text-muted">← Team Monitoring</a>
                        <h4 class="mb-0 mt-1">{{ $employee->name }}</h4>
                        <div class="text-muted small">{{ $employee->email }} · {{ $employee->designation ?? '—' }}</div>
                    </div>
                    <div class="d-flex flex-wrap align-items-end gap-2">
                        <a href="{{ $timeline_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                            Open timeline
                        </a>
                        <form method="get" class="d-flex flex-wrap align-items-end gap-2">
                            <input type="hidden" name="timezone" value="{{ $timezone }}">
                            <input type="hidden" name="day_reset" value="{{ $day_reset }}">
                            <div>
                                <label class="form-label small text-muted mb-0">Date</label>
                                <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm" onchange="this.form.submit()">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <div class="shot-page-card p-3">
                <h6 class="mb-3">
                    <span class="shot-dot" aria-hidden="true"></span>
                    <i class="ri-camera-line me-1"></i> Screen captures
                    <span class="text-muted fw-normal small">— {{ \Carbon\Carbon::parse($date)->format('M j, Y') }}</span>
                    <span class="text-muted fw-normal">(<span id="shotLoadedCount">{{ count($screenshots) }}</span>{{ ($screenshot_total ?? 0) > 0 ? ' of '.$screenshot_total : '' }} — latest first)</span>
                </h6>
                @if(($screenshot_total ?? 0) > 0)
                <div class="shot-grid" id="shotGrid"
                     data-url="{{ $shots_api }}"
                     data-page="1"
                     data-has-more="{{ ($screenshot_has_more ?? false) ? '1' : '0' }}"
                     data-total="{{ $screenshot_total ?? 0 }}"
                     data-date="{{ $date }}"
                     data-timezone="{{ $timezone }}"
                     data-day-reset="{{ $day_reset }}">
                    @foreach($screenshots as $shot)
                    @php
                        $pct = $shot['active_percent'];
                        $barColor = $pct >= 70 ? '#22c55e' : ($pct >= 40 ? '#eab308' : '#94a3b8');
                    @endphp
                    <a href="{{ $shot['image_url'] }}" target="_blank" rel="noopener" class="shot-card" title="{{ $shot['captured_label'] }} — {{ $shot['app'] }}">
                        <div class="shot-card-frame">
                            <img src="{{ $shot['thumb_url'] ?? $shot['image_url'] }}" alt="" loading="lazy">
                        </div>
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
                <div id="shotEnd" class="shot-end {{ ($screenshot_has_more ?? false) ? 'd-none' : '' }}">All screenshots loaded</div>
                @else
                <p class="text-muted small mb-0">No screenshots for this day. The desktop agent captures screens while the employee is clocked in.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function() {
    const shotGrid = document.getElementById('shotGrid');
    const shotLoader = document.getElementById('shotLoader');
    const shotSentinel = document.getElementById('shotSentinel');
    const shotEnd = document.getElementById('shotEnd');
    const shotLoadedCount = document.getElementById('shotLoadedCount');

    if (!shotGrid || !shotSentinel) return;

    let loading = false;

    function barColor(pct) {
        return pct >= 70 ? '#22c55e' : (pct >= 40 ? '#eab308' : '#94a3b8');
    }

    function shotCardHtml(shot) {
        const pct = shot.active_percent || 0;
        const img = shot.thumb_url || shot.image_url;
        return '<a href="' + shot.image_url + '" target="_blank" rel="noopener" class="shot-card" title="' +
            shot.captured_label + ' — ' + shot.app + '">' +
            '<div class="shot-card-frame"><img src="' + img + '" alt="" loading="lazy"></div>' +
            '<div class="shot-body">' +
            '<div class="shot-time">' + shot.captured_at + '</div>' +
            '<div class="shot-app" title="' + shot.app + '">' + shot.app + '</div>' +
            '<div class="shot-bar"><span style="width:' + pct + '%;background:' + barColor(pct) + '"></span></div>' +
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
            if (!data.has_more) shotEnd?.classList.remove('d-none');
        } catch (_) {}
        finally {
            loading = false;
            shotLoader?.classList.add('d-none');
        }
    }

    new IntersectionObserver((entries) => {
        if (entries.some(e => e.isIntersecting)) loadMoreShots();
    }, { rootMargin: '200px' }).observe(shotSentinel);
})();
</script>
@endsection
