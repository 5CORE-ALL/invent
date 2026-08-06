@extends('layouts.vertical', ['title' => 'Salary'])

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .payroll-card { border: 1px solid rgba(0,0,0,.08); border-radius: 10px; background: #fff; }
    #employeesTable { font-size: .85rem; }
    .payroll-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem .5rem; }
    .payroll-toolbar.flex-nowrap { flex-wrap: nowrap; overflow: visible; }
    #salaryStatusSection.payroll-card { overflow: visible; }
    .payroll-toolbar-title { margin: 0; font-size: 1.05rem; font-weight: 600; white-space: nowrap; line-height: 31px; height: 31px; }
    .payroll-month-select { width: auto; min-width: 160px; max-width: 200px; }
    .payroll-toolbar .btn,
    .payroll-toolbar .form-select,
    .payroll-toolbar .form-control,
    .payroll-toolbar .input-group,
    .payroll-toolbar .input-group-text,
    .payroll-toolbar .payroll-stat-inline {
        height: 31px !important;
        min-height: 31px !important;
        box-sizing: border-box;
    }
    .payroll-toolbar .btn {
        display: inline-flex; align-items: center; padding-top: 0; padding-bottom: 0;
        line-height: 1; font-size: .8rem;
    }
    .payroll-toolbar .form-select,
    .payroll-toolbar .form-control {
        padding-top: 0; padding-bottom: 0; font-size: .8rem; line-height: 31px;
    }
    .payroll-toolbar .input-group-text {
        display: inline-flex; align-items: center; padding-top: 0; padding-bottom: 0;
    }
    .payroll-stat-inline {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: 0 .65rem; border-radius: 6px;
        font-size: .78rem; white-space: nowrap;
    }
    .payroll-stat-inline .val { font-size: .88rem; font-weight: 700; }
    .payroll-stat-employees { background: rgba(13, 110, 253, .12); color: #0a58ca; }
    .payroll-stat-employees .val { color: #084298; }
    .payroll-stat-net { background: rgba(25, 135, 84, .14); color: #146c43; }
    .payroll-stat-net .val { color: #0f5132; }
    .payroll-history-tip {
        cursor: help; display: inline-flex; align-items: center; justify-content: center;
        font-size: 1.1rem; position: relative;
    }
    .payroll-history-tip:hover::after {
        content: attr(data-tip);
        position: absolute; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%);
        background: #212529; color: #fff; font-size: .72rem; font-weight: 500;
        padding: .35rem .55rem; border-radius: 6px; white-space: nowrap; z-index: 20;
        box-shadow: 0 2px 8px rgba(0,0,0,.18); pointer-events: none;
    }
    .payroll-doc-row { display: flex; align-items: center; justify-content: center; gap: .25rem; flex-wrap: wrap; margin: .15rem 0; font-size: .72rem; }
    .payroll-doc-label { color: #6c757d; min-width: 0; text-align: right; }
    .payroll-doc-actions { display: inline-flex; align-items: center; gap: .15rem; }
    .table-payroll { font-size: .85rem; }
    /* Center-align all payroll table data and headers. */
    #payrollApp .tabulator .tabulator-cell,
    #payrollApp .tabulator .tabulator-header .tabulator-col-title {
        text-align: center !important;
    }
    #payrollApp .tbl-dot {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
    }
    #payrollApp .tbl-dot--green { background: #22c55e; }
    #payrollApp .tbl-dot--yellow { background: #eab308; }
    #payrollApp .tbl-dot--red { background: #ef4444; }
    #payrollApp .tbl-dot--gray { background: #9ca3af; }
    .payroll-status-multi { position: relative; flex-shrink: 0; width: 140px; z-index: 30; }
    .payroll-status-multi > button {
        width: 100%; height: 31px; text-align: left; padding: 0 .75rem; padding-right: 1.75rem;
        border: 1px solid #ced4da; border-radius: .375rem; background: #fff; font-size: .8rem;
        line-height: 29px; cursor: pointer; color: #212529;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        background-repeat: no-repeat; background-position: right .6rem center; background-size: 12px;
    }
    .payroll-status-menu {
        display: none; position: fixed; z-index: 2000;
        min-width: 160px; padding: .4rem 0; background: #fff; border: 1px solid rgba(0,0,0,.12);
        border-radius: 8px; box-shadow: 0 6px 18px rgba(0,0,0,.12);
    }
    .payroll-status-multi.open .payroll-status-menu { display: block; }
    .payroll-status-menu label {
        display: flex; align-items: center; gap: .45rem; margin: 0;
        padding: .35rem .75rem; font-size: .8rem; cursor: pointer; white-space: nowrap;
        color: #212529; height: auto !important; min-height: 0 !important;
    }
    .payroll-status-menu label:hover { background: #f1f3f5; }
    .payroll-status-menu input { margin: 0; cursor: pointer; height: auto !important; min-height: 0 !important; }
</style>
@endsection

@section('content')
<div class="container-fluid" id="payrollApp"
     data-can-manage="{{ $canManage ? '1' : '0' }}"
     data-csrf="{{ csrf_token() }}"
     data-active-month-id="{{ $activeMonth?->id }}"
     data-base-url="{{ url('/payroll') }}">

    <div class="payroll-card px-3 py-2 mb-2" id="salaryStatusSection">
        <div class="payroll-toolbar flex-nowrap">
            <h4 class="payroll-toolbar-title"><i class="ri-wallet-3-line me-1 text-primary"></i>Salary</h4>
            <div class="d-flex align-items-center gap-2 flex-shrink-0" id="monthStats">
                <span class="payroll-stat-inline payroll-stat-employees">Employees <span class="val" id="statEmployees">—</span></span>
                <span class="payroll-stat-inline payroll-stat-net">Net <span class="val" id="statNet">—</span></span>
            </div>
            <div class="input-group input-group-sm" style="width: 150px; flex-shrink: 0;">
                <span class="input-group-text bg-light border-0 py-0"><i class="ri-search-line"></i></span>
                <input type="text" id="payrollSearch" class="form-control border-0 bg-light form-control-sm" placeholder="Name">
            </div>
            <div class="payroll-status-multi" id="payrollStatusMulti" title="Filter by one or more statuses">
                <button type="button" id="payrollStatusMultiBtn">2 selected</button>
                <div class="payroll-status-menu" id="payrollStatusMenu">
                    {{-- Active + Inactive both on by default so leavers with login hours still appear --}}
                    <label><input type="checkbox" name="payroll_status" value="active" checked> Active</label>
                    <label><input type="checkbox" name="payroll_status" value="inactive" checked> Inactive</label>
                    <label><input type="checkbox" name="payroll_status" value="deleted"> Deleted</label>
                    <label><input type="checkbox" name="payroll_status" value="na"> N/A</label>
                </div>
            </div>
            <select class="form-select form-select-sm payroll-month-select" id="payrollMonthSelect" style="flex-shrink: 0;">
                @forelse($months as $m)
                    <option value="{{ $m->id }}" {{ $activeMonth?->id === $m->id ? 'selected' : '' }}
                        data-locked="{{ $m->is_locked ? '1' : '0' }}"
                        data-status="{{ $m->status }}"
                        data-format="{{ $m->payslip_format }}">
                        {{ $m->month_label }}
                    </option>
                @empty
                    <option value="">No payroll months yet</option>
                @endforelse
            </select>
            @if($canManage)
            <button type="button" class="btn btn-sm btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#createMonthModal" title="Create Payroll Month">
                <i class="ri-calendar-line me-1"></i>Create Month
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger text-nowrap" id="btnToggleLock"><i class="ri-lock-line"></i> Lock</button>
            <a href="#" id="btnDownloadPayoutSheet" class="btn btn-sm btn-success text-nowrap"><i class="ri-file-excel-2-line me-1"></i>Download</a>
            <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" id="btnSyncEmployees"><i class="ri-refresh-line"></i> Sync Hours</button>
            @endif
        </div>
    </div>

    <div class="payroll-card p-2">
        <div id="employeesTable"></div>
        <input type="file" id="payrollDocInput" class="d-none" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
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
                    <div class="col-12">
                        <label class="form-label small">Active</label>
                        <select name="account_status" id="editAccountStatus" class="form-select form-select-sm">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="deleted">Deleted</option>
                            <option value="na">N/A</option>
                        </select>
                        <small class="text-muted" style="font-size: 11px;">Saved to users table (same as Team Management)</small>
                    </div>
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

    function formatDocSlot(d, type, label, locked) {
        const pathKey = type + '_document_path';
        const nameKey = type + '_document_name';
        const hasFile = !!(d[pathKey]);
        const downloadUrl = base + '/employee-salary/' + d.id + '/document/' + type;
        const fileName = hasFile ? esc(d[nameKey] || label) : '';
        let actions = '';
        if (hasFile) {
            actions += '<a href="' + downloadUrl + '" class="btn btn-sm btn-link p-0 text-success" title="Download ' + esc(label) + '"><i class="ri-download-line"></i></a>';
            if (canManage && !locked) {
                actions += '<button type="button" class="btn btn-sm btn-link p-0 text-danger btn-delete-doc" data-id="' + d.id + '" data-type="' + type + '" title="Remove ' + esc(label) + '"><i class="ri-delete-bin-line"></i></button>';
            }
        } else if (canManage && !locked) {
            actions += '<button type="button" class="btn btn-sm btn-link p-0 text-primary btn-upload-doc" data-id="' + d.id + '" data-type="' + type + '" title="Upload ' + esc(label) + '"><i class="ri-upload-2-line"></i></button>';
        } else {
            actions += '<span class="text-muted">—</span>';
        }
        const title = hasFile ? (' title="' + fileName + '"') : '';
        return '<div class="payroll-doc-row"' + title + '>'
            + '<span class="payroll-doc-label">' + esc(label) + '</span>'
            + '<span class="payroll-doc-actions">' + actions + '</span>'
            + '</div>';
    }

    function formatDocumentsCell(d, locked) {
        return formatDocSlot(d, 'bill', 'Bill', locked);
    }

    async function uploadPayrollDocument(rowId, type, file) {
        const fd = new FormData();
        fd.append('type', type);
        fd.append('file', file);
        const r = await fetch(base + '/employee-salary/' + rowId + '/document', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd,
        });
        const j = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(j.message || 'Upload failed');
        return j;
    }

    async function deletePayrollDocument(rowId, type) {
        const r = await fetch(base + '/employee-salary/' + rowId + '/document/' + type, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        const j = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(j.message || 'Delete failed');
        return j;
    }

    function applyEmployeeRowUpdate(rowData) {
        if (!rowData || !rowData.id) return;
        employeeRowsById[rowData.id] = rowData;
        if (employeesTable && employeesTableBuilt) {
            const row = employeesTable.getRows().find(r => String(r.getData().id) === String(rowData.id));
            if (row) row.update(Object.assign({}, row.getData(), rowData));
        }
    }

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
        const statusMeta = {
            active: { cls: 'tbl-dot--green', label: 'Active' },
            inactive: { cls: 'tbl-dot--yellow', label: 'Inactive' },
            deleted: { cls: 'tbl-dot--red', label: 'Deleted' },
            na: { cls: 'tbl-dot--gray', label: 'N/A' },
        };
        const rowAccountStatus = (d) => d.account_status || (d.is_deleted ? 'deleted' : (d.is_active === true ? 'active' : (d.is_active === false ? 'inactive' : 'na')));

        const columns = [
            {
                title: 'Active', field: 'account_status', width: 80, hozAlign: 'center', headerSort: false,
                formatter: (c) => {
                    const status = rowAccountStatus(c.getRow().getData());
                    const meta = statusMeta[status] || statusMeta.na;
                    return '<span class="tbl-dot ' + meta.cls + '" title="' + meta.label + '"></span>';
                }
            },
            { title: 'Name', field: 'name', minWidth: 180, formatter: (c) => esc(c.getRow().getData().name || '—') },
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
            { title: 'Docs', field: 'documents', hozAlign: 'center', headerSort: false, width: 70, minWidth: 70,
                formatter: (c) => formatDocumentsCell(c.getRow().getData(), !!c.getRow().getData()._locked) },
            { title: 'Advance', field: 'adv_inc_other', hozAlign: 'right', formatter: (c) => fmt(c.getValue()) },
            { title: 'Amount', field: 'gross_amount', hozAlign: 'right', formatter: (c) => fmt(c.getRow().getData().gross_amount ?? c.getRow().getData().amount_lm) },
            { title: 'Payable', field: 'net_amount', hozAlign: 'right', formatter: (c) => '<strong>' + fmt(c.getRow().getData().net_amount ?? c.getRow().getData().amount_p) + '</strong>' },
        ];
        columns.push({
            title: 'History', field: 'edited_at', hozAlign: 'center', headerSort: false, width: 80,
            formatter: (c) => {
                const d = c.getRow().getData();
                if (!d.edited_at && !d.edited_by) return '—';
                let full = '';
                if (d.edited_at) {
                    const dt = new Date(d.edited_at);
                    if (!isNaN(dt)) {
                        full = dt.toLocaleString('en-GB', {
                            day: '2-digit', month: 'short', year: 'numeric',
                            hour: '2-digit', minute: '2-digit',
                        });
                    }
                }
                const who = d.edited_by ? String(d.edited_by) : '';
                const tip = [who, full].filter(Boolean).join(' · ') || 'Edited';
                return '<span class="payroll-history-tip" data-tip="' + esc(tip) + '"><i class="ri-search-line text-primary"></i></span>';
            }
        });
        if (canManage) {
            columns.push({
                title: 'Edit', field: 'id', hozAlign: 'center', headerSort: false, width: 70,
                formatter: (c) => {
                    const d = c.getRow().getData();
                    if (d._locked) return '—';
                    return '<button type="button" class="btn btn-sm btn-link btn-edit-salary p-0" data-id="' + d.id + '" title="Edit"><i class="ri-pencil-line"></i></button>';
                }
            });
        }
        columns.push({
            title: 'Payslip', field: 'user_id', hozAlign: 'center', headerSort: false, width: 120,
            formatter: (c) => {
                const d = c.getRow().getData();
                const url = `${base}/month/${currentMonthId()}/salary-slip/${d.user_id}`;
                return '<a href="' + url + '?print=0" target="_blank" class="btn btn-sm btn-outline-primary py-0 me-1" title="View"><i class="ri-eye-line"></i></a>'
                     + '<a href="' + url + '?print=1" target="_blank" class="btn btn-sm btn-success py-0" title="Download"><i class="ri-download-line"></i></a>';
            }
        });

        if (!employeesTable) {
            employeesTable = new Tabulator('#employeesTable', {
                layout: 'fitColumns',
                placeholder: 'No employees — sync from Team Management.',
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200],
                columns: columns,
            });
            employeesTable.on('tableBuilt', () => {
                employeesTableBuilt = true;
                employeesTable.setData(pendingEmployeesData);
                applyPayrollFilters();
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
                paginationSize: 100,
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

    function updateMonthSelectOption(month) {
        const sel = document.getElementById('payrollMonthSelect');
        if (!sel || !month) return;
        const opt = sel.querySelector('option[value="' + month.id + '"]');
        if (!opt) return;
        if (month.month_label) opt.textContent = month.month_label;
        opt.dataset.status = month.status || 'draft';
        opt.dataset.locked = month.is_locked ? '1' : '0';
    }

    function renderStatusSection(m) {
        if (!m) return;

        const locked = !!m.is_locked;
        const btnLock = document.getElementById('btnToggleLock');
        if (btnLock) {
            btnLock.innerHTML = locked
                ? '<i class="ri-lock-unlock-line"></i> Unlock'
                : '<i class="ri-lock-line"></i> Lock';
            btnLock.className = 'btn btn-sm text-nowrap ' + (locked ? 'btn-danger' : 'btn-outline-danger');
        }
    }

    async function loadMonth() {
        const id = currentMonthId();
        if (!id) return;
        monthId = id;
        const data = await api(`${base}/month/${id}/data`);
        const m = data.month;
        const emps = data.employees || [];

        Object.keys(employeeRowsById).forEach(k => delete employeeRowsById[k]);
        emps.forEach(e => { employeeRowsById[e.id] = e; });

        renderEmployeesTable(emps, !!m.is_locked);
        applyPayrollFilters();
        renderStatusSection(m);
        updateMonthSelectOption(m);

        document.getElementById('btnDownloadPayoutSheet')?.setAttribute('href', `${base}/month/${id}/payout-sheet`);
    }

    function updateEmployeeStats(rows) {
        const list = rows || [];
        document.getElementById('statEmployees').textContent = list.length;
        document.getElementById('statNet').textContent = fmt(list.reduce((s, e) => s + parseFloat(e.net_amount || 0), 0));
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
        try {
            const res = await api(`${base}/month/${currentMonthId()}/sync-employees`, 'POST', {});
            alert(res.message || 'Hours synced.');
            loadMonth();
        } catch (err) {
            alert((err && err.message) ? err.message : 'Failed to sync hours.');
        }
    });

    document.getElementById('btnToggleLock')?.addEventListener('click', async () => {
        const res = await api(`${base}/month/${currentMonthId()}/toggle-lock`, 'POST', {});
        alert(res.message);
        loadMonth();
    });

    let pendingDocUpload = null;

    document.getElementById('payrollDocInput')?.addEventListener('change', async (e) => {
        const input = e.target;
        const file = input.files && input.files[0];
        const pending = pendingDocUpload;
        input.value = '';
        pendingDocUpload = null;
        if (!file || !pending) return;
        try {
            const res = await uploadPayrollDocument(pending.id, pending.type, file);
            applyEmployeeRowUpdate(res.row);
            alert(res.message || 'Document uploaded.');
        } catch (err) {
            alert((err && err.message) ? err.message : 'Upload failed.');
        }
    });

    document.addEventListener('click', async (e) => {
        const uploadBtn = e.target.closest('.btn-upload-doc');
        if (uploadBtn) {
            pendingDocUpload = { id: uploadBtn.dataset.id, type: uploadBtn.dataset.type };
            document.getElementById('payrollDocInput')?.click();
            return;
        }
        const deleteBtn = e.target.closest('.btn-delete-doc');
        if (deleteBtn) {
            if (!confirm('Remove this document?')) return;
            try {
                const res = await deletePayrollDocument(deleteBtn.dataset.id, deleteBtn.dataset.type);
                applyEmployeeRowUpdate(res.row);
            } catch (err) {
                alert((err && err.message) ? err.message : 'Delete failed.');
            }
            return;
        }

        const editBtn = e.target.closest('.btn-edit-salary');
        if (editBtn) {
            const row = employeeRowsById[editBtn.dataset.id];
            if (!row) return;
            document.getElementById('editRowId').value = row.id;
            const f = document.getElementById('formEditSalary');
            ['salary_pp','increment','other','adv_inc_other','incentive','hours_worked'].forEach(k => {
                if (f[k]) f[k].value = row[k] ?? '';
            });
            const statusSelect = document.getElementById('editAccountStatus');
            if (statusSelect) {
                statusSelect.disabled = false;
                statusSelect.value = payrollRowStatus(row);
            }
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
        // Disabled hours_worked is omitted from FormData; keep it out of the save payload.
        delete body.hours_worked;
        try {
            await api(`${base}/employee-salary/${id}`, 'PUT', body);
            bootstrap.Modal.getInstance(document.getElementById('editSalaryModal'))?.hide();
            loadMonth();
        } catch (err) {
            alert((err && err.message) ? err.message : 'Failed to save.');
        }
    });

    function payrollRowStatus(d) {
        return d.account_status
            || (d.is_deleted ? 'deleted' : (d.is_active === true ? 'active' : (d.is_active === false ? 'inactive' : 'na')));
    }

    const statusLabels = { active: 'Active', inactive: 'Inactive', deleted: 'Deleted', na: 'N/A' };

    function selectedPayrollStatuses() {
        return Array.from(document.querySelectorAll('#payrollStatusMenu input[name="payroll_status"]:checked'))
            .map((el) => el.value);
    }

    function updatePayrollStatusButtonLabel() {
        const btn = document.getElementById('payrollStatusMultiBtn');
        if (!btn) return;
        const selected = selectedPayrollStatuses();
        if (!selected.length) {
            btn.textContent = 'All statuses';
            return;
        }
        if (selected.length === 1) {
            btn.textContent = statusLabels[selected[0]] || selected[0];
            return;
        }
        btn.textContent = selected.length + ' selected';
    }

    // Top filters: one/multiple statuses + search by employee name.
    function applyPayrollFilters() {
        const term = (document.getElementById('payrollSearch')?.value || '').toLowerCase().trim();
        const statuses = selectedPayrollStatuses();
        const matchesStatus = (d) => !statuses.length || statuses.includes(payrollRowStatus(d));
        const matchesSearch = (d) => !term || ['name', 'email'].some((f) => String(d[f] || '').toLowerCase().includes(term));
        const empFilter = (d) => matchesStatus(d) && matchesSearch(d);
        const userFilter = (d) => {
            const rowStatus = d.account_status
                || (d.user?.deleted_at ? 'deleted' : (d.user ? (d.user.is_active ? 'active' : 'inactive') : payrollRowStatus(d)));
            const statusOk = !statuses.length || statuses.includes(rowStatus);
            const searchOk = !term || String(d.user?.name || d.name || '').toLowerCase().includes(term);
            return statusOk && searchOk;
        };

        updatePayrollStatusButtonLabel();

        if (employeesTable && employeesTableBuilt) {
            try { employeesTable.setFilter(empFilter); } catch (e) {}
        }
        updateEmployeeStats((pendingEmployeesData || []).filter(empFilter));

        Object.values(tableInstances).forEach((t) => {
            try { t.setFilter(userFilter); } catch (e) {}
        });
    }

    const statusMulti = document.getElementById('payrollStatusMulti');
    const statusMultiBtn = document.getElementById('payrollStatusMultiBtn');
    const statusMenu = document.getElementById('payrollStatusMenu');

    function positionStatusMenu() {
        if (!statusMultiBtn || !statusMenu) return;
        const rect = statusMultiBtn.getBoundingClientRect();
        statusMenu.style.top = (rect.bottom + 4) + 'px';
        statusMenu.style.left = rect.left + 'px';
        statusMenu.style.width = Math.max(rect.width, 160) + 'px';
    }

    function closeStatusMenu() {
        statusMulti?.classList.remove('open');
    }

    function openStatusMenu() {
        positionStatusMenu();
        statusMulti?.classList.add('open');
    }

    statusMultiBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (statusMulti?.classList.contains('open')) {
            closeStatusMenu();
        } else {
            openStatusMenu();
        }
    });
    statusMenu?.addEventListener('click', (e) => e.stopPropagation());
    document.querySelectorAll('#payrollStatusMenu input[name="payroll_status"]').forEach((cb) => {
        cb.addEventListener('change', applyPayrollFilters);
    });
    document.addEventListener('click', (e) => {
        if (!statusMulti?.contains(e.target) && !statusMenu?.contains(e.target)) {
            closeStatusMenu();
        }
    });
    window.addEventListener('resize', () => {
        if (statusMulti?.classList.contains('open')) positionStatusMenu();
    });
    window.addEventListener('scroll', () => {
        if (statusMulti?.classList.contains('open')) positionStatusMenu();
    }, true);
    document.getElementById('payrollSearch')?.addEventListener('keyup', applyPayrollFilters);
    updatePayrollStatusButtonLabel();

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
