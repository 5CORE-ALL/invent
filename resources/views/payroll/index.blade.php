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
    /* Center-align all payroll table data and headers. */
    #payrollApp .tabulator .tabulator-cell,
    #payrollApp .tabulator .tabulator-header .tabulator-col-title {
        text-align: center !important;
    }
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
                    <div class="col-6 col-md-6"><div class="payroll-stat"><div class="text-muted small">Employees</div><div class="val" id="statEmployees">—</div></div></div>
                    <div class="col-6 col-md-6"><div class="payroll-stat"><div class="text-muted small">Total Net</div><div class="val" id="statNet">—</div></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="payroll-card p-3">
        <div class="d-flex justify-content-end align-items-center mb-2">
            <div class="d-flex align-items-center gap-2">
                @can('payroll.sheet-admin')
                <a href="#" id="btnDownloadPayoutSheet" class="btn btn-sm btn-success py-0"><i class="ri-file-excel-2-line me-1"></i>Download Month Sheet</a>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnSyncEmployees"><i class="ri-refresh-line"></i> Sync from Team</button>
                @endcan
            </div>
        </div>
        <div id="employeesTable"></div>
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
                    <div class="col-6"><label class="form-label small">Advance</label><input type="number" name="adv_inc_other" class="form-control form-control-sm" step="0.01"></div>
                    <div class="col-6"><label class="form-label small">Incentive</label><input type="number" name="incentive" class="form-control form-control-sm" step="0.01"></div>
                    <div class="col-6"><label class="form-label small">Hours worked</label><input type="number" name="hours_worked" class="form-control form-control-sm" step="0.01"></div>
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
                hours_overridden: true,
                edited_by: fresh.edited_by ?? d.edited_by,
                edited_at: fresh.edited_at ?? d.edited_at,
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
    // Hours overrides stay locked until the 2nd of the next month (set per month).
    let hoursOverrideLocked = false;
    let hoursOverrideUnlockDate = null;
    function renderEmployeesTable(emps, locked) {
        const data = (emps || []).map(e => Object.assign({}, e, { _locked: locked }));
        pendingEmployeesData = data;
        const columns = [
            { title: 'Name', field: 'name', minWidth: 180, formatter: (c) => {
                const d = c.getRow().getData();
                return (d.name || '—');
            } },
            { title: 'Hours LM', field: 'hours_worked', hozAlign: 'center', width: 110,
                editor: canManage ? 'number' : false,
                editorParams: { min: 0, step: 1, selectContents: true },
                editable: (cell) => canManage && !cell.getRow().getData()._locked && !hoursOverrideLocked,
                cellEdited: onHoursEdited,
                formatter: (c) => {
                    const d = c.getRow().getData();
                    const v = parseFloat(c.getValue());
                    const txt = isNaN(v) ? '—' : (Math.round(v) + 'h');
                    // Pen tag shows ONLY when the hours were manually edited (overridden),
                    // so live working hours stay clean and edited values stand out in bold.
                    if (d.hours_overridden) {
                        const who = d.edited_by ? ' title="Edited by ' + esc(d.edited_by) + '"' : '';
                        return '<strong>' + txt + ' <i class="ri-pencil-fill text-primary"' + who + '></i></strong>';
                    }
                    // Until the override unlock date, show a lock hint instead of a pen.
                    if (hoursOverrideLocked) {
                        const tip = hoursOverrideUnlockDate ? ' title="Editable from ' + esc(hoursOverrideUnlockDate) + '"' : '';
                        return txt + ' <i class="ri-lock-line text-muted small"' + tip + '></i>';
                    }
                    return txt;
                } },
            { title: 'Salary PP', field: 'salary_pp', hozAlign: 'right', formatter: (c) => fmt(c.getValue()) },
            { title: 'Incr', field: 'increment', hozAlign: 'right', formatter: (c) => fmt(c.getValue()) },
            { title: 'Other', field: 'other', hozAlign: 'right', formatter: (c) => fmt(c.getValue()) },
            { title: 'Incentive', field: 'incentive', hozAlign: 'right', formatter: (c) => fmt(c.getValue()) },
            { title: 'Advance', field: 'adv_inc_other', hozAlign: 'right', formatter: (c) => fmt(c.getValue()) },
            { title: 'Amount', field: 'gross_amount', hozAlign: 'right', formatter: (c) => fmt(c.getRow().getData().gross_amount ?? c.getRow().getData().amount_lm) },
            { title: 'Payable', field: 'net_amount', hozAlign: 'right', formatter: (c) => '<strong>' + fmt(c.getRow().getData().net_amount ?? c.getRow().getData().amount_p) + '</strong>' },
        ];
        columns.push({
            title: 'History', field: 'edited_at', hozAlign: 'center', headerSort: false, minWidth: 140,
            formatter: (c) => {
                const d = c.getRow().getData();
                let dateTxt = '';
                if (d.edited_at) {
                    const dt = new Date(d.edited_at);
                    if (!isNaN(dt)) dateTxt = dt.getDate() + ' ' + dt.toLocaleString('en-US', { month: 'short' });
                }
                const who = d.edited_by ? esc(d.edited_by) : '';
                return [who, dateTxt].filter(Boolean).join(' · ') || '—';
            }
        });
        columns.push({
            title: 'Action', field: 'user_id', hozAlign: 'center', headerSort: false, width: 150,
            formatter: (c) => {
                const d = c.getRow().getData();
                const url = `${base}/month/${currentMonthId()}/salary-slip/${d.user_id}`;
                let html = '';
                if (canManage && !d._locked) {
                    html += '<button type="button" class="btn btn-sm btn-link btn-edit-salary p-0 me-2" data-id="' + d.id + '" title="Edit"><i class="ri-pencil-line"></i></button>';
                }
                html += '<a href="' + url + '?print=0" target="_blank" class="btn btn-sm btn-outline-primary py-0 me-1" title="View"><i class="ri-eye-line"></i></a>'
                      + '<a href="' + url + '?print=1" target="_blank" class="btn btn-sm btn-success py-0" title="Download"><i class="ri-download-line"></i></a>';
                return html;
            }
        });

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

        Object.keys(employeeRowsById).forEach(k => delete employeeRowsById[k]);
        emps.forEach(e => { employeeRowsById[e.id] = e; });

        hoursOverrideLocked = !!data.hours_override_locked;
        hoursOverrideUnlockDate = data.hours_override_unlock_date
            ? new Date(data.hours_override_unlock_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
            : null;

        renderEmployeesTable(emps, !!m.is_locked);

        document.getElementById('btnDownloadPayoutSheet')?.setAttribute('href', `${base}/month/${id}/payout-sheet`);
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

    document.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('.btn-edit-salary');
        if (editBtn) {
            const row = employeeRowsById[editBtn.dataset.id];
            if (!row) return;
            document.getElementById('editRowId').value = row.id;
            const f = document.getElementById('formEditSalary');
            ['salary_pp','increment','other','adv_inc_other','incentive','hours_worked'].forEach(k => {
                if (f[k]) f[k].value = row[k] ?? '';
            });
            // Hours can't be overridden until the unlock date — lock the field too.
            if (f.hours_worked) {
                f.hours_worked.disabled = hoursOverrideLocked;
                f.hours_worked.title = hoursOverrideLocked && hoursOverrideUnlockDate
                    ? ('Editable from ' + hoursOverrideUnlockDate)
                    : '';
            }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('editSalaryModal')).show();
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
})();
</script>
@endsection
