@extends('layouts.vertical', ['title' => 'Feedback', 'sidenav' => 'condensed'])

@section('css')
<style>
    .dfb-lead {
        color: #64748b;
        font-size: 13px;
        margin: 2px 0 0;
    }
    .dfb-week {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }
    .dfb-card {
        text-align: left;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 10px 10px 12px;
        min-height: 118px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        cursor: pointer;
        width: 100%;
        color: inherit;
    }
    .dfb-card:hover {
        border-color: #cbd5e1;
    }
    .dfb-card.is-today {
        border-color: var(--dfb-accent, #4338ca);
        box-shadow: 0 0 0 1px var(--dfb-accent, #4338ca);
    }
    .dfb-card.is-selected {
        background: #f8fafc;
    }
    .dfb-day {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #94a3b8;
    }
    .dfb-name {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-top: 6px;
        font-weight: 700;
        font-size: 13px;
        color: #0f172a;
        line-height: 1.2;
    }
    .dfb-name i {
        color: var(--dfb-accent, #4338ca);
        font-size: 16px;
    }
    .dfb-status {
        display: inline-block;
        margin-top: 8px;
        border-radius: 999px;
        padding: 2px 8px;
        font-size: 11px;
        font-weight: 700;
        background: #f1f5f9;
        color: #475569;
    }
    .dfb-status.is-sent { background: #ecfdf5; color: #047857; }
    .dfb-status.is-today { background: #eef2ff; color: #3730a3; }
    .dfb-status.is-open { background: #fff7ed; color: #c2410c; }
    .dfb-meta {
        margin-top: 6px;
        font-size: 11px;
        color: #94a3b8;
    }
    .dfb-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px;
        height: 100%;
    }
    .dfb-panel h5 {
        font-size: 15px;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .dfb-panel .dfb-hint {
        color: #64748b;
        font-size: 12px;
        margin-bottom: 14px;
    }
    .dfb-stars {
        display: flex;
        gap: 8px;
    }
    .dfb-stars label {
        width: 42px;
        height: 40px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        display: grid;
        place-items: center;
        cursor: pointer;
        color: #cbd5e1;
        font-size: 18px;
        background: #fff;
        margin: 0;
    }
    .dfb-stars input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .dfb-stars label.is-on {
        color: #d97706;
        border-color: #fcd34d;
        background: #fffbeb;
    }
    .dfb-scale {
        display: flex;
        justify-content: space-between;
        max-width: 250px;
        color: #94a3b8;
        font-size: 11px;
        font-weight: 600;
        margin: 4px 0 12px;
    }
    .dfb-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 12px;
    }
    .dfb-filters a {
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        text-decoration: none;
        background: #fff;
    }
    .dfb-filters a.is-active {
        background: #0f172a;
        border-color: #0f172a;
        color: #fff;
    }
    .dfb-item {
        border-top: 1px solid #f1f5f9;
        padding: 12px 0;
    }
    .dfb-item:first-of-type { border-top: 0; padding-top: 0; }
    .dfb-item-top {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        align-items: baseline;
    }
    .dfb-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        font-weight: 700;
        color: var(--dfb-accent, #4338ca);
    }
    .dfb-item p {
        margin: 6px 0 0;
        color: #334155;
        font-size: 13px;
        line-height: 1.45;
        white-space: pre-wrap;
    }
    .dfb-who {
        color: #94a3b8;
        font-size: 12px;
    }
    .dfb-stars-inline { color: #d97706; letter-spacing: 1px; font-size: 12px; }
    @media (max-width: 1199px) {
        .dfb-week { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
    @media (max-width: 767px) {
        .dfb-week { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
</style>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title mb-0">
                    <i class="ri-feedback-line me-1 text-primary"></i>Feedback
                </h4>
                <p class="dfb-lead">
                    Each department is asked in its own popup, once a week.
                    This week is {{ $weekStart->format('M j') }} – {{ $weekEnd->format('M j') }}.
                </p>
            </div>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success py-2">{{ session('status') }}</div>
    @endif

    <div class="dfb-week">
        @foreach($departments as $key => $meta)
            @php
                $row = $responses->get($key);
                $stat = $stats->get($key);
                $isToday = $key === $todayKey;
                $isPast = $meta['weekday'] < $todayIso;
                if ($row && ! $row->skipped && $row->rating) {
                    $status = 'Sent';
                    $statusClass = 'is-sent';
                } elseif ($row && $row->skipped) {
                    $status = 'Skipped';
                    $statusClass = '';
                } elseif ($isToday) {
                    $status = 'Ask today';
                    $statusClass = 'is-today';
                } elseif ($isPast) {
                    $status = 'Still open';
                    $statusClass = 'is-open';
                } else {
                    $status = $weekdays[$meta['weekday']];
                    $statusClass = '';
                }
            @endphp
            <button type="button"
                    class="dfb-card {{ $isToday ? 'is-today' : '' }} {{ $selected === $key ? 'is-selected' : '' }}"
                    style="--dfb-accent: {{ $meta['accent'] }}"
                    data-dept="{{ $key }}">
                <div class="dfb-day">{{ $weekdays[$meta['weekday']] }}</div>
                <div class="dfb-name"><i class="{{ $meta['icon'] }}"></i>{{ $meta['label'] }}</div>
                <span class="dfb-status {{ $statusClass }}">{{ $status }}</span>
                <div class="dfb-meta">
                    @if($stat)
                        {{ number_format((float) $stat->avg_rating, 1) }} avg · {{ (int) $stat->total }} this week
                    @else
                        No team ratings yet
                    @endif
                </div>
            </button>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="dfb-panel">
                <h5>Send feedback</h5>
                <p class="dfb-hint" id="dfbQuestion">{{ $departments[$selected]['question'] }}</p>
                <form method="POST" action="{{ route('feedback.store') }}" id="dfbPageForm">
                    @csrf
                    <div class="mb-3">
                        <label for="dfbDepartment" class="form-label">Department</label>
                        <select class="form-select" id="dfbDepartment" name="department">
                            @foreach($departments as $key => $meta)
                                <option value="{{ $key }}" @selected($selected === $key)>
                                    {{ $meta['label'] }} · {{ $weekdays[$meta['weekday']] }}
                                </option>
                            @endforeach
                        </select>
                        @error('department')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-1">Rating</div>
                    <div class="dfb-stars" id="dfbPageStars">
                        @for($star = 1; $star <= 5; $star++)
                            <label class="{{ $ratingValue >= $star ? 'is-on' : '' }}" title="{{ $star }} of 5">
                                <input type="radio" name="rating" value="{{ $star }}" @checked($ratingValue === $star)>
                                <i class="ri-star-fill"></i>
                            </label>
                        @endfor
                    </div>
                    <div class="dfb-scale"><span>Needs work</span><span>Excellent</span></div>
                    @error('rating')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                    <div class="mb-3">
                        <label for="dfbComment" class="form-label">Note</label>
                        <textarea class="form-control" id="dfbComment" name="comment" rows="4" maxlength="2000" required placeholder="What should this department keep doing, or change?">{{ $commentValue }}</textarea>
                        @error('comment')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Save this week</button>
                        <button type="submit" class="btn btn-light" form="dfbSkipForm">Skip this week</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('feedback.skip') }}" id="dfbSkipForm">
                    @csrf
                    <input type="hidden" name="department" id="dfbSkipDepartment" value="{{ $selected }}">
                </form>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="dfb-panel">
                <h5>Recent feedback</h5>
                <p class="dfb-hint">Notes people have already sent. Skipped weeks stay private.</p>
                <div class="dfb-filters">
                    <a href="{{ route('feedback.index') }}" class="{{ $filter ? '' : 'is-active' }}">All</a>
                    @foreach($departments as $key => $meta)
                        <a href="{{ route('feedback.index', ['department' => $key]) }}" class="{{ $filter === $key ? 'is-active' : '' }}">{{ $meta['label'] }}</a>
                    @endforeach
                </div>
                @if(!$feed || $feed->isEmpty())
                    <p class="text-muted mb-0">No feedback yet{{ $filter ? ' for this department' : '' }}.</p>
                @else
                    @foreach($feed as $item)
                        @php $meta = $departments[$item->department] ?? null; @endphp
                        <article class="dfb-item">
                            <div class="dfb-item-top">
                                <span class="dfb-pill" @if($meta) style="--dfb-accent: {{ $meta['accent'] }}" @endif>
                                    @if($meta)<i class="{{ $meta['icon'] }}"></i>@endif
                                    {{ $meta['label'] ?? $item->department }}
                                </span>
                                <span class="dfb-stars-inline">{{ str_repeat('★', (int) $item->rating) }}{{ str_repeat('☆', 5 - (int) $item->rating) }}</span>
                            </div>
                            <p>{{ $item->comment }}</p>
                            <div class="dfb-who">
                                {{ $item->user->name ?? 'Unknown' }}
                                · week of {{ optional($item->week_start)->format('M j') }}
                                · {{ optional($item->created_at)->diffForHumans() }}
                            </div>
                        </article>
                    @endforeach
                    <div class="mt-2">{{ $feed->links('pagination::bootstrap-5') }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('script-after-vite')
<script>
(function () {
    var mine = @json($mine);
    var questions = @json($questions);
    var select = document.getElementById('dfbDepartment');
    var stars = document.getElementById('dfbPageStars');
    var comment = document.getElementById('dfbComment');
    var question = document.getElementById('dfbQuestion');
    var labels = stars ? Array.prototype.slice.call(stars.querySelectorAll('label')) : [];

    function paint(value) {
        labels.forEach(function (label, index) {
            label.classList.toggle('is-on', index < value);
        });
    }
    labels.forEach(function (label, index) {
        var input = label.querySelector('input');
        label.addEventListener('mouseenter', function () { paint(index + 1); });
        label.addEventListener('mouseleave', function () {
            var checked = stars.querySelector('input:checked');
            paint(checked ? parseInt(checked.value, 10) : 0);
        });
        if (input) input.addEventListener('change', function () { paint(parseInt(input.value, 10)); });
    });

    function applyDepartment(key) {
        var saved = mine[key] || {};
        var rating = saved.rating ? parseInt(saved.rating, 10) : 0;
        labels.forEach(function (label) {
            var input = label.querySelector('input');
            if (input) input.checked = parseInt(input.value, 10) === rating;
        });
        paint(rating);
        comment.value = saved.comment || '';
        var skipDepartment = document.getElementById('dfbSkipDepartment');
        if (skipDepartment) skipDepartment.value = key;
        if (questions[key]) question.textContent = questions[key];
        document.querySelectorAll('.dfb-card').forEach(function (card) {
            card.classList.toggle('is-selected', card.getAttribute('data-dept') === key);
        });
    }

    if (select) {
        select.addEventListener('change', function () { applyDepartment(select.value); });
    }
    document.querySelectorAll('.dfb-card').forEach(function (card) {
        card.addEventListener('click', function () {
            var key = card.getAttribute('data-dept');
            if (select) select.value = key;
            applyDepartment(key);
            var panel = document.getElementById('dfbPageForm');
            if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });
})();
</script>
@endsection
