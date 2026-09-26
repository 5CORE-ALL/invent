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
        $businessToday = \App\Support\TaskBusinessTime::today()->toDateString();
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
        $withIncentive = collect($people)->filter(fn ($person) => count($person['items'] ?? []) > 0)->count();
    @endphp

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-bold mb-1">Could not save incentives.</div>
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
            @foreach ($alerts as $alert)
                <div>{{ $alert }}</div>
            @endforeach
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
                                            </td>
                                            <td class="inc-days {{ $left !== null && $left <= 1 ? 'inc-cutoff-hot' : '' }}">{{ $daysLeftText($left) }}</td>
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
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($shown as $row)
                                    @php
                                        $iso = $cutoffInput($row['additional_condition'] ?? '');
                                        $left = $daysLeft($iso);
                                        $hot = $left !== null && $left <= 1;
                                    @endphp
                                    <tr class="{{ $hot ? 'is-cutoff' : '' }}">
                                        <td>{{ $row['title'] ?: '—' }}</td>
                                        <td class="inc-amt">{{ $row['amount'] !== null && $row['amount'] !== '' ? $rupee($row['amount']) : '—' }}</td>
                                        <td>{!! nl2br(e($row['body'] ?: '—')) !!}</td>
                                        <td class="{{ $hot ? 'inc-cutoff-hot' : '' }}">{{ $cutoffLabel($row['additional_condition'] ?? '') }}</td>
                                        <td class="inc-days {{ $hot ? 'inc-cutoff-hot' : '' }}">{{ $daysLeftText($left) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Total</td>
                                    <td class="inc-amt">{{ $rupee($personTotal) }}</td>
                                    <td colspan="3"></td>
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
    <p class="text-muted small mt-3 mb-0">Incentives are managed by president@5core.com. Days Left counts from today to the cutoff date.</p>

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
        cell.textContent = daysLeftText(days);
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
