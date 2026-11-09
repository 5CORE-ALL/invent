@extends('layouts.vertical', ['title' => 'Feedback', 'sidenav' => 'condensed'])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
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
    .dfb-focus { margin: -6px 0 12px; }
    .dfb-focus-label { font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 4px; }
    .dfb-focus-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .dfb-focus-chips button {
        border: 1px solid #e2e8f0; background: #fff; color: #334155; border-radius: 999px;
        padding: 3px 10px; font-size: 12px; font-weight: 600; cursor: pointer;
    }
    .dfb-focus-chips button:hover { border-color: var(--dfb-accent, #4338ca); color: var(--dfb-accent, #4338ca); background: #f8fafc; }
    .dfb-users-head { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; margin: 4px 0 8px; }
    .dfb-users-head h5 { margin: 0; font-size: 15px; font-weight: 700; }
    .dfb-users-head span { color: #64748b; font-size: 12px; }
    .dfb-utabs { display: flex; gap: 6px; flex-wrap: wrap; border-bottom: 1px solid #e2e8f0; margin-bottom: 8px; }
    .dfb-utabs button {
        border: 0; background: transparent; padding: 8px 14px; font-size: 13px; font-weight: 700; color: #64748b;
        border-bottom: 3px solid transparent; margin-bottom: -1px; cursor: pointer;
    }
    .dfb-utabs button span {
        display: inline-block; min-width: 22px; margin-left: 4px; padding: 0 6px; border-radius: 999px;
        background: #f1f5f9; color: #475569; font-size: 11px; text-align: center;
    }
    .dfb-utabs button:hover { color: #0f172a; }
    .dfb-utabs button.is-active { color: #4338ca; border-bottom-color: #4338ca; }
    .dfb-utabs button.is-active span { background: #eef2ff; color: #4338ca; }
    .dfb-users-table { margin-bottom: 16px; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; background: #fff; }
    .dfb-users-table .tabulator { border: 0; font-size: 13px; }
    .dfb-users-table .tabulator .tabulator-header { background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; }
    .dfb-users-table .tabulator .tabulator-col-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .dfb-users-table .tabulator-row .tabulator-cell { padding: 8px 10px; }
    .dfb-users-table .tabulator-row.tabulator-row-even { background: #fafbfd; }
    .dfb-ut-user { display: flex; align-items: center; gap: 8px; }
    .dfb-ut-user img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; background: #e2e8f0; }
    .dfb-ut-user b { color: #0f172a; font-weight: 700; }
    .dfb-ut-pill { display: inline-block; border-radius: 999px; padding: 2px 8px; font-size: 11px; font-weight: 700; }
    .dfb-ut-pill.is-given { background: #ecfdf5; color: #047857; }
    .dfb-ut-pill.is-none { background: #fef2f2; color: #b91c1c; }
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
                    <strong>Ratings, reviews and averages shown are lifetime totals</strong> (all weeks).
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
                        {{ number_format((float) $stat->avg_rating, 1) }} avg · {{ (int) $stat->total }} {{ (int) $stat->total === 1 ? 'rating' : 'ratings' }} · {{ (int) $stat->reviews }} {{ (int) $stat->reviews === 1 ? 'review' : 'reviews' }}
                    @else
                        No team ratings yet
                    @endif
                </div>
            </button>
        @endforeach
    </div>

    @php
        $uc = $userCards ?? collect();
        $uGiven = $uc->where('ratings', '>', 0)->count();
        $userRows = $uc->map(function ($u) {
            return [
                'name' => $u['name'],
                'designation' => $u['designation'],
                'avatar' => $u['avatar'],
                'status' => $u['ratings'] > 0 ? 1 : 0,
                'ratings' => $u['ratings'],
                'reviews' => $u['reviews'],
                'avg' => $u['avg'],
                'last' => $u['last_at'] ? $u['last_at']->format('Y-m-d') : '',
            ];
        })->values()->all();
    @endphp
    @if($uc->isNotEmpty())
        <div class="dfb-users-head">
            <h5>Team members · Lifetime</h5>
            <span>{{ $uGiven }} given feedback · {{ $uc->count() - $uGiven }} yet to give</span>
        </div>
        <div class="dfb-utabs" id="dfbUserTabs" role="tablist">
            <button type="button" class="is-active" role="tab" data-tab="all">All <span>{{ $uc->count() }}</span></button>
            <button type="button" role="tab" data-tab="given">Given <span>{{ $uGiven }}</span></button>
            <button type="button" role="tab" data-tab="none">Not given yet <span>{{ $uc->count() - $uGiven }}</span></button>
        </div>
        <div class="dfb-users-table"><div id="dfbUsersTable"></div></div>
    @endif

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
                    <div class="dfb-focus" id="dfbFocus" style="--dfb-accent: {{ $departments[$selected]['accent'] }}">
                        <div class="dfb-focus-label">Where does this department need to improve? Tap to add to your note</div>
                        <div class="dfb-focus-chips" id="dfbFocusChips"></div>
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
                    <a href="{{ route('feedback.index') }}" class="{{ $filter ? '' : 'is-active' }}">All ({{ (int) ($feedCounts ?? collect())->sum() }})</a>
                    @foreach($departments as $key => $meta)
                        <a href="{{ route('feedback.index', ['department' => $key]) }}" class="{{ $filter === $key ? 'is-active' : '' }}">{{ $meta['label'] }} ({{ (int) (($feedCounts ?? collect())[$key] ?? 0) }})</a>
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
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
(function () {
    var userRows = {!! json_encode($userRows ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!};
    function escHtml(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    var usersEl = document.getElementById('dfbUsersTable');
    if (usersEl && window.Tabulator) {
        var usersTable = new Tabulator(usersEl, {
            data: userRows,
            layout: 'fitColumns',
            maxHeight: '560px',
            placeholder: 'No team members',
            // Fewest reviews first, so people who have not given any feedback are on top.
            initialSort: [{ column: 'reviews', dir: 'asc' }, { column: 'name', dir: 'asc' }],
            columns: [
                { title: '#', formatter: 'rownum', width: 50, hozAlign: 'center', headerSort: false },
                { title: 'Member', field: 'name', minWidth: 180, formatter: function (cell) {
                    var d = cell.getData();
                    return '<div class="dfb-ut-user"><img src="' + escHtml(d.avatar) + '" alt="" loading="lazy"><b>' + escHtml(d.name) + '</b></div>';
                } },
                { title: 'Designation', field: 'designation', minWidth: 130 },
                { title: 'Status', field: 'status', width: 130, hozAlign: 'center', formatter: function (cell) {
                    return cell.getValue() > 0
                        ? '<span class="dfb-ut-pill is-given">Given</span>'
                        : '<span class="dfb-ut-pill is-none">Not given yet</span>';
                }, sorter: 'number' },
                { title: 'Avg Rating', field: 'avg', width: 110, hozAlign: 'center', sorter: 'number', formatter: function (cell) {
                    var v = cell.getValue();
                    return v == null ? '<span class="text-muted">—</span>' : '<span style="color:#d97706">★</span> ' + Number(v).toFixed(1);
                } },
                { title: 'No. of Reviews', field: 'reviews', width: 130, hozAlign: 'center', sorter: 'number' },
                { title: 'Last Given', field: 'last', width: 120, hozAlign: 'center', formatter: function (cell) {
                    var v = cell.getValue();
                    if (!v) return '<span class="text-muted">—</span>';
                    var d = new Date(v + 'T00:00:00');
                    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                } }
            ]
        });

        // Tabs: switch between All / Given / Not given yet and filter the rows.
        var tabsEl = document.getElementById('dfbUserTabs');
        if (tabsEl) {
            tabsEl.addEventListener('click', function (e) {
                var btn = e.target.closest('button[data-tab]');
                if (!btn) return;
                tabsEl.querySelectorAll('button').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
                var tab = btn.getAttribute('data-tab');
                if (tab === 'given') usersTable.setFilter('status', '=', 1);
                else if (tab === 'none') usersTable.setFilter('status', '=', 0);
                else usersTable.clearFilter();
            });
        }
    }

    var mine = @json($mine);
    var questions = @json($questions);
    var focusAreas = @json($focusAreas);
    var accents = @json(collect($departments)->map(fn ($m) => $m['accent']));
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

    var focusBox = document.getElementById('dfbFocus');
    var focusChips = document.getElementById('dfbFocusChips');
    function renderFocus(key) {
        if (!focusChips) return;
        focusChips.innerHTML = '';
        if (focusBox && accents[key]) focusBox.style.setProperty('--dfb-accent', accents[key]);
        (focusAreas[key] || []).forEach(function (area) {
            var b = document.createElement('button');
            b.type = 'button';
            b.textContent = area;
            b.addEventListener('click', function () {
                var line = 'Needs improvement – ' + area + ': ';
                var cur = comment.value.replace(/\s+$/, '');
                comment.value = cur ? cur + '\n' + line : line;
                comment.focus();
                comment.setSelectionRange(comment.value.length, comment.value.length);
            });
            focusChips.appendChild(b);
        });
    }
    renderFocus(select ? select.value : '');

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
        renderFocus(key);
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
