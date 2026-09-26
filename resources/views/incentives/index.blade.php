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
    .inc-hero h4 {
        margin: 0;
        font-weight: 800;
        color: #0f172a;
    }
    .inc-hero .inc-des {
        color: #64748b;
        margin-top: 0.2rem;
    }
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
    .inc-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    .inc-table th,
    .inc-table td {
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
    .inc-table tfoot td {
        background: #ecfdf5;
        font-weight: 800;
        color: #166534;
        border-top: 2px solid #86efac;
    }
    .inc-table tr.is-cutoff td {
        background: #fef2f2;
    }
    .inc-table tr.is-inactive {
        opacity: 0.55;
    }
    .inc-cutoff-hot {
        color: #dc2626;
        font-weight: 800;
    }
    .inc-switcher {
        min-width: min(280px, 100%);
    }
</style>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Incentives',
        'sub_title' => 'Tasks',
    ])

    @php
        $formRows = old('items');
        if (! is_array($formRows)) {
            $formRows = collect($items)->map(function ($item) {
                return [
                    'id' => $item['id'] ?? null,
                    'title' => $item['title'] ?? '',
                    'amount' => $item['amount'] ?? null,
                    'body' => $item['body'] ?? '',
                    'additional_condition' => $item['additional_condition'] ?? '',
                    'is_active' => $item['is_active'] ?? true,
                ];
            })->all();
        }
        if ($canEdit && $formRows === [] && ! session()->hasOldInput()) {
            $formRows = [[
                'id' => null,
                'title' => '',
                'amount' => null,
                'body' => '',
                'additional_condition' => $defaultCutoff,
                'is_active' => true,
            ]];
        }

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
        $displayRows = $canEdit
            ? $formRows
            : array_values(array_filter($formRows, fn ($row) => $isActive($row['is_active'] ?? true)));
        $displayTotal = 0;
        foreach ($displayRows as $row) {
            if (! $isActive($row['is_active'] ?? true)) {
                continue;
            }
            $displayTotal += is_numeric($row['amount'] ?? null) ? (float) $row['amount'] : 0;
        }
        $businessToday = \App\Support\TaskBusinessTime::today()->toDateString();
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
            <h4>{{ $target->name }}</h4>
            @if ($target->designation)
                <div class="inc-des">{{ $target->designation }}</div>
            @endif
        </div>
        <div class="inc-total-pill" title="Active incentive total">
            ₹
            <span id="inc-total-pill">{{ $rupee($displayTotal) }}</span>
        </div>
    </div>

    @if ($canViewAll)
        <form method="get" action="{{ route('incentives.index') }}" class="inc-card mb-3">
            <label for="inc-user" class="form-label fw-bold mb-1">Show incentives for</label>
            <div class="d-flex flex-wrap gap-2">
                <select id="inc-user" name="user_id" class="form-select inc-switcher" onchange="this.form.submit()">
                    @foreach ($users as $user)
                        <option value="{{ $user['id'] }}" @selected((int) $user['id'] === (int) $target->id)>
                            {{ $user['name'] }}{{ !empty($user['designation']) ? ' — '.$user['designation'] : '' }}
                        </option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn-outline-secondary">Show</button></noscript>
            </div>
        </form>
    @endif

    @if (count($alerts) && ! session()->hasOldInput())
        <div class="alert alert-danger">
            @foreach ($alerts as $alert)
                <div>{{ $alert }}</div>
            @endforeach
        </div>
    @endif

    <div class="inc-card">
        @if ($canEdit)
            <form method="post" action="{{ route('incentives.update') }}" id="inc-form">
                @csrf
                <input type="hidden" name="user_id" value="{{ $target->id }}">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-muted small fw-bold text-uppercase">Edit incentives</span>
                    <button type="button" class="btn btn-sm btn-outline-success" id="inc-add-row">Add row</button>
                </div>
                <div class="table-responsive">
                    <table class="inc-table">
                        <thead>
                            <tr>
                                <th>Target</th>
                                <th>Incentive</th>
                                <th>Condition</th>
                                <th>CutOff Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="inc-rows">
                            @foreach ($formRows as $idx => $row)
                                @php
                                    $activeOn = $isActive($row['is_active'] ?? true);
                                    $cutoff = $cutoffInput($row['additional_condition'] ?? '') ?: $defaultCutoff;
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
                                        <input type="date" class="form-control form-control-sm" name="items[{{ $idx }}][additional_condition]" value="{{ $cutoff }}">
                                        <div class="form-check mt-1">
                                            <input type="hidden" name="items[{{ $idx }}][is_active]" value="0">
                                            <input class="form-check-input inc-active" type="checkbox" name="items[{{ $idx }}][is_active]" value="1" id="inc-active-{{ $idx }}" {{ $activeOn ? 'checked' : '' }}>
                                            <label class="form-check-label small" for="inc-active-{{ $idx }}">Active</label>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger inc-remove" aria-label="Remove row">&times;</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td>Total</td>
                                <td class="inc-amt" id="inc-total-cell">{{ $rupee($displayTotal) }}</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <button type="submit" class="btn btn-success mt-3">Save incentives</button>
                <p class="text-muted small mt-2 mb-0">Blank target rows are skipped. Saving with no targets removes this user's incentives.</p>
            </form>
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
                        <input type="date" class="form-control form-control-sm" data-name="additional_condition" value="{{ $defaultCutoff }}">
                        <div class="form-check mt-1">
                            <input type="hidden" data-name="is_active" value="0">
                            <input class="form-check-input inc-active" type="checkbox" data-name="is_active" value="1" checked>
                            <label class="form-check-label small">Active</label>
                        </div>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger inc-remove" aria-label="Remove row">&times;</button>
                    </td>
                </tr>
            </template>
        @elseif (count($displayRows) === 0)
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
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($displayRows as $row)
                            @php
                                $iso = $cutoffInput($row['additional_condition'] ?? '');
                                $hot = $iso !== '' && $iso <= \Carbon\Carbon::parse($businessToday)->addDay()->toDateString();
                            @endphp
                            <tr class="{{ $hot ? 'is-cutoff' : '' }}">
                                <td>{{ $row['title'] ?: '—' }}</td>
                                <td class="inc-amt">{{ $row['amount'] !== null && $row['amount'] !== '' ? $rupee($row['amount']) : '—' }}</td>
                                <td>{!! nl2br(e($row['body'] ?: '—')) !!}</td>
                                <td class="{{ $hot ? 'inc-cutoff-hot' : '' }}">{{ $cutoffLabel($row['additional_condition'] ?? '') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="inc-amt">{{ $rupee($displayTotal) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        <p class="text-muted small mt-3 mb-0">Incentives are managed by president@5core.com.</p>
    </div>
@endsection

@section('script')
<script>
(function () {
    var form = document.getElementById('inc-form');
    if (!form) return;
    var rows = document.getElementById('inc-rows');
    var template = document.getElementById('inc-row-template');
    var nextIdx = 0;
    rows.querySelectorAll('.inc-row').forEach(function (row) {
        var n = parseInt(row.getAttribute('data-idx'), 10);
        if (!isNaN(n) && n >= nextIdx) nextIdx = n + 1;
    });

    function rupee(n) {
        var rounded = Math.round(n || 0);
        return '₹' + rounded.toLocaleString('en-IN');
    }
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
        var pill = document.getElementById('inc-total-pill');
        var cell = document.getElementById('inc-total-cell');
        if (pill) pill.textContent = label;
        if (cell) cell.textContent = label;
    }
    function nameRow(row, idx) {
        row.querySelectorAll('[data-name]').forEach(function (input) {
            input.name = 'items[' + idx + '][' + input.getAttribute('data-name') + ']';
        });
        var box = row.querySelector('.inc-active');
        var label = row.querySelector('.form-check-label');
        if (box) box.id = 'inc-active-' + idx;
        if (label) label.setAttribute('for', 'inc-active-' + idx);
    }

    document.getElementById('inc-add-row').addEventListener('click', function () {
        if (rows.querySelectorAll('.inc-row').length >= 25) return;
        var node = template.content.firstElementChild.cloneNode(true);
        node.setAttribute('data-idx', String(nextIdx));
        nameRow(node, nextIdx++);
        rows.appendChild(node);
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
    });
    rows.addEventListener('change', function (e) {
        if (e.target.classList.contains('inc-active') || e.target.classList.contains('inc-amount')) refreshTotal();
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
})();
</script>
@endsection
