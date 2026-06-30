@extends('layouts.vertical', ['title' => $title ?? 'Generate Payroll'])

@section('css')
<style>
    .pr-card { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
    .pr-toolbar .form-select, .pr-toolbar .form-control { min-height: 34px; font-size: .85rem; }
    .pr-table {
        font-size: .85rem;
        margin-bottom: 0;
        table-layout: fixed;
        width: 100%;
        min-width: 980px;
    }
    .pr-table thead th {
        font-size: .72rem; text-transform: uppercase; letter-spacing: .03em;
        color: #64748b; font-weight: 600; white-space: nowrap; vertical-align: middle;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .pr-table tbody td {
        vertical-align: middle;
        overflow: hidden;
    }
    .pr-table .name-col {
        font-weight: 600; color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 0;
    }
    .pr-table .time-col {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        width: 120px;
    }
    .pr-table .pay-col {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        text-align: right;
        width: 130px;
    }
    .pr-table .input-col { width: 100px; }
    .pr-table .input-col-wide { width: 110px; }
    .pr-table .input-col input,
    .pr-table .input-col select {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        font-size: .82rem;
        font-variant-numeric: tabular-nums;
    }
    .pr-table .input-col-manual { width: 88px; }
    .pr-table .input-col-manual input { text-align: center; }
</style>
@endsection

@section('content')
<div class="container-fluid" id="attendancePayroll"
     data-csrf="{{ csrf_token() }}"
     data-save-base="{{ url('/attendance/payroll/lines') }}">

    <div class="row mb-3">
        <div class="col-12">
            <div class="pr-card p-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <h4 class="mb-0">Generate Payroll</h4>
                        <div class="text-muted small">Hours from attendance · pay = (worked + manual) × rate + adjustment</div>
                    </div>
                    <a href="{{ route('attendance.payroll.export', request()->query()) }}" class="btn btn-primary btn-sm" id="btnDownload">
                        <i class="ri-download-line me-1"></i> Download Payroll
                    </a>
                </div>

                <form method="get" class="d-flex flex-wrap align-items-end gap-2 pr-toolbar" id="filterForm">
                    <div>
                        <label class="form-label small text-muted mb-0">Team</label>
                        <select name="team" class="form-select form-select-sm">
                            <option value="all" {{ $team === 'all' ? 'selected' : '' }}>ALL</option>
                            @foreach($teams as $t)
                            <option value="{{ $t }}" {{ $team === $t ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-0">Start Date</label>
                        <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-0">End Date</label>
                        <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-0">Timezone</label>
                        <select name="timezone" class="form-select form-select-sm">
                            @foreach(['Asia/Kolkata' => 'GMT+0530', 'America/Los_Angeles' => 'GMT-0700', 'America/New_York' => 'GMT-0400', 'Asia/Shanghai' => 'GMT+0800'] as $tz => $label)
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
                </form>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="pr-card p-0 overflow-auto">
                <table class="table table-hover pr-table mb-0">
                    <colgroup>
                        <col style="width:24%">
                        <col style="width:120px">
                        <col style="width:88px">
                        <col style="width:100px">
                        <col style="width:88px">
                        <col style="width:100px">
                        <col style="width:130px">
                    </colgroup>
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Total Time Worked</th>
                            <th>Manual Time</th>
                            <th>Hourly Pay Rate</th>
                            <th>Currency</th>
                            <th>Adjustment</th>
                            <th>Total Pay</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                        <tr data-user-id="{{ $row['user_id'] }}">
                            <td class="name-col">{{ $row['name'] }}</td>
                            <td class="time-col worked-cell">{{ $row['worked_label'] }}</td>
                            <td class="input-col input-col-manual">
                                <input type="text" class="form-control form-control-sm line-manual"
                                       value="{{ $row['manual_label'] }}" placeholder="0h 0" title="e.g. 2h 30">
                            </td>
                            <td class="input-col input-col-wide">
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm line-rate"
                                       value="{{ number_format((float) $row['hourly_rate'], 2, '.', '') }}">
                            </td>
                            <td class="input-col">
                                <select class="form-select form-select-sm line-currency">
                                    @foreach($currencies as $cur)
                                    <option value="{{ $cur }}" {{ $row['currency'] === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="input-col">
                                <input type="number" step="0.01" class="form-control form-control-sm line-adjustment"
                                       value="{{ number_format((float) $row['adjustment'], 2, '.', '') }}">
                            </td>
                            <td class="pay-col pay-cell">{{ $row['total_pay_label'] }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No employees found for this team and period.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(function() {
    const root = document.getElementById('attendancePayroll');
    const csrf = root.dataset.csrf;
    const filterForm = document.getElementById('filterForm');
    const params = () => new URLSearchParams(new FormData(filterForm));

    filterForm?.querySelectorAll('select, input').forEach(el => {
        el.addEventListener('change', () => filterForm.submit());
    });

    function updateDownloadLink() {
        const btn = document.getElementById('btnDownload');
        if (!btn) return;
        const base = @json(route('attendance.payroll.export'));
        btn.href = base + '?' + params().toString();
    }

    updateDownloadLink();

    function formatPay(currency, amount) {
        return currency + ' ' + amount.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function recalcRow(tr) {
        const workedText = tr.querySelector('.worked-cell')?.textContent?.trim() || '0h 0';
        const manualText = tr.querySelector('.line-manual')?.value?.trim() || '0h 0';
        const rate = parseFloat(tr.querySelector('.line-rate')?.value || '0') || 0;
        const currency = tr.querySelector('.line-currency')?.value || 'USD';
        const adjustment = parseFloat(tr.querySelector('.line-adjustment')?.value || '0') || 0;

        const workedSec = parseDuration(workedText);
        const manualSec = parseDuration(manualText);
        const hours = (workedSec + manualSec) / 3600;
        const total = (hours * rate) + adjustment;

        const payCell = tr.querySelector('.pay-cell');
        if (payCell) {
            payCell.textContent = formatPay(currency, total);
        }
    }

    function parseDuration(text) {
        const m = String(text).trim().match(/^(\d+)\s*h(?:\s*(\d+))?$/i);
        if (!m) return 0;
        return (parseInt(m[1], 10) * 3600) + (parseInt(m[2] || '0', 10) * 60);
    }

    async function saveRow(tr) {
        const userId = tr.dataset.userId;
        if (!userId) return;

        const url = root.dataset.saveBase + '/' + userId;
        const body = {
            from: filterForm.querySelector('[name=from]')?.value,
            to: filterForm.querySelector('[name=to]')?.value,
            timezone: filterForm.querySelector('[name=timezone]')?.value,
            manual_time: tr.querySelector('.line-manual')?.value || '',
            hourly_rate: tr.querySelector('.line-rate')?.value || '0',
            currency: tr.querySelector('.line-currency')?.value || 'USD',
            adjustment: tr.querySelector('.line-adjustment')?.value || '0',
        };

        try {
            const r = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            });
            if (!r.ok) return;
            const data = await r.json();
            if (data.row) {
                const payCell = tr.querySelector('.pay-cell');
                if (payCell) payCell.textContent = data.row.total_pay_label;
            }
        } catch (_) {}
    }

    document.querySelectorAll('tbody tr[data-user-id]').forEach(tr => {
        tr.querySelectorAll('.line-manual, .line-rate, .line-currency, .line-adjustment').forEach(input => {
            input.addEventListener('input', () => recalcRow(tr));
            input.addEventListener('change', () => {
                recalcRow(tr);
                saveRow(tr);
            });
        });
    });
})();
</script>
@endsection
