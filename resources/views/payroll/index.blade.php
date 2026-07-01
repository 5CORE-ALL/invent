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
    .payroll-status-steps { display: flex; align-items: center; gap: .35rem; flex-wrap: wrap; }
    .payroll-status-step {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .35rem .65rem; border-radius: 999px; font-size: .75rem; font-weight: 600;
        background: #f1f3f5; color: #6c757d; border: 1px solid transparent;
    }
    .payroll-status-step.active { background: rgba(13,110,253,.12); color: #0d6efd; border-color: rgba(13,110,253,.25); }
    .payroll-status-step.done { background: rgba(25,135,84,.12); color: #198754; border-color: rgba(25,135,84,.2); }
    .payroll-status-arrow { color: #adb5bd; font-size: .85rem; }
    .payroll-status-badge { font-size: .7rem; padding: .25rem .55rem; border-radius: 999px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
    .payroll-status-badge.draft { background: #e9ecef; color: #495057; }
    .payroll-status-badge.processing { background: rgba(255,193,7,.2); color: #997404; }
    .payroll-status-badge.processed { background: rgba(13,110,253,.15); color: #0d6efd; }
    .payroll-status-badge.released { background: rgba(25,135,84,.15); color: #198754; }
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
     data-base-url="{{ url('/payroll') }}"
     data-month-statuses='@json($monthStatuses)'>

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

    @if($canManage)
    <div class="row mb-3">
        <div class="col-12">
            <div class="payroll-card p-3" id="salaryStatusSection">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                    <div>
                        <h6 class="mb-1"><i class="ri-flag-line me-1 text-primary"></i>Salary Status</h6>
                        <p class="text-muted small mb-2" id="statusHelpText">Move payroll from draft to release when ready.</p>
                        <div class="payroll-status-steps" id="statusSteps"></div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="payroll-status-badge draft" id="statusBadge">Draft</span>
                        <span class="badge bg-danger d-none" id="lockBadge"><i class="ri-lock-line me-1"></i>Locked</span>
                        <div class="input-group input-group-sm" style="width: auto;">
                            <select class="form-select form-select-sm" id="statusSelect" style="min-width: 140px;">
                                @foreach($monthStatuses as $k => $label)
                                    <option value="{{ $k }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnApplyStatus">Update</button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-warning" id="btnToggleLock"><i class="ri-lock-line"></i> Lock</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnRecalculate"><i class="ri-calculator-line"></i> Process</button>
                        <button type="button" class="btn btn-sm btn-outline-info" id="btnGeneratePayslips"><i class="ri-file-list-3-line"></i> Generate Payslips</button>
                        <button type="button" class="btn btn-sm btn-success" id="btnReleasePayslips"><i class="ri-send-plane-line"></i> Release</button>
                    </div>
                </div>
                <div class="small text-muted mt-2 d-none" id="statusMeta"></div>
            </div>
        </div>
    </div>
    @endif

    <div class="payroll-card p-3">
        <div class="d-flex justify-content-end align-items-center mb-2">
            <div class="d-flex align-items-center gap-2">
               
                <a href="#" id="btnDownloadPayoutSheet" class="btn btn-sm btn-success py-0"><i class="ri-file-excel-2-line me-1"></i>Download Month Sheet</a>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnSyncEmployees"><i class="ri-refresh-line"></i> Sync Hours from Team</button>
               
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
                    <div class="col-6">
                        <label class="form-label small">Hours worked</label>
                        <input type="number" name="hours_worked" class="form-control form-control-sm bg-light" step="0.01" disabled title="Edit hours from the table row (pen icon). This field is read-only here so saving other fields does not affect live API hours.">
                        <small class="text-muted" style="font-size: 11px;">Edit from table row</small>
                    </div>
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
    const monthStatuses = JSON.parse(app.dataset.monthStatuses || '{}');
    const statusOrder = ['draft', 'processing', 'processed', 'released'];
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
                editable: (cell) => canManage && !cell.getRow().getData()._locked,
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

    function formatDateTime(iso) {
        if (!iso) return '';
        const dt = new Date(iso);
        if (isNaN(dt)) return '';
        return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
            + ' ' + dt.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }

    function updateMonthSelectOption(month) {
        const sel = document.getElementById('payrollMonthSelect');
        if (!sel || !month) return;
        const opt = sel.querySelector('option[value="' + month.id + '"]');
        if (!opt) return;
        const label = (month.month_label || opt.textContent.split(' (')[0]).trim();
        opt.textContent = label + ' (' + (monthStatuses[month.status] || month.status || 'Draft') + ')';
        opt.dataset.status = month.status || 'draft';
        opt.dataset.locked = month.is_locked ? '1' : '0';
    }

    function renderStatusSection(m) {
        const section = document.getElementById('salaryStatusSection');
        if (!section || !m) return;

        const status = m.status || 'draft';
        const locked = !!m.is_locked;
        const idx = statusOrder.indexOf(status);

        section.classList.toggle('payroll-locked', locked);

        const stepsEl = document.getElementById('statusSteps');
        if (stepsEl) {
            stepsEl.innerHTML = statusOrder.map((key, i) => {
                const cls = i < idx ? 'done' : (i === idx ? 'active' : '');
                const arrow = i < statusOrder.length - 1 ? '<span class="payroll-status-arrow">→</span>' : '';
                return '<span class="payroll-status-step ' + cls + '">' + esc(monthStatuses[key] || key) + '</span>' + arrow;
            }).join('');
        }

        const badge = document.getElementById('statusBadge');
        if (badge) {
            badge.className = 'payroll-status-badge ' + status;
            badge.textContent = monthStatuses[status] || status;
        }

        const lockBadge = document.getElementById('lockBadge');
        if (lockBadge) lockBadge.classList.toggle('d-none', !locked);

        const statusSelect = document.getElementById('statusSelect');
        if (statusSelect) {
            statusSelect.value = status;
            statusSelect.disabled = locked;
        }

        const btnApply = document.getElementById('btnApplyStatus');
        if (btnApply) btnApply.disabled = locked;

        const btnLock = document.getElementById('btnToggleLock');
        if (btnLock) {
            btnLock.innerHTML = locked
                ? '<i class="ri-lock-unlock-line"></i> Unlock'
                : '<i class="ri-lock-line"></i> Lock';
            btnLock.className = 'btn btn-sm ' + (locked ? 'btn-warning' : 'btn-outline-warning');
        }

        const btnRecalc = document.getElementById('btnRecalculate');
        const btnGen = document.getElementById('btnGeneratePayslips');
        const btnRelease = document.getElementById('btnReleasePayslips');
        if (btnRecalc) btnRecalc.disabled = locked || status === 'released';
        if (btnGen) btnGen.disabled = locked || status === 'released';
        if (btnRelease) btnRelease.disabled = status === 'released';

        const help = document.getElementById('statusHelpText');
        if (help) {
            const hints = {
                draft: 'Draft — edit salaries and hours. Lock when ready to finalize.',
                processing: 'Processing — review and recalculate before marking processed.',
                processed: 'Processed — generate payslips, then release to employees.',
                released: 'Released — payslips are live for employees.',
            };
            help.textContent = hints[status] || hints.draft;
        }

        const meta = document.getElementById('statusMeta');
        if (meta) {
            const parts = [];
            if (m.payslips_released_at) parts.push('Payslips released: ' + formatDateTime(m.payslips_released_at));
            if (m.it_statements_released_at) parts.push('IT statements released: ' + formatDateTime(m.it_statements_released_at));
            if (parts.length) {
                meta.textContent = parts.join(' · ');
                meta.classList.remove('d-none');
            } else {
                meta.classList.add('d-none');
            }
        }
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

        renderEmployeesTable(emps, !!m.is_locked);
        renderStatusSection(m);
        updateMonthSelectOption(m);

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

    document.getElementById('btnApplyStatus')?.addEventListener('click', async () => {
        const status = document.getElementById('statusSelect')?.value;
        if (!status) return;
        const res = await api(`${base}/month/${currentMonthId()}`, 'PUT', { status });
        alert('Status updated to ' + (monthStatuses[status] || status) + '.');
        loadMonth();
    });

    document.getElementById('btnToggleLock')?.addEventListener('click', async () => {
        const res = await api(`${base}/month/${currentMonthId()}/toggle-lock`, 'POST', {});
        alert(res.message);
        loadMonth();
    });

    document.getElementById('btnRecalculate')?.addEventListener('click', async () => {
        if (!confirm('Recalculate all employee amounts and mark this month as Processed?')) return;
        const res = await api(`${base}/month/${currentMonthId()}/recalculate`, 'POST', {});
        alert(res.message);
        loadMonth();
    });

    document.getElementById('btnGeneratePayslips')?.addEventListener('click', async () => {
        const res = await api(`${base}/month/${currentMonthId()}/generate-payslips`, 'POST', {});
        alert(res.message);
        loadMonth();
    });

    document.getElementById('btnReleasePayslips')?.addEventListener('click', async () => {
        if (!confirm('Release payslips to all employees? This marks the month as Released.')) return;
        const res = await api(`${base}/month/${currentMonthId()}/release-payslips`, 'POST', {});
        alert(res.message);
        loadMonth();
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
            // Hours field is intentionally read-only in this modal so saving
            // other salary fields never carries the current hours value to the
            // server (which would mark the row as a manual override and stop
            // the live TeamLogger refresh). Hours editing lives on the table
            // row's pen icon — that flow already toggles override correctly.
            if (f.hours_worked) {
                f.hours_worked.disabled = true;
                f.hours_worked.title = 'Edit hours from the table row.';
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
