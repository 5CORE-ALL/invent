@extends('layouts.vertical', ['title' => 'Payroll'])

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .payroll-card { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
    #employeesTable { font-size: .85rem; }
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
                        <div class="input-group input-group-sm" style="max-width: 240px;">
                            <span class="input-group-text bg-light border-0"><i class="ri-search-line"></i></span>
                            <input type="text" id="payrollSearch" class="form-control border-0 bg-light" placeholder="Search employee...">
                        </div>
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
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-salary-slip">Salary Slip</button></li>
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
                <div id="employeesTable"></div>
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
                <div id="componentsTable"></div>
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
                <div id="paymentsTable"></div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-payslips">
            <div class="payroll-card p-3">
                <p class="small text-muted">Formats: Standard, Detailed (with line items), Compact summary.</p>
                <div id="payslipsTable"></div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-salary-slip">
            <div class="payroll-card p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Salary Slips</h6>
                    <span class="small text-muted">Download any employee's salary slip for <strong id="salarySlipMonth">—</strong></span>
                </div>
                <p class="small text-muted">Each employee on this month's sheet has a downloadable salary slip. The slip is built from their current salary row.</p>
                <div id="salarySlipTable"></div>
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
                <div id="arrearsTable"></div>
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
                <div id="settlementsTable"></div>
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
                <div id="previousTable"></div>
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
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
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

    async function onHoursEdited(cell) {
        const row = cell.getRow();
        const d = row.getData();
        const oldValue = cell.getOldValue();
        const value = parseFloat(cell.getValue());
        if (isNaN(value) || value < 0) {
            cell.restoreOldValue();
            return;
        }
        try {
            const res = await api(`${base}/employee-salary/${d.id}`, 'PUT', { hours_worked: value });
            const fresh = res.row || {};
            // Reflect recalculated amounts immediately, without a full reload.
            row.update({
                hours_worked: fresh.hours_worked ?? value,
                gross_amount: fresh.gross_amount ?? d.gross_amount,
                amount_lm: fresh.gross_amount ?? d.amount_lm,
                net_amount: fresh.net_amount ?? d.net_amount,
                amount_p: fresh.net_amount ?? d.amount_p,
            });
            if (employeeRowsById[d.id]) Object.assign(employeeRowsById[d.id], row.getData());
        } catch (err) {
            cell.restoreOldValue();
            alert((err && err.message) ? err.message : 'Failed to save hours.');
        }
    }

    let employeesTable = null;
    let employeesTableBuilt = false;
    let pendingEmployeesData = [];
    function renderEmployeesTable(emps, locked) {
        const data = (emps || []).map(e => Object.assign({}, e, { _locked: locked }));
        pendingEmployeesData = data;
        const columns = [
            { title: 'Name', field: 'name', minWidth: 180, formatter: (c) => {
                const d = c.getRow().getData();
                return (d.name || '—') + (d.is_new_hire ? ' <span class="badge bg-info">New</span>' : '');
            } },
            { title: 'Hours LM', field: 'hours_worked', hozAlign: 'center', width: 110,
                editor: canManage ? 'number' : false,
                editorParams: { min: 0, step: 1, selectContents: true },
                editable: (cell) => canManage && !cell.getRow().getData()._locked,
                cellEdited: onHoursEdited,
                formatter: (c) => {
                    const v = parseFloat(c.getValue());
                    const txt = isNaN(v) ? '—' : (Math.round(v) + 'h');
                    const editable = canManage && !c.getRow().getData()._locked;
                    return editable ? (txt + ' <i class="ri-pencil-line text-muted small"></i>') : txt;
                } },
            { title: 'Salary PP', field: 'salary_pp', hozAlign: 'right', formatter: (c) => fmt(c.getValue()) },
            { title: 'Incr', field: 'increment', hozAlign: 'right', formatter: (c) => fmt(c.getValue()) },
            { title: 'Amt LM', field: 'gross_amount', hozAlign: 'right', formatter: (c) => fmt(c.getRow().getData().gross_amount ?? c.getRow().getData().amount_lm) },
            { title: 'Amt P', field: 'net_amount', hozAlign: 'right', formatter: (c) => '<strong>' + fmt(c.getRow().getData().net_amount ?? c.getRow().getData().amount_p) + '</strong>' },
        ];
        if (canManage) {
            columns.push({
                title: 'Action', field: 'id', hozAlign: 'center', headerSort: false, width: 90,
                formatter: (c) => {
                    const d = c.getRow().getData();
                    return d._locked ? '<span class="text-muted">Locked</span>' : '<button type="button" class="btn btn-sm btn-link btn-edit-salary p-0" data-id="' + d.id + '">Edit</button>';
                }
            });
        }

        if (!employeesTable) {
            employeesTable = new Tabulator('#employeesTable', {
                layout: 'fitColumns',
                height: '500px',
                placeholder: 'No employees — sync from Team Management.',
                pagination: true,
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200],
                columns: columns,
            });
            employeesTable.on('tableBuilt', () => {
                employeesTableBuilt = true;
                employeesTable.setData(pendingEmployeesData);
            });
        } else if (employeesTableBuilt) {
            // Columns already handle the locked state per-row (d._locked), so just swap the data.
            employeesTable.setData(pendingEmployeesData);
        }
        // If not yet built, the tableBuilt handler will load the latest pendingEmployeesData.
    }

    function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    const tableInstances = {};
    function renderTable(id, columns, data, placeholder) {
        const rows = data || [];
        if (!tableInstances[id]) {
            tableInstances[id] = new Tabulator('#' + id, {
                data: rows,
                layout: 'fitColumns',
                maxHeight: '500px',
                pagination: true,
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200],
                placeholder: placeholder || 'None',
                columns: columns,
            });
        } else {
            tableInstances[id].setColumns(columns);
            tableInstances[id].replaceData(rows);
        }
    }

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

        renderEmployeesTable(emps, !!m.is_locked);

        const compCols = [
            { title: 'Employee', minWidth: 160, formatter: (c) => esc(c.getRow().getData().user?.name || '') },
            { title: 'Type', field: 'type', width: 120 },
            { title: 'Label', field: 'label', minWidth: 160, formatter: (c) => esc(c.getValue()) },
            { title: 'Amount', field: 'amount', hozAlign: 'right', formatter: (c) => fmt(c.getValue()) },
        ];
        if (canManage && !m.is_locked) compCols.push({ title: '', headerSort: false, width: 60, hozAlign: 'center', formatter: (c) => '<button class="btn btn-link btn-sm text-danger btn-del-component p-0" data-id="' + c.getRow().getData().id + '">×</button>' });
        renderTable('componentsTable', compCols, data.components);

        const payCols = [
            { title: 'Employee', minWidth: 160, formatter: (c) => esc(c.getRow().getData().user?.name || '') },
            { title: 'Type', field: 'entry_type', width: 120 },
            { title: 'Label', field: 'label', minWidth: 160, formatter: (c) => esc(c.getValue()) },
            { title: 'Amount', field: 'amount', hozAlign: 'right', formatter: (c) => fmt(c.getValue()) },
        ];
        if (canManage && !m.is_locked) payCols.push({ title: '', headerSort: false, width: 60, hozAlign: 'center', formatter: (c) => '<button class="btn btn-link btn-sm text-danger btn-del-payment p-0" data-id="' + c.getRow().getData().id + '">×</button>' });
        renderTable('paymentsTable', payCols, data.payments);

        const psCols = [
            { title: 'Employee', minWidth: 160, formatter: (c) => esc(c.getRow().getData().user?.name || '') },
            { title: 'Hours', hozAlign: 'center', width: 90, formatter: (c) => { const d = c.getRow().getData(); return d.hours_worked != null ? d.hours_worked + 'h' : '—'; } },
            { title: 'Amt LM', hozAlign: 'right', formatter: (c) => { const d = c.getRow().getData(); return d.amount_lm != null ? fmt(d.amount_lm) : '—'; } },
            { title: 'Format', field: 'format', width: 120 },
            { title: 'Amt P', hozAlign: 'right', formatter: (c) => { const d = c.getRow().getData(); return '<strong>' + fmt(d.net ?? d.data?.net ?? 0) + '</strong>'; } },
            { title: 'Released', hozAlign: 'center', width: 90, formatter: (c) => c.getRow().getData().released_at ? 'Yes' : 'No' },
            { title: '', headerSort: false, width: 80, hozAlign: 'center', formatter: (c) => '<a href="' + base + '/payslip/' + c.getRow().getData().id + '" target="_blank" class="btn btn-sm btn-outline-primary py-0">View</a>' },
        ];
        renderTable('payslipsTable', psCols, data.payslips, 'Generate payslips from Actions tab');

        const salarySlipMonth = document.getElementById('salarySlipMonth');
        if (salarySlipMonth) salarySlipMonth.textContent = m.month_label || '—';

        const slipCols = [
            { title: 'Employee', field: 'name', minWidth: 180, formatter: (c) => esc(c.getRow().getData().name || '—') },
            { title: 'Email', field: 'email', minWidth: 180, formatter: (c) => esc(c.getRow().getData().email || '—') },
            { title: 'Hours', hozAlign: 'center', width: 90, formatter: (c) => { const v = parseFloat(c.getRow().getData().hours_worked); return isNaN(v) ? '—' : (Math.round(v) + 'h'); } },
            { title: 'Net', hozAlign: 'right', formatter: (c) => '<strong>' + fmt(c.getRow().getData().net_amount ?? c.getRow().getData().amount_p) + '</strong>' },
            { title: 'Salary Slip', headerSort: false, hozAlign: 'center', width: 180, formatter: (c) => {
                const d = c.getRow().getData();
                const url = `${base}/month/${id}/salary-slip/${d.user_id}`;
                return '<a href="' + url + '?print=1" target="_blank" class="btn btn-sm btn-success py-0 me-1"><i class="ri-download-line"></i> Download</a>'
                     + '<a href="' + url + '?print=0" target="_blank" class="btn btn-sm btn-outline-secondary py-0"><i class="ri-eye-line"></i></a>';
            } },
        ];
        renderTable('salarySlipTable', slipCols, emps, 'No employees on this month\'s sheet');

        const arrearMonthHint = document.getElementById('arrearPayrollMonth');
        if (arrearMonthHint) arrearMonthHint.textContent = m.month_label || '—';

        const arrCols = [
            { title: 'Employee', minWidth: 140, formatter: (c) => esc(c.getRow().getData().user?.name || '') },
            { title: 'Type', hozAlign: 'center', width: 90, formatter: (c) => c.getRow().getData().adjustment_type === 'deduct' ? '<span class="text-danger">Deduct</span>' : '<span class="text-success">Add</span>' },
            { title: 'Amount', hozAlign: 'right', formatter: (c) => { const a = c.getRow().getData(); return a.adjustment_type === 'deduct' ? '−' + fmt(a.amount) : fmt(a.amount); } },
            { title: 'Arrear month', formatter: (c) => { const a = c.getRow().getData(); return '<strong>' + esc(a.arrear_for || a.month_label || '—') + '</strong>'; } },
            { title: 'Payroll month', formatter: (c) => { const a = c.getRow().getData(); return esc(a.month_label || a.payroll_month?.month_label || m.month_label || '—'); } },
            { title: 'Status', field: 'status', width: 100 },
        ];
        if (canManage && !m.is_locked) arrCols.push({ title: '', headerSort: false, width: 90, hozAlign: 'center', formatter: (c) => { const a = c.getRow().getData(); return a.status === 'pending' ? '<button class="btn btn-sm btn-outline-success btn-apply-arrear py-0" data-id="' + a.id + '">Apply</button>' : '<button class="btn btn-sm btn-outline-warning btn-revoke-arrear py-0" data-id="' + a.id + '">Remove</button>'; } });
        renderTable('arrearsTable', arrCols, data.arrears, 'None for this month');

        document.getElementById('btnExportCsv')?.setAttribute('href', `${base}/month/${id}/export`);
    }

    async function loadPrevious() {
        const data = await api(`${base}/previous-records`);
        const prevCols = [
            { title: 'Employee', minWidth: 160, formatter: (c) => esc(c.getRow().getData().user?.name || '') },
            { title: 'Month', field: 'month_label', width: 140 },
            { title: 'Gross', hozAlign: 'right', formatter: (c) => fmt(c.getRow().getData().gross_amount) },
            { title: 'Deductions', hozAlign: 'right', formatter: (c) => fmt(c.getRow().getData().deductions_total) },
            { title: 'Net', hozAlign: 'right', formatter: (c) => fmt(c.getRow().getData().net_amount) },
            { title: 'Imported', width: 120, formatter: (c) => { const r = c.getRow().getData(); return r.imported_at ? new Date(r.imported_at).toLocaleDateString() : '—'; } },
        ];
        renderTable('previousTable', prevCols, data.records, 'No previous payroll records');
    }

    async function loadSettlements() {
        const data = await api(`${base}/settlements`);
        const setCols = [
            { title: 'Employee', minWidth: 160, formatter: (c) => esc(c.getRow().getData().user?.name || '') },
            { title: 'LWD', field: 'last_working_date', width: 120, formatter: (c) => c.getValue() || '—' },
            { title: 'Net', hozAlign: 'right', formatter: (c) => fmt(c.getRow().getData().net_settlement) },
            { title: 'Status', field: 'status', width: 100 },
        ];
        if (canManage) setCols.push({ title: '', headerSort: false, width: 90, hozAlign: 'center', formatter: (c) => { const s = c.getRow().getData(); return s.status === 'draft' ? '<button class="btn btn-sm btn-success btn-process-settlement py-0" data-id="' + s.id + '">Process</button>' : ''; } });
        renderTable('settlementsTable', setCols, data.settlements);
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

    // Top search: filter every payroll table by employee name.
    function applyPayrollSearch() {
        const term = (document.getElementById('payrollSearch')?.value || '').toLowerCase().trim();
        const empFilter = (d) => !term || ['name', 'email'].some((f) => String(d[f] || '').toLowerCase().includes(term));
        const userFilter = (d) => !term || String(d.user?.name || '').toLowerCase().includes(term);
        if (employeesTable && employeesTableBuilt) {
            try { term ? employeesTable.setFilter(empFilter) : employeesTable.clearFilter(); } catch (e) {}
        }
        Object.values(tableInstances).forEach((t) => {
            try { term ? t.setFilter(userFilter) : t.clearFilter(); } catch (e) {}
        });
    }
    document.getElementById('payrollSearch')?.addEventListener('keyup', applyPayrollSearch);

    // Tables built inside hidden tabs need a redraw once their tab becomes visible.
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach((btn) => {
        btn.addEventListener('shown.bs.tab', () => {
            if (employeesTable) employeesTable.redraw(true);
            Object.values(tableInstances).forEach((t) => { try { t.redraw(true); } catch (e) {} });
        });
    });

    if (currentMonthId()) loadMonth();
    loadPrevious();
    loadSettlements();
})();
</script>
@endsection
