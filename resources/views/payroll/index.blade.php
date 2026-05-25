@extends('layouts.vertical', ['title' => 'Payroll'])

@section('css')
<style>
    .payroll-card { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
    .payroll-month-select { min-width: 220px; }
    .payroll-stat { border-radius: 10px; padding: .75rem 1rem; background: #f8f9fa; }
    .payroll-stat .val { font-size: 1.25rem; font-weight: 700; }
    .payroll-locked { background: rgba(220,53,69,.08); border: 1px solid rgba(220,53,69,.2); }
    .nav-tabs .nav-link { font-size: .875rem; }
    .table-payroll { font-size: .85rem; }
</style>
@endsection

@section('content')
<div class="container-fluid" id="payrollApp"
     data-can-manage="{{ $canManage ? '1' : '0' }}"
     data-csrf="{{ csrf_token() }}"
     data-active-month-id="{{ $activeMonth?->id }}"
     data-base-url="{{ url('/payroll') }}">

    <div class="row mb-3">
        <div class="col-12">
            <div class="payroll-card p-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h4 class="mb-1"><i class="ri-wallet-3-line me-2 text-primary"></i>Payroll</h4>
                        <p class="text-muted mb-0 small">Mini payroll system — months, salaries, payslips &amp; history</p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <select class="form-select form-select-sm payroll-month-select" id="payrollMonthSelect">
                            @forelse($months as $m)
                                <option value="{{ $m->id }}" {{ $activeMonth?->id === $m->id ? 'selected' : '' }}
                                    data-locked="{{ $m->is_locked ? '1' : '0' }}"
                                    data-status="{{ $m->status }}"
                                    data-format="{{ $m->payslip_format }}">
                                    {{ $m->month_label }} ({{ ucfirst($m->status) }})
                                </option>
                            @empty
                                <option value="">No payroll months yet</option>
                            @endforelse
                        </select>
                        @if($canManage)
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createMonthModal">
                            <i class="ri-calendar-line me-1"></i> Create Payroll Month
                        </button>
                        <a href="{{ route('users.add') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="ri-team-line me-1"></i> Team Management
                        </a>
                        @endif
                    </div>
                </div>
                <div class="row g-2 mt-3" id="monthStats">
                    <div class="col-6 col-md-3"><div class="payroll-stat"><div class="text-muted small">Employees</div><div class="val" id="statEmployees">—</div></div></div>
                    <div class="col-6 col-md-3"><div class="payroll-stat"><div class="text-muted small">Total Net</div><div class="val" id="statNet">—</div></div></div>
                    <div class="col-6 col-md-3"><div class="payroll-stat"><div class="text-muted small">Status</div><div class="val text-capitalize" id="statStatus">—</div></div></div>
                    <div class="col-6 col-md-3"><div class="payroll-stat" id="lockStatBox"><div class="text-muted small">Lock</div><div class="val" id="statLock">—</div></div></div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs nav-bordered mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-employees">Employees</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-components">Salary Components</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payments">Payments &amp; Deductions</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payslips">Payslips &amp; IT</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-arrears">Arrears</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-settlement">Full &amp; Final</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-previous">Previous Payroll</button></li>
        @if($canManage)
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-actions">Actions</button></li>
        @endif
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-employees">
            <div class="payroll-card p-3">
                <div class="d-flex justify-content-between mb-2">
                    <h6 class="mb-0">Employee Salaries</h6>
                    @if($canManage)
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnSyncEmployees"><i class="ri-refresh-line"></i> Sync from Team</button>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-payroll" id="employeesTable">
                        <thead><tr>
                            <th>Name</th><th>Hours LM</th><th>Salary PP</th><th>Incr</th><th>Amt LM</th><th>Amt P</th><th></th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-components">
            <div class="payroll-card p-3">
                @if($canManage)
                <form id="formComponent" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <select name="user_id" class="form-select form-select-sm" required>
                            <option value="">Employee</option>
                            @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="type" class="form-select form-select-sm"><option value="earning">Earning</option><option value="deduction">Deduction</option></select>
                    </div>
                    <div class="col-md-3"><input type="text" name="label" class="form-control form-control-sm" placeholder="Label" required></div>
                    <div class="col-md-2"><input type="number" name="amount" class="form-control form-control-sm" placeholder="Amount" step="0.01" required></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100">Add Component</button></div>
                </form>
                @endif
                <div class="table-responsive"><table class="table table-sm table-payroll" id="componentsTable"><thead><tr><th>Employee</th><th>Type</th><th>Label</th><th>Amount</th><th></th></tr></thead><tbody></tbody></table></div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-payments">
            <div class="payroll-card p-3">
                @if($canManage)
                <form id="formPayment" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <select name="user_id" class="form-select form-select-sm" required>
                            <option value="">Employee</option>
                            @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="entry_type" class="form-select form-select-sm"><option value="payment">Payment</option><option value="deduction">Deduction</option></select>
                    </div>
                    <div class="col-md-3"><input type="text" name="label" class="form-control form-control-sm" placeholder="Label" required></div>
                    <div class="col-md-2"><input type="number" name="amount" class="form-control form-control-sm" placeholder="Amount" required></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100">Add</button></div>
                </form>
                @endif
                <div class="table-responsive"><table class="table table-sm table-payroll" id="paymentsTable"><thead><tr><th>Employee</th><th>Type</th><th>Label</th><th>Amount</th><th></th></tr></thead><tbody></tbody></table></div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-payslips">
            <div class="payroll-card p-3">
                <p class="small text-muted">Formats: Standard, Detailed (with line items), Compact summary.</p>
                <div class="table-responsive"><table class="table table-sm table-payroll" id="payslipsTable"><thead><tr><th>Employee</th><th>Hours</th><th>Amt LM</th><th>Format</th><th>Amt P</th><th>Released</th><th></th></tr></thead><tbody></tbody></table></div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-arrears">
            <div class="payroll-card p-3">
                @if($canManage)
                <form id="formArrear" class="row g-2 mb-3">
                    <div class="col-md-2">
                        <select name="user_id" class="form-select form-select-sm" required>
                            <option value="">Employee</option>
                            @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="adjustment_type" class="form-select form-select-sm" title="Add or deduct from final salary">
                            <option value="add">Add to salary</option>
                            <option value="deduct">Deduct from salary</option>
                        </select>
                    </div>
                    <div class="col-md-2"><input type="number" name="amount" class="form-control form-control-sm" placeholder="Amount" step="0.01" min="0" required></div>
                    <div class="col-md-2">
                        <input type="text" name="arrear_for_month" class="form-control form-control-sm" placeholder="Arrear month e.g. March 2026" title="Which month this arrear is for">
                    </div>
                    <div class="col-md-2"><input type="text" name="description" class="form-control form-control-sm" placeholder="Note"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary w-100">Add Arrear</button></div>
                </form>
                <p class="small text-muted mb-2">Paid in selected payroll month <strong id="arrearPayrollMonth">—</strong>. Use <strong>Arrear month</strong> for the period the money is owed for (e.g. March 2026).</p>
                @endif
                <div class="table-responsive"><table class="table table-sm table-payroll" id="arrearsTable"><thead><tr><th>Employee</th><th>Type</th><th>Amount</th><th>Arrear month</th><th>Payroll month</th><th>Status</th><th></th></tr></thead><tbody></tbody></table></div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-settlement">
            <div class="payroll-card p-3">
                @if($canManage)
                <form id="formSettlement" class="row g-2 mb-3">
                    <div class="col-md-4">
                        <select name="user_id" class="form-select form-select-sm" required>
                            <option value="">Exiting employee</option>
                            @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><input type="date" name="last_working_date" class="form-control form-control-sm"></div>
                    <div class="col-md-3"><input type="text" name="notes" class="form-control form-control-sm" placeholder="Notes"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-sm btn-danger w-100">Create Settlement</button></div>
                </form>
                @endif
                <div class="table-responsive"><table class="table table-sm table-payroll" id="settlementsTable"><thead><tr><th>Employee</th><th>LWD</th><th>Net</th><th>Status</th><th></th></tr></thead><tbody></tbody></table></div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-previous">
            <div class="payroll-card p-3">
                <h6 class="mb-2">Previous Payroll Records</h6>
                <p class="small text-muted">Add historical payroll manually or import CSV (columns: Name/Email, Month, Net, Gross, Deductions).</p>
                @if($canManage)
                <div class="row g-2 mb-3">
                    <form id="formPrevious" class="col-lg-8 row g-2">
                        <div class="col-md-3">
                            <select name="user_id" class="form-select form-select-sm" required>
                                <option value="">Employee</option>
                                @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-2"><input type="text" name="month_label" class="form-control form-control-sm" placeholder="e.g. Jan 2024" required></div>
                        <div class="col-md-2"><input type="number" name="net_amount" class="form-control form-control-sm" placeholder="Net" required></div>
                        <div class="col-md-2"><input type="number" name="gross_amount" class="form-control form-control-sm" placeholder="Gross"></div>
                        <div class="col-md-3"><button type="submit" class="btn btn-sm btn-success w-100">Add Previous Record</button></div>
                    </form>
                    <div class="col-lg-4">
                        <form id="formImportPrevious" enctype="multipart/form-data">
                            <div class="input-group input-group-sm">
                                <input type="file" name="file" class="form-control" accept=".csv,.txt" required>
                                <button class="btn btn-outline-secondary" type="submit">Import CSV</button>
                            </div>
                        </form>
                    </div>
                </div>
                @endif
                <div class="table-responsive"><table class="table table-sm table-payroll" id="previousTable"><thead><tr><th>Employee</th><th>Month</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Imported</th></tr></thead><tbody></tbody></table></div>
            </div>
        </div>

        @if($canManage)
        <div class="tab-pane fade" id="tab-actions">
            <div class="payroll-card p-3">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small">Payslip format</label>
                        <select id="payslipFormatSelect" class="form-select form-select-sm">
                            @foreach($payslipFormats as $k => $label)
                                <option value="{{ $k }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Month status</label>
                        <select id="monthStatusSelect" class="form-select form-select-sm">
                            @foreach($monthStatuses as $k => $label)
                                <option value="{{ $k }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnRecalculate"><i class="ri-calculator-line"></i> Recalculate All</button>
                    <button type="button" class="btn btn-sm btn-outline-success" id="btnGeneratePayslips"><i class="ri-file-text-line"></i> Generate Payslips</button>
                    <button type="button" class="btn btn-sm btn-success" id="btnReleasePayslips"><i class="ri-send-plane-line"></i> Release Payslips</button>
                    <button type="button" class="btn btn-sm btn-info" id="btnReleaseIt"><i class="ri-file-chart-line"></i> Release IT Statements</button>
                    <button type="button" class="btn btn-sm btn-warning" id="btnToggleLock"><i class="ri-lock-line"></i> Lock / Unlock</button>
                    <a href="#" class="btn btn-sm btn-outline-secondary" id="btnExportCsv"><i class="ri-download-line"></i> Export CSV</a>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnSyncNewHires"><i class="ri-user-add-line"></i> Add New Hires Only</button>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@if($canManage)
<div class="modal fade" id="createMonthModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formCreateMonth">
                <div class="modal-header"><h5 class="modal-title">Create Payroll Month</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Month label</label>
                        <input type="text" name="month_label" class="form-control" value="{{ $defaultMonthLabel }}" placeholder="April 2026" required>
                        <div class="form-text">Use format like "April 2026" (matches TeamLogger month).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payslip format</label>
                        <select name="payslip_format" class="form-select">
                            @foreach($payslipFormats as $k => $label)
                                <option value="{{ $k }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create &amp; Sync Employees</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editSalaryModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form id="formEditSalary">
                <input type="hidden" name="row_id" id="editRowId">
                <div class="modal-header"><h5 class="modal-title">Edit Salary</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body row g-2">
                    <div class="col-6"><label class="form-label small">Salary PP</label><input type="number" name="salary_pp" class="form-control form-control-sm" step="0.01"></div>
                    <div class="col-6"><label class="form-label small">Increment</label><input type="number" name="increment" class="form-control form-control-sm" step="0.01"></div>
                    <div class="col-6"><label class="form-label small">Other</label><input type="number" name="other" class="form-control form-control-sm" step="0.01"></div>
                    <div class="col-6"><label class="form-label small">Adv/Inc Other</label><input type="number" name="adv_inc_other" class="form-control form-control-sm" step="0.01"></div>
                    <div class="col-12"><label class="form-label small">Hours worked</label><input type="number" name="hours_worked" class="form-control form-control-sm" step="0.01"></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@section('script')
<script>
(function() {
    const app = document.getElementById('payrollApp');
    const canManage = app.dataset.canManage === '1';
    const csrf = app.dataset.csrf;
    const base = app.dataset.baseUrl;
    let monthId = app.dataset.activeMonthId || document.getElementById('payrollMonthSelect')?.value;
    const employeeRowsById = {};

    const headers = { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' };

    async function api(url, method = 'GET', body = null) {
        const opts = { method, headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } };
        if (body && !(body instanceof FormData)) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        } else if (body instanceof FormData) {
            delete opts.headers['Content-Type'];
            opts.body = body;
        }
        const r = await fetch(url, opts);
        const j = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(j.message || 'Request failed');
        return j;
    }

    function fmt(n) { return '₹' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 }); }

    function currentMonthId() {
        return document.getElementById('payrollMonthSelect')?.value || monthId;
    }

    async function loadMonth() {
        const id = currentMonthId();
        if (!id) return;
        monthId = id;
        const data = await api(`${base}/month/${id}/data`);
        const m = data.month;
        const emps = data.employees || [];

        document.getElementById('statEmployees').textContent = emps.length;
        document.getElementById('statNet').textContent = fmt(emps.reduce((s, e) => s + parseFloat(e.net_amount || 0), 0));
        document.getElementById('statStatus').textContent = m.status || '—';
        document.getElementById('statLock').textContent = m.is_locked ? 'Locked' : 'Open';
        document.getElementById('lockStatBox')?.classList.toggle('payroll-locked', !!m.is_locked);

        const sel = document.getElementById('payslipFormatSelect');
        const st = document.getElementById('monthStatusSelect');
        if (sel) sel.value = m.payslip_format || 'standard';
        if (st) st.value = m.status || 'draft';

        Object.keys(employeeRowsById).forEach(k => delete employeeRowsById[k]);
        emps.forEach(e => { employeeRowsById[e.id] = e; });

        const tbody = document.querySelector('#employeesTable tbody');
        if (tbody) {
        tbody.innerHTML = emps.map(e => `<tr>
            <td>${e.name || '—'}${e.is_new_hire ? ' <span class="badge bg-info">New</span>' : ''}</td>
            <td>${e.hours_worked != null ? e.hours_worked + 'h' : '—'}</td>
            <td>${fmt(e.salary_pp)}</td>
            <td>${fmt(e.increment)}</td>
            <td>${fmt(e.gross_amount ?? e.amount_lm)}</td>
            <td><strong>${fmt(e.net_amount ?? e.amount_p)}</strong></td>
            <td>${canManage && !m.is_locked ? `<button type="button" class="btn btn-xs btn-link btn-edit-salary" data-id="${e.id}">Edit</button>` : ''}</td>
        </tr>`).join('') || '<tr><td colspan="7" class="text-muted">No employees — sync from Team Management.</td></tr>';
        }

        document.querySelector('#componentsTable tbody').innerHTML = (data.components || []).map(c =>
            `<tr><td>${c.user?.name}</td><td>${c.type}</td><td>${c.label}</td><td>${fmt(c.amount)}</td>
            <td>${canManage && !m.is_locked ? `<button class="btn btn-link btn-sm text-danger btn-del-component" data-id="${c.id}">×</button>` : ''}</td></tr>`
        ).join('') || '<tr><td colspan="5" class="text-muted">None</td></tr>';

        document.querySelector('#paymentsTable tbody').innerHTML = (data.payments || []).map(p =>
            `<tr><td>${p.user?.name}</td><td>${p.entry_type}</td><td>${p.label}</td><td>${fmt(p.amount)}</td>
            <td>${canManage && !m.is_locked ? `<button class="btn btn-link btn-sm text-danger btn-del-payment" data-id="${p.id}">×</button>` : ''}</td></tr>`
        ).join('') || '<tr><td colspan="5" class="text-muted">None</td></tr>';

        document.querySelector('#payslipsTable tbody').innerHTML = (data.payslips || []).map(p => {
            const net = p.net ?? p.data?.net ?? 0;
            const hours = p.hours_worked != null ? p.hours_worked + 'h' : '—';
            const amtLm = p.amount_lm != null ? fmt(p.amount_lm) : '—';
            return `<tr><td>${p.user?.name}</td><td>${hours}</td><td>${amtLm}</td><td>${p.format}</td><td><strong>${fmt(net)}</strong></td><td>${p.released_at ? 'Yes' : 'No'}</td>
            <td><a href="${base}/payslip/${p.id}" target="_blank" class="btn btn-sm btn-outline-primary">View</a></td></tr>`;
        }).join('') || '<tr><td colspan="7" class="text-muted">Generate payslips from Actions tab</td></tr>';

        const arrearMonthHint = document.getElementById('arrearPayrollMonth');
        if (arrearMonthHint) arrearMonthHint.textContent = m.month_label || '—';

        document.querySelector('#arrearsTable tbody').innerHTML = (data.arrears || []).map(a => {
            const typeLabel = (a.adjustment_type === 'deduct') ? '<span class="text-danger">Deduct</span>' : '<span class="text-success">Add</span>';
            const amt = (a.adjustment_type === 'deduct') ? '−' + fmt(a.amount) : fmt(a.amount);
            const arrearMonth = a.arrear_for || a.month_label || '—';
            const payMonth = a.month_label || a.payroll_month?.month_label || m.month_label || '—';
            const actions = canManage && !m.is_locked
                ? (a.status === 'pending'
                    ? `<button class="btn btn-sm btn-outline-success btn-apply-arrear" data-id="${a.id}">Apply</button>`
                    : `<button class="btn btn-sm btn-outline-warning btn-revoke-arrear" data-id="${a.id}">Remove</button>`)
                : '';
            return `<tr><td>${a.user?.name}</td><td>${typeLabel}</td><td>${amt}</td><td><strong>${arrearMonth}</strong></td><td>${payMonth}</td><td>${a.status}</td><td>${actions}</td></tr>`;
        }).join('') || '<tr><td colspan="7" class="text-muted">None for this month</td></tr>';

        document.getElementById('btnExportCsv')?.setAttribute('href', `${base}/month/${id}/export`);
    }

    async function loadPrevious() {
        const data = await api(`${base}/previous-records`);
        document.querySelector('#previousTable tbody').innerHTML = (data.records || []).map(r =>
            `<tr><td>${r.user?.name}</td><td>${r.month_label}</td><td>${fmt(r.gross_amount)}</td><td>${fmt(r.deductions_total)}</td><td>${fmt(r.net_amount)}</td><td>${r.imported_at ? new Date(r.imported_at).toLocaleDateString() : '—'}</td></tr>`
        ).join('') || '<tr><td colspan="6" class="text-muted">No previous payroll records</td></tr>';
    }

    async function loadSettlements() {
        const data = await api(`${base}/settlements`);
        document.querySelector('#settlementsTable tbody').innerHTML = (data.settlements || []).map(s =>
            `<tr><td>${s.user?.name}</td><td>${s.last_working_date || '—'}</td><td>${fmt(s.net_settlement)}</td><td>${s.status}</td>
            <td>${canManage && s.status === 'draft' ? `<button class="btn btn-sm btn-success btn-process-settlement" data-id="${s.id}">Process</button>` : ''}</td></tr>`
        ).join('') || '<tr><td colspan="5" class="text-muted">None</td></tr>';
    }

    document.getElementById('payrollMonthSelect')?.addEventListener('change', () => { loadMonth(); });

    document.getElementById('formCreateMonth')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const body = Object.fromEntries(fd.entries());
        const res = await api(`${base}/month`, 'POST', body);
        alert(res.message);
        location.reload();
    });

    document.getElementById('btnSyncEmployees')?.addEventListener('click', async () => {
        const res = await api(`${base}/month/${currentMonthId()}/sync-employees`, 'POST', {});
        alert(res.message); loadMonth();
    });

    document.getElementById('btnSyncNewHires')?.addEventListener('click', async () => {
        const res = await api(`${base}/month/${currentMonthId()}/sync-employees`, 'POST', { new_hires_only: true });
        alert(res.message); loadMonth();
    });

    document.getElementById('formComponent')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        await api(`${base}/month/${currentMonthId()}/components`, 'POST', Object.fromEntries(fd.entries()));
        e.target.reset(); loadMonth();
    });

    document.getElementById('formPayment')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        await api(`${base}/month/${currentMonthId()}/payments`, 'POST', Object.fromEntries(fd.entries()));
        e.target.reset(); loadMonth();
    });

    document.getElementById('formArrear')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const body = Object.fromEntries(fd.entries());
        body.payroll_month_id = currentMonthId();
        await api(`${base}/arrears`, 'POST', body);
        e.target.reset(); loadMonth();
    });

    document.getElementById('formSettlement')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        await api(`${base}/settlements`, 'POST', Object.fromEntries(fd.entries()));
        e.target.reset(); loadSettlements();
    });

    document.getElementById('formPrevious')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const res = await api(`${base}/previous-records`, 'POST', Object.fromEntries(fd.entries()));
        alert(res.success ? 'Saved' : 'Error'); loadPrevious();
    });

    document.getElementById('formImportPrevious')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const opts = { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: fd };
        const r = await fetch(`${base}/previous-records/import`, opts);
        const j = await r.json();
        alert(j.message || 'Done'); loadPrevious();
    });

    document.getElementById('btnRecalculate')?.addEventListener('click', async () => {
        const res = await api(`${base}/month/${currentMonthId()}/recalculate`, 'POST', {});
        alert(res.message); loadMonth();
    });

    document.getElementById('btnGeneratePayslips')?.addEventListener('click', async () => {
        const res = await api(`${base}/month/${currentMonthId()}/generate-payslips`, 'POST', {});
        alert(res.message); loadMonth();
    });

    document.getElementById('btnReleasePayslips')?.addEventListener('click', async () => {
        if (!confirm('Release payslips for this month?')) return;
        const res = await api(`${base}/month/${currentMonthId()}/release-payslips`, 'POST', {});
        alert(res.message); loadMonth();
    });

    document.getElementById('btnReleaseIt')?.addEventListener('click', async () => {
        const res = await api(`${base}/month/${currentMonthId()}/release-it`, 'POST', {});
        alert(res.message); loadMonth();
    });

    document.getElementById('btnToggleLock')?.addEventListener('click', async () => {
        const res = await api(`${base}/month/${currentMonthId()}/toggle-lock`, 'POST', {});
        alert(res.message); location.reload();
    });

    document.getElementById('payslipFormatSelect')?.addEventListener('change', async (e) => {
        await api(`${base}/month/${currentMonthId()}`, 'PUT', { payslip_format: e.target.value });
    });

    document.getElementById('monthStatusSelect')?.addEventListener('change', async (e) => {
        await api(`${base}/month/${currentMonthId()}`, 'PUT', { status: e.target.value });
    });

    document.addEventListener('click', async (e) => {
        if (e.target.classList.contains('btn-edit-salary')) {
            const row = employeeRowsById[e.target.dataset.id];
            if (!row) return;
            document.getElementById('editRowId').value = row.id;
            const f = document.getElementById('formEditSalary');
            ['salary_pp','increment','other','adv_inc_other','hours_worked'].forEach(k => {
                if (f[k]) f[k].value = row[k] ?? '';
            });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('editSalaryModal')).show();
        }
        if (e.target.classList.contains('btn-del-component')) {
            await api(`${base}/components/${e.target.dataset.id}`, 'DELETE');
            loadMonth();
        }
        if (e.target.classList.contains('btn-del-payment')) {
            await api(`${base}/payments/${e.target.dataset.id}`, 'DELETE');
            loadMonth();
        }
        if (e.target.classList.contains('btn-apply-arrear')) {
            await api(`${base}/arrears/${e.target.dataset.id}/apply`, 'POST', {});
            loadMonth();
        }
        if (e.target.classList.contains('btn-revoke-arrear')) {
            if (!confirm('Remove this arrear from salary? Net pay will be recalculated.')) return;
            await api(`${base}/arrears/${e.target.dataset.id}`, 'DELETE');
            loadMonth();
        }
        if (e.target.classList.contains('btn-process-settlement')) {
            await api(`${base}/settlements/${e.target.dataset.id}/process`, 'POST', {});
            loadSettlements();
        }
    });

    document.getElementById('formEditSalary')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('editRowId').value;
        const fd = new FormData(e.target);
        const body = Object.fromEntries(fd.entries());
        delete body.row_id;
        await api(`${base}/employee-salary/${id}`, 'PUT', body);
        bootstrap.Modal.getInstance(document.getElementById('editSalaryModal'))?.hide();
        loadMonth();
    });

    if (currentMonthId()) loadMonth();
    loadPrevious();
    loadSettlements();
})();
</script>
@endsection
