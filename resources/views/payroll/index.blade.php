@extends('layouts.vertical', ['title' => 'Salary'])

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    .payroll-card { border: 1px solid rgba(0,0,0,.08); border-radius: 10px; background: #fff; }
    #employeesTable { font-size: .85rem; }
    .payroll-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem .5rem; }
    #salaryStatusSection.payroll-card { overflow: visible; }
    .payroll-toolbar-title { margin: 0; font-size: 1.05rem; font-weight: 600; white-space: nowrap; line-height: 31px; height: 31px; }
    .payroll-month-select { width: auto; min-width: 150px; max-width: 200px; flex: 0 1 180px; }
    .payroll-toolbar .input-group { flex: 1 1 140px; min-width: 120px; max-width: 180px; }
    #monthStats { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
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
    .payroll-stat-rate { background: rgba(253, 126, 20, .14); color: #c2410c; }
    .payroll-stat-rate .val { color: #9a3412; }
    .payroll-stat-fx { background: rgba(111, 66, 193, .12); color: #5a32a3; }
    .payroll-stat-fx .val { color: #4c1d95; }
    .payroll-fx-badges { display: none; }
    .payroll-fx-badges.is-visible { display: inline-flex; align-items: center; gap: .35rem; }
    .payroll-history-tip {
        cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
        font-size: 1.1rem;
    }
    .payroll-history-pop {
        position: fixed; z-index: 4000; max-width: 280px;
        background: #212529; color: #fff; font-size: .75rem; font-weight: 500;
        padding: .4rem .6rem; border-radius: 6px; line-height: 1.35;
        box-shadow: 0 4px 14px rgba(0,0,0,.2); pointer-events: none;
    }
    .payroll-doc-row { display: flex; align-items: center; justify-content: center; gap: .25rem; flex-wrap: wrap; margin: .15rem 0; font-size: .72rem; }
    .payroll-doc-label { color: #6c757d; min-width: 0; text-align: right; }
    .payroll-doc-actions { display: inline-flex; align-items: center; gap: .15rem; }
    .table-payroll { font-size: .85rem; }
    /* Increment tab */
    .inc-wrap { padding: .35rem .25rem; }
    .inc-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem .75rem; margin-bottom: .85rem; }
    .inc-title { margin: 0; font-size: 1.15rem; font-weight: 700; color: #1f2937; }
    .inc-subtitle { margin: .1rem 0 0; font-size: .8rem; color: #6b7280; }
    .inc-controls { display: flex; flex-wrap: nowrap; align-items: center; gap: .5rem; flex: 0 0 auto; }
    .inc-controls .form-select, .inc-controls .form-control { height: 34px; font-size: .85rem; }
    .inc-controls .form-select { width: 170px; min-width: 0; flex: 0 0 170px; }
    .inc-search { position: relative; flex: 0 0 190px; }
    .inc-search i { position: absolute; left: .6rem; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none; }
    .inc-search input { width: 100%; padding-left: 1.9rem; }
    .inc-btn-add { height: 34px; display: inline-flex; align-items: center; gap: .3rem; font-size: .85rem; font-weight: 600; white-space: nowrap; flex: 0 0 auto; }
    @media (max-width: 1199.98px) {
        .inc-controls { flex-wrap: wrap; width: 100%; }
        .inc-search { flex: 1 1 160px; }
        .inc-controls .form-select { flex: 1 1 150px; width: auto; }
    }
    .inc-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .75rem; margin-bottom: 1rem; }
    .inc-stat { border: 1px solid #e5e7eb; border-radius: 10px; padding: .75rem 1rem; background: #fff; }
    .inc-stat .k { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; font-weight: 600; }
    .inc-stat .v { margin-top: .15rem; font-size: 1.3rem; font-weight: 700; color: #111827; line-height: 1.25; }
    .inc-stat.is-primary { background: linear-gradient(135deg, #ecfdf5, #f0fdf4); border-color: #bbf7d0; }
    .inc-stat.is-primary .v { color: #047857; }
    .inc-badge { display: inline-flex; align-items: center; gap: .3rem; font-size: .75rem; font-weight: 600; padding: .15rem .55rem; border-radius: 999px; }
    .inc-badge.open { background: #dcfce7; color: #166534; }
    .inc-badge.locked { background: #fee2e2; color: #991b1b; }
    .inc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 1rem; align-items: start; }
    .inc-grid.is-single { grid-template-columns: 1fr; }
    .inc-card { border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; overflow: hidden; }
    .inc-card-head { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .65rem 1rem; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
    .inc-card-head .m { font-weight: 700; color: #111827; font-size: .92rem; }
    .inc-card-head .c { margin-left: .4rem; font-size: .75rem; color: #6b7280; font-weight: 500; }
    .inc-card-head .t { font-weight: 700; color: #047857; font-size: .9rem; }
    .inc-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .inc-table th { padding: .5rem 1rem; text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; font-weight: 600; background: #fff; border-bottom: 1px solid #e5e7eb; }
    .inc-table td { padding: .5rem 1rem; border-bottom: 1px solid #f1f5f9; color: #1f2937; vertical-align: middle; }
    .inc-table tbody tr:hover { background: #f9fafb; }
    .inc-table tfoot td { background: #f9fafb; font-weight: 700; border-bottom: 0; border-top: 1px solid #e5e7eb; }
    .inc-table .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .inc-table .idx { width: 44px; color: #9ca3af; }
    .inc-table .act { width: 76px; text-align: right; white-space: nowrap; }
    .inc-table th.num, .inc-table th.act { text-align: right; }
    .inc-amt { font-weight: 600; color: #047857; }
    .inc-zero { color: #9ca3af; }
    .inc-person { display: inline-flex; align-items: center; gap: .6rem; }
    .inc-avatar { width: 28px; height: 28px; border-radius: 50%; background: #e0e7ff; color: #3730a3; display: inline-flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 700; flex-shrink: 0; }
    .inc-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; display: block; }
    .inc-avatar.has-img { background: #e5e7eb; overflow: hidden; }
    .inc-combo-item .who { display: inline-flex; align-items: center; gap: .5rem; min-width: 0; }
    .inc-combo-item .inc-avatar { width: 24px; height: 24px; font-size: .65rem; }
    .inc-icon-btn { border: 0; background: transparent; padding: .15rem .3rem; border-radius: 6px; color: #6b7280; line-height: 1; font-size: 1rem; }
    .inc-icon-btn:hover { background: #eef2ff; color: #4338ca; }
    .inc-icon-btn.danger:hover { background: #fee2e2; color: #b91c1c; }
    .inc-combo { position: relative; }
    .inc-combo-list {
        position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 1060;
        max-height: 250px; overflow-y: auto; background: #fff;
        border: 1px solid #d1d5db; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.14);
    }
    .inc-combo-item { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .45rem .75rem; cursor: pointer; font-size: .86rem; }
    .inc-combo-item:hover, .inc-combo-item.is-active { background: #eef2ff; }
    .inc-combo-item .meta { font-size: .72rem; color: #6b7280; white-space: nowrap; }
    .inc-combo-item .meta.has { color: #047857; font-weight: 600; }
    .inc-combo-empty { padding: .75rem; text-align: center; color: #6b7280; font-size: .82rem; }
    .inc-bulk-list { max-height: 300px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; }
    .inc-bulk-list table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    .inc-bulk-list th { position: sticky; top: 0; background: #f9fafb; z-index: 1; padding: .45rem .75rem; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; border-bottom: 1px solid #e5e7eb; text-align: left; }
    .inc-bulk-list td { padding: .4rem .75rem; border-bottom: 1px solid #f1f5f9; }
    .inc-bulk-list .num { text-align: right; white-space: nowrap; }
    .inc-bulk-list tr.is-conflict td { color: #92400e; background: #fffbeb; }
    .inc-table th.inc-sortable { cursor: pointer; user-select: none; white-space: nowrap; }
    .inc-table th.inc-sortable:hover { color: #4338ca; }
    .inc-table th.inc-sortable .arr { display: inline-block; margin-left: .25rem; font-size: .7rem; color: #c4c7ce; }
    .inc-table th.inc-sortable.is-sorted { color: #4338ca; }
    .inc-table th.inc-sortable.is-sorted .arr { color: #4338ca; }
    .inc-table .chk { width: 40px; text-align: center; padding-left: .75rem; padding-right: 0; }
    .inc-table .chk input { cursor: pointer; }
    .inc-table tbody tr.is-selected { background: #eef2ff; }
    .inc-be-chips { display: flex; flex-wrap: wrap; gap: .4rem; max-height: 140px; overflow-y: auto; padding: .5rem; border: 1px solid #e5e7eb; border-radius: 8px; background: #fafafa; }
    .inc-be-chip { display: inline-flex; align-items: center; gap: .4rem; padding: .15rem .6rem .15rem .2rem; border-radius: 999px; background: #fff; border: 1px solid #e5e7eb; font-size: .8rem; }
    .inc-be-chip .inc-avatar { width: 22px; height: 22px; font-size: .6rem; }
    .inc-empty { text-align: center; padding: 2.5rem 1rem; color: #6b7280; border: 1px dashed #d1d5db; border-radius: 10px; background: #fafafa; }
    .inc-empty .big { font-size: 2rem; line-height: 1; margin-bottom: .4rem; }
    .inc-empty .t { font-weight: 600; color: #374151; margin-bottom: .25rem; }
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
    .payroll-region-tabs { border-bottom: 1px solid rgba(0,0,0,.08); }
    .payroll-region-tabs .nav-link {
        font-size: .85rem; font-weight: 600; color: #6c757d;
        border: 0; border-bottom: 2px solid transparent; border-radius: 0;
        padding: .55rem 1rem; margin-bottom: -1px;
    }
    .payroll-region-tabs .nav-link:hover { color: #0d6efd; }
    .payroll-region-tabs .nav-link.active {
        color: #0d6efd; background: transparent; border-bottom-color: #0d6efd;
    }
    #payrollApp .payroll-country-select {
        width: 100%; max-width: 118px; height: 28px; padding: 0 .35rem;
        border: 1px solid #ced4da; border-radius: .35rem; font-size: .78rem;
        background: #fff; cursor: pointer; text-align: center;
    }
    #payrollApp .payroll-country-select:disabled {
        background: #f8f9fa; cursor: default; opacity: .85;
    }
    .payroll-region-tabs { overflow-x: auto; flex-wrap: nowrap; }
    #payrollApp .tabulator .tabulator-tableholder { overflow-x: auto; }
    @media (max-width: 1199.98px) {
        .payroll-toolbar .input-group { max-width: none; }
        .payroll-month-select,
        .payroll-status-multi { flex: 1 1 140px; width: auto; max-width: none; }
    }
    @media (max-width: 767.98px) {
        #payrollApp.container-fluid { padding-left: .5rem; padding-right: .5rem; }
        #salaryStatusSection { padding-left: .6rem !important; padding-right: .6rem !important; }
        .payroll-toolbar-title { width: 100%; }
        #monthStats,
        .payroll-fx-badges.is-visible { width: 100%; }
        .payroll-toolbar .input-group,
        .payroll-month-select,
        .payroll-status-multi { flex: 1 1 100%; max-width: none; width: 100%; }
        .payroll-toolbar .btn { flex: 1 1 calc(50% - .4rem); justify-content: center; }
        .payroll-region-tabs .nav-link { padding: .5rem .75rem; }
    }
</style>
@endsection

@section('content')
<div class="container-fluid" id="payrollApp"
     data-can-manage="{{ $canManage ? '1' : '0' }}"
     data-csrf="{{ csrf_token() }}"
     data-active-month-id="{{ $activeMonth?->id }}"
     data-base-url="{{ url('/payroll') }}">

    <ul class="nav nav-tabs payroll-region-tabs mb-2" id="payrollRegionTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-salary-btn" data-bs-toggle="tab" data-bs-target="#tab-salary"
                type="button" role="tab" data-region="india" aria-selected="true">
                <span class="me-1">🇮🇳</span>Salary
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-china-btn" data-bs-toggle="tab" data-bs-target="#tab-china"
                type="button" role="tab" data-region="china" aria-selected="false">
                <span class="me-1">🇨🇳</span>China
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-usa-btn" data-bs-toggle="tab" data-bs-target="#tab-usa"
                type="button" role="tab" data-region="usa" aria-selected="false">
                <span class="me-1">🇺🇸</span>USA
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-increment-btn" data-bs-toggle="tab" data-bs-target="#tab-increment"
                type="button" role="tab" data-tab-kind="increment" aria-selected="false">
                <span class="me-1">📈</span>Increment
            </button>
        </li>
    </ul>

    <div class="payroll-card px-3 py-2 mb-2" id="salaryStatusSection">
        <div class="payroll-toolbar">
            <h4 class="payroll-toolbar-title" id="payrollRegionTitle"><span class="me-1">🇮🇳</span>Salary</h4>
            <div class="d-flex align-items-center gap-2 flex-wrap" id="monthStats">
                <span class="payroll-stat-inline payroll-stat-employees">Employees <span class="val" id="statEmployees">—</span></span>
                <span class="payroll-stat-inline payroll-stat-net">Net <span class="val" id="statNet">—</span></span>
                <span class="payroll-fx-badges" id="payrollFxBadges">
                    <span class="payroll-stat-inline payroll-stat-rate" title="INR per 1 USD/RMB — fetched on the 1st of the month">Current INR Rate <span class="val" id="statInrRate">—</span></span>
                    <span class="payroll-stat-inline payroll-stat-fx"><span id="statFxLabel">USD Amount</span> <span class="val" id="statFxAmount">—</span></span>
                </span>
            </div>
            <div class="input-group input-group-sm">
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
            <select class="form-select form-select-sm payroll-month-select" id="payrollMonthSelect">
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
            <button type="button" class="btn btn-sm btn-outline-success text-nowrap" id="btnSyncFinalHours" title="Copy Final Hour into Hours LM and recalculate salary"><i class="ri-arrow-left-right-line"></i> Sync Final Hour</button>
            @endif
        </div>
    </div>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-salary" role="tabpanel">
            <div class="payroll-card p-2">
                <div id="employeesTable"></div>
            </div>
        </div>
        <div class="tab-pane fade" id="tab-china" role="tabpanel">
            <div class="payroll-card p-2">
                <div id="chinaEmployeesTable"></div>
            </div>
        </div>
        <div class="tab-pane fade" id="tab-usa" role="tabpanel">
            <div class="payroll-card p-2">
                <div id="usaEmployeesTable"></div>
            </div>
        </div>
        <div class="tab-pane fade" id="tab-increment" role="tabpanel">
            <div class="payroll-card p-3 inc-wrap">
                <div class="inc-header">
                    <div>
                        <h5 class="inc-title">Increments</h5>
                        <p class="inc-subtitle" id="incSubtitle">Employees with a salary increment, month wise.</p>
                    </div>
                    <div class="inc-controls">
                        <div class="inc-search">
                            <i class="ri-search-line"></i>
                            <input type="text" id="incrementSearch" class="form-control form-control-sm" placeholder="Search employee">
                        </div>
                        <select id="incrementMonthSelect" class="form-select form-select-sm" title="Select month">
                            <option value="all">All Months</option>
                        </select>
                        @if($canManage)
                        <a href="#" class="btn btn-sm btn-success inc-btn-add" id="btnDownloadIncrements" title="Download Excel (one tab per month)">
                            <i class="ri-file-excel-2-line"></i> Download Excel
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary inc-btn-add" id="btnBulkIncrement" title="Repeat the same increments in the next month for all or selected employees">
                            <i class="ri-file-copy-line"></i> Copy to Next Month
                        </button>
                        <button type="button" class="btn btn-sm btn-primary inc-btn-add" id="btnAddIncrement">
                            <i class="ri-add-line"></i> Add Increment
                        </button>
                        @endif
                    </div>
                </div>
                <div id="incrementSummary"></div>
                <div id="incrementList"><div class="text-muted small">Loading…</div></div>
            </div>
        </div>
    </div>
    <input type="file" id="payrollDocInput" class="d-none" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
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

<div class="modal fade" id="incrementModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formIncrement">
                <div class="modal-header">
                    <h5 class="modal-title" id="incrementModalTitle">Add Increment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Month</label>
                        <select id="incModalMonth" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Employee</label>
                        <div class="inc-combo" id="incCombo">
                            <input type="text" id="incComboInput" class="form-control" placeholder="Search employee name…" autocomplete="off">
                            <div class="inc-combo-list" id="incComboList" hidden></div>
                        </div>
                        <div class="form-text" id="incModalCurrent"></div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-semibold">Increment amount</label>
                        <div class="input-group">
                            <span class="input-group-text" id="incModalCurrency">₹</span>
                            <input type="number" id="incModalAmount" class="form-control" min="0" step="0.01" placeholder="0" required>
                        </div>
                        <div class="form-text">Added on top of Salary PP for the selected month. Saving replaces any existing increment. No hours recorded yet? It is still saved and counts in salary once hours start.</div>
                    </div>
                    <div class="text-danger small mt-2" id="incModalError" hidden></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="incModalSave">Save Increment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="incrementBulkModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Copy Increments to Next Month</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Copy from</label>
                        <select id="incBulkFrom" class="form-select"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Copy to</label>
                        <select id="incBulkTo" class="form-select" title="Increments are copied to the next month"></select>
                    </div>
                </div>
                <div class="text-danger small mb-2" id="incBulkTargetNote"></div>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="incBulkOverwrite">
                        <label class="form-check-label small" for="incBulkOverwrite">Overwrite if the target month already has an increment</label>
                    </div>
                    <span class="small text-muted" id="incBulkCount"></span>
                </div>
                <div class="inc-search mb-2" style="flex:none;">
                    <i class="ri-search-line"></i>
                    <input type="text" id="incBulkSearch" class="form-control form-control-sm" placeholder="Search employee" autocomplete="off">
                </div>
                <div class="inc-bulk-list" id="incBulkList"></div>
                <div class="text-danger small mt-2" id="incBulkError" hidden></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="incBulkSave">Copy Selected to Next Month</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="incrementBulkEditModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formIncrementBulkEdit">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Increment — <span id="incBEMonth"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="small fw-semibold mb-1" id="incBECount"></div>
                    <div class="inc-be-chips mb-3" id="incBEChips"></div>
                    <label class="form-label small fw-semibold">Increment amount (same for all selected)</label>
                    <div class="input-group">
                        <span class="input-group-text" id="incBECurrency">₹</span>
                        <input type="number" id="incBEAmount" class="form-control" min="0" step="0.01" placeholder="0" required>
                    </div>
                    <div class="form-text" id="incBEHint"></div>
                    <div class="text-danger small mt-2" id="incBEError" hidden></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="incBESave">Save for Selected</button>
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

    function fmt(n, region) {
        const amount = Number(n || 0);
        const r = String(region || (typeof activeSalaryRegion !== 'undefined' ? activeSalaryRegion : 'india') || 'india').toLowerCase();
        if (r === 'china') {
            return 'RMB ' + amount.toLocaleString('en-US', { maximumFractionDigits: 0 });
        }
        if (r === 'usa') {
            return 'US $' + amount.toLocaleString('en-US', { maximumFractionDigits: 0 });
        }
        return '₹' + amount.toLocaleString('en-IN', { maximumFractionDigits: 0 });
    }

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
        const locked = !!(employeeRowsById[rowData.id]?._locked);
        employeeRowsById[rowData.id] = Object.assign({}, rowData, { _locked: locked });
        // Country change moves the row between India / China / USA tables.
        redistributeEmployeeRows();
        applyPayrollFilters();
    }

    async function onHoursEdited(cell) {
        const row = cell.getRow();
        const d = row.getData();
        const value = parseFloat(cell.getValue());
        if (isNaN(value) || value < 0) {
            cell.restoreOldValue();
            return;
        }
        try {
            const res = await api(`${base}/employee-salary/${d.id}`, 'PUT', { hours_worked: value });
            const fresh = res.row || {};
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

    const REGION_META = {
        india: { label: 'India', flag: '🇮🇳', title: 'Salary' },
        china: { label: 'China', flag: '🇨🇳', title: 'China' },
        usa: { label: 'USA', flag: '🇺🇸', title: 'USA' },
    };

    function normalizeRegion(value) {
        const v = String(value || 'india').toLowerCase();
        if (v === 'default') return 'india';
        return REGION_META[v] ? v : 'india';
    }

    function countryLabel(region) {
        const meta = REGION_META[normalizeRegion(region)] || REGION_META.india;
        return meta.flag + ' ' + meta.label;
    }

    let employeesTable = null;
    let employeesTableBuilt = false;
    let chinaEmployeesTable = null;
    let chinaEmployeesTableBuilt = false;
    let usaEmployeesTable = null;
    let usaEmployeesTableBuilt = false;
    let pendingEmployeesData = [];
    let pendingChinaEmployeesData = [];
    let pendingUsaEmployeesData = [];
    let allEmployeesData = [];
    let activeSalaryRegion = 'india';

    function regionForEmployee(d) {
        return normalizeRegion(d?.salary_region);
    }

    function buildEmployeeColumns() {
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
            { title: 'Name', field: 'name', minWidth: 160, formatter: (c) => esc(c.getRow().getData().name || '—') },
            {
                title: 'Country', field: 'salary_region', hozAlign: 'center', width: 130, headerSort: false,
                formatter: (c) => {
                    const region = normalizeRegion(c.getValue());
                    const locked = !!c.getRow().getData()._locked || !canManage;
                    if (locked) {
                        return '<span title="Country">' + countryLabel(region) + '</span>';
                    }
                    return '<select class="payroll-country-select" data-row-id="' + esc(String(c.getRow().getData().id)) + '" title="Country">'
                        + '<option value="india"' + (region === 'india' ? ' selected' : '') + '>🇮🇳 India</option>'
                        + '<option value="china"' + (region === 'china' ? ' selected' : '') + '>🇨🇳 China</option>'
                        + '<option value="usa"' + (region === 'usa' ? ' selected' : '') + '>🇺🇸 USA</option>'
                        + '</select>';
                }
            },
            { title: 'Hours LM', field: 'hours_worked', hozAlign: 'center', width: 110,
                editor: canManage ? 'number' : false,
                editorParams: { min: 0, step: 1, selectContents: true },
                editable: (cell) => canManage && !cell.getRow().getData()._locked,
                cellEdited: onHoursEdited,
                formatter: (c) => {
                    const d = c.getRow().getData();
                    const v = parseFloat(c.getValue());
                    const txt = isNaN(v) ? '—' : (Math.round(v) + 'h');
                    if (d.hours_overridden) {
                        const who = d.edited_by ? ' title="Edited by ' + esc(d.edited_by) + '"' : '';
                        return '<strong>' + txt + ' <i class="ri-pencil-fill text-primary"' + who + '></i></strong>';
                    }
                    return txt;
                } },
            { title: 'Logger', field: 'final_hours', hozAlign: 'center', width: 110,
                headerTooltip: 'The larger of Team Logger and New Logger',
                formatter: (c) => {
                    const d = c.getRow().getData();
                    const v = parseFloat(c.getValue());
                    if (isNaN(v)) return '—';
                    const teamFull = parseFloat(d.team_logger_hours);
                    const neu = parseFloat(d.new_logger_hours);
                    let tip;
                    if (d.attendance_only) {
                        const teamH = isNaN(teamFull) ? 0 : Math.round(teamFull);
                        const neuH = isNaN(neu) ? 0 : Math.round(neu);
                        const used = teamH >= neuH ? 'Team Logger' : 'New Logger';
                        tip = Math.round(v) + 'h from ' + used + ' (' + teamH + 'h Team Logger, ' + neuH + 'h New Logger)';
                    } else {
                        const team = parseFloat(d.team_logger_split_hours ?? d.team_logger_15_hours);
                        const second = parseFloat(d.final_second_hours);
                        const secondH = !isNaN(second) ? Math.round(second) : (isNaN(neu) ? 0 : Math.round(neu));
                        const secondSrc = (!isNaN(neu) && neu > 0) ? 'New Logger' : 'TeamLogger';
                        const teamRange = (d.logger_team_from && d.logger_team_to)
                            ? (d.logger_team_from + ' – ' + d.logger_team_to)
                            : 'first 18 days';
                        const afterRange = (d.logger_new_from && d.logger_new_to)
                            ? (d.logger_new_from + ' – ' + d.logger_new_to)
                            : 'after day 18';
                        tip = (isNaN(team) ? 0 : Math.round(team)) + 'h TeamLogger (' + teamRange + ') + '
                            + secondH + 'h ' + secondSrc + ' (' + afterRange + ')';
                    }
                    return '<span title="' + esc(tip) + '">' + Math.round(v) + 'h</span>';
                } },
            { title: 'Salary PP', field: 'salary_pp', minWidth: 110, hozAlign: 'right', formatter: (c) => fmt(c.getValue(), c.getRow().getData().salary_region) },
            { title: 'Incr', field: 'increment', minWidth: 90, hozAlign: 'right', formatter: (c) => fmt(c.getValue(), c.getRow().getData().salary_region) },
            { title: 'Other', field: 'other', minWidth: 90, hozAlign: 'right', formatter: (c) => fmt(c.getValue(), c.getRow().getData().salary_region) },
            { title: 'Incentive', field: 'incentive', minWidth: 100, hozAlign: 'right', formatter: (c) => fmt(c.getValue(), c.getRow().getData().salary_region) },
            { title: 'Docs', field: 'documents', hozAlign: 'center', headerSort: false, width: 70, minWidth: 70,
                formatter: (c) => formatDocumentsCell(c.getRow().getData(), !!c.getRow().getData()._locked) },
            { title: 'Advance', field: 'adv_inc_other', minWidth: 100, hozAlign: 'right', formatter: (c) => fmt(c.getValue(), c.getRow().getData().salary_region) },
            { title: 'Amount', field: 'gross_amount', minWidth: 110, hozAlign: 'right', formatter: (c) => {
                const d = c.getRow().getData();
                return fmt(d.gross_amount ?? d.amount_lm, d.salary_region);
            } },
            { title: 'Payable', field: 'net_amount', minWidth: 110, hozAlign: 'right', formatter: (c) => {
                const d = c.getRow().getData();
                return '<strong>' + fmt(d.net_amount ?? d.amount_p, d.salary_region) + '</strong>';
            } },
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
                return '<span class="payroll-history-tip" data-tip="' + esc(tip).replace(/"/g, '&quot;') + '"><i class="ri-search-line text-primary"></i></span>';
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
        return columns;
    }

    const regionTableState = {
        india: { table: null, built: false, el: '#employeesTable', placeholder: 'No employees — sync from Team Management.', get pending() { return pendingEmployeesData; }, setPending(v) { pendingEmployeesData = v; }, setTable(t) { employeesTable = t; }, getTable() { return employeesTable; }, setBuilt(b) { employeesTableBuilt = b; }, getBuilt() { return employeesTableBuilt; } },
        china: { table: null, built: false, el: '#chinaEmployeesTable', placeholder: 'No China candidates for this month.', get pending() { return pendingChinaEmployeesData; }, setPending(v) { pendingChinaEmployeesData = v; }, setTable(t) { chinaEmployeesTable = t; }, getTable() { return chinaEmployeesTable; }, setBuilt(b) { chinaEmployeesTableBuilt = b; }, getBuilt() { return chinaEmployeesTableBuilt; } },
        usa: { table: null, built: false, el: '#usaEmployeesTable', placeholder: 'No USA candidates for this month.', get pending() { return pendingUsaEmployeesData; }, setPending(v) { pendingUsaEmployeesData = v; }, setTable(t) { usaEmployeesTable = t; }, getTable() { return usaEmployeesTable; }, setBuilt(b) { usaEmployeesTableBuilt = b; }, getBuilt() { return usaEmployeesTableBuilt; } },
    };

    function ensureEmployeeTable(kind) {
        const state = regionTableState[kind];
        if (!state) return null;
        if (state.getTable()) return state.getTable();

        const table = new Tabulator(state.el, {
            layout: 'fitColumns',
            placeholder: state.placeholder,
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [25, 50, 100, 200],
            columns: buildEmployeeColumns(),
            data: [],
        });
        table.on('tableBuilt', () => {
            state.setBuilt(true);
            state.getTable().setData(state.pending);
            applyPayrollFilters();
        });
        state.setTable(table);
        return table;
    }

    function redistributeEmployeeRows() {
        const data = Object.values(employeeRowsById);
        allEmployeesData = data;
        pendingEmployeesData = data.filter((e) => regionForEmployee(e) === 'india');
        pendingChinaEmployeesData = data.filter((e) => regionForEmployee(e) === 'china');
        pendingUsaEmployeesData = data.filter((e) => regionForEmployee(e) === 'usa');

        if (employeesTableBuilt) employeesTable.setData(pendingEmployeesData);
        if (chinaEmployeesTableBuilt) chinaEmployeesTable.setData(pendingChinaEmployeesData);
        if (usaEmployeesTableBuilt) usaEmployeesTable.setData(pendingUsaEmployeesData);
    }

    function renderEmployeesTable(emps, locked) {
        const data = (emps || []).map(e => Object.assign({}, e, {
            _locked: locked,
            salary_region: normalizeRegion(e.salary_region),
        }));
        Object.keys(employeeRowsById).forEach(k => delete employeeRowsById[k]);
        data.forEach(e => { employeeRowsById[e.id] = e; });

        ensureEmployeeTable('india');
        ensureEmployeeTable('china');
        ensureEmployeeTable('usa');
        redistributeEmployeeRows();
    }

    function updateRegionUi() {
        const region = normalizeRegion(activeSalaryRegion);
        const meta = REGION_META[region] || REGION_META.india;
        const title = document.getElementById('payrollRegionTitle');
        if (title) {
            title.innerHTML = '<span class="me-1">' + meta.flag + '</span>' + meta.title;
        }
        const id = currentMonthId();
        if (id) {
            document.getElementById('btnDownloadPayoutSheet')?.setAttribute(
                'href',
                `${base}/month/${id}/payout-sheet?region=${region}`
            );
        }
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

    let monthFxRates = { inr_usd_rate: null, inr_cny_rate: null };

    async function loadMonth() {
        const id = currentMonthId();
        if (!id) return;
        monthId = id;
        const data = await api(`${base}/month/${id}/data`);
        const m = data.month;
        const emps = data.employees || [];

        monthFxRates = {
            inr_usd_rate: m?.inr_usd_rate != null ? parseFloat(m.inr_usd_rate) : null,
            inr_cny_rate: m?.inr_cny_rate != null ? parseFloat(m.inr_cny_rate) : null,
        };

        Object.keys(employeeRowsById).forEach(k => delete employeeRowsById[k]);
        emps.forEach(e => { employeeRowsById[e.id] = e; });

        renderEmployeesTable(emps, !!m.is_locked);
        applyPayrollFilters();
        renderStatusSection(m);
        updateMonthSelectOption(m);
        updateRegionUi();
    }

    function updateEmployeeStats(rows) {
        const list = rows || [];
        const netInr = list.reduce((s, e) => s + parseFloat(e.net_amount || 0), 0);
        document.getElementById('statEmployees').textContent = list.length;
        document.getElementById('statNet').textContent = fmt(netInr, activeSalaryRegion);

        const region = normalizeRegion(activeSalaryRegion);
        const fxWrap = document.getElementById('payrollFxBadges');
        const rateEl = document.getElementById('statInrRate');
        const fxLabel = document.getElementById('statFxLabel');
        const fxAmount = document.getElementById('statFxAmount');

        if (!fxWrap || !rateEl || !fxLabel || !fxAmount) return;

        if (region !== 'usa' && region !== 'china') {
            fxWrap.classList.remove('is-visible');
            return;
        }

        fxWrap.classList.add('is-visible');
        const rate = region === 'usa' ? monthFxRates.inr_usd_rate : monthFxRates.inr_cny_rate;
        rateEl.textContent = (rate && rate > 0)
            ? Number(rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 })
            : '—';
        fxLabel.textContent = region === 'usa' ? 'USD Amount' : 'RMB Amount';
        if (rate && rate > 0) {
            const converted = netInr / rate;
            fxAmount.textContent = region === 'usa'
                ? ('US $' + converted.toLocaleString('en-US', { maximumFractionDigits: 2 }))
                : ('RMB ' + converted.toLocaleString('en-US', { maximumFractionDigits: 2 }));
        } else {
            fxAmount.textContent = '—';
        }
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

    document.getElementById('btnSyncFinalHours')?.addEventListener('click', async () => {
        if (!confirm('Copy Final Hour into Hours LM for this month and recalculate salary?')) return;
        try {
            const res = await api(`${base}/month/${currentMonthId()}/sync-final-hours`, 'POST', {});
            alert(res.message || 'Final Hour synced to Hours LM.');
            loadMonth();
        } catch (err) {
            alert((err && err.message) ? err.message : 'Failed to sync Final Hour.');
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
            // the live hours refresh). Hours editing lives on the table
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
        if (chinaEmployeesTable && chinaEmployeesTableBuilt) {
            try { chinaEmployeesTable.setFilter(empFilter); } catch (e) {}
        }
        if (usaEmployeesTable && usaEmployeesTableBuilt) {
            try { usaEmployeesTable.setFilter(empFilter); } catch (e) {}
        }

        const region = normalizeRegion(activeSalaryRegion);
        const activeRows = region === 'china'
            ? (pendingChinaEmployeesData || [])
            : (region === 'usa' ? (pendingUsaEmployeesData || []) : (pendingEmployeesData || []));
        updateEmployeeStats(activeRows.filter(empFilter));

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

    const historyPop = document.createElement('div');
    historyPop.className = 'payroll-history-pop';
    historyPop.hidden = true;
    document.body.appendChild(historyPop);

    function placeHistoryPop(anchor) {
        const text = anchor.getAttribute('data-tip') || 'Edited';
        historyPop.textContent = text;
        historyPop.hidden = false;
        const rect = anchor.getBoundingClientRect();
        const pop = historyPop.getBoundingClientRect();
        let left = rect.left + (rect.width / 2) - (pop.width / 2);
        left = Math.max(8, Math.min(left, window.innerWidth - pop.width - 8));
        let top = rect.top - pop.height - 8;
        if (top < 8) top = rect.bottom + 8;
        historyPop.style.left = left + 'px';
        historyPop.style.top = top + 'px';
    }

    document.addEventListener('click', (e) => {
        const tip = e.target.closest('.payroll-history-tip');
        if (!tip) {
            historyPop.hidden = true;
            return;
        }
        e.preventDefault();
        placeHistoryPop(tip);
    });

    // Country flag dropdown — save to DB and move row to the matching tab.
    document.getElementById('payrollApp')?.addEventListener('change', async (e) => {
        const select = e.target.closest('.payroll-country-select');
        if (!select) return;
        const rowId = select.dataset.rowId;
        const row = employeeRowsById[rowId];
        if (!row) return;
        const previous = normalizeRegion(row.salary_region);
        const value = normalizeRegion(select.value);
        if (value === previous) return;
        select.disabled = true;
        try {
            const res = await api(`${base}/employee-salary/${rowId}`, 'PUT', { salary_region: value });
            applyEmployeeRowUpdate(Object.assign({}, row, res.row || {}, { salary_region: value }));
        } catch (err) {
            select.value = previous;
            alert((err && err.message) ? err.message : 'Failed to save country.');
        } finally {
            select.disabled = false;
        }
    });

    // ---------- Increment tab ----------
    let incrementData = null;
    let salarySheetStale = false;
    let incrementMonthChosen = false;

    function formatTotals(totals) {
        const order = ['india', 'china', 'usa'];
        const parts = order.filter((r) => totals && totals[r]).map((r) => fmt(totals[r], r));
        return parts.length ? parts.join(' + ') : fmt(0, 'india');
    }

    function initials(name) {
        const parts = String(name || '?').trim().split(/\s+/);
        return ((parts[0] || '?')[0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
    }

    // Profile image (falls back to initials when missing or if the image fails to load).
    function avatarHtml(u) {
        const ini = esc(initials(u.name));
        if (!u.avatar_url) return '<span class="inc-avatar">' + ini + '</span>';
        return '<span class="inc-avatar has-img"><img src="' + esc(u.avatar_url) + '" alt="" class="rounded-circle" loading="lazy"'
            + ' onerror="var p=this.parentNode;p.classList.remove(\'has-img\');p.textContent=\'' + ini.replace(/'/g, '') + '\';"></span>';
    }

    function findIncMonth(id) {
        return (incrementData?.months || []).find((m) => String(m.month_id) === String(id));
    }

    // Column sorting for the month tables (click Employee / Increment headers).
    let incSort = { key: 'name', dir: 'asc' };

    function incSortUsers(users) {
        const dir = incSort.dir === 'desc' ? -1 : 1;
        const byName = (a, b) => String(a.name).localeCompare(String(b.name), undefined, { sensitivity: 'base', numeric: true });
        return users.slice().sort((a, b) => {
            if (incSort.key === 'increment') {
                const d = (Number(a.increment || 0) - Number(b.increment || 0)) * dir;
                return d !== 0 ? d : byName(a, b);
            }
            return byName(a, b) * dir;
        });
    }

    function incSortTh(label, key, cls) {
        const active = incSort.key === key;
        const arrow = active ? (incSort.dir === 'asc' ? '▲' : '▼') : '⇅';
        return '<th class="inc-sortable' + (active ? ' is-sorted' : '') + (cls ? ' ' + cls : '') + '" data-sort="' + key + '" title="Sort by ' + label + '">'
            + label + '<span class="arr">' + arrow + '</span></th>';
    }

    // ----- Row selection for bulk edit (per month card) -----
    const incSel = {};
    function incSelSet(monthId) { return (incSel[monthId] = incSel[monthId] || new Set()); }

    // DOM is the source of truth: only rows visible in the card can be selected.
    function incRefreshCard(card) {
        const monthId = Number(card.dataset.month);
        const rows = Array.from(card.querySelectorAll('.inc-row-chk'));
        const set = incSelSet(monthId);
        set.clear();
        rows.forEach((c) => {
            if (c.checked) set.add(Number(c.value));
            c.closest('tr')?.classList.toggle('is-selected', c.checked);
        });
        const all = card.querySelector('.inc-card-all');
        if (all) {
            all.checked = rows.length > 0 && set.size === rows.length;
            all.indeterminate = set.size > 0 && set.size < rows.length;
        }
    }

    function renderIncrements() {
        const box = document.getElementById('incrementList');
        const summary = document.getElementById('incrementSummary');
        const subtitle = document.getElementById('incSubtitle');
        const select = document.getElementById('incrementMonthSelect');
        if (!box || !incrementData) return;

        const selected = select ? select.value : 'all';
        const term = (document.getElementById('incrementSearch')?.value || '').toLowerCase().trim();
        const single = selected !== 'all' ? findIncMonth(selected) : null;
        // "All Months" only lists months that have at least one increment; a single month always shows.
        const sourceMonths = single ? [single] : (incrementData.months || []).filter((m) => (m.increment_count || 0) > 0);

        // Every employee is listed (0 when no increment); the name search narrows the list.
        const months = sourceMonths.map((m) => {
            const users = incSortUsers((m.users || []).filter((u) => !term || String(u.name).toLowerCase().includes(term)));
            const totals = {};
            let withInc = 0;
            users.forEach((u) => {
                const amt = Number(u.increment || 0);
                if (amt) { totals[u.region] = (totals[u.region] || 0) + amt; withInc++; }
            });
            return Object.assign({}, m, { users, totals, withInc });
        }).filter((m) => single || m.users.length);

        const totals = {};
        let entries = 0;
        months.forEach((m) => {
            entries += m.withInc;
            Object.keys(m.totals).forEach((r) => { totals[r] = (totals[r] || 0) + m.totals[r]; });
        });

        if (subtitle) {
            subtitle.textContent = single
                ? 'All employees for ' + single.month_label + ' — 0 where no increment.'
                : 'Month wise increments for all employees — 0 where no increment.';
        }

        // Third card: the month (with year) + its status, or "All Months" with the covered range.
        let monthCard;
        if (single) {
            monthCard = '<div class="inc-stat"><div class="k">Month</div>'
                + '<div class="v">' + esc(single.month_label)
                + ' <span class="inc-badge ' + (single.is_locked ? 'locked' : 'open') + '" style="vertical-align:middle;margin-left:.35rem;">'
                + (single.is_locked ? '🔒 Locked' : '● Open') + '</span></div></div>';
        } else {
            const newest = months[0]?.month_label;
            const oldest = months[months.length - 1]?.month_label;
            const range = months.length > 1 ? esc(oldest) + ' – ' + esc(newest) : esc(newest || '');
            monthCard = '<div class="inc-stat"><div class="k">Month</div>'
                + '<div class="v">All Months</div>'
                + '<div class="small text-muted mt-1">' + range + (months.length ? ' · ' + months.length + ' month' + (months.length === 1 ? '' : 's') : '') + '</div></div>';
        }

        if (summary) {
            summary.innerHTML = '<div class="inc-stats">'
                + '<div class="inc-stat is-primary"><div class="k">Total Increment</div><div class="v">' + esc(formatTotals(totals)) + '</div></div>'
                + '<div class="inc-stat"><div class="k">Employees with increment</div><div class="v">' + entries + '</div></div>'
                + monthCard
                + '</div>';
        }

        if (!months.length) {
            box.innerHTML = '<div class="inc-empty"><div class="big">📭</div>'
                + '<div class="t">' + (term ? 'No matching employees' : 'No increments found') + '</div>'
                + '<div class="small">' + (canManage && !term ? 'Use “Add Increment” to give someone an increment.' : '') + '</div></div>';
            return;
        }

        const grid = document.createElement('div');
        grid.className = 'inc-grid' + (months.length === 1 ? ' is-single' : '');
        grid.innerHTML = months.map((m) => {
            const editable = canManage && !m.is_locked;
            const selSet = incSelSet(m.month_id);
            const rowsHtml = m.users.map((u, i) => '<tr class="' + (editable && selSet.has(Number(u.user_id)) ? 'is-selected' : '') + '">'
                + (canManage ? '<td class="chk">' + (editable
                    ? '<input type="checkbox" class="inc-row-chk" value="' + u.user_id + '"' + (selSet.has(Number(u.user_id)) ? ' checked' : '') + '>'
                    : '') + '</td>' : '')
                + '<td class="idx">' + (i + 1) + '</td>'
                + '<td><span class="inc-person">' + avatarHtml(u) + esc(u.name) + '</span></td>'
                + '<td class="num"><span class="' + (Number(u.increment) ? 'inc-amt' : 'inc-zero') + '">' + esc(fmt(u.increment || 0, u.region)) + '</span></td>'
                + (canManage ? '<td class="act">' + (editable
                    ? '<button type="button" class="inc-icon-btn inc-edit" title="' + (Number(u.increment) ? 'Edit (applies to all ticked rows if several are selected)' : 'Add increment (applies to all ticked rows if several are selected)') + '" data-month="' + m.month_id + '" data-user="' + u.user_id + '"><i class="' + (Number(u.increment) ? 'ri-pencil-line' : 'ri-add-circle-line') + '"></i></button>'
                    + (Number(u.increment) ? '<button type="button" class="inc-icon-btn danger inc-remove" title="Remove increment" data-month="' + m.month_id + '" data-user="' + u.user_id + '"><i class="ri-delete-bin-line"></i></button>' : '')
                    : '<span class="text-muted" title="Month is locked">🔒</span>') + '</td>' : '')
                + '</tr>').join('');
            const emptyRow = m.users.length ? '' : '<tr><td colspan="' + (canManage ? 5 : 3) + '" class="text-center text-muted py-4">No employees in this month.</td></tr>';
            return '<div class="inc-card" data-month="' + m.month_id + '">'
                + '<div class="inc-card-head"><span><span class="m">' + esc(m.month_label) + '</span><span class="c">' + m.withInc + ' of ' + m.users.length + ' with increment</span></span>'
                + '<span class="t">' + esc(formatTotals(m.totals)) + '</span></div>'
                + '<table class="inc-table"><thead><tr>'
                + (canManage ? '<th class="chk">' + (editable ? '<input type="checkbox" class="inc-card-all" title="Select all">' : '') + '</th>' : '')
                + '<th class="idx">#</th>' + incSortTh('Employee', 'name') + incSortTh('Increment', 'increment', 'num')
                + (canManage ? '<th class="act"></th>' : '') + '</tr></thead><tbody>' + rowsHtml + emptyRow + '</tbody>'
                + (m.users.length > 1 ? '<tfoot><tr>' + (canManage ? '<td></td>' : '') + '<td></td><td>Total</td><td class="num">' + esc(formatTotals(m.totals)) + '</td>' + (canManage ? '<td></td>' : '') + '</tr></tfoot>' : '')
                + '</table></div>';
        }).join('');
        box.innerHTML = '';
        box.appendChild(grid);
        // Sync each card's bulk bar / header checkbox, and drop selections no longer on screen.
        grid.querySelectorAll('.inc-card').forEach((card) => incRefreshCard(card));
    }

    function fillIncrementMonthSelect() {
        const select = document.getElementById('incrementMonthSelect');
        if (!select || !incrementData) return;
        const keep = incrementMonthChosen ? select.value : null;
        select.innerHTML = '<option value="all">All Months</option>'
            + (incrementData.months || []).map((m) => '<option value="' + m.month_id + '">' + esc(m.month_label) + '</option>').join('');
        const wanted = keep || (incrementData.default_month_id ? String(incrementData.default_month_id) : 'all');
        select.value = Array.from(select.options).some((o) => o.value === wanted) ? wanted : 'all';
    }

    async function loadIncrements() {
        const box = document.getElementById('incrementList');
        const summary = document.getElementById('incrementSummary');
        if (!box) return;
        box.innerHTML = '<div class="text-muted small">Loading…</div>';
        if (summary) summary.innerHTML = '';
        try {
            incrementData = await api(`${base}/increments`);
            fillIncrementMonthSelect();
            renderIncrements();
        } catch (err) {
            incrementData = null;
            box.innerHTML = '<div class="text-danger small">' + esc(err.message || 'Failed to load increments.') + '</div>';
        }
    }

    document.getElementById('incrementMonthSelect')?.addEventListener('change', () => {
        incrementMonthChosen = true;
        renderIncrements();
    });
    document.getElementById('incrementSearch')?.addEventListener('input', renderIncrements);

    // ----- Add / edit increment modal -----
    const incModalEl = document.getElementById('incrementModal');
    const incModalMonth = document.getElementById('incModalMonth');
    const incModalAmount = document.getElementById('incModalAmount');
    const incModalCurrent = document.getElementById('incModalCurrent');
    const incModalCurrency = document.getElementById('incModalCurrency');
    const incModalError = document.getElementById('incModalError');
    const incComboInput = document.getElementById('incComboInput');
    const incComboList = document.getElementById('incComboList');
    let incSelected = null;      // { user_id, name, region, increment }
    let incComboItems = [];      // currently rendered options
    let incComboActive = -1;

    // Everyone on the Salary tab (+ anyone already on this month's sheet), with this month's current increment.
    function incModalEmployeesForMonth(monthId) {
        const m = findIncMonth(monthId);
        const byUser = {};
        (incrementData?.salary_users || []).forEach((u) => {
            byUser[u.user_id] = { user_id: u.user_id, name: u.name, region: u.region, avatar_url: u.avatar_url, increment: 0 };
        });
        (m?.employees || []).forEach((e) => {
            byUser[e.user_id] = { user_id: e.user_id, name: e.name, region: e.region, avatar_url: e.avatar_url, increment: e.increment || 0 };
        });
        return Object.values(byUser).sort((a, b) => String(a.name).localeCompare(String(b.name), undefined, { sensitivity: 'base' }));
    }

    function incSetSelected(emp) {
        incSelected = emp || null;
        if (emp) {
            incComboInput.value = emp.name;
            incModalCurrency.textContent = (fmt(0, emp.region).replace(/[0-9,.\s]+$/, '').trim()) || '₹';
            incModalCurrent.textContent = emp.increment
                ? 'Current increment: ' + fmt(emp.increment, emp.region) + '. Saving will replace it.'
                : '';
            if (emp.increment) incModalAmount.value = emp.increment;
        } else {
            incModalCurrent.textContent = '';
            incModalCurrency.textContent = '₹';
        }
    }

    function incComboRender() {
        const term = incComboInput.value.toLowerCase().trim();
        const all = incModalEmployeesForMonth(incModalMonth.value);
        incComboItems = all.filter((e) => !term || String(e.name).toLowerCase().includes(term));
        incComboActive = incComboItems.length ? 0 : -1;
        if (!incComboItems.length) {
            incComboList.innerHTML = '<div class="inc-combo-empty">No employee found</div>';
        } else {
            incComboList.innerHTML = incComboItems.map((e, i) =>
                '<div class="inc-combo-item' + (i === incComboActive ? ' is-active' : '') + '" data-idx="' + i + '">'
                + '<span class="who">' + avatarHtml(e) + '<span>' + esc(e.name) + '</span></span>'
                + '<span class="meta' + (e.increment ? ' has' : '') + '">'
                + (e.increment ? 'Current ' + esc(fmt(e.increment, e.region)) : esc(countryLabel(e.region))) + '</span></div>'
            ).join('');
        }
        incComboList.hidden = false;
    }

    function incComboHighlight(delta) {
        if (!incComboItems.length) return;
        incComboActive = (incComboActive + delta + incComboItems.length) % incComboItems.length;
        incComboList.querySelectorAll('.inc-combo-item').forEach((el, i) => {
            el.classList.toggle('is-active', i === incComboActive);
            if (i === incComboActive) el.scrollIntoView({ block: 'nearest' });
        });
    }

    incComboInput?.addEventListener('focus', () => { if (!incComboInput.disabled) incComboRender(); });
    incComboInput?.addEventListener('input', () => {
        if (incSelected && incComboInput.value !== incSelected.name) incSetSelected(null);
        incComboRender();
    });
    incComboInput?.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (incComboList.hidden) incComboRender(); else incComboHighlight(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); incComboHighlight(-1); }
        else if (e.key === 'Enter') {
            if (!incComboList.hidden && incComboActive >= 0 && incComboItems[incComboActive]) {
                e.preventDefault();
                incSetSelected(incComboItems[incComboActive]);
                incComboList.hidden = true;
                incModalAmount.focus();
            }
        } else if (e.key === 'Escape' && !incComboList.hidden) {
            e.stopPropagation();
            incComboList.hidden = true;
        }
    });
    incComboList?.addEventListener('mousedown', (e) => {
        const item = e.target.closest('.inc-combo-item');
        if (!item) return;
        e.preventDefault();
        incSetSelected(incComboItems[Number(item.dataset.idx)]);
        incComboList.hidden = true;
        incModalAmount.focus();
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#incCombo')) incComboList && (incComboList.hidden = true);
    });

    function openIncrementModal(opts) {
        if (!incrementData || !incModalEl) return;
        opts = opts || {};
        const open = (incrementData.months || []).filter((m) => !m.is_locked);
        if (!open.length) { alert('All payroll months are locked.'); return; }
        incModalMonth.innerHTML = open.map((m) => '<option value="' + m.month_id + '">' + esc(m.month_label) + '</option>').join('');
        const sel = document.getElementById('incrementMonthSelect')?.value;
        const preferred = opts.monthId || (sel && sel !== 'all' ? sel : incrementData.default_month_id);
        incModalMonth.value = open.some((m) => String(m.month_id) === String(preferred)) ? String(preferred) : String(open[0].month_id);

        incModalAmount.value = '';
        incComboInput.value = '';
        incSetSelected(null);
        incComboList.hidden = true;
        incModalError.hidden = true;

        if (opts.userId) {
            const emp = incModalEmployeesForMonth(incModalMonth.value).find((x) => String(x.user_id) === String(opts.userId));
            if (emp) incSetSelected(emp);
        }
        document.getElementById('incrementModalTitle').textContent = opts.userId ? 'Edit Increment' : 'Add Increment';
        incModalMonth.disabled = !!opts.userId;
        incComboInput.disabled = !!opts.userId;
        bootstrap.Modal.getOrCreateInstance(incModalEl).show();
        if (!opts.userId) setTimeout(() => incComboInput.focus(), 300);
    }

    document.getElementById('btnAddIncrement')?.addEventListener('click', () => openIncrementModal());
    incModalMonth?.addEventListener('change', () => {
        // Re-resolve the chosen employee against the new month (current increment differs per month).
        const keep = incSelected ? incSelected.user_id : null;
        incModalAmount.value = '';
        incSetSelected(null);
        incComboInput.value = '';
        if (keep) {
            const emp = incModalEmployeesForMonth(incModalMonth.value).find((x) => x.user_id === keep);
            if (emp) incSetSelected(emp);
        }
    });

    // Excel download follows the month dropdown: All Months = one tab per month, otherwise just that month.
    document.getElementById('btnDownloadIncrements')?.addEventListener('click', (e) => {
        e.preventDefault();
        const month = document.getElementById('incrementMonthSelect')?.value || 'all';
        window.location.href = `${base}/increments/export?month=${encodeURIComponent(month)}`;
    });

    function saveIncrement(monthId, userId, amount) {
        return api(`${base}/increments`, 'POST', { payroll_month_id: monthId, user_id: userId, increment: amount });
    }

    document.getElementById('formIncrement')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const amount = parseFloat(incModalAmount.value);
        incModalError.hidden = true;
        if (!incSelected) { incModalError.textContent = 'Please select an employee from the list.'; incModalError.hidden = false; return; }
        if (isNaN(amount) || amount < 0) { incModalError.textContent = 'Enter a valid amount.'; incModalError.hidden = false; return; }
        const btn = document.getElementById('incModalSave');
        btn.disabled = true;
        try {
            await saveIncrement(incModalMonth.value, incSelected.user_id, amount);
            bootstrap.Modal.getInstance(incModalEl)?.hide();
            await loadIncrements();
            salarySheetStale = true;
        } catch (err) {
            incModalError.textContent = err.message || 'Failed to save increment.';
            incModalError.hidden = false;
        } finally {
            btn.disabled = false;
        }
    });

    document.getElementById('incrementList')?.addEventListener('click', async (e) => {
        const edit = e.target.closest('.inc-edit');
        const remove = e.target.closest('.inc-remove');
        // If several employees are ticked and the clicked row is one of them, the pencil / bin act on all of them.
        const btn = edit || remove;
        const picked = btn ? incSelSet(Number(btn.dataset.month)) : null;
        const isBulk = !!(btn && picked && picked.size > 1 && picked.has(Number(btn.dataset.user)));

        if (edit) {
            if (isBulk) {
                openBulkEdit(btn.dataset.month);
            } else {
                openIncrementModal({ monthId: edit.dataset.month, userId: edit.dataset.user });
            }
        } else if (remove) {
            if (isBulk) {
                const ids = Array.from(picked);
                if (!confirm('Remove the increment (set to 0) for ' + ids.length + ' selected employees?')) return;
                remove.disabled = true;
                try {
                    await api(`${base}/increments/bulk`, 'POST', { payroll_month_id: Number(remove.dataset.month), user_ids: ids, increment: 0 });
                    picked.clear();
                    await loadIncrements();
                    salarySheetStale = true;
                } catch (err) {
                    alert(err.message || 'Failed to remove increments.');
                    remove.disabled = false;
                }
                return;
            }
            if (!confirm('Remove this increment (set to 0)?')) return;
            remove.disabled = true;
            try {
                await saveIncrement(remove.dataset.month, remove.dataset.user, 0);
                await loadIncrements();
                salarySheetStale = true;
            } catch (err) {
                alert(err.message || 'Failed to remove increment.');
                remove.disabled = false;
            }
        }
    });

    document.getElementById('incrementList')?.addEventListener('click', (e) => {
        const th = e.target.closest('th.inc-sortable');
        if (!th) return;
        const key = th.dataset.sort;
        if (incSort.key === key) {
            incSort.dir = incSort.dir === 'asc' ? 'desc' : 'asc';
        } else {
            // Names start A-Z; amounts start highest first.
            incSort = { key, dir: key === 'increment' ? 'desc' : 'asc' };
        }
        renderIncrements();
    });

    // ----- Bulk edit: tick rows, then use the pencil on any ticked row -----
    document.getElementById('incrementList')?.addEventListener('change', (e) => {
        const card = e.target.closest('.inc-card');
        if (!card) return;
        if (e.target.classList.contains('inc-card-all')) {
            card.querySelectorAll('.inc-row-chk').forEach((c) => { c.checked = e.target.checked; });
            incRefreshCard(card);
        } else if (e.target.classList.contains('inc-row-chk')) {
            incRefreshCard(card);
        }
    });

    const incBEEl = document.getElementById('incrementBulkEditModal');
    const incBEAmount = document.getElementById('incBEAmount');
    const incBEError = document.getElementById('incBEError');
    let incBEState = null;

    function openBulkEdit(monthId) {
        const month = findIncMonth(monthId);
        const ids = Array.from(incSelSet(monthId));
        if (!month || !ids.length || !incBEEl) return;
        const emps = ids.map((id) => (month.employees || []).find((x) => Number(x.user_id) === id)).filter(Boolean);
        incBEState = { monthId: Number(monthId), userIds: emps.map((x) => Number(x.user_id)) };

        document.getElementById('incBEMonth').textContent = month.month_label;
        document.getElementById('incBECount').textContent = emps.length + ' employee' + (emps.length === 1 ? '' : 's') + ' selected';
        document.getElementById('incBEChips').innerHTML = emps.map((u) =>
            '<span class="inc-be-chip">' + avatarHtml(u) + esc(u.name) + '</span>').join('');

        const regions = Array.from(new Set(emps.map((x) => x.region)));
        document.getElementById('incBECurrency').textContent = regions.length === 1
            ? (fmt(0, regions[0]).replace(/[0-9,.\s]+$/, '').trim() || '₹') : '₹';
        document.getElementById('incBEHint').textContent = regions.length > 1
            ? 'Selected employees are in different countries — the same number is applied to each in their own currency.'
            : 'This amount replaces the current increment of every selected employee for ' + month.month_label + '.';

        const amounts = Array.from(new Set(emps.map((x) => Number(x.increment || 0))));
        incBEAmount.value = amounts.length === 1 && amounts[0] ? amounts[0] : '';
        incBEError.hidden = true;
        bootstrap.Modal.getOrCreateInstance(incBEEl).show();
        setTimeout(() => { incBEAmount.focus(); incBEAmount.select(); }, 300);
    }

    document.getElementById('formIncrementBulkEdit')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!incBEState) return;
        const amount = parseFloat(incBEAmount.value);
        incBEError.hidden = true;
        if (isNaN(amount) || amount < 0) { incBEError.textContent = 'Enter a valid amount.'; incBEError.hidden = false; return; }
        const btn = document.getElementById('incBESave');
        btn.disabled = true;
        try {
            await api(`${base}/increments/bulk`, 'POST', {
                payroll_month_id: incBEState.monthId,
                user_ids: incBEState.userIds,
                increment: amount,
            });
            bootstrap.Modal.getInstance(incBEEl)?.hide();
            incSelSet(incBEState.monthId).clear();
            await loadIncrements();
            salarySheetStale = true;
        } catch (err) {
            incBEError.textContent = err.message || 'Failed to save increments.';
            incBEError.hidden = false;
        } finally {
            btn.disabled = false;
        }
    });

    // ----- Bulk copy increments (e.g. same increment for next month) -----
    const incBulkEl = document.getElementById('incrementBulkModal');
    const incBulkFrom = document.getElementById('incBulkFrom');
    const incBulkTo = document.getElementById('incBulkTo');
    const incBulkList = document.getElementById('incBulkList');
    const incBulkOverwrite = document.getElementById('incBulkOverwrite');
    const incBulkError = document.getElementById('incBulkError');

    function incBulkRender() {
        const from = findIncMonth(incBulkFrom.value);
        const to = findIncMonth(incBulkTo.value);
        const sources = (from?.employees || []).filter((e) => Number(e.increment));
        const targetByUser = {};
        (to?.employees || []).forEach((e) => { targetByUser[e.user_id] = e; });
        incBulkError.hidden = true;

        if (!sources.length) {
            incBulkList.innerHTML = '<div class="inc-empty" style="border:0;">No increments in ' + esc(from?.month_label || 'this month') + ' to copy.</div>';
            incBulkUpdateCount();
            return;
        }

        incBulkList.innerHTML = '<table><thead><tr>'
            + '<th style="width:34px;"><input type="checkbox" id="incBulkAll" checked title="Select all"></th>'
            + '<th>Employee</th><th class="num">Increment</th><th>In ' + esc(to?.month_label || 'target') + '</th></tr></thead><tbody>'
            + sources.map((e) => {
                const t = targetByUser[e.user_id];
                const has = t && Number(t.increment);
                return '<tr class="' + (has ? 'is-conflict' : '') + '" data-user="' + e.user_id + '" data-conflict="' + (has ? 1 : 0) + '">'
                    + '<td><input type="checkbox" class="inc-bulk-chk" value="' + e.user_id + '" checked></td>'
                    + '<td><span class="inc-person">' + avatarHtml(e) + esc(e.name) + '</span></td>'
                    + '<td class="num"><span class="inc-amt">' + esc(fmt(e.increment, e.region)) + '</span></td>'
                    + '<td>' + (has ? 'Already ' + esc(fmt(t.increment, t.region)) : '<span class="text-muted">—</span>') + '</td>'
                    + '</tr>';
            }).join('')
            + '</tbody></table>'
            + '<div class="inc-combo-empty" id="incBulkNoMatch" hidden>No matching employees</div>';
        incBulkFilter();
        incBulkUpdateCount();
    }

    // Name search: hides non-matching rows (their ticked state is kept).
    function incBulkFilter() {
        const term = (document.getElementById('incBulkSearch')?.value || '').toLowerCase().trim();
        let visible = 0;
        incBulkList.querySelectorAll('tbody tr').forEach((tr) => {
            const name = (tr.querySelector('.inc-person')?.textContent || '').toLowerCase();
            const show = !term || name.includes(term);
            tr.hidden = !show;
            if (show) visible++;
        });
        const noMatch = document.getElementById('incBulkNoMatch');
        if (noMatch) noMatch.hidden = visible > 0 || !incBulkList.querySelector('tbody tr');
        incBulkUpdateCount();
    }

    document.getElementById('incBulkSearch')?.addEventListener('input', incBulkFilter);

    function incBulkSelectedIds() {
        // Only rows currently visible count: anything hidden by the search is never copied.
        return Array.from(incBulkList.querySelectorAll('tbody tr:not([hidden]) .inc-bulk-chk:checked')).map((c) => Number(c.value));
    }

    function incBulkUpdateCount() {
        const all = incBulkList.querySelectorAll('.inc-bulk-chk').length;
        const total = incBulkList.querySelectorAll('tbody tr:not([hidden]) .inc-bulk-chk').length;
        const sel = incBulkSelectedIds().length;
        document.getElementById('incBulkCount').textContent = all
            ? sel + ' of ' + total + ' selected' + (total < all ? ' (search shows ' + total + ' of ' + all + ')' : '')
            : '';
        document.getElementById('incBulkSave').disabled = sel === 0 || !incBulkTargetOk;
        const headerChk = document.getElementById('incBulkAll');
        if (headerChk) {
            const vis = incBulkList.querySelectorAll('tbody tr:not([hidden]) .inc-bulk-chk');
            headerChk.checked = vis.length > 0 && Array.from(vis).every((c) => c.checked);
        }
    }

    incBulkList?.addEventListener('change', (e) => {
        if (e.target.id === 'incBulkAll') {
            incBulkList.querySelectorAll('tbody tr:not([hidden]) .inc-bulk-chk').forEach((c) => { c.checked = e.target.checked; });
        }
        incBulkUpdateCount();
    });

    let incBulkTargetOk = false;

    // "Copy to" is always just the month right after the source one (newest-first list => index - 1).
    function incBulkFillTargets() {
        const months = incrementData?.months || [];
        const idx = months.findIndex((m) => String(m.month_id) === String(incBulkFrom.value));
        const next = idx > 0 ? months[idx - 1] : null;
        const note = document.getElementById('incBulkTargetNote');

        if (!next) {
            incBulkTo.innerHTML = '<option value="">No next month</option>';
            incBulkTargetOk = false;
            if (note) note.textContent = 'There is no next payroll month yet. Create it first, then copy.';
        } else {
            incBulkTo.innerHTML = '<option value="' + next.month_id + '">' + esc(next.month_label) + '</option>';
            incBulkTo.value = String(next.month_id);
            incBulkTargetOk = !next.is_locked;
            if (note) note.textContent = next.is_locked ? next.month_label + ' is locked, so increments cannot be copied into it.' : '';
        }
        incBulkTo.disabled = true;
    }

    function openBulkModal() {
        if (!incrementData || !incBulkEl) return;
        const months = incrementData.months || [];
        incBulkFrom.innerHTML = months.map((m) => '<option value="' + m.month_id + '">' + esc(m.month_label) + ((m.increment_count || 0) ? ' (' + m.increment_count + ')' : '') + '</option>').join('');
        // Default source = the current month (the one the Salary tab opens on).
        const preferred = incrementData.default_month_id;
        incBulkFrom.value = months.some((m) => String(m.month_id) === String(preferred)) ? String(preferred) : (months[0] ? String(months[0].month_id) : '');
        incBulkOverwrite.checked = false;
        const bulkSearch = document.getElementById('incBulkSearch');
        if (bulkSearch) bulkSearch.value = '';
        incBulkFillTargets();
        incBulkRender();
        bootstrap.Modal.getOrCreateInstance(incBulkEl).show();
    }

    document.getElementById('btnBulkIncrement')?.addEventListener('click', openBulkModal);
    incBulkFrom?.addEventListener('change', () => { incBulkFillTargets(); incBulkRender(); });
    incBulkTo?.addEventListener('change', incBulkRender);

    document.getElementById('incBulkSave')?.addEventListener('click', async () => {
        const ids = incBulkSelectedIds();
        if (!ids.length || !incBulkTargetOk) return;
        const toLabel = findIncMonth(incBulkTo.value)?.month_label || 'the target month';
        const fromLabel = findIncMonth(incBulkFrom.value)?.month_label || 'the source month';
        const names = Array.from(incBulkList.querySelectorAll('tbody tr:not([hidden]) .inc-bulk-chk:checked'))
            .map((c) => c.closest('tr').querySelector('.inc-person')?.textContent.trim()).filter(Boolean);
        const who = names.length <= 5 ? '\n\n' + names.join(', ') : '';
        if (!confirm('Copy ' + ids.length + ' increment(s) from ' + fromLabel + ' to ' + toLabel + '?' + who)) return;
        const btn = document.getElementById('incBulkSave');
        btn.disabled = true;
        incBulkError.hidden = true;
        try {
            const res = await api(`${base}/increments/copy`, 'POST', {
                from_month_id: Number(incBulkFrom.value),
                to_month_id: Number(incBulkTo.value),
                user_ids: ids,
                overwrite: incBulkOverwrite.checked,
            });
            bootstrap.Modal.getInstance(incBulkEl)?.hide();
            const keepSelect = document.getElementById('incrementMonthSelect');
            if (keepSelect) { incrementMonthChosen = true; }
            await loadIncrements();
            // Jump to the month that was just filled so the result is visible.
            if (keepSelect) { keepSelect.value = String(incBulkTo.value); renderIncrements(); }
            salarySheetStale = true;
            alert(res.message || 'Increments copied.');
        } catch (err) {
            incBulkError.textContent = err.message || 'Failed to copy increments.';
            incBulkError.hidden = false;
        } finally {
            btn.disabled = false;
        }
    });

    // Tables built inside hidden tabs need a redraw once their tab becomes visible.
    document.querySelectorAll('#payrollRegionTabs [data-bs-toggle="tab"]').forEach((btn) => {
        btn.addEventListener('shown.bs.tab', () => {
            const isIncrement = btn.dataset.tabKind === 'increment';
            const toolbar = document.getElementById('salaryStatusSection');
            if (toolbar) toolbar.style.display = isIncrement ? 'none' : '';
            if (isIncrement) {
                loadIncrements();
                return;
            }
            if (salarySheetStale) {
                salarySheetStale = false;
                if (currentMonthId()) loadMonth();
            }
            activeSalaryRegion = normalizeRegion(btn.dataset.region || 'india');
            updateRegionUi();
            applyPayrollFilters();
            if (employeesTable) employeesTable.redraw(true);
            if (chinaEmployeesTable) chinaEmployeesTable.redraw(true);
            if (usaEmployeesTable) usaEmployeesTable.redraw(true);
            Object.values(tableInstances).forEach((t) => { try { t.redraw(true); } catch (e) {} });
        });
    });

    updateRegionUi();
    if (currentMonthId()) loadMonth();
})();
</script>
@endsection
