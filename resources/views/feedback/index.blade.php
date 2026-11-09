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
    .dfb-card-zoom {
        position: absolute; top: 8px; right: 8px; width: 26px; height: 26px; border-radius: 8px;
        display: grid; place-items: center; font-size: 15px; color: #64748b; background: #f1f5f9; cursor: pointer;
    }
    .dfb-card-zoom:hover { background: var(--dfb-accent, #4338ca); color: #fff; }
    .dfb-card {
        position: relative;
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
    .dfb-utabs button.is-active { color: var(--dfb-accent, #4338ca); border-bottom-color: var(--dfb-accent, #4338ca); }
    .dfb-utabs button.is-active span { background: #eef2ff; color: var(--dfb-accent, #4338ca); }
    .dfb-utabs button i { font-size: 15px; vertical-align: -2px; color: var(--dfb-accent, #64748b); }
    #dfbDeptTabs { margin-bottom: 0; border-bottom-color: #e2e8f0; }
    .dfb-ag-badge {
        border: 0; border-radius: 999px; padding: 3px 12px; font-size: 12px; font-weight: 700;
        background: #4338ca; color: #fff; cursor: pointer;
    }
    .dfb-ag-badge:hover { background: #3730a3; }
    .dfb-ut-btn {
        border: 1px solid #c7d2fe; background: #eef2ff; color: #3730a3; border-radius: 6px;
        padding: 2px 10px; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap;
    }
    .dfb-ut-btn:hover { background: #e0e7ff; }
    .dfb-ut-btn.is-all { background: #4338ca; border-color: #4338ca; color: #fff; }
    .dfb-ut-btn.is-all:hover { background: #3730a3; }
    .dfb-ut-btn:disabled { opacity: .5; cursor: default; }
    .dfb-rv { border-top: 1px solid #f1f5f9; padding: 10px 0; }
    .dfb-rv:first-child { border-top: 0; padding-top: 0; }
    .dfb-rv-top { display: flex; justify-content: space-between; gap: 8px; align-items: baseline; }
    .dfb-rv-note { margin: 4px 0 2px; white-space: pre-wrap; color: #334155; font-size: 13px; }
    .dfb-rv-meta { color: #94a3b8; font-size: 12px; }
    #dfbAgRulesModal .modal-content { border: 0; background: #f4f6fb; }
    #dfbAgRulesModal .dfb-agm-head {
        display: flex; align-items: center; gap: 14px; padding: 14px 22px; color: #fff;
        background: linear-gradient(120deg, #4338ca 0%, #6d28d9 55%, #0ea5e9 100%);
    }
    #dfbAgRulesModal .dfb-agm-ico {
        width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 22px;
        background: rgba(255, 255, 255, .18); backdrop-filter: blur(4px);
    }
    #dfbAgRulesModal .dfb-agm-head h5 { margin: 0; font-weight: 800; letter-spacing: .01em; }
    #dfbAgRulesModal .dfb-agm-head small { opacity: .85; }
    #dfbAgRulesModal .dfb-agm-count {
        margin-left: 6px; padding: 2px 10px; border-radius: 999px; background: rgba(255, 255, 255, .22); font-size: 12px; font-weight: 700;
    }
    #dfbAgRulesModal .dfb-agm-add {
        margin-left: auto; border: 0; border-radius: 10px; padding: 8px 16px; font-weight: 700; font-size: 13px;
        background: #fff; color: #4338ca; box-shadow: 0 4px 14px rgba(0, 0, 0, .18); cursor: pointer;
    }
    #dfbAgRulesModal .dfb-agm-add:hover { background: #eef2ff; }
    #dfbAgRulesModal .dfb-agm-close {
        border: 0; width: 36px; height: 36px; border-radius: 10px; background: rgba(255, 255, 255, .18); color: #fff; font-size: 20px; cursor: pointer;
    }
    #dfbAgRulesModal .dfb-agm-close:hover { background: rgba(255, 255, 255, .3); }
    #dfbAgRulesModal .modal-body { padding: 16px 22px 22px; }
    .dfb-agr-hint { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 12px; color: #64748b; margin-bottom: 14px; }
    .dfb-agr-hint button {
        border: 1px solid #c7d2fe; background: #fff; color: #4338ca; border-radius: 999px; padding: 2px 10px;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; cursor: pointer;
    }
    .dfb-agr-hint button:hover { background: #eef2ff; }
    .dfb-agr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(460px, 1fr)); gap: 14px; align-items: stretch; }
    .dfb-agr-row {
        display: flex; flex-direction: column; gap: 10px; padding: 14px; background: #fff; border-radius: 16px;
        border: 1px solid #e5e9f5; box-shadow: 0 6px 22px rgba(67, 56, 202, .07); transition: box-shadow .2s, transform .2s;
        border-top: 4px solid #6d28d9;
    }
    .dfb-agr-row:hover { box-shadow: 0 10px 28px rgba(67, 56, 202, .14); transform: translateY(-1px); }
    .dfb-agr-row.is-off { opacity: .65; border-top-color: #cbd5e1; }
    .dfb-agr-top { display: flex; align-items: center; gap: 10px; }
    .dfb-agr-top .form-switch { margin: 0; min-height: 0; }
    .dfb-agr-state { font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #059669; }
    .dfb-agr-row.is-off .dfb-agr-state { color: #dc2626; }
    .dfb-agr-ic { display: inline-grid; place-items: center; font-size: 22px; line-height: 1; }
    .dfb-agr-ic.is-on { color: #16a34a; }
    .dfb-agr-ic.is-off { color: #dc2626; }
    .dfb-agr-ic.is-on i { animation: dfbAgPulse 2s ease-in-out infinite; }
    @keyframes dfbAgPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.12); } }
    @media (prefers-reduced-motion: reduce) { .dfb-agr-ic.is-on i { animation: none; } }
    .dfb-agr-lbl { font-size: 10px; font-weight: 800; letter-spacing: .08em; color: #94a3b8; text-transform: uppercase; margin-bottom: 4px; }
    .dfb-agr-conds { display: flex; flex-direction: column; gap: 6px; padding: 8px; border-radius: 12px; background: #f1f5ff; }
    .dfb-agr-c { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
    .dfb-agr-c .form-select, .dfb-agr-c .form-control { width: auto; min-width: 90px; font-size: 13px; border-radius: 8px; border-color: #dbe3f7; }
    .dfb-agr-join { width: 38px; font-size: 11px; font-weight: 800; letter-spacing: .06em; color: #6d28d9; }
    .dfb-agr-x {
        border: 0; width: 28px; height: 28px; border-radius: 8px; background: #fff; color: #94a3b8; cursor: pointer; line-height: 1;
    }
    .dfb-agr-x:hover { background: #fee2e2; color: #b91c1c; }
    .dfb-agr-addc {
        margin-top: 6px; border: 1px dashed #a5b4fc; background: #fff; color: #4338ca; border-radius: 10px;
        padding: 4px 12px; font-size: 12px; font-weight: 700; cursor: pointer;
    }
    .dfb-agr-addc:hover { background: #eef2ff; }
    .dfb-agr-match { margin-left: auto; display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 800; letter-spacing: .06em; color: #64748b; text-transform: uppercase; }
    .dfb-agr-match[hidden] { display: none; }
    .dfb-agr-match .form-select { width: auto; font-size: 12px; border-radius: 8px; text-transform: none; letter-spacing: 0; }
    .dfb-agr-cond { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; padding: 8px; border-radius: 12px; background: #f1f5ff; }
    .dfb-agr-cond .form-select, .dfb-agr-cond .form-control { width: auto; min-width: 90px; font-size: 13px; border-radius: 8px; border-color: #dbe3f7; }
    .dfb-agr-row textarea { flex: 1; min-height: 150px; resize: vertical; font-size: 13px; border-radius: 12px; border-color: #dbe3f7; }
    .dfb-agr-row textarea:focus, .dfb-agr-cond .form-select:focus, .dfb-agr-cond .form-control:focus { border-color: #6d28d9; box-shadow: 0 0 0 3px rgba(109, 40, 217, .12); }
    .dfb-agr-foot { display: flex; align-items: center; gap: 8px; }
    .dfb-agr-foot .form-check { margin: 0; flex: 1; }
    .dfb-agr-btn { border: 0; border-radius: 10px; padding: 6px 16px; font-size: 13px; font-weight: 700; cursor: pointer; }
    .dfb-agr-btn.is-save { background: linear-gradient(120deg, #4338ca, #6d28d9); color: #fff; }
    .dfb-agr-btn.is-save:hover { filter: brightness(1.1); }
    .dfb-agr-btn.is-del { background: #fff1f2; color: #be123c; }
    .dfb-agr-btn.is-del:hover { background: #ffe4e6; }
    .dfb-agr-btn.is-cancel { background: #f1f5f9; color: #475569; }
    .dfb-agr-empty { text-align: center; color: #64748b; padding: 60px 0; }
    .dfb-agr-empty i { font-size: 44px; color: #a5b4fc; display: block; }
    .dfb-toast {
        position: fixed; right: 18px; bottom: 18px; z-index: 3000; max-width: 380px; padding: 10px 14px;
        background: #0f172a; color: #fff; border-radius: 10px; font-size: 13px; box-shadow: 0 6px 20px rgba(0,0,0,.25);
    }
    .dfb-toast.is-err { background: #b91c1c; }
    @media (max-width: 575px) { .dfb-agr-grid { grid-template-columns: 1fr; } }
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
                <span class="dfb-card-zoom" role="button" tabindex="0" data-dept-reviews="{{ $key }}" title="View {{ $meta['label'] }} reviews" aria-label="View {{ $meta['label'] }} reviews"><i class="ri-search-line"></i></span>
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

    <div class="modal fade" id="dfbReviewsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0" id="dfbReviewsTitle">Reviews</h5>
                        <small class="text-muted" id="dfbReviewsSub"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="dfbReviewsBody"></div>
            </div>
        </div>
    </div>


    @php
        $uc = $userCards ?? collect();
        $uGiven = $uc->where('ratings', '>', 0)->count();
        $userRows = $uc->map(function ($u) {
            return [
                'id' => $u['id'],
                'name' => $u['name'],
                'designation' => $u['designation'],
                'avatar' => $u['avatar'],
                'status' => $u['ratings'] > 0 ? 1 : 0,
                'ratings' => $u['ratings'],
                'reviews' => $u['reviews'],
                'avg' => $u['avg'],
                'last' => $u['last_at'] ? $u['last_at']->format('Y-m-d') : '',
                'depts' => (object) ($u['depts'] ?? []),
            ];
        })->values()->all();
    @endphp
    @if($uc->isNotEmpty())
        <div class="dfb-users-head">
            <h5>Team members · Lifetime</h5>
            <span>{{ $uGiven }} given feedback · {{ $uc->count() - $uGiven }} yet to give</span>
            @if($canAgMsg ?? false)
                <button type="button" class="dfb-ag-badge" id="dfbAgRulesBtn" title="Create rules that generate messages from president@5core.com"><i class="ri-mail-send-line"></i> AG-MSG Rules</button>
            @endif
        </div>
        <div class="dfb-utabs" id="dfbDeptTabs" role="tablist" aria-label="Department">
            <button type="button" class="is-active" role="tab" data-dept="all">All departments <span>{{ $uc->count() }}</span></button>
            @foreach($departments as $key => $meta)
                <button type="button" role="tab" data-dept="{{ $key }}" title="{{ $meta['label'] }} · lifetime ratings" style="--dfb-accent: {{ $meta['accent'] }}"><i class="{{ $meta['icon'] }}"></i> {{ $meta['label'] }} <span>{{ (int) (($feedCounts ?? collect())[$key] ?? 0) }}</span></button>
            @endforeach
        </div>
        <div class="dfb-utabs" id="dfbUserTabs" role="tablist" aria-label="Status">
            <button type="button" class="is-active" role="tab" data-tab="all">All <span>{{ $uc->count() }}</span></button>
            <button type="button" role="tab" data-tab="given">Given <span>{{ $uGiven }}</span></button>
            <button type="button" role="tab" data-tab="none">Not given yet <span>{{ $uc->count() - $uGiven }}</span></button>
        </div>
        <div class="dfb-users-table"><div id="dfbUsersTable"></div></div>

        @if($canAgMsg ?? false)
            <div class="modal fade" id="dfbAgRulesModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-fullscreen">
                    <div class="modal-content">
                        <div class="dfb-agm-head">
                            <div class="dfb-agm-ico"><i class="ri-mail-send-line"></i></div>
                            <div>
                                <h5>AG-MSG Rules <span class="dfb-agm-count" id="dfbAgCount">0</span></h5>
                                <small>When a person's row matches a rule, the message beside it is sent to them from president@5core.com.</small>
                            </div>
                            <button type="button" class="dfb-agm-add" id="dfbAgAddRule"><i class="ri-add-line"></i> Add rule</button>
                            <button type="button" class="dfb-agm-close" data-bs-dismiss="modal" aria-label="Close"><i class="ri-close-line"></i></button>
                        </div>
                        <div class="modal-body">
                            <div class="dfb-agr-hint">
                                Tap a placeholder to insert it into the message:
                                @foreach(['name', 'first_name', 'date', 'department', 'status', 'avg', 'ratings', 'reviews'] as $ph)
                                    <button type="button" data-ph="{{ '{'.$ph.'}' }}">{{ '{'.$ph.'}' }}</button>
                                @endforeach
                            </div>
                            <div id="dfbAgRulesList" class="dfb-agr-grid"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
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
    var CAN_AG = {{ ($canAgMsg ?? false) ? 'true' : 'false' }};
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var URLS = {
        reviews: @json(url('/feedback/user')),
        rules: @json(route('feedback.ag-msg.rules')),
        saveRule: @json(route('feedback.ag-msg.rules.save')),
        rulesBase: @json(url('/feedback/ag-msg/rules')),
        send: @json(route('feedback.ag-msg.send'))
    };
    function flash(msg, isErr) {
        var t = document.createElement('div');
        t.className = 'dfb-toast' + (isErr ? ' is-err' : '');
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(function () { t.remove(); }, 4500);
    }
    function api(url, opts) {
        opts = opts || {};
        opts.credentials = 'same-origin';
        opts.headers = Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, opts.headers || {});
        return fetch(url, opts).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) {
                if (!r.ok) {
                    var m = j.message || (j.errors ? j.errors[Object.keys(j.errors)[0]][0] : 'Request failed.');
                    var e = new Error(m); e.payload = j; throw e;
                }
                return j;
            });
        });
    }
    function showModal(id) {
        var el = document.getElementById(id);
        if (el && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(el).show();
    }
    function starsText(n) { return '★★★★★'.slice(0, n) + '☆☆☆☆☆'.slice(0, 5 - n); }

    // Magnifying glass on each department card: every review given for that department.
    function openDeptReviews(key) {
        var body = document.getElementById('dfbReviewsBody');
        document.getElementById('dfbReviewsTitle').textContent = 'Reviews';
        document.getElementById('dfbReviewsSub').textContent = 'Loading…';
        body.innerHTML = '<div class="text-center text-muted py-4">Loading…</div>';
        showModal('dfbReviewsModal');
        api(@json(url('/feedback/department')) + '/' + encodeURIComponent(key) + '/reviews').then(function (j) {
            document.getElementById('dfbReviewsTitle').textContent = j.label + ' · user reviews';
            document.getElementById('dfbReviewsSub').textContent = j.count + ' review' + (j.count === 1 ? '' : 's') + (j.avg != null ? ' · ' + Number(j.avg).toFixed(1) + ' avg' : '') + ' · lifetime';
            if (!j.data.length) { body.innerHTML = '<p class="text-muted mb-0">No reviews yet for this department.</p>'; return; }
            body.innerHTML = j.data.map(function (r) {
                return '<div class="dfb-rv"><div class="dfb-rv-top">' +
                    '<span class="dfb-ut-user"><img src="' + escHtml(r.avatar) + '" alt="" loading="lazy"><b>' + escHtml(r.user) + '</b></span>' +
                    '<span style="color:#d97706;letter-spacing:1px">' + starsText(r.rating) + '</span></div>' +
                    '<div class="dfb-rv-note">' + (r.comment ? escHtml(r.comment) : '<span class="text-muted">No note</span>') + '</div>' +
                    '<div class="dfb-rv-meta">Week of ' + escHtml(r.week || '') + ' · ' + escHtml(r.given_at || '') + '</div></div>';
            }).join('');
        }).catch(function (e) {
            document.getElementById('dfbReviewsSub').textContent = '';
            body.innerHTML = '<div class="alert alert-danger mb-0">' + escHtml(e.message) + '</div>';
        });
    }
    var weekEl = document.querySelector('.dfb-week');
    if (weekEl) {
        // Capture phase so the card's own "select department" click does not also fire.
        weekEl.addEventListener('click', function (e) {
            var z = e.target.closest('[data-dept-reviews]');
            if (!z) return;
            e.stopPropagation();
            e.preventDefault();
            openDeptReviews(z.getAttribute('data-dept-reviews'));
        }, true);
        weekEl.addEventListener('keydown', function (e) {
            var z = e.target.closest('[data-dept-reviews]');
            if (!z || (e.key !== 'Enter' && e.key !== ' ')) return;
            e.stopPropagation();
            e.preventDefault();
            openDeptReviews(z.getAttribute('data-dept-reviews'));
        }, true);
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
                { title: 'Rating', field: 'avg', width: 110, hozAlign: 'center', sorter: 'number', formatter: function (cell) {
                    var v = cell.getValue();
                    return v == null ? '<span class="text-muted">—</span>' : '<span style="color:#d97706">★</span> ' + Number(v).toFixed(1);
                } },
                { title: 'No. of Reviews', field: 'reviews', width: 130, hozAlign: 'center', sorter: 'number' },
                { title: 'Last Given', field: 'last', width: 120, hozAlign: 'center', formatter: function (cell) {
                    var v = cell.getValue();
                    if (!v) return '<span class="text-muted">—</span>';
                    var d = new Date(v + 'T00:00:00');
                    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                } },
                { title: 'Reviews', field: 'reviews', width: 120, hozAlign: 'center', headerSort: false, formatter: function (cell) {
                    var d = cell.getData();
                    if (!d.reviews && !d.ratings) return '<span class="text-muted">—</span>';
                    return '<button type="button" class="dfb-ut-btn" data-reviews="' + d.id + '"><i class="ri-eye-line"></i> View (' + (d.ratings || 0) + ')</button>';
                } }
            ].concat(CAN_AG ? [{
                title: 'AG-MSG', field: 'id', width: 130, hozAlign: 'center', headerSort: false,
                titleFormatter: function () { return '<button type="button" class="dfb-ut-btn is-all" data-ag-all="1" title="Generate messages for every person shown">AG-MSG All</button>'; },
                formatter: function (cell) {
                    return '<button type="button" class="dfb-ut-btn" data-ag="' + cell.getData().id + '" title="Generate message for this person">AG-MSG</button>';
                }
            }] : [])
        });

        // Tabs: department (row 1) and status (row 2). Department tab re-points the row values to that department.
        var deptTabs = document.getElementById('dfbDeptTabs');
        var statusTabs = document.getElementById('dfbUserTabs');
        var curDept = 'all';
        var curStatus = 'all';

        function rowsFor(dept) {
            return userRows.map(function (r) {
                if (dept === 'all') return r;
                var d = (r.depts || {})[dept];
                var o = Object.assign({}, r);
                o.ratings = d ? d.ratings : 0;
                o.reviews = d ? d.reviews : 0;
                o.avg = d ? d.avg : null;
                o.last = d ? d.last : '';
                o.status = d && d.ratings > 0 ? 1 : 0;
                return o;
            });
        }
        function refreshTabs(rows) {
            var given = rows.filter(function (r) { return r.status === 1; }).length;
            var counts = { all: rows.length, given: given, none: rows.length - given };
            statusTabs.querySelectorAll('button[data-tab]').forEach(function (b) {
                var badge = b.querySelector('span');
                if (badge) badge.textContent = counts[b.getAttribute('data-tab')];
            });
        }
        function applyStatus() {
            if (curStatus === 'given') usersTable.setFilter('status', '=', 1);
            else if (curStatus === 'none') usersTable.setFilter('status', '=', 0);
            else usersTable.clearFilter();
        }
        function applyAll() {
            var rows = rowsFor(curDept);
            refreshTabs(rows);
            usersTable.setData(rows).then(applyStatus);
        }

        if (deptTabs) deptTabs.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-dept]');
            if (!btn) return;
            deptTabs.querySelectorAll('button').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
            curDept = btn.getAttribute('data-dept');
            applyAll();
        });
        if (statusTabs) statusTabs.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-tab]');
            if (!btn) return;
            statusTabs.querySelectorAll('button').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
            curStatus = btn.getAttribute('data-tab');
            applyStatus();
        });

        // ---- Reviews column: show every review this person has given (current department tab).
        function openReviews(uid) {
            var row = userRows.filter(function (r) { return String(r.id) === String(uid); })[0] || {};
            document.getElementById('dfbReviewsTitle').textContent = (row.name || 'Reviews') + ' · reviews given';
            var deptLabel = curDept === 'all' ? 'All departments' : (deptTabs.querySelector('[data-dept="' + curDept + '"]').textContent.replace(/\s+\d+\s*$/, '').trim());
            document.getElementById('dfbReviewsSub').textContent = deptLabel + ' · lifetime';
            var body = document.getElementById('dfbReviewsBody');
            body.innerHTML = '<div class="text-center text-muted py-4">Loading…</div>';
            showModal('dfbReviewsModal');
            api(URLS.reviews + '/' + uid + '/reviews' + (curDept === 'all' ? '' : '?department=' + encodeURIComponent(curDept)))
                .then(function (j) {
                    if (!j.data || !j.data.length) { body.innerHTML = '<p class="text-muted mb-0">No reviews given yet.</p>'; return; }
                    body.innerHTML = j.data.map(function (r) {
                        return '<div class="dfb-rv"><div class="dfb-rv-top">' +
                            '<b style="color:' + escHtml(r.accent) + '">' + escHtml(r.department) + '</b>' +
                            '<span style="color:#d97706;letter-spacing:1px">' + starsText(r.rating) + '</span></div>' +
                            '<div class="dfb-rv-note">' + (r.comment ? escHtml(r.comment) : '<span class="text-muted">No note</span>') + '</div>' +
                            '<div class="dfb-rv-meta">Week of ' + escHtml(r.week || '') + ' · ' + escHtml(r.given_at || '') + '</div></div>';
                    }).join('');
                })
                .catch(function (e) { body.innerHTML = '<div class="alert alert-danger mb-0">' + escHtml(e.message) + '</div>'; });
        }

        // ---- AG-MSG buttons (one row / all rows shown).
        function sendAg(ids, btn) {
            if (!ids.length) { flash('No rows to send to.', true); return; }
            if (btn) btn.disabled = true;
            api(URLS.send, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_ids: ids, department: curDept })
            }).then(function (j) { flash(j.message || 'Done.'); })
              .catch(function (e) { flash(e.message, true); })
              .then(function () { if (btn) btn.disabled = false; });
        }

        usersEl.addEventListener('click', function (e) {
            var rv = e.target.closest('[data-reviews]');
            if (rv) { openReviews(rv.getAttribute('data-reviews')); return; }
            var one = e.target.closest('[data-ag]');
            if (one) { sendAg([parseInt(one.getAttribute('data-ag'), 10)], one); return; }
            var all = e.target.closest('[data-ag-all]');
            if (all) {
                var ids = usersTable.getData('active').map(function (r) { return r.id; });
                if (ids.length && window.confirm('Generate AG-MSG messages for the ' + ids.length + ' people shown (rules decide who gets a message)?')) sendAg(ids, all);
            }
        });
    }

    // ---- AG-MSG Rules modal (rule on the left, message text beside it).
    var rulesBtn = document.getElementById('dfbAgRulesBtn');
    if (rulesBtn) {
        var listEl = document.getElementById('dfbAgRulesList');
        var FIELDS = [['status', 'Status'], ['avg', 'Avg Rating'], ['reviews', 'No. of Reviews'], ['ratings', 'No. of Ratings']];
        var NUM_OPS = ['=', '!=', '<', '<=', '>', '>='];
        function opt(v, label, sel) { return '<option value="' + escHtml(v) + '"' + (sel ? ' selected' : '') + '>' + escHtml(label) + '</option>'; }

        // One condition = "<field> <operator> <value>". A rule can hold several, joined with AND / OR.
        function condHtml(c) {
            var f = c.field || 'status';
            var fieldSel = '<select class="form-select form-select-sm" data-f="field">' + FIELDS.map(function (x) { return opt(x[0], x[1], x[0] === f); }).join('') + '</select>';
            var opSel, valCtl;
            if (f === 'status') {
                opSel = '<select class="form-select form-select-sm" data-f="operator">' + opt('=', 'is', c.operator !== '!=') + opt('!=', 'is not', c.operator === '!=') + '</select>';
                valCtl = '<select class="form-select form-select-sm" data-f="value">' + opt('given', 'Given', c.value === 'given') + opt('none', 'Not given yet', c.value !== 'given') + '</select>';
            } else {
                opSel = '<select class="form-select form-select-sm" data-f="operator">' + NUM_OPS.map(function (o) { return opt(o, o, o === (c.operator || '=')); }).join('') + '</select>';
                valCtl = '<input type="number" step="any" class="form-control form-control-sm" data-f="value" value="' + escHtml(c.value == null ? '0' : c.value) + '" style="width:90px">';
            }
            return fieldSel + opSel + valCtl;
        }
        function condRow(c, first) {
            var el = document.createElement('div');
            el.className = 'dfb-agr-c';
            el.innerHTML = '<span class="dfb-agr-join" data-join>' + (first ? 'IF' : '') + '</span>' + condHtml(c) +
                '<button type="button" class="dfb-agr-x" data-rmcond title="Remove this condition" aria-label="Remove condition"><i class="ri-close-line"></i></button>';
            return el;
        }
        function syncConds(row) {
            var rows = row.querySelectorAll('.dfb-agr-c');
            var any = (row.querySelector('[data-f="match_mode"]') || {}).value === 'any';
            rows.forEach(function (el, i) {
                el.querySelector('[data-join]').textContent = i === 0 ? 'IF' : (any ? 'OR' : 'AND');
                el.querySelector('[data-rmcond]').style.visibility = rows.length > 1 ? 'visible' : 'hidden';
            });
            var mm = row.querySelector('.dfb-agr-match');
            if (mm) mm.hidden = rows.length < 2;
        }

        function ruleRow(r) {
            var row = document.createElement('div');
            row.className = 'dfb-agr-row' + (r.is_active === false ? ' is-off' : '');
            row.dataset.id = r.id || '';
            var on = r.is_active !== false;
            var conds = (r.conditions && r.conditions.length) ? r.conditions : [{ field: r.field || 'status', operator: r.operator || '=', value: r.value == null ? 'none' : r.value }];
            row.innerHTML =
                '<div class="dfb-agr-top"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-f="is_active"' + (on ? ' checked' : '') + ' title="Rule on / off"></div>' +
                (r.id ? '<span class="dfb-agr-ic ' + (on ? 'is-on' : 'is-off') + '" title="Saved rule: ' + (on ? 'Active' : 'Paused') + '"><i class="' + (on ? 'ri-checkbox-circle-fill' : 'ri-pause-circle-fill') + '"></i></span>' : '') +
                '<span class="dfb-agr-state">' + (on ? 'Active' : 'Paused') + '</span>' +
                '<label class="dfb-agr-match" hidden>Match <select class="form-select form-select-sm" data-f="match_mode">' +
                opt('all', 'ALL conditions (AND)', r.match_mode !== 'any') + opt('any', 'ANY condition (OR)', r.match_mode === 'any') + '</select></label></div>' +
                '<div><div class="dfb-agr-lbl">Conditions</div><div class="dfb-agr-conds"></div>' +
                '<button type="button" class="dfb-agr-addc" data-addcond><i class="ri-add-line"></i> Add condition</button></div>' +
                '<div style="display:flex;flex-direction:column;flex:1"><div class="dfb-agr-lbl">Then send message</div>' +
                '<textarea class="form-control" data-f="message" maxlength="2000" placeholder="Hi {name}, please share your feedback for {department}.">' + escHtml(r.message || '') + '</textarea></div>' +
                '<div class="dfb-agr-foot"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" data-f="allow_reply"' + (r.allow_reply ? ' checked' : '') + '> <label class="form-check-label small">Allow reply</label></div>' +
                (r.id ? '<button type="button" class="dfb-agr-btn is-del" data-del><i class="ri-delete-bin-line"></i> Delete</button>' : '<button type="button" class="dfb-agr-btn is-cancel" data-cancel>Cancel</button>') +
                '<button type="button" class="dfb-agr-btn is-save" data-save><i class="ri-save-3-line"></i> Save</button></div>';
            var box = row.querySelector('.dfb-agr-conds');
            conds.forEach(function (c, i) { box.appendChild(condRow(c, i === 0)); });
            syncConds(row);
            return row;
        }
        function readCond(el) {
            return {
                field: el.querySelector('[data-f="field"]').value,
                operator: el.querySelector('[data-f="operator"]').value,
                value: el.querySelector('[data-f="value"]').value
            };
        }
        function readRow(row) {
            function q(f) { return row.querySelector('[data-f="' + f + '"]'); }
            return {
                id: row.dataset.id ? parseInt(row.dataset.id, 10) : null,
                conditions: Array.prototype.map.call(row.querySelectorAll('.dfb-agr-c'), readCond),
                match_mode: q('match_mode').value,
                message: q('message').value.trim(),
                allow_reply: q('allow_reply').checked,
                is_active: q('is_active').checked
            };
        }
        function updateCount() {
            var el = document.getElementById('dfbAgCount');
            if (el) el.textContent = listEl.querySelectorAll('.dfb-agr-row').length;
        }
        // Placeholder chips insert at the cursor of the last message box that was used.
        var lastTa = null;
        listEl.addEventListener('focusin', function (e) { if (e.target.matches('textarea')) lastTa = e.target; });
        document.querySelector('#dfbAgRulesModal .dfb-agr-hint').addEventListener('click', function (e) {
            var b = e.target.closest('[data-ph]');
            if (!b) return;
            var ta = (lastTa && document.body.contains(lastTa)) ? lastTa : listEl.querySelector('textarea');
            if (!ta) { flash('Add a rule first.', true); return; }
            var st = ta.selectionStart == null ? ta.value.length : ta.selectionStart;
            var en = ta.selectionEnd == null ? st : ta.selectionEnd;
            ta.value = ta.value.slice(0, st) + b.getAttribute('data-ph') + ta.value.slice(en);
            ta.focus();
            ta.setSelectionRange(st + b.getAttribute('data-ph').length, st + b.getAttribute('data-ph').length);
        });
        function loadRules() {
            listEl.innerHTML = '<div class="text-muted py-3">Loading…</div>';
            api(URLS.rules).then(function (j) {
                listEl.innerHTML = '';
                if (!j.data.length) listEl.innerHTML = '<div class="dfb-agr-empty" id="dfbAgEmpty" style="grid-column:1/-1"><i class="ri-mail-send-line"></i>No rules yet. Click “Add rule” to create one.</div>';
                j.data.forEach(function (r) { listEl.appendChild(ruleRow(r)); });
                updateCount();
            }).catch(function (e) { listEl.innerHTML = '<div class="alert alert-danger">' + escHtml(e.message) + '</div>'; });
        }
        rulesBtn.addEventListener('click', function () { showModal('dfbAgRulesModal'); loadRules(); });
        document.getElementById('dfbAgAddRule').addEventListener('click', function () {
            var empty = document.getElementById('dfbAgEmpty'); if (empty) empty.remove();
            var row = ruleRow({ conditions: [{ field: 'status', operator: '=', value: 'none' }], match_mode: 'all', message: '', is_active: true });
            listEl.prepend(row);
            row.querySelector('textarea').focus();
            updateCount();
        });
        listEl.addEventListener('change', function (e) {
            var row = e.target.closest('.dfb-agr-row');
            if (!row) return;
            if (e.target.matches('[data-f="field"]')) {
                var cEl = e.target.closest('.dfb-agr-c');
                var f = e.target.value;
                var rebuilt = condRow({ field: f, operator: '=', value: f === 'status' ? 'none' : '0' }, false);
                cEl.innerHTML = rebuilt.innerHTML;
                syncConds(row);
            }
            if (e.target.matches('[data-f="match_mode"]')) syncConds(row);
            if (e.target.matches('[data-f="is_active"]')) {
                row.classList.toggle('is-off', !e.target.checked);
                var st = row.querySelector('.dfb-agr-state'); if (st) st.textContent = e.target.checked ? 'Active' : 'Paused';
            }
        });
        listEl.addEventListener('click', function (e) {
            var row = e.target.closest('.dfb-agr-row');
            if (!row) return;
            if (e.target.closest('[data-addcond]')) {
                row.querySelector('.dfb-agr-conds').appendChild(condRow({ field: 'reviews', operator: '<', value: '1' }, false));
                syncConds(row);
                return;
            }
            var rm = e.target.closest('[data-rmcond]');
            if (rm) {
                if (row.querySelectorAll('.dfb-agr-c').length > 1) { rm.closest('.dfb-agr-c').remove(); syncConds(row); }
                return;
            }
            if (e.target.closest('[data-cancel]')) { row.remove(); updateCount(); return; }
            if (e.target.closest('[data-del]')) {
                if (!window.confirm('Delete this rule?')) return;
                api(URLS.rulesBase + '/' + row.dataset.id, { method: 'DELETE' }).then(function () { row.remove(); updateCount(); flash('Rule deleted.'); })
                    .catch(function (err) { flash(err.message, true); });
                return;
            }
            if (e.target.closest('[data-save]')) {
                var data = readRow(row);
                if (!data.message) { flash('Type the message to send.', true); return; }
                api(URLS.saveRule, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
                    .then(function (j) { row.replaceWith(ruleRow(j.rule)); flash('Rule saved.'); })
                    .catch(function (err) { flash(err.message, true); });
            }
        });
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
