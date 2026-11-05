@extends('layouts.vertical', ['title' => 'Incentives', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<style>
    .inc-hero {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }
    .inc-hero h4 { margin: 0; font-weight: 800; color: #0f172a; }
    .inc-hero .inc-des { color: #64748b; margin-top: 0.2rem; }
    .inc-total-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        background: #15803d;
        color: #fff;
        border-radius: 999px;
        padding: 0.45rem 0.55rem 0.45rem 0.85rem;
        font-weight: 800;
        font-size: 1.05rem;
        line-height: 1;
        box-shadow: 0 0 0 2px rgba(21, 128, 61, 0.15);
    }
    .inc-total-pill span {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fcd34d;
        border-radius: 999px;
        padding: 0.28rem 0.65rem;
        font-size: 0.95rem;
    }
    .inc-card {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #fff;
        padding: 1rem 1.1rem 1.15rem;
    }
    .inc-person + .inc-person { margin-top: 0.85rem; }
    .inc-person-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }
    .inc-person-head h5 { margin: 0; font-weight: 800; color: #0f172a; }
    .inc-person-head .inc-des { color: #64748b; font-size: 0.85rem; }
    .inc-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .inc-table th, .inc-table td {
        border: 1px solid #fde68a;
        padding: 0.55rem 0.6rem;
        vertical-align: top;
    }
    .inc-table thead th {
        background: #fffbeb;
        color: #92400e;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 800;
        white-space: nowrap;
    }
    .inc-table .inc-amt {
        font-weight: 800;
        color: #15803d;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .inc-table .inc-days {
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .inc-table tfoot td {
        background: #ecfdf5;
        font-weight: 800;
        color: #166534;
        border-top: 2px solid #86efac;
    }
    .inc-table tr.is-cutoff td { background: #fef2f2; }
    .inc-table tr.is-inactive { opacity: 0.55; }
    .inc-cutoff-hot { color: #dc2626; font-weight: 800; }
    .inc-countdown {
        display: inline-block;
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fcd34d;
        border-radius: 999px;
        padding: 0.12em 0.55em;
        font-weight: 800;
        white-space: nowrap;
    }
    .inc-actions { white-space: nowrap; text-align: center; width: 1%; }
    .inc-action-btns {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
    }
    .inc-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        padding: 0;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        color: #0f172a;
        text-decoration: none;
        line-height: 1;
        font-size: 1rem;
    }
    .inc-action-btn:hover {
        background: #ecfdf5;
        color: #15803d;
        border-color: #86efac;
    }
    .inc-action-btn.is-extend:hover {
        background: #fffbeb;
        color: #92400e;
        border-color: #fde68a;
    }
    .inc-extend-btn {
        white-space: nowrap;
        font-weight: 800;
    }
    .inc-request-badge {
        display: inline-block;
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fcd34d;
        border-radius: 999px;
        padding: 0.2em 0.6em;
        font-size: 0.72rem;
        font-weight: 800;
        white-space: nowrap;
    }
    .inc-request-badge.is-claimed {
        background: #ecfdf5;
        color: #166534;
        border-color: #86efac;
    }
    .inc-retry-check {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        margin: 0.85rem 0 0;
        font-weight: 700;
        color: #0f172a;
    }
    .inc-retry-check input { width: 1.05rem; height: 1.05rem; }
    .inc-review-btns {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        flex-wrap: wrap;
        justify-content: center;
    }
    .inc-pending-card {
        border: 1px solid #fde68a;
        background: #fffbeb;
        border-radius: 14px;
        padding: 1rem 1.1rem;
        margin-bottom: 1rem;
    }
    .inc-nx .modal-dialog { max-width: 560px; }
    .inc-nx-sheet {
        border: 0;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
    }
    .inc-nx-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.15rem 1.25rem 0.9rem;
        background: linear-gradient(135deg, #0f172a, #1e293b);
        color: #fff;
    }
    .inc-nx-kicker {
        font-size: 0.68rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #fcd34d;
    }
    .inc-nx-head h5 { margin: 0.15rem 0 0; font-weight: 800; color: #fff; }
    .inc-nx-close {
        width: 34px;
        height: 34px;
        border: 0;
        border-radius: 10px;
        background: rgba(255,255,255,0.12);
        color: #fff;
        font-size: 1.25rem;
        line-height: 1;
    }
    .inc-nx-body { padding: 1.1rem 1.25rem 0.4rem; background: #f8fafc; }
    .inc-nx-who {
        margin: 0 0 0.85rem;
        padding: 0.7rem 0.85rem;
        border-radius: 14px;
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #0f172a;
        font-weight: 800;
    }
    .inc-nx-choices {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.55rem;
        margin-bottom: 0.75rem;
    }
    .inc-nx-choice {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        min-height: 108px;
        margin: 0;
        padding: 0.75rem 0.7rem;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        background: #fff;
        cursor: pointer;
        color: #0f172a;
    }
    .inc-nx-choice input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .inc-nx-choice strong { display: block; font-size: 0.86rem; }
    .inc-nx-choice small { display: block; color: #64748b; font-weight: 600; line-height: 1.25; }
    .inc-nx-ico {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 10px;
        background: #f1f5f9;
        color: #334155;
        font-size: 1.05rem;
    }
    .inc-nx-choice.is-extend:has(input:checked) { border-color: #f59e0b; background: #fffbeb; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.18); }
    .inc-nx-choice.is-extend:has(input:checked) .inc-nx-ico { background: #f59e0b; color: #fff; }
    .inc-nx-choice.is-help:has(input:checked) { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.16); }
    .inc-nx-choice.is-help:has(input:checked) .inc-nx-ico { background: #2563eb; color: #fff; }
    .inc-nx-choice.is-end:has(input:checked) { border-color: #e11d48; background: #fff1f2; box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.14); }
    .inc-nx-choice.is-end:has(input:checked) .inc-nx-ico { background: #e11d48; color: #fff; }
    .inc-nx-note { min-height: 2.4em; margin: 0 0 0.85rem; color: #64748b; font-size: 0.84rem; }
    .inc-nx-field { margin-bottom: 0.85rem; }
    .inc-nx-field label {
        display: block;
        margin-bottom: 0.35rem;
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #475569;
    }
    .inc-nx-field input,
    .inc-nx-field textarea,
    .inc-nx-field select {
        width: 100%;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        padding: 0.7rem 0.8rem;
        color: #0f172a;
    }
    .inc-nx-field textarea { min-height: 110px; resize: vertical; }
    .inc-nx-field input:focus,
    .inc-nx-field textarea:focus,
    .inc-nx-field select:focus {
        outline: none;
        border-color: #f59e0b;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.18);
    }
    .inc-nx-foot {
        display: flex;
        justify-content: flex-end;
        gap: 0.55rem;
        padding: 0.85rem 1.25rem 1.15rem;
        background: #fff;
        border-top: 1px solid #e2e8f0;
    }
    .inc-nx-cancel,
    .inc-nx-submit {
        border: 0;
        border-radius: 12px;
        padding: 0.7rem 1rem;
        font-weight: 800;
    }
    .inc-nx-cancel { background: #f1f5f9; color: #334155; }
    .inc-nx-submit { background: #d97706; color: #fff; }
    .inc-nx-sheet[data-kind="help"] .inc-nx-submit { background: #2563eb; }
    .inc-nx-sheet[data-kind="approve"] .inc-nx-submit { background: #16a34a; }
    .inc-nx-sheet[data-kind="end"] .inc-nx-submit { background: #e11d48; }
    @media (max-width: 575.98px) {
        .inc-nx-choices { grid-template-columns: 1fr; }
        .inc-nx-choice { min-height: 0; flex-direction: row; align-items: center; }
    }
    .inc-pending-card h5 {
        margin: 0 0 0.65rem;
        font-weight: 800;
        color: #92400e;
    }
    .inc-pending-list {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
    }
    .inc-pending-item {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
        background: #fff;
        border: 1px solid #fde68a;
        border-radius: 10px;
        padding: 0.7rem 0.8rem;
    }
    .inc-pending-item p { margin: 0; }
    #inc-search { max-width: 420px; }
</style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Incentives',
        'sub_title' => 'Tasks',
    ])

    @php
        $cutoffInput = function ($value) {
            $s = trim((string) $value);
            if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) {
                return $m[1];
            }
            return '';
        };
        $cutoffLabel = function ($value) use ($cutoffInput) {
            $iso = $cutoffInput($value);
            if ($iso === '') {
                return $value ? (string) $value : '—';
            }
            try {
                return \Carbon\Carbon::parse($iso)->format('j M Y');
            } catch (\Throwable $e) {
                return $iso;
            }
        };
        $isActive = function ($value) {
            if (is_bool($value)) {
                return $value;
            }
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return $parsed === null ? true : $parsed;
        };
        $rupee = function ($amount) {
            $n = is_numeric($amount) ? (float) $amount : 0;
            return '₹'.number_format($n, 0);
        };
        $businessToday = \App\Support\TaskBusinessTime::incentiveToday()->toDateString();
        $daysLeft = function ($value) use ($cutoffInput, $businessToday) {
            $iso = $cutoffInput($value);
            if ($iso === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $businessToday)) {
                return null;
            }
            $start = strtotime($businessToday.' 00:00:00');
            $end = strtotime($iso.' 00:00:00');
            if ($start === false || $end === false) {
                return null;
            }
            return (int) round(($end - $start) / 86400);
        };
        $daysLeftText = function ($days) {
            if ($days === null) {
                return '—';
            }
            if ($days < 0) {
                $n = abs($days);
                return $n.' day'.($n === 1 ? '' : 's').' over';
            }
            if ($days === 0) {
                return 'Today';
            }
            if ($days === 1) {
                return '1 day';
            }
            return $days.' days';
        };
        $daysLeftHtml = function ($days) use ($daysLeftText) {
            $text = $daysLeftText($days);
            if ($days !== null && $days > 1) {
                return '<span class="inc-countdown">'.e($text).'</span>';
            }
            return e($text);
        };
        $withIncentive = collect($people)->filter(fn ($person) => count($person['items'] ?? []) > 0)->count();
        $viewerId = (int) ($viewerId ?? auth()->id());
        $extendRequests = $extendRequests ?? [];
        $canApproveClaims = ! empty($canApproveClaims);
        $visibleRequests = array_values(array_filter($extendRequests, function ($req) use ($canApproveClaims) {
            return ($req['request_type'] ?? '') !== 'claim' || $canApproveClaims;
        }));
    @endphp

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-bold mb-1">{{ ($errors->has('decision') || $errors->has('review_note') || $errors->has('retry_cutoff_date')) ? 'Could not review this request.' : 'Could not save incentives.' }}</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="inc-hero">
        <div>
            <h4>Incentives</h4>
            <div class="inc-des">{{ $withIncentive }} of {{ count($people) }} {{ count($people) === 1 ? 'person' : 'people' }} with an incentive</div>
        </div>
        <div class="inc-total-pill" title="Active incentive total">
            ₹
            <span id="inc-total-pill">{{ $rupee($total) }}</span>
        </div>
    </div>

    @if ($canViewAll)
        <div class="inc-card mb-3">
            <label for="inc-search" class="form-label fw-bold mb-1">Quick search</label>
            <input type="search" id="inc-search" class="form-control" placeholder="Search name, designation, target, or condition" autocomplete="off">
            <div class="text-muted small mt-2" id="inc-search-count"></div>
        </div>
    @endif

    @if (count($alerts) && ! session()->hasOldInput())
        <div class="alert alert-danger">
            @if ($canEdit)
                <form method="post" action="{{ route('incentives.alerts.clear') }}" class="float-end ms-3">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-light border">Clear</button>
                </form>
            @endif
            @foreach ($alerts as $alert)
                <div>{{ $alert }}</div>
            @endforeach
        </div>
    @endif

    @if (count($visibleRequests))
        <div class="inc-pending-card">
            <h5>Requests waiting for you</h5>
            <div class="inc-pending-list">
                @foreach ($visibleRequests as $req)
                    <div class="inc-pending-item">
                        <div>
                            <p class="fw-bold mb-1">{{ $req['user_name'] ?: 'Team member' }} · {{ $req['title'] ?: 'Incentive' }}</p>
                            <p class="small mb-1">
                                @if (($req['request_type'] ?? '') === 'claim')
                                    {{ $req['requested_by'] ?: 'Someone' }} asked to claim {{ isset($req['amount']) && $req['amount'] !== null ? $rupee($req['amount']) : 'this incentive' }} onto the salary sheet.
                                @elseif (($req['request_type'] ?? '') === 'help')
                                    {{ $req['requested_by'] ?: 'Someone' }} needs help to achieve this incentive.
                                @elseif (($req['request_type'] ?? '') === 'end')
                                    {{ $req['requested_by'] ?: 'Someone' }} asked to end this incentive.
                                @else
                                    {{ $req['requested_by'] ?: 'Someone' }} asked to move the cutoff
                                    from {{ $cutoffLabel($req['current_cutoff'] ?? '') }}
                                    to {{ $cutoffLabel($req['requested_cutoff'] ?? '') }}.
                                @endif
                            </p>
                            @if (!empty($req['reason']))
                                <p class="small text-muted mb-0">Reason: {{ $req['reason'] }}</p>
                            @endif
                        </div>
                        @if (($req['request_type'] ?? '') === 'claim')
                            <button type="button"
                                class="btn btn-sm btn-primary inc-claim-review"
                                data-bs-toggle="modal"
                                data-bs-target="#inc-claim-review-modal"
                                data-id="{{ $req['id'] }}"
                                data-name="{{ $req['user_name'] ?? '' }}"
                                data-title="{{ $req['title'] ?? '' }}"
                                data-amount="{{ isset($req['amount']) ? $rupee($req['amount']) : '' }}"
                                data-cutoff="">
                                Review
                            </button>
                        @else
                        <div class="inc-review-btns">
                            <form method="post" action="{{ route('incentives.extend.review') }}">
                                @csrf
                                <input type="hidden" name="id" value="{{ $req['id'] }}">
                                <input type="hidden" name="decision" value="approve">
                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                            </form>
                            <form method="post" action="{{ route('incentives.extend.review') }}" class="inc-deny-form">
                                @csrf
                                <input type="hidden" name="id" value="{{ $req['id'] }}">
                                <input type="hidden" name="decision" value="deny">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Deny</button>
                            </form>
                        </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div id="inc-people">
        @forelse ($people as $person)
            @php
                $editing = $canEdit && (int) $editUserId === (int) $person['id'];
                $rows = $person['items'];
                if ($editing && (int) old('user_id') === (int) $person['id'] && is_array(old('items'))) {
                    $rows = old('items');
                }
                $activeRows = array_values(array_filter($rows, fn ($row) => $isActive($row['is_active'] ?? true)));
                if ($editing && $rows === [] && ! (is_array(old('items')) && (int) old('user_id') === (int) $person['id'])) {
                    $rows = [[
                        'id' => null,
                        'title' => '',
                        'amount' => null,
                        'body' => '',
                        'additional_condition' => $defaultCutoff,
                        'is_active' => true,
                    ]];
                }
                $shown = $editing ? $rows : $activeRows;
                $canRequest = (int) $viewerId === (int) $person['id'];
                $showActions = true;
                $personTotal = 0;
                foreach ($shown as $row) {
                    if (! $isActive($row['is_active'] ?? true)) {
                        continue;
                    }
                    $personTotal += is_numeric($row['amount'] ?? null) ? (float) $row['amount'] : 0;
                }
            @endphp
            <section class="inc-card inc-person" id="inc-user-{{ $person['id'] }}" data-search="{{ $person['search'] }}">
                <div class="inc-person-head">
                    <div>
                        <h5>{{ $person['name'] }}</h5>
                        @if (!empty($person['designation']))
                            <div class="inc-des">{{ $person['designation'] }}</div>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="inc-total-pill">₹<span class="inc-person-total">{{ $rupee($personTotal) }}</span></span>
                        @if ($canEdit && ! $editing)
                            <a class="btn btn-sm btn-outline-success" href="{{ route('incentives.index', ['user_id' => $person['id']]).'#inc-user-'.$person['id'] }}">Edit</a>
                        @endif
                    </div>
                </div>

                @if ($editing)
                    <form method="post" action="{{ route('incentives.update') }}" class="inc-form">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $person['id'] }}">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-bold text-uppercase">Edit incentives</span>
                            <button type="button" class="btn btn-sm btn-outline-success inc-add-row">Add row</button>
                        </div>
                        <div class="table-responsive">
                            <table class="inc-table">
                                <thead>
                                    <tr>
                                        <th>Target</th>
                                        <th>Incentive</th>
                                        <th>Condition</th>
                                        <th>CutOff Date</th>
                                        <th>Days Left</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody class="inc-rows">
                                    @foreach ($rows as $idx => $row)
                                        @php
                                            $activeOn = $isActive($row['is_active'] ?? true);
                                            $cutoff = $cutoffInput($row['additional_condition'] ?? '') ?: $defaultCutoff;
                                            $left = $daysLeft($cutoff);
                                        @endphp
                                        <tr class="inc-row {{ $activeOn ? '' : 'is-inactive' }}" data-idx="{{ $idx }}">
                                            <td>
                                                @if (!empty($row['id']))
                                                    <input type="hidden" name="items[{{ $idx }}][id]" value="{{ $row['id'] }}">
                                                @endif
                                                <input type="text" class="form-control form-control-sm" name="items[{{ $idx }}][title]" maxlength="200" placeholder="Target" value="{{ $row['title'] ?? '' }}">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm inc-amount" name="items[{{ $idx }}][amount]" min="0" step="1" placeholder="₹" value="{{ isset($row['amount']) && $row['amount'] !== '' && $row['amount'] !== null ? $row['amount'] : '' }}">
                                            </td>
                                            <td>
                                                <textarea class="form-control form-control-sm" name="items[{{ $idx }}][body]" rows="2" maxlength="5000" placeholder="Condition">{{ $row['body'] ?? '' }}</textarea>
                                            </td>
                                            <td>
                                                <input type="date" class="form-control form-control-sm inc-cutoff" name="items[{{ $idx }}][additional_condition]" value="{{ $cutoff }}">
                                                <div class="form-check mt-1">
                                                    <input type="hidden" name="items[{{ $idx }}][is_active]" value="0">
                                                    <input class="form-check-input inc-active" type="checkbox" name="items[{{ $idx }}][is_active]" value="1" id="inc-active-{{ $person['id'] }}-{{ $idx }}" {{ $activeOn ? 'checked' : '' }}>
                                                    <label class="form-check-label small" for="inc-active-{{ $person['id'] }}-{{ $idx }}">Active</label>
                                                </div>
                                                <div class="form-check mt-1" title="Tick when the target is achieved. The user gets a congratulations message in chat asking them to claim it.">
                                                    <input type="hidden" name="items[{{ $idx }}][is_achieved]" value="0">
                                                    <input class="form-check-input inc-achieved" type="checkbox" name="items[{{ $idx }}][is_achieved]" value="1" id="inc-achieved-{{ $person['id'] }}-{{ $idx }}" {{ ! empty($row['is_achieved']) ? 'checked' : '' }}>
                                                    <label class="form-check-label small" for="inc-achieved-{{ $person['id'] }}-{{ $idx }}">Achieved</label>
                                                </div>
                                            </td>
                                            <td class="inc-days {{ $left !== null && $left <= 1 ? 'inc-cutoff-hot' : '' }}">{!! $daysLeftHtml($left) !!}</td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger inc-remove" aria-label="Remove row">&times;</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td>Total</td>
                                        <td class="inc-amt inc-edit-total">{{ $rupee($personTotal) }}</td>
                                        <td colspan="4"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button type="submit" class="btn btn-success">Save incentives</button>
                            <a class="btn btn-light" href="{{ route('incentives.index').'#inc-user-'.$person['id'] }}">Cancel</a>
                        </div>
                        <p class="text-muted small mt-2 mb-0">Blank target rows are skipped. Saving with no targets removes this user's incentives.</p>
                    </form>
                @elseif (count($shown) === 0)
                    <p class="text-muted mb-0">No incentives assigned yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="inc-table">
                            <thead>
                                <tr>
                                    <th>Target</th>
                                    <th>Incentive</th>
                                    <th>Condition</th>
                                    <th>CutOff Date</th>
                                    <th>Days Left</th>
                                    @if ($showActions)
                                        <th>Action</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($shown as $row)
                                    @php
                                        $iso = $cutoffInput($row['additional_condition'] ?? '');
                                        $left = $daysLeft($iso);
                                        $achieved = ! empty($row['is_achieved']);
                                        $hot = ! $achieved && $left !== null && $left <= 1;
                                        $pending = $row['pending_extend'] ?? null;
                                        $claim = $row['pending_claim'] ?? null;
                                        $claimed = ! empty($row['is_claimed']);
                                        $claimAmount = is_numeric($row['amount'] ?? null) ? (float) $row['amount'] : 0;
                                    @endphp
                                    <tr class="{{ $hot ? 'is-cutoff' : '' }}">
                                        <td>{{ $row['title'] ?: '—' }}@if ($achieved) <span class="badge bg-success ms-1">Achieved</span>@endif</td>
                                        <td class="inc-amt">{{ $row['amount'] !== null && $row['amount'] !== '' ? $rupee($row['amount']) : '—' }}</td>
                                        <td>{!! nl2br(e($row['body'] ?: '—')) !!}</td>
                                        <td class="{{ $hot ? 'inc-cutoff-hot' : '' }}">{{ $cutoffLabel($row['additional_condition'] ?? '') }}</td>
                                        <td class="inc-days {{ $hot ? 'inc-cutoff-hot' : '' }}">{!! $daysLeftHtml($left) !!}</td>
                                        @if ($showActions)
                                            <td class="inc-actions">
                                                <div class="inc-action-btns">
                                                    @if ($canEdit)
                                                        <a class="inc-action-btn" href="{{ route('incentives.index', ['user_id' => $person['id']]).'#inc-user-'.$person['id'] }}" title="Edit" aria-label="Edit incentive">
                                                            <i class="ri-pencil-line" aria-hidden="true"></i>
                                                        </a>
                                                    @endif
                                                    @if (! empty($pending))
                                                        <span class="inc-request-badge" title="{{ !empty($pending['reason']) ? $pending['reason'] : 'Waiting for approval' }}">
                                                            @if (($pending['request_type'] ?? '') === 'help')
                                                                Need help
                                                            @elseif (($pending['request_type'] ?? '') === 'end')
                                                                Request to end
                                                            @else
                                                                Requested {{ $cutoffLabel($pending['requested_cutoff'] ?? '') }}
                                                            @endif
                                                        </span>
                                                        @if ($canEdit)
                                                            <div class="inc-review-btns">
                                                                <form method="post" action="{{ route('incentives.extend.review') }}">
                                                                    @csrf
                                                                    <input type="hidden" name="id" value="{{ $pending['id'] }}">
                                                                    <input type="hidden" name="decision" value="approve">
                                                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                                                </form>
                                                                <form method="post" action="{{ route('incentives.extend.review') }}" class="inc-deny-form">
                                                                    @csrf
                                                                    <input type="hidden" name="id" value="{{ $pending['id'] }}">
                                                                    <input type="hidden" name="decision" value="deny">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Deny</button>
                                                                </form>
                                                            </div>
                                                        @endif
                                                    @elseif (! empty($row['id']) && ! $achieved && ($canRequest || $canEdit))
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-warning inc-extend-btn inc-extend-date"
                                                            title="{{ $canEdit ? 'Extend cutoff date' : 'Request a later cutoff date' }}"
                                                            data-id="{{ $row['id'] }}"
                                                            data-name="{{ $person['name'] }}"
                                                            data-title="{{ $row['title'] ?? '' }}"
                                                            data-cutoff="{{ $iso }}"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#inc-extend-modal">
                                                            Extend
                                                        </button>
                                                    @endif
                                                    @if ($claimed)
                                                        <span class="inc-request-badge is-claimed">Claimed</span>
                                                    @elseif (! empty($claim))
                                                        <span class="inc-request-badge" title="Waiting for president@5core.com">Claim sent</span>
                                                        @if ($canApproveClaims)
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary inc-claim-review"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#inc-claim-review-modal"
                                                                data-id="{{ $claim['id'] }}"
                                                                data-name="{{ $person['name'] }}"
                                                                data-title="{{ $row['title'] ?? '' }}"
                                                                data-amount="{{ $rupee($claimAmount) }}"
                                                                data-cutoff="{{ $iso }}">
                                                                Review
                                                            </button>
                                                        @endif
                                                    @elseif ($canRequest && ! empty($row['id']) && $claimAmount > 0)
                                                        @if (! empty($row['claim_denial_reason']))
                                                            <span class="inc-request-badge" title="{{ $row['claim_denial_reason'] }}">Denied</span>
                                                        @endif
                                                        <form method="post" action="{{ route('incentives.claim') }}">
                                                            @csrf
                                                            <input type="hidden" name="id" value="{{ $row['id'] }}">
                                                            <button type="submit" class="btn btn-sm btn-success inc-extend-btn" title="Send a claim to president@5core.com">Claim</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Total</td>
                                    <td class="inc-amt">{{ $rupee($personTotal) }}</td>
                                    <td colspan="{{ $showActions ? 4 : 3 }}"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </section>
        @empty
            <div class="inc-card"><p class="text-muted mb-0">No incentives assigned yet.</p></div>
        @endforelse
    </div>
    <p class="text-muted small d-none mt-3 mb-0" id="inc-search-empty">No matching people.</p>
    <p class="text-muted small mt-3 mb-0">Incentives are managed by president@5core.com and software5@5core.com. Days Left counts from today (California time, {{ \App\Support\TaskBusinessTime::incentiveToday()->format('j M Y') }}) to the cutoff date.</p>

    <div class="modal fade inc-nx" id="inc-extend-modal" tabindex="-1" aria-labelledby="inc-extend-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" action="{{ route('incentives.extend') }}" class="modal-content inc-nx-sheet" data-kind="extend">
                @csrf
                <input type="hidden" name="id" id="inc-extend-id">
                <div class="inc-nx-head">
                    <div>
                        <div class="inc-nx-kicker">Incentive</div>
                        <h5 id="inc-extend-title">Make a request</h5>
                    </div>
                    <button type="button" class="inc-nx-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="inc-nx-body">
                    <p class="inc-nx-who" id="inc-extend-summary"></p>
                    <div class="inc-nx-choices" role="radiogroup" aria-label="Request type">
                        <label class="inc-nx-choice is-extend">
                            <input type="radio" name="request_type" value="extend" checked>
                            <span class="inc-nx-ico"><i class="ri-calendar-event-line"></i></span>
                            <span><strong>Extend date</strong><small>Ask for a later cutoff</small></span>
                        </label>
                        <label class="inc-nx-choice is-help">
                            <input type="radio" name="request_type" value="help">
                            <span class="inc-nx-ico"><i class="ri-lifebuoy-line"></i></span>
                            <span><strong>Need Help</strong><small>Help to achieve it</small></span>
                        </label>
                        <label class="inc-nx-choice is-end">
                            <input type="radio" name="request_type" value="end">
                            <span class="inc-nx-ico"><i class="ri-close-circle-line"></i></span>
                            <span><strong>Request to End</strong><small>Ask to close it</small></span>
                        </label>
                    </div>
                    <p class="inc-nx-note" id="inc-option-note">Extend date asks for a later cutoff. Add a reason if you want.</p>
                    <div class="inc-nx-field" id="inc-extend-date-wrap">
                        <label for="inc-extend-date">New cutoff date</label>
                        <input type="date" name="additional_condition" id="inc-extend-date" min="{{ $businessToday }}">
                    </div>
                    <div class="inc-nx-field">
                        <label for="inc-extend-reason">Reason</label>
                        <textarea name="reason" id="inc-extend-reason" maxlength="1000" placeholder="Why does this cutoff need more time?"></textarea>
                    </div>
                </div>
                <div class="inc-nx-foot">
                    <button type="button" class="inc-nx-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="inc-nx-submit" id="inc-extend-submit">Save date</button>
                </div>
            </form>
        </div>
    </div>

    @if ($canApproveClaims)
        <div class="modal fade inc-nx" id="inc-claim-review-modal" tabindex="-1" aria-labelledby="inc-claim-review-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="post" action="{{ route('incentives.extend.review') }}" class="modal-content inc-nx-sheet" id="inc-claim-review-form" data-kind="approve">
                    @csrf
                    <input type="hidden" name="id" id="inc-claim-review-id">
                    <div class="inc-nx-head">
                        <div>
                            <div class="inc-nx-kicker">Claim</div>
                            <h5 id="inc-claim-review-title">Review claim</h5>
                        </div>
                        <button type="button" class="inc-nx-close" data-bs-dismiss="modal" aria-label="Close">&times;</button>
                    </div>
                    <div class="inc-nx-body">
                        <p class="inc-nx-who" id="inc-claim-review-summary"></p>
                        <div class="inc-nx-choices" role="radiogroup" aria-label="Decision">
                            <label class="inc-nx-choice is-extend">
                                <input type="radio" name="decision" value="approve" checked>
                                <span class="inc-nx-ico"><i class="ri-check-line"></i></span>
                                <span><strong>Approve</strong><small>Add it to salary incentive</small></span>
                            </label>
                            <label class="inc-nx-choice is-end">
                                <input type="radio" name="decision" value="deny">
                                <span class="inc-nx-ico"><i class="ri-close-line"></i></span>
                                <span><strong>Deny</strong><small>Send a reason back</small></span>
                            </label>
                        </div>
                        <p class="inc-nx-note" id="inc-claim-review-note">Approve adds this amount to the Incentive column on the salary page.</p>
                        <div class="inc-nx-field d-none" id="inc-claim-deny-wrap">
                            <label for="inc-claim-review-note-input">Reason for denial</label>
                            <textarea name="review_note" id="inc-claim-review-note-input" maxlength="1000" placeholder="Why is this claim denied?"></textarea>
                        </div>
                        <label class="inc-retry-check d-none" id="inc-claim-retry-wrap">
                            <input type="checkbox" name="retry_cutoff" id="inc-claim-retry" value="1">
                            <span>Retry with a new cutoff date</span>
                        </label>
                        <div class="inc-nx-field d-none" id="inc-claim-retry-date-wrap">
                            <label for="inc-claim-retry-date">New cutoff date</label>
                            <input type="date" name="retry_cutoff_date" id="inc-claim-retry-date" min="{{ $businessToday }}">
                        </div>
                    </div>
                    <div class="inc-nx-foot">
                        <button type="button" class="inc-nx-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="inc-nx-submit" id="inc-claim-review-submit">Approve and add to incentive</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($canEdit)
        <template id="inc-row-template">
            <tr class="inc-row">
                <td>
                    <input type="text" class="form-control form-control-sm" data-name="title" maxlength="200" placeholder="Target">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm inc-amount" data-name="amount" min="0" step="1" placeholder="₹">
                </td>
                <td>
                    <textarea class="form-control form-control-sm" data-name="body" rows="2" maxlength="5000" placeholder="Condition"></textarea>
                </td>
                <td>
                    <input type="date" class="form-control form-control-sm inc-cutoff" data-name="additional_condition" value="{{ $defaultCutoff }}">
                    <div class="form-check mt-1">
                        <input type="hidden" data-name="is_active" value="0">
                        <input class="form-check-input inc-active" type="checkbox" data-name="is_active" value="1" checked>
                        <label class="form-check-label small">Active</label>
                    </div>
                    <div class="form-check mt-1" title="Tick when the target is achieved. The user gets a congratulations message in chat asking them to claim it.">
                        <input type="hidden" data-name="is_achieved" value="0">
                        <input class="form-check-input inc-achieved" type="checkbox" data-name="is_achieved" value="1">
                        <label class="form-check-label small">Achieved</label>
                    </div>
                </td>
                <td class="inc-days">—</td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger inc-remove" aria-label="Remove row">&times;</button>
                </td>
            </tr>
        </template>
    @endif
@endsection

@section('script')
<script>
(function () {
    var businessToday = @json($businessToday);

    function daysLeft(iso) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(iso || '') || !/^\d{4}-\d{2}-\d{2}$/.test(businessToday)) return null;
        var start = Date.parse(businessToday + 'T00:00:00');
        var end = Date.parse(iso + 'T00:00:00');
        if (isNaN(start) || isNaN(end)) return null;
        return Math.round((end - start) / 86400000);
    }
    function daysLeftText(days) {
        if (days === null) return '—';
        if (days < 0) {
            var n = Math.abs(days);
            return n + (n === 1 ? ' day over' : ' days over');
        }
        if (days === 0) return 'Today';
        if (days === 1) return '1 day';
        return days + ' days';
    }
    function paintDays(cell, iso) {
        if (!cell) return;
        var days = daysLeft(iso);
        var text = daysLeftText(days);
        if (days !== null && days > 1) {
            cell.innerHTML = '<span class="inc-countdown"></span>';
            cell.querySelector('.inc-countdown').textContent = text;
        } else {
            cell.textContent = text;
        }
        cell.classList.toggle('inc-cutoff-hot', days !== null && days <= 1);
    }
    function rupee(n) {
        return '₹' + Math.round(n || 0).toLocaleString('en-IN');
    }

    var search = document.getElementById('inc-search');
    var cards = document.querySelectorAll('.inc-person');
    var empty = document.getElementById('inc-search-empty');
    var count = document.getElementById('inc-search-count');
    function applySearch() {
        if (!search) return;
        var q = (search.value || '').trim().toLowerCase();
        var shown = 0;
        cards.forEach(function (card) {
            var hay = (card.getAttribute('data-search') || '').toLowerCase();
            var on = !q || hay.indexOf(q) !== -1;
            card.classList.toggle('d-none', !on);
            if (on) shown++;
        });
        if (count) count.textContent = q ? ('Showing ' + shown + ' of ' + cards.length) : '';
        if (empty) empty.classList.toggle('d-none', shown !== 0);
    }
    if (search) search.addEventListener('input', applySearch);

    var extendModal = document.getElementById('inc-extend-modal');
    if (extendModal) {
        extendModal.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) return;
            var id = btn.getAttribute('data-id') || '';
            var name = btn.getAttribute('data-name') || '';
            var title = btn.getAttribute('data-title') || '';
            var cutoff = btn.getAttribute('data-cutoff') || '';
            var idInput = document.getElementById('inc-extend-id');
            var dateInput = document.getElementById('inc-extend-date');
            var reasonInput = document.getElementById('inc-extend-reason');
            var summary = document.getElementById('inc-extend-summary');
            if (idInput) idInput.value = id;
            if (dateInput) {
                dateInput.value = cutoff && cutoff >= businessToday ? cutoff : businessToday;
                dateInput.min = businessToday;
            }
            if (reasonInput) reasonInput.value = '';
            if (summary) {
                summary.textContent = [name, title].filter(Boolean).join(' · ') || 'Incentive request';
            }
            var extendChoice = extendModal.querySelector('input[name="request_type"][value="extend"]');
            if (extendChoice) extendChoice.checked = true;
            syncRequestOption(extendModal);
        });
    }
    var canEditIncentive = @json((bool) $canEdit);
    function syncRequestOption(modal) {
        if (!modal) return;
        var picked = modal.querySelector('input[name="request_type"]:checked');
        var type = picked ? picked.value : 'extend';
        var dateWrap = modal.querySelector('[id$="extend-date-wrap"], #inc-extend-date-wrap');
        var dateInput = modal.querySelector('#inc-extend-date');
        var reason = modal.querySelector('#inc-extend-reason');
        var note = modal.querySelector('#inc-option-note');
        var submit = modal.querySelector('#inc-extend-submit');
        var showDate = type === 'extend';
        if (dateWrap) dateWrap.classList.toggle('d-none', !showDate);
        if (dateInput) dateInput.required = showDate;
        if (reason) {
            reason.required = type !== 'extend';
            reason.placeholder = type === 'help'
                ? 'What help do you need to achieve this incentive?'
                : (type === 'end' ? 'Why should this incentive end?' : 'Why does this cutoff need more time?');
        }
        if (note) {
            note.textContent = type === 'help'
                ? 'Need Help asks president@5core.com or software5@5core.com for help to achieve this incentive.'
                : (type === 'end'
                    ? 'Request to End asks them to close this incentive.'
                    : 'Extend date asks for a later cutoff. Add a reason if you want.');
        }
        var sheet = modal.querySelector('.inc-nx-sheet');
        if (sheet) sheet.setAttribute('data-kind', type);
        if (submit) {
            submit.textContent = type === 'help' ? 'Send help request' : (type === 'end' ? 'Request to End' : (canEditIncentive ? 'Save date' : 'Send extend request'));
        }
    }
    if (extendModal) {
        extendModal.addEventListener('change', function (e) {
            if (e.target && e.target.name === 'request_type') syncRequestOption(extendModal);
        });
    }
    var claimReview = document.getElementById('inc-claim-review-modal');
    function syncClaimReview() {
        if (!claimReview) return;
        var decision = claimReview.querySelector('input[name="decision"]:checked');
        var deny = decision && decision.value === 'deny';
        var denyWrap = document.getElementById('inc-claim-deny-wrap');
        var note = document.getElementById('inc-claim-review-note-input');
        var retryWrap = document.getElementById('inc-claim-retry-wrap');
        var retry = document.getElementById('inc-claim-retry');
        var dateWrap = document.getElementById('inc-claim-retry-date-wrap');
        var dateInput = document.getElementById('inc-claim-retry-date');
        var help = document.getElementById('inc-claim-review-note');
        var submit = document.getElementById('inc-claim-review-submit');
        var sheet = document.getElementById('inc-claim-review-form');
        if (denyWrap) denyWrap.classList.toggle('d-none', !deny);
        if (retryWrap) retryWrap.classList.toggle('d-none', !deny);
        if (note) note.required = !!deny;
        if (!deny && retry) retry.checked = false;
        var showDate = !!deny && !!(retry && retry.checked);
        if (dateWrap) dateWrap.classList.toggle('d-none', !showDate);
        if (dateInput) dateInput.required = showDate;
        if (help) {
            help.textContent = deny
                ? 'Deny sends your reason back. Tick retry to give a new cutoff date.'
                : 'Approve adds this amount to the Incentive column on the salary page.';
        }
        if (sheet) sheet.setAttribute('data-kind', deny ? 'end' : 'approve');
        if (submit) submit.textContent = deny ? 'Deny claim' : 'Approve and add to incentive';
    }
    if (claimReview) {
        claimReview.addEventListener('show.bs.modal', function (e) {
            var btn = e.relatedTarget;
            if (!btn) return;
            var idInput = document.getElementById('inc-claim-review-id');
            var summary = document.getElementById('inc-claim-review-summary');
            var note = document.getElementById('inc-claim-review-note-input');
            var retry = document.getElementById('inc-claim-retry');
            var dateInput = document.getElementById('inc-claim-retry-date');
            var approve = claimReview.querySelector('input[name="decision"][value="approve"]');
            if (idInput) idInput.value = btn.getAttribute('data-id') || '';
            if (summary) {
                var who = [btn.getAttribute('data-name') || '', btn.getAttribute('data-title') || ''].filter(Boolean).join(' · ');
                var amount = btn.getAttribute('data-amount') || '';
                summary.textContent = (who || 'Incentive claim') + (amount ? ' · ' + amount : '');
            }
            if (note) note.value = '';
            if (retry) retry.checked = false;
            if (dateInput) {
                var cutoff = btn.getAttribute('data-cutoff') || '';
                dateInput.value = cutoff && cutoff >= businessToday ? cutoff : businessToday;
                dateInput.min = businessToday;
            }
            if (approve) approve.checked = true;
            syncClaimReview();
        });
        claimReview.addEventListener('change', function (e) {
            if (!e.target) return;
            if (e.target.name === 'decision' || e.target.id === 'inc-claim-retry') syncClaimReview();
        });
    }

    document.querySelectorAll('.inc-deny-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm('Deny this extend request?')) e.preventDefault();
        });
    });

    document.querySelectorAll('.inc-form').forEach(function (form) {
        var rows = form.querySelector('.inc-rows');
        var template = document.getElementById('inc-row-template');
        var nextIdx = 0;
        rows.querySelectorAll('.inc-row').forEach(function (row) {
            var n = parseInt(row.getAttribute('data-idx'), 10);
            if (!isNaN(n) && n >= nextIdx) nextIdx = n + 1;
        });
        function refreshTotal() {
            var sum = 0;
            rows.querySelectorAll('.inc-row').forEach(function (row) {
                var active = row.querySelector('.inc-active');
                if (active && !active.checked) {
                    row.classList.add('is-inactive');
                    return;
                }
                row.classList.remove('is-inactive');
                var amount = row.querySelector('.inc-amount');
                var n = amount && amount.value !== '' ? parseFloat(amount.value) : 0;
                if (!isNaN(n)) sum += n;
            });
            var label = rupee(sum);
            var cell = form.querySelector('.inc-edit-total');
            var pill = form.closest('.inc-person') && form.closest('.inc-person').querySelector('.inc-person-total');
            if (cell) cell.textContent = label;
            if (pill) pill.textContent = label;
        }
        function nameRow(row, idx) {
            row.querySelectorAll('[data-name]').forEach(function (input) {
                input.name = 'items[' + idx + '][' + input.getAttribute('data-name') + ']';
            });
            var box = row.querySelector('.inc-active');
            var label = row.querySelector('.form-check-label');
            var id = 'inc-active-new-' + idx;
            if (box) box.id = id;
            if (label) label.setAttribute('for', id);
        }
        form.querySelector('.inc-add-row').addEventListener('click', function () {
            if (rows.querySelectorAll('.inc-row').length >= 25) return;
            var node = template.content.firstElementChild.cloneNode(true);
            node.setAttribute('data-idx', String(nextIdx));
            nameRow(node, nextIdx++);
            rows.appendChild(node);
            var cutoff = node.querySelector('.inc-cutoff');
            paintDays(node.querySelector('.inc-days'), cutoff ? cutoff.value : '');
            var title = node.querySelector('[data-name="title"]');
            if (title) title.focus();
            refreshTotal();
        });
        rows.addEventListener('click', function (e) {
            var btn = e.target.closest('.inc-remove');
            if (!btn) return;
            var row = btn.closest('.inc-row');
            if (row) row.remove();
            refreshTotal();
        });
        rows.addEventListener('input', function (e) {
            if (e.target.classList.contains('inc-amount')) refreshTotal();
            if (e.target.classList.contains('inc-cutoff')) {
                paintDays(e.target.closest('tr').querySelector('.inc-days'), e.target.value);
            }
        });
        rows.addEventListener('change', function (e) {
            if (e.target.classList.contains('inc-active') || e.target.classList.contains('inc-amount')) refreshTotal();
            if (e.target.classList.contains('inc-cutoff')) {
                paintDays(e.target.closest('tr').querySelector('.inc-days'), e.target.value);
            }
        });
        form.addEventListener('submit', function (e) {
            var titled = 0;
            rows.querySelectorAll('.inc-row').forEach(function (row) {
                var title = row.querySelector('[name$="[title]"]');
                if (title && title.value.trim() !== '') titled++;
            });
            if (titled === 0 && !window.confirm('Save with no targets? This removes all incentives for this user.')) {
                e.preventDefault();
            }
        });
    });
})();
</script>
@endsection
